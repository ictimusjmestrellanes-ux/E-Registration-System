<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HandlesClientStorage;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FingerprintController extends Controller
{
    use HandlesClientStorage;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'fingerprint_template' => ['required', 'string'],
        ]);

        $clients = Client::query()
            ->get([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'fingerprint_template',
                'fingerprint_path',
            ])
            ->map(function (Client $client) {
                $fingerprintImageDataUrl = null;

                if (empty($client->fingerprint_template) && !empty($client->fingerprint_path) && Storage::disk('public')->exists($client->fingerprint_path)) {
                    $mimeType = Storage::disk('public')->mimeType($client->fingerprint_path) ?: 'image/png';
                    $fingerprintImageDataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(Storage::disk('public')->get($client->fingerprint_path));
                }

                return [
                    'id' => $client->id,
                    'name' => $client->full_name,
                    'fingerprintTemplateXml' => $client->fingerprint_template,
                    'fingerprintImageDataUrl' => $fingerprintImageDataUrl,
                ];
            })
            ->filter(function (array $client) {
                return !empty($client['fingerprintTemplateXml']) || !empty($client['fingerprintImageDataUrl']);
            })
            ->values()
            ->all();

        if (empty($clients)) {
            return response()->json([
                'success' => false,
                'message' => 'No fingerprint templates were found to search against.',
            ], 404);
        }

        $response = Http::timeout(60)->post($this->fingerprintBridgeUrl('api/match'), [
            'fingerprintTemplateXml' => $validated['fingerprint_template'],
            'clients' => $clients,
        ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => $response->json('message') ?: 'Fingerprint search failed.',
            ], 503);
        }

        $match = $response->json();
        if (!($match['success'] ?? false) || empty($match['matchedClientId'])) {
            return response()->json([
                'success' => true,
                'matched' => false,
                'message' => $match['message'] ?? 'No matching client found.',
                'score' => $match['bestScore'] ?? null,
            ]);
        }

        $client = Client::find($match['matchedClientId']);
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'The matching client record could not be loaded.',
            ], 404);
        }

        $payload = [
            'success' => true,
            'matched' => true,
            'score' => $match['bestScore'] ?? null,
            'client' => [
                'id' => $client->id,
                'name' => $client->full_name,
                'photo_url' => $client->photo_url,
                'age' => $client->age,
                'birth_date' => optional($client->birth_date)->format('Y-m-d'),
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
                'show_url' => route('clients.show', $client),
            ],
        ];

        $this->recordActivity(
            'fingerprint_search',
            'Fingerprint search matched client ' . $this->clientDisplayName($client) . '.',
            ['client_id' => $client->id, 'score' => $match['bestScore'] ?? null],
            $client
        );

        return response()->json($payload);
    }

    public function health()
    {
        try {
            $response = Http::timeout(5)->get($this->fingerprintBridgeUrl('api/health'));

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fingerprint bridge is not reachable.',
            ], 503);
        }
    }

    public function capture(Request $request)
    {
        try {
            $response = Http::timeout(45)->post($this->fingerprintBridgeUrl('api/capture'), [
                'source' => 'laravel',
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => $response->json('message') ?: 'Fingerprint capture failed.',
                ], 503);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fingerprint capture failed: ' . $e->getMessage(),
            ], 503);
        }
    }
}
