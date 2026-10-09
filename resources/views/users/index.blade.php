@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="row">
        <!-- Stats Cards -->
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Total Users" value="{{ $totalUsers ?? '0' }}" icon="solar:users-group-rounded-bold-duotone"
                trend="+5.2%" trendDirection="up" trendLabel="This Month" />
        </div>

        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Active Users" value="{{ $activeUsers ?? '0' }}"
                icon="solar:user-check-rounded-bold-duotone" trend="+3.1%" trendDirection="up" trendLabel="This Week" />
        </div>

        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="New This Month" value="{{ $newUsers ?? '0' }}"
                icon="solar:user-plus-rounded-bold-duotone" trend="+12%" trendDirection="up" trendLabel="Last Month" />
        </div>

        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Inactive Users" value="{{ $inactiveUsers ?? '0' }}"
                icon="solar:user-block-rounded-bold-duotone" trend="-1.5%" trendDirection="down" trendLabel="This Week" />
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <x-ui-card title="All Users">
                <x-slot:headerActions>
                    @can('users.create')
                        <x-ui-button variant="primary" icon="solar:user-plus-broken" href="{{ route('users.create') }}">
                            Add User
                        </x-ui-button>
                    @endcan

                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button"
                            data-bs-toggle="dropdown">
                            Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bx bx-download me-1"></i> Download
                                    CSV</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bx bx-file me-1"></i> Export PDF</a>
                            </li>
                        </ul>
                    </div>
                </x-slot:headerActions>

                <x-ui-table>
                    <x-slot:header>
                        <tr>
                            <th style="width: 20px;">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                    <label class="form-check-label" for="selectAll"></label>
                                </div>
                            </th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </x-slot:header>

                    @forelse($users ?? [] as $user)
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="check{{ $user->id }}">
                                    <label class="form-check-label" for="check{{ $user->id }}"></label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ $user->avatar ?? asset('assets/images/users/avatar-1.jpg') }}"
                                        alt="{{ $user->name }}" class="avatar-sm rounded-circle">
                                    <div>
                                        <a href="{{ route('users.show', $user) }}" class="text-dark fw-medium">
                                            {{ $user->name }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <x-ui-badge variant="info" soft>{{ $role->name }}</x-ui-badge>
                                @endforeach
                            </td>
                            <td>
                                <x-ui-badge :variant="$user->is_active ? 'success' : 'danger'">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </x-ui-badge>
                            </td>
                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                            <td>
                                <x-ui-action-buttons :model="$user" route="users" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-ui-empty-state icon="solar:users-group-rounded-broken" title="No users found"
                                    message="Get started by adding your first user" actionText="Add User"
                                    actionUrl="{{ route('users.create') }}" actionIcon="solar:user-plus-broken" />
                            </td>
                        </tr>
                    @endforelse
                </x-ui-table>

                @if (isset($users) && $users->hasPages())
                    <div class="mt-3">
                        {{ $users->links() }}
                    </div>
                @endif
            </x-ui-card>
        </div>
    </div>
@endsection
