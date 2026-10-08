<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\DuplicateClientScan;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateReviewController extends Controller
{
    private const EXACT_KEY = "CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(last_name)))";

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, DuplicateClientScan $scan)
    {
        abort_unless(feature_allowed('Duplicate Clients Review'), 404);
        $perPage = $this->duplicateClientPerPage($request);
        $similar = $scan->results();
        $groups = $this->normalizeSimilarIds($similar ?? []);
        $ids = $groups->flatten()->unique()->values();
        $results = $this->hydrateVisibleDuplicateGroups([
            // Likely full-name groups are already covered by the broader exact
            // first/last-name rule, as in the previous implementation.
            'likely' => $this->paginateDuplicateClientGroups($request, collect(), null, 'likely_page', $perPage, 'likely-tab'),
            'similar' => $this->paginateDuplicateClientGroups($request, $groups,
                $this->matchingDuplicateClientIds($request, $ids), 'similar_page', $perPage, 'similar-tab'),
        ]);
        $results['exact'] = $this->paginateExactGroups($request, $perPage);
        $data = ['perPage' => $perPage, 'similarScanPending' => $similar === null];
        foreach ($results as $category => $result) {
            $data[$category.'Groups'] = $result['paginator'];
            $data[$category.'GroupsTotal'] = $result['total'];
            $data[$category.'RecordsTotal'] = $result['records'];
        }

        return view('pages.duplicates.index', array_merge($this->filterOptions($ids), $data));
    }

    public function scanSimilar(DuplicateClientScan $scan)
    {
        abort_unless(feature_allowed('Duplicate Clients Review'), 404);
        @ini_set('memory_limit', '512M');

        return response()->json($scan->advance());
    }

    private function normalizeSimilarIds(array $groups): Collection
    {
        $existing = [];
        foreach (collect($groups)->flatten()->unique()->chunk($this->duplicateClientQueryChunkSize()) as $chunk) {
            foreach (DB::table('clients')->whereIn('id', $chunk->all())->pluck('id') as $id) {
                $existing[(int) $id] = true;
            }
        }

        return collect($groups)->map(fn ($ids) => array_values(array_filter(
            array_unique(array_map('intval', $ids)), fn ($id) => isset($existing[$id])
        )))->filter(fn ($ids) => count($ids) > 1)->values();
    }

    /**
     * Indexed existence checks avoid multiplying clients by their transactions.
     * Legacy prefixes are read in one uncorrelated subquery, never via a
     * transaction_id LIKE scan for every client.
     */
    private function transactionClients()
    {
        $legacyPrefix = DB::connection()->getDriverName() === 'sqlite'
            ? "substr(transaction_id, 1, instr(transaction_id, '-') - 1)"
            : "SUBSTRING_INDEX(transaction_id, '-', 1)";
        return DB::table('clients')->where(function ($query) use ($legacyPrefix) {
            $query->whereExists(function ($transactions) {
                $transactions->selectRaw('1')->from('transaction_history')
                    ->whereColumn('transaction_history.client_id', 'clients.client_id');
            })->orWhereIn('clients.client_id', DB::table('transaction_history')->selectRaw($legacyPrefix)
                ->whereNull('client_id')->where('transaction_id', 'like', '%-%'));
        })->select('clients.*');
    }

    private function exactGroupQuery(?Request $request = null)
    {
        $eligible = DB::query()->fromSub($this->transactionClients(), 'clients');
        if ($request && $request->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to'])) {
            $matching = DB::query()->fromSub($this->transactionClients(), 'clients');
            $this->applyClientFilters($matching, $request);
            // A matching member includes the whole group, not only that member.
            $eligible->whereIn(DB::raw(self::EXACT_KEY), $matching->selectRaw(self::EXACT_KEY));
        }

        return $eligible->selectRaw(self::EXACT_KEY.' as name_key, COUNT(*) as records, MIN(id) as first_id')
            ->groupBy('name_key')->havingRaw('COUNT(*) > 1');
    }

    private function applyClientFilters($query, Request $request): void
    {
        foreach (['gender', 'civil_status', 'city', 'barangay'] as $field) {
            $value = strtolower(trim((string) $request->input($field, '')));
            if ($value !== '') {
                $query->whereRaw("LOWER(TRIM(COALESCE($field, ''))) = ?", [$value]);
            }
        }
        if ($from = trim((string) $request->input('date_from', ''))) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = trim((string) $request->input('date_to', ''))) {
            $query->whereDate('created_at', '<=', $to);
        }
        $keyword = strtolower(trim((string) $request->input('search', '')));
        if ($keyword === '') {
            return;
        }
        $fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'client_id', 'email', 'contact',
            'contact_2', 'address', 'barangay', 'city', 'province', 'sector', 'gender', 'civil_status', 'birth_date'];
        $parts = array_map(fn ($field) => "COALESCE($field, '')", $fields);
        if (DB::connection()->getDriverName() === 'sqlite') {
            $cases = '';
            foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $index => $month) {
                $cases .= " WHEN '".sprintf('%02d', $index + 1)."' THEN '$month'";
            }
            $parts[] = "COALESCE((CASE strftime('%m', birth_date)$cases END) || strftime(' %d, %Y', birth_date), '')";
        } else {
            $parts[] = "COALESCE(DATE_FORMAT(birth_date, '%b %d, %Y'), '')";
        }
        $parts[] = 'id';
        // INSTR treats percent/underscore as literals, preserving substring search.
        $query->whereRaw("INSTR(LOWER(CONCAT_WS(' ', ".implode(', ', $parts).")), ?) > 0", [$keyword]);
    }

    private function paginateExactGroups(Request $request, int $perPage): array
    {
        $query = $this->exactGroupQuery($request);
        $totals = DB::query()->fromSub(clone $query, 'duplicate_groups')
            ->selectRaw('COUNT(*) as groups_total, COALESCE(SUM(records), 0) as records_total')->first();
        $total = (int) $totals->groups_total;
        $page = min(max(1, (int) $request->input('exact_page', 1)), max(1, (int) ceil($total / $perPage)));
        $keys = (clone $query)->orderBy('first_id')->forPage($page, $perPage)->pluck('name_key');
        $groups = collect();
        if ($keys->isNotEmpty()) {
            $case = 'CASE';
            $bindings = [];
            foreach ($keys as $key) {
                $case .= ' WHEN '.self::EXACT_KEY.' = ? THEN ?';
                array_push($bindings, $key, $key);
            }
            $members = Client::query()->fromSub($this->transactionClients(), 'clients')->select([
                'clients.id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status', 'sector', 'email', 'contact',
                'contact_2', 'address', 'province', 'city', 'barangay', 'photo_path', 'created_at',
            ])->whereIn(DB::raw(self::EXACT_KEY), $keys)
                ->selectRaw($case.' END as duplicate_name_key', $bindings)->orderBy('clients.id')->get()
                // Use the database's canonical key, including its Unicode and
                // accent collation, rather than regrouping differently in PHP.
                ->groupBy('duplicate_name_key');
            $groups = $keys->map(fn ($key) => $this->groupPayload($members->get($key, collect())));
        }
        $paginator = new LengthAwarePaginator($groups, $total, $perPage, $page, [
            'path' => $request->url(), 'pageName' => 'exact_page',
        ]);

        return ['paginator' => $paginator->withQueryString()->fragment('exact-tab'),
            'total' => $total, 'records' => (int) $totals->records_total];
    }

    private function filterOptions(Collection $similarIds): array
    {
        $fields = ['gender', 'civil_status', 'city', 'barangay'];
        $rows = DB::query()->fromSub($this->transactionClients(), 'clients')
            ->joinSub($this->exactGroupQuery(), 'duplicate_groups', function ($join) {
                $join->on(DB::raw(self::EXACT_KEY), '=', 'duplicate_groups.name_key');
            })->select($fields)->distinct()->get();
        foreach ($similarIds->chunk($this->duplicateClientQueryChunkSize()) as $chunk) {
            $rows = $rows->concat(DB::table('clients')->whereIn('id', $chunk->all())->select($fields)->distinct()->get());
        }
        $result = [];
        foreach (['gender' => 'filterGenders', 'civil_status' => 'filterCivilStatuses',
            'city' => 'filterCities', 'barangay' => 'filterBarangays'] as $field => $name) {
            $result[$name] = $rows->pluck($field)->map(fn ($value) => trim((string) $value))
                ->filter()->unique(fn ($value) => strtolower($value))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        }

        return $result;
    }

    /**
     * Load the small set of columns needed for active filters and return an ID
     * lookup. With no filters, no client detail rows are loaded at this stage.
     *
     * @return array<int, true>|null
     */
    private function matchingDuplicateClientIds(Request $request, Collection $ids): ?array
    {
        $keyword = strtolower(trim((string) $request->input('search', '')));
        $gender = strtolower(trim((string) $request->input('gender', '')));
        $civilStatus = strtolower(trim((string) $request->input('civil_status', '')));
        $city = strtolower(trim((string) $request->input('city', '')));
        $barangay = strtolower(trim((string) $request->input('barangay', '')));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $hasFilters = $keyword !== '' || $gender !== '' || $civilStatus !== ''
            || $city !== '' || $barangay !== '' || $dateFrom !== '' || $dateTo !== '';
        if (! $hasFilters || $ids->isEmpty()) {
            return $hasFilters ? [] : null;
        }

        $matches = [];
        foreach ($ids->chunk($this->duplicateClientQueryChunkSize()) as $chunk) {
            $clients = Client::query()->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'birth_date', 'gender', 'civil_status', 'sector', 'email', 'contact',
                'contact_2', 'address', 'province', 'city', 'barangay', 'created_at',
            ])->whereIn('id', $chunk->all())->get();

            foreach ($clients as $client) {
                if ($keyword !== '') {
                    $haystack = strtolower(implode(' ', [
                        $client->first_name ?? '', $client->middle_name ?? '',
                        $client->last_name ?? '', $client->suffix ?? '',
                        $client->client_id ?? '', $client->email ?? '',
                        $client->contact ?? '', $client->contact_2 ?? '',
                        $client->address ?? '', $client->barangay ?? '',
                        $client->city ?? '', $client->province ?? '',
                        $client->sector ?? '', $client->gender ?? '',
                        $client->civil_status ?? '',
                        $client->birth_date?->format('Y-m-d') ?? '',
                        $client->birth_date?->format('M d, Y') ?? '',
                        $client->id,
                    ]));
                    if (! str_contains($haystack, $keyword)) {
                        continue;
                    }
                }
                if ($gender !== '' && strtolower(trim((string) $client->gender)) !== $gender) {
                    continue;
                }
                if ($civilStatus !== '' && strtolower(trim((string) $client->civil_status)) !== $civilStatus) {
                    continue;
                }
                if ($city !== '' && strtolower(trim((string) $client->city)) !== $city) {
                    continue;
                }
                if ($barangay !== '' && strtolower(trim((string) $client->barangay)) !== $barangay) {
                    continue;
                }
                $createdAt = $client->created_at?->format('Y-m-d') ?? '';
                if ($dateFrom !== '' && ($createdAt === '' || $createdAt < $dateFrom)) {
                    continue;
                }
                if ($dateTo !== '' && ($createdAt === '' || $createdAt > $dateTo)) {
                    continue;
                }

                $matches[(int) $client->id] = true;
            }
        }

        return $matches;
    }

    /**
     * Filter lightweight membership groups and paginate before hydration.
     *
     * @param array<int, true>|null $matchingIds
     * @return array{paginator: LengthAwarePaginator, total: int, records: int}
     */
    private function paginateDuplicateClientGroups(
        Request $request,
        Collection $groups,
        ?array $matchingIds,
        string $pageParam,
        int $perPage,
        string $fragment
    ): array {
        $filtered = $matchingIds === null
            ? $groups->values()
            : $groups->filter(fn ($memberIds) => collect($memberIds)
                ->contains(fn ($id) => isset($matchingIds[(int) $id])))
                ->values();

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

        return [
            'paginator' => $paginator->withQueryString()->fragment($fragment),
            'total' => $total,
            'records' => $filtered->sum(fn ($memberIds) => count($memberIds)),
        ];
    }

    /**
     * Hydrate only the groups visible on the current page of each tab.
     *
     * @param array<string, array{paginator: LengthAwarePaginator, total: int, records: int}> $results
     */
    private function hydrateVisibleDuplicateGroups(array $results): array
    {
        $ids = collect($results)
            ->flatMap(fn ($result) => $result['paginator']->getCollection()->flatten())
            ->unique()
            ->values();

        $clients = collect();
        foreach ($ids->chunk($this->duplicateClientQueryChunkSize()) as $chunk) {
            foreach (Client::query()->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status', 'sector',
                'email', 'contact', 'contact_2', 'address',
                'province', 'city', 'barangay', 'photo_path', 'created_at',
            ])->whereIn('id', $chunk->all())->get() as $client) {
                $clients->put((int) $client->id, $client);
            }
        }

        foreach ($results as &$result) {
            $hydrated = $result['paginator']->getCollection()->map(function ($memberIds) use ($clients) {
                $members = collect($memberIds)
                    ->map(fn ($id) => $clients->get((int) $id))
                    ->filter()
                    ->values();

                return $this->groupPayload($members);
            })->filter(fn ($group) => $group['total'] > 1)->values();

            $result['paginator']->setCollection($hydrated);
        }
        unset($result);

        return $results;
    }

    private function duplicateClientPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 10;
    }

    private function duplicateClientQueryChunkSize(): int
    {
        // SQLite has a low placeholder limit in tests. MySQL can safely use a
        // larger batch, reducing round trips on production-sized client lists.
        return DB::connection()->getDriverName() === 'sqlite' ? 900 : 5000;
    }

    private function groupPayload($items): array
    {
        return [
            'total' => $items->count(),
            'clients' => $items,
            'created_at' => $items->min('created_at'),
        ];
    }
}
