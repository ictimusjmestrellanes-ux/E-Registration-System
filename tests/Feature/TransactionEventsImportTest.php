<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionEventsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_import_skips_rows_with_out_of_range_age(): void
    {
        $this->actingAs(User::factory()->create());

        $csv = implode("\n", [
            'full_name,contact_no,address,age',
            'RICARDO MONICIPYO,,,383',
            'JANE DOE,,Sample Address,30',
        ]);

        $response = $this->post(route('transaction-events.import'), [
            'csv_file' => $this->csvUpload($csv),
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $response->assertSessionHas('success', 'Successfully imported 1 event(s). Skipped 1 invalid row(s).');

        $this->assertDatabaseCount('transaction_events', 0);
        $this->assertDatabaseHas('clients', [
            'age' => 30,
            'contact' => '',
            'address' => 'Sample Address',
        ]);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseHas('transaction_history', [
            'category' => '',
            'type' => '',
        ]);

        $this->assertDatabaseCount('clients', 1);
        $this->assertSame(30, Client::query()->firstOrFail()->age);
        $this->assertNull(TransactionEvent::query()->where('full_name', 'RICARDO MONICIPYO')->first());
    }

    public function test_import_accepts_spaced_transaction_event_headers(): void
    {
        $this->actingAs(User::factory()->create());

        $csv = implode("\n", [
            'Full Name,Contact No,Address,Age,Birth Date,Client Category,Transaction Category,Transaction Type',
            'JANE DOE,09170000000,Sample Address,30,1996-01-01,PWD,social_services,burial_assistance',
        ]);

        $response = $this->post(route('transaction-events.import'), [
            'csv_file' => $this->csvUpload($csv),
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseCount('transaction_events', 0);
        $this->assertDatabaseHas('transaction_history', [
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);
        $this->assertDatabaseHas('clients', [
            'first_name' => 'JANE',
            'last_name' => 'DOE',
            'sector' => 'PWD',
        ]);
    }

    public function test_import_does_not_create_duplicate_client_for_same_full_name_and_birth_date(): void
    {
        $this->actingAs(User::factory()->create());

        $existingClient = Client::create([
            'client_id' => '2600100',
            'first_name' => 'JANE',
            'last_name' => 'DOE',
            'birth_date' => '1996-01-01',
        ]);

        $csv = implode("\n", [
            'Full Name,Contact No,Address,Age,Birth Date,Client Category,Transaction Category,Transaction Type',
            'JANE DOE,09170000000,Sample Address,30,1996-01-01,PWD,social_services,burial_assistance',
        ]);

        $response = $this->post(route('transaction-events.import'), [
            'csv_file' => $this->csvUpload($csv),
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => '2600100',
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);
    }

    public function test_import_archives_csv_file_after_processing(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $csv = implode("\n", [
            'Full Name,Contact No,Address,Age,Birth Date,Client Category,Transaction Category,Transaction Type',
            'JANE DOE,09170000000,Sample Address,30,1996-01-01,PWD,social_services,burial_assistance',
        ]);

        $response = $this->post(route('transaction-events.import'), [
            'csv_file' => $this->csvUpload($csv),
        ]);

        $response->assertRedirect(route('transaction-events.index'));
        $files = Storage::disk('local')->allFiles('transaction-events-archive');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('transaction-events_', basename($files[0]));
    }

    public function test_transfer_starts_imported_transaction_ids_at_zero(): void
    {
        Carbon::setTestNow('2026-07-30 09:00:00');
        $this->actingAs(User::factory()->create());

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $event = TransactionEvent::create([
            'full_name' => 'Jane Doe',
            'client_category' => 'PWD',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
        ]);

        $response = $this->post(route('transaction-events.transfer', $event));

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => '2600001',
            'client_category' => 'PWD',
            'transaction_id' => '2600001-26-0001',
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('2600001-26-0001')
            ->assertSee('PWD')
            ->assertSee('BURIAL ASSISTANCE');
    }

    public function test_transfer_uses_next_client_specific_transaction_id(): void
    {
        Carbon::setTestNow('2026-07-30 09:00:00');
        $this->actingAs(User::factory()->create());

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600001-26-0003',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600001-26-0005',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600002-26-0008',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        $event = TransactionEvent::create([
            'full_name' => 'Jane Doe',
            'client_category' => 'Senior Citizen',
            'transaction_category' => 'social_services',
            'transaction_type' => 'educational_assistance',
        ]);

        $response = $this->post(route('transaction-events.transfer', $event));

        $response->assertRedirect(route('transaction-events.index'));
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => '2600001',
            'client_category' => 'Senior Citizen',
            'transaction_id' => '2600001-26-0006',
            'category' => 'social_services',
            'type' => 'educational_assistance',
        ]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('2600001-26-0006')
            ->assertSee('Senior Citizen')
            ->assertSee('EDUCATIONAL ASSISTANCE');
    }

    public function test_store_creates_next_client_specific_transaction_id_after_imported_transactions(): void
    {
        Carbon::setTestNow('2026-07-30 09:00:00');
        $this->actingAs(User::factory()->create());

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600001-26-0001',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600001-26-0002',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'education',
        ]);

        $response = $this->post(route('transactions.store'), [
            'client_id' => '2600001',
            'transaction_date' => now()->format('Y-m-d'),
            'category' => 'appointments',
            'type' => 'event',
            'description' => 'Existing client second transaction',
        ]);

        $response->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('transaction_history', [
            'client_id' => '2600001',
            'transaction_id' => '2600001-26-0003',
            'category' => 'appointments',
            'type' => 'event',
        ]);
    }

    private function csvUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'transaction-events');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'transaction-events.csv', 'text/csv', null, true);
    }
}
