@extends('layouts.master')
@section('title', 'ERS | Transaction Events')

@section('content')
    <style>
        #eventFiltersCard {
            background: #ffffff;
            border: 1px solid #e3e8ef;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }

        #eventFiltersCard .form-label,
        #eventFiltersCard .small,
        #eventFiltersCard .fw-bold,
        #eventFiltersCard .fw-semibold {
            color: #1f2937 !important;
        }

        #eventFiltersCard .input-group-text,
        #eventFiltersCard .form-control,
        #eventFiltersCard .form-select {
            background-color: #f8fafc;
            color: #111827;
            border-color: #d5dbe3;
        }

        #eventFiltersCard .input-group-text {
            color: #475569;
        }

        #eventFiltersCard .form-control::placeholder {
            color: #94a3b8;
        }

        #eventFiltersCard .form-control:focus,
        #eventFiltersCard .form-select:focus {
            border-color: #4d63d6;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.14);
        }

        #eventFiltersCard .client-filters-toggle-btn {
            transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        #eventFiltersCard .client-filters-toggle-btn:hover,
        #eventFiltersCard .client-filters-toggle-btn:focus,
        #eventFiltersCard .client-filters-toggle-btn:active {
            background: #eef2ff;
            color: #2f49c5;
            border-color: #6276df;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.12);
        }

        #eventFiltersCard .btn-primary {
            background: linear-gradient(135deg, #4d63d6, #5a73ff);
            border-color: transparent;
        }
    </style>
    <div class="container-fluid">

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="mb-0">
                            <h5 class="card-title mb-0">Import Events</h5>
                            <p class="text-muted mb-0">Manage and import data events.</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if (feature_allowed('Duplicate Review'))
                                <a href="{{ route('transaction-events.duplicate-review') }}"
                                    class="btn btn-sm {{ $totalDuplicateGroups ? 'btn-warning' : 'btn-outline-warning' }}">
                                    <i class="ri-file-copy-2-line me-1"></i> Duplicate Names
                                    @if ($totalDuplicateGroups)
                                        <span class="badge bg-danger text-white ms-1">{{ $totalDuplicateGroups }}</span>
                                    @endif
                                </a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-warning" disabled
                                    title="You do not have permission to view duplicate names.">
                                    <i class="ri-file-copy-2-line me-1"></i> Not Allowed to View Duplicate Names
                                </button>
                            @endif
                            @if (feature_allowed('View Archive Files'))
                                <a href="{{ route('transaction-events.archives') }}"
                                    class="btn btn-outline-secondary btn-sm">
                                    <i class="ri-archive-line me-1"></i> View Archives Files
                                </a>
                            @else
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                                    title="You do not have permission to view archive files.">
                                    <i class="ri-archive-line me-1"></i> Not Allowed to View Archives
                                </button>
                            @endif
                            <div class="d-flex align-items-center gap-2">
                                @unless (auth()->user()?->role_name === 'Viewer')
                                    @if (feature_allowed('Transfer Selected'))
                                        <button type="button" class="btn btn-success btn-sm" id="bulkTransferBtn" disabled>
                                            <i class="ri-exchange-box-line me-1"></i> Transfer Selected
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-success btn-sm" disabled
                                            title="You do not have permission to transfer selected events.">
                                            <i class="ri-exchange-box-line me-1"></i> Not Allowed to Transfer Selected
                                        </button>
                                    @endif
                                @endunless
                            </div>

                            <form id="bulkTransferForm" action="{{ route('transaction-events.transfer-selected') }}"
                                method="POST" class="d-none">
                                @csrf
                            </form>
                            @unless (auth()->user()?->role_name === 'Viewer')
                                @if (feature_allowed('Download Template'))
                                    <a href="{{ route('transaction-events.template') }}" class="btn btn-soft-primary btn-sm">
                                        <i class="ri-download-2-line me-1"></i> Excel Template
                                    </a>
                                @else
                                    <button type="button" class="btn btn-soft-primary btn-sm" disabled
                                        title="You do not have permission to download the Excel template.">
                                        <i class="ri-download-2-line me-1"></i> Not Allowed to Download Template
                                    </button>
                                @endif
                                @if (feature_allowed('Import CSV'))
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#importModal">
                                        <i class="ri-upload-2-line me-1"></i> Import CSV
                                    </button>
                                @else
                                    <button type="button" class="btn btn-primary btn-sm" disabled
                                        title="You do not have permission to import CSV files.">
                                        <i class="ri-upload-2-line me-1"></i> Not Allowed to Import CSV
                                    </button>
                                @endif
                            @endunless
                            <span class="badge bg-primary-subtle text-primary px-4 py-2">{{ $events->total() }} total</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="border rounded-4 p-3 mb-3" id="eventFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-2">
                                <div>
                                    <div class="fw-bold fs-5">Filter Events</div>
                                    <div class="text-muted small">Narrow pending events by keyword, contact, age,
                                        and created date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary client-filters-toggle-btn"
                                        id="eventFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    @if (request()->hasAny([
                                            'search',
                                            'contact',
                                            'age_from',
                                            'age_to',
                                            'date_from',
                                            'date_to',
                                            'client_category',
                                            'transaction_category',
                                            'transaction_type',
                                        ]))
                                        <a href="{{ route('transaction-events.index') }}"
                                            class="btn btn-sm btn-soft-secondary">Reset</a>
                                    @endif
                                    <select class="form-select form-select-sm w-auto" id="eventPerPageSelect"
                                        aria-label="Records per page" title="Records per page">
                                        @foreach ([15, 25, 50, 100] as $size)
                                            <option value="{{ $size }}"
                                                {{ request('per_page', 15) == $size ? 'selected' : '' }}>
                                                {{ $size }} / page
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-soft-primary btn-sm text-nowrap"
                                        id="eventListColumnsBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        aria-expanded="false" title="Manage Columns">
                                        <i class="ri-layout-column-line me-1"></i> Manage Columns
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 230px;">
                                        <h6 class="dropdown-header px-0">Manage Columns</h6>
                                        @foreach ([
            'full_name' => 'Full Name',
            'age' => 'Age',
            'birth_date' => 'Birth Date',
            'contact_no' => 'Contact No.',
            'address' => 'Address',
            'client_category' => 'Client Category',
            'transaction_category' => 'Transaction Category',
            'transaction_type' => 'Transaction Type',
            'event_date' => 'Event Date',
            'created_at' => 'Imported',
        ] as $key => $label)
                                            <div class="form-check">
                                                <input class="form-check-input event-list-column-toggle" type="checkbox"
                                                    id="evcol-{{ $key }}" value="{{ $key }}" checked>
                                                <label class="form-check-label"
                                                    for="evcol-{{ $key }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                        <hr class="dropdown-divider my-2">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-light flex-fill"
                                                id="resetEventListColumnsBtn">Reset</button>
                                            <button type="button" class="btn btn-primary btn-sm flex-fill"
                                                id="applyEventListColumnsBtn">Apply</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="GET" id="eventFiltersForm"
                                class="{{ request()->hasAny(['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to', 'client_category', 'transaction_category', 'transaction_type']) ? '' : 'd-none' }}">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="eventKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="eventKeywordInput"
                                                name="search" placeholder="Full name" value="{{ request('search') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="eventContactFilter"
                                            class="form-label fw-semibold text-uppercase small">Contact</label>
                                        <input type="text" class="form-control" id="eventContactFilter"
                                            name="contact" placeholder="Contact no." value="{{ request('contact') }}">
                                    </div>
                                    <div class="col-6 col-md-6 col-xl-2">
                                        <label for="eventAgeFrom" class="form-label fw-semibold text-uppercase small">Age
                                            From</label>
                                        <input type="number" min="0" max="120" class="form-control"
                                            id="eventAgeFrom" name="age_from" placeholder="From"
                                            value="{{ request('age_from') }}">
                                    </div>
                                    <div class="col-6 col-md-6 col-xl-2">
                                        <label for="eventAgeTo" class="form-label fw-semibold text-uppercase small">Age
                                            To</label>
                                        <input type="number" min="0" max="120" class="form-control"
                                            id="eventAgeTo" name="age_to" placeholder="To"
                                            value="{{ request('age_to') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="eventDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="eventDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="eventDateTo" class="form-label fw-semibold text-uppercase small">Date
                                            To</label>
                                        <input type="date" class="form-control" id="eventDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="eventClientCategory"
                                            class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        <select class="form-select" id="eventClientCategory" name="client_category">
                                            <option value="">All client categories</option>
                                            @foreach ($clientCategories as $clientCategory)
                                                <option value="{{ $clientCategory }}"
                                                    {{ request('client_category') === $clientCategory ? 'selected' : '' }}>
                                                    {{ $clientCategory }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="eventTransactionCategory"
                                            class="form-label fw-semibold text-uppercase small">Transaction
                                            Category</label>
                                        <select class="form-select" id="eventTransactionCategory"
                                            name="transaction_category">
                                            <option value="">All categories</option>
                                            @foreach ($transactionCategories as $txCategory)
                                                <option value="{{ $txCategory }}"
                                                    {{ request('transaction_category') === $txCategory ? 'selected' : '' }}>
                                                    {{ $txCategory }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="eventTransactionType"
                                            class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <select class="form-select" id="eventTransactionType" name="transaction_type">
                                            <option value="">All types</option>
                                            @foreach ($transactionTypes as $txType)
                                                <option value="{{ $txType }}"
                                                    {{ request('transaction_type') === $txType ? 'selected' : '' }}>
                                                    {{ $txType }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-xl-4 d-flex gap-2 justify-content-xl-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3" id="eventSearchSummary">
                                    {{ request()->hasAny(['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to', 'client_category', 'transaction_category', 'transaction_type']) ? 'Filtered events are shown below.' : 'Showing all pending events.' }}
                                </div>
                            </form>
                        </div>

                        @if (request()->boolean('duplicate_names'))
                            <div class="alert alert-warning py-2 mb-3">
                                Showing transaction events with duplicate names.
                            </div>
                        @endif

                        <div id="selectAllPagesBar"
                            class="alert alert-primary py-2 px-3 mb-3 d-none align-items-center justify-content-between flex-wrap gap-2"
                            role="alert">
                            <span id="selectAllPagesText"></span>
                            <button type="button" id="clearSelectionBtn" class="btn btn-sm btn-light d-none">
                                Clear selection
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0" id="eventListTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">
                                            <div
                                                class="d-inline-flex align-items-center justify-content-center gap-2 text-nowrap">
                                                @unless (auth()->user()?->role_name === 'Viewer')
                                                    <input type="checkbox" class="form-check-input"
                                                        id="selectAllTransactionEvents"
                                                        aria-label="Select all transaction events on this page"
                                                        title="Select all">
                                                @endunless
                                            </div>
                                        </th>
                                        <th data-column="full_name">Full Name</th>
                                        <th data-column="age">Age</th>
                                        <th data-column="birth_date">Birth Date</th>
                                        <th data-column="contact_no">Contact No.</th>
                                        <th data-column="address">Address</th>
                                        <th data-column="client_category">Client Category</th>
                                        <th data-column="transaction_category">Transaction Category</th>
                                        <th data-column="transaction_type">Transaction Type</th>
                                        <th data-column="event_date">Event Date</th>
                                        <th style="width: 100px;" data-column="created_at">Imported</th>
                                        <th style="width: 250px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($events as $event)
                                        @php
                                            $isTransferred = !is_null($event->transferred_at);
                                        @endphp
                                        <tr class="{{ $isTransferred ? 'table-secondary text-muted' : '' }}">
                                            <td class="text-center">
                                                @unless (auth()->user()?->role_name === 'Viewer')
                                                    <input type="checkbox" class="form-check-input transaction-event-checkbox"
                                                        value="{{ $event->id }}"
                                                        aria-label="Select transaction event {{ $event->id }}"
                                                        {{ $isTransferred ? 'disabled' : '' }}
                                                        {{ in_array($event->full_name, $duplicateFullNames, true) ? 'data-duplicate="1" title="Duplicate name - excluded from Select All"' : '' }}>
                                                @endunless
                                            </td>
                                            <td data-column="full_name" class="fw-semibold">{{ $event->full_name }}</td>
                                            <td data-column="age">{{ $event->age ?? '-' }}</td>
                                            <td data-column="birth_date">
                                                {{ $event->birth_date ? $event->birth_date->format('M d, Y') : '-' }}</td>
                                            <td data-column="contact_no">{{ str_replace('-', '', $event->contact_no) }}
                                            </td>
                                            <td data-column="address">{{ $event->address ?? '-' }}</td>
                                            <td data-column="client_category" class="small">
                                                {{ $event->client_category ?? '-' }}</td>
                                            <td data-column="transaction_category" class="small">
                                                {{ $event->transaction_category ?? '-' }}</td>
                                            <td data-column="transaction_type" class="small">
                                                {{ $event->transaction_type ?? '-' }}</td>
                                            <td data-column="event_date" class="small">
                                                {{ optional($event->event_date)->format('M d, Y') ?? '-' }}</td>
                                            <td data-column="created_at" class="small">
                                                {{ optional($event->created_at)->format('M d, Y') }}</td>
                                            <td class="text-center">
                                                @if ($isTransferred)
                                                    <span class="badge bg-success-subtle text-success px-3 py-2">
                                                        <i class="ri-check-line me-1"></i>Approved
                                                    </span>
                                                @elseif (auth()->user()?->role_name === 'Viewer')
                                                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                                                        <i class="ri-time-line me-1"></i>Pending
                                                    </span>
                                                @else
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <form action="{{ route('transaction-events.transfer', $event) }}"
                                                            method="POST" class="transaction-transfer-form"
                                                            data-event-name="{{ $event->full_name }}">
                                                            @csrf
                                                            @if (feature_allowed('Transfer Event'))
                                                                <button type="submit" class="btn btn-sm btn-soft-success"
                                                                    {{ empty($event->transaction_category) && empty($event->transaction_type) ? 'disabled' : '' }}
                                                                    title="{{ empty($event->transaction_category) && empty($event->transaction_type) ? 'No transaction category or type to transfer' : 'Transfer to transaction' }}">
                                                                    <i class="ri-exchange-line"></i> Transfer
                                                                </button>
                                                            @else
                                                                <button type="button" class="btn btn-sm btn-soft-success"
                                                                    disabled
                                                                    title="You do not have permission to transfer this event.">
                                                                    <i class="ri-exchange-line"></i> Not Allowed to
                                                                    Transfer
                                                                </button>
                                                            @endif
                                                        </form>
                                                        @if (feature_allowed('Delete Event'))
                                                            <form
                                                                action="{{ route('transaction-events.delete', $event) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Delete this event ({{ $event->full_name }}) from the Import Events list? This cannot be undone.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                @if (feature_allowed('Delete Event'))
                                                                    <button type="submit"
                                                                        class="btn btn-sm btn-soft-danger"
                                                                        title="Delete Event">
                                                                        <i class="ri-delete-bin-line"></i> Delete
                                                                    </button>
                                                                @endif
                                                            </form>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center text-muted py-5">
                                                No transaction events found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            {{ $events->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Confirmation Modal -->
        <div class="modal fade" id="transferConfirmModal" tabindex="-1" aria-labelledby="transferConfirmModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="mb-3">
                            <i class="ri-exchange-line text-success" style="font-size: 3rem;"></i>
                        </div>
                        <p class="fs-5 fw-semibold mb-1">Confirm Transfer</p>
                        <p class="text-muted mb-0">
                            Create a transaction from this event for
                            <span class="fw-semibold" id="transferConfirmName">this client</span>?
                        </p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success px-4" id="confirmTransferBtn">
                            <i class="ri-check-line me-1"></i> Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Transfer Confirmation Modal -->
        <div class="modal fade" id="bulkTransferConfirmModal" tabindex="-1"
            aria-labelledby="bulkTransferConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="mb-3">
                            <i class="ri-exchange-line text-success" style="font-size: 3rem;"></i>
                        </div>
                        <p class="fs-5 fw-semibold mb-1" id="bulkTransferConfirmModalLabel">Confirm Transfer</p>
                        <p class="text-muted mb-0">
                            Create transactions from <span class="fw-semibold" id="bulkTransferCount">0</span>
                            selected event(s)?
                        </p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success px-4" id="confirmBulkTransferBtn">
                            <i class="ri-check-line me-1"></i> Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import form (hidden, used by both modals) -->
        <form id="importForm" action="{{ route('transaction-events.import') }}" method="POST"
            enctype="multipart/form-data" class="d-none">
            @csrf
            <input type="file" id="csv_file" name="csv_file" accept=".csv">
        </form>

        <!-- Import Modal (Step 1: Select File) -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Import Transaction Events</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csv_file_visible" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="csv_file_visible" accept=".csv" required>
                            <div id="csvFileError" class="invalid-feedback d-none"></div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <strong>CSV Format:</strong> The file should have the following columns (with header
                            row):<br>
                            <code>full_name, contact_no, address, age, birth_date, client_category, transaction_category,
                                transaction_type, event_date</code><br>
                            <a href="{{ route('transaction-events.template') }}" class="alert-link mt-1 d-inline-block">
                                <i class="ri-download-2-line me-1"></i>Download the Excel template
                            </a> to get started, then save as CSV.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="previewCsvBtn">
                            <i class="ri-eye-line me-1"></i> Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Modal (Step 2: Review & Confirm) -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="previewModalLabel">Review Import Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="previewLoading" class="text-center py-4">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <div>Parsing CSV file...</div>
                        </div>
                        <div id="previewContent" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="fw-semibold" id="previewTotalRows"></span> rows found
                                    (<span id="previewSkippedRows"></span> skipped)
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light" style="position: sticky; top: 0;">
                                        <tr>
                                            <th>#</th>
                                            <th>Full Name</th>
                                            <th>Status</th>
                                            <th>Age</th>
                                            <th>Birth Date</th>
                                            <th>Client Category</th>
                                            <th>Transaction Category</th>
                                            <th>Transaction Type</th>
                                            <th>Event Date</th>
                                            <th>Contact No.</th>
                                            <th>Address</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody"></tbody>
                                </table>
                            </div>
                            <div id="previewSkippedSection" class="d-none mt-3">
                                <h6 class="text-danger mb-2">Skipped Rows</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle mb-0">
                                        <thead class="table-danger">
                                            <tr>
                                                <th>CSV Line</th>
                                                <th>Reason</th>
                                                <th>Data</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previewSkippedBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div id="previewError" class="d-none">
                            <div class="alert alert-danger mb-0" id="previewErrorMessage"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmImportBtn">
                            <i class="ri-upload-2-line me-1"></i> Confirm Import
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Already-imported warning modal -->
        <div class="modal fade" id="importDuplicateModal" tabindex="-1" aria-labelledby="importDuplicateModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning-subtle">
                        <h5 class="modal-title" id="importDuplicateModalLabel">
                            <i class="ri-alert-fill text-warning me-1"></i> Data Already Imported
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="importDuplicateSummary"></p>
                        <p class="text-muted small mb-2">Matching rows found in Transaction History or Import Events:</p>
                        <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Event Date</th>
                                        <th>Transaction Category</th>
                                        <th>Transaction Type</th>
                                    </tr>
                                </thead>
                                <tbody id="importDuplicateBody"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">You can still continue, but importing again will create
                            duplicate records.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning px-4" id="importDuplicateContinueBtn">
                            <i class="ri-upload-2-line me-1"></i> Import Anyway
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Progress Modal (Step 3: Live progress bar) -->
        <div class="modal fade" id="importProgressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
            data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ri-loader-3-line ri-spin me-1"></i> Importing Transaction Events
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="progress" style="height: 22px;">
                            <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0"
                                aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span id="importProgressText" class="text-muted small">Preparing import...</span>
                            <span id="importProgressPercent" class="fw-semibold small">0%</span>
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
            // ----- Filter Records card toggle (Client List style) -----
            const eventFiltersToggleBtn = document.getElementById('eventFiltersToggleBtn');
            const eventFiltersForm = document.getElementById('eventFiltersForm');

            const setEventFiltersVisible = (visible) => {
                if (!eventFiltersForm || !eventFiltersToggleBtn) return;
                eventFiltersForm.classList.toggle('d-none', !visible);
                eventFiltersToggleBtn.innerHTML = visible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };

            eventFiltersToggleBtn?.addEventListener('click', function() {
                setEventFiltersVisible(eventFiltersForm.classList.contains('d-none'));
            });

            // ----- Per page selector -----
            document.getElementById('eventPerPageSelect')?.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });

            @if (request()->hasAny([
                    'search',
                    'contact',
                    'age_from',
                    'age_to',
                    'date_from',
                    'date_to',
                    'client_category',
                    'transaction_category',
                    'transaction_type',
                ]))
                setEventFiltersVisible(true);
            @endif

            // ----- Event List: Manage Columns -----
            const EV_COLUMNS_KEY = 'eventListHiddenColumns-{{ auth()->id() }}';
            const evTable = document.getElementById('eventListTable');
            const evToggles = Array.from(document.querySelectorAll('.event-list-column-toggle'));

            const evGetHidden = () => {
                try {
                    return JSON.parse(localStorage.getItem(EV_COLUMNS_KEY)) || [];
                } catch (e) {
                    return [];
                }
            };
            const evApply = () => {
                if (!evTable) return;
                const hidden = evGetHidden();
                evTable.querySelectorAll('[data-column]').forEach((cell) => {
                    cell.style.display = hidden.includes(cell.dataset.column) ? 'none' : '';
                });
            };
            const evSyncToggles = () => {
                const hidden = evGetHidden();
                evToggles.forEach((t) => {
                    t.checked = !hidden.includes(t.value);
                });
            };
            evToggles.forEach((toggle) => {
                toggle.addEventListener('change', function() {
                    if (!toggle.checked && evToggles.every((t) => !t.checked)) {
                        toggle.checked = true;
                    }
                });
            });
            document.getElementById('applyEventListColumnsBtn')?.addEventListener('click', function() {
                localStorage.setItem(EV_COLUMNS_KEY, JSON.stringify(evToggles.filter((t) => !t
                    .checked).map(
                    (t) => t.value)));
                evApply();
                bootstrap.Dropdown.getInstance(document.getElementById('eventListColumnsBtn'))?.hide();
            });
            document.getElementById('resetEventListColumnsBtn')?.addEventListener('click', function() {
                localStorage.removeItem(EV_COLUMNS_KEY);
                evSyncToggles();
                evApply();
            });
            evSyncToggles();
            evApply();

            const selectAll = document.getElementById('selectAllTransactionEvents');
            const eventCheckboxes = Array.from(document.querySelectorAll('.transaction-event-checkbox'));
            const transferConfirmModalEl = document.getElementById('transferConfirmModal');
            const transferConfirmName = document.getElementById('transferConfirmName');
            const confirmTransferBtn = document.getElementById('confirmTransferBtn');
            let selectedTransferForm = null;

            const bulkTransferBtn = document.getElementById('bulkTransferBtn');
            const bulkTransferForm = document.getElementById('bulkTransferForm');
            const bulkTransferConfirmModalEl = document.getElementById('bulkTransferConfirmModal');
            const bulkTransferCount = document.getElementById('bulkTransferCount');
            const confirmBulkTransferBtn = document.getElementById('confirmBulkTransferBtn');
            let selectedBulkTransferIds = [];
            const totalMatchingEvents = @json($events->total());
            let allPagesSelected = false;

            if (selectAll) {
                const selectableCheckboxes = () => eventCheckboxes.filter((checkbox) => !checkbox.dataset
                    .duplicate && !checkbox.disabled);

                const hasMorePages = @json($events->lastPage() > 1);

                const bar = document.getElementById('selectAllPagesBar');
                const barText = document.getElementById('selectAllPagesText');
                const clearBtn = document.getElementById('clearSelectionBtn');

                const hideBar = () => {
                    if (bar) {
                        bar.classList.add('d-none');
                        bar.classList.remove('d-flex');
                    }
                };

                const showBarAllSelected = () => {
                    if (!bar || !barText) {
                        return;
                    }
                    bar.classList.remove('d-none');
                    bar.classList.add('d-flex');
                    if (clearBtn) {
                        clearBtn.classList.remove('d-none');
                    }
                    barText.innerHTML = '<i class="ri-check-double-line me-1"></i><strong>All ' +
                        totalMatchingEvents + '</strong> matching events are selected (across all pages).';
                };

                const clearAllSelection = () => {
                    allPagesSelected = false;
                    eventCheckboxes.forEach((checkbox) => {
                        checkbox.checked = false;
                    });
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    hideBar();
                    syncSelectAllState();
                };

                if (clearBtn) {
                    clearBtn.addEventListener('click', clearAllSelection);
                }

                var syncSelectAllState = () => {
                    const selectable = selectableCheckboxes();
                    const checkedCount = selectable.filter((checkbox) => checkbox.checked).length;
                    selectAll.checked = allPagesSelected ||
                        (selectable.length > 0 && checkedCount === selectable.length);
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < selectable.length;
                    selectAll.disabled = selectable.length === 0;
                    if (bulkTransferBtn) {
                        // Any manual selection (including duplicate-named rows) enables the button.
                        const anyChecked = eventCheckboxes.some((checkbox) => checkbox.checked);
                        bulkTransferBtn.disabled = !allPagesSelected && !anyChecked;
                    }
                };

                selectAll.addEventListener('change', function() {
                    selectableCheckboxes().forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                    if (selectAll.checked && hasMorePages) {
                        // Selecting everything on this page means all pages:
                        // mark every matching event across all pages as selected.
                        allPagesSelected = true;
                        showBarAllSelected();
                    } else {
                        allPagesSelected = false;
                        hideBar();
                    }
                    syncSelectAllState();
                });

                eventCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', function() {
                        if (!checkbox.checked) {
                            allPagesSelected = false;
                            hideBar();
                        }
                        syncSelectAllState();
                    });
                });

                syncSelectAllState();
            }

            if (bulkTransferBtn && bulkTransferForm && bulkTransferConfirmModalEl && confirmBulkTransferBtn) {
                const bulkTransferConfirmModal = bootstrap.Modal.getOrCreateInstance(bulkTransferConfirmModalEl);

                bulkTransferBtn.addEventListener('click', function() {
                    selectedBulkTransferIds = eventCheckboxes
                        .filter((checkbox) => checkbox.checked)
                        .map((checkbox) => checkbox.value);

                    if (selectedBulkTransferIds.length === 0 && !allPagesSelected) {
                        return;
                    }

                    if (bulkTransferCount) {
                        bulkTransferCount.textContent = allPagesSelected ?
                            totalMatchingEvents :
                            selectedBulkTransferIds.length;
                    }

                    bulkTransferConfirmModal.show();
                });

                confirmBulkTransferBtn.addEventListener('click', function() {
                    confirmBulkTransferBtn.disabled = true;

                    // Reset previous payload.
                    bulkTransferForm.querySelectorAll(
                            'input[name="event_ids[]"], input[name="select_all"], input[data-list-filter], input[name="exclude_duplicates"]'
                            )
                        .forEach((input) => input.remove());

                    if (allPagesSelected) {
                        const allInput = document.createElement('input');
                        allInput.type = 'hidden';
                        allInput.name = 'select_all';
                        allInput.value = '1';
                        bulkTransferForm.appendChild(allInput);

                        // Always exclude duplicates when using select all
                        const excludeDupesInput = document.createElement('input');
                        excludeDupesInput.type = 'hidden';
                        excludeDupesInput.name = 'exclude_duplicates';
                        excludeDupesInput.value = '1';
                        bulkTransferForm.appendChild(excludeDupesInput);

                        // Carry over the active list filters so the backend
                        // targets exactly the rows shown across pages.
                        ['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to',
                            'duplicate_names', 'client_category', 'transaction_category', 'transaction_type'
                        ]
                        .forEach((name) => {
                            const value = new URLSearchParams(window.location.search).get(name);
                            if (value !== null && value !== '') {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = name;
                                input.setAttribute('data-list-filter', '1');
                                input.value = value;
                                bulkTransferForm.appendChild(input);
                            }
                        });
                    } else {
                        if (selectedBulkTransferIds.length === 0) {
                            confirmBulkTransferBtn.disabled = false;
                            return;
                        }
                        selectedBulkTransferIds.forEach((id) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'event_ids[]';
                            input.value = id;
                            bulkTransferForm.appendChild(input);
                        });
                    }

                    bulkTransferForm.submit();
                });

                bulkTransferConfirmModalEl.addEventListener('hidden.bs.modal', function() {
                    selectedBulkTransferIds = [];
                    confirmBulkTransferBtn.disabled = false;
                });
            }

            if (transferConfirmModalEl && confirmTransferBtn) {
                const transferConfirmModal = bootstrap.Modal.getOrCreateInstance(transferConfirmModalEl);

                document.querySelectorAll('.transaction-transfer-form').forEach((form) => {
                    form.addEventListener('submit', function(event) {
                        event.preventDefault();
                        selectedTransferForm = this;

                        if (transferConfirmName) {
                            transferConfirmName.textContent = this.dataset.eventName ||
                                'this client';
                        }

                        transferConfirmModal.show();
                    });
                });

                confirmTransferBtn.addEventListener('click', function() {
                    if (!selectedTransferForm) {
                        return;
                    }

                    confirmTransferBtn.disabled = true;
                    selectedTransferForm.submit();
                });

                transferConfirmModalEl.addEventListener('hidden.bs.modal', function() {
                    selectedTransferForm = null;
                    confirmTransferBtn.disabled = false;
                });
            }

            // CSV Import preview flow
            const importModalEl = document.getElementById('importModal');
            const previewModalEl = document.getElementById('previewModal');
            const csvFileVisible = document.getElementById('csv_file_visible');
            const csvFileHidden = document.getElementById('csv_file');
            const csvFileError = document.getElementById('csvFileError');
            const previewBtn = document.getElementById('previewCsvBtn');
            const confirmBtn = document.getElementById('confirmImportBtn');
            const importForm = document.getElementById('importForm');
            const previewLoading = document.getElementById('previewLoading');
            const previewContent = document.getElementById('previewContent');
            const previewError = document.getElementById('previewError');
            const previewErrorMessage = document.getElementById('previewErrorMessage');
            const previewTableBody = document.getElementById('previewTableBody');
            const previewTotalRows = document.getElementById('previewTotalRows');
            const previewSkippedRows = document.getElementById('previewSkippedRows');
            const previewSkippedSection = document.getElementById('previewSkippedSection');
            const previewSkippedBody = document.getElementById('previewSkippedBody');

            if (!importModalEl || !previewModalEl || !csvFileVisible || !previewBtn || !confirmBtn) {
                return;
            }

            const importModal = bootstrap.Modal.getOrCreateInstance(importModalEl);
            const previewModal = bootstrap.Modal.getOrCreateInstance(previewModalEl);

            // Sync visible file input to hidden one
            csvFileVisible.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const dt = new DataTransfer();
                    dt.items.add(this.files[0]);
                    csvFileHidden.files = dt.files;
                }
            });

            previewBtn.addEventListener('click', async function() {
                const file = csvFileVisible.files[0];
                if (!file) {
                    csvFileError.textContent = 'Please select a CSV file.';
                    csvFileError.classList.remove('d-none');
                    csvFileVisible.classList.add('is-invalid');
                    return;
                }

                csvFileError.classList.add('d-none');
                csvFileVisible.classList.remove('is-invalid');

                const formData = new FormData();
                formData.append('csv_file', file);

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                    'content') || '';

                importModal.hide();
                previewModal.show();
                previewLoading.classList.remove('d-none');
                previewContent.classList.add('d-none');
                previewError.classList.add('d-none');
                previewTableBody.innerHTML = '';

                try {
                    const response = await fetch('{{ route('transaction-events.preview') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to parse CSV file.');
                    }

                    previewTotalRows.textContent = data.total;
                    previewSkippedRows.textContent = data.skipped;

                    if (data.rows && data.rows.length > 0) {
                        data.rows.forEach(function(row, index) {
                            const tr = document.createElement('tr');
                            const statusBadge = row.duplicate ?
                                '<span class="badge bg-warning-subtle text-warning">Duplicate</span>' :
                                '<span class="badge bg-success-subtle text-success">New</span>';

                            tr.innerHTML = `
                                <td>${index + 1}</td>
                                <td class="fw-semibold">${escapeHtml(row.full_name)}</td>
                                <td>${statusBadge}</td>
                                <td>${row.age ?? '-'}</td>
                                <td>${escapeHtml(row.birth_date || '-')}</td>
                                <td>${escapeHtml(row.client_category || '-')}</td>
                                <td>${escapeHtml(row.transaction_category || '-')}</td>
                                <td>${escapeHtml(row.transaction_type || '-')}</td>
                                <td>${escapeHtml(row.event_date || '-')}</td>
                                <td>${escapeHtml(row.contact_no || '-')}</td>
                                <td>${escapeHtml(row.address || '-')}</td>
                            `;
                            previewTableBody.appendChild(tr);
                        });
                    } else {
                        previewTableBody.innerHTML =
                            '<tr><td colspan="11" class="text-center text-muted py-4">No valid rows found in the CSV file.</td></tr>';
                    }

                    if (data.skipped_rows && data.skipped_rows.length > 0) {
                        data.skipped_rows.forEach(function(row) {
                            const cellData = Object.entries(row.data || {}).map(function(kv) {
                                return kv[0] + ': ' + escapeHtml(String(kv[1] || ''));
                            }).join(', ');
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${row.line}</td>
                                <td>${escapeHtml(row.reason)}</td>
                                <td class="small">${cellData}</td>
                            `;
                            previewSkippedBody.appendChild(tr);
                        });
                        previewSkippedSection.classList.remove('d-none');
                    }

                    previewLoading.classList.add('d-none');
                    previewContent.classList.remove('d-none');

                } catch (error) {
                    previewLoading.classList.add('d-none');
                    previewError.classList.remove('d-none');
                    previewErrorMessage.textContent = error.message || 'An unexpected error occurred.';
                }
            });

            const runImport = async function(eventsOnly = false) {
                if (csvFileHidden.files.length === 0) {
                    return;
                }

                const file = csvFileHidden.files[0];
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                    'content') || '';
                const apiHeaders = {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                };

                const progressModalEl = document.getElementById('importProgressModal');
                const progressBar = document.getElementById('importProgressBar');
                const progressText = document.getElementById('importProgressText');
                const progressPercent = document.getElementById('importProgressPercent');

                const setProgress = function(percent, text) {
                    progressBar.style.width = percent + '%';
                    progressBar.setAttribute('aria-valuenow', percent);
                    progressPercent.textContent = Math.round(percent) + '%';
                    if (text) {
                        progressText.textContent = text;
                    }
                };

                if (!progressModalEl || !progressBar || !progressText || !progressPercent) {
                    importForm.submit();
                    return;
                }

                const progressModal = bootstrap.Modal.getOrCreateInstance(progressModalEl);

                confirmBtn.disabled = true;
                previewModal.hide();
                progressModal.show();
                setProgress(0, 'Preparing import...');

                try {
                    const prepareForm = new FormData();
                    prepareForm.append('csv_file', file);
                    prepareForm.append('events_only', eventsOnly ? '1' : '0');

                    const prepareRes = await fetch(
                        '{{ route('transaction-events.import.prepare') }}', {
                            method: 'POST',
                            headers: apiHeaders,
                            body: prepareForm,
                        });
                    const prepareData = await prepareRes.json();

                    if (!prepareRes.ok || !prepareData.success) {
                        throw new Error(prepareData.message || 'Failed to prepare the import.');
                    }

                    const total = prepareData.total;
                    if (total === 0) {
                        throw new Error('The CSV file has no rows to import.');
                    }

                    const CHUNK_SIZE = 200;
                    let offset = 0;

                    while (offset < total) {
                        const next = Math.min(offset + CHUNK_SIZE, total);
                        setProgress(
                            (offset / total) * 100,
                            'Importing rows ' + (offset + 1) + ' - ' + next + ' of ' + total
                        );

                        const chunkBody = new URLSearchParams();
                        chunkBody.append('token', prepareData.token);
                        chunkBody.append('offset', offset);
                        chunkBody.append('limit', CHUNK_SIZE);

                        const chunkRes = await fetch(
                            '{{ route('transaction-events.import.process') }}', {
                                method: 'POST',
                                headers: apiHeaders,
                                body: chunkBody,
                            });
                        const chunkData = await chunkRes.json();

                        if (!chunkRes.ok || !chunkData.success) {
                            throw new Error(chunkData.message ||
                                'Import failed while processing rows.');
                        }

                        offset = chunkData.processed;

                        if (chunkData.done) {
                            break;
                        }
                    }

                    setProgress(100, 'Finalizing import...');

                    const finishBody = new URLSearchParams();
                    finishBody.append('token', prepareData.token);

                    const finishRes = await fetch('{{ route('transaction-events.import.finish') }}', {
                        method: 'POST',
                        headers: apiHeaders,
                        body: finishBody,
                    });
                    const finishData = await finishRes.json();

                    if (!finishRes.ok || !finishData.success) {
                        throw new Error(finishData.message || 'Import failed to finalize.');
                    }

                    setProgress(100, 'Done!');
                    setTimeout(function() {
                        window.location.href = '{{ route('transaction-events.index') }}';
                    }, 500);
                } catch (error) {
                    progressModal.hide();
                    confirmBtn.disabled = false;
                    new Message('imessage').show(error.message || 'Import failed.', 'fail',
                        'top-center');
                }
            };

            // ----- Confirm Import: warn if data already exists in history -----
            confirmBtn.addEventListener('click', async function() {
                if (csvFileHidden.files.length === 0) {
                    return;
                }

                const file = csvFileHidden.files[0];
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                    'content') || '';

                confirmBtn.disabled = true;
                try {
                    const checkForm = new FormData();
                    checkForm.append('csv_file', file);

                    const res = await fetch(
                        '{{ route('transaction-events.import.check-duplicates') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                Accept: 'application/json',
                            },
                            body: checkForm,
                        });
                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Duplicate check failed.');
                    }

                    if ((data.duplicates_count ?? 0) > 0) {
                        const body = document.getElementById('importDuplicateBody');
                        body.innerHTML = '';
                        (data.duplicates || []).forEach(function(row) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="fw-semibold">${escapeHtml(row.full_name)}</td>
                                <td>${escapeHtml(row.event_date || '-')}</td>
                                <td>${escapeHtml(row.transaction_category || '-')}</td>
                                <td>${escapeHtml(row.transaction_type || '-')}</td>
                            `;
                            body.appendChild(tr);
                        });
                        document.getElementById('importDuplicateSummary').textContent =
                            `${data.duplicates_count} of ${data.total_rows} row(s) in this file already exist in the system (Transaction History or Import Events).`;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById(
                            'importDuplicateModal')).show();
                        confirmBtn.disabled = false;
                        return; // wait for user choice; "Import Anyway" calls runImport
                    }

                    await runImport();
                } catch (error) {
                    new Message('imessage').show(error.message || 'Duplicate check failed.', 'fail',
                        'top-center');
                    confirmBtn.disabled = false;
                }
            });

            document.getElementById('importDuplicateContinueBtn')?.addEventListener('click', function() {
                bootstrap.Modal.getInstance(document.getElementById('importDuplicateModal'))?.hide();
                runImport(true);
            });

            previewModalEl.addEventListener('hidden.bs.modal', function() {
                previewTableBody.innerHTML = '';
                previewLoading.classList.remove('d-none');
                previewContent.classList.add('d-none');
                previewError.classList.add('d-none');
            });

            function escapeHtml(str) {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
        });
    </script>
@endpush
