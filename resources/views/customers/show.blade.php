@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
    <div>
        <div class="mb-3">
            <a href="{{ route('customers.index') }}" class="btn btn-soft-secondary">
                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon> Back to Customers
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div
                                    class="avatar-lg bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center">
                                    <span class="fs-1">{{ strtoupper(substr($customer->name, 0, 2)) }}</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h3 class="mb-1">{{ $customer->name }}</h3>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    @if ($customer->status === 'active')
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                    @endif

                                    @if ($customer->customer_type === 'wholesale')
                                        <span class="badge bg-info-subtle text-info">
                                            <iconify-icon icon="solar:box-bold-duotone" class="me-1"></iconify-icon>
                                            Wholesale
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <iconify-icon icon="solar:user-bold-duotone" class="me-1"></iconify-icon>
                                            Retail
                                        </span>
                                    @endif

                                    @if ($customer->allow_credit)
                                        <span class="badge bg-warning-subtle text-warning">
                                            <iconify-icon icon="solar:card-bold-duotone" class="me-1"></iconify-icon>
                                            Credit Enabled
                                        </span>
                                    @endif

                                    <span class="text-muted">{{ $customer->code }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                @can('update', $customer)
                                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary">
                                        <iconify-icon icon="solar:pen-2-broken" class="me-1"></iconify-icon>
                                        Edit
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row" style="width: 200px;">Email:</th>
                                        <td>
                                            @if ($customer->email)
                                                <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Phone:</th>
                                        <td>
                                            @if ($customer->phone)
                                                <a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Address:</th>
                                        <td>{{ $customer->address ?? '—' }}</td>
                                    </tr>
                                    @if ($customer->notes)
                                        <tr>
                                            <th scope="row">Notes:</th>
                                            <td>{{ $customer->notes }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if ($customer->allow_credit)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Credit Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <p class="text-muted mb-1">Credit Limit</p>
                                        <h4 class="mb-0">{{ format_currency($customer->credit_limit) }}</h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <p class="text-muted mb-1">Current Balance</p>
                                        <h4
                                            class="mb-0 {{ $customer->credit_balance > 0 ? 'text-warning' : 'text-success' }}">
                                            {{ format_currency($customer->credit_balance) }}
                                        </h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <p class="text-muted mb-1">Available Credit</p>
                                        <h4 class="mb-0 text-success">
                                            {{ format_currency(max(0, $customer->credit_limit - $customer->credit_balance)) }}
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            @if ($customer->credit_limit > 0)
                                <div class="mt-3">
                                    <label class="form-label mb-1">Credit Usage</label>
                                    @php
                                        $creditUsagePercent =
                                            $customer->credit_limit > 0
                                                ? min(100, ($customer->credit_balance / $customer->credit_limit) * 100)
                                                : 0;
                                        $progressClass =
                                            $creditUsagePercent >= 90
                                                ? 'bg-danger'
                                                : ($creditUsagePercent >= 70
                                                    ? 'bg-warning'
                                                    : 'bg-success');
                                    @endphp
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar {{ $progressClass }}" role="progressbar"
                                            style="width: {{ $creditUsagePercent }}%"
                                            aria-valuenow="{{ $creditUsagePercent }}" aria-valuemin="0"
                                            aria-valuemax="100">
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ number_format($creditUsagePercent, 1) }}% of credit limit
                                        used</small>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <h5 class="card-title mb-1">Purchase History</h5>
                            <span class="badge bg-info-subtle text-info">Latest {{ $purchases->count() }} of {{ $customer->sales_count ?? 0 }} purchases</span>
                        </div>
                        <a href="{{ route('customers.purchases', $customer) }}" class="btn btn-primary btn-sm">
                            <iconify-icon icon="solar:list-bold-duotone" class="me-1"></iconify-icon>
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th class="text-end">Total</th>
                                        <th>Sale Status</th>
                                        <th>Payment Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($purchases as $purchase)
                                        <tr>
                                            <td>
                                                <a href="{{ route('sales.show', $purchase) }}" class="fw-medium">{{ $purchase->invoice_number }}</a>
                                                <small class="d-block text-muted">{{ $purchase->source?->name ?? 'Direct sale' }}</small>
                                            </td>
                                            <td>{{ $purchase->created_at->format('M d, Y') }}</td>
                                            <td class="text-end">{{ format_currency($purchase->total_amount) }}</td>
                                            <td>
                                                @if ($purchase->status === 'completed')
                                                    <span class="badge bg-success-subtle text-success">Completed</span>
                                                @elseif ($purchase->status === 'pending')
                                                    <span class="badge bg-warning-subtle text-warning">Pending</span>
                                                @elseif ($purchase->status === 'voided')
                                                    <span class="badge bg-danger-subtle text-danger">Voided</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($purchase->status) }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($purchase->payment_status === 'paid')
                                                    <span class="badge bg-success-subtle text-success">Paid</span>
                                                @elseif ($purchase->payment_status === 'partial')
                                                    <span class="badge bg-warning-subtle text-warning">Partial</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">{{ ucfirst($purchase->payment_status) }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('sales.show', $purchase) }}" class="btn btn-light btn-sm">View</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-5 text-muted">No purchases found for this customer.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Type</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            @if ($customer->customer_type === 'wholesale')
                                <div class="avatar-md bg-info bg-opacity-10 rounded">
                                    <iconify-icon icon="solar:box-bold-duotone"
                                        class="fs-32 text-info avatar-title"></iconify-icon>
                                </div>
                                <div>
                                    <h5 class="mb-1">Wholesale Customer</h5>
                                    <p class="text-muted mb-0 small">Eligible for wholesale/bulk pricing</p>
                                </div>
                            @else
                                <div class="avatar-md bg-secondary bg-opacity-10 rounded">
                                    <iconify-icon icon="solar:user-bold-duotone"
                                        class="fs-32 text-secondary avatar-title"></iconify-icon>
                                </div>
                                <div>
                                    <h5 class="mb-1">Retail Customer</h5>
                                    <p class="text-muted mb-0 small">Standard retail pricing applies</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Total Orders:</span>
                                <span class="badge bg-info-subtle text-info">{{ $customer->sales_count ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Total Spent:</span>
                                <span
                                    class="badge bg-success-subtle text-success">{{ format_currency($customer->total_spent ?? 0) }}</span>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Status:</span>
                                @if ($customer->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        @can('update', $customer)
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:pen-2-broken" class="me-1"></iconify-icon>
                                Edit Customer
                            </a>
                        @endcan

                        @if ($customer->status === 'active')
                            @can('deactivate', $customer)
                                <form action="{{ route('customers.deactivate', $customer) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-soft-warning w-100"
                                        onclick="return confirm('Are you sure you want to deactivate this customer?')">
                                        <iconify-icon icon="solar:pause-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Deactivate
                                    </button>
                                </form>
                            @endcan
                        @else
                            @can('activate', $customer)
                                <form action="{{ route('customers.activate', $customer) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-soft-success w-100">
                                        <iconify-icon icon="solar:play-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Activate
                                    </button>
                                </form>
                            @endcan
                        @endif

                        <a href="{{ route('customers.index') }}" class="btn btn-light w-100">
                            <iconify-icon icon="solar:arrow-left-broken" class="me-1"></iconify-icon>
                            Back to List
                        </a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Customer Code</small>
                            <code class="d-block">{{ $customer->code }}</code>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">UUID</small>
                            <code class="d-block small">{{ $customer->uuid }}</code>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Created At</small>
                            <p class="mb-0">{{ $customer->created_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $customer->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block mb-1">Last Updated</small>
                            <p class="mb-0">{{ $customer->updated_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $customer->updated_at->diffForHumans() }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
