<?php

namespace Tests\Unit;

use App\Support\PayrollCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayrollCategoryTest extends TestCase
{
    #[DataProvider('categoryMappings')]
    public function test_it_maps_client_categories_to_their_payroll_category(
        ?string $clientCategory,
        string $expected,
    ): void {
        $this->assertSame($expected, PayrollCategory::fromClientCategory($clientCategory));
    }

    /** @return array<string, array{0: string|null, 1: string}> */
    public static function categoryMappings(): array
    {
        return [
            'solo parents' => ['SOLO PARENTS', 'SOLO PARENT'],
            'solo parent graduates' => ['SOLO PARENT GRADUATES', 'SOLO PARENT'],
            'toda' => ['TODA', 'TRANSPORT GROUP'],
            'poda' => ['PODA', 'TRANSPORT GROUP'],
            'transport group' => ['TRANSPORT GROUP', 'TRANSPORT GROUP'],
            'depressed area' => ['DEPRESSED AREA', 'URBAN POOR'],
            'dulong pader' => ['DULONG PADER', 'URBAN POOR'],
            'isf' => ['ISF', 'URBAN POOR'],
            'daycare worker' => ['DAYCARE WORKER', 'LABOR GROUP'],
            'delivery riders' => ['DELIVERY RIDERS', 'LABOR GROUP'],
            'case and whitespace normalization' => ['  solo   parents ', 'SOLO PARENT'],
            'other category' => ['SENIOR CITIZEN', 'INDIGENT'],
            'empty category' => ['', 'INDIGENT'],
            'null category' => [null, 'INDIGENT'],
        ];
    }
}
