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

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $clients = Client::query()
            ->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status',
                'email', 'contact', 'contact_2', 'address',
                'province', 'city', 'barangay', 'birthplace',
                'education', 'course', 'sector', 'position_organization',
                'photo_path', 'created_at',
            ])
            // Server-side keyword search so results span every page.
            ->when($request->filled('search'), function ($q) use ($request) {
                $keyword = strtolower(trim($request->input('search')));
                $q->where(function ($sub) use ($keyword) {
                    $sub->whereRaw("LOWER(CONCAT_WS(' ', first_name, middle_name, last_name, suffix)) LIKE ?", ["%{$keyword}%"])
                        ->orWhereRaw('LOWER(client_id) LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->when($request->filled('civil_status'), fn ($q) => $q->where('civil_status', $request->input('civil_status')))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->when($request->filled('barangay'), fn ($q) => $q->where('barangay', $request->input('barangay')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
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
            ->paginate($perPage)
            ->withQueryString();

        $clientCities = Client::whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $clientBarangays = Client::whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay');
        $clientCivilStatuses = Client::whereNotNull('civil_status')->distinct()->orderBy('civil_status')->pluck('civil_status');

        return view('pages.clients.clientList', compact('clients', 'matchedClientId', 'clientCities', 'clientBarangays', 'clientCivilStatuses'));
    }
}
