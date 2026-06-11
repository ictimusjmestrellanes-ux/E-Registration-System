@echo off
setlocal

set "ROOT=%~dp0"
set "BUILD_SCRIPT=%ROOT%build-bridge.bat"
set "RELEASE_EXE=%ROOT%bin\Release\net48\FingerprintBridge.exe"
set "DEBUG_EXE=%ROOT%bin\Debug\net48\FingerprintBridge.exe"

tasklist /FI "IMAGENAME eq FingerprintBridge.exe" | find /I "FingerprintBridge.exe" >nul
if %errorlevel%==0 (
    echo FingerprintBridge is already running.
    exit /b 0
)

if exist "%RELEASE_EXE%" goto run_release
if exist "%DEBUG_EXE%" goto run_debug

echo FingerprintBridge is not built yet. Building now...
call "%BUILD_SCRIPT%"
if errorlevel 1 (
    echo Build failed.
    exit /b 1
)

if exist "%RELEASE_EXE%" goto run_release
if exist "%DEBUG_EXE%" goto run_debug

echo FingerprintBridge.exe was not found after building.
exit /b 1

:run_release
start "" "%RELEASE_EXE%"
exit /b 0

:run_debug
start "" "%DEBUG_EXE%"
exit /b 0
