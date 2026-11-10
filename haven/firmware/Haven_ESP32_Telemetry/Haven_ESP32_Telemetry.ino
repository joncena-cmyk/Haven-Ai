// ============================================================
// HAVEN AI — ESP32 Multi-Sensor Telemetry Node v2.5
// Vehicle: 2026 Toyota Corolla  |  Vehicle ID: 1
// POST → http://192.168.254.106:8000/api/v1/telemetry/ingest
// Auth: X-Haven-Device-Key header (ISO/SAE 21434 §9)
// ============================================================

#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <OneWire.h>
#include <DallasTemperature.h>

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
#ifndef HAVEN_PING_URL
  #define HAVEN_PING_URL "http://192.168.1.100:8000/api/v1/telemetry/ping"
#endif
#ifndef HAVEN_DEVICE_KEY
  #define HAVEN_DEVICE_KEY ""
#endif
#ifndef HAVEN_VEHICLE_ID
  #define HAVEN_VEHICLE_ID 1
#endif

// ── 1. CONFIGURE VIA secrets.h OR NVS CAPTIVE PORTAL (http://192.168.4.1/config) ──
Preferences prefs;
WebServer portalServer(80);
char ssid[64]       = HAVEN_WIFI_SSID;
char password[64]   = HAVEN_WIFI_PASS;
char serverUrl[128] = HAVEN_SERVER_URL;
char pingUrl[128]   = HAVEN_PING_URL;
char deviceKey[64]  = HAVEN_DEVICE_KEY;
const int vehicleId = HAVEN_VEHICLE_ID;
// ────────────────────────────────────────────────────────────

// ── 2. SENSOR PIN ASSIGNMENTS & 100Hz 3-AXIS RING BUFFER ────
#define PIN_DS18B20    4    // Temperature (OneWire, async non-blocking mode)
#define PIN_HALL_RPM   23   // RPM sensor
#define PIN_MPU_SDA    21   // MPU6050 SDA
#define PIN_MPU_SCL    22   // MPU6050 SCL
#define MPU_ADDR       0x68
#define VIB_BUFFER_SIZE 128

OneWire oneWire(PIN_DS18B20);
DallasTemperature tempSensor(&oneWire);
float vibBufX[VIB_BUFFER_SIZE];
float vibBufY[VIB_BUFFER_SIZE];
float vibBufZ[VIB_BUFFER_SIZE];
int vibBufferIdx = 0;
bool vibBufferFull = false;
bool accelReady = false;
float latestAx = 0.0f, latestAy = 0.0f, latestAz = 0.0f;
unsigned long lastSampleMs = 0;
// ────────────────────────────────────────────────────────────

// ── 3. SEND INTERVAL ─────────────────────────────────────────
const unsigned long SEND_INTERVAL_MS = 5000; // Ingest every 5 seconds
unsigned long lastSend = 0;
// ────────────────────────────────────────────────────────────

// ── 4. HARDWARE I2C ACCELEROMETER & SENSOR READINGS ──────────
bool initAccelI2C() {
  Wire.begin(PIN_MPU_SDA, PIN_MPU_SCL, 400000);
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x6B); // PWR_MGMT_1: wake up
  Wire.write(0x00);
  if (Wire.endTransmission() != 0) return false;
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x1C); // ACCEL_CONFIG: +/- 8g (4096 LSB/g)
  Wire.write(0x10);
  return (Wire.endTransmission() == 0);
}

bool readAccelG(float &ax, float &ay, float &az) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x3B);
  if (Wire.endTransmission(false) != 0) return false;
  if (Wire.requestFrom((uint8_t)MPU_ADDR, (uint8_t)6, (uint8_t)true) != 6) return false;
  int16_t rx = (int16_t)((Wire.read() << 8) | Wire.read());
  int16_t ry = (int16_t)((Wire.read() << 8) | Wire.read());
  int16_t rz = (int16_t)((Wire.read() << 8) | Wire.read());
  ax = (float)rx / 4096.0f;
  ay = (float)ry / 4096.0f;
  az = (float)rz / 4096.0f;
  return true;
}

float readTemperatureC() {
  if (tempSensor.getDeviceCount() == 0) return NAN;
  float t = tempSensor.getTempCByIndex(0);
  tempSensor.requestTemperatures();
  return (t >= -50.0f && t <= 125.0f && t != DEVICE_DISCONNECTED_C && t != 85.0f) ? t : NAN;
}

void collectVibrationSamples(JsonArray& arr, int maxCount) {
  int available = vibBufferFull ? VIB_BUFFER_SIZE : vibBufferIdx;
  int countToSend = (available > maxCount) ? maxCount : available;
  for (int i = 0; i < countToSend; i++) {
    int idx = (vibBufferIdx - countToSend + i + VIB_BUFFER_SIZE) % VIB_BUFFER_SIZE;
    JsonObject sObj = arr.createNestedObject();
    sObj["x"] = round(vibBufX[idx] * 1000.0f) / 1000.0f;
    sObj["y"] = round(vibBufY[idx] * 1000.0f) / 1000.0f;
    sObj["z"] = round(vibBufZ[idx] * 1000.0f) / 1000.0f;
  }
}
// ────────────────────────────────────────────────────────────

void connectWifi() {
  Serial.println("\n[HAVEN] Connecting to Wi-Fi: " + String(ssid));
  WiFi.begin(ssid, password);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[HAVEN] [OK] Wi-Fi connected!");
    Serial.println("[HAVEN] ESP32 IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("\n[HAVEN] [FAIL] Wi-Fi connection failed. Check SSID/password.");
  }
}

bool pingServer() {
  HTTPClient http;
  http.begin(pingUrl);
  http.setTimeout(5000);
  int code = http.GET();
  bool ok = (code == 200);
  if (ok) {
    Serial.println("[PING] [OK] HAVEN Server Online!");
  } else {
    Serial.println("[PING] [FAIL] Cannot reach server. HTTP Code: " + String(code));
    Serial.println("[PING] Make sure your laptop and ESP32 are on the exact same Wi-Fi.");
  }
  http.end();
  return ok;
}

void sendTelemetry() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HAVEN] Reconnecting Wi-Fi...");
    connectWifi();
    return;
  }

  DynamicJsonDocument doc(8192);
  doc["vehicle_id"]      = vehicleId;
  doc["device_id"]       = "ESP32-HAVEN-NODE-01";
  doc["device_key"]      = deviceKey;
  doc["sample_rate_hz"]  = 100;
  doc["save_session"]    = false;
  doc["auto_analyze"]    = false;

  float tempC = readTemperatureC();
  if (!isnan(tempC)) {
    doc["temperature_c"] = round(tempC * 10.0f) / 10.0f;
  } else {
    doc["temperature_c"] = nullptr;
  }

  if (accelReady) {
    JsonArray samples = doc.createNestedArray("samples");
    collectVibrationSamples(samples, 64);
    JsonObject mpuObj = doc.createNestedObject("sensor_data").createNestedObject("mpu6050");
    mpuObj["ax"] = latestAx;
    mpuObj["ay"] = latestAy;
    mpuObj["az"] = latestAz;
  }

  String body;
  serializeJson(doc, body);

  HTTPClient http;
  http.begin(serverUrl);
  http.setTimeout(2000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Haven-Device-Key", deviceKey);

  Serial.println("\n[HAVEN] Sending telemetry...");
  int responseCode = http.POST(body);
  Serial.printf("[HAVEN] Server Response Code: %d\n", responseCode);

  if (responseCode == 201 || responseCode == 200) {
    String resp = http.getString();
    Serial.println("[HAVEN] [OK] Telemetry ingested successfully!");
    StaticJsonDocument<512> respDoc;
    if (!deserializeJson(respDoc, resp)) {
      int diagId = respDoc["diagnostic_id"] | 0;
      if (diagId > 0) {
        Serial.printf("[HAVEN] New Diagnostic Session Created: #%d\n", diagId);
        Serial.printf("[HAVEN] View in browser: http://127.0.0.1:8000/diagnostics/%d\n", diagId);
      }
    }
  } else if (responseCode == 401) {
    Serial.println("[HAVEN] [AUTH ERROR] Unauthorized: Device Key mismatch.");
  } else {
    Serial.println("[HAVEN] [ERROR] Ingestion failed. Check response.");
  }

  http.end();
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("===========================================");
  Serial.println("  HAVEN AI — ESP32 Diagnostic Ingestion");
  Serial.println("  Target: 2026 Toyota Corolla (Vehicle ID: 1)");
  Serial.println("===========================================");

  // Load saved Wi-Fi & Server URL from NVS Flash if available
  prefs.begin("haven", true);
  if (prefs.isKey("ssid")) {
    prefs.getString("ssid", ssid, sizeof(ssid));
    prefs.getString("pass", password, sizeof(password));
    prefs.getString("server", serverUrl, sizeof(serverUrl));
    prefs.getString("key", deviceKey, sizeof(deviceKey));
  }
  prefs.end();

  accelReady = initAccelI2C();
  tempSensor.begin();
  tempSensor.setResolution(10);
  tempSensor.setWaitForConversion(false);
  tempSensor.requestTemperatures();

  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP("HAVEN_AI_LOCAL", "haven1234");
  connectWifi();

  portalServer.on("/config", []() {
    String html = "<html><body style='background:#090d16;color:#e2e8f0;font-family:sans-serif;padding:1.5rem;'><h2>HAVEN AI Node Setup</h2><form method='POST' action='/save'>SSID:<br><input name='ssid' value='" + String(ssid) + "'><br>Pass:<br><input name='pass' type='password'><br>Server:<br><input name='server' value='" + String(serverUrl) + "'><br>Key:<br><input name='key' value='" + String(deviceKey) + "'><br><br><button type='submit'>Save & Reboot</button></form></body></html>";
    portalServer.send(200, "text/html", html);
  });
  portalServer.on("/save", HTTP_POST, []() {
    if (strlen(deviceKey) > 0 && portalServer.arg("key") != String(deviceKey)) {
      portalServer.send(403, "text/plain", "Forbidden: matching device key required to overwrite NVS.");
      return;
    }
    prefs.begin("haven", false);
    if (portalServer.hasArg("ssid")) prefs.putString("ssid", portalServer.arg("ssid"));
    if (portalServer.hasArg("pass")) prefs.putString("pass", portalServer.arg("pass"));
    if (portalServer.hasArg("server")) prefs.putString("server", portalServer.arg("server"));
    if (portalServer.hasArg("key")) prefs.putString("key", portalServer.arg("key"));
    prefs.end();
    portalServer.send(200, "text/plain", "Saved! Rebooting...");
    delay(800);
    ESP.restart();
  });
  portalServer.begin();

  if (WiFi.status() == WL_CONNECTED) {
    delay(1000);
    pingServer();
  }
}

void loop() {
  portalServer.handleClient();
  unsigned long now = millis();
  // Strict 100Hz non-blocking 3-axis hardware vibration sampling (10ms interval)
  if (now - lastSampleMs >= 10) {
    lastSampleMs = now;
    if (accelReady && readAccelG(latestAx, latestAy, latestAz)) {
      vibBufX[vibBufferIdx] = latestAx;
      vibBufY[vibBufferIdx] = latestAy;
      vibBufZ[vibBufferIdx] = latestAz;
      vibBufferIdx = (vibBufferIdx + 1) % VIB_BUFFER_SIZE;
      if (vibBufferIdx == 0) vibBufferFull = true;
    } else if (!accelReady && (now % 1000 < 10)) {
      accelReady = initAccelI2C();
    }
  }
  if (now - lastSend >= SEND_INTERVAL_MS) {
    lastSend = now;
    sendTelemetry();
  }
}

