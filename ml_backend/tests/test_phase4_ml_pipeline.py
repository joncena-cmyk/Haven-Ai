"""
Pytest suite for HAVEN AI Phase 4 ML Pipeline Fixes.
Verifies:
  1. Known sine wave + static 1g gravity offset yields expected peak_g, rms_g,
     crest_factor (~sqrt(2)), dominant_freq_hz, and real inferred sample_rate_hz (1000 Hz).
  2. Known impulse train yields expected high crest_factor and peak_g.
  3. Window segmentation (1.5 s, 50% overlap) produces expected window counts.
  4. Contextual features (temperature_c, rpm, voltage_v, current_ma) are excluded from
     FEATURE_COLS, missing features become NaN (not 0.0), and all-missing raises ValueError.
  5. Grouped evaluation (GroupKFold) fails loudly when < 2 vehicles or < 4 classes,
     and outputs accuracy, per-class precision/recall/F1, and 4x4 confusion matrix
     when >= 2 vehicles and 4 classes are provided.
  6. /analyze and /train endpoints enforce X-ML-API-Secret and do not invent RPM.
"""

from __future__ import annotations

import csv
import json
import math
import os
import pathlib
import sys
from typing import Dict

import numpy as np
import pytest
import soundfile as sf

_ML_DIR = pathlib.Path(__file__).resolve().parent.parent
if str(_ML_DIR) not in sys.path:
    sys.path.insert(0, str(_ML_DIR))

from features import (  # noqa: E402
    bandpass_filter_signal,
    compute_vibration_window_metrics,
    extract_vibration_features,
    segment_windows,
)
from train import (  # noqa: E402
    CLASSES,
    CONTEXT_METADATA_COLS,
    FEATURE_COLS,
    build_feature_vector,
    evaluate_and_train_grouped,
    load_recordings_dataset,
)


def test_known_sine_wave_with_dc_gravity_offset_gives_expected_values(tmp_path: pathlib.Path) -> None:
    """
    A 1000 Hz 3-axis signal with:
      - ax = 0.50 * sin(2*pi*40*t)
      - ay = 0.0
      - az = 1.00 (static 1g gravity offset)
    must have DC offset removed so:
      - sample_rate_hz == 1000.0 (inferred from t_us, NOT hardcoded 100 Hz)
      - peak_g == 0.50
      - rms_g == 0.50 / sqrt(2) ~= 0.3536
      - crest_factor == sqrt(2) ~= 1.4142
      - dominant_freq_hz == 40.0 Hz
    """
    sr = 1000.0
    duration_s = 3.0
    n = int(sr * duration_s)
    t_us = np.arange(n, dtype=np.int64) * 1000  # 1000 us step -> 1000 Hz
    t_s = np.arange(n, dtype=np.float64) / sr

    amp = 0.50
    freq_hz = 40.0
    ax = amp * np.sin(2.0 * np.pi * freq_hz * t_s)
    ay = np.zeros_like(t_s)
    az = np.ones_like(t_s) * 1.00  # +1.0g gravity offset

    csv_path = tmp_path / "sine_1000hz.csv"
    with csv_path.open("w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["t_us", "ax", "ay", "az"])
        for i in range(n):
            writer.writerow([int(t_us[i]), f"{ax[i]:.6f}", f"{ay[i]:.6f}", f"{az[i]:.6f}"])

    feats = extract_vibration_features(str(csv_path), ext="csv", window_sec=1.5, overlap=0.5)

    assert feats["sample_rate_hz"] == pytest.approx(1000.0, rel=1e-3)
    assert feats["window_count"] == 3  # 3.0s with 1.5s window & 0.5 overlap -> [0..1.5, 0.75..2.25, 1.5..3.0]
    assert feats["peak_g"] == pytest.approx(amp, rel=0.03)
    assert feats["rms_g"] == pytest.approx(amp / math.sqrt(2.0), rel=0.03)
    assert feats["crest_factor"] == pytest.approx(math.sqrt(2.0), rel=0.03)
    assert feats["dominant_freq_hz"] == pytest.approx(freq_hz, abs=1.0)


def test_known_impulse_train_increases_crest_factor_and_peak_g() -> None:
    """
    Adding periodic sharp mechanical impulses (simulating valve lash tapping) to a
    low-amplitude baseline sine wave must yield high peak_g and high crest_factor (> 4.5).
    """
    sr = 1000.0
    n = 1500  # 1.5 s window
    t_s = np.arange(n, dtype=np.float64) / sr

    ax = 0.10 * np.sin(2.0 * np.pi * 25.0 * t_s)
    ay = np.zeros(n, dtype=np.float64)
    az = np.ones(n, dtype=np.float64) * 0.98  # static gravity

    # Inject sharp 3.2g impulses every 100 ms (10 Hz tap rate)
    for idx in range(50, n, 100):
        az[idx] += 3.20

    xyz = np.column_stack([ax, ay, az])
    metrics = compute_vibration_window_metrics(xyz, sample_rate_hz=sr)

    assert metrics["peak_g"] > 3.0
    assert metrics["crest_factor"] > 4.5
    assert metrics["kurtosis"] > 10.0


def test_bandpass_filter_rejects_low_frequency_drift_and_high_frequency_noise() -> None:
    """
    Verify bandpass_filter_signal attenuates sub-lowcut (0.4 Hz) chassis drift
    and above-highcut (450 Hz) sensor noise while preserving in-band (40 Hz)
    engine vibration.
    """
    sr = 1000.0
    t = np.arange(int(sr * 4.0), dtype=np.float64) / sr
    in_band = 0.35 * np.sin(2.0 * np.pi * 40.0 * t)
    low_drift = 0.80 * np.sin(2.0 * np.pi * 0.4 * t)
    high_noise = 0.40 * np.sin(2.0 * np.pi * 460.0 * t)
    composite = (in_band + low_drift + high_noise)[:, np.newaxis]

    filtered = bandpass_filter_signal(
        composite,
        sample_rate_hz=sr,
        lowcut_hz=2.0,
        highcut_hz=350.0,
    )
    # Trim 0.25s edges and verify filtered signal matches in_band closely
    mid = slice(250, -250)
    residual_rms = float(np.sqrt(np.mean((filtered[mid, 0] - in_band[mid]) ** 2)))
    assert residual_rms < 0.03
    assert float(np.max(np.abs(filtered[mid, 0]))) == pytest.approx(0.35, rel=0.05)


def test_window_segmentation_overlap_and_Feature_cols_scope() -> None:
    sig = np.zeros((4500, 3), dtype=np.float64)
    wins = segment_windows(sig, sample_rate_hz=1000.0, window_sec=1.5, overlap=0.5)
    assert len(wins) == 5
    assert all(w.shape == (1500, 3) for w in wins)

    # Verify contextual features are NOT in FEATURE_COLS
    for ctx_col in CONTEXT_METADATA_COLS:
        assert ctx_col not in FEATURE_COLS, f"Contextual feature '{ctx_col}' must not be in FEATURE_COLS"

    # Verify missing features become NaN (not 0.0) and all-missing raises ValueError
    partial_vec = build_feature_vector({"peak_g": 1.5, "rms_g": 0.3})
    assert partial_vec[0] == pytest.approx(1.5)
    assert partial_vec[1] == pytest.approx(0.3)
    assert partial_vec[2] == pytest.approx(5.0)  # crest_factor derived from peak_g / rms_g
    assert all(math.isnan(v) for v in partial_vec[3:])

    with pytest.raises(ValueError, match="all sound and vibration features are missing"):
        build_feature_vector({"temperature_c": 90.0, "rpm": 850})


def test_grouped_evaluation_fails_loudly_on_insufficient_vehicles_or_classes(tmp_path: pathlib.Path) -> None:
    rng = np.random.default_rng(123)
    X = rng.normal(size=(40, len(FEATURE_COLS)))
    y_all_4 = np.array(CLASSES * 10, dtype=object)

    # Case 1: Only 1 vehicle -> must fail loudly
    vehicles_one = np.array(["veh_1"] * 40, dtype=object)
    groups_one = np.array([f"veh_1::sess_{i % 4}" for i in range(40)], dtype=object)
    with pytest.raises(ValueError, match="at least 2 distinct vehicles"):
        evaluate_and_train_grouped(
            X, y_all_4, groups_one, vehicles_one, report_path=None, model_path=None
        )

    # Case 2: 2 vehicles, but only 3 classes -> must fail loudly
    y_only_3 = np.array(["normal", "misfire", "loose_component"] * 13 + ["normal"], dtype=object)
    vehicles_two = np.array(["veh_1", "veh_2"] * 20, dtype=object)
    groups_two = np.array([f"veh_{(i % 2) + 1}::sess_{i % 4}" for i in range(40)], dtype=object)
    with pytest.raises(ValueError, match="all 4 canonical engine classes"):
        evaluate_and_train_grouped(
            X, y_only_3, groups_two, vehicles_two, report_path=None, model_path=None
        )


def _write_synthetic_recording_folder(
    root: pathlib.Path,
    *,
    session_id: str,
    vehicle_id: str,
    condition: str,
    vib_freq_hz: float,
    vib_amp_g: float,
    audio_freq_hz: float,
    audio_amp: float,
) -> pathlib.Path:
    rec_dir = root / session_id / vehicle_id / condition / "20261105T120000Z"
    rec_dir.mkdir(parents=True, exist_ok=True)

    duration_sec = 3.0
    audio_sr = 44100
    vib_sr = 1000

    # Audio WAV
    n_aud = int(duration_sec * audio_sr)
    t_aud = np.arange(n_aud, dtype=np.float64) / audio_sr
    aud_sig = audio_amp * np.sin(2.0 * np.pi * audio_freq_hz * t_aud)
    aud_i16 = np.clip(np.round(aud_sig * 32767.0), -32768, 32767).astype(np.int16)
    sf.write(str(rec_dir / "audio.wav"), aud_i16, audio_sr, subtype="PCM_16")

    # Vibration CSV
    n_vib = int(duration_sec * vib_sr)
    t_us = np.arange(n_vib, dtype=np.int64) * 1000 + 1_000_000
    t_vib = np.arange(n_vib, dtype=np.float64) / vib_sr
    ax = vib_amp_g * np.sin(2.0 * np.pi * vib_freq_hz * t_vib)
    ay = 0.5 * vib_amp_g * np.cos(2.0 * np.pi * vib_freq_hz * t_vib)
    az = 1.0 + 0.2 * vib_amp_g * np.sin(2.0 * np.pi * (2.0 * vib_freq_hz) * t_vib)

    if condition == "valve_clearance":
        for idx in range(50, n_vib, 80):
            ax[idx] += 3.2

    with (rec_dir / "vibration.csv").open("w", newline="", encoding="utf-8") as f_csv:
        writer = csv.writer(f_csv)
        writer.writerow(["t_us", "ax", "ay", "az"])
        for i in range(n_vib):
            writer.writerow([int(t_us[i]), f"{ax[i]:.5f}", f"{ay[i]:.5f}", f"{az[i]:.5f}"])

    meta = {
        "session_id": session_id,
        "vehicle_id": vehicle_id,
        "condition": condition,
        "rpm_target": 850,
        "duration_target_sec": duration_sec,
        "operator": "Anton",
        "sensor_position": "Cylinder head",
        "mic_position": "15cm above valve cover",
        "audio_sample_rate_hz": audio_sr,
        "vibration_sensor_range_g": 16.0,
    }
    with (rec_dir / "metadata.json").open("w", encoding="utf-8") as f_meta:
        json.dump(meta, f_meta, indent=2)

    return rec_dir


def test_grouped_evaluation_end_to_end_on_validated_recording_folders(tmp_path: pathlib.Path) -> None:
    """
    Create 2 distinct vehicles x 4 classes (8 validated recording folders),
    load windowed features with `load_recordings_dataset`, run `evaluate_and_train_grouped`,
    and verify report output contains accuracy, per_class_metrics, and 4x4 confusion_matrix.
    """
    class_profiles: Dict[str, Dict[str, float]] = {
        "normal": {"vib_f": 14.0, "vib_a": 0.18, "aud_f": 220.0, "aud_a": 0.12},
        "misfire": {"vib_f": 9.0, "vib_a": 0.65, "aud_f": 680.0, "aud_a": 0.22},
        "loose_component": {"vib_f": 38.0, "vib_a": 0.85, "aud_f": 140.0, "aud_a": 0.18},
        "valve_clearance": {"vib_f": 24.0, "vib_a": 0.35, "aud_f": 3200.0, "aud_a": 0.30},
    }

    rec_root = tmp_path / "recordings"
    for veh_idx, veh_id in enumerate(["vios_2021", "civic_2022"], start=1):
        for cond, prof in class_profiles.items():
            _write_synthetic_recording_folder(
                rec_root,
                session_id=f"SESS-V{veh_idx}-{cond}",
                vehicle_id=veh_id,
                condition=cond,
                vib_freq_hz=prof["vib_f"] + (veh_idx * 0.5),
                vib_amp_g=prof["vib_a"],
                audio_freq_hz=prof["aud_f"],
                audio_amp=prof["aud_a"],
            )

    X, y, groups, vehicles, win_meta = load_recordings_dataset(rec_root, window_sec=1.5, overlap=0.5)
    assert X.shape == (24, len(FEATURE_COLS))  # 8 recordings * 3 windows each = 24 windows
    assert set(vehicles) == {"vios_2021", "civic_2022"}
    assert set(y) == set(CLASSES)

    report_file = tmp_path / "eval_report.json"
    model_file = tmp_path / "model.pkl"
    meta = evaluate_and_train_grouped(
        X,
        y,
        groups,
        vehicles,
        report_path=report_file,
        model_path=model_file,
        group_by="vehicle",
    )

    assert report_file.exists()
    assert model_file.exists()
    saved_report = json.loads(report_file.read_text(encoding="utf-8"))
    assert "accuracy" in saved_report
    assert "per_class_metrics" in saved_report
    assert set(saved_report["per_class_metrics"].keys()) == set(CLASSES)
    assert len(saved_report["confusion_matrix"]) == 4
    assert all(len(row) == 4 for row in saved_report["confusion_matrix"])
    assert saved_report["n_vehicles"] == 2
    assert meta["accuracy"] >= 0.75


def test_ml_api_secret_protection_and_no_invented_rpm(monkeypatch: pytest.MonkeyPatch, tmp_path: pathlib.Path) -> None:
    """
    Verify /analyze and /train reject requests without a valid X-ML-API-Secret header (401)
    and that /analyze does not invent RPM when omitted.
    """
    import asyncio
    from fastapi import HTTPException
    from starlette.datastructures import UploadFile as StarletteUploadFile
    import main as ml_main

    monkeypatch.setenv("ML_API_SECRET", "test-phase4-shared-secret")

    # 1. Missing or wrong secret raises 401
    with pytest.raises(HTTPException) as exc_info:
        ml_main.verify_ml_api_secret(None)
    assert exc_info.value.status_code == 401

    with pytest.raises(HTTPException) as exc_info2:
        ml_main.verify_ml_api_secret("wrong-secret")
    assert exc_info2.value.status_code == 401

    # Valid secret passes without raising
    ml_main.verify_ml_api_secret("test-phase4-shared-secret")

    # 2. Verify /analyze does not invent RPM when rpm=None
    csv_path = tmp_path / "vib.csv"
    with csv_path.open("w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["t_us", "ax", "ay", "az"])
        for i in range(200):
            writer.writerow([i * 1000, "0.20", "0.05", "1.00"])

    with csv_path.open("rb") as f_rb:
        upload = StarletteUploadFile(filename="vib.csv", file=f_rb)
        resp = asyncio.run(
            ml_main.analyze(
                audio_file=None,
                vibration_file=upload,
                diagnostic_id=99,
                vehicle_id=1,
                temperature_c=None,
                rpm=None,
                voltage_v=None,
                current_ma=None,
                sample_rate_hz=None,
                recording_mode="revving",
                vibration_source="adxl345_live",
            )
        )

    payload = json.loads(resp.body.decode("utf-8"))
    assert payload["context_metadata"]["rpm"] is None, "RPM must not be invented when omitted"
    assert payload["vibration_features"]["sample_rate_hz"] == pytest.approx(1000.0, rel=1e-2)


def test_audio_only_submodel_and_window_ensemble_inference(tmp_path: pathlib.Path, monkeypatch: pytest.MonkeyPatch) -> None:
    """
    Verify that training exports both `model` (14 features) and `audio_model` (9 acoustic features),
    that Audio-Only diagnostics use `trained_audio_only` without vibration bias, and that
    multi-window inputs trigger `_window_ensemble` probability voting.
    """
    import pickle
    import classifier as clf_mod
    from train import generate_synthetic_samples, train_and_export

    X, y = generate_synthetic_samples(n_per_class=40)
    tmp_model_path = str(tmp_path / "model.pkl")
    train_and_export(X, y, real_count=0, synth_count=len(X), model_path=tmp_model_path)

    with open(tmp_model_path, "rb") as f:
        saved = pickle.load(f)
    assert "model" in saved and saved["model"] is not None
    assert "audio_model" in saved and saved["audio_model"] is not None

    monkeypatch.setattr(clf_mod, "MODEL_PATH", tmp_model_path)
    ec = clf_mod.EngineClassifier()

    # 1. Audio-only misfire signature (no vibration features, vibration_source="skipped")
    audio_misfire = {
        "vibration_source": "skipped",
        "rms_mean": 0.12,
        "zcr_mean": 0.26,
        "spectral_centroid_mean": 2900.0,
        "spectral_rolloff_mean": 4800.0,
        "mfcc_1_mean": -205.0,
        "mfcc_2_mean": 25.0,
        "mfcc_3_mean": -5.0,
        "mfcc_4_mean": 2.0,
        "mfcc_5_mean": -4.0,
        "_window_features": [
            {
                "rms_mean": 0.12,
                "zcr_mean": 0.27,
                "spectral_centroid_mean": 2950.0,
                "spectral_rolloff_mean": 4850.0,
                "mfcc_1_mean": -205.0,
                "mfcc_2_mean": 25.0,
                "mfcc_3_mean": -5.0,
                "mfcc_4_mean": 2.0,
                "mfcc_5_mean": -4.0,
            },
            {
                "rms_mean": 0.11,
                "zcr_mean": 0.25,
                "spectral_centroid_mean": 2850.0,
                "spectral_rolloff_mean": 4750.0,
                "mfcc_1_mean": -205.0,
                "mfcc_2_mean": 25.0,
                "mfcc_3_mean": -5.0,
                "mfcc_4_mean": 2.0,
                "mfcc_5_mean": -4.0,
            },
        ],
    }
    res = ec.predict(audio_misfire)
    assert res["mode"] == "trained_audio_only_window_ensemble"
    assert res["window_ensemble_count"] == 2
    assert res["condition"] == "misfire"
    assert len(res["feature_contributions"]) >= 3
    assert sum(c["impact_pct"] for c in res["feature_contributions"]) == pytest.approx(100.0, abs=0.5)
    for c in res["feature_contributions"]:
        assert c["feature"] not in CONTEXT_METADATA_COLS

    # 2. Borderline low-amplitude fault still produces non-empty XAI contributions
    for cls in CLASSES:
        xai = ec.explain_prediction({"peak_g": 0.12, "rms_g": 0.05, "zcr_mean": 0.05}, cls)
        assert len(xai) >= 3
        assert sum(item["impact_pct"] for item in xai) == pytest.approx(100.0, abs=0.5)

    # 3. Isolated clap in silent room (rms_std >> rms_mean, no vibration/telemetry) is rejected as ambient_noise
    clap_noise = {
        "vibration_source": "skipped",
        "rms_mean": 0.09,
        "rms_std": 0.30,
        "spectral_centroid_mean": 2200.0,
        "spectral_rolloff_mean": 4200.0,
    }
    clap_res = ec.predict(clap_noise)
    assert clap_res["condition"] == "ambient_noise"

    # 4. Vibration-only inference uses dedicated 5-feature `vibration_model` (BUG-07)
    assert "vibration_model" in saved and saved["vibration_model"] is not None
    vib_only_valve = {
        "vibration_source": "adxl345_live",
        "peak_g": 2.15,
        "rms_g": 0.46,
        "crest_factor": 4.67,
        "dominant_freq_hz": 24.0,
        "spectral_entropy": 0.42,
    }
    vib_res = ec.predict(vib_only_valve)
    assert vib_res["mode"] == "trained_vibration_only"
    assert vib_res["condition"] == "valve_clearance"

    # 5. Quiet healthy idle audio (rms_audio = 0.024) is classified as normal, not ambient_noise (BUG-10)
    quiet_idle_audio = {
        "vibration_source": "skipped",
        "rms_mean": 0.024,
        "rms_std": 0.004,
        "zcr_mean": 0.08,
        "spectral_centroid_mean": 1050.0,
        "spectral_rolloff_mean": 2100.0,
        "spectral_bandwidth_mean": 1100.0,
        "mfcc_1_mean": -250.0,
        "mfcc_2_mean": 82.0,
        "mfcc_3_mean": -14.0,
        "mfcc_4_mean": 24.0,
    }
    quiet_res = ec.predict(quiet_idle_audio)
    assert quiet_res["condition"] == "normal"


def test_bug_audit_regressions_multivehicle_train_nan_json_and_range_g8(
    tmp_path: pathlib.Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    """
    Regression coverage for BUG-03 (multi-vehicle /train NameError), BUG-06 (empty vibration CSV NaN JSON crash),
    BUG-09 (firmware RANGE_G=8 clipping check), and BUG-12 (timestamp-less JSON 100 Hz fallback).
    """
    import asyncio
    from starlette.datastructures import UploadFile as StarletteUploadFile
    import main as ml_main
    from features import extract_vibration_features
    from record_session import parse_firmware_range_g
    from validate_recording import validate_recording_folder

    monkeypatch.setenv("ML_API_SECRET", "test-audit-secret")

    # 1. BUG-03: Multi-vehicle POST /train (>= 2 vehicle groups) succeeds without NameError
    tmp_model_path = tmp_path / "trained_api_model.pkl"
    monkeypatch.setattr(ml_main.classifier, "reload_model", lambda: True)
    records = [
        {
            "vehicle_id": "1",
            "session_id": "S1",
            "true_condition": "normal",
            "peak_g": 0.25,
            "rms_g": 0.11,
            "crest_factor": 2.27,
            "dominant_freq_hz": 14.0,
            "spectral_entropy": 0.28,
        },
        {
            "vehicle_id": "2",
            "session_id": "S2",
            "true_condition": "valve_clearance",
            "peak_g": 2.10,
            "rms_g": 0.44,
            "crest_factor": 4.77,
            "dominant_freq_hz": 24.0,
            "spectral_entropy": 0.45,
        },
    ]
    import train as train_mod

    orig_train_export = train_mod.train_and_export

    def _redirected_train_export(*args, **kwargs):
        kwargs["model_path"] = str(tmp_model_path)
        return orig_train_export(*args, **kwargs)

    monkeypatch.setattr(train_mod, "train_and_export", _redirected_train_export)
    train_resp = asyncio.run(
        ml_main.retrain_model(
            {
                "records": records,
                "augment_synthetic": True,
                "synthetic_per_class": 25,
            }
        )
    )
    train_json = json.loads(train_resp.body.decode("utf-8"))
    assert train_json["success"] is True
    assert train_json["meta"]["real_samples_count"] == 2

    # 2. BUG-06: Empty vibration CSV (0 rows -> NaN) + valid WAV audio returns valid JSON (no 500 ValueError)
    wav_path = tmp_path / "valid.wav"
    sr = 16000
    t = np.arange(sr * 2, dtype=np.float64) / sr
    sig = 0.15 * np.sin(2.0 * np.pi * 120.0 * t)
    sf.write(str(wav_path), (sig * 32767.0).astype(np.int16), sr, subtype="PCM_16")

    empty_csv = tmp_path / "empty_vib.csv"
    empty_csv.write_text("t_us,ax,ay,az\n", encoding="utf-8")

    with wav_path.open("rb") as f_aud, empty_csv.open("rb") as f_vib:
        aud_up = StarletteUploadFile(filename="valid.wav", file=f_aud)
        vib_up = StarletteUploadFile(filename="empty_vib.csv", file=f_vib)
        analyze_resp = asyncio.run(
            ml_main.analyze(
                audio_file=aud_up,
                vibration_file=vib_up,
                diagnostic_id=101,
                vehicle_id=1,
                temperature_c=None,
                rpm=None,
                voltage_v=None,
                current_ma=None,
                sample_rate_hz=None,
                recording_mode="idle",
                vibration_source="adxl345_live",
            )
        )
    analyze_json = json.loads(analyze_resp.body.decode("utf-8"))
    assert analyze_resp.status_code == 200
    assert analyze_json["vibration_features"]["peak_g"] is None

    # 3. BUG-12: Timestamp-less JSON samples without sample_rate_hz default to 100.0 Hz
    json_vib = tmp_path / "esp32_samples.json"
    samples_3d = [
        {"x": 0.25 * np.sin(2.0 * np.pi * 15.0 * (i / 100.0)), "y": 0.0, "z": 1.0}
        for i in range(128)
    ]
    json_vib.write_text(json.dumps(samples_3d), encoding="utf-8")
    jf = extract_vibration_features(str(json_vib), ext="json", sample_rate_hz=None)
    assert jf["sample_rate_hz"] == pytest.approx(100.0)
    assert jf["peak_g"] == pytest.approx(0.25, abs=0.03)

    # 4. BUG-09: Firmware header RANGE_G=8 is parsed and enforces ±8g clipping in validator
    assert parse_firmware_range_g(["# HAVEN_LOGGER_V1 SENSOR=MPU6050 RANGE_G=8"]) == 8.0
    clip_dir = _write_synthetic_recording_folder(
        tmp_path / "rec_clip",
        session_id="S-CLIP",
        vehicle_id="car1",
        condition="normal",
        vib_freq_hz=20.0,
        vib_amp_g=7.95,  # Clips ±8g (98% of 8g = 7.84g), would NOT clip ±16g!
        audio_freq_hz=150.0,
        audio_amp=0.15,
    )
    meta_file = clip_dir / "metadata.json"
    meta_obj = json.loads(meta_file.read_text(encoding="utf-8"))
    meta_obj["vibration_sensor_range_g"] = 16.0  # Legacy stale default
    meta_obj["firmware_headers"] = ["# HAVEN_LOGGER_V1 SENSOR=MPU6050 RANGE_G=8 ACHIEVED_RATE_HZ=1000.0"]
    meta_file.write_text(json.dumps(meta_obj, indent=2), encoding="utf-8")

    clip_val = validate_recording_folder(clip_dir)
    assert clip_val.valid is False
    assert any("±8.0g" in err or "±8g" in err for err in clip_val.errors)




