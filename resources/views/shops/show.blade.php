@extends('layouts.app')

@section('title', 'Shop Details')

@section('content')
    <div class="row">
        <!-- Shop Profile -->
        <div class="col-lg-8">
            <!-- Shop Header Card -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-4">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-lg">
                                <div class="avatar-title bg-primary-subtle text-primary rounded fs-1">
                                    {{ substr($shop->name, 0, 1) }}
                                </div>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h4 class="mb-1">{{ $shop->name }}</h4>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-secondary-subtle text-secondary">{{ $shop->code }}</span>
                                @if ($shop->status->value === 'active')
                                    <span class="badge bg-success-subtle text-success">
                                        <iconify-icon icon="solar:check-circle-bold" class="align-middle"></iconify-icon>
                                        Active
                                    </span>
                                @elseif($shop->status->value === 'inactive')
                                    <span class="badge bg-warning-subtle text-warning">
                                        <iconify-icon icon="solar:pause-circle-bold" class="align-middle"></iconify-icon>
                                        Inactive
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">
                                        <iconify-icon icon="solar:close-circle-bold" class="align-middle"></iconify-icon>
                                        Suspended
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @can('update', $shop)
                                <a href="{{ route('shops.edit', $shop) }}" class="btn btn-primary">
                                    <iconify-icon icon="solar:pen-linear" class="align-middle me-1"></iconify-icon>
                                    Edit Shop
                                </a>
                            @endcan
                        </div>
                    </div>

                    @if ($shop->description)
                        <div class="alert alert-info border-info">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-2">
                                    <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-20"></iconify-icon>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-0">{{ $shop->description }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Shop Details -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:document-text-bold-duotone"
                            class="align-middle text-primary me-2"></iconify-icon>
                        Shop Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Location Information -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">
                                <iconify-icon icon="solar:map-point-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Location
                            </h6>
                            @if ($shop->address || $shop->city || $shop->country)
                                <div class="mb-2">
                                    @if ($shop->address)
                                        <p class="mb-1">{{ $shop->address }}</p>
                                    @endif
                                    @if ($shop->city || $shop->state || $shop->postal_code)
                                        <p class="mb-1">
                                            {{ implode(', ', array_filter([$shop->city, $shop->state, $shop->postal_code])) }}
                                        </p>
                                    @endif
                                    @if ($shop->country)
                                        <p class="mb-0"><strong>{{ $shop->country }}</strong></p>
                                    @endif
                                </div>
                                @if ($shop->latitude && $shop->longitude)
                                    <div class="text-muted">
                                        <small>
                                            <iconify-icon icon="solar:compass-bold" class="align-middle"></iconify-icon>
                                            {{ $shop->latitude }}, {{ $shop->longitude }}
                                        </small>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted mb-0">No location information available</p>
                            @endif
                        </div>

                        <!-- Contact Information -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">
                                <iconify-icon icon="solar:phone-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Contact
                            </h6>
                            @if ($shop->phone || $shop->email)
                                <div class="vstack gap-2">
                                    @if ($shop->phone)
                                        <div>
                                            <iconify-icon icon="solar:phone-calling-linear"
                                                class="align-middle text-primary me-2"></iconify-icon>
                                            <a href="tel:{{ $shop->phone }}" class="text-body">{{ $shop->phone }}</a>
                                        </div>
                                    @endif
                                    @if ($shop->email)
                                        <div>
                                            <iconify-icon icon="solar:letter-linear"
                                                class="align-middle text-primary me-2"></iconify-icon>
                                            <a href="mailto:{{ $shop->email }}" class="text-body">{{ $shop->email }}</a>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-muted mb-0">No contact information available</p>
                            @endif
                        </div>

                        <!-- Manager Information -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">
                                <iconify-icon icon="solar:user-id-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Shop Manager
                            </h6>
                            @if ($shop->manager)
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="avatar-sm">
                                            <div class="avatar-title bg-info-subtle text-info rounded-circle">
                                                {{ substr($shop->manager->name, 0, 1) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">{{ $shop->manager->name }}</h6>
                                        <p class="text-muted mb-0">{{ $shop->manager->email }}</p>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted mb-0">No manager assigned</p>
                            @endif
                        </div>

                        <!-- Metadata -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">
                                <iconify-icon icon="solar:clock-circle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Metadata
                            </h6>
                            <div class="vstack gap-2">
                                <div>
                                    <small class="text-muted">Created:</small>
                                    <p class="mb-0">{{ $shop->created_at->format('M d, Y \a\t h:i A') }}</p>
                                </div>
                                <div>
                                    <small class="text-muted">Last Updated:</small>
                                    <p class="mb-0">{{ $shop->updated_at->diffForHumans() }}</p>
                                </div>
                                @if ($shop->creator)
                                    <div>
                                        <small class="text-muted">Created By:</small>
                                        <p class="mb-0">{{ $shop->creator?->name ?? __('Deleted user') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- E-commerce Integrations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:link-bold-duotone" class="align-middle text-info me-2"></iconify-icon>
                        E-commerce Integrations
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $enabledIntegrations = $shop->getEnabledIntegrations();
                    @endphp
                    @if (count($enabledIntegrations) > 0)
                        <div class="row g-3">
                            @if ($shop->isIntegrationEnabled('woocommerce'))
                                @php
                                    $wcStatus = $shop->getConnectionStatus('woocommerce');
                                    $wcConfig = $shop->getIntegrationConfig('woocommerce');
                                @endphp
                                <div class="col-md-6">
                                    <div
                                        class="card border border-{{ $wcStatus['connected'] ? 'success' : 'secondary' }} mb-0">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar-sm">
                                                        <span
                                                            class="avatar-title bg-{{ $wcStatus['connected'] ? 'success' : 'secondary' }}-subtle rounded">
                                                            <iconify-icon icon="simple-icons:woocommerce"
                                                                class="text-{{ $wcStatus['connected'] ? 'success' : 'secondary' }} fs-20"></iconify-icon>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">WooCommerce</h6>
                                                    <span
                                                        class="badge bg-{{ $wcStatus['connected'] ? 'success' : 'secondary' }}-subtle text-{{ $wcStatus['connected'] ? 'success' : 'secondary' }} mt-1">
                                                        <iconify-icon
                                                            icon="solar:{{ $wcStatus['connected'] ? 'check-circle-bold' : 'close-circle-bold' }}"
                                                            class="align-middle"></iconify-icon>
                                                        {{ $wcStatus['connected'] ? 'Connected' : 'Not Tested' }}
                                                    </span>
                                                </div>
                                            </div>
                                            @if (!empty($wcConfig['store_url']))
                                                <p class="text-muted mb-2">
                                                    <small><iconify-icon icon="solar:link-linear"
                                                            class="align-middle"></iconify-icon>
                                                        {{ $wcConfig['store_url'] }}</small>
                                                </p>
                                            @endif
                                            @if ($wcStatus['connected'] && $wcStatus['last_tested_at'])
                                                <p class="text-muted mb-0">
                                                    <small>Last tested:
                                                        {{ \Carbon\Carbon::parse($wcStatus['last_tested_at'])->diffForHumans() }}</small>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($shop->isIntegrationEnabled('shopify'))
                                @php
                                    $shopifyStatus = $shop->getConnectionStatus('shopify');
                                    $shopifyConfig = $shop->getIntegrationConfig('shopify');
                                @endphp
                                <div class="col-md-6">
                                    <div
                                        class="card border border-{{ $shopifyStatus['connected'] ? 'success' : 'secondary' }} mb-0">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar-sm">
                                                        <span
                                                            class="avatar-title bg-{{ $shopifyStatus['connected'] ? 'success' : 'secondary' }}-subtle rounded">
                                                            <iconify-icon icon="simple-icons:shopify"
                                                                class="text-{{ $shopifyStatus['connected'] ? 'success' : 'secondary' }} fs-20"></iconify-icon>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">Shopify</h6>
                                                    <span
                                                        class="badge bg-{{ $shopifyStatus['connected'] ? 'success' : 'secondary' }}-subtle text-{{ $shopifyStatus['connected'] ? 'success' : 'secondary' }} mt-1">
                                                        <iconify-icon
                                                            icon="solar:{{ $shopifyStatus['connected'] ? 'check-circle-bold' : 'close-circle-bold' }}"
                                                            class="align-middle"></iconify-icon>
                                                        {{ $shopifyStatus['connected'] ? 'Connected' : 'Not Tested' }}
                                                    </span>
                                                </div>
                                            </div>
                                            @if (!empty($shopifyConfig['shop_domain']))
                                                <p class="text-muted mb-2">
                                                    <small><iconify-icon icon="solar:link-linear"
                                                            class="align-middle"></iconify-icon>
                                                        {{ $shopifyConfig['shop_domain'] }}</small>
                                                </p>
                                            @endif
                                            @if ($shopifyStatus['connected'] && $shopifyStatus['last_tested_at'])
                                                <p class="text-muted mb-0">
                                                    <small>Last tested:
                                                        {{ \Carbon\Carbon::parse($shopifyStatus['last_tested_at'])->diffForHumans() }}</small>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-4">
                            <iconify-icon icon="solar:link-broken-bold-duotone" class="text-muted"
                                style="font-size: 3rem; opacity: 0.5;"></iconify-icon>
                            <p class="text-muted mb-0 mt-2">No integrations configured for this shop.</p>
                            @can('update', $shop)
                                <a href="{{ route('shops.edit', $shop) }}" class="btn btn-sm btn-soft-primary mt-2">
                                    <iconify-icon icon="solar:settings-linear" class="align-middle me-1"></iconify-icon>
                                    Configure Integrations
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>

            <!-- Assigned Users -->
            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <iconify-icon icon="solar:users-group-rounded-bold-duotone"
                                    class="align-middle text-success me-2"></iconify-icon>
                                Assigned Staff ({{ $shop->users->count() }})
                            </h5>
                        </div>
                        <div class="col-auto">
                            @can('update', $shop)
                                <a href="{{ route('shops.users', $shop) }}" class="btn btn-sm btn-soft-primary">
                                    <iconify-icon icon="solar:user-plus-linear" class="align-middle me-1"></iconify-icon>
                                    Manage Users
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if ($shop->users->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Added On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($shop->users as $user)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0 me-2">
                                                        <div class="avatar-xxs">
                                                            <div
                                                                class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                {{ substr($user->name, 0, 1) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>{{ $user->name }}</div>
                                                </div>
                                            </td>
                                            <td>{{ $user->email }}</td>
                                            <td>
                                                @if ($user->roles->count() > 0)
                                                    @foreach ($user->roles as $role)
                                                        <span
                                                            class="badge bg-info-subtle text-info">{{ $role->name }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-muted">{{ $user->pivot->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <iconify-icon icon="solar:user-cross-bold-duotone" class="text-muted"
                                style="font-size: 3rem; opacity: 0.5;"></iconify-icon>
                            <p class="text-muted mb-0 mt-2">No staff assigned to this shop yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title text-white mb-0">
                        <iconify-icon icon="solar:chart-2-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Quick Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="vstack gap-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Staff</p>
                                <h4 class="mb-0">{{ $shop->users->count() }}</h4>
                            </div>
                            <div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-info-subtle rounded">
                                        <iconify-icon icon="solar:users-group-rounded-bold-duotone"
                                            class="text-info fs-22"></iconify-icon>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Products</p>
                                <h4 class="mb-0">0</h4>
                            </div>
                            <div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-success-subtle rounded">
                                        <iconify-icon icon="solar:box-bold-duotone"
                                            class="text-success fs-22"></iconify-icon>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Sales Today</p>
                                <h4 class="mb-0">0</h4>
                            </div>
                            <div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-warning-subtle rounded">
                                        <iconify-icon icon="solar:cart-large-2-bold-duotone"
                                            class="text-warning fs-22"></iconify-icon>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:bolt-bold-duotone"
                            class="align-middle text-warning me-2"></iconify-icon>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('update', $shop)
                            <a href="{{ route('shops.edit', $shop) }}" class="btn btn-primary">
                                <iconify-icon icon="solar:pen-linear" class="align-middle me-1"></iconify-icon>
                                Edit Shop Details
                            </a>
                            <a href="{{ route('shops.users', $shop) }}" class="btn btn-soft-info">
                                <iconify-icon icon="solar:user-plus-linear" class="align-middle me-1"></iconify-icon>
                                Manage Staff
                            </a>
                        @endcan

                        @if ($shop->status->value === 'inactive' || $shop->status->value === 'suspended')
                            @can('activate', $shop)
                                <form action="{{ route('shops.activate', $shop) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <iconify-icon icon="solar:check-circle-bold" class="align-middle me-1"></iconify-icon>
                                        Activate Shop
                                    </button>
                                </form>
                            @endcan
                        @endif

                        @if ($shop->status->value === 'active')
                            @can('deactivate', $shop)
                                <form action="{{ route('shops.deactivate', $shop) }}" method="POST"
                                    onsubmit="return confirm('Deactivate this shop?')">
                                    @csrf
                                    <button type="submit" class="btn btn-warning w-100">
                                        <iconify-icon icon="solar:pause-circle-bold" class="align-middle me-1"></iconify-icon>
                                        Deactivate Shop
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="card border-secondary border-opacity-25">
                <div class="card-header bg-secondary-subtle">
                    <h6 class="card-title mb-0 text-secondary">
                        <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                        System Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">UUID:</td>
                                    <td class="text-end">
                                        <code class="fs-11">{{ Str::limit($shop->uuid, 20, '...') }}</code>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Created:</td>
                                    <td class="text-end">{{ $shop->created_at->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Last Updated:</td>
                                    <td class="text-end">{{ $shop->updated_at->format('Y-m-d') }}</td>
                                </tr>
                                @if ($shop->deleted_at)
                                    <tr>
                                        <td class="text-muted">Deleted:</td>
                                        <td class="text-end text-danger">{{ $shop->deleted_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
