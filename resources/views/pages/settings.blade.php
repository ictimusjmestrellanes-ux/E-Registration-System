@extends('layouts.master')
@section('title', 'Profile Settings')
@section('content')
    <!-- Page-content -->
    <style>
        .profile-setting-img {
            overflow: hidden;
        }

        .profile-setting-img::before,
        .profile-setting-img::after {
            content: none !important;
            display: none !important;
            background: none !important;
        }

        .profile-setting-img .profile-wid-img {
            opacity: 1 !important;
            filter: none !important;
        }

        .profile-setting-img .overlay-content {
            background: transparent !important;
            backdrop-filter: none !important;
            z-index: 2;
        }
    </style>
    <div class="container-fluid">
        <div class="position-relative mx-n4 mt-n4">
            <div class="profile-wid-bg profile-setting-img">
                @php
                    $profileCover = auth()->user()?->cover_photo ? asset('storage/' . auth()->user()->cover_photo) : asset('assets/images/profile-bg.jpg');
                @endphp
                <img src="{{ $profileCover }}" class="profile-wid-img" alt="">
                <div class="overlay-content">
                    <div class="text-end p-3">
                        <div class="p-0 ms-auto rounded-circle profile-photo-edit">
                            <input
                                id="profile-cover-img-file-input"
                                type="file"
                                class="profile-foreground-img-file-input d-none"
                                accept="image/*"
                            >
                            <label for="profile-cover-img-file-input" class="profile-photo-edit btn btn-light">
                                <i class="ri-image-edit-line align-bottom me-1"></i> Change Cover
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xxl-3">
                <div class="card mt-n5">
                    <div class="card-body p-4">
                        <div class="text-center">
                            @php
                                $profileAvatar = auth()->user()?->avatar ? asset('storage/' . auth()->user()->avatar) : asset('assets/images/avatar-1.jpg');
                            @endphp
                            <div class="profile-user position-relative d-inline-block mx-auto  mb-4">
                                <img src="{{ $profileAvatar }}" class="rounded-circle avatar-xl img-thumbnail user-profile-image material-shadow" alt="user-profile-image">
                                <div class="avatar-xs p-0 rounded-circle profile-photo-edit">
                                    <input id="profile-img-file-input" name="avatar" type="file" class="profile-img-file-input" form="profile-update-form" accept="image/*">
                                    <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                                        <span class="avatar-title rounded-circle bg-light text-body material-shadow">
                                            <i class="ri-camera-fill"></i>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <h5 class="fs-16 mb-1">{{ auth()->user()->name ?? 'User' }}</h5>
                        </div>
                    </div>
                </div>
                <!--end card-->
                
                
            </div>
            <!--end col-->
            <div class="col-xxl-9">
                <div class="card mt-xxl-n5">
                    <div class="card-header">
                        <ul class="nav nav-tabs-custom rounded card-header-tabs border-bottom-0" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#personalDetails" role="tab">
                                    <i class="fas fa-home"></i> Personal Details
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#changePassword" role="tab">
                                    <i class="far fa-user"></i> Change Password
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-4">
                        <div class="tab-content">
                            <div class="tab-pane active" id="personalDetails" role="tabpanel">
                                <form id="profile-update-form" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" id="profile-cover-photo-data" name="cover_photo_data" value="">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="mb-3">
                                                <label for="nameInput" class="form-label">Name</label>
                                                <input type="text" class="form-control" id="nameInput" name="name" placeholder="Enter your name" value="{{ old('name', auth()->user()->name ?? '') }}">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-6">
                                            <div class="mb-3">
                                                <label for="phoneInput" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="phoneInput" name="phone_number" placeholder="Enter your phone number" value="{{ old('phone_number', auth()->user()->phone_number ?? '') }}">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-12">
                                            <div class="mb-3">
                                                <label for="emailInput" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="emailInput" name="email" placeholder="Enter your email" value="{{ old('email', auth()->user()->email ?? '') }}">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-12">
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="submit" class="btn btn-primary">Update</button>
                                                <button type="reset" class="btn btn-soft-success" style="min-width: 170px;">Cancel</button>
                                            </div>
                                        </div>
                                        <!--end col-->
                                    </div>
                                    <!--end row-->
                                </form>
                            </div>
                            <!--end tab-pane-->
                            <div class="tab-pane" id="changePassword" role="tabpanel">
                                <form action="javascript:void(0);">
                                    <div class="row g-2">
                                        <div class="col-lg-4">
                                            <div>
                                                <label for="oldpasswordInput" class="form-label">Old Password*</label>
                                                <input type="password" class="form-control" id="oldpasswordInput" placeholder="Enter current password">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-4">
                                            <div>
                                                <label for="newpasswordInput" class="form-label">New Password*</label>
                                                <input type="password" class="form-control" id="newpasswordInput" placeholder="Enter new password">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-4">
                                            <div>
                                                <label for="confirmpasswordInput" class="form-label">Confirm Password*</label>
                                                <input type="password" class="form-control" id="confirmpasswordInput" placeholder="Confirm password">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-12">
                                            <div class="mb-3">
                                                <a href="javascript:void(0);" class="link-primary text-decoration-underline">Forgot Password ?</a>
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-12">
                                            <div class="text-end">
                                                <button type="submit" class="btn btn-success">Change Password</button>
                                            </div>
                                        </div>
                                        <!--end col-->
                                    </div>
                                    <!--end row-->
                                </form>
                            </div>
                            <!--end tab-pane-->
                        </div>
                    </div>
                </div>
            </div>
            <!--end col-->
        </div>
        <!--end row-->
        
    </div>
    <!-- End Page-content -->

    <div class="modal fade" id="coverPhotoEditorModal" tabindex="-1" aria-labelledby="coverPhotoEditorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="coverPhotoEditorModalLabel">Adjust Cover Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="border rounded-3 bg-dark-subtle p-2 overflow-hidden">
                                <canvas id="coverPhotoEditorCanvas" class="w-100 d-block" style="cursor: move; touch-action: none;"></canvas>
                            </div>
                            <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
                                <label for="coverPhotoZoomRange" class="form-label mb-0 fw-semibold">Zoom</label>
                                <input type="range" id="coverPhotoZoomRange" class="form-range flex-grow-1" min="1" max="3" step="0.01" value="1">
                                <div class="btn-group" role="group" aria-label="Cover move controls">
                                    <button type="button" class="btn btn-soft-secondary" id="coverPhotoMoveUpBtn">
                                        <i class="ri-arrow-up-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-soft-secondary" id="coverPhotoMoveDownBtn">
                                        <i class="ri-arrow-down-line"></i>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-soft-secondary" id="coverPhotoResetBtn">Reset</button>
                            </div>
                            <p class="text-muted small mb-0 mt-2">Drag the image to move it. Use the slider to resize the cover before saving. Use the arrows to move it up or down.</p>
                        </div>
                        <div class="col-lg-4">
                            <div class="card shadow-none border h-100 mb-0">
                                <div class="card-body">
                                    <h6 class="card-title mb-2">Preview</h6>
                                    <img id="coverPhotoEditorPreview" src="" alt="Cover preview" class="img-fluid rounded-3 border w-100" style="aspect-ratio: 16 / 5; object-fit: cover;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="coverPhotoApplyBtn">Apply Cover</button>
                </div>
            </div>
        </div>
    </div>
    
    @section('script')
  <!-- profile-setting init js -->
    <script src="{{ asset('assets/js/profile-setting.init.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const coverInput = document.getElementById('profile-cover-img-file-input');
            const coverDataInput = document.getElementById('profile-cover-photo-data');
            const coverPreview = document.querySelector('.profile-wid-img');
            const coverEditorModalEl = document.getElementById('coverPhotoEditorModal');
            const coverEditorCanvas = document.getElementById('coverPhotoEditorCanvas');
            const coverEditorPreview = document.getElementById('coverPhotoEditorPreview');
            const coverZoomRange = document.getElementById('coverPhotoZoomRange');
            const coverMoveUpBtn = document.getElementById('coverPhotoMoveUpBtn');
            const coverMoveDownBtn = document.getElementById('coverPhotoMoveDownBtn');
            const coverResetBtn = document.getElementById('coverPhotoResetBtn');
            const coverApplyBtn = document.getElementById('coverPhotoApplyBtn');

            if (!coverInput || !coverDataInput || !coverPreview || !coverEditorModalEl || !coverEditorCanvas || !coverEditorPreview || !coverZoomRange || !coverMoveUpBtn || !coverMoveDownBtn || !coverResetBtn || !coverApplyBtn || !window.bootstrap) {
                return;
            }

            const coverEditorModal = bootstrap.Modal.getOrCreateInstance(coverEditorModalEl);
            const ctx = coverEditorCanvas.getContext('2d');
            const baseWidth = 1600;
            const baseHeight = 420;

            coverEditorCanvas.width = baseWidth;
            coverEditorCanvas.height = baseHeight;

            const state = {
                image: null,
                imageUrl: '',
                scale: 1,
                offsetX: 0,
                offsetY: 0,
                dragging: false,
                dragStartX: 0,
                dragStartY: 0,
                startOffsetX: 0,
                startOffsetY: 0,
                fitScale: 1,
            };

            const resizeCanvas = () => {
                coverEditorCanvas.width = baseWidth;
                coverEditorCanvas.height = baseHeight;
                redraw();
            };

            const redraw = () => {
                if (!state.image) {
                    ctx.clearRect(0, 0, coverEditorCanvas.width, coverEditorCanvas.height);
                    return;
                }

                const drawWidth = state.image.width * state.fitScale * state.scale;
                const drawHeight = state.image.height * state.fitScale * state.scale;
                const x = (coverEditorCanvas.width - drawWidth) / 2 + state.offsetX;
                const y = (coverEditorCanvas.height - drawHeight) / 2 + state.offsetY;

                ctx.clearRect(0, 0, coverEditorCanvas.width, coverEditorCanvas.height);
                ctx.fillStyle = '#1f2937';
                ctx.fillRect(0, 0, coverEditorCanvas.width, coverEditorCanvas.height);
                ctx.drawImage(state.image, x, y, drawWidth, drawHeight);

                coverEditorPreview.src = coverEditorCanvas.toDataURL('image/jpeg', 0.92);
            };

            const resetState = () => {
                state.scale = 1;
                state.offsetX = 0;
                state.offsetY = 0;
                coverZoomRange.value = '1';
                redraw();
            };

            const moveCoverY = (delta) => {
                state.offsetY += delta;
                redraw();
            };

            const loadImage = (file) => {
                if (!file) {
                    return;
                }

                if (state.imageUrl) {
                    URL.revokeObjectURL(state.imageUrl);
                }

                state.imageUrl = URL.createObjectURL(file);
                const image = new Image();
                image.onload = function () {
                    state.image = image;
                    state.fitScale = Math.max(
                        coverEditorCanvas.width / image.width,
                        coverEditorCanvas.height / image.height
                    );
                    resetState();
                    coverEditorModal.show();
                };
                image.src = state.imageUrl;
            };

            coverInput.addEventListener('change', function () {
                const file = this.files?.[0];
                if (file) {
                    loadImage(file);
                }
            });

            coverEditorModalEl.addEventListener('shown.bs.modal', function () {
                resizeCanvas();
            });

            coverZoomRange.addEventListener('input', function () {
                state.scale = parseFloat(this.value || '1');
                redraw();
            });

            coverResetBtn.addEventListener('click', function () {
                resetState();
            });

            coverMoveUpBtn.addEventListener('click', function () {
                moveCoverY(-24);
            });

            coverMoveDownBtn.addEventListener('click', function () {
                moveCoverY(24);
            });

            coverEditorCanvas.addEventListener('pointerdown', function (event) {
                if (!state.image) {
                    return;
                }

                state.dragging = true;
                state.dragStartX = event.clientX;
                state.dragStartY = event.clientY;
                state.startOffsetX = state.offsetX;
                state.startOffsetY = state.offsetY;
                coverEditorCanvas.setPointerCapture(event.pointerId);
            });

            coverEditorCanvas.addEventListener('pointermove', function (event) {
                if (!state.dragging) {
                    return;
                }

                state.offsetX = state.startOffsetX + (event.clientX - state.dragStartX);
                state.offsetY = state.startOffsetY + (event.clientY - state.dragStartY);
                redraw();
            });

            const endDrag = (event) => {
                state.dragging = false;
                try {
                    coverEditorCanvas.releasePointerCapture(event.pointerId);
                } catch (error) {
                    // Ignore if capture was already released.
                }
            };

            coverEditorCanvas.addEventListener('pointerup', endDrag);
            coverEditorCanvas.addEventListener('pointercancel', endDrag);
            coverEditorCanvas.addEventListener('pointerleave', function () {
                state.dragging = false;
            });

            coverApplyBtn.addEventListener('click', function () {
                if (!state.image) {
                    return;
                }

                coverDataInput.value = coverEditorCanvas.toDataURL('image/jpeg', 0.92);
                coverPreview.src = coverDataInput.value;
                coverEditorModal.hide();
            });

            coverEditorModalEl.addEventListener('hidden.bs.modal', function () {
                coverInput.value = '';
                if (state.imageUrl) {
                    URL.revokeObjectURL(state.imageUrl);
                    state.imageUrl = '';
                }
            });
        });
    </script>
    @endsection
@endsection
