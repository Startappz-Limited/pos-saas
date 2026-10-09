@extends('layouts.app')

@section('title', 'Pending Stock Intakes')

@section('content')
    <div>

        <!-- Page Header -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Pending Stock Intakes</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('stock-intakes.index') }}">Stock Intakes</a></li>
                            <li class="breadcrumb-item active">Pending</li>
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
                            Pending Intakes
                            <span class="badge bg-warning-subtle text-warning ms-1">{{ $stockIntakes->count() }}</span>
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('stock-intakes.create') }}" class="btn btn-primary btn-sm">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                New Intake
                            </a>
                            <a href="{{ route('stock-intakes.index') }}" class="btn btn-light btn-sm">
                                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                Back to All
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Intake #</th>
                                        <th>Product</th>
                                        <th>Supplier</th>
                                        <th>Shop</th>
                                        <th>Qty Received</th>
                                        <th>Qty Accepted</th>
                                        <th>PO #</th>
                                        <th>Intake Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($stockIntakes as $intake)
                                        <tr>
                                            <td>
                                                <a href="{{ route('stock-intakes.show', $intake) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $intake->intake_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @if ($intake->product)
                                                    {{ $intake->product->name }}
                                                    <br><small class="text-muted">{{ $intake->product->sku }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $intake->supplier->name ?? '—' }}</td>
                                            <td>{{ $intake->shop->name ?? '—' }}</td>
                                            <td>{{ number_format($intake->quantity_received, 2) }}</td>
                                            <td>{{ number_format($intake->quantity_accepted, 2) }}</td>
                                            <td>
                                                @if ($intake->purchaseOrder)
                                                    <a href="{{ route('purchase-orders.show', $intake->purchaseOrder) }}">
                                                        {{ $intake->purchaseOrder->order_number }}
                                                    </a>
                                                @else
                                                    <span class="badge bg-light text-muted">Manual</span>
                                                @endif
                                            </td>
                                            <td>{{ $intake->intake_date?->format('M d, Y') ?? '—' }}</td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('stock-intakes.show', $intake) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>
                                                    @can('complete', $intake)
                                                        <form action="{{ route('stock-intakes.complete', $intake) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-success btn-sm"
                                                                title="Complete"
                                                                onclick="return confirm('Complete this stock intake? Stock will be added to inventory.')">
                                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                    @can('cancel', $intake)
                                                        <form action="{{ route('stock-intakes.cancel', $intake) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Cancel"
                                                                onclick="return confirm('Cancel this stock intake?')">
                                                                <iconify-icon icon="solar:close-circle-bold-duotone"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                    class="fs-1 text-success mb-2"></iconify-icon>
                                                <p class="text-muted">No pending stock intakes. All caught up!</p>
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
