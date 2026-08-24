<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\TransactionRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TransactionEventsUndoTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_undo_transfer_removes_only_the_linked_transaction_and_restores_the_event(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');
        $this->actingAs(User::factory()->create());

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $unrelatedTransaction = TransactionHistory::create([
            'client_id' => '2600001',
            'transaction_id' => '2600001-26-0001',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        $event = TransactionEvent::create([
            'full_name' => 'Jane Doe',
            'client_category' => 'PWD',
            'transaction_category' => 'social_services',
            'transaction_type' => 'educational_assistance',
        ]);

        $this->post(route('transaction-events.transfer', $event))
            ->assertRedirect(route('transaction-events.index'));

        $event->refresh();
        $transferredTransactionId = $event->transferred_transaction_id;

        $this->assertNotNull($transferredTransactionId);
        $this->assertDatabaseHas('transaction_history', ['id' => $transferredTransactionId]);

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertSee('Undo Transfer');

        $this->post(route('transaction-events.undo-transfer', $event))
            ->assertRedirect(route('transaction-events.records'))
            ->assertSessionHas('success', 'Transfer undone. Transaction 2600001-26-0002 was removed and the event is pending again.');

        $this->assertDatabaseMissing('transaction_history', ['id' => $transferredTransactionId]);
        $this->assertDatabaseHas('transaction_history', ['id' => $unrelatedTransaction->id]);
        $this->assertDatabaseHas('transaction_events', [
            'id' => $event->id,
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'event_transfer_undone',
            'subject_type' => 'TransactionEvent',
            'subject_id' => $event->id,
        ]);

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertDontSee('Jane Doe');

        $this->get(route('transaction-events.index'))
            ->assertOk()
            ->assertSee('Jane Doe');
    }

    public function test_undo_transfer_uses_the_audit_link_for_legacy_transferred_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = TransactionEvent::create([
            'full_name' => 'Legacy Event',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
            'transferred_at' => now(),
        ]);

        $transaction = TransactionHistory::create([
            'transaction_id' => '2600002-26-0001',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'transaction_created',
            'description' => 'Created transaction from imported event.',
            'subject_type' => 'TransactionHistory',
            'subject_id' => $transaction->id,
            'properties' => json_encode(['event_id' => $event->id]),
        ]);

        $this->post(route('transaction-events.undo-transfer', $event))
            ->assertRedirect(route('transaction-events.records'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('transaction_history', ['id' => $transaction->id]);
        $this->assertDatabaseHas('transaction_events', [
            'id' => $event->id,
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);
    }

    public function test_undo_transfer_keeps_data_unchanged_when_the_linked_transaction_is_missing(): void
    {
        $this->actingAs(User::factory()->create());

        $event = TransactionEvent::create([
            'full_name' => 'Missing Record',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
            'transferred_at' => now(),
        ]);

        $this->post(route('transaction-events.undo-transfer', $event))
            ->assertRedirect(route('transaction-events.records'))
            ->assertSessionHas('error');

        $this->assertNotNull($event->fresh()->transferred_at);
    }

    public function test_undo_transfer_is_blocked_when_the_transaction_has_requirements(): void
    {
        $this->actingAs(User::factory()->create());

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $event = TransactionEvent::create([
            'full_name' => 'Jane Doe',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
        ]);

        $this->post(route('transaction-events.transfer', $event));

        $event->refresh();
        $transaction = TransactionHistory::findOrFail($event->transferred_transaction_id);

        TransactionRequirement::create([
            'transaction_id' => $transaction->id,
            'requirement_type' => 'valid_id',
        ]);

        $this->post(route('transaction-events.undo-transfer', $event))
            ->assertRedirect(route('transaction-events.records'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('transaction_history', ['id' => $transaction->id]);
        $this->assertNotNull($event->fresh()->transferred_at);
    }

    public function test_viewers_cannot_undo_transferred_events(): void
    {
        $viewer = User::factory()->create(['role_name' => 'Viewer']);
        $event = TransactionEvent::create([
            'full_name' => 'Read Only User',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
            'transferred_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->post(route('transaction-events.undo-transfer', $event))
            ->assertForbidden();
    }
}
