@extends('layouts.error')
@section('title', '419 Error')
@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-8">
            <div class="text-center">
                <img src="{{ asset('assets/images/error419.png') }}" 
                    alt="Page Expired" 
                    class="img-fluid">

                <div class="mt-3">
                    <h3 class="text-uppercase fw-bold">Sorry, 419 Page Expired 😭</h3>
                    <p class="text-muted mb-4">
                        The page you are trying to access has expired or is no longer available.
                    </p>

                    <a href="{{ url('/') }}" class="btn btn-success">
                        <i class="mdi mdi-home me-1"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
