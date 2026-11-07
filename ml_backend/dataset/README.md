# HAVEN Engine Dataset Directory

Place your recorded `.wav` audio files and `.csv` 3-axis vibration telemetry logs into their corresponding canonical **4-class** subdirectories (or use the **Graphical Dataset Recorder & ML Studio** via `start_dataset_studio.bat` / `recordings/<session_id>/<vehicle>/<condition>/<timestamp>/`):

```
ml_backend/dataset/
├── normal/           # Healthy 4-stroke engine baseline idle & revving
├── misfire/          # Combustion dropout / ignition or injector misfire
├── loose_component/  # Degraded motor mount / loose bracket structural vibration
└── valve_clearance/  # Improper valvetrain lash / 0.5x RPM metallic tapping
```

To train `model.pkl` on synchronized recordings or dataset folders, run:
```bash
# Preferred (Phase 4 GroupKFold vehicle-level evaluation):
python train.py --recordings-dir ../recordings

# Or hybrid calibration mode:
python train.py --generate-synthetic
```
