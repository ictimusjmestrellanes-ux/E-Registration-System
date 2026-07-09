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
                    </div>

                    <div class="mb-4 p-3 border rounded-3 bg-light">
                        <label class="fw-semibold mb-2">2. Registered Death Certificate (CTC)</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload2" onchange="previewRequirement(this, 'reqPreview2')">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openRequirementPreview('reqPreview2')">Preview</button>
                        </div>
                    </div>

                    <div class="mb-3 p-3 border rounded-3 bg-light">
                        <label class="fw-semibold mb-2">3. Funeral Contract</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload3" onchange="previewRequirement(this, 'reqPreview3')">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openRequirementPreview('reqPreview3')">Preview</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="requirementConfirmBtn">Upload</button>
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

document.getElementById('requirementConfirmBtn')?.addEventListener('click', async function() {
    const btn = this;
    const transactionId = btn.getAttribute('data-transaction-id');
    if (!transactionId) {
        alert('No transaction selected.');
        return;
    }

    const requirements = [
        { id: 'reqUpload1', type: 'valid_id' },
        { id: 'reqUpload2', type: 'death_certificate' },
        { id: 'reqUpload3', type: 'funeral_contract' }
    ];

    try {
        btn.disabled = true;
        btn.textContent = 'Uploading...';

        for (const req of requirements) {
            const fileInput = document.getElementById(req.id);
            if (fileInput && fileInput.files.length > 0) {
                const formData = new FormData();
                formData.append('transaction_id', transactionId);
                formData.append('requirement_type', req.type);
                formData.append('file', fileInput.files[0]);

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
        }

        bootstrap.Modal.getInstance(document.getElementById('requirementModal'))?.hide();
        alert('Requirements uploaded successfully!');
    } catch (error) {
        alert('Error uploading files: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Upload';
    }
});
</script>
<?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/client_transaction/requirementModal.blade.php ENDPATH**/ ?>