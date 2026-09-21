<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Services\FingerprintBridgeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Illuminate\Support\Facades\URL;

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
        foreach ([
            \App\Models\Client::class,
            \App\Models\ArchivedClient::class,
            \App\Models\TransactionEvent::class,
            \App\Models\TransactionHistory::class,
            \App\Models\TransactionRequirement::class,
            \App\Models\ImportArchiveFile::class,
            \App\Models\User::class,
            \App\Models\Role::class,
            \App\Models\Permission::class,
        ] as $model) {
            $model::observe(\App\Observers\ActivityObserver::class);
        }

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        // Navbar notification feed: latest import / transfer / tag / delete /
        // undo updates. Admins see every user's updates; other roles see only
        // their own (same scoping as the Activity Logs page).
        View::composer('layouts.master', function ($view): void {
            if (!auth()->check()) {
                return;
            }

            try {
                $isPrivileged = in_array(auth()->user()->role_name, ['Admin', 'Super Admin'], true);
                $baseQuery = ActivityLog::query()->whereIn('action', ActivityLog::NOTIFICATION_ACTIONS);
                if (! $isPrivileged) {
                    $baseQuery->where('user_id', auth()->id());
                }

                $notifications = (clone $baseQuery)->with('user')
                    ->latest()
                    ->orderByDesc('id')
                    ->take(8)
                    ->get();

                // Anything logged after the user's read marker (higher id)
                // counts as unread.
                $readId = auth()->user()->notifications_read_id;
                $unreadCount = (clone $baseQuery)
                    ->when($readId, fn ($query) => $query->where('id', '>', $readId))
                    ->count();

                $view->with('navbarNotifications', $notifications)
                    ->with('navbarNotificationCount', $notifications->count())
                    ->with('navbarUnreadCount', $unreadCount);
            } catch (\Throwable) {
                // The navbar must never break page rendering (e.g. pending
                // migrations on a fresh checkout).
                $view->with('navbarNotifications', collect())
                    ->with('navbarNotificationCount', 0)
                    ->with('navbarUnreadCount', 0);
            }
        });

        if ($this->app->runningInConsole()) {
            return;
        }

        if (!app()->environment('production')) {
            $this->autoStartFingerprintBridge();
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
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
