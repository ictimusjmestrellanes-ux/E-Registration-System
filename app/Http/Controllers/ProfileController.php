<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\ArchivedClient;
use App\Models\ActivityLog;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Services\ActivityLogger;

class ProfileController extends Controller
{
    private const FINGERPRINT_BRIDGE_BASE = 'http://127.0.0.1:38654';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function profile()
    {
        return view('pages.profile');
    }

    public function activityLogs()
    {
        $activityQuery = ActivityLog::with('user')->latest();

        $activities = (clone $activityQuery)->paginate(12);
        $todayActivities = (clone $activityQuery)
            ->whereDate('created_at', now()->toDateString())
            ->take(8)
            ->get();
        $weeklyActivities = (clone $activityQuery)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->take(8)
            ->get();
        $monthlyActivities = (clone $activityQuery)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->take(8)
            ->get();

        return view('pages.activityLogs', compact(
            'activities',
            'todayActivities',
            'weeklyActivities',
            'monthlyActivities'
        ));
    }

    public function dashboard()
    {
        return view('pages.dashboard');
    }

    public function clients()
    {
        return view('pages.clients');
    }

    public function clientList(Request $request)
    {
        $matchedClientId = $request->query('matched_client');

        $clients = Client::latest()
            ->when($matchedClientId, function ($query, $matchedClientId) {
                $query->where('id', $matchedClientId);
            })
            ->get();

        return view('pages.clientList', compact('clients', 'matchedClientId'));
    }

    public function archiveList()
    {
        $archivedClients = ArchivedClient::latest('archived_at')->get();

        return view('pages.archive', compact('archivedClients'));
    }

    public function restoreArchivedClient(ArchivedClient $archivedClient)
    {
        $client = Client::create([
            'first_name' => $archivedClient->first_name,
            'middle_name' => $archivedClient->middle_name,
            'last_name' => $archivedClient->last_name,
            'suffix' => $archivedClient->suffix,
            'age' => $archivedClient->age,
            'birth_date' => $archivedClient->birth_date,
            'birthplace' => $archivedClient->birthplace,
            'education' => $archivedClient->education,
            'course' => $archivedClient->course,
            'sector' => $archivedClient->sector,
            'position_organization' => $archivedClient->position_organization,
            'gender' => $archivedClient->gender,
            'civil_status' => $archivedClient->civil_status,
            'email' => $archivedClient->email,
            'contact' => $archivedClient->contact,
            'address' => $archivedClient->address,
            'province' => $archivedClient->province,
            'city' => $archivedClient->city,
            'barangay' => $archivedClient->barangay,
            'photo_path' => $archivedClient->photo_path,
            'fingerprint_path' => $archivedClient->fingerprint_path,
            'fingerprint_template' => $archivedClient->fingerprint_template,
        ]);

        $archivedClient->delete();
        $this->recordActivity(
            'client_restored',
            'Restored archived client ' . $this->clientDisplayName($client) . '.',
            ['archived_client_id' => $archivedClient->id],
            $client
        );

        return redirect()->route('archive.list')->with('success', 'Client restored successfully.');
    }

    public function editClient(Client $client)
    {
        return view('pages.clients', compact('client'));
    }

    public function viewClient(Client $client)
    {
        $transactionLogs = ActivityLog::with('user')
            ->where(function ($query) use ($client) {
                $query->where('subject_type', 'Client')
                    ->where('subject_id', $client->id);
            })
            ->latest()
            ->take(10)
            ->get();

        return view('pages.clientShow', compact('client', 'transactionLogs'));
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

    public function storeClient(Request $request)
    {
        $validated = $this->validateClientPayload($request);

        $this->ensureFingerprintForDuplicateClientIdentity($validated);

        $this->ensureFingerprintIsUnique(
            $validated['fingerprint_template'] ?? null,
            $validated['fingerprint_data'] ?? null
        );

        $photoPath = $this->storeClientPhoto($validated['photo_data'] ?? null);
        $fingerprintPath = $this->storeClientFingerprint($validated['fingerprint_data'] ?? null);
        $fingerprintTemplate = $validated['fingerprint_template'] ?? null;

        $client = Client::create([
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

        return redirect()->route('clients')->with('success', 'Client saved successfully.');
    }

    public function updateClient(Request $request, Client $client)
    {
        $validated = $this->validateClientPayload($request);

        $photoPath = $client->photo_path;
        if (!empty($validated['photo_data'])) {
            if ($client->photo_path) {
                Storage::disk('public')->delete($client->photo_path);
            }

            $photoPath = $this->storeClientPhoto($validated['photo_data']);
        }

        $fingerprintPath = $client->fingerprint_path;
        $fingerprintTemplate = $client->fingerprint_template;
        if (!empty($validated['fingerprint_data'])) {
            $this->ensureFingerprintIsUnique(
                $validated['fingerprint_template'] ?? null,
                $validated['fingerprint_data'] ?? null,
                $client->id
            );

            if ($client->fingerprint_path) {
                Storage::disk('public')->delete($client->fingerprint_path);
            }

            $fingerprintPath = $this->storeClientFingerprint($validated['fingerprint_data']);
            $fingerprintTemplate = $validated['fingerprint_template'] ?? null;
        } elseif ($request->boolean('fingerprint_remove') && $client->fingerprint_path) {
            Storage::disk('public')->delete($client->fingerprint_path);
            $fingerprintPath = null;
            $fingerprintTemplate = null;
        }

        $client->update([
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
            'client_updated',
            'Updated client ' . $this->clientDisplayName($client) . '.',
            ['client_id' => $client->id],
            $client
        );

        return redirect()->route('client.list')->with('success', 'Client updated successfully.');
    }

    public function destroyClient(Client $client)
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

    public function archiveClient(Client $client)
    {
        ArchivedClient::create([
            'original_client_id' => $client->id,
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

    private function validateClientPayload(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'birth_date' => ['nullable', 'date'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'position_organization' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'civil_status' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:30'],
            'contact_2' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'photo_data' => ['nullable', 'string'],
            'fingerprint_data' => ['nullable', 'string'],
            'fingerprint_template' => ['nullable', 'string'],
            'fingerprint_remove' => ['nullable'],
        ]);
    }

    private function ensureFingerprintForDuplicateClientIdentity(array $validated): void
    {
        if (empty($validated['birth_date']) || !empty($validated['fingerprint_data'])) {
            return;
        }

        if (!$this->clientIdentityAlreadyExists($validated)) {
            return;
        }

        throw ValidationException::withMessages([
            'fingerprint_template' => 'Fingerprint is required when a client with the same name and birth date already exists.',
        ]);
    }

    private function clientIdentityAlreadyExists(array $validated): bool
    {
        $birthDate = $validated['birth_date'] ?? null;
        if (empty($birthDate)) {
            return false;
        }

        foreach ([Client::class, ArchivedClient::class] as $modelClass) {
            $matches = $modelClass::query()
                ->whereDate('birth_date', $birthDate)
                ->get(['first_name', 'middle_name', 'last_name', 'suffix'])
                ->contains(function ($client) use ($validated) {
                    return $this->clientIdentityMatches($validated, $client);
                });

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function clientIdentityMatches(array $validated, Client|ArchivedClient $client): bool
    {
        $targetFirstName = $this->normalizeClientNamePart($validated['first_name'] ?? null);
        $targetMiddleName = $this->normalizeClientNamePart($validated['middle_name'] ?? null);
        $targetLastName = $this->normalizeClientNamePart($validated['last_name'] ?? null);
        $targetSuffix = $this->normalizeClientNamePart($validated['suffix'] ?? null);

        $clientFirstName = $this->normalizeClientNamePart($client->first_name ?? null);
        $clientMiddleName = $this->normalizeClientNamePart($client->middle_name ?? null);
        $clientLastName = $this->normalizeClientNamePart($client->last_name ?? null);
        $clientSuffix = $this->normalizeClientNamePart($client->suffix ?? null);

        if ($targetFirstName !== $clientFirstName || $targetLastName !== $clientLastName) {
            return false;
        }

        if ($targetMiddleName !== '' && $clientMiddleName !== '' && $targetMiddleName !== $clientMiddleName) {
            return false;
        }

        if ($targetSuffix !== '' && $clientSuffix !== '' && $targetSuffix !== $clientSuffix) {
            return false;
        }

        return true;
    }

    private function normalizeClientNamePart(?string $value): string
    {
        return Str::of((string) $value)->squish()->lower()->toString();
    }

    private function storeClientPhoto(?string $photoData): ?string
    {
        return $this->storeBase64Image($photoData, 'clients', 'client_');
    }

    private function storeClientFingerprint(?string $fingerprintData): ?string
    {
        return $this->storeBase64Image($fingerprintData, 'fingerprints', 'fingerprint_');
    }

    private function recordActivity(string $action, string $description, array $properties = [], Client|ArchivedClient|array|null $subject = null): void
    {
        app(ActivityLogger::class)->record($action, $description, $properties, $subject);
    }

    private function clientDisplayName(Client|ArchivedClient $client): string
    {
        return trim(implode(' ', array_filter([
            $client->first_name,
            $client->middle_name,
            $client->last_name,
            $client->suffix ?? null,
        ])));
    }

    private function ensureFingerprintIsUnique(?string $fingerprintTemplate, ?string $fingerprintData, ?int $ignoreClientId = null): void
    {
        if (empty($fingerprintTemplate) && empty($fingerprintData)) {
            return;
        }

        $clientsQuery = Client::query()->select([
            'id',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'fingerprint_template',
            'fingerprint_path',
        ]);

        if ($ignoreClientId) {
            $clientsQuery->where('id', '!=', $ignoreClientId);
        }

        $clients = $clientsQuery
            ->get()
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
            return;
        }

        $response = Http::timeout(60)->post($this->fingerprintBridgeUrl('api/match'), [
            'fingerprintTemplateXml' => $fingerprintTemplate ?? '',
            'fingerprintImageDataUrl' => $fingerprintData ?? '',
            'clients' => $clients,
        ]);

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'fingerprint_template' => $response->json('message') ?: 'Fingerprint validation failed.',
            ]);
        }

        $match = $response->json();
        if (!($match['matched'] ?? false) || empty($match['matchedClientId'])) {
            return;
        }

        $matchedClient = Client::find($match['matchedClientId']);
        $matchedName = $matchedClient
            ? $matchedClient->full_name
            : ($match['matchedClientName'] ?? 'an existing client');

        throw ValidationException::withMessages([
            'fingerprint_template' => "This fingerprint is already taken by {$matchedName}.",
        ]);
    }

    private function fingerprintBridgeUrl(string $path = ''): string
    {
        return rtrim(self::FINGERPRINT_BRIDGE_BASE, '/') . '/' . ltrim($path, '/');
    }

    private function storeBase64Image(?string $imageData, string $directory, string $prefix): ?string
    {
        if (empty($imageData)) {
            return null;
        }

        if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]);
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
        $decoded = base64_decode($imageData);

        if ($decoded === false) {
            return null;
        }

        $path = $directory . '/' . uniqid($prefix, true) . '.' . $extension;
        Storage::disk('public')->put($path, $decoded);

        return $path;
    }

    private function fetchPsgcJson(string $url)
    {
        $response = Http::timeout(15)->get($url);

        if (!$response->successful()) {
            return response()->json(['message' => 'Unable to load PSGC data.'], 503);
        }

        return response()->json($this->normalizePsgcItems($response->json()));
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

    public function searchClientByFingerprint(Request $request)
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

    public function profileSettings()
    {
        return view('pages.settings');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_photo_file' => ['nullable', 'image', 'max:4096'],
            'cover_photo_data' => ['nullable', 'string'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone_number'] ?? null;

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        if (!empty($validated['cover_photo_data'])) {
            if ($user->cover_photo) {
                Storage::disk('public')->delete($user->cover_photo);
            }

            $user->cover_photo = $this->storeBase64Image($validated['cover_photo_data'], 'covers', 'cover_');
        } elseif ($request->hasFile('cover_photo_file')) {
            if ($user->cover_photo) {
                Storage::disk('public')->delete($user->cover_photo);
            }

            $user->cover_photo = $request->file('cover_photo_file')->store('covers', 'public');
        }

        $user->save();

        $this->recordActivity(
            'profile_updated',
            'Updated profile information.',
            ['user_id' => $user->id],
            ['type' => 'User', 'id' => $user->id]
        );

        return redirect()->route('settings')->with('success', 'Profile updated successfully.');
    }

    public function faqs()
    {
        return view('pages.faqs');
    }
}
