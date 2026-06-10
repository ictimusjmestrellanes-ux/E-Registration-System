@echo off
setlocal

set "ROOT=%~dp0"
set "BRIDGE_EXE=%ROOT%bin\Release\net48\FingerprintBridge.exe"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT=%STARTUP%\FingerprintBridge.lnk"

if not exist "%BRIDGE_EXE%" (
    echo Build the bridge first so the Release EXE exists.
    echo Expected file: %BRIDGE_EXE%
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ws = New-Object -ComObject WScript.Shell; " ^
    "$s = $ws.CreateShortcut('%SHORTCUT%'); " ^
    "$s.TargetPath = '%BRIDGE_EXE%'; " ^
    "$s.WorkingDirectory = '%ROOT%bin\Release\net48'; " ^
    "$s.Description = 'DigitalPersona Fingerprint Bridge'; " ^
    "$s.Save()"

if errorlevel 1 (
    echo Failed to create the startup shortcut.
    exit /b 1
)

echo Autostart installed:
echo %SHORTCUT%
exit /b 0
