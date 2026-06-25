<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content" style="max-height: calc(100vh - 2rem); overflow: hidden;">
            <div class="modal-header">
                <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editClientForm" method="POST">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" id="editClientId" name="edit_client_id">
                <div class="modal-body" style="overflow-y: auto; max-height: calc(100vh - 11rem);">
                    <div class="rounded-4 client-details-panel p-3 mb-4">
                        <div class="row g-4 align-items-start">
                            <div class="col-12 col-xl-6">
                                <label class="form-label client-details-label mb-2">Client Photo <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
                                    <div class="flex-shrink-0">
                                        <img id="editClientPhoto" src="<?php echo e($defaultClientPhoto); ?>"
                                            data-default-src="<?php echo e($defaultClientPhoto); ?>" alt="Client Photo"
                                            onerror="this.onerror=null;this.src='<?php echo e($defaultClientPhoto); ?>';"
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
                                <?php $__errorArgs = ['photo_data'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div class="text-danger small mt-2"><?php echo e($message); ?></div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <div class="col-12 col-xl-6">
                                <label class="form-label client-details-label mb-2">Fingerprint Scanner <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
                                    <div class="flex-shrink-0">
                                        <img id="editFingerprintPreview"
                                            src="<?php echo e(asset('assets/images/fingerprint.png')); ?>"
                                            data-default-src="<?php echo e(asset('assets/images/fingerprint.png')); ?>" alt="Fingerprint Preview"
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
                                        <button type="button"
                                            class="btn btn-soft-primary btn-sm d-none align-self-start"
                                            id="editScanAgainBtn">Capture Again</button>
                                    </div>
                                </div>
                                <input type="hidden" id="editFingerprintData" name="fingerprint_data">
                                <input type="hidden" id="editFingerprintTemplate" name="fingerprint_template">
                                <?php $__errorArgs = ['fingerprint_data'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div class="text-danger small mt-2"><?php echo e($message); ?></div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                <?php $__errorArgs = ['fingerprint_template'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div class="text-danger small mt-2"><?php echo e($message); ?></div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
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
                                        <?php $__currentLoopData = $educationOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $educationOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($educationOption); ?>"><?php echo e($educationOption); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editCourse">Course</label>
                                    <input type="text" class="form-control" id="editCourse"
                                        placeholder="Enter course" name="course">
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="editSector">Sector</label>
                                    <select class="form-select" id="editSector" name="sector">
                                        <option value="">Select sector</option>
                                        <?php $__currentLoopData = $sectorOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectorOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($sectorOption); ?>"><?php echo e($sectorOption); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
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
<?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\clientEdit.blade.php ENDPATH**/ ?>