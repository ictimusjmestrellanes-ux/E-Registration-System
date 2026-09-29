<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default" data-bs-theme="light"><head>

    <meta charset="utf-8">
    <title>@yield('title', 'Error')</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/City-Logo.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description">
    <meta content="E-Registration System" name="author">
    <!-- App favicon -->
    {{-- <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}"> --}}
    <!-- Apply the saved color mode before styles load to prevent a light-mode flash. -->
    <script src="{{ asset('assets/js/theme-mode.js') }}"></script>
    <!-- Layout config Js -->
    <script src="{{ asset('assets/js/layout.js') }}"></script>
    <!-- Bootstrap Css -->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
    <!-- Icons Css -->
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">
    <!-- App Css-->
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css">
    <!-- custom Css-->
    <link href="{{ asset('assets/css/custom.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/theme-mode.css') }}" rel="stylesheet" type="text/css">

</head>
<body>

    <button type="button" class="btn btn-icon btn-light rounded-circle material-shadow theme-mode-floating" data-color-mode-toggle aria-label="Switch to night mode" aria-pressed="false" title="Switch to night mode">
        <i class="bx bx-moon fs-22" data-color-mode-icon aria-hidden="true"></i>
    </button>

    <!-- auth-page wrapper -->
    <div class="auth-page-wrapper py-5 d-flex justify-content-center align-items-center min-vh-100">
        <!-- auth-page content -->
        <div class="auth-page-content overflow-hidden p-0">
            <div class="container">
                @yield('content')
            </div>
            <!-- end container -->
        </div>
        <!-- end auth-page content -->
    </div>
    <!-- end auth-page-wrapper -->

</body>
</html>
