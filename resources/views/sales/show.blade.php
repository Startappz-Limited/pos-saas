@extends('layouts.app')

@section('title', 'Sale Details')

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <div class="row align-items-center">
                        <div class="col-sm">
                            <h5 class="card-title mb-0">
                                Sale #{{ $sale->invoice_number }}
                                @if ($sale->is_cod)
                                    <span class="badge bg-warning-subtle text-warning ms-2">COD</span>
                                @endif
                            </h5>
                        </div>
                        <div class="col-sm-auto">
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn btn-soft-info">
                                    <iconify-icon icon="solar:printer-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Print Receipt
                                </a>
                                @if ($sale->payment_status !== 'paid')
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                        data-bs-target="#collectPaymentModal">
                                        <iconify-icon icon="solar:money-bag-bold-duotone"
                                            class="align-middle me-1"></iconify-icon> Collect Payment
                                    </button>
                                @endif
                                @if ($sale->status === 'pending')
                                    <a href="{{ route('sales.edit', $sale) }}" class="btn btn-soft-primary">
                                        <iconify-icon icon="solar:pen-linear" class="align-middle me-1"></iconify-icon> Edit
                                    </a>
                                @endif
                                <a href="{{ route('sales.index') }}" class="btn btn-soft-secondary">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Sale Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Sale Information</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-medium">Invoice #:</td>
                                            <td>{{ $sale->invoice_number }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Date:</td>
                                            <td>{{ $sale->created_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Status:</td>
                                            <td>
                                                @if ($sale->status === 'completed')
                                                    <span class="badge bg-success-subtle text-success">
                                                        <iconify-icon icon="solar:check-circle-bold"
                                                            class="align-middle"></iconify-icon> Completed
                                                    </span>
                                                @elseif($sale->status === 'pending')
                                                    <span class="badge bg-warning-subtle text-warning">
                                                        <iconify-icon icon="solar:clock-circle-bold"
                                                            class="align-middle"></iconify-icon> Pending
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">
                                                        <iconify-icon icon="solar:close-circle-bold"
                                                            class="align-middle"></iconify-icon> Voided
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Payment Status:</td>
                                            <td>
                                                @if ($sale->payment_status === 'paid')
                                                    <span class="badge bg-success">Paid</span>
                                                @elseif($sale->payment_status === 'partial')
                                                    <span class="badge bg-warning">Partial</span>
                                                @else
                                                    <span class="badge bg-danger">Unpaid</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Served By:</td>
                                            <td>{{ $sale->createdBy->name ?? 'N/A' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Customer Information</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        @if ($sale->customer)
                                            <tr>
                                                <td class="fw-medium">Name:</td>
                                                <td>{{ $sale->customer->name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Phone:</td>
                                                <td>{{ $sale->customer->phone }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Type:</td>
                                                <td>{{ ucfirst($sale->customer->customer_type) }}</td>
                                            </tr>
                                            @if ($sale->customer->email)
                                                <tr>
                                                    <td class="fw-medium">Email:</td>
                                                    <td>{{ $sale->customer->email }}</td>
                                                </tr>
                                            @endif
                                        @elseif($sale->walk_in_customer_name)
                                            <tr>
                                                <td class="fw-medium">Name:</td>
                                                <td>{{ $sale->walk_in_customer_name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Phone:</td>
                                                <td>{{ $sale->walk_in_customer_phone }}</td>
                                            </tr>
                                            @if ($sale->walk_in_customer_email)
                                                <tr>
                                                    <td class="fw-medium">Email:</td>
                                                    <td>{{ $sale->walk_in_customer_email }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td class="fw-medium">Type:</td>
                                                <td><span class="badge bg-secondary-subtle text-secondary">Walk-in</span>
                                                </td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="2" class="text-muted">Walk-in Customer</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Source & Delivery Details -->
                    <div class="row mt-4">
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Source & Delivery</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-medium">Sale Source:</td>
                                            <td>
                                                @if ($sale->source)
                                                    <span class="badge"
                                                        style="background-color: {{ $sale->source->color }}; color: #fff;">
                                                        @if ($sale->source->icon)
                                                            <iconify-icon icon="{{ $sale->source->icon }}"
                                                                class="align-middle me-1"></iconify-icon>
                                                        @endif
                                                        {{ $sale->source->name }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Delivery Location:</td>
                                            <td>{{ $sale->delivery_location ?? 'Not specified' }}</td>
                                        </tr>
                                        @if ($sale->deliveryCompany)
                                            <tr>
                                                <td class="fw-medium">Delivery Company:</td>
                                                <td>
                                                    {{ $sale->deliveryCompany->name }}<br>
                                                    <small class="text-muted">{{ $sale->deliveryCompany->phone }}</small>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sale Items -->
                    <div class="mt-4">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3">Items</h6>
                        <div class="table-responsive">
                            <table class="table table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Product</th>
                                        <th scope="col">Variation</th>
                                        <th scope="col" class="text-end">Unit Price</th>
                                        <th scope="col" class="text-end">Quantity</th>
                                        <th scope="col" class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sale->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-medium">{{ $item->product->name }}</div>
                                                <small class="text-muted">SKU: {{ $item->product->sku }}</small>
                                            </td>
                                            <td>
                                                @if ($item->variation)
                                                    <span
                                                        class="badge bg-info-subtle text-info">{{ $item->variation->name }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ format_currency($item->unit_price) }}</td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ format_currency($item->line_total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="mt-4">
                        <div class="row justify-content-end">
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted">Subtotal:</td>
                                                <td class="text-end">{{ format_currency($sale->subtotal) }}</td>
                                            </tr>
                                            @if ($sale->discount_amount > 0)
                                                <tr>
                                                    <td class="text-muted">Discount:</td>
                                                    <td class="text-end text-danger">-
                                                        {{ format_currency($sale->discount_amount) }}</td>
                                                </tr>
                                            @endif
                                            @if ($sale->tax_amount > 0)
                                                <tr>
                                                    <td class="text-muted">Tax:</td>
                                                    <td class="text-end">{{ format_currency($sale->tax_amount) }}</td>
                                                </tr>
                                            @endif
                                            @if ($sale->delivery_fee > 0 || $sale->packaging_fee > 0 || $sale->other_expenses > 0)
                                                <tr class="border-top">
                                                    <td colspan="2" class="text-muted fw-semibold"><small>Additional
                                                            Expenses</small></td>
                                                </tr>
                                                @if ($sale->delivery_fee > 0)
                                                    <tr>
                                                        <td class="text-muted ps-3"><small>Delivery Fee:</small></td>
                                                        <td class="text-end">
                                                            <small>{{ format_currency($sale->delivery_fee) }}</small>
                                                        </td>
                                                    </tr>
                                                @endif
                                                @if ($sale->packaging_fee > 0)
                                                    <tr>
                                                        <td class="text-muted ps-3"><small>Packaging Fee:</small></td>
                                                        <td class="text-end">
                                                            <small>{{ format_currency($sale->packaging_fee) }}</small>
                                                        </td>
                                                    </tr>
                                                @endif
                                                @if ($sale->other_expenses > 0)
                                                    <tr>
                                                        <td class="text-muted ps-3"><small>Other Expenses:</small></td>
                                                        <td class="text-end">
                                                            <small>{{ format_currency($sale->other_expenses) }}</small>
                                                        </td>
                                                    </tr>
                                                @endif
                                                @if ($sale->expense_notes)
                                                    <tr>
                                                        <td colspan="2" class="text-muted ps-3">
                                                            <small><em>{{ $sale->expense_notes }}</em></small>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endif
                                            <tr class="border-top">
                                                <th class="fs-16">Total Payable:</th>
                                                <th class="text-end fs-16 text-primary">
                                                    {{ format_currency($sale->total_amount) }}</th>
                                            </tr>
                                            @if ($sale->paid_amount > 0)
                                                <tr>
                                                    <td class="text-muted">Paid:</td>
                                                    <td class="text-end text-success">
                                                        {{ format_currency($sale->paid_amount) }}</td>
                                                </tr>
                                            @endif
                                            @if ($sale->balance_due > 0)
                                                <tr>
                                                    <td class="fw-medium">Balance Due:</td>
                                                    <td class="text-end fw-medium text-danger">
                                                        {{ format_currency($sale->balance_due) }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($sale->notes)
                        <div class="mt-4">
                            <h6 class="text-muted text-uppercase fw-semibold mb-2">Notes</h6>
                            <p class="text-muted mb-0">{{ $sale->notes }}</p>
                        </div>
                    @endif

                    <!-- Payment History -->
                    @if ($sale->payments->count() > 0)
                        <div class="mt-4">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Payment History</h6>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Payment #</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Method</th>
                                            <th scope="col">Reference</th>
                                            <th scope="col" class="text-end">Amount</th>
                                            <th scope="col">Received By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sale->payments as $payment)
                                            <tr>
                                                <td>
                                                    <span class="fw-medium">{{ $payment->payment_number }}</span>
                                                </td>
                                                <td>{{ $payment->paid_at->format('M d, Y h:i A') }}</td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info">
                                                        {{ $payment->payment_method_label }}
                                                    </span>
                                                </td>
                                                <td>{{ $payment->reference ?? '—' }}</td>
                                                <td class="text-end text-success fw-medium">
                                                    {{ format_currency($payment->amount) }}
                                                </td>
                                                <td>{{ $payment->receiver?->name ?? 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Collect Payment Modal -->
    <div class="modal fade" id="collectPaymentModal" tabindex="-1" aria-labelledby="collectPaymentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="collectPaymentModalLabel">Collect Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Amount: <strong>{{ format_currency($sale->total_amount) }}</strong></span>
                            <span>Balance Due: <strong
                                    class="text-danger">{{ format_currency($sale->balance_due) }}</strong></span>
                        </div>
                    </div>

                    <form id="collectPaymentForm">
                        <div id="paymentRows">
                            <div class="payment-row mb-3 p-3 border rounded">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control payment-amount"
                                            name="payments[0][amount]" step="0.01" min="0.01"
                                            max="{{ $sale->balance_due }}" value="{{ $sale->balance_due }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Method <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select" name="payments[0][payment_method]" required>
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="mobile_money">Mobile Money</option>
                                            <option value="cheque">Cheque</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Reference</label>
                                        <input type="text" class="form-control" name="payments[0][reference]"
                                            placeholder="Transaction ID">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Notes</label>
                                        <input type="text" class="form-control" name="payments[0][notes]"
                                            placeholder="Optional notes">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-soft-primary btn-sm" id="addPaymentRow">
                            <iconify-icon icon="solar:add-circle-linear" class="align-middle me-1"></iconify-icon>
                            Add Another Payment Method
                        </button>

                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-medium">Total Payment: <span id="totalPaymentDisplay"
                                        class="text-success">{{ currency_symbol() }}0.00</span></span>
                                <span class="text-muted">Remaining: <span id="remainingDisplay"
                                        class="text-danger">{{ format_currency($sale->balance_due) }}</span></span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="submitPaymentBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                        Collect Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const balanceDue = {{ $sale->balance_due }};
            let paymentRowIndex = 1;

            // Add payment row
            document.getElementById('addPaymentRow').addEventListener('click', function() {
                const paymentRows = document.getElementById('paymentRows');
                const newRow = document.createElement('div');
                newRow.className = 'payment-row mb-3 p-3 border rounded';
                newRow.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" class="form-control payment-amount" name="payments[${paymentRowIndex}][amount]"
                                step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="payments[${paymentRowIndex}][payment_method]" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" name="payments[${paymentRowIndex}][reference]"
                                placeholder="Transaction ID">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" name="payments[${paymentRowIndex}][notes]"
                                placeholder="Optional notes">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-soft-danger btn-sm remove-payment-row">
                                <iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon>
                            </button>
                        </div>
                    </div>
                `;
                paymentRows.appendChild(newRow);
                paymentRowIndex++;
                updatePaymentTotals();
            });

            // Remove payment row
            document.getElementById('paymentRows').addEventListener('click', function(e) {
                if (e.target.closest('.remove-payment-row')) {
                    e.target.closest('.payment-row').remove();
                    updatePaymentTotals();
                }
            });

            // Update totals when amount changes
            document.getElementById('paymentRows').addEventListener('input', function(e) {
                if (e.target.classList.contains('payment-amount')) {
                    updatePaymentTotals();
                }
            });

            function updatePaymentTotals() {
                const amounts = document.querySelectorAll('.payment-amount');
                let total = 0;
                amounts.forEach(input => {
                    total += parseFloat(input.value) || 0;
                });
                document.getElementById('totalPaymentDisplay').textContent = '{{ currency_symbol() }}' + total
                    .toFixed(2);
                const remaining = balanceDue - total;
                document.getElementById('remainingDisplay').textContent = '{{ currency_symbol() }}' + remaining
                    .toFixed(2);
                document.getElementById('remainingDisplay').className = remaining > 0 ? 'text-danger' :
                    'text-success';
            }

            // Initial calculation
            updatePaymentTotals();

            // Submit payment
            document.getElementById('submitPaymentBtn').addEventListener('click', function() {
                const form = document.getElementById('collectPaymentForm');
                const formData = new FormData(form);
                const btn = this;
                const spinner = btn.querySelector('.spinner-border');

                // Convert FormData to proper structure
                const payments = [];
                const paymentRows = document.querySelectorAll('.payment-row');
                paymentRows.forEach((row, index) => {
                    const amount = row.querySelector('.payment-amount').value;
                    const method = row.querySelector('select').value;
                    const reference = row.querySelector('input[name*="reference"]').value;
                    const notes = row.querySelector('input[name*="notes"]').value;

                    if (amount && parseFloat(amount) > 0) {
                        payments.push({
                            amount: parseFloat(amount),
                            payment_method: method,
                            reference: reference || null,
                            notes: notes || null
                        });
                    }
                });

                if (payments.length === 0) {
                    alert('Please enter at least one payment amount.');
                    return;
                }

                btn.disabled = true;
                spinner.classList.remove('d-none');

                fetch('{{ route('sales.collectPayment', $sale) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            payments: payments
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to collect payment.');
                            btn.disabled = false;
                            spinner.classList.add('d-none');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                        btn.disabled = false;
                        spinner.classList.add('d-none');
                    });
            });
        });
    </script>
@endpush
