<?php $__env->startSection('title', 'Client List'); ?>
<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client List</h4>
                                <p class="text-muted mb-0">View all registered clients here.</p>
                            </div>
                            <a href="<?php echo e(route('clients')); ?>" class="btn btn-primary">Add Client</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Civil Status</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Address</th>
                                        <th>Province</th>
                                        <th>City</th>
                                        <th>Barangay</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><?php echo e($loop->iteration); ?></td>
                                            <td>
                                                <?php
                                                    $clientPhoto = $client->photo_path ? asset('storage/' . $client->photo_path) : asset('assets/images/avatar-1.jpg');
                                                ?>
                                                <button
                                                    type="button"
                                                    class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#clientPhotoModal"
                                                    data-client-photo="<?php echo e($clientPhoto); ?>"
                                                    data-client-name="<?php echo e(trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name)); ?>"
                                                >
                                                    <img
                                                        src="<?php echo e($clientPhoto); ?>"
                                                        alt="Client Photo"
                                                        class="rounded-3 border object-fit-cover"
                                                        style="width: 72px; height: 72px;"
                                                    >
                                                </button>
                                            </td>
                                            <td><?php echo e(trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name)); ?></td>
                                            <td><?php echo e($client->age ?? '-'); ?></td>
                                            <td><?php echo e($client->gender ?? '-'); ?></td>
                                            <td><?php echo e($client->civil_status ?? '-'); ?></td>
                                            <td><?php echo e($client->email ?? '-'); ?></td>
                                            <td><?php echo e($client->contact ?? '-'); ?></td>
                                            <td><?php echo e($client->address ?? '-'); ?></td>
                                            <td><?php echo e($client->province ?? '-'); ?></td>
                                            <td><?php echo e($client->city ?? '-'); ?></td>
                                            <td><?php echo e($client->barangay ?? '-'); ?></td>
                                            <td>
                                                <div class="d-flex gap-2 text-center justify-content-center">
                                                    <a href="<?php echo e(route('clients.show', $client)); ?>" class="btn btn-sm btn-soft-info">
                                                        View
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-soft-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editClientModal"
                                                        data-update-url="<?php echo e(route('clients.update', $client)); ?>"
                                                        data-client-id="<?php echo e($client->id); ?>"
                                                        data-first-name="<?php echo e($client->first_name); ?>"
                                                        data-middle-name="<?php echo e($client->middle_name); ?>"
                                                        data-last-name="<?php echo e($client->last_name); ?>"
                                                        data-age="<?php echo e($client->age); ?>"
                                                        data-gender="<?php echo e($client->gender); ?>"
                                                        data-civil-status="<?php echo e($client->civil_status); ?>"
                                                        data-email="<?php echo e($client->email); ?>"
                                                        data-contact="<?php echo e($client->contact); ?>"
                                                        data-address="<?php echo e($client->address); ?>"
                                                        data-province="<?php echo e($client->province); ?>"
                                                        data-city="<?php echo e($client->city); ?>"
                                                        data-barangay="<?php echo e($client->barangay); ?>"
                                                        data-client-name="<?php echo e(trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name)); ?>"
                                                        data-client-photo="<?php echo e($clientPhoto); ?>"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form action="<?php echo e(route('clients.archive', $client)); ?>" method="POST" onsubmit="return confirm('Are you sure you want to archive this client?');">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-sm btn-soft-warning">
                                                            Archive
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="13" class="text-center text-muted py-4">
                                                No clients found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientPhotoModal" tabindex="-1" aria-labelledby="clientPhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientPhotoModalLabel">Client Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="clientPhotoModalImage" src="" alt="Client Photo Preview" class="img-fluid rounded-3 border object-fit-cover" style="max-height: 420px;">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editClientForm" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 d-flex align-items-center gap-3">
                                <img id="editClientPhoto" src="" alt="Client Photo" class="rounded-3 border object-fit-cover" style="width: 72px; height: 72px;">
                                <div>
                                    <div class="fw-semibold" id="editClientName"></div>
                                    <div class="text-muted small">Edit client details below.</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Change Photo</label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-soft-primary" id="editOpenCameraBtn">Open Camera</button>
                                    <button type="button" class="btn btn-primary" id="editCapturePhotoBtn" disabled>Capture Photo</button>
                                    <button type="button" class="btn btn-soft-success" id="editRetakePhotoBtn" disabled>Retake</button>
                                </div>
                                <div id="editCameraWrapper" class="border rounded p-2 bg-light d-none">
                                    <video id="editCameraView" class="rounded w-100" autoplay playsinline style="max-height: 320px; object-fit: cover; transform: scaleX(-1);"></video>
                                </div>
                                <canvas id="editCameraCanvas" class="d-none"></canvas>
                                <input type="hidden" id="editPhotoData" name="photo_data">
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label" for="editFirstName">First Name</label>
                                <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editMiddleName">Middle Name</label>
                                <input type="text" class="form-control" id="editMiddleName" name="middle_name">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editLastName">Last Name</label>
                                <input type="text" class="form-control" id="editLastName" name="last_name" required>
                            </div>

                            <div class="col-lg-2">
                                <label class="form-label" for="editAge">Age</label>
                                <input type="number" class="form-control" id="editAge" name="age" min="0">
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label" for="editGender">Gender</label>
                                <select class="form-select" id="editGender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label" for="editCivilStatus">Civil Status</label>
                                <select class="form-select" id="editCivilStatus" name="civil_status">
                                    <option value="">Select civil status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Separated">Separated</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Annulled">Annulled</option>
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editContact">Contact</label>
                                <input type="text" class="form-control" id="editContact" name="contact" maxlength="11" inputmode="numeric">
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label" for="editEmail">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email">
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="editAddress">Address</label>
                                <input type="text" class="form-control" id="editAddress" name="address">
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label" for="editProvince">Province</label>
                                <input type="text" class="form-control" id="editProvince" name="province">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editCity">City</label>
                                <input type="text" class="form-control" id="editCity" name="city">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="editBarangay">Barangay</label>
                                <input type="text" class="form-control" id="editBarangay" name="barangay">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('clientPhotoModal');
            const modalImage = document.getElementById('clientPhotoModalImage');
            const modalTitle = document.getElementById('clientPhotoModalLabel');
            const editModalEl = document.getElementById('editClientModal');
            const editForm = document.getElementById('editClientForm');
            const editTitle = document.getElementById('editClientModalLabel');
            const editPhoto = document.getElementById('editClientPhoto');
            const editName = document.getElementById('editClientName');
            const editFirstName = document.getElementById('editFirstName');
            const editMiddleName = document.getElementById('editMiddleName');
            const editLastName = document.getElementById('editLastName');
            const editAge = document.getElementById('editAge');
            const editGender = document.getElementById('editGender');
            const editCivilStatus = document.getElementById('editCivilStatus');
            const editContact = document.getElementById('editContact');
            const editEmail = document.getElementById('editEmail');
            const editAddress = document.getElementById('editAddress');
            const editProvince = document.getElementById('editProvince');
            const editCity = document.getElementById('editCity');
            const editBarangay = document.getElementById('editBarangay');
            const editOpenCameraBtn = document.getElementById('editOpenCameraBtn');
            const editCapturePhotoBtn = document.getElementById('editCapturePhotoBtn');
            const editRetakePhotoBtn = document.getElementById('editRetakePhotoBtn');
            const editCameraWrapper = document.getElementById('editCameraWrapper');
            const editCameraView = document.getElementById('editCameraView');
            const editCameraCanvas = document.getElementById('editCameraCanvas');
            const editPhotoData = document.getElementById('editPhotoData');

            if (!modalEl || !modalImage || !modalTitle || !editModalEl || !editForm || !editFirstName || !editLastName || !editAge || !editGender || !editCivilStatus || !editContact || !editEmail || !editAddress || !editProvince || !editCity || !editBarangay || !editPhoto || !editName || !editTitle || !editOpenCameraBtn || !editCapturePhotoBtn || !editRetakePhotoBtn || !editCameraWrapper || !editCameraView || !editCameraCanvas || !editPhotoData) {
                return;
            }

            let editStream = null;
            const stopEditCamera = () => {
                if (editStream) {
                    editStream.getTracks().forEach((track) => track.stop());
                    editStream = null;
                }
                editCameraView.srcObject = null;
                editCapturePhotoBtn.disabled = true;
                editRetakePhotoBtn.disabled = false;
                editCameraWrapper.classList.add('d-none');
            };

            const startEditCamera = async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Camera capture is not supported in this browser.');
                    return;
                }

                try {
                    editStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false
                    });

                    editCameraView.srcObject = editStream;
                    editCameraWrapper.classList.remove('d-none');
                    editCapturePhotoBtn.disabled = false;
                    editRetakePhotoBtn.disabled = true;
                } catch (error) {
                    alert('Unable to access the camera. Please allow camera permissions and try again.');
                }
            };

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                const photo = trigger.getAttribute('data-client-photo');
                const name = trigger.getAttribute('data-client-name') || 'Client Photo';

                modalImage.src = photo || '';
                modalTitle.textContent = name;
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                modalImage.src = '';
                modalTitle.textContent = 'Client Photo';
            });

            editModalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                editForm.action = trigger.getAttribute('data-update-url') || editForm.action;
                editTitle.textContent = `Edit ${trigger.getAttribute('data-client-name') || 'Client'}`;
                editName.textContent = trigger.getAttribute('data-client-name') || 'Client';
                editPhoto.src = trigger.getAttribute('data-client-photo') || '';
                editFirstName.value = trigger.getAttribute('data-first-name') || '';
                editMiddleName.value = trigger.getAttribute('data-middle-name') || '';
                editLastName.value = trigger.getAttribute('data-last-name') || '';
                editAge.value = trigger.getAttribute('data-age') || '';
                editGender.value = trigger.getAttribute('data-gender') || '';
                editCivilStatus.value = trigger.getAttribute('data-civil-status') || '';
                editContact.value = trigger.getAttribute('data-contact') || '';
                editEmail.value = trigger.getAttribute('data-email') || '';
                editAddress.value = trigger.getAttribute('data-address') || '';
                editProvince.value = trigger.getAttribute('data-province') || '';
                editCity.value = trigger.getAttribute('data-city') || '';
                editBarangay.value = trigger.getAttribute('data-barangay') || '';
                editPhoto.dataset.original = trigger.getAttribute('data-client-photo') || '';
                editPhotoData.value = '';
                editCameraWrapper.classList.add('d-none');
                editCapturePhotoBtn.disabled = true;
                editRetakePhotoBtn.disabled = true;
                editCameraView.srcObject = null;
            });

            editModalEl.addEventListener('hidden.bs.modal', function () {
                editForm.action = '';
                editTitle.textContent = 'Edit Client';
                editName.textContent = '';
                editPhoto.src = '';
                editPhotoData.value = '';
                stopEditCamera();
                editForm.reset();
            });

            editOpenCameraBtn.addEventListener('click', function () {
                startEditCamera();
            });

            editCapturePhotoBtn.addEventListener('click', function () {
                const context = editCameraCanvas.getContext('2d');
                editCameraCanvas.width = editCameraView.videoWidth || 200;
                editCameraCanvas.height = editCameraView.videoHeight || 200;
                context.save();
                context.translate(editCameraCanvas.width, 0);
                context.scale(-1, 1);
                context.drawImage(editCameraView, 0, 0, editCameraCanvas.width, editCameraCanvas.height);
                context.restore();

                const imageData = editCameraCanvas.toDataURL('image/png');
                editPhoto.src = imageData;
                editPhotoData.value = imageData;

                stopEditCamera();
                editRetakePhotoBtn.disabled = false;
            });

            editRetakePhotoBtn.addEventListener('click', function () {
                editPhotoData.value = '';
                editPhoto.src = editPhoto.dataset.original || editPhoto.src;
                editOpenCameraBtn.click();
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/clientList.blade.php ENDPATH**/ ?>