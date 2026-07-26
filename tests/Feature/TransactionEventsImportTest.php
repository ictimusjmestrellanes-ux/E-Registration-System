<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TransactionEventsImportTest extends TestCase
{
    use RefreshDatabase;

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

    private function csvUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'transaction-events');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'transaction-events.csv', 'text/csv', null, true);
    }
}
