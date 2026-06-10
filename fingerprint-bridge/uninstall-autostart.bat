@echo off
setlocal

set "SHORTCUT=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\FingerprintBridge.lnk"

if exist "%SHORTCUT%" (
    del /f /q "%SHORTCUT%"
    echo Removed autostart shortcut.
) else (
    echo No autostart shortcut found.
)

exit /b 0
