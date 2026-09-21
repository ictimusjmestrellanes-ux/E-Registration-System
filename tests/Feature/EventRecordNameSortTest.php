<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $firstPage = $this->get(route('transaction-events.records', ['sort' => 'client_asc']))
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

        $events = $this->get(route('transaction-events.index', ['sort' => 'client_asc']))
            ->assertOk()->viewData('events');
        $this->assertSame(
            ['ABA, HONEY', 'ABAD, ANGELO', 'REYES, ABAD ARTJAY'],
            $events->pluck('display_name')->all()
        );
    }
}
