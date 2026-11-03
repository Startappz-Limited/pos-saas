@extends('layouts.app')

@section('title', 'Stock Adjustments')

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
                                    <iconify-icon icon="solar:tuning-2-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Adjustments</p>
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
                                    <iconify-icon icon="solar:add-square-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Increases</p>
                                <h4 class="mb-0">{{ $statistics['increase_count'] ?? 0 }}</h4>
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
                                <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                                    <iconify-icon icon="solar:minus-square-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Decreases</p>
                                <h4 class="mb-0">{{ $statistics['decrease_count'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Adjustments List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Stock Adjustments</h4>
                        <div class="d-flex gap-2">
                            @can('create', App\Models\StockAdjustment::class)
                                <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    New Adjustment
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('stock-adjustments.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search adjustments..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option>
                                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    @foreach (App\Enums\AdjustmentType::cases() as $type)
                                        <option value="{{ $type->value }}"
                                            {{ request('type') === $type->value ? 'selected' : '' }}>
                                            {{ __($type->label()) }}
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
                            <div class="col-md-1 d-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="self_approved" value="1" id="filter-self-approved" @checked(request()->boolean('self_approved'))>
                                    <label class="form-check-label small" for="filter-self-approved">{{ __('Self-approved') }}</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon> Search
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Adjustment #</th>
                                        <th>Type</th>
                                        <th>Reason</th>
                                        <th>Shop</th>
                                        <th class="text-center">Items</th>
                                        <th>Created By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($adjustments as $adjustment)
                                        <tr>
                                            <td>
                                                <a href="{{ route('stock-adjustments.show', $adjustment) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $adjustment->adjustment_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @include('stock-adjustments._type', ['type' => $adjustment->type])
                                            </td>
                                            <td>
                                                {{ __($adjustment->reason->label()) }}
                                            </td>
                                            <td>{{ $adjustment->shop?->name ?? '—' }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">{{ $adjustment->items->count() }}</span>
                                            </td>
                                            <td>{{ $adjustment->creator?->name ?? '—' }}</td>
                                            <td>{{ $adjustment->created_at?->format('M d, Y') ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'pending' => 'warning',
                                                        'approved' => 'info',
                                                        'completed' => 'success',
                                                        'rejected' => 'danger',
                                                        'cancelled' => 'secondary',
                                                    ];
                                                    $color = $statusColors[$adjustment->status] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                    {{ ucfirst($adjustment->status) }}
                                                </span>
                                                @if ($adjustment->self_approved)
                                                    <span class="badge bg-warning-subtle text-warning">{{ __('Self-approved') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('stock-adjustments.show', $adjustment) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>

                                                    @if ($adjustment->isPending())
                                                        <a href="{{ route('stock-adjustments.edit', $adjustment) }}"
                                                            class="btn btn-soft-primary btn-sm" title="Edit">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endif

                                                    @can('approve', $adjustment)
                                                        <form action="{{ route('stock-adjustments.approve', $adjustment) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-soft-success btn-sm"
                                                                title="{{ __('Approve & Apply') }}" aria-label="{{ __('Approve & Apply') }}"
                                                                onclick="return confirm(@js(__('Approve this adjustment and update the stock?')))">
                                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                                    class="align-middle fs-18" aria-hidden="true"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <iconify-icon icon="solar:tuning-2-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No stock adjustments found.</p>
                                                @can('create', App\Models\StockAdjustment::class)
                                                    <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Stock Adjustment
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($adjustments->hasPages())
                            <div class="mt-4">
                                {{ $adjustments->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection