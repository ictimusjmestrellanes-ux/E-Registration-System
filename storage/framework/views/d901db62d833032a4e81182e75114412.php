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
                            $exactCount = $exactGroups->sum('total');
                            $likelyCount = $likelyGroups->sum('total');
                            $similarCount = $similarGroups->sum('total');
                            $totalGroups = $exactGroups->count() + $likelyGroups->count() + $similarGroups->count();
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
                                $out .= '<th>ID</th><th>Transaction ID</th><th>Full Name</th><th>Age</th><th>Birth Date</th><th>Contact No.</th><th>Category</th><th>Status</th>';
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
                                    $out .= '<td><span class="badge bg-success-subtle text-success"><i class="ri-check-line me-1"></i>Approved</span></td>';
                                    $out .= '</tr>';
                                }
                                $out .= '</tbody></table></div></div>';
                                return $out;
                            };
                        ?>

                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#rexact-tab" role="tab">
                                    Exact Match
                                    <span class="badge bg-danger-subtle text-danger ms-1"><?php echo e($exactGroups->count()); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#rlikely-tab" role="tab">
                                    Likely Match
                                    <span class="badge bg-warning-subtle text-warning ms-1"><?php echo e($likelyGroups->count()); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#rsimilar-tab" role="tab">
                                    Similar Spelling
                                    <span class="badge bg-info-subtle text-info ms-1"><?php echo e($similarGroups->count()); ?></span>
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
                                    <div class="small">Exact-same name and birth date. High confidence duplicates.</div>
                                </div>
                                <?php $__empty_1 = true; $__currentLoopData = $exactGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php echo $renderGroup($group); ?>

                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No exact duplicate records found.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="rlikely-tab" role="tabpanel">
                                <div class="alert alert-warning-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-alert-line fs-4 me-2"></i>
                                    <div class="small">Same name, birth date missing or year-only match. Review before acting.</div>
                                </div>
                                <?php $__empty_1 = true; $__currentLoopData = $likelyGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php echo $renderGroup($group); ?>

                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No likely duplicate records found.
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/transaction_events/recordsDuplicates.blade.php ENDPATH**/ ?>