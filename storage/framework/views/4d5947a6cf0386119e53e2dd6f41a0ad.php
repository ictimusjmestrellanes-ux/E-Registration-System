<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Dashboard</h4>
                                <p class="text-muted mb-0">Welcome back, <?php echo e(auth()->user()?->name ?? 'User'); ?>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Users</p>
                        <h3 class="mb-0">128</h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow">
                    <div class="card-body">
                        <p class="text-muted mb-1">Pending Requests</p>
                        <h3 class="mb-0">24</h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow">
                    <div class="card-body">
                        <p class="text-muted mb-1">Approved Today</p>
                        <h3 class="mb-0">18</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/dashboard.blade.php ENDPATH**/ ?>