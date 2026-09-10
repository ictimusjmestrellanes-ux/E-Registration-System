<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_can_be_edited_and_returns_to_the_same_list_context(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = TransactionEvent::create(['full_name' => 'Original Name', 'transferred_at' => now()]);
        $context = ['search' => 'Name', 'page' => 2, 'per_page' => 25];
        $this->get(route('transaction-events.records'))->assertOk()
            ->assertSee('data-bs-target="#editRecordModal"', false)
            ->assertSee(route('transaction-events.records.update', $event))
            ->assertSee('Original Name');

        $this->put(route('transaction-events.records.update', $context + ['event' => $event->id]), [
            'full_name' => 'Updated Name', 'contact_no' => '09123456789',
            'age' => 35, 'birth_date' => '1991-01-01', 'address' => 'Updated Address',
            'client_category' => 'INDIGENT', 'transaction_category' => 'ASSISTANCE',
            'transaction_type' => 'TRANCH 2', 'event_date' => '2026-09-10',
            'transferred_at' => null,
        ])->assertRedirect(route('transaction-events.records', $context))
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('Updated Name', $event->full_name);
        $this->assertSame('TRANCH 2', $event->transaction_type);
        $this->assertSame('2026-09-10', $event->event_date->format('Y-m-d'));
        $this->assertNotNull($event->transferred_at);
        $this->get(route('transaction-events.records'))->assertSee('Updated Name');
    }

    public function test_invalid_record_changes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = TransactionEvent::create(['full_name' => 'Original Name', 'transferred_at' => now()]);
        $listUrl = route('transaction-events.records', ['per_page' => 25]);
        $this->from($listUrl)->put(route('transaction-events.records.update', $event), [
            'full_name' => '', 'age' => -1, 'event_date' => 'invalid', 'edit_record_id' => $event->id,
        ])->assertRedirect($listUrl)->assertSessionHasErrors(['full_name', 'age', 'event_date']);
        $this->get($listUrl)->assertOk()
            ->assertSee('bootstrap.Modal.getOrCreateInstance(editModal).show();', false)
            ->assertSee('value="-1"', false);
        $this->assertSame('Original Name', $event->fresh()->full_name);
    }

    public function test_viewers_cannot_edit_and_pending_events_are_not_editable_as_records(): void
    {
        $event = TransactionEvent::create(['full_name' => 'Original Name', 'transferred_at' => now()]);
        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $this->get(route('transaction-events.records'))->assertOk()
            ->assertDontSee('data-bs-target="#editRecordModal"', false);
        $this->put(route('transaction-events.records.update', $event), ['full_name' => 'Changed'])->assertForbidden();

        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $pending = TransactionEvent::create(['full_name' => 'Pending']);
        $this->put(route('transaction-events.records.update', $pending), ['full_name' => 'Changed'])->assertNotFound();
    }
}
