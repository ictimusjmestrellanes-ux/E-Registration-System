<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Carbon\Carbon;
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

    public function dashboard()
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

        $transactionTrend = Cache::remember('dashboard.transaction_trend', 300, function () {
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
        });

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

        return view('pages.dashboard', compact('totalClients', 'totalTransactions', 'categoryCounts', 'categories', 'clientTrend', 'transactionTrend', 'caravanTrend'));
    }
}
