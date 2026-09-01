@extends('layouts.master')
@section('title', 'ERS | Events - Records')

@section('content')
    <style>
        #recordFiltersCard {
            background: #ffffff;
            border: 1px solid #e3e8ef;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }

        #recordFiltersCard .form-label,
        #recordFiltersCard .small,
        #recordFiltersCard .fw-bold,
        #recordFiltersCard .fw-semibold {
            color: #1f2937 !important;
        }

        #recordFiltersCard .input-group-text,
        #recordFiltersCard .form-control,
        #recordFiltersCard .form-select {
            background-color: #f8fafc;
            color: #111827;
            border-color: #d5dbe3;
        }

        #recordFiltersCard .input-group-text {
            color: #475569;
        }

        #recordFiltersCard .form-control::placeholder {
            color: #94a3b8;
        }

        #recordFiltersCard .form-control:focus,
        #recordFiltersCard .form-select:focus {
            border-color: #4d63d6;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.14);
        }

        #recordFiltersCard .client-filters-toggle-btn {
            transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        #recordFiltersCard .client-filters-toggle-btn:hover,
        #recordFiltersCard .client-filters-toggle-btn:focus,
        #recordFiltersCard .client-filters-toggle-btn:active {
            background: #eef2ff;
            color: #2f49c5;
            border-color: #6276df;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.12);
        }

        #recordFiltersCard .btn-primary {
            background: linear-gradient(135deg, #4d63d6, #5a73ff);
            border-color: transparent;
        }
    </style>
    @php
        $activeRecordFilters = request()->hasAny([
            'search',
            'contact',
            'age_from',
            'age_to',
            'date_from',
            'date_to',
            'transaction_category',
            'transaction_type',
        ]);
    @endphp
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1 fw-semibold">Event Records</h4>
                <p class="text-muted mb-0">List of transaction events that have been transferred to records.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary px-4 py-2">{{ $events->total() }} total</span>
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="border rounded-4 p-3 mb-3" id="recordFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                                <div>
                                    <div class="fw-bold fs-5">Filter Records</div>
                                    <div class="text-muted small">Narrow transferred records by keyword, contact, age,
                                        and transferred date range.</div>

                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary client-filters-toggle-btn"
                                        id="recordFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    @if ($activeRecordFilters)
                                        <a href="{{ route('transaction-events.records') }}"
                                            class="btn btn-soft-secondary">Reset</a>
                                    @endif
                                    @if (feature_allowed('Events Records Duplicates'))
                                        <a href="{{ route('transaction-events.records-duplicates') }}"
                                            class="btn btn-sm btn-outline-warning">
                                            <i class="ri-file-copy-2-line me-1"></i>View Duplicate Records
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-warning" disabled
                                            title="Feature not allowed">
                                            <i class="ri-file-copy-2-line me-1"></i>Not Allowed to View Duplicate Records
                                        </button>
                                    @endif

                                    <select class="form-select form-select-sm w-auto" id="recordPerPageSelect"
                                        aria-label="Records per page" title="Records per page">
                                        @foreach ([10, 15, 25, 50, 100] as $size)
                                            <option value="{{ $size }}"
                                                {{ request('per_page', 15) == $size ? 'selected' : '' }}>
                                                {{ $size }} / page
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-soft-primary btn-sm text-nowrap"
                                            id="recordColumnsBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                            aria-expanded="false" title="Manage Columns">
                                            <i class="ri-layout-column-line me-1"></i> Manage Columns
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 230px;">
                                            <h6 class="dropdown-header px-0">Manage Columns</h6>
                                            @foreach ([
            'id' => 'ID',
            'transaction_id' => 'Transaction ID',
            'full_name' => 'Full Name',
            'age' => 'Age',
            'birth_date' => 'Birth Date',
            'contact' => 'Contact No.',
            'address' => 'Address',
            'client_category' => 'Category',
            'transaction_category' => 'Transaction Category',
            'transaction_type' => 'Transaction Type',
            'event_date' => 'Event Date',
            'transferred_at' => 'Transferred At',
            'status' => 'Status',
        ] as $key => $label)
                                                <div class="form-check">
                                                    <input class="form-check-input column-toggle" type="checkbox"
                                                        id="col-{{ $key }}" value="{{ $key }}" checked>
                                                    <label class="form-check-label"
                                                        for="col-{{ $key }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                            <hr class="dropdown-divider my-2">
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-light flex-fill"
                                                    id="resetColumnsBtn">Reset</button>
                                                <button type="button" class="btn btn-primary btn-sm flex-fill"
                                                    id="applyRecordColumnsBtn">Apply</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="GET" action="{{ route('transaction-events.records') }}" id="recordFiltersForm"
                                class="{{ $activeRecordFilters ? '' : 'd-none' }}">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="recordKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="recordKeywordInput"
                                                name="search" placeholder="Full name" value="{{ request('search') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-2 col-xl-2">
                                        <label for="recordContactFilter"
                                            class="form-label fw-semibold text-uppercase small">Contact</label>
                                        <input type="text" class="form-control" id="recordContactFilter" name="contact"
                                            placeholder="Contact no." value="{{ request('contact') }}">
                                    </div>
                                    <div class="col-6 col-md-6 col-xl-2">
                                        <label for="recordAgeFrom" class="form-label fw-semibold text-uppercase small">Age
                                            From</label>
                                        <input type="number" min="0" max="120" class="form-control"
                                            id="recordAgeFrom" name="age_from" placeholder="From"
                                            value="{{ request('age_from') }}">
                                    </div>
                                    <div class="col-6 col-md-6 col-xl-2">
                                        <label for="recordAgeTo" class="form-label fw-semibold text-uppercase small">Age
                                            To</label>
                                        <input type="number" min="0" max="120" class="form-control"
                                            id="recordAgeTo" name="age_to" placeholder="To"
                                            value="{{ request('age_to') }}">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="recordCategoryFilter"
                                            class="form-label fw-semibold text-uppercase small">Transaction
                                            Category</label>
                                        <select class="form-select" id="recordCategoryFilter"
                                            name="transaction_category">
                                            <option value="">All categories</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category }}"
                                                    {{ request('transaction_category') === $category ? 'selected' : '' }}>
                                                    {{ $category }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="recordTypeFilter"
                                            class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <select class="form-select" id="recordTypeFilter" name="transaction_type">
                                            <option value="">All types</option>
                                            @foreach ($types as $type)
                                                <option value="{{ $type }}"
                                                    {{ request('transaction_type') === $type ? 'selected' : '' }}>
                                                    {{ $type }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label for="recordDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Transferred From</label>
                                        <input type="date" class="form-control" id="recordDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label for="recordDateTo"
                                            class="form-label fw-semibold text-uppercase small">Transferred To</label>
                                        <input type="date" class="form-control" id="recordDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                    <div class="col-12 col-xl-2 d-flex gap-2 justify-content-xl-end">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-2" id="recordSearchSummary">
                                    {{ $activeRecordFilters ? 'Filtered records are shown below.' : 'Showing all records.' }}
                                </div>
                            </form>
                        </div>

                        {{-- <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge rounded-pill px-3 py-2" id="recordFiltersCountBadge">
                                Showing {{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} of
                                {{ $events->total() }} record(s)
                            </span>
                        </div> --}}

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0" id="eventRecordsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th data-column="id">ID</th>
                                        <th data-column="transaction_id">Transaction ID</th>
                                        <th data-column="full_name">Full Name</th>
                                        <th data-column="age">Age</th>
                                        <th data-column="birth_date">Birth Date</th>
                                        <th data-column="contact">Contact No.</th>
                                        <th data-column="address">Address</th>
                                        <th data-column="client_category">Category</th>
                                        <th data-column="transaction_category">Transaction Category</th>
                                        <th data-column="transaction_type">Transaction Type</th>
                                        <th data-column="event_date">Event Date</th>
                                        <th style="width: 160px;" data-column="transferred_at">Transferred At</th>
                                        <th style="width: 120px;" class="text-center" data-column="status">Status</th>
                                        @if (auth()->user()?->role_name !== 'Viewer')
                                            <th style="width: 140px;" class="text-center">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($events as $event)
                                        <tr>
                                            <td data-column="id">{{ $event->id }}</td>
                                            <td data-column="transaction_id" class="fw-semibold" style="width: 150px">
                                                {{ $event->transferredTransaction?->transaction_id ?? '-' }}</td>
                                            <td data-column="full_name" class="fw-semibold">{{ $event->full_name }}</td>
                                            <td data-column="age">{{ $event->age ?? '-' }}</td>
                                            <td data-column="birth_date">
                                                {{ optional($event->birth_date)->format('M d, Y') ?? '-' }}</td>
                                            <td data-column="contact">
                                                {{ str_replace('-', '', $event->contact_no ?? '') ?: '-' }}</td>
                                            <td data-column="address" class="small">{{ $event->address ?? '-' }}</td>
                                            <td data-column="client_category" class="small">
                                                {{ $event->client_category ?? '-' }}</td>
                                            <td data-column="transaction_category" class="small">
                                                {{ $event->transaction_category ?? '-' }}</td>
                                            <td data-column="transaction_type" class="small">
                                                {{ $event->transaction_type ?? '-' }}</td>
                                            <td data-column="event_date" class="small">
                                                {{ optional($event->event_date)->format('M d, Y') ?? '-' }}</td>
                                            <td data-column="transferred_at">
                                                {{ optional($event->transferred_at)->timezone('Asia/Manila')->format('M d, Y H:i:s') }}
                                            </td>
                                            <td data-column="status" class="text-center">
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <i class="ri-check-line me-1"></i>Approved
                                                </span>
                                            </td>
                                            @if (auth()->user()?->role_name !== 'Viewer')
                                                <td class="text-center" style="width: 50px">
                                                    <form action="{{ route('transaction-events.undo-transfer', $event) }}"
                                                        method="POST" class="m-0">
                                                        @csrf
                                                        @if (feature_allowed('Undo Transfer'))
                                                            <button type="submit" class="btn btn-sm btn-soft-warning"
                                                                onclick="return confirm('Undo this transfer? The created transaction record will be removed and this event will return to pending. The client record will remain.');">
                                                                <i class="ri-arrow-go-back-line me-1"></i> Undo Transfer
                                                            </button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-soft-warning"
                                                                disabled title="Feature not allowed">
                                                                <i class="ri-arrow-go-back-line me-1"></i>Not Allowed to
                                                                Undo Transfer
                                                            </button>
                                                        @endif
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->user()?->role_name !== 'Viewer' ? 14 : 13 }}"
                                                class="text-center text-muted py-5">
                                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                No transferred event records found.
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
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ----- Filter Records card toggle (Client List style) -----
            const filtersToggleBtn = document.getElementById('recordFiltersToggleBtn');
            const filtersForm = document.getElementById('recordFiltersForm');

            const setFiltersVisible = (visible) => {
                if (!filtersForm || !filtersToggleBtn) return;
                filtersForm.classList.toggle('d-none', !visible);
                filtersToggleBtn.innerHTML = visible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };

            filtersToggleBtn?.addEventListener('click', function() {
                setFiltersVisible(filtersForm.classList.contains('d-none'));
            });

            // ----- Per page selector -----
            document.getElementById('recordPerPageSelect')?.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });

            // Auto-expand when filters are active.
            @if ($activeRecordFilters)
                setFiltersVisible(true);
            @endif

            const STORAGE_KEY = 'eventRecordsHiddenColumns-{{ auth()->id() }}';
            const table = document.getElementById('eventRecordsTable');
            const toggles = Array.from(document.querySelectorAll('.column-toggle'));
            const resetBtn = document.getElementById('resetColumnsBtn');

            const getHidden = () => {
                try {
                    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
                } catch (e) {
                    return [];
                }
            };

            const applyColumns = () => {
                const hidden = getHidden();
                if (!table) return;
                table.querySelectorAll('[data-column]').forEach((cell) => {
                    cell.style.display = hidden.includes(cell.dataset.column) ? 'none' : '';
                });
            };

            const syncToggles = () => {
                const hidden = getHidden();
                toggles.forEach((toggle) => {
                    toggle.checked = !hidden.includes(toggle.value);
                });
            };

            // selection is committed only on Apply; prevent hiding every column
            toggles.forEach((toggle) => {
                toggle.addEventListener('change', function() {
                    if (!toggle.checked && toggles.every((t) => !t.checked)) {
                        toggle.checked = true;
                    }
                });
            });

            document.getElementById('applyRecordColumnsBtn')?.addEventListener('click', function() {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(toggles.filter((t) => !t.checked).map((
                    t) => t.value)));
                applyColumns();
                bootstrap.Dropdown.getInstance(document.getElementById('recordColumnsBtn'))?.hide();
            });

            resetBtn?.addEventListener('click', function() {
                localStorage.removeItem(STORAGE_KEY);
                syncToggles();
                applyColumns();
            });

            syncToggles();
            applyColumns();
        });
    </script>
@endpush
