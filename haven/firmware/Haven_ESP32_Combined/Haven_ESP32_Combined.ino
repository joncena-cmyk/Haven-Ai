#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <Adafruit_INA219.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <driver/i2s.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <esp_timer.h>
#include <math.h>
#include "accel_driver.h"

#if __has_include("secrets.h")
  #include "secrets.h"
#endif

#ifndef HAVEN_WIFI_SSID
  #define HAVEN_WIFI_SSID ""
#endif
#ifndef HAVEN_WIFI_PASS
  #define HAVEN_WIFI_PASS ""
#endif
#ifndef HAVEN_SERVER_URL
  #define HAVEN_SERVER_URL "http://192.168.1.100:8000/api/v1/telemetry/ingest"
#endif
#ifndef HAVEN_DEVICE_KEY
  #define HAVEN_DEVICE_KEY ""
#endif
#ifndef HAVEN_VEHICLE_ID
  #define HAVEN_VEHICLE_ID 1
#endif
#ifndef HAVEN_AP_SSID
  #define HAVEN_AP_SSID "HAVEN_AI_LOCAL"
#endif
#ifndef HAVEN_AP_PASS
  #define HAVEN_AP_PASS ""
#endif

// Set to 1 to boot directly into 921600-baud LOGGER mode (no Wi-Fi),
// or leave 0 and send "LOGGER\n" over Serial to switch at runtime.
#ifndef HAVEN_DEFAULT_LOGGER_MODE
  #define HAVEN_DEFAULT_LOGGER_MODE 0
#endif

// ============================================================
// HAVEN AI - ESP32 FIVE SENSOR DAQ & HIGH-RATE SERIAL LOGGER
// Complies with ISO/SAE 21434 Hardware Key Authentication
// Credentials loaded from NVS Preferences or git-ignored secrets.h
// ============================================================

Preferences prefs;

// ── 1. NETWORK & HAVEN CONFIGURATION (Loaded from NVS / secrets.h) ──
char wifiSSID[64]        = HAVEN_WIFI_SSID;
char wifiPassword[64]    = HAVEN_WIFI_PASS;
char havenServerUrl[128] = HAVEN_SERVER_URL;
char havenDeviceKey[64]  = HAVEN_DEVICE_KEY;
int  vehicleId           = HAVEN_VEHICLE_ID;

// Standalone fallback AP for mobile provisioning: http://192.168.4.1/config
const char* apSSID     = HAVEN_AP_SSID;
const char* apPassword = HAVEN_AP_PASS;

WebServer server(80);
DNSServer dnsServer;
const byte DNS_PORT = 53;

// ── 2. GPIO PIN ASSIGNMENTS ─────────────────────────────────
#define TEMP_PIN 4
#define HALL_PIN 23

#define MPU_SDA 21
#define MPU_SCL 22

#define MIC_BCLK 18
#define MIC_WS   19
#define MIC_SD   5

// ── 3. SENSOR OBJECTS (Swappable Accelerometer Driver Layer) ──
OneWire oneWire(TEMP_PIN);
DallasTemperature tempSensor(&oneWire);
int ds18DeviceCount = 0;

// Default I2C MPU6050 driver; swap with `Adxl345SpiDriver accelHw(CS_PIN);` for SPI ADXL345
Mpu6050I2cDriver accelHw(MPU_SDA, MPU_SCL);
IAccelDriver* accelDriver = &accelHw;
bool mpuReady = false;

// High-rate LOGGER mode state (target 1000 Hz, 921600 baud, no Wi-Fi)
bool loggerModeActive = (HAVEN_DEFAULT_LOGGER_MODE != 0);
const uint32_t LOGGER_BAUD_RATE = 921600;
const int64_t  LOGGER_TARGET_PERIOD_US = 1000; // 1000 Hz target
int64_t nextLoggerSampleUs = 0;

Adafruit_INA219 ina219(0x40);
bool inaReady = false;

bool micReady = false;
float audioRMS = 0;
float audioPeak = 0;

// Hall sensor interrupt
volatile unsigned long pulseCount = 0;
volatile unsigned long lastPulseUs = 0;
portMUX_TYPE pulseMux = portMUX_INITIALIZER_UNLOCKED;
const float pulsesPerRevolution = 1.0;
const unsigned long minPulseIntervalUs = 20000;

// Current Sensor Values
float temperature = NAN;
float rpm = 0;
float accelX = 0;
float accelY = 0;
float accelZ = 0;
float accelMagnitude = 0;
float busVoltage = 0;
float currentMA = 0;
float powerMW = 0;

// 100Hz 128-Sample Vibration Ring Buffer for FFT Spectral Analysis
#define VIB_BUFFER_SIZE 128
float vibBuffer[VIB_BUFFER_SIZE];
int vibBufferIdx = 0;
bool vibBufferFull = false;
float windowPeakG = 0;
float windowRmsG = 0;
float windowCrestFactor = 0;

// Loop Timers
unsigned long lastRPM = 0;
unsigned long lastTemp = 0;
unsigned long lastMPU = 0;
unsigned long lastINA = 0;
unsigned long lastMic = 0;
unsigned long lastHavenUpload = 0;
const unsigned long UPLOAD_INTERVAL_MS = 5000; // Ingest to Haven every 5s

// ============================================================
// HALL SENSOR INTERRUPT
// ============================================================
void IRAM_ATTR countPulse() {
  unsigned long now = micros();
  portENTER_CRITICAL_ISR(&pulseMux);
  if ((unsigned long)(now - lastPulseUs) >= minPulseIntervalUs) {
    if (digitalRead(HALL_PIN) == LOW) {
      pulseCount++;
      lastPulseUs = now;
    }
  }
  portEXIT_CRITICAL_ISR(&pulseMux);
}

// ============================================================
// INMP441 MICROPHONE SETUP & READ
// ============================================================
void setupMicrophone() {
  i2s_config_t config = {};
  config.mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX);
  config.sample_rate = 16000;
  config.bits_per_sample = I2S_BITS_PER_SAMPLE_32BIT;
  config.channel_format = I2S_CHANNEL_FMT_ONLY_LEFT;
  config.communication_format = I2S_COMM_FORMAT_STAND_I2S;
  config.intr_alloc_flags = 0;
  config.dma_buf_count = 4;
  config.dma_buf_len = 256;
  config.use_apll = false;

  i2s_pin_config_t pins = {};
  pins.bck_io_num = MIC_BCLK;
  pins.ws_io_num = MIC_WS;
  pins.data_out_num = I2S_PIN_NO_CHANGE;
  pins.data_in_num = MIC_SD;

  esp_err_t result = i2s_driver_install(I2S_NUM_0, &config, 0, NULL);
  if (result != ESP_OK) {
    micReady = false;
    return;
  }
  result = i2s_set_pin(I2S_NUM_0, &pins);
  micReady = (result == ESP_OK);
}

void readMicrophone() {
  if (!micReady) return;
  int32_t samples[256];
  size_t bytesRead = 0;

  esp_err_t result = i2s_read(I2S_NUM_0, samples, sizeof(samples), &bytesRead, pdMS_TO_TICKS(100));
  if (result != ESP_OK || bytesRead == 0) return;

  int count = bytesRead / sizeof(int32_t);
  double sum = 0, sumSquares = 0, peak = 0;

  for (int i = 0; i < count; i++) {
    double sample = samples[i] / 256.0;
    sum += sample;
    sumSquares += sample * sample;
    double absVal = fabs(sample);
    if (absVal > peak) peak = absVal;
  }

  double mean = sum / count;
  double variance = (sumSquares / count) - (mean * mean);
  if (variance < 0) variance = 0;

  audioRMS = sqrt(variance);
  audioPeak = peak;
}

// ============================================================
// DYNAMIC HARDWARE PROBING (Live Pin & Wire Detection)
// ============================================================
bool probeI2C(uint8_t address) {
  Wire.beginTransmission(address);
  return (Wire.endTransmission() == 0);
}

// ============================================================
// HAVEN AI TELEMETRY POST (PUSH TO WEBSITE)
// ============================================================
void postTelemetryToHaven() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HAVEN] Wi-Fi not connected. Skipping upload.");
    return;
  }

  // Live I2C hardware bus probe right before sending
  bool mpuDetected = probeI2C(0x68);
  if (!mpuDetected) {
    mpuReady = false;
  } else if (!mpuReady) {
    mpuReady = accelDriver->begin(false);
  }

  bool inaDetected = probeI2C(0x40);
  if (!inaDetected) {
    inaReady = false;
  } else if (!inaReady) {
    inaReady = ina219.begin(&Wire);
  }

  bool ds18Plugged = (!isnan(temperature) && ds18DeviceCount > 0);

  // Compute window Peak G, RMS G, and Crest Factor across the 100Hz ring buffer.
  // The raw magnitude includes ~1 g of static gravity, so subtract the window mean first;
  // otherwise a sensor resting on a table reports ~1.0 g peak/RMS.
  int sampleCount = vibBufferFull ? VIB_BUFFER_SIZE : vibBufferIdx;
  float meanMag = 0.0f;
  if (mpuReady && sampleCount > 0) {
    double sum = 0;
    for (int i = 0; i < sampleCount; i++) sum += vibBuffer[i];
    meanMag = (float)(sum / sampleCount);

    double sumSq = 0;
    float maxG = 0;
    for (int i = 0; i < sampleCount; i++) {
      float v = fabsf(vibBuffer[i] - meanMag);
      if (v > maxG) maxG = v;
      sumSq += (double)v * (double)v;
    }
    windowPeakG = maxG;
    windowRmsG  = sqrt(sumSq / sampleCount);
    windowCrestFactor = (windowRmsG > 0.001f) ? (windowPeakG / windowRmsG) : 0.0f;
  } else {
    windowPeakG = 0.0f;
    windowRmsG  = 0.0f;
    windowCrestFactor = 0.0f;
  }

  HTTPClient http;
  http.begin(havenServerUrl);
  http.setTimeout(5000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Haven-Device-Key", havenDeviceKey);

  StaticJsonDocument<4096> doc;
  doc["vehicle_id"]     = vehicleId;
  doc["device_id"]      = "ESP32-5SENSOR-NODE";
  doc["device_key"]     = havenDeviceKey;
  doc["save_session"]   = false; // Hardware checkup only: does NOT create database sessions
  doc["auto_analyze"]   = false;
  doc["sample_rate_hz"] = 100;

  // Component Health Flags (Tells website if hardware is plugged in)
  JsonObject health = doc.createNestedObject("health");
  health["ds18b20"]  = ds18Plugged;
  health["mpu6050"]  = mpuReady;
  health["ina219"]   = inaReady;
  health["inmp441"]  = micReady;
  health["hall_rpm"] = true;

  if (ds18Plugged) {
    doc["temperature_c"] = round(temperature * 10.0) / 10.0;
  } else {
    doc["temperature_c"] = nullptr;
  }
  
  doc["rpm"] = (int)rpm;
  
  if (inaReady) {
    doc["voltage_v"]  = round(busVoltage * 100.0) / 100.0;
    doc["current_ma"] = round(currentMA * 10.0) / 10.0;
  } else {
    doc["voltage_v"]  = nullptr;
    doc["current_ma"] = nullptr;
  }
  
  if (mpuReady) {
    doc["peak_g"]       = round(windowPeakG * 1000.0) / 1000.0;
    doc["rms_g"]        = round(windowRmsG * 1000.0) / 1000.0;
    doc["crest_factor"] = round(windowCrestFactor * 100.0) / 100.0;
    JsonArray samplesArr = doc.createNestedArray("samples");
    int countToSend = (sampleCount > 64) ? 64 : sampleCount;
    for (int i = 0; i < countToSend; i++) {
      int idx = (vibBufferIdx - countToSend + i + VIB_BUFFER_SIZE) % VIB_BUFFER_SIZE;
      // Send gravity-removed dynamic magnitude, consistent with peak_g / rms_g above.
      samplesArr.add(round((vibBuffer[idx] - meanMag) * 1000.0) / 1000.0);
    }
  } else {
    doc["peak_g"] = nullptr;
  }

  // Multi-sensor detail object
  JsonObject sensorData = doc.createNestedObject("sensor_data");
  JsonObject ds = sensorData.createNestedObject("ds18b20");
  ds["pin"] = "D4";
  if (ds18Plugged) {
    ds["temp_c"] = round(temperature * 10.0) / 10.0;
  } else {
    ds["temp_c"] = nullptr;
  }

  JsonObject hall = sensorData.createNestedObject("hall_rpm");
  hall["pin"] = "D23";
  hall["rpm"] = (int)rpm;

  JsonObject mpuObj = sensorData.createNestedObject("mpu6050");
  if (mpuReady) {
    mpuObj["ax"] = accelX;
    mpuObj["ay"] = accelY;
    mpuObj["az"] = accelZ;
    mpuObj["magnitude"] = accelMagnitude;
  } else {
    mpuObj["ax"] = nullptr;
    mpuObj["ay"] = nullptr;
    mpuObj["az"] = nullptr;
    mpuObj["magnitude"] = nullptr;
  }

  JsonObject inaObj = sensorData.createNestedObject("ina219");
  if (inaReady) {
    inaObj["voltage_v"]  = busVoltage;
    inaObj["current_ma"] = currentMA;
    inaObj["power_mw"]   = powerMW;
  } else {
    inaObj["voltage_v"]  = nullptr;
    inaObj["current_ma"] = nullptr;
    inaObj["power_mw"]   = nullptr;
  }

  JsonObject micObj = sensorData.createNestedObject("inmp441");
  micObj["rms"]  = audioRMS;
  micObj["peak"] = audioPeak;

  String body;
  serializeJson(doc, body);

  Serial.println("\n[HAVEN] POSTing 5-Sensor Telemetry to Haven Website...");
  Serial.printf("  Temp: %s | RPM: %.0f | Volt: %s | Mag: %s | Audio: %.0f\n",
                ds18Plugged ? String(temperature, 1).c_str() : "DISCONNECTED",
                rpm,
                inaReady ? String(busVoltage, 2).c_str() : "DISCONNECTED",
                mpuReady ? String(accelMagnitude, 2).c_str() : "DISCONNECTED",
                audioRMS);

  int httpCode = http.POST(body);
  Serial.printf("[HAVEN] HTTP Code: %d\n", httpCode);

  if (httpCode == 200 || httpCode == 201) {
    Serial.println("[HAVEN] [OK] Successfully sent to Haven Website!");
  } else if (httpCode == 401) {
    Serial.println("[HAVEN] [AUTH ERROR] Device key was rejected by server.");
  } else {
    Serial.printf("[HAVEN] [FAIL] Could not reach Laravel. Check IP / Firewall.\n");
  }

  http.end();
}

// ============================================================
// LOCAL WEB SERVER ENDPOINTS (From your friend's code)
// ============================================================
void handleData() {
  String json = "{";
  json += "\"temperature\":" + (isnan(temperature) ? "null" : String(temperature, 2));
  json += ",\"rpm\":" + String(rpm, 1);
  json += ",\"ax\":" + (mpuReady ? String(accelX, 3) : "null");
  json += ",\"ay\":" + (mpuReady ? String(accelY, 3) : "null");
  json += ",\"az\":" + (mpuReady ? String(accelZ, 3) : "null");
  json += ",\"vib\":" + (mpuReady ? String(accelMagnitude, 3) : "null");
  json += ",\"inaConnected\":" + String(inaReady ? "true" : "false");
  json += ",\"voltage\":" + (inaReady ? String(busVoltage, 2) : "null");
  json += ",\"current\":" + (inaReady ? String(currentMA, 2) : "null");
  json += ",\"power\":" + (inaReady ? String(powerMW, 2) : "null");
  json += ",\"micConnected\":" + String(micReady ? "true" : "false");
  json += ",\"audioRMS\":" + (micReady ? String(audioRMS, 2) : "null");
  json += "}";
  server.send(200, "application/json", json);
}

// ============================================================
// CAPTIVE PORTAL & WEB PROVISIONING (http://192.168.4.1/config)
// ============================================================

// Escape user-controlled text (SSIDs, URLs) before inserting it into HTML.
String htmlEscape(const String& in) {
  String out;
  out.reserve(in.length() + 16);
  for (size_t i = 0; i < in.length(); i++) {
    char c = in[i];
    switch (c) {
      case '&':  out += "&amp;";  break;
      case '<':  out += "&lt;";   break;
      case '>':  out += "&gt;";   break;
      case '"':  out += "&quot;"; break;
      case '\'': out += "&#39;";  break;
      default:   out += c;        break;
    }
  }
  return out;
}

// Bounded copy that always NUL-terminates (strncpy does not when the source is too long).
void copyBounded(char* dst, size_t dstSize, const char* src) {
  if (!dst || dstSize == 0) return;
  strlcpy(dst, src ? src : "", dstSize);
}

void handleScanWifi() {
  int n = WiFi.scanNetworks();
  // 25 networks x (32-char SSID + keys/overhead) needs ~3 KB of ArduinoJson pool.
  DynamicJsonDocument doc(4096);
  JsonArray arr = doc.createNestedArray("networks");
  for (int i = 0; i < n && i < 25; i++) {
    JsonObject net = arr.createNestedObject();
    net["ssid"] = WiFi.SSID(i);
    net["rssi"] = WiFi.RSSI(i);
    net["secure"] = (WiFi.encryptionType(i) != WIFI_AUTH_OPEN);
  }
  WiFi.scanDelete();
  String json;
  serializeJson(doc, json);
  server.send(200, "application/json", json);
}

void handleCaptivePortalRedirect() {
  String host = server.hostHeader();
  String apIp = WiFi.softAPIP().toString();
  String staIp = WiFi.localIP().toString();
  // Only hijack requests from captive-portal probes on the hotspot. Requests that already
  // target one of our own IPs (AP or home-network STA) get the config page directly;
  // redirecting STA clients to 192.168.4.1 would send them to an unreachable address.
  if (host.indexOf(apIp) < 0 && (staIp == "0.0.0.0" || host.indexOf(staIp) < 0)) {
    server.sendHeader("Location", "http://" + apIp + "/config", true);
    server.send(302, "text/plain", "");
    return;
  }
  handleConfig();
}

void handleConfig() {
  bool isConn = (WiFi.status() == WL_CONNECTED);
  String localIp = isConn ? WiFi.localIP().toString() : "Not connected";

  String html = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  html += "<title>HAVEN AI — ESP32 DAQ Setup</title>";
  html += "<style>";
  html += "*{box-sizing:border-box}body{font-family:system-ui,-apple-system,sans-serif;background:#090d16;color:#e2e8f0;margin:0;padding:1.5rem;display:flex;justify-content:center}";
  html += ".box{max-width:480px;width:100%}";
  html += ".head{display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem}";
  html += ".icon{width:38px;height:38px;border-radius:10px;background:rgba(14,165,233,0.15);border:1px solid rgba(14,165,233,0.3);display:flex;align-items:center;justify-content:center;color:#38bdf8;font-size:1rem;font-weight:700}";
  html += "h1{color:#f8fafc;font-size:1.15rem;margin:0 0 0.15rem;font-weight:700}";
  html += ".sub{color:#94a3b8;font-size:0.75rem;margin:0}";
  html += ".badge{display:inline-block;padding:0.3rem 0.65rem;border-radius:6px;font-size:0.72rem;font-weight:700;margin-bottom:1rem;font-family:monospace}";
  html += ".b-green{background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3)}";
  html += ".b-amber{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)}";
  html += ".card{background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:1.25rem;box-shadow:0 12px 32px rgba(0,0,0,0.5)}";
  html += "label{display:block;font-size:0.72rem;font-weight:700;color:#94a3b8;margin:0.85rem 0 0.3rem;text-transform:uppercase;letter-spacing:0.04em}";
  html += "input,select{width:100%;padding:0.65rem;background:#131c2e;border:1px solid #334155;border-radius:8px;color:#fff;font-size:0.88rem;outline:none}";
  html += "input:focus,select:focus{border-color:#0284c7}";
  html += ".btn{width:100%;margin-top:1.25rem;padding:0.75rem;background:linear-gradient(135deg,#0284c7,#0ea5e9);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer}";
  html += ".btn-sec{width:auto;padding:0.35rem 0.65rem;background:#1e293b;color:#38bdf8;border:1px solid #334155;border-radius:6px;font-size:0.72rem;font-weight:600;cursor:pointer}";
  html += ".hint{font-size:0.68rem;color:#64748b;margin-top:0.25rem}";
  html += "</style></head><body><div class='box'>";
  html += "<div class='head'><div class='icon'>HV</div><div><h1>HAVEN AI Node Setup</h1><div class='sub'>ISO/SAE 21434 Hardware Auto-Provisioning</div></div></div>";

  if (isConn) {
    html += "<div class='badge b-green'>CONNECTED: " + htmlEscape(wifiSSID) + " (" + localIp + ")</div>";
  } else {
    html += "<div class='badge b-amber'>STANDALONE HOTSPOT (192.168.4.1)</div>";
  }

  html += "<form class='card' method='POST' action='/save'>";
  html += "<div style='display:flex;justify-content:space-between;align-items:center;'>";
  html += "<label style='margin:0'>Wi-Fi Network (SSID)</label>";
  html += "<button type='button' class='btn-sec' onclick='scanNetworks()'>Scan Networks</button>";
  html += "</div>";
  html += "<div id='scan-box' style='display:none;margin-top:0.35rem;'>";
  html += "<select id='scan-select' onchange='if(this.value){document.getElementById(\"ssid-input\").value=this.value;}'></select>";
  html += "</div>";
  html += "<input id='ssid-input' name='ssid' value='" + htmlEscape(wifiSSID) + "' placeholder='e.g. MyWiFi-2.4G' required maxlength='32' style='margin-top:0.35rem'>";
  html += "<label>Wi-Fi Password</label>";
  // Never echo the stored password: the setup hotspot is open, so anyone nearby could read it.
  html += "<input name='pass' type='password' value='' maxlength='63' placeholder='" + String(strlen(wifiPassword) > 0 ? "Saved (leave blank to keep)" : "Leave blank if open network") + "'>";
  html += "<label>Haven Server URL</label>";
  html += "<input name='server' value='" + htmlEscape(havenServerUrl) + "' required maxlength='127'>";
  html += "<div class='hint'>Laptop IP on this Wi-Fi (e.g. http://192.168.1.50:8000/api/v1/telemetry/ingest)</div>";
  html += "<label>Device Key</label>";
  // Never echo the stored device key for the same reason.
  html += "<input name='key' value='' maxlength='63' placeholder='" + String(strlen(havenDeviceKey) > 0 ? "Saved (leave blank to keep)" : "hvn_dev_...") + "'" + String(strlen(havenDeviceKey) > 0 ? "" : " required") + ">";
  html += "<label>Vehicle ID</label>";
  html += "<input name='vehicle_id' type='number' min='1' value='" + String(vehicleId) + "' required>";
  html += "<button class='btn' type='submit'>Save & Reconnect</button>";
  html += "</form>";

  html += "<script>";
  html += "function scanNetworks(){";
  html += "var sb=document.getElementById('scan-box');var ss=document.getElementById('scan-select');";
  html += "ss.innerHTML='<option>Scanning...</option>';sb.style.display='block';";
  html += "fetch('/scan').then(function(r){return r.json()}).then(function(d){";
  html += "ss.innerHTML='';";
  html += "if(!d.networks||!d.networks.length){var e=document.createElement('option');e.textContent='No networks found';ss.appendChild(e);return;}";
  html += "var f=document.createElement('option');f.value='';f.textContent='-- Select Found Wi-Fi --';ss.appendChild(f);";
  // Build options via DOM APIs so SSIDs containing quotes or tags cannot inject markup.
  html += "d.networks.forEach(function(n){if(!n.ssid)return;var o=document.createElement('option');o.value=n.ssid;o.textContent=n.ssid+' ('+n.rssi+' dBm)'+(n.secure?' [secured]':'');ss.appendChild(o);});";
  html += "}).catch(function(){ss.innerHTML='<option>Scan failed</option>';});";
  html += "}";
  html += "</script>";
  html += "</div></body></html>";

  server.send(200, "text/html", html);
}

void handleSave() {
  String newSsid = server.hasArg("ssid") ? server.arg("ssid") : String(wifiSSID);
  newSsid.trim();
  bool ssidChanged = (newSsid != String(wifiSSID));
  copyBounded(wifiSSID, sizeof(wifiSSID), newSsid.c_str());

  // Blank password = keep the saved one, unless the SSID changed (then it is an open network).
  String newPass = server.hasArg("pass") ? server.arg("pass") : String("");
  if (newPass.length() > 0) {
    copyBounded(wifiPassword, sizeof(wifiPassword), newPass.c_str());
  } else if (ssidChanged) {
    wifiPassword[0] = '\0';
  }

  if (server.hasArg("server") && server.arg("server").length() > 0) {
    copyBounded(havenServerUrl, sizeof(havenServerUrl), server.arg("server").c_str());
  }
  // Blank key = keep the saved key.
  if (server.hasArg("key") && server.arg("key").length() > 0) {
    copyBounded(havenDeviceKey, sizeof(havenDeviceKey), server.arg("key").c_str());
  }
  if (server.hasArg("vehicle_id")) {
    long vid = server.arg("vehicle_id").toInt();
    if (vid > 0) vehicleId = (int)vid;
  }

  prefs.begin("haven", false);
  prefs.putString("ssid", wifiSSID);
  prefs.putString("pass", wifiPassword);
  prefs.putString("server", havenServerUrl);
  prefs.putString("key", havenDeviceKey);
  prefs.putInt("vid", vehicleId);
  prefs.end();

  String html = "<!DOCTYPE html><html><body style='background:#090d16;color:#4ade80;font-family:sans-serif;padding:2rem;text-align:center;'>";
  html += "<h2>Credentials Saved!</h2><p style='color:#94a3b8;'>Rebooting ESP32 and connecting to " + htmlEscape(wifiSSID) + "...</p></body></html>";
  server.send(200, "text/html", html);
  delay(1000);
  ESP.restart();
}

// ============================================================
// HIGH-RATE SERIAL LOGGER MODE (Phase 3: 1000 Hz, 921600 Baud, No Wi-Fi)
// ============================================================
void enterLoggerMode() {
  loggerModeActive = true;

  // Shut down HTTP server and Wi-Fi completely to eliminate radio/ISR jitter
  server.stop();
  WiFi.disconnect(true);
  WiFi.mode(WIFI_OFF);
  btStop();

  Serial.flush();
  Serial.updateBaudRate(LOGGER_BAUD_RATE);
  delay(50);

  mpuReady = accelDriver->begin(true);

  // Measure actual achieved sample rate over a 200-sample hardware-timed calibration burst
  const int kCalibSamples = 200;
  float tx = 0.0f, ty = 0.0f, tz = 0.0f;
  int64_t tStartUs = esp_timer_get_time();
  int64_t nextUs = tStartUs;
  int validReads = 0;

  for (int i = 0; i < kCalibSamples; i++) {
    while (esp_timer_get_time() < nextUs) {
      // Tight hardware timer poll
    }
    if (accelDriver->readSampleG(tx, ty, tz)) {
      validReads++;
    }
    nextUs += LOGGER_TARGET_PERIOD_US;
  }
  int64_t elapsedUs = esp_timer_get_time() - tStartUs;
  float achievedHz = (elapsedUs > 0) ? (1000000.0f * (float)kCalibSamples / (float)elapsedUs) : 0.0f;

  Serial.println();
  Serial.printf(
    "# HAVEN_LOGGER_V1 SENSOR=%s RANGE_G=%d DLPF_HZ=%d TARGET_RATE_HZ=%d ACHIEVED_RATE_HZ=%.1f VALID_READS=%d/%d\n",
    accelDriver->sensorName(),
    accelDriver->rangeG(),
    accelDriver->bandwidthHz(),
    (int)(1000000 / LOGGER_TARGET_PERIOD_US),
    achievedHz,
    validReads,
    kCalibSamples
  );
  Serial.println("t_us,ax,ay,az");

  nextLoggerSampleUs = esp_timer_get_time();
}

void checkSerialModeCommand() {
  while (Serial.available() > 0) {
    String raw = Serial.readStringUntil('\n');
    raw.trim();
    if (raw.length() == 0) continue;

    String upper = raw;
    upper.toUpperCase();

    if (upper == "LOGGER" && !loggerModeActive) {
      enterLoggerMode();
      return;
    } else if (upper == "LIVE" && loggerModeActive) {
      ESP.restart();
      return;
    } else if (upper == "PING") {
      Serial.println("PONG:HAVEN_ESP32_NODE");
      return;
    } else if (upper == "SCAN_WIFI") {
      int n = WiFi.scanNetworks();
      DynamicJsonDocument doc(4096);
      JsonArray arr = doc.createNestedArray("networks");
      for (int i = 0; i < n && i < 20; i++) {
        JsonObject item = arr.createNestedObject();
        item["ssid"] = WiFi.SSID(i);
        item["rssi"] = WiFi.RSSI(i);
      }
      WiFi.scanDelete();
      Serial.print("WIFI_LIST:");
      serializeJson(doc, Serial);
      Serial.println();
      return;
    } else if (upper.startsWith("SET_CONFIG:")) {
      String jsonStr = raw.substring(11);
      StaticJsonDocument<768> doc;
      DeserializationError err = deserializeJson(doc, jsonStr);
      if (err) {
        Serial.println("ERR:INVALID_JSON");
        return;
      }
      const char* newSsid = doc["ssid"] | "";
      if (strlen(newSsid) == 0) {
        Serial.println("ERR:MISSING_SSID");
        return;
      }
      copyBounded(wifiSSID, sizeof(wifiSSID), newSsid);
      copyBounded(wifiPassword, sizeof(wifiPassword), doc["pass"] | "");
      if (doc.containsKey("server")) {
        copyBounded(havenServerUrl, sizeof(havenServerUrl), doc["server"] | "");
      }
      if (doc.containsKey("key")) {
        copyBounded(havenDeviceKey, sizeof(havenDeviceKey), doc["key"] | "");
      }
      int newVid = doc["vehicle_id"] | vehicleId;
      if (newVid > 0) vehicleId = newVid;

      prefs.begin("haven", false);
      prefs.putString("ssid", wifiSSID);
      prefs.putString("pass", wifiPassword);
      prefs.putString("server", havenServerUrl);
      prefs.putString("key", havenDeviceKey);
      prefs.putInt("vid", vehicleId);
      prefs.end();

      Serial.println("OK:SAVED");
      delay(400);
      ESP.restart();
      return;
    } else if (upper == "GET_CONFIG") {
      StaticJsonDocument<256> doc;
      doc["device"] = "HAVEN_ESP32_NODE";
      doc["ssid"] = wifiSSID;
      doc["server"] = havenServerUrl;
      doc["key_set"] = (strlen(havenDeviceKey) > 0);
      doc["vehicle_id"] = vehicleId;
      doc["connected"] = (WiFi.status() == WL_CONNECTED);
      doc["ip"] = (WiFi.status() == WL_CONNECTED) ? WiFi.localIP().toString() : "";
      Serial.print("CONFIG:");
      serializeJson(doc, Serial);
      Serial.println();
      return;
    }
  }
}

void runLoggerLoop() {
  int64_t nowUs = esp_timer_get_time();
  if (nowUs < nextLoggerSampleUs) {
    return;
  }
  nextLoggerSampleUs += LOGGER_TARGET_PERIOD_US;
  if (nowUs - nextLoggerSampleUs > LOGGER_TARGET_PERIOD_US * 4) {
    nextLoggerSampleUs = nowUs + LOGGER_TARGET_PERIOD_US;
  }

  float ax = 0.0f, ay = 0.0f, az = 0.0f;
  if (accelDriver->readSampleG(ax, ay, az)) {
    char lineBuf[64];
    int len = snprintf(lineBuf, sizeof(lineBuf), "%lld,%.5f,%.5f,%.5f\n", (long long)nowUs, ax, ay, az);
    if (len > 0) {
      Serial.write((const uint8_t*)lineBuf, (size_t)len);
    }
  }

  if (Serial.available() > 0) {
    checkSerialModeCommand();
  }
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  // Default RX buffer (256 B) is smaller than a full SET_CONFIG line and overflows while
  // the loop is blocked in an HTTP POST. Must be set before Serial.begin().
  Serial.setRxBufferSize(1024);
  Serial.begin(loggerModeActive ? LOGGER_BAUD_RATE : 115200);
  delay(500);

  // Check if host recorder sent "LOGGER" command during boot window
  unsigned long bootWait = millis();
  while (millis() - bootWait < 500) {
    if (Serial.available() > 0) {
      checkSerialModeCommand();
      if (loggerModeActive) return;
    }
    delay(10);
  }

  if (loggerModeActive) {
    enterLoggerMode();
    return;
  }

  Serial.println("\n=============================================");
  Serial.println("  HAVEN AI — 5-Sensor Automotive Node v2.5");
  Serial.println("  Send 'LOGGER' over Serial for 1000Hz CSV mode");
  Serial.println("=============================================");

  // Load custom Wi-Fi & Haven configurations from NVS if provisioned
  prefs.begin("haven", true);
  if (prefs.isKey("ssid")) {
    prefs.getString("ssid", wifiSSID, sizeof(wifiSSID));
    prefs.getString("pass", wifiPassword, sizeof(wifiPassword));
    prefs.getString("server", havenServerUrl, sizeof(havenServerUrl));
    prefs.getString("key", havenDeviceKey, sizeof(havenDeviceKey));
    if (prefs.isKey("vid")) {
      vehicleId = prefs.getInt("vid", vehicleId);
    }
    Serial.println("[PREFS] Loaded custom Wi-Fi & Haven endpoint credentials from NVS!");
  }
  prefs.end();

  // Hall RPM Pin
  pinMode(HALL_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(HALL_PIN), countPulse, FALLING);

  // DS18B20 Temp Sensor (Async non-blocking conversion mode)
  tempSensor.begin();
  tempSensor.setResolution(10);
  tempSensor.setWaitForConversion(false);
  ds18DeviceCount = tempSensor.getDeviceCount();
  if (ds18DeviceCount > 0) {
    tempSensor.requestTemperatures();
  }
  tempSensor.requestTemperatures();

  // Initialize Accelerometer Driver (widest DLPF, ±8g, 400 kHz I2C)
  mpuReady = accelDriver->begin(false);
  if (mpuReady) {
    Serial.printf("[OK] %s accelerometer initialized (±%dg, %d Hz BW).\n",
                  accelDriver->sensorName(), accelDriver->rangeG(), accelDriver->bandwidthHz());
  } else {
    Serial.println("[WARN] Accelerometer not detected.");
  }

  inaReady = ina219.begin(&Wire);
  if (inaReady) {
    ina219.setCalibration_32V_2A();
    Serial.println("[OK] INA219 power sensor initialized.");
  } else {
    Serial.println("[WARN] INA219 not detected.");
  }

  // INMP441 Microphone
  setupMicrophone();
  if (micReady) {
    Serial.println("[OK] INMP441 I2S microphone initialized.");
  } else {
    Serial.println("[WARN] INMP441 microphone failed.");
  }

  // Connect to Wi-Fi (so it can reach Haven on laptop)
  WiFi.mode(WIFI_AP_STA);
  if (strlen(apPassword) >= 8) {
    WiFi.softAP(apSSID, apPassword);
  } else {
    WiFi.softAP(apSSID);
  }
  Serial.printf("[AP] Local Hotspot started: %s (IP: %s)\n", apSSID, WiFi.softAPIP().toString().c_str());

  // Start Captive Portal DNS Server (redirects all DNS queries to SoftAP IP 192.168.4.1)
  dnsServer.setErrorReplyCode(DNSReplyCode::NoError);
  dnsServer.start(DNS_PORT, "*", WiFi.softAPIP());

  if (strlen(wifiSSID) > 0) {
    Serial.printf("[STA] Connecting to Wi-Fi: %s ...\n", wifiSSID);
    WiFi.begin(wifiSSID, wifiPassword);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 25) {
      delay(400);
      Serial.print(".");
      attempts++;
      // Stay responsive to USB provisioning (SET_CONFIG / LOGGER) while the old Wi-Fi fails.
      if (Serial.available() > 0) {
        checkSerialModeCommand();
        if (loggerModeActive) return;
      }
    }
  } else {
    Serial.println("[STA] No Wi-Fi SSID configured. Provision via http://192.168.4.1/config or secrets.h.");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[STA] [OK] Connected to Home/Lab Wi-Fi!");
    Serial.printf("[STA] ESP32 IP: %s\n", WiFi.localIP().toString().c_str());
  } else {
    Serial.println("\n[STA] [WARN] Could not connect to Wi-Fi. Check SSID/Password.");
  }

  // Local Web Server endpoints & Captive Provisioning Portal
  server.on("/", handleConfig);
  server.on("/config", handleConfig);
  server.on("/save", HTTP_POST, handleSave);
  server.on("/scan", handleScanWifi);
  server.on("/data", handleData);
  server.on("/generate_204", handleConfig);
  server.on("/hotspot-detect.html", handleConfig);
  server.on("/ncsi.txt", handleConfig);
  server.on("/connecttest.txt", handleConfig);
  server.onNotFound(handleCaptivePortalRedirect);
  server.begin();

  lastRPM = millis();
}

// ============================================================
// MAIN LOOP
// ============================================================
void loop() {
  if (loggerModeActive) {
    runLoggerLoop();
    return;
  }

  // Handle Captive Portal DNS queries
  dnsServer.processNextRequest();

  if (Serial.available() > 0) {
    checkSerialModeCommand();
    if (loggerModeActive) return;
  }

  server.handleClient();
  unsigned long now = millis();

  // 1. Read DS18B20 (Non-blocking, cached device count)
  if (now - lastTemp >= 1000) {
    if (ds18DeviceCount == 0) {
      ds18DeviceCount = tempSensor.getDeviceCount();
      temperature = NAN;
    } else {
      float reading = tempSensor.getTempCByIndex(0);
      if (reading >= -50.0 && reading <= 125.0 && reading != DEVICE_DISCONNECTED_C && reading != 85.0) {
        temperature = reading;
      } else {
        temperature = NAN;
        if (reading == DEVICE_DISCONNECTED_C) {
          ds18DeviceCount = 0;
        }
      }
      tempSensor.requestTemperatures(); // non-blocking conversion
    }
    lastTemp = now;
  }

  // 2. Read Hall RPM (Every 1000ms)
  if (now - lastRPM >= 1000) {
    unsigned long elapsed = now - lastRPM;
    portENTER_CRITICAL(&pulseMux);
    unsigned long pulses = pulseCount;
    pulseCount = 0;
    portEXIT_CRITICAL(&pulseMux);

    rpm = (pulses * 60000.0) / (elapsed * pulsesPerRevolution);
    lastRPM = now;
  }

  // 3. Read Accelerometer (100Hz non-blocking sampling loop for Live-Check mode)
  if (now - lastMPU >= 10) {
    lastMPU = now;
    if (mpuReady && accelDriver->readSampleG(accelX, accelY, accelZ)) {
      accelMagnitude = sqrt(accelX * accelX + accelY * accelY + accelZ * accelZ);

      vibBuffer[vibBufferIdx] = accelMagnitude;
      vibBufferIdx = (vibBufferIdx + 1) % VIB_BUFFER_SIZE;
      if (vibBufferIdx == 0) {
        vibBufferFull = true;
      }
    } else if (now % 1000 < 10 && probeI2C(0x68)) {
      mpuReady = accelDriver->begin(false);
    }
  }

  // 4. Read INA219 (Every 1000ms with dynamic I2C bus detection)
  if (now - lastINA >= 1000) {
    if (probeI2C(0x40)) {
      if (!inaReady) {
        inaReady = ina219.begin(&Wire);
      }
      if (inaReady) {
        busVoltage = ina219.getBusVoltage_V();
        currentMA  = ina219.getCurrent_mA();
        powerMW    = ina219.getPower_mW();
      }
    } else {
      inaReady = false;
      busVoltage = 0;
      currentMA = 0;
      powerMW = 0;
    }
    lastINA = now;
  }

  // 5. Read INMP441 Mic (Every 200ms)
  if (micReady && now - lastMic >= 200) {
    readMicrophone();
    lastMic = millis();
  }

  // 6. Push Live Data to HAVEN AI Website (Every 5 seconds)
  if (now - lastHavenUpload >= UPLOAD_INTERVAL_MS) {
    lastHavenUpload = now;
    postTelemetryToHaven();
  }
}

