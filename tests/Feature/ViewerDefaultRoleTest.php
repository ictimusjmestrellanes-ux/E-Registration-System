<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OAuthUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class ViewerDefaultRoleTest extends TestCase
{
    use RefreshDatabase;

    private function setUpOAuthFakes(): void
    {
        Http::fake();
        Storage::fake('public');
    }

    private function azureSocialiteUser(string $email = 'new.viewer@cityofimus.gov.ph'): SocialiteUser
    {
        return (new SocialiteUser)->setRaw([
            'sub' => 'azure-viewer-1',
            'email' => $email,
            'email_verified' => true,
            'name' => 'New Viewer',
        ])->map([
            'id' => 'azure-viewer-1',
            'name' => 'New Viewer',
            'email' => $email,
        ]);
    }

    public function test_new_oauth_user_defaults_to_viewer_role(): void
    {
        $this->setUpOAuthFakes();

        $user = app(OAuthUserService::class)->findOrCreateUser('azure', $this->azureSocialiteUser());

        $this->assertSame(User::ROLE_VIEWER, $user->role_name);
        $this->assertDatabaseHas('users', [
            'email' => 'new.viewer@cityofimus.gov.ph',
            'role_name' => User::ROLE_VIEWER,
            'status' => 'Active',
        ]);
    }

    public function test_existing_user_keeps_their_role_on_login(): void
    {
        $this->setUpOAuthFakes();

        User::factory()->create([
            'email' => 'existing.viewer@cityofimus.gov.ph',
            'role_name' => 'Admin',
            'status' => 'Active',
        ]);

        $user = app(OAuthUserService::class)->findOrCreateUser('azure', $this->azureSocialiteUser('existing.viewer@cityofimus.gov.ph'));

        $this->assertSame('Admin', $user->role_name);
    }

    public function test_new_viewer_only_gates_from_dashboard(): void
    {
        $this->setUpOAuthFakes();

        Role::firstOrCreate(['name' => 'Viewer']);
        Permission::firstOrCreate([
            'feature' => 'Dashboard',
            'role_name' => 'Viewer',
        ], ['allowed' => true]);
        Permission::firstOrCreate([
            'feature' => 'Duplicate Clients Review',
            'role_name' => 'Viewer',
        ], ['allowed' => false]);
        Permission::firstOrCreate([
            'feature' => 'Client List',
            'role_name' => 'Viewer',
        ], ['allowed' => false]);

        $user = app(OAuthUserService::class)->findOrCreateUser('azure', $this->azureSocialiteUser());

        $this->actingAs($user);

        $this->assertTrue(feature_allowed('Dashboard'));
        $this->assertFalse(feature_allowed('Duplicate Clients Review'));
        $this->assertFalse(feature_allowed('Client List'));

        $this->assertTrue(feature_allowed_uri('dashboard'));
        $this->assertFalse(feature_allowed_uri('duplicate-review'));
        $this->assertFalse(feature_allowed_uri('client-list'));
    }

    public function test_dashboard_is_allowed_by_default_for_viewer_in_sync(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Viewer']);
        Role::firstOrCreate(['name' => 'Admin']);

        $this->actingAs($admin);

        // Permissions page self-heals the matrix; Dashboard must be allowed.
        $this->get(route('permissions.index'))->assertOk();

        $this->assertDatabaseHas('permissions', [
            'feature' => 'Dashboard',
            'role_name' => 'Viewer',
            'allowed' => true,
        ]);
        $this->assertDatabaseHas('permissions', [
            'feature' => 'Duplicate Clients Review',
            'role_name' => 'Viewer',
            'allowed' => false,
        ]);
    }
}