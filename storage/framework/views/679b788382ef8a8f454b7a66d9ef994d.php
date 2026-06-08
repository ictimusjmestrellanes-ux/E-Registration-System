<?php $__env->startSection('title', 'Clients'); ?>
<?php $__env->startSection('content'); ?>
    <?php
        $editingClient = $client ?? null;
        $selectedProvince = old('province', $editingClient?->province ?? '');
        $selectedCity = old('city', $editingClient?->city ?? '');
        $selectedBarangay = old('barangay', $editingClient?->barangay ?? '');
        $previewImage = $editingClient && $editingClient->photo_path
            ? asset('storage/' . $editingClient->photo_path)
            : 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 240 240"><rect width="240" height="240" rx="24" fill="#f1f5f9"/><path d="M85 80l10-16h50l10 16h15c8.3 0 15 6.7 15 15v70c0 8.3-6.7 15-15 15H80c-8.3 0-15-6.7-15-15V95c0-8.3 6.7-15 15-15h5zm35 30c-16.6 0-30 13.4-30 30s13.4 30 30 30 30-13.4 30-30-13.4-30-30-30zm0 15c8.3 0 15 6.7 15 15s-6.7 15-15 15-15-6.7-15-15 6.7-15 15-15z" fill="#6b7280"/><circle cx="170" cy="98" r="8" fill="#6b7280"/></svg>');
        $fingerprintPreview = $editingClient && $editingClient->fingerprint_path
            ? asset('storage/' . $editingClient->fingerprint_path)
            : 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 240 240"><rect width="240" height="240" rx="24" fill="#f8fafc"/><g fill="none" stroke="#475569" stroke-linecap="round" stroke-linejoin="round"><path d="M120 52c-29.8 0-54 24.2-54 54 0 39 18 54 18 54"/><path d="M120 70c-20 0-36 16-36 36 0 24 10 36 10 36"/><path d="M120 88c-10 0-18 8-18 18 0 10 3 18 3 18"/><path d="M120 52c29.8 0 54 24.2 54 54 0 39-18 54-18 54"/><path d="M120 70c20 0 36 16 36 36 0 24-10 36-10 36"/><path d="M120 88c10 0 18 8 18 18 0 10-3 18-3 18"/><path d="M83 168c6 10 20 18 37 18s31-8 37-18"/></g></svg>');
    ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Clients</h4>
                                <p class="text-muted mb-0">Add and manage client information here.</p>
                            </div>
                        </div>

                        <form
                            action="<?php echo e($editingClient ? route('clients.update', $editingClient) : route('clients.store')); ?>"
                            method="POST" class="clients-uppercase-form">
                            <?php echo csrf_field(); ?>
                            <?php if($editingClient): ?>
                                <?php echo method_field('PUT'); ?>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <label for="clientPhoto" class="form-label">Client Photo</label>
                                            <div class="row g-3 align-items-start">
                                                <div class="col-md-auto">
                                                    <img id="clientPhotoPreview" src="<?php echo e($previewImage); ?>"
                                                        alt="Client Photo Preview"
                                                        class="rounded-3 img-thumbnail material-shadow object-fit-cover"
                                                        style="width: 250px; height: 250px;">
                                                </div>
                                                <div class="col-md d-flex flex-column justify-content-center">
                                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                                        <button type="button" class="btn btn-soft-primary"
                                                            id="openCameraBtn">Open Camera</button>
                                                        <button type="button" class="btn btn-soft-success"
                                                            id="retakePhotoBtn" disabled>Retake</button>
                                                    </div>
                                                    <canvas id="cameraCanvas" class="d-none"></canvas>
                                                    <input type="hidden" id="clientPhotoData" name="photo_data">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-6">
                                            <label for="fingerprintInput" class="form-label">Fingerprint</label>
                                            <div class="row g-3 align-items-start">
                                                <div class="col-md-auto">
                                                    <img id="fingerprintPreview" src="<?php echo e($fingerprintPreview); ?>"
                                                        alt="Fingerprint Preview"
                                                        class="rounded-3 img-thumbnail material-shadow object-fit-cover"
                                                        style="width: 250px; height: 250px;">
                                                </div>
                                                <div class="col-md d-flex flex-column justify-content-center">
                                                    <div class="mb-2">
                                                        <input type="file" class="form-control" id="fingerprintInput"
                                                            accept="image/*">
                                                        <div class="form-text">Upload a fingerprint image from your scanner
                                                            or device.</div>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <button type="button" class="btn btn-soft-secondary"
                                                            id="clearFingerprintBtn">Clear Fingerprint</button>
                                                    </div>
                                                    <input type="hidden" id="fingerprintData" name="fingerprint_data">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <label for="firstName" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="firstName" name="first_name"
                                        placeholder="Enter first name"
                                        value="<?php echo e(old('first_name', $editingClient->first_name ?? '')); ?>">
                                </div>

                                <div class="col-lg-4">
                                    <label for="middleName" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middleName" name="middle_name"
                                        placeholder="Enter middle name"
                                        value="<?php echo e(old('middle_name', $editingClient->middle_name ?? '')); ?>">
                                </div>

                                <div class="col-lg-4">
                                    <label for="lastName" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" name="last_name"
                                        placeholder="Enter last name"
                                        value="<?php echo e(old('last_name', $editingClient->last_name ?? '')); ?>">
                                </div>

                                <div class="col-lg-1">
                                    <label for="age" class="form-label">Age</label>
                                    <input type="number" class="form-control" id="age" name="age" placeholder="Enter age"
                                        min="0" value="<?php echo e(old('age', $editingClient->age ?? '')); ?>">
                                </div>

                                <div class="col-lg-2">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="" <?php echo e(old('gender', $editingClient->gender ?? '') === '' ? 'selected' : ''); ?>>Select gender</option>
                                        <option value="Male" <?php echo e(old('gender', $editingClient->gender ?? '') === 'Male' ? 'selected' : ''); ?>>Male</option>
                                        <option value="Female" <?php echo e(old('gender', $editingClient->gender ?? '') === 'Female' ? 'selected' : ''); ?>>Female</option>
                                        <option value="Other" <?php echo e(old('gender', $editingClient->gender ?? '') === 'Other' ? 'selected' : ''); ?>>Other</option>
                                    </select>
                                </div>

                                <div class="col-lg-2">
                                    <label for="civilStatus" class="form-label">Civil Status</label>
                                    <select class="form-select" id="civilStatus" name="civil_status">
                                        <option value="" <?php echo e(old('civil_status', $editingClient->civil_status ?? '') === '' ? 'selected' : ''); ?>>Select civil status</option>
                                        <option value="Single" <?php echo e(old('civil_status', $editingClient->civil_status ?? '') === 'Single' ? 'selected' : ''); ?>>Single</option>
                                        <option value="Married" <?php echo e(old('civil_status', $editingClient->civil_status ?? '') === 'Married' ? 'selected' : ''); ?>>Married</option>
                                        <option value="Separated" <?php echo e(old('civil_status', $editingClient->civil_status ?? '') === 'Separated' ? 'selected' : ''); ?>>Separated</option>
                                        <option value="Widowed" <?php echo e(old('civil_status', $editingClient->civil_status ?? '') === 'Widowed' ? 'selected' : ''); ?>>Widowed</option>
                                        <option value="Annulled" <?php echo e(old('civil_status', $editingClient->civil_status ?? '') === 'Annulled' ? 'selected' : ''); ?>>Annulled</option>
                                    </select>
                                </div>

                                <div class="col-lg-3">
                                    <label for="contact" class="form-label">Contact</label>
                                    <input type="text" class="form-control" id="contact" name="contact"
                                        placeholder="Enter contact number" inputmode="numeric" maxlength="11"
                                        autocomplete="off" value="<?php echo e(old('contact', $editingClient->contact ?? '')); ?>">
                                    <div id="contactError" class="text-danger small mt-1 d-none">Only numbers can be input.
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        placeholder="Enter email" value="<?php echo e(old('email', $editingClient->email ?? '')); ?>">
                                </div>

                                <div class="col-lg-12">
                                    <label for="address" class="form-label">Address</label>
                                    <input class="form-control" id="address" name="address" rows="3"
                                        placeholder="Enter address"
                                        value="<?php echo e(old('address', $editingClient->address ?? '')); ?>">
                                </div>

                                <div class="col-lg-4">
                                    <label for="province" class="form-label">Province</label>
                                    <select class="form-select" id="province" name="province">
                                        <option value="">Select province</option>
                                    </select>
                                    <input type="text" class="form-control d-none mt-2" id="provinceManual"
                                        name="province_manual" placeholder="Enter province manually"
                                        value="<?php echo e(old('province', $editingClient->province ?? '')); ?>">
                                </div>

                                <div class="col-lg-4">
                                    <label for="city" class="form-label">City</label>
                                    <select class="form-select" id="city" name="city">
                                        <option value="">Select city</option>
                                    </select>
                                    <input type="text" class="form-control d-none mt-2" id="cityManual" name="city_manual"
                                        placeholder="Enter city manually"
                                        value="<?php echo e(old('city', $editingClient->city ?? '')); ?>">
                                </div>

                                <div class="col-lg-4">
                                    <label for="barangay" class="form-label">Barangay</label>
                                    <select class="form-select" id="barangay" name="barangay">
                                        <option value="">Select barangay</option>
                                    </select>
                                    <input type="text" class="form-control d-none mt-2" id="barangayManual"
                                        name="barangay_manual" placeholder="Enter barangay manually"
                                        value="<?php echo e(old('barangay', $editingClient->barangay ?? '')); ?>">
                                </div>

                                <div class="col-12">
                                    <div class="d-flex justify-content-end gap-2 mt-2">
                                        <button type="reset" class="btn btn-soft-success">Reset</button>
                                        <button type="submit"
                                            class="btn btn-primary"><?php echo e($editingClient ? 'Update Client' : 'Save Client'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cameraModalLabel">Capture Client Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cameraWrapper" class="border rounded p-2 bg-light">
                        <video id="cameraView" class="rounded w-100" autoplay playsinline
                            style="max-height: 1000px; object-fit: cover; transform: scaleX(-1);"></video>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Position the camera, then click Capture Photo.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="capturePhotoBtn" disabled>Capture Photo</button>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <style>
        .clients-uppercase-form input,
        .clients-uppercase-form textarea,
        .clients-uppercase-form select,
        .clients-uppercase-form option {
            text-transform: uppercase;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const openCameraBtn = document.getElementById('openCameraBtn');
            const capturePhotoBtn = document.getElementById('capturePhotoBtn');
            const retakePhotoBtn = document.getElementById('retakePhotoBtn');
            const cameraWrapper = document.getElementById('cameraWrapper');
            const cameraView = document.getElementById('cameraView');
            const cameraCanvas = document.getElementById('cameraCanvas');
            const clientPhotoData = document.getElementById('clientPhotoData');
            const preview = document.getElementById('clientPhotoPreview');
            const fingerprintPreview = document.getElementById('fingerprintPreview');
            const fingerprintInput = document.getElementById('fingerprintInput');
            const fingerprintData = document.getElementById('fingerprintData');
            const clearFingerprintBtn = document.getElementById('clearFingerprintBtn');
            const form = document.querySelector('form');
            const contactInput = document.getElementById('contact');
            const contactError = document.getElementById('contactError');
            const cameraModalEl = document.getElementById('cameraModal');
            const provinceSelect = document.getElementById('province');
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            const provinceManual = document.getElementById('provinceManual');
            const cityManual = document.getElementById('cityManual');
            const barangayManual = document.getElementById('barangayManual');

            const selectedProvince = <?php echo json_encode($selectedProvince, 15, 512) ?>;
            const selectedCity = <?php echo json_encode($selectedCity, 15, 512) ?>;
            const selectedBarangay = <?php echo json_encode($selectedBarangay, 15, 512) ?>;
            const apiBase = 'https://psgc.gitlab.io/api';
            const calabarzonProvinces = ['Batangas', 'Cavite', 'Laguna', 'Quezon', 'Rizal'];

            if (!openCameraBtn || !capturePhotoBtn || !retakePhotoBtn || !cameraWrapper || !cameraView || !cameraCanvas || !clientPhotoData || !preview || !fingerprintPreview || !fingerprintInput || !fingerprintData || !clearFingerprintBtn || !form || !contactInput || !contactError || !cameraModalEl || !provinceSelect || !citySelect || !barangaySelect || !provinceManual || !cityManual || !barangayManual) {
                return;
            }

            let stream = null;
            const cameraModal = bootstrap.Modal.getOrCreateInstance(cameraModalEl);
            const defaultPreview = preview.src;
            const defaultFingerprintPreview = fingerprintPreview.src;

            const fillSelect = (select, placeholder, items, selectedValue = '') => {
                select.innerHTML = '';

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                placeholderOption.selected = !selectedValue;
                select.appendChild(placeholderOption);

                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.name;
                    option.textContent = item.name;
                    option.dataset.code = item.code || '';
                    if (item.name === selectedValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            };

            const enableManualLocations = (message) => {
                provinceSelect.disabled = true;
                citySelect.disabled = true;
                barangaySelect.disabled = true;
                provinceSelect.classList.add('d-none');
                citySelect.classList.add('d-none');
                barangaySelect.classList.add('d-none');

                provinceManual.classList.remove('d-none');
                cityManual.classList.remove('d-none');
                barangayManual.classList.remove('d-none');
                provinceManual.disabled = false;
                cityManual.disabled = false;
                barangayManual.disabled = false;

                provinceManual.name = 'province';
                cityManual.name = 'city';
                barangayManual.name = 'barangay';

                provinceManual.value = selectedProvince;
                cityManual.value = selectedCity;
                barangayManual.value = selectedBarangay;

                if (message) {
                    alert(message);
                }
            };

            const disableManualLocations = () => {
                provinceSelect.disabled = false;
                citySelect.disabled = false;
                barangaySelect.disabled = false;
                provinceSelect.classList.remove('d-none');
                citySelect.classList.remove('d-none');
                barangaySelect.classList.remove('d-none');

                provinceManual.classList.add('d-none');
                cityManual.classList.add('d-none');
                barangayManual.classList.add('d-none');
                provinceManual.disabled = true;
                cityManual.disabled = true;
                barangayManual.disabled = true;

                provinceManual.name = 'province_manual';
                cityManual.name = 'city_manual';
                barangayManual.name = 'barangay_manual';
            };

            const loadProvinces = async () => {
                const response = await fetch(`${apiBase}/provinces.json`);
                if (!response.ok) {
                    throw new Error('Failed to load provinces.');
                }

                const provinces = await response.json();
                const filteredProvinces = provinces.filter((province) => calabarzonProvinces.includes(province.name));
                fillSelect(provinceSelect, 'Select province', filteredProvinces, selectedProvince);
            };

            const loadCities = async (provinceCode, preferredCity = '') => {
                if (!provinceCode) {
                    fillSelect(citySelect, 'Select city', [], '');
                    fillSelect(barangaySelect, 'Select barangay', [], '');
                    return;
                }

                const response = await fetch(`${apiBase}/provinces/${encodeURIComponent(provinceCode)}/cities.json`);
                if (!response.ok) {
                    throw new Error('Failed to load cities.');
                }

                const cities = await response.json();
                fillSelect(citySelect, 'Select city', cities, preferredCity);
            };

            const loadBarangays = async (cityCode, preferredBarangay = '') => {
                if (!cityCode) {
                    fillSelect(barangaySelect, 'Select barangay', [], '');
                    return;
                }

                const response = await fetch(`${apiBase}/cities/${encodeURIComponent(cityCode)}/barangays.json`);
                if (!response.ok) {
                    throw new Error('Failed to load barangays.');
                }

                const barangays = await response.json();
                fillSelect(barangaySelect, 'Select barangay', barangays, preferredBarangay);
            };

            const restoreLocations = async () => {
                disableManualLocations();
                await loadProvinces();

                if (!selectedProvince) {
                    fillSelect(citySelect, 'Select city', [], '');
                    fillSelect(barangaySelect, 'Select barangay', [], '');
                    return;
                }

                provinceSelect.value = selectedProvince;
                const provinceCode = provinceSelect.selectedOptions[0]?.dataset.code || '';
                await loadCities(provinceCode, selectedCity);

                if (!selectedCity) {
                    fillSelect(barangaySelect, 'Select barangay', [], '');
                    return;
                }

                citySelect.value = selectedCity;
                const cityCode = citySelect.selectedOptions[0]?.dataset.code || '';
                await loadBarangays(cityCode, selectedBarangay);
            };

            const stopCamera = () => {
                if (stream) {
                    stream.getTracks().forEach((track) => track.stop());
                    stream = null;
                }
                cameraView.srcObject = null;
                capturePhotoBtn.disabled = true;
                retakePhotoBtn.disabled = false;
            };

            const startCamera = async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Camera capture is not supported in this browser.');
                    return;
                }

                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false
                    });

                    cameraView.srcObject = stream;
                    capturePhotoBtn.disabled = false;
                    retakePhotoBtn.disabled = true;
                } catch (error) {
                    alert('Unable to access the camera. Please allow camera permissions and try again.');
                }
            };

            openCameraBtn.addEventListener('click', function () {
                cameraModal.show();
            });

            provinceSelect.addEventListener('change', function () {
                const provinceCode = this.selectedOptions[0]?.dataset.code || '';
                loadCities(provinceCode, '').catch(() => enableManualLocations('Unable to load cities for the selected province. You can enter it manually.'));
                fillSelect(barangaySelect, 'Select barangay', [], '');
            });

            citySelect.addEventListener('change', function () {
                const cityCode = this.selectedOptions[0]?.dataset.code || '';
                loadBarangays(cityCode, '').catch(() => enableManualLocations('Unable to load barangays for the selected city. You can enter it manually.'));
            });

            cameraModalEl.addEventListener('shown.bs.modal', function () {
                startCamera();
            });

            cameraModalEl.addEventListener('hidden.bs.modal', function () {
                stopCamera();
            });

            capturePhotoBtn.addEventListener('click', function () {
                const context = cameraCanvas.getContext('2d');
                cameraCanvas.width = cameraView.videoWidth || 200;
                cameraCanvas.height = cameraView.videoHeight || 200;
                context.save();
                context.translate(cameraCanvas.width, 0);
                context.scale(-1, 1);
                context.drawImage(cameraView, 0, 0, cameraCanvas.width, cameraCanvas.height);
                context.restore();

                const imageData = cameraCanvas.toDataURL('image/png');
                preview.src = imageData;
                clientPhotoData.value = imageData;

                stopCamera();
                retakePhotoBtn.disabled = false;
                cameraModal.hide();
            });

            retakePhotoBtn.addEventListener('click', function () {
                clientPhotoData.value = '';
                preview.src = defaultPreview;
                retakePhotoBtn.disabled = true;
                cameraModal.show();
            });

            fingerprintInput.addEventListener('change', function () {
                const file = this.files && this.files[0];

                if (!file) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    const result = event.target?.result;

                    if (typeof result === 'string') {
                        fingerprintPreview.src = result;
                        fingerprintData.value = result;
                    }
                };
                reader.readAsDataURL(file);
            });

            clearFingerprintBtn.addEventListener('click', function () {
                fingerprintInput.value = '';
                fingerprintData.value = '';
                fingerprintPreview.src = defaultFingerprintPreview;
            });

            form.addEventListener('reset', function () {
                setTimeout(() => {
                    clientPhotoData.value = '';
                    preview.src = defaultPreview;
                    retakePhotoBtn.disabled = true;
                    fingerprintInput.value = '';
                    fingerprintData.value = '';
                    fingerprintPreview.src = defaultFingerprintPreview;
                    restoreLocations().catch(() => alert('Unable to restore location data.'));
                    stopCamera();
                }, 0);
            });

            contactInput.addEventListener('input', function () {
                const onlyDigits = this.value.replace(/\D/g, '').slice(0, 11);
                const hasInvalidChars = this.value !== onlyDigits;
                this.value = onlyDigits;
                contactError.classList.toggle('d-none', !hasInvalidChars);
            });

            restoreLocations().catch(() => enableManualLocations('Unable to load location data from the API. You can enter the address manually.'));
            window.addEventListener('beforeunload', stopCamera);
        });
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/clients.blade.php ENDPATH**/ ?>