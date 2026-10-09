@extends('layouts.app')

@section('title', 'Close Cash Register')

@section('content')

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:close-circle-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Close Cash Register: {{ $cashRegister->register_number }}
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Register Summary -->
                    <div class="alert alert-primary">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Opened:</strong> {{ $cashRegister->opened_at->format('M d, Y h:i A') }}<br>
                                <strong>By:</strong> {{ $cashRegister->user->name }}
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>Total Sales:</strong> {{ format_currency($cashRegister->total_sales) }}<br>
                                <strong>Transactions:</strong> {{ $cashRegister->transaction_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Sales Breakdown -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Sales Breakdown</h6>
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
                                        <tr>
                                            <td class="fw-medium bg-light">Card Sales:</td>
                                            <td class="text-end text-info">
                                                {{ format_currency($cashRegister->total_card_sales) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium bg-light">Credit Sales (On Account):</td>
                                            <td class="text-end text-warning">
                                                {{ format_currency($cashRegister->total_credit_sales) }}</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td class="fw-semibold fs-16">Expected Cash in Drawer:</td>
                                            <td class="text-end fw-semibold fs-16">
                                                {{ format_currency($cashRegister->calculateExpectedBalance()) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Closing Form -->
                    <form action="{{ route('cash-registers.close', $cashRegister) }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label for="closing_balance" class="form-label">
                                Actual Cash Count <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                <input type="number"
                                    class="form-control form-control-lg @error('closing_balance') is-invalid @enderror"
                                    id="closing_balance" name="closing_balance" value="{{ old('closing_balance') }}"
                                    step="0.01" min="0" required autofocus>
                            </div>
                            @error('closing_balance')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Count all cash in the drawer and enter the total amount.</div>
                        </div>

                        <!-- Variance Indicator (Dynamic) -->
                        <div class="mb-4">
                            <div class="card bg-light" id="variance-card" style="display: none;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Expected:</strong> $<span
                                                id="expected-balance">{{ number_format($cashRegister->calculateExpectedBalance(), 2) }}</span><br>
                                            <strong>Actual:</strong> $<span id="actual-balance">0.00</span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-12 text-muted">Variance</div>
                                            <h3 class="mb-0" id="variance-amount">{{ currency_symbol() }}0.00</h3>
                                            <small id="variance-label" class="text-muted"></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="closing_notes" class="form-label">Closing Notes</label>
                            <textarea class="form-control @error('closing_notes') is-invalid @enderror" id="closing_notes" name="closing_notes"
                                rows="4" placeholder="Explain any cash variances, unusual circumstances, or other relevant information...">{{ old('closing_notes') }}</textarea>
                            @error('closing_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="border-top pt-3 mt-4">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="{{ route('cash-registers.show', $cashRegister) }}" class="btn btn-soft-secondary">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-warning">
                                    <iconify-icon icon="solar:lock-keyhole-bold-duotone"
                                        class="align-middle me-1"></iconify-icon> Close Register
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cash Counting Tips -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <iconify-icon icon="solar:lightbulb-bolt-bold-duotone"
                            class="text-warning align-middle me-2"></iconify-icon>
                        Cash Counting Tips
                    </h6>
                    <ul class="mb-0">
                        <li class="mb-2">Count bills in stacks of 10 or 20 for accuracy</li>
                        <li class="mb-2">Separate and count coins by denomination</li>
                        <li class="mb-2">Count twice to verify your total</li>
                        <li class="mb-2">Check for counterfeit bills, especially large denominations</li>
                        <li class="mb-2">If variance is large, do a third count</li>
                        <li>Document any discrepancies in closing notes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        const expectedBalance = {{ $cashRegister->calculateExpectedBalance() }};
        const closingBalanceInput = document.getElementById('closing_balance');
        const varianceCard = document.getElementById('variance-card');
        const actualBalanceSpan = document.getElementById('actual-balance');
        const varianceAmountSpan = document.getElementById('variance-amount');
        const varianceLabelSpan = document.getElementById('variance-label');

        closingBalanceInput.addEventListener('input', function() {
            const actualBalance = parseFloat(this.value) || 0;
            const variance = actualBalance - expectedBalance;

            if (this.value) {
                varianceCard.style.display = 'block';
                actualBalanceSpan.textContent = actualBalance.toFixed(2);
                varianceAmountSpan.textContent = '{{ currency_symbol() }}' + Math.abs(variance).toFixed(2);

                // Update color and label based on variance
                varianceCard.classList.remove('bg-light', 'bg-success-subtle', 'bg-danger-subtle',
                    'bg-warning-subtle');
                varianceAmountSpan.classList.remove('text-success', 'text-danger', 'text-muted', 'text-warning');

                if (variance === 0) {
                    varianceCard.classList.add('bg-success-subtle');
                    varianceAmountSpan.classList.add('text-success');
                    varianceLabelSpan.textContent = 'Exact Match';
                } else if (variance < 0) {
                    varianceCard.classList.add('bg-danger-subtle');
                    varianceAmountSpan.classList.add('text-danger');
                    varianceLabelSpan.textContent = 'Short';
                } else {
                    varianceCard.classList.add('bg-warning-subtle');
                    varianceAmountSpan.classList.add('text-warning');
                    varianceLabelSpan.textContent = 'Over';
                }
            } else {
                varianceCard.style.display = 'none';
            }
        });
    </script>
@endpush
