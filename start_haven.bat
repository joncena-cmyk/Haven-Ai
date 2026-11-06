@echo off
setlocal enabledelayedexpansion
title HAVEN AI - Capstone System Launcher
cd /d "%~dp0"
color 0B

echo ========================================================================
echo   HAVEN AI: Multi-Sensor Powertrain Diagnostic and Defense Platform
echo   UMak CET Capstone Defense Launcher
echo ========================================================================
echo.

:: ── 1. CHECK / START MYSQL DATABASE (PORT 3306) ──
echo [1/4] Checking MySQL Database Service...
netstat -ano | findstr :3306 | findstr LISTENING >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo   [OK] MySQL Database is already active on port 3306.
) else (
    echo   [..] Starting MySQL database via XAMPP...
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "HAVEN - MySQL Daemon" /b "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        ping 127.0.0.1 -n 4 >nul
        netstat -ano | findstr :3306 | findstr LISTENING >nul 2>&1
        if !ERRORLEVEL! equ 0 (
            echo   [OK] MySQL Database started successfully on port 3306.
        ) else (
            echo   [WARN] Could not bind port 3306 automatically. Ensure XAMPP MySQL is started.
        )
    ) else (
        echo   [WARN] XAMPP MySQL not found at C:\xampp\mysql. Please start MySQL manually.
    )
)
echo.

:: ── 2. DETECT LOCAL WI-FI / LAN IP FOR ESP32 TELEMETRY ──
echo [2/4] Detecting Network Configuration for ESP32...
set LOCAL_IP=
for /f "usebackq tokens=*" %%i in (`python -c "import socket; s=socket.socket(socket.AF_INET, socket.SOCK_DGRAM); s.connect(('8.8.8.8', 80)); print(s.getsockname()[0]); s.close()" 2^>nul`) do set LOCAL_IP=%%i
if "%LOCAL_IP%"=="" (
    for /f "usebackq tokens=*" %%i in (`python -c "import socket; print(socket.gethostbyname(socket.gethostname()))" 2^>nul`) do set LOCAL_IP=%%i
)
if "%LOCAL_IP%"=="" set LOCAL_IP=127.0.0.1

echo   ------------------------------------------------------------------
echo   * Laptop Local Wi-Fi IP  : %LOCAL_IP%
echo   * Web App Browser URL    : http://localhost:8000
echo   * ESP32 Server URL to use: http://%LOCAL_IP%:8000/api/v1/telemetry/ingest
echo   ------------------------------------------------------------------
echo.

:: ── 3. LAUNCH FASTAPI ML BACKEND (PORT 8001) ──
echo [3/4] Launching FastAPI ML Engine (Port 8001)...
netstat -ano | findstr :8001 | findstr LISTENING >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo   [OK] ML Backend (FastAPI) is already active on port 8001.
) else (
    start "HAVEN AI - ML Backend (FastAPI :8001)" cmd /k "title HAVEN AI - ML Backend && cd /d "%~dp0ml_backend" && python -m uvicorn main:app --host 127.0.0.1 --port 8001"
    echo   [OK] Spawned FastAPI ML service on port 8001.
)
echo.

:: ── 4. LAUNCH LARAVEL WEB APP AND QUEUE WORKER (PORT 8000) ──
echo [4/4] Launching HAVEN Laravel App and Queue Worker...
netstat -ano | findstr :8000 | findstr LISTENING >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo   [OK] Laravel Web App is already active on port 8000.
) else (
    start "HAVEN AI - Web App (Laravel :8000)" cmd /k "title HAVEN AI - Web App && cd /d "%~dp0haven" && php artisan serve --host=0.0.0.0 --port=8000"
    echo   [OK] Spawned Laravel HTTP server bound to 0.0.0.0:8000.
)

start "HAVEN AI - Queue Worker" cmd /k "title HAVEN AI - Queue Worker && cd /d "%~dp0haven" && php artisan queue:work --tries=3 --timeout=90"
echo   [OK] Spawned Laravel Asynchronous Queue Worker.
echo.

:: ── WAIT FOR INITIALIZATION AND OPEN BROWSER ──
echo Initializing ecosystem, opening dashboard in 4 seconds...
ping 127.0.0.1 -n 5 >nul
start http://localhost:8000

:MENU
cls
color 0A
echo ========================================================================
echo   HAVEN AI ECOSYSTEM IS ACTIVE AND RUNNING
echo ========================================================================
echo.
echo   * Web Application       : http://localhost:8000
echo   * Admin ML Console      : http://localhost:8000/admin/ml
echo   * FastAPI ML Backend    : http://127.0.0.1:8001/info
echo   * ESP32 Telemetry Ingest: http://%LOCAL_IP%:8000/api/v1/telemetry/ingest
echo.
echo ========================================================================
echo   QUICK ACTIONS:
echo   [1] Open HAVEN Dashboard in Browser
echo   [2] Open Admin ML and Datasets Console
echo   [3] Open Hardware and Live Telemetry Fleet
echo   [4] Test FastAPI ML Health Endpoint
echo   [S] Stop all HAVEN AI Services (Shutdown)
echo   [Q] Exit this console (services will remain running)
echo ========================================================================
echo.
set /p CHOICE="Select an option (1-4, S, Q): "

if /i "%CHOICE%"=="1" (
    start http://localhost:8000/dashboard
    goto MENU
)
if /i "%CHOICE%"=="2" (
    start http://localhost:8000/admin/ml
    goto MENU
)
if /i "%CHOICE%"=="3" (
    start http://localhost:8000/vehicles
    goto MENU
)
if /i "%CHOICE%"=="4" (
    start http://127.0.0.1:8001/health
    goto MENU
)
if /i "%CHOICE%"=="S" (
    call "%~dp0stop_haven.bat"
    exit /b 0
)
if /i "%CHOICE%"=="Q" (
    echo.
    echo Leaving services running in background. Run stop_haven.bat to shut down.
    ping 127.0.0.1 -n 3 >nul
    exit /b 0
)
goto MENU
