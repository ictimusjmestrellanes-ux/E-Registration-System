<?php

namespace App\Services;

use App\Models\TransactionEvent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class EventRecordsXlsxProcess
{
    /** Keep every request comfortably below common 60-second proxy limits. */
    public const BATCH_SIZE = 1000;

    private const HEADERS = [
        'ID', 'Transaction ID', 'Full Name', 'Age', 'Birth Date', 'Contact No.',
        'Address', 'Client Category', 'Transaction Category', 'Transaction Type',
        'Event Date', 'Transferred At', 'Status',
    ];

    private const WIDTHS = [8, 22, 28, 8, 14, 16, 35, 20, 24, 24, 14, 20, 12];

    private function directory(string $token): string
    {
        abort_unless(Str::isUuid($token), 404);

        return storage_path('app/private/event-xlsx-exports/'.$token);
    }

    /**
     * Create a short-lived snapshot of the ordered record IDs. Subsequent
     * requests write one bounded batch at a time so a reverse proxy cannot
     * time out one long-running response.
     *
     * @param  array<int, int|string>  $eventIds
     */
    public function start(array $eventIds, int $owner): array
    {
        $root = storage_path('app/private/event-xlsx-exports');
        File::ensureDirectoryExists($root);
        foreach (File::directories($root) as $directory) {
            if (Str::isUuid(basename($directory)) && File::lastModified($directory) < now()->subHours(2)->timestamp) {
                File::deleteDirectory($directory);
            }
        }

        $token = (string) Str::uuid();
        $directory = $this->directory($token);
        File::ensureDirectoryExists($directory);

        $state = [
            'owner' => $owner,
            'expires' => now()->addHours(2)->timestamp,
            'total' => count($eventIds),
            'completed' => 0,
            'rows' => 0,
            'ready' => false,
        ];

        File::put($directory.'/ids.json', json_encode(array_values($eventIds), JSON_THROW_ON_ERROR));
        File::put($directory.'/sheet.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$this->columnsXml(self::WIDTHS).'<sheetData>'
            .$this->rowXml(1, self::HEADERS, true));
        File::put($directory.'/state.json', json_encode($state, JSON_THROW_ON_ERROR));

        return ['token' => $token] + $this->progress($state);
    }

    public function step(string $token, int $owner): array
    {
        $directory = $this->directory($token);
        $this->state($directory, $owner);
        $lock = fopen($directory.'/process.lock', 'c');
        abort_if($lock === false, 500, 'Unable to lock the XLSX export.');

        try {
            abort_unless(flock($lock, LOCK_EX | LOCK_NB), 409, 'Export is still processing.');
            $state = $this->state($directory, $owner);
            if ($state['ready']) {
                return $this->progress($state);
            }

            if ($state['completed'] < $state['total']) {
                $ids = json_decode(File::get($directory.'/ids.json'), true, 512, JSON_THROW_ON_ERROR);
                $batchIds = array_slice($ids, $state['completed'], self::BATCH_SIZE);
                $events = TransactionEvent::query()
                    ->with('transferredTransaction:id,transaction_id')
                    ->whereIn('id', $batchIds)
                    ->get([
                        'id', 'full_name', 'age', 'birth_date', 'contact_no', 'address',
                        'client_category', 'transaction_category', 'transaction_type',
                        'event_date', 'transferred_at', 'transferred_transaction_id', 'status',
                    ])
                    ->keyBy('id');

                $xml = '';
                foreach ($batchIds as $id) {
                    $event = $events->get($id);
                    if (! $event) {
                        continue;
                    }

                    $state['rows']++;
                    $xml .= $this->rowXml($state['rows'] + 1, [
                        $event->id,
                        $event->transferredTransaction?->transaction_id ?? '',
                        $event->full_name ?? '',
                        $event->age ?? '',
                        $event->birth_date?->format('Y-m-d') ?? '',
                        $event->contact_no ?? '',
                        $event->address ?? '',
                        $event->client_category ?? '',
                        $event->transaction_category ?? '',
                        $event->transaction_type ?? '',
                        $event->event_date?->format('Y-m-d') ?? '',
                        $event->transferred_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? '',
                        $event->status,
                    ], false);
                }

                File::append($directory.'/sheet.xml', $xml);
                $state['completed'] += count($batchIds);
            }

            if ($state['completed'] >= $state['total']) {
                File::append($directory.'/sheet.xml', '</sheetData></worksheet>');
                $this->package($directory);
                $state['ready'] = true;
                app(ActivityLogger::class)->record(
                    'event_records_xlsx_exported',
                    "Exported {$state['total']} Event Record(s) to XLSX.",
                    ['record_count' => $state['total']]
                );
                File::delete($directory.'/ids.json');
                File::delete($directory.'/sheet.xml');
            }

            File::replace($directory.'/state.json', json_encode($state, JSON_THROW_ON_ERROR));

            return $this->progress($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function downloadPath(string $token, int $owner): string
    {
        $directory = $this->directory($token);
        $state = $this->state($directory, $owner);
        abort_unless($state['ready'] && File::exists($directory.'/export.xlsx'), 409, 'The XLSX file is not ready yet.');

        return $directory.'/export.xlsx';
    }

    private function state(string $directory, int $owner): array
    {
        abort_unless(File::exists($directory.'/state.json'), 404);
        $state = json_decode(File::get($directory.'/state.json'), true, 512, JSON_THROW_ON_ERROR);
        abort_unless((int) $state['owner'] === $owner, 404);
        abort_if($state['expires'] < time(), 410, 'This export expired. Start a new XLSX export.');

        return $state;
    }

    private function progress(array $state): array
    {
        return array_intersect_key($state, array_flip(['total', 'completed', 'ready']));
    }

    private function package(string $directory): void
    {
        $zip = new ZipArchive;
        abort_unless($zip->open($directory.'/export.xlsx', ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Unable to package the XLSX export.');

        $zip->addFile($directory.'/sheet.xml', 'xl/worksheets/sheet1.xml');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Event Records" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        abort_unless($zip->close(), 500, 'Unable to finish the XLSX export.');
    }

    private function columnsXml(array $widths): string
    {
        $columns = [];
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $columns[] = '<col min="'.$column.'" max="'.$column.'" width="'.number_format((float) $width, 2, '.', '').'" customWidth="1"/>';
        }

        return '<cols>'.implode('', $columns).'</cols>';
    }

    private function rowXml(int $rowNumber, array $values, bool $header): string
    {
        $cells = [];
        foreach (array_values($values) as $index => $value) {
            $reference = $this->cellReference($index + 1).$rowNumber;
            $stringValue = $value === null ? null : (string) $value;
            if ($stringValue === null || $stringValue === '') {
                $cells[] = '<c r="'.$reference.'"/>';
            } elseif ($header || ! is_numeric($stringValue) || ($index === 5 && str_starts_with($stringValue, '0'))) {
                $cells[] = '<c r="'.$reference.'"'.($header ? ' s="1"' : '').' t="inlineStr"><is><t>'.$this->escape($stringValue).'</t></is></c>';
            } else {
                $cells[] = '<c r="'.$reference.'" t="n"><v>'.$stringValue.'</v></c>';
            }
        }

        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function cellReference(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $letters = chr(65 + (($column - 1) % 26)).$letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
