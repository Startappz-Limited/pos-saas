@extends('layouts.app')

@section('title', 'Website Orders')

@section('content')

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:cart-large-4-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['total'] ?? 0 }}</h3>
                    <p class="text-muted">Total Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['pending'] ?? 0 }}</h3>
                    <p class="text-muted">Pending</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:refresh-bold-duotone" class="fs-36 text-info"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['processing'] ?? 0 }}</h3>
                    <p class="text-muted">Processing</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-36 text-success"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['completed'] ?? 0 }}</h3>
                    <p class="text-muted">Completed</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders List Card -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <h5 class="card-title mb-0">Website Orders</h5>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        @if ($shops->count() > 0)
                            <form method="POST" action="{{ route('ecommerce-orders.refresh') }}" class="d-inline">
                                @csrf
                                <div class="input-group">
                                    <select name="shop_id" class="form-select form-select-sm" required>
                                        @foreach ($shops as $shop)
                                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-soft-primary">
                                        <iconify-icon icon="solar:download-minimalistic-bold-duotone"
                                            class="align-middle me-1"></iconify-icon> Sync Orders
                                    </button>
                                </div>
                            </form>
                        @endif
                        <button class="btn btn-sm btn-soft-info" type="button" data-bs-toggle="collapse"
                            data-bs-target="#webhookUrls" aria-expanded="false">
                            <iconify-icon icon="solar:link-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Webhook URLs
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Webhook URLs Collapse -->
        <div class="collapse" id="webhookUrls">
            <div class="card-body border-bottom-dashed bg-light">
                <h6 class="text-muted text-uppercase fw-semibold mb-3">
                    <iconify-icon icon="solar:link-bold-duotone" class="align-middle me-1"></iconify-icon>
                    Webhook URLs
                </h6>
                <p class="text-muted small mb-3">Add these URLs in your WooCommerce/Shopify webhook settings to receive
                    orders automatically. </p>
                @foreach ($shops as $shop)
                    <div class="mb-3">
                        <span class="fw-semibold">{{ $shop->name }}</span>
                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">WooCommerce Webhook URL</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace small" readonly
                                        value="{{ url('/api/webhooks/woocommerce/' . $shop->uuid) }}"
                                        id="woo-url-{{ $shop->id }}">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('woo-url-{{ $shop->id }}').value)"
                                        title="Copy">
                                        <iconify-icon icon="solar:copy-linear"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Shopify Webhook URL</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace small" readonly
                                        value="{{ url('/api/webhooks/shopify/' . $shop->uuid) }}"
                                        id="shopify-url-{{ $shop->id }}">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('shopify-url-{{ $shop->id }}').value)"
                                        title="Copy">
                                        <iconify-icon icon="solar:copy-linear"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card-body">
            <!-- Search and Filters -->
            <form method="GET" action="{{ route('ecommerce-orders.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-3 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="Search order # or customer..." value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="shop_id">
                            <option value="">All Shops</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}"
                                    {{ request('shop_id') == $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="platform">
                            <option value="">All Platforms</option>
                            <option value="woocommerce" {{ request('platform') == 'woocommerce' ? 'selected' : '' }}>
                                WooCommerce</option>
                            <option value="shopify" {{ request('platform') == 'shopify' ? 'selected' : '' }}>Shopify
                            </option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            @foreach (\App\Enums\EcommerceOrderStatus::cases() as $status)
                                <option value="{{ $status->value }}"
                                    {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-1 col-sm-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                        </button>
                    </div>
                    <div class="col-xxl-2 col-sm-3">
                        <a href="{{ route('ecommerce-orders.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            @if ($orders->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Order #</th>
                                <th scope="col">Platform</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Items</th>
                                <th scope="col">Total</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('ecommerce-orders.show', $order) }}"
                                            class="fw-semibold text-primary">
                                            {{ $order->order_number }}
                                        </a>
                                        @if ($order->is_converted)
                                            <span class="badge bg-success-subtle text-success ms-1">Converted</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($order->platform === 'woocommerce')
                                            <span class="badge bg-purple-subtle text-purple">
                                                <iconify-icon icon="logos:woocommerce-icon"
                                                    class="align-middle me-1"></iconify-icon> WooCommerce
                                            </span>
                                        @else
                                            <span class="badge bg-success-subtle text-success">
                                                <iconify-icon icon="logos:shopify"
                                                    class="align-middle me-1"></iconify-icon> Shopify
                                            </span>
                                        @endif
                                        <small class="text-muted d-block">{{ $order->shop->name ?? '' }}</small>
                                    </td>
                                    <td>
                                        {{ $order->customer_name ?? 'N/A' }}
                                        @if ($order->customer_email)
                                            <small class="text-muted d-block">{{ $order->customer_email }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $order->items_count }}
                                            items</span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $order->currency }}
                                            {{ number_format($order->total, 2) }}</span>
                                    </td>
                                    <td>
                                        @if ($order->payment_status === 'paid')
                                            <span class="badge bg-success">Paid</span>
                                        @elseif($order->payment_status === 'refunded')
                                            <span class="badge bg-dark">Refunded</span>
                                        @else
                                            <span class="badge bg-danger">Unpaid</span>
                                        @endif
                                        @if ($order->is_cod)
                                            <span class="badge bg-warning-subtle text-warning">COD</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-{{ $order->status->color() }}-subtle text-{{ $order->status->color() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $order->platform_created_at?->format('M d, Y') ?? $order->created_at->format('M d, Y') }}
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a href="{{ route('ecommerce-orders.show', $order) }}"
                                                class="btn btn-sm btn-soft-info" title="View">
                                                <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                            </a>
                                            @if ($order->can_be_converted)
                                                <a href="{{ route('ecommerce-orders.convert', $order) }}"
                                                    class="btn btn-sm btn-soft-success" title="Convert to Sale">
                                                    <iconify-icon icon="solar:cart-plus-linear"></iconify-icon>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $orders->withQueryString()->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:cart-large-4-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Website Orders Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('search') ? 'Try adjusting your search or filters.' : 'Orders from your connected e-commerce platforms will appear here.' }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
