"""
HAVEN ML Backend — FastAPI
Stage 5: Engine Condition Classification

Endpoints:
  GET  /health       — liveness check
  GET  /info         — model info and feature spec
  POST /analyze      — classify engine condition from audio + vibration data

Expected model output classes (UMak CET Capstone Scope):
  normal | misfire | loose_component | valve_clearance
"""

from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import uvicorn
import tempfile
import os
import logging
from datetime import datetime, timezone
from classifier import EngineClassifier
from features import extract_audio_features, extract_vibration_features

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="HAVEN ML Backend",
    description="Engine condition classifier for HAVEN diagnostic system.",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://127.0.0.1:8000"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

classifier = EngineClassifier()


@app.get("/health")
def health():
    return {
        "status": "ok",
        "model_loaded": classifier.is_model_loaded(),
        "mode": "trained" if classifier.is_model_loaded() else "rule_based",
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/info")
def info():
    return {
        "model": classifier.model_info(),
        "classes": ["normal", "misfire", "loose_component", "valve_clearance"],
        "audio_features": [
            "rms_mean", "rms_std", "zcr_mean", "spectral_centroid_mean",
            "spectral_rolloff_mean", "mfcc_1..13_mean",
        ],
        "vibration_features": [
            "peak_g", "rms_g", "sample_count", "crest_factor",
        ],
        "hardware_features": [
            "temperature_c (DS18B20/D4)",
            "rpm (Hall/D23)",
            "voltage_v (INA219/D21-D22)",
            "current_ma (INA219/D21-D22)",
        ],
        "sample_rate_hz": 22050,
        "vibration_rate_hz": "50-200 (from ESP32)",
    }


@app.post("/analyze")
async def analyze(
    audio_file: UploadFile = File(None),
    vibration_file: UploadFile = File(None),
    diagnostic_id: int = Form(None),
    vehicle_id: int = Form(None),
    temperature_c: float = Form(None),
    rpm: int = Form(None),
    voltage_v: float = Form(None),
    current_ma: float = Form(None),
    recording_mode: str = Form("idle"),
):
    if audio_file is None and vibration_file is None and temperature_c is None and rpm is None:
        raise HTTPException(status_code=422, detail="At least one of audio_file, vibration_file, or hardware sensor data is required.")

    audio_features = {}
    vibration_features = {}
    hardware_features = {}
    errors = []

    # ── Hardware telemetry extraction ──
    if temperature_c is not None:
        hardware_features["temperature_c"] = temperature_c
    if rpm is not None:
        hardware_features["rpm"] = rpm
    else:
        hardware_features["rpm"] = 2200 if recording_mode == "revving" else 800
    if voltage_v is not None:
        hardware_features["voltage_v"] = voltage_v
    if current_ma is not None:
        hardware_features["current_ma"] = current_ma

    # ── Audio feature extraction (INMP441) ──
    if audio_file:
        suffix = os.path.splitext(audio_file.filename or "rec.webm")[1] or ".webm"
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            tmp.write(await audio_file.read())
            tmp_path = tmp.name
        try:
            audio_features = extract_audio_features(tmp_path)
            logger.info(f"Audio features extracted from {audio_file.filename}")
        except Exception as e:
            errors.append(f"Audio extraction warning: {str(e)}")
            logger.warning(f"Audio extraction failed: {e}")
        finally:
            os.unlink(tmp_path)

    # ── Vibration feature extraction (MPU6050) ──
    if vibration_file:
        suffix = os.path.splitext(vibration_file.filename or "data.csv")[1] or ".csv"
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            tmp.write(await vibration_file.read())
            tmp_path = tmp.name
        try:
            vib_raw = tmp_path
            vibration_features = extract_vibration_features(tmp_path, suffix.lstrip("."))
            logger.info(f"Vibration features extracted from {vibration_file.filename}")
        except Exception as e:
            errors.append(f"Vibration extraction warning: {str(e)}")
            logger.warning(f"Vibration extraction failed: {e}")
        finally:
            os.unlink(tmp_path)

    # ── Multi-Modal Classification ──
    all_features = {**audio_features, **vibration_features, **hardware_features}
    result = classifier.predict(all_features)

    return JSONResponse({
        "diagnostic_id": diagnostic_id,
        "vehicle_id": vehicle_id,
        "condition": result["condition"],
        "confidence": result["confidence"],
        "confidence_pct": f"{result['confidence']*100:.1f}%",
        "top_classes": result.get("top_classes", []),
        "features_used": list(all_features.keys()),
        "audio_features": audio_features,
        "vibration_features": vibration_features,
        "hardware_features": hardware_features,
        "mode": result.get("mode", "rule_based"),
        "warnings": errors,
        "processed_at": datetime.now(timezone.utc).isoformat(),
    })


if __name__ == "__main__":
    uvicorn.run("main:app", host="0.0.0.0", port=8001, reload=True)
