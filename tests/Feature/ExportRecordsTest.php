<?php

namespace Tests\Feature;

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
            'status' => 'Approved',
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
        // Client names resolve via linked clients (full_name accessor uppercases).
        $this->assertStringContainsString('BETA TWO', $xml);
        $this->assertStringNotContainsString('ALPHA ONE', $xml);
        $this->assertStringContainsString('LUPON', $xml);
        // Header row present.
        $this->assertStringContainsString('Client Name', $xml);
    }

    public function test_export_with_no_matches_still_returns_valid_xlsx(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->seedEvent(['full_name' => 'Alpha One']);

        $response = $this->get(route('transaction-events.records.export', [
            'client_category' => 'NOPE',
        ]));

        $xml = $this->sheetXml($response);
        $this->assertStringContainsString('Client Name', $xml);
        $this->assertStringNotContainsString('ALPHA ONE', $xml);
    }
}
