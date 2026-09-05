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

        // Optional per-category filter for the Total Transactions graph
        // (?tx_category=BIGAY BIGAS SA MASA). Series are sliced from one
        // cached month x category grid so every option stays instant.
        $txCategoryOptions = $this->txCategoryOptions();

        $txCategory = trim((string) $request->query('tx_category', ''));
        if ($txCategory !== '' && ! in_array($txCategory, $txCategoryOptions, true)) {
            $txCategory = '';
        }

        if ($txCategory !== '') {
            $trendByCategory = $this->transactionTrendGrid();

            if (isset($trendByCategory['series'][$txCategory])) {
                $transactionTrend = [
                    'labels' => $trendByCategory['labels'],
                    'data' => $trendByCategory['series'][$txCategory],
                ];
            }
        }

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

        return view('pages.dashboard', compact('totalClients', 'totalTransactions', 'txCategoryOptions', 'txCategory', 'categoryCounts', 'categories', 'clientTrend', 'transactionTrend', 'caravanTrend'));
    }

    /**
     * JSON feed for the Total Transactions graph so the category filter
     * refreshes only the chart (no page reload).
     */
    public function transactionTrend(Request $request)
    {
        $options = $this->txCategoryOptions();

        $txCategory = trim((string) $request->query('tx_category', ''));
        if ($txCategory !== '' && ! in_array($txCategory, $options, true)) {
            $txCategory = '';
        }

        if ($txCategory === '') {
            $trend = Cache::remember(
                'dashboard.transaction_trend',
                300,
                fn () => $this->buildTransactionTrendAll()
            );
        } else {
            $grid = $this->transactionTrendGrid();
            $trend = [
                'labels' => $grid['labels'],
                'data' => $grid['series'][$txCategory] ?? array_fill(0, count($grid['labels']), 0),
            ];
        }

        return response()->json([
            'success' => true,
            'category' => $txCategory,
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

    private function transactionTrendGrid(): array
    {
        return Cache::remember('dashboard.transaction_trend_by_category', 300, function () {
            $start = Carbon::create(2026, 1, 1)->startOfMonth();

            $rows = TransactionHistory::query()
                ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month, category, count(*) as total")
                ->where('transaction_date', '>=', $start)
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->groupBy('month', 'category')
                ->get();

            $months = [];
            $cursor = $start->copy();
            while ($cursor->lte(now())) {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            $grid = [];
            foreach ($rows as $row) {
                $grid[$row->category][$row->month] = (int) $row->total;
            }

            $labels = array_map(
                fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('M Y'),
                $months
            );
            $series = [];
            foreach ($grid as $cat => $byMonth) {
                $data = [];
                foreach ($months as $mk) {
                    $data[] = $byMonth[$mk] ?? 0;
                }
                $series[$cat] = $data;
            }

            return ['labels' => $labels, 'series' => $series];
        });
    }
}
