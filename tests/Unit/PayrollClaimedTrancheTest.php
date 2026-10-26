<?php

namespace Tests\Unit;

use App\Support\PayrollClaimedTranche;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollClaimedTrancheTest extends TestCase
{
    #[DataProvider('transactionTypes')]
    public function test_it_maps_transaction_types_to_their_claimed_tranche(?string $type, ?int $expected): void
    {
        $this->assertSame($expected, PayrollClaimedTranche::fromTransactionType($type));
    }

    public static function transactionTypes(): array
    {
        return [
            ['C/O List', 1],
            ['CLAIMED - TRANCHE 1', 1],
            ['SIR ALLEN', 1],
            ['TRANCHE 1', 1],
            ['TRANCHE 2', 2],
            ['TRANCHE 3', 3],
            ['TRANCHE 4', 4],
            ['  tranche   2  ', 2],
            ['TRANCH 1', 1],
            ['TRANCH 4', 4],
            ['OTHER', null],
            [null, null],
        ];
    }
}
