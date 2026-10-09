@extends('layouts.app')

@section('title', 'Low Stock Products')

@section('content')
    <div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <iconify-icon icon="solar:danger-triangle-bold-duotone" class="text-warning me-2"></iconify-icon>
                            Products Running Low on Stock
                        </h4>
                        <a href="{{ route('products.index') }}" class="btn btn-light btn-sm">
                            <iconify-icon icon="solar:arrow-left-broken" class="me-1"></iconify-icon>
                            Back to All Products
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Threshold</th>
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
                                                            class="text-dark fw-medium">
                                                            {{ $product->name }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code>{{ $product->sku }}</code></td>
                                            <td>
                                                @if ($product->category)
                                                    <span
                                                        class="badge bg-info-subtle text-info">{{ $product->category->name }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($product->stock_quantity <= 0)
                                                    <span
                                                        class="badge bg-danger-subtle text-danger fs-14">{{ $product->stock_quantity }}</span>
                                                @else
                                                    <span
                                                        class="badge bg-warning-subtle text-warning fs-14">{{ $product->stock_quantity }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-secondary-subtle text-secondary">{{ $product->low_stock_threshold }}</span>
                                            </td>
                                            <td>
                                                @if ($product->stock_quantity <= 0)
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                @else
                                                    <span class="badge bg-warning">Low Stock</span>
                                                @endif
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
                                                            class="btn btn-soft-primary btn-sm" title="Update Stock">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                    class="fs-1 text-success mb-2"></iconify-icon>
                                                <p class="text-muted">All products have sufficient stock!</p>
                                                <a href="{{ route('products.index') }}" class="btn btn-primary btn-sm">
                                                    <iconify-icon icon="solar:arrow-left-broken"
                                                        class="me-1"></iconify-icon>
                                                    Back to Products
                                                </a>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($products->hasPages())
                            <div class="mt-4">
                                {{ $products->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
