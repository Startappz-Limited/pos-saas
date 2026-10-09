@extends('layouts.app')

@section('title', 'Register Details')

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <div class="row align-items-center">
                        <div class="col-sm">
                            <h5 class="card-title mb-0">Register {{ $cashRegister->register_number }}</h5>
                        </div>
                        <div class="col-sm-auto">
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                @if ($cashRegister->isOpen())
                                    <a href="{{ route('cash-registers.close-form', $cashRegister) }}"
                                        class="btn btn-warning">
                                        <iconify-icon icon="solar:close-circle-line-duotone"
                                            class="align-middle me-1"></iconify-icon> Close Register
                                    </a>
                                @endif
                                <a href="{{ route('cash-registers.index') }}" class="btn btn-soft-secondary">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Register Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Register Information</h6>
                            <table class="table table-borderless table-sm">
                                <tbody>
                                    <tr>
                                        <td class="fw-medium" style="width: 150px;">Register #:</td>
                                        <td>{{ $cashRegister->register_number }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Date:</td>
                                        <td>{{ $cashRegister->register_date->format('l, M d, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Status:</td>
                                        <td>
                                            @if ($cashRegister->isOpen())
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
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Opened By:</td>
                                        <td>{{ $cashRegister->user->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">Opened At:</td>
                                        <td>{{ $cashRegister->opened_at->format('h:i A') }}</td>
                                    </tr>
                                    @if ($cashRegister->isClosed())
                                        <tr>
                                            <td class="fw-medium">Closed By:</td>
                                            <td>{{ $cashRegister->closedBy->name ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Closed At:</td>
                                            <td>{{ $cashRegister->closed_at?->format('h:i A') ?? 'N/A' }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Sales Summary -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Sales Summary</h6>
                            <table class="table table-borderless table-sm">
                                <tbody>
                                    <tr>
                                        <td class="fw-medium" style="width: 150px;">Total Sales:</td>
                                        <td class="text-end fw-semibold">{{ format_currency($cashRegister->total_sales) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3">Cash:</td>
                                        <td class="text-end text-muted">
                                            {{ format_currency($cashRegister->total_cash_sales) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3">Card:</td>
                                        <td class="text-end text-muted">
                                            {{ format_currency($cashRegister->total_card_sales) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3">Credit:</td>
                                        <td class="text-end text-muted">
                                            {{ format_currency($cashRegister->total_credit_sales) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3">Mobile Money:</td>
                                        <td class="text-end text-muted">
                                            {{ format_currency($cashRegister->total_mobile_money_sales) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3">Bank Transfer:</td>
                                        <td class="text-end text-muted">
                                            {{ format_currency($cashRegister->total_bank_transfer_sales) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3">Cheque:</td>
                                        <td class="text-end text-muted">
                                            {{ format_currency($cashRegister->total_cheque_sales) }}</td>
                                    </tr>
                                    <tr class="border-top">
                                        <td class="fw-medium">Transactions:</td>
                                        <td class="text-end fw-semibold">{{ $cashRegister->transaction_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Cash Balance -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Cash Balance</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <td class="fw-medium bg-light">Opening Balance:</td>
                                            <td class="text-end">{{ format_currency($cashRegister->opening_balance) }}
                                            </td>
                                        </tr>
                                        @if ($cashRegister->expense_opening_balance > 0)
                                            <tr>
                                                <td class="fw-medium bg-light">Expense Opening Balance:</td>
                                                <td class="text-end text-muted">
                                                    {{ format_currency($cashRegister->expense_opening_balance) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium bg-light">Expense Balance Used:</td>
                                                <td class="text-end text-danger">
                                                    -{{ format_currency($cashRegister->expense_balance_used) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium bg-light">Expense Balance Remaining:</td>
                                                <td class="text-end text-success">
                                                    {{ format_currency($cashRegister->getRemainingExpenseBalance()) }}
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="fw-medium bg-light">Cash Sales:</td>
                                            <td class="text-end text-success">+
                                                {{ format_currency($cashRegister->total_cash_sales) }}</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td class="fw-semibold">Expected Balance:</td>
                                            <td class="text-end fw-semibold">
                                                {{ format_currency($cashRegister->calculateExpectedBalance()) }}</td>
                                        </tr>
                                        @if ($cashRegister->isClosed())
                                            <tr>
                                                <td class="fw-medium bg-light">Actual Closing Balance:</td>
                                                <td class="text-end">
                                                    {{ format_currency($cashRegister->closing_balance) }}</td>
                                            </tr>
                                            <tr
                                                class="{{ $cashRegister->variance < 0 ? 'table-danger' : ($cashRegister->variance > 0 ? 'table-success' : 'table-secondary') }}">
                                                <td class="fw-semibold">Variance:</td>
                                                <td class="text-end fw-semibold">
                                                    {{ format_currency($cashRegister->variance) }}
                                                    @if ($cashRegister->variance < 0)
                                                        (Short)
                                                    @elseif($cashRegister->variance > 0)
                                                        (Over)
                                                    @else
                                                        (Exact)
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if ($cashRegister->opening_notes || $cashRegister->closing_notes)
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3">Notes</h6>
                                @if ($cashRegister->opening_notes)
                                    <div class="mb-3">
                                        <strong>Opening Notes:</strong>
                                        <p class="text-muted mb-0">{{ $cashRegister->opening_notes }}</p>
                                    </div>
                                @endif
                                @if ($cashRegister->closing_notes)
                                    <div>
                                        <strong>Closing Notes:</strong>
                                        <p class="text-muted mb-0">{{ $cashRegister->closing_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Sales List -->
                    @if ($cashRegister->sales->count() > 0)
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3">Transactions
                                    ({{ $cashRegister->sales->count() }})</h6>
                                <div class="table-responsive">
                                    <table class="table table-nowrap table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Customer</th>
                                                <th>Time</th>
                                                <th>Payment</th>
                                                <th class="text-end">Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($cashRegister->sales as $sale)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('sales.show', $sale) }}"
                                                            class="fw-medium text-primary">
                                                            {{ $sale->invoice_number }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                                                    <td>{{ $sale->created_at->format('h:i A') }}</td>
                                                    <td>
                                                        <span
                                                            class="badge bg-{{ $sale->payment_method === 'cash' ? 'success' : ($sale->payment_method === 'card' ? 'info' : 'warning') }}-subtle text-{{ $sale->payment_method === 'cash' ? 'success' : ($sale->payment_method === 'card' ? 'info' : 'warning') }}">
                                                            {{ ucfirst($sale->payment_method) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end">{{ format_currency($sale->total_amount) }}</td>
                                                    <td>
                                                        @if ($sale->status === 'completed')
                                                            <span class="badge bg-success">Completed</span>
                                                        @elseif($sale->status === 'pending')
                                                            <span class="badge bg-warning">Pending</span>
                                                        @else
                                                            <span class="badge bg-danger">Voided</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info mt-4">
                            <iconify-icon icon="solar:info-circle-bold" class="align-middle me-2"></iconify-icon>
                            No transactions recorded for this register session.
                        </div>
                    @endif

                    <!-- Expenses List -->
                    @if ($cashRegister->expenses->count() > 0)
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3">
                                    <iconify-icon icon="solar:wallet-money-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Expenses Settled from Register ({{ $cashRegister->expenses->count() }})
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-nowrap table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Payment Method</th>
                                                <th>Date</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($cashRegister->expenses as $expense)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('expenses.show', $expense) }}"
                                                            class="fw-medium text-primary">
                                                            {{ Str::limit($expense->title, 40) }}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        @if ($expense->category)
                                                            <span
                                                                class="badge bg-light text-dark">{{ $expense->category->name }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            $methodColors = [
                                                                'cash' => 'success',
                                                                'card' => 'info',
                                                                'bank_transfer' => 'primary',
                                                                'mobile_money' => 'warning',
                                                                'cheque' => 'secondary',
                                                                'credit' => 'dark',
                                                            ];
                                                            $color =
                                                                $methodColors[$expense->payment_method] ?? 'secondary';
                                                        @endphp
                                                        <span
                                                            class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                            {{ ucwords(str_replace('_', ' ', $expense->payment_method ?? 'N/A')) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $expense->expense_date->format('M d, Y') }}</td>
                                                    <td class="text-end text-danger fw-semibold">
                                                        -{{ format_currency($expense->amount) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="4" class="fw-semibold text-end">Total Expenses:</td>
                                                <td class="text-end fw-bold text-danger">
                                                    -{{ format_currency($cashRegister->expenses->sum('amount')) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
