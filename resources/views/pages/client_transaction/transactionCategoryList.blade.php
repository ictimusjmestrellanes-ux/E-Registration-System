@extends('layouts.master')
@section('title', 'ERS | ' . $labels . ' Transactions')
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1">{{ $labels }} Transactions</h4>
                                <p class="text-muted mb-0">{{ $total }} transaction(s){{ $category ? ' under this service category' : '' }}</p>
                            </div>
                            <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm">
                                <i class="ri-arrow-left-line me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="search"
                                    placeholder="Search transaction ID, clerk, or type..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                    <i class="ri-search-line"></i>
                                </button>
                                @if (request()->filled('search'))
                                    <a href="{{ $category ? route('transactions.category', $category) : route('transactions.index') }}"
                                        class="btn btn-light btn-sm flex-fill">
                                        <i class="ri-close-line"></i>
                                    </a>
                                @endif
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Client</th>
                                        <th>Transaction ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Clerk</th>
                                        <th style="width: 110px; text-align: center;">Status</th>
                                        <th style="width: 90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($transactions as $transaction)
                                        @php
                                            $isApproved = strtolower($transaction->status ?? 'Pending') === 'approved';
                                            $client = App\Models\Client::where('client_id', $transaction->client_id)->first();
                                        @endphp
                                        <tr class="{{ $isApproved ? '' : 'table-warning' }}">
                                            <td class="fw-semibold">{{ $client->full_name ?? $transaction->client_id }}</td>
                                            <td class="small">
                                                <a href="{{ route('transactions.show', $transaction->id) }}"
                                                    class="text-primary">{{ $transaction->transaction_id }}</a>
                                            </td>
                                            <td class="small">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                                            <td class="small">{{ $transaction->type_label }}</td>
                                            <td class="small">{{ $transaction->clerk ?? '-' }}</td>
                                            <td class="text-center">
                                                <span
                                                    class="badge {{ $isApproved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} px-3 py-2">
                                                    {{ $transaction->status ?? 'Pending' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if ($client)
                                                    <a href="{{ route('clients.show', $client) }}"
                                                        class="btn btn-sm btn-soft-primary" title="View client details">
                                                        <i class="ri-eye-line"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                No transactions found{{ $category ? ' under this category' : '' }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            {{ $transactions->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection