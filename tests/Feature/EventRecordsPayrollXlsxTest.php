<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class EventRecordsPayrollXlsxTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes): TransactionEvent
    {
        return TransactionEvent::create(array_merge([
            'full_name' => 'ABA, HONEY',
            'address' => 'Brgy 1',
            'age' => 42,
            'client_category' => 'INDIGENT',
            'transaction_category' => 'BIGAY BIGAS SA MASA',
            'event_date' => '2026-03-09',
            'transferred_at' => now(),
        ], $attributes));
    }

    private function workbookParts($response): array
    {
        $response->assertOk()->assertHeader(
            'Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $workbook = $zip->getFromName('xl/workbook.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($workbook);
        $this->assertNotFalse($styles);

        return [$sheet, $workbook, $styles];
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }

    private function cell(DOMXPath $xpath, string $reference): string
    {
        return $xpath->evaluate('string(//x:c[@r="'.$reference.'"]/x:is/x:t | //x:c[@r="'.$reference.'"]/x:v)');
    }

    public function test_payroll_details_uses_a_calendar_date_picker(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $this->get(route('transaction-events.records'))
            ->assertOk()
            ->assertSee('<input type="date" class="form-control" id="recordPdfDate" name="report_date">', false);
    }

    public function test_payroll_excel_uses_filters_report_details_and_printable_layout(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Client::create(['client_id' => 'C1', 'first_name' => 'Honey', 'last_name' => 'Aba', 'gender' => 'Female']);
        $historyId = DB::table('transaction_history')->insertGetId([
            'transaction_id' => 'C1-26-0001',
            'client_id' => 'C1',
            'transaction_date' => '2026-03-09',
            'category' => 'BIGAY BIGAS SA MASA',
            'type' => 'BIGAY BIGAS SA MASA',
            'status' => 'Claimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->event(['full_name' => 'ABAD, ANGELO', 'event_date' => '2026-03-10']);
        $this->event(['transferred_transaction_id' => $historyId]);
        $this->event(['full_name' => 'ABAD ARTJAY REYES']);
        $this->event(['full_name' => 'OTHER, CLIENT', 'client_category' => 'PWD']);

        [$sheet, $workbook, $styles] = $this->workbookParts($this->get(route('transaction-events.records.payroll-xlsx', [
            'client_category' => 'INDIGENT',
            'report_date' => 'March 2026',
            'prepared_by' => 'Payroll Officer',
            'reviewed_by' => 'Supervisor',
            'approved_by' => 'Director',
            'numbered_tranches' => [1, 3],
        ])));

        $xpath = $this->xpath($sheet);
        $this->assertStringContainsString('RICE DISTRIBUTION PROJECT', $this->cell($xpath, 'A1'));
        $this->assertSame('DATE: March 2026', $this->cell($xpath, 'A2'));
        $this->assertSame("BENEF'S NAME", $this->cell($xpath, 'C3'));
        $this->assertSame('SIGNATURE 4th TRANCHE', $this->cell($xpath, 'L3'));
        $this->assertSame('ABA, HONEY', $this->cell($xpath, 'C4'));
        $this->assertSame('ABAD, ANGELO', $this->cell($xpath, 'C5'));
        $this->assertSame('REYES, ABAD ARTJAY', $this->cell($xpath, 'C6'));
        $this->assertSame('FEMALE', $this->cell($xpath, 'E4'));
        $this->assertSame('FOOD ASSISTANCE', $this->cell($xpath, 'H4'));
        $this->assertSame('1', $this->cell($xpath, 'I4'));
        $this->assertSame('', $this->cell($xpath, 'J4'));
        $this->assertSame('1', $this->cell($xpath, 'K4'));
        $this->assertSame('', $this->cell($xpath, 'L4'));
        $this->assertStringNotContainsString('OTHER, CLIENT', $sheet);
        $this->assertStringContainsString('Payroll Officer', $sheet);
        $this->assertStringContainsString('Supervisor', $sheet);
        $this->assertStringContainsString('Director', $sheet);
        $this->assertSame('landscape', $xpath->evaluate('string(//x:pageSetup/@orientation)'));
        $this->assertSame('1', $xpath->evaluate('string(//x:pageSetup/@fitToWidth)'));
        $this->assertSame('1', $xpath->evaluate('string(//x:pageSetup/@fitToHeight)'));
        $this->assertSame('14', $xpath->evaluate('string(//x:pageSetup/@paperSize)'));
        $this->assertSame('0', $xpath->evaluate('string(//x:pageMargins/@bottom)'));
        $this->assertSame('5', $xpath->evaluate('string(//x:c[@r="A4"]/@s)'));
        $this->assertSame('8', $xpath->evaluate('string(//x:c[@r="I4"]/@s)'));
        $this->assertSame('8', $xpath->evaluate('string(//x:c[@r="K4"]/@s)'));
        $this->assertStringContainsString('<sz val="7"/>', $styles);
        $this->assertStringNotContainsString('_xlnm.Print_Titles', $workbook);
    }

    public function test_payroll_excel_without_matches_still_has_headers_and_message(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        [$sheet] = $this->workbookParts($this->get(route('transaction-events.records.payroll-xlsx', [
            'client_category' => 'NO MATCH',
        ])));

        $xpath = $this->xpath($sheet);
        $this->assertSame('No records match the selected filters.', $this->cell($xpath, 'A4'));
        $this->assertSame('CLIENT NAME', $this->cell($xpath, 'B3'));
    }

    public function test_payroll_excel_starts_a_new_print_section_after_twenty_records(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        foreach (range(1, 21) as $number) {
            $this->event(['full_name' => sprintf('PERSON, %02d', $number)]);
        }

        [$sheet] = $this->workbookParts($this->get(route('transaction-events.records.payroll-xlsx')));
        $xpath = $this->xpath($sheet);

        $this->assertSame('20', $this->cell($xpath, 'A23'));
        $this->assertStringContainsString('RICE DISTRIBUTION PROJECT', $this->cell($xpath, 'A26'));
        $this->assertSame('NO', $this->cell($xpath, 'A28'));
        $this->assertSame('21', $this->cell($xpath, 'A29'));
        $this->assertSame('25', $xpath->evaluate('string(//x:rowBreaks/x:brk/@id)'));
        $this->assertSame('1', $xpath->evaluate('string(//x:rowBreaks/@manualBreakCount)'));
        $this->assertSame('2', $xpath->evaluate('string(//x:pageSetup/@fitToHeight)'));
    }
}
