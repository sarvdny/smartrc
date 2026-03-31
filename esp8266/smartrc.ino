#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

// ── USER CONFIG ──────────────────────────────────────────
const char *WIFI_SSID = "WIFI_SSID";
const char *WIFI_PASSWORD = "WIFI_PASSWORD";
const char *SERVER_URL = "API_URL";
const char *API_KEY = "API_KEY";

// ── HTTPS / SSL CONFIG ───────────────────────────────────
// Option A (recommended): set your server SSL fingerprint.
// Get it with: openssl s_client -connect example.com:443 < /dev/null 2>/dev/null \
//              | openssl x509 -fingerprint -sha1 -noout
// Paste the result below (colons or spaces between hex pairs both work).
// Must update whenever your SSL certificate renews.
//
// Option B (easy but less secure): set USE_HTTPS_INSECURE true.
// This skips certificate verification — still encrypts traffic
// but does not verify the server is who it claims to be.
// Fine for a private IoT device on a trusted network.
const bool USE_HTTPS_INSECURE = true; // set false to use fingerprint
const char *SSL_FINGERPRINT = "AA BB CC DD EE FF 00 11 22 33 44 55 66 77 88 99 AA BB CC DD";

const unsigned long RESULT_TIMEOUT_MS = 30000; // result screen auto-return
const unsigned long CACHE_TTL_MS = 60000;      // cache same card 60s
const unsigned long OLED_SLEEP_MS = 180000;    // OLED off after 3 min
const unsigned long WIFI_RETRY_MS = 10000;     // WiFi retry interval
const unsigned long RFID_POLL_MS = 50;         // FIX 7: RFID poll interval

// ── PINS ─────────────────────────────────────────────────
#define RST_PIN 0  // RC522 RST → D3 (GPIO0)
#define SS_PIN 15  // RC522 SDA → D8 (GPIO15)
#define BTN_NAV 16 // D0 (GPIO16) ext pull-up, active HIGH
#define BTN_BACK 2 // D4 (GPIO2)  int pull-up, active LOW
// BTN_SEL → A0, analogRead < 512 = pressed

// ── OLED ─────────────────────────────────────────────────
#define SCREEN_W 128
#define SCREEN_H 64
Adafruit_SH1106G display(SCREEN_W, SCREEN_H, &Wire, -1);
bool oledAsleep = false;

// ── RFID ─────────────────────────────────────────────────
MFRC522 rfid(SS_PIN, RST_PIN);

// ── STATE MACHINE ────────────────────────────────────────
enum State
{
  STATE_WIFI,
  STATE_MENU,
  STATE_READY,
  STATE_FETCHING,
  STATE_RESULT,
  STATE_NOT_FOUND,
  STATE_ERROR,
  STATE_WIFI_RECONNECTING,
  STATE_SETTINGS,
  STATE_EXPIRED_ALERT
};
State currentState = STATE_WIFI;

// ── MENU ─────────────────────────────────────────────────
// isAction=true items open a special handler instead of going to STATE_READY
struct FieldGroup
{
  const char *label;
  const char *fields;
  bool isAction;
};

const FieldGroup MENU[] = {
    {"Vehicle Info", "vehicle_no,model,fuel_type,color,class", false},
    {"Owner Details", "owner_name,father_name", false},
    {"Insurance", "insurance", false},
    {"Fitness Cert", "fitness", false},
    {"Road Tax", "tax", false},
    {"Pollution PUC", "pollution", false},
    {"Compliance", "insurance,fitness,tax,pollution", false},
    {"Full Details", "owner_name,vehicle_no,model,insurance,fitness", false},
    {"Expired Docs", "insurance,fitness,tax,pollution", false},
    {"Settings", NULL, true},
};
const int MENU_COUNT = sizeof(MENU) / sizeof(MENU[0]);
// MENU_WIFI_IDX removed — was a #define referencing a runtime const int,
// which the AVR/Xtensa preprocessor evaluates to 0. Bug: reconnect option
// always fell through to goToReady(). Now detected via strcmp on fields sentinel.
const int MENU_VISIBLE = 4;
int menuIndex = 0, menuScroll = 0;

// ── RESULT STORE ─────────────────────────────────────────
const int MAX_RESULT_ROWS = 8;
const int RESULT_ROWS_OLED = 5;
char resultRcNo[20] = "";
char resultKeys[MAX_RESULT_ROWS][12]; // FIX 8: char[] not String
char resultVals[MAX_RESULT_ROWS][24]; // FIX 8: char[] not String
int resultCount = 0;
int resultScroll = 0;
bool resultFromCache = false; // FIX 4: explicit cache flag
int expiredCount = 0;         // how many docs are EXP
char expiredFields[32] = "";  // e.g. "Ins,PUC"
unsigned long resultShowTime = 0;

// ── CACHE ────────────────────────────────────────────────
char cachedTag[20] = "";
char cachedRcNo[20] = "";
char cachedKeys[MAX_RESULT_ROWS][12];
char cachedVals[MAX_RESULT_ROWS][24];
int cachedCount = 0;
int cachedMenuIndex = -1; // which field group was cached
unsigned long cachedAt = 0;

// ── SESSION COUNTER ──────────────────────────────────────
int scanCount = 0;

// ── TIMERS ───────────────────────────────────────────────
unsigned long lastActivityMs = 0;
unsigned long lastWifiRetryMs = 0;
unsigned long lastRfidPollMs = 0; // FIX 7

// ── ERROR STRING ─────────────────────────────────────────
char errorMsg[32] = "";

// ── WIFI RECONNECT STATE (globals — reset explicitly each use) ──
bool wifiReconnectActive = false;
bool wifiJustConnected = false; // latched once on connect
unsigned long wifiReconnectStartMs = 0;
unsigned long wifiSuccessShowMs = 0;
int wifiDots = 1;
unsigned long wifiDotMs = 0;

// ── SETTINGS STATE ───────────────────────────────────────
struct SettingItem
{
  const char *label;
  const char *value;
};

// Settings sub-menu index
int settingsIndex = 0;
int settingsScroll = 0;
const int SETTINGS_VISIBLE = 4;

// Persistent settings (survive between menu visits)
bool settingCacheEnabled = true;
uint8_t settingBrightness = 255; // 0–255, SH110X contrast

// Settings action flags
bool settingsServerChecking = false;
char settingsMsg[32] = "";
unsigned long settingsMsgMs = 0;

// ─────────────────────────────────────────────────────────
//  FIX 3: FORWARD DECLARATIONS
//  bumpActivity() calls draw functions defined later —
//  forward-declare them so the compiler knows their signatures.
// ─────────────────────────────────────────────────────────
void drawMenu();
void drawReady();
void drawResult();
void drawFetching(const char *tag, int frame);
void drawNotFound(const char *msg, const char *tag);
void drawError(const char *msg);
void goToMenu();
void goToReady();
void fetchRC(const char *tag);
void drawWifiReconnecting(int dots, bool success);
void drawSettings();
void goToSettings();
void execSetting(int idx);
void drawExpiredAlert();
void goToExpiredAlert();

// ─────────────────────────────────────────────────────────
//  FIX 1 + FIX 2: BUTTON EVENT QUEUE
//
//  Old code used a single int — simultaneous presses overwrote
//  each other. New code uses a small circular queue so no event
//  is ever lost.
//
//  FIX 2: All three buttons now fire on PRESS (not release).
//  Previously NAV fired on release (~50-100ms later than SEL/BACK),
//  making the device feel inconsistent. Now all are instant.
// ─────────────────────────────────────────────────────────
#define BTN_QUEUE_SIZE 4

volatile int btnQueue[BTN_QUEUE_SIZE];
volatile int btnQHead = 0;
volatile int btnQTail = 0;

void enqueueBtn(int evt)
{
  int next = (btnQTail + 1) % BTN_QUEUE_SIZE;
  if (next != btnQHead)
  { // don't overwrite if full
    btnQueue[btnQTail] = evt;
    btnQTail = next;
  }
}

int dequeueBtn()
{
  if (btnQHead == btnQTail)
    return 0;
  int evt = btnQueue[btnQHead];
  btnQHead = (btnQHead + 1) % BTN_QUEUE_SIZE;
  return evt;
}

// Debounce state
bool navRaw = false, backRaw = false, selRaw = false;
unsigned long lastNavMs = 0, lastBkMs = 0, lastSelMs = 0;
const unsigned long DEBOUNCE_MS = 25;

void pollButtons()
{
  unsigned long now = millis();

  // NAV — D0, active HIGH, fire on PRESS (FIX 2)
  bool navNow = (digitalRead(BTN_NAV) == HIGH);
  if (navNow != navRaw && (now - lastNavMs) > DEBOUNCE_MS)
  {
    navRaw = navNow;
    lastNavMs = now;
    if (navNow)
      enqueueBtn(1); // press (was: release)
  }

  // SEL — A0, active LOW via pull-up, fire on press
  bool selNow = (analogRead(A0) < 512);
  if (selNow != selRaw && (now - lastSelMs) > DEBOUNCE_MS)
  {
    selRaw = selNow;
    lastSelMs = now;
    if (selNow)
      enqueueBtn(2);
  }

  // BACK — D4, active LOW, fire on press
  bool bkNow = (digitalRead(BTN_BACK) == LOW);
  if (bkNow != backRaw && (now - lastBkMs) > DEBOUNCE_MS)
  {
    backRaw = bkNow;
    lastBkMs = now;
    if (bkNow)
      enqueueBtn(3);
  }
}

// ─────────────────────────────────────────────────────────
//  ACTIVITY TRACKER
// ─────────────────────────────────────────────────────────
void bumpActivity()
{
  lastActivityMs = millis();
  if (oledAsleep)
  {
    display.oled_command(SH110X_DISPLAYON);
    oledAsleep = false;
    switch (currentState)
    {
    case STATE_MENU:
      drawMenu();
      break;
    case STATE_READY:
      drawReady();
      break;
    case STATE_RESULT:
      drawResult();
      break;
    // Fix 3: redraw reconnect screen on OLED wake
    case STATE_WIFI_RECONNECTING:
      drawWifiReconnecting(wifiDots, wifiJustConnected);
      break;
    case STATE_SETTINGS:
      drawSettings();
      break;
    case STATE_EXPIRED_ALERT:
      drawExpiredAlert();
      break;
    default:
      break;
    }
  }
}

// ─────────────────────────────────────────────────────────
//  OLED HELPERS
// ─────────────────────────────────────────────────────────
void oledHeader(const char *title)
{
  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);
  display.setCursor(0, 0);
  // truncate to 18 chars — leaves room for WiFi indicator at x=116
  char buf[19];
  strncpy(buf, title, 18);
  buf[18] = '\0';
  display.print(buf);
  display.drawLine(0, 10, 127, 10, SH110X_WHITE);
}

void oledRow(int row, const char *text, bool highlight = false)
{
  int y = 13 + row * 10;
  if (highlight)
  {
    display.fillRect(0, y - 1, 128, 10, SH110X_WHITE);
    display.setTextColor(SH110X_BLACK);
  }
  else
  {
    display.setTextColor(SH110X_WHITE);
  }
  display.setCursor(0, y);
  // truncate to 21 chars (128px / 6px per char)
  char buf[22];
  strncpy(buf, text, 21);
  buf[21] = '\0';
  if (strlen(text) > 21)
    buf[20] = '~';
  display.print(buf);
  display.setTextColor(SH110X_WHITE);
}

void oledHint(const char *h)
{
  int len = strlen(h);
  int x = 128 - len * 6;
  if (x < 0)
    x = 0;
  display.setTextColor(SH110X_WHITE);
  display.setCursor(x, 56);
  display.print(h);
}

void oledScrollbar(int total, int visible, int offset)
{
  if (total <= visible)
    return;
  int trackH = SCREEN_H - 13;
  int barH = max(4, trackH * visible / total);
  int barY = 13 + trackH * offset / total;
  // Fix 4: was x=126 — overlapped last pixel of hint text (e.g. "BACK")
  display.fillRect(125, barY, 2, barH, SH110X_WHITE);
}

void oledWifiDot()
{
  display.setTextColor(SH110X_WHITE);
  display.setCursor(116, 0);
  display.print(WiFi.status() == WL_CONNECTED ? "W" : "~");
}

// ─────────────────────────────────────────────────────────
//  SCREEN RENDERERS
// ─────────────────────────────────────────────────────────
void drawWifi(int dots)
{
  display.clearDisplay();
  oledHeader("Smart RC Book");
  oledRow(0, "Connecting WiFi...");
  oledRow(1, WIFI_SSID);
  char d[4] = "";
  for (int i = 0; i < dots && i < 3; i++)
    d[i] = '.';
  oledRow(2, d);
  display.display();
}

void drawWifiOk()
{
  display.clearDisplay();
  oledHeader("Smart RC Book");
  // Fix 6: store IP in local buffer — .c_str() into a temporary is UB
  char ipBuf[20];
  strncpy(ipBuf, WiFi.localIP().toString().c_str(), sizeof(ipBuf) - 1);
  ipBuf[sizeof(ipBuf) - 1] = '\0';
  oledRow(0, "WiFi Connected!");
  oledRow(1, ipBuf);
  oledRow(2, "Checking server...");
  display.display();
}

void drawWifiFail()
{
  display.clearDisplay();
  oledHeader("WiFi Failed");
  oledRow(0, "Cannot connect:");
  oledRow(1, WIFI_SSID);
  oledRow(2, "Restarting in 5s");
  display.display();
}

void drawServerCheck(bool ok, const char *detail)
{
  display.clearDisplay();
  oledHeader("Server Check");
  oledRow(0, ok ? "Server OK!" : "Server Error");
  oledRow(1, detail);
  oledRow(2, ok ? "Starting..." : "Check SERVER_URL");
  display.display();
}

void drawWifiReconnecting(int dots, bool success)
{
  display.clearDisplay();
  if (success)
  {
    oledHeader("WiFi Connected");
    char ipBuf2[20];
    strncpy(ipBuf2, WiFi.localIP().toString().c_str(), sizeof(ipBuf2) - 1);
    ipBuf2[sizeof(ipBuf2) - 1] = '\0';
    oledRow(0, "Reconnected!");
    oledRow(1, ipBuf2);
    oledRow(2, "Returning to menu");
  }
  else
  {
    oledHeader("Reconnecting...");
    oledRow(0, WIFI_SSID);
    char d[4] = "";
    for (int i = 0; i < dots && i < 3; i++)
      d[i] = '.';
    oledRow(1, d);
    oledRow(2, "Press BACK to cancel");
  }
  display.display();
}

void drawMenu()
{
  display.clearDisplay();
  oledHeader("Select Data");
  oledWifiDot();
  for (int i = 0; i < MENU_VISIBLE; i++)
  {
    int idx = menuScroll + i;
    if (idx >= MENU_COUNT)
      break;
    bool sel = (idx == menuIndex);
    char line[22];
    snprintf(line, sizeof(line), "%c%s", sel ? '>' : ' ', MENU[idx].label);
    oledRow(i, line, sel);
  }
  oledScrollbar(MENU_COUNT, MENU_VISIBLE, menuScroll);
  oledHint("SEL=OK");
  display.display();
}

void drawReady()
{
  display.clearDisplay();
  oledHeader("Tap Your Card");
  oledWifiDot();
  oledRow(0, "Group:");
  oledRow(1, MENU[menuIndex].label);
  char scanLine[22];
  snprintf(scanLine, sizeof(scanLine), "Scans: %d", scanCount);
  oledRow(2, scanLine);
  oledRow(3, "Ready to scan...");
  oledHint("BACK");
  display.display();
}

const char SPIN[] = {'|', '/', '-', '\\'};

void drawFetching(const char *tag, int frame)
{
  display.clearDisplay();
  oledHeader("Fetching...");
  display.setCursor(120, 0);
  display.print(SPIN[frame & 3]);
  char tagLine[22];
  snprintf(tagLine, sizeof(tagLine), "Tag: %s", tag);
  oledRow(0, tagLine);
  oledRow(1, "Contacting server");
  oledRow(2, "Please wait...");
  display.display();
}

void drawResult()
{
  display.clearDisplay();
  // FIX 4: use explicit resultFromCache flag — not a comparison
  // that was always true after a fresh fetch set both equal
  char hdr[20];
  if (resultFromCache)
  {
    snprintf(hdr, sizeof(hdr), "%.14s [C]", resultRcNo);
  }
  else
  {
    strncpy(hdr, resultRcNo, 19);
    hdr[19] = '\0';
  }
  oledHeader(hdr);
  oledWifiDot();

  for (int i = 0; i < RESULT_ROWS_OLED; i++)
  {
    int idx = resultScroll + i;
    if (idx >= resultCount)
      break;
    char k[7];
    strncpy(k, resultKeys[idx], 6);
    k[6] = '\0';
    for (int j = 0; j < 6 && k[j]; j++)
      k[j] = toupper(k[j]);
    char line[22];
    snprintf(line, sizeof(line), "%s: %s", k, resultVals[idx]);
    // Highlight EXP rows with inversion so officer sees them instantly
    bool isExp = (strcmp(resultVals[idx], "EXP") == 0);
    oledRow(i, line, isExp);
  }
  oledScrollbar(resultCount, RESULT_ROWS_OLED, resultScroll);
  oledHint(resultCount > RESULT_ROWS_OLED ? "v ^ BK" : "BACK");
  display.display();
}

// FIX 5: accept tag param so UID is shown on row 2 for debugging
void drawNotFound(const char *msg, const char *tag)
{
  display.clearDisplay();
  oledHeader("Not Found");
  oledRow(0, "Tag not registered");
  oledRow(1, (msg && msg[0]) ? msg : "Check rfid_tag col");
  // FIX 5: show the scanned UID so officer can report it
  char uidLine[22];
  snprintf(uidLine, sizeof(uidLine), "UID: %s", tag ? tag : "?");
  oledRow(2, uidLine);
  oledRow(3, "Try another card");
  oledHint("BACK");
  display.display();
}

void drawError(const char *msg)
{
  display.clearDisplay();
  oledHeader("Error");
  oledRow(0, msg);
  oledRow(1, "Check WiFi/server");
  oledHint("BACK");
  display.display();
}

// ─────────────────────────────────────────────────────────
//  EXPIRED ALERT SCREEN
//  Shown when expired_count > 0. Large warning, lists which
//  docs are expired. SEL or NAV → proceed to full result.
//  BACK → return to menu.
// ─────────────────────────────────────────────────────────
void drawExpiredAlert()
{
  display.clearDisplay();

  // Header with inverted background for maximum urgency
  display.fillRect(0, 0, 128, 11, SH110X_WHITE);
  display.setTextColor(SH110X_BLACK);
  display.setTextSize(1);
  display.setCursor(2, 1);
  display.print("!!! EXPIRED DOCS !!!");
  display.setTextColor(SH110X_WHITE);

  // Big expired count in centre
  display.setTextSize(2);
  char countLine[8];
  snprintf(countLine, sizeof(countLine), "%d EXP", expiredCount);
  int cx = (128 - strlen(countLine) * 12) / 2;
  if (cx < 0)
    cx = 0;
  display.setCursor(cx, 16);
  display.print(countLine);
  display.setTextSize(1);

  // Which docs are expired
  char expLine[22];
  snprintf(expLine, sizeof(expLine), "%.21s", expiredFields);
  display.setCursor(0, 38);
  display.print(expLine);

  // RC number
  display.setCursor(0, 50);
  char rcLine[22];
  snprintf(rcLine, sizeof(rcLine), "%.21s", resultRcNo);
  display.print(rcLine);

  oledHint("SEL=details");
  display.display();
}

// ─────────────────────────────────────────────────────────
//  SETTINGS — 6 items, each with a live value line
// ─────────────────────────────────────────────────────────
const char *settingLabels[] = {
    "WiFi Reconnect",
    "WiFi Info",
    "Clear Cache",
    "Server Check",
    "Brightness",
    "Device Info",
};
const int SETTINGS_COUNT = 6;

// Build value string for each setting item
void settingValueStr(int idx, char *out, int outLen)
{
  switch (idx)
  {
  case 0: // WiFi Reconnect
    strncpy(out, WiFi.status() == WL_CONNECTED ? "Connected" : "Disconnected", outLen - 1);
    break;
  case 1: // WiFi Info
    if (WiFi.status() == WL_CONNECTED)
    {
      char ipBuf[16];
      strncpy(ipBuf, WiFi.localIP().toString().c_str(), 15);
      ipBuf[15] = '\0';
      snprintf(out, outLen, "RSSI:%ddBm", WiFi.RSSI());
    }
    else
    {
      strncpy(out, "Not connected", outLen - 1);
    }
    break;
  case 2: // Clear Cache
    if (cachedCount > 0)
    {
      snprintf(out, outLen, "Cached: %s", cachedRcNo);
    }
    else
    {
      strncpy(out, "Cache empty", outLen - 1);
    }
    break;
  case 3: // Server Check
    strncpy(out, settingsMsg[0] ? settingsMsg : "Press SEL", outLen - 1);
    break;
  case 4: // Brightness
    snprintf(out, outLen, "%d%%", (int)(settingBrightness / 2.55f));
    break;
  case 5: // Device Info
    snprintf(out, outLen, "Heap:%uB", ESP.getFreeHeap());
    break;
  default:
    strncpy(out, "", outLen - 1);
  }
  out[outLen - 1] = '\0';
}

void drawSettings()
{
  display.clearDisplay();
  oledHeader("Settings");
  oledWifiDot();

  for (int i = 0; i < SETTINGS_VISIBLE; i++)
  {
    int idx = settingsScroll + i;
    if (idx >= SETTINGS_COUNT)
      break;
    bool sel = (idx == settingsIndex);

    // Row A: ">Label" or " Label"
    char rowA[22];
    snprintf(rowA, sizeof(rowA), "%c%s", sel ? '>' : ' ', settingLabels[idx]);
    oledRow(i, rowA, sel);
  }

  oledScrollbar(SETTINGS_COUNT, SETTINGS_VISIBLE, settingsScroll);
  oledHint("SEL=run");
  display.display();
}

// Draw a full-screen info panel for WiFi Info / Device Info
void drawSettingsInfo(const char *title,
                      const char *l0, const char *l1,
                      const char *l2, const char *l3)
{
  display.clearDisplay();
  oledHeader(title);
  if (l0)
    oledRow(0, l0);
  if (l1)
    oledRow(1, l1);
  if (l2)
    oledRow(2, l2);
  if (l3)
    oledRow(3, l3);
  oledHint("BACK");
  display.display();
}

void goToSettings()
{
  settingsIndex = 0;
  settingsScroll = 0;
  settingsMsg[0] = '\0';
  settingsMsgMs = 0;
  currentState = STATE_SETTINGS;
  drawSettings();
}

void execSetting(int idx)
{
  switch (idx)
  {

  case 0: // ── WiFi Reconnect ──────────────────────────
    wifiReconnectActive = true;
    wifiJustConnected = false;
    wifiReconnectStartMs = millis();
    wifiSuccessShowMs = 0;
    wifiDots = 1;
    wifiDotMs = 0;
    currentState = STATE_WIFI_RECONNECTING;
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    drawWifiReconnecting(1, false);
    break;

  case 1:
  { // ── WiFi Info ───────────────────────────────
    char ssidBuf[22], ipBuf[20], rssi[16], mac[20];
    snprintf(ssidBuf, sizeof(ssidBuf), "SSID: %s", WIFI_SSID);
    if (WiFi.status() == WL_CONNECTED)
    {
      strncpy(ipBuf, WiFi.localIP().toString().c_str(), 19);
      ipBuf[19] = '\0';
      snprintf(rssi, sizeof(rssi), "RSSI: %d dBm", WiFi.RSSI());
      snprintf(mac, sizeof(mac), "MAC: %s", WiFi.macAddress().c_str());
    }
    else
    {
      strncpy(ipBuf, "Not connected", 19);
      strncpy(rssi, "", 15);
      strncpy(mac, "", 19);
    }
    drawSettingsInfo("WiFi Info", ssidBuf, ipBuf, rssi, mac);
    // Stay showing info until BACK pressed — loop handles this via
    // STATE_SETTINGS + settingsMsgMs timer approach
    // We set a long display timer so BACK is the only exit
    settingsMsgMs = millis();
    break;
  }

  case 2: // ── Clear Cache ─────────────────────────────
    cachedTag[0] = '\0';
    cachedRcNo[0] = '\0';
    cachedCount = 0;
    cachedMenuIndex = -1;
    cachedAt = 0;
    strncpy(settingsMsg, "Cache cleared!", sizeof(settingsMsg) - 1);
    settingsMsgMs = millis();
    drawSettings();
    break;

  case 3: // ── Server Check ────────────────────────────
    strncpy(settingsMsg, "Checking...", sizeof(settingsMsg) - 1);
    drawSettings();
    {
      bool ok = checkServer();
      strncpy(settingsMsg, ok ? "Server OK!" : "Unreachable!", sizeof(settingsMsg) - 1);
      settingsMsgMs = millis();
    }
    drawSettings();
    break;

  case 4: // ── Brightness cycle: 100% → 50% → 25% → 100% ──
    if (settingBrightness == 255)
      settingBrightness = 128;
    else if (settingBrightness == 128)
      settingBrightness = 64;
    else
      settingBrightness = 255;
    display.setContrast(settingBrightness);
    strncpy(settingsMsg, "Brightness set", sizeof(settingsMsg) - 1);
    settingsMsgMs = millis();
    drawSettings();
    break;

  case 5:
  { // ── Device Info ──────────────────────────────
    char heap[20], ver[20], scans[20], uptime[20];
    snprintf(heap, sizeof(heap), "Heap: %u B", ESP.getFreeHeap());
    snprintf(ver, sizeof(ver), "FW: v3.1");
    snprintf(scans, sizeof(scans), "Scans: %d", scanCount);
    unsigned long secs = millis() / 1000;
    snprintf(uptime, sizeof(uptime), "Up: %lum %lus", secs / 60, secs % 60);
    drawSettingsInfo("Device Info", heap, ver, scans, uptime);
    settingsMsgMs = millis();
    break;
  }
  }
}

// ─────────────────────────────────────────────────────────
//  STATE TRANSITIONS
// ─────────────────────────────────────────────────────────
void goToMenu()
{
  resultRcNo[0] = '\0';
  resultCount = 0;
  resultScroll = 0;
  resultFromCache = false;
  errorMsg[0] = '\0';
  currentState = STATE_MENU;
  drawMenu();
}

void goToReady()
{
  currentState = STATE_READY;
  drawReady();
}

void goToExpiredAlert()
{
  currentState = STATE_EXPIRED_ALERT;
  drawExpiredAlert();
}

// ─────────────────────────────────────────────────────────
//  SERVER CHECK
// ─────────────────────────────────────────────────────────
bool checkServer()
{
  if (WiFi.status() != WL_CONNECTED)
    return false;
  char url[128];
  snprintf(url, sizeof(url), "%s?key=healthcheck", SERVER_URL);
  WiFiClientSecure client;
  if (USE_HTTPS_INSECURE)
  {
    client.setInsecure(); // skip cert verification
  }
  else
  {
    client.setFingerprint(SSL_FINGERPRINT);
  }
  HTTPClient http;
  http.begin(client, url);
  http.setTimeout(5000);
  int code = http.GET();
  http.end();
  return (code > 0);
}

// ─────────────────────────────────────────────────────────
//  FIX 6: WIFI RECONNECT
//  Old code called WiFi.disconnect() + WiFi.begin() every 10s
//  even while the stack was mid-reconnect. This reset the attempt.
//  New code only intervenes when WiFi is truly idle/failed.
// ─────────────────────────────────────────────────────────
void handleWifiReconnect()
{
  wl_status_t status = WiFi.status();
  if (status == WL_CONNECTED)
    return;

  // Only retry if truly stuck — not while actively trying
  if (status == WL_DISCONNECTED || status == WL_NO_SSID_AVAIL ||
      status == WL_CONNECT_FAILED)
  {
    if (millis() - lastWifiRetryMs >= WIFI_RETRY_MS)
    {
      lastWifiRetryMs = millis();
      Serial.println("WiFi retry...");
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    }
  }
  // WL_IDLE_STATUS / WL_NO_SHIELD / mid-connect: leave it alone
}

// ─────────────────────────────────────────────────────────
//  FETCH RC — cache + char[] buffers (FIX 8)
// ─────────────────────────────────────────────────────────
void fetchRC(const char *tag)
{

  // Cache check — tag AND menu group must both match.
  // Bug was: only tag was checked, so changing the field
  // group and scanning the same card returned stale data.
  if (strlen(cachedTag) > 0 &&
      strcmp(tag, cachedTag) == 0 &&
      menuIndex == cachedMenuIndex &&
      cachedCount > 0 &&
      millis() - cachedAt < CACHE_TTL_MS)
  {
    Serial.print("Cache hit: ");
    Serial.println(tag);
    strncpy(resultRcNo, cachedRcNo, sizeof(resultRcNo) - 1);
    resultCount = cachedCount;
    resultScroll = 0;
    resultFromCache = true; // FIX 4
    for (int i = 0; i < cachedCount; i++)
    {
      strncpy(resultKeys[i], cachedKeys[i], sizeof(resultKeys[i]) - 1);
      strncpy(resultVals[i], cachedVals[i], sizeof(resultVals[i]) - 1);
    }
    scanCount++;
    resultShowTime = millis();
    currentState = STATE_RESULT;
    drawResult();
    return;
  }

  currentState = STATE_FETCHING;
  drawFetching(tag, 0);

  if (WiFi.status() != WL_CONNECTED)
  {
    strncpy(errorMsg, "No WiFi", sizeof(errorMsg) - 1);
    currentState = STATE_ERROR;
    drawError(errorMsg);
    return;
  }

  // Safety guard: action items must never reach fetchRC
  if (MENU[menuIndex].isAction || MENU[menuIndex].fields == NULL)
  {
    goToMenu();
    return;
  }
  // FIX 8: build URL in a char[] — no String heap allocation
  char fields[80];
  strncpy(fields, MENU[menuIndex].fields, sizeof(fields) - 1);
  fields[sizeof(fields) - 1] = '\0';
  // URL-encode commas
  char fieldsEnc[160] = "";
  // Fix 1: guard was j<158 — comma at j=157 wrote fieldsEnc[160] (OOB)
  // Fix: j<156 ensures comma expansion (3 bytes) + null stay within [0..159]
  for (int i = 0, j = 0; fields[i] && j < 156; i++)
  {
    if (fields[i] == ',')
    {
      fieldsEnc[j++] = '%';
      fieldsEnc[j++] = '2';
      fieldsEnc[j++] = 'C';
    }
    else
    {
      fieldsEnc[j++] = fields[i];
    }
    fieldsEnc[j] = '\0';
  }

  char url[256];
  snprintf(url, sizeof(url), "%s?tag=%s&fields=%s&key=%s&compact=1",
           SERVER_URL, tag, fieldsEnc, API_KEY);

  WiFiClientSecure client;
  if (USE_HTTPS_INSECURE)
  {
    client.setInsecure(); // skip cert verification
  }
  else
  {
    client.setFingerprint(SSL_FINGERPRINT);
  }
  HTTPClient http;
  http.begin(client, url);
  http.setTimeout(8000); // HTTPS handshake needs more time

  drawFetching(tag, 1);
  int code = http.GET();
  drawFetching(tag, 2);

  if (code != 200)
  {
    snprintf(errorMsg, sizeof(errorMsg), "HTTP %d", code);
    http.end();
    currentState = STATE_ERROR;
    drawError(errorMsg);
    return;
  }

  String payload = http.getString(); // HTTPClient requires String here
  http.end();

  StaticJsonDocument<1024> doc;
  DeserializationError err = deserializeJson(doc, payload);
  if (err)
  {
    strncpy(errorMsg, "Parse error", sizeof(errorMsg) - 1);
    currentState = STATE_ERROR;
    drawError(errorMsg);
    return;
  }

  bool found = doc["found"] | false;
  if (!found)
  {
    const char *msg = doc["message"] | "Unknown";
    currentState = STATE_NOT_FOUND;
    drawNotFound(msg, tag); // FIX 5: pass tag so UID shows on screen
    return;
  }

  // Store result in char[] buffers (FIX 8)
  const char *rcNo = doc["rc_no"] | "?";
  strncpy(resultRcNo, rcNo, sizeof(resultRcNo) - 1);
  resultRcNo[sizeof(resultRcNo) - 1] = '\0';

  resultCount = 0;
  resultScroll = 0;
  resultFromCache = false;

  // Read expired document info from API response
  expiredCount = doc["expired_count"] | 0;
  const char *expFlds = doc["expired_fields"] | "";
  strncpy(expiredFields, expFlds, sizeof(expiredFields) - 1);
  expiredFields[sizeof(expiredFields) - 1] = '\0';

  JsonObject data = doc["data"].as<JsonObject>();
  for (JsonPair kv : data)
  {
    if (resultCount >= MAX_RESULT_ROWS)
      break;
    strncpy(resultKeys[resultCount], kv.key().c_str(),
            sizeof(resultKeys[0]) - 1);
    resultKeys[resultCount][sizeof(resultKeys[0]) - 1] = '\0';
    // Fix 2: evaluate once into local
    const char *valStr = kv.value().as<const char *>();
    strncpy(resultVals[resultCount], valStr ? valStr : "",
            sizeof(resultVals[0]) - 1);
    resultVals[resultCount][sizeof(resultVals[0]) - 1] = '\0';
    resultCount++;
  }

  // Populate cache
  strncpy(cachedTag, tag, sizeof(cachedTag) - 1);
  strncpy(cachedRcNo, resultRcNo, sizeof(cachedRcNo) - 1);
  cachedCount = resultCount;
  cachedMenuIndex = menuIndex;
  cachedAt = millis();
  for (int i = 0; i < resultCount; i++)
  {
    strncpy(cachedKeys[i], resultKeys[i], sizeof(cachedKeys[i]) - 1);
    strncpy(cachedVals[i], resultVals[i], sizeof(cachedVals[i]) - 1);
  }

  scanCount++;
  resultShowTime = millis();

  // If any compliance doc is expired AND user used Expired Docs / Compliance
  // show the alert screen first — officer sees the warning immediately
  if (expiredCount > 0)
  {
    goToExpiredAlert();
  }
  else
  {
    currentState = STATE_RESULT;
    drawResult();
  }
}

// ─────────────────────────────────────────────────────────
//  SETUP
// ─────────────────────────────────────────────────────────
void setup()
{
  Serial.begin(115200);
  Serial.println("\nSmart RC Book v3.2");

  pinMode(BTN_NAV, INPUT);         // external pull-up
  pinMode(BTN_BACK, INPUT_PULLUP); // internal pull-up

  Wire.begin(4, 5);
  if (!display.begin(0x3C, true))
  {
    Serial.println("OLED not found");
    while (true)
      delay(1000);
  }
  display.clearDisplay();
  display.setTextWrap(false);
  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);
  display.setCursor(10, 16);
  display.print("Smart RC Book v3.2");
  display.setCursor(22, 30);
  display.print("Starting...");
  display.display();
  delay(1000);

  SPI.begin();
  rfid.PCD_Init();
  delay(100);
  Serial.println("RFID ready");

  currentState = STATE_WIFI;
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0, dots = 1;
  while (attempts < 40)
  {
    drawWifi(dots);
    delay(500);
    dots = (dots % 3) + 1;
    attempts++;
    if (WiFi.status() == WL_CONNECTED)
      break;
  }

  if (WiFi.status() != WL_CONNECTED)
  {
    drawWifiFail();
    delay(5000);
    ESP.restart();
  }

  Serial.print("WiFi: ");
  Serial.println(WiFi.localIP());
  drawWifiOk();
  delay(1000);

  // Fix 6: store IP in local buffer before passing to drawServerCheck
  char svrIpBuf[20];
  strncpy(svrIpBuf, WiFi.localIP().toString().c_str(), sizeof(svrIpBuf) - 1);
  svrIpBuf[sizeof(svrIpBuf) - 1] = '\0';
  bool svrOk = checkServer();
  drawServerCheck(svrOk, svrOk ? svrIpBuf : "Unreachable");
  delay(1500);

  lastActivityMs = millis();
  goToMenu();
}

// ─────────────────────────────────────────────────────────
//  MAIN LOOP
// ─────────────────────────────────────────────────────────
void loop()
{
  // Poll buttons into queue every iteration
  pollButtons();

  // Drain ONE event per loop tick (process in order)
  int btn = dequeueBtn();

  if (btn != 0)
    bumpActivity();

  // OLED sleep check
  if (!oledAsleep && millis() - lastActivityMs > OLED_SLEEP_MS)
  {
    display.oled_command(SH110X_DISPLAYOFF);
    oledAsleep = true;
    Serial.println("OLED sleep");
  }

  // FIX 6: smarter WiFi reconnect
  // Bug: was running even during STATE_WIFI_RECONNECTING,
  // calling WiFi.begin() on top of the manual reconnect attempt.
  if (currentState != STATE_WIFI_RECONNECTING)
  {
    handleWifiReconnect();
  }

  switch (currentState)
  {

  case STATE_MENU:
    if (btn == 1)
    {
      menuIndex = (menuIndex + 1) % MENU_COUNT;
      if (menuIndex < menuScroll)
        menuScroll = menuIndex;
      if (menuIndex >= menuScroll + MENU_VISIBLE)
        menuScroll = menuIndex - MENU_VISIBLE + 1;
      drawMenu();
    }
    else if (btn == 2)
    {
      Serial.print("Selected: ");
      Serial.println(MENU[menuIndex].label);
      // isAction items go to special handlers, not STATE_READY
      if (MENU[menuIndex].isAction)
      {
        // Reset all reconnect globals before entering the state
        // "Settings" is the only action item in the main menu
        goToSettings();
      }
      else
      {
        goToReady();
      }
    }
    else if (btn == 3)
    {
      if (menuIndex > 0)
      {
        menuIndex--;
        if (menuIndex < menuScroll)
          menuScroll = menuIndex;
        drawMenu();
      }
    }
    break;

  case STATE_READY:
    if (btn == 3)
    {
      goToMenu();
      break;
    }
    if (btn == 1 || btn == 2)
    {
      goToMenu();
      break;
    }

    // FIX 7: non-blocking RFID poll using millis()
    // Old: delay(50) every loop → missed buttons + blocked timers
    // New: check elapsed time, only poll when interval has passed
    if (millis() - lastRfidPollMs >= RFID_POLL_MS)
    {
      lastRfidPollMs = millis();
      if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial())
      {
        // FIX 8: tag in char[] buffer
        char tag[20] = "";
        for (byte i = 0; i < rfid.uid.size && i < 9; i++)
        {
          char hex[3];
          snprintf(hex, sizeof(hex), "%02X", rfid.uid.uidByte[i]);
          strncat(tag, hex, sizeof(tag) - strlen(tag) - 1);
        }
        rfid.PICC_HaltA();
        rfid.PCD_StopCrypto1();
        Serial.print("Tag: ");
        Serial.println(tag);
        bumpActivity();
        fetchRC(tag);
      }
    }
    break;

  case STATE_RESULT:
    if (btn == 3)
    {
      goToMenu();
      break;
    }
    if (btn == 1)
    {
      if (resultScroll + RESULT_ROWS_OLED < resultCount)
      {
        resultScroll++;
        drawResult();
      }
      else
      {
        goToReady();
      }
      break;
    }
    if (btn == 2)
    {
      if (resultScroll > 0)
      {
        resultScroll--;
        drawResult();
      }
      break;
    }
    if (millis() - resultShowTime > RESULT_TIMEOUT_MS)
      goToReady();
    break;

  case STATE_NOT_FOUND:
  case STATE_ERROR:
    if (btn != 0)
      goToReady();
    break;

  case STATE_EXPIRED_ALERT:
    if (btn == 3)
    {
      goToMenu();
      break;
    } // BACK → menu
    if (btn == 1 || btn == 2)
    {
      // NAV or SEL → proceed to full result details
      currentState = STATE_RESULT;
      drawResult();
    }
    break;

  case STATE_SETTINGS:
    if (btn == 3)
    {
      goToMenu();
      break;
    } // BACK → main menu
    if (btn == 1)
    {
      // NAV → next setting
      settingsIndex = (settingsIndex + 1) % SETTINGS_COUNT;
      if (settingsIndex < settingsScroll)
        settingsScroll = settingsIndex;
      if (settingsIndex >= settingsScroll + SETTINGS_VISIBLE)
        settingsScroll = settingsIndex - SETTINGS_VISIBLE + 1;
      settingsMsg[0] = '\0';
      drawSettings();
      break;
    }
    if (btn == 2)
    {
      // SEL → execute selected setting
      execSetting(settingsIndex);
      break;
    }
    // Auto-clear status messages after 2s
    if (settingsMsg[0] && settingsMsgMs > 0 &&
        millis() - settingsMsgMs > 2000)
    {
      settingsMsg[0] = '\0';
      settingsMsgMs = 0;
      drawSettings();
    }
    break;

  case STATE_WIFI_RECONNECTING:
  {

    // BACK — cancel
    if (btn == 3)
    {
      wifiReconnectActive = false;
      wifiJustConnected = false;
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
      goToMenu();
      break;
    }

    // ── Root cause fix ──────────────────────────────────
    // Old code checked WiFi.status() every loop tick and kept
    // resetting wifiSuccessShowMs = millis() on every connected
    // iteration, so millis()-wifiSuccessShowMs was always < 1ms
    // and goToMenu() was never reached. The 20s timeout then
    // fired even though WiFi had already connected.
    //
    // Fix: latch wifiJustConnected = true exactly ONCE on first
    // connect detection. Never re-enter that block again.
    // ────────────────────────────────────────────────────

    if (!wifiJustConnected && WiFi.status() == WL_CONNECTED)
    {
      // First time we see a connection — latch it
      wifiJustConnected = true;
      wifiReconnectActive = false;
      wifiSuccessShowMs = millis();
      Serial.print("Reconnected: ");
      Serial.println(WiFi.localIP());
      drawWifiReconnecting(3, true); // show success screen
    }

    // Waiting to show success screen for 1.5s then go to menu
    if (wifiJustConnected && wifiSuccessShowMs > 0 &&
        millis() - wifiSuccessShowMs > 1500)
    {
      wifiJustConnected = false;
      wifiSuccessShowMs = 0;
      goToMenu();
      break;
    }

    // Still trying — animate dots every 500ms
    if (!wifiJustConnected && millis() - wifiDotMs > 500)
    {
      wifiDotMs = millis();
      wifiDots = (wifiDots % 3) + 1;
      drawWifiReconnecting(wifiDots, false);
    }

    // 20s timeout — give up
    if (wifiReconnectActive &&
        millis() - wifiReconnectStartMs > 20000)
    {
      Serial.println("Reconnect timed out");
      strncpy(errorMsg, "WiFi timed out", sizeof(errorMsg) - 1);
      wifiReconnectActive = false;
      wifiJustConnected = false;
      currentState = STATE_ERROR;
      drawError(errorMsg);
    }
    break;
  }

  default:
    break;
  }
}
