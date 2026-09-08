<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function profile()
    {
        return view('pages.client_profile.settings');
    }

    public function dashboard(Request $request)
    {
        $transactionDateOptions = $this->transactionDateOptions();
        $dateFilters = $request->validate([
            'transaction_date_types' => ['nullable', 'array'],
            'transaction_date_types.*' => ['required', 'string', \Illuminate\Validation\Rule::in($this->txTypeOptions())],
            'transaction_date_categories' => ['nullable', 'array'],
            'transaction_date_categories.*' => ['required', 'string', \Illuminate\Validation\Rule::in($this->txCategoryOptions())],
            'transaction_dates' => ['nullable', 'array'],
            'transaction_dates.*' => ['required', 'date_format:Y-m-d', \Illuminate\Validation\Rule::in($transactionDateOptions)],
            'transaction_date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'transaction_date_to' => array_merge(
                ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                $request->filled('transaction_date_from') ? ['after_or_equal:transaction_date_from'] : []
            ),
        ]);
        $transactionDateFrom = $dateFilters['transaction_date_from'] ?? null;
        $transactionDateTo = $dateFilters['transaction_date_to'] ?? null;
        $transactionDateCategories = array_values(array_unique($dateFilters['transaction_date_categories'] ?? []));
        $transactionDateTypes = array_values(array_unique($dateFilters['transaction_date_types'] ?? []));
        $transactionDates = array_values(array_unique($dateFilters['transaction_dates'] ?? []));
        $cascade = $this->transactionDateCascade($transactionDateCategories, $transactionDateTypes, $transactionDates);
        $transactionDateTypes = $cascade['types'];
        $transactionDates = $cascade['dates'];
        $transactionDateTypeOptions = $cascade['type_options'];
        $transactionDateVisibleDates = $cascade['date_options'];
        sort($transactionDates);
        if ($transactionDates !== []) {
            $transactionDateFrom = $transactionDates[0];
            $transactionDateTo = $transactionDates[count($transactionDates) - 1];
        }

        $totalClients = Cache::remember('dashboard.total_clients', 300, function () {
            return Client::count();
        });

        // True total of all transaction rows. (The per-category breakdown
        // below only covers rows with a recognized non-empty category, so it
        // must not be used as the headline total.)
        $totalTransactions = Cache::remember('dashboard.total_transactions', 300, function () {
            return TransactionHistory::count();
        });

        $categoryCounts = Cache::remember('dashboard.category_counts', 300, function () {
            $counts = array_fill_keys(array_keys(TransactionHistory::CATEGORIES), 0);

            $rows = TransactionHistory::query()
                ->selectRaw('category, count(*) as total')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->groupBy('category')
                ->get();

            foreach ($rows as $row) {
                $key = TransactionHistory::normalizeCategory($row->category);
                if ($key !== null && array_key_exists($key, $counts)) {
                    $counts[$key] += (int) $row->total;
                }
            }

            return $counts;
        });

        $categories = TransactionHistory::CATEGORIES;

        $clientCategoryDistribution = Cache::remember(
            'dashboard.client_category_counts',
            300,
            fn () => $this->clientCategoryDistribution(10)
        );

        $clientTrend = Cache::remember(
            'dashboard.client_trend',
            300,
            fn () => $this->monthlyTrend(Client::query(), 'created_at', true)
        );

        // Optional multi-select category/type filters for the Total
        // Transactions graph (?tx_category[]=…&tx_type[]=…). All combos slice
        // one cached month x category x type grid so every option stays instant.
        $txCategoryOptions = $this->txCategoryOptions();
        $txTypeOptions = $this->txTypeOptions();

        $txCategories = $this->txMultiFilter($request, 'tx_category', $txCategoryOptions);
        $txTypes = $this->txMultiFilter($request, 'tx_type', $txTypeOptions);

        // Cascading dropdowns: the Type menu lists only types that occur in
        // the selected categories (all types when nothing is picked).
        $txVisibleTypes = $txCategories === []
            ? $txTypeOptions
            : $this->txTypesForCategories($txCategories);
        // Drop pre-selected types that the current categories hide so the
        // menu, chart, and title always agree (the URL self-heals on reload).
        $txTypes = array_values(array_intersect($txTypes, $txVisibleTypes));

        if ($txCategories === [] && $txTypes === []) {
            $transactionTrend = Cache::remember(
                'dashboard.transaction_trend_stacked',
                300,
                fn () => $this->buildStackedTransactionTrend(null, null)
            );
        } else {
            $transactionTrend = $this->buildStackedTransactionTrend($txCategories, $txTypes);
        }

        $txTrendSuffix = implode(' · ', array_filter([implode(', ', $txCategories), implode(', ', $txTypes)]));

        $transactionDateTrend = $this->transactionDateTotals($transactionDateFrom, $transactionDateTo, $transactionDates, $transactionDateCategories, $transactionDateTypes);

        $recentActivities = $this->recentDashboardActivities();

        $caravanTrend = Cache::remember(
            'dashboard.caravan_trend',
            300,
            fn () => $this->monthlyTrend(
                TransactionEvent::query()
                    ->where('transaction_category', 'CARAVAN')
                    ->whereNotNull('transferred_at'),
                'event_date'
            )
        );

        return view('pages.dashboard', compact('totalClients', 'totalTransactions', 'txCategoryOptions', 'txCategories', 'txTypeOptions', 'txTypes', 'txVisibleTypes', 'txTrendSuffix', 'categoryCounts', 'categories', 'clientTrend', 'transactionTrend', 'transactionDateTrend', 'transactionDateFrom', 'transactionDateTo', 'transactionDates', 'transactionDateOptions', 'transactionDateCategories', 'transactionDateTypes', 'transactionDateTypeOptions', 'transactionDateVisibleDates', 'caravanTrend', 'recentActivities', 'clientCategoryDistribution'));
    }

    public function transactionDateTrend(Request $request)
    {
        $validated = $request->validate([
            'transaction_date_types' => ['nullable', 'array'],
            'transaction_date_types.*' => ['required', 'string', \Illuminate\Validation\Rule::in($this->txTypeOptions())],
            'transaction_date_categories' => ['nullable', 'array'],
            'transaction_date_categories.*' => ['required', 'string', \Illuminate\Validation\Rule::in($this->txCategoryOptions())],
            'transaction_dates' => ['nullable', 'array'],
            'transaction_dates.*' => ['required', 'date_format:Y-m-d', \Illuminate\Validation\Rule::in($this->transactionDateOptions())],
        ]);
        $dates = array_values(array_unique($validated['transaction_dates'] ?? []));
        sort($dates);
        $categories = array_values(array_unique($validated['transaction_date_categories'] ?? []));
        $types = array_values(array_unique($validated['transaction_date_types'] ?? []));
        $cascade = $this->transactionDateCascade($categories, $types, $dates);
        $types = $cascade['types'];
        $dates = $cascade['dates'];
        $trend = $this->transactionDateTotals($dates[0] ?? null, $dates === [] ? null : $dates[count($dates) - 1], $dates, $categories, $types);

        return response()->json($trend + [
            'dates' => $dates,
            'categories' => $categories,
            'types' => $types,
            'type_options' => $cascade['type_options'],
            'date_options' => $cascade['date_options'],
            'total' => array_sum($trend['data']),
            'description' => $dates === []
                ? 'Monthly totals from Transaction History through '.now()->format('F Y')
                : 'Monthly totals for '.count($dates).' selected '.(count($dates) === 1 ? 'date' : 'dates'),
        ]);
    }

    private function transactionDateCascade(array $categories, array $types, array $dates): array
    {
        $typeOptions = $this->txTypesForCategories($categories);
        $types = array_values(array_intersect($types, $typeOptions));
        $dateOptions = $this->transactionDateOptions($categories, $types);
        $dates = array_values(array_intersect($dates, $dateOptions));
        sort($dates);

        return ['types' => $types, 'dates' => $dates, 'type_options' => $typeOptions, 'date_options' => $dateOptions];
    }

    private function transactionDateOptions(array $categories = [], array $types = []): array
    {
        return TransactionHistory::query()
            ->when($categories !== [], fn ($query) => $query->whereIn('category', $categories))
            ->when($types !== [], fn ($query) => $query->whereIn(DB::raw($this->transactionTypeExpression()), $types))
            ->whereNotNull('transaction_date')
            ->selectRaw($this->transactionDayExpression().' as transaction_day')
            ->distinct()->orderByDesc('transaction_day')
            ->toBase()->pluck('transaction_day')->all();
    }

    private function transactionDayExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql', 'sqlsrv' => 'CAST(transaction_date AS DATE)',
            default => 'DATE(transaction_date)',
        };
    }

    private function transactionDateTotals(?string $from, ?string $to, array $dates = [], array $categories = [], array $types = []): array
    {
        if ($from === null && $to === null) {
            $dateGrid = $this->transactionTrendGrid();

            return [
                'labels' => $dateGrid['labels'],
                'data' => array_map(
                    fn ($month) => array_sum(array_map(
                        fn ($typeCounts) => array_sum($types === [] ? $typeCounts : array_intersect_key($typeCounts, array_flip($types))),
                        $categories === []
                        ? ($dateGrid['grid'][$month] ?? [])
                        : array_intersect_key($dateGrid['grid'][$month] ?? [], array_flip($categories)))),
                    $dateGrid['months']
                ),
            ];
        }

        // Apply exact day boundaries before grouping; monthly caches cannot
        // distinguish transactions inside and outside a partial month.
        $end = $to ? Carbon::createFromFormat('!Y-m-d', $to) : now();
        $rows = TransactionHistory::query()
            ->when($types !== [], fn ($query) => $query->whereIn(DB::raw($this->transactionTypeExpression()), $types))
            ->when($categories !== [], fn ($query) => $query->whereIn('category', $categories))
            ->when($dates !== [], fn ($query) => $query->whereIn(DB::raw($this->transactionDayExpression()), $dates))
            ->when($from !== null, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->selectRaw($this->monthExpression('transaction_date').' as month, count(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');
        $firstMonth = $rows->keys()->first();
        $start = $from
            ? Carbon::createFromFormat('!Y-m-d', $from)->startOfMonth()
            : ($firstMonth ? Carbon::createFromFormat('!Y-m', $firstMonth) : $end->copy()->startOfMonth());

        $labels = [];
        $data = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $labels[] = $cursor->format('M Y');
            $data[] = (int) ($rows[$cursor->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * JSON feed for the Total Transactions graph so the category/type filters
     * refresh only the chart (no page reload).
     */
    public function transactionTrend(Request $request)
    {
        $txCategories = $this->txMultiFilter($request, 'tx_category', $this->txCategoryOptions());
        $txTypes = $this->txMultiFilter($request, 'tx_type', $this->txTypeOptions());

        if ($txCategories !== []) {
            $txTypes = array_values(array_intersect($txTypes, $this->txTypesForCategories($txCategories)));
        }

        if ($txCategories === [] && $txTypes === []) {
            $trend = Cache::remember(
                'dashboard.transaction_trend_stacked',
                300,
                fn () => $this->buildStackedTransactionTrend(null, null)
            );
        } else {
            $trend = $this->buildStackedTransactionTrend($txCategories, $txTypes);
        }

        return response()->json([
            'success' => true,
            'categories' => $txCategories,
            'types' => $txTypes,
            'labels' => $trend['labels'],
            'datasets' => $trend['datasets'],
        ]);
    }

    /**
     * Transaction share per client category for the dashboard doughnut.
     * Largest slices first, capped at $top entries with the long tail
     * folded into an "Others" slice. Plain group-by so it stays
     * SQLite-safe.
     *
     * @return array{labels: array, data: array}
     */
    public function clientCategoryDistribution(int $top = PHP_INT_MAX): array
    {
        $counts = TransactionHistory::query()
            ->selectRaw('client_category, count(*) as total')
            ->whereNotNull('client_category')
            ->where('client_category', '<>', '')
            ->groupBy('client_category')
            ->orderByDesc('total')
            ->orderBy('client_category')
            ->pluck('total', 'client_category')
            ->all();

        $labels = array_keys($counts);
        $data = array_values(array_map('intval', $counts));

        if ($top < count($labels)) {
            $rest = array_sum(array_slice($data, $top));
            $labels = array_slice($labels, 0, $top);
            $data = array_slice($data, 0, $top);
            if ($rest > 0) {
                $labels[] = 'Others';
                $data[] = $rest;
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Latest activity entries for the dashboard sidebar widget.
     * Mirrors the Activity Logs page visibility (non-admins see only
     * their own) and stays hidden without the Activity Logs feature.
     *
     * @return \Illuminate\Support\Collection
     */
    public function recentDashboardActivities(int $limit = 6)
    {
        if (! feature_allowed('Activity Logs')) {
            return collect();
        }

        $viewOwnOnly = ! in_array(auth()->user()->role_name, ['Admin', 'Super Admin']);

        return ActivityLog::with('user')->latest()
            ->when($viewOwnOnly, fn ($query) => $query->where('user_id', auth()->id()))
            ->take($limit)
            ->get();
    }

    /**
     * Distinct transaction types occurring in the given categories
     * (all types when the list is empty). Backs the cascading Type menu.
     */
    public function transactionTrendTypes(Request $request)
    {
        $txCategories = $this->txMultiFilter($request, 'tx_category', $this->txCategoryOptions());

        return response()->json([
            'success' => true,
            'categories' => $txCategories,
            'types' => $txCategories === [] ? $this->txTypeOptions() : $this->txTypesForCategories($txCategories),
        ]);
    }

    private function txTypesForCategories(array $categories): array
    {
        sort($categories);

        // This is intentionally queried live. Its cache key used to be based
        // on a category hash, which could not be cleared when imports or
        // transfers introduced a new type.
        $typeExpression = $this->transactionTypeExpression();
        $query = TransactionHistory::query()
            ->selectRaw("{$typeExpression} as transaction_type")
            ->whereRaw("{$typeExpression} <> ''");

        if ($categories !== []) {
            $query->whereIn('category', $categories);
        }

        return $query->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type')
            ->all();
    }

    /**
     * Parse a multi-select filter: accepts repeated params
     * (?tx_category[]=A&tx_category[]=B), a single value, or a
     * comma-separated list. Unknown values are dropped; empty = all.
     */
    private function txMultiFilter(Request $request, string $key, array $options): array
    {
        $raw = $request->query($key, []);
        $values = is_array($raw) ? $raw : [$raw];

        $filtered = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part !== '' && in_array($part, $options, true)) {
                    $filtered[] = $part;
                }
            }
        }

        return array_values(array_unique($filtered));
    }

    private function txCategoryOptions(): array
    {
        return Cache::remember('dashboard.tx_category_options', 300, function () {
            return TransactionHistory::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->all();
        });
    }

    private function txTypeOptions(): array
    {
        return Cache::remember('dashboard.tx_type_options', 300, function () {
            $typeExpression = $this->transactionTypeExpression();

            return TransactionHistory::query()
                ->selectRaw("{$typeExpression} as transaction_type")
                ->whereRaw("{$typeExpression} <> ''")
                ->distinct()
                ->orderBy('transaction_type')
                ->pluck('transaction_type')
                ->all();
        });
    }

    /**
     * Prefer the event-specific type when present, while retaining manually
     * entered transactions that only populated the legacy `type` column.
     */
    private function transactionTypeExpression(): string
    {
        return "COALESCE(NULLIF(events_transaction_type, ''), COALESCE(type, ''))";
    }

    /**
     * Database-specific expression that buckets a date/datetime as YYYY-MM.
     * The production database is MySQL while feature tests use SQLite.
     */
    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlsrv' => "FORMAT({$column}, 'yyyy-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Build a complete monthly series from the first recorded month through
     * the current month, including zero-count months in between.
     *
     * @return array{labels: array, data: array}
     */
    private function monthlyTrend($query, string $dateColumn, bool $includePreviousMonth = false): array
    {
        $monthExpression = $this->monthExpression($dateColumn);
        $rows = $query
            ->whereNotNull($dateColumn)
            ->selectRaw("{$monthExpression} as month, count(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($total) => (int) $total)
            ->all();

        $end = now()->startOfMonth();
        $firstMonth = array_key_first($rows);
        $start = $firstMonth === null
            ? $end->copy()
            : Carbon::createFromFormat('!Y-m', $firstMonth)->startOfMonth();

        // Ignore accidentally future-dated records until their month arrives.
        if ($start->gt($end)) {
            $start = $end->copy();
        }

        if ($includePreviousMonth && $start->gt($end->copy()->subMonth())) {
            $start = $end->copy()->subMonth();
        }

        $labels = [];
        $data = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $data[] = $rows[$key] ?? 0;
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Grouped-stacked monthly series for the Total Transactions graph,
     * sliced from the cached month x category x type grid (no extra
     * queries). Empty arrays mean "all". Each category in scope renders a
     * side-by-side cluster segmented by type; one category (+ optionally one
     * type) collapses to the familiar single stacked bar.
     *
     * @return array{labels: array, datasets: array}
     */
    private function buildStackedTransactionTrend(?array $categories, ?array $types): array
    {
        $grid = $this->transactionTrendGrid();

        return [
            'labels' => $grid['labels'],
            'datasets' => $this->stackTrendGrid($grid['grid'], $grid['months'], $categories, $types),
        ];
    }

    /**
     * Split one month x category x type grid into grouped-stacked monthly
     * series honouring the active filters (null/empty = all). Each category
     * in scope becomes a side-by-side cluster (dataset "stack") segmented
     * by type, so colors always encode the transaction type. Combos without
     * any data are omitted entirely (no bars, no legend entries).
     */
    private function stackTrendGrid(array $grid, array $months, ?array $categories, ?array $types): array
    {
        $catSet = empty($categories) ? null : array_flip($categories);
        $typeSet = empty($types) ? null : array_flip($types);

        // (category, type) pairs holding data in at least one month.
        $pairs = [];
        foreach ($months as $mk) {
            foreach ($grid[$mk] ?? [] as $cat => $typeCounts) {
                if ($catSet !== null && ! isset($catSet[$cat])) {
                    continue;
                }
                foreach ($typeCounts as $t => $n) {
                    if ($typeSet !== null && ! isset($typeSet[$t])) {
                        continue;
                    }
                    if ($n > 0) {
                        $pairs[$cat][$t] = true;
                    }
                }
            }
        }

        $cats = array_keys($pairs);
        sort($cats);
        // Blank values stay complete in the totals; show them last.
        $cats = array_values(array_filter($cats, fn ($k) => $k !== ''));
        if (isset($pairs[''])) {
            $cats[] = '';
        }

        // Stable per-type colors following the sorted type list.
        $typeOrder = $this->txTypeOptions();
        $colorIndex = [];
        foreach ($typeOrder as $i => $t) {
            $colorIndex[$t] = $i;
        }
        $blankColor = count($typeOrder);

        $labelFor = fn ($v) => $v !== '' ? $v : 'Unspecified';
        $datasets = [];
        foreach ($cats as $cat) {
            $typeKeys = array_keys($pairs[$cat]);
            sort($typeKeys);
            $typeKeys = array_values(array_filter($typeKeys, fn ($k) => $k !== ''));
            if (isset($pairs[$cat][''])) {
                $typeKeys[] = '';
            }
            foreach ($typeKeys as $t) {
                $data = [];
                foreach ($months as $mk) {
                    $data[] = $grid[$mk][$cat][$t] ?? 0;
                }
                $datasets[] = [
                    'label' => count($cats) === 1 ? $labelFor($t) : $labelFor($cat).' · '.$labelFor($t),
                    'stack' => $labelFor($cat),
                    'colorIndex' => $colorIndex[$t] ?? $blankColor,
                    'data' => $data,
                ];
            }
        }

        // No overlap at all (e.g. one category + one foreign type): keep a
        // single labelled zero series instead of an empty plot.
        if ($datasets === []) {
            $suffix = implode(' · ', array_filter([
                $catSet !== null ? implode(', ', array_keys($catSet)) : '',
                $typeSet !== null ? implode(', ', array_keys($typeSet)) : '',
            ]));
            $datasets[] = [
                'label' => $suffix !== '' ? $suffix : 'Transactions',
                'stack' => 'All',
                'colorIndex' => 0,
                'data' => array_fill(0, count($months), 0),
            ];
        }

        return $datasets;
    }

    /**
     * Cached month x category x type grid backing both graph filters.
     * Blank categories/types are kept under '' so single-dimension slices
     * stay complete; dropdown options list only non-empty values.
     *
     * @return array{labels: array, months: array, grid: array}
     */
    private function transactionTrendGrid(): array
    {
        return Cache::remember('dashboard.transaction_trend_grid', 300, function () {
            $monthExpression = $this->monthExpression('transaction_date');
            $typeExpression = $this->transactionTypeExpression();

            $rows = TransactionHistory::query()
                ->whereNotNull('transaction_date')
                ->selectRaw("{$monthExpression} as month, COALESCE(category, '') as category, {$typeExpression} as ttype, count(*) as total")
                ->groupBy('month', 'category', 'ttype')
                ->orderBy('month')
                ->get();

            $end = now()->startOfMonth();
            $start = $end->copy()->subMonth();
            $firstMonth = $rows->first()?->month;
            if ($firstMonth !== null) {
                $firstRecordedMonth = Carbon::createFromFormat('!Y-m', $firstMonth);
                if ($firstRecordedMonth->lt($start)) {
                    $start = $firstRecordedMonth;
                }
            }

            $months = [];
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            $grid = [];
            foreach ($rows as $row) {
                $grid[$row->month][$row->category][$row->ttype] =
                    ($grid[$row->month][$row->category][$row->ttype] ?? 0) + (int) $row->total;
            }

            $labels = array_map(
                fn ($m) => Carbon::createFromFormat('!Y-m', $m)->format('M Y'),
                $months
            );

            return ['labels' => $labels, 'months' => $months, 'grid' => $grid];
        });
    }
}
