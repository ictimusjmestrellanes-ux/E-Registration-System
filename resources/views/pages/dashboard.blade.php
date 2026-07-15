@extends('layouts.master')
@section('title', 'Dashboard')
@section('content')
    @php
        $categoryMeta = [
            'social_services' => ['fa-hand-holding-heart', 'primary'],
            'solicitation'    => ['fa-file-invoice', 'success'],
            'youth_sports'    => ['fa-futbol', 'info'],
            'appointments'    => ['fa-calendar-check', 'warning'],
            'infrastructure'  => ['fa-building', 'secondary'],
            'scholarships'    => ['fa-graduation-cap', 'danger'],
            'permits'         => ['fa-file-contract', 'primary'],
            'events'          => ['fa-calendar-days', 'success'],
            'job_application' => ['fa-briefcase', 'info'],
            'hoa'             => ['fa-house', 'warning'],
            'others'          => ['fa-ellipsis', 'secondary'],
        ];
        $totalCategoryTransactions = array_sum($categoryCounts);
    @endphp

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
                <div class="card material-shadow border-primary border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-users text-primary fs-4"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-muted mb-1">Total Registered Clients</p>
                                <h3 class="mb-0">{{ $totalClients }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow border-success border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-layer-group text-success fs-4"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-muted mb-1">Total Categories</p>
                                <h3 class="mb-0">{{ count($categories) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card material-shadow border-info border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm bg-info bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-receipt text-info fs-4"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-muted mb-1">Total Transactions</p>
                                <h3 class="mb-0">{{ $totalCategoryTransactions }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-0">Service Categories</h5>
                        <p class="text-muted mb-0">Overview of client service requests</p>
                    </div>
                </div>
            </div>

            @foreach ($categories as $key => $label)
                @php
                    [$icon, $color] = $categoryMeta[$key] ?? ['fa-circle', 'secondary'];
                    $count = $categoryCounts[$key] ?? 0;
                @endphp
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card material-shadow h-100">
                        <div class="card-body text-center">
                            <div class="avatar-md bg-{{ $color }} bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                <i class="fa-solid {{ $icon }} text-{{ $color }} fs-3"></i>
                            </div>
                            <h4 class="mb-1">{{ $count }}</h4>
                            <p class="text-muted mb-0">{{ $label }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
