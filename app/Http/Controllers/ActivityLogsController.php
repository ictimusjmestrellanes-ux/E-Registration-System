<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class ActivityLogsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $activityQuery = ActivityLog::with('user')->latest();

        $activities = (clone $activityQuery)->paginate(12);
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

        return view('pages.activityLogs', compact(
            'activities',
            'todayActivities',
            'weeklyActivities',
            'monthlyActivities'
        ));
    }
}
