<?php

namespace App\Services;

use App\Models\Client;

class FingerprintCandidateRepository
{
    public function __construct(private readonly MediaStorageService $media)
    {
    }

    public function activeClientCandidates(?int $ignoreClientId = null): array
    {
        $clients = [];
        $query = Client::query()
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'fingerprint_template', 'fingerprint_path']);

        if ($ignoreClientId) {
            $query->where('id', '!=', $ignoreClientId);
        }

        $query->chunk(200, function ($chunk) use (&$clients) {
            foreach ($chunk as $client) {
                $fingerprintImageDataUrl = empty($client->fingerprint_template)
                    ? $this->media->dataUrlFromPublicPath($client->fingerprint_path)
                    : null;

                if (!empty($client->fingerprint_template) || !empty($fingerprintImageDataUrl)) {
                    $clients[] = [
                        'id' => $client->id,
                        'name' => $client->full_name,
                        'fingerprintTemplateXml' => $client->fingerprint_template,
                        'fingerprintImageDataUrl' => $fingerprintImageDataUrl,
                    ];
                }
            }
        });

        return $clients;
    }
}
