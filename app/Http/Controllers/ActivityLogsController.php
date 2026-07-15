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
        $activityQuery = ActivityLog::with('user')->latest();

        $todayActivities = (clone $activityQuery)
            ->whereDate('created_at', now()->toDateString())
            ->take(8)
            ->get();
        $weeklyActivities = (clone $activityQuery)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->take(8)
            ->get();
        $monthlyActivities = (clone $activityQuery)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->take(8)
            ->get();

        $activitiesQuery = ActivityLog::with('user')->latest();

        $period = $request->input('period', 'all');
        $actionFilter = $request->input('action', '');
        $search = $request->input('search', '');

        if ($period !== 'all') {
            $now = now();
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

            if (isset($periodMap[$period])) {
                $activitiesQuery->where('created_at', '>=', $now->copy()->subDays($periodMap[$period]));
            } elseif ($period === 'this_month') {
                $activitiesQuery->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()]);
            } elseif ($period === 'this_week') {
                $activitiesQuery->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()]);
            } elseif ($period === 'today') {
                $activitiesQuery->whereDate('created_at', $now->toDateString());
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
        $uniqueActions = ActivityLog::distinct()->pluck('action')->filter()->sort()->values();
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
