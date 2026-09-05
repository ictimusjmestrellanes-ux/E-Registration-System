<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function seedHistory(): void
    {
        DB::table('transaction_history')->insert([
            [
                'transaction_id' => 'C1-26-0001',
                'client_id' => 'C1',
                'transaction_date' => '2026-03-09',
                'category' => 'BIGAY BIGAS SA MASA',
                'type' => 'BIGAY BIGAS SA MASA',
                'events_transaction_type' => 'TRANCH 1',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_id' => 'C2-26-0001',
                'client_id' => 'C2',
                'transaction_date' => '2026-03-09',
                'category' => 'CARAVAN',
                'type' => 'CARAVAN',
                'events_transaction_type' => "MAYOR'S OFFICE",
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_transaction_type_filter_narrows_results(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedHistory();

        $response = $this->get(route('transactions.index', ['transaction_type' => 'TRANCH 1']));
        $response->assertOk();
        $response->assertSee('C1-26-0001');
        $response->assertDontSee('C2-26-0001');
    }

    public function test_transaction_type_filter_apostrophe_value(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedHistory();

        $response = $this->get(route('transactions.index', ['transaction_type' => "MAYOR'S OFFICE"]));
        $response->assertOk();
        $response->assertSee('C2-26-0001');
        $response->assertDontSee('C1-26-0001');
    }

    public function test_transaction_category_filter_narrows_results(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedHistory();

        $response = $this->get(route('transactions.index', ['transaction_category' => 'CARAVAN']));
        $response->assertOk();
        $response->assertSee('C2-26-0001');
        $response->assertDontSee('C1-26-0001');
    }

    public function test_list_shows_events_transaction_type_in_type_column(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedHistory();

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        // The visible Transaction Type column must reflect the filterable value.
        // (Blade escapes the apostrophe to &#039; — default escaped assertSee
        // matches that encoding.)
        $response->assertSee('TRANCH 1');
        $response->assertSee("MAYOR'S OFFICE");
    }

    public function test_keyword_search_finds_events_transaction_type(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedHistory();

        $response = $this->get(route('transactions.index', ['search' => 'TRANCH 1']));
        $response->assertOk();
        $response->assertSee('C1-26-0001');
        $response->assertDontSee('C2-26-0001');
    }
}
