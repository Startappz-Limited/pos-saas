@extends('layouts.app')

@section('title', 'Create Purchase Order')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
                        <li class="breadcrumb-item active">Create New</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Create Purchase Order</h4>
            </div>
        </div>

        <form action="{{ route('purchase-orders.store') }}" method="POST" id="purchase-order-form">
            @csrf
            <div class="row">
                <div class="col-xl-8">
                    <!-- Basic Info Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                                    <select name="supplier_id" id="supplier_id"
                                        class="form-select @error('supplier_id') is-invalid @enderror" required>
                                        <option value="">— Select Supplier —</option>
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
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Shop <span class="text-danger">*</span></label>
                                    <select name="shop_id" id="shop_id"
                                        class="form-select @error('shop_id') is-invalid @enderror" required>
                                        <option value="">— Select Shop —</option>
                                        @foreach ($shops as $shop)
                                            <option value="{{ $shop->id }}"
                                                {{ old('shop_id') == $shop->id ? 'selected' : '' }}>
                                                {{ $shop->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('shop_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Order Date</label>
                                    <input type="date" name="order_date"
                                        class="form-control @error('order_date') is-invalid @enderror"
                                        value="{{ old('order_date', date('Y-m-d')) }}">
                                    @error('order_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Expected Delivery</label>
                                    <input type="date" name="expected_delivery_date"
                                        class="form-control @error('expected_delivery_date') is-invalid @enderror"
                                        value="{{ old('expected_delivery_date') }}">
                                    @error('expected_delivery_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Payment Terms</label>
                                    <select name="payment_terms"
                                        class="form-select @error('payment_terms') is-invalid @enderror">
                                        <option value="">— Select —</option>
                                        <option value="NET_7" {{ old('payment_terms') === 'NET_7' ? 'selected' : '' }}>
                                            Net 7</option>
                                        <option value="NET_15" {{ old('payment_terms') === 'NET_15' ? 'selected' : '' }}>
                                            Net 15</option>
                                        <option value="NET_30" {{ old('payment_terms') === 'NET_30' ? 'selected' : '' }}>
                                            Net 30</option>
                                        <option value="NET_60" {{ old('payment_terms') === 'NET_60' ? 'selected' : '' }}>
                                            Net 60</option>
                                        <option value="COD" {{ old('payment_terms') === 'COD' ? 'selected' : '' }}>Cash
                                            on Delivery</option>
                                        <option value="PREPAID" {{ old('payment_terms') === 'PREPAID' ? 'selected' : '' }}>
                                            Prepaid</option>
                                    </select>
                                    @error('payment_terms')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                    placeholder="Any notes for this order...">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Order Items Card -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Order Items</h5>
                            <button type="button" class="btn btn-soft-primary btn-sm" id="add-item-btn">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Add Item
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-hover mb-0" id="items-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 200px;">Product</th>
                                            <th style="min-width: 150px;">Variation</th>
                                            <th style="width: 100px;">Qty</th>
                                            <th style="width: 80px;">Unit</th>
                                            <th class="unit-cost-column">Unit Cost</th>
                                            <th style="width: 120px;">Line Total</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-body">
                                        <!-- Items will be added here -->
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="5" class="text-end fw-medium">Subtotal:</td>
                                            <td colspan="2">
                                                <span id="subtotal">{{ currency_symbol() }}0.00</span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Costs Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Additional Costs</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Shipping Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ currency_symbol() }}</span>
                                        <input type="number" name="shipping_cost"
                                            class="form-control @error('shipping_cost') is-invalid @enderror"
                                            value="{{ old('shipping_cost', 0) }}" min="0" step="0.01"
                                            id="shipping-cost">
                                    </div>
                                    @error('shipping_cost')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tax Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ currency_symbol() }}</span>
                                        <input type="number" name="tax_amount"
                                            class="form-control @error('tax_amount') is-invalid @enderror"
                                            value="{{ old('tax_amount', 0) }}" min="0" step="0.01"
                                            id="tax-amount">
                                    </div>
                                    @error('tax_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Discount Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ currency_symbol() }}</span>
                                        <input type="number" name="discount_amount"
                                            class="form-control @error('discount_amount') is-invalid @enderror"
                                            value="{{ old('discount_amount', 0) }}" min="0" step="0.01"
                                            id="discount-amount">
                                    </div>
                                    @error('discount_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-xl-4">
                    <!-- Order Summary Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span id="summary-subtotal">{{ currency_symbol() }}0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Shipping:</span>
                                <span id="summary-shipping">{{ currency_symbol() }}0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Tax:</span>
                                <span id="summary-tax">{{ currency_symbol() }}0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Discount:</span>
                                <span id="summary-discount" class="text-danger">-{{ currency_symbol() }}0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total:</span>
                                <span class="fw-bold text-primary fs-18"
                                    id="summary-total">{{ currency_symbol() }}0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" name="status" value="draft" class="btn btn-secondary">
                                    <iconify-icon icon="solar:diskette-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Save as Draft
                                </button>
                                <button type="submit" name="status" value="pending" class="btn btn-primary">
                                    <iconify-icon icon="solar:send-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Save & Submit for Approval
                                </button>
                                @if (auth()->user()?->can('purchase_orders.full-access') ||
                                        (auth()->user()?->can('purchase_orders.approve') && auth()->user()?->can('purchase_orders.update')))
                                    <button type="submit" name="workflow" value="approve_and_mark_ordered"
                                        class="btn btn-success">
                                        <iconify-icon icon="solar:cart-check-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Save, Approve & Mark Ordered
                                    </button>
                                @endif
                                <a href="{{ route('purchase-orders.index') }}" class="btn btn-light">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Item Row Template -->
    <template id="item-row-template">
        <tr class="item-row">
            <td>
                <div class="product-search-wrapper position-relative">
                    <input type="text" class="form-control form-control-sm product-search"
                        placeholder="Search product..." autocomplete="off">
                    <select name="items[INDEX][product_id]" class="form-select form-select-sm product-select d-none"
                        required>
                        <option value="">— Select —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                data-has-variations="{{ $product->has_variations ? '1' : '0' }}"
                                data-shop-ids="{{ $product->shops->pluck('id')->implode(',') }}"
                                data-name="{{ $product->name }}">
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                    <div class="product-results position-absolute bg-white border rounded shadow-sm d-none"></div>
                </div>
            </td>
            <td>
                <select name="items[INDEX][product_variation_id]" class="form-select form-select-sm variation-select"
                    disabled>
                    <option value="">— None —</option>
                </select>
            </td>
            <td>
                <input type="number" name="items[INDEX][quantity_ordered]"
                    class="form-control form-control-sm qty-input" value="1" min="1" step="1"
                    required>
            </td>
            <td>
                <input type="text" name="items[INDEX][unit]" class="form-control form-control-sm" value="pcs"
                    placeholder="pcs">
            </td>
            <td class="unit-cost-column">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                    <input type="number" name="items[INDEX][unit_cost]" class="form-control cost-input" value="0"
                        min="0" step="0.01" required>
                </div>
            </td>
            <td>
                <span class="line-total fw-medium">{{ currency_symbol() }}0.00</span>
            </td>
            <td>
                <button type="button" class="btn btn-soft-danger btn-sm remove-item-btn">
                    <iconify-icon icon="solar:trash-bin-trash-bold-duotone"></iconify-icon>
                </button>
            </td>
        </tr>
    </template>

    @push('styles')
        <style>
            .product-search-wrapper {
                position: relative;
            }

            .product-results {
                left: 0;
                margin-top: 2px;
                max-height: 260px;
                overflow-y: auto;
                top: 100%;
                width: 100%;
                z-index: 1000;
            }

            .product-result-item:hover,
            .product-result-item:focus {
                background-color: var(--bs-light);
            }

            #items-table .unit-cost-column {
                min-width: 180px;
                width: 180px;
            }

            #items-table .unit-cost-column .input-group {
                flex-wrap: nowrap;
                min-width: 165px;
            }

            #items-table .unit-cost-column .input-group-text {
                flex: 0 0 auto;
            }

            #items-table .unit-cost-column .cost-input {
                min-width: 90px;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const itemsBody = document.getElementById('items-body');
                const addItemBtn = document.getElementById('add-item-btn');
                const itemTemplate = document.getElementById('item-row-template');
                const shopSelect = document.getElementById('shop_id');
                let itemIndex = 0;

                // Add initial item row
                addItemRow();

                // Add item button click
                addItemBtn.addEventListener('click', addItemRow);

                function addItemRow() {
                    const template = itemTemplate.content.cloneNode(true);
                    const row = template.querySelector('tr');

                    // Update index in names
                    row.innerHTML = row.innerHTML.replace(/INDEX/g, itemIndex);

                    itemsBody.appendChild(row);

                    setupRowListeners(row);
                    refreshProductSelect(row.querySelector('.product-select'));

                    itemIndex++;
                }

                function optionMatchesShop(option, selectedShopId, selectedProductId) {
                    if (!option.value || !selectedShopId || option.value === selectedProductId) {
                        return true;
                    }

                    return option.dataset.shopIds.split(',').includes(selectedShopId);
                }

                function filterProductSelect(productSelect, clearInvalid = false) {
                    const selectedShopId = shopSelect.value;
                    const selectedProductId = productSelect.value;
                    let selectedProductIsAllowed = true;

                    Array.from(productSelect.options).forEach(option => {
                        const isAllowed = optionMatchesShop(option, selectedShopId, clearInvalid ? '' :
                            selectedProductId);
                        option.hidden = !isAllowed;
                        option.disabled = !isAllowed;

                        if (option.value === selectedProductId && !isAllowed) {
                            selectedProductIsAllowed = false;
                        }
                    });

                    if (clearInvalid && !selectedProductIsAllowed) {
                        productSelect.value = '';
                        productSelect.dispatchEvent(new Event('change'));
                    }
                }

                function refreshProductSelect(productSelect, clearInvalid = false) {
                    filterProductSelect(productSelect, clearInvalid);
                    syncProductSearch(productSelect);
                }

                function refreshProductFilters(clearInvalid = false) {
                    document.querySelectorAll('.product-select').forEach(productSelect => {
                        refreshProductSelect(productSelect, clearInvalid);
                    });
                }

                shopSelect.addEventListener('change', () => refreshProductFilters(true));

                function setupRowListeners(row) {
                    const productSelect = row.querySelector('.product-select');
                    const productSearch = row.querySelector('.product-search');
                    const productResults = row.querySelector('.product-results');
                    const variationSelect = row.querySelector('.variation-select');
                    const qtyInput = row.querySelector('.qty-input');
                    const costInput = row.querySelector('.cost-input');
                    const removeBtn = row.querySelector('.remove-item-btn');

                    productSearch.addEventListener('input', function() {
                        renderProductResults(row);
                    });

                    productSearch.addEventListener('focus', function() {
                        renderProductResults(row);
                    });

                    productResults.addEventListener('click', function(e) {
                        const item = e.target.closest('.product-result-item');
                        if (!item) {
                            return;
                        }

                        productSelect.value = item.dataset.value;
                        productSelect.dispatchEvent(new Event('change'));
                        productResults.classList.add('d-none');
                        syncProductSearch(productSelect);
                    });

                    // Product change - load variations
                    productSelect.addEventListener('change', async function() {
                        const productId = this.value;
                        const selectedOption = this.querySelector(`option[value="${productId}"]`);
                        const hasVariations = selectedOption?.dataset?.hasVariations === '1';

                        if (hasVariations && productId) {
                            variationSelect.disabled = false;
                            try {
                                const response = await fetch(`/api/products/${productId}/variations`);
                                const variations = await response.json();
                                variationSelect.innerHTML = '<option value="">— Select —</option>';
                                variations.forEach(v => {
                                    variationSelect.innerHTML +=
                                        `<option value="${v.id}">${v.name}</option>`;
                                });
                            } catch (e) {
                                console.error('Failed to load variations:', e);
                            }
                        } else {
                            variationSelect.disabled = true;
                            variationSelect.innerHTML = '<option value="">— None —</option>';
                        }
                    });

                    // Quantity/Cost change - update line total
                    qtyInput.addEventListener('input', () => updateLineTotal(row));
                    costInput.addEventListener('input', () => updateLineTotal(row));

                    // Remove row
                    removeBtn.addEventListener('click', function() {
                        if (itemsBody.querySelectorAll('.item-row').length > 1) {
                            row.remove();
                            updateTotals();
                        } else {
                            alert('At least one item is required.');
                        }
                    });
                }

                function renderProductResults(row) {
                    const productSearch = row.querySelector('.product-search');
                    const productSelect = row.querySelector('.product-select');
                    const productResults = row.querySelector('.product-results');
                    const searchTerm = productSearch.value.trim().toLowerCase();
                    const matches = Array.from(productSelect.options).filter(option => {
                        return option.value && !option.disabled && option.textContent.toLowerCase().includes(
                            searchTerm);
                    });

                    if (matches.length === 0) {
                        productResults.innerHTML = '<div class="p-2 text-muted">No products found for this shop</div>';
                        productResults.classList.remove('d-none');
                        return;
                    }

                    productResults.innerHTML = matches.map(option => `
                        <button type="button" class="dropdown-item product-result-item py-2" data-value="${option.value}">
                            ${escapeHtml(option.textContent.trim())}
                        </button>
                    `).join('');
                    productResults.classList.remove('d-none');
                }

                function escapeHtml(value) {
                    const element = document.createElement('div');
                    element.textContent = value;

                    return element.innerHTML;
                }

                function syncProductSearch(productSelect) {
                    const row = productSelect.closest('.item-row');
                    const productSearch = row.querySelector('.product-search');
                    const selectedOption = productSelect.options[productSelect.selectedIndex];

                    productSearch.value = selectedOption && selectedOption.value ? selectedOption.textContent.trim() :
                        '';
                }

                function updateLineTotal(row) {
                    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
                    const total = qty * cost;
                    row.querySelector('.line-total').textContent = '{{ currency_symbol() }}' + total.toFixed(2);
                    updateTotals();
                }

                function updateTotals() {
                    let subtotal = 0;
                    document.querySelectorAll('.item-row').forEach(row => {
                        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
                        subtotal += qty * cost;
                    });

                    const shipping = parseFloat(document.getElementById('shipping-cost').value) || 0;
                    const tax = parseFloat(document.getElementById('tax-amount').value) || 0;
                    const discount = parseFloat(document.getElementById('discount-amount').value) || 0;
                    const total = subtotal + shipping + tax - discount;

                    document.getElementById('subtotal').textContent = '{{ currency_symbol() }}' + subtotal.toFixed(2);
                    document.getElementById('summary-subtotal').textContent = '{{ currency_symbol() }}' + subtotal
                        .toFixed(2);
                    document.getElementById('summary-shipping').textContent = '{{ currency_symbol() }}' + shipping
                        .toFixed(2);
                    document.getElementById('summary-tax').textContent = '{{ currency_symbol() }}' + tax.toFixed(2);
                    document.getElementById('summary-discount').textContent = '-{{ currency_symbol() }}' + discount
                        .toFixed(2);
                    document.getElementById('summary-total').textContent = '{{ currency_symbol() }}' + total.toFixed(
                    2);
                }

                // Additional cost changes
                ['shipping-cost', 'tax-amount', 'discount-amount'].forEach(id => {
                    document.getElementById(id).addEventListener('input', updateTotals);
                });

                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.product-search-wrapper')) {
                        document.querySelectorAll('.product-results').forEach(result => result.classList.add(
                            'd-none'));
                    }
                });
            });
        </script>
    @endpush
@endsection
