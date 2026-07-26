<div class="modal fade" id="newTransactionModal" tabindex="-1" aria-labelledby="newTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newTransactionModalLabel">New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newTransactionForm" action="<?php echo e(route('transactions.store')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body p-0">
                    <div class="bg-light text-white px-4 py-3 mb-3">
                        <h6 class="mb-0">Transaction Details</h6>
                    </div>
                    
                    <div class="px-4 pb-3">
                        <div class="row gx-3 align-items-center mb-3">
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Applicant ID</div>
                            <div class="col-auto flex-fill">
                                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold" name="client_id" value="<?php echo e($client->client_id ?? ''); ?>" readonly>
                            </div>
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Transaction Date</div>
                            <div class="col-auto flex-fill">
                                <input type="text" class="form-control form-control-sm border-0 bg-light fw-bold" value="<?php echo e(now()->format('m/d/Y')); ?>" readonly>
                                <input type="hidden" name="transaction_date" value="<?php echo e(now()->format('Y-m-d')); ?>">
                            </div>
                        </div>

                        <div class="row gx-3 align-items-center mb-3">
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Client</div>
                            <div class="col">
                                <input type="text" class="form-control form-control-sm border-0 bg-light text-uppercase fw-bold" name="client_name" value="<?php echo e($client->full_name ?? ''); ?>" readonly>
                            </div>
                        </div>

                        <div class="row gx-3 align-items-center mb-3">
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Transaction Category</div>
                            <div class="col">
                                <select class="form-select form-select-sm border-0 bg-light text-uppercase" name="category" id="categorySelect" required>
                                    <option value="">SELECT CATEGORY</option>
                                    <option value="social_services_assistance">SOCIAL SERVICES ASSISTANCE</option>
                                    <option value="solicitation">SOLICITATION</option>
                                    <option value="youth_sports">YOUTH & SPORTS</option>
                                    <option value="appointments">APPOINTMENTS</option>
                                    <option value="infrastructure">INFRASTRUCTURE</option>
                                    <option value="scholarships">SCHOLARSHIPS</option>
                                    <option value="permits">PERMITS</option>
                                    <option value="events">EVENTS</option>
                                    <option value="job_application">JOB APPLICATION</option>
                                    <option value="hoa">HOA</option>
                                    <option value="others">OTHERS</option>
                                </select>
                            </div>
                        </div>

                        <div class="row gx-3 align-items-center mb-3">
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Transaction Type</div>
                            <div class="col">
                                <select class="form-select form-select-sm border-0 bg-light text-uppercase" name="type" id="typeSelect" required disabled>
                                    <option value="">SELECT CATEGORY FIRST</option>
                                </select>
                            </div>
                        </div>

                        <div class="row gx-3 align-items-start mb-3">
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Description of Request</div>
                            <div class="col">
                                <textarea class="form-control form-control-sm border-0 bg-light text-uppercase" name="description" rows="4" placeholder="Enter description (optional)"></textarea>
                            </div>
                        </div>

                        <div class="row gx-3 align-items-center mb-3">
                            <div class="col-auto text-uppercase text-muted small fw-semibold">Addressed To</div>
                            <div class="col">
                                <select class="form-select form-select-sm border-0 text-uppercase" name="addressed_to" id="addressedToSelect" required>
                                    <option value="">SELECT ADDRESSED TO</option>
                                    <option value="mayor">MAYOR ALEX L. ADVINCULA</option>
                                    <option value="cong">CONG. ADRIAN JAY C. ADVINCULA</option>
                                    <option value="vice_mayor">VICE MAYOR HOMER T. SAQUILAYAN</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="continueTransactionBtn">Continue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-question-circle-fill text-primary" style="font-size: 3rem;"></i>
                </div>
                <p class="fs-5 fw-semibold mb-1">Confirm Transaction</p>
                <p class="text-muted mb-0">Are you sure you want to continue with this transaction?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" id="cancelTransactionConfirmBtn">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="submitTransactionBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('categorySelect');
    const typeSelect = document.getElementById('typeSelect');

    const socialServicesTypes = [
        { value: 'null', label: 'SELECT TRANSACTION TYPE' },
        { value: 'burial_assistance', label: 'BURIAL ASSISTANCE' },
        { value: 'educational_assistance', label: 'EDUCATIONAL ASSISTANCE' },
        { value: 'financial_balik_probinsya', label: 'FINANCIAL ASSISTANCE - BALIK PROBINSYA' },
        { value: 'financial_fire_victims', label: 'FINANCIAL ASSISTANCE - FIRE VICTIMS' },
        { value: 'medical_hospitalization', label: 'MEDICAL ASSISTANCE - CONFINEMENT/HOSPITALIZATION' },
        { value: 'medical_chemo_dialisys', label: 'MEDICAL ASSISTANCE - CHEMO/DIALYSIS' },
        { value: 'medical_regular', label: 'MEDICAL ASSISTANCE - REGULAR MEDICATION' },
        { value: 'subsistence_assistance', label: 'SUBSISTENCE ASSISTANCE' }
    ];

    const typeLabels = {
        solicitation: 'SOLICITATION',
        youth_sports: 'YOUTH & SPORTS',
        appointments: 'APPOINTMENTS',
        infrastructure: 'INFRASTRUCTURE',
        scholarships: 'SCHOLARSHIPS',
        permits: 'PERMITS',
        events: 'EVENTS',
        job_application: 'JOB APPLICATION',
        hoa: 'HOA',
        others: 'OTHERS'
    };

    categorySelect.addEventListener('change', function() {
        const category = this.value;
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
    });

    const newTxModal = document.getElementById('newTransactionModal');
    const confirmModal = document.getElementById('confirmTransactionModal');
    const continueBtn = document.getElementById('continueTransactionBtn');
    const submitBtn = document.getElementById('submitTransactionBtn');

    const newTxModalInstance = bootstrap.Modal.getOrCreateInstance(newTxModal);
    const confirmModalInstance = bootstrap.Modal.getOrCreateInstance(confirmModal);
    let submitted = false;

    continueBtn.addEventListener('click', function() {
        const form = document.getElementById('newTransactionForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        newTxModalInstance.hide();
        confirmModalInstance.show();
    });

    submitBtn.addEventListener('click', function() {
        submitted = true;
        submitBtn.disabled = true;
        confirmModalInstance.hide();
        document.getElementById('newTransactionForm').submit();
    });

    confirmModal.addEventListener('hidden.bs.modal', function() {
        if (!submitted) {
            newTxModalInstance.show();
        }
    });
});
</script><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/client_transaction/newTransaction.blade.php ENDPATH**/ ?>