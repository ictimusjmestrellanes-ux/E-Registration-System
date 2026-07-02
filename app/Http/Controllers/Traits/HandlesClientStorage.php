<?php

namespace App\Http\Controllers\Traits;

use App\Models\ActivityLog;
use App\Models\ArchivedClient;
use App\Models\Client;
use App\Services\ActivityLogger;
use App\Services\FingerprintWorkflowService;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait HandlesClientStorage
{
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
            'sector' => ['nullable', 'string', 'max:500'],
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
            'fingerprint_template' => ['nullable', 'string'],
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

        if (!$hasIncomingFingerprintData && $hasIncomingFingerprintTemplate) {
            $errors['fingerprint_data'] = 'Fingerprint capture must include an image.';
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
        return app(MediaStorageService::class)->clientHasStoredPhoto($client);
    }

    private function clientHasStoredFingerprint(Client|ArchivedClient $client): bool
    {
        return app(MediaStorageService::class)->clientHasStoredFingerprint($client);
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
                ->where('first_name', $validated['first_name'] ?? '')
                ->where('last_name', $validated['last_name'] ?? '')
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
        return app(MediaStorageService::class)->storeClientPhoto($photoData);
    }

    private function storeClientFingerprint(?string $fingerprintData): ?string
    {
        return app(MediaStorageService::class)->storeClientFingerprint($fingerprintData);
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
        app(FingerprintWorkflowService::class)->ensureUnique($fingerprintTemplate, $fingerprintData, $ignoreClientId);
    }

    private function fingerprintBridgeUrl(string $path = ''): string
    {
        return rtrim((string) config('fingerprint.local_bridge_url'), '/') . '/' . ltrim($path, '/');
    }

    private function storeBase64Image(?string $imageData, string $directory, string $prefix): ?string
    {
        return app(MediaStorageService::class)->storeBase64Image($imageData, $directory, $prefix);
    }
}
