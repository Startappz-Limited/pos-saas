@extends('layouts.app')

@section('title', 'Return: ' . $saleReturn->return_number)

@section('content')
    @php
        $user = auth()->user();
        $canApprove = $saleReturn->canBeApproved() && (bool) $user?->can('approve', $saleReturn);
        $canReceive = $saleReturn->canBeReceived() && (bool) $user?->can('receive', $saleReturn);
        $canInspect = $saleReturn->canBeInspected() && (bool) $user?->can('inspect', $saleReturn);
        $canReject = $saleReturn->canBeRejected() && (bool) $user?->can('reject', $saleReturn);
    @endphp

    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('returns.index') }}">Returns</a></li>
                                <li class="breadcrumb-item active">{{ $saleReturn->return_number }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">{{ $saleReturn->return_number }}</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @if ($canApprove)
                            <form action="{{ route('returns.approve', $saleReturn) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Approve this return?')">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Approve
                                </button>
                            </form>
                        @endif

                        @if ($canReceive)
                            <form action="{{ route('returns.receive', $saleReturn) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Mark items as received?')">
                                    <iconify-icon icon="solar:box-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Receive Items
                                </button>
                            </form>
                        @endif

                        @if ($canInspect)
                            <button type="button" class="btn btn-info" data-bs-toggle="modal"
                                data-bs-target="#inspectModal">
                                <iconify-icon icon="solar:magnifer-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Inspect
                            </button>
                        @endif

                        @if ($saleReturn->canBeRefunded() && !$saleReturn->refund)
                            @can('create', App\Models\Refund::class)
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                                    data-bs-target="#refundModal">
                                    <iconify-icon icon="solar:wallet-money-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Issue Refund
                                </button>
                            @endcan
                        @endif

                        @if ($saleReturn->isPending())
                            <a href="{{ route('returns.edit', $saleReturn) }}" class="btn btn-soft-primary">
                                <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                Edit
                            </a>
                        @endif

                        @if ($canReject)
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#rejectModal">
                                <iconify-icon icon="solar:close-circle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Reject
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-xl-8">
                <!-- Return Details Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Return Details</h5>
                        <span
                            class="badge bg-{{ $saleReturn->status->color() }}-subtle text-{{ $saleReturn->status->color() }} fs-13">
                            {{ $saleReturn->status->label() }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Sale:</td>
                                        <td>
                                            @if ($saleReturn->sale)
                                                <a href="{{ route('sales.show', $saleReturn->sale) }}">
                                                    {{ $saleReturn->sale->invoice_number ?? '#' . $saleReturn->sale->id }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Reason:</td>
                                        <td>{{ $saleReturn->reason->label() }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Customer:</td>
                                        <td>{{ $saleReturn->customer?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $saleReturn->shop?->name ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Requested By:</td>
                                        <td>{{ $saleReturn->requestedBy?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td>{{ $saleReturn->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                    @if ($saleReturn->approvedBy)
                                        <tr>
                                            <td class="text-muted">
                                                {{ $saleReturn->isRejected() ? 'Rejected' : 'Approved' }} By:</td>
                                            <td>{{ $saleReturn->approvedBy?->name ?? __('Deleted user') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">
                                                {{ $saleReturn->isRejected() ? 'Rejected' : 'Approved' }} At:</td>
                                            <td>{{ $saleReturn->approved_at?->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        @if ($saleReturn->notes)
                            <div class="mt-3 p-3 bg-light rounded">
                                <strong class="text-muted">Notes:</strong>
                                <p class="mb-0 mt-1">{{ $saleReturn->notes }}</p>
                            </div>
                        @endif

                        @if ($saleReturn->rejection_reason)
                            <div class="mt-3 p-3 bg-danger-subtle rounded">
                                <strong class="text-danger">Rejection Reason:</strong>
                                <p class="mb-0 mt-1">{{ $saleReturn->rejection_reason }}</p>
                            </div>
                        @endif

                        @if ($saleReturn->inspection_notes)
                            <div class="mt-3 p-3 bg-info-subtle rounded">
                                <strong class="text-info">Inspection Notes:</strong>
                                <p class="mb-0 mt-1">{{ $saleReturn->inspection_notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Items Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Return Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                        <th>Condition</th>
                                        <th>Restockable</th>
                                        <th>Supplier Return</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($saleReturn->items as $item)
                                        <tr>
                                            <td>
                                                @if ($item->product)
                                                    <a href="{{ route('products.show', $item->product) }}">
                                                        {{ $item->product->name }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Product Deleted</span>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                            <td class="text-end fw-medium">{{ number_format($item->total_price, 2) }}</td>
                                            <td>
                                                @if ($item->condition)
                                                    <span class="badge bg-secondary-subtle text-secondary text-capitalize">
                                                        {{ $item->condition }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($item->is_restockable)
                                                    <span class="badge bg-success-subtle text-success">Yes</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($item->return_to_supplier)
                                                    <span class="badge bg-warning-subtle text-warning mb-1">Marked</span>
                                                    @if ($item->purchaseReturnItems->isNotEmpty())
                                                        @foreach ($item->purchaseReturnItems as $purchaseReturnItem)
                                                            @if ($purchaseReturnItem->purchaseReturn)
                                                                <div>
                                                                    <a href="{{ route('purchase-returns.show', $purchaseReturnItem->purchaseReturn) }}"
                                                                        class="small">
                                                                        {{ $purchaseReturnItem->purchaseReturn->return_number }}
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        @can('create', App\Models\PurchaseReturn::class)
                                                            <div>
                                                                <a href="{{ route('purchase-returns.create', ['return_item_id' => $item->uuid]) }}"
                                                                    class="btn btn-soft-warning btn-sm mt-1">
                                                                    Create Supplier Return
                                                                </a>
                                                            </div>
                                                        @endcan
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <span class="text-muted">No items in this return.</span>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Refund Details (if exists) -->
                @if ($saleReturn->refund)
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Refund Details</h5>
                            <a href="{{ route('refunds.show', $saleReturn->refund) }}" class="btn btn-sm btn-light">
                                View Full Details
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="text-muted">Refund #:</td>
                                            <td class="fw-medium">{{ $saleReturn->refund->refund_number }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Method:</td>
                                            <td>{{ $saleReturn->refund->method->label() }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="text-muted">Amount:</td>
                                            <td class="fw-medium">{{ number_format($saleReturn->refund->amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Status:</td>
                                            <td>
                                                @php
                                                    $refundStatusColors = [
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                    ];
                                                    $refundColor =
                                                        $refundStatusColors[$saleReturn->refund->status] ?? 'secondary';
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $refundColor }}-subtle text-{{ $refundColor }}">
                                                    {{ ucfirst($saleReturn->refund->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-xl-4">
                <!-- Financial Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Financial Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Total Amount</span>
                            <span class="fw-medium">{{ number_format($saleReturn->total_amount, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Restocking Fee</span>
                            <span
                                class="fw-medium text-danger">-{{ number_format($saleReturn->restocking_fee, 2) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-medium">Refund Amount</span>
                            <span class="fw-bold fs-16">{{ number_format($saleReturn->refund_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Status Timeline -->
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
                                    <h6 class="mb-1">Requested</h6>
                                    <p class="text-muted mb-0 fs-13">
                                        {{ $saleReturn->created_at?->format('M d, Y H:i') }}
                                        <br>by {{ $saleReturn->requestedBy?->name ?? 'Unknown' }}
                                    </p>
                                </div>
                            </li>
                            @if ($saleReturn->approved_at && !$saleReturn->isRejected())
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-info-subtle text-info rounded-circle">
                                            <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Approved</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $saleReturn->approved_at?->format('M d, Y H:i') }}
                                            <br>by {{ $saleReturn->approvedBy?->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                            @if ($saleReturn->received_at)
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-primary-subtle text-primary rounded-circle">
                                            <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Items Received</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $saleReturn->received_at?->format('M d, Y H:i') }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                            @if ($saleReturn->inspected_at)
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-secondary-subtle text-secondary rounded-circle">
                                            <iconify-icon icon="solar:magnifer-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Inspected</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $saleReturn->inspected_at?->format('M d, Y H:i') }}
                                            <br>by {{ $saleReturn->inspectedBy?->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                            @if ($saleReturn->isCompleted())
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-success-subtle text-success rounded-circle">
                                            <iconify-icon icon="solar:check-square-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Completed</h6>
                                        <p class="text-muted mb-0 fs-13">Return finalized with refund</p>
                                    </div>
                                </li>
                            @endif
                            @if ($saleReturn->isRejected())
                                <li class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-danger-subtle text-danger rounded-circle">
                                            <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Rejected</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $saleReturn->approved_at?->format('M d, Y H:i') }}
                                            <br>by {{ $saleReturn->approvedBy?->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    @if ($canReject)
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('returns.reject', $saleReturn) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Return</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Rejection Reason</label>
                                <textarea name="rejection_reason" class="form-control" rows="3"
                                    placeholder="Optional reason for rejecting this return"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Return</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Inspect Modal -->
    @if ($canInspect)
        <div class="modal fade" id="inspectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('returns.inspect', $saleReturn) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Inspect Return Items</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Inspection Notes</label>
                                <textarea name="inspection_notes" class="form-control" rows="3" placeholder="Notes from inspection"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-info">Complete Inspection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Refund Modal -->
    @if ($saleReturn->canBeRefunded() && !$saleReturn->refund)
        @can('create', App\Models\Refund::class)
            <div class="modal fade" id="refundModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('refunds.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="return_id" value="{{ $saleReturn->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title">Issue Refund</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Refund Method <span class="text-danger">*</span></label>
                                    <select name="method" class="form-select" required>
                                        <option value="">— Select Method —</option>
                                        @foreach (\App\Enums\RefundMethod::cases() as $method)
                                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                                        value="{{ $saleReturn->refund_amount }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" name="reference_number" class="form-control"
                                        placeholder="Optional reference number">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional refund notes"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">Issue Refund</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    @endif
@endsection
