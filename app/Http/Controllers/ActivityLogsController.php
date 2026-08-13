<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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

        $baseQuery = ActivityLog::with('user')->latest();
        if ($viewOwnOnly) {
            $baseQuery->where('user_id', auth()->id());
        }

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

        $activitiesQuery = ActivityLog::with('user')->latest();
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
}
