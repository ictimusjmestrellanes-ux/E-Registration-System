<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionNameSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_transactions_defaults_to_the_displayed_client_name_order(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $bravo = Client::create([
            'client_id' => '2600002',
            'first_name' => 'BAKER',
            'last_name' => 'BRAVO',
        ]);
        $aldea = Client::create([
            'client_id' => '2600001',
            'first_name' => 'ALDEA',
            'middle_name' => 'LORETO',
            'last_name' => 'Z.',
        ]);

        foreach ([$bravo, $aldea] as $index => $client) {
            TransactionHistory::create([
                'client_id' => $client->client_id,
                'transaction_id' => 'TX-'.($index + 1),
                'transaction_date' => '2026-09-01',
                'category' => 'events',
                'type' => 'Assistance',
                'status' => 'Pending',
            ]);
        }

        $transactions = $this->get(route('transactions.index'))
            ->assertOk()->viewData('transactions');
        $this->assertSame(
            [$aldea->client_id, $bravo->client_id],
            $transactions->pluck('client_id')->all()
        );

        $descending = $this->get(route('transactions.index', ['sort' => 'client_desc']))
            ->assertOk()->viewData('transactions');
        $this->assertSame(
            [$bravo->client_id, $aldea->client_id],
            $descending->pluck('client_id')->all()
        );
    }
}
