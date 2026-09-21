<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventRecordDuplicatesPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_duplicate_sets_stay_together_and_records_are_not_repeated(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        for ($client = 0; $client < 12; $client++) {
            foreach (['2026-09-01', '2026-09-02'] as $date) {
                for ($copy = 0; $copy < 2; $copy++) {
                    DB::table('transaction_events')->insert([
                        'full_name' => 'Client '.$client, 'birth_date' => '1990-01-01',
                        'event_date' => $date, 'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                        'transferred_at' => '2026-09-03 12:00:00',
                    ]);
                }
            }
        }
        $response = $this->get(route('transaction-events.records-duplicates', [
            'exact_page' => 2, 'likely_page' => 2, 'similar_page' => 2, 'per_page' => 10,
        ]))->assertOk()->assertSee('Each client appears once per tab');
        foreach (['exactGroups' => 'exactRecordsTotal', 'likelyGroups' => 'likelyRecordsTotal', 'similarGroups' => 'similarRecordsTotal'] as $key => $total) {
            $groups = $response->viewData($key);
            $this->assertSame(12, $groups->total());
            $this->assertCount(2, $groups);
            $this->assertSame(48, $response->viewData($total));
            foreach ($groups as $group) {
                $this->assertSame(4, $group['total']);
                $this->assertCount(4, $group['events']);
                $this->assertCount(4, $group['events']->pluck('id')->unique());
                $this->assertCount(1, $group['events']->pluck('full_name')->unique());
            }
        }
    }

    public function test_status_filter_applies_to_counts_and_members_in_every_duplicate_tab(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        foreach (['Pending', 'Claimed', 'Unclaimed'] as $status) {
            foreach (['TYPE-A', 'TYPE-A', 'TYPE-B'] as $type) {
                DB::table('transaction_events')->insert([
                    'full_name' => 'Juan Santos', 'status' => $status,
                    'event_date' => '2026-09-01',
                    'transaction_type' => $type, 'transferred_at' => '2026-09-01 12:00:00',
                ]);
            }
            DB::table('transaction_events')->insert(['full_name' => 'Juan Santos', 'status' => $status, 'transaction_type' => 'TYPE-A']);
        }
        foreach (['Pending', 'Claimed', 'Unclaimed'] as $status) {
            $response = $this->get(route('transaction-events.records-duplicates', ['status' => $status, 'search' => 'Juan']))
                ->assertOk()->assertSee('All statuses')->assertSee('Filtered groups are shown below.');
            $this->assertSame(1, $response->viewData('exactGroups')->total());
            $this->assertSame(2, $response->viewData('exactRecordsTotal'));
            $this->assertSame(3, $response->viewData('similarRecordsTotal'));
            foreach (['exactGroups', 'likelyGroups', 'similarGroups'] as $key) {
                $groups = $response->viewData($key);
                $this->assertGreaterThan(0, $groups->total());
                $this->assertStringContainsString('status='.$status, $groups->url(2));
                foreach ($groups as $group) {
                    $this->assertCount($group['total'], $group['events']);
                    $this->assertSame([$status], $group['events']->pluck('status')->unique()->values()->all());
                    $this->assertTrue($group['events']->every(fn ($event) => $event->transferred_at !== null));
                }
            }
        }
        $response = $this->get(route('transaction-events.records-duplicates', ['status' => '']))->assertOk();
        $this->assertSame(9, $response->viewData('exactRecordsTotal'));
    }

    public function test_exact_match_uses_first_last_names_and_event_fields_except_birth_date(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $base = [
            'full_name' => 'Juan A. Dela Cruz', 'birth_date' => '1990-01-01',
            'client_category' => 'PWD', 'sector' => 'Education',
            'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
            'event_date' => '2026-09-01', 'transferred_at' => '2026-09-01 12:00:00',
        ];
        $expected = [DB::table('transaction_events')->insertGetId($base)];
        $expected[] = DB::table('transaction_events')->insertGetId(array_replace($base, [
            'full_name' => '  DELA CRUZ, JUAN B. JR.  ', 'sector' => ' education ',
            'client_category' => 'pwd', 'transaction_category' => 'events', 'transaction_type' => 'type',
        ]));
        $expected[] = DB::table('transaction_events')->insertGetId(array_replace($base, ['sector' => 'Health']));
        $expected[] = DB::table('transaction_events')->insertGetId(array_replace($base, ['birth_date' => '2000-02-02']));
        foreach ([
            'full_name' => 'Pedro A. Dela Cruz',
            'client_category' => 'SENIOR',
            'transaction_category' => 'OTHER', 'transaction_type' => 'OTHER',
            'event_date' => '2026-09-02', 'transferred_at' => null,
        ] as $field => $value) {
            DB::table('transaction_events')->insert(array_replace($base, [$field => $value]));
        }
        DB::table('transaction_events')->insert(array_replace($base, ['full_name' => 'Juan A. Santos']));

        $response = $this->get(route('transaction-events.records-duplicates'))->assertOk()
            ->assertSee('Lastname and Firstname')->assertDontSee('<strong>Sector</strong>', false);
        $groups = $response->viewData('exactGroups');
        $this->assertSame(1, $groups->total());
        $this->assertSame(4, $response->viewData('exactRecordsTotal'));
        $this->assertSame($expected, $groups->first()['events']->pluck('id')->sort()->values()->all());
    }

    public function test_match_full_name_uses_only_name_and_excludes_similar_spellings_and_pending_rows(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        DB::table('transaction_events')->insert([
            [
                'full_name' => 'Juan Escobar', 'birth_date' => '1990-01-01', 'event_date' => '2026-09-01',
                'client_category' => 'PWD', 'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE-A',
                'transferred_at' => '2026-09-01 12:00:00',
            ],
            [
                'full_name' => '  JUAN ESCOBAR  ', 'birth_date' => '2000-02-02', 'event_date' => '2026-09-02',
                'client_category' => 'SENIOR', 'transaction_category' => 'OTHER', 'transaction_type' => 'TYPE-B',
                'transferred_at' => '2026-09-02 12:00:00',
            ],
        ]);
        DB::table('transaction_events')->insert([
            ['full_name' => 'Juan Iscober', 'transferred_at' => '2026-09-01 12:00:00'],
            ['full_name' => 'Juan Escobar', 'transferred_at' => null],
        ]);

        $response = $this->get(route('transaction-events.records-duplicates', ['duplicate_tab' => 'full_name']))
            ->assertOk()->assertSee('Match Full Name')->assertDontSee('Similar Spelling');
        $this->assertSame(0, $response->viewData('exactGroups')->total());
        $this->assertSame(0, $response->viewData('likelyGroups')->total());
        $groups = $response->viewData('similarGroups');
        $this->assertSame(1, $groups->total());
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
        $this->assertCount(2, $groups->first()['events']);
        foreach ($groups->first()['events'] as $event) {
            $this->assertSame('juan escobar', strtolower(trim($event->full_name)));
            $this->assertNotNull($event->transferred_at);
        }

        $filtered = $this->get(route('transaction-events.records-duplicates', ['client_category' => ['PWD']]))
            ->assertOk();
        $this->assertSame(0, $filtered->viewData('similarGroups')->total());
    }

    public function test_match_full_name_pagination_keeps_tab_active(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        for ($group = 0; $group < 12; $group++) {
            foreach ([0, 1] as $copy) {
                DB::table('transaction_events')->insert([
                    'full_name' => 'Person '.$group,
                    'transferred_at' => '2026-09-01 12:00:00',
                ]);
            }
        }
        $response = $this->get(route('transaction-events.records-duplicates', ['similar_page' => 2]))
            ->assertOk()->assertSee('tab-pane fade show active" id="rsimilar-tab', false);
        $groups = $response->viewData('similarGroups');
        $this->assertSame(12, $groups->total());
        $this->assertCount(2, $groups);
        $this->assertSame(24, $response->viewData('similarRecordsTotal'));
        $this->assertStringContainsString('duplicate_tab=full_name', $groups->url(1));
    }

    public function test_duplicate_pages_load_only_visible_groups_with_bounded_queries(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $rows = [];
        for ($group = 0; $group < 35; $group++) {
            foreach ([0, 1] as $copy) {
                $history = DB::table('transaction_history')->insertGetId([
                    'transaction_id' => 'DUP-'.$group.'-'.$copy, 'client_id' => 'C1',
                    'transaction_date' => '2026-09-01', 'type' => 'TYPE', 'category' => 'EVENTS', 'status' => 'Claimed',
                ]);
                $rows[] = [
                    'full_name' => ($copy ? '  PERSON ' : 'person ').$group.($copy ? '  ' : ''),
                    'event_date' => '2026-09-01', 'client_category' => 'PWD',
                    'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                    'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $history,
                ];
            }
        }
        // Likely-only pairs: same name + date + category, but different
        // client category and transaction type, so they never form an
        // exact group yet match three likely patterns each.
        foreach (['PWD|TYPE-A', 'SENIOR|TYPE-B'] as $copy => $combo) {
            [$clientCategory, $type] = explode('|', $combo);
            foreach ([0, 1, 2] as $group) {
                $history = DB::table('transaction_history')->insertGetId([
                    'transaction_id' => 'LIKELY-'.$group.'-'.$copy, 'client_id' => 'C1',
                    'transaction_date' => '2026-09-02', 'type' => $type, 'category' => 'EVENTS', 'status' => 'Claimed',
                ]);
                $rows[] = [
                    'full_name' => 'Likely Person '.$group,
                    'event_date' => '2026-09-02', 'client_category' => $clientCategory,
                    'transaction_category' => 'EVENTS', 'transaction_type' => $type,
                    'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $history,
                ];
            }
        }
        DB::table('transaction_events')->insert($rows);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('transaction-events.records-duplicates', [
            'per_page' => 10, 'exact_page' => 2, 'likely_page' => 1,
        ]))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], 'transaction_events') || str_contains($query['query'], 'transaction_history'));
        DB::disableQueryLog();

        $exact = $response->viewData('exactGroups');
        $likely = $response->viewData('likelyGroups');
        $this->assertSame(35, $exact->total());
        $this->assertSame(3, $likely->total());
        $this->assertCount(10, $exact);
        $this->assertCount(3, $likely);
        $this->assertSame(2, $exact->currentPage());
        $this->assertSame(1, $likely->currentPage());
        $this->assertStringContainsString('exact_page=3', $exact->nextPageUrl());
        $this->assertNull($likely->nextPageUrl());
        $this->assertSame(70, $response->viewData('exactRecordsTotal'));
        $this->assertSame(6, $response->viewData('likelyRecordsTotal'));
        foreach ([$exact, $likely] as $paginator) {
            foreach ($paginator as $group) {
                $this->assertCount(2, $group['events']);
                foreach ($group['events'] as $event) {
                    $this->assertTrue($event->relationLoaded('transferredTransaction'));
                }
            }
        }
        // Exact matches must not repeat in the likely tab.
        $exactMemberSets = [];
        foreach ($exact as $group) {
            $exactMemberSets[] = $group['events']->pluck('id')->sort()->values()->all();
        }
        foreach ($likely as $group) {
            $this->assertNotContains($group['events']->pluck('id')->sort()->values()->all(), $exactMemberSets);
        }
        // Likely Match adds a bounded lookup for its visible records and histories.
        $this->assertLessThanOrEqual(13, $queries->count(), 'Duplicate queries must not grow with group count.');
    }

    public function test_exact_matches_are_excluded_from_likely_tab(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $historyIds = [];
        foreach (['EXACT-1', 'EXACT-2'] as $transactionId) {
            $historyIds[] = DB::table('transaction_history')->insertGetId([
                'transaction_id' => $transactionId, 'client_id' => 'C1',
                'transaction_date' => '2026-09-01', 'type' => 'TYPE', 'category' => 'EVENTS', 'status' => 'Claimed',
            ]);
        }
        DB::table('transaction_events')->insert([
            [
                'full_name' => 'Exact Person', 'event_date' => '2026-09-01', 'client_category' => 'PWD',
                'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $historyIds[0],
            ],
            [
                'full_name' => 'exact person', 'event_date' => '2026-09-01', 'client_category' => 'PWD',
                'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $historyIds[1],
            ],
        ]);

        $response = $this->get(route('transaction-events.records-duplicates'))->assertOk();
        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(0, $response->viewData('likelyGroups')->total());
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertSame(0, $response->viewData('likelyRecordsTotal'));
    }

    public function test_different_birth_dates_match_exact_and_do_not_repeat_in_likely(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $historyIds = [];
        foreach (['BIRTH-1', 'BIRTH-2'] as $transactionId) {
            $historyIds[] = DB::table('transaction_history')->insertGetId([
                'transaction_id' => $transactionId, 'client_id' => 'C1',
                'transaction_date' => '2026-09-01', 'type' => 'TYPE', 'category' => 'EVENTS', 'status' => 'Claimed',
            ]);
        }
        DB::table('transaction_events')->insert([
            [
                'full_name' => 'Same Person', 'birth_date' => '1990-01-01', 'event_date' => '2026-09-01',
                'client_category' => 'PWD', 'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $historyIds[0],
            ],
            [
                'full_name' => 'Same Person', 'birth_date' => '2000-02-02', 'event_date' => '2026-09-01',
                'client_category' => 'PWD', 'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
                'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $historyIds[1],
            ],
        ]);

        $response = $this->get(route('transaction-events.records-duplicates'))->assertOk();
        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertCount(2, $response->viewData('exactGroups')->first()['events']);
        $this->assertSame(0, $response->viewData('likelyGroups')->total());
        $this->assertSame(0, $response->viewData('likelyRecordsTotal'));
    }

    public function test_likely_match_ignores_birth_date(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $historyIds = [];
        foreach (['LIKELY-1', 'LIKELY-2'] as $transactionId) {
            $historyIds[] = DB::table('transaction_history')->insertGetId([
                'transaction_id' => $transactionId, 'client_id' => 'C1',
                'transaction_date' => '2026-09-02', 'type' => 'TYPE-A', 'category' => 'EVENTS', 'status' => 'Claimed',
            ]);
        }
        DB::table('transaction_events')->insert([
            [
                'full_name' => 'Likely Person', 'birth_date' => '2000-02-02', 'event_date' => '2026-09-02',
                'client_category' => 'PWD', 'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE-A',
                'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $historyIds[0],
            ],
            [
                'full_name' => 'Likely Person', 'birth_date' => '1990-01-01', 'event_date' => '2026-09-02',
                'client_category' => 'SENIOR', 'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE-B',
                'transferred_at' => '2026-09-01 12:00:00', 'transferred_transaction_id' => $historyIds[1],
            ],
        ]);

        $response = $this->get(route('transaction-events.records-duplicates'))->assertOk();
        // Same name/date/category but different birth date, client category,
        // and type: not exact, but likely via date+category, date-only,
        // and category-only.
        $this->assertSame(0, $response->viewData('exactGroups')->total());
        $this->assertSame(1, $response->viewData('likelyGroups')->total());
        foreach ($response->viewData('likelyGroups') as $group) {
            $this->assertCount(2, $group['events']);
        }
    }

    public function test_duplicate_review_hides_exact_matches_from_likely_tab(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        DB::table('transaction_events')->insert([
            [
                'full_name' => 'Exact Person', 'event_date' => '2026-09-01', 'client_category' => 'PWD',
                'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
            ],
            [
                'full_name' => 'Exact Person', 'event_date' => '2026-09-01', 'client_category' => 'PWD',
                'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE',
            ],
            [
                'full_name' => 'Likely Person', 'event_date' => '2026-09-02', 'client_category' => 'PWD',
                'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE-A',
            ],
            [
                'full_name' => 'Likely Person', 'event_date' => '2026-09-02', 'client_category' => 'SENIOR',
                'transaction_category' => 'EVENTS', 'transaction_type' => 'TYPE-B',
            ],
        ]);

        $response = $this->get(route('transaction-events.duplicate-review'))->assertOk();
        $exact = $response->viewData('exactGroups');
        $likely = $response->viewData('likelyGroups');
        $this->assertSame(1, $exact->total());
        // The likely-only pair matches the date+category, date-only, and
        // category-only patterns; the exact pair must appear nowhere here.
        $this->assertSame(3, $likely->total());
        $exactMemberSets = [];
        foreach ($exact as $group) {
            $exactMemberSets[] = $group['events']->pluck('id')->sort()->values()->all();
        }
        foreach ($likely as $group) {
            $this->assertSame('Likely Person', $group['events']->first()->full_name);
            $this->assertNotContains($group['events']->pluck('id')->sort()->values()->all(), $exactMemberSets);
        }
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
        // The Alpha pair is an exact match, so it must not repeat as likely.
        $this->assertSame(0, $response->viewData('likelyGroups')->total());
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
