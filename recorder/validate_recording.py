#!/usr/bin/env python3
"""
HAVEN AI — Recording Folder Validator & Laravel Importer (Phase 2)
==================================================================
Validates a recorded engine capture folder produced by `record_session.py`:
  1. Required files present (`metadata.json`, `vibration.csv`, `audio.wav`)
  2. Ground-truth `condition` is one of the 4 canonical thesis classes
  3. Vibration sample rate is stable and has no timestamp gaps or non-monotonic jumps
  4. Audio is uncompressed mono WAV and is neither clipped nor silent
  5. Audio duration, vibration duration, and target metadata duration match

Optionally imports a validated recording directly into the Laravel database
via `php artisan haven:import-recording` with `true_condition` and
`is_training_sample = true`.
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import pathlib
import subprocess
import sys
from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional

import numpy as np
import soundfile as sf

ALLOWED_CONDITIONS = ("normal", "misfire", "loose_component", "valve_clearance")
UNCOMPRESSED_SUBTYPES = {"PCM_16", "PCM_24", "PCM_32", "FLOAT", "DOUBLE"}


@dataclass
class ValidationResult:
    folder: pathlib.Path
    valid: bool
    errors: List[str] = field(default_factory=list)
    warnings: List[str] = field(default_factory=list)
    metrics: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "folder": str(self.folder),
            "valid": self.valid,
            "errors": self.errors,
            "warnings": self.warnings,
            "metrics": self.metrics,
        }


def validate_recording_folder(
    folder: pathlib.Path | str,
    *,
    min_vib_rate_hz: float = 200.0,
    max_jitter_ratio: float = 0.25,
    max_gap_factor: float = 5.0,
    max_gap_us: float = 75_000.0,
    max_audio_clip_ratio: float = 0.001,
    max_vib_clip_ratio: float = 0.005,
    max_av_duration_diff_sec: float = 0.5,
    duration_tolerance_ratio: float = 0.15,
) -> ValidationResult:
    """
    Validate a recording folder and return a structured ValidationResult.
    """
    folder_path = pathlib.Path(folder).resolve()
    errors: List[str] = []
    warnings: List[str] = []
    metrics: Dict[str, Any] = {}

    if not folder_path.exists() or not folder_path.is_dir():
        return ValidationResult(
            folder=folder_path,
            valid=False,
            errors=[f"Recording folder does not exist: {folder_path}"],
        )

    meta_path = folder_path / "metadata.json"
    vib_path = folder_path / "vibration.csv"
    audio_path = folder_path / "audio.wav"

    for req_file in (meta_path, vib_path, audio_path):
        if not req_file.exists():
            errors.append(f"Missing required file: {req_file.name}")

    if errors:
        return ValidationResult(folder=folder_path, valid=False, errors=errors)

    # ── 1. Validate metadata.json ──
    try:
        with meta_path.open("r", encoding="utf-8") as f:
            meta = json.load(f)
    except Exception as exc:
        return ValidationResult(
            folder=folder_path,
            valid=False,
            errors=[f"Invalid metadata.json: {exc}"],
        )

    required_meta_keys = [
        "session_id",
        "vehicle_id",
        "condition",
        "rpm_target",
        "duration_target_sec",
        "operator",
        "sensor_position",
        "mic_position",
    ]
    for key in required_meta_keys:
        if key not in meta or meta[key] in (None, ""):
            errors.append(f"metadata.json missing required field: '{key}'")

    condition = str(meta.get("condition", "")).strip()
    if condition not in ALLOWED_CONDITIONS:
        errors.append(
            f"Invalid condition '{condition}' in metadata.json; must be one of {ALLOWED_CONDITIONS}"
        )

    target_duration = float(meta.get("duration_target_sec") or 0.0)
    sensor_range_g = float(meta.get("vibration_sensor_range_g") or 16.0)
    fw_headers = meta.get("firmware_headers")
    if isinstance(fw_headers, list) and fw_headers:
        from record_session import parse_firmware_range_g

        fw_rg = parse_firmware_range_g([str(h) for h in fw_headers])
        if fw_rg is not None:
            sensor_range_g = fw_rg
    expected_audio_sr = int(meta.get("audio_sample_rate_hz") or 44100)

    # ── 2. Validate audio.wav ──
    audio_duration_s = 0.0
    try:
        info = sf.info(str(audio_path))
        metrics["audio_sample_rate_hz"] = info.samplerate
        metrics["audio_channels"] = info.channels
        metrics["audio_subtype"] = info.subtype
        metrics["audio_frames"] = info.frames
        audio_duration_s = float(info.frames) / float(info.samplerate) if info.samplerate > 0 else 0.0
        metrics["audio_duration_sec"] = round(audio_duration_s, 4)

        if info.channels != 1:
            errors.append(f"Audio must be mono (1 channel), got {info.channels} channels")
        if info.subtype not in UNCOMPRESSED_SUBTYPES:
            errors.append(f"Audio WAV must be uncompressed PCM/FLOAT, got subtype '{info.subtype}'")
        if expected_audio_sr > 0 and info.samplerate != expected_audio_sr:
            errors.append(
                f"Audio sample rate ({info.samplerate} Hz) does not match metadata ({expected_audio_sr} Hz)"
            )

        audio_data, _ = sf.read(str(audio_path), dtype="float64", always_2d=False)
        audio_flat = np.asarray(audio_data, dtype=np.float64).reshape(-1)
        if audio_flat.size == 0:
            errors.append("Audio WAV contains 0 samples")
        else:
            abs_audio = np.abs(audio_flat)
            audio_peak = float(np.max(abs_audio))
            audio_rms = float(np.sqrt(np.mean((audio_flat - np.mean(audio_flat)) ** 2)))
            clipped_samples = int(np.count_nonzero(abs_audio >= 0.995))
            clip_ratio = float(clipped_samples) / float(audio_flat.size)

            metrics["audio_peak"] = round(audio_peak, 5)
            metrics["audio_rms"] = round(audio_rms, 5)
            metrics["audio_clipped_samples"] = clipped_samples
            metrics["audio_clip_ratio"] = round(clip_ratio, 6)

            if clip_ratio > max_audio_clip_ratio:
                errors.append(
                    f"Audio is clipped: {clipped_samples}/{audio_flat.size} samples "
                    f"({clip_ratio * 100:.3f}%) exceed 99.5% full scale"
                )
            if audio_rms < 1e-5:
                errors.append("Audio signal is silent or flatlined (RMS < 1e-5)")
    except Exception as exc:
        errors.append(f"Failed to read audio.wav: {exc}")

    # ── 3. Validate vibration.csv ──
    vib_duration_s = 0.0
    try:
        rows: List[List[float]] = []
        with vib_path.open("r", encoding="utf-8") as f_csv:
            reader = csv.reader(f_csv)
            header: Optional[List[str]] = None
            for line_num, row in enumerate(reader, start=1):
                if not row or row[0].strip().startswith("#"):
                    continue
                if header is None:
                    header = [col.strip() for col in row]
                    if header != ["t_us", "ax", "ay", "az"]:
                        errors.append(
                            f"Invalid vibration.csv header {header}; expected ['t_us', 'ax', 'ay', 'az']"
                        )
                    continue
                if len(row) != 4:
                    errors.append(f"vibration.csv line {line_num} has {len(row)} columns; expected 4")
                    break
                vals = [float(v) for v in row]
                if not all(math.isfinite(v) for v in vals):
                    errors.append(f"vibration.csv line {line_num} contains NaN or Inf")
                    break
                rows.append(vals)

        if len(rows) < 50:
            errors.append(f"vibration.csv has too few samples ({len(rows)}; minimum 50 required)")
        else:
            arr = np.asarray(rows, dtype=np.float64)
            t_us = arr[:, 0]
            xyz = arr[:, 1:4]

            dt_us = np.diff(t_us)
            non_positive = int(np.count_nonzero(dt_us <= 0))
            if non_positive > 0:
                errors.append(
                    f"vibration.csv timestamps are not strictly increasing ({non_positive} non-positive steps)"
                )

            pos_dt = dt_us[dt_us > 0]
            if pos_dt.size > 0:
                median_dt_us = float(np.median(pos_dt))
                mean_dt_us = float(np.mean(pos_dt))
                raw_std_dt_us = float(np.std(pos_dt))
                # Compute robust jitter across 99.5% of samples so an isolated single USB scheduler
                # buffer stall does not falsely fail an otherwise stable 10,000-sample take.
                dt_p99_5 = np.percentile(pos_dt, 99.5) if pos_dt.size >= 50 else float(np.max(pos_dt))
                trimmed_dt = pos_dt[pos_dt <= dt_p99_5]
                std_dt_us = float(np.std(trimmed_dt if trimmed_dt.size > 0 else pos_dt))
                max_dt_us = float(np.max(pos_dt))
                achieved_hz = 1e6 / median_dt_us if median_dt_us > 0 else 0.0
                jitter_ratio = std_dt_us / median_dt_us if median_dt_us > 0 else float("inf")
                raw_jitter_ratio = raw_std_dt_us / median_dt_us if median_dt_us > 0 else float("inf")
                vib_duration_s = float(t_us[-1] - t_us[0]) * 1e-6

                metrics["vibration_samples"] = len(rows)
                metrics["vibration_achieved_rate_hz"] = round(achieved_hz, 2)
                metrics["vibration_median_dt_us"] = round(median_dt_us, 2)
                metrics["vibration_mean_dt_us"] = round(mean_dt_us, 2)
                metrics["vibration_max_dt_us"] = round(max_dt_us, 2)
                metrics["vibration_jitter_ratio"] = round(jitter_ratio, 4)
                metrics["vibration_raw_jitter_ratio"] = round(raw_jitter_ratio, 4)
                metrics["vibration_duration_sec"] = round(vib_duration_s, 4)

                if achieved_hz < min_vib_rate_hz:
                    errors.append(
                        f"Vibration sample rate ({achieved_hz:.1f} Hz) is below minimum ({min_vib_rate_hz:.1f} Hz)"
                    )

                if jitter_ratio > max_jitter_ratio:
                    errors.append(
                        f"Vibration sample rate is unstable: jitter ratio std/median = {jitter_ratio:.3f} "
                        f"(max allowed {max_jitter_ratio:.3f})"
                    )

                gap_limit_us = max(max_gap_factor * median_dt_us, max_gap_us)
                if max_dt_us > gap_limit_us:
                    errors.append(
                        f"Timestamp gap detected in vibration.csv: max dt = {max_dt_us:.1f} us "
                        f"(median dt = {median_dt_us:.1f} us, limit = {gap_limit_us:.1f} us)"
                    )
                elif max_dt_us > max_gap_factor * median_dt_us:
                    warnings.append(
                        f"Minor USB packet buffer lag detected in vibration.csv: max dt = {max_dt_us:.1f} us "
                        f"(median dt = {median_dt_us:.1f} us, limit = {gap_limit_us:.1f} us)"
                    )

            # Check vibration clipping & flatline
            vib_clipped = int(np.count_nonzero(np.abs(xyz) >= 0.98 * sensor_range_g))
            vib_clip_ratio = float(vib_clipped) / float(xyz.size)
            centered_xyz = xyz - np.mean(xyz, axis=0, keepdims=True)
            dyn_mag = np.linalg.norm(centered_xyz, axis=1)
            vib_rms_g = float(np.sqrt(np.mean(dyn_mag ** 2)))
            vib_peak_g = float(np.max(dyn_mag))

            metrics["vibration_rms_g"] = round(vib_rms_g, 5)
            metrics["vibration_peak_g"] = round(vib_peak_g, 5)
            metrics["vibration_clipped_values"] = vib_clipped
            metrics["vibration_clip_ratio"] = round(vib_clip_ratio, 6)

            if vib_clip_ratio > max_vib_clip_ratio:
                errors.append(
                    f"Vibration accelerometer railed/clipped: {vib_clipped}/{xyz.size} values "
                    f"({vib_clip_ratio * 100:.2f}%) exceed 98% of ±{sensor_range_g}g range"
                )
            if vib_rms_g < 1e-5:
                errors.append("Vibration signal is flatlined (dynamic RMS < 1e-5 g)")
    except Exception as exc:
        errors.append(f"Failed to parse vibration.csv: {exc}")

    # ── 4. Cross-check Audio, Vibration, and Target Durations ──
    if audio_duration_s > 0 and vib_duration_s > 0:
        av_diff = abs(audio_duration_s - vib_duration_s)
        metrics["av_duration_diff_sec"] = round(av_diff, 4)
        allowed_av_diff = max(max_av_duration_diff_sec, 0.10 * max(audio_duration_s, vib_duration_s))
        if av_diff > allowed_av_diff:
            errors.append(
                f"Audio duration ({audio_duration_s:.2f}s) and vibration duration ({vib_duration_s:.2f}s) "
                f"mismatch by {av_diff:.2f}s (max allowed {allowed_av_diff:.2f}s)"
            )

    if target_duration > 0:
        tol_s = max(0.5, target_duration * duration_tolerance_ratio)
        if audio_duration_s > 0 and abs(audio_duration_s - target_duration) > tol_s:
            errors.append(
                f"Audio duration ({audio_duration_s:.2f}s) deviates from target duration "
                f"({target_duration:.2f}s) by more than ±{tol_s:.2f}s"
            )
        if vib_duration_s > 0 and abs(vib_duration_s - target_duration) > tol_s:
            errors.append(
                f"Vibration duration ({vib_duration_s:.2f}s) deviates from target duration "
                f"({target_duration:.2f}s) by more than ±{tol_s:.2f}s"
            )

    return ValidationResult(
        folder=folder_path,
        valid=(len(errors) == 0),
        errors=errors,
        warnings=warnings,
        metrics=metrics,
    )


def import_validated_recording_to_laravel(
    folder: pathlib.Path,
    haven_dir: Optional[pathlib.Path] = None,
    user_id: Optional[int] = None,
    vehicle_id: Optional[int] = None,
    verified_by: Optional[str] = None,
    analyze: bool = False,
) -> subprocess.CompletedProcess:
    """
    Validate a recording folder and invoke `php artisan haven:import-recording`
    in the Laravel `/haven` application directory.
    """
    validation = validate_recording_folder(folder)
    if not validation.valid:
        raise ValueError(
            f"Cannot import invalid recording folder {folder}: " + "; ".join(validation.errors)
        )

    if haven_dir is None:
        haven_dir = pathlib.Path(__file__).resolve().parent.parent / "haven"

    cmd = ["php", "artisan", "haven:import-recording", str(folder.resolve())]
    if user_id is not None:
        cmd.append(f"--user-id={user_id}")
    if vehicle_id is not None:
        cmd.append(f"--vehicle-id={vehicle_id}")
    if verified_by:
        cmd.append(f"--verified-by={verified_by}")
    if analyze:
        cmd.append("--analyze")

    return subprocess.run(cmd, cwd=str(haven_dir), check=True, text=True, capture_output=True)


def build_arg_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Validate a HAVEN AI recording folder and optionally import it into Laravel."
    )
    parser.add_argument("folder", type=str, help="Path to recording folder")
    parser.add_argument("--min-vib-rate", type=float, default=200.0, help="Minimum vibration rate in Hz")
    parser.add_argument("--json", action="store_true", help="Print validation result as JSON")
    parser.add_argument(
        "--import-laravel",
        action="store_true",
        help="Import into Laravel after passing validation via `php artisan haven:import-recording`",
    )
    parser.add_argument("--user-id", type=int, default=None, help="Laravel user ID for import")
    parser.add_argument("--vehicle-id", type=int, default=None, help="Override Laravel vehicle ID for import")
    parser.add_argument("--verified-by", type=str, default=None, help="Verifier name for import")
    parser.add_argument("--analyze", action="store_true", help="Run ML analysis job upon Laravel import")
    return parser


def main(argv: Optional[List[str]] = None) -> int:
    parser = build_arg_parser()
    args = parser.parse_args(argv)

    res = validate_recording_folder(args.folder, min_vib_rate_hz=args.min_vib_rate)

    if args.json:
        print(json.dumps(res.to_dict(), indent=2))
    else:
        status_str = "PASS" if res.valid else "FAIL"
        print(f"[HAVEN VALIDATOR] {status_str} — {res.folder}")
        for k, v in res.metrics.items():
            print(f"  {k:28s}: {v}")
        if res.errors:
            print("\nErrors:")
            for err in res.errors:
                print(f"  - {err}")

    if not res.valid:
        return 1

    if args.import_laravel:
        proc = import_validated_recording_to_laravel(
            res.folder,
            user_id=args.user_id,
            vehicle_id=args.vehicle_id,
            verified_by=args.verified_by,
            analyze=args.analyze,
        )
        print("\n[LARAVEL IMPORT]")
        print(proc.stdout.strip())

    return 0


if __name__ == "__main__":
    sys.exit(main())

