@extends('layouts.app')

@section('title', 'Create Customer')

@section('content')
    <div>
        <div class="mb-3">
            <a href="{{ route('customers.index') }}" class="btn btn-soft-secondary">
                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon> Back to Customers
            </a>
        </div>

        <form action="{{ route('customers.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                                            id="email" name="email" value="{{ old('email') }}">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                            id="phone" name="phone" value="{{ old('phone') }}">
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3">{{ old('address') }}</textarea>
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Credit Settings Card -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Credit Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="allow_credit"
                                        name="allow_credit" value="1" {{ old('allow_credit') ? 'checked' : '' }}
                                        onchange="toggleCreditLimit()">
                                    <label class="form-check-label" for="allow_credit">Allow Credit Sales</label>
                                </div>
                                <small class="text-muted">Enable this to allow the customer to make purchases on
                                    credit.</small>
                            </div>

                            <div class="mb-0" id="credit_limit_field"
                                style="{{ old('allow_credit') ? '' : 'display: none;' }}">
                                <label for="credit_limit" class="form-label">Credit Limit</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" class="form-control @error('credit_limit') is-invalid @enderror"
                                        id="credit_limit" name="credit_limit" value="{{ old('credit_limit', 0) }}"
                                        step="0.01" min="0">
                                </div>
                                @error('credit_limit')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Maximum amount the customer can owe at any time.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Customer Type Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Customer Type <span class="text-danger">*</span></h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="customer_type" id="type_retail"
                                        value="retail" {{ old('customer_type', 'retail') === 'retail' ? 'checked' : '' }}
                                        required>
                                    <label class="form-check-label" for="type_retail">
                                        <strong>Retail Customer</strong>
                                        <small class="d-block text-muted">Standard pricing for individual buyers</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="customer_type"
                                        id="type_wholesale" value="wholesale"
                                        {{ old('customer_type') === 'wholesale' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="type_wholesale">
                                        <strong>Wholesale Customer</strong>
                                        <small class="d-block text-muted">Discounted pricing for bulk buyers</small>
                                    </label>
                                </div>
                            </div>
                            @error('customer_type')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Status Card -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Status</h5>
                        </div>
                        <div class="card-body">
                            <select name="status" id="status"
                                class="form-select @error('status') is-invalid @enderror">
                                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active
                                </option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive
                                </option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div class="card mt-3">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                                Create Customer
                            </button>
                            <a href="{{ route('customers.index') }}" class="btn btn-light w-100">
                                <iconify-icon icon="solar:close-circle-bold-duotone" class="me-1"></iconify-icon>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function toggleCreditLimit() {
                const allowCredit = document.getElementById('allow_credit').checked;
                const creditLimitField = document.getElementById('credit_limit_field');

                if (allowCredit) {
                    creditLimitField.style.display = 'block';
                } else {
                    creditLimitField.style.display = 'none';
                    document.getElementById('credit_limit').value = 0;
                }
            }
        </script>
    @endpush
@endsection
