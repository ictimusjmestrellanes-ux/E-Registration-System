@extends('layouts.master')
@section('title', 'ERS | Completed Client Merges')

@section('content')
    <div class="container-fluid">
        @foreach (['success' => 'success', 'error' => 'danger'] as $message => $color)
            @if (session($message))
                <div class="alert alert-{{ $color }} alert-dismissible fade show merge-groups-flash-alert"
                    role="alert" data-auto-dismiss-ms="5000">
                    {{ session($message) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        @endforeach

        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1 fw-semibold">Merge Client Groups</h4>
                        <p class="text-muted mb-0">
                            View all client groups that have already been merged into their oldest client profile.
                        </p>
                    </div>
                    <a href="{{ route('transaction-events.records-duplicates') }}"
                        class="btn btn-outline-primary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i> Back to Duplicate Event Records
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h5 class="card-title mb-1">Completed Merges</h5>
                            <div class="text-muted small">
                                {{ $completedMerges->total() }} completed client merge group(s)
                            </div>
                        </div>
                        <select class="form-select form-select-sm w-auto" id="completedMergesPerPage"
                            aria-label="Completed merges per page">
                            @foreach ([10, 15, 25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} / page</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="card-body">
                        @forelse ($completedMerges as $merge)
                            @php
                                $properties = $merge->properties ?? [];
                                $targetClientId = (string) ($properties['target_client_id'] ?? '—');
                                $targetClient = $completedTargetClients->get($targetClientId);
                                $sourceClientIds = collect($properties['source_client_ids'] ?? [])->filter()->values();
                                $eventIds = collect($properties['event_ids'] ?? [])->filter()->values();
                                $transactionChanges = collect($properties['transaction_id_changes'] ?? [])->values();
                                $updatedFields = collect($properties['profile_fields_updated'] ?? [])->filter()->values();
                            @endphp

                            <div class="border border-success-subtle rounded-4 p-3 mb-3">
                                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
                                    <div>
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h5 class="mb-0">
                                                Client {{ $targetClientId }}
                                                @if ($targetClient)
                                                    — {{ $targetClient->full_name }}
                                                @endif
                                            </h5>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="ri-check-double-line me-1"></i>Completed
                                            </span>
                                        </div>
                                        <div class="text-muted small">
                                            Merged by {{ $merge->user?->name ?? 'System' }} on
                                            {{ $merge->created_at?->format('M d, Y h:i A') ?? '—' }}
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary-subtle text-primary">
                                            {{ $eventIds->count() }} event record(s)
                                        </span>
                                        <span class="badge bg-info-subtle text-info">
                                            {{ $transactionChanges->count() }} transaction ID(s) updated
                                        </span>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-lg-6">
                                        <div class="border border-success-subtle bg-success-subtle rounded-3 p-3 h-100">
                                            <div class="text-uppercase text-success small fw-semibold mb-2">Client kept</div>
                                            <div class="fw-bold">
                                                {{ $targetClientId }}{{ $targetClient ? ' — '.$targetClient->full_name : '' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-uppercase text-muted small fw-semibold mb-2">
                                                Client profile(s) removed
                                            </div>
                                            @forelse ($sourceClientIds as $sourceClientId)
                                                <span class="badge bg-danger-subtle text-danger me-1">
                                                    {{ $sourceClientId }}
                                                </span>
                                            @empty
                                                <span class="text-muted">No source client IDs recorded.</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                                @if ($updatedFields->isNotEmpty())
                                    <div class="small mb-3">
                                        <span class="text-muted fw-semibold me-1">Profile fields updated:</span>
                                        {{ $updatedFields->map(fn ($field) => str($field)->replace('_', ' ')->title())->implode(', ') }}
                                    </div>
                                @endif

                                @if ($transactionChanges->isNotEmpty())
                                    <details>
                                        <summary class="fw-semibold text-primary" role="button">
                                            View transaction ID changes
                                        </summary>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-bordered table-sm table-hover align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Previous Transaction ID</th>
                                                        <th>New Transaction ID</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($transactionChanges as $change)
                                                        <tr>
                                                            <td>{{ $change['old'] ?? '—' }}</td>
                                                            <td class="fw-semibold text-success">{{ $change['new'] ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i class="ri-history-line display-5 text-muted"></i>
                                <h5 class="mt-3 mb-1">No completed merges yet</h5>
                                <p class="text-muted mb-0">Finished client-group merges will be recorded here.</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($completedMerges->hasPages())
                        <div class="card-footer d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <div class="small text-muted">
                                Showing {{ $completedMerges->firstItem() }}–{{ $completedMerges->lastItem() }} of
                                {{ $completedMerges->total() }} completed merge groups
                            </div>
                            @include('pages.transaction_events.partials.paginationWithPageJump', [
                                'paginator' => $completedMerges->onEachSide(1),
                                'pageName' => 'completed_page',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.merge-groups-flash-alert').forEach(alertElement => {
                const delay = Number(alertElement.dataset.autoDismissMs) || 5000;
                window.setTimeout(() => {
                    bootstrap.Alert.getOrCreateInstance(alertElement).close();
                }, delay);
            });

            document.getElementById('completedMergesPerPage')?.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                url.searchParams.delete('completed_page');
                window.location.href = url.toString();
            });
        });
    </script>
@endpush
