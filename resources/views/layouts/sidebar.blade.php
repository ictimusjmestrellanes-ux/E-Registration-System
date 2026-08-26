    <style>
        .sidebar-user {
            position: relative;
            z-index: 5;
        }

        .sidebar-user .sidebar-user-toggle {
            width: 100%;
            text-align: left;
            cursor: pointer;
            position: relative;
            z-index: 6;
        }

        .sidebar-brand-content {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            max-width: 100%;
            vertical-align: middle;
        }

        .sidebar-brand-name {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.1;
        }

        .sidebar-brand-name > span {
            white-space: nowrap;
        }

        .sidebar-brand-logo {
            width: auto;
            height: 40px;
        }
    </style>
    <div class="app-menu navbar-menu">
        <!-- LOGO -->
        <div class="navbar-brand-box">
            <!-- Dark Logo-->
            <a href="{{ route('dashboard') }}" class="logo logo-dark">
                <span class="logo-sm">
                    <img src="{{ asset('assets/images/City-Logo.png') }}" class="sidebar-brand-logo" alt="City Logo">
                </span>
                <span class="logo-lg">
                    <span class="sidebar-brand-content">
                        <img src="{{ asset('assets/images/City-Logo.png') }}" class="sidebar-brand-logo" alt="City Logo">
                        <span class="sidebar-brand-name fw-bold fs-6 text-white">
                            <span>E-Registration</span>
                            <span>Management System</span>
                        </span>
                    </span>
                </span>
            </a>
            <!-- Light Logo-->
            <a href="{{ route('dashboard') }}" class="logo logo-light">
                <span class="logo-sm">
                    <img src="{{ asset('assets/images/City-Logo.png') }}" class="sidebar-brand-logo" alt="City Logo">
                </span>
                <span class="logo-lg">
                    <span class="sidebar-brand-content">
                        <img src="{{ asset('assets/images/City-Logo.png') }}" class="sidebar-brand-logo" alt="City Logo">
                        <span class="sidebar-brand-name fw-bold fs-6 text-white">
                            <span>E-Registration</span>
                            <span>Management System</span>
                        </span>
                    </span>
                </span>
            </a>
            <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
                id="vertical-hover">
                <i class="ri-record-circle-line"></i>
            </button>
        </div>
        <div class="dropdown sidebar-user m-1 rounded">
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="sidebar-user-dropdown">
                <!-- item-->
                <h6 class="dropdown-header">Welcome {{ auth()->user()->name ?? 'User' }}!</h6>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('profile') }}"><i
                        class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i> <span
                        class="align-middle">Settings</span></a>
                <a class="dropdown-item" href="auth-logout-basic.html"><i
                        class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span class="align-middle"
                        data-key="t-logout">Logout</span></a>
            </div>
        </div>
        <div id="scrollbar">
            <div class="container-fluid">
                <div id="two-column-menu"></div>
                <ul class="navbar-nav" id="navbar-nav">

                    @php
                        // Resolve each module's visibility once.
                        $showDashboard = feature_allowed_uri('dashboard');
                        $showCreateClient = feature_allowed_uri('clients');
                        $showClientList = feature_allowed_uri('client-list');
                        $showArchiveList = feature_allowed_uri('archive');
                        $showDuplicateReview = feature_allowed_uri('duplicate-review');
                        $showEvents = feature_allowed_uri('transaction-events');
                        $showEventRecords = feature_allowed_uri('transaction-events/records');
                        $showRecordsDuplicates = feature_allowed_uri('transaction-events/records/duplicates');
                        $showArchiveFiles = feature_allowed_uri('transaction-events/archives');
                        $showEventsDuplicateReview = feature_allowed_uri('transaction-events/duplicate-review');
                        $canManage = !in_array(auth()->user()?->role_name, ['DSWD', 'Staff']);
                        $showUserManagement = $canManage && feature_allowed_uri('users');
                        $showActivityLogs = feature_allowed_uri('activity-logs');

                        // A section title only renders when it has at least one visible item.
                        $showMenuSection = $showDashboard;
                        $showClientsSection = $showCreateClient || $showClientList || $showArchiveList || $showDuplicateReview;
                        $showEventsSection = $showEvents || $showEventRecords || $showArchiveFiles || $showEventsDuplicateReview || $showRecordsDuplicates;
                        $showSettingsSection = $showUserManagement || $showActivityLogs;
                    @endphp

                    @if ($showMenuSection)
                    <li class="menu-title"><span data-key="t-menu">Menu</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['dashboard']) }}" href="{{ route('dashboard') }}">
                            <i class="ri-dashboard-2-line"></i> <span data-key="t-dashboard">Dashboard</span>
                        </a>
                    </li>
                    @endif

                    @if ($showClientsSection)
                    <li class="menu-title"><span data-key="t-menu">Clients</span></li>

                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['clients', 'clients/*', 'client-list', 'client-list/*', 'archive', 'archive/*']) }}" href="#sidebarClients"
                            data-bs-toggle="collapse" role="button"
                            aria-expanded="true" aria-controls="sidebarClients">
                            <i class="ri-group-line"></i> <span data-key="t-clients">Client Management</span>
                        </a>
                        <div class="collapse menu-dropdown show"
                            id="sidebarClients">
                            <ul class="nav nav-sm flex-column">
                                @if ($showCreateClient)
                                <li class="nav-item">
                                    <a href="{{ route('clients') }}" class="nav-link {{ set_active(['clients']) }}"
                                        data-key="t-create-client">Create Client</a>
                                </li>
                                @endif
                                @if ($showClientList)
                                <li class="nav-item">
                                    <a href="{{ route('client.list') }}"
                                        class="nav-link {{ set_active(['client-list', 'client-list/*', 'clients/*']) }}"
                                        data-key="t-client-list">Client List</a>
                                </li>
                                @endif
                                @if ($showArchiveList)
                                <li class="nav-item">
                                    <a href="{{ route('archive.list') }}"
                                        class="nav-link {{ set_active(['archive', 'archive/*']) }}"
                                        data-key="t-archive-list">Archive Clients</a>
                                </li>
                                @endif
                                @if ($showDuplicateReview)
                                <li class="nav-item">
                                    <a href="{{ route('duplicate.review') }}"
                                        class="nav-link {{ set_active(['duplicate-review']) }}">
                                        Duplicate Clients Review
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                    @endif

                    @if ($showEventsSection)
                    <li class="menu-title"><span data-key="t-menu">Events</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['transaction-events', 'transaction-events/*']) }}"
                            href="#sidebarEvents" data-bs-toggle="collapse" role="button"
                            aria-expanded="true" aria-controls="sidebarEvents">
                            <i class="ri-calendar-event-line"></i> <span>Events Management</span>
                        </a>
                        <div class="collapse menu-dropdown show"
                            id="sidebarEvents">
                            <ul class="nav nav-sm flex-column">
                                @if ($showEvents)
                                <li class="nav-item">
                                    <a href="{{ route('transaction-events.index') }}"
                                        class="nav-link {{ set_active(['transaction-events', 'transaction-events/duplicate-review', 'transaction-events/archives']) }}">Import Events</a>
                                </li>
                                @endif

                                @if ($showEventRecords)
                                <li class="nav-item">
                                    <a href="{{ route('transaction-events.records') }}"
                                        class="nav-link {{ set_active(['transaction-events/records', 'transaction-events/records/duplicates']) }}"
                                        data-key="t-events-records">Event Records</a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                    @endif

                    @if ($showSettingsSection)
                    <li class="menu-title"><span data-key="t-menu">Settings</span></li>
                    @endif
                    @if ($showUserManagement)
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['users.index', 'roles.index', 'permissions.index']) }}" href="#sidebarSettings"
                            data-bs-toggle="collapse" role="button"
                            aria-expanded="{{ set_expanded(['users.index', 'roles.index', 'permissions.index']) }}" aria-controls="sidebarSettings">
                            <i class="ri-settings-3-line"></i> <span data-key="t-settings">User Management</span>
                        </a>
                        <div class="collapse menu-dropdown {{ set_show(['users.index', 'roles.index', 'permissions.index']) }}"
                            id="sidebarSettings">
                            <ul class="nav nav-sm flex-column">
                                <li class="nav-item">
                                    <a href="{{ route('users.index') }}"
                                        class="nav-link {{ set_active(['users.index']) }}"
                                        data-key="t-users">Users</a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('roles.index') }}"
                                        class="nav-link {{ set_active(['roles.index']) }}"
                                        data-key="t-roles">Roles</a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('permissions.index') }}"
                                        class="nav-link {{ set_active(['permissions.index']) }}"
                                        data-key="t-permissions">Permissions</a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    @endif

                    @if ($showActivityLogs)
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['activity.logs']) }}"
                            href="{{ route('activity.logs') }}">
                            <i class="ri-history-line"></i> <span data-key="t-simple-page">Activity Logs</span>
                        </a>
                    </li>
                    @endif

                    <li class="menu-title"><span data-key="t-menu">Pages</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['settings']) }}"
                            href="{{ route('settings') }}">
                            <i class="ri-user-line"></i> <span data-key="t-settings">Profile Page</span>
                        </a>
                    </li>

                </ul>

            </div>
            <!-- Sidebar -->
        </div>
        <div class="sidebar-background"></div>
    </div>

@push('scripts')
    <script>
        // Keep Clients / Events (and other groups) open when another
        // top-level menu is opened. The template JS normally closes all
        // sibling menus on "show.bs.collapse"; intercept that per group.
        document.addEventListener('DOMContentLoaded', function() {
            ['sidebarClients', 'sidebarEvents', 'sidebarSettings'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('show.bs.collapse', function(e) {
                    e.stopImmediatePropagation();
                }, true);
            });
        });
    </script>
@endpush
