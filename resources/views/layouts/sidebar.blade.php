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
                <span class="logo-lg">
                    <span class="fw-bold fs-5 text-white">E-Registration System</span>
                </span>
            </a>
            <!-- Light Logo-->
            <a href="{{ route('dashboard') }}" class="logo logo-light">
                <span class="logo-lg">
                    <span class="fw-bold fs-5 text-white">E-Registration System</span>
                </span>
            </a>
            <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
                id="vertical-hover">
                <i class="ri-record-circle-line"></i>
            </button>
        </div>
        <div class="dropdown sidebar-user m-1 rounded">
            <button type="button" class="btn material-shadow-none sidebar-user-toggle" id="sidebar-user-dropdown"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="d-flex align-items-center gap-2">
                    @php
                        $sidebarAvatar = auth()->user()?->avatar_url ?? asset('assets/images/avatar-1.jpg');
                    @endphp
                    <img class="rounded header-profile-user" src="{{ $sidebarAvatar }}" alt="Header Avatar">
                    <span class="text-start">
                        <span class="d-block fw-medium sidebar-user-name-text">E-Registration System</span>
                        <span class="d-block fs-14 sidebar-user-name-sub-text"><i
                                class="ri ri-circle-fill fs-10 text-success align-baseline"></i> <span
                                class="align-middle">Online</span></span>
                    </span>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="sidebar-user-dropdown">
                <!-- item-->
                <h6 class="dropdown-header">Welcome {{ auth()->user()->name ?? 'User' }}!</h6>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('profile') }}"><i
                        class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i> <span
                        class="align-middle">Settings</span></a>
                <a class="dropdown-item" href="auth-lockscreen-basic.html"><i
                        class="mdi mdi-lock text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Lock
                        screen</span></a>
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

                    <li class="menu-title"><i class="ri-more-fill"></i> <span data-key="t-pages">Pages</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ set_active(['profile', 'settings']) }}" href="#sidebarPages"
                            data-bs-toggle="collapse" role="button"
                            aria-expanded="{{ set_expanded(['profile', 'settings']) }}" aria-controls="sidebarPages">
                            <i class="ri-pages-line"></i> <span data-key="t-pages">Profile Page</span>
                        </a>
                        <div class="collapse menu-dropdown {{ set_show(['activity.logs', 'profile', 'settings']) }}"
                            id="sidebarPages">
                            <ul class="nav nav-sm flex-column">
                                <li class="nav-item">
                                    <a href="#sidebarProfile"
                                        class="nav-link {{ set_active(['activity.logs', 'profile', 'settings']) }}"
                                        data-bs-toggle="collapse" role="button"
                                        aria-expanded="{{ set_expanded(['activity.logs', 'profile', 'settings']) }}"
                                        aria-controls="sidebarProfile" data-key="t-profile">Profile</a>
                                    <div class="collapse menu-dropdown {{ set_show(['activity.logs', 'profile', 'settings']) }}"
                                        id="sidebarProfile">
                                        <ul class="nav nav-sm flex-column">
                                            <li class="nav-item">
                                                <a href="{{ route('activity.logs') }}"
                                                    class="nav-link {{ set_active(['activity.logs']) }}"
                                                    data-key="t-simple-page">Activity Logs</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="{{ route('settings') }}"
                                                    class="nav-link {{ set_active(['settings']) }}"
                                                    data-key="t-settings">Settings</a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            </ul>
                        </div>

                    </li>

                </ul>

            </div>
            <!-- Sidebar -->
        </div>
        <div class="sidebar-background"></div>
    </div>
