<?php $__env->startSection('title', 'ERS | Events - Records'); ?>

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <h4 class="mb-1 fw-semibold">Events - Records</h4>
                <p class="text-muted mb-0">List of transaction events that have been transferred to records.</p>
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
                        <form method="GET" action="<?php echo e(route('transaction-events.records')); ?>" class="d-flex flex-wrap gap-2 mb-3">
                            <input type="text" name="search" class="form-control w-auto" placeholder="Search by name..."
                                value="<?php echo e(request('search')); ?>">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="ri-search-line me-1"></i> Search
                            </button>
                            <?php if(request('search')): ?>
                                <a href="<?php echo e(route('transaction-events.records')); ?>" class="btn btn-soft-secondary btn-sm px-3">
                                    Reset
                                </a>
                            <?php endif; ?>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Transaction ID</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Birth Date</th>
                                        <th>Contact No.</th>
                                        <th>Address</th>
                                        <th>Category</th>
                                        <th>Transaction Category</th>
                                        <th>Transaction Type</th>
                                        <th style="width: 160px;">Transferred At</th>
                                        <th style="width: 120px;" class="text-center">Status</th>
                                        <?php if(auth()->user()?->role_name !== 'Viewer'): ?>
                                            <th style="width: 140px;" class="text-center">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><?php echo e($event->id); ?></td>
                                            <td class="fw-semibold" style="width: 150px"><?php echo e($event->transferredTransaction?->transaction_id ?? '-'); ?></td>
                                            <td class="fw-semibold"><?php echo e($event->full_name); ?></td>
                                            <td><?php echo e($event->age ?? '-'); ?></td>
                                            <td><?php echo e(optional($event->birth_date)->format('M d, Y') ?? '-'); ?></td>
                                            <td><?php echo e(str_replace('-', '', $event->contact_no ?? '') ?: '-'); ?></td>
                                            <td class="small"><?php echo e($event->address ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->client_category ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->transaction_category ?? '-'); ?></td>
                                            <td class="small"><?php echo e($event->transaction_type ?? '-'); ?></td>
                                            <td><?php echo e(optional($event->transferred_at)->timezone('Asia/Manila')->format('M d, Y H:i:s')); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <i class="ri-check-line me-1"></i>Approved
                                                </span>
                                            </td>
                                            <?php if(auth()->user()?->role_name !== 'Viewer'): ?>
                                                <td class="text-center">
                                                    <form action="<?php echo e(route('transaction-events.undo-transfer', $event)); ?>" method="POST"
                                                        class="m-0">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-sm btn-soft-warning"
                                                            onclick="return confirm('Undo this transfer? The created transaction record will be removed and this event will return to pending. The client record will remain.');">
                                                            <i class="ri-arrow-go-back-line me-1"></i> Undo Transfer
                                                        </button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="<?php echo e(auth()->user()?->role_name !== 'Viewer' ? 13 : 12); ?>" class="text-center text-muted py-5">
                                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                No transferred event records found.
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

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/transaction_events/eventRecords.blade.php ENDPATH**/ ?>