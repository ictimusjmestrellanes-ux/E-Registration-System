<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventRecordDuplicatesPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_pages_load_only_visible_groups_with_bounded_queries(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $rows = [];
        for ($group = 0; $group < 35; $group++) {
            foreach ([0, 1] as $copy) {
                $history = DB::table('transaction_history')->insertGetId([
                    'transaction_id' => 'DUP-'.$group.'-'.$copy, 'client_id' => 'C1',
                    'transaction_date' => '2026-09-01', 'type' => 'TYPE', 'category' => 'EVENTS', 'status' => 'Approved',
                ]);
                $rows[] = [
                    'full_name' => ($copy ? '  PERSON ' : 'person ').$group.($copy ? '  ' : ''),
                    'event_date' => '2026-09-01', 'client_category' => 'PWD',
                    'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                    'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $history,
                ];
            }
        }
        DB::table('transaction_events')->insert($rows);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('transaction-events.records-duplicates', [
            'per_page' => 10, 'exact_page' => 2, 'likely_page' => 3,
        ]))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], 'transaction_events') || str_contains($query['query'], 'transaction_history'));
        DB::disableQueryLog();

        $exact = $response->viewData('exactGroups');
        $likely = $response->viewData('likelyGroups');
        $this->assertSame(35, $exact->total());
        $this->assertSame(210, $likely->total());
        $this->assertCount(10, $exact);
        $this->assertCount(10, $likely);
        $this->assertSame(2, $exact->currentPage());
        $this->assertSame(3, $likely->currentPage());
        $this->assertStringContainsString('likely_page=4', $likely->nextPageUrl());
        $this->assertStringContainsString('exact_page=2', $likely->nextPageUrl());
        $this->assertSame(70, $response->viewData('exactRecordsTotal'));
        foreach ([$exact, $likely] as $paginator) {
            foreach ($paginator as $group) {
                $this->assertCount(2, $group['events']);
                foreach ($group['events'] as $event) {
                    $this->assertTrue($event->relationLoaded('transferredTransaction'));
                }
            }
        }
        $this->assertLessThanOrEqual(11, $queries->count(), 'Duplicate queries must not grow with group count.');
    }

    public function test_blank_group_fields_cannot_include_unrelated_or_pending_events(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        foreach (['Alpha', 'Alpha', 'Unrelated'] as $name) {
            DB::table('transaction_events')->insert([
                'full_name' => $name, 'client_category' => '', 'transaction_type' => '',
                'transferred_at' => '2026-09-01 12:00:00',
            ]);
        }
        DB::table('transaction_events')->insert(['full_name' => 'Alpha', 'client_category' => '', 'transaction_type' => '']);
        $response = $this->get(route('transaction-events.records-duplicates'))->assertOk();
        $this->assertSame(1, $response->viewData('exactGroups')->total());
        foreach (['exactGroups', 'likelyGroups'] as $key) {
            foreach ($response->viewData($key) as $group) {
                $this->assertCount(2, $group['events']);
                foreach ($group['events'] as $event) {
                    $this->assertSame('Alpha', $event->full_name);
                    $this->assertNotNull($event->transferred_at);
                }
            }
        }
    }
}
