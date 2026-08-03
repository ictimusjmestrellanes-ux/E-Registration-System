<?php $__env->startSection('title', '404 Error'); ?>
<?php $__env->startSection('content'); ?>
    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-8">
            <div class="text-center">
                <img src="<?php echo e(asset('assets/images/error400-cover.png')); ?>" alt="error img" class="img-fluid">
                <div class="mt-3">
                    <h3 class="text-uppercase">Sorry, Page not Found 😭</h3>
                    <p class="text-muted mb-4">The page you are looking for not available!</p>
                    <a href="<?php echo e(url('/')); ?>" class="btn btn-success"><i class="mdi mdi-home me-1"></i>Back to login</a>
                </div>
            </div>
        </div><!-- end col -->
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.error', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/errors/404.blade.php ENDPATH**/ ?>