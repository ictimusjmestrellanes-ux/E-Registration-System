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

        $transactionTrend = Cache::remember(
            'dashboard.transaction_trend',
            300,
            fn () => $this->buildTransactionTrendAll()
        );

        // Optional per-category / per-type filters for the Total Transactions
        // graph (?tx_category=…&tx_type=…). Both compose: data is sliced from
        // one cached month x category x type grid so every option stays instant.
        $txCategoryOptions = $this->txCategoryOptions();
        $txTypeOptions = $this->txTypeOptions();

        $txCategory = trim((string) $request->query('tx_category', ''));
        if ($txCategory !== '' && ! in_array($txCategory, $txCategoryOptions, true)) {
            $txCategory = '';
        }

        $txType = trim((string) $request->query('tx_type', ''));
        if ($txType !== '' && ! in_array($txType, $txTypeOptions, true)) {
            $txType = '';
        }

        if ($txCategory !== '' || $txType !== '') {
            $trendGrid = $this->transactionTrendGrid();
            $transactionTrend = [
                'labels' => $trendGrid['labels'],
                'data' => $this->sliceTrendGrid(
                    $trendGrid['grid'],
                    $trendGrid['months'],
                    $txCategory !== '' ? $txCategory : null,
                    $txType !== '' ? $txType : null
                ),
            ];
        }

        $txTrendSuffix = implode(' · ', array_filter([$txCategory, $txType]));

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

        return view('pages.dashboard', compact('totalClients', 'totalTransactions', 'txCategoryOptions', 'txCategory', 'txTypeOptions', 'txType', 'txTrendSuffix', 'categoryCounts', 'categories', 'clientTrend', 'transactionTrend', 'caravanTrend'));
    }

    /**
     * JSON feed for the Total Transactions graph so the category/type filters
     * refresh only the chart (no page reload).
     */
    public function transactionTrend(Request $request)
    {
        $txCategory = trim((string) $request->query('tx_category', ''));
        if ($txCategory !== '' && ! in_array($txCategory, $this->txCategoryOptions(), true)) {
            $txCategory = '';
        }

        $txType = trim((string) $request->query('tx_type', ''));
        if ($txType !== '' && ! in_array($txType, $this->txTypeOptions(), true)) {
            $txType = '';
        }

        if ($txCategory === '' && $txType === '') {
            $trend = Cache::remember(
                'dashboard.transaction_trend',
                300,
                fn () => $this->buildTransactionTrendAll()
            );
        } else {
            $grid = $this->transactionTrendGrid();
            $trend = [
                'labels' => $grid['labels'],
                'data' => $this->sliceTrendGrid(
                    $grid['grid'],
                    $grid['months'],
                    $txCategory !== '' ? $txCategory : null,
                    $txType !== '' ? $txType : null
                ),
            ];
        }

        return response()->json([
            'success' => true,
            'category' => $txCategory,
            'type' => $txType,
            'labels' => $trend['labels'],
            'data' => $trend['data'],
        ]);
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

    private function buildTransactionTrendAll(): array
    {
        $start = Carbon::create(2026, 1, 1)->startOfMonth();

        $rows = TransactionHistory::query()
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month, count(*) as total")
            ->where('transaction_date', '>=', $start)
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

    /**
     * Sum one month x category x type grid down to a monthly series,
     * honouring whichever of the two filters is active (null = all).
     */
    private function sliceTrendGrid(array $grid, array $months, ?string $category, ?string $type): array
    {
        $data = [];
        foreach ($months as $mk) {
            $sum = 0;
            foreach ($grid[$mk] ?? [] as $cat => $types) {
                if ($category !== null && $cat !== $category) {
                    continue;
                }
                foreach ($types as $t => $n) {
                    if ($type === null || $t === $type) {
                        $sum += $n;
                    }
                }
            }
            $data[] = $sum;
        }

        return $data;
    }
}
