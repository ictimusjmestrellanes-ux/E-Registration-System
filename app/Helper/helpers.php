<?php

/** Set active class for sidebar menu */
function set_active($routes)
{
    $current = Request::path();

    if (is_array($routes)) {
        return in_array($current, $routes) ? 'active' : '';
    }

    return $current === $routes ? 'active' : '';
}

/** Determine if sidebar dropdown should be expanded */
function set_expanded($routes)
{
    $current = Request::path();

    foreach ((array) $routes as $route) {
        if ($current === $route) {
            return 'true';
        }
    }

    return 'false';
}

/** Set show class for sidebar dropdown */
function set_show($routes)
{
    $current = Request::path();

    if (is_array($routes)) {
        return in_array($current, $routes) ? 'show' : '';
    }

    return $current === $routes ? 'show' : '';
}

/**
 * Check the permission matrix for the logged-in user's role.
 * Features that are not registered in the matrix remain accessible.
 */
function feature_allowed(string $feature): bool
{
    $role = auth()->user()->role_name ?? null;

    if ($role === null) {
        return false;
    }

    $permission = \App\Models\Permission::where('feature', $feature)
        ->where('role_name', $role)
        ->first();

    if ($permission === null) {
        return true;
    }

    return (bool) $permission->allowed;
}

/**
 * Maps permission features to URL paths. Adding a permission whose name
 * appears here automatically gates those pages via EnsureFeatureAllowed.
 * Patterns ending in * match path prefixes.
 */
function feature_route_map(): array
{
    return [
        'Dashboard' => ['dashboard'],
        'Create Client' => ['clients'],
        'Client List' => ['client-list'],
        'Archive' => ['archive'],
        'Events' => ['transaction-events'],
        'Event Records' => ['transaction-events/records'],
        'View Archive Files' => ['transaction-events/archives'],
        'Duplicate Clients Review' => ['duplicate-review'],
        'Manage Users' => ['users', 'users/*', 'roles', 'permissions'],
        'Roles' => ['roles', 'roles/*'],
        'Permissions' => ['permissions', 'permissions/*'],
        'Activity Logs' => ['activity-logs'],
        'Send Transactions' => ['transactions', 'transactions/*', 'transaction-requirements/*'],
        'Profile Page' => ['settings'],
    ];
}

/** Resolve the most specific feature governing a URL path, or null. */
function resolve_feature_for_path(string $path): ?string
{
    $best = null;
    $bestLength = -1;

    foreach (feature_route_map() as $feature => $patterns) {
        foreach ($patterns as $pattern) {
            $matches = str_ends_with($pattern, '*')
                ? str_starts_with($path, rtrim($pattern, '*'))
                : $pattern === $path;

            // Later map entries win ties so specific features
            // (Roles/Permissions) override the broad Manage Users entry.
            if ($matches && strlen($pattern) >= $bestLength) {
                $best = $feature;
                $bestLength = strlen($pattern);
            }
        }
    }

    return $best;
}

/** Sidebar helper: is a URI accessible for the current user's role? */
function feature_allowed_uri(string $uri): bool
{
    $feature = resolve_feature_for_path(trim($uri, '/'));

    if ($feature === null) {
        return true;
    }

    return feature_allowed($feature);
}