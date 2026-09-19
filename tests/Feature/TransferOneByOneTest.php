<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferOneByOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_one_by_one_transfer_creates_a_fresh_client_and_linked_transaction(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
        ]);

        $events = collect([
            ['full_name' => 'Juan Dela Cruz', 'contact_no' => '09170000001'],
            ['full_name' => 'Juan Dela Cruz', 'contact_no' => '09170000002'],
            ['full_name' => 'Maria Santos', 'contact_no' => '09170000003'],
        ])->map(fn (array $data) => TransactionEvent::create($data + [
            'address' => 'Brgy 1',
            'birth_date' => '1990-01-01',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-09-01',
        ]));

        $clientIds = [];
        foreach ($events as $event) {
            $response = $this->postJson(route('transaction-events.transfer-one'), ['event_id' => $event->id]);
            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('created_client', true);

            $event->refresh();
            $this->assertNotNull($event->transferred_at);
            $history = TransactionHistory::findOrFail($event->transferred_transaction_id);
            $this->assertSame($response->json('transaction_id'), $history->transaction_id);
            $this->assertSame('transfer-one', $history->source);
            $this->assertSame('TRANCH 1', $history->events_transaction_type);
            $this->assertSame('2026-09-01', $history->transaction_date->format('Y-m-d'));
            $clientIds[] = $history->client_id;
        }

        $this->assertCount(3, array_unique($clientIds));
        $this->assertNotContains('2600001', $clientIds);
        $this->assertDatabaseCount('clients', 4);
        $this->assertDatabaseCount('transaction_history', 3);
        $this->assertDatabaseHas('clients', ['client_id' => $clientIds[0], 'contact' => '09170000001']);
        $this->assertDatabaseHas('clients', ['client_id' => $clientIds[1], 'contact' => '09170000002']);
        $this->assertDatabaseHas('clients', ['client_id' => $clientIds[2], 'contact' => '09170000003']);

        $this->postJson(route('transaction-events.transfer-one'), ['event_id' => $events[0]->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertDatabaseCount('clients', 4);
        $this->assertDatabaseCount('transaction_history', 3);
    }

    public function test_force_create_client_processes_at_most_500_events_per_chunk(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Batch',
            'last_name' => 'Person',
        ]);

        $rows = [];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = [
                'full_name' => 'Batch Person',
                'contact_no' => '0917' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'status' => 'Pending',
                'transaction_category' => 'BIGAY BIGAS SA MASA',
                'transaction_type' => 'TRANCH 1',
                'event_date' => '2026-09-01',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        TransactionEvent::insert($rows);

        $prepare = $this->postJson(route('transaction-events.transfer-selected.prepare'), [
            'select_all' => 1,
            'force_new_clients' => true,
        ]);
        $prepare->assertOk()->assertJsonPath('total', 501);
        $token = $prepare->json('token');

        $this->postJson(route('transaction-events.transfer-selected.process'), [
            'token' => $token,
            'offset' => 0,
            'limit' => 1000,
        ])->assertOk()->assertJsonPath('processed', 500)->assertJsonPath('done', false);
        $this->assertDatabaseCount('transaction_history', 500);
        $this->assertSame(1, TransactionEvent::whereNull('transferred_at')->count());

        $this->postJson(route('transaction-events.transfer-selected.process'), [
            'token' => $token,
            'offset' => 500,
            'limit' => 1000,
        ])->assertOk()->assertJsonPath('processed', 501)->assertJsonPath('done', true);

        $this->postJson(route('transaction-events.transfer-selected.finish'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('successCount', 501)
            ->assertJsonPath('createdClients', 501)
            ->assertJsonPath('skippedCount', 0);

        $this->assertDatabaseCount('clients', 502);
        $this->assertDatabaseCount('transaction_history', 501);
        $this->assertSame(501, TransactionHistory::distinct()->count('client_id'));
        $this->assertSame(0, TransactionHistory::where('client_id', '2600001')->count());
        $this->assertSame(501, TransactionHistory::where('source', 'transfer-one')->count());
    }
}
