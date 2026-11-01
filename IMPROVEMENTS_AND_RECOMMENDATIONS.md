# 🛡️ HAVEN AI — System Audit, Technical Improvements & Security Roadmap

> **Target Platform:** HAVEN Automotive Diagnostic & Telemetry Intelligence System  
> **Repository:** `Haven-Ai` (`/haven` & `/ml_backend`)  
> **Standards Reference:** ISO/IEC 27001:2022 (Information Security) · ISO/SAE 21434:2021 (Road Vehicle Cybersecurity)  
> **Prepared For:** Capstone Defense, Engineering Team, and Alpha Evaluation  

---

## 📑 Table of Contents
1. [Executive Summary & Recent Alpha Fixes](#1-executive-summary--recent-alpha-fixes)
2. [Security & Cryptographic Hardening (ISO/IEC 27001 & ISO/SAE 21434)](#2-security--cryptographic-hardening)
3. [AI & Machine Learning Innovation Roadmap](#3-ai--machine-learning-innovation-roadmap)
4. [Hardware & Edge IoT Enhancements (ESP32 & Sensors)](#4-hardware--edge-iot-enhancements)
5. [Full-Stack Architecture & Cloud Scalability](#5-full-stack-architecture--cloud-scalability)
6. [Recommended APIs (External Integrations & Internal Endpoints)](#7-recommended-apis-external-integrations--internal-endpoints)
7. [Prioritized Implementation Matrix](#8-prioritized-implementation-matrix)

---

## 1. Executive Summary & Recent Alpha Fixes

During the Alpha and QA tester audit, several high-impact UX frictions and evaluator traps were identified and rectified:

### ✅ Remediated in Latest Revision
* **Stale Stage Banners Removed:** Dashboard and Diagnostic Creation Wizard no longer display obsolete *"Stage 4 active · ML planned for Stage 5"* text. All interfaces reflect **Stage 8 Active: Dual-Tier ML Inference & 5-Sensor ESP32 Telemetry Live**.
* **Vehicle Detail CTA Restored:** Unlocked the primary *"Start Diagnostic"* action on `/vehicles/{id}` which previously had a hardcoded `disabled` state.
* **Vehicle Hardware Key Card Added:** Added a dedicated **ESP32 Ingest Key Card** with 1-click clipboard copy directly onto the vehicle profile page, eliminating the need to search through user profile logs.
* **Printable Inspection Report Enhanced:** Embedded the complete 5-sensor multi-telemetry array (DS18B20 coolant temperature, crankshaft Hall RPM, INA219 bus voltage and current) into `/diagnostics/{id}/report` and added a direct navigation link from `/diagnostics/{id}`.
* **Manual DAQ Telemetry Fields:** Added optional manual inputs for temperature, RPM, voltage, and current in Step 3 of the Diagnostic Wizard for testing when hardware Wi-Fi is unavailable.
* **Automated Inference Dispatch:** Submitting via the web wizard now immediately queues background ML analysis via `AnalyzeDiagnosticJob`.

---

## 2. Security & Cryptographic Hardening

To establish institutional-grade security aligned with **ISO/IEC 27001:2022** and **ISO/SAE 21434:2021 (Automotive Cybersecurity)**, the following upgrades are recommended:

### A. HMAC-SHA256 Payload Signature Verification
* **Current State:** The ESP32 authenticates by sending a static vehicle token in the `X-Haven-Device-Key` header over HTTPS.
* **Vulnerability:** If an attacker intercepts the transmission or snoops the microcontroller flash, they could replay or forge telemetry packets.
* **Proposed Enhancement:**
  - Implement HMAC-SHA256 request signing.
  - The ESP32 generates a Unix timestamp nonce and hashes the request body:
    $$\text{Signature} = \text{HMAC-SHA256}(\text{DeviceKey}, \text{Timestamp} + \text{Body})$$
  - The server verifies that the timestamp is within $\pm 60$ seconds (preventing replay attacks) and that the signature matches before writing to the database.

### B. Two-Factor Authentication (2FA / TOTP) & Brute-Force Shielding
* **Current State:** Session-based email/password authentication via Laravel Breeze.
* **Proposed Enhancement:**
  - Introduce RFC 6238 Time-Based One-Time Password (TOTP) support (Google Authenticator / Microsoft Authenticator) for admin and technician profiles.
  - Rate-limit failed login attempts strictly per IP and account (e.g., maximum 5 attempts per 15 minutes with exponential lockout).

### C. Role-Based Access Control (RBAC)
* **Current State:** Flat single-user structure where each user owns their vehicles and sessions.
* **Proposed Enhancement:**
  - Introduce roles:
    1. `Lead Diagnostic Technician`: Can run ML models, export raw datasets, calibrate sensors, and approve diagnostic reports.
    2. `Fleet Operator / Customer`: Read-only access to vehicle health cards, printable reports, and maintenance schedules.
    3. `System Administrator`: Access to ISO/IEC 27001 audit logs, API rate limiting, and hardware key revocation.

### D. Cryptographic Data-at-Rest & Media Hygiene
* **Current State:** Audio recordings and vibration CSVs are stored in `storage/app/public/diagnostics/`.
* **Proposed Enhancement:**
  - Store sensitive diagnostic audio using AES-256 server-side encryption.
  - Implement a retention lifecycle policy (e.g., auto-archiving raw PCM waveforms after 90 days while preserving extracted acoustic feature vectors).
  - Add strict HTTP security headers:
    ```http
    Strict-Transport-Security: max-age=31536000; includeSubDomains
    Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';
    X-Frame-Options: SAMEORIGIN
    X-Content-Type-Options: nosniff
    Referrer-Policy: strict-origin-when-cross-origin
    ```

---

## 3. AI & Machine Learning Innovation Roadmap

### A. Continuous Active Learning (Mechanic Human-in-the-Loop)
* **Current State:** Static Random Forest model (`engine_classifier.joblib`) trained offline with synthetic/historical data.
* **Proposed Enhancement:**
  - Add a **"Verify / Correct Diagnosis"** toggle on the Diagnostic Show page.
  - When an experienced technician confirms a diagnosis (or corrects a misfire to normal), the feature vector is tagged as `ground_truth = true` in the database.
  - A scheduled background artisan command (`php artisan haven:retrain-model`) re-fits the classifier on approved real-world data and evaluates test accuracy against the active baseline before deploying.

### B. Multimodal Deep Learning (Audio + Vibration Late-Fusion)
* **Current State:** 14-dimensional tabular vector (MFCCs, spectral centroid, zero-crossing rate, RMS, peak acceleration, crest factor, engine temperature, RPM, voltage).
* **Proposed Enhancement:**
  - Transition from tabular Random Forest to a **Dual-Stream CNN-LSTM Network**:
    - **Stream 1 (Acoustics):** 2D Mel-Spectrogram image passing through a lightweight MobileNetV3 backbone.
    - **Stream 2 (Vibration & IMU):** 1D Temporal Convolutional Network (TCN) processing raw 3-axis accelerometer readings.
    - **Stream 3 (Telemetry Embeddings):** Dense feed-forward layers for DS18B20 temperature, Hall RPM, and INA219 voltage.
    - **Fusion Layer:** Concatenates latent embeddings for final softmax classification with higher spatial-temporal precision.

### C. Unsupervised Anomaly Detection (Isolation Forest / Autoencoder)
* **Problem:** Supervised classifiers can only identify conditions they were explicitly trained on (`normal`, `knocking`, `misfiring`, `idling_rough`). Unusual mechanical failures (e.g., blown head gasket, failing water pump bearing, intake valve leak) might get erroneously classified into an existing category.
* **Proposed Enhancement:**
  - Train an unsupervised **Deep Autoencoder** on healthy engine profiles.
  - When a test engine generates a high reconstruction error ($\text{MSE} > \theta$), the system flags:
    > *"⚠️ Unclassified Acoustic Anomaly Detected — Signature does not match known fault profiles. Manual inspection recommended."*

### D. Retrieval-Augmented Generation (RAG) AI Copilot
* **Current State:** The AI Copilot uses rule-based knowledge injection and general automotive heuristics.
* **Proposed Enhancement:**
  - Ingest PDF workshop service repair manuals (Haynes / OEM FSMs) and OBD-II Technical Service Bulletins into a vector database (ChromaDB / Qdrant).
  - When a fault code or condition is detected (e.g., Cylinder 3 Misfire on a 2018 Honda Civic), the Copilot retrieves exact torque specs, ignition coil resistance ranges, and wiring harness pinouts for that specific make and model.

---

## 4. Hardware & Edge IoT Enhancements

### A. TinyML On-Device Edge Inference (ESP32)
* **Current State:** ESP32 buffers raw IMU and temperature readings, packaging them into JSON/CSV for transmission to Laravel and FastAPI.
* **Proposed Enhancement:**
  - Quantize the acoustic or vibration classifier using **TensorFlow Lite for Microcontrollers (TFLite Micro)** or **Edge Impulse**.
  - The ESP32 can perform pre-inference on the engine bay in real time (sampling at 500 Hz), lighting up onboard status LEDs (Green = Pass, Amber = Warning, Red = Knock/Overheat) even before Wi-Fi transmission.

### B. CAN-Bus (OBD-II ECU Interfacing)
* **Current State:** External sensors only (DS18B20 temp, MPU6050 vibration, INA219 power, Hall RPM, Microphone).
* **Proposed Enhancement:**
  - Add an **MCP2515 CAN-Bus Controller + TJA1050 Transceiver** module to the ESP32.
  - Connect directly to the vehicle's OBD-II port (Pins 6 & 14 for CAN-H and CAN-L) to pull native ECU Parameters IDs:
    - `01 05` — Engine Coolant Temp (OEM sensor confirmation)
    - `01 0C` — True Crankshaft Engine RPM
    - `01 0E` — Ignition Timing Advance
    - `01 06 / 01 07` — Short-Term / Long-Term Fuel Trim (STFT/LTFT)
    - `01 02` — Active Freeze Frame DTCs

### C. Real-Time Protocol Upgrade: MQTT / WebSockets
* **Current State:** HTTP POST requests (`/api/telemetry/ingest`).
* **Proposed Enhancement:**
  - Use lightweight **MQTT over TLS** for the ESP32 to publish sensor telemetry.
  - A broker (Mosquitto) dispatches events to Laravel Reverb / Redis, providing continuous live streaming gauges on the web dashboard with sub-100ms latency.

---

## 5. Full-Stack Architecture & Cloud Scalability

### A. 1-Click Containerization (Docker Compose)
* **Current State:** Local dependencies require XAMPP, PHP CLI, Composer, Node.js, and Python venv to be launched in separate command windows.
* **Proposed Enhancement:**
  - Create a unified `docker-compose.yml` defining:
    1. `web`: PHP 8.2-FPM + Nginx
    2. `queue`: Background artisan worker (`php artisan queue:work`)
    3. `ml_service`: FastAPI server (`uvicorn main:app --port 8001`)
    4. `db`: MySQL 8.0 or PostgreSQL 15
    5. `cache`: Redis 7 (handling session storage, rate limiting, and queue jobs)

### B. Real-Time Canvas / Chart.js Oscilloscope
* **Current State:** Diagnostic views render tabular telemetry grids and static summary pills.
* **Proposed Enhancement:**
  - Implement dynamic time-series charts on `diagnostics.show` using Chart.js or Canvas:
    - Live RPM fluctuation curve during engine rev tests.
    - Temperature rise curve over 10-minute idle capture.
    - Tri-axial vibration waveform plot ($X, Y, Z$ overlay with zoom and pan).

### C. Offline Progressive Web App (PWA)
* **Problem:** Garages and automotive bays frequently suffer from poor cell service and patchy Wi-Fi.
* **Proposed Enhancement:**
  - Register a Service Worker with Web App Manifest (`manifest.json`).
  - Cache core UI pages and provide local IndexedDB storage.
  - Mechanics can record audio and input observations offline; when network connection resumes, the queue syncs with the server automatically.

---

---

## 7. Recommended APIs (External Integrations & Internal Endpoints)

To transform HAVEN from a standalone prototype into an enterprise-grade automotive telematics platform, two categories of APIs should be incorporated:

### A. External Third-Party APIs to Integrate (Data Enrichment)

| API Service | Data Provided | Academic & Practical Justification |
| :--- | :--- | :--- |
| **1. NHTSA Vehicle Safety & Recalls API** *(Free · US Dept. of Transportation)* | Official safety recalls, active defect investigations, and technical service bulletins by VIN or Make/Model/Year. | When HAVEN identifies knocking or misfiring, the system cross-references active manufacturer recalls (e.g., faulty Honda VTC actuators or Ford EcoBoost spark plug recalls) to alert the user if a free dealer repair exists. |
| **2. Environmental / Weather API** *(Open-Meteo · Free / No Key Required)* | Ambient temperature (°C), relative humidity (%), and barometric pressure (hPa). | **Acoustic and combustion dynamics are heavily dependent on ambient climate.** Sound velocity varies with air density, and high ambient temperatures trigger pre-ignition knock earlier. Normalizing acoustic features against ambient heat elevates scientific rigor. |
| **3. CarMD / RepairCost API** *(or PartsTech)* | Estimated parts cost (spark plugs, ignition coils, rod bearings) and labor repair hours. | Upgrades the diagnostic report from a technical classification into a practical repair estimate (e.g., *"Knocking detected: Estimated rod bearing replacement: $1,200 – $1,800 · 8.5 labor hours"*). |
| **4. Google Maps / Places API** | Local certified automotive repair shops, dealerships, and specialty engine mechanics. | When a high-severity fault is diagnosed, the UI provides an immediate button: *"Locate 5 Nearest ASE-Certified Mechanics"* with contact numbers and driving directions. |
| **5. Automotive LLM Provider** *(OpenAI / Anthropic / Local Ollama)* | Conversational mechanical reasoning, step-by-step diagnostic tree generation. | Powers the real-time AI Copilot, contextualizing raw telemetry readings against specific engine architectures (e.g., Boxer vs Inline-4 vs V6). |

---

### B. Internal REST & Real-Time APIs to Expose (Platform Expansion)

Expanding `routes/api.php` allows HAVEN to interface with mobile companion apps, garage fleet tablets, and third-party shop management systems:

#### 1. Real-Time Telemetry Stream (`GET /api/v1/telemetry/stream/{vehicle_id}`)
* **Protocol:** Server-Sent Events (SSE) or WebSockets.
* **Payload:** 10 Hz broadcast of live Hall RPM, DS18B20 temperature, INA219 voltage, and MPU6050 vibration RMS.
* **Benefit:** Powers live browser gauge animations while the vehicle is idling in the bay.

#### 2. Mobile Companion Fleet API (`GET & POST /api/v1/vehicles`)
* **Authentication:** Sanctum Personal Access Token (`Bearer {token}`).
* **Endpoints:**
  - `GET /api/v1/vehicles`: List garage vehicles and health status.
  - `GET /api/v1/vehicles/{id}/diagnostics`: Historical diagnostic log.
  - `POST /api/v1/vehicles`: Register a new vehicle and automatically generate an IoT `device_key`.

#### 3. Over-The-Air Hardware Provisioning (`POST /api/v1/devices/pair`)
* **Function:** Enables an ESP32 or mobile app using Bluetooth LE to scan a QR code in the Haven dashboard and pair the microcontroller to a vehicle without manually copying and pasting device keys into Arduino code.

#### 4. OBD-II Fault Code Dictionary API (`GET /api/v1/dtc/{code}`)
* **Function:** Returns technical description, severity level, affected subsystems, and step-by-step diagnostic checklists for any standard SAE/ISO DTC (e.g., `P0300`, `P0325`, `P0171`).

#### 5. Active Learning Feedback API (`POST /api/v1/diagnostics/{id}/feedback`)
* **Function:** Technicians submit verified physical inspection findings (`verified_condition: "knocking"`, `confidence: "confirmed"`, `technician_notes: "Cylinder 2 rod bearing worn"`). Automatically flags the record for the ML retraining queue.

#### 6. System Health & ISO 27001 Liveness Probe (`GET /api/v1/health`)
* **Function:** Returns status of Database, Redis Queue Worker, FastAPI Python backend, and average telemetry ingest latency (ms). Essential for monitoring and academic defense demonstration.

---

## 8. Prioritized Implementation Matrix

| Phase | Initiative | Technical Effort | Impact on Capstone Defense |
| :---: | :--- | :---: | :---: |
| **P1** | **NHTSA Recall & Specs API Integration** | Low (1-2 days) | 🌟 High (Real-world automotive relevance) |
| **P1** | **Real-Time Telemetry Stream (SSE/WebSocket)** | Medium (2 days) | 🌟 High (Live visual demonstration for panel) |
| **P1** | **HMAC-SHA256 ESP32 Signing** | Low (1-2 days) | 🌟 High (Demonstrates ISO/SAE 21434 compliance) |
| **P2** | **Open-Meteo Ambient Climate Normalization** | Low (1 day) | 🌟 High (Scientific rigor for acoustic research) |
| **P2** | **Continuous Active Learning Feedback API** | Medium (2-3 days) | 🌟 High (Demonstrates ML operationalization) |
| **P2** | **Docker Compose Containerization** | Low (1 day) | ⚡ Medium (Simplifies evaluation & deployment) |
| **P3** | **CarMD / Repair Cost Estimation API** | Medium (3-4 days) | 🚀 High (Transforms data into repair estimates) |
| **P3** | **MCP2515 CAN-Bus OBD-II Interfacing** | High (5-7 days) | 🚀 Breakthrough (Merges external DAQ with native ECU) |

---

*Document compiled and maintained under HAVEN-AI Project Management Guidelines.*

