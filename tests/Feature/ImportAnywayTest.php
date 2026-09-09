<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertJsonPath('duplicates.0.full_name', 'Dela Cruz, Juan P.');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_events', 0);
        $this->assertDatabaseCount('transaction_history', 0);
    }

    public function test_import_anyway_stages_entire_file_through_chunks_and_finish_fallback(): void
    {
        $this->prepareUser();
        $prepared = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $this->file(), 'events_only' => 1,
        ])->assertOk();
        $token = $prepared->json('token');
        $this->postJson(route('transaction-events.import.process'), ['token' => $token, 'offset' => 0, 'limit' => 1])
            ->assertOk()->assertJsonPath('imported', 1);
        $this->postJson(route('transaction-events.import.finish'), ['token' => $token])
            ->assertOk()->assertJsonPath('imported', 3)->assertJsonPath('skipped', 0);
        $this->assertPendingFile();
        $this->get(route('transaction-events.index'))->assertOk()
            ->assertSee('Dela Cruz, Juan P.')->assertSee('Maria Santos');
        $this->get(route('transaction-events.records'))->assertOk()->assertDontSee('Maria Santos');
    }

    public function test_direct_form_fallback_stages_all_rows(): void
    {
        $this->prepareUser();
        $this->post(route('transaction-events.import'), ['csv_file' => $this->file(), 'events_only' => 1])
            ->assertRedirect(route('transaction-events.index'));
        $this->assertPendingFile();
    }

    private function assertPendingFile(): void
    {
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transaction_history', 0);
        $this->assertDatabaseCount('transaction_events', 3);
        $this->assertSame(3, TransactionEvent::whereNull('transferred_at')->whereNull('transferred_transaction_id')->count());
        $this->assertSame(2, TransactionEvent::where('full_name', 'Maria Santos')->count());
        $this->assertDatabaseHas('transaction_events', ['full_name' => 'Dela Cruz, Juan P.', 'contact_no' => '09170000001']);
    }
}
