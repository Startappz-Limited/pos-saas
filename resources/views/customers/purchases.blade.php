@extends('layouts.app')

@section('title', 'Customer Purchase History')

@section('content')
    @php
        $pdfQuery = array_filter($filters, fn ($value) => filled($value));
    @endphp

    <div>
        <div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-soft-secondary">
                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                Back to Customer
            </a>
            <a href="{{ route('customers.purchases.pdf', ['customer' => $customer] + $pdfQuery) }}" class="btn btn-primary">
                <iconify-icon icon="solar:download-bold-duotone" class="align-middle me-1"></iconify-icon>
                Download PDF
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div>
                    <h4 class="card-title mb-1">Purchase History: {{ $customer->name }}</h4>
                    <p class="text-muted mb-0">{{ $customer->code }} · {{ $periodLabel }} · {{ $statusLabel }} · {{ $paymentStatusLabel }}</p>
                </div>
                <span class="badge bg-info-subtle text-info">{{ $summary['count'] }} matching purchases</span>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('customers.purchases', $customer) }}" class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <label for="search" class="form-label">Invoice</label>
                        <input type="search" name="search" id="search" value="{{ $filters['search'] }}" class="form-control @error('search') is-invalid @enderror" placeholder="Invoice number">
                        @error('search')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="date_from" class="form-label">From</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}" class="form-control @error('date_from') is-invalid @enderror">
                        @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="date_to" class="form-label">To</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}" class="form-control @error('date_to') is-invalid @enderror">
                        @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="status" class="form-label">Sale Status</label>
                        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="">All Statuses</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select name="payment_status" id="payment_status" class="form-select @error('payment_status') is-invalid @enderror">
                            <option value="">All Payment Statuses</option>
                            @foreach ($paymentStatusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['payment_status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('customers.purchases', $customer) }}" class="btn btn-light">Reset</a>
                        <button type="submit" class="btn btn-secondary">Filter</button>
                    </div>
                </form>

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Purchases</p><h5 class="mb-0">{{ $summary['count'] }}</h5></div></div>
                    <div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Completed</p><h5 class="mb-0">{{ $summary['completed_count'] }}</h5></div></div>
                    <div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Total Amount</p><h5 class="mb-0">{{ format_currency($summary['total_amount']) }}</h5></div></div>
                    <div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Balance Due</p><h5 class="mb-0">{{ format_currency($summary['balance_due']) }}</h5></div></div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Source</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th>Sale Status</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                <tr>
                                    <td><a href="{{ route('sales.show', $purchase) }}" class="fw-medium">{{ $purchase->invoice_number }}</a></td>
                                    <td>{{ $purchase->created_at->format('M d, Y') }}</td>
                                    <td>{{ $purchase->source?->name ?? 'Direct sale' }}</td>
                                    <td class="text-end">{{ format_currency($purchase->total_amount) }}</td>
                                    <td class="text-end">{{ format_currency($purchase->paid_amount) }}</td>
                                    <td class="text-end">{{ format_currency($purchase->balance_due) }}</td>
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
                                    <td><a href="{{ route('sales.show', $purchase) }}" class="btn btn-light btn-sm">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center py-5 text-muted">No purchases found for these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($purchases->hasPages())
                    <div class="mt-4">{{ $purchases->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection