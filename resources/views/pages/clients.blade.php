@extends('layouts.master')
@section('title', 'Clients')
@section('content')
    @php
        $editingClient = $client ?? null;
        $selectedProvince = old('province', optional($editingClient)->province ?? '');
        $selectedCity = old('city', optional($editingClient)->city ?? '');
        $selectedBarangay = old('barangay', optional($editingClient)->barangay ?? '');
        $selectedBirthDate = old('birth_date', optional($editingClient?->birth_date)->format('Y-m-d') ?? '');
        $educationOptions = [
            'ELEMENTARY GRADUATE',
            'ELEMENTARY LEVEL (IN SCHOOL)',
            'ELEMENTARY UNDERGRADUATE',
            'HIGH SCHOOL GRADUATE',
            'HIGH SCHOOL LEVEL (IN SCHOOL)',
            'HIGH SCHOOL UNDERGRADUATE',
            'N/A',
            'POST-GRADUATE STUDIES',
            'SENIOR HS (IN SCHOOL)',
            'SENIOR HS GRADUATEs',
        ];
        $sectorOptions = [
            'COMMON CITIZEN',
            'EDUCATION',
            'FAMILY HEADS AND OTHER NEEDY ADULTS',
            'HEALTH',
            'INDUSTRY / BUSINESS',
            'LGU',
            'NGOS',
            'OTHERS',
            'PEACE AND ORDER',
            'PERSONS WITH DISABILITIES',
        ];
        if (!$editingClient && $selectedProvince === '') {
            $selectedProvince = 'CAVITE';
        }
        if (!$editingClient && $selectedCity === '') {
            $selectedCity = 'CITY OF IMUS';
        }
        $oldFingerprintData = old('fingerprint_data', '');
        $previewImage = $editingClient && $editingClient->photo_path
            ? asset('storage/' . $editingClient->photo_path)
            : asset('assets/images/profile.png');
        $fingerprintPlaceholder = asset('assets/images/fingerprint.png');
        $fingerprintPreview = $editingClient && $editingClient->fingerprint_path
            ? asset('storage/' . $editingClient->fingerprint_path)
            : ($oldFingerprintData ?: $fingerprintPlaceholder);
    @endphp

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

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <div class="fw-semibold mb-1">Please fix the highlighted issue(s) below.</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        @endif

                        <form
                            action="{{ $editingClient ? route('clients.update', $editingClient) : route('clients.store') }}"
                            method="POST" class="clients-uppercase-form">
                            @csrf
                            @if($editingClient)
                                @method('PUT')
                            @endif

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <label for="clientPhoto" class="form-label">Client Photo</label>
                                            <div class="row g-3 align-items-start">
                                                <div class="col-md-auto">
                                                    <img id="clientPhotoPreview" src="{{ $previewImage }}"
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
                                            <label for="fingerprintCapture" class="form-label">Fingerprint Scanner</label>
                                            <div class="row g-3 align-items-start">
                                                <div class="col-md-auto">
                                                    <img id="fingerprintPreview" src="{{ $fingerprintPreview }}"
                                                        alt="Fingerprint Preview"
                                                        class="rounded-3 img-thumbnail material-shadow object-fit-cover"
                                                        style="width: 250px; height: 250px;">
                                                </div>
                                                <div class="col-md d-flex flex-column justify-content-center">
                                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                                        <button type="button" class="btn btn-soft-primary"
                                                            id="openFingerprintBtn">Open Scanner</button>
                                                        <button type="button" class="btn btn-soft-success"
                                                            id="clearFingerprintBtn" disabled>Clear</button>
                                                    </div>
                                                    <p class="text-muted small mb-2">
                                                        Upload a captured fingerprint image from the scanner or biometric device.
                                                    </p>
                                                    @unless ($editingClient)
                                                        <div class="form-check form-switch mb-2">
                                                            <input class="form-check-input" type="checkbox" role="switch" id="skipFingerprintCheck">
                                                            <label class="form-check-label" for="skipFingerprintCheck">Skip fingerprint scan</label>
                                                        </div>
                                                    @endunless
                                                    <input type="hidden" id="clientFingerprintData" name="fingerprint_data" value="{{ old('fingerprint_data', '') }}">
                                                    <input type="hidden" id="clientFingerprintTemplate" name="fingerprint_template" value="{{ old('fingerprint_template', '') }}">
                                                    <input type="hidden" id="clientFingerprintRemove" name="fingerprint_remove" value="{{ old('fingerprint_remove', '') }}">
                                                    <div class="small text-muted" id="fingerprintStatus">No fingerprint captured yet.</div>
                                                    @error('fingerprint_template')
                                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded-4 p-3 bg-light-subtle">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-1">Personal Information</h5>
                                                <p class="text-muted mb-0 small">Basic identity and demographic details.</p>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-3">
                                                <label for="firstName" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="firstName" name="first_name"
                                                    placeholder="Enter first name"
                                                    value="{{ old('first_name', optional($editingClient)->first_name ?? '') }}">
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="middleName" class="form-label">Middle Name</label>
                                                <input type="text" class="form-control" id="middleName" name="middle_name"
                                                    placeholder="Enter middle name"
                                                    value="{{ old('middle_name', optional($editingClient)->middle_name ?? '') }}">
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="lastName" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="lastName" name="last_name"
                                                    placeholder="Enter last name"
                                                    value="{{ old('last_name', optional($editingClient)->last_name ?? '') }}">
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="suffix" class="form-label">Suffix</label>
                                                <input type="text" class="form-control" id="suffix" name="suffix"
                                                    placeholder="Jr., Sr., III"
                                                    value="{{ old('suffix', optional($editingClient)->suffix ?? '') }}">
                                            </div>

                                            <div class="col-lg-2">
                                                <label for="gender" class="form-label">Gender</label>
                                                <select class="form-select" id="gender" name="gender">
                                                    <option value="" {{ old('gender', optional($editingClient)->gender ?? '') === '' ? 'selected' : '' }}>Select gender</option>
                                                    <option value="Male" {{ old('gender', optional($editingClient)->gender ?? '') === 'Male' ? 'selected' : '' }}>Male</option>
                                                    <option value="Female" {{ old('gender', optional($editingClient)->gender ?? '') === 'Female' ? 'selected' : '' }}>Female</option>
                                                    <option value="Other" {{ old('gender', optional($editingClient)->gender ?? '') === 'Other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>

                                            <div class="col-lg-2">
                                                <label for="birthDate" class="form-label">Birth Date</label>
                                                <input type="date" class="form-control" id="birthDate" name="birth_date"
                                                    value="{{ $selectedBirthDate }}">
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="birthplace" class="form-label">Birthplace</label>
                                                <input type="text" class="form-control" id="birthplace" name="birthplace"
                                                    placeholder="Enter birthplace"
                                                    value="{{ old('birthplace', optional($editingClient)->birthplace ?? '') }}">
                                            </div>

                                            <div class="col-lg-2">
                                                <label for="age" class="form-label">Age</label>
                                                <input type="number" class="form-control" id="age" name="age" placeholder="Enter age"
                                                    min="0" value="{{ old('age', optional($editingClient)->age ?? '') }}">
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="civilStatus" class="form-label">Civil Status</label>
                                                <select class="form-select" id="civilStatus" name="civil_status">
                                                    <option value="" {{ old('civil_status', optional($editingClient)->civil_status ?? '') === '' ? 'selected' : '' }}>Select civil status</option>
                                                    <option value="Single" {{ old('civil_status', optional($editingClient)->civil_status ?? '') === 'Single' ? 'selected' : '' }}>Single</option>
                                                    <option value="Married" {{ old('civil_status', optional($editingClient)->civil_status ?? '') === 'Married' ? 'selected' : '' }}>Married</option>
                                                    <option value="Separated" {{ old('civil_status', optional($editingClient)->civil_status ?? '') === 'Separated' ? 'selected' : '' }}>Separated</option>
                                                    <option value="Widowed" {{ old('civil_status', optional($editingClient)->civil_status ?? '') === 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                                    <option value="Annulled" {{ old('civil_status', optional($editingClient)->civil_status ?? '') === 'Annulled' ? 'selected' : '' }}>Annulled</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded-4 p-3 bg-light-subtle">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-1">Address and Contact Information</h5>
                                                <p class="text-muted mb-0 small">Where the client lives and how to reach them.</p>
                                            </div>
                                        </div>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="sameAsHomeAddress">
                                            <label class="form-check-label" for="sameAsHomeAddress">Check if address is outside City of Imus</label>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-12">
                                                <label for="address" class="form-label">Address</label>
                                                <input class="form-control" id="address" name="address" rows="3"
                                                    placeholder="Enter address"
                                                    value="{{ old('address', optional($editingClient)->address ?? '') }}">
                                            </div>

                                            <div class="col-lg-4">
                                                <label for="province" class="form-label">Province</label>
                                                <select class="form-select" id="province" name="province">
                                                    <option value="">Select province</option>
                                                </select>
                                                <input type="text" class="form-control d-none mt-2" id="provinceManual"
                                                    name="province_manual" placeholder="Enter province manually"
                                                    value="{{ old('province', optional($editingClient)->province ?? '') }}">
                                            </div>

                                            <div class="col-lg-4">
                                                <label for="city" class="form-label">City</label>
                                                <select class="form-select" id="city" name="city">
                                                    <option value="">Select city</option>
                                                </select>
                                                <input type="text" class="form-control d-none mt-2" id="cityManual" name="city_manual"
                                                    placeholder="Enter city manually"
                                                    value="{{ old('city', optional($editingClient)->city ?? '') }}">
                                            </div>

                                            <div class="col-lg-4">
                                                <label for="barangay" class="form-label">Barangay</label>
                                                <select class="form-select" id="barangay" name="barangay">
                                                    <option value="">Select barangay</option>
                                                </select>
                                                <input type="text" class="form-control d-none mt-2" id="barangayManual"
                                                    name="barangay_manual" placeholder="Enter barangay manually"
                                                    value="{{ old('barangay', optional($editingClient)->barangay ?? '') }}">
                                            </div>

                                            <div class="col-lg-4">
                                                <label for="contact" class="form-label">Contact 1</label>
                                                <input type="text" class="form-control" id="contact" name="contact"
                                                    placeholder="Enter primary contact number" inputmode="numeric" maxlength="11"
                                                    autocomplete="off" value="{{ old('contact', optional($editingClient)->contact ?? '') }}">
                                                <div id="contactError" class="text-danger small mt-1 d-none">Only numbers can be input.
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <label for="contact2" class="form-label">Contact 2</label>
                                                <input type="text" class="form-control" id="contact2" name="contact_2"
                                                    placeholder="Enter secondary contact number" inputmode="numeric" maxlength="11"
                                                    autocomplete="off" value="{{ old('contact_2', optional($editingClient)->contact_2 ?? '') }}">
                                                <div id="contact2Error" class="text-danger small mt-1 d-none">Only numbers can be input.
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email"
                                                    placeholder="Enter email" value="{{ old('email', optional($editingClient)->email ?? '') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded-4 p-3 bg-light-subtle">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-1">Additional Information</h5>
                                                <p class="text-muted mb-0 small">Education and work-related details.</p>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-3">
                                                <label for="education" class="form-label">Education</label>
                                                <select class="form-select" id="education" name="education">
                                                    <option value="">Select education</option>
                                                    @foreach ($educationOptions as $educationOption)
                                                        <option value="{{ $educationOption }}" {{ old('education', optional($editingClient)->education ?? '') === $educationOption ? 'selected' : '' }}>
                                                            {{ $educationOption }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="course" class="form-label">Course</label>
                                                <input type="text" class="form-control" id="course" name="course"
                                                    placeholder="Enter course"
                                                    value="{{ old('course', optional($editingClient)->course ?? '') }}">
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="sector" class="form-label">Sector</label>
                                                <select class="form-select" id="sector" name="sector">
                                                    <option value="">Select sector</option>
                                                    @foreach ($sectorOptions as $sectorOption)
                                                        <option value="{{ $sectorOption }}" {{ old('sector', optional($editingClient)->sector ?? '') === $sectorOption ? 'selected' : '' }}>
                                                            {{ $sectorOption }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-lg-3">
                                                <label for="positionOrganization" class="form-label">Position / Organization</label>
                                                <input type="text" class="form-control" id="positionOrganization" name="position_organization"
                                                    placeholder="Enter position or organization"
                                                    value="{{ old('position_organization', optional($editingClient)->position_organization ?? '') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="d-flex justify-content-end gap-2 mt-2">
                                        <button type="submit"
                                            class="btn btn-primary">{{ $editingClient ? 'Update Client' : 'Save Client' }}</button>
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

    <div class="modal fade" id="fingerprintModal" tabindex="-1" aria-labelledby="fingerprintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fingerprintModalLabel">Capture Fingerprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
                                <div>
                                    <div class="fw-semibold mb-1">Fingerprint Scanner</div>
                                    <p class="text-muted small mb-0">
                                        Open this panel, place your finger on the scanner, and it will capture automatically.
                                    </p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-soft-secondary" id="clearFingerprintCaptureBtn">Clear</button>
                                </div>
                            </div>
                        <div class="mt-3 text-center">
                            <img id="fingerprintModalPreview" src="{{ $fingerprintPreview }}" alt="Fingerprint Scanner Preview"
                                class="rounded-3 border object-fit-cover bg-white" style="width: 100%; max-width: 420px; height: 280px;">
                        </div>
                        <div id="fingerprintModalError" class="text-danger small mt-3 d-none"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-primary d-none" id="retryFingerprintCaptureBtn">Scan Again</button>
                    <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveFingerprintBtn">Use Fingerprint</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
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
            const openFingerprintBtn = document.getElementById('openFingerprintBtn');
            const clearFingerprintBtn = document.getElementById('clearFingerprintBtn');
            const fingerprintPreview = document.getElementById('fingerprintPreview');
            const fingerprintStatus = document.getElementById('fingerprintStatus');
            const skipFingerprintCheck = document.getElementById('skipFingerprintCheck');
            const fingerprintModalEl = document.getElementById('fingerprintModal');
            const fingerprintModalPreview = document.getElementById('fingerprintModalPreview');
            const fingerprintModalError = document.getElementById('fingerprintModalError');
            const retryFingerprintCaptureBtn = document.getElementById('retryFingerprintCaptureBtn');
            const saveFingerprintBtn = document.getElementById('saveFingerprintBtn');
            const clearFingerprintCaptureBtn = document.getElementById('clearFingerprintCaptureBtn');
            const clientFingerprintData = document.getElementById('clientFingerprintData');
            const clientFingerprintTemplate = document.getElementById('clientFingerprintTemplate');
            const clientFingerprintRemove = document.getElementById('clientFingerprintRemove');
            const preview = document.getElementById('clientPhotoPreview');
            const form = document.querySelector('form');
            const contactInput = document.getElementById('contact');
            const contactError = document.getElementById('contactError');
            const contact2Input = document.getElementById('contact2');
            const contact2Error = document.getElementById('contact2Error');
            const cameraModalEl = document.getElementById('cameraModal');
            const provinceSelect = document.getElementById('province');
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            const provinceManual = document.getElementById('provinceManual');
            const cityManual = document.getElementById('cityManual');
            const barangayManual = document.getElementById('barangayManual');
            const sameAsHomeAddress = document.getElementById('sameAsHomeAddress');

            const selectedProvince = @json($selectedProvince);
            const selectedCity = @json($selectedCity);
            const selectedBarangay = @json($selectedBarangay);
            const fingerprintPlaceholder = @json($fingerprintPlaceholder);
            const fingerprintSearchUrl = @json(route('client.search.fingerprint'));
            const apiBase = 'https://psgc.gitlab.io/api';
            const calabarzonProvinces = ['Batangas', 'Cavite', 'Laguna', 'Quezon', 'Rizal'];

            if (!openCameraBtn || !capturePhotoBtn || !retakePhotoBtn || !cameraWrapper || !cameraView || !cameraCanvas || !clientPhotoData || !preview || !form || !contactInput || !contactError || !contact2Input || !contact2Error || !cameraModalEl || !provinceSelect || !citySelect || !barangaySelect || !provinceManual || !cityManual || !barangayManual || !sameAsHomeAddress || !openFingerprintBtn || !clearFingerprintBtn || !fingerprintPreview || !fingerprintStatus || !fingerprintModalEl || !fingerprintModalPreview || !fingerprintModalError || !retryFingerprintCaptureBtn || !saveFingerprintBtn || !clearFingerprintCaptureBtn || !clientFingerprintData || !clientFingerprintTemplate || !clientFingerprintRemove) {
                return;
            }

            let stream = null;
            const cameraModal = bootstrap.Modal.getOrCreateInstance(cameraModalEl);
            const fingerprintModal = bootstrap.Modal.getOrCreateInstance(fingerprintModalEl);
            const defaultPreview = preview.src;
            const originalFingerprintPreview = fingerprintPreview.src;
            let fingerprintDataUrl = clientFingerprintData.value || '';
            let fingerprintTemplateXml = clientFingerprintTemplate.value || '';
            const existingFingerprint = originalFingerprintPreview !== fingerprintPlaceholder;
            const fingerprintBridgeBase = 'http://127.0.0.1:38654';
            const hasSkipFingerprintToggle = !!skipFingerprintCheck;
            let fingerprintSkipped = false;

            if (existingFingerprint) {
                clearFingerprintBtn.disabled = false;
                fingerprintStatus.textContent = 'Existing fingerprint on file.';
            }

            const setFingerprintSkipState = (skipped) => {
                if (!hasSkipFingerprintToggle) {
                    return;
                }

                fingerprintSkipped = skipped;
                skipFingerprintCheck.checked = skipped;

                if (skipped) {
                    fingerprintDataUrl = '';
                    fingerprintTemplateXml = '';
                    clientFingerprintData.value = '';
                    clientFingerprintTemplate.value = '';
                    clientFingerprintRemove.value = '';
                    fingerprintPreview.src = fingerprintPlaceholder;
                    fingerprintModalPreview.src = fingerprintPlaceholder;
                    fingerprintStatus.textContent = 'Fingerprint scan skipped.';
                } else {
                    fingerprintPreview.src = fingerprintDataUrl || (existingFingerprint ? originalFingerprintPreview : fingerprintPlaceholder);
                    fingerprintModalPreview.src = fingerprintDataUrl || (existingFingerprint ? originalFingerprintPreview : fingerprintPlaceholder);
                    if (fingerprintDataUrl) {
                        fingerprintStatus.textContent = 'Fingerprint captured and ready to save.';
                    } else {
                        fingerprintStatus.textContent = existingFingerprint ? 'Existing fingerprint on file.' : 'No fingerprint captured yet.';
                    }
                }

                openFingerprintBtn.disabled = skipped;
                clearFingerprintBtn.disabled = skipped ? true : !fingerprintDataUrl && !existingFingerprint;
            };

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
                    if ((item.name || '').toLowerCase() === (selectedValue || '').toLowerCase()) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            };

            const selectOptionByLabel = (select, desiredValue) => {
                if (!desiredValue) {
                    select.value = '';
                    return;
                }

                const match = Array.from(select.options).find((option) =>
                    (option.value || '').toLowerCase() === desiredValue.toLowerCase() ||
                    (option.textContent || '').toLowerCase() === desiredValue.toLowerCase()
                );

                select.value = match ? match.value : desiredValue;
            };

            const enableManualLocations = (message) => {
                provinceSelect.disabled = true;
                citySelect.disabled = true;
                barangaySelect.disabled = false;
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
                provinceSelect.disabled = true;
                citySelect.disabled = true;
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

            const setLocationMode = (outsideImus) => {
                sameAsHomeAddress.checked = outsideImus;

                provinceSelect.disabled = !outsideImus;
                citySelect.disabled = !outsideImus;
                barangaySelect.disabled = false;

                provinceManual.classList.add('d-none');
                cityManual.classList.add('d-none');
                barangayManual.classList.add('d-none');

                provinceManual.disabled = true;
                cityManual.disabled = true;
                barangayManual.disabled = false;

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
                    setLocationMode(sameAsHomeAddress.checked);
                    return;
                }

                selectOptionByLabel(provinceSelect, selectedProvince);
                const provinceCode = provinceSelect.selectedOptions[0]?.dataset.code || '';
                await loadCities(provinceCode, selectedCity);

                if (!selectedCity) {
                    fillSelect(barangaySelect, 'Select barangay', [], '');
                    setLocationMode(sameAsHomeAddress.checked);
                    return;
                }

                selectOptionByLabel(citySelect, selectedCity);
                const cityCode = citySelect.selectedOptions[0]?.dataset.code || '';
                await loadBarangays(cityCode, selectedBarangay);

                setLocationMode(sameAsHomeAddress.checked);
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

            const setFingerprintPreview = (imageData, statusText, templateXml = '') => {
                fingerprintDataUrl = imageData || '';
                fingerprintTemplateXml = templateXml || fingerprintTemplateXml;
                clientFingerprintData.value = fingerprintDataUrl;
                clientFingerprintTemplate.value = fingerprintTemplateXml;
                clientFingerprintRemove.value = '';
                fingerprintPreview.src = fingerprintDataUrl || fingerprintPlaceholder;
                fingerprintModalPreview.src = fingerprintDataUrl || fingerprintPlaceholder;
                fingerprintStatus.textContent = statusText || (fingerprintDataUrl ? 'Fingerprint captured and ready to save.' : 'No fingerprint captured yet.');
                clearFingerprintBtn.disabled = !fingerprintDataUrl;
                if (hasSkipFingerprintToggle) {
                    skipFingerprintCheck.checked = false;
                    fingerprintSkipped = false;
                    openFingerprintBtn.disabled = false;
                }
            };

            const clearFingerprintModalError = () => {
                fingerprintModalError.textContent = '';
                fingerprintModalError.classList.add('d-none');
            };

            const showFingerprintModalError = (message) => {
                fingerprintModalError.textContent = message;
                fingerprintModalError.classList.remove('d-none');
            };

            const scanFingerprintAgain = async () => {
                clearFingerprintModalError();
                retryFingerprintCaptureBtn.classList.add('d-none');
                fingerprintStatus.textContent = 'Place your finger on the scanner...';

                const bridgeOnline = await isFingerprintBridgeOnline();
                if (!bridgeOnline) {
                    throw new Error('DigitalPersona bridge is not running. Start the FingerprintBridge app first.');
                }

                const captureResult = await captureFingerprintFromBridge();
                fingerprintModalPreview.src = captureResult.imageDataUrl;
                setFingerprintPreview(captureResult.imageDataUrl, 'Fingerprint captured from device. Click Use Fingerprint to save it.', captureResult.fingerprintTemplateXml || '');
            };

            const checkDuplicateFingerprint = async () => {
                if (!fingerprintTemplateXml) {
                    throw new Error('Please wait for the fingerprint to finish capturing.');
                }

                const response = await fetch(fingerprintSearchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        fingerprint_template: fingerprintTemplateXml
                    })
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Unable to verify fingerprint.');
                }

                if (payload.matched && payload.client?.name) {
                    throw new Error(`This fingerprint is already taken by ${payload.client.name}.`);
                }
            };

            const clearFingerprintCapture = (markRemove = false) => {
                fingerprintDataUrl = '';
                fingerprintTemplateXml = '';
                clientFingerprintData.value = '';
                clientFingerprintTemplate.value = '';
                clientFingerprintRemove.value = markRemove && existingFingerprint ? '1' : '';
                fingerprintPreview.src = markRemove ? fingerprintPlaceholder : originalFingerprintPreview;
                fingerprintModalPreview.src = markRemove ? fingerprintPlaceholder : originalFingerprintPreview;
                fingerprintStatus.textContent = markRemove
                    ? 'No fingerprint captured yet.'
                    : (existingFingerprint ? 'Existing fingerprint on file.' : 'No fingerprint captured yet.');
                clearFingerprintBtn.disabled = markRemove ? true : !existingFingerprint;
                retryFingerprintCaptureBtn.classList.add('d-none');
            };

            const captureFingerprintFromBridge = async () => {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 45000);

                try {
                    const response = await fetch(`${fingerprintBridgeBase}/api/capture`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ source: 'laravel' }),
                        signal: controller.signal
                    });

                    const payload = await response.json();
                    if (!response.ok || !payload.success || !payload.imageDataUrl) {
                        throw new Error(payload.message || 'Scanner capture failed.');
                    }

                    return payload;
                } finally {
                    clearTimeout(timeoutId);
                }
            };

            const isFingerprintBridgeOnline = async () => {
                try {
                    const response = await fetch(`${fingerprintBridgeBase}/api/health`, {
                        method: 'GET',
                        cache: 'no-store'
                    });

                    return response.ok;
                } catch (error) {
                    return false;
                }
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

            openFingerprintBtn.addEventListener('click', function () {
                if (fingerprintSkipped) {
                    return;
                }
                fingerprintModalPreview.src = fingerprintPreview.src;
                retryFingerprintCaptureBtn.classList.add('d-none');
                fingerprintModal.show();
            });

            fingerprintModalEl.addEventListener('shown.bs.modal', function () {
                fingerprintModalPreview.src = fingerprintPreview.src;

                (async () => {
                    try {
                        await scanFingerprintAgain();
                    } catch (error) {
                        fingerprintStatus.textContent = 'Scanner bridge is not available. Make sure the bridge app is running.';
                        retryFingerprintCaptureBtn.classList.remove('d-none');
                        alert(`Unable to capture from the scanner bridge.\n\n${error.message || error}`);
                    }
                })();
            });

            clearFingerprintBtn.addEventListener('click', function () {
                clearFingerprintCapture(true);
            });

            saveFingerprintBtn.addEventListener('click', function () {
                (async () => {
                    clearFingerprintModalError();

                    const imageData = fingerprintModalPreview.src;
                    if (!imageData || !imageData.startsWith('data:image/')) {
                        if (existingFingerprint) {
                            fingerprintModal.hide();
                            return;
                        }

                        showFingerprintModalError('Please wait for the scanner to capture a fingerprint first.');
                        return;
                    }

                    try {
                        saveFingerprintBtn.disabled = true;
                        await checkDuplicateFingerprint();
                        setFingerprintPreview(imageData, 'Fingerprint captured and ready to save.');
                        retryFingerprintCaptureBtn.classList.add('d-none');
                        fingerprintModal.hide();
                    } catch (error) {
                        const message = error?.message || 'This fingerprint is already registered.';
                        showFingerprintModalError(message);
                        fingerprintStatus.textContent = message;
                        retryFingerprintCaptureBtn.classList.remove('d-none');
                    } finally {
                        saveFingerprintBtn.disabled = false;
                    }
                })();
            });

            retryFingerprintCaptureBtn.addEventListener('click', function () {
                (async () => {
                    try {
                        await scanFingerprintAgain();
                    } catch (error) {
                        fingerprintStatus.textContent = 'Scanner bridge is not available. Make sure the bridge app is running.';
                        retryFingerprintCaptureBtn.classList.remove('d-none');
                        alert(`Unable to capture from the scanner bridge.\n\n${error.message || error}`);
                    }
                })();
            });

            clearFingerprintCaptureBtn.addEventListener('click', function () {
                clearFingerprintCapture(false);
            });

            if (hasSkipFingerprintToggle) {
                skipFingerprintCheck.addEventListener('change', function () {
                    setFingerprintSkipState(this.checked);
                });
            }

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

            form.addEventListener('reset', function () {
                setTimeout(() => {
                    clientPhotoData.value = '';
                    preview.src = defaultPreview;
                    retakePhotoBtn.disabled = true;
                    clientFingerprintData.value = '';
                    clientFingerprintTemplate.value = '';
                    clientFingerprintRemove.value = '';
                    fingerprintDataUrl = '';
                    fingerprintTemplateXml = '';
                    fingerprintPreview.src = originalFingerprintPreview;
                    fingerprintModalPreview.src = originalFingerprintPreview;
                    fingerprintStatus.textContent = existingFingerprint ? 'Existing fingerprint on file.' : 'No fingerprint captured yet.';
                    clearFingerprintBtn.disabled = !existingFingerprint;
                    retryFingerprintCaptureBtn.classList.add('d-none');
                    setLocationMode(false);
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

            contact2Input.addEventListener('input', function () {
                const onlyDigits = this.value.replace(/\D/g, '').slice(0, 11);
                const hasInvalidChars = this.value !== onlyDigits;
                this.value = onlyDigits;
                contact2Error.classList.toggle('d-none', !hasInvalidChars);
            });

            sameAsHomeAddress.addEventListener('change', function () {
                setLocationMode(this.checked);
                if (!this.checked) {
                    restoreLocations().catch(() => enableManualLocations('Unable to load location data from the API. You can enter the address manually.'));
                }
            });

            setLocationMode(sameAsHomeAddress.checked);
            restoreLocations().catch(() => enableManualLocations('Unable to load location data from the API. You can enter the address manually.'));
            window.addEventListener('beforeunload', stopCamera);

            if (hasSkipFingerprintToggle) {
                setFingerprintSkipState(skipFingerprintCheck.checked);
            }
        });
    </script>
@endsection
