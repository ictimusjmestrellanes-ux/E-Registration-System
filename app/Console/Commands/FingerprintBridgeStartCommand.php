<?php

namespace App\Console\Commands;

use App\Services\FingerprintBridgeService;
use Illuminate\Console\Command;

class FingerprintBridgeStartCommand extends Command
{
    protected $signature = 'fingerprint-bridge:start
        {--restart : Restart the bridge if already running}
        {--stop : Stop the bridge}
        {--status : Show bridge status}';

    protected $description = 'Manage the DigitalPersona fingerprint scanner bridge process';

    public function handle(FingerprintBridgeService $bridge): int
    {
        if ($this->option('stop')) {
            return $this->stopBridge($bridge);
        }

        if ($this->option('status')) {
            return $this->showStatus($bridge);
        }

        if ($this->option('restart')) {
            return $this->restartBridge($bridge);
        }

        return $this->startBridge($bridge);
    }

    private function startBridge(FingerprintBridgeService $bridge): int
    {
        $this->line('Checking fingerprint bridge...');

        if ($bridge->isHealthy()) {
            $this->info('Fingerprint bridge is already running and healthy.');

            return Command::SUCCESS;
        }

        $this->line('Bridge is not running. Starting...');

        if ($bridge->start()) {
            $status = $bridge->getStatus();
            $this->info(sprintf('Fingerprint bridge started successfully (%d reader(s) detected).', $status['readers_count']));

            return Command::SUCCESS;
        }

        $this->error('Failed to start fingerprint bridge. Check the scanner connection and SDK installation.');

        return Command::FAILURE;
    }

    private function stopBridge(FingerprintBridgeService $bridge): int
    {
        $this->line('Stopping fingerprint bridge...');

        if (!$bridge->isProcessRunning()) {
            $this->info('Fingerprint bridge is not running.');

            return Command::SUCCESS;
        }

        if ($bridge->stop()) {
            $this->info('Fingerprint bridge stopped.');

            return Command::SUCCESS;
        }

        $this->error('Failed to stop fingerprint bridge.');

        return Command::FAILURE;
    }

    private function restartBridge(FingerprintBridgeService $bridge): int
    {
        $this->line('Restarting fingerprint bridge...');

        if ($bridge->restart()) {
            $status = $bridge->getStatus();
            $this->info(sprintf('Fingerprint bridge restarted successfully (%d reader(s) detected).', $status['readers_count']));

            return Command::SUCCESS;
        }

        $this->error('Failed to restart fingerprint bridge.');

        return Command::FAILURE;
    }

    private function showStatus(FingerprintBridgeService $bridge): int
    {
        $status = $bridge->getStatus();

        $this->table(
            ['Property', 'Value'],
            [
                ['Running', $status['running'] ? 'Yes' : 'No'],
                ['Healthy', $status['healthy'] ? 'Yes' : 'No'],
                ['Built', $status['built'] ? 'Yes' : 'No'],
                ['Readers Detected', (string) $status['readers_count']],
                ['Bridge URL', $status['bridge_url']],
                ['Executable Path', $status['exe_path'] ?? 'Not built'],
            ]
        );

        if (!empty($status['readers'])) {
            $this->newLine();
            $this->info('Connected Readers:');

            foreach ($status['readers'] as $reader) {
                $this->line(sprintf('  - %s', $reader['name']));
            }
        } elseif ($status['healthy']) {
            $this->warn('Bridge is running but no fingerprint reader detected.');
        }

        return Command::SUCCESS;
    }
}
