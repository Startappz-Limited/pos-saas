@extends('layouts.app')

@section('title', 'Stock Adjustment: ' . $stockAdjustment->adjustment_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a></li>
                                <li class="breadcrumb-item active">{{ $stockAdjustment->adjustment_number }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">{{ $stockAdjustment->adjustment_number }}</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @can('approve', $stockAdjustment)
                            <form action="{{ route('stock-adjustments.approve', $stockAdjustment) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Approve this adjustment?')">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Approve
                                </button>
                            </form>
                        @endcan

                        @can('complete', $stockAdjustment)
                            <form action="{{ route('stock-adjustments.complete', $stockAdjustment) }}" method="POST"
                                class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Complete this adjustment and update inventory?')">
                                    <iconify-icon icon="solar:box-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Complete & Apply
                                </button>
                            </form>
                        @endcan

                        @if ($stockAdjustment->isPending())
                            <a href="{{ route('stock-adjustments.edit', $stockAdjustment) }}" class="btn btn-soft-primary">
                                <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                Edit
                            </a>
                        @endif

                        @can('reject', $stockAdjustment)
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <iconify-icon icon="solar:close-circle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Reject
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-xl-8">
                <!-- Adjustment Details Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Adjustment Details</h5>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'approved' => 'info',
                                'completed' => 'success',
                                'rejected' => 'danger',
                            ];
                            $color = $statusColors[$stockAdjustment->status] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-13">
                            {{ ucfirst($stockAdjustment->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Type:</td>
                                        <td class="fw-medium">
                                            @if($stockAdjustment->type->value === 'increase')
                                                <span class="badge bg-success-subtle text-success">
                                                    <iconify-icon icon="solar:arrow-up-bold" class="align-middle"></iconify-icon>
                                                    Increase
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">
                                                    <iconify-icon icon="solar:arrow-down-bold" class="align-middle"></iconify-icon>
                                                    Decrease
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Reason:</td>
                                        <td class="text-capitalize">{{ str_replace('_', ' ', $stockAdjustment->reason->value) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $stockAdjustment->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td>{{ $stockAdjustment->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Created By:</td>
                                        <td>{{ $stockAdjustment->creator?->name ?? '—' }}</td>
                                    </tr>
                                    @if ($stockAdjustment->approver)
                                        <tr>
                                            <td class="text-muted">{{ $stockAdjustment->isRejected() ? 'Rejected' : 'Approved' }} By:</td>
                                            <td>{{ $stockAdjustment->approver?->name ?? __('Deleted user') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ $stockAdjustment->isRejected() ? 'Rejected' : 'Approved' }} At:</td>
                                            <td>{{ $stockAdjustment->approved_at?->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        @if ($stockAdjustment->notes)
                            <div class="mt-3 p-3 bg-light rounded">
                                <strong class="text-muted">Notes:</strong>
                                <p class="mb-0 mt-1">{{ $stockAdjustment->notes }}</p>
                            </div>
                        @endif

                        @if ($stockAdjustment->rejection_reason)
                            <div class="mt-3 p-3 bg-danger-subtle rounded">
                                <strong class="text-danger">Rejection Reason:</strong>
                                <p class="mb-0 mt-1">{{ $stockAdjustment->rejection_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Items Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Adjustment Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Before</th>
                                        <th class="text-center">Change</th>
                                        <th class="text-center">After</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($stockAdjustment->items as $item)
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
                                            <td class="text-center">{{ number_format($item->quantity_before) }}</td>
                                            <td class="text-center">
                                                @if($stockAdjustment->type->value === 'increase')
                                                    <span class="text-success">+{{ number_format($item->quantity_change) }}</span>
                                                @else
                                                    <span class="text-danger">-{{ number_format($item->quantity_change) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center fw-medium">{{ number_format($item->quantity_after) }}</td>
                                            <td>
                                                @if ($item->item_notes)
                                                    <small class="text-muted">{{ $item->item_notes }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <span class="text-muted">No items in this adjustment.</span>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-xl-4">
                <!-- Summary Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Total Items</span>
                            <span class="fw-medium">{{ $stockAdjustment->items->count() }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Quantity Change</span>
                            @if($stockAdjustment->type->value === 'increase')
                                <span class="fw-medium text-success">+{{ number_format($stockAdjustment->total_quantity_change) }}</span>
                            @else
                                <span class="fw-medium text-danger">-{{ number_format($stockAdjustment->total_quantity_change) }}</span>
                            @endif
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
                                    <h6 class="mb-1">Created</h6>
                                    <p class="text-muted mb-0 fs-13">
                                        {{ $stockAdjustment->created_at?->format('M d, Y H:i') }}
                                        <br>by {{ $stockAdjustment->creator?->name ?? 'Unknown' }}
                                    </p>
                                </div>
                            </li>
                            @if ($stockAdjustment->approved_at && !$stockAdjustment->isRejected())
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-info-subtle text-info rounded-circle">
                                            <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Approved</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $stockAdjustment->approved_at?->format('M d, Y H:i') }}
                                            <br>by {{ $stockAdjustment->approver?->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                            @if ($stockAdjustment->isCompleted())
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-success-subtle text-success rounded-circle">
                                            <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Completed</h6>
                                        <p class="text-muted mb-0 fs-13">Inventory updated</p>
                                    </div>
                                </li>
                            @endif
                            @if ($stockAdjustment->isRejected())
                                <li class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-danger-subtle text-danger rounded-circle">
                                            <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Rejected</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $stockAdjustment->approved_at?->format('M d, Y H:i') }}
                                            <br>by {{ $stockAdjustment->approver?->name ?? 'Unknown' }}
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
    @can('reject', $stockAdjustment)
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('stock-adjustments.reject', $stockAdjustment) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectModalLabel">Reject Adjustment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="rejection_reason" class="form-label">Rejection Reason</label>
                                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"
                                    placeholder="Enter reason for rejection (optional)"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Adjustment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection