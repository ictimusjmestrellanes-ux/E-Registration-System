<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $transactions = TransactionHistory::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('transaction_id', 'like', '%' . $request->search . '%')
                        ->orWhere('clerk', 'like', '%' . $request->search . '%')
                        ->orWhere('type', 'like', '%' . $request->search . '%')
                        ->orWhere('events_transaction_type', 'like', '%' . $request->search . '%')
                        ->orWhere('category', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('client_category'), fn ($q) => $q->where('client_category', $request->input('client_category')))
            ->when($request->filled('category_filter'), function ($q) use ($request) {
                $q->whereIn('category', $this->categoryFilterValues($request->input('category_filter')));
            })
            ->when($request->filled('transaction_category'), fn ($q) => $q->where('category', $request->input('transaction_category')))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where('events_transaction_type', $request->input('transaction_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->input('date_to')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $total = $transactions->total();

        return view('pages.client_transaction.transactionCategoryList', [
            'category' => null,
            'labels' => 'All',
            'transactions' => $transactions,
            'total' => $total,
        ] + $this->transactionFilterOptions());
    }

    public function categoryList(Request $request, string $category)
    {
        if (!array_key_exists($category, TransactionHistory::CATEGORIES)) {
            abort(404);
        }

        $aliases = [];
        foreach (TransactionHistory::query()->select('category')->distinct()->pluck('category') as $stored) {
            $canonical = TransactionHistory::normalizeCategory($stored);
            if ($canonical !== null) {
                $aliases[$canonical][] = $stored;
            }
        }

        $categoryNames = array_unique(array_merge($aliases[$category] ?? [], [$category]));

        $transactions = TransactionHistory::query()
            ->whereIn('category', $categoryNames)
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('transaction_id', 'like', '%' . $request->search . '%')
                        ->orWhere('clerk', 'like', '%' . $request->search . '%')
                        ->orWhere('type', 'like', '%' . $request->search . '%')
                        ->orWhere('events_transaction_type', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('client_category'), fn ($q) => $q->where('client_category', $request->input('client_category')))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where('events_transaction_type', $request->input('transaction_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->input('date_to')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $labels = TransactionHistory::CATEGORIES[$category];
        $total = $transactions->total();

        return view('pages.client_transaction.transactionCategoryList', compact('category', 'labels', 'transactions', 'total') + $this->transactionFilterOptions());
    }

    /**
     * Dropdown options shared by the transaction list filters.
     */
    private function transactionFilterOptions(): array
    {
        $statuses = TransactionHistory::query()
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $clientCategories = TransactionHistory::query()
            ->whereNotNull('client_category')
            ->where('client_category', '<>', '')
            ->distinct()
            ->orderBy('client_category')
            ->pluck('client_category');

        $transactionCategories = TransactionHistory::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $transactionTypes = TransactionHistory::query()
            ->whereNotNull('events_transaction_type')
            ->where('events_transaction_type', '<>', '')
            ->distinct()
            ->orderBy('events_transaction_type')
            ->pluck('events_transaction_type');

        return [
            'filterStatuses' => $statuses,
            'filterClientCategories' => $clientCategories,
            'filterCategories' => TransactionHistory::CATEGORIES,
            'filterTransactionCategories' => $transactionCategories,
            'filterTransactionTypes' => $transactionTypes,
        ];
    }

    /**
     * All stored category values that belong to one canonical category key
     * (e.g. 'events' matches stored 'events', 'CARAVAN', ...).
     */
    private function categoryFilterValues(string $canonical): array
    {
        $values = [$canonical];

        foreach (TransactionHistory::query()->select('category')->distinct()->pluck('category') as $stored) {
            if (TransactionHistory::normalizeCategory($stored) === $canonical) {
                $values[] = $stored;
            }
        }

        return array_values(array_unique($values));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string|exists:clients,client_id',
            'transaction_date' => 'required|date',
            'category' => 'required|string|max:100',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'addressed_to' => 'nullable|string',
            'actions_taken' => 'nullable|string',
            'remarks' => 'nullable|string',
            'signatory' => 'nullable|string|max:100',
            'personnel_endorsed_to' => 'nullable|string|max:100',
            'responsible_office' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $validated['transaction_id'] = $this->nextClientTransactionId($validated['client_id']);
        $validated['source'] = 'E-Registration';
        $validated['category'] = TransactionHistory::normalizeCategory($validated['category']);
        $validated['status'] = in_array(auth()->user()->role_name, ['Admin', 'Super Admin'], true) ? 'Approved' : 'Pending';
        $validated['clerk'] = auth()->user()->name ?? null;
        $validated['amount'] = (float) ($validated['amount'] ?? 0);

        $transaction = TransactionHistory::create($validated);

        $client = Client::where('client_id', $validated['client_id'])->first();

        if (!$client) {
            return redirect()->route('client.list')->with('error', 'Client not found.');
        }

        \App\Models\TransactionEvent::create([
            'full_name'                  => $client->full_name,
            'contact_no'                 => $client->contact ?? '',
            'address'                    => $client->address ?? '',
            'age'                        => $client->age ?? null,
            'birth_date'                 => $client->birth_date ? $client->birth_date->format('Y-m-d') : null,
            'client_category'            => $client->sector ?? '',
            'transaction_category'       => $validated['category'],
            'transaction_type'           => $validated['type'],
            'event_date'                 => $validated['transaction_date'],
            'transferred_at'             => now(),
            'transferred_transaction_id' => $transaction->id,
        ]);

        TransactionHistory::flushDashboardCache();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transaction_created',
            'description' => 'Created transaction for client ' . $validated['client_id'],
            'subject_type' => 'TransactionHistory',
            'subject_id' => $transaction->id,
            'properties' => json_encode(['transaction_id' => $transaction->id]),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully.',
                'transaction' => $transaction,
                'redirect' => route('clients.show', $client) . '?show_transaction=' . $transaction->id,
            ]);
        }

        return redirect()->route('clients.show', $client)
            ->with('show_transaction', $transaction->id);
    }

    public function edit($id)
    {
        if (auth()->user()->role_name === 'Viewer') {
            abort(403, 'Viewer role is read-only.');
        }

        $transaction = TransactionHistory::with('requirements')->find($id);

        if (!$transaction) {
            abort(404);
        }

        $client = Client::where('client_id', $transaction->client_id)->first();

        if (!$client) {
            abort(404);
        }

        return view('pages.client_transaction.transactionEdit', compact('transaction', 'client'));
    }

    public function update(Request $request, $id)
    {
        $transaction = TransactionHistory::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'required|string|exists:clients,client_id',
            'transaction_date' => 'required|date',
            'category' => 'required|string|max:100',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'addressed_to' => 'nullable|string',
            'actions_taken' => 'nullable|string',
            'remarks' => 'nullable|string',
            'signatory' => 'nullable|string|max:100',
            'personnel_endorsed_to' => 'nullable|string|max:100',
            'responsible_office' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $validated['amount'] = (float) ($validated['amount'] ?? 0);
        unset($validated['client_id']);
        $validated['category'] = TransactionHistory::normalizeCategory($validated['category']);

        $transaction->update($validated);

        TransactionHistory::flushDashboardCache();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transaction_updated',
            'description' => 'Updated transaction ' . $transaction->transaction_id,
            'subject_type' => 'TransactionHistory',
            'subject_id' => $transaction->id,
            'properties' => json_encode(['transaction_id' => $transaction->id]),
        ]);

        $client = Client::where('client_id', $transaction->client_id)->first();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully.',
                'transaction' => $transaction,
                'redirect' => route('clients.show', $client) . '?show_transaction=' . $transaction->id,
            ]);
        }

        return redirect()->route('clients.show', $client)
            ->with('show_transaction', $transaction->id);
    }

    private function nextClientTransactionId(string $clientId): string
    {
        $year = now()->format('y');
        $prefix = $clientId . '-' . $year . '-';

        $maxSequence = TransactionHistory::query()
            ->where('transaction_id', 'like', $prefix . '%')
            ->pluck('transaction_id')
            ->reduce(function (int $max, string $transactionId) use ($prefix) {
                $suffix = substr($transactionId, strlen($prefix));

                return ctype_digit($suffix) ? max($max, (int) $suffix) : $max;
            }, 0);

        return $prefix . str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    public function show($id)
    {
        $transaction = TransactionHistory::find($id);

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        return response()->json([
            'id' => $transaction->id,
            'transaction_id' => $transaction->transaction_id,
            'transaction_date' => $transaction->transaction_date->format('m/d/Y'),
            'source' => $transaction->source ?? 'E-Registration',
            'type' => $transaction->type_label,
            'category' => $transaction->category_label,
            'clerk' => $transaction->clerk ?? auth()->user()->name ?? 'System',
            'signatory' => $transaction->signatory ?? 'N/A',
            'personnel_endorsed_to' => $transaction->personnel_endorsed_to ?? 'N/A',
            'responsible_office' => $transaction->responsible_office ?? 'N/A',
            'status' => $transaction->status ?? 'Pending',
            'description' => $transaction->description ?? 'N/A',
            'actions_taken' => $transaction->actions_taken ?? 'N/A',
            'remarks' => $transaction->remarks ?? 'N/A',
            'amount' => $transaction->amount > 0 ? 'PHP ' . number_format((float) $transaction->amount, 2) : 'PHP 0.00',
            'subject_summary' => $transaction->subject_summary ?? 'N/A',
        ]);
    }

    public function process($id)
    {
        $transaction = TransactionHistory::with('requirements')->find($id);

        if (!$transaction) {
            abort(404);
        }

        $client = Client::where('client_id', $transaction->client_id)->first();

        $requirementLabels = [
            'valid_id' => 'Valid Id of Claimant with Address to Imus (Back to Back)',
            'death_certificate' => 'Registered Death Certificate (CTC)',
            'funeral_contract' => 'Funeral Contract',
        ];

        $requirements = $transaction->requirements
            ->sortBy('created_at')
            ->map(function ($requirement) use ($requirementLabels) {
                return [
                    'id' => $requirement->id,
                    'label' => $requirementLabels[$requirement->requirement_type] ?? strtoupper(str_replace('_', ' ', $requirement->requirement_type)),
                    'type' => $requirement->requirement_type,
                    'file_name' => $requirement->file_name,
                    'file_url' => $requirement->file_url,
                    'created_at' => $requirement->created_at,
                ];
            })
            ->values();

        $hasSubject = filled($transaction->subject_first_name);

        $processSteps = [
            [
                'title' => 'Transaction Registration',
                'done' => true,
                'time' => $transaction->created_at,
                'detail' => 'Transaction ' . $transaction->transaction_id . ' was registered via ' . ($transaction->source ?? 'E-Registration') . ' by ' . ($transaction->clerk ?? 'System') . '.',
            ],
            [
                'title' => 'Transaction Details',
                'done' => true,
                'time' => $transaction->created_at,
                'detail' => $transaction->type_label . ' (' . $transaction->category_label . ')',
            ],
            [
                'title' => 'Requirements Submission',
                'done' => $requirements->isNotEmpty(),
                'time' => $requirements->isNotEmpty() ? $requirements->first()['created_at'] : null,
                'detail' => $requirements->isNotEmpty()
                    ? $requirements->count() . ' requirement record(s) submitted.'
                    : 'No requirements submitted yet.',
            ],
            [
                'title' => 'Subject Information',
                'done' => $hasSubject,
                'time' => $hasSubject ? $transaction->updated_at : null,
                'detail' => $hasSubject
                    ? $transaction->subject_full_name . ($transaction->subject_client_relation ? ' (' . $transaction->subject_client_relation . ')' : '')
                    : 'No subject information recorded yet.',
            ],
            [
                'title' => 'Approval',
                'done' => strtolower($transaction->status ?? 'Pending') === 'approved',
                'time' => null,
                'detail' => $transaction->status ?? 'Pending',
            ],
        ];

        return view('pages.client_transaction.transactionProcess', compact('transaction', 'client', 'requirements', 'processSteps', 'hasSubject'));
    }

    public function storeSubject(Request $request, $id)
    {
        $transaction = TransactionHistory::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'name_ext' => 'nullable|string|max:20',
            'gender' => 'required|string|max:20',
            'birthdate' => 'required|date',
            'age' => 'nullable|integer|min:0|max:150',
            'barangay' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'client_relation' => 'required|string|max:100',
        ]);

        $transaction->update([
            'subject_first_name' => $validated['first_name'],
            'subject_middle_name' => $validated['middle_name'] ?? null,
            'subject_last_name' => $validated['last_name'],
            'subject_name_ext' => $validated['name_ext'] ?? null,
            'subject_gender' => $validated['gender'],
            'subject_birthdate' => $validated['birthdate'],
            'subject_age' => $validated['age'] ?? null,
            'subject_barangay' => $validated['barangay'],
            'subject_municipality' => $validated['municipality'],
            'subject_client_relation' => $validated['client_relation'],
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transaction_subject_updated',
            'description' => "Saved subject information for transaction {$transaction->transaction_id}.",
            'subject_type' => 'TransactionHistory',
            'subject_id' => $transaction->id,
            'properties' => json_encode([
                'subject_name' => trim($validated['first_name'] . ' ' . ($validated['middle_name'] ?? '') . ' ' . $validated['last_name']),
                'client_relation' => $validated['client_relation'],
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subject information saved.',
            'data' => [
                'subject_summary' => $transaction->fresh()->subject_summary,
            ],
        ]);
    }
}
