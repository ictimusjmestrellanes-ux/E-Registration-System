
<?php $__env->startSection('title', 'ERS | Transaction Event Archives'); ?>

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <h4 class="mb-1 fw-semibold">Transaction Event Archives</h4>
                <p class="text-muted mb-0">Browse and download CSV archive files generated from imported transaction events.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Archive Files</h5>
                        <a href="<?php echo e(route('transaction-events.index')); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="ri-arrow-left-line me-1"></i> Back to Transaction Events
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if($files->isEmpty()): ?>
                            <div class="alert alert-info mb-0">
                                No archive files found yet. Import a CSV file to create archive records.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Filename</th>
                                            <th style="width: 140px;">Uploaded At</th>
                                            <th style="width: 120px;">Size</th>
                                            <th style="width: 140px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $__currentLoopData = $files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr>
                                                <td><?php echo e($file['name']); ?></td>
                                                <td><?php echo e(\Carbon\Carbon::createFromTimestamp($file['uploaded_at'])->format('M d, Y H:i:s')); ?></td>
                                                <td><?php echo e(number_format($file['size'] / 1024, 2)); ?> KB</td>
                                                <td class="text-center">
                                                    <a href="<?php echo e($file['download_url']); ?>" class="btn btn-sm btn-primary">
                                                        <i class="ri-download-line me-1"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/transaction_events/transactionEventArchives.blade.php ENDPATH**/ ?>