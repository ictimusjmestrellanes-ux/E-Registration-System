<?php $__env->startSection('title', 'Transaction Information'); ?>
<?php $__env->startSection('content'); ?>
    <?php
        $formattedId = 'TXN-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
        $clientName = $transaction->client ? strtoupper($transaction->client->full_name) : '-';
    ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Transaction Information</h4>
                                <p class="text-muted mb-0">Details of the completed transaction.</p>
                            </div>
                        </div>

                        <div class="px-4 py-3 bg-light border rounded-4 mb-4">
                            <div class="row gy-2">
                                <div class="col-md-6 d-flex">
                                    <div class="text-uppercase text-muted small fw-semibold me-3">Transaction ID</div>
                                    <div class="fw-bold"><?php echo e($formattedId); ?></div>
                                </div>
                                <div class="col-md-6 d-flex">
                                    <div class="text-uppercase text-muted small fw-semibold me-3">Transaction Date</div>
                                    <div class="fw-bold"><?php echo e($transaction->transaction_date->format('m/d/Y')); ?></div>
                                </div>
                                <div class="col-md-6 d-flex">
                                    <div class="text-uppercase text-muted small fw-semibold me-3">Client</div>
                                    <div class="fw-bold text-uppercase"><?php echo e($clientName); ?></div>
                                </div>
                                <div class="col-md-6 d-flex">
                                    <div class="text-uppercase text-muted small fw-semibold me-3">Transaction Type</div>
                                    <div class="fw-bold text-uppercase"><?php echo e(str_replace('_', ' ', $transaction->type)); ?></div>
                                </div>
                                <div class="col-md-6 d-flex">
                                    <div class="text-uppercase text-muted small fw-semibold me-3">Category</div>
                                    <div class="fw-bold text-uppercase"><?php echo e(str_replace('_', ' ', $transaction->category)); ?></div>
                                </div>
                                <div class="col-md-6 d-flex">
                                    <div class="text-uppercase text-muted small fw-semibold me-3">Addressed To</div>
                                    <div class="fw-bold text-uppercase"><?php echo e(str_replace('_', ' ', $transaction->addressed_to ?? '-')); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if($transaction->description): ?>
                            <div class="mb-3">
                                <label class="form-label text-uppercase text-muted small fw-semibold">Description</label>
                                <div class="border rounded-3 p-3 bg-light"><?php echo e($transaction->description); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-4">
                            <a href="<?php echo e(route('clients.show', $transaction->client)); ?>" class="btn btn-primary">View Client</a>
                            <a href="<?php echo e(route('client.list')); ?>" class="btn btn-outline-secondary">Back to List</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/client_transaction/transactionInfo.blade.php ENDPATH**/ ?>