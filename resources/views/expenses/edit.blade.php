@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('expenses.show', $expense) }}">{{ $expense->expense_number }}</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Edit Expense</h4>
            </div>
        </div>

        <form action="{{ route('expenses.update', $expense) }}" method="POST" id="expense-form">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-xl-8">
                    <!-- Basic Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Expense Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title"
                                        class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title', $expense->title) }}" placeholder="Enter expense title"
                                        required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category_id"
                                        class="form-select @error('category_id') is-invalid @enderror" required>
                                        <option value="">Select Category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ old('category_id', $expense->category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3"
                                    placeholder="Expense description">{{ old('description', $expense->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="amount" step="0.01" min="0.01"
                                            class="form-control @error('amount') is-invalid @enderror"
                                            value="{{ old('amount', $expense->amount) }}" placeholder="0.00" required>
                                    </div>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                                    <input type="date" name="expense_date"
                                        class="form-control @error('expense_date') is-invalid @enderror"
                                        value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}"
                                        required>
                                    @error('expense_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" name="due_date"
                                        class="form-control @error('due_date') is-invalid @enderror"
                                        value="{{ old('due_date', $expense->due_date?->format('Y-m-d')) }}">
                                    @error('due_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Vendor/Supplier</label>
                                    <select name="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror">
                                        <option value="">Select Vendor (Optional)</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}"
                                                {{ old('vendor_id', $expense->vendor_id) == $vendor->id ? 'selected' : '' }}>
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vendor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method"
                                        class="form-select @error('payment_method') is-invalid @enderror">
                                        <option value="">Select Method</option>
                                        <option value="cash"
                                            {{ old('payment_method', $expense->payment_method) == 'cash' ? 'selected' : '' }}>
                                            Cash</option>
                                        <option value="card"
                                            {{ old('payment_method', $expense->payment_method) == 'card' ? 'selected' : '' }}>
                                            Card</option>
                                        <option value="bank_transfer"
                                            {{ old('payment_method', $expense->payment_method) == 'bank_transfer' ? 'selected' : '' }}>
                                            Bank Transfer</option>
                                        <option value="mobile_money"
                                            {{ old('payment_method', $expense->payment_method) == 'mobile_money' ? 'selected' : '' }}>
                                            Mobile Money</option>
                                        <option value="cheque"
                                            {{ old('payment_method', $expense->payment_method) == 'cheque' ? 'selected' : '' }}>
                                            Cheque</option>
                                    </select>
                                    @error('payment_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number"
                                    class="form-control @error('reference_number') is-invalid @enderror"
                                    value="{{ old('reference_number', $expense->reference_number) }}"
                                    placeholder="Invoice/Receipt number">
                                @error('reference_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Tax Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tax Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_tax_deductible" value="1"
                                            class="form-check-input" id="is_tax_deductible"
                                            {{ old('is_tax_deductible', $expense->is_tax_deductible) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_tax_deductible">Tax Deductible</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="tax_amount" step="0.01" min="0"
                                            class="form-control @error('tax_amount') is-invalid @enderror"
                                            value="{{ old('tax_amount', $expense->tax_amount) }}" placeholder="0.00">
                                    </div>
                                    @error('tax_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <!-- Current Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Status</h5>
                        </div>
                        <div class="card-body text-center">
                            <span class="badge bg-{{ $expense->status->color() }} fs-6 px-3 py-2">
                                {{ $expense->status->label() }}
                            </span>
                            <p class="text-muted mt-2 mb-0">
                                Created: {{ $expense->created_at->format('M d, Y H:i') }}
                            </p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Notes</h5>
                        </div>
                        <div class="card-body">
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="5"
                                placeholder="Additional notes...">{{ old('notes', $expense->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:pen-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Update Expense
                                </button>
                                <a href="{{ route('expenses.show', $expense) }}" class="btn btn-soft-secondary">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
