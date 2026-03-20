/*
 * ============================================================
 *  Smart RC Book — ESP8266 Firmware v2.0
 *  COMPLETE REWRITE — implements the full described flow:
 *
 *  1. Boot → WiFi connecting screen
 *  2. Connected → Field group menu (navigate with buttons)
 *  3. Confirmed → "Tap your card" screen
 *  4. Card scanned → API request → structured result display
 *  5. Back button → return to main menu
 *
 *  Hardware:
 *    ESP8266 NodeMCU / Wemos D1 Mini
 *    RC522 RFID reader
 *    SSD1306 1.3" OLED (I2C)
 *    2 push buttons
 *
 *  ── WIRING ──────────────────────────────────────────────────
 *
 *  RC522 RFID → ESP8266
 *    SDA  → D8  (GPIO15)
 *    SCK  → D5  (GPIO14)
 *    MOSI → D7  (GPIO13)
 *    MISO → D6  (GPIO12)
 *    RST  → D3  (GPIO0)
 *    3.3V → 3.3V
 *    GND  → GND
 *
 *  SSD1306 OLED (I2C) → ESP8266
 *    SDA  → D2  (GPIO4)
 *    SCL  → D1  (GPIO5)
 *    VCC  → 3.3V
 *    GND  → GND
 *
 *  BTN_NAV (Next/Select) → D0 (GPIO16)
 *    One leg → D0
 *    Other   → GND
 *    10kΩ resistor between D0 and 3.3V (external pull-up)
 *    Short press (< 800ms) = move to next menu item
 *    Long  press (≥ 800ms) = confirm / select current item
 *
 *  BTN_BACK → D4 (GPIO2)
 *    One leg → D4
 *    Other   → GND
 *    Uses internal pull-up (no external resistor needed)
 *    ⚠ Do NOT hold this button during power-on (D4 must boot HIGH)
 *    Press = go back to main menu from any screen
 *
 *  ── LIBRARIES (Arduino Library Manager) ─────────────────────
 *    MFRC522           by GithubCommunity
 *    Adafruit SSD1306
 *    Adafruit GFX Library
 *    ArduinoJson       v6.x
 *    ESP8266WiFi       (built-in)
 *    ESP8266HTTPClient (built-in)
 * ============================================================
 */

#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <ArduinoJson.h>

// ── USER CONFIG — edit before flashing ────────────────────
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";
const char* SERVER_URL    = "http://192.168.1.100/smartrc/api/rfid.php";
const char* API_KEY       = "smartrc_rfid_2025";

// After this many ms on the result screen, auto-return to ready
const unsigned long RESULT_TIMEOUT_MS = 30000;

// ── PINS ──────────────────────────────────────────────────
#define RST_PIN   0   // RC522 RST → D3 (GPIO0)
#define SS_PIN    15  // RC522 SDA → D8 (GPIO15)
#define BTN_NAV   16  // Nav/Select → D0 (GPIO16)  external pull-up, active HIGH
#define BTN_BACK  2   // Back       → D4 (GPIO2)   internal pull-up, active LOW

// ── OLED ──────────────────────────────────────────────────
#define SCREEN_W 128
#define SCREEN_H 64
Adafruit_SSD1306 display(SCREEN_W, SCREEN_H, &Wire, -1);

// ── RFID ──────────────────────────────────────────────────
MFRC522 rfid(SS_PIN, RST_PIN);

// ── STATE MACHINE ─────────────────────────────────────────
enum State {
  STATE_WIFI,
  STATE_MENU,
  STATE_READY,
  STATE_FETCHING,
  STATE_RESULT,
  STATE_NOT_FOUND,
  STATE_ERROR
};
State currentState = STATE_WIFI;

// ── MENU FIELD GROUPS ─────────────────────────────────────
struct FieldGroup {
  const char* label;
  const char* fields;
};

const FieldGroup MENU[] = {
  { "Vehicle Info",   "vehicle_no,model,fuel_type,color,class"        },
  { "Owner Details",  "owner_name,father_name"                         },
  { "Insurance",      "insurance"                                       },
  { "Fitness Cert",   "fitness"                                         },
  { "Road Tax",       "tax"                                             },
  { "Pollution PUC",  "pollution"                                       },
  { "Compliance",     "insurance,fitness,tax,pollution"                 },
  { "Full Details",   "owner_name,vehicle_no,model,insurance,fitness"  },
};
const int MENU_COUNT   = sizeof(MENU) / sizeof(MENU[0]);
const int MENU_VISIBLE = 4; // rows visible at once (fits with header)

int menuIndex  = 0;
int menuScroll = 0;

// ── RESULT STORE ──────────────────────────────────────────
const int MAX_RESULT_ROWS  = 8;
const int RESULT_ROWS_OLED = 5; // rows that fit below the header

String resultRcNo   = "";
String resultKeys[MAX_RESULT_ROWS];
String resultVals[MAX_RESULT_ROWS];
int    resultCount  = 0;
int    resultScroll = 0;
unsigned long resultShowTime = 0;

// ── ERROR MESSAGE ─────────────────────────────────────────
String errorMsg = "";

// ── BUTTON DEBOUNCE + LONG PRESS ─────────────────────────
const unsigned long DEBOUNCE_MS   = 25;
const unsigned long LONG_PRESS_MS = 800;

bool     navRaw         = false;
bool     backRaw        = false;
bool     navDown        = false;
unsigned long navDownAt = 0;
unsigned long lastNavMs = 0;
unsigned long lastBkMs  = 0;

// Returns: 0=none  1=NAV short  2=NAV long  3=BACK
int readButtons() {
  int event = 0;
  unsigned long now = millis();

  // ── NAV (active HIGH, external pull-up) ──────────────
  bool navNow = (digitalRead(BTN_NAV) == HIGH);
  if (navNow != navRaw && (now - lastNavMs) > DEBOUNCE_MS) {
    navRaw  = navNow;
    lastNavMs = now;
    if (navNow) {
      navDown   = true;
      navDownAt = now;
    } else if (navDown) {
      navDown = false;
      unsigned long held = now - navDownAt;
      event = (held >= LONG_PRESS_MS) ? 2 : 1;
    }
  }

  // ── BACK (active LOW, internal pull-up) ──────────────
  bool bkNow = (digitalRead(BTN_BACK) == LOW);
  if (bkNow != backRaw && (now - lastBkMs) > DEBOUNCE_MS) {
    backRaw = bkNow;
    lastBkMs = now;
    if (bkNow) event = 3;
  }

  return event;
}

// ─────────────────────────────────────────────────────────
//  OLED DRAW HELPERS
//  Layout: y=0 header text | y=10 divider line | y=13+ data rows (10px each)
//  Font size 1 → 6×8px per char, 21 chars per row
// ─────────────────────────────────────────────────────────

void oledHeader(const String& title) {
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  String t = title;
  if (t.length() > 21) t = t.substring(0, 21);
  display.print(t);
  display.drawLine(0, 10, 127, 10, SSD1306_WHITE);
}

// row: 0-based index below the divider
void oledRow(int row, const String& text, bool highlight = false) {
  int y = 13 + row * 10;
  String t = text;
  if (t.length() > 21) t = t.substring(0, 20) + "~";
  if (highlight) {
    display.fillRect(0, y - 1, 128, 10, SSD1306_WHITE);
    display.setTextColor(SSD1306_BLACK);
  } else {
    display.setTextColor(SSD1306_WHITE);
  }
  display.setCursor(0, y);
  display.print(t);
  display.setTextColor(SSD1306_WHITE);
}

// tiny hint in bottom-right corner
void oledHint(const String& h) {
  display.setTextColor(SSD1306_WHITE);
  int x = 128 - h.length() * 6;
  if (x < 0) x = 0;
  display.setCursor(x, 56);
  display.print(h);
}

// draw scrollbar on right edge when more items exist
void oledScrollbar(int total, int visible, int offset) {
  if (total <= visible) return;
  int trackH = SCREEN_H - 13;
  int barH   = max(4, trackH * visible / total);
  int barY   = 13 + trackH * offset / total;
  display.fillRect(126, barY, 2, barH, SSD1306_WHITE);
}

// ─────────────────────────────────────────────────────────
//  SCREEN RENDERERS
// ─────────────────────────────────────────────────────────

void drawWifi(int dots) {
  display.clearDisplay();
  oledHeader("Smart RC Book");
  oledRow(0, "Connecting WiFi...");
  oledRow(1, String(WIFI_SSID));
  String d = "";
  for (int i = 0; i < dots; i++) d += ".";
  oledRow(2, d);
  display.display();
}

void drawWifiOk() {
  display.clearDisplay();
  oledHeader("Smart RC Book");
  oledRow(0, "WiFi Connected!");
  oledRow(1, WiFi.localIP().toString());
  oledRow(2, "Loading menu...");
  display.display();
}

void drawWifiFail() {
  display.clearDisplay();
  oledHeader("WiFi Failed");
  oledRow(0, "Cannot connect:");
  oledRow(1, String(WIFI_SSID));
  oledRow(2, "Restarting in 5s");
  display.display();
}

void drawMenu() {
  display.clearDisplay();
  oledHeader("Select Data Group");
  for (int i = 0; i < MENU_VISIBLE; i++) {
    int idx = menuScroll + i;
    if (idx >= MENU_COUNT) break;
    bool sel = (idx == menuIndex);
    String line = (sel ? ">" : " ");
    line += String(MENU[idx].label);
    oledRow(i, line, sel);
  }
  oledScrollbar(MENU_COUNT, MENU_VISIBLE, menuScroll);
  oledHint("Hold=OK");
  display.display();
}

void drawReady() {
  display.clearDisplay();
  oledHeader("Tap Your Card");
  oledRow(0, "Group:");
  // show selected group name, wrapping if long
  String g = String(MENU[menuIndex].label);
  if (g.length() > 21) {
    oledRow(1, g.substring(0, 21));
    oledRow(2, g.substring(21));
  } else {
    oledRow(1, g);
  }
  oledRow(3, "Ready to scan...");
  oledHint("BACK");
  display.display();
}

void drawFetching(const String& tag) {
  display.clearDisplay();
  oledHeader("Scanning...");
  oledRow(0, "Tag:");
  oledRow(1, tag);
  oledRow(2, "Requesting server");
  display.display();
}

void drawResult() {
  display.clearDisplay();

  // Header: RC number
  String hdr = resultRcNo;
  if (hdr.length() > 21) hdr = hdr.substring(0, 21);
  oledHeader(hdr);

  for (int i = 0; i < RESULT_ROWS_OLED; i++) {
    int idx = resultScroll + i;
    if (idx >= resultCount) break;

    // key: uppercase, max 6 chars
    String k = resultKeys[idx];
    k.toUpperCase();
    if (k.length() > 6) k = k.substring(0, 6);

    String line = k + ": " + resultVals[idx];
    oledRow(i, line);
  }

  oledScrollbar(resultCount, RESULT_ROWS_OLED, resultScroll);

  // hint based on scroll position
  if (resultCount > RESULT_ROWS_OLED) {
    oledHint("v ^ BACK");
  } else {
    oledHint("BACK");
  }
  display.display();
}

void drawNotFound(const String& msg) {
  display.clearDisplay();
  oledHeader("Not Found");
  oledRow(0, "Tag not registered");
  oledRow(1, msg.length() > 21 ? msg.substring(0, 21) : msg);
  oledRow(3, "Try another card");
  oledHint("BACK");
  display.display();
}

void drawError(const String& msg) {
  display.clearDisplay();
  oledHeader("Error");
  oledRow(0, msg.length() > 21 ? msg.substring(0, 21) : msg);
  oledRow(1, "Check WiFi/server");
  oledHint("BACK");
  display.display();
}

// ─────────────────────────────────────────────────────────
//  STATE TRANSITIONS
// ─────────────────────────────────────────────────────────

void goToMenu() {
  resultRcNo   = "";
  resultCount  = 0;
  resultScroll = 0;
  errorMsg     = "";
  currentState = STATE_MENU;
  drawMenu();
}

void goToReady() {
  currentState = STATE_READY;
  drawReady();
}

// ─────────────────────────────────────────────────────────
//  API FETCH
// ─────────────────────────────────────────────────────────

void fetchRC(const String& tag) {
  currentState = STATE_FETCHING;
  drawFetching(tag);

  if (WiFi.status() != WL_CONNECTED) {
    errorMsg     = "No WiFi connection";
    currentState = STATE_ERROR;
    drawError(errorMsg);
    return;
  }

  // URL-encode commas in fields list
  String fields = String(MENU[menuIndex].fields);
  fields.replace(",", "%2C");

  String url = String(SERVER_URL)
    + "?tag="     + tag
    + "&fields="  + fields
    + "&key="     + String(API_KEY)
    + "&compact=1";

  WiFiClient  client;
  HTTPClient  http;
  http.begin(client, url);
  http.setTimeout(6000);

  int code = http.GET();
  if (code != 200) {
    errorMsg = "HTTP " + String(code);
    http.end();
    currentState = STATE_ERROR;
    drawError(errorMsg);
    return;
  }

  String payload = http.getString();
  http.end();

  StaticJsonDocument<1024> doc;
  DeserializationError err = deserializeJson(doc, payload);
  if (err) {
    errorMsg = String("Parse: ") + err.c_str();
    currentState = STATE_ERROR;
    drawError(errorMsg);
    return;
  }

  bool found = doc["found"] | false;
  if (!found) {
    String msg = doc["message"] | "Unknown";
    currentState = STATE_NOT_FOUND;
    drawNotFound(msg);
    return;
  }

  // Store result
  resultRcNo   = doc["rc_no"] | "?";
  resultCount  = 0;
  resultScroll = 0;

  JsonObject data = doc["data"].as<JsonObject>();
  for (JsonPair kv : data) {
    if (resultCount >= MAX_RESULT_ROWS) break;
    resultKeys[resultCount] = String(kv.key().c_str());
    resultVals[resultCount] = kv.value().as<String>();
    resultCount++;
  }

  resultShowTime = millis();
  currentState   = STATE_RESULT;
  drawResult();
}

// ─────────────────────────────────────────────────────────
//  SETUP
// ─────────────────────────────────────────────────────────

void setup() {
  Serial.begin(115200);
  Serial.println("\n\nSmart RC Book v2.0 booting...");

  // Buttons
  pinMode(BTN_NAV,  INPUT);        // external pull-up
  pinMode(BTN_BACK, INPUT_PULLUP); // internal pull-up

  // OLED — SDA=D2(GPIO4), SCL=D1(GPIO5)
  Wire.begin(4, 5);
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println("OLED not found — check wiring");
    while (true) delay(1000);
  }
  display.clearDisplay();
  display.setTextWrap(false);
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(16, 16);
  display.print("Smart RC Book");
  display.setCursor(22, 30);
  display.print("Starting...");
  display.display();
  delay(1000);

  // RFID
  SPI.begin();
  rfid.PCD_Init();
  delay(100);
  Serial.println("RFID ready");

  // WiFi connect loop
  currentState = STATE_WIFI;
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int  attempts = 0;
  int  dots     = 1;

  while (attempts < 40) {
    drawWifi(dots);
    delay(500);
    dots = (dots % 3) + 1;
    attempts++;
    if (WiFi.status() == WL_CONNECTED) break;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("Connected: ");
    Serial.println(WiFi.localIP());
    drawWifiOk();
    delay(1500);
    goToMenu();
  } else {
    Serial.println("WiFi failed — restarting");
    drawWifiFail();
    delay(5000);
    ESP.restart();
  }
}

// ─────────────────────────────────────────────────────────
//  MAIN LOOP
// ─────────────────────────────────────────────────────────

void loop() {
  int btn = readButtons();

  switch (currentState) {

    // ── MENU ──────────────────────────────────────────────
    case STATE_MENU:
      if (btn == 1) {
        // Short press → next item with wrap-around
        menuIndex = (menuIndex + 1) % MENU_COUNT;
        // keep scroll window
        if (menuIndex < menuScroll)
          menuScroll = menuIndex;
        if (menuIndex >= menuScroll + MENU_VISIBLE)
          menuScroll = menuIndex - MENU_VISIBLE + 1;
        drawMenu();
      }
      else if (btn == 2) {
        // Long press → confirm selection, go to ready
        Serial.print("Selected: ");
        Serial.println(MENU[menuIndex].label);
        goToReady();
      }
      else if (btn == 3) {
        // Back in menu → scroll back / reset to top
        if (menuIndex > 0) {
          menuIndex--;
          if (menuIndex < menuScroll) menuScroll = menuIndex;
          drawMenu();
        }
      }
      break;

    // ── READY (waiting for card) ───────────────────────────
    case STATE_READY:
      if (btn == 3 || btn == 1) {
        // back or nav short → back to menu
        goToMenu();
        break;
      }
      // Poll RFID
      if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
        delay(50);
        break;
      }
      {
        String tag = "";
        for (byte i = 0; i < rfid.uid.size; i++) {
          if (rfid.uid.uidByte[i] < 0x10) tag += "0";
          tag += String(rfid.uid.uidByte[i], HEX);
        }
        tag.toUpperCase();
        rfid.PICC_HaltA();
        rfid.PCD_StopCrypto1();
        Serial.println("Tag: " + tag);
        fetchRC(tag);
      }
      break;

    // ── RESULT ────────────────────────────────────────────
    case STATE_RESULT:
      if (btn == 3) {
        goToMenu(); break;
      }
      if (btn == 1) {
        // scroll down
        if (resultScroll + RESULT_ROWS_OLED < resultCount) {
          resultScroll++;
          drawResult();
        } else {
          goToReady(); // at bottom → scan again
        }
        break;
      }
      if (btn == 2) {
        // scroll up
        if (resultScroll > 0) {
          resultScroll--;
          drawResult();
        }
        break;
      }
      // timeout → go back to ready (officer can scan next card)
      if (millis() - resultShowTime > RESULT_TIMEOUT_MS) {
        goToReady();
      }
      break;

    // ── NOT FOUND / ERROR ─────────────────────────────────
    case STATE_NOT_FOUND:
    case STATE_ERROR:
      if (btn != 0) {
        // any button → back to ready to try again
        goToReady();
      }
      break;

    default:
      break;
  }
}
