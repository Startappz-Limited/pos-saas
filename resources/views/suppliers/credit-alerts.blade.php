@extends('layouts.app')

@section('title', 'Credit Limit Alerts')

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
                                <li class="breadcrumb-item active">Credit Limit Alerts</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">
                            <iconify-icon icon="solar:danger-triangle-bold-duotone"
                                class="align-middle text-warning me-1"></iconify-icon>
                            Suppliers Near Credit Limit
                        </h4>
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
                        <h5 class="card-title mb-0">Suppliers at 90%+ Credit Utilization</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supplier</th>
                                        <th class="text-end">Credit Limit</th>
                                        <th class="text-end">Current Balance</th>
                                        <th class="text-end">Remaining</th>
                                        <th class="text-center">Utilization</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($suppliers as $supplier)
                                        <tr>
                                            <td>
                                                <a href="{{ route('suppliers.show', $supplier) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $supplier->name }}
                                                </a>
                                                <br><small class="text-muted">{{ $supplier->code }}</small>
                                            </td>
                                            <td class="text-end">{{ number_format($supplier->credit_limit, 2) }}</td>
                                            <td class="text-end fw-medium">
                                                {{ number_format($supplier->current_balance, 2) }}</td>
                                            <td class="text-end">
                                                <span
                                                    class="{{ $supplier->remaining_credit <= 0 ? 'text-danger fw-bold' : 'text-warning' }}">
                                                    {{ number_format($supplier->remaining_credit, 2) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $util = $supplier->credit_utilization;
                                                    $utilColor =
                                                        $util >= 100 ? 'danger' : ($util >= 95 ? 'warning' : 'info');
                                                @endphp
                                                <span class="badge bg-{{ $utilColor }}-subtle text-{{ $utilColor }}">
                                                    {{ number_format($util, 1) }}%
                                                </span>
                                                <div class="progress mt-1"
                                                    style="height: 4px; width: 80px; margin: 0 auto;">
                                                    <div class="progress-bar bg-{{ $utilColor }}"
                                                        style="width: {{ min($util, 100) }}%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $supplier->status->color() }}-subtle text-{{ $supplier->status->color() }}">
                                                    {{ $supplier->status->label() }}
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
                                            <td colspan="7" class="text-center py-5">
                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                    class="fs-1 text-success mb-2"></iconify-icon>
                                                <p class="text-muted">No credit limit alerts. All suppliers are within safe
                                                    limits.</p>
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
