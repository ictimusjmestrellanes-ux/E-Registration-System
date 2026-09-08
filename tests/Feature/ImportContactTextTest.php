<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ImportContactTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_and_excel_imports_preserve_multiple_contacts_as_text(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $contacts = str_repeat('0917-123-4567 / +63 928 123 4567; ', 10).'09181234567, 09991234567';

        foreach (['csv', 'xlsx'] as $extension) {
            $path = tempnam(sys_get_temp_dir(), 'contact_text_');
            $rows = [
                ['Full Name', 'Contact No.', 'Address', 'Age'],
                ['Contact '.strtoupper($extension), $contacts, 'Sample Address', '30'],
            ];
            try {
                if ($extension === 'xlsx') {
                    $template = $this->get(route('transaction-events.template'))->assertOk();
                    $templatePath = $template->baseResponse->getFile()->getPathname();
                    copy($templatePath, $path);
                    unlink($templatePath);
                    $zip = new ZipArchive;
                    $this->assertTrue($zip->open($path));
                    $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
                    foreach ($rows as $index => $row) {
                        $xml .= '<row r="'.($index + 1).'">';
                        foreach ($row as $column => $value) {
                            $xml .= '<c r="'.chr(65 + $column).($index + 1).'" t="inlineStr"><is><t>'
                                .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                        }
                        $xml .= '</row>';
                    }
                    $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
                    $zip->close();
                } else {
                    $handle = fopen($path, 'w');
                    foreach ($rows as $row) {
                        fputcsv($handle, $row);
                    }
                    fclose($handle);
                }

                $this->post(route('transaction-events.import'), [
                    'csv_file' => new UploadedFile($path, 'contacts.'.$extension, null, null, true),
                ])->assertRedirect(route('transaction-events.index'))->assertSessionHasNoErrors();

                $event = TransactionEvent::where('full_name', 'Contact '.strtoupper($extension))->firstOrFail();
                $this->assertSame($contacts, $event->contact_no);
                $client = Client::where('client_id', $event->transferredTransaction->client_id)->firstOrFail();
                $this->assertSame($contacts, $client->contact);
            } finally {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        foreach (['clients' => 'contact', 'archived_clients' => 'contact', 'transaction_events' => 'contact_no', 'transaction_event_archives' => 'contact_no'] as $table => $column) {
            $this->assertSame('text', Schema::getColumnType($table, $column));
        }
    }

    public function test_excel_template_has_text_formatted_contact_cells(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('transaction-events.template'))->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
            $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $cells = $sheet->xpath('//*[local-name()="c" and @r="B2"]');
            $this->assertCount(1, $cells);
            $cell = $cells[0];
            $this->assertSame('inlineStr', (string) $cell['t']);
            $this->assertSame('09123456789 / 09198765432', (string) $cell->is->t);
            $formatId = (int) $styles->cellXfs->xf[(int) $cell['s']]['numFmtId'];
            // 49 is Excel's built-in Text number format.
            $customFormats = $styles->xpath('//*[local-name()="numFmt" and @numFmtId="'.$formatId.'"]');
            $this->assertTrue($formatId === 49 || (string) ($customFormats[0]['formatCode'] ?? '') === '@');
            $columns = $sheet->xpath('//*[local-name()="col" and @min="2" and @max="2"]');
            $this->assertSame((string) $cell['s'], (string) $columns[0]['style']);
        } finally {
            $zip->close();
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
