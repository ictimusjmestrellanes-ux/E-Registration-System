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

        $clients = Client::query()
            ->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status',
                'email', 'contact', 'contact_2', 'address',
                'province', 'city', 'barangay', 'birthplace',
                'education', 'course', 'sector', 'position_organization',
                'photo_path', 'created_at',
            ])
            ->orderBy('client_id', 'desc')
            ->when($matchedClientId, function ($query, $matchedClientId) {
                $query->where('id', $matchedClientId);
            })
            ->paginate(25);

        $clientCities = Client::whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $clientBarangays = Client::whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay');
        $clientCivilStatuses = Client::whereNotNull('civil_status')->distinct()->orderBy('civil_status')->pluck('civil_status');

        return view('pages.clients.clientList', compact('clients', 'matchedClientId', 'clientCities', 'clientBarangays', 'clientCivilStatuses'));
    }
}
