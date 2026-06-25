<?php $__env->startSection('title', '419 Error'); ?>
<?php $__env->startSection('content'); ?>
    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-8">
            <div class="text-center">
                <img src="<?php echo e(asset('assets/images/error419.png')); ?>" 
                    alt="Page Expired" 
                    class="img-fluid">

                <div class="mt-3">
                    <h3 class="text-uppercase fw-bold">Sorry, 419 Page Expired 😭</h3>
                    <p class="text-muted mb-4">
                        The page you are trying to access has expired or is no longer available.
                    </p>

                    <a href="<?php echo e(url('/')); ?>" class="btn btn-success">
                        <i class="mdi mdi-home me-1"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.error', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\errors\419.blade.php ENDPATH**/ ?>