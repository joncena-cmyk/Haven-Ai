"""
Feature extraction module for HAVEN ML backend.

Audio features (requires librosa + soundfile):
  - RMS energy statistics
  - Zero crossing rate
  - Spectral centroid & rolloff
  - MFCCs (13 coefficients)

Vibration features (pure numpy/csv):
  - Peak G, RMS G, Crest Factor
  - Sample count, estimated frequency
"""

import numpy as np
import csv
import json
import os

# ── Audio feature extraction ──
def extract_audio_features(file_path: str) -> dict:
    """
    Extract audio features from a file using librosa.
    Falls back to basic numpy extraction if librosa unavailable.
    """
    try:
        import librosa
        y, sr = librosa.load(file_path, sr=22050, mono=True, duration=30)

        rms         = librosa.feature.rms(y=y)[0]
        zcr         = librosa.feature.zero_crossing_rate(y)[0]
        cent        = librosa.feature.spectral_centroid(y=y, sr=sr)[0]
        rolloff     = librosa.feature.spectral_rolloff(y=y, sr=sr)[0]
        mfccs       = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=13)
        bandwidth   = librosa.feature.spectral_bandwidth(y=y, sr=sr)[0]

        features = {
            "rms_mean":               float(np.mean(rms)),
            "rms_std":                float(np.std(rms)),
            "zcr_mean":               float(np.mean(zcr)),
            "zcr_std":                float(np.std(zcr)),
            "spectral_centroid_mean": float(np.mean(cent)),
            "spectral_centroid_std":  float(np.std(cent)),
            "spectral_rolloff_mean":  float(np.mean(rolloff)),
            "spectral_bandwidth_mean":float(np.mean(bandwidth)),
        }

        for i, mfcc_row in enumerate(mfccs, start=1):
            features[f"mfcc_{i}_mean"] = float(np.mean(mfcc_row))
            features[f"mfcc_{i}_std"]  = float(np.std(mfcc_row))

        features["duration_sec"]   = float(len(y) / sr)
        features["sample_rate"]    = int(sr)
        features["extraction_mode"] = "librosa"
        return features

    except ImportError:
        # Fallback: basic file-size-based placeholder (no audio processing)
        size = os.path.getsize(file_path)
        return {
            "file_size_bytes":   size,
            "estimated_duration": round(size / 16000, 2),   # rough estimate
            "extraction_mode":   "fallback_no_librosa",
            "rms_mean":          None,
            "zcr_mean":          None,
            "spectral_centroid_mean": None,
        }

    except Exception as e:
        raise RuntimeError(f"Audio feature extraction failed: {e}") from e


# ── Vibration feature extraction ──
def extract_vibration_features(file_path: str, ext: str = "csv") -> dict:
    """
    Extract vibration features from an ESP32 CSV or JSON data file.

    CSV expected format (with header):
        timestamp,x,y,z        — individual axis readings (g)
    or
        timestamp,magnitude     — pre-computed magnitude

    JSON expected format:
        [{"timestamp": ..., "x": ..., "y": ..., "z": ...}, ...]
    or
        [{"timestamp": ..., "magnitude": ...}, ...]
    """
    magnitudes = []

    try:
        if ext.lower() == "json":
            with open(file_path, "r") as f:
                data = json.load(f)
            for row in data:
                if "magnitude" in row:
                    magnitudes.append(float(row["magnitude"]))
                elif all(k in row for k in ("x", "y", "z")):
                    x, y, z = float(row["x"]), float(row["y"]), float(row["z"])
                    magnitudes.append(np.sqrt(x**2 + y**2 + z**2))

        else:  # CSV
            with open(file_path, "r", newline="", errors="replace") as f:
                reader = csv.reader(f)
                header = next(reader, None)
                for row in reader:
                    if not row:
                        continue
                    try:
                        if len(row) >= 4:
                            x, y, z = float(row[1]), float(row[2]), float(row[3])
                            magnitudes.append(np.sqrt(x**2 + y**2 + z**2))
                        elif len(row) >= 2:
                            magnitudes.append(abs(float(row[1])))
                    except (ValueError, IndexError):
                        continue

    except Exception as e:
        raise RuntimeError(f"Vibration feature extraction failed: {e}") from e

    if not magnitudes:
        return {"sample_count": 0, "extraction_mode": "vibration_empty"}

    arr = np.array(magnitudes, dtype=np.float64)
    peak    = float(np.max(arr))
    rms     = float(np.sqrt(np.mean(arr ** 2)))
    mean_g  = float(np.mean(arr))
    std_g   = float(np.std(arr))
    crest   = float(peak / rms) if rms > 0 else 0.0

    return {
        "sample_count":     int(len(magnitudes)),
        "peak_g":           round(peak, 4),
        "rms_g":            round(rms, 4),
        "mean_g":           round(mean_g, 4),
        "std_g":            round(std_g, 4),
        "crest_factor":     round(crest, 4),
        "extraction_mode":  "vibration_csv",
    }
