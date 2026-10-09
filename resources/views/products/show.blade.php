@extends('layouts.app')

@section('title', 'Product Details')

@section('content')
    @php
        // Cost and the profit margin derived from it are gated together — hiding
        // one while showing the other would leak the cost by subtraction.
        $canViewCost = auth()->user()?->can('viewCost', \App\Models\Product::class) ?? false;
    @endphp

    <div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                @if ($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}"
                                        class="img-fluid rounded bg-light">
                                @else
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                        style="height: 300px;">
                                        <iconify-icon icon="solar:box-bold-duotone" class="fs-1 text-muted"></iconify-icon>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-8">
                                <h3 class="mb-1">{{ $product->name }}</h3>
                                <div class="d-flex gap-2 mb-3">
                                    <span
                                        class="badge bg-{{ $product->status->color() }}-subtle text-{{ $product->status->color() }}">{{ $product->status->label() }}</span>
                                    @if ($product->category)
                                        <span class="badge bg-info-subtle text-info">{{ $product->category->name }}</span>
                                    @endif
                                    @if ($product->stock_quantity <= 0)
                                        <span class="badge bg-danger-subtle text-danger">Out of Stock</span>
                                    @elseif($product->stock_quantity <= $product->low_stock_threshold)
                                        <span class="badge bg-warning-subtle text-warning">Low Stock</span>
                                    @endif
                                </div>

                                @if ($product->description)
                                    <div class="mb-3">
                                        <p class="text-muted">{{ $product->description }}</p>
                                    </div>
                                @endif

                                <h5 class="text-dark fw-medium">Price:</h5>
                                <h4 class="fw-semibold text-primary mb-3">
                                    {{ format_currency($product->selling_price) }}
                                    @if ($canViewCost && $product->cost_price)
                                        <small class="text-muted">(Cost:
                                            {{ format_currency($product->cost_price) }})</small>
                                    @endif
                                </h4>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <p class="text-muted mb-1">SKU:</p>
                                        <p class="fw-medium"><code>{{ $product->sku }}</code></p>
                                    </div>
                                    @if ($product->barcode)
                                        <div class="col-6">
                                            <p class="text-muted mb-1">Barcode:</p>
                                            <p class="fw-medium"><code>{{ $product->barcode }}</code></p>
                                        </div>
                                    @endif
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Stock:</p>
                                        <p class="fw-medium">{{ $product->stock_quantity }} units</p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Low Stock Alert:</p>
                                        <p class="fw-medium">{{ $product->low_stock_threshold }} units</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Product Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row" style="width: 200px;">Name:</th>
                                        <td>{{ $product->name }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">SKU:</th>
                                        <td><code>{{ $product->sku }}</code></td>
                                    </tr>
                                    @if ($product->barcode)
                                        <tr>
                                            <th scope="row">Barcode:</th>
                                            <td><code>{{ $product->barcode }}</code></td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th scope="row">Category:</th>
                                        <td>
                                            @if ($product->category)
                                                <a href="{{ route('categories.show', $product->category) }}">
                                                    {{ $product->category->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Supplier:</th>
                                        <td>
                                            @if ($product->supplier)
                                                <a href="{{ route('suppliers.show', $product->supplier) }}">
                                                    {{ $product->supplier->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($canViewCost)
                                        <tr>
                                            <th scope="row">Cost Price:</th>
                                            <td>
                                                @if ($product->cost_price !== null)
                                                    {{ format_currency($product->cost_price) }}
                                                @else
                                                    <span class="text-muted">{{ __('Not set') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th scope="row">Selling Price:</th>
                                        <td class="fw-medium text-primary">{{ format_currency($product->selling_price) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Stock Quantity:</th>
                                        <td>{{ $product->stock_quantity }} units</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Low Stock Threshold:</th>
                                        <td>{{ $product->low_stock_threshold }} units</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Status:</th>
                                        <td>
                                            @if ($product->is_active)
                                                <span class="badge bg-success-subtle text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if ($product->shops->count() > 0)
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Shop Stock Levels</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Shop</th>
                                            <th class="text-center">Stock</th>
                                            <th class="text-center">Reorder</th>
                                            <th class="text-end">Price Override</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($product->shops as $shop)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a>
                                                </td>
                                                <td class="text-center">
                                                    @if ($shop->pivot->stock_quantity <= 0)
                                                        <span
                                                            class="badge bg-danger-subtle text-danger">{{ $shop->pivot->stock_quantity }}</span>
                                                    @elseif ($shop->pivot->stock_quantity <= $shop->pivot->reorder_level)
                                                        <span
                                                            class="badge bg-warning-subtle text-warning">{{ $shop->pivot->stock_quantity }}</span>
                                                    @else
                                                        <span
                                                            class="badge bg-success-subtle text-success">{{ $shop->pivot->stock_quantity }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $shop->pivot->reorder_level }}</td>
                                                <td class="text-end">
                                                    @if ($shop->pivot->selling_price)
                                                        {{ format_currency($shop->pivot->selling_price) }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($shop->pivot->is_active)
                                                        <span class="badge bg-success-subtle text-success">Active</span>
                                                    @else
                                                        <span
                                                            class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- E-commerce Sync Status -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:refresh-bold-duotone"
                                class="align-middle text-info me-2"></iconify-icon>
                            E-commerce Sync Status
                        </h5>
                    </div>
                    <div class="card-body">
                        @php
                            $syncRecords = $product->ecommerceSyncs()->with('shop')->get();
                            $shopsWithIntegration = \App\Models\Shop::active()
                                ->get()
                                ->filter(fn($shop) => $shop->hasAnyEcommerceIntegration());
                        @endphp
                        @if ($syncRecords->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Shop</th>
                                            <th>Platform</th>
                                            <th>Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($syncRecords as $sync)
                                            <tr>
                                                <td>
                                                    <a
                                                        href="{{ route('shops.show', $sync->shop) }}">{{ $sync->shop->name }}</a>
                                                </td>
                                                <td>
                                                    @if ($sync->platform === 'woocommerce')
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <iconify-icon icon="simple-icons:woocommerce"
                                                                class="align-middle"></iconify-icon> WooCommerce
                                                        </span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">
                                                            <iconify-icon icon="simple-icons:shopify"
                                                                class="align-middle"></iconify-icon> Shopify
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($sync->isSynced())
                                                        <span class="badge bg-success-subtle text-success">
                                                            <iconify-icon icon="solar:check-circle-bold"
                                                                class="align-middle"></iconify-icon> Synced
                                                        </span>
                                                        @if ($sync->last_synced_at)
                                                            <small
                                                                class="text-muted d-block">{{ $sync->last_synced_at->diffForHumans() }}</small>
                                                        @endif
                                                    @elseif ($sync->hasError())
                                                        <span class="badge bg-danger-subtle text-danger"
                                                            data-bs-toggle="tooltip"
                                                            title="{{ $sync->sync_error ?? 'Unknown error' }}">
                                                            <iconify-icon icon="solar:close-circle-bold"
                                                                class="align-middle"></iconify-icon> Error
                                                        </span>
                                                    @elseif ($sync->isPending())
                                                        <span class="badge bg-warning-subtle text-warning">
                                                            <iconify-icon icon="solar:clock-circle-bold"
                                                                class="align-middle"></iconify-icon> Pending
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary">
                                                            <iconify-icon icon="solar:question-circle-bold"
                                                                class="align-middle"></iconify-icon> Out of Sync
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <form action="{{ route('products.sync.single', $product) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="shop_id"
                                                            value="{{ $sync->shop_id }}">
                                                        <input type="hidden" name="platform"
                                                            value="{{ $sync->platform }}">
                                                        <button type="submit" class="btn btn-sm btn-soft-info"
                                                            data-bs-toggle="tooltip" title="Sync now">
                                                            <iconify-icon icon="solar:refresh-linear"></iconify-icon>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-3">
                                <iconify-icon icon="solar:refresh-broken" class="text-muted"
                                    style="font-size: 2.5rem; opacity: 0.5;"></iconify-icon>
                                <p class="text-muted mb-0 mt-2">This product has not been synced to any e-commerce platform
                                    yet.</p>
                                @if ($shopsWithIntegration->count() > 0)
                                    <button type="button" class="btn btn-sm btn-soft-primary mt-2"
                                        data-bs-toggle="modal" data-bs-target="#syncSingleProductModal">
                                        <iconify-icon icon="solar:refresh-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Sync to Platform
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        @can('update', $product)
                            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:pen-2-broken" class="me-1"></iconify-icon>
                                Edit Product
                            </a>
                        @endcan

                        @if ($product->is_active)
                            @can('update', $product)
                                <form action="{{ route('products.deactivate', $product) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-warning w-100">
                                        <iconify-icon icon="solar:pause-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Deactivate
                                    </button>
                                </form>
                            @endcan
                        @else
                            @can('update', $product)
                                <form action="{{ route('products.activate', $product) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <iconify-icon icon="solar:play-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Activate
                                    </button>
                                </form>
                            @endcan
                        @endif

                        <a href="{{ route('products.index') }}" class="btn btn-light w-100">
                            <iconify-icon icon="solar:arrow-left-broken" class="me-1"></iconify-icon>
                            Back to List
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Current Stock:</span>
                                @if ($product->stock_quantity > $product->low_stock_threshold)
                                    <span
                                        class="badge bg-success-subtle text-success">{{ $product->stock_quantity }}</span>
                                @elseif($product->stock_quantity > 0)
                                    <span
                                        class="badge bg-warning-subtle text-warning">{{ $product->stock_quantity }}</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">0</span>
                                @endif
                            </div>
                        </div>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Selling Price:</span>
                                <span
                                    class="badge bg-primary-subtle text-primary">{{ format_currency($product->selling_price) }}</span>
                            </div>
                        </div>
                        @if ($canViewCost && $product->cost_price)
                            <div class="mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Profit Margin:</span>
                                    <span class="badge bg-success-subtle text-success">
                                        {{ format_currency($product->selling_price - $product->cost_price) }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Product ID</small>
                            <code class="d-block">{{ $product->id }}</code>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Created At</small>
                            <p class="mb-0">{{ $product->created_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $product->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block mb-1">Last Updated</small>
                            <p class="mb-0">{{ $product->updated_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $product->updated_at->diffForHumans() }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Single Product Modal -->
    <div class="modal fade" id="syncSingleProductModal" tabindex="-1" aria-labelledby="syncSingleProductModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="syncSingleProductModalLabel">
                        <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Sync {{ $product->name }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('products.sync.single', $product) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="shop_id" class="form-label">Select Shop</label>
                            <select class="form-select" id="shop_id" name="shop_id" required>
                                <option value="">Choose shop...</option>
                                @foreach (\App\Models\Shop::active()->get() as $shop)
                                    @if ($shop->hasAnyEcommerceIntegration())
                                        <option value="{{ $shop->id }}">
                                            {{ $shop->name }}
                                            @php
                                                $integrations = $shop->getEnabledIntegrations();
                                            @endphp
                                            ({{ implode(', ', array_map('ucfirst', $integrations)) }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            <small class="text-muted">Only shops with e-commerce integrations are shown</small>
                        </div>

                        <div class="mb-3">
                            <label for="platform" class="form-label">Platform</label>
                            <select class="form-select" id="platform" name="platform" required>
                                <option value="">Choose platform...</option>
                                <option value="woocommerce">WooCommerce</option>
                                <option value="shopify">Shopify</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="platform_name" class="form-label">Platform Product Name (Optional)</label>
                            <input type="text" class="form-control" id="platform_name" name="platform_name"
                                placeholder="{{ $product->name }}">
                            <small class="text-muted">Leave empty to use the same name as local product</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Sync Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
