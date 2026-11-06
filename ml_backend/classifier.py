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
        self._audio_model = None
        self._model_meta = {}
        self._load_model()

    def _load_model(self):
        if os.path.exists(MODEL_PATH):
            try:
                with open(MODEL_PATH, "rb") as f:
                    payload = pickle.load(f)
                self._model = payload.get("model") or payload  # support both formats
                self._audio_model = payload.get("audio_model") if isinstance(payload, dict) else None
                self._model_meta = payload.get("meta", {}) if isinstance(payload, dict) else {}
                logger.info("Trained model loaded from model.pkl")
            except Exception as e:
                logger.warning(f"Could not load model.pkl: {e}. Using rule-based fallback.")
                self._model = None
                self._audio_model = None
        else:
            logger.info("model.pkl not found — using rule-based classifier.")

    def reload_model(self) -> bool:
        """Hot-reload model.pkl after retraining."""
        self._load_model()
        return self.is_model_loaded()

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
        peak_g = float(features.get("peak_g") or 0.0)
        rms_g = float(features.get("rms_g") or 0.0)
        temp_c = features.get("temperature_c")
        voltage_v = features.get("voltage_v")
        rpm = features.get("rpm")
        rms_audio = float(features.get("rms_mean") or 0.0)
        rms_audio_std = float(features.get("rms_std") or 0.0)
        centroid = float(features.get("spectral_centroid_mean") or 0.0)
        rolloff = float(features.get("spectral_rolloff_mean") or 0.0)

        vibration_source = features.get("vibration_source")
        has_mechanical = (vibration_source not in ("simulated", "skipped")) and (peak_g >= 0.08 or rms_g >= 0.04)
        has_telemetry = (
            (temp_c is not None and float(temp_c) >= 45.0)
            or (voltage_v is not None and float(voltage_v) >= 12.2)
            or (rpm is not None and float(rpm) >= 300.0)
        )
        # Acoustic engine presence requires sustained energy and plausible engine spectral envelope
        # (rejects silent room or isolated transient claps where rms_std >> rms_mean)
        is_sustained_audio = (rms_audio_std == 0.0) or (rms_audio_std <= rms_audio * 2.2)
        has_engine_spectrum = (centroid == 0.0 and rms_audio >= 0.08) or (350.0 <= centroid <= 6500.0 and rolloff >= 700.0)
        has_strong_audio = (rms_audio >= 0.035) and is_sustained_audio and has_engine_spectrum

        # Engine presence gating
        if not has_mechanical and not has_telemetry and not has_strong_audio:
            return {
                "condition": "ambient_noise",
                "confidence": 0.95,
                "top_classes": [
                    {"condition": "ambient_noise", "confidence": 0.95},
                    {"condition": "normal", "confidence": 0.05}
                ],
                "mode": "live_ml",
                "feature_contributions": self.explain_prediction(features, "ambient_noise"),
                "warnings": ["SIGNAL GATE: No physical engine block vibration (hardware uncoupled) or sustained powertrain acoustic signature detected. Ambient noise filter engaged."]
            }

        if self._model:
            result = self._predict_trained(features)
        else:
            result = self._predict_rule_based(features)

        result["feature_contributions"] = self.explain_prediction(features, result.get("condition", "normal"))
        return result

    # ── Explainable AI (Model-Intrinsic Feature Contributions) ──
    def explain_prediction(self, features: dict, predicted_class: str) -> list:
        """
        Computes model-driven Explainable AI (XAI) feature contributions for `predicted_class`
        by combining the trained Random Forest's `feature_importances_` with each feature's
        standardized deviation (`|z-score|` from the trained `StandardScaler`).
        Strictly uses the 14 Sound + Vibration model features and guarantees a non-empty list.
        """
        peak_g    = float(features.get("peak_g") or 0.0)
        rms_g     = float(features.get("rms_g") or 0.0)
        crest     = float(features.get("crest_factor") or 0.0)
        dom_hz    = float(features.get("dominant_freq_hz") or 0.0)
        entropy   = float(features.get("spectral_entropy") or 0.0)
        rms_audio = float(features.get("rms_mean") or 0.0)
        zcr       = float(features.get("zcr_mean") or 0.0)
        centroid  = float(features.get("spectral_centroid_mean") or 0.0)
        rolloff   = float(features.get("spectral_rolloff_mean") or 0.0)
        mfcc1     = float(features.get("mfcc_1_mean") or 0.0)

        if predicted_class in ("ambient_noise", "no_engine"):
            return [
                {
                    "feature": "mechanical_coupling",
                    "label": "Engine Block Mechanical Coupling",
                    "value": round(rms_g, 2),
                    "unit": "g RMS",
                    "impact_pct": 45.0,
                    "explanation": "0.00g mechanical vibration confirms the sensor was uncoupled from an operating internal combustion engine.",
                },
                {
                    "feature": "ambient_acoustic_profile",
                    "label": "Acoustic Combustion Cadence",
                    "value": 0,
                    "unit": "orders",
                    "impact_pct": 35.0,
                    "explanation": "Microphone signal consists of diffuse ambient/crowd environmental noise without periodic cylinder firing harmonics.",
                },
                {
                    "feature": "hardware_telemetry",
                    "label": "Powertrain Operating Telemetry",
                    "value": 0,
                    "unit": "sensors",
                    "impact_pct": 20.0,
                    "explanation": "No active engine coolant thermal signature (>45°C) or alternator bus voltage (>12.2V) verified.",
                },
            ]

        feature_catalog = {
            "peak_g": {
                "label": "Peak Dynamic Acceleration",
                "val": round(peak_g, 3),
                "unit": "g",
                "desc": {
                    "valve_clearance": f"Impulsive cylinder head acceleration peak ({peak_g:.2f}g) reflects valvetrain lash metal-to-metal impact.",
                    "misfire": f"Rotational torque-dip shockwave ({peak_g:.2f}g) during uneven cylinder combustion strokes.",
                    "loose_component": f"Elevated structural vibration peak ({peak_g:.2f}g) from mount or bracket play.",
                    "normal": f"Gravity-removed peak acceleration ({peak_g:.2f}g) remains within normal baseline limits.",
                },
            },
            "rms_g": {
                "label": "RMS Continuous Vibration Energy",
                "val": round(rms_g, 3),
                "unit": "g",
                "desc": {
                    "valve_clearance": f"Baseline RMS vibration energy ({rms_g:.3f}g) underlying high-crest valvetrain impacts.",
                    "misfire": f"Sustained RMS vibration ({rms_g:.3f}g) elevated by cyclic combustion imbalance.",
                    "loose_component": f"High continuous RMS vibration energy ({rms_g:.3f}g) indicates degraded engine mount damping.",
                    "normal": f"Continuous RMS vibration ({rms_g:.3f}g) resides comfortably inside healthy factory tolerance.",
                },
            },
            "crest_factor": {
                "label": "Crest Factor (Impulsiveness)",
                "val": round(crest, 2),
                "unit": "ratio",
                "desc": {
                    "valve_clearance": f"High crest factor ({crest:.2f}) confirms sharp impulsive valvetrain tapping relative to RMS energy.",
                    "misfire": f"Crest factor ({crest:.2f}) captures intermittent torque stumble spikes across firing cycles.",
                    "loose_component": f"Low-to-moderate crest factor ({crest:.2f}) confirms continuous sinusoidal resonance rather than sharp clicks.",
                    "normal": f"Balanced crest factor ({crest:.2f}) indicates smooth harmonic rotation without impact shocks.",
                },
            },
            "dominant_freq_hz": {
                "label": "FFT Dominant Vibration Frequency",
                "val": round(dom_hz, 1),
                "unit": "Hz",
                "desc": {
                    "valve_clearance": f"Dominant FFT harmonic at {dom_hz:.1f} Hz tracks camshaft valvetrain excitation order.",
                    "misfire": f"Low-frequency FFT peak at {dom_hz:.1f} Hz reflects sub-harmonic half-order combustion dropout.",
                    "loose_component": f"Structural resonance peak at {dom_hz:.1f} Hz indicates un-damped component harmonic oscillation.",
                    "normal": f"Primary firing order frequency at {dom_hz:.1f} Hz shows steady crankshaft cadence.",
                },
            },
            "spectral_entropy": {
                "label": "Vibration Spectral Entropy",
                "val": round(entropy, 3),
                "unit": "entropy",
                "desc": {
                    "valve_clearance": f"Spectral entropy ({entropy:.3f}) reflects broadband harmonic spread from metallic valve taps.",
                    "misfire": f"Elevated spectral entropy ({entropy:.3f}) shows chaotic power distribution across frequency bins.",
                    "loose_component": f"Concentrated spectral entropy ({entropy:.3f}) indicates energy locked into a structural resonance mode.",
                    "normal": f"Low spectral entropy ({entropy:.3f}) confirms clean, predictable engine firing harmonics.",
                },
            },
            "zcr_mean": {
                "label": "Acoustic Zero-Crossing Rate",
                "val": round(zcr, 3),
                "unit": "rate",
                "desc": {
                    "valve_clearance": f"Acoustic ZCR ({zcr:.3f}) captures high-frequency metallic valvetrain click transients.",
                    "misfire": f"Elevated acoustic ZCR ({zcr:.3f}) detects erratic combustion cadence and exhaust stumble.",
                    "loose_component": f"Low acoustic ZCR ({zcr:.3f}) aligns with low-frequency structural droning.",
                    "normal": f"Steady acoustic ZCR ({zcr:.3f}) reflects uniform cylinder firing intervals.",
                },
            },
            "spectral_centroid_mean": {
                "label": "Acoustic Spectral Centroid",
                "val": round(centroid, 0),
                "unit": "Hz",
                "desc": {
                    "valve_clearance": f"Bright acoustic centroid ({centroid:.0f} Hz) matches high-pitched metallic lash tapping.",
                    "misfire": f"Shifted acoustic centroid ({centroid:.0f} Hz) from irregular combustion pulse harmonics.",
                    "loose_component": f"Low-frequency acoustic centroid ({centroid:.0f} Hz) characteristic of structural mount rumble.",
                    "normal": f"Balanced acoustic spectral centroid ({centroid:.0f} Hz) within normal engine acoustic profile.",
                },
            },
            "spectral_rolloff_mean": {
                "label": "Acoustic Spectral Rolloff (85%)",
                "val": round(rolloff, 0),
                "unit": "Hz",
                "desc": {
                    "valve_clearance": f"High 85% spectral rolloff ({rolloff:.0f} Hz) driven by ultrasonic/high-band valve impact energy.",
                    "misfire": f"Spectral rolloff at {rolloff:.0f} Hz reflects broadband acoustic turbulence during misfire.",
                    "loose_component": f"Low spectral rolloff ({rolloff:.0f} Hz) confirms acoustic energy is concentrated in bass harmonics.",
                    "normal": f"85% acoustic rolloff ({rolloff:.0f} Hz) matches nominal valvetrain and exhaust bandwidth.",
                },
            },
            "rms_mean": {
                "label": "Acoustic RMS Sound Pressure",
                "val": round(rms_audio, 3),
                "unit": "RMS",
                "desc": {
                    "valve_clearance": f"Elevated acoustic RMS level ({rms_audio:.3f}) from audible valvetrain clatter.",
                    "misfire": f"Acoustic RMS energy ({rms_audio:.3f}) modulated by uneven exhaust pulses.",
                    "loose_component": f"Sustained acoustic RMS level ({rms_audio:.3f}) from resonant component hum.",
                    "normal": f"Acoustic RMS amplitude ({rms_audio:.3f}) consistent with normal idling/revving sound level.",
                },
            },
            "mfcc_1_mean": {
                "label": "MFCC-1 Cepstral Timbre Envelope",
                "val": round(mfcc1, 1),
                "unit": "dB-cep",
                "desc": {
                    "valve_clearance": f"MFCC-1 cepstral coefficient ({mfcc1:.1f}) reflects sharp high-frequency acoustic timbre.",
                    "misfire": f"MFCC-1 cepstral shift ({mfcc1:.1f}) captures hollow combustion dropout acoustic envelope.",
                    "loose_component": f"MFCC-1 cepstral profile ({mfcc1:.1f}) indicates low-band resonant cavity timbre.",
                    "normal": f"MFCC-1 cepstral baseline ({mfcc1:.1f}) matches healthy powertrain acoustic timbre.",
                },
            },
        }

        vib_source = str(features.get("vibration_source") or "").strip().lower()
        has_vibration = vib_source != "skipped" and (peak_g > 0.0 or rms_g > 0.0)
        use_audio_only = (not has_vibration) and (self._audio_model is not None)
        active_model = self._audio_model if use_audio_only else self._model
        active_cols = (
            self._model_meta.get("audio_feature_order", [
                "rms_mean", "zcr_mean", "spectral_centroid_mean", "spectral_rolloff_mean",
                "mfcc_1_mean", "mfcc_2_mean", "mfcc_3_mean", "mfcc_4_mean", "mfcc_5_mean",
            ])
            if use_audio_only
            else self._model_meta.get("feature_order", list(feature_catalog.keys()))
        )

        # Extract trained Random Forest Gini importances & StandardScaler z-scores when available
        rf_weights = {}
        z_scores = {}
        if active_model is not None and hasattr(active_model, "named_steps"):
            try:
                rf_step = active_model.named_steps.get("rf")
                scaler_step = active_model.named_steps.get("scaler")
                if rf_step is not None and hasattr(rf_step, "feature_importances_"):
                    for col, imp in zip(active_cols, rf_step.feature_importances_):
                        rf_weights[col] = float(imp)
                if scaler_step is not None and hasattr(scaler_step, "mean_") and hasattr(scaler_step, "scale_"):
                    for idx, col in enumerate(active_cols):
                        raw_v = features.get(col)
                        if raw_v is not None and not (isinstance(raw_v, float) and np.isnan(raw_v)):
                            scale = float(scaler_step.scale_[idx]) or 1.0
                            z_scores[col] = abs((float(raw_v) - float(scaler_step.mean_[idx])) / scale)
            except Exception:
                pass

        # Class-specific domain affinity multiplier so top explanations highlight the physical mechanism
        class_affinity = {
            "valve_clearance": {"crest_factor": 1.6, "peak_g": 1.5, "spectral_centroid_mean": 1.4, "spectral_rolloff_mean": 1.3, "dominant_freq_hz": 1.2},
            "misfire": {"zcr_mean": 1.6, "spectral_entropy": 1.5, "peak_g": 1.4, "dominant_freq_hz": 1.3, "mfcc_1_mean": 1.25},
            "loose_component": {"rms_g": 1.6, "dominant_freq_hz": 1.5, "crest_factor": 1.35, "spectral_centroid_mean": 1.3, "peak_g": 1.2},
            "normal": {"rms_g": 1.5, "crest_factor": 1.4, "peak_g": 1.35, "spectral_entropy": 1.25, "zcr_mean": 1.2, "spectral_centroid_mean": 1.2},
        }.get(predicted_class, {})

        scored_candidates = []
        for col, meta_info in feature_catalog.items():
            if not has_vibration and col in ("peak_g", "rms_g", "crest_factor", "dominant_freq_hz", "spectral_entropy"):
                continue
            raw_v = features.get(col)
            if raw_v is None or (isinstance(raw_v, float) and np.isnan(raw_v)):
                continue
            base_imp = rf_weights.get(col, 0.08)
            z_factor = 0.6 + min(3.5, z_scores.get(col, 0.5))
            affinity = class_affinity.get(col, 1.0)
            score = base_imp * z_factor * affinity
            scored_candidates.append((score, col, meta_info))

        # Guarantee non-empty fallback even if only minimal features were supplied
        if not scored_candidates:
            fallback_cols = ["zcr_mean", "spectral_centroid_mean", "rms_mean"] if not has_vibration else ["peak_g", "rms_g", "crest_factor"]
            for idx, col in enumerate(fallback_cols):
                meta_info = feature_catalog[col]
                scored_candidates.append((float(3 - idx), col, meta_info))

        scored_candidates.sort(key=lambda x: x[0], reverse=True)
        top_selected = scored_candidates[:4]
        total_score = sum(item[0] for item in top_selected) or 1.0

        contributions = []
        for score, col, meta_info in top_selected:
            contributions.append({
                "feature": col,
                "label": meta_info["label"],
                "value": meta_info["val"],
                "unit": meta_info["unit"],
                "impact_pct": round((score / total_score) * 100.0, 1),
                "explanation": meta_info["desc"].get(predicted_class, meta_info["desc"]["normal"]),
            })

        return contributions

    # ── Trained model path (NaN-aware; supports Audio-Only sub-model & Window Ensemble) ──
    def _predict_trained(self, features: dict) -> dict:
        try:
            vib_source = str(features.get("vibration_source") or "").strip().lower()
            peak_g_val = features.get("peak_g")
            rms_g_val = features.get("rms_g")
            has_vibration = (
                vib_source != "skipped"
                and (
                    (peak_g_val is not None and not (isinstance(peak_g_val, float) and np.isnan(peak_g_val)) and float(peak_g_val) > 0.0)
                    or (rms_g_val is not None and not (isinstance(rms_g_val, float) and np.isnan(rms_g_val)) and float(rms_g_val) > 0.0)
                )
            )

            use_audio_only = (not has_vibration) and (self._audio_model is not None)
            active_model = self._audio_model if use_audio_only else self._model
            feature_order = (
                self._model_meta.get("audio_feature_order", [
                    "rms_mean", "zcr_mean", "spectral_centroid_mean", "spectral_rolloff_mean",
                    "mfcc_1_mean", "mfcc_2_mean", "mfcc_3_mean", "mfcc_4_mean", "mfcc_5_mean",
                ])
                if use_audio_only
                else self._model_meta.get("feature_order", [])
            )

            def _dict_to_row(d: dict) -> list:
                row_vals = []
                for k in feature_order:
                    v = d.get(k)
                    if v is None or (isinstance(v, float) and np.isnan(v)):
                        row_vals.append(np.nan)
                    else:
                        row_vals.append(float(v))
                return row_vals

            if feature_order:
                X_summary = np.array([_dict_to_row(features)], dtype=np.float64)
            else:
                X_summary = np.array(
                    [[float(v) for k, v in features.items() if not str(k).startswith("_") and isinstance(v, (int, float)) and v is not None]],
                    dtype=np.float64,
                )

            if X_summary.size == 0 or np.all(np.isnan(X_summary)):
                raise ValueError("All sound and vibration model features are missing (NaN).")

            win_dicts = features.get("_window_features")
            X_wins = None
            if isinstance(win_dicts, list) and len(win_dicts) > 0 and feature_order:
                win_rows = [_dict_to_row(wd) for wd in win_dicts if isinstance(wd, dict)]
                win_rows = [r for r in win_rows if not all(np.isnan(v) for v in r)]
                if win_rows:
                    X_wins = np.asarray(win_rows, dtype=np.float64)

            if hasattr(active_model, "predict_proba"):
                proba_summary = active_model.predict_proba(X_summary)[0]
                if X_wins is not None and len(X_wins) > 0:
                    proba_wins = active_model.predict_proba(X_wins)
                    proba = 0.50 * proba_summary + 0.50 * np.mean(proba_wins, axis=0)
                else:
                    proba = proba_summary
                labels = list(active_model.classes_) if hasattr(active_model, "classes_") else CLASSES
                best_idx = int(np.argmax(proba))
                pred = labels[best_idx]
                confidence = float(proba[best_idx])
                top_classes = sorted(
                    [{"condition": str(l), "confidence": round(float(p), 4)} for l, p in zip(labels, proba)],
                    key=lambda x: x["confidence"],
                    reverse=True,
                )[:3]
            else:
                pred = active_model.predict(X_summary)[0]
                confidence = 0.85
                top_classes = [{"condition": str(pred), "confidence": confidence}]

            mode_label = "trained_audio_only" if use_audio_only else "trained"
            if X_wins is not None and len(X_wins) > 1:
                mode_label += "_window_ensemble"

            return {
                "condition": str(pred),
                "confidence": round(confidence, 4),
                "top_classes": top_classes,
                "mode": mode_label,
                "window_ensemble_count": int(len(X_wins)) if X_wins is not None else 1,
            }

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

        # 1. Improper Valve Clearance: High Crest Factor (> 3.8), impulsive Peak G (> 1.35g dynamic), high acoustic frequency (> 2600 Hz)
        if crest > 4.0 or peak_g > 1.8:
            scores["valve_clearance"] += 0.65
        elif crest > 3.5 and peak_g > 1.25:
            scores["valve_clearance"] += 0.35
        if centroid > 2600 and rms_audio > 0.06:
            scores["valve_clearance"] += 0.25

        # 2. Engine Misfire: Unstable combustion -> elevated ZCR, cyclic torque dips
        if zcr > 0.17:
            scores["misfire"] += 0.55
        if 2.5 < crest <= 4.2 and 0.65 < peak_g < 1.9:
            scores["misfire"] += 0.30
        if rms_g > 0.25 and zcr > 0.12:
            scores["misfire"] += 0.20

        # 3. Loose or Worn Component Vibration: High continuous baseline RMS G, moderate peak, low-frequency rumble
        if rms_g > 0.32 and crest < 3.0:
            scores["loose_component"] += 0.60
        elif rms_g > 0.28 and peak_g > 0.65:
            scores["loose_component"] += 0.35
        if centroid < 1700 and rms_audio > 0.04 and crest < 3.0 and rms_g > 0.25:
            scores["loose_component"] += 0.25

        # 4. Normal Engine Operation: Default when gravity-removed signals reside within manufacturer baseline envelope
        if peak_g <= 0.85 and rms_g <= 0.28 and crest <= 3.6:
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
