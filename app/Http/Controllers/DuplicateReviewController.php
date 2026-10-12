<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateReviewController extends Controller
{
    private const FULL_NAME_KEY = "CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), LOWER(TRIM(COALESCE(suffix,''))))";
    private const EXACT_NAME_KEY = "CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(last_name)))";
    private const EXACT_MATCH_KEY = "CONCAT_WS('|', exact_name_key, client_category_key, transaction_category_key, transaction_type_key, event_date_key)";

    private ?bool $hasLegacyTransactionRows = null;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        abort_unless(feature_allowed('Duplicate Clients Review'), 404);
        $perPage = $this->duplicateClientPerPage($request);
        $exact = $this->paginateExactNameGroups($request, $perPage);
        // Match Full Name considers every client, so its membership, counts,
        // and pagination must not reuse Exact Match's transaction-only results.
        $fullName = $this->paginateNameGroups($request, $perPage, 'similar_page', 'similar-tab', false, self::FULL_NAME_KEY);
        $results = [
            'exact' => $exact,
            'likely' => $this->paginateInitialNameGroups($request, $perPage),
            // Keep the existing paginator/view variable names for old links.
            'similar' => $fullName,
        ];
        $data = ['perPage' => $perPage];
        foreach ($results as $category => $result) {
            $data[$category.'Groups'] = $result['paginator'];
            $data[$category.'GroupsTotal'] = $result['total'];
            $data[$category.'RecordsTotal'] = $result['records'];
        }

        $view = view('pages.duplicates.index', array_merge($this->filterOptions(), $data));
        if ($request->ajax()) {
            $html = $view->render();
            $fragment = Str::between(
                $html,
                '<!-- duplicate-clients-content:start -->',
                '<!-- duplicate-clients-content:end -->'
            );

            return response(trim($fragment));
        }

        return $view;
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

    private function nameClients(bool $transactionsOnly)
    {
        return $transactionsOnly ? $this->transactionClients() : DB::table('clients')->select('clients.*');
    }

    /**
     * One row per client and normalized transaction signature. Current rows
     * use the indexed client_id join; only unlinked legacy rows need to derive
     * ownership from transaction_id.
     */
    private function exactClientTransactionRows(bool $withClientDetails = true)
    {
        $addSignature = function ($query) use ($withClientDetails) {
            $query->select($withClientDetails ? ['clients.*'] : [
                'clients.id', 'clients.first_name', 'clients.last_name',
            ]);

            return $query
                ->selectRaw(self::EXACT_NAME_KEY.' as exact_name_key')
                ->selectRaw("LOWER(TRIM(COALESCE(transaction_history.client_category,''))) as client_category_key")
                ->selectRaw("COALESCE(DATE(transaction_history.transaction_date),'') as event_date_key")
                ->selectRaw("LOWER(TRIM(COALESCE(transaction_history.category,''))) as transaction_category_key")
                ->selectRaw("LOWER(TRIM(COALESCE(NULLIF(transaction_history.events_transaction_type,''), transaction_history.type, ''))) as transaction_type_key")
                ->distinct();
        };

        $current = $addSignature(DB::table('clients')
            ->join('transaction_history', 'transaction_history.client_id', '=', 'clients.client_id')
            ->whereNotNull('transaction_history.client_id')
            ->where('transaction_history.client_id', '<>', ''));

        $this->hasLegacyTransactionRows ??= DB::table('transaction_history')
            ->where(function ($query) {
                $query->whereNull('client_id')->orWhereRaw("TRIM(client_id) = ''");
            })
            ->where('transaction_id', 'like', '%-%')
            ->exists();
        if (!$this->hasLegacyTransactionRows) {
            return $current;
        }

        $legacyOwner = DB::connection()->getDriverName() === 'sqlite'
            ? "SUBSTR(transaction_history.transaction_id, 1, INSTR(transaction_history.transaction_id, '-') - 1)"
            : "SUBSTRING_INDEX(transaction_history.transaction_id, '-', 1)";
        $legacy = $addSignature(DB::table('clients')
            ->join('transaction_history', function ($join) use ($legacyOwner) {
                $join->on('clients.client_id', '=', DB::raw($legacyOwner));
            })
            ->where(function ($query) {
                $query->whereNull('transaction_history.client_id')
                    ->orWhereRaw("TRIM(transaction_history.client_id) = ''");
            })
            ->where('transaction_history.transaction_id', 'like', '%-%'));

        return $current->unionAll($legacy);
    }

    private function exactMatchGroupQuery(?Request $request = null)
    {
        $hasFilters = $request && $request->anyFilled([
            'search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to',
        ]);
        $eligible = DB::query()->fromSub($this->exactClientTransactionRows($hasFilters), 'clients');
        if ($hasFilters) {
            $matching = DB::query()->fromSub($this->exactClientTransactionRows(), 'clients');
            $this->applyClientFilters($matching, $request);
            $eligible->whereIn(DB::raw(self::EXACT_MATCH_KEY), $matching->selectRaw(self::EXACT_MATCH_KEY));
        }

        return $eligible->selectRaw(self::EXACT_MATCH_KEY.' as match_key, COUNT(*) as records, MIN(id) as first_id')
            ->groupBy('match_key')->havingRaw('COUNT(*) > 1');
    }

    private function nameGroupQuery(string $keyExpression, bool $transactionsOnly, ?Request $request = null)
    {
        $eligible = DB::query()->fromSub($this->nameClients($transactionsOnly), 'clients');
        if ($request && $request->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to'])) {
            $matching = DB::query()->fromSub($this->nameClients($transactionsOnly), 'clients');
            $this->applyClientFilters($matching, $request);
            // A matching member includes the whole group, not only that member.
            $eligible->whereIn(DB::raw($keyExpression), $matching->selectRaw($keyExpression));
        }

        return $eligible->selectRaw($keyExpression.' as name_key, COUNT(*) as records, MIN(id) as first_id')
            ->groupBy('name_key')->havingRaw('COUNT(*) > 1');
    }

    /**
     * Candidates whose first, last, and suffix match while one middle name is
     * an initial and another is a full name beginning with the same letter.
     */
    private function initialNameGroupQuery(?Request $request = null)
    {
        $expressions = $this->initialNameExpressions();
        $eligible = DB::query()->fromSub($this->transactionClients(), 'clients');
        if ($request && $request->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to'])) {
            $matching = DB::query()->fromSub($this->transactionClients(), 'clients');
            $this->applyClientFilters($matching, $request);
            // A matching member includes the whole likely-match group.
            $eligible->whereIn(DB::raw($expressions['key']), $matching->selectRaw($expressions['key']));
        }

        $length = DB::connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';

        return $eligible
            ->whereRaw($expressions['given_name']." <> ''")
            ->whereRaw("TRIM(COALESCE(last_name, '')) <> ''")
            ->whereRaw($expressions['middle_letter']." <> ''")
            ->selectRaw($expressions['key'].' as name_key, COUNT(*) as records, MIN(id) as first_id')
            ->groupBy('name_key')
            ->havingRaw("SUM(CASE WHEN $length({$expressions['compact_middle']}) = 1 THEN 1 ELSE 0 END) > 0")
            ->havingRaw('SUM(CASE WHEN '.$expressions['full_middle_condition'].' THEN 1 ELSE 0 END) > 0');
    }

    /**
     * Older rows sometimes store the full middle name at the end of first_name
     * ("Juan Santos" + blank middle_name), while abbreviated rows store
     * "Juan" + "S.". Canonicalize both layouts to the same candidate key.
     */
    private function initialNameExpressions(): array
    {
        $compactMiddle = "REPLACE(REPLACE(TRIM(COALESCE(middle_name,'')), '.', ''), ' ', '')";
        $hasEmbeddedMiddle = "($compactMiddle = '' AND INSTR(TRIM(COALESCE(first_name,'')), ' ') > 0)";
        $givenName = "LOWER(CASE WHEN $hasEmbeddedMiddle THEN SUBSTR(TRIM(first_name), 1, INSTR(TRIM(first_name), ' ') - 1) ELSE TRIM(COALESCE(first_name,'')) END)";
        $middleLetter = "LOWER(CASE WHEN $compactMiddle <> '' THEN SUBSTR($compactMiddle, 1, 1) WHEN $hasEmbeddedMiddle THEN SUBSTR(TRIM(SUBSTR(TRIM(first_name), INSTR(TRIM(first_name), ' ') + 1)), 1, 1) ELSE '' END)";
        $length = DB::connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';

        return [
            'compact_middle' => $compactMiddle,
            'full_middle_condition' => "$length($compactMiddle) > 1 OR $hasEmbeddedMiddle",
            'given_name' => $givenName,
            'middle_letter' => $middleLetter,
            'key' => "CONCAT_WS('|', $givenName, LOWER(TRIM(last_name)), LOWER(TRIM(COALESCE(suffix,''))), $middleLetter)",
        ];
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

    private function paginateNameGroups(
        Request $request,
        int $perPage,
        string $pageName,
        string $fragment,
        bool $transactionsOnly,
        string $keyExpression
    ): array
    {
        $query = $this->nameGroupQuery($keyExpression, $transactionsOnly, $request);
        $pageData = $this->groupPage($query, $request, $perPage, $pageName, 'name_key');
        $total = $pageData['total'];
        $page = $pageData['page'];
        $keys = $pageData['keys'];
        $groups = collect();
        if ($keys->isNotEmpty()) {
            $case = 'CASE';
            $bindings = [];
            foreach ($keys as $key) {
                $case .= ' WHEN '.$keyExpression.' = ? THEN ?';
                array_push($bindings, $key, $key);
            }
            $members = Client::query()->fromSub($this->nameClients($transactionsOnly), 'clients')->select([
                'clients.id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status', 'sector', 'email', 'contact',
                'contact_2', 'address', 'province', 'city', 'barangay', 'photo_path', 'created_at',
            ])->whereIn(DB::raw($keyExpression), $keys)
                ->selectRaw($case.' END as duplicate_name_key', $bindings)->orderBy('clients.id')->get()
                // Use the database's canonical key, including its Unicode and
                // accent collation, rather than regrouping differently in PHP.
                ->groupBy('duplicate_name_key');
            $groups = $keys->map(fn ($key) => $this->groupPayload($members->get($key, collect())));
        }
        return ['paginator' => $this->namePaginator($request, $groups, $total, $perPage, $page, $pageName, $fragment),
            'total' => $total, 'records' => $pageData['records']];
    }

    private function paginateExactNameGroups(Request $request, int $perPage): array
    {
        $query = $this->exactMatchGroupQuery($request);
        $pageData = $this->groupPage($query, $request, $perPage, 'exact_page', 'match_key');
        $total = $pageData['total'];
        $page = $pageData['page'];
        $keys = $pageData['keys'];
        $groups = collect();
        if ($keys->isNotEmpty()) {
            $case = 'CASE';
            $bindings = [];
            foreach ($keys as $key) {
                $case .= ' WHEN '.self::EXACT_MATCH_KEY.' = ? THEN ?';
                array_push($bindings, $key, $key);
            }
            $members = Client::query()->fromSub($this->exactClientTransactionRows(), 'clients')->select([
                'clients.id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status', 'sector', 'email', 'contact',
                'contact_2', 'address', 'province', 'city', 'barangay', 'photo_path', 'created_at',
            ])->whereIn(DB::raw(self::EXACT_MATCH_KEY), $keys)
                ->selectRaw($case.' END as duplicate_match_key', $bindings)->orderBy('clients.id')->get()
                ->groupBy('duplicate_match_key');
            $groups = $keys->map(fn ($key) => $this->groupPayload($members->get($key, collect())));
        }

        return ['paginator' => $this->namePaginator(
            $request,
            $groups,
            $total,
            $perPage,
            $page,
            'exact_page',
            'exact-tab'
        ), 'total' => $total, 'records' => $pageData['records']];
    }

    private function paginateInitialNameGroups(Request $request, int $perPage): array
    {
        $keyExpression = $this->initialNameExpressions()['key'];
        $query = $this->initialNameGroupQuery($request);
        $pageData = $this->groupPage($query, $request, $perPage, 'likely_page', 'name_key');
        $total = $pageData['total'];
        $page = $pageData['page'];
        $keys = $pageData['keys'];
        $groups = collect();
        if ($keys->isNotEmpty()) {
            $case = 'CASE';
            $bindings = [];
            foreach ($keys as $key) {
                $case .= ' WHEN '.$keyExpression.' = ? THEN ?';
                array_push($bindings, $key, $key);
            }
            $members = Client::query()->fromSub($this->transactionClients(), 'clients')->select([
                'clients.id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status', 'sector', 'email', 'contact',
                'contact_2', 'address', 'province', 'city', 'barangay', 'photo_path', 'created_at',
            ])->whereIn(DB::raw($keyExpression), $keys)
                ->selectRaw($case.' END as duplicate_name_key', $bindings)->orderBy('clients.id')->get()
                ->groupBy('duplicate_name_key');
            $groups = $keys->map(fn ($key) => $this->groupPayload($members->get($key, collect())));
        }

        return ['paginator' => $this->namePaginator($request, $groups, $total, $perPage, $page, 'likely_page', 'likely-tab'),
            'total' => $total, 'records' => $pageData['records']];
    }

    /**
     * Fetch page keys and aggregate totals in one grouped scan. A second scan
     * is needed only when a stale URL requests a page beyond the last page.
     */
    private function groupPage($query, Request $request, int $perPage, string $pageName, string $keyName): array
    {
        $requestedPage = max(1, (int) $request->input($pageName, 1));
        $loadPage = fn (int $page) => DB::query()->fromSub(clone $query, 'duplicate_groups')
            ->select('duplicate_groups.*')
            ->selectRaw('COUNT(*) OVER () as groups_total, COALESCE(SUM(records) OVER (), 0) as records_total')
            ->orderBy('first_id')->forPage($page, $perPage)->get();
        $rows = $loadPage($requestedPage);

        if ($rows->isNotEmpty()) {
            $first = $rows->first();

            return [
                'keys' => $rows->pluck($keyName),
                'total' => (int) $first->groups_total,
                'records' => (int) $first->records_total,
                'page' => $requestedPage,
            ];
        }

        if ($requestedPage === 1) {
            return ['keys' => collect(), 'total' => 0, 'records' => 0, 'page' => 1];
        }

        $totals = DB::query()->fromSub(clone $query, 'duplicate_groups')
            ->selectRaw('COUNT(*) as groups_total, COALESCE(SUM(records), 0) as records_total')->first();
        $total = (int) $totals->groups_total;
        $page = min($requestedPage, max(1, (int) ceil($total / $perPage)));
        $rows = $total > 0 ? $loadPage($page) : collect();

        return [
            'keys' => $rows->pluck($keyName),
            'total' => $total,
            'records' => (int) $totals->records_total,
            'page' => $page,
        ];
    }

    private function namePaginator(Request $request, $groups, int $total, int $perPage, int $page, string $pageName, string $fragment): LengthAwarePaginator
    {
        $paginator = new LengthAwarePaginator($groups, $total, $perPage, $page, [
            'path' => $request->url(), 'pageName' => $pageName,
        ]);
        $tab = match ($pageName) {
            'similar_page' => 'full_name',
            'likely_page' => 'likely',
            default => 'exact',
        };

        return $paginator->withQueryString()->appends(['duplicate_tab' => $tab])->fragment($fragment);
    }

    private function filterOptions(): array
    {
        return Cache::rememberForever('duplicate_clients_filter_options_v1', function () {
            $fields = ['gender', 'civil_status', 'city', 'barangay'];
            // Filter choices are client attributes. Reading them directly is
            // substantially cheaper than recalculating all duplicate tabs.
            $rows = DB::table('clients')->select($fields)->distinct()->get();
            $result = [];
            foreach (['gender' => 'filterGenders', 'civil_status' => 'filterCivilStatuses',
                'city' => 'filterCities', 'barangay' => 'filterBarangays'] as $field => $name) {
                $result[$name] = $rows->pluck($field)->map(fn ($value) => trim((string) $value))
                    ->filter()->unique(fn ($value) => strtolower($value))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
            }

            return $result;
        });
    }

    private function duplicateClientPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 10;
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
