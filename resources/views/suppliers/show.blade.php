@extends('layouts.app')

@section('title', 'Supplier: ' . $supplier->name)

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
                                <li class="breadcrumb-item active">{{ $supplier->name }}</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">
                            {{ $supplier->name }}
                            <span
                                class="badge bg-{{ $supplier->status->color() }}-subtle text-{{ $supplier->status->color() }} fs-13 ms-2">
                                {{ $supplier->status->label() }}
                            </span>
                        </h4>
                    </div>
                    <div class="d-flex gap-2">
                        @can('update', $supplier)
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">
                                <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                Edit
                            </a>
                        @endcan

                        @can('update', $supplier)
                            @if ($supplier->status !== App\Enums\SupplierStatus::ACTIVE)
                                <form action="{{ route('suppliers.activate', $supplier) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <iconify-icon icon="solar:check-circle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Activate
                                    </button>
                                </form>
                            @endif

                            @if ($supplier->status === App\Enums\SupplierStatus::ACTIVE)
                                <form action="{{ route('suppliers.deactivate', $supplier) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning"
                                        onclick="return confirm('Deactivate this supplier?')">
                                        <iconify-icon icon="solar:pause-circle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Deactivate
                                    </button>
                                </form>
                            @endif
                        @endcan

                        @can('delete', $supplier)
                            <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this supplier?')">
                                    <iconify-icon icon="solar:trash-bin-trash-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-xl-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Supplier Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Name:</td>
                                        <td class="fw-medium">{{ $supplier->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Code:</td>
                                        <td><span class="badge bg-light text-dark">{{ $supplier->code }}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email:</td>
                                        <td>
                                            @if ($supplier->email)
                                                <a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Phone:</td>
                                        <td>{{ $supplier->phone ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Mobile:</td>
                                        <td>{{ $supplier->mobile ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Website:</td>
                                        <td>
                                            @if ($supplier->website)
                                                <a href="{{ $supplier->website }}" target="_blank"
                                                    rel="noopener">{{ $supplier->website }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Contact Person:</td>
                                        <td>{{ $supplier->contact_person ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Contact Email:</td>
                                        <td>
                                            @if ($supplier->contact_email)
                                                <a
                                                    href="mailto:{{ $supplier->contact_email }}">{{ $supplier->contact_email }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Contact Phone:</td>
                                        <td>{{ $supplier->contact_phone ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created By:</td>
                                        <td>{{ $supplier->creator?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td>{{ $supplier->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Last Updated:</td>
                                        <td>{{ $supplier->updated_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Address</h5>
                    </div>
                    <div class="card-body">
                        @if ($supplier->address || $supplier->city || $supplier->country)
                            <p class="mb-1">{{ $supplier->address ?? '' }}</p>
                            <p class="mb-1">
                                {{ collect([$supplier->city, $supplier->state, $supplier->postal_code])->filter()->implode(', ') }}
                            </p>
                            <p class="mb-0">{{ $supplier->country ?? '' }}</p>
                        @else
                            <p class="text-muted mb-0">No address provided.</p>
                        @endif
                    </div>
                </div>

                <!-- Financial Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Financial & Business Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 170px;">Tax Number:</td>
                                        <td>{{ $supplier->tax_number ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Registration No:</td>
                                        <td>{{ $supplier->registration_number ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Currency:</td>
                                        <td>{{ $supplier->currency ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Payment Terms:</td>
                                        <td>{{ $supplier->payment_terms_days ? $supplier->payment_terms_days . ' days' : '—' }}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 170px;">Credit Limit:</td>
                                        <td class="fw-medium">
                                            {{ $supplier->credit_limit ? number_format($supplier->credit_limit, 2) : '—' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Current Balance:</td>
                                        <td class="fw-medium">{{ number_format($supplier->current_balance, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Remaining Credit:</td>
                                        <td>
                                            @if ($supplier->credit_limit)
                                                <span
                                                    class="{{ $supplier->remaining_credit < $supplier->credit_limit * 0.1 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($supplier->remaining_credit, 2) }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Credit Utilization:</td>
                                        <td>
                                            @if ($supplier->credit_limit)
                                                {{ number_format($supplier->credit_utilization, 1) }}%
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($supplier->credit_limit)
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Credit Utilization</small>
                                    <small
                                        class="fw-medium">{{ number_format($supplier->credit_utilization, 1) }}%</small>
                                </div>
                                @php
                                    $barColor =
                                        $supplier->credit_utilization >= 90
                                            ? 'danger'
                                            : ($supplier->credit_utilization >= 70
                                                ? 'warning'
                                                : 'success');
                                @endphp
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-{{ $barColor }}"
                                        style="width: {{ min($supplier->credit_utilization, 100) }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Notes -->
                @if ($supplier->notes)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Notes</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $supplier->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-xl-4">
                <!-- Performance Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-1">{{ $supplier->order_count }}</h3>
                                    <small class="text-muted">Total Orders</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-1">
                                        @if ($supplier->average_rating)
                                            <span class="text-warning">
                                                <iconify-icon icon="solar:star-bold" class="align-middle"></iconify-icon>
                                            </span>
                                            {{ number_format($supplier->average_rating, 1) }}
                                        @else
                                            —
                                        @endif
                                    </h3>
                                    <small class="text-muted">Rating</small>
                                </div>
                            </div>
                        </div>

                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Total Orders Value:</td>
                                <td class="fw-medium text-end">{{ number_format($supplier->total_orders, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Avg. Order Value:</td>
                                <td class="fw-medium text-end">{{ number_format($supplier->average_order_value, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">On-Time Deliveries:</td>
                                <td class="text-end">
                                    <span class="text-success fw-medium">{{ $supplier->on_time_deliveries }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Late Deliveries:</td>
                                <td class="text-end">
                                    <span class="text-danger fw-medium">{{ $supplier->late_deliveries }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Delivery Success:</td>
                                <td class="fw-medium text-end">
                                    {{ number_format($supplier->delivery_success_rate, 1) }}%
                                </td>
                            </tr>
                        </table>

                        @if ($supplier->on_time_deliveries + $supplier->late_deliveries > 0)
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Delivery Success Rate</small>
                                    <small
                                        class="fw-medium">{{ number_format($supplier->delivery_success_rate, 1) }}%</small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success"
                                        style="width: {{ $supplier->delivery_success_rate }}%"></div>
                                    <div class="progress-bar bg-danger"
                                        style="width: {{ 100 - $supplier->delivery_success_rate }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
