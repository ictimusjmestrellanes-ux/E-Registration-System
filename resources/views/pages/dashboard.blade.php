@extends('layouts.master')
@section('title', 'Dashboard')
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Dashboard</h4>
                                <p class="text-muted mb-0">Welcome back, {{ auth()->user()?->name ?? 'User' }}.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Users</p>
                        <h3 class="mb-0">128</h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow">
                    <div class="card-body">
                        <p class="text-muted mb-1">Pending Requests</p>
                        <h3 class="mb-0">24</h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow">
                    <div class="card-body">
                        <p class="text-muted mb-1">Approved Today</p>
                        <h3 class="mb-0">18</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
