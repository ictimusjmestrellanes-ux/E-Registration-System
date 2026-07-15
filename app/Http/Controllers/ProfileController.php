<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransactionHistory;

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
        $totalClients = Client::count();

        $categoryCounts = TransactionHistory::selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $categories = TransactionHistory::CATEGORIES;

        return view('pages.dashboard', compact('totalClients', 'categoryCounts', 'categories'));
    }
}
