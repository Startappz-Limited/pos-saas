@extends('layouts.app')

@section('title', 'Cash Registers')

@section('content')

    <!-- Active Register Alert -->
    @if ($activeRegister)
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-18 align-middle me-2"></iconify-icon>
            <strong>Register Open:</strong> {{ $activeRegister->register_number }} opened by
            {{ $activeRegister->user?->name ?? __('Deleted user') }} at {{ $activeRegister->opened_at->format('h:i A') }}
            <div class="mt-2">
                <a href="{{ route('cash-registers.show', $activeRegister) }}" class="btn btn-sm btn-success me-2">
                    <iconify-icon icon="solar:eye-linear" class="align-middle"></iconify-icon> View Details
                </a>
                <a href="{{ route('cash-registers.close-form', $activeRegister) }}" class="btn btn-sm btn-warning">
                    <iconify-icon icon="solar:close-circle-linear" class="align-middle"></iconify-icon> Close Register
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @else
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-18 align-middle me-2"></iconify-icon>
            <strong>No Active Register:</strong> Register will auto-open when first sale is created today.
            <a href="{{ route('cash-registers.open') }}" class="btn btn-sm btn-primary ms-2">
                <iconify-icon icon="solar:add-circle-linear" class="align-middle"></iconify-icon> Open Register Manually
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:dollar-bold-duotone" class="fs-36 text-success"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['today_sales']) }}</h3>
                    <p class="text-muted">Today's Sales</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:cart-check-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['today_transactions'] }}</h3>
                    <p class="text-muted">Transactions</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:wallet-money-bold-duotone" class="fs-36 text-info"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['cash_sales']) }}</h3>
                    <p class="text-muted">Cash Sales</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body overflow-hidden position-relative">
                    <iconify-icon icon="solar:card-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                    <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['card_sales']) }}</h3>
                    <p class="text-muted">Card Sales</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Balance Info -->
    @if ($activeRegister && $statistics['expense_opening_balance'] > 0)
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:wallet-bold-duotone" class="fs-36 text-secondary"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['expense_opening_balance']) }}
                        </h3>
                        <p class="text-muted">Expense Opening Balance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:minus-circle-bold-duotone" class="fs-36 text-danger"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['expense_balance_used']) }}</h3>
                        <p class="text-muted">Expense Balance Used</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-36 text-success"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['remaining_expense_balance']) }}
                        </h3>
                        <p class="text-muted">Expense Balance Remaining</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Register List -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">Register History</h5>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <a href="{{ route('cash-registers.open') }}" class="btn btn-primary add-btn">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            Open Register
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Filters -->
            <form method="GET" action="{{ route('cash-registers.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-3 col-sm-6">
                        <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                    </div>
                    <div class="col-xxl-2 col-sm-6">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                            <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Filter
                        </button>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <a href="{{ route('cash-registers.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            @if ($registers->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Register #</th>
                                <th>Date</th>
                                <th>Opened By</th>
                                <th>Opening</th>
                                <th>Closing</th>
                                <th>Sales</th>
                                <th>Transactions</th>
                                <th>Variance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registers as $register)
                                <tr>
                                    <td>
                                        <a href="{{ route('cash-registers.show', $register) }}"
                                            class="fw-semibold text-primary">
                                            {{ $register->register_number }}
                                        </a>
                                    </td>
                                    <td>{{ $register->register_date->format('M d, Y') }}</td>
                                    <td>{{ $register->user?->name ?? __('Deleted user') }}</td>
                                    <td>{{ format_currency($register->opening_balance) }}</td>
                                    <td>
                                        @if ($register->closing_balance !== null)
                                            {{ format_currency($register->closing_balance) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ format_currency($register->total_sales) }}</td>
                                    <td>{{ $register->transaction_count }}</td>
                                    <td>
                                        @if ($register->variance !== null)
                                            <span
                                                class="{{ $register->variance < 0 ? 'text-danger' : ($register->variance > 0 ? 'text-success' : 'text-muted') }}">
                                                {{ format_currency($register->variance) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($register->status === 'open')
                                            <span class="badge bg-success-subtle text-success">
                                                <iconify-icon icon="solar:check-circle-bold"
                                                    class="align-middle"></iconify-icon> Open
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">
                                                <iconify-icon icon="solar:lock-keyhole-bold"
                                                    class="align-middle"></iconify-icon> Closed
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a href="{{ route('cash-registers.show', $register) }}"
                                                class="btn btn-sm btn-soft-info">
                                                <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                            </a>
                                            @if ($register->status === 'open')
                                                <a href="{{ route('cash-registers.close-form', $register) }}"
                                                    class="btn btn-sm btn-soft-warning">
                                                    <iconify-icon icon="solar:close-circle-linear"></iconify-icon>
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
                    {{ $registers->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Registers Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('date') || request('status') ? 'Try adjusting your filters.' : 'Start by opening your first register.' }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
