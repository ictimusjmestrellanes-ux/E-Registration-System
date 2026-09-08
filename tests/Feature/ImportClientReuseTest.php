<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportClientReuseTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'full_name,contact_no,address,age,birth_date,client_category,transaction_category,transaction_type,event_date';

    private function importCsv(string $body, array $extra = []): array
    {
        $file = UploadedFile::fake()->createWithContent('reuse.csv', self::HEADER . "\n" . $body);

        $prepare = $this->postJson(route('transaction-events.import.prepare'), array_merge(
            ['csv_file' => $file],
            $extra
        ));
        $prepare->assertOk();
        $token = $prepare->json('token');
        $this->assertNotEmpty($token);

        $process = $this->postJson(route('transaction-events.import.process'), [
            'token' => $token, 'offset' => 0, 'limit' => 500,
        ]);
        $process->assertOk();

        $finish = $this->postJson(route('transaction-events.import.finish'), ['token' => $token]);
        $finish->assertOk();

        return $finish->json();
    }

    public function test_confirm_import_reuses_client_from_client_list(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'sector' => 'INDIGENT',
        ]);

        // Different case + extra spacing must still hit the same client.
        $result = $this->importCsv(
            "juan   dela cruz,09170000001,Brgy 1,40,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
        );

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => '2600001',
            'events_transaction_type' => 'TRANCH 1',
        ]);
    }

    public function test_confirm_import_registers_unknown_client_and_transfers(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $result = $this->importCsv(
            "Maria Santos Reyes,09170000002,Brgy 2,30,1996-01-01,LUPON,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
        );

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseCount('clients', 1);
        // Original case preserved for the new record.
        $client = Client::firstOrFail();
        $this->assertSame('Maria', $client->first_name);
        $this->assertSame('Santos', $client->middle_name);
        $this->assertSame('Reyes', $client->last_name);
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => $client->client_id,
            'client_category' => 'LUPON',
        ]);
    }

    public function test_confirm_import_does_not_merge_same_first_name_without_birthdate(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'sector' => 'INDIGENT',
        ]);

        // Same first name, different person, no birth date to disambiguate:
        // must register fresh, never attach to Juan Dela Cruz.
        $result = $this->importCsv(
            "Juan Santos,09170000003,Brgy 3,25,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
        );

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseCount('clients', 2);
        $newClient = Client::where('last_name', 'Santos')->firstOrFail();
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => $newClient->client_id,
        ]);
        $this->assertDatabaseMissing('transaction_history', [
            'client_id' => '2600001',
        ]);
    }

    public function test_confirm_import_handles_suffix_without_garbage_last_name(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $result = $this->importCsv(
            "Jose Rizal Mercado JR,09170000004,Brgy 4,35,1991-02-02,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
        );

        $this->assertSame(1, $result['imported']);
        $client = Client::firstOrFail();
        $this->assertSame('Jose', $client->first_name);
        $this->assertSame('Mercado', $client->last_name);
        $this->assertSame('JR', $client->suffix);
    }

    public function test_force_create_all_creates_one_client_per_row(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'sector' => 'INDIGENT',
        ]);

        // Two rows, same name as the client list entry: force mode must NOT
        // reuse — one fresh client per row (strict 1:1).
        $result = $this->importCsv(
            "Juan Dela Cruz,09170000001,Brgy 1,40,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
            . "Juan Dela Cruz,09170000002,Brgy 2,41,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-02\n",
            ['force_direct' => 1]
        );

        $this->assertSame(2, $result['imported']);
        $this->assertDatabaseCount('clients', 3);
        $this->assertDatabaseCount('transaction_history', 2);
        $linkedIds = TransactionHistory::pluck('client_id')->all();
        $this->assertCount(2, array_unique($linkedIds));
        $this->assertNotContains('2600001', $linkedIds);
    }

    public function test_normal_import_reuses_for_same_rows(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        Client::create([
            'client_id' => '2600001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'sector' => 'INDIGENT',
        ]);

        // Same file without the flag: both histories attach to the one client.
        $result = $this->importCsv(
            "Juan Dela Cruz,09170000001,Brgy 1,40,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
            . "Juan Dela Cruz,09170000002,Brgy 2,41,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-02\n"
        );

        $this->assertSame(2, $result['imported']);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('transaction_history', ['client_id' => '2600001']);
    }

    public function test_direct_import_honors_force_direct(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $file = UploadedFile::fake()->createWithContent('force.csv', self::HEADER . "\n"
            . "Juan Dela Cruz,09170000001,Brgy 1,40,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
            . "Juan Dela Cruz,09170000002,Brgy 2,41,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-02\n");

        $response = $this->post(route('transaction-events.import'), [
            'csv_file' => $file,
            'force_direct' => 1,
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('transaction_history', 2);
    }

    public function test_imported_rows_appear_in_event_records(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $result = $this->importCsv(
            "Record Visible Juan,09170000009,Brgy 9,33,,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n"
        );

        $this->assertSame(1, $result['imported']);

        $response = $this->get(route('transaction-events.records'));
        $response->assertOk();
        $response->assertSee('Record Visible Juan');
    }
}
