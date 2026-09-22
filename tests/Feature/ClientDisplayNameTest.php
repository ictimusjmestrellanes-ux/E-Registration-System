<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_list_and_details_show_last_first_full_middle_name_and_suffix(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Maria',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
        ]);

        $this->assertSame('DELA CRUZ, JUAN MARIA JR.', $client->full_name);
        $this->assertSame('DELA CRUZ, JUAN MARIA JR.', $client->list_display_name);

        $list = $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-client-name="DELA CRUZ, JUAN MARIA JR."', false);
        $this->assertMatchesRegularExpression('/<td>\s*DELA CRUZ, JUAN MARIA JR\.\s*<\/td>/', $list->getContent());

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<div class="fs-4 fw-bold">DELA CRUZ, JUAN MARIA JR.</div>', false);

        $this->get(route('client.list', ['search' => 'Dela Cruz, Juan Maria']))
            ->assertOk()
            ->assertSee('DELA CRUZ, JUAN MARIA JR.');

        $this->get(route('client.list', ['search' => 'Dela Cruz, Juan M.']))
            ->assertOk()
            ->assertSee('DELA CRUZ, JUAN MARIA JR.');

        $this->getJson(route('client.list.preview-without-transactions'))
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'DELA CRUZ, JUAN MARIA JR.');
    }

    public function test_single_letter_middle_name_is_shown_as_an_initial(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $client = Client::create([
            'client_id' => '2600002',
            'first_name' => 'Juan',
            'middle_name' => 'M',
            'last_name' => 'Dela Cruz',
        ]);

        $this->assertSame('DELA CRUZ, JUAN M.', $client->full_name);
        $this->assertSame('DELA CRUZ, JUAN M.', $client->list_display_name);

        $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-client-name="DELA CRUZ, JUAN M."', false);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<div class="fs-4 fw-bold">DELA CRUZ, JUAN M.</div>', false);

        $client->middle_name = 'M.';
        $this->assertSame('DELA CRUZ, JUAN M.', $client->list_display_name);
    }

    public function test_missing_middle_name_has_no_trailing_initial(): void
    {
        $client = new Client([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
        ]);

        $this->assertSame('REYES, ANA', $client->full_name);
        $this->assertSame($client->full_name, $client->list_display_name);
    }

    public function test_legacy_client_with_middle_initial_in_last_name_displays_in_the_intended_order(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $client = Client::create([
            'client_id' => '2600003',
            'first_name' => 'ALDEA',
            'middle_name' => 'LORETO',
            'last_name' => 'A.',
        ]);

        $this->assertSame('ALDEA, LORETO A.', $client->full_name);
        $this->assertSame($client->full_name, $client->list_display_name);

        $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-client-name="ALDEA, LORETO A."', false);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<div class="fs-4 fw-bold">ALDEA, LORETO A.</div>', false);
    }

    public function test_dash_placeholder_is_displayed_as_the_middle_name_not_the_last_name(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $client = Client::create([
            'client_id' => '2626870',
            'first_name' => 'LEGASPI,',
            'middle_name' => 'JOBILLEE',
            'last_name' => '----',
        ]);

        $this->assertSame('LEGASPI, JOBILLEE ----', $client->full_name);

        $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-client-name="LEGASPI, JOBILLEE ----"', false)
            ->assertDontSee('data-client-name="----, LEGASPI, JOBILLEE"', false);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<div class="fs-4 fw-bold">LEGASPI, JOBILLEE ----</div>', false);
    }

    public function test_client_list_and_details_prefer_the_linked_event_record_full_name(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $client = Client::create([
            'client_id' => '2611172',
            'first_name' => 'AALA',
            'middle_name' => 'ERNESTO',
            'last_name' => 'ESTACIO',
            'suffix' => 'JR',
        ]);
        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'transaction_id' => '2611172-26-0001',
            'transaction_date' => '2026-09-01',
            'category' => 'events',
            'type' => 'Assistance',
            'status' => 'Pending',
        ]);
        TransactionEvent::create([
            'full_name' => 'AALA, ERNESTO ESTACIO JR',
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-client-name="AALA, ERNESTO ESTACIO JR"', false)
            ->assertDontSee('data-client-name="ESTACIO, AALA ERNESTO JR"', false);

        $this->get(route('client.list', ['search' => 'AALA, ERNESTO ESTACIO JR']))
            ->assertOk()
            ->assertSee('data-client-row="'.$client->id.'"', false);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<div class="fs-4 fw-bold">AALA, ERNESTO ESTACIO JR</div>', false)
            ->assertDontSee('<div class="fs-4 fw-bold">ESTACIO, AALA ERNESTO JR</div>', false);
    }

    public function test_search_finds_clients_using_names_copied_from_event_records(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $clientWithFullMiddle = Client::create([
            'client_id' => '2600101',
            'first_name' => 'Juan',
            'middle_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        $clientWithInitial = Client::create([
            'client_id' => '2600102',
            'first_name' => 'Ana',
            'middle_name' => 'M.',
            'last_name' => 'Reyes',
        ]);
        $clientWithSuffix = Client::create([
            'client_id' => '2600103',
            'first_name' => 'Jose',
            'middle_name' => 'Pedro',
            'last_name' => 'Mercado',
            'suffix' => 'JR',
        ]);

        foreach ([
            [$clientWithFullMiddle, 'Juan M. Santos'],
            [$clientWithInitial, 'Ana Maria Reyes'],
            [$clientWithSuffix, 'Jose P. Mercado Jr.'],
        ] as [$client, $sourceName]) {
            $copiedName = (new TransactionEvent(['full_name' => $sourceName]))->display_name;

            $this->get(route('client.list', ['search' => $copiedName]))
                ->assertOk()
                ->assertSee('data-client-row="'.$client->id.'"', false);
        }
    }
}
