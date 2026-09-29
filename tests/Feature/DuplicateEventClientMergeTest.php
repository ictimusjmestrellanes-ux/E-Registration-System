<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\TransactionRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateEventClientMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_merge_newer_event_client_into_oldest_without_losing_transactions_or_requirements(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $oldest = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Allan',
            'last_name' => 'Asaytono',
            'birth_date' => '1980-01-01',
            'address' => 'Oldest complete address',
            'gender' => 'Male',
        ]);
        $oldest->forceFill([
            'created_at' => '2026-01-01 08:00:00',
            'updated_at' => '2026-01-01 08:00:00',
        ])->saveQuietly();

        $newest = Client::create([
            'client_id' => '2600002',
            'first_name' => 'Allan',
            'middle_name' => 'R',
            'last_name' => 'Asaytono',
            'age' => 42,
            'birth_date' => '1984-02-03',
            'contact' => '9701791890',
            'address' => 'Newest complete address',
            'sector' => 'PODA',
        ]);
        $newest->forceFill([
            'created_at' => '2026-09-03 08:00:00',
            'updated_at' => '2026-09-03 08:00:00',
        ])->saveQuietly();

        $oldHistory = TransactionHistory::create([
            'client_id' => $oldest->client_id,
            'transaction_id' => '2600001-26-0001',
            'transaction_date' => '2026-09-03',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'TRANCH 1',
            'status' => 'Claimed',
        ]);
        $newHistory = TransactionHistory::create([
            'client_id' => $newest->client_id,
            'transaction_id' => '2600002-26-0001',
            'transaction_date' => '2026-09-03',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'TRANCH 1',
            'status' => 'Claimed',
        ]);
        $olderExtraHistory = TransactionHistory::create([
            'client_id' => $newest->client_id,
            'transaction_id' => '2600002-25-0001',
            'transaction_date' => '2025-12-20',
            'category' => 'SOCIAL SERVICES',
            'type' => 'ASSISTANCE',
            'status' => 'Claimed',
        ]);
        $requirement = TransactionRequirement::create([
            'transaction_id' => $olderExtraHistory->id,
            'requirement_type' => 'valid_id',
            'file_path' => 'requirements/test.pdf',
            'file_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 123,
        ]);

        $baseEvent = [
            'client_category' => 'PODA',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-09-03',
            'status' => 'Claimed',
            'transferred_at' => '2026-09-03 12:00:00',
            'not_duplicate' => false,
        ];
        $oldEvent = TransactionEvent::create($baseEvent + [
            'full_name' => 'ASAYTONO, ALLAN',
            'transferred_transaction_id' => $oldHistory->id,
        ]);
        $newEvent = TransactionEvent::create($baseEvent + [
            'full_name' => 'ASAYTONO, ALLAN R',
            'contact_no' => '9701791890',
            'transferred_transaction_id' => $newHistory->id,
        ]);

        $this->get(route('transaction-events.records-duplicates'))
            ->assertOk()
            ->assertSee('Merge to Oldest Client')
            ->assertSee(route('transaction-events.records-duplicates.merge-clients.index'))
            ->assertSee('data-bs-target="#mergeDuplicateClientsModal"', false)
            ->assertSee('Full Name')
            ->assertSee('Contact No.')
            ->assertSee('profile_field_sources', false)
            ->assertSee('data-source-clients=', false)
            ->assertSee('Newest complete address');

        $this->get(route('transaction-events.records-duplicates.merge-clients.index'))
            ->assertOk()
            ->assertSee('Merge Client Groups')
            ->assertSee('No completed merges yet')
            ->assertDontSee('Review This Group');

        $this->post(route('transaction-events.records-duplicates.merge-clients'), [
            'event_ids' => [$oldEvent->id, $newEvent->id],
            'profile_field_sources' => [
                'name' => '2600002',
                'birth_date' => 'oldest',
                'contact' => '2600002',
                'address' => 'oldest',
                'sector' => '2600002',
            ],
        ])->assertRedirect(route('transaction-events.records-duplicates'))->assertSessionHas('success');

        $canonical = $oldest->fresh();
        $this->assertNotNull($canonical);
        $this->assertSame('R', $canonical->middle_name);
        $this->assertNull($canonical->age, 'Fields not shown in the modal must retain their current value.');
        $this->assertSame('1980-01-01', $canonical->birth_date?->toDateString());
        $this->assertSame('9701791890', $canonical->contact);
        $this->assertSame('Oldest complete address', $canonical->address);
        $this->assertSame('PODA', $canonical->sector);
        $this->assertSame('Male', $canonical->gender, 'Fields not shown in the modal must retain their current value.');
        $this->assertNull($newest->fresh());

        $this->assertDatabaseHas('transaction_history', [
            'id' => $oldHistory->id,
            'client_id' => '2600001',
            'transaction_id' => '2600001-26-0001',
        ]);
        $this->assertDatabaseHas('transaction_history', [
            'id' => $newHistory->id,
            'client_id' => '2600001',
            'transaction_id' => '2600001-26-0002',
        ]);
        $this->assertDatabaseHas('transaction_history', [
            'id' => $olderExtraHistory->id,
            'client_id' => '2600001',
            'transaction_id' => '2600001-25-0001',
        ]);
        $this->assertDatabaseHas('transaction_requirements', [
            'id' => $requirement->id,
            'transaction_id' => $olderExtraHistory->id,
        ]);
        $this->assertFalse($oldEvent->fresh()->not_duplicate);
        $this->assertFalse($newEvent->fresh()->not_duplicate);
        $this->assertNotNull($oldEvent->fresh()->duplicate_merged_at);
        $this->assertNotNull($newEvent->fresh()->duplicate_merged_at);
        $this->get(route('transaction-events.records-duplicates'))
            ->assertOk()
            ->assertDontSee('data-target-client-id="2600001"', false);
        $this->get(route('transaction-events.removed-duplicates'))
            ->assertOk()
            ->assertDontSee($oldHistory->transaction_id)
            ->assertDontSee($newHistory->fresh()->transaction_id);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'duplicate_event_clients_merged',
            'subject_type' => 'Client',
            'subject_id' => $oldest->id,
        ]);
        $mergeLog = ActivityLog::where('action', 'duplicate_event_clients_merged')->latest('id')->firstOrFail();
        $this->assertSame([
            'name' => '2600002',
            'birth_date' => 'oldest',
            'contact' => '2600002',
            'address' => 'oldest',
            'sector' => '2600002',
        ], $mergeLog->properties['profile_field_sources']);
        $this->assertContains('duplicate_event_clients_merged', ActivityLog::NOTIFICATION_ACTIONS);

        $this->get(route('transaction-events.records-duplicates.merge-clients.index'))
            ->assertOk()
            ->assertSee('Completed Merges')
            ->assertSee('Client 2600001')
            ->assertSee('2600002')
            ->assertSee('2600002-26-0001')
            ->assertSee('2600001-26-0002');
    }

    public function test_merge_keeps_the_current_oldest_profile_by_default(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $oldest = Client::create([
            'client_id' => '2600003',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact' => '09111111111',
            'address' => 'Oldest address',
        ]);
        $oldest->forceFill(['created_at' => '2026-01-01 08:00:00'])->saveQuietly();
        $newest = Client::create([
            'client_id' => '2600004',
            'first_name' => 'Maria',
            'middle_name' => 'D',
            'last_name' => 'Santos',
            'contact' => '09222222222',
            'address' => 'Newest address',
        ]);
        $newest->forceFill(['created_at' => '2026-09-03 08:00:00'])->saveQuietly();

        $events = collect([$oldest, $newest])->map(function (Client $client, int $index) {
            $history = TransactionHistory::create([
                'client_id' => $client->client_id,
                'transaction_id' => $client->client_id.'-26-0001',
                'transaction_date' => '2026-09-03',
                'category' => 'EVENTS',
                'type' => 'TYPE',
            ]);

            return TransactionEvent::create([
                'full_name' => $index === 0 ? 'SANTOS, MARIA' : 'SANTOS, MARIA D',
                'client_category' => 'SOLO PARENT',
                'transaction_category' => 'EVENTS',
                'transaction_type' => 'TYPE',
                'event_date' => '2026-09-03',
                'transferred_at' => '2026-09-03 12:00:00',
                'transferred_transaction_id' => $history->id,
            ]);
        });

        $this->post(route('transaction-events.records-duplicates.merge-clients'), [
            'event_ids' => $events->pluck('id')->all(),
        ])->assertRedirect(route('transaction-events.records-duplicates'))->assertSessionHas('success');

        $canonical = $oldest->fresh();
        $this->assertSame('Maria', $canonical->first_name);
        $this->assertNull($canonical->middle_name);
        $this->assertSame('09111111111', $canonical->contact);
        $this->assertSame('Oldest address', $canonical->address);
        $this->assertNull($newest->fresh());

        $mergeLog = ActivityLog::where('action', 'duplicate_event_clients_merged')->latest('id')->firstOrFail();
        $this->assertSame([
            'name' => 'oldest',
            'birth_date' => 'oldest',
            'contact' => 'oldest',
            'address' => 'oldest',
            'sector' => 'oldest',
        ], $mergeLog->properties['profile_field_sources']);
    }

    public function test_admin_can_merge_only_selected_newer_clients_and_leave_others_for_review(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $clients = collect([
            ['client_id' => '2600020', 'middle_name' => null, 'created_at' => '2026-01-01 08:00:00'],
            ['client_id' => '2600021', 'middle_name' => 'M', 'created_at' => '2026-02-01 08:00:00'],
            ['client_id' => '2600022', 'middle_name' => 'N', 'created_at' => '2026-03-01 08:00:00'],
        ])->map(function (array $attributes) {
            $client = Client::create([
                'client_id' => $attributes['client_id'],
                'first_name' => 'Allan',
                'middle_name' => $attributes['middle_name'],
                'last_name' => 'Asaytono',
            ]);
            $client->forceFill(['created_at' => $attributes['created_at']])->saveQuietly();

            return $client;
        });

        $events = $clients->map(function (Client $client) {
            $history = TransactionHistory::create([
                'client_id' => $client->client_id,
                'transaction_id' => $client->client_id.'-26-0001',
                'transaction_date' => '2026-09-03',
                'category' => 'EVENTS',
                'type' => 'TYPE',
            ]);

            return TransactionEvent::create([
                'full_name' => 'ASAYTONO, ALLAN'.($client->middle_name ? ' '.$client->middle_name : ''),
                'client_category' => 'PODA',
                'transaction_category' => 'EVENTS',
                'transaction_type' => 'TYPE',
                'event_date' => '2026-09-03',
                'transferred_at' => '2026-09-03 12:00:00',
                'transferred_transaction_id' => $history->id,
            ]);
        })->values();

        $this->post(route('transaction-events.records-duplicates.merge-clients'), [
            'event_ids' => $events->pluck('id')->all(),
            'source_client_ids' => ['2600021'],
            'profile_field_sources' => ['name' => '2600022'],
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(3, Client::whereIn('client_id', $clients->pluck('client_id'))->count());

        $this->post(route('transaction-events.records-duplicates.merge-clients'), [
            'event_ids' => $events->pluck('id')->all(),
            'source_client_ids' => ['2600021'],
            'profile_field_sources' => ['name' => '2600021'],
        ])->assertRedirect(route('transaction-events.records-duplicates'))->assertSessionHas('success');

        $this->assertNotNull($clients[0]->fresh());
        $this->assertSame('M', $clients[0]->fresh()->middle_name);
        $this->assertNull($clients[1]->fresh());
        $this->assertNotNull($clients[2]->fresh());
        $this->assertNull($events[0]->fresh()->duplicate_merged_at);
        $this->assertNotNull($events[1]->fresh()->duplicate_merged_at);
        $this->assertNull($events[2]->fresh()->duplicate_merged_at);
        $this->assertDatabaseHas('transaction_history', [
            'id' => $events[1]->transferred_transaction_id,
            'client_id' => '2600020',
        ]);
        $this->assertDatabaseHas('transaction_history', [
            'id' => $events[2]->transferred_transaction_id,
            'client_id' => '2600022',
        ]);

        $mergeLog = ActivityLog::where('action', 'duplicate_event_clients_merged')->latest('id')->firstOrFail();
        $this->assertSame(['2600021'], $mergeLog->properties['source_client_ids']);
        $this->assertFalse($mergeLog->properties['duplicate_group_removed']);
    }

    public function test_merge_rejects_unrelated_event_clients(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $clients = collect([
            Client::create(['client_id' => '2600010', 'first_name' => 'Allan', 'last_name' => 'Asaytono']),
            Client::create(['client_id' => '2600011', 'first_name' => 'Maria', 'last_name' => 'Santos']),
        ]);
        $events = $clients->values()->map(function (Client $client, int $index) {
            $history = TransactionHistory::create([
                'client_id' => $client->client_id,
                'transaction_id' => $client->client_id.'-26-0001',
                'transaction_date' => '2026-09-03',
                'category' => 'EVENTS',
                'type' => 'TYPE',
            ]);

            return TransactionEvent::create([
                'full_name' => $index === 0 ? 'ASAYTONO, ALLAN' : 'SANTOS, MARIA',
                'client_category' => 'PODA',
                'transaction_category' => 'EVENTS',
                'transaction_type' => 'TYPE',
                'event_date' => '2026-09-03',
                'transferred_at' => '2026-09-03 12:00:00',
                'transferred_transaction_id' => $history->id,
            ]);
        });

        $this->post(route('transaction-events.records-duplicates.merge-clients'), [
            'event_ids' => $events->pluck('id')->all(),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(2, Client::whereIn('client_id', $clients->pluck('client_id'))->count());
        $this->assertSame(0, ActivityLog::where('action', 'duplicate_event_clients_merged')->count());
    }
}
