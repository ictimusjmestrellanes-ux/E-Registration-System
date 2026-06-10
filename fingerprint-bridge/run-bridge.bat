@echo off
setlocal

set "ROOT=%~dp0"
set "RELEASE_EXE=%ROOT%bin\Release\net48\FingerprintBridge.exe"
set "DEBUG_EXE=%ROOT%bin\Debug\net48\FingerprintBridge.exe"

tasklist /FI "IMAGENAME eq FingerprintBridge.exe" | find /I "FingerprintBridge.exe" >nul
if %errorlevel%==0 (
    echo FingerprintBridge is already running.
    exit /b 0
)

if exist "%RELEASE_EXE%" (
    start "" "%RELEASE_EXE%"
    exit /b 0
)

if exist "%DEBUG_EXE%" (
    start "" "%DEBUG_EXE%"
    exit /b 0
)

echo FingerprintBridge.exe was not found.
echo Run build-bridge.bat first.
exit /b 1
