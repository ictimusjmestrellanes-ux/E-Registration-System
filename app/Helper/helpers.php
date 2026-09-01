<?php

/** Check whether the current request matches a sidebar URI pattern. */
function sidebar_item_matches($routes): bool
{
    foreach ((array) $routes as $route) {
        if (Request::is($route)) {
            return true;
        }
    }

    return false;
}

/** Set active class for sidebar menu. */
function set_active($routes)
{
    return sidebar_item_matches($routes) ? 'active' : '';
}

/** Determine if sidebar dropdown should be expanded. */
function set_expanded($routes)
{
    return sidebar_item_matches($routes) ? 'true' : 'false';
}

/** Set show class for sidebar dropdown. */
function set_show($routes)
{
    return sidebar_item_matches($routes) ? 'show' : '';
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
        'Create Client' => ['clients', 'clients/create'],
        'Client List' => ['client-list'],
        'Archive' => ['archive'],
        'Events' => ['transaction-events'],
        'Delete Event' => ['transaction-events/*/delete'],
        'Event Records' => ['transaction-events/records'],
        'Events Records Duplicates' => ['transaction-events/records/duplicates'],
        'View Archive Files' => ['transaction-events/archives'],
        'Duplicate Clients Review' => ['duplicate-review'],
        'Events - Duplicate Review' => ['transaction-events/duplicate-review'],
        'Manage Users' => ['users', 'users/*', 'roles', 'permissions'],
        'Roles' => ['roles'],
        'Add Roles' => ['roles/add'],
        'Delete Roles' => ['roles/*/delete'],
        'Permissions' => ['permissions'],
        'Save Permissions' => ['permissions/save'],
        'Add Permissions' => ['permissions/add'],
        'Delete Permissions' => ['permissions/delete'],
        'Activity Logs' => ['activity-logs'],
        'Send Transactions' => ['transactions', 'transactions/*', 'transaction-requirements/*'],
        'Profile Page' => ['settings'],
        'Import CSV' => ['transaction-events/import*'],
        'Download Template' => ['transaction-events/template'],
        'Edit User Roles' => ['users/*/role'],
        'Update User Status' => ['users/*/status'],
        'Transfer Selected' => ['transaction-events/transfer-selected'],
        'Archive Clients' => ['clients/*/archive', 'clients/*/delete'],
        'Edit Client' => ['clients/*/edit', 'clients/*/update'],
        'View Client' => ['clients/*'],
        'Restore Archive' => ['archive/*/restore'],
        'View Removed Duplicates' => ['transaction-events/removed-duplicates'],
        'Reset Duplicate Review' => ['transaction-events/*/reset-duplicate'],
        'Download Archive' => ['transaction-events/archives/*'],
        'Undo Transfer' => ['transaction-events/*/undo-transfer'],
        'Transfer Event' => ['transaction-events/*/transfer'],
        'Mark Not Duplicate' => ['transaction-events/*/not-duplicate'],
        'Duplicate Review' => ['transaction-events/duplicate-review'],
        'Preview Import' => ['transaction-events/import/prepare'],
    ];
}

/** Resolve the most specific feature governing a URL path, or null. */
function resolve_feature_for_path(string $path): ?string
{
    $best = null;
    $bestLength = -1;
    $normalizedPath = trim($path, '/');

    foreach (feature_route_map() as $feature => $patterns) {
        foreach ($patterns as $pattern) {
            $normalizedPattern = trim($pattern, '/');

            // Patterns containing * act as wildcards (matching across segments).
            $matches = str_contains($normalizedPattern, '*')
                ? preg_match('#^'.str_replace('\*', '.*', preg_quote($normalizedPattern, '#')).'$#', $normalizedPath) === 1
                : $normalizedPattern === $normalizedPath;

            // Later map entries win ties so specific features
            // (Roles/Permissions) override the broad Manage Users entry.
            if ($matches && strlen($normalizedPattern) >= $bestLength) {
                $best = $feature;
                $bestLength = strlen($normalizedPattern);
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
