<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $clientTrend = Cache::remember('dashboard.client_trend', 300, function () {
            $start = Carbon::create(2026, 1, 1)->startOfMonth();

            $rows = Client::query()
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, count(*) as total")
                ->where('created_at', '>=', $start)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $labels = [];
            $data = [];
            $cursor = $start->copy();
            while ($cursor->lte(now())) {
                $key = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $data[] = $rows[$key] ?? 0;
                $cursor->addMonth();
            }

            return ['labels' => $labels, 'data' => $data];
        });

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

        $caravanTrend = Cache::remember('dashboard.caravan_trend', 300, function () {
            $start = Carbon::create(2026, 1, 1)->startOfMonth();

            $rows = TransactionEvent::query()
                ->selectRaw("DATE_FORMAT(event_date, '%Y-%m') as month, count(*) as total")
                ->where('transaction_category', 'CARAVAN')
                ->whereNotNull('transferred_at')
                ->whereNotNull('event_date')
                ->where('event_date', '>=', $start)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $labels = [];
            $data = [];
            $cursor = $start->copy();
            while ($cursor->lte(now())) {
                $key = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $data[] = $rows[$key] ?? 0;
                $cursor->addMonth();
            }

            return ['labels' => $labels, 'data' => $data];
        });

        return view('pages.dashboard', compact('totalClients', 'totalTransactions', 'txCategoryOptions', 'txCategories', 'txTypeOptions', 'txTypes', 'txVisibleTypes', 'txTrendSuffix', 'categoryCounts', 'categories', 'clientTrend', 'transactionTrend', 'caravanTrend'));
    }

    /**
     * JSON feed for the Total Transactions graph so the category/type filters
     * refresh only the chart (no page reload).
     */
    public function transactionTrend(Request $request)
    {
        $txCategories = $this->txMultiFilter($request, 'tx_category', $this->txCategoryOptions());
        $txTypes = $this->txMultiFilter($request, 'tx_type', $this->txTypeOptions());

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

        return Cache::remember(
            'dashboard.tx_types_for_categories.'.md5(json_encode($categories)),
            300,
            function () use ($categories) {
                $query = TransactionHistory::query()
                    ->whereNotNull('events_transaction_type')
                    ->where('events_transaction_type', '<>', '');

                if ($categories !== []) {
                    $query->whereIn('category', $categories);
                }

                return $query->distinct()
                    ->orderBy('events_transaction_type')
                    ->pluck('events_transaction_type')
                    ->all();
            }
        );
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
            return TransactionHistory::query()
                ->whereNotNull('events_transaction_type')
                ->where('events_transaction_type', '<>', '')
                ->distinct()
                ->orderBy('events_transaction_type')
                ->pluck('events_transaction_type')
                ->all();
        });
    }

    /**
     * Stacked monthly series for the Total Transactions graph, sliced from
     * the cached month x category x type grid (no extra queries).
     * Empty arrays mean "all". The stack dimension is whichever filter is
     * NOT pinned to a single value: no (single) filter stacks by category,
     * one pinned category stacks by type, and a single category + single
     * type collapse to one series.
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
     * Split one month x category x type grid into per-segment monthly
     * series honouring the active filters (null/empty = all).
     */
    private function stackTrendGrid(array $grid, array $months, ?array $categories, ?array $types): array
    {
        $catSet = empty($categories) ? null : array_flip($categories);
        $typeSet = empty($types) ? null : array_flip($types);

        // Stack by type only when exactly one category is pinned (and type is not).
        $byType = $catSet !== null && count($catSet) === 1
            && ($typeSet === null || count($typeSet) !== 1);
        $segments = [];

        foreach ($months as $mk) {
            foreach ($grid[$mk] ?? [] as $cat => $types) {
                if ($catSet !== null && ! isset($catSet[$cat])) {
                    continue;
                }
                foreach ($types as $t => $n) {
                    if ($typeSet !== null && ! isset($typeSet[$t])) {
                        continue;
                    }
                    $key = $byType ? $t : $cat;
                    $segments[$key] = true;
                }
            }
        }

        $keys = array_keys($segments);
        sort($keys);
        // Blank values stay complete in the totals; show them last.
        $keys = array_values(array_filter($keys, fn ($k) => $k !== ''));
        if (isset($segments[''])) {
            $keys[] = '';
        }

        $datasets = [];
        foreach ($keys as $key) {
            $data = [];
            foreach ($months as $mk) {
                $sum = 0;
                foreach ($grid[$mk] ?? [] as $cat => $types) {
                    if ($catSet !== null && ! isset($catSet[$cat])) {
                        continue;
                    }
                    foreach ($types as $t => $n) {
                        if ($typeSet !== null && ! isset($typeSet[$t])) {
                            continue;
                        }
                        if (($byType ? $t : $cat) === $key) {
                            $sum += $n;
                        }
                    }
                }
                $data[] = $sum;
            }
            $datasets[] = [
                'label' => $key !== '' ? $key : 'Unspecified',
                'data' => $data,
            ];
        }

        // Single category + single type: collapse to one labelled series so
        // the bar keeps its title instead of rendering an empty legend.
        if ($catSet !== null && $typeSet !== null
            && count($catSet) === 1 && count($typeSet) === 1
            && count($datasets) <= 1) {
            $total = $datasets[0]['data'] ?? array_fill(0, count($months), 0);
            $datasets = [[
                'label' => array_key_first($catSet).' · '.array_key_first($typeSet),
                'data' => $total,
            ]];
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
            $start = Carbon::create(2026, 1, 1)->startOfMonth();

            $rows = TransactionHistory::query()
                ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month, COALESCE(category, '') as category, COALESCE(events_transaction_type, '') as ttype, count(*) as total")
                ->where('transaction_date', '>=', $start)
                ->groupBy('month', 'category', 'ttype')
                ->get();

            $months = [];
            $cursor = $start->copy();
            while ($cursor->lte(now())) {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            $grid = [];
            foreach ($rows as $row) {
                $grid[$row->month][$row->category][$row->ttype] =
                    ($grid[$row->month][$row->category][$row->ttype] ?? 0) + (int) $row->total;
            }

            $labels = array_map(
                fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('M Y'),
                $months
            );

            return ['labels' => $labels, 'months' => $months, 'grid' => $grid];
        });
    }
}
