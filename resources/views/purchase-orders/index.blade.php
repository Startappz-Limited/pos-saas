@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
    <div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                    <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Orders</p>
                                <h4 class="mb-0">{{ $statistics['total_count'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:hourglass-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Pending Approval</p>
                                <h4 class="mb-0">{{ $statistics['pending_count'] ?? 0 }}</h4>
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
                                <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                                    <iconify-icon icon="solar:delivery-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Ordered</p>
                                <h4 class="mb-0">{{ $statistics['ordered_count'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:alarm-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Overdue</p>
                                <h4 class="mb-0">{{ $statistics['overdue_count'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Purchase Orders</h4>
                        <div class="d-flex gap-2">
                            <a href="{{ route('purchase-orders.pending') }}" class="btn btn-warning btn-sm">
                                <iconify-icon icon="solar:hourglass-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Pending
                            </a>
                            <a href="{{ route('purchase-orders.overdue') }}" class="btn btn-danger btn-sm">
                                <iconify-icon icon="solar:alarm-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Overdue
                            </a>
                            @can('create', App\Models\PurchaseOrder::class)
                                <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    New Order
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('purchase-orders.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search orders..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    @foreach (App\Enums\PurchaseOrderStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ request('status') === $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="supplier_id" class="form-select">
                                    <option value="">All Suppliers</option>
                                    @foreach (App\Models\Supplier::active()->orderBy('name')->get() as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon> Search
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Supplier</th>
                                        <th>Shop</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Order Date</th>
                                        <th>Expected</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($purchaseOrders as $order)
                                        <tr>
                                            <td>
                                                <a href="{{ route('purchase-orders.show', $order) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $order->order_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @if ($order->supplier)
                                                    <a href="{{ route('suppliers.show', $order->supplier) }}">
                                                        {{ $order->supplier->name }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($order->shop)
                                                    {{ $order->shop->name }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    {{ $order->items->count() }} items
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-medium">{{ format_currency($order->total_amount) }}</span>
                                            </td>
                                            <td>
                                                {{ $order->order_date?->format('M d, Y') ?? '—' }}
                                            </td>
                                            <td>
                                                @if ($order->expected_delivery_date)
                                                    @if ($order->expected_delivery_date->isPast() && !in_array($order->status->value, ['received', 'cancelled']))
                                                        <span class="text-danger">{{ $order->expected_delivery_date->format('M d, Y') }}</span>
                                                        <br><small class="text-danger">Overdue</small>
                                                    @else
                                                        {{ $order->expected_delivery_date->format('M d, Y') }}
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'draft' => 'secondary',
                                                        'pending' => 'warning',
                                                        'approved' => 'info',
                                                        'ordered' => 'primary',
                                                        'partially_received' => 'warning',
                                                        'received' => 'success',
                                                        'cancelled' => 'danger',
                                                    ];
                                                    $color = $statusColors[$order->status->value] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                    {{ $order->status->label() }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('purchase-orders.show', $order) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>

                                                    @if ($order->status->canReceive())
                                                        <a href="{{ route('stock-intakes.create', ['purchase_order_id' => $order->uuid]) }}"
                                                            class="btn btn-success btn-sm" title="Receive Stock">
                                                            <iconify-icon icon="solar:inbox-in-bold-duotone"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endif

                                                    @if ($order->status->canEdit())
                                                        <a href="{{ route('purchase-orders.edit', $order) }}"
                                                            class="btn btn-soft-primary btn-sm" title="Edit">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <iconify-icon icon="solar:clipboard-list-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No purchase orders found.</p>
                                                @can('create', App\Models\PurchaseOrder::class)
                                                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Purchase Order
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($purchaseOrders->hasPages())
                            <div class="mt-4">
                                {{ $purchaseOrders->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
