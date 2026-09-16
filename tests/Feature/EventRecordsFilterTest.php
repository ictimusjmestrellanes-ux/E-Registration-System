<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_addresses_filter_both_lists_exports_and_bulk_ids(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        foreach ([false, true] as $transferred) {
            $records = collect(['North, City', 'South', 'Other'])->map(fn ($address) => TransactionEvent::create([
                'full_name' => ($transferred ? 'Record ' : 'Import ').$address,
                'address' => $address,
                'transferred_at' => $transferred ? now() : null,
            ]));
            $filters = ['address' => [' north, city ', 'South']];
            $route = $transferred ? 'transaction-events.records' : 'transaction-events.index';
            $idsRoute = $transferred ? 'transaction-events.undo-transfer-selected.ids' : 'transaction-events.transfer-selected.ids';
            $exportRoute = $transferred ? 'transaction-events.records.export' : 'transaction-events.export';
            $expectedIds = $records->take(2)->pluck('id')->sort()->values()->all();

            $this->get(route($route, $filters))->assertOk()
                ->assertViewHas('events', fn ($events) => $events->total() === 2
                    && $events->pluck('id')->sort()->values()->all() === $expectedIds);
            $ids = $this->postJson(route($idsRoute), $filters + ['select_all' => 1])
                ->assertOk()->assertJsonPath('total', 2)->json('ids');
            sort($ids);
            $this->assertSame($expectedIds, $ids);

            $export = $this->get(route($exportRoute, $filters))->assertOk();
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($export->getFile()->getPathname()));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString($records[0]->full_name, $sheet);
            $this->assertStringContainsString($records[1]->full_name, $sheet);
            $this->assertStringNotContainsString($records[2]->full_name, $sheet);
        }
    }

    public function test_address_type_options_use_all_records_in_each_pages_scope(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        foreach ([false, true] as $transferred) {
            foreach (['Type A' => 'North', 'Type B' => 'South'] as $type => $address) {
                TransactionEvent::create([
                    'full_name' => ($transferred ? 'Record' : 'Pending').' '.$type,
                    'transaction_type' => $type,
                    'address' => ' '.$address.($transferred ? ' Records' : ' Imports').' ',
                    'transferred_at' => $transferred ? now() : null,
                ]);
            }
        }
        TransactionEvent::create([
            'full_name' => 'Excluded pending event', 'not_duplicate' => true,
            'transaction_type' => 'Type A', 'address' => 'Excluded',
        ]);

        foreach ([false, true] as $transferred) {
            $suffix = $transferred ? ' Records' : ' Imports';
            $route = $transferred ? 'transaction-events.records' : 'transaction-events.index';
            $this->get(route($route, ['transaction_type' => ['Type A']]))->assertOk()
                ->assertViewHas('addressTypes', fn ($options) => $options->all() === [
                    ['address' => 'North'.$suffix, 'type' => 'Type A'],
                    ['address' => 'South'.$suffix, 'type' => 'Type B'],
                ]);
        }
    }

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

    public function test_address_dropdown_filters_lists_exports_and_select_all_for_both_pages(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        foreach ([false, true] as $transferred) {
            $matching = TransactionEvent::create([
                'full_name' => ($transferred ? 'Transferred' : 'Pending').' Address Match',
                'address' => 'Pasong Buaya II',
                'transferred_at' => $transferred ? now() : null,
            ]);
            $other = TransactionEvent::create([
                'full_name' => ($transferred ? 'Transferred' : 'Pending').' Other Address',
                'address' => 'Buhay Na Tubig',
                'transferred_at' => $transferred ? now() : null,
            ]);

            $listRoute = $transferred ? 'transaction-events.records' : 'transaction-events.index';
            $exportRoute = $transferred ? 'transaction-events.records.export' : 'transaction-events.export';
            $idsRoute = $transferred ? 'transaction-events.undo-transfer-selected.ids' : 'transaction-events.transfer-selected.ids';
            $filters = ['address' => 'Pasong Buaya II'];

            $this->get(route($listRoute, $filters))->assertOk()
                ->assertSee('name="address[]"', false)
                ->assertSee('All addresses')
                ->assertSee('Pasong Buaya II')
                ->assertSee($matching->full_name)
                ->assertDontSee($other->full_name)
                ->assertViewHas('events', fn ($events) => $events->total() === 1 && $events->first()->is($matching));

            $this->postJson(route($idsRoute), $filters + ['select_all' => 1])
                ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('ids.0', $matching->id);

            $export = $this->get(route($exportRoute, $filters))->assertOk();
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($export->getFile()->getPathname()));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString($matching->full_name, $sheet);
            $this->assertStringNotContainsString($other->full_name, $sheet);
        }
    }
}
