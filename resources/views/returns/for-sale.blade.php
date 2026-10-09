@extends('layouts.app')

@section('title', 'Returns for Sale #' . $sale->invoice_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
                                <li class="breadcrumb-item"><a
                                        href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></li>
                                <li class="breadcrumb-item active">Returns</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">Returns for Sale #{{ $sale->invoice_number }}</h4>
                        @if ($sale->customer)
                            <p class="text-muted mb-0">Customer: {{ $sale->customer->name }}</p>
                        @endif
                    </div>
                    @can('create', App\Models\SaleReturn::class)
                        <a href="{{ route('returns.create', ['sale_id' => $sale->id]) }}" class="btn btn-primary">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                            New Return
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if ($returns->isEmpty())
            <div class="card">
                <div class="card-body text-center py-5">
                    <iconify-icon icon="solar:rewind-back-bold-duotone" class="fs-48 text-muted mb-3"></iconify-icon>
                    <h5 class="text-muted">No Returns Found</h5>
                    <p class="text-muted mb-0">There are no returns for this sale.</p>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Return #</th>
                                    <th>Reason</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($returns as $return)
                                    <tr>
                                        <td>
                                            <a href="{{ route('returns.show', $return) }}" class="fw-medium">
                                                {{ $return->return_number }}
                                            </a>
                                        </td>
                                        <td>{{ $return->reason->label() }}</td>
                                        <td class="text-center">{{ $return->items_count ?? $return->items->count() }}</td>
                                        <td class="text-end">{{ number_format($return->refund_amount, 2) }}</td>
                                        <td>
                                            @php
                                                $statusColors = [
                                                    'pending' => 'warning',
                                                    'approved' => 'info',
                                                    'rejected' => 'danger',
                                                    'received' => 'primary',
                                                    'inspected' => 'secondary',
                                                    'completed' => 'success',
                                                ];
                                                $color = $statusColors[$return->status->value] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                {{ ucfirst($return->status->value) }}
                                            </span>
                                        </td>
                                        <td>{{ $return->created_at?->format('M d, Y') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('returns.show', $return) }}" class="btn btn-sm btn-light">
                                                <iconify-icon icon="solar:eye-bold-duotone"></iconify-icon>
                                            </a>
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
@endsection
