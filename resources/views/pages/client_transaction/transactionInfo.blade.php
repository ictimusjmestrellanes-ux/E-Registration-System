@if (true)
<div class="modal fade" id="transactionInfoModal" tabindex="-1" aria-labelledby="transactionInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light text-black">
                <h5 class="modal-title" id="transactionInfoModalLabel">Transaction Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="transactionInfoSection">
                    <div class="px-4 py-3 bg-light border-bottom">
                        <div class="row gy-2">
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Transaction ID</div>
                                <div class="fw-bold" id="modalTransactionId">-</div>
                            </div>
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Transaction Date</div>
                                <div class="fw-bold" id="modalTransactionDate">-</div>
                            </div>
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Source</div>
                                <div class="fw-bold text-uppercase" id="modalTransactionSource">-</div>
                            </div>
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Category</div>
                                <div class="fw-bold text-uppercase" id="modalTransactionCategory">-</div>
                            </div>
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Type</div>
                                <div class="fw-bold text-uppercase" id="modalTransactionType">-</div>
                            </div>
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Clerk</div>
                                <div class="fw-bold text-uppercase" id="modalTransactionClerk">-</div>
                            </div>
                            <div class="col-md-6 d-flex">
                                <div class="text-uppercase text-muted small fw-semibold me-3">Status</div>
                                <div class="fw-bold" id="modalTransactionStatus">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3">
                        <div class="row gx-3 gy-3">
                            <div class="col-12">
                                <label class="form-label text-uppercase text-muted small fw-semibold">Description of Request</label>
                                <textarea class="form-control form-control-sm bg-white text-uppercase" rows="2" id="modalTransactionDescription" ></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase text-muted small fw-semibold">Actions Taken</label>
                                <textarea class="form-control form-control-sm bg-white text-uppercase" rows="2" id="modalTransactionActionsTaken" ></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase text-muted small fw-semibold">Remarks</label>
                                <textarea class="form-control form-control-sm bg-white text-uppercase" rows="2" id="modalTransactionRemarks" ></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <p class="text-muted mb-3 fw-semibold">Upload and confirm the following requirements:</p>

                        <div class="mb-4 p-3 border rounded-3 bg-light">
                            <label class="fw-semibold mb-2">1. Valid Id of Claimant with Address to Imus (Back to Back)</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload1" onchange="previewRequirement(this, 'reqPreview1')">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="reqPreviewBtn1" onclick="openRequirementPreview('reqPreview1')">Preview</button>
                                <img id="reqPreview1" class="rounded border bg-white object-fit-cover d-none" style="width: 64px; height: 64px;">
                            </div>
                        </div>

                        <div class="mb-4 p-3 border rounded-3 bg-light">
                            <label class="fw-semibold mb-2">2. Registered Death Certificate (CTC)</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload2" onchange="previewRequirement(this, 'reqPreview2')">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="reqPreviewBtn2" onclick="openRequirementPreview('reqPreview2')">Preview</button>
                                <img id="reqPreview2" class="rounded border bg-white object-fit-cover d-none" style="width: 64px; height: 64px;">
                            </div>
                        </div>

                        <div class="mb-3 p-3 border rounded-3 bg-light">
                            <label class="fw-semibold mb-2">3. Funeral Contract</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload3" onchange="previewRequirement(this, 'reqPreview3')">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="reqPreviewBtn3" onclick="openRequirementPreview('reqPreview3')">Preview</button>
                                <img id="reqPreview3" class="rounded border bg-white object-fit-cover d-none" style="width: 64px; height: 64px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="transactionInfoCloseBtn">Close</button>
                <button type="button" class="btn btn-primary" id="transactionInfoConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>
@endif

        <script>
            /**
             * Preview uploaded requirement file.
             * @param {HTMLInputElement} input - file input element (or passed this)
             * @param {string} previewId - id of the img element to show preview
             */
            function previewRequirement(input, previewId) {
                const file = input.files && input.files[0];
                const preview = document.getElementById(previewId);
                if (!preview) return;

                if (!file) {
                    preview.src = '';
                    preview.classList.add('d-none');
                    return;
                }

                // If image, render data URL
                if (file.type && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                    return;
                }

                // Non-image files: render a simple SVG placeholder with filename
                const safeName = (file.name || 'FILE').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const svg = `data:image/svg+xml;utf8,` + encodeURIComponent(`
                    <svg xmlns='http://www.w3.org/2000/svg' width='96' height='96'>
                        <rect width='100%' height='100%' fill='%23ffffff' stroke='%23e9ecef' />
                        <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-family='Arial, Helvetica, sans-serif' font-size='10' fill='%23666'>${safeName}</text>
                    </svg>`);
                preview.src = svg;
                preview.classList.remove('d-none');
            }
        </script>

        <!-- Requirement preview modal -->
        <div class="modal fade" id="requirementPreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Preview Requirement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="requirementPreviewImage" src="" alt="Preview" class="img-fluid rounded" style="max-height: 70vh;" />
                    </div>
                </div>
            </div>
        </div>

        <script>
            function openRequirementPreview(previewId) {
                const preview = document.getElementById(previewId);
                const modalEl = document.getElementById('requirementPreviewModal');
                const modalImage = document.getElementById('requirementPreviewImage');
                if (!preview || !modalEl || !modalImage) return;

                    const src = preview.src || '';

                    // If there's no preview yet, try to derive it from the corresponding file input
                    const ensurePreview = () => {
                        return new Promise((resolve) => {
                            try {
                                // Map previewId -> input id (reqPreview1 -> reqUpload1)
                                const inputId = previewId.replace(/^reqPreview/, 'reqUpload');
                                const inputEl = document.getElementById(inputId);

                                // Prefer reading directly from the file input if present
                                if (inputEl && inputEl.files && inputEl.files[0]) {
                                    const file = inputEl.files[0];
                                    console.debug('preview: reading file from input', inputId, file.name, file.type);
                                    if (file.type && file.type.startsWith('image/')) {
                                        const reader = new FileReader();
                                        reader.onload = function (e) {
                                            preview.src = e.target.result;
                                            preview.classList.remove('d-none');
                                            resolve(preview.src);
                                        };
                                        reader.onerror = (err) => {
                                            console.error('preview: FileReader error', err);
                                            resolve(null);
                                        };
                                        reader.readAsDataURL(file);
                                        return;
                                    }

                                    // Non-image: create svg placeholder
                                    const safeName = (file.name || 'FILE').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                    const svg = `data:image/svg+xml;utf8,` + encodeURIComponent(`\n                                    <svg xmlns='http://www.w3.org/2000/svg' width='96' height='96'>\n                                        <rect width='100%' height='100%' fill='%23ffffff' stroke='%23e9ecef' />\n                                        <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-family='Arial, Helvetica, sans-serif' font-size='10' fill='%23666'>${safeName}</text>\n                                    </svg>`);
                                    preview.src = svg;
                                    preview.classList.remove('d-none');
                                    return resolve(preview.src);
                                }

                                // Fallback: use existing preview src if visible
                                if (preview.src && !preview.classList.contains('d-none')) {
                                    console.debug('preview: using existing preview.src');
                                    return resolve(preview.src);
                                }

                                resolve(null);
                            } catch (err) {
                                console.error('preview: unexpected error', err);
                                resolve(null);
                            }
                        });
                    };

                    ensurePreview().then((finalSrc) => {
                        if (!finalSrc) {
                            alert('No file selected to preview.');
                            return;
                        }

                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        const frame = document.getElementById('requirementPreviewFrame');
                        const img = document.getElementById('requirementPreviewImage');

                        // Decide whether to show PDF/frame or image
                        const lower = String(finalSrc).toLowerCase();
                        if (lower.endsWith('.pdf') || lower.startsWith('data:application/pdf')) {
                            img.classList.add('d-none');
                            frame.src = finalSrc;
                            frame.classList.remove('d-none');
                        } else {
                            frame.classList.add('d-none');
                            img.src = finalSrc;
                            img.classList.remove('d-none');
                        }

                        modal.show();

                        // Clear image/frame when modal hidden
                        modalEl.addEventListener('hidden.bs.modal', function handler() {
                            img.src = '';
                            frame.src = '';
                            img.classList.add('d-none');
                            frame.classList.add('d-none');
                            modalEl.removeEventListener('hidden.bs.modal', handler);
                        });
                    });
            }
        </script>