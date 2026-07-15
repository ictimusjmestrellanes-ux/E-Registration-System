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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string|exists:clients,client_id',
            'transaction_date' => 'required|date',
            'category' => 'required|string|max:100',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'addressed_to' => 'nullable|string',
        ]);

        $year = now()->format('y');
        $lastToday = TransactionHistory::whereDate('created_at', today())->count();
        $validated['transaction_id'] = $validated['client_id'] . '-' . $year . '-' . str_pad($lastToday + 1, 4, '0', STR_PAD_LEFT);
        $validated['source'] = 'E-Registration';
        $validated['status'] = 'Pending';

        $transaction = TransactionHistory::create($validated);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transaction_created',
            'description' => 'Created transaction for client ' . $validated['client_id'],
            'subject_type' => 'TransactionHistory',
            'subject_id' => $transaction->id,
            'properties' => json_encode(['transaction_id' => $transaction->id]),
        ]);

        $client = Client::where('client_id', $validated['client_id'])->first();

        return redirect()->route('clients.show', $client)
            ->with('show_transaction', $transaction->id);
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
            'status' => $transaction->status ?? 'Pending',
            'description' => $transaction->description ?? 'N/A',
            'actions_taken' => $transaction->actions_taken ?? 'N/A',
            'remarks' => $transaction->remarks ?? 'N/A',
            'amount' => $transaction->amount > 0 ? 'PHP ' . number_format((float) $transaction->amount, 2) : 'PHP 0.00',
            'subject_summary' => $transaction->subject_summary ?? 'N/A',
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Subject information saved.',
            'data' => [
                'subject_summary' => $transaction->fresh()->subject_summary,
            ],
        ]);
    }
}
