<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
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

    public function test_admin_overview_only_shows_their_own_logs(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $staff = User::factory()->create(['role_name' => 'Staff']);
        $this->createLog($admin, 'Admin activity', 1);
        $this->createLog($staff, 'Staff activity', 2);

        $response = $this->actingAs($admin)->get(route('activity.logs'));

        $response->assertOk()
            ->assertDontSee('name="overview_user"', false)
            ->assertViewHas('allActivities', function ($activities) use ($admin) {
                return $activities->total() === 1
                    && $activities->first()->user_id === $admin->id;
            });
    }

    public function test_transaction_history_update_entries_are_hidden_from_activity_lists(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $this->createLog(
            $admin,
            'Updated Transaction History #8115 (2608114-26-0001).',
            1,
            'transaction_history_updated'
        );
        $this->createLog($admin, 'Visible event update.', 2, 'transaction_event_updated');

        $response = $this->actingAs($admin)->get(route('activity.logs'));

        $response->assertOk()
            ->assertDontSee('Updated Transaction History #8115')
            ->assertSee('Visible event update.')
            ->assertViewHas('allActivities', fn ($activities) => $activities->total() === 1)
            ->assertViewHas('activities', fn ($activities) => $activities->total() === 1)
            ->assertViewHas('uniqueActions', fn ($actions) => ! $actions->contains('transaction_history_updated'));
    }

    public function test_activity_logs_page_exposes_live_updates_and_live_state_respects_visibility(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $staff = User::factory()->create(['role_name' => 'Staff']);
        $this->createLog($staff, 'Staff activity', 2);
        $staffLogId = ActivityLog::max('id');
        $this->createLog($admin, 'Admin activity', 1);
        $adminLogId = ActivityLog::max('id');
        $this->createLog($admin, 'Hidden history update', 0, 'transaction_history_updated');

        $this->actingAs($staff)
            ->get(route('activity.logs'))
            ->assertOk()
            ->assertSee('id="activityLogsLive"', false)
            ->assertSee('data-live-url="'.route('activity.logs.live-state').'"', false)
            ->assertSee('New activity logs are available.');
        $this->getJson(route('activity.logs.live-state'))
            ->assertOk()
            ->assertJsonPath('overview_latest_id', $staffLogId)
            ->assertJsonPath('activities_latest_id', $staffLogId);

        $this->actingAs($admin)
            ->getJson(route('activity.logs.live-state'))
            ->assertOk()
            ->assertJsonPath('overview_latest_id', $adminLogId)
            ->assertJsonPath('activities_latest_id', $adminLogId);
    }

    public function test_activities_tab_searches_client_id_and_name_in_log_details(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $client = Client::create([
            'client_id' => '2600456',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
        ]);
        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'transaction_id' => '2600456-26-0001',
            'transaction_date' => '2026-09-30',
            'category' => 'EVENTS',
            'type' => 'TRANCH 1',
        ]);
        $event = TransactionEvent::create([
            'full_name' => 'CRUZ, JUAN DELA',
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'event_status_tagged',
            'subject_type' => 'TransactionEvent',
            'subject_id' => $event->id,
            'description' => 'Updated an event record.',
            'properties' => [
                'event_id' => $event->id,
                'status' => 'Claimed',
            ],
        ]);
        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'login',
            'description' => 'Unrelated activity.',
        ]);

        $this->actingAs($admin)
            ->get(route('activity.logs', [
                'search' => '2600456',
                'period' => 'all',
                'action' => '',
            ]))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) {
                return $activities->total() === 1
                    && $activities->first()->action === 'event_status_tagged';
            });

        $this->actingAs($admin)
            ->get(route('activity.logs', [
                'search' => 'JUAN DELA CRUZ',
                'period' => 'all',
                'action' => '',
            ]))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) {
                return $activities->total() === 1
                    && $activities->first()->action === 'event_status_tagged';
            })
            ->assertSee('Client ID / Full Name')
            ->assertSee('Client ID')
            ->assertSee('2600456')
            ->assertSee('CRUZ, JUAN DELA')
            ->assertDontSee('Event ID');
    }
}
