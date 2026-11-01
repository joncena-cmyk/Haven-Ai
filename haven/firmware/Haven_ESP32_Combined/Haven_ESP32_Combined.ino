#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <Adafruit_INA219.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <driver/i2s.h>
#include <ArduinoJson.h>
#include <math.h>

// ============================================================
// HAVEN AI - ESP32 FIVE SENSOR DAQ & AUTOMOTIVE INGEST NODE
// Complies with ISO/SAE 21434 Hardware Key Authentication
// ============================================================

// ── 1. NETWORK & HAVEN CONFIGURATION ────────────────────────
// ⚠️ Set to the same Wi-Fi your laptop is connected to:
const char* wifiSSID     = "GFiber_FC756";       // ← YOUR WI-FI NAME
const char* wifiPassword = "4C34FA60";   // ← YOUR WI-FI PASSWORD

// HAVEN Server Endpoint (Your Laptop's IP):
const char* havenServerUrl = "http://192.168.254.106:8000/api/v1/telemetry/ingest";
const char* havenDeviceKey = "hvn_dev_cde1c79d141a9cdcb064d3ef4b9e63f2";
const int   vehicleId      = 1;

// Also host local AP for standalone fallback if needed
const char* apSSID     = "HAVEN_AI_LOCAL";
const char* apPassword = "haven1234";

WebServer server(80);

// ── 2. GPIO PIN ASSIGNMENTS ─────────────────────────────────
#define TEMP_PIN 4
#define HALL_PIN 23

#define MPU_SDA 21
#define MPU_SCL 22

#define MIC_BCLK 18
#define MIC_WS   19
#define MIC_SD   5

// ── 3. SENSOR OBJECTS ───────────────────────────────────────
OneWire oneWire(TEMP_PIN);
DallasTemperature tempSensor(&oneWire);

Adafruit_MPU6050 mpu;
bool mpuReady = false;

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
    mpuReady = mpu.begin(0x68, &Wire);
  }

  bool inaDetected = probeI2C(0x40);
  if (!inaDetected) {
    inaReady = false;
  } else if (!inaReady) {
    inaReady = ina219.begin(&Wire);
  }

  bool ds18Plugged = (!isnan(temperature) && tempSensor.getDeviceCount() > 0);

  HTTPClient http;
  http.begin(havenServerUrl);
  http.setTimeout(5000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Haven-Device-Key", havenDeviceKey);

  StaticJsonDocument<2048> doc;
  doc["vehicle_id"]     = vehicleId;
  doc["device_id"]      = "ESP32-5SENSOR-NODE";
  doc["device_key"]     = havenDeviceKey;
  doc["save_session"]   = false; // Hardware checkup only: does NOT create database sessions
  doc["auto_analyze"]   = false;
  doc["sample_rate_hz"] = 50;

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
    doc["peak_g"] = round(accelMagnitude * 100.0) / 100.0;
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
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=============================================");
  Serial.println("  HAVEN AI — 5-Sensor Automotive Node v2.5");
  Serial.println("=============================================");

  // Hall RPM Pin
  pinMode(HALL_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(HALL_PIN), countPulse, FALLING);

  // DS18B20 Temp Sensor
  tempSensor.begin();
  tempSensor.setResolution(10);
  tempSensor.setWaitForConversion(false);
  tempSensor.requestTemperatures();

  // I2C: MPU6050 & INA219
  Wire.begin(MPU_SDA, MPU_SCL);
  Wire.setClock(100000);

  mpuReady = mpu.begin(0x68, &Wire);
  if (mpuReady) {
    mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
    mpu.setGyroRange(MPU6050_RANGE_500_DEG);
    mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);
    Serial.println("[OK] MPU6050 accelerometer initialized.");
  } else {
    Serial.println("[WARN] MPU6050 not detected.");
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
  WiFi.softAP(apSSID, apPassword);
  Serial.printf("[AP] Local Hotspot started: %s (IP: %s)\n", apSSID, WiFi.softAPIP().toString().c_str());

  Serial.printf("[STA] Connecting to your Wi-Fi: %s ...\n", wifiSSID);
  WiFi.begin(wifiSSID, wifiPassword);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 25) {
    delay(400);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[STA] [OK] Connected to Home/Lab Wi-Fi!");
    Serial.printf("[STA] ESP32 IP: %s\n", WiFi.localIP().toString().c_str());
  } else {
    Serial.println("\n[STA] [WARN] Could not connect to Wi-Fi. Check SSID/Password.");
  }

  // Local Web Server endpoints
  server.on("/data", handleData);
  server.begin();

  lastRPM = millis();
}

// ============================================================
// MAIN LOOP
// ============================================================
void loop() {
  server.handleClient();
  unsigned long now = millis();

  // 1. Read DS18B20 (Every 1000ms with device count check)
  if (now - lastTemp >= 1000) {
    if (tempSensor.getDeviceCount() == 0) {
      temperature = NAN;
    } else {
      float reading = tempSensor.getTempCByIndex(0);
      if (reading >= -50.0 && reading <= 125.0 && reading != DEVICE_DISCONNECTED_C && reading != 85.0) {
        temperature = reading;
      } else {
        temperature = NAN;
      }
    }
    tempSensor.requestTemperatures();
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

  // 3. Read MPU6050 (Every 100ms with dynamic I2C bus detection)
  if (now - lastMPU >= 100) {
    if (probeI2C(0x68)) {
      if (!mpuReady) {
        mpuReady = mpu.begin(0x68, &Wire);
      }
      if (mpuReady) {
        sensors_event_t a, g, t;
        mpu.getEvent(&a, &g, &t);
        accelX = a.acceleration.x;
        accelY = a.acceleration.y;
        accelZ = a.acceleration.z;
        accelMagnitude = sqrt(accelX * accelX + accelY * accelY + accelZ * accelZ);
      }
    } else {
      mpuReady = false;
      accelMagnitude = 0;
      accelX = 0; accelY = 0; accelZ = 0;
    }
    lastMPU = now;
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

