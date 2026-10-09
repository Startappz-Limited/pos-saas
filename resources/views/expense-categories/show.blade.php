@extends('layouts.app')

@section('title', $expenseCategory->name)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('expense-categories.index') }}">Expense Categories</a>
                        </li>
                        @if ($expenseCategory->parent)
                            <li class="breadcrumb-item">
                                <a
                                    href="{{ route('expense-categories.show', $expenseCategory->parent) }}">{{ $expenseCategory->parent->name }}</a>
                            </li>
                        @endif
                        <li class="breadcrumb-item active">{{ $expenseCategory->name }}</li>
                    </ol>
                </nav>
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        @if ($expenseCategory->icon)
                            <iconify-icon icon="{{ $expenseCategory->icon }}" class="align-middle me-1"></iconify-icon>
                        @endif
                        {{ $expenseCategory->name }}
                    </h4>
                    <span class="badge bg-{{ $expenseCategory->status->color() }} fs-6 px-3 py-2">
                        {{ $expenseCategory->status->label() }}
                    </span>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-xl-8">
                <!-- Category Details -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Category Details</h5>
                        <span class="text-muted">{{ $expenseCategory->code }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Name</p>
                            </div>
                            <div class="col-sm-9">
                                <p class="mb-0 fw-semibold">{{ $expenseCategory->name }}</p>
                            </div>
                        </div>
                        @if ($expenseCategory->description)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Description</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ $expenseCategory->description }}</p>
                                </div>
                            </div>
                        @endif
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Type</p>
                            </div>
                            <div class="col-sm-9">
                                <span
                                    class="badge bg-{{ $expenseCategory->type->color() }}-subtle text-{{ $expenseCategory->type->color() }}">
                                    {{ $expenseCategory->type->label() }}
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Parent Category</p>
                            </div>
                            <div class="col-sm-9">
                                @if ($expenseCategory->parent)
                                    <a href="{{ route('expense-categories.show', $expenseCategory->parent) }}">
                                        {{ $expenseCategory->parent->name }}
                                    </a>
                                @else
                                    <span class="text-muted">Root Category</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Total Expenses</p>
                            </div>
                            <div class="col-sm-9">
                                <p class="mb-0 fw-semibold">{{ $expenseCategory->expenses_count }}</p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Flags</p>
                            </div>
                            <div class="col-sm-9">
                                @if ($expenseCategory->is_operational)
                                    <span class="badge bg-primary-subtle text-primary me-1">Operational</span>
                                @endif
                                @if ($expenseCategory->is_tax_deductible)
                                    <span class="badge bg-success-subtle text-success me-1">Tax Deductible</span>
                                @endif
                                @if ($expenseCategory->requires_approval)
                                    <span class="badge bg-warning-subtle text-warning me-1">Requires Approval</span>
                                @endif
                                @if (!$expenseCategory->is_operational && !$expenseCategory->is_tax_deductible && !$expenseCategory->requires_approval)
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </div>
                        @if ($expenseCategory->approval_threshold)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Approval Threshold</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ format_currency($expenseCategory->approval_threshold) }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Budget Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Budget Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted mb-2">Monthly Budget</h6>
                                    <h4 class="mb-1">
                                        {{ $expenseCategory->monthly_budget ? format_currency($expenseCategory->monthly_budget) : '—' }}
                                    </h4>
                                    @if ($monthlyUsage !== null)
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-{{ $monthlyUsage > 100 ? 'danger' : ($monthlyUsage > 80 ? 'warning' : 'success') }}"
                                                style="width: {{ min($monthlyUsage, 100) }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ number_format($monthlyUsage, 1) }}% used</small>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted mb-2">Yearly Budget</h6>
                                    <h4 class="mb-1">
                                        {{ $expenseCategory->yearly_budget ? format_currency($expenseCategory->yearly_budget) : '—' }}
                                    </h4>
                                    @if ($yearlyUsage !== null)
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-{{ $yearlyUsage > 100 ? 'danger' : ($yearlyUsage > 80 ? 'warning' : 'success') }}"
                                                style="width: {{ min($yearlyUsage, 100) }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ number_format($yearlyUsage, 1) }}% used</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Expenses -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Expenses</h5>
                    </div>
                    <div class="card-body">
                        @if ($recentExpenses->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Created By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentExpenses as $expense)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('expenses.show', $expense) }}">
                                                        {{ Str::limit($expense->title, 40) }}
                                                    </a>
                                                </td>
                                                <td>{{ format_currency($expense->amount) }}</td>
                                                <td>{{ $expense->expense_date->format('M d, Y') }}</td>
                                                <td>{{ $expense->creator?->name ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted text-center mb-0">No expenses recorded in this category yet.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <!-- Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @can('update', $expenseCategory)
                                <a href="{{ route('expense-categories.edit', $expenseCategory) }}"
                                    class="btn btn-soft-primary">
                                    <iconify-icon icon="solar:pen-linear" class="align-middle me-1"></iconify-icon>
                                    Edit Category
                                </a>
                            @endcan
                            @if ($expenseCategory->children->count() > 0)
                                <a href="{{ route('expense-categories.children', $expenseCategory) }}"
                                    class="btn btn-soft-info">
                                    <iconify-icon icon="solar:list-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    View Sub-Categories ({{ $expenseCategory->children->count() }})
                                </a>
                            @endif
                            <a href="{{ route('expense-categories.hierarchy', $expenseCategory) }}"
                                class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:diagram-up-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                View Hierarchy
                            </a>
                            @can('delete', $expenseCategory)
                                <form action="{{ route('expense-categories.destroy', $expenseCategory) }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger w-100">
                                        <iconify-icon icon="solar:trash-bin-trash-linear"
                                            class="align-middle me-1"></iconify-icon>
                                        Delete Category
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>

                <!-- Meta Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="text-muted mb-1">Created By</p>
                            <p class="mb-0">{{ $expenseCategory->creator?->name ?? '—' }}</p>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted mb-1">Created At</p>
                            <p class="mb-0">{{ $expenseCategory->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        @if ($expenseCategory->updater)
                            <div class="mb-3">
                                <p class="text-muted mb-1">Last Updated By</p>
                                <p class="mb-0">{{ $expenseCategory->updater->name }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-muted mb-1">Last Updated</p>
                            <p class="mb-0">{{ $expenseCategory->updated_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
