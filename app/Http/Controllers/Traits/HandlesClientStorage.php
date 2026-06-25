<?php

namespace App\Http\Controllers\Traits;

use App\Models\ActivityLog;
use App\Models\ArchivedClient;
use App\Models\Client;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait HandlesClientStorage
{
    private const FINGERPRINT_BRIDGE_BASE = 'http://127.0.0.1:38654';

    private function validateClientPayload(Request $request, ?Client $client = null): array
    {
        $validated = $request->validate([
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
            'photo_data' => $client ? ['nullable', 'string'] : ['required', 'string'],
            'fingerprint_data' => $client ? ['nullable', 'string'] : ['required', 'string'],
            'fingerprint_template' => $client ? ['nullable', 'string'] : ['required', 'string'],
        ]);

        if (!$client) {
            return $validated;
        }

        $errors = [];
        $hasIncomingPhoto = filled($validated['photo_data'] ?? null);
        $hasIncomingFingerprintData = filled($validated['fingerprint_data'] ?? null);
        $hasIncomingFingerprintTemplate = filled($validated['fingerprint_template'] ?? null);

        if (!$hasIncomingPhoto && !$this->clientHasStoredPhoto($client)) {
            $errors['photo_data'] = 'Client photo is required before saving.';
        }

        if ($hasIncomingFingerprintData xor $hasIncomingFingerprintTemplate) {
            $errors['fingerprint_data'] = 'Fingerprint capture must include both the image and template.';
            $errors['fingerprint_template'] = 'Fingerprint capture must include both the image and template.';
        } elseif (!$hasIncomingFingerprintData && !$hasIncomingFingerprintTemplate && !$this->clientHasStoredFingerprint($client)) {
            $errors['fingerprint_data'] = 'Client fingerprint is required before saving.';
            $errors['fingerprint_template'] = 'Client fingerprint is required before saving.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    private function clientHasStoredPhoto(Client|ArchivedClient $client): bool
    {
        return !empty($client->photo_path) && Storage::disk('public')->exists($client->photo_path);
    }

    private function clientHasStoredFingerprint(Client|ArchivedClient $client): bool
    {
        if (!empty($client->fingerprint_template)) {
            return true;
        }

        return !empty($client->fingerprint_path) && Storage::disk('public')->exists($client->fingerprint_path);
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
}
