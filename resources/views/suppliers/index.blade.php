@extends('layouts.app')

@section('title', 'Suppliers')

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
                                    <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Suppliers</p>
                                <h4 class="mb-0">{{ $statistics['total'] ?? 0 }}</h4>
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
                                <p class="text-uppercase fw-medium text-muted mb-1">Active</p>
                                <h4 class="mb-0">{{ $statistics['active'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:danger-triangle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Near Credit Limit</p>
                                <h4 class="mb-0">{{ $statistics['suppliers_near_limit'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:dollar-minimalistic-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Orders Value</p>
                                <h4 class="mb-0">{{ number_format($statistics['total_orders_value'] ?? 0, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suppliers List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Suppliers</h4>
                        <div class="d-flex gap-2">
                            @can('create', App\Models\Supplier::class)
                                <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    New Supplier
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('suppliers.index') }}" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control"
                                    placeholder="Search by name, code, email..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    @foreach (App\Enums\SupplierStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ request('status') === $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="country" class="form-control" placeholder="Filter by country"
                                    value="{{ request('country') }}">
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
                                        <th>Supplier</th>
                                        <th>Code</th>
                                        <th>Contact</th>
                                        <th>Country</th>
                                        <th class="text-center">Orders</th>
                                        <th class="text-center">Rating</th>
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
                                                @if ($supplier->email)
                                                    <br><small class="text-muted">{{ $supplier->email }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $supplier->code }}</span>
                                            </td>
                                            <td>
                                                @if ($supplier->contact_person)
                                                    {{ $supplier->contact_person }}
                                                    @if ($supplier->phone)
                                                        <br><small class="text-muted">{{ $supplier->phone }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $supplier->country ?? '—' }}</td>
                                            <td class="text-center">
                                                <span class="fw-medium">{{ $supplier->order_count }}</span>
                                            </td>
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
                                            <td>
                                                <span
                                                    class="badge bg-{{ $supplier->status->color() }}-subtle text-{{ $supplier->status->color() }}">
                                                    {{ $supplier->status->label() }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('suppliers.show', $supplier) }}"
                                                        class="btn btn-light btn-sm" title="View">
                                                        <iconify-icon icon="solar:eye-broken"
                                                            class="align-middle fs-18"></iconify-icon>
                                                    </a>
                                                    @can('update', $supplier)
                                                        <a href="{{ route('suppliers.edit', $supplier) }}"
                                                            class="btn btn-soft-primary btn-sm" title="Edit">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <iconify-icon icon="solar:users-group-rounded-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No suppliers found.</p>
                                                @can('create', App\Models\Supplier::class)
                                                    <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Add First Supplier
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($suppliers->hasPages())
                            <div class="mt-4">
                                {{ $suppliers->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
