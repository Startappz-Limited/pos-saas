@extends('layouts.app')

@section('title', 'Attribute Details')

@section('content')
    <div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Attribute Header -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div
                                    class="avatar-lg bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center">
                                    <iconify-icon icon="solar:widget-bold-duotone" class="fs-1"></iconify-icon>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h3 class="mb-1">{{ $attribute->name }}</h3>
                                <div class="d-flex gap-2 align-items-center">
                                    @if ($attribute->is_active)
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                    @endif

                                    @if ($attribute->is_required)
                                        <span class="badge bg-warning-subtle text-warning">Required</span>
                                    @endif

                                    @if ($attribute->is_visible)
                                        <span class="badge bg-info-subtle text-info">Visible</span>
                                    @endif

                                    <span
                                        class="badge bg-secondary-subtle text-secondary">{{ ucfirst($attribute->type) }}</span>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                @can('update', $attribute)
                                    <a href="{{ route('attributes.edit', $attribute) }}" class="btn btn-primary">
                                        <iconify-icon icon="solar:pen-2-broken" class="align-middle me-1"></iconify-icon>
                                        Edit
                                    </a>
                                @endcan
                            </div>
                        </div>

                        @if ($attribute->description)
                            <div class="alert alert-info mb-0">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                {{ $attribute->description }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Attribute Details -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Attribute Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row" style="width: 200px;">Name:</th>
                                        <td>{{ $attribute->name }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Slug:</th>
                                        <td><code>{{ $attribute->slug }}</code></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Display Type:</th>
                                        <td>
                                            <span class="badge bg-info-subtle text-info">
                                                {{ ucfirst($attribute->type) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Display Order:</th>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary">
                                                {{ $attribute->display_order }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Required:</th>
                                        <td>
                                            @if ($attribute->is_required)
                                                <span class="badge bg-warning-subtle text-warning">Yes</span>
                                            @else
                                                <span class="badge bg-light text-dark">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Visible to Customers:</th>
                                        <td>
                                            @if ($attribute->is_visible)
                                                <span class="badge bg-success-subtle text-success">Yes</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Status:</th>
                                        <td>
                                            @if ($attribute->is_active)
                                                <span class="badge bg-success-subtle text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Attribute Values -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Available Values</h5>
                    </div>
                    <div class="card-body">
                        @if ($attribute->values && count($attribute->values) > 0)
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($attribute->values as $value)
                                    <span class="badge bg-primary-subtle text-primary fs-14">{{ $value }}</span>
                                @endforeach
                            </div>
                            <hr>
                            <small class="text-muted">
                                <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                                Total: {{ count($attribute->values) }} values
                            </small>
                        @else
                            <div class="text-center text-muted py-3">
                                <iconify-icon icon="solar:widget-bold-duotone" class="fs-1 mb-2"></iconify-icon>
                                <p class="mb-0">No values defined</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Products Using This Attribute -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Products Using This Attribute</h5>
                    </div>
                    <div class="card-body">
                        @if (isset($attribute->products_count) && $attribute->products_count > 0)
                            <div class="alert alert-info mb-0">
                                <iconify-icon icon="solar:box-bold-duotone" class="me-2"></iconify-icon>
                                This attribute is being used by <strong>{{ $attribute->products_count }}</strong> products.
                            </div>
                        @else
                            <div class="text-center text-muted py-3">
                                <iconify-icon icon="solar:box-bold-duotone" class="fs-1 mb-2"></iconify-icon>
                                <p class="mb-0">No products are using this attribute yet</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Total Values:</span>
                                <span
                                    class="badge bg-primary-subtle text-primary">{{ $attribute->values ? count($attribute->values) : 0 }}</span>
                            </div>
                        </div>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Products Using:</span>
                                <span
                                    class="badge bg-success-subtle text-success">{{ $attribute->products_count ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Display Order:</span>
                                <span
                                    class="badge bg-secondary-subtle text-secondary">{{ $attribute->display_order }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        @can('update', $attribute)
                            <a href="{{ route('attributes.edit', $attribute) }}" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:pen-2-broken" class="me-1"></iconify-icon>
                                Edit Attribute
                            </a>
                        @endcan

                        @if ($attribute->is_active)
                            @can('update', $attribute)
                                <form action="{{ route('attributes.deactivate', $attribute) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-warning w-100"
                                        onclick="return confirm('Are you sure you want to deactivate this attribute?')">
                                        <iconify-icon icon="solar:pause-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Deactivate
                                    </button>
                                </form>
                            @endcan
                        @else
                            @can('update', $attribute)
                                <form action="{{ route('attributes.activate', $attribute) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <iconify-icon icon="solar:play-circle-bold-duotone" class="me-1"></iconify-icon>
                                        Activate
                                    </button>
                                </form>
                            @endcan
                        @endif

                        <a href="{{ route('attributes.index') }}" class="btn btn-light w-100">
                            <iconify-icon icon="solar:arrow-left-broken" class="me-1"></iconify-icon>
                            Back to List
                        </a>
                    </div>
                </div>

                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Attribute ID</small>
                            <code class="d-block">{{ $attribute->id }}</code>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Created At</small>
                            <p class="mb-0">{{ $attribute->created_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $attribute->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block mb-1">Last Updated</small>
                            <p class="mb-0">{{ $attribute->updated_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $attribute->updated_at->diffForHumans() }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
