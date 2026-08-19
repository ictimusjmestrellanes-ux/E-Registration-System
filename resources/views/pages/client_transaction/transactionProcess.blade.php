@extends('layouts.master')
@section('title', 'ERS | Transaction Process')
@section('content')
    @php
        $txStatus = $transaction->status ?? 'Pending';
        $isApproved = strtolower($txStatus) === 'approved';
    @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Transaction Process</h4>
                                <p class="text-muted mb-0">
                                    Step-by-step process for
                                    <span class="text-uppercase fw-semibold">{{ $transaction->transaction_id }}</span>
                                    @if ($client)
                                        &nbsp;- {{ strtoupper($client->full_name) }}
                                    @endif
                                </p>
                            </div>
                            <a href="{{ $client ? route('clients.show', $client->id) : route('client.list') }}"
                                class="btn btn-secondary">Back to Client</a>
                        </div>

                        <div class="row g-4">
                            {{-- Process timeline --}}
                            <div class="col-12 col-lg-7">
                                <div class="border rounded-4 p-4 bg-light-subtle h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <h5 class="mb-0">Process Steps</h5>
                                        <span class="badge {{ $isApproved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} fs-6">
                                            {{ $txStatus }}
                                        </span>
                                    </div>

                                    <div class="position-relative" style="padding-left: 2.5rem;">
                                        <div class="position-absolute top-0 bottom-0" style="left: 0.9rem; width: 2px; background: #dee2e6;"></div>

                                        @foreach ($processSteps as $index => $step)
                                            <div class="position-relative mb-4 pb-1">
                                                <div class="position-absolute top-0 start-0 translate-middle d-flex align-items-center justify-content-center rounded-circle {{ $step['done'] ? 'bg-success text-white' : 'bg-secondary text-white' }}"
                                                    style="width: 1.8rem; height: 1.8rem; left: -1.6rem !important; z-index: 1;">
                                                    <i class="bi {{ $step['done'] ? 'bi-check-lg' : 'bi-clock' }}"></i>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div>
                                                        <h6 class="mb-1">{{ $index + 1 }}. {{ $step['title'] }}</h6>
                                                        <p class="text-muted mb-0 small">{{ $step['detail'] }}</p>
                                                    </div>
                                                    <div class="text-end">
                                                        @if ($step['time'])
                                                            <span class="badge bg-light text-dark border">{{ $step['time']->format('m/d/Y h:i A') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Transaction details --}}
                            <div class="col-12 col-lg-5">
                                <div class="border rounded-4 p-4 bg-light-subtle">
                                    <h5 class="mb-3">Transaction Details</h5>
                                    <table class="table table-sm table-bordered align-middle mb-4">
                                        <tbody>
                                            <tr>
                                                <th class="table-light text-uppercase small" style="width: 40%;">Transaction ID</th>
                                                <td class="text-uppercase">{{ $transaction->transaction_id }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Transaction Date</th>
                                                <td>{{ $transaction->transaction_date->format('m/d/Y') }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Source</th>
                                                <td class="text-uppercase">{{ $transaction->source ?? 'E-Registration' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Category</th>
                                                <td class="text-uppercase">{{ $transaction->category_label }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Type</th>
                                                <td class="text-uppercase">{{ $transaction->type_label }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Clerk</th>
                                                <td class="text-uppercase">{{ $transaction->clerk ?? 'System' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Signatory</th>
                                                <td class="text-uppercase">{{ $transaction->signatory ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Personnel Endorsed To</th>
                                                <td class="text-uppercase">{{ $transaction->personnel_endorsed_to ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Responsible Office</th>
                                                <td class="text-uppercase">{{ $transaction->responsible_office ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Amount</th>
                                                <td>{{ $transaction->amount > 0 ? 'PHP ' . number_format((float) $transaction->amount, 2) : 'PHP 0.00' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Description</th>
                                                <td>{{ $transaction->description ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Actions Taken</th>
                                                <td>{{ $transaction->actions_taken ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th class="table-light text-uppercase small">Remarks</th>
                                                <td>{{ $transaction->remarks ?? 'N/A' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h5 class="mb-3">Subject Information</h5>
                                    @if ($hasSubject)
                                        <table class="table table-sm table-bordered align-middle mb-4">
                                            <tbody>
                                                <tr>
                                                    <th class="table-light text-uppercase small" style="width: 40%;">Name</th>
                                                    <td class="text-uppercase">{{ $transaction->subject_full_name }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Age</th>
                                                    <td>{{ $transaction->subject_age !== null ? $transaction->subject_age . ' yrs. old' : 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Gender</th>
                                                    <td class="text-uppercase">{{ $transaction->subject_gender ?? 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Birthdate</th>
                                                    <td>{{ optional($transaction->subject_birthdate)->format('m/d/Y') ?? 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Barangay</th>
                                                    <td class="text-uppercase">{{ $transaction->subject_barangay ?? 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Municipality</th>
                                                    <td class="text-uppercase">{{ $transaction->subject_municipality ?? 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light text-uppercase small">Relation to Client</th>
                                                    <td class="text-uppercase">{{ $transaction->subject_client_relation ?? 'N/A' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    @else
                                        <div class="alert alert-secondary py-2 small">No subject information recorded for this transaction.</div>
                                    @endif

                                    <h5 class="mb-3">Requirements ({{ $requirements->count() }})</h5>
                                    @if ($requirements->isNotEmpty())
                                        <div class="list-group">
                                            @foreach ($requirements as $requirement)
                                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                    <div>
                                                        <div class="fw-semibold small">{{ $requirement['label'] }}</div>
                                                        <div class="text-muted small">
                                                            {{ $requirement['created_at']->format('m/d/Y h:i A') }}
                                                            &middot;
                                                            @if ($requirement['file_name'])
                                                                {{ $requirement['file_name'] }}
                                                            @else
                                                                <span class="text-secondary fst-italic">No file provided</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if ($requirement['file_url'])
                                                        <a href="{{ route('transaction-requirements.download', $requirement['id']) }}"
                                                            class="btn btn-outline-primary btn-sm">Download</a>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">No file</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="alert alert-secondary py-2 small">No requirements submitted for this transaction.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
