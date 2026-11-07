@echo off
title HAVEN AI - Graphical Dataset Recorder and ML Studio
cd /d "%~dp0"
color 0B

echo ========================================================================
echo   HAVEN AI - Graphical Dataset Recorder and ML Studio
echo   Launching Desktop Data Acquisition and Training Window...
echo ========================================================================
echo.

python "%~dp0recorder\dataset_studio.py"

if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Dataset Studio exited with code %ERRORLEVEL%.
    echo Make sure Python dependencies are installed:
    echo   pip install -r recorder/requirements.txt -r ml_backend/requirements.txt
    pause
)

