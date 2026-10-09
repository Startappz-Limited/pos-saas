@extends('layouts.app')

@section('title', 'Refund: ' . $refund->refund_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('refunds.index') }}">Refunds</a></li>
                                <li class="breadcrumb-item active">{{ $refund->refund_number }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">{{ $refund->refund_number }}</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @can('process', $refund)
                            <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                data-bs-target="#processModal">
                                <iconify-icon icon="solar:check-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Process Refund
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-xl-8">
                <!-- Refund Details -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Refund Details</h5>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'processing' => 'info',
                                'completed' => 'success',
                                'failed' => 'danger',
                            ];
                            $color = $statusColors[$refund->status] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-13">
                            {{ ucfirst($refund->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Return:</td>
                                        <td>
                                            @if ($refund->saleReturn)
                                                <a href="{{ route('returns.show', $refund->saleReturn) }}">
                                                    {{ $refund->saleReturn->return_number }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Method:</td>
                                        <td>{{ $refund->method->label() }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Customer:</td>
                                        <td>{{ $refund->customer?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $refund->shop?->name ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Amount:</td>
                                        <td class="fw-bold fs-16">{{ number_format($refund->amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Processed By:</td>
                                        <td>{{ $refund->processedBy?->name ?? '—' }}</td>
                                    </tr>
                                    @if ($refund->processed_at)
                                        <tr>
                                            <td class="text-muted">Processed At:</td>
                                            <td>{{ $refund->processed_at->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endif
                                    @if ($refund->transaction_id)
                                        <tr>
                                            <td class="text-muted">Transaction ID:</td>
                                            <td><code>{{ $refund->transaction_id }}</code></td>
                                        </tr>
                                    @endif
                                    @if ($refund->reference_number)
                                        <tr>
                                            <td class="text-muted">Reference #:</td>
                                            <td>{{ $refund->reference_number }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        @if ($refund->notes)
                            <div class="mt-3 p-3 bg-light rounded">
                                <strong class="text-muted">Notes:</strong>
                                <p class="mb-0 mt-1">{{ $refund->notes }}</p>
                            </div>
                        @endif

                        @if ($refund->failure_reason)
                            <div class="mt-3 p-3 bg-danger-subtle rounded">
                                <strong class="text-danger">Failure Reason:</strong>
                                <p class="mb-0 mt-1">{{ $refund->failure_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Return Items (from linked return) -->
                @if ($refund->saleReturn && $refund->saleReturn->items->count())
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Returned Items</h5>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($refund->saleReturn->items as $item)
                                            <tr>
                                                <td>{{ $item->product?->name ?? 'Unknown' }}</td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                                <td class="text-end fw-medium">{{ number_format($item->total_price, 2) }}
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Timeline</h5>
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
                                        {{ $refund->created_at?->format('M d, Y H:i') }}
                                    </p>
                                </div>
                            </li>
                            @if ($refund->isCompleted())
                                <li class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-success-subtle text-success rounded-circle">
                                            <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Processed</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $refund->processed_at?->format('M d, Y H:i') }}
                                            <br>by {{ $refund->processedBy?->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                            @if ($refund->isFailed())
                                <li class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="avatar avatar-sm bg-danger-subtle text-danger rounded-circle">
                                            <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Failed</h6>
                                        <p class="text-muted mb-0 fs-13">{{ $refund->failure_reason ?? 'Unknown reason' }}
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

    <!-- Process Modal -->
    @can('process', $refund)
        <div class="modal fade" id="processModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('refunds.process', $refund) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Process Refund</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Confirm processing of refund <strong>{{ $refund->refund_number }}</strong> for
                                <strong>{{ number_format($refund->amount, 2) }}</strong> via
                                <strong>{{ $refund->method->label() }}</strong>.
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control"
                                    placeholder="Optional transaction reference">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Confirm & Process</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection
