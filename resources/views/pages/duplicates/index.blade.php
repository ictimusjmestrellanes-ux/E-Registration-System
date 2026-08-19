@extends('layouts.master')
@section('title', 'ERS | Duplicate Clients Review')
@section('content')
@php
    $exactCount = $exactGroups->sum('total');
    $likelyCount = $likelyGroups->sum('total');
    $similarCount = $similarGroups->sum('total');
    $totalGroups = $exactGroups->count() + $likelyGroups->count() + $similarGroups->count();
    $totalDuplicates = $exactCount + $likelyCount + $similarCount;

    $renderGroup = function ($group) {
        $first = $group['clients']->first();
        $out = '<div class="border rounded-4 p-3 mb-3">';
        $out .= '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">';
        $out .= '<div>';
        $out .= '<h6 class="mb-0">' . e($first->full_name);
        $out .= ' <span class="badge bg-danger-subtle text-danger ms-1">' . $group['total'] . ' records</span></h6>';
        $out .= '<p class="text-muted small mb-0">Birth date: ' . e(optional($first->birth_date)->format('M d, Y') ?? '-');
        $out .= ' &middot; Earliest record: ' . e(optional($group['created_at'])->format('M d, Y')) . '</p>';
        $out .= '</div>';
        $out .= '<a href="' . e(route('client.list', ['duplicate_names' => 1])) . '" class="btn btn-sm btn-outline-secondary">View in Client List</a>';
        $out .= '</div>';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-sm table-hover align-middle mb-0">';
        $out .= '<thead class="table-light"><tr>';
        $out .= '<th>Client ID</th><th>Photo</th><th>Age</th><th>Birth Date</th><th>Gender</th><th>Contact</th><th>Address</th><th class="text-center">Actions</th>';
        $out .= '</tr></thead><tbody>';
        foreach ($group['clients'] as $client) {
            $out .= '<tr>';
            $out .= '<td class="fw-semibold">' . e($client->client_id) . '</td>';
            $out .= '<td><img src="' . e($client->photo_url) . '" alt="Photo" class="rounded avatar-sm object-fit-cover" onerror="this.onerror=null;this.src=\'' . e(asset('assets/images/profile.png')) . '\';"></td>';
            $out .= '<td>' . e($client->age ?? '-') . '</td>';
            $out .= '<td>' . e(optional($client->birth_date)->format('M d, Y') ?? '-') . '</td>';
            $out .= '<td>' . e($client->gender ?? '-') . '</td>';
            $out .= '<td>' . e($client->contact ?? '-') . '</td>';
            $out .= '<td class="small">' . e(collect([$client->address, $client->barangay, $client->city, $client->province])->filter()->implode(', ') ?: '-') . '</td>';
            $out .= '<td class="text-center"><a href="' . e(route('clients.show', $client)) . '" class="btn btn-sm btn-soft-info">View</a></td>';
            $out .= '</tr>';
        }
        $out .= '</tbody></table></div></div>';
        return $out;
    };
@endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Duplicate Clients Review</h4>
                                <p class="text-muted mb-0">Review potential duplicate client records before taking action.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-primary-subtle text-primary fs-13">{{ $totalGroups }} group(s)</span>
                                <span class="badge bg-danger-subtle text-danger fs-13">{{ $totalDuplicates }} record(s)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#exact-tab" role="tab">
                                    Exact Match
                                    <span class="badge bg-danger-subtle text-danger ms-1">{{ $exactGroups->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#likely-tab" role="tab">
                                    Likely Match
                                    <span class="badge bg-warning-subtle text-warning ms-1">{{ $likelyGroups->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#similar-tab" role="tab">
                                    Similar Spelling
                                    <span class="badge bg-info-subtle text-info ms-1">{{ $similarGroups->count() }}</span>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="exact-tab" role="tabpanel">
                                <div class="alert alert-danger-subtle alert-dismissible d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-error-warning-line fs-4 me-2"></i>
                                    <div class="small">Exact-same name and birth date. High confidence duplicates.</div>
                                </div>
                                @forelse ($exactGroups as $group)
                                    {!! $renderGroup($group) !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No exact duplicate records found.
                                    </div>
                                @endforelse
                            </div>

                            <div class="tab-pane fade" id="likely-tab" role="tabpanel">
                                <div class="alert alert-warning-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-alert-line fs-4 me-2"></i>
                                    <div class="small">Likely-same name, birth date missing or year-only match. Review before acting.</div>
                                </div>
                                @forelse ($likelyGroups as $group)
                                    {!! $renderGroup($group) !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No likely duplicate records found.
                                    </div>
                                @endforelse
                            </div>

                            <div class="tab-pane fade" id="similar-tab" role="tabpanel">
                                <div class="alert alert-info-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-information-line fs-4 me-2"></i>
                                    <div class="small">Possible-similar name spelling (e.g. Maria/Marie, Jon/John). Verify before acting.</div>
                                </div>
                                @forelse ($similarGroups as $group)
                                    {!! $renderGroup($group) !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No similar-spelling records found.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection