<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferToSelectedClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_name' => 'Admin', 'name' => 'Transfer Clerk']));
    }

    private function client(string $clientId, string $firstName, string $lastName, string $sector): Client
    {
        return Client::create([
            'client_id' => $clientId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'sector' => $sector,
            'birth_date' => '1990-01-01',
            'contact' => '09170000000',
            'address' => 'Test Address',
        ]);
    }

    private function event(array $overrides = []): TransactionEvent
    {
        return TransactionEvent::create(array_replace([
            'full_name' => 'Same Name',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'ASSISTANCE',
            'transaction_type' => 'FOOD',
            'event_date' => '2026-09-22',
            'status' => 'Pending',
            'imported_by' => 'Import Clerk',
        ], $overrides));
    }

    public function test_client_search_returns_identifying_details(): void
    {
        $this->client('2600001', 'Juan', 'Cruz', 'INDIGENT');
        $this->client('2600002', 'Juan', 'Cruz', 'PWD');

        $this->getJson(route('transaction-events.clients.search', ['q' => '2600002']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.client_id', '2600002')
            ->assertJsonPath('clients.0.sector', 'PWD')
            ->assertJsonPath('clients.0.birth_date', '1990-01-01');
    }

    public function test_import_events_page_contains_the_client_selection_action_and_modal(): void
    {
        $this->get(route('transaction-events.index'))
            ->assertOk()
            ->assertSee('id="bulkTransferToClientBtn"', false)
            ->assertSee('id="transferToClientModal"', false)
            ->assertSee('Transfer to Selected Client');
    }

    public function test_selected_events_transfer_to_the_exact_chosen_client_id(): void
    {
        $this->client('2600001', 'Same', 'Name', 'INDIGENT');
        $chosen = $this->client('2600002', 'Same', 'Name', 'INDIGENT');
        $first = $this->event(['transaction_type' => 'FOOD']);
        $second = $this->event(['transaction_type' => 'MEDICINE']);

        $response = $this->postJson(route('transaction-events.transfer-to-client'), [
            'event_ids' => [$first->id, $second->id],
            'client_id' => $chosen->client_id,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('transferred', 2)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('client_id', '2600002')
            ->assertJsonPath('redirect', route('transaction-events.index'));

        $this->assertSame([$first->id, $second->id], $response->json('event_ids'));
        $this->assertDatabaseCount('transaction_history', 2);
        $this->assertSame(
            ['2600002'],
            TransactionHistory::query()->distinct()->pluck('client_id')->all()
        );
        $this->assertSame(2, TransactionHistory::where('source', 'selected-client')->count());
        $this->assertNotNull($first->fresh()->transferred_at);
        $this->assertNotNull($second->fresh()->transferred_at);
        $this->assertNotNull($first->fresh()->transferred_transaction_id);
        $this->assertNotNull($second->fresh()->transferred_transaction_id);
    }

    public function test_retry_does_not_create_a_second_transaction(): void
    {
        $client = $this->client('2600001', 'Juan', 'Cruz', 'INDIGENT');
        $event = $this->event();
        $payload = ['event_ids' => [$event->id], 'client_id' => $client->client_id];

        $this->postJson(route('transaction-events.transfer-to-client'), $payload)
            ->assertOk()
            ->assertJsonPath('transferred', 1);

        $this->postJson(route('transaction-events.transfer-to-client'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('transferred', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseCount('transaction_history', 1);
    }

    public function test_transfer_requires_an_existing_client_id(): void
    {
        $event = $this->event();

        $this->postJson(route('transaction-events.transfer-to-client'), [
            'event_ids' => [$event->id],
            'client_id' => 'DOES-NOT-EXIST',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');

        $this->assertDatabaseCount('transaction_history', 0);
        $this->assertNull($event->fresh()->transferred_at);
    }
}
