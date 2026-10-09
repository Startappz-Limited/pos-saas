@extends('layouts.app')

@section('title', 'Budget Report')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('expense-categories.index') }}">Expense Categories</a>
                        </li>
                        <li class="breadcrumb-item active">Budget Report</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Budget Report</h4>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:wallet-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($summary['total_budget']) }}</h3>
                        <p class="text-muted">Total Monthly Budget</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:dollar-bold-duotone" class="fs-36 text-info"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($summary['total_spent']) }}</h3>
                        <p class="text-muted">Total Spent (Period)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:danger-triangle-bold-duotone" class="fs-36 text-danger"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $summary['categories_over_budget'] }}</h3>
                        <p class="text-muted">Over Budget</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:shield-warning-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $summary['categories_near_budget'] }}</h3>
                        <p class="text-muted">Near Budget (80%+)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('expense-categories.budgetReport') }}">
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
                            <button type="submit" class="btn btn-primary">
                                <iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Filter
                            </button>
                            <a href="{{ route('expense-categories.budgetReport') }}" class="btn btn-soft-secondary">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories Budget Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Category Budget Breakdown</h5>
            </div>
            <div class="card-body">
                @if ($categories->count() > 0)
                    <div class="table-responsive table-card">
                        <table class="table table-nowrap table-striped-columns align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Monthly Budget</th>
                                    <th>Period Spent</th>
                                    <th>Monthly Usage</th>
                                    <th>Yearly Usage</th>
                                    <th>Expenses</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td>
                                            <a href="{{ route('expense-categories.show', $category) }}" class="fw-semibold">
                                                {{ $category->name }}
                                            </a>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-{{ $category->type->color() }}-subtle text-{{ $category->type->color() }}">
                                                {{ $category->type->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $category->monthly_budget ? format_currency($category->monthly_budget) : '—' }}
                                        </td>
                                        <td>{{ format_currency($category->period_expenses) }}</td>
                                        <td>
                                            @if ($category->monthly_usage_percent !== null)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-{{ $category->monthly_usage_percent > 100 ? 'danger' : ($category->monthly_usage_percent > 80 ? 'warning' : 'success') }}"
                                                            style="width: {{ min($category->monthly_usage_percent, 100) }}%">
                                                        </div>
                                                    </div>
                                                    <small>{{ number_format($category->monthly_usage_percent, 1) }}%</small>
                                                </div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($category->yearly_usage_percent !== null)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-{{ $category->yearly_usage_percent > 100 ? 'danger' : ($category->yearly_usage_percent > 80 ? 'warning' : 'success') }}"
                                                            style="width: {{ min($category->yearly_usage_percent, 100) }}%">
                                                        </div>
                                                    </div>
                                                    <small>{{ number_format($category->yearly_usage_percent, 1) }}%</small>
                                                </div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $category->expenses_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <iconify-icon icon="solar:chart-bold-duotone" class="text-muted"
                            style="font-size: 3rem; opacity: 0.5;"></iconify-icon>
                        <p class="text-muted mt-2 mb-0">No active categories found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
