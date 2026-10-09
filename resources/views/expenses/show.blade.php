@extends('layouts.app')

@section('title', 'Expense Details')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
                        <li class="breadcrumb-item active">{{ $expense->expense_number }}</li>
                    </ol>
                </nav>
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">{{ $expense->title }}</h4>
                    <span class="badge bg-{{ $expense->status->color() }} fs-6 px-3 py-2">
                        {{ $expense->status->label() }}
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
                <!-- Expense Details -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Expense Details</h5>
                        <span class="text-muted">{{ $expense->expense_number }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Title</p>
                            </div>
                            <div class="col-sm-9">
                                <p class="mb-0 fw-semibold">{{ $expense->title }}</p>
                            </div>
                        </div>
                        @if ($expense->description)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Description</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ $expense->description }}</p>
                                </div>
                            </div>
                        @endif
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Category</p>
                            </div>
                            <div class="col-sm-9">
                                <p class="mb-0">
                                    @if ($expense->category)
                                        <span class="badge bg-light text-dark">{{ $expense->category->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Amount</p>
                            </div>
                            <div class="col-sm-9">
                                <p class="mb-0 fs-5 fw-bold text-primary">{{ format_currency($expense->amount) }}</p>
                            </div>
                        </div>
                        @if ($expense->tax_amount)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Tax Amount</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ format_currency($expense->tax_amount) }}
                                        @if ($expense->is_tax_deductible)
                                            <span class="badge bg-success-subtle text-success ms-1">Tax Deductible</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Expense Date</p>
                            </div>
                            <div class="col-sm-9">
                                <p class="mb-0">{{ $expense->expense_date->format('F d, Y') }}</p>
                            </div>
                        </div>
                        @if ($expense->due_date)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Due Date</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ $expense->due_date->format('F d, Y') }}</p>
                                </div>
                            </div>
                        @endif
                        @if ($expense->vendor)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Vendor</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ $expense->vendor->name }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <p class="text-muted mb-0">Payment Status</p>
                            </div>
                            <div class="col-sm-9">
                                @if ($expense->is_paid)
                                    <span class="badge bg-success">Paid</span>
                                @else
                                    <span class="badge bg-secondary">Unpaid</span>
                                @endif
                            </div>
                        </div>
                        @if ($expense->payment_method)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Payment Method</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ ucwords(str_replace('_', ' ', $expense->payment_method)) }}</p>
                                </div>
                            </div>
                        @endif
                        @if ($expense->reference_number)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Reference Number</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ $expense->reference_number }}</p>
                                </div>
                            </div>
                        @endif
                        @if ($expense->paid_date)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Paid Date</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ $expense->paid_date->format('F d, Y') }}</p>
                                </div>
                            </div>
                        @endif
                        @if ($expense->settled_from_register && $expense->cashRegister)
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Settled From</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">
                                        <a href="{{ route('cash-registers.show', $expense->cashRegister) }}"
                                            class="badge bg-info-subtle text-info">
                                            <iconify-icon icon="solar:wallet-money-bold-duotone"
                                                class="align-middle me-1"></iconify-icon>
                                            Register {{ $expense->cashRegister->register_number }}
                                        </a>
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Recurring Info -->
                @if ($expense->is_recurring)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <iconify-icon icon="solar:repeat-bold" class="align-middle me-1"></iconify-icon>
                                Recurring Expense
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <p class="text-muted mb-0">Frequency</p>
                                </div>
                                <div class="col-sm-9">
                                    <p class="mb-0">{{ ucfirst($expense->recurrence_frequency) }}</p>
                                </div>
                            </div>
                            @if ($expense->recurrence_end_date)
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <p class="text-muted mb-0">End Date</p>
                                    </div>
                                    <div class="col-sm-9">
                                        <p class="mb-0">{{ $expense->recurrence_end_date->format('F d, Y') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Notes -->
                @if ($expense->notes)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Notes</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $expense->notes }}</p>
                        </div>
                    </div>
                @endif

                <!-- Rejection Reason -->
                @if ($expense->status === \App\Enums\ExpenseStatus::REJECTED && $expense->rejection_reason)
                    <div class="card mb-4 border-danger">
                        <div class="card-header bg-danger-subtle">
                            <h5 class="card-title mb-0 text-danger">
                                <iconify-icon icon="solar:close-circle-bold" class="align-middle me-1"></iconify-icon>
                                Rejection Reason
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $expense->rejection_reason }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-xl-4">
                <!-- Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if ($expense->canEdit())
                                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-primary">
                                    <iconify-icon icon="solar:pen-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Edit Expense
                                </a>
                            @endif

                            @if ($expense->status === \App\Enums\ExpenseStatus::DRAFT)
                                <form action="{{ route('expenses.submit', $expense) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-warning w-100">
                                        <iconify-icon icon="solar:send-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Submit for Approval
                                    </button>
                                </form>
                            @endif

                            @can('approve', $expense)
                                @if ($expense->canApprove())
                                    <form action="{{ route('expenses.approve', $expense) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-success w-100">
                                            <iconify-icon icon="solar:check-circle-bold-duotone"
                                                class="align-middle me-1"></iconify-icon>
                                            Approve
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                        data-bs-target="#rejectModal">
                                        <iconify-icon icon="solar:close-circle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Reject
                                    </button>
                                @endif
                            @endcan

                            @if ($expense->canPay())
                                <button type="button" class="btn btn-info" data-bs-toggle="modal"
                                    data-bs-target="#markPaidModal">
                                    <iconify-icon icon="solar:dollar-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Mark as Paid
                                </button>

                                @if (isset($activeRegister) && $activeRegister && $activeRegister->getRemainingExpenseBalance() > 0)
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                                        data-bs-target="#settleFromRegisterModal">
                                        <iconify-icon icon="solar:wallet-money-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Settle from Register
                                    </button>
                                @endif
                            @endif

                            @if ($expense->canEdit())
                                <form action="{{ route('expenses.destroy', $expense) }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this expense?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger w-100">
                                        <iconify-icon icon="solar:trash-bin-trash-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Delete Expense
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('expenses.index') }}" class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:arrow-left-line-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Audit Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Audit Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="text-muted mb-1">Created By</p>
                            <p class="mb-0">
                                {{ $expense->creator?->name ?? 'System' }}
                                <small class="text-muted d-block">{{ $expense->created_at->format('M d, Y H:i') }}</small>
                            </p>
                        </div>
                        @if ($expense->submitted_at)
                            <div class="mb-3">
                                <p class="text-muted mb-1">Submitted At</p>
                                <p class="mb-0">{{ $expense->submitted_at->format('M d, Y H:i') }}</p>
                            </div>
                        @endif
                        @if ($expense->approver)
                            <div class="mb-3">
                                <p class="text-muted mb-1">
                                    {{ $expense->status === \App\Enums\ExpenseStatus::REJECTED ? 'Rejected' : 'Approved' }}
                                    By</p>
                                <p class="mb-0">
                                    {{ $expense->approver?->name ?? __('Deleted user') }}
                                    <small
                                        class="text-muted d-block">{{ $expense->approved_at?->format('M d, Y H:i') }}</small>
                                </p>
                            </div>
                        @endif
                        @if ($expense->updated_at->ne($expense->created_at))
                            <div class="mb-0">
                                <p class="text-muted mb-1">Last Updated</p>
                                <p class="mb-0">{{ $expense->updated_at->format('M d, Y H:i') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('expenses.reject', $expense) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required
                                placeholder="Please provide a reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark Paid Modal -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('expenses.markPaid', $expense) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Mark as Paid</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference Number</label>
                            <input type="text" name="reference_number" class="form-control"
                                placeholder="Transaction reference">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark as Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settle from Register Modal -->
    @if (isset($activeRegister) &&
            $activeRegister &&
            $activeRegister->getRemainingExpenseBalance() > 0 &&
            $expense->canPay())
        <div class="modal fade" id="settleFromRegisterModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('expenses.settleFromRegister', $expense) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Settle from Register Balance</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <iconify-icon icon="solar:info-circle-bold" class="align-middle me-1"></iconify-icon>
                                This will deduct <strong>{{ format_currency($expense->amount) }}</strong> from the
                                register's expense balance and mark the expense as paid.
                            </div>
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <small class="text-muted d-block">Opening Balance</small>
                                    <strong>{{ format_currency($activeRegister->expense_opening_balance) }}</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Used</small>
                                    <strong
                                        class="text-danger">{{ format_currency($activeRegister->expense_balance_used) }}</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Available</small>
                                    <strong
                                        class="text-success">{{ format_currency($activeRegister->getRemainingExpenseBalance()) }}</strong>
                                </div>
                            </div>
                            @if ($expense->amount > $activeRegister->getRemainingExpenseBalance())
                                <div class="alert alert-danger mb-0">
                                    <iconify-icon icon="solar:danger-triangle-bold"
                                        class="align-middle me-1"></iconify-icon>
                                    Expense amount exceeds available balance.
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning"
                                {{ $expense->amount > $activeRegister->getRemainingExpenseBalance() ? 'disabled' : '' }}>
                                <iconify-icon icon="solar:wallet-money-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Settle from Register
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
