<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_list_and_details_show_last_first_and_full_middle_name(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Maria',
            'last_name' => 'Dela Cruz',
        ]);

        $this->assertSame('DELA CRUZ, JUAN MARIA', $client->list_display_name);

        $list = $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-client-name="DELA CRUZ, JUAN MARIA"', false);
        $this->assertMatchesRegularExpression('/<td>\s*DELA CRUZ, JUAN MARIA\s*<\/td>/', $list->getContent());

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<div class="fs-4 fw-bold">DELA CRUZ, JUAN MARIA</div>', false);

        $this->get(route('client.list', ['search' => 'Dela Cruz, Juan Maria']))
            ->assertOk()
            ->assertSee('DELA CRUZ, JUAN MARIA');

        $this->get(route('client.list', ['search' => 'Dela Cruz, Juan M.']))
            ->assertOk()
            ->assertSee('DELA CRUZ, JUAN MARIA');

        $this->getJson(route('client.list.preview-without-transactions'))
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'DELA CRUZ, JUAN MARIA');
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

        $this->assertSame('REYES, ANA', $client->list_display_name);
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
