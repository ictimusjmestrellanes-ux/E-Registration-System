<div class="modal fade" id="newTransactionModal" tabindex="-1" aria-labelledby="newTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newTransactionModalLabel">New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newTransactionForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Client ID</label>
                        <input type="text" class="form-control" name="client_id" value="<?php echo e($client->client_id ?? ''); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction Date</label>
                        <input type="text" class="form-control" name="transaction_date" value="<?php echo e(now()->format('m/d/Y')); ?>" readonly>
                        <input type="hidden" name="transaction_date_hidden" value="<?php echo e(now()->format('Y-m-d')); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction Category</label>
                        <select class="form-select" name="category" id="categorySelect" required>
                            <option value="">SELECT CATEGORY</option>
                            <option value="social_services">SOCIAL SERVICES ASSISTANCE</option>
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
                    <div class="mb-3">
                        <label class="form-label">Transaction Type</label>
                        <select class="form-select" name="type" id="typeSelect" required disabled>
                            <option value="">SELECT CATEGORY FIRST</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </form>
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

        if (category === 'social_services') {
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

    document.getElementById('newTransactionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        console.log('Transaction data:', data);
        // Add your submit logic here (AJAX call, redirect, etc.)
        alert('Transaction submitted: ' + JSON.stringify(data, null, 2));
    });
});
</script><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/newTransaction.blade.php ENDPATH**/ ?>