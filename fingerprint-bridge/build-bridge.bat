@echo off
setlocal

set "ROOT=%~dp0"
set "PROJECT=%ROOT%FingerprintBridge.csproj"

where dotnet >nul 2>nul
if %errorlevel%==0 (
    dotnet build "%PROJECT%" -c Release
    exit /b %errorlevel%
)

where msbuild >nul 2>nul
if %errorlevel%==0 (
    msbuild "%PROJECT%" /p:Configuration=Release /p:Platform=AnyCPU
    exit /b %errorlevel%
)

echo Neither dotnet nor msbuild was found.
echo Install the .NET SDK or Visual Studio Build Tools, then run this again.
exit /b 1
