"""
Engine condition classifier for HAVEN ML backend.

Priority loading order:
1. model.pkl — trained scikit-learn pipeline (drop-in replacement)
2. Rule-based fallback — uses feature thresholds derived from domain knowledge

Condition classes (UMak CET Capstone Scope):
  normal          — Normal Engine Operation (baseline idling and revving)
  misfire         — Engine Misfire (combustion dropout, cyclic torque dips)
  loose_component — Loose or Worn Component Vibration (structural mount/bracket resonance)
  valve_clearance — Improper Valve Clearance (valvetrain lash, 0.5x RPM tapping)
"""

import os
import pickle
import numpy as np
import logging

logger = logging.getLogger(__name__)

MODEL_PATH = os.path.join(os.path.dirname(__file__), "model.pkl")

CLASSES = ["normal", "misfire", "loose_component", "valve_clearance"]


class EngineClassifier:
    def __init__(self):
        self._model = None
        self._model_meta = {}
        self._load_model()

    def _load_model(self):
        if os.path.exists(MODEL_PATH):
            try:
                with open(MODEL_PATH, "rb") as f:
                    payload = pickle.load(f)
                self._model = payload.get("model") or payload  # support both formats
                self._model_meta = payload.get("meta", {}) if isinstance(payload, dict) else {}
                logger.info("Trained model loaded from model.pkl")
            except Exception as e:
                logger.warning(f"Could not load model.pkl: {e}. Using rule-based fallback.")
                self._model = None
        else:
            logger.info("model.pkl not found — using rule-based classifier.")

    def is_model_loaded(self) -> bool:
        return self._model is not None

    def model_info(self) -> dict:
        if self._model:
            return {
                "type": "trained_sklearn",
                "path": MODEL_PATH,
                "meta": self._model_meta,
            }
        return {
            "type": "rule_based",
            "description": "Domain-knowledge threshold classifier. Replace with trained model.pkl.",
        }

    def predict(self, features: dict) -> dict:
        if self._model:
            return self._predict_trained(features)
        return self._predict_rule_based(features)

    # ── Trained model path ──
    def _predict_trained(self, features: dict) -> dict:
        try:
            feature_order = self._model_meta.get("feature_order", [])
            if feature_order:
                X = np.array([[features.get(k, 0.0) or 0.0 for k in feature_order]])
            else:
                X = np.array([[v for v in features.values() if isinstance(v, (int, float)) and v is not None]])

            pred = self._model.predict(X)[0]
            proba = self._model.predict_proba(X)[0] if hasattr(self._model, "predict_proba") else None

            top_classes = []
            if proba is not None:
                labels = self._model.classes_ if hasattr(self._model, "classes_") else CLASSES
                top_classes = sorted(
                    [{"condition": str(l), "confidence": float(p)} for l, p in zip(labels, proba)],
                    key=lambda x: x["confidence"], reverse=True
                )[:3]
                confidence = float(max(proba))
            else:
                confidence = 0.85
                top_classes = [{"condition": str(pred), "confidence": confidence}]

            return {"condition": str(pred), "confidence": round(confidence, 4), "top_classes": top_classes, "mode": "trained"}

        except Exception as e:
            logger.error(f"Trained model prediction failed: {e}. Falling back to rule-based.")
            return self._predict_rule_based(features)

    # ── Rule-based fallback ──
    def _predict_rule_based(self, features: dict) -> dict:
        """
        Rule-based classifier using domain thresholds aligned with the 4 capstone classes:
          1. valve_clearance - Impulsive high-G valvetrain lash (0.5x RPM tapping, high Crest Factor)
          2. misfire         - Combustion dropout (ragged ZCR, torque dip vibration)
          3. loose_component - Structural mount / accessory harmonic (elevated continuous RMS G)
          4. normal          - Balanced baseline idling or revving
        """
        scores = {c: 0.0 for c in CLASSES}

        peak_g      = features.get("peak_g") or 0.0
        rms_g       = features.get("rms_g") or 0.0
        crest       = features.get("crest_factor") or 0.0
        zcr         = features.get("zcr_mean") or 0.0
        centroid    = features.get("spectral_centroid_mean") or 0.0
        rms_audio   = features.get("rms_mean") or 0.0
        mfcc1       = features.get("mfcc_1_mean") or 0.0
        temp_c      = features.get("temperature_c")
        rpm         = features.get("rpm")
        voltage_v   = features.get("voltage_v")

        # 1. Improper Valve Clearance: High Crest Factor (> 4.0), high Peak G, high acoustic frequency (> 2600 Hz)
        if crest > 4.2 or peak_g > 3.6:
            scores["valve_clearance"] += 0.65
        elif crest > 3.5 and peak_g > 2.5:
            scores["valve_clearance"] += 0.35
        if centroid > 2600 and rms_audio > 0.06:
            scores["valve_clearance"] += 0.25

        # 2. Engine Misfire: Unstable combustion -> elevated ZCR, cyclic torque dips
        if zcr > 0.17:
            scores["misfire"] += 0.55
        if 2.8 < crest <= 4.2 and 1.6 < peak_g < 3.5:
            scores["misfire"] += 0.30
        if rms_g > 0.55 and zcr > 0.12:
            scores["misfire"] += 0.20

        # 3. Loose or Worn Component Vibration: High continuous baseline RMS G, moderate peak, low-frequency rumble
        if rms_g > 0.55 and crest < 3.4:
            scores["loose_component"] += 0.60
        elif rms_g > 0.40 and peak_g > 1.8:
            scores["loose_component"] += 0.35
        if centroid < 1700 and rms_audio > 0.04 and crest < 3.2:
            scores["loose_component"] += 0.25

        # 4. Normal Engine Operation: Default when signals reside within manufacturer baseline envelope
        if peak_g <= 1.8 and rms_g <= 0.45 and crest <= 3.6:
            scores["normal"] += 0.45

        if all(s == 0.0 for k, s in scores.items() if k != "normal"):
            scores["normal"] = 0.80

        # Normalise to probabilities
        total = sum(scores.values()) or 1.0
        probs = {k: round(v / total, 4) for k, v in scores.items()}
        condition = max(probs, key=probs.get)

        top_classes = sorted(
            [{"condition": k, "confidence": v} for k, v in probs.items()],
            key=lambda x: x["confidence"], reverse=True
        )[:3]

        return {
            "condition":   condition,
            "confidence":  probs[condition],
            "top_classes": top_classes,
            "mode":        "rule_based",
        }
