<?php $__env->startSection('title', 'Transaction Events'); ?>

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="mb-4">
                    <h4 class="mb-1 fw-semibold">Transaction Events</h4>
                    <p class="text-muted mb-0">Manage and track all transaction events.</p>
                </div>
            </div>
        </div>

        <?php if(session('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e(session('success')); ?>

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
                            <span class="badge bg-primary-subtle text-primary px-3 py-2"><?php echo e($events->total()); ?> total</span>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="ri-upload-2-line me-1"></i> Import CSV
                            </button>
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
                            <div class="col-md-1 d-grid gap-1">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ri-search-line"></i>
                                </button>
                                <?php if(request()->hasAny(['search','contact','age_from','age_to','date_from','date_to'])): ?>
                                    <a href="<?php echo e(route('transaction-events.index')); ?>" class="btn btn-light btn-sm">
                                        <i class="ri-close-line"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Contact No.</th>
                                        <th>Address</th>
                                        <th style="width: 100px;">Date</th>
                                        <th style="width: 80px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td class="text-muted"><?php echo e($event->id); ?></td>
                                            <td class="fw-semibold"><?php echo e($event->full_name); ?></td>
                                            <td><?php echo e($event->age ?? '-'); ?></td>
                                            <td><?php echo e($event->contact_no ?? '-'); ?></td>
                                            <td><?php echo e($event->address ?? '-'); ?></td>
                                            <td class="text-muted small"><?php echo e($event->created_at?->format('M d, Y')); ?></td>
                                            <td class="text-center">
                                                <form action="<?php echo e(route('transaction-events.destroy', $event)); ?>" method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this event?')">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
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

        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="<?php echo e(route('transaction-events.import')); ?>" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="importModalLabel">Import Transaction Events</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">Select CSV File</label>
                                <input type="file" class="form-control <?php $__errorArgs = ['csv_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                    id="csv_file" name="csv_file" accept=".csv" required>
                                <?php $__errorArgs = ['csv_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div class="invalid-feedback"><?php echo e($message); ?></div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                            <div class="alert alert-info mb-0">
                                <strong>CSV Format:</strong> The file should have the following columns (with header row):<br>
                                <code>full_name, contact_no, address, age</code>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-upload-2-line me-1"></i> Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/transaction_events/transactionEvents.blade.php ENDPATH**/ ?>