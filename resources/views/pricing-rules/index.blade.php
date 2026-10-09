@extends('layouts.app')

@section('title', 'Pricing Rules')

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
                                    <iconify-icon icon="solar:tag-price-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Rules</p>
                                <h4 class="mb-0">{{ $statistics['total'] ?? 0 }}</h4>
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
                                <h4 class="mb-0">{{ $statistics['active'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:clock-circle-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Upcoming</p>
                                <h4 class="mb-0">{{ $statistics['upcoming'] ?? 0 }}</h4>
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
                                    <iconify-icon icon="solar:calendar-minimalistic-bold-duotone"></iconify-icon>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">Expired</p>
                                <h4 class="mb-0">{{ $statistics['expired'] ?? 0 }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Rules List -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">All Pricing Rules</h4>
                        <div class="d-flex gap-2">
                            <a href="{{ route('pricing-rules.expired') }}" class="btn btn-danger btn-sm">
                                <iconify-icon icon="solar:calendar-minimalistic-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Expired
                            </a>
                            <a href="{{ route('pricing-rules.upcoming') }}" class="btn btn-warning btn-sm">
                                <iconify-icon icon="solar:clock-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Upcoming
                            </a>
                            @can('create', App\Models\PricingRule::class)
                                <a href="{{ route('pricing-rules.create') }}" class="btn btn-primary btn-sm">
                                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Add Rule
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('pricing-rules.index') }}" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search pricing rules..."
                                    value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    @foreach (App\Enums\PricingType::cases() as $type)
                                        <option value="{{ $type->value }}"
                                            {{ request('type') === $type->value ? 'selected' : '' }}>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="is_active" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
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
                                        <th>Rule Name</th>
                                        <th>Type</th>
                                        <th>Product</th>
                                        <th>Pricing</th>
                                        <th>Date Range</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pricingRules as $rule)
                                        <tr>
                                            <td>
                                                <div>
                                                    <a href="{{ route('pricing-rules.show', $rule) }}"
                                                        class="text-dark fw-medium fs-15">
                                                        {{ $rule->name }}
                                                    </a>
                                                    @if ($rule->description)
                                                        <p class="text-muted mb-0 mt-1 fs-13">
                                                            {{ Str::limit($rule->description, 50) }}</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @php
                                                    $typeColors = [
                                                        'regular' => 'secondary',
                                                        'member' => 'info',
                                                        'bulk' => 'primary',
                                                        'promotional' => 'danger',
                                                        'customer_specific' => 'warning',
                                                        'time_based' => 'success',
                                                    ];
                                                    $color = $typeColors[$rule->type->value] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">
                                                    {{ $rule->type->label() }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($rule->product)
                                                    <a href="{{ route('products.show', $rule->product) }}"
                                                        class="text-dark">
                                                        {{ Str::limit($rule->product->name, 30) }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">All Products</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($rule->price)
                                                    <span class="fw-medium text-primary">{{ format_currency($rule->price) }}</span>
                                                @elseif ($rule->discount_percentage)
                                                    <span class="fw-medium text-success">{{ $rule->discount_percentage }}% off</span>
                                                @elseif ($rule->discount_amount)
                                                    <span class="fw-medium text-success">{{ format_currency($rule->discount_amount) }} off</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                                @if ($rule->min_quantity)
                                                    <br><small class="text-muted">Min: {{ $rule->min_quantity }} units</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($rule->start_date || $rule->end_date)
                                                    <small>
                                                        @if ($rule->start_date)
                                                            {{ $rule->start_date->format('M d, Y') }}
                                                        @else
                                                            —
                                                        @endif
                                                        <br>to<br>
                                                        @if ($rule->end_date)
                                                            @if ($rule->end_date->isPast())
                                                                <span class="text-danger">{{ $rule->end_date->format('M d, Y') }}</span>
                                                            @else
                                                                {{ $rule->end_date->format('M d, Y') }}
                                                            @endif
                                                        @else
                                                            No end
                                                        @endif
                                                    </small>
                                                @else
                                                    <span class="text-muted">Always</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $rule->priority }}</span>
                                            </td>
                                            <td>
                                                @if ($rule->is_active)
                                                    @if ($rule->end_date && $rule->end_date->isPast())
                                                        <span class="badge bg-danger-subtle text-danger">Expired</span>
                                                    @elseif ($rule->start_date && $rule->start_date->isFuture())
                                                        <span class="badge bg-warning-subtle text-warning">Upcoming</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">Active</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    @can('view', $rule)
                                                        <a href="{{ route('pricing-rules.show', $rule) }}"
                                                            class="btn btn-light btn-sm">
                                                            <iconify-icon icon="solar:eye-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('update', $rule)
                                                        <a href="{{ route('pricing-rules.edit', $rule) }}"
                                                            class="btn btn-soft-primary btn-sm">
                                                            <iconify-icon icon="solar:pen-2-broken"
                                                                class="align-middle fs-18"></iconify-icon>
                                                        </a>
                                                    @endcan

                                                    @can('delete', $rule)
                                                        <form action="{{ route('pricing-rules.destroy', $rule) }}"
                                                            method="POST" class="d-inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this pricing rule?')">
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
                                                <iconify-icon icon="solar:tag-price-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No pricing rules found.</p>
                                                @can('create', App\Models\PricingRule::class)
                                                    <a href="{{ route('pricing-rules.create') }}" class="btn btn-primary btn-sm">
                                                        <iconify-icon icon="solar:add-circle-bold-duotone"
                                                            class="align-middle me-1"></iconify-icon>
                                                        Create First Pricing Rule
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($pricingRules->hasPages())
                            <div class="mt-4">
                                {{ $pricingRules->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
