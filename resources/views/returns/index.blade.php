@extends('layouts.app')

@section('title', 'Returns')

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
                                <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Approved</p>
                                <h4 class="mb-0">{{ $statistics['approved_count'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
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

        <!-- Returns List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Returns</h4>
                        <div class="d-flex gap-2">
                            @can('create', App\Models\SaleReturn::class)
                                <a href="{{ route('returns.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    New Return
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('returns.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search returns..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    @foreach (\App\Enums\ReturnStatus::cases() as $status)
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
                                    @foreach (\App\Enums\ReturnReason::cases() as $reason)
                                        <option value="{{ $reason->value }}"
                                            {{ request('reason') === $reason->value ? 'selected' : '' }}>
                                            {{ $reason->label() }}
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
                                        <th>Return #</th>
                                        <th>Sale</th>
                                        <th>Customer</th>
                                        <th>Reason</th>
                                        <th>Shop</th>
                                        <th class="text-end">Amount</th>
                                        <th>Requested By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($returns as $return)
                                        @php
                                            $customerName = $return->customer?->name
                                                ?? $return->sale?->customer?->name
                                                ?? $return->sale?->walk_in_customer_name
                                                ?? ($return->sale ? 'Walk-in' : '—');
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('returns.show', $return) }}" class="text-dark fw-medium">
                                                    {{ $return->return_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @if ($return->sale)
                                                    <a href="{{ route('sales.show', $return->sale) }}">
                                                        {{ $return->sale->invoice_number ?? '#' . $return->sale->id }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $customerName }}</td>
                                            <td>{{ $return->reason->label() }}</td>
                                            <td>{{ $return->shop?->name ?? '—' }}</td>
                                            <td class="text-end">{{ number_format($return->total_amount, 2) }}</td>
                                            <td>{{ $return->requestedBy?->name ?? '—' }}</td>
                                            <td>{{ $return->created_at?->format('M d, Y') ?? '—' }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $return->status->color() }}-subtle text-{{ $return->status->color() }}">
                                                    {{ $return->status->label() }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('returns.show', $return) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>

                                                    @if ($return->isPending())
                                                        <a href="{{ route('returns.edit', $return) }}"
                                                            class="btn btn-soft-primary btn-sm" title="Edit">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endif

                                                    @can('approve', $return)
                                                        <form action="{{ route('returns.approve', $return) }}" method="POST"
                                                            class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-soft-success btn-sm"
                                                                title="Approve"
                                                                onclick="return confirm('Approve this return?')">
                                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <iconify-icon icon="solar:undo-left-round-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No returns found.</p>
                                                @can('create', App\Models\SaleReturn::class)
                                                    <a href="{{ route('returns.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Return
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($returns->hasPages())
                            <div class="mt-4">
                                {{ $returns->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
