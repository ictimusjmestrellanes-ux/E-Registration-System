@extends('layouts.master')
@section('title', 'Profile')
@section('content')
    <!-- Page-content -->
    <style>
        .profile-cover-shell {
            min-height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(64, 81, 137, 0.18), rgba(64, 81, 137, 0.06));
        }

        .profile-cover-placeholder {
            min-height: 220px;
            width: 100%;
            background: linear-gradient(135deg, rgba(64, 81, 137, 0.18), rgba(64, 81, 137, 0.06));
        }
    </style>
    <div class="container-fluid">
        <div class="profile-foreground position-relative mx-n4 mt-n4">
            <div class="profile-wid-bg profile-cover-shell">
                @php
                    $profileCover = auth()->user()?->cover_photo ? asset('storage/' . auth()->user()->cover_photo) : null;
                @endphp
                @if ($profileCover)
                    <img src="{{ $profileCover }}" alt="" class="profile-wid-img">
                @else
                    <div class="profile-cover-placeholder profile-wid-img" aria-hidden="true"></div>
                @endif
            </div>
        </div>
        <div class="pt-4 mb-4 mb-lg-3 pb-lg-4 profile-wrapper">
            <div class="row g-4">
                <div class="col-auto">
                    <div class="avatar-lg">
                        @php
                            $profileAvatar = auth()->user()?->avatar ? asset('storage/' . auth()->user()->avatar) : asset('assets/images/avatar-1.jpg');
                        @endphp
                        <img src="{{ $profileAvatar }}" alt="user-img"
                            class="img-thumbnail rounded-circle">
                    </div>
                </div>
                <!--end col-->
                <div class="col">
                    <div class="p-2">
                        <h3 class="text-white mb-1">{{ auth()->user()?->name ?? 'User' }}</h3>
                    </div>
                </div>

            </div>
            <!--end row-->
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div>
                    <div class="d-flex profile-wrapper">
                        <!-- Nav tabs -->
                        <ul class="nav nav-pills animation-nav profile-nav gap-2 gap-lg-3 flex-grow-1" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link fs-14 active" data-bs-toggle="tab" href="#overview-tab" role="tab">
                                    <i class="ri-airplay-fill d-inline-block d-md-none"></i> <span
                                        class="d-none d-md-inline-block">Overview</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fs-14" data-bs-toggle="tab" href="#activities" role="tab">
                                    <i class="ri-list-unordered d-inline-block d-md-none"></i> <span
                                        class="d-none d-md-inline-block">Activities</span>
                                </a>
                            </li>
                        </ul>
                        <div class="flex-shrink-0">
                            <a href="pages-profile-settings.html" class="btn btn-success"><i
                                    class="ri-edit-box-line align-bottom"></i> Edit Profile</a>
                        </div>
                    </div>
                    <!-- Tab panes -->
                    <div class="tab-content pt-4 text-muted">
                        <div class="tab-pane active" id="overview-tab" role="tabpanel">
                            <div class="row">
                                <div class="col-xxl-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">Info</h5>
                                            <div class="table-responsive">
                                                <table class="table table-borderless mb-0">
                                                    <tbody>
                                                        <tr>
                                                            <th class="ps-0" scope="row">Full Name :</th>
                                                            <td class="text-muted">{{ auth()->user()?->name ?? 'User' }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th class="ps-0" scope="row">Mobile :</th>
                                                            <td class="text-muted">{{ auth()->user()->phone_number ?? '' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th class="ps-0" scope="row">E-mail :</th>
                                                            <td class="text-muted">{{ auth()->user()->email ?? '' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th class="ps-0" scope="row">Joining Date</th>
                                                            <td class="text-muted">{{ auth()->user()->created_at->format('d M Y') ?? '' }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- end card body -->
                                    </div><!-- end card -->
                                </div>
                                <!--end col-->
                                <div class="col-xxl-9">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="card">
                                                <div class="card-header align-items-center d-flex">
                                                    <h4 class="card-title mb-0  me-2">Recent Activity</h4>
                                                    <div class="flex-shrink-0 ms-auto">
                                                        <ul class="nav justify-content-end nav-tabs-custom rounded card-header-tabs border-bottom-0"
                                                            role="tablist">
                                                            <li class="nav-item">
                                                                <a class="nav-link active" data-bs-toggle="tab"
                                                                    href="#today" role="tab">
                                                                    Today
                                                                </a>
                                                            </li>
                                                            <li class="nav-item">
                                                                <a class="nav-link" data-bs-toggle="tab" href="#weekly"
                                                                    role="tab">
                                                                    Weekly
                                                                </a>
                                                            </li>
                                                            <li class="nav-item">
                                                                <a class="nav-link" data-bs-toggle="tab" href="#monthly"
                                                                    role="tab">
                                                                    Monthly
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="tab-content text-muted">
                                                        <div class="tab-pane active" id="today" role="tabpanel">
                                                            <div class="profile-timeline">
                                                                <div class="accordion accordion-flush" id="todayExample">
                                                                </div>
                                                                <!--end accordion-->
                                                            </div>
                                                        </div>
                                                        <div class="tab-pane" id="weekly" role="tabpanel">
                                                            <div class="profile-timeline">
                                                                <div class="accordion accordion-flush" id="weeklyExample">
                                                                </div>
                                                                <!--end accordion-->
                                                            </div>
                                                        </div>
                                                        <div class="tab-pane" id="monthly" role="tabpanel">
                                                            <div class="profile-timeline">
                                                                <div class="accordion accordion-flush" id="monthlyExample">
                                                                </div>
                                                                <!--end accordion-->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div><!-- end card body -->
                                            </div><!-- end card -->
                                        </div><!-- end col -->
                                    </div><!-- end row -->
                                </div>
                                <!--end col-->
                            </div>
                            <!--end row-->
                        </div>
                        <div class="tab-pane fade" id="activities" role="tabpanel">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Activities</h5>
                                </div>
                                <!--end card-body-->
                            </div>
                            <!--end card-->
                        </div>
                        <!--end tab-pane-->
                    </div>
                    <!--end tab-content-->
                </div>
            </div>
            <!--end col-->
        </div>
        <!--end row-->

    </div><!-- container-fluid -->
    <!-- End Page-content -->

    @section('script')
        <!-- profile init js -->
        <script src="js/profile.init.js"></script>
    @endsection
@endsection
