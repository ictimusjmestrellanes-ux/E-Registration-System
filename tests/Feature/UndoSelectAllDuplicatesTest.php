<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UndoSelectAllDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private function seedTransferred(
        string $transactionId,
        string $name,
        string $clientCategory = 'INDIGENT',
        string $transactionCategory = 'BIGAY BIGAS SA MASA',
        string $transactionType = 'TRANCH 1',
        string $eventDate = '2026-03-09',
    ): TransactionEvent {
        $historyId = DB::table('transaction_history')->insertGetId([
            'transaction_id' => $transactionId,
            'client_id' => 'C1',
            'transaction_date' => '2026-03-09',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'BIGAY BIGAS SA MASA',
            'events_transaction_type' => 'TRANCH 1',
            'status' => 'Claimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TransactionEvent::create([
            'full_name' => $name,
            'client_category' => $clientCategory,
            'transaction_category' => $transactionCategory,
            'transaction_type' => $transactionType,
            'event_date' => $eventDate,
            'transferred_at' => now(),
            'transferred_transaction_id' => $historyId,
        ]);
    }

    public function test_select_all_with_exclude_duplicates_skips_five_field_matches(): void
    {
        $this->actingAs(User::factory()->create());

        $dupA = $this->seedTransferred('T-0001', 'Juan Dela Cruz');
        $dupB = $this->seedTransferred('T-0002', 'Juan Dela Cruz');
        $unique = $this->seedTransferred('T-0003', 'Maria Santos');

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total', 1);
        $this->assertEquals([$unique->id], $response->json('ids'));
        $this->assertNotContains($dupA->id, $response->json('ids'));
        $this->assertNotContains($dupB->id, $response->json('ids'));
    }

    public function test_select_all_without_exclusion_includes_and_can_undo_duplicates(): void
    {
        $this->actingAs(User::factory()->create());

        $dupA = $this->seedTransferred('T-0001', 'Juan Dela Cruz');
        $dupB = $this->seedTransferred('T-0002', 'Juan Dela Cruz');

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([$dupA->id, $dupB->id], $response->json('ids'));

        $undo = $this->postJson(route('transaction-events.undo-transfer-selected'), [
            'event_ids' => [$dupA->id, $dupB->id],
        ]);

        $undo->assertOk()->assertJsonPath('undone', 2);
        $this->assertNull($dupA->fresh()->transferred_at);
        $this->assertNull($dupB->fresh()->transferred_at);
    }

    public function test_rows_differing_in_any_of_the_five_fields_are_not_duplicates(): void
    {
        $this->actingAs(User::factory()->create());

        $base = $this->seedTransferred('T-0001', 'Juan Dela Cruz');
        $otherDate = $this->seedTransferred('T-0002', 'Juan Dela Cruz', 'INDIGENT', 'BIGAY BIGAS SA MASA', 'TRANCH 1', '2026-03-10');
        $otherType = $this->seedTransferred('T-0003', 'Juan Dela Cruz', 'INDIGENT', 'BIGAY BIGAS SA MASA', 'TRANCH 2', '2026-03-09');

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing(
            [$base->id, $otherDate->id, $otherType->id],
            $response->json('ids')
        );
    }

    public function test_records_page_allows_duplicate_rows_to_be_selected(): void
    {
        $this->actingAs(User::factory()->create());

        $dupA = $this->seedTransferred('T-0001', 'Juan Dela Cruz');
        $this->seedTransferred('T-0002', 'Juan Dela Cruz');
        $unique = $this->seedTransferred('T-0003', 'Maria Santos');

        $response = $this->get(route('transaction-events.records', ['per_page' => 100]));

        $response->assertOk();
        $response->assertDontSee('data-duplicate="1"', false);
        $response->assertSee('value="'.$dupA->id.'"', false);
        $response->assertSee('value="'.$unique->id.'"', false);
    }

    public function test_reviewed_not_duplicate_record_is_selectable_even_when_unresolved_matches_remain(): void
    {
        $this->actingAs(User::factory()->create());

        $unresolvedA = $this->seedTransferred('T-0001', 'Juan Dela Cruz');
        $unresolvedB = $this->seedTransferred('T-0002', 'Juan Dela Cruz');
        $reviewed = $this->seedTransferred('T-0003', 'Juan Dela Cruz');
        $reviewed->update(['not_duplicate' => true]);

        $recordsResponse = $this->get(route('transaction-events.records', ['per_page' => 100]));

        $recordsResponse->assertOk();
        $recordsResponse->assertSee('value="'.$unresolvedA->id.'"', false);
        $recordsResponse->assertSee('value="'.$unresolvedB->id.'"', false);
        $recordsResponse->assertSee('value="'.$reviewed->id.'"', false);

        $idsResponse = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
        ]);

        $idsResponse->assertOk();
        $this->assertSame([$reviewed->id], $idsResponse->json('ids'));

        $undoResponse = $this->postJson(route('transaction-events.undo-transfer-selected'), [
            'event_ids' => [$reviewed->id],
        ]);

        $undoResponse->assertOk();
        $undoResponse->assertJsonPath('undone', 1);
        $this->assertNull($reviewed->fresh()->transferred_at);
    }
}
