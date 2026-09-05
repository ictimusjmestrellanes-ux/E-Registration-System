@extends('layouts.master')
@section('title', 'ERS | Duplicate Clients Review')
@section('content')
@php
    $exactCount = $exactRecordsTotal ?? $exactGroups->sum('total');
    $likelyCount = $likelyRecordsTotal ?? $likelyGroups->sum('total');
    $similarCount = $similarRecordsTotal ?? $similarGroups->sum('total');
    $totalGroups = ($exactGroupsTotal ?? $exactGroups->count()) + ($likelyGroupsTotal ?? $likelyGroups->count()) + ($similarGroupsTotal ?? $similarGroups->count());
    $totalDuplicates = $exactCount + $likelyCount + $similarCount;

    $renderGroup = function ($group) {
        $first = $group['clients']->first();
        $out = '<div class="border rounded-4 p-3 mb-3">';
        $out .= '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">';
        $out .= '<div>';
        $out .= '<span class="badge bg-danger-subtle text-danger ms-1">' . $group['total'] . ' records</span>';
        $out .= '</div>';
            $out .= '<a href="' . e(route('client.list', ['client_ids' => $group['clients']->pluck('id')->implode(',')])) . '" class="btn btn-sm btn-outline-secondary">View in Client List</a>';
        $out .= '</div>';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-sm table-hover align-middle mb-0">';
        $out .= '<thead class="table-light"><tr>';
        $out .= '<th>Client ID</th><th>Photo</th><th>Name</th><th>Age</th><th>Birth Date</th><th>Gender</th><th>Contact</th><th>Address</th><th class="text-center">Actions</th>';
        $out .= '</tr></thead><tbody>';
        foreach ($group['clients'] as $client) {
            $out .= '<tr>';
            $out .= '<td class="fw-semibold">' . e($client->client_id) . '</td>';
            $out .= '<td><img src="' . e($client->photo_url) . '" alt="Photo" class="rounded avatar-sm object-fit-cover" onerror="this.onerror=null;this.src=\'' . e(asset('assets/images/profile.png')) . '\';"></td>';
            $out .= '<td>' . e($client->full_name) . '</td>';
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
                            <a href="{{ route('client.list') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-arrow-left-line me-1"></i> Back to Client List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="border rounded-4 p-3 mb-4" id="dupFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-0">
                                <div>
                                    <div class="fw-bold fs-5">Filter Duplicates</div>
                                    <div class="text-muted small">Narrow groups by keyword, gender, civil status,
                                        location, and created date range.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="dupFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="{{ route('duplicate.review') }}"
                                        class="btn btn-sm btn-soft-secondary">Reset</a>
                                    <select class="form-select form-select-sm w-auto" id="dupPerPageSelect"
                                        aria-label="Groups per page" title="Groups per page">
                                        @foreach ([10, 15, 25, 50, 100] as $size)
                                            <option value="{{ $size }}"
                                                {{ ($perPage ?? 25) == $size ? 'selected' : '' }}>
                                                {{ $size }} / page</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <form method="GET" id="dupFiltersForm"
                                class="mt-3 {{ request()->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to']) ? '' : 'd-none' }}">
                                <div class="row g-3">
                                    <div class="col-12 col-xl-4">
                                        <label for="dupKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Keyword Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="dupKeywordInput" name="search"
                                                placeholder="Name, client ID, contact, address..."
                                                value="{{ request('search') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupGenderFilter"
                                            class="form-label fw-semibold text-uppercase small">Gender</label>
                                        <select class="form-select" id="dupGenderFilter" name="gender">
                                            <option value="">All Gender</option>
                                            @foreach (($filterGenders ?? []) as $gender)
                                                <option value="{{ $gender }}"
                                                    {{ strtolower(request('gender', '')) === strtolower($gender) ? 'selected' : '' }}>
                                                    {{ $gender }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupCivilStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Civil Status</label>
                                        <select class="form-select" id="dupCivilStatusFilter" name="civil_status">
                                            <option value="">All Statuses</option>
                                            @foreach (($filterCivilStatuses ?? []) as $civilStatus)
                                                <option value="{{ $civilStatus }}"
                                                    {{ strtolower(request('civil_status', '')) === strtolower($civilStatus) ? 'selected' : '' }}>
                                                    {{ $civilStatus }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupCityFilter"
                                            class="form-label fw-semibold text-uppercase small">City</label>
                                        <select class="form-select" id="dupCityFilter" name="city">
                                            <option value="">All Cities</option>
                                            @foreach (($filterCities ?? []) as $city)
                                                <option value="{{ $city }}"
                                                    {{ strtolower(request('city', '')) === strtolower($city) ? 'selected' : '' }}>
                                                    {{ $city }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupBarangayFilter"
                                            class="form-label fw-semibold text-uppercase small">Barangay</label>
                                        <select class="form-select" id="dupBarangayFilter" name="barangay">
                                            <option value="">All Barangays</option>
                                            @foreach (($filterBarangays ?? []) as $barangay)
                                                <option value="{{ $barangay }}"
                                                    {{ strtolower(request('barangay', '')) === strtolower($barangay) ? 'selected' : '' }}>
                                                    {{ $barangay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupDateFrom"
                                            class="form-label fw-semibold text-uppercase small">Date From</label>
                                        <input type="date" class="form-control" id="dupDateFrom" name="date_from"
                                            value="{{ request('date_from') }}">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label for="dupDateTo"
                                            class="form-label fw-semibold text-uppercase small">Date To</label>
                                        <input type="date" class="form-control" id="dupDateTo" name="date_to"
                                            value="{{ request('date_to') }}">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 d-flex gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-3">
                                    {{ request()->anyFilled(['search', 'gender', 'civil_status', 'city', 'barangay', 'date_from', 'date_to']) ? 'Filtered groups are shown below.' : 'Showing all duplicate groups.' }}
                                </div>
                            </form>
                        </div>

                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#exact-tab" role="tab">
                                    Exact Match
                                    <span class="badge bg-danger-subtle text-danger ms-1">{{ $exactGroupsTotal ?? $exactGroups->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#likely-tab" role="tab">
                                    Likely Match
                                    <span class="badge bg-warning-subtle text-warning ms-1">{{ $likelyGroupsTotal ?? $likelyGroups->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#similar-tab" role="tab">
                                    Similar Spelling
                                    <span class="badge bg-info-subtle text-info ms-1">{{ $similarGroupsTotal ?? $similarGroups->count() }}</span>
                                </a>
                            </li>
                        </ul>
                        <div class="d-flex gap-2">
                                <span class="badge bg-primary-subtle text-primary fs-13">{{ $totalGroups }} group(s)</span>
                                <span class="badge bg-danger-subtle text-danger fs-13">{{ $totalDuplicates }} record(s)</span>
                            </div>
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
                                @if ($exactGroups->total() > 0)
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing {{ $exactGroups->firstItem() }}–{{ $exactGroups->lastItem() }} of {{ $exactGroups->total() }} groups</div>
                                        {{ $exactGroups->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
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
                                @if ($likelyGroups->total() > 0)
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing {{ $likelyGroups->firstItem() }}–{{ $likelyGroups->lastItem() }} of {{ $likelyGroups->total() }} groups</div>
                                        {{ $likelyGroups->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade" id="similar-tab" role="tabpanel">
                                <div class="alert alert-info-subtle d-flex align-items-center mb-3 py-2" role="alert">
                                    <i class="ri-information-line fs-4 me-2"></i>
                                    <div class="small">Possible-similar name spelling or typos (e.g. Maria/Marie, Iscober/Escobar) and format mismatches (e.g. "SURNAME, First" vs "First Last"). Verify before acting.</div>
                                </div>
                                @forelse ($similarGroups as $group)
                                    {!! $renderGroup($group) !!}
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-1 d-block mb-2"></i>
                                        No similar-spelling records found.
                                    </div>
                                @endforelse
                                @if ($similarGroups->total() > 0)
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
                                        <div class="small text-muted">Showing {{ $similarGroups->firstItem() }}–{{ $similarGroups->lastItem() }} of {{ $similarGroups->total() }} groups</div>
                                        {{ $similarGroups->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('dupFiltersToggleBtn');
            const formEl = document.getElementById('dupFiltersForm');
            if (!toggleBtn || !formEl) {
                return;
            }
            let filtersVisible = !formEl.classList.contains('d-none');
            const syncToggleLabel = () => {
                toggleBtn.innerHTML = filtersVisible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };
            syncToggleLabel();
            toggleBtn.addEventListener('click', function() {
                filtersVisible = !filtersVisible;
                formEl.classList.toggle('d-none', !filtersVisible);
                syncToggleLabel();
            });
            document.getElementById('dupPerPageSelect')?.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                ['exact_page', 'likely_page', 'similar_page', 'page'].forEach((k) => url.searchParams.delete(k));
                window.location.href = url.toString();
            });
            const initialHash = window.location.hash;
            if (initialHash) {
                const tabTrigger = document.querySelector('a[data-bs-toggle="tab"][href="' + initialHash + '"]');
                if (tabTrigger) {
                    bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
                }
            }
        });
    </script>
@endpush