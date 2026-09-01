@extends('layouts.master')
@section('title', 'ERS | Transaction Event Archives')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 mb-4">
                <h4 class="mb-1 fw-semibold">Transaction Event Archives</h4>
                <p class="text-muted mb-0">Browse and download CSV archive files generated from imported transaction events.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Archive Files</h5>
                        <a href="{{ route('transaction-events.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="ri-arrow-left-line me-1"></i> Back to Transaction Events
                        </a>
                    </div>
                    <div class="card-body">
                        @if ($files->isEmpty())
                            <div class="alert alert-info mb-0">
                                No archive files found yet. Import a CSV file to create archive records.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Filename</th>
                                            <th style="width: 400px;">Imported By</th>
                                            <th style="width: 140px;">Uploaded At</th>
                                            <th style="width: 120px;">Size</th>
                                            <th style="width: 140px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($files as $file)
                                            <tr>
                                                <td>{{ $file['name'] }}</td>
                                                <td>
                                                    @if (!empty($file['imported_by']))
                                                        {{ $file['imported_by']['imported_by'] }}
                                                        @if (!empty($file['imported_by']['role']))
                                                            <span
                                                                class="badge badge-soft-secondary ms-1">{{ $file['imported_by']['role'] }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::createFromTimestamp($file['uploaded_at'])->timezone('Asia/Manila')->format('M d, Y H:i:s') }}
                                                </td>
                                                <td>{{ number_format($file['size'] / 1024, 2) }} KB</td>
                                                <td class="text-center">
                                                    @if (feature_allowed('Download Archive'))
                                                        <a href="{{ $file['download_url'] }}"
                                                            class="btn btn-sm btn-primary">
                                                            <i class="ri-download-line me-1"></i> Download
                                                        </a>
                                                    @else
                                                        <a href="{{ $file['download_url'] }}"
                                                            class="btn btn-sm btn-primary disabled" aria-disabled="true">
                                                            <i class="ri-download-line me-1"></i>Not Allowed to Download
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
