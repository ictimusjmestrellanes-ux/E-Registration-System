<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

        $this->assertDatabaseCount('transaction_events', 1);
        $this->assertDatabaseHas('transaction_events', [
            'full_name' => 'JANE DOE',
            'contact_no' => '',
            'address' => 'Sample Address',
            'age' => 30,
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
        $this->assertDatabaseHas('transaction_events', [
            'full_name' => 'JANE DOE',
            'client_category' => 'PWD',
            'transaction_category' => 'social_services',
            'transaction_type' => 'burial_assistance',
        ]);
        $this->assertDatabaseHas('clients', [
            'first_name' => 'JANE',
            'last_name' => 'DOE',
            'sector' => 'PWD',
        ]);
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
            'transaction_id' => '2600000-26-0000',
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('2600000-26-0000')
            ->assertSee('PWD')
            ->assertSee('BURIAL ASSISTANCE');
    }

    public function test_transfer_uses_next_global_imported_transaction_id_across_clients(): void
    {
        Carbon::setTestNow('2026-07-30 09:00:00');
        $this->actingAs(User::factory()->create());

        $client = Client::create([
            'client_id' => '2600001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600000-26-0003',
            'transaction_date' => now(),
            'category' => 'social_services',
            'type' => 'burial_assistance',
        ]);

        TransactionHistory::create([
            'transaction_id' => '2600000-26-0005',
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
            'transaction_id' => '2600000-26-0006',
            'category' => 'social_services',
            'type' => 'educational_assistance',
        ]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('2600000-26-0006')
            ->assertSee('Senior Citizen')
            ->assertSee('EDUCATIONAL ASSISTANCE');
    }

    private function csvUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'transaction-events');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'transaction-events.csv', 'text/csv', null, true);
    }
}
