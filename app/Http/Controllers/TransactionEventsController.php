<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ImportArchiveFile;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class TransactionEventsController extends Controller
{
    /**
     * In-memory cache of the current transaction-id sequence per client-year.
     * Avoids re-querying the DB for every row during bulk import/transfer,
     * which is a major speedup when processing tens of thousands of rows.
     *
     * @var array<string, int>
     */
    private array $transactionSequenceCache = [];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = TransactionEvent::whereNull('transferred_at')
            ->where('not_duplicate', false);

        $this->applyEventListFilters($query, $request);

        $duplicateFullNames = $this->duplicateFullNamesList();

        if ($request->boolean('duplicate_names')) {
            $query->whereIn('full_name', $duplicateFullNames);
        }

        if ($request->boolean('duplicate_names')) {
            $query->orderBy('full_name')->orderBy('id', 'desc');
        } else {
            $query->orderByDesc('id');
        }

        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $events = $query->paginate($perPage)->withQueryString();

        // Distinct values (from pending events) for the dropdown filters.
        $pendingBase = TransactionEvent::whereNull('transferred_at')->where('not_duplicate', false);
        $clientCategories = (clone $pendingBase)->select('client_category')->distinct()
            ->pluck('client_category')->filter()->sort()->values();
        $transactionCategories = (clone $pendingBase)->select('transaction_category')->distinct()
            ->pluck('transaction_category')->filter()->sort()->values();
        $transactionTypes = (clone $pendingBase)->select('transaction_type')->distinct()
            ->pluck('transaction_type')->filter()->sort()->values();

        // Scope to the pending list so the duplicate-names filter count
        // matches what the filtered Event List can actually show.
        $totalDuplicateGroups = TransactionEvent::query()
            ->whereNull('transferred_at')
            ->selectRaw('LOWER(TRIM(full_name)) as keyval')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return view('pages.transaction_events.transactionEvents',
            compact('events', 'totalDuplicateGroups', 'duplicateFullNames', 'clientCategories', 'transactionCategories', 'transactionTypes'));
    }

    /**
     * Export pending events to XLSX
     */
    public function exportEvents(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $query = TransactionEvent::whereNull('transferred_at')->where('not_duplicate', false);
        $this->applyEventListFilters($query, $request);

        $events = $query->orderByDesc('id')->get([
            'id', 'full_name', 'age', 'birth_date', 'contact_no', 'address',
            'client_category', 'transaction_category', 'transaction_type',
            'event_date', 'created_at',
        ]);

        $headers = [
            'ID', 'Full Name', 'Age', 'Birth Date', 'Contact No.', 'Address',
            'Client Category', 'Transaction Category', 'Transaction Type',
            'Event Date', 'Created At',
        ];
        $widths = [8, 28, 8, 14, 16, 35, 20, 24, 24, 14, 20];

        // Write the worksheet incrementally so large exports stay lean.
        $sheetPath = tempnam(sys_get_temp_dir(), 'events_sheet_').'.xml';
        $sheet = fopen($sheetPath, 'w');

        if ($sheet === false) {
            return back()->with('error', 'Unable to generate the Excel file. Please try again.');
        }

        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$this->xlsxColsXml($widths).'<sheetData>');

        $rowNumber = 1;
        $this->fwriteXlsxRow($sheet, $rowNumber++, $headers, true);

        foreach ($events as $event) {
            $this->fwriteXlsxRow($sheet, $rowNumber++, [
                $event->id,
                $event->full_name ?? '',
                $event->age ?? '',
                $event->birth_date?->format('Y-m-d') ?? '',
                $event->contact_no ?? '',
                $event->address ?? '',
                $event->client_category ?? '',
                $event->transaction_category ?? '',
                $event->transaction_type ?? '',
                $event->event_date?->format('Y-m-d') ?? '',
                $event->created_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? '',
            ], false);
        }

        fwrite($sheet, '</sheetData></worksheet>');
        fclose($sheet);

        $zipPath = tempnam(sys_get_temp_dir(), 'events_export_').'.xlsx';
        $zip = new \ZipArchive;

        if (!$zip->open($zipPath, \ZipArchive::CREATE)) {
            @unlink($sheetPath);
            return back()->with('error', 'Unable to create Excel file. Please try again.');
        }

        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
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
            .'<sheets><sheet name="Transaction Events" sheetId="1" r:id="rId1"/></sheets>'
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
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        $zip->close();

        @unlink($sheetPath);

        return response()->download($zipPath, 'transaction-events-' . now()->format('YmdHis') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Normalize a list filter that may arrive as a single value, a repeated
     * ?key[]=… param, or a comma-separated list. Always returns the full
     * set — a basic where() would silently keep only the first element.
     *
     * @return string[]
     */
    private function multiFilterValues(Request $request, string $key): array
    {
        $raw = $request->input($key);
        $values = is_array($raw) ? $raw : [$raw];

        $clean = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $clean[] = $part;
                }
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Shared list filters (search, contact, age range, date range) so the
     * bulk "select all across pages" transfer targets exactly what is shown.
     */
    private function applyEventListFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        if ($contact = $request->input('contact')) {
            $query->where('contact_no', 'like', "%{$contact}%");
        }

        if ($ageFrom = $request->input('age_from')) {
            $query->where('age', '>=', (int) $ageFrom);
        }

        if ($ageTo = $request->input('age_to')) {
            $query->where('age', '<=', (int) $ageTo);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($clientCategories = $this->multiFilterValues($request, 'client_category')) {
            $query->whereIn('client_category', $clientCategories);
        }

        if ($txCategory = $request->input('transaction_category')) {
            $query->where('transaction_category', $txCategory);
        }

        if ($txTypes = $this->multiFilterValues($request, 'transaction_type')) {
            $query->whereIn('transaction_type', $txTypes);
        }
    }

    private function duplicateFullNamesList(): array
    {
        // Scope to the pending list population so already-transferred records
        // do not flag remaining rows as duplicates.
        return TransactionEvent::query()
            ->select('full_name')
            ->whereNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('full_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('full_name')
            ->all();
    }

    /**
     * Filters for the Event Records (transferred events) list. Shared by the
     * records() listing and the bulk-undo ID resolution so "select all pages"
     * targets exactly the rows the filters show.
     */
    private function applyRecordFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        if ($contact = $request->input('contact')) {
            $query->where('contact_no', 'like', "%{$contact}%");
        }

        if ($ageFrom = $request->input('age_from')) {
            $query->where('age', '>=', (int) $ageFrom);
        }

        if ($ageTo = $request->input('age_to')) {
            $query->where('age', '<=', (int) $ageTo);
        }

        // Date range applies to when the record was transferred.
        if ($from = $request->input('date_from')) {
            $query->whereDate('transferred_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('transferred_at', '<=', $to);
        }

        if ($category = $request->input('transaction_category')) {
            $query->where('transaction_category', $category);
        }

        // Handle both single value and multiple values for transaction_type
        $types = $request->input('transaction_type') ?? $request->input('transaction_type[]');
        if ($types) {
            if (is_array($types)) {
                // Filter out empty values
                $types = array_filter($types);
                if (!empty($types)) {
                    $query->whereIn('transaction_type', $types);
                }
            } else {
                // Single value (backward compatibility)
                $query->where('transaction_type', $types);
            }
        }

        if ($clientCategories = $this->multiFilterValues($request, 'client_category')) {
            $query->whereIn('client_category', $clientCategories);
        }
    }

    /**
     * Resolve every transferred event id matching the current Event Records
     * filters (for cross-page bulk undo).
     *
     * @return int[]
     */
    private function resolveUndoSelectedIds(Request $request): array
    {
        $query = TransactionEvent::query()->whereNotNull('transferred_at');

        $this->applyRecordFilters($query, $request);

        return $query->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function records(Request $request)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }

        $query = TransactionEvent::whereNotNull('transferred_at');

        $this->applyRecordFilters($query, $request);

        // Allow sortable columns via query params: sort_by, sort_dir
        $allowedSorts = [
            'id' => 'id',
            'transaction_id' => 'transferred_transaction_id',
            'full_name' => 'full_name',
            'age' => 'age',
            'birth_date' => 'birth_date',
            'contact' => 'contact_no',
            'address' => 'address',
            'client_category' => 'client_category',
            'transaction_category' => 'transaction_category',
            'transaction_type' => 'transaction_type',
            'event_date' => 'event_date',
            'transferred_at' => 'transferred_at',
        ];

        $sortBy = $request->input('sort_by');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = $allowedSorts[$sortBy] ?? null;

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        if ($sortColumn) {
            $query = $query->orderBy($sortColumn, $sortDir);
        } else {
            $query = $query->orderByDesc('id');
        }

        $events = $query->with('transferredTransaction:id,transaction_id')
            ->paginate($perPage)
            ->withQueryString();

        // Distinct values (from transferred records) for the dropdown filters.
        $categories = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_category')->distinct()
            ->pluck('transaction_category')->filter()->sort()->values();
        $types = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_type')->distinct()
            ->pluck('transaction_type')->filter()->sort()->values();
        $clientCategories = TransactionEvent::whereNotNull('transferred_at')
            ->select('client_category')->distinct()
            ->pluck('client_category')->filter()->sort()->values();

        $typeClientCategories = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_type', 'client_category')
            ->whereNotNull('transaction_type')
            ->whereNotNull('client_category')
            ->where('transaction_type', '<>', '')
            ->where('client_category', '<>', '')
            ->distinct()
            ->get()
            ->groupBy('transaction_type')
            ->map(fn ($items) => $items->pluck('client_category')->filter()->unique()->sort()->values()->all())
            ->all();

        return view('pages.transaction_events.eventRecords', compact('events', 'categories', 'types', 'clientCategories', 'typeClientCategories'));
    }

    /**
     * Export the currently filtered Event Records to .xlsx (same filters as
     * the listing; all matching rows across pages, not just the current one).
     */
    public function exportRecords(Request $request)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $query = TransactionEvent::whereNotNull('transferred_at');
        $this->applyRecordFilters($query, $request);

        // Export should always be alphabetical by client name across all pages.
        $events = $query->with('transferredTransaction:id,transaction_id')
            ->orderByRaw('LOWER(full_name)')
            ->orderBy('id')
            ->get([
                'id', 'full_name', 'age', 'birth_date', 'contact_no', 'address',
                'client_category', 'transaction_category', 'transaction_type',
                'event_date', 'transferred_at', 'transferred_transaction_id',
            ]);

        // Resolve each event's client through its linked transaction history
        // (events carry no client_id themselves).
        $histories = TransactionHistory::whereIn(
            'id',
            $events->pluck('transferred_transaction_id')->filter()->unique()->values()->all()
        )->get(['id', 'client_id'])->keyBy('id');

        $clients = Client::whereIn('client_id', $histories->pluck('client_id')->filter()->unique()->values()->all())
            ->get(['client_id', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->keyBy('client_id');

        $headers = [
            'ID', 'Transaction ID', 'Client Name', 'Age', 'Birth Date', 'Contact No.',
            'Address', 'Client Category', 'Transaction Category', 'Transaction Type',
            'Event Date', 'Transferred At', 'Status',
        ];
        $widths = [8, 22, 28, 8, 14, 16, 35, 20, 24, 24, 14, 20, 12];

        // Write the worksheet incrementally so large exports stay lean.
        $sheetPath = tempnam(sys_get_temp_dir(), 'records_sheet_').'.xml';
        $sheet = fopen($sheetPath, 'w');

        if ($sheet === false) {
            return back()->with('error', 'Unable to generate the Excel file. Please try again.');
        }

        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$this->xlsxColsXml($widths).'<sheetData>');

        $rowNumber = 1;
        $this->fwriteXlsxRow($sheet, $rowNumber++, $headers, true);

        foreach ($events as $event) {
            $history = $event->transferred_transaction_id
                ? $histories->get($event->transferred_transaction_id)
                : null;
            $client = $history ? $clients->get($history->client_id) : null;
            $this->fwriteXlsxRow($sheet, $rowNumber++, [
                $event->id,
                $event->transferredTransaction?->transaction_id ?? '',
                $client ? $client->full_name : ($event->full_name ?? ''),
                $event->age ?? '',
                $event->birth_date?->format('Y-m-d') ?? '',
                $event->contact_no ?? '',
                $event->address ?? '',
                $event->client_category ?? '',
                $event->transaction_category ?? '',
                $event->transaction_type ?? '',
                $event->event_date?->format('Y-m-d') ?? '',
                $event->transferred_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? '',
                'Approved',
            ], false);
        }

        fwrite($sheet, '</sheetData></worksheet>');
        fclose($sheet);

        $zipPath = tempnam(sys_get_temp_dir(), 'records_export_').'.xlsx';
        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($sheetPath);

            return back()->with('error', 'Unable to generate the Excel file. Please try again.');
        }

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
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        $zip->close();

        @unlink($sheetPath);

        return response()->download($zipPath, 'event-records_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function xlsxColsXml(array $widths): string
    {
        $cols = [];

        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $cols[] = '<col min="'.$column.'" max="'.$column.'" width="'.number_format((float) $width, 2, '.', '').'" customWidth="1"/>';
        }

        return $cols === [] ? '' : '<cols>'.implode('', $cols).'</cols>';
    }

    /**
     * Write a single XML row for an xlsx worksheet.
     */
    private function fwriteXlsxRow($sheet, int $rowNumber, array $values, bool $header): void
    {
        $cells = [];
        $column = 1;

        foreach ($values as $value) {
            $cellReference = $this->xlsxCellReference($column).$rowNumber;
            $stringValue = $value === null ? null : (string) $value;

            if ($value === null || $value === '') {
                $cells[] = '<c r="'.$cellReference.'"/>';
                $column++;
                continue;
            }

            if ($header || ! is_numeric($stringValue)) {
                $cells[] = '<c r="'.$cellReference.'"'.($header ? ' s="1"' : '').' t="inlineStr"><is><t>'.$this->xlsxXmlEscape($stringValue).'</t></is></c>';
                $column++;
                continue;
            }

            $cells[] = '<c r="'.$cellReference.'" t="n"><v>'.$stringValue.'</v></c>';
            $column++;
        }

        fwrite($sheet, '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>');
    }

    private function xlsxCellReference(int $column): string
    {
        $letters = '';

        while ($column > 0) {
            $columnIndex = ($column - 1) % 26;
            $letters = chr(65 + $columnIndex).$letters;
            $column = (int) (($column - 1) / 26);
        }

        return $letters;
    }

    private function xlsxXmlEscape(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            "'" => '&apos;',
        ]);
    }
}
