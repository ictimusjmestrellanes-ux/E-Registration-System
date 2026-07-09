<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FingerprintBridgeService
{
    private const BRIDGE_BASE = 'http://127.0.0.1:38654';

    private string $bridgeDir;
    private string $exePath;

    public function __construct()
    {
        $this->bridgeDir = base_path('fingerprint-bridge');
        $this->exePath = $this->bridgeDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'Release' . DIRECTORY_SEPARATOR . 'net48' . DIRECTORY_SEPARATOR . 'FingerprintBridge.exe';
    }

    public function isProcessRunning(): bool
    {
        $output = [];
        exec('tasklist /FI "IMAGENAME eq FingerprintBridge.exe" /NH 2>nul', $output, $exitCode);

        return collect($output)->contains(function ($line) {
            return str_contains($line, 'FingerprintBridge.exe');
        });
    }

    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(3)->get(self::BRIDGE_BASE . '/api/health');

            return $response->successful() && ($response->json('success') ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isBuilt(): bool
    {
        return file_exists($this->exePath);
    }

    public function build(): bool
    {
        $buildScript = $this->bridgeDir . DIRECTORY_SEPARATOR . 'build-bridge.bat';

        if (!file_exists($buildScript)) {
            Log::error('Fingerprint bridge build script not found.', ['path' => $buildScript]);

            return false;
        }

        $output = [];
        $exitCode = 0;
        exec(sprintf('"%s" 2>&1', $buildScript), $output, $exitCode);

        if ($exitCode !== 0 || !$this->isBuilt()) {
            Log::error('Fingerprint bridge build failed.', ['output' => implode("\n", $output)]);

            return false;
        }

        return true;
    }

    public function registerUrlAcl(): bool
    {
        $output = [];
        exec('netsh http show urlacl url=http://127.0.0.1:38654/ 2>nul', $output, $exitCode);

        $alreadyRegistered = collect($output)->contains(function ($line) {
            return str_contains($line, 'http://127.0.0.1:38654/');
        });

        if ($alreadyRegistered) {
            return true;
        }

        $user = getenv('USERDOMAIN') . '\\' . getenv('USERNAME');
        exec(sprintf('netsh http add urlacl url=http://127.0.0.1:38654/ user="%s" 2>&1', $user), $output, $exitCode);

        if ($exitCode !== 0) {
            Log::warning('Failed to register URL ACL for fingerprint bridge.', ['output' => implode("\n", $output)]);
        }

        return $exitCode === 0;
    }

    public function start(): bool
    {
        if ($this->isHealthy()) {
            return true;
        }

        if ($this->isProcessRunning() && !$this->isHealthy()) {
            $this->killZombie();
        }

        if ($this->isProcessRunning()) {
            return true;
        }

        if (!$this->isBuilt()) {
            Log::info('Fingerprint bridge binary not found. Building now...');

            if (!$this->build()) {
                return false;
            }
        }

        $this->registerUrlAcl();

        if (!$this->launchProcess()) {
            return false;
        }

        $this->installAutostartShortcut();

        for ($i = 0; $i < 50; $i++) {
            if ($this->isHealthy()) {
                return true;
            }

            usleep(200000);
        }

        return $this->isHealthy();
    }

    public function ensureRunning(): bool
    {
        if ($this->isHealthy()) {
            return true;
        }

        return $this->start();
    }

    public function stop(): bool
    {
        exec('taskkill /F /IM FingerprintBridge.exe 2>nul', $output, $exitCode);

        $maxWait = 20;

        for ($i = 0; $i < $maxWait; $i++) {
            if (!$this->isProcessRunning()) {
                return true;
            }

            usleep(250000);
        }

        return !$this->isProcessRunning();
    }

    public function restart(): bool
    {
        $this->stop();
        sleep(1);

        return $this->start();
    }

    public function getStatus(): array
    {
        $processRunning = $this->isProcessRunning();
        $healthy = $this->isHealthy();
        $built = $this->isBuilt();

        $readers = [];

        if ($healthy) {
            try {
                $response = Http::timeout(3)->get(self::BRIDGE_BASE . '/api/readers');
                if ($response->successful()) {
                    $readers = $response->json('readers', []);
                }
            } catch (\Exception $e) {
                // Reader list is best-effort
            }
        }

        return [
            'success' => true,
            'running' => $processRunning,
            'healthy' => $healthy,
            'built' => $built,
            'readers' => $readers,
            'readers_count' => count($readers),
            'exe_path' => $built ? $this->exePath : null,
            'bridge_url' => self::BRIDGE_BASE,
        ];
    }

    private function installAutostartShortcut(): bool
    {
        $installScript = $this->bridgeDir . DIRECTORY_SEPARATOR . 'install-autostart.bat';

        if (!file_exists($installScript)) {
            Log::warning('Fingerprint bridge autostart script not found.', ['path' => $installScript]);
            return false;
        }

        exec(sprintf('"%s" 2>&1', $installScript), $output, $exitCode);

        if ($exitCode !== 0) {
            Log::warning('Fingerprint bridge autostart installation failed.', [
                'output' => implode("\n", $output),
                'script' => $installScript,
            ]);
            return false;
        }

        return true;
    }

    private function killZombie(): void
    {
        exec('taskkill /F /IM FingerprintBridge.exe 2>nul');

        for ($i = 0; $i < 10; $i++) {
            if (!$this->isProcessRunning()) {
                return;
            }

            usleep(300000);
        }
    }

    private function launchProcess(): bool
    {
        $exePath = $this->exePath;
        $exeArg = sprintf('"%s" /auto', $exePath);

        // Method 1: START command via popen (most reliable for interactive user sessions)
        try {
            pclose(popen(sprintf('start "" %s > NUL 2>&1', $exeArg), 'r'));
            return true;
        } catch (\Exception $e) {
            Log::warning('popen start failed for fingerprint bridge.', ['error' => $e->getMessage()]);
        }

        // Method 2: Direct process creation (works under SYSTEM/headless)
        try {
            $process = proc_open(
                $exeArg,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true, 'suppress_errors' => true]
            );

            if (is_resource($process)) {
                proc_close($process);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning('proc_open failed for fingerprint bridge.', ['error' => $e->getMessage()]);
        }

        // Method 3: Scheduled task (runs as interactive user, survives Apache restart)
        try {
            $taskName = 'FingerprintBridge_ERegSystem';
            exec(sprintf(
                'schtasks /create /tn "%s" /tr "%s /auto" /sc once /st 00:00 /f /rl HIGHEST 2>nul',
                $taskName,
                $exePath
            ), $_, $exitCode);
            exec(sprintf('schtasks /run /tn "%s" /f 2>nul', $taskName), $_, $exitCode);
            exec(sprintf('schtasks /delete /tn "%s" /f 2>nul', $taskName), $_, $exitCode);
            return true;
        } catch (\Exception $e) {
            Log::warning('Scheduled task method failed for fingerprint bridge.', ['error' => $e->getMessage()]);
        }

        Log::error('All bridge launch methods failed.', ['exe' => $exePath]);

        return false;
    }
}
