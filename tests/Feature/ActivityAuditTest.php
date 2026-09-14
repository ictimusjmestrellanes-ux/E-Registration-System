<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_edit_records_actor_before_after_and_readable_details(): void
    {
        $user = User::factory()->create(['role_name' => 'Admin']);
        $this->actingAs($user);
        $event = TransactionEvent::create(['full_name' => 'Original Name', 'transferred_at' => now()]);
        $this->put(route('transaction-events.records.update', $event), ['full_name' => 'Updated Name'])
            ->assertRedirect();
        $log = ActivityLog::where('action', 'transaction_event_updated')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($event->id, $log->subject_id);
        $this->assertSame('Original Name', $log->properties['before']['full_name']);
        $this->assertSame('Updated Name', $log->properties['after']['full_name']);
        $this->assertSame('transaction-events.records.update', $log->properties['route']);
        $this->assertNotEmpty($log->ip_address);

        $this->put(route('transaction-events.records.update', $event), ['full_name' => 'Updated Name'])->assertRedirect();
        $this->assertSame(1, ActivityLog::where('action', 'transaction_event_updated')->count());
        $this->put(route('transaction-events.records.update', $event), ['full_name' => ''])->assertSessionHasErrors();
        $this->assertSame(1, ActivityLog::where('action', 'transaction_event_updated')->count());
        $this->get(route('activity.logs', ['action' => 'transaction_event_updated']))->assertOk()
            ->assertSee('View details')->assertSee('Before')->assertSee('After')
            ->assertSee('Original Name')->assertSee('Updated Name');
    }

    public function test_bulk_delete_and_duplicate_review_audit_each_affected_record(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $events = collect(['Alpha', 'Beta'])->map(fn ($name) => TransactionEvent::create(['full_name' => $name]));
        $this->post(route('transaction-events.group-not-duplicate'), ['event_ids' => $events->pluck('id')->all()])->assertRedirect();
        $this->assertSame(2, ActivityLog::where('action', 'transaction_event_updated')->count());
        foreach ($events as $event) {
            $this->post(route('transaction-events.reset-duplicate', $event))->assertRedirect();
        }
        $this->delete(route('transaction-events.delete-selected'), ['event_ids' => $events->pluck('id')->all()])->assertRedirect();
        $logs = ActivityLog::where('action', 'transaction_event_deleted')->get();
        $this->assertCount(2, $logs);
        $this->assertEqualsCanonicalizing(['Alpha', 'Beta'], $logs->map(fn ($log) => $log->properties['before']['full_name'])->all());
        $this->assertDatabaseCount('transaction_events', 0);
    }

    public function test_rolled_back_changes_leave_no_audit_entries_and_sensitive_values_are_redacted(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Route::post('/audit-test', function () {
            DB::beginTransaction();
            Client::create(['client_id' => 'ROLLBACK', 'first_name' => 'Rolled', 'last_name' => 'Back']);
            DB::rollBack();
            Client::create([
                'client_id' => 'SAVED', 'first_name' => 'Saved', 'last_name' => 'Client',
                'fingerprint_template' => 'private-biometric-template',
            ]);
            return response()->noContent();
        })->name('audit.test');
        $this->post('/audit-test')->assertNoContent();
        $logs = ActivityLog::where('action', 'client_created')->get();
        $this->assertCount(1, $logs);
        $this->assertSame('SAVED', $logs->first()->properties['after']['client_id']);
        $this->assertSame('[redacted]', $logs->first()->properties['after']['fingerprint_template']);
        $this->assertStringNotContainsString('private-biometric-template', $logs->toJson());
    }
}
