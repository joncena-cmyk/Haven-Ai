# HAVEN — Capstone End-to-End Hardware & Machine Learning Guide
**Group 3 · CINS Capstone Project**

This guide provides the complete, plain-English, step-by-step roadmap for your project: from the physical sensors under the car hood to machine learning training and live web diagnosis.

---

## 📌 TABLE OF CONTENTS
1. [The Big Picture: How the Whole System Works](#1-the-big-picture)
2. [What Hardware You Need & How to Connect It](#2-hardware-setup--wiring)
3. [Physical Mounting on a Real Vehicle](#3-physical-mounting-on-a-vehicle)
4. [How to Gather Training Data](#4-gathering-training-data)
5. [How to Train the Machine Learning Model (`model.pkl`)](#5-training-the-ml-model)
6. [How the Live System Uses the Model (Inference)](#6-live-usage--inference)
7. [Step-by-Step Capstone Defense / Demo Script](#7-capstone-defense--demo-script)

---

## 1. THE BIG PICTURE

Your project diagnoses vehicle engine health using **multi-modal sensing**:
```
[ Car Engine ]
   │
   ├── 🎙️ Sound (INMP441 Microphone)          ──► Acoustic Pings & Knocks
   ├── 📈 Vibration (MPU6050 Accelerometer)   ──► Shaking & Impact Forces (G)
   ├── 🌡️ Heat (DS18B20 Temp Probe)           ──► Overheating (> 105°C)
   ├── ⏱️ Speed (Hall Sensor)                 ──► Engine RPM & Misfire Dips
   └── ⚡ Power (INA219 Voltage/Current)      ──► Alternator & Battery Health
   │
   ▼
[ ESP32 Microcontroller ] (Collects 5 sensor streams)
   │
   │ Wi-Fi HTTP POST (with ISO/SAE 21434 Hardware Key)
   ▼
[ HAVEN Web Platform (Laravel) ] ──► [ Background Queue Worker ]
                                                │
                                                ▼
                                    [ ML Inference Engine ]
                                     (Classifies Condition:
                                      Normal / Knocking / Misfire /
                                      Idling Rough / Overheating)
                                                │
                                                ▼
                                    [ AI Mechanic Copilot ]
                                     (Voice readout, urgency,
                                      probable causes & repair steps)
```

---

## 2. HARDWARE SETUP & WIRING

### The Microcontroller
* **ESP32 DevKit V1 (30-pin or 38-pin)** with Wi-Fi & Bluetooth.

### The 5 Sensor Modules & Exact Pins

| Module | Purpose | Connection Pin | ESP32 Pin | Extra Components |
|---|---|---|---|---|
| **DS18B20** | Engine / Coolant Temp (°C) | `DATA` | **D4 (GPIO 4)** | **4.7kΩ resistor** between DATA and 3.3V |
| **Hall Sensor** | Tachometer / Crankshaft RPM | `DIG` | **D23 (GPIO 23)** | **10kΩ resistor** between DIG and 3.3V |
| **MPU6050** | 3-Axis Engine Vibration (G) | `SDA` / `SCL` | **D21 / D22** | Connect VCC to 3.3V, GND to GND |
| **INA219** | Battery DC Volts & Amps | `SDA` / `SCL` | **D21 / D22** | Shares I2C with MPU6050 (0x40 vs 0x68) |
| **INMP441** | Digital MEMS Engine Mic | `SCK` / `WS` / `SD` | **D18 / D19 / D5** | Connect `L/R` to GND (left channel) |

### Powering the ESP32 in the Car
* Plug a standard **5V / 2A USB car cigarette lighter adapter** into the vehicle's 12V socket.
* Connect a Micro-USB / USB-C cable from the adapter directly into the ESP32 board.
* Alternatively, use a standard 5V portable power bank during road tests.

---

## 3. PHYSICAL MOUNTING ON A VEHICLE

Where and how to place the sensors under the hood:

### 1. MPU6050 (Vibration Sensor) — CRITICAL
* **Where:** Mount directly on the **metal engine valve cover** or **engine cylinder head**.
* **How:** Use a strong neodymium magnet mount, metal bracket, or heat-resistant 3M VHB double-sided tape.
* **Why:** The sensor MUST vibrate rigidly with the engine block. If it is hanging loose on wires, it will record road bumps instead of internal engine combustion shocks!

### 2. DS18B20 (Temperature Probe)
* **Where:** Zip-tie the stainless steel probe tightly against the **upper radiator hose** (near the thermostat housing) or against the engine block.
* **Tip:** Wrap a small piece of thermal insulation tape around the probe to protect it from ambient engine bay air.

### 3. Hall RPM Sensor
* **Where:** Positioned **2–5 mm** from the rotating crankshaft pulley or alternator pulley.
* **How:** Attach a tiny neodymium button magnet to the pulley rim with epoxy/tape. Each time the magnet passes the Hall sensor, it sends 1 electrical pulse to pin `D23`.

### 4. INA219 (Voltage & Current)
* **Where:** Connect `Vin+` and `Vin-` to the 12V car battery terminals or tap into the 12V fuse box / cigarette lighter lines.

### 5. INMP441 (MEMS Microphone)
* **Where:** Mount 15–30 cm away from the engine block (e.g., zip-tied to the firewall or inner fender), pointing toward the intake/cylinder head.
* **Tip:** Put a small foam windscreen over the mic opening to reduce radiator fan turbulence.

---

## 4. GATHERING TRAINING DATA

A machine learning model needs **labeled examples** to learn what each condition sounds and shakes like.

### The 5 Target Engine Conditions:
1. **`normal`**: Warm engine running at idle (750–850 RPM), coolant ~90°C, smooth harmonic hum, low vibration ($< 1.2\text{g}$).
2. **`knocking`**: Metallic pinging/clattering under load, high vibration shockwaves ($> 3.5\text{g}$).
3. **`misfiring`**: Engine stuttering, unburnt fuel, cyclical torque dips, irregular RPM drops.
4. **`idling_rough`**: Shaking cabin, low RPM ($< 700$), high continuous baseline vibration ($> 0.5\text{g}$ RMS) caused by dirty throttle body or bad engine mounts.
5. **`overheating`**: Coolant temperature $\ge 105^\circ\text{C}$, boiling cavitation noise, electric fans running at 100%.

### How to Collect Samples in Real Life:

#### Safe Ways to Create Test Conditions:
* **For `normal`:** Record any healthy family car, school test car, or motorcycle idling and revving in neutral.
* **For `misfiring`:** On a test vehicle, **temporarily disconnect ONE spark plug wire or ONE fuel injector plug** for 15–30 seconds. The engine will instantly misfire, shake, and drop RPM safely. Reconnect the plug when done!
* **For `idling_rough`:** Turn on the A/C to full blast with high electrical load, or disconnect an idle air vacuum hose temporarily to induce idle hunting.
* **For `knocking`:** Use recordings from automotive mechanical failure benchmarks, or simulate piston detonation impacts on a test fixture.
* **For `overheating`:** Recorded during radiator fan disconnect on a test bench or simulated via thermal heat guns on the probe.

### Two Ways to Feed Collected Data to HAVEN:
* **Method 1 (Direct ESP32 Wi-Fi):** Flash the Arduino sketch provided in **Diagnostics -> ESP32 IoT Hub -> C++ Firmware**. The board connects to your mobile phone Wi-Fi hotspot and automatically transmits live packets to `POST /api/v1/telemetry/ingest`.
* **Method 2 (Web Capture Wizard):** Go to `http://localhost:8000/diagnostics/create`. Hold your phone/laptop near the engine, record 10 seconds of sound via the browser microphone, upload the ESP32 vibration CSV file, and click save.

---

## 5. TRAINING THE ML MODEL (`model.pkl`)

Once you have gathered data, you train the machine learning brain!

### Step 1: Open Terminal in `ml_backend/`
```bash
cd c:\xampp\htdocs\Haven-Ai\ml_backend
```

### Step 2: Install Python Packages (One-Time Setup)
```bash
pip install -r requirements.txt
```

### Step 3: Train the Classifier
HAVEN includes an automated training pipeline: [`ml_backend/train.py`](file:///c:/xampp/htdocs/Haven-Ai/ml_backend/train.py).

#### Option A — Train using Synthetic Calibration Data (Instant 1-Click Training):
If you haven't recorded real cars yet, generate 750 calibrated automotive samples and train the model immediately:
```bash
python train.py --generate-synthetic
```
**Output:**
```
[INFO] Generating 750 synthetic automotive telemetry samples...
[INFO] Training on 750 samples across 5 classes...
[INFO] 5-Fold Cross-Validation Accuracy: 98.40%
[INFO] Holdout Test Set Accuracy: 98.67%
--- CLASSIFICATION REPORT ---
               precision    recall  f1-score   support
      normal       0.97      1.00      0.98        30
    knocking       1.00      0.97      0.98        30
   misfiring       1.00      1.00      1.00        30
idling_rough       0.97      0.97      0.97        30
 overheating       1.00      1.00      1.00        30
[INFO] Success! Trained model exported to: model.pkl
```

#### Option B — Train using Real Car Recordings:
1. Put your recorded `.wav` audio files and `.csv` vibration files into:
   * `ml_backend/dataset/normal/`
   * `ml_backend/dataset/knocking/`
   * `ml_backend/dataset/misfiring/`
   * `ml_backend/dataset/idling_rough/`
   * `ml_backend/dataset/overheating/`
2. Run:
   ```bash
   python train.py
   ```
3. `train.py` automatically extracts 13 MFCCs, Spectral Centroid, Peak G, RMS G, Crest Factor, Temp, and RPM, fits a `RandomForestClassifier`, and generates **`model.pkl`**.

---

## 6. LIVE USAGE & INFERENCE

How the trained model makes decisions in real time:

1. **ESP32 Broadcasts Packet:**
   When the car runs, the ESP32 samples the sensors and sends:
   ```json
   POST /api/v1/telemetry/ingest
   Header: X-Haven-Device-Key: hvn_dev_cde1c79d141a9c...
   {
     "vehicle_id": 1,
     "temperature_c": 108.4,
     "rpm": 880,
     "voltage_v": 13.5,
     "samples": [1.02, 1.05, 3.84, 1.12],
     "auto_analyze": true
   }
   ```

2. **Asynchronous Background Queue Worker:**
   * Laravel returns HTTP `201 Created` immediately so the ESP32 doesn't wait.
   * `AnalyzeDiagnosticJob` executes in the background (`php artisan queue:listen`).
   * It loads `model.pkl` (or the resilient multi-sensor heuristic fallback).
   * It maps the input vector:
     $$\vec{X} = [\text{Peak G}, \text{RMS G}, \text{Crest}, \text{Temp}, \text{RPM}, \text{Volts}, \text{MFCCs}\dots]$$
   * Model outputs: **Condition: `overheating` (98% confidence)**.

3. **User Experience on Web & Phone:**
   * The diagnostic page auto-refreshes.
   * Displays the **ESP32 Hardware Multi-Sensor Telemetry Array** (108.4°C in red, 880 RPM, 13.5V).
   * The **HAVEN AI Mechanic Copilot (Siri/Gigi)** speaks aloud:
     > *"Stop Immediately! Engine temperature reached 108.4°C exceeding thermal limits. Driving risks warped cylinder heads."*
   * Displays estimated repair costs and clickable action checklists.

---

## 7. CAPSTONE DEFENSE & DEMO SCRIPT

When presenting to your professors/panel:

1. **Show the Physical Hardware:**
   * Hold up the ESP32 board and point out the 5 sensors: DS18B20 (probe), Hall sensor (magnet pickup), MPU6050 (gyro/accel), INA219 (power), and INMP441 (mic).
   * Open **Diagnostics -> ESP32 IoT Hub -> Pinout & Wiring Guide** on the projector to show the wiring diagram.

2. **Demonstrate 1-Click Live Hardware Telemetry:**
   * In the **ESP32 IoT Hub** modal, switch to **1-Click Hardware Simulator**.
   * Select **Rod Knocking** and click **Broadcast ESP32 Wi-Fi Telemetry Packet**.
   * Show the live console transmitting with the **ISO/SAE 21434 Hardware Key**.
   * Show the background queue worker processing the job in real time.

3. **Demonstrate the AI Mechanic Copilot:**
   * Open the newly created diagnostic session.
   * Show the 5-sensor dashboard tiles.
   * Tap the **Microphone** button or click one of the suggestion chips:
     * *"Can I drive 10 miles to a garage?"*
   * Listen to the AI Assistant speak the diagnosis aloud with repair estimates.

4. **Show ML Training (`train.py`):**
   * Open your terminal and run `python train.py --generate-synthetic`.
   * Show the 98%+ accuracy score and show that `model.pkl` is loaded by the FastAPI backend.
