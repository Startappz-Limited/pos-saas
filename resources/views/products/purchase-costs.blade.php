@extends('layouts.app')

@section('title', __('Purchase Costs'))

@section('content')
    <div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md bg-danger-subtle rounded d-flex align-items-center justify-content-center">
                                <iconify-icon icon="solar:tag-price-bold-duotone" class="fs-2 text-danger"></iconify-icon>
                            </div>
                            <div>
                                <p class="text-muted mb-1">{{ __('Missing Purchase Cost') }}</p>
                                <h4 class="mb-0">{{ number_format($statistics['missing']) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md bg-warning-subtle rounded d-flex align-items-center justify-content-center">
                                <iconify-icon icon="solar:danger-triangle-bold-duotone"
                                    class="fs-2 text-warning"></iconify-icon>
                            </div>
                            <div>
                                <p class="text-muted mb-1">{{ __('Cost Above Selling Price') }}</p>
                                <h4 class="mb-0">{{ number_format($statistics['overpriced']) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md bg-success-subtle rounded d-flex align-items-center justify-content-center">
                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                    class="fs-2 text-success"></iconify-icon>
                            </div>
                            <div>
                                <p class="text-muted mb-1">{{ __('Costed Products') }}</p>
                                <h4 class="mb-0">{{ number_format($statistics['priced']) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h4 class="card-title mb-1">
                                <iconify-icon icon="solar:wallet-money-bold-duotone"
                                    class="text-primary me-2"></iconify-icon>
                                {{ __('Purchase Costs') }}
                            </h4>
                            <p class="text-muted mb-0 fs-13">
                                {{ __('Products imported from an online store arrive without a supplier cost. Enter what you actually paid per unit so profit reports are correct.') }}
                            </p>
                        </div>
                        <a href="{{ route('products.index') }}" class="btn btn-light btn-sm">
                            <iconify-icon icon="solar:arrow-left-broken" class="me-1"></iconify-icon>
                            {{ __('Back to All Products') }}
                        </a>
                    </div>

                    <div class="card-body">
                        <form method="GET" action="{{ route('products.purchase-costs.index') }}" class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">{{ __('Search') }}</label>
                                <input type="search" class="form-control" id="search" name="search"
                                    value="{{ request('search') }}" placeholder="{{ __('Name, SKU or barcode') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="filter" class="form-label">{{ __('Show') }}</label>
                                <select class="form-select" id="filter" name="filter">
                                    <option value="all" @selected($filter === 'all')>{{ __('Needs attention') }}</option>
                                    <option value="missing" @selected($filter === 'missing')>{{ __('Missing cost only') }}
                                    </option>
                                    <option value="overpriced" @selected($filter === 'overpriced')>
                                        {{ __('Cost above selling price') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="category_id" class="form-label">{{ __('Category') }}</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">{{ __('All categories') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                                            {{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <iconify-icon icon="solar:magnifer-broken" class="me-1"></iconify-icon>
                                    {{ __('Filter') }}
                                </button>
                            </div>
                        </form>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('products.purchase-costs.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="table-responsive">
                                <table class="table align-middle mb-0 table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('Product') }}</th>
                                            <th>{{ __('SKU') }}</th>
                                            <th class="text-end">{{ __('Selling Price') }}</th>
                                            <th class="text-end">{{ __('Current Cost') }}</th>
                                            <th class="text-end">{{ __('Last Purchased At') }}</th>
                                            <th style="width: 180px;">{{ __('Purchase Cost') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($products as $product)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('products.show', $product) }}"
                                                        class="text-dark fw-medium">{{ $product->name }}</a>
                                                    @if ($product->category)
                                                        <div>
                                                            <span
                                                                class="badge bg-info-subtle text-info">{{ $product->category->name }}</span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td><code>{{ $product->sku }}</code></td>
                                                <td class="text-end">{{ number_format((float) $product->selling_price, 2) }}
                                                </td>
                                                <td class="text-end">
                                                    @if (! $product->hasPurchaseCost())
                                                        <span
                                                            class="badge bg-danger-subtle text-danger">{{ __('Not set') }}</span>
                                                    @elseif ($product->hasCostAboveSellingPrice())
                                                        <span class="badge bg-warning-subtle text-warning"
                                                            title="{{ __('This cost is higher than the selling price, so every sale reports a loss.') }}">
                                                            {{ number_format((float) $product->cost_price, 2) }}
                                                        </span>
                                                    @else
                                                        {{ number_format((float) $product->cost_price, 2) }}
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if ($suggestions->has($product->id))
                                                        <span class="text-muted"
                                                            title="{{ __('Most recent purchase order unit cost') }}">
                                                            {{ number_format($suggestions[$product->id], 2) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($product->has_variations && $product->variations->isNotEmpty())
                                                        <span
                                                            class="text-muted fs-13">{{ __('Set per variation below') }}</span>
                                                    @else
                                                        <input type="number" step="0.01" min="0"
                                                            class="form-control form-control-sm"
                                                            name="costs[product][{{ $product->id }}]"
                                                            value="{{ old("costs.product.{$product->id}") }}"
                                                            placeholder="{{ $suggestions->has($product->id) ? number_format($suggestions[$product->id], 2, '.', '') : '0.00' }}"
                                                            aria-label="{{ __('Purchase cost for :product', ['product' => $product->name]) }}">
                                                    @endif
                                                </td>
                                            </tr>

                                            @foreach ($product->variations as $variation)
                                                <tr class="table-light">
                                                    <td class="ps-4">
                                                        <iconify-icon icon="solar:arrow-right-down-broken"
                                                            class="text-muted me-1"></iconify-icon>
                                                        {{ $variation->name }}
                                                    </td>
                                                    <td><code>{{ $variation->sku }}</code></td>
                                                    <td class="text-end">
                                                        {{ number_format((float) $variation->selling_price, 2) }}</td>
                                                    <td class="text-end">
                                                        @if (! $variation->hasPurchaseCost())
                                                            <span
                                                                class="badge bg-danger-subtle text-danger">{{ __('Not set') }}</span>
                                                        @else
                                                            {{ number_format((float) $variation->cost_price, 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="text-end"><span class="text-muted">&mdash;</span></td>
                                                    <td>
                                                        <input type="number" step="0.01" min="0"
                                                            class="form-control form-control-sm"
                                                            name="costs[variation][{{ $variation->id }}]"
                                                            value="{{ old("costs.variation.{$variation->id}") }}"
                                                            placeholder="0.00"
                                                            aria-label="{{ __('Purchase cost for :variation', ['variation' => $variation->name]) }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                                        class="fs-1 text-success mb-2"></iconify-icon>
                                                    <p class="text-muted mb-0">
                                                        {{ __('Every product has a sensible purchase cost.') }}</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($products->isNotEmpty())
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                                    <p class="text-muted mb-0 fs-13">
                                        {{ __('Blank fields are left unchanged. Saved costs apply to future sales; use the profit recalculation to correct past ones.') }}
                                    </p>
                                    <button type="submit" class="btn btn-primary">
                                        <iconify-icon icon="solar:diskette-bold-duotone" class="me-1"></iconify-icon>
                                        {{ __('Save Purchase Costs') }}
                                    </button>
                                </div>
                            @endif
                        </form>

                        @if ($products->hasPages())
                            <div class="mt-4">
                                {{ $products->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
