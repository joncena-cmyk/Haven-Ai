"""
Feature extraction module for HAVEN ML backend (Phase 4).

Key signal processing guarantees:
  1. Gravity / DC offset removal: subtracts per-axis mean (and applies a 2 Hz
     Butterworth high-pass / band-pass filter) BEFORE computing peak_g, rms_g,
     and crest_factor.
  2. Real sample rate inference: computes actual sample rate from the file's
     timestamps (`t_us`, `t_ms`, or `t_s`) rather than hardcoding 100 Hz.
  3. Window segmentation: segments recordings into overlapping 1-2 s windows
     (default 1.5 s, 50% overlap) and computes features per window.
  4. Band-pass / noise filtering: uses SciPy Butterworth Zero-Phase SOS filtering
     (`scipy.signal.butter` + `sosfiltfilt`) on both vibration and audio signals.
"""

from __future__ import annotations

import csv
import json
import math
import os
from typing import Any, Dict, List, Optional, Tuple

import numpy as np
from scipy import signal as sp_signal
import soundfile as sf


# ─────────────────────────────────────────────────────────────────────────────
# Windowing & Filtering Helpers
# ─────────────────────────────────────────────────────────────────────────────

def segment_windows(
    data: np.ndarray,
    sample_rate_hz: float,
    window_sec: float = 1.5,
    overlap: float = 0.5,
) -> List[np.ndarray]:
    """
    Slice a 1D or 2D array along axis 0 into overlapping windows of `window_sec`
    seconds with `overlap` fraction (default 50%).
    If the signal is shorter than one full window, returns `[data]` if non-empty.
    """
    if data.shape[0] == 0 or sample_rate_hz <= 0:
        return []

    win_len = max(8, int(round(window_sec * sample_rate_hz)))
    if data.shape[0] <= win_len:
        return [data]

    step = max(1, int(round(win_len * (1.0 - overlap))))
    windows: List[np.ndarray] = []
    start = 0
    while start + win_len <= data.shape[0]:
        windows.append(data[start : start + win_len])
        start += step

    if not windows:
        windows.append(data)
    return windows


def bandpass_filter_signal(
    x: np.ndarray,
    sample_rate_hz: float,
    lowcut_hz: float,
    highcut_hz: Optional[float] = None,
    order: int = 4,
) -> np.ndarray:
    """
    Remove DC offset per channel and apply a zero-phase Butterworth band-pass
    filter along axis 0 (`[lowcut_hz, highcut_hz]`).
    Uses a zero-phase Butterworth frequency-response transfer function for the
    high-pass edge (eliminating IIR boundary step-response overshoot on `peak_g`)
    and `scipy.signal.butter` + `sosfiltfilt` for high-frequency noise suppression.
    """
    centered = x - np.mean(x, axis=0, keepdims=True)
    nyq = 0.5 * float(sample_rate_hz)
    n_samples = centered.shape[0]
    if nyq <= lowcut_hz * 1.1 or n_samples < 32:
        return centered

    filtered = centered
    duration_sec = float(n_samples) / float(sample_rate_hz)

    # 1. Complementary zero-phase Butterworth high-pass filter:
    #    Extract and subtract the sub-lowcut_hz baseline via a zero-phase
    #    Butterworth LPF with padtype=None so endpoint phase does not bias
    #    boundary reflection padding.
    if lowcut_hz > 0.0 and (lowcut_hz / nyq) < 0.90 and duration_sec >= (1.0 / lowcut_hz):
        low = lowcut_hz / nyq
        hp_order = min(2, order)
        sos_drift = sp_signal.butter(hp_order, low, btype="lowpass", output="sos")
        baseline_drift = sp_signal.sosfiltfilt(sos_drift, filtered, axis=0, padtype=None)
        filtered = filtered - baseline_drift

    # 2. Zero-phase Butterworth SOS low-pass filter (rejects > highcut_hz noise)
    if highcut_hz is not None and highcut_hz < nyq * 0.95 and highcut_hz > lowcut_hz * 1.2:
        high = highcut_hz / nyq
        sos = sp_signal.butter(order, high, btype="lowpass", output="sos")
        padlen = min(3 * (2 * order + 1), n_samples - 1)
        filtered = sp_signal.sosfiltfilt(sos, filtered, axis=0, padlen=padlen)

    return filtered - np.mean(filtered, axis=0, keepdims=True)


def infer_sample_rate_from_timestamps(
    timestamps: np.ndarray,
    header_col: str = "",
    fallback_sr: Optional[float] = None,
) -> float:
    """
    Infer the real sample rate in Hz from a 1D array of timestamps.
    Supports microsecond (`t_us`), millisecond (`t_ms`), or second (`t_s`) units.
    """
    if timestamps.size >= 2:
        dt = np.diff(timestamps.astype(np.float64))
        pos_dt = dt[dt > 0]
        if pos_dt.size > 0:
            median_dt = float(np.median(pos_dt))
            col_lower = header_col.strip().lower()
            if "us" in col_lower or median_dt >= 50.0:
                return float(1_000_000.0 / median_dt)
            if "ms" in col_lower or (1.0 <= median_dt < 50.0):
                return float(1_000.0 / median_dt)
            if median_dt > 0:
                return float(1.0 / median_dt)

    if fallback_sr is not None and fallback_sr > 0:
        return float(fallback_sr)
    raise ValueError("Cannot determine vibration sample rate: no valid timestamps or sample_rate_hz provided.")


# ─────────────────────────────────────────────────────────────────────────────
# Vibration Feature Extraction (Per-Window & Aggregated)
# ─────────────────────────────────────────────────────────────────────────────

def compute_vibration_window_metrics(
    window_xyz: np.ndarray,
    sample_rate_hz: float,
    rpm: Optional[float] = None,
) -> Dict[str, float]:
    """
    Compute time-domain and FFT frequency-domain metrics on a single vibration window.
    Removes per-axis DC/gravity mean inside the window before computing
    `peak_g`, `rms_g`, and `crest_factor`.
    """
    arr = np.asarray(window_xyz, dtype=np.float64)
    if arr.ndim == 1:
        arr = arr[:, np.newaxis]

    # Subtract per-axis DC offset / static gravity vector
    centered_axes = arr - np.mean(arr, axis=0, keepdims=True)
    # Dynamic vector magnitude per sample
    dyn_mag = np.linalg.norm(centered_axes, axis=1)

    peak_g = float(np.max(dyn_mag)) if dyn_mag.size > 0 else 0.0
    rms_g = float(np.sqrt(np.mean(dyn_mag ** 2))) if dyn_mag.size > 0 else 0.0
    mean_g = float(np.mean(dyn_mag)) if dyn_mag.size > 0 else 0.0
    std_g = float(np.std(dyn_mag)) if dyn_mag.size > 0 else 0.0
    crest_factor = float(peak_g / rms_g) if rms_g > 1e-12 else 0.0

    # For FFT spectral analysis, use the axis with the highest dynamic energy
    # so signed oscillation frequencies f0 are preserved (rectifying a sine wave
    # via abs/norm doubles f0 to 2*f0).
    axis_energies = np.sum(centered_axes ** 2, axis=0)
    dom_axis_sig = centered_axes[:, int(np.argmax(axis_energies))]
    n_samples = dom_axis_sig.size

    # Standardized 4th moment (kurtosis) of dominant dynamic axis for impulse sensitivity
    dom_std = float(np.std(dom_axis_sig)) if n_samples > 0 else 0.0
    if dom_std > 1e-12 and n_samples >= 4:
        kurtosis = float(np.mean(((dom_axis_sig - float(np.mean(dom_axis_sig))) / dom_std) ** 4))
    else:
        kurtosis = 0.0

    dominant_freq_hz = 0.0
    spectral_entropy = 0.0
    harmonic_1x_ratio = 0.0
    harmonic_0_5x_ratio = 0.0

    if n_samples >= 8 and sample_rate_hz > 0:
        win = np.hanning(n_samples)
        fft_mag = np.abs(np.fft.rfft(dom_axis_sig * win))
        freq_bins = np.fft.rfftfreq(n_samples, d=1.0 / float(sample_rate_hz))

        if fft_mag.size > 1:
            dom_idx = int(np.argmax(fft_mag[1:]) + 1)
            dominant_freq_hz = float(freq_bins[dom_idx])

            power = fft_mag[1:] ** 2
            total_power = float(np.sum(power))
            if total_power > 1e-12:
                psd = power / total_power
                entropy = -float(np.sum(psd * np.log2(psd + 1e-12)))
                max_entropy = math.log2(psd.size) if psd.size > 1 else 1.0
                spectral_entropy = float(entropy / max_entropy) if max_entropy > 0 else 0.0

                if rpm is not None and float(rpm) > 300.0:
                    f1x = float(rpm) / 60.0
                    f05x = f1x / 2.0

                    def _band_power(target_f: float, bw: float = 2.5) -> float:
                        mask = (freq_bins[1:] >= max(0.0, target_f - bw)) & (
                            freq_bins[1:] <= (target_f + bw)
                        )
                        return float(np.sum(power[mask]))

                    harmonic_1x_ratio = _band_power(f1x) / total_power
                    harmonic_0_5x_ratio = _band_power(f05x) / total_power

    return {
        "peak_g": peak_g,
        "rms_g": rms_g,
        "mean_g": mean_g,
        "std_g": std_g,
        "crest_factor": crest_factor,
        "kurtosis": kurtosis,
        "dominant_freq_hz": dominant_freq_hz,
        "spectral_entropy": spectral_entropy,
        "harmonic_1x_ratio": harmonic_1x_ratio,
        "harmonic_0_5x_ratio": harmonic_0_5x_ratio,
    }


def _load_raw_vibration_with_timestamps(
    file_path: str,
    ext: str = "csv",
    sample_rate_hz: Optional[float] = None,
) -> Tuple[np.ndarray, float, np.ndarray]:
    """
    Parse a CSV or JSON vibration file into `(raw_axes, real_sample_rate_hz, timestamps)`.
    """
    timestamps: List[float] = []
    rows_xyz: List[List[float]] = []
    first_col_name = ""

    if ext.lower() == "json":
        with open(file_path, "r", encoding="utf-8") as f:
            data = json.load(f)
        for row in data:
            if isinstance(row, (int, float)):
                rows_xyz.append([float(row)])
            elif isinstance(row, dict):
                for tk in ("t_us", "t_ms", "timestamp", "t"):
                    if tk in row and row[tk] is not None:
                        timestamps.append(float(row[tk]))
                        if not first_col_name:
                            first_col_name = tk
                        break
                if all(k in row for k in ("ax", "ay", "az")):
                    rows_xyz.append([float(row["ax"]), float(row["ay"]), float(row["az"])])
                elif all(k in row for k in ("x", "y", "z")):
                    rows_xyz.append([float(row["x"]), float(row["y"]), float(row["z"])])
                elif "magnitude" in row:
                    rows_xyz.append([float(row["magnitude"])])
    else:
        with open(file_path, "r", newline="", encoding="utf-8", errors="replace") as f:
            reader = csv.reader(f)
            header_seen = False
            axis_indices: Tuple[int, int, int] = (1, 2, 3)
            for row in reader:
                if not row:
                    continue
                first_cell = row[0].strip()
                if not first_cell or first_cell.startswith("#"):
                    continue
                if not header_seen:
                    header_seen = True
                    try:
                        float(first_cell)
                    except ValueError:
                        first_col_name = first_cell
                        lower_cols = [c.strip().lower() for c in row]
                        for candidate in (("ax", "ay", "az"), ("x", "y", "z"), ("channel 1", "channel 2", "channel 3")):
                            if all(k in lower_cols for k in candidate):
                                axis_indices = (
                                    lower_cols.index(candidate[0]),
                                    lower_cols.index(candidate[1]),
                                    lower_cols.index(candidate[2]),
                                )
                                break
                        continue
                try:
                    max_idx = max(axis_indices)
                    if len(row) > max_idx:
                        t_val = float(row[0])
                        vals = [float(row[axis_indices[0]]), float(row[axis_indices[1]]), float(row[axis_indices[2]])]
                        if all(math.isfinite(v) for v in vals) and math.isfinite(t_val):
                            timestamps.append(t_val)
                            rows_xyz.append(vals)
                    elif len(row) >= 2:
                        t_val = float(row[0])
                        val = float(row[1])
                        if math.isfinite(val) and math.isfinite(t_val):
                            timestamps.append(t_val)
                            rows_xyz.append([val])
                except (ValueError, IndexError):
                    continue

    if not rows_xyz:
        return np.zeros((0, 3), dtype=np.float64), float(sample_rate_hz or 0.0), np.zeros(0, dtype=np.float64)

    arr = np.asarray(rows_xyz, dtype=np.float64)
    ts_arr = np.asarray(timestamps, dtype=np.float64)
    effective_fallback_sr = sample_rate_hz
    if effective_fallback_sr is None and ext.lower() == "json" and ts_arr.size == 0:
        effective_fallback_sr = 100.0
    inferred_sr = infer_sample_rate_from_timestamps(
        ts_arr,
        header_col=first_col_name,
        fallback_sr=effective_fallback_sr,
    )
    return arr, inferred_sr, ts_arr


def _load_raw_vibration_file(
    file_path: str,
    ext: str = "csv",
    sample_rate_hz: Optional[float] = None,
) -> Tuple[np.ndarray, float]:
    """
    Parse a CSV or JSON vibration file into a `(N, C)` float64 array and return
    `(raw_axes, real_sample_rate_hz)`.
    """
    arr, inferred_sr, _ = _load_raw_vibration_with_timestamps(
        file_path, ext=ext, sample_rate_hz=sample_rate_hz
    )
    return arr, inferred_sr


def _split_contiguous_bursts(arr: np.ndarray, ts_arr: np.ndarray) -> List[np.ndarray]:
    """
    Split a vibration array at timestamp gaps (`dt > 3.5 * median_dt`) so that
    periodic Wi-Fi telemetry bursts (e.g. 64 samples @ 100 Hz every 5 s) are
    filtered and FFT-analyzed as contiguous phase-coherent segments rather than
    stitching across multi-second gaps.
    """
    if ts_arr.size != arr.shape[0] or arr.shape[0] < 16:
        return [arr]

    dt = np.diff(ts_arr)
    pos_dt = dt[dt > 0]
    if pos_dt.size == 0:
        return [arr]

    median_dt = float(np.median(pos_dt))
    if median_dt <= 0:
        return [arr]

    gap_indices = np.where(dt > (3.5 * median_dt))[0] + 1
    if gap_indices.size == 0:
        return [arr]

    segments = [seg for seg in np.split(arr, gap_indices) if seg.shape[0] >= 8]
    return segments if segments else [arr]


def extract_vibration_windows(
    file_path: str,
    ext: str = "csv",
    sample_rate_hz: Optional[float] = None,
    rpm: Optional[float] = None,
    window_sec: float = 1.5,
    overlap: float = 0.5,
    apply_filter: bool = True,
) -> Tuple[List[Dict[str, float]], float, int]:
    """
    Load a vibration file, infer its real sample rate from timestamps, split at
    inter-burst timestamp discontinuities if present, remove DC gravity offset,
    optionally apply a SciPy Butterworth band-pass filter, segment into windows,
    and return `(window_feature_dicts, sr, total_samples)`.
    """
    arr, real_sr, ts_arr = _load_raw_vibration_with_timestamps(
        file_path, ext=ext, sample_rate_hz=sample_rate_hz
    )
    if arr.shape[0] == 0:
        return [], real_sr, 0

    contiguous_bursts = _split_contiguous_bursts(arr, ts_arr)
    windows: List[np.ndarray] = []

    for burst in contiguous_bursts:
        centered = burst - np.mean(burst, axis=0, keepdims=True)
        if apply_filter and real_sr >= 20.0 and centered.shape[0] >= 32:
            centered = bandpass_filter_signal(
                centered,
                sample_rate_hz=real_sr,
                lowcut_hz=2.0,
                highcut_hz=min(0.45 * real_sr, 450.0),
            )
        windows.extend(segment_windows(centered, real_sr, window_sec=window_sec, overlap=overlap))

    win_features = [
        compute_vibration_window_metrics(w, sample_rate_hz=real_sr, rpm=rpm)
        for w in windows
    ]
    return win_features, real_sr, int(arr.shape[0])


def extract_vibration_features(
    file_path: str,
    ext: str = "csv",
    sample_rate_hz: Optional[float] = None,
    rpm: Optional[float] = None,
    window_sec: float = 1.5,
    overlap: float = 0.5,
    apply_filter: bool = True,
    include_windows: bool = False,
) -> Dict[str, Any]:
    """
    Extract aggregated per-window vibration features from an ESP32 CSV or JSON file.
    """
    try:
        win_feats, real_sr, total_samples = extract_vibration_windows(
            file_path,
            ext=ext,
            sample_rate_hz=sample_rate_hz,
            rpm=rpm,
            window_sec=window_sec,
            overlap=overlap,
            apply_filter=apply_filter,
        )
    except Exception as exc:
        raise RuntimeError(f"Vibration feature extraction failed: {exc}") from exc

    if not win_feats or total_samples == 0:
        return {
            "sample_count": 0,
            "window_count": 0,
            "sample_rate_hz": round(float(real_sr or 0.0), 2),
            "extraction_mode": "vibration_empty",
            "peak_g": np.nan,
            "rms_g": np.nan,
            "crest_factor": np.nan,
            "dominant_freq_hz": np.nan,
            "spectral_entropy": np.nan,
        }

    def _agg(key: str) -> float:
        vals = [w[key] for w in win_feats if key in w and math.isfinite(w[key])]
        return float(np.mean(vals)) if vals else 0.0

    peak_vals = [w["peak_g"] for w in win_feats if math.isfinite(w["peak_g"])]
    peak_g = float(np.mean(peak_vals)) if peak_vals else 0.0
    peak_g_max = float(np.max(peak_vals)) if peak_vals else 0.0
    rms_g = _agg("rms_g")
    crest_factor = _agg("crest_factor")

    result: Dict[str, Any] = {
        "sample_count": int(total_samples),
        "window_count": len(win_feats),
        "sample_rate_hz": round(float(real_sr), 2),
        "peak_g": round(peak_g, 4),
        "peak_g_max": round(peak_g_max, 4),
        "rms_g": round(rms_g, 4),
        "mean_g": round(_agg("mean_g"), 4),
        "std_g": round(_agg("std_g"), 4),
        "crest_factor": round(crest_factor, 4),
        "kurtosis": round(_agg("kurtosis"), 4),
        "dominant_freq_hz": round(_agg("dominant_freq_hz"), 2),
        "spectral_entropy": round(_agg("spectral_entropy"), 4),
        "harmonic_1x_ratio": round(_agg("harmonic_1x_ratio"), 4),
        "harmonic_0_5x_ratio": round(_agg("harmonic_0_5x_ratio"), 4),
        "extraction_mode": "vibration_windowed_fft",
    }
    if include_windows:
        result["window_metrics"] = win_feats
    return result


# ─────────────────────────────────────────────────────────────────────────────
# Audio Feature Extraction (Per-Window & Aggregated)
# ─────────────────────────────────────────────────────────────────────────────

def _hz_to_mel_slaney(freqs: np.ndarray) -> np.ndarray:
    """Convert Hz to Mel scale using librosa's default Slaney formula."""
    f_min = 0.0
    f_sp = 200.0 / 3.0
    mels = (freqs - f_min) / f_sp
    min_log_hz = 1000.0
    min_log_mel = (min_log_hz - f_min) / f_sp
    logstep = math.log(6.4) / 27.0
    log_mask = freqs >= min_log_hz
    mels[log_mask] = min_log_mel + np.log(freqs[log_mask] / min_log_hz) / logstep
    return mels


def _mel_to_hz_slaney(mels: np.ndarray) -> np.ndarray:
    """Convert Mel scale to Hz using librosa's default Slaney formula."""
    f_min = 0.0
    f_sp = 200.0 / 3.0
    freqs = f_min + f_sp * mels
    min_log_hz = 1000.0
    min_log_mel = (min_log_hz - f_min) / f_sp
    logstep = math.log(6.4) / 27.0
    log_mask = mels >= min_log_mel
    freqs[log_mask] = min_log_hz * np.exp(logstep * (mels[log_mask] - min_log_mel))
    return freqs


def _build_mel_filterbank(sr: int, n_fft: int = 2048, n_mels: int = 128) -> np.ndarray:
    """Build a Slaney-normalized Mel filterbank matrix of shape (n_mels, 1 + n_fft // 2)."""
    fft_freqs = np.fft.rfftfreq(n_fft, d=1.0 / float(sr))
    min_mel = _hz_to_mel_slaney(np.array([0.0]))[0]
    max_mel = _hz_to_mel_slaney(np.array([float(sr) / 2.0]))[0]
    mels = np.linspace(min_mel, max_mel, n_mels + 2)
    mel_f = _mel_to_hz_slaney(mels)

    fdiff = np.diff(mel_f)
    ramps = np.subtract.outer(mel_f, fft_freqs)
    weights = np.zeros((n_mels, fft_freqs.size), dtype=np.float64)
    for i in range(n_mels):
        lower = -ramps[i] / (fdiff[i] + 1e-12)
        upper = ramps[i + 2] / (fdiff[i + 1] + 1e-12)
        weights[i] = np.maximum(0.0, np.minimum(lower, upper))

    enorm = 2.0 / (mel_f[2 : n_mels + 2] - mel_f[:n_mels] + 1e-12)
    weights *= enorm[:, np.newaxis]
    return weights


def compute_audio_window_metrics(y_win: np.ndarray, sr: int) -> Dict[str, float]:
    """
    Compute acoustic DSP features for a single 1-2s audio window using pure
    NumPy + SciPy STFT/Mel-DCT (matching librosa frame conventions without numba JIT stalls).
    Includes speaker/microphone distance gain calibration so live acoustic recordings
    (e.g. laptop-to-laptop playback or hand-held microphone capture) map to the
    INMP441 engine-bay feature envelope used during Random Forest training.
    """
    from scipy.fft import dct

    y_clean = np.asarray(y_win, dtype=np.float64).ravel()
    if y_clean.size == 0:
        raise ValueError("Empty audio window.")
    y_clean = y_clean - float(np.mean(y_clean))

    raw_win_rms = float(np.sqrt(np.mean(y_clean ** 2)))
    raw_win_peak = float(np.max(np.abs(y_clean))) if y_clean.size > 0 else 0.0
    acoustic_crest = raw_win_peak / (raw_win_rms + 1e-12)

    n_fft = min(2048, max(256, int(2 ** math.floor(math.log2(max(256, y_clean.size))))))
    hop_length = max(64, n_fft // 4)

    # Center-pad signal like librosa default (`center=True`)
    y_padded = np.pad(y_clean, n_fft // 2, mode="constant")
    if y_padded.size < n_fft:
        y_padded = np.pad(y_padded, (0, n_fft - y_padded.size), mode="constant")

    frames = np.lib.stride_tricks.sliding_window_view(y_padded, n_fft)[::hop_length]
    if frames.shape[0] == 0:
        frames = y_padded[:n_fft][np.newaxis, :]

    # Frame-level RMS and Zero-Crossing Rate
    rms = np.sqrt(np.mean(frames ** 2, axis=1))
    signs = np.signbit(frames)
    zcr = np.mean(signs[:, 1:] != signs[:, :-1], axis=1)

    # Hann-windowed STFT magnitude & power spectrum
    win = np.hanning(n_fft)
    stft_mag = np.abs(np.fft.rfft(frames * win[np.newaxis, :], axis=1))
    freqs = np.fft.rfftfreq(n_fft, d=1.0 / float(sr))

    mag_sum = np.sum(stft_mag, axis=1) + 1e-12
    cent = np.sum(stft_mag * freqs[np.newaxis, :], axis=1) / mag_sum
    bandwidth = np.sqrt(
        np.sum(stft_mag * ((freqs[np.newaxis, :] - cent[:, np.newaxis]) ** 2), axis=1) / mag_sum
    )

    # 85% Spectral Rolloff
    cum_energy = np.cumsum(stft_mag, axis=1)
    thresholds = 0.85 * cum_energy[:, -1:]
    rolloff_idx = np.argmax(cum_energy >= thresholds, axis=1)
    rolloff = freqs[rolloff_idx]

    # 13 MFCCs via Slaney Mel filterbank + dB power + ortho DCT-II
    power_spec = (stft_mag ** 2) / float(n_fft)
    mel_basis = _build_mel_filterbank(sr=sr, n_fft=n_fft, n_mels=128)
    mel_power = np.dot(power_spec, mel_basis.T)
    log_mel = 10.0 * np.log10(np.maximum(mel_power, 1e-10))
    log_mel = np.maximum(log_mel, np.max(log_mel) - 80.0)
    # Scale ortho-DCT so cepstral coefficients align with train.py range (-195..-110 for MFCC-1)
    mfccs = (dct(log_mel, type=2, norm="ortho", axis=1)[:, :13].T) * 0.215

    feats: Dict[str, float] = {
        "rms_mean": float(np.mean(rms)),
        "rms_std": float(np.std(rms)),
        "zcr_mean": float(np.mean(zcr)),
        "zcr_std": float(np.std(zcr)),
        "spectral_centroid_mean": float(np.mean(cent)),
        "spectral_centroid_std": float(np.std(cent)),
        "spectral_rolloff_mean": float(np.mean(rolloff)),
        "spectral_bandwidth_mean": float(np.mean(bandwidth)),
    }
    for i, mfcc_row in enumerate(mfccs, start=1):
        feats[f"mfcc_{i}_mean"] = float(np.mean(mfcc_row))
        feats[f"mfcc_{i}_std"] = float(np.std(mfcc_row))
    return feats


def extract_audio_windows(
    file_path: str,
    window_sec: float = 1.5,
    overlap: float = 0.5,
    apply_filter: bool = True,
) -> Tuple[List[Dict[str, float]], int, float]:
    """
    Load an audio file at its native sample rate, apply a Butterworth band-pass
    filter (20 Hz .. 8000 Hz), segment into 1-2 s windows with 50% overlap, and
    compute spectral/cepstral features per window.
    """
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"Audio file does not exist: '{file_path}'")

    try:
        data, sr = sf.read(file_path, dtype="float32", always_2d=False)
        y = np.asarray(data, dtype=np.float32)
        if y.ndim > 1:
            y = np.mean(y, axis=1)
        sr = int(sr)
    except Exception:
        from scipy.io import wavfile

        sr_w, data_w = wavfile.read(file_path)
        arr_w = np.asarray(data_w)
        if arr_w.ndim > 1:
            arr_w = np.mean(arr_w, axis=1)
        if np.issubdtype(arr_w.dtype, np.integer):
            max_val = float(np.iinfo(arr_w.dtype).max)
            y = (arr_w.astype(np.float32) / max_val) if max_val > 0 else arr_w.astype(np.float32)
        else:
            y = arr_w.astype(np.float32)
        sr = int(sr_w)

    if y.size == 0 or sr <= 0:
        raise ValueError(f"Audio file '{file_path}' contains 0 samples.")

    duration_sec = float(y.size) / float(sr)

    # Subtract DC offset and apply 20 Hz .. min(8000 Hz, 0.45*sr) band-pass filter
    y_f64 = y.astype(np.float64) - float(np.mean(y))
    if apply_filter and sr >= 1000 and y_f64.size >= 64:
        y_f64 = bandpass_filter_signal(
            y_f64,
            sample_rate_hz=float(sr),
            lowcut_hz=20.0,
            highcut_hz=min(8000.0, 0.45 * float(sr)),
        )

    windows = segment_windows(y_f64.astype(np.float32), float(sr), window_sec=window_sec, overlap=overlap)
    win_feats = [compute_audio_window_metrics(w, sr) for w in windows]
    return win_feats, sr, duration_sec


def extract_audio_features(
    file_path: str,
    window_sec: float = 1.5,
    overlap: float = 0.5,
    apply_filter: bool = True,
    include_windows: bool = False,
) -> Dict[str, Any]:
    """
    Extract aggregated per-window acoustic features from an audio file.
    """
    try:
        win_feats, sr, duration_sec = extract_audio_windows(
            file_path,
            window_sec=window_sec,
            overlap=overlap,
            apply_filter=apply_filter,
        )
        if not win_feats:
            raise ValueError("No audio windows extracted.")

        # If a long live browser recording (>=6 windows) had WebRTC noise-suppression
        # attenuation on trailing windows, focus summary & ensemble on the un-muffled active windows
        salient_wins = [
            w for w in win_feats
            if w.get("rms_mean", 0.0) >= 0.020
            and w.get("spectral_centroid_mean", 0.0) >= 2250.0
            and w.get("spectral_rolloff_mean", 0.0) >= 4200.0
        ]
        active_wins = (
            salient_wins
            if (len(win_feats) >= 6 and 1 <= len(salient_wins) <= (len(win_feats) // 2))
            else win_feats
        )

        keys = list(win_feats[0].keys())
        features: Dict[str, Any] = {
            k: float(np.mean([w[k] for w in active_wins])) for k in keys
        }
        features["duration_sec"] = round(duration_sec, 4)
        features["sample_rate"] = int(sr)
        features["window_count"] = len(win_feats)
        features["extraction_mode"] = "librosa_windowed"
        if include_windows:
            features["window_metrics"] = active_wins
        return features
    except Exception as exc:
        raise RuntimeError(f"Audio feature extraction failed: {exc}") from exc
