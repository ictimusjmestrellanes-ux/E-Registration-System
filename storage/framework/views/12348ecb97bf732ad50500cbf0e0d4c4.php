<?php $__env->startSection('title', 'ERS | Events - Removed Duplicates'); ?>

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1 fw-semibold">Events - Removed Duplicates</h4>
                        <p class="text-muted mb-0">List of transaction events removed during duplicate review.</p>
                    </div>
                    <a href="<?php echo e(route('transaction-events.duplicate-review')); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i> Back to Duplicate Review
                    </a>
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Birth Date</th>
                                        <th>Contact No.</th>
                                        <th>Address</th>
                                        <th>Category</th>
                                        <th style="width: 160px;">Removed At</th>
                                        <?php if(auth()->user()?->role_name !== 'Viewer'): ?>
                                            <th style="width: 140px;" class="text-center">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><?php echo e($event->id); ?></td>
                                            <td class="fw-semibold"><?php echo e($event->full_name); ?></td>
                                            <td><?php echo e(optional($event->birth_date)->format('M d, Y') ?? '-'); ?></td>
                                            <td><?php echo e(str_replace('-', '', $event->contact_no ?? '') ?: '-'); ?></td>
                                            <td class="small"><?php echo e($event->address ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->client_category ?? '-'); ?></td>
                                            <td><?php echo e(optional($event->updated_at)->timezone('Asia/Manila')->format('M d, Y H:i:s')); ?>

                                            </td>
                                            <?php if(auth()->user()?->role_name !== 'Viewer'): ?>
                                                <td class="text-center">
                                                    <form
                                                        action="<?php echo e(route('transaction-events.reset-duplicate', $event)); ?>"
                                                        method="POST" class="m-0">
                                                        <?php echo csrf_field(); ?>
                                                        <?php if(feature_allowed('Reset Duplicate Review')): ?>
                                                            <button type="submit" class="btn btn-sm btn-soft-warning"
                                                                onclick="return confirm('Restore this event back to duplicate review?');">
                                                                <i class="ri-arrow-go-back-line me-1"></i> Undo Duplicate
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-soft-warning"
                                                                disabled>
                                                                <i class="ri-arrow-go-back-line me-1"></i>Not Allowed to
                                                                Undo Duplicate
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                No removed duplicates found.
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
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/transaction_events/removedDuplicates.blade.php ENDPATH**/ ?>