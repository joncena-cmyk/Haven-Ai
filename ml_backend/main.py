"""
HAVEN ML Backend — FastAPI (Phase 4)
Stage 5: Engine Condition Classification (Sound + Vibration Scope)

Endpoints:
  GET  /health       — liveness check
  GET  /info         — model info and feature spec
  POST /analyze      — classify engine condition from audio + vibration data (requires X-ML-API-Secret)
  POST /train        — retrain model (requires X-ML-API-Secret)

Expected model output classes (UMak CET Capstone Scope):
  normal | misfire | loose_component | valve_clearance
"""

from __future__ import annotations

import hmac
import logging
import math
import os
import pathlib
import tempfile
from datetime import datetime, timezone
from typing import Any, Optional

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import numpy as np
import uvicorn

from classifier import EngineClassifier
from features import extract_audio_features, extract_vibration_features
from train import CONTEXT_METADATA_COLS, FEATURE_COLS

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


def _sanitize_json_floats(obj: Any) -> Any:
    """Recursively convert non-finite floats (NaN, Inf, -Inf) to None for strict JSON compliance."""
    if isinstance(obj, float):
        return obj if math.isfinite(obj) else None
    if isinstance(obj, np.floating):
        val = float(obj)
        return val if math.isfinite(val) else None
    if isinstance(obj, np.integer):
        return int(obj)
    if isinstance(obj, dict):
        return {k: _sanitize_json_floats(v) for k, v in obj.items()}
    if isinstance(obj, (list, tuple)):
        return [_sanitize_json_floats(v) for v in obj]
    return obj


def _resolve_ml_api_secret() -> str:
    """
    Load ML_API_SECRET from environment or fall back to reading the key from
    `../haven/.env` so Laravel and FastAPI share the same secret without
    hardcoding it in source.
    """
    env_val = os.environ.get("ML_API_SECRET", "").strip()
    if env_val:
        return env_val

    haven_env = pathlib.Path(__file__).resolve().parent.parent / "haven" / ".env"
    if haven_env.exists():
        try:
            for line in haven_env.read_text(encoding="utf-8", errors="ignore").splitlines():
                stripped = line.strip()
                if stripped.startswith("ML_API_SECRET="):
                    val = stripped.split("=", 1)[1].strip().strip("\"'")
                    if val:
                        return val
        except Exception:
            pass
    return ""


def verify_ml_api_secret(
    x_ml_api_secret: Optional[str] = Header(default=None, alias="X-ML-API-Secret"),
) -> None:
    """
    Verify the shared `ML_API_SECRET` header sent by Laravel on `/analyze` and `/train`.
    """
    expected = _resolve_ml_api_secret()
    if not expected:
        raise HTTPException(
            status_code=503,
            detail="ML_API_SECRET is not configured on the ML backend.",
        )
    if not x_ml_api_secret or not hmac.compare_digest(x_ml_api_secret.encode("utf-8"), expected.encode("utf-8")):
        raise HTTPException(
            status_code=401,
            detail="Unauthorized: missing or invalid X-ML-API-Secret header.",
        )


app = FastAPI(
    title="HAVEN ML Backend",
    description="Sound + Vibration engine condition classifier for HAVEN diagnostic system.",
    version="2.0.0",
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
        "secret_configured": bool(_resolve_ml_api_secret()),
        "mode": "trained" if classifier.is_model_loaded() else "rule_based",
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/info")
def info():
    return {
        "model": classifier.model_info(),
        "classes": ["normal", "misfire", "loose_component", "valve_clearance"],
        "training_features": FEATURE_COLS,
        "context_metadata": CONTEXT_METADATA_COLS,
        "audio_features": [
            "rms_mean",
            "rms_std",
            "zcr_mean",
            "spectral_centroid_mean",
            "spectral_rolloff_mean",
            "mfcc_1..13_mean",
        ],
        "vibration_features": [
            "peak_g",
            "rms_g",
            "crest_factor",
            "dominant_freq_hz",
            "spectral_entropy",
            "sample_rate_hz",
            "window_count",
        ],
    }


@app.post("/analyze", dependencies=[Depends(verify_ml_api_secret)])
async def analyze(
    audio_file: UploadFile = File(None),
    vibration_file: UploadFile = File(None),
    diagnostic_id: int = Form(None),
    vehicle_id: int = Form(None),
    temperature_c: Optional[float] = Form(None),
    rpm: Optional[int] = Form(None),
    voltage_v: Optional[float] = Form(None),
    current_ma: Optional[float] = Form(None),
    sample_rate_hz: Optional[float] = Form(None),
    recording_mode: str = Form("idle"),
    vibration_source: Optional[str] = Form(None),
):
    if audio_file is None and vibration_file is None:
        raise HTTPException(
            status_code=422,
            detail="At least one of audio_file or vibration_file is required for sound + vibration analysis.",
        )

    audio_features = {}
    vibration_features = {}
    context_metadata = {
        "recording_mode": recording_mode,
        "vibration_source": vibration_source,
        # Never invent RPM when omitted; keep None if sensor reading is absent
        "temperature_c": temperature_c,
        "rpm": rpm,
        "voltage_v": voltage_v,
        "current_ma": current_ma,
    }
    errors = []

    # ── Audio feature extraction (Band-pass filtered, 1.5s windows with 50% overlap) ──
    audio_windows = []
    if audio_file:
        suffix = os.path.splitext(audio_file.filename or "rec.wav")[1] or ".wav"
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            tmp.write(await audio_file.read())
            tmp_path = tmp.name
        try:
            audio_features = extract_audio_features(tmp_path, include_windows=True)
            audio_windows = audio_features.pop("window_metrics", [])
            logger.info(f"Audio features extracted from {audio_file.filename}")
        except Exception as e:
            errors.append(f"Audio extraction error: {str(e)}")
            logger.warning(f"Audio extraction failed: {e}")
        finally:
            if os.path.exists(tmp_path):
                os.unlink(tmp_path)

    # ── Vibration feature extraction (DC-offset removed, real sample rate from file, windowed) ──
    vibration_windows = []
    if vibration_file:
        suffix = os.path.splitext(vibration_file.filename or "data.csv")[1] or ".csv"
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            tmp.write(await vibration_file.read())
            tmp_path = tmp.name
        try:
            vibration_features = extract_vibration_features(
                tmp_path,
                ext=suffix.lstrip("."),
                sample_rate_hz=sample_rate_hz,
                rpm=rpm,
                include_windows=True,
            )
            vibration_windows = vibration_features.pop("window_metrics", [])
            logger.info(f"Vibration features extracted from {vibration_file.filename}")
        except Exception as e:
            errors.append(f"Vibration extraction error: {str(e)}")
            logger.warning(f"Vibration extraction failed: {e}")
        finally:
            if os.path.exists(tmp_path):
                os.unlink(tmp_path)

    if not audio_features and not vibration_features:
        raise HTTPException(
            status_code=422,
            detail="Signal feature extraction failed for all provided files: " + "; ".join(errors),
        )

    # Build aligned per-window feature dictionaries for window-ensemble probability voting
    window_features_list = []
    if audio_windows and vibration_windows:
        n_wins = max(len(audio_windows), len(vibration_windows))
        for i in range(n_wins):
            aw = audio_windows[min(i, len(audio_windows) - 1)]
            vw = vibration_windows[min(i, len(vibration_windows) - 1)]
            window_features_list.append({**aw, **vw})
    elif audio_windows:
        window_features_list = list(audio_windows)
    elif vibration_windows:
        window_features_list = list(vibration_windows)

    # Combine extracted sound + vibration features with context metadata for gating/XAI
    all_features = {
        **audio_features,
        **vibration_features,
        **context_metadata,
        "_window_features": window_features_list,
    }
    result = classifier.predict(all_features)

    features_used = [
        k
        for k in FEATURE_COLS
        if k in all_features
        and all_features[k] is not None
        and not (isinstance(all_features[k], (float, np.floating)) and not math.isfinite(float(all_features[k])))
    ]

    return JSONResponse(
        _sanitize_json_floats(
            {
                "diagnostic_id": diagnostic_id,
                "vehicle_id": vehicle_id,
                "condition": result["condition"],
                "confidence": result["confidence"],
                "confidence_pct": f"{result['confidence'] * 100:.1f}%",
                "top_classes": result.get("top_classes", []),
                "feature_contributions": result.get("feature_contributions", []),
                "features_used": features_used,
                "audio_features": audio_features,
                "vibration_features": vibration_features,
                "hardware_features": context_metadata,
                "context_metadata": context_metadata,
                "mode": result.get("mode", "rule_based"),
                "warnings": errors + result.get("warnings", []),
                "processed_at": datetime.now(timezone.utc).isoformat(),
            }
        )
    )


@app.post("/train", dependencies=[Depends(verify_ml_api_secret)])
async def retrain_model(payload: dict = None):
    """
    Retrain the Random Forest model using verified ground-truth records (`true_condition`)
    and/or calibrated synthetic augmentation.
    """
    try:
        from train import load_hybrid_dataset, train_and_export

        payload = payload or {}
        records = payload.get("records", [])
        augment_synth = payload.get("augment_synthetic", True)
        synth_per_class = int(payload.get("synthetic_per_class", 100))

        X, y, real_c, synth_c = load_hybrid_dataset(
            records,
            augment_synthetic=augment_synth,
            synthetic_per_class=synth_per_class,
        )
        groups = None
        if records and real_c > 0:
            real_groups = []
            from train import CLASSES, build_feature_vector
            for idx, r in enumerate(records):
                cond = str(r.get("true_condition") or r.get("condition") or r.get("label") or "").lower().strip()
                feat_dict = r.get("features") if isinstance(r.get("features"), dict) else r
                if cond in CLASSES:
                    try:
                        build_feature_vector(feat_dict)
                        grp = str(r.get("vehicle_id") or r.get("session_id") or f"real_{idx}")
                        real_groups.append(grp)
                    except ValueError:
                        continue
            if len(set(real_groups)) >= 2:
                synth_groups = [f"synth_grp_{i % 5}" for i in range(synth_c)]
                groups = np.asarray(real_groups + synth_groups, dtype=object)

        meta = train_and_export(X, y, real_count=real_c, synth_count=synth_c, groups=groups)
        classifier.reload_model()

        return JSONResponse(
            _sanitize_json_floats(
                {
                    "success": True,
                    "message": "Model retrained and reloaded successfully (14 Sound + Vibration features).",
                    "meta": meta,
                }
            )
        )
    except Exception as e:
        logger.error(f"Retraining failed: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Retraining failed: {str(e)}")


if __name__ == "__main__":
    uvicorn.run("main:app", host="0.0.0.0", port=8001, reload=True)
