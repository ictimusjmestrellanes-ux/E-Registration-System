<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\ArchivedClient;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function profile()
    {
        return view('pages.profile');
    }

    public function dashboard()
    {
        return view('pages.dashboard');
    }

    public function clients()
    {
        return view('pages.clients');
    }

    public function clientList()
    {
        $clients = Client::latest()->get();

        return view('pages.clientList', compact('clients'));
    }

    public function archiveList()
    {
        $archivedClients = ArchivedClient::latest('archived_at')->get();

        return view('pages.archive', compact('archivedClients'));
    }

    public function restoreArchivedClient(ArchivedClient $archivedClient)
    {
        Client::create([
            'first_name' => $archivedClient->first_name,
            'middle_name' => $archivedClient->middle_name,
            'last_name' => $archivedClient->last_name,
            'age' => $archivedClient->age,
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
        ]);

        $archivedClient->delete();

        return redirect()->route('archive.list')->with('success', 'Client restored successfully.');
    }

    public function editClient(Client $client)
    {
        return view('pages.clients', compact('client'));
    }

    public function viewClient(Client $client)
    {
        return view('pages.clientShow', compact('client'));
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

        $photoPath = $this->storeClientPhoto($validated['photo_data'] ?? null);

        Client::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'age' => $validated['age'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'civil_status' => $validated['civil_status'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'address' => $validated['address'] ?? null,
            'province' => $validated['province'] ?? null,
            'city' => $validated['city'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'photo_path' => $photoPath,
            'fingerprint_path' => null,
        ]);

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

        $client->update([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'age' => $validated['age'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'civil_status' => $validated['civil_status'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'address' => $validated['address'] ?? null,
            'province' => $validated['province'] ?? null,
            'city' => $validated['city'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('client.list')->with('success', 'Client updated successfully.');
    }

    public function destroyClient(Client $client)
    {
        if ($client->photo_path) {
            Storage::disk('public')->delete($client->photo_path);
        }

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
            'age' => $client->age,
            'gender' => $client->gender,
            'civil_status' => $client->civil_status,
            'email' => $client->email,
            'contact' => $client->contact,
            'address' => $client->address,
            'province' => $client->province,
            'city' => $client->city,
            'barangay' => $client->barangay,
            'photo_path' => $client->photo_path,
            'fingerprint_path' => $client->fingerprint_path,
            'archived_at' => now(),
        ]);

        $client->delete();

        return redirect()->route('client.list')->with('success', 'Client archived successfully.');
    }

    private function validateClientPayload(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', 'string', 'max:20'],
            'civil_status' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'photo_data' => ['nullable', 'string'],
        ]);
    }

    private function storeClientPhoto(?string $photoData): ?string
    {
        return $this->storeBase64Image($photoData, 'clients', 'client_');
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

        return response()->json($response->json());
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

        $user->save();

        return redirect()->route('settings')->with('success', 'Profile updated successfully.');
    }

    public function faqs()
    {
        return view('pages.faqs');
    }
}
