<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NameDetailEventRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
    }

    private function event(string $name, string $clientCategory, ?string $clientId = null, array $changes = []): int
    {
        $historyId = $clientId === null ? null : DB::table('transaction_history')->insertGetId([
            'client_id' => $clientId,
            'transaction_id' => uniqid('MATCH-', true),
            'transaction_date' => '2026-09-01',
            'category' => 'EVENTS',
            'type' => 'TRANCH 1',
        ]);

        return DB::table('transaction_events')->insertGetId(array_replace([
            'full_name' => $name,
            'birth_date' => '1990-01-01',
            'event_date' => '2026-09-01',
            'transaction_type' => 'TRANCH 1',
            'client_category' => $clientCategory,
            'transaction_category' => 'EVENTS',
            'transferred_at' => '2026-09-02 12:00:00',
            'transferred_transaction_id' => $historyId,
        ], $changes));
    }

    public function test_initial_and_full_middle_names_with_any_one_matching_detail_are_shown(): void
    {
        $dateMatch = [
            $this->event('Maria Patricia Cruz', 'PWD', 'C1'),
            $this->event('Maria P. Cruz', 'SENIOR', 'C2', [
                'birth_date' => '2000-02-02', 'transaction_type' => 'TRANCH 2',
            ]),
        ];
        $typeMatch = [
            $this->event('Juan Antonio Santos', 'PWD'),
            $this->event('Juan A. Santos', 'SENIOR', null, ['event_date' => '2026-09-03']),
        ];
        $categoryMatch = [
            $this->event('Ana Maria Reyes', 'PWD'),
            $this->event('Ana M. Reyes', ' pwd ', null, [
                'event_date' => '2026-09-04', 'transaction_type' => 'TRANCH 2',
            ]),
        ];
        // Full middle names without an initial variant are excluded.
        $this->event('Pedro Manuel Gomez', 'PWD');
        $this->event('Pedro Miguel Gomez', 'PWD');
        // An initial must match the full middle name's first letter.
        $this->event('Luisa Beatriz Ramos', 'PWD');
        $this->event('Luisa C. Ramos', 'PWD');
        // A valid name pair with none of the three details in common is excluded.
        $this->event('Paolo Gabriel Diaz', 'PWD');
        $this->event('Paolo G. Diaz', 'SENIOR', null, [
            'event_date' => '2026-09-04', 'transaction_type' => 'TRANCH 2',
        ]);
        // A missing middle name is not an initial/full-middle pair.
        $this->event('Other Person', 'PWD');
        $this->event('Other P. Person', 'PWD');

        $response = $this->get(route('transaction-events.records-duplicates', ['duplicate_tab' => 'likely']))
            ->assertOk()->assertSee('Likely Match')
            ->assertSee('middle initial')
            ->assertSee('full middle name')
            ->assertSee('Matching details: Event Date')
            ->assertSee('Matching details: Transaction Type')
            ->assertSee('Matching details: Client Category');
        $groups = $response->viewData('likelyGroups');
        $this->assertSame(3, $groups->total());
        $this->assertSame(6, $response->viewData('likelyRecordsTotal'));
        $actual = $groups->map(fn ($group) => $group['events']->pluck('id')->sort()->values()->all())->all();
        $this->assertEqualsCanonicalizing([$dateMatch, $typeMatch, $categoryMatch], $actual);
        $this->assertSame([['Event Date'], ['Transaction Type'], ['Client Category']],
            $groups->pluck('matched_fields')->all());
    }

    public function test_blank_values_do_not_count_as_matching_details_and_filters_apply_before_matching(): void
    {
        $this->event('Blank Santos Person', '', null, ['event_date' => null, 'transaction_type' => null]);
        $this->event('Blank S. Person', '', null, ['event_date' => null, 'transaction_type' => null]);
        $this->event('Maria Lourdes Cruz', 'PWD');
        $this->event('Maria L. Cruz', 'SENIOR', null, ['event_date' => '2026-09-03']);

        $response = $this->get(route('transaction-events.records-duplicates', ['duplicate_tab' => 'likely']))
            ->assertOk();
        $this->assertSame(1, $response->viewData('likelyGroups')->total());

        $filtered = $this->get(route('transaction-events.records-duplicates', [
            'duplicate_tab' => 'likely', 'client_category' => ['PWD'],
        ]))->assertOk();
        $this->assertSame(0, $filtered->viewData('likelyGroups')->total());
    }

    public function test_likely_group_includes_other_records_for_the_qualified_person(): void
    {
        $expectedIds = [
            $this->event('DELOS SANTOS, DANILO CAMABALISA', 'INDIGENT', 'C1', [
                'event_date' => '2026-09-05',
                'transaction_type' => 'SIR ALLEN',
            ]),
            $this->event('DELOS SANTOS, DANILO C.', 'WALKIN', 'C2', [
                'event_date' => '2026-09-05',
                'transaction_type' => 'TRANCHE 2',
            ]),
            $this->event('DELOS SANTOS, DANILO C.', 'PODA', 'C3', [
                'event_date' => '2026-10-08',
                'transaction_type' => 'TRANCHE 2',
            ]),
        ];

        $response = $this->get(route('transaction-events.records-duplicates', [
            'duplicate_tab' => 'likely',
        ]))->assertOk();

        $groups = $response->viewData('likelyGroups');
        $this->assertSame(1, $groups->total());
        $this->assertSame(3, $response->viewData('likelyRecordsTotal'));
        $this->assertSame(
            collect($expectedIds)->sort()->values()->all(),
            $groups->first()['events']->pluck('id')->sort()->values()->all()
        );
    }

    public function test_tab_paginates_by_name_and_keeps_tab_active(): void
    {
        for ($person = 0; $person < 12; $person++) {
            $this->event('Person Santos Family'.$person, 'PWD', 'C'.$person.'A');
            $this->event('Person S. Family'.$person, 'SENIOR', 'C'.$person.'B');
        }
        $response = $this->get(route('transaction-events.records-duplicates', [
            'likely_page' => 2, 'per_page' => 10,
        ]))->assertOk()->assertSee('tab-pane fade show active" id="rlikely-tab', false)
            ->assertSee('name="duplicate_tab" id="dupActiveTab" value="likely"', false);
        $groups = $response->viewData('likelyGroups');
        $this->assertSame(12, $groups->total());
        $this->assertCount(2, $groups);
        $this->assertSame(24, $response->viewData('likelyRecordsTotal'));
        $this->assertStringContainsString('duplicate_tab=likely', $groups->url(1));
        $this->assertTrue($groups->every(fn ($group) => $group['events']->count() === 2));
    }

    public function test_ajax_reload_returns_only_the_duplicate_records_fragment(): void
    {
        $this->event('Juan Santos', 'PWD', 'C1');
        $this->event('Santos, Juan', 'PWD', 'C2');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('transaction-events.records-duplicates', ['duplicate_tab' => 'exact']))
            ->assertOk()
            ->assertSee('id="duplicateRecordsContent"', false)
            ->assertSee('Filter Duplicates')
            ->assertDontSee('<html', false)
            ->assertDontSee('<h4 class="mb-1">Duplicate Events Records</h4>', false);

        $this->withHeader('X-Requested-With', '')
            ->get(route('transaction-events.records-duplicates'))
            ->assertOk()
            ->assertSee('loadDuplicateRecords', false)
            ->assertSee("'X-Requested-With': 'XMLHttpRequest'", false);
    }

    public function test_status_action_returns_to_the_tab(): void
    {
        $eventId = $this->event('Juan Santos', 'PWD', 'C1');
        $this->event('Santos, Juan', 'SENIOR', 'C2');

        $query = ['duplicate_tab' => 'likely', 'likely_page' => 2];
        $this->patch(route('transaction-events.records.status', ['event' => $eventId] + $query),
            ['status' => 'Claimed'])
            ->assertRedirect(route('transaction-events.records-duplicates', $query))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('transaction_events', ['id' => $eventId, 'status' => 'Claimed']);
    }
}
