@php
    $selectedSupplierId = old('supplier_id', $defaults['supplier_id'] ?? $purchaseReturn?->supplier_id);
    $selectedShopId = old('shop_id', $defaults['shop_id'] ?? $purchaseReturn?->shop_id);
    $selectedStatus = old('status', $purchaseReturn?->status->value ?? \App\Enums\PurchaseReturnStatus::PENDING->value);
    $selectedReason = old('reason', $purchaseReturn?->reason->value ?? null);
@endphp

<form action="{{ $formAction }}" method="POST" id="purchase-return-form">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <input type="hidden" name="purchase_order_id" id="purchase-order-id"
        value="{{ old('purchase_order_id', $defaults['purchase_order_id'] ?? $purchaseReturn?->purchase_order_id) }}">
    <input type="hidden" name="sale_return_id" id="sale-return-id"
        value="{{ old('sale_return_id', $defaults['sale_return_id'] ?? $purchaseReturn?->sale_return_id) }}">
    <input type="hidden" name="shop_id" id="shop-id" value="{{ $selectedShopId }}">

    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Supplier Return Details</h5>
                </div>
                <div class="card-body">
                    @if ($errors->has('supplier_id') || $errors->has('shop_id'))
                        <div class="alert alert-danger">
                            @error('supplier_id')
                                <div>{{ $message }}</div>
                            @enderror
                            @error('shop_id')
                                <div>{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="supplier-id"
                                class="form-select @error('supplier_id') is-invalid @enderror" required>
                                <option value="">-- Select Supplier --</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}"
                                        {{ (string) $selectedSupplierId === (string) $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                                <option value="">-- Select Reason --</option>
                                @foreach ($reasons as $reason)
                                    <option value="{{ $reason->value }}"
                                        {{ $selectedReason === $reason->value ? 'selected' : '' }}>
                                        {{ $reason->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="{{ \App\Enums\PurchaseReturnStatus::PENDING->value }}"
                                    {{ $selectedStatus === \App\Enums\PurchaseReturnStatus::PENDING->value ? 'selected' : '' }}>
                                    Pending Approval
                                </option>
                                <option value="{{ \App\Enums\PurchaseReturnStatus::DRAFT->value }}"
                                    {{ $selectedStatus === \App\Enums\PurchaseReturnStatus::DRAFT->value ? 'selected' : '' }}>
                                    Draft
                                </option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shipment Reference</label>
                            <input type="text" name="shipment_reference"
                                class="form-control @error('shipment_reference') is-invalid @enderror"
                                value="{{ old('shipment_reference', $purchaseReturn?->shipment_reference) }}"
                                placeholder="Optional tracking or waybill">
                            @error('shipment_reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Credit Reference</label>
                            <input type="text" name="supplier_credit_reference"
                                class="form-control @error('supplier_credit_reference') is-invalid @enderror"
                                value="{{ old('supplier_credit_reference', $purchaseReturn?->supplier_credit_reference) }}"
                                placeholder="Optional credit note reference">
                            @error('supplier_credit_reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Credit Amount</label>
                            <input type="number" name="supplier_credit_amount"
                                class="form-control @error('supplier_credit_amount') is-invalid @enderror"
                                step="0.01" min="0"
                                value="{{ old('supplier_credit_amount', $purchaseReturn?->supplier_credit_amount ?? 0) }}">
                            @error('supplier_credit_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                placeholder="Optional notes about this supplier return">{{ old('notes', $purchaseReturn?->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Items to Return</h5>
                    <button type="button" class="btn btn-sm btn-primary" id="add-item-btn">
                        <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                        Add Item
                    </button>
                </div>
                <div class="card-body">
                    @error('items')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="items-table" style="min-width: 1080px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 320px; min-width: 320px;">Source</th>
                                    <th style="width: 220px; min-width: 220px;">Product</th>
                                    <th style="width: 120px; min-width: 120px;">Qty</th>
                                    <th style="width: 150px; min-width: 150px;">Unit Cost</th>
                                    <th style="width: 180px; min-width: 180px;">Condition</th>
                                    <th style="width: 220px; min-width: 220px;">Notes</th>
                                    <th style="width: 64px; min-width: 64px;"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody"></tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3" id="no-items-alert">
                        <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle me-2"></iconify-icon>
                        Add a completed stock intake or a customer return item marked for supplier return.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Items</span>
                        <span class="fw-medium" id="total-items">0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Quantity</span>
                        <span class="fw-medium" id="total-quantity">0</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fw-medium">Estimated Credit</span>
                        <span class="fw-bold fs-16" id="total-amount">0.00</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <iconify-icon icon="solar:check-circle-bold-duotone"
                                class="align-middle me-1"></iconify-icon>
                            {{ $submitLabel }}
                        </button>
                        <a href="{{ $cancelUrl }}" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sourceOptions = @json($sourceOptions);
            const initialItems = @json($initialItems);
            let itemIndex = 0;

            const itemsTbody = document.getElementById('items-tbody');
            const addItemBtn = document.getElementById('add-item-btn');
            const noItemsAlert = document.getElementById('no-items-alert');
            const supplierSelect = document.getElementById('supplier-id');
            const shopInput = document.getElementById('shop-id');
            const purchaseOrderInput = document.getElementById('purchase-order-id');
            const saleReturnInput = document.getElementById('sale-return-id');
            const totalItemsEl = document.getElementById('total-items');
            const totalQuantityEl = document.getElementById('total-quantity');
            const totalAmountEl = document.getElementById('total-amount');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function findSource(key) {
                return sourceOptions.find(option => option.key === key);
            }

            function supplierId() {
                return supplierSelect.value;
            }

            function filteredSourceOptions(selectedKey = '') {
                const selectedSupplierId = supplierId();
                const selectedKeys = selectedSourceKeys();
                const selectedShopId = activeShopId();

                if (!selectedSupplierId) {
                    return [];
                }

                return sourceOptions.filter(option => {
                    const matchesSupplier = String(option.supplier_id) === String(selectedSupplierId);
                    const matchesShop = !selectedShopId || String(option.shop_id) === String(
                        selectedShopId) || option.key === selectedKey;
                    const isAvailable = option.key === selectedKey || !selectedKeys.includes(option.key);

                    return matchesSupplier && matchesShop && isAvailable;
                });
            }

            function sourceOptionsHtml(selectedKey = '') {
                const options = filteredSourceOptions(selectedKey);
                let placeholder = '-- Select Supplier First --';

                if (supplierId()) {
                    placeholder = options.length > 0 ? '-- Select Source --' : '-- No Sources Available --';
                }

                return `<option value="">${placeholder}</option>${options.map(option => `
                        <option value="${escapeHtml(option.key)}" ${selectedKey === option.key ? 'selected' : ''}>
                            ${escapeHtml(option.label)}
                        </option>
                    `).join('')}`;
            }

            function updateSummary() {
                const rows = itemsTbody.querySelectorAll('tr');
                let totalQty = 0;
                let totalAmount = 0;

                rows.forEach(row => {
                    const qty = parseInt(row.querySelector('.quantity-input')?.value || '0', 10);
                    const unitCost = parseFloat(row.querySelector('.unit-cost-input')?.value || '0');
                    totalQty += qty || 0;
                    totalAmount += (qty || 0) * (unitCost || 0);
                });

                totalItemsEl.textContent = rows.length;
                totalQuantityEl.textContent = totalQty;
                totalAmountEl.textContent = totalAmount.toFixed(2);
                noItemsAlert.style.display = rows.length > 0 ? 'none' : 'block';
            }

            function selectedSources() {
                return Array.from(itemsTbody.querySelectorAll('.source-select'))
                    .map(select => findSource(select.value))
                    .filter(Boolean);
            }

            function selectedSourceKeys() {
                return Array.from(itemsTbody.querySelectorAll('.source-select'))
                    .map(select => select.value)
                    .filter(Boolean);
            }

            function activeShopId() {
                return selectedSources()[0]?.shop_id ?? '';
            }

            function supplierSourceCount() {
                const selectedSupplierId = supplierId();
                const selectedShopId = activeShopId();

                if (!selectedSupplierId) {
                    return 0;
                }

                return sourceOptions.filter(option => {
                    const matchesSupplier = String(option.supplier_id) === String(selectedSupplierId);
                    const matchesShop = !selectedShopId || String(option.shop_id) === String(
                    selectedShopId);

                    return matchesSupplier && matchesShop;
                }).length;
            }

            function updateAddItemButton() {
                const selectedCount = selectedSourceKeys().length;
                const availableCount = supplierSourceCount();

                addItemBtn.disabled = !supplierId() || selectedCount >= availableCount;
                addItemBtn.title = !supplierId() ?
                    'Select a supplier first' :
                    selectedCount >= availableCount ?
                    'All available sources for this supplier are already selected' :
                    '';
            }

            function syncHeaderFields() {
                const source = selectedSources()[0];

                shopInput.value = source?.shop_id ?? '';
                purchaseOrderInput.value = source?.purchase_order_id ?? '';
                saleReturnInput.value = source?.sale_return_id ?? '';
            }

            function clearRowSource(row) {
                row.querySelector('.stock-intake-id-input').value = '';
                row.querySelector('.return-item-id-input').value = '';
                row.querySelector('.purchase-order-item-id-input').value = '';
                row.querySelector('.product-id-input').value = '';
                row.querySelector('.product-variation-id-input').value = '';
                row.querySelector('.product-name').textContent = 'Select source';
                row.querySelector('.source-reference').textContent = '';
                row.querySelector('.source-meta').textContent = '';
                row.querySelector('.quantity-input').value = 1;
                row.querySelector('.unit-cost-input').value = 0;
                row.querySelector('.condition-input').value = '';
                row.querySelector('.notes-input').value = '';
            }

            function refreshSourceSelects() {
                const seenKeys = new Set();
                const selectedShopId = activeShopId();

                itemsTbody.querySelectorAll('tr').forEach(row => {
                    const sourceSelect = row.querySelector('.source-select');
                    const selectedKey = sourceSelect.value;
                    const selectedSource = findSource(selectedKey);
                    const keepSelectedSource = selectedSource &&
                        String(selectedSource.supplier_id) === String(supplierId()) &&
                        (!selectedShopId || String(selectedSource.shop_id) === String(selectedShopId)) &&
                        !seenKeys.has(selectedKey);

                    sourceSelect.innerHTML = sourceOptionsHtml(keepSelectedSource ? selectedKey : '');
                    sourceSelect.disabled = !supplierId();

                    if (keepSelectedSource) {
                        seenKeys.add(selectedKey);
                    }

                    if (!keepSelectedSource) {
                        clearRowSource(row);
                    }
                });

                syncHeaderFields();
                updateSummary();
                updateAddItemButton();
            }

            function applySource(row, source, prefill = {}) {
                row.querySelector('.stock-intake-id-input').value = source?.stock_intake_id ?? '';
                row.querySelector('.return-item-id-input').value = source?.return_item_id ?? '';
                row.querySelector('.purchase-order-item-id-input').value = source?.purchase_order_item_id ?? '';
                row.querySelector('.product-id-input').value = source?.product_id ?? '';
                row.querySelector('.product-variation-id-input').value = source?.product_variation_id ?? '';
                row.querySelector('.product-name').textContent = source?.product_name ?? 'Select source';
                row.querySelector('.source-reference').textContent = source?.reference ?
                    `Ref: ${source.reference}` : '';
                row.querySelector('.source-meta').textContent = source ? [source.supplier_name, source.shop_name]
                    .filter(Boolean).join(' • ') : '';

                const quantityInput = row.querySelector('.quantity-input');
                quantityInput.max = source?.available_quantity ?? 1;
                quantityInput.value = prefill.quantity ?? Math.min(1, Math.max(1, source?.available_quantity ?? 1));

                row.querySelector('.unit-cost-input').value = prefill.unit_cost ?? source?.unit_cost ?? 0;
                row.querySelector('.condition-input').value = prefill.condition ?? source?.condition ?? '';
                row.querySelector('.notes-input').value = prefill.notes ?? source?.notes ?? '';

                syncHeaderFields();
                updateSummary();
            }

            function addItem(prefill = {}) {
                const row = document.createElement('tr');
                const selectedKey = prefill.key ?? '';

                row.innerHTML = `
                    <td>
                        <select class="form-select form-select-sm source-select" required>
                            ${sourceOptionsHtml(selectedKey)}
                        </select>
                        <input type="hidden" name="items[${itemIndex}][stock_intake_id]" class="stock-intake-id-input">
                        <input type="hidden" name="items[${itemIndex}][return_item_id]" class="return-item-id-input">
                        <input type="hidden" name="items[${itemIndex}][purchase_order_item_id]" class="purchase-order-item-id-input">
                        <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id-input" required>
                        <input type="hidden" name="items[${itemIndex}][product_variation_id]" class="product-variation-id-input">
                    </td>
                    <td>
                        <div class="fw-medium product-name">Select source</div>
                        <small class="text-muted source-reference"></small>
                        <small class="text-muted d-block source-meta"></small>
                    </td>
                    <td>
                        <input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-sm quantity-input" min="1" value="1" style="min-width: 96px;" required>
                    </td>
                    <td>
                        <input type="number" name="items[${itemIndex}][unit_cost]" class="form-control form-control-sm unit-cost-input bg-light" min="0" step="0.01" value="0" style="min-width: 124px;" readonly>
                    </td>
                    <td>
                        <input type="text" name="items[${itemIndex}][condition]" class="form-control form-control-sm condition-input" placeholder="Condition">
                    </td>
                    <td>
                        <input type="text" name="items[${itemIndex}][notes]" class="form-control form-control-sm notes-input" placeholder="Optional notes">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-soft-danger remove-item-btn">
                            <iconify-icon icon="solar:trash-bin-trash-bold-duotone" class="align-middle"></iconify-icon>
                        </button>
                    </td>
                `;

                itemsTbody.appendChild(row);
                itemIndex++;

                const sourceSelect = row.querySelector('.source-select');
                sourceSelect.disabled = !supplierId();
                const selectedSource = findSource(selectedKey);
                if (selectedSource && String(selectedSource.supplier_id) === String(supplierId())) {
                    applySource(row, selectedSource, prefill);
                }

                sourceSelect.addEventListener('change', function() {
                    const source = findSource(this.value);

                    if (source) {
                        applySource(row, source);
                    } else {
                        clearRowSource(row);
                    }

                    refreshSourceSelects();
                });

                row.querySelector('.quantity-input').addEventListener('input', updateSummary);
                row.querySelector('.unit-cost-input').addEventListener('input', updateSummary);
                row.querySelector('.remove-item-btn').addEventListener('click', function() {
                    row.remove();
                    syncHeaderFields();
                    updateSummary();
                    refreshSourceSelects();
                });

                updateSummary();
                updateAddItemButton();
            }

            supplierSelect.addEventListener('change', refreshSourceSelects);
            addItemBtn.addEventListener('click', () => {
                if (!addItemBtn.disabled) {
                    addItem();
                }
            });

            if (initialItems.length > 0) {
                initialItems.forEach(item => addItem(item));
            } else {
                addItem();
            }
        });
    </script>
@endpush
