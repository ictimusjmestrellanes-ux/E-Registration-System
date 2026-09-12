<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class EventRecordsPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_exports_all_filtered_records_alphabetically(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $attributes = [
            'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'TRANCH 1',
            'client_category' => 'INDIGENT', 'event_date' => '2026-03-09',
            'transferred_at' => '2026-04-01 12:00:00', 'address' => 'ALAPAN I-C', 'age' => 35,
        ];
        foreach (['ZULU, ZOE', 'aba, ana', 'BETA, BEN'] as $name) {
            TransactionEvent::create(['full_name' => $name] + $attributes);
        }
        foreach ([['transferred_at' => null], ['transaction_category' => 'OTHER'], ['event_date' => '2026-03-10'], ['client_category' => 'LUPON'], ['transaction_type' => 'TRANCH 2']] as $override) {
            TransactionEvent::create(['full_name' => 'EXCLUDED'] + $override + $attributes);
        }

        $data = null;
        View::composer('pages.transaction_events.recordsPdf', function ($view) use (&$data) {
            $data = $view->getData();
        });
        $response = $this->get(route('transaction-events.records.export-pdf', [
            'transaction_category' => ['BIGAY BIGAS SA MASA'], 'client_category' => ['INDIGENT'],
            'transaction_type' => ['TRANCH 1'], 'event_date_from' => '2026-03-09', 'event_date_to' => '2026-03-09',
            'page' => 2, 'per_page' => 1, 'sort_by' => 'id', 'sort_dir' => 'desc',
        ]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(['aba, ana', 'BETA, BEN', 'ZULU, ZOE'], $data['events']->pluck('full_name')->all());
        $this->assertTrue($data['isRice']);
        $this->assertSame('2026-03-09', $data['dateLabel']);
    }

    public function test_empty_export_is_a_valid_pdf(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->get(route('transaction-events.records.export-pdf', ['transaction_category' => 'BIGAY BIGAS SA MASA']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_only_selected_signature_columns_are_numbered_across_pages(): void
    {
        $events = collect(range(1, 21))->map(fn () => new TransactionEvent(['full_name' => 'BENEFICIARY']));
        foreach ([[], [2, 4], [1, 2, 3, 4]] as $selected) {
            $html = view('pages.transaction_events.recordsPdf', [
                'events' => $events, 'isRice' => true, 'dateLabel' => '', 'rowOffset' => 100,
                'details' => ['numbered_tranches' => $selected],
            ])->render();
            $document = new \DOMDocument();
            @$document->loadHTML($html);
            $rows = (new \DOMXPath($document))->query('//tbody/tr');
            $this->assertCount(40, $rows);
            foreach ($rows as $index => $row) {
                $cells = $row->getElementsByTagName('td');
                for ($tranche = 1; $tranche <= 4; $tranche++) {
                    $expected = $index < 21 && in_array($tranche, $selected) ? (string) (101 + $index) : '';
                    $this->assertSame($expected, trim($cells->item(7 + $tranche)->textContent));
                }
            }
        }
    }

    public function test_invalid_signature_tranche_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        $this->postJson(route('transaction-events.records.export-pdf'), ['numbered_tranches' => [5]])
            ->assertUnprocessable()->assertJsonValidationErrors('numbered_tranches.0');
    }

    public function test_pdf_requires_authentication(): void
    {
        $this->get(route('transaction-events.records.export-pdf'))->assertRedirect(route('login'));
    }

    public function test_twenty_rows_and_signoffs_fit_on_each_landscape_page(): void
    {
        $events = collect(range(1, 41))->map(fn () => new TransactionEvent([
            'full_name' => 'ABARRIENTOS, JENNELYN MAE ALVAREZ',
            'address' => 'CARSADANG BAGO I', 'age' => 42,
            'client_category' => 'INDIGENT', 'transaction_category' => 'BIGAY BIGAS SA MASA',
        ]));
        $html = view('pages.transaction_events.recordsPdf', [
            'events' => $events, 'isRice' => true, 'dateLabel' => '2026-03-09',
        ])->render();
        $pdf = new \Dompdf\Dompdf();
        $pdf->setPaper(\App\Services\EventRecordsPdfExporter::PAPER_SIZE, 'landscape');
        $pdf->loadHtml($html);
        $pdf->render();
        $this->assertSame(3, $pdf->getCanvas()->get_page_count());
    }

    public function test_export_batches_keep_every_record_and_continuous_numbering(): void
    {
        $batches = [];
        View::composer('pages.transaction_events.recordsPdf', function ($view) use (&$batches) {
            $data = $view->getData();
            $batches[] = [$data['events']->count(), $data['rowOffset']];
        });
        $events = collect(range(1, 221))->map(fn ($i) => new TransactionEvent([
            'full_name' => sprintf('BENEFICIARY, %04d', $i),
            'transaction_category' => 'BIGAY BIGAS SA MASA', 'client_category' => 'INDIGENT',
        ]));
        $content = app(\App\Services\EventRecordsPdfExporter::class)->render($events, true, '2026-03-09');
        $reader = new \setasign\Fpdi\Fpdi();
        $pages = $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($content));
        $this->assertSame(12, $pages);
        $size = $reader->getTemplateSize($reader->importPage(1));
        $this->assertEqualsWithDelta(330.2, $size['width'], 0.01);
        $this->assertEqualsWithDelta(215.9, $size['height'], 0.01);
        $this->assertSame([[100, 0], [100, 100], [21, 200]], $batches);
        $this->assertStringStartsWith('%PDF-', $content);
        $reader->cleanUp(true);
    }

    public function test_progressive_export_filters_sorts_and_downloads_with_owner_protection(): void
    {
        $owner = User::factory()->create(['role_name' => 'Admin']);
        $other = User::factory()->create(['role_name' => 'Admin']);
        $this->actingAs($owner);
        foreach (range(101, 1) as $i) {
            TransactionEvent::create([
                'full_name' => sprintf('BENEFICIARY, %04d', $i), 'transferred_at' => now(),
                'transaction_category' => 'BIGAY BIGAS SA MASA', 'transaction_type' => 'SIR ALLEN',
            ]);
        }
        TransactionEvent::create(['full_name' => 'EXCLUDED', 'transferred_at' => now(), 'transaction_type' => 'OTHER']);
        $start = $this->postJson(route('transaction-events.records.export-pdf', [
            'transaction_category' => ['BIGAY BIGAS SA MASA'], 'transaction_type' => ['SIR ALLEN'], 'page' => 2,
        ]), [
            'prepared_by' => 'CUSTOM PREPARER', 'reviewed_by' => 'CUSTOM REVIEWER',
            'approved_by' => 'CUSTOM APPROVER', 'report_date' => 'September 12, 2026',
            'numbered_tranches' => [2, 4],
        ])->assertOk()->assertJsonPath('total', 101)->assertJsonPath('completed', 0);
        $token = $start->json('token');
        try {
            $step = route('transaction-events.records.pdf-step', $token);
            $download = route('transaction-events.records.pdf-download', $token);
            $this->get($download)->assertStatus(409);
            $this->actingAs($other)->postJson($step)->assertNotFound();
            $this->get($download)->assertNotFound();
            $this->actingAs($owner);
            $seen = [];
            View::composer('pages.transaction_events.recordsPdf', function ($view) use (&$seen) {
                $this->assertSame('September 12, 2026', $view->getData()['dateLabel']);
                $this->assertSame('CUSTOM PREPARER', $view->getData()['details']['prepared_by']);
                $this->assertSame('CUSTOM REVIEWER', $view->getData()['details']['reviewed_by']);
                $this->assertSame('CUSTOM APPROVER', $view->getData()['details']['approved_by']);
                $this->assertSame([2, 4], $view->getData()['details']['numbered_tranches']);
                $seen = array_merge($seen, $view->getData()['events']->pluck('full_name')->all());
            });
            $this->postJson($step)->assertOk()->assertJsonPath('completed', 100)->assertJsonPath('ready', false);
            $this->postJson($step)->assertOk()->assertJsonPath('completed', 101)->assertJsonPath('ready', false);
            $this->postJson($step)->assertOk()->assertJsonPath('ready', true);
            $this->postJson($step)->assertOk()->assertJsonPath('ready', true);
            $this->assertSame(array_map(fn ($i) => sprintf('BENEFICIARY, %04d', $i), range(1, 101)), $seen);
            $response = $this->get($download)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $reader = new \setasign\Fpdi\Fpdi();
            $this->assertSame(6, $reader->setSourceFile($response->getFile()->getPathname()));
            $size = $reader->getTemplateSize($reader->importPage(6));
            $this->assertEqualsWithDelta(330.2, $size['width'], 0.01);
            $this->assertEqualsWithDelta(215.9, $size['height'], 0.01);
            $reader->cleanUp(true);
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/private/event-pdf-exports/'.$token));
        }
    }
}
