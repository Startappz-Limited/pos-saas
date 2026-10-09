@extends('layouts.app')

@section('title', 'Category Details')

@section('content')
    <div class="row">
        <!-- Category Profile -->
        <div class="col-lg-8">
            <!-- Category Header Card -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-4">
                        <div class="flex-shrink-0 me-3">
                            @if ($category->image)
                                <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                                    class="avatar-lg rounded">
                            @else
                                <div class="avatar-lg">
                                    <div class="avatar-title bg-primary-subtle text-primary rounded fs-1">
                                        {{ substr($category->name, 0, 1) }}
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <h4 class="mb-1">{{ $category->name }}</h4>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-secondary-subtle text-secondary">{{ $category->slug }}</span>
                                @if ($category->status->value === 'active')
                                    <span class="badge bg-success-subtle text-success">
                                        <iconify-icon icon="solar:check-circle-bold" class="align-middle"></iconify-icon>
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning">
                                        <iconify-icon icon="solar:pause-circle-bold" class="align-middle"></iconify-icon>
                                        Inactive
                                    </span>
                                @endif
                                @if (!$category->parent_id)
                                    <span class="badge bg-info-subtle text-info">
                                        <iconify-icon icon="solar:star-bold" class="align-middle"></iconify-icon> Root
                                        Category
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @can('update', $category)
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-primary">
                                    <iconify-icon icon="solar:pen-linear" class="align-middle me-1"></iconify-icon>
                                    Edit Category
                                </a>
                            @endcan
                        </div>
                    </div>

                    @if ($category->description)
                        <div class="alert alert-info border-info">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-2">
                                    <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-20"></iconify-icon>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-0">{{ $category->description }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Category Details -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:document-text-bold-duotone"
                            class="align-middle text-primary me-2"></iconify-icon>
                        Category Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Hierarchy Information -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">
                                <iconify-icon icon="solar:soundwave-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Hierarchy
                            </h6>
                            @if ($category->parent)
                                <div class="mb-2">
                                    <label class="text-muted mb-1">Parent Category:</label>
                                    <div class="d-flex align-items-center">
                                        @if ($category->parent->image)
                                            <img src="{{ asset('storage/' . $category->parent->image) }}"
                                                alt="{{ $category->parent->name }}" class="avatar-xxs rounded me-2">
                                        @else
                                            <div class="avatar-xxs me-2">
                                                <div class="avatar-title bg-primary-subtle text-primary rounded fs-11">
                                                    {{ substr($category->parent->name, 0, 1) }}
                                                </div>
                                            </div>
                                        @endif
                                        <a href="{{ route('categories.show', $category->parent) }}"
                                            class="fw-medium">{{ $category->parent->name }}</a>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted mb-2">This is a root category (no parent)</p>
                            @endif
                            <div>
                                <label class="text-muted mb-1">Display Order:</label>
                                <p class="mb-0"><span class="badge bg-secondary">{{ $category->order ?? 0 }}</span></p>
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-semibold text-muted mb-3">
                                <iconify-icon icon="solar:clock-circle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Metadata
                            </h6>
                            <div class="vstack gap-2">
                                <div>
                                    <small class="text-muted">Created:</small>
                                    <p class="mb-0">{{ $category->created_at->format('M d, Y \a\t h:i A') }}</p>
                                </div>
                                <div>
                                    <small class="text-muted">Last Updated:</small>
                                    <p class="mb-0">{{ $category->updated_at->diffForHumans() }}</p>
                                </div>
                                @if ($category->creator)
                                    <div>
                                        <small class="text-muted">Created By:</small>
                                        <p class="mb-0">{{ $category->creator->name }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subcategories -->
            @if ($category->children->count() > 0)
                <div class="card">
                    <div class="card-header border-bottom-dashed">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title mb-0">
                                    <iconify-icon icon="solar:folder-with-files-bold-duotone"
                                        class="align-middle text-success me-2"></iconify-icon>
                                    Subcategories ({{ $category->children->count() }})
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th>Order</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($category->children->sortBy('order') as $child)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @if ($child->image)
                                                        <img src="{{ asset('storage/' . $child->image) }}"
                                                            alt="{{ $child->name }}" class="avatar-xxs rounded me-2">
                                                    @else
                                                        <div class="avatar-xxs me-2">
                                                            <div
                                                                class="avatar-title bg-primary-subtle text-primary rounded fs-11">
                                                                {{ substr($child->name, 0, 1) }}
                                                            </div>
                                                        </div>
                                                    @endif
                                                    <a
                                                        href="{{ route('categories.show', $child) }}">{{ $child->name }}</a>
                                                </div>
                                            </td>
                                            <td><span
                                                    class="badge bg-secondary-subtle text-secondary">{{ $child->order ?? 0 }}</span>
                                            </td>
                                            <td>
                                                @if ($child->status->value === 'active')
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="hstack gap-2">
                                                    @can('view', $child)
                                                        <a href="{{ route('categories.show', $child) }}"
                                                            class="btn btn-sm btn-soft-info">
                                                            <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                                        </a>
                                                    @endcan
                                                    @can('update', $child)
                                                        <a href="{{ route('categories.edit', $child) }}"
                                                            class="btn btn-sm btn-soft-primary">
                                                            <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                        </a>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title text-white mb-0">
                        <iconify-icon icon="solar:chart-2-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Quick Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="vstack gap-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Subcategories</p>
                                <h4 class="mb-0">{{ $category->children->count() }}</h4>
                            </div>
                            <div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-info-subtle rounded">
                                        <iconify-icon icon="solar:folder-with-files-bold-duotone"
                                            class="text-info fs-22"></iconify-icon>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Products</p>
                                <h4 class="mb-0">{{ $category->products_count ?? 0 }}</h4>
                            </div>
                            <div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-success-subtle rounded">
                                        <iconify-icon icon="solar:box-bold-duotone"
                                            class="text-success fs-22"></iconify-icon>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Display Order</p>
                                <h4 class="mb-0">{{ $category->order ?? 0 }}</h4>
                            </div>
                            <div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-warning-subtle rounded">
                                        <iconify-icon icon="solar:sort-bold-duotone"
                                            class="text-warning fs-22"></iconify-icon>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:bolt-bold-duotone"
                            class="align-middle text-warning me-2"></iconify-icon>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('update', $category)
                            <a href="{{ route('categories.edit', $category) }}" class="btn btn-primary">
                                <iconify-icon icon="solar:pen-linear" class="align-middle me-1"></iconify-icon>
                                Edit Category
                            </a>
                        @endcan

                        @if ($category->status->value === 'inactive')
                            @can('update', $category)
                                <form action="{{ route('categories.activate', $category) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <iconify-icon icon="solar:check-circle-bold" class="align-middle me-1"></iconify-icon>
                                        Activate Category
                                    </button>
                                </form>
                            @endcan
                        @else
                            @can('update', $category)
                                <form action="{{ route('categories.deactivate', $category) }}" method="POST"
                                    onsubmit="return confirm('Deactivate this category?')">
                                    @csrf
                                    <button type="submit" class="btn btn-warning w-100">
                                        <iconify-icon icon="solar:pause-circle-bold" class="align-middle me-1"></iconify-icon>
                                        Deactivate Category
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="card border-secondary border-opacity-25">
                <div class="card-header bg-secondary-subtle">
                    <h6 class="card-title mb-0 text-secondary">
                        <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                        System Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">UUID:</td>
                                    <td class="text-end">
                                        <code class="fs-11">{{ Str::limit($category->uuid, 20, '...') }}</code>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Slug:</td>
                                    <td class="text-end">{{ $category->slug }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Created:</td>
                                    <td class="text-end">{{ $category->created_at->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Last Updated:</td>
                                    <td class="text-end">{{ $category->updated_at->format('Y-m-d') }}</td>
                                </tr>
                                @if ($category->deleted_at)
                                    <tr>
                                        <td class="text-muted">Deleted:</td>
                                        <td class="text-end text-danger">{{ $category->deleted_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
