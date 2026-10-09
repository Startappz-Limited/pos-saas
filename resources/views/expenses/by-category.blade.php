@extends('layouts.app')

@section('title', 'Expenses by Category')

@section('content')
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Expenses by Category</h4>
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
            <form method="GET" action="{{ route('expenses.byCategory') }}">
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

    <!-- Categories Grid -->
    <div class="row">
        @forelse ($categories as $category)
            @if ($category->count > 0 || $category->monthly_budget)
                <div class="col-md-6 col-xl-4">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                @if ($category->icon)
                                    <iconify-icon icon="{{ $category->icon }}" class="me-1"></iconify-icon>
                                @endif
                                {{ $category->name }}
                            </h6>
                            <span class="badge bg-light text-dark">{{ $category->count }} expenses</span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Total Spent</span>
                                <span class="fs-5 fw-bold text-primary">{{ format_currency($category->total) }}</span>
                            </div>

                            @if ($category->monthly_budget)
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Budget Usage</small>
                                        <small class="text-muted">
                                            {{ format_currency($category->total) }} /
                                            {{ format_currency($category->monthly_budget) }}
                                        </small>
                                    </div>
                                    @php
                                        $usage = $category->monthly_usage ?? 0;
                                        $progressClass =
                                            $usage > 100 ? 'bg-danger' : ($usage > 80 ? 'bg-warning' : 'bg-success');
                                    @endphp
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar {{ $progressClass }}"
                                            style="width: {{ min($usage, 100) }}%"></div>
                                    </div>
                                    @if ($usage > 100)
                                        <small class="text-danger">Over budget by
                                            {{ number_format($usage - 100, 1) }}%</small>
                                    @endif
                                </div>
                            @endif

                            <div class="text-end mt-3">
                                <a href="{{ route('expenses.index', ['category_id' => $category->id]) }}"
                                    class="btn btn-sm btn-soft-primary">
                                    View Expenses
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <iconify-icon icon="solar:folder-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Categories Found</h5>
                        <p class="text-muted mb-0">Create expense categories to organize your expenses.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
