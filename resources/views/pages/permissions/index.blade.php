@extends('layouts.master')
@section('title', 'Permissions')
@section('content')
@php $canEdit = in_array(auth()->user()?->role_name, ['Admin', 'Super Admin']); @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">Permissions</h4>
                                <p class="text-muted mb-0">Feature access matrix per role.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @if ($canEdit)
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#addPermissionModal">
                                        <i class="ri-add-line align-bottom me-1"></i> Add Permission
                                    </button>
                                    <button type="submit" form="permissionsForm" class="btn btn-success">
                                        <i class="ri-save-line align-bottom me-1"></i> Save Changes
                                    </button>
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
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @if ($canEdit)
                            <form method="POST" action="{{ route('permissions.update') }}" id="permissionsForm">
                                @csrf
                                @method('PUT')
                        @endif
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Feature</th>
                                        @foreach ($roles as $role)
                                            <th class="text-center">{{ $role }}</th>
                                        @endforeach
                                        @if ($canEdit)
                                            <th class="text-center">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($permissions as $permission)
                                        <tr>
                                            <td class="fw-medium">{{ $permission['feature'] }}</td>
                                            @foreach ($roles as $role)
                                                <td class="text-center">
                                                    @if ($canEdit)
                                                        <div class="form-check form-switch form-switch-lg d-inline-block mb-0">
                                                            <input type="checkbox" class="form-check-input" id="perm-{{ $permission['feature'] }}-{{ $role }}"
                                                                name="allowed[{{ $permission['feature'] }}][{{ $role }}]"
                                                                @checked($permission[$role] ?? false)>
                                                            <label class="form-check-label" for="perm-{{ $permission['feature'] }}-{{ $role }}"></label>
                                                        </div>
                                                    @else
                                                        @if ($permission[$role] ?? false)
                                                            <span class="badge bg-success-subtle text-success"><i class="ri-check-line align-bottom"></i> Allowed</span>
                                                        @else
                                                            <span class="badge bg-danger-subtle text-danger"><i class="ri-close-line align-bottom"></i> Denied</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endforeach
                                            @if ($canEdit)
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deletePermissionModal-{{ str_replace(' ', '-', $permission['feature']) }}"
                                                        title="Delete Permission">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($canEdit)
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canEdit)
        <div class="modal fade" id="addPermissionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('permissions.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Permission</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="featureName" class="form-label">Feature Name</label>
                                <input type="text" class="form-control @error('feature') is-invalid @enderror"
                                    id="featureName" name="feature" placeholder="e.g. Reports" required>
                                @error('feature')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Permission</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($permissions as $permission)
            <div class="modal fade" id="deletePermissionModal-{{ str_replace(' ', '-', $permission['feature']) }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('permissions.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="feature" value="{{ $permission['feature'] }}">
                            <div class="modal-header">
                                <h5 class="modal-title">Delete Permission</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Are you sure you want to delete the permission
                                    <strong>{{ $permission['feature'] }}</strong>? It will be removed for all roles.</p>
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