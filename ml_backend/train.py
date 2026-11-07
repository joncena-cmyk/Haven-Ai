"""
HAVEN ML Backend — Model Training & Grouped Evaluation Pipeline (Phase 4)
=========================================================================
Trains and evaluates the HAVEN engine fault classifier strictly on
thesis-scoped Sound + Vibration features (no contextual temperature, RPM,
voltage, or current in the model's feature vector):

  - Mechanical vibration features (per-window DC-removed Peak G, RMS G,
    Crest Factor, FFT Dominant Frequency, Spectral Entropy)
  - Acoustic DSP features (per-window RMS, ZCR, Spectral Centroid,
    Spectral Rolloff, MFCC 1..5)

Supports two modes:
  1. Real-Data Grouped Evaluation (`--recordings-dir <path>`):
     Loads validated offline recording folders (`recordings/<session>/<vehicle>/<condition>/<ts>/`),
     segments into 1.5 s windows (50% overlap), enforces >= 2 vehicles and all 4 classes,
     evaluates with `GroupKFold` grouped by vehicle/session (never random window splits),
     and writes accuracy, per-class precision/recall/F1, and confusion matrix to a report file.
  2. Hybrid / Synthetic Calibration (`--generate-synthetic` or `/train` endpoint).
"""

from __future__ import annotations

import argparse
import json
import logging
import math
import os
import pathlib
import pickle
import sys
from collections import Counter
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple

import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.model_selection import (
    GroupKFold,
    GroupShuffleSplit,
    KFold,
    StratifiedKFold,
    cross_val_score,
    train_test_split,
)
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import StandardScaler

from features import extract_audio_windows, extract_vibration_windows

# Allow importing validate_recording from ../recorder
_REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
if str(_REPO_ROOT / "recorder") not in sys.path:
    sys.path.insert(0, str(_REPO_ROOT / "recorder"))

try:
    from validate_recording import validate_recording_folder
except ImportError:  # pragma: no cover
    validate_recording_folder = None  # type: ignore

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

CLASSES: List[str] = ["normal", "misfire", "loose_component", "valve_clearance"]
MODEL_PATH: str = os.path.join(os.path.dirname(__file__), "model.pkl")
DEFAULT_REPORT_PATH: str = os.path.join(os.path.dirname(__file__), "evaluation_report.json")

# Strictly Sound + Vibration features (UMak CET Capstone Scope).
# Contextual sensors (temperature_c, rpm, voltage_v, current_ma) are excluded from model training.
VIBRATION_FEATURE_COLS: List[str] = [
    "peak_g",
    "rms_g",
    "crest_factor",
    "dominant_freq_hz",
    "spectral_entropy",
]

AUDIO_FEATURE_COLS: List[str] = [
    "rms_mean",
    "zcr_mean",
    "spectral_centroid_mean",
    "spectral_rolloff_mean",
    "mfcc_1_mean",
    "mfcc_2_mean",
    "mfcc_3_mean",
    "mfcc_4_mean",
    "mfcc_5_mean",
]

FEATURE_COLS: List[str] = VIBRATION_FEATURE_COLS + AUDIO_FEATURE_COLS

CONTEXT_METADATA_COLS: List[str] = ["temperature_c", "rpm", "voltage_v", "current_ma"]


def _to_float_or_nan(val: Any) -> float:
    """Convert a value to float, returning np.nan if None, empty, or non-finite."""
    if val is None or val == "":
        return float("nan")
    try:
        f = float(val)
        return f if math.isfinite(f) else float("nan")
    except (ValueError, TypeError):
        return float("nan")


def build_feature_vector(row: Dict[str, Any]) -> List[float]:
    """
    Extract a 14-element sound + vibration feature vector matching FEATURE_COLS.
    Never invents constants or fills missing values with 0.0; missing values are
    represented as `np.nan` for downstream `SimpleImputer` handling.
    Raises ValueError if all 14 sound + vibration features are missing (NaN).
    """
    peak_g = _to_float_or_nan(row.get("peak_g", row.get("vibration_peak_g")))
    rms_g = _to_float_or_nan(row.get("rms_g", row.get("vibration_rms_g")))
    crest = _to_float_or_nan(row.get("crest_factor"))
    if math.isnan(crest) and not math.isnan(peak_g) and not math.isnan(rms_g) and rms_g > 1e-12:
        crest = peak_g / rms_g

    dom_hz = _to_float_or_nan(row.get("dominant_freq_hz"))
    entropy = _to_float_or_nan(row.get("spectral_entropy"))
    rms_audio = _to_float_or_nan(row.get("rms_mean"))
    zcr = _to_float_or_nan(row.get("zcr_mean"))
    centroid = _to_float_or_nan(row.get("spectral_centroid_mean"))
    rolloff = _to_float_or_nan(row.get("spectral_rolloff_mean"))
    mfcc1 = _to_float_or_nan(row.get("mfcc_1_mean"))
    mfcc2 = _to_float_or_nan(row.get("mfcc_2_mean"))
    mfcc3 = _to_float_or_nan(row.get("mfcc_3_mean"))
    mfcc4 = _to_float_or_nan(row.get("mfcc_4_mean"))
    mfcc5 = _to_float_or_nan(row.get("mfcc_5_mean"))

    vec = [
        peak_g,
        rms_g,
        crest,
        dom_hz,
        entropy,
        rms_audio,
        zcr,
        centroid,
        rolloff,
        mfcc1,
        mfcc2,
        mfcc3,
        mfcc4,
        mfcc5,
    ]

    if all(math.isnan(v) for v in vec):
        raise ValueError(
            "Cannot build feature vector: all sound and vibration features are missing (NaN)."
        )
    return vec


def build_pipeline(random_state: int = 42) -> Pipeline:
    """Build the NaN-aware SimpleImputer + StandardScaler + RandomForest pipeline."""
    return Pipeline(
        [
            ("imputer", SimpleImputer(strategy="median")),
            ("scaler", StandardScaler()),
            (
                "rf",
                RandomForestClassifier(
                    n_estimators=120,
                    max_depth=12,
                    min_samples_split=3,
                    class_weight="balanced",
                    random_state=random_state,
                    n_jobs=-1,
                ),
            ),
        ]
    )


# ─────────────────────────────────────────────────────────────────────────────
# Real Recording Folder Loader & Grouped Evaluation (GroupKFold)
# ─────────────────────────────────────────────────────────────────────────────

def load_recordings_dataset(
    recordings_root: str | pathlib.Path,
    window_sec: float = 1.5,
    overlap: float = 0.5,
    validate_first: bool = True,
) -> Tuple[np.ndarray, np.ndarray, np.ndarray, np.ndarray, List[Dict[str, Any]]]:
    """
    Scan `recordings_root` for recording folders containing `metadata.json`,
    `vibration.csv`, and `audio.wav`. Validate each folder, segment audio and
    vibration into overlapping 1.5 s windows (50% overlap), and return:
        `(X, y, groups, vehicles, window_metadata)`
    where:
        - `X` has shape `(n_windows, 14)` matching `FEATURE_COLS`
        - `y` has shape `(n_windows,)` with ground-truth `condition`
        - `groups` has shape `(n_windows,)` with `f"{vehicle_id}::{session_id}"`
        - `vehicles` has shape `(n_windows,)` with `vehicle_id`
    """
    root_path = pathlib.Path(recordings_root).resolve()
    if not root_path.exists():
        raise FileNotFoundError(f"Recordings directory does not exist: {root_path}")

    meta_files = sorted(root_path.rglob("metadata.json"))
    if not meta_files:
        raise ValueError(f"No recording folders (metadata.json) found under {root_path}")

    X_rows: List[List[float]] = []
    y_rows: List[str] = []
    group_rows: List[str] = []
    vehicle_rows: List[str] = []
    window_meta: List[Dict[str, Any]] = []

    for meta_file in meta_files:
        rec_dir = meta_file.parent
        if validate_first and validate_recording_folder is not None:
            vres = validate_recording_folder(rec_dir)
            if not vres.valid:
                raise ValueError(
                    f"Recording folder failed validation ({rec_dir}): " + "; ".join(vres.errors)
                )

        with meta_file.open("r", encoding="utf-8") as f:
            meta = json.load(f)

        condition = str(meta.get("condition") or "").strip().lower()
        if condition not in CLASSES:
            raise ValueError(
                f"Invalid condition '{condition}' in {meta_file}; must be one of {CLASSES}"
            )

        vehicle_id = str(meta.get("vehicle_id") or "").strip()
        session_id = str(meta.get("session_id") or "").strip()
        if not vehicle_id or not session_id:
            raise ValueError(f"Missing vehicle_id or session_id in {meta_file}")

        vib_path = rec_dir / "vibration.csv"
        audio_path = rec_dir / "audio.wav"

        vib_wins, vib_sr, _ = extract_vibration_windows(
            str(vib_path),
            ext="csv",
            window_sec=window_sec,
            overlap=overlap,
        )
        aud_wins, aud_sr, _ = extract_audio_windows(
            str(audio_path),
            window_sec=window_sec,
            overlap=overlap,
        )

        n_win = min(len(vib_wins), len(aud_wins))
        if n_win == 0:
            raise ValueError(f"No valid windows extracted from {rec_dir}")

        group_key = f"{vehicle_id}::{session_id}"
        for w_idx in range(n_win):
            merged = {**aud_wins[w_idx], **vib_wins[w_idx]}
            vec = build_feature_vector(merged)
            X_rows.append(vec)
            y_rows.append(condition)
            group_rows.append(group_key)
            vehicle_rows.append(vehicle_id)
            window_meta.append(
                {
                    "folder": str(rec_dir),
                    "window_index": w_idx,
                    "vehicle_id": vehicle_id,
                    "session_id": session_id,
                    "condition": condition,
                    "vibration_sr_hz": vib_sr,
                    "audio_sr_hz": aud_sr,
                    # Contextual metadata kept separate from training features
                    "rpm_target": meta.get("rpm_target"),
                    "load_state": meta.get("load_state"),
                    "sensor_position": meta.get("sensor_position"),
                    "mic_position": meta.get("mic_position"),
                }
            )

    return (
        np.asarray(X_rows, dtype=np.float64),
        np.asarray(y_rows, dtype=object),
        np.asarray(group_rows, dtype=object),
        np.asarray(vehicle_rows, dtype=object),
        window_meta,
    )


def evaluate_and_train_grouped(
    X: np.ndarray,
    y: np.ndarray,
    groups: np.ndarray,
    vehicles: np.ndarray,
    *,
    report_path: Optional[str | pathlib.Path] = DEFAULT_REPORT_PATH,
    model_path: Optional[str | pathlib.Path] = MODEL_PATH,
    group_by: str = "vehicle",
) -> Dict[str, Any]:
    """
    Evaluate model generalization using `GroupKFold` grouped by vehicle or
    vehicle/session (never random window splits!), train the final pipeline on
    all windows, and write the evaluation report and model artifact.

    Fails loudly (raises ValueError) if:
      - fewer than 2 distinct vehicles are present, OR
      - fewer than 4 distinct classes (all 4 canonical fault classes) are present.
    """
    unique_vehicles = sorted(set(str(v) for v in vehicles if str(v).strip()))
    unique_classes = sorted(set(str(c) for c in y if str(c).strip()))

    if len(unique_vehicles) < 2:
        raise ValueError(
            f"Grouped evaluation requires at least 2 distinct vehicles; "
            f"found {len(unique_vehicles)} ({unique_vehicles})."
        )

    missing_classes = [c for c in CLASSES if c not in unique_classes]
    if len(unique_classes) < 4 or missing_classes:
        raise ValueError(
            f"Grouped evaluation requires all 4 canonical engine classes {CLASSES}; "
            f"found {len(unique_classes)} ({unique_classes}), missing {missing_classes}."
        )

    eval_groups = vehicles if group_by == "vehicle" else groups
    unique_eval_groups = sorted(set(str(g) for g in eval_groups))
    n_splits = min(5, len(unique_eval_groups))
    if n_splits < 2:
        raise ValueError(
            f"GroupKFold requires at least 2 distinct groups; found {len(unique_eval_groups)}."
        )

    gkf = GroupKFold(n_splits=n_splits)
    oof_preds = np.empty_like(y, dtype=object)
    fold_accuracies: List[float] = []
    fold_details: List[Dict[str, Any]] = []

    for fold_idx, (train_idx, test_idx) in enumerate(gkf.split(X, y, groups=eval_groups), start=1):
        # Verify zero group leakage between train and test folds
        train_grp_set = set(eval_groups[train_idx])
        test_grp_set = set(eval_groups[test_idx])
        assert train_grp_set.isdisjoint(test_grp_set), "Group leakage detected between train and test folds!"

        fold_pipe = build_pipeline(random_state=42 + fold_idx)
        fold_pipe.fit(X[train_idx], y[train_idx])
        preds = fold_pipe.predict(X[test_idx])
        oof_preds[test_idx] = preds

        fold_acc = float(np.mean(preds == y[test_idx]))
        fold_accuracies.append(fold_acc)
        fold_details.append(
            {
                "fold": fold_idx,
                "n_train_windows": int(len(train_idx)),
                "n_test_windows": int(len(test_idx)),
                "test_groups": sorted(str(g) for g in test_grp_set),
                "accuracy": round(fold_acc, 4),
            }
        )

    overall_acc = float(np.mean(oof_preds == y))
    mean_fold_acc = float(np.mean(fold_accuracies))
    std_fold_acc = float(np.std(fold_accuracies))

    report_dict = classification_report(
        y, oof_preds, labels=CLASSES, target_names=CLASSES, output_dict=True, zero_division=0
    )
    cm = confusion_matrix(y, oof_preds, labels=CLASSES).tolist()

    per_class_metrics: Dict[str, Dict[str, Any]] = {}
    for c in CLASSES:
        c_rep = report_dict.get(c, {})
        per_class_metrics[c] = {
            "precision": round(float(c_rep.get("precision", 0.0)), 4),
            "recall": round(float(c_rep.get("recall", 0.0)), 4),
            "f1_score": round(float(c_rep.get("f1-score", 0.0)), 4),
            "support": int(c_rep.get("support", 0)),
        }

    # Fit final pipeline on all real windows (14-feature multimodal + audio-only + vibration-only sub-pipelines)
    final_pipeline = build_pipeline(random_state=42)
    final_pipeline.fit(X, y)

    audio_idx = [FEATURE_COLS.index(c) for c in AUDIO_FEATURE_COLS]
    X_audio = X[:, audio_idx]
    valid_aud_mask = ~np.all(np.isnan(X_audio), axis=1)
    audio_pipeline = None
    if int(np.count_nonzero(valid_aud_mask)) >= 4 and len(set(y[valid_aud_mask])) >= 2:
        audio_pipeline = build_pipeline(random_state=42)
        audio_pipeline.fit(X_audio[valid_aud_mask], y[valid_aud_mask])

    vib_idx = [FEATURE_COLS.index(c) for c in VIBRATION_FEATURE_COLS]
    X_vib = X[:, vib_idx]
    valid_vib_mask = ~np.all(np.isnan(X_vib), axis=1)
    vibration_pipeline = None
    if int(np.count_nonzero(valid_vib_mask)) >= 4 and len(set(y[valid_vib_mask])) >= 2:
        vibration_pipeline = build_pipeline(random_state=42)
        vibration_pipeline.fit(X_vib[valid_vib_mask], y[valid_vib_mask])

    rf_model = final_pipeline.named_steps["rf"]
    raw_importances = rf_model.feature_importances_
    feature_importances = [
        {
            "feature": f,
            "importance": round(float(imp), 4),
            "importance_pct": round(float(imp) * 100.0, 2),
        }
        for f, imp in sorted(zip(FEATURE_COLS, raw_importances), key=lambda x: x[1], reverse=True)
    ]

    meta: Dict[str, Any] = {
        "evaluation_scheme": f"GroupKFold(n_splits={n_splits}, group_by={group_by})",
        "feature_order": FEATURE_COLS,
        "audio_feature_order": AUDIO_FEATURE_COLS,
        "vibration_feature_order": VIBRATION_FEATURE_COLS,
        "context_metadata_excluded": CONTEXT_METADATA_COLS,
        "classes": CLASSES,
        "vehicles": unique_vehicles,
        "n_vehicles": len(unique_vehicles),
        "n_groups": len(unique_eval_groups),
        "n_samples": int(len(X)),
        "real_samples_count": int(len(X)),
        "synthetic_samples_count": 0,
        "accuracy": round(overall_acc, 4),
        "cv_accuracy": round(mean_fold_acc, 4),
        "cv_std": round(std_fold_acc, 4),
        "f1_macro": round(float(report_dict.get("macro avg", {}).get("f1-score", 0.0)), 4),
        "f1_weighted": round(float(report_dict.get("weighted avg", {}).get("f1-score", 0.0)), 4),
        "per_class_metrics": per_class_metrics,
        "confusion_matrix": cm,
        "folds": fold_details,
        "feature_importances": feature_importances,
        "trained_on": datetime.now(timezone.utc).isoformat(),
        "algorithm": "Pipeline(SimpleImputer, StandardScaler, RandomForestClassifier)",
    }

    if report_path is not None:
        r_path = pathlib.Path(report_path)
        r_path.parent.mkdir(parents=True, exist_ok=True)
        with r_path.open("w", encoding="utf-8") as rf:
            json.dump(meta, rf, indent=2)

    if model_path is not None:
        m_path = pathlib.Path(model_path)
        m_path.parent.mkdir(parents=True, exist_ok=True)
        with m_path.open("wb") as mf:
            pickle.dump(
                {
                    "model": final_pipeline,
                    "audio_model": audio_pipeline,
                    "vibration_model": vibration_pipeline,
                    "meta": meta,
                },
                mf,
            )
        meta_json_path = m_path.parent / "model_meta.json"
        with meta_json_path.open("w", encoding="utf-8") as mjf:
            json.dump(meta, mjf, indent=2)

    return meta


# ─────────────────────────────────────────────────────────────────────────────
# Calibrated Synthetic & Hybrid Support (Used when bootstrapping or via Admin UI)
# ─────────────────────────────────────────────────────────────────────────────

def generate_synthetic_samples(n_per_class: int = 150) -> Tuple[np.ndarray, np.ndarray]:
    """
    Generate gravity-removed (AC dynamic g) 14-feature sound + vibration vectors
    for the 4 engine classes, incorporating realistic sensor variance and
    borderline operating-regime overlap (~5-7% boundary ambiguity).
    """
    rng = np.random.default_rng(42)
    X_list: List[List[float]] = []
    y_list: List[str] = []

    for condition in CLASSES:
        for i in range(n_per_class):
            is_borderline = (i % 14 == 0)  # ~7% borderline cross-class transition regime
            if condition == "valve_clearance":
                peak_g = float(rng.uniform(1.35, 3.60))
                rms_g = float(rng.uniform(0.22, 0.55))
                dom_hz = float(rng.uniform(17.0, 35.0))
                entropy = float(rng.uniform(0.30, 0.54))
                rms_audio = float(rng.uniform(0.08, 0.22))
                zcr = float(rng.uniform(0.10, 0.20))
                centroid = float(rng.uniform(2650, 5100))
                rolloff = float(rng.uniform(4400, 7700))
                mfcc1 = float(rng.uniform(-165, -110))
                if is_borderline:
                    # Mild valve tap overlapping with misfire / high-RPM normal
                    peak_g = float(rng.uniform(0.70, 1.30))
                    rms_g = float(rng.uniform(0.25, 0.48))
                    dom_hz = float(rng.uniform(11.0, 18.0))
                    entropy = float(rng.uniform(0.52, 0.68))
                    rms_audio = float(rng.uniform(0.07, 0.13))
                    zcr = float(rng.uniform(0.18, 0.27))
                    centroid = float(rng.uniform(2250, 3100))
                    rolloff = float(rng.uniform(3900, 5100))
                    mfcc1 = float(rng.uniform(-210, -165))
            elif condition == "loose_component":
                peak_g = float(rng.uniform(0.60, 1.70))
                rms_g = float(rng.uniform(0.30, 0.75))
                dom_hz = float(rng.uniform(24.0, 56.0))
                entropy = float(rng.uniform(0.20, 0.44))
                rms_audio = float(rng.uniform(0.05, 0.12))
                zcr = float(rng.uniform(0.06, 0.13))
                centroid = float(rng.uniform(800, 1750))
                rolloff = float(rng.uniform(1700, 3000))
                mfcc1 = float(rng.uniform(-210, -145))
                if is_borderline:
                    # Borderline mount wear overlapping with revving normal
                    peak_g = float(rng.uniform(0.36, 0.68))
                    rms_g = float(rng.uniform(0.14, 0.25))
                    dom_hz = float(rng.uniform(31.0, 41.0))
                    entropy = float(rng.uniform(0.22, 0.36))
                    rms_audio = float(rng.uniform(0.045, 0.075))
                    zcr = float(rng.uniform(0.055, 0.095))
                    centroid = float(rng.uniform(1450, 1950))
                    rolloff = float(rng.uniform(2600, 3600))
                    mfcc1 = float(rng.uniform(-170, -135))
            elif condition == "misfire":
                peak_g = float(rng.uniform(0.65, 1.85))
                rms_g = float(rng.uniform(0.22, 0.58))
                dom_hz = float(rng.uniform(6.0, 16.0))
                entropy = float(rng.uniform(0.50, 0.78))
                rms_audio = float(rng.uniform(0.06, 0.15))
                zcr = float(rng.uniform(0.17, 0.34))
                centroid = float(rng.uniform(2100, 3600))
                rolloff = float(rng.uniform(3700, 5600))
                mfcc1 = float(rng.uniform(-240, -165))
                if is_borderline:
                    # Intermittent single-cylinder stumble overlapping with loose_component / valve_clearance
                    peak_g = float(rng.uniform(1.40, 2.30))
                    rms_g = float(rng.uniform(0.26, 0.45))
                    dom_hz = float(rng.uniform(18.0, 28.0))
                    entropy = float(rng.uniform(0.34, 0.50))
                    rms_audio = float(rng.uniform(0.09, 0.16))
                    zcr = float(rng.uniform(0.11, 0.18))
                    centroid = float(rng.uniform(2700, 3900))
                    rolloff = float(rng.uniform(4500, 6200))
                    mfcc1 = float(rng.uniform(-160, -120))
            else:
                is_revving = (i % 2 == 1)
                if is_revving:
                    peak_g = float(rng.uniform(0.32, 0.78))
                    rms_g = float(rng.uniform(0.11, 0.26))
                    dom_hz = float(rng.uniform(30.0, 44.0))
                    entropy = float(rng.uniform(0.20, 0.38))
                    rms_audio = float(rng.uniform(0.04, 0.08))
                    zcr = float(rng.uniform(0.05, 0.10))
                    centroid = float(rng.uniform(1350, 2100))
                    rolloff = float(rng.uniform(2500, 3800))
                    mfcc1 = float(rng.uniform(-175, -130))
                else:
                    peak_g = float(rng.uniform(0.09, 0.38))
                    rms_g = float(rng.uniform(0.04, 0.15))
                    dom_hz = float(rng.uniform(11.5, 16.5))
                    entropy = float(rng.uniform(0.18, 0.35))
                    rms_audio = float(rng.uniform(0.02, 0.055))
                    zcr = float(rng.uniform(0.04, 0.08))
                    centroid = float(rng.uniform(1000, 1650))
                    rolloff = float(rng.uniform(1900, 3050))
                    mfcc1 = float(rng.uniform(-195, -145))
                if is_borderline:
                    # Rough cold-start normal overlapping with loose_component
                    peak_g = float(rng.uniform(0.68, 1.35))
                    rms_g = float(rng.uniform(0.34, 0.58))
                    dom_hz = float(rng.uniform(26.0, 46.0))
                    entropy = float(rng.uniform(0.24, 0.40))
                    rms_audio = float(rng.uniform(0.06, 0.10))
                    zcr = float(rng.uniform(0.07, 0.12))
                    centroid = float(rng.uniform(950, 1550))
                    rolloff = float(rng.uniform(1850, 2750))
                    mfcc1 = float(rng.uniform(-195, -155))

            crest_factor = round(peak_g / rms_g, 3) if rms_g > 0 else 1.0
            mfcc2 = float(rng.uniform(10, 45))
            mfcc3 = float(rng.uniform(-25, 15))
            mfcc4 = float(rng.uniform(-15, 20))
            mfcc5 = float(rng.uniform(-20, 10))

            X_list.append(
                [
                    peak_g,
                    rms_g,
                    crest_factor,
                    dom_hz,
                    entropy,
                    rms_audio,
                    zcr,
                    centroid,
                    rolloff,
                    mfcc1,
                    mfcc2,
                    mfcc3,
                    mfcc4,
                    mfcc5,
                ]
            )
            y_list.append(condition)

    return np.asarray(X_list, dtype=np.float64), np.asarray(y_list, dtype=object)


def load_hybrid_dataset(
    real_records: Optional[List[Dict[str, Any]]] = None,
    augment_synthetic: bool = True,
    synthetic_per_class: int = 100,
) -> Tuple[np.ndarray, np.ndarray, int, int]:
    """
    Combine real labeled diagnostic records (`true_condition` / `condition`) with
    optional synthetic augmentation using the 14 sound + vibration features.
    Never uses `ml_condition` as a training label.
    """
    X_real: List[List[float]] = []
    y_real: List[str] = []
    counts = {c: 0 for c in CLASSES}

    if real_records:
        for r in real_records:
            cond = str(r.get("true_condition") or r.get("condition") or r.get("label") or "").lower().strip()
            feat_dict = r.get("features") if isinstance(r.get("features"), dict) else r
            if cond in CLASSES:
                try:
                    X_real.append(build_feature_vector(feat_dict))
                    y_real.append(cond)
                    counts[cond] += 1
                except ValueError:
                    continue

    real_count = len(X_real)
    logger.info(f"Loaded {real_count} real vehicle samples across classes: {counts}")

    if augment_synthetic or real_count == 0:
        max_needed = max((max(0, synthetic_per_class - counts[c]) for c in CLASSES), default=0)
        X_synth_list = []
        y_synth_list = []
        if max_needed > 0:
            X_pool, y_pool = generate_synthetic_samples(n_per_class=max_needed)
            for cond in CLASSES:
                needed = max(0, synthetic_per_class - counts[cond])
                if needed > 0:
                    mask = (y_pool == cond)
                    X_synth_list.append(X_pool[mask][:needed])
                    y_synth_list.append(y_pool[mask][:needed])

        if X_synth_list:
            X_synth = np.vstack(X_synth_list)
            y_synth = np.concatenate(y_synth_list)
            if real_count > 0:
                X = np.vstack([np.asarray(X_real, dtype=np.float64), X_synth])
                y = np.concatenate([np.asarray(y_real, dtype=object), y_synth])
            else:
                X = X_synth
                y = y_synth
            synth_count = len(X_synth)
        else:
            X = np.asarray(X_real, dtype=np.float64)
            y = np.asarray(y_real, dtype=object)
            synth_count = 0
    else:
        X = np.asarray(X_real, dtype=np.float64)
        y = np.asarray(y_real, dtype=object)
        synth_count = 0

    return X, y, real_count, synth_count


def train_and_export(
    X: np.ndarray,
    y: np.ndarray,
    real_count: int = 0,
    synth_count: int = 0,
    model_path: str = MODEL_PATH,
    groups: Optional[np.ndarray] = None,
) -> Dict[str, Any]:
    """
    Train the 14-feature Sound + Vibration pipeline and export `model.pkl`.
    Uses GroupKFold & GroupShuffleSplit when distinct vehicle/session groups are
    provided so overlapping windows from the same vehicle never leak across splits.
    """
    if len(X) < 4 or len(set(str(lbl) for lbl in y)) < 2:
        raise ValueError(
            f"At least 4 samples across >= 2 classes are required for training (got {len(X)} samples)."
        )

    unique_groups = sorted(set(str(g) for g in groups if str(g).strip())) if groups is not None else []
    use_grouped = groups is not None and len(unique_groups) >= 2 and len(groups) == len(X)

    if use_grouped:
        gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=42)
        train_idx, test_idx = next(gss.split(X, y, groups=groups))
        X_train, X_test = X[train_idx], X[test_idx]
        y_train, y_test = y[train_idx], y[test_idx]
    else:
        class_counts = Counter(str(lbl) for lbl in y)
        min_cls = min(class_counts.values())
        n_test = max(1, int(round(len(y) * 0.2)))
        can_stratify = min_cls >= 2 and n_test >= len(class_counts) and (len(y) - n_test) >= len(class_counts)
        X_train, X_test, y_train, y_test = train_test_split(
            X,
            y,
            test_size=0.2,
            random_state=42,
            stratify=y if can_stratify else None,
        )

    pipeline = build_pipeline(random_state=42)
    pipeline.fit(X_train, y_train)

    audio_idx = [FEATURE_COLS.index(c) for c in AUDIO_FEATURE_COLS]
    X_audio = X[:, audio_idx]
    valid_aud_mask = ~np.all(np.isnan(X_audio), axis=1)
    audio_pipeline = None
    if int(np.count_nonzero(valid_aud_mask)) >= 4 and len(set(y[valid_aud_mask])) >= 2:
        audio_pipeline = build_pipeline(random_state=42)
        audio_pipeline.fit(X_audio[valid_aud_mask], y[valid_aud_mask])

    vib_idx = [FEATURE_COLS.index(c) for c in VIBRATION_FEATURE_COLS]
    X_vib = X[:, vib_idx]
    valid_vib_mask = ~np.all(np.isnan(X_vib), axis=1)
    vibration_pipeline = None
    if int(np.count_nonzero(valid_vib_mask)) >= 4 and len(set(y[valid_vib_mask])) >= 2:
        vibration_pipeline = build_pipeline(random_state=42)
        vibration_pipeline.fit(X_vib[valid_vib_mask], y[valid_vib_mask])

    if use_grouped:
        n_splits = min(5, len(unique_groups))
        gkf = GroupKFold(n_splits=n_splits)
        cv_scores = cross_val_score(pipeline, X, y, groups=groups, cv=gkf)
        eval_scheme = f"GroupKFold(n_splits={n_splits}) + GroupHoldout(20%)"
    else:
        min_cls = min(Counter(str(lbl) for lbl in y).values())
        if min_cls >= 2:
            n_splits = min(5, min_cls)
            cv = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=42)
        else:
            n_splits = min(5, len(X))
            cv = KFold(n_splits=n_splits, shuffle=True, random_state=42)
        cv_scores = cross_val_score(pipeline, X, y, cv=cv)
        eval_scheme = (
            f"Hybrid StratifiedHoldout(20%) + {n_splits}-Fold CV"
            if real_count > 0
            else f"Synthetic Baseline Calibration ({n_splits}-Fold StratifiedKFold + 20% Holdout)"
        )

    mean_cv = float(np.mean(cv_scores))
    std_cv = float(np.std(cv_scores))

    y_pred = pipeline.predict(X_test)
    test_acc = float(np.mean(y_pred == y_test))

    report = classification_report(
        y_test, y_pred, labels=CLASSES, target_names=CLASSES, output_dict=True, zero_division=0
    )
    cm = confusion_matrix(y_test, y_pred, labels=CLASSES).tolist()

    # Refit final multimodal pipeline on full dataset after holdout & CV evaluation
    pipeline.fit(X, y)

    rf_model = pipeline.named_steps["rf"]
    raw_importances = rf_model.feature_importances_
    feature_importances = [
        {
            "feature": f,
            "importance": round(float(imp), 4),
            "importance_pct": round(float(imp) * 100.0, 2),
        }
        for f, imp in sorted(zip(FEATURE_COLS, raw_importances), key=lambda x: x[1], reverse=True)
    ]

    per_class_metrics = {}
    for c in CLASSES:
        if c in report:
            per_class_metrics[c] = {
                "precision": round(float(report[c]["precision"]), 4),
                "recall": round(float(report[c]["recall"]), 4),
                "f1_score": round(float(report[c]["f1-score"]), 4),
                "support": int(report[c]["support"]),
            }

    payload = {
        "model": pipeline,
        "audio_model": audio_pipeline,
        "vibration_model": vibration_pipeline,
        "meta": {
            "evaluation_scheme": eval_scheme,
            "feature_order": FEATURE_COLS,
            "audio_feature_order": AUDIO_FEATURE_COLS,
            "vibration_feature_order": VIBRATION_FEATURE_COLS,
            "context_metadata_excluded": CONTEXT_METADATA_COLS,
            "classes": CLASSES,
            "accuracy": round(test_acc, 4),
            "cv_accuracy": round(mean_cv, 4),
            "cv_std": round(std_cv, 4),
            "f1_macro": round(float(report.get("macro avg", {}).get("f1-score", test_acc)), 4),
            "f1_weighted": round(float(report.get("weighted avg", {}).get("f1-score", test_acc)), 4),
            "trained_on": datetime.now(timezone.utc).isoformat(),
            "n_samples": int(len(X)),
            "real_samples_count": int(real_count),
            "synthetic_samples_count": int(synth_count),
            "algorithm": "Pipeline(SimpleImputer, StandardScaler, RandomForestClassifier)",
            "confusion_matrix": cm,
            "per_class_metrics": per_class_metrics,
            "feature_importances": feature_importances,
        },
    }

    with open(model_path, "wb") as f:
        pickle.dump(payload, f)

    meta_path = os.path.join(os.path.dirname(model_path), "model_meta.json")
    with open(meta_path, "w", encoding="utf-8") as mf:
        json.dump(payload["meta"], mf, indent=2)

    return payload["meta"]


def main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(
        description="HAVEN AI — Train & Evaluate Sound + Vibration Engine Fault Classifier"
    )
    parser.add_argument(
        "--recordings-dir",
        type=str,
        default=None,
        help="Path to validated real recordings root (`recordings/<session>/<vehicle>/<condition>/<ts>/`)",
    )
    parser.add_argument(
        "--report-file",
        type=str,
        default=DEFAULT_REPORT_PATH,
        help="Output JSON report path for GroupKFold evaluation",
    )
    parser.add_argument(
        "--group-by",
        type=str,
        choices=["vehicle", "session"],
        default="vehicle",
        help="Grouping key for GroupKFold evaluation (default: vehicle)",
    )
    parser.add_argument("--window-sec", type=float, default=1.5, help="Window length in seconds (1-2s)")
    parser.add_argument("--overlap", type=float, default=0.5, help="Window overlap ratio (default: 0.5)")
    parser.add_argument("--generate-synthetic", action="store_true", help="Train with synthetic calibration data")
    parser.add_argument("--no-synthetic", action="store_true", help="Disable synthetic augmentation when training from --records-json")
    parser.add_argument("--samples", type=int, default=150, help="Synthetic samples per class")
    parser.add_argument("--records-json", type=str, default=None, help="Path to JSON records from Laravel")
    args = parser.parse_args(argv)

    if args.recordings_dir:
        X, y, groups, vehicles, _ = load_recordings_dataset(
            args.recordings_dir,
            window_sec=args.window_sec,
            overlap=args.overlap,
        )
        meta = evaluate_and_train_grouped(
            X,
            y,
            groups,
            vehicles,
            report_path=args.report_file,
            model_path=MODEL_PATH,
            group_by=args.group_by,
        )
        print(
            f"[GROUPED EVALUATION COMPLETE] Vehicles={meta['n_vehicles']} | "
            f"Windows={meta['n_samples']} | "
            f"Accuracy={meta['accuracy'] * 100:.2f}% | "
            f"Macro-F1={meta['f1_macro'] * 100:.2f}% | "
            f"Report={args.report_file}"
        )
        return 0

    real_records = []
    if args.records_json and os.path.exists(args.records_json):
        with open(args.records_json, "r", encoding="utf-8") as rf:
            real_records = json.load(rf)

    augment_synth = not args.no_synthetic
    X, y, real_c, synth_c = load_hybrid_dataset(
        real_records=real_records,
        augment_synthetic=augment_synth,
        synthetic_per_class=args.samples,
    )
    groups = None
    if real_records and real_c > 0:
        real_groups = []
        for idx, r in enumerate(real_records):
            cond = str(r.get("true_condition") or r.get("condition") or r.get("label") or "").lower().strip()
            feat_dict = r.get("features") if isinstance(r.get("features"), dict) else r
            if cond in CLASSES:
                try:
                    build_feature_vector(feat_dict)
                    real_groups.append(str(r.get("vehicle_id") or r.get("session_id") or f"real_{idx}"))
                except ValueError:
                    continue
        if len(set(real_groups)) >= 2 and len(real_groups) == real_c:
            synth_groups = [f"synth_grp_{i % 5}" for i in range(synth_c)]
            groups = np.asarray(real_groups + synth_groups, dtype=object)

    meta = train_and_export(X, y, real_count=real_c, synth_count=synth_c, groups=groups)
    print(f"Accuracy: {meta['accuracy'] * 100:.2f}% | F1: {meta['f1_macro'] * 100:.2f}%")
    return 0


if __name__ == "__main__":
    sys.exit(main())
