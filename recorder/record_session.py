#!/usr/bin/env python3
"""
HAVEN AI — Offline Synchronized Engine Data Recorder (Phase 2)
==============================================================
Captures high-rate 3-axis accelerometer telemetry from ESP32 over USB Serial
("t_us,ax,ay,az" at 921600 baud) simultaneously with uncompressed 16-bit mono
WAV audio (44100 Hz) from an external/instrumentation microphone.

Output directory structure:
    recordings/<session_id>/<vehicle>/<condition>/<timestamp>/
        ├── audio.wav
        ├── vibration.csv
        └── metadata.json
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import math
import pathlib
import platform
import re
import sys
import threading
import time
from typing import Any, Callable, Dict, List, Optional, Tuple

import numpy as np
import soundfile as sf

try:
    import serial
    import serial.tools.list_ports
except ImportError:  # pragma: no cover
    serial = None  # type: ignore

try:
    import sounddevice as sd
except ImportError:  # pragma: no cover
    sd = None  # type: ignore


ALLOWED_CONDITIONS: Tuple[str, ...] = (
    "normal",
    "misfire",
    "loose_component",
    "valve_clearance",
)


def sanitize_path_segment(value: str) -> str:
    """Convert an arbitrary identifier into a filesystem-safe folder name."""
    cleaned = re.sub(r"[^A-Za-z0-9._-]+", "_", str(value).strip())
    return cleaned.strip("_") or "unknown"


def get_library_versions() -> Dict[str, str]:
    """Return versions of core recording libraries for reproducibility."""
    return {
        "python": platform.python_version(),
        "numpy": getattr(np, "__version__", "unknown"),
        "soundfile": getattr(sf, "__version__", "unknown"),
        "sounddevice": getattr(sd, "__version__", "not_installed") if sd else "not_installed",
        "pyserial": getattr(serial, "__version__", "not_installed") if serial else "not_installed",
    }


def detect_tap_marker(
    audio_samples: np.ndarray,
    audio_sr: int,
    vib_t_us: np.ndarray,
    vib_xyz: np.ndarray,
    search_window_sec: float = 3.0,
) -> Dict[str, Any]:
    """
    Detect an initial synchronization tap marker in the first `search_window_sec`
    seconds of both audio and 3-axis vibration streams.

    Returns a dictionary with tap timestamps, crest factors, and estimated
    audio-to-vibration clock offset in milliseconds.
    """
    result: Dict[str, Any] = {
        "tap_detected": False,
        "search_window_sec": float(search_window_sec),
        "audio_tap_time_s": None,
        "vibration_tap_time_s": None,
        "estimated_offset_ms": None,
        "audio_crest_factor": None,
        "vibration_crest_factor": None,
    }

    if audio_samples.size < 100 or audio_sr <= 0 or vib_t_us.size < 10 or vib_xyz.shape[0] < 10:
        return result

    mono = audio_samples.reshape(-1).astype(np.float64)
    mono = mono - np.mean(mono)
    n_audio_win = min(mono.size, int(search_window_sec * audio_sr))
    audio_win = np.abs(mono[:n_audio_win])

    audio_rms = float(np.sqrt(np.mean(audio_win ** 2))) + 1e-12
    audio_peak_idx = int(np.argmax(audio_win))
    audio_peak = float(audio_win[audio_peak_idx])
    audio_crest = audio_peak / audio_rms
    audio_tap_s = audio_peak_idx / float(audio_sr)

    vib_rel_s = (vib_t_us.astype(np.float64) - float(vib_t_us[0])) * 1e-6
    vib_mask = vib_rel_s <= search_window_sec
    if np.count_nonzero(vib_mask) < 5:
        vib_mask = np.ones_like(vib_rel_s, dtype=bool)

    vib_win_xyz = vib_xyz[vib_mask].astype(np.float64)
    vib_centered = vib_win_xyz - np.mean(vib_win_xyz, axis=0, keepdims=True)
    vib_dyn_mag = np.linalg.norm(vib_centered, axis=1)

    vib_rms = float(np.sqrt(np.mean(vib_dyn_mag ** 2))) + 1e-12
    vib_peak_idx = int(np.argmax(vib_dyn_mag))
    vib_peak = float(vib_dyn_mag[vib_peak_idx])
    vib_crest = vib_peak / vib_rms
    vib_tap_s = float(vib_rel_s[vib_mask][vib_peak_idx])

    # A sharp mechanical tap produces a high crest factor (impulse-to-RMS >= 4.5) in both channels
    tap_detected = bool(audio_crest >= 4.5 and vib_crest >= 4.0)
    offset_ms = (audio_tap_s - vib_tap_s) * 1000.0

    result.update(
        {
            "tap_detected": tap_detected,
            "audio_tap_time_s": round(audio_tap_s, 6),
            "vibration_tap_time_s": round(vib_tap_s, 6),
            "estimated_offset_ms": round(offset_ms, 3),
            "audio_crest_factor": round(audio_crest, 3),
            "vibration_crest_factor": round(vib_crest, 3),
        }
    )
    return result


def parse_serial_csv_line(line: str) -> Optional[Tuple[int, float, float, float]]:
    """
    Parse a single ESP32 logger line formatted as 't_us,ax,ay,az'.
    Returns (t_us, ax, ay, az) or None if the line is a comment/header/malformed.
    """
    stripped = line.strip()
    if not stripped or stripped.startswith("#") or stripped.startswith("t_us"):
        return None
    parts = stripped.split(",")
    if len(parts) != 4:
        return None
    try:
        t_us = int(float(parts[0]))
        ax = float(parts[1])
        ay = float(parts[2])
        az = float(parts[3])
        if not (math.isfinite(ax) and math.isfinite(ay) and math.isfinite(az)):
            return None
        return (t_us, ax, ay, az)
    except ValueError:
        return None


def build_output_dir(
    output_root: pathlib.Path,
    session_id: str,
    vehicle_id: str,
    condition: str,
    timestamp_str: Optional[str] = None,
) -> pathlib.Path:
    """
    Construct the canonical output folder path:
        recordings/<session_id>/<vehicle>/<condition>/<timestamp>/
    """
    if condition not in ALLOWED_CONDITIONS:
        raise ValueError(f"Invalid condition '{condition}'. Must be one of {ALLOWED_CONDITIONS}")
    ts = timestamp_str or dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    return (
        output_root
        / sanitize_path_segment(session_id)
        / sanitize_path_segment(vehicle_id)
        / condition
        / sanitize_path_segment(ts)
    )


def get_serial_ports() -> List[Dict[str, str]]:
    """Return available USB/Serial ports as structured dictionaries."""
    if serial is None:
        return []
    result: List[Dict[str, str]] = []
    for p in serial.tools.list_ports.comports():
        result.append(
            {
                "device": str(p.device),
                "description": str(p.description or p.device),
                "hwid": str(p.hwid or ""),
                "label": f"{p.device} — {p.description or 'Serial Device'}",
            }
        )
    return result


def get_audio_input_devices() -> List[Dict[str, Any]]:
    """Return available audio input microphones as structured dictionaries."""
    if sd is None:
        return []
    result: List[Dict[str, Any]] = []
    try:
        devices = sd.query_devices()
        default_in = None
        try:
            default_pair = sd.default.device
            if isinstance(default_pair, (list, tuple)) and len(default_pair) >= 1:
                default_in = default_pair[0]
        except Exception:
            default_in = None

        for idx, dev in enumerate(devices):
            max_in = int(dev.get("max_input_channels", 0) or 0)
            if max_in > 0:
                is_def = idx == default_in
                prefix = "★ " if is_def else ""
                result.append(
                    {
                        "index": idx,
                        "name": str(dev.get("name", f"Device {idx}")),
                        "channels": max_in,
                        "default_samplerate": float(dev.get("default_samplerate", 44100.0)),
                        "is_default": is_def,
                        "label": f"{prefix}[{idx}] {dev.get('name', f'Device {idx}')}",
                    }
                )
    except Exception:
        pass
    return result


def open_esp32_serial(port: str, baud_rate: int = 921600, timeout: float = 0.05) -> Any:
    """
    Open an ESP32 serial connection with DTR/RTS held low so opening or changing
    baud rate does not trigger an unwanted bootloader reset on CP210x/CH340 boards.
    """
    if serial is None:
        raise RuntimeError("pyserial is required to open serial ports")
    ser = serial.Serial()
    ser.port = port
    ser.baudrate = int(baud_rate)
    ser.timeout = float(timeout)
    try:
        ser.dtr = False
        ser.rts = False
    except Exception:
        pass
    ser.open()
    return ser


def ensure_esp32_logger_mode(
    ser: Any,
    target_baud: int = 921600,
    control_baud: int = 115200,
) -> List[str]:
    """
    Ensure the connected ESP32 is streaming high-rate 't_us,ax,ay,az' CSV lines
    at `target_baud`.

    1. Listens briefly at `target_baud` in case the board is already in LOGGER mode.
    2. If no valid CSV lines arrive and `target_baud != control_baud`, switches in-place
       to `control_baud` (115200), sends `LOGGER\\n` so `Haven_ESP32_Combined.ino` shuts
       down Wi-Fi and switches UART to `target_baud`, waits briefly for its 200-sample
       hardware calibration burst, and switches host UART back to `target_baud`.
    Returns any `# HAVEN_LOGGER_...` header lines captured during negotiation.
    """
    headers: List[str] = []

    def _probe_stream(window_s: float) -> bool:
        deadline = time.monotonic() + window_s
        valid_count = 0
        while time.monotonic() < deadline:
            raw = ser.readline()
            if not raw:
                continue
            line = raw.decode("utf-8", errors="replace").strip()
            if line.startswith("#"):
                headers.append(line)
                continue
            if parse_serial_csv_line(line) is not None:
                valid_count += 1
                if valid_count >= 2:
                    return True
        return False

    try:
        ser.reset_input_buffer()
    except Exception:
        pass

    # Fast check: already in high-rate LOGGER mode?
    if _probe_stream(0.22):
        return headers

    # Also try sending LOGGER at current baud in case firmware was compiled at target_baud
    try:
        ser.write(b"\nLOGGER\n")
        ser.flush()
    except Exception:
        pass

    if _probe_stream(0.25):
        return headers

    # Negotiate from default 115200 normal mode -> 921600 LOGGER mode
    if int(target_baud) != int(control_baud):
        try:
            ser.baudrate = int(control_baud)
            ser.reset_input_buffer()
            for _ in range(2):
                ser.write(b"\nLOGGER\n")
                ser.flush()
                time.sleep(0.18)
            # Allow ESP32 to complete 200-sample calibration (~200 ms) and switch UART baud
            time.sleep(0.20)
            ser.baudrate = int(target_baud)
            ser.reset_input_buffer()
            _probe_stream(0.25)
        except Exception:
            try:
                ser.baudrate = int(target_baud)
            except Exception:
                pass

    return headers


def list_available_devices() -> None:
    """Print available serial ports and audio input devices."""
    print("=== Serial Ports (pyserial) ===")
    if serial is None:
        print("  pyserial not installed.")
    else:
        ports = get_serial_ports()
        if not ports:
            print("  No serial ports detected.")
        for p in ports:
            print(f"  {p['device']}: {p['description']} [{p['hwid']}]")

    print("\n=== Audio Input Devices (sounddevice) ===")
    if sd is None:
        print("  sounddevice not installed.")
    else:
        for dev in get_audio_input_devices():
            print(
                f"  [{dev['index']}] {dev['name']} "
                f"(in={dev['channels']}, default_sr={dev['default_samplerate']})"
            )


def record_synchronized_session(
    args: argparse.Namespace,
    *,
    progress_callback: Optional[Callable[[Dict[str, Any]], None]] = None,
    external_stop_event: Optional[threading.Event] = None,
) -> pathlib.Path:
    """
    Execute a synchronized serial + microphone capture session and write
    vibration.csv, audio.wav, and metadata.json into the output directory.
    """
    if serial is None:
        raise RuntimeError("pyserial is required to run record_session.py")
    if sd is None:
        raise RuntimeError("sounddevice is required to run record_session.py")

    start_utc = dt.datetime.now(dt.timezone.utc)
    timestamp_folder = start_utc.strftime("%Y%m%dT%H%M%SZ")
    out_dir = build_output_dir(
        pathlib.Path(args.output_root),
        args.session_id,
        str(args.vehicle_id),
        args.condition,
        timestamp_folder,
    )
    out_dir.mkdir(parents=True, exist_ok=True)

    audio_path = out_dir / "audio.wav"
    vib_path = out_dir / "vibration.csv"
    meta_path = out_dir / "metadata.json"

    duration_s = float(args.duration)
    audio_sr = int(args.audio_rate)
    baud_rate = int(args.baud)
    sensor_range_g = float(args.sensor_range_g)

    vib_samples: List[Tuple[int, float, float, float]] = []
    firmware_headers: List[str] = []
    audio_chunks: List[np.ndarray] = []

    serial_ready = threading.Event()
    start_gate = threading.Event()
    stop_gate = threading.Event()
    serial_error: List[str] = []
    timing_info: Dict[str, Any] = {}

    def serial_worker() -> None:
        ser = None
        try:
            ser = open_esp32_serial(args.port, baud_rate=baud_rate, timeout=0.05)
            negotiated_headers = ensure_esp32_logger_mode(ser, target_baud=baud_rate)
            firmware_headers.extend(negotiated_headers)
            ser.reset_input_buffer()
            serial_ready.set()

            start_gate.wait()
            timing_info["serial_start_iso"] = dt.datetime.now(dt.timezone.utc).isoformat()
            timing_info["serial_start_ns"] = time.perf_counter_ns()

            while not stop_gate.is_set():
                raw = ser.readline()
                if not raw:
                    continue
                line = raw.decode("utf-8", errors="replace").strip()
                if line.startswith("#"):
                    firmware_headers.append(line)
                    continue
                parsed = parse_serial_csv_line(line)
                if parsed is not None:
                    vib_samples.append(parsed)
        except Exception as exc:
            serial_error.append(str(exc))
            serial_ready.set()
            stop_gate.set()
        finally:
            if ser is not None:
                try:
                    ser.close()
                except Exception:
                    pass

    serial_thread = threading.Thread(target=serial_worker, daemon=True)
    serial_thread.start()

    def audio_callback(indata: np.ndarray, frames: int, time_info: Any, status: Any) -> None:
        if not start_gate.is_set():
            return
        if "audio_start_ns" not in timing_info:
            timing_info["audio_start_ns"] = time.perf_counter_ns()
            timing_info["audio_start_iso"] = dt.datetime.now(dt.timezone.utc).isoformat()
        if not stop_gate.is_set():
            audio_chunks.append(indata.copy())

    device_arg: Optional[Any] = None
    if args.device is not None and str(args.device).strip() != "":
        try:
            device_arg = int(args.device)
        except ValueError:
            device_arg = args.device

    print(f"[HAVEN RECORDER] Output folder: {out_dir}")
    print(
        f"[HAVEN RECORDER] Opening Serial ({args.port} @ {baud_rate} baud) "
        f"& Audio ({audio_sr} Hz, 16-bit mono)..."
    )

    # Wait for ESP32 serial logger handshake before triggering synchronized audio+vibration gate
    serial_ready.wait(timeout=4.0)
    if serial_error:
        serial_thread.join(timeout=1.0)
        raise RuntimeError(f"Serial capture failed on {args.port}: {serial_error[0]}")

    with sd.InputStream(
        samplerate=audio_sr,
        channels=1,
        dtype="int16",
        device=device_arg,
        callback=audio_callback,
    ):
        # Synchronized trigger for both streams
        timing_info["host_start_iso"] = dt.datetime.now(dt.timezone.utc).isoformat()
        timing_info["host_start_ns"] = time.perf_counter_ns()
        start_gate.set()

        t0 = time.monotonic()
        last_print = t0
        last_cb = 0.0
        print(
            "[HAVEN RECORDER] Recording started! "
            "Tip: Tap the sensor mount lightly within the first 2s to create an alignment marker."
        )

        while (time.monotonic() - t0) < duration_s and not stop_gate.is_set():
            if external_stop_event is not None and external_stop_event.is_set():
                stop_gate.set()
                break
            time.sleep(0.08)
            now = time.monotonic()
            elapsed = now - t0

            if progress_callback is not None and (now - last_cb) >= 0.12:
                n_vib = len(vib_samples)
                est_vib_rate = n_vib / max(elapsed, 1e-3)
                recent_vib = vib_samples[-250:]
                recent_aud = audio_chunks[-4:]
                aud_peak = (
                    max((int(np.max(np.abs(c))) for c in recent_aud), default=0) / 32768.0
                    if recent_aud
                    else 0.0
                )
                vib_peak = (
                    max((max(abs(s[1]), abs(s[2]), abs(s[3])) for s in recent_vib), default=0.0)
                    if recent_vib
                    else 0.0
                )
                latest_xyz = recent_vib[-1][1:4] if recent_vib else (0.0, 0.0, 0.0)
                aud_tail: List[float] = []
                if recent_aud:
                    flat_a = np.concatenate(recent_aud, axis=0).reshape(-1)
                    step_a = max(1, flat_a.size // 80)
                    aud_tail = (flat_a[::step_a][-80:].astype(np.float64) / 32768.0).tolist()
                vib_tail: List[float] = []
                if len(recent_vib) >= 4:
                    arr_v = np.asarray([s[1:4] for s in recent_vib], dtype=np.float64)
                    dyn_v = np.linalg.norm(arr_v - np.mean(arr_v, axis=0, keepdims=True), axis=1)
                    step_v = max(1, dyn_v.size // 80)
                    vib_tail = dyn_v[::step_v][-80:].tolist()

                try:
                    progress_callback(
                        {
                            "elapsed_sec": elapsed,
                            "duration_sec": duration_s,
                            "vib_samples": n_vib,
                            "vib_rate_hz": est_vib_rate,
                            "vib_peak_g": vib_peak,
                            "latest_xyz": latest_xyz,
                            "audio_peak": aud_peak,
                            "audio_tail": aud_tail,
                            "vib_tail": vib_tail,
                        }
                    )
                except Exception:
                    pass
                last_cb = now

            if (now - last_print) >= 1.0:
                n_vib = len(vib_samples)
                est_vib_rate = n_vib / max(elapsed, 1e-3)
                n_aud = sum(chunk.shape[0] for chunk in audio_chunks)
                aud_peak = (
                    max((int(np.max(np.abs(c))) for c in audio_chunks[-10:]), default=0) / 32768.0
                    if audio_chunks
                    else 0.0
                )
                vib_peak = (
                    max(
                        (
                            max(abs(s[1]), abs(s[2]), abs(s[3]))
                            for s in vib_samples[-1000:]
                        ),
                        default=0.0,
                    )
                    if vib_samples
                    else 0.0
                )
                clip_warn = ""
                if aud_peak >= 0.99:
                    clip_warn += " [WARN: AUDIO CLIPPING!]"
                if vib_peak >= 0.98 * sensor_range_g:
                    clip_warn += " [WARN: VIBRATION RAILING!]"
                print(
                    f"  [{elapsed:5.1f}s / {duration_s:.1f}s] "
                    f"vib_samples={n_vib} (~{est_vib_rate:.1f} Hz, peakAxis={vib_peak:.2f}g) | "
                    f"audio_frames={n_aud} (peak={aud_peak * 100:.1f}% FS){clip_warn}"
                )
                last_print = now

        stop_gate.set()
        timing_info["host_end_iso"] = dt.datetime.now(dt.timezone.utc).isoformat()
        timing_info["host_end_ns"] = time.perf_counter_ns()

    serial_thread.join(timeout=2.0)

    if serial_error:
        raise RuntimeError(f"Serial capture failed on {args.port}: {serial_error[0]}")

    # Write uncompressed 16-bit PCM WAV
    if audio_chunks:
        audio_int16 = np.concatenate(audio_chunks, axis=0)
    else:
        audio_int16 = np.zeros((0, 1), dtype=np.int16)

    sf.write(str(audio_path), audio_int16, audio_sr, subtype="PCM_16")

    # Write vibration CSV
    with vib_path.open("w", newline="", encoding="utf-8") as f_csv:
        writer = csv.writer(f_csv)
        writer.writerow(["t_us", "ax", "ay", "az"])
        writer.writerows(vib_samples)

    # Compute summary & tap-marker alignment
    audio_float = audio_int16.reshape(-1).astype(np.float64) / 32768.0
    audio_clipped_count = int(np.count_nonzero(np.abs(audio_int16) >= 32600))
    audio_duration_actual = float(audio_int16.shape[0]) / float(audio_sr) if audio_sr > 0 else 0.0

    if len(vib_samples) >= 2:
        vib_arr = np.array(vib_samples, dtype=np.float64)
        vib_t_us = vib_arr[:, 0].astype(np.int64)
        vib_xyz = vib_arr[:, 1:4]
        dt_us = np.diff(vib_t_us)
        valid_dt = dt_us[dt_us > 0]
        achieved_vib_hz = float(1e6 / np.median(valid_dt)) if valid_dt.size > 0 else 0.0
        vib_duration_actual = float(vib_t_us[-1] - vib_t_us[0]) * 1e-6
        vib_clipped_count = int(np.count_nonzero(np.abs(vib_xyz) >= 0.98 * sensor_range_g))
    else:
        vib_t_us = np.zeros((0,), dtype=np.int64)
        vib_xyz = np.zeros((0, 3), dtype=np.float64)
        achieved_vib_hz = 0.0
        vib_duration_actual = 0.0
        vib_clipped_count = 0

    tap_info = detect_tap_marker(audio_float, audio_sr, vib_t_us, vib_xyz)

    metadata: Dict[str, Any] = {
        "session_id": args.session_id,
        "vehicle_id": str(args.vehicle_id),
        "condition": args.condition,
        "rpm_target": int(args.rpm_target),
        "duration_target_sec": duration_s,
        "operator": args.operator,
        "verified_by": args.verified_by or "",
        "sensor_position": args.sensor_position,
        "mic_position": args.mic_position,
        "load_state": args.load_state,
        "fault_detail": args.fault_detail or "",
        "notes": args.notes or "",
        "serial_port": args.port,
        "serial_baud": baud_rate,
        "audio_device": args.device,
        "audio_sample_rate_hz": audio_sr,
        "audio_channels": 1,
        "audio_subtype": "PCM_16",
        "audio_frames": int(audio_int16.shape[0]),
        "audio_duration_sec": round(audio_duration_actual, 4),
        "audio_clipped_samples": audio_clipped_count,
        "vibration_samples": len(vib_samples),
        "vibration_achieved_rate_hz": round(achieved_vib_hz, 2),
        "vibration_duration_sec": round(vib_duration_actual, 4),
        "vibration_sensor_range_g": sensor_range_g,
        "vibration_clipped_samples": vib_clipped_count,
        "firmware_headers": firmware_headers,
        "timestamps": timing_info,
        "tap_marker": tap_info,
        "library_versions": get_library_versions(),
    }

    with meta_path.open("w", encoding="utf-8") as f_meta:
        json.dump(metadata, f_meta, indent=2)

    print("\n=== Recording Complete ===")
    print(f"  Folder             : {out_dir}")
    print(
        f"  Audio WAV          : {audio_int16.shape[0]} frames "
        f"({audio_duration_actual:.2f}s @ {audio_sr} Hz, clipped={audio_clipped_count})"
    )
    print(
        f"  Vibration CSV      : {len(vib_samples)} samples "
        f"({vib_duration_actual:.2f}s @ ~{achieved_vib_hz:.1f} Hz, clipped={vib_clipped_count})"
    )
    if tap_info["tap_detected"]:
        print(
            f"  Tap Marker Aligned : YES "
            f"(audio={tap_info['audio_tap_time_s']:.3f}s, "
            f"vib={tap_info['vibration_tap_time_s']:.3f}s, "
            f"offset={tap_info['estimated_offset_ms']:+.1f} ms)"
        )
    else:
        print(
            "  Tap Marker Aligned : No distinct initial tap detected "
            f"(audio crest={tap_info['audio_crest_factor']}, vib crest={tap_info['vibration_crest_factor']})"
        )

    return out_dir


def build_arg_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="HAVEN AI — Synchronized ESP32 Serial + Uncompressed WAV Recorder"
    )
    parser.add_argument("--list-devices", action="store_true", help="List serial ports and audio devices and exit")
    parser.add_argument("--vehicle-id", type=str, help="Vehicle ID or identifier (e.g. 1 or toyota_vios_2021)")
    parser.add_argument(
        "--condition",
        type=str,
        choices=ALLOWED_CONDITIONS,
        help="Verified ground-truth engine condition class",
    )
    parser.add_argument("--rpm-target", type=int, help="Target engine speed in RPM (e.g. 850 or 2200)")
    parser.add_argument("--duration", type=float, default=10.0, help="Recording duration in seconds (default: 10.0)")
    parser.add_argument("--session-id", type=str, help="Recording session identifier (e.g. SESS-2026-001)")
    parser.add_argument("--operator", type=str, help="Name of operator performing the capture")
    parser.add_argument("--verified-by", type=str, default="", help="Mechanic or supervisor verifying ground truth")
    parser.add_argument("--sensor-position", type=str, help="Accelerometer mounting position on engine")
    parser.add_argument("--mic-position", type=str, help="Microphone placement position and distance")
    parser.add_argument("--load-state", type=str, default="Idle, Park/Neutral, AC Off", help="Engine load state")
    parser.add_argument("--fault-detail", type=str, default="", help="Specific mechanical fault description")
    parser.add_argument("--notes", type=str, default="", help="Additional session notes")
    parser.add_argument("--port", type=str, help="ESP32 serial port (e.g. COM3 or /dev/ttyUSB0)")
    parser.add_argument("--baud", type=int, default=921600, help="Serial baud rate (default: 921600)")
    parser.add_argument("--device", type=str, default=None, help="Audio input device index or name (sounddevice)")
    parser.add_argument("--audio-rate", type=int, default=44100, help="Audio sample rate in Hz (default: 44100)")
    parser.add_argument("--sensor-range-g", type=float, default=16.0, help="Accelerometer full-scale range in g")
    parser.add_argument("--output-root", type=str, default="recordings", help="Root output directory")
    return parser


def main(argv: Optional[List[str]] = None) -> int:
    parser = build_arg_parser()
    args = parser.parse_args(argv)

    if args.list_devices:
        list_available_devices()
        return 0

    required = [
        ("vehicle_id", "--vehicle-id"),
        ("condition", "--condition"),
        ("rpm_target", "--rpm-target"),
        ("session_id", "--session-id"),
        ("operator", "--operator"),
        ("sensor_position", "--sensor-position"),
        ("mic_position", "--mic-position"),
        ("port", "--port"),
    ]
    missing = [flag for attr, flag in required if getattr(args, attr) in (None, "")]
    if missing:
        parser.error(f"Missing required arguments: {', '.join(missing)}")

    record_synchronized_session(args)
    return 0


if __name__ == "__main__":
    sys.exit(main())

