@extends('layouts.master')
@section('title', 'Users')
@section('content')
@php $canEditRole = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Users</h4>
                                <p class="text-muted mb-0">Manage system accounts and roles.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    value="{{ $search }}" placeholder="NAME, EMAIL">
                            </div>
                            <div class="col-md-4">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All roles</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected($selectedRole === $role)>{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="ri-search-line align-bottom me-1"></i> Filter
                                </button>
                                <a href="{{ route('users.index') }}" class="btn btn-soft-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Provider</th>
                                        <th>Last Login</th>
                                        @if ($canEditRole)
                                            <th class="text-center">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($users as $user)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name ?? 'User' }}"
                                                        class="rounded-circle avatar-xs object-fit-cover me-2">
                                                    <div>
                                                        <h6 class="mb-0">{{ strtoupper($user->name ?? 'User') }}</h6>
                                                        <p class="text-muted mb-0">{{ strtoupper($user->email) }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary">{{ $user->role_name ?? '-' }}</span>
                                            </td>
                                            <td>
                                                @if ($user->status === 'Active')
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary">{{ $user->status ?? 'Inactive' }}</span>
                                                @endif
                                            </td>
                                            <td>{{ ucfirst($user->auth_provider ?? 'local') }}</td>
                                            <td>{{ $user->last_login ?? '-' }}</td>
                                            @if ($canEditRole)
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-warning" data-bs-toggle="modal"
                                                        data-bs-target="#editRoleModal-{{ $user->id }}">
                                                        <i class="ri-edit-box-line align-bottom"></i> Edit Role
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>

                                    @empty
                                        <tr>
                                            <td colspan="{{ $canEditRole ? 6 : 5 }}" class="text-center text-muted py-5">No users found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $users->links() }}
                        </div>
                    </div>

                    @if ($canEditRole)
                        @foreach ($users as $user)
                            <div class="modal fade" id="editRoleModal-{{ $user->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('users.updateRole', $user) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Role - {{ $user->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="role_name-{{ $user->id }}" class="form-label">Role</label>
                                                    <select class="form-select" id="role_name-{{ $user->id }}" name="role_name">
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role }}" @selected($user->role_name === $role)>{{ $role }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Role</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection
