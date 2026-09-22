<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordStatusTest extends TestCase
{
    use RefreshDatabase;

    private function record(): TransactionEvent
    {
        $history = TransactionHistory::create([
            'client_id' => 'C1', 'transaction_id' => 'STATUS-1',
            'transaction_date' => '2026-09-14', 'category' => 'EVENTS',
            'type' => 'TRANCH 1', 'events_transaction_type' => 'TRANCH 1', 'status' => 'Pending',
        ]);

        return TransactionEvent::create([
            'full_name' => 'Juan Santos', 'transaction_type' => 'TRANCH 1',
            'transferred_at' => now(), 'transferred_transaction_id' => $history->id,
        ]);
    }

    public function test_dropdown_updates_each_status_and_history_without_changing_type(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = $this->record();
        $this->get(route('transaction-events.records'))->assertOk()
            ->assertSee('Tag as Pending')->assertSee('Tag as Claimed')->assertSee('Tag as Unclaimed');

        foreach (['Claimed', 'Unclaimed', 'Pending'] as $status) {
            $this->patch(route('transaction-events.records.status', ['event' => $event, 'page' => 2]), ['status' => $status])
                ->assertRedirect(route('transaction-events.records', ['page' => 2]))->assertSessionHas('success');
            $this->assertDatabaseHas('transaction_events', [
                'id' => $event->id, 'status' => $status, 'transaction_type' => 'TRANCH 1',
            ]);
            $this->assertDatabaseHas('transaction_history', [
                'id' => $event->transferred_transaction_id, 'status' => $status,
                'type' => 'TRANCH 1', 'events_transaction_type' => 'TRANCH 1',
            ]);
            $transactionsPage = $this->get(route('transactions.index'))->assertOk();
            $this->assertSame($status, $transactionsPage->viewData('transactions')->first()->status);
            $this->assertSame(
                1,
                $this->get(route('transactions.index', ['status' => $status]))
                    ->assertOk()->viewData('transactions')->total()
            );
        }
    }

    public function test_invalid_status_and_untransferred_record_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = $this->record();
        $this->patchJson(route('transaction-events.records.status', $event), ['status' => 'Invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $event->update(['transferred_at' => null]);
        $this->patchJson(route('transaction-events.records.status', $event), ['status' => 'Claimed'])->assertNotFound();
        $this->assertSame('Pending', $event->fresh()->status);
        $this->assertSame('Pending', $event->transferredTransaction->status);
    }

    public function test_tag_selected_updates_the_all_transactions_status(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = $this->record();

        $this->postJson(route('transaction-events.records.status-selected'), [
            'event_ids' => [$event->id],
            'status' => 'Unclaimed',
        ])->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('processed_ids.0', $event->id);

        $transactionsPage = $this->get(route('transactions.index'))->assertOk();
        $this->assertSame('Unclaimed', $transactionsPage->viewData('transactions')->first()->status);
    }

    public function test_single_tag_returns_json_for_an_in_place_page_update(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = $this->record();

        $this->patchJson(route('transaction-events.records.status', $event), ['status' => 'Claimed'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('status', 'Claimed');

        $this->assertSame('Claimed', $event->fresh()->status);
        $this->assertSame('Claimed', $event->fresh()->transferredTransaction->status);
    }

    public function test_duplicate_tabs_can_tag_one_record_and_keep_the_current_page_and_filters(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = $this->record();
        $otherHistory = $event->transferredTransaction->replicate();
        $otherHistory->transaction_id = 'STATUS-2';
        $otherHistory->save();
        $other = $event->replicate();
        $other->transferred_transaction_id = $otherHistory->id;
        $other->save();

        foreach (['exact' => 'Claimed', 'likely' => 'Unclaimed', 'full_name' => 'Pending'] as $tab => $status) {
            $other->update(['transaction_type' => $tab === 'likely' ? 'TRANCH 2' : 'TRANCH 1']);
            $query = ['duplicate_tab' => $tab, 'search' => 'Juan', 'per_page' => 10];
            $this->get(route('transaction-events.records-duplicates', $query))->assertOk()
                ->assertSee('Tag as Pending')->assertSee('Tag as Claimed')->assertSee('Tag as Unclaimed');
            $pageQuery = $query + ['exact_page' => 2, 'likely_page' => 3, 'similar_page' => 4];
            $this->patch(route('transaction-events.records.status', ['event' => $event] + $pageQuery), ['status' => $status])
                ->assertRedirect(route('transaction-events.records-duplicates', $pageQuery))->assertSessionHas('success');
            $this->assertSame($status, $event->fresh()->status);
            $this->assertSame($status, $event->fresh()->transferredTransaction->status);
            $this->assertSame('Pending', $other->fresh()->status);
            $this->assertSame('Pending', $otherHistory->fresh()->status);
            $this->get(route('transaction-events.records-duplicates', $query))->assertOk()
                ->assertSee('>'.$status.'</span>', false);
        }
        $this->assertSame(3, \App\Models\ActivityLog::where('action', 'transaction_event_updated')
            ->where('subject_id', $event->id)->count());
    }

    public function test_status_filter_combines_with_search_and_preserves_pagination(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        foreach (TransactionEvent::STATUSES as $status) {
            for ($i = 0; $i < 12; $i++) {
                TransactionEvent::create(['full_name' => 'Juan '.$i, 'status' => $status, 'transferred_at' => now()]);
            }
            TransactionEvent::create(['full_name' => 'Other', 'status' => $status, 'transferred_at' => now()]);
            TransactionEvent::create(['full_name' => 'Juan staged', 'status' => $status]);
        }

        foreach (TransactionEvent::STATUSES as $status) {
            $response = $this->get(route('transaction-events.records', ['status' => $status, 'search' => 'Juan', 'page' => 2]))
                ->assertOk()->assertSee('All statuses');
            $events = $response->viewData('events');
            $this->assertSame(12, $events->total());
            $this->assertCount(2, $events);
            $this->assertSame([$status], $events->pluck('status')->unique()->values()->all());
            $this->assertStringContainsString('status='.$status, $events->url(1));
        }
        $this->assertSame(39, $this->get(route('transaction-events.records', ['status' => '']))
            ->assertOk()->viewData('events')->total());
    }

    public function test_viewer_cannot_tag_records(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $event = $this->record();
        $this->patch(route('transaction-events.records.status', $event), ['status' => 'Claimed'])->assertForbidden();
        $this->assertSame('Pending', $event->fresh()->status);
    }

    public function test_missing_history_does_not_partially_update_event(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = $this->record();
        $event->transferredTransaction->delete();
        $this->patch(route('transaction-events.records.status', $event), ['status' => 'Claimed'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('Pending', $event->fresh()->status);
    }
}
