<?php $__env->startSection('title', 'ERS | Permissions'); ?>
<?php $__env->startSection('content'); ?>
<?php $canEdit = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Permissions</h4>
                                <p class="text-muted mb-0">Feature access matrix per role.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if($canEdit): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#addPermissionModal">
                                        <i class="ri-add-line align-bottom me-1"></i> Add Permission
                                    </button>
                                    <button type="submit" form="permissionsForm" class="btn btn-success">
                                        <i class="ri-save-line align-bottom me-1"></i> Save Changes
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(session('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e(session('success')); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if($canEdit): ?>
                            <form method="POST" action="<?php echo e(route('permissions.update')); ?>" id="permissionsForm">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PUT'); ?>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Feature</th>
                                        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <th class="text-center"><?php echo e($role); ?></th>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        <?php if($canEdit): ?>
                                            <th class="text-center">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <tr>
                                            <td class="fw-medium"><?php echo e($permission['feature']); ?></td>
                                            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <td class="text-center">
                                                    <?php if($canEdit): ?>
                                                        <div class="form-check form-switch form-switch-lg d-inline-block mb-0">
                                                            <input type="checkbox" class="form-check-input" id="perm-<?php echo e($permission['feature']); ?>-<?php echo e($role); ?>"
                                                                name="allowed[<?php echo e($permission['feature']); ?>][<?php echo e($role); ?>]"
                                                                <?php if($permission[$role] ?? false): echo 'checked'; endif; ?>>
                                                            <label class="form-check-label" for="perm-<?php echo e($permission['feature']); ?>-<?php echo e($role); ?>"></label>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php if($permission[$role] ?? false): ?>
                                                            <span class="badge bg-success-subtle text-success"><i class="ri-check-line align-bottom"></i> Allowed</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger"><i class="ri-close-line align-bottom"></i> Denied</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <?php if($canEdit): ?>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deletePermissionModal-<?php echo e(str_replace(' ', '-', $permission['feature'])); ?>"
                                                        title="Delete Permission">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if($canEdit): ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if($canEdit): ?>
        <div class="modal fade" id="addPermissionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="<?php echo e(route('permissions.store')); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="modal-header">
                            <h5 class="modal-title">Add Permission</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="featureName" class="form-label">Feature Name</label>
                                <input type="text" class="form-control <?php $__errorArgs = ['feature'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                    id="featureName" name="feature" placeholder="e.g. Reports" required>
                                <?php $__errorArgs = ['feature'];
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
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Permission</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="modal fade" id="deletePermissionModal-<?php echo e(str_replace(' ', '-', $permission['feature'])); ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="<?php echo e(route('permissions.destroy')); ?>">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <input type="hidden" name="feature" value="<?php echo e($permission['feature']); ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Delete Permission</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Are you sure you want to delete the permission
                                    <strong><?php echo e($permission['feature']); ?></strong>? It will be removed for all roles.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/permissions/index.blade.php ENDPATH**/ ?>