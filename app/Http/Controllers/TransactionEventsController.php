<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;
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
        $query = TransactionEvent::query();

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

        $duplicateFullNames = TransactionEvent::query()
            ->select('full_name')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('full_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('full_name')
            ->all();

        if ($request->boolean('duplicate_names')) {
            $query->whereIn('full_name', $duplicateFullNames);
        }

        if ($request->boolean('duplicate_names')) {
            $query->orderBy('full_name')->orderBy('id', 'desc');
        } else {
            $query->orderByRaw('ISNULL(transferred_at) DESC, id DESC');
        }

        $events = $query->paginate(15)->withQueryString();

        $totalDuplicateGroups = TransactionEvent::query()
            ->selectRaw('LOWER(TRIM(full_name)) as keyval')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return view('pages.transaction_events.transactionEvents', compact('events', 'totalDuplicateGroups', 'duplicateFullNames'));
    }

    public function duplicateReview()
    {
        $exactGroups = $this->findEventExactDuplicates();
        $likelyGroups = $this->findEventLikelyDuplicates();
        $similarGroups = $this->findEventSimilarSpellingDuplicates();

        $notDuplicates = TransactionEvent::where('not_duplicate', true)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('pages.transaction_events.duplicateReview', compact('exactGroups', 'likelyGroups', 'similarGroups', 'notDuplicates'));
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
            'description' => "Marked transaction event #{$event->id} ({$event->full_name}) as not a duplicate.",
            'subject_type' => 'TransactionEvent',
            'subject_id' => $event->id,
            'properties' => json_encode(['event_id' => $event->id, 'full_name' => $event->full_name], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return redirect()->route('transaction-events.duplicate-review')
            ->with('success', "Event #{$event->id} ({$event->full_name}) marked as not a duplicate.");
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

        return redirect()->route('transaction-events.duplicate-review')
            ->with('success', "Event #{$event->id} ({$event->full_name}) restored to duplicate review.");
    }

    private function findEventExactDuplicates(): \Illuminate\Support\Collection
    {
        $keys = TransactionEvent::query()
            ->selectRaw("CONCAT(LOWER(TRIM(full_name)), '|', COALESCE(birth_date,'')) as keyval")
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        return $this->groupEventsByKey($keys, "CONCAT(LOWER(TRIM(full_name)), '|', COALESCE(birth_date,''))");
    }

    private function findEventLikelyDuplicates(): \Illuminate\Support\Collection
    {
        $nameKeys = TransactionEvent::query()
            ->selectRaw('LOWER(TRIM(full_name)) as keyval')
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

    private function findEventSimilarSpellingDuplicates(): \Illuminate\Support\Collection
    {
        $keys = TransactionEvent::query()
            ->selectRaw("COALESCE(SOUNDEX(LOWER(TRIM(full_name))),'') as keyval")
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

    private function groupEventsByKey(array $keys, string $keyExpr): \Illuminate\Support\Collection
    {
        if (empty($keys)) {
            return collect();
        }

        $events = TransactionEvent::whereIn(DB::raw($keyExpr), $keys)
            ->where('not_duplicate', false)
            ->get();

        return $events->groupBy(function ($event) {
            return strtolower(trim($event->full_name)) . '|' . ($event->birth_date?->format('Y-m-d') ?? '');
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
        $directory = 'transaction-events-archive';
        $files = Storage::disk('local')->files($directory);

        $archiveFiles = collect($files)
            ->map(function ($path) use ($directory) {
                $filename = basename($path);
                $uploadedAt = $this->extractArchiveUploadedAt($filename);

                return [
                    'name' => $filename,
                    'path' => $path,
                    'download_url' => route('transaction-events.archives.download', ['filename' => $filename]),
                    'size' => Storage::disk('local')->size($path),
                    'modified_at' => Storage::disk('local')->lastModified($path),
                    'uploaded_at' => $uploadedAt ?? Storage::disk('local')->lastModified($path),
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

            $timestamp = \DateTime::createFromFormat('YmdHis', $date . $time, new \DateTimeZone('UTC'));
            if ($timestamp !== false) {
                return $timestamp->getTimestamp();
            }
        }

        return null;
    }

    public function downloadArchive(string $filename)
    {
        $path = 'transaction-events-archive/' . $filename;

        if (!Storage::disk('local')->exists($path)) {
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

        if (!empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        return response()->json([
            'success'      => true,
            'rows'         => $result['rows'],
            'total'        => $result['total'],
            'skipped'      => $result['skipped'],
            'skipped_rows' => $result['skipped_rows'],
        ]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'full_name',
            'contact_no',
            'address',
            'age',
            'birth_date',
            'client_category',
            'transaction_category',
            'transaction_type',
        ];

        $widths = [28, 16, 35, 8, 14, 22, 24, 24];
        $exampleRow = [
            'Juan Dela Cruz',
            '09171234567',
            'Brgy. Poblacion, City Hall',
            '45',
            '1981-03-15',
            'INDIGENT',
            'CARAVAN',
            'FOOD ASSISTANCE',
        ];

        $rows = '';

        foreach ([$headers, $exampleRow] as $index => $row) {
            $style = $index === 0 ? ' s="1"' : '';
            $cells = '';
            foreach ($row as $i => $cell) {
                $ref = $this->excelColumnName($i + 1) . ($index + 1);
                $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$value}</t></is></c>";
            }
            $rows .= "<row r=\"" . ($index + 1) . "\">{$cells}</row>";
        }

        $cols = '';
        foreach ($widths as $i => $width) {
            $cols .= "<col min=\"" . ($i + 1) . "\" max=\"" . ($i + 1) . "\" width=\"{$width}\" customWidth=\"1\"/>";
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . "<cols>{$cols}</cols>"
            . "<sheetData>{$rows}</sheetData>"
            . '</worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Import Template" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $tempPath = tempnam(sys_get_temp_dir(), 'template_') . '.xlsx';

        $zip = new \ZipArchive();
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

        if (!empty($result['errors'])) {
            return back()->withErrors(['csv_file' => $result['errors'][0]]);
        }

        $rows = $result['rows'];
        $archiveFile = '';
        $imported = count($rows);
        $hasDuplicateRows = collect($rows)->contains(fn ($row) => !empty($row['duplicate']));

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

        if (!empty($archiveFile)) {
            $message .= " Archived CSV as {$archiveFile}.";
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_imported',
            'description' => "Imported {$imported} transaction event(s) from CSV" . ($request->file('csv_file')->getClientOriginalName() ? ' (' . $request->file('csv_file')->getClientOriginalName() . ')' : '') . '.',
            'subject_type' => 'TransactionEvent',
            'subject_id' => null,
            'properties' => json_encode([
                'imported' => $imported,
                'skipped' => $skipped,
                'file_name' => $request->file('csv_file')->getClientOriginalName(),
            ], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return redirect()->route('transaction-events.index')->with('success', $message);
    }

    public function transfer(TransactionEvent $event)
    {
        if (!is_null($event->transferred_at)) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #' . $event->id . ' is already approved/transferred.');
        }

        if ($event->not_duplicate) {
            return redirect()->route('transaction-events.duplicate-review')
                ->with('error', 'Event #' . $event->id . ' is marked as not a duplicate and cannot be transferred.');
        }

        $nameParts = $this->splitFullName($event->full_name);

        $client = Client::whereRaw('LOWER(first_name) = ?', [strtolower($nameParts['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($nameParts['last_name'])])
            ->first();

        if (!$client) {
            $client = $this->createClientFromImportedEvent([
                'full_name'       => $event->full_name,
                'age'             => $event->age,
                'contact_no'      => $event->contact_no,
                'address'         => $event->address,
                'birth_date'      => $event->birth_date?->format('Y-m-d'),
                'client_category' => $event->client_category,
            ]);

            ActivityLog::create([
                'user_id'      => auth()->id(),
                'action'       => 'client_created',
                'description'  => 'Auto-created client ' . $client->client_id . ' (' . trim($event->full_name) . ') during event transfer.',
                'subject_type' => 'Client',
                'subject_id'   => $client->id,
                'properties'   => json_encode(['source' => 'transaction-event-transfer', 'event_id' => $event->id]),
            ]);
        }

        if (empty($event->transaction_category) && empty($event->transaction_type)) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #' . $event->id . ' has no transaction category or type to transfer.');
        }

        $transactionId = $this->nextTransferredTransactionId($client->client_id);
        $clientCategory = $event->client_category ?: $client->sector;

        $transaction = TransactionHistory::create([
            'client_id'        => $client->client_id,
            'client_category'  => $clientCategory,
            'transaction_id'   => $transactionId,
            'transaction_date' => now(),
            'category'         => TransactionHistory::normalizeCategory($event->transaction_category),
            'type'             => $event->transaction_type,
            'source'           => 'E-Registration',
            'clerk'            => auth()->user()->name ?? 'System',
            'status'           => 'Approved',
            'description'      => 'Transferred from imported event for ' . $event->full_name,
        ]);

        ActivityLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'transaction_created',
            'description'  => 'Created transaction ' . $transactionId . ' from imported event.',
            'subject_type' => 'TransactionHistory',
            'subject_id'   => $transaction->id,
            'properties'   => json_encode(['event_id' => $event->id]),
        ]);

        if (!empty($event->client_category)) {
            $client->update(['sector' => $event->client_category]);
        }

        $event->update(['transferred_at' => now()]);

        return redirect()->route('transaction-events.index')
            ->with('success', 'Transaction ' . $transactionId . ' created successfully for ' . $client->full_name . '.');
    }

    public function transferSelected(Request $request)
    {
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

            if (!$client) {
                $client = $this->createClientFromImportedEvent([
                    'full_name'       => $event->full_name,
                    'age'             => $event->age,
                    'contact_no'      => $event->contact_no,
                    'address'         => $event->address,
                    'birth_date'      => $event->birth_date?->format('Y-m-d'),
                    'client_category' => $event->client_category,
                ]);
                $createdClients++;

                ActivityLog::create([
                    'user_id'      => auth()->id(),
                    'action'       => 'client_created',
                    'description'  => 'Auto-created client ' . $client->client_id . ' (' . trim($event->full_name) . ') during event transfer.',
                    'subject_type' => 'Client',
                    'subject_id'   => $client->id,
                    'properties'   => json_encode(['source' => 'transaction-event-transfer', 'event_id' => $event->id]),
                ]);
            }

            $transactionId = $this->nextTransferredTransactionId($client->client_id);
            $clientCategory = $event->client_category ?: $client->sector;

            $transaction = TransactionHistory::create([
                'client_id'        => $client->client_id,
                'client_category'  => $clientCategory,
                'transaction_id'   => $transactionId,
                'transaction_date' => now(),
'category'         => TransactionHistory::normalizeCategory($event->transaction_category),
            'type'             => $event->transaction_type,
            'source'           => 'E-Registration',
            'clerk'            => auth()->user()->name ?? 'System',
            'status'           => 'Approved',
            'description'      => 'Transferred from imported event for ' . $event->full_name,
        ]);

        ActivityLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'transaction_created',
            'description'  => 'Created transaction ' . $transactionId . ' from imported event.',
            'subject_type' => 'TransactionHistory',
            'subject_id'   => $transaction->id,
            'properties'   => json_encode(['event_id' => $event->id]),
        ]);

            if (!empty($event->client_category)) {
                $client->update(['sector' => $event->client_category]);
            }

            $event->update(['transferred_at' => now()]);
            $archivedEvents[] = $event;
            $successCount++;
        }

        $archiveFile = '';
        if (!empty($archivedEvents)) {
            $archiveFile = $this->storeArchivedTransactionEvents($archivedEvents);
        }

        $message = "Transferred {$successCount} event(s).";
        if ($createdClients > 0) {
            $message .= " Auto-created {$createdClients} new client(s).";
        }
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} event(s) because they are missing transaction details.";
        }
        if (!empty($archiveFile)) {
            $message .= " Archived as {$archiveFile}.";
        }

        if ($successCount > 0) {
            return redirect()->route('transaction-events.index')->with('success', $message);
        }

        return redirect()->route('transaction-events.index')->with('error', $message);
    }

    private function nextTransferredTransactionId(string $clientId): string
    {
        $prefix = $clientId . '-' . now()->format('y') . '-';

        $maxSequence = TransactionHistory::query()
            ->where('transaction_id', 'like', $prefix . '%')
            ->pluck('transaction_id')
            ->reduce(function (int $max, string $transactionId) use ($prefix) {
                $suffix = substr($transactionId, strlen($prefix));

                return ctype_digit($suffix) ? max($max, (int) $suffix) : $max;
            }, 0);

        $sequence = $maxSequence + 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
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
                if (mb_check_encoding($converted, 'UTF-8') && !str_contains($converted, "\x00")) {
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
            $name = chr(65 + $mod) . $name;
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

        if (!in_array('full_name', $header)) {
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
                    'line'   => $lineNumber,
                    'reason' => 'Empty full_name',
                    'data'   => $data,
                ];
                continue;
            }

            $age = $this->parseImportedAge($data['age'] ?? null);
            if (($data['age'] ?? '') !== '' && $age === null) {
                $skippedRows[] = [
                    'line'   => $lineNumber,
                    'reason' => 'Invalid age value',
                    'data'   => $data,
                ];
                continue;
            }

            $birthDate = $this->parseImportedDate($data['birth_date'] ?? $data['birthdate'] ?? null);
            $eventKey = $this->normalizeImportedEventKey($fullName, $birthDate);

            $eventKeyCounts[$eventKey] = ($eventKeyCounts[$eventKey] ?? 0) + 1;

            $tempRows[] = [
                'full_name'           => $fullName,
                'contact_no'          => $data['contact_no'] ?? '',
                'address'             => $data['address'] ?? '',
                'age'                 => $age,
                'birth_date'          => $birthDate,
                'client_category'     => $data['client_category'] ?? '',
                'transaction_category' => $data['transaction_category'] ?? '',
                'transaction_type'    => $data['transaction_type'] ?? '',
                'event_key'           => $eventKey,
            ];
        }

        foreach ($tempRows as $tempRow) {
            $duplicate = $eventKeyCounts[$tempRow['event_key']] > 1;

            $rows[] = [
                'full_name'           => $tempRow['full_name'],
                'contact_no'          => $tempRow['contact_no'],
                'address'             => $tempRow['address'],
                'age'                 => $tempRow['age'],
                'birth_date'          => $tempRow['birth_date'],
                'client_category'     => $tempRow['client_category'],
                'transaction_category' => $tempRow['transaction_category'],
                'transaction_type'    => $tempRow['transaction_type'],
                'duplicate'           => $duplicate,
            ];
        }

        fclose($handle);
        @unlink($tempPath);

        return [
            'errors'       => [],
            'rows'         => $rows,
            'total'        => count($rows),
            'skipped'      => count($skippedRows),
            'skipped_rows' => $skippedRows,
        ];
    }

    private function normalizeImportedEventKey(string $fullName, ?string $birthDate): string
    {
        $normalizedFullName = preg_replace('/\s+/', ' ', trim(strtolower($fullName)));
        $normalizedBirthDate = $birthDate ? trim(strtolower($birthDate)) : '';

        return $normalizedFullName . '|' . $normalizedBirthDate;
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

        if (!empty($nameParts['first_name']) && !empty($nameParts['last_name'])) {
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

                if (!$client) {
                    $client = $this->createClientFromImportedEvent($event);
                }

                if ($clientKey !== null) {
                    $clientMap[$clientKey] = $client;
                }
            }

            if (!empty($event['client_category']) && $client->sector !== $event['client_category']) {
                $client->update(['sector' => $event['client_category']]);
            }

            $this->createTransactionHistoryFromImportedEvent($client, $event);
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
                'first'      => strtolower($nameParts['first_name']),
                'last'       => strtolower($nameParts['last_name']),
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
            'last_name'  => $nameParts['last_name'],
            'birth_date' => $event['birth_date'] ?? null,
        ]);
    }

    private function normalizeImportedClientKey(array $data): string
    {
        return strtolower(trim($data['first_name'] . '|' . $data['last_name'] . '|' . ($data['birth_date'] ?? '')));
    }

    private function createClientFromImportedEvent(array $event): Client
    {
        $nameParts = $this->splitFullName($event['full_name']);

        $clientData = [
            'client_id'   => Client::generateClientId(),
            'first_name'  => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'last_name'   => $nameParts['last_name'],
            'suffix'      => $nameParts['suffix'],
            'age'         => $event['age'],
            'contact'     => $event['contact_no'],
            'address'     => $event['address'],
        ];

        if (!empty($event['birth_date'])) {
            $clientData['birth_date'] = $event['birth_date'];
        }

        if (!empty($event['client_category'])) {
            $clientData['sector'] = $event['client_category'];
        }

        return Client::create($clientData);
    }

    private function createTransactionHistoryFromImportedEvent(Client $client, array $event): void
    {
        TransactionHistory::create([
            'client_id'        => $client->client_id,
            'client_category'  => $event['client_category'] ?: $client->sector,
            'transaction_id'   => $this->nextTransferredTransactionId($client->client_id),
            'transaction_date' => now(),
            'category'         => TransactionHistory::normalizeCategory($event['transaction_category'] ?? ''),
            'type'             => $event['transaction_type'] ?? '',
            'source'           => 'E-Registration',
            'clerk'            => auth()->user()->name ?? 'System',
            'status'           => 'Approved',
            'description'      => 'Imported from transaction events CSV',
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
        $archivePath = $directory . '/' . $archiveName;

        $csvHeader = [
            'full_name',
            'contact_no',
            'address',
            'age',
            'birth_date',
            'client_category',
            'transaction_category',
            'transaction_type',
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
            ]);
        }

        rewind($stream);
        $csvContents = stream_get_contents($stream);
        fclose($stream);

        Storage::disk('local')->put($archivePath, $csvContents);

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
            'first_name'  => $firstName,
            'middle_name' => $middleName,
            'last_name'   => $lastName,
            'suffix'      => $suffix,
        ];
    }
}
