@extends('layouts.app')

@section('title', 'Purchase Order: ' . $purchaseOrder->order_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a>
                                </li>
                                <li class="breadcrumb-item active">{{ $purchaseOrder->order_number }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">{{ $purchaseOrder->order_number }}</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @if ($purchaseOrder->status->canReceive())
                            <a href="{{ route('stock-intakes.create', ['purchase_order_id' => $purchaseOrder->uuid]) }}"
                                class="btn btn-success">
                                <iconify-icon icon="solar:inbox-in-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Receive Stock
                            </a>
                        @endif

                        @if ($purchaseOrder->stockIntakes->contains(fn($intake) => $intake->isCompleted() && $intake->quantity_accepted > 0))
                            @can('create', App\Models\PurchaseReturn::class)
                                <a href="{{ route('purchase-returns.create', ['purchase_order_id' => $purchaseOrder->uuid]) }}"
                                    class="btn btn-warning">
                                    <iconify-icon icon="solar:undo-left-round-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Supplier Return
                                </a>
                            @endcan
                        @endif

                        @if ($purchaseOrder->status->canEdit())
                            <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-primary">
                                <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                Edit Order
                            </a>
                        @endif

                        @if ($purchaseOrder->isDraft())
                            @can('submitForApproval', $purchaseOrder)
                                <form action="{{ route('purchase-orders.submitForApproval', $purchaseOrder) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-info"
                                        onclick="return confirm('Submit this order for approval?')">
                                        <iconify-icon icon="solar:send-bold-duotone" class="align-middle me-1"></iconify-icon>
                                        Submit for Approval
                                    </button>
                                </form>
                            @endcan
                            @can('submitApproveAndMarkAsOrdered', $purchaseOrder)
                                <form action="{{ route('purchase-orders.submitApproveAndMarkAsOrdered', $purchaseOrder) }}"
                                    method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success"
                                        onclick="return confirm('Submit, approve, and mark this order as ordered?')">
                                        <iconify-icon icon="solar:cart-check-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Submit, Approve & Mark Ordered
                                    </button>
                                </form>
                            @endcan
                        @endif

                        @if ($purchaseOrder->status->canApprove())
                            @can('approve', $purchaseOrder)
                                <form action="{{ route('purchase-orders.approve', $purchaseOrder) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-info"
                                        onclick="return confirm('Are you sure you want to approve this order?')">
                                        <iconify-icon icon="solar:check-circle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Approve Order
                                    </button>
                                </form>
                            @endcan
                            @can('approveAndMarkAsOrdered', $purchaseOrder)
                                <form action="{{ route('purchase-orders.approveAndMarkAsOrdered', $purchaseOrder) }}"
                                    method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-primary"
                                        onclick="return confirm('Approve this order and mark it as ordered?')">
                                        <iconify-icon icon="solar:cart-check-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Approve & Mark as Ordered
                                    </button>
                                </form>
                            @endcan
                        @endif

                        @if ($purchaseOrder->isApproved())
                            @can('markAsOrdered', $purchaseOrder)
                                <form action="{{ route('purchase-orders.markAsOrdered', $purchaseOrder) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-primary"
                                        onclick="return confirm('Mark this purchase order as ordered?')">
                                        <iconify-icon icon="solar:cart-check-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Mark as Ordered
                                    </button>
                                </form>
                            @endcan
                        @endif

                        @if ($purchaseOrder->status->canCancel())
                            <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to cancel this order?')">
                                    <iconify-icon icon="solar:close-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Cancel Order
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Details -->
            <div class="col-xl-8">
                <!-- Order Info Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Order Details</h5>
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
                            $color = $statusColors[$purchaseOrder->status->value] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-13">
                            {{ $purchaseOrder->status->label() }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Supplier:</td>
                                        <td class="fw-medium">
                                            @if ($purchaseOrder->supplier)
                                                <a href="{{ route('suppliers.show', $purchaseOrder->supplier) }}">
                                                    {{ $purchaseOrder->supplier->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td class="fw-medium">{{ $purchaseOrder->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Order Date:</td>
                                        <td>{{ $purchaseOrder->order_date?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Expected Delivery:</td>
                                        <td>
                                            @if ($purchaseOrder->expected_delivery_date)
                                                @if (
                                                    $purchaseOrder->expected_delivery_date->isPast() &&
                                                        !in_array($purchaseOrder->status->value, ['received', 'cancelled']))
                                                    <span
                                                        class="text-danger">{{ $purchaseOrder->expected_delivery_date->format('M d, Y') }}</span>
                                                    <span class="badge bg-danger-subtle text-danger ms-1">Overdue</span>
                                                @else
                                                    {{ $purchaseOrder->expected_delivery_date->format('M d, Y') }}
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Created By:</td>
                                        <td>{{ $purchaseOrder->creator?->name ?? '—' }}</td>
                                    </tr>
                                    @if ($purchaseOrder->approver)
                                        <tr>
                                            <td class="text-muted">Approved By:</td>
                                            <td>{{ $purchaseOrder->approver->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Approved At:</td>
                                            <td>{{ $purchaseOrder->approved_at?->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td class="text-muted">Payment Terms:</td>
                                        <td>{{ $purchaseOrder->payment_terms ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($purchaseOrder->notes)
                            <div class="mt-3">
                                <h6 class="text-muted mb-2">Notes</h6>
                                <p class="mb-0">{{ $purchaseOrder->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Order Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Ordered</th>
                                        <th class="text-center">Received</th>
                                        <th class="text-center">Remaining</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Line Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($purchaseOrder->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-medium">{{ $item->product_name }}</div>
                                                @if ($item->variation_attributes)
                                                    <small class="text-muted">
                                                        @foreach ($item->variation_attributes as $attr => $value)
                                                            {{ ucfirst($attr) }}: {{ $value }}
                                                            @if (!$loop->last)
                                                                |
                                                            @endif
                                                        @endforeach
                                                    </small>
                                                @endif
                                                @if ($item->sku)
                                                    <br><small class="text-muted">SKU: {{ $item->sku }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{ number_format($item->quantity_ordered) }}
                                                {{ $item->unit ?? 'pcs' }}
                                            </td>
                                            <td class="text-center">
                                                @if ($item->quantity_received > 0)
                                                    <span
                                                        class="text-success">{{ number_format($item->quantity_received) }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($item->quantity_remaining > 0)
                                                    <span
                                                        class="badge bg-warning-subtle text-warning">{{ number_format($item->quantity_remaining) }}</span>
                                                @else
                                                    <span class="badge bg-success-subtle text-success">Complete</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ format_currency($item->unit_cost) }}</td>
                                            <td class="text-end fw-medium">{{ format_currency($item->line_total) }}</td>
                                            <td>
                                                @if ($purchaseOrder->status->canReceive() && $item->quantity_remaining > 0)
                                                    <a href="{{ route('stock-intakes.create', ['purchase_order_id' => $purchaseOrder->uuid, 'purchase_order_item_id' => $item->uuid]) }}"
                                                        class="btn btn-soft-success btn-sm" title="Receive this item">
                                                        <iconify-icon icon="solar:inbox-in-bold-duotone"
                                                            class="align-middle"></iconify-icon>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                No items in this order.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($purchaseOrder->items->count() > 0)
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="5" class="text-end">Subtotal:</td>
                                            <td class="text-end">{{ format_currency($purchaseOrder->subtotal) }}</td>
                                            <td></td>
                                        </tr>
                                        @if ($purchaseOrder->discount_amount > 0)
                                            <tr>
                                                <td colspan="5" class="text-end">Discount:</td>
                                                <td class="text-end text-danger">
                                                    -{{ format_currency($purchaseOrder->discount_amount) }}</td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        @if ($purchaseOrder->tax_amount > 0)
                                            <tr>
                                                <td colspan="5" class="text-end">Tax:</td>
                                                <td class="text-end">{{ format_currency($purchaseOrder->tax_amount) }}
                                                </td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        @if ($purchaseOrder->shipping_cost > 0)
                                            <tr>
                                                <td colspan="5" class="text-end">Shipping:</td>
                                                <td class="text-end">
                                                    {{ format_currency($purchaseOrder->shipping_cost) }}</td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="5" class="text-end fw-bold">Total:</td>
                                            <td class="text-end fw-bold fs-16">
                                                {{ format_currency($purchaseOrder->total_amount) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Stock Intakes History -->
                @if ($purchaseOrder->stockIntakes->count() > 0)
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Stock Intake History</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Intake #</th>
                                            <th>Date</th>
                                            <th class="text-center">Qty Received</th>
                                            <th class="text-center">Qty Accepted</th>
                                            <th class="text-center">Qty Rejected</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($purchaseOrder->stockIntakes as $intake)
                                            @php
                                                $intakeStatusColors = [
                                                    'pending' => 'warning',
                                                    'in_progress' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                ];
                                                $intakeColor =
                                                    $intakeStatusColors[$intake->status->value] ?? 'secondary';
                                            @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('stock-intakes.show', $intake) }}"
                                                        class="fw-medium">
                                                        {{ $intake->intake_number }}
                                                    </a>
                                                </td>
                                                <td>{{ $intake->received_at?->format('M d, Y H:i') ?? '—' }}</td>
                                                <td class="text-center">{{ number_format($intake->quantity_received) }}
                                                </td>
                                                <td class="text-center text-success">
                                                    {{ number_format($intake->quantity_accepted) }}</td>
                                                <td class="text-center text-danger">
                                                    {{ number_format($intake->quantity_rejected) }}</td>
                                                <td>
                                                    <span
                                                        class="badge bg-{{ $intakeColor }}-subtle text-{{ $intakeColor }}">
                                                        {{ $intake->status->label() }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="{{ route('stock-intakes.show', $intake) }}"
                                                        class="btn btn-light btn-sm">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle"></iconify-icon>
                                                    </a>
                                                    @if ($intake->isCompleted() && $intake->quantity_accepted > 0)
                                                        @can('create', App\Models\PurchaseReturn::class)
                                                            <a href="{{ route('purchase-returns.create', ['stock_intake_id' => $intake->uuid]) }}"
                                                                class="btn btn-soft-warning btn-sm"
                                                                title="Return to supplier">
                                                                <iconify-icon icon="solar:undo-left-round-bold-duotone"
                                                                    class="align-middle"></iconify-icon>
                                                            </a>
                                                        @endcan
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
            </div>

            <!-- Sidebar -->
            <div class="col-xl-4">
                <!-- Order Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Items:</span>
                            <span class="fw-medium">{{ $purchaseOrder->items->count() }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Quantity:</span>
                            <span
                                class="fw-medium">{{ number_format($purchaseOrder->items->sum('quantity_ordered')) }}</span>
                        </div>
                        @php
                            $totalReceived = $purchaseOrder->items->sum('quantity_received');
                            $totalOrdered = $purchaseOrder->items->sum('quantity_ordered');
                            $receivePercent = $totalOrdered > 0 ? ($totalReceived / $totalOrdered) * 100 : 0;
                        @endphp
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Received:</span>
                            <span class="fw-medium">{{ number_format($totalReceived) }}
                                ({{ number_format($receivePercent, 1) }}%)</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Total Amount:</span>
                            <span
                                class="fw-bold text-primary fs-18">{{ format_currency($purchaseOrder->total_amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Receive Quantity Progress -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Receiving Progress</h5>
                    </div>
                    <div class="card-body">
                        @foreach ($purchaseOrder->items as $item)
                            @php
                                $itemPercent =
                                    $item->quantity_ordered > 0
                                        ? ($item->quantity_received / $item->quantity_ordered) * 100
                                        : 0;
                                $barColor =
                                    $itemPercent >= 100 ? 'success' : ($itemPercent > 0 ? 'warning' : 'secondary');
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-truncate" style="max-width: 200px;"
                                        title="{{ $item->product_name }}">
                                        {{ $item->product_name }}
                                    </small>
                                    <small
                                        class="text-muted">{{ number_format($item->quantity_received) }}/{{ number_format($item->quantity_ordered) }}</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-{{ $barColor }}" role="progressbar"
                                        style="width: {{ min($itemPercent, 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if ($purchaseOrder->status->canReceive())
                                <a href="{{ route('stock-intakes.create', ['purchase_order_id' => $purchaseOrder->uuid]) }}"
                                    class="btn btn-success">
                                    <iconify-icon icon="solar:inbox-in-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Receive All Items
                                </a>
                            @endif

                            <a href="{{ route('purchase-orders.index') }}" class="btn btn-light">
                                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
