<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">
    <head>

    <meta charset="utf-8">
    <title><?php echo $__env->yieldContent('title', 'Error'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description">
    <meta content="E-Registration System" name="author">
    <!-- App favicon -->
    <link rel="shortcut icon" href="<?php echo e(asset('assets/images/favicon.ico')); ?>">
    <!-- Layout config Js -->
    <script src="<?php echo e(asset('assets/js/layout.js')); ?>"></script>
    <!-- Bootstrap Css -->
    <link href="<?php echo e(asset('assets/css/bootstrap.min.css')); ?>" rel="stylesheet" type="text/css">
    <!-- Icons Css -->
    <link href="<?php echo e(asset('assets/css/icons.min.css')); ?>" rel="stylesheet" type="text/css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- App Css-->
    <link href="<?php echo e(asset('assets/css/app.min.css')); ?>" rel="stylesheet" type="text/css">
    <!-- custom Css-->
    <link href="<?php echo e(asset('assets/css/custom.min.css')); ?>" rel="stylesheet" type="text/css">

</head>
<body>

    <div class="auth-page-wrapper pt-5">
        <!-- auth page bg -->
        <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
            <div class="bg-overlay"></div>
            <div class="shape">
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1440 120">
                    <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
                </svg>
            </div>
        </div>

        <!-- auth page content -->
        <div class="auth-page-content">
            <?php echo $__env->yieldContent('content'); ?>
        </div>
        <!-- end auth page content -->

        <!-- footer -->
        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <p class="mb-0 text-muted">©
                                <script>document.write(new Date().getFullYear())</script> City Government of Imus. All rights reserved. Developed by IT Team.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <!-- end Footer -->
    </div>
    <!-- end auth-page-wrapper -->

    <!-- JAVASCRIPT -->
    <script src="<?php echo e(asset('assets/js/bootstrap.bundle.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/simplebar.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/waves.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/feather.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/lord-icon-2.1.0.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/plugins.js')); ?>"></script>
    <!-- particles js -->
    <script src="<?php echo e(asset('assets/js/particles.js')); ?>"></script>
    <!-- particles app js -->
    <script src="<?php echo e(asset('assets/js/particles.app.js')); ?>"></script>
    <!-- password-addon init -->
    <script src="<?php echo e(asset('assets/js/password-addon.init.js')); ?>"></script>
    <!-- imessage -->
    <script src="<?php echo e(asset('assets/js/imessage.js')); ?>"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let messages = {
                success: "<?php echo e(session('success')); ?>",
                error: "<?php echo e(session('error')); ?>",
                warning: "<?php echo e(session('warning')); ?>",
                info: "<?php echo e(session('info')); ?>"
            };

            Object.keys(messages).forEach(type => {
                if (messages[type]) {
                    new Message('imessage').show(messages[type], type === "error" ? "fail" : type, "top-right");
                }
            });
        });
    </script>

    <?php echo $__env->yieldContent('script'); ?>


</body>
</html>
<?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/layouts/app.blade.php ENDPATH**/ ?>