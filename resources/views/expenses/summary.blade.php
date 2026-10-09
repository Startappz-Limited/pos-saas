@extends('layouts.app')

@section('title', 'Expense Summary')

@section('content')
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Expense Summary</h4>
                <a href="{{ route('expenses.index') }}" class="btn btn-soft-secondary">
                    <iconify-icon icon="solar:arrow-left-line-duotone" class="align-middle me-1"></iconify-icon>
                    Back to Expenses
                </a>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('expenses.summary') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:wallet-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($totals['total']) }}</h3>
                    <p class="text-muted">Total Expenses</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-36 text-success"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($totals['paid']) }}</h3>
                    <p class="text-muted">Paid</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($totals['unpaid']) }}</h3>
                    <p class="text-muted">Unpaid</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:hourglass-bold-duotone" class="fs-36 text-info"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $totals['pending_count'] }}</h3>
                    <p class="text-muted">Pending Approval</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- By Category -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expenses by Category</h5>
                </div>
                <div class="card-body">
                    @if ($byCategory->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byCategory as $item)
                                        <tr>
                                            <td>
                                                @if ($item->category)
                                                    <span
                                                        class="badge bg-light text-dark">{{ $item->category->name }}</span>
                                                @else
                                                    <span class="text-muted">Uncategorized</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-semibold">{{ format_currency($item->total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center mb-0">No data available</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- By Status -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expenses by Status</h5>
                </div>
                <div class="card-body">
                    @if ($byStatus->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-center">Count</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byStatus as $item)
                                        <tr>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $item->status->color() }}-subtle text-{{ $item->status->color() }}">
                                                    {{ $item->status->label() }}
                                                </span>
                                            </td>
                                            <td class="text-center">{{ $item->count }}</td>
                                            <td class="text-end fw-semibold">{{ format_currency($item->total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center mb-0">No data available</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trend -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Monthly Expense Trend ({{ now()->year }})</h5>
        </div>
        <div class="card-body">
            @if ($byMonth->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                @foreach ($byMonth as $item)
                                    <th class="text-center">{{ date('M', mktime(0, 0, 0, $item->month, 1)) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                @foreach ($byMonth as $item)
                                    <td class="text-center fw-semibold">{{ format_currency($item->total) }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted text-center mb-0">No data available for this year</p>
            @endif
        </div>
    </div>
@endsection
