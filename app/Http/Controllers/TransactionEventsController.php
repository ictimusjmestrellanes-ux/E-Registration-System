<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
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
    public function recordsDuplicates(Request $request)
    {
        if (!feature_allowed('Events Records Duplicates')) {
            abort(404);
        }

        $cacheKey = 'records_duplicates_v1';
        $cacheTtl = now()->addSeconds(self::DUPLICATE_REVIEW_CACHE_TTL);

        $groups = Cache::remember($cacheKey, $cacheTtl, function () {
            $base = fn () => TransactionEvent::whereNotNull('transferred_at')
                ->whereNotNull('full_name')
                ->where('full_name', '<>', '');

            ['exact' => $exactGroups, 'likely' => $likelyGroups] = $this->computeRecordDuplicateGroups();

            // Similar spelling groups
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

            return [
                'exact' => $exactGroups,
                'likely' => $likelyGroups,
                'similar' => $similarGroups,
            ];
        });

        $exactGroups = $groups['exact'];
        $likelyGroups = $groups['likely'];
        $similarGroups = $groups['similar'];

        $perPage = $this->duplicateEventPerPage($request);
        $exact = $this->filterDuplicateEventGroups($request, $exactGroups, 'exact_page', $perPage, 'rexact-tab');
        $likely = $this->filterDuplicateEventGroups($request, $likelyGroups, 'likely_page', $perPage, 'rlikely-tab');
        $similar = $this->filterDuplicateEventGroups($request, $similarGroups, 'similar_page', $perPage, 'rsimilar-tab');

        return view('pages.transaction_events.recordsDuplicates', array_merge(
            $this->duplicateEventFilterOptions($exactGroups, $likelyGroups, $similarGroups),
            [
                'exactGroups' => $exact['paginator'],
                'exactGroupsTotal' => $exact['total'],
                'exactRecordsTotal' => $exact['records'],
                'likelyGroups' => $likely['paginator'],
                'likelyGroupsTotal' => $likely['total'],
                'likelyRecordsTotal' => $likely['records'],
                'similarGroups' => $similar['paginator'],
                'similarGroupsTotal' => $similar['total'],
                'similarRecordsTotal' => $similar['records'],
                'perPage' => $perPage,
            ]
        ));
    }

    /**
     * Compute exact + likely duplicate groups for transferred records.
     *
     * Grouping runs over lightweight plain rows (single query, no Eloquent
     * hydration), and full models are loaded afterwards only for the grouped
     * ids. Combo order matches eventLikelyComboKeys() so group priority is
     * unchanged.
     *
     * @return array{exact: Collection, likely: Collection}
     */
    private function computeRecordDuplicateGroups(): array
    {
        // [name, client_category, transaction_category, transaction_type, event_date]
        $norm = [];
        $light = DB::table('transaction_events')
            ->whereNotNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->selectRaw("id, TRIM(full_name) AS full_name, TRIM(COALESCE(client_category, '')) AS client_category, TRIM(COALESCE(transaction_category, '')) AS transaction_category, TRIM(COALESCE(transaction_type, '')) AS transaction_type, DATE_FORMAT(event_date, '%Y-%m-%d') AS event_date")
            ->get();

        foreach ($light as $r) {
            $norm[$r->id] = [
                strtolower(trim($r->full_name)),
                strtolower(trim($r->client_category)),
                strtolower(trim($r->transaction_category)),
                strtolower(trim($r->transaction_type)),
                $r->event_date,
            ];
        }
        unset($light);

        // Exact groups: same key as exactEventDuplicateKey(), all five fields present.
        $exactBuckets = [];
        foreach ($norm as $id => $f) {
            if ($f[1] === '' || $f[4] === null || $f[2] === '' || $f[3] === '') {
                continue;
            }
            $exactBuckets[$f[0] . '|' . $f[1] . '|' . $f[2] . '|' . $f[3] . '|' . $f[4]][] = $id;
        }

        $exactIdGroups = [];
        $exactIds = [];
        foreach ($exactBuckets as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $exactIdGroups[] = $ids;
            foreach ($ids as $id) {
                $exactIds[$id] = true;
            }
        }
        unset($exactBuckets);

        // Likely groups: same six combos as eventLikelyComboKeys(), in order.
        $comboFields = [
            [0, 4, 2],
            [0, 4, 3],
            [0, 2, 3],
            [0, 4],
            [0, 3],
            [0, 2],
        ];
        $seenIdSets = [];
        $likelyIdGroups = [];
        foreach ($comboFields as $fields) {
            $buckets = [];
            foreach ($norm as $id => $f) {
                if (isset($exactIds[$id])) {
                    continue;
                }
                $parts = [];
                foreach ($fields as $k) {
                    $parts[] = $f[$k];
                }
                $buckets[implode('|', $parts)][] = $id;
            }
            foreach ($buckets as $ids) {
                if (count($ids) < 2) {
                    continue;
                }
                sort($ids, SORT_NUMERIC);
                $setKey = implode(',', $ids);
                if (isset($seenIdSets[$setKey])) {
                    continue;
                }
                $seenIdSets[$setKey] = true;
                $likelyIdGroups[] = $ids;
            }
            unset($buckets);
        }
        unset($norm);

        // Hydrate full models (with transaction id for display) for grouped ids only.
        $groupedIds = array_keys($exactIds);
        foreach ($likelyIdGroups as $ids) {
            foreach ($ids as $id) {
                $groupedIds[] = $id;
            }
        }
        $groupedIds = array_values(array_unique($groupedIds));

        $keyed = $groupedIds
            ? TransactionEvent::with('transferredTransaction:id,transaction_id')
                ->whereIn('id', $groupedIds)
                ->get()
                ->keyBy('id')
            : collect();

        $toPayload = fn (array $ids) => $this->eventGroupPayload(
            collect($ids)->map(fn ($id) => $keyed[$id])->filter()->values()
        );

        return [
            'exact' => collect($exactIdGroups)->map($toPayload)->values(),
            'likely' => collect($likelyIdGroups)->map($toPayload)->values(),
        ];
    }

    /**
     * Filter cached duplicate-event groups (a group is kept when ANY member
     * matches ALL active filters) and paginate the result for one tab with a
     * numbered LengthAwarePaginator (own page query param + tab fragment).
     *
     * @return array{paginator: LengthAwarePaginator, total: int, records: int}
     */
    private function filterDuplicateEventGroups(Request $request, Collection $groups, string $pageParam, int $perPage, string $fragment): array
    {
        $keyword = strtolower(trim((string) $request->input('search', '')));
        $clientCategory = strtolower(trim((string) $request->input('client_category', '')));
        $transactionCategory = strtolower(trim((string) $request->input('transaction_category', '')));
        $transactionType = strtolower(trim((string) $request->input('transaction_type', '')));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $memberMatches = function ($e) use ($keyword, $clientCategory, $transactionCategory, $transactionType, $dateFrom, $dateTo) {
            if ($keyword !== '') {
                $haystack = strtolower(implode(' ', [
                    $e->full_name ?? '',
                    $e->client_category ?? '',
                    $e->transaction_category ?? '',
                    $e->transaction_type ?? '',
                    $e->event_date?->format('Y-m-d') ?? '',
                    $e->event_date?->format('M d, Y') ?? '',
                    $e->id ?? '',
                    optional($e->transferredTransaction)->transaction_id ?? '',
                ]));
                if (! str_contains($haystack, $keyword)) {
                    return false;
                }
            }
            if ($clientCategory !== '' && strtolower(trim((string) ($e->client_category ?? ''))) !== $clientCategory) {
                return false;
            }
            if ($transactionCategory !== '' && strtolower(trim((string) ($e->transaction_category ?? ''))) !== $transactionCategory) {
                return false;
            }
            if ($transactionType !== '' && strtolower(trim((string) ($e->transaction_type ?? ''))) !== $transactionType) {
                return false;
            }
            $eventDate = $e->event_date?->format('Y-m-d') ?? '';
            if ($dateFrom !== '' && ($eventDate === '' || $eventDate < $dateFrom)) {
                return false;
            }
            if ($dateTo !== '' && ($eventDate === '' || $eventDate > $dateTo)) {
                return false;
            }

            return true;
        };

        $hasFilters = $keyword !== '' || $clientCategory !== '' || $transactionCategory !== ''
            || $transactionType !== '' || $dateFrom !== '' || $dateTo !== '';

        $filtered = $hasFilters
            ? $groups->filter(fn ($g) => collect($g['events'] ?? [])->contains($memberMatches))->values()
            : $groups->values();

        $total = $filtered->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $request->input($pageParam, 1)), $pages);

        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageParam]
        );
        $paginator = $paginator->withQueryString()->fragment($fragment);

        return [
            'paginator' => $paginator,
            'total' => $total,
            'records' => $filtered->sum('total'),
        ];
    }

    /**
     * Distinct dropdown options built from cached groups' members.
     *
     * @return array{filterClientCategories: array, filterTransactionCategories: array, filterTransactionTypes: array}
     */
    private function duplicateEventFilterOptions(Collection ...$allGroups): array
    {
        $ccats = [];
        $tcats = [];
        $ttypes = [];
        foreach ($allGroups as $groups) {
            foreach ($groups as $g) {
                foreach ($g['events'] ?? [] as $e) {
                    $cc = trim((string) ($e->client_category ?? ''));
                    $tc = trim((string) ($e->transaction_category ?? ''));
                    $tt = trim((string) ($e->transaction_type ?? ''));
                    if ($cc !== '') {
                        $ccats[strtolower($cc)] = $cc;
                    }
                    if ($tc !== '') {
                        $tcats[strtolower($tc)] = $tc;
                    }
                    if ($tt !== '') {
                        $ttypes[strtolower($tt)] = $tt;
                    }
                }
            }
        }
        asort($ccats);
        asort($tcats);
        asort($ttypes);

        return [
            'filterClientCategories' => array_values($ccats),
            'filterTransactionCategories' => array_values($tcats),
            'filterTransactionTypes' => array_values($ttypes),
        ];
    }

    private function duplicateEventPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 25;
    }

    private function exactEventDuplicateKey(TransactionEvent $event): string
    {
        return implode('|', [
            strtolower(trim($event->full_name)),
            strtolower(trim((string) $event->client_category)),
            strtolower(trim((string) $event->transaction_category)),
            strtolower(trim((string) $event->transaction_type)),
            $event->event_date?->format('Y-m-d'),
        ]);
    }

    private function eventLikelyComboKeys(): array
    {
        return [
            // (Full Name, Event Date, Transaction Category)
            fn (TransactionEvent $e) => implode('|', [
                strtolower(trim($e->full_name)),
                $e->event_date?->format('Y-m-d'),
                strtolower(trim((string) $e->transaction_category)),
            ]),
            // (Full Name, Event Date, Transaction Type)
            fn (TransactionEvent $e) => implode('|', [
                strtolower(trim($e->full_name)),
                $e->event_date?->format('Y-m-d'),
                strtolower(trim((string) $e->transaction_type)),
            ]),
            // (Full Name, Transaction Category, Transaction Type)
            fn (TransactionEvent $e) => implode('|', [
                strtolower(trim($e->full_name)),
                strtolower(trim((string) $e->transaction_category)),
                strtolower(trim((string) $e->transaction_type)),
            ]),
            // (Full Name, Event Date)
            fn (TransactionEvent $e) => implode('|', [
                strtolower(trim($e->full_name)),
                $e->event_date?->format('Y-m-d'),
            ]),
            // (Full Name, Transaction Type)
            fn (TransactionEvent $e) => implode('|', [
                strtolower(trim($e->full_name)),
                strtolower(trim((string) $e->transaction_type)),
            ]),
            // (Full Name, Transaction Category)
            fn (TransactionEvent $e) => implode('|', [
                strtolower(trim($e->full_name)),
                strtolower(trim((string) $e->transaction_category)),
            ]),
        ];
    }

    public function duplicateReview(Request $request)
    {
        $cacheKey = 'duplicate_review_v1';
        $cacheTtl = now()->addSeconds(self::DUPLICATE_REVIEW_CACHE_TTL);

        $groups = Cache::remember($cacheKey, $cacheTtl, function () {
            return [
                'exact' => $this->findEventExactDuplicates(),
                'likely' => $this->findEventLikelyDuplicates(),
                'similar' => $this->findEventSimilarSpellingDuplicates(),
            ];
        });

        $exactGroups = $groups['exact'];
        $likelyGroups = $groups['likely'];
        $similarGroups = $groups['similar'];

        $perPage = $this->duplicateEventPerPage($request);
        $exact = $this->filterDuplicateEventGroups($request, $exactGroups, 'exact_page', $perPage, 'exact-tab');
        $likely = $this->filterDuplicateEventGroups($request, $likelyGroups, 'likely_page', $perPage, 'likely-tab');
        $similar = $this->filterDuplicateEventGroups($request, $similarGroups, 'similar_page', $perPage, 'similar-tab');

        $notDuplicates = TransactionEvent::where('not_duplicate', true)
            ->whereNull('transferred_at')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('pages.transaction_events.duplicateReview', array_merge(
            $this->duplicateEventFilterOptions($exactGroups, $likelyGroups, $similarGroups),
            compact('notDuplicates'),
            [
                'exactGroups' => $exact['paginator'],
                'exactGroupsTotal' => $exact['total'],
                'exactRecordsTotal' => $exact['records'],
                'likelyGroups' => $likely['paginator'],
                'likelyGroupsTotal' => $likely['total'],
                'likelyRecordsTotal' => $likely['records'],
                'similarGroups' => $similar['paginator'],
                'similarGroupsTotal' => $similar['total'],
                'similarRecordsTotal' => $similar['records'],
                'perPage' => $perPage,
            ]
        ));
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
        $this->clearDuplicateCaches();

        return redirect()->route('transaction-events.removed-duplicates')
            ->with('success', "Event #{$event->id} ({$event->full_name}) removed as duplicate.");
    }

    public function markGroupNotDuplicate(\Illuminate\Http\Request $request)
    {
        $authUser = auth()->user();
        if ($authUser->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        $ids = array_filter(
            array_map('intval', explode(',', $request->input('event_ids', ''))),
            fn ($id) => $id > 0
        );

        if (empty($ids)) {
            return redirect()->route('transaction-events.duplicate-review')
                ->with('error', 'No events specified.');
        }

        $events = TransactionEvent::whereIn('id', $ids)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();

        foreach ($events as $event) {
            $event->update(['not_duplicate' => true]);

            ActivityLog::create([
                'user_id'      => $authUser->id,
                'action'       => 'event_marked_not_duplicate',
                'description'  => "Marked transaction event #{$event->id} ({$event->full_name}) as not a duplicate (group action).",
                'subject_type' => 'TransactionEvent',
                'subject_id'   => $event->id,
                'properties'   => json_encode(
                    ['event_id' => $event->id, 'full_name' => $event->full_name, 'group_action' => true],
                    JSON_INVALID_UTF8_SUBSTITUTE
                ),
            ]);
        }
        $this->clearDuplicateCaches();

        $count = $events->count();

        return redirect()->route('transaction-events.duplicate-review')
            ->with('success', "{$count} event(s) in the group marked as not a duplicate and removed from review.");
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
        $this->clearDuplicateCaches();

        return redirect()->back()
            ->with('success', "Event #{$event->id} ({$event->full_name}) restored to duplicate review.");
    }

    private function findEventExactDuplicates(): Collection
    {
        // Exact Match: Full Name + Client Category + Transaction Category + Transaction Type + Event Date
        // All 5 fields must be present and non-empty for a true exact match.
        $events = TransactionEvent::query()
            ->whereNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->get();

        return $events
            ->filter(fn ($e) =>
                trim((string) $e->client_category) !== '' &&
                $e->event_date !== null &&
                trim((string) $e->transaction_category) !== '' &&
                trim((string) $e->transaction_type) !== ''
            )
            ->groupBy(fn ($e) => $this->exactEventDuplicateKey($e))
            ->filter(fn ($g) => $g->count() > 1)
            ->map(fn ($items) => $this->eventGroupPayload($items))
            ->values();
    }

    private function findEventLikelyDuplicates(): Collection
    {
        $events = TransactionEvent::query()
            ->whereNull('transferred_at')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->where('not_duplicate', false)
            ->get();

        $exactGroups = $this->findEventExactDuplicates();
        $exactEventIds = $exactGroups->flatMap(fn ($g) => $g['events'])->pluck('id')->flip();

        $likelyCandidates = $events->reject(fn ($e) => $exactEventIds->has($e->id));

        // Track which event-ID sets have already been grouped to avoid duplicates
        // across combo key variations (e.g. a pair that matches both combo 1 & combo 4
        // should only appear once).
        $seenIdSets = [];
        $likelyGroups = collect();

        foreach ($this->eventLikelyComboKeys() as $comboKey) {
            $likelyCandidates
                ->groupBy(fn ($e) => $comboKey($e))
                ->filter(fn ($g) => $g->count() > 1)
                ->each(function ($items) use (&$seenIdSets, &$likelyGroups) {
                    $ids = $items->pluck('id')->sort()->values()->implode(',');
                    if (isset($seenIdSets[$ids])) {
                        return; // skip — already captured by an earlier (stricter) combo
                    }
                    $seenIdSets[$ids] = true;
                    $likelyGroups->push($this->eventGroupPayload($items));
                });
        }

        return $likelyGroups->values();
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

        return response()->download(Storage::disk('local')->path($path), $filename);
    }

    public function preview(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:102400',
        ]);

        $result = $this->parseImportFile($request->file('csv_file'));

        if (! empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        $totalRows = count($result['rows']);
        $previewRows = array_slice($result['rows'], 0, 100);

        return response()->json([
            'success' => true,
            'rows' => $previewRows,
            'total' => $totalRows,
            'preview_count' => count($previewRows),
            'is_truncated' => $totalRows > 100,
            'skipped' => $result['skipped'],
            'skipped_rows' => array_slice($result['skipped_rows'] ?? [], 0, 50),
            'skipped_total' => count($result['skipped_rows'] ?? []),
        ]);
    }

    /**
     * Check how many rows of the uploaded file already exist in transaction
     * history (same client + category/type + event date when provided).
     */
    public function importDuplicatesCheck(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:102400',
        ]);

        $result = $this->parseImportFile($request->file('csv_file'));

        if (!empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        $duplicates = [];

        foreach ($this->findExistingDuplicateIndexes($result['rows']) as $index) {
            $row = $result['rows'][$index];
            $duplicates[] = [
                'full_name' => $row['full_name'],
                'event_date' => $row['event_date'] ?? '',
                'transaction_category' => $row['transaction_category'] ?? '',
                'transaction_type' => $row['transaction_type'] ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'total_rows' => count($result['rows']),
            'duplicates_count' => count($duplicates),
            'duplicates' => array_slice($duplicates, 0, 100),
            'duplicates_truncated' => count($duplicates) > 100,
        ]);
    }

    /**
     * Return the indexes of $rows that already exist in the system
     * (i.e. a matching Transaction History for an existing client, or a
     * matching row already in the Import Events module).
     */
    private function findExistingDuplicateIndexes(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        // Preload existing history joined with client names into an in-memory
        // hash set keyed by the same 5-field duplicate validation key.
        $historyRows = DB::table('transaction_history')
            ->join('clients', 'transaction_history.client_id', '=', 'clients.client_id')
            ->selectRaw("TRIM(clients.first_name) as first_name, TRIM(clients.middle_name) as middle_name, TRIM(clients.last_name) as last_name, transaction_history.client_category as client_category, transaction_history.category as category, transaction_history.type as type, DATE_FORMAT(transaction_history.transaction_date, '%Y-%m-%d') as tx_date")
            ->get();

        $historyLookup = [];

        foreach ($historyRows as $h) {
            $fullName = preg_replace(
                '/\s+/',
                ' ',
                trim(implode(' ', array_filter([
                    (string) $h->first_name,
                    (string) $h->middle_name,
                    (string) $h->last_name,
                ])))
            );

            $historyLookup[$this->importDuplicateValidationKey(
                $fullName,
                (string) $h->client_category,
                (string) TransactionHistory::normalizeCategory((string) $h->category),
                (string) $h->type,
                (string) $h->tx_date
            )] = true;
        }

        // Preload existing transaction events into an in-memory hash set using
        // the same 5-field duplicate validation key.
        $eventRows = DB::table('transaction_events')
            ->selectRaw("TRIM(full_name) as full_name, client_category as client_category, transaction_category as cat, transaction_type as type, DATE_FORMAT(event_date, '%Y-%m-%d') as ev_date")
            ->get();

        $eventLookup = [];

        foreach ($eventRows as $ev) {
            $eventLookup[$this->importDuplicateValidationKey(
                (string) $ev->full_name,
                (string) ($ev->client_category ?? ''),
                (string) TransactionHistory::normalizeCategory((string) ($ev->cat ?? '')),
                (string) ($ev->type ?? ''),
                (string) $ev->ev_date
            )] = true;
        }

        $indexes = [];

        foreach ($rows as $index => $row) {
            $fullName = $row['full_name'] ?? '';

            if (empty($fullName)) {
                continue;
            }

            $fullName = preg_replace('/\s+/', ' ', trim($fullName));
            $rawCat = TransactionHistory::normalizeCategory($row['transaction_category'] ?? '');
            $cat = strtolower(trim((string) $rawCat));
            $evDate = ! empty($row['event_date']) ? (string) $row['event_date'] : '';

            $key = $this->importDuplicateValidationKey(
                $fullName,
                (string) ($row['client_category'] ?? ''),
                $cat,
                (string) ($row['transaction_type'] ?? ''),
                $evDate
            );

            if (isset($historyLookup[$key]) || isset($eventLookup[$key])) {
                $indexes[] = $index;
            }
        }

        return $indexes;
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
                $cells .= "'<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$value}</t></is></c>'";
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
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:102400',
        ]);

        $result = $this->parseImportFile($request->file('csv_file'));

        if (! empty($result['errors'])) {
            return back()->withErrors(['csv_file' => $result['errors'][0]]);
        }

        $rows = $result['rows'];
        $archiveFile = '';
        $hasDuplicateRows = collect($rows)->contains(fn ($row) => ! empty($row['duplicate']));
        $skippedDuplicates = 0;
        // "Import All Anyway": import every row as clients + transaction
        // history even if duplicates exist — no skipping, no staging.
        $forceDirect = $request->boolean('force_direct');

        if ($request->boolean('events_only') && ! $forceDirect) {
            // "Import Anyway": skip rows that already exist in the system and
            // import the remainder as real clients + transaction history.
            $duplicateIndexes = $this->findExistingDuplicateIndexes($rows);
            $skippedDuplicates = count($duplicateIndexes);

            if ($skippedDuplicates > 0) {
                $rows = array_values(array_diff_key($rows, array_flip($duplicateIndexes)));
            }
        }

        $imported = count($rows);

        if ($imported > 0) {
            if ($hasDuplicateRows && ! $request->boolean('events_only') && ! $forceDirect) {
                $this->storeImportedEventsOnly($rows);
            } else {
                $this->processImportedEvents($rows, $forceDirect);
            }
            $archiveFile = $this->storeImportedEventArchive($rows, $request->file('csv_file')->getClientOriginalName());
        }

        $skipped = $result['skipped'];
        $message = match (true) {
            $forceDirect => "Imported {$imported} record(s) including duplicate row(s).",
            $skippedDuplicates > 0 => "Imported {$imported} record(s) and skipped {$skippedDuplicates} duplicate row(s) that already existed in the system.",
            $hasDuplicateRows => "Imported {$imported} event(s) to the event list because duplicate rows were found in the selected file.",
            default => "Successfully imported {$imported} event(s).",
        };

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        if (! empty($archiveFile)) {
            $message .= " Archived CSV as {$archiveFile}.";
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_imported',
            'description' => "Imported {$imported} transaction event(s) from file".($request->file('csv_file')->getClientOriginalName() ? ' ('.$request->file('csv_file')->getClientOriginalName().')' : '').'.',
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
        // "Import Anyway" also produces transferred records, so go there too.
        // "Import All Anyway" always produces transferred records as well.
        if (($forceDirect || ! $hasDuplicateRows || $skippedDuplicates > 0) && $imported > 0) {
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

    // Cached longer because grouping 24k+ records is expensive; the cache is
    // cleared automatically on every import/transfer/undo/review mutation
    // via clearDuplicateCaches(), so results stay fresh.
    private const DUPLICATE_REVIEW_CACHE_TTL = 1800; // seconds (30 minutes)

    private function clearDuplicateCaches(): void
    {
        Cache::forget('duplicate_review_v1');
        Cache::forget('records_duplicates_v1');
    }

    public function prepareImport(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:102400',
        ]);

        $result = $this->parseImportFile($request->file('csv_file'));

        if (! empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        $rows = $result['rows'];
        $hasDuplicateRows = collect($rows)->contains(fn ($row) => ! empty($row['duplicate']));
        $eventsOnly = $request->boolean('events_only');
        // "Import All Anyway": bypass every duplicate rule and import all rows
        // as clients + transaction history.
        $forceDirect = $request->boolean('force_direct');
        $skippedDuplicates = 0;

        if ($eventsOnly && ! $forceDirect) {
            // "Import Anyway": skip rows that already exist in the system
            // (Transaction History or Import Events) and import the remaining
            // rows as real clients + transaction history, rather than staging
            // them (or the skipped duplicates) in the Import Events list.
            $duplicateIndexes = $this->findExistingDuplicateIndexes($rows);
            $skippedDuplicates = count($duplicateIndexes);

            if ($skippedDuplicates > 0) {
                $rows = array_values(array_diff_key($rows, array_flip($duplicateIndexes)));
            }
        }

        $token = md5(uniqid((string) auth()->id(), true));
        $this->cleanupStaleImportSessions();

        Storage::disk('local')->makeDirectory(self::IMPORT_SESSION_DIR);
        Storage::disk('local')->put(
            self::IMPORT_SESSION_DIR.'/'.$token.'.json',
            json_encode([
                'mode' => $forceDirect ? 'direct' : (($hasDuplicateRows && ! $eventsOnly) ? 'events_only' : 'direct'),
                'force_events_only' => $eventsOnly && ! $forceDirect,
                'force_direct' => $forceDirect,
                'rows' => $rows,
                'original_filename' => $request->file('csv_file')->getClientOriginalName(),
                'skipped' => $result['skipped'],
                'skipped_duplicates' => $skippedDuplicates,
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

        $limit = min(max((int) $request->input('limit', 500), 1), 1000);
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
            $this->processImportedEvents($slice, (bool) ($sessionData['force_direct'] ?? false));
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
        $skippedDuplicates = $sessionData['skipped_duplicates'] ?? 0;
        $isEventsOnly = $sessionData['mode'] === 'events_only';
        $forceEventsOnly = $sessionData['force_events_only'] ?? false;
        $forceDirect = $sessionData['force_direct'] ?? false;
        $archiveFile = '';

        if ($imported > 0) {
            $archiveFile = $this->storeImportedEventArchive($rows, $sessionData['original_filename']);
        }

        $message = match (true) {
            $forceDirect => "Imported {$imported} record(s) including duplicate row(s).",
            $skippedDuplicates > 0 => "Imported {$imported} record(s) and skipped {$skippedDuplicates} duplicate row(s) that already existed in the system.",
            $forceEventsOnly => "Imported {$imported} event(s) to the Import Events list because the data already exists in the system.",
            $isEventsOnly => "Imported {$imported} event(s) to the event list because duplicate rows were found in the selected file.",
            default => "Successfully imported {$imported} event(s).",
        };

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        if (! empty($archiveFile)) {
            $message .= " Archived CSV as {$archiveFile}.";
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'events_imported',
            'description' => "Imported {$imported} transaction event(s) from file".($sessionData['original_filename'] ? ' ('.$sessionData['original_filename'].')' : '').'.',
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

    private function saveImportSession(string $token, array $data): void
    {
        Storage::disk('local')->put(
            self::IMPORT_SESSION_DIR.'/'.$token.'.json',
            json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE)
        );
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

        // Each transferred event becomes its own client (distinct by Full Name,
        // Client Category, Transaction Category, Transaction Type, Event Date).
        // Existing Client records do not carry the transaction identity, so a
        // new client is always created for this event.
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
            'category' => $this->transactionCategoryForHistory($event->transaction_category),
            'type' => $this->transactionCategoryForHistory($event->transaction_category),
            'events_transaction_type' => $this->transactionCategoryForHistory($event->transaction_type),
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

            $this->clearDuplicateCaches();

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
                    ->findOrFail($event->id);

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
        $this->clearDuplicateCaches();

        return redirect()->route('transaction-events.records')
            ->with('success', 'Transfer undone. Transaction '.$transactionId.' was removed and the event is pending again.');
    }

    /**
     * Resolve the set of pending TransactionEvents targeted by a "Transfer
     * Selected" request (either explicit event_ids or the filtered select-all
     * population).
     */
    private function resolveTransferSelectedEvents(Request $request): \Illuminate\Support\Collection
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
            } elseif ($request->boolean('exclude_duplicates')) {
                // When using select all, always exclude duplicates unless specifically viewing duplicates
                $query->whereNotIn('full_name', $this->duplicateFullNamesList());
            }

            return $query->get();
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('event_ids', []))));

        if (empty($ids)) {
            return collect();
        }

        return TransactionEvent::query()
            ->whereIn('id', $ids)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();
    }

    /**
     * Transfer a single pending event into a client + transaction history.
     * Returns ['success' => bool, 'created_client' => bool].
     *
     * $clientCache (keyed by lower(first)|lower(last)) lets a whole batch
     * reuse the same resolved/created client without re-querying the DB.
     */
    private function transferSingleEvent(TransactionEvent $event, array &$clientCache = []): array
    {
        if (empty($event->transaction_category) || empty($event->transaction_type)) {
            return ['success' => false, 'created_client' => false];
        }

        $nameParts = $this->splitFullName($event->full_name);
        $clientKey = $this->normalizeImportedClientKey([
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'client_category' => $event->client_category ?? '',
            'transaction_category' => (string) TransactionHistory::normalizeCategory($event->transaction_category ?? ''),
            'transaction_type' => $event->transaction_type ?? '',
            'event_date' => $event->event_date?->format('Y-m-d') ?? '',
        ]);

        if (! isset($clientCache[$clientKey])) {
            // A distinct 5-field row always becomes its own client. Existing
            // Client records do not store the transaction identity, so a new
            // client is created for each unique (Full Name, Client Category,
            // Transaction Category, Transaction Type, Event Date) combination.
            $client = null;
            $clientCache[$clientKey] = $client;
        }

        $client = $clientCache[$clientKey];

        $createdClient = false;
        if (! $client) {
            $client = $this->createClientFromImportedEvent([
                'full_name' => $event->full_name,
                'age' => $event->age,
                'contact_no' => $event->contact_no,
                'address' => $event->address,
                'birth_date' => $event->birth_date?->format('Y-m-d'),
                'client_category' => $event->client_category,
            ]);
            $clientCache[$clientKey] = $client;
            $createdClient = true;

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
            'category' => $this->transactionCategoryForHistory($event->transaction_category),
            'type' => $this->transactionCategoryForHistory($event->transaction_category),
            'events_transaction_type' => $this->transactionCategoryForHistory($event->transaction_type),
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

        return ['success' => true, 'created_client' => $createdClient];
    }

    /**
     * Transfer a batch of pending event ids. Returns aggregate counters.
     */
    private function transferEventIds(array $ids, array &$clientCache = []): array
    {
        $events = TransactionEvent::query()
            ->whereIn('id', $ids)
            ->whereNull('transferred_at')
            ->where('not_duplicate', false)
            ->get();

        $successCount = 0;
        $skippedCount = 0;
        $createdClients = 0;

        foreach ($events as $event) {
            $result = $this->transferSingleEvent($event, $clientCache);

            if ($result['success']) {
                $successCount++;

                if ($result['created_client']) {
                    $createdClients++;
                }
            } else {
                $skippedCount++;
            }
        }

        return compact('successCount', 'skippedCount', 'createdClients');
    }

    // ----------------------------------------------------------------------
    // Chunked "Transfer Selected" (progress-bar friendly). The target event
    // ids are resolved once and cached to a temp session file, then processed
    // in slices so the browser can show a live percentage while transferring.
    // ----------------------------------------------------------------------

    public function prepareTransferSelected(Request $request)
    {
        $events = $this->resolveTransferSelectedEvents($request);

        if ($events->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Selected transaction events are already transferred or no longer available.',
            ], 422);
        }

        $token = md5(uniqid((string) auth()->id(), true));
        $this->cleanupStaleImportSessions();

        Storage::disk('local')->makeDirectory(self::IMPORT_SESSION_DIR);
        $this->saveImportSession($token, [
            'ids' => $events->pluck('id')->values()->all(),
            'successCount' => 0,
            'skippedCount' => 0,
            'createdClients' => 0,
            'clientCache' => [],
            'mode' => 'transfer',
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'total' => $events->count(),
        ]);
    }

    public function processTransferChunk(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $request->validate([
            'token' => 'required|string',
            'offset' => 'required|integer|min:0',
        ]);

        $limit = min(max((int) $request->input('limit', 200), 1), 1000);
        $sessionData = $this->loadImportSession($request->input('token'));

        if ($sessionData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer session not found. Please start the transfer again.',
            ], 404);
        }

        $ids = $sessionData['ids'];
        $offset = (int) $request->input('offset');
        $slice = array_slice($ids, $offset, $limit);

        if (! empty($slice)) {
            // Hydrate the shared client cache (key => client_id) into Client
            // models so identical 5-field rows share one client across chunks.
            $peerIds = array_values(array_filter(array_map('intval', array_values($sessionData['clientCache'] ?? []))));
            $hydratedClients = $peerIds
                ? Client::whereIn('id', $peerIds)->get()->keyBy('id')
                : collect();
            $clientCache = [];
            foreach (($sessionData['clientCache'] ?? []) as $key => $id) {
                if (isset($hydratedClients[$id])) {
                    $clientCache[$key] = $hydratedClients[$id];
                }
            }

            $result = $this->transferEventIds($slice, $clientCache);

            $sessionData['successCount'] = ($sessionData['successCount'] ?? 0) + $result['successCount'];
            $sessionData['skippedCount'] = ($sessionData['skippedCount'] ?? 0) + $result['skippedCount'];
            $sessionData['createdClients'] = ($sessionData['createdClients'] ?? 0) + $result['createdClients'];

            $persistedCache = [];
            foreach (($clientCache ?? []) as $key => $client) {
                if ($client instanceof Client) {
                    $persistedCache[$key] = $client->id;
                }
            }
            $sessionData['clientCache'] = $persistedCache;

            $this->saveImportSession($request->input('token'), $sessionData);
        }

        $processed = $offset + count($slice);

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'total' => count($ids),
            'done' => $processed >= count($ids),
        ]);
    }

    public function finishTransferSelected(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $sessionData = $this->loadImportSession($request->input('token'));

        if ($sessionData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer session not found. Please start the transfer again.',
            ], 404);
        }

        $successCount = $sessionData['successCount'] ?? 0;
        $skippedCount = $sessionData['skippedCount'] ?? 0;
        $createdClients = $sessionData['createdClients'] ?? 0;

        $archiveFile = '';
        if ($successCount > 0) {
            $transferredEvents = TransactionEvent::query()
                ->whereIn('id', $sessionData['ids'])
                ->whereNotNull('transferred_at')
                ->get();
            $archiveFile = $this->storeArchivedTransactionEvents($transferredEvents);

            TransactionHistory::flushDashboardCache();
            $this->clearDuplicateCaches();
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

        Storage::disk('local')->delete(self::IMPORT_SESSION_DIR.'/'.$request->input('token').'.json');

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

    public function transferSelected(Request $request)
    {
        $events = $this->resolveTransferSelectedEvents($request);

        if ($events->isEmpty()) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Selected transaction events are already transferred or no longer available.');
        }

        $result = $this->transferEventIds($events->pluck('id')->all());

        $successCount = $result['successCount'];
        $skippedCount = $result['skippedCount'];
        $createdClients = $result['createdClients'];

        $archiveFile = '';
        if ($successCount > 0) {
            $transferredEvents = TransactionEvent::query()
                ->whereIn('id', $events->pluck('id'))
                ->whereNotNull('transferred_at')
                ->get();
            $archiveFile = $this->storeArchivedTransactionEvents($transferredEvents);
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
            $this->clearDuplicateCaches();

            return redirect()->route('transaction-events.records')->with('success', $message);
        }

        return redirect()->route('transaction-events.index')->with('error', $message);
    }

    public function destroy(TransactionEvent $event)
    {
        $authUser = auth()->user();
        if ($authUser->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        if (! is_null($event->transferred_at)) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #'.$event->id.' is already approved/transferred and cannot be deleted.');
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
            'properties' => json_encode(['event_id' => $eventId, 'full_name' => $fullName], JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return redirect()->route('transaction-events.index')
            ->with('success', "Event #{$eventId} ({$fullName}) deleted successfully.");
    }

    private function nextTransferredTransactionId(string $clientId): string
    {
        $prefix = $clientId.'-'.now()->format('y').'-';

        // First call for this client-year hits the DB once; subsequent calls
        // in the same request increment the cached sequence instead.
        if (! array_key_exists($prefix, $this->transactionSequenceCache)) {
            $maxSequence = TransactionHistory::query()
                ->where('transaction_id', 'like', $prefix.'%')
                ->pluck('transaction_id')
                ->reduce(function (int $max, string $transactionId) use ($prefix) {
                    $suffix = substr($transactionId, strlen($prefix));

                    return ctype_digit($suffix) ? max($max, (int) $suffix) : $max;
                }, 0);

            $this->transactionSequenceCache[$prefix] = $maxSequence;
        }

        $sequence = ++$this->transactionSequenceCache[$prefix];

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

    /**
     * Parse an uploaded import file (CSV, TXT, or Excel) into normalized rows.
     * Excel (.xlsx) is read natively via ZipArchive + SimpleXML so no extra
     * composer dependency is required. Legacy binary .xls files are not
     * supported and produce a clear error asking for .xlsx instead.
     */
    private function parseImportFile($file): array
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: ''));

        if ($extension === '') {
            $extension = strtolower((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseXlsx($file);
        }

        return $this->parseCsv($file);
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
            return preg_replace('/[^a-z0-9]+/', '_', trim(strtolower((string) $column)));
        }, $header);
        $header = array_filter($header);
        $header = array_values($header);

        if (! in_array('full_name', $header)) {
            fclose($handle);
            @unlink($tempPath);

            return ['errors' => ['Missing required column: full_name.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $matrix = [];

        while (($row = fgetcsv($handle)) !== false) {
            $matrix[] = $row;
        }

        fclose($handle);
        @unlink($tempPath);

        $built = $this->buildImportRowsFromMatrix($header, $matrix);

        return [
            'errors' => [],
            'rows' => $built['rows'],
            'total' => count($built['rows']),
            'skipped' => $built['skipped'],
            'skipped_rows' => $built['skipped_rows'],
        ];
    }

    private function parseXlsx($file): array
    {
        $matrix = $this->readXlsxMatrix($file->getPathname());

        if (is_string($matrix)) {
            return ['errors' => [$matrix], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        if (empty($matrix)) {
            return ['errors' => ['The Excel file is empty or has no header row.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $header = array_map(function ($column) {
            return preg_replace('/[^a-z0-9]+/', '_', trim(strtolower((string) $column)));
        }, array_values($matrix[0]));
        $header = array_filter($header);
        $header = array_values($header);

        if (! in_array('full_name', $header)) {
            return ['errors' => ['Missing required column: full_name.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $built = $this->buildImportRowsFromMatrix($header, array_slice($matrix, 1));

        return [
            'errors' => [],
            'rows' => $built['rows'],
            'total' => count($built['rows']),
            'skipped' => $built['skipped'],
            'skipped_rows' => $built['skipped_rows'],
        ];
    }

    /**
     * Read the first worksheet of an .xlsx file into a 2-D string matrix.
     * Returns the matrix, or an error message string on failure.
     *
     * @return array|string
     */
    private function readXlsxMatrix(string $path): array|string
    {
        if (! class_exists(\ZipArchive::class)) {
            return 'Excel support is unavailable on this server. Please upload a CSV file instead.';
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return 'Unable to read the Excel file. If this is an old .xls file, please save it as .xlsx and try again.';
        }

        try {
            $shared = [];
            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

            if ($sharedStringsXml !== false) {
                $strings = @simplexml_load_string($sharedStringsXml);

                if ($strings !== false) {
                    $strings->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                    foreach ($strings->xpath('//m:si') ?: [] as $si) {
                        $si->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                        $chunks = [];

                        foreach ($si->xpath('.//m:t') ?: [] as $node) {
                            $chunks[] = (string) $node;
                        }

                        $shared[] = implode('', $chunks);
                    }
                }
            }

            $sheetPath = null;

            if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
                $sheetPath = 'xl/worksheets/sheet1.xml';
            } else {
                for ($i = 2; $i <= 50; $i++) {
                    $candidate = "xl/worksheets/sheet{$i}.xml";

                    if ($zip->locateName($candidate) !== false) {
                        $sheetPath = $candidate;

                        break;
                    }
                }
            }

            if ($sheetPath === null) {
                return 'No worksheet found in the Excel file.';
            }

            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                return 'Unable to read the Excel file.';
            }

            $sheet = @simplexml_load_string($sheetXml);

            if ($sheet === false) {
                return 'Unable to read the Excel file.';
            }

            $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $matrix = [];

            foreach ($sheet->xpath('//m:row') ?: [] as $row) {
                $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                $cells = [];
                $maxCol = -1;

                foreach ($row->xpath('./m:c') ?: [] as $cell) {
                    $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $col = $this->xlsxColumnIndex((string) ($cell['r'] ?? ''));

                    if ($col < 0) {
                        continue;
                    }

                    $maxCol = max($maxCol, $col);
                    $type = (string) ($cell['t'] ?? '');
                    $valueNodes = $cell->xpath('./m:v');

                    if ($type === 's') {
                        $value = ($valueNodes && isset($valueNodes[0]))
                            ? ($shared[(int) trim((string) $valueNodes[0])] ?? '')
                            : '';
                    } elseif ($type === 'inlineStr') {
                        $chunks = [];

                        foreach ($cell->xpath('./m:is/m:t') ?: [] as $node) {
                            $chunks[] = (string) $node;
                        }

                        $value = implode('', $chunks);
                    } elseif ($type === 'b') {
                        $value = ($valueNodes && trim((string) $valueNodes[0]) === '1') ? '1' : '0';
                    } elseif ($type === 'e') {
                        $value = '';
                    } else {
                        $value = ($valueNodes && isset($valueNodes[0])) ? trim((string) $valueNodes[0]) : '';
                    }

                    $cells[$col] = $value;
                }

                if ($maxCol < 0) {
                    continue;
                }

                $rowData = [];
                $allBlank = true;

                for ($i = 0; $i <= $maxCol; $i++) {
                    $cellValue = $cells[$i] ?? '';
                    $rowData[] = $cellValue;

                    if (trim((string) $cellValue) !== '') {
                        $allBlank = false;
                    }
                }

                if ($allBlank) {
                    continue;
                }

                $matrix[] = $rowData;
            }

            return $matrix;
        } finally {
            $zip->close();
        }
    }

    private function xlsxColumnIndex(string $cellRef): int
    {
        if (! preg_match('/^([A-Za-z]+)/', $cellRef, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * Convert an Excel serial date number to a Y-m-d string when the raw
     * value looks like one. Plain text values pass through untouched so CSV
     * behavior is unchanged.
     */
    private function normalizeMaybeExcelSerialDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return $value === '' ? null : $value;
        }

        $serial = (float) $value;

        // Excel serials ~20000-60000 cover 1954-2064. Anything outside that
        // (ages, phone numbers) is left alone.
        if ($serial < 20000 || $serial > 60000) {
            return $value;
        }

        return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
    }

    /**
     * Shared row normalizer for CSV and Excel matrices. Applies trimming,
     * age/date validation, duplicate-key counting, and skip tracking so both
     * formats behave identically downstream.
     */
    private function buildImportRowsFromMatrix(array $header, array $matrix): array
    {
        $rows = [];
        $skippedRows = [];
        $lineNumber = 1;
        $eventKeyCounts = [];
        $tempRows = [];

        foreach ($matrix as $row) {
            $lineNumber++;

            if (! is_array($row)) {
                $row = [$row];
            }

            $row = array_map(fn ($cell) => $this->utf8Encode(is_scalar($cell) || $cell === null ? (string) $cell : ''), array_values($row));
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

            $birthDate = $this->parseImportedDate($this->normalizeMaybeExcelSerialDate($data['birth_date'] ?? $data['birthdate'] ?? null));
            $rawEventDate = $data['event_date'] ?? $data['eventdate'] ?? null;
            $eventDate = $this->parseImportedDate($this->normalizeMaybeExcelSerialDate($rawEventDate));
            if (($rawEventDate ?? '') !== '' && $eventDate === null) {
                $skippedRows[] = [
                    'line' => $lineNumber,
                    'reason' => 'Invalid event_date value',
                    'data' => $data,
                ];

                continue;
            }
            $eventKey = $this->importDuplicateValidationKey(
                $fullName,
                $data['client_category'] ?? '',
                $data['transaction_category'] ?? '',
                $data['transaction_type'] ?? '',
                $eventDate
            );

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

        return [
            'rows' => $rows,
            'skipped' => count($skippedRows),
            'skipped_rows' => $skippedRows,
        ];
    }

    private function importDuplicateValidationKey(
        string $fullName,
        string $clientCategory,
        string $transactionCategory,
        string $transactionType,
        ?string $eventDate
    ): string {
        $normalizedFullName = preg_replace('/\s+/', ' ', trim(strtolower($fullName)));
        $normalizedClientCategory = strtolower(trim($clientCategory) ?: '*');
        $normalizedTransactionCategory = strtolower(trim($transactionCategory) ?: '*');
        $normalizedTransactionType = strtolower(trim($transactionType) ?: '*');
        $normalizedEventDate = $eventDate ? trim(strtolower($eventDate)) : '*';

        return implode('|', [
            $normalizedFullName,
            $normalizedClientCategory,
            $normalizedTransactionCategory,
            $normalizedTransactionType,
            $normalizedEventDate,
        ]);
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

    /**
     * Create clients + transaction history for the given rows.
     *
     * When $forceNewClients is true (Force Create All / Import All Anyway),
     * every row gets its own brand-new client — no in-batch sharing and no
     * reuse of existing clients — so client and transaction counts stay 1:1.
     */
    private function processImportedEvents(array $events, bool $forceNewClients = false): void
    {
        $existingClients = $forceNewClients ? [] : $this->loadExistingClientsForImportedEvents($events);
        $clientMap = [];

        foreach ($events as $event) {
            $clientKey = $forceNewClients ? null : $this->importedEventClientKey($event);

            if ($clientKey !== null && isset($clientMap[$clientKey])) {
                $client = $clientMap[$clientKey];
            } else {
                $client = $forceNewClients
                    ? null
                    : $this->findExistingClientForImportedEvent($event, $existingClients);

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
                'client_category' => $client->sector ?? '',
                'transaction_category' => '',
                'transaction_type' => '',
                'event_date' => '',
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
            'client_category' => $event['client_category'] ?? '',
            'transaction_category' => (string) TransactionHistory::normalizeCategory($event['transaction_category'] ?? ''),
            'transaction_type' => $event['transaction_type'] ?? '',
            'event_date' => $event['event_date'] ?? '',
        ]);
    }

    private function normalizeImportedClientKey(array $data): string
    {
        $fullName = preg_replace(
            '/\s+/',
            ' ',
            strtolower(trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')))
        );
        $norm = fn ($v) => strtolower(trim((string) $v)) ?: '*';

        return implode('|', [
            $fullName,
            $norm($data['client_category'] ?? ''),
            $norm($data['transaction_category'] ?? ''),
            $norm($data['transaction_type'] ?? ''),
            $norm($data['event_date'] ?? ''),
        ]);
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

    /**
     * Preserve the event/import transaction category in Transaction History
     * instead of collapsing unknown categories into the generic 'others' key.
     */
    private function transactionCategoryForHistory(?string $category): string
    {
        $category = trim((string) $category);

        return $category === '' ? '' : strtoupper($category);
    }

    private function createTransactionHistoryFromImportedEvent(Client $client, array $event): TransactionHistory
    {
        return TransactionHistory::create([
            'client_id' => $client->client_id,
            'client_category' => $event['client_category'] ?: $client->sector,
            'transaction_id' => $this->nextTransferredTransactionId($client->client_id),
            // Use the CSV row's event date when provided.
            'transaction_date' => $event['event_date'] ?? now(),
            'category' => $this->transactionCategoryForHistory($event['transaction_category'] ?? ''),
            'type' => $this->transactionCategoryForHistory($event['transaction_category'] ?? ''),
            'events_transaction_type' => $this->transactionCategoryForHistory($event['transaction_type'] ?? ''),
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