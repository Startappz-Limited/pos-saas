@extends('layouts.app')

@section('title', 'Low Stock Alerts')

@section('content')
    <div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-4 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                                    <iconify-icon icon="solar:danger-triangle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Alerts</p>
                                <h4 class="mb-0">{{ $statistics['total_alerts'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                                    <iconify-icon icon="solar:bell-bing-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Active Alerts</p>
                                <h4 class="mb-0">{{ $statistics['active_alerts'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Acknowledged</p>
                                <h4 class="mb-0">{{ $statistics['acknowledged_alerts'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Low Stock Alerts</h4>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('low-stock-alerts.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
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
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="acknowledged" {{ request('status') === 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
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
                                        <th>Product</th>
                                        <th>Shop</th>
                                        <th class="text-center">Current</th>
                                        <th class="text-center">Threshold</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($alerts as $alert)
                                        <tr>
                                            <td>
                                                @if ($alert->product)
                                                    {{ $alert->product->name }}
                                                    @if ($alert->variation)
                                                        <br><small class="text-muted">{{ $alert->variation->name }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $alert->shop?->name ?? '—' }}</td>
                                            <td class="text-center">
                                                <span class="text-danger fw-medium">{{ number_format($alert->current_quantity) }}</span>
                                            </td>
                                            <td class="text-center">{{ number_format($alert->threshold_quantity) }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'pending' => 'danger',
                                                        'acknowledged' => 'warning',
                                                        'resolved' => 'success',
                                                        'ignored' => 'secondary',
                                                    ];
                                                    $color = $statusColors[$alert->status->value] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                    {{ ucfirst($alert->status->value) }}
                                                </span>
                                            </td>
                                            <td>{{ $alert->created_at?->format('M d, Y') ?? '—' }}</td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('low-stock-alerts.show', $alert) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>

                                                    @if ($alert->isPending())
                                                        @can('acknowledge', $alert)
                                                            <form action="{{ route('low-stock-alerts.acknowledge', $alert) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-soft-warning btn-sm"
                                                                    title="Acknowledge">
                                                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                                                        class="align-middle fs-18"></iconify-icon>
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <iconify-icon icon="solar:shield-check-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No low stock alerts found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($alerts->hasPages())
                            <div class="mt-4">
                                {{ $alerts->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection