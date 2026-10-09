@extends('layouts.app')

@section('title', 'Supplier Return: ' . $purchaseReturn->return_number)

@section('content')
    @php
        $user = auth()->user();
        $canApprove = $purchaseReturn->canApprove() && (bool) $user?->can('approve', $purchaseReturn);
        $canApproveAndShip = $purchaseReturn->canApprove() && (bool) $user?->can('approveAndShip', $purchaseReturn);
        $canShip = $purchaseReturn->canShip() && (bool) $user?->can('ship', $purchaseReturn);
        $canComplete = $purchaseReturn->canComplete() && (bool) $user?->can('complete', $purchaseReturn);
        $canCancel = $purchaseReturn->canCancel() && (bool) $user?->can('cancel', $purchaseReturn);
    @endphp

    <div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('purchase-returns.index') }}">Supplier
                                        Returns</a></li>
                                <li class="breadcrumb-item active">{{ $purchaseReturn->return_number }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">{{ $purchaseReturn->return_number }}</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @if ($canApproveAndShip)
                            <form action="{{ route('purchase-returns.approveAndShip', $purchaseReturn) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Approve this supplier return and mark it as returned to supplier?')">
                                    <iconify-icon icon="solar:box-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Approve & Return
                                </button>
                            </form>
                        @endif

                        @if ($canApprove)
                            <form action="{{ route('purchase-returns.approve', $purchaseReturn) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Approve this supplier return?')">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Approve
                                </button>
                            </form>
                        @endif

                        @if ($canShip)
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#shipModal">
                                <iconify-icon icon="solar:box-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Return to Supplier
                            </button>
                        @endif

                        @if ($canComplete)
                            <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                data-bs-target="#completeModal">
                                <iconify-icon icon="solar:clipboard-check-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Complete
                            </button>
                        @endif

                        @if ($purchaseReturn->canEdit())
                            @can('update', $purchaseReturn)
                                <a href="{{ route('purchase-returns.edit', $purchaseReturn) }}" class="btn btn-soft-primary">
                                    <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                    Edit
                                </a>
                            @endcan
                        @endif

                        @if ($canCancel)
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#cancelModal">
                                <iconify-icon icon="solar:close-circle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Cancel
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Return Details</h5>
                        <span
                            class="badge bg-{{ $purchaseReturn->status->color() }}-subtle text-{{ $purchaseReturn->status->color() }} fs-13">
                            {{ $purchaseReturn->status->label() }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 160px;">Supplier:</td>
                                        <td>
                                            @if ($purchaseReturn->supplier)
                                                <a
                                                    href="{{ route('suppliers.show', $purchaseReturn->supplier) }}">{{ $purchaseReturn->supplier->name }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $purchaseReturn->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Reason:</td>
                                        <td>{{ $purchaseReturn->reason->label() }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Source:</td>
                                        <td>
                                            @if ($purchaseReturn->purchaseOrder)
                                                <a
                                                    href="{{ route('purchase-orders.show', $purchaseReturn->purchaseOrder) }}">
                                                    {{ $purchaseReturn->purchaseOrder->order_number }}
                                                </a>
                                            @elseif ($purchaseReturn->saleReturn)
                                                <a href="{{ route('returns.show', $purchaseReturn->saleReturn) }}">
                                                    {{ $purchaseReturn->saleReturn->return_number }}
                                                </a>
                                            @else
                                                <span class="text-muted">Direct</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 160px;">Requested By:</td>
                                        <td>{{ $purchaseReturn->requestedBy?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td>{{ $purchaseReturn->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                    @if ($purchaseReturn->shipment_reference)
                                        <tr>
                                            <td class="text-muted">Shipment Ref:</td>
                                            <td>{{ $purchaseReturn->shipment_reference }}</td>
                                        </tr>
                                    @endif
                                    @if ($purchaseReturn->supplier_credit_reference)
                                        <tr>
                                            <td class="text-muted">Credit Ref:</td>
                                            <td>{{ $purchaseReturn->supplier_credit_reference }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        @if ($purchaseReturn->notes)
                            <div class="mt-3 p-3 bg-light rounded">
                                <strong class="text-muted">Notes:</strong>
                                <p class="mb-0 mt-1">{{ $purchaseReturn->notes }}</p>
                            </div>
                        @endif

                        @if ($purchaseReturn->cancellation_reason)
                            <div class="mt-3 p-3 bg-danger-subtle rounded">
                                <strong class="text-danger">Cancellation Reason:</strong>
                                <p class="mb-0 mt-1">{{ $purchaseReturn->cancellation_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Source</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Line Total</th>
                                        <th>Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($purchaseReturn->items as $item)
                                        <tr>
                                            <td>
                                                @if ($item->product)
                                                    <a
                                                        href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a>
                                                @else
                                                    <span class="text-muted">Product Deleted</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($item->stockIntake)
                                                    <a
                                                        href="{{ route('stock-intakes.show', $item->stockIntake) }}">{{ $item->stockIntake->intake_number }}</a>
                                                @elseif ($item->returnItem?->saleReturn)
                                                    <a
                                                        href="{{ route('returns.show', $item->returnItem->saleReturn) }}">{{ $item->returnItem->saleReturn->return_number }}</a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ number_format($item->quantity) }}</td>
                                            <td class="text-end">{{ number_format($item->unit_cost, 2) }}</td>
                                            <td class="text-end fw-medium">{{ number_format($item->line_total, 2) }}</td>
                                            <td>
                                                @if ($item->condition)
                                                    <span
                                                        class="badge bg-secondary-subtle text-secondary text-capitalize">{{ str_replace('_', ' ', $item->condition) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No items in this
                                                supplier return.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Items</span>
                            <span class="fw-medium">{{ $purchaseReturn->items->count() }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Quantity</span>
                            <span class="fw-medium">{{ number_format($purchaseReturn->total_quantity) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Estimated Credit</span>
                            <span class="fw-medium">{{ number_format($purchaseReturn->total_amount, 2) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-medium">Supplier Credit</span>
                            <span
                                class="fw-bold fs-16">{{ number_format($purchaseReturn->supplier_credit_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Status Timeline</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <span class="avatar avatar-sm bg-primary-subtle text-primary rounded-circle">
                                        <iconify-icon icon="solar:document-add-bold-duotone"></iconify-icon>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Created</h6>
                                    <p class="text-muted mb-0 fs-13">
                                        {{ $purchaseReturn->created_at?->format('M d, Y H:i') }}<br>by
                                        {{ $purchaseReturn->requestedBy?->name ?? 'Unknown' }}</p>
                                </div>
                            </li>
                            @if ($purchaseReturn->approved_at)
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-info-subtle text-info rounded-circle">
                                            <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Approved</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $purchaseReturn->approved_at?->format('M d, Y H:i') }}<br>by
                                            {{ $purchaseReturn->approvedBy?->name ?? 'Unknown' }}</p>
                                    </div>
                                </li>
                            @endif
                            @if ($purchaseReturn->shipped_at)
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-primary-subtle text-primary rounded-circle">
                                            <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Returned to Supplier</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $purchaseReturn->shipped_at?->format('M d, Y H:i') }}<br>by
                                            {{ $purchaseReturn->shippedBy?->name ?? 'Unknown' }}</p>
                                    </div>
                                </li>
                            @endif
                            @if ($purchaseReturn->completed_at)
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-success-subtle text-success rounded-circle">
                                            <iconify-icon icon="solar:clipboard-check-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Completed</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $purchaseReturn->completed_at?->format('M d, Y H:i') }}<br>by
                                            {{ $purchaseReturn->completedBy?->name ?? 'Unknown' }}</p>
                                    </div>
                                </li>
                            @endif
                            @if ($purchaseReturn->cancelled_at)
                                <li class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-danger-subtle text-danger rounded-circle">
                                            <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Cancelled</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $purchaseReturn->cancelled_at?->format('M d, Y H:i') }}<br>by
                                            {{ $purchaseReturn->cancelledBy?->name ?? 'Unknown' }}</p>
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canShip)
        <div class="modal fade" id="shipModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('purchase-returns.ship', $purchaseReturn) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Return to Supplier</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Shipment Reference</label>
                                <input type="text" name="shipment_reference" class="form-control"
                                    value="{{ $purchaseReturn->shipment_reference }}"
                                    placeholder="Optional tracking or waybill">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Return to Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($canComplete)
        <div class="modal fade" id="completeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('purchase-returns.complete', $purchaseReturn) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Complete Supplier Return</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Supplier Credit Amount</label>
                                <input type="number" name="supplier_credit_amount" class="form-control" step="0.01"
                                    min="0" value="{{ $purchaseReturn->supplier_credit_amount }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Supplier Credit Reference</label>
                                <input type="text" name="supplier_credit_reference" class="form-control"
                                    value="{{ $purchaseReturn->supplier_credit_reference }}"
                                    placeholder="Optional credit note reference">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Complete Return</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($canCancel)
        <div class="modal fade" id="cancelModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('purchase-returns.cancel', $purchaseReturn) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Cancel Supplier Return</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Cancellation Reason</label>
                                <textarea name="cancellation_reason" class="form-control" rows="3"
                                    placeholder="Optional reason for cancelling this return"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Cancel Return</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
