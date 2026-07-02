@extends('layouts.master')
@section('title', 'Activity Logs')

@section('content')
    @php
        $user = auth()->user();
        $profileAvatar = $user?->avatar ? asset('storage/' . $user->avatar) : asset('assets/images/avatar-1.jpg');
        $profileCover = $user?->cover_photo ? asset('storage/' . $user->cover_photo) : asset('assets/images/profile-bg.jpg');
        $totalLogs = $activities->total();
        $todayCount = $todayActivities->count();
        $weeklyCount = $weeklyActivities->count();
        $monthlyCount = $monthlyActivities->count();

        $actionMeta = function ($action) {
            $action = strtolower((string) $action);

            return match (true) {
                str_contains($action, 'create') => ['Create', 'bg-primary-subtle text-primary', 'ri-add-line'],
                str_contains($action, 'update') => ['Update', 'bg-info-subtle text-info', 'ri-pencil-line'],
                str_contains($action, 'delete') => ['Delete', 'bg-danger-subtle text-danger', 'ri-delete-bin-line'],
                str_contains($action, 'archive') => ['Archive', 'bg-warning-subtle text-warning', 'ri-archive-line'],
                str_contains($action, 'restore') => ['Restore', 'bg-success-subtle text-success', 'ri-restart-line'],
                str_contains($action, 'login') => ['Login', 'bg-success-subtle text-success', 'ri-login-box-line'],
                str_contains($action, 'logout') => ['Logout', 'bg-secondary-subtle text-secondary', 'ri-logout-box-r-line'],
                str_contains($action, 'fingerprint') => ['Fingerprint', 'bg-primary-subtle text-primary', 'ri-fingerprint-line'],
                default => [ucfirst(str_replace('_', ' ', $action ?: 'Activity')), 'bg-light text-dark', 'ri-history-line'],
            };
        };

        $renderActivityList = function ($items) use ($actionMeta) {
            if ($items->isEmpty()) {
                return '<div class="text-center text-muted py-4">No activity logs found in this range.</div>';
            }

            return $items->map(function ($activity) use ($actionMeta) {
                [$label, $badgeClass, $icon] = $actionMeta($activity->action);
                $subjectLabel = $activity->subject_type
                    ? class_basename($activity->subject_type) . ($activity->subject_id ? ' #' . $activity->subject_id : '')
                    : 'System';
                $propertiesCount = is_array($activity->properties) ? count($activity->properties) : 0;
                $timeLabel = $activity->created_at?->format('M d, Y h:i A') ?? '-';
                $userName = $activity->user?->name ?? 'System';

                return '
                    <div class="activity-item d-flex gap-3">
                        <div class="activity-icon flex-shrink-0">
                            <i class="' . $icon . '"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h6 class="mb-0">' . e($activity->description) . '</h6>
                                <span class="badge rounded-pill ' . e($badgeClass) . '">' . e($label) . '</span>
                            </div>
                            <div class="activity-meta text-muted">
                                <span><i class="ri-user-3-line me-1"></i>' . e($userName) . '</span>
                                <span><i class="ri-time-line me-1"></i>' . e($timeLabel) . '</span>
                                <span><i class="ri-government-line me-1"></i>' . e($subjectLabel) . '</span>
                                <span><i class="ri-global-line me-1"></i>' . e($activity->ip_address ?? 'Unknown IP') . '</span>
                            </div>
                            ' . ($propertiesCount > 0 ? '<div class="activity-note mt-2">Has ' . e($propertiesCount) . ' detail field(s).</div>' : '') . '
                        </div>
                    </div>
                ';
            })->implode('');
        };
    @endphp

    <style>
        .activity-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            min-height: 220px;
            background: #3f4f8f;
            color: #fff;
        }

        .activity-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(51, 63, 116, 0.18), rgba(51, 63, 116, 0.32));
            z-index: 0;
        }

        .activity-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url('{{ $profileCover }}');
            background-size: cover;
            background-position: center;
            opacity: 0.28;
            z-index: 0;
        }

        .activity-hero > * {
            position: relative;
            z-index: 1;
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
            background: linear-gradient(180deg, rgba(255,255,255,0.9), rgba(248,250,255,1));
        }

        .activity-item {
            position: relative;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(65, 81, 163, 0.1);
            border-radius: 14px;
            background: var(--vz-card-bg, #fff);
        }

        .activity-item + .activity-item {
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
    </style>

    <div class="container-fluid">
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
                    <a href="{{ route('dashboard') }}" class="btn btn-success">Back to Dashboard</a>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mt-4">
                <ul class="nav nav-pills activity-hero-tabs gap-2" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#overview-tab" role="tab">Overview</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#activities-tab" role="tab">Activities</a>
                    </li>
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <div class="badge rounded-pill bg-light text-primary px-3 py-2">{{ $totalLogs }} total logs</div>
                    <div class="badge rounded-pill bg-light text-primary px-3 py-2">{{ $todayCount }} today</div>
                </div>
            </div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="overview-tab" role="tabpanel">
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
                                                <td class="text-muted">{{ $user?->created_at?->format('d M Y') ?? '-' }}</td>
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
                                    <ul class="nav justify-content-end nav-tabs-custom rounded card-header-tabs border-bottom-0 activity-tabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-bs-toggle="tab" href="#today-tab" role="tab">Today</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#weekly-tab" role="tab">Weekly</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#monthly-tab" role="tab">Monthly</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="tab-content text-muted">
                                    <div class="tab-pane fade show active" id="today-tab" role="tabpanel">
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

            <div class="tab-pane fade" id="activities-tab" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                            <div>
                                <h5 class="card-title mb-1">All Activity Logs</h5>
                                <p class="text-muted mb-0">Paginated history of system actions.</p>
                            </div>
                            <div class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2">
                                Showing {{ $activities->firstItem() ?? 0 }} - {{ $activities->lastItem() ?? 0 }} of {{ $totalLogs }}
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 160px;">Date</th>
                                        <th style="width: 180px;">User</th>
                                        <th style="width: 160px;">Action</th>
                                        <th>Description</th>
                                        <th style="width: 170px;">Subject</th>
                                        <th style="width: 130px;">IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($activities as $activity)
                                        @php
                                            [$label, $badgeClass] = $actionMeta($activity->action);
                                            $subjectLabel = $activity->subject_type
                                                ? class_basename($activity->subject_type) . ($activity->subject_id ? ' #' . $activity->subject_id : '')
                                                : '-';
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $activity->created_at?->format('M d, Y') }}</div>
                                                <div class="text-muted small">{{ $activity->created_at?->format('h:i A') }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $activity->user?->name ?? 'System' }}</div>
                                                <div class="text-muted small">{{ $activity->user?->email ?? 'No linked user' }}</div>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill {{ $badgeClass }} px-3 py-2">{{ $label }}</span>
                                            </td>
                                            <td>{{ $activity->description }}</td>
                                            <td>{{ $subjectLabel }}</td>
                                            <td>{{ $activity->ip_address ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">No activity logs found yet.</td>
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
