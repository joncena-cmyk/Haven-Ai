# HAVEN AI — ESP32 Zero-Code Provisioning & Captive Portal Architecture
> **Document Purpose:** Engineering specification for implementing plug-and-play, zero-hardcoded-code ESP32 hardware provisioning for the HAVEN AI Automotive Diagnostic System. Revisit this document when ready to implement.

---

## 1. Problem Statement & Motivation

### Current State (The Pain Point)
Currently, `TelemetryIngestController::sketch` generates an Arduino C++ sketch containing hardcoded constants:
```cpp
const char* ssid      = "YOUR_WIFI_SSID";
const char* password  = "YOUR_WIFI_PASSWORD";
const char* serverUrl = "http://YOUR_SERVER_IP:8000/api/v1/telemetry/ingest";
const char* deviceKey = "hvn_dev_xxxxxxxxxxxx";
```
**Why this is problematic:**
1. **Flashing Friction:** Every change of Wi-Fi router, mobile hotspot, or vehicle assignment requires opening an IDE (Arduino IDE / PlatformIO), editing source code, compiling, and re-flashing via USB.
2. **ngrok / Tunnel Changes:** When ngrok restarts and issues a new forwarding URL, the ESP32 becomes orphaned unless physically connected to a computer and re-flashed.
3. **Defense / Capstone Evaluation:** Hardcoded credentials fail ISO/SAE 21434 automotive cybersecurity evaluation and look unpolished during live thesis panel demos.

### Target State (The "Flash-Once" Principle)
- The user flashes the ESP32 **one time** with a universal binary.
- On first power-up (or when connection fails), the ESP32 acts as an autonomous Access Point (Hotspot) with a Captive Portal.
- The user configures Wi-Fi and connects their vehicle using their smartphone browser in under 60 seconds.
- All configuration is saved in the ESP32's **Non-Volatile Storage (NVS)** and persists across power cycles.

---

## 2. System Architecture & Lifecycle

```
┌────────────────────────────────────────────────────────────────────────┐
│                        ESP32 BOOT SEQUENCE                             │
└────────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
                 [ Check NVS (Preferences.h) ]
                                   │
                  Is Wi-Fi & Device Key Saved?
                                  / \
                           NO   /     \   YES
                              /         \
                             ▼           ▼
                   ┌──────────────┐   ┌──────────────────────────┐
                   │  STATE: AP   │   │     STATE: STATION       │
                   │ Captive Mode │   │ Attempt Wi-Fi Connection │
                   └──────┬───────┘   └────────────┬─────────────┘
                          │                        │
               User connects phone                 │ Connection Successful?
               Enters Wi-Fi + Key                 / \
               Saves to NVS                      /   \
                          │               YES  /       \  NO (Timeout 20s)
                          └───────────────────►         ▼
                                               ┌──────────────────────────┐
                                               │ Send Ping to Laravel     │
                                               │ Begin 50Hz Sensor Stream │
                                               └──────────────────────────┘
```

---

## 3. Provisioning Workflows: Evaluation & Options

### Approach A: Direct Key & Server URL in Captive Portal (Recommended First)
*Fastest to build, highly flexible for ngrok and LAN setups.*

1. **Hotspot Broadcast:** ESP32 broadcasts an open AP named `HAVEN-DAQ-[MAC]`.
2. **Captive Portal:** Connecting triggers a captive portal browser page (`192.168.4.1`) with:
   - **Wi-Fi Network:** Dropdown scanning available 2.4 GHz SSIDs.
   - **Wi-Fi Password:** Password input field.
   - **Backend Server URL:** Defaults to production, or editable to local IP / ngrok URL (e.g. `https://my-tunnel.ngrok-free.dev`).
   - **Vehicle Device Key:** Input field to paste the 32-character key from Laravel.
3. **Save & Reboot:** ESP32 validates input, writes to NVS, reboots into Station Mode, and immediately pings Laravel.

---

### Approach B: 6-Digit "Claim Code" (Smart TV / Commercial Consumer Flow)
*Highest polish, zero long-key typing on phone.*

1. **Hotspot Broadcast:** User connects to `HAVEN-DAQ-[MAC]` and **only** enters Wi-Fi SSID & Password.
2. **Temporary Claim Token:**
   - ESP32 connects to Wi-Fi and calls Laravel: `POST /api/v1/devices/claim-request` passing its MAC address.
   - Laravel generates an ephemeral 6-digit PIN code (e.g., `492-817`) valid for 10 minutes.
   - The ESP32 captive portal displays: *"Connected! Your pairing code is **492-817**"*.
3. **Claiming on Website:**
   - User goes to their Haven AI dashboard -> *My Vehicles* -> clicks *"Claim Hardware Node"*.
   - User inputs `492-817`.
   - Laravel pairs the vehicle record with the ESP32's MAC address and issues its permanent `device_key`.
   - ESP32 polls `GET /api/v1/devices/claim-status?mac=...` -> receives the key -> stores it in NVS.

---

## 4. Hardware Fail-Safe: Physical Reset & Watchdog

What happens if the Wi-Fi password changes or the device is brought to a new workshop?

### Onboard Factory Reset Button (GPIO 0 / BOOT)
1. On every boot, the firmware checks if the onboard **BOOT button (GPIO 0)** is being held down.
2. If held for **> 5 seconds**:
   - An onboard status LED blinks rapidly.
   - Firmware calls `preferences.clear()` in NVS.
   - ESP32 reboots directly into Captive Portal AP mode.
3. **No computer or USB cable is ever needed to reset network settings.**

### Network Watchdog
If the configured Wi-Fi network is unreachable for **more than 30 consecutive seconds** (e.g. router turned off), the ESP32 can either:
- Periodically retry connecting every 10 seconds.
- Re-open a fallback AP hotspot in the background without clearing previous settings, allowing access to `192.168.4.1` to update settings.

---

## 5. Technical Requirements & Implementation Plan

### A. ESP32 Firmware Stack
- **Framework:** Arduino / ESP-IDF (ESP32 Dev Module / WROOM-32).
- **Core Libraries:**
  - `WiFi.h` & `WebServer.h` (Built into ESP32 core).
  - `DNSServer.h` (Captive portal redirect to `192.168.4.1`).
  - `Preferences.h` (Flash-based key-value storage).
  - *Optional Convenience Library:* `WiFiManager` by tzapu (implements custom fields via `WiFiManagerParameter`).
- **NVS Namespace Scheme:**
  ```cpp
  Preferences prefs;
  prefs.begin("haven-cfg", false);
  prefs.putString("ssid", ssid);
  prefs.putString("pass", password);
  prefs.putString("server", serverUrl);
  prefs.putString("dev_key", deviceKey);
  prefs.end();
  ```

---

### B. Laravel Backend Additions

#### 1. Database Schema
Add device telemetry and pairing columns to `vehicles`:
```php
Schema::table('vehicles', function (Blueprint $table) {
    $table->string('device_mac', 30)->nullable()->after('device_key');
    $table->string('device_firmware_version', 20)->nullable()->after('device_mac');
    $table->timestamp('device_last_seen_at')->nullable()->after('device_firmware_version');
    $table->enum('device_status', ['unpaired', 'offline', 'online'])->default('unpaired')->after('device_last_seen_at');
});
```

#### 2. Enhanced Ingest Handshake Endpoint (`api.php`)
```php
// Upgraded Ping / Handshake endpoint
Route::post('/api/v1/telemetry/handshake', [TelemetryIngestController::class, 'handshake']);
```
- Receives: `X-Haven-Device-Key`, `X-Device-MAC`, `X-Firmware-Version`.
- Validates that the device key matches the vehicle.
- Updates `device_mac`, `device_firmware_version`, and `device_last_seen_at`.
- Returns vehicle metadata (engine type, baseline RPM targets) so the ESP32 can adjust sample filtering dynamically.

#### 3. Vehicle Detail UI (`vehicles/show.blade.php`)
- **Interactive Pairing Card:**
  - Status Pill: `● Hardware Online` / `○ Offline` / `⚠ Unpaired`.
  - Display MAC address and firmware version.
  - "Copy Setup Key" button + QR Code for mobile scanning.
  - "Reset Hardware Pairing" button to revoke an old key.

---

## 6. Implementation Checklist (For When Ready to Build)

- [ ] **Step 1:** Create Laravel migration for `vehicles` device metadata (`device_mac`, `device_last_seen_at`, `device_status`).
- [ ] **Step 2:** Add `handshake` method in `TelemetryIngestController` with MAC & version registration.
- [ ] **Step 3:** Design the Hardware Connection UI card in `vehicles/show.blade.php`.
- [ ] **Step 4:** Build the clean C++ ESP32 firmware sketch implementing:
  - `Preferences.h` NVS load/save functions.
  - Captive Portal AP setup using `WiFiManager` with custom inputs (`serverUrl`, `deviceKey`).
  - GPIO 0 long-press hardware factory reset handler.
- [ ] **Step 5:** End-to-end field testing (Hotspot configuration -> Wi-Fi switch -> Sensor stream verification).

