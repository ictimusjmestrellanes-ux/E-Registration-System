<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function profile()
    {
        return view('pages.client_profile.profile');
    }

    public function dashboard()
    {
        $totalClients = Cache::remember('dashboard.total_clients', 300, function () {
            return Client::count();
        });

        $categoryCounts = Cache::remember('dashboard.category_counts', 300, function () {
            return TransactionHistory::selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->toArray();
        });

        $categories = TransactionHistory::CATEGORIES;

        return view('pages.dashboard', compact('totalClients', 'categoryCounts', 'categories'));
    }
}
