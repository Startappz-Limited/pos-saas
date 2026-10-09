@extends('layouts.app')

@section('title', 'Create Category')

@section('content')
    <form action="{{ route('categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:folder-bold-duotone"
                                class="align-middle text-primary me-2"></iconify-icon>
                            Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Category Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="slug" class="form-label">Slug</label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                    id="slug" name="slug" value="{{ old('slug') }}"
                                    placeholder="Auto-generated from name">
                                <div class="form-text">Leave empty to auto-generate from name</div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Hierarchy -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:soundwave-bold-duotone"
                                class="align-middle text-info me-2"></iconify-icon>
                            Category Hierarchy
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Parent Category</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id"
                                name="parent_id">
                                <option value="">None (Root Category)</option>
                                @foreach ($rootCategories as $parent)
                                    <option value="{{ $parent->id }}"
                                        {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                    @if ($parent->children->count() > 0)
                                        @foreach ($parent->children as $child)
                                            <option value="{{ $child->id }}"
                                                {{ old('parent_id') == $child->id ? 'selected' : '' }}>
                                                &nbsp;&nbsp;└─ {{ $child->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                            <div class="form-text">Select a parent category to create a subcategory</div>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label for="order" class="form-label">Display Order</label>
                            <input type="number" class="form-control @error('order') is-invalid @enderror" id="order"
                                name="order" value="{{ old('order', 0) }}" min="0">
                            <div class="form-text">Lower numbers appear first</div>
                            @error('order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Category Image -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:gallery-bold-duotone"
                                class="align-middle text-success me-2"></iconify-icon>
                            Category Image
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="image" class="form-label">Upload Image</label>
                            <input type="file" class="form-control @error('image') is-invalid @enderror" id="image"
                                name="image" accept="image/*">
                            <div class="form-text">Recommended: 400x400px, max 2MB</div>
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div id="image-preview" class="d-none">
                            <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Settings -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:settings-bold-duotone"
                                class="align-middle text-warning me-2"></iconify-icon>
                            Category Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status"
                                name="status" required>
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active
                                </option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive
                                </option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <iconify-icon icon="solar:check-circle-line-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Create Category
                            </button>
                            <a href="{{ route('categories.index') }}" class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:close-circle-line-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card border-primary border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <iconify-icon icon="solar:info-circle-bold-duotone"
                                class="text-primary fs-20 me-2"></iconify-icon>
                            <h6 class="mb-0">Quick Tips</h6>
                        </div>
                        <ul class="mb-0 ps-3">
                            <li class="mb-1"><small>Use clear, descriptive names</small></li>
                            <li class="mb-1"><small>Root categories appear at top level</small></li>
                            <li class="mb-1"><small>Subcategories help organize products</small></li>
                            <li class="mb-1"><small>Lower order numbers show first</small></li>
                            <li class="mb-1"><small>Images improve visual browsing</small></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            // Auto-generate slug from name
            document.getElementById('name').addEventListener('input', function() {
                const slugInput = document.getElementById('slug');
                if (!slugInput.value || slugInput.value === '') {
                    const slug = this.value.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    slugInput.value = slug;
                }
            });

            // Image preview
            document.getElementById('image').addEventListener('change', function(e) {
                const preview = document.getElementById('image-preview');
                const file = e.target.files[0];

                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.querySelector('img').src = e.target.result;
                        preview.classList.remove('d-none');
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.classList.add('d-none');
                }
            });
        </script>
    @endpush
@endsection
