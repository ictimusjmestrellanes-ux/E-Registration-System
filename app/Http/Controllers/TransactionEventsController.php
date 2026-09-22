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

        $sort = $this->normalizeEventSort($request->input('sort'));
        $this->applyEventSort($query, $sort);

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
        $addresses = (clone $pendingBase)->select('address')->distinct()
            ->pluck('address')->map(fn ($address) => trim((string) $address))->filter()->unique()->sort()->values();
        $addressTypes = $this->addressTypeOptions(clone $pendingBase);

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
            compact('events', 'totalDuplicateGroups', 'duplicateFullNames', 'clientCategories', 'transactionCategories', 'transactionTypes', 'addresses', 'addressTypes', 'sort'));
    }

    /**
     * Whitelisted sort keys for the Import Events list (single `sort` param,
     * same pattern as the All Transactions module).
     */
    private function validEventSorts(): array
    {
        return [
            'newest', 'oldest',
            'client_asc', 'client_desc',
            'age_asc', 'age_desc',
            'birth_asc', 'birth_desc',
            'contact_asc', 'contact_desc',
            'address_asc', 'address_desc',
            'clientcat_asc', 'clientcat_desc',
            'category_asc', 'category_desc',
            'type_asc', 'type_desc',
            'eventdate_asc', 'eventdate_desc',
            'imported_asc', 'imported_desc',
            'status_asc', 'status_desc',
        ];
    }

    private function normalizeEventSort(?string $sort): string
    {
        if (in_array($sort, $this->validEventSorts(), true)) {
            return $sort;
        }

        return 'client_asc';
    }

    /**
     * Apply server-side ordering for the Import Events list. Text columns
     * sort case-insensitively, matching the All Transactions module.
     */
    private function applyEventSort($query, string $sort): void
    {
        if ($sort === 'newest') {
            $query->orderByDesc('id');

            return;
        }

        if ($sort === 'oldest') {
            $query->orderBy('id');

            return;
        }

        $direction = str_ends_with($sort, '_desc') ? 'desc' : 'asc';
        $column = match (true) {
            str_starts_with($sort, 'client_') => 'full_name',
            str_starts_with($sort, 'age_') => 'age',
            str_starts_with($sort, 'birth_') => 'birth_date',
            str_starts_with($sort, 'contact_') => 'contact_no',
            str_starts_with($sort, 'address_') => 'address',
            str_starts_with($sort, 'clientcat_') => 'client_category',
            str_starts_with($sort, 'category_') => 'transaction_category',
            str_starts_with($sort, 'type_') => 'transaction_type',
            str_starts_with($sort, 'eventdate_') => 'event_date',
            str_starts_with($sort, 'imported_') => 'created_at',
            str_starts_with($sort, 'status_') => 'status',
            default => 'id',
        };

        $textColumns = ['full_name', 'contact_no', 'address', 'client_category', 'transaction_category', 'transaction_type', 'status'];

        if ($column === 'full_name') {
            $query->orderByRaw('COALESCE(display_name_sort, LOWER(TRIM(full_name))) '.$direction);
        } elseif (in_array($column, $textColumns, true)) {
            $query->orderByRaw("LOWER({$column}) {$direction}");
        } else {
            $query->orderBy($column, $direction);
        }

        $query->orderBy('id', $direction === 'asc' ? 'asc' : 'desc');
    }

    private function addressTypeOptions($query): Collection
    {
        return $query->select('address', 'transaction_type')->distinct()->get()
            ->map(fn ($event) => [
                'address' => trim((string) $event->address),
                'type' => (string) $event->transaction_type,
            ])
            ->filter(fn ($option) => $option['address'] !== '')
            ->values();
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
    private function applyAddressFilter($query, Request $request): void
    {
        // Commas belong to addresses, not separators between selections.
        $addresses = collect((array) $request->input('address', []))
            ->filter(fn ($value) => is_string($value))
            ->map(fn ($value) => mb_strtolower(trim($value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()->values()->all();
        if ($addresses) {
            $query->whereIn(DB::raw('LOWER(TRIM(address))'), $addresses);
        }
    }

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
            $this->applyEventNameSearch($query, $search);
        }

        if ($contact = $request->input('contact')) {
            $query->where('contact_no', 'like', "%{$contact}%");
        }

        $this->applyAddressFilter($query, $request);

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

    private function applyEventNameSearch($query, string $search): void
    {
        $search = trim($search);
        $query->where(function ($nameQuery) use ($search) {
            $nameQuery->where('full_name', 'like', "%{$search}%");

            if (str_contains($search, ',')) {
                [$lastName, $givenName] = array_map('trim', explode(',', $search, 2));
                $givenName = rtrim($givenName, '.');

                if ($lastName !== '' && $givenName !== '') {
                    $nameQuery->orWhere(function ($partsQuery) use ($lastName, $givenName) {
                        $partsQuery->where('full_name', 'like', "%{$lastName}%")
                            ->where('full_name', 'like', "%{$givenName}%");
                    });
                }
            }
        });
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
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($search = $request->input('search')) {
            $transactionSearch = trim(str_replace(['(', ')', "'", '"'], '', $search));
            $query->where(function ($searchQuery) use ($search, $transactionSearch) {
                $this->applyEventNameSearch($searchQuery, $search);

                if ($transactionSearch !== '') {
                    $searchQuery->orWhereHas('transferredTransaction', function ($transactionQuery) use ($transactionSearch) {
                        if (ctype_digit($transactionSearch)) {
                            $transactionQuery->where(function ($idQuery) use ($transactionSearch) {
                                $idQuery->where('transaction_id', 'like', "{$transactionSearch}-%")
                                    ->orWhere('transaction_id', 'like', "%-{$transactionSearch}");
                            });
                        } else {
                            $transactionQuery->where('transaction_id', 'like', "%{$transactionSearch}%");
                        }
                    });
                }
            });
        }

        if ($contact = $request->input('contact')) {
            $query->where('contact_no', 'like', "%{$contact}%");
        }

        $this->applyAddressFilter($query, $request);

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

    public function updateRecordStatus(Request $request, TransactionEvent $event)
    {
        $returnRoute = in_array($request->query('duplicate_tab'), ['exact', 'likely', 'full_name'], true)
            ? 'transaction-events.records-duplicates'
            : 'transaction-events.records';
        abort_unless(feature_allowed('Event Records'), 404);
        abort_if(auth()->user()->role_name === 'Viewer', 403, 'Viewer role is read-only.');
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(TransactionEvent::STATUSES)],
        ]);

        try {
            DB::transaction(function () use ($event, $validated) {
                $event = TransactionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
                abort_if($event->transferred_at === null, 404);
                $this->setEventStatus($event, $validated['status']);
            });
        } catch (\RuntimeException $exception) {
            // HTTP exceptions (such as a record no longer transferred) must retain their status.
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $exception;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->route($returnRoute, $request->query())
                ->with('error', $exception->getMessage());
        }

        app(\App\Services\ActivityLogger::class)->record(
            'event_status_tagged',
            "Tagged event #{$event->id} ({$event->full_name}) as {$validated['status']}.",
            ['event_id' => $event->id, 'full_name' => $event->full_name, 'status' => $validated['status']],
            $event
        );

        $message = 'Event and All Transactions status updated to '.$validated['status'].'.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'event_id' => (int) $event->id,
                'status' => $validated['status'],
                'message' => $message,
            ]);
        }

        return redirect()->route($returnRoute, $request->query())->with('success', $message);
    }

    public function updateRecordStatusSelected(Request $request)
    {
        abort_unless(feature_allowed('Event Records'), 404);
        abort_if(auth()->user()->role_name === 'Viewer', 403, 'Viewer role is read-only.');
        abort_unless(feature_allowed('Tag Transaction Event Record Status'), 403);

        $validated = $request->validate([
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['integer'],
            'status' => ['required', \Illuminate\Validation\Rule::in(TransactionEvent::STATUSES)],
        ]);

        $ids = array_values(array_unique(array_filter($validated['event_ids'], 'is_numeric')));
        if ($ids === []) {
            abort(422, 'No event ids were selected.');
        }

        $updated = 0;
        $skipped = 0;
        $processedIds = [];

        foreach ($ids as $id) {
            try {
                $changed = DB::transaction(function () use ($id, $validated) {
                    $event = TransactionEvent::whereKey($id)->lockForUpdate()->first();
                    if (! $event || $event->transferred_at === null) {
                        return null;
                    }

                    return $this->setEventStatus($event, $validated['status']);
                });
            } catch (\RuntimeException $exception) {
                // Missing linked history etc. counts as skipped, not fatal.
                $skipped++;
                continue;
            }

            if ($changed === null) {
                $skipped++;
            } else {
                $processedIds[] = (int) $id;
                if ($changed) {
                    $updated++;
                }
            }
        }

        if ($updated > 0) {
            app(\App\Services\ActivityLogger::class)->record(
                'events_status_tagged',
                "Tagged {$updated} event record(s) as {$validated['status']}. Skipped {$skipped}.",
                ['updated' => $updated, 'skipped' => $skipped, 'status' => $validated['status'], 'event_ids' => $ids],
                ['type' => 'TransactionEvent']
            );
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'status' => $validated['status'],
            'processed_ids' => $processedIds,
        ]);
    }

    public function records(Request $request)
    {
        if (!feature_allowed('Event Records')) {
            abort(404);
        }

        $query = TransactionEvent::whereNotNull('transferred_at');

        $this->applyRecordFilters($query, $request);

        // Single `sort` param (same pattern as All Transactions). Legacy
        // `sort_by`/`sort_dir` links keep working by mapping to the new keys.
        $sort = $this->normalizeRecordSort($request->input('sort', $this->legacyRecordSort($request)));

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $this->applyRecordSort($query, $sort);

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
        $addresses = TransactionEvent::whereNotNull('transferred_at')
            ->select('address')->distinct()
            ->pluck('address')->map(fn ($address) => trim((string) $address))->filter()->unique()->sort()->values();
        $addressTypes = $this->addressTypeOptions(TransactionEvent::whereNotNull('transferred_at'));

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

        return view('pages.transaction_events.eventRecords', compact('events', 'categories', 'types', 'clientCategories', 'addresses', 'addressTypes', 'typeClientCategories', 'duplicateRecordIds', 'sort'));
    }

    /**
     * Whitelisted sort keys for the Event Records list (single `sort` param,
     * same pattern as the All Transactions module).
     */
    private function validRecordSorts(): array
    {
        return [
            'newest', 'oldest',
            'client_asc', 'client_desc',
            'txid_asc', 'txid_desc',
            'age_asc', 'age_desc',
            'birth_asc', 'birth_desc',
            'contact_asc', 'contact_desc',
            'address_asc', 'address_desc',
            'clientcat_asc', 'clientcat_desc',
            'category_asc', 'category_desc',
            'type_asc', 'type_desc',
            'eventdate_asc', 'eventdate_desc',
            'transferred_asc', 'transferred_desc',
            'status_asc', 'status_desc',
        ];
    }

    private function normalizeRecordSort(?string $sort): string
    {
        return in_array($sort, $this->validRecordSorts(), true) ? $sort : 'client_asc';
    }

    /**
     * Map legacy `sort_by`/`sort_dir` params to the new single `sort` keys
     * so old bookmarks and links keep working.
     */
    private function legacyRecordSort(Request $request): string
    {
        if (! $request->has('sort_by') && ! $request->has('sort_dir')) {
            return 'client_asc';
        }

        $map = [
            'full_name' => 'client',
            'transaction_id' => 'txid',
            'age' => 'age',
            'birth_date' => 'birth',
            'contact' => 'contact',
            'address' => 'address',
            'client_category' => 'clientcat',
            'transaction_category' => 'category',
            'transaction_type' => 'type',
            'event_date' => 'eventdate',
            'transferred_at' => 'transferred',
            'status' => 'status',
            'id' => 'newest',
        ];

        $key = $map[$request->input('sort_by', 'full_name')] ?? 'client';
        $dir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($key === 'newest') {
            return $dir === 'asc' ? 'oldest' : 'newest';
        }

        return $key.'_'.$dir;
    }

    /**
     * Apply server-side ordering for the Event Records list. Text columns
     * sort case-insensitively, matching the All Transactions module.
     */
    private function applyRecordSort($query, string $sort): void
    {
        if ($sort === 'newest') {
            $query->orderByDesc('id');

            return;
        }

        if ($sort === 'oldest') {
            $query->orderBy('id');

            return;
        }

        $direction = str_ends_with($sort, '_desc') ? 'desc' : 'asc';
        $column = match (true) {
            str_starts_with($sort, 'client_') => 'full_name',
            str_starts_with($sort, 'txid_') => 'transferred_transaction_id',
            str_starts_with($sort, 'age_') => 'age',
            str_starts_with($sort, 'birth_') => 'birth_date',
            str_starts_with($sort, 'contact_') => 'contact_no',
            str_starts_with($sort, 'address_') => 'address',
            str_starts_with($sort, 'clientcat_') => 'client_category',
            str_starts_with($sort, 'category_') => 'transaction_category',
            str_starts_with($sort, 'type_') => 'transaction_type',
            str_starts_with($sort, 'eventdate_') => 'event_date',
            str_starts_with($sort, 'transferred_') => 'transferred_at',
            str_starts_with($sort, 'status_') => 'status',
            default => 'full_name',
        };

        $textColumns = ['contact_no', 'address', 'client_category', 'transaction_category', 'transaction_type', 'status'];

        if ($column === 'full_name') {
            $query->orderByRaw('COALESCE(display_name_sort, LOWER(TRIM(full_name))) '.$direction);
        } elseif (in_array($column, $textColumns, true)) {
            $query->orderByRaw("LOWER({$column}) {$direction}");
        } else {
            $query->orderBy($column, $direction);
        }

        $query->orderBy('id', 'asc');
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
        $events = $query->orderByRaw('COALESCE(display_name_sort, LOWER(TRIM(full_name)))')->orderBy('id')->get();

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

    /**
     * Download the printable payroll roster in Excel using the PDF report details.
     */
    public function exportRecordsPayrollXlsx(Request $request)
    {
        abort_unless(feature_allowed('Event Records') && feature_allowed('Print Payroll Transaction PDF'), 404);

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
        $events = $query->orderByRaw('COALESCE(display_name_sort, LOWER(TRIM(full_name)))')->orderBy('id')->get();

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

        $path = app(\App\Services\EventRecordsPayrollXlsxExporter::class)
            ->render($events, $isRice, $dateLabel, $details);

        return response()->download($path, 'event-records-payroll_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
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
                'event_date', 'transferred_at', 'transferred_transaction_id', 'status',
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
            'Full Name',
        ];
        $widths = [8, 22, 28, 8, 14, 16, 35, 20, 24, 24, 14, 20, 12, 28];

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
                $event->status,
                // Preserve the event's exact matching name for re-imports.
                // Client Name is a display value and may differ from this.
                $event->full_name ?? '',
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
     * Exact match is: Same Lastname and Firstname + Client Category +
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
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

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
     * One shared aggregation for Exact Match and Full Name: transferred (and
     * pre-filtered) rows grouped by the full normalized duplicate key.
     * Both tabs regroup these rows in PHP.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function recordDuplicateKeyRows(Request $request): Collection
    {
        $columns = ['event_date', 'client_category', 'transaction_category', 'transaction_type'];
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
     * Regroup full-key rows into tab descriptors. Exact combines parsed first/last
     * names; Full Name combines normalized names.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildRecordDuplicateDescriptors(Collection $keyRows, array $patterns, bool $excludeExact): Collection
    {
        $columns = ['event_date', 'client_category', 'transaction_category', 'transaction_type'];
        $descriptors = collect();
        foreach ($patterns as $pattern => $groupColumns) {
            $grouped = $keyRows->groupBy(function ($row) use ($pattern, $groupColumns) {
                $nameKey = $row->fullname;
                if ($pattern === 'exact') {
                    $name = $this->splitImportFullName($row->fullname);
                    $nameKey = $name['last']."\0".$name['first'];
                }

                return $nameKey."\0".implode("\0", array_map(fn ($c) => (string) $row->$c, $groupColumns));
            });
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
                $descriptor->fullnames = $members->pluck('fullname')->unique()->values()->all();
                $descriptor->member_ids = $members->pluck('group_id')->all();
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
        $columns = ['event_date', 'client_category', 'transaction_category', 'transaction_type'];
        $keyRowsById = $keyRows->keyBy('group_id');
        // Match rules identify records first; display and paginate once per client.
        // Exact groups parsed last and first names. Full Name groups the
        // normalized full name.
        $descriptors = $this->buildRecordDuplicateDescriptors($keyRows, $patterns, $excludeExact)
            ->groupBy(function ($descriptor) use ($pageName) {
                if ($pageName !== 'exact_page') {
                    return $descriptor->fullname;
                }
                $name = $this->splitImportFullName($descriptor->fullname);
                return json_encode([$name['last'], $name['first']]);
            })
            ->map(function ($matches) use ($keyRowsById) {
                $memberIds = $matches->flatMap(fn ($match) => $match->member_ids)->unique()->values();
                return (object) [
                    'group_id' => $memberIds->min(),
                    'member_ids' => $memberIds->all(),
                    'total' => (int) $keyRowsById->only($memberIds->all())->sum('total'),
                ];
            })
            ->sort(fn ($a, $b) => ($b->total <=> $a->total) ?: ($a->group_id <=> $b->group_id))
            ->values();
        $recordsTotal = (int) $descriptors->sum('total');
        $page = max(1, (int) $request->input($pageName, $pageName === 'exact_page' ? $request->input('page', 1) : 1));
        $pageDescriptors = $descriptors->forPage($page, $perPage)->values();
        $groups = collect();
        if ($pageDescriptors->isNotEmpty()) {
            // Fetch visible clients once, then retain only qualifying duplicate
            // keys. This avoids a SQL UNION for every event pattern per client.
            $memberGroups = [];
            $fullnames = [];
            $fields = ['fullname', ...$columns];
            foreach ($pageDescriptors as $descriptor) {
                foreach ($descriptor->member_ids as $memberId) {
                    $member = $keyRowsById->get($memberId);
                    $memberKey = json_encode(array_map(fn ($field) => (string) $member->$field, $fields));
                    $memberGroups[$memberKey] = $descriptor->group_id;
                    $fullnames[] = $member->fullname;
                }
            }
            $query = TransactionEvent::query()->whereNotNull('transferred_at')
                ->whereIn(DB::raw($this->recordDuplicateNormalizedExpression('full_name')), array_unique($fullnames))
                ->select('transaction_events.*');
            $this->applyRecordDuplicatePrefilters($query, $request);
            foreach ($fields as $field) {
                $column = $field === 'fullname' ? 'full_name' : $field;
                $query->selectRaw($this->recordDuplicateNormalizedExpression($column).' as duplicate_'.$field);
            }
            $events = $query->with('transferredTransaction:id,transaction_id')
                ->orderByDesc('transaction_events.id')->get()
                ->groupBy(function ($event) use ($fields, $memberGroups) {
                    $key = json_encode(array_map(fn ($field) => (string) $event->getAttribute('duplicate_'.$field), $fields));
                    return $memberGroups[$key] ?? 'unmatched';
                });
            $groups = $pageDescriptors->map(fn ($descriptor) => [
                'events' => $events->get($descriptor->group_id, collect())->unique('id')->values(),
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

    /**
     * Group records with the same parsed last and first name when at least
     * one nonblank event date, transaction type, or client category matches.
     */
    private function likelyRecordGroups(Request $request, int $perPage): array
    {
        $base = DB::table('transaction_events')->whereNotNull('transferred_at');
        $this->applyRecordDuplicatePrefilters($base, $request);
        $rows = $base->get(['id', 'full_name', 'event_date', 'transaction_category',
            'transaction_type', 'client_category']);
        $normalized = static fn ($value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
        $people = [];
        foreach ($rows as $row) {
            $name = $this->splitImportFullName((string) $row->full_name);
            $first = $normalized(preg_split('/\s+/u', trim($name['first']))[0] ?? '');
            $last = $normalized($name['last']);
            if ($first === '' || $last === '') {
                continue;
            }
            $people[json_encode([$last, $first])][] = $row;
        }

        $matches = collect();
        foreach ($people as $personRows) {
            $eventIds = [];
            $matchedFields = [];
            foreach (['event_date' => 'Event Date', 'transaction_type' => 'Transaction Type',
                'client_category' => 'Client Category'] as $field => $label) {
                $buckets = [];
                foreach ($personRows as $row) {
                    $value = $field === 'event_date'
                        ? substr(trim((string) $row->$field), 0, 10)
                        : $normalized($row->$field);
                    if ($value !== '') {
                        $buckets[$value][] = (int) $row->id;
                    }
                }
                foreach ($buckets as $bucket) {
                    if (count($bucket) < 2) {
                        continue;
                    }
                    $matchedFields[$field] = $label;
                    foreach ($bucket as $eventId) {
                        $eventIds[$eventId] = true;
                    }
                }
            }
            if (count($eventIds) < 2) {
                continue;
            }
            $eventIds = array_keys($eventIds);
            $matchedIdLookup = array_fill_keys($eventIds, true);
            // Exact-only groups belong in Exact Match, not Likely Match.
            $exactKeys = collect($personRows)->filter(fn ($row) => isset($matchedIdLookup[(int) $row->id]))
                ->map(function ($row) use ($normalized) {
                    $name = $this->splitImportFullName((string) $row->full_name);
                    return json_encode([
                        $normalized($name['first']),
                        substr(trim((string) $row->event_date), 0, 10),
                        $normalized($row->client_category),
                        $normalized($row->transaction_category),
                        $normalized($row->transaction_type),
                    ]);
                })->unique();
            if ($exactKeys->count() === 1) {
                continue;
            }
            $matches->push([
                'event_ids' => $eventIds,
                'total' => count($eventIds),
                'matched_fields' => array_values($matchedFields),
            ]);
        }
        $matches = $matches->sort(fn ($a, $b) => ($b['total'] <=> $a['total'])
            ?: (min($a['event_ids']) <=> min($b['event_ids'])))->values();
        $recordsTotal = $matches->sum('total');
        $page = max(1, (int) $request->input('likely_page', 1));
        $visible = $matches->forPage($page, $perPage)->values();
        $events = $visible->isEmpty() ? collect() : TransactionEvent::query()
            ->with('transferredTransaction:id,transaction_id')
            ->whereIn('id', $visible->flatMap(fn ($group) => $group['event_ids'])->all())
            ->get()->keyBy('id');
        $groups = $visible->map(fn ($group) => [
            'events' => collect($group['event_ids'])->map(fn ($id) => $events->get($id))->filter()->values(),
            'total' => $group['total'],
            'matched_fields' => $group['matched_fields'],
        ]);

        return [new LengthAwarePaginator($groups, $matches->count(), $perPage, $page, [
            'path' => url()->current(),
            'query' => array_merge($request->query(), ['duplicate_tab' => 'likely']),
            'pageName' => 'likely_page',
        ]), (int) $recordsTotal];
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

        // Exact match is: Same Lastname and Firstname + Client Category +
        // Transaction Category + Transaction Type + Event Date.
        // Exact Match and Full Name share the full-key aggregation.
        $duplicateKeyRows = $this->recordDuplicateKeyRows($request);
        [$exactGroups, $exactRecordsTotal] = $this->paginatedRecordDuplicateGroups($duplicateKeyRows, [
            'exact' => ['event_date', 'client_category', 'transaction_category', 'transaction_type'],
        ], $request, $perPage, 'exact_page');
        [$likelyGroups, $likelyRecordsTotal] = $this->likelyRecordGroups($request, $perPage);
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
            'exactGroups', 'likelyGroups', 'similarGroups',
            'exactRecordsTotal', 'likelyRecordsTotal', 'similarRecordsTotal',
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

        if ($search = trim((string) $request->input('search', ''))) {
            $base->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('client_category', 'like', "%{$search}%")
                    ->orWhere('transaction_category', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%");
            });
        }

        if ($vals = $this->multiFilterValues($request, 'client_category')) {
            $base->whereIn('client_category', $vals);
        }

        if ($vals = $this->multiFilterValues($request, 'transaction_category')) {
            $base->whereIn('transaction_category', $vals);
        }

        if ($vals = $this->multiFilterValues($request, 'transaction_type')) {
            $base->whereIn('transaction_type', $vals);
        }

        if ($from = $request->input('date_from')) {
            $base->whereDate('event_date', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $base->whereDate('event_date', '<=', $to);
        }

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

            // Ignore the removed column in older import templates.
            unset($mapped['sector']);

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

        return ['rows' => $records, 'skipped' => $skipped, 'skipped_examples' => $skippedExamples, 'headers' => array_values(array_diff($header, ['sector']))];
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
        $existingEvents = TransactionEvent::query()->get([
            'full_name', 'client_category', 'transaction_category', 'transaction_type', 'event_date',
        ]);
        $matchingCounts = $existingEvents->countBy(fn ($event) => $this->importMatchKey($event->getAttributes()));
        $existingNames = $existingEvents->pluck('full_name')
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
                    '_match_record' => $record,
                    'full_name' => $fullName,
                    'client_category' => trim((string) ($record['client_category'] ?? '')),
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
                    '_match_record' => $record,
                    'full_name' => $fullName,
                    'client_category' => trim((string) ($record['client_category'] ?? '')),
                    'event_date' => $eventDate,
                    'transaction_category' => $category,
                    'transaction_type' => $transactionType,
                ];
            }
        }

        $duplicateCount = count($duplicates);
        foreach ($duplicates as &$duplicate) {
            $record = $duplicate['_match_record'];
            $complete = collect(['full_name', 'client_category', 'transaction_category', 'transaction_type', 'event_date'])
                ->every(fn ($field) => trim((string) ($record[$field] ?? '')) !== '');
            $duplicate['matching_records_count'] = $complete ? ($matchingCounts[$this->importMatchKey($record)] ?? 0) : 0;
            unset($duplicate['_match_record']);
        }
        unset($duplicate);

        return response()->json([
            'success' => true,
            'total_rows' => count($records),
            'duplicates_count' => $duplicateCount,
            'duplicates' => $duplicates,
            'duplicates_truncated' => false,
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
            'update_existing' => $request->boolean('update_existing'),
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
                $outcome = $this->storeImportRow($record, (bool) ($payload['events_only'] ?? false), $forceNewClients, (bool) ($payload['update_existing'] ?? false));
                $payload[$outcome] = (int) ($payload[$outcome] ?? 0) + 1;
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
                    $outcome = $this->storeImportRow($record, (bool) ($payload['events_only'] ?? false), $forceNewClients, (bool) ($payload['update_existing'] ?? false));
                    $payload[$outcome] = (int) ($payload[$outcome] ?? 0) + 1;
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

            session()->flash('success', ($payload['update_existing'] ?? false)
                ? $this->importUpdateSummary($payload, $skipped)
                : 'Successfully imported ' . $imported . ' event(s).' . ($skipped > 0 ? ' Skipped ' . $skipped . ' invalid row(s).' : ''));

            $importMessage = ($payload['update_existing'] ?? false)
                ? $this->importUpdateSummary($payload, $skipped)
                : 'Successfully imported ' . $imported . ' event(s).' . ($skipped > 0 ? ' Skipped ' . $skipped . ' invalid row(s).' : '');

            $this->logImportSummary(
                (string) ($payload['original_filename'] ?? 'transaction-events.csv'),
                $payload,
                $skipped,
                (bool) ($payload['update_existing'] ?? false)
            );

            // MySQL error strings can carry raw non-UTF-8 bytes, so never let
            // the response encoder itself become the failure (HTTP 500 with
            // "Malformed UTF-8 characters" and no usable message).
            return response()->json([
                'success' => true,
                'imported' => $imported,
                'created' => (int) ($payload['created'] ?? 0),
                'updated' => (int) ($payload['updated'] ?? 0),
                'unchanged' => (int) ($payload['unchanged'] ?? 0),
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 10), // Return first 10 errors
                'message' => $importMessage,
                'type' => 'success',
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
        $outcomes = [];
        $reviewErrors = [];
        foreach ($parsed['rows'] as $index => $record) {
            try {
                $outcome = $this->storeImportRow($record, $request->boolean('events_only'), $forceNewClients, $request->boolean('update_existing'));
                $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
                $imported++;
            } catch (\Throwable $e) {
                if (!$request->boolean('update_existing')) {
                    throw $e;
                }
                $parsed['skipped']++;
                if (count($reviewErrors) < 10) {
                    $reviewErrors[] = 'Row '.($index + 1).': '.$e->getMessage();
                }
            }
        }

        $message = 'Successfully imported ' . $imported . ' event(s).';
        if ($parsed['skipped'] > 0) {
            $message .= ' Skipped ' . $parsed['skipped'] . ' invalid row(s).';
        }
        if ($request->boolean('update_existing')) {
            $message = $this->importUpdateSummary($outcomes, $parsed['skipped']);
            $message .= $reviewErrors ? ' Review: '.implode(' ', $reviewErrors) : '';
        }

        $this->archiveImportedFile($file, $parsed['rows']);
        $this->logImportSummary($file->getClientOriginalName(), $outcomes, $parsed['skipped'], $request->boolean('update_existing'));

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
        $storedName = 'transaction-events_' . $timestamp . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;
        $destination = 'transaction-events-archive/' . $storedName;

        $file->storeAs('transaction-events-archive', $storedName, 'local');

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

    private function importUpdateSummary(array $outcomes, int $skipped): string
    {
        return sprintf('Import complete: %d created, %d updated, %d unchanged, %d skipped.',
            $outcomes['created'] ?? 0, $outcomes['updated'] ?? 0, $outcomes['unchanged'] ?? 0, $skipped);
    }

    private function logImportSummary(string $filename, array $outcomes, int $skipped, bool $updateExisting): void
    {
        app(\App\Services\ActivityLogger::class)->record(
            'events_imported',
            'Imported '.$filename.'. '.$this->importUpdateSummary($outcomes, $skipped),
            [
                'filename' => $filename,
                'mode' => $updateExisting ? 'Update Matching Records' : 'Create records',
                'created' => (int) ($outcomes['created'] ?? 0),
                'updated' => (int) ($outcomes['updated'] ?? 0),
                'unchanged' => (int) ($outcomes['unchanged'] ?? 0),
                'skipped' => $skipped,
            ],
            ['type' => 'TransactionEvent']
        );
    }

    private function importMatchKey(array $record): string
    {
        $values = [];
        foreach (['full_name', 'client_category', 'transaction_category', 'transaction_type'] as $field) {
            $value = $record[$field] ?? '';
            $values[] = mb_strtolower(trim((string) $value));
        }
        $eventDate = trim((string) ($record['event_date'] ?? ''));
        $values[] = $eventDate === '' ? '' : \Carbon\Carbon::parse($eventDate)->toDateString();
        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    private function matchingImportEvents(array $record, bool $lock = false): Collection
    {
        if (trim((string) ($record['event_date'] ?? '')) === '') {
            return collect();
        }
        $key = $this->importMatchKey($record);
        $query = TransactionEvent::whereDate('event_date', \Carbon\Carbon::parse($record['event_date'])->toDateString());
        foreach (['full_name', 'client_category', 'transaction_category', 'transaction_type'] as $field) {
            $value = mb_strtolower(trim((string) ($record[$field] ?? '')));
            if ($value === '') {
                return collect();
            }
            $query->whereRaw("LOWER(TRIM({$field})) = ?", [$value]);
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->get()->filter(fn ($event) => $this->importMatchKey($event->getAttributes()) === $key);
    }

    private function updateMatchingImportRow(array $record): string
    {
        foreach (['full_name', 'client_category', 'transaction_category', 'transaction_type', 'event_date'] as $field) {
            if (trim((string) ($record[$field] ?? '')) === '') {
                throw new \RuntimeException('Full name, client category, transaction category, transaction type, and event date are required when updating matching records.');
            }
        }
        $matches = $this->matchingImportEvents($record, true);
        if ($matches->count() > 1) {
            throw new \RuntimeException('Multiple existing records match this row. Resolve the duplicates before updating.');
        }
        $event = $matches->first();
        if (!$event) {
            throw new \RuntimeException('No existing event matches the full name, client category, transaction category, transaction type, and event date. No record was created.');
        }
        return $this->setEventStatus($event, 'Claimed') ? 'updated' : 'unchanged';
    }

    private function setEventStatus(TransactionEvent $event, string $status): bool
    {
        $history = $event->transferred_transaction_id
            ? TransactionHistory::whereKey($event->transferred_transaction_id)->lockForUpdate()->first()
            : null;
        if ($event->transferred_at && !$history) {
            throw new \RuntimeException('The linked transaction history is missing. Review this record before updating.');
        }
        $changed = $event->status !== $status;
        if ($history) {
            $changed = $changed || $history->status !== $status;
            $history->status = $status;
            if ($history->isDirty()) {
                $history->save();
            }
        }
        $event->status = $status;
        if ($event->isDirty()) {
            $event->save();
        }
        return $changed;
    }

    private function storeImportRow(array $record, bool $eventsOnly, bool $forceNewClient = false, bool $updateExisting = false): string
    {
        // A failed row must roll back both its data and its audit entries.
        return DB::transaction(fn () => $this->persistImportRow($record, $eventsOnly, $forceNewClient, $updateExisting));
    }

    private function persistImportRow(array $record, bool $eventsOnly, bool $forceNewClient, bool $updateExisting): string
    {
        if ($updateExisting) {
            return $this->updateMatchingImportRow($record);
        }
        if (!$eventsOnly) {
            $this->createTransactionHistoryFromImportRow($record, $forceNewClient);
            return 'created';
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
            'imported_by' => auth()->user()?->name ?? 'System',
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);
        return 'created';
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
                'status' => 'Pending',
                'source' => 'import',
                'clerk' => auth()->user()?->name ?? 'System',
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
                'imported_by' => auth()->user()?->name ?? 'System',
                'transferred_at' => now(),
                'transferred_transaction_id' => $transactionHistory->id,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create transaction history: ' . $e->getMessage(), 0, $e);
        }
    }

    private function findOrCreateClientForEvent(string $fullName, array $record, ?string $birthDate = null): Client
    {
        $client = $this->findClientForImport($fullName, $birthDate)
            ?? $this->createClientForImportRow($fullName, $record, $birthDate);
        return $client;
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
            'status' => $event->status,
            'source' => 'transfer',
            'clerk' => $event->imported_by ?: (auth()->user()?->name ?? 'System'),
            'description' => 'Transferred from event record.',
        ]);

        $event->update([
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        app(\App\Services\ActivityLogger::class)->record(
            'event_transferred',
            "Transferred event #{$event->id} ({$event->full_name}) to transaction {$history->transaction_id}.",
            ['event_id' => $event->id, 'full_name' => $event->full_name, 'transaction_id' => $history->transaction_id],
            $event
        );

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
                    $properties = $log->properties;
                    if (is_string($properties)) {
                        $properties = json_decode($properties, true);
                    }
                    $properties = is_array($properties) ? $properties : [];
                    return (int) ($properties['event_id'] ?? 0) === (int) $event->id;
                });

            if ($audit) {
                $transaction = TransactionHistory::find($audit->subject_id);
                $transactionId = $transaction?->id;
            }
        }

        if ($transaction === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No transferred transaction was found for this event.'], 422);
            }

            return $redirect->with('error', 'No transferred transaction was found for this event.');
        }

        if (TransactionRequirement::query()->where('transaction_id', $transaction->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This transfer cannot be undone because the transaction has uploaded requirements.',
                ], 422);
            }

            return $redirect->with('error', 'This transfer cannot be undone because the transaction has uploaded requirements.');
        }

        $linkedTransactionId = $transaction->transaction_id;
        $transaction->delete();
        $event->update([
            'status' => 'Pending',
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

        $message = 'Transfer undone. Transaction '.$linkedTransactionId.' was removed and the event is pending again.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'event_id' => (int) $event->id,
                'message' => $message,
            ]);
        }

        return $redirect->with('success', $message);
    }

    /**
     * Transfer one event via JSON for bulk UI flows.
     */
    public function transferOne(Request $request)
    {
        $request->validate([
            'event_id' => ['required', 'integer'],
        ]);

        $result = DB::transaction(function () use ($request) {
            $event = TransactionEvent::whereKey($request->integer('event_id'))->lockForUpdate()->first();
            if (! $event) {
                abort(404, 'The selected event id is invalid.');
            }

            if ($event->transferred_at !== null) {
                return ['success' => false, 'message' => 'This event has already been transferred.'];
            }

            // Match Force Create All: every event receives its own new client,
            // even when another client already has the same name.
            $client = $this->createClientForImportRow(
                $event->full_name,
                $event->getAttributes(),
                $event->birth_date?->format('Y-m-d')
            );

            $history = TransactionHistory::create([
                'client_id' => $client->client_id,
                'client_category' => $event->client_category ?? '',
                'transaction_id' => $this->nextTransactionIdForClient($client->client_id),
                'transaction_date' => $event->event_date?->format('Y-m-d') ?? now()->toDateString(),
                'category' => $event->transaction_category ?? '',
                'type' => $event->transaction_type ?? '',
                'events_transaction_type' => $event->transaction_type ?? '',
                'status' => $event->status,
                'source' => 'transfer-one',
                'clerk' => $event->imported_by ?: (auth()->user()?->name ?? 'System'),
                'description' => 'Transferred from event record.',
            ]);

            $event->update([
                'transferred_at' => now(),
                'transferred_transaction_id' => $history->id,
            ]);

            return [
                'success' => true,
                'created_client' => true,
                'transaction_id' => $history->transaction_id,
                'event_id' => $event->id,
                'full_name' => $event->full_name,
            ];
        });

        if ($result['success']) {
            app(\App\Services\ActivityLogger::class)->record(
                'event_force_created',
                "Force Create Client: created a new client and transaction {$result['transaction_id']} for event #{$result['event_id']} ({$result['full_name']}).",
                ['event_id' => $result['event_id'], 'full_name' => $result['full_name'], 'transaction_id' => $result['transaction_id']],
                ['type' => 'TransactionEvent', 'id' => $result['event_id']]
            );
        }

        return response()->json($result, $result['success'] ? 200 : 422);
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

        if ($transferred > 0) {
            app(\App\Services\ActivityLogger::class)->record(
                'events_transfer_selected',
                "Transfer Selected: created {$transferred} new transaction(s). Skipped {$skipped} event(s).",
                [
                    'transferred' => $transferred,
                    'skipped' => $skipped,
                    'select_all' => $request->boolean('select_all'),
                ],
                ['type' => 'TransactionEvent']
            );
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
        $removedIds = [];

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
                    'status' => 'Pending',
                    'transferred_at' => null,
                    'transferred_transaction_id' => null,
                ]);
                $removedIds[] = (int) $event->id;
                $skipped++;
                continue;
            }

            if (TransactionRequirement::query()->where('transaction_id', $transaction->id)->exists()) {
                $skipped++;
                continue;
            }

            $transaction->delete();
            $event->update([
                'status' => 'Pending',
                'transferred_at' => null,
                'transferred_transaction_id' => null,
            ]);
            $removedIds[] = (int) $event->id;
            $undone++;
        }

        if ($undone > 0) {
            app(\App\Services\ActivityLogger::class)->record(
                'events_transfer_undone',
                "Undo Transfer Selected: undid transfer for {$undone} event(s). Skipped {$skipped} event(s).",
                ['undone' => $undone, 'skipped' => $skipped],
                ['type' => 'TransactionEvent']
            );
        }

        return response()->json([
            'success' => true,
            'undone' => $undone,
            'skipped' => $skipped,
            'removed_ids' => $removedIds,
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
        DB::transaction(function () use ($ids) {
            TransactionEvent::whereIn('id', $ids)->lockForUpdate()->chunkById(200, function ($events) {
                foreach ($events as $event) {
                    $event->update(['not_duplicate' => true]);
                }
            });
        });

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

    public function destroy(Request $request, TransactionEvent $event)
    {
        if (auth()->user()?->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        if ($event->transferred_at !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Event #'.$event->id.' is already transferred and cannot be deleted.',
                ], 422);
            }

            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #' . $event->id . ' is already transferred and cannot be deleted.');
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

        $message = "Event #{$eventId} ({$fullName}) deleted successfully.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'count' => 1,
                'deleted_ids' => [(int) $eventId],
                'message' => $message,
            ]);
        }

        return redirect()->route('transaction-events.index')->with('success', $message);
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
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No matching pending events to delete.'], 422);
            }

            return redirect()->route('transaction-events.index')
                ->with('error', 'No matching pending events to delete.');
        }

        $ids = $events->pluck('id')->all();
        $ids = DB::transaction(function () use ($ids) {
            $deletedIds = [];
            TransactionEvent::whereIn('id', $ids)->whereNull('transferred_at')->lockForUpdate()
                ->chunkById(200, function ($events) use (&$deletedIds) {
                    foreach ($events as $event) {
                        $event->delete();
                        $deletedIds[] = $event->id;
                    }
                });
            return $deletedIds;
        });
        $count = count($ids);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_bulk_deleted',
            'description' => "Bulk-deleted {$count} pending transaction event(s) from the Import Events list.",
            'subject_type' => 'TransactionEvent',
            'subject_id' => null,
            'properties' => [
                'event_ids' => $ids,
                'count' => $count,
                'select_all' => $request->boolean('select_all'),
                'filters' => $request->only([
                    'search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to',
                    'event_date_from', 'event_date_to',
                    'duplicate_names', 'client_category', 'transaction_category', 'transaction_type',
                ]),
            ],
        ]);

        $message = "Deleted {$count} pending event(s). This cannot be undone.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'count' => $count,
                'deleted_ids' => array_map('intval', $ids),
                'message' => $message,
            ]);
        }

        return redirect()->route('transaction-events.index')->with('success', $message);
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
    private function transferSinglePendingEvent(TransactionEvent $event, bool $forceNewClient = false): array
    {
        return DB::transaction(function () use ($event, $forceNewClient) {
            $event = TransactionEvent::whereKey($event->id)->lockForUpdate()->first();
            if ($event === null || $event->transferred_at !== null) {
                return ['success' => false, 'created_client' => false];
            }

            $birthDate = $event->birth_date?->format('Y-m-d');
            $client = $forceNewClient ? null : $this->findClientForImport($event->full_name, $birthDate);
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
                'status' => $event->status,
                'source' => $forceNewClient ? 'transfer-one' : 'transfer',
                'clerk' => $event->imported_by ?: (auth()->user()?->name ?? 'System'),
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
            'forceNewClients' => $request->boolean('force_new_clients'),
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

        $session = $this->loadTransferSession($request->input('token'));

        if ($session === null) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer session not found. Please start the transfer again.',
            ], 404);
        }

        $forceNewClients = (bool) ($session['forceNewClients'] ?? false);
        $limit = min(max((int) $request->input('limit', 200), 1), $forceNewClients ? 500 : 1000);

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
                $result = $this->transferSinglePendingEvent($event, $forceNewClients);

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

        if ($successCount > 0) {
            $forceNewClients = (bool) ($session['forceNewClients'] ?? false);
            app(\App\Services\ActivityLogger::class)->record(
                $forceNewClients ? 'events_force_created_all' : 'events_transfer_selected',
                ($forceNewClients ? 'Force Create All' : 'Transfer Selected')
                    .": transferred {$successCount} event(s)."
                    .($createdClients > 0 ? " Auto-created {$createdClients} new client(s)." : '')
                    .($skippedCount > 0 ? " Skipped {$skippedCount} event(s)." : ''),
                [
                    'transferred' => $successCount,
                    'created_clients' => $createdClients,
                    'skipped' => $skippedCount,
                    'force_new_clients' => $forceNewClients,
                ],
                ['type' => 'TransactionEvent']
            );
        }

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
