<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $timezone = 'Asia/Manila';
        $manilaNow = now($timezone);
        $viewOwnOnly = !in_array(auth()->user()->role_name, ['Admin', 'Super Admin']);

        $baseQuery = ActivityLog::with('user')->latest()->orderByDesc('id');
        if ($viewOwnOnly) {
            $baseQuery->where('user_id', auth()->id());
        }

        $overviewActions = (clone $baseQuery)
            ->reorder()
            ->distinct()
            ->pluck('action')
            ->filter()
            ->sort()
            ->values();
        $overviewUsers = $viewOwnOnly
            ? collect()
            : User::query()
                ->whereIn('id', (clone $baseQuery)->reorder()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

        $overviewSearch = trim((string) $request->input('overview_search', ''));
        $overviewAction = (string) $request->input('overview_action', '');
        $overviewUserId = $viewOwnOnly ? '' : (string) $request->input('overview_user', '');

        if ($overviewAction !== '') {
            $baseQuery->where('action', $overviewAction);
        }

        if ($overviewUserId !== '') {
            $baseQuery->where('user_id', $overviewUserId);
        }

        if ($overviewSearch !== '') {
            $baseQuery->where(function ($query) use ($overviewSearch) {
                $query->where('description', 'like', "%{$overviewSearch}%")
                    ->orWhere('action', 'like', "%{$overviewSearch}%")
                    ->orWhere('ip_address', 'like', "%{$overviewSearch}%")
                    ->orWhereHas('user', function ($userQuery) use ($overviewSearch) {
                        $userQuery->where('name', 'like', "%{$overviewSearch}%")
                            ->orWhere('email', 'like', "%{$overviewSearch}%");
                    });
            });
        }

        $allActivities = (clone $baseQuery)
            ->paginate(10, ['*'], 'overview_page')
            ->withQueryString();

        $monthStart = $manilaNow->copy()->startOfMonth()->setTimezone('UTC');
        $monthEnd = $manilaNow->copy()->endOfMonth()->setTimezone('UTC');

        $monthlyActivities = (clone $baseQuery)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->take(8)
            ->get();

        $todayActivities = $monthlyActivities->filter(function ($a) use ($timezone) {
            return $a->created_at && $a->created_at->setTimezone($timezone)->isToday();
        })->values();

        $weeklyActivities = $monthlyActivities->filter(function ($a) use ($timezone, $manilaNow) {
            return $a->created_at
                && $a->created_at->setTimezone($timezone)
                    ->between($manilaNow->copy()->startOfWeek(), $manilaNow->copy()->endOfWeek());
        })->values();

        $activitiesQuery = ActivityLog::with('user')->latest()->orderByDesc('id');
        if ($viewOwnOnly) {
            $activitiesQuery->where('user_id', auth()->id());
        }

        $period = $request->input('period', 'all');
        $actionFilter = $request->input('action', '');
        $search = $request->input('search', '');

        if ($period !== 'all') {
            $periodMap = [
                '7days'  => 7,
                '14days' => 14,
                '30days' => 30,
                '3months' => 90,
                '6months' => 180,
                '1year' => 365,
                '2years' => 730,
                '3years' => 1095,
            ];

            $startInTz = null;
            $endInTz = null;

            switch ($period) {
                case 'today':
                    $startInTz = $manilaNow->copy()->startOfDay();
                    $endInTz = $startInTz->copy()->addDay();
                    break;
                case 'this_week':
                    $startInTz = $manilaNow->copy()->startOfWeek();
                    $endInTz = $startInTz->copy()->addWeek();
                    break;
                case 'this_month':
                    $startInTz = $manilaNow->copy()->startOfMonth();
                    $endInTz = $startInTz->copy()->addMonth();
                    break;
                default:
                    if (isset($periodMap[$period])) {
                        $startInTz = $manilaNow->copy()->subDays($periodMap[$period]);
                    }
            }

            if ($startInTz) {
                $activitiesQuery->where('created_at', '>=', $startInTz->setTimezone('UTC'));

                if ($endInTz) {
                    $activitiesQuery->where('created_at', '<', $endInTz->setTimezone('UTC'));
                }
            }
        }

        if ($actionFilter !== '') {
            $activitiesQuery->where('action', $actionFilter);
        }

        if ($search !== '') {
            $activitiesQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $activities = $activitiesQuery->paginate(15)->withQueryString();
        $uniqueActions = ActivityLog::query()
            ->when($viewOwnOnly, fn ($query) => $query->where('user_id', auth()->id()))
            ->distinct()
            ->pluck('action')
            ->filter()
            ->sort()
            ->values();
        $filteredTotal = (clone $activitiesQuery)->count();

        return view('pages.activity_logs.activityLogs', compact(
            'activities',
            'allActivities',
            'overviewActions',
            'overviewUsers',
            'overviewSearch',
            'overviewAction',
            'overviewUserId',
            'todayActivities',
            'weeklyActivities',
            'monthlyActivities',
            'period',
            'actionFilter',
            'search',
            'uniqueActions',
            'filteredTotal'
        ));
    }

    /**
     * Mark every navbar notification as read for the current user by
     * stamping their read marker. Read-only safe: it only touches the
     * user's own preference, so Viewers may use it too.
     */
    public function markAllAsRead(Request $request)
    {
        $readId = ActivityLog::max('id') ?? 0;
        $user = $request->user();
        $user->update(['notifications_read_id' => $readId]);

        if ($request->wantsJson()) {
            $unreadQuery = ActivityLog::query()
                ->whereIn('action', ActivityLog::NOTIFICATION_ACTIONS)
                ->where('id', '>', $readId);
            if (! in_array($user->role_name, ['Admin', 'Super Admin'], true)) {
                $unreadQuery->where('user_id', $user->id);
            }

            return response()->json([
                'success' => true,
                'unread_count' => $unreadQuery->count(),
            ]);
        }

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    public function notificationState(Request $request)
    {
        $user = $request->user();
        $query = ActivityLog::query()->whereIn('action', ActivityLog::NOTIFICATION_ACTIONS);
        if (! in_array($user->role_name, ['Admin', 'Super Admin'], true)) {
            $query->where('user_id', $user->id);
        }

        $latestId = (clone $query)->max('id') ?? 0;
        $readId = $user->notifications_read_id;
        $unreadCount = (clone $query)
            ->when($readId, fn ($items) => $items->where('id', '>', $readId))
            ->count();

        return response()->json([
            'latest_id' => $latestId,
            'unread_count' => $unreadCount,
        ])->header('Cache-Control', 'no-store');
    }
}
