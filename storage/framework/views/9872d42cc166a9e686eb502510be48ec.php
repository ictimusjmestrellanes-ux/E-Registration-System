<?php $__env->startSection('title', 'Client List'); ?>
<?php $__env->startSection('content'); ?>
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

        #editClientModal .form-control,
        #editClientModal .form-select {
            text-transform: uppercase;
        }

        #editClientModal .form-control::placeholder {
            text-transform: uppercase;
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

        #clientListTable tbody tr[data-show-url] {
            cursor: pointer;
            transition: background-color 0.18s ease, box-shadow 0.18s ease;
        }

        #clientListTable tbody tr[data-show-url]:hover {
            background-color: rgba(77, 99, 214, 0.06);
        }

        #clientListTable .client-name-link {
            color: inherit;
        }

        #clientListTable .client-name-link:hover,
        #clientListTable .client-name-link:focus {
            color: #3551d5;
            text-decoration: underline;
        }

        html[data-bs-theme="dark"] #clientListTable tbody tr[data-show-url]:hover {
            background-color: rgba(255, 255, 255, 0.04);
        }

        html[data-bs-theme="dark"] #clientListTable .client-name-link:hover, 
        html[data-bs-theme="dark"] #clientListTable .client-name-link:focus {
            color: #b9c7ff; 
        }
    </style>
    <?php
        $defaultClientPhoto = asset('assets/images/profile.png');
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client List</h4>
                                <p class="text-muted mb-0">
                                    <?php echo e($matchedClientId ? 'Showing the matched client only.' : 'View all registered clients here.'); ?>

                                </p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if($matchedClientId): ?>
                                    <a href="<?php echo e(route('client.list')); ?>" class="btn btn-soft-secondary">Show All Clients</a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-soft-primary" id="searchFingerprintBtn">Search by
                                    Fingerprint</button>
                                <a href="<?php echo e(route('clients')); ?>" class="btn btn-primary">Add Client</a>
                            </div>
                        </div>

                        <?php if($errors->any()): ?>
                            <div class="alert alert-danger">
                                <div class="fw-semibold mb-1">Please fix the highlighted issue(s) below.</div>
                                <div><?php echo e($errors->first()); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if($matchedClientId): ?>
                            <div
                                class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div>Fingerprint search matched one client and the list is filtered to that result.</div>
                                <a href="<?php echo e(route('client.list')); ?>" class="btn btn-sm btn-outline-success">Clear Filter</a>
                            </div>
                        <?php endif; ?>

                        <?php
                            $clientCities = $clients->pluck('city')->filter()->unique()->sort()->values();
                            $clientBarangays = $clients->pluck('barangay')->filter()->unique()->sort()->values();
                            $clientCivilStatuses = $clients
                                ->pluck('civil_status')
                                ->filter()
                                ->unique()
                                ->sort()
                                ->values();
                        ?>

                        <div class="border rounded-4 p-3 mb-3" id="clientFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
                                <div>
                                    <div class="fw-bold fs-5">Filter Clients</div>
                                    <div class="text-muted small">Narrow records by keyword, sex, civil status, location,
                                        and created date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-outline-primary client-filters-toggle-btn"
                                        id="clientFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <button type="button" class="btn btn-soft-secondary"
                                        id="clientFiltersResetBtn">Reset</button>
                                    <span class="badge rounded-pill px-3 py-2" id="clientFiltersCountBadge">Showing all
                                        clients</span>
                                </div>
                            </div>

                            <div id="clientFiltersBody" class="d-none">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="clientKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="clientKeywordInput"
                                                placeholder="Name, email, contact, address">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientSexFilter"
                                            class="form-label fw-semibold text-uppercase small">Gender</label>
                                        <select class="form-select" id="clientSexFilter">
                                            <option value="">All Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientCivilStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Civil Status</label>
                                        <select class="form-select" id="clientCivilStatusFilter">
                                            <option value="">All civil statuses</option>
                                            <?php $__currentLoopData = $clientCivilStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $civilStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e(strtolower($civilStatus)); ?>"><?php echo e($civilStatus); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientCityFilter"
                                            class="form-label fw-semibold text-uppercase small">City</label>
                                        <select class="form-select" id="clientCityFilter">
                                            <option value="">All cities</option>
                                            <?php $__currentLoopData = $clientCities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $city): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e(strtolower($city)); ?>"><?php echo e($city); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="clientBarangayFilter"
                                            class="form-label fw-semibold text-uppercase small">Barangay</label>
                                        <select class="form-select" id="clientBarangayFilter">
                                            <option value="">All barangays</option>
                                            <?php $__currentLoopData = $clientBarangays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $barangay): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e(strtolower($barangay)); ?>"><?php echo e($barangay); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                                        <input type="date" class="form-control" id="clientDateFrom">
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <label for="clientDateTo" class="form-label fw-semibold text-uppercase small">Date
                                            To</label>
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
                            <table id="clientListTable" class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Client ID</th>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Suffix</th>
                                        <th>Gender</th>
                                        <th>Civil Status</th>
                                        <th>Contact 1</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center text-uppercase">
                                    <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $clientName = $client->full_name;
                                            $clientPhoto = $client->photo_url ?: $defaultClientPhoto;
                                        ?>
                                        <tr data-client-row="<?php echo e($client->id); ?>"
                                            data-show-url="<?php echo e(route('clients.show', $client)); ?>"
                                            data-client-photo="<?php echo e($clientPhoto); ?>"
                                            data-client-name="<?php echo e($client->full_name); ?>"
                                            data-client-suffix="<?php echo e($client->suffix ?? ''); ?>"
                                            data-client-birth-date="<?php echo e(optional($client->birth_date)->format('m/d/Y') ?? ''); ?>"
                                            data-client-age="<?php echo e($client->age ?? ''); ?>"
                                            data-client-gender="<?php echo e($client->gender ?? ''); ?>"
                                            data-client-civil-status="<?php echo e($client->civil_status ?? ''); ?>"
                                            data-client-email="<?php echo e($client->email ?? ''); ?>"
                                            data-client-contact="<?php echo e($client->contact ?? ''); ?>"
                                            data-client-contact-2="<?php echo e($client->contact_2 ?? ''); ?>"
                                            data-client-address="<?php echo e($client->address ?? ''); ?>"
                                            data-client-birthplace="<?php echo e($client->birthplace ?? ''); ?>"
                                            data-client-education="<?php echo e($client->education ?? ''); ?>"
                                            data-client-course="<?php echo e($client->course ?? ''); ?>"
                                            data-client-sector="<?php echo e($client->sector ?? ''); ?>"
                                            data-client-position-organization="<?php echo e($client->position_organization ?? ''); ?>"
                                            data-client-province="<?php echo e($client->province ?? ''); ?>"
                                            data-client-city="<?php echo e($client->city ?? ''); ?>"
                                            data-client-barangay="<?php echo e($client->barangay ?? ''); ?>" role="button"
                                            tabindex="0" title="Open <?php echo e($clientName); ?> details"
                                            data-search-name="<?php echo e(strtolower($clientName)); ?>"
                                            data-search-email="<?php echo e(strtolower($client->email ?? '')); ?>"
                                            data-search-contact="<?php echo e(strtolower($client->contact ?? '')); ?>"
                                            data-search-contact-2="<?php echo e(strtolower($client->contact_2 ?? '')); ?>"
                                            data-search-address="<?php echo e(strtolower($client->address ?? '')); ?>"
                                            data-search-birthplace="<?php echo e(strtolower($client->birthplace ?? '')); ?>"
                                            data-search-education="<?php echo e(strtolower($client->education ?? '')); ?>"
                                            data-search-course="<?php echo e(strtolower($client->course ?? '')); ?>"
                                            data-search-sector="<?php echo e(strtolower($client->sector ?? '')); ?>"
                                            data-search-position-organization="<?php echo e(strtolower($client->position_organization ?? '')); ?>"
                                            data-search-gender="<?php echo e(strtolower($client->gender ?? '')); ?>"
                                            data-search-civil-status="<?php echo e(strtolower($client->civil_status ?? '')); ?>"
                                            data-search-province="<?php echo e(strtolower($client->province ?? '')); ?>"
                                            data-search-city="<?php echo e(strtolower($client->city ?? '')); ?>"
                                            data-search-barangay="<?php echo e(strtolower($client->barangay ?? '')); ?>"
                                            data-search-created-at="<?php echo e(optional($client->created_at)->format('Y-m-d')); ?>"
                                            data-search-all="<?php echo e(strtolower($clientName . ' ' . ($client->suffix ?? '') . ' ' . ($client->email ?? '') . ' ' . ($client->contact ?? '') . ' ' . ($client->contact_2 ?? '') . ' ' . ($client->gender ?? '') . ' ' . ($client->civil_status ?? '') . ' ' . ($client->birthplace ?? '') . ' ' . ($client->education ?? '') . ' ' . ($client->course ?? '') . ' ' . ($client->sector ?? '') . ' ' . ($client->position_organization ?? '') . ' ' . ($client->address ?? '') . ' ' . ($client->province ?? '') . ' ' . ($client->city ?? '') . ' ' . ($client->barangay ?? ''))); ?>">
                                            <td><?php echo e($client->client_id ?? '-'); ?></td>
                                            <td>
                                                <button type="button" class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal" data-bs-target="#clientPhotoModal"
                                                    data-client-photo="<?php echo e($clientPhoto); ?>"
                                                    data-client-name="<?php echo e(trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name)); ?>">
                                                    <img src="<?php echo e($clientPhoto); ?>" alt="Client Photo"
                                                        onerror="this.onerror=null;this.src='<?php echo e($defaultClientPhoto); ?>';"
                                                        class="rounded-3 border object-fit-cover"
                                                        style="width: 72px; height: 72px;">
                                                </button>
                                            </td>
                                            <td>
                                                <?php echo e($client->full_name); ?>

                                            </td>
                                            <td><?php echo e($client->suffix ?? '-'); ?></td>
                                            <td><?php echo e($client->gender ?? '-'); ?></td>
                                            <td><?php echo e($client->civil_status ?? '-'); ?></td>
                                            <td><?php echo e($client->contact ?? '-'); ?></td>
                                            <td class="text-start">
                                                <div class="small lh-sm">
                                                    <div><?php echo e($client->address ?? '-'); ?></div>
                                                    <div class="text-muted">
                                                        <?php echo e(collect([$client->barangay, $client->city, $client->province])->filter()->implode(', ') ?:'-'); ?>

                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 text-center justify-content-center">
                                                    <a href="<?php echo e(route('clients.show', $client)); ?>"
                                                        class="btn btn-sm btn-soft-info">
                                                        View
                                                    </a>
                                                    <a href="<?php echo e(route('clients.edit', $client)); ?>"
                                                        class="btn btn-sm btn-soft-primary">
                                                        Edit
                                                    </a>
                                                    <form action="<?php echo e(route('clients.archive', $client)); ?>" method="POST"
                                                        onsubmit="return confirm('Are you sure you want to archive this client?');">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-sm btn-soft-warning">
                                                            Archive
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr id="clientSearchEmptyRow">
                                            <td colspan="10" class="text-center text-muted py-4">
                                                <?php echo e($matchedClientId ? 'No matching client found.' : 'No clients found.'); ?>

                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if($clients->count()): ?>
                                        <tr id="clientSearchNoResultsRow" class="d-none">
                                            <td colspan="10" class="text-center text-muted py-4">
                                                No matching clients found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <div class="modal fade" id="clientViewModal" tabindex="-1" aria-labelledby="clientViewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientViewModalLabel">Client Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-12 col-lg-4 text-center">
                            <img id="clientViewPhoto" src="<?php echo e(asset('assets/images/profile.png')); ?>" alt="Client Photo"
                                class="rounded-4 border object-fit-cover bg-light"
                                style="width: 100%; max-width: 320px; height: 320px;">
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <div class="text-muted small text-uppercase fw-semibold">Full Name</div>
                                    <div class="fs-4 fw-bold" id="clientViewName">Client</div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Suffix</div>
                                        <div class="fw-semibold" id="clientViewSuffix">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Birth Date</div>
                                        <div class="fw-semibold" id="clientViewBirthDate">-</div>
                                    </div>
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
                                        <div class="text-muted small text-uppercase fw-semibold">Contact 1</div>
                                        <div class="fw-semibold" id="clientViewContact">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small text-uppercase fw-semibold">Contact 2</div>
                                        <div class="fw-semibold" id="clientViewContact2">-</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="text-muted small text-uppercase fw-semibold">Address</div>
                                        <div class="fw-semibold" id="clientViewAddress">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small text-uppercase fw-semibold">Birthplace</div>
                                        <div class="fw-semibold" id="clientViewBirthplace">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small text-uppercase fw-semibold">Education</div>
                                        <div class="fw-semibold" id="clientViewEducation">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Course</div>
                                        <div class="fw-semibold" id="clientViewCourse">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Sector</div>
                                        <div class="fw-semibold" id="clientViewSector">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small text-uppercase fw-semibold">Position / Organization
                                        </div>
                                        <div class="fw-semibold" id="clientViewPositionOrganization">-</div>
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
                            <img id="fingerprintSearchPreview" src="<?php echo e(asset('assets/images/fingerprint.png')); ?>"
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
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('clientPhotoModal');
            const modalImage = document.getElementById('clientPhotoModalImage');
            const modalTitle = document.getElementById('clientPhotoModalLabel');
            const clientViewModalEl = document.getElementById('clientViewModal');
            const clientViewPhoto = document.getElementById('clientViewPhoto');
            const clientViewName = document.getElementById('clientViewName');
            const clientViewSuffix = document.getElementById('clientViewSuffix');
            const clientViewBirthDate = document.getElementById('clientViewBirthDate');
            const clientViewAge = document.getElementById('clientViewAge');
            const clientViewGender = document.getElementById('clientViewGender');
            const clientViewCivilStatus = document.getElementById('clientViewCivilStatus');
            const clientViewEmail = document.getElementById('clientViewEmail');
            const clientViewContact = document.getElementById('clientViewContact');
            const clientViewContact2 = document.getElementById('clientViewContact2');
            const clientViewAddress = document.getElementById('clientViewAddress');
            const clientViewBirthplace = document.getElementById('clientViewBirthplace');
            const clientViewEducation = document.getElementById('clientViewEducation');
            const clientViewCourse = document.getElementById('clientViewCourse');
            const clientViewSector = document.getElementById('clientViewSector');
            const clientViewPositionOrganization = document.getElementById('clientViewPositionOrganization');
            const clientViewProvince = document.getElementById('clientViewProvince');
            const clientViewCity = document.getElementById('clientViewCity');
            const clientViewBarangay = document.getElementById('clientViewBarangay');
            const clientViewPageLink = document.getElementById('clientViewPageLink');
            const fingerprintPlaceholderPreview = <?php echo json_encode(asset('assets/images/fingerprint.png'), 15, 512) ?>;
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
            const clientListSearchUrl = <?php echo json_encode(route('client.search.fingerprint'), 15, 512) ?>;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!modalEl || !modalImage || !modalTitle || !clientViewModalEl || !clientViewPhoto || !
                clientViewName || !clientViewSuffix || !clientViewBirthDate || !clientViewAge || !
                clientViewGender || !clientViewCivilStatus || !clientViewEmail || !clientViewContact || !
                clientViewContact2 || !clientViewAddress || !clientViewBirthplace || !clientViewEducation || !
                clientViewCourse || !clientViewSector || !clientViewPositionOrganization || !clientViewProvince || !
                clientViewCity || !clientViewBarangay || !clientViewPageLink || !searchFingerprintBtn || !
                fingerprintSearchModalEl || !fingerprintSearchPreview || !fingerprintSearchStatus || !
                fingerprintScanAgainBtn || !clientKeywordInput || !clientSexFilter || !clientCivilStatusFilter || !
                clientCityFilter || !clientBarangayFilter || !clientRecordTypeFilter || !clientFiltersResetBtn || !
                clientFiltersToggleBtn || !clientFiltersBody || !clientDateFrom || !clientDateTo || !
                clientDateApplyBtn || !clientFiltersCountBadge || !clientSearchSummary || !clientSearchNoResultsRow
            ) {
                return;
            }


            const fingerprintSearchModal = bootstrap.Modal.getOrCreateInstance(fingerprintSearchModalEl);
            const clientViewModal = bootstrap.Modal.getOrCreateInstance(clientViewModalEl);
            const clientRows = Array.from(document.querySelectorAll('[data-client-row]'));
            const clientRowInteractiveSelector = 'a, button, input, select, textarea, label, form';
            let filtersVisible = true;


            const setFiltersVisibility = (visible) => {
                filtersVisible = visible;
                clientFiltersBody.classList.toggle('d-none', !visible);
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
                    const matchesDate = (!dateFrom || createdAt >= dateFrom) && (!dateTo || createdAt <=
                        dateTo);
                    const matches = matchesSearch && matchesSex && matchesCivilStatus && matchesCity &&
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
                row.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            };

            const buildClientViewPayloadFromRow = (row) => ({
                photo_url: row?.dataset.clientPhoto || <?php echo json_encode(asset('assets/images/profile.png'), 15, 512) ?>,
                name: row?.dataset.clientName || 'Client',
                suffix: row?.dataset.clientSuffix || '-',
                birth_date: row?.dataset.clientBirthDate || '-',
                age: row?.dataset.clientAge || '-',
                gender: row?.dataset.clientGender || '-',
                civil_status: row?.dataset.clientCivilStatus || '-',
                email: row?.dataset.clientEmail || '-',
                contact: row?.dataset.clientContact || '-',
                contact_2: row?.dataset.clientContact2 || '-',
                address: row?.dataset.clientAddress || '-',
                birthplace: row?.dataset.clientBirthplace || '-',
                education: row?.dataset.clientEducation || '-',
                course: row?.dataset.clientCourse || '-',
                sector: row?.dataset.clientSector || '-',
                position_organization: row?.dataset.clientPositionOrganization || '-',
                province: row?.dataset.clientProvince || '-',
                city: row?.dataset.clientCity || '-',
                barangay: row?.dataset.clientBarangay || '-',
                show_url: row?.dataset.showUrl || '#',
            });

            const showClientViewModal = (client) => {
                clientViewPhoto.src = client.photo_url || <?php echo json_encode(asset('assets/images/profile.png'), 15, 512) ?>;
                clientViewName.textContent = client.name || 'Client';
                clientViewSuffix.textContent = client.suffix || '-';
                clientViewBirthDate.textContent = client.birth_date || '-';
                clientViewAge.textContent = client.age ?? '-';
                clientViewGender.textContent = client.gender || '-';
                clientViewCivilStatus.textContent = client.civil_status || '-';
                clientViewEmail.textContent = client.email || '-';
                clientViewContact.textContent = client.contact || '-';
                clientViewContact2.textContent = client.contact_2 || '-';
                clientViewAddress.textContent = client.address || '-';
                clientViewBirthplace.textContent = client.birthplace || '-';
                clientViewEducation.textContent = client.education || '-';
                clientViewCourse.textContent = client.course || '-';
                clientViewSector.textContent = client.sector || '-';
                clientViewPositionOrganization.textContent = client.position_organization || '-';
                clientViewProvince.textContent = client.province || '-';
                clientViewCity.textContent = client.city || '-';
                clientViewBarangay.textContent = client.barangay || '-';
                clientViewPageLink.href = client.show_url || '#';
                clientViewModal.show();
            };

            const openClientDetailsFromRow = (row) => {
                showClientViewModal(buildClientViewPayloadFromRow(row));
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

                    const searchResult = await postFingerprintSearch(captureResult.fingerprintTemplateXml ||
                        '');
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

            clientRows.forEach((row) => {
                const nameButton = row.querySelector('.client-name-link');

                row.addEventListener('click', function(event) {
                    if (event.target.closest(clientRowInteractiveSelector)) {
                        return;
                    }

                    openClientDetailsFromRow(row);
                });

                row.addEventListener('keydown', function(event) {
                    if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) {
                        return;
                    }

                    event.preventDefault();
                    openClientDetailsFromRow(row);
                });

                if (nameButton) {
                    nameButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        openClientDetailsFromRow(row);
                    });
                }
            });

            searchFingerprintBtn.addEventListener('click', function() {
                fingerprintSearchModal.show();
            });

            fingerprintScanAgainBtn.addEventListener('click', function() {
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

            clientFiltersToggleBtn.addEventListener('click', function() {
                setFiltersVisibility(!filtersVisible);
            });

            clientFiltersResetBtn.addEventListener('click', function() {
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

            clientDateApplyBtn.addEventListener('click', function() {
                filterClientList();
            });

            setFiltersVisibility(false);
            filterClientList();

            fingerprintSearchModalEl.addEventListener('shown.bs.modal', function() {
                fingerprintSearchPreview.src = fingerprintPlaceholderPreview;
                fingerprintSearchStatus.textContent = 'Place your finger on the scanner...';
                fingerprintScanAgainBtn.classList.add('d-none');
                searchFingerprintAndHighlight();
            });

            clientViewModalEl.addEventListener('hidden.bs.modal', function() {
                clientViewPhoto.src = <?php echo json_encode(asset('assets/images/profile.png'), 15, 512) ?>;
                clientViewName.textContent = 'Client';
                clientViewSuffix.textContent = '-';
                clientViewBirthDate.textContent = '-';
                clientViewAge.textContent = '-';
                clientViewGender.textContent = '-';
                clientViewCivilStatus.textContent = '-';
                clientViewEmail.textContent = '-';
                clientViewContact.textContent = '-';
                clientViewContact2.textContent = '-';
                clientViewAddress.textContent = '-';
                clientViewBirthplace.textContent = '-';
                clientViewEducation.textContent = '-';
                clientViewCourse.textContent = '-';
                clientViewSector.textContent = '-';
                clientViewPositionOrganization.textContent = '-';
                clientViewProvince.textContent = '-';
                clientViewCity.textContent = '-';
                clientViewBarangay.textContent = '-';
                clientViewPageLink.href = '#';
            });

            const matchedClientId = new URLSearchParams(window.location.search).get('matched_client');
            if (matchedClientId) {
                highlightMatchedClient(matchedClientId);
            }
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\clientList.blade.php ENDPATH**/ ?>