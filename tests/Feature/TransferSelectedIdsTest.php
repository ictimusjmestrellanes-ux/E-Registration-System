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
