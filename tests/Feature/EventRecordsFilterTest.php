<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_filter_by_client_category(): void
    {
        // role_name with no permission rows => all unregistered features allowed.
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        TransactionEvent::create([
            'full_name' => 'Alpha One', 'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09', 'transferred_at' => now(),
        ]);
        TransactionEvent::create([
            'full_name' => 'Beta Two', 'client_category' => 'LUPON',
            'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09', 'transferred_at' => now(),
        ]);

        $response = $this->get(route('transaction-events.records', ['client_category' => 'LUPON']));
        $response->assertOk();
        $response->assertSee('Beta Two');
        $response->assertDontSee('Alpha One');
        // Dropdown is populated.
        $response->assertSee('All client categories');
        $response->assertSee('INDIGENT', false);
    }

    public function test_undo_ids_respects_client_category_filter(): void
    {
        $this->actingAs(User::factory()->create());

        $a = TransactionEvent::create([
            'full_name' => 'Alpha One', 'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09', 'transferred_at' => now(),
        ]);
        $b = TransactionEvent::create([
            'full_name' => 'Beta Two', 'client_category' => 'LUPON',
            'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09', 'transferred_at' => now(),
        ]);

        $response = $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'client_category' => 'INDIGENT',
        ]);
        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $this->assertEquals([$a->id], $response->json('ids'));
    }
}
