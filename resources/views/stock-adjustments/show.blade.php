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
                            @if ($stockAdjustment->created_by === auth()->id())
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#selfApproveModal">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" class="align-middle me-1" aria-hidden="true"></iconify-icon>
                                    {{ __('Approve your own') }}
                                </button>
                            @else
                                <form action="{{ route('stock-adjustments.approve', $stockAdjustment) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success"
                                        onclick="return confirm(@js(__('Approve this adjustment and update the stock?')))">
                                        <iconify-icon icon="solar:check-circle-bold-duotone"
                                            class="align-middle me-1" aria-hidden="true"></iconify-icon>
                                        {{ __('Approve & Apply') }}
                                    </button>
                                </form>
                            @endif
                        @endcan

                        @can('update', $stockAdjustment)
                            <a href="{{ route('stock-adjustments.edit', $stockAdjustment) }}" class="btn btn-soft-primary">
                                <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1" aria-hidden="true"></iconify-icon>
                                {{ __('Edit') }}
                            </a>
                        @endcan

                        @can('cancel', $stockAdjustment)
                            <form action="{{ route('stock-adjustments.cancel', $stockAdjustment) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-soft-secondary"
                                    onclick="return confirm(@js(__('Cancel this adjustment? It keeps its number and moves no stock.')))">
                                    {{ __('Cancel adjustment') }}
                                </button>
                            </form>
                        @endcan

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

        @if ($stockAdjustment->isPending() || $stockAdjustment->isApproved())
            <div class="alert alert-warning" role="status">
                @if ($stockAdjustment->isApproved())
                    {{ __('Approved before approving applied the stock: its stock was never changed. Approve it to apply it now, at today\'s stock, or reject it.') }}
                @elseif ($stockAdjustment->created_by === auth()->id() && auth()->user()->can('approve', $stockAdjustment))
                    {{ __('Nobody else in the business can approve this adjustment, so as the owner you may approve your own, with a note. It will be marked self-approved.') }}
                @elseif ($stockAdjustment->created_by === auth()->id())
                    {{ __('This adjustment waits for someone else holding the approve permission. They have been notified. You can\'t approve your own.') }}
                @else
                    {{ __('This adjustment waits for approval. Approving it applies the stock.') }}
                @endif
            </div>
        @endif

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
                                'cancelled' => 'secondary',
                            ];
                            $color = $statusColors[$stockAdjustment->status] ?? 'secondary';
                        @endphp
                        <span>
                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-13">
                                {{ __(ucfirst($stockAdjustment->status)) }}
                            </span>
                            @if ($stockAdjustment->self_approved)
                                <span class="badge bg-warning-subtle text-warning fs-13">{{ __('Self-approved') }}</span>
                            @elseif ($stockAdjustment->auto_applied)
                                <span class="badge bg-light text-body fs-13">{{ __('Applied on saving') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Type:</td>
                                        <td class="fw-medium">
                                            @include('stock-adjustments._type', ['type' => $stockAdjustment->type])
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Reason:</td>
                                        <td>{{ __($stockAdjustment->reason->label()) }}</td>
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
                                            <td>{{ $stockAdjustment->approver->name }}</td>
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

                        @if ($stockAdjustment->approval_note)
                            <div class="mt-3 p-3 bg-warning-subtle rounded">
                                <strong>{{ __('Self-approval note:') }}</strong>
                                <p class="mb-0 mt-1">{{ $stockAdjustment->approval_note }}</p>
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
                                        <th>{{ __('Product') }}</th>
                                        @if ($stockAdjustment->isCompleted())
                                            <th class="text-center">{{ __('Before') }}</th>
                                        @else
                                            <th class="text-center">{{ __('Stock now') }}</th>
                                        @endif
                                        <th class="text-center">{{ __('Change') }}</th>
                                        @if ($stockAdjustment->isCompleted())
                                            <th class="text-center">{{ __('After') }}</th>
                                        @else
                                            <th class="text-end">{{ __('Unit cost') }}</th>
                                        @endif
                                        <th>{{ __('Notes') }}</th>
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
                                                    <span class="text-muted">{{ __('Product Deleted') }}</span>
                                                @endif
                                                @if ($item->variation && ! $item->variation->is_default)
                                                    <small class="d-block text-muted">{{ $item->variation->name }}</small>
                                                @endif
                                            </td>
                                            @if ($stockAdjustment->isCompleted())
                                                <td class="text-center">{{ $item->quantity_before === null ? '—' : number_format($item->quantity_before) }}</td>
                                            @else
                                                <td class="text-center">{{ $item->variation_id ? number_format($ledger->quantity($item->shop_id, $item->variation_id)) : '—' }}</td>
                                            @endif
                                            <td class="text-center">
                                                @if ($item->quantity_change > 0)
                                                    <span class="text-success">+{{ number_format($item->quantity_change) }}</span>
                                                @else
                                                    <span class="text-danger">−{{ number_format(abs($item->quantity_change)) }}</span>
                                                @endif
                                            </td>
                                            @if ($stockAdjustment->isCompleted())
                                                <td class="text-center fw-medium">{{ $item->quantity_after === null ? '—' : number_format($item->quantity_after) }}</td>
                                            @else
                                                @php($unitCost = $item->product ? $approval->unitCost($item) : null)
                                                <td class="text-end">
                                                    @if ($unitCost === null)
                                                        <span class="badge bg-warning-subtle text-warning">{{ __('Cost unknown') }}</span>
                                                    @else
                                                        @can('viewCost', App\Models\Product::class)
                                                            {{ number_format($unitCost, 2) }}
                                                        @else
                                                            <span class="text-muted">{{ __('Known') }}</span>
                                                        @endcan
                                                    @endif
                                                </td>
                                            @endif
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
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">{{ __('Units added') }}</span>
                            <span class="fw-medium text-success">+{{ number_format($stockAdjustment->items->where('quantity_change', '>', 0)->sum('quantity_change')) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">{{ __('Units removed') }}</span>
                            <span class="fw-medium text-danger">−{{ number_format(abs($stockAdjustment->items->where('quantity_change', '<', 0)->sum('quantity_change'))) }}</span>
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
                                        <h6 class="mb-1">{{ __('Applied') }}</h6>
                                        <p class="text-muted mb-0 fs-13">{{ __('Stock updated') }}</p>
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
                                <label for="rejection_reason" class="form-label">{{ __('Rejection Reason') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('rejection_reason') is-invalid @enderror" id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" required
                                    placeholder="{{ __('Why is it rejected? Its creator is told.') }}"></textarea>
                                @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

    @can('approve', $stockAdjustment)
        @if ($stockAdjustment->created_by === auth()->id())
            <div class="modal fade" id="selfApproveModal" tabindex="-1" aria-labelledby="selfApproveModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('stock-adjustments.approve', $stockAdjustment) }}" method="POST">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="selfApproveModalLabel">{{ __('Approve your own adjustment') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted">{{ __('Nobody else in the business can approve it. It will be marked self-approved and audited.') }}</p>
                                <label for="approval_note" class="form-label">{{ __('Why are you approving it yourself?') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('approval_note') is-invalid @enderror" id="approval_note" name="approval_note" rows="3" maxlength="500" required></textarea>
                                @error('approval_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-success">{{ __('Approve & Apply') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endcan
