@extends('layouts.app')

@section('title', 'Expenses')

@section('content')

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:wallet-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['total_amount'] ?? 0) }}</h3>
                    <p class="text-muted">Total Expenses</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['pending'] ?? 0 }}</h3>
                    <p class="text-muted">Pending Approval</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-36 text-success"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['approved'] ?? 0 }}</h3>
                    <p class="text-muted">Approved</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:dollar-bold-duotone" class="fs-36 text-info"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['paid_amount'] ?? 0) }}</h3>
                    <p class="text-muted">Paid</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses List Card -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">All Expenses</h5>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <a href="{{ route('expenses.pending') }}" class="btn btn-soft-warning">
                            <iconify-icon icon="solar:clock-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            Pending
                        </a>
                        <a href="{{ route('expenses.create') }}" class="btn btn-primary add-btn">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            New Expense
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Search and Filters -->
            <form method="GET" action="{{ route('expenses.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-3 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="Search expenses..." value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            @foreach (\App\Enums\ExpenseStatus::cases() as $status)
                                <option value="{{ $status->value }}"
                                    {{ request('status') == $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="category_id">
                            <option value="">All Categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="is_paid">
                            <option value="">All Payment</option>
                            <option value="1" {{ request('is_paid') === '1' ? 'selected' : '' }}>Paid</option>
                            <option value="0" {{ request('is_paid') === '0' ? 'selected' : '' }}>Unpaid</option>
                        </select>
                    </div>
                    <div class="col-xxl-1 col-sm-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                        </button>
                    </div>
                    <div class="col-xxl-1 col-sm-3">
                        <a href="{{ route('expenses.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle"></iconify-icon>
                        </a>
                    </div>
                </div>
            </form>

            @if ($expenses->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Expense #</th>
                                <th scope="col">Title</th>
                                <th scope="col">Category</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Date</th>
                                <th scope="col">Status</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expenses as $expense)
                                <tr>
                                    <td>
                                        <a href="{{ route('expenses.show', $expense) }}" class="fw-semibold text-primary">
                                            {{ $expense->expense_number }}
                                        </a>
                                    </td>
                                    <td>
                                        {{ Str::limit($expense->title, 30) }}
                                        @if ($expense->is_recurring)
                                            <span class="badge bg-info-subtle text-info ms-1">
                                                <iconify-icon icon="solar:repeat-bold" class="align-middle"></iconify-icon>
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($expense->category)
                                            <span class="badge bg-light text-dark">{{ $expense->category->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ format_currency($expense->amount) }}</td>
                                    <td>{{ $expense->expense_date->format('M d, Y') }}</td>
                                    <td>
                                        <span
                                            class="badge bg-{{ $expense->status->color() }}-subtle text-{{ $expense->status->color() }}">
                                            {{ $expense->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($expense->is_paid)
                                            <span class="badge bg-success">Paid</span>
                                        @else
                                            <span class="badge bg-secondary">Unpaid</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a href="{{ route('expenses.show', $expense) }}"
                                                class="btn btn-sm btn-soft-info">
                                                <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                            </a>
                                            @if ($expense->canEdit())
                                                <a href="{{ route('expenses.edit', $expense) }}"
                                                    class="btn btn-sm btn-soft-primary">
                                                    <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $expenses->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:wallet-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Expenses Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('search') || request('status') ? 'Try adjusting your search or filters.' : 'Start by creating your first expense.' }}
                        </p>
                        <a href="{{ route('expenses.create') }}" class="btn btn-primary mt-3">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            New Expense
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
