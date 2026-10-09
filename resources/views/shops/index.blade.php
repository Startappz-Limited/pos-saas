@extends('layouts.app')

@section('title', 'Shops')

@section('content')
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Total Shops</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['total'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-primary-subtle rounded fs-3">
                                    <iconify-icon icon="solar:shop-2-bold-duotone" class="text-primary"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Active Shops</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['active'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="text-success"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Inactive Shops</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['inactive'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-warning-subtle rounded fs-3">
                                    <iconify-icon icon="solar:pause-circle-bold-duotone"
                                        class="text-warning"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Suspended Shops</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['suspended'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-danger-subtle rounded fs-3">
                                    <iconify-icon icon="solar:close-circle-bold-duotone" class="text-danger"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shops List Card -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">All Shops</h5>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        @can('create', App\Models\Shop::class)
                            <a href="{{ route('shops.create') }}" class="btn btn-primary add-btn">
                                <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon> Add
                                Shop
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Search and Filters -->
            <form method="GET" action="{{ route('shops.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-4 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="Search by name, code, or location..." value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-sm-6">
                        <select class="form-select" name="status" id="status-filter">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive
                            </option>
                            <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspended
                            </option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon> Filter
                        </button>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <a href="{{ route('shops.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon> Reset
                        </a>
                    </div>
                </div>
            </form>

            @if ($shops->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Shop</th>
                                <th scope="col">Code</th>
                                <th scope="col">Location</th>
                                <th scope="col">Manager</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Integrations</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shops as $shop)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-2">
                                                <div class="avatar-xs">
                                                    <div
                                                        class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16">
                                                        {{ substr($shop->name, 0, 1) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0">
                                                    <a href="{{ route('shops.show', $shop) }}"
                                                        class="text-body">{{ $shop->name }}</a>
                                                </h6>
                                                @if ($shop->description)
                                                    <small
                                                        class="text-muted">{{ Str::limit($shop->description, 40) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary">{{ $shop->code }}</span>
                                    </td>
                                    <td>
                                        @if ($shop->city || $shop->country)
                                            <div>
                                                @if ($shop->city)
                                                    <span class="text-body">{{ $shop->city }}</span>
                                                @endif
                                                @if ($shop->country)
                                                    <small class="text-muted d-block">{{ $shop->country }}</small>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($shop->manager)
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    <div class="avatar-xxs">
                                                        <div class="avatar-title bg-info-subtle text-info rounded-circle">
                                                            {{ substr($shop->manager->name, 0, 1) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>{{ $shop->manager->name }}</div>
                                            </div>
                                        @else
                                            <span class="text-muted">Not assigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($shop->phone || $shop->email)
                                            <div class="text-muted">
                                                @if ($shop->phone)
                                                    <div><iconify-icon icon="solar:phone-linear"
                                                            class="align-middle"></iconify-icon> {{ $shop->phone }}</div>
                                                @endif
                                                @if ($shop->email)
                                                    <div><iconify-icon icon="solar:letter-linear"
                                                            class="align-middle"></iconify-icon> {{ $shop->email }}</div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $enabledIntegrations = $shop->getEnabledIntegrations();
                                        @endphp
                                        @if (count($enabledIntegrations) > 0)
                                            <div class="d-flex gap-1 flex-wrap">
                                                @foreach ($enabledIntegrations as $platform)
                                                    @php
                                                        $status = $shop->getConnectionStatus($platform);
                                                    @endphp
                                                    @if ($platform === 'woocommerce')
                                                        <span
                                                            class="badge {{ $status['connected'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}"
                                                            data-bs-toggle="tooltip"
                                                            title="WooCommerce {{ $status['connected'] ? '(Connected)' : '(Not tested)' }}">
                                                            <iconify-icon icon="simple-icons:woocommerce"
                                                                class="align-middle"></iconify-icon> WC
                                                        </span>
                                                    @elseif ($platform === 'shopify')
                                                        <span
                                                            class="badge {{ $status['connected'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}"
                                                            data-bs-toggle="tooltip"
                                                            title="Shopify {{ $status['connected'] ? '(Connected)' : '(Not tested)' }}">
                                                            <iconify-icon icon="simple-icons:shopify"
                                                                class="align-middle"></iconify-icon> Shopify
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($shop->status->value === 'active')
                                            <span class="badge bg-success-subtle text-success">
                                                <iconify-icon icon="solar:check-circle-bold"
                                                    class="align-middle"></iconify-icon> Active
                                            </span>
                                        @elseif($shop->status->value === 'inactive')
                                            <span class="badge bg-warning-subtle text-warning">
                                                <iconify-icon icon="solar:pause-circle-bold"
                                                    class="align-middle"></iconify-icon> Inactive
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">
                                                <iconify-icon icon="solar:close-circle-bold"
                                                    class="align-middle"></iconify-icon> Suspended
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            @can('view', $shop)
                                                <a href="{{ route('shops.show', $shop) }}" class="btn btn-sm btn-soft-info">
                                                    <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('update', $shop)
                                                <a href="{{ route('shops.edit', $shop) }}"
                                                    class="btn btn-sm btn-soft-primary">
                                                    <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('delete', $shop)
                                                <button type="button" class="btn btn-sm btn-soft-danger"
                                                    onclick="confirmDelete('{{ $shop->uuid }}')">
                                                    <iconify-icon icon="solar:trash-bin-minimalistic-linear"></iconify-icon>
                                                </button>
                                                <form id="delete-form-{{ $shop->uuid }}"
                                                    action="{{ route('shops.destroy', $shop) }}" method="POST"
                                                    class="d-none">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $shops->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Shops Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('search') ? 'Try adjusting your search or filters.' : 'Start by creating your first shop.' }}
                        </p>
                        @can('create', App\Models\Shop::class)
                            <a href="{{ route('shops.create') }}" class="btn btn-primary mt-3">
                                <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                                Add Shop
                            </a>
                        @endcan
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            function confirmDelete(uuid) {
                if (confirm('Are you sure you want to delete this shop? This action cannot be undone.')) {
                    document.getElementById('delete-form-' + uuid).submit();
                }
            }
        </script>
    @endpush
@endsection
