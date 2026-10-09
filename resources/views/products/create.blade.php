@extends('layouts.app')

@section('title', 'Create Product')

@section('content')
    @php
        // Purchase cost exposes supplier terms and margin, so the whole cost
        // column of this form is gated. Grid widths follow so the row still
        // fills when the cost inputs are absent.
        $canSetCost = auth()->user()?->can('setCost', \App\Models\Product::class) ?? false;
        $priceCols = $canSetCost ? 'col-md-4' : 'col-md-6';
        $variationPriceCols = $canSetCost ? 'col-md-3' : 'col-md-4';
    @endphp

    <div>

        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Product Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sku" class="form-label">SKU</label>
                                        <input type="text" class="form-control @error('sku') is-invalid @enderror"
                                            id="sku" name="sku" value="{{ old('sku') }}"
                                            placeholder="Auto-generated if empty">
                                        @error('sku')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="barcode" class="form-label">Barcode</label>
                                        <input type="text" class="form-control @error('barcode') is-invalid @enderror"
                                            id="barcode" name="barcode" value="{{ old('barcode') }}">
                                        @error('barcode')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="4">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="shop_id" class="form-label">Owning Shop <span class="text-danger">*</span></label>
                                        <select class="form-select @error('shop_id') is-invalid @enderror"
                                            id="shop_id" name="shop_id" required>
                                            <option value="">Select Shop</option>
                                            @foreach ($shops as $shop)
                                                <option value="{{ $shop->id }}"
                                                    {{ old('shop_id') == $shop->id ? 'selected' : '' }}>
                                                    {{ $shop->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Sales of this product are reported under this shop.</small>
                                        @error('shop_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select @error('category_id') is-invalid @enderror"
                                            id="category_id" name="category_id">
                                            <option value="">Select Category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}"
                                                    {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('category_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="supplier_id" class="form-label">Supplier</label>
                                        <select class="form-select @error('supplier_id') is-invalid @enderror"
                                            id="supplier_id" name="supplier_id">
                                            <option value="">Select Supplier</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                                    {{ $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('supplier_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Pricing & Stock</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @if ($canSetCost)
                                    <div class="{{ $priceCols }}">
                                        <div class="mb-3">
                                            <label for="cost_price" class="form-label">Cost Price</label>
                                            <input type="number" step="0.01"
                                                class="form-control @error('cost_price') is-invalid @enderror"
                                                id="cost_price" name="cost_price" value="{{ old('cost_price') }}">
                                            @error('cost_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endif
                                <div class="{{ $priceCols }}">
                                    <div class="mb-3">
                                        <label for="selling_price" class="form-label">Selling Price <span
                                                class="text-danger">*</span></label>
                                        <input type="number" step="0.01"
                                            class="form-control @error('selling_price') is-invalid @enderror"
                                            id="selling_price" name="selling_price" value="{{ old('selling_price') }}"
                                            required>
                                        @error('selling_price')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="{{ $priceCols }}">
                                    <div class="mb-3">
                                        <label for="wholesale_price" class="form-label">Wholesale Price</label>
                                        <input type="number" step="0.01"
                                            class="form-control @error('wholesale_price') is-invalid @enderror"
                                            id="wholesale_price" name="wholesale_price"
                                            value="{{ old('wholesale_price') }}">
                                        @error('wholesale_price')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tax_class" class="form-label">{{ __('Tax Class') }}</label>
                                        <select class="form-select @error('tax_class') is-invalid @enderror"
                                            id="tax_class" name="tax_class">
                                            {{-- Blank inherits the shop's default, so a rate
                                                 change does not have to be applied product by product. --}}
                                            <option value="">{{ __('Use shop default') }}</option>
                                            @foreach (\App\Enums\TaxClass::options() as $value => $label)
                                                <option value="{{ $value }}" @selected(old('tax_class') === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('tax_class')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">
                                            {{ __('Zero-rated and exempt both charge no VAT but are reported separately.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                        <input type="number"
                                            class="form-control @error('stock_quantity') is-invalid @enderror"
                                            id="stock_quantity" name="stock_quantity"
                                            value="{{ old('stock_quantity', 0) }}">
                                        @error('stock_quantity')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                        <input type="number"
                                            class="form-control @error('low_stock_threshold') is-invalid @enderror"
                                            id="low_stock_threshold" name="low_stock_threshold"
                                            value="{{ old('low_stock_threshold', 10) }}">
                                        @error('low_stock_threshold')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Product Variations --}}
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">Product Variations</h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="has_variations"
                                    name="has_variations" value="1" {{ old('has_variations') ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_variations">Has Variations</label>
                            </div>
                        </div>
                        <div class="card-body" id="variationsContainer"
                            style="{{ old('has_variations') ? '' : 'display: none;' }}">
                            <p class="text-muted small mb-3">
                                <iconify-icon icon="solar:info-circle-bold-duotone" class="me-1"></iconify-icon>
                                Add variations like size, color, etc. Each variation can have its own SKU, price, and stock.
                            </p>

                            <div id="variationsList">
                                @if (old('variations'))
                                    @foreach (old('variations') as $index => $variation)
                                        <div class="variation-row border rounded p-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong>Variation #{{ $index + 1 }}</strong>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger remove-variation">
                                                    <iconify-icon
                                                        icon="solar:trash-bin-minimalistic-2-broken"></iconify-icon>
                                                </button>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label class="form-label">Name <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        name="variations[{{ $index }}][name]"
                                                        value="{{ $variation['name'] ?? '' }}"
                                                        placeholder="e.g. Large / Red">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">SKU <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        name="variations[{{ $index }}][sku]"
                                                        value="{{ $variation['sku'] ?? '' }}" placeholder="SKU-VAR-001">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Barcode</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        name="variations[{{ $index }}][barcode]"
                                                        value="{{ $variation['barcode'] ?? '' }}">
                                                </div>
                                                @if ($canSetCost)
                                                    <div class="{{ $variationPriceCols }}">
                                                        <label class="form-label">Cost Price</label>
                                                        <input type="number" step="0.01"
                                                            class="form-control form-control-sm"
                                                            name="variations[{{ $index }}][cost_price]"
                                                            value="{{ $variation['cost_price'] ?? '' }}">
                                                    </div>
                                                @endif
                                                <div class="{{ $variationPriceCols }}">
                                                    <label class="form-label">Selling Price <span
                                                            class="text-danger">*</span></label>
                                                    <input type="number" step="0.01"
                                                        class="form-control form-control-sm"
                                                        name="variations[{{ $index }}][selling_price]"
                                                        value="{{ $variation['selling_price'] ?? '' }}">
                                                </div>
                                                <div class="{{ $variationPriceCols }}">
                                                    <label class="form-label">Wholesale Price</label>
                                                    <input type="number" step="0.01"
                                                        class="form-control form-control-sm"
                                                        name="variations[{{ $index }}][wholesale_price]"
                                                        value="{{ $variation['wholesale_price'] ?? '' }}">
                                                </div>
                                                <div class="{{ $variationPriceCols }}">
                                                    <label class="form-label">Stock Qty</label>
                                                    <input type="number" class="form-control form-control-sm"
                                                        name="variations[{{ $index }}][stock_quantity]"
                                                        value="{{ $variation['stock_quantity'] ?? 0 }}">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary" id="addVariation">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="me-1"></iconify-icon>
                                Add Variation
                            </button>

                            @error('variations')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                            @error('variations.*')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Product Image</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <input type="file" class="form-control @error('image') is-invalid @enderror"
                                    id="image" name="image" accept="image/*">
                                @error('image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div id="imagePreview" class="d-none">
                                <img src="" alt="Preview" class="img-fluid rounded">
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Shops</h5>
                        </div>
                        <div class="card-body">
                            @php
                                $selectedShopIds = collect(old('shop_ids', []))
                                    ->map(fn($shopId) => (string) $shopId)
                                    ->all();
                            @endphp
                            <input type="hidden" name="sync_shops" value="1">
                            <div class="shop-search-wrapper position-relative">
                                <input type="text"
                                    class="form-control shop-search @error('shop_ids') is-invalid @enderror @error('shop_ids.*') is-invalid @enderror"
                                    id="shop_search" placeholder="Search shops..." autocomplete="off">
                                <select name="shop_ids[]" id="shop_ids" class="form-select d-none" multiple>
                                    @foreach ($shops as $shop)
                                        <option value="{{ $shop->id }}"
                                            {{ in_array((string) $shop->id, $selectedShopIds, true) ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="shop-results position-absolute bg-white border rounded shadow-sm d-none">
                                </div>
                            </div>
                            <div id="selected-shops" class="d-flex flex-wrap gap-2 mt-2"></div>
                            @error('shop_ids')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                            @error('shop_ids.*')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="status" value="inactive">
                                <input class="form-check-input" type="checkbox" role="switch" id="status"
                                    name="status" value="active"
                                    {{ old('status', 'active') === 'active' ? 'checked' : '' }}>
                                <label class="form-check-label" for="status">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                                Create Product
                            </button>
                            <a href="{{ route('products.index') }}" class="btn btn-light w-100">
                                <iconify-icon icon="solar:close-circle-bold-duotone" class="me-1"></iconify-icon>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('styles')
        <style>
            .shop-search-wrapper {
                position: relative;
            }

            .shop-results {
                left: 0;
                margin-top: 2px;
                max-height: 240px;
                overflow-y: auto;
                top: 100%;
                width: 100%;
                z-index: 1000;
            }

            .shop-result-item:hover,
            .shop-result-item:focus {
                background-color: var(--bs-light);
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            // Image preview
            document.getElementById('image').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('imagePreview');
                        preview.querySelector('img').src = e.target.result;
                        preview.classList.remove('d-none');
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Variations toggle
            const hasVariationsCheckbox = document.getElementById('has_variations');
            const variationsContainer = document.getElementById('variationsContainer');

            hasVariationsCheckbox.addEventListener('change', function() {
                variationsContainer.style.display = this.checked ? '' : 'none';
            });

            // Add variation row
            let variationIndex = document.querySelectorAll('.variation-row').length;

            document.getElementById('addVariation').addEventListener('click', function() {
                const html = `
                    <div class="variation-row border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Variation #${variationIndex + 1}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-variation">
                                <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"></iconify-icon>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm"
                                    name="variations[${variationIndex}][name]" placeholder="e.g. Large / Red">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm"
                                    name="variations[${variationIndex}][sku]" placeholder="SKU-VAR-001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Barcode</label>
                                <input type="text" class="form-control form-control-sm"
                                    name="variations[${variationIndex}][barcode]">
                            </div>
                            @if ($canSetCost)
                                <div class="{{ $variationPriceCols }}">
                                    <label class="form-label">Cost Price</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm"
                                        name="variations[${variationIndex}][cost_price]">
                                </div>
                            @endif
                            <div class="{{ $variationPriceCols }}">
                                <label class="form-label">Selling Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm"
                                    name="variations[${variationIndex}][selling_price]">
                            </div>
                            <div class="{{ $variationPriceCols }}">
                                <label class="form-label">Wholesale Price</label>
                                <input type="number" step="0.01" class="form-control form-control-sm"
                                    name="variations[${variationIndex}][wholesale_price]">
                            </div>
                            <div class="{{ $variationPriceCols }}">
                                <label class="form-label">Stock Qty</label>
                                <input type="number" class="form-control form-control-sm"
                                    name="variations[${variationIndex}][stock_quantity]" value="0">
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('variationsList').insertAdjacentHTML('beforeend', html);
                variationIndex++;
            });

            // Remove variation row
            document.getElementById('variationsList').addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-variation');
                if (removeBtn) {
                    removeBtn.closest('.variation-row').remove();
                }
            });

            const shopSearchInput = document.getElementById('shop_search');
            const shopSelect = document.getElementById('shop_ids');
            const shopResults = document.querySelector('.shop-results');
            const selectedShops = document.getElementById('selected-shops');

            function getShopOptions() {
                return Array.from(shopSelect.options);
            }

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value;

                return element.innerHTML;
            }

            function renderSelectedShops() {
                const selectedOptions = getShopOptions().filter(option => option.selected);

                selectedShops.innerHTML = selectedOptions.map(option => `
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1 py-2">
                        ${escapeHtml(option.textContent.trim())}
                        <button type="button" class="btn-close btn-close-sm ms-1 remove-shop" aria-label="Remove shop" data-value="${option.value}"></button>
                    </span>
                `).join('');
            }

            function renderShopResults() {
                const searchTerm = shopSearchInput.value.trim().toLowerCase();
                const matches = getShopOptions().filter(option => {
                    return !option.selected && option.textContent.toLowerCase().includes(searchTerm);
                });

                if (matches.length === 0) {
                    shopResults.innerHTML = '<div class="p-2 text-muted">No shops found</div>';
                    shopResults.classList.remove('d-none');
                    return;
                }

                shopResults.innerHTML = matches.map(option => `
                    <button type="button" class="dropdown-item shop-result-item py-2" data-value="${option.value}">
                        ${escapeHtml(option.textContent.trim())}
                    </button>
                `).join('');
                shopResults.classList.remove('d-none');
            }

            shopSearchInput.addEventListener('input', renderShopResults);
            shopSearchInput.addEventListener('focus', renderShopResults);

            shopResults.addEventListener('click', function(e) {
                const item = e.target.closest('.shop-result-item');
                if (!item) {
                    return;
                }

                const option = getShopOptions().find(shopOption => shopOption.value === item.dataset.value);
                if (option) {
                    option.selected = true;
                    shopSearchInput.value = '';
                    renderSelectedShops();
                    renderShopResults();
                }
            });

            selectedShops.addEventListener('click', function(e) {
                const removeButton = e.target.closest('.remove-shop');
                if (!removeButton) {
                    return;
                }

                const option = getShopOptions().find(shopOption => shopOption.value === removeButton.dataset.value);
                if (option) {
                    option.selected = false;
                    renderSelectedShops();
                    renderShopResults();
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.shop-search-wrapper')) {
                    shopResults.classList.add('d-none');
                }
            });

            renderSelectedShops();
        </script>
    @endpush
@endsection
