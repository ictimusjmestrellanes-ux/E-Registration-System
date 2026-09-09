<?php

namespace Tests\Feature;

use App\Models\Client;
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

    public function test_cold_and_warm_cache_preserve_groups_filters_and_pagination(): void
    {
        for ($group = 0; $group < 12; $group++) {
            foreach ([0, 1] as $copy) {
                Client::create([
                    'client_id' => 'TEST-'.$group.'-'.$copy,
                    'first_name' => 'Person '.$group, 'last_name' => 'Example',
                    'birth_date' => '1990-01-01', 'city' => $group === 0 ? 'Imus' : 'Other',
                ]);
            }
        }

        $response = $this->get('/duplicate-review?exact_page=2')->assertOk();
        $this->assertSame(12, $response->viewData('exactGroups')->total());
        $this->assertCount(2, $response->viewData('exactGroups'));
        $this->assertSame(24, $response->viewData('exactRecordsTotal'));
        $cached = Cache::get('duplicate_clients_v2');
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
        $this->assertSame($cached, Cache::get('duplicate_clients_v2'));
    }

    public function test_client_changes_invalidate_membership_and_missing_clients_are_tolerated(): void
    {
        $client = Client::create(['client_id' => 'TEST-1', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        Cache::put('duplicate_clients_v2', ['exact' => [[$client->id, 999999]], 'likely' => [], 'similar' => []]);
        $response = $this->get('/duplicate-review')->assertOk();
        $this->assertSame(0, $response->viewData('exactGroups')->total());

        $client->update(['first_name' => 'John']);
        $this->assertNull(Cache::get('duplicate_clients_v2'));
        Cache::put('duplicate_clients_v2', []);
        $client->delete();
        $this->assertNull(Cache::get('duplicate_clients_v2'));
    }
}
