@extends('layouts.app')

@section('title', 'Role Details')

@section('content')
    <div class="row">
        <!-- Role Info Card -->
        <div class="col-xl-4">
            <x-ui-card>
                <div class="text-center">
                    <div class="avatar-xl bg-soft-primary rounded mx-auto mb-3">
                        <iconify-icon icon="solar:shield-user-bold-duotone" class="fs-48 text-primary"></iconify-icon>
                    </div>

                    <h4 class="mb-1">{{ $role->name }}</h4>
                    @if ($role->display_name)
                        <p class="text-muted mb-2">{{ $role->display_name }}</p>
                    @endif

                    <div class="mt-3">
                        @if ($role->isGlobal())
                            <x-ui-badge variant="warning">{{ __('System Role') }}</x-ui-badge>
                        @else
                            <x-ui-badge variant="secondary">{{ __('Business Role') }}</x-ui-badge>
                        @endif
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-center">
                        @can('update', $role)
                            <x-ui-button variant="primary" size="sm" href="{{ route('roles.edit', $role) }}"
                                icon="solar:pen-2-broken">
                                Edit Role
                            </x-ui-button>
                        @endcan

                        @if (!$role->isSystemRole())
                            @can('delete', $role)
                                <x-ui-button variant="danger" size="sm" data-bs-toggle="modal"
                                    data-bs-target="#deleteRoleModal">
                                    Delete
                                </x-ui-button>
                            @endcan
                        @endif
                    </div>
                </div>

                @if ($role->description)
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="mb-2">Description</h6>
                        <p class="text-muted mb-0">{{ $role->description }}</p>
                    </div>
                @endif

                <div class="mt-4 pt-3 border-top">
                    <h6 class="mb-3">Information</h6>

                    <div class="d-flex align-items-center mb-2">
                        <i class="bx bx-time text-muted fs-18 me-2"></i>
                        <span>Created {{ $role->created_at->format('M d, Y') }}</span>
                    </div>

                    <div class="d-flex align-items-center">
                        <i class="bx bx-calendar text-muted fs-18 me-2"></i>
                        <span>Updated {{ $role->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </x-ui-card>
        </div>

        <!-- Stats & Details -->
        <div class="col-xl-8">
            <!-- Stats Cards -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <x-ui-card class="text-center">
                        <div class="avatar-md bg-soft-primary rounded mx-auto mb-3">
                            <iconify-icon icon="solar:users-group-rounded-bold-duotone"
                                class="avatar-title fs-32 text-primary"></iconify-icon>
                        </div>
                        <h4 class="mb-0">{{ $role->users->count() }}</h4>
                        <p class="text-muted mb-0">Users Assigned</p>
                    </x-ui-card>
                </div>

                <div class="col-md-4">
                    <x-ui-card class="text-center">
                        <div class="avatar-md bg-soft-success rounded mx-auto mb-3">
                            <iconify-icon icon="solar:shield-keyhole-bold-duotone"
                                class="avatar-title fs-32 text-success"></iconify-icon>
                        </div>
                        <h4 class="mb-0">{{ $role->permissions->count() }}</h4>
                        <p class="text-muted mb-0">Permissions</p>
                    </x-ui-card>
                </div>

                <div class="col-md-4">
                    <x-ui-card class="text-center">
                        <div class="avatar-md bg-soft-info rounded mx-auto mb-3">
                            <iconify-icon icon="solar:check-circle-bold-duotone"
                                class="avatar-title fs-32 text-info"></iconify-icon>
                        </div>
                        <h4 class="mb-0">{{ $role->isGlobal() ? __('All businesses') : ($role->business?->name ?? __('Business')) }}</h4>
                        <p class="text-muted mb-0">{{ __('Applies to') }}</p>
                    </x-ui-card>
                </div>
            </div>

            <!-- Permissions List -->
            <x-ui-card title="Permissions" class="mb-3">
                @if ($role->permissions->count() > 0)
                    @php
                        $groupedPermissions = $role->permissions->groupBy('module');
                    @endphp

                    @foreach ($groupedPermissions as $module => $permissions)
                        <div class="mb-4">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">{{ ucfirst($module) }}</h6>
                            <div class="row g-2">
                                @foreach ($permissions as $permission)
                                    <div class="col-lg-4 col-md-6">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            <span>{{ $permission->name }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <x-ui-empty-state icon="solar:shield-keyhole-broken" title="No permissions assigned"
                        message="This role has no permissions assigned yet" />
                @endif
            </x-ui-card>

            <!-- Assigned Users -->
            <x-ui-card title="Assigned Users">
                @if ($role->users->count() > 0)
                    <x-ui-table hover>
                        <x-slot:header>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Joined</th>
                            </tr>
                        </x-slot:header>

                        {{-- Rows go in the default slot: ui-table renders $slot, not a body slot --}}
                            @foreach ($role->users as $user)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="{{ $user->profile_photo_url }}"
                                                alt="{{ $user->name }}" class="avatar-sm rounded-circle me-2">
                                            <div>
                                                <h6 class="mb-0">{{ $user->name }}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <x-ui-badge :variant="$user->isActive() ? 'success' : 'danger'">
                                            {{ $user->isActive() ? __('Active') : __('Inactive') }}
                                        </x-ui-badge>
                                    </td>
                                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                    </x-ui-table>
                @else
                    <x-ui-empty-state icon="solar:users-group-rounded-broken" title="No users assigned"
                        message="No users have been assigned this role yet" />
                @endif
            </x-ui-card>
        </div>
    </div>

    @if (!$role->isSystemRole())
        @can('delete', $role)
            @push('modals')
                <x-ui-modal id="deleteRoleModal" title="Confirm Delete" centered>
                    <x-slot:body>
                        <p class="mb-3">Are you sure you want to delete the <strong>{{ $role->name }}</strong> role?</p>
                        @if ($role->users->count() > 0)
                            <x-ui-alert variant="warning">
                                <strong>Warning:</strong> This role is assigned to {{ $role->users->count() }} user(s).
                                Deleting it will remove this role from all assigned users.
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
