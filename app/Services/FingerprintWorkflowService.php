<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Validation\ValidationException;

class FingerprintWorkflowService
{
    public function __construct(
        private readonly FingerprintCandidateRepository $candidates,
        private readonly FingerprintMatcherClient $matcher,
    ) {
    }

    public function search(?string $templateXml, ?string $imageDataUrl): array
    {
        $clients = $this->candidates->activeClientCandidates();

        if (empty($clients)) {
            return [
                'success' => true,
                'matched' => false,
                'message' => 'No fingerprint templates were found to search against.',
                'score' => null,
            ];
        }

        return $this->matcher->match($templateXml, $imageDataUrl, $clients);
    }

    public function ensureUnique(?string $templateXml, ?string $imageDataUrl, ?int $ignoreClientId = null): void
    {
        if (blank($templateXml) && blank($imageDataUrl)) {
            return;
        }

        $clients = $this->candidates->activeClientCandidates($ignoreClientId);
        if (empty($clients)) {
            return;
        }

        try {
            $match = $this->matcher->match($templateXml, $imageDataUrl, $clients);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'fingerprint_template' => $e->getMessage() ?: 'Fingerprint validation failed.',
            ]);
        }

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
}
