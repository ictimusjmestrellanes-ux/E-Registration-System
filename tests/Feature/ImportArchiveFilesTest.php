<?php

namespace Tests\Feature;

use App\Models\ImportArchiveFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportArchiveFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_archives_page_lists_db_records(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        ImportArchiveFile::create([
            'filename' => 'transaction-events_20260906_120000_abc123_list.csv',
            'original_filename' => 'list.csv',
            'rows_count' => 150,
            'file_size' => 2048,
            'source' => 'import',
            'imported_by_id' => 1,
            'imported_by' => 'Test User',
            'role' => 'Admin',
            'imported_at' => now(),
        ]);

        $response = $this->get(route('transaction-events.archives'));
        $response->assertOk();
        $response->assertSee('transaction-events_20260906_120000_abc123_list.csv');
        $response->assertSee('Test User');
        $response->assertSee('2.00 KB');
    }

    public function test_store_imported_event_archive_writes_db_record(): void
    {
        // Isolate from the real archive directory (the migration backfill may
        // have registered pre-existing files in this database).
        \Illuminate\Support\Facades\Storage::fake('local');
        $baseline = ImportArchiveFile::count();

        $controller = new \App\Http\Controllers\TransactionEventsController();
        $method = new \ReflectionMethod($controller, 'storeImportedEventArchive');
        $method->setAccessible(true);

        $rows = [
            [
                'full_name' => 'Juan Dela Cruz', 'contact_no' => '0917', 'address' => 'Brgy 1',
                'age' => 40, 'birth_date' => '1986-05-05', 'client_category' => 'INDIGENT',
                'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'TRANCH 1',
                'event_date' => '2026-03-09',
            ],
        ];

        $name = $method->invoke($controller, $rows, 'sample.xlsx', 'import');

        $this->assertStringEndsWith('.csv', $name);
        $this->assertDatabaseHas('import_archive_files', [
            'filename' => $name,
            'original_filename' => 'sample.xlsx',
            'rows_count' => 1,
            'source' => 'import',
        ]);

        $record = ImportArchiveFile::where('filename', $name)->firstOrFail();
        $this->assertGreaterThan(0, $record->file_size);
        $this->assertNotNull($record->imported_at);

        // Two rapid same-name stores must not collide.
        $second = $method->invoke($controller, $rows, 'sample.xlsx', 'import');
        $this->assertNotSame($name, $second);
        $this->assertSame($baseline + 2, ImportArchiveFile::count());
    }
}
