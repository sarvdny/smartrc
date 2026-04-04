# RFID Based Smart RC Verification System

---

- The project **RFID Based Smart RC Book** Is under the subject named **Micro Project** by the **Tech Titans** which includes the member :
  > - **Sarvdny Potfode**
---
# Abstract:

Smart RC Book is an IoT-based vehicle Registration Certificate verification system designed to modernise roadside document checking for traffic police and RTO enforcement officers in India. The system addresses a critical operational gap in the existing verification infrastructure, where officers rely on physical paper documents that can be forged, damaged, or withheld, and where existing digital solutions such as mParivahan require internet-connected smartphones that junior-level constables rarely have access to during field duty.

The hardware component consists of a compact handheld device built around an ESP8266 NodeMCU microcontroller connected to an MFRC522 RC522 RFID reader via SPI, an SH1106 1.3-inch 128×64 OLED display via I2C, and three dedicated push buttons for navigation, selection, and back actions. Each registered vehicle is issued a MIFARE Classic RFID card provisioned with its RC number. When an officer taps the card, the device reads the hardware UID and sends a secured HTTPS GET request to a cloud-hosted REST API. The server performs a six-table JOIN across the RC_DATA MySQL database — covering registration, ownership, insurance, fitness certificate, road tax, and pollution compliance — and returns a compact JSON payload optimised for the OLED display. The result is rendered within one to two seconds of the card tap.

The firmware, written in Arduino C++ targeting the ESP8266 board package at version 3.2, implements a nine-state finite state machine with a dedicated settings sub-menu, a 60-second group-aware result cache, non-blocking RFID polling, a circular button event queue, and BearSSL HTTPS encryption. A newly introduced Expired Documents feature allows the officer to scan a vehicle specifically for compliance violations. The API response includes an expired_count field and a comma-separated expired_fields list identifying which documents have lapsed. When one or more documents are expired, the firmware transitions to a dedicated STATE_EXPIRED_ALERT screen that displays a full-width inverted warning header, the number of expired documents in large text, and the names of the specific documents that have lapsed. The officer can then proceed to the full scrollable detail view or return to the menu. In the detail view, any row with an EXP value is rendered with an inverted highlight, providing at-a-glance identification of violations even within a multi-field result.

The cloud backend is a PHP 8 REST API hosted on Apache with a MySQL database, secured by bcrypt password hashing at cost factor 12, prepared statements for all database operations, HTTPS enforced via 301 redirect and HSTS header, and .htaccess rules blocking direct access to internal PHP includes, SQL files, and environment files. An admin dashboard provides browser-based management of vehicle records with token-rotated session authentication. The total hardware cost per device is approximately ₹800, making the system deployable across an entire RTO jurisdiction at a fraction of the cost of issuing department smartphones. The system directly addresses the three core failures of current field verification — document forgery, lack of portable infrastructure, and dependence on paper — by binding the RFID tag to the authoritative government database record rather than to any physical document.

> **This Repository includes the programs of both, Hardware as well as The Software.**
>
> In the [HARDWARE](esp8266) Folder includes all the stuff / code related to the microcontroller **( ESP8266 )**
>
> And in the Folder apart from "esp8266" Includes all the code to the API and frontend.
