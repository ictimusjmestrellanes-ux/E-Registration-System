<?php $__env->startSection('title', 'Lock Screen'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-center mt-sm-5 mb-4 text-white-50">
                    <div>
                        <a href="<?php echo e(route('profile')); ?>" class="d-inline-block auth-logo">
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
                            <h5 class="text-primary">Lock Screen</h5>
                            <p class="text-muted">Enter your password to unlock the screen!</p>
                        </div>
                        <div class="user-thumb text-center">
                            <?php
                                $lockScreenAvatar = auth()->user()?->avatar ? asset('storage/' . auth()->user()->avatar) : asset('assets/images/avatar-1.jpg');
                            ?>
                            <img src="<?php echo e($lockScreenAvatar); ?>" class="rounded-circle img-thumbnail avatar-lg material-shadow" alt="thumbnail">
                            <h5 class="font-size-15 mt-3">E-Registration System</h5>
                        </div>
                        <div class="p-2 mt-4">
                            <form action="<?php echo e(route('unlock-screen')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <div class="mb-3">
                                    <label class="form-label" for="userpassword">Password</label>
                                    <input type="password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="userpassword" name="password" placeholder="Enter password">
                                </div>
                                <div class="mb-2 mt-4">
                                    <button class="btn btn-success w-100" type="submit">Unlock</button>
                                </div>
                            </form><!-- end form -->

                        </div>
                    </div>
                    <!-- end card body -->
                </div>
                <!-- end card -->

                <div class="mt-4 text-center">
                    <p class="mb-0">Not you ? return <a href="<?php echo e(route('login')); ?>" class="fw-semibold text-primary text-decoration-underline"> Signin </a> </p>
                </div>

            </div>
        </div>
        <!-- end row -->
    </div>
    <!-- end container -->
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/auth/lock-screen.blade.php ENDPATH**/ ?>