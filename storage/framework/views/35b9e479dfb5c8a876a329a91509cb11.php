<?php $__env->startSection('title', 'ERS | Transaction Process'); ?>
<?php $__env->startSection('content'); ?>
    <?php
        $txStatus = $transaction->status ?? 'Pending';
        $isApproved = strtolower($txStatus) === 'approved';
    ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Transaction Process</h4>
                                <p class="text-muted mb-0">
                                    Step-by-step process for
                                    <span class="text-uppercase fw-semibold"><?php echo e($transaction->transaction_id); ?></span>
                                    <?php if($client): ?>
                                        &nbsp;- <?php echo e(strtoupper($client->full_name)); ?>

                                    <?php endif; ?>
                                </p>
                            </div>
                            <a href="<?php echo e($client ? route('clients.show', $client->id) : route('client.list')); ?>"
                                class="btn btn-secondary">Back to Client</a>
                        </div>

                        <div class="row g-4">
                            
                            <div class="col-12 col-lg-7">
                                <div class="border rounded-4 p-4 bg-light-subtle h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <h5 class="mb-0">Process Steps</h5>
                                        <span class="badge <?php echo e($isApproved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'); ?> fs-6">
                                            <?php echo e($txStatus); ?>

                                        </span>
                                    </div>

                                    <div class="position-relative" style="padding-left: 2.5rem;">
                                        <div class="position-absolute top-0 bottom-0" style="left: 0.9rem; width: 2px; background: #dee2e6;"></div>

                                        <?php $__currentLoopData = $processSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="position-relative mb-4 pb-1">
                                                <div class="position-absolute top-0 start-0 translate-middle d-flex align-items-center justify-content-center rounded-circle <?php echo e($step['done'] ? 'bg-success text-white' : 'bg-secondary text-white'); ?>"
                                                    style="width: 1.8rem; height: 1.8rem; left: -1.6rem !important; z-index: 1;">
                                                    <i class="bi <?php echo e($step['done'] ? 'bi-check-lg' : 'bi-clock'); ?>"></i>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo e($index + 1); ?>. <?php echo e($step['title']); ?></h6>
                                                        <p class="text-muted mb-0 small"><?php echo e($step['detail']); ?></p>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php if($step['time']): ?>
                                                            <span class="badge bg-light text-dark border"><?php echo e($step['time']->format('m/d/Y h:i A')); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="col-12 col-lg-5">
                                <div class="border rounded-4 p-4 bg-light-subtle">
                                    <h5 class="mb-3">Transaction Details</h5>
                                    <table class="table table-sm table-bordered align-middle mb-4">
                                        <tbody>
                                            <tr>
                                                <th class="table-light text-uppercase small" style="width: 40%;">Transaction ID</th>
                                                <td class="text-uppercase"><?php echo e($transaction->transaction_id); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Transaction Date</th>
                                                <td><?php echo e($transaction->transaction_date->format('m/d/Y')); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Source</th>
                                                <td class="text-uppercase"><?php echo e($transaction->source ?? 'E-Registration'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Category</th>
                                                <td class="text-uppercase"><?php echo e($transaction->category_label); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Type</th>
                                                <td class="text-uppercase"><?php echo e($transaction->type_label); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Clerk</th>
                                                <td class="text-uppercase"><?php echo e($transaction->clerk ?? 'System'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Signatory</th>
                                                <td class="text-uppercase"><?php echo e($transaction->signatory ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Personnel Endorsed To</th>
                                                <td class="text-uppercase"><?php echo e($transaction->personnel_endorsed_to ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Responsible Office</th>
                                                <td class="text-uppercase"><?php echo e($transaction->responsible_office ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Amount</th>
                                                <td><?php echo e($transaction->amount > 0 ? 'PHP ' . number_format((float) $transaction->amount, 2) : 'PHP 0.00'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Description</th>
                                                <td><?php echo e($transaction->description ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Actions Taken</th>
                                                <td><?php echo e($transaction->actions_taken ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Remarks</th>
                                                <td><?php echo e($transaction->remarks ?? 'N/A'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h5 class="mb-3">Subject Information</h5>
                                    <?php if($hasSubject): ?>
                                        <table class="table table-sm table-bordered align-middle mb-4">
                                            <tbody>
                                                <tr>
                                                    <th class="table-light text-uppercase small" style="width: 40%;">Name</th>
                                                    <td class="text-uppercase"><?php echo e($transaction->subject_full_name); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Age</th>
                                                    <td><?php echo e($transaction->subject_age !== null ? $transaction->subject_age . ' yrs. old' : 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Gender</th>
                                                    <td class="text-uppercase"><?php echo e($transaction->subject_gender ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Birthdate</th>
                                                    <td><?php echo e(optional($transaction->subject_birthdate)->format('m/d/Y') ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Barangay</th>
                                                    <td class="text-uppercase"><?php echo e($transaction->subject_barangay ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Municipality</th>
                                                    <td class="text-uppercase"><?php echo e($transaction->subject_municipality ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Relation to Client</th>
                                                    <td class="text-uppercase"><?php echo e($transaction->subject_client_relation ?? 'N/A'); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="alert alert-secondary py-2 small">No subject information recorded for this transaction.</div>
                                    <?php endif; ?>

                                    <h5 class="mb-3">Requirements (<?php echo e($requirements->count()); ?>)</h5>
                                    <?php if($requirements->isNotEmpty()): ?>
                                        <div class="list-group">
                                            <?php $__currentLoopData = $requirements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $requirement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                    <div>
                                                        <div class="fw-semibold small"><?php echo e($requirement['label']); ?></div>
                                                        <div class="text-muted small">
                                                            <?php echo e($requirement['created_at']->format('m/d/Y h:i A')); ?>

                                                            &middot;
                                                            <?php if($requirement['file_name']): ?>
                                                                <?php echo e($requirement['file_name']); ?>

                                                            <?php else: ?>
                                                                <span class="text-secondary fst-italic">No file provided</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if($requirement['file_url']): ?>
                                                        <a href="<?php echo e(route('transaction-requirements.download', $requirement['id'])); ?>"
                                                            class="btn btn-outline-primary btn-sm">Download</a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary">No file</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-secondary py-2 small">No requirements submitted for this transaction.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\client_transaction\transactionProcess.blade.php ENDPATH**/ ?>