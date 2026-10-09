@extends('layouts.app')

@section('title', 'Create Expense Category')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('expense-categories.index') }}">Expense Categories</a>
                        </li>
                        <li class="breadcrumb-item active">Create New</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Add New Expense Category</h4>
            </div>
        </div>

        <form action="{{ route('expense-categories.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-xl-8">
                    <!-- Basic Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Category Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}"
                                        placeholder="Enter category name" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Type <span class="text-danger">*</span></label>
                                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                        <option value="">Select Type</option>
                                        @foreach ($types as $type)
                                            <option value="{{ $type->value }}"
                                                {{ old('type') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3"
                                    placeholder="Category description">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Parent Category</label>
                                    <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                        <option value="">None (Root Category)</option>
                                        @foreach ($parentCategories as $parent)
                                            <option value="{{ $parent->id }}"
                                                {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                                {{ $parent->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('parent_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" min="0"
                                        class="form-control @error('sort_order') is-invalid @enderror"
                                        value="{{ old('sort_order', 0) }}">
                                    @error('sort_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Icon</label>
                                    <input type="text" name="icon"
                                        class="form-control @error('icon') is-invalid @enderror"
                                        value="{{ old('icon') }}" placeholder="e.g. solar:wallet-bold-duotone">
                                    @error('icon')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Color</label>
                                    <input type="text" name="color"
                                        class="form-control @error('color') is-invalid @enderror"
                                        value="{{ old('color') }}" placeholder="e.g. primary, danger, success">
                                    @error('color')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Budget Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Budget Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Monthly Budget</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="monthly_budget" step="0.01" min="0"
                                            class="form-control @error('monthly_budget') is-invalid @enderror"
                                            value="{{ old('monthly_budget') }}" placeholder="0.00">
                                    </div>
                                    @error('monthly_budget')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Yearly Budget</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="yearly_budget" step="0.01" min="0"
                                            class="form-control @error('yearly_budget') is-invalid @enderror"
                                            value="{{ old('yearly_budget') }}" placeholder="0.00">
                                    </div>
                                    @error('yearly_budget')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Approval &amp; Flags</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_operational" value="1"
                                            class="form-check-input" id="is_operational"
                                            {{ old('is_operational') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_operational">Operational</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_tax_deductible" value="1"
                                            class="form-check-input" id="is_tax_deductible"
                                            {{ old('is_tax_deductible') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_tax_deductible">Tax Deductible</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="requires_approval" value="1"
                                            class="form-check-input" id="requires_approval"
                                            {{ old('requires_approval') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="requires_approval">Requires Approval</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Approval Threshold</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="approval_threshold" step="0.01" min="0"
                                            class="form-control @error('approval_threshold') is-invalid @enderror"
                                            value="{{ old('approval_threshold') }}" placeholder="0.00">
                                    </div>
                                    @error('approval_threshold')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <!-- Submit -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-line-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Create Category
                                </button>
                                <a href="{{ route('expense-categories.index') }}" class="btn btn-soft-secondary">
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
