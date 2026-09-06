<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionsController extends Controller
{
    private const DEFAULT_PERMISSIONS = [
        ['feature' => 'Dashboard', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Create Client', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Client List', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Archive', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Events', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Delete Event', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Manage Users', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Activity Logs', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Send Transactions', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Event Records', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Events Records Duplicates', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'View Archive Files', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Import CSV', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Download Template', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Edit User Roles', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Update User Status', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Add Roles', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Delete Roles', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Transfer Selected', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Archive Clients', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Edit Client', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'View Client', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Restore Archive', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'View Removed Duplicates', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Reset Duplicate Review', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Download Archive', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Save Permissions', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Delete Permissions', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Add Permissions', 'DSWD' => false, 'Staff' => false, 'Admin' => false, 'Super Admin' => true],
        ['feature' => 'Undo Transfer', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Transfer Event', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Mark Not Duplicate', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Duplicate Review', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Preview Import', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Roles', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Permissions', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Duplicate Clients Review', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
    ];

    /**
     * A brand-new role starts with every feature denied, except these which
     * default to allowed. New users land on the Viewer role, so keep the
     * dashboard visible out of the box.
     */
    private const VIEWER_DEFAULT_FEATURES = ['Dashboard'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->seedDefaultsIfEmpty();
        $this->syncMissingFeatureRows();

        $rows = Permission::all();
        $roles = Role::orderBy('name')->pluck('name')->all() ?: User::ROLES;
        $permissions = $rows->groupBy('feature')->map(function ($items) {
            $row = ['feature' => $items->first()->feature];
            foreach ($items as $item) {
                $row[$item->role_name] = $item->allowed;
            }
            return $row;
        })->values();

        return view('pages.permissions.index', [
            'permissions' => $permissions,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request)
    {
        $authUser = auth()->user();
        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        $this->seedDefaultsIfEmpty();
        $this->syncMissingFeatureRows();

        $features = Permission::select('feature')->distinct()->pluck('feature');

        $allowedMap = $request->input('allowed', []);
        $changed = [];

        foreach ($features as $feature) {
            foreach (Role::pluck('name')->all() ?: User::ROLES as $role) {
                $allowed = !empty($allowedMap[$feature][$role]);
                $existing = Permission::where('feature', $feature)->where('role_name', $role)->first();

                if ($existing && (bool) $existing->allowed !== $allowed) {
                    $changed[] = "{$role}:{$feature}";
                }

                Permission::updateOrCreate(
                    ['feature' => $feature, 'role_name' => $role],
                    ['allowed' => $allowed]
                );
            }
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'permissions_updated',
            'description' => 'Updated permission matrix for ' . $features->count() . ' feature(s).',
            'subject_type' => 'Permission',
            'subject_id' => null,
            'properties' => json_encode(['changed' => $changed]),
        ]);

        return redirect()->route('permissions.index')->with('success', 'Permissions updated successfully.');
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'feature' => ['required', 'string', 'max:100', Rule::unique('permissions', 'feature')],
        ]);

        foreach (Role::pluck('name')->all() ?: User::ROLES as $role) {
            Permission::create([
                'feature' => $validated['feature'],
                'role_name' => $role,
                'allowed' => false,
            ]);
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'permission_created',
            'description' => "Added permission \"{$validated['feature']}\".",
            'subject_type' => 'Permission',
            'subject_id' => null,
            'properties' => json_encode(['feature' => $validated['feature']]),
        ]);

        return redirect()->route('permissions.index')->with('success', "Permission \"{$validated['feature']}\" added successfully.");
    }

    public function destroy(Request $request)
    {
        $authUser = auth()->user();
        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'feature' => ['required', 'string', 'max:100'],
        ]);

        Permission::where('feature', $validated['feature'])->delete();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'permission_deleted',
            'description' => "Deleted permission \"{$validated['feature']}\".",
            'subject_type' => 'Permission',
            'subject_id' => null,
            'properties' => json_encode(['feature' => $validated['feature']]),
        ]);

        return redirect()->route('permissions.index')->with('success', "Permission \"{$validated['feature']}\" deleted successfully.");
    }

    private function seedDefaultsIfEmpty()
    {
        if (Permission::exists()) {
            return;
        }

        $roles = Role::pluck('name')->all() ?: User::ROLES;

        foreach (self::DEFAULT_PERMISSIONS as $row) {
            foreach ($roles as $role) {
                Permission::create([
                    'feature' => $row['feature'],
                    'role_name' => $role,
                    'allowed' => $row[$role] ?? in_array($row['feature'], self::VIEWER_DEFAULT_FEATURES, true),
                ]);
            }
        }
    }

    /**
     * Self-healing matrix: every known feature gets a row for every role.
     * Features never registered (or roles created before a feature existed)
     * used to fall through to open-by-default access — this closes that gap.
     * Unknown roles default to disallowed.
     */
    private function syncMissingFeatureRows(): void
    {
        $defaults = [];
        foreach (self::DEFAULT_PERMISSIONS as $row) {
            $defaults[$row['feature']] = $row;
        }

        $features = array_unique(array_merge(
            array_keys($defaults),
            Permission::select('feature')->distinct()->pluck('feature')->all()
        ));
        $roles = Role::pluck('name')->all() ?: User::ROLES;

        foreach ($features as $feature) {
            foreach ($roles as $role) {
                $exists = Permission::where('feature', $feature)
                    ->where('role_name', $role)
                    ->exists();

                if (! $exists) {
                    Permission::create([
                        'feature' => $feature,
                        'role_name' => $role,
                        'allowed' => $defaults[$feature][$role] ?? in_array($feature, self::VIEWER_DEFAULT_FEATURES, true),
                    ]);
                }
            }
        }
    }
}