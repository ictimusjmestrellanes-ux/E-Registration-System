<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Models\TransactionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientCategoryDistributionTest extends TestCase
{
    use RefreshDatabase;

    private function seedHistory(string $clientCategory): void
    {
        DB::table('transaction_history')->insert([
            'transaction_id' => 'TX-' . uniqid(),
            'client_id' => 'C1',
            'client_category' => $clientCategory,
            'transaction_date' => '2026-04-01',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'BIGAY BIGAS SA MASA',
            'events_transaction_type' => 'TRANCH 1',
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_distribution_groups_and_orders_by_count(): void
    {
        $this->seedHistory('LUPON');
        $this->seedHistory('INDIGENT');
        $this->seedHistory('INDIGENT');
        $this->seedHistory('PWD');
        $this->seedHistory('INDIGENT');

        $dist = app(ProfileController::class)->clientCategoryDistribution();

        $this->assertSame(['INDIGENT', 'LUPON', 'PWD'], $dist['labels']);
        $this->assertSame([3, 1, 1], $dist['data']);
    }

    public function test_distribution_ignores_blank_categories(): void
    {
        $this->seedHistory('INDIGENT');
        $this->seedHistory('');
        DB::table('transaction_history')->insert([
            'transaction_id' => 'TX-blank-null',
            'client_id' => 'C1',
            'client_category' => null,
            'transaction_date' => '2026-04-01',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'BIGAY BIGAS SA MASA',
            'events_transaction_type' => 'TRANCH 1',
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dist = app(ProfileController::class)->clientCategoryDistribution();

        $this->assertSame(['INDIGENT'], $dist['labels']);
        $this->assertSame([1], $dist['data']);
    }

    public function test_distribution_empty_without_rows(): void
    {
        $dist = app(ProfileController::class)->clientCategoryDistribution();

        $this->assertSame([], $dist['labels']);
        $this->assertSame([], $dist['data']);
    }

    public function test_distribution_folds_long_tail_into_others(): void
    {
        foreach (['AAA', 'BBB', 'CCC', 'DDD'] as $category) {
            $this->seedHistory($category);
        }
        $this->seedHistory('AAA');

        // Top 2 + the remaining 3 rows folded into Others.
        $dist = app(ProfileController::class)->clientCategoryDistribution(2);

        $this->assertSame(['AAA', 'BBB', 'Others'], $dist['labels']);
        $this->assertSame([2, 1, 2], $dist['data']);
    }
}
