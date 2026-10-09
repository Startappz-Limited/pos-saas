@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    <div class="row">
        <div class="col-xl-10 col-lg-11 mx-auto">
            <form action="{{ route('roles.update', $role) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Role Information -->
                <x-ui-card title="Role Information" class="mb-3">
                    @if ($role->is_system)
                        <x-ui-alert variant="warning" class="mb-3">
                            <i class="bx bx-error me-1"></i>
                            This is a system role. Some fields may be restricted.
                        </x-ui-alert>
                    @endif

                    <div class="row">
                        <div class="col-lg-6">
                            <x-ui-form-input name="name" label="Role Name" :value="old('name', $role->name)"
                                placeholder="e.g., Store Manager" required :readonly="$role->is_system" />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-input name="display_name" label="Display Name" :value="old('display_name', $role->display_name)"
                                placeholder="e.g., Store Manager" help="Human-readable name for the role" />
                        </div>

                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3"
                                    placeholder="Describe the role's purpose and responsibilities">{{ old('description', $role->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                    value="1" {{ old('is_active', $role->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                </x-ui-card>

                <!-- Permissions -->
                <x-ui-card title="Assign Permissions" class="mb-3">
                    <x-ui-alert variant="info" class="mb-3">
                        <i class="bx bx-info-circle me-1"></i>
                        Select the permissions this role should have. Users with this role will inherit all selected
                        permissions.
                    </x-ui-alert>

                    <x-ui-permission-grid :permissions="$permissions" :selectedPermissions="old('permissions', $role->permissions->pluck('id')->toArray())" groupBy="module" />
                </x-ui-card>

                <!-- Role Statistics -->
                <x-ui-card title="Role Statistics" class="mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-1">{{ $role->users->count() }}</h3>
                                <p class="text-muted mb-0">Users Assigned</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-1">{{ $role->permissions->count() }}</h3>
                                <p class="text-muted mb-0">Permissions</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-1">{{ $role->created_at->diffForHumans() }}</h3>
                                <p class="text-muted mb-0">Created</p>
                            </div>
                        </div>
                    </div>
                </x-ui-card>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <x-ui-button variant="primary" type="submit" icon="solar:check-circle-broken">
                        Update Role
                    </x-ui-button>
                    <x-ui-button variant="secondary" href="{{ route('roles.index') }}">
                        Cancel
                    </x-ui-button>
                    @if (!$role->is_system)
                        @can('roles.destroy')
                            <x-ui-button variant="danger" data-bs-toggle="modal" data-bs-target="#deleteRoleModal"
                                class="float-end">
                                Delete Role
                            </x-ui-button>
                        @endcan
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if (!$role->is_system)
        @can('roles.destroy')
            @push('modals')
                <x-ui-modal id="deleteRoleModal" title="Confirm Delete" centered>
                    <x-slot:body>
                        <p class="mb-3">Are you sure you want to delete the <strong>{{ $role->name }}</strong> role?</p>
                        @if ($role->users->count() > 0)
                            <x-ui-alert variant="warning">
                                <strong>Warning:</strong> This role is assigned to {{ $role->users->count() }} user(s). Deleting
                                it will remove this role from all assigned users.
                            </x-ui-alert>
                        @endif
                    </x-slot:body>

                    <x-slot:footer>
                        <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
                        <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <x-ui-button variant="danger" type="submit">
                                Delete Role
                            </x-ui-button>
                        </form>
                    </x-slot:footer>
                </x-ui-modal>
            @endpush
        @endcan
    @endif
@endsection
