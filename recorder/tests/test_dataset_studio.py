"""
Automated tests for HAVEN AI Graphical Dataset Recorder & ML Studio (`dataset_studio.py`).
Covers:
  - ESP32 115200 -> 921600 baud `LOGGER` serial handshake
  - Dataset scanning, readiness tracking, and failed-recording quarantine
  - Auto-switching between Hybrid training (partial collection) and GroupKFold Real-Only training
  - `EngineClassifier` automatic mtime hot-reload when `model.pkl` changes on disk
"""

from __future__ import annotations

import csv
import json
import os
import pathlib
import shutil
import sys
import time
from typing import List, Optional

import numpy as np
import soundfile as sf

_RECORDER_DIR = pathlib.Path(__file__).resolve().parent.parent
_REPO_ROOT = _RECORDER_DIR.parent
_ML_BACKEND_DIR = _REPO_ROOT / "ml_backend"

for _p in (_RECORDER_DIR, _ML_BACKEND_DIR):
    if str(_p) not in sys.path:
        sys.path.insert(0, str(_p))

from dataset_studio import DatasetStudioController  # noqa: E402
from record_session import build_output_dir, ensure_esp32_logger_mode  # noqa: E402


class FakeEsp32Serial:
    """Simulates an ESP32 booting at 115200 baud and switching to 921600 on `LOGGER`."""

    def __init__(self, initial_mode: str = "normal") -> None:
        self.baudrate: int = 921600
        self.mode: str = initial_mode
        self.writes: List[tuple[int, bytes]] = []
        self._sample_idx: int = 0

    def reset_input_buffer(self) -> None:
        pass

    def flush(self) -> None:
        pass

    def write(self, data: bytes) -> int:
        self.writes.append((self.baudrate, data))
        if self.baudrate == 115200 and b"LOGGER" in data.upper():
            self.mode = "logger"
        return len(data)

    def readline(self) -> bytes:
        if self.mode == "logger" and self.baudrate == 921600:
            if self._sample_idx == 0:
                self._sample_idx += 1
                return b"# HAVEN_LOGGER_V1 SENSOR=MPU6050 RANGE_G=8 ACHIEVED_RATE_HZ=1000.0\n"
            t_us = 1_000_000 + self._sample_idx * 1000
            self._sample_idx += 1
            return f"{t_us},0.02000,-0.01000,1.00500\n".encode("utf-8")
        time.sleep(0.02)
        return b""


def _write_recording(
    root: pathlib.Path,
    *,
    session_id: str,
    vehicle_id: str,
    condition: str,
    ts: str,
    duration_sec: float = 3.5,
    valid: bool = True,
) -> pathlib.Path:
    rec_dir = build_output_dir(root, session_id, vehicle_id, condition, ts)
    rec_dir.mkdir(parents=True, exist_ok=True)

    audio_sr = 44100
    n_aud = int(duration_sec * audio_sr)
    t_aud = np.arange(n_aud, dtype=np.float64) / audio_sr
    freq_map = {
        "normal": 110.0,
        "misfire": 75.0,
        "loose_component": 55.0,
        "valve_clearance": 950.0,
    }
    f0 = freq_map.get(condition, 120.0)
    amp = 0.0 if not valid else 0.25  # silent audio fails validation when valid=False
    sig = amp * np.sin(2.0 * np.pi * f0 * t_aud)
    audio_i16 = np.clip(np.round(sig * 32767.0), -32768, 32767).astype(np.int16)
    sf.write(str(rec_dir / "audio.wav"), audio_i16, audio_sr, subtype="PCM_16")

    vib_sr = 500
    n_vib = int(duration_sec * vib_sr)
    t_us = 1_000_000 + np.arange(n_vib, dtype=np.int64) * 2000
    t_s = (t_us - t_us[0]) * 1e-6
    vib_amp = {"normal": 0.12, "misfire": 0.45, "loose_component": 0.55, "valve_clearance": 0.85}.get(
        condition, 0.2
    )
    ax = vib_amp * np.sin(2.0 * np.pi * 28.0 * t_s)
    ay = 0.5 * vib_amp * np.cos(2.0 * np.pi * 28.0 * t_s)
    az = 1.0 + vib_amp * np.sin(2.0 * np.pi * 56.0 * t_s)

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
        "operator": "TestOp",
        "sensor_position": "Valve Cover",
        "mic_position": "25cm Front",
        "audio_sample_rate_hz": audio_sr,
        "vibration_sensor_range_g": 16.0,
    }
    (rec_dir / "metadata.json").write_text(json.dumps(meta, indent=2), encoding="utf-8")
    return rec_dir


def test_esp32_logger_baud_negotiation() -> None:
    fake_ser = FakeEsp32Serial(initial_mode="normal")
    headers = ensure_esp32_logger_mode(fake_ser, target_baud=921600, control_baud=115200)

    assert fake_ser.mode == "logger"
    assert fake_ser.baudrate == 921600
    assert any(b == 115200 and b"LOGGER" in payload for b, payload in fake_ser.writes)
    assert any("HAVEN_LOGGER_V1" in h for h in headers)


def test_scan_quarantine_and_auto_training_modes(tmp_path: pathlib.Path) -> None:
    rec_root = tmp_path / "recordings"
    quar_root = tmp_path / "quarantine"
    ml_dir = tmp_path / "ml_out"
    ml_dir.mkdir(parents=True, exist_ok=True)

    ctrl = DatasetStudioController(
        recordings_root=rec_root,
        quarantine_root=quar_root,
        ml_backend_dir=ml_dir,
    )

    # 1. Partial collection (1 vehicle, 1 valid + 1 invalid recording)
    _write_recording(
        rec_root,
        session_id="S1",
        vehicle_id="car_a",
        condition="normal",
        ts="20261107T100000Z",
        valid=True,
    )
    bad_dir = _write_recording(
        rec_root,
        session_id="S1",
        vehicle_id="car_a",
        condition="misfire",
        ts="20261107T100500Z",
        valid=False,
    )

    scan1 = ctrl.scan_dataset()
    assert scan1["valid_count"] == 1
    assert scan1["invalid_count"] == 1
    assert scan1["ready_for_grouped_eval"] is False

    # Quarantine the invalid take
    q_dest = ctrl.quarantine_recording(bad_dir)
    assert q_dest.exists()
    assert not bad_dir.exists()

    # Auto training with partial dataset should succeed via Hybrid mode
    res_hybrid = ctrl.train_from_recordings(mode="auto", synthetic_per_class=30)
    assert res_hybrid["mode_used"] == "hybrid_calibrated"
    assert res_hybrid["real_windows"] >= 2
    assert (ml_dir / "model.pkl").exists()
    assert ctrl.get_active_model_summary()["loaded"] is True

    # 2. Complete collection (>= 2 vehicles & all 4 classes) unlocks GroupKFold Real-Only mode
    for v_idx, veh in enumerate(("car_a", "car_b"), start=1):
        for c_idx, cond in enumerate(("normal", "misfire", "loose_component", "valve_clearance")):
            if veh == "car_a" and cond == "normal":
                continue
            _write_recording(
                rec_root,
                session_id=f"S{v_idx}",
                vehicle_id=veh,
                condition=cond,
                ts=f"20261107T11{v_idx}{c_idx}00Z",
                valid=True,
            )

    scan2 = ctrl.scan_dataset()
    assert scan2["ready_for_grouped_eval"] is True
    assert scan2["invalid_count"] == 0

    res_grouped = ctrl.train_from_recordings(mode="auto")
    assert res_grouped["mode_used"] == "grouped_real_only"
    assert res_grouped["synthetic_windows"] == 0
    assert (ml_dir / "evaluation_report.json").exists()


def test_classifier_hot_reloads_updated_model_on_disk(tmp_path: pathlib.Path, monkeypatch: Optional[object] = None) -> None:
    import classifier as clf_mod
    from train import load_hybrid_dataset, train_and_export

    temp_model = tmp_path / "model.pkl"
    X1, y1, rc1, sc1 = load_hybrid_dataset([], augment_synthetic=True, synthetic_per_class=25)
    train_and_export(X1, y1, model_path=str(temp_model), real_count=rc1, synth_count=sc1)

    orig_path = clf_mod.MODEL_PATH
    try:
        clf_mod.MODEL_PATH = str(temp_model)
        engine = clf_mod.EngineClassifier()
        first_trained_on = engine.model_info()["meta"]["trained_on"]

        # Simulate a newer model written by Dataset Studio
        time.sleep(0.05)
        X2, y2, rc2, sc2 = load_hybrid_dataset([], augment_synthetic=True, synthetic_per_class=30)
        train_and_export(X2, y2, model_path=str(temp_model), real_count=rc2, synth_count=sc2)
        os.utime(temp_model, (time.time() + 2.0, time.time() + 2.0))

        # Without manually calling reload_model(), model_info() should hot-reload automatically
        second_info = engine.model_info()
        assert second_info["meta"]["trained_on"] != first_trained_on
        assert second_info["meta"]["n_samples"] == 120
    finally:
        clf_mod.MODEL_PATH = orig_path

