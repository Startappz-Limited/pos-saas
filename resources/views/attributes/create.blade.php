@extends('layouts.app')

@section('title', 'Create Attribute')

@section('content')
    <div>

        <form action="{{ route('attributes.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Attribute Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}"
                                    placeholder="e.g., Color, Size, Material" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug</label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                    id="slug" name="slug" value="{{ old('slug') }}" placeholder="auto-generated">
                                <small class="text-muted">Leave blank to auto-generate from name</small>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3" placeholder="Describe this attribute...">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Attribute Values -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Attribute Values</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="values" class="form-label">Values <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('values') is-invalid @enderror"
                                    id="values" name="values" value="{{ old('values') }}"
                                    placeholder="e.g., Red, Blue, Green, Yellow" required>
                                <small class="text-muted">Enter values separated by commas</small>
                                @error('values')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-0">
                                <label for="type" class="form-label">Display Type <span
                                        class="text-danger">*</span></label>
                                <select class="form-select @error('type') is-invalid @enderror" id="type"
                                    name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="dropdown" {{ old('type') === 'dropdown' ? 'selected' : '' }}>Dropdown
                                    </option>
                                    <option value="radio" {{ old('type') === 'radio' ? 'selected' : '' }}>Radio Buttons
                                    </option>
                                    <option value="checkbox" {{ old('type') === 'checkbox' ? 'selected' : '' }}>Checkboxes
                                    </option>
                                    <option value="color" {{ old('type') === 'color' ? 'selected' : '' }}>Color Picker
                                    </option>
                                    <option value="button" {{ old('type') === 'button' ? 'selected' : '' }}>Button Group
                                    </option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control @error('display_order') is-invalid @enderror"
                                    id="display_order" name="display_order" value="{{ old('display_order', 0) }}"
                                    min="0">
                                <small class="text-muted">Lower numbers appear first</small>
                                @error('display_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_required"
                                        name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_required">Required Attribute</label>
                                </div>
                                <small class="text-muted d-block">Must be selected for products</small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_visible"
                                        name="is_visible" value="1" {{ old('is_visible', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_visible">Visible to Customers</label>
                                </div>
                                <small class="text-muted d-block">Show on product pages</small>
                            </div>

                            <div class="mb-0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                                        name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active Status</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tips</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <iconify-icon icon="solar:check-circle-bold" class="text-success me-2"></iconify-icon>
                                    <small>Use clear, descriptive names</small>
                                </li>
                                <li class="mb-2">
                                    <iconify-icon icon="solar:check-circle-bold" class="text-success me-2"></iconify-icon>
                                    <small>Add all possible values</small>
                                </li>
                                <li class="mb-2">
                                    <iconify-icon icon="solar:check-circle-bold" class="text-success me-2"></iconify-icon>
                                    <small>Choose appropriate display type</small>
                                </li>
                                <li class="mb-2">
                                    <iconify-icon icon="solar:check-circle-bold" class="text-success me-2"></iconify-icon>
                                    <small>Set display order for priority</small>
                                </li>
                                <li class="mb-0">
                                    <iconify-icon icon="solar:check-circle-bold" class="text-success me-2"></iconify-icon>
                                    <small>Mark required for important attributes</small>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                                Create Attribute
                            </button>
                            <a href="{{ route('attributes.index') }}" class="btn btn-light w-100">
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
            // Auto-generate slug from name
            document.getElementById('name').addEventListener('input', function() {
                const name = this.value;
                const slugInput = document.getElementById('slug');

                if (!slugInput.dataset.manuallyEdited) {
                    const slug = name
                        .toLowerCase()
                        .trim()
                        .replace(/[^\w\s-]/g, '')
                        .replace(/[\s_-]+/g, '-')
                        .replace(/^-+|-+$/g, '');

                    slugInput.value = slug;
                }
            });

            document.getElementById('slug').addEventListener('input', function() {
                if (this.value !== '') {
                    this.dataset.manuallyEdited = 'true';
                }
            });
        </script>
    @endpush
@endsection
