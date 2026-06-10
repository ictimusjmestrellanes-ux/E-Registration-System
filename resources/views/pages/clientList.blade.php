@extends('layouts.master')
@section('title', 'Client List')
@section('content')
    <style>
        .client-details-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #0f172a;
        }

        .client-details-panel .client-details-label,
        .client-details-panel .client-details-title,
        .client-details-panel .client-details-text {
            color: inherit;
        }

        .client-details-panel .client-details-muted {
            color: #64748b;
        }

        html[data-bs-theme="dark"] .client-details-panel {
            background: #1f2329;
            border-color: #2f353d;
            color: #f8fafc;
        }

        html[data-bs-theme="dark"] .client-details-panel .client-details-muted {
            color: #a7b0bf;
        }

        #clientFiltersCard {
            background: #ffffff;
            border: 1px solid #e3e8ef;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }

        #clientFiltersCard .form-label,
        #clientFiltersCard .small,
        #clientFiltersCard .fw-bold,
        #clientFiltersCard .fw-semibold {
            color: #1f2937 !important;
        }

        #clientFiltersCard .input-group-text,
        #clientFiltersCard .form-control,
        #clientFiltersCard .form-select {
            background-color: #f8fafc;
            color: #111827;
            border-color: #d5dbe3;
        }

        #clientFiltersCard .input-group-text {
            color: #475569;
        }

        #clientFiltersCard .form-control::placeholder {
            color: #94a3b8;
        }

        #clientFiltersCard .form-control:focus,
        #clientFiltersCard .form-select:focus {
            border-color: #4d63d6;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.14);
        }

        #clientFiltersCard .btn-outline-primary {
            color: #3551d5;
            border-color: #7a8de8;
        }

        #clientFiltersCard .client-filters-toggle-btn {
            transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        #clientFiltersCard .client-filters-toggle-btn:hover,
        #clientFiltersCard .client-filters-toggle-btn:focus,
        #clientFiltersCard .client-filters-toggle-btn:active {
            background: #eef2ff;
            color: #2f49c5;
            border-color: #6276df;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.12);
        }

        #clientFiltersCard .btn-soft-secondary {
            background: #eef2ff;
            color: #334155;
            border-color: #e5e7eb;
        }

        #clientFiltersCard .badge {
            background: #eef2ff !important;
            color: #334155 !important;
        }

        #clientFiltersCard .input-group-text,
        #clientFiltersCard .form-control,
        #clientFiltersCard .form-select {
            box-shadow: none;
        }

        #clientFiltersCard .text-primary {
            color: #3551d5 !important;
        }

        #clientFiltersCard .bg-primary-subtle {
            background: rgba(77, 99, 214, 0.12) !important;
        }

        #clientFiltersCard .btn-primary {
            background: linear-gradient(135deg, #4d63d6, #5a73ff);
            border-color: transparent;
        }

        html[data-bs-theme="dark"] #clientFiltersCard {
            background: #1f2329;
            border-color: #2f353d;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.24);
        }

        html[data-bs-theme="dark"] #clientFiltersCard .form-label,
        html[data-bs-theme="dark"] #clientFiltersCard .small,
        html[data-bs-theme="dark"] #clientFiltersCard .fw-bold,
        html[data-bs-theme="dark"] #clientFiltersCard .fw-semibold {
            color: #d7dbe3 !important;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .input-group-text,
        html[data-bs-theme="dark"] #clientFiltersCard .form-control,
        html[data-bs-theme="dark"] #clientFiltersCard .form-select {
            background-color: #2a2f36;
            color: #f8fafc;
            border-color: #3a4049;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .input-group-text {
            color: #c9ced8;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .form-control::placeholder {
            color: #95a0b2;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .btn-outline-primary {
            color: #b9c7ff;
            border-color: #5a68d6;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .client-filters-toggle-btn:hover,
        html[data-bs-theme="dark"] #clientFiltersCard .client-filters-toggle-btn:focus,
        html[data-bs-theme="dark"] #clientFiltersCard .client-filters-toggle-btn:active {
            background: rgba(77, 99, 214, 0.18);
            color: #d9e1ff;
            border-color: #7f90ef;
            box-shadow: 0 0 0 0.2rem rgba(77, 99, 214, 0.18);
        }

        html[data-bs-theme="dark"] #clientFiltersCard .btn-soft-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #edf1ff;
            border-color: rgba(255, 255, 255, 0.08);
        }

        html[data-bs-theme="dark"] #clientFiltersCard .badge {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #e8ecf5 !important;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .text-primary {
            color: #8aa0ff !important;
        }

        html[data-bs-theme="dark"] #clientFiltersCard .bg-primary-subtle {
            background: rgba(77, 99, 214, 0.16) !important;
        }
    </style>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client List</h4>
                                <p class="text-muted mb-0">
                                    {{ $matchedClientId ? 'Showing the matched client only.' : 'View all registered clients here.' }}
                                </p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @if ($matchedClientId)
                                    <a href="{{ route('client.list') }}" class="btn btn-soft-secondary">Show All Clients</a>
                                @endif
                                <button type="button" class="btn btn-soft-primary" id="searchFingerprintBtn">Search by Fingerprint</button>
                                <a href="{{ route('clients') }}" class="btn btn-primary">Add Client</a>
                            </div>
                        </div>

                        @if ($matchedClientId)
                            <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div>Fingerprint search matched one client and the list is filtered to that result.</div>
                                <a href="{{ route('client.list') }}" class="btn btn-sm btn-outline-success">Clear Filter</a>
                            </div>
                        @endif

                        @php
                            $clientCities = $clients->pluck('city')->filter()->unique()->sort()->values();
                            $clientBarangays = $clients->pluck('barangay')->filter()->unique()->sort()->values();
                            $clientCivilStatuses = $clients->pluck('civil_status')->filter()->unique()->sort()->values();
                        @endphp

                        <div class="border rounded-4 p-3 mb-3" id="clientFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
                                <div>
                                    <div class="fw-bold fs-5">Filter Clients</div>
                                    <div class="text-muted small">Narrow records by keyword, sex, civil status, location, and created date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-outline-primary client-filters-toggle-btn" id="clientFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <button type="button" class="btn btn-soft-secondary" id="clientFiltersResetBtn">Reset</button>
                                    <span class="badge rounded-pill px-3 py-2" id="clientFiltersCountBadge">Showing all clients</span>
                                </div>
                            </div>

                            <div id="clientFiltersBody" class="d-none">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="clientKeywordInput" class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="clientKeywordInput" placeholder="Name, email, contact, address">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientSexFilter" class="form-label fw-semibold text-uppercase small">Gender</label>
                                        <select class="form-select" id="clientSexFilter">
                                            <option value="">All Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientCivilStatusFilter" class="form-label fw-semibold text-uppercase small">Civil Status</label>
                                        <select class="form-select" id="clientCivilStatusFilter">
                                            <option value="">All civil statuses</option>
                                            @foreach ($clientCivilStatuses as $civilStatus)
                                                <option value="{{ strtolower($civilStatus) }}">{{ $civilStatus }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientCityFilter" class="form-label fw-semibold text-uppercase small">City</label>
                                        <select class="form-select" id="clientCityFilter">
                                            <option value="">All cities</option>
                                            @foreach ($clientCities as $city)
                                                <option value="{{ strtolower($city) }}">{{ $city }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientBarangayFilter" class="form-label fw-semibold text-uppercase small">Barangay</label>
                                        <select class="form-select" id="clientBarangayFilter">
                                            <option value="">All barangays</option>
                                            @foreach ($clientBarangays as $barangay)
                                                <option value="{{ strtolower($barangay) }}">{{ $barangay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 col-lg-3">
                                        <label for="clientRecordTypeFilter" class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <select class="form-select" id="clientRecordTypeFilter">
                                            <option value="all">All records</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <label for="clientDateFrom" class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="clientDateFrom">
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <label for="clientDateTo" class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="clientDateTo">
                                    </div>
                                    <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                                        <button type="button" class="btn btn-primary px-4" id="clientDateApplyBtn">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3" id="clientSearchSummary">Showing all clients.</div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Civil Status</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Address</th>
                                        <th>Province</th>
                                        <th>City</th>
                                        <th>Barangay</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    @forelse ($clients as $client)
                                        @php
                                            $clientName = trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name);
                                        @endphp
                                        <tr
                                            data-client-row="{{ $client->id }}"
                                            data-search-name="{{ strtolower($clientName) }}"
                                            data-search-email="{{ strtolower($client->email ?? '') }}"
                                            data-search-contact="{{ strtolower($client->contact ?? '') }}"
                                            data-search-address="{{ strtolower($client->address ?? '') }}"
                                            data-search-gender="{{ strtolower($client->gender ?? '') }}"
                                            data-search-civil-status="{{ strtolower($client->civil_status ?? '') }}"
                                            data-search-province="{{ strtolower($client->province ?? '') }}"
                                            data-search-city="{{ strtolower($client->city ?? '') }}"
                                            data-search-barangay="{{ strtolower($client->barangay ?? '') }}"
                                            data-search-created-at="{{ optional($client->created_at)->format('Y-m-d') }}"
                                            data-search-all="{{ strtolower($clientName . ' ' . ($client->email ?? '') . ' ' . ($client->contact ?? '') . ' ' . ($client->gender ?? '') . ' ' . ($client->civil_status ?? '') . ' ' . ($client->address ?? '') . ' ' . ($client->province ?? '') . ' ' . ($client->city ?? '') . ' ' . ($client->barangay ?? '')) }}"
                                        >
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @php
                                                    $clientPhoto = $client->photo_path ? asset('storage/' . $client->photo_path) : asset('assets/images/avatar-1.jpg');
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#clientPhotoModal"
                                                    data-client-photo="{{ $clientPhoto }}"
                                                    data-client-name="{{ trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name) }}"
                                                >
                                                    <img
                                                        src="{{ $clientPhoto }}"
                                                        alt="Client Photo"
                                                        class="rounded-3 border object-fit-cover"
                                                        style="width: 72px; height: 72px;"
                                                    >
                                                </button>
                                            </td>
                                            <td>{{ trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name) }}</td>
                                            <td>{{ $client->age ?? '-' }}</td>
                                            <td>{{ $client->gender ?? '-' }}</td>
                                            <td>{{ $client->civil_status ?? '-' }}</td>
                                            <td>{{ $client->email ?? '-' }}</td>
                                            <td>{{ $client->contact ?? '-' }}</td>
                                            <td>{{ $client->address ?? '-' }}</td>
                                            <td>{{ $client->province ?? '-' }}</td>
                                            <td>{{ $client->city ?? '-' }}</td>
                                            <td>{{ $client->barangay ?? '-' }}</td>
                                            <td>
                                                <div class="d-flex gap-2 text-center justify-content-center">
                                                    <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-soft-info">
                                                        View
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-soft-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editClientModal"
                                                        data-update-url="{{ route('clients.update', $client) }}"
                                                        data-client-id="{{ $client->id }}"
                                                        data-first-name="{{ $client->first_name }}"
                                                        data-middle-name="{{ $client->middle_name }}"
                                                        data-last-name="{{ $client->last_name }}"
                                                        data-age="{{ $client->age }}"
                                                        data-gender="{{ $client->gender }}"
                                                        data-civil-status="{{ $client->civil_status }}"
                                                        data-email="{{ $client->email }}"
                                                        data-contact="{{ $client->contact }}"
                                                        data-address="{{ $client->address }}"
                                                        data-province="{{ $client->province }}"
                                                        data-city="{{ $client->city }}"
                                                        data-barangay="{{ $client->barangay }}"
                                                        data-client-name="{{ trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name) }}"
                                                        data-client-photo="{{ $clientPhoto }}"
                                                        data-client-fingerprint="{{ $client->fingerprint_path ? asset('storage/' . $client->fingerprint_path) : '' }}"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form action="{{ route('clients.archive', $client) }}" method="POST" onsubmit="return confirm('Are you sure you want to archive this client?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-warning">
                                                            Archive
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="clientSearchEmptyRow">
                                            <td colspan="13" class="text-center text-muted py-4">
                                                {{ $matchedClientId ? 'No matching client found.' : 'No clients found.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    @if ($clients->count())
                                        <tr id="clientSearchNoResultsRow" class="d-none">
                                            <td colspan="13" class="text-center text-muted py-4">
                                                No matching clients found.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientPhotoModal" tabindex="-1" aria-labelledby="clientPhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientPhotoModalLabel">Client Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="clientPhotoModalImage" src="" alt="Client Photo Preview" class="img-fluid rounded-3 border object-fit-cover" style="max-height: 420px;">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientViewModal" tabindex="-1" aria-labelledby="clientViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientViewModalLabel">Client Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-12 col-lg-4 text-center">
                            <img
                                id="clientViewPhoto"
                                src="{{ asset('assets/images/avatar-1.jpg') }}"
                                alt="Client Photo"
                                class="rounded-4 border object-fit-cover bg-light"
                                style="width: 100%; max-width: 320px; height: 320px;"
                            >
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <div class="text-muted small text-uppercase fw-semibold">Full Name</div>
                                    <div class="fs-4 fw-bold" id="clientViewName">Client</div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Age</div>
                                        <div class="fw-semibold" id="clientViewAge">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Gender</div>
                                        <div class="fw-semibold" id="clientViewGender">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Civil Status</div>
                                        <div class="fw-semibold" id="clientViewCivilStatus">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small text-uppercase fw-semibold">Email</div>
                                        <div class="fw-semibold" id="clientViewEmail">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small text-uppercase fw-semibold">Contact</div>
                                        <div class="fw-semibold" id="clientViewContact">-</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="text-muted small text-uppercase fw-semibold">Address</div>
                                        <div class="fw-semibold" id="clientViewAddress">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Province</div>
                                        <div class="fw-semibold" id="clientViewProvince">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">City</div>
                                        <div class="fw-semibold" id="clientViewCity">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Barangay</div>
                                        <div class="fw-semibold" id="clientViewBarangay">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-soft-info" id="clientViewPageLink">Open Full Profile</a>
                    <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
            <div class="modal-content" style="max-height: calc(100vh - 2rem); overflow: hidden;">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editClientForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body" style="overflow-y: auto; max-height: calc(100vh - 11rem);">
                        <div class="rounded-4 client-details-panel p-3 mb-4">
                            <div class="row g-4 align-items-start">
                                <div class="col-12 col-xl-6">
                                    <label class="form-label client-details-label mb-2">Client Photo</label>
                                    <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
                                        <div class="flex-shrink-0">
                                            <img
                                                id="editClientPhoto"
                                                src=""
                                                alt="Client Photo"
                                                class="rounded-4 border border-secondary-subtle bg-light object-fit-cover"
                                                style="width: 240px; height: 240px;"
                                            >
                                        </div>
                                        <div class="d-flex flex-column gap-2 justify-content-start">
                                            <div class="fw-semibold fs-5 client-details-title" id="editClientName"></div>
                                            <div class="client-details-muted small">Edit client details below.</div>
                                            <div class="d-flex flex-column gap-2 mt-2">
                                                <button type="button" class="btn btn-outline-primary" id="editOpenCameraBtn">Open Camera</button>
                                                <button type="button" class="btn btn-outline-secondary" id="editRetakePhotoBtn" disabled>Retake</button>
                                                <button type="button" class="btn btn-primary" id="editCapturePhotoBtn" disabled>Capture Photo</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="editCameraWrapper" class="border border-secondary-subtle rounded-4 p-2 bg-body-tertiary d-none mt-3">
                                        <video id="editCameraView" class="rounded-3 w-100" autoplay playsinline style="max-height: 320px; object-fit: cover; transform: scaleX(-1);"></video>
                                    </div>
                                    <canvas id="editCameraCanvas" class="d-none"></canvas>
                                    <input type="hidden" id="editPhotoData" name="photo_data">
                                </div>

                                <div class="col-12 col-xl-6">
                                    <label class="form-label client-details-label mb-2">Fingerprint Scanner</label>
                                    <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
                                        <div class="flex-shrink-0">
                                            <img
                                                id="editFingerprintPreview"
                                                src="{{ asset('assets/images/avatar-1.jpg') }}"
                                                alt="Fingerprint Preview"
                                                class="rounded-4 border border-secondary-subtle bg-light object-fit-cover"
                                                style="width: 240px; height: 240px;"
                                            >
                                        </div>
                                        <div class="d-flex flex-column gap-2 justify-content-start">
                                            <div class="d-flex flex-column flex-sm-row gap-2">
                                                <button type="button" class="btn btn-outline-primary" id="editOpenFingerprintBtn">Open Scanner</button>
                                                <button type="button" class="btn btn-outline-secondary" id="editClearFingerprintBtn" disabled>Clear</button>
                                            </div>
                                            <div class="client-details-muted small">Upload a captured fingerprint image from the scanner or biometric device.</div>
                                            <div class="client-details-muted small" id="editFingerprintStatus">No fingerprint captured yet.</div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="editFingerprintData" name="fingerprint_data">
                                    <input type="hidden" id="editFingerprintTemplate" name="fingerprint_template">
                                    <input type="hidden" id="editFingerprintRemove" name="fingerprint_remove" value="">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">

                            <div class="col-lg-4">
                                <label class="form-label" for="editFirstName">First Name</label>
                                <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editMiddleName">Middle Name</label>
                                <input type="text" class="form-control" id="editMiddleName" name="middle_name">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editLastName">Last Name</label>
                                <input type="text" class="form-control" id="editLastName" name="last_name" required>
                            </div>

                            <div class="col-lg-2">
                                <label class="form-label" for="editAge">Age</label>
                                <input type="number" class="form-control" id="editAge" name="age" min="0">
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label" for="editGender">Gender</label>
                                <select class="form-select" id="editGender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label" for="editCivilStatus">Civil Status</label>
                                <select class="form-select" id="editCivilStatus" name="civil_status">
                                    <option value="">Select civil status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Separated">Separated</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Annulled">Annulled</option>
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editContact">Contact</label>
                                <input type="text" class="form-control" id="editContact" name="contact" maxlength="11" inputmode="numeric">
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label" for="editEmail">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email">
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="editAddress">Address</label>
                                <input type="text" class="form-control" id="editAddress" name="address">
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label" for="editProvince">Province</label>
                                <input type="text" class="form-control" id="editProvince" name="province">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editCity">City</label>
                                <input type="text" class="form-control" id="editCity" name="city">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editBarangay">Barangay</label>
                                <input type="text" class="form-control" id="editBarangay" name="barangay">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fingerprintSearchModal" tabindex="-1" aria-labelledby="fingerprintSearchModalLabel" aria-hidden="true">
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
                                    Open this panel, place your finger on the scanner, and the matching client will be highlighted automatically.
                                </p>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0" role="alert" id="fingerprintSearchStatus">
                            Waiting to start fingerprint search.
                        </div>
                        <div class="mt-3 text-center">
                            <img id="fingerprintSearchPreview" src="{{ asset('assets/images/avatar-1.jpg') }}" alt="Fingerprint Search Preview" class="rounded-3 border object-fit-cover bg-white" style="width: 100%; max-width: 420px; height: 280px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-primary d-none" id="fingerprintScanAgainBtn">Scan Again</button>
                    <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('clientPhotoModal');
            const modalImage = document.getElementById('clientPhotoModalImage');
            const modalTitle = document.getElementById('clientPhotoModalLabel');
            const clientViewModalEl = document.getElementById('clientViewModal');
            const clientViewPhoto = document.getElementById('clientViewPhoto');
            const clientViewName = document.getElementById('clientViewName');
            const clientViewAge = document.getElementById('clientViewAge');
            const clientViewGender = document.getElementById('clientViewGender');
            const clientViewCivilStatus = document.getElementById('clientViewCivilStatus');
            const clientViewEmail = document.getElementById('clientViewEmail');
            const clientViewContact = document.getElementById('clientViewContact');
            const clientViewAddress = document.getElementById('clientViewAddress');
            const clientViewProvince = document.getElementById('clientViewProvince');
            const clientViewCity = document.getElementById('clientViewCity');
            const clientViewBarangay = document.getElementById('clientViewBarangay');
            const clientViewPageLink = document.getElementById('clientViewPageLink');
            const editModalEl = document.getElementById('editClientModal');
            const editForm = document.getElementById('editClientForm');
            const editTitle = document.getElementById('editClientModalLabel');
            const editPhoto = document.getElementById('editClientPhoto');
            const editName = document.getElementById('editClientName');
            const editFirstName = document.getElementById('editFirstName');
            const editMiddleName = document.getElementById('editMiddleName');
            const editLastName = document.getElementById('editLastName');
            const editAge = document.getElementById('editAge');
            const editGender = document.getElementById('editGender');
            const editCivilStatus = document.getElementById('editCivilStatus');
            const editContact = document.getElementById('editContact');
            const editEmail = document.getElementById('editEmail');
            const editAddress = document.getElementById('editAddress');
            const editProvince = document.getElementById('editProvince');
            const editCity = document.getElementById('editCity');
            const editBarangay = document.getElementById('editBarangay');
            const editOpenCameraBtn = document.getElementById('editOpenCameraBtn');
            const editCapturePhotoBtn = document.getElementById('editCapturePhotoBtn');
            const editRetakePhotoBtn = document.getElementById('editRetakePhotoBtn');
            const editCameraWrapper = document.getElementById('editCameraWrapper');
            const editCameraView = document.getElementById('editCameraView');
            const editCameraCanvas = document.getElementById('editCameraCanvas');
            const editPhotoData = document.getElementById('editPhotoData');
            const editOpenFingerprintBtn = document.getElementById('editOpenFingerprintBtn');
            const editClearFingerprintBtn = document.getElementById('editClearFingerprintBtn');
            const editFingerprintPreview = document.getElementById('editFingerprintPreview');
            const editFingerprintData = document.getElementById('editFingerprintData');
            const editFingerprintTemplate = document.getElementById('editFingerprintTemplate');
            const editFingerprintRemove = document.getElementById('editFingerprintRemove');
            const editFingerprintStatus = document.getElementById('editFingerprintStatus');
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
            const clientFiltersResetBtn = document.getElementById('clientFiltersResetBtn');
            const clientFiltersToggleBtn = document.getElementById('clientFiltersToggleBtn');
            const clientFiltersBody = document.getElementById('clientFiltersBody');
            const clientSearchSummary = document.getElementById('clientSearchSummary');
            const clientSearchNoResultsRow = document.getElementById('clientSearchNoResultsRow');
            const clientDateFrom = document.getElementById('clientDateFrom');
            const clientDateTo = document.getElementById('clientDateTo');
            const clientDateApplyBtn = document.getElementById('clientDateApplyBtn');
            const clientFiltersCountBadge = document.getElementById('clientFiltersCountBadge');
            const fingerprintBridgeBase = 'http://127.0.0.1:38654';
            const clientListSearchUrl = @json(route('client.search.fingerprint'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!modalEl || !modalImage || !modalTitle || !clientViewModalEl || !clientViewPhoto || !clientViewName || !clientViewAge || !clientViewGender || !clientViewCivilStatus || !clientViewEmail || !clientViewContact || !clientViewAddress || !clientViewProvince || !clientViewCity || !clientViewBarangay || !clientViewPageLink || !editModalEl || !editForm || !editFirstName || !editLastName || !editAge || !editGender || !editCivilStatus || !editContact || !editEmail || !editAddress || !editProvince || !editCity || !editBarangay || !editPhoto || !editName || !editTitle || !editOpenCameraBtn || !editCapturePhotoBtn || !editRetakePhotoBtn || !editCameraWrapper || !editCameraView || !editCameraCanvas || !editPhotoData || !editOpenFingerprintBtn || !editClearFingerprintBtn || !editFingerprintPreview || !editFingerprintData || !editFingerprintTemplate || !editFingerprintRemove || !editFingerprintStatus || !searchFingerprintBtn || !fingerprintSearchModalEl || !fingerprintSearchPreview || !fingerprintSearchStatus || !fingerprintScanAgainBtn || !clientKeywordInput || !clientSexFilter || !clientCivilStatusFilter || !clientCityFilter || !clientBarangayFilter || !clientRecordTypeFilter || !clientFiltersResetBtn || !clientFiltersToggleBtn || !clientFiltersBody || !clientDateFrom || !clientDateTo || !clientDateApplyBtn || !clientFiltersCountBadge || !clientSearchSummary || !clientSearchNoResultsRow) {
                return;
            }

            let editStream = null;
            const editDefaultFingerprint = editFingerprintPreview.src;
            let editHasFingerprint = false;
            let editOriginalFingerprintPreview = editDefaultFingerprint;
            let editFingerprintDataUrl = '';
            let editFingerprintTemplateXml = '';
            const fingerprintSearchModal = bootstrap.Modal.getOrCreateInstance(fingerprintSearchModalEl);
            const clientViewModal = bootstrap.Modal.getOrCreateInstance(clientViewModalEl);
            const clientRows = Array.from(document.querySelectorAll('[data-client-row]'));
            let filtersVisible = true;

            const setFiltersVisibility = (visible) => {
                filtersVisible = visible;
                clientFiltersBody.classList.toggle('d-none', !visible);
                clientFiltersToggleBtn.innerHTML = visible
                    ? 'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>'
                    : 'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
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
                    const searchableValue = row.dataset.searchAll || '';
                    const rowSex = (row.dataset.searchGender || '').toLowerCase();
                    const rowCivilStatus = (row.dataset.searchCivilStatus || '').toLowerCase();
                    const rowCity = (row.dataset.searchCity || '').toLowerCase();
                    const rowBarangay = (row.dataset.searchBarangay || '').toLowerCase();
                    const createdAt = row.dataset.searchCreatedAt || '';
                    const matchesSearch = !query || searchableValue.includes(query);
                    const matchesSex = !sex || rowSex === sex;
                    const matchesCivilStatus = !civilStatus || rowCivilStatus === civilStatus;
                    const matchesCity = !city || rowCity === city;
                    const matchesBarangay = !barangay || rowBarangay === barangay;
                    const matchesDate = (!dateFrom || createdAt >= dateFrom) && (!dateTo || createdAt <= dateTo);
                    const matches = matchesSearch && matchesSex && matchesCivilStatus && matchesCity && matchesBarangay && matchesDate;
                    row.classList.toggle('d-none', !matches);

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (clientSearchNoResultsRow) {
                    clientSearchNoResultsRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (clientSearchSummary) {
                    const activeFilters = [query, sex, civilStatus, city, barangay, dateFrom, dateTo].filter(Boolean).length;
                    if (!activeFilters) {
                        clientSearchSummary.textContent = 'Showing all clients.';
                    } else {
                        clientSearchSummary.textContent = `Showing ${visibleCount} matching client${visibleCount === 1 ? '' : 's'}.`;
                    }
                }

                if (clientFiltersCountBadge) {
                    clientFiltersCountBadge.textContent = activeFiltersText(visibleCount);
                }
            };

            const activeFiltersText = (visibleCount) => {
                const totalCount = clientRows.length;
                return totalCount === 0
                    ? 'Showing 0 clients'
                    : (visibleCount === totalCount
                        ? 'Showing all clients'
                        : `Showing ${visibleCount} of ${totalCount} clients`);
            };

            const stopEditCamera = () => {
                if (editStream) {
                    editStream.getTracks().forEach((track) => track.stop());
                    editStream = null;
                }
                editCameraView.srcObject = null;
                editCapturePhotoBtn.disabled = true;
                editRetakePhotoBtn.disabled = false;
                editCameraWrapper.classList.add('d-none');
            };

            const setEditFingerprintPreview = (imageData, statusText, templateXml = '') => {
                editFingerprintDataUrl = imageData || '';
                editFingerprintTemplateXml = templateXml || editFingerprintTemplateXml;
                editFingerprintData.value = editFingerprintDataUrl;
                editFingerprintTemplate.value = editFingerprintTemplateXml;
                editFingerprintRemove.value = '';
                editFingerprintPreview.src = editFingerprintDataUrl || editDefaultFingerprint;
                editFingerprintStatus.textContent = statusText || (editFingerprintDataUrl ? 'Fingerprint captured and ready to save.' : 'No fingerprint captured yet.');
                editClearFingerprintBtn.disabled = !editFingerprintDataUrl && !editHasFingerprint;
            };

            const clearEditFingerprintCapture = (markRemove = false) => {
                editFingerprintDataUrl = '';
                editFingerprintTemplateXml = '';
                editFingerprintData.value = '';
                editFingerprintTemplate.value = '';
                editFingerprintRemove.value = markRemove && editHasFingerprint ? '1' : '';
                editFingerprintPreview.src = markRemove ? "{{ asset('assets/images/avatar-1.jpg') }}" : editOriginalFingerprintPreview;
                editFingerprintStatus.textContent = markRemove
                    ? 'No fingerprint captured yet.'
                    : (editHasFingerprint ? 'Existing fingerprint on file.' : 'No fingerprint captured yet.');
                editClearFingerprintBtn.disabled = markRemove ? true : !editHasFingerprint;
            };

            const captureEditFingerprintFromBridge = async () => {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 45000);

                try
                {
                    const response = await fetch(`${fingerprintBridgeBase}/api/capture`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ source: 'laravel' }),
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

            const postFingerprintSearch = async (templateXml) => {
                const response = await fetch(clientListSearchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        fingerprint_template: templateXml
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
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };

            const showClientViewModal = (client) => {
                clientViewPhoto.src = client.photo_url || @json(asset('assets/images/avatar-1.jpg'));
                clientViewName.textContent = client.name || 'Client';
                clientViewAge.textContent = client.age ?? '-';
                clientViewGender.textContent = client.gender || '-';
                clientViewCivilStatus.textContent = client.civil_status || '-';
                clientViewEmail.textContent = client.email || '-';
                clientViewContact.textContent = client.contact || '-';
                clientViewAddress.textContent = client.address || '-';
                clientViewProvince.textContent = client.province || '-';
                clientViewCity.textContent = client.city || '-';
                clientViewBarangay.textContent = client.barangay || '-';
                clientViewPageLink.href = client.show_url || '#';
                clientViewModal.show();
            };

            const searchFingerprintAndHighlight = async () => {
                try {
                    const bridgeOnline = await isFingerprintBridgeOnline();
                    if (!bridgeOnline) {
                        throw new Error('DigitalPersona bridge is not running. Start the FingerprintBridge app first.');
                    }

                    fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                    const captureResult = await captureEditFingerprintFromBridge();
                    fingerprintSearchPreview.src = captureResult.imageDataUrl;
                    fingerprintSearchStatus.textContent = 'Searching for a matching client...';

                    const searchResult = await postFingerprintSearch(captureResult.fingerprintTemplateXml || '');
                    if (searchResult.matched && searchResult.client) {
                        fingerprintSearchStatus.textContent = `Match found: ${searchResult.client.name}`;
                        fingerprintScanAgainBtn.classList.add('d-none');
                        fingerprintSearchModal.hide();
                        window.location.href = searchResult.client.show_url;
                        return;
                    }

                    fingerprintSearchStatus.textContent = searchResult.message || 'No matching client found.';
                    fingerprintScanAgainBtn.classList.remove('d-none');
                } catch (error) {
                    fingerprintSearchStatus.textContent = 'Fingerprint search failed.';
                    fingerprintScanAgainBtn.classList.remove('d-none');
                }
            };

            const startEditCamera = async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Camera capture is not supported in this browser.');
                    return;
                }

                try {
                    editStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false
                    });

                    editCameraView.srcObject = editStream;
                    editCameraWrapper.classList.remove('d-none');
                    editCapturePhotoBtn.disabled = false;
                    editRetakePhotoBtn.disabled = true;
                } catch (error) {
                    alert('Unable to access the camera. Please allow camera permissions and try again.');
                }
            };

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                const photo = trigger.getAttribute('data-client-photo');
                const name = trigger.getAttribute('data-client-name') || 'Client Photo';

                modalImage.src = photo || '';
                modalTitle.textContent = name;
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                modalImage.src = '';
                modalTitle.textContent = 'Client Photo';
            });

            editModalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                editForm.action = trigger.getAttribute('data-update-url') || editForm.action;
                editTitle.textContent = `Edit ${trigger.getAttribute('data-client-name') || 'Client'}`;
                editName.textContent = trigger.getAttribute('data-client-name') || 'Client';
                editPhoto.src = trigger.getAttribute('data-client-photo') || '';
                editFirstName.value = trigger.getAttribute('data-first-name') || '';
                editMiddleName.value = trigger.getAttribute('data-middle-name') || '';
                editLastName.value = trigger.getAttribute('data-last-name') || '';
                editAge.value = trigger.getAttribute('data-age') || '';
                editGender.value = trigger.getAttribute('data-gender') || '';
                editCivilStatus.value = trigger.getAttribute('data-civil-status') || '';
                editContact.value = trigger.getAttribute('data-contact') || '';
                editEmail.value = trigger.getAttribute('data-email') || '';
                editAddress.value = trigger.getAttribute('data-address') || '';
                editProvince.value = trigger.getAttribute('data-province') || '';
                editCity.value = trigger.getAttribute('data-city') || '';
                editBarangay.value = trigger.getAttribute('data-barangay') || '';
                editPhoto.dataset.original = trigger.getAttribute('data-client-photo') || '';
                editPhotoData.value = '';
                editHasFingerprint = !!trigger.getAttribute('data-client-fingerprint');
                editOriginalFingerprintPreview = trigger.getAttribute('data-client-fingerprint') || editDefaultFingerprint;
                editFingerprintPreview.src = trigger.getAttribute('data-client-fingerprint') || editDefaultFingerprint;
                editFingerprintData.value = '';
                editFingerprintTemplate.value = '';
                editFingerprintRemove.value = '';
                editFingerprintDataUrl = '';
                editFingerprintTemplateXml = '';
                editFingerprintStatus.textContent = editHasFingerprint ? 'Existing fingerprint on file.' : 'No fingerprint captured yet.';
                editClearFingerprintBtn.disabled = !editHasFingerprint;
                editCameraWrapper.classList.add('d-none');
                editCapturePhotoBtn.disabled = true;
                editRetakePhotoBtn.disabled = true;
                editCameraView.srcObject = null;
            });

            editModalEl.addEventListener('hidden.bs.modal', function () {
                editForm.action = '';
                editTitle.textContent = 'Edit Client';
                editName.textContent = '';
                editPhoto.src = '';
                editPhotoData.value = '';
                editFingerprintPreview.src = editDefaultFingerprint;
                editOriginalFingerprintPreview = editDefaultFingerprint;
                editFingerprintData.value = '';
                editFingerprintTemplate.value = '';
                editFingerprintRemove.value = '';
                editFingerprintDataUrl = '';
                editFingerprintTemplateXml = '';
                editFingerprintStatus.textContent = 'No fingerprint captured yet.';
                editClearFingerprintBtn.disabled = true;
                editHasFingerprint = false;
                stopEditCamera();
                editForm.reset();
            });

            editOpenCameraBtn.addEventListener('click', function () {
                startEditCamera();
            });

            searchFingerprintBtn.addEventListener('click', function () {
                fingerprintSearchModal.show();
            });

            fingerprintScanAgainBtn.addEventListener('click', function () {
                fingerprintScanAgainBtn.classList.add('d-none');
                fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                searchFingerprintAndHighlight();
            });

            clientKeywordInput.addEventListener('input', filterClientList);
            clientSexFilter.addEventListener('change', filterClientList);
            clientCivilStatusFilter.addEventListener('change', filterClientList);
            clientCityFilter.addEventListener('change', filterClientList);
            clientBarangayFilter.addEventListener('change', filterClientList);
            clientRecordTypeFilter.addEventListener('change', filterClientList);
            clientDateFrom.addEventListener('change', filterClientList);
            clientDateTo.addEventListener('change', filterClientList);

            clientFiltersToggleBtn.addEventListener('click', function () {
                setFiltersVisibility(!filtersVisible);
            });

            clientFiltersResetBtn.addEventListener('click', function () {
                clientKeywordInput.value = '';
                clientSexFilter.value = '';
                clientCivilStatusFilter.value = '';
                clientCityFilter.value = '';
                clientBarangayFilter.value = '';
                clientRecordTypeFilter.value = 'all';
                clientDateFrom.value = '';
                clientDateTo.value = '';
                setFiltersVisibility(false);
                filterClientList();
                clientKeywordInput.focus();
            });

            clientDateApplyBtn.addEventListener('click', function () {
                filterClientList();
            });

            setFiltersVisibility(false);
            filterClientList();

            fingerprintSearchModalEl.addEventListener('shown.bs.modal', function () {
                fingerprintSearchPreview.src = @json(asset('assets/images/avatar-1.jpg'));
                fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                fingerprintScanAgainBtn.classList.add('d-none');
                searchFingerprintAndHighlight();
            });

            fingerprintSearchModalEl.addEventListener('hidden.bs.modal', function () {
            });

            clientViewModalEl.addEventListener('hidden.bs.modal', function () {
                clientViewPhoto.src = @json(asset('assets/images/avatar-1.jpg'));
                clientViewName.textContent = 'Client';
                clientViewAge.textContent = '-';
                clientViewGender.textContent = '-';
                clientViewCivilStatus.textContent = '-';
                clientViewEmail.textContent = '-';
                clientViewContact.textContent = '-';
                clientViewAddress.textContent = '-';
                clientViewProvince.textContent = '-';
                clientViewCity.textContent = '-';
                clientViewBarangay.textContent = '-';
                clientViewPageLink.href = '#';
            });

            editOpenFingerprintBtn.addEventListener('click', function () {
                (async () => {
                    try {
                        const bridgeOnline = await isFingerprintBridgeOnline();
                        if (!bridgeOnline) {
                            throw new Error('DigitalPersona bridge is not running. Start the FingerprintBridge app first.');
                        }

                        editFingerprintStatus.textContent = 'Place your finger on the scanner...';
                        const captureResult = await captureEditFingerprintFromBridge();
                        setEditFingerprintPreview(captureResult.imageDataUrl, 'Fingerprint captured from device. Save the client to keep it.', captureResult.fingerprintTemplateXml || '');
                    } catch (error) {
                        editFingerprintStatus.textContent = 'Scanner bridge is not available. Make sure the bridge app is running.';
                        alert(`Unable to capture from the scanner bridge.\n\n${error.message || error}`);
                    }
                })();
            });

            editClearFingerprintBtn.addEventListener('click', function () {
                clearEditFingerprintCapture(true);
            });

            editCapturePhotoBtn.addEventListener('click', function () {
                const context = editCameraCanvas.getContext('2d');
                editCameraCanvas.width = editCameraView.videoWidth || 200;
                editCameraCanvas.height = editCameraView.videoHeight || 200;
                context.save();
                context.translate(editCameraCanvas.width, 0);
                context.scale(-1, 1);
                context.drawImage(editCameraView, 0, 0, editCameraCanvas.width, editCameraCanvas.height);
                context.restore();

                const imageData = editCameraCanvas.toDataURL('image/png');
                editPhoto.src = imageData;
                editPhotoData.value = imageData;

                stopEditCamera();
                editRetakePhotoBtn.disabled = false;
            });

            editRetakePhotoBtn.addEventListener('click', function () {
                editPhotoData.value = '';
                editPhoto.src = editPhoto.dataset.original || editPhoto.src;
                editOpenCameraBtn.click();
            });

            const matchedClientId = new URLSearchParams(window.location.search).get('matched_client');
            if (matchedClientId) {
                highlightMatchedClient(matchedClientId);
            }
        });
    </script>
@endsection
