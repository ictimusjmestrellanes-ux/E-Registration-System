<?php

namespace Tests\Feature;

use App\Models\ImportArchiveFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportEncodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_windows_1252_bytes_are_converted_not_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        // Raw Windows-1252 bytes as Excel often writes them:
        // 0xD1 = Ñ, 0x96 = en-dash. Neither is valid UTF-8 on its own.
        $csv = "full_name,contact_no,address,age,birth_date,client_category,transaction_category,transaction_type,event_date\n"
            . "PE\xD1A CRUZ,09170000001,Brgy 1 \x96 Main,40,1990-01-01,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,2026-09-01\n";

        $this->assertFalse(mb_check_encoding("PE\xD1A CRUZ", 'UTF-8'));

        $file = UploadedFile::fake()->createWithContent('latin1-names.csv', $csv);

        // Previously this blew up with "Malformed UTF-8 characters,
        // possibly incorrectly encoded" instead of returning JSON.
        $prepare = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $file,
        ]);
        $prepare->assertOk();
        $prepare->assertJsonPath('success', true);
        $prepare->assertJsonPath('total', 1);
        $prepare->assertJsonPath('skipped', 0);

        $rows = $prepare->json('preview_rows');
        $this->assertNotEmpty($rows);
        $this->assertSame('PEÑA CRUZ', $rows[0]['full_name']);
        $this->assertSame('Brgy 1 – Main', $rows[0]['address']);

        $finish = $this->postJson(route('transaction-events.import.finish'), [
            'token' => $prepare->json('token'),
        ]);
        $finish->assertOk();
        $finish->assertJsonPath('success', true);
        $finish->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('clients', ['first_name' => 'PEÑA']);
    }

    public function test_chunked_import_persists_clients_history_and_archive(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        // Same hostile bytes as above, through the preview flow the UI uses:
        // prepare -> process -> finish must create the client + history and
        // register a downloadable archive file.
        $csv = "full_name,contact_no,address,age,birth_date,client_category,transaction_category,transaction_type,event_date\n"
            . "PE\xD1A CRUZ,09170000001,Brgy 1 \x96 Main,40,1990-01-01,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,46268\n";

        $file = UploadedFile::fake()->createWithContent('latin1-chunked.csv', $csv);

        $prepare = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $file,
        ]);
        $prepare->assertOk();
        $prepare->assertJsonPath('success', true);
        $prepare->assertJsonPath('total', 1);
        $token = $prepare->json('token');
        $this->assertNotEmpty($token);

        $process = $this->postJson(route('transaction-events.import.process'), [
            'token' => $token,
            'offset' => 0,
            'limit' => 500,
        ]);
        $process->assertOk();
        $process->assertJsonPath('done', true);

        $finish = $this->postJson(route('transaction-events.import.finish'), [
            'token' => $token,
        ]);
        $finish->assertOk();
        $finish->assertJsonPath('success', true);
        $finish->assertJsonPath('imported', 1);
        $finish->assertJsonPath('skipped', 0);

        // Client auto-registered with the converted name...
        $this->assertDatabaseHas('clients', ['first_name' => 'PEÑA', 'last_name' => 'CRUZ']);
        // ...transferred into transaction history with the converted date...
        $this->assertDatabaseHas('transaction_history', [
            'transaction_date' => '2026-09-03 00:00:00',
            'events_transaction_type' => 'TRANCH 1',
        ]);
        // ...and archived for View Archive Files.
        $archive = ImportArchiveFile::latest('id')->firstOrFail();
        $this->assertSame('latin1-chunked.csv', $archive->original_filename);
        $this->assertSame(1, (int) $archive->rows_count);
        $this->assertStringEndsWith('.csv', $archive->filename);
        Storage::disk('local')->assertExists('transaction-events-archive/' . $archive->filename);
    }
}
