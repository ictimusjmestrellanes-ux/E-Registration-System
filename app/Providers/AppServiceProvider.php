<?php

namespace App\Providers;

use App\Services\FingerprintBridgeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FingerprintBridgeService::class, function () {
            return new FingerprintBridgeService();
        });
    }

    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        if ($this->app->runningInConsole()) {
            return;
        }

        $this->autoStartFingerprintBridge();
    }

    private function autoStartFingerprintBridge(): void
    {
        if (!config('fingerprint.auto_start', true)) {
            return;
        }

        $checkKey = 'fingerprint_bridge_health_check';

        $shouldCheck = Cache::remember($checkKey, now()->addSeconds(30), function () {
            return true;
        });

        if (!$shouldCheck) {
            return;
        }

        try {
            $bridge = app(FingerprintBridgeService::class);

            if ($bridge->isHealthy()) {
                return;
            }

            $bridge->start();
        } catch (\Throwable $e) {
            logger()->error('Auto-start fingerprint bridge failed: ' . $e->getMessage());
        }
    }
}
