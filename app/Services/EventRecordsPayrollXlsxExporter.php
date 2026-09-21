<?php

namespace App\Services;

use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class EventRecordsPayrollXlsxExporter
{
    /**
     * Create a printable payroll workbook and return its temporary file path.
     */
    public function render(Collection $events, bool $isRice, string $dateLabel, array $details): string
    {
        $sheetPath = tempnam(sys_get_temp_dir(), 'payroll_sheet_');
        $zipPath = tempnam(sys_get_temp_dir(), 'payroll_xlsx_');
        if ($sheetPath === false || $zipPath === false) {
            throw new RuntimeException('Unable to create a temporary payroll file.');
        }

        try {
            $sheet = fopen($sheetPath, 'wb');
            if ($sheet === false) {
                throw new RuntimeException('Unable to write the payroll worksheet.');
            }

            try {
                $this->writeSheet($sheet, $events, $isRice, $dateLabel, $details);
            } finally {
                fclose($sheet);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to package the payroll workbook.');
            }

            try {
                $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
                $zip->addFromString('[Content_Types].xml', $this->contentTypes());
                $zip->addFromString('_rels/.rels', $this->packageRelationships());
                $zip->addFromString('xl/workbook.xml', $this->workbook());
                $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
                $zip->addFromString('xl/styles.xml', $this->styles());
            } finally {
                $zip->close();
            }

            return $zipPath;
        } catch (\Throwable $exception) {
            @unlink($zipPath);
            throw $exception;
        } finally {
            @unlink($sheetPath);
        }
    }

    private function writeSheet($sheet, Collection $events, bool $isRice, string $dateLabel, array $details): void
    {
        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols><col min="1" max="1" width="6" customWidth="1"/>'
            .'<col min="2" max="2" width="18" customWidth="1"/>'
            .'<col min="3" max="3" width="30" customWidth="1"/>'
            .'<col min="4" max="4" width="28" customWidth="1"/>'
            .'<col min="5" max="6" width="7" customWidth="1"/>'
            .'<col min="7" max="7" width="19" customWidth="1"/>'
            .'<col min="8" max="8" width="22" customWidth="1"/>'
            .'<col min="9" max="12" width="16" customWidth="1"/></cols><sheetData>');

        $title = $isRice
            ? 'ASSISTANCE TO INDIGENT INDIVIDUALS OR FAMILIES - FOOD ASSISTANCE / RICE DISTRIBUTION PROJECT'
            : 'EVENT RECORDS - ASSISTANCE DISTRIBUTION';
        $selectedTranches = array_map('intval', $details['numbered_tranches'] ?? []);
        $pages = $events->isEmpty() ? collect([collect()]) : $events->chunk(20);
        $rowNumber = 1;
        $recordNumber = 0;
        $merges = [];
        $breaks = [];

        foreach ($pages as $pageIndex => $pageEvents) {
            $titleRow = $rowNumber++;
            $this->row($sheet, $titleRow, [$title], 1, 28);
            $merges[] = 'A'.$titleRow.':L'.$titleRow;

            $dateRow = $rowNumber++;
            $this->row($sheet, $dateRow, ['DATE: '.($dateLabel !== '' ? $dateLabel : '____________________________')], 2, 22);
            $merges[] = 'A'.$dateRow.':L'.$dateRow;

            $this->row($sheet, $rowNumber++, [
                'NO', 'CLIENT NAME', "BENEF'S NAME", 'ADDRESS', 'SEX', 'AGE',
                'CLIENT CATEGORY', 'TYPE OF ASSISTANCE', 'SIGNATURE 1st TRANCHE',
                'SIGNATURE 2nd TRANCHE', 'SIGNATURE 3rd TRANCHE', 'SIGNATURE 4th TRANCHE',
            ], 3, 42);

            foreach ($pageEvents as $event) {
                $number = ++$recordNumber;
                $category = mb_strtoupper(trim((string) $event->transaction_category));
                $this->row($sheet, $rowNumber++, [
                    $number,
                    '',
                    mb_strtoupper((string) $event->display_name),
                    mb_strtoupper((string) ($event->address ?? '')),
                    mb_strtoupper((string) ($event->export_sex ?? '')),
                    $event->age,
                    mb_strtoupper((string) ($event->client_category ?? '')),
                    $category === 'BIGAY BIGAS SA MASA' ? 'FOOD ASSISTANCE' : $category,
                    in_array(1, $selectedTranches, true) ? $number : '',
                    in_array(2, $selectedTranches, true) ? $number : '',
                    in_array(3, $selectedTranches, true) ? $number : '',
                    in_array(4, $selectedTranches, true) ? $number : '',
                ], 4, 28, [1, 5, 6], [9, 10, 11, 12]);
            }

            if ($pageEvents->isEmpty()) {
                $emptyRow = $rowNumber++;
                $this->row($sheet, $emptyRow, ['No records match the selected filters.'], 4, 28);
                $merges[] = 'A'.$emptyRow.':L'.$emptyRow;
            }

            // Keep 20 writable lines in each printable payroll section.
            for ($blank = $pageEvents->count(); $blank < 20; $blank++) {
                if ($pageEvents->isEmpty() && $blank === 0) {
                    continue;
                }
                $this->row($sheet, $rowNumber++, array_fill(0, 12, ''), 4, 28);
            }

            $signoffLabelRow = $rowNumber++;
            $this->row($sheet, $signoffLabelRow, [
                'Prepared by:', '', '', '', 'Reviewed by:', '', '', '', 'Approved by:',
            ], 6, 20);
            $signoffNameRow = $rowNumber++;
            $this->row($sheet, $signoffNameRow, [
                ($details['prepared_by'] ?? '') ?: ($isRice ? 'LONIZA B. ESGUERRA' : '________________________'),
                '', '', '',
                ($details['reviewed_by'] ?? '') ?: ($isRice ? 'JOSEPHINE G. VILLANUEVA' : '________________________'),
                '', '', '',
                ($details['approved_by'] ?? '') ?: ($isRice ? 'ALEX L. ADVINCULA' : '________________________'),
            ], 7, 20);

            foreach ([$signoffLabelRow, $signoffNameRow] as $signoffRow) {
                $merges[] = 'A'.$signoffRow.':D'.$signoffRow;
                $merges[] = 'E'.$signoffRow.':H'.$signoffRow;
                $merges[] = 'I'.$signoffRow.':L'.$signoffRow;
            }

            if ($pageIndex < $pages->count() - 1) {
                $breaks[] = $signoffNameRow;
            }
        }

        fwrite($sheet, '</sheetData><mergeCells count="'.count($merges).'">');
        foreach ($merges as $range) {
            fwrite($sheet, '<mergeCell ref="'.$range.'"/>');
        }
        fwrite($sheet, '</mergeCells>'
            .'<printOptions horizontalCentered="1"/>'
            .'<pageMargins left="0.25" right="0.25" top="0.1" bottom="0" header="0" footer="0"/>'
            .'<pageSetup paperSize="14" orientation="landscape" fitToWidth="1" fitToHeight="'.$pages->count().'"/>');
        if ($breaks !== []) {
            fwrite($sheet, '<rowBreaks count="'.count($breaks).'" manualBreakCount="'.count($breaks).'">');
            foreach ($breaks as $breakRow) {
                fwrite($sheet, '<brk id="'.$breakRow.'" min="0" max="16383" man="1"/>');
            }
            fwrite($sheet, '</rowBreaks>');
        }
        fwrite($sheet, '</worksheet>');
    }

    private function row(
        $sheet,
        int $rowNumber,
        array $values,
        int $style,
        int $height,
        array $centerColumns = [],
        array $smallLeftColumns = []
    ): void
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $column = $index + 1;
            $reference = chr(64 + $column).$rowNumber;
            $cellStyle = $style === 4 && in_array($column, $smallLeftColumns, true) ? 8
                : ($style === 4 && in_array($column, $centerColumns, true) ? 5 : $style);
            if ($value === null || $value === '') {
                $cells .= '<c r="'.$reference.'" s="'.$cellStyle.'"/>';
            } elseif (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$reference.'" s="'.$cellStyle.'" t="n"><v>'.$value.'</v></c>';
            } else {
                $cells .= '<c r="'.$reference.'" s="'.$cellStyle.'" t="inlineStr"><is><t xml:space="preserve">'
                    .$this->escape((string) $value).'</t></is></c>';
            }
        }

        fwrite($sheet, '<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Payroll" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4"><font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><name val="Calibri"/></font>'
            .'<font><sz val="7"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="9">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
