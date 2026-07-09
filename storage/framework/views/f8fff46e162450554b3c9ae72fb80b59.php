<?php $__env->startSection('title', 'Users'); ?>
<?php $__env->startSection('content'); ?>
<?php $canEditRole = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Users</h4>
                                <p class="text-muted mb-0">Manage system accounts and roles.</p>
                            </div>
                            <a href="<?php echo e(route('register')); ?>" class="btn btn-primary">
                                <i class="ri-user-add-line align-bottom me-1"></i> Add User
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <form method="GET" action="<?php echo e(route('users.index')); ?>" class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    value="<?php echo e($search); ?>" placeholder="NAME, EMAIL">
                            </div>
                            <div class="col-md-4">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All roles</option>
                                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($role); ?>" <?php if($selectedRole === $role): echo 'selected'; endif; ?>><?php echo e($role); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="ri-search-line align-bottom me-1"></i> Filter
                                </button>
                                <a href="<?php echo e(route('users.index')); ?>" class="btn btn-soft-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Provider</th>
                                        <th>Last Login</th>
                                        <?php if($canEditRole): ?>
                                            <th class="text-center">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo e($user->avatar_url); ?>" alt="<?php echo e($user->name ?? 'User'); ?>"
                                                        class="rounded-circle avatar-xs object-fit-cover me-2">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo e(strtoupper($user->name ?? 'User')); ?></h6>
                                                        <p class="text-muted mb-0"><?php echo e(strtoupper($user->email)); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary"><?php echo e($user->role_name ?? '-'); ?></span>
                                            </td>
                                            <td>
                                                <?php if($user->status === 'Active'): ?>
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary"><?php echo e($user->status ?? 'Inactive'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e(ucfirst($user->auth_provider ?? 'local')); ?></td>
                                            <td><?php echo e($user->last_login ?? '-'); ?></td>
                                            <?php if($canEditRole): ?>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-warning" data-bs-toggle="modal"
                                                        data-bs-target="#editRoleModal-<?php echo e($user->id); ?>">
                                                        <i class="ri-edit-box-line align-bottom"></i> Edit Role
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>

                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="<?php echo e($canEditRole ? 6 : 5); ?>" class="text-center text-muted py-5">No users found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <?php echo e($users->links()); ?>

                        </div>
                    </div>

                    <?php if($canEditRole): ?>
                        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="modal fade" id="editRoleModal-<?php echo e($user->id); ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="<?php echo e(route('users.updateRole', $user)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PUT'); ?>
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Role - <?php echo e($user->name); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="role_name-<?php echo e($user->id); ?>" class="form-label">Role</label>
                                                    <select class="form-select" id="role_name-<?php echo e($user->id); ?>" name="role_name">
                                                        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <option value="<?php echo e($role); ?>" <?php if($user->role_name === $role): echo 'selected'; endif; ?>><?php echo e($role); ?></option>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Role</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/users/index.blade.php ENDPATH**/ ?>