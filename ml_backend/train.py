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
    # Mechanical Vibration (MPU6050: D21/D22)
    "peak_g", "rms_g", "crest_factor",
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
                temp_c, rpm, voltage_v, current_ma,
                rms_audio, zcr, centroid, rolloff,
                mfcc1, mfcc2, mfcc3, mfcc4, mfcc5,
            ]

            X_list.append(sample_vector)
            y_list.append(condition)

    return np.array(X_list), np.array(y_list)

    return np.array(X_list), np.array(y_list)


def train_and_export(X: np.ndarray, y: np.ndarray):
    """
    Trains a StandardScaler + RandomForest pipeline and exports model.pkl.
    """
    logger.info(f"Training on {len(X)} samples across {len(np.unique(y))} classes...")

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

    # Cross-validation
    cv_scores = cross_val_score(pipeline, X, y, cv=5)
    mean_cv = np.mean(cv_scores)
    logger.info(f"5-Fold Cross-Validation Accuracy: {mean_cv * 100:.2f}% (±{np.std(cv_scores)*100:.2f}%)")

    # Test set evaluation
    y_pred = pipeline.predict(X_test)
    test_acc = np.mean(y_pred == y_test)
    logger.info(f"Holdout Test Set Accuracy: {test_acc * 100:.2f}%")

    print("\n--- CLASSIFICATION REPORT ---")
    print(classification_report(y_test, y_pred, target_names=CLASSES))

    # Package model payload
    payload = {
        "model": pipeline,
        "meta": {
            "feature_order": FEATURE_COLS,
            "classes": CLASSES,
            "accuracy": round(float(test_acc), 4),
            "cv_accuracy": round(float(mean_cv), 4),
            "trained_on": datetime.now(timezone.utc).isoformat(),
            "n_samples": len(X),
            "algorithm": "RandomForestClassifier(n_estimators=120)",
        }
    }

    with open(MODEL_PATH, "wb") as f:
        pickle.dump(payload, f)

    logger.info(f"✅ Success! Trained model exported to: {MODEL_PATH}")
    logger.info("FastAPI will automatically load model.pkl on next startup.")


def main():
    parser = argparse.ArgumentParser(description="Train HAVEN Engine Fault Classifier")
    parser.add_argument("--generate-synthetic", action="store_true", help="Generate synthetic multi-sensor training dataset")
    parser.add_argument("--samples", type=int, default=150, help="Number of samples per class to generate (default: 150)")
    args = parser.parse_args()

    # If dataset directory exists and has files, train on custom data; otherwise generate synthetic
    if args.generate_synthetic or not os.path.exists(DATASET_DIR):
        X, y = generate_synthetic_samples(n_per_class=args.samples)
        train_and_export(X, y)
    else:
        logger.info(f"Found dataset directory: {DATASET_DIR}. Loading samples...")
        # (Custom dataset loader can be implemented here)


if __name__ == "__main__":
    main()
