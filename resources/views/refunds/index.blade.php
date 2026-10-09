@extends('layouts.app')

@section('title', 'Refunds')

@section('content')
    <div>
        <!-- Refunds List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Refunds</h4>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('refunds.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search refunds..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending
                                    </option>
                                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>
                                        Processing</option>
                                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>
                                        Completed</option>
                                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed
                                    </option>
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
                                        <th>Refund #</th>
                                        <th>Return #</th>
                                        <th>Customer</th>
                                        <th>Method</th>
                                        <th>Shop</th>
                                        <th class="text-end">Amount</th>
                                        <th>Processed By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($refunds as $refund)
                                        <tr>
                                            <td>
                                                <a href="{{ route('refunds.show', $refund) }}" class="text-dark fw-medium">
                                                    {{ $refund->refund_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @if ($refund->saleReturn)
                                                    <a href="{{ route('returns.show', $refund->saleReturn) }}">
                                                        {{ $refund->saleReturn->return_number }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $refund->customer?->name ?? '—' }}</td>
                                            <td>{{ $refund->method->label() }}</td>
                                            <td>{{ $refund->shop?->name ?? '—' }}</td>
                                            <td class="text-end fw-medium">{{ number_format($refund->amount, 2) }}</td>
                                            <td>{{ $refund->processedBy?->name ?? '—' }}</td>
                                            <td>{{ $refund->created_at?->format('M d, Y') ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                    ];
                                                    $color = $statusColors[$refund->status] ?? 'secondary';
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                    {{ ucfirst($refund->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('refunds.show', $refund) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>

                                                    @can('process', $refund)
                                                        <form action="{{ route('refunds.process', $refund) }}" method="POST"
                                                            class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-soft-success btn-sm"
                                                                title="Process"
                                                                onclick="return confirm('Process this refund?')">
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
                                                <iconify-icon icon="solar:wallet-money-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No refunds found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($refunds->hasPages())
                            <div class="mt-4">
                                {{ $refunds->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
