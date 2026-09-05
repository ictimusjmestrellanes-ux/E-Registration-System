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
                        <div class="border rounded-4 p-3 mb-3" id="transactionFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-0">
                                <div>
                                    <div class="fw-bold fs-5">Filter Transactions</div>
                                    <div class="text-muted small">Narrow records by keyword, status, client category,
                                        service category, and transaction date range. Search covers all pages.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        id="transactionFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="{{ $category ? route('transactions.category', $category) : route('transactions.index') }}"
                                        class="btn btn-sm btn-soft-secondary" id="transactionFiltersResetBtn">Reset</a>
                                </div>
                            </div>

                            <form method="GET" id="transactionFiltersForm" class="mt-3 {{ request()->anyFilled(['search', 'status', 'client_category', 'category_filter', 'date_from', 'date_to']) ? '' : 'd-none' }}">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="transactionKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="transactionKeywordInput"
                                                name="search" placeholder="Transaction ID, clerk, or type..."
                                                value="{{ request('search') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Status</label>
                                        <select class="form-select" id="transactionStatusFilter" name="status">
                                            <option value="">All Status</option>
                                            @foreach (($filterStatuses ?? []) as $status)
                                                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                                    {{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionClientCategoryFilter"
                                            class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        <select class="form-select" id="transactionClientCategoryFilter" name="client_category">
                                            <option value="">All Client Categories</option>
                                            @foreach (($filterClientCategories ?? []) as $clientCategory)
                                                <option value="{{ $clientCategory }}" {{ request('client_category') === $clientCategory ? 'selected' : '' }}>
                                                    {{ $clientCategory }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if (!$category)
                                        <div class="col-12 col-md-6 col-xl-2">
                                            <label for="transactionCategoryFilter"
                                                class="form-label fw-semibold text-uppercase small">Category</label>
                                            <select class="form-select" id="transactionCategoryFilter" name="category_filter">
                                                <option value="">All Categories</option>
                                                @foreach (($filterCategories ?? []) as $key => $label)
                                                    <option value="{{ $key }}" {{ request('category_filter') === $key ? 'selected' : '' }}>
                                                        {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="transactionDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="transactionDateTo"
                                            class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="transactionDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 d-flex gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4" id="transactionDateApplyBtn">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3" id="transactionSearchSummary">
                                    {{ request()->anyFilled(['search', 'status', 'client_category', 'category_filter', 'date_from', 'date_to']) ? 'Filtered transactions are shown below.' : 'Showing all transactions.' }}
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Client</th>
                                        <th>Transaction ID</th>
                                        <th>Event Date</th>
                                        <th>Transaction Category</th>
                                        <th>Transaction Type</th>
                                        <th>Clerk</th>
                                        <th style="width: 110px; text-align: center;">Status</th>
                                        <th style="width: 200px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($transactions as $transaction)
                                        @php
                                            $isApproved = strtolower($transaction->status ?? 'Pending') === 'approved';
                                            $client = App\Models\Client::where('client_id', $transaction->client_id)->first();
                                            $clientName = $client->full_name ?? $transaction->client_id;
                                        @endphp
                                        <tr class="{{ $isApproved ? '' : 'table-warning' }}" data-transaction-row
                                            data-search-transaction-id="{{ strtolower($transaction->transaction_id ?? '') }}"
                                            data-search-client="{{ strtolower($clientName) }}"
                                            data-search-client-id="{{ strtolower($transaction->client_id ?? '') }}"
                                            data-search-category="{{ strtolower($transaction->category ?? '') }}"
                                            data-search-category-key="{{ strtolower(App\Models\TransactionHistory::normalizeCategory($transaction->category) ?? '') }}"
                                            data-search-type="{{ strtolower($transaction->type ?? '') }}"
                                            data-search-clerk="{{ strtolower($transaction->clerk ?? '') }}"
                                            data-search-status="{{ strtolower($transaction->status ?? 'pending') }}"
                                            data-search-client-category="{{ strtolower($transaction->client_category ?? '') }}"
                                            data-search-date="{{ $transaction->transaction_date?->format('Y-m-d') }}"
                                            data-search-all="{{ strtolower(($transaction->transaction_id ?? '') . ' ' . $clientName . ' ' . ($transaction->client_id ?? '') . ' ' . ($transaction->category ?? '') . ' ' . ($transaction->type ?? '') . ' ' . ($transaction->clerk ?? '') . ' ' . ($transaction->status ?? '') . ' ' . ($transaction->client_category ?? '')) }}">
                                            <td class="fw-semibold">{{ $clientName }}</td>
                                            <td class="small">
                                                <a href="{{ route('transactions.show', $transaction->id) }}"
                                                    class="text-primary">{{ $transaction->transaction_id }}</a>
                                            </td>
                                            <td class="small">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                                            <td class="small">{{ $transaction->category_label ?? '-' }}</td>
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
                                                        class="btn btn-sm btn-soft-primary" title="View in client details">
                                                        <i class="ri-eye-line"></i> View in Client Details
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No transactions found{{ $category ? ' under this category' : '' }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                        <tr id="transactionSearchNoResultsRow" class="d-none">
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No transactions match the current filters.
                                            </td>
                                        </tr>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const transactionFiltersToggleBtn = document.getElementById('transactionFiltersToggleBtn');
            const transactionFiltersFormEl = document.getElementById('transactionFiltersForm');
            const transactionKeywordInput = document.getElementById('transactionKeywordInput');
            const transactionStatusFilter = document.getElementById('transactionStatusFilter');
            const transactionClientCategoryFilter = document.getElementById('transactionClientCategoryFilter');
            const transactionCategoryFilter = document.getElementById('transactionCategoryFilter');
            const transactionDateFrom = document.getElementById('transactionDateFrom');
            const transactionDateTo = document.getElementById('transactionDateTo');
            const transactionSearchSummary = document.getElementById('transactionSearchSummary');
            const transactionSearchNoResultsRow = document.getElementById('transactionSearchNoResultsRow');

            if (!transactionFiltersToggleBtn || !transactionFiltersFormEl || !transactionKeywordInput ||
                !transactionStatusFilter || !transactionClientCategoryFilter || !transactionDateFrom ||
                !transactionDateTo || !transactionSearchSummary
            ) {
                return;
            }

            const transactionRows = Array.from(document.querySelectorAll('[data-transaction-row]'));
            let filtersVisible = !transactionFiltersFormEl.classList.contains('d-none');

            const setFiltersVisibility = (visible) => {
                filtersVisible = visible;
                transactionFiltersFormEl.classList.toggle('d-none', !visible);
                transactionFiltersToggleBtn.innerHTML = visible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };

            const syncToggleLabel = () => {
                transactionFiltersToggleBtn.innerHTML = filtersVisible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };
            syncToggleLabel();

            transactionFiltersToggleBtn.addEventListener('click', function() {
                setFiltersVisibility(!filtersVisible);
            });

            const filterTransactionList = () => {
                const query = transactionKeywordInput.value.trim().toLowerCase();
                const status = transactionStatusFilter.value.trim().toLowerCase();
                const clientCategory = transactionClientCategoryFilter.value.trim().toLowerCase();
                const category = transactionCategoryFilter ? transactionCategoryFilter.value.trim().toLowerCase() : '';
                const dateFrom = transactionDateFrom.value;
                const dateTo = transactionDateTo.value;
                let visibleCount = 0;

                transactionRows.forEach((row) => {
                    const searchableValue = row.dataset.searchAll || '';
                    const rowStatus = (row.dataset.searchStatus || '').toLowerCase();
                    const rowClientCategory = (row.dataset.searchClientCategory || '').toLowerCase();
                    const rowCategory = (row.dataset.searchCategory || '').toLowerCase();
                    const rowCategoryKey = (row.dataset.searchCategoryKey || '').toLowerCase();
                    const rowDate = row.dataset.searchDate || '';
                    const matchesSearch = !query || searchableValue.includes(query);
                    const matchesStatus = !status || rowStatus === status;
                    const matchesClientCategory = !clientCategory || rowClientCategory === clientCategory;
                    const matchesCategory = !category || rowCategoryKey === category || rowCategory === category;
                    const matchesDate = (!dateFrom || rowDate >= dateFrom) && (!dateTo || rowDate <= dateTo);
                    const matches = matchesSearch && matchesStatus && matchesClientCategory &&
                        matchesCategory && matchesDate;
                    row.classList.toggle('d-none', !matches);

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (transactionSearchNoResultsRow) {
                    transactionSearchNoResultsRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (transactionSearchSummary) {
                    const activeFilters = [query, status, clientCategory, category, dateFrom, dateTo].filter(
                        Boolean).length;
                    if (!activeFilters) {
                        transactionSearchSummary.textContent = 'Showing all transactions.';
                    } else {
                        transactionSearchSummary.textContent =
                            `Showing ${visibleCount} matching transaction${visibleCount === 1 ? '' : 's'}.`;
                    }
                }
            };

            transactionKeywordInput.addEventListener('input', filterTransactionList);
            transactionStatusFilter.addEventListener('change', filterTransactionList);
            transactionClientCategoryFilter.addEventListener('change', filterTransactionList);
            if (transactionCategoryFilter) {
                transactionCategoryFilter.addEventListener('change', filterTransactionList);
            }
            transactionDateFrom.addEventListener('change', filterTransactionList);
            transactionDateTo.addEventListener('change', filterTransactionList);
        });
    </script>
@endpush