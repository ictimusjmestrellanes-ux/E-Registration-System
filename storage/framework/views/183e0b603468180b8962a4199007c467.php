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
            <a href="<?php echo e(route('dashboard')); ?>" class="logo logo-dark">
                <span class="logo-sm">
                    <img src="<?php echo e(asset('assets/images/City-Logo.png')); ?>" class="sidebar-brand-logo" alt="City Logo">
                </span>
                <span class="logo-lg">
                    <span class="sidebar-brand-content">
                        <img src="<?php echo e(asset('assets/images/City-Logo.png')); ?>" class="sidebar-brand-logo" alt="City Logo">
                        <span class="sidebar-brand-name fw-bold fs-6 text-white">
                            <span>E-Registration</span>
                            <span>Management System</span>
                        </span>
                    </span>
                </span>
            </a>
            <!-- Light Logo-->
            <a href="<?php echo e(route('dashboard')); ?>" class="logo logo-light">
                <span class="logo-sm">
                    <img src="<?php echo e(asset('assets/images/City-Logo.png')); ?>" class="sidebar-brand-logo" alt="City Logo">
                </span>
                <span class="logo-lg">
                    <span class="sidebar-brand-content">
                        <img src="<?php echo e(asset('assets/images/City-Logo.png')); ?>" class="sidebar-brand-logo" alt="City Logo">
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
                <h6 class="dropdown-header">Welcome <?php echo e(auth()->user()->name ?? 'User'); ?>!</h6>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?php echo e(route('profile')); ?>"><i
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

                    <?php
                        // Resolve each module's visibility once.
                        $showDashboard = feature_allowed_uri('dashboard');
                        $showCreateClient = feature_allowed_uri('clients');
                        $showClientList = feature_allowed_uri('client-list');
                        $showArchiveList = feature_allowed_uri('archive');
                        $showDuplicateReview = feature_allowed_uri('duplicate-review');
                        $showEvents = feature_allowed_uri('transaction-events');
                        $showEventRecords = feature_allowed_uri('transaction-events/records');
                        $showArchiveFiles = feature_allowed_uri('transaction-events/archives');
                        $canManage = !in_array(auth()->user()?->role_name, ['DSWD', 'Staff']);
                        $showUserManagement = $canManage && feature_allowed_uri('users');
                        $showActivityLogs = feature_allowed_uri('activity-logs');

                        // A section title only renders when it has at least one visible item.
                        $showMenuSection = $showDashboard;
                        $showClientsSection = $showCreateClient || $showClientList || $showArchiveList || $showDuplicateReview;
                        $showEventsSection = $showEvents || $showEventRecords || $showArchiveFiles;
                        $showSettingsSection = $showUserManagement || $showActivityLogs;
                    ?>

                    <?php if($showMenuSection): ?>
                    <li class="menu-title"><span data-key="t-menu">Menu</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['dashboard'])); ?>" href="<?php echo e(route('dashboard')); ?>">
                            <i class="ri-dashboard-2-line"></i> <span data-key="t-dashboard">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if($showClientsSection): ?>
                    <li class="menu-title"><span data-key="t-menu">Clients</span></li>

                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['clients', 'clients/*', 'client-list', 'client-list/*', 'archive', 'archive/*'])); ?>" href="#sidebarClients"
                            data-bs-toggle="collapse" role="button"
                            aria-expanded="<?php echo e(set_expanded(['clients', 'clients/*', 'client-list', 'client-list/*', 'archive', 'archive/*'])); ?>" aria-controls="sidebarClients">
                            <i class="ri-group-line"></i> <span data-key="t-clients">Clients</span>
                        </a>
                        <div class="collapse menu-dropdown <?php echo e(set_show(['clients', 'clients/*', 'client-list', 'client-list/*', 'archive', 'archive/*'])); ?>"
                            id="sidebarClients">
                            <ul class="nav nav-sm flex-column">
                                <?php if($showCreateClient): ?>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('clients')); ?>" class="nav-link <?php echo e(set_active(['clients'])); ?>"
                                        data-key="t-create-client">Create Client</a>
                                </li>
                                <?php endif; ?>
                                <?php if($showClientList): ?>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('client.list')); ?>"
                                        class="nav-link <?php echo e(set_active(['client-list', 'client-list/*', 'clients/*'])); ?>"
                                        data-key="t-client-list">Clients Management</a>
                                </li>
                                <?php endif; ?>
                                <?php if($showArchiveList): ?>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('archive.list')); ?>"
                                        class="nav-link <?php echo e(set_active(['archive', 'archive/*'])); ?>"
                                        data-key="t-archive-list">Archive Clients</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if($showDuplicateReview): ?>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['duplicate.review'])); ?>"
                            href="<?php echo e(route('duplicate.review')); ?>">
                            <i class="ri-file-copy-2-line"></i> <span data-key="t-duplicate-review">Duplicate Clients Review</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if($showEventsSection): ?>
                    <li class="menu-title"><span data-key="t-menu">Events</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['transaction-events', 'transaction-events/*'])); ?>"
                            href="#sidebarEvents" data-bs-toggle="collapse" role="button"
                            aria-expanded="<?php echo e(set_expanded(['transaction-events', 'transaction-events/*'])); ?>"
                            aria-controls="sidebarEvents">
                            <i class="ri-calendar-event-line"></i> <span>Events</span>
                        </a>
                        <div class="collapse menu-dropdown <?php echo e(set_show(['transaction-events', 'transaction-events/*'])); ?>"
                            id="sidebarEvents">
                            <ul class="nav nav-sm flex-column">
                                <?php if($showEvents): ?>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('transaction-events.index')); ?>"
                                        class="nav-link <?php echo e(set_active(['transaction-events', 'transaction-events/duplicate-review', 'transaction-events/removed-duplicates'])); ?>">Events Management</a>
                                </li>
                                <?php endif; ?>

                                <?php if($showEventRecords): ?>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('transaction-events.records')); ?>"
                                        class="nav-link <?php echo e(set_active(['transaction-events/records'])); ?>"
                                        data-key="t-events-records">Events Records</a>
                                </li>
                                <?php endif; ?>

                                <?php if($showArchiveFiles): ?>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('transaction-events.archives')); ?>"
                                        class="nav-link <?php echo e(set_active(['transaction-events/archives', 'transaction-events/archives/*'])); ?>"
                                        data-key="t-archive-files">View Archive Files</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>

                    <?php if($showSettingsSection): ?>
                    <li class="menu-title"><span data-key="t-menu">Settings</span></li>
                    <?php endif; ?>
                    <?php if($showUserManagement): ?>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['users.index', 'roles.index', 'permissions.index'])); ?>" href="#sidebarSettings"
                            data-bs-toggle="collapse" role="button"
                            aria-expanded="<?php echo e(set_expanded(['users.index', 'roles.index', 'permissions.index'])); ?>" aria-controls="sidebarSettings">
                            <i class="ri-settings-3-line"></i> <span data-key="t-settings">User Management</span>
                        </a>
                        <div class="collapse menu-dropdown <?php echo e(set_show(['users.index', 'roles.index', 'permissions.index'])); ?>"
                            id="sidebarSettings">
                            <ul class="nav nav-sm flex-column">
                                <li class="nav-item">
                                    <a href="<?php echo e(route('users.index')); ?>"
                                        class="nav-link <?php echo e(set_active(['users.index'])); ?>"
                                        data-key="t-users">Users</a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('roles.index')); ?>"
                                        class="nav-link <?php echo e(set_active(['roles.index'])); ?>"
                                        data-key="t-roles">Roles</a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo e(route('permissions.index')); ?>"
                                        class="nav-link <?php echo e(set_active(['permissions.index'])); ?>"
                                        data-key="t-permissions">Permissions</a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>

                    <?php if($showActivityLogs): ?>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['activity.logs'])); ?>"
                            href="<?php echo e(route('activity.logs')); ?>">
                            <i class="ri-history-line"></i> <span data-key="t-simple-page">Activity Logs</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="menu-title"><span data-key="t-menu">Pages</span></li>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?php echo e(set_active(['settings'])); ?>"
                            href="<?php echo e(route('settings')); ?>">
                            <i class="ri-user-line"></i> <span data-key="t-settings">Profile Page</span>
                        </a>
                    </li>

                </ul>

            </div>
            <!-- Sidebar -->
        </div>
        <div class="sidebar-background"></div>
    </div>
<?php /**PATH C:\xampp\htdocs\E-Reg-System\resources\views/layouts/sidebar.blade.php ENDPATH**/ ?>