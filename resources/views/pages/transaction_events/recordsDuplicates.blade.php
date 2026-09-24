@extends('layouts.master')
@section('title', 'ERS | Events - Duplicate Records')

@section('content')
    <div class="container-fluid">
        @foreach (['success' => 'success', 'error' => 'danger'] as $message => $color)
            @if (session($message))
                <div class="alert alert-{{ $color }} alert-dismissible fade show duplicate-records-flash-alert"
                    role="alert" data-auto-dismiss-ms="5000">
                    {{ session($message) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        @endforeach
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Duplicate Events Records</h4>
                                <p class="text-muted mb-0">Review duplicate records grouped by client. Each client appears once per tab, with each matching record listed once.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @if (feature_allowed('View Removed Duplicates'))
                                    <a href="{{ route('transaction-events.removed-duplicates') }}"
                                        class="btn btn-warning btn-sm">
                                        <i class="ri-file-list-3-line me-1"></i> Not a Duplicate Review
                                    </a>
                                @endif
                                <a href="{{ route('transaction-events.records') }}"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="ri-arrow-left-line me-1"></i> Back to Event Records
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @php
                            $activeTab = request('duplicate_tab', request()->has('similar_page') ? 'full_name' : (request()->has('likely_page') ? 'likely' : 'exact'));
                            $showLikely = $activeTab === 'likely';
                            $showFullName = $activeTab === 'full_name';
                            $exactCount = $exactRecordsTotal ?? $exactGroups->sum('total');
                            $likelyCount = $likelyRecordsTotal ?? $likelyGroups->sum('total');
                            $similarCount = $similarRecordsTotal ?? $similarGroups->sum('total');

                            $renderGroup = function ($group, $tab) {
                                $first = $group['events']->first();
                                $groupIds = $group['events']->pluck('id')->values();
                                $out = '<div class="border rounded-4 p-3 mb-3">';
                                $out .= '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">';
                                $out .= '<div>';
                                $out .= '<h6 class="mb-0">' . e($first->full_name) . ' (' . e($first->transferredTransaction?->transaction_id ?? '-') . ') <span class="badge bg-danger-subtle text-danger ms-1">' .  (int) $group['total'] . ' records</span></h6>';
                                $out .= '</div>';
                                if (auth()->user()?->role_name !== 'Viewer') {
                                    $out .= '<button type="button" class="btn btn-sm btn-outline-success text-nowrap" data-bs-toggle="modal" data-bs-target="#notDuplicateGroupModal" data-event-ids="' . e($groupIds->implode(',')) . '" data-group-name="' . e($first->full_name) . '" data-record-count="' . (int) $groupIds->count() . '"><i class="ri-check-line me-1"></i> Not a duplicate</button>';
                                }
                                $out .= '</div>';
                                $out .= '<div class="table-responsive">';
                                $out .= '<table class="table table-sm table-hover align-middle mb-0">';
                                $out .= '<thead class="table-light"><tr>';
                                $out .= '<th>ID</th><th>Transaction ID</th><th>Full Name</th><th>Age</th><th>Birth Date</th><th>Contact No.</th><th>Client Category</th><th>Transaction Category</th><th>Transaction Type</th><th>Event Date</th><th>Status</th>';
                                if (auth()->user()?->role_name !== 'Viewer') {
                                    $out .= '<th class="text-center">Action</th>';
                                }
                                $out .= '</tr></thead><tbody>';
                                foreach ($group['events'] as $event) {
                                    $txId = $event->transferredTransaction?->transaction_id ?? '-';
                                    $eventSummary = implode(' · ', array_filter([
                                        $txId !== '-' ? $txId : null,
                                        optional($event->event_date)->format('M d, Y'),
                                        $event->transaction_category,
                                        $event->transaction_type,
                                    ]));
                                    $out .= '<tr data-event-id="' . (int) $event->id . '" data-event-name="' . e($event->full_name) . '" data-event-summary="' . e($eventSummary) . '">';
                                    $out .= '<td>' . e($event->id) . '</td>';
                                    $out .= '<td class="fw-semibold">' . e($txId) . '</td>';
                                    $out .= '<td class="fw-semibold">' . e($event->full_name) . '</td>';
                                    $out .= '<td>' . e($event->age ?? '-') . '</td>';
                                    $out .= '<td>' . e(optional($event->birth_date)->format('M d, Y') ?? '-') . '</td>';
                                    $out .= '<td>' . e($event->contact_no ?: '-') . '</td>';
                                    $out .= '<td class="small">' . e($event->client_category ?? '-') . '</td>';
                                    $out .= '<td class="small">' . e($event->transaction_category ?? '-') . '</td>';
                                    $out .= '<td class="small">' . e($event->transaction_type ?? '-') . '</td>';
                                    $out .= '<td>' . e(optional($event->event_date)->format('M d, Y') ?? '-') . '</td>';
                                    $statusColor = match ($event->status) {
                                        'Claimed' => 'success',
                                        'Unclaimed' => 'danger',
                                        default => 'warning',
                                    };
                                    $out .= '<td><span class="badge bg-' . $statusColor . '-subtle text-' . $statusColor . '">' . e($event->status) . '</span></td>';
                                    if (auth()->user()?->role_name !== 'Viewer') {
                                        $out .= '<td class="text-center text-nowrap">';
                                        $out .= view('pages.transaction_events.partials.duplicateStatusAction', ['event' => $event, 'tab' => $tab])->render();
                                        if (feature_allowed('Undo Transfer')) {
                                            $undoUrl = route('transaction-events.undo-transfer', array_merge(request()->query(), ['event' => $event, 'duplicate_tab' => $tab]));
                                            $out .= '<button type="button" class="btn btn-sm btn-soft-warning" data-bs-toggle="modal" data-bs-target="#undoSingleTransferModal" data-undo-url="' . e($undoUrl) . '" data-event-id="' . (int) $event->id . '" data-event-name="' . e($event->full_name) . '" title="Undo transfer"><i class="ri-arrow-go-back-line me-1"></i> Undo Transfer</button>';
                                        }
                                        $out .= '</td>';
                                    }
                                    $out .= '</tr>';
                                }
                                $out .= '</tbody></table></div></div>';
                                return $out;
                            };

                        @endphp

                        <div class="border rounded-4 p-3 mb-4" id="dupFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-0">
                                <div>
                                    <div class="fw-bold fs-5">Filter Duplicates</div>
                                    <div class="text-muted small">Narrow groups by keyword, category, type, and event
                                        date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-primary" id="dupFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="{{ route('transaction-events.records-duplicates') }}"
                                        class="btn btn-sm btn-soft-primary">Reset</a>
                                    <select class="form-select form-select-sm w-auto" id="dupPerPageSelect"
                                        aria-label="Groups per page" title="Groups per page">
                                        @foreach ([10, 15, 25, 50, 100] as $size)
                                            <option value="{{ $size }}"
                                                {{ ($perPage ?? 10) == $size ? 'selected' : '' }}>
                                                {{ $size }} / page</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <form method="GET" id="dupFiltersForm"
                                class="mt-3 {{ request()->anyFilled(['search', 'client_category', 'transaction_category', 'transaction_type', 'status', 'date_from', 'date_to']) ? '' : 'd-none' }}">
                                <input type="hidden" name="duplicate_tab" id="dupActiveTab" value="{{ $activeTab }}">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="dupKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="dupKeywordInput" name="search"
                                                placeholder="Name, category, type, date, transaction ID..."
                                                value="{{ request('search') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        <div class="dropdown w-100">
                                            <button
                                                class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
                                                type="button" id="dupClientCategoryBtn" data-bs-toggle="dropdown"
                                                data-bs-auto-close="outside" aria-expanded="false"
                                                style="padding: .5rem 0.75rem;">
                                                <span id="dupClientCategoryLabel">All client categories</span>
                                            </button>
                                            <div class="dropdown-menu w-100" id="dupClientCategoryDropdown"
                                                style="max-height: 260px; overflow-y: auto;">
                                                <div class="p-2">
                                                    <input type="search" class="form-control form-control-sm mb-2" placeholder="Search client categories..." autocomplete="off" data-dropdown-search>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="dupClientCategoryAll" value="">
                                                        <label class="form-check-label fw-semibold"
                                                            for="dupClientCategoryAll">
                                                            All client categories
                                                        </label>
                                                    </div>
                                                    <hr class="my-2">
                                                    @foreach (($filterClientCategories ?? []) as $clientCategory)
                                                        @php
                                                            $dupSelectedClientCategories = collect((array) request('client_category', []))->filter();
                                                            $dupIsClientCategoryChecked = $dupSelectedClientCategories->contains($clientCategory);
                                                        @endphp
                                                        <div class="form-check" data-option-row data-option-label="{{ strtolower($clientCategory) }}">
                                                            <input class="form-check-input dup-client-category-checkbox"
                                                                type="checkbox" id="dupClientCategory_{{ $loop->index }}"
                                                                value="{{ $clientCategory }}"
                                                                {{ $dupIsClientCategoryChecked ? 'checked' : '' }}>
                                                            <label class="form-check-label"
                                                                for="dupClientCategory_{{ $loop->index }}">
                                                                {{ $clientCategory }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                    <div class="text-muted small px-1 py-2 d-none" data-dropdown-empty>No matches found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Transaction Category</label>
                                        <div class="dropdown w-100">
                                            <button
                                                class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
                                                type="button" id="dupTransactionCategoryBtn" data-bs-toggle="dropdown"
                                                data-bs-auto-close="outside" aria-expanded="false"
                                                style="padding: .5rem 0.75rem;">
                                                <span id="dupTransactionCategoryLabel">All categories</span>
                                            </button>
                                            <div class="dropdown-menu w-100" id="dupTransactionCategoryDropdown"
                                                style="max-height: 260px; overflow-y: auto;">
                                                <div class="p-2">
                                                    <input type="search" class="form-control form-control-sm mb-2" placeholder="Search categories..." autocomplete="off" data-dropdown-search>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="dupTransactionCategoryAll" value="">
                                                        <label class="form-check-label fw-semibold"
                                                            for="dupTransactionCategoryAll">
                                                            All categories
                                                        </label>
                                                    </div>
                                                    <hr class="my-2">
                                                    @foreach (($filterTransactionCategories ?? []) as $transactionCategory)
                                                        @php
                                                            $dupSelectedTransactionCategories = collect((array) request('transaction_category', []))->filter();
                                                            $dupIsTransactionCategoryChecked = $dupSelectedTransactionCategories->contains($transactionCategory);
                                                        @endphp
                                                        <div class="form-check" data-option-row data-option-label="{{ strtolower($transactionCategory) }}">
                                                            <input class="form-check-input dup-transaction-category-checkbox"
                                                                type="checkbox" id="dupTransactionCategory_{{ $loop->index }}"
                                                                value="{{ $transactionCategory }}"
                                                                {{ $dupIsTransactionCategoryChecked ? 'checked' : '' }}>
                                                            <label class="form-check-label"
                                                                for="dupTransactionCategory_{{ $loop->index }}">
                                                                {{ $transactionCategory }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                    <div class="text-muted small px-1 py-2 d-none" data-dropdown-empty>No matches found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <div class="dropdown w-100">
                                            <button
                                                class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
                                                type="button" id="dupTransactionTypeBtn" data-bs-toggle="dropdown"
                                                data-bs-auto-close="outside" aria-expanded="false"
                                                style="padding: .5rem 0.75rem;">
                                                <span id="dupTransactionTypeLabel">All types</span>
                                            </button>
                                            <div class="dropdown-menu w-100" id="dupTransactionTypeDropdown"
                                                style="max-height: 260px; overflow-y: auto;">
                                                <div class="p-2">
                                                    <input type="search" class="form-control form-control-sm mb-2" placeholder="Search types..." autocomplete="off" data-dropdown-search>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="dupTransactionTypeAll" value="">
                                                        <label class="form-check-label fw-semibold"
                                                            for="dupTransactionTypeAll">
                                                            All types
                                                        </label>
                                                    </div>
                                                    <hr class="my-2">
                                                    @foreach (($filterTransactionTypes ?? []) as $transactionType)
                                                        @php
                                                            $dupSelectedTransactionTypes = collect((array) request('transaction_type', []))->filter();
                                                            $dupIsTransactionTypeChecked = $dupSelectedTransactionTypes->contains($transactionType);
                                                        @endphp
                                                        <div class="form-check" data-option-row data-option-label="{{ strtolower($transactionType) }}">
                                                            <input class="form-check-input dup-transaction-type-checkbox"
                                                                type="checkbox" id="dupTransactionType_{{ $loop->index }}"
                                                                value="{{ $transactionType }}"
                                                                {{ $dupIsTransactionTypeChecked ? 'checked' : '' }}>
                                                            <label class="form-check-label"
                                                                for="dupTransactionType_{{ $loop->index }}">
                                                                {{ $transactionType }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                    <div class="text-muted small px-1 py-2 d-none" data-dropdown-empty>No matches found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupStatusFilter" class="form-label fw-semibold text-uppercase small">Status</label>
                                        <select class="form-select" id="dupStatusFilter" name="status">
                                            <option value="">All statuses</option>
                                            @foreach (\App\Models\TransactionEvent::STATUSES as $status)
                                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="dupDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupDateTo"
                                            class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="dupDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 d-flex gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3">
                                    {{ request()->anyFilled(['search', 'client_category', 'transaction_category', 'transaction_type', 'status', 'date_from', 'date_to']) ? 'Filtered groups are shown below.' : 'Showing all duplicate groups.' }}
                                </div>
                            </form>
                        </div>

                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link {{ $showLikely || $showFullName ? '' : 'active' }}" data-bs-toggle="tab" href="#rexact-tab" role="tab" data-client-count="{{ $exactGroups->total() }}" data-record-count="{{ $exactCount }}">
                                    Exact Match
                                    <span class="badge bg-danger-subtle text-danger ms-1">{{ $exactGroupsTotal ?? $exactGroups->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $showLikely ? 'active' : '' }}" data-bs-toggle="tab" href="#rlikely-tab" role="tab" data-client-count="{{ $likelyGroups->total() }}" data-record-count="{{ $likelyCount }}">
                                    Likely Match
                                    <span class="badge bg-warning-subtle text-warning ms-1">{{ $likelyGroupsTotal ?? $likelyGroups->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $showFullName ? 'active' : '' }}" data-bs-toggle="tab" href="#rsimilar-tab" role="tab" data-client-count="{{ $similarGroups->total() }}" data-record-count="{{ $similarCount }}">
                                    Match Full Name
                                    <span class="badge bg-info-subtle text-info ms-1">{{ $similarGroupsTotal ?? $similarGroups->count() }}</span>
                                </a>
                            </li>
                        </ul>

                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <span id="duplicateClientCount" class="badge bg-primary-subtle text-primary fs-13">{{ $showLikely ? $likelyGroups->total() : ($showFullName ? $similarGroups->total() : $exactGroups->total()) }} client group(s) in this tab</span>
                            <span id="duplicateRecordCount" class="badge bg-danger-subtle text-danger fs-13">{{ $showLikely ? $likelyCount : ($showFullName ? $similarCount : $exactCount) }} record(s) in this tab</span>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade {{ $showLikely || $showFullName ? '' : 'show active' }}" id="rexact-tab" role="tabpanel">
                                <div class="alert alert-danger-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-error-warning-line fs-4 me-2"></i>
                                    <div class="small">Same <strong>Lastname and Firstname</strong>, <strong>Client Category</strong>, <strong>Transaction Category</strong>, <strong>Transaction Type</strong>, and <strong>Event Date</strong>. High confidence duplicates.</div>
                                </div>
                                @forelse ($exactGroups as $group)
                                    {!! $renderGroup($group, 'exact') !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No exact duplicate records found.
                                    </div>
                                @endforelse
                                @if ($exactGroups->total() > 0)
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing {{ $exactGroups->firstItem() }}–{{ $exactGroups->lastItem() }} of {{ $exactGroups->total() }} client groups</div>
                                        {{ $exactGroups->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade {{ $showLikely ? 'show active' : '' }}" id="rlikely-tab" role="tabpanel">
                                <div class="alert alert-warning-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-alert-line fs-4 me-2"></i>
                                    <div class="small">
                                        Same <strong>Lastname and Firstname</strong> plus at least one matching <strong>Event Date</strong>, <strong>Transaction Type</strong>, or <strong>Client Category</strong>. Exact-only groups appear in Exact Match. Review before acting.
                                    </div>
                                </div>
                                @forelse ($likelyGroups as $group)
                                    {!! $renderGroup($group, 'likely') !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No likely duplicate records found.
                                    </div>
                                @endforelse
                                @if ($likelyGroups->total() > 0)
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing {{ $likelyGroups->firstItem() }}–{{ $likelyGroups->lastItem() }} of {{ $likelyGroups->total() }} client groups</div>
                                        {{ $likelyGroups->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade {{ $showFullName ? 'show active' : '' }}" id="rsimilar-tab" role="tabpanel">
                                <div class="alert alert-info-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-information-line fs-4 me-2"></i>
                                    <div class="small">Same <strong>Full Name</strong> only, ignoring letter case and leading or trailing spaces. Birth dates and transaction details may differ.</div>
                                </div>
                                @forelse ($similarGroups as $group)
                                    {!! $renderGroup($group, 'full_name') !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No records with matching full names found.
                                    </div>
                                @endforelse
                                @if ($similarGroups->total() > 0)
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing {{ $similarGroups->firstItem() }}–{{ $similarGroups->lastItem() }} of {{ $similarGroups->total() }} client groups</div>
                                        {{ $similarGroups->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()?->role_name !== 'Viewer')
        <div class="modal fade" id="notDuplicateGroupModal" tabindex="-1"
            aria-labelledby="notDuplicateGroupModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('transaction-events.group-not-duplicate') }}" method="POST"
                        id="notDuplicateGroupForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="notDuplicateGroupModalLabel">
                                <i class="ri-check-double-line text-success me-1"></i> Mark as Not a Duplicate
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Select the Event Records for
                                <strong id="notDuplicateGroupName"></strong> to tag as not duplicates.</p>
                            <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 mb-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="notDuplicateSelectAll" checked>
                                    <label class="form-check-label fw-semibold" for="notDuplicateSelectAll">
                                        Select All
                                    </label>
                                </div>
                                <span class="badge bg-success-subtle text-success">
                                    <span id="notDuplicateSelectedCount">0</span> selected
                                </span>
                            </div>
                            <div id="notDuplicateRecordChoices" class="border rounded-3 p-2"
                                style="max-height: 300px; overflow-y: auto;"></div>
                            <div class="alert alert-warning-subtle mt-3 mb-0">
                                Selected records will be removed from Duplicate Event Records and stored in Not a
                                Duplicate Review.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" id="confirmNotDuplicateGroupBtn">
                                <i class="ri-check-line me-1"></i> Tag Selected as Not a Duplicate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (auth()->user()?->role_name !== 'Viewer' && feature_allowed('Undo Transfer'))
        @include('pages.transaction_events.partials.undoSingleTransferModal')
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.duplicate-records-flash-alert').forEach(alertElement => {
                const delay = Number(alertElement.dataset.autoDismissMs) || 5000;
                window.setTimeout(() => {
                    bootstrap.Alert.getOrCreateInstance(alertElement).close();
                }, delay);
            });

            const notDuplicateModal = document.getElementById('notDuplicateGroupModal');
            const notDuplicateSelectAll = document.getElementById('notDuplicateSelectAll');
            const notDuplicateChoices = document.getElementById('notDuplicateRecordChoices');
            const notDuplicateSelectedCount = document.getElementById('notDuplicateSelectedCount');
            const confirmNotDuplicateBtn = document.getElementById('confirmNotDuplicateGroupBtn');

            const syncNotDuplicateSelection = function() {
                const boxes = Array.from(notDuplicateChoices?.querySelectorAll('[data-not-duplicate-choice]') || []);
                const selected = boxes.filter(box => box.checked);
                if (notDuplicateSelectedCount) notDuplicateSelectedCount.textContent = selected.length;
                if (confirmNotDuplicateBtn) confirmNotDuplicateBtn.disabled = selected.length === 0;
                if (notDuplicateSelectAll) {
                    notDuplicateSelectAll.checked = boxes.length > 0 && selected.length === boxes.length;
                    notDuplicateSelectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
                }
            };

            notDuplicateModal?.addEventListener('show.bs.modal', function(event) {
                const trigger = event.relatedTarget;
                const ids = String(trigger?.dataset.eventIds || '').split(',').filter(Boolean);
                document.getElementById('notDuplicateGroupName').textContent =
                    trigger?.dataset.groupName || 'this client';

                const groupCard = trigger?.closest('.border.rounded-4');
                const eventRows = Array.from(groupCard?.querySelectorAll('tr[data-event-id]') || []);
                const rowsById = new Map(eventRows.map(row => [String(row.dataset.eventId), row]));
                notDuplicateChoices.replaceChildren(...ids.map(id => {
                    const row = rowsById.get(String(id));
                    const wrapper = document.createElement('label');
                    wrapper.className = 'd-flex align-items-start gap-2 rounded-2 px-2 py-2 mb-1 bg-light';

                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.name = 'event_ids[]';
                    input.value = id;
                    input.checked = true;
                    input.className = 'form-check-input mt-1';
                    input.setAttribute('data-not-duplicate-choice', '');
                    input.addEventListener('change', syncNotDuplicateSelection);

                    const details = document.createElement('span');
                    details.className = 'small';
                    const name = document.createElement('span');
                    name.className = 'fw-semibold d-block';
                    name.textContent = row?.dataset.eventName || ('Event #' + id);
                    const summary = document.createElement('span');
                    summary.className = 'text-muted';
                    summary.textContent = '#' + id + (row?.dataset.eventSummary ? ' · ' + row.dataset.eventSummary : '');
                    details.append(name, summary);
                    wrapper.append(input, details);

                    return wrapper;
                }));
                if (notDuplicateSelectAll) notDuplicateSelectAll.checked = true;
                syncNotDuplicateSelection();
            });

            notDuplicateSelectAll?.addEventListener('change', function() {
                notDuplicateChoices?.querySelectorAll('[data-not-duplicate-choice]').forEach(box => {
                    box.checked = this.checked;
                });
                syncNotDuplicateSelection();
            });

            document.querySelectorAll('[data-client-count]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', () => {
                    document.getElementById('duplicateClientCount').textContent = `${tab.dataset.clientCount} client group(s) in this tab`;
                    document.getElementById('duplicateRecordCount').textContent = `${tab.dataset.recordCount} record(s) in this tab`;
                    document.getElementById('dupActiveTab').value = ({
                        '#rexact-tab': 'exact',
                        '#rlikely-tab': 'likely',
                        '#rsimilar-tab': 'full_name',
                    })[tab.getAttribute('href')];
                });
            });
            const toggleBtn = document.getElementById('dupFiltersToggleBtn');
            const formEl = document.getElementById('dupFiltersForm');
            if (!toggleBtn || !formEl) {
                return;
            }
            let filtersVisible = !formEl.classList.contains('d-none');
            const syncToggleLabel = () => {
                toggleBtn.innerHTML = filtersVisible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };
            syncToggleLabel();
            toggleBtn.addEventListener('click', function() {
                filtersVisible = !filtersVisible;
                formEl.classList.toggle('d-none', !filtersVisible);
                syncToggleLabel();
            });
            document.getElementById('dupPerPageSelect')?.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                url.searchParams.set('duplicate_tab', document.getElementById('dupActiveTab').value);
                ['exact_page', 'likely_page', 'similar_page', 'page'].forEach((k) => url.searchParams.delete(k));
                window.location.href = url.toString();
            });

            // ----- Multi-select dropdowns (Client Category / Transaction Category / Transaction Type) -----
            const setupDupMultiSelect = (allId, checkboxClass, labelId, allLabel) => {
                const allCheckbox = document.getElementById(allId);
                const checkboxes = Array.from(document.querySelectorAll('.' + checkboxClass));
                const label = document.getElementById(labelId);
                let updating = false;

                const syncLabel = () => {
                    const checked = checkboxes.filter((cb) => cb.checked);
                    if (!label) return;
                    if (checked.length === 0 || checked.length === checkboxes.length) {
                        label.textContent = allLabel;
                    } else if (checked.length === 1) {
                        label.textContent = checked[0].value;
                    } else {
                        label.textContent = checked.length + ' selected';
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
                    const shouldCheck = this.checked;
                    checkboxes.forEach((cb) => { cb.checked = shouldCheck; });
                    syncLabel();
                });

                checkboxes.forEach((cb) => cb.addEventListener('change', syncLabel));
                syncLabel();
            };

            setupDupMultiSelect('dupClientCategoryAll', 'dup-client-category-checkbox', 'dupClientCategoryLabel', 'All client categories');
            setupDupMultiSelect('dupTransactionCategoryAll', 'dup-transaction-category-checkbox', 'dupTransactionCategoryLabel', 'All categories');
            setupDupMultiSelect('dupTransactionTypeAll', 'dup-transaction-type-checkbox', 'dupTransactionTypeLabel', 'All types');

            formEl?.addEventListener('submit', function() {
                const injectMulti = (checkboxClass, fieldName) => {
                    this.querySelectorAll('input[type="hidden"][name="' + fieldName + '[]"]').forEach((el) => el.remove());
                    const boxes = Array.from(document.querySelectorAll('.' + checkboxClass));
                    const checked = boxes.filter((cb) => cb.checked).map((cb) => cb.value).filter((v) => v !== '');
                    if (checked.length > 0 && checked.length < boxes.length) {
                        checked.forEach((val) => {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = fieldName + '[]';
                            hidden.value = val;
                            this.appendChild(hidden);
                        });
                    }
                };
                injectMulti('dup-client-category-checkbox', 'client_category');
                injectMulti('dup-transaction-category-checkbox', 'transaction_category');
                injectMulti('dup-transaction-type-checkbox', 'transaction_type');
            });
            const initialHash = window.location.hash;
            if (initialHash) {
                const tabTrigger = document.querySelector('a[data-bs-toggle="tab"][href="' + initialHash + '"]');
                if (tabTrigger) {
                    bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
                }
            }
        });
    </script>
@endpush
