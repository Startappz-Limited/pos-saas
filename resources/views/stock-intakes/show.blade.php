@extends('layouts.app')

@section('title', 'Stock Intake: ' . $stockIntake->intake_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('stock-intakes.index') }}">Stock Intakes</a>
                                </li>
                                <li class="breadcrumb-item active">{{ $stockIntake->intake_number }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">{{ $stockIntake->intake_number }}</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @if ($stockIntake->status->canComplete())
                            <form action="{{ route('stock-intakes.complete', $stockIntake) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Complete this intake and update inventory?')">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Complete Intake
                                </button>
                            </form>
                        @endif

                        @if ($stockIntake->status->canEdit())
                            <a href="{{ route('stock-intakes.edit', $stockIntake) }}" class="btn btn-primary">
                                <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                Edit
                            </a>
                        @endif

                        @if ($stockIntake->status->canCancel())
                            <form action="{{ route('stock-intakes.cancel', $stockIntake) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to cancel this intake?')">
                                    <iconify-icon icon="solar:close-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Cancel Intake
                                </button>
                            </form>
                        @endif

                        @if ($stockIntake->isCompleted() && $stockIntake->quantity_accepted > 0)
                            @can('create', App\Models\PurchaseReturn::class)
                                <a href="{{ route('purchase-returns.create', ['stock_intake_id' => $stockIntake->uuid]) }}"
                                    class="btn btn-warning">
                                    <iconify-icon icon="solar:undo-left-round-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Return to Supplier
                                </a>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-xl-8">
                <!-- Intake Details Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Intake Details</h5>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'in_progress' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                            ];
                            $color = $statusColors[$stockIntake->status->value] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-13">
                            {{ $stockIntake->status->label() }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Product:</td>
                                        <td class="fw-medium">
                                            @if ($stockIntake->product)
                                                <a href="{{ route('products.show', $stockIntake->product) }}">
                                                    {{ $stockIntake->product->name }}
                                                </a>
                                                @if ($stockIntake->productVariation)
                                                    <br><small
                                                        class="text-muted">{{ $stockIntake->productVariation->name }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $stockIntake->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Supplier:</td>
                                        <td>
                                            @if ($stockIntake->supplier)
                                                <a href="{{ route('suppliers.show', $stockIntake->supplier) }}">
                                                    {{ $stockIntake->supplier->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Purchase Order:</td>
                                        <td>
                                            @if ($stockIntake->purchaseOrder)
                                                <a href="{{ route('purchase-orders.show', $stockIntake->purchaseOrder) }}">
                                                    {{ $stockIntake->purchaseOrder->order_number }}
                                                </a>
                                            @else
                                                <span class="text-muted">Direct Intake</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Intake Date:</td>
                                        <td>{{ $stockIntake->intake_date?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Received By:</td>
                                        <td>{{ $stockIntake->receivedByUser?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Received At:</td>
                                        <td>{{ $stockIntake->received_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                    @if ($stockIntake->completedByUser)
                                        <tr>
                                            <td class="text-muted">Completed By:</td>
                                            <td>{{ $stockIntake->completedByUser->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Completed At:</td>
                                            <td>{{ $stockIntake->completed_at?->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td class="text-muted">Created By:</td>
                                        <td>{{ $stockIntake->creator?->name ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quantity Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quantity Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded">
                                    <h2 class="mb-1">{{ number_format($stockIntake->quantity_received) }}</h2>
                                    <p class="text-muted mb-0">Received</p>
                                    <small class="text-muted">{{ $stockIntake->unit ?? 'pcs' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-success-subtle rounded">
                                    <h2 class="mb-1 text-success">{{ number_format($stockIntake->quantity_accepted) }}</h2>
                                    <p class="text-muted mb-0">Accepted</p>
                                    <small class="text-muted">{{ $stockIntake->unit ?? 'pcs' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-danger-subtle rounded">
                                    <h2 class="mb-1 text-danger">{{ number_format($stockIntake->quantity_rejected) }}</h2>
                                    <p class="text-muted mb-0">Rejected</p>
                                    <small class="text-muted">{{ $stockIntake->unit ?? 'pcs' }}</small>
                                </div>
                            </div>
                        </div>

                        @php
                            $acceptanceRate =
                                $stockIntake->quantity_received > 0
                                    ? ($stockIntake->quantity_accepted / $stockIntake->quantity_received) * 100
                                    : 0;
                        @endphp
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Acceptance Rate</span>
                                <span class="fw-medium">{{ number_format($acceptanceRate, 1) }}%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: {{ $acceptanceRate }}%"></div>
                                <div class="progress-bar bg-danger" style="width: {{ 100 - $acceptanceRate }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quality & Storage Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quality & Storage</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Quality Status</h6>
                                @php
                                    $qualityColors = [
                                        'passed' => 'success',
                                        'partial' => 'warning',
                                        'failed' => 'danger',
                                    ];
                                    $qualityColor = $qualityColors[$stockIntake->quality_status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $qualityColor }}-subtle text-{{ $qualityColor }} fs-13">
                                    {{ ucfirst($stockIntake->quality_status ?? 'Not Set') }}
                                </span>

                                @if ($stockIntake->quality_notes)
                                    <div class="mt-3">
                                        <h6 class="text-muted">Quality Notes</h6>
                                        <p class="mb-0">{{ $stockIntake->quality_notes }}</p>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Storage Information</h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted ps-0">Location:</td>
                                        <td>{{ $stockIntake->storage_location ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-0">Bin:</td>
                                        <td>{{ $stockIntake->bin_location ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-0">Batch #:</td>
                                        <td>{{ $stockIntake->batch_number ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-0">Expiry:</td>
                                        <td>
                                            @if ($stockIntake->expiry_date)
                                                @if ($stockIntake->expiry_date->isPast())
                                                    <span
                                                        class="text-danger">{{ $stockIntake->expiry_date->format('M d, Y') }}</span>
                                                    <span class="badge bg-danger-subtle text-danger ms-1">Expired</span>
                                                @elseif($stockIntake->expiry_date->diffInDays(now()) < 30)
                                                    <span
                                                        class="text-warning">{{ $stockIntake->expiry_date->format('M d, Y') }}</span>
                                                    <span class="badge bg-warning-subtle text-warning ms-1">Expiring
                                                        Soon</span>
                                                @else
                                                    {{ $stockIntake->expiry_date->format('M d, Y') }}
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($stockIntake->notes)
                            <hr>
                            <h6 class="text-muted">Notes</h6>
                            <p class="mb-0">{{ $stockIntake->notes }}</p>
                        @endif
                    </div>
                </div>

                <!-- Price Update Section (shown after completing intake) -->
                @if ($stockIntake->status->value === 'completed')
                    <div class="card mb-4 border-primary">
                        <div class="card-header bg-primary-subtle">
                            <h5 class="card-title mb-0">
                                <iconify-icon icon="solar:tag-price-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Update Product Pricing
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Now that stock has been received, you may want to update the product pricing based on the
                                new cost.
                            </p>
                            @if ($stockIntake->purchaseOrderItem)
                                <div class="alert alert-info mb-3">
                                    <strong>Cost from PO:</strong>
                                    {{ format_currency($stockIntake->purchaseOrderItem->unit_cost) }} per unit
                                </div>
                            @endif
                            <div class="d-flex gap-2">
                                @if ($stockIntake->product)
                                    <a href="{{ route('products.edit', $stockIntake->product) }}"
                                        class="btn btn-primary">
                                        <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                        Edit Product Pricing
                                    </a>
                                @endif
                                <a href="{{ route('pricing-rules.index') }}" class="btn btn-soft-primary">
                                    <iconify-icon icon="solar:tag-price-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    View Pricing Rules
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-xl-4">
                <!-- Summary Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Received:</span>
                            <span class="fw-medium">{{ number_format($stockIntake->quantity_received) }}
                                {{ $stockIntake->unit ?? 'pcs' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Accepted:</span>
                            <span class="fw-medium text-success">{{ number_format($stockIntake->quantity_accepted) }}
                                {{ $stockIntake->unit ?? 'pcs' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Rejected:</span>
                            <span class="fw-medium text-danger">{{ number_format($stockIntake->quantity_rejected) }}
                                {{ $stockIntake->unit ?? 'pcs' }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Quality:</span>
                            <span class="badge bg-{{ $qualityColor }}-subtle text-{{ $qualityColor }}">
                                {{ ucfirst($stockIntake->quality_status ?? 'N/A') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Related PO Card -->
                @if ($stockIntake->purchaseOrder)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Related Purchase Order</h5>
                        </div>
                        <div class="card-body">
                            <a href="{{ route('purchase-orders.show', $stockIntake->purchaseOrder) }}"
                                class="text-decoration-none">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded">
                                            <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0">{{ $stockIntake->purchaseOrder->order_number }}</h6>
                                        <small
                                            class="text-muted">{{ $stockIntake->purchaseOrder->supplier?->name }}</small>
                                    </div>
                                </div>
                            </a>

                            @if ($stockIntake->purchaseOrderItem)
                                <div class="border-top pt-3">
                                    <small class="text-muted d-block mb-1">Item being received:</small>
                                    <span class="fw-medium">{{ $stockIntake->purchaseOrderItem->product_name }}</span>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">Ordered:</small>
                                        <small>{{ number_format($stockIntake->purchaseOrderItem->quantity_ordered) }}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Previously Received:</small>
                                        <small>{{ number_format($stockIntake->purchaseOrderItem->quantity_received - $stockIntake->quantity_accepted) }}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">This Intake:</small>
                                        <small
                                            class="text-success">+{{ number_format($stockIntake->quantity_accepted) }}</small>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if ($stockIntake->purchaseOrder && $stockIntake->purchaseOrder->status->canReceive())
                                <a href="{{ route('stock-intakes.create', ['purchase_order_id' => $stockIntake->purchaseOrder->uuid]) }}"
                                    class="btn btn-success">
                                    <iconify-icon icon="solar:inbox-in-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Receive More Items
                                </a>
                            @endif

                            @if ($stockIntake->isCompleted() && $stockIntake->quantity_accepted > 0)
                                @can('create', App\Models\PurchaseReturn::class)
                                    <a href="{{ route('purchase-returns.create', ['stock_intake_id' => $stockIntake->uuid]) }}"
                                        class="btn btn-warning">
                                        <iconify-icon icon="solar:undo-left-round-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Supplier Return
                                    </a>
                                @endcan
                            @endif

                            <a href="{{ route('stock-intakes.index') }}" class="btn btn-light">
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
