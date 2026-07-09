<?php

namespace App\Services;

use App\Models\ArchivedClient;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function storeClientPhoto(?string $photoData): ?string
    {
        return $this->storeBase64Image($photoData, 'clients', 'client_');
    }

    public function storeClientFingerprint(?string $fingerprintData): ?string
    {
        return $this->storeBase64Image($fingerprintData, 'fingerprints', 'fingerprint_');
    }

    public function clientHasStoredPhoto(Client|ArchivedClient $client): bool
    {
        return !empty($client->photo_path) && Storage::disk('public')->exists($client->photo_path);
    }

    public function clientHasStoredFingerprint(Client|ArchivedClient $client): bool
    {
        if (!empty($client->fingerprint_template)) {
            return true;
        }

        return !empty($client->fingerprint_path) && Storage::disk('public')->exists($client->fingerprint_path);
    }

    public function dataUrlFromPublicPath(?string $path): ?string
    {
        if (empty($path) || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return 'data:' . $mimeType . ';base64,' . base64_encode(Storage::disk('public')->get($path));
    }

    public function storeBase64Image(?string $imageData, string $directory, string $prefix): ?string
    {
        if (empty($imageData)) {
            return null;
        }

        if (!preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $imageData, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        if (!in_array($extension, ['png', 'jpg', 'gif', 'webp', 'bmp'], true)) {
            return null;
        }

        $encoded = substr($imageData, strpos($imageData, ',') + 1);
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        $path = $directory . '/' . uniqid($prefix, true) . '.' . $extension;
        Storage::disk('public')->put($path, $decoded);

        return $path;
    }
}
