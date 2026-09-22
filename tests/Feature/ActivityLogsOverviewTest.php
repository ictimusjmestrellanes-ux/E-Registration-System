<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function createLog(
        User $user,
        string $description,
        int $minutesAgo,
        string $action = 'login'
    ): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_all_tab_paginates_every_log_visible_to_the_user(): void
    {
        $staff = User::factory()->create(['role_name' => 'Staff']);
        $other = User::factory()->create(['role_name' => 'Staff']);

        for ($i = 1; $i <= 11; $i++) {
            $this->createLog($staff, "Own activity {$i}", $i);
        }
        $this->createLog($other, 'Another user activity', 20);

        $response = $this->actingAs($staff)->get(route('activity.logs', ['overview_page' => 2]));

        $response->assertOk()
            ->assertSee('href="#all-tab"', false)
            ->assertSee('All</a>', false)
            ->assertSee('Own activity 11')
            ->assertDontSee('Another user activity');
    }

    public function test_overview_filters_logs_by_search_and_action(): void
    {
        $staff = User::factory()->create(['role_name' => 'Staff']);
        $this->createLog($staff, 'Matching profile update', 1, 'profile_updated');
        $this->createLog($staff, 'Unrelated login', 2);

        $response = $this->actingAs($staff)->get(route('activity.logs', [
            'overview_search' => 'Matching',
            'overview_action' => 'profile_updated',
        ]));

        $response->assertOk()
            ->assertSee('name="overview_search"', false)
            ->assertSee('name="overview_action"', false)
            ->assertViewHas('allActivities', function ($activities) {
                return $activities->total() === 1
                    && $activities->first()->description === 'Matching profile update';
            });
    }

    public function test_admin_can_filter_overview_by_user(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $firstUser = User::factory()->create(['role_name' => 'Staff']);
        $secondUser = User::factory()->create(['role_name' => 'Staff']);
        $this->createLog($firstUser, 'First user activity', 1);
        $this->createLog($secondUser, 'Second user activity', 2);

        $response = $this->actingAs($admin)->get(route('activity.logs', [
            'overview_user' => $firstUser->id,
        ]));

        $response->assertOk()
            ->assertSee('name="overview_user"', false)
            ->assertViewHas('allActivities', function ($activities) use ($firstUser) {
                return $activities->total() === 1
                    && $activities->first()->user_id === $firstUser->id;
            });
    }
}
