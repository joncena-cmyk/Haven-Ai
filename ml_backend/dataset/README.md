# HAVEN Engine Dataset Directory
Place your recorded `.wav` / `.webm` audio files and `.csv` vibration telemetry logs into their corresponding class subdirectories:

```
ml_backend/dataset/
├── normal/          # Smooth 4-stroke engine baseline idle & cruise
├── knocking/        # Rod knocking / detonation pinging
├── misfiring/       # Combustion dropout / ignition misfire
├── idling_rough/    # Rough mount vibration / hunting idle
└── overheating/     # Thermal cavitation / boiling / severe heat stress
```

To train `model.pkl` on this data, run:
```bash
python train.py
```
