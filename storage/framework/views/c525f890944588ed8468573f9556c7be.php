<?php $__env->startSection('title', 'Reset Password'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-center mt-sm-5 mb-4 text-white-50">
                    <div>
                        <a href="index.html" class="d-inline-block auth-logo">
                            <span class="fw-bold fs-3 text-white">E-Registration System</span>
                        </a>
                    </div>
                    <p class="mt-3 fs-15 fw-medium">Premium Admin & Dashboard Template</p>
                </div>
            </div>
        </div>
        <!-- end row -->

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card mt-4 card-bg-fill">
                    <div class="card-body p-4">
                        <div class="text-center mt-2">
                            <h5 class="text-primary">Forgot Password?</h5>
                            <p class="text-muted">Reset password with E-Registration System</p>
                            <lord-icon src="https://cdn.lordicon.com/rhvddzym.json" trigger="loop" colors="primary:#0ab39c" class="avatar-xl"></lord-icon>
                        </div>

                        <div class="alert border-0 alert-warning text-center mb-2 mx-2" role="alert">
                            Enter your email and instructions will be sent to you!
                        </div>
                        <div class="p-2">
                            <form action="<?php echo e(route('password.email')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <div class="mb-4">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="email" name="email" placeholder="Enter Email">
                                </div>

                                <div class="text-center mt-4">
                                    <button class="btn btn-success w-100" type="submit">Send Reset Link</button>
                                </div>
                            </form><!-- end form -->
                        </div>
                    </div>
                    <!-- end card body -->
                </div>
                <!-- end card -->

                <div class="mt-4 text-center">
                    <p class="mb-0">Wait, I remember my password... <a href="<?php echo e(route('login')); ?>" class="fw-semibold text-primary text-decoration-underline"> Click here </a> </p>
                </div>

            </div>
        </div>
        <!-- end row -->
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\auth\passwords\email.blade.php ENDPATH**/ ?>