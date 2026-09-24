@extends('layouts.master')
@section('title', 'ERS | Not a Duplicate Review')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1 fw-semibold">Not a Duplicate Review</h4>
                        <p class="text-muted mb-0">Reviewed records remain grouped by client, like Duplicate Event
                            Records.</p>
                    </div>
                    <a href="{{ route('transaction-events.records-duplicates') }}"
                        class="btn btn-outline-primary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i> Back to Duplicate Event Records
                    </a>
                </div>
            </div>
        </div>

        @foreach (['success' => 'success', 'error' => 'danger'] as $message => $color)
            @if (session($message))
                <div class="alert alert-{{ $color }} alert-dismissible fade show not-duplicate-review-flash-alert"
                    role="alert" data-auto-dismiss-ms="5000">
                    {{ session($message) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        @endforeach

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @php
                            $reviewFilterKeys = ['search', 'client_category', 'transaction_category', 'transaction_type', 'status', 'date_from', 'date_to'];
                            $reviewFiltersActive = request()->anyFilled($reviewFilterKeys);
                            $reviewSelectedClientCategories = collect((array) request('client_category', []))->filter();
                            $reviewSelectedTransactionCategories = collect((array) request('transaction_category', []))->filter();
                            $reviewSelectedTransactionTypes = collect((array) request('transaction_type', []))->filter();
                        @endphp

                        <div class="border rounded-4 p-3 mb-4" id="reviewFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                                <div>
                                    <div class="fw-bold fs-5">Filter Reviewed Records</div>
                                    <div class="text-muted small">Narrow groups by keyword, category, type, status,
                                        and event date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-primary" id="reviewFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="{{ route('transaction-events.removed-duplicates') }}"
                                        class="btn btn-sm btn-soft-primary">Reset</a>
                                    <select class="form-select form-select-sm w-auto" id="reviewPerPage"
                                        aria-label="Groups per page" title="Groups per page">
                                        @foreach ([10, 15, 25, 50, 100] as $size)
                                            <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>
                                                {{ $size }} / page
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <form method="GET" id="reviewFiltersForm"
                                class="mt-3 {{ $reviewFiltersActive ? '' : 'd-none' }}">
                                <input type="hidden" name="per_page" value="{{ $perPage }}">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="reviewKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="reviewKeywordInput"
                                                name="search" placeholder="Name, category, or type..."
                                                value="{{ request('search') }}">
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        <div class="dropdown w-100">
                                            <button class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
                                                type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                                aria-expanded="false" style="padding: .5rem .75rem;">
                                                <span id="reviewClientCategoryLabel">All client categories</span>
                                            </button>
                                            <div class="dropdown-menu w-100" style="max-height: 260px; overflow-y: auto;">
                                                <div class="p-2">
                                                    <input type="search" class="form-control form-control-sm mb-2"
                                                        placeholder="Search client categories..." autocomplete="off"
                                                        data-dropdown-search>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="reviewClientCategoryAll">
                                                        <label class="form-check-label fw-semibold"
                                                            for="reviewClientCategoryAll">All client categories</label>
                                                    </div>
                                                    <hr class="my-2">
                                                    @foreach ($filterClientCategories as $clientCategory)
                                                        <div class="form-check" data-option-row
                                                            data-option-label="{{ strtolower($clientCategory) }}">
                                                            <input class="form-check-input review-client-category-checkbox"
                                                                type="checkbox" id="reviewClientCategory_{{ $loop->index }}"
                                                                value="{{ $clientCategory }}"
                                                                @checked($reviewSelectedClientCategories->contains($clientCategory))>
                                                            <label class="form-check-label"
                                                                for="reviewClientCategory_{{ $loop->index }}">{{ $clientCategory }}</label>
                                                        </div>
                                                    @endforeach
                                                    <div class="text-muted small px-1 py-2 d-none" data-dropdown-empty>
                                                        No matches found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Transaction Category</label>
                                        <div class="dropdown w-100">
                                            <button class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
                                                type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                                aria-expanded="false" style="padding: .5rem .75rem;">
                                                <span id="reviewTransactionCategoryLabel">All categories</span>
                                            </button>
                                            <div class="dropdown-menu w-100" style="max-height: 260px; overflow-y: auto;">
                                                <div class="p-2">
                                                    <input type="search" class="form-control form-control-sm mb-2"
                                                        placeholder="Search categories..." autocomplete="off"
                                                        data-dropdown-search>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="reviewTransactionCategoryAll">
                                                        <label class="form-check-label fw-semibold"
                                                            for="reviewTransactionCategoryAll">All categories</label>
                                                    </div>
                                                    <hr class="my-2">
                                                    @foreach ($filterTransactionCategories as $transactionCategory)
                                                        <div class="form-check" data-option-row
                                                            data-option-label="{{ strtolower($transactionCategory) }}">
                                                            <input class="form-check-input review-transaction-category-checkbox"
                                                                type="checkbox" id="reviewTransactionCategory_{{ $loop->index }}"
                                                                value="{{ $transactionCategory }}"
                                                                @checked($reviewSelectedTransactionCategories->contains($transactionCategory))>
                                                            <label class="form-check-label"
                                                                for="reviewTransactionCategory_{{ $loop->index }}">{{ $transactionCategory }}</label>
                                                        </div>
                                                    @endforeach
                                                    <div class="text-muted small px-1 py-2 d-none" data-dropdown-empty>
                                                        No matches found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <div class="dropdown w-100">
                                            <button class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
                                                type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                                aria-expanded="false" style="padding: .5rem .75rem;">
                                                <span id="reviewTransactionTypeLabel">All types</span>
                                            </button>
                                            <div class="dropdown-menu w-100" style="max-height: 260px; overflow-y: auto;">
                                                <div class="p-2">
                                                    <input type="search" class="form-control form-control-sm mb-2"
                                                        placeholder="Search types..." autocomplete="off" data-dropdown-search>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="reviewTransactionTypeAll">
                                                        <label class="form-check-label fw-semibold"
                                                            for="reviewTransactionTypeAll">All types</label>
                                                    </div>
                                                    <hr class="my-2">
                                                    @foreach ($filterTransactionTypes as $transactionType)
                                                        <div class="form-check" data-option-row
                                                            data-option-label="{{ strtolower($transactionType) }}">
                                                            <input class="form-check-input review-transaction-type-checkbox"
                                                                type="checkbox" id="reviewTransactionType_{{ $loop->index }}"
                                                                value="{{ $transactionType }}"
                                                                @checked($reviewSelectedTransactionTypes->contains($transactionType))>
                                                            <label class="form-check-label"
                                                                for="reviewTransactionType_{{ $loop->index }}">{{ $transactionType }}</label>
                                                        </div>
                                                    @endforeach
                                                    <div class="text-muted small px-1 py-2 d-none" data-dropdown-empty>
                                                        No matches found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="reviewStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Status</label>
                                        <select class="form-select" id="reviewStatusFilter" name="status">
                                            <option value="">All statuses</option>
                                            @foreach (\App\Models\TransactionEvent::STATUSES as $status)
                                                <option value="{{ $status }}" @selected(request('status') === $status)>
                                                    {{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="reviewDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="reviewDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="reviewDateTo"
                                            class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="reviewDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-sm btn-primary px-4">
                                        <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                    </button>
                                </div>
                                <div class="small mt-3">
                                    {{ $reviewFiltersActive ? 'Filtered groups are shown below.' : 'Showing all reviewed groups.' }}
                                </div>
                            </form>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <span class="badge bg-secondary-subtle text-secondary fs-13 px-3 py-2">
                                {{ $groups->total() }} reviewed client group(s)
                            </span>
                        </div>

                        @forelse ($groups as $group)
                            @php
                                $events = $group['events']->sort(function ($left, $right) {
                                    $nameOrder = strnatcasecmp(trim((string) $left->full_name), trim((string) $right->full_name));

                                    return $nameOrder !== 0 ? $nameOrder : ($left->id <=> $right->id);
                                })->values();
                                $first = $events->first();
                                $groupIds = $events->pluck('id')->values();
                                $sortableHeaders = [
                                    ['label' => 'ID', 'type' => 'number'],
                                    ['label' => 'Transaction ID', 'type' => 'text'],
                                    ['label' => 'Full Name', 'type' => 'text'],
                                    ['label' => 'Birth Date', 'type' => 'date'],
                                    ['label' => 'Contact No.', 'type' => 'text'],
                                    ['label' => 'Client Category', 'type' => 'text'],
                                    ['label' => 'Transaction Category', 'type' => 'text'],
                                    ['label' => 'Transaction Type', 'type' => 'text'],
                                    ['label' => 'Event Date', 'type' => 'date'],
                                    ['label' => 'Status', 'type' => 'text'],
                                ];
                            @endphp
                            <div class="border rounded-4 p-3 mb-0">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <h6 class="mb-0 fw-semibold">
                                        {{ $first->full_name }} -
                                        <span class="fw-bold">Transaction ID: {{ $first->transferredTransaction?->transaction_id ?? '-' }}</span>
                                        <span class="badge bg-danger-subtle text-danger ms-1">
                                            {{ $group['total'] }} records
                                        </span>
                                    </h6>
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span class="small text-muted">
                                            Reviewed
                                            {{ optional($group['reviewed_at'])->timezone('Asia/Manila')->format('M d, Y H:i:s') }}
                                        </span>
                                        @if (auth()->user()?->role_name !== 'Viewer' && feature_allowed('Reset Duplicate Review'))
                                            <button type="button" class="btn btn-sm btn-warning text-nowrap"
                                                data-bs-toggle="modal" data-bs-target="#undoReviewGroupModal"
                                                data-event-ids="{{ $groupIds->implode(',') }}"
                                                data-group-name="{{ $first->full_name }}"
                                                data-record-count="{{ $groupIds->count() }}">
                                                <i class="ri-arrow-go-back-line me-1"></i> Undo Review
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 not-duplicate-group-table">
                                        <thead class="table-light">
                                            <tr>
                                                @foreach ($sortableHeaders as $column => $header)
                                                    @php
                                                        $isDefaultSort = $column === 2;
                                                    @endphp
                                                    <th scope="col"
                                                        @if ($isDefaultSort) aria-sort="ascending" @endif>
                                                        <button type="button"
                                                            class="btn btn-link btn-sm p-0 text-body fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 text-nowrap"
                                                            data-not-duplicate-sort data-sort-column="{{ $column }}"
                                                            data-sort-type="{{ $header['type'] }}"
                                                            data-sort-direction="{{ $isDefaultSort ? 'asc' : '' }}"
                                                            aria-label="Sort by {{ $header['label'] }} {{ $isDefaultSort ? 'descending' : 'ascending' }}">
                                                            {{ $header['label'] }}
                                                            <i class="{{ $isDefaultSort ? 'ri-arrow-up-line text-primary' : 'ri-arrow-up-down-line text-muted' }}"
                                                                aria-hidden="true"></i>
                                                        </button>
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($events as $event)
                                                <tr>
                                                    <td data-sort-value="{{ $event->id }}">{{ $event->id }}</td>
                                                    <td class="fw-semibold"
                                                        data-sort-value="{{ $event->transferredTransaction?->transaction_id ?? '' }}">
                                                        {{ $event->transferredTransaction?->transaction_id ?? '-' }}
                                                    </td>
                                                    <td class="fw-semibold" data-sort-value="{{ $event->full_name }}">
                                                        {{ $event->full_name }}
                                                    </td>
                                                    <td data-sort-value="{{ optional($event->birth_date)->format('Y-m-d') ?? '' }}">
                                                        {{ optional($event->birth_date)->format('M d, Y') ?? '-' }}
                                                    </td>
                                                    <td data-sort-value="{{ $event->contact_no ?: '' }}">
                                                        {{ $event->contact_no ?: '-' }}
                                                    </td>
                                                    <td class="small" data-sort-value="{{ $event->client_category ?? '' }}">
                                                        {{ $event->client_category ?? '-' }}
                                                    </td>
                                                    <td class="small" data-sort-value="{{ $event->transaction_category ?? '' }}">
                                                        {{ $event->transaction_category ?? '-' }}
                                                    </td>
                                                    <td class="small" data-sort-value="{{ $event->transaction_type ?? '' }}">
                                                        {{ $event->transaction_type ?? '-' }}
                                                    </td>
                                                    <td data-sort-value="{{ optional($event->event_date)->format('Y-m-d') ?? '' }}">
                                                        {{ optional($event->event_date)->format('M d, Y') ?? '-' }}
                                                    </td>
                                                    <td data-sort-value="{{ $event->status ?? '' }}">
                                                        @php
                                                            $statusColor = match ($event->status) {
                                                                'Claimed' => 'success',
                                                                'Unclaimed' => 'danger',
                                                                default => 'warning',
                                                            };
                                                        @endphp
                                                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} px-3 py-2" style="font-size: 10px">
                                                            {{ $event->status }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                No events have been marked as not a duplicate.
                            </div>
                        @endforelse

                        @if ($groups->total() > 0)
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                <div class="small text-muted">
                                    Showing {{ $groups->firstItem() }}–{{ $groups->lastItem() }} of
                                    {{ $groups->total() }} client groups
                                </div>
                                {{ $groups->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()?->role_name !== 'Viewer' && feature_allowed('Reset Duplicate Review'))
        <div class="modal fade" id="undoReviewGroupModal" tabindex="-1"
            aria-labelledby="undoReviewGroupModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('transaction-events.group-reset-duplicate') }}" method="POST">
                        @csrf
                        <div id="undoReviewGroupInputs"></div>
                        <div class="modal-header">
                            <h5 class="modal-title" id="undoReviewGroupModalLabel">
                                <i class="ri-arrow-go-back-line text-warning me-1"></i> Undo Not a Duplicate Review
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Return the entire reviewed group for
                                <strong id="undoReviewGroupName"></strong> to duplicate checking?</p>
                            <div class="alert alert-warning-subtle mb-0">
                                All <strong id="undoReviewGroupCount">0</strong> records will be removed from Not a
                                Duplicate Review and can appear again in Duplicate Event Records.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-warning">
                                <i class="ri-arrow-go-back-line me-1"></i> Confirm Undo Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.not-duplicate-review-flash-alert').forEach(alertElement => {
            const delay = Number(alertElement.dataset.autoDismissMs) || 5000;
            window.setTimeout(() => {
                bootstrap.Alert.getOrCreateInstance(alertElement).close();
            }, delay);
        });

        const notDuplicateSortCollator = new Intl.Collator(undefined, {
            numeric: true,
            sensitivity: 'base',
        });

        document.addEventListener('click', function(event) {
            const sortButton = event.target.closest('[data-not-duplicate-sort]');
            if (!sortButton) return;

            const table = sortButton.closest('.not-duplicate-group-table');
            const tbody = table?.tBodies[0];
            if (!tbody) return;

            const column = Number(sortButton.dataset.sortColumn);
            const type = sortButton.dataset.sortType || 'text';
            const direction = sortButton.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
            const rows = Array.from(tbody.rows);

            const valueFor = row => {
                const rawValue = row.cells[column]?.dataset.sortValue?.trim() ?? '';
                if (rawValue === '') return null;
                if (type === 'number') {
                    const numberValue = Number(rawValue);
                    return Number.isNaN(numberValue) ? null : numberValue;
                }
                if (type === 'date') {
                    const dateValue = Date.parse(rawValue);
                    return Number.isNaN(dateValue) ? null : dateValue;
                }
                return rawValue;
            };

            rows
                .map((row, originalIndex) => ({ row, originalIndex, value: valueFor(row) }))
                .sort((left, right) => {
                    if (left.value === null && right.value === null) {
                        return left.originalIndex - right.originalIndex;
                    }
                    if (left.value === null) return 1;
                    if (right.value === null) return -1;

                    const comparison = type === 'text'
                        ? notDuplicateSortCollator.compare(left.value, right.value)
                        : left.value - right.value;
                    return comparison === 0
                        ? left.originalIndex - right.originalIndex
                        : (direction === 'asc' ? comparison : -comparison);
                })
                .forEach(item => tbody.appendChild(item.row));

            table.querySelectorAll('[data-not-duplicate-sort]').forEach(button => {
                button.dataset.sortDirection = '';
                button.closest('th')?.removeAttribute('aria-sort');
                const icon = button.querySelector('i');
                if (icon) icon.className = 'ri-arrow-up-down-line text-muted';
                button.setAttribute('aria-label', `Sort by ${button.textContent.trim()} ascending`);
            });

            sortButton.dataset.sortDirection = direction;
            sortButton.closest('th')?.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
            const activeIcon = sortButton.querySelector('i');
            if (activeIcon) {
                activeIcon.className = direction === 'asc'
                    ? 'ri-arrow-up-line text-primary'
                    : 'ri-arrow-down-line text-primary';
            }
            sortButton.setAttribute('aria-label',
                `Sort by ${sortButton.textContent.trim()} ${direction === 'asc' ? 'descending' : 'ascending'}`);
        });

        const undoReviewModal = document.getElementById('undoReviewGroupModal');
        undoReviewModal?.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            const ids = String(trigger?.dataset.eventIds || '').split(',').filter(Boolean);
            document.getElementById('undoReviewGroupName').textContent =
                trigger?.dataset.groupName || 'this client';
            document.getElementById('undoReviewGroupCount').textContent =
                trigger?.dataset.recordCount || ids.length;

            const inputs = document.getElementById('undoReviewGroupInputs');
            inputs.replaceChildren(...ids.map(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_ids[]';
                input.value = id;
                return input;
            }));
        });

        const reviewFiltersToggle = document.getElementById('reviewFiltersToggleBtn');
        const reviewFiltersForm = document.getElementById('reviewFiltersForm');
        let reviewFiltersVisible = reviewFiltersForm && !reviewFiltersForm.classList.contains('d-none');
        const syncReviewFilterToggle = () => {
            if (!reviewFiltersToggle) return;
            reviewFiltersToggle.innerHTML = reviewFiltersVisible
                ? 'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>'
                : 'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
        };
        syncReviewFilterToggle();
        reviewFiltersToggle?.addEventListener('click', function() {
            reviewFiltersVisible = !reviewFiltersVisible;
            reviewFiltersForm?.classList.toggle('d-none', !reviewFiltersVisible);
            syncReviewFilterToggle();
        });

        const setupReviewMultiSelect = (allId, checkboxClass, labelId, allLabel) => {
            const allCheckbox = document.getElementById(allId);
            const checkboxes = Array.from(document.querySelectorAll('.' + checkboxClass));
            const label = document.getElementById(labelId);
            let updating = false;

            const syncLabel = () => {
                const checked = checkboxes.filter(checkbox => checkbox.checked);
                if (label) {
                    label.textContent = checked.length === 0 || checked.length === checkboxes.length
                        ? allLabel
                        : (checked.length === 1 ? checked[0].value : checked.length + ' selected');
                }
                if (allCheckbox && !updating) {
                    updating = true;
                    allCheckbox.checked = checked.length === checkboxes.length;
                    allCheckbox.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
                    updating = false;
                }
            };

            allCheckbox?.addEventListener('change', function() {
                if (updating) return;
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                syncLabel();
            });
            checkboxes.forEach(checkbox => checkbox.addEventListener('change', syncLabel));
            syncLabel();
        };

        setupReviewMultiSelect('reviewClientCategoryAll', 'review-client-category-checkbox',
            'reviewClientCategoryLabel', 'All client categories');
        setupReviewMultiSelect('reviewTransactionCategoryAll', 'review-transaction-category-checkbox',
            'reviewTransactionCategoryLabel', 'All categories');
        setupReviewMultiSelect('reviewTransactionTypeAll', 'review-transaction-type-checkbox',
            'reviewTransactionTypeLabel', 'All types');

        reviewFiltersForm?.addEventListener('submit', function() {
            const injectSelections = (checkboxClass, fieldName) => {
                this.querySelectorAll(`input[type="hidden"][name="${fieldName}[]"]`).forEach(input => input.remove());
                const checkboxes = Array.from(document.querySelectorAll('.' + checkboxClass));
                const selected = checkboxes.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);
                if (selected.length > 0 && selected.length < checkboxes.length) {
                    selected.forEach(value => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = fieldName + '[]';
                        input.value = value;
                        this.appendChild(input);
                    });
                }
            };

            injectSelections('review-client-category-checkbox', 'client_category');
            injectSelections('review-transaction-category-checkbox', 'transaction_category');
            injectSelections('review-transaction-type-checkbox', 'transaction_type');
        });

        document.getElementById('reviewPerPage')?.addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    </script>
@endpush
