<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Permission;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_can_be_edited_and_returns_to_the_same_list_context(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $client = Client::create([
            'client_id' => '2600100',
            'first_name' => 'Original',
            'last_name' => 'Name',
        ]);
        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => 'ORIGINAL CLIENT CATEGORY',
            'transaction_id' => '2600100-26-0001',
            'transaction_date' => '2026-09-01',
            'category' => 'ORIGINAL CATEGORY',
            'type' => 'ORIGINAL TYPE',
            'events_transaction_type' => 'ORIGINAL TYPE',
            'remarks' => 'Original remarks',
            'status' => 'Pending',
        ]);
        $event = TransactionEvent::create([
            'full_name' => 'Original Name',
            'remarks' => 'Original remarks',
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);
        $context = ['search' => 'Name', 'page' => 2, 'per_page' => 25];
        $this->get(route('transaction-events.records'))->assertOk()
            ->assertSee('data-bs-target="#editRecordModal"', false)
            ->assertSee(route('transaction-events.records.update', $event))
            ->assertSee('Original Name')
            ->assertSee('name="remarks"', false)
            ->assertSee('Original remarks')
            ->assertSee("editForm.addEventListener('submit'", false)
            ->assertSee("'X-Requested-With': 'XMLHttpRequest'", false);

        $this->put(route('transaction-events.records.update', $context + ['event' => $event->id]), [
            'full_name' => 'Updated Name', 'contact_no' => '09123456789',
            'age' => 35, 'birth_date' => '1991-01-01', 'address' => 'Updated Address',
            'client_category' => 'INDIGENT', 'transaction_category' => 'ASSISTANCE',
            'transaction_type' => 'TRANCH 2', 'event_date' => '2026-09-10',
            'remarks' => 'Updated event remarks',
            'transferred_at' => null,
        ])->assertRedirect(route('transaction-events.records', $context))
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('Updated Name', $event->full_name);
        $this->assertSame('TRANCH 2', $event->transaction_type);
        $this->assertSame('Updated event remarks', $event->remarks);
        $this->assertSame('2026-09-10', $event->event_date->format('Y-m-d'));
        $this->assertNotNull($event->transferred_at);
        $history->refresh();
        $client->refresh();
        $this->assertSame('09123456789', $client->contact);
        $this->assertSame('1991-01-01', $client->birth_date?->toDateString());
        $this->assertSame('Updated Address', $client->address);
        $this->assertSame('INDIGENT', $client->sector);
        $this->assertSame('INDIGENT', $history->client_category);
        $this->assertSame('ASSISTANCE', $history->category);
        $this->assertSame('TRANCH 2', $history->type);
        $this->assertSame('TRANCH 2', $history->events_transaction_type);
        $this->assertSame('Updated event remarks', $history->remarks);
        $this->assertSame('2026-09-10', $history->transaction_date->format('Y-m-d'));
        $this->get(route('transaction-events.records'))->assertSee('Updated Name');
        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('09/10/2026')
            ->assertSee('ASSISTANCE')
            ->assertSee('INDIGENT')
            ->assertSee('TRANCH 2');
    }

    public function test_invalid_record_changes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = TransactionEvent::create(['full_name' => 'Original Name', 'transferred_at' => now()]);
        $listUrl = route('transaction-events.records', ['per_page' => 25]);
        $this->from($listUrl)->put(route('transaction-events.records.update', $event), [
            'full_name' => '', 'age' => -1, 'event_date' => 'invalid', 'edit_record_id' => $event->id,
        ])->assertRedirect($listUrl)->assertSessionHasErrors(['full_name', 'age', 'event_date']);
        $this->get($listUrl)->assertOk()
            ->assertSee('bootstrap.Modal.getOrCreateInstance(editModal).show();', false)
            ->assertSee('value="-1"', false);
        $this->assertSame('Original Name', $event->fresh()->full_name);
    }

    public function test_editing_shared_client_details_updates_all_event_records_for_that_client(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $client = Client::create([
            'client_id' => '2600103',
            'first_name' => 'RIZA',
            'last_name' => 'PENAFLOR',
        ]);
        $firstHistory = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => 'ORIGINAL',
            'transaction_id' => '2600103-26-0001',
            'transaction_date' => '2026-09-15',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'SIR ALLEN',
        ]);
        $secondHistory = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => 'ORIGINAL',
            'transaction_id' => '2600103-26-0002',
            'transaction_date' => '2026-10-06',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'TRANCHE 2',
        ]);
        $firstEvent = TransactionEvent::create([
            'full_name' => 'PENAFLOR, RIZA MENDOZA',
            'age' => 39,
            'transferred_at' => now(),
            'transferred_transaction_id' => $firstHistory->id,
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'SIR ALLEN',
            'event_date' => '2026-09-15',
        ]);
        $secondEvent = TransactionEvent::create([
            'full_name' => 'PENAFLOR, RIZA MENDOZA',
            'age' => 39,
            'transferred_at' => now(),
            'transferred_transaction_id' => $secondHistory->id,
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCHE 2',
            'event_date' => '2026-10-06',
        ]);

        $this->putJson(route('transaction-events.records.update', $firstEvent), [
            'full_name' => 'PENAFLOR, RIZA MENDOZA',
            'age' => 38,
            'birth_date' => '1987-12-30',
            'contact_no' => '09123456789',
            'address' => 'BUCANDALA II',
            'client_category' => 'TODA',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'SIR ALLEN',
            'event_date' => '2026-09-15',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('record.birth_date', '1987-12-30')
            ->assertJsonPath('synchronized_record_ids.0', $firstEvent->id)
            ->assertJsonPath('synchronized_record_ids.1', $secondEvent->id);

        foreach ([$firstEvent->fresh(), $secondEvent->fresh()] as $event) {
            $this->assertSame(38, $event->age);
            $this->assertSame('1987-12-30', $event->birth_date?->toDateString());
            $this->assertSame('09123456789', $event->contact_no);
            $this->assertSame('BUCANDALA II', $event->address);
            $this->assertSame('TODA', $event->client_category);
            $this->assertSame('TRANSPORT GROUP', $event->payroll_category);
        }

        $this->assertSame('TRANCHE 2', $secondEvent->fresh()->transaction_type);
        $this->assertSame('2026-10-06', $secondEvent->fresh()->event_date?->toDateString());
        $this->assertSame('TODA', $secondHistory->fresh()->client_category);

        $client->refresh();
        $this->assertSame(38, $client->age);
        $this->assertSame('1987-12-30', $client->birth_date?->toDateString());
        $this->assertSame('09123456789', $client->contact);
        $this->assertSame('BUCANDALA II', $client->address);
        $this->assertSame('TODA', $client->sector);
    }

    public function test_record_can_be_edited_asynchronously_without_a_redirect(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = TransactionEvent::create([
            'full_name' => 'Original Name',
            'transferred_at' => now(),
        ]);

        $this->putJson(route('transaction-events.records.update', $event), [
            'full_name' => 'Updated Name',
            'age' => 42,
            'birth_date' => '1984-03-12',
            'contact_no' => '09123456789',
            'address' => 'Updated Address',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'ASSISTANCE',
            'transaction_type' => 'FOOD',
            'event_date' => '2026-10-14',
            'remarks' => 'Updated asynchronously',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('record.id', $event->id)
            ->assertJsonPath('record.full_name', 'Updated Name')
            ->assertJsonPath('record.age', 42)
            ->assertJsonPath('record.birth_date_display', 'Mar 12, 1984')
            ->assertJsonPath('record.event_date_display', 'Oct 14, 2026');

        $this->assertSame('Updated Name', $event->fresh()->full_name);
    }

    public function test_record_name_can_be_saved_when_additional_field_permission_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Permission::create([
            'feature' => 'Edit Transaction Event Record Fields',
            'role_name' => 'Admin',
            'allowed' => false,
        ]);
        $history = TransactionHistory::create([
            'client_id' => '2600104',
            'transaction_id' => '2600104-26-0001',
            'transaction_date' => '2026-10-01',
            'category' => 'ORIGINAL CATEGORY',
            'type' => 'ORIGINAL TYPE',
        ]);
        $event = TransactionEvent::create([
            'full_name' => 'Original Name',
            'age' => 40,
            'transaction_category' => 'ORIGINAL CATEGORY',
            'transaction_type' => 'ORIGINAL TYPE',
            'event_date' => '2026-10-01',
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertSee('id="edit_record_full_name"', false)
            ->assertDontSee('id="edit_record_age"', false);

        $this->putJson(route('transaction-events.records.update', $event), [
            'full_name' => 'Updated Name',
            'age' => 21,
            'transaction_category' => 'UNAUTHORIZED CHANGE',
            'transaction_type' => 'UNAUTHORIZED CHANGE',
            'event_date' => '2026-10-19',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('record.full_name', 'Updated Name');

        $event->refresh();
        $history->refresh();
        $this->assertSame('Updated Name', $event->full_name);
        $this->assertSame(40, $event->age);
        $this->assertSame('ORIGINAL CATEGORY', $event->transaction_category);
        $this->assertSame('ORIGINAL TYPE', $event->transaction_type);
        $this->assertSame('2026-10-01', $event->event_date?->toDateString());
        $this->assertSame('ORIGINAL CATEGORY', $history->category);
        $this->assertSame('ORIGINAL TYPE', $history->type);
    }

    public function test_invalid_asynchronous_record_changes_return_json_errors(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $event = TransactionEvent::create([
            'full_name' => 'Original Name',
            'transferred_at' => now(),
        ]);

        $this->putJson(route('transaction-events.records.update', $event), [
            'full_name' => '',
            'age' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'age']);

        $this->assertSame('Original Name', $event->fresh()->full_name);
    }

    public function test_latest_event_name_populates_both_client_edit_entry_points(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $client = Client::create([
            'client_id' => '2600101',
            'first_name' => 'ROSALIDA',
            'middle_name' => 'S.',
            'last_name' => 'QUIMNO',
        ]);
        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'transaction_id' => '2600101-26-0001',
            'transaction_date' => '2026-09-01',
            'category' => 'ASSISTANCE',
            'type' => 'FOOD',
        ]);
        TransactionEvent::create([
            'full_name' => 'QUIMNO, ROSALINDA SUMILI',
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        $response = $this->get(route('clients.edit', $client))->assertOk();
        $this->assertMatchesRegularExpression('/name="first_name"[^>]*value="ROSALINDA"/s', $response->getContent());
        $this->assertMatchesRegularExpression('/name="middle_name"[^>]*value="SUMILI"/s', $response->getContent());
        $this->assertMatchesRegularExpression('/name="last_name"[^>]*value="QUIMNO"/s', $response->getContent());
    }

    public function test_editing_latest_event_name_synchronizes_the_linked_client_profile(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $client = Client::create([
            'client_id' => '2600102',
            'first_name' => 'ROSALIDA',
            'middle_name' => 'S.',
            'last_name' => 'QUIMNO',
        ]);
        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'transaction_id' => '2600102-26-0001',
            'transaction_date' => '2026-09-01',
            'category' => 'ASSISTANCE',
            'type' => 'FOOD',
        ]);
        $event = TransactionEvent::create([
            'full_name' => 'QUIMNO, ROSALIDA S.',
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        $this->put(route('transaction-events.records.update', $event), [
            'full_name' => 'QUIMNO, ROSALINDA SUMILI',
            'transaction_category' => 'ASSISTANCE',
            'transaction_type' => 'FOOD',
            'event_date' => '2026-09-01',
        ])->assertRedirect()->assertSessionHas('success');

        $client->refresh();
        $this->assertSame('ROSALINDA', $client->first_name);
        $this->assertSame('SUMILI', $client->middle_name);
        $this->assertSame('QUIMNO', $client->last_name);

        $this->get(route('clients.edit', $client))
            ->assertOk()
            ->assertSee('value="ROSALINDA"', false)
            ->assertSee('value="SUMILI"', false)
            ->assertSee('value="QUIMNO"', false);
        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('QUIMNO, ROSALINDA SUMILI');
    }

    public function test_viewers_cannot_edit_and_pending_events_are_not_editable_as_records(): void
    {
        $event = TransactionEvent::create(['full_name' => 'Original Name', 'transferred_at' => now()]);
        $this->actingAs(User::factory()->create(['role_name' => 'Viewer']));
        $this->get(route('transaction-events.records'))->assertOk()
            ->assertDontSee('data-bs-target="#editRecordModal"', false);
        $this->put(route('transaction-events.records.update', $event), ['full_name' => 'Changed'])->assertForbidden();

        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $pending = TransactionEvent::create(['full_name' => 'Pending']);
        $this->put(route('transaction-events.records.update', $pending), ['full_name' => 'Changed'])->assertNotFound();
    }
}
