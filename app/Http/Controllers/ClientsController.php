<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ArchivedClient;
use App\Models\Client;
use App\Http\Controllers\Traits\HandlesClientStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClientsController extends Controller
{
    use HandlesClientStorage;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create()
    {
        return view('pages.clients.clients');
    }

    public function store(Request $request)
    {
        $validated = $this->validateClientPayload($request);

        $this->ensureFingerprintForDuplicateClientIdentity($validated);

        $this->ensureFingerprintIsUnique(
            $validated['fingerprint_template'] ?? null,
            $validated['fingerprint_data'] ?? null
        );

        $photoPath = $this->storeClientPhoto($validated['photo_data'] ?? null);
        if (empty($photoPath)) {
            throw ValidationException::withMessages([
                'photo_data' => 'A valid client photo is required.',
            ]);
        }

        $fingerprintPath = $this->storeClientFingerprint($validated['fingerprint_data'] ?? null);
        if (empty($fingerprintPath)) {
            throw ValidationException::withMessages([
                'fingerprint_data' => 'A valid fingerprint capture is required.',
                'fingerprint_template' => 'A valid fingerprint capture is required.',
            ]);
        }

        $fingerprintTemplate = $validated['fingerprint_template'] ?? null;

        $client = Client::create([
            'client_id' => Client::generateClientId(),
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'age' => $validated['age'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'birthplace' => $validated['birthplace'] ?? null,
            'education' => $validated['education'] ?? null,
            'course' => $validated['course'] ?? null,
            'sector' => $validated['sector'] ?? null,
            'position_organization' => $validated['position_organization'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'civil_status' => $validated['civil_status'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'contact_2' => $validated['contact_2'] ?? null,
            'address' => $validated['address'] ?? null,
            'province' => $validated['province'] ?? null,
            'city' => $validated['city'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'photo_path' => $photoPath,
            'fingerprint_path' => $fingerprintPath,
            'fingerprint_template' => $fingerprintTemplate,
        ]);

        $this->recordActivity(
            'client_created',
            'Created client ' . $this->clientDisplayName($client) . '.',
            ['client_id' => $client->id],
            $client
        );

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client saved successfully.')
            ->with('show_created_modal', true);
    }

    public function show(Client $client)
    {
        $transactions = \App\Models\TransactionHistory::where('transaction_id', 'LIKE', $client->client_id . '-%')
            ->latest()
            ->get();

        $transaction = null;
        $transactionId = session('show_transaction') ?? request()->query('show_transaction');
        if ($transactionId) {
            $transaction = \App\Models\TransactionHistory::find($transactionId);
        }

        return view('pages.clients.clientShow', compact('client', 'transactions', 'transaction'));
    }

    public function destroy(Client $client)
    {
        if ($client->photo_path) {
            Storage::disk('public')->delete($client->photo_path);
        }

        if ($client->fingerprint_path) {
            Storage::disk('public')->delete($client->fingerprint_path);
        }

        $this->recordActivity(
            'client_deleted',
            'Deleted client ' . $this->clientDisplayName($client) . '.',
            ['client_id' => $client->id],
            $client
        );

        $client->delete();

        return redirect()->route('client.list')->with('success', 'Client deleted successfully.');
    }

    public function archive(Client $client)
    {
        ArchivedClient::create([
            'original_client_id' => $client->id,
            'client_id' => $client->client_id,
            'first_name' => $client->first_name,
            'middle_name' => $client->middle_name,
            'last_name' => $client->last_name,
            'suffix' => $client->suffix,
            'age' => $client->age,
            'birth_date' => $client->birth_date,
            'birthplace' => $client->birthplace,
            'education' => $client->education,
            'course' => $client->course,
            'sector' => $client->sector,
            'position_organization' => $client->position_organization,
            'gender' => $client->gender,
            'civil_status' => $client->civil_status,
            'email' => $client->email,
            'contact' => $client->contact,
            'contact_2' => $client->contact_2,
            'address' => $client->address,
            'province' => $client->province,
            'city' => $client->city,
            'barangay' => $client->barangay,
            'photo_path' => $client->photo_path,
            'fingerprint_path' => $client->fingerprint_path,
            'fingerprint_template' => $client->fingerprint_template,
            'archived_at' => now(),
        ]);

        $this->recordActivity(
            'client_archived',
            'Archived client ' . $this->clientDisplayName($client) . '.',
            ['client_id' => $client->id],
            $client
        );

        $client->delete();

        return redirect()->route('client.list')->with('success', 'Client archived successfully.');
    }

    public function psgcProvinces()
    {
        return $this->fetchPsgcJson('https://psgc.cloud/api/v2/provinces');
    }

    public function psgcCities(string $provinceCode)
    {
        return $this->fetchPsgcJson("https://psgc.cloud/api/v2/provinces/{$provinceCode}/cities-municipalities");
    }

    public function psgcBarangays(string $cityCode)
    {
        return $this->fetchPsgcJson("https://psgc.cloud/api/v2/cities-municipalities/{$cityCode}/barangays");
    }

    private function fetchPsgcJson(string $url)
    {
        $cacheKey = 'psgc_' . md5($url);

        return Cache::remember($cacheKey, 86400, function () use ($url) {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                return response()->json(['message' => 'Unable to load PSGC data.'], 503);
            }

            return response()->json($this->normalizePsgcItems($response->json()));
        });
    }

    private function normalizePsgcItems(mixed $payload): array
    {
        $items = data_get($payload, 'data', $payload);

        if ($items instanceof \Traversable) {
            $items = iterator_to_array($items);
        }

        if (!is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item) {
                $name = data_get($item, 'name')
                    ?? data_get($item, 'label')
                    ?? data_get($item, 'description');
                if (blank($name)) {
                    return null;
                }

                return [
                    'code' => data_get($item, 'code') ?? data_get($item, 'psgcCode') ?? data_get($item, 'id') ?? '',
                    'name' => $name,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
