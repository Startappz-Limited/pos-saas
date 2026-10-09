@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <div class="row">
        <div class="col-12">
            <x-ui-card title="All Roles">
                <x-slot:headerActions>
                    @can('roles.create')
                        <x-ui-button variant="primary" icon="solar:shield-plus-broken" href="{{ route('roles.create') }}">
                            Add Role
                        </x-ui-button>
                    @endcan
                </x-slot:headerActions>

                <x-ui-table>
                    <x-slot:header>
                        <tr>
                            <th>Role Name</th>
                            <th>Users</th>
                            <th>Permissions</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </x-slot:header>

                    @forelse($roles ?? [] as $role)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-sm bg-soft-primary rounded">
                                        <iconify-icon icon="solar:shield-user-bold-duotone"
                                            class="avatar-title text-primary fs-20"></iconify-icon>
                                    </div>
                                    <div>
                                        <a href="{{ route('roles.show', $role) }}" class="text-dark fw-medium">
                                            {{ $role->name }}
                                        </a>
                                        @if ($role->isGlobal())
                                            <x-ui-badge variant="secondary">{{ __('System') }}</x-ui-badge>
                                        @elseif (auth()->user()->isSuperAdmin())
                                            <x-ui-badge variant="info">{{ $role->business?->name }}</x-ui-badge>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <x-ui-badge variant="secondary">{{ $role->users_count ?? 0 }}</x-ui-badge>
                            </td>
                            <td>
                                <x-ui-badge variant="info" soft>{{ $role->permissions->count() }}</x-ui-badge>
                            </td>
                            <td>{{ $role->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    @can('view', $role)
                                        <a href="{{ route('roles.show', $role) }}" class="btn btn-sm btn-soft-info">
                                            <i class="bx bx-show"></i>
                                        </a>
                                    @endcan
                                    @can('update', $role)
                                        <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-soft-primary">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                    @endcan
                                    @if (! $role->isSystemRole())
                                        @can('delete', $role)
                                            <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this role?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-soft-danger">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-ui-empty-state icon="solar:shield-user-broken" title="No roles found"
                                    message="Get started by adding your first role" actionText="Add Role"
                                    actionUrl="{{ route('roles.create') }}" actionIcon="solar:shield-plus-broken" />
                            </td>
                        </tr>
                    @endforelse
                </x-ui-table>

                @if (isset($roles) && $roles->hasPages())
                    <div class="mt-3">
                        {{ $roles->links() }}
                    </div>
                @endif
            </x-ui-card>
        </div>
    </div>
@endsection
