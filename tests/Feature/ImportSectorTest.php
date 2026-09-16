<?php

namespace Tests\Feature;

use App\Http\Controllers\TransactionEventsController;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportSectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('legacy.csv',
            "full_name,client_category,event_date,transaction_category,transaction_type,sector\n"
            ."Gemma De Quiroz,Individual,2026-07-03,BIGAY BIGAS SA MASA,TRANCH 1,".str_repeat('X', 501)."\n");
    }

    public function test_legacy_sector_is_ignored_in_preview_staging_and_archive(): void
    {
        $response = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->upload(), 'events_only' => 1,
        ])->assertOk()->assertJsonPath('skipped', 0);
        $this->assertArrayNotHasKey('sector', $response->json('preview_rows.0'));
        $this->assertNotContains('sector', $response->json('headers'));
        $this->postJson(route('transaction-events.import.finish'), ['token' => $response->json('token')])
            ->assertOk()->assertJsonPath('created', 1);
        $this->assertNull(TransactionEvent::firstOrFail()->sector);
        $archive = Storage::disk('local')->files('transaction-events-archive')[0];
        $this->assertStringNotContainsString('sector', Storage::disk('local')->get($archive));
    }

    public function test_matching_updates_status_without_changing_saved_sectors(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload()])->assertRedirect();
        $event = TransactionEvent::firstOrFail();
        $event->update(['sector' => 'SAVED EVENT SECTOR']);
        Client::firstOrFail()->update(['sector' => 'SAVED CLIENT SECTOR']);
        $this->postJson(route('transaction-events.import.check-duplicates'), ['csv_file' => $this->upload()])
            ->assertOk()->assertJsonMissingPath('duplicates.0.sector')->assertJsonMissingPath('duplicates.0.existing_sector');
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'update_existing' => 1])
            ->assertRedirect()->assertSessionHas('success', 'Import complete: 0 created, 1 updated, 0 unchanged, 0 skipped.');
        $this->assertSame('Claimed', $event->fresh()->status);
        $this->assertSame('Claimed', $event->fresh()->transferredTransaction->status);
        $this->assertSame('SAVED EVENT SECTOR', $event->fresh()->sector);
        $this->assertSame('SAVED CLIENT SECTOR', Client::firstOrFail()->sector);
    }

    public function test_excel_and_csv_templates_have_nine_columns_without_sector(): void
    {
        $response = $this->get(route('transaction-events.template'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringNotContainsString('Sector', $xml);
            $this->assertStringNotContainsString('r="J1"', $xml);
            $this->assertStringContainsString('r="I1"', $xml);
            $zip->close();
        } finally {
            unlink($path);
        }
        $method = new \ReflectionMethod(TransactionEventsController::class, 'downloadTemplateAsCSV');
        ob_start();
        $method->invoke(new TransactionEventsController)->sendContent();
        $csv = ob_get_clean();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        foreach ($rows as $row) {
            $this->assertCount(9, $row);
        }
        $this->assertStringNotContainsString('Sector', $csv);
    }

    public function test_transfer_preserves_existing_client_sector(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload()])->assertRedirect();
        $client = Client::firstOrFail();
        $client->update(['sector' => 'SAVED SECTOR']);
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
        $event = TransactionEvent::latest('id')->firstOrFail();
        $event->update(['sector' => 'LEGACY SECTOR']);
        $this->post(route('transaction-events.transfer-selected'), ['event_ids' => [$event->id]])->assertRedirect();
        $this->assertSame('SAVED SECTOR', $client->fresh()->sector);
    }
}
