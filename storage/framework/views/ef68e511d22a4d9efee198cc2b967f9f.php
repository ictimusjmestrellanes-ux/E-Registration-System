<?php $__env->startSection('title', 'Client Details'); ?>
<?php $__env->startSection('content'); ?>
    <?php
        $defaultClientPhoto = asset('assets/images/profile.png');
        $defaultFingerprint = asset('assets/images/fingerprint.png');
        $clientPhoto = $client->photo_url ?: $defaultClientPhoto;
        $clientFingerprint = $client->fingerprint_url ?: $defaultFingerprint;
        $fullName = strtoupper($client->full_name) ?: 'Client';
        $registrationDate = strtoupper(optional($client->created_at)->format('m/d/Y') ?? '-');
        $birthDate = strtoupper(optional($client->birth_date)->format('m/d/Y') ?? '-');
        $age = filled($client->age) ? strtoupper($client->age . ' yrs. old') : '-';
        $location =
            collect([$client->address, $client->barangay, $client->city, $client->province])
                ->filter()
                ->implode(', ') ?:
            '-';
        $hasFingerprint = filled($client->fingerprint_path) || filled($client->fingerprint_template);
        $fingerprintStatus = $hasFingerprint ? 'Registered' : 'Not registered';
    ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client Details</h4>
                                <p class="text-muted mb-0">View full profile and transaction history for <?php echo e($fullName); ?>.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <button type="button" class="btn btn-primary flex-fill text-uppercase" data-bs-toggle="modal"
                                data-bs-target="#newTransactionModal">New Transaction</button>

                            <a href="<?php echo e(route('clients.edit', $client)); ?>"
                                class="btn btn-primary flex-fill text-uppercase">Update Client Information</a>

                            <a href="#clientTransactionHistory" class="btn btn-primary flex-fill text-uppercase">View
                                Transaction Information</a>

                            <button type="button" class="btn btn-primary flex-fill text-uppercase" disabled>Cancel
                                Transaction</button>

                            <button type="button" class="btn btn-primary flex-fill text-uppercase" data-bs-toggle="modal"
                                data-bs-target="#verifyFingerprintModal" <?php if(!$hasFingerprint): echo 'disabled'; endif; ?>>Verify Client
                                Fingerprint</button>

                            <form action="<?php echo e(route('clients.archive', $client)); ?>" method="POST"
                                class="m-0 d-inline-flex flex-fill"
                                onsubmit="return confirm('Deactivate this client and move the record to archive?');">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-primary w-100 text-uppercase">Deactivate
                                    Client</button>
                            </form>

                            <button type="button" class="btn btn-primary flex-fill text-uppercase" disabled>Merge
                                Account</button>

                            <a href="<?php echo e(route('client.list')); ?>" class="btn btn-primary flex-fill text-uppercase">Back to
                                List</a>
                        </div>

                        <div class="border rounded-4 p-3 mb-2 bg-light-subtle">
                            <div class="row g-4 align-items-start">
                                <div class="col-12 col-lg-4 text-center">
                                    <img src="<?php echo e($clientPhoto); ?>" alt="Client Photo"
                                        onerror="this.onerror=null;this.src='<?php echo e($defaultClientPhoto); ?>';"
                                        class="img-fluid border bg-light"
                                        style="width: 320px; height: 320px; object-fit: cover;">
                                </div>

                                <div class="col-12 col-lg-8">
                                    <div class="d-flex flex-column gap-3">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">Full Name
                                                </div>
                                                <div class="fs-4 fw-bold"><?php echo e($fullName); ?></div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">Client ID
                                                </div>
                                                <div class="fs-4 fw-bold"><?php echo e($client->client_id ?? '-'); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Date
                                                    Registered</div>
                                                <div class="fs-4 fw-bold"><?php echo e($registrationDate); ?></div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Birth
                                                    Date</div>
                                                <div class="fw-semibold"><?php echo e($birthDate); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Age</div>
                                                <div class="fw-semibold"><?php echo e($age); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Birthplace</div>
                                                <div class="fw-semibold"><?php echo e(strtoupper($client->birthplace ?: '-')); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Civil
                                                    Status</div>
                                                <div class="fw-semibold"><?php echo e(strtoupper($client->civil_status ?: '-')); ?>

                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Gender
                                                </div>
                                                <div class="fw-semibold"><?php echo e(strtoupper($client->gender ?: '-')); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Email
                                                </div>
                                                <div class="fw-semibold text-break"><?php echo e(strtoupper($client->email ?: '-')); ?>

                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Contact 1
                                                </div>
                                                <div class="fw-semibold"><?php echo e($client->contact ?: '-'); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Contact 2
                                                </div>
                                                <div class="fw-semibold"><?php echo e($client->contact_2 ?: '-'); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Education
                                                </div>
                                                <div class="fw-semibold"><?php echo e(strtoupper($client->education ?: '-')); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Course
                                                </div>
                                                <div class="fw-semibold"><?php echo e(strtoupper($client->course ?: '-')); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Sector
                                                </div>
                                                <div class="fw-semibold"><?php echo e($client->sector ? strtoupper(str_replace(',', ', ', $client->sector)) : '-'); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Position
                                                    / Organization</div>
                                                <div class="fw-semibold">
                                                    <?php echo e(strtoupper($client->position_organization ?: '-')); ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Address
                                                </div>
                                                <div class="fw-semibold"><?php echo e(strtoupper($location ?: '-')); ?></div>
                                            </div>

                                            <div class="col-4">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Fingerprint</div>
                                                <div class="d-inline-flex align-items-center gap-2 fw-semibold">
                                                    
                                                    <span><?php echo e(strtoupper($fingerprintStatus ?: '-')); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-4 p-3 bg-light-subtle" id="clientTransactionHistory">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Transaction History</h5>
                                    <p class="text-muted mb-0 small">Latest transactions for this client.</p>
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light text-center" style="position: sticky; top: 0; z-index: 1; background: #f8f9fa;">
                                        <tr>
                                            <th>Transaction ID</th>
                                            <th>Transaction Date</th>
                                            <th>Source</th>
                                            <th>Category Type</th>
                                            <th>Clerk</th>
                                            <th>Status</th>
                                            <th>Description of Request</th>
                                            <th>Actions Taken</th>
                                            <th>Remarks</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                            <tr class="transaction-row" style="cursor: pointer;" data-transaction-id="<?php echo e($transaction->id); ?>">
                                                <td><?php echo e($transaction->transaction_id); ?></td>
                                                <td><?php echo e($transaction->transaction_date->format('m/d/Y')); ?></td>
                                                <td class="text-uppercase">E-Registration</td>
                                                <td class="text-uppercase"><?php echo e($transaction->category_label); ?></td>
                                                <td class="text-uppercase"><?php echo e($transaction->clerk ?? auth()->user()->name ?? 'System'); ?></td>
                                                <td>
                                                    <?php
                                                        $txStatus = $transaction->status ?? 'Completed';
                                                    ?>
                                                    <?php if(strtolower($txStatus) === 'pending'): ?>
                                                        <span class="badge bg-warning-subtle text-warning"><?php echo e($txStatus); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success"><?php echo e($txStatus); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo e($transaction->description ?? 'N/A'); ?></td>
                                                <td><?php echo e($transaction->actions_taken ?? 'N/A'); ?></td>
                                                <td><?php echo e($transaction->remarks ?? 'N/A'); ?></td>
                                                <td><?php echo e($transaction->amount > 0 ? '₱' . number_format($transaction->amount, 2) : 'PHP 0.00'); ?></td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">
                                                    No transactions recorded for this client.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php echo $__env->make('pages.client_transaction.transactionInfo', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="modal fade" id="verifyFingerprintModal" tabindex="-1" aria-labelledby="verifyFingerprintModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verifyFingerprintModalLabel">Verify Client Fingerprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="alert alert-info mb-3" role="alert" id="verifyFingerprintStatus">
                            Ready to verify fingerprint.
                        </div>
                        <div class="text-center">
                            <img id="verifyFingerprintPreview" src="<?php echo e($defaultFingerprint); ?>"
                                alt="Fingerprint Verification Preview"
                                class="img-fluid rounded-3 border object-fit-cover bg-white">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="verifyFingerprintScanAgainBtn">Scan
                        Again</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php echo $__env->make('pages.client_transaction.newTransaction', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if(session('show_created_modal')): ?>
        <div class="modal fade" id="clientCreatedModal" tabindex="-1" aria-labelledby="clientCreatedModalLabel"
            aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title" id="clientCreatedModalLabel">Client Saved</h5>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <p class="fs-5 fw-semibold mb-1"><?php echo e($fullName); ?> has been saved successfully.</p>
                        <p class="text-muted mb-0">Would you like to add another client?</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                        <a href="<?php echo e(route('clients')); ?>" class="btn btn-primary px-4">Continue</a>
                        <button type="button" class="btn btn-outline-secondary px-4"
                            data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <style>
        .transaction-row:hover {
            background-color: rgba(0, 0, 0, 0.075) !important;
        }
    </style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verifyModalEl = document.getElementById('verifyFingerprintModal');
            const verifyPreview = document.getElementById('verifyFingerprintPreview');
            const verifyStatus = document.getElementById('verifyFingerprintStatus');
            const verifyScanAgainBtn = document.getElementById('verifyFingerprintScanAgainBtn');
            const createdModalEl = document.getElementById('clientCreatedModal');

            window.previewRequirement = function(input, previewId) {
                const preview = document.getElementById(previewId);
                const file = input.files[0];
                if (!file) {
                    preview.classList.add('d-none');
                    return;
                }
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.classList.add('d-none');
                }
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const currentClientId = Number(<?php echo json_encode($client->id, 15, 512) ?>);
            const currentClientName = <?php echo json_encode($fullName, 15, 512) ?>;
            const fingerprintPlaceholder = <?php echo json_encode($defaultFingerprint, 15, 512) ?>;
            const fingerprintCaptureUrl = <?php echo json_encode(route('fingerprint.capture'), 15, 512) ?>;
            const fingerprintSearchUrl = <?php echo json_encode(route('client.search.fingerprint'), 15, 512) ?>;

            if (createdModalEl) {
                const createdModal = bootstrap.Modal.getOrCreateInstance(createdModalEl);
                createdModal.show();
            }

            // Global variable to track current transaction ID for modal
            let currentTransactionId = null;

            const transactionInfoModalEl = document.getElementById('transactionInfoModal');
            const showTransactionId = <?php echo json_encode(session('show_transaction'), 15, 512) ?>;

            // Only auto-show if there's a new transaction from session flash
            if (transactionInfoModalEl && showTransactionId) {
                loadTransactionData(showTransactionId);

                // Clean up URL
                const url = new URL(window.location.href);
                if (url.searchParams.has('show_transaction')) {
                    url.searchParams.delete('show_transaction');
                    window.history.replaceState({}, '', url.toString());
                }
            }

            // Function to populate modal with transaction data
            async function loadTransactionData(transactionId) {
                try {
                    const response = await fetch(`<?php echo e(route('transactions.show', ['id' => 'REPLACE'])); ?>`.replace('REPLACE', transactionId));
                    if (!response.ok) {
                        throw new Error('Failed to load transaction');
                    }
                    const data = await response.json();

                    // Update modal with fetched data
                    document.getElementById('modalTransactionId').textContent = data.transaction_id;
                    document.getElementById('modalTransactionDate').textContent = data.transaction_date;
                    document.getElementById('modalTransactionSource').textContent = data.source;
                    document.getElementById('modalTransactionCategory').textContent = data.category;
                    document.getElementById('modalTransactionType').textContent = data.type;
                    document.getElementById('modalTransactionClerk').textContent = data.clerk;
                    const statusEl = document.getElementById('modalTransactionStatus');
                    statusEl.textContent = data.status || '-';
                    // Reset classes and apply badge color: yellow for Pending, green otherwise
                    statusEl.className = 'fw-bold';
                    if (String(data.status || '').toLowerCase() === 'pending') {
                        statusEl.classList.add('badge', 'bg-warning-subtle', 'text-warning');
                    } else {
                        statusEl.classList.add('badge', 'bg-success-subtle', 'text-success');
                    }
                    document.getElementById('modalTransactionDescription').value = data.description;
                    document.getElementById('modalTransactionActionsTaken').value = data.actions_taken;
                    document.getElementById('modalTransactionRemarks').value = data.remarks;

                    // Clear file inputs and previews
                    document.getElementById('reqUpload1').value = '';
                    document.getElementById('reqUpload2').value = '';
                    document.getElementById('reqUpload3').value = '';
                    document.getElementById('reqPreview1').classList.add('d-none');
                    document.getElementById('reqPreview2').classList.add('d-none');
                    document.getElementById('reqPreview3').classList.add('d-none');

                        // Load any saved requirement files for this transaction
                        try {
                            const reqResp = await fetch(`<?php echo e(route('transaction-requirements.show', ['transactionId' => 'REPLACE'])); ?>`.replace('REPLACE', transactionId));
                            if (reqResp.ok) {
                                const json = await reqResp.json();
                                if (json.success && Array.isArray(json.data)) {
                                    json.data.forEach(item => {
                                        // Map requirement_type to preview element
                                        let previewId = null;
                                        let previewBtnId = null;
                                        if (item.requirement_type === 'valid_id') { previewId = 'reqPreview1'; previewBtnId = 'reqPreviewBtn1'; }
                                        if (item.requirement_type === 'death_certificate') { previewId = 'reqPreview2'; previewBtnId = 'reqPreviewBtn2'; }
                                        if (item.requirement_type === 'funeral_contract') { previewId = 'reqPreview3'; previewBtnId = 'reqPreviewBtn3'; }
                                        if (previewId) {
                                            const imgEl = document.getElementById(previewId);
                                            if (imgEl) {
                                                imgEl.src = item.file_path || ('/storage/' + (item.file_path || ''));
                                                imgEl.classList.remove('d-none');
                                            }
                                        }
                                        if (previewBtnId) {
                                            const btn = document.getElementById(previewBtnId);
                                            if (btn) {
                                                btn.dataset.requirementId = item.id;
                                            }
                                        }
                                    });
                                }
                            }
                        } catch (err) {
                            console.error('Failed to load transaction requirements', err);
                        }

                    // Store transaction ID for confirm button
                    currentTransactionId = data.id;

                    // Show modal
                    const modal = bootstrap.Modal.getOrCreateInstance(transactionInfoModalEl);
                    modal.show();
                } catch (error) {
                    alert('Error loading transaction: ' + error.message);
                }
            }

            // Add click handlers to transaction rows
            document.querySelectorAll('.transaction-row').forEach(row => {
                row.addEventListener('click', function() {
                    const transactionId = this.getAttribute('data-transaction-id');
                    loadTransactionData(transactionId);
                });
            });

            // Handle transaction requirement confirm button
            const confirmBtn = document.getElementById('transactionInfoConfirmBtn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', async function() {
                    if (!currentTransactionId) {
                        alert('No transaction selected');
                        return;
                    }

                    const requirements = [
                        { id: 'reqUpload1', type: 'valid_id' },
                        { id: 'reqUpload2', type: 'death_certificate' },
                        { id: 'reqUpload3', type: 'funeral_contract' }
                    ];

                    try {
                        confirmBtn.disabled = true;
                        confirmBtn.textContent = 'Uploading...';

                        for (const req of requirements) {
                            const fileInput = document.getElementById(req.id);
                            if (fileInput && fileInput.files.length > 0) {
                                const formData = new FormData();
                                formData.append('transaction_id', currentTransactionId);
                                formData.append('requirement_type', req.type);
                                formData.append('file', fileInput.files[0]);

                                const response = await fetch(<?php echo json_encode(route('transaction-requirements.store'), 15, 512) ?>, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
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

                        // Close the modal and show success
                        bootstrap.Modal.getInstance(transactionInfoModalEl)?.hide();
                        alert('Requirements uploaded successfully!');

                    } catch (error) {
                        alert('Error uploading files: ' + error.message);
                    } finally {
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = 'Confirm';
                    }
                });
            }

            if (!verifyModalEl || !verifyPreview || !verifyStatus || !verifyScanAgainBtn) {
                return;
            }

            const setVerifyStatus = (message, type = 'info') => {
                verifyStatus.className = `alert alert-${type} mb-3`;
                verifyStatus.textContent = message;
            };

            const postJson = async (url, body = {}) => {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(body)
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Request failed.');
                }

                return payload;
            };

            const verifyClientFingerprint = async () => {
                verifyScanAgainBtn.disabled = true;
                verifyPreview.src = fingerprintPlaceholder;
                setVerifyStatus('Place your finger on the scanner...', 'info');

                try {
                    const captureResult = await postJson(fingerprintCaptureUrl, {
                        source: 'laravel'
                    });
                    const templateXml = captureResult.fingerprintTemplateXml || '';
                    const fingerprintImageData = captureResult.imageDataUrl || '';

                    verifyPreview.src = fingerprintImageData || fingerprintPlaceholder;
                    if (!templateXml && !fingerprintImageData) {
                        throw new Error('The scanner did not return a fingerprint capture.');
                    }

                    setVerifyStatus('Checking fingerprint against this client...', 'info');
                    const searchResult = await postJson(fingerprintSearchUrl, {
                        fingerprint_template: templateXml,
                        fingerprint_data: fingerprintImageData
                    });

                    if (searchResult.matched && Number(searchResult.client?.id) === currentClientId) {
                        setVerifyStatus(`Fingerprint verified for ${currentClientName}.`, 'success');
                        return;
                    }

                    if (searchResult.matched) {
                        setVerifyStatus(
                            `Fingerprint matched another client: ${searchResult.client?.name || 'Unknown client'}.`,
                            'danger'
                        );
                        return;
                    }

                    setVerifyStatus(searchResult.message || 'No matching client found.', 'warning');
                } catch (error) {
                    setVerifyStatus(error.message || 'Fingerprint verification failed.', 'danger');
                } finally {
                    verifyScanAgainBtn.disabled = false;
                }
            };

            verifyModalEl.addEventListener('shown.bs.modal', verifyClientFingerprint);
            verifyScanAgainBtn.addEventListener('click', verifyClientFingerprint);
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/clients/clientShow.blade.php ENDPATH**/ ?>