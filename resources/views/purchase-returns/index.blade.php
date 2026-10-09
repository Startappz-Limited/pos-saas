@extends('layouts.app')

@section('title', 'Supplier Returns')

@section('content')
    <div>
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                    <iconify-icon icon="solar:undo-left-round-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Returns</p>
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
                                <p class="text-uppercase fw-medium text-muted mb-1">Open</p>
                                <h4 class="mb-0">{{ $statistics['open_count'] ?? 0 }}</h4>
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
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                    <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Returned</p>
                                <h4 class="mb-0">{{ $statistics['shipped_count'] ?? 0 }}</h4>
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
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Supplier Returns</h4>
                        @can('create', App\Models\PurchaseReturn::class)
                            <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary btn-sm">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                New Supplier Return
                            </a>
                        @endcan
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('purchase-returns.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search returns..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    @foreach (\App\Enums\PurchaseReturnStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ request('status') === $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="reason" class="form-select">
                                    <option value="">All Reasons</option>
                                    @foreach (\App\Enums\PurchaseReturnReason::cases() as $reason)
                                        <option value="{{ $reason->value }}"
                                            {{ request('reason') === $reason->value ? 'selected' : '' }}>
                                            {{ $reason->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="supplier_id" class="form-select">
                                    <option value="">All Suppliers</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="shop_id" class="form-select">
                                    <option value="">All Shops</option>
                                    @foreach ($shops as $shop)
                                        <option value="{{ $shop->id }}"
                                            {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon>
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Return #</th>
                                        <th>Supplier</th>
                                        <th>Shop</th>
                                        <th>Source</th>
                                        <th>Reason</th>
                                        <th class="text-center">Items</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($purchaseReturns as $purchaseReturn)
                                        <tr>
                                            <td>
                                                <a href="{{ route('purchase-returns.show', $purchaseReturn) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $purchaseReturn->return_number }}
                                                </a>
                                            </td>
                                            <td>{{ $purchaseReturn->supplier?->name ?? '—' }}</td>
                                            <td>{{ $purchaseReturn->shop?->name ?? '—' }}</td>
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
                                            <td>{{ $purchaseReturn->reason->label() }}</td>
                                            <td class="text-center">{{ $purchaseReturn->items->count() }}</td>
                                            <td class="text-end">{{ number_format($purchaseReturn->total_amount, 2) }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $purchaseReturn->status->color() }}-subtle text-{{ $purchaseReturn->status->color() }}">
                                                    {{ $purchaseReturn->status->label() }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('purchase-returns.show', $purchaseReturn) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>
                                                    @if ($purchaseReturn->canEdit())
                                                        @can('update', $purchaseReturn)
                                                            <a href="{{ route('purchase-returns.edit', $purchaseReturn) }}"
                                                                class="btn btn-soft-primary btn-sm" title="Edit">
                                                                <iconify-icon icon="solar:pen-2-broken"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <iconify-icon icon="solar:undo-left-round-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No supplier returns found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($purchaseReturns->hasPages())
                            <div class="mt-4">
                                {{ $purchaseReturns->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
