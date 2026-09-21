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
                                        service category, transaction category/type, and transaction date range. Search covers all pages.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-primary"
                                        id="transactionFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    @php
                                        // Reset clears all filters AND returns to the default A–Z sort.
                                        $resetUrl = ($category ? route('transactions.category', $category) : route('transactions.index')) . '?sort=client_asc';
                                    @endphp
                                    <a href="{{ $resetUrl }}"
                                        class="btn btn-sm btn-soft-primary" id="transactionFiltersResetBtn">Reset</a>
                                </div>
                            </div>

                            <form method="GET" id="transactionFiltersForm" class="mt-3 {{ request()->anyFilled(['search', 'status', 'client_category', 'category_filter', 'transaction_category', 'transaction_type', 'date_from', 'date_to']) ? '' : 'd-none' }}">
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
                                        <label class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        @include('pages.transaction_events.partials.multiSelectSearchDropdown', [
                                            'dropdownId' => 'txClientCategory',
                                            'fieldName' => 'client_category',
                                            'options' => $filterClientCategories ?? [],
                                            'allLabel' => 'All client categories',
                                            'searchPlaceholder' => 'Search client categories...',
                                        ])
                                    </div>
                                    @if (!$category)
                                        <div class="col-12 col-md-6 col-xl-2">
                                            <label class="form-label fw-semibold text-uppercase small">Transaction Category</label>
                                            @include('pages.transaction_events.partials.multiSelectSearchDropdown', [
                                                'dropdownId' => 'txTransactionCategory',
                                                'fieldName' => 'transaction_category',
                                                'options' => $filterTransactionCategories ?? [],
                                                'allLabel' => 'All transaction categories',
                                                'searchPlaceholder' => 'Search categories...',
                                            ])
                                        </div>
                                    @endif
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        @include('pages.transaction_events.partials.multiSelectSearchDropdown', [
                                            'dropdownId' => 'txTransactionType',
                                            'fieldName' => 'transaction_type',
                                            'options' => $filterTransactionTypes ?? [],
                                            'allLabel' => 'All transaction types',
                                            'searchPlaceholder' => 'Search types...',
                                        ])
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
                                    {{ request()->anyFilled(['search', 'status', 'client_category', 'category_filter', 'transaction_category', 'transaction_type', 'date_from', 'date_to']) ? 'Filtered transactions are shown below.' : 'Showing all transactions.' }}
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        @php
                                            $currentSort = $sort ?? request('sort', 'client_asc');
                                        @endphp
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Client', 'asc' => 'client_asc', 'desc' => 'client_desc', 'current' => $currentSort])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Transaction ID', 'asc' => 'txid_asc', 'desc' => 'txid_desc', 'current' => $currentSort])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Event Date', 'asc' => 'date_asc', 'desc' => 'date_desc', 'current' => $currentSort])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Transaction Category', 'asc' => 'category_asc', 'desc' => 'category_desc', 'current' => $currentSort])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Transaction Type', 'asc' => 'type_asc', 'desc' => 'type_desc', 'current' => $currentSort])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Client Category', 'asc' => 'clientcat_asc', 'desc' => 'clientcat_desc', 'current' => $currentSort])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Status', 'asc' => 'status_asc', 'desc' => 'status_desc', 'current' => $currentSort, 'center' => true, 'style' => 'width: 130px; text-align: center;'])
                                        <th style="width: 200px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($transactions as $transaction)
                                        @php
                                            $client = App\Models\Client::where('client_id', $transaction->client_id)->first();
                                            $clientName = ($client && filled($client->list_display_name)) ? $client->list_display_name : $transaction->client_id;
                                        @endphp
                                        <tr data-transaction-row
                                            data-search-transaction-id="{{ strtolower($transaction->transaction_id ?? '') }}"
                                            data-search-client="{{ strtolower($clientName) }}"
                                            data-search-client-id="{{ strtolower($transaction->client_id ?? '') }}"
                                            data-search-category="{{ strtolower($transaction->category ?? '') }}"
                                            data-search-category-key="{{ strtolower(App\Models\TransactionHistory::normalizeCategory($transaction->category) ?? '') }}"
                                            data-search-type="{{ strtolower($transaction->type ?? '') }}"
                                            data-search-events-transaction-type="{{ strtolower($transaction->events_transaction_type ?? '') }}"
                                            data-search-clerk="{{ strtolower($transaction->clerk ?? '') }}"
                                            data-search-status="{{ strtolower($transaction->status ?? 'pending') }}"
                                            data-search-client-category="{{ strtolower($transaction->client_category ?? '') }}"
                                            data-search-date="{{ $transaction->transaction_date?->format('Y-m-d') }}"
                                            data-search-all="{{ strtolower(($transaction->transaction_id ?? '') . ' ' . $clientName . ' ' . ($transaction->client_id ?? '') . ' ' . ($transaction->category ?? '') . ' ' . ($transaction->type ?? '') . ' ' . ($transaction->events_transaction_type ?? '') . ' ' . ($transaction->clerk ?? '') . ' ' . ($transaction->status ?? '') . ' ' . ($transaction->client_category ?? '')) }}">
                                            <td class="fw-semibold">{{ $clientName }}</td>
                                            <td class="fw-semibold" style="font-size: 14px">
                                                {{ $transaction->transaction_id }}
                                            </td>
                                            <td class="small text-uppercase">{{ filled($transaction->client_category) ? $transaction->client_category : ($client->sector ?? '-') }}</td>
                                            <td class="small">{{ $transaction->category_label ?? '-' }}</td>
                                            <td class="small">{{ $transaction->events_transaction_type ?: $transaction->type_label }}</td>
                                            <td class="small">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                                            <td class="text-center">
                                               @php
                                                    $txStatus = $transaction->status ?? 'Pending';
                                                    $statusColor = match (strtolower(trim($txStatus))) {
                                                        'claimed' => 'success',
                                                        'unclaimed' => 'danger',
                                                        'pending' => 'warning',
                                                        default => 'secondary',
                                                    };
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} px-3 py-2">{{ $txStatus }}</span>
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
            const transactionCategoryFilter = document.getElementById('transactionCategoryFilter');
            const transactionDateFrom = document.getElementById('transactionDateFrom');
            const transactionDateTo = document.getElementById('transactionDateTo');
            const transactionSearchSummary = document.getElementById('transactionSearchSummary');
            const transactionSearchNoResultsRow = document.getElementById('transactionSearchNoResultsRow');

            const checkedValues = (checkboxClass) => Array.from(document.querySelectorAll('.' + checkboxClass + ':checked'))
                .map((cb) => cb.value.trim().toLowerCase()).filter(Boolean);

            if (!transactionFiltersToggleBtn || !transactionFiltersFormEl || !transactionKeywordInput ||
                !transactionStatusFilter || !transactionDateFrom ||
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
                const clientCategories = checkedValues('txClientCategory-checkbox');
                const category = transactionCategoryFilter ? transactionCategoryFilter.value.trim().toLowerCase() : '';
                const txCategories = checkedValues('txTransactionCategory-checkbox');
                const txTypes = checkedValues('txTransactionType-checkbox');
                const dateFrom = transactionDateFrom.value;
                const dateTo = transactionDateTo.value;
                let visibleCount = 0;

                transactionRows.forEach((row) => {
                    const searchableValue = row.dataset.searchAll || '';
                    const rowStatus = (row.dataset.searchStatus || '').toLowerCase();
                    const rowClientCategory = (row.dataset.searchClientCategory || '').toLowerCase();
                    const rowCategory = (row.dataset.searchCategory || '').toLowerCase();
                    const rowCategoryKey = (row.dataset.searchCategoryKey || '').toLowerCase();
                    const rowTxType = (row.dataset.searchEventsTransactionType || '').toLowerCase();
                    const rowDate = row.dataset.searchDate || '';
                    const matchesSearch = !query || searchableValue.includes(query);
                    const matchesStatus = !status || rowStatus === status;
                    const matchesClientCategory = clientCategories.length === 0 || clientCategories.includes(rowClientCategory);
                    const matchesCategory = !category || rowCategoryKey === category || rowCategory === category;
                    const matchesTxCategory = txCategories.length === 0 || txCategories.includes(rowCategory);
                    const matchesTxType = txTypes.length === 0 || txTypes.includes(rowTxType);
                    const matchesDate = (!dateFrom || rowDate >= dateFrom) && (!dateTo || rowDate <= dateTo);
                    const matches = matchesSearch && matchesStatus && matchesClientCategory &&
                        matchesCategory && matchesTxCategory && matchesTxType && matchesDate;
                    row.classList.toggle('d-none', !matches);

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (transactionSearchNoResultsRow) {
                    transactionSearchNoResultsRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (transactionSearchSummary) {
                    const activeCount = [query, status, category, dateFrom, dateTo].filter(Boolean).length +
                        clientCategories.length + txCategories.length + txTypes.length;
                    if (!activeCount) {
                        transactionSearchSummary.textContent = 'Showing all transactions.';
                    } else {
                        transactionSearchSummary.textContent =
                            `Showing ${visibleCount} matching transaction${visibleCount === 1 ? '' : 's'}.`;
                    }
                }
            };

            transactionKeywordInput.addEventListener('input', filterTransactionList);
            transactionStatusFilter.addEventListener('change', filterTransactionList);
            if (transactionCategoryFilter) {
                transactionCategoryFilter.addEventListener('change', filterTransactionList);
            }
            document.addEventListener('multiSelectChange', filterTransactionList);
            transactionDateFrom.addEventListener('change', filterTransactionList);
            transactionDateTo.addEventListener('change', filterTransactionList);
        });
    </script>
@endpush
