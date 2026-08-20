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
    </style>
    <div class="app-menu navbar-menu">
        <!-- LOGO -->
        <div class="navbar-brand-box">
            <!-- Dark Logo-->
            <a href="{{ route('dashboard') }}" class="logo logo-dark">
                <span class="logo-lg d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/City-Logo.png') }}" alt="City Logo" style="height: 40px; width: auto;">
                    <span class="fw-bold fs-5 text-white" style="white-space: nowrap;">E-Reg System</span>
                </span>
            </a>
            <!-- Light Logo-->
            <a href="{{ route('dashboard') }}" class="logo logo-light">
                <span class="logo-lg d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/City-Logo.png') }}" alt="City Logo" style="height: 40px; width: auto;">
                    <span class="fw-bold fs-5 text-white" style="white-space: nowrap;">E-Reg System</span>
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

                    <li class="menu-title"><span data-key="t-menu">Menu</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['dashboard']) }}" href="{{ route('dashboard') }}">
                            <i class="ri-dashboard-2-line"></i> <span data-key="t-dashboard">Dashboard</span>
                        </a>
                    </li>

                    <li class="menu-title"><span data-key="t-menu">Clients</span></li>

                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['clients', 'client.list', 'archive.list']) }}" href="#sidebarClients"
                            data-bs-toggle="collapse" role="button"
                            aria-expanded="{{ set_expanded(['clients', 'client.list', 'archive.list']) }}" aria-controls="sidebarClients">
                            <i class="ri-group-line"></i> <span data-key="t-clients">Clients</span>
                        </a>
                        <div class="collapse menu-dropdown {{ set_show(['clients', 'client.list', 'archive.list']) }}"
                            id="sidebarClients">
                            <ul class="nav nav-sm flex-column">
                                <li class="nav-item">
                                    <a href="{{ route('clients') }}" class="nav-link {{ set_active(['clients']) }}"
                                        data-key="t-create-client">Create Client</a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('client.list') }}"
                                        class="nav-link {{ set_active(['client.list']) }}"
                                        data-key="t-client-list">Client List</a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('archive.list') }}"
                                        class="nav-link {{ set_active(['archive.list']) }}"
                                        data-key="t-archive-list">Archive</a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['duplicate.review']) }}"
                            href="{{ route('duplicate.review') }}">
                            <i class="ri-file-copy-2-line"></i> <span data-key="t-duplicate-review">Duplicate Clients Review</span>
                        </a>
                    </li>

                    <li class="menu-title"><span data-key="t-menu">Events</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['transaction-events.*']) }}"
                            href="{{ route('transaction-events.index') }}">
                            <i class="ri-calendar-event-line"></i> <span>Events</span>
                        </a>
                    </li>
                    

                    <li class="menu-title"><span data-key="t-menu">Settings</span></li>
                    @if(auth()->user()?->role_name !== 'DSWD' && auth()->user()?->role_name !== 'Staff')
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

                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['activity.logs']) }}"
                            href="{{ route('activity.logs') }}">
                            <i class="ri-history-line"></i> <span data-key="t-simple-page">Activity Logs</span>
                        </a>
                    </li>

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
