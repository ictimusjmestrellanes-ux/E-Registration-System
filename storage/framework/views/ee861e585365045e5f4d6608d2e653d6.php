<?php $__env->startSection('title', 'ERS | ' . $labels . ' Transactions'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1"><?php echo e($labels); ?> Transactions</h4>
                                <p class="text-muted mb-0"><?php echo e($total); ?> transaction(s)<?php echo e($category ? ' under this service category' : ''); ?></p>
                            </div>
                            <a href="<?php echo e(route('dashboard')); ?>" class="btn btn-light btn-sm">
                                <i class="ri-arrow-left-line me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="search"
                                    placeholder="Search transaction ID, clerk, or type..." value="<?php echo e(request('search')); ?>">
                            </div>
                            <div class="col-md-2 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                    <i class="ri-search-line"></i>
                                </button>
                                <?php if(request()->filled('search')): ?>
                                    <a href="<?php echo e($category ? route('transactions.category', $category) : route('transactions.index')); ?>"
                                        class="btn btn-light btn-sm flex-fill">
                                        <i class="ri-close-line"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Client</th>
                                        <th>Transaction ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Clerk</th>
                                        <th style="width: 110px; text-align: center;">Status</th>
                                        <th style="width: 90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $isApproved = strtolower($transaction->status ?? 'Pending') === 'approved';
                                            $client = App\Models\Client::where('client_id', $transaction->client_id)->first();
                                        ?>
                                        <tr class="<?php echo e($isApproved ? '' : 'table-warning'); ?>">
                                            <td class="fw-semibold"><?php echo e($client->full_name ?? $transaction->client_id); ?></td>
                                            <td class="small">
                                                <a href="<?php echo e(route('transactions.show', $transaction->id)); ?>"
                                                    class="text-primary"><?php echo e($transaction->transaction_id); ?></a>
                                            </td>
                                            <td class="small"><?php echo e($transaction->transaction_date?->format('M d, Y')); ?></td>
                                            <td class="small"><?php echo e($transaction->type_label); ?></td>
                                            <td class="small"><?php echo e($transaction->clerk ?? '-'); ?></td>
                                            <td class="text-center">
                                                <span
                                                    class="badge <?php echo e($isApproved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'); ?> px-3 py-2">
                                                    <?php echo e($transaction->status ?? 'Pending'); ?>

                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($client): ?>
                                                    <a href="<?php echo e(route('clients.show', $client)); ?>"
                                                        class="btn btn-sm btn-soft-primary" title="View client details">
                                                        <i class="ri-eye-line"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                No transactions found<?php echo e($category ? ' under this category' : ''); ?>.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <?php echo e($transactions->links('pagination::bootstrap-5')); ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\client_transaction\transactionCategoryList.blade.php ENDPATH**/ ?>