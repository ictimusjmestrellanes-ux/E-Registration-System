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
        ['feature' => 'Events', 'DSWD' => true, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Manage Users', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Activity Logs', 'DSWD' => false, 'Staff' => false, 'Admin' => true, 'Super Admin' => true],
        ['feature' => 'Send Transactions', 'DSWD' => false, 'Staff' => true, 'Admin' => true, 'Super Admin' => true],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->seedDefaultsIfEmpty();

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
                    'allowed' => $row[$role] ?? false,
                ]);
            }
        }
    }
}