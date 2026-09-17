<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ArchivedClient;
use App\Models\Permission;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientBulkDeleteWithoutTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_only_selected_clients_without_transaction_history(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Storage::disk('public')->put('clients/unused.png', 'photo');
        Storage::disk('public')->put('fingerprints/unused.png', 'fingerprint');

        $unused = $this->client('2600001', [
            'photo_path' => 'clients/unused.png',
            'fingerprint_path' => 'fingerprints/unused.png',
            'address' => '123 Sample Street',
            'barangay' => 'Bucandala',
            'city' => 'Imus',
            'province' => 'Cavite',
        ]);
        $alsoUnused = $this->client('2600002');
        $linkedByClientId = $this->client('2600003');
        $linkedByLegacyId = $this->client('2600004');

        $this->history('EXTERNAL-00001', '2600003');
        $this->history('2600004-00001', null);

        $this->get(route('client.list'))
            ->assertOk()
            ->assertSee('data-bs-target="#deleteClientsWithoutTransactionsModal"', false)
            ->assertSee('deleteClientsPreviewRows')
            ->assertSee('deleteClientsSelectAll')
            ->assertSee('deleteClientsProgress')
            ->assertSee('Delete Selected Clients');

        $this->getJson(route('client.list.preview-without-transactions'))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $unused->id)
            ->assertJsonPath('data.0.client_id', $unused->client_id)
            ->assertJsonPath('data.0.address', '123 Sample Street, Bucandala, Imus, Cavite')
            ->assertJsonPath('data.1.client_id', $alsoUnused->client_id)
            ->assertJsonMissing(['client_id' => $linkedByClientId->client_id])
            ->assertJsonMissing(['client_id' => $linkedByLegacyId->client_id]);

        $this->delete(route('client.list.destroy-without-transactions'), $this->selectionPayload(false, [$unused->id]))
            ->assertRedirect(route('client.list'))
            ->assertSessionHas('success', 'Deleted 1 client without transaction history. Updated 3 client IDs and linked transaction IDs.');

        $this->assertDatabaseMissing('clients', ['id' => $unused->id]);
        $this->assertDatabaseHas('clients', ['id' => $alsoUnused->id]);
        $this->assertDatabaseHas('clients', ['id' => $linkedByClientId->id]);
        $this->assertDatabaseHas('clients', ['id' => $linkedByLegacyId->id]);
        $this->assertDatabaseHas('clients', ['id' => $alsoUnused->id, 'client_id' => '2600001']);
        $this->assertDatabaseHas('clients', ['id' => $linkedByClientId->id, 'client_id' => '2600002']);
        $this->assertDatabaseHas('clients', ['id' => $linkedByLegacyId->id, 'client_id' => '2600003']);
        $this->assertDatabaseHas('transaction_history', ['transaction_id' => '2600003-00001', 'client_id' => null]);
        $this->assertDatabaseHas('transaction_history', ['transaction_id' => 'EXTERNAL-00001', 'client_id' => '2600002']);
        $this->assertDatabaseCount('transaction_history', 2);
        $this->assertSame(1, \App\Models\ActivityLog::where('action', 'client_deleted')->count());
        Storage::disk('public')->assertMissing('clients/unused.png');
        Storage::disk('public')->assertMissing('fingerprints/unused.png');
    }

    public function test_bulk_delete_reports_when_no_clients_are_eligible(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->client('2600001');
        $this->history('2600001-00001', '2600001');

        $this->getJson(route('client.list.preview-without-transactions'))
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->delete(route('client.list.destroy-without-transactions'), $this->selectionPayload(true))
            ->assertRedirect(route('client.list'))
            ->assertSessionHas('success', 'No selected clients without transaction history were found.');

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_select_all_reaches_every_page_and_keeps_unchecked_clients(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $unchecked = null;
        for ($number = 1; $number <= 105; $number++) {
            $client = $this->client('26' . str_pad((string) $number, 5, '0', STR_PAD_LEFT));
            if ($number === 50) {
                $unchecked = $client;
            }
        }

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        $this->getJson(route('client.list.preview-without-transactions', ['page' => 11]))
            ->assertOk()
            ->assertJsonPath('total', 105)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.4.client_id', '2600105');

        $previewQueryCount = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();
        $this->assertLessThan(20, $previewQueryCount);

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(true, [], [$unchecked->id]))
            ->assertSessionHas('success', 'Deleted 104 clients without transaction history. Updated 1 client ID and linked transaction IDs.');

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('clients', ['id' => $unchecked->id]);
        $this->assertDatabaseHas('clients', ['id' => $unchecked->id, 'client_id' => '2600001']);
    }

    public function test_manual_selection_can_include_clients_from_multiple_preview_pages(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $clients = [];
        for ($number = 1; $number <= 12; $number++) {
            $clients[] = $this->client('26' . str_pad((string) $number, 5, '0', STR_PAD_LEFT));
        }

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(false, [$clients[0]->id, $clients[11]->id]))
            ->assertSessionHas('success', 'Deleted 2 clients without transaction history. Updated 10 client IDs and linked transaction IDs.');

        $this->assertDatabaseCount('clients', 10);
        $this->assertDatabaseMissing('clients', ['id' => $clients[0]->id]);
        $this->assertDatabaseMissing('clients', ['id' => $clients[11]->id]);
    }

    public function test_delete_rejects_an_empty_selection(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->client('2600001');

        $this->delete(route('client.list.destroy-without-transactions'), $this->selectionPayload(false))
            ->assertSessionHas('error', 'Select at least one client to delete.');

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_client_with_history_added_after_preview_is_kept(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $client = $this->client('2600001');

        $this->getJson(route('client.list.preview-without-transactions'))
            ->assertJsonPath('total', 1);

        $this->history('EXTERNAL-00001', $client->client_id);

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(false, [$client->id]))
            ->assertSessionHas('success', 'No selected clients without transaction history were found.');

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_select_all_does_not_delete_a_client_created_after_preview(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $previewed = $this->client('2600001');

        $preview = $this->getJson(route('client.list.preview-without-transactions'))
            ->assertJsonPath('total', 1)
            ->json();

        $newClient = $this->client('2600002');

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(true, [], [], $preview['max_client_id']))
            ->assertSessionHas('success', 'Deleted 1 client without transaction history. Updated 1 client ID and linked transaction IDs.');

        $this->assertDatabaseMissing('clients', ['id' => $previewed->id]);
        $this->assertDatabaseHas('clients', ['id' => $newClient->id]);
        $this->assertDatabaseHas('clients', ['id' => $newClient->id, 'client_id' => '2600001']);
    }

    public function test_deleting_2600202_updates_later_client_and_transaction_ids(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $before = $this->client('2600201');
        $deleted = $this->client('2600202');
        $next = $this->client('2600203');
        $last = $this->client('2600204');
        $otherYear = $this->client('2700001');
        $firstHistory = $this->history('2600203-26-0001', '2600203');
        $secondHistory = $this->history('2600204-26-0001', '2600204');
        $otherHistory = $this->history('2700001-27-0001', '2700001');

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(false, [$deleted->id]))
            ->assertSessionHas('success', 'Deleted 1 client without transaction history. Updated 2 client IDs and linked transaction IDs.');

        $this->assertDatabaseMissing('clients', ['id' => $deleted->id]);
        $this->assertDatabaseHas('clients', ['id' => $before->id, 'client_id' => '2600201']);
        $this->assertDatabaseHas('clients', ['id' => $next->id, 'client_id' => '2600202']);
        $this->assertDatabaseHas('clients', ['id' => $last->id, 'client_id' => '2600203']);
        $this->assertDatabaseHas('transaction_history', ['id' => $firstHistory->id,
            'client_id' => '2600202', 'transaction_id' => '2600202-26-0001']);
        $this->assertDatabaseHas('transaction_history', ['id' => $secondHistory->id,
            'client_id' => '2600203', 'transaction_id' => '2600203-26-0001']);
        $this->assertDatabaseHas('clients', ['id' => $otherYear->id, 'client_id' => '2700001']);
        $this->assertDatabaseHas('transaction_history', ['id' => $otherHistory->id,
            'client_id' => '2700001', 'transaction_id' => '2700001-27-0001']);
    }

    public function test_archived_and_orphan_history_ids_are_not_reassigned(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $deleted = $this->client('2600202');
        ArchivedClient::create(['client_id' => '2600203', 'first_name' => 'Archived', 'last_name' => 'Client']);
        $next = $this->client('2600204');
        $last = $this->client('2600206');
        $this->history('2600205-26-0001', null);
        $history = $this->history('2600206-26-0001', '2600206');

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(false, [$deleted->id]))
            ->assertSessionHas('success', 'Deleted 1 client without transaction history. Updated 2 client IDs and linked transaction IDs.');

        $this->assertDatabaseHas('clients', ['id' => $next->id, 'client_id' => '2600202']);
        $this->assertDatabaseHas('clients', ['id' => $last->id, 'client_id' => '2600204']);
        $this->assertDatabaseHas('transaction_history', ['id' => $history->id,
            'client_id' => '2600204', 'transaction_id' => '2600204-26-0001']);
        $this->assertDatabaseHas('transaction_history', ['transaction_id' => '2600205-26-0001', 'client_id' => null]);
        $this->assertDatabaseHas('archived_clients', ['client_id' => '2600203']);
    }

    public function test_single_client_delete_renumbers_linked_transactions(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $deleted = $this->client('2600202');
        $next = $this->client('2600203');
        $history = $this->history('2600203-26-0001', '2600203');

        $this->delete(route('clients.destroy', $deleted))
            ->assertRedirect(route('client.list'))
            ->assertSessionHas('success', 'Client deleted successfully. Updated 1 client ID and linked transaction IDs.');

        $this->assertDatabaseMissing('clients', ['id' => $deleted->id]);
        $this->assertDatabaseHas('clients', ['id' => $next->id, 'client_id' => '2600202']);
        $this->assertDatabaseHas('transaction_history', ['id' => $history->id,
            'client_id' => '2600202', 'transaction_id' => '2600202-26-0001']);
    }

    public function test_multiple_deletions_shift_each_remaining_client_once(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $deletedFirst = $this->client('2600202');
        $firstSurvivor = $this->client('2600203');
        $deletedSecond = $this->client('2600204');
        $secondSurvivor = $this->client('2600205');
        $history = $this->history('2600205-26-0001', '2600205');

        $this->delete(route('client.list.destroy-without-transactions'),
            $this->selectionPayload(false, [$deletedFirst->id, $deletedSecond->id]))
            ->assertSessionHas('success', 'Deleted 2 clients without transaction history. Updated 2 client IDs and linked transaction IDs.');

        $this->assertDatabaseHas('clients', ['id' => $firstSurvivor->id, 'client_id' => '2600202']);
        $this->assertDatabaseHas('clients', ['id' => $secondSurvivor->id, 'client_id' => '2600203']);
        $this->assertDatabaseHas('transaction_history', ['id' => $history->id,
            'client_id' => '2600203', 'transaction_id' => '2600203-26-0001']);
    }

    public function test_ajax_delete_reports_completion_only_to_the_requesting_user(): void
    {
        $user = User::factory()->create(['role_name' => 'Admin']);
        $this->actingAs($user);
        $deleted = $this->client('2600202');
        $remaining = $this->client('2600203');
        $operationId = (string) Str::uuid();
        $statusRoute = route('client.list.delete-progress', $operationId);

        $this->getJson($statusRoute)
            ->assertOk()
            ->assertJsonPath('state', 'pending');

        $this->deleteJson(route('client.list.destroy-without-transactions'),
            array_merge($this->selectionPayload(false, [$deleted->id]), ['operation_id' => $operationId]))
            ->assertOk()
            ->assertJsonPath('redirect', route('client.list'))
            ->assertSessionHas('success', 'Deleted 1 client without transaction history. Updated 1 client ID and linked transaction IDs.');

        $this->getJson($statusRoute)
            ->assertOk()
            ->assertJsonPath('state', 'complete')
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('renumbered', 1);
        $this->assertDatabaseHas('clients', ['id' => $remaining->id, 'client_id' => '2600202']);

        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->getJson($statusRoute)
            ->assertOk()
            ->assertJsonPath('state', 'pending');
    }

    public function test_single_client_delete_refuses_existing_history(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $client = $this->client('2600202');
        $this->history('2600202-26-0001', '2600202');

        $this->delete(route('clients.destroy', $client))
            ->assertSessionHas('error', 'Clients with transaction history cannot be deleted.');

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'client_id' => '2600202']);
        $this->assertDatabaseHas('transaction_history', ['client_id' => '2600202',
            'transaction_id' => '2600202-26-0001']);
    }

    public function test_role_without_archive_permission_cannot_bulk_delete(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Staff']));
        Permission::create(['feature' => 'Archive Clients', 'role_name' => 'Staff', 'allowed' => false]);
        $this->client('2600001');

        $this->get(route('client.list'))
            ->assertOk()
            ->assertDontSee('id="deleteClientsWithoutTransactionsModal"', false);
        $this->getJson(route('client.list.preview-without-transactions'))->assertNotFound();
        $this->getJson(route('client.list.delete-progress', (string) Str::uuid()))->assertNotFound();
        $this->delete(route('client.list.destroy-without-transactions'))->assertNotFound();

        $this->assertDatabaseCount('clients', 1);
    }

    private function client(string $clientId, array $attributes = []): Client
    {
        return Client::create(array_merge([
            'client_id' => $clientId,
            'first_name' => 'Test',
            'last_name' => 'Client',
        ], $attributes));
    }

    private function history(string $transactionId, ?string $clientId): TransactionHistory
    {
        return TransactionHistory::create([
            'transaction_id' => $transactionId,
            'client_id' => $clientId,
            'transaction_date' => '2026-09-17',
            'category' => 'others',
            'type' => 'test',
        ]);
    }

    private function selectionPayload(bool $selectAll, array $selectedIds = [], array $excludedIds = [], ?int $maxClientId = null): array
    {
        return [
            'select_all' => $selectAll ? '1' : '0',
            'selected_ids' => json_encode($selectedIds),
            'excluded_ids' => json_encode($excludedIds),
            'max_client_id' => $maxClientId ?? (int) Client::query()->max('id'),
        ];
    }
}
