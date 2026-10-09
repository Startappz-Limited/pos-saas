@extends('layouts.app')

@section('title', 'Overdue Purchase Orders')

@section('content')
    <div>

        <!-- Page Header -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Overdue Purchase Orders</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a>
                            </li>
                            <li class="breadcrumb-item active">Overdue</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:alarm-bold-duotone"
                                class="align-middle text-danger me-1"></iconify-icon>
                            Overdue Orders
                            <span class="badge bg-danger-subtle text-danger ms-1">{{ $purchaseOrders->count() }}</span>
                        </h5>
                        <a href="{{ route('purchase-orders.index') }}" class="btn btn-light btn-sm">
                            <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                            Back to All Orders
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Supplier</th>
                                        <th>Shop</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Expected Delivery</th>
                                        <th>Days Overdue</th>
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
                                            <td>{{ $order->shop->name ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    {{ $order->items->count() }} items
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-medium">{{ format_currency($order->total_amount) }}</span>
                                            </td>
                                            <td>
                                                <span class="text-danger">
                                                    {{ $order->expected_delivery_date?->format('M d, Y') ?? '—' }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($order->expected_delivery_date)
                                                    <span class="badge bg-danger-subtle text-danger">
                                                        {{ now()->diffInDays($order->expected_delivery_date) }} days
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'ordered' => 'primary',
                                                        'partially_received' => 'warning',
                                                    ];
                                                    $color = $statusColors[$order->status->value] ?? 'secondary';
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $color }}-subtle text-{{ $color }}">
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
                                                    @if ($order->status->canCancel())
                                                        <form action="{{ route('purchase-orders.cancel', $order) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-danger btn-sm"
                                                                title="Cancel"
                                                                onclick="return confirm('Cancel this overdue purchase order?')">
                                                                <iconify-icon icon="solar:close-circle-bold-duotone"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                    class="fs-1 text-success mb-2"></iconify-icon>
                                                <p class="text-muted">No overdue purchase orders. All deliveries are on
                                                    track!</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
