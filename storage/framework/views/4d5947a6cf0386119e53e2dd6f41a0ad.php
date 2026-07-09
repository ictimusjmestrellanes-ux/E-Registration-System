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
                <div class="card material-shadow border-primary border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-users text-primary fs-4"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-muted mb-1">Total Registered Clients</p>
                                <h3 class="mb-0"><?php echo e($totalClients); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-0">Service Categories</h5>
                        <p class="text-muted mb-0">Overview of client service requests</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-hand-holding-heart text-primary fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Social Services Assistance</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-success bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-file-invoice text-success fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Solicitation</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-info bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-futbol text-info fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Youth & Sports</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-warning bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-calendar-check text-warning fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Appointments</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-secondary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-building text-secondary fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Infrastructure</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-danger bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-graduation-cap text-danger fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Scholarships</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-file-contract text-primary fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Permits</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-success bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-calendar-days text-success fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Events</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-info bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-briefcase text-info fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Job Application</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-warning bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-house text-warning fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">HOA</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card material-shadow h-100">
                    <div class="card-body text-center">
                        <div class="avatar-md bg-secondary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-ellipsis text-secondary fs-3"></i>
                        </div>
                        <h4 class="mb-1">0</h4>
                        <p class="text-muted mb-0">Others</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/dashboard.blade.php ENDPATH**/ ?>