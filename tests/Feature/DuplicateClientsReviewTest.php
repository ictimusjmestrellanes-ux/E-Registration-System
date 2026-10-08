<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DuplicateClientsReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Production uses MySQL; provide its string functions for SQLite tests.
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('CONCAT_WS', static fn ($separator, ...$parts) => implode($separator, array_filter($parts, static fn ($part) => $part !== null)));
        $pdo->sqliteCreateFunction('CONCAT', static fn (...$parts) => implode('', $parts));
        $pdo->sqliteCreateFunction('SOUNDEX', static fn ($value) => soundex($value ?? ''));
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
    }

    public function test_exact_match_uses_last_and_first_names_only(): void
    {
        $first = Client::create([
            'client_id' => 'EXACT-1',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Cruz',
            'birth_date' => '1990-01-01',
        ]);
        $second = Client::create([
            'client_id' => 'EXACT-2',
            'first_name' => ' juan ',
            'middle_name' => ' santos ',
            'last_name' => ' cruz ',
            'birth_date' => '2001-12-31',
        ]);
        $this->createTransaction($first);
        $this->createTransaction($second);
        $expectedIds = [$first->id, $second->id];
        foreach (['Reyes', null, 'S.'] as $index => $middle) {
            $other = Client::create(['client_id' => 'OTHER-MIDDLE-'.$index,
                'first_name' => 'Juan', 'middle_name' => $middle, 'last_name' => 'Cruz']);
            $this->createTransaction($other);
            $expectedIds[] = $other->id;
        }
        foreach ([['Pedro', 'Cruz'], ['Juan', 'Reyes']] as $index => [$given, $surname]) {
            $other = Client::create(['client_id' => 'OTHER-NAME-'.$index,
                'first_name' => $given, 'middle_name' => 'Santos', 'last_name' => $surname]);
            $this->createTransaction($other);
        }

        $response = $this->get('/duplicate-review')->assertOk();

        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(5, $response->viewData('exactRecordsTotal'));
        $this->assertSame(0, $response->viewData('likelyGroups')->total());
        $this->assertEqualsCanonicalizing(
            $expectedIds,
            $response->viewData('exactGroups')->first()['clients']->pluck('id')->all()
        );
        $response->assertSee('Same Last Name and First Name.');
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
    }

    public function test_exact_match_ignores_suffix_and_middle_name_while_full_name_still_checks_them(): void
    {
        $first = Client::create(['client_id' => 'EXACT-SUFFIX-1', 'first_name' => 'Juan', 'middle_name' => null,
            'last_name' => 'Cruz', 'suffix' => 'Jr.']);
        $second = Client::create(['client_id' => 'EXACT-SUFFIX-2', 'first_name' => 'Juan', 'middle_name' => ' ',
            'last_name' => 'Cruz', 'suffix' => ' jr. ']);
        foreach ([$first, $second] as $client) {
            $this->createTransaction($client);
        }
        $expectedIds = [$first->id, $second->id];
        foreach (['Sr.', null] as $index => $suffix) {
            $other = Client::create(['client_id' => 'EXACT-SUFFIX-OTHER-'.$index,
                'first_name' => 'Juan', 'last_name' => 'Cruz', 'suffix' => $suffix]);
            $this->createTransaction($other);
            $expectedIds[] = $other->id;
        }
        $response = $this->get('/duplicate-review')->assertOk();
        $this->assertSame(1, $response->viewData('exactGroupsTotal'));
        $this->assertSame(4, $response->viewData('exactRecordsTotal'));
        $this->assertEqualsCanonicalizing($expectedIds,
            $response->viewData('exactGroups')->first()['clients']->pluck('id')->all());
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
    }

    public function test_match_full_name_excludes_spelling_variations(): void
    {
        foreach ([
            ['Maria', 'Santos', 'Cruz'], ['Marie', 'Santos', 'Cruz'],
            ['Maria', 'Santus', 'Cruz'], ['Maria', 'Santos', 'Kruz'],
        ] as $index => [$first, $middle, $last]) {
            $client = Client::create(['client_id' => 'FULL-VARIATION-'.$index,
                'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last]);
            $this->createTransaction($client);
        }
        $response = $this->get('/duplicate-review?duplicate_tab=full_name')->assertOk();
        $this->assertSame(1, $response->viewData('exactGroupsTotal'));
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertSame(0, $response->viewData('similarGroupsTotal'));
        $response->assertSee('No records with matching full names found.');
    }

    public function test_exact_match_only_includes_clients_with_transactions(): void
    {
        $first = Client::create([
            'client_id' => 'WITH-TX-1',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        $second = Client::create([
            'client_id' => 'WITH-TX-2',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        Client::create([
            'client_id' => 'NO-TX-1',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        $this->createTransaction($first);
        $this->createTransaction($second);

        $response = $this->get('/duplicate-review')->assertOk();

        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->viewData('exactGroups')->first()['clients']->pluck('id')->all()
        );
    }

    public function test_exact_match_recognizes_legacy_transaction_id_links(): void
    {
        $first = Client::create([
            'client_id' => '2601001',
            'first_name' => 'Legacy',
            'last_name' => 'Client',
        ]);
        $second = Client::create([
            'client_id' => '2601002',
            'first_name' => 'Legacy',
            'last_name' => 'Client',
        ]);
        $this->createTransaction($first);
        TransactionHistory::create([
            'client_id' => null,
            'transaction_id' => $second->client_id.'-26-0001',
            'transaction_date' => '2026-01-01',
            'category' => 'others',
            'type' => 'test',
        ]);

        $response = $this->get('/duplicate-review')->assertOk();

        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->viewData('exactGroups')->first()['clients']->pluck('id')->all()
        );
    }

    public function test_clients_inside_duplicate_groups_have_sortable_columns(): void
    {
        foreach ([31, 24] as $index => $age) {
            $client = Client::create([
                'client_id' => 'SORT-'.$index,
                'first_name' => 'Sortable',
                'last_name' => 'Client',
                'age' => $age,
                'birth_date' => '1990-01-0'.($index + 1),
                'gender' => $index === 0 ? 'Female' : 'Male',
                'contact' => '0917000000'.$index,
                'address' => $index === 0 ? 'Alpha Street' : 'Beta Street',
            ]);
            $this->createTransaction($client);
        }

        $this->get('/duplicate-review')
            ->assertOk()
            ->assertSee('duplicate-client-group-table', false)
            ->assertSee('data-duplicate-client-sort', false)
            ->assertSee('data-sort-column="0" data-sort-type="text"', false)
            ->assertSee('data-sort-column="3" data-sort-type="number"', false)
            ->assertSee('data-sort-column="4" data-sort-type="date"', false)
            ->assertSee('data-sort-column="8" data-sort-type="number"', false)
            ->assertSee('data-sort-value="1990-01-01"', false)
            ->assertSee('duplicateClientSortCollator', false);
    }

    public function test_database_pagination_preserves_groups_and_filters_on_repeated_requests(): void
    {
        for ($group = 0; $group < 12; $group++) {
            foreach ([0, 1] as $copy) {
                $client = Client::create([
                    'client_id' => 'TEST-'.$group.'-'.$copy,
                    'first_name' => 'Person '.$group, 'last_name' => 'Example',
                    'birth_date' => '1990-01-01', 'city' => $group === 0 ? 'Imus' : 'Other',
                ]);
                $this->createTransaction($client);
            }
        }

        $response = $this->get('/duplicate-review?exact_page=2')->assertOk();
        $this->assertSame(12, $response->viewData('exactGroups')->total());
        $this->assertCount(2, $response->viewData('exactGroups'));
        $this->assertSame(24, $response->viewData('exactRecordsTotal'));
        // Exact queries no longer depend on a full-table membership cache.
        $this->assertNull(Cache::get('duplicate_clients_v3'));

        $response = $this->get('/duplicate-review?city=Imus')->assertOk();
        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertNull(Cache::get('duplicate_clients_v3'));
    }

    public function test_page_ignores_old_membership_cache_and_hydrates_only_visible_groups(): void
    {
        $memberships = [];
        for ($group = 0; $group < 12; $group++) {
            $memberships[$group] = [];
            foreach ([0, 1] as $copy) {
                $client = Client::create([
                    'client_id' => 'VISIBLE-'.$group.'-'.$copy,
                    'first_name' => 'Visible '.$group,
                    'last_name' => 'Client',
                    'birth_date' => '1990-01-01',
                ]);
                $memberships[$group][] = $client->id;
                $this->createTransaction($client);
            }
        }

        Cache::put('duplicate_clients_v3', [
            'exact' => $memberships,
            'likely' => [],
            'similar' => [],
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/duplicate-review')->assertOk();
        $detailQueries = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], '"client_id"')
            && str_contains($query['query'], '"first_name"')
            && str_contains($query['query'], 'from "clients"')
        )->values();
        DB::disableQueryLog();

        $this->assertSame(12, $response->viewData('exactGroups')->total());
        $this->assertCount(10, $response->viewData('exactGroups'));
        $this->assertCount(2, $detailQueries, 'Each tab should hydrate only its visible page, not all duplicates.');
        // Ten keys in WHERE and in the canonical-key CASE, plus one legacy
        // predicate. The query never binds the full duplicate client ID list.
        $this->assertCount(31, $detailQueries->first()['bindings']);
        $this->assertCount(30, $detailQueries->last()['bindings']);
        $this->assertSame(20, $response->viewData('exactGroups')->sum(fn ($group) => $group['clients']->count()));
    }

    public function test_client_changes_invalidate_membership_and_missing_clients_are_tolerated(): void
    {
        $client = Client::create(['client_id' => 'TEST-1', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        Cache::put('duplicate_clients_v3', ['exact' => [[$client->id, 999999]], 'likely' => [], 'similar' => []]);
        $response = $this->get('/duplicate-review')->assertOk();
        $this->assertSame(0, $response->viewData('exactGroups')->total());

        $client->update(['first_name' => 'John']);
        $this->assertNull(Cache::get('duplicate_clients_v3'));
        $this->assertNull(Cache::get('duplicate_clients_filter_options_v1'));
        Cache::put('duplicate_clients_v3', []);
        Cache::put('duplicate_clients_filter_options_v1', ['filterCities' => ['Imus']]);
        $client->delete();
        $this->assertNull(Cache::get('duplicate_clients_v3'));
        $this->assertNull(Cache::get('duplicate_clients_filter_options_v1'));
    }

    public function test_filters_match_one_member_but_keep_the_whole_transaction_group(): void
    {
        $first = Client::create(['client_id' => 'FILTER-1', 'first_name' => ' Juan ', 'last_name' => ' CRUZ ',
            'city' => ' Imus ', 'gender' => 'Male', 'birth_date' => '1990-01-01', 'address' => '100% real']);
        $second = Client::create(['client_id' => 'FILTER-2', 'first_name' => 'juan', 'last_name' => 'cruz',
            'city' => 'Other', 'gender' => 'Female']);
        $this->createTransaction($first);
        $this->createTransaction($second);
        foreach (['city=imus', 'search=100%25', 'search=Jan%2001%2C%201990', 'gender=male&city=imus'] as $filter) {
            $response = $this->get('/duplicate-review?'.$filter)->assertOk();
            $this->assertSame(1, $response->viewData('exactGroupsTotal'));
            $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        }
        $this->assertSame(0, $this->get('/duplicate-review?gender=female&city=imus')->assertOk()->viewData('exactGroupsTotal'));
        $this->assertSame(['Imus', 'Other'], $response->viewData('filterCities'));
    }

    public function test_extra_transactions_do_not_inflate_duplicate_counts_and_new_transactions_are_immediately_visible(): void
    {
        $first = Client::create(['client_id' => 'LIVE-1', 'first_name' => 'Live', 'last_name' => 'Match']);
        $second = Client::create(['client_id' => 'LIVE-2', 'first_name' => 'Live', 'last_name' => 'Match']);
        $this->createTransaction($first);
        $this->assertSame(0, $this->get('/duplicate-review')->assertOk()->viewData('exactGroupsTotal'));
        $this->createTransaction($second);
        TransactionHistory::create(['client_id' => $second->client_id, 'transaction_id' => 'extra-transaction',
            'transaction_date' => '2026-01-02', 'category' => 'others', 'type' => 'test']);
        $response = $this->get('/duplicate-review')->assertOk();
        $this->assertSame(1, $response->viewData('exactGroupsTotal'));
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
    }

    public function test_match_full_name_loads_immediately_and_includes_clients_without_transactions(): void
    {
        $ids = [];
        foreach ([['Juan', 'Santos', 'Cruz'], [' juan ', ' santos ', ' cruz ']] as $index => [$first, $middle, $last]) {
            $client = Client::create(['client_id' => 'FULL-READY-'.$index,
                'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
                'birth_date' => $index === 0 ? '1990-01-01' : '2000-12-31']);
            $ids[] = $client->id;
            $this->createTransaction($client);
        }
        $withoutTransaction = Client::create(['client_id' => 'FULL-NO-TX', 'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Cruz']);
        $ids[] = $withoutTransaction->id;
        Cache::put('duplicate_clients_scan_generation_v3', 'obsolete-generation');
        Cache::put('duplicate_clients_scan_v3_obsolete-generation_results', [[999998, 999999]]);
        $response = $this->get('/duplicate-review?duplicate_tab=full_name')->assertOk()
            ->assertSee('Match Full Name')->assertDontSee('Similar Spelling')
            ->assertDontSee('data-similar-scan', false)->assertDontSee('Load Similar Spelling');
        $this->assertArrayNotHasKey('similarScanPending', $response->original->getData());
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(3, $response->viewData('similarRecordsTotal'));
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertEqualsCanonicalizing($ids, $response->viewData('similarGroups')->first()['clients']->pluck('id')->all());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('duplicate.review.similar-scan'));
        $this->postJson('/duplicate-review/similar-scan')->assertNotFound();
    }

    public function test_match_full_name_groups_clients_even_when_none_have_transactions(): void
    {
        foreach ([0, 1] as $copy) {
            Client::create(['client_id' => 'NO-TRANSACTIONS-'.$copy,
                'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Cruz']);
        }
        $response = $this->get('/duplicate-review?duplicate_tab=full_name')->assertOk();
        $this->assertSame(0, $response->viewData('exactGroupsTotal'));
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
        // Check the Match Full Name panel specifically; Exact Match still has
        // its transaction requirement and explanatory text.
        $previousErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $panel = $document->getElementById('similar-tab');
        $this->assertNotNull($panel);
        $this->assertStringNotContainsString('Each client must have at least one transaction.', $panel->textContent);
    }

    public function test_full_name_filters_and_dropdowns_include_the_member_without_transactions(): void
    {
        $withTransaction = Client::create(['client_id' => 'ONE-TX', 'first_name' => 'Maria',
            'middle_name' => 'Santos', 'last_name' => 'Cruz', 'city' => 'Other']);
        $this->createTransaction($withTransaction);
        $withoutTransaction = Client::create(['client_id' => 'ZERO-TX', 'first_name' => 'Maria',
            'middle_name' => 'Santos', 'last_name' => 'Cruz', 'city' => 'Imus']);
        $response = $this->get('/duplicate-review?duplicate_tab=full_name&city=Imus')->assertOk();
        $this->assertSame(0, $response->viewData('exactGroupsTotal'));
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
        $this->assertEqualsCanonicalizing([$withTransaction->id, $withoutTransaction->id],
            $response->viewData('similarGroups')->first()['clients']->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['Imus', 'Other'], $response->viewData('filterCities'));
    }

    public function test_filters_and_dropdowns_include_both_exact_only_and_full_name_only_groups(): void
    {
        foreach (['Santos' => 'Exact City A', 'Reyes' => 'Exact City B'] as $middle => $city) {
            $client = Client::create(['client_id' => 'EXACT-CITY-'.$middle, 'first_name' => 'Juan',
                'middle_name' => $middle, 'last_name' => 'Cruz', 'city' => $city]);
            $this->createTransaction($client);
        }
        foreach (['Full City A', 'Full City B'] as $index => $city) {
            Client::create(['client_id' => 'FULL-CITY-'.$index, 'first_name' => 'Maria',
                'middle_name' => 'Santos', 'last_name' => 'Cruz', 'city' => $city]);
        }
        $response = $this->get('/duplicate-review?city=Exact%20City%20A')->assertOk();
        $this->assertSame(1, $response->viewData('exactGroupsTotal'));
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertSame(0, $response->viewData('similarGroupsTotal'));
        $this->assertEqualsCanonicalizing(['Exact City A', 'Exact City B', 'Full City A', 'Full City B'],
            $response->viewData('filterCities'));

        $response = $this->get('/duplicate-review?city=Full%20City%20A&duplicate_tab=full_name')->assertOk();
        $this->assertSame(0, $response->viewData('exactGroupsTotal'));
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
    }

    public function test_match_full_name_keeps_tab_and_filters_when_paginating(): void
    {
        for ($group = 0; $group < 12; $group++) {
            foreach ([0, 1] as $copy) {
                $client = Client::create(['client_id' => 'FULL-PAGE-'.$group.'-'.$copy,
                    'first_name' => 'Person '.$group, 'middle_name' => 'Santos', 'last_name' => 'Cruz',
                    'city' => $group === 0 ? 'Imus' : 'Other']);
                if ($group === 0) {
                    $this->createTransaction($client);
                }
            }
        }
        // Legacy similar_page links now open Match Full Name automatically.
        $response = $this->get('/duplicate-review?similar_page=2')->assertOk();
        $this->assertSame(12, $response->viewData('similarGroupsTotal'));
        $this->assertSame(24, $response->viewData('similarRecordsTotal'));
        $this->assertCount(2, $response->viewData('similarGroups'));
        $this->assertCount(1, $response->viewData('exactGroups'));
        $this->assertSame(1, $response->viewData('exactGroupsTotal'));
        $response->assertSee('tab-pane fade show active" id="similar-tab"', false)
            ->assertSee('name="duplicate_tab" id="dupActiveTab" value="full_name"', false);
        $this->assertStringContainsString('duplicate_tab=full_name', $response->viewData('similarGroups')->url(1));
        $this->assertStringContainsString('#similar-tab', $response->viewData('similarGroups')->url(1));

        $filtered = $this->get('/duplicate-review?duplicate_tab=full_name&city=Imus')->assertOk();
        $this->assertSame(1, $filtered->viewData('similarGroupsTotal'));
        $this->assertSame(2, $filtered->viewData('similarRecordsTotal'));
        $this->assertStringContainsString('city=Imus', $filtered->viewData('similarGroups')->url(1));
        $filtered->assertSee('name="duplicate_tab" id="dupActiveTab" value="full_name"', false);
    }

    public function test_match_full_name_uses_matching_suffixes_and_middle_names(): void
    {
        foreach ([
            ['Santos', 'Jr.'], [' santos ', ' jr. '], ['Reyes', 'Jr.'], [null, 'Jr.'], ['Santos', 'Sr.'],
        ] as $index => [$middle, $suffix]) {
            $client = Client::create(['client_id' => 'FULL-SUFFIX-'.$index,
                'first_name' => 'Juan', 'middle_name' => $middle, 'last_name' => 'Cruz', 'suffix' => $suffix]);
            $this->createTransaction($client);
        }
        $response = $this->get('/duplicate-review?duplicate_tab=full_name')->assertOk();
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertSame(2, $response->viewData('similarRecordsTotal'));
        $this->assertEqualsCanonicalizing(['FULL-SUFFIX-0', 'FULL-SUFFIX-1'],
            $response->viewData('similarGroups')->first()['clients']->pluck('client_id')->all());
    }

    public function test_full_name_counts_are_per_tab_and_include_nontransaction_members(): void
    {
        foreach ([0, 1] as $copy) {
            $client = Client::create(['client_id' => 'FULL-COUNTS-'.$copy, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
            $this->createTransaction($client);
        }
        Client::create(['client_id' => 'FULL-COUNTS-NO-TX', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $response = $this->get('/duplicate-review?duplicate_tab=full_name')->assertOk()
            ->assertSee('1 client group(s) in this tab')->assertSee('3 record(s) in this tab')
            ->assertDontSee('5 record(s) in this tab');
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertSame(3, $response->viewData('similarRecordsTotal'));
        $this->get('/duplicate-review?duplicate_tab=likely')->assertOk()
            ->assertSee('0 client group(s) in this tab')->assertSee('0 record(s) in this tab');
    }

    public function test_62000_clients_are_paginated_in_sql_with_immediate_full_name_results(): void
    {
        for ($offset = 0; $offset < 62000; $offset += 1000) {
            $clients = [];
            $transactions = [];
            for ($id = $offset + 1; $id <= $offset + 1000; $id++) {
                $publicId = 'LARGE-'.$id;
                $clients[] = ['id' => $id, 'client_id' => $publicId,
                    'first_name' => 'Person '.intdiv($id - 1, 2), 'last_name' => 'Example'];
                // Half of the duplicate groups have no transactions at all.
                if (intdiv($id - 1, 2) % 2 === 0) {
                    $transactions[] = ['client_id' => $publicId, 'transaction_id' => $publicId.'-26-0001',
                        'transaction_date' => '2026-01-01', 'category' => 'others', 'type' => 'test'];
                }
            }
            DB::table('clients')->insert($clients);
            DB::table('transaction_history')->insert($transactions);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $started = microtime(true);
        $response = $this->get('/duplicate-review?exact_page=2')->assertOk();
        $elapsed = microtime(true) - $started;
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(15500, $response->viewData('exactGroupsTotal'));
        $this->assertSame(31000, $response->viewData('exactRecordsTotal'));
        $this->assertCount(10, $response->viewData('exactGroups'));
        $this->assertSame(20, $response->viewData('exactGroups')->sum(fn ($group) => $group['clients']->count()));
        $this->assertSame(31000, $response->viewData('similarGroupsTotal'));
        $this->assertSame(62000, $response->viewData('similarRecordsTotal'));
        $this->assertCount(10, $response->viewData('similarGroups'));
        $this->assertFalse($queries->contains(fn ($query) => str_contains(strtolower($query['query']), 'soundex')));
        $this->assertFalse($queries->contains(fn ($query) => str_contains($query['query'], '"middle_name"') && !str_contains($query['query'], 'where')));
        fwrite(STDERR, sprintf("\n62k page benchmark: %.3fs; only 20 client models displayed.\n", $elapsed));
    }

    private function createTransaction(Client $client): TransactionHistory
    {
        return TransactionHistory::create([
            'client_id' => $client->client_id,
            'transaction_id' => $client->client_id.'-26-0001',
            'transaction_date' => '2026-01-01',
            'category' => 'others',
            'type' => 'test',
        ]);
    }
}
