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

        $this->assertSame(1, $response->viewData('similarGroups')->total());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->viewData('similarGroups')->first()['clients']->pluck('id')->all()
        );
    }

    public function test_cold_and_warm_cache_preserve_groups_filters_and_pagination(): void
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
        $cached = Cache::get('duplicate_clients_v3');
        $this->assertCount(12, $cached['exact']);
        foreach ($cached as $category) {
            foreach ($category as $ids) {
                foreach ($ids as $id) {
                    $this->assertIsInt($id);
                }
            }
        }

        $response = $this->get('/duplicate-review?city=Imus')->assertOk();
        $this->assertSame(1, $response->viewData('exactGroups')->total());
        $this->assertSame(2, $response->viewData('exactRecordsTotal'));
        $this->assertSame($cached, Cache::get('duplicate_clients_v3'));
    }

    public function test_cached_reload_hydrates_only_visible_groups(): void
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
        $this->assertCount(20, $detailQueries->first()['bindings']);
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
