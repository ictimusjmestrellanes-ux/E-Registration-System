<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Services\ClientIdCompactor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ClientListController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $matchedClientId = $request->query('matched_client');
        $groupClientIds = $this->parseGroupClientIds($request);
        $sort = $this->normalizeClientSort($request->input('sort'));

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $clientsQuery = Client::query()
            ->with('latestLinkedEvent')
            ->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status',
                'email', 'contact', 'contact_2', 'address',
                'province', 'city', 'barangay', 'birthplace',
                'education', 'course', 'sector', 'position_organization',
                'photo_path', 'created_at',
            ])
            // Server-side keyword search so results span every page.
            ->when($request->filled('search'), function ($q) use ($request) {
                $keyword = strtolower(trim($request->input('search')));
                $nameExpression = DB::connection()->getDriverName() === 'sqlite'
                    ? "LOWER(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(middle_name, '') || ' ' || COALESCE(last_name, '') || ' ' || COALESCE(suffix, '')))"
                    : "LOWER(CONCAT_WS(' ', first_name, middle_name, last_name, suffix))";
                $formattedNameExpression = DB::connection()->getDriverName() === 'sqlite'
                    ? "LOWER(TRIM(COALESCE(last_name, '') || ', ' || COALESCE(first_name, '') || ' ' || COALESCE(middle_name, '')))"
                    : "LOWER(CONCAT_WS('', last_name, ', ', first_name, ' ', COALESCE(middle_name, '')))";

                $q->where(function ($sub) use ($keyword, $nameExpression, $formattedNameExpression) {
                    $sub->whereRaw("{$nameExpression} LIKE ?", ["%{$keyword}%"])
                        ->orWhereRaw("{$formattedNameExpression} LIKE ?", ['%' . rtrim($keyword, '.') . '%'])
                        ->orWhereRaw('LOWER(client_id) LIKE ?', ["%{$keyword}%"])
                        ->orWhereExists(function ($eventQuery) use ($keyword) {
                            $eventQuery->selectRaw('1')
                                ->from('transaction_events')
                                ->join('transaction_history', 'transaction_history.id', '=', 'transaction_events.transferred_transaction_id')
                                ->whereColumn('transaction_history.client_id', 'clients.client_id')
                                ->whereNotNull('transaction_events.transferred_at')
                                ->where(function ($eventNameQuery) use ($keyword) {
                                    $eventNameQuery
                                        ->whereRaw('LOWER(transaction_events.full_name) LIKE ?', ["%{$keyword}%"])
                                        ->orWhereRaw('LOWER(transaction_events.display_name_sort) LIKE ?', ["%{$keyword}%"]);
                                });
                        });

                    if (str_contains($keyword, ',')) {
                        [$lastName, $givenNames] = array_map('trim', explode(',', $keyword, 2));
                        $givenNames = preg_replace('/\s+(?:JR\.?|SR\.?|II|III|IV|V)$/iu', '', $givenNames);
                        $firstName = preg_split('/\s+/u', $givenNames)[0] ?? '';

                        if ($lastName !== '' && $firstName !== '') {
                            $sub->orWhere(function ($formattedName) use ($lastName, $firstName) {
                                $formattedName->whereRaw('LOWER(TRIM(last_name)) LIKE ?', ["%{$lastName}%"])
                                    ->whereRaw('LOWER(TRIM(first_name)) LIKE ?', ["{$firstName}%"]);
                            });
                        }
                    }
                });
            })
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->when($request->filled('civil_status'), fn ($q) => $q->where('civil_status', $request->input('civil_status')))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->when($request->filled('barangay'), fn ($q) => $q->where('barangay', $request->input('barangay')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($request->boolean('duplicate_names'), function ($q) {
                // find duplicate keys using first+middle+last name + birth_date
                $duplicates = Client::query()
                    ->selectRaw("CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), COALESCE(birth_date,'')) as keyval")
                    ->groupBy('keyval')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('keyval')
                    ->map(fn($v) => (string) $v)
                    ->toArray();

                if (!empty($duplicates)) {
                    $q->whereIn(DB::raw("CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), COALESCE(birth_date,''))"), $duplicates);
                } else {
                    // nothing matches — ensure no rows
                    $q->whereRaw('0 = 1');
                }
            })
            ->when($matchedClientId, function ($query, $matchedClientId) {
                $query->where('id', $matchedClientId);
            })
            ->when(!empty($groupClientIds), function ($query) use ($groupClientIds) {
                $query->whereIn('id', $groupClientIds);
            });

        $this->applyClientSort($clientsQuery, $sort);

        $clients = $clientsQuery->paginate($perPage)
            ->withQueryString();

        $clientCities = Client::whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $clientBarangays = Client::whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay');
        $clientCivilStatuses = Client::whereNotNull('civil_status')->distinct()->orderBy('civil_status')->pluck('civil_status');

        return view('pages.clients.clientList', compact('clients', 'matchedClientId', 'groupClientIds', 'clientCities', 'clientBarangays', 'clientCivilStatuses', 'sort'));
    }

    private function normalizeClientSort(?string $sort): string
    {
        $validSorts = [
            'name_asc', 'name_desc',
            'clientid_asc', 'clientid_desc',
            'gender_asc', 'gender_desc',
            'age_asc', 'age_desc',
            'contact_asc', 'contact_desc',
            'address_asc', 'address_desc',
        ];

        return in_array($sort, $validSorts, true) ? $sort : 'name_asc';
    }

    private function applyClientSort(Builder $query, string $sort): void
    {
        $direction = str_ends_with($sort, '_desc') ? 'desc' : 'asc';

        if (str_starts_with($sort, 'name_')) {
            [$lastName, $firstName, $middleName] = Client::listDisplaySortExpressions();
            $latestEventName = '(SELECT transaction_events.display_name_sort'
                .' FROM transaction_events'
                .' INNER JOIN transaction_history ON transaction_history.id = transaction_events.transferred_transaction_id'
                .' WHERE transaction_history.client_id = clients.client_id'
                .' AND transaction_events.transferred_at IS NOT NULL'
                .' ORDER BY transaction_events.id DESC LIMIT 1)';

            $query->orderByRaw("COALESCE({$latestEventName}, {$lastName}) {$direction}")
                ->orderByRaw("{$firstName} {$direction}")
                ->orderByRaw("{$middleName} {$direction}");
        } else {
            $column = match (true) {
                str_starts_with($sort, 'clientid_') => 'client_id',
                str_starts_with($sort, 'gender_') => 'gender',
                str_starts_with($sort, 'age_') => 'age',
                str_starts_with($sort, 'contact_') => 'contact',
                str_starts_with($sort, 'address_') => 'address',
                default => 'last_name',
            };

            if (in_array($column, ['client_id', 'gender', 'contact', 'address'], true)) {
                $query->orderByRaw("LOWER(TRIM(COALESCE({$column}, ''))) {$direction}");
            } else {
                $query->orderBy($column, $direction);
            }
        }

        $query->orderBy('id', $direction);
    }

    public function destroyWithoutTransactions(Request $request, ClientIdCompactor $compactor)
    {
        abort_if(auth()->user()?->role_name === 'Viewer' || !feature_allowed('Archive Clients'), 403);

        $request->validate([
            'select_all' => ['required', 'boolean'],
            'selected_ids' => ['required', 'json'],
            'excluded_ids' => ['required', 'json'],
            'max_client_id' => [Rule::requiredIf($request->boolean('select_all')), 'nullable', 'integer', 'min:0'],
            'operation_id' => ['nullable', 'uuid'],
        ]);

        $selectAll = $request->boolean('select_all');
        $selectedIds = $this->parseJsonClientIds($request->input('selected_ids'));
        $excludedIds = array_fill_keys($this->parseJsonClientIds($request->input('excluded_ids')), true);

        if (!$selectAll && $selectedIds === []) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Select at least one client to delete.'], 422);
            }
            return redirect()->route('client.list')->with('error', 'Select at least one client to delete.');
        }

        $operationId = $request->input('operation_id');
        $report = fn (array $status) => $this->recordDeletionProgress($operationId, $status);
        $report(['state' => 'working', 'message' => 'Preparing selected clients...',
            'checked' => 0, 'total' => 0, 'deleted' => 0]);

        try {
            [$deletedCount, $renumberedCount, $mediaPaths] = DB::transaction(function () use (
                $selectAll, $selectedIds, $excludedIds, $request, $compactor, $report
            ) {
            $deletedIds = [];
            $mediaPaths = [];
            $checkedCount = 0;
            $queries = $selectAll
                ? [$this->clientsWithoutDirectTransactionsQuery()->where('clients.id', '<=', (int) $request->input('max_client_id'))]
                : array_map(
                    fn ($ids) => $this->clientsWithoutDirectTransactionsQuery()->whereIn('clients.id', $ids),
                    array_chunk($selectedIds, 500)
                );
            $totalCandidates = array_sum(array_map(fn ($query) => (clone $query)->count(), $queries));
            $report(['state' => 'working', 'message' => 'Checking selected clients...',
                'checked' => 0, 'total' => $totalCandidates, 'deleted' => 0]);

            foreach ($queries as $query) {
                $query->chunkById(100, function ($candidates) use (
                    &$deletedIds, &$mediaPaths, &$checkedCount, $excludedIds, $selectAll, $totalCandidates, $report
                ) {
                    foreach ($candidates as $candidate) {
                        if ($selectAll && isset($excludedIds[$candidate->id])) {
                            continue;
                        }

                        $client = Client::query()->lockForUpdate()->find($candidate->id);
                        if (!$client) {
                            continue;
                        }

                        // Older history rows can be linked only by the transaction ID prefix.
                        $hasHistory = filled($client->client_id) && TransactionHistory::query()
                            ->where('client_id', $client->client_id)
                            ->orWhere('transaction_id', 'like', $client->client_id . '-%')
                            ->exists();

                        if ($hasHistory) {
                            continue;
                        }

                        $mediaPaths[] = array_filter([$client->photo_path, $client->fingerprint_path]);
                        $deletedIds[] = (string) $client->client_id;
                        $client->delete();
                    }
                    $checkedCount += $candidates->count();
                    $report(['state' => 'working', 'message' => 'Checking and deleting selected clients...',
                        'checked' => $checkedCount, 'total' => $totalCandidates,
                        'deleted' => count($deletedIds)]);
                });
            }

            $renumberedCount = $compactor->compactAfterDeletion($deletedIds,
                function (string $message, int $renumbered) use ($report, $checkedCount, $totalCandidates, &$deletedIds) {
                    $report(['state' => 'working', 'message' => $message,
                        'checked' => $checkedCount, 'total' => $totalCandidates,
                        'deleted' => count($deletedIds), 'renumbered' => $renumbered]);
                });
            $report(['state' => 'working', 'message' => 'Saving changes...',
                'checked' => $checkedCount, 'total' => $totalCandidates,
                'deleted' => count($deletedIds), 'renumbered' => $renumberedCount]);

            return [count($deletedIds), $renumberedCount, $mediaPaths];
            });
        } catch (Throwable $exception) {
            $report(['state' => 'failed', 'message' => 'Deletion could not be completed. Check the Client List before retrying.']);
            throw $exception;
        }

        $report(['state' => 'working', 'message' => 'Removing saved media...',
            'deleted' => $deletedCount, 'renumbered' => $renumberedCount]);
        $mediaWarning = '';
        try {
            foreach ($mediaPaths as $paths) {
                Storage::disk('public')->delete($paths);
            }
        } catch (Throwable $exception) {
            report($exception);
            $mediaWarning = ' Some saved media could not be removed.';
        }

        $message = $deletedCount === 0
            ? 'No selected clients without transaction history were found.'
            : "Deleted {$deletedCount} " . ($deletedCount === 1 ? 'client' : 'clients') . ' without transaction history.'
                . ($renumberedCount > 0
                    ? " Updated {$renumberedCount} client " . ($renumberedCount === 1 ? 'ID' : 'IDs') . ' and linked transaction IDs.'
                    : '') . $mediaWarning;
        $report(['state' => 'complete', 'message' => $message,
            'deleted' => $deletedCount, 'renumbered' => $renumberedCount,
            'redirect' => route('client.list')]);

        if ($request->expectsJson()) {
            session()->flash('success', $message);
            return response()->json(['message' => $message, 'redirect' => route('client.list')]);
        }

        return redirect()->route('client.list')->with('success', $message);
    }

    public function deletionProgress(string $operationId)
    {
        abort_if(auth()->user()?->role_name === 'Viewer' || !feature_allowed('Archive Clients'), 403);
        abort_unless(Str::isUuid($operationId), 404);

        return response()->json(Cache::store('file')->get(
            $this->deletionProgressKey($operationId),
            ['state' => 'pending', 'message' => 'Waiting for deletion to start...',
                'checked' => 0, 'total' => 0, 'deleted' => 0]
        ));
    }

    private function recordDeletionProgress(?string $operationId, array $status): void
    {
        if (!$operationId) {
            return;
        }

        // A separate file cache keeps polling visible while the database
        // transaction is still open and its writes are uncommitted.
        Cache::store('file')->put($this->deletionProgressKey($operationId), $status, now()->addMinutes(20));
    }

    private function deletionProgressKey(string $operationId): string
    {
        return 'client_delete_progress:' . auth()->id() . ':' . $operationId;
    }

    public function previewWithoutTransactions(Request $request)
    {
        abort_if(auth()->user()?->role_name === 'Viewer' || !feature_allowed('Archive Clients'), 403);

        $request->validate([
            'max_client_id' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $maxClientId = $request->has('max_client_id')
            ? (int) $request->query('max_client_id')
            : (int) Client::query()->max('id');
        $page = max(1, $request->integer('page', 1));
        $perPage = 10;

        $candidates = $this->clientsWithoutDirectTransactionsQuery()
            ->where('clients.id', '<=', $maxClientId)
            ->orderBy('clients.id')
            ->get(['clients.id', 'clients.client_id']);

        $candidateIdsByClientId = $candidates->filter(fn (Client $client) => filled($client->client_id))
            ->pluck('id', 'client_id')
            ->all();
        $linkedByLegacyId = [];

        if ($candidateIdsByClientId !== []) {
            DB::table('transaction_history')
                ->select(['id', 'transaction_id'])
                ->chunkById(1000, function ($histories) use ($candidateIdsByClientId, &$linkedByLegacyId) {
                    foreach ($histories as $history) {
                        $transactionId = (string) $history->transaction_id;
                        $separator = strpos($transactionId, '-');

                        while ($separator !== false) {
                            $prefix = substr($transactionId, 0, $separator);
                            if (isset($candidateIdsByClientId[$prefix])) {
                                $linkedByLegacyId[$candidateIdsByClientId[$prefix]] = true;
                            }
                            $separator = strpos($transactionId, '-', $separator + 1);
                        }
                    }
                });
        }

        $eligibleIds = $candidates->pluck('id')
            ->reject(fn ($id) => isset($linkedByLegacyId[$id]))
            ->values();
        $total = $eligibleIds->count();
        $pageIds = $eligibleIds->slice(($page - 1) * $perPage, $perPage)->all();

        $clients = Client::query()
            ->whereIn('clients.id', $pageIds)
            ->select(['clients.id', 'clients.client_id', 'clients.first_name', 'clients.middle_name',
                'clients.last_name', 'clients.suffix', 'clients.address', 'clients.barangay',
                'clients.city', 'clients.province'])
            ->orderBy('clients.id')
            ->get();

        $preview = $clients->map(fn (Client $client) => [
            'id' => $client->id,
            'client_id' => $client->client_id,
            'full_name' => $client->list_display_name,
            'address' => collect([$client->address, $client->barangay, $client->city, $client->province])
                ->filter(fn ($part) => filled($part))
                ->implode(', '),
        ])->values()->all();

        return response()->json([
            'data' => $preview,
            'total' => $total,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $preview === [] ? null : ($page - 1) * $perPage + 1,
            'to' => $preview === [] ? null : ($page - 1) * $perPage + count($preview),
            'max_client_id' => $maxClientId,
        ]);
    }

    private function clientsWithoutDirectTransactionsQuery(): Builder
    {
        return Client::query()
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('transaction_history')
                    ->whereColumn('transaction_history.client_id', 'clients.client_id');
            });
    }

    private function parseJsonClientIds(string $json): array
    {
        $ids = json_decode($json, true);

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => filter_var($id, FILTER_VALIDATE_INT) ?: null,
            $ids
        ), static fn ($id) => $id > 0)));
    }

    /**
     * Parse the ?client_ids=1,2,3 query param used by the "View in Client
     * List" button on the Duplicate Clients Review page to show one group.
     *
     * @return int[]
     */
    private function parseGroupClientIds(Request $request): array
    {
        $ids = array_map('intval', explode(',', (string) $request->input('client_ids', '')));
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));

        return array_slice($ids, 0, 200);
    }
}
