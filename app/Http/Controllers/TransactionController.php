<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Transaction;
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

        $transaction = Transaction::create($validated);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'transaction_created',
            'description' => 'Created transaction for client ' . $validated['client_id'],
            'subject_type' => 'Transaction',
            'subject_id' => $transaction->id,
            'properties' => json_encode(['transaction_id' => $transaction->id]),
        ]);

        return redirect()->route('transactions.show', $transaction);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('client');
        return view('pages.client_transaction.transactionInfo', compact('transaction'));
    }
}
