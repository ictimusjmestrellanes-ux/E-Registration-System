<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardActivityWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function seedLog(User $user, string $action, string $description, int $minutesAgo = 0): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_admin_sees_latest_six_across_users(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $other = User::factory()->create(['role_name' => 'Staff']);
        $this->actingAs($admin);

        for ($i = 1; $i <= 8; $i++) {
            $this->seedLog($i % 2 ? $admin : $other, 'login', "Log entry {$i}", 10 - $i);
        }

        $recent = app(ProfileController::class)->recentDashboardActivities();

        $this->assertCount(6, $recent);
        // Latest first.
        $this->assertSame('Log entry 8', $recent->first()->description);
        $this->assertSame('Log entry 3', $recent->last()->description);
        // Spans both users.
        $this->assertContains($other->id, $recent->pluck('user_id')->all());
    }

    public function test_non_admin_sees_only_own_activity(): void
    {
        $staff = User::factory()->create(['role_name' => 'Staff']);
        $other = User::factory()->create(['role_name' => 'Staff']);
        $this->actingAs($staff);

        $this->seedLog($staff, 'login', 'My login');
        $this->seedLog($other, 'login', 'Other login');

        $recent = app(ProfileController::class)->recentDashboardActivities();

        $this->assertCount(1, $recent);
        $this->assertSame('My login', $recent->first()->description);
    }

    public function test_widget_empty_without_activity_logs_feature(): void
    {
        Role::firstOrCreate(['name' => 'Viewer']);
        Permission::create([
            'feature' => 'Activity Logs',
            'role_name' => 'Viewer',
            'allowed' => false,
        ]);
        $viewer = User::factory()->create(['role_name' => 'Viewer']);
        $this->actingAs($viewer);

        $this->seedLog($viewer, 'login', 'Viewer login');

        $this->assertFalse(feature_allowed('Activity Logs'));
        $this->assertCount(0, app(ProfileController::class)->recentDashboardActivities());
    }
}
