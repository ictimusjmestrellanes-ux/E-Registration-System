@extends('layouts.master')
@section('title', 'ERS | Client Details')

@push('styles')
    <style>
        .transaction-row {
            cursor: pointer;
        }

        .transaction-row:hover {
            background-color: rgba(0, 0, 0, 0.075) !important;
        }

        .transaction-row.transaction-row-disabled {
            cursor: default;
        }

        .transaction-row.transaction-row-disabled:hover {
            background-color: transparent !important;
        }
    </style>
@endpush

@section('content')
    @php
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
    @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client Details</h4>
                                <p class="text-muted mb-0">View full profile and transaction history for {{ $fullName }}.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            @unless (auth()->user()?->role_name === 'Viewer')
                                <button type="button" class="btn btn-primary flex-fill text-uppercase" data-bs-toggle="modal"
                                    data-bs-target="#newTransactionModal">New Transaction</button>

                                <a href="{{ route('clients.edit', $client) }}"
                                    class="btn btn-primary flex-fill text-uppercase">Update Client Information</a>
                            @endunless

                            <a href="#clientTransactionHistory" class="btn btn-primary flex-fill text-uppercase">View
                                Transaction Information</a>

                            <button type="button" class="btn btn-primary flex-fill text-uppercase" disabled>Cancel
                                Transaction</button>

                            @unless (auth()->user()?->role_name === 'Viewer')
                                <button type="button" class="btn btn-primary flex-fill text-uppercase" data-bs-toggle="modal"
                                    data-bs-target="#verifyFingerprintModal" @disabled(!$hasFingerprint)>Verify Client
                                    Fingerprint</button>

                                <form action="{{ route('clients.archive', $client) }}" method="POST"
                                    class="m-0 d-inline-flex flex-fill"
                                    onsubmit="return confirm('Deactivate this client and move the record to archive?');">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100 text-uppercase">Deactivate
                                        Client</button>
                                </form>

                                <button type="button" class="btn btn-primary flex-fill text-uppercase" disabled>Merge
                                    Account</button>
                            @endunless

                            <a href="{{ route('client.list') }}" class="btn btn-primary flex-fill text-uppercase">Back to
                                List</a>
                        </div>

                        <div class="border rounded-4 p-3 mb-2 bg-light-subtle">
                            <div class="row g-4 align-items-start">
                                <div class="col-12 col-lg-4 text-center">
                                    <img src="{{ $clientPhoto }}" alt="Client Photo"
                                        onerror="this.onerror=null;this.src='{{ $defaultClientPhoto }}';"
                                        class="img-fluid border bg-light"
                                        style="width: 320px; height: 320px; object-fit: cover;">
                                </div>

                                <div class="col-12 col-lg-8">
                                    <div class="d-flex flex-column gap-3">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">Full Name
                                                </div>
                                                <div class="fs-4 fw-bold">{{ $fullName }}</div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">Client ID
                                                </div>
                                                <div class="fs-4 fw-bold">{{ $client->client_id ?? '-' }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Date
                                                    Registered</div>
                                                <div class="fs-4 fw-bold">{{ $registrationDate }}</div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Birth
                                                    Date</div>
                                                <div class="fw-semibold">{{ $birthDate }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Age</div>
                                                <div class="fw-semibold">{{ $age }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Birthplace</div>
                                                <div class="fw-semibold">{{ strtoupper($client->birthplace ?: '-') }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Civil
                                                    Status</div>
                                                <div class="fw-semibold">{{ strtoupper($client->civil_status ?: '-') }}
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Gender
                                                </div>
                                                <div class="fw-semibold">{{ strtoupper($client->gender ?: '-') }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Email
                                                </div>
                                                <div class="fw-semibold text-break">{{ strtoupper($client->email ?: '-') }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Contact 1
                                                </div>
                                                <div class="fw-semibold">{{ $client->contact ?: '-' }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Contact 2
                                                </div>
                                                <div class="fw-semibold">{{ $client->contact_2 ?: '-' }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Education
                                                </div>
                                                <div class="fw-semibold">{{ strtoupper($client->education ?: '-') }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Course
                                                </div>
                                                <div class="fw-semibold">{{ strtoupper($client->course ?: '-') }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Sector
                                                </div>
                                                <div class="fw-semibold">{{ $client->sector ? strtoupper(str_replace(',', ', ', $client->sector)) : '-' }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Position
                                                    / Organization</div>
                                                <div class="fw-semibold">
                                                    {{ strtoupper($client->position_organization ?: '-') }}</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Address
                                                </div>
                                                <div class="fw-semibold">{{ strtoupper($location ?: '-') }}</div>
                                            </div>

                                            <div class="col-4">
                                                <div class="text-muted small text-uppercase fw-semibold">
                                                    Fingerprint</div>
                                                <div class="d-inline-flex align-items-center gap-2 fw-semibold">
                                                    {{-- <img src="{{ $clientFingerprint }}" alt="Client Fingerprint"
                                                        class="avatar-sm rounded-3 border object-fit-cover bg-white"
                                                        onerror="this.onerror=null;this.src='{{ $defaultFingerprint }}';"> --}}
                                                    <span>{{ strtoupper($fingerprintStatus ?: '-') }}</span>
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
                                    <p class="text-muted mb-0 small">Latest transactions for this client. Select a
                                        transaction to view its process.</p>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                                    <form method="GET" class="d-flex align-items-center gap-2">
                                        @if (request()->query('show_transaction'))
                                            <input type="hidden" name="show_transaction" value="{{ request()->query('show_transaction') }}">
                                        @endif
                                        <label for="transactionPerPage" class="text-muted small fw-semibold mb-0">Per page</label>
                                        <select id="transactionPerPage" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                            @foreach ([5, 10, 15, 20, 25] as $option)
                                                <option value="{{ $option }}" {{ (string) $perPage === (string) $option ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-soft-primary btn-sm"
                                        id="txHistoryColumnsBtn"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        aria-expanded="false" title="Manage Columns">
                                        <i class="ri-layout-column-line me-1"></i> Manage Columns
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 230px;">
                                        <h6 class="dropdown-header px-0">Manage Columns</h6>
                                        @foreach ([
                                            'transaction_id' => 'Transaction ID',
                                            'transaction_date' => 'Transaction Date',
                                            'source' => 'Source',
                                            'category_type' => 'Category Type',
                                            'clerk' => 'Clerk',
                                            'client_category' => 'Client Category',
                                            'transaction_type' => 'Transaction Type',
                                            'status' => 'Status',
                                            'actions_taken' => 'Actions Taken',
                                            'remarks' => 'Remarks',
                                            'amount' => 'Amount',
                                        ] as $key => $label)
                                            <div class="form-check">
                                                <input class="form-check-input tx-column-toggle" type="checkbox"
                                                    id="txcol-{{ $key }}" value="{{ $key }}" checked>
                                                <label class="form-check-label" for="txcol-{{ $key }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                        <hr class="dropdown-divider my-2">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-light flex-fill"
                                                id="resetTxColumnsBtn">Reset</button>
                                            <button type="button" class="btn btn-primary btn-sm flex-fill"
                                                id="applyTxColumnsBtn">Apply</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                                <table class="table table-bordered table-hover align-middle mb-0" id="clientTxHistoryTable">
                                    <thead class="table-light text-center" style="position: sticky; top: 0; background: #f8f9fa;">
                                        <tr>
                                            <th data-column="transaction_id">Transaction ID</th>
                                            <th data-column="transaction_date">Transaction Date</th>
                                            <th data-column="source">Source</th>
                                            <th data-column="category_type">Category Type</th>
                                            <th data-column="clerk">Clerk</th>
                                            <th data-column="client_category">Client Category</th>
                                            <th data-column="transaction_type">Transaction Type</th>
                                            <th data-column="status">Status</th>
                                            <th data-column="actions_taken">Actions Taken</th>
                                            <th data-column="remarks">Remarks</th>
                                            <th data-column="amount">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        @forelse ($transactions as $transaction)
                                            @php
                                                $txStatus = $transaction->status ?? 'Completed';
                                                $txIsApproved = strtolower($txStatus) === 'approved';
                                                $transactionEditUrl = route('transactions.edit', $transaction->id);
                                            @endphp
                                            <tr class="transaction-row {{ $txIsApproved ? 'transaction-row-disabled' : '' }}" data-transaction-url="{{ auth()->user()?->role_name === 'Viewer' ? '' : ($txIsApproved ? '' : $transactionEditUrl) }}">
                                                <td data-column="transaction_id">
                                                    @if ($txIsApproved || auth()->user()?->role_name === 'Viewer')
                                                        <span class="fw-semibold">{{ $transaction->transaction_id }}</span>
                                                    @else
                                                        <a href="{{ $transactionEditUrl }}"
                                                            class="fw-semibold text-decoration-none">
                                                            {{ $transaction->transaction_id }}
                                                        </a>
                                                    @endif
                                                </td>
                                                <td data-column="transaction_date">{{ $transaction->transaction_date->format('m/d/Y') }}</td>
                                                <td data-column="source" class="text-uppercase">E-Registration</td>
                                                <td data-column="category_type" class="text-uppercase">{{ $transaction->category_label }}</td>
                                                <td data-column="clerk" class="text-uppercase">{{ $transaction->clerk ?? auth()->user()->name ?? 'System' }}</td>
                                                <td data-column="client_category" class="text-uppercase">{{ filled($transaction->client_category) ? $transaction->client_category : ($client->sector ?? 'N/A') }}</td>
                                                <td data-column="transaction_type" class="text-uppercase">{{ $transaction->type_label ?? 'N/A' }}</td>
                                                <td data-column="status">
                                                    @if (strtolower($txStatus) === 'pending')
                                                        <span class="badge bg-warning-subtle text-warning">{{ $txStatus }}</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">{{ $txStatus }}</span>
                                                    @endif
                                                </td>
                                                <td data-column="actions_taken">{{ $transaction->actions_taken ?? 'N/A' }}</td>
                                                <td data-column="remarks">{{ $transaction->remarks ?? 'N/A' }}</td>
                                                <td data-column="amount">{{ $transaction->amount > 0 ? '₱' . number_format($transaction->amount, 2) : 'PHP 0.00' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="12" class="text-center text-muted py-4">
                                                    No transactions recorded for this client.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
                                <div class="small text-muted">
                                    Showing {{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }}
                                </div>
                                <div>
                                    {{ $transactions->links('pagination::bootstrap-5') }}
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
                            <img id="verifyFingerprintPreview" src="{{ $defaultFingerprint }}"
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

    @include('pages.client_transaction.newTransaction', ['isEditMode' => false])

    @if (session('show_created_modal'))
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
                        <p class="fs-5 fw-semibold mb-1">{{ $fullName }} has been saved successfully.</p>
                        <p class="text-muted mb-0">Would you like to add another client?</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                        <a href="{{ route('clients') }}" class="btn btn-primary px-4">Continue</a>
                        <button type="button" class="btn btn-outline-secondary px-4"
                            data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verifyModalEl = document.getElementById('verifyFingerprintModal');
            const verifyPreview = document.getElementById('verifyFingerprintPreview');
            const verifyStatus = document.getElementById('verifyFingerprintStatus');
            const verifyScanAgainBtn = document.getElementById('verifyFingerprintScanAgainBtn');
            const createdModalEl = document.getElementById('clientCreatedModal');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const currentClientId = Number(@json($client->id));
            const currentClientName = @json($fullName);
            const fingerprintPlaceholder = @json($defaultFingerprint);
            const fingerprintCaptureUrl = @json(route('fingerprint.capture'));
            const fingerprintSearchUrl = @json(route('client.search.fingerprint'));

            if (createdModalEl) {
                const createdModal = bootstrap.Modal.getOrCreateInstance(createdModalEl);
                createdModal.show();
            }

            document.querySelectorAll('.transaction-row').forEach((row) => {
                row.addEventListener('click', function(event) {
                    if (event.target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }

                    const transactionUrl = this.dataset.transactionUrl;
                    if (transactionUrl) {
                        window.location.assign(transactionUrl);
                    }
                });
            });

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

        // ----- Transaction History: Manage Columns -----
        document.addEventListener('DOMContentLoaded', function() {
            const STORAGE_KEY = 'clientTxHistoryHiddenColumns-{{ auth()->id() }}';
            const table = document.getElementById('clientTxHistoryTable');
            const toggles = Array.from(document.querySelectorAll('.tx-column-toggle'));
            const resetBtn = document.getElementById('resetTxColumnsBtn');

            const getHidden = () => {
                try {
                    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
                } catch (e) {
                    return [];
                }
            };

            const applyColumns = () => {
                if (!table) return;
                const hidden = getHidden();
                table.querySelectorAll('[data-column]').forEach((cell) => {
                    cell.style.display = hidden.includes(cell.dataset.column) ? 'none' : '';
                });
            };

            const syncToggles = () => {
                const hidden = getHidden();
                toggles.forEach((toggle) => {
                    toggle.checked = !hidden.includes(toggle.value);
                });
            };

            // selection is committed only on Apply; prevent hiding every column
            toggles.forEach((toggle) => {
                toggle.addEventListener('change', function() {
                    if (!toggle.checked && toggles.every((t) => !t.checked)) {
                        toggle.checked = true;
                    }
                });
            });

            document.getElementById('applyTxColumnsBtn')?.addEventListener('click', function() {
                const hidden = toggles.filter((t) => !t.checked).map((t) => t.value);
                localStorage.setItem(STORAGE_KEY, JSON.stringify(hidden));
                applyColumns();
                bootstrap.Dropdown.getInstance(document.getElementById('txHistoryColumnsBtn'))?.hide();
            });

            resetBtn?.addEventListener('click', function() {
                localStorage.removeItem(STORAGE_KEY);
                syncToggles();
                applyColumns();
            });

            syncToggles();
            applyColumns();
        });
    </script>
@endsection
