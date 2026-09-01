@extends('layouts.master')
@section('title', 'ERS | Roles')
@section('content')
    @php $canManage = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Roles</h4>
                                <p class="text-muted mb-0">System roles and their assigned users.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary-subtle text-primary fs-13">
                                    {{ $totalUsers }} Total User(s)
                                </span>
                                @if ($canManage)
                                    @if (feature_allowed('Add Roles'))
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#addRoleModal">
                                            <i class="ri-add-line align-bottom me-1"></i> Add Role
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-primary" disabled>
                                            <i class="ri-add-line align-bottom me-1"></i>Not Allowed to Add Role
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            @foreach ($roles as $role)
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-0">{{ $role['name'] }}</h5>
                                    <p class="text-muted mb-0">Role</p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($canManage)
                                        @if (feature_allowed('Delete Roles'))
                                            <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteRoleModal-{{ $role['id'] }}" title="Delete Role">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-soft-danger" disabled title="Not Allowed to Delete Role">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        @endif
                                    @endif
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-primary-subtle text-primary rounded fs-4">
                                            <i class="ri-shield-user-line"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted">Assigned Users</span>
                                <h4 class="mb-0">{{ $role['users_count'] }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('roles.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Role</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="roleName" class="form-label">Role Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="roleName" name="name" placeholder="e.g. Encoder" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($roles as $role)
            <div class="modal fade" id="deleteRoleModal-{{ $role['id'] }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('roles.destroy', $role['id']) }}">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h5 class="modal-title">Delete Role</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Are you sure you want to delete the role
                                    <strong>{{ $role['name'] }}</strong>? Its permissions will also be removed.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
