@echo off
setlocal

set "ROOT=%~dp0"
set "RUN_BRIDGE=%ROOT%run-bridge.bat"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT=%STARTUP%\FingerprintBridge.lnk"

if not exist "%RUN_BRIDGE%" (
    echo Cannot find the bridge launcher.
    echo Expected file: %RUN_BRIDGE%
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ws = New-Object -ComObject WScript.Shell; " ^
    "$s = $ws.CreateShortcut('%SHORTCUT%'); " ^
    "$s.TargetPath = '%COMSPEC%'; " ^
    "$s.Arguments = '/c ""%RUN_BRIDGE%""'; " ^
    "$s.WorkingDirectory = '%ROOT%'; " ^
    "$s.WindowStyle = 7; " ^
    "$s.Description = 'DigitalPersona Fingerprint Bridge'; " ^
    "$s.Save()"

if errorlevel 1 (
    echo Failed to create the startup shortcut.
    exit /b 1
)

echo Autostart installed:
echo %SHORTCUT%
exit /b 0
