"""
HAVEN ML Backend — Model Training Pipeline
Train a scikit-learn classifier pipeline on multi-sensor vehicular telemetry:
  - Audio acoustic DSP features (13 MFCCs, Spectral Centroid, Rolloff, ZCR, RMS)
  - Mechanical vibration features (Peak G, RMS G, Crest Factor)
  - Hardware DAQ features (DS18B20 Temp, Hall RPM, INA219 Voltage/Current)

Output:
  Exports `model.pkl` for drop-in loading by `classifier.py`.

Usage:
  # Train on existing dataset in ./dataset/
  python train.py

  # Generate 500 synthetic calibration samples and train model.pkl
  python train.py --generate-synthetic
"""

import os
import sys
import pickle
import argparse
import numpy as np
import logging
from datetime import datetime, timezone
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.preprocessing import StandardScaler
from sklearn.pipeline import Pipeline

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

CLASSES = ["normal", "misfire", "loose_component", "valve_clearance"]
MODEL_PATH = os.path.join(os.path.dirname(__file__), "model.pkl")
DATASET_DIR = os.path.join(os.path.dirname(__file__), "dataset")

FEATURE_COLS = [
    # Mechanical Vibration (MPU6050: D21/D22 & FFT Frequency Domain)
    "peak_g", "rms_g", "crest_factor",
    "dominant_freq_hz", "spectral_entropy",
    # Thermal Dynamics (DS18B20: D4)
    "temperature_c",
    # Rotational Tachometer (Hall: D23)
    "rpm",
    # Electrical Power (INA219: D21/D22)
    "voltage_v", "current_ma",
    # Acoustic DSP (INMP441 / Web Audio: D18/D19/D5)
    "rms_mean", "zcr_mean", "spectral_centroid_mean", "spectral_rolloff_mean",
    "mfcc_1_mean", "mfcc_2_mean", "mfcc_3_mean", "mfcc_4_mean", "mfcc_5_mean",
]


def generate_synthetic_samples(n_per_class: int = 150) -> tuple:
    """
    Generates physically calibrated synthetic telemetry and acoustic features
    for the 4 thesis-approved engine conditions (UMak CET Capstone Scope):
      1. normal          - Normal Engine Operation (baseline idling & revving)
      2. misfire         - Engine Misfire
      3. loose_component - Loose or Worn Component Vibration
      4. valve_clearance - Improper Valve Clearance (valvetrain lash)
    """
    logger.info(f"Generating {n_per_class * len(CLASSES)} synthetic automotive telemetry samples across {len(CLASSES)} target classes...")
    X_list = []
    y_list = []

    for label_idx, condition in enumerate(CLASSES):
        for i in range(n_per_class):
            if condition == "valve_clearance":
                # Improper Valve Clearance: sharp metallic impact clicking at 0.5x RPM (camshaft speed),
                # high peak G acceleration spikes on cylinder head, high crest factor, prominent high-freq acoustics
                peak_g = np.random.uniform(2.8, 5.2)
                rms_g = np.random.uniform(0.40, 0.85)
                dom_hz = np.random.uniform(18.0, 35.0)  # half-engine harmonic taps
                entropy = np.random.uniform(0.32, 0.52)
                temp_c = np.random.uniform(88.0, 96.0)
                rpm = int(np.random.uniform(1200, 2600))
                voltage_v = np.random.uniform(13.8, 14.3)
                current_ma = np.random.uniform(380.0, 520.0)
                rms_audio = np.random.uniform(0.08, 0.20)
                zcr = np.random.uniform(0.10, 0.18)
                centroid = np.random.uniform(2800, 5200) # high-frequency valve lash ping
                rolloff = np.random.uniform(4500, 7800)
                mfcc1 = np.random.uniform(-160, -110)

            elif condition == "loose_component":
                # Loose or Worn Component Vibration: engine/transmission mount fatigue, loose accessory brackets,
                # continuous elevated baseline RMS vibration energy, moderate peak, low-to-mid frequency rumble
                peak_g = np.random.uniform(1.8, 3.4)
                rms_g = np.random.uniform(0.55, 1.15) # high continuous vibration energy
                dom_hz = np.random.uniform(25.0, 58.0) # structural resonance band
                entropy = np.random.uniform(0.22, 0.42) # narrowband structural resonance
                temp_c = np.random.uniform(86.0, 94.0)
                rpm = int(np.random.uniform(650, 950)) # common at idle and low gear engagement
                voltage_v = np.random.uniform(13.5, 14.1)
                current_ma = np.random.uniform(360.0, 480.0)
                rms_audio = np.random.uniform(0.05, 0.11)
                zcr = np.random.uniform(0.06, 0.12)
                centroid = np.random.uniform(800, 1650) # low-frequency mechanical rumble
                rolloff = np.random.uniform(1700, 2900)
                mfcc1 = np.random.uniform(-210, -150)

            elif condition == "misfire":
                # Engine Misfire: combustion dropout every 720 deg crank rotation, erratic RPM lurch,
                # ragged combustion acoustics (high ZCR), cyclic torque dip vibration
                peak_g = np.random.uniform(1.8, 3.2)
                rms_g = np.random.uniform(0.50, 1.05)
                dom_hz = np.random.uniform(6.0, 15.0)  # low-frequency cyclical torque dip lurch
                entropy = np.random.uniform(0.55, 0.78) # high entropy from irregular combustion
                temp_c = np.random.uniform(86.0, 93.0)
                rpm = int(np.random.uniform(620, 850)) # unmetered idle lurch
                voltage_v = np.random.uniform(13.6, 14.1)
                current_ma = np.random.uniform(400.0, 520.0)
                rms_audio = np.random.uniform(0.06, 0.14)
                zcr = np.random.uniform(0.19, 0.35) # ragged combustion ZCR
                centroid = np.random.uniform(2200, 3600)
                rolloff = np.random.uniform(3800, 5600)
                mfcc1 = np.random.uniform(-240, -170)

            else: # normal
                # Normal Engine Operation: split between baseline idling (50%) and baseline revving (50%)
                is_revving = (i % 2 == 1)
                if is_revving:
                    # Baseline Revving (2,000 - 2,500 RPM)
                    peak_g = np.random.uniform(1.15, 1.75)
                    rms_g = np.random.uniform(0.26, 0.46)
                    dom_hz = np.random.uniform(33.0, 42.0)
                    entropy = np.random.uniform(0.20, 0.38)
                    temp_c = np.random.uniform(89.0, 94.0)
                    rpm = int(np.random.uniform(2000, 2500))
                    voltage_v = np.random.uniform(14.1, 14.4)
                    current_ma = np.random.uniform(380.0, 480.0)
                    rms_audio = np.random.uniform(0.04, 0.08)
                    zcr = np.random.uniform(0.05, 0.09)
                    centroid = np.random.uniform(1400, 2100)
                    rolloff = np.random.uniform(2600, 3800)
                    mfcc1 = np.random.uniform(-170, -130)
                else:
                    # Baseline Idling (750 - 900 RPM)
                    peak_g = np.random.uniform(0.80, 1.25)
                    rms_g = np.random.uniform(0.15, 0.30)
                    dom_hz = np.random.uniform(12.0, 15.0)
                    entropy = np.random.uniform(0.18, 0.35)
                    temp_c = np.random.uniform(88.0, 93.0)
                    rpm = int(np.random.uniform(760, 860))
                    voltage_v = np.random.uniform(14.0, 14.3)
                    current_ma = np.random.uniform(320.0, 410.0)
                    rms_audio = np.random.uniform(0.02, 0.05)
                    zcr = np.random.uniform(0.04, 0.07)
                    centroid = np.random.uniform(1050, 1650)
                    rolloff = np.random.uniform(1950, 3050)
                    mfcc1 = np.random.uniform(-190, -145)

            crest_factor = round(peak_g / rms_g, 3) if rms_g > 0 else 1.0
            mfcc2 = np.random.uniform(10, 45)
            mfcc3 = np.random.uniform(-25, 15)
            mfcc4 = np.random.uniform(-15, 20)
            mfcc5 = np.random.uniform(-20, 10)

            sample_vector = [
                peak_g, rms_g, crest_factor,
                dom_hz, entropy,
                temp_c, rpm, voltage_v, current_ma,
                rms_audio, zcr, centroid, rolloff,
                mfcc1, mfcc2, mfcc3, mfcc4, mfcc5,
            ]

            X_list.append(sample_vector)
            y_list.append(condition)

    return np.array(X_list), np.array(y_list)


def build_feature_vector(row: dict) -> list:
    """Extract a 18-element feature vector matching FEATURE_COLS from a dict."""
    peak_g = float(row.get("peak_g") or row.get("vibration_peak_g") or 1.2)
    rms_g = float(row.get("rms_g") or row.get("vibration_rms_g") or 0.28)
    crest = float(row.get("crest_factor") or (peak_g / rms_g if rms_g > 0 else 1.0))
    dom_hz = float(row.get("dominant_freq_hz") or 14.0)
    entropy = float(row.get("spectral_entropy") or 0.28)
    temp_c = float(row.get("temperature_c") or 90.0)
    rpm = float(row.get("rpm") or 800.0)
    voltage_v = float(row.get("voltage_v") or 14.1)
    current_ma = float(row.get("current_ma") or 400.0)
    rms_audio = float(row.get("rms_mean") or 0.05)
    zcr = float(row.get("zcr_mean") or 0.07)
    centroid = float(row.get("spectral_centroid_mean") or 1600.0)
    rolloff = float(row.get("spectral_rolloff_mean") or 2800.0)
    mfcc1 = float(row.get("mfcc_1_mean") or -160.0)
    mfcc2 = float(row.get("mfcc_2_mean") or 25.0)
    mfcc3 = float(row.get("mfcc_3_mean") or -10.0)
    mfcc4 = float(row.get("mfcc_4_mean") or 5.0)
    mfcc5 = float(row.get("mfcc_5_mean") or -5.0)

    return [
        peak_g, rms_g, crest,
        dom_hz, entropy,
        temp_c, rpm, voltage_v, current_ma,
        rms_audio, zcr, centroid, rolloff,
        mfcc1, mfcc2, mfcc3, mfcc4, mfcc5,
    ]


def load_hybrid_dataset(real_records: list = None, augment_synthetic: bool = True, synthetic_per_class: int = 100) -> tuple:
    """
    Combines real labeled diagnostic records with calibrated synthetic augmentation
    so underrepresented classes are balanced.
    """
    X_real = []
    y_real = []
    counts = {c: 0 for c in CLASSES}

    if real_records:
        for r in real_records:
            cond = str(r.get("condition") or r.get("ml_condition") or r.get("label") or "").lower().strip()
            feat_dict = r.get("features") if isinstance(r.get("features"), dict) else r
            if cond in CLASSES:
                X_real.append(build_feature_vector(feat_dict))
                y_real.append(cond)
                counts[cond] += 1

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
                X = np.vstack([np.array(X_real), X_synth])
                y = np.concatenate([np.array(y_real), y_synth])
            else:
                X = X_synth
                y = y_synth
            synth_count = len(X_synth)
        else:
            X = np.array(X_real)
            y = np.array(y_real)
            synth_count = 0
    else:
        X = np.array(X_real)
        y = np.array(y_real)
        synth_count = 0

    return X, y, real_count, synth_count


def train_and_export(X: np.ndarray, y: np.ndarray, real_count: int = 0, synth_count: int = 0) -> dict:
    """
    Trains a StandardScaler + RandomForest pipeline and exports model.pkl.
    Returns full evaluation metrics payload.
    """
    logger.info(f"Training on {len(X)} samples ({real_count} real, {synth_count} synthetic) across {len(np.unique(y))} classes...")

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    pipeline = Pipeline([
        ("scaler", StandardScaler()),
        ("rf", RandomForestClassifier(
            n_estimators=120,
            max_depth=12,
            min_samples_split=3,
            random_state=42,
            n_jobs=-1
        )),
    ])

    pipeline.fit(X_train, y_train)

    # 5-Fold Cross-validation
    cv_scores = cross_val_score(pipeline, X, y, cv=5)
    mean_cv = float(np.mean(cv_scores))
    std_cv = float(np.std(cv_scores))
    logger.info(f"5-Fold Cross-Validation Accuracy: {mean_cv * 100:.2f}% (±{std_cv * 100:.2f}%)")

    # Holdout Test set evaluation
    y_pred = pipeline.predict(X_test)
    test_acc = float(np.mean(y_pred == y_test))
    logger.info(f"Holdout Test Set Accuracy: {test_acc * 100:.2f}%")

    # Classification report dict
    report = classification_report(y_test, y_pred, target_names=CLASSES, output_dict=True)
    cm = confusion_matrix(y_test, y_pred, labels=CLASSES).tolist()

    # Feature importances
    rf_model = pipeline.named_steps["rf"]
    raw_importances = rf_model.feature_importances_
    feature_importances = [
        {"feature": f, "importance": round(float(imp), 4), "importance_pct": round(float(imp) * 100, 2)}
        for f, imp in sorted(zip(FEATURE_COLS, raw_importances), key=lambda x: x[1], reverse=True)
    ]

    per_class_metrics = {}
    for c in CLASSES:
        if c in report:
            per_class_metrics[c] = {
                "precision": round(float(report[c]["precision"]), 4),
                "recall":    round(float(report[c]["recall"]), 4),
                "f1_score":  round(float(report[c]["f1-score"]), 4),
                "support":   int(report[c]["support"]),
            }

    # Package model payload
    payload = {
        "model": pipeline,
        "meta": {
            "feature_order":        FEATURE_COLS,
            "classes":              CLASSES,
            "accuracy":             round(test_acc, 4),
            "cv_accuracy":          round(mean_cv, 4),
            "cv_std":               round(std_cv, 4),
            "f1_macro":             round(float(report.get("macro avg", {}).get("f1-score", test_acc)), 4),
            "f1_weighted":          round(float(report.get("weighted avg", {}).get("f1-score", test_acc)), 4),
            "trained_on":           datetime.now(timezone.utc).isoformat(),
            "n_samples":            len(X),
            "real_samples_count":   real_count,
            "synthetic_samples_count": synth_count,
            "algorithm":            "RandomForestClassifier(n_estimators=120, max_depth=12)",
            "confusion_matrix":     cm,
            "per_class_metrics":    per_class_metrics,
            "feature_importances":  feature_importances,
        }
    }

    with open(MODEL_PATH, "wb") as f:
        pickle.dump(payload, f)

    import json
    meta_path = os.path.join(os.path.dirname(MODEL_PATH), "model_meta.json")
    with open(meta_path, "w", encoding="utf-8") as mf:
        json.dump(payload["meta"], mf, indent=2)

    logger.info(f"✅ Success! Trained model exported to: {MODEL_PATH}")
    return payload["meta"]


def main():
    import json
    parser = argparse.ArgumentParser(description="Train HAVEN Engine Fault Classifier")
    parser.add_argument("--generate-synthetic", action="store_true", help="Generate synthetic multi-sensor training dataset")
    parser.add_argument("--samples", type=int, default=150, help="Number of samples per class to generate (default: 150)")
    parser.add_argument("--records-json", type=str, default=None, help="Path to JSON file of real labeled diagnostic records")
    args = parser.parse_args()

    real_records = []
    if args.records_json and os.path.exists(args.records_json):
        try:
            with open(args.records_json, "r", encoding="utf-8") as rf:
                real_records = json.load(rf)
        except Exception as ex:
            logger.warning(f"Could not read records-json: {ex}")

    X, y, real_c, synth_c = load_hybrid_dataset(
        real_records=real_records,
        augment_synthetic=True,
        synthetic_per_class=args.samples,
    )
    meta = train_and_export(X, y, real_count=real_c, synth_count=synth_c)
    print(f"Accuracy: {meta['accuracy']*100:.2f}% | F1: {meta['f1_macro']*100:.2f}%")


if __name__ == "__main__":
    main()

