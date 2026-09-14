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

    private function upload(?string $sector = 'SOLO PARENT', string $type = 'CLAIMED'): UploadedFile
    {
        $header = 'full_name,client_category,event_date,transaction_category,transaction_type';
        $row = 'Gemma De Quiroz,Individual,2026-07-03,BIGAY BIGAS SA MASA,'.$type;
        if ($sector !== null) {
            $header .= ',sector';
            $row .= ','.$sector;
        }
        return UploadedFile::fake()->createWithContent('sector.csv', $header."\n".$row."\n");
    }

    public function test_csv_sector_survives_preview_staging_archive_and_transfer(): void
    {
        $token = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->upload(), 'events_only' => 1,
        ])->assertOk()->assertJsonPath('preview_rows.0.sector', 'SOLO PARENT')->json('token');
        $this->postJson(route('transaction-events.import.process'), ['token' => $token, 'offset' => 0, 'limit' => 1])->assertOk();
        $this->postJson(route('transaction-events.import.finish'), ['token' => $token])->assertOk()->assertJsonPath('imported', 1);
        $event = TransactionEvent::firstOrFail();
        $this->assertSame('SOLO PARENT', $event->sector);
        $archive = Storage::disk('local')->files('transaction-events-archive')[0];
        $this->assertStringContainsString('sector', Storage::disk('local')->get($archive));
        $this->assertStringContainsString('SOLO PARENT', Storage::disk('local')->get($archive));
        $this->post(route('transaction-events.transfer-selected'), ['event_ids' => [$event->id]])->assertRedirect();
        $this->assertSame('SOLO PARENT', Client::firstOrFail()->sector);
        $this->assertDatabaseHas('transaction_history', ['client_category' => 'Individual']);
    }

    public function test_downloaded_xlsx_template_contains_sector_and_imports_it(): void
    {
        $response = $this->get(route('transaction-events.template'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('Sector', $xml);
            $this->assertStringContainsString('r="J1"', $xml);
            $zip->close();
            $this->post(route('transaction-events.import'), [
                'csv_file' => UploadedFile::fake()->createWithContent('template.xlsx', file_get_contents($path)),
            ])->assertRedirect();
            $this->assertDatabaseHas('clients', ['first_name' => 'Maria', 'sector' => 'SOLO PARENT']);
            $this->assertDatabaseHas('transaction_events', ['full_name' => 'Maria Santos', 'sector' => 'SOLO PARENT']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_csv_template_fallback_includes_sector(): void
    {
        $method = new \ReflectionMethod(TransactionEventsController::class, 'downloadTemplateAsCSV');
        $response = $method->invoke(new TransactionEventsController);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertSame('Sector', $rows[0][9]);
        $this->assertSame('SOLO PARENT', $rows[2][9]);
    }

    public function test_explicit_sector_updates_existing_profile_and_blank_values_preserve_it(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(null)])->assertRedirect();
        $this->assertSame('Individual', Client::firstOrFail()->sector);
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload()])->assertRedirect();
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame('SOLO PARENT', Client::firstOrFail()->sector);
        $this->assertSame('SOLO PARENT', TransactionEvent::latest('id')->firstOrFail()->sector);
        foreach (['', null] as $sector) {
            $this->post(route('transaction-events.import'), ['csv_file' => $this->upload($sector)])->assertRedirect();
            $this->assertSame('SOLO PARENT', Client::firstOrFail()->sector);
        }
        $html = $this->get(route('clients.show', Client::firstOrFail()))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Sector\s*<\/div>\s*<div class="fw-semibold">SOLO PARENT<\/div>/', $html);
    }

    public function test_matching_reimport_claims_record_and_preserves_type_and_sector(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload('SOLO PARENT', 'TRANCH 1')])->assertRedirect();
        Client::firstOrFail()->update(['sector' => 'COMMON CITIZEN']);
        $this->postJson(route('transaction-events.import.check-duplicates'), ['csv_file' => $this->upload('PWD')])
            ->assertOk()->assertJsonPath('duplicates.0.sector', 'PWD')
            ->assertJsonPath('duplicates.0.existing_sector', 'SOLO PARENT')->assertJsonPath('duplicates.0.matching_records_count', 1);
        $token = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->upload('PWD'), 'update_existing' => 1, 'events_only' => 1,
        ])->assertOk()->json('token');
        $this->postJson(route('transaction-events.import.finish'), ['token' => $token])
            ->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame('COMMON CITIZEN', Client::firstOrFail()->sector);
        $this->assertSame('SOLO PARENT', TransactionEvent::firstOrFail()->sector);
        $this->assertSame('TRANCH 1', TransactionEvent::firstOrFail()->transaction_type);
        $this->assertSame('Claimed', TransactionEvent::firstOrFail()->status);
        $this->assertDatabaseHas('transaction_history', ['type' => 'TRANCH 1', 'events_transaction_type' => 'TRANCH 1', 'status' => 'Claimed']);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseCount('transaction_events', 1);
        $this->postJson(route('transaction-events.import.check-duplicates'), ['csv_file' => $this->upload('PWD')])
            ->assertOk()->assertJsonPath('duplicates.0.existing_sector', 'SOLO PARENT')
            ->assertJsonPath('duplicates.0.sector', 'PWD')->assertJsonPath('duplicates.0.matching_records_count', 1);
        $this->assertSame('COMMON CITIZEN', Client::firstOrFail()->sector);
        foreach (['PWD', '', null] as $sector) {
            $this->post(route('transaction-events.import'), ['csv_file' => $this->upload($sector), 'update_existing' => 1])
                ->assertRedirect()->assertSessionHas('success', 'Import complete: 0 created, 0 updated, 1 unchanged, 0 skipped.');
            $this->assertSame('SOLO PARENT', TransactionEvent::firstOrFail()->sector);
            $this->assertSame('COMMON CITIZEN', Client::firstOrFail()->sector);
        }
    }

    public function test_pending_matching_updates_preserve_sector(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload('SOLO PARENT', 'TRANCH 1'), 'events_only' => 1])->assertRedirect();
        $token = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->upload('PWD'), 'events_only' => 1, 'update_existing' => 1,
        ])->assertOk()->json('token');
        $this->postJson(route('transaction-events.import.process'), ['token' => $token, 'offset' => 0, 'limit' => 1])->assertOk();
        $this->postJson(route('transaction-events.import.finish'), ['token' => $token])->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame('SOLO PARENT', TransactionEvent::firstOrFail()->sector);
        $this->assertSame('TRANCH 1', TransactionEvent::firstOrFail()->transaction_type);
        $this->assertSame('Claimed', TransactionEvent::firstOrFail()->status);
        $this->assertDatabaseCount('transaction_events', 1);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('transaction_history', 0);
    }

    public function test_all_transfer_routes_update_existing_client_sector(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(null)])->assertRedirect();
        foreach (['transaction-events.transfer', 'transaction-events.transfer-one', 'transaction-events.transfer-selected'] as $route) {
            Client::firstOrFail()->update(['sector' => 'OLD SECTOR']);
            $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
            $event = TransactionEvent::latest('id')->firstOrFail();
            $this->assertSame('OLD SECTOR', Client::firstOrFail()->sector);
            if ($route === 'transaction-events.transfer') {
                $this->post(route($route, $event))->assertRedirect();
            } elseif ($route === 'transaction-events.transfer-one') {
                $this->postJson(route($route), ['event_id' => $event->id])->assertOk();
            } else {
                $this->post(route($route), ['event_ids' => [$event->id]])->assertRedirect();
            }
            $this->assertSame('SOLO PARENT', Client::firstOrFail()->sector);
        }
    }

    public function test_overlong_sector_is_reported_before_import(): void
    {
        $this->postJson(route('transaction-events.import.prepare'), ['csv_file' => $this->upload(str_repeat('A', 501))])
            ->assertOk()->assertJsonPath('total', 0)->assertJsonPath('skipped', 1)
            ->assertJsonPath('skipped_examples.0.reason', 'Sector must not exceed 500 characters');
    }
}
