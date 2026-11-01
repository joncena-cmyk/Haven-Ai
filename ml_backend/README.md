# HAVEN ML Backend

FastAPI engine condition classifier for the HAVEN diagnostic system.

## Setup (Python 3.10+)

```bash
cd ml_backend
pip install -r requirements.txt
python main.py
```

The server runs at **http://localhost:8001**

---

## Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/health` | Liveness check + model mode |
| GET | `/info` | Feature spec and class list |
| POST | `/analyze` | Classify engine from audio + vibration |

### POST `/analyze`

Form-data fields:

| Field | Type | Required |
|---|---|---|
| `audio_file` | File (webm/wav/mp3) | At least one |
| `vibration_file` | File (csv/json) | At least one |
| `diagnostic_id` | int | Optional |
| `vehicle_id` | int | Optional |

### Response

```json
{
  "condition": "knocking",
  "confidence": 0.812,
  "confidence_pct": "81.2%",
  "top_classes": [
    {"condition": "knocking", "confidence": 0.812},
    {"condition": "normal",   "confidence": 0.134},
    {"condition": "misfiring","confidence": 0.054}
  ],
  "features_used": ["peak_g", "rms_g", "crest_factor", ...],
  "audio_features": {...},
  "vibration_features": {...},
  "mode": "rule_based",
  "processed_at": "2026-10-20T01:00:00Z"
}
```

---

## Replacing the rule-based classifier with a trained model

1. Train a scikit-learn pipeline and save it:

```python
import pickle

payload = {
    "model": pipeline,               # sklearn Pipeline or estimator
    "meta": {
        "feature_order": [...],      # list of feature keys in order
        "trained_on": "2026-10-20",
        "accuracy": 0.94,
    }
}
with open("model.pkl", "wb") as f:
    pickle.dump(payload, f)
```

2. Drop `model.pkl` into the `ml_backend/` folder. The server auto-loads it on next start.

---

## Condition classes

| Class | Description |
|---|---|
| `normal` | Engine running within expected parameters |
| `knocking` | Detonation / pre-ignition (high peak G, low freq audio) |
| `misfiring` | Irregular combustion (high ZCR, unstable vibration) |
| `idling_rough` | Rough idle at low RPM |
| `overheating` | Thermal stress signature in audio spectrum |

---

## HAVEN Capstone — Group 3, CINS
