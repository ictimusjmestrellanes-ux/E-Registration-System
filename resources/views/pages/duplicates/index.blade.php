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
            $out .= '<a href="' . e(route('client.list', ['client_ids' => $group['clients']->pluck('id')->implode(',')])) . '" class="btn btn-sm btn-outline-primary">View in Client List</a>';
        $out .= '</div>';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-sm table-hover align-middle mb-0 duplicate-client-group-table">';
        $out .= '<thead class="table-light"><tr>';
        $sortableHeaders = [
            ['label' => 'Client ID', 'type' => 'text'],
            ['label' => 'Photo', 'type' => 'text'],
            ['label' => 'Name', 'type' => 'text'],
            ['label' => 'Age', 'type' => 'number'],
            ['label' => 'Birth Date', 'type' => 'date'],
            ['label' => 'Gender', 'type' => 'text'],
            ['label' => 'Contact', 'type' => 'text'],
            ['label' => 'Address', 'type' => 'text'],
            ['label' => 'Actions', 'type' => 'number', 'center' => true],
        ];
        foreach ($sortableHeaders as $column => $header) {
            $out .= '<th scope="col"' . (!empty($header['center']) ? ' class="text-center"' : '') . '>';
            $out .= '<button type="button" class="btn btn-link btn-sm p-0 text-body fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 text-nowrap' . (!empty($header['center']) ? ' justify-content-center' : '') . '" data-duplicate-client-sort data-sort-column="' . $column . '" data-sort-type="' . $header['type'] . '" data-sort-direction="" aria-label="Sort by ' . e($header['label']) . ' ascending">';
            $out .= e($header['label']) . '<i class="ri-arrow-up-down-line text-muted" aria-hidden="true"></i>';
            $out .= '</button></th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($group['clients'] as $client) {
            $address = collect([$client->address, $client->barangay, $client->city, $client->province])->filter()->implode(', ');
            $photoSortValue = filled($client->photo_path) ? '1|' . $client->photo_path : '0';
            $out .= '<tr>';
            $out .= '<td class="fw-semibold" data-sort-value="' . e($client->client_id) . '">' . e($client->client_id) . '</td>';
            $out .= '<td data-sort-value="' . e($photoSortValue) . '"><img src="' . e($client->photo_url) . '" alt="Photo" class="rounded avatar-sm object-fit-cover" onerror="this.onerror=null;this.src=\'' . e(asset('assets/images/profile.png')) . '\';"></td>';
            $out .= '<td data-sort-value="' . e($client->full_name) . '">' . e($client->full_name) . '</td>';
            $out .= '<td data-sort-value="' . e($client->age ?? '') . '">' . e($client->age ?? '-') . '</td>';
            $out .= '<td data-sort-value="' . e(optional($client->birth_date)->format('Y-m-d') ?? '') . '">' . e(optional($client->birth_date)->format('M d, Y') ?? '-') . '</td>';
            $out .= '<td data-sort-value="' . e($client->gender ?? '') . '">' . e($client->gender ?? '-') . '</td>';
            $out .= '<td data-sort-value="' . e($client->contact ?? '') . '">' . e($client->contact ?? '-') . '</td>';
            $out .= '<td class="small" data-sort-value="' . e($address) . '">' . e($address ?: '-') . '</td>';
            $out .= '<td class="text-center" data-sort-value="' . (int) $client->id . '"><a href="' . e(route('clients.show', $client)) . '" class="btn btn-sm btn-soft-info">View</a></td>';
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
                            <a href="{{ route('client.list') }}" class="btn btn-outline-primary btn-sm">
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
                                    <button type="button" class="btn btn-sm btn-primary" id="dupFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    <a href="{{ route('duplicate.review') }}"
                                        class="btn btn-sm btn-soft-primary">Reset</a>
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
                                    <span class="badge bg-info-subtle text-info ms-1">{{ ($similarScanPending ?? false) ? 'Pending' : ($similarGroupsTotal ?? $similarGroups->count()) }}</span>
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
                                    <div class="small">Same first name and last name, with at least one transaction per client. High confidence duplicates.</div>
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
                                @if ($similarScanPending ?? false)
                                    <div class="alert alert-info" data-similar-scan>
                                        <p class="mb-2" data-scan-status role="status" aria-live="polite">Similar Spelling loads in short batches so large client lists do not block this page. Exact Match is ready above.</p>
                                        <button type="button" class="btn btn-sm btn-primary" data-start-similar-scan>Load Similar Spelling</button>
                                    </div>
                                @else
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
            const scanPanel = document.querySelector('[data-similar-scan]');
            const scanButton = document.querySelector('[data-start-similar-scan]');
            const similarTab = document.querySelector('a[href="#similar-tab"]');
            let scanRunning = false;
            async function loadSimilarSpelling() {
                if (!scanPanel || scanRunning) return;
                scanRunning = true;
                scanButton.disabled = true;
                const status = scanPanel.querySelector('[data-scan-status]');
                try {
                    while (true) {
                        const response = await fetch(@json(route('duplicate.review.similar-scan')), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Scan request failed (' + response.status + ').');
                        const progress = await response.json();
                        status.textContent = progress.message;
                        if (progress.done) {
                            if (similarTab?.classList.contains('active')) {
                                const url = new URL(window.location.href);
                                url.hash = 'similar-tab';
                                window.location.replace(url.href);
                                window.location.reload();
                            } else {
                                scanButton.disabled = false;
                                scanButton.textContent = 'View results';
                                scanButton.onclick = () => {
                                    const url = new URL(window.location.href);
                                    url.hash = 'similar-tab';
                                    window.location.replace(url.href);
                                    window.location.reload();
                                };
                            }
                            return;
                        }
                        // Stop requesting batches when the reviewer leaves the tab;
                        // its cached cursor resumes on the next visit or retry.
                        if (!similarTab?.classList.contains('active')) return;
                        await new Promise(resolve => setTimeout(resolve, 250));
                    }
                } catch (error) {
                    status.textContent = error.message + ' You can retry; completed batches are retained.';
                    scanButton.textContent = 'Retry scan';
                } finally {
                    scanRunning = false;
                    scanButton.disabled = false;
                }
            }
            scanButton?.addEventListener('click', loadSimilarSpelling);
            similarTab?.addEventListener('shown.bs.tab', loadSimilarSpelling);
            if (window.location.hash === '#similar-tab') loadSimilarSpelling();

            const duplicateClientSortCollator = new Intl.Collator(undefined, {
                numeric: true,
                sensitivity: 'base',
            });

            document.addEventListener('click', function(event) {
                const sortButton = event.target.closest('[data-duplicate-client-sort]');
                if (!sortButton) return;

                const table = sortButton.closest('.duplicate-client-group-table');
                const tbody = table?.tBodies[0];
                if (!tbody) return;

                const column = Number(sortButton.dataset.sortColumn);
                const type = sortButton.dataset.sortType || 'text';
                const direction = sortButton.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
                const rows = Array.from(tbody.rows);

                const valueFor = row => {
                    const rawValue = row.cells[column]?.dataset.sortValue?.trim() ?? '';
                    if (rawValue === '') return null;
                    if (type === 'number') {
                        const numberValue = Number(rawValue);
                        return Number.isNaN(numberValue) ? null : numberValue;
                    }
                    if (type === 'date') {
                        const dateValue = Date.parse(rawValue);
                        return Number.isNaN(dateValue) ? null : dateValue;
                    }
                    return rawValue;
                };

                rows
                    .map((row, originalIndex) => ({ row, originalIndex, value: valueFor(row) }))
                    .sort((left, right) => {
                        if (left.value === null && right.value === null) {
                            return left.originalIndex - right.originalIndex;
                        }
                        if (left.value === null) return 1;
                        if (right.value === null) return -1;

                        const comparison = type === 'text'
                            ? duplicateClientSortCollator.compare(left.value, right.value)
                            : left.value - right.value;

                        return comparison === 0
                            ? left.originalIndex - right.originalIndex
                            : (direction === 'asc' ? comparison : -comparison);
                    })
                    .forEach(item => tbody.appendChild(item.row));

                table.querySelectorAll('[data-duplicate-client-sort]').forEach(button => {
                    button.dataset.sortDirection = '';
                    button.closest('th')?.removeAttribute('aria-sort');
                    const icon = button.querySelector('i');
                    if (icon) icon.className = 'ri-arrow-up-down-line text-muted';
                    button.setAttribute('aria-label', `Sort by ${button.textContent.trim()} ascending`);
                });

                sortButton.dataset.sortDirection = direction;
                sortButton.closest('th')?.setAttribute(
                    'aria-sort',
                    direction === 'asc' ? 'ascending' : 'descending'
                );
                const activeIcon = sortButton.querySelector('i');
                if (activeIcon) {
                    activeIcon.className = direction === 'asc'
                        ? 'ri-arrow-up-line text-primary'
                        : 'ri-arrow-down-line text-primary';
                }
                sortButton.setAttribute(
                    'aria-label',
                    `Sort by ${sortButton.textContent.trim()} ${direction === 'asc' ? 'descending' : 'ascending'}`
                );
            });

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
