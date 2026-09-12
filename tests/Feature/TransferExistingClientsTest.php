<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferExistingClientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Client::create(['client_id' => '2600001', 'first_name' => 'Jane', 'last_name' => 'Doe']);
    }

    private function event(string $name): TransactionEvent
    {
        return TransactionEvent::create([
            'full_name' => $name,
            'client_category' => 'PWD',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
            'event_date' => '2026-09-01',
        ]);
    }

    private function assertPending(TransactionEvent $event): void
    {
        $this->assertNull($event->fresh()->transferred_at);
        $this->assertNull($event->fresh()->transferred_transaction_id);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_single_transfer_keeps_unknown_client_pending(): void
    {
        $event = $this->event('Jane Smith');
        $this->post(route('transaction-events.transfer', $event))
            ->assertRedirect(route('transaction-events.index'))->assertSessionHas('error');
        $this->assertPending($event);
        $this->assertDatabaseCount('transaction_history', 0);
    }

    public function test_single_transfer_links_existing_client(): void
    {
        $event = $this->event('Jane Doe');
        $this->post(route('transaction-events.transfer', $event))
            ->assertRedirect(route('transaction-events.records'))->assertSessionHas('success');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseHas('transaction_history', [
            'id' => $event->fresh()->transferred_transaction_id,
            'client_id' => '2600001',
            'transaction_date' => '2026-09-01 00:00:00',
        ]);
    }

    public function test_transfer_does_not_match_conflicting_birth_date(): void
    {
        Client::where('client_id', '2600001')->update(['birth_date' => '1990-01-01']);
        $event = $this->event('Jane Doe');
        $event->update(['birth_date' => '1991-01-01']);
        $this->post(route('transaction-events.transfer', $event))
            ->assertRedirect(route('transaction-events.index'))->assertSessionHas('error');
        $this->assertPending($event);
        $this->assertDatabaseCount('transaction_history', 0);
    }

    public function test_json_transfer_skips_unknown_and_reuses_existing_client(): void
    {
        $unknown = $this->event('Jane Smith');
        $known = $this->event('jane   doe');
        $this->postJson(route('transaction-events.transfer-one'), ['event_id' => $unknown->id])
            ->assertStatus(422)->assertJson(['success' => false, 'created_client' => false]);
        $this->postJson(route('transaction-events.transfer-one'), ['event_id' => $known->id])
            ->assertOk()->assertJson(['success' => true, 'created_client' => false]);
        $this->assertPending($unknown);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseHas('transaction_history', ['client_id' => '2600001']);
        $this->assertNotNull($known->fresh()->transferred_transaction_id);
    }

    public function test_form_selected_and_select_all_create_transactions_for_every_row(): void
    {
        foreach ([false, true] as $selectAll) {
            $known = $this->event('Jane Doe');
            $unknown = $this->event('Unknown Person');
            $payload = $selectAll ? ['select_all' => 1] : ['event_ids' => [$known->id, $unknown->id]];
            $this->post(route('transaction-events.transfer-selected'), $payload)
                ->assertRedirect()->assertSessionHas('success');
            $this->assertNotNull($unknown->fresh()->transferred_transaction_id);
            $this->assertNotNull($known->fresh()->transferred_transaction_id);
        }
        $this->assertDatabaseCount('transaction_history', 4);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_chunked_select_all_reuses_matches_and_creates_unknown_clients(): void
    {
        $known = $this->event('Jane Doe');
        $unknown = $this->event('Unknown Person');
        $prepare = $this->postJson(route('transaction-events.transfer-selected.prepare'), ['select_all' => 1]);
        $prepare->assertOk()->assertJson(['total' => 2]);
        $token = $prepare->json('token');
        foreach ([0, 1] as $offset) {
            $this->postJson(route('transaction-events.transfer-selected.process'), [
                'token' => $token, 'offset' => $offset, 'limit' => 1,
            ])->assertOk();
        }
        $this->postJson(route('transaction-events.transfer-selected.finish'), ['token' => $token])
            ->assertOk()->assertJson(['successCount' => 2, 'skippedCount' => 0, 'createdClients' => 1]);
        $this->assertNotNull($unknown->fresh()->transferred_transaction_id);
        $this->assertNotNull($known->fresh()->transferred_transaction_id);
        $this->assertDatabaseCount('transaction_history', 2);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_chunked_selected_with_no_matches_creates_client_and_transaction(): void
    {
        $event = $this->event('Unknown Person');
        $prepare = $this->postJson(route('transaction-events.transfer-selected.prepare'), ['event_ids' => [$event->id]]);
        $prepare->assertOk();
        $token = $prepare->json('token');
        $this->postJson(route('transaction-events.transfer-selected.process'), ['token' => $token, 'offset' => 0])
            ->assertOk();
        $this->postJson(route('transaction-events.transfer-selected.finish'), ['token' => $token])
            ->assertOk()->assertJson(['successCount' => 1, 'skippedCount' => 0, 'createdClients' => 1,
                'redirect' => route('transaction-events.records')]);
        $this->assertNotNull($event->fresh()->transferred_transaction_id);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_selected_events_append_distinct_history_entries_and_retries_do_not_duplicate(): void
    {
        $first = $this->event('Jane Doe');
        $second = $this->event('Jane Doe');
        $this->post(route('transaction-events.transfer-selected'), ['event_ids' => [$first->id]])->assertRedirect();
        $firstHistory = \App\Models\TransactionHistory::findOrFail($first->fresh()->transferred_transaction_id);
        $original = $firstHistory->getAttributes();

        $this->post(route('transaction-events.transfer-selected'), ['event_ids' => [$first->id, $second->id]])->assertRedirect();
        $secondHistory = \App\Models\TransactionHistory::findOrFail($second->fresh()->transferred_transaction_id);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 2);
        $this->assertSame($original, $firstHistory->fresh()->getAttributes());
        $this->assertNotSame($firstHistory->transaction_id, $secondHistory->transaction_id);
        $this->assertSame('2600001', $secondHistory->client_id);
        $this->assertSame('Approved', $secondHistory->status);
        $this->assertSame('transfer', $secondHistory->source);
        $this->assertSame('social_services', $secondHistory->category);
        $this->assertSame('burial_assistance', $secondHistory->type);
        $this->assertSame('2026-09-01', $secondHistory->transaction_date->format('Y-m-d'));
    }
}
