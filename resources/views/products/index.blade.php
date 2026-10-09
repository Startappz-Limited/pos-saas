@extends('layouts.app')

@section('title', 'Products')

@section('content')
    @php
        // Cost reveals supplier terms and margin, so the per-row cost line is
        // gated rather than shown to anyone who can list products.
        $canViewCost = auth()->user()?->can('viewCost', \App\Models\Product::class) ?? false;
    @endphp

    <div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                    <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Products</p>
                                <h4 class="mb-0">{{ $statistics['total'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Active</p>
                                <h4 class="mb-0">{{ $statistics['active'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                                    <iconify-icon icon="solar:danger-triangle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Low Stock</p>
                                <h4 class="mb-0">{{ $statistics['low_stock'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                                    <iconify-icon icon="solar:close-square-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Out of Stock</p>
                                <h4 class="mb-0">{{ $statistics['out_of_stock'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Products</h4>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal"
                                data-bs-target="#syncProductsModal">
                                <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Sync Products
                            </button>
                            <a href="{{ route('products.lowStock') }}" class="btn btn-warning btn-sm">
                                <iconify-icon icon="solar:danger-triangle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Low Stock
                            </a>
                            @can('create', App\Models\Product::class)
                                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Add Product
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('products.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search products..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="category_id" class="form-select">
                                    <option value="">All Categories</option>
                                    @foreach (\App\Models\Category::active()->ordered()->get() as $category)
                                        <option value="{{ $category->id }}"
                                            {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="shop_id" class="form-select">
                                    <option value="">All Shops</option>
                                    @foreach (\App\Models\Shop::active()->orderBy('name')->get() as $shop)
                                        <option value="{{ $shop->id }}"
                                            {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>
                                        Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-secondary flex-fill">
                                        <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon>
                                        Search
                                    </button>
                                    @if (request()->hasAny(['search', 'category_id', 'shop_id', 'status']))
                                        <a href="{{ route('products.index') }}" class="btn btn-light">
                                            <iconify-icon icon="solar:refresh-linear" class="align-middle"></iconify-icon>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Shop(s)</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($products as $product)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div
                                                        class="rounded bg-light avatar-md d-flex align-items-center justify-content-center">
                                                        @if ($product->image)
                                                            <img src="{{ asset('storage/' . $product->image) }}"
                                                                alt="{{ $product->name }}" class="avatar-md rounded">
                                                        @else
                                                            <iconify-icon icon="solar:box-bold-duotone"
                                                                class="fs-1 text-muted"></iconify-icon>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <a href="{{ route('products.show', $product) }}"
                                                            class="text-dark fw-medium fs-15">
                                                            {{ $product->name }}
                                                        </a>
                                                        @if ($product->description)
                                                            <p class="text-muted mb-0 mt-1 fs-13">
                                                                {{ Str::limit($product->description, 50) }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code>{{ $product->sku }}</code>
                                            </td>
                                            <td>
                                                @if ($product->category)
                                                    <span
                                                        class="badge bg-info-subtle text-info">{{ $product->category->name }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($product->shops->isNotEmpty())
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach ($product->shops->take(3) as $shop)
                                                            <span
                                                                class="badge bg-primary-subtle text-primary">{{ $shop->name }}</span>
                                                        @endforeach
                                                        @if ($product->shops->count() > 3)
                                                            <span
                                                                class="badge bg-secondary-subtle text-secondary">+{{ $product->shops->count() - 3 }}</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div>
                                                    <p class="mb-0 fw-medium">
                                                        {{ format_currency($product->selling_price) }}</p>
                                                    @if ($canViewCost && $product->cost_price)
                                                        <small class="text-muted">Cost:
                                                            {{ format_currency($product->cost_price) }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if ($product->stock_quantity > $product->low_stock_threshold)
                                                    <span
                                                        class="badge bg-success-subtle text-success">{{ $product->stock_quantity }}</span>
                                                @elseif($product->stock_quantity > 0)
                                                    <span
                                                        class="badge bg-warning-subtle text-warning">{{ $product->stock_quantity }}</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">Out</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $product->status->color() }}-subtle text-{{ $product->status->color() }}">{{ $product->status->label() }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    @can('view', $product)
                                                        <a href="{{ route('products.show', $product) }}"
                                                            class="btn btn-light btn-sm">
                                                            <iconify-icon icon="solar:eye-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('update', $product)
                                                        <a href="{{ route('products.edit', $product) }}"
                                                            class="btn btn-soft-primary btn-sm">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('delete', $product)
                                                        <form action="{{ route('products.destroy', $product) }}"
                                                            method="POST" class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-soft-danger btn-sm">
                                                                <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <iconify-icon icon="solar:box-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No products found.</p>
                                                @can('create', App\Models\Product::class)
                                                    <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Product
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($products->hasPages())
                        <div class="card-footer border-top">
                            <div class="d-flex justify-content-end">
                                {{ $products->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <!-- Sync Products Modal -->
    <div class="modal fade" id="syncProductsModal" tabindex="-1" aria-labelledby="syncProductsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="syncProductsModalLabel">
                        <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Sync Products with E-commerce
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="syncProductsForm" method="POST" action="{{ route('products.sync.initiate') }}">
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
                            <label for="direction" class="form-label">Sync Direction</label>
                            <select class="form-select" id="direction" name="direction" required>
                                <option value="from-platform">Import from Platform (Platform → Local)</option>
                                <option value="to-platform">Export to Platform (Local → Platform)</option>
                                <option value="both">Both Directions</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="limit" class="form-label">Limit (optional)</label>
                            <input type="number" class="form-control" id="limit" name="limit" placeholder="100"
                                min="1" max="1000">
                            <small class="text-muted">Maximum number of products to sync (leave empty for all)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Start Sync
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
