<?php $__env->startSection('title', '500 Error'); ?>
<?php $__env->startSection('content'); ?>
    <style>
        html, body {
            min-height: 100%;
            overflow: hidden;
        }
    </style>
    <div class="row justify-content-center">
        <div class="col-xl-4 text-center">
            <div class="error-500 position-relative">
                <img src="<?php echo e(asset('assets/images/error500.png')); ?>" alt="" class="img-fluid error-500-img error-img">
                <h1 class="title text-muted">500</h1>
            </div>
            <div>
                <h4>Internal Server Error!</h4>
                <p class="text-muted w-75 mx-auto">Server Error 500. We're not exactly sure what happened, but our servers say something is wrong.</p>
                <a href="<?php echo e(url('/')); ?>" class="btn btn-success"><i class="mdi mdi-home me-1"></i>Back to login</a>
            </div>
        </div><!-- end col-->
    </div> 
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.error', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\errors\500.blade.php ENDPATH**/ ?>