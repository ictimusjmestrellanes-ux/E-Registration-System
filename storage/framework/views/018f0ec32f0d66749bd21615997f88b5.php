<?php $__env->startSection('title', 'ERS | Events - Duplicate Records'); ?>

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Duplicate Events Records</h4>
                                <p class="text-muted mb-0">Review transferred records that may be duplicates.</p>
                            </div>
                            <a href="<?php echo e(route('transaction-events.records')); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-arrow-left-line me-1"></i> Back to Event Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php
                            $exactCount = $exactRecordsTotal ?? $exactGroups->sum('total');
                            $likelyCount = $likelyRecordsTotal ?? $likelyGroups->sum('total');
                            $similarCount = $similarRecordsTotal ?? $similarGroups->sum('total');
                            $totalGroups = ($exactGroupsTotal ?? $exactGroups->count()) + ($likelyGroupsTotal ?? $likelyGroups->count()) + ($similarGroupsTotal ?? $similarGroups->count());
                            $totalDuplicates = $exactCount + $likelyCount + $similarCount;

                            $renderGroup = function ($group) {
                                $first = $group['events']->first();
                                $out = '<div class="border rounded-4 p-3 mb-3">';
                                $out .= '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">';
                                $out .= '<div>';
                                $out .= '<h6 class="mb-0">' . e($first->full_name);
                                $out .= ' <span class="badge bg-danger-subtle text-danger ms-1">' . $group['total'] . ' records</span></h6>';
                                $out .= '<p class="text-muted small mb-0">Birth date: ' . e(optional($first->birth_date)->format('M d, Y') ?? '-') . '</p>';
                                $out .= '</div>';
                                $out .= '</div>';
                                $out .= '<div class="table-responsive">';
                                $out .= '<table class="table table-sm table-hover align-middle mb-0">';
                                $out .= '<thead class="table-light"><tr>';
                                $out .= '<th>ID</th><th>Transaction ID</th><th>Full Name</th><th>Age</th><th>Birth Date</th><th>Contact No.</th><th>Category</th><th>Transaction Category</th><th>Transaction Type</th><th>Event Date</th><th>Status</th>';
                                if (auth()->user()?->role_name !== 'Viewer') {
                                    $out .= '<th class="text-center">Action</th>';
                                }
                                $out .= '</tr></thead><tbody>';
                                foreach ($group['events'] as $event) {
                                    $txId = $event->transferredTransaction?->transaction_id ?? '-';
                                    $out .= '<tr>';
                                    $out .= '<td>' . e($event->id) . '</td>';
                                    $out .= '<td class="fw-semibold">' . e($txId) . '</td>';
                                    $out .= '<td class="fw-semibold">' . e($event->full_name) . '</td>';
                                    $out .= '<td>' . e($event->age ?? '-') . '</td>';
                                    $out .= '<td>' . e(optional($event->birth_date)->format('M d, Y') ?? '-') . '</td>';
                                    $out .= '<td>' . e(str_replace('-', '', $event->contact_no ?? '') ?: '-') . '</td>';
                                    $out .= '<td class="small">' . e($event->client_category ?? '-') . '</td>';
                                    $out .= '<td class="small">' . e($event->transaction_category ?? '-') . '</td>';
                                    $out .= '<td class="small">' . e($event->transaction_type ?? '-') . '</td>';
                                    $out .= '<td>' . e(optional($event->event_date)->format('M d, Y') ?? '-') . '</td>';
                                    $out .= '<td><span class="badge bg-success-subtle text-success"><i class="ri-check-line me-1"></i>Approved</span></td>';
                                    if (auth()->user()?->role_name !== 'Viewer') {
                                        $out .= '<td class="text-center text-nowrap">';
                                        $out .= '<form action="' . e(route('transaction-events.undo-transfer', $event)) . '" method="POST" class="d-inline m-0">';
                                        $out .= csrf_field();
                                        if (feature_allowed('Undo Transfer')) {
                                            $out .= '<button type="submit" class="btn btn-sm btn-soft-warning" onclick="return confirm(\'Undo transfer for event #' . $event->id . ' (' . e($event->full_name) . ')? The created transaction record will be removed and this event will return to pending. The client record will remain.\');" title="Undo transfer"><i class="ri-arrow-go-back-line me-1"></i> Undo Transfer</button>';
                                        } else {
                                            $out .= '<button type="button" class="btn btn-sm btn-soft-warning" disabled title="Feature not allowed"><i class="ri-arrow-go-back-line me-1"></i>Not Allowed to Undo Transfer</button>';
                                        }
                                        $out .= '</form>';
                                        $out .= '</td>';
                                    }
                                    $out .= '</tr>';
                                }
                                $out .= '</tbody></table></div></div>';
                                return $out;
                            };

                        ?>

                        <div class="border rounded-4 p-3 mb-4" id="dupFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-0">
                                <div>
                                    <div class="fw-bold fs-5">Filter Duplicates</div>
                                    <div class="text-muted small">Narrow groups by keyword, category, type, and event
                                        date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="dupFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="<?php echo e(route('transaction-events.records-duplicates')); ?>"
                                        class="btn btn-sm btn-soft-secondary">Reset</a>
                                    <select class="form-select form-select-sm w-auto" id="dupPerPageSelect"
                                        aria-label="Groups per page" title="Groups per page">
                                        <?php $__currentLoopData = [10, 15, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($size); ?>"
                                                <?php echo e(($perPage ?? 25) == $size ? 'selected' : ''); ?>>
                                                <?php echo e($size); ?> / page</option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                            </div>

                            <form method="GET" id="dupFiltersForm"
                                class="mt-3 <?php echo e(request()->anyFilled(['search', 'client_category', 'transaction_category', 'transaction_type', 'date_from', 'date_to']) ? '' : 'd-none'); ?>">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="dupKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="dupKeywordInput" name="search"
                                                placeholder="Name, category, type, date, transaction ID..."
                                                value="<?php echo e(request('search')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupClientCategoryFilter"
                                            class="form-label fw-semibold text-uppercase small">Client Category</label>
                                        <select class="form-select" id="dupClientCategoryFilter" name="client_category">
                                            <option value="">All Client Categories</option>
                                            <?php $__currentLoopData = ($filterClientCategories ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clientCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($clientCategory); ?>"
                                                    <?php echo e(strtolower(request('client_category', '')) === strtolower($clientCategory) ? 'selected' : ''); ?>>
                                                    <?php echo e($clientCategory); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupTransactionCategoryFilter"
                                            class="form-label fw-semibold text-uppercase small">Transaction Category</label>
                                        <select class="form-select" id="dupTransactionCategoryFilter" name="transaction_category">
                                            <option value="">All Categories</option>
                                            <?php $__currentLoopData = ($filterTransactionCategories ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transactionCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($transactionCategory); ?>"
                                                    <?php echo e(strtolower(request('transaction_category', '')) === strtolower($transactionCategory) ? 'selected' : ''); ?>>
                                                    <?php echo e($transactionCategory); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupTransactionTypeFilter"
                                            class="form-label fw-semibold text-uppercase small">Transaction Type</label>
                                        <select class="form-select" id="dupTransactionTypeFilter" name="transaction_type">
                                            <option value="">All Types</option>
                                            <?php $__currentLoopData = ($filterTransactionTypes ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transactionType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($transactionType); ?>"
                                                    <?php echo e(strtolower(request('transaction_type', '')) === strtolower($transactionType) ? 'selected' : ''); ?>>
                                                    <?php echo e($transactionType); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="dupDateFrom" name="date_from"
                                            value="<?php echo e(request('date_from')); ?>">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupDateTo"
                                            class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="dupDateTo" name="date_to"
                                            value="<?php echo e(request('date_to')); ?>">
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
                                    <?php echo e(request()->anyFilled(['search', 'client_category', 'transaction_category', 'transaction_type', 'date_from', 'date_to']) ? 'Filtered groups are shown below.' : 'Showing all duplicate groups.'); ?>

                                </div>
                            </form>
                        </div>

                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#rexact-tab" role="tab">
                                    Exact Match
                                    <span class="badge bg-danger-subtle text-danger ms-1"><?php echo e($exactGroupsTotal ?? $exactGroups->count()); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#rlikely-tab" role="tab">
                                    Likely Match
                                    <span class="badge bg-warning-subtle text-warning ms-1"><?php echo e($likelyGroupsTotal ?? $likelyGroups->count()); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#rsimilar-tab" role="tab">
                                    Similar Spelling
                                    <span class="badge bg-info-subtle text-info ms-1"><?php echo e($similarGroupsTotal ?? $similarGroups->count()); ?></span>
                                </a>
                            </li>
                        </ul>

                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <span class="badge bg-primary-subtle text-primary fs-13"><?php echo e($totalGroups); ?> group(s)</span>
                            <span class="badge bg-danger-subtle text-danger fs-13"><?php echo e($totalDuplicates); ?> record(s)</span>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="rexact-tab" role="tabpanel">
                                <div class="alert alert-danger-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-error-warning-line fs-4 me-2"></i>
                                    <div class="small">Same <strong>Full Name</strong>, <strong>Client Category</strong>, <strong>Transaction Category</strong>, <strong>Transaction Type</strong>, and <strong>Event Date</strong>. High confidence duplicates.</div>
                                </div>
                                <?php $__empty_1 = true; $__currentLoopData = $exactGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php echo $renderGroup($group); ?>

                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No exact duplicate records found.
                                    </div>
                                <?php endif; ?>
                                <?php if($exactGroups->total() > 0): ?>
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing <?php echo e($exactGroups->firstItem()); ?>–<?php echo e($exactGroups->lastItem()); ?> of <?php echo e($exactGroups->total()); ?> groups</div>
                                        <?php echo e($exactGroups->links('pagination::bootstrap-5')); ?>

                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="rlikely-tab" role="tabpanel">
                                <div class="alert alert-warning-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-alert-line fs-4 me-2"></i>
                                    <div class="small">
                                        Same <strong>Full Name</strong> plus at least one of:
                                        Event Date + Transaction Category, Event Date + Transaction Type, Transaction Category + Transaction Type, Event Date only, Transaction Type only, or Transaction Category only.
                                        Review before acting.
                                    </div>
                                </div>
                                <?php $__empty_1 = true; $__currentLoopData = $likelyGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php echo $renderGroup($group); ?>

                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No likely duplicate records found.
                                    </div>
                                <?php endif; ?>
                                <?php if($likelyGroups->total() > 0): ?>
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing <?php echo e($likelyGroups->firstItem()); ?>–<?php echo e($likelyGroups->lastItem()); ?> of <?php echo e($likelyGroups->total()); ?> groups</div>
                                        <?php echo e($likelyGroups->links('pagination::bootstrap-5')); ?>

                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="rsimilar-tab" role="tabpanel">
                                <div class="alert alert-info-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-information-line fs-4 me-2"></i>
                                    <div class="small">Phonetically similar names (e.g., Iscober/Escobar). Verify before acting.</div>
                                </div>
                                <?php $__empty_1 = true; $__currentLoopData = $similarGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php echo $renderGroup($group); ?>

                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No similar-spelling records found.
                                    </div>
                                <?php endif; ?>
                                <?php if($similarGroups->total() > 0): ?>
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing <?php echo e($similarGroups->firstItem()); ?>–<?php echo e($similarGroups->lastItem()); ?> of <?php echo e($similarGroups->total()); ?> groups</div>
                                        <?php echo e($similarGroups->links('pagination::bootstrap-5')); ?>

                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                ['exact_page', 'likely_page', 'similar_page', 'page'].forEach((k) => url.searchParams.delete(k));
                window.location.href = url.toString();
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\transaction_events\recordsDuplicates.blade.php ENDPATH**/ ?>