<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use App\Support\ImportName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_events_and_event_records_show_full_middle_names_and_initials(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $pending = TransactionEvent::create(['full_name' => 'Juan Maria Santos']);
        $transferred = TransactionEvent::create([
            'full_name' => 'Ana M. Reyes',
            'transferred_at' => now(),
        ]);

        $this->assertSame('Santos, Juan Maria', $pending->display_name);
        $this->assertSame('Reyes, Ana M.', $transferred->display_name);

        $importList = $this->get(route('transaction-events.index'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<td data-column="full_name" class="fw-semibold"\s+title="Juan Maria Santos">Santos, Juan Maria<\/td>/',
            $importList->getContent()
        );

        $filteredImportList = $this->get(route('transaction-events.index', ['search' => 'Santos, Juan Maria']))
            ->assertOk();
        $this->assertMatchesRegularExpression(
            '/<td data-column="full_name" class="fw-semibold"\s+title="Juan Maria Santos">Santos, Juan Maria<\/td>/',
            $filteredImportList->getContent()
        );

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertSee('<td data-column="full_name" class="fw-semibold" title="Ana M. Reyes">Reyes, Ana M.</td>', false);

        $this->get(route('transaction-events.records', ['search' => 'Reyes, Ana M.']))
            ->assertOk()
            ->assertSee('<td data-column="full_name" class="fw-semibold" title="Ana M. Reyes">Reyes, Ana M.</td>', false);
    }

    public function test_formatter_preserves_comma_names_full_middle_names_and_suffixes(): void
    {
        $this->assertSame('Dela Cruz, Juan Carlos Maria', ImportName::format('Dela Cruz, Juan Carlos Maria'));
        $this->assertSame('Dela Cruz, Juan Carlos P.', ImportName::format('Juan Carlos P Dela Cruz'));
        $this->assertSame('Mercado, Jose P. JR', ImportName::format('Jose P. Mercado Jr.'));
    }

    public function test_trailing_middle_initial_is_not_displayed_as_the_last_name(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $pending = TransactionEvent::create(['full_name' => 'ALDEA LORETO A.']);
        $transferred = TransactionEvent::create([
            'full_name' => 'AUSAN BABY RHEA A.',
            'transferred_at' => now(),
        ]);

        $this->assertSame('ALDEA, LORETO A.', $pending->display_name);
        $this->assertSame('AUSAN, BABY RHEA A.', $transferred->display_name);
        $this->assertSame([
            'first' => 'LORETO',
            'middle' => 'A.',
            'last' => 'ALDEA',
            'suffix' => '',
        ], ImportName::split('ALDEA LORETO A.'));

        $this->get(route('transaction-events.index'))
            ->assertOk()
            ->assertSee('ALDEA, LORETO A.');

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertSee('AUSAN, BABY RHEA A.');
    }
}
