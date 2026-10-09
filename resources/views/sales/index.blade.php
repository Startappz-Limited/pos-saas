@extends('layouts.app')

@section('title', 'Sales')

@section('content')

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:dollar-bold-duotone" class="fs-36 text-success"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['total'] ?? 0 }}</h3>
                    <p class="text-muted">Total Sales</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['completed'] ?? 0 }}</h3>
                    <p class="text-muted">Completed</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['pending'] ?? 0 }}</h3>
                    <p class="text-muted">Pending</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:close-circle-bold-duotone" class="fs-36 text-danger"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['voided'] ?? 0 }}</h3>
                    <p class="text-muted">Voided</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales List Card -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">All Sales</h5>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <a href="{{ route('sales.create') }}" class="btn btn-primary add-btn">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon> New
                            Sale
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Search and Filters -->
            <form method="GET" action="{{ route('sales.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-4 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="Search by invoice or customer..." value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    @if (($shops ?? collect())->isNotEmpty())
                        <div class="col-xxl-2 col-sm-6">
                            <select class="form-select" name="shop_id">
                                <option value="">All Accessible Shops</option>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected(($selectedShopId ?? null) === $shop->id)>
                                        {{ $shop->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-xxl-2 col-sm-6">
                        <select class="form-select" name="status" id="status-filter">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed
                            </option>
                            <option value="voided" {{ request('status') == 'voided' ? 'selected' : '' }}>Voided</option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon> Filter
                        </button>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <a href="{{ route('sales.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon> Reset
                        </a>
                    </div>
                </div>
            </form>

            @if ($sales->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Invoice #</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Date</th>
                                <th scope="col">Total Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sales as $sale)
                                <tr>
                                    <td>
                                        <a href="{{ route('sales.show', $sale) }}" class="fw-semibold text-primary">
                                            {{ $sale->invoice_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($sale->customer)
                                            {{ $sale->customer->name }}
                                            <small
                                                class="text-muted d-block">{{ ucfirst($sale->customer->customer_type) }}</small>
                                        @else
                                            <span class="text-muted">Walk-in Customer</span>
                                        @endif
                                    </td>
                                    <td>{{ $sale->created_at->format('M d, Y') }}</td>
                                    <td>{{ format_currency($sale->total_amount) }}</td>
                                    <td>
                                        @if ($sale->status === 'completed')
                                            <span class="badge bg-success-subtle text-success">
                                                <iconify-icon icon="solar:check-circle-bold"
                                                    class="align-middle"></iconify-icon> Completed
                                            </span>
                                        @elseif($sale->status === 'pending')
                                            <span class="badge bg-warning-subtle text-warning">
                                                <iconify-icon icon="solar:clock-circle-bold"
                                                    class="align-middle"></iconify-icon> Pending
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">
                                                <iconify-icon icon="solar:close-circle-bold"
                                                    class="align-middle"></iconify-icon> Voided
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($sale->payment_status === 'paid')
                                            <span class="badge bg-success">Paid</span>
                                        @elseif($sale->payment_status === 'partial')
                                            <span class="badge bg-warning">Partial</span>
                                        @else
                                            <span class="badge bg-danger">Unpaid</span>
                                        @endif
                                        @if ($sale->is_cod)
                                            <span class="badge bg-warning-subtle text-warning">COD</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-soft-info">
                                                <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                            </a>
                                            @if ($sale->status === 'pending')
                                                <a href="{{ route('sales.edit', $sale) }}"
                                                    class="btn btn-sm btn-soft-primary">
                                                    <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $sales->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Sales Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('search') ? 'Try adjusting your search or filters.' : 'Start by creating your first sale.' }}
                        </p>
                        <a href="{{ route('sales.create') }}" class="btn btn-primary mt-3">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            New Sale
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
