<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteEventsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPending(string $name, string $clientCategory = 'INDIGENT'): TransactionEvent
    {
        return TransactionEvent::create([
            'full_name' => $name,
            'client_category' => $clientCategory,
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => null,
            'not_duplicate' => false,
        ]);
    }

    public function test_delete_selected_removes_checked_pending_events(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $a = $this->seedPending('Alpha One');
        $b = $this->seedPending('Beta Two');
        $keep = $this->seedPending('Gamma Three');

        $response = $this->delete(route('transaction-events.delete-selected'), [
            'event_ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('transaction_events', ['id' => $a->id]);
        $this->assertDatabaseMissing('transaction_events', ['id' => $b->id]);
        $this->assertDatabaseHas('transaction_events', ['id' => $keep->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'events_bulk_deleted']);
    }

    public function test_delete_all_removes_filtered_population_across_pages(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $this->seedPending('Alpha One', 'INDIGENT');
        $this->seedPending('Beta Two', 'LUPON');
        $keep = $this->seedPending('Gamma Three', 'PWD');

        $response = $this->delete(route('transaction-events.delete-selected'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
            'client_category' => ['INDIGENT', 'LUPON'],
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseMissing('transaction_events', ['full_name' => 'Alpha One']);
        $this->assertDatabaseMissing('transaction_events', ['full_name' => 'Beta Two']);
        $this->assertDatabaseHas('transaction_events', ['id' => $keep->id]);
    }

    public function test_delete_selected_never_removes_transferred_events(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $transferred = TransactionEvent::create([
            'full_name' => 'Already Transferred',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => now(),
            'not_duplicate' => false,
        ]);

        // Even a select-all sweep must leave transferred rows alone.
        $response = $this->delete(route('transaction-events.delete-selected'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseHas('transaction_events', ['id' => $transferred->id]);

        // Explicit ids are constrained to pending rows too.
        $retry = $this->delete(route('transaction-events.delete-selected'), [
            'event_ids' => [$transferred->id],
        ]);
        $retry->assertRedirect(route('transaction-events.index'));
        $retry->assertSessionHas('error');
        $this->assertDatabaseHas('transaction_events', ['id' => $transferred->id]);
    }

    public function test_delete_selected_with_empty_selection_reports_error(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedPending('Alpha One');

        $response = $this->delete(route('transaction-events.delete-selected'), [
            'event_ids' => [],
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('transaction_events', ['full_name' => 'Alpha One']);
    }

    public function test_delete_selected_blocked_for_viewers(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $event = $this->seedPending('Alpha One');

        $response = $this->delete(route('transaction-events.delete-selected'), [
            'event_ids' => [$event->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('transaction_events', ['id' => $event->id]);
    }
}
