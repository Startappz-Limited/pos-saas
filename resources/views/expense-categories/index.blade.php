@extends('layouts.app')

@section('title', 'Expense Categories')

@section('content')

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Expense Categories</h4>
                <div class="d-flex gap-2">
                    <a href="{{ route('expense-categories.budgetReport') }}" class="btn btn-soft-info">
                        <iconify-icon icon="solar:chart-bold-duotone" class="align-middle me-1"></iconify-icon>
                        Budget Report
                    </a>
                    @can('create', App\Models\ExpenseCategory::class)
                        <a href="{{ route('expense-categories.create') }}" class="btn btn-primary">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            New Category
                        </a>
                    @endcan
                </div>
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

    <!-- Categories List Card -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <h5 class="card-title mb-0">All Categories</h5>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Search and Filters -->
            <form method="GET" action="{{ route('expense-categories.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-3 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="Search categories..." value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="type">
                            <option value="">All Types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" {{ request('type') == $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}"
                                    {{ request('status') == $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="root_only" value="1" class="form-check-input" id="root_only"
                                {{ request('root_only') ? 'checked' : '' }}>
                            <label class="form-check-label" for="root_only">Root Only</label>
                        </div>
                    </div>
                    <div class="col-xxl-1 col-sm-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                        </button>
                    </div>
                    <div class="col-xxl-1 col-sm-3">
                        <a href="{{ route('expense-categories.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle"></iconify-icon>
                        </a>
                    </div>
                </div>
            </form>

            @if ($categories->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Code</th>
                                <th scope="col">Name</th>
                                <th scope="col">Type</th>
                                <th scope="col">Parent</th>
                                <th scope="col">Expenses</th>
                                <th scope="col">Monthly Budget</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <a href="{{ route('expense-categories.show', $category) }}"
                                            class="fw-semibold text-primary">
                                            {{ $category->code }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($category->icon)
                                            <iconify-icon icon="{{ $category->icon }}"
                                                class="align-middle me-1"></iconify-icon>
                                        @endif
                                        {{ $category->name }}
                                        @if ($category->children->count() > 0)
                                            <span class="badge bg-light text-dark ms-1">{{ $category->children->count() }}
                                                sub</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-{{ $category->type->color() }}-subtle text-{{ $category->type->color() }}">
                                            {{ $category->type->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($category->parent)
                                            <a href="{{ route('expense-categories.show', $category->parent) }}">
                                                {{ $category->parent->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $category->expenses_count }}</td>
                                    <td>
                                        @if ($category->monthly_budget)
                                            {{ format_currency($category->monthly_budget) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-{{ $category->status->color() }}-subtle text-{{ $category->status->color() }}">
                                            {{ $category->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a href="{{ route('expense-categories.show', $category) }}"
                                                class="btn btn-sm btn-soft-info">
                                                <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                            </a>
                                            @can('update', $category)
                                                <a href="{{ route('expense-categories.edit', $category) }}"
                                                    class="btn btn-sm btn-soft-primary">
                                                    <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $categories->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:folder-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Categories Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('search') || request('type') || request('status') ? 'Try adjusting your search or filters.' : 'Start by creating your first expense category.' }}
                        </p>
                        @can('create', App\Models\ExpenseCategory::class)
                            <a href="{{ route('expense-categories.create') }}" class="btn btn-primary mt-3">
                                <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                                New Category
                            </a>
                        @endcan
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
