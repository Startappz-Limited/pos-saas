@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Total Categories</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['total'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-primary-subtle rounded fs-3">
                                    <iconify-icon icon="solar:folder-bold-duotone" class="text-primary"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Active Categories</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['active'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="text-success"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Parent Categories</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['root'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-info-subtle rounded fs-3">
                                    <iconify-icon icon="solar:folder-with-files-bold-duotone"
                                        class="text-info"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-3">Inactive Categories</p>
                            <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value"
                                    data-target="{{ $statistics['inactive'] ?? 0 }}">0</span></h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-warning-subtle rounded fs-3">
                                    <iconify-icon icon="solar:pause-circle-bold-duotone"
                                        class="text-warning"></iconify-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories List Card -->
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">All Categories</h5>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <a href="{{ route('categories.tree') }}" class="btn btn-soft-info">
                            <iconify-icon icon="solar:soundwave-bold-duotone" class="align-middle me-1"></iconify-icon> Tree
                            View
                        </a>
                        @can('create', App\Models\Category::class)
                            <a href="{{ route('categories.create') }}" class="btn btn-primary add-btn">
                                <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon> Add
                                Category
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Search and Filters -->
            <form method="GET" action="{{ route('categories.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-5 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="Search by name or description..." value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-sm-6">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive
                            </option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon> Filter
                        </button>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <a href="{{ route('categories.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            @if ($categories->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Category</th>
                                <th scope="col">Parent Category</th>
                                <th scope="col">Order</th>
                                <th scope="col">Products Count</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if ($category->image)
                                                <div class="flex-shrink-0 me-2">
                                                    <img src="{{ asset('storage/' . $category->image) }}"
                                                        alt="{{ $category->name }}" class="avatar-xs rounded">
                                                </div>
                                            @else
                                                <div class="flex-shrink-0 me-2">
                                                    <div class="avatar-xs">
                                                        <div
                                                            class="avatar-title bg-primary-subtle text-primary rounded fs-16">
                                                            {{ substr($category->name, 0, 1) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0">
                                                    <a href="{{ route('categories.show', $category) }}"
                                                        class="text-body">{{ $category->name }}</a>
                                                </h6>
                                                @if ($category->description)
                                                    <small
                                                        class="text-muted">{{ Str::limit($category->description, 40) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($category->parent)
                                            <div class="d-flex align-items-center">
                                                <iconify-icon icon="solar:folder-bold"
                                                    class="text-muted me-1"></iconify-icon>
                                                <a href="{{ route('categories.show', $category->parent) }}"
                                                    class="text-body">
                                                    {{ $category->parent->name }}
                                                </a>
                                            </div>
                                        @else
                                            <span class="badge bg-info-subtle text-info">
                                                <iconify-icon icon="solar:star-bold" class="align-middle"></iconify-icon>
                                                Root
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-secondary-subtle text-secondary">{{ $category->order ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <iconify-icon icon="solar:box-bold" class="text-muted me-1"></iconify-icon>
                                            <span>{{ $category->products_count ?? 0 }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($category->status->value === 'active')
                                            <span class="badge bg-success-subtle text-success">
                                                <iconify-icon icon="solar:check-circle-bold"
                                                    class="align-middle"></iconify-icon> Active
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning">
                                                <iconify-icon icon="solar:pause-circle-bold"
                                                    class="align-middle"></iconify-icon> Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2">
                                            @can('view', $category)
                                                <a href="{{ route('categories.show', $category) }}"
                                                    class="btn btn-sm btn-soft-info">
                                                    <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('update', $category)
                                                <a href="{{ route('categories.edit', $category) }}"
                                                    class="btn btn-sm btn-soft-primary">
                                                    <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('delete', $category)
                                                <button type="button" class="btn btn-sm btn-soft-danger"
                                                    onclick="confirmDelete('{{ $category->uuid }}')">
                                                    <iconify-icon icon="solar:trash-bin-minimalistic-linear"></iconify-icon>
                                                </button>
                                                <form id="delete-form-{{ $category->uuid }}"
                                                    action="{{ route('categories.destroy', $category) }}" method="POST"
                                                    class="d-none">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $categories->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:folder-open-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Categories Found</h5>
                        <p class="text-muted mb-0">
                            {{ request('search') ? 'Try adjusting your search or filters.' : 'Start by creating your first category.' }}
                        </p>
                        @can('create', App\Models\Category::class)
                            <a href="{{ route('categories.create') }}" class="btn btn-primary mt-3">
                                <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                                Add Category
                            </a>
                        @endcan
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            function confirmDelete(uuid) {
                if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                    document.getElementById('delete-form-' + uuid).submit();
                }
            }
        </script>
    @endpush
@endsection
