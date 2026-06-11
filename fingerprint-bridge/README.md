# DigitalPersona Fingerprint Bridge

This folder contains a small Windows bridge app that talks to the installed DigitalPersona U.are.U SDK and exposes a local HTTP API for the Laravel app.

## What it does

- Starts a hidden Windows Forms process
- Exposes `http://127.0.0.1:38654/api/health`
- Exposes `http://127.0.0.1:38654/api/readers`
- Exposes `POST http://127.0.0.1:38654/api/capture`
- Returns a `data:image/png;base64,...` fingerprint image that the Laravel forms can store in `fingerprint_data`

## SDK path used

The project references:

- `C:\Program Files\DigitalPersona\U.are.U SDK\Windows\Lib\.NET\DPUruNet.dll`

If your SDK is installed somewhere else, update the `HintPath` in `FingerprintBridge.csproj`.

## Build and run without Visual Studio

You do not need to open Visual Studio to use this bridge.

1. Build the bridge from a terminal:
   - `fingerprint-bridge\build-bridge.bat`
2. Run it manually:
   - `fingerprint-bridge\run-bridge.bat`
   - If the bridge has not been built yet, `run-bridge.bat` will build it first and then launch it.
3. Install auto-start for Windows:
   - `fingerprint-bridge\install-autostart.bat`
   - This adds a Startup shortcut that launches `run-bridge.bat`, so the bridge can rebuild and start automatically when Windows logs in.
4. Remove auto-start later:
   - `fingerprint-bridge\uninstall-autostart.bat`

The bridge targets `.NET Framework 4.8`.

If `HttpListener` needs a URL reservation, run `fingerprint-bridge\register-urlacl.bat` as Administrator once.

If you prefer VS Code, the repository includes VS Code tasks in [`.vscode/tasks.json`](../.vscode/tasks.json).

## Laravel integration

The Laravel app should call the bridge endpoint from the browser and store the returned `imageDataUrl` in the `fingerprint_data` hidden field.
