<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUpdateExistingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
    }

    private function upload(array $changes = [], bool $xlsx = false): UploadedFile
    {
        $row = array_replace([
            'full_name' => 'DE QUIROZ, GEMMA S.', 'contact_no' => '', 'address' => 'BUHAY NA TUBIG',
            'age' => '', 'birth_date' => '', 'event_date' => '2026-07-03',
            'client_category' => 'SOLO PARENT', 'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
        ], $changes);
        if ($xlsx) {
            $template = $this->get(route('transaction-events.template'))->assertOk();
            $path = $template->baseResponse->getFile()->getPathname();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ([array_keys($row), array_values($row)] as $index => $cells) {
                $xml .= '<row r="'.($index + 1).'">';
                foreach ($cells as $column => $value) {
                    $xml .= '<c r="'.chr(65 + $column).($index + 1).'" t="inlineStr"><is><t>'
                        .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
            $zip->close();
            $contents = file_get_contents($path);
            unlink($path);
            return UploadedFile::fake()->createWithContent('update.xlsx', $contents);
        }
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, array_keys($row));
        fputcsv($stream, array_values($row));
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return UploadedFile::fake()->createWithContent('update.csv', $contents);
    }

    private function updateImport(array $changes = [], bool $chunk = true, bool $xlsx = false)
    {
        $token = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->upload($changes, $xlsx), 'events_only' => 1, 'update_existing' => 1,
        ])->assertOk()->json('token');
        if ($chunk) {
            $this->postJson(route('transaction-events.import.process'), ['token' => $token, 'offset' => 0, 'limit' => 1])->assertOk();
        }
        return $this->postJson(route('transaction-events.import.finish'), ['token' => $token])->assertOk();
    }

    public function test_csv_then_xlsx_updates_existing_history_and_repeat_is_unchanged(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload()])->assertRedirect();
        $original = TransactionEvent::firstOrFail();
        $this->postJson(route('transaction-events.import.check-duplicates'), [
            'csv_file' => $this->upload(['transaction_type' => 'CLAIMED']),
        ])->assertOk()->assertJsonPath('duplicates_count', 1);
        $this->updateImport(['transaction_type' => 'CLAIMED'], true, true)
            ->assertJsonPath('updated', 1)->assertJsonPath('created', 0);
        $this->updateImport(['transaction_type' => 'CLAIMED'], false)
            ->assertJsonPath('unchanged', 1)->assertJsonPath('updated', 0);
        $this->assertDatabaseCount('transaction_events', 1);
        $this->assertDatabaseCount('transaction_history', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('transaction_events', ['id' => $original->id, 'transaction_type' => 'TRANCH 1', 'status' => 'Claimed',
            'transferred_transaction_id' => $original->transferred_transaction_id]);
        $this->assertDatabaseHas('transaction_history', ['id' => $original->transferred_transaction_id,
            'type' => 'TRANCH 1', 'events_transaction_type' => 'TRANCH 1', 'status' => 'Claimed']);
        $summaries = \App\Models\ActivityLog::where('action', 'events_imported')->orderBy('id')->get();
        $this->assertCount(3, $summaries);
        $this->assertSame(1, $summaries[0]->properties['created']);
        $this->assertSame('update.xlsx', $summaries[1]->properties['filename']);
        $this->assertSame(1, $summaries[1]->properties['updated']);
        $this->assertSame(1, $summaries[2]->properties['unchanged']);
        $statusLog = \App\Models\ActivityLog::where('action', 'transaction_event_updated')
            ->where('subject_id', $original->id)->firstOrFail();
        $this->assertSame('Pending', $statusLog->properties['before']['status']);
        $this->assertSame('Claimed', $statusLog->properties['after']['status']);
    }

    public function test_pending_record_updates_and_distinct_events_are_created(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
        $this->updateImport(['transaction_type' => 'CLAIMED', 'full_name' => ' de quiroz, gemma s. '], false)
            ->assertJsonPath('updated', 1);
        foreach (['event_date' => '2026-07-04', 'address' => 'OTHER ADDRESS', 'birth_date' => '1980-01-01',
            'client_category' => 'SENIOR', 'transaction_category' => 'OTHER PROGRAM'] as $field => $value) {
            $this->updateImport([$field => $value])->assertJsonPath('created', 1)->assertJsonPath('updated', 0);
        }
        $this->assertDatabaseCount('transaction_events', 6);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('transaction_history', 0);
    }

    public function test_formatted_event_dates_match_existing_records_with_a_sector(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(['sector' => 'SOLO PARENT'])])->assertRedirect();
        $event = TransactionEvent::firstOrFail();
        $changes = ['event_date' => '07/03/2026', 'sector' => 'PWD'];
        $this->postJson(route('transaction-events.import.check-duplicates'), [
            'csv_file' => $this->upload($changes),
        ])->assertOk()->assertJsonPath('duplicates.0.matching_records_count', 1);
        $this->updateImport($changes, true, true)
            ->assertJsonPath('updated', 1)->assertJsonPath('created', 0)->assertJsonPath('skipped', 0);
        $this->updateImport(['event_date' => 'July 3, 2026'], false)
            ->assertJsonPath('unchanged', 1)->assertJsonPath('created', 0);
        $this->assertDatabaseCount('transaction_events', 1);
        $this->assertSame('Claimed', $event->fresh()->status);
        $this->assertSame('Claimed', $event->fresh()->transferredTransaction->status);
        $this->assertSame('SOLO PARENT', $event->fresh()->sector);
    }

    public function test_import_and_duplicate_views_display_the_saved_claimed_status(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
        $this->updateImport()->assertJsonPath('updated', 1);
        $event = TransactionEvent::firstOrFail();
        $html = $this->get(route('transaction-events.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-event-status="'.$event->id.'">Claimed</span>', $html);

        $event->replicate()->save();
        $html = $this->get(route('transaction-events.duplicate-review'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<span class="badge bg-success-subtle text-success">Claimed<\/span>/', $html);
    }

    public function test_ambiguous_matches_are_reported_without_modifying_them(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
        }
        $this->updateImport(['transaction_type' => 'CLAIMED'])
            ->assertJsonPath('skipped', 1)->assertJsonPath('updated', 0)
            ->assertJsonPath('errors.0.error', 'Multiple existing records match this row. Resolve the duplicates before updating.');
        $this->assertSame(2, TransactionEvent::where('transaction_type', 'TRANCH 1')->count());
    }

    public function test_claimed_import_keeps_status_when_later_transferred(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload()])->assertRedirect();
        foreach (['transaction-events.transfer', 'transaction-events.transfer-one', 'transaction-events.transfer-selected'] as $route) {
            $date = '2026-07-'.(10 + TransactionEvent::count());
            $this->post(route('transaction-events.import'), [
                'csv_file' => $this->upload(['event_date' => $date]), 'events_only' => 1,
            ])->assertRedirect();
            $this->updateImport(['event_date' => $date, 'transaction_type' => ''])
                ->assertJsonPath('updated', 1)->assertJsonPath('skipped', 0);
            $event = TransactionEvent::latest('id')->firstOrFail();
            if ($route === 'transaction-events.transfer') {
                $this->post(route($route, $event))->assertRedirect();
            } elseif ($route === 'transaction-events.transfer-one') {
                $this->postJson(route($route), ['event_id' => $event->id])->assertOk();
            } else {
                $this->post(route($route), ['event_ids' => [$event->id]])->assertRedirect();
            }
            $event->refresh();
            $this->assertSame('Claimed', $event->status);
            $this->assertSame('Claimed', $event->transferredTransaction->status);
            $this->assertSame('TRANCH 1', $event->transferredTransaction->type);
        }
    }

    public function test_direct_update_claims_record_even_with_blank_import_type(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
        $this->post(route('transaction-events.import'), [
            'csv_file' => $this->upload(['transaction_type' => 'CLAIMED']), 'update_existing' => 1,
        ])->assertRedirect()->assertSessionHas('success', 'Import complete: 0 created, 1 updated, 0 unchanged, 0 skipped.');
        $this->updateImport(['transaction_type' => ''])->assertJsonPath('unchanged', 1)->assertJsonPath('skipped', 0);
        $this->updateImport(['event_date' => ''])->assertJsonPath('skipped', 1);
        $this->assertDatabaseCount('transaction_events', 1);
        $this->assertSame('TRANCH 1', TransactionEvent::firstOrFail()->transaction_type);
        $this->assertSame('Claimed', TransactionEvent::firstOrFail()->status);
    }

    public function test_repeated_rows_across_chunks_claim_once_and_new_rows_are_staged(): void
    {
        $this->post(route('transaction-events.import'), ['csv_file' => $this->upload(), 'events_only' => 1])->assertRedirect();
        $first = $this->upload(['transaction_type' => 'CLAIMED'])->get();
        $second = explode("\n", trim($this->upload(['transaction_type' => 'RELEASED'])->get()))[1];
        $third = explode("\n", trim($this->upload(['full_name' => 'NEW CLIENT'])->get()))[1];
        $token = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => UploadedFile::fake()->createWithContent('mixed.csv', $first.$second."\n".$third."\n"),
            'events_only' => 1, 'update_existing' => 1,
        ])->assertOk()->json('token');
        $this->postJson(route('transaction-events.import.process'), ['token' => $token, 'offset' => 0, 'limit' => 1])->assertOk();
        $this->postJson(route('transaction-events.import.finish'), ['token' => $token])->assertOk()
            ->assertJsonPath('updated', 1)->assertJsonPath('unchanged', 1)->assertJsonPath('created', 1)->assertJsonPath('skipped', 0);
        $this->assertDatabaseCount('transaction_events', 2);
        $this->assertDatabaseHas('transaction_events', ['full_name' => 'DE QUIROZ, GEMMA S.', 'transaction_type' => 'TRANCH 1', 'status' => 'Claimed']);
        $this->assertDatabaseHas('transaction_events', ['full_name' => 'NEW CLIENT', 'status' => 'Pending']);
        $this->assertDatabaseCount('transaction_history', 0);
        $this->get(route('transaction-events.index'))->assertOk()->assertSee('Update Matching Records');
    }
}
