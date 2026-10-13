<?php

namespace App\Support;

final class PayrollCategory
{
    /** @var array<string, string> */
    private const CATEGORY_MAP = [
        'SOLO PARENTS' => 'SOLO PARENT',
        'SOLO PARENT GRADUATES' => 'SOLO PARENT',
        'TODA' => 'TRANSPORT GROUP',
        'PODA' => 'TRANSPORT GROUP',
        'TRANSPORT GROUP' => 'TRANSPORT GROUP',
        'DEPRESSED AREA' => 'URBAN POOR',
        'DULONG PADER' => 'URBAN POOR',
        'ISF' => 'URBAN POOR',
        'DAYCARE WORKER' => 'LABOR GROUP',
        'DELIVERY RIDERS' => 'LABOR GROUP',
    ];

    public static function fromClientCategory(?string $clientCategory): string
    {
        $normalized = mb_strtoupper(trim((string) $clientCategory));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return self::CATEGORY_MAP[$normalized] ?? 'INDIGENT';
    }
}
