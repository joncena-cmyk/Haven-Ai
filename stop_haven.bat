@echo off
title Stopping Services - HAVEN
cd /d "%~dp0"
color 0C

echo ========================================================================
echo   Stopping HAVEN AI Services...
echo ========================================================================
echo.

echo [1/3] Terminating Laravel Web Server (Port 8000)...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING 2^>nul') do (
    taskkill /F /PID %%a >nul 2>&1
)

echo [2/3] Terminating FastAPI ML Backend (Port 8001)...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8001 ^| findstr LISTENING 2^>nul') do (
    taskkill /F /PID %%a >nul 2>&1
)

echo [3/3] Terminating Laravel Queue Workers and Service Windows...
taskkill /FI "WINDOWTITLE eq HAVEN AI*" /T /F >nul 2>&1

echo.
echo ========================================================================
echo   [OK] All HAVEN AI services have been cleanly shut down.
echo ========================================================================
echo.
ping 127.0.0.1 -n 3 >nul
