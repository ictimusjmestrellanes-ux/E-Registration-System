@extends('layouts.master')
@section('title', 'ERS | Users')
@section('content')
    @php $canEditRole = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); @endphp
    @php
        $activeUserFilters = request()->anyFilled(['search', 'role', 'status']);
    @endphp
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
                    <div class="card-body">
                        <div class="border rounded-4 p-3 mb-3" id="userFiltersCard">
                            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                                <div>
                                    <div class="fw-bold fs-5">Filter Users</div>
                                    <div class="text-muted small">Narrow users by name, role, and Active/Inactive
                                        status.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-primary client-filters-toggle-btn"
                                        id="userFiltersToggleBtn">
                                        Show Filters <i class="ri-arrow-down-s-line ms-1"></i>
                                    </button>
                                    @if ($activeUserFilters)
                                        <a href="{{ route('users.index') }}"
                                            class="btn btn-sm btn-soft-primary">Reset</a>
                                    @endif
                                </div>
                            </div>

                            <form method="GET" action="{{ route('users.index') }}" id="userFiltersForm"
                                class="{{ $activeUserFilters ? '' : 'd-none' }}">
                                <div class="row g-3 mt-1 align-items-end">
                                    <div class="col-12 col-xl-4">
                                        <label for="userKeywordInput"
                                            class="form-label fw-semibold text-uppercase small">Name Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                                            <input type="text" class="form-control" id="userKeywordInput"
                                                name="search" placeholder="Full name" value="{{ $search }}">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label for="userRoleFilter"
                                            class="form-label fw-semibold text-uppercase small">Role</label>
                                        <select class="form-select" id="userRoleFilter" name="role">
                                            <option value="">All roles</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected($selectedRole === $role)>
                                                    {{ $role }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label for="userStatusFilter"
                                            class="form-label fw-semibold text-uppercase small">Status</label>
                                        <select class="form-select" id="userStatusFilter" name="status">
                                            <option value="">All statuses</option>
                                            <option value="Active" @selected(($selectedStatus ?? '') === 'Active')>Active
                                            </option>
                                            <option value="Inactive" @selected(($selectedStatus ?? '') === 'Inactive')>Inactive
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-xl-2 d-flex gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-primary px-4">
                                            <i class="ri-filter-3-fill me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="small mt-2">
                                    {{ $activeUserFilters ? 'Filtered users are shown below.' : 'Showing all users.' }}
                                </div>
                            </form>
                        </div>
                    </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
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
                                                <span
                                                    class="badge bg-primary-subtle text-primary">{{ $user->role_name ?? '-' }}</span>
                                            </td>
                                            <td>
                                                @if ($user->status === 'Active')
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                @else
                                                    <span
                                                        class="badge bg-secondary-subtle text-secondary">{{ $user->status ?? 'Inactive' }}</span>
                                                @endif
                                            </td>
                                            <td>{{ ucfirst($user->auth_provider ?? 'local') }}</td>
                                            <td>{{ $user->last_login ?? '-' }}</td>
                                            @if ($canEditRole)
                                                <td class="text-center">
                                                    @if (feature_allowed('Edit User Roles'))
                                                        <button type="button" class="btn btn-sm btn-soft-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editRoleModal-{{ $user->id }}">
                                                            <i class="ri-edit-box-line align-bottom"></i> Edit Role
                                                        </button>
                                                    @endif

                                                    @if (feature_allowed('Update User Status'))
                                                        @if ($user->status === 'Active')
                                                            <form method="POST" class="d-inline"
                                                                action="{{ route('users.updateStatus', $user) }}"
                                                                onsubmit="return confirm('Deactivate {{ $user->name }}? This will prevent them from signing in.');">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="status" value="Inactive">
                                                                <button type="submit"
                                                                    class="btn btn-sm btn-soft-danger">
                                                                    <i class="ri-user-unfollow-line align-bottom"></i>
                                                                    Deactivate
                                                                </button>
                                                            </form>
                                                        @else
                                                            <form method="POST" class="d-inline"
                                                                action="{{ route('users.updateStatus', $user) }}">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="status" value="Active">
                                                                <button type="submit"
                                                                    class="btn btn-sm btn-soft-success">
                                                                    <i class="ri-user-follow-line align-bottom"></i>
                                                                    Activate
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>

                                    @empty
                                        <tr>
                                            <td colspan="{{ $canEditRole ? 6 : 5 }}" class="text-center text-muted py-5">No
                                                users found.</td>
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
                            <div class="modal fade" id="editRoleModal-{{ $user->id }}" tabindex="-1"
                                aria-hidden="true">
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
                                                    <label for="role_name-{{ $user->id }}"
                                                        class="form-label">Role</label>
                                                    <select class="form-select" id="role_name-{{ $user->id }}"
                                                        name="role_name">
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role }}"
                                                                @selected($user->role_name === $role)>{{ $role }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-light"
                                                    data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-primary">Update Role</button>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ----- Filter Users card toggle (Event Records style) -----
            const filtersToggleBtn = document.getElementById('userFiltersToggleBtn');
            const filtersForm = document.getElementById('userFiltersForm');

            const setFiltersVisible = (visible) => {
                if (!filtersForm || !filtersToggleBtn) return;
                filtersForm.classList.toggle('d-none', !visible);
                filtersToggleBtn.innerHTML = visible ?
                    'Hide Filters <i class="ri-arrow-up-s-line ms-1"></i>' :
                    'Show Filters <i class="ri-arrow-down-s-line ms-1"></i>';
            };

            filtersToggleBtn?.addEventListener('click', function() {
                setFiltersVisible(filtersForm.classList.contains('d-none'));
            });

            // Auto-expand when filters are active.
            @if ($activeUserFilters)
                setFiltersVisible(true);
            @endif
        });
    </script>
@endpush
