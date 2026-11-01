// ============================================================
// HAVEN AI — ESP32 Multi-Sensor Telemetry Node v2.5
// Vehicle: 2026 Toyota Corolla  |  Vehicle ID: 1
// POST → http://192.168.254.106:8000/api/v1/telemetry/ingest
// Auth: X-Haven-Device-Key header (ISO/SAE 21434 §9)
// ============================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ── 1. CONFIGURE THESE ──────────────────────────────────────
// ⚠️ IMPORTANT: Laptop AND ESP32 must be on the SAME Wi-Fi network.
//    Your laptop is at: 192.168.254.106 port 8000
const char* ssid       = "YOUR_WIFI_SSID";       // ← Change to your Wi-Fi name
const char* password   = "YOUR_WIFI_PASSWORD";   // ← Change to your Wi-Fi password
const char* serverUrl  = "http://192.168.254.106:8000/api/v1/telemetry/ingest";
const char* pingUrl    = "http://192.168.254.106:8000/api/v1/telemetry/ping";
const char* deviceKey  = "hvn_dev_cde1c79d141a9cdcb064d3ef4b9e63f2";
const int   vehicleId  = 1;
// ────────────────────────────────────────────────────────────

// ── 2. SENSOR PIN ASSIGNMENTS ────────────────────────────────
#define PIN_DS18B20    4    // Temperature (OneWire)
#define PIN_HALL_RPM   23   // RPM sensor
#define PIN_MPU_SDA    21   // MPU6050 SDA
#define PIN_MPU_SCL    22   // MPU6050 SCL
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

  connectWifi();

  if (WiFi.status() == WL_CONNECTED) {
    delay(1000);
    pingServer();
  }
}

void loop() {
  unsigned long now = millis();
  if (now - lastSend >= SEND_INTERVAL_MS) {
    lastSend = now;
    sendTelemetry();
  }
  delay(100);
}

