<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $liveLatestIds = $this->liveLatestIds($request);

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
            ->whereNotIn('action', self::HIDDEN_ACTIONS);
        if ($viewOwnOnly) {
            $activitiesQuery->where('user_id', auth()->id());
        }

        $normalizeMultiSelect = static function ($values): array {
            return collect((array) $values)
                ->flatMap(fn ($value) => explode(',', (string) $value))
                ->map(fn ($value) => trim($value))
                ->filter(fn ($value) => $value !== '')
                ->unique()
                ->values()
                ->all();
        };
        $normalizeDate = static function ($value): string {
            $value = trim((string) $value);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return '';
            }

            [$year, $month, $day] = array_map('intval', explode('-', $value));

            return checkdate($month, $day, $year) ? $value : '';
        };

        $userFilters = array_values(array_unique(array_map(
            'intval',
            array_filter(
                $normalizeMultiSelect($request->input('user', [])),
                fn ($value) => ctype_digit($value) && (int) $value > 0
            )
        )));
        $actionFilters = $normalizeMultiSelect($request->input('action', []));
        $dateFrom = $normalizeDate($request->input('date_from', ''));
        $dateTo = $normalizeDate($request->input('date_to', ''));
        $search = trim((string) $request->input('search', ''));
        $requestedSort = $request->input('sort');
        $requestedDirection = $request->input('direction');
        $sort = is_string($requestedSort)
            && in_array($requestedSort, ['date', 'user', 'action', 'description', 'client'], true)
            ? $requestedSort
            : 'date';
        $sortDirection = is_string($requestedDirection) && strtolower($requestedDirection) === 'asc'
            ? 'asc'
            : 'desc';

        if ($userFilters !== []) {
            $activitiesQuery->whereIn('user_id', $userFilters);
        }

        if ($actionFilters !== []) {
            $activitiesQuery->whereIn('action', $actionFilters);
        }

        if ($dateFrom !== '') {
            $activitiesQuery->where(
                'created_at',
                '>=',
                Carbon::createFromFormat('!Y-m-d', $dateFrom, $timezone)
            );
        }

        if ($dateTo !== '') {
            $activitiesQuery->where(
                'created_at',
                '<',
                Carbon::createFromFormat('!Y-m-d', $dateTo, $timezone)->addDay()
            );
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

        $filteredTotal = (clone $activitiesQuery)->count();
        $this->applyActivitiesSort($activitiesQuery, $sort, $sortDirection);
        $activities = $activitiesQuery->paginate(15)->withQueryString();
        $filterOptionsQuery = ActivityLog::query()
            ->when($viewOwnOnly, fn ($query) => $query->where('user_id', auth()->id()))
            ->whereNotIn('action', self::HIDDEN_ACTIONS);
        $uniqueActions = (clone $filterOptionsQuery)
            ->distinct()
            ->pluck('action')
            ->filter()
            ->sort()
            ->values();
        $filterableUsers = User::query()
            ->whereIn('id', (clone $filterOptionsQuery)
                ->select('user_id')
                ->whereNotNull('user_id')
                ->distinct())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        return view('pages.activity_logs.activityLogs', compact(
            'activities',
            'allActivities',
            'overviewActions',
            'overviewSearch',
            'overviewAction',
            'todayActivities',
            'weeklyActivities',
            'monthlyActivities',
            'userFilters',
            'actionFilters',
            'dateFrom',
            'dateTo',
            'search',
            'sort',
            'sortDirection',
            'uniqueActions',
            'filterableUsers',
            'filteredTotal',
            'liveLatestIds'
        ));
    }

    private function applyActivitiesSort($query, string $sort, string $direction): void
    {
        if ($sort === 'user') {
            $query->orderByRaw(
                "COALESCE((SELECT users.name FROM users WHERE users.id = activity_logs.user_id), 'System') {$direction}"
            );
        } elseif ($sort === 'action') {
            $query->orderBy('activity_logs.action', $direction);
        } elseif ($sort === 'description') {
            $query->orderBy('activity_logs.description', $direction);
        } elseif ($sort === 'client') {
            [$clientIdExpression, $clientNameExpression] = $this->activityClientSortExpressions();
            $query->orderByRaw("{$clientIdExpression} {$direction}")
                ->orderByRaw("{$clientNameExpression} {$direction}");
        } else {
            $query->orderBy('activity_logs.created_at', $direction);
        }

        $query->orderBy('activity_logs.id', $direction);
    }

    private function activityClientSortExpressions(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $clientName = static fn (string $alias): string => "trim(coalesce({$alias}.first_name, '') || ' ' || coalesce({$alias}.middle_name, '') || ' ' || coalesce({$alias}.last_name, '') || ' ' || coalesce({$alias}.suffix, ''))";
            $jsonValue = static fn (string $path): string => "json_extract(activity_logs.properties, '$.{$path}')";
            $jsonName = static fn (string $snapshot): string => "nullif(trim(coalesce(json_extract(activity_logs.properties, '$.{$snapshot}.first_name'), '') || ' ' || coalesce(json_extract(activity_logs.properties, '$.{$snapshot}.middle_name'), '') || ' ' || coalesce(json_extract(activity_logs.properties, '$.{$snapshot}.last_name'), '') || ' ' || coalesce(json_extract(activity_logs.properties, '$.{$snapshot}.suffix'), '')), '')";
        } else {
            $clientName = static fn (string $alias): string => "trim(concat_ws(' ', {$alias}.first_name, {$alias}.middle_name, {$alias}.last_name, {$alias}.suffix))";
            $jsonValue = static fn (string $path): string => "nullif(json_unquote(json_extract(activity_logs.properties, '$.{$path}')), 'null')";
            $jsonName = static fn (string $snapshot): string => "nullif(trim(concat_ws(' ', json_unquote(json_extract(activity_logs.properties, '$.{$snapshot}.first_name')), json_unquote(json_extract(activity_logs.properties, '$.{$snapshot}.middle_name')), json_unquote(json_extract(activity_logs.properties, '$.{$snapshot}.last_name')), json_unquote(json_extract(activity_logs.properties, '$.{$snapshot}.suffix')))), '')";
        }

        $subjectClientId = "(SELECT clients.client_id FROM clients WHERE activity_logs.subject_type = 'Client' AND clients.id = activity_logs.subject_id)";
        $historyClientId = "(SELECT transaction_history.client_id FROM transaction_history WHERE activity_logs.subject_type = 'TransactionHistory' AND transaction_history.id = activity_logs.subject_id)";
        $eventClientId = "(SELECT event_history.client_id FROM transaction_events AS event_subject LEFT JOIN transaction_history AS event_history ON event_history.id = event_subject.transferred_transaction_id WHERE activity_logs.subject_type = 'TransactionEvent' AND event_subject.id = activity_logs.subject_id)";

        $subjectClientName = "(SELECT nullif({$clientName('subject_client')}, '') FROM clients AS subject_client WHERE activity_logs.subject_type = 'Client' AND subject_client.id = activity_logs.subject_id)";
        $historyClientName = "(SELECT nullif({$clientName('history_client')}, '') FROM transaction_history AS history_subject LEFT JOIN clients AS history_client ON history_client.client_id = history_subject.client_id WHERE activity_logs.subject_type = 'TransactionHistory' AND history_subject.id = activity_logs.subject_id)";
        $eventClientName = "(SELECT coalesce(nullif({$clientName('event_client')}, ''), nullif(event_subject.full_name, '')) FROM transaction_events AS event_subject LEFT JOIN transaction_history AS event_history ON event_history.id = event_subject.transferred_transaction_id LEFT JOIN clients AS event_client ON event_client.client_id = event_history.client_id WHERE activity_logs.subject_type = 'TransactionEvent' AND event_subject.id = activity_logs.subject_id)";
        $actorName = "(SELECT users.name FROM users WHERE users.id = activity_logs.user_id)";

        return [
            'coalesce('.implode(', ', [
                $subjectClientId,
                $historyClientId,
                $eventClientId,
                $jsonValue('client_id'),
                $jsonValue('target_client_id'),
                $jsonValue('after.client_id'),
                $jsonValue('before.client_id'),
                "''",
            ]).')',
            'coalesce('.implode(', ', [
                $subjectClientName,
                $historyClientName,
                $eventClientName,
                $jsonValue('full_name'),
                $jsonValue('client_name'),
                $jsonValue('after.full_name'),
                $jsonValue('before.full_name'),
                $jsonName('after'),
                $jsonName('before'),
                $actorName,
                "''",
            ]).')',
        ];
    }

    public function liveState(Request $request)
    {
        return response()->json($this->liveLatestIds($request))
            ->header('Cache-Control', 'no-store');
    }

    private function liveLatestIds(Request $request): array
    {
        $visibleQuery = ActivityLog::query()->whereNotIn('action', self::HIDDEN_ACTIONS);
        if (!in_array($request->user()->role_name, ['Admin', 'Super Admin'], true)) {
            $visibleQuery->where('user_id', $request->user()->id);
        }

        return [
            'overview_latest_id' => (int) ActivityLog::query()
                ->where('user_id', $request->user()->id)
                ->whereNotIn('action', self::HIDDEN_ACTIONS)
                ->max('id'),
            'activities_latest_id' => (int) $visibleQuery->max('id'),
        ];
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
