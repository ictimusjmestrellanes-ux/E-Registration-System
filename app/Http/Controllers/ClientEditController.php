<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Controllers\Traits\HandlesClientStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClientEditController extends Controller
{
    use HandlesClientStorage;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function edit(Client $client)
    {
        return view('pages.clients.clients', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $this->validateClientPayload($request, $client);

        $photoPath = $client->photo_path;
        if (!empty($validated['photo_data'])) {
            $photoPath = $this->storeClientPhoto($validated['photo_data']);
            if (empty($photoPath)) {
                throw ValidationException::withMessages([
                    'photo_data' => 'A valid client photo is required.',
                ]);
            }

            if ($client->photo_path) {
                Storage::disk('public')->delete($client->photo_path);
            }
        }

        $fingerprintPath = $client->fingerprint_path;
        $fingerprintTemplate = $client->fingerprint_template;
        if (!empty($validated['fingerprint_data'])) {
            $this->ensureFingerprintIsUnique(
                $validated['fingerprint_template'] ?? null,
                $validated['fingerprint_data'] ?? null,
                $client->id
            );

            $storedFingerprintPath = $this->storeClientFingerprint($validated['fingerprint_data']);
            if (empty($storedFingerprintPath)) {
                throw ValidationException::withMessages([
                    'fingerprint_data' => 'A valid fingerprint capture is required.',
                ]);
            }

            if ($client->fingerprint_path) {
                Storage::disk('public')->delete($client->fingerprint_path);
            }

            $fingerprintPath = $storedFingerprintPath;
            $fingerprintTemplate = $validated['fingerprint_template'] ?? null;
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
}
