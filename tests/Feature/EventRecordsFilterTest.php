<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_date_range_filters_records_export_and_select_all_ids(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $records = collect(['2026-03-08', '2026-03-09', '2026-03-10', '2026-03-11', null])
            ->map(fn ($date, $index) => TransactionEvent::create([
                'full_name' => 'Date Filter Person '.$index,
                'event_date' => $date,
                'transferred_at' => '2026-04-01 12:00:00',
            ]));
        TransactionEvent::create([
            'full_name' => 'Pending Date Match',
            'event_date' => '2026-03-09',
        ]);

        foreach ([
            ['event_date_from' => '2026-03-09'],
            ['event_date_to' => '2026-03-10'],
            ['event_date_from' => '2026-03-09', 'event_date_to' => '2026-03-10'],
        ] as $filters) {
            $expected = $records->filter(fn ($record) => $record->event_date !== null
                && (!isset($filters['event_date_from']) || $record->event_date->format('Y-m-d') >= $filters['event_date_from'])
                && (!isset($filters['event_date_to']) || $record->event_date->format('Y-m-d') <= $filters['event_date_to']));

            $response = $this->get(route('transaction-events.records', $filters));
            $response->assertOk()->assertSee('Event Date From')->assertSee('Event Date To');
            $response->assertViewHas('events', fn ($events) => $events->total() === $expected->count()
                && $events->getCollection()->pluck('id')->sort()->values()->all() === $expected->pluck('id')->sort()->values()->all());

            $this->postJson(route('transaction-events.undo-transfer-selected.ids'), $filters + ['select_all' => 1])
                ->assertOk()->assertJsonPath('total', $expected->count())
                ->assertJsonFragment(['ids' => $expected->pluck('id')->values()->all()]);

            $export = $this->get(route('transaction-events.records.export', $filters));
            $export->assertOk();
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($export->getFile()->getPathname()));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            foreach ($records as $record) {
                if ($expected->contains('id', $record->id)) {
                    $this->assertStringContainsString($record->full_name, $sheet);
                } else {
                    $this->assertStringNotContainsString($record->full_name, $sheet);
                }
            }
            $this->assertStringNotContainsString('Pending Date Match', $sheet);
            $zip->close();
        }
    }

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
