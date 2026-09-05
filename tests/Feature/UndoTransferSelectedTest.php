<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UndoTransferSelectedTest extends TestCase
{
    use RefreshDatabase;

    private function seedTransferred(string $transactionId, string $name): array
    {
        $historyId = DB::table('transaction_history')->insertGetId([
            'transaction_id' => $transactionId,
            'client_id' => 'C1',
            'transaction_date' => '2026-03-09',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'BIGAY BIGAS SA MASA',
            'events_transaction_type' => 'TRANCH 1',
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = TransactionEvent::create([
            'full_name' => $name,
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => now(),
            'transferred_transaction_id' => $historyId,
        ]);

        return [$event, $historyId];
    }

    public function test_undo_transfer_selected_undoes_batch_and_skips_bad_rows(): void
    {
        $this->actingAs(User::factory()->create());
        [$e1, $h1] = $this->seedTransferred('T-0001', 'Alpha One');
        [$e2, $h2] = $this->seedTransferred('T-0002', 'Beta Two');
        [$e3, $h3] = $this->seedTransferred('T-0003', 'Gamma Three');

        // E3's transaction has requirements -> must be skipped, not deleted.
        DB::table('transaction_requirements')->insert([
            'transaction_id' => $h3,
            'requirement_type' => 'valid_id',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pending (never transferred) event -> skipped.
        $pending = TransactionEvent::create([
            'full_name' => 'Delta Four',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
        ]);

        $response = $this->postJson(route('transaction-events.undo-transfer-selected'), [
            'event_ids' => [$e1->id, $e2->id, $e3->id, $pending->id, 999999],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('undone', 2);
        $response->assertJsonPath('skipped', 3);

        // Undone: transactions gone, events reset to pending.
        $this->assertDatabaseMissing('transaction_history', ['id' => $h1]);
        $this->assertDatabaseMissing('transaction_history', ['id' => $h2]);
        $this->assertDatabaseHas('transaction_events', [
            'id' => $e1->id,
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);
        $this->assertDatabaseHas('transaction_events', [
            'id' => $e2->id,
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);

        // Skipped: requirement-backed transfer untouched.
        $this->assertDatabaseHas('transaction_history', ['id' => $h3]);
        $this->assertNotNull($e3->fresh()->transferred_at);
    }

    public function test_undo_transfer_selected_rejects_empty_selection(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('transaction-events.undo-transfer-selected'), ['event_ids' => []])
            ->assertStatus(422);
    }

    public function test_undo_transfer_selected_forbidden_for_viewers(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));

        $this->postJson(route('transaction-events.undo-transfer-selected'), ['event_ids' => [1]])
            ->assertForbidden();
    }

    public function test_undo_ids_endpoint_resolves_filtered_population(): void
    {
        $this->actingAs(User::factory()->create());
        [$e1] = $this->seedTransferred('T-0001', 'Alpha One');
        [$e2] = $this->seedTransferred('T-0002', 'Beta Two');

        // Pending events must never be included.
        TransactionEvent::create([
            'full_name' => 'Gamma Pending',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
        ]);

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
        ]);
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing(
            [$e1->id, $e2->id],
            $response->json('ids')
        );
    }

    public function test_undo_ids_endpoint_applies_list_filters(): void
    {
        $this->actingAs(User::factory()->create());
        [$e1] = $this->seedTransferred('T-0001', 'Alpha One');
        [$e2] = $this->seedTransferred('T-0002', 'Beta Two');

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'search' => 'Beta',
        ]);
        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $this->assertEquals([$e2->id], $response->json('ids'));
        $this->assertNotContains($e1->id, $response->json('ids'));
    }

    public function test_undo_ids_endpoint_requires_select_all_and_blocks_viewers(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [])
            ->assertStatus(422);

        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $this->postJson(route('transaction-events.undo-transfer-selected.ids'), ['select_all' => 1])
            ->assertForbidden();
    }
}
