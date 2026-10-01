<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_with_an_existing_session_is_logged_out(): void
    {
        $user = User::factory()->create([
            'status' => 'Inactive',
            'role_name' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account is not active.');
        $this->assertGuest();
    }

    public function test_inactive_session_cannot_bypass_the_check_through_microsoft_login(): void
    {
        $user = User::factory()->create([
            'status' => 'Inactive',
            'role_name' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)->get(route('azure.redirect'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account is not active.');
        $this->assertGuest();
    }

    public function test_active_user_session_is_not_terminated(): void
    {
        $user = User::factory()->create([
            'status' => 'Active',
            'role_name' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
