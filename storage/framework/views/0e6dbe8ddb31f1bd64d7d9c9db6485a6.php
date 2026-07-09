<?php $__env->startSection('title', 'Client Details'); ?>
<?php $__env->startSection('content'); ?>
    <?php
        $clientPhoto = $client->photo_url;
        $clientFingerprint = $client->fingerprint_url;
        $accountNumber = sprintf('%08d', $client->id);
        $registrationDate = optional($client->created_at)->format('m/d/Y') ?? 'N/A';
        $birthDate = optional($client->birth_date)->format('m/d/Y') ?? 'N/A';
        $age = filled($client->age) ? $client->age . ' Yrs.' : 'N/A';
        $nameExtension = $client->suffix ?: 'N/A';
        $street = $client->address ?: 'N/A';
        $subdivision = 'N/A';
        $barangay = $client->barangay ?: 'N/A';
        $municipality = $client->city ?: 'N/A';
        $province = $client->province ?: 'N/A';
        $gender = $client->gender ?: 'N/A';
        $civilStatus = $client->civil_status ?: 'N/A';
        $education = $client->education ?: 'N/A';
        $course = $client->course ?: 'N/A';
        $sector = $client->sector ?: 'N/A';
        $position = $client->position_organization ?: 'N/A';
        $contactInfo = $client->contact ?: ($client->contact_2 ?: 'N/A');
    ?>

    <style>
        .client-show-page {
            background:
                radial-gradient(circle at top left, rgba(35, 58, 136, 0.10), transparent 30%),
                radial-gradient(circle at top right, rgba(17, 102, 48, 0.08), transparent 26%),
                linear-gradient(180deg, #f3f5f8 0%, #eef1f6 100%);
            min-height: 100%;
        }

        .client-sheet {
            border: 1px solid #1f2430;
            background: #f7f5ef;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.12);
            max-width: 1600px;
            margin: 0 auto;
        }

        .sheet-header {
            padding: 0.85rem 1rem;
            background: linear-gradient(90deg, #17122f 0%, #22244f 45%, #141826 100%);
            color: #fff;
            border-bottom: 1px solid #1f2430;
        }

        .sheet-kicker {
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .sheet-title {
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .sheet-body {
            padding: 0.85rem;
        }

        .stack-panel {
            border: 1px solid #9ea6b6;
            background: #fdfcf7;
            overflow: hidden;
        }

        .panel-title {
            padding: 0.34rem 0.7rem;
            background: linear-gradient(90deg, #202533 0%, #30384a 100%);
            color: #fff;
            font-size: 0.88rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: capitalize;
        }

        .photo-frame {
            background: #101828;
            padding: 0.55rem;
        }

        .photo-frame img {
            width: 100%;
            height: 228px;
            object-fit: cover;
            border: 1px solid #d8dbe2;
            background: #fff;
        }

        .account-box {
            padding: 0.55rem 0.65rem 0.75rem;
        }

        .account-row {
            margin-bottom: 0.65rem;
        }

        .account-label {
            color: #4b5563;
            font-size: 0.76rem;
            margin-bottom: 0.2rem;
            font-weight: 600;
            text-transform: none;
        }

        .account-value {
            min-height: 2rem;
            display: flex;
            align-items: center;
            padding: 0.3rem 0.55rem;
            background: #e8ecf5;
            border: 1px solid #9fa8ba;
            color: #111827;
            font-weight: 700;
        }

        .account-value.accent {
            background: #213b8c;
            color: #f6c744;
            justify-content: center;
            letter-spacing: 0.02em;
        }

        .info-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .info-table th,
        .info-table td {
            border: 1px solid #bcc3d0;
            padding: 0.35rem 0.55rem;
            font-size: 0.84rem;
            vertical-align: middle;
        }

        .info-table th {
            width: 14%;
            background: #edf0f6;
            color: #2f3744;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }

        .info-table td {
            background: #fdfcf7;
            color: #111827;
        }

        .info-table .value-cell {
            font-weight: 700;
        }

        .transaction-table {
            margin-bottom: 0;
        }

        .transaction-table thead th {
            background: #edf0f6;
            color: #1f2937;
            font-size: 0.8rem;
            font-weight: 700;
            border-color: #c4cbda;
            white-space: nowrap;
        }

        .transaction-table td {
            font-size: 0.82rem;
            vertical-align: middle;
        }

        .transaction-table tbody tr:nth-child(even) {
            background: #fafbfe;
        }

        .soft-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 5.25rem;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            background: #d9f2e0;
            color: #106230;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .section-gap {
            margin-top: 0.75rem;
        }

        @media (max-width: 991.98px) {
            .info-table th,
            .info-table td {
                display: block;
                width: 100%;
            }

            .info-table tr {
                display: block;
                margin-bottom: 0.6rem;
            }

            .info-table th {
                border-bottom: 0;
            }
        }

        @media (max-width: 575.98px) {
            .sheet-body {
                padding: 0.65rem;
            }

            .photo-frame img {
                height: 200px;
            }

            .account-box {
                padding: 0.5rem;
            }
        }
    </style>

    <div class="container-fluid client-show-page py-3">
        <div class="client-sheet">
            <div class="sheet-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <div class="sheet-kicker">Fingerprint Search Result</div>
                    <h4 class="sheet-title mb-0">Client Details</h4>
                </div>
                <a href="<?php echo e(route('client.list')); ?>" class="btn btn-sm btn-light border">Back to List</a>
            </div>

            <div class="sheet-body">
                <div class="row g-3">
                    <div class="col-xl-3 col-lg-4">
                        <div class="stack-panel">
                            <div class="panel-title">Photo</div>
                            <div class="photo-frame">
                                <img src="<?php echo e($clientPhoto); ?>" alt="Client Photo">
                            </div>
                        </div>

                        <div class="stack-panel section-gap">
                            <div class="panel-title">Account Information</div>
                            <div class="account-box">
                                <div class="account-row">
                                    <div class="account-label">Applicant ID</div>
                                    <div class="account-value accent"><?php echo e($accountNumber); ?></div>
                                </div>
                                <div class="account-row mb-0">
                                    <div class="account-label">Date of Registration</div>
                                    <div class="account-value"><?php echo e($registrationDate); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-9 col-lg-8">
                        <div class="stack-panel h-100">
                            <div class="panel-title">Personal Information</div>
                            <div class="table-responsive">
                                <table class="info-table">
                                    <tbody>
                                        <tr>
                                            <th>First Name</th>
                                            <td class="value-cell"><?php echo e($client->first_name ?: 'N/A'); ?></td>
                                            <th>Civil Status</th>
                                            <td class="value-cell"><?php echo e($civilStatus); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Middle Name</th>
                                            <td class="value-cell"><?php echo e($client->middle_name ?: 'N/A'); ?></td>
                                            <th>Education</th>
                                            <td class="value-cell"><?php echo e($education); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Last Name</th>
                                            <td class="value-cell"><?php echo e($client->last_name ?: 'N/A'); ?></td>
                                            <th>Course</th>
                                            <td class="value-cell"><?php echo e($course); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Name Ext.</th>
                                            <td class="value-cell"><?php echo e($nameExtension); ?></td>
                                            <th>Sector</th>
                                            <td class="value-cell"><?php echo e($sector); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Gender</th>
                                            <td class="value-cell"><?php echo e($gender); ?></td>
                                            <th>Position</th>
                                            <td class="value-cell"><?php echo e($position); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Age</th>
                                            <td class="value-cell"><?php echo e($age); ?></td>
                                            <th>Contact Info</th>
                                            <td class="value-cell"><?php echo e($contactInfo); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Birthday</th>
                                            <td class="value-cell"><?php echo e($birthDate); ?></td>
                                            <th>Street</th>
                                            <td class="value-cell"><?php echo e($street); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Birthplace</th>
                                            <td class="value-cell"><?php echo e($client->birthplace ?: 'N/A'); ?></td>
                                            <th>Subdivision</th>
                                            <td class="value-cell"><?php echo e($subdivision); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Barangay</th>
                                            <td class="value-cell"><?php echo e($barangay); ?></td>
                                            <th>Municipality</th>
                                            <td class="value-cell"><?php echo e($municipality); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Province</th>
                                            <td class="value-cell"><?php echo e($province); ?></td>
                                            <th>Fingerprint</th>
                                            <td class="value-cell">
                                                <span class="d-inline-flex align-items-center gap-2">
                                                    <img src="<?php echo e($clientFingerprint); ?>" alt="Client Fingerprint" style="width: 44px; height: 26px; object-fit: cover; border: 1px solid #c1c7d4; background: #fff; flex: 0 0 auto;">
                                                    <span>Registered</span>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stack-panel section-gap">
                    <div class="panel-title">Transaction History</div>
                    <div class="table-responsive">
                        <table class="table transaction-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Transaction Date</th>
                                    <th>Source</th>
                                    <th>Type</th>
                                    <th>Clerk</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__empty_1 = true; $__currentLoopData = $transactionLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php
                                        $transactionType = \Illuminate\Support\Str::headline($log->action);
                                        $transactionStatus = in_array($log->action, ['client_deleted', 'client_archived'], true)
                                            ? 'Archived'
                                            : 'Completed';
                                    ?>
                                    <tr>
                                        <td>TXN-<?php echo e(str_pad((string) $log->id, 6, '0', STR_PAD_LEFT)); ?></td>
                                        <td><?php echo e(optional($log->created_at)->format('m/d/Y') ?? 'N/A'); ?></td>
                                        <td>E-Registration</td>
                                        <td><?php echo e($transactionType); ?></td>
                                        <td><?php echo e($log->user?->name ?? 'System'); ?></td>
                                        <td>
                                            <span class="soft-badge"><?php echo e($transactionStatus); ?></span>
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/pages/clientShow.blade.php ENDPATH**/ ?>