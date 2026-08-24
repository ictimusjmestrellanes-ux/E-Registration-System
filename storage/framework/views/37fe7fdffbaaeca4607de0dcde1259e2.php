<?php $__env->startSection('title', 'ERS | Profile Settings'); ?>
<?php $__env->startSection('content'); ?>
    <!-- Page-content -->
    <style>
        .profile-setting-img {
            position: relative;
            max-height: none;
        }

        .profile-setting-img::before,
        .profile-setting-img::after {
            content: none !important;
            display: none !important;
            background: none !important;
        }

        .profile-setting-img .profile-wid-img {
            display: block;
            width: 100%;
            height: auto;
            max-height: none;
            object-fit: contain;
            opacity: 1 !important;
            filter: none !important;
        }

        .profile-setting-img .overlay-content {
            background: transparent !important;
            backdrop-filter: none !important;
            z-index: 2;
        }

        .profile-settings-panel .card-header,
        .profile-settings-panel .card-body {
            background-color: transparent;
        }

        .profile-settings-panel {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.82) 0%, rgba(255, 255, 255, 0.42) 100%);
            border: 1px solid rgba(255, 255, 255, 0.55);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
    </style>
    <div class="container-fluid">
        <div class="position-relative mx-n4 mt-n4">
            <div class="profile-setting-img">
                <?php
                    $profileCover = auth()->user()?->cover_url ?? asset('assets/images/city-hall1.jpg');
                ?>
                <img src="<?php echo e($profileCover); ?>" class="profile-wid-img" alt="Profile cover">
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
                <div class="card mt-n9 profile-settings-panel" style="height: 300px">
                    <div class="card-body p-4">
                        <div class="text-center">
                            <?php
                                $profileAvatar = auth()->user()?->avatar_url;
                            ?>
                            <div class="profile-user position-relative d-inline-block mx-auto  mb-4">
                                <img src="<?php echo e($profileAvatar); ?>" class="rounded-circle avatar-xl img-thumbnail user-profile-image material-shadow" alt="user-profile-image">
                                <div class="avatar-xs p-0 rounded-circle profile-photo-edit">
                                    <input id="profile-img-file-input" name="avatar" type="file" class="profile-img-file-input" form="profile-update-form" accept="image/*">
                                    <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                                        <span class="avatar-title rounded-circle bg-light text-body material-shadow">
                                            <i class="ri-camera-fill"></i>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <h5 class="fs-16 mb-1"><?php echo e(auth()->user()->name ?? 'User'); ?></h5>
                        </div>
                    </div>
                </div>
                <!--end card-->

                <!-- Analog Clock Card -->
                <div class="card profile-settings-panel">
                    <div class="card-body p-4">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3 text-center">
                            <i class="ri-time-line me-1"></i> Clock
                        </h6>
                        <div class="d-flex justify-content-center">
                            <canvas id="analogClock" width="200" height="200" style="max-width: 100%;"></canvas>
                        </div>
                        <div class="text-center mt-3">
                            <span id="digitalClockDisplay" class="fs-5 fw-semibold text-primary"></span>
                            <br>
                            <span id="digitalDateDisplay" class="text-muted small"></span>
                        </div>
                    </div>
                </div>
                <!--end clock card-->

                
            </div>
            <!--end col-->
            <div class="col-xxl-9">
                <div class="card mt-xxl-n9 profile-settings-panel">
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
                                <form id="profile-update-form" action="<?php echo e(route('profile.update')); ?>" method="POST" enctype="multipart/form-data">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PUT'); ?>
                                    <input type="hidden" id="profile-cover-photo-data" name="cover_photo_data" value="">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="mb-3">
                                                <label for="nameInput" class="form-label">Name</label>
                                                <input type="text" class="form-control" id="nameInput" name="name" placeholder="Enter your name" value="<?php echo e(old('name', auth()->user()->name ?? '')); ?>">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-6">
                                            <div class="mb-3">
                                                <label for="phoneInput" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="phoneInput" name="phone_number" placeholder="Enter your phone number" value="<?php echo e(old('phone_number', auth()->user()->phone_number ?? '')); ?>">
                                            </div>
                                        </div>
                                        <!--end col-->
                                        <div class="col-lg-12">
                                            <div class="mb-3">
                                                <label for="emailInput" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="emailInput" name="email" placeholder="Enter your email" value="<?php echo e(old('email', auth()->user()->email ?? '')); ?>">
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
                
                <!-- Calendar Card -->
                <div class="card profile-settings-panel">
                    <div class="card-body p-3" style="height: 335px;">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3 text-center">
                            <i class="ri-calendar-line me-1"></i> Calendar
                        </h6>
                        <div id="profileCalendar">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <button type="button" class="btn btn-sm btn-soft-primary" id="calPrevMonth">
                                    <i class="ri-arrow-left-s-line"></i>
                                </button>
                                <span id="calMonthYear" class="fw-semibold"></span>
                                <button type="button" class="btn btn-sm btn-soft-primary" id="calNextMonth">
                                    <i class="ri-arrow-right-s-line"></i>
                                </button>
                            </div>
                            <table class="table table-sm table-borderless text-center mb-0" id="calTable">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>Su</th><th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th>
                                    </tr>
                                </thead>
                                <tbody id="calBody" style="height: 220px"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!--end calendar card-->

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
    
    <?php $__env->startSection('script'); ?>
  <!-- profile-setting init js -->
    <script src="<?php echo e(asset('assets/js/profile-setting.init.js')); ?>"></script>

    <!-- Analog Clock & Calendar -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // ── Analog Clock ─────────────────────────────────────────────
        const clockCanvas = document.getElementById('analogClock');
        const digitalClock = document.getElementById('digitalClockDisplay');
        const digitalDate = document.getElementById('digitalDateDisplay');

        if (clockCanvas && clockCanvas.getContext) {
            const cCtx = clockCanvas.getContext('2d');
            const radius = clockCanvas.width / 2;

            function drawClock() {
                const now = new Date();
                const h = now.getHours() % 12;
                const m = now.getMinutes();
                const s = now.getSeconds();

                cCtx.save();
                cCtx.clearRect(0, 0, clockCanvas.width, clockCanvas.height);
                cCtx.translate(radius, radius);

                // Face
                cCtx.beginPath();
                cCtx.arc(0, 0, radius - 4, 0, 2 * Math.PI);
                cCtx.fillStyle = '#fff';
                cCtx.fill();
                cCtx.lineWidth = 3;
                cCtx.strokeStyle = '#405189';
                cCtx.stroke();

                // Hour markers
                for (let i = 0; i < 12; i++) {
                    const ang = (i * Math.PI) / 6;
                    const isMajor = i % 3 === 0;
                    const outerR = radius - 10;
                    const innerR = isMajor ? radius - 26 : radius - 20;
                    cCtx.beginPath();
                    cCtx.moveTo(Math.cos(ang) * innerR, Math.sin(ang) * innerR);
                    cCtx.lineTo(Math.cos(ang) * outerR, Math.sin(ang) * outerR);
                    cCtx.lineWidth = isMajor ? 3 : 1.5;
                    cCtx.strokeStyle = '#405189';
                    cCtx.stroke();
                }

                // Minute markers
                for (let i = 0; i < 60; i++) {
                    if (i % 5 !== 0) {
                        const ang = (i * Math.PI) / 30;
                        cCtx.beginPath();
                        cCtx.arc(Math.cos(ang) * (radius - 12), Math.sin(ang) * (radius - 12), 1, 0, 2 * Math.PI);
                        cCtx.fillStyle = '#adb5bd';
                        cCtx.fill();
                    }
                }

                // Hour numbers
                cCtx.font = 'bold 14px sans-serif';
                cCtx.fillStyle = '#405189';
                cCtx.textAlign = 'center';
                cCtx.textBaseline = 'middle';
                for (let n = 1; n <= 12; n++) {
                    const ang = (n * Math.PI) / 6 - Math.PI / 2;
                    const nr = radius - 36;
                    cCtx.fillText(n.toString(), Math.cos(ang) * nr, Math.sin(ang) * nr);
                }

                // Hands helper
                function drawHand(angle, length, width, color) {
                    cCtx.beginPath();
                    cCtx.lineWidth = width;
                    cCtx.lineCap = 'round';
                    cCtx.strokeStyle = color;
                    cCtx.moveTo(0, 0);
                    cCtx.lineTo(
                        Math.cos(angle) * length,
                        Math.sin(angle) * length
                    );
                    cCtx.stroke();
                }

                // Hour hand
                const hAngle = ((h + m / 60) * Math.PI) / 6 - Math.PI / 2;
                drawHand(hAngle, radius * 0.5, 5, '#405189');

                // Minute hand
                const mAngle = ((m + s / 60) * Math.PI) / 30 - Math.PI / 2;
                drawHand(mAngle, radius * 0.7, 3, '#0ab39c');

                // Second hand
                const sAngle = (s * Math.PI) / 30 - Math.PI / 2;
                drawHand(sAngle, radius * 0.78, 1.5, '#f06548');

                // Center dot
                cCtx.beginPath();
                cCtx.arc(0, 0, 4, 0, 2 * Math.PI);
                cCtx.fillStyle = '#405189';
                cCtx.fill();

                cCtx.restore();

                // Digital readout
                if (digitalClock) {
                    digitalClock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                if (digitalDate) {
                    digitalDate.textContent = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                }
            }

            drawClock();
            setInterval(drawClock, 1000);
        }

        // ── Calendar ─────────────────────────────────────────────────
        const calBody = document.getElementById('calBody');
        const calMonthYear = document.getElementById('calMonthYear');
        const calPrev = document.getElementById('calPrevMonth');
        const calNext = document.getElementById('calNextMonth');

        if (calBody && calMonthYear && calPrev && calNext) {
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            let calDate = new Date();

            function renderCalendar() {
                const today = new Date();
                const year = calDate.getFullYear();
                const month = calDate.getMonth();
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();

                calMonthYear.textContent = months[month] + ' ' + year;
                calBody.innerHTML = '';

                let row = document.createElement('tr');
                for (let i = 0; i < firstDay; i++) {
                    row.appendChild(document.createElement('td'));
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const td = document.createElement('td');
                    td.textContent = d;
                    td.style.cursor = 'default';
                    td.style.borderRadius = '50%';
                    td.style.padding = '4px 0';

                    const isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                    if (isToday) {
                        td.style.background = '#405189';
                        td.style.color = '#fff';
                        td.style.fontWeight = 'bold';
                        td.style.borderRadius = '50%';
                    }

                    row.appendChild(td);
                    if ((firstDay + d) % 7 === 0) {
                        calBody.appendChild(row);
                        row = document.createElement('tr');
                    }
                }

                if (row.children.length > 0) {
                    while (row.children.length < 7) {
                        row.appendChild(document.createElement('td'));
                    }
                    calBody.appendChild(row);
                }
            }

            calPrev.addEventListener('click', function () {
                calDate.setMonth(calDate.getMonth() - 1);
                renderCalendar();
            });

            calNext.addEventListener('click', function () {
                calDate.setMonth(calDate.getMonth() + 1);
                renderCalendar();
            });

            renderCalendar();
        }
    });
    </script>
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
    <?php $__env->stopSection(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/client_profile/settings.blade.php ENDPATH**/ ?>