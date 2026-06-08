<?php $__env->startSection('title', 'Client Details'); ?>
<?php $__env->startSection('content'); ?>
    <?php
        $clientPhoto = $client->photo_path ? asset('storage/' . $client->photo_path) : asset('assets/images/avatar-1.jpg');
        $clientFingerprint = $client->fingerprint_path ? asset('storage/' . $client->fingerprint_path) : null;
    ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client Details</h4>
                                <p class="text-muted mb-0">Review the selected client information.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo e(route('client.list')); ?>" class="btn btn-soft-secondary">Back to List</a>
                                <a href="<?php echo e(route('clients.edit', $client)); ?>" class="btn btn-primary">Edit Client</a>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-3 text-center">
                                <img src="<?php echo e($clientPhoto); ?>" alt="Client Photo" class="img-thumbnail avatar-xxl object-fit-cover rounded-3">
                                <h5 class="mt-3 mb-1"><?php echo e(trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name)); ?></h5>
                                <p class="text-muted mb-0"><?php echo e($client->gender ?? 'N/A'); ?></p>
                            </div>

                            <div class="col-lg-9">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <tbody>
                                            <tr>
                                                <th style="width: 220px;">First Name</th>
                                                <td><?php echo e($client->first_name); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Middle Name</th>
                                                <td><?php echo e($client->middle_name ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Last Name</th>
                                                <td><?php echo e($client->last_name); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Age</th>
                                                <td><?php echo e($client->age ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Gender</th>
                                                <td><?php echo e($client->gender ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Civil Status</th>
                                                <td><?php echo e($client->civil_status ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?php echo e($client->email ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Contact</th>
                                                <td><?php echo e($client->contact ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Address</th>
                                                <td><?php echo e($client->address ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Province</th>
                                                <td><?php echo e($client->province ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>City</th>
                                                <td><?php echo e($client->city ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Barangay</th>
                                                <td><?php echo e($client->barangay ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Fingerprint</th>
                                                <td>
                                                    <?php if($clientFingerprint): ?>
                                                        <a href="<?php echo e($clientFingerprint); ?>" target="_blank" rel="noopener">
                                                            <img src="<?php echo e($clientFingerprint); ?>" alt="Fingerprint" class="rounded-3 border object-fit-cover" style="width: 120px; height: 120px;">
                                                        </a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/clientShow.blade.php ENDPATH**/ ?>