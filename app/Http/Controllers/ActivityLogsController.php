<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityLogsController extends Controller
{
    private const HIDDEN_ACTIONS = ['transaction_history_updated'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $timezone = 'Asia/Manila';
        $manilaNow = now($timezone);
        $viewOwnOnly = !in_array(auth()->user()->role_name, ['Admin', 'Super Admin']);

        $activityRelations = [
            'user',
            'subjectClient',
            'subjectTransactionHistory.client',
            'subjectTransactionEvent.transferredTransaction.client',
        ];

        $baseQuery = ActivityLog::with($activityRelations)
            ->where('user_id', auth()->id())
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
            ->latest()
            ->orderByDesc('id');

        $overviewActions = (clone $baseQuery)
            ->reorder()
            ->distinct()
            ->pluck('action')
            ->filter()
            ->sort()
            ->values();
        $overviewSearch = trim((string) $request->input('overview_search', ''));
        $overviewAction = (string) $request->input('overview_action', '');

        if ($overviewAction !== '') {
            $baseQuery->where('action', $overviewAction);
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

        $activitiesQuery = ActivityLog::with($activityRelations)
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
            ->latest()
            ->orderByDesc('id');
        if ($viewOwnOnly) {
            $activitiesQuery->where('user_id', auth()->id());
        }

        $period = $request->input('period', 'all');
        // ConvertEmptyStringsToNull turns the "All Actions" option into null
        // on real web requests. Normalize it so it does not add
        // `WHERE action IS NULL` and hide every search result.
        $actionFilter = trim((string) $request->input('action', ''));
        $search = trim((string) $request->input('search', ''));

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
            $terms = preg_split('/[\s,]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];

            $activitiesQuery->where(function ($q) use ($search, $terms) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%")
                    // A full name may be split across several audit fields or
                    // stored in a different order, so require every term
                    // rather than relying only on one contiguous JSON string.
                    ->orWhere(function ($logQuery) use ($terms) {
                        foreach ($terms as $term) {
                            $like = "%{$term}%";
                            $logQuery->where(function ($termQuery) use ($like) {
                                $termQuery->where('description', 'like', $like)
                                    ->orWhere('properties', 'like', $like);
                            });
                        }
                    })
                    ->orWhere(function ($subjectQuery) use ($terms) {
                        $subjectQuery->where('subject_type', 'Client')
                            ->whereIn('subject_id', Client::query()
                                ->select('id')
                                ->where(function ($clientQuery) use ($terms) {
                                    $this->applyClientSearchTerms($clientQuery, $terms);
                                }));
                    })
                    ->orWhere(function ($subjectQuery) use ($terms) {
                        $subjectQuery->where('subject_type', 'TransactionHistory')
                            ->whereIn('subject_id', TransactionHistory::query()
                                ->select('id')
                                ->where(function ($historyQuery) use ($terms) {
                                    $this->applyTransactionSearchTerms($historyQuery, $terms);
                                }));
                    })
                    ->orWhere(function ($subjectQuery) use ($terms) {
                        $subjectQuery->where('subject_type', 'TransactionEvent')
                            ->whereIn('subject_id', TransactionEvent::query()
                                ->select('id')
                                ->where(function ($eventQuery) use ($terms) {
                                    foreach ($terms as $term) {
                                        $like = "%{$term}%";
                                        $eventQuery->where(function ($termQuery) use ($like) {
                                            $termQuery->where('full_name', 'like', $like)
                                                ->orWhereHas('transferredTransaction', function ($historyQuery) use ($like) {
                                                    $this->applyTransactionSearchTerm($historyQuery, $like);
                                                });
                                        });
                                    }
                                }));
                    });
            });
        }

        $activities = $activitiesQuery->paginate(15)->withQueryString();
        $uniqueActions = ActivityLog::query()
            ->when($viewOwnOnly, fn ($query) => $query->where('user_id', auth()->id()))
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
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
            'overviewSearch',
            'overviewAction',
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

    private function applyClientSearchTerms($query, array $terms): void
    {
        foreach ($terms as $term) {
            $like = "%{$term}%";
            $query->where(function ($termQuery) use ($like) {
                $this->applyClientSearchTerm($termQuery, $like);
            });
        }
    }

    private function applyClientSearchTerm($query, string $like): void
    {
        $query->where('client_id', 'like', $like)
            ->orWhere('first_name', 'like', $like)
            ->orWhere('middle_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('suffix', 'like', $like);
    }

    private function applyTransactionSearchTerms($query, array $terms): void
    {
        foreach ($terms as $term) {
            $like = "%{$term}%";
            $query->where(function ($termQuery) use ($like) {
                $this->applyTransactionSearchTerm($termQuery, $like);
            });
        }
    }

    private function applyTransactionSearchTerm($query, string $like): void
    {
        $query->where('client_id', 'like', $like)
            ->orWhereHas('client', function ($clientQuery) use ($like) {
                $this->applyClientSearchTerm($clientQuery, $like);
            });
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
        $notifications = (clone $query)
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->map(fn (ActivityLog $notification) => [
                'id' => $notification->id,
                'action' => $notification->action,
                'description' => Str::limit($notification->description, 110),
                'user_name' => $notification->user?->name ?? 'System',
                'time_ago' => $notification->created_at
                    ?->timezone('Asia/Manila')
                    ?->diffForHumans(),
            ]);

        return response()->json([
            'latest_id' => $latestId,
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ])->header('Cache-Control', 'no-store');
    }
}
