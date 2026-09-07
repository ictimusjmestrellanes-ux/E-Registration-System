<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ImportArchiveFile;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\TransactionRequirement;
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

    /**
     * Parse an uploaded CSV or .xlsx file into a row matrix.
     *
     * @return array<int, array<int, string>>
     */
    private function parseImportFile(
        \Illuminate\Http\UploadedFile|string $file,
        ?string $filename = null,
    ): array {
        $path = $file instanceof \Illuminate\Http\UploadedFile ? $file->getRealPath() : $file;
        $name = strtolower((string) ($filename ?: ($file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : basename((string) $path))));

        if ($name === '' || str_ends_with($name, '.xlsx')) {
            return $this->parseXlsxImportFile($path);
        }

        if ($path === null || ! is_readable($path)) {
            return [];
        }

        $rows = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn ($cell) => is_string($cell) ? trim($cell) : (string) $cell, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse a minimal .xlsx worksheet into a row matrix.
     *
     * @return array<int, array<int, string>>
     */
    private function parseXlsxImportFile(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared !== false) {
                foreach ($shared->si as $si) {
                    $text = '';
                    foreach ($si->t as $node) {
                        $text .= (string) $node;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetPath = null;
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml !== false) {
            $rels = simplexml_load_string($relsXml);
            if ($rels !== false) {
                foreach ($rels->Relationship as $relationship) {
                    $target = (string) $relationship->attributes()->Target;
                    if (str_contains((string) $relationship->attributes()->Type, 'worksheet')) {
                        $sheetPath = 'xl/' . ltrim((string) $target, '/');
                        break;
                    }
                }
            }
        }

        if ($sheetPath === null) {
            $sheetPath = 'xl/worksheets/sheet1.xml';
        }

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return [];
        }

        $rows = [];
        $ns = $sheet->getNamespaces(true);
        $sheetData = $sheet->children($ns['main'] ?? null)->sheetData ?? null;
        if ($sheetData === null) {
            return [];
        }

        foreach ($sheetData->row as $rowNode) {
            $values = [];
            foreach ($rowNode->c as $cell) {
                $cellType = (string) ($cell->attributes()->t ?: 'n');
                $value = '';
                if ($cellType === 'inlineStr') {
                    $value = (string) $cell->is->t;
                } elseif ($cellType === 's') {
                    $index = (int) $cell->v;
                    $value = $sharedStrings[$index] ?? '';
                } else {
                    $value = (string) $cell->v;
                }
                $values[] = trim((string) $value);
            }
            if ($values !== []) {
                $rows[] = $values;
            }
        }

        return $rows;
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        $header = trim($header, '_');

        return $header;
    }

    private function importRowsToRecords(array $rows): array
    {
        if ($rows === []) {
            return ['rows' => [], 'skipped' => 0];
        }

        $header = array_map([$this, 'normalizeImportHeader'], $rows[0]);
        $records = [];
        $skipped = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            $mapped = [];
            foreach ($header as $index => $key) {
                $mapped[$key] = $row[$index] ?? '';
            }

            if (($mapped['full_name'] ?? '') === '') {
                $skipped++;
                continue;
            }

            if (isset($mapped['age']) && $mapped['age'] !== '') {
                $age = (int) $mapped['age'];
                if ($age < 0 || $age > 120) {
                    $skipped++;
                    continue;
                }
            }

            if (($mapped['event_date'] ?? '') !== '') {
                try {
                    \Carbon\Carbon::parse($mapped['event_date']);
                } catch (\Throwable) {
                    $skipped++;
                    continue;
                }
            }

            $records[] = $mapped;
        }

        return ['rows' => $records, 'skipped' => $skipped];
    }

    private function importSessionKey(string $token): string
    {
        return 'transaction_events_import_' . $token;
    }

    private function ensureImportSessionToken(): string
    {
        return (string) (session()->get('transaction_events_import_token') ?: bin2hex(random_bytes(16)));
    }

    /**
     * Check the uploaded file for rows that already exist in the system.
     */
    public function importDuplicatesCheck(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $file = $request->file('csv_file');
        $rows = $this->parseImportFile($file, $file->getClientOriginalName());
        $parsed = $this->importRowsToRecords($rows);
        $records = $parsed['rows'];

        $existing = TransactionHistory::query()
            ->select(['transaction_date', 'category', 'type', 'events_transaction_type'])
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->transaction_date?->format('Y-m-d') ?? '',
                    'category' => trim((string) ($row->category ?? '')),
                    'type' => trim((string) ($row->type ?? '')),
                    'event_type' => trim((string) ($row->events_transaction_type ?? '')),
                ];
            })
            ->all();

        $duplicates = [];
        $seen = [];

        foreach ($records as $record) {
            $fullName = trim((string) ($record['full_name'] ?? ''));
            $eventDate = trim((string) ($record['event_date'] ?? ''));
            $category = trim((string) ($record['transaction_category'] ?? ''));
            $transactionType = trim((string) ($record['transaction_type'] ?? ''));
            $key = strtolower($fullName) . '|' . $eventDate . '|' . strtolower($category) . '|' . strtolower($transactionType);

            if (isset($seen[$key])) {
                $duplicates[] = [
                    'full_name' => $fullName,
                    'event_date' => $eventDate,
                    'transaction_category' => $category,
                    'transaction_type' => $transactionType,
                ];
                continue;
            }

            $seen[$key] = true;

            $match = collect($existing)->contains(function ($item) use ($category, $transactionType, $eventDate) {
                return ($item['category'] === $category || $item['event_type'] === $transactionType)
                    && ($item['type'] === $transactionType || $item['event_type'] === $transactionType)
                    && ($item['date'] === $eventDate || $eventDate === '');
            });

            if ($match) {
                $duplicates[] = [
                    'full_name' => $fullName,
                    'event_date' => $eventDate,
                    'transaction_category' => $category,
                    'transaction_type' => $transactionType,
                ];
            }
        }

        $duplicates = array_slice($duplicates, 0, 100);

        return response()->json([
            'success' => true,
            'total_rows' => count($records),
            'duplicates_count' => count($duplicates),
            'duplicates' => $duplicates,
            'duplicates_truncated' => count($duplicates) >= 100,
        ]);
    }

    /**
     * Prepare a large import by streaming/parsing the uploaded file and storing the
     * rows in session for chunk processing.
     */
    public function prepareImport(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $file = $request->file('csv_file');
        $rows = $this->parseImportFile($file, $file->getClientOriginalName());
        $parsed = $this->importRowsToRecords($rows);
        $token = bin2hex(random_bytes(16));

        session()->put($this->importSessionKey($token), [
            'rows' => $parsed['rows'],
            'total' => count($parsed['rows']),
            'skipped' => $parsed['skipped'],
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'total' => count($parsed['rows']),
            'skipped' => $parsed['skipped'],
        ]);
    }

    /**
     * Process a prepared import in chunks.
     */
    public function processImportChunk(Request $request)
    {
        $token = $request->input('token');
        if (! is_string($token) || $token === '') {
            abort(404, 'Import session not found.');
        }

        $payload = session()->get($this->importSessionKey($token));
        if (! is_array($payload)) {
            abort(404, 'Import session not found.');
        }

        $request->validate([
            'token' => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1'],
        ]);

        $offset = (int) $request->input('offset');
        $limit = (int) $request->input('limit');
        $total = (int) ($payload['total'] ?? 0);
        $processed = min($offset + $limit, $total);

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'total' => $total,
            'done' => $processed >= $total,
        ]);
    }

    /**
     * Finalize a prepared import by inserting the rows into clients/history.
     */
    public function finishImport(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = $request->input('token');
        $payload = session()->get($this->importSessionKey($token));
        if (! is_array($payload)) {
            abort(404, 'Import session not found.');
        }

        $rows = $payload['rows'] ?? [];
        $imported = 0;
        $skipped = (int) ($payload['skipped'] ?? 0);

        foreach ($rows as $record) {
            $this->createTransactionHistoryFromImportRow($record);
            $imported++;
        }

        session()->forget($this->importSessionKey($token));
        session()->flash('success', 'Successfully imported ' . $imported . ' event(s).' . ($skipped > 0 ? ' Skipped ' . $skipped . ' invalid row(s).' : ''));

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
        ])->setStatusCode(200);
    }

    /**
     * Direct import endpoint used by the standard form submit.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $file = $request->file('csv_file');
        $rows = $this->parseImportFile($file, $file->getClientOriginalName());
        $parsed = $this->importRowsToRecords($rows);

        $imported = 0;
        foreach ($parsed['rows'] as $record) {
            $this->createTransactionHistoryFromImportRow($record);
            $imported++;
        }

        $message = 'Successfully imported ' . $imported . ' event(s).';
        if ($parsed['skipped'] > 0) {
            $message .= ' Skipped ' . $parsed['skipped'] . ' invalid row(s).';
        }

        $this->archiveImportedFile($file, $parsed['rows']);

        return redirect()->route('transaction-events.index')->with('success', $message);
    }

    private function archiveImportedFile(\Illuminate\Http\UploadedFile $file, array $rows = []): void
    {
        $originalName = $file->getClientOriginalName() ?: 'transaction-events.csv';
        $timestamp = now()->format('Ymd_His');
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $originalName) ?: 'transaction-events.csv';
        $destination = 'transaction-events-archive/transaction-events_' . $timestamp . '_' . $safeName;

        $file->storeAs('transaction-events-archive', 'transaction-events_' . $timestamp . '_' . $safeName, 'local');

        ImportArchiveFile::create([
            'filename' => basename($destination),
            'original_filename' => $originalName,
            'rows_count' => count($rows),
            'file_size' => $file->getSize() ?? 0,
            'source' => 'manual_import',
            'imported_by_id' => auth()->id(),
            'imported_by' => auth()->user()?->name ?? '',
            'role' => auth()->user()?->role_name ?? '',
            'imported_at' => now(),
        ]);
    }

    private function createTransactionHistoryFromImportRow(array $record): void
    {
        $fullName = trim((string) ($record['full_name'] ?? ''));
        $birthDate = trim((string) ($record['birth_date'] ?? $record['birthdate'] ?? ''));
        $client = $this->findOrCreateClientForEvent($fullName, $record, $birthDate);

        $transactionHistory = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => trim((string) ($record['client_category'] ?? '')),
            'transaction_id' => $this->nextTransactionIdForClient($client->client_id),
            'transaction_date' => ! empty($record['event_date']) ? $record['event_date'] : now()->toDateString(),
            'category' => trim((string) ($record['transaction_category'] ?? '')),
            'type' => trim((string) ($record['transaction_type'] ?? '')),
            'events_transaction_type' => trim((string) ($record['transaction_type'] ?? '')),
            'status' => 'Approved',
            'source' => 'import',
            'description' => 'Imported from event CSV/XLSX file.',
        ]);

        $transactionHistory->touch();
    }

    private function findOrCreateClientForEvent(string $fullName, array $record, ?string $birthDate = null): Client
    {
        $trimmed = trim($fullName);
        if ($trimmed === '') {
            throw new \RuntimeException('Client full name is required.');
        }

        $parts = preg_split('/\s+/', $trimmed);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[count($parts) - 1] ?? '';
        $middleNames = array_slice($parts, 1, -1);
        $middleName = implode(' ', $middleNames);
        $suffix = '';
        if (preg_match('/^(.*)\s+(JR|SR|II|III|IV|V)$/i', $trimmed, $matches)) {
            $suffix = strtoupper($matches[2]);
            $trimmed = trim($matches[1]);
        }

        $normalizedTrimmed = strtolower(preg_replace('/\s+/', ' ', trim($trimmed)));
        $candidateNames = array_values(array_unique(array_filter([
            $normalizedTrimmed,
            strtolower(trim($firstName . ' ' . $lastName)),
            strtolower(trim($firstName . ' ' . $middleName . ' ' . $lastName)),
            strtolower(trim($firstName . ' ' . $middleName)),
            strtolower(trim($middleName . ' ' . $lastName)),
        ], fn ($value) => $value !== '')));

        $query = Client::query()->where(function ($sub) use ($candidateNames, $firstName, $trimmed, $birthDate) {
            if ($birthDate !== null && $birthDate !== '') {
                $sub->whereDate('birth_date', $birthDate);
            }

            $sub->where(function ($nameQuery) use ($candidateNames, $trimmed) {
                foreach ($candidateNames as $candidate) {
                    $nameQuery->orWhereRaw('LOWER(TRIM(COALESCE(first_name, "") || " " || COALESCE(middle_name, "") || " " || COALESCE(last_name, ""))) = ?', [$candidate]);
                    $nameQuery->orWhereRaw('LOWER(TRIM(COALESCE(first_name, "") || " " || COALESCE(last_name, ""))) = ?', [$candidate]);
                }

                $nameQuery->orWhereRaw('LOWER(TRIM(COALESCE(first_name, "") || " " || COALESCE(middle_name, "") || " " || COALESCE(last_name, ""))) = ?', [strtolower($trimmed)]);
                $nameQuery->orWhereRaw('LOWER(TRIM(COALESCE(first_name, "") || " " || COALESCE(last_name, ""))) = ?', [strtolower($trimmed)]);
            });

            if ($firstName !== '') {
                $sub->orWhere(function ($firstQuery) use ($firstName, $birthDate) {
                    $firstQuery->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower($firstName)]);
                    if ($birthDate !== null && $birthDate !== '') {
                        $firstQuery->whereDate('birth_date', $birthDate);
                    }
                });
            }
        });

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        $clientData = [
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName,
            'suffix' => $suffix !== '' ? $suffix : null,
            'birth_date' => $birthDate !== null && $birthDate !== '' ? $birthDate : null,
            'sector' => trim((string) ($record['client_category'] ?? '')),
            'contact' => trim((string) ($record['contact_no'] ?? '')),
            'address' => trim((string) ($record['address'] ?? '')),
            'age' => isset($record['age']) && $record['age'] !== '' ? (int) $record['age'] : null,
        ];

        return Client::createWithGeneratedId($clientData);
    }

    private function nextTransactionIdForClient(string $clientId): string
    {
        $year = now()->format('y');
        $pattern = $clientId . '-' . $year . '-';

        $latest = TransactionHistory::query()
            ->where('transaction_id', 'like', $pattern . '%')
            ->orderByDesc('transaction_id')
            ->value('transaction_id');

        $current = 1;
        if ($latest !== null && preg_match('/-(\d{4})$/', $latest, $m)) {
            $current = (int) $m[1] + 1;
        }

        return $clientId . '-' . $year . '-' . str_pad((string) $current, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Transfer a single pending event into a client history record.
     */
    public function transfer(TransactionEvent $event)
    {
        if ($event->transferred_at !== null) {
            return redirect()->route('transaction-events.records')->with('error', 'This record has already been transferred.');
        }

        $client = $this->findOrCreateClientForEvent($event->full_name, [
            'full_name' => $event->full_name,
            'client_category' => $event->client_category,
            'contact_no' => $event->contact_no,
            'address' => $event->address,
            'age' => $event->age,
            'birth_date' => $event->birth_date?->format('Y-m-d'),
        ], $event->birth_date?->format('Y-m-d'));

        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => $event->client_category ?? '',
            'transaction_id' => $this->nextTransactionIdForClient($client->client_id),
            'transaction_date' => $event->event_date?->format('Y-m-d') ?? now()->toDateString(),
            'category' => $event->transaction_category ?? '',
            'type' => $event->transaction_type ?? '',
            'events_transaction_type' => $event->transaction_type ?? '',
            'status' => 'Approved',
            'source' => 'transfer',
            'description' => 'Transferred from event record.',
        ]);

        $event->update([
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        return redirect()->route('transaction-events.records')->with('success', 'Event transferred successfully.');
    }

    /**
     * Undo a single transfer.
     */
    public function undoTransfer(TransactionEvent $event)
    {
        $transaction = null;
        $transactionId = $event->transferred_transaction_id;

        if ($transactionId !== null) {
            $transaction = TransactionHistory::find($transactionId);
        }

        if ($transaction === null && $transactionId === null) {
            $audit = ActivityLog::query()
                ->where('subject_type', 'TransactionHistory')
                ->get()
                ->first(function ($log) use ($event) {
                    $properties = is_array($log->properties) ? $log->properties : (array) ($log->properties ?? []);
                    return (int) ($properties['event_id'] ?? 0) === (int) $event->id;
                });

            if ($audit) {
                $transaction = TransactionHistory::find($audit->subject_id);
                $transactionId = $transaction?->id;
            }
        }

        if ($transaction === null) {
            return redirect()->route('transaction-events.records')->with('error', 'No transferred transaction was found for this event.');
        }

        if (TransactionRequirement::query()->where('transaction_id', $transaction->id)->exists()) {
            return redirect()->route('transaction-events.records')->with('error', 'This transfer cannot be undone because the transaction has uploaded requirements.');
        }

        $linkedTransactionId = $transaction->transaction_id;
        $transaction->delete();
        $event->update([
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'event_transfer_undone',
            'subject_type' => 'TransactionEvent',
            'subject_id' => $event->id,
            'description' => 'Undid transfer for event and removed linked transaction.',
        ]);

        return redirect()->route('transaction-events.records')->with('success', 'Transfer undone. Transaction ' . $linkedTransactionId . ' was removed and the event is pending again.');
    }

    /**
     * Transfer one event via JSON for bulk UI flows.
     */
    public function transferOne(Request $request)
    {
        $request->validate([
            'event_id' => ['required', 'integer'],
        ]);

        $event = TransactionEvent::find($request->input('event_id'));
        if (! $event) {
            abort(404, 'The selected event id is invalid.');
        }

        if ($event->transferred_at !== null) {
            return response()->json(['success' => false, 'message' => 'This event has already been transferred.'], 422);
        }

        $client = $this->findOrCreateClientForEvent($event->full_name, [
            'full_name' => $event->full_name,
            'client_category' => $event->client_category,
            'contact_no' => $event->contact_no,
            'address' => $event->address,
            'age' => $event->age,
            'birth_date' => $event->birth_date?->format('Y-m-d'),
        ], $event->birth_date?->format('Y-m-d'));

        $isNewClient = $client->wasRecentlyCreated;

        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => $event->client_category ?? '',
            'transaction_id' => $this->nextTransactionIdForClient($client->client_id),
            'transaction_date' => $event->event_date?->format('Y-m-d') ?? now()->toDateString(),
            'category' => $event->transaction_category ?? '',
            'type' => $event->transaction_type ?? '',
            'events_transaction_type' => $event->transaction_type ?? '',
            'status' => 'Approved',
            'source' => 'transfer-one',
            'description' => 'Transferred from event record.',
        ]);

        $event->update([
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        return response()->json([
            'success' => true,
            'created_client' => $isNewClient,
            'transaction_id' => $history->transaction_id,
        ]);
    }

    /**
     * Transfer multiple selected events.
     */
    public function transferSelected(Request $request)
    {
        $request->validate([
            'event_ids' => ['required', 'array'],
            'event_ids.*' => ['integer', 'exists:transaction_events,id'],
        ]);

        foreach ($request->input('event_ids') as $id) {
            $event = TransactionEvent::find($id);
            if ($event && $event->transferred_at === null) {
                $this->transfer($event);
            }
        }

        return redirect()->back()->with('success', 'Selected events transferred successfully.');
    }

    /**
     * Resolve which pending event ids match the current filter set.
     */
    public function transferSelectedIds(Request $request)
    {
        $request->validate([
            'select_all' => ['required', 'accepted'],
        ]);

        $query = TransactionEvent::query()->whereNull('transferred_at')->where('not_duplicate', false);
        $this->applyEventListFilters($query, $request);

        $ids = $query->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'success' => true,
            'total' => count($ids),
            'ids' => $ids,
        ]);
    }

    /**
     * Undo multiple transferred events in one request.
     */
    public function undoTransferSelected(Request $request)
    {
        $request->validate([
            'event_ids' => ['required', 'array'],
            'event_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_filter($request->input('event_ids', []), 'is_numeric')));
        if ($ids === []) {
            abort(422, 'No event ids were selected.');
        }

        $undone = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $event = TransactionEvent::find($id);
            if (! $event || $event->transferred_at === null) {
                $skipped++;
                continue;
            }

            $transactionId = $event->transferred_transaction_id;
            $transaction = $transactionId ? TransactionHistory::find($transactionId) : null;
            if (! $transaction) {
                $event->update([
                    'transferred_at' => null,
                    'transferred_transaction_id' => null,
                ]);
                $skipped++;
                continue;
            }

            if (TransactionRequirement::query()->where('transaction_id', $transaction->id)->exists()) {
                $skipped++;
                continue;
            }

            $transaction->delete();
            $event->update([
                'transferred_at' => null,
                'transferred_transaction_id' => null,
            ]);
            $undone++;
        }

        return response()->json([
            'success' => true,
            'undone' => $undone,
            'skipped' => $skipped,
        ]);
    }

    public function undoTransferSelectedIds(Request $request)
    {
        $request->validate([
            'select_all' => ['required', 'accepted'],
        ]);

        $query = TransactionEvent::query()->whereNotNull('transferred_at');
        $this->applyRecordFilters($query, $request);

        $ids = $query->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'success' => true,
            'total' => count($ids),
            'ids' => $ids,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $rows = $this->parseImportFile($request->file('csv_file'), $request->file('csv_file')->getClientOriginalName());
        $parsed = $this->importRowsToRecords($rows);

        return response()->json([
            'success' => true,
            'rows' => $parsed['rows'],
            'total_rows' => count($parsed['rows']),
            'skipped_rows' => $parsed['skipped'],
        ]);
    }

    public function markNotDuplicate(TransactionEvent $event)
    {
        $event->update(['not_duplicate' => true]);

        return redirect()->back()->with('success', 'Marked as not a duplicate.');
    }

    public function resetNotDuplicate(TransactionEvent $event)
    {
        $event->update(['not_duplicate' => false]);

        return redirect()->back()->with('success', 'Reset duplicate flag.');
    }

    public function markGroupNotDuplicate(Request $request)
    {
        $ids = $request->input('event_ids', []);
        TransactionEvent::whereIn('id', $ids)->update(['not_duplicate' => true]);

        return redirect()->back()->with('success', 'Duplicate group marked as not duplicate.');
    }
}
