@extends('layouts.app')

@section('title', 'User Profile')

@section('content')
    <div class="row">
        <!-- User Info Card -->
        <div class="col-xl-4">
            <x-ui-card>
                <div class="text-center">
                    <img src="{{ $user->avatar ?? asset('assets/images/users/avatar-1.jpg') }}" alt="{{ $user->name }}"
                        class="avatar-xl rounded-circle mb-3">

                    <h4 class="mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-3">{{ $user->email }}</p>

                    @foreach ($user->roles as $role)
                        <x-ui-badge variant="info" soft class="mb-2">{{ $role->name }}</x-ui-badge>
                    @endforeach

                    <div class="mt-3">
                        <x-ui-badge :variant="$user->is_active ? 'success' : 'danger'">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </x-ui-badge>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-center">
                        @can('users.edit')
                            <x-ui-button variant="primary" size="sm" href="{{ route('users.edit', $user) }}"
                                icon="solar:pen-2-broken">
                                Edit Profile
                            </x-ui-button>
                        @endcan

                        @can('users.destroy')
                            <x-ui-button variant="danger" size="sm" data-bs-toggle="modal"
                                data-bs-target="#deleteUserModal">
                                Delete
                            </x-ui-button>
                        @endcan
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <h5 class="mb-3">Contact Information</h5>

                    <div class="d-flex align-items-center mb-2">
                        <i class="bx bx-envelope text-muted fs-18 me-2"></i>
                        <span>{{ $user->email }}</span>
                    </div>

                    @if ($user->phone)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bx bx-phone text-muted fs-18 me-2"></i>
                            <span>{{ $user->phone }}</span>
                        </div>
                    @endif

                    <div class="d-flex align-items-center">
                        <i class="bx bx-calendar text-muted fs-18 me-2"></i>
                        <span>Joined {{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </x-ui-card>
        </div>

        <!-- Activity & Stats -->
        <div class="col-xl-8">
            <!-- Stats Cards -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <x-ui-card class="text-center">
                        <div class="avatar-md bg-soft-primary rounded mx-auto mb-3">
                            <iconify-icon icon="solar:cart-5-bold-duotone"
                                class="avatar-title fs-32 text-primary"></iconify-icon>
                        </div>
                        <h4 class="mb-0">{{ $salesCount ?? '0' }}</h4>
                        <p class="text-muted mb-0">Total Sales</p>
                    </x-ui-card>
                </div>

                <div class="col-md-4">
                    <x-ui-card class="text-center">
                        <div class="avatar-md bg-soft-success rounded mx-auto mb-3">
                            <iconify-icon icon="solar:dollar-minimalistic-bold-duotone"
                                class="avatar-title fs-32 text-success"></iconify-icon>
                        </div>
                        <h4 class="mb-0">{{ format_currency($totalRevenue ?? 0) }}</h4>
                        <p class="text-muted mb-0">Revenue</p>
                    </x-ui-card>
                </div>

                <div class="col-md-4">
                    <x-ui-card class="text-center">
                        <div class="avatar-md bg-soft-warning rounded mx-auto mb-3">
                            <iconify-icon icon="solar:box-bold-duotone"
                                class="avatar-title fs-32 text-warning"></iconify-icon>
                        </div>
                        <h4 class="mb-0">{{ $productsManaged ?? '0' }}</h4>
                        <p class="text-muted mb-0">Products</p>
                    </x-ui-card>
                </div>
            </div>

            <!-- User Details -->
            <x-ui-card title="User Details" class="mb-3">
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="fw-medium" style="width: 200px;">Full Name:</td>
                                <td>{{ $user->name }}</td>
                            </tr>
                            <tr>
                                <td class="fw-medium">Email:</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                            @if ($user->phone)
                                <tr>
                                    <td class="fw-medium">Phone:</td>
                                    <td>{{ $user->phone }}</td>
                                </tr>
                            @endif
                            @if ($user->gender)
                                <tr>
                                    <td class="fw-medium">Gender:</td>
                                    <td class="text-capitalize">{{ $user->gender }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="fw-medium">Role:</td>
                                <td>
                                    @foreach ($user->roles as $role)
                                        <x-ui-badge variant="info" soft>{{ $role->name }}</x-ui-badge>
                                    @endforeach
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">Shop Access:</td>
                                <td>
                                    @forelse ($user->shops as $shop)
                                        <x-ui-badge variant="secondary" soft>{{ $shop->name }}</x-ui-badge>
                                    @empty
                                        <x-ui-badge variant="info" soft>All Shops</x-ui-badge>
                                    @endforelse
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">Status:</td>
                                <td>
                                    <x-ui-badge :variant="$user->is_active ? 'success' : 'danger'">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </x-ui-badge>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-medium">Member Since:</td>
                                <td>{{ $user->created_at->format('F d, Y') }}</td>
                            </tr>
                            <tr>
                                <td class="fw-medium">Last Updated:</td>
                                <td>{{ $user->updated_at->diffForHumans() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui-card>

            <!-- Recent Activity -->
            <x-ui-card title="Recent Activity">
                @if (isset($recentActivities) && count($recentActivities) > 0)
                    <div class="activity-timeline">
                        @foreach ($recentActivities as $activity)
                            <div class="activity-item d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                                            <i class="bx bx-check"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">{{ $activity->description }}</h6>
                                    <p class="text-muted mb-0">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-ui-empty-state icon="solar:history-broken" title="No recent activity"
                        message="Activity history will appear here" />
                @endif
            </x-ui-card>
        </div>
    </div>

    @can('users.destroy')
        @push('modals')
            <!-- Delete Confirmation Modal -->
            <x-ui-modal id="deleteUserModal" title="Confirm Delete" centered>
                <x-slot:body>
                    <p class="mb-0">Are you sure you want to delete <strong>{{ $user->name }}</strong>? This action cannot
                        be undone.</p>
                </x-slot:body>

                <x-slot:footer>
                    <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <x-ui-button variant="danger" type="submit">
                            Delete User
                        </x-ui-button>
                    </form>
                </x-slot:footer>
            </x-ui-modal>
        @endpush
    @endcan
@endsection
