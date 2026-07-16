<?php $__env->startSection('title', 'Login'); ?>

<?php $__env->startSection('content'); ?>
    <style>
        html,
        body {
            min-height: 100%;
        }

        body {
            background: #ffffff;
        }

        .auth-page-wrapper.pt-5 {
            padding-top: 0 !important;
        }

        .auth-one-bg-position,
        .footer {
            display: none !important;
        }

        .auth-page-content {
            padding: 0 !important;
            min-height: 100vh;
        }

        .login-shell {
            min-height: 100vh;
            display: flex;
            background: linear-gradient(90deg, #f7fbff 0%, #edf4ff 42%, #dce9ff 73%, #ffffff 73%, #ffffff 100%);
        }

        .login-hero {
            flex: 1 1 67%;
            padding: 84px 56px 56px 60px;
            display: flex;
            align-items: center;
        }

        .login-hero-inner {
            max-width: 760px;
            color: #1b2a4a;
        }

        .brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 34px;
        }

        .brand-lockup img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            border-radius: 50%;
            flex: 0 0 auto;
        }

        .brand-copy {
            font-size: 26px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: 0.16em;
            color: #1a5de9;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: clamp(2.9rem, 4.3vw, 4.45rem);
            line-height: 0.98;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: #182445;
            margin: 0 0 28px;
            max-width: 700px;
        }

        .hero-copy {
            max-width: 600px;
            font-size: clamp(1.08rem, 1.15vw, 1.45rem);
            line-height: 1.55;
            color: #6c7c9d;
            margin-bottom: 36px;
        }

        .hero-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 18px;
            max-width: 640px;
        }

        .hero-list li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            color: #50607f;
            font-size: 1rem;
            line-height: 1.45;
        }

        .hero-check {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(48, 137, 239, 0.16);
            color: #1f82f0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 1rem;
            font-weight: 800;
            margin-top: 1px;
        }

        .login-panel {
            flex: 0 0 36%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 36px;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
            text-align: center;
            color: #24304a;
        }

        .login-card h1 {
            font-size: clamp(2rem, 2vw, 2.35rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin: 0;
        }

        .login-card p.lead {
            margin: 10px 0 0;
            font-size: 1.03rem;
            color: #7686a8;
        }

        .oauth-buttons {
            margin: 56px auto 42px;
            width: 100%;
            max-width: 368px;
            display: grid;
            gap: 12px;
        }

        .microsoft-btn,
        .google-btn {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 16px 24px;
            font-size: 1.02rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .microsoft-btn {
            background: linear-gradient(180deg, #1784ec 0%, #0f75d9 100%);
            color: #ffffff;
            box-shadow: 0 16px 28px rgba(22, 120, 219, 0.22);
        }

        .google-btn {
            background: #ffffff;
            color: #24304a;
            border: 1px solid #d7dfec;
            box-shadow: 0 12px 22px rgba(36, 48, 74, 0.08);
        }

        .google-btn i {
            color: #ea4335;
        }

        .microsoft-btn:focus,
        .microsoft-btn:hover {
            color: #ffffff;
            filter: brightness(1.02);
            transform: translateY(-1px);
        }

        .google-btn:focus,
        .google-btn:hover {
            color: #24304a;
            border-color: #c3ccdc;
            transform: translateY(-1px);
        }

        .microsoft-logo {
            width: 20px;
            height: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 2px;
            flex: 0 0 auto;
        }

        .microsoft-logo span {
            display: block;
            width: 100%;
            height: 100%;
        }

        .microsoft-logo .c1 { background: #f25022; }
        .microsoft-logo .c2 { background: #7fba00; }
        .microsoft-logo .c3 { background: #00a4ef; }
        .microsoft-logo .c4 { background: #ffb900; }

        .support-copy {
            color: #7e8eae;
            font-size: 0.98rem;
            line-height: 1.6;
            max-width: 330px;
            margin: 0 auto;
        }

        .support-copy strong {
            color: #1269d9;
            font-weight: 800;
        }

        @media (max-width: 1199.98px) {
            .login-shell {
                background: linear-gradient(180deg, #f7fbff 0%, #eaf2ff 52%, #ffffff 52%, #ffffff 100%);
                flex-direction: column;
            }

            .login-hero,
            .login-panel {
                flex: 1 1 auto;
                padding-left: 32px;
                padding-right: 32px;
            }

            .login-hero {
                padding-top: 56px;
                padding-bottom: 24px;
            }

            .login-panel {
                padding-top: 28px;
                padding-bottom: 56px;
            }
        }

        @media (max-width: 767.98px) {
            .login-hero,
            .login-panel {
                padding-left: 22px;
                padding-right: 22px;
            }

            .hero-title {
                max-width: 100%;
            }

            .hero-copy {
                font-size: 1rem;
            }

            .login-card p.lead {
                font-size: 0.98rem;
            }
        }
    </style>

    <div class="login-shell">
        <section class="login-hero">
            <div class="login-hero-inner">
                <div class="brand-lockup">
                    
                    <div class="brand-copy">
                        E-REGISTRATION SYSTEM
                    </div>
                </div>

                <h1 class="hero-title">Monitor clients in real time.</h1>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <h1>Welcome back</h1>
                <p class="lead">Sign in to your account to continue</p>

                <div class="oauth-buttons">
                    <a href="<?php echo e(route('azure.redirect')); ?>" class="microsoft-btn" aria-label="Sign in with Microsoft">
                        <span class="microsoft-logo" aria-hidden="true">
                            <span class="c1"></span>
                            <span class="c2"></span>
                            <span class="c3"></span>
                            <span class="c4"></span>
                        </span>
                        Sign in with Microsoft
                    </a>
                    <a href="<?php echo e(route('google.redirect')); ?>" class="google-btn" aria-label="Sign in with Google">
                        <i class="ri-google-fill fs-18" aria-hidden="true"></i>
                        Sign in with Google
                    </a>
                </div>

                <p class="support-copy mt-4">
                    Need assistance? Contact the <strong>IT Support Team</strong> for help accessing your account.
                </p>
            </div>
        </section>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/auth/login.blade.php ENDPATH**/ ?>