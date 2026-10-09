@extends('layouts.app')

@section('title', 'Stock Intakes')

@section('content')
    <div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                    <iconify-icon icon="solar:inbox-in-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Intakes</p>
                                <h4 class="mb-0">{{ $statistics['total_count'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                                    <iconify-icon icon="solar:hourglass-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Pending</p>
                                <h4 class="mb-0">{{ $statistics['pending_count'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Completed</p>
                                <h4 class="mb-0">{{ $statistics['completed_count'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                                    <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Accepted</p>
                                <h4 class="mb-0">{{ number_format($statistics['total_quantity_accepted'] ?? 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Intakes List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Stock Intakes</h4>
                        <div class="d-flex gap-2">
                            @can('create', App\Models\StockIntake::class)
                                <a href="{{ route('stock-intakes.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    New Intake
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('stock-intakes.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search intakes..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    @foreach (App\Enums\StockIntakeStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ request('status') === $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="shop_id" class="form-select">
                                    <option value="">All Shops</option>
                                    @foreach (App\Models\Shop::active()->orderBy('name')->get() as $shop)
                                        <option value="{{ $shop->id }}"
                                            {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="supplier_id" class="form-select">
                                    <option value="">All Suppliers</option>
                                    @foreach (App\Models\Supplier::active()->orderBy('name')->get() as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon> Search
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Intake #</th>
                                        <th>Purchase Order</th>
                                        <th>Product</th>
                                        <th>Shop</th>
                                        <th class="text-center">Received</th>
                                        <th class="text-center">Accepted</th>
                                        <th class="text-center">Rejected</th>
                                        <th>Date</th>
                                        <th>Status</th>
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
                                                @if ($intake->purchaseOrder)
                                                    <a href="{{ route('purchase-orders.show', $intake->purchaseOrder) }}">
                                                        {{ $intake->purchaseOrder->order_number }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Direct Intake</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($intake->product)
                                                    <div>{{ $intake->product->name }}</div>
                                                    @if ($intake->productVariation)
                                                        <small class="text-muted">{{ $intake->productVariation->name }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $intake->shop?->name ?? '—' }}</td>
                                            <td class="text-center">
                                                <span class="fw-medium">{{ number_format($intake->quantity_received) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="text-success">{{ number_format($intake->quantity_accepted) }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if ($intake->quantity_rejected > 0)
                                                    <span class="text-danger">{{ number_format($intake->quantity_rejected) }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>{{ $intake->intake_date?->format('M d, Y') ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'pending' => 'warning',
                                                        'in_progress' => 'info',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                    ];
                                                    $color = $statusColors[$intake->status->value] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                    {{ $intake->status->label() }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('stock-intakes.show', $intake) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>

                                                    @if ($intake->status->canEdit())
                                                        <a href="{{ route('stock-intakes.edit', $intake) }}"
                                                            class="btn btn-soft-primary btn-sm" title="Edit">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endif

                                                    @if ($intake->status->canComplete())
                                                        <form action="{{ route('stock-intakes.complete', $intake) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-soft-success btn-sm"
                                                                title="Complete"
                                                                onclick="return confirm('Complete this intake and update inventory?')">
                                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <iconify-icon icon="solar:inbox-in-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No stock intakes found.</p>
                                                @can('create', App\Models\StockIntake::class)
                                                    <a href="{{ route('stock-intakes.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Stock Intake
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($stockIntakes->hasPages())
                            <div class="mt-4">
                                {{ $stockIntakes->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
