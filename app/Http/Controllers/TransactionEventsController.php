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
use Illuminate\Support\Facades\Log;
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

        // How many rows would Select All actually target (duplicates excluded).
        // If the remaining filtered data is all duplicates, this is 0 and the
        // Select All checkbox must stay disabled.
        if ($request->boolean('duplicate_names')) {
            $selectableTotal = 0;
        } else {
            $selectableQuery = TransactionEvent::whereNull('transferred_at')
                ->where('not_duplicate', false);
            $this->applyEventListFilters($selectableQuery, $request);
            if (! empty($duplicateFullNames)) {
                $selectableQuery->whereNotIn('full_name', $duplicateFullNames);
            }
            $selectableTotal = (clone $selectableQuery)->count();
        }

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
            compact('events', 'totalDuplicateGroups', 'duplicateFullNames', 'clientCategories', 'transactionCategories', 'transactionTypes', 'selectableTotal'));
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

        if ($eventDateFrom = $request->input('event_date_from')) {
            $query->whereDate('event_date', '>=', $eventDateFrom);
        }

        if ($eventDateTo = $request->input('event_date_to')) {
            $query->whereDate('event_date', '<=', $eventDateTo);
        }

        if ($clientCategories = $this->multiFilterValues($request, 'client_category')) {
            $query->whereIn('client_category', $clientCategories);
        }

        if ($txCategories = $this->multiFilterValues($request, 'transaction_category')) {
            $query->whereIn('transaction_category', $txCategories);
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

        if ($eventDateFrom = $request->input('event_date_from')) {
            $query->whereDate('event_date', '>=', $eventDateFrom);
        }

        if ($eventDateTo = $request->input('event_date_to')) {
            $query->whereDate('event_date', '<=', $eventDateTo);
        }

        if ($categories = $this->multiFilterValues($request, 'transaction_category')) {
            $query->whereIn('transaction_category', $categories);
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
     * Normalized exact-match duplicate key for transferred records.
     * Exact match is: Same Full Name + Client Category +
     * Transaction Category + Transaction Type + Event Date.
     */
    private function duplicateRecordKey(mixed $row): string
    {
        $value = function (string $key) use ($row): string {
            $raw = null;

            if (is_array($row)) {
                $raw = $row[$key] ?? null;
            } elseif ($row instanceof \Illuminate\Database\Eloquent\Model) {
                $raw = $row->getAttribute($key);
            } elseif (is_object($row)) {
                $raw = $row->{$key} ?? null;
            }

            return strtolower(trim((string) ($raw ?? '')));
        };

        $eventDate = $value('event_date');
        if ($eventDate !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $eventDate)) {
                $eventDate = substr($eventDate, 0, 10);
            } else {
                try {
                    $eventDate = \Carbon\Carbon::parse($eventDate)->toDateString();
                } catch (\Throwable) {
                    // Keep the raw value when it is not a parseable date.
                }
            }
        }

        return implode('|', [
            $value('full_name'),
            $value('client_category'),
            $value('transaction_category'),
            $value('transaction_type'),
            $eventDate,
        ]);
    }

    /**
     * Every exact-match duplicate key (see duplicateRecordKey()) occurring
     * more than once among transferred records. Exact match is: Same
     * Full Name + Client Category + Transaction Category +
     * Transaction Type + Event Date.
     *
     * @return string[]
     */
    private function duplicateRecordKeys(): array
    {
        $groups = TransactionEvent::query()
            ->whereNotNull('transferred_at')
            ->selectRaw("LOWER(TRIM(COALESCE(full_name,''))) as nk_name, COALESCE(DATE(event_date),'') as nk_event_date, LOWER(TRIM(COALESCE(client_category,''))) as nk_client_category, LOWER(TRIM(COALESCE(transaction_category,''))) as nk_transaction_category, LOWER(TRIM(COALESCE(transaction_type,''))) as nk_transaction_type, COUNT(*) as total")
            ->groupBy(DB::raw("LOWER(TRIM(COALESCE(full_name,'')))"), DB::raw("COALESCE(DATE(event_date),'')"), DB::raw("LOWER(TRIM(COALESCE(client_category,'')))"), DB::raw("LOWER(TRIM(COALESCE(transaction_category,'')))"), DB::raw("LOWER(TRIM(COALESCE(transaction_type,'')))"))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $groups
            ->map(fn ($group) => $this->duplicateRecordKey([
                'full_name' => $group->nk_name ?? '',
                'client_category' => $group->nk_client_category ?? '',
                'transaction_category' => $group->nk_transaction_category ?? '',
                'transaction_type' => $group->nk_transaction_type ?? '',
                'event_date' => $group->nk_event_date ?? '',
            ]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve every transferred event id matching the current Event Records
     * filters (for cross-page bulk undo). When $excludeDuplicates is true,
     * rows belonging to a 5-field duplicate group are left out so Select All
     * never checks them.
     *
     * @return int[]
     */
    private function resolveUndoSelectedIds(Request $request, bool $excludeDuplicates = false): array
    {
        $query = TransactionEvent::query()->whereNotNull('transferred_at');

        $this->applyRecordFilters($query, $request);

        $rows = $query->orderByDesc('id')->get([
            'id', 'full_name', 'client_category', 'transaction_category', 'transaction_type', 'event_date',
        ]);

        if ($excludeDuplicates) {
            $duplicateKeys = array_flip($this->duplicateRecordKeys());

            $rows = $rows->reject(fn ($row) => isset($duplicateKeys[$this->duplicateRecordKey($row)]));
        }

        return $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function updateRecord(Request $request, TransactionEvent $event)
    {
        abort_if(auth()->user()->role_name === 'Viewer', 403, 'Viewer role is read-only.');
        abort_if($event->transferred_at === null, 404);

        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'contact_no' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'birth_date' => 'nullable|date',
            'client_category' => 'nullable|string|max:100',
            'transaction_category' => 'nullable|string|max:100',
            'transaction_type' => 'nullable|string|max:100',
            'event_date' => 'nullable|date',
        ]);

        $event->update($validated);

        return redirect()->route('transaction-events.records', $request->query())
            ->with('success', 'Event record updated successfully.');
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

        // Ids on this page belonging to a 5-field duplicate group so Select
        // All can leave them unchecked.
        $duplicateKeySet = array_flip($this->duplicateRecordKeys());
        $duplicateRecordIds = $events->getCollection()
            ->filter(fn ($event) => isset($duplicateKeySet[$this->duplicateRecordKey($event)]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('pages.transaction_events.eventRecords', compact('events', 'categories', 'types', 'clientCategories', 'typeClientCategories', 'duplicateRecordIds'));
    }

    /**
     * Export every matching Event Record to a printable alphabetical roster.
     */
    public function exportRecordsPdf(Request $request)
    {
        abort_unless(feature_allowed('Event Records'), 404);

        $details = $request->validate([
            'prepared_by' => 'nullable|string|max:100',
            'reviewed_by' => 'nullable|string|max:100',
            'approved_by' => 'nullable|string|max:100',
            'report_date' => 'nullable|string|max:100',
            'numbered_tranches' => 'sometimes|array|max:4',
            'numbered_tranches.*' => 'required|integer|in:1,2,3,4|distinct',
        ]);

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $query = TransactionEvent::whereNotNull('transferred_at');
        $this->applyRecordFilters($query, $request);
        $events = $query->orderByRaw('LOWER(TRIM(full_name))')->orderBy('id')->get();

        // Sex is available only through the linked client; never infer it from a name.
        $histories = TransactionHistory::whereIn('id', $events->pluck('transferred_transaction_id')->filter()->unique())
            ->pluck('client_id', 'id');
        $clients = Client::whereIn('client_id', $histories->values()->unique())
            ->get(['client_id', 'gender'])->keyBy('client_id');
        foreach ($events as $event) {
            $event->setAttribute('export_sex', $clients->get($histories->get($event->transferred_transaction_id))?->gender ?? '');
        }

        $categories = $events->pluck('transaction_category')->map(fn ($value) => mb_strtoupper(trim((string) $value)))->unique();
        $selectedCategories = collect($this->multiFilterValues($request, 'transaction_category'))
            ->map(fn ($value) => mb_strtoupper(trim($value)));
        $isRice = ($categories->isNotEmpty() ? $categories : $selectedCategories)->all() === ['BIGAY BIGAS SA MASA'];
        $dates = $events->pluck('event_date')->filter()->map(fn ($date) => $date->format('Y-m-d'))->unique()->sort()->values();
        $dateLabel = $dates->count() === 1 ? $dates->first() : ($dates->count() > 1 ? $dates->first().' to '.$dates->last() : '');
        $dateLabel = $details['report_date'] ?? $dateLabel;

        if ($request->isMethod('post')) {
            return response()->json(app(\App\Services\EventRecordsPdfProcess::class)
                ->start($events, $isRice, $dateLabel, (int) $request->user()->id, $details));
        }

        $content = app(\App\Services\EventRecordsPdfExporter::class)->render($events, $isRice, $dateLabel, $details);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="event_records_'.now()->format('Ymd_His').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function advanceRecordsPdf(Request $request, string $token)
    {
        abort_unless(feature_allowed('Event Records'), 404);
        @ini_set('memory_limit', '512M');

        return response()->json(app(\App\Services\EventRecordsPdfProcess::class)->step($token, (int) $request->user()->id));
    }

    public function downloadRecordsPdf(Request $request, string $token)
    {
        abort_unless(feature_allowed('Event Records'), 404);
        $path = app(\App\Services\EventRecordsPdfProcess::class)->downloadPath($token, (int) $request->user()->id);

        return response()->download($path, 'event_records_'.now()->format('Ymd_His').'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Export the filtered Event Records to XLSX across all listing pages.
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

    /**
     * Paginate group descriptors in SQL, then fetch the visible groups in one
     * joined query. Query count is independent of the number of duplicates.
     *
     * Exact match is: Same Full Name + Birth Date + Client Category +
     * Transaction Category + Transaction Type + Event Date (normalized:
     * case-insensitive, trimmed, date-only so "PWD" = "pwd" and datetimes
     * on the same day still match).
     */
    private function recordDuplicateNormalizedExpression(string $column): string
    {
        return match ($column) {
            'full_name' => "LOWER(TRIM(COALESCE(full_name,'')))",
            'client_category' => "LOWER(TRIM(COALESCE(client_category,'')))",
            'transaction_category' => "LOWER(TRIM(COALESCE(transaction_category,'')))",
            'transaction_type' => "LOWER(TRIM(COALESCE(transaction_type,'')))",
            'event_date' => "COALESCE(DATE(event_date),'')",
            'birth_date' => "COALESCE(DATE(birth_date),'')",
            default => $column,
        };
    }

    /**
     * Null-safe equality mirroring the duplicate tabs: missing values
     * (null or '') are equal, and dates compare by day only.
     */
    private function sameDuplicateValue($a, $b): bool
    {
        if ($a instanceof \DateTimeInterface) {
            $a = $a->format('Y-m-d');
        }
        if ($b instanceof \DateTimeInterface) {
            $b = $b->format('Y-m-d');
        }
        $a = $a ?? '';
        $b = $b ?? '';

        return (string) $a === (string) $b;
    }

    /**
     * Narrow the duplicate-group population by the Filter Duplicates form
     * (keyword, multi-select categories/type, event date range). Applied to
     * each pattern's base query before grouping so counts and pages reflect
     * exactly what the filters show.
     */
    private function applyRecordDuplicatePrefilters($query, Request $request): void
    {
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($matches) use ($search) {
                $matches->where('full_name', 'like', "%{$search}%")
                    ->orWhere('client_category', 'like', "%{$search}%")
                    ->orWhere('transaction_category', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%");
            });
        }

        if ($values = $this->multiFilterValues($request, 'client_category')) {
            $query->whereIn('client_category', $values);
        }

        if ($values = $this->multiFilterValues($request, 'transaction_category')) {
            $query->whereIn('transaction_category', $values);
        }

        if ($values = $this->multiFilterValues($request, 'transaction_type')) {
            $query->whereIn('transaction_type', $values);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('event_date', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('event_date', '<=', $to);
        }
    }

    /**
     * One shared aggregation for every duplicate tab: transferred (and
     * pre-filtered) rows grouped by the full normalized duplicate key.
     * All tabs regroup these rows in PHP, so the table is scanned once
     * instead of once per pattern plus once per totals query.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function recordDuplicateKeyRows(Request $request): Collection
    {
        $columns = ['event_date', 'birth_date', 'client_category', 'transaction_category', 'transaction_type'];
        $query = DB::table('transaction_events')->whereNotNull('transferred_at');
        $this->applyRecordDuplicatePrefilters($query, $request);
        $query->selectRaw($this->recordDuplicateNormalizedExpression('full_name').' as fullname');
        foreach ($columns as $column) {
            $query->selectRaw($this->recordDuplicateNormalizedExpression($column).' as '.$column);
        }
        $query->selectRaw('COUNT(*) as total, MIN(id) as group_id')
            ->groupBy(
                DB::raw($this->recordDuplicateNormalizedExpression('full_name')),
                ...array_map(fn ($c) => DB::raw($this->recordDuplicateNormalizedExpression($c)), $columns)
            );

        return $query->get();
    }

    /**
     * Regroup full-key rows into tab descriptors. Exact keeps full-key rows
     * with more than one record; likely regroups by each pattern and keeps
     * groups with more than one record that vary outside the pattern (so an
     * exact match never repeats as likely).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildRecordDuplicateDescriptors(Collection $keyRows, array $patterns, bool $excludeExact): Collection
    {
        $columns = ['event_date', 'birth_date', 'client_category', 'transaction_category', 'transaction_type'];
        $descriptors = collect();
        foreach ($patterns as $pattern => $groupColumns) {
            $grouped = $keyRows->groupBy(fn ($row) => $row->fullname."\0".implode("\0", array_map(fn ($c) => (string) $row->$c, $groupColumns)));
            foreach ($grouped as $members) {
                $total = $members->sum('total');
                if ($total <= 1) {
                    continue;
                }
                if ($excludeExact && $members->count() <= 1) {
                    continue;
                }
                $first = $members->first();
                $descriptor = new \stdClass;
                $descriptor->pattern = $pattern;
                $descriptor->fullname = $first->fullname;
                $descriptor->group_id = $members->min('group_id');
                $descriptor->total = $total;
                foreach ($columns as $column) {
                    $descriptor->$column = in_array($column, $groupColumns, true) ? $first->$column : null;
                }
                $descriptors->push($descriptor);
            }
        }

        return $descriptors->sort(function ($a, $b) {
            if ($a->total !== $b->total) {
                return $b->total <=> $a->total;
            }
            if ($a->pattern !== $b->pattern) {
                return $a->pattern <=> $b->pattern;
            }

            return $a->group_id <=> $b->group_id;
        })->values();
    }

    private function paginatedRecordDuplicateGroups(Collection $keyRows, array $patterns, Request $request, int $perPage, string $pageName, bool $excludeExact = false): array
    {
        $columns = ['event_date', 'birth_date', 'client_category', 'transaction_category', 'transaction_type'];
        $descriptors = $this->buildRecordDuplicateDescriptors($keyRows, $patterns, $excludeExact);
        $recordsTotal = (int) $descriptors->sum('total');
        $page = max(1, (int) $request->input($pageName, $pageName === 'exact_page' ? $request->input('page', 1) : 1));
        $pageDescriptors = $descriptors->forPage($page, $perPage)->values();
        $groups = collect();
        if ($pageDescriptors->isNotEmpty()) {
            // A small bound-value table loads only the visible groups'
            // events in a single query.
            $pageTable = null;
            foreach ($pageDescriptors as $descriptor) {
                $fields = ['fullname', 'group_id', 'pattern', ...$columns];
                $row = DB::query()->selectRaw(
                    implode(', ', array_map(fn ($field) => '? as '.$field, $fields)),
                    array_map(fn ($field) => $descriptor->$field, $fields)
                );
                $pageTable = $pageTable === null ? $row : $pageTable->unionAll($row);
            }
            $events = TransactionEvent::query()->whereNotNull('transaction_events.transferred_at')
                ->joinSub($pageTable, 'visible_groups', function ($join) use ($patterns) {
                    $join->on(DB::raw(str_replace('full_name', 'transaction_events.full_name', $this->recordDuplicateNormalizedExpression('full_name'))), '=', 'visible_groups.fullname');
                    $join->where(function ($matches) use ($patterns) {
                        foreach ($patterns as $pattern => $groupColumns) {
                            $matches->orWhere(function ($match) use ($pattern, $groupColumns) {
                                $match->where('visible_groups.pattern', $pattern);
                                foreach ($groupColumns as $column) {
                                    // Normalized values are never NULL (COALESCE to ''),
                                    // so plain equality is null-safe. Never let an OR
                                    // escape the name/transferred constraints.
                                    $expr = str_replace($column, 'transaction_events.'.$column, $this->recordDuplicateNormalizedExpression($column));
                                    $match->whereRaw('('.$expr.' = visible_groups.'.$column.')');
                                }
                            });
                        }
                    });
                })
                ->select('transaction_events.*', 'visible_groups.group_id as duplicate_group_id', 'visible_groups.pattern as duplicate_pattern')
                ->with('transferredTransaction:id,transaction_id')
                ->orderByDesc('transaction_events.id')->get()
                ->groupBy(fn ($event) => $event->duplicate_pattern.':'.$event->duplicate_group_id);
            $groups = $pageDescriptors->map(fn ($descriptor) => [
                'events' => $events->get($descriptor->pattern.':'.$descriptor->group_id, collect()),
                'total' => (int) $descriptor->total,
            ]);
        }

        return [new LengthAwarePaginator($groups, $descriptors->count(), $perPage, $page, [
            'path' => url()->current(),
            'query' => array_merge($request->query(), ['duplicate_tab' => match ($pageName) {
                'likely_page' => 'likely',
                'similar_page' => 'full_name',
                default => 'exact',
            }]),
            'pageName' => $pageName,
        ]), $recordsTotal];
    }

    public function recordsDuplicates(Request $request)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Exact match is: Same Full Name + Birth Date + Client Category +
        // Transaction Category + Transaction Type + Event Date.
        // One shared full-key aggregation feeds all tabs, so the table is
        // scanned once no matter how many patterns or pages are involved.
        $duplicateKeyRows = $this->recordDuplicateKeyRows($request);
        [$exactGroups, $exactRecordsTotal] = $this->paginatedRecordDuplicateGroups($duplicateKeyRows, [
            'exact' => ['event_date', 'birth_date', 'client_category', 'transaction_category', 'transaction_type'],
        ], $request, $perPage, 'exact_page');
        [$likelyGroups, $likelyRecordsTotal] = $this->paginatedRecordDuplicateGroups($duplicateKeyRows, [
            'event_date+transaction_category' => ['birth_date', 'event_date', 'transaction_category'],
            'event_date+transaction_type' => ['birth_date', 'event_date', 'transaction_type'],
            'transaction_category+transaction_type' => ['birth_date', 'transaction_category', 'transaction_type'],
            'event_date' => ['birth_date', 'event_date'],
            'transaction_type' => ['birth_date', 'transaction_type'],
            'transaction_category' => ['birth_date', 'transaction_category'],
        ], $request, $perPage, 'likely_page', true);
        [$similarGroups, $similarRecordsTotal] = $this->paginatedRecordDuplicateGroups($duplicateKeyRows, [
            'full_name' => [],
        ], $request, $perPage, 'similar_page');
        $exactGroupsTotal = $exactGroups->total();
        $likelyGroupsTotal = $likelyGroups->total();
        $similarGroupsTotal = $similarGroups->total();

        $filterClientCategories = TransactionEvent::whereNotNull('transferred_at')
            ->select('client_category')->distinct()->pluck('client_category')->filter()->sort()->values();
        $filterTransactionCategories = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_category')->distinct()->pluck('transaction_category')->filter()->sort()->values();
        $filterTransactionTypes = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_type')->distinct()->pluck('transaction_type')->filter()->sort()->values();

        return view('pages.transaction_events.recordsDuplicates', compact(
            'exactGroups', 'likelyGroups', 'similarGroups', 'exactRecordsTotal', 'likelyRecordsTotal', 'similarRecordsTotal',
            'exactGroupsTotal', 'likelyGroupsTotal', 'similarGroupsTotal',
            'filterClientCategories', 'filterTransactionCategories', 'filterTransactionTypes', 'perPage'
        ));
    }
    public function archives(Request $request)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }

        $files = ImportArchiveFile::query()
            ->orderByDesc('imported_at')
            ->get()
            ->map(function ($f) {
                return [
                    'name' => $f->original_filename ?: $f->filename,
                    'imported_by' => $f->imported_by ? ['imported_by' => $f->imported_by, 'role' => $f->role ?? ''] : null,
                    'uploaded_at' => $f->imported_at?->getTimestamp() ?? null,
                    'size' => (int) ($f->file_size ?? 0),
                    'download_url' => route('transaction-events.archives.download', ['filename' => $f->filename]),
                ];
            })->all();

        return view('pages.transaction_events.transactionEventArchives', compact('files'));
    }

    public function downloadArchive(Request $request, string $filename)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }

        $file = ImportArchiveFile::where('filename', $filename)->firstOrFail();

        if (! feature_allowed('Download Archive')) {
            abort(403);
        }

        $path = 'transaction-events-archive/' . $file->filename;
        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'Archive file not found on disk.');
        }

        return Storage::disk('local')->download($path, $file->original_filename ?: $file->filename);
    }

    public function duplicateReview(Request $request)
    {
        if (!feature_allowed('Duplicate Review')) {
            abort(404);
        }

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $page = (int) $request->input('page', 1);

        $base = TransactionEvent::whereNull('transferred_at')->where('not_duplicate', false);

        $groupsQuery = (clone $base)
            ->selectRaw('LOWER(TRIM(full_name)) as fullname, event_date, client_category, transaction_category, transaction_type, COUNT(*) as total')
            ->groupBy(DB::raw('LOWER(TRIM(full_name))'), 'event_date', 'client_category', 'transaction_category', 'transaction_type')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total');

        $allGroups = $groupsQuery->get();
        $totalGroups = $allGroups->count();

        $pageSlice = $allGroups->forPage($page, $perPage)->values();

        $groups = $pageSlice->map(function ($g) {
            $query = TransactionEvent::query()->whereNull('transferred_at')->where('not_duplicate', false)
                ->whereRaw('LOWER(TRIM(full_name)) = ?', [$g->fullname]);

            if ($g->event_date !== null && $g->event_date !== '') {
                $query->whereDate('event_date', $g->event_date);
            } else {
                $query->whereNull('event_date')->orWhere('event_date', '');
            }

            if ($g->client_category !== null && $g->client_category !== '') {
                $query->where('client_category', $g->client_category);
            } else {
                $query->whereNull('client_category')->orWhere('client_category', '');
            }

            if ($g->transaction_category !== null && $g->transaction_category !== '') {
                $query->where('transaction_category', $g->transaction_category);
            } else {
                $query->whereNull('transaction_category')->orWhere('transaction_category', '');
            }

            if ($g->transaction_type !== null && $g->transaction_type !== '') {
                $query->where('transaction_type', $g->transaction_type);
            } else {
                $query->whereNull('transaction_type')->orWhere('transaction_type', '');
            }

            $events = $query->orderByDesc('id')->get();

            return ['events' => $events, 'total' => (int) $g->total, 'created_at' => $events->min('created_at')];
        })->values();

        $exactGroups = new LengthAwarePaginator($groups, $totalGroups, $perPage, $page, [
            'path' => url()->current(),
            'query' => $request->query(),
        ]);

        // Build likely-match groups: Same Full Name plus at least one of:
        // Event Date + Transaction Category, Event Date + Transaction Type,
        // Transaction Category + Transaction Type, Event Date only,
        // Transaction Type only, or Transaction Category only.
        $likelyCollection = collect();
        $seen = [];
        $patterns = [
            ['event_date', 'transaction_category'],
            ['event_date', 'transaction_type'],
            ['transaction_category', 'transaction_type'],
            ['event_date'],
            ['transaction_type'],
            ['transaction_category'],
        ];

        foreach ($patterns as $pi => $cols) {
            $sel = 'LOWER(TRIM(full_name)) as fullname, ' . implode(', ', $cols) . ', COUNT(*) as total';
            $groupRows = (clone $base)
                ->selectRaw($sel)
                ->groupBy(DB::raw('LOWER(TRIM(full_name))'), ...$cols)
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groupRows as $g) {
                $keyParts = [$pi, $g->fullname];
                foreach ($cols as $c) {
                    $keyParts[] = (string) ($g->$c ?? '');
                }
                $key = implode('|', $keyParts);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                $query = TransactionEvent::query()->whereNull('transferred_at')->where('not_duplicate', false)
                    ->whereRaw('LOWER(TRIM(full_name)) = ?', [$g->fullname]);

                foreach ($cols as $c) {
                    $val = $g->$c ?? null;
                    if ($c === 'event_date') {
                        if ($val !== null && $val !== '') {
                            $query->whereDate('event_date', $val);
                        } else {
                            $query->whereNull('event_date')->orWhere('event_date', '');
                        }
                    } else {
                        if ($val !== null && $val !== '') {
                            $query->where($c, $val);
                        } else {
                            $query->whereNull($c)->orWhere($c, '');
                        }
                    }
                }

                $events = $query->orderByDesc('id')->get();
                // Exact matches already live in the Exact tab; don't repeat
                // the identical group here. A likely group whose rows all
                // share the full exact key is the exact match, not a
                // likely one (genuine likely groups vary somewhere outside
                // the pattern and still pass through).
                $firstLikely = $events->first();
                if ($firstLikely !== null && $events->every(fn ($event) =>
                    $this->sameDuplicateValue($event->event_date, $firstLikely->event_date) &&
                    $this->sameDuplicateValue($event->client_category, $firstLikely->client_category) &&
                    $this->sameDuplicateValue($event->transaction_category, $firstLikely->transaction_category) &&
                    $this->sameDuplicateValue($event->transaction_type, $firstLikely->transaction_type))) {
                    continue;
                }
                $likelyCollection->push(['events' => $events, 'total' => (int) $g->total, 'created_at' => $events->min('created_at')]);
            }
        }

        $likelyGroups = new LengthAwarePaginator($likelyCollection->values(), $likelyCollection->count(), $perPage, (int) $request->input('likely_page', 1), [
            'path' => url()->current(),
            'query' => $request->query(),
        ]);

        $similarGroups = new LengthAwarePaginator([], 0, $perPage, (int) $request->input('similar_page', 1), [
            'path' => url()->current(),
            'query' => $request->query(),
        ]);

        $filterClientCategories = (clone $base)->select('client_category')->distinct()->pluck('client_category')->filter()->sort()->values();
        $filterTransactionCategories = (clone $base)->select('transaction_category')->distinct()->pluck('transaction_category')->filter()->sort()->values();
        $filterTransactionTypes = (clone $base)->select('transaction_type')->distinct()->pluck('transaction_type')->filter()->sort()->values();

        $notDuplicates = TransactionEvent::whereNull('transferred_at')->where('not_duplicate', true)
            ->orderByDesc('id')->get();

        return view('pages.transaction_events.duplicateReview', compact(
            'exactGroups', 'likelyGroups', 'similarGroups',
            'filterClientCategories', 'filterTransactionCategories', 'filterTransactionTypes',
            'notDuplicates', 'perPage'
        ));
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
     * Force a raw cell value into valid UTF-8. Files saved outside UTF-8
     * (e.g. Windows-1252 with ñ, smart quotes, or en-dashes — common in
     * Excel-origin CSVs) otherwise blow up every JSON response with
     * "Malformed UTF-8 characters" and store undecodable bytes. Sanitizing
     * once at parse time protects responses, session payloads, and the DB.
     */
    private function sanitizeImportCellValue(mixed $value): string
    {
        $value = is_string($value) ? $value : (string) $value;
        $value = trim($value);

        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        return mb_check_encoding($converted, 'UTF-8') ? $converted : '';
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

        if ($name === ''
            || str_ends_with($name, '.xlsx')
            || str_ends_with($name, '.xlsm')
            || str_ends_with($name, '.xltx')
            || str_ends_with($name, '.xltm')
            || str_ends_with($name, '.xls')) {
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
            $rows[] = array_map(fn ($cell) => $this->sanitizeImportCellValue($cell), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse a minimal .xlsx worksheet into a row matrix.
     *
     * @return array<int, array<int, string>>
     */
    /**
     * Convert an .xlsx cell reference (e.g. "C12", "AA7") to a zero-based
     * column index. Returns null when the reference carries no column part.
     */
    private function xlsxColumnIndexFromReference(?string $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (! preg_match('/^([A-Za-z]+)/', $reference, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * Collect every descendant <t> text node (namespace-agnostic) so both
     * plain (<si><t>) and rich-text (<si><r><t>>, <is><r><t>>) strings
     * resolve to their visible value.
     */
    private function xlsxCollectText(\SimpleXMLElement $node): string
    {
        $text = '';
        $parts = $node->xpath('.//*[local-name()="t"]');
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $text .= (string) $part;
            }
        }

        return $text;
    }

    private function parseXlsxImportFile(string $path): array
    {
        if (! is_string($path) || $path === '' || ! file_exists($path)) {
            return [];
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = @simplexml_load_string($sharedXml);
            if ($shared !== false) {
                $siNodes = $shared->xpath('//*[local-name()="si"]');
                if (is_array($siNodes)) {
                    foreach ($siNodes as $si) {
                        $sharedStrings[] = $this->xlsxCollectText($si);
                    }
                }
            }
        }

        $sheetPath = null;
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml !== false) {
            $rels = @simplexml_load_string($relsXml);
            if ($rels !== false) {
                $relNodes = $rels->xpath('//*[local-name()="Relationship"]');
                if (is_array($relNodes)) {
                    foreach ($relNodes as $relationship) {
                        $type = (string) ($relationship->attributes()->Type ?? '');
                        if (str_contains($type, 'worksheet')) {
                            $target = (string) ($relationship->attributes()->Target ?? '');
                            // Targets are relative to xl/ (e.g. worksheets/sheet1.xml).
                            $target = ltrim($target, '/');
                            if (! str_starts_with($target, 'xl/')) {
                                $target = 'xl/' . $target;
                            }
                            $sheetPath = $target;
                            break;
                        }
                    }
                }
            }
        }

        if ($sheetPath === null) {
            $sheetPath = 'xl/worksheets/sheet1.xml';
        }

        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            // Fall back to the first worksheet part when the rels target
            // is missing or uses an unexpected layout.
            $sheetXml = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (is_string($entry) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $entry)) {
                    $sheetXml = $zip->getFromName($entry);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return [];
        }

        $rows = [];
        $rowNodes = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if (! is_array($rowNodes) || $rowNodes === []) {
            return [];
        }

        foreach ($rowNodes as $rowNode) {
            $cells = $rowNode->xpath('./*[local-name()="c"]');
            if (! is_array($cells) || $cells === []) {
                continue;
            }

            $byColumn = [];
            $maxColumn = -1;
            $sequential = 0;
            foreach ($cells as $cell) {
                $ref = (string) ($cell->attributes()->r ?? '');
                $column = $this->xlsxColumnIndexFromReference($ref !== '' ? $ref : null);
                if ($column === null) {
                    $column = $sequential;
                }
                $sequential = max($sequential, $column + 1);

                $cellType = (string) ($cell->attributes()->t ?? '');
                $value = '';
                if ($cellType === 'inlineStr') {
                    $isNodes = $cell->xpath('./*[local-name()="is"]');
                    if (is_array($isNodes) && isset($isNodes[0])) {
                        $value = $this->xlsxCollectText($isNodes[0]);
                    }
                } elseif ($cellType === 's') {
                    $vNodes = $cell->xpath('./*[local-name()="v"]');
                    $raw = trim((string) ($vNodes[0] ?? ''));
                    // A missing/empty <v> means an intentionally blank cell.
                    if ($raw !== '' && is_numeric($raw)) {
                        $value = $sharedStrings[(int) $raw] ?? '';
                    } else {
                        $value = '';
                    }
                } elseif ($cellType === 'b') {
                    $vNodes = $cell->xpath('./*[local-name()="v"]');
                    $value = trim((string) ($vNodes[0] ?? ''));
                } elseif ($cellType === 'e') {
                    // Excel error values (#DIV/0!, #N/A, ...) carry no data.
                    $value = '';
                } else {
                    // Covers n (numbers incl. date serials), str (formula
                    // string results), and cells with no t attribute.
                    $vNodes = $cell->xpath('./*[local-name()="v"]');
                    $value = (string) ($vNodes[0] ?? '');
                }
                $byColumn[$column] = $this->sanitizeImportCellValue($value);
                $maxColumn = max($maxColumn, $column);
            }
            if ($maxColumn < 0) {
                continue;
            }
            $values = [];
            $allEmpty = true;
            for ($i = 0; $i <= $maxColumn; $i++) {
                $cellValue = $byColumn[$i] ?? '';
                $values[] = $cellValue;
                if ($cellValue !== '') {
                    $allEmpty = false;
                }
            }
            // Skip fully blank rows (e.g. trailing empty rows Excel keeps).
            if ($allEmpty) {
                continue;
            }
            $rows[] = $values;
        }

        if ($rows !== []) {
            return $rows;
        }

        // Fallback: if PhpSpreadsheet is available, use it for more robust parsing
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $psRows = [];
                foreach ($sheet->toArray(null, true, true, true) as $row) {
                    // Normalize to zero-based numeric array and sanitize values
                    $vals = array_values($row);
                    $psRows[] = array_map(fn ($c) => $this->sanitizeImportCellValue($c), $vals);
                }

                if ($psRows !== []) {
                    return $psRows;
                }
            } catch (\Throwable $e) {
                // Fall through to returning empty rows below
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

    /**
     * Convert a raw Excel serial date (days since 1899-12-30, the 1900 date
     * system) to Y-m-d. Date-formatted .xlsx cells arrive as plain numbers
     * like "46268" from the native parser; without this every such row is
     * rejected as "Invalid event_date". Pure integers outside the plausible
     * supported range are left untouched so real data entry mistakes (e.g. an
     * age typed into a date column) still fail validation instead of
     * becoming silent garbage dates.
     */
    private function normalizeMaybeExcelSerialDate(string $value, bool $isBirthDate = false): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return $value;
        }

        $serial = (int) floor((float) $trimmed);

        // Birth dates can predate the modern event-date range. Start after
        // Excel's fictitious 1900-02-29 so the shared epoch remains accurate.
        $minimumSerial = $isBirthDate ? 61 : 15000;
        if ($serial < $minimumSerial || $serial > 80000) {
            return $value;
        }

        try {
            return \Carbon\Carbon::create(1899, 12, 30)->addDays($serial)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function importRowsToRecords(array $rows): array
    {
        if ($rows === []) {
            return ['rows' => [], 'skipped' => 0];
        }

        $header = array_map([$this, 'normalizeImportHeader'], $rows[0]);
        $rawHeader = $rows[0] ?? [];
        $records = [];
        $skipped = 0;
        $skippedExamples = [];

        // Log header information for debugging
        Log::debug('Import header detected', [
            'raw_header' => $rawHeader,
            'normalized_header' => $header,
            'header_count' => count($header),
            'has_full_name' => in_array('full_name', $header),
        ]);

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            $mapped = [];
            foreach ($header as $index => $key) {
                $mapped[$key] = $row[$index] ?? '';
            }

            // Excel date cells arrive as serial numbers; normalize them
            // before validation so correct files are not mass-skipped.
            foreach (['birth_date', 'birthdate', 'event_date'] as $dateKey) {
                if (isset($mapped[$dateKey]) && $mapped[$dateKey] !== '') {
                    $mapped[$dateKey] = $this->normalizeMaybeExcelSerialDate(
                        (string) $mapped[$dateKey],
                        in_array($dateKey, ['birth_date', 'birthdate'], true)
                    );
                }
            }

            if (($mapped['full_name'] ?? '') === '') {
                $skipped++;
                if (count($skippedExamples) < 50) {
                    $skippedExamples[] = ['line' => $i + 1, 'reason' => 'Missing full_name', 'data' => $mapped];
                }
                continue;
            }

            if (isset($mapped['age']) && $mapped['age'] !== '') {
                $age = (int) $mapped['age'];
                if ($age < 0 || $age > 120) {
                    $skipped++;
                    if (count($skippedExamples) < 50) {
                        $skippedExamples[] = ['line' => $i + 1, 'reason' => 'Invalid age', 'data' => $mapped];
                    }
                    continue;
                }
            }

            // Validate birth_date if present
            if (($mapped['birth_date'] ?? $mapped['birthdate'] ?? '') !== '') {
                $birthDateStr = $mapped['birth_date'] ?? $mapped['birthdate'];
                try {
                    \Carbon\Carbon::parse($birthDateStr);
                } catch (\Throwable) {
                    $skipped++;
                    if (count($skippedExamples) < 50) {
                        $skippedExamples[] = ['line' => $i + 1, 'reason' => 'Invalid birth_date format', 'data' => $mapped];
                    }
                    continue;
                }
            }

            if (($mapped['event_date'] ?? '') !== '') {
                try {
                    \Carbon\Carbon::parse($mapped['event_date']);
                } catch (\Throwable) {
                    $skipped++;
                    if (count($skippedExamples) < 50) {
                        $skippedExamples[] = ['line' => $i + 1, 'reason' => 'Invalid event_date', 'data' => $mapped];
                    }
                    continue;
                }
            }

            $records[] = $mapped;
        }

        return ['rows' => $records, 'skipped' => $skipped, 'skipped_examples' => $skippedExamples, 'headers' => $header];
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

        $nameKey = fn (string $name) => mb_strtolower(implode('|', $this->splitImportFullName($name)));
        $existingNames = TransactionEvent::query()->pluck('full_name')
            ->mapWithKeys(fn ($name) => [$nameKey((string) $name) => true])->all();
        $duplicates = [];
        $seen = [];

        foreach ($records as $record) {
            $fullName = trim((string) ($record['full_name'] ?? ''));
            $eventDate = trim((string) ($record['event_date'] ?? ''));
            $category = trim((string) ($record['transaction_category'] ?? ''));
            $transactionType = trim((string) ($record['transaction_type'] ?? ''));
            $key = $nameKey($fullName) . '|' . $eventDate . '|' . strtolower($category) . '|' . strtolower($transactionType);

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

            $birthDate = trim((string) ($record['birth_date'] ?? $record['birthdate'] ?? ''));
            $match = isset($existingNames[$nameKey($fullName)])
                || $this->findClientForImport($fullName, $birthDate ?: null) !== null;
            if ($match) {
                $duplicates[] = [
                    'full_name' => $fullName,
                    'event_date' => $eventDate,
                    'transaction_category' => $category,
                    'transaction_type' => $transactionType,
                ];
            }
        }

        $duplicateCount = count($duplicates);
        $duplicates = array_slice($duplicates, 0, 100);

        return response()->json([
            'success' => true,
            'total_rows' => count($records),
            'duplicates_count' => $duplicateCount,
            'duplicates' => $duplicates,
            'duplicates_truncated' => $duplicateCount > 100,
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
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize() ?? 0,
            'force_new_clients' => $request->boolean('force_direct'),
            'events_only' => $request->boolean('events_only'),
        ]);

        if (($parsed['skipped'] ?? 0) > 0) {
            try {
                Log::warning('Import prepare skipped rows', [
                    'file' => $file->getClientOriginalName(),
                    'skipped' => $parsed['skipped'],
                    'examples' => array_slice($parsed['skipped_examples'] ?? [], 0, 10),
                ]);
            } catch (\Throwable $e) {
                // ignore logging failures
            }
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'total' => count($parsed['rows']),
            'skipped' => $parsed['skipped'],
            'headers' => $parsed['headers'] ?? [],
            'skipped_examples' => $parsed['skipped_examples'] ?? [],
            'preview_rows' => array_slice($parsed['rows'] ?? [], 0, 10),
        ]);
    }

    /**
     * Process a prepared import in chunks. Each chunk inserts its own row
     * slice so the progress bar tracks real database work instead of
     * deferring every insert to the finalize step. Running counters live in
     * the session payload next to the rows.
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
        $rows = $payload['rows'] ?? [];
        $total = (int) ($payload['total'] ?? 0);
        $processed = min($offset + $limit, $total);
        $forceNewClients = (bool) ($payload['force_new_clients'] ?? false);

        $imported = (int) ($payload['processed_imported'] ?? 0);
        $failed = (int) ($payload['processed_failed'] ?? 0);
        $errorSamples = $payload['processed_errors'] ?? [];
        if (! is_array($errorSamples)) {
            $errorSamples = [];
        }

        foreach (array_slice($rows, $offset, $limit) as $index => $record) {
            try {
                $this->storeImportRow($record, (bool) ($payload['events_only'] ?? false), $forceNewClients);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                if (count($errorSamples) < 50) {
                    $errorSamples[] = [
                        'row' => $offset + $index + 1,
                        'error' => $e->getMessage(),
                        'data' => $record['full_name'] ?? 'Unknown',
                    ];
                }
                Log::error('Import row processing failed', [
                    'row_index' => $offset + $index,
                    'record' => $record,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $payload['processed_imported'] = $imported;
        $payload['processed_failed'] = $failed;
        $payload['processed_errors'] = $errorSamples;
        $payload['processed_upto'] = max((int) ($payload['processed_upto'] ?? 0), $offset + $limit);
        session()->put($this->importSessionKey($token), $payload);

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'total' => $total,
            'done' => $processed >= $total,
            'imported' => $imported,
            'failed' => $failed,
        ]);
    }

    /**
     * Finalize a prepared import: insert any rows the chunk step did not
     * cover (normally none — chunks insert their own slices), archive the
     * file, and report totals accumulated across chunks.
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
        $imported = (int) ($payload['processed_imported'] ?? 0);
        $errors = $payload['processed_errors'] ?? [];
        if (! is_array($errors)) {
            $errors = [];
        }
        $skipped = (int) ($payload['skipped'] ?? 0) + (int) ($payload['processed_failed'] ?? 0);

        try {
            // Safety net: rows past the chunk high-water mark (e.g. sessions
            // prepared before chunked inserts existed) still get imported.
            $processedUpto = (int) ($payload['processed_upto'] ?? 0);
            $forceNewClients = (bool) ($payload['force_new_clients'] ?? false);
            foreach (array_slice($rows, $processedUpto) as $index => $record) {
                try {
                    $this->storeImportRow($record, (bool) ($payload['events_only'] ?? false), $forceNewClients);
                    $imported++;
                } catch (\Throwable $e) {
                    if (count($errors) < 50) {
                        $errors[] = [
                            'row' => $processedUpto + $index + 1,
                            'error' => $e->getMessage(),
                            'data' => $record['full_name'] ?? 'Unknown',
                        ];
                    }
                    Log::error('Import row processing failed', [
                        'row_index' => $processedUpto + $index,
                        'record' => $record,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $skipped++;
                }
            }

            session()->forget($this->importSessionKey($token));

            // Archive the normalized rows so chunked (preview-flow) imports
            // appear in View Archive Files like direct imports do. Archiving
            // must never fail the import itself.
            try {
                $this->storeImportedEventArchive(
                    $rows,
                    (string) ($payload['original_filename'] ?? 'transaction-events.csv'),
                    'manual_import'
                );
            } catch (\Throwable $archiveError) {
                Log::warning('Import archive write failed', [
                    'token' => $token,
                    'error' => $archiveError->getMessage(),
                ]);
            }

            if (!empty($errors)) {
                Log::warning('Import finished with errors', [
                    'total_errors' => count($errors),
                    'first_error' => $errors[0] ?? null,
                ]);
            }

            session()->flash('success', 'Successfully imported ' . $imported . ' event(s).' . ($skipped > 0 ? ' Skipped ' . $skipped . ' invalid row(s).' : ''));

            // MySQL error strings can carry raw non-UTF-8 bytes, so never let
            // the response encoder itself become the failure (HTTP 500 with
            // "Malformed UTF-8 characters" and no usable message).
            return response()->json([
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 10), // Return first 10 errors
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('Import finalization failed', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Diagnostic endpoint to analyze an import file without importing it.
     */
    public function diagnoseImportFile(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        try {
            $file = $request->file('csv_file');
            $rows = $this->parseImportFile($file, $file->getClientOriginalName());
            
            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File appears to be empty or could not be parsed.',
                ]);
            }

            $header = array_map([$this, 'normalizeImportHeader'], $rows[0] ?? []);
            $rawHeader = $rows[0] ?? [];
            
            // Show first 5 data rows (formatted)
            $sampleRows = [];
            for ($i = 1; $i < min(6, count($rows)); $i++) {
                $row = $rows[$i];
                $mapped = [];
                foreach ($header as $index => $key) {
                    $mapped[$key] = $row[$index] ?? '';
                }
                $sampleRows[] = $mapped;
            }

            return response()->json([
                'success' => true,
                'file_name' => $file->getClientOriginalName(),
                'total_rows' => count($rows) - 1, // exclude header
                'raw_header' => $rawHeader,
                'normalized_header' => $header,
                'sample_rows' => $sampleRows,
                'has_full_name_column' => in_array('full_name', $header),
                'warnings' => $this->getImportWarnings($header, $rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function getImportWarnings(array $header, array $rows): array
    {
        $warnings = [];

        if (!in_array('full_name', $header)) {
            $warnings[] = 'Missing required "full_name" column. Found columns: ' . implode(', ', $header);
        }

        // Check first few rows for data issues
        $fullNameIndex = array_search('full_name', $header);
        if ($fullNameIndex !== false) {
            $emptyCount = 0;
            for ($i = 1; $i < min(100, count($rows)); $i++) {
                if (empty(trim($rows[$i][$fullNameIndex] ?? ''))) {
                    $emptyCount++;
                }
            }
            if ($emptyCount > 0) {
                $warnings[] = 'Found ' . $emptyCount . ' empty "full_name" values in first ' . min(99, count($rows) - 1) . ' data rows.';
            }
        }

        return $warnings;
    }

    /**
     * Download an Excel template for importing transaction events.
     */
    public function downloadTemplate()
    {
        $rows = [
            ['Full Name', 'Contact No.', 'Address', 'Age', 'Birth Date', 'Client Category', 'Transaction Category', 'Transaction Type', 'Event Date'],
            ['Juan Dela Cruz', '09123456789 / 09198765432', 'Manila, NCR', '35', '1989-05-15', 'Individual', 'Registration', 'New Registration', '2024-01-10'],
            ['Maria Santos', '09198765432', 'Quezon City, NCR', '28', '1996-08-22', 'Business', 'Renewal', 'License Renewal', '2024-02-14'],
        ];
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="1" width="28" customWidth="1"/>'
            .'<col min="2" max="2" width="38" customWidth="1" style="2"/>'
            .'<col min="3" max="9" width="24" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $index => $values) {
            $rowNumber = $index + 1;
            $sheetXml .= '<row r="'.$rowNumber.'">';
            foreach ($values as $column => $value) {
                $style = $index === 0 ? 1 : ($column === 1 ? 2 : 0);
                $sheetXml .= '<c r="'.$this->xlsxCellReference($column + 1).$rowNumber.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                    .$this->xlsxXmlEscape($value).'</t></is></c>';
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        $tempFile = tempnam(sys_get_temp_dir(), 'contacts_template_');
        $zip = new \ZipArchive;
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            return $this->downloadTemplateAsCSV();
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
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
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        $zip->close();

        return response()->download($tempFile, 'Transaction_Events_Template_'.now()->format('YmdHis').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function downloadTemplateAsCSV()
    {
        $filename = 'Transaction_Events_Template_' . now()->format('YmdHis') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() {
            $handle = fopen('php://output', 'w');
            
            // Write BOM for Excel UTF-8 detection
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // Write headers
            $headerRow = ['Full Name', 'Contact No.', 'Address', 'Age', 'Birth Date', 'Client Category', 'Transaction Category', 'Transaction Type', 'Event Date'];
            fputcsv($handle, $headerRow);
            
            // Write sample data
            $sampleData = [
                ['Juan Dela Cruz', '09123456789', 'Manila, NCR', '35', '1989-05-15', 'Individual', 'Registration', 'New Registration', '2024-01-10'],
                ['Maria Santos', '09198765432', 'Quezon City, NCR', '28', '1996-08-22', 'Business', 'Renewal', 'License Renewal', '2024-02-14'],
                ['Antonio Rodriguez', '09165432198', 'Makati, NCR', '42', '1982-03-10', 'Individual', 'Amendment', 'Information Update', '2024-03-20'],
            ];
            
            foreach ($sampleData as $row) {
                fputcsv($handle, $row);
            }
            
            fclose($handle);
        };
        
        return response()->stream($callback, 200, $headers);
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
        $forceNewClients = $request->boolean('force_direct');

        $imported = 0;
        foreach ($parsed['rows'] as $record) {
            $this->storeImportRow($record, $request->boolean('events_only'), $forceNewClients);
            $imported++;
        }

        $message = 'Successfully imported ' . $imported . ' event(s).';
        if ($parsed['skipped'] > 0) {
            $message .= ' Skipped ' . $parsed['skipped'] . ' invalid row(s).';
        }

        $this->archiveImportedFile($file, $parsed['rows']);

        return redirect()->route('transaction-events.index')->with('success', $message);
    }

    /**
     * Persist normalized import rows as a uniquely-named .csv archive and
     * register it for View Archive Files. Returns the stored filename.
     * Used by the chunked (preview-flow) import, which no longer holds the
     * original upload at finalize time.
     */
    private function storeImportedEventArchive(array $rows, string $originalFilename, string $source = 'import'): string
    {
        $timestamp = now()->format('Ymd_His');
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $originalFilename) ?: 'transaction-events.csv';
        if (! str_ends_with(strtolower($safeName), '.csv')) {
            $safeName = pathinfo($safeName, PATHINFO_FILENAME) . '.csv';
        }
        $storedName = 'transaction-events_' . $timestamp . '_' . uniqid() . '_' . $safeName;

        $columns = ['full_name', 'contact_no', 'address', 'age', 'birth_date', 'client_category', 'transaction_category', 'transaction_type', 'event_date'];

        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new \RuntimeException('Unable to build the import archive file.');
        }

        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, $columns);
        foreach ($rows as $record) {
            $record = is_array($record) ? $record : [];
            $line = [];
            foreach ($columns as $column) {
                $value = $record[$column] ?? '';
                if ($column === 'birth_date' && $value === '' && isset($record['birthdate'])) {
                    $value = $record['birthdate'];
                }
                $line[] = is_scalar($value) ? (string) $value : '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        Storage::disk('local')->put('transaction-events-archive/' . $storedName, $contents);

        ImportArchiveFile::create([
            'filename' => $storedName,
            'original_filename' => $originalFilename,
            'rows_count' => count($rows),
            'file_size' => strlen($contents),
            'source' => $source,
            'imported_by_id' => auth()->id(),
            'imported_by' => auth()->user()?->name ?? '',
            'role' => auth()->user()?->role_name ?? '',
            'imported_at' => now(),
        ]);

        return $storedName;
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

    private function storeImportRow(array $record, bool $eventsOnly, bool $forceNewClient = false): void
    {
        if (!$eventsOnly) {
            $this->createTransactionHistoryFromImportRow($record, $forceNewClient);
            return;
        }

        // Stage every valid row, including matches, for later review/transfer.
        TransactionEvent::create([
            'full_name' => trim((string) ($record['full_name'] ?? '')),
            'contact_no' => trim((string) ($record['contact_no'] ?? '')),
            'address' => trim((string) ($record['address'] ?? '')),
            'age' => isset($record['age']) && $record['age'] !== '' ? (int) $record['age'] : null,
            'birth_date' => ($record['birth_date'] ?? $record['birthdate'] ?? '') ?: null,
            'client_category' => trim((string) ($record['client_category'] ?? '')),
            'transaction_category' => trim((string) ($record['transaction_category'] ?? '')),
            'transaction_type' => trim((string) ($record['transaction_type'] ?? '')),
            'event_date' => ($record['event_date'] ?? '') ?: null,
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);
    }

    private function createTransactionHistoryFromImportRow(array $record, bool $forceNewClient = false): void
    {
        $fullName = trim((string) ($record['full_name'] ?? ''));
        $birthDate = trim((string) ($record['birth_date'] ?? $record['birthdate'] ?? ''));
        
        if (empty($fullName)) {
            throw new \RuntimeException('Full name is required but empty');
        }

        try {
            // Force Create All registers a fresh client per row (strict 1:1)
            // even when the name already exists; normal imports reuse matches.
            $client = $forceNewClient
                ? $this->createClientForImportRow($fullName, $record, $birthDate ?: null)
                : $this->findOrCreateClientForEvent($fullName, $record, $birthDate ?: null);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to find/create client: ' . $e->getMessage(), 0, $e);
        }

        try {
            // Validate and format transaction date
            $transactionDate = $record['event_date'] ?? '';
            if (!empty($transactionDate)) {
                try {
                    $parsedDate = \Carbon\Carbon::parse($transactionDate)->toDateString();
                } catch (\Throwable) {
                    throw new \RuntimeException('Invalid event_date format: ' . $transactionDate);
                }
            } else {
                $parsedDate = now()->toDateString();
            }

            $transactionHistory = TransactionHistory::create([
                'client_id' => $client->client_id,
                'client_category' => trim((string) ($record['client_category'] ?? '')) ?: null,
                'transaction_id' => $this->nextTransactionIdForClient($client->client_id),
                'transaction_date' => $parsedDate,
                'category' => trim((string) ($record['transaction_category'] ?? '')),
                'type' => trim((string) ($record['transaction_type'] ?? '')),
                'events_transaction_type' => trim((string) ($record['transaction_type'] ?? '')) ?: null,
                'status' => 'Approved',
                'source' => 'import',
                'description' => 'Imported from event CSV/XLSX file.',
            ]);

            $transactionHistory->touch();

            // Mirror manual transaction creation: every imported row lands
            // in Event Records as a transferred event linked to its history,
            // so imports are visible exactly like transferred transactions.
            TransactionEvent::create([
                'full_name' => $fullName,
                'contact_no' => trim((string) ($record['contact_no'] ?? '')),
                'address' => trim((string) ($record['address'] ?? '')),
                'age' => isset($record['age']) && $record['age'] !== '' ? (int) $record['age'] : null,
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'client_category' => trim((string) ($record['client_category'] ?? '')),
                'transaction_category' => trim((string) ($record['transaction_category'] ?? '')),
                'transaction_type' => trim((string) ($record['transaction_type'] ?? '')),
                'event_date' => $parsedDate,
                'transferred_at' => now(),
                'transferred_transaction_id' => $transactionHistory->id,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create transaction history: ' . $e->getMessage(), 0, $e);
        }
    }

    private function findOrCreateClientForEvent(string $fullName, array $record, ?string $birthDate = null): Client
    {
        return $this->findClientForImport($fullName, $birthDate)
            ?? $this->createClientForImportRow($fullName, $record, $birthDate);
    }

    private function findClientForImport(string $fullName, ?string $birthDate = null): ?Client
    {
        $trimmed = trim($fullName);
        if ($trimmed === '') {
            throw new \RuntimeException('Client full name is required.');
        }

        $name = $this->splitImportFullName($trimmed);
        // Matching is lowercase; creation keeps the original case.
        $firstName = strtolower($name['first']);
        $lastName = strtolower($name['last']);
        $middleName = strtolower($name['middle']);

        // Portable name matching (no || or CONCAT, so it behaves the same
        // on MySQL and SQLite): exact full-name triple, or first + last with
        // any middle. A first-name-only fallback additionally requires a
        // birth date — without one it merges distinct people, so those rows
        // register fresh clients instead.
        $query = Client::query()->where(function ($sub) use ($firstName, $middleName, $lastName, $birthDate) {
            $nameMatch = function ($nameQuery) use ($firstName, $middleName, $lastName) {
                $nameQuery->where(function ($triple) use ($firstName, $middleName, $lastName) {
                    $triple->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
                        ->whereRaw("LOWER(TRIM(COALESCE(middle_name, ''))) = ?", [$middleName])
                        ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName]);
                })->orWhere(function ($firstLast) use ($firstName, $lastName) {
                    $firstLast->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
                        ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName]);
                });
            };

            if ($birthDate !== null && $birthDate !== '') {
                $sub->whereDate('birth_date', $birthDate);
                $sub->where($nameMatch);

                if ($firstName !== '') {
                    $sub->orWhere(function ($firstQuery) use ($firstName, $birthDate) {
                        $firstQuery->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
                            ->whereDate('birth_date', $birthDate);
                    });
                }
            } else {
                $sub->where($nameMatch);
            }
        });

        return $query->first();
    }

    /**
     * Split an import full name into original-case parts (suffix-aware).
     *
     * @return array{first: string, middle: string, last: string, suffix: string}
     */
    private function splitImportFullName(string $fullName): array
    {
        return \App\Support\ImportName::split($fullName);
    }

    /**
     * Always register a fresh client for one import row (strict 1:1), even
     * when the same name already exists. Used by Force Create All.
     */
    private function createClientForImportRow(string $fullName, array $record, ?string $birthDate = null): Client
    {
        $trimmed = trim($fullName);
        if ($trimmed === '') {
            throw new \RuntimeException('Client full name is required.');
        }

        $name = $this->splitImportFullName($trimmed);

        $clientData = [
            'first_name' => $name['first'],
            'middle_name' => $name['middle'] !== '' ? $name['middle'] : null,
            'last_name' => $name['last'],
            'suffix' => $name['suffix'] !== '' ? $name['suffix'] : null,
            'birth_date' => $birthDate !== null && $birthDate !== '' ? $birthDate : null,
            'sector' => trim((string) ($record['client_category'] ?? '')),
            'contact' => trim((string) ($record['contact_no'] ?? '')),
            'address' => trim((string) ($record['address'] ?? '')),
            'age' => isset($record['age']) && $record['age'] !== '' ? (int) $record['age'] : null,
        ];

        try {
            return Client::createWithGeneratedId($clientData);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create client with data: ' . json_encode($clientData) . '. Error: ' . $e->getMessage(), 0, $e);
        }
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

        $client = $this->findClientForImport($event->full_name, $event->birth_date?->format('Y-m-d'));
        if ($client === null) {
            return redirect()->route('transaction-events.index')->with('error',
                'No matching client found in the Client List. This record remains in Import Events.');
        }

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
    public function undoTransfer(Request $request, TransactionEvent $event)
    {
        $redirect = in_array($request->query('duplicate_tab'), ['exact', 'likely', 'full_name'], true)
            ? redirect()->route('transaction-events.records-duplicates', $request->query())
            : redirect()->back(302, [], route('transaction-events.records'));

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
            return $redirect->with('error', 'No transferred transaction was found for this event.');
        }

        if (TransactionRequirement::query()->where('transaction_id', $transaction->id)->exists()) {
            return $redirect->with('error', 'This transfer cannot be undone because the transaction has uploaded requirements.');
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

        return $redirect->with('success', 'Transfer undone. Transaction ' . $linkedTransactionId . ' was removed and the event is pending again.');
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

        $client = $this->findClientForImport($event->full_name, $event->birth_date?->format('Y-m-d'));
        if ($client === null) {
            return response()->json(['success' => false, 'created_client' => false,
                'message' => 'No matching client found in the Client List. This record remains in Import Events.'], 422);
        }

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
            'created_client' => false,
            'transaction_id' => $history->transaction_id,
        ]);
    }

    /**
     * Transfer multiple selected events.
     */
    public function transferSelected(Request $request)
    {
        $request->validate([
            'event_ids' => ['required_without:select_all', 'array'],
            'event_ids.*' => ['integer', 'exists:transaction_events,id'],
            'select_all' => ['sometimes', 'accepted'],
        ]);

        $transferred = 0;
        $skipped = 0;
        foreach ($this->resolveBulkTransferEvents($request) as $event) {
            $result = $this->transferSinglePendingEvent($event);
            $result['success'] ? $transferred++ : $skipped++;
        }

        return redirect()->back()->with($transferred > 0 ? 'success' : 'error',
            "Created {$transferred} new transaction(s). Skipped {$skipped} event(s) already transferred or no longer available.");
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

        if ($request->boolean('duplicate_names')) {
            $query->whereIn('full_name', $this->duplicateFullNamesList());
        } elseif ($request->boolean('exclude_duplicates')) {
            $query->whereNotIn('full_name', $this->duplicateFullNamesList());
        }

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

        // Duplicates (same full name, client category, transaction category,
        // transaction type and event date) are excluded from bulk Select All
        // and must be resolved in View Duplicate Records instead.
        $duplicateKeys = array_flip($this->duplicateRecordKeys());

        $undone = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $event = TransactionEvent::find($id);
            if (! $event || $event->transferred_at === null) {
                $skipped++;
                continue;
            }

            if (isset($duplicateKeys[$this->duplicateRecordKey($event)])) {
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

        $excludeDuplicates = $request->boolean('exclude_duplicates');
        $ids = $this->resolveUndoSelectedIds($request, $excludeDuplicates);

        return response()->json([
            'success' => true,
            'total' => count($ids),
            'ids' => $ids,
            'excluded_duplicates' => $excludeDuplicates,
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

    public function removedDuplicates()
    {
        $events = TransactionEvent::where('not_duplicate', true)
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('pages.transaction_events.removedDuplicates', compact('events'));
    }

    /**
     * Resolve the pending events targeted by a bulk delete request: either
     * explicitly checked event_ids or the whole filtered select-all
     * population (same scope as Transfer Selected). Transferred rows can
     * never match and are therefore never deleted.
     */
    private function resolveDeleteSelectedEvents(Request $request): Collection
    {
        if ($request->boolean('select_all')) {
            $query = TransactionEvent::query()
                ->whereNull('transferred_at')
                ->where('not_duplicate', false);

            $this->applyEventListFilters($query, $request);

            if ($request->boolean('duplicate_names')) {
                $query->whereIn('full_name', $this->duplicateFullNamesList());
            } elseif ($request->boolean('exclude_duplicates')) {
                $query->whereNotIn('full_name', $this->duplicateFullNamesList());
            }

            return $query->get();
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('event_ids', []))));

        if ($ids === []) {
            return collect();
        }

        return TransactionEvent::query()
            ->whereIn('id', $ids)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();
    }

    public function destroy(TransactionEvent $event)
    {
        if (auth()->user()?->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        if ($event->transferred_at !== null) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #' . $event->id . ' is already approved/transferred and cannot be deleted.');
        }

        $fullName = $event->full_name;
        $eventId = $event->id;

        $event->delete();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'event_deleted',
            'description' => "Deleted transaction event #{$eventId} ({$fullName}) from the Import Events list.",
            'subject_type' => 'TransactionEvent',
            'subject_id' => $eventId,
            'properties' => ['event_id' => $eventId, 'full_name' => $fullName],
        ]);

        return redirect()->route('transaction-events.index')
            ->with('success', "Event #{$eventId} ({$fullName}) deleted successfully.");
    }

    /**
     * Bulk-delete pending events: either explicitly checked event_ids or the
     * whole filtered select-all population (same scope as Transfer Selected).
     * Transferred events can never match and are therefore never deleted.
     */
    public function destroySelected(Request $request)
    {
        if (auth()->user()?->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        if (! feature_allowed('Delete Event')) {
            abort(404);
        }

        $events = $this->resolveDeleteSelectedEvents($request);

        if ($events->isEmpty()) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'No matching pending events to delete.');
        }

        $ids = $events->pluck('id')->all();
        $count = count($ids);

        TransactionEvent::query()->whereIn('id', $ids)->delete();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_bulk_deleted',
            'description' => "Bulk-deleted {$count} pending transaction event(s) from the Import Events list.",
            'subject_type' => 'TransactionEvent',
            'subject_id' => null,
            'properties' => [
                'count' => $count,
                'select_all' => $request->boolean('select_all'),
                'filters' => $request->only([
                    'search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to',
                    'event_date_from', 'event_date_to',
                    'duplicate_names', 'client_category', 'transaction_category', 'transaction_type',
                ]),
            ],
        ]);

        return redirect()->route('transaction-events.index')
            ->with('success', "Deleted {$count} pending event(s). This cannot be undone.");
    }

    private const TRANSFER_SESSION_DIR = 'transfer-sessions';

    /**
     * Resolve the pending events targeted by a bulk transfer request: either
     * explicitly checked event_ids or the whole filtered select-all
     * population across pages.
     */
    private function resolveBulkTransferEvents(Request $request): Collection
    {
        if ($request->boolean('select_all')) {
            $query = TransactionEvent::query()
                ->whereNull('transferred_at')
                ->where('not_duplicate', false);

            $this->applyEventListFilters($query, $request);

            if ($request->boolean('duplicate_names')) {
                $query->whereIn('full_name', $this->duplicateFullNamesList());
            } elseif ($request->boolean('exclude_duplicates')) {
                $query->whereNotIn('full_name', $this->duplicateFullNamesList());
            }

            return $query->orderByDesc('id')->get();
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('event_ids', []))));

        if ($ids === []) {
            return collect();
        }

        return TransactionEvent::query()
            ->whereIn('id', $ids)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();
    }

    /**
     * Transfer one pending event into a client history record.
     *
     * @return array{success: bool, created_client: bool}
     */
    private function transferSinglePendingEvent(TransactionEvent $event): array
    {
        return DB::transaction(function () use ($event) {
            $event = TransactionEvent::whereKey($event->id)->lockForUpdate()->first();
            if ($event === null || $event->transferred_at !== null) {
                return ['success' => false, 'created_client' => false];
            }

            $birthDate = $event->birth_date?->format('Y-m-d');
            $client = $this->findClientForImport($event->full_name, $birthDate);
            $createdClient = $client === null;
            if ($createdClient) {
                $client = $this->createClientForImportRow($event->full_name, $event->getAttributes(), $birthDate);
            }

            // Every selected event gets a new history entry, even for an existing client.
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

            return ['success' => true, 'created_client' => $createdClient];
        });
    }

    private function saveTransferSession(string $token, array $data): void
    {
        Storage::disk('local')->makeDirectory(self::TRANSFER_SESSION_DIR);
        Storage::disk('local')->put(
            self::TRANSFER_SESSION_DIR . '/' . $token . '.json',
            json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE)
        );
    }

    private function loadTransferSession(string $token): ?array
    {
        $path = self::TRANSFER_SESSION_DIR . '/' . $token . '.json';

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function cleanupStaleTransferSessions(): void
    {
        try {
            foreach (Storage::disk('local')->files(self::TRANSFER_SESSION_DIR) as $file) {
                if (now()->timestamp - Storage::disk('local')->lastModified($file) > 7200) {
                    Storage::disk('local')->delete($file);
                }
            }
        } catch (\Throwable) {
            // Session cleanup is best-effort only.
        }
    }

    /**
     * Chunked "Transfer Selected" step 1: resolve the target ids once and
     * cache them so the browser can transfer in slices with live progress.
     */
    public function prepareTransferSelected(Request $request)
    {
        if (auth()->user()?->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        $events = $this->resolveBulkTransferEvents($request);

        if ($events->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Selected transaction events are already transferred or no longer available.',
            ], 422);
        }

        $token = md5(uniqid((string) auth()->id(), true));
        $this->cleanupStaleTransferSessions();
        $this->saveTransferSession($token, [
            'ids' => $events->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'successCount' => 0,
            'skippedCount' => 0,
            'createdClients' => 0,
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'total' => $events->count(),
        ]);
    }

    /**
     * Chunked "Transfer Selected" step 2: transfer one slice of ids.
     */
    public function processTransferChunk(Request $request)
    {
        if (auth()->user()?->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate([
            'token' => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        $limit = min(max((int) $request->input('limit', 200), 1), 1000);
        $session = $this->loadTransferSession($request->input('token'));

        if ($session === null) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer session not found. Please start the transfer again.',
            ], 404);
        }

        $ids = $session['ids'] ?? [];
        $offset = (int) $request->input('offset');
        $slice = array_slice($ids, $offset, $limit);

        if ($slice !== []) {
            $events = TransactionEvent::query()
                ->whereIn('id', $slice)
                ->whereNull('transferred_at')
                ->where('not_duplicate', false)
                ->get();

            foreach ($events as $event) {
                $result = $this->transferSinglePendingEvent($event);

                if ($result['success']) {
                    $session['successCount'] = ($session['successCount'] ?? 0) + 1;

                    if ($result['created_client']) {
                        $session['createdClients'] = ($session['createdClients'] ?? 0) + 1;
                    }
                } else {
                    $session['skippedCount'] = ($session['skippedCount'] ?? 0) + 1;
                }
            }

            // Ids that no longer resolve (already transferred/deleted) count as skipped.
            $missing = count($slice) - $events->count();
            if ($missing > 0) {
                $session['skippedCount'] = ($session['skippedCount'] ?? 0) + $missing;
            }

            $this->saveTransferSession($request->input('token'), $session);
        }

        $processed = $offset + count($slice);

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'total' => count($ids),
            'done' => $processed >= count($ids),
        ]);
    }

    /**
     * Chunked "Transfer Selected" step 3: summarize and clean up the session.
     */
    public function finishTransferSelected(Request $request)
    {
        if (auth()->user()?->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $session = $this->loadTransferSession($request->input('token'));

        if ($session === null) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer session not found. Please start the transfer again.',
            ], 404);
        }

        $successCount = (int) ($session['successCount'] ?? 0);
        $skippedCount = (int) ($session['skippedCount'] ?? 0);
        $createdClients = (int) ($session['createdClients'] ?? 0);

        Storage::disk('local')->delete(self::TRANSFER_SESSION_DIR . '/' . $request->input('token') . '.json');

        $message = "Transferred {$successCount} event(s).";
        if ($createdClients > 0) {
            $message .= " Auto-created {$createdClients} new client(s).";
        }
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} event(s) already transferred or no longer available.";
        }

        $request->session()->flash($successCount > 0 ? 'success' : 'error', $message);

        return response()->json([
            'success' => true,
            'type' => $successCount > 0 ? 'success' : 'error',
            'message' => $message,
            'successCount' => $successCount,
            'skippedCount' => $skippedCount,
            'createdClients' => $createdClients,
            'redirect' => $successCount > 0
                ? route('transaction-events.records')
                : route('transaction-events.index'),
        ]);
    }
}
