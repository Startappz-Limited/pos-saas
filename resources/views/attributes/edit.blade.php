@extends('layouts.app')

@section('title', 'Edit Attribute')

@section('content')
    <div>

        <form action="{{ route('attributes.update', $attribute) }}" method="POST">
            @csrf
            @method('PUT')

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
                                    id="name" name="name" value="{{ old('name', $attribute->name) }}"
                                    placeholder="e.g., Color, Size, Material" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug</label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                    id="slug" name="slug" value="{{ old('slug', $attribute->slug) }}"
                                    placeholder="auto-generated">
                                <small class="text-muted">Leave blank to auto-generate from name</small>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3" placeholder="Describe this attribute...">{{ old('description', $attribute->description) }}</textarea>
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
                                    id="values" name="values"
                                    value="{{ old('values', is_array($attribute->values) ? implode(', ', $attribute->values) : '') }}"
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
                                    <option value="dropdown"
                                        {{ old('type', $attribute->type) === 'dropdown' ? 'selected' : '' }}>Dropdown
                                    </option>
                                    <option value="radio"
                                        {{ old('type', $attribute->type) === 'radio' ? 'selected' : '' }}>Radio Buttons
                                    </option>
                                    <option value="checkbox"
                                        {{ old('type', $attribute->type) === 'checkbox' ? 'selected' : '' }}>Checkboxes
                                    </option>
                                    <option value="color"
                                        {{ old('type', $attribute->type) === 'color' ? 'selected' : '' }}>Color Picker
                                    </option>
                                    <option value="button"
                                        {{ old('type', $attribute->type) === 'button' ? 'selected' : '' }}>Button Group
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
                                    id="display_order" name="display_order"
                                    value="{{ old('display_order', $attribute->display_order) }}" min="0">
                                <small class="text-muted">Lower numbers appear first</small>
                                @error('display_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_required"
                                        name="is_required" value="1"
                                        {{ old('is_required', $attribute->is_required) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_required">Required Attribute</label>
                                </div>
                                <small class="text-muted d-block">Must be selected for products</small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_visible"
                                        name="is_visible" value="1"
                                        {{ old('is_visible', $attribute->is_visible) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_visible">Visible to Customers</label>
                                </div>
                                <small class="text-muted d-block">Show on product pages</small>
                            </div>

                            <div class="mb-0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                                        name="is_active" value="1"
                                        {{ old('is_active', $attribute->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active Status</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    @if ($attribute->is_active)
                        <div class="card border-warning">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Quick Actions</h6>
                                <form action="{{ route('attributes.deactivate', $attribute) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning w-100 mb-2"
                                        onclick="return confirm('Are you sure you want to deactivate this attribute?')">
                                        <iconify-icon icon="solar:pause-circle-bold-duotone"
                                            class="me-1"></iconify-icon>
                                        Deactivate
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Quick Actions</h6>
                                <form action="{{ route('attributes.activate', $attribute) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100 mb-2">
                                        <iconify-icon icon="solar:play-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Activate
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <!-- Attribute Info -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Attribute Info</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted">ID:</small>
                                <p class="mb-0"><code>{{ $attribute->id }}</code></p>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Slug:</small>
                                <p class="mb-0">{{ $attribute->slug }}</p>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Created:</small>
                                <p class="mb-0">{{ $attribute->created_at->format('M d, Y') }}</p>
                            </div>
                            <div class="mb-0">
                                <small class="text-muted">Last Updated:</small>
                                <p class="mb-0">{{ $attribute->updated_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                                Update Attribute
                            </button>
                            <a href="{{ route('attributes.index') }}" class="btn btn-light w-100 mb-2">
                                <iconify-icon icon="solar:close-circle-bold-duotone" class="me-1"></iconify-icon>
                                Cancel
                            </a>

                            @can('delete', $attribute)
                                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken" class="me-1"></iconify-icon>
                                    Delete
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>

    <!-- Delete Modal -->
    @can('delete', $attribute)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Delete Attribute</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the attribute <strong>{{ $attribute->name }}</strong>?</p>
                        <div class="alert alert-warning">
                            <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                            This action cannot be undone. Products using this attribute may be affected.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('attributes.destroy', $attribute) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete Attribute</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan

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
