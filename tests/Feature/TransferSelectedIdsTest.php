<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferSelectedIdsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPending(string $name, string $clientCategory = 'INDIGENT'): TransactionEvent
    {
        return TransactionEvent::create([
            'full_name' => $name,
            'client_category' => $clientCategory,
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => null,
            'not_duplicate' => false,
        ]);
    }

    public function test_ids_endpoint_resolves_filtered_population(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $a = $this->seedPending('Alpha One', 'INDIGENT');
        $b = $this->seedPending('Beta Two', 'LUPON');
        $this->seedPending('Gamma Three', 'PWD');

        $response = $this->postJson(route('transaction-events.transfer-selected.ids'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
            'client_category' => ['INDIGENT', 'LUPON'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $response->json('ids'));
    }

    public function test_ids_endpoint_never_includes_transferred_events(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $pending = $this->seedPending('Alpha One');
        TransactionEvent::create([
            'full_name' => 'Already Transferred',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => now(),
            'not_duplicate' => false,
        ]);

        $response = $this->postJson(route('transaction-events.transfer-selected.ids'), [
            'select_all' => 1,
            'exclude_duplicates' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $this->assertEquals([$pending->id], $response->json('ids'));
    }

    public function test_select_all_in_duplicate_names_filter_includes_every_page(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $duplicateIds = [];
        for ($i = 0; $i < 16; $i++) {
            $duplicateIds[] = $this->seedPending('Juan Dela Cruz')->id;
        }
        $unique = $this->seedPending('Maria Santos');

        $this->get(route('transaction-events.index', [
            'duplicate_names' => 1,
            'per_page' => 15,
        ]))->assertOk()
            ->assertViewHas('events', fn ($events) => $events->total() === 16 && $events->count() === 15)
            ->assertSee('const totalMatchingEvents = 16;', false);

        $filtered = $this->postJson(route('transaction-events.transfer-selected.ids'), [
            'select_all' => 1,
            'duplicate_names' => 1,
        ]);
        $filtered->assertOk()->assertJsonPath('total', 16);
        $this->assertEqualsCanonicalizing($duplicateIds, $filtered->json('ids'));

        $prepare = $this->postJson(route('transaction-events.transfer-selected.prepare'), [
            'select_all' => 1,
            'duplicate_names' => 1,
        ]);
        $prepare->assertOk()->assertJsonPath('total', 16);

        $all = $this->postJson(route('transaction-events.transfer-selected.ids'), [
            'select_all' => 1,
        ]);
        $all->assertOk()->assertJsonPath('total', 17);
        $this->assertEqualsCanonicalizing([...$duplicateIds, $unique->id], $all->json('ids'));
    }

    public function test_ids_endpoint_requires_select_all(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedPending('Alpha One');

        $response = $this->postJson(route('transaction-events.transfer-selected.ids'), [
            'event_ids' => [1],
        ]);

        $response->assertStatus(422);
    }

    public function test_ids_endpoint_blocked_for_viewers(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $this->seedPending('Alpha One');

        $response = $this->postJson(route('transaction-events.transfer-selected.ids'), [
            'select_all' => 1,
        ]);

        $response->assertForbidden();
    }
}
