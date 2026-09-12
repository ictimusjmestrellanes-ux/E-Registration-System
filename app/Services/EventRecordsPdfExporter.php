<?php

namespace App\Services;

use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class EventRecordsPdfExporter
{
    public const BATCH_SIZE = 100;

    // 8.5 x 13 inches, expressed in PDF points (72 points per inch).
    public const PAPER_SIZE = [0, 0, 612, 936];

    public function renderBatch(Collection $events, bool $isRice, string $dateLabel, int $rowOffset, array $details = []): string
    {
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
        $pdf->setPaper(self::PAPER_SIZE, 'landscape');
        $pdf->loadHtml(view('pages.transaction_events.recordsPdf', [
            'events' => $events, 'isRice' => $isRice, 'dateLabel' => $dateLabel,
            'rowOffset' => $rowOffset, 'mergedExport' => true, 'details' => $details,
        ])->render());
        $pdf->render();
        $content = $pdf->output();
        unset($pdf);
        gc_collect_cycles();

        return $content;
    }

    public function mergeFiles(array $paths): string
    {
        $merged = new Fpdi('L', 'pt', [612, 936]);
        $merged->SetAutoPageBreak(false);
        $merged->AliasNbPages();
        try {
            foreach ($paths as $path) {
                $pageCount = $merged->setSourceFile($path);
                for ($page = 1; $page <= $pageCount; $page++) {
                    $template = $merged->importPage($page);
                    $size = $merged->getTemplateSize($template);
                    $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $merged->useTemplate($template);
                    $merged->SetFont('Helvetica', '', 6);
                    $merged->SetXY(30, $size['height'] - 20);
                    $merged->Cell($size['width'] - 60, 8, 'Page '.$merged->PageNo().' of {nb}', 0, 0, 'R');
                }
            }

            return $merged->Output('S');
        } finally {
            $merged->cleanUp(true);
        }
    }

    public function render(Collection $events, bool $isRice, string $dateLabel, array $details = []): string
    {
        $merged = new Fpdi('L', 'pt', [612, 936]);
        $merged->SetAutoPageBreak(false);
        $merged->AliasNbPages();
        $temporaryFiles = [];
        $batches = $events->isEmpty() ? collect([collect()]) : $events->chunk(self::BATCH_SIZE);

        try {
            foreach ($batches as $batchIndex => $batch) {
                // A full export's HTML/CSS tree can exceed PHP's memory limit.
                // Release each small renderer before starting the next batch.
                $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
                $pdf->setPaper(self::PAPER_SIZE, 'landscape');
                $pdf->loadHtml(view('pages.transaction_events.recordsPdf', [
                    'events' => $batch->values(), 'isRice' => $isRice, 'dateLabel' => $dateLabel,
                    'rowOffset' => $batchIndex * self::BATCH_SIZE, 'mergedExport' => true, 'details' => $details,
                ])->render());
                $pdf->render();
                $path = tempnam(sys_get_temp_dir(), 'event_pdf_');
                if ($path === false) {
                    throw new RuntimeException('Unable to create PDF export temporary file.');
                }
                $temporaryFiles[] = $path;
                if (file_put_contents($path, $pdf->output()) === false) {
                    throw new RuntimeException('Unable to write PDF export temporary file.');
                }
                unset($pdf);
                gc_collect_cycles();

                $pageCount = $merged->setSourceFile($path);
                for ($page = 1; $page <= $pageCount; $page++) {
                    $template = $merged->importPage($page);
                    $size = $merged->getTemplateSize($template);
                    $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $merged->useTemplate($template);
                    $merged->SetFont('Helvetica', '', 6);
                    $merged->SetXY(30, $size['height'] - 20);
                    $merged->Cell($size['width'] - 60, 8, 'Page '.$merged->PageNo().' of {nb}', 0, 0, 'R');
                }
            }

            return $merged->Output('S');
        } finally {
            // FPDI needs the source files until it finishes writing the output.
            $merged->cleanUp(true);
            foreach ($temporaryFiles as $path) {
                @unlink($path);
            }
        }
    }
}
