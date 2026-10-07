@extends('layouts.master')
@section('title', 'ERS | Activity Logs')

@section('content')
    @php
        $user = auth()->user();
        $profileAvatar = $user?->avatar_url;
        $profileCover = $user?->cover_url ?? asset('assets/images/city-hall1.jpg');
        $totalLogs = $activities->total();
        $todayCount = $todayActivities->count();
        $weeklyCount = $weeklyActivities->count();
        $monthlyCount = $monthlyActivities->count();
        $showActivities = feature_allowed('View Activity Logs');

        $actionMeta = function ($action, $properties = []) {
            $action = strtolower((string) $action);
            $route = (string) data_get($properties, 'route', '');
            $isDuplicateMerge =
                str_contains($action, 'merge') ||
                ($action === 'client_deleted' && $route === 'transaction-events.records-duplicates.merge-clients');

            return match (true) {
                $isDuplicateMerge => ['Merge', 'bg-primary-subtle text-primary', 'ri-git-merge-line'],
                $action === 'events_transferred_to_selected_client' => [
                    'Events transferred to selected client',
                    'bg-success-subtle text-success',
                    'ri-exchange-line',
                ],
                $action === 'events_marked_not_duplicate' => [
                    'Events marked not duplicate',
                    'bg-secondary-subtle text-secondary',
                    'ri-checkbox-circle-line',
                ],
                $action === 'events_imported' => [
                    'Events imported',
                    'bg-success-subtle text-success',
                    'ri-upload-2-line',
                ],
                $action === 'permission_created' => ['Create', 'bg-success-subtle text-success', 'ri-add-line'],
                in_array($action, ['event_transferred', 'events_transfer_selected'], true) => [
                    'Transfer',
                    'bg-success-subtle text-success',
                    'ri-exchange-line',
                ],
                in_array($action, ['event_transfer_undone', 'events_transfer_undone'], true) => [
                    'Undo transfer',
                    'bg-warning-subtle text-warning',
                    'ri-arrow-go-back-line',
                ],
                str_contains($action, 'create') => ['Create', 'bg-primary-subtle text-primary', 'ri-add-line'],
                str_contains($action, 'update') || str_contains($action, 'edit') => [
                    'Update',
                    'bg-info-subtle text-info',
                    'ri-pencil-line',
                ],
                str_contains($action, 'delete') => ['Delete', 'bg-danger-subtle text-danger', 'ri-delete-bin-line'],
                str_contains($action, 'archive') => ['Archive', 'bg-warning-subtle text-warning', 'ri-archive-line'],
                str_contains($action, 'restore') => ['Restore', 'bg-success-subtle text-success', 'ri-restart-line'],
                str_contains($action, 'login') => ['Login', 'bg-success-subtle text-success', 'ri-login-box-line'],
                str_contains($action, 'logout') => [
                    'Logout',
                    'bg-secondary-subtle text-secondary',
                    'ri-logout-box-r-line',
                ],
                str_contains($action, 'fingerprint') => [
                    'Fingerprint',
                    'bg-primary-subtle text-primary',
                    'ri-fingerprint-line',
                ],
                default => [
                    ucfirst(str_replace('_', ' ', $action ?: 'Activity')),
                    'bg-light text-dark',
                    'ri-history-line',
                ],
            };
        };

        $activitySortUrl = function (string $column) use ($sort, $sortDirection): string {
            $query = request()->query();
            unset($query['page']);
            $query['sort'] = $column;
            $query['direction'] = $sort === $column && $sortDirection === 'asc' ? 'desc' : 'asc';

            return route('activity.logs', $query);
        };
        $activitySortIcon = fn(string $column): string => $sort === $column
            ? ($sortDirection === 'asc'
                ? 'ri-arrow-up-line'
                : 'ri-arrow-down-line')
            : 'ri-arrow-up-down-line';
        $activityAriaSort = fn(string $column): string => $sort === $column
            ? ($sortDirection === 'asc'
                ? 'ascending'
                : 'descending')
            : 'none';

        $renderActivityList = function ($items) use ($actionMeta) {
            if ($items->isEmpty()) {
                return '<div class="text-center text-muted py-4">No activity logs found in this range.</div>';
            }

            return $items
                ->map(function ($activity) use ($actionMeta) {
                    [$label, $badgeClass, $icon] = $actionMeta($activity->action, $activity->properties);
                    $subjectLabel = $activity->subject_type
                        ? class_basename($activity->subject_type) .
                            ($activity->subject_id ? ' #' . $activity->subject_id : '')
                        : 'System';
                    $timeLabel = $activity->created_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') ?? '-';
                    $userName = $activity->user?->name ?? 'System';

                    return '
                    <div class="activity-item d-flex gap-3">
                        <div class="activity-icon flex-shrink-0">
                            <i class="' .
                        $icon .
                        '"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h6 class="mb-0">' .
                        e($activity->description) .
                        '</h6>
                                <span class="badge rounded-pill ' .
                        e($badgeClass) .
                        '">' .
                        e($label) .
                        '</span>
                            </div>
                            <div class="activity-meta text-muted">
                                <span><i class="ri-user-3-line me-1"></i>' .
                        e($userName) .
                        '</span>
                                <span><i class="ri-time-line me-1"></i>' .
                        e($timeLabel) .
                        '</span>
                                <span><i class="ri-government-line me-1"></i>' .
                        e($subjectLabel) .
                        '</span>
                                <span><i class="ri-global-line me-1"></i>' .
                        e($activity->ip_address ?? 'Unknown IP') .
                        '</span>
                            </div>
                            ' .
                        view('pages.activity_logs.details', ['activity' => $activity])->render() .
                        '
                        </div>
                    </div>
                ';
                })
                ->implode('');
        };
    @endphp

    <style>
        .activity-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            min-height: 0;
            aspect-ratio: 2571 / 727;
            background-color: #192452;
            color: #fff;
        }

        .activity-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(25, 36, 82, 0.2), rgba(25, 36, 82, 0.36));
            z-index: 1;
        }

        .activity-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url('{{ $profileCover }}');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 1;
            z-index: 0;
        }

        .activity-hero>* {
            position: relative;
            z-index: 2;
        }

        .activity-avatar {
            width: 92px;
            height: 92px;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.85);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .activity-hero-tabs .nav-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 8px;
            font-weight: 600;
            padding: 0.65rem 1rem;
        }

        .activity-hero-tabs .nav-link.active {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        .activity-panel {
            border-radius: 14px;
            overflow: hidden;
        }

        .activity-summary-card {
            border-radius: 14px;
        }

        .activity-stat {
            border: 1px solid rgba(65, 81, 163, 0.12);
            border-radius: 14px;
            padding: 1rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 255, 1));
        }

        .activity-item {
            position: relative;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(65, 81, 163, 0.1);
            border-radius: 14px;
            background: var(--vz-card-bg, #fff);
        }

        .activity-item+.activity-item {
            margin-top: 0.85rem;
        }

        .activity-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(82, 93, 187, 0.12);
            color: #515fcb;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            font-size: 0.85rem;
        }

        .activity-note {
            font-size: 0.85rem;
            color: var(--vz-secondary-color, #6c757d);
        }

        .activity-tabs .nav-link {
            border: 0;
            border-bottom: 2px solid transparent;
            color: var(--vz-primary, #405189);
            padding: 0.75rem 0.9rem;
            font-weight: 600;
        }

        .activity-tabs .nav-link.active {
            color: var(--vz-primary, #405189);
            border-bottom-color: var(--vz-primary, #405189);
            background: transparent;
        }

        @media (max-width: 767.98px) {
            .activity-hero {
                aspect-ratio: auto;
            }

            .activity-hero::after {
                background-size: cover;
            }

            .activity-hero-tabs {
                width: 100%;
            }

            .activity-hero-tabs .nav-item {
                flex: 1 1 0;
            }

            .activity-hero-tabs .nav-link {
                width: 100%;
                text-align: center;
            }
        }
    </style>

    <div class="container-fluid" id="activityLogsLive" data-live-url="{{ route('activity.logs.live-state') }}"
        data-overview-latest-id="{{ $liveLatestIds['overview_latest_id'] }}"
        data-activities-latest-id="{{ $liveLatestIds['activities_latest_id'] }}">
        <div class="activity-hero p-3 p-lg-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                <div class="d-flex align-items-center gap-3 gap-lg-4">
                    <img src="{{ $profileAvatar }}" alt="User Avatar" class="rounded-circle activity-avatar">
                    <div>
                        <h2 class="text-white mb-1 fw-bold text-uppercase">{{ $user?->name ?? 'User' }}</h2>
                        <p class="text-white-50 mb-0">Track every meaningful action recorded in the system.</p>
                    </div>
                </div>
                <div class="d-flex align-items-start align-items-lg-center gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary">Back to Dashboard</a>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mt-4">
                <ul class="nav nav-pills activity-hero-tabs gap-2" role="tablist">

                    @if (feature_allowed('View Activity Logs'))
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab"
                                href="#activities-tab" role="tab">Activities</a>
                        </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link {{ $showActivities ? '' : 'active' }}" data-bs-toggle="tab"
                            href="#overview-tab" role="tab">Overview</a>
                    </li>
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <div class="badge rounded-pill bg-light text-primary px-3 py-2">{{ $totalLogs }} total logs</div>
                    <div class="badge rounded-pill bg-light text-primary px-3 py-2">{{ $todayCount }} today</div>
                </div>
            </div>
        </div>

        <div class="alert alert-info d-none align-items-center justify-content-between gap-3" role="status"
            aria-live="polite" id="activityLogsLiveNotice">
            <span><i class="ri-notification-3-line me-1" aria-hidden="true"></i> New activity logs are available.</span>
            <button type="button" class="btn btn-sm btn-info" id="activityLogsShowNewest">Show now</button>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade {{ $showActivities ? '' : 'show active' }}" id="overview-tab"
                role="tabpanel">
                <div class="row g-4">
                    <div class="col-xxl-3">
                        <div class="card activity-summary-card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Info</h5>
                                <div class="activity-stat mb-3">
                                    <div class="text-muted small text-uppercase mb-1">Logged In User</div>
                                    <div class="fw-semibold">{{ $user?->name ?? 'User' }}</div>
                                    <div class="text-muted small">{{ $user?->email ?? 'No email set' }}</div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0 align-middle">
                                        <tbody>
                                            <tr>
                                                <th class="ps-0" scope="row">Total Logs :</th>
                                                <td class="text-muted">{{ $totalLogs }}</td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0" scope="row">Today :</th>
                                                <td class="text-muted">{{ $todayCount }}</td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0" scope="row">Weekly :</th>
                                                <td class="text-muted">{{ $weeklyCount }}</td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0" scope="row">Monthly :</th>
                                                <td class="text-muted">{{ $monthlyCount }}</td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0" scope="row">Joining Date :</th>
                                                <td class="text-muted">{{ $user?->created_at?->format('d M Y') ?? '-' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-9">
                        <div class="card activity-panel shadow-sm">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 me-2">Recent Activity</h4>
                                <div class="flex-shrink-0 ms-auto">
                                    <ul class="nav justify-content-end nav-tabs-custom rounded card-header-tabs border-bottom-0 activity-tabs"
                                        role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-bs-toggle="tab" href="#all-tab"
                                                role="tab">All</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#today-tab"
                                                role="tab">Today</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#weekly-tab"
                                                role="tab">Weekly</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#monthly-tab"
                                                role="tab">Monthly</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="{{ route('activity.logs') }}" class="mb-4">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-lg-6 col-md-6">
                                            <label class="form-label fw-semibold small text-muted">Search</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                                <input type="text" name="overview_search" class="form-control"
                                                    placeholder="Search activity or IP..." value="{{ $overviewSearch }}">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label fw-semibold small text-muted">Action Type</label>
                                            <select name="overview_action" class="form-select">
                                                <option value="">All Actions</option>
                                                @foreach ($overviewActions as $overviewActionOption)
                                                    <option value="{{ $overviewActionOption }}"
                                                        {{ $overviewAction === $overviewActionOption ? 'selected' : '' }}>
                                                        {{ ucfirst(str_replace('_', ' ', $overviewActionOption)) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-2 col-md-6 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary flex-grow-1">
                                                <i class="ri-filter-3-line me-1"></i>Filter
                                            </button>
                                            <a href="{{ route('activity.logs') }}" class="btn btn-outline-secondary"
                                                title="Reset overview filters">
                                                <i class="ri-refresh-line"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>

                                <div class="tab-content text-muted">
                                    <div class="tab-pane fade show active" id="all-tab" role="tabpanel">
                                        {!! $renderActivityList($allActivities) !!}

                                        @if ($allActivities->hasPages())
                                            <div class="d-flex justify-content-end mt-3">
                                                {{ $allActivities->links('pagination::bootstrap-5') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="tab-pane fade " id="today-tab" role="tabpanel">
                                        {!! $renderActivityList($todayActivities) !!}
                                    </div>
                                    <div class="tab-pane fade" id="weekly-tab" role="tabpanel">
                                        {!! $renderActivityList($weeklyActivities) !!}
                                    </div>
                                    <div class="tab-pane fade" id="monthly-tab" role="tabpanel">
                                        {!! $renderActivityList($monthlyActivities) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade {{ $showActivities ? 'show active' : '' }}" id="activities-tab"
                role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                            <div>
                                <h5 class="card-title mb-1">All Activity Logs</h5>
                                <p class="text-muted mb-0">Filter and search through system actions.</p>
                            </div>
                            <div class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2">
                                Showing {{ $activities->firstItem() ?? 0 }} - {{ $activities->lastItem() ?? 0 }} of
                                {{ $filteredTotal }}
                            </div>
                        </div>

                        <form method="GET" action="{{ route('activity.logs') }}" id="activityFilterForm">
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-lg-4 col-xxl-3">
                                    <label class="form-label fw-semibold small text-muted">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                                        <input type="text" name="search" class="form-control"
                                            placeholder="Search client ID, name, description, action, IP..."
                                            value="{{ $search }}">
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4 col-xxl-2">
                                    <label class="form-label fw-semibold small text-muted">User</label>
                                    @include(
                                        'pages.transaction_events.partials.multiSelectSearchDropdown',
                                        [
                                            'dropdownId' => 'activityUserFilter',
                                            'fieldName' => 'user',
                                            'options' => $filterableUsers->map(
                                                fn($filterUser) => [
                                                    'value' => (string) $filterUser->id,
                                                    'label' => $filterUser->name,
                                                ]),
                                            'allLabel' => 'All users',
                                            'searchPlaceholder' => 'Search users...',
                                        ]
                                    )
                                </div>
                                <div class="col-12 col-lg-4 col-xxl-2">
                                    <label class="form-label fw-semibold small text-muted">Action Type</label>
                                    @include(
                                        'pages.transaction_events.partials.multiSelectSearchDropdown',
                                        [
                                            'dropdownId' => 'activityActionFilter',
                                            'fieldName' => 'action',
                                            'options' => $uniqueActions->map(
                                                fn($activityAction) => [
                                                    'value' => $activityAction,
                                                    'label' => ucfirst(str_replace('_', ' ', $activityAction)),
                                                ]),
                                            'allLabel' => 'All actions',
                                            'searchPlaceholder' => 'Search actions...',
                                        ]
                                    )
                                </div>
                                <div class="col-12 col-lg-3 col-xxl-2">
                                    <label for="activityDateFrom" class="form-label fw-semibold small text-muted">Date
                                        From</label>
                                    <input type="date" name="date_from" id="activityDateFrom" class="form-control"
                                        value="{{ $dateFrom }}">
                                </div>
                                <div class="col-12 col-lg-3 col-xxl-2">
                                    <label for="activityDateTo" class="form-label fw-semibold small text-muted">Date
                                        To</label>
                                    <input type="date" name="date_to" id="activityDateTo" class="form-control"
                                        value="{{ $dateTo }}">
                                </div>
                                <div class="col-12 col-lg-2 col-xxl-1 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="ri-filter-3-line"></i><span class="visually-hidden">Filter</span>
                                    </button>
                                    <a href="{{ route('activity.logs') }}" class="btn btn-outline-secondary"
                                        title="Reset">
                                        <i class="ri-refresh-line"></i>
                                    </a>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 160px;" aria-sort="{{ $activityAriaSort('date') }}">
                                            <a href="{{ $activitySortUrl('date') }}"
                                                class="d-inline-flex align-items-center gap-1 text-reset text-decoration-none">
                                                Date <i class="{{ $activitySortIcon('date') }}" aria-hidden="true"></i>
                                            </a>
                                        </th>
                                        <th style="width: 180px;" aria-sort="{{ $activityAriaSort('user') }}">
                                            <a href="{{ $activitySortUrl('user') }}"
                                                class="d-inline-flex align-items-center gap-1 text-reset text-decoration-none">
                                                User <i class="{{ $activitySortIcon('user') }}" aria-hidden="true"></i>
                                            </a>
                                        </th>
                                        <th style="width: 160px;" aria-sort="{{ $activityAriaSort('action') }}">
                                            <a href="{{ $activitySortUrl('action') }}"
                                                class="d-inline-flex align-items-center gap-1 text-reset text-decoration-none">
                                                Action <i class="{{ $activitySortIcon('action') }}"
                                                    aria-hidden="true"></i>
                                            </a>
                                        </th>
                                        <th aria-sort="{{ $activityAriaSort('description') }}">
                                            <a href="{{ $activitySortUrl('description') }}"
                                                class="d-inline-flex align-items-center gap-1 text-reset text-decoration-none">
                                                Description <i class="{{ $activitySortIcon('description') }}"
                                                    aria-hidden="true"></i>
                                            </a>
                                        </th>
                                        <th style="width: 220px;" aria-sort="{{ $activityAriaSort('client') }}">
                                            <a href="{{ $activitySortUrl('client') }}"
                                                class="d-inline-flex align-items-center gap-1 text-reset text-decoration-none">
                                                Client ID / Full Name <i class="{{ $activitySortIcon('client') }}"
                                                    aria-hidden="true"></i>
                                            </a>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($activities as $activity)
                                        @php
                                            [$label, $badgeClass] = $actionMeta(
                                                $activity->action,
                                                $activity->properties,
                                            );
                                            $subjectLabel = $activity->subject_type
                                                ? class_basename($activity->subject_type) .
                                                    ($activity->subject_id ? ' #' . $activity->subject_id : '')
                                                : '-';
                                            $relatedClientId = $activity->related_client_id;
                                            $relatedClientName = $activity->related_client_name;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">
                                                    {{ $activity->created_at?->setTimezone('Asia/Manila')->format('M d, Y') }}
                                                </div>
                                                <div class="text-muted small">
                                                    {{ $activity->created_at?->setTimezone('Asia/Manila')->format('h:i A') }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $activity->user?->name ?? 'System' }}</div>
                                                <div class="text-muted small">
                                                    {{ $activity->user?->email ?? 'No linked user' }}</div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge rounded-pill {{ $badgeClass }} px-3 py-2">{{ $label }}</span>
                                            </td>
                                            <td>
                                                {{ $activity->description }}
                                                @include('pages.activity_logs.details', [
                                                    'activity' => $activity,
                                                ])
                                            </td>
                                            <td>
                                                @if ($relatedClientId || $relatedClientName)
                                                    <div class="fw-semibold">{{ $relatedClientId ?? '—' }}</div>
                                                    <div class="text-muted small">{{ $relatedClientName ?? '—' }}</div>
                                                @else
                                                    {{ $activity->user?->name ?? 'System' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                @if ($search || $userFilters || $actionFilters || $dateFrom || $dateTo)
                                                    No activity logs match your filters. <a
                                                        href="{{ route('activity.logs') }}">Clear filters</a>
                                                @else
                                                    No activity logs found yet.
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            {{ $activities->links('pagination::bootstrap-5') }}
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
            const liveRoot = document.getElementById('activityLogsLive');
            const notice = document.getElementById('activityLogsLiveNotice');
            const showNewest = document.getElementById('activityLogsShowNewest');
            if (!liveRoot || !notice || !showNewest) return;

            const latestIds = {
                overview: Number(liveRoot.dataset.overviewLatestId) || 0,
                activities: Number(liveRoot.dataset.activitiesLatestId) || 0,
            };
            let filtersDirty = false;
            let polling = false;
            let timer = null;

            const activeScope = () => document.getElementById('activities-tab')?.classList.contains('show') ?
                'activities' : 'overview';

            const rememberActiveTabs = () => {
                const top = document.querySelector('.activity-hero-tabs .nav-link.active')?.getAttribute(
                'href');
                const recent = document.querySelector('.activity-tabs .nav-link.active')?.getAttribute('href');
                sessionStorage.setItem('activityLogsLiveTabs', JSON.stringify({
                    top,
                    recent
                }));
            };

            const restoreActiveTabs = () => {
                let saved = null;
                try {
                    saved = JSON.parse(sessionStorage.getItem('activityLogsLiveTabs') || 'null');
                } catch (error) {
                    saved = null;
                }
                sessionStorage.removeItem('activityLogsLiveTabs');
                [saved?.top, saved?.recent].filter(Boolean).forEach(selector => {
                    const trigger = document.querySelector(
                        `a[data-bs-toggle="tab"][href="${selector}"]`);
                    if (trigger && window.bootstrap?.Tab) bootstrap.Tab.getOrCreateInstance(trigger)
                        .show();
                });
            };

            const onFirstPage = (scope) => {
                const url = new URL(window.location.href);
                const page = Number(url.searchParams.get(scope === 'activities' ? 'page' : 'overview_page')) ||
                    1;
                return page === 1;
            };

            const showNotice = () => notice.classList.replace('d-none', 'd-flex');

            const refresh = () => {
                rememberActiveTabs();
                window.location.reload();
            };

            const handleNewLogs = (scope) => {
                if (onFirstPage(scope) && !filtersDirty && !document.hidden) {
                    refresh();
                    return true;
                }
                showNotice();
                return false;
            };

            const poll = async () => {
                if (polling || document.hidden) return;
                polling = true;
                try {
                    const response = await fetch(liveRoot.dataset.liveUrl, {
                        headers: {
                            'Accept': 'application/json'
                        },
                        cache: 'no-store',
                    });
                    if (!response.ok) return;
                    const state = await response.json();
                    const scope = activeScope();
                    const newestId = Number(state[`${scope}_latest_id`]) || 0;
                    if (newestId > latestIds[scope] && handleNewLogs(scope)) return;
                } catch (error) {
                    // A temporary polling failure should not disrupt the page.
                } finally {
                    polling = false;
                }
            };

            document.querySelectorAll(
                    '#activityLogsLive form input, #activityLogsLive form select, #activityLogsLive form textarea')
                .forEach(control => {
                    control.addEventListener('input', () => {
                        filtersDirty = true;
                    });
                    control.addEventListener('change', () => {
                        filtersDirty = true;
                    });
                });

            showNewest.addEventListener('click', () => {
                const url = new URL(window.location.href);
                url.searchParams.delete(activeScope() === 'activities' ? 'page' : 'overview_page');
                rememberActiveTabs();
                window.location.assign(url.toString());
            });

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) poll();
            });

            restoreActiveTabs();
            timer = window.setInterval(poll, 5000);
            window.addEventListener('beforeunload', () => window.clearInterval(timer), {
                once: true
            });
        });
    </script>
@endpush
