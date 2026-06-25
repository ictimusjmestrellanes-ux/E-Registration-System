<?php

namespace App\Http\Controllers;

use App\Models\ArchivedClient;
use App\Models\Client;
use App\Http\Controllers\Traits\HandlesClientStorage;

class ArchiveController extends Controller
{
    use HandlesClientStorage;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $archivedClients = ArchivedClient::latest('archived_at')->get();

        return view('pages.archive', compact('archivedClients'));
    }

    public function restore(ArchivedClient $archivedClient)
    {
        if (!$this->clientHasStoredPhoto($archivedClient) || !$this->clientHasStoredFingerprint($archivedClient)) {
            return redirect()
                ->route('archive.list')
                ->with('error', 'Archived clients must have both a photo and fingerprint before they can be restored.');
        }

        $client = Client::create([
            'client_id' => $archivedClient->client_id ?? Client::generateClientId(),
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
}
