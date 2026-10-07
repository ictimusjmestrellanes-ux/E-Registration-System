<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
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

    public function test_activities_tab_is_active_by_default(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);

        $this->actingAs($admin)
            ->get(route('activity.logs'))
            ->assertOk()
            ->assertSee('class="nav-link active" data-bs-toggle="tab"', false)
            ->assertSee('href="#activities-tab"', false)
            ->assertSee('class="tab-pane fade show active" id="activities-tab"', false);
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

    public function test_client_merge_activity_uses_merge_action_metadata(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $this->createLog(
            $admin,
            'Merged a newer duplicate client profile into the oldest client.',
            1,
            'duplicate_event_clients_merged'
        );

        $this->actingAs($admin)
            ->get(route('activity.logs'))
            ->assertOk()
            ->assertSee('ri-git-merge-line', false)
            ->assertSee('>Merge</span>', false)
            ->assertDontSee('>Delete</span>', false);
    }

    public function test_historical_client_delete_from_merge_route_displays_as_merge(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'client_deleted',
            'description' => 'Deleted Client #1219 (Duplicate Client).',
            'properties' => [
                'route' => 'transaction-events.records-duplicates.merge-clients',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('activity.logs', ['action' => ['client_deleted']]))
            ->assertOk()
            ->assertSee('ri-git-merge-line', false)
            ->assertSee('>Merge</span>', false)
            ->assertDontSee('>Delete</span>', false);
    }

    public function test_transfer_actions_use_success_and_undo_actions_use_warning_metadata(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);

        foreach (['event_transferred', 'events_transfer_selected'] as $action) {
            $this->createLog($admin, "{$action} activity", 1, $action);
        }

        $this->createLog(
            $admin,
            'events_transferred_to_selected_client activity',
            1,
            'events_transferred_to_selected_client'
        );
        $this->createLog($admin, 'events_marked_not_duplicate activity', 1, 'events_marked_not_duplicate');
        $this->createLog($admin, 'events_imported activity', 1, 'events_imported');

        foreach (['event_transfer_undone', 'events_transfer_undone'] as $action) {
            $this->createLog($admin, "{$action} activity", 1, $action);
        }

        $this->actingAs($admin)
            ->get(route('activity.logs'))
            ->assertOk()
            ->assertSee(
                'class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Transfer</span>',
                false
            )
            ->assertSee(
                'class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Events transferred to selected client</span>',
                false
            )
            ->assertSee(
                'class="badge rounded-pill bg-secondary-subtle text-secondary px-3 py-2">Events marked not duplicate</span>',
                false
            )
            ->assertSee(
                'class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Events imported</span>',
                false
            )
            ->assertSee(
                'class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">Undo transfer</span>',
                false
            );
    }

    public function test_created_permission_uses_success_create_metadata(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $this->createLog($admin, 'Added permission "Test Permission".', 1, 'permission_created');

        $this->actingAs($admin)
            ->get(route('activity.logs'))
            ->assertOk()
            ->assertSee(
                'class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Create</span>',
                false
            );
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

    public function test_activities_tab_filters_multiple_users_actions_and_manila_date_range(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $staffA = User::factory()->create(['name' => 'Alpha Staff', 'role_name' => 'Staff']);
        $staffB = User::factory()->create(['name' => 'Beta Staff', 'role_name' => 'Staff']);
        $staffC = User::factory()->create(['name' => 'Gamma Staff', 'role_name' => 'Staff']);

        $createDatedLog = function (User $user, string $description, string $action, string $manilaDate): void {
            $createdAt = Carbon::createFromFormat('Y-m-d H:i:s', $manilaDate, 'Asia/Manila');
            $log = ActivityLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'description' => $description,
            ]);
            $log->timestamps = false;
            $log->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        };

        $createDatedLog($staffA, 'Selected login', 'login', '2026-10-05 08:00:00');
        $createDatedLog($staffB, 'Selected update', 'profile_updated', '2026-10-06 17:00:00');
        $createDatedLog($staffC, 'Unselected user', 'login', '2026-10-05 10:00:00');
        $createDatedLog($staffA, 'Unselected action', 'logout', '2026-10-05 11:00:00');
        $createDatedLog($staffA, 'Outside date range', 'login', '2026-10-07 00:00:00');

        $response = $this->actingAs($admin)
            ->get(route('activity.logs', [
                'user' => [$staffA->id, $staffB->id],
                'action' => ['login', 'profile_updated'],
                'date_from' => '2026-10-05',
                'date_to' => '2026-10-06',
            ]));

        $response->assertOk()
            ->assertSee('name="user[]"', false)
            ->assertSee('name="action[]"', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false);

        $activities = $response->viewData('activities');
        $this->assertSame(2, $activities->total());
        $this->assertSame([
            'Selected login',
            'Selected update',
        ], $activities->pluck('description')->sort()->values()->all());
    }

    public function test_activity_table_columns_are_sortable_in_both_directions(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $alphaUser = User::factory()->create(['name' => 'Alpha User', 'role_name' => 'Staff']);
        $zuluUser = User::factory()->create(['name' => 'Zulu User', 'role_name' => 'Staff']);
        $alphaClient = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Alpha',
            'last_name' => 'Client',
        ]);
        $zuluClient = Client::create([
            'client_id' => '2600002',
            'first_name' => 'Zulu',
            'last_name' => 'Client',
        ]);

        $alphaLog = ActivityLog::create([
            'user_id' => $alphaUser->id,
            'action' => 'alpha_sort_action',
            'subject_type' => 'Client',
            'subject_id' => $alphaClient->id,
            'description' => 'Alpha sortable marker',
        ]);
        $zuluLog = ActivityLog::create([
            'user_id' => $zuluUser->id,
            'action' => 'zulu_sort_action',
            'subject_type' => 'Client',
            'subject_id' => $zuluClient->id,
            'description' => 'Zulu sortable marker',
        ]);

        foreach ([
            [$alphaLog, '2026-10-05 08:00:00'],
            [$zuluLog, '2026-10-06 08:00:00'],
        ] as [$log, $createdAt]) {
            $log->timestamps = false;
            $log->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        foreach (['date', 'user', 'action', 'description', 'client'] as $sort) {
            $ascending = $this->actingAs($admin)->get(route('activity.logs', [
                'search' => 'sortable marker',
                'sort' => $sort,
                'direction' => 'asc',
            ]));

            $ascending->assertOk()->assertSee('aria-sort="ascending"', false);
            $this->assertSame(
                [$alphaLog->id, $zuluLog->id],
                $ascending->viewData('activities')->pluck('id')->all(),
                "Failed ascending sort for {$sort}."
            );

            $descending = $this->actingAs($admin)->get(route('activity.logs', [
                'search' => 'sortable marker',
                'sort' => $sort,
                'direction' => 'desc',
            ]));

            $descending->assertOk()->assertSee('aria-sort="descending"', false);
            $this->assertSame(
                [$zuluLog->id, $alphaLog->id],
                $descending->viewData('activities')->pluck('id')->all(),
                "Failed descending sort for {$sort}."
            );
        }
    }
}
