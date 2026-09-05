<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferReuseTest extends TestCase
{
    use RefreshDatabase;

    private function seedPendingEvent(array $overrides = []): TransactionEvent
    {
        return TransactionEvent::create(array_merge([
            'full_name' => 'Juan Dela Cruz',
            'contact_no' => '09170000001',
            'address' => 'Brgy 1',
            'age' => 40,
            'birth_date' => '1986-05-05',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
        ], $overrides));
    }

    public function test_transfer_reuses_existing_client(): void
    {
        $this->actingAs(User::factory()->create());

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'birth_date' => '1986-05-05',
            'sector' => 'INDIGENT',
        ]);
        $event = $this->seedPendingEvent();

        $response = $this->post(route('transaction-events.transfer', $event));
        $response->assertRedirect(route('transaction-events.records'));

        // No duplicate client created; transaction appended to existing one.
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => '2600001',
            'client_category' => 'INDIGENT',
        ]);
        $this->assertNotNull($event->fresh()->transferred_at);
        $this->assertNotNull($event->fresh()->transferred_transaction_id);
    }

    public function test_transfer_reuses_system_layout_client(): void
    {
        $this->actingAs(User::factory()->create());

        // Client as created by the system itself (surname split across columns).
        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'birth_date' => '1986-05-05',
            'sector' => 'INDIGENT',
        ]);
        $event = $this->seedPendingEvent();

        $response = $this->post(route('transaction-events.transfer', $event));
        $response->assertRedirect(route('transaction-events.records'));

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('transaction_history', ['client_id' => '2600001']);
        $this->assertNotNull($event->fresh()->transferred_at);
    }

    public function test_transfer_creates_client_when_none_exists(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->seedPendingEvent();

        $response = $this->post(route('transaction-events.transfer', $event));
        $response->assertRedirect(route('transaction-events.records'));

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertNotNull($event->fresh()->transferred_at);
    }

    public function test_transfer_one_json_reuses_and_reports(): void
    {
        $this->actingAs(User::factory()->create());

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'birth_date' => '1986-05-05',
            'sector' => 'INDIGENT',
        ]);
        $event = $this->seedPendingEvent();

        $response = $this->postJson(route('transaction-events.transfer-one'), [
            'event_id' => $event->id,
        ]);
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('created_client', false);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 1);
    }

    public function test_transfer_one_creates_client_for_unknown_person(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->seedPendingEvent();

        $response = $this->postJson(route('transaction-events.transfer-one'), [
            'event_id' => $event->id,
        ]);
        $response->assertOk();
        $response->assertJsonPath('created_client', true);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_transfer_one_rejects_bad_requests(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('transaction-events.transfer-one'), ['event_id' => 999999])
            ->assertStatus(404);

        $event = $this->seedPendingEvent();
        $event->update(['transferred_at' => now()]);
        $this->postJson(route('transaction-events.transfer-one'), ['event_id' => $event->id])
            ->assertStatus(422);

        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $this->postJson(route('transaction-events.transfer-one'), ['event_id' => $event->id])
            ->assertForbidden();
    }

    public function test_transfer_selected_reuses_existing_client(): void
    {
        $this->actingAs(User::factory()->create());

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'birth_date' => '1986-05-05',
            'sector' => 'INDIGENT',
        ]);
        $e1 = $this->seedPendingEvent();
        $e2 = $this->seedPendingEvent(['client_category' => 'LUPON']);

        $response = $this->post(route('transaction-events.transfer-selected'), [
            'event_ids' => [$e1->id, $e2->id],
        ]);
        $response->assertRedirect();

        // Both transactions land in the existing client's history.
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 2);
        $this->assertDatabaseHas('transaction_history', ['client_id' => '2600001']);
        $this->assertNotNull($e1->fresh()->transferred_at);
        $this->assertNotNull($e2->fresh()->transferred_at);
    }

    public function test_transfer_selected_creates_clients_for_unknown_people(): void
    {
        $this->actingAs(User::factory()->create());

        $e1 = $this->seedPendingEvent();
        $e2 = $this->seedPendingEvent([
            'full_name' => 'Maria Santos Reyes',
            'birth_date' => '1990-02-02',
        ]);

        $response = $this->post(route('transaction-events.transfer-selected'), [
            'event_ids' => [$e1->id, $e2->id],
        ]);
        $response->assertRedirect();

        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('transaction_history', 2);
    }
}
