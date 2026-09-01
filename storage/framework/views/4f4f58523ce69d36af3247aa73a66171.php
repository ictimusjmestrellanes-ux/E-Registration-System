<?php
    $isEditMode = $isEditMode ?? false;
    $wizTxReqs = $isEditMode ? $transaction->requirements->keyBy('requirement_type') : collect();
    $wizTxDate = $isEditMode ? $transaction->transaction_date->format('Y-m-d') : now()->format('Y-m-d');
?>

<?php if($isEditMode): ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Edit Transaction</h4>
                                <p class="text-muted mb-0">
                                    Transaction <?php echo e($transaction->transaction_id); ?> &mdash;
                                    <?php echo e(strtoupper($client->full_name ?? 'Client')); ?>

                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo e(route('transactions.process', $transaction->id)); ?>"
                                    class="btn btn-outline-primary">View Process</a>
                                <a href="<?php echo e(route('clients.show', $client)); ?>" class="btn btn-secondary">Back to
                                    Client</a>
                            </div>
                        </div>
                        <form id="newTransactionForm">
                            <?php echo csrf_field(); ?>
                            <div class="wizard-body border rounded-3">
                            <?php else: ?>
                                <div class="modal fade" id="newTransactionModal" tabindex="-1"
                                    aria-labelledby="newTransactionModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="newTransactionModalLabel">New Transaction
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <form id="newTransactionForm">
                                                <?php echo csrf_field(); ?>
                                                <div class="modal-body p-0 wizard-body">
<?php endif; ?>

<div class="px-4 pt-3 pb-2 wizard-progress">
    <?php $__currentLoopData = [
        1 => 'Transaction Details',
        2 => 'Transaction Information',
        3 => 'Upload Requirements',
        4 => 'Subject Information',
        5 => 'Review',
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $wizStep => $wizLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <span class="wizard-step-badge <?php echo e($wizStep === 1 ? 'active' : ''); ?>" data-step="<?php echo e($wizStep); ?>">
            <span class="wizard-step-num"><?php echo e($wizStep); ?></span>
            <span class="wizard-step-label"><?php echo e($wizLabel); ?></span>
        </span>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<div class="wizard-step" data-step="1">
    <div class="bg-light text-white px-4 py-3">
        <h6 class="mb-0">Transaction Details</h6>
    </div>
    <div class="px-4 py-3">
        <div class="row gx-3 align-items-center mb-3">
            <div class="col-auto text-uppercase text-muted small fw-semibold">Applicant ID</div>
            <div class="col-auto flex-fill">
                <input type="text" id="clientIdInput"
                    class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold" name="client_id"
                    value="<?php echo e($client->client_id ?? ''); ?>" readonly>
            </div>
            <div class="col-auto text-uppercase text-muted small fw-semibold">Client</div>
            <div class="col-auto flex-fill">
                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    value="<?php echo e($client->full_name ?? ''); ?>" readonly>
            </div>
            <div class="col-auto text-uppercase text-muted small fw-semibold">Transaction Date</div>
            <div class="col-auto flex-fill">
                <input type="text" class="form-control form-control-sm border-0 bg-light fw-bold"
                    value="<?php echo e($isEditMode ? $transaction->transaction_date->format('m/d/Y') : now()->format('m/d/Y')); ?>"
                    readonly>
                <input type="hidden" name="transaction_date" value="<?php echo e($wizTxDate); ?>">
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-auto text-uppercase text-muted small fw-semibold">Transaction Category</div>
            <div class="col">
                <select class="form-select form-select-sm border-0 bg-light text-uppercase" name="category"
                    id="categorySelect" required>
                    <option value="">SELECT CATEGORY</option>
                    <option value="social_services_assistance" <?php if($isEditMode && $transaction->category === 'social_services_assistance'): echo 'selected'; endif; ?>>SOCIAL SERVICES ASSISTANCE
                    </option>
                    <option value="solicitation" <?php if($isEditMode && $transaction->category === 'solicitation'): echo 'selected'; endif; ?>>SOLICITATION</option>
                    <option value="youth_sports" <?php if($isEditMode && $transaction->category === 'youth_sports'): echo 'selected'; endif; ?>>YOUTH & SPORTS</option>
                    <option value="appointments" <?php if($isEditMode && $transaction->category === 'appointments'): echo 'selected'; endif; ?>>APPOINTMENTS</option>
                    <option value="infrastructure" <?php if($isEditMode && $transaction->category === 'infrastructure'): echo 'selected'; endif; ?>>INFRASTRUCTURE</option>
                    <option value="scholarships" <?php if($isEditMode && $transaction->category === 'scholarships'): echo 'selected'; endif; ?>>SCHOLARSHIPS</option>
                    <option value="permits" <?php if($isEditMode && $transaction->category === 'permits'): echo 'selected'; endif; ?>>PERMITS</option>
                    <option value="events" <?php if($isEditMode && $transaction->category === 'events'): echo 'selected'; endif; ?>>EVENTS</option>
                    <option value="job_application" <?php if($isEditMode && $transaction->category === 'job_application'): echo 'selected'; endif; ?>>JOB APPLICATION</option>
                    <option value="hoa" <?php if($isEditMode && $transaction->category === 'hoa'): echo 'selected'; endif; ?>>HOA</option>
                    <option value="others" <?php if($isEditMode && $transaction->category === 'others'): echo 'selected'; endif; ?>>OTHERS</option>
                </select>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-auto text-uppercase text-muted small fw-semibold">Transaction Type</div>
            <div class="col">
                <select class="form-select form-select-sm border-0 bg-light text-uppercase" name="type"
                    id="typeSelect" required disabled>
                    <option value="">SELECT CATEGORY FIRST</option>
                </select>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-auto text-uppercase text-muted small fw-semibold">Addressed To</div>
            <div class="col">
                <select class="form-select form-select-sm border-0 text-uppercase" name="addressed_to"
                    id="addressedToSelect" required>
                    <option value="">SELECT ADDRESSED TO</option>
                    <option value="mayor" <?php if($isEditMode && str_contains($transaction->signatory ?? '', 'ALEX')): echo 'selected'; endif; ?>>MAYOR ALEX L. ADVINCULA</option>
                    <option value="cong" <?php if($isEditMode && str_contains($transaction->signatory ?? '', 'ADRIAN')): echo 'selected'; endif; ?>>CONG. ADRIAN JAY C. ADVINCULA</option>
                    <option value="vice_mayor" <?php if($isEditMode && !str_contains($transaction->signatory ?? '', 'ALEX') && !str_contains($transaction->signatory ?? '', 'ADRIAN')): echo 'selected'; endif; ?>>Others</option>
                </select>
            </div>
        </div>
    </div>
</div>


<div class="wizard-step d-none" data-step="2">
    <div class="bg-light text-white px-4 py-3">
        <h6 class="mb-0">Transaction Information</h6>
    </div>
    <div class="px-4 py-3">
        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Transaction ID</div>
            <div class="col-md-3">
                <input type="text" id="wizTransactionIdDisplay"
                    class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    value="<?php echo e($isEditMode ? $transaction->transaction_id : 'AUTO-GENERATED'); ?>" readonly>
            </div>
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Transaction Date</div>
            <div class="col-md-3">
                <input type="text" id="wizTransactionDateDisplay"
                    class="form-control form-control-sm border-0 bg-light fw-bold"
                    value="<?php echo e($isEditMode ? $transaction->transaction_date->format('m/d/Y') : now()->format('m/d/Y')); ?>"
                    readonly>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Transaction Type</div>
            <div class="col-md-3">
                <input type="text" id="wizTransactionTypeDisplay"
                    class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold" value=""
                    readonly>
            </div>
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Client ID</div>
            <div class="col-md-3">
                <input type="text" id="wizClientIdDisplay"
                    class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    value="<?php echo e($client->client_id ?? ''); ?>" readonly>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Client</div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    value="<?php echo e(strtoupper($client->full_name ?? '')); ?>" readonly>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Client Address</div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    value="<?php echo e(strtoupper(collect([$client->address, $client->barangay, $client->city, $client->province])->filter()->implode(', ') ?:'-')); ?>"
                    readonly>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Clerk</div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    value="<?php echo e(strtoupper(auth()->user()->name ?? '')); ?>" readonly>
            </div>
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Signatory</div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold"
                    name="signatory" value="<?php echo e($isEditMode ? $transaction->signatory : ''); ?>" readonly>
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Personnel Endorsed To</div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm text-uppercase"
                    name="personnel_endorsed_to" value="<?php echo e($isEditMode ? $transaction->personnel_endorsed_to : ''); ?>"
                    placeholder="Enter personnel">
            </div>
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Responsible Office</div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm text-uppercase" name="responsible_office"
                    value="<?php echo e($isEditMode ? $transaction->responsible_office : ''); ?>" placeholder="Enter office">
            </div>
        </div>

        <div class="row gx-3 align-items-center mb-3">
            <div class="col-md-3 text-uppercase text-muted small fw-semibold">Amount Given</div>
            <div class="col-md-3">
                <input type="number" class="form-control form-control-sm" name="amount" step="0.01"
                    min="0" placeholder="0.00" value="<?php echo e($isEditMode ? $transaction->amount : ''); ?>">
            </div>
        </div>

        <hr class="my-3">

        <div class="row gx-3 gy-3">
            <div class="col-12">
                <label class="form-label text-uppercase text-muted small fw-semibold">Description of Request</label>
                <textarea class="form-control form-control-sm text-uppercase" name="description" rows="3"
                    placeholder="Enter description (optional)"><?php echo e($isEditMode ? $transaction->description : ''); ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label text-uppercase text-muted small fw-semibold">Actions Taken</label>
                <textarea class="form-control form-control-sm text-uppercase" name="actions_taken" rows="3"
                    placeholder="Enter actions taken (optional)"><?php echo e($isEditMode ? $transaction->actions_taken : ''); ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label text-uppercase text-muted small fw-semibold">Remarks</label>
                <textarea class="form-control form-control-sm text-uppercase" name="remarks" rows="3"
                    placeholder="Enter remarks (optional)"><?php echo e($isEditMode ? $transaction->remarks : ''); ?></textarea>
            </div>
        </div>
    </div>
</div>


<div class="wizard-step d-none" data-step="3">
    <div class="bg-light text-white px-4 py-3">
        <h6 class="mb-0">Upload Requirements</h6>
    </div>
    <div class="px-4 py-3">
        <p class="text-muted mb-3 small fw-semibold">Upload the required documents. Leave unchecked and empty to skip a
            requirement.</p>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="p-3 border rounded-3 bg-light h-100">
                    <label class="fw-semibold mb-2 small">1. Valid Id of Claimant with Address to Imus (Back to
                        Back)</label>
                    <input type="file" class="form-control form-control-sm" accept="image/*,.pdf"
                        id="wizReqUpload1" onchange="previewRequirement(this, 'wizReqPreview1')">
                    <img id="wizReqPreview1" src="" alt="Preview" class="img-thumbnail d-none mt-2"
                        style="max-height: 120px; object-fit: cover;">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2 w-100"
                        data-upload-id="wizReqUpload1"
                        onclick="openRequirementPreview('wizReqPreview1')">Preview</button>
                    <?php $wizCur1 = $wizTxReqs->get('valid_id'); ?>
                    <?php if($isEditMode && $wizCur1): ?>
                        <div class="small text-muted mt-2" id="wizReqCurrent1">
                            <?php if($wizCur1->file_path): ?>
                                Current file: <a
                                    href="<?php echo e(route('transaction-requirements.download', $wizCur1->id)); ?>"><?php echo e($wizCur1->file_name); ?></a>
                            <?php else: ?>
                                <span class="fst-italic">Currently marked: no file provided</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input wizard-no-file" type="checkbox" id="wizReqNoFile1"
                            data-upload-id="wizReqUpload1" <?php if($isEditMode && $wizCur1 && !$wizCur1->file_path): echo 'checked'; endif; ?>>
                        <label class="form-check-label small text-muted" for="wizReqNoFile1">No file to upload for
                            this requirement</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="p-3 border rounded-3 bg-light h-100">
                    <label class="fw-semibold mb-2 small">2. Registered Death Certificate (CTC)</label>
                    <input type="file" class="form-control form-control-sm" accept="image/*,.pdf"
                        id="wizReqUpload2" onchange="previewRequirement(this, 'wizReqPreview2')">
                    <img id="wizReqPreview2" src="" alt="Preview" class="img-thumbnail d-none mt-2"
                        style="max-height: 120px; object-fit: cover;">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2 w-100"
                        data-upload-id="wizReqUpload2"
                        onclick="openRequirementPreview('wizReqPreview2')">Preview</button>
                    <?php $wizCur2 = $wizTxReqs->get('death_certificate'); ?>
                    <?php if($isEditMode && $wizCur2): ?>
                        <div class="small text-muted mt-2" id="wizReqCurrent2">
                            <?php if($wizCur2->file_path): ?>
                                Current file: <a
                                    href="<?php echo e(route('transaction-requirements.download', $wizCur2->id)); ?>"><?php echo e($wizCur2->file_name); ?></a>
                            <?php else: ?>
                                <span class="fst-italic">Currently marked: no file provided</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input wizard-no-file" type="checkbox" id="wizReqNoFile2"
                            data-upload-id="wizReqUpload2" <?php if($isEditMode && $wizCur2 && !$wizCur2->file_path): echo 'checked'; endif; ?>>
                        <label class="form-check-label small text-muted" for="wizReqNoFile2">No file to upload for
                            this requirement</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="p-3 border rounded-3 bg-light h-100">
                    <label class="fw-semibold mb-2 small">3. Funeral Contract</label>
                    <input type="file" class="form-control form-control-sm" accept="image/*,.pdf"
                        id="wizReqUpload3" onchange="previewRequirement(this, 'wizReqPreview3')">
                    <img id="wizReqPreview3" src="" alt="Preview" class="img-thumbnail d-none mt-2"
                        style="max-height: 120px; object-fit: cover;">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2 w-100"
                        data-upload-id="wizReqUpload3"
                        onclick="openRequirementPreview('wizReqPreview3')">Preview</button>
                    <?php $wizCur3 = $wizTxReqs->get('funeral_contract'); ?>
                    <?php if($isEditMode && $wizCur3): ?>
                        <div class="small text-muted mt-2" id="wizReqCurrent3">
                            <?php if($wizCur3->file_path): ?>
                                Current file: <a
                                    href="<?php echo e(route('transaction-requirements.download', $wizCur3->id)); ?>"><?php echo e($wizCur3->file_name); ?></a>
                            <?php else: ?>
                                <span class="fst-italic">Currently marked: no file provided</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input wizard-no-file" type="checkbox" id="wizReqNoFile3"
                            data-upload-id="wizReqUpload3" <?php if($isEditMode && $wizCur3 && !$wizCur3->file_path): echo 'checked'; endif; ?>>
                        <label class="form-check-label small text-muted" for="wizReqNoFile3">No file to upload for
                            this requirement</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="wizard-step d-none" data-step="4">
    <div class="bg-light text-white px-4 py-3">
        <h6 class="mb-0">Subject Information</h6>
    </div>
    <div class="px-4 py-3">
        <div class="form-check text-center mb-3">
            <input class="form-check-input" type="checkbox" id="wizSubjectIsClient">
            <label class="form-check-label text-primary text-decoration-underline" for="wizSubjectIsClient">
                Please check if subject is the client
            </label>
        </div>
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <label for="wizSubjectFirstName" class="form-label mb-0">First Name <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm text-uppercase" id="wizSubjectFirstName"
                    name="first_name" value="<?php echo e($isEditMode ? $transaction->subject_first_name : ''); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="wizSubjectMiddleName" class="form-label mb-0">Middle Name</label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm text-uppercase" id="wizSubjectMiddleName"
                    name="middle_name" value="<?php echo e($isEditMode ? $transaction->subject_middle_name : ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="wizSubjectLastName" class="form-label mb-0">Last Name <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm text-uppercase" id="wizSubjectLastName"
                    name="last_name" value="<?php echo e($isEditMode ? $transaction->subject_last_name : ''); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="wizSubjectNameExt" class="form-label mb-0">Name Ext.</label>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm text-uppercase" id="wizSubjectNameExt"
                    name="name_ext" value="<?php echo e($isEditMode ? $transaction->subject_name_ext : ''); ?>">
            </div>
            <div class="col-md-2">
                <label for="wizSubjectGender" class="form-label mb-0">Gender <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" id="wizSubjectGender" name="gender" required>
                    <option value="">Select gender</option>
                    <option value="Male" <?php if($isEditMode && $transaction->subject_gender === 'Male'): echo 'selected'; endif; ?>>Male</option>
                    <option value="Female" <?php if($isEditMode && $transaction->subject_gender === 'Female'): echo 'selected'; endif; ?>>Female</option>
                    <option value="Other" <?php if($isEditMode && $transaction->subject_gender === 'Other'): echo 'selected'; endif; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="wizSubjectBirthdate" class="form-label mb-0">Birthdate <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control form-control-sm" id="wizSubjectBirthdate" name="birthdate"
                    value="<?php echo e($isEditMode ? optional($transaction->subject_birthdate)->format('Y-m-d') : ''); ?>"
                    required>
            </div>
            <div class="col-md-2">
                <label for="wizSubjectAge" class="form-label mb-0">Age</label>
            </div>
            <div class="col-md-4">
                <input type="number" class="form-control form-control-sm bg-warning-subtle" id="wizSubjectAge"
                    name="age" min="0" readonly
                    value="<?php echo e($isEditMode ? $transaction->subject_age : ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="wizSubjectBarangay" class="form-label mb-0">Barangay <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm text-uppercase" id="wizSubjectBarangay"
                    name="barangay" value="<?php echo e($isEditMode ? $transaction->subject_barangay : ''); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="wizSubjectMunicipality" class="form-label mb-0">Municipality <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm text-uppercase" id="wizSubjectMunicipality"
                    name="municipality" value="<?php echo e($isEditMode ? $transaction->subject_municipality : ''); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="wizSubjectClientRelation" class="form-label mb-0">Client Relation <span
                        class="text-danger">*</span></label>
            </div>
            <div class="col-md-9">
                <select class="form-select form-select-sm" id="wizSubjectClientRelation" name="client_relation"
                    required>
                    <option value="">Select relation</option>
                    <option value="Self" <?php if($isEditMode && $transaction->subject_client_relation === 'Self'): echo 'selected'; endif; ?>>Self</option>
                    <option value="Parent" <?php if($isEditMode && $transaction->subject_client_relation === 'Parent'): echo 'selected'; endif; ?>>Parent</option>
                    <option value="Spouse" <?php if($isEditMode && $transaction->subject_client_relation === 'Spouse'): echo 'selected'; endif; ?>>Spouse</option>
                    <option value="Child" <?php if($isEditMode && $transaction->subject_client_relation === 'Child'): echo 'selected'; endif; ?>>Child</option>
                    <option value="Sibling" <?php if($isEditMode && $transaction->subject_client_relation === 'Sibling'): echo 'selected'; endif; ?>>Sibling</option>
                    <option value="Relative" <?php if($isEditMode && $transaction->subject_client_relation === 'Relative'): echo 'selected'; endif; ?>>Relative</option>
                    <option value="Guardian" <?php if($isEditMode && $transaction->subject_client_relation === 'Guardian'): echo 'selected'; endif; ?>>Guardian</option>
                    <option value="Other" <?php if($isEditMode && $transaction->subject_client_relation === 'Other'): echo 'selected'; endif; ?>>Other</option>
                </select>
            </div>
        </div>
    </div>
</div>


<div class="wizard-step d-none" data-step="5">
    <div class="bg-light text-white px-4 py-3">
        <h6 class="mb-0">Review</h6>
    </div>
    <div class="px-4 py-3">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <tbody>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold" style="width: 220px;">
                            Client</th>
                        <td class="text-uppercase" id="reviewClient">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Applicant ID</th>
                        <td class="text-uppercase" id="reviewClientId">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Transaction Date</th>
                        <td id="reviewDate">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Category</th>
                        <td class="text-uppercase" id="reviewCategory">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Type</th>
                        <td class="text-uppercase" id="reviewType">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Addressed To</th>
                        <td class="text-uppercase" id="reviewAddressedTo">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Description</th>
                        <td class="text-uppercase" id="reviewDescription">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Actions Taken</th>
                        <td class="text-uppercase" id="reviewActionsTaken">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Remarks</th>
                        <td class="text-uppercase" id="reviewRemarks">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Signatory</th>
                        <td class="text-uppercase" id="reviewSignatory">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Personnel Endorsed To
                        </th>
                        <td class="text-uppercase" id="reviewPersonnelEndorsedTo">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Responsible Office</th>
                        <td class="text-uppercase" id="reviewResponsibleOffice">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Amount Given</th>
                        <td id="reviewAmount">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Requirements</th>
                        <td id="reviewRequirements">-</td>
                    </tr>
                    <tr>
                        <th class="bg-light-subtle text-uppercase text-muted small fw-semibold">Subject</th>
                        <td class="text-uppercase" id="reviewSubject">-</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if($isEditMode): ?>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-3 mt-3">
        <button type="button" class="btn btn-secondary d-none" id="wizardBackBtn">Back</button>
        <button type="button" class="btn btn-primary" id="wizardNextBtn">Next</button>
    </div>
    </form>
    </div>
    </div>
    </div>
    </div>
    </div>
<?php else: ?>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary d-none" id="wizardBackBtn">Back</button>
        <button type="button" class="btn btn-primary" id="wizardNextBtn">Next</button>
    </div>
    </form>
    </div>
    </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="confirmTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-question-circle-fill text-primary" style="font-size: 3rem;"></i>
                </div>
                <p class="fs-5 fw-semibold mb-1"><?php echo e($isEditMode ? 'Update Transaction' : 'Confirm Transaction'); ?></p>
                <p class="text-muted mb-0">Are you sure you want to
                    <?php echo e($isEditMode ? 'save the changes to this' : 'submit this'); ?> transaction?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"
                    id="cancelTransactionConfirmBtn">Cancel</button>
                <button type="button" class="btn btn-primary px-4"
                    id="submitTransactionBtn"><?php echo e($isEditMode ? 'Update' : 'Submit'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
    .wizard-body {
        max-height: 72vh;
        overflow-y: auto;
    }

    .wizard-progress {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .wizard-step-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        background: #e9ecef;
        color: #6c757d;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .wizard-step-badge .wizard-step-num {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #adb5bd;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .wizard-step-badge.active {
        background: rgba(13, 110, 253, 0.12);
        color: #0d6efd;
    }

    .wizard-step-badge.active .wizard-step-num {
        background: #0d6efd;
    }

    .wizard-step-badge.completed {
        background: rgba(25, 135, 84, 0.12);
        color: #198754;
    }

    .wizard-step-badge.completed .wizard-step-num {
        background: #198754;
    }

    .wizard-step-badge.completed .wizard-step-label::after {
        content: ' ✓';
    }

    @media (max-width: 767.98px) {
        .wizard-step-badge .wizard-step-label {
            display: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('newTransactionModal');
        const form = document.getElementById('newTransactionForm');
        const backBtn = document.getElementById('wizardBackBtn');
        const nextBtn = document.getElementById('wizardNextBtn');
        const categorySelect = document.getElementById('categorySelect');
        const typeSelect = document.getElementById('typeSelect');
        const confirmModal = document.getElementById('confirmTransactionModal');
        const confirmModalInstance = bootstrap.Modal.getOrCreateInstance(confirmModal);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const storeUrl = <?php echo json_encode(route('transactions.store'), 15, 512) ?>;
        const requirementsUrl = <?php echo json_encode(route('transaction-requirements.store'), 15, 512) ?>;
        const subjectUrlTemplate = <?php echo json_encode(route('transactions.subject.store', ['id' => '__TRANSACTION_ID__']), 512) ?>;
        const clientShowUrl = <?php echo json_encode(route('clients.show', $client), 512) ?>;
        const isEdit = <?php echo json_encode($isEditMode, 15, 512) ?>;
        const updateUrl = <?php echo json_encode($isEditMode ? route('transactions.update', $transaction->id) : '', 512) ?>;

        const TOTAL_STEPS = 5;
        let currentStep = 1;
        let submitting = false;

        const socialServicesTypes = [{
                value: 'null',
                label: 'SELECT TRANSACTION TYPE'
            },
            {
                value: 'burial_assistance',
                label: 'BURIAL ASSISTANCE'
            },
            {
                value: 'educational_assistance',
                label: 'EDUCATIONAL ASSISTANCE'
            },
            {
                value: 'financial_balik_probinsya',
                label: 'FINANCIAL ASSISTANCE - BALIK PROBINSYA'
            },
            {
                value: 'financial_fire_victims',
                label: 'FINANCIAL ASSISTANCE - FIRE VICTIMS'
            },
            {
                value: 'medical_hospitalization',
                label: 'MEDICAL ASSISTANCE - CONFINEMENT/HOSPITALIZATION'
            },
            {
                value: 'medical_chemo_dialisys',
                label: 'MEDICAL ASSISTANCE - CHEMO/DIALYSIS'
            },
            {
                value: 'medical_regular',
                label: 'MEDICAL ASSISTANCE - REGULAR MEDICATION'
            },
            {
                value: 'subsistence_assistance',
                label: 'SUBSISTENCE ASSISTANCE'
            }
        ];

        const eventTypes = [{
                value: 'null',
                label: 'SELECT TRANSACTION TYPE'
            },
            {
                value: 'events',
                label: 'EVENTS'
            },
            {
                value: 'bigay_bigas_sa_masa',
                label: 'BIGAY BIGAS SA MASA'
            },
            {
                value: 'caravan',
                label: 'CARAVAN'
            }
        ];

        const typeLabels = {
            solicitation: 'SOLICITATION',
            youth_sports: 'YOUTH & SPORTS',
            appointments: 'APPOINTMENTS',
            infrastructure: 'INFRASTRUCTURE',
            scholarships: 'SCHOLARSHIPS',
            permits: 'PERMITS',
            job_application: 'JOB APPLICATION',
            hoa: 'HOA',
            others: 'OTHERS'
        };

        function populateTypeSelect(category, selectedValue) {
            typeSelect.innerHTML = '';
            typeSelect.disabled = true;

            if (!category) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'SELECT CATEGORY FIRST';
                typeSelect.appendChild(opt);
                return;
            }

            if (category === 'social_services_assistance') {
                socialServicesTypes.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.value;
                    opt.textContent = t.label;
                    if (selectedValue && t.value === selectedValue) opt.selected = true;
                    typeSelect.appendChild(opt);
                });
                typeSelect.disabled = false;
            } else if (category === 'events') {
                eventTypes.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.value;
                    opt.textContent = t.label;
                    if (selectedValue && t.value === selectedValue) opt.selected = true;
                    typeSelect.appendChild(opt);
                });
                typeSelect.disabled = false;
            } else if (typeLabels[category]) {
                const opt = document.createElement('option');
                opt.value = category;
                opt.textContent = typeLabels[category];
                opt.selected = true;
                typeSelect.appendChild(opt);
            }
        }

        categorySelect.addEventListener('change', function() {
            populateTypeSelect(this.value, '');
        });

        function goToStep(step) {
            if (step < 1 || step > TOTAL_STEPS) return;
            currentStep = step;

            document.querySelectorAll('.wizard-step').forEach(el => {
                el.classList.toggle('d-none', Number(el.dataset.step) !== step);
            });
            document.querySelectorAll('.wizard-step-badge').forEach(el => {
                const n = Number(el.dataset.step);
                el.classList.toggle('active', n === step);
                el.classList.toggle('completed', n < step);
            });

            backBtn.classList.toggle('d-none', step === 1);
            nextBtn.textContent = step === TOTAL_STEPS ? (isEdit ? 'Review & Update' : 'Confirm Transaction') :
                'Next';
            modalEl?.querySelector('.modal-body')?.scrollTo(0, 0);
        }

        function validateStep(step) {
            const container = document.querySelector('.wizard-step[data-step="' + step + '"]');
            if (!container) return true;

            const fields = container.querySelectorAll('[required]');
            for (const field of fields) {
                if (field.disabled) continue;
                if (!field.value) {
                    field.reportValidity();
                    return false;
                }
            }
            return true;
        }

        const clientSubjectData = {
            first_name: <?php echo json_encode($client->first_name ?? '', 15, 512) ?>,
            middle_name: <?php echo json_encode($client->middle_name ?? '', 15, 512) ?>,
            last_name: <?php echo json_encode($client->last_name ?? '', 15, 512) ?>,
            name_ext: <?php echo json_encode($client->suffix ?? '', 15, 512) ?>,
            gender: <?php echo json_encode($client->gender ?? '', 15, 512) ?>,
            birthdate: <?php echo json_encode(optional($client->birth_date ?? null)->format('Y-m-d'), 15, 512) ?>,
            age: <?php echo json_encode($client->age ?? '', 15, 512) ?>,
            barangay: <?php echo json_encode($client->barangay ?? '', 15, 512) ?>,
            municipality: <?php echo json_encode($client->city ?? '', 15, 512) ?>,
            client_relation: 'Self'
        };

        function fillSubject() {
            const fieldMap = {
                wizSubjectFirstName: clientSubjectData.first_name || '',
                wizSubjectMiddleName: clientSubjectData.middle_name || '',
                wizSubjectLastName: clientSubjectData.last_name || '',
                wizSubjectNameExt: clientSubjectData.name_ext || '',
                wizSubjectGender: clientSubjectData.gender || '',
                wizSubjectBirthdate: clientSubjectData.birthdate || '',
                wizSubjectAge: clientSubjectData.age || '',
                wizSubjectBarangay: clientSubjectData.barangay || '',
                wizSubjectMunicipality: clientSubjectData.municipality || '',
                wizSubjectClientRelation: clientSubjectData.client_relation || ''
            };

            Object.entries(fieldMap).forEach(([id, value]) => {
                const field = document.getElementById(id);
                if (field) field.value = value;
            });
        }

        function calculateSubjectAge() {
            const birthdateField = document.getElementById('wizSubjectBirthdate');
            const ageField = document.getElementById('wizSubjectAge');
            if (!birthdateField || !ageField || !birthdateField.value) {
                if (ageField) ageField.value = '';
                return;
            }

            const birthdate = new Date(birthdateField.value);
            const today = new Date();
            let age = today.getFullYear() - birthdate.getFullYear();
            const monthDiff = today.getMonth() - birthdate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                age -= 1;
            }

            ageField.value = Math.max(age, 0);
        }

        document.getElementById('wizSubjectIsClient')?.addEventListener('change', function() {
            if (this.checked) fillSubject();
        });

        document.getElementById('wizSubjectBirthdate')?.addEventListener('change', calculateSubjectAge);

        const requirementMeta = [{
                id: 'wizReqUpload1',
                noFileId: 'wizReqNoFile1',
                currentId: 'wizReqCurrent1',
                type: 'valid_id',
                label: 'Valid Id of Claimant'
            },
            {
                id: 'wizReqUpload2',
                noFileId: 'wizReqNoFile2',
                currentId: 'wizReqCurrent2',
                type: 'death_certificate',
                label: 'Registered Death Certificate'
            },
            {
                id: 'wizReqUpload3',
                noFileId: 'wizReqNoFile3',
                currentId: 'wizReqCurrent3',
                type: 'funeral_contract',
                label: 'Funeral Contract'
            }
        ];

        function setNoFileState(uploadId, checked) {
            const fileInput = document.getElementById(uploadId);
            if (!fileInput) return;

            fileInput.disabled = checked;
            document.querySelectorAll(`[data-upload-id="${uploadId}"]:not(input[type="checkbox"])`).forEach((
                el) => {
                    el.disabled = checked;
                });

            if (checked) {
                fileInput.value = '';
                const preview = document.getElementById('wizReqPreview' + uploadId.replace('wizReqUpload', ''));
                if (preview) {
                    preview.src = '';
                    preview.classList.add('d-none');
                }
            }
        }

        document.querySelectorAll('.wizard-no-file').forEach((checkbox) => {
            checkbox.addEventListener('change', function() {
                setNoFileState(this.dataset.uploadId, this.checked);
            });
        });

        function fillStep2() {
            const typeDisplay = document.getElementById('wizTransactionTypeDisplay');
            if (typeDisplay) {
                typeDisplay.value = typeSelect && typeSelect.selectedIndex >= 0 ? typeSelect.options[typeSelect
                    .selectedIndex].textContent : '-';
            }
            syncSignatory();
        }

        function initEditMode() {
            populateTypeSelect(categorySelect.value, <?php echo json_encode($isEditMode ? $transaction->type : '', 15, 512) ?>);
            document.querySelectorAll('.wizard-no-file').forEach((checkbox) => {
                if (checkbox.checked) setNoFileState(checkbox.dataset.uploadId, true);
            });
            syncSignatory();
            calculateSubjectAge();
        }

        function syncSignatory() {
            const addressedToSelect = document.getElementById('addressedToSelect');
            const signatoryInput = form.elements['signatory'];
            if (signatoryInput) {
                signatoryInput.value = addressedToSelect && addressedToSelect.selectedIndex >= 0 ?
                    addressedToSelect.options[addressedToSelect.selectedIndex].textContent :
                    '';
            }
        }

        document.getElementById('addressedToSelect')?.addEventListener('change', syncSignatory);

        function fillReview() {
            const namedValue = (name) => (form.elements[name] ? form.elements[name].value : '').trim();
            const selectedText = (select) => select && select.selectedIndex >= 0 ? select.options[select
                .selectedIndex].textContent : '-';

            document.getElementById('reviewClient').textContent = <?php echo json_encode(strtoupper($client->full_name ?? 'Client'), 15, 512) ?>;
            document.getElementById('reviewClientId').textContent = namedValue('client_id') || '-';
            document.getElementById('reviewDate').textContent = namedValue('transaction_date') || '-';
            document.getElementById('reviewCategory').textContent = selectedText(categorySelect);
            document.getElementById('reviewType').textContent = selectedText(typeSelect);
            document.getElementById('reviewAddressedTo').textContent = selectedText(document.getElementById(
                'addressedToSelect'));
            document.getElementById('reviewDescription').textContent = namedValue('description') || 'N/A';
            document.getElementById('reviewActionsTaken').textContent = namedValue('actions_taken') || 'N/A';
            document.getElementById('reviewRemarks').textContent = namedValue('remarks') || 'N/A';
            document.getElementById('reviewSignatory').textContent = namedValue('signatory') || 'N/A';
            document.getElementById('reviewPersonnelEndorsedTo').textContent = namedValue(
                'personnel_endorsed_to') || 'N/A';
            document.getElementById('reviewResponsibleOffice').textContent = namedValue('responsible_office') ||
                'N/A';
            document.getElementById('reviewAmount').textContent = namedValue('amount') ? '₱' + Number(
                namedValue('amount')).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            }) : 'N/A';

            const reqItems = requirementMeta.map((meta) => {
                const fileInput = document.getElementById(meta.id);
                const noFileCheckbox = document.getElementById(meta.noFileId);
                const fileName = fileInput?.files?.[0]?.name;
                if (fileName) return meta.label + ': ' + fileName;
                if (noFileCheckbox?.checked) return meta.label + ': No file';
                const currentEl = document.getElementById(meta.currentId);
                const currentText = currentEl ? currentEl.textContent.trim() : '';
                if (currentText.startsWith('Current file:')) {
                    return meta.label + ': ' + currentText.replace('Current file:', '').trim();
                }
                if (currentText.includes('no file provided')) {
                    return meta.label + ': No file';
                }
                return meta.label + ': Not provided';
            });
            document.getElementById('reviewRequirements').innerHTML = reqItems.map(item => '<div>' + item +
                '</div>').join('');

            const subjectParts = [
                namedValue('first_name'),
                namedValue('middle_name'),
                namedValue('last_name'),
                namedValue('name_ext')
            ].filter(Boolean).join(' ').toUpperCase();
            const subjectLine = subjectParts || '-';
            const subjectMeta = [];
            if (namedValue('gender')) subjectMeta.push(namedValue('gender'));
            if (namedValue('birthdate')) subjectMeta.push(namedValue('birthdate'));
            if (namedValue('barangay')) subjectMeta.push('Brgy. ' + namedValue('barangay'));
            if (namedValue('municipality')) subjectMeta.push(namedValue('municipality'));
            if (namedValue('client_relation')) subjectMeta.push('Relation: ' + namedValue('client_relation'));
            document.getElementById('reviewSubject').textContent = subjectLine + (subjectMeta.length ? ' (' +
                subjectMeta.join(', ') + ')' : '');
        }

        nextBtn.addEventListener('click', function() {
            if (currentStep === TOTAL_STEPS) {
                confirmModalInstance.show();
                return;
            }
            if (!validateStep(currentStep)) return;
            const nextStep = currentStep + 1;
            goToStep(nextStep);
            if (nextStep === 2) fillStep2();
            if (nextStep === TOTAL_STEPS) fillReview();
        });

        backBtn.addEventListener('click', function() {
            goToStep(currentStep - 1);
        });

        confirmModal.addEventListener('hidden.bs.modal', function() {
            if (!submitting && modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });

        document.getElementById('submitTransactionBtn').addEventListener('click', submitTransaction);

        async function submitTransaction() {
            if (submitting) return;
            submitting = true;
            const btn = document.getElementById('submitTransactionBtn');
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            try {
                const formData = new FormData(form);

                let res = await fetch(isEdit ? updateUrl : storeUrl, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });
                let data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Failed to ' + (isEdit ? 'update' : 'create') +
                        ' the transaction.');
                }

                const transactionId = data.transaction.id;

                for (const meta of requirementMeta) {
                    const fileInput = document.getElementById(meta.id);
                    const noFileCheckbox = document.getElementById(meta.noFileId);
                    const noFile = Boolean(noFileCheckbox?.checked);
                    const hasFile = Boolean(fileInput && fileInput.files.length > 0);
                    if (!hasFile && !noFile) continue;

                    const fd = new FormData();
                    fd.append('transaction_id', transactionId);
                    fd.append('requirement_type', meta.type);
                    fd.append('no_file', noFile ? '1' : '0');
                    if (hasFile) fd.append('file', fileInput.files[0]);

                    res = await fetch(requirementsUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: fd
                    });
                    data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'A requirement upload failed.');
                    }
                }

                const subjectPayload = {
                    first_name: document.getElementById('wizSubjectFirstName').value,
                    middle_name: document.getElementById('wizSubjectMiddleName').value,
                    last_name: document.getElementById('wizSubjectLastName').value,
                    name_ext: document.getElementById('wizSubjectNameExt').value,
                    gender: document.getElementById('wizSubjectGender').value,
                    birthdate: document.getElementById('wizSubjectBirthdate').value,
                    age: document.getElementById('wizSubjectAge').value,
                    barangay: document.getElementById('wizSubjectBarangay').value,
                    municipality: document.getElementById('wizSubjectMunicipality').value,
                    client_relation: document.getElementById('wizSubjectClientRelation').value
                };

                res = await fetch(subjectUrlTemplate.replace('__TRANSACTION_ID__', transactionId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(subjectPayload)
                });
                data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Subject information could not be saved.');
                }

                confirmModalInstance.hide();
                window.location.href = data.redirect || (clientShowUrl + '?show_transaction=' +
                    transactionId);
            } catch (error) {
                alert('Error: ' + error.message);
                submitting = false;
                btn.disabled = false;
                btn.textContent = isEdit ? 'Update' : 'Submit';
            }
        }

        if (isEdit) initEditMode();
    });
</script>

<?php
    $wizPreviewLabels = [
        1 => 'Valid Id of Claimant with Address to Imus (Back to Back)',
        2 => 'Registered Death Certificate (CTC)',
        3 => 'Funeral Contract',
    ];
?>

<?php $__currentLoopData = $wizPreviewLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="modal fade" id="requirementPreviewModal<?php echo e($num); ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview - <?php echo e($label); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="requirementPreviewImage<?php echo e($num); ?>" src="" alt="Preview"
                        class="img-fluid rounded d-none" style="max-height: 70vh;" />
                    <iframe id="requirementPreviewFrame<?php echo e($num); ?>" src="" class="d-none"
                        style="width: 100%; height: 70vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<script>
    function previewRequirement(input, previewId) {
        const file = input.files && input.files[0];
        const preview = document.getElementById(previewId);
        if (!preview) return;

        if (!file) {
            preview.src = '';
            preview.classList.add('d-none');
            return;
        }

        if (file.type && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
            return;
        }

        const safeName = (file.name || 'FILE').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const svg = `data:image/svg+xml;utf8,` + encodeURIComponent(`
        <svg xmlns='http://www.w3.org/2000/svg' width='96' height='96'>
            <rect width='100%' height='100%' fill='%23ffffff' stroke='%23e9ecef' />
            <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-family='Arial, Helvetica, sans-serif' font-size='10' fill='%23666'>${safeName}</text>
        </svg>`);
        preview.src = svg;
        preview.classList.remove('d-none');
    }

    function openRequirementPreview(previewId) {
        const num = previewId.replace('reqPreview', '').replace('wizReqPreview', '');
        const preview = document.getElementById(previewId);
        const modalEl = document.getElementById('requirementPreviewModal' + num);
        const modalImage = document.getElementById('requirementPreviewImage' + num);
        const modalFrame = document.getElementById('requirementPreviewFrame' + num);
        if (!preview || !modalEl) return;

        let src = preview.src || '';

        if (!src) {
            const inputEl = document.getElementById('reqUpload' + num) || document.getElementById('wizReqUpload' + num);
            if (inputEl && inputEl.files && inputEl.files[0]) {
                const file = inputEl.files[0];
                if (file.type && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                        showRequirementPreviewModal(e.target.result, modalEl, modalImage, modalFrame);
                    };
                    reader.readAsDataURL(file);
                    return;
                }
                alert('Cannot preview non-image files directly.');
                return;
            }
            alert('No file selected to preview.');
            return;
        }

        showRequirementPreviewModal(src, modalEl, modalImage, modalFrame);
    }

    function showRequirementPreviewModal(src, modalEl, img, frame) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const lower = String(src).toLowerCase();
        if (lower.endsWith('.pdf') || lower.startsWith('data:application/pdf')) {
            if (img) img.classList.add('d-none');
            if (frame) {
                frame.src = src;
                frame.classList.remove('d-none');
            }
        } else {
            if (frame) frame.classList.add('d-none');
            if (img) {
                img.src = src;
                img.classList.remove('d-none');
            }
        }
        modal.show();
        modalEl.addEventListener('hidden.bs.modal', function handler() {
            if (img) {
                img.src = '';
                img.classList.add('d-none');
            }
            if (frame) {
                frame.src = '';
                frame.classList.add('d-none');
            }
            modalEl.removeEventListener('hidden.bs.modal', handler);
        });
    }
</script>
<?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/client_transaction/newTransaction.blade.php ENDPATH**/ ?>