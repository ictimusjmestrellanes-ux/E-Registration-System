<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $roles = Role::query()->orderBy('name', 'asc')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => User::query()->where('role_name', $role->name)->count(),
            ];
        });

        return view('pages.roles.index', [
            'roles' => $roles,
            'totalUsers' => User::count(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = $this->authenticatedUser();
        if (! in_array($authUser->role_name, ['Admin', 'Super Admin'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')],
        ]);

        $role = Role::create(['name' => $validated['name']]);

        foreach (Permission::query()->select('feature')->distinct()->pluck('feature') as $feature) {
            Permission::create([
                'feature' => $feature,
                'role_name' => $role->name,
                'allowed' => false,
            ]);
        }

        ActivityLog::create([
            'user_id' => $authUser->id,
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
        $authUser = $this->authenticatedUser();
        if (! in_array($authUser->role_name, ['Admin', 'Super Admin'], true)) {
            abort(403);
        }

        $roleName = $role->name;

        ActivityLog::create([
            'user_id' => $authUser->id,
            'action' => 'role_deleted',
            'description' => "Deleted role \"{$roleName}\".",
            'subject_type' => 'Role',
            'subject_id' => $role->id,
            'properties' => json_encode(['role' => $roleName]),
        ]);

        Role::destroy($role->getKey());
        Permission::query()->where('role_name', $roleName)->get()->each->delete();

        return redirect()->route('roles.index')->with('success', "Role \"{$roleName}\" deleted successfully.");
    }

    private function authenticatedUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
