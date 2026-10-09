@extends('layouts.app')

@section('title', 'Create Supplier')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
                        <li class="breadcrumb-item active">Create New</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Add New Supplier</h4>
            </div>
        </div>

        <form action="{{ route('suppliers.store') }}" method="POST" id="supplier-form">
            @csrf
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
                                        class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}"
                                        placeholder="Enter supplier name" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier Code</label>
                                    <input type="text" name="code"
                                        class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}"
                                        placeholder="Auto-generated if empty">
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
                                        value="{{ old('email') }}" placeholder="supplier@example.com">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone"
                                        class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone') }}" placeholder="Phone number">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" name="mobile"
                                        class="form-control @error('mobile') is-invalid @enderror"
                                        value="{{ old('mobile') }}" placeholder="Mobile number">
                                    @error('mobile')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" name="website"
                                    class="form-control @error('website') is-invalid @enderror"
                                    value="{{ old('website') }}" placeholder="https://www.example.com">
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
                                        value="{{ old('contact_person') }}" placeholder="Full name">
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" name="contact_email"
                                        class="form-control @error('contact_email') is-invalid @enderror"
                                        value="{{ old('contact_email') }}" placeholder="contact@example.com">
                                    @error('contact_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="text" name="contact_phone"
                                        class="form-control @error('contact_phone') is-invalid @enderror"
                                        value="{{ old('contact_phone') }}" placeholder="Phone number">
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
                                    value="{{ old('address') }}" placeholder="Street address">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city"
                                        class="form-control @error('city') is-invalid @enderror"
                                        value="{{ old('city') }}" placeholder="City">
                                    @error('city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="state"
                                        class="form-control @error('state') is-invalid @enderror"
                                        value="{{ old('state') }}" placeholder="State/Province">
                                    @error('state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country"
                                        class="form-control @error('country') is-invalid @enderror"
                                        value="{{ old('country') }}" placeholder="Country">
                                    @error('country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" name="postal_code"
                                        class="form-control @error('postal_code') is-invalid @enderror"
                                        value="{{ old('postal_code') }}" placeholder="Postal code">
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
                                        value="{{ old('tax_number') }}" placeholder="Tax identification number">
                                    @error('tax_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Registration Number</label>
                                    <input type="text" name="registration_number"
                                        class="form-control @error('registration_number') is-invalid @enderror"
                                        value="{{ old('registration_number') }}"
                                        placeholder="Business registration number">
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
                                                {{ (old('currency') ?: currency_code()) === $code ? 'selected' : '' }}>
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
                                        value="{{ old('payment_terms_days', 30) }}" min="0">
                                    @error('payment_terms_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Credit Limit</label>
                                    <input type="number" name="credit_limit"
                                        class="form-control @error('credit_limit') is-invalid @enderror"
                                        value="{{ old('credit_limit') }}" min="0" step="0.01"
                                        placeholder="0.00">
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
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3"
                                    placeholder="Any additional notes about this supplier...">{{ old('notes') }}</textarea>
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
                                            {{ old('status', 'active') === $status->value ? 'selected' : '' }}>
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
                                    Create Supplier
                                </button>
                                <a href="{{ route('suppliers.index') }}" class="btn btn-light">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
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
