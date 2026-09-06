<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerPermissionLeakTest extends TestCase
{
    use RefreshDatabase;

    private function seedViewerWithDashboardOnly(): void
    {
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Viewer']);
        Permission::firstOrCreate([
            'feature' => 'Dashboard',
            'role_name' => 'Viewer',
        ], ['allowed' => true]);
        // NOTE: deliberately no 'Duplicate Clients Review' row — the leak state.
    }

    public function test_sync_backfills_missing_viewer_row_as_disallowed(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedViewerWithDashboardOnly();

        $this->assertDatabaseMissing('permissions', [
            'feature' => 'Duplicate Clients Review',
            'role_name' => 'Viewer',
        ]);

        // Visiting the Permissions page heals the matrix.
        $this->get(route('permissions.index'))->assertOk();

        $this->assertDatabaseHas('permissions', [
            'feature' => 'Duplicate Clients Review',
            'role_name' => 'Viewer',
            'allowed' => false,
        ]);
        $this->assertDatabaseHas('permissions', [
            'feature' => 'Duplicate Clients Review',
            'role_name' => 'Admin',
            'allowed' => true,
        ]);
    }

    public function test_viewer_with_dashboard_only_cannot_open_duplicate_clients_review(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedViewerWithDashboardOnly();
        $this->get(route('permissions.index'))->assertOk(); // trigger sync

        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        // Abort happens before any data query, so this is DB-safe.
        $this->assertFalse(feature_allowed('Duplicate Clients Review'));
        $this->assertTrue(feature_allowed('Dashboard'));
        $this->get(route('duplicate.review'))->assertNotFound();
    }

    public function test_admin_keeps_access_after_sync(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedViewerWithDashboardOnly();
        $this->get(route('permissions.index'))->assertOk(); // trigger sync

        $this->assertTrue(feature_allowed('Duplicate Clients Review'));
    }
}
