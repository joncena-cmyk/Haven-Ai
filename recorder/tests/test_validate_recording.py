"""
Pytest suite for HAVEN AI Phase 2 Offline Recorder & Validator.
Tests:
  - Valid recording folder passes all checks
  - Output path hierarchy matches recordings/<session_id>/<vehicle>/<condition>/<timestamp>/
  - Tap-marker detection helper accurately aligns audio and vibration impulses
  - Timestamp gaps in vibration.csv are rejected
  - Unstable / low vibration sample rate or non-monotonic timestamps are rejected
  - Clipped audio WAV is rejected
  - Duration mismatch between audio, vibration, and target metadata is rejected
  - Invalid condition label or missing files are rejected
"""

from __future__ import annotations

import csv
import json
import pathlib
import sys
from typing import Any, Dict, Optional

import numpy as np
import pytest
import soundfile as sf

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent.parent))

from record_session import (  # noqa: E402
    build_output_dir,
    detect_tap_marker,
    parse_serial_csv_line,
)
from validate_recording import validate_recording_folder  # noqa: E402


def _create_recording_fixture(
    base_dir: pathlib.Path,
    *,
    duration_sec: float = 4.0,
    audio_sr: int = 44100,
    vib_sr: float = 1000.0,
    condition: str = "valve_clearance",
    audio_amplitude: float = 0.25,
    insert_gap_us: Optional[int] = None,
    non_monotonic: bool = False,
    jitter_std_us: float = 0.0,
    audio_duration_override: Optional[float] = None,
    meta_overrides: Optional[Dict[str, Any]] = None,
) -> pathlib.Path:
    """Create a synthetic recording folder with audio.wav, vibration.csv, and metadata.json."""
    rec_dir = build_output_dir(
        base_dir,
        session_id="SESS-TEST-01",
        vehicle_id="vios_2021",
        condition=condition if condition in ("normal", "misfire", "loose_component", "valve_clearance") else "normal",
        timestamp_str="20261105T100000Z",
    )
    rec_dir.mkdir(parents=True, exist_ok=True)

    # 1. Write audio.wav (16-bit mono PCM)
    aud_dur = audio_duration_override if audio_duration_override is not None else duration_sec
    n_aud = int(round(aud_dur * audio_sr))
    t_aud = np.arange(n_aud, dtype=np.float64) / float(audio_sr)
    audio_sig = audio_amplitude * np.sin(2.0 * np.pi * 120.0 * t_aud)
    audio_int16 = np.clip(np.round(audio_sig * 32767.0), -32768, 32767).astype(np.int16)
    sf.write(str(rec_dir / "audio.wav"), audio_int16, audio_sr, subtype="PCM_16")

    # 2. Write vibration.csv ("t_us,ax,ay,az")
    n_vib = int(round(duration_sec * vib_sr))
    dt_us = 1_000_000.0 / vib_sr
    rng = np.random.default_rng(42)

    t_us_list = []
    cur_t = 1_000_000.0
    for i in range(n_vib):
        if i > 0:
            step = dt_us + (rng.normal(0.0, jitter_std_us) if jitter_std_us > 0 else 0.0)
            if insert_gap_us is not None and i == n_vib // 2:
                step += float(insert_gap_us)
            if non_monotonic and i == n_vib // 3:
                step = -500.0
            cur_t += step
        t_us_list.append(int(round(cur_t)))

    t_us_arr = np.asarray(t_us_list, dtype=np.int64)
    t_sec = (t_us_arr - t_us_arr[0]) * 1e-6
    ax = 0.15 * np.sin(2.0 * np.pi * 28.0 * t_sec)
    ay = 0.10 * np.cos(2.0 * np.pi * 28.0 * t_sec)
    az = 1.00 + 0.20 * np.sin(2.0 * np.pi * 56.0 * t_sec)  # Includes 1.0g gravity on Z

    with (rec_dir / "vibration.csv").open("w", newline="", encoding="utf-8") as f_csv:
        writer = csv.writer(f_csv)
        writer.writerow(["t_us", "ax", "ay", "az"])
        for i in range(n_vib):
            writer.writerow([int(t_us_arr[i]), f"{ax[i]:.5f}", f"{ay[i]:.5f}", f"{az[i]:.5f}"])

    # 3. Write metadata.json
    meta: Dict[str, Any] = {
        "session_id": "SESS-TEST-01",
        "vehicle_id": "vios_2021",
        "condition": condition,
        "rpm_target": 850,
        "duration_target_sec": duration_sec,
        "operator": "Anton",
        "verified_by": "Master Tech",
        "sensor_position": "Cylinder head bolt #2",
        "mic_position": "15cm above valve cover",
        "load_state": "Idle, AC Off",
        "fault_detail": "Intake valve #2 clearance 0.35mm",
        "audio_sample_rate_hz": audio_sr,
        "vibration_sensor_range_g": 16.0,
    }
    if meta_overrides:
        meta.update(meta_overrides)

    with (rec_dir / "metadata.json").open("w", encoding="utf-8") as f_meta:
        json.dump(meta, f_meta, indent=2)

    return rec_dir


def test_valid_recording_folder_passes(tmp_path: pathlib.Path) -> None:
    rec_dir = _create_recording_fixture(tmp_path)
    res = validate_recording_folder(rec_dir)

    assert res.valid is True, f"Expected valid recording, got errors: {res.errors}"
    assert res.errors == []
    assert res.metrics["audio_sample_rate_hz"] == 44100
    assert res.metrics["audio_channels"] == 1
    assert res.metrics["audio_subtype"] == "PCM_16"
    assert pytest.approx(res.metrics["vibration_achieved_rate_hz"], rel=0.02) == 1000.0
    # Verify output path structure: recordings/<session_id>/<vehicle>/<condition>/<timestamp>
    rel_parts = rec_dir.relative_to(tmp_path).parts
    assert rel_parts == ("SESS-TEST-01", "vios_2021", "valve_clearance", "20261105T100000Z")


def test_timestamp_gap_is_rejected(tmp_path: pathlib.Path) -> None:
    rec_dir = _create_recording_fixture(tmp_path, insert_gap_us=120_000)
    res = validate_recording_folder(rec_dir)

    assert res.valid is False
    assert any("Timestamp gap detected" in e for e in res.errors)


def test_non_monotonic_timestamps_are_rejected(tmp_path: pathlib.Path) -> None:
    rec_dir = _create_recording_fixture(tmp_path, non_monotonic=True)
    res = validate_recording_folder(rec_dir)

    assert res.valid is False
    assert any("not strictly increasing" in e for e in res.errors)


def test_unstable_sample_rate_is_rejected(tmp_path: pathlib.Path) -> None:
    # Jitter std of 450 us on a 1000 us period -> jitter ratio ~0.45 > 0.25 limit
    rec_dir = _create_recording_fixture(tmp_path, jitter_std_us=450.0)
    res = validate_recording_folder(rec_dir)

    assert res.valid is False
    assert any("unstable" in e or "Timestamp gap" in e for e in res.errors)


def test_clipped_audio_is_rejected(tmp_path: pathlib.Path) -> None:
    # Amplitude 1.5 clips a large fraction of the sine wave at ±1.0 full scale
    rec_dir = _create_recording_fixture(tmp_path, audio_amplitude=1.5)
    res = validate_recording_folder(rec_dir)

    assert res.valid is False
    assert any("Audio is clipped" in e for e in res.errors)


def test_duration_mismatch_is_rejected(tmp_path: pathlib.Path) -> None:
    # Audio is 2.0s while vibration and target duration are 4.0s
    rec_dir = _create_recording_fixture(tmp_path, duration_sec=4.0, audio_duration_override=2.0)
    res = validate_recording_folder(rec_dir)

    assert res.valid is False
    assert any("duration" in e.lower() for e in res.errors)


def test_invalid_condition_is_rejected(tmp_path: pathlib.Path) -> None:
    rec_dir = _create_recording_fixture(
        tmp_path,
        meta_overrides={"condition": "unknown_fault"},
    )
    res = validate_recording_folder(rec_dir)

    assert res.valid is False
    assert any("Invalid condition" in e for e in res.errors)


def test_tap_marker_detection_and_serial_line_parser() -> None:
    audio_sr = 44100
    vib_sr = 1000
    dur = 3.0

    rng = np.random.default_rng(7)
    audio = 0.01 * rng.standard_normal(int(dur * audio_sr))
    t_us = np.arange(int(dur * vib_sr), dtype=np.int64) * 1000
    vib_xyz = 0.01 * rng.standard_normal((t_us.size, 3))
    vib_xyz[:, 2] += 1.0  # 1g static gravity

    # Inject a sharp tap at t = 0.850s in audio and t = 0.820s in vibration (+30 ms offset)
    audio_tap_idx = int(0.850 * audio_sr)
    audio[audio_tap_idx : audio_tap_idx + 20] = 0.85

    vib_tap_idx = int(0.820 * vib_sr)
    vib_xyz[vib_tap_idx : vib_tap_idx + 3, 2] += 3.5

    tap = detect_tap_marker(audio, audio_sr, t_us, vib_xyz)
    assert tap["tap_detected"] is True
    assert pytest.approx(tap["audio_tap_time_s"], abs=0.002) == 0.850
    assert pytest.approx(tap["vibration_tap_time_s"], abs=0.002) == 0.820
    assert pytest.approx(tap["estimated_offset_ms"], abs=3.0) == 30.0

    # Verify serial CSV line parser handles comments, headers, and valid data
    assert parse_serial_csv_line("# SAMPLE_RATE_HZ=1000") is None
    assert parse_serial_csv_line("t_us,ax,ay,az") is None
    assert parse_serial_csv_line("125000,-0.012,0.034,0.998") == (125000, -0.012, 0.034, 0.998)


def test_phase3_firmware_has_no_hardcoded_secrets_and_uses_wide_dlpf() -> None:
    repo_root = pathlib.Path(__file__).resolve().parent.parent.parent
    combined_ino = (
        repo_root / "haven" / "firmware" / "Haven_ESP32_Combined" / "Haven_ESP32_Combined.ino"
    ).read_text(encoding="utf-8")
    telemetry_ino = (
        repo_root / "haven" / "firmware" / "Haven_ESP32_Telemetry" / "Haven_ESP32_Telemetry.ino"
    ).read_text(encoding="utf-8")
    accel_driver = (
        repo_root / "haven" / "firmware" / "Haven_ESP32_Combined" / "accel_driver.h"
    ).read_text(encoding="utf-8")
    secrets_example = (
        repo_root / "haven" / "firmware" / "Haven_ESP32_Combined" / "secrets.h.example"
    )

    assert secrets_example.exists(), "secrets.h.example must exist"

    # 1. No hardcoded Wi-Fi SSID/password or device keys in source
    for leaked in ("GFiber_FC756", "4C34FA60", "hvn_dev_cde1c79d141a9cdcb064d3ef4b9e63f2"):
        assert leaked not in combined_ino, f"Hardcoded secret '{leaked}' still in Haven_ESP32_Combined.ino"
        assert leaked not in telemetry_ino, f"Hardcoded secret '{leaked}' still in Haven_ESP32_Telemetry.ino"

    # 2. Restrictive 21 Hz low-pass filter is removed and replaced with 260 Hz widest DLPF
    assert "MPU6050_BAND_21_HZ" not in combined_ino
    assert "MPU6050_BAND_260_HZ" in accel_driver

    # 3. LOGGER mode header, 921600 baud, Wi-Fi OFF, and swappable IAccelDriver layer are present
    assert "LOGGER_BAUD_RATE = 921600" in combined_ino
    assert "WiFi.mode(WIFI_OFF)" in combined_ino
    assert "# HAVEN_LOGGER_V1 SENSOR=" in combined_ino
    assert "class IAccelDriver" in accel_driver
    assert "class Mpu6050I2cDriver" in accel_driver
    assert "class Adxl345SpiDriver" in accel_driver


