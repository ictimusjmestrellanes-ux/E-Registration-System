<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventRecordNameSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_name_sort_uses_the_displayed_name_across_pages(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        for ($number = 1; $number <= 10; $number++) {
            TransactionEvent::create([
                'full_name' => sprintf('ABA, PERSON %02d', $number),
                'transferred_at' => now(),
            ]);
        }
        TransactionEvent::create(['full_name' => 'ABAD, ANGELO', 'transferred_at' => now()]);
        TransactionEvent::create(['full_name' => 'ABAD ARTJAY REYES', 'transferred_at' => now()]);

        $firstPage = $this->get(route('transaction-events.records'))
            ->assertOk()->viewData('events');
        $this->assertSame(
            array_map(fn ($number) => sprintf('ABA, PERSON %02d', $number), range(1, 10)),
            $firstPage->pluck('display_name')->all()
        );

        $secondPage = $this->get(route('transaction-events.records', [
            'sort' => 'client_asc',
            'page' => 2,
        ]))->assertOk()->viewData('events');
        $this->assertSame(
            ['ABAD, ANGELO', 'REYES, ABAD ARTJAY'],
            $secondPage->pluck('display_name')->all()
        );

        $descending = $this->get(route('transaction-events.records', [
            'sort' => 'client_desc',
        ]))->assertOk()->viewData('events');
        $this->assertSame('REYES, ABAD ARTJAY', $descending->first()->display_name);
    }

    public function test_import_events_use_the_same_displayed_name_order(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        foreach (['ABAD ARTJAY REYES', 'ABAD, ANGELO', 'ABA, HONEY'] as $name) {
            TransactionEvent::create(['full_name' => $name]);
        }

        $events = $this->get(route('transaction-events.index'))
            ->assertOk()->viewData('events');
        $this->assertSame(
            ['ABA, HONEY', 'ABAD, ANGELO', 'REYES, ABAD ARTJAY'],
            $events->pluck('display_name')->all()
        );
    }

    public function test_display_name_sort_backfill_refreshes_existing_event_names(): void
    {
        $event = TransactionEvent::create(['full_name' => 'ALDEA LORETO A.']);
        DB::table('transaction_events')->where('id', $event->id)->update([
            'display_name_sort' => 'a., aldea loreto',
        ]);

        $migration = require database_path('migrations/2026_09_22_000001_refresh_transaction_event_display_name_sort.php');
        $migration->up();

        $this->assertDatabaseHas('transaction_events', [
            'id' => $event->id,
            'display_name_sort' => 'aldea, loreto a.',
        ]);
    }

    public function test_dash_placeholder_backfill_does_not_sort_dashes_as_the_last_name(): void
    {
        $event = TransactionEvent::create(['full_name' => 'LEGASPI JOBILLEE ----']);
        DB::table('transaction_events')->where('id', $event->id)->update([
            'display_name_sort' => '----, legaspi jobillee',
        ]);

        $migration = require database_path('migrations/2026_09_22_000002_refresh_dash_placeholder_display_name_sort.php');
        $migration->up();

        $this->assertDatabaseHas('transaction_events', [
            'id' => $event->id,
            'display_name_sort' => 'legaspi, jobillee ----',
        ]);
    }
}
