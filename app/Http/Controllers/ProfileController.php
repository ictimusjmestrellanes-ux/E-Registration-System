<?php

namespace App\Http\Controllers;

use App\Models\Client;

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

        return view('pages.dashboard', compact('totalClients'));
    }
}
