<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_all_as_read_returns_json_and_clears_the_navbar_count(): void
    {
        $user = User::factory()->create(['role_name' => 'Admin']);
        $this->actingAs($user);
        $notification = ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'event_status_tagged',
            'description' => 'Tagged an event.',
        ]);

        $this->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame($notification->id, $user->fresh()->notifications_read_id);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="m-0 d-none" id="markAllNotificationsReadForm"', false)
            ->assertSee('id="notificationUnreadLabel">0 New', false);
    }

    public function test_notification_state_reports_only_the_current_users_visible_updates(): void
    {
        $admin = User::factory()->create(['role_name' => 'Admin']);
        $viewer = User::factory()->create(['role_name' => 'Viewer']);
        $own = ActivityLog::create([
            'user_id' => $viewer->id,
            'action' => 'event_status_tagged',
            'description' => 'Viewer notification.',
        ]);
        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'events_imported',
            'description' => 'Admin notification.',
        ]);
        ActivityLog::create([
            'user_id' => $viewer->id,
            'action' => 'transaction_created',
            'description' => 'Not a navbar notification.',
        ]);

        $this->actingAs($viewer);
        $this->getJson(route('notifications.state'))
            ->assertOk()
            ->assertJsonPath('latest_id', $own->id)
            ->assertJsonPath('unread_count', 1);
        $this->postJson(route('notifications.read-all'))->assertOk();
        $this->getJson(route('notifications.state'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin);
        $this->getJson(route('notifications.state'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2);
    }
}
