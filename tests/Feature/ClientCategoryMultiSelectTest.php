<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCategoryMultiSelectTest extends TestCase
{
    use RefreshDatabase;

    private function seedPending(string $name, string $clientCategory, string $type = 'TRANCH 1'): TransactionEvent
    {
        return TransactionEvent::create([
            'full_name' => $name,
            'client_category' => $clientCategory,
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => $type,
            'event_date' => '2026-03-09',
            'transferred_at' => null,
            'not_duplicate' => false,
        ]);
    }

    private function seedRecord(string $name, string $clientCategory): TransactionEvent
    {
        return TransactionEvent::create([
            'full_name' => $name,
            'client_category' => $clientCategory,
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => now(),
        ]);
    }

    public function test_event_list_filters_by_multiple_client_categories(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedPending('Alpha One', 'INDIGENT');
        $this->seedPending('Beta Two', 'LUPON');
        $this->seedPending('Gamma Three', 'PWD');

        $response = $this->get(route('transaction-events.index', [
            'client_category' => ['INDIGENT', 'LUPON'],
        ]));

        $response->assertOk();
        $response->assertSee('Alpha One');
        $response->assertSee('Beta Two');
        $response->assertDontSee('Gamma Three');
    }

    public function test_event_list_filters_by_multiple_transaction_types(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedPending('Alpha One', 'INDIGENT', 'TRANCH 1');
        $this->seedPending('Beta Two', 'INDIGENT', 'TRANCH 2');
        $this->seedPending('Gamma Three', 'INDIGENT', 'TRANCH 3');

        $response = $this->get(route('transaction-events.index', [
            'transaction_type' => ['TRANCH 1', 'TRANCH 2'],
        ]));

        $response->assertOk();
        $response->assertSee('Alpha One');
        $response->assertSee('Beta Two');
        $response->assertDontSee('Gamma Three');
    }

    public function test_event_list_single_client_category_still_works(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedPending('Alpha One', 'INDIGENT');
        $this->seedPending('Beta Two', 'LUPON');

        $response = $this->get(route('transaction-events.index', [
            'client_category' => 'LUPON',
        ]));

        $response->assertOk();
        $response->assertSee('Beta Two');
        $response->assertDontSee('Alpha One');
    }

    public function test_records_filter_by_multiple_client_categories(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedRecord('Alpha One', 'INDIGENT');
        $this->seedRecord('Beta Two', 'LUPON');
        $this->seedRecord('Gamma Three', 'PWD');

        $response = $this->get(route('transaction-events.records', [
            'client_category' => ['INDIGENT', 'LUPON'],
        ]));

        $response->assertOk();
        $response->assertSee('Alpha One');
        $response->assertSee('Beta Two');
        $response->assertDontSee('Gamma Three');
    }

    public function test_undo_ids_respects_multiple_client_categories(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $a = $this->seedRecord('Alpha One', 'INDIGENT');
        $b = $this->seedRecord('Beta Two', 'LUPON');
        $this->seedRecord('Gamma Three', 'PWD');

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'client_category' => ['INDIGENT', 'LUPON'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $response->json('ids'));
    }
}
