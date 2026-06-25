<?php $__env->startSection('title', 'Archive'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Archived Clients</h4>
                                <p class="text-muted mb-0">View clients that have been moved to the archive.</p>
                            </div>
                            <a href="<?php echo e(route('client.list')); ?>" class="btn btn-primary">Back to Client List</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Client ID</th>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Civil Status</th>
                                        <th>Birthplace</th>
                                        <th>Contact 1</th>
                                        <th>Address</th>
                                        <th>Archived At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    <?php $__empty_1 = true; $__currentLoopData = $archivedClients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><?php echo e($client->client_id ?? '-'); ?></td>
                                            <td>
                                                <?php
                                                    $clientPhoto = $client->photo_url;
                                                    $defaultClientPhoto = asset('images/default-client.png');
                                                ?>
                                                <button type="button" class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal" data-bs-target="#clientPhotoModal"
                                                    data-client-photo="<?php echo e($clientPhoto); ?>"
                                                    data-client-name="<?php echo e(trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name)); ?>">
                                                    <img src="<?php echo e($clientPhoto); ?>" alt="Client Photo"
                                                        onerror="this.onerror=null;this.src='<?php echo e($defaultClientPhoto); ?>';"
                                                        class="rounded-3 border object-fit-cover"
                                                        style="width: 72px; height: 72px;">
                                                </button>
                                            </td>
                                            <td>
                                                <?php echo e($client->full_name); ?>

                                            </td>
                                            <td><?php echo e($client->suffix ?? '-'); ?></td>
                                            <td><?php echo e($client->gender ?? '-'); ?></td>
                                            <td><?php echo e($client->civil_status ?? '-'); ?></td>
                                            <td><?php echo e($client->birthplace ?? '-'); ?></td>
                                            <td><?php echo e($client->contact ?? '-'); ?></td>
                                            <td class="text-start">
                                                <div class="small lh-sm">
                                                    <div><?php echo e($client->address ?? '-'); ?></div>
                                                    <div class="text-muted">
                                                        <?php echo e(collect([$client->barangay, $client->city, $client->province])->filter()->implode(', ') ?:'-'); ?>

                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo e($client->archived_at ? $client->archived_at->format('m/d/Y h:i A') : '-'); ?></td>
                                            <td>
                                                <form action="<?php echo e(route('archive.restore', $client)); ?>" method="POST" onsubmit="return confirm('Restore this client back to the active list?');">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="btn btn-sm btn-soft-success">
                                                        Restore
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="23" class="text-center text-muted py-4">
                                                No archived clients found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/archive.blade.php ENDPATH**/ ?>