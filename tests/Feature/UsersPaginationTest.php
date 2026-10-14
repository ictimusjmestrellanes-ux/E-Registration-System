<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_page_uses_bootstrap_pagination_and_keeps_filters(): void
    {
        $admin = User::factory()->create([
            'role_name' => User::ROLE_ADMIN,
            'status' => 'Active',
        ]);

        User::factory()->count(11)->create([
            'name' => 'Matching User',
            'role_name' => User::ROLE_STAFF,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->get(route('users.index', [
            'search' => 'Matching',
            'role' => User::ROLE_STAFF,
            'status' => 'Active',
        ]));

        $response
            ->assertOk()
            ->assertSee('class="pagination"', false)
            ->assertSee('role=Staff', false)
            ->assertSee('status=Active', false)
            ->assertSee('search=Matching', false);
    }
}
