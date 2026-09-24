@extends('layouts.master')
@section('title', 'ERS | Not a Duplicate Review')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1 fw-semibold">Not a Duplicate Review</h4>
                        <p class="text-muted mb-0">Reviewed records remain grouped by client, like Duplicate Event
                            Records.</p>
                    </div>
                    <a href="{{ route('transaction-events.records-duplicates') }}"
                        class="btn btn-outline-primary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i> Back to Duplicate Event Records
                    </a>
                </div>
            </div>
        </div>

        @foreach (['success' => 'success', 'error' => 'danger'] as $message => $color)
            @if (session($message))
                <div class="alert alert-{{ $color }} alert-dismissible fade show" role="alert">
                    {{ session($message) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        @endforeach

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <span class="badge bg-warning-subtle text-warning fs-13">
                                {{ $groups->total() }} reviewed client group(s)
                            </span>
                            <select class="form-select form-select-sm w-auto" id="reviewPerPage"
                                aria-label="Groups per page">
                                @foreach ([10, 15, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>
                                        {{ $size }} / page
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @forelse ($groups as $group)
                            @php
                                $first = $group['events']->first();
                                $groupIds = $group['events']->pluck('id')->values();
                            @endphp
                            <div class="border rounded-4 p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <h6 class="mb-0">
                                        {{ $first->full_name }}
                                        <span class="badge bg-warning-subtle text-warning ms-1">
                                            {{ $group['total'] }} records
                                        </span>
                                    </h6>
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span class="small text-muted">
                                            Reviewed
                                            {{ optional($group['reviewed_at'])->timezone('Asia/Manila')->format('M d, Y H:i:s') }}
                                        </span>
                                        @if (auth()->user()?->role_name !== 'Viewer' && feature_allowed('Reset Duplicate Review'))
                                            <button type="button" class="btn btn-sm btn-outline-warning text-nowrap"
                                                data-bs-toggle="modal" data-bs-target="#undoReviewGroupModal"
                                                data-event-ids="{{ $groupIds->implode(',') }}"
                                                data-group-name="{{ $first->full_name }}"
                                                data-record-count="{{ $groupIds->count() }}">
                                                <i class="ri-arrow-go-back-line me-1"></i> Undo Review
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Transaction ID</th>
                                                <th>Full Name</th>
                                                <th>Birth Date</th>
                                                <th>Contact No.</th>
                                                <th>Client Category</th>
                                                <th>Transaction Category</th>
                                                <th>Transaction Type</th>
                                                <th>Event Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($group['events'] as $event)
                                                <tr>
                                                    <td>{{ $event->id }}</td>
                                                    <td class="fw-semibold">
                                                        {{ $event->transferredTransaction?->transaction_id ?? '-' }}
                                                    </td>
                                                    <td class="fw-semibold">{{ $event->full_name }}</td>
                                                    <td>{{ optional($event->birth_date)->format('M d, Y') ?? '-' }}</td>
                                                    <td>{{ $event->contact_no ?: '-' }}</td>
                                                    <td class="small">{{ $event->client_category ?? '-' }}</td>
                                                    <td class="small">{{ $event->transaction_category ?? '-' }}</td>
                                                    <td class="small">{{ $event->transaction_type ?? '-' }}</td>
                                                    <td>{{ optional($event->event_date)->format('M d, Y') ?? '-' }}</td>
                                                    <td>
                                                        @php
                                                            $statusColor = match ($event->status) {
                                                                'Claimed' => 'success',
                                                                'Unclaimed' => 'danger',
                                                                default => 'warning',
                                                            };
                                                        @endphp
                                                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">
                                                            {{ $event->status }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                No events have been marked as not a duplicate.
                            </div>
                        @endforelse

                        @if ($groups->total() > 0)
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                <div class="small text-muted">
                                    Showing {{ $groups->firstItem() }}–{{ $groups->lastItem() }} of
                                    {{ $groups->total() }} client groups
                                </div>
                                {{ $groups->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()?->role_name !== 'Viewer' && feature_allowed('Reset Duplicate Review'))
        <div class="modal fade" id="undoReviewGroupModal" tabindex="-1"
            aria-labelledby="undoReviewGroupModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('transaction-events.group-reset-duplicate') }}" method="POST">
                        @csrf
                        <div id="undoReviewGroupInputs"></div>
                        <div class="modal-header">
                            <h5 class="modal-title" id="undoReviewGroupModalLabel">
                                <i class="ri-arrow-go-back-line text-warning me-1"></i> Undo Not a Duplicate Review
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Return the entire reviewed group for
                                <strong id="undoReviewGroupName"></strong> to duplicate checking?</p>
                            <div class="alert alert-warning-subtle mb-0">
                                All <strong id="undoReviewGroupCount">0</strong> records will be removed from Not a
                                Duplicate Review and can appear again in Duplicate Event Records.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="ri-arrow-go-back-line me-1"></i> Confirm Undo Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        const undoReviewModal = document.getElementById('undoReviewGroupModal');
        undoReviewModal?.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            const ids = String(trigger?.dataset.eventIds || '').split(',').filter(Boolean);
            document.getElementById('undoReviewGroupName').textContent =
                trigger?.dataset.groupName || 'this client';
            document.getElementById('undoReviewGroupCount').textContent =
                trigger?.dataset.recordCount || ids.length;

            const inputs = document.getElementById('undoReviewGroupInputs');
            inputs.replaceChildren(...ids.map(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_ids[]';
                input.value = id;
                return input;
            }));
        });

        document.getElementById('reviewPerPage')?.addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    </script>
@endpush
