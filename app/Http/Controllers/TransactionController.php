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
        // New transactions should start in a pending state until processed
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
            'amount' => $transaction->amount > 0 ? '₱' . number_format($transaction->amount, 2) : '₱0.00',
        ]);
    }
}
