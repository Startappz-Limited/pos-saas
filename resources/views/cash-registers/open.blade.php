@extends('layouts.app')

@section('title', 'Open Cash Register')

@section('content')

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:wallet-money-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Open Cash Register
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <iconify-icon icon="solar:info-circle-bold" class="align-middle me-2"></iconify-icon>
                        <strong>Note:</strong> The register will auto-open with {{ currency_symbol() }}0.00 balance when you
                        create the first
                        sale.
                        You can manually open it here if you want to set a specific opening balance.
                    </div>

                    <form action="{{ route('cash-registers.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="opening_balance" class="form-label">Opening Balance <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                <input type="number" class="form-control @error('opening_balance') is-invalid @enderror"
                                    id="opening_balance" name="opening_balance" value="{{ old('opening_balance', '0.00') }}"
                                    step="0.01" min="0" required autofocus>
                            </div>
                            @error('opening_balance')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Count the cash in the drawer and enter the total amount.</div>
                        </div>

                        <div class="mb-3">
                            <label for="expense_opening_balance" class="form-label">Expense Opening Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                <input type="number"
                                    class="form-control @error('expense_opening_balance') is-invalid @enderror"
                                    id="expense_opening_balance" name="expense_opening_balance"
                                    value="{{ old('expense_opening_balance', '0.00') }}" step="0.01" min="0">
                            </div>
                            @error('expense_opening_balance')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Cash held by the cashier for expenses (petty cash, supplies, etc.).</div>
                        </div>

                        <div class="mb-3">
                            <label for="opening_notes" class="form-label">Opening Notes</label>
                            <textarea class="form-control @error('opening_notes') is-invalid @enderror" id="opening_notes" name="opening_notes"
                                rows="3" placeholder="Optional notes about opening balance breakdown, e.g., '2x $100 bills, 5x $20 bills...'">{{ old('opening_notes') }}</textarea>
                            @error('opening_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="border-top pt-3 mt-4">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="{{ route('cash-registers.index') }}" class="btn btn-soft-secondary">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon> Open Register
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <iconify-icon icon="solar:lightbulb-bolt-bold-duotone"
                            class="text-warning align-middle me-2"></iconify-icon>
                        Quick Tips
                    </h6>
                    <ul class="mb-0">
                        <li class="mb-2">Count all cash currently in the drawer</li>
                        <li class="mb-2">Double-check large bills</li>
                        <li class="mb-2">Include coins in your count</li>
                        <li class="mb-2">Record any unusual circumstances in notes</li>
                        <li>Keep receipts for float additions</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

@endsection
