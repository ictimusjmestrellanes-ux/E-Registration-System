<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $roles = Role::orderBy('name')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => User::where('role_name', $role->name)->count(),
            ];
        });

        return view('pages.roles.index', [
            'roles' => $roles,
            'totalUsers' => User::count(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')],
        ]);

        $role = Role::create(['name' => $validated['name']]);

        foreach (Permission::select('feature')->distinct()->pluck('feature') as $feature) {
            Permission::create([
                'feature' => $feature,
                'role_name' => $role->name,
                'allowed' => false,
            ]);
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_created',
            'description' => "Created role \"{$role->name}\".",
            'subject_type' => 'Role',
            'subject_id' => $role->id,
            'properties' => json_encode(['role' => $role->name]),
        ]);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" created successfully.");
    }

    public function destroy(Role $role)
    {
        $authUser = auth()->user();
        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        $roleName = $role->name;

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_deleted',
            'description' => "Deleted role \"{$roleName}\".",
            'subject_type' => 'Role',
            'subject_id' => $role->id,
            'properties' => json_encode(['role' => $roleName]),
        ]);

        $role->delete();
        Permission::where('role_name', $roleName)->delete();

        return redirect()->route('roles.index')->with('success', "Role \"{$roleName}\" deleted successfully.");
    }
}