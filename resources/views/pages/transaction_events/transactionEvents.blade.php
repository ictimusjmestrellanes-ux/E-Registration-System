@extends('layouts.master')
@section('title', 'Transaction Events')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="mb-4">
                    <h4 class="mb-1 fw-semibold">Transaction Events</h4>
                    <p class="text-muted mb-0">Manage and track all transaction events.</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Event List</h5>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('transaction-events.index', array_merge(request()->except('page'), ['duplicate_names' => 1])) }}"
                                class="btn btn-sm {{ request()->boolean('duplicate_names') ? 'btn-warning' : 'btn-outline-warning' }}">
                                <i class="ri-file-copy-2-line me-1"></i> Duplicate Names
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#importModal">
                                <i class="ri-upload-2-line me-1"></i> Import CSV
                            </button>
                            <span class="badge bg-primary-subtle text-primary px-4 py-2">{{ $events->total() }} total</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" name="search"
                                    placeholder="Search name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" name="contact"
                                    placeholder="Search contact..." value="{{ request('contact') }}">
                            </div>
                            <div class="col-md-1">
                                <input type="number" class="form-control form-control-sm" name="age_from"
                                    placeholder="Age from" value="{{ request('age_from') }}">
                            </div>
                            <div class="col-md-1">
                                <input type="number" class="form-control form-control-sm" name="age_to"
                                    placeholder="Age to" value="{{ request('age_to') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" name="date_from"
                                    value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" name="date_to"
                                    value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                    <i class="ri-search-line"></i>
                                </button>
                                @if (request()->hasAny(['search', 'contact', 'age_from', 'age_to', 'date_from', 'date_to', 'duplicate_names']))
                                    <a href="{{ route('transaction-events.index') }}" class="btn btn-light btn-sm flex-fill">
                                        <i class="ri-close-line"></i>
                                    </a>
                                @endif
                            </div>
                        </form>

                        @if (request()->boolean('duplicate_names'))
                            <div class="alert alert-warning py-2 mb-3">
                                Showing transaction events with duplicate names.
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 120px;" class="text-center">
                                            <div class="d-inline-flex align-items-center justify-content-center gap-2 text-nowrap">
                                                <input type="checkbox" class="form-check-input" 
                                                    id="selectAllTransactionEvents"
                                                    aria-label="Select all transaction events on this page">
                                                <span>Select all</span>
                                            </div>
                                        </th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Contact No.</th>
                                        <th>Address</th>
                                        <th style="width: 100px;">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($events as $event)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input transaction-event-checkbox"
                                                    value="{{ $event->id }}"
                                                    aria-label="Select transaction event {{ $event->id }}">
                                            </td>
                                            <td class="fw-semibold">{{ $event->full_name }}</td>
                                            <td>{{ $event->age ?? '-' }}</td>
                                            <td>{{ str_replace('-', '', $event->contact_no) }}</td>
                                            <td>{{ $event->address ?? '-' }}</td>
                                            <td class="text-muted small">{{ $event->created_at?->format('M d, Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No transaction events found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            {{ $events->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('transaction-events.import') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importModalLabel">Import Transaction Events</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">Select CSV File</label>
                                <input type="file" class="form-control @error('csv_file') is-invalid @enderror"
                                    id="csv_file" name="csv_file" accept=".csv" required>
                                @error('csv_file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="alert alert-info mb-0">
                                <strong>CSV Format:</strong> The file should have the following columns (with header
                                row):<br>
                                <code>full_name, contact_no, address, age</code>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-upload-2-line me-1"></i> Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAllTransactionEvents');
            const eventCheckboxes = Array.from(document.querySelectorAll('.transaction-event-checkbox'));

            if (!selectAll) {
                return;
            }

            const syncSelectAllState = () => {
                const checkedCount = eventCheckboxes.filter((checkbox) => checkbox.checked).length;

                selectAll.checked = eventCheckboxes.length > 0 && checkedCount === eventCheckboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < eventCheckboxes.length;
                selectAll.disabled = eventCheckboxes.length === 0;
            };

            selectAll.addEventListener('change', function() {
                eventCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });

                syncSelectAllState();
            });

            eventCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', syncSelectAllState);
            });

            syncSelectAllState();
        });
    </script>
@endpush
