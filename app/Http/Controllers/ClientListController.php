<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->when($request->boolean('duplicate_names'), function ($q) {
                // find duplicate keys using first+middle+last name + birth_date
                $duplicates = Client::query()
                    ->selectRaw("CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), COALESCE(birth_date,'')) as keyval")
                    ->groupBy('keyval')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('keyval')
                    ->map(fn($v) => (string) $v)
                    ->toArray();

                if (!empty($duplicates)) {
                    $q->whereIn(DB::raw("CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), COALESCE(birth_date,''))"), $duplicates);
                } else {
                    // nothing matches — ensure no rows
                    $q->whereRaw('0 = 1');
                }
            })
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
