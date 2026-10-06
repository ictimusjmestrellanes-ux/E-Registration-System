<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class ExportRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function seedEvent(array $overrides): TransactionEvent
    {
        return TransactionEvent::create(array_merge([
            'full_name' => 'Alpha One',
            'contact_no' => '09170000001',
            'address' => 'Brgy 1',
            'age' => 40,
            'birth_date' => '1986-05-05',
            'client_id' => 'C1',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-03-09',
            'transferred_at' => now(),
        ], $overrides));
    }

    private function seedClient(string $clientId, string $firstName, string $lastName): void
    {
        \App\Models\Client::create([
            'client_id' => $clientId,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function sheetXml($response): string
    {
        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertNotFalse($xml);

        return $xml;
    }

    public function test_export_downloads_filtered_xlsx(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedClient('C1', 'Alpha', 'One');
        $this->seedClient('C2', 'Beta', 'Two');
        $this->seedEvent(['full_name' => 'Alpha One', 'client_id' => 'C1']);
        // Beta's event is linked to its transaction history (client C2).
        $historyId = DB::table('transaction_history')->insertGetId([
            'transaction_id' => 'C2-26-0001',
            'client_id' => 'C2',
            'transaction_date' => '2026-03-09',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'BIGAY BIGAS SA MASA',
            'events_transaction_type' => 'TRANCH 1',
            'status' => 'Claimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedEvent([
            'full_name' => 'Beta Two',
            'client_id' => 'C2',
            'client_category' => 'LUPON',
            'transferred_transaction_id' => $historyId,
        ]);

        $response = $this->get(route('transaction-events.records.export', [
            'client_category' => 'LUPON',
        ]));

        $xml = $this->sheetXml($response);
        $this->assertStringContainsString('Beta Two', $xml);
        $this->assertStringNotContainsString('Alpha One', $xml);
        $this->assertStringContainsString('LUPON', $xml);
        // Header row present.
        $this->assertStringContainsString('Full Name', $xml);
        $this->assertStringNotContainsString('Client Name', $xml);
    }

    public function test_records_page_renders_xlsx_progress_ui(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertSee('id="recordExportXlsxBtn"', false)
            ->assertSee('id="recordXlsxProgressModal"', false)
            ->assertSee('id="recordXlsxProgressElapsed"', false)
            ->assertSee('event-records-xlsx-export.js');
    }

    public function test_xlsx_export_reports_progress_for_the_current_user(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedEvent(['full_name' => 'Progress Person']);
        $operationId = (string) \Illuminate\Support\Str::uuid();

        $this->get(route('transaction-events.records.export', [
            'export_operation_id' => $operationId,
        ]))->assertOk();

        $this->get(route('transaction-events.records.export-progress', $operationId))
            ->assertOk()
            ->assertJsonPath('state', 'complete')
            ->assertJsonPath('completed', 1)
            ->assertJsonPath('total', 1);
    }

    public function test_browser_export_is_batched_and_downloads_a_valid_xlsx(): void
    {
        $user = User::factory()->create(['role_name' => 'Admin']);
        $this->actingAs($user);

        $now = now();
        $rows = [];
        for ($index = 1; $index <= 1001; $index++) {
            $rows[] = [
                'full_name' => sprintf('Person %04d', $index),
                'contact_no' => '09170000001',
                'address' => 'Brgy 1',
                'age' => 40,
                'birth_date' => '1986-05-05',
                'client_category' => 'INDIGENT',
                'transaction_category' => 'BIGAY BIGAS SA MASA',
                'transaction_type' => 'TRANCH 1',
                'event_date' => '2026-03-09',
                'status' => 'Pending',
                'transferred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('transaction_events')->insert($chunk);
        }

        $start = $this->postJson(route('transaction-events.records.export'))
            ->assertOk()
            ->assertJsonPath('total', 1001)
            ->assertJsonPath('completed', 0)
            ->assertJsonPath('ready', false)
            ->json();

        $firstStep = $this->postJson(route('transaction-events.records.xlsx-step', $start['token']))
            ->assertOk()
            ->assertJsonPath('completed', 1000)
            ->assertJsonPath('ready', false);

        $firstStep->assertJsonPath('total', 1001);
        $this->postJson(route('transaction-events.records.xlsx-step', $start['token']))
            ->assertOk()
            ->assertJsonPath('completed', 1001)
            ->assertJsonPath('ready', true);
        $this->postJson(route('transaction-events.records.xlsx-step', $start['token']))
            ->assertOk()
            ->assertJsonPath('ready', true);

        $log = ActivityLog::where('action', 'event_records_xlsx_exported')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(1001, $log->properties['record_count']);
        $this->assertContains('event_records_xlsx_exported', ActivityLog::NOTIFICATION_ACTIONS);
        $this->getJson(route('notifications.state'))
            ->assertOk()
            ->assertJsonPath('notifications.0.action', 'event_records_xlsx_exported');

        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->get(route('transaction-events.records.xlsx-download', $start['token']))
            ->assertNotFound();

        $this->actingAs($user);
        $response = $this->get(route('transaction-events.records.xlsx-download', $start['token']));
        $xml = $this->sheetXml($response);
        $this->assertStringContainsString('Full Name', $xml);
        $this->assertStringNotContainsString('Client Name', $xml);
        $this->assertStringContainsString('Person 0001', $xml);
        $this->assertStringContainsString('Person 1001', $xml);
    }

    public function test_export_with_no_matches_still_returns_valid_xlsx(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedEvent(['full_name' => 'Alpha One']);

        $response = $this->get(route('transaction-events.records.export', [
            'client_category' => 'NOPE',
        ]));

        $xml = $this->sheetXml($response);
        $this->assertStringContainsString('Full Name', $xml);
        $this->assertStringNotContainsString('Client Name', $xml);
        $this->assertStringNotContainsString('ALPHA ONE', $xml);
    }
}
