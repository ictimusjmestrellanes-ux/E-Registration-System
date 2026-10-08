<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Services\DuplicateClientScan;
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

    public function test_exact_match_uses_only_first_and_last_names(): void
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
            'first_name' => 'Juan',
            'middle_name' => 'Reyes',
            'last_name' => 'Cruz',
            'birth_date' => '2001-12-31',
        ]);
        $this->createTransaction($first);
        $this->createTransaction($second);

        $response = $this->get('/duplicate-review')->assertOk();

        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertSame(0, $response->viewData('likelyGroups')->total());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->viewData('exactGroups')->first()['clients']->pluck('id')->all()
        );
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

    public function test_similar_spelling_keeps_first_letter_typo_matches(): void
    {
        $first = Client::create([
            'client_id' => 'SIMILAR-1',
            'first_name' => 'Maria',
            'last_name' => 'Cruz',
        ]);
        $second = Client::create([
            'client_id' => 'SIMILAR-2',
            'first_name' => 'Maria',
            'last_name' => 'Kruz',
        ]);

        $response = $this->get('/duplicate-review')->assertOk();
        $this->assertTrue($response->viewData('similarScanPending'));
        $this->finishScan();
        $response = $this->get('/duplicate-review')->assertOk();

        $this->assertSame(1, $response->viewData('similarGroups')->total());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->viewData('similarGroups')->first()['clients']->pluck('id')->all()
        );
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
        $this->assertCount(1, $detailQueries, 'A cached reload should issue one detail query for visible records only.');
        // Ten keys in WHERE and in the canonical-key CASE, plus one legacy
        // predicate. The query never binds the full duplicate client ID list.
        $this->assertCount(31, $detailQueries->first()['bindings']);
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

    public function test_scan_resumes_in_small_batches_and_client_edits_invalidate_results(): void
    {
        foreach (['Cruz', 'Kruz', 'Krux'] as $index => $surname) {
            Client::create(['client_id' => 'BATCH-'.$index, 'first_name' => 'Maria', 'last_name' => $surname]);
        }
        $scan = app(DuplicateClientScan::class);
        $this->assertFalse($scan->advance(1)['done']); // read
        $this->assertFalse($scan->advance(1)['done']); // index
        $this->assertFalse($scan->advance(1)['done']); // one pair, not the full block
        $this->assertNull($scan->results());
        $this->finishScan();
        $this->assertNotEmpty($scan->results());
        $generation = $scan->generation();
        Client::first()->update(['last_name' => 'Changed']);
        $this->assertNotSame($generation, $scan->generation());
        $this->assertNull($scan->results());
    }

    public function test_database_cache_can_store_scan_state_and_page_never_starts_scan(): void
    {
        Cache::setDefaultDriver('database');
        Client::create(['client_id' => 'DB-1', 'first_name' => 'Maria', 'last_name' => 'Cruz']);
        Client::create(['client_id' => 'DB-2', 'first_name' => 'Maria', 'last_name' => 'Kruz']);
        $this->get('/duplicate-review')->assertOk()->assertSee('Load Similar Spelling');
        $this->assertSame(0, DB::table('cache')->where('key', 'like', '%_state')->count());
        $this->postJson('/duplicate-review/similar-scan')->assertOk()->assertJson(['done' => false]);
        $this->assertSame(1, DB::table('cache')->where('key', 'like', '%_state')->count());
        $this->finishScan();
        $this->assertCount(1, app(DuplicateClientScan::class)->results());
    }

    public function test_similar_scan_keeps_imported_name_formats_and_does_not_treat_middle_names_as_typos(): void
    {
        Client::create(['client_id' => 'FORMAT-1', 'first_name' => 'CALDO,', 'last_name' => 'PATRICK']);
        Client::create(['client_id' => 'FORMAT-2', 'first_name' => 'PATRICK', 'last_name' => 'CALDA']);
        Client::create(['client_id' => 'SAME-1', 'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Cruz']);
        Client::create(['client_id' => 'SAME-2', 'first_name' => 'Juan', 'middle_name' => 'Reyes', 'last_name' => 'Cruz']);
        $this->finishScan();
        $response = $this->get('/duplicate-review')->assertOk();
        $this->assertSame(1, $response->viewData('similarGroupsTotal'));
        $this->assertEqualsCanonicalizing(['FORMAT-1', 'FORMAT-2'],
            $response->viewData('similarGroups')->first()['clients']->pluck('client_id')->all());
    }

    public function test_62000_clients_are_paginated_in_sql_without_full_model_hydration_or_fuzzy_scan(): void
    {
        for ($offset = 0; $offset < 62000; $offset += 1000) {
            $clients = [];
            $transactions = [];
            for ($id = $offset + 1; $id <= $offset + 1000; $id++) {
                $publicId = 'LARGE-'.$id;
                $clients[] = ['id' => $id, 'client_id' => $publicId,
                    'first_name' => 'Person '.intdiv($id - 1, 2), 'last_name' => 'Example'];
                $transactions[] = ['client_id' => $publicId, 'transaction_id' => $publicId.'-26-0001',
                    'transaction_date' => '2026-01-01', 'category' => 'others', 'type' => 'test'];
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
        $this->assertSame(31000, $response->viewData('exactGroupsTotal'));
        $this->assertSame(62000, $response->viewData('exactRecordsTotal'));
        $this->assertCount(10, $response->viewData('exactGroups'));
        $this->assertSame(20, $response->viewData('exactGroups')->sum(fn ($group) => $group['clients']->count()));
        $this->assertTrue($response->viewData('similarScanPending'));
        $this->assertFalse($queries->contains(fn ($query) => str_contains(strtolower($query['query']), 'soundex')));
        $this->assertFalse($queries->contains(fn ($query) => str_contains($query['query'], '"middle_name"') && !str_contains($query['query'], 'where')));
        $progress = app(DuplicateClientScan::class)->advance();
        $this->assertFalse($progress['done']);
        $this->assertSame(2000, $progress['processed']);
        fwrite(STDERR, sprintf("\n62k page benchmark: %.3fs; only 20 client models displayed.\n", $elapsed));
    }

    private function finishScan(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (app(DuplicateClientScan::class)->advance()['done']) {
                return;
            }
        }
        $this->fail('The scan did not finish within the expected number of batches.');
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
