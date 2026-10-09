@extends('layouts.app')

@section('title', 'Pending Purchase Orders')

@section('content')
    <div>

        <!-- Page Header -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Pending Purchase Orders</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a>
                            </li>
                            <li class="breadcrumb-item active">Pending Approval</li>
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
                            <iconify-icon icon="solar:hourglass-bold-duotone"
                                class="align-middle text-warning me-1"></iconify-icon>
                            Orders Awaiting Approval
                            <span class="badge bg-warning-subtle text-warning ms-1">{{ $purchaseOrders->count() }}</span>
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
                                        <th>Order Date</th>
                                        <th>Created By</th>
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
                                            <td>{{ $order->order_date?->format('M d, Y') ?? '—' }}</td>
                                            <td>{{ $order->creator->name ?? '—' }}</td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('purchase-orders.show', $order) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>
                                                    @can('approve', $order)
                                                        <form action="{{ route('purchase-orders.approve', $order) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-success btn-sm"
                                                                title="Approve"
                                                                onclick="return confirm('Approve this purchase order?')">
                                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
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
                                            <td colspan="8" class="text-center py-5">
                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                    class="fs-1 text-success mb-2"></iconify-icon>
                                                <p class="text-muted">No purchase orders pending approval.</p>
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
