@extends('layouts.app')

@section('title', 'Edit Category')

@section('content')
    <form action="{{ route('categories.update', $category) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

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
                                    id="name" name="name" value="{{ old('name', $category->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="slug" class="form-label">Slug</label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                    id="slug" name="slug" value="{{ old('slug', $category->slug) }}">
                                <div class="form-text">URL-friendly identifier</div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3">{{ old('description', $category->description) }}</textarea>
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
                                        {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                    @if ($parent->children->count() > 0)
                                        @foreach ($parent->children as $child)
                                            <option value="{{ $child->id }}"
                                                {{ old('parent_id', $category->parent_id) == $child->id ? 'selected' : '' }}>
                                                &nbsp;&nbsp;└─ {{ $child->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                            <div class="form-text">Change parent to reorganize category hierarchy</div>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label for="order" class="form-label">Display Order</label>
                            <input type="number" class="form-control @error('order') is-invalid @enderror" id="order"
                                name="order" value="{{ old('order', $category->order ?? 0) }}" min="0">
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
                        @if ($category->image)
                            <div class="mb-3">
                                <label class="form-label">Current Image</label>
                                <div class="position-relative d-inline-block">
                                    <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                                        class="img-thumbnail" style="max-height: 200px;">
                                </div>
                            </div>
                        @endif
                        <div class="mb-0">
                            <label for="image"
                                class="form-label">{{ $category->image ? 'Replace Image' : 'Upload Image' }}</label>
                            <input type="file" class="form-control @error('image') is-invalid @enderror" id="image"
                                name="image" accept="image/*">
                            <div class="form-text">Recommended: 400x400px, max 2MB</div>
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div id="image-preview" class="d-none mt-3">
                            <label class="form-label">New Image Preview</label>
                            <div>
                                <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                            </div>
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
                                <option value="active"
                                    {{ old('status', $category->status->value) == 'active' ? 'selected' : '' }}>Active
                                </option>
                                <option value="inactive"
                                    {{ old('status', $category->status->value) == 'inactive' ? 'selected' : '' }}>Inactive
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
                                Update Category
                            </button>
                            <a href="{{ route('categories.show', $category) }}" class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:close-circle-line-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Cancel
                            </a>
                        </div>

                        <hr class="my-3">

                        <!-- Quick Actions -->
                        <div class="mb-2">
                            <h6 class="fs-13 mb-2">Quick Actions:</h6>
                        </div>

                        @if ($category->status->value === 'inactive')
                            @can('update', $category)
                                <form action="{{ route('categories.activate', $category) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <iconify-icon icon="solar:check-circle-bold" class="align-middle me-1"></iconify-icon>
                                        Activate Category
                                    </button>
                                </form>
                            @endcan
                        @else
                            @can('update', $category)
                                <form action="{{ route('categories.deactivate', $category) }}" method="POST" class="mb-2"
                                    onsubmit="return confirm('Deactivate this category?')">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm w-100">
                                        <iconify-icon icon="solar:pause-circle-bold" class="align-middle me-1"></iconify-icon>
                                        Deactivate Category
                                    </button>
                                </form>
                            @endcan
                        @endif

                        @can('delete', $category)
                            <hr class="my-3">
                            <button type="button" class="btn btn-soft-danger btn-sm w-100" data-bs-toggle="modal"
                                data-bs-target="#deleteModal">
                                <iconify-icon icon="solar:trash-bin-minimalistic-bold"
                                    class="align-middle me-1"></iconify-icon>
                                Delete Category
                            </button>
                        @endcan
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card border-info border-opacity-25">
                    <div class="card-body">
                        <h6 class="card-title mb-3">
                            <iconify-icon icon="solar:info-circle-bold-duotone" class="text-info me-1"></iconify-icon>
                            Category Information
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">UUID:</td>
                                        <td class="text-end"><code class="fs-11">{{ $category->uuid }}</code></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Slug:</td>
                                        <td class="text-end">{{ $category->slug }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td class="text-end">{{ $category->created_at->format('M d, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Updated:</td>
                                        <td class="text-end">{{ $category->updated_at->diffForHumans() }}</td>
                                    </tr>
                                    @if ($category->children->count() > 0)
                                        <tr>
                                            <td class="text-muted">Subcategories:</td>
                                            <td class="text-end"><span
                                                    class="badge bg-info">{{ $category->children->count() }}</span></td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Delete Modal -->
    @can('delete', $category)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel">
                            <iconify-icon icon="solar:trash-bin-minimalistic-bold" class="align-middle me-2"></iconify-icon>
                            Delete Category
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <form action="{{ route('categories.destroy', $category) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body">
                            <div class="text-center mb-3">
                                <iconify-icon icon="solar:danger-triangle-bold-duotone" class="text-danger"
                                    style="font-size: 4rem;"></iconify-icon>
                            </div>
                            <h5 class="text-center mb-3">Are you absolutely sure?</h5>
                            <p class="text-muted text-center mb-3">
                                This will permanently delete <strong>{{ $category->name }}</strong> and all its data.
                            </p>
                            @if ($category->children->count() > 0)
                                <div class="alert alert-warning mb-0">
                                    <strong>Warning:</strong> This category has {{ $category->children->count() }}
                                    subcategory(ies). They will also be affected.
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">
                                <iconify-icon icon="solar:trash-bin-minimalistic-bold"
                                    class="align-middle me-1"></iconify-icon>
                                Yes, Delete Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @push('scripts')
        <script>
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
