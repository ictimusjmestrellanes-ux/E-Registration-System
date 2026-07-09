<?php

namespace App\Http\Controllers;

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
            'roles' => User::ROLES,
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
            'role_name' => ['required', 'string', Rule::in(User::ROLES)],
        ]);

        $user->update(['role_name' => $validated['role_name']]);

        return redirect()->route('users.index')->with('success', "User role updated to {$validated['role_name']} successfully.");
    }
}
