@extends('layouts.master')
@section('title', 'ERS | Transaction Events')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="mb-4">
                    <h4 class="mb-1 fw-semibold">Events</h4>
                    <p class="text-muted mb-0">Manage and import data events.</p>
                </div>
            </div>
        </div>

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
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Event List</h5>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('transaction-events.duplicate-review') }}"
                                class="btn btn-sm {{ $totalDuplicateGroups ? 'btn-warning' : 'btn-outline-warning' }}">
                                <i class="ri-file-copy-2-line me-1"></i> Duplicate Names
                                @if ($totalDuplicateGroups)
                                    <span class="badge bg-danger text-white ms-1">{{ $totalDuplicateGroups }}</span>
                                @endif
                            </a>
                            <div class="d-flex align-items-center gap-2">
                                @unless (auth()->user()?->role_name === 'Viewer')
                                    <button type="button" class="btn btn-success btn-sm" id="bulkTransferBtn" disabled>
                                        <i class="ri-exchange-box-line me-1"></i> Transfer Selected
                                    </button>
                                @endunless
                            </div>

                            <form id="bulkTransferForm" action="{{ route('transaction-events.transfer-selected') }}"
                                method="POST" class="d-none">
                                @csrf
                            </form>
                            <a href="{{ route('transaction-events.archives') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-archive-line me-1"></i> View Archives
                            </a>
                            @unless (auth()->user()?->role_name === 'Viewer')
                                <a href="{{ route('transaction-events.template') }}"
                                    class="btn btn-soft-primary btn-sm">
                                    <i class="ri-download-2-line me-1"></i> Excel Template
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#importModal">
                                    <i class="ri-upload-2-line me-1"></i> Import CSV
                                </button>
                            @endunless
                            <span class="badge bg-primary-subtle text-primary px-4 py-2">{{ $events->total() }} total</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" name="search"
                                    placeholder="Search name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" name="contact"
                                    placeholder="Search contact..." value="{{ request('contact') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control form-control-sm" name="age_from"
                                    placeholder="Age from" value="{{ request('age_from') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control form-control-sm" name="age_to"
                                    placeholder="Age to" value="{{ request('age_to') }}">
                            </div>
                            <div class="col-md-1">
                                <input type="date" class="form-control form-control-sm" name="date_from"
                                    value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-1">
                                <input type="date" class="form-control form-control-sm" name="date_to"
                                    value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-1">
                                <select name="per_page" class="form-select form-select-sm"
                                    onchange="this.form.submit()" aria-label="Records per page">
                                    @foreach ([15, 25, 50, 100] as $size)
                                        <option value="{{ $size }}" {{ request('per_page', 15) == $size ? 'selected' : '' }}>
                                            {{ $size }} / page
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                    <i class="ri-search-line"></i>
                                </button>
                                @if (request()->hasAny(['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to', 'duplicate_names']))
                                    <a href="{{ route('transaction-events.index') }}"
                                        class="btn btn-light btn-sm flex-fill">
                                        <i class="ri-close-line"></i>
                                    </a>
                                @endif
                            </div>
                        </form>

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
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 120px;" class="text-center">
                                            <div
                                                class="d-inline-flex align-items-center justify-content-center gap-2 text-nowrap">
                                                @unless (auth()->user()?->role_name === 'Viewer')
                                                    <input type="checkbox" class="form-check-input"
                                                        id="selectAllTransactionEvents"
                                                        aria-label="Select all transaction events on this page">
                                                    <span>Select all</span>
                                                @endunless
                                            </div>
                                        </th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Birth Date</th>
                                        <th>Contact No.</th>
                                        <th>Address</th>
                                        <th>Client Category</th>
                                        <th>Transaction Category</th>
                                        <th>Transaction Type</th>
                                        <th style="width: 100px;">Date</th>
                                        <th style="width: 100px; text-align: center;">Action</th>
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
                                            <td class="fw-semibold">{{ $event->full_name }}</td>
                                            <td>{{ $event->age ?? '-' }}</td>
                                            <td>{{ $event->birth_date ? $event->birth_date->format('M d, Y') : '-' }}</td>
                                            <td>{{ str_replace('-', '', $event->contact_no) }}</td>
                                            <td>{{ $event->address ?? '-' }}</td>
                                            <td class="small">{{ $event->client_category ?? '-' }}</td>
                                            <td class="small">{{ $event->transaction_category ?? '-' }}</td>
                                            <td class="small">{{ $event->transaction_type ?? '-' }}</td>
                                            <td class="small">{{ $event->created_at?->format('M d, Y') }}</td>
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
                                                    <form action="{{ route('transaction-events.transfer', $event) }}"
                                                        method="POST" class="transaction-transfer-form"
                                                        data-event-name="{{ $event->full_name }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-success"
                                                            {{ empty($event->transaction_category) && empty($event->transaction_type) ? 'disabled' : '' }}
                                                            title="{{ empty($event->transaction_category) && empty($event->transaction_type) ? 'No transaction category or type to transfer' : 'Transfer to transaction' }}">
                                                            <i class="ri-exchange-line"></i> Transfer
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-5">
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
                                transaction_type</code><br>
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

        <!-- Import Progress Modal (Step 3: Live progress bar) -->
        <div class="modal fade" id="importProgressModal" tabindex="-1" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
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
                                role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
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
                const selectableCheckboxes = () => eventCheckboxes.filter((checkbox) => !checkbox.dataset.duplicate && !checkbox.disabled);

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

                const showBarPrompt = () => {
                    if (!bar || !barText) {
                        return;
                    }
                    bar.classList.remove('d-none');
                    bar.classList.add('d-flex');
                    if (clearBtn) {
                        clearBtn.classList.add('d-none');
                    }
                    const checkedCount = selectableCheckboxes().filter((checkbox) => checkbox.checked).length;
                    barText.innerHTML = checkedCount + ' selected on this page. <a href="#" id="selectAllPagesLink">Click here</a> to select all ' +
                        totalMatchingEvents + ' matching events across all pages.';
                    const link = document.getElementById('selectAllPagesLink');
                    if (link) {
                        link.addEventListener('click', function(event) {
                            event.preventDefault();
                            allPagesSelected = true;
                            selectableCheckboxes().forEach((checkbox) => {
                                checkbox.checked = true;
                            });
                            syncSelectAllState();
                        });
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
                    selectAll.checked = !allPagesSelected && selectable.length > 0 && checkedCount === selectable.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < selectable.length;
                    selectAll.disabled = selectable.length === 0;
                    if (bulkTransferBtn) {
                        // Any manual selection (including duplicate-named rows) enables the button.
                        const anyChecked = eventCheckboxes.some((checkbox) => checkbox.checked);
                        bulkTransferBtn.disabled = !allPagesSelected && !anyChecked;
                    }
                };

                selectAll.addEventListener('change', function() {
                    allPagesSelected = false;
                    selectableCheckboxes().forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                    if (selectAll.checked && hasMorePages) {
                        showBarPrompt();
                    } else {
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
                        bulkTransferCount.textContent = allPagesSelected
                            ? totalMatchingEvents
                            : selectedBulkTransferIds.length;
                    }

                    bulkTransferConfirmModal.show();
                });

                confirmBulkTransferBtn.addEventListener('click', function() {
                    confirmBulkTransferBtn.disabled = true;

                    // Reset previous payload.
                    bulkTransferForm.querySelectorAll('input[name="event_ids[]"], input[name="select_all"], input[data-list-filter]')
                        .forEach((input) => input.remove());

                    if (allPagesSelected) {
                        const allInput = document.createElement('input');
                        allInput.type = 'hidden';
                        allInput.name = 'select_all';
                        allInput.value = '1';
                        bulkTransferForm.appendChild(allInput);

                        // Carry over the active list filters so the backend
                        // targets exactly the rows shown across pages.
                        ['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to', 'duplicate_names']
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
                                <td>${escapeHtml(row.contact_no || '-')}</td>
                                <td>${escapeHtml(row.address || '-')}</td>
                            `;
                            previewTableBody.appendChild(tr);
                        });
                    } else {
                        previewTableBody.innerHTML =
                            '<tr><td colspan="9" class="text-center text-muted py-4">No valid rows found in the CSV file.</td></tr>';
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

            confirmBtn.addEventListener('click', async function() {
                if (csvFileHidden.files.length === 0) {
                    return;
                }

                const file = csvFileHidden.files[0];
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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

                    const prepareRes = await fetch('{{ route('transaction-events.import.prepare') }}', {
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

                        const chunkRes = await fetch('{{ route('transaction-events.import.process') }}', {
                            method: 'POST',
                            headers: apiHeaders,
                            body: chunkBody,
                        });
                        const chunkData = await chunkRes.json();

                        if (!chunkRes.ok || !chunkData.success) {
                            throw new Error(chunkData.message || 'Import failed while processing rows.');
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
                    new Message('imessage').show(error.message || 'Import failed.', 'fail', 'top-center');
                }
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
