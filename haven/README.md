# HAVEN — AI-Powered Vehicle Engine Diagnostic System
**Group 3 · CINS Capstone Project**

HAVEN is a dual-service intelligent vehicular diagnostic platform combining acoustic engine analysis (Web Audio API / microphone recording) and accelerometer vibration data (ESP32 telemetry) to predict engine anomalies such as knocking, misfiring, rough idling, and overheating.

---

## 🚀 System Architecture

HAVEN operates as a two-service decoupled system:
1. **Web Platform (`haven/`)**: Built on Laravel 12 (PHP 8.2+), Blade, and custom responsive styling featuring a modern topbar SaaS layout (V2.5 architecture). Handles vehicle management, diagnostic capture wizards, NHTSA VIN decoding, telemetry storage, and reporting.
2. **ML Backend (`ml_backend/`)**: Built on Python FastAPI, `librosa`, and `scikit-learn`. Extracts acoustic frequency spectra (MFCCs, spectral centroid, ZCR) and statistical vibration metrics (Peak G, RMS G, Crest Factor) to classify engine conditions. Includes a zero-dependency PHP simulation fallback when FastAPI is offline.

```
┌────────────────────────────────────────────────────────┐
│                   HAVEN Web UI (Laravel)               │
│  - Vehicle Management (NHTSA VIN Decoder)             │
│  - 4-Step Diagnostic Capture Wizard                   │
│  - Live Audio Waveform & ESP32 Vibration Preview      │
└────────────┬─────────────────────────────┬─────────────┘
             │ Multipart Audio + Vibration │ Fallback Rule Engine
             ▼                             ▼
┌───────────────────────────┐    ┌──────────────────────┐
│  FastAPI ML Microservice  │    │ Built-in PHP Mock    │
│  - Librosa Audio Features │    │ Simulation Mode      │
│  - Numpy Vibration Stats  │    │ (Runs without Python)│
│  - Scikit-Learn Model/Rule│    └──────────────────────┘
└───────────────────────────┘
```

---

## 📊 Development Progress & Stage Roadmap

| Stage | Milestone | Status | Key Deliverables |
|---|---|:---:|---|
| **Stage 1** | **Project Setup & Auth** | ✅ Completed | Laravel 12 setup, custom Haven dark design system, authentication suite (Login, Register, Password Reset, Profile Management). |
| **Stage 2** | **Dashboard & UX** | ✅ Completed | Live metric counters, recent vehicles card, recent diagnostics list, quick-action CTA launchpad, and dynamic topbar context. |
| **Stage 3** | **Vehicle Management** | ✅ Completed | Full Vehicle CRUD, live NHTSA vPIC API integration (cached makes & models), automatic 17-digit VIN decoding (engine, transmission, year, make, model). |
| **Stage 4** | **Diagnostic Data Capture** | ✅ Completed | 4-step wizard: vehicle selection, live mic recording with real-time `<canvas>` audio waveform visualizer, ESP32 CSV/JSON vibration parser with client-side metric preview, and review summary. |
| **Stage 5** | **ML Backend Integration** | ✅ Completed | FastAPI service (`/health`, `/info`, `/analyze`) with `librosa` audio feature extraction + `numpy` vibration processing. Laravel `MlBackendService` with seamless offline PHP fallback. Results panel with color-coded condition & confidence bars. |
| **Stage 6** | **Inspection Reports** | ✅ Completed | Standalone, print-ready, high-resolution diagnostic report (`diagnostics.report`) formatted for PDF export and client-ready printouts. |
| **Stage 7** | **Learning Modules & Knowledge Hub** | ✅ Completed | Interactive knowledge base covering engine failure modes (knocking, misfires, mounts, overheating), Web Audio API acoustic soundboard simulator, OBD-II DTC reference directory, and diagnostic mastery quiz. |
| **Stage 8** | **Async Queue & IoT Ingest** | ✅ Completed | Background queue worker (`AnalyzeDiagnosticJob`) for asynchronous file analysis, direct ESP32 Wi-Fi REST telemetry ingestion (`/api/v1/telemetry/ingest`), live status polling, and 1-click IoT hardware simulator. |

---

## 🛠️ Feature Breakdown

### 1. Vehicle Telemetry & Catalog
- **NHTSA vPIC Integration**: Decodes 17-character VINs on the fly, auto-populating vehicle specs (Displacement, Cylinders, Transmission type, Year, Make, Model).
- **Intelligent Caching**: Makes and models are cached in Laravel Cache for 12–24 hours to ensure snappy autocomplete without rate-limiting.
- **Manual Overrides**: Fallback text inputs for custom, modified, or foreign market vehicles.

### 2. Multi-Modal Diagnostic Capture
- **Live Acoustic Sampling**: Web Audio API captures audio directly from browser microphones, generating a real-time oscilloscope waveform and packaging it via `MediaRecorder` into standard WebM audio.
- **File Upload Support**: Accepts uploaded engine audio (`.wav`, `.mp3`, `.webm`, `.ogg`, `.m4a`).
- **ESP32 Vibration Ingestion**: Ingests accelerometer telemetry (`timestamp,x,y,z` or `timestamp,magnitude` from CSV, JSON, or TXT). Computes Peak Acceleration (G), Root-Mean-Square (RMS G), and Crest Factor instantly.

### 3. Machine Learning Inference & Fault Classification
- **Acoustic Pipeline**: Evaluates 13 MFCC coefficients, spectral rolloff, spectral centroid, and zero-crossing rate.
- **Mechanical Pipeline**: Analyzes impulsive vibration spikes versus steady baseline vibrations.
- **Classified Conditions**:
  - `Normal`: Balanced acoustic and vibration envelope.
  - `Knocking`: Sharp high-magnitude acceleration spikes (`peak_g > 3.5` or `crest_factor > 4.5`).
  - `Misfiring`: Rhythmic periodic energy anomalies and high ZCR.
  - `Idling Rough`: Elevated RMS vibration at low RPM without extreme impact peaks.
  - `Overheating`: High-frequency spectral shifts.
- **Dual Inference Mode**: Production-ready for a trained `model.pkl` pipeline or built-in domain rule classifier. If the Python microservice is offline, Laravel provides seamless mock inference using captured vibration statistics.

### 4. Print-Ready Diagnostic Reports
- Accessible directly from `diagnostics/{id}/report`.
- Clean, white-background printable layout optimized with `@media print` rules.
### 5. Automotive Knowledge Hub & Learning Modules (Stage 7)
- **Technical Manuals**: Comprehensive guides on engine knocking, cylinder misfires, rough idling mounts, engine thermal stress, MFCC DSP signal extraction, and ESP32 telemetry hardware setups.
- **Synthetic Acoustic Simulator**: In-browser Web Audio API oscillator reproducing healthy 4-stroke idle, violent 6.2 kHz knocking ping bursts, cyclical misfire dropouts, and low-frequency mount shudders with real-time oscilloscope visualization.
- **OBD-II DTC Reference Directory**: Searchable directory covering powertrain error codes, symptoms, and root-cause inspection workflows.
- **Self-Assessment Quiz**: Interactive diagnostic mastery exam testing mechanical and signal processing comprehension with instant explanations.

### 6. Interactive AI Mechanic Copilot (Siri / Gigi Voice Assistant)
- **Global Floating Siri/Gigi Orb Widget**: Positioned on all website pages (`<x-ai-orb />`), featuring a multi-layered 3D spherical gradient with specular highlights, rotating hue aura, breathing ripple rings, and a hover tooltip (*"Ask Haven AI"*).
- **Interactive Assistant Drawer**: Clicking the orb opens a glassmorphism assistant modal accessible anywhere on the platform (Dashboard, Diagnostics, Vehicles, Learning Hub, or Landing Page).
- **Automatic Post-Analysis Recommendations**: Instantly generates an expert assessment upon completion of any acoustic or vibration analysis, including safety status (e.g., `CRITICAL HAZARD · DO NOT DRIVE`), highway driveability advice, urgency rating, estimated repair cost ranges, and probable root causes.
- **Interactive Action Checklist**: Step-by-step diagnostic verification procedures with clickable completion states.
- **Siri / Gigi Voice Experience**:
  - Glowing animated Siri-style AI orb with real-time reactive keyframes (`siriPulse`, `siriRotate`, `siriListen`, `siriSpeak`).
  - **Speech-to-Text Voice Input**: Integrated Web Speech API (`SpeechRecognition`) enabling voice questions with hands-free mechanic mode.
  - **Text-to-Speech Voice Readout**: In-browser speech synthesis (`window.speechSynthesis`) speaking expert recommendations aloud with instant mute/unmute toggle.
  - **Context-Aware Automotive Engine**: Injects exact vehicle year/make/model, engine displacement, detected fault condition, vibration Peak G, and Crest Factor into every AI mechanic answer.
  - **Clickable Prompt Suggestions**: Quick prompt chips for common mechanic queries (*"Can I drive 10 miles to a garage?"*, *"Estimated repair cost?"*, *"What tools do I need?"*).

### 7. Multi-Device & Mobile Responsiveness Suite
- **Optimized for Smartphones & Tablets**: Tailored breakpoints for iPhone (375px/390px/430px), Android devices, iPad/tablets (768px/1024px), and high-resolution desktop monitors.
- **Touch Ergonomics & Input Sizing**: Enforces 16px font minimum on mobile inputs to eliminate iOS Safari auto-zoom, and sets minimum 44px touch targets on buttons and navigation items.
- **Mobile Drawer Navigation**: Slide-down drawer with darkened backdrop blur overlay (`.haven-mobile-backdrop`), instant tap-to-close behavior, and embedded quick theme toggle.
- **Adaptive AI Assistant**: The floating Siri orb repositions seamlessly on mobile screens (`bottom: 16px; right: 16px; 48px width`), and the assistant window opens as a responsive bottom drawer fitting 100% of the mobile viewport without horizontal overflow.
- **Responsive 4-Step Capture Wizard**: Automatically collapses step labels to clean numeric circular indicators (`01, 02, 03, 04`) with auto-scaling audio waveform oscilloscopes.
- **Card Grids & Stat Tiles**: Dynamically shifts between 4-column, 2-column, and single-column layouts based on screen width.

### 8. Async Queue Workers & ESP32 IoT Ingestion (Stage 8)
- **Background Queue Worker (`AnalyzeDiagnosticJob`)**: Decoupled, asynchronous execution of `MlBackendService` via Laravel's database queue driver. Heavy acoustic feature extraction (MFCCs, spectral centroid) and vibration calculations are processed off the main HTTP thread with automatic retries and failure handling.
- **Direct ESP32 Wi-Fi REST Telemetry Ingest (`POST /api/v1/telemetry/ingest`)**: High-throughput REST API endpoint designed for wireless microcontrollers (ESP32, Arduino, Raspberry Pi Pico W). Ingests multi-axis accelerometer readings (`timestamp,x,y,z` or magnitude arrays) and audio chunks directly over Wi-Fi without manual file uploads.
- **Dynamic Auto-Analysis Workflow**: When `auto_analyze: true` is sent in the telemetry packet, Haven automatically generates a new diagnostic session, computes Peak G and RMS G metrics, stores raw vibration data, and dispatches the analysis job to background queue workers instantly.
- **Real-Time Status Polling (`GET /diagnostics/{id}/status`)**: Diagnostic views feature live AJAX polling during `processing` state, automatically updating results the instant the queue worker completes without manual browser refreshes.

### 9. ESP32 5-Sensor Hardware DAQ Node & Automotive Pinout
HAVEN is fully configured and hardware-ready for direct integration with an ESP32 multi-sensor data acquisition node.

| Sensor Module | Connection Pin | ESP32 GPIO Pin | Protocol & Automotive Purpose |
|---|---|---|---|
| **DS18B20** | `DATA` | **D4** (GPIO 4) | Dallas 1-Wire Digital Coolant / Cylinder Head Temperature (°C) *(requires 4.7kΩ pullup to 3.3V)* |
| **Hall RPM** | `DIG` | **D23** (GPIO 23) | Digital Interrupt Hardware Pulse Counting for Crankshaft / Tachometer RPM *(requires 10kΩ pullup)* |
| **MPU6050** | `SDA / SCL` | **D21 / D22** | I2C (Address `0x68`) 3-Axis Engine Vibration Acceleration & Peak G-Force Detection |
| **INA219** | `SDA / SCL` | **D21 / D22** | I2C (Address `0x40`) DC Battery Voltage, Alternator Charging State & System Current Draw (mA) |
| **INMP441** | `SCK / WS / SD` | **D18 / D19 / D5** | I2S Digital MEMS Microphone for High-Fidelity Engine Acoustic Streaming & Detonation Detection |

- **ISO/SAE 21434 Hardware Key Authentication**: Every vehicle generates a cryptographically secure device key (`hvn_dev_...`). Microcontrollers authenticate using the `X-Haven-Device-Key` header, rejecting unauthorized packets with HTTP 401.
- **Full Arduino C++ Firmware Sketch**: Available directly from the built-in ESP32 IoT Hub modal (`diagnostics.index`) and pre-configured with all 5 sensor libraries, WiFi connection, and JSON payload serialization.
- **Multi-Sensor Heuristic Analysis**: Integrates thermal thresholds (> 105°C overheating alert), electrical charging bounds (< 12.2V / > 15.0V alternator alert), and tachometer vibration correlation directly into the ML and AI Mechanic Copilot recommendation engines.

---

## 🏃 Running the Project

### Prerequisites
- PHP 8.2+ with SQLite or MySQL (XAMPP)
- Composer
- Node.js & NPM
- *(Optional for ML service)* Python 3.10+

### 1. Web Application (Laravel)
```bash
cd haven
composer install
npm install && npm run build
php artisan migrate
php artisan serve
```
Access the application at: **http://localhost:8000**

### 2. ML Microservice (FastAPI)
```bash
cd ml_backend
pip install -r requirements.txt
python main.py
```
ML server runs at: **http://localhost:8001** (healthcheck: `http://localhost:8001/health`)
*(Note: If the Python server is not started, Haven automatically runs in simulation mode so all features remain testable.)*
