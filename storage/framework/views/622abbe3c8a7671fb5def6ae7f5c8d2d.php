<div class="modal fade" id="requirementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Requirements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-3 fw-semibold">Upload the following requirements:</p>

                <form id="requirementForm">
                    <div class="mb-4 p-3 border rounded-3 bg-light">
                        <label class="fw-semibold mb-2">1. Valid Id of Claimant with Address to Imus (Back to Back)</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload1" onchange="previewRequirement(this, 'reqPreview1')">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openRequirementPreview('reqPreview1')">Preview</button>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input requirement-no-file" type="checkbox" id="reqNoFile1" data-upload-id="reqUpload1">
                            <label class="form-check-label small text-muted" for="reqNoFile1">No file to upload for this requirement</label>
                        </div>
                    </div>

                    <div class="mb-4 p-3 border rounded-3 bg-light">
                        <label class="fw-semibold mb-2">2. Registered Death Certificate (CTC)</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload2" onchange="previewRequirement(this, 'reqPreview2')">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openRequirementPreview('reqPreview2')">Preview</button>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input requirement-no-file" type="checkbox" id="reqNoFile2" data-upload-id="reqUpload2">
                            <label class="form-check-label small text-muted" for="reqNoFile2">No file to upload for this requirement</label>
                        </div>
                    </div>

                    <div class="mb-3 p-3 border rounded-3 bg-light">
                        <label class="fw-semibold mb-2">3. Funeral Contract</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload3" onchange="previewRequirement(this, 'reqPreview3')">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openRequirementPreview('reqPreview3')">Preview</button>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input requirement-no-file" type="checkbox" id="reqNoFile3" data-upload-id="reqUpload3">
                            <label class="form-check-label small text-muted" for="reqNoFile3">No file to upload for this requirement</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="requirementConfirmBtn">Upload</button>
                <button type="button" class="btn btn-primary" id="requirementContinueBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="subjectInformationModal" tabindex="-1" aria-labelledby="subjectInformationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="subjectInformationModalLabel">Subject Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="subjectInformationForm">
                    <div class="form-check text-center mb-3">
                        <input class="form-check-input" type="checkbox" id="subjectIsClient">
                        <label class="form-check-label text-primary text-decoration-underline" for="subjectIsClient">
                            Please check if subject is the client
                        </label>
                    </div>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <label for="subjectFirstName" class="form-label mb-0">First Name <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-sm" id="subjectFirstName" name="first_name" required>
                        </div>

                        <div class="col-md-3">
                            <label for="subjectMiddleName" class="form-label mb-0">Middle Name</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-sm" id="subjectMiddleName" name="middle_name">
                        </div>

                        <div class="col-md-3">
                            <label for="subjectLastName" class="form-label mb-0">Last Name <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-sm" id="subjectLastName" name="last_name" required>
                        </div>

                        <div class="col-md-3">
                            <label for="subjectNameExt" class="form-label mb-0">Name Ext.</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm" id="subjectNameExt" name="name_ext">
                        </div>
                        <div class="col-md-2">
                            <label for="subjectGender" class="form-label mb-0">Gender <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" id="subjectGender" name="gender" required>
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="subjectBirthdate" class="form-label mb-0">Birthdate <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control form-control-sm" id="subjectBirthdate" name="birthdate" required>
                        </div>
                        <div class="col-md-2">
                            <label for="subjectAge" class="form-label mb-0">Age</label>
                        </div>
                        <div class="col-md-4">
                            <input type="number" class="form-control form-control-sm bg-warning-subtle" id="subjectAge" name="age" min="0" readonly>
                        </div>

                        <div class="col-md-3">
                            <label for="subjectBarangay" class="form-label mb-0">Barangay <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-sm" id="subjectBarangay" name="barangay" required>
                        </div>

                        <div class="col-md-3">
                            <label for="subjectMunicipality" class="form-label mb-0">Municipality <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-sm" id="subjectMunicipality" name="municipality" required>
                        </div>

                        <div class="col-md-3">
                            <label for="subjectClientRelation" class="form-label mb-0">Client Relation <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-9">
                            <select class="form-select form-select-sm" id="subjectClientRelation" name="client_relation" required>
                                <option value="">Select relation</option>
                                <option value="Self">Self</option>
                                <option value="Parent">Parent</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Child">Child</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Relative">Relative</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-success-subtle justify-content-between">
                <button type="button" class="btn btn-outline-primary btn-sm" id="usePreviousSubjectBtn">
                    <i class="bi bi-person-check me-1"></i>Use Previous Subject Data
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="subjectConfirmBtn">
                        <i class="bi bi-check-circle me-1"></i>Confirm
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    $reqModalLabels = [
        1 => 'Valid Id of Claimant with Address to Imus (Back to Back)',
        2 => 'Registered Death Certificate (CTC)',
        3 => 'Funeral Contract',
    ];
?>

<?php $__currentLoopData = $reqModalLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="modal fade" id="requirementPreviewModal<?php echo e($num); ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview - <?php echo e($label); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="requirementPreviewImage<?php echo e($num); ?>" src="" alt="Preview" class="img-fluid rounded d-none" style="max-height: 70vh;" />
                <iframe id="requirementPreviewFrame<?php echo e($num); ?>" src="" class="d-none" style="width: 100%; height: 70vh; border: none;"></iframe>
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
        reader.onload = function (e) {
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
    const num = previewId.replace('reqPreview', '');
    const preview = document.getElementById(previewId);
    const modalEl = document.getElementById('requirementPreviewModal' + num);
    const modalImage = document.getElementById('requirementPreviewImage' + num);
    const modalFrame = document.getElementById('requirementPreviewFrame' + num);
    if (!preview || !modalEl) return;

    let src = preview.src || '';

    if (!src) {
        const inputId = 'reqUpload' + num;
        const inputEl = document.getElementById(inputId);
        if (inputEl && inputEl.files && inputEl.files[0]) {
            const file = inputEl.files[0];
            if (file.type && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
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
        if (frame) { frame.src = src; frame.classList.remove('d-none'); }
    } else {
        if (frame) frame.classList.add('d-none');
        if (img) { img.src = src; img.classList.remove('d-none'); }
    }
    modal.show();
    modalEl.addEventListener('hidden.bs.modal', function handler() {
        if (img) { img.src = ''; img.classList.add('d-none'); }
        if (frame) { frame.src = ''; frame.classList.add('d-none'); }
        modalEl.removeEventListener('hidden.bs.modal', handler);
    });
}

document.querySelectorAll('.requirement-no-file').forEach((checkbox) => {
    checkbox.addEventListener('change', function() {
        const fileInput = document.getElementById(this.dataset.uploadId);
        if (!fileInput) return;
        const previewButton = fileInput.closest('.d-flex')?.querySelector('button');

        fileInput.disabled = this.checked;
        if (previewButton) previewButton.disabled = this.checked;
        if (this.checked) {
            fileInput.value = '';
        }
    });
});

const subjectClientData = {
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
const subjectSaveUrlTemplate = <?php echo json_encode(route('transactions.subject.store', ['id' => '__TRANSACTION_ID__']), 512) ?>;

function fillSubjectInformation(data) {
    const fieldMap = {
        subjectFirstName: data.first_name || '',
        subjectMiddleName: data.middle_name || '',
        subjectLastName: data.last_name || '',
        subjectNameExt: data.name_ext || '',
        subjectGender: data.gender || '',
        subjectBirthdate: data.birthdate || '',
        subjectAge: data.age || '',
        subjectBarangay: data.barangay || '',
        subjectMunicipality: data.municipality || '',
        subjectClientRelation: data.client_relation || ''
    };

    Object.entries(fieldMap).forEach(([id, value]) => {
        const field = document.getElementById(id);
        if (field) field.value = value;
    });

    calculateSubjectAge();
}

function calculateSubjectAge() {
    const birthdateField = document.getElementById('subjectBirthdate');
    const ageField = document.getElementById('subjectAge');
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

document.getElementById('subjectIsClient')?.addEventListener('change', function() {
    if (this.checked) {
        fillSubjectInformation(subjectClientData);
    }
});

document.getElementById('usePreviousSubjectBtn')?.addEventListener('click', function() {
    const subjectIsClient = document.getElementById('subjectIsClient');
    if (subjectIsClient) subjectIsClient.checked = true;
    fillSubjectInformation(subjectClientData);
});

document.getElementById('subjectBirthdate')?.addEventListener('change', calculateSubjectAge);

document.getElementById('subjectConfirmBtn')?.addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('subjectInformationForm');
    if (!form) return;

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const subjectModalEl = document.getElementById('subjectInformationModal');
    const transactionId = subjectModalEl?.getAttribute('data-transaction-id');
    if (!transactionId) {
        alert('No transaction selected.');
        return;
    }

    try {
        btn.disabled = true;
        btn.dataset.defaultText = btn.dataset.defaultText || btn.textContent;
        btn.textContent = 'Saving...';

        const response = await fetch(subjectSaveUrlTemplate.replace('__TRANSACTION_ID__', transactionId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.success === false) {
            throw new Error(result.message || 'Subject information could not be saved.');
        }

        const subjectCell = document.getElementById('transactionSubjectSummary' + transactionId);
        if (subjectCell) {
            subjectCell.textContent = result.data?.subject_summary || 'N/A';
        }

        bootstrap.Modal.getInstance(subjectModalEl)?.hide();
        alert('Subject information saved in transaction history.');
    } catch (error) {
        alert('Error saving subject information: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = btn.dataset.defaultText || 'Confirm';
    }
});

async function saveRequirements(btn, options = {}) {
    const transactionId = btn.getAttribute('data-transaction-id');
    if (!transactionId) {
        alert('No transaction selected.');
        return;
    }

    const requirements = [
        { id: 'reqUpload1', noFileId: 'reqNoFile1', type: 'valid_id' },
        { id: 'reqUpload2', noFileId: 'reqNoFile2', type: 'death_certificate' },
        { id: 'reqUpload3', noFileId: 'reqNoFile3', type: 'funeral_contract' }
    ];

    try {
        btn.disabled = true;
        btn.textContent = 'Saving...';

        const incompleteRequirement = requirements.some((req) => {
            const fileInput = document.getElementById(req.id);
            const noFileCheckbox = document.getElementById(req.noFileId);

            return !(fileInput && fileInput.files.length > 0) && !noFileCheckbox?.checked;
        });

        if (incompleteRequirement) {
            throw new Error('Please upload a file or check the no-file option for each requirement.');
        }

        for (const req of requirements) {
            const fileInput = document.getElementById(req.id);
            const noFileCheckbox = document.getElementById(req.noFileId);
            const noFile = Boolean(noFileCheckbox?.checked);
            const hasFile = Boolean(fileInput && fileInput.files.length > 0);

            if (!hasFile && !noFile) {
                continue;
            }

            const formData = new FormData();
            formData.append('transaction_id', transactionId);
            formData.append('requirement_type', req.type);
            formData.append('no_file', noFile ? '1' : '0');

            if (hasFile) {
                formData.append('file', fileInput.files[0]);
            }

            const response = await fetch('<?php echo e(route('transaction-requirements.store')); ?>', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Upload failed');
            }
        }

        document.getElementById('requirementForm')?.reset();
        document.querySelectorAll('.requirement-no-file').forEach((checkbox) => {
            const fileInput = document.getElementById(checkbox.dataset.uploadId);
            if (fileInput) fileInput.disabled = false;
            const previewButton = fileInput?.closest('.d-flex')?.querySelector('button');
            if (previewButton) previewButton.disabled = false;
        });
        bootstrap.Modal.getInstance(document.getElementById('requirementModal'))?.hide();
        if (options.openSubjectModal) {
            const subjectModalEl = document.getElementById('subjectInformationModal');
            subjectModalEl?.setAttribute('data-transaction-id', transactionId);
            bootstrap.Modal.getOrCreateInstance(subjectModalEl).show();
        } else {
            alert('Requirements saved successfully!');
        }
    } catch (error) {
        alert('Error saving requirements: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = btn.dataset.defaultText || 'Upload';
    }
}

document.getElementById('requirementConfirmBtn')?.addEventListener('click', function() {
    this.dataset.defaultText = this.dataset.defaultText || this.textContent;
    saveRequirements(this);
});

document.getElementById('requirementContinueBtn')?.addEventListener('click', function() {
    const uploadBtn = document.getElementById('requirementConfirmBtn');

    this.dataset.defaultText = this.dataset.defaultText || this.textContent;
    if (uploadBtn) {
        uploadBtn.setAttribute('data-transaction-id', this.getAttribute('data-transaction-id') || '');
    }

    saveRequirements(this, { openSubjectModal: true });
});
</script>
<?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/client_transaction/requirementModal.blade.php ENDPATH**/ ?>