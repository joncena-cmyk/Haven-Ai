// ============================================================
// HAVEN AI — ESP32 Multi-Sensor Telemetry Node v2.5
// Vehicle: 2026 Toyota Corolla  |  Vehicle ID: 1
// POST → http://192.168.254.106:8000/api/v1/telemetry/ingest
// Auth: X-Haven-Device-Key header (ISO/SAE 21434 §9)
// ============================================================

#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ── 1. CONFIGURE THESE (Overridable via NVS Captive Portal http://192.168.4.1/config) ──
Preferences prefs;
WebServer portalServer(80);
char ssid[64]       = "YOUR_WIFI_SSID";       // ← Change or configure via AP portal
char password[64]   = "YOUR_WIFI_PASSWORD";   // ← Change or configure via AP portal
char serverUrl[128] = "http://192.168.254.106:8000/api/v1/telemetry/ingest";
char pingUrl[128]   = "http://192.168.254.106:8000/api/v1/telemetry/ping";
char deviceKey[64]  = "hvn_dev_cde1c79d141a9cdcb064d3ef4b9e63f2";
const int vehicleId = 1;
// ────────────────────────────────────────────────────────────

// ── 2. SENSOR PIN ASSIGNMENTS & 100Hz RING BUFFER ───────────
#define PIN_DS18B20    4    // Temperature (OneWire, async non-blocking mode)
#define PIN_HALL_RPM   23   // RPM sensor
#define PIN_MPU_SDA    21   // MPU6050 SDA
#define PIN_MPU_SCL    22   // MPU6050 SCL
#define VIB_BUFFER_SIZE 128
float vibBuffer[VIB_BUFFER_SIZE];
int vibBufferIdx = 0;
unsigned long lastSampleMs = 0;
// ────────────────────────────────────────────────────────────

// ── 3. SEND INTERVAL ─────────────────────────────────────────
const unsigned long SEND_INTERVAL_MS = 10000; // Ingest every 10 seconds
unsigned long lastSend = 0;
// ────────────────────────────────────────────────────────────

// ── 4. SENSOR READINGS (Simulated / Ready for real sensor libs)
float readTemperatureC() {
  return 72.5 + random(0, 230) / 10.0;
}

int readRPM() {
  return 750 + random(0, 100);
}

float readVoltageV() {
  return 12.4 + random(0, 40) / 100.0;
}

float readCurrentMa() {
  return 150.0 + random(0, 500) / 10.0;
}

void collectVibrationSamples(JsonArray& arr, int count) {
  for (int i = 0; i < count; i++) {
    float x = 0.05 + random(-50, 50) / 1000.0;
    float y = 0.03 + random(-30, 30) / 1000.0;
    float z = 1.00 + random(-80, 80) / 1000.0;
    float mag = sqrt(x*x + y*y + z*z);
    arr.add(round(mag * 10000.0) / 10000.0);
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

  StaticJsonDocument<2048> doc;
  doc["vehicle_id"]      = vehicleId;
  doc["device_id"]       = "ESP32-HAVEN-NODE-01";
  doc["device_key"]      = deviceKey;
  doc["sample_rate_hz"]  = 50;
  doc["auto_analyze"]    = true;

  float tempC     = readTemperatureC();
  int   rpm       = readRPM();
  float voltageV  = readVoltageV();
  float currentMa = readCurrentMa();

  doc["temperature_c"] = tempC;
  doc["rpm"]           = rpm;
  doc["voltage_v"]     = voltageV;
  doc["current_ma"]    = currentMa;

  JsonArray samples = doc.createNestedArray("samples");
  collectVibrationSamples(samples, 50);

  String body;
  serializeJson(doc, body);

  HTTPClient http;
  http.begin(serverUrl);
  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Haven-Device-Key", deviceKey);

  Serial.println("\n[HAVEN] Sending telemetry...");
  Serial.printf("  Temp: %.1f deg C | RPM: %d | Battery: %.2f V | Current: %.1f mA\n",
                tempC, rpm, voltageV, currentMa);

  int responseCode = http.POST(body);
  Serial.printf("[HAVEN] Server Response Code: %d\n", responseCode);

  if (responseCode == 201) {
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

  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP("HAVEN_AI_LOCAL", "haven1234");
  connectWifi();

  portalServer.on("/config", []() {
    String html = "<html><body style='background:#090d16;color:#e2e8f0;font-family:sans-serif;padding:1.5rem;'><h2>HAVEN AI Node Setup</h2><form method='POST' action='/save'>SSID:<br><input name='ssid' value='" + String(ssid) + "'><br>Pass:<br><input name='pass' type='password'><br>Server:<br><input name='server' value='" + String(serverUrl) + "'><br>Key:<br><input name='key' value='" + String(deviceKey) + "'><br><br><button type='submit'>Save & Reboot</button></form></body></html>";
    portalServer.send(200, "text/html", html);
  });
  portalServer.on("/save", HTTP_POST, []() {
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
  // Strict 100Hz non-blocking vibration sampling (10ms interval)
  if (now - lastSampleMs >= 10) {
    lastSampleMs = now;
    float z = 1.0 + random(-80, 80) / 1000.0;
    vibBuffer[vibBufferIdx] = fabs(z);
    vibBufferIdx = (vibBufferIdx + 1) % VIB_BUFFER_SIZE;
  }
  if (now - lastSend >= SEND_INTERVAL_MS) {
    lastSend = now;
    sendTelemetry();
  }
}

