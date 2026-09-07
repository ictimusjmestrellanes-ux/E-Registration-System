<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_events_export_returns_valid_xlsx_package(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        TransactionEvent::create([
            'full_name' => 'Export Me',
            'contact_no' => '09170000009',
            'address' => 'Brgy Export',
            'age' => 33,
            'birth_date' => '1993-01-01',
            'client_id' => 'C9',
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'transaction_type' => 'TRANCH 1',
            'event_date' => '2026-04-01',
            'transferred_at' => null,
            'not_duplicate' => false,
        ]);
        // A transferred event must be excluded from the pending export.
        TransactionEvent::create([
            'full_name' => 'Already Transferred',
            'transferred_at' => now(),
            'not_duplicate' => false,
        ]);

        $response = $this->get(route('transaction-events.export'));

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));

        // Every part of the package must exist at its spec-correct path.
        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ] as $part) {
            $content = $zip->getFromName($part);
            $this->assertNotFalse($content, "missing package part: {$part}");
            $this->assertNotFalse(@simplexml_load_string($content), "malformed XML: {$part}");
        }
        // The old broken assembly leaked a junk part at the wrong path.
        $this->assertFalse($zip->getFromName('xl/workbookRels.xml'));

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Full Name', $sheet);
        $this->assertStringContainsString('Export Me', $sheet);
        $this->assertStringNotContainsString('Already Transferred', $sheet);
        // Header row carries the bold style index defined in styles.xml.
        $this->assertStringContainsString('s="1"', $sheet);

        $styles = $zip->getFromName('xl/styles.xml');
        $this->assertStringContainsString('<b/>', $styles);

        $zip->close();
    }
}
