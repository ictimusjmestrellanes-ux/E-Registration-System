<?php $__env->startSection('title', 'ERS | Roles'); ?>
<?php $__env->startSection('content'); ?>
    <?php $canManage = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Roles</h4>
                                <p class="text-muted mb-0">System roles and their assigned users.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary-subtle text-primary fs-13">
                                    <?php echo e($totalUsers); ?> Total User(s)
                                </span>
                                <?php if($canManage): ?>
                                    <?php if(feature_allowed('Add Roles')): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#addRoleModal">
                                            <i class="ri-add-line align-bottom me-1"></i> Add Role
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary" disabled>
                                            <i class="ri-add-line align-bottom me-1"></i>Not Allowed to Add Role
                                        </button>
                                    <?php endif; ?>
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
            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-0"><?php echo e($role['name']); ?></h5>
                                    <p class="text-muted mb-0">Role</p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if($canManage): ?>
                                        <?php if(feature_allowed('Delete Roles')): ?>
                                            <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteRoleModal-<?php echo e($role['id']); ?>" title="Delete Role">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-soft-danger" disabled title="Not Allowed to Delete Role">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-primary-subtle text-primary rounded fs-4">
                                            <i class="ri-shield-user-line"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted">Assigned Users</span>
                                <h4 class="mb-0"><?php echo e($role['users_count']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <?php if($canManage): ?>
        <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="<?php echo e(route('roles.store')); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="modal-header">
                            <h5 class="modal-title">Add Role</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="roleName" class="form-label">Role Name</label>
                                <input type="text" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                    id="roleName" name="name" placeholder="e.g. Encoder" required>
                                <?php $__errorArgs = ['name'];
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
                            <button type="submit" class="btn btn-primary">Create Role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="modal fade" id="deleteRoleModal-<?php echo e($role['id']); ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="<?php echo e(route('roles.destroy', $role['id'])); ?>">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <div class="modal-header">
                                <h5 class="modal-title">Delete Role</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Are you sure you want to delete the role
                                    <strong><?php echo e($role['name']); ?></strong>? Its permissions will also be removed.
                                </p>
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

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\roles\index.blade.php ENDPATH**/ ?>