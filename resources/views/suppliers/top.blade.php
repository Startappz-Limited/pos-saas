@extends('layouts.app')

@section('title', 'Top Suppliers')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
                                <li class="breadcrumb-item active">Top Suppliers</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">Top Performing Suppliers</h4>
                    </div>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-light">
                        <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                        Back to Suppliers
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Top Suppliers by Orders & Rating</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Supplier</th>
                                        <th class="text-center">Orders</th>
                                        <th class="text-end">Total Value</th>
                                        <th class="text-end">Avg. Order</th>
                                        <th class="text-center">Rating</th>
                                        <th class="text-center">Delivery Rate</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topSuppliers as $index => $supplier)
                                        <tr>
                                            <td>
                                                @if ($index < 3)
                                                    <span class="badge bg-warning-subtle text-warning rounded-pill">
                                                        {{ $index + 1 }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">{{ $index + 1 }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('suppliers.show', $supplier) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $supplier->name }}
                                                </a>
                                                <br><small class="text-muted">{{ $supplier->code }}</small>
                                            </td>
                                            <td class="text-center fw-medium">{{ $supplier->order_count }}</td>
                                            <td class="text-end">{{ number_format($supplier->total_orders, 2) }}</td>
                                            <td class="text-end">{{ number_format($supplier->average_order_value, 2) }}</td>
                                            <td class="text-center">
                                                @if ($supplier->average_rating)
                                                    <span class="text-warning">
                                                        <iconify-icon icon="solar:star-bold"
                                                            class="align-middle"></iconify-icon>
                                                    </span>
                                                    {{ number_format($supplier->average_rating, 1) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $rate = $supplier->delivery_success_rate;
                                                    $rateColor =
                                                        $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $rateColor }}-subtle text-{{ $rateColor }}">
                                                    {{ number_format($rate, 1) }}%
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('suppliers.show', $supplier) }}"
                                                    class="btn btn-light btn-sm" title="View">
                                                    <iconify-icon icon="solar:eye-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <iconify-icon icon="solar:star-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No top suppliers found. Suppliers need a rating of 4.0
                                                    or above.</p>
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
