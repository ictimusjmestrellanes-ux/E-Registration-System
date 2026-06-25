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

                        <div class="border rounded-4 p-3 mb-4 bg-light-subtle">
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
                                                <div class="fw-semibold"><?php echo e(strtoupper($client->sector ?: '-')); ?></div>
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
                                                    <img src="<?php echo e($clientFingerprint); ?>" alt="Client Fingerprint"
                                                        class="avatar-sm rounded-3 border object-fit-cover bg-white"
                                                        onerror="this.onerror=null;this.src='<?php echo e($defaultFingerprint); ?>';">
                                                    <span><?php echo e(strtoupper($fingerprintStatus ?: '-')); ?></span>
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
                                        <p class="text-muted mb-0 small">Latest recorded activity for this client.
                                        </p>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th>Transaction ID</th>
                                                <th>Transaction Date</th>
                                                <th>Source</th>
                                                <th>Type</th>
                                                <th>Clerk</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-center">
                                            <?php $__empty_1 = true; $__currentLoopData = $transactionLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <?php
                                                    $transactionType = \Illuminate\Support\Str::headline($log->action);
                                                    $transactionStatus = in_array(
                                                        $log->action,
                                                        ['client_deleted', 'client_archived'],
                                                        true,
                                                    )
                                                        ? 'Archived'
                                                        : 'Completed';
                                                    $statusClass =
                                                        $transactionStatus === 'Archived'
                                                            ? 'bg-warning-subtle text-warning'
                                                            : 'bg-success-subtle text-success';
                                                ?>
                                                <tr>
                                                    <td>TXN-<?php echo e(str_pad((string) $log->id, 6, '0', STR_PAD_LEFT)); ?>

                                                    </td>
                                                    <td><?php echo e(optional($log->created_at)->format('m/d/Y') ?? '-'); ?>

                                                    </td>
                                                    <td>E-Registration</td>
                                                    <td><?php echo e($transactionType); ?></td>
                                                    <td><?php echo e($log->user?->name ?? 'System'); ?></td>
                                                    <td>
                                                        <span
                                                            class="badge <?php echo e($statusClass); ?>"><?php echo e($transactionStatus); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        No transaction history available for this client.
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
    </div>

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

    <?php echo $__env->make('pages.newTransaction', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

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
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verifyModalEl = document.getElementById('verifyFingerprintModal');
            const verifyPreview = document.getElementById('verifyFingerprintPreview');
            const verifyStatus = document.getElementById('verifyFingerprintStatus');
            const verifyScanAgainBtn = document.getElementById('verifyFingerprintScanAgainBtn');
            const createdModalEl = document.getElementById('clientCreatedModal');
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

                    verifyPreview.src = captureResult.imageDataUrl || fingerprintPlaceholder;
                    if (!templateXml) {
                        throw new Error('The scanner did not return a fingerprint template.');
                    }

                    setVerifyStatus('Checking fingerprint against this client...', 'info');
                    const searchResult = await postJson(fingerprintSearchUrl, {
                        fingerprint_template: templateXml
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

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views\pages\clientShow.blade.php ENDPATH**/ ?>