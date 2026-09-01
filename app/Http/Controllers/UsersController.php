<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role_name', $role))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $totalUsers = User::count();
        $activeUsers = User::where('status', 'Active')->count();

        return view('pages.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name')->all() ?: User::ROLES,
            'selectedRole' => $role,
            'search' => $search,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $authUser = auth()->user();

        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'role_name' => ['required', 'string', Rule::in(Role::pluck('name')->all() ?: User::ROLES)],
        ]);

        $oldRole = $user->role_name;
        $user->update(['role_name' => $validated['role_name']]);

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_role_updated',
            'description' => "Changed role of {$user->name} from {$oldRole} to {$validated['role_name']}.",
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'properties' => json_encode(['old_role' => $oldRole, 'new_role' => $validated['role_name']]),
        ]);

        return redirect()->route('users.index')->with('success', "User role updated to {$validated['role_name']} successfully.");
    }

    public function updateStatus(Request $request, User $user)
    {
        $authUser = auth()->user();

        if (!in_array($authUser->role_name, ['Admin', 'Super Admin'])) {
            abort(403);
        }

        if ($user->id === $authUser->id) {
            return redirect()->route('users.index')->with('error', 'You cannot deactivate your own account.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Active,Inactive'],
        ]);

        // Prevent deactivating the last active Super Admin.
        if ($validated['status'] === 'Inactive' && $user->role_name === User::ROLE_SUPER_ADMIN) {
            $activeSuperAdmins = User::where('role_name', User::ROLE_SUPER_ADMIN)
                ->where('status', 'Active')
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSuperAdmins === 0) {
                return redirect()->route('users.index')
                    ->with('error', 'Cannot deactivate the only active Super Admin account.');
            }
        }

        $oldStatus = $user->status ?: 'Inactive';
        $user->update(['status' => $validated['status']]);

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_status_updated',
            'description' => "Changed status of {$user->name} from {$oldStatus} to {$validated['status']}.",
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'properties' => json_encode(['old_status' => $oldStatus, 'new_status' => $validated['status']]),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User status updated to {$validated['status']} successfully.");
    }
}
