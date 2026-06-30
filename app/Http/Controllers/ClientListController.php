<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientListController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $matchedClientId = $request->query('matched_client');

        $clients = Client::latest()
            ->when($matchedClientId, function ($query, $matchedClientId) {
                $query->where('id', $matchedClientId);
            })
            ->get();

        return view('pages.clients.clientList', compact('clients', 'matchedClientId'));
    }
}
