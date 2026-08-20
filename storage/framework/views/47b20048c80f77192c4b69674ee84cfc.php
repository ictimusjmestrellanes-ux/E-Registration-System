<?php $__env->startSection('title', 'ERS | Transaction Events'); ?>

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="mb-4">
                    <h4 class="mb-1 fw-semibold">Events</h4>
                    <p class="text-muted mb-0">Manage and import data events.</p>
                </div>
            </div>
        </div>

        <?php if(session('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e(session('success')); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo e(session('error')); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if($errors->any()): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div><?php echo e($error); ?></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Event List</h5>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?php echo e(route('transaction-events.duplicate-review')); ?>"
                                class="btn btn-sm <?php echo e($totalDuplicateGroups ? 'btn-warning' : 'btn-outline-warning'); ?>">
                                <i class="ri-file-copy-2-line me-1"></i> Duplicate Names
                                <?php if($totalDuplicateGroups): ?>
                                    <span class="badge bg-danger text-white ms-1"><?php echo e($totalDuplicateGroups); ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (! (auth()->user()?->role_name === 'Viewer')): ?>
                                    <button type="button" class="btn btn-success btn-sm" id="bulkTransferBtn" disabled>
                                        <i class="ri-exchange-box-line me-1"></i> Transfer Selected
                                    </button>
                                <?php endif; ?>
                            </div>

                            <form id="bulkTransferForm" action="<?php echo e(route('transaction-events.transfer-selected')); ?>"
                                method="POST" class="d-none">
                                <?php echo csrf_field(); ?>
                            </form>
                            <a href="<?php echo e(route('transaction-events.archives')); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-archive-line me-1"></i> View Archives
                            </a>
                            <?php if (! (auth()->user()?->role_name === 'Viewer')): ?>
                                <a href="<?php echo e(route('transaction-events.template')); ?>"
                                    class="btn btn-soft-primary btn-sm">
                                    <i class="ri-download-2-line me-1"></i> Excel Template
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#importModal">
                                    <i class="ri-upload-2-line me-1"></i> Import CSV
                                </button>
                            <?php endif; ?>
                            <span class="badge bg-primary-subtle text-primary px-4 py-2"><?php echo e($events->total()); ?> total</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" name="search"
                                    placeholder="Search name..." value="<?php echo e(request('search')); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" name="contact"
                                    placeholder="Search contact..." value="<?php echo e(request('contact')); ?>">
                            </div>
                            <div class="col-md-1">
                                <input type="number" class="form-control form-control-sm" name="age_from"
                                    placeholder="Age from" value="<?php echo e(request('age_from')); ?>">
                            </div>
                            <div class="col-md-1">
                                <input type="number" class="form-control form-control-sm" name="age_to"
                                    placeholder="Age to" value="<?php echo e(request('age_to')); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" name="date_from"
                                    value="<?php echo e(request('date_from')); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" name="date_to"
                                    value="<?php echo e(request('date_to')); ?>">
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                    <i class="ri-search-line"></i>
                                </button>
                                <?php if(request()->hasAny(['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to', 'duplicate_names'])): ?>
                                    <a href="<?php echo e(route('transaction-events.index')); ?>"
                                        class="btn btn-light btn-sm flex-fill">
                                        <i class="ri-close-line"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <?php if(request()->boolean('duplicate_names')): ?>
                            <div class="alert alert-warning py-2 mb-3">
                                Showing transaction events with duplicate names.
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 120px;" class="text-center">
                                            <div
                                                class="d-inline-flex align-items-center justify-content-center gap-2 text-nowrap">
                                                <?php if (! (auth()->user()?->role_name === 'Viewer')): ?>
                                                    <input type="checkbox" class="form-check-input"
                                                        id="selectAllTransactionEvents"
                                                        aria-label="Select all transaction events on this page">
                                                    <span>Select all</span>
                                                <?php endif; ?>
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
                                    <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $isTransferred = !is_null($event->transferred_at);
                                        ?>
                                        <tr class="<?php echo e($isTransferred ? 'table-secondary text-muted' : ''); ?>">
                                            <td class="text-center">
                                                <?php if (! (auth()->user()?->role_name === 'Viewer')): ?>
                                                    <input type="checkbox" class="form-check-input transaction-event-checkbox"
                                                        value="<?php echo e($event->id); ?>"
                                                        aria-label="Select transaction event <?php echo e($event->id); ?>"
                                                        <?php echo e($isTransferred ? 'disabled' : ''); ?>

                                                        <?php echo e(in_array($event->full_name, $duplicateFullNames, true) ? 'data-duplicate="1"' : ''); ?>>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold"><?php echo e($event->full_name); ?></td>
                                            <td><?php echo e($event->age ?? '-'); ?></td>
                                            <td><?php echo e($event->birth_date ? $event->birth_date->format('M d, Y') : '-'); ?></td>
                                            <td><?php echo e(str_replace('-', '', $event->contact_no)); ?></td>
                                            <td><?php echo e($event->address ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->client_category ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->transaction_category ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->transaction_type ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->created_at?->format('M d, Y')); ?></td>
                                            <td class="text-center">
                                                <?php if($isTransferred): ?>
                                                    <span class="badge bg-success-subtle text-success px-3 py-2">
                                                        <i class="ri-check-line me-1"></i>Approved
                                                    </span>
                                                <?php elseif(auth()->user()?->role_name === 'Viewer'): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                                                        <i class="ri-time-line me-1"></i>Pending
                                                    </span>
                                                <?php else: ?>
                                                    <form action="<?php echo e(route('transaction-events.transfer', $event)); ?>"
                                                        method="POST" class="transaction-transfer-form"
                                                        data-event-name="<?php echo e($event->full_name); ?>">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-sm btn-soft-success"
                                                            <?php echo e(empty($event->transaction_category) && empty($event->transaction_type) ? 'disabled' : ''); ?>

                                                            title="<?php echo e(empty($event->transaction_category) && empty($event->transaction_type) ? 'No transaction category or type to transfer' : 'Transfer to transaction'); ?>">
                                                            <i class="ri-exchange-line"></i> Transfer
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-5">
                                                No transaction events found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <?php echo e($events->links('pagination::bootstrap-5')); ?>

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
        <form id="importForm" action="<?php echo e(route('transaction-events.import')); ?>" method="POST"
            enctype="multipart/form-data" class="d-none">
            <?php echo csrf_field(); ?>
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
                            <a href="<?php echo e(route('transaction-events.template')); ?>" class="alert-link mt-1 d-inline-block">
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
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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

            if (selectAll) {
                const selectableCheckboxes = () => eventCheckboxes.filter((checkbox) => !checkbox.dataset.duplicate && !checkbox.disabled);

                const syncSelectAllState = () => {
                    const selectable = selectableCheckboxes();
                    const checkedCount = selectable.filter((checkbox) => checkbox.checked).length;
                    selectAll.checked = selectable.length > 0 && checkedCount === selectable.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < selectable.length;
                    selectAll.disabled = selectable.length === 0;
                    if (bulkTransferBtn) {
                        bulkTransferBtn.disabled = checkedCount === 0;
                    }
                };

                selectAll.addEventListener('change', function() {
                    selectableCheckboxes().forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                    syncSelectAllState();
                });

                eventCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', syncSelectAllState);
                });

                syncSelectAllState();
            }

            if (bulkTransferBtn && bulkTransferForm && bulkTransferConfirmModalEl && confirmBulkTransferBtn) {
                const bulkTransferConfirmModal = bootstrap.Modal.getOrCreateInstance(bulkTransferConfirmModalEl);

                bulkTransferBtn.addEventListener('click', function() {
                    selectedBulkTransferIds = eventCheckboxes
                        .filter((checkbox) => checkbox.checked)
                        .map((checkbox) => checkbox.value);

                    if (selectedBulkTransferIds.length === 0) {
                        return;
                    }

                    if (bulkTransferCount) {
                        bulkTransferCount.textContent = selectedBulkTransferIds.length;
                    }

                    bulkTransferConfirmModal.show();
                });

                confirmBulkTransferBtn.addEventListener('click', function() {
                    if (selectedBulkTransferIds.length === 0) {
                        return;
                    }

                    confirmBulkTransferBtn.disabled = true;
                    bulkTransferForm.querySelectorAll('input[name="event_ids[]"]').forEach((input) => input
                        .remove());
                    selectedBulkTransferIds.forEach((id) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'event_ids[]';
                        input.value = id;
                        bulkTransferForm.appendChild(input);
                    });

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
                    const response = await fetch('<?php echo e(route('transaction-events.preview')); ?>', {
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

            confirmBtn.addEventListener('click', function() {
                if (csvFileHidden.files.length === 0) {
                    return;
                }
                importForm.submit();
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/transaction_events/transactionEvents.blade.php ENDPATH**/ ?>