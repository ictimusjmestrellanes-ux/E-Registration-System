@extends('layouts.master')
@section('title', 'ERS | Events - Removed Duplicates')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1 fw-semibold">Events - Removed Duplicates</h4>
                        <p class="text-muted mb-0">List of transaction events removed during duplicate review.</p>
                    </div>
                    <a href="{{ route('transaction-events.duplicate-review') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i> Back to Duplicate Review
                    </a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Birth Date</th>
                                        <th>Contact No.</th>
                                        <th>Address</th>
                                        <th>Category</th>
                                        <th style="width: 160px;">Removed At</th>
                                        @if (auth()->user()?->role_name !== 'Viewer')
                                            <th style="width: 140px;" class="text-center">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($events as $event)
                                        <tr>
                                            <td>{{ $event->id }}</td>
                                            <td class="fw-semibold">{{ $event->full_name }}</td>
                                            <td>{{ optional($event->birth_date)->format('M d, Y') ?? '-' }}</td>
                                            <td>{{ str_replace('-', '', $event->contact_no ?? '') ?: '-' }}</td>
                                            <td class="small">{{ $event->address ?? '-' }}</td>
                                            <td class="small">{{ $event->client_category ?? '-' }}</td>
                                            <td>{{ optional($event->updated_at)->timezone('Asia/Manila')->format('M d, Y H:i:s') }}
                                            </td>
                                            @if (auth()->user()?->role_name !== 'Viewer')
                                                <td class="text-center">
                                                    <form
                                                        action="{{ route('transaction-events.reset-duplicate', $event) }}"
                                                        method="POST" class="m-0">
                                                        @csrf
                                                        @if (feature_allowed('Reset Duplicate Review'))
                                                            <button type="submit" class="btn btn-sm btn-soft-warning"
                                                                onclick="return confirm('Restore this event back to duplicate review?');">
                                                                <i class="ri-arrow-go-back-line me-1"></i> Undo Duplicate
                                                            </button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-soft-warning"
                                                                disabled>
                                                                <i class="ri-arrow-go-back-line me-1"></i>Not Allowed to
                                                                Undo Duplicate
                                                            </button>
                                                        @endif
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                                No removed duplicates found.
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
    </div>
@endsection
