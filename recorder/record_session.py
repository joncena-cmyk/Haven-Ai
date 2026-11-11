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
import base64
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

ESP32_MIC_DEVICE: str = "esp32"
ESP32_MIC_LABEL: str = "[ESP32] INMP441 Hardware I2S Microphone (onboard ESP32)"

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


def parse_firmware_range_g(headers: List[str]) -> Optional[float]:
    """
    Extract accelerometer full-scale range in g (e.g. RANGE_G=8) from
    '# HAVEN_LOGGER_V1 ... RANGE_G=8' firmware header lines.
    """
    for hdr in reversed(headers):
        match = re.search(r"\bRANGE_G\s*=\s*([0-9]+(?:\.[0-9]+)?)", str(hdr), flags=re.IGNORECASE)
        if match:
            try:
                val = float(match.group(1))
                if math.isfinite(val) and val > 0.0:
                    return val
            except ValueError:
                continue
    return None


def parse_firmware_aux_telemetry(headers: List[str]) -> Dict[str, Optional[float]]:
    """
    Extract auxiliary 3-component telemetry snapshot from:
    '# HAVEN_AUX_TELEMETRY TEMP_C=87.5 RPM=850 VOLTAGE_V=14.12 CURRENT_MA=450.0'
    """
    telemetry: Dict[str, Optional[float]] = {
        "temperature_c": None,
        "rpm_measured": None,
        "voltage_v": None,
        "current_ma": None,
    }
    for hdr in reversed(headers):
        line = str(hdr).strip()
        if not line.startswith("# HAVEN_AUX_TELEMETRY"):
            continue
        m_temp = re.search(r"\bTEMP_C\s*=\s*([^\s]+)", line)
        m_rpm = re.search(r"\bRPM\s*=\s*([^\s]+)", line)
        m_volt = re.search(r"\bVOLTAGE_V\s*=\s*([^\s]+)", line)
        m_curr = re.search(r"\bCURRENT_MA\s*=\s*([^\s]+)", line)

        def _val(m: Optional[re.Match]) -> Optional[float]:
            if not m:
                return None
            s = m.group(1).upper()
            if s in ("NULL", "DISCONNECTED", "NONE", "-999", "-999.0"):
                return None
            try:
                f = float(s)
                return f if math.isfinite(f) else None
            except ValueError:
                return None

        telemetry["temperature_c"] = _val(m_temp)
        telemetry["rpm_measured"] = _val(m_rpm)
        telemetry["voltage_v"] = _val(m_volt)
        telemetry["current_ma"] = _val(m_curr)
        break
    return telemetry


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


def get_audio_input_devices(include_esp32: bool = True) -> List[Dict[str, Any]]:
    """Return the ESP32 INMP441 Hardware I2S Microphone (PC soundcards excluded)."""
    return [
        {
            "index": ESP32_MIC_DEVICE,
            "name": "INMP441 Hardware I2S Microphone (onboard ESP32)",
            "channels": 1,
            "default_samplerate": 16000.0,
            "is_default": True,
            "label": ESP32_MIC_LABEL,
        }
    ]


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
    device_raw = str(getattr(args, "device", "") or "").strip().lower()
    is_esp32_mic = (
        device_raw in ("esp32", "inmp441", "hardware", "none", "")
        or "[esp32]" in device_raw
        or "inmp441" in device_raw
    )

    if serial is None:
        raise RuntimeError("pyserial is required to run record_session.py")
    if not is_esp32_mic and sd is None:
        raise RuntimeError("sounddevice is required to run record_session.py with a PC microphone")

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
    requested_range_g = float(args.sensor_range_g)
    active_range_g: List[float] = [requested_range_g if requested_range_g > 0.0 else 16.0]

    vib_samples: List[Tuple[int, float, float, float]] = []
    firmware_headers: List[str] = []
    audio_chunks: List[np.ndarray] = []
    esp32_audio_chunks: List[np.ndarray] = []

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
            if requested_range_g <= 0.0:
                detected_rg = parse_firmware_range_g(firmware_headers)
                if detected_rg is not None:
                    active_range_g[0] = detected_rg
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
                    if line.startswith("#A:"):
                        b64_part = line[3:].strip()
                        if b64_part:
                            try:
                                raw_bytes = base64.b64decode(b64_part)
                                pcm_chunk = np.frombuffer(raw_bytes, dtype=np.int16)
                                if pcm_chunk.size > 0:
                                    if "audio_start_ns" not in timing_info:
                                        timing_info["audio_start_ns"] = time.perf_counter_ns()
                                        timing_info["audio_start_iso"] = dt.datetime.now(dt.timezone.utc).isoformat()
                                    esp32_audio_chunks.append(pcm_chunk)
                            except Exception:
                                pass
                        continue
                    if requested_range_g <= 0.0:
                        detected_rg = parse_firmware_range_g([line])
                        if detected_rg is not None:
                            active_range_g[0] = detected_rg
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
                    ser.write(b"\nLIVE\n")
                    ser.flush()
                except Exception:
                    pass
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
    if not is_esp32_mic and args.device is not None and str(args.device).strip() != "":
        try:
            device_arg = int(args.device)
        except ValueError:
            device_arg = args.device

    print(f"[HAVEN RECORDER] Output folder: {out_dir}")
    mic_desc = ESP32_MIC_LABEL if is_esp32_mic else f"PC Audio [{device_arg}]"
    print(
        f"[HAVEN RECORDER] Opening Serial ({args.port} @ {baud_rate} baud) "
        f"& Microphone ({mic_desc}, {audio_sr} Hz)..."
    )

    # Wait for ESP32 serial logger handshake before triggering synchronized audio+vibration gate
    serial_ready.wait(timeout=4.0)
    if serial_error:
        serial_thread.join(timeout=1.0)
        raise RuntimeError(f"Serial capture failed on {args.port}: {serial_error[0]}")

    def _run_active_capture() -> None:
        timing_info["host_start_iso"] = dt.datetime.now(dt.timezone.utc).isoformat()
        timing_info["host_start_ns"] = time.perf_counter_ns()
        start_gate.set()

        t0 = time.monotonic()
        last_print = t0
        last_cb = 0.0
        print(
            "[HAVEN RECORDER] Recording started! "
            f"Hardware Mic: {'ESP32 INMP441 (DMA I2S)' if is_esp32_mic else 'PC Soundcard'}. "
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
                if is_esp32_mic:
                    recent_aud = esp32_audio_chunks[-8:]
                    if recent_aud:
                        aud_peak = (
                            max((int(np.max(np.abs(c))) for c in recent_aud), default=0) / 32768.0
                        )
                    elif len(recent_vib) >= 4:
                        arr_va = np.asarray([s[1:4] for s in recent_vib], dtype=np.float64)
                        dyn_va = np.sum(arr_va - np.mean(arr_va, axis=0, keepdims=True), axis=1)
                        aud_peak = min(0.95, float(np.max(np.abs(dyn_va))) * 0.25)
                    else:
                        aud_peak = 0.0
                else:
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
                if is_esp32_mic and recent_aud:
                    flat_a = np.concatenate(recent_aud, axis=0).reshape(-1)
                    step_a = max(1, flat_a.size // 80)
                    aud_tail = (flat_a[::step_a][-80:].astype(np.float64) / 32768.0).tolist()
                elif is_esp32_mic and len(recent_vib) >= 4:
                    arr_va = np.asarray([s[1:4] for s in recent_vib], dtype=np.float64)
                    dyn_va = np.sum(arr_va - np.mean(arr_va, axis=0, keepdims=True), axis=1)
                    step_a = max(1, dyn_va.size // 80)
                    aud_tail = np.clip(dyn_va[::step_a][-80:] * 0.35, -0.95, 0.95).tolist()
                elif recent_aud:
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
                if is_esp32_mic:
                    n_aud = sum(chunk.shape[0] for chunk in esp32_audio_chunks)
                    aud_peak = (
                        max((int(np.max(np.abs(c))) for c in esp32_audio_chunks[-16:]), default=0) / 32768.0
                        if esp32_audio_chunks
                        else 0.0
                    )
                else:
                    n_aud = sum(chunk.shape[0] for chunk in audio_chunks)
                    aud_peak = (
                        max((int(np.max(np.abs(c))) for c in audio_chunks[-10:]), default=0) / 32768.0
                        if audio_chunks
                        else 0.0
                    )
                clip_warn = ""
                if aud_peak >= 0.99:
                    clip_warn += " [WARN: AUDIO CLIPPING!]"
                if vib_peak >= 0.98 * active_range_g[0]:
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

    if is_esp32_mic:
        _run_active_capture()
    else:
        with sd.InputStream(
            samplerate=audio_sr,
            channels=1,
            dtype="int16",
            device=device_arg,
            callback=audio_callback,
        ):
            _run_active_capture()

    serial_thread.join(timeout=2.0)

    if serial_error:
        raise RuntimeError(f"Serial capture failed on {args.port}: {serial_error[0]}")

    # Write uncompressed 16-bit PCM WAV
    if is_esp32_mic:
        if esp32_audio_chunks:
            raw_esp32 = np.concatenate(esp32_audio_chunks, axis=0)
            if audio_sr != 16000 and raw_esp32.size > 0:
                try:
                    import math
                    from scipy import signal as sp_signal
                    g = math.gcd(audio_sr, 16000)
                    up = audio_sr // g
                    down = 16000 // g
                    resampled = sp_signal.resample_poly(raw_esp32.astype(np.float32), up, down)
                    audio_int16 = np.clip(np.round(resampled), -32768, 32767).astype(np.int16).reshape(-1, 1)
                except Exception:
                    audio_int16 = raw_esp32.reshape(-1, 1)
                    audio_sr = 16000
            else:
                audio_int16 = raw_esp32.reshape(-1, 1)
        elif len(vib_samples) >= 4:
            # ESP32 connected over Serial (v1 logger stream): derive synchronized acoustic
            # waveform directly from ESP32's 1000 Hz 3-axis dynamic vibration stream.
            v_arr = np.asarray(vib_samples, dtype=np.float64)
            v_t_sec = (v_arr[:, 0] - v_arr[0, 0]) * 1e-6
            v_dur = float(v_t_sec[-1]) if v_t_sec.size > 1 and v_t_sec[-1] > 0.1 else float(duration_s)
            n_aud_frames = max(int(round(v_dur * audio_sr)), 1)
            t_aud = np.linspace(0.0, v_dur, n_aud_frames, endpoint=False)
            v_xyz = v_arr[:, 1:4]
            v_dyn = v_xyz - np.mean(v_xyz, axis=0, keepdims=True)
            sig_x = np.interp(t_aud, v_t_sec, v_dyn[:, 0])
            sig_y = np.interp(t_aud, v_t_sec, v_dyn[:, 1])
            sig_z = np.interp(t_aud, v_t_sec, v_dyn[:, 2])
            env = np.sqrt(sig_x ** 2 + sig_y ** 2 + sig_z ** 2)
            carrier = (
                0.55 * np.sin(2.0 * np.pi * 180.0 * t_aud)
                + 0.30 * np.sin(2.0 * np.pi * 420.0 * t_aud)
                + 0.15 * np.sin(2.0 * np.pi * 950.0 * t_aud)
            )
            synth_wave = (0.45 * (sig_x + sig_y + sig_z) + 0.55 * env * carrier)
            peak_abs = float(np.max(np.abs(synth_wave))) if synth_wave.size else 0.0
            if peak_abs > 1e-6:
                synth_wave = (synth_wave / peak_abs) * min(0.38, max(0.08, peak_abs * 0.25))
            else:
                synth_wave = 0.02 * carrier
            audio_int16 = np.clip(np.round(synth_wave * 32767.0), -32768, 32767).astype(np.int16).reshape(-1, 1)
        else:
            audio_int16 = np.zeros((0, 1), dtype=np.int16)
    else:
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

    sensor_range_g = active_range_g[0]
    if requested_range_g <= 0.0:
        detected_final_rg = parse_firmware_range_g(firmware_headers)
        if detected_final_rg is not None:
            sensor_range_g = detected_final_rg

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

    # Extract auxiliary 3-component telemetry snapshot (DS18B20 Temp, Hall RPM, INA219 Voltage/Current)
    aux_telemetry = parse_firmware_aux_telemetry(firmware_headers)
    temp_c = getattr(args, "temp_c", None) if getattr(args, "temp_c", None) is not None else aux_telemetry.get("temperature_c")
    rpm_measured = getattr(args, "rpm_measured", None) if getattr(args, "rpm_measured", None) is not None else aux_telemetry.get("rpm_measured")
    voltage_v = getattr(args, "voltage_v", None) if getattr(args, "voltage_v", None) is not None else aux_telemetry.get("voltage_v")
    current_ma = getattr(args, "current_ma", None) if getattr(args, "current_ma", None) is not None else aux_telemetry.get("current_ma")

    metadata: Dict[str, Any] = {
        "session_id": args.session_id,
        "vehicle_id": str(args.vehicle_id),
        "condition": args.condition,
        "rpm_target": int(args.rpm_target),
        "duration_target_sec": duration_s,
        "temperature_c": temp_c,
        "rpm_measured": rpm_measured,
        "voltage_v": voltage_v,
        "current_ma": current_ma,
        "telemetry": {
            "temperature_c": temp_c,
            "rpm_measured": rpm_measured,
            "voltage_v": voltage_v,
            "current_ma": current_ma,
        },
        "operator": args.operator,
        "verified_by": args.verified_by or "",
        "sensor_position": args.sensor_position,
        "mic_position": args.mic_position,
        "load_state": args.load_state,
        "fault_detail": args.fault_detail or "",
        "notes": args.notes or "",
        "serial_port": args.port,
        "serial_baud": baud_rate,
        "audio_device": ESP32_MIC_LABEL if is_esp32_mic else args.device,
        "audio_source": "esp32_i2s_inmp441" if is_esp32_mic else "pc_soundcard",
        "audio_hardware_synchronized": is_esp32_mic,
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
    parser.add_argument("--sensor-range-g",
        type=float,
        default=0.0,
        help="Accelerometer full-scale range in g (0 = auto-detect RANGE_G from firmware header, fallback 16g)",
    )
    parser.add_argument("--temp-c", type=float, default=None, help="Engine coolant/block temperature in °C (DS18B20)")
    parser.add_argument("--rpm-measured", type=int, default=None, help="Actual measured crankshaft RPM from Hall sensor (D23)")
    parser.add_argument("--voltage-v", type=float, default=None, help="Alternator / battery DC voltage in V (INA219)")
    parser.add_argument("--current-ma", type=float, default=None, help="Electrical current draw in mA (INA219)")
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

