<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportAnywayTest extends TestCase
{
    use RefreshDatabase;

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('review.csv',
            "full_name,contact_no,birth_date,transaction_category,transaction_type,event_date\n".
            "\"Dela Cruz, Juan P.\",09170000001,,Food,Rice,2026-09-01\n".
            "Maria Santos,09170000002,,Food,Rice,2026-09-01\n".
            "Maria Santos,09170000002,,Food,Rice,2026-09-01\n");
    }

    private function prepareUser(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Client::create(['client_id' => '2600001', 'first_name' => 'Juan', 'middle_name' => 'P.', 'last_name' => 'Dela Cruz']);
    }

    public function test_client_matches_and_file_duplicates_trigger_warning_without_writes(): void
    {
        $this->prepareUser();
        $this->postJson(route('transaction-events.import.check-duplicates'), ['csv_file' => $this->file()])
            ->assertOk()->assertJsonPath('duplicates_count', 2)->assertJsonPath('total_rows', 3)
            ->assertJsonPath('duplicates.0.full_name', 'Dela Cruz, Juan P.')
            ->assertJson(fn ($json) => $json->whereType('check_token', 'string')->etc());
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_events', 0);
        $this->assertDatabaseCount('transaction_history', 0);
    }

    public function test_import_anyway_reuses_existing_client_and_registers_new_client_through_chunks_and_finish_fallback(): void
    {
        $this->prepareUser();
        $prepared = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->file(),
        ])->assertOk();
        $token = $prepared->json('token');
        $this->postJson(route('transaction-events.import.process'), ['token' => $token, 'offset' => 0, 'limit' => 1])
            ->assertOk()->assertJsonPath('imported', 1);
        $this->postJson(route('transaction-events.import.finish'), ['token' => $token])
            ->assertOk()->assertJsonPath('imported', 3)->assertJsonPath('skipped', 0);
        $this->assertTransferredFile();
        $this->get(route('transaction-events.index'))->assertOk()
            ->assertDontSee('Maria Santos');
        $this->get(route('transaction-events.records'))->assertOk()
            ->assertSee('Dela Cruz, Juan P.')->assertSee('Maria Santos');
    }

    public function test_direct_form_fallback_transfers_all_rows(): void
    {
        $this->prepareUser();
        $this->post(route('transaction-events.import'), ['csv_file' => $this->file()])
            ->assertRedirect(route('transaction-events.index'));
        $this->assertTransferredFile();
    }

    public function test_import_anyway_button_uses_client_and_history_import_mode(): void
    {
        $this->prepareUser();
        $html = $this->get(route('transaction-events.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            "/importDuplicateContinueBtn'\)\?\.addEventListener\('click', function\(\) \{.{0,260}runImport\(false, false, false, importCheckToken\);/s",
            $html
        );
    }

    public function test_confirm_check_payload_is_reused_once_without_uploading_the_file_again(): void
    {
        $this->prepareUser();

        $check = $this->postJson(route('transaction-events.import.check-duplicates'), [
            'csv_file' => $this->file(),
        ])->assertOk();
        $checkToken = $check->json('check_token');

        $prepared = $this->postJson(route('transaction-events.import.prepare'), [
            'check_token' => $checkToken,
        ])->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('chunk_size', 1000);

        $this->assertNotEmpty($prepared->json('token'));
        $this->postJson(route('transaction-events.import.prepare'), [
            'check_token' => $checkToken,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_normal_import_processes_a_large_chunk_with_batched_database_writes(): void
    {
        $this->prepareUser();
        $rows = array_fill(0, 120, '"Dela Cruz, Juan P.",09170000001,,Food,Rice,2026-09-01');
        $file = UploadedFile::fake()->createWithContent(
            'fast.csv',
            "full_name,contact_no,birth_date,transaction_category,transaction_type,event_date\n".
            implode("\n", $rows)
        );
        $token = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $file,
        ])->assertOk()
            ->assertJsonPath('chunk_size', 1000)
            ->json('token');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson(route('transaction-events.import.process'), [
            'token' => $token,
            'offset' => 0,
            'limit' => 1000,
        ])->assertOk()
            ->assertJsonPath('imported', 120)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('done', true);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $queryCount, 'The normal import should use batched writes, not per-row inserts.');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 120);
        $this->assertDatabaseCount('transaction_events', 120);
        $this->assertSame(120, TransactionEvent::whereNotNull('transferred_transaction_id')->count());
    }

    private function assertTransferredFile(): void
    {
        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('transaction_history', 3);
        $this->assertDatabaseCount('transaction_events', 3);
        $this->assertSame(0, TransactionEvent::whereNull('transferred_at')->count());
        $this->assertSame(2, TransactionEvent::where('full_name', 'Maria Santos')->count());
        $this->assertDatabaseHas('transaction_events', ['full_name' => 'Dela Cruz, Juan P.', 'contact_no' => '09170000001']);
        $this->assertSame(1, TransactionHistory::where('client_id', '2600001')->count());
        $maria = Client::where('first_name', 'Maria')->where('last_name', 'Santos')->firstOrFail();
        $this->assertSame(2, TransactionHistory::where('client_id', $maria->client_id)->count());
        $this->assertSame(3, TransactionEvent::whereHas('transferredTransaction')->count());
    }
}
