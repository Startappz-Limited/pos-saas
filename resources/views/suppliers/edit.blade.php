@extends('layouts.app')

@section('title', 'Edit Supplier: ' . $supplier->name)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Edit Supplier: {{ $supplier->name }}</h4>
            </div>
        </div>

        <form action="{{ route('suppliers.update', $supplier) }}" method="POST" id="supplier-form">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-xl-8">
                    <!-- Basic Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $supplier->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier Code</label>
                                    <input type="text" name="code"
                                        class="form-control @error('code') is-invalid @enderror"
                                        value="{{ old('code', $supplier->code) }}">
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $supplier->email) }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone"
                                        class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $supplier->phone) }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" name="mobile"
                                        class="form-control @error('mobile') is-invalid @enderror"
                                        value="{{ old('mobile', $supplier->mobile) }}">
                                    @error('mobile')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" name="website"
                                    class="form-control @error('website') is-invalid @enderror"
                                    value="{{ old('website', $supplier->website) }}">
                                @error('website')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Contact Person -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Contact Person</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Name</label>
                                    <input type="text" name="contact_person"
                                        class="form-control @error('contact_person') is-invalid @enderror"
                                        value="{{ old('contact_person', $supplier->contact_person) }}">
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" name="contact_email"
                                        class="form-control @error('contact_email') is-invalid @enderror"
                                        value="{{ old('contact_email', $supplier->contact_email) }}">
                                    @error('contact_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="text" name="contact_phone"
                                        class="form-control @error('contact_phone') is-invalid @enderror"
                                        value="{{ old('contact_phone', $supplier->contact_phone) }}">
                                    @error('contact_phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Address</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Street Address</label>
                                <input type="text" name="address"
                                    class="form-control @error('address') is-invalid @enderror"
                                    value="{{ old('address', $supplier->address) }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city"
                                        class="form-control @error('city') is-invalid @enderror"
                                        value="{{ old('city', $supplier->city) }}">
                                    @error('city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="state"
                                        class="form-control @error('state') is-invalid @enderror"
                                        value="{{ old('state', $supplier->state) }}">
                                    @error('state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country"
                                        class="form-control @error('country') is-invalid @enderror"
                                        value="{{ old('country', $supplier->country) }}">
                                    @error('country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" name="postal_code"
                                        class="form-control @error('postal_code') is-invalid @enderror"
                                        value="{{ old('postal_code', $supplier->postal_code) }}">
                                    @error('postal_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial & Business Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Financial & Business Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Number</label>
                                    <input type="text" name="tax_number"
                                        class="form-control @error('tax_number') is-invalid @enderror"
                                        value="{{ old('tax_number', $supplier->tax_number) }}">
                                    @error('tax_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Registration Number</label>
                                    <input type="text" name="registration_number"
                                        class="form-control @error('registration_number') is-invalid @enderror"
                                        value="{{ old('registration_number', $supplier->registration_number) }}">
                                    @error('registration_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select @error('currency') is-invalid @enderror">
                                        <option value="">— Select Currency —</option>
                                        @foreach (['KES' => 'KES - Kenyan Shilling', 'USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound', 'JPY' => 'JPY - Japanese Yen', 'KWD' => 'KWD - Kuwaiti Dinar', 'AED' => 'AED - UAE Dirham', 'SAR' => 'SAR - Saudi Riyal'] as $code => $label)
                                            <option value="{{ $code }}"
                                                {{ (old('currency') ?: $supplier->currency) === $code ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('currency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Payment Terms (Days)</label>
                                    <input type="number" name="payment_terms_days"
                                        class="form-control @error('payment_terms_days') is-invalid @enderror"
                                        value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}"
                                        min="0">
                                    @error('payment_terms_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Credit Limit</label>
                                    <input type="number" name="credit_limit"
                                        class="form-control @error('credit_limit') is-invalid @enderror"
                                        value="{{ old('credit_limit', $supplier->credit_limit) }}" min="0"
                                        step="0.01">
                                    @error('credit_limit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Additional Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $supplier->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-xl-4">
                    <!-- Status & Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Status & Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror"
                                    required>
                                    @foreach (App\Enums\SupplierStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ old('status', $supplier->status->value) === $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Update Supplier
                                </button>
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-light">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Stats -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Supplier Statistics</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Total Orders:</td>
                                    <td class="fw-medium">{{ $supplier->order_count }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Orders Value:</td>
                                    <td class="fw-medium">{{ number_format($supplier->total_orders, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Current Balance:</td>
                                    <td class="fw-medium">{{ number_format($supplier->current_balance, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">On-Time Deliveries:</td>
                                    <td class="fw-medium">{{ $supplier->on_time_deliveries }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Late Deliveries:</td>
                                    <td class="fw-medium">{{ $supplier->late_deliveries }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
