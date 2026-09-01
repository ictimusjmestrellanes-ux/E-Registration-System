<?php $__env->startSection('title', 'ERS | Duplicate Clients Review'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $exactCount = $exactGroups->sum('total');
    $likelyCount = $likelyGroups->sum('total');
    $similarCount = $similarGroups->sum('total');
    $totalGroups = $exactGroups->count() + $likelyGroups->count() + $similarGroups->count();
    $totalDuplicates = $exactCount + $likelyCount + $similarCount;

    $renderGroup = function ($group) {
        $first = $group['clients']->first();
        $out = '<div class="border rounded-4 p-3 mb-3">';
        $out .= '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">';
        $out .= '<div>';
        $out .= '<span class="badge bg-danger-subtle text-danger ms-1">' . $group['total'] . ' records</span>';
        $out .= '</div>';
        $out .= '<a href="' . e(route('client.list', ['duplicate_names' => 1])) . '" class="btn btn-sm btn-outline-secondary">View in Client List</a>';
        $out .= '</div>';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-sm table-hover align-middle mb-0">';
        $out .= '<thead class="table-light"><tr>';
        $out .= '<th>Client ID</th><th>Photo</th><th>Name</th><th>Age</th><th>Birth Date</th><th>Gender</th><th>Contact</th><th>Address</th><th class="text-center">Actions</th>';
        $out .= '</tr></thead><tbody>';
        foreach ($group['clients'] as $client) {
            $out .= '<tr>';
            $out .= '<td class="fw-semibold">' . e($client->client_id) . '</td>';
            $out .= '<td><img src="' . e($client->photo_url) . '" alt="Photo" class="rounded avatar-sm object-fit-cover" onerror="this.onerror=null;this.src=\'' . e(asset('assets/images/profile.png')) . '\';"></td>';
            $out .= '<td>' . e($client->full_name) . '</td>';
            $out .= '<td>' . e($client->age ?? '-') . '</td>';
            $out .= '<td>' . e(optional($client->birth_date)->format('M d, Y') ?? '-') . '</td>';
            $out .= '<td>' . e($client->gender ?? '-') . '</td>';
            $out .= '<td>' . e($client->contact ?? '-') . '</td>';
            $out .= '<td class="small">' . e(collect([$client->address, $client->barangay, $client->city, $client->province])->filter()->implode(', ') ?: '-') . '</td>';
            $out .= '<td class="text-center"><a href="' . e(route('clients.show', $client)) . '" class="btn btn-sm btn-soft-info">View</a></td>';
            $out .= '</tr>';
        }
        $out .= '</tbody></table></div></div>';
        return $out;
    };
?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Duplicate Clients Review</h4>
                                <p class="text-muted mb-0">Review potential duplicate client records before taking action.</p>
                            </div>
                            <a href="<?php echo e(route('client.list')); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-arrow-left-line me-1"></i> Back to Client List
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
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#exact-tab" role="tab">
                                    Exact Match
                                    <span class="badge bg-danger-subtle text-danger ms-1"><?php echo e($exactGroups->count()); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#likely-tab" role="tab">
                                    Likely Match
                                    <span class="badge bg-warning-subtle text-warning ms-1"><?php echo e($likelyGroups->count()); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#similar-tab" role="tab">
                                    Similar Spelling
                                    <span class="badge bg-info-subtle text-info ms-1"><?php echo e($similarGroups->count()); ?></span>
                                </a>
                            </li>
                        </ul>
                        <div class="d-flex gap-2">
                                <span class="badge bg-primary-subtle text-primary fs-13"><?php echo e($totalGroups); ?> group(s)</span>
                                <span class="badge bg-danger-subtle text-danger fs-13"><?php echo e($totalDuplicates); ?> record(s)</span>
                            </div>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="exact-tab" role="tabpanel">
                                <div class="alert alert-danger-subtle alert-dismissible d-flex align-items-center mb-3 py-2" role="alert">
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

                            <div class="tab-pane fade" id="likely-tab" role="tabpanel">
                                <div class="alert alert-warning-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-alert-line fs-4 me-2"></i>
                                    <div class="small">Likely-same name, birth date missing or year-only match. Review before acting.</div>
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

                            <div class="tab-pane fade" id="similar-tab" role="tabpanel">
                                <div class="alert alert-info-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-information-line fs-4 me-2"></i>
                                    <div class="small">Possible-similar name spelling or typos (e.g. Maria/Marie, Iscober/Escobar) and format mismatches (e.g. "SURNAME, First" vs "First Last"). Verify before acting.</div>
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
<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\duplicates\index.blade.php ENDPATH**/ ?>