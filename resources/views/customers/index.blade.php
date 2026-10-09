@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <div>

        <div class="row">
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md bg-primary bg-opacity-10 rounded">
                                <iconify-icon icon="solar:users-group-two-rounded-bold-duotone"
                                    class="fs-32 text-primary avatar-title"></iconify-icon>
                            </div>
                            <div>
                                <h4 class="mb-0">All Customers</h4>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="text-muted fw-medium fs-22 mb-0">{{ $statistics['total'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md bg-success bg-opacity-10 rounded">
                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                    class="fs-32 text-success avatar-title"></iconify-icon>
                            </div>
                            <div>
                                <h4 class="mb-0">Active</h4>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="text-muted fw-medium fs-22 mb-0">{{ $statistics['active'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md bg-secondary bg-opacity-10 rounded">
                                <iconify-icon icon="solar:user-bold-duotone"
                                    class="fs-32 text-secondary avatar-title"></iconify-icon>
                            </div>
                            <div>
                                <h4 class="mb-0">Retail</h4>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="text-muted fw-medium fs-22 mb-0">{{ $statistics['retail'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md bg-info bg-opacity-10 rounded">
                                <iconify-icon icon="solar:box-bold-duotone"
                                    class="fs-32 text-info avatar-title"></iconify-icon>
                            </div>
                            <div>
                                <h4 class="mb-0">Wholesale</h4>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="text-muted fw-medium fs-22 mb-0">{{ $statistics['wholesale'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Customers</h4>
                        @can('create', App\Models\Customer::class)
                            <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Add Customer
                            </a>
                        @endcan
                    </div>

                    <div class="card-body">
                        <form method="GET" action="{{ route('customers.index') }}" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search customers..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>
                                        Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="customer_type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="retail" {{ request('customer_type') === 'retail' ? 'selected' : '' }}>
                                        Retail</option>
                                    <option value="wholesale"
                                        {{ request('customer_type') === 'wholesale' ? 'selected' : '' }}>Wholesale</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon> Search
                                </button>
                            </div>
                            @if (request()->hasAny(['search', 'status', 'customer_type']))
                                <div class="col-md-2">
                                    <a href="{{ route('customers.index') }}" class="btn btn-light w-100">
                                        <iconify-icon icon="solar:close-circle-bold-duotone"
                                            class="align-middle"></iconify-icon> Clear
                                    </a>
                                </div>
                            @endif
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Type</th>
                                        <th>Credit</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($customers as $customer)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-sm flex-shrink-0">
                                                        <span
                                                            class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                            {{ strtoupper(substr($customer->name, 0, 2)) }}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <a href="{{ route('customers.show', $customer) }}"
                                                            class="text-dark fw-medium">
                                                            {{ $customer->name }}
                                                        </a>
                                                        <small class="d-block text-muted">{{ $customer->code }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    @if ($customer->email)
                                                        <small class="d-block">{{ $customer->email }}</small>
                                                    @endif
                                                    @if ($customer->phone)
                                                        <small class="text-muted">{{ $customer->phone }}</small>
                                                    @endif
                                                    @if (!$customer->email && !$customer->phone)
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if ($customer->customer_type === 'wholesale')
                                                    <span class="badge bg-info-subtle text-info">
                                                        <iconify-icon icon="solar:box-bold-duotone"
                                                            class="me-1"></iconify-icon>
                                                        Wholesale
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary">
                                                        <iconify-icon icon="solar:user-bold-duotone"
                                                            class="me-1"></iconify-icon>
                                                        Retail
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($customer->allow_credit)
                                                    <div>
                                                        <small class="d-block text-muted">Limit:
                                                            {{ format_currency($customer->credit_limit) }}</small>
                                                        @if ($customer->credit_balance > 0)
                                                            <small class="text-warning">Balance:
                                                                {{ format_currency($customer->credit_balance) }}</small>
                                                        @else
                                                            <small class="text-success">No balance</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($customer->status === 'active')
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    @can('view', $customer)
                                                        <a href="{{ route('customers.show', $customer) }}"
                                                            class="btn btn-light btn-sm" data-bs-toggle="tooltip"
                                                            title="View">
                                                            <iconify-icon icon="solar:eye-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('update', $customer)
                                                        <a href="{{ route('customers.edit', $customer) }}"
                                                            class="btn btn-soft-primary btn-sm" data-bs-toggle="tooltip"
                                                            title="Edit">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('delete', $customer)
                                                        <form action="{{ route('customers.destroy', $customer) }}"
                                                            method="POST" class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this customer?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-soft-danger btn-sm"
                                                                data-bs-toggle="tooltip" title="Delete">
                                                                <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <iconify-icon icon="solar:users-group-two-rounded-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No customers found.</p>
                                                @can('create', App\Models\Customer::class)
                                                    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="me-1"></iconify-icon>
                                                        Create First Customer
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($customers->hasPages())
                            <div class="mt-4">
                                {{ $customers->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
