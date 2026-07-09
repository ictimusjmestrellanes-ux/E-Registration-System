<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content" style="max-height: calc(100vh - 2rem); overflow: visible;">
            <div class="modal-header">
                <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editClientForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="editClientId" name="edit_client_id">
                <div class="modal-body" style="overflow-y: auto; max-height: calc(100vh - 11rem);">
                    <div class="rounded-4 client-details-panel p-3 mb-4">
                        <div class="row g-4 align-items-start">
                            <div class="col-12 col-xl-6">
                                <label class="form-label client-details-label mb-2">Client Photo <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
                                    <div class="flex-shrink-0">
                                        <img id="editClientPhoto" src="{{ $defaultClientPhoto }}"
                                            data-default-src="{{ $defaultClientPhoto }}" alt="Client Photo"
                                            onerror="this.onerror=null;this.src='{{ $defaultClientPhoto }}';"
                                            class="rounded-4 border border-secondary-subtle bg-light object-fit-cover"
                                            style="width: 240px; height: 240px;">
                                    </div>
                                        <div class="d-flex flex-column gap-2 justify-content-start">
                                            <div class="fw-semibold fs-5 client-details-title" id="editClientName"></div>
                                            <div class="client-details-muted small">Edit client details below.</div>
                                            <div class="client-details-muted small">A captured client photo is required before saving.</div>
                                            <div class="d-flex flex-column gap-2 mt-2">
                                                <button type="button" class="btn btn-outline-primary"
                                                    id="editOpenCameraBtn">Open Camera</button>
                                                <button type="button" class="btn btn-outline-secondary"
                                                    id="editRetakePhotoBtn" disabled>Retake</button>
                                            <button type="button" class="btn btn-primary" id="editCapturePhotoBtn"
                                                disabled>Capture Photo</button>
                                        </div>
                                    </div>
                                </div>
                                <div id="editCameraWrapper"
                                    class="border border-secondary-subtle rounded-4 p-2 bg-body-tertiary d-none mt-3">
                                    <video id="editCameraView" class="rounded-3 w-100" autoplay playsinline
                                        style="max-height: 320px; object-fit: cover; transform: scaleX(-1);"></video>
                                </div>
                                <canvas id="editCameraCanvas" class="d-none"></canvas>
                                <input type="hidden" id="editPhotoData" name="photo_data">
                                @error('photo_data')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-xl-6">
                                <label class="form-label client-details-label mb-2">Fingerprint Scanner <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
                                    <div class="flex-shrink-0">
                                        <img id="editFingerprintPreview"
                                            src="{{ asset('assets/images/fingerprint.png') }}"
                                            data-default-src="{{ asset('assets/images/fingerprint.png') }}" alt="Fingerprint Preview"
                                            class="rounded-4 border border-secondary-subtle bg-light object-fit-cover"
                                            style="width: 240px; height: 240px;">
                                    </div>
                                    <div class="d-flex flex-column gap-2 justify-content-start">
                                        <div class="d-flex flex-column flex-sm-row gap-1">
                                            <button type="button" class="btn btn-outline-primary"
                                                id="editOpenFingerprintBtn">Capture New Fingerprint</button>
                                            <button type="button" class="btn btn-outline-secondary"
                                                id="editClearFingerprintBtn" disabled>Discard Capture</button>
                                        </div>
                                        <div class="client-details-muted small">Capture a replacement fingerprint
                                            image from the scanner or biometric device.</div>
                                        <div class="client-details-muted small">Fingerprint capture is required before saving.</div>
                                        <div class="client-details-muted small" id="editFingerprintStatus">No
                                            fingerprint captured yet.</div>
                                        <div class="form-check mb-2 mt-2">
                                            <input class="form-check-input" type="checkbox"
                                                id="editSkipFingerprintCheckbox" name="skip_fingerprint"
                                                value="1">
                                            <label class="form-check-label" for="editSkipFingerprintCheckbox">
                                                Skip fingerprint capture
                                            </label>
                                        </div>
                                        <button type="button"
                                            class="btn btn-soft-primary btn-sm d-none align-self-start"
                                            id="editScanAgainBtn">Capture Again</button>
                                    </div>
                                </div>
                                <input type="hidden" id="editFingerprintData" name="fingerprint_data">
                                <input type="hidden" id="editFingerprintTemplate" name="fingerprint_template">
                                @error('fingerprint_data')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                                @error('fingerprint_template')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
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
                                    <label class="form-label" for="editFirstName">First Name</label>
                                    <input type="text" class="form-control" id="editFirstName"
                                        placeholder="Enter first name" name="first_name" required>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editMiddleName">Middle Name</label>
                                    <input type="text" class="form-control" id="editMiddleName"
                                        placeholder="Enter Middle Name" name="middle_name">
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editLastName">Last Name</label>
                                    <input type="text" class="form-control" id="editLastName"
                                        placeholder="Enter last name" name="last_name" required>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editSuffix">Suffix</label>
                                    <input type="text" class="form-control" id="editSuffix" name="suffix"
                                        placeholder="Jr., Sr., III">
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label" for="editGender">Gender</label>
                                    <select class="form-select" id="editGender" name="gender">
                                        <option value="">Select gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label" for="editBirthDate">Birth Date</label>
                                    <input type="date" class="form-control" id="editBirthDate"
                                        placeholder="Enter birth date" name="birth_date">
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label" for="editBirthplace">Birthplace</label>
                                    <input type="text" class="form-control" id="editBirthplace"
                                        placeholder="Enter birthplace" name="birthplace">
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label" for="editAge">Age</label>
                                    <input type="number" class="form-control" id="editAge"
                                        placeholder="Enter Age" name="age" min="0">
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label" for="editCivilStatus">Civil Status</label>
                                    <select class="form-select" id="editCivilStatus" name="civil_status">
                                        <option value="">Select civil status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Common-Law">Common-Law Relationship</option>
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

                            <div class="row g-3">
                                <div class="col-lg-12">
                                    <label class="form-label" for="editAddress">Address</label>
                                    <input type="text" class="form-control" id="editAddress"
                                        placeholder="Enter address" name="address">
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label" for="editProvince">Province</label>
                                    <select class="form-select" id="editProvince" name="province">
                                        <option value="">Select province</option>
                                    </select>
                                    <input type="text" class="form-control d-none mt-2" id="editProvinceManual"
                                        name="province_manual" placeholder="Enter province manually">
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label" for="editCity">City</label>
                                    <select class="form-select" id="editCity" name="city">
                                        <option value="">Select city</option>
                                    </select>
                                    <input type="text" class="form-control d-none mt-2" id="editCityManual"
                                        name="city_manual" placeholder="Enter city manually">
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label" for="editBarangay">Barangay</label>
                                    <select class="form-select" id="editBarangay" name="barangay">
                                        <option value="">Select barangay</option>
                                    </select>
                                    <input type="text" class="form-control d-none mt-2" id="editBarangayManual"
                                        name="barangay_manual" placeholder="Enter barangay manually">
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label" for="editContact">Contact 1</label>
                                    <input type="text" class="form-control" id="editContact" name="contact"
                                        placeholder="Enter contact number" maxlength="11" inputmode="numeric">
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label" for="editContact2">Contact 2</label>
                                    <input type="text" class="form-control" id="editContact2" name="contact_2"
                                        placeholder="Enter contact number" maxlength="11" inputmode="numeric">
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label" for="editEmail">Email</label>
                                    <input type="email" class="form-control" id="editEmail"
                                        placeholder="Enter email" name="email">
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
                                    <label class="form-label" for="editEducation">Education</label>
                                    <select class="form-select" id="editEducation" name="education">
                                        <option value="">Select education</option>
                                        @foreach ($educationOptions as $educationOption)
                                            <option value="{{ $educationOption }}">{{ $educationOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editCourse">Course</label>
                                    <input type="text" class="form-control" id="editCourse"
                                        placeholder="Enter course" name="course">
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label">Sector</label>
                                    @php
                                        $editSelectedSectors = old('sectors', old('sector', $client->sector ?? ''));
                                        $editSelectedSectorsArr = is_array($editSelectedSectors)
                                            ? array_map('trim', $editSelectedSectors)
                                            : array_filter(
                                                array_map('trim', explode(',', (string) $editSelectedSectors)),
                                            );
                                    @endphp
                                    <div>
                                        <label for="editSectorSelect" class="visually-hidden">Sector</label>
                                        <select id="editSectorSelect" name="sectors[]" class="form-select" multiple>
                                            @foreach ($sectorOptions as $sectorOption)
                                                <option value="{{ $sectorOption }}"
                                                    {{ in_array($sectorOption, $editSelectedSectorsArr) ? 'selected' : '' }}>
                                                    {{ $sectorOption }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="hidden" id="editSector" name="sector"
                                        value="{{ old('sector', $client->sector ?? '') }}">
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editPositionOrganization">Position /
                                        Organization</label>
                                    <input type="text" class="form-control" id="editPositionOrganization"
                                        placeholder="Enter position or organization" name="position_organization">
                                </div>
                            </div>
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

<!-- Choices.js for compact searchable multi-select -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<style>
    /* Render Choices dropdown in-flow so it expands the modal content instead of overlaying */
    .choices__list--dropdown {
        position: static !important;
        display: none !important;
        box-shadow: none !important;
        max-width: 100% !important;
        width: auto !important;
        min-width: 100px;
    }

    .choices.is-open .choices__list--dropdown {
        display: block !important;
    }

    .choices__list--dropdown .choices__list {
        max-height: 240px !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }

    .choices__list--dropdown .choices__item {
        white-space: normal !important;
        word-break: break-word;
    }

    .choices[data-type*=select-multiple] .choices__inner {
        min-height: 44px;
        padding: .35rem .5rem;
        box-sizing: border-box;
    }

    /* Fix clipped/trimmed first letters by removing extra list padding
       and ensuring each item has its own inner padding. Also ensure
       dropdown has visible background and border so text isn't cut off. */
    .choices__list--dropdown {
        padding-left: 0 !important;
        background: #ffffff !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        border-radius: .375rem !important;
        box-sizing: border-box !important;
    }

    .choices__list--dropdown .choices__list {
        padding: 0 !important;
        margin: 0 !important;
    }

    .choices__list--dropdown .choices__item {
        padding: .375rem .75rem !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const editSectorHidden = document.getElementById('editSector');
        const editSectorSelect = document.getElementById('editSectorSelect');

        if (!editSectorHidden || !editSectorSelect) {
            return;
        }

        const selectedSectorValues = () => editSectorHidden.value
            .split(',')
            .map(value => value.trim())
            .filter(Boolean);

        const syncEditSectorHidden = () => {
            editSectorHidden.value = Array.from(editSectorSelect.selectedOptions || [])
                .map(opt => opt.value)
                .join(',');
        };

        const syncEditSectorSelect = () => {
            const selectedValues = selectedSectorValues();
            Array.from(editSectorSelect.options).forEach(opt => {
                opt.selected = selectedValues.includes(opt.value);
            });
        };

        try {
            new Choices(editSectorSelect, {
                removeItemButton: true,
                placeholderValue: 'Select sectors',
                searchPlaceholderValue: 'Search sectors',
                shouldSort: false,
                itemSelectText: '',
                position: 'bottom',
                silent: true,
            });
        } catch (e) {
            // Choices not available; continue
        }

        editSectorSelect.addEventListener('change', syncEditSectorHidden);

        syncEditSectorSelect();
        syncEditSectorHidden();

        const editClientModal = document.getElementById('editClientModal');
        if (editClientModal) {
            editClientModal.addEventListener('shown.bs.modal', syncEditSectorSelect);
            editClientModal.addEventListener('hide.bs.modal', syncEditSectorHidden);
        }

        // Edit fingerprint skip behavior
        const editOpenFingerprintBtn = document.getElementById('editOpenFingerprintBtn');
        const editClearFingerprintBtn = document.getElementById('editClearFingerprintBtn');
        const editFingerprintPreview = document.getElementById('editFingerprintPreview');
        const editFingerprintStatus = document.getElementById('editFingerprintStatus');
        const editFingerprintData = document.getElementById('editFingerprintData');
        const editFingerprintTemplate = document.getElementById('editFingerprintTemplate');
        const editRetryFingerprintCaptureBtn = document.getElementById('editScanAgainBtn');
        const editSaveFingerprintBtn = document.getElementById('editCapturePhotoBtn');
        const editSkipFingerprintCheckbox = document.getElementById('editSkipFingerprintCheckbox');

        const updateEditFingerprintMode = () => {
            if (!editSkipFingerprintCheckbox) return;
            const skip = editSkipFingerprintCheckbox.checked;
            editOpenFingerprintBtn.disabled = skip;
            editClearFingerprintBtn.disabled = skip || !editFingerprintData.value;
            if (skip) {
                editFingerprintStatus.textContent = 'Fingerprint capture skipped.';
            } else if (!editFingerprintData.value) {
                editFingerprintStatus.textContent = (editFingerprintPreview && editFingerprintPreview.dataset && editFingerprintPreview.dataset.defaultSrc && editFingerprintPreview.src !== editFingerprintPreview.dataset.defaultSrc) ? 'Existing fingerprint on file.' : 'No fingerprint captured yet.';
            } else {
                editFingerprintStatus.textContent = 'Fingerprint captured and ready to save.';
            }
        };

        if (editSkipFingerprintCheckbox) {
            // Default: check if there is no fingerprint data and preview is placeholder
            const hasEditFingerprint = (editFingerprintData && editFingerprintData.value) || (editFingerprintPreview && editFingerprintPreview.dataset && editFingerprintPreview.dataset.defaultSrc && editFingerprintPreview.src !== editFingerprintPreview.dataset.defaultSrc);
            if (!hasEditFingerprint) {
                editSkipFingerprintCheckbox.checked = true;
            }
            editSkipFingerprintCheckbox.addEventListener('change', updateEditFingerprintMode);
            // ensure mode is applied when modal shown/hidden
            if (editClientModal) {
                editClientModal.addEventListener('shown.bs.modal', updateEditFingerprintMode);
                editClientModal.addEventListener('hide.bs.modal', updateEditFingerprintMode);
            }
            updateEditFingerprintMode();
        }
    });
</script>
