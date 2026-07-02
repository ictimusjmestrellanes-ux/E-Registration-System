<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FingerprintMatcherClient
{
    public function match(?string $templateXml, ?string $imageDataUrl, array $clients): array
    {
        if (blank($templateXml) && blank($imageDataUrl)) {
            throw new RuntimeException('A fingerprint capture is required.');
        }

        if (empty($clients)) {
            return [
                'success' => true,
                'matched' => false,
                'message' => 'No fingerprint templates were provided.',
                'bestScore' => null,
            ];
        }

        $response = $this->http()
            ->timeout(60)
            ->post($this->url((string) config('fingerprint.matcher_match_path', '/api/match')), [
                'fingerprintTemplateXml' => $templateXml ?? '',
                'fingerprintImageDataUrl' => $imageDataUrl ?? '',
                'clients' => $clients,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($response->json('message') ?: 'Fingerprint matching service failed.');
        }

        return $response->json();
    }

    public function health(): array
    {
        $response = $this->http()
            ->timeout(5)
            ->get($this->url((string) config('fingerprint.matcher_health_path', '/api/health')));

        if (!$response->successful()) {
            throw new RuntimeException($response->json('message') ?: 'Fingerprint matching service is not healthy.');
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson();
        $token = config('fingerprint.matcher_token');

        return filled($token) ? $request->withToken((string) $token) : $request;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('fingerprint.matcher_url'), '/') . '/' . ltrim($path, '/');
    }
}
