<?php

namespace App\Support;

final class PayrollClaimedTranche
{
    /** @var array<string, int> */
    private const TRANSACTION_TYPE_MAP = [
        'C/O LIST' => 1,
        'CLAIMED - TRANCHE 1' => 1,
        'SIR ALLEN' => 1,
        'TRANCHE 1' => 1,
        'TRANCH 1' => 1,
        'TRANCHE 2' => 2,
        'TRANCH 2' => 2,
        'TRANCHE 3' => 3,
        'TRANCH 3' => 3,
        'TRANCHE 4' => 4,
        'TRANCH 4' => 4,
    ];

    public static function fromTransactionType(?string $transactionType): ?int
    {
        $normalized = mb_strtoupper(trim((string) $transactionType));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return self::TRANSACTION_TYPE_MAP[$normalized] ?? null;
    }
}
