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
                                <textarea class="form-control form-control-sm bg-white text-uppercase" rows="2" id="modalTransactionDescription" readonly></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase text-muted small fw-semibold">Actions Taken</label>
                                <textarea class="form-control form-control-sm bg-white text-uppercase" rows="2" id="modalTransactionActionsTaken" readonly></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase text-muted small fw-semibold">Remarks</label>
                                <textarea class="form-control form-control-sm bg-white text-uppercase" rows="2" id="modalTransactionRemarks" readonly></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <p class="text-muted mb-3 fw-semibold">Upload and confirm the following requirements:</p>

                        <div class="mb-4 p-3 border rounded-3 bg-light">
                            <label class="fw-semibold mb-2">1. Valid Id of Claimant with Address to Imus (Back to Back)</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload1" onchange="previewRequirement(this, 'reqPreview1')">
                                <img id="reqPreview1" class="rounded border bg-white object-fit-cover d-none" style="width: 64px; height: 64px;">
                            </div>
                        </div>

                        <div class="mb-4 p-3 border rounded-3 bg-light">
                            <label class="fw-semibold mb-2">2. Registered Death Certificate (CTC)</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload2" onchange="previewRequirement(this, 'reqPreview2')">
                                <img id="reqPreview2" class="rounded border bg-white object-fit-cover d-none" style="width: 64px; height: 64px;">
                            </div>
                        </div>

                        <div class="mb-3 p-3 border rounded-3 bg-light">
                            <label class="fw-semibold mb-2">3. Funeral Contract</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" class="form-control form-control-sm" accept="image/*,.pdf" id="reqUpload3" onchange="previewRequirement(this, 'reqPreview3')">
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