<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionEventsController extends Controller
{
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

        if ($clientCategory = $request->input('client_category')) {
            $query->where('client_category', $clientCategory);
        }

        if ($txCategory = $request->input('transaction_category')) {
            $query->where('transaction_category', $txCategory);
        }

        if ($txType = $request->input('transaction_type')) {
            $query->where('transaction_type', $txType);
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

    public function records(Request $request)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }

        $query = TransactionEvent::whereNotNull('transferred_at');

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

        if ($type = $request->input('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $events = $query->with('transferredTransaction:id,transaction_id')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // Distinct values (from transferred records) for the dropdown filters.
        $categories = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_category')->distinct()
            ->pluck('transaction_category')->filter()->sort()->values();
        $types = TransactionEvent::whereNotNull('transferred_at')
            ->select('transaction_type')->distinct()
            ->pluck('transaction_type')->filter()->sort()->values();

        return view('pages.transaction_events.eventRecords', compact('events', 'categories', 'types'));
    }

    /**
     * Duplicate review for transferred events (Events - Records).
     */
    public function recordsDuplicates()
    {
        if (!feature_allowed('Events Records Duplicates')) {
            abort(404);
        }

        $base = fn () => TransactionEvent::whereNotNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '');

        // ----- Exact: same name + real birth date -----
        $exactKeys = $base()
            ->selectRaw("CONCAT(LOWER(TRIM(full_name)), '|', birth_date) as keyval")
            ->whereNotNull('birth_date')
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval');

        $exactGroups = collect();
        if ($exactKeys->isNotEmpty()) {
            $exactGroups = $base()
                ->whereIn(DB::raw("CONCAT(LOWER(TRIM(full_name)), '|', birth_date)"), $exactKeys)
                ->with('transferredTransaction:id,transaction_id')
                ->get()
                ->groupBy(fn ($e) => strtolower(trim($e->full_name)) . '|' . optional($e->birth_date)->format('Y-m-d'))
                ->filter(fn ($g) => $g->count() > 1)
                ->map(fn ($items) => $this->eventGroupPayload($items))
                ->values();
        }

        // ----- Likely: same name, missing or year-only birth dates -----
        $likelyGroups = $base()
            ->with('transferredTransaction:id,transaction_id')
            ->get()
            ->groupBy(fn ($e) => strtolower(trim($e->full_name)))
            ->filter(function ($items) {
                if ($items->count() < 2) {
                    return false;
                }
                $dates = $items->pluck('birth_date')->filter()->map(fn ($d) => $d->format('Y-m-d'));
                if ($dates->count() === $items->count() && $dates->unique()->count() === 1) {
                    return false; // fully identical known dates belong to Exact
                }
                return true;
            })
            ->map(fn ($items) => $this->eventGroupPayload($items))
            ->values();

        // ----- Similar spelling: SOUNDEX of the full name -----
        $similarKeys = $base()
            ->selectRaw("SOUNDEX(LOWER(TRIM(full_name))) as keyval")
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval');

        $similarGroups = collect();
        if ($similarKeys->isNotEmpty()) {
            $similarGroups = $base()
                ->whereIn(DB::raw('SOUNDEX(LOWER(TRIM(full_name)))'), $similarKeys)
                ->with('transferredTransaction:id,transaction_id')
                ->get()
                ->groupBy(fn ($e) => (string) soundex(strtolower(trim($e->full_name))))
                ->filter(fn ($g) => $g->count() > 1)
                ->map(fn ($items) => $this->eventGroupPayload($items))
                ->values();
        }

        return view('pages.transaction_events.recordsDuplicates',
            compact('exactGroups', 'likelyGroups', 'similarGroups'));
    }

    public function duplicateReview()
    {
        $exactGroups = $this->findEventExactDuplicates();
        $likelyGroups = $this->findEventLikelyDuplicates();
        $similarGroups = $this->findEventSimilarSpellingDuplicates();

        $notDuplicates = TransactionEvent::where('not_duplicate', true)
            ->whereNull('transferred_at')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('pages.transaction_events.duplicateReview', compact('exactGroups', 'likelyGroups', 'similarGroups', 'notDuplicates'));
    }

    public function removedDuplicates()
    {
        $events = TransactionEvent::where('not_duplicate', true)
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('pages.transaction_events.removedDuplicates', compact('events'));
    }

    public function markNotDuplicate(TransactionEvent $event)
    {
        $authUser = auth()->user();
        if ($authUser->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        $event->update(['not_duplicate' => true]);

        ActivityLog::create([
            'user_id' => $authUser->id,
            'action' => 'event_marked_not_duplicate',
            'description' => "Removed transaction event #{$event->id} ({$event->full_name}) as a duplicate.",
            'subject_type' => 'TransactionEvent',
            'subject_id' => $event->id,
            'properties' => json_encode(['event_id' => $event->id, 'full_name' => $event->full_name], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return redirect()->route('transaction-events.removed-duplicates')
            ->with('success', "Event #{$event->id} ({$event->full_name}) removed as duplicate.");
    }

    public function resetNotDuplicate(TransactionEvent $event)
    {
        $authUser = auth()->user();
        if ($authUser->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        $event->update(['not_duplicate' => false]);

        ActivityLog::create([
            'user_id' => $authUser->id,
            'action' => 'event_restored_to_duplicate_review',
            'description' => "Restored transaction event #{$event->id} ({$event->full_name}) to duplicate review.",
            'subject_type' => 'TransactionEvent',
            'subject_id' => $event->id,
            'properties' => json_encode(['event_id' => $event->id, 'full_name' => $event->full_name], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return redirect()->back()
            ->with('success', "Event #{$event->id} ({$event->full_name}) restored to duplicate review.");
    }

    private function findEventExactDuplicates(): Collection
    {
        // A true exact match needs a real birth date on both records.
        // Pairs with missing birth dates belong to Likely Match instead.
        $keys = TransactionEvent::query()
            ->selectRaw("CONCAT(LOWER(TRIM(full_name)), '|', birth_date) as keyval")
            ->whereNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->whereNotNull('birth_date')
            ->where('not_duplicate', false)
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        return $this->groupEventsByKey($keys, "CONCAT(LOWER(TRIM(full_name)), '|', birth_date)");
    }

    private function findEventLikelyDuplicates(): Collection
    {
        $nameKeys = TransactionEvent::query()
            ->selectRaw('LOWER(TRIM(full_name)) as keyval')
            ->whereNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        if (empty($nameKeys)) {
            return collect();
        }

        $events = TransactionEvent::whereIn(DB::raw('LOWER(TRIM(full_name))'), $nameKeys)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();

        return $events->groupBy(fn ($event) => strtolower(trim($event->full_name)))
            ->filter(function ($items) {
                if ($items->count() < 2) {
                    return false;
                }

                $birthDates = $items->pluck('birth_date')->filter()->map(fn ($d) => $d->format('Y-m-d'));

                // Fully identical birth dates belong to the exact tab.
                if ($birthDates->count() === $items->count() && $birthDates->unique()->count() === 1) {
                    return false;
                }

                // Some dates missing -> likely duplicate.
                if ($birthDates->count() < $items->count()) {
                    return true;
                }

                // Only year-level match -> likely duplicate.
                $years = $birthDates->map(fn ($d) => substr($d, 0, 4))->unique();

                return $years->count() === 1;
            })
            ->map(fn ($items) => $this->eventGroupPayload($items))
            ->values();
    }

    private function findEventSimilarSpellingDuplicates(): Collection
    {
        $keys = TransactionEvent::query()
            ->selectRaw("COALESCE(SOUNDEX(LOWER(TRIM(full_name))),'') as keyval")
            ->whereNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        if (empty($keys)) {
            return collect();
        }

        $events = TransactionEvent::whereIn(DB::raw("COALESCE(SOUNDEX(LOWER(TRIM(full_name))),'')"), $keys)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();

        return $events->groupBy(fn ($event) => (string) soundex(strtolower(trim($event->full_name))))
            ->filter(function ($items) {
                if ($items->count() < 2) {
                    return false;
                }

                $distinctNames = $items->map(fn ($event) => strtolower(trim($event->full_name)))->unique();

                return $distinctNames->count() > 1;
            })
            ->map(fn ($items) => $this->eventGroupPayload($items))
            ->values();
    }

    private function groupEventsByKey(array $keys, string $keyExpr): Collection
    {
        if (empty($keys)) {
            return collect();
        }

        $events = TransactionEvent::whereIn(DB::raw($keyExpr), $keys)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();

        return $events->groupBy(function ($event) {
            return strtolower(trim($event->full_name)).'|'.($event->birth_date?->format('Y-m-d') ?? '');
        })->map(fn ($items) => $this->eventGroupPayload($items))->values();
    }

    private function eventGroupPayload($items): array
    {
        return [
            'total' => $items->count(),
            'events' => $items,
            'created_at' => $items->min('created_at'),
        ];
    }

    public function archives()
    {
        if (!feature_allowed('View Archive Files')) {
            abort(404);
        }

        $directory = 'transaction-events-archive';
        $files = Storage::disk('local')->files($directory);

        $archiveFiles = collect($files)
            ->filter(fn ($path) => str_ends_with(strtolower($path), '.csv'))
            ->map(function ($path) {
                $filename = basename($path);
                $uploadedAt = $this->extractArchiveUploadedAt($filename);
                $importedBy = null;

                $metaPath = $path.'.importer.json';
                if (Storage::disk('local')->exists($metaPath)) {
                    $meta = json_decode(Storage::disk('local')->get($metaPath), true);
                    if (is_array($meta) && ! empty($meta['imported_by'])) {
                        $importedBy = $meta;
                    }
                }

                return [
                    'name' => $filename,
                    'path' => $path,
                    'download_url' => route('transaction-events.archives.download', ['filename' => $filename]),
                    'size' => Storage::disk('local')->size($path),
                    'modified_at' => Storage::disk('local')->lastModified($path),
                    'uploaded_at' => $uploadedAt ?? Storage::disk('local')->lastModified($path),
                    'imported_by' => $importedBy,
                ];
            })
            ->sortByDesc('uploaded_at')
            ->values();

        return view('pages.transaction_events.transactionEventArchives', ['files' => $archiveFiles]);
    }

    private function extractArchiveUploadedAt(string $filename): ?int
    {
        if (preg_match('/transaction-events_(\d{8})_(\d{6})/i', $filename, $matches)) {
            $date = $matches[1];
            $time = $matches[2];

            $timestamp = \DateTime::createFromFormat('YmdHis', $date.$time, new \DateTimeZone('UTC'));
            if ($timestamp !== false) {
                return $timestamp->getTimestamp();
            }
        }

        return null;
    }

    public function downloadArchive(string $filename)
    {
        $path = 'transaction-events-archive/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path, $filename);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $this->parseCsv($request->file('csv_file'));

        if (! empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        return response()->json([
            'success' => true,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'skipped' => $result['skipped'],
            'skipped_rows' => $result['skipped_rows'],
        ]);
    }

    /**
     * Check how many rows of the uploaded CSV already exist in transaction
     * history (same client + category/type + event date when provided).
     */
    public function importDuplicatesCheck(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $this->parseCsv($request->file('csv_file'));

        if (!empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        $duplicates = [];

        foreach ($result['rows'] as $row) {
            $nameParts = $this->splitFullName($row['full_name']);

            if (empty($nameParts['first_name']) || empty($nameParts['last_name'])) {
                continue;
            }

            $clientIds = Client::query()
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower(trim($nameParts['first_name']))])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower(trim($nameParts['last_name']))])
                ->pluck('client_id');

            if ($clientIds->isEmpty()) {
                continue;
            }

            $history = TransactionHistory::whereIn('client_id', $clientIds)
                ->where('category', TransactionHistory::normalizeCategory($row['transaction_category'] ?? ''))
                ->where('type', $row['transaction_type'] ?? '');

            if (!empty($row['event_date'])) {
                $history->whereDate('transaction_date', $row['event_date']);
            }

            if ($history->exists()) {
                $duplicates[] = [
                    'full_name' => $row['full_name'],
                    'event_date' => $row['event_date'] ?? '',
                    'transaction_category' => $row['transaction_category'] ?? '',
                    'transaction_type' => $row['transaction_type'] ?? '',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total_rows' => count($result['rows']),
            'duplicates_count' => count($duplicates),
            'duplicates' => $duplicates,
        ]);
    }

    public function downloadTemplate()
    {        $headers = [
            'full_name',
            'contact_no',
            'address',
            'age',
            'birth_date',
            'event_date',
            'client_category',
            'transaction_category',
            'transaction_type',
        ];

        $widths = [28, 16, 35, 8, 14, 14, 22, 24, 24];
        $exampleRow = [
            'Juan Dela Cruz',
            '09171234567',
            'Brgy. Poblacion, City Hall',
            '45',
            '1981-03-15',
            now()->format('Y-m-d'),
            'INDIGENT',
            'CARAVAN',
            'FOOD ASSISTANCE',
        ];

        $rows = '';

        foreach ([$headers, $exampleRow] as $index => $row) {
            $style = $index === 0 ? ' s="1"' : '';
            $cells = '';
            foreach ($row as $i => $cell) {
                $ref = $this->excelColumnName($i + 1).($index + 1);
                $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$value}</t></is></c>";
            }
            $rows .= '<row r="'.($index + 1)."\">{$cells}</row>";
        }

        $cols = '';
        foreach ($widths as $i => $width) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1)."\" width=\"{$width}\" customWidth=\"1\"/>";
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            ."<cols>{$cols}</cols>"
            ."<sheetData>{$rows}</sheetData>"
            .'</worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Import Template" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
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
            .'</styleSheet>';

        $tempPath = tempnam(sys_get_temp_dir(), 'template_').'.xlsx';

        $zip = new \ZipArchive;
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);

            return back()->with('error', 'Unable to generate the Excel template. Please try again.');
        }

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();

        return response()->download($tempPath, 'import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $this->parseCsv($request->file('csv_file'));

        if (! empty($result['errors'])) {
            return back()->withErrors(['csv_file' => $result['errors'][0]]);
        }

        $rows = $result['rows'];
        $archiveFile = '';
        $imported = count($rows);
        $hasDuplicateRows = collect($rows)->contains(fn ($row) => ! empty($row['duplicate']));

        if ($imported > 0) {
            if ($hasDuplicateRows) {
                $this->storeImportedEventsOnly($rows);
            } else {
                $this->processImportedEvents($rows);
            }
            $archiveFile = $this->storeImportedEventArchive($rows, $request->file('csv_file')->getClientOriginalName());
        }

        $skipped = $result['skipped'];
        $message = $hasDuplicateRows
            ? "Imported {$imported} event(s) to the event list because duplicate rows were found in the selected file."
            : "Successfully imported {$imported} event(s).";

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        if (! empty($archiveFile)) {
            $message .= " Archived CSV as {$archiveFile}.";
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_imported',
            'description' => "Imported {$imported} transaction event(s) from CSV".($request->file('csv_file')->getClientOriginalName() ? ' ('.$request->file('csv_file')->getClientOriginalName().')' : '').'.',
            'subject_type' => 'TransactionEvent',
            'subject_id' => null,
            'properties' => json_encode([
                'imported' => $imported,
                'skipped' => $skipped,
                'file_name' => $request->file('csv_file')->getClientOriginalName(),
            ], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        // Direct imports (no duplicate rows) are transferred immediately,
        // so land the user on Events - Records where those records live.
        if (! $hasDuplicateRows && $imported > 0) {
            return redirect()->route('transaction-events.records')->with('success', $message);
        }

        return redirect()->route('transaction-events.index')->with('success', $message);
    }

    // ----------------------------------------------------------------------
    // Chunked import (progress-bar friendly). The file is parsed once, rows
    // are cached to a temp session file, then processed in slices so the
    // browser can show a live percentage while importing.
    // ----------------------------------------------------------------------

    private const IMPORT_SESSION_DIR = 'import-sessions';

    public function prepareImport(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $this->parseCsv($request->file('csv_file'));

        if (! empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        $rows = $result['rows'];
        $hasDuplicateRows = collect($rows)->contains(fn ($row) => ! empty($row['duplicate']));

        $token = md5(uniqid((string) auth()->id(), true));
        $this->cleanupStaleImportSessions();

        Storage::disk('local')->makeDirectory(self::IMPORT_SESSION_DIR);
        Storage::disk('local')->put(
            self::IMPORT_SESSION_DIR.'/'.$token.'.json',
            json_encode([
                'mode' => $hasDuplicateRows ? 'events_only' : 'direct',
                'rows' => $rows,
                'original_filename' => $request->file('csv_file')->getClientOriginalName(),
                'skipped' => $result['skipped'],
            ], JSON_INVALID_UTF8_SUBSTITUTE)
        );

        return response()->json([
            'success' => true,
            'token' => $token,
            'total' => count($rows),
            'skipped' => $result['skipped'],
            'has_duplicates' => $hasDuplicateRows,
        ]);
    }

    public function processImportChunk(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'offset' => 'required|integer|min:0',
        ]);

        $limit = min(max((int) $request->input('limit', 200), 1), 1000);
        $sessionData = $this->loadImportSession($request->input('token'));

        if ($sessionData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Import session not found. Please start the import again.',
            ], 404);
        }

        $rows = $sessionData['rows'];
        $offset = (int) $request->input('offset');
        $slice = array_slice($rows, $offset, $limit);

        if ($sessionData['mode'] === 'events_only') {
            $this->storeImportedEventsOnly($slice);
        } else {
            $this->processImportedEvents($slice);
        }

        $processed = $offset + count($slice);

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'total' => count($rows),
            'done' => $processed >= count($rows),
        ]);
    }

    public function finishImport(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $sessionData = $this->loadImportSession($request->input('token'));

        if ($sessionData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Import session not found. Please start the import again.',
            ], 404);
        }

        $rows = $sessionData['rows'];
        $imported = count($rows);
        $skipped = $sessionData['skipped'];
        $hasDuplicateRows = $sessionData['mode'] === 'events_only';
        $archiveFile = '';

        if ($imported > 0) {
            $archiveFile = $this->storeImportedEventArchive($rows, $sessionData['original_filename']);
        }

        $message = $hasDuplicateRows
            ? "Imported {$imported} event(s) to the event list because duplicate rows were found in the selected file."
            : "Successfully imported {$imported} event(s).";

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        if (! empty($archiveFile)) {
            $message .= " Archived CSV as {$archiveFile}.";
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_imported',
            'description' => "Imported {$imported} transaction event(s) from CSV".($sessionData['original_filename'] ? ' ('.$sessionData['original_filename'].')' : '').'.',
            'subject_type' => 'TransactionEvent',
            'subject_id' => null,
            'properties' => json_encode([
                'imported' => $imported,
                'skipped' => $skipped,
                'file_name' => $sessionData['original_filename'],
            ], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        Storage::disk('local')->delete(self::IMPORT_SESSION_DIR.'/'.$request->input('token').'.json');

        TransactionHistory::flushDashboardCache();

        session()->flash('success', $message);

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => $message,
        ]);
    }

    private function loadImportSession(string $token): ?array
    {
        $path = self::IMPORT_SESSION_DIR.'/'.$token.'.json';

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $data = json_decode(Storage::disk('local')->get($path), true);

        return is_array($data) ? $data : null;
    }

    private function cleanupStaleImportSessions(): void
    {
        foreach (Storage::disk('local')->files(self::IMPORT_SESSION_DIR) as $file) {
            if (Storage::disk('local')->lastModified($file) < now()->subHours(2)->timestamp) {
                Storage::disk('local')->delete($file);
            }
        }
    }

    public function transfer(TransactionEvent $event)
    {
        if (! is_null($event->transferred_at)) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #'.$event->id.' is already approved/transferred.');
        }

        if ($event->not_duplicate) {
            return redirect()->route('transaction-events.duplicate-review')
                ->with('error', 'Event #'.$event->id.' is marked as not a duplicate and cannot be transferred.');
        }

        $nameParts = $this->splitFullName($event->full_name);

        $client = Client::whereRaw('LOWER(first_name) = ?', [strtolower($nameParts['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($nameParts['last_name'])])
            ->first();

        if (! $client) {
            $client = $this->createClientFromImportedEvent([
                'full_name' => $event->full_name,
                'age' => $event->age,
                'contact_no' => $event->contact_no,
                'address' => $event->address,
                'birth_date' => $event->birth_date?->format('Y-m-d'),
                'client_category' => $event->client_category,
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'client_created',
                'description' => 'Auto-created client '.$client->client_id.' ('.trim($event->full_name).') during event transfer.',
                'subject_type' => 'Client',
                'subject_id' => $client->id,
                'properties' => json_encode(['source' => 'transaction-event-transfer', 'event_id' => $event->id]),
            ]);
        }

        if (empty($event->transaction_category) && empty($event->transaction_type)) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #'.$event->id.' has no transaction category or type to transfer.');
        }

        $transactionId = $this->nextTransferredTransactionId($client->client_id);
        $clientCategory = $event->client_category ?: $client->sector;

        $transaction = TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => $clientCategory,
            'transaction_id' => $transactionId,
            // Use the event's own event date when available.
            'transaction_date' => $event->event_date ?? now(),
            'category' => TransactionHistory::normalizeCategory($event->transaction_category),
            'type' => $event->transaction_type,
            'source' => 'E-Registration',
            'clerk' => auth()->user()->name ?? 'System',
            'status' => 'Approved',
            'description' => 'Transferred from imported event for '.$event->full_name,
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transaction_created',
            'description' => 'Created transaction '.$transactionId.' from imported event.',
            'subject_type' => 'TransactionHistory',
            'subject_id' => $transaction->id,
            'properties' => ['event_id' => $event->id],
        ]);

        if (! empty($event->client_category)) {
            $client->update(['sector' => $event->client_category]);
        }

        $event->update([
            'transferred_at' => now(),
            'transferred_transaction_id' => $transaction->id,
        ]);

        TransactionHistory::flushDashboardCache();

        return redirect()->route('transaction-events.records')
            ->with('success', 'Transaction '.$transactionId.' created successfully for '.$client->full_name.'.');
    }

    public function undoTransfer(TransactionEvent $event)
    {
        if (auth()->user()->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        try {
            $transactionId = DB::transaction(function () use ($event) {
                $event = TransactionEvent::query()
                    ->lockForUpdate()
                    ->find($event->id);

                if (! $event) {
                    throw new \RuntimeException('This event record no longer exists.');
                }

                if (is_null($event->transferred_at)) {
                    throw new \RuntimeException('Event #'.$event->id.' is not currently transferred.');
                }

                $transaction = $this->findTransferredTransaction($event);

                if (! $transaction) {
                    throw new \RuntimeException(
                        'The transaction created from event #'.$event->id.' could not be found. No changes were made.'
                    );
                }

                $transaction = TransactionHistory::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->first();

                if (! $transaction) {
                    throw new \RuntimeException(
                        'The transaction created from event #'.$event->id.' could not be found. No changes were made.'
                    );
                }

                if ($transaction->requirements()->exists()) {
                    throw new \RuntimeException(
                        'This transfer cannot be undone because the transaction already has supporting requirements. Remove them first.'
                    );
                }

                $transactionNumber = $transaction->transaction_id;
                $transactionHistoryId = $transaction->id;

                $transaction->delete();

                $event->update([
                    'transferred_at' => null,
                    'transferred_transaction_id' => null,
                ]);

                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'event_transfer_undone',
                    'description' => 'Undid transfer of transaction event #'.$event->id
                        .' ('.$event->full_name.') and removed transaction '.$transactionNumber.'.',
                    'subject_type' => 'TransactionEvent',
                    'subject_id' => $event->id,
                    'properties' => [
                        'event_id' => $event->id,
                        'transaction_history_id' => $transactionHistoryId,
                        'transaction_id' => $transactionNumber,
                    ],
                ]);

                return $transactionNumber;
            });
        } catch (\RuntimeException $exception) {
            return redirect()->route('transaction-events.records')
                ->with('error', $exception->getMessage());
        }

        TransactionHistory::flushDashboardCache();

        return redirect()->route('transaction-events.records')
            ->with('success', 'Transfer undone. Transaction '.$transactionId.' was removed and the event is pending again.');
    }

    public function transferSelected(Request $request)
    {
        if ($request->boolean('select_all')) {
            // Transfer every pending event matching the current list filters,
            // not just the ones visible on the current page.
            $query = TransactionEvent::query()
                ->whereNull('transferred_at')
                ->where('not_duplicate', false);

            $this->applyEventListFilters($query, $request);

            if ($request->boolean('duplicate_names')) {
                $query->whereIn('full_name', $this->duplicateFullNamesList());
            }

            $events = $query->get();
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $request->input('event_ids', []))));

            if (empty($ids)) {
                return redirect()->route('transaction-events.index')
                    ->with('error', 'No transaction events selected for transfer.');
            }

            $events = TransactionEvent::query()
                ->whereIn('id', $ids)
                ->whereNull('transferred_at')
                ->where('not_duplicate', false)
                ->get();
        }

        if ($events->isEmpty()) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Selected transaction events are already transferred or no longer available.');
        }

        $successCount = 0;
        $skippedCount = 0;
        $createdClients = 0;
        $archivedEvents = [];

        foreach ($events as $event) {
            if (empty($event->transaction_category) || empty($event->transaction_type)) {
                $skippedCount++;

                continue;
            }

            $nameParts = $this->splitFullName($event->full_name);
            $client = Client::whereRaw('LOWER(first_name) = ?', [strtolower($nameParts['first_name'])])
                ->whereRaw('LOWER(last_name) = ?', [strtolower($nameParts['last_name'])])
                ->first();

            if (! $client) {
                $client = $this->createClientFromImportedEvent([
                    'full_name' => $event->full_name,
                    'age' => $event->age,
                    'contact_no' => $event->contact_no,
                    'address' => $event->address,
                    'birth_date' => $event->birth_date?->format('Y-m-d'),
                    'client_category' => $event->client_category,
                ]);
                $createdClients++;

                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'client_created',
                    'description' => 'Auto-created client '.$client->client_id.' ('.trim($event->full_name).') during event transfer.',
                    'subject_type' => 'Client',
                    'subject_id' => $client->id,
                    'properties' => json_encode(['source' => 'transaction-event-transfer', 'event_id' => $event->id]),
                ]);
            }

            $transactionId = $this->nextTransferredTransactionId($client->client_id);
            $clientCategory = $event->client_category ?: $client->sector;

            $transaction = TransactionHistory::create([
                'client_id' => $client->client_id,
                'client_category' => $clientCategory,
                'transaction_id' => $transactionId,
                // Use the event's own event date when available.
                'transaction_date' => $event->event_date ?? now(),
                'category' => TransactionHistory::normalizeCategory($event->transaction_category),
                'type' => $event->transaction_type,
                'source' => 'E-Registration',
                'clerk' => auth()->user()->name ?? 'System',
                'status' => 'Approved',
                'description' => 'Transferred from imported event for '.$event->full_name,
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'transaction_created',
                'description' => 'Created transaction '.$transactionId.' from imported event.',
                'subject_type' => 'TransactionHistory',
                'subject_id' => $transaction->id,
                'properties' => ['event_id' => $event->id],
            ]);

            if (! empty($event->client_category)) {
                $client->update(['sector' => $event->client_category]);
            }

            $event->update([
                'transferred_at' => now(),
                'transferred_transaction_id' => $transaction->id,
            ]);
            $archivedEvents[] = $event;
            $successCount++;
        }

        $archiveFile = '';
        if (! empty($archivedEvents)) {
            $archiveFile = $this->storeArchivedTransactionEvents($archivedEvents);
        }

        $message = "Transferred {$successCount} event(s).";
        if ($createdClients > 0) {
            $message .= " Auto-created {$createdClients} new client(s).";
        }
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} event(s) because they are missing transaction details.";
        }
        if (! empty($archiveFile)) {
            $message .= " Archived as {$archiveFile}.";
        }

        if ($successCount > 0) {
            TransactionHistory::flushDashboardCache();

            return redirect()->route('transaction-events.records')->with('success', $message);
        }

        return redirect()->route('transaction-events.index')->with('error', $message);
    }

    private function nextTransferredTransactionId(string $clientId): string
    {
        $prefix = $clientId.'-'.now()->format('y').'-';

        $maxSequence = TransactionHistory::query()
            ->where('transaction_id', 'like', $prefix.'%')
            ->pluck('transaction_id')
            ->reduce(function (int $max, string $transactionId) use ($prefix) {
                $suffix = substr($transactionId, strlen($prefix));

                return ctype_digit($suffix) ? max($max, (int) $suffix) : $max;
            }, 0);

        $sequence = $maxSequence + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function findTransferredTransaction(TransactionEvent $event): ?TransactionHistory
    {
        if (! is_null($event->transferred_transaction_id)) {
            return TransactionHistory::find($event->transferred_transaction_id);
        }

        // Transfers made before the direct link was added can still be reversed
        // through their original transaction-created audit entry.
        $activity = ActivityLog::query()
            ->where('action', 'transaction_created')
            ->where('subject_type', 'TransactionHistory')
            ->latest('id')
            ->get()
            ->first(function (ActivityLog $activity) use ($event) {
                $properties = $activity->properties;

                if (is_string($properties)) {
                    $properties = json_decode($properties, true);
                }

                return is_array($properties)
                    && (int) ($properties['event_id'] ?? 0) === $event->id;
            });

        $transactionHistoryId = $activity?->subject_id;

        return $transactionHistoryId
            ? TransactionHistory::find($transactionHistoryId)
            : null;
    }

    private function utf8Encode(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        if (str_contains($value, "\x00")) {
            foreach (['UTF-16LE', 'UTF-16BE', 'UTF-16', 'UTF-32LE', 'UTF-32BE', 'UTF-32'] as $encoding) {
                $converted = mb_convert_encoding($value, 'UTF-8', $encoding);
                if (mb_check_encoding($converted, 'UTF-8') && ! str_contains($converted, "\x00")) {
                    return $converted;
                }
            }
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return ltrim($value, "\xEF\xBB\xBF");
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = mb_convert_encoding($value, 'UTF-8', $encoding);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return mb_scrub($value, 'UTF-8');
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }

    private function parseCsv($file): array
    {
        $contents = file_get_contents($file->getPathname());

        if ($contents === false) {
            return ['errors' => ['Unable to read the uploaded file.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $contents = $this->utf8Encode($contents);

        $tempPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempPath, $contents);
        $handle = fopen($tempPath, 'r');

        if ($handle === false) {
            @unlink($tempPath);

            return ['errors' => ['Unable to read the uploaded file.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            @unlink($tempPath);

            return ['errors' => ['The CSV file is empty or has no header row.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $header = array_map(function ($column) {
            return preg_replace('/[^a-z0-9]+/', '_', trim(strtolower($column)));
        }, $header);
        $header = array_filter($header);
        $header = array_values($header);

        if (! in_array('full_name', $header)) {
            fclose($handle);
            @unlink($tempPath);

            return ['errors' => ['Missing required column: full_name.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $rows = [];
        $skippedRows = [];
        $lineNumber = 1;
        $eventKeyCounts = [];
        $tempRows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $row = array_map(fn ($cell) => $this->utf8Encode($cell), $row);
            $row = array_map('trim', $row);
            $row = array_slice(array_pad($row, count($header), ''), 0, count($header));

            $data = array_combine($header, $row);
            $fullName = $data['full_name'] ?? '';

            if (empty($fullName)) {
                $skippedRows[] = [
                    'line' => $lineNumber,
                    'reason' => 'Empty full_name',
                    'data' => $data,
                ];

                continue;
            }

            $age = $this->parseImportedAge($data['age'] ?? null);
            if (($data['age'] ?? '') !== '' && $age === null) {
                $skippedRows[] = [
                    'line' => $lineNumber,
                    'reason' => 'Invalid age value',
                    'data' => $data,
                ];

                continue;
            }

            $birthDate = $this->parseImportedDate($data['birth_date'] ?? $data['birthdate'] ?? null);
            $eventDate = $this->parseImportedDate($data['event_date'] ?? $data['eventdate'] ?? null);
            if (($data['event_date'] ?? $data['eventdate'] ?? '') !== '' && $eventDate === null) {
                $skippedRows[] = [
                    'line' => $lineNumber,
                    'reason' => 'Invalid event_date value',
                    'data' => $data,
                ];

                continue;
            }
            $eventKey = $this->normalizeImportedEventKey($fullName, $birthDate);

            $eventKeyCounts[$eventKey] = ($eventKeyCounts[$eventKey] ?? 0) + 1;

            $tempRows[] = [
                'full_name' => $fullName,
                'contact_no' => $data['contact_no'] ?? '',
                'address' => $data['address'] ?? '',
                'age' => $age,
                'birth_date' => $birthDate,
                'client_category' => $data['client_category'] ?? '',
                'transaction_category' => $data['transaction_category'] ?? '',
                'transaction_type' => $data['transaction_type'] ?? '',
                'event_date' => $eventDate,
                'event_key' => $eventKey,
            ];
        }

        foreach ($tempRows as $tempRow) {
            $duplicate = $eventKeyCounts[$tempRow['event_key']] > 1;

            $rows[] = [
                'full_name' => $tempRow['full_name'],
                'contact_no' => $tempRow['contact_no'],
                'address' => $tempRow['address'],
                'age' => $tempRow['age'],
                'birth_date' => $tempRow['birth_date'],
                'client_category' => $tempRow['client_category'],
                'transaction_category' => $tempRow['transaction_category'],
                'transaction_type' => $tempRow['transaction_type'],
                'event_date' => $tempRow['event_date'],
                'duplicate' => $duplicate,
            ];
        }

        fclose($handle);
        @unlink($tempPath);

        return [
            'errors' => [],
            'rows' => $rows,
            'total' => count($rows),
            'skipped' => count($skippedRows),
            'skipped_rows' => $skippedRows,
        ];
    }

    private function normalizeImportedEventKey(string $fullName, ?string $birthDate): string
    {
        $normalizedFullName = preg_replace('/\s+/', ' ', trim(strtolower($fullName)));
        $normalizedBirthDate = $birthDate ? trim(strtolower($birthDate)) : '';

        return $normalizedFullName.'|'.$normalizedBirthDate;
    }

    private function isDuplicateImportedEvent(string $fullName, ?string $birthDate): bool
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return false;
        }

        $normalizedName = preg_replace('/\s+/', ' ', trim($fullName));
        if ($normalizedName === '') {
            return false;
        }

        $nameParts = $this->splitFullName($normalizedName);

        if (! empty($nameParts['first_name']) && ! empty($nameParts['last_name'])) {
            $clientMatch = Client::query()
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower($nameParts['first_name'])])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower($nameParts['last_name'])])
                ->whereDate('birth_date', $birthDate)
                ->exists();

            if ($clientMatch) {
                return true;
            }
        }

        return TransactionEvent::query()
            ->whereRaw('LOWER(TRIM(full_name)) = ?', [strtolower($normalizedName)])
            ->whereDate('birth_date', $birthDate)
            ->exists();
    }

    private function processImportedEvents(array $events): void
    {
        $existingClients = $this->loadExistingClientsForImportedEvents($events);
        $clientMap = [];

        foreach ($events as $event) {
            $clientKey = $this->importedEventClientKey($event);

            if ($clientKey !== null && isset($clientMap[$clientKey])) {
                $client = $clientMap[$clientKey];
            } else {
                $client = $this->findExistingClientForImportedEvent($event, $existingClients);

                if (! $client) {
                    $client = $this->createClientFromImportedEvent($event);
                }

                if ($clientKey !== null) {
                    $clientMap[$clientKey] = $client;
                }
            }

            if (! empty($event['client_category']) && $client->sector !== $event['client_category']) {
                $client->update(['sector' => $event['client_category']]);
            }

            $transaction = $this->createTransactionHistoryFromImportedEvent($client, $event);
            TransactionEvent::create([
                'full_name'                  => $event['full_name'],
                'contact_no'                 => $event['contact_no'] ?? '',
                'address'                    => $event['address'] ?? '',
                'age'                        => $event['age'] ?? null,
                'birth_date'                 => $event['birth_date'] ?? null,
                'client_category'            => $event['client_category'] ?? '',
                'transaction_category'       => $event['transaction_category'] ?? '',
                'transaction_type'           => $event['transaction_type'] ?? '',
                'event_date'                 => $event['event_date'] ?? null,
                'transferred_at'             => now(),
                'transferred_transaction_id' => $transaction->id,
            ]);
        }
    }

    private function storeImportedEventsOnly(array $events): void
    {
        foreach ($events as $event) {
            TransactionEvent::create([
                'full_name'           => $event['full_name'],
                'contact_no'          => $event['contact_no'] ?? '',
                'address'             => $event['address'] ?? '',
                'age'                 => $event['age'] ?? null,
                'birth_date'          => $event['birth_date'] ?? null,
                'client_category'     => $event['client_category'] ?? '',
                'transaction_category' => $event['transaction_category'] ?? '',
                'transaction_type'    => $event['transaction_type'] ?? '',
                'event_date'          => $event['event_date'] ?? null,
            ]);
        }
    }

    private function loadExistingClientsForImportedEvents(array $events): array
    {
        $searchKeys = [];

        foreach ($events as $event) {
            $nameParts = $this->splitFullName($event['full_name']);
            if (empty($nameParts['first_name']) || empty($nameParts['last_name'])) {
                continue;
            }

            $searchKeys[] = [
                'first' => strtolower($nameParts['first_name']),
                'last' => strtolower($nameParts['last_name']),
                'birth_date' => $event['birth_date'],
            ];
        }

        if (empty($searchKeys)) {
            return [];
        }

        $clients = Client::where(function ($query) use ($searchKeys) {
            foreach ($searchKeys as $key) {
                $query->orWhere(function ($query) use ($key) {
                    $query->whereRaw('LOWER(first_name) = ?', [$key['first']])
                        ->whereRaw('LOWER(last_name) = ?', [$key['last']]);

                    if ($key['birth_date'] !== null && $key['birth_date'] !== '') {
                        $query->whereDate('birth_date', $key['birth_date']);
                    } else {
                        $query->whereNull('birth_date');
                    }
                });
            }
        })->get();

        return $clients->keyBy(function ($client) {
            return $this->normalizeImportedClientKey([
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'birth_date' => $client->birth_date ? $client->birth_date->format('Y-m-d') : null,
            ]);
        })->all();
    }

    private function findExistingClientForImportedEvent(array $event, array $existingClients): ?Client
    {
        $key = $this->importedEventClientKey($event);

        if ($key !== null && isset($existingClients[$key])) {
            return $existingClients[$key];
        }

        return null;
    }

    private function importedEventClientKey(array $event): ?string
    {
        $nameParts = $this->splitFullName($event['full_name']);

        if (empty($nameParts['first_name']) || empty($nameParts['last_name'])) {
            return null;
        }

        return $this->normalizeImportedClientKey([
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'birth_date' => $event['birth_date'] ?? null,
        ]);
    }

    private function normalizeImportedClientKey(array $data): string
    {
        return strtolower(trim($data['first_name'].'|'.$data['last_name'].'|'.($data['birth_date'] ?? '')));
    }

    private function createClientFromImportedEvent(array $event): Client
    {
        $nameParts = $this->splitFullName($event['full_name']);

        $clientData = [
            'first_name' => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'last_name' => $nameParts['last_name'],
            'suffix' => $nameParts['suffix'],
            'age' => $event['age'],
            'contact' => $event['contact_no'],
            'address' => $event['address'],
        ];

        if (! empty($event['birth_date'])) {
            $clientData['birth_date'] = $event['birth_date'];
        }

        if (! empty($event['client_category'])) {
            $clientData['sector'] = $event['client_category'];
        }

        return Client::createWithGeneratedId($clientData);
    }

    private function createTransactionHistoryFromImportedEvent(Client $client, array $event): TransactionHistory
    {
        return TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => $event['client_category'] ?: $client->sector,
            'transaction_id' => $this->nextTransferredTransactionId($client->client_id),
            // Use the CSV row's event date when provided.
            'transaction_date' => $event['event_date'] ?? now(),
            'category' => TransactionHistory::normalizeCategory($event['transaction_category'] ?? ''),
            'type' => $event['transaction_type'] ?? '',
            'source' => 'E-Registration',
            'clerk' => auth()->user()->name ?? 'System',
            'status' => 'Approved',
            'description' => 'Imported from transaction events CSV',
        ]);
    }

    private function storeArchivedTransactionEvents(array $events): string
    {
        $archiveRows = array_map(function (TransactionEvent $event) {
            return [
                'full_name' => $event->full_name,
                'contact_no' => $event->contact_no,
                'address' => $event->address,
                'age' => $event->age,
                'birth_date' => $event->birth_date?->format('Y-m-d'),
                'client_category' => $event->client_category,
                'transaction_category' => $event->transaction_category,
                'transaction_type' => $event->transaction_type,
                'event_date' => $event->event_date?->format('Y-m-d'),
            ];
        }, $events);

        return $this->storeImportedEventArchive($archiveRows, 'transferred_transaction_events.csv');
    }

    private function storeImportedEventArchive(array $events, string $originalFilename): string
    {
        $directory = 'transaction-events-archive';
        Storage::disk('local')->makeDirectory($directory);

        $safeFilename = $this->sanitizeArchiveFilename($originalFilename);
        $archiveName = sprintf('transaction-events_%s_%s', now()->format('Ymd_His'), $safeFilename);
        $archivePath = $directory.'/'.$archiveName;

        $csvHeader = [
            'full_name',
            'contact_no',
            'address',
            'age',
            'birth_date',
            'client_category',
            'transaction_category',
            'transaction_type',
            'event_date',
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $csvHeader);

        foreach ($events as $event) {
            fputcsv($stream, [
                $event['full_name'],
                $event['contact_no'],
                $event['address'],
                $event['age'],
                $event['birth_date'],
                $event['client_category'],
                $event['transaction_category'],
                $event['transaction_type'],
                $event['event_date'] ?? '',
            ]);
        }

        rewind($stream);
        $csvContents = stream_get_contents($stream);
        fclose($stream);

        Storage::disk('local')->put($archivePath, $csvContents);

        $user = auth()->user();
        Storage::disk('local')->put(
            $directory.'/'.$archiveName.'.importer.json',
            json_encode([
                'imported_by_id' => $user?->id,
                'imported_by' => $user->name ?? 'System',
                'role' => $user->role_name ?? '',
                'imported_at' => now()->toDateTimeString(),
            ], JSON_INVALID_UTF8_SUBSTITUTE)
        );

        return $archiveName;
    }

    private function sanitizeArchiveFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
    }

    private function parseImportedDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $formats = ['m/d/Y', 'm/d/y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function parseImportedAge(?string $age): ?int
    {
        if ($age === null || $age === '') {
            return null;
        }

        $age = filter_var($age, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 0,
                'max_range' => 120,
            ],
        ]);

        return $age === false ? null : $age;
    }

    private function splitFullName(string $fullName): array
    {
        $suffixes = ['jr', 'sr', 'ii', 'iii', 'iv', 'v'];
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        $count = count($parts);

        $suffix = '';
        $lastPart = strtolower(end($parts));

        if (in_array($lastPart, $suffixes) && $count > 1) {
            $suffix = array_pop($parts);
            $count--;
        }

        $firstName = '';
        $lastName = '';
        $middleName = '';

        if ($count === 1) {
            $firstName = $parts[0];
        } elseif ($count === 2) {
            $firstName = $parts[0];
            $lastName = $parts[1];
        } else {
            $firstName = array_shift($parts);
            $lastName = array_pop($parts);
            $middleName = implode(' ', $parts);
        }

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
        ];
    }
}