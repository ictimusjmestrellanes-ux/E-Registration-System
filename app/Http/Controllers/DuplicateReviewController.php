<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DuplicateReviewController extends Controller
{
    private const EXACT_KEY = "CONCAT_WS('|', LOWER(TRIM(last_name)), LOWER(TRIM(first_name)))";
    private const FULL_NAME_KEY = "CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), LOWER(TRIM(COALESCE(suffix,''))))";

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        abort_unless(feature_allowed('Duplicate Clients Review'), 404);
        $perPage = $this->duplicateClientPerPage($request);
        $exact = $this->paginateNameGroups($request, $perPage, 'exact_page', 'exact-tab', true, self::EXACT_KEY);
        // Match Full Name considers every client, so its membership, counts,
        // and pagination must not reuse Exact Match's transaction-only results.
        $fullName = $this->paginateNameGroups($request, $perPage, 'similar_page', 'similar-tab', false, self::FULL_NAME_KEY);
        $results = [
            'exact' => $exact,
            // Birth-date-only likely groups are covered by full-name matching.
            'likely' => [
                'paginator' => $this->namePaginator($request, collect(), 0, $perPage, 1, 'likely_page', 'likely-tab'),
                'total' => 0, 'records' => 0,
            ],
            // Keep the existing paginator/view variable names for old links.
            'similar' => $fullName,
        ];
        $data = ['perPage' => $perPage];
        foreach ($results as $category => $result) {
            $data[$category.'Groups'] = $result['paginator'];
            $data[$category.'GroupsTotal'] = $result['total'];
            $data[$category.'RecordsTotal'] = $result['records'];
        }

        return view('pages.duplicates.index', array_merge($this->filterOptions(), $data));
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
        $totals = DB::query()->fromSub(clone $query, 'duplicate_groups')
            ->selectRaw('COUNT(*) as groups_total, COALESCE(SUM(records), 0) as records_total')->first();
        $total = (int) $totals->groups_total;
        $page = min(max(1, (int) $request->input($pageName, 1)), max(1, (int) ceil($total / $perPage)));
        $keys = (clone $query)->orderBy('first_id')->forPage($page, $perPage)->pluck('name_key');
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
            'total' => $total, 'records' => (int) $totals->records_total];
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
        $fields = ['gender', 'civil_status', 'city', 'barangay'];
        // Neither tab contains the other's complete population: Exact Match
        // ignores middle names/suffixes; Match Full Name includes nontransaction
        // clients. Supply dropdown values from both populations.
        $fullNameRows = DB::query()->fromSub($this->nameClients(false), 'clients')
            ->joinSub($this->nameGroupQuery(self::FULL_NAME_KEY, false), 'duplicate_groups', function ($join) {
                $join->on(DB::raw(self::FULL_NAME_KEY), '=', 'duplicate_groups.name_key');
            })->select($fields);
        $exactRows = DB::query()->fromSub($this->nameClients(true), 'clients')
            ->joinSub($this->nameGroupQuery(self::EXACT_KEY, true), 'duplicate_groups', function ($join) {
                $join->on(DB::raw(self::EXACT_KEY), '=', 'duplicate_groups.name_key');
            })->select($fields);
        $rows = $fullNameRows->union($exactRows)->get();
        $result = [];
        foreach (['gender' => 'filterGenders', 'civil_status' => 'filterCivilStatuses',
            'city' => 'filterCities', 'barangay' => 'filterBarangays'] as $field => $name) {
            $result[$name] = $rows->pluck($field)->map(fn ($value) => trim((string) $value))
                ->filter()->unique(fn ($value) => strtolower($value))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        }

        return $result;
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
