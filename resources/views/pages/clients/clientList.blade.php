@extends('layouts.master')
@section('title', 'ERS | Client List')
@section('content')
    
    @php
        $defaultClientPhoto = asset('assets/images/profile.png');
    @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                            <div>
                                <h4 class="mb-1">Client List</h4>
                                <p class="text-muted mb-0">
                                    {{ $matchedClientId ? 'Showing the matched client only.' : (!empty($groupClientIds ?? []) ? 'Showing clients from the selected duplicate group.' : 'View all registered clients here.') }}
                                </p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @if ($matchedClientId || !empty($groupClientIds ?? []))
                                    <a href="{{ route('client.list') }}" class="btn btn-sm btn-soft-secondary">Show All
                                        Clients</a>
                                @endif
                                <button type="button" class="btn btn-sm btn-soft-primary" id="searchFingerprintBtn">Search
                                    by
                                    Fingerprint</button>
                                @unless (auth()->user()?->role_name === 'Viewer')
                                    <a href="{{ route('clients') }}" class="btn btn-sm btn-primary">Add Client</a>
                                    @if (feature_allowed('Delete Clients Without Transactions'))
                                        <button type="button" class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteClientsWithoutTransactionsModal">
                                            Delete Clients Without Transactions
                                        </button>
                                    @endif
                                @endunless
                            </div>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <div class="fw-semibold mb-1">Please fix the highlighted issue(s) below.</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        @endif

                        @if ($matchedClientId)
                            <div
                                class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div>Fingerprint search matched one client and the list is filtered to that result.</div>
                                <a href="{{ route('client.list') }}" class="btn btn-sm btn-outline-success">Clear Filter</a>
                            </div>
                        @endif

                        @if (!empty($groupClientIds ?? []))
                            <div
                                class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div>Viewing {{ count($groupClientIds) }} client(s) from the selected duplicate group.</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('duplicate.review') }}" class="btn btn-sm btn-outline-warning">Back
                                        to Duplicate Review</a>
                                </div>
                            </div>
                        @endif

                        {{-- @if (request()->boolean('duplicate_names'))
                            <div class="alert alert-warning py-2 mb-3">
                                Showing clients with duplicate identity (first + middle + last name + birth date).
                            </div>
                        @endif --}}

                        <div class="border rounded-4 p-3 mb-3" id="clientFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-0">
                                <div>
                                    <div class="fw-bold fs-5">Filter Clients</div>
                                    <div class="text-muted small">Narrow records by keyword, sex, civil status, location,
                                        and created date range. Search covers all pages.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-primary client-filters-toggle-btn"
                                        id="clientFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="{{ route('client.list') }}" class="btn btn-sm btn-soft-primary"
                                        id="clientFiltersResetBtn">Reset</a>
                                    <select class="form-select form-select-sm w-auto" id="clientPerPageSelect"
                                        aria-label="Clients per page" title="Clients per page">
                                        @foreach ([10, 15, 25, 50, 100] as $size)
                                            <option value="{{ $size }}"
                                                {{ request('per_page', 10) == $size ? 'selected' : '' }}>
                                                {{ $size }} / page
                                            </option>
                                        @endforeach
                                    </select>
                                    {{-- <a href="{{ route('client.list', array_merge(request()->except('page'), ['duplicate_names' => 1])) }}"
                                        class="btn btn-sm {{ request()->boolean('duplicate_names') ? 'btn-warning' : 'btn-outline-warning' }}" style="font-size: 13px; padding: 7px;">
                                        <i class="ri-file-copy-2-line"></i> Duplicate Names
                                    </a> --}}
                                    {{-- <span class="badge rounded-pill px-3 py-2" id="clientFiltersCountBadge">{{ $clients->total() }} clients</span> --}}
                                </div>
                            </div>

                            <form method="GET" action="{{ route('client.list') }}" id="clientFiltersForm"
                                class="{{ request()->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to']) ? '' : 'd-none' }}">
                                @if ($matchedClientId)
                                    <input type="hidden" name="matched_client" value="{{ $matchedClientId }}">
                                @endif
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="clientKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="clientKeywordInput"
                                                name="search" placeholder="Name or Client ID" value="{{ request('search') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientSexFilter"
                                            class="form-label fw-semibold text-uppercase small">Gender</label>
                                        <select class="form-select" id="clientSexFilter" name="gender">
                                            <option value="">All Gender</option>
                                            <option value="male" {{ request('gender') === 'male' ? 'selected' : '' }}>
                                                Male</option>
                                            <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>
                                                Female</option>
                                            <option value="other" {{ request('gender') === 'other' ? 'selected' : '' }}>
                                                Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientCivilStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Civil Status</label>
                                        <select class="form-select" id="clientCivilStatusFilter" name="civil_status">
                                            <option value="">All civil statuses</option>
                                            @foreach ($clientCivilStatuses as $civilStatus)
                                                <option value="{{ strtolower($civilStatus) }}"
                                                    {{ strtolower(request('civil_status', '')) === strtolower($civilStatus) ? 'selected' : '' }}>
                                                    {{ $civilStatus }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientCityFilter"
                                            class="form-label fw-semibold text-uppercase small">City</label>
                                        <select class="form-select" id="clientCityFilter" name="city">
                                            <option value="">All cities</option>
                                            @foreach ($clientCities as $city)
                                                <option value="{{ strtolower($city) }}"
                                                    {{ strtolower(request('city', '')) === strtolower($city) ? 'selected' : '' }}>
                                                    {{ $city }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientBarangayFilter"
                                            class="form-label fw-semibold text-uppercase small">Barangay</label>
                                        <select class="form-select" id="clientBarangayFilter" name="barangay">
                                            <option value="">All barangays</option>
                                            @foreach ($clientBarangays as $barangay)
                                                <option value="{{ strtolower($barangay) }}"
                                                    {{ strtolower(request('barangay', '')) === strtolower($barangay) ? 'selected' : '' }}>
                                                    {{ $barangay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 col-lg-3">
                                        <label for="clientRecordTypeFilter"
                                            class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <select class="form-select" id="clientRecordTypeFilter">
                                            <option value="all">All records</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <label for="clientDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="clientDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <label for="clientDateTo" class="form-label fw-semibold text-uppercase small">Date
                                            To</label>
                                        <input type="date" class="form-control" id="clientDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                    <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4" id="clientDateApplyBtn">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3" id="clientSearchSummary">
                                    {{ request()->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to']) ? 'Filtered clients are shown below.' : 'Showing all clients.' }}
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table id="clientListTable" class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        @php
                                            $currentSort = $sort ?? request('sort', 'name_asc');
                                        @endphp
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Client ID', 'asc' => 'clientid_asc', 'desc' => 'clientid_desc', 'current' => $currentSort, 'center' => true])
                                        <th>Photo</th>
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Full Name', 'asc' => 'name_asc', 'desc' => 'name_desc', 'current' => $currentSort, 'center' => true])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Sex', 'asc' => 'gender_asc', 'desc' => 'gender_desc', 'current' => $currentSort, 'center' => true])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Age', 'asc' => 'age_asc', 'desc' => 'age_desc', 'current' => $currentSort, 'center' => true])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Contact', 'asc' => 'contact_asc', 'desc' => 'contact_desc', 'current' => $currentSort, 'center' => true])
                                        @include('pages.transaction_events.partials.sortableHeader', ['label' => 'Address', 'asc' => 'address_asc', 'desc' => 'address_desc', 'current' => $currentSort, 'center' => true])
                                        <th style="width:190px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center text-uppercase">
                                    @forelse ($clients as $client)
                                        @php
                                            $clientName = $client->latestLinkedEvent?->display_name
                                                ?: $client->full_name;
                                            $clientPhoto = $client->photo_url ?: $defaultClientPhoto;
                                        @endphp
                                        <tr data-client-row="{{ $client->id }}"
                                            data-search-name="{{ strtolower($clientName) }}"
                                            data-search-email="{{ strtolower($client->email ?? '') }}"
                                            data-search-contact="{{ strtolower($client->contact ?? '') }}"
                                            data-search-contact-2="{{ strtolower($client->contact_2 ?? '') }}"
                                            data-search-address="{{ strtolower($client->address ?? '') }}"
                                            data-search-birthplace="{{ strtolower($client->birthplace ?? '') }}"
                                            data-search-education="{{ strtolower($client->education ?? '') }}"
                                            data-search-course="{{ strtolower($client->course ?? '') }}"
                                            data-search-sector="{{ strtolower($client->sector ?? '') }}"
                                            data-search-position-organization="{{ strtolower($client->position_organization ?? '') }}"
                                            data-search-gender="{{ strtolower($client->gender ?? '') }}"
                                            data-search-civil-status="{{ strtolower($client->civil_status ?? '') }}"
                                            data-search-province="{{ strtolower($client->province ?? '') }}"
                                            data-search-city="{{ strtolower($client->city ?? '') }}"
                                            data-search-barangay="{{ strtolower($client->barangay ?? '') }}"
                                            data-search-client-id="{{ strtolower($client->client_id ?? '') }}"
                                            data-search-created-at="{{ optional($client->created_at)->format('Y-m-d') }}"
                                            data-search-all="{{ strtolower(($client->client_id ?? '') . ' ' . $clientName . ' ' . ($client->suffix ?? '') . ' ' . ($client->email ?? '') . ' ' . ($client->contact ?? '') . ' ' . ($client->contact_2 ?? '') . ' ' . ($client->gender ?? '') . ' ' . ($client->civil_status ?? '') . ' ' . ($client->birthplace ?? '') . ' ' . ($client->education ?? '') . ' ' . ($client->course ?? '') . ' ' . ($client->sector ?? '') . ' ' . ($client->position_organization ?? '') . ' ' . ($client->address ?? '') . ' ' . ($client->province ?? '') . ' ' . ($client->city ?? '') . ' ' . ($client->barangay ?? '')) }}">
                                            <td>{{ $client->client_id ?? '-' }}</td>
                                            <td>
                                                <button type="button" class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal" data-bs-target="#clientPhotoModal"
                                                    data-client-photo="{{ $clientPhoto }}"
                                                    data-client-name="{{ $clientName }}">
                                                    <img src="{{ $clientPhoto }}" alt="Client Photo"
                                                        onerror="this.onerror=null;this.src='{{ $defaultClientPhoto }}';"
                                                        class="rounded-3 border object-fit-cover"
                                                        style="width: 50px; height: 50px;">
                                                </button>
                                            </td>
                                            <td>
                                                {{ $clientName }}
                                            </td>
                                            <td>{{ $client->gender ?? '-' }}</td>
                                            <td>{{ $client->age ?? '-' }}</td>
                                            <td>{{ $client->contact ?? '-' }}</td>
                                            <td class="text-start">
                                                <div class="small lh-sm">
                                                    <div>{{ $client->address ?? '-' }}</div>
                                                    <div class="text-muted">
                                                        {{ collect([$client->barangay, $client->city, $client->province])->filter()->implode(', ') ?:'-' }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 text-center justify-content-center">
                                                    @if (feature_allowed('View Client'))
                                                        <a href="{{ route('clients.show', $client) }}"
                                                            class="btn btn-sm btn-soft-info">
                                                            View
                                                        </a>
                                                    @endif
                                                    @unless (auth()->user()?->role_name === 'Viewer')
                                                        @if (feature_allowed('Edit Client'))
                                                            <a href="{{ route('clients.edit', $client) }}"
                                                                class="btn btn-sm btn-soft-primary">
                                                                Edit
                                                            </a>
                                                        @endif
                                                        <form action="{{ route('clients.archive', $client) }}" method="POST"
                                                            onsubmit="return confirm('Are you sure you want to archive this client?');">
                                                            @csrf
                                                            @if (feature_allowed('Archive Clients'))
                                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                                    ARCHIVE
                                                                </button>
                                                            @endif
                                                        </form>
                                                    @endunless
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="clientSearchEmptyRow">
                                            <td colspan="8" class="text-center text-muted py-4">
                                                {{ $matchedClientId ? 'No matching client found.' : 'No clients found.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    @if ($clients->count())
                                        <tr id="clientSearchNoResultsRow" class="d-none">
                                            <td colspan="8" class="text-center text-muted py-4">
                                                No matching clients found.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            {{ $clients->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @unless (auth()->user()?->role_name === 'Viewer')
        @if (feature_allowed('Archive Clients'))
            <div class="modal fade" id="deleteClientsWithoutTransactionsModal" tabindex="-1"
                aria-labelledby="deleteClientsWithoutTransactionsModalLabel" aria-hidden="true"
                data-preview-url="{{ route('client.list.preview-without-transactions') }}"
                data-progress-url="{{ route('client.list.delete-progress', ['operationId' => '__operation__']) }}">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form action="{{ route('client.list.destroy-without-transactions') }}" method="POST"
                        class="modal-content">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="select_all" id="deleteClientsSelectAllValue" value="0">
                        <input type="hidden" name="selected_ids" id="deleteClientsSelectedIds" value="[]">
                        <input type="hidden" name="excluded_ids" id="deleteClientsExcludedIds" value="[]">
                        <input type="hidden" name="max_client_id" id="deleteClientsMaxClientId" value="">
                        <input type="hidden" name="operation_id" id="deleteClientsOperationId" value="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteClientsWithoutTransactionsModalLabel">
                                Delete Clients Without Transactions
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                id="deleteClientsClose"></button>
                        </div>
                        <div class="modal-body">
                            <div id="deleteClientsSelectionContent">
                            <p class="mb-2">Select the clients with no transaction history that you want to delete.</p>
                            <p class="fw-semibold mb-2" id="deleteClientsPreviewCount" role="status" aria-live="polite">
                                Loading eligible clients...
                            </p>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="deleteClientsSelectAll" disabled>
                                <label class="form-check-label fw-semibold" for="deleteClientsSelectAll">
                                    Select all eligible clients across every page
                                </label>
                            </div>
                            <p class="small text-muted mb-2" id="deleteClientsSelectionCount" aria-live="polite">
                                0 clients selected
                            </p>
                            <div class="table-responsive border rounded" style="max-height: 360px; overflow-y: auto;">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Select</th>
                                            <th>Client ID</th>
                                            <th>Full Name</th>
                                            <th>Address</th>
                                        </tr>
                                    </thead>
                                    <tbody id="deleteClientsPreviewRows">
                                        <tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                                <span class="small text-muted" id="deleteClientsPreviewPage"></span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        id="deleteClientsPreviewPrevious" disabled>Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        id="deleteClientsPreviewNext" disabled>Next</button>
                                </div>
                            </div>
                            <p class="text-muted small mt-3 mb-0">Only selected clients will be deleted. Select all
                                includes eligible clients on every page, even when the Client List is filtered. Later
                                client IDs will move down to fill available gaps, and their transaction IDs will be
                                updated to match. Archived IDs remain reserved. Saved photos and fingerprints of
                                deleted clients will also be removed. This action cannot be undone.</p>
                            </div>
                            <div class="d-none py-3 text-center" id="deleteClientsProgress" role="status" aria-live="polite">
                                <div class="spinner-border text-primary mb-3" aria-hidden="true"></div>
                                <h6 class="fw-semibold mb-2" id="deleteClientsProgressMessage">Preparing deletion...</h6>
                                <p class="text-muted mb-1" id="deleteClientsProgressCount">Checking selected clients...</p>
                                <p class="small text-muted mb-0">Keep this page open until the deletion finishes.</p>
                                <div class="alert alert-danger d-none mt-3 mb-0 text-start" id="deleteClientsProgressError"
                                    role="alert"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal"
                                id="deleteClientsCancel">Cancel</button>
                            <button type="submit" class="btn btn-danger" id="deleteEligibleClientsConfirm" disabled>
                                Delete Selected Clients
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endunless

    <div class="modal fade" id="clientPhotoModal" tabindex="-1" aria-labelledby="clientPhotoModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientPhotoModalLabel">Client Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="clientPhotoModalImage" src="" alt="Client Photo Preview"
                        class="img-fluid rounded-3 border object-fit-cover" style="max-height: 420px;">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fingerprintSearchModal" tabindex="-1" aria-labelledby="fingerprintSearchModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fingerprintSearchModalLabel">Search by Fingerprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
                            <div>
                                <div class="fw-semibold mb-1">Fingerprint Search</div>
                                <p class="text-muted small mb-0">
                                    Open this panel, place your finger on the scanner, and the matching client will be
                                    highlighted automatically.
                                </p>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0" role="alert" id="fingerprintSearchStatus">
                            Waiting to start fingerprint search.
                        </div>
                        <div class="mt-3 text-center">
                            <img id="fingerprintSearchPreview" src="{{ asset('assets/images/fingerprint.png') }}"
                                alt="Fingerprint Search Preview" class="rounded-3 border object-fit-cover bg-white"
                                style="width: 100%; max-width: 420px; height: 280px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-primary d-none" id="fingerprintScanAgainBtn">Scan
                        Again</button>
                    <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('deleteClientsWithoutTransactionsModal');
            if (!modal) return;

            const form = modal.querySelector('form');
            const rows = document.getElementById('deleteClientsPreviewRows');
            const count = document.getElementById('deleteClientsPreviewCount');
            const pageLabel = document.getElementById('deleteClientsPreviewPage');
            const previous = document.getElementById('deleteClientsPreviewPrevious');
            const next = document.getElementById('deleteClientsPreviewNext');
            const confirmDelete = document.getElementById('deleteEligibleClientsConfirm');
            const selectAll = document.getElementById('deleteClientsSelectAll');
            const selectionCount = document.getElementById('deleteClientsSelectionCount');
            const selectAllValue = document.getElementById('deleteClientsSelectAllValue');
            const selectedIdsValue = document.getElementById('deleteClientsSelectedIds');
            const excludedIdsValue = document.getElementById('deleteClientsExcludedIds');
            const maxClientIdValue = document.getElementById('deleteClientsMaxClientId');
            const operationIdValue = document.getElementById('deleteClientsOperationId');
            const selectionContent = document.getElementById('deleteClientsSelectionContent');
            const progressPanel = document.getElementById('deleteClientsProgress');
            const progressMessage = document.getElementById('deleteClientsProgressMessage');
            const progressCount = document.getElementById('deleteClientsProgressCount');
            const progressError = document.getElementById('deleteClientsProgressError');
            const progressSpinner = progressPanel.querySelector('.spinner-border');
            const closeButton = document.getElementById('deleteClientsClose');
            const cancelButton = document.getElementById('deleteClientsCancel');
            const selectedIds = new Set();
            const excludedIds = new Set();
            let allSelected = false;
            let totalEligible = 0;
            let previewReady = false;
            let previewMaxClientId = null;
            let currentPage = 1;
            let requestVersion = 0;
            let processing = false;
            let progressTimer = null;
            let progressSnapshot = {};
            let postConnectionLost = false;
            let allowCloseOnDisconnect = false;

            const renderProgress = (update) => {
                progressSnapshot = { ...progressSnapshot, ...update };
                progressMessage.textContent = progressSnapshot.message || 'Processing deletion...';
                const checked = Number(progressSnapshot.checked) || 0;
                const total = Number(progressSnapshot.total) || 0;
                const deleted = Number(progressSnapshot.deleted) || 0;
                progressCount.textContent = total > 0 ?
                    `Checked ${checked.toLocaleString()} of ${total.toLocaleString()} clients. Deleted ${deleted.toLocaleString()}.` :
                    `${deleted.toLocaleString()} ${deleted === 1 ? 'client' : 'clients'} deleted so far.`;
            };

            const stopProgressPolling = () => {
                if (progressTimer) clearTimeout(progressTimer);
                progressTimer = null;
            };

            const showProgressError = (message) => {
                processing = false;
                stopProgressPolling();
                progressSpinner.classList.add('d-none');
                progressMessage.textContent = 'Could not confirm deletion';
                progressError.textContent = message;
                progressError.classList.remove('d-none');
                closeButton.disabled = false;
                cancelButton.disabled = false;
                cancelButton.textContent = 'Close';
            };

            const finishProgress = (redirectUrl) => {
                processing = false;
                stopProgressPolling();
                progressSpinner.classList.add('d-none');
                progressMessage.textContent = 'Deletion complete. Opening Client List...';
                window.location.assign(redirectUrl || @json(route('client.list')));
            };

            const pollProgress = async (operationId) => {
                if (!processing) return;
                try {
                    const url = modal.dataset.progressUrl.replace('__operation__', operationId);
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store'
                    });
                    if (response.ok) {
                        const status = await response.json();
                        renderProgress(status);
                        if (status.state === 'complete' && postConnectionLost) {
                            finishProgress(status.redirect);
                            return;
                        }
                        if (status.state === 'failed') {
                            showProgressError(status.message || 'Check the Client List before trying again.');
                            return;
                        }
                    }
                } catch (error) {
                    if (postConnectionLost) {
                        progressMessage.textContent = 'Connection interrupted. Checking deletion status...';
                    }
                }
                if (processing) progressTimer = setTimeout(() => pollProgress(operationId), 800);
            };

            const updateSelection = () => {
                const selectedCount = allSelected ?
                    Math.max(0, totalEligible - excludedIds.size) : selectedIds.size;
                const everyClientSelected = totalEligible > 0 && selectedCount === totalEligible;
                selectAll.checked = everyClientSelected;
                selectAll.indeterminate = selectedCount > 0 && !everyClientSelected;
                selectAll.disabled = !previewReady || totalEligible === 0;
                selectionCount.textContent = `${selectedCount.toLocaleString()} ${selectedCount === 1 ? 'client' : 'clients'} selected`;
                confirmDelete.textContent = selectedCount ?
                    `Delete Selected Clients (${selectedCount.toLocaleString()})` : 'Delete Selected Clients';
                confirmDelete.disabled = !previewReady || selectedCount === 0;
            };

            const showMessage = (message) => {
                rows.replaceChildren();
                const cell = rows.insertRow().insertCell();
                cell.colSpan = 4;
                cell.className = 'text-center text-muted py-3';
                cell.textContent = message;
            };

            const loadPreview = async (page) => {
                const version = ++requestVersion;
                const firstLoad = previewMaxClientId === null;
                const previousWasDisabled = previous.disabled;
                const nextWasDisabled = next.disabled;
                previewReady = false;
                previous.disabled = true;
                next.disabled = true;
                updateSelection();
                if (firstLoad) {
                    count.textContent = 'Loading eligible clients...';
                    pageLabel.textContent = '';
                    showMessage('Loading...');
                } else {
                    pageLabel.textContent = `Loading page ${page}...`;
                    rows.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.disabled = true;
                    });
                }

                try {
                    const url = new URL(modal.dataset.previewUrl, window.location.href);
                    url.searchParams.set('page', page);
                    if (previewMaxClientId !== null) {
                        url.searchParams.set('max_client_id', previewMaxClientId);
                    }
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('Preview request failed');
                    const preview = await response.json();
                    if (version !== requestVersion) return;

                    const clients = Array.isArray(preview.data) ? preview.data : [];
                    previewMaxClientId = Number(preview.max_client_id) || 0;
                    const total = Number(preview.total) || 0;
                    totalEligible = total;
                    currentPage = Number(preview.current_page) || 1;
                    count.textContent = `${total.toLocaleString()} ${total === 1 ? 'client' : 'clients'} eligible for deletion`;
                    pageLabel.textContent = total ?
                        `Showing ${preview.from}–${preview.to} of ${total.toLocaleString()}` : '';

                    rows.replaceChildren();
                    if (!clients.length) {
                        showMessage('No clients without transaction history were found.');
                    } else {
                        clients.forEach((client) => {
                            const row = rows.insertRow();
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'form-check-input';
                            checkbox.setAttribute('aria-label',
                                `Select client ${client.client_id || client.id}: ${client.full_name || '-'}`);
                            const id = Number(client.id);
                            checkbox.checked = allSelected ? !excludedIds.has(id) : selectedIds.has(id);
                            checkbox.addEventListener('change', () => {
                                if (allSelected) {
                                    checkbox.checked ? excludedIds.delete(id) : excludedIds.add(id);
                                } else {
                                    checkbox.checked ? selectedIds.add(id) : selectedIds.delete(id);
                                }
                                updateSelection();
                            });
                            row.insertCell().appendChild(checkbox);
                            ['client_id', 'full_name', 'address'].forEach((field) => {
                                row.insertCell().textContent = client[field] || '-';
                            });
                        });
                    }

                    previous.disabled = currentPage <= 1;
                    next.disabled = currentPage >= Number(preview.last_page);
                    previewReady = true;
                    updateSelection();
                } catch (error) {
                    if (version !== requestVersion) return;
                    if (firstLoad) {
                        count.textContent = 'Unable to load eligible clients.';
                        showMessage('Close this window and try again.');
                    } else {
                        pageLabel.textContent = 'Could not load this page. Try again.';
                        previous.disabled = previousWasDisabled;
                        next.disabled = nextWasDisabled;
                        rows.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                            checkbox.disabled = false;
                        });
                        previewReady = true;
                    }
                    updateSelection();
                }
            };

            modal.addEventListener('show.bs.modal', () => {
                if (processing) return;
                selectedIds.clear();
                excludedIds.clear();
                allSelected = false;
                totalEligible = 0;
                previewMaxClientId = null;
                progressSnapshot = {};
                postConnectionLost = false;
                allowCloseOnDisconnect = false;
                stopProgressPolling();
                selectionContent.classList.remove('d-none');
                progressPanel.classList.add('d-none');
                progressSpinner.classList.remove('d-none');
                progressError.classList.add('d-none');
                closeButton.disabled = false;
                cancelButton.disabled = false;
                cancelButton.textContent = 'Cancel';
                loadPreview(1);
            });
            modal.addEventListener('hide.bs.modal', (event) => {
                if (processing && !allowCloseOnDisconnect) event.preventDefault();
            });
            modal.addEventListener('hidden.bs.modal', () => {
                processing = false;
                stopProgressPolling();
                requestVersion++;
                previewReady = false;
                confirmDelete.disabled = true;
            });
            selectAll.addEventListener('change', () => {
                allSelected = selectAll.checked;
                selectedIds.clear();
                excludedIds.clear();
                rows.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.checked = allSelected;
                });
                updateSelection();
            });
            previous.addEventListener('click', () => loadPreview(currentPage - 1));
            next.addEventListener('click', () => loadPreview(currentPage + 1));
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!previewReady || confirmDelete.disabled) {
                    return;
                }
                selectAllValue.value = allSelected ? '1' : '0';
                selectedIdsValue.value = JSON.stringify([...selectedIds]);
                excludedIdsValue.value = JSON.stringify([...excludedIds]);
                maxClientIdValue.value = previewMaxClientId;
                operationIdValue.value = window.crypto?.randomUUID?.() ||
                    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
                        const random = window.crypto.getRandomValues(new Uint8Array(1))[0] & 15;
                        return (character === 'x' ? random : ((random & 3) | 8)).toString(16);
                    });
                processing = true;
                confirmDelete.disabled = true;
                closeButton.disabled = true;
                cancelButton.disabled = true;
                selectionContent.classList.add('d-none');
                progressPanel.classList.remove('d-none');
                progressError.classList.add('d-none');
                renderProgress({ state: 'pending', message: 'Starting deletion...', checked: 0,
                    total: 0, deleted: 0 });
                progressTimer = setTimeout(() => pollProgress(operationIdValue.value), 400);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        showProgressError(result.message || 'Deletion failed. Check the Client List before trying again.');
                        return;
                    }
                    renderProgress({ state: 'complete', message: result.message });
                    finishProgress(result.redirect);
                } catch (error) {
                    postConnectionLost = true;
                    allowCloseOnDisconnect = true;
                    closeButton.disabled = false;
                    cancelButton.disabled = false;
                    cancelButton.textContent = 'Close';
                    progressMessage.textContent = 'Connection interrupted. Checking deletion status...';
                    progressError.textContent = 'You can close this window and check the Client List. Do not retry until you confirm whether deletion finished.';
                    progressError.classList.remove('d-none');
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('clientPhotoModal');
            const modalImage = document.getElementById('clientPhotoModalImage');
            const modalTitle = document.getElementById('clientPhotoModalLabel');
            const fingerprintPlaceholderPreview = @json(asset('assets/images/fingerprint.png'));
            const searchFingerprintBtn = document.getElementById('searchFingerprintBtn');
            const fingerprintSearchModalEl = document.getElementById('fingerprintSearchModal');
            const fingerprintSearchPreview = document.getElementById('fingerprintSearchPreview');
            const fingerprintSearchStatus = document.getElementById('fingerprintSearchStatus');
            const fingerprintScanAgainBtn = document.getElementById('fingerprintScanAgainBtn');
            const clientKeywordInput = document.getElementById('clientKeywordInput');
            const clientSexFilter = document.getElementById('clientSexFilter');
            const clientCivilStatusFilter = document.getElementById('clientCivilStatusFilter');
            const clientCityFilter = document.getElementById('clientCityFilter');
            const clientBarangayFilter = document.getElementById('clientBarangayFilter');
            const clientRecordTypeFilter = document.getElementById('clientRecordTypeFilter');
            const clientFiltersToggleBtn = document.getElementById('clientFiltersToggleBtn');
            const clientFiltersFormEl = document.getElementById('clientFiltersForm');
            const clientSearchSummary = document.getElementById('clientSearchSummary');
            const clientSearchNoResultsRow = document.getElementById('clientSearchNoResultsRow');
            const clientDateFrom = document.getElementById('clientDateFrom');
            const clientDateTo = document.getElementById('clientDateTo');
            const clientDateApplyBtn = document.getElementById('clientDateApplyBtn');
            const clientFiltersCountBadge = document.getElementById('clientFiltersCountBadge');
            const fingerprintBridgeBase = 'http://127.0.0.1:38654';
            const clientListSearchUrl = @json(route('client.search.fingerprint'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!modalEl || !modalImage || !modalTitle || !searchFingerprintBtn || !
                fingerprintSearchModalEl || !fingerprintSearchPreview || !fingerprintSearchStatus || !
                fingerprintScanAgainBtn || !clientKeywordInput || !clientSexFilter || !clientCivilStatusFilter || !
                clientCityFilter || !clientBarangayFilter || !clientRecordTypeFilter || !
                clientFiltersToggleBtn || !clientFiltersFormEl || !clientDateFrom || !clientDateTo || !
                clientDateApplyBtn || !clientSearchSummary || !clientSearchNoResultsRow
            ) {
                return;
            }


            const fingerprintSearchModal = bootstrap.Modal.getOrCreateInstance(fingerprintSearchModalEl);
            const clientRows = Array.from(document.querySelectorAll('[data-client-row]'));
            let filtersVisible = true;


            const setFiltersVisibility = (visible) => {
                filtersVisible = visible;
                clientFiltersFormEl.classList.toggle('d-none', !visible);
                clientFiltersToggleBtn.innerHTML = visible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };

            const filterClientList = () => {
                const query = clientKeywordInput.value.trim().toLowerCase();
                const sex = clientSexFilter.value.trim().toLowerCase();
                const civilStatus = clientCivilStatusFilter.value.trim().toLowerCase();
                const city = clientCityFilter.value.trim().toLowerCase();
                const barangay = clientBarangayFilter.value.trim().toLowerCase();
                const dateFrom = clientDateFrom.value;
                const dateTo = clientDateTo.value;
                let visibleCount = 0;

                clientRows.forEach((row) => {
                    const rowSex = (row.dataset.searchGender || '').toLowerCase();
                    const rowCivilStatus = (row.dataset.searchCivilStatus || '').toLowerCase();
                    const rowCity = (row.dataset.searchCity || '').toLowerCase();
                    const rowBarangay = (row.dataset.searchBarangay || '').toLowerCase();
                    const createdAt = row.dataset.searchCreatedAt || '';
                    const matchesSex = !sex || rowSex === sex;
                    const matchesCivilStatus = !civilStatus || rowCivilStatus === civilStatus;
                    const matchesCity = !city || rowCity === city;
                    const matchesBarangay = !barangay || rowBarangay === barangay;
                    const matchesDate = (!dateFrom || createdAt >= dateFrom) && (!dateTo || createdAt <=
                        dateTo);
                    const matches = matchesSex && matchesCivilStatus && matchesCity &&
                        matchesBarangay && matchesDate;
                    row.classList.toggle('d-none', !matches);

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (clientSearchNoResultsRow) {
                    clientSearchNoResultsRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (clientSearchSummary) {
                    const activeFilters = [query, sex, civilStatus, city, barangay, dateFrom, dateTo].filter(
                        Boolean).length;
                    if (!activeFilters) {
                        clientSearchSummary.textContent = 'Showing all clients.';
                    } else {
                        clientSearchSummary.textContent =
                            `Showing ${visibleCount} matching client${visibleCount === 1 ? '' : 's'}.`;
                    }
                }

                if (clientFiltersCountBadge) {
                    clientFiltersCountBadge.textContent = activeFiltersText(visibleCount);
                }
            };

            const activeFiltersText = (visibleCount) => {
                const totalCount = clientRows.length;
                return totalCount === 0 ?
                    'Showing 0 clients' :
                    (visibleCount === totalCount ?
                        'Showing all clients' :
                        `Showing ${visibleCount} of ${totalCount} clients`);
            };

            const captureEditFingerprintFromBridge = async () => {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 45000);

                try {
                    const response = await fetch(`${fingerprintBridgeBase}/api/capture`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            source: 'laravel'
                        }),
                        signal: controller.signal
                    });

                    const payload = await response.json();
                    if (!response.ok || !payload.success || !payload.imageDataUrl) {
                        throw new Error(payload.message || 'Scanner capture failed.');
                    }

                    return payload;
                } finally {
                    clearTimeout(timeoutId);
                }
            };

            const isFingerprintBridgeOnline = async () => {
                try {
                    const response = await fetch(`${fingerprintBridgeBase}/api/health`, {
                        method: 'GET',
                        cache: 'no-store'
                    });

                    return response.ok;
                } catch (error) {
                    return false;
                }
            };

            const postFingerprintSearch = async (templateXml, imageDataUrl = '') => {
                const response = await fetch(clientListSearchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        fingerprint_template: templateXml,
                        fingerprint_data: imageDataUrl
                    })
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Fingerprint search failed.');
                }

                return payload;
            };

            const highlightMatchedClient = (clientId) => {
                const row = document.querySelector(`[data-client-row="${clientId}"]`);
                if (!row) {
                    return;
                }

                document.querySelectorAll('[data-client-row]').forEach((tableRow) => {
                    tableRow.classList.remove('table-success');
                });

                row.classList.add('table-success');
                row.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            };

            const searchFingerprintAndHighlight = async () => {
                try {
                    const bridgeOnline = await isFingerprintBridgeOnline();
                    if (!bridgeOnline) {
                        throw new Error(
                            'DigitalPersona bridge is not running. Start the FingerprintBridge app first.'
                        );
                    }

                    fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                    const captureResult = await captureEditFingerprintFromBridge();
                    fingerprintSearchPreview.src = captureResult.imageDataUrl;
                    fingerprintSearchStatus.textContent = 'Searching for a matching client...';

                    const searchResult = await postFingerprintSearch(
                        captureResult.fingerprintTemplateXml || '',
                        captureResult.imageDataUrl || ''
                    );
                    if (searchResult.matched && searchResult.client) {
                        fingerprintSearchStatus.textContent = `Match found: ${searchResult.client.name}`;
                        fingerprintScanAgainBtn.classList.add('d-none');
                        highlightMatchedClient(searchResult.client.id);
                        fingerprintSearchModal.hide();
                        window.location.href = searchResult.client.show_url;
                        return;
                    }

                    fingerprintSearchStatus.textContent = searchResult.message ||
                        'No matching client found.';
                    fingerprintScanAgainBtn.classList.remove('d-none');
                } catch (error) {
                    fingerprintSearchStatus.textContent = 'Fingerprint search failed.';
                    fingerprintScanAgainBtn.classList.remove('d-none');
                }
            };

            modalEl.addEventListener('show.bs.modal', function(event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                const photo = trigger.getAttribute('data-client-photo');
                const name = trigger.getAttribute('data-client-name') || 'Client Photo';

                modalImage.src = photo || '';
                modalTitle.textContent = name;
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                modalImage.src = '';
                modalTitle.textContent = 'Client Photo';
            });

            searchFingerprintBtn.addEventListener('click', function() {
                fingerprintSearchModal.show();
            });

            fingerprintScanAgainBtn.addEventListener('click', function() {
                fingerprintScanAgainBtn.classList.add('d-none');
                fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                searchFingerprintAndHighlight();
            });

            // ----- Auto search: debounced submit while typing -----
            const VISIBILITY_KEY = 'clientFiltersVisible';
            let autoSearchDebounce = null;
            const submitFilters = () => {
                // Keep the panel open across the reload.
                sessionStorage.setItem(VISIBILITY_KEY, '1');
                clientFiltersFormEl.submit();
            };

            // ----- Per page selector -----
            document.getElementById('clientPerPageSelect')?.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });

            clientKeywordInput.addEventListener('input', function() {
                clearTimeout(autoSearchDebounce);
                // Panel stays visible while typing.
                setFiltersVisibility(true);
                autoSearchDebounce = setTimeout(submitFilters, 500);
            });
            clientSexFilter.addEventListener('change', submitFilters);
            clientCivilStatusFilter.addEventListener('change', submitFilters);
            clientCityFilter.addEventListener('change', submitFilters);
            clientBarangayFilter.addEventListener('change', submitFilters);
            clientDateFrom.addEventListener('change', submitFilters);
            clientDateTo.addEventListener('change', submitFilters);

            // Restore focus/caret after an auto-submit so typing continues smoothly.
            @if (request()->filled('search'))
                setFiltersVisibility(true);
                clientKeywordInput.focus();
                clientKeywordInput.setSelectionRange(clientKeywordInput.value.length, clientKeywordInput.value
                    .length);
            @endif

            clientFiltersToggleBtn.addEventListener('click', function() {
                setFiltersVisibility(!filtersVisible);
            });

            @if (request()->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to']))
                setFiltersVisibility(true);
            @else
                setFiltersVisibility(false);
            @endif
            filterClientList();

            fingerprintSearchModalEl.addEventListener('shown.bs.modal', function() {
                fingerprintSearchPreview.src = fingerprintPlaceholderPreview;
                fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                fingerprintScanAgainBtn.classList.add('d-none');
                searchFingerprintAndHighlight();
            });

            const matchedClientId = new URLSearchParams(window.location.search).get('matched_client');
            if (matchedClientId) {
                highlightMatchedClient(matchedClientId);
            }
        });
    </script>
@endsection
