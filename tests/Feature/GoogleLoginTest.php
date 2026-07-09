<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_and_logs_in_user(): void
    {
        Socialite::fake('google', (new SocialiteUser)->setRaw([
            'sub' => 'google-123',
            'email' => 'new.user@example.com',
            'email_verified' => true,
            'name' => 'New User',
            'picture' => 'https://example.com/avatar.png',
        ])->map([
            'id' => 'google-123',
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new.user@example.com',
            'google_id' => 'google-123',
            'status' => 'Active',
            'role_name' => 'User',
        ]);
    }

    public function test_google_callback_links_existing_active_user(): void
    {
        $user = User::factory()->create([
            'email' => 'existing.user@example.com',
            'status' => 'Active',
            'google_id' => null,
        ]);

        Socialite::fake('google', (new SocialiteUser)->setRaw([
            'sub' => 'google-456',
            'email' => 'existing.user@example.com',
            'email_verified' => true,
            'name' => 'Existing User',
        ])->map([
            'id' => 'google-456',
            'name' => 'Existing User',
            'email' => 'existing.user@example.com',
        ]));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-456',
        ]);
    }
}
