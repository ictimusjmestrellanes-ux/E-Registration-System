<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_list_defaults_to_displayed_name_and_has_sortable_data_headers(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600002',
            'first_name' => 'BAKER',
            'last_name' => 'BRAVO',
            'age' => 20,
            'gender' => 'Male',
            'contact' => '0999',
            'address' => 'Zulu Street',
        ]);
        Client::create([
            'client_id' => '2600001',
            'first_name' => 'ALDEA',
            'middle_name' => 'LORETO',
            'last_name' => 'Z.',
            'age' => 40,
            'gender' => 'Female',
            'contact' => '0111',
            'address' => 'Alpha Street',
        ]);

        $response = $this->get(route('client.list'))->assertOk();
        $this->assertSame(
            ['ALDEA, LORETO Z.', 'BRAVO, BAKER'],
            $response->viewData('clients')->pluck('list_display_name')->all()
        );

        foreach (['clientid_asc', 'name_desc', 'gender_asc', 'age_asc', 'contact_asc', 'address_asc'] as $sort) {
            $response->assertSee('sort='.$sort, false);
        }

        $descending = $this->get(route('client.list', ['sort' => 'name_desc']))
            ->assertOk()
            ->viewData('clients');
        $this->assertSame(
            ['BRAVO, BAKER', 'ALDEA, LORETO Z.'],
            $descending->pluck('list_display_name')->all()
        );
    }

    public function test_client_list_sorts_other_columns_across_the_query(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600002',
            'first_name' => 'Ana',
            'last_name' => 'Zulu',
            'age' => 45,
            'address' => 'Beta Street',
        ]);
        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Ben',
            'last_name' => 'Alpha',
            'age' => 20,
            'address' => 'Alpha Street',
        ]);

        $byAge = $this->get(route('client.list', ['sort' => 'age_asc']))
            ->assertOk()->viewData('clients');
        $this->assertSame(['2600001', '2600002'], $byAge->pluck('client_id')->all());

        $byAddress = $this->get(route('client.list', ['sort' => 'address_desc']))
            ->assertOk()->viewData('clients');
        $this->assertSame(['2600002', '2600001'], $byAddress->pluck('client_id')->all());
    }
}
