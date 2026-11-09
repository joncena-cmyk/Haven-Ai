#!/usr/bin/env python3
"""
HAVEN AI — Graphical Dataset Recorder & ML Training Studio
==========================================================
All-in-one desktop graphical application for gathering synchronized
Engine Audio (44.1 kHz 16-bit WAV) + ESP32 3-Axis Vibration (921600-baud CSV),
validating hardware signal integrity, managing dataset folders, syncing with
the Laravel Web Portal, and retraining `ml_backend/model.pkl` in one click.
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import math
import os
import pathlib
import queue
import shutil
import subprocess
import sys
import threading
import time
from dataclasses import dataclass
from typing import Any, Callable, Dict, List, Optional, Tuple

import numpy as np

# Ensure local imports from `recorder/` and `ml_backend/` resolve cleanly
_RECORDER_DIR = pathlib.Path(__file__).resolve().parent
_REPO_ROOT = _RECORDER_DIR.parent
_ML_BACKEND_DIR = _REPO_ROOT / "ml_backend"
_HAVEN_DIR = _REPO_ROOT / "haven"

for _p in (_RECORDER_DIR, _ML_BACKEND_DIR):
    if str(_p) not in sys.path:
        sys.path.insert(0, str(_p))

from record_session import (  # noqa: E402
    ALLOWED_CONDITIONS,
    ensure_esp32_logger_mode,
    get_audio_input_devices,
    get_serial_ports,
    open_esp32_serial,
    parse_serial_csv_line,
    record_synchronized_session,
    sd,
    serial,
)
from validate_recording import (  # noqa: E402
    ValidationResult,
    import_validated_recording_to_laravel,
    validate_recording_folder,
)

CONDITION_META: Dict[str, Dict[str, str]] = {
    "normal": {
        "title": "Normal Operation",
        "short": "Normal",
        "color": "#10b981",
        "hint": "Healthy warm engine idling or revving smoothly",
    },
    "misfire": {
        "title": "Engine Misfire",
        "short": "Misfire",
        "color": "#ef4444",
        "hint": "Combustion dropout, uneven exhaust pulse, torque stumble",
    },
    "loose_component": {
        "title": "Loose Component",
        "short": "Loose Comp.",
        "color": "#f59e0b",
        "hint": "Loose bracket, heat shield rattle, or worn engine mount",
    },
    "valve_clearance": {
        "title": "Valve Clearance",
        "short": "Valve Lash",
        "color": "#8b5cf6",
        "hint": "Metallic valvetrain ticking / improper tappet lash",
    },
}


@dataclass
class RecordingEntry:
    folder: pathlib.Path
    session_id: str
    vehicle_id: str
    condition: str
    rpm_target: int
    duration_sec: float
    vib_rate_hz: float
    vib_peak_g: float
    audio_rms: float
    tap_synced: bool
    timestamp_label: str
    valid: bool
    errors: List[str]
    temperature_c: Optional[float] = None
    voltage_v: Optional[float] = None
    rpm_measured: Optional[int] = None


class DatasetStudioController:
    """
    Headless business-logic controller connecting:
      1. `recordings/` directory scanning & caching
      2. `validate_recording.py` quality checks & quarantine management
      3. `ml_backend/train.py` (Grouped GroupKFold + Hybrid training)
      4. `haven` Laravel database vehicle lookup & recording import
    """

    def __init__(
        self,
        recordings_root: Optional[pathlib.Path | str] = None,
        quarantine_root: Optional[pathlib.Path | str] = None,
        ml_backend_dir: Optional[pathlib.Path | str] = None,
        haven_dir: Optional[pathlib.Path | str] = None,
    ) -> None:
        self.recordings_root = pathlib.Path(recordings_root or (_REPO_ROOT / "recordings")).resolve()
        self.quarantine_root = pathlib.Path(
            quarantine_root or (_REPO_ROOT / "recordings_quarantine")
        ).resolve()
        self.ml_backend_dir = pathlib.Path(ml_backend_dir or _ML_BACKEND_DIR).resolve()
        self.haven_dir = pathlib.Path(haven_dir or _HAVEN_DIR).resolve()
        self._validation_cache: Dict[Tuple[str, float], ValidationResult] = {}

    def get_active_model_summary(self) -> Dict[str, Any]:
        """Read `ml_backend/model_meta.json` if available."""
        meta_path = self.ml_backend_dir / "model_meta.json"
        if not meta_path.exists():
            return {"loaded": False, "label": "Rule-Based Fallback (No model.pkl yet)"}
        try:
            meta = json.loads(meta_path.read_text(encoding="utf-8"))
            acc = float(meta.get("accuracy", 0.0)) * 100.0
            f1 = float(meta.get("f1_macro", 0.0)) * 100.0
            real_n = int(meta.get("real_samples_count", meta.get("n_samples", 0)))
            scheme = str(meta.get("evaluation_scheme", "trained"))
            mode_tag = "GroupKFold Real" if "GroupKFold" in scheme else "Hybrid / Calibrated"
            return {
                "loaded": True,
                "accuracy_pct": round(acc, 2),
                "f1_macro_pct": round(f1, 2),
                "real_samples": real_n,
                "scheme": mode_tag,
                "label": f"Active AI: {acc:.1f}% Acc · {f1:.1f}% F1 ({mode_tag})",
            }
        except Exception:
            return {"loaded": False, "label": "Model Metadata Unreadable"}

    def _validate_cached(self, folder: pathlib.Path) -> ValidationResult:
        try:
            mtimes = [
                (folder / name).stat().st_mtime
                for name in ("metadata.json", "vibration.csv", "audio.wav")
                if (folder / name).exists()
            ]
            sig = max(mtimes) if mtimes else 0.0
        except OSError:
            sig = 0.0

        cache_key = (str(folder), sig)
        if cache_key not in self._validation_cache:
            self._validation_cache[cache_key] = validate_recording_folder(folder)
        return self._validation_cache[cache_key]

    def scan_dataset(self) -> Dict[str, Any]:
        """
        Scan `self.recordings_root` for recording folders and compute dataset readiness.
        """
        entries: List[RecordingEntry] = []
        class_counts: Dict[str, int] = {c: 0 for c in ALLOWED_CONDITIONS}
        valid_vehicles: set[str] = set()
        invalid_count = 0

        if self.recordings_root.exists():
            meta_files = sorted(self.recordings_root.rglob("metadata.json"), reverse=True)
            for meta_file in meta_files:
                folder = meta_file.parent
                vres = self._validate_cached(folder)
                try:
                    meta = json.loads(meta_file.read_text(encoding="utf-8"))
                except Exception:
                    meta = {}

                cond = str(meta.get("condition") or "unknown").strip().lower()
                veh = str(meta.get("vehicle_id") or "unknown").strip()
                sess = str(meta.get("session_id") or "unknown").strip()
                rpm = int(meta.get("rpm_target") or 0)
                dur = float(
                    vres.metrics.get(
                        "audio_duration_sec",
                        meta.get("audio_duration_sec") or meta.get("duration_target_sec") or 0.0,
                    )
                )
                vib_hz = float(
                    vres.metrics.get(
                        "vibration_achieved_rate_hz",
                        meta.get("vibration_achieved_rate_hz") or 0.0,
                    )
                )
                peak_g = float(vres.metrics.get("vibration_peak_g", 0.0))
                aud_rms = float(vres.metrics.get("audio_rms", 0.0))
                tap_info = meta.get("tap_marker") if isinstance(meta.get("tap_marker"), dict) else {}
                tap_ok = bool(tap_info.get("tap_detected", False))
                ts_label = folder.name

                telem = meta.get("telemetry") if isinstance(meta.get("telemetry"), dict) else {}
                temp_c = meta.get("temperature_c") if meta.get("temperature_c") is not None else telem.get("temperature_c")
                volt_v = meta.get("voltage_v") if meta.get("voltage_v") is not None else telem.get("voltage_v")
                rpm_meas = meta.get("rpm_measured") if meta.get("rpm_measured") is not None else telem.get("rpm_measured")

                if vres.valid and cond in class_counts:
                    class_counts[cond] += 1
                    if veh:
                        valid_vehicles.add(veh)
                else:
                    invalid_count += 1

                entries.append(
                    RecordingEntry(
                        folder=folder,
                        session_id=sess,
                        vehicle_id=veh,
                        condition=cond,
                        rpm_target=rpm,
                        duration_sec=dur,
                        vib_rate_hz=vib_hz,
                        vib_peak_g=peak_g,
                        audio_rms=aud_rms,
                        tap_synced=tap_ok,
                        timestamp_label=ts_label,
                        valid=vres.valid,
                        errors=list(vres.errors),
                        temperature_c=float(temp_c) if temp_c is not None else None,
                        voltage_v=float(volt_v) if volt_v is not None else None,
                        rpm_measured=int(rpm_meas) if rpm_meas is not None else None,
                    )
                )

        entries.sort(key=lambda e: e.timestamp_label, reverse=True)
        missing_classes = [c for c, cnt in class_counts.items() if cnt == 0]
        ready_grouped = (
            len(valid_vehicles) >= 2 and len(missing_classes) == 0 and invalid_count == 0
        )

        return {
            "entries": entries,
            "total_count": len(entries),
            "valid_count": sum(1 for e in entries if e.valid),
            "invalid_count": invalid_count,
            "class_counts": class_counts,
            "vehicles": sorted(valid_vehicles),
            "missing_classes": missing_classes,
            "ready_for_grouped_eval": ready_grouped,
        }

    def quarantine_recording(self, folder: pathlib.Path | str) -> pathlib.Path:
        """
        Move an invalid or unwanted recording folder out of `recordings/` into
        `recordings_quarantine/` so `train.py` never fails on broken captures.
        """
        src = pathlib.Path(folder).resolve()
        if not src.exists():
            raise FileNotFoundError(f"Folder not found: {src}")

        self.quarantine_root.mkdir(parents=True, exist_ok=True)
        stamp = dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        dest_name = f"{stamp}_{src.parent.name}_{src.name}"
        dest = self.quarantine_root / dest_name
        shutil.move(str(src), str(dest))

        # Prune empty parent directories inside recordings_root
        parent = src.parent
        while parent != self.recordings_root and self.recordings_root in parent.parents:
            try:
                if not any(parent.iterdir()):
                    parent.rmdir()
                    parent = parent.parent
                else:
                    break
            except OSError:
                break

        return dest

    def train_from_recordings(
        self,
        mode: str = "auto",
        synthetic_per_class: int = 120,
        window_sec: float = 1.5,
        overlap: float = 0.5,
    ) -> Dict[str, Any]:
        """
        Train `ml_backend/model.pkl` using collected recordings in `self.recordings_root`.

        Modes:
          - `"grouped"`: Strict real-only `GroupKFold` evaluation (`>= 2` vehicles & all 4 classes,
                         no invalid folders in `recordings/`). Writes `evaluation_report.json`.
          - `"hybrid"` : Extracts 1.5s feature windows from all VALID recordings and combines
                         them with calibrated synthetic windows so early testing works at any time.
          - `"auto"`   : Uses `"grouped"` if dataset meets full thesis criteria (`>= 2` vehicles,
                         all 4 classes, 0 invalid folders); otherwise uses `"hybrid"`.
        """
        from features import extract_audio_windows, extract_vibration_windows
        from train import (
             build_feature_vector,
            evaluate_and_train_grouped,
            load_hybrid_dataset,
            load_recordings_dataset,
            train_and_export,
        )

        summary = self.scan_dataset()
        model_path = self.ml_backend_dir / "model.pkl"
        report_path = self.ml_backend_dir / "evaluation_report.json"

        chosen_mode = mode.lower().strip()
        if chosen_mode == "auto":
            chosen_mode = "grouped" if summary["ready_for_grouped_eval"] else "hybrid"

        if chosen_mode == "grouped":
            if summary["invalid_count"] > 0:
                raise ValueError(
                    f"Found {summary['invalid_count']} invalid recording folder(s) in '{self.recordings_root.name}'. "
                    "Please quarantine or remove failed recordings before running strict Grouped Evaluation."
                )
            X, y, groups, vehicles, _ = load_recordings_dataset(
                self.recordings_root,
                window_sec=window_sec,
                overlap=overlap,
                validate_first=True,
            )
            meta = evaluate_and_train_grouped(
                X,
                y,
                groups,
                vehicles,
                report_path=report_path,
                model_path=model_path,
                group_by="vehicle",
            )
            return {
                "mode_used": "grouped_real_only",
                "accuracy_pct": round(float(meta["accuracy"]) * 100.0, 2),
                "f1_macro_pct": round(float(meta["f1_macro"]) * 100.0, 2),
                "real_windows": int(meta["n_samples"]),
                "synthetic_windows": 0,
                "n_vehicles": int(meta.get("n_vehicles", len(summary["vehicles"]))),
                "model_path": str(model_path),
                "report_path": str(report_path),
                "meta": meta,
            }

        # Hybrid mode: gather all windows from valid recordings + calibrated synthetic data
        real_records: List[Dict[str, Any]] = []
        real_groups: List[str] = []

        for entry in summary["entries"]:
            if not entry.valid or entry.condition not in ALLOWED_CONDITIONS:
                continue
            vib_path = entry.folder / "vibration.csv"
            aud_path = entry.folder / "audio.wav"
            vib_wins, _, _ = extract_vibration_windows(
                str(vib_path), ext="csv", window_sec=window_sec, overlap=overlap
            )
            aud_wins, _, _ = extract_audio_windows(
                str(aud_path), window_sec=window_sec, overlap=overlap
            )
            n_win = min(len(vib_wins), len(aud_wins))
            for w_idx in range(n_win):
                merged = {**aud_wins[w_idx], **vib_wins[w_idx]}
                build_feature_vector(merged)  # verify valid feature vector
                real_records.append(
                    {
                        "true_condition": entry.condition,
                        "vehicle_id": entry.vehicle_id,
                        "session_id": entry.session_id,
                        "features": merged,
                    }
                )
                real_groups.append(f"{entry.vehicle_id}::{entry.session_id}")

        X, y, real_c, synth_c = load_hybrid_dataset(
            real_records=real_records,
            augment_synthetic=True,
            synthetic_per_class=synthetic_per_class,
        )
        groups_arr = None
        if real_c > 0 and len(set(real_groups)) >= 2:
            synth_groups = [f"synth_grp_{i % 5}" for i in range(synth_c)]
            groups_arr = np.asarray(real_groups[:real_c] + synth_groups, dtype=object)

        meta = train_and_export(
            X,
            y,
            model_path=str(model_path),
            real_count=real_c,
            synth_count=synth_c,
            groups=groups_arr,
        )
        return {
            "mode_used": "hybrid_calibrated",
            "accuracy_pct": round(float(meta["accuracy"]) * 100.0, 2),
            "f1_macro_pct": round(float(meta["f1_macro"]) * 100.0, 2),
            "real_windows": int(real_c),
            "synthetic_windows": int(synth_c),
            "n_vehicles": len(summary["vehicles"]),
            "model_path": str(model_path),
            "report_path": None,
            "meta": meta,
        }

    def list_laravel_vehicles(self) -> List[Dict[str, Any]]:
        """Query registered vehicles from Laravel so recordings can be imported into the web app."""
        if not (self.haven_dir / "artisan").exists():
            return []
        php_code = (
            "require 'vendor/autoload.php';"
            "$app = require_once 'bootstrap/app.php';"
            "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"
            "echo json_encode(App\\Models\\Vehicle::select('id','make','model','year','plate_number')->orderBy('id')->get());"
        )
        try:
            proc = subprocess.run(
                ["php", "-r", php_code],
                cwd=str(self.haven_dir),
                capture_output=True,
                text=True,
                timeout=8,
                check=True,
            )
            data = json.loads(proc.stdout.strip() or "[]")
            return data if isinstance(data, list) else []
        except Exception:
            return []

    def import_recording_to_web(
        self,
        folder: pathlib.Path | str,
        laravel_vehicle_id: int,
        verified_by: str = "",
        analyze: bool = True,
    ) -> str:
        proc = import_validated_recording_to_laravel(
            pathlib.Path(folder),
            haven_dir=self.haven_dir,
            vehicle_id=int(laravel_vehicle_id),
            verified_by=verified_by or None,
            analyze=analyze,
        )
        return proc.stdout.strip()


class LiveSensorMonitor:
    """
    Background non-recording preview of Microphone + ESP32 Serial Accelerometer
    so the user can visually confirm hardware readings before recording a sample.
    """

    def __init__(self) -> None:
        self._stop = threading.Event()
        self._thread: Optional[threading.Thread] = None
        self._lock = threading.Lock()
        self.state: Dict[str, Any] = self._empty_state()

    @staticmethod
    def _empty_state() -> Dict[str, Any]:
        return {
            "running": False,
            "mode": "idle",
            "error": "",
            "audio_peak": 0.0,
            "audio_rms": 0.0,
            "vib_xyz": (0.0, 0.0, 0.0),
            "vib_mag_g": 0.0,
            "vib_dyn_g": 0.0,
            "vib_rate_hz": 0.0,
            "audio_wave": [0.0] * 80,
            "vib_wave": [0.0] * 80,
        }

    def is_running(self) -> bool:
        return self._thread is not None and self._thread.is_alive()

    def start(
        self,
        port: Optional[str],
        audio_device: Optional[Any],
        baud_rate: int = 921600,
        audio_sr: int = 44100,
        *,
        enable_audio: bool = True,
        enable_vib: bool = True,
    ) -> None:
        self.stop()
        self._stop.clear()
        mode_name = "both" if (enable_audio and enable_vib) else ("mic" if enable_audio else "vib")
        with self._lock:
            self.state = self._empty_state()
            self.state["running"] = True
            self.state["mode"] = mode_name

        self._thread = threading.Thread(
            target=self._run,
            args=(port, audio_device, baud_rate, audio_sr, enable_audio, enable_vib),
            daemon=True,
        )
        self._thread.start()

    def stop(self) -> None:
        self._stop.set()
        if self._thread is not None and self._thread.is_alive():
            self._thread.join(timeout=1.5)
        self._thread = None
        with self._lock:
            self.state["running"] = False
            self.state["mode"] = "idle"

    def snapshot(self) -> Dict[str, Any]:
        with self._lock:
            return dict(self.state)

    def _run(
        self,
        port: Optional[str],
        audio_device: Optional[Any],
        baud_rate: int,
        audio_sr: int,
        enable_audio: bool,
        enable_vib: bool,
    ) -> None:
        vib_ring: List[Tuple[int, float, float, float]] = []
        aud_ring: List[float] = [0.0] * 80
        ser_err: List[str] = []

        def serial_reader() -> None:
            if not enable_vib or not port or serial is None:
                return
            ser = None
            try:
                ser = open_esp32_serial(port, baud_rate=baud_rate, timeout=0.05)
                ensure_esp32_logger_mode(ser, target_baud=baud_rate)
                ser.reset_input_buffer()
                t_start = time.monotonic()
                total_n = 0
                while not self._stop.is_set():
                    raw = ser.readline()
                    if not raw:
                        continue
                    line = raw.decode("utf-8", errors="replace").strip()
                    parsed = parse_serial_csv_line(line)
                    if parsed is not None:
                        total_n += 1
                        vib_ring.append(parsed)
                        if len(vib_ring) > 300:
                            del vib_ring[:-300]
                        if total_n % 25 == 0:
                            elapsed = max(time.monotonic() - t_start, 1e-3)
                            arr = np.asarray([s[1:4] for s in vib_ring[-160:]], dtype=np.float64)
                            ax, ay, az = parsed[1], parsed[2], parsed[3]
                            mag = math.sqrt(ax * ax + ay * ay + az * az)
                            dyn_arr = np.linalg.norm(
                                arr - np.mean(arr, axis=0, keepdims=True), axis=1
                            )
                            dyn_rms = float(np.sqrt(np.mean(dyn_arr ** 2))) if dyn_arr.size else 0.0
                            step = max(1, dyn_arr.size // 80)
                            tail = dyn_arr[::step][-80:].tolist()
                            with self._lock:
                                self.state["vib_xyz"] = (ax, ay, az)
                                self.state["vib_mag_g"] = mag
                                self.state["vib_dyn_g"] = dyn_rms
                                self.state["vib_rate_hz"] = total_n / elapsed
                                self.state["vib_wave"] = tail
            except Exception as exc:
                msg = str(exc)
                if "FileNotFoundError" in msg or "cannot find the file" in msg:
                    ser_err.append(
                        f"ESP32 USB ({port}) is unplugged. Plug in USB cable & click Refresh Devices (or use 'Test Mic' for audio-only)."
                    )
                elif "PermissionError" in msg or "Access is denied" in msg:
                    ser_err.append(
                        f"ESP32 USB ({port}) is locked by Arduino Serial Monitor or Browser. Close them and retry."
                    )
                else:
                    ser_err.append(f"ESP32 Serial ({port}): {msg}")
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

        ser_thread = threading.Thread(target=serial_reader, daemon=True)
        ser_thread.start()

        def audio_cb(indata: np.ndarray, frames: int, time_info: Any, status: Any) -> None:
            if self._stop.is_set():
                return
            flat = indata.reshape(-1).astype(np.float64) / 32768.0
            if flat.size == 0:
                return
            pk = float(np.max(np.abs(flat)))
            rms = float(np.sqrt(np.mean((flat - np.mean(flat)) ** 2)))
            step = max(1, flat.size // 20)
            pts = flat[::step][-20:].tolist()
            nonlocal aud_ring
            aud_ring = (aud_ring + pts)[-80:]
            with self._lock:
                self.state["audio_peak"] = pk
                self.state["audio_rms"] = rms
                self.state["audio_wave"] = list(aud_ring)

        try:
            if enable_audio and sd is not None:
                with sd.InputStream(
                    samplerate=audio_sr,
                    channels=1,
                    dtype="int16",
                    device=audio_device,
                    callback=audio_cb,
                ):
                    while not self._stop.is_set():
                        if ser_err:
                            with self._lock:
                                self.state["error"] = ser_err[0]
                        time.sleep(0.08)
            else:
                while not self._stop.is_set():
                    if ser_err:
                        with self._lock:
                            self.state["error"] = ser_err[0]
                    time.sleep(0.08)
        except Exception as exc:
            with self._lock:
                self.state["error"] = f"Audio Device Error: {exc}"
        finally:
            self._stop.set()
            ser_thread.join(timeout=1.0)
            with self._lock:
                if ser_err and not self.state["error"]:
                    self.state["error"] = ser_err[0]
                self.state["running"] = False


# =====================================================================
# TKINTER GRAPHICAL INTERFACE
# =====================================================================
def launch_gui(controller: Optional[DatasetStudioController] = None) -> None:
    import tkinter as tk
    from tkinter import messagebox, simpledialog, ttk

    ctrl = controller or DatasetStudioController()
    monitor = LiveSensorMonitor()
    ui_queue: "queue.Queue[Callable[[], None]]" = queue.Queue()

    root = tk.Tk()
    root.title("HAVEN AI — Graphical Dataset Recorder & ML Studio")
    root.geometry("1220x830")
    root.minsize(1060, 740)
    root.configure(bg="#0f172a")

    # Color Palette
    BG_MAIN = "#0f172a"
    BG_CARD = "#1e293b"
    BG_INPUT = "#0b1120"
    FG_TEXT = "#f8fafc"
    FG_MUTED = "#94a3b8"
    ACCENT_BLUE = "#38bdf8"
    ACCENT_GREEN = "#10b981"
    ACCENT_RED = "#ef4444"
    ACCENT_AMBER = "#f59e0b"

    style = ttk.Style(root)
    try:
        style.theme_use("clam")
    except Exception:
        pass

    style.configure("TCombobox", fieldbackground="#f8fafc", background="#e2e8f0", foreground="#0f172a")
    style.configure(
        "Haven.Treeview",
        background="#0b1120",
        foreground=FG_TEXT,
        fieldbackground="#0b1120",
        rowheight=26,
        font=("Segoe UI", 9),
    )
    style.configure(
        "Haven.Treeview.Heading",
        background="#334155",
        foreground=FG_TEXT,
        font=("Segoe UI", 9, "bold"),
    )
    style.map("Haven.Treeview", background=[("selected", "#0284c7")])

    # State Variables
    port_var = tk.StringVar()
    mic_var = tk.StringVar()
    condition_var = tk.StringVar(value="normal")
    vehicle_var = tk.StringVar(value="car1_vios")
    rpm_var = tk.StringVar(value="850")
    duration_var = tk.StringVar(value="10")
    session_var = tk.StringVar(value=f"SESS-{dt.datetime.now().strftime('%Y%m%d')}")
    sensor_pos_var = tk.StringVar(value="Valve Cover")
    mic_pos_var = tk.StringVar(value="25cm Front of Engine")
    operator_var = tk.StringVar(value="HAVEN Team")
    notes_var = tk.StringVar(value="")
    train_mode_var = tk.StringVar(value="auto")

    port_map: Dict[str, str] = {}
    mic_map: Dict[str, Optional[int]] = {}
    recording_active = {"flag": False, "stop_event": threading.Event(), "last_failed_folder": None}

    # ── HEADER BAR ──
    header = tk.Frame(root, bg="#020617", padx=18, pady=12)
    header.pack(fill="x")

    title_box = tk.Frame(header, bg="#020617")
    title_box.pack(side="left")
    tk.Label(
        title_box,
        text="HAVEN AI · Graphical Dataset Recorder & ML Studio",
        font=("Segoe UI", 15, "bold"),
        fg=FG_TEXT,
        bg="#020617",
    ).pack(anchor="w")
    tk.Label(
        title_box,
        text="Follow Steps 1 → 4 below to test sensors, capture synchronized Audio + ESP32 Vibration, and train your AI model.",
        font=("Segoe UI", 9),
        fg=FG_MUTED,
        bg="#020617",
    ).pack(anchor="w")

    model_badge_lbl = tk.Label(
        header,
        text="Checking Model...",
        font=("Segoe UI", 10, "bold"),
        fg="#ecfdf5",
        bg="#065f46",
        padx=12,
        pady=6,
    )
    model_badge_lbl.pack(side="right", pady=4)

    # ── TOP SPLIT ROW (STEP 1 LEFT + STEP 2 RIGHT) ──
    top_row = tk.Frame(root, bg=BG_MAIN, padx=14, pady=8)
    top_row.pack(fill="x")
    top_row.columnconfigure(0, weight=5)
    top_row.columnconfigure(1, weight=6)

    # === STEP 1 CARD: HARDWARE & LIVE SENSOR PREVIEW ===
    card1 = tk.Frame(top_row, bg=BG_CARD, padx=14, pady=12, highlightthickness=1, highlightbackground="#334155")
    card1.grid(row=0, column=0, sticky="nsew", padx=(0, 7))

    s1_hdr = tk.Frame(card1, bg=BG_CARD)
    s1_hdr.pack(fill="x", pady=(0, 8))
    tk.Label(
        s1_hdr,
        text="STEP 1 · Connect Hardware & Test Sensors",
        font=("Segoe UI", 11, "bold"),
        fg=ACCENT_BLUE,
        bg=BG_CARD,
    ).pack(side="left")
    btn_refresh_hw = tk.Button(
        s1_hdr,
        text="🔄 Refresh Devices",
        font=("Segoe UI", 8, "bold"),
        bg="#334155",
        fg=FG_TEXT,
        activebackground="#475569",
        activeforeground=FG_TEXT,
        relief="flat",
        padx=8,
        pady=3,
    )
    btn_refresh_hw.pack(side="right")

    hw_grid = tk.Frame(card1, bg=BG_CARD)
    hw_grid.pack(fill="x")
    hw_grid.columnconfigure(1, weight=1)

    tk.Label(hw_grid, text="ESP32 USB Port:", font=("Segoe UI", 9, "bold"), fg=FG_TEXT, bg=BG_CARD).grid(
        row=0, column=0, sticky="w", pady=3, padx=(0, 8)
    )
    port_combo = ttk.Combobox(hw_grid, textvariable=port_var, state="readonly")
    port_combo.grid(row=0, column=1, sticky="ew", pady=3)

    tk.Label(hw_grid, text="Microphone:", font=("Segoe UI", 9, "bold"), fg=FG_TEXT, bg=BG_CARD).grid(
        row=1, column=0, sticky="w", pady=3, padx=(0, 8)
    )
    mic_combo = ttk.Combobox(hw_grid, textvariable=mic_var, state="readonly")
    mic_combo.grid(row=1, column=1, sticky="ew", pady=3)

    preview_bar = tk.Frame(card1, bg=BG_CARD, pady=6)
    preview_bar.pack(fill="x")

    btn_test_mic = tk.Button(
        preview_bar,
        text="🎙️ Test Mic",
        font=("Segoe UI", 8, "bold"),
        bg="#0369a1",
        fg=FG_TEXT,
        activebackground="#0284c7",
        activeforeground=FG_TEXT,
        relief="flat",
        padx=8,
        pady=4,
    )
    btn_test_mic.pack(side="left", padx=(0, 4))

    btn_test_vib = tk.Button(
        preview_bar,
        text="📳 Test Vibration",
        font=("Segoe UI", 8, "bold"),
        bg="#b45309",
        fg=FG_TEXT,
        activebackground="#d97706",
        activeforeground=FG_TEXT,
        relief="flat",
        padx=8,
        pady=4,
    )
    btn_test_vib.pack(side="left", padx=(0, 4))

    btn_preview = tk.Button(
        preview_bar,
        text="📡 Test Both",
        font=("Segoe UI", 8, "bold"),
        bg="#0f766e",
        fg=FG_TEXT,
        activebackground="#0d9488",
        activeforeground=FG_TEXT,
        relief="flat",
        padx=8,
        pady=4,
    )
    btn_preview.pack(side="left", padx=(0, 4))

    btn_stop_preview = tk.Button(
        preview_bar,
        text="⏹ Stop",
        font=("Segoe UI", 8, "bold"),
        bg="#475569",
        fg=FG_TEXT,
        relief="flat",
        padx=7,
        pady=4,
        state="disabled",
    )
    btn_stop_preview.pack(side="left")

    lbl_sensor_readout = tk.Label(
        preview_bar,
        text="Idle — Test Mic first, then Vibration",
        font=("Consolas", 8),
        fg=FG_MUTED,
        bg=BG_CARD,
    )
    lbl_sensor_readout.pack(side="right")

    scope_canvas = tk.Canvas(
        card1,
        height=115,
        bg=BG_INPUT,
        highlightthickness=1,
        highlightbackground="#334155",
    )
    scope_canvas.pack(fill="x", pady=(4, 2))

    lbl_xyz_detail = tk.Label(
        card1,
        text="Audio Peak: 0.0%  |  ESP32 Accel: X=0.00g  Y=0.00g  Z=0.00g  (|a|=0.00g, 0 Hz)",
        font=("Consolas", 8),
        fg="#cbd5e1",
        bg=BG_CARD,
    )
    lbl_xyz_detail.pack(anchor="w")

    # === STEP 2 CARD: ENGINE CONDITION & VEHICLE METADATA ===
    card2 = tk.Frame(top_row, bg=BG_CARD, padx=14, pady=12, highlightthickness=1, highlightbackground="#334155")
    card2.grid(row=0, column=1, sticky="nsew", padx=(7, 0))

    tk.Label(
        card2,
        text="STEP 2 · Choose Engine Condition & Vehicle Info",
        font=("Segoe UI", 11, "bold"),
        fg=ACCENT_BLUE,
        bg=BG_CARD,
    ).pack(anchor="w", pady=(0, 6))

    cond_frame = tk.Frame(card2, bg=BG_CARD)
    cond_frame.pack(fill="x", pady=(0, 4))
    for i in range(4):
        cond_frame.columnconfigure(i, weight=1)

    cond_buttons: Dict[str, tk.Button] = {}
    lbl_cond_hint = tk.Label(
        card2,
        text=CONDITION_META["normal"]["hint"],
        font=("Segoe UI", 8, "italic"),
        fg=FG_MUTED,
        bg=BG_CARD,
    )

    def select_condition(c_key: str) -> None:
        condition_var.set(c_key)
        lbl_cond_hint.config(text=f"Selected: {CONDITION_META[c_key]['title']} — {CONDITION_META[c_key]['hint']}")
        for k, b in cond_buttons.items():
            if k == c_key:
                b.config(bg=CONDITION_META[k]["color"], fg="#ffffff", relief="sunken")
            else:
                b.config(bg="#334155", fg="#cbd5e1", relief="flat")

    for idx, (c_key, c_info) in enumerate(CONDITION_META.items()):
        btn = tk.Button(
            cond_frame,
            text=c_info["short"],
            font=("Segoe UI", 9, "bold"),
            pady=6,
            command=lambda k=c_key: select_condition(k),
        )
        btn.grid(row=0, column=idx, sticky="ew", padx=3)
        cond_buttons[c_key] = btn

    lbl_cond_hint.pack(anchor="w", pady=(2, 8))
    select_condition("normal")

    form = tk.Frame(card2, bg=BG_CARD)
    form.pack(fill="x")
    for c_i in (1, 3):
        form.columnconfigure(c_i, weight=1)

    def _add_field(row: int, col: int, label: str, widget: tk.Widget) -> None:
        tk.Label(form, text=label, font=("Segoe UI", 9), fg="#cbd5e1", bg=BG_CARD).grid(
            row=row, column=col, sticky="w", padx=(0 if col == 0 else 10, 6), pady=3
        )
        widget.grid(row=row, column=col + 1, sticky="ew", pady=3)

    _add_field(
        0,
        0,
        "Vehicle Name:",
        tk.Entry(form, textvariable=vehicle_var, bg="#f8fafc", fg="#0f172a", font=("Segoe UI", 9)),
    )
    _add_field(
        0,
        2,
        "Target RPM:",
        ttk.Combobox(form, textvariable=rpm_var, values=["750", "800", "850", "1000", "1500", "2000", "2500"]),
    )
    _add_field(
        1,
        0,
        "Duration (sec):",
        ttk.Combobox(form, textvariable=duration_var, values=["5", "10", "15", "20"]),
    )
    _add_field(
        1,
        2,
        "Session ID:",
        tk.Entry(form, textvariable=session_var, bg="#f8fafc", fg="#0f172a", font=("Segoe UI", 9)),
    )
    _add_field(
        2,
        0,
        "Sensor Mount:",
        ttk.Combobox(
            form,
            textvariable=sensor_pos_var,
            values=["Valve Cover", "Cylinder Head", "Engine Block", "Alternator Bracket"],
        ),
    )
    _add_field(
        2,
        2,
        "Mic Position:",
        ttk.Combobox(
            form,
            textvariable=mic_pos_var,
            values=["25cm Front of Engine", "15cm Above Valve Cover", "30cm Intake Side"],
        ),
    )
    _add_field(
        3,
        0,
        "Operator:",
        tk.Entry(form, textvariable=operator_var, bg="#f8fafc", fg="#0f172a", font=("Segoe UI", 9)),
    )
    _add_field(
        3,
        2,
        "Notes (Opt.):",
        tk.Entry(form, textvariable=notes_var, bg="#f8fafc", fg="#0f172a", font=("Segoe UI", 9)),
    )

    # ── STEP 3 CARD: SYNCHRONIZED RECORDING & INSTANT VALIDATION ──
    card3 = tk.Frame(root, bg=BG_CARD, padx=14, pady=10, highlightthickness=1, highlightbackground="#334155")
    card3.pack(fill="x", padx=14, pady=(2, 8))

    rec_top = tk.Frame(card3, bg=BG_CARD)
    rec_top.pack(fill="x")

    tk.Label(
        rec_top,
        text="STEP 3 · Capture Synchronized Sample",
        font=("Segoe UI", 11, "bold"),
        fg=ACCENT_BLUE,
        bg=BG_CARD,
    ).pack(side="left", padx=(0, 14))

    btn_record = tk.Button(
        rec_top,
        text="🔴  RECORD AUDIO + VIBRATION SAMPLE",
        font=("Segoe UI", 11, "bold"),
        bg=ACCENT_RED,
        fg="#ffffff",
        activebackground="#dc2626",
        activeforeground="#ffffff",
        relief="flat",
        padx=18,
        pady=7,
    )
    btn_record.pack(side="left")

    btn_cancel_rec = tk.Button(
        rec_top,
        text="⏹ Cancel",
        font=("Segoe UI", 9, "bold"),
        bg="#475569",
        fg=FG_TEXT,
        relief="flat",
        padx=10,
        pady=7,
        state="disabled",
    )
    btn_cancel_rec.pack(side="left", padx=(8, 14))

    progress_bar = ttk.Progressbar(rec_top, orient="horizontal", mode="determinate", maximum=100.0)
    progress_bar.pack(side="left", fill="x", expand=True, padx=(0, 10))

    lbl_timer = tk.Label(
        rec_top,
        text="Ready",
        font=("Consolas", 10, "bold"),
        fg=FG_TEXT,
        bg=BG_CARD,
        width=22,
        anchor="e",
    )
    lbl_timer.pack(side="right")

    banner_frame = tk.Frame(card3, bg="#0b1120", padx=10, pady=6)
    banner_frame.pack(fill="x", pady=(8, 0))

    lbl_result_banner = tk.Label(
        banner_frame,
        text="💡 Tip: Click 'Test Sensors Live' first to verify your Mic & ESP32, then click RECORD. Lightly tap the sensor mount in the first 2s!",
        font=("Segoe UI", 9),
        fg="#cbd5e1",
        bg="#0b1120",
        anchor="w",
        justify="left",
    )
    lbl_result_banner.pack(side="left", fill="x", expand=True)

    btn_quarantine_last = tk.Button(
        banner_frame,
        text="🗑 Discard Failed Take",
        font=("Segoe UI", 8, "bold"),
        bg="#7f1d1d",
        fg="#fecaca",
        relief="flat",
        padx=8,
        pady=2,
    )

    # ── STEP 4 CARD: DATASET MANAGER & ONE-CLICK ML TRAINING ──
    card4 = tk.Frame(root, bg=BG_CARD, padx=14, pady=10, highlightthickness=1, highlightbackground="#334155")
    card4.pack(fill="both", expand=True, padx=14, pady=(0, 12))

    s4_top = tk.Frame(card4, bg=BG_CARD)
    s4_top.pack(fill="x", pady=(0, 6))

    tk.Label(
        s4_top,
        text="STEP 4 · Recorded Dataset & One-Click Machine Learning Trainer",
        font=("Segoe UI", 11, "bold"),
        fg=ACCENT_BLUE,
        bg=BG_CARD,
    ).pack(side="left")

    lbl_readiness = tk.Label(
        s4_top,
        text="Scanning recordings/ ...",
        font=("Segoe UI", 9, "bold"),
        fg=ACCENT_AMBER,
        bg=BG_CARD,
    )
    lbl_readiness.pack(side="right")

    counts_strip = tk.Frame(card4, bg="#0b1120", padx=10, pady=5)
    counts_strip.pack(fill="x", pady=(0, 6))
    lbl_counts_summary = tk.Label(
        counts_strip,
        text="",
        font=("Consolas", 9, "bold"),
        fg="#e2e8f0",
        bg="#0b1120",
    )
    lbl_counts_summary.pack(side="left")

    table_wrap = tk.Frame(card4, bg=BG_CARD)
    table_wrap.pack(fill="both", expand=True)

    cols = (
        "status",
        "condition",
        "vehicle",
        "rpm",
        "duration",
        "vib_hz",
        "peak_g",
        "tap",
        "timestamp",
    )
    tree = ttk.Treeview(table_wrap, columns=cols, show="headings", style="Haven.Treeview", height=7)
    headings = [
        ("status", "Validation", 95),
        ("condition", "Condition", 125),
        ("vehicle", "Vehicle ID", 130),
        ("rpm", "RPM", 65),
        ("duration", "Duration", 75),
        ("vib_hz", "Vib Rate", 90),
        ("peak_g", "Peak G", 80),
        ("tap", "Tap Sync", 80),
        ("timestamp", "Captured UTC", 160),
    ]
    for col_id, col_title, col_w in headings:
        tree.heading(col_id, text=col_title)
        tree.column(col_id, width=col_w, anchor="center" if col_id != "vehicle" else "w")

    vsb = ttk.Scrollbar(table_wrap, orient="vertical", command=tree.yview)
    tree.configure(yscrollcommand=vsb.set)
    tree.pack(side="left", fill="both", expand=True)
    vsb.pack(side="right", fill="y")

    action_bar = tk.Frame(card4, bg=BG_CARD, pady=6)
    action_bar.pack(fill="x")

    btn_open_dir = tk.Button(
        action_bar,
        text="📂 Open Recordings Folder",
        font=("Segoe UI", 9),
        bg="#334155",
        fg=FG_TEXT,
        relief="flat",
        padx=10,
        pady=5,
    )
    btn_open_dir.pack(side="left", padx=(0, 6))

    btn_quarantine_sel = tk.Button(
        action_bar,
        text="🗑 Quarantine Selected",
        font=("Segoe UI", 9),
        bg="#334155",
        fg=FG_TEXT,
        relief="flat",
        padx=10,
        pady=5,
    )
    btn_quarantine_sel.pack(side="left", padx=(0, 6))

    btn_sync_web = tk.Button(
        action_bar,
        text="📥 Sync Selected to HAVEN Web DB",
        font=("Segoe UI", 9),
        bg="#0f766e",
        fg=FG_TEXT,
        relief="flat",
        padx=10,
        pady=5,
    )
    btn_sync_web.pack(side="left", padx=(0, 6))

    # Right side: ML Training controls
    btn_train = tk.Button(
        action_bar,
        text="🧠  TRAIN ML MODEL NOW",
        font=("Segoe UI", 10, "bold"),
        bg=ACCENT_GREEN,
        fg="#022c22",
        activebackground="#059669",
        activeforeground="#ffffff",
        relief="flat",
        padx=16,
        pady=5,
    )
    btn_train.pack(side="right")

    mode_combo = ttk.Combobox(
        action_bar,
        textvariable=train_mode_var,
        state="readonly",
        width=28,
        values=[
            "auto",
            "hybrid",
            "grouped",
        ],
    )
    mode_combo.pack(side="right", padx=(6, 10))
    tk.Label(action_bar, text="Training Mode:", font=("Segoe UI", 9), fg=FG_MUTED, bg=BG_CARD).pack(
        side="right"
    )

    # ── UI LOGIC & CALLBACKS ──
    item_to_folder: Dict[str, pathlib.Path] = {}

    def refresh_model_badge() -> None:
        info = ctrl.get_active_model_summary()
        model_badge_lbl.config(
            text=info["label"],
            bg="#065f46" if info["loaded"] else "#78350f",
        )

    def refresh_devices() -> None:
        port_map.clear()
        ports = get_serial_ports()
        port_labels: List[str] = []
        for p in ports:
            lbl = p["label"]
            port_map[lbl] = p["device"]
            port_labels.append(lbl)
        if not port_labels:
            port_labels = ["(No ESP32 USB Serial Port Detected)"]
        port_combo["values"] = port_labels
        if port_var.get() not in port_labels:
            port_var.set(port_labels[0])

        mic_map.clear()
        mics = get_audio_input_devices()
        mic_labels: List[str] = []
        default_lbl: Optional[str] = None
        for m in mics:
            lbl = m["label"]
            mic_map[lbl] = int(m["index"])
            mic_labels.append(lbl)
            if m.get("is_default") and default_lbl is None:
                default_lbl = lbl
        if not mic_labels:
            mic_labels = ["(Default System Microphone)"]
            mic_map[mic_labels[0]] = None
        mic_combo["values"] = mic_labels
        if mic_var.get() not in mic_labels:
            mic_var.set(default_lbl or mic_labels[0])

    def refresh_dataset_table() -> None:
        summary = ctrl.scan_dataset()
        tree.delete(*tree.get_children())
        item_to_folder.clear()

        for idx, entry in enumerate(summary["entries"]):
            iid = f"rec_{idx}"
            item_to_folder[iid] = entry.folder
            status_txt = "✅ PASS" if entry.valid else "❌ FAIL"
            cond_title = CONDITION_META.get(entry.condition, {}).get("short", entry.condition)
            tree.insert(
                "",
                "end",
                iid=iid,
                values=(
                    status_txt,
                    cond_title,
                    entry.vehicle_id,
                    entry.rpm_target,
                    f"{entry.duration_sec:.1f}s",
                    f"{entry.vib_rate_hz:.0f} Hz",
                    f"{entry.vib_peak_g:.2f}g",
                    "Yes" if entry.tap_synced else "No",
                    entry.timestamp_label,
                ),
            )

        cc = summary["class_counts"]
        lbl_counts_summary.config(
            text=(
                f"Valid Samples  ▸  Normal: {cc['normal']}   |   "
                f"Misfire: {cc['misfire']}   |   "
                f"Loose Comp.: {cc['loose_component']}   |   "
                f"Valve Lash: {cc['valve_clearance']}   |   "
                f"Distinct Vehicles: {len(summary['vehicles'])}   |   "
                f"Invalid Takes: {summary['invalid_count']}"
            )
        )

        if summary["ready_for_grouped_eval"]:
            lbl_readiness.config(
                text="✅ Thesis Ready: ≥2 Vehicles & All 4 Classes Collected (GroupKFold Unlocked)",
                fg=ACCENT_GREEN,
            )
        else:
            needed_parts = []
            if len(summary["vehicles"]) < 2:
                needed_parts.append(f"{2 - len(summary['vehicles'])} more vehicle(s)")
            if summary["missing_classes"]:
                needed_parts.append("missing: " + ", ".join(summary["missing_classes"]))
            if summary["invalid_count"] > 0:
                needed_parts.append(f"quarantine {summary['invalid_count']} failed take(s)")
            lbl_readiness.config(
                text="Hybrid Training Ready · For Full GroupKFold: " + " | ".join(needed_parts),
                fg=ACCENT_AMBER,
            )

    def draw_scope(audio_wave: List[float], vib_wave: List[float]) -> None:
        scope_canvas.delete("all")
        w = max(scope_canvas.winfo_width(), 320)
        h = max(scope_canvas.winfo_height(), 110)
        mid1 = h * 0.28
        mid2 = h * 0.75

        scope_canvas.create_line(0, h * 0.5, w, h * 0.5, fill="#1e293b", dash=(3, 3))
        scope_canvas.create_text(8, 10, text="MIC AUDIO", fill=ACCENT_BLUE, anchor="nw", font=("Consolas", 8, "bold"))
        scope_canvas.create_text(
            8, int(h * 0.54), text="ESP32 VIBRATION (g)", fill=ACCENT_AMBER, anchor="nw", font=("Consolas", 8, "bold")
        )

        if len(audio_wave) >= 2:
            pts_a: List[float] = []
            n_a = len(audio_wave)
            for i, val in enumerate(audio_wave):
                x = (i / (n_a - 1)) * w
                y = mid1 - max(-1.0, min(1.0, val * 2.2)) * (h * 0.20)
                pts_a.extend([x, y])
            scope_canvas.create_line(*pts_a, fill=ACCENT_BLUE, width=1.5)

        if len(vib_wave) >= 2:
            pts_v: List[float] = []
            n_v = len(vib_wave)
            scale_v = max(max(vib_wave), 0.35)
            for i, val in enumerate(vib_wave):
                x = (i / (n_v - 1)) * w
                norm = min(1.0, max(0.0, val / scale_v))
                y = (h - 8) - norm * (h * 0.38)
                pts_v.extend([x, y])
            scope_canvas.create_line(*pts_v, fill=ACCENT_AMBER, width=1.5)

    def stop_preview() -> None:
        monitor.stop()
        btn_stop_preview.config(state="disabled")
        lbl_sensor_readout.config(text="Sensor test stopped", fg=FG_MUTED)

    def start_sensor_test(enable_audio: bool, enable_vib: bool) -> None:
        if recording_active["flag"]:
            return
        if enable_vib:
            refresh_devices()
        selected_port = port_map.get(port_var.get()) if enable_vib else None
        if enable_vib and not selected_port:
            if not enable_audio:
                messagebox.showwarning(
                    "ESP32 USB Cable Unplugged",
                    "No ESP32 USB Serial port (e.g. COM4) was found.\n\n"
                    "• Wi-Fi only sends 5-second dashboard summaries.\n"
                    "• For 1000 Hz vibration testing & ML dataset recording, keep the ESP32 plugged into your laptop's USB port.\n\n"
                    "Plug in the USB cable and click 'Test Vibration' again (or click 'Test Mic' to test audio only).",
                )
                return
            enable_vib = False
        selected_mic = mic_map.get(mic_var.get()) if enable_audio else None
        monitor.start(
            port=selected_port,
            audio_device=selected_mic,
            enable_audio=enable_audio,
            enable_vib=enable_vib,
        )
        btn_stop_preview.config(state="normal")
        tag = "Mic + Vibration" if (enable_audio and enable_vib) else ("Mic Only (USB Unplugged)" if enable_audio else "Vibration Only")
        lbl_sensor_readout.config(text=f"Testing {tag}...", fg=ACCENT_GREEN)

    def set_banner(text: str, bg_color: str = "#0b1120", fg_color: str = "#cbd5e1", show_discard: bool = False) -> None:
        banner_frame.config(bg=bg_color)
        lbl_result_banner.config(text=text, bg=bg_color, fg=fg_color)
        if show_discard:
            btn_quarantine_last.pack(side="right", padx=(8, 0))
        else:
            btn_quarantine_last.pack_forget()

    def discard_last_failed() -> None:
        folder = recording_active.get("last_failed_folder")
        if folder and pathlib.Path(folder).exists():
            dest = ctrl.quarantine_recording(folder)
            recording_active["last_failed_folder"] = None
            set_banner(
                f"🗑 Moved failed take to quarantine ({dest.name}). Your recordings/ folder is clean.",
                bg_color="#1e293b",
                fg_color="#cbd5e1",
                show_discard=False,
            )
            refresh_dataset_table()

    def start_recording() -> None:
        if recording_active["flag"]:
            return

        refresh_devices()
        selected_port = port_map.get(port_var.get())
        if not selected_port:
            messagebox.showwarning(
                "ESP32 USB Port Required",
                "No ESP32 USB Serial port (e.g. COM4) is currently plugged into this laptop.\n\n"
                "Note: Wi-Fi streams 5-second summaries to the Website Dashboard, while the Graphical Dataset Recorder uses the USB cable for 1000 Hz 3-axis vibration capture.\n\n"
                "1. Plug your ESP32 into your laptop's USB port.\n"
                "2. Keep Arduino Serial Monitor closed.\n"
                "3. Click '🔴 RECORD AUDIO + VIBRATION SAMPLE' again.",
            )
            return

        veh = vehicle_var.get().strip()
        sess = session_var.get().strip()
        if not veh or not sess:
            messagebox.showwarning("Missing Info", "Please enter a Vehicle Name and Session ID.")
            return

        try:
            rpm_int = int(float(rpm_var.get().strip()))
            dur_float = float(duration_var.get().strip())
            if dur_float < 2.0 or dur_float > 120.0:
                raise ValueError
        except ValueError:
            messagebox.showwarning("Invalid Number", "Target RPM must be a number and Duration must be 2–120 seconds.")
            return

        # Stop live preview cleanly before opening hardware for synchronized capture
        if monitor.is_running():
            stop_preview()
            time.sleep(0.15)

        selected_mic = mic_map.get(mic_var.get())
        cond = condition_var.get()

        ns = argparse.Namespace(
            port=selected_port,
            baud=921600,
            device=selected_mic,
            audio_rate=44100,
            sensor_range_g=0.0,
            output_root=str(ctrl.recordings_root),
            session_id=sess,
            vehicle_id=veh,
            condition=cond,
            rpm_target=rpm_int,
            duration=dur_float,
            operator=operator_var.get().strip() or "HAVEN Team",
            verified_by="",
            sensor_position=sensor_pos_var.get().strip() or "Valve Cover",
            mic_position=mic_pos_var.get().strip() or "25cm Front",
            load_state="Idle, Park/Neutral",
            fault_detail=notes_var.get().strip(),
            notes=notes_var.get().strip(),
        )

        stop_ev = threading.Event()
        recording_active["flag"] = True
        recording_active["stop_event"] = stop_ev
        recording_active["last_failed_folder"] = None

        btn_record.config(state="disabled", bg="#7f1d1d", text="⏳ RECORDING IN PROGRESS...")
        btn_cancel_rec.config(state="normal")
        btn_train.config(state="disabled")
        progress_bar["value"] = 0.0
        lbl_timer.config(text=f"0.0s / {dur_float:.1f}s", fg=ACCENT_AMBER)
        set_banner(
            "🎙️ RECORDING LIVE: Capturing 44.1 kHz Audio + ESP32 3-Axis Vibration... Lightly tap sensor mount in first 2s!",
            bg_color="#172554",
            fg_color="#bae6fd",
        )

        def on_progress(p: Dict[str, Any]) -> None:
            def _upd() -> None:
                el = float(p["elapsed_sec"])
                tot = max(float(p["duration_sec"]), 0.1)
                pct = min(100.0, (el / tot) * 100.0)
                progress_bar["value"] = pct
                lbl_timer.config(text=f"{el:4.1f}s / {tot:.1f}s ({int(p['vib_samples'])} vib)")
                ax, ay, az = p["latest_xyz"]
                mag = math.sqrt(ax * ax + ay * ay + az * az)
                lbl_xyz_detail.config(
                    text=(
                        f"Audio Peak: {p['audio_peak'] * 100:4.1f}%  |  "
                        f"ESP32 Accel: X={ax:+.2f}g  Y={ay:+.2f}g  Z={az:+.2f}g  "
                        f"(|a|={mag:.2f}g, ~{p['vib_rate_hz']:.0f} Hz)"
                    )
                )
                draw_scope(p.get("audio_tail", []), p.get("vib_tail", []))

            ui_queue.put(_upd)

        def worker() -> None:
            try:
                out_dir = record_synchronized_session(
                    ns,
                    progress_callback=on_progress,
                    external_stop_event=stop_ev,
                )
                vres = validate_recording_folder(out_dir)

                def _done() -> None:
                    progress_bar["value"] = 100.0
                    lbl_timer.config(text="Saved!", fg=ACCENT_GREEN if vres.valid else ACCENT_RED)
                    if vres.valid:
                        hz = vres.metrics.get("vibration_achieved_rate_hz", 0)
                        pk_g = vres.metrics.get("vibration_peak_g", 0)
                        set_banner(
                            f"✅ VALIDATION PASSED! Saved '{cond}' for '{veh}'  "
                            f"(Vib: {hz:.0f} Hz, Peak {pk_g:.2f}g | Audio: 44.1 kHz WAV) → {out_dir.name}",
                            bg_color="#064e3b",
                            fg_color="#ecfdf5",
                            show_discard=False,
                        )
                    else:
                        recording_active["last_failed_folder"] = out_dir
                        err_summary = "; ".join(vres.errors[:2])
                        set_banner(
                            f"⚠️ SAVED WITH SENSOR WARNING: {err_summary}",
                            bg_color="#450a0a",
                            fg_color="#fecaca",
                            show_discard=True,
                        )
                    refresh_dataset_table()

                ui_queue.put(_done)
            except Exception as exc:
                err_msg = str(exc)

                def _err() -> None:
                    lbl_timer.config(text="Error", fg=ACCENT_RED)
                    set_banner(
                        f"❌ Recording Error: {err_msg}",
                        bg_color="#450a0a",
                        fg_color="#fecaca",
                    )
                    messagebox.showerror("Recording Failed", err_msg)

                ui_queue.put(_err)
            finally:

                def _restore() -> None:
                    recording_active["flag"] = False
                    btn_record.config(
                        state="normal",
                        bg=ACCENT_RED,
                        text="🔴  RECORD AUDIO + VIBRATION SAMPLE",
                    )
                    btn_cancel_rec.config(state="disabled")
                    btn_train.config(state="normal")

                ui_queue.put(_restore)

        threading.Thread(target=worker, daemon=True).start()

    def cancel_recording() -> None:
        if recording_active["flag"]:
            recording_active["stop_event"].set()
            lbl_timer.config(text="Cancelling...", fg=ACCENT_AMBER)

    def open_selected_folder() -> None:
        sel = tree.selection()
        target = item_to_folder.get(sel[0], ctrl.recordings_root) if sel else ctrl.recordings_root
        target.mkdir(parents=True, exist_ok=True)
        try:
            os.startfile(str(target))  # type: ignore[attr-defined]
        except Exception:
            subprocess.Popen(["explorer", str(target)])

    def quarantine_selected() -> None:
        sel = tree.selection()
        if not sel:
            messagebox.showinfo("Select Recording", "Click a recording row in the table first.")
            return
        folders = [item_to_folder[i] for i in sel if i in item_to_folder]
        if not messagebox.askyesno(
            "Quarantine Recording(s)",
            f"Move {len(folders)} selected recording(s) out of 'recordings/' into 'recordings_quarantine/'?",
        ):
            return
        for f in folders:
            if f.exists():
                ctrl.quarantine_recording(f)
        refresh_dataset_table()

    def sync_selected_to_web() -> None:
        sel = tree.selection()
        if not sel:
            messagebox.showinfo(
                "Select Recording",
                "Select one or more ✅ PASS recordings in the table to import into the HAVEN Web Database.",
            )
            return
        folders = [item_to_folder[i] for i in sel if i in item_to_folder]
        vehicles = ctrl.list_laravel_vehicles()
        prompt_lines = ["Enter the HAVEN Web App Vehicle ID number to attach these sample(s) to:"]
        if vehicles:
            prompt_lines.append("\nAvailable Vehicles in Database:")
            for v in vehicles[:10]:
                prompt_lines.append(
                    f"  ID {v.get('id')}: {v.get('year', '')} {v.get('make', '')} {v.get('model', '')} ({v.get('plate_number', '')})"
                )
        default_vid = int(vehicles[0]["id"]) if vehicles else 1
        vid = simpledialog.askinteger(
            "Sync to HAVEN Web Database",
            "\n".join(prompt_lines),
            initialvalue=default_vid,
            minvalue=1,
            parent=root,
        )
        if not vid:
            return

        imported = 0
        errors: List[str] = []
        for f in folders:
            try:
                ctrl.import_recording_to_web(f, laravel_vehicle_id=vid, verified_by=operator_var.get())
                imported += 1
            except Exception as exc:
                errors.append(f"{f.name}: {exc}")

        msg = f"Successfully imported {imported} recording(s) into HAVEN Web App (Vehicle ID #{vid})."
        if errors:
            msg += "\n\nSkipped/Errors:\n" + "\n".join(errors[:3])
        messagebox.showinfo("HAVEN Web Sync Complete", msg)

    def run_training() -> None:
        btn_train.config(state="disabled", text="⏳ Training AI...")
        set_banner(
            "🧠 Extracting 14 Sound + Vibration DSP features across 1.5s windows and training Random Forest...",
            bg_color="#172554",
            fg_color="#bae6fd",
        )
        selected_mode = train_mode_var.get()

        def train_worker() -> None:
            try:
                res = ctrl.train_from_recordings(mode=selected_mode)

                def _ok() -> None:
                    refresh_model_badge()
                    mode_nice = (
                        "Full Thesis GroupKFold (Real Data Only)"
                        if res["mode_used"] == "grouped_real_only"
                        else "Hybrid (Real Windows + Calibrated Synthetic)"
                    )
                    summary_msg = (
                        f"✅ ML Model Retrained & Saved to ml_backend/model.pkl!\n\n"
                        f"• Mode: {mode_nice}\n"
                        f"• Test / CV Accuracy: {res['accuracy_pct']:.2f}%\n"
                        f"• Macro F1-Score: {res['f1_macro_pct']:.2f}%\n"
                        f"• Real Hardware Windows: {res['real_windows']}\n"
                        f"• Synthetic Calibration Windows: {res['synthetic_windows']}\n\n"
                        f"The live HAVEN ML server will automatically hot-reload this new model!"
                    )
                    set_banner(
                        f"✅ AI Trained ({mode_nice}) — Accuracy: {res['accuracy_pct']:.2f}% | "
                        f"F1: {res['f1_macro_pct']:.2f}% | Real Windows: {res['real_windows']}",
                        bg_color="#064e3b",
                        fg_color="#ecfdf5",
                    )
                    messagebox.showinfo("Machine Learning Training Complete", summary_msg)

                ui_queue.put(_ok)
            except Exception as exc:
                err_txt = str(exc)

                def _fail() -> None:
                    set_banner(f"❌ Training Error: {err_txt}", bg_color="#450a0a", fg_color="#fecaca")
                    messagebox.showerror("Training Error", err_txt)

                ui_queue.put(_fail)
            finally:
                ui_queue.put(lambda: btn_train.config(state="normal", text="🧠  TRAIN ML MODEL NOW"))

        threading.Thread(target=train_worker, daemon=True).start()

    # Bind buttons
    btn_refresh_hw.config(command=refresh_devices)
    btn_test_mic.config(command=lambda: start_sensor_test(enable_audio=True, enable_vib=False))
    btn_test_vib.config(command=lambda: start_sensor_test(enable_audio=False, enable_vib=True))
    btn_preview.config(command=lambda: start_sensor_test(enable_audio=True, enable_vib=True))
    btn_stop_preview.config(command=stop_preview)
    btn_record.config(command=start_recording)
    btn_cancel_rec.config(command=cancel_recording)
    btn_quarantine_last.config(command=discard_last_failed)
    btn_open_dir.config(command=open_selected_folder)
    btn_quarantine_sel.config(command=quarantine_selected)
    btn_sync_web.config(command=sync_selected_to_web)
    btn_train.config(command=run_training)

    # Periodic UI loop (drains worker queue + refreshes live preview scope)
    def ui_tick() -> None:
        while True:
            try:
                fn = ui_queue.get_nowait()
                fn()
            except queue.Empty:
                break

        if monitor.is_running() and not recording_active["flag"]:
            snap = monitor.snapshot()
            if snap["error"]:
                lbl_sensor_readout.config(text=snap["error"][:55], fg=ACCENT_RED)
            else:
                ax, ay, az = snap["vib_xyz"]
                mag = snap["vib_mag_g"]
                hz = snap["vib_rate_hz"]
                aud_pk = snap["audio_peak"] * 100.0
                m_mode = snap.get("mode", "both")
                if m_mode == "mic":
                    status_str = f"MIC LIVE · Peak {aud_pk:4.1f}%"
                elif m_mode == "vib":
                    status_str = f"VIB LIVE · {hz:4.0f} Hz (|a|={mag:.2f}g)"
                else:
                    status_str = f"BOTH LIVE · Mic {aud_pk:4.1f}% · {hz:4.0f} Hz"
                lbl_sensor_readout.config(text=status_str, fg=ACCENT_GREEN)
                lbl_xyz_detail.config(
                    text=(
                        f"Audio Peak: {aud_pk:4.1f}%  |  "
                        f"ESP32 Accel: X={ax:+.2f}g  Y={ay:+.2f}g  Z={az:+.2f}g  "
                        f"(|a|={mag:.2f}g, ~{hz:.0f} Hz)"
                    )
                )
                draw_scope(snap["audio_wave"], snap["vib_wave"])

        root.after(90, ui_tick)

    def on_close() -> None:
        if recording_active["flag"]:
            recording_active["stop_event"].set()
        monitor.stop()
        root.destroy()

    root.protocol("WM_DELETE_WINDOW", on_close)

    # Initial load
    refresh_devices()
    refresh_model_badge()
    refresh_dataset_table()
    root.after(120, lambda: draw_scope([0.0] * 40, [0.0] * 40))
    root.after(90, ui_tick)
    root.mainloop()


if __name__ == "__main__":
    launch_gui()

