@extends('layouts.app')
@section('title', 'Login')

@section('content')
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

        .login-form {
            text-align: left;
            margin-top: 40px;
        }

        .login-form .form-label {
            color: #44516f;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-form .form-control {
            min-height: 52px;
            border-radius: 12px;
            border-color: #d7dfec;
            box-shadow: none;
            padding-left: 16px;
            padding-right: 16px;
        }

        .login-form .form-control:focus {
            border-color: #1784ec;
            box-shadow: 0 0 0 0.18rem rgba(23, 132, 236, 0.12);
        }

        .login-form .form-check-label,
        .login-form .forgot-link {
            color: #6f7f9f;
            font-size: 0.95rem;
        }

        .login-form .forgot-link {
            text-decoration: none;
        }

        .login-form .forgot-link:hover {
            color: #1269d9;
            text-decoration: underline;
        }

        .login-submit {
            margin-top: 28px;
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 15px 24px;
            background: linear-gradient(180deg, #1784ec 0%, #0f75d9 100%);
            color: #ffffff;
            font-size: 1.02rem;
            font-weight: 700;
            box-shadow: 0 16px 28px rgba(22, 120, 219, 0.22);
        }

        .login-submit:hover,
        .login-submit:focus {
            color: #ffffff;
            filter: brightness(1.02);
        }

        .google-btn {
            margin: 56px auto 42px;
            width: 100%;
            max-width: 368px;
            border: 1px solid #d7dfec;
            border-radius: 14px;
            padding: 16px 24px;
            background: #ffffff;
            color: #24304a;
            font-size: 1.02rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 12px 24px rgba(36, 48, 74, 0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
        }

        .google-btn:focus,
        .google-btn:hover {
            color: #24304a;
            border-color: #b9c5d8;
            transform: translateY(-1px);
        }

        .google-logo {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 0 0 42px;
            color: #c3cad8;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #d9e1ef;
        }

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
                    {{-- <img src="{{ asset('assets/images/logo-dark.png') }}" alt="PESO logo"> --}}
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

                <a href="{{ route('google.login') }}" class="google-btn" aria-label="Sign in with Google">
                    <svg class="google-logo" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.3 9.14 5.38 12 5.38z"/>
                    </svg>
                    Sign in with Google
                </a>

                <div class="divider">Authorized access</div>

                <form class="login-form" action="{{ route('login') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Username</label>
                        <input
                            type="text"
                            class="form-control @error('email') is-invalid @enderror"
                            id="email"
                            name="email"
                            placeholder="Enter username"
                            value="{{ old('email') }}"
                        >
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <label class="form-label mb-0" for="password-input">Password</label>
                            <a href="{{ route('forget-password') }}" class="forgot-link">Forgot password?</a>
                        </div>
                        <div class="position-relative mt-2">
                            <input
                                type="password"
                                class="form-control pe-5 password-input @error('password') is-invalid @enderror"
                                id="password-input"
                                name="password"
                                placeholder="Enter password"
                            >
                            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon" aria-label="Toggle password visibility">
                                <i class="ri-eye-fill align-middle"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="auth-remember-check" name="remember">
                        <label class="form-check-label" for="auth-remember-check">Remember me</label>
                    </div>

                    <button type="submit" class="login-submit">Sign In</button>
                </form>

                <p class="support-copy mt-4">
                    Need assistance? Contact the <strong>IT Support Team</strong> for help accessing your account.
                </p>
            </div>
        </section>
    </div>
@endsection
