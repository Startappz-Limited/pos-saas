@extends('layouts.app')

@section('title', 'Attributes')

@section('content')
    <div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                    <iconify-icon icon="solar:widget-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Attributes</p>
                                <h4 class="mb-0">{{ $totalAttributes }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Active</p>
                                <h4 class="mb-0">{{ $activeAttributes }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                                    <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Inactive</p>
                                <h4 class="mb-0">{{ $inactiveAttributes }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                                    <iconify-icon icon="solar:star-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Required</p>
                                <h4 class="mb-0">{{ $requiredAttributes }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attributes List -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Attributes</h4>
                        @can('create', App\Models\Attribute::class)
                            <a href="{{ route('attributes.create') }}" class="btn btn-primary">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Add Attribute
                            </a>
                        @endcan
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('attributes.index') }}" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search attributes..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="dropdown" {{ request('type') === 'dropdown' ? 'selected' : '' }}>Dropdown
                                    </option>
                                    <option value="radio" {{ request('type') === 'radio' ? 'selected' : '' }}>Radio
                                    </option>
                                    <option value="checkbox" {{ request('type') === 'checkbox' ? 'selected' : '' }}>Checkbox
                                    </option>
                                    <option value="color" {{ request('type') === 'color' ? 'selected' : '' }}>Color
                                    </option>
                                    <option value="button" {{ request('type') === 'button' ? 'selected' : '' }}>Button
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>
                                        Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon> Search
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Values</th>
                                        <th>Type</th>
                                        <th>Order</th>
                                        <th>Required</th>
                                        <th>Status</th>
                                        <th>Products</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($attributes as $attribute)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <h6 class="mb-0">{{ $attribute->name }}</h6>
                                                        <small class="text-muted">{{ $attribute->slug }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($attribute->values && count($attribute->values) > 0)
                                                    <span
                                                        class="text-muted">{{ implode(', ', array_slice($attribute->values, 0, 3)) }}</span>
                                                    @if (count($attribute->values) > 3)
                                                        <small
                                                            class="badge bg-secondary-subtle text-secondary">+{{ count($attribute->values) - 3 }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info">
                                                    {{ ucfirst($attribute->type) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-secondary-subtle text-secondary">{{ $attribute->display_order }}</span>
                                            </td>
                                            <td>
                                                @if ($attribute->is_required)
                                                    <span class="badge bg-warning-subtle text-warning">Required</span>
                                                @else
                                                    <span class="badge bg-light text-dark">Optional</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($attribute->is_active)
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary">0</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    @can('view', $attribute)
                                                        <a href="{{ route('attributes.show', $attribute) }}"
                                                            class="btn btn-light btn-sm">
                                                            <iconify-icon icon="solar:eye-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('update', $attribute)
                                                        <a href="{{ route('attributes.edit', $attribute) }}"
                                                            class="btn btn-soft-primary btn-sm">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('delete', $attribute)
                                                        <form action="{{ route('attributes.destroy', $attribute) }}"
                                                            method="POST" class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this attribute?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-soft-danger btn-sm">
                                                                <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                                    class="align-middle fs-18"></iconify-icon>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <iconify-icon icon="solar:widget-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No attributes found.</p>
                                                @can('create', App\Models\Attribute::class)
                                                    <a href="{{ route('attributes.create') }}"
                                                        class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Attribute
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($attributes->hasPages())
                            <div class="mt-4">
                                {{ $attributes->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
