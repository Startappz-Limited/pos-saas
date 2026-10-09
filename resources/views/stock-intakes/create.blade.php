@extends('layouts.app')

@section('title', 'Create Stock Intake')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('stock-intakes.index') }}">Stock Intakes</a></li>
                        <li class="breadcrumb-item active">Create New</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Receive Stock</h4>
            </div>
        </div>

        <form action="{{ route('stock-intakes.store') }}" method="POST" id="stock-intake-form">
            @csrf
            <div class="row">
                <div class="col-xl-8">
                    <!-- Purchase Order Info (if linked) -->
                    @if (isset($purchaseOrder))
                        <div class="card mb-4">
                            <div class="card-header bg-primary-subtle">
                                <h5 class="card-title mb-0">
                                    <iconify-icon icon="solar:clipboard-list-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Receiving from Purchase Order: {{ $purchaseOrder->order_number }}
                                </h5>
                            </div>
                            <div class="card-body">
                                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">

                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <span class="text-muted">Supplier:</span>
                                            <strong>{{ $purchaseOrder->supplier?->name ?? 'N/A' }}</strong>
                                        </p>
                                        <p class="mb-1">
                                            <span class="text-muted">Shop:</span>
                                            <strong>{{ $purchaseOrder->shop?->name ?? 'N/A' }}</strong>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <span class="text-muted">Order Date:</span>
                                            <strong>{{ $purchaseOrder->order_date?->format('M d, Y') ?? 'N/A' }}</strong>
                                        </p>
                                        <p class="mb-1">
                                            <span class="text-muted">Expected Delivery:</span>
                                            <strong>{{ $purchaseOrder->expected_delivery_date?->format('M d, Y') ?? 'N/A' }}</strong>
                                        </p>
                                    </div>
                                </div>

                                @if (isset($purchaseOrderItem))
                                    <hr class="my-3">
                                    <div class="alert alert-info mb-0">
                                        <strong>Receiving specific item:</strong> {{ $purchaseOrderItem->product_name }}
                                        @if ($purchaseOrderItem->variation_attributes)
                                            <br>
                                            <small>
                                                @foreach ($purchaseOrderItem->variation_attributes as $attr => $value)
                                                    {{ ucfirst($attr) }}: {{ $value }}
                                                    @if (!$loop->last)
                                                        |
                                                    @endif
                                                @endforeach
                                            </small>
                                        @endif
                                        <br>
                                        <small class="text-muted">
                                            Remaining to receive:
                                            {{ number_format($purchaseOrderItem->quantity_remaining) }}
                                            {{ $purchaseOrderItem->unit ?? 'pcs' }}
                                        </small>
                                        <input type="hidden" name="purchase_order_item_id"
                                            value="{{ $purchaseOrderItem->id }}">
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Product Selection -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Product Information</h5>
                        </div>
                        <div class="card-body">
                            @if (isset($purchaseOrder) && !isset($purchaseOrderItem))
                                <!-- Select item from PO -->
                                <div class="mb-3">
                                    <label class="form-label">Select Item to Receive <span
                                            class="text-danger">*</span></label>
                                    <select name="purchase_order_item_id"
                                        class="form-select @error('purchase_order_item_id') is-invalid @enderror" required>
                                        <option value="">— Select an item —</option>
                                        @foreach ($purchaseOrder->items as $item)
                                            @if ($item->quantity_remaining > 0)
                                                <option value="{{ $item->id }}"
                                                    data-product-id="{{ $item->product_id }}"
                                                    data-variation-id="{{ $item->product_variation_id }}"
                                                    data-remaining="{{ $item->quantity_remaining }}"
                                                    data-unit="{{ $item->unit ?? 'pcs' }}"
                                                    data-unit-cost="{{ $item->unit_cost }}"
                                                    {{ old('purchase_order_item_id') == $item->id ? 'selected' : '' }}>
                                                    {{ $item->product_name }}
                                                    @if ($item->variation_attributes)
                                                        ({{ implode(', ', $item->variation_attributes) }})
                                                    @endif
                                                    - {{ number_format($item->quantity_remaining) }} remaining
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('purchase_order_item_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @elseif(isset($purchaseOrderItem))
                                <!-- Pre-selected item - just hidden fields -->
                                <input type="hidden" name="product_id" value="{{ $purchaseOrderItem->product_id }}">
                                <input type="hidden" name="product_variation_id"
                                    value="{{ $purchaseOrderItem->product_variation_id }}">
                            @else
                                <!-- Manual intake - select product -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Product <span class="text-danger">*</span></label>
                                        <select name="product_id" id="product_id"
                                            class="form-select @error('product_id') is-invalid @enderror" data-choices
                                            data-choices-search-true required>
                                            <option value="">— Select Product —</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}"
                                                    data-has-variations="{{ $product->has_variations ? 'true' : 'false' }}"
                                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                                    {{ $product->name }} ({{ $product->sku }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('product_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3" id="variation-container" style="display: none;">
                                        <label class="form-label">Variation</label>
                                        <select name="product_variation_id" id="product_variation_id"
                                            class="form-select @error('product_variation_id') is-invalid @enderror">
                                            <option value="">— Select Variation —</option>
                                        </select>
                                        @error('product_variation_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Supplier</label>
                                        <select name="supplier_id"
                                            class="form-select @error('supplier_id') is-invalid @enderror">
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
                                        <select name="shop_id" class="form-select @error('shop_id') is-invalid @enderror"
                                            required>
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
                            @endif
                        </div>
                    </div>

                    <!-- Quantity & Quality -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quantity & Quality Check</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity Received <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity_received"
                                        class="form-control @error('quantity_received') is-invalid @enderror"
                                        value="{{ old('quantity_received', isset($purchaseOrderItem) ? $purchaseOrderItem->quantity_remaining : '') }}"
                                        min="0" step="0.01" required>
                                    @error('quantity_received')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if (isset($purchaseOrderItem))
                                        <small class="text-muted">Max:
                                            {{ number_format($purchaseOrderItem->quantity_remaining) }}
                                            {{ $purchaseOrderItem->unit ?? 'pcs' }}</small>
                                    @endif
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity Accepted <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity_accepted"
                                        class="form-control @error('quantity_accepted') is-invalid @enderror"
                                        value="{{ old('quantity_accepted', isset($purchaseOrderItem) ? $purchaseOrderItem->quantity_remaining : '') }}"
                                        min="0" step="0.01" required>
                                    @error('quantity_accepted')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity Rejected</label>
                                    <input type="number" name="quantity_rejected"
                                        class="form-control @error('quantity_rejected') is-invalid @enderror"
                                        value="{{ old('quantity_rejected', 0) }}" min="0" step="0.01">
                                    @error('quantity_rejected')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" name="unit"
                                        class="form-control @error('unit') is-invalid @enderror"
                                        value="{{ old('unit', isset($purchaseOrderItem) ? $purchaseOrderItem->unit : 'pcs') }}"
                                        placeholder="e.g., pcs, kg, liters">
                                    @error('unit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Quality Status</label>
                                    <select name="quality_status"
                                        class="form-select @error('quality_status') is-invalid @enderror">
                                        <option value="passed" {{ old('quality_status') === 'passed' ? 'selected' : '' }}>
                                            Passed</option>
                                        <option value="partial"
                                            {{ old('quality_status') === 'partial' ? 'selected' : '' }}>Partial (Some
                                            Issues)</option>
                                        <option value="failed" {{ old('quality_status') === 'failed' ? 'selected' : '' }}>
                                            Failed</option>
                                    </select>
                                    @error('quality_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Quality Notes</label>
                                <textarea name="quality_notes" class="form-control @error('quality_notes') is-invalid @enderror" rows="2"
                                    placeholder="Describe any quality issues, damages, or discrepancies...">{{ old('quality_notes') }}</textarea>
                                @error('quality_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Additional Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" name="expiry_date"
                                        class="form-control @error('expiry_date') is-invalid @enderror"
                                        value="{{ old('expiry_date') }}">
                                    @error('expiry_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                    placeholder="Any additional notes about this intake...">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-xl-4">
                    <!-- Summary Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="save_draft" class="btn btn-secondary">
                                    <iconify-icon icon="solar:diskette-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Save as Draft
                                </button>
                                <button type="submit" name="action" value="save_complete" class="btn btn-success">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Save & Complete Intake
                                </button>
                                <a href="{{ url()->previous() }}" class="btn btn-light">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    @if (isset($purchaseOrder))
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">PO Items Summary</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center">Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($purchaseOrder->items as $item)
                                                <tr class="{{ $item->quantity_remaining <= 0 ? 'table-success' : '' }}">
                                                    <td class="text-truncate" style="max-width: 150px;">
                                                        {{ $item->product_name }}
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($item->quantity_remaining > 0)
                                                            <span
                                                                class="badge bg-warning-subtle text-warning">{{ number_format($item->quantity_remaining) }}</span>
                                                        @else
                                                            <span
                                                                class="badge bg-success-subtle text-success">Complete</span>
                                                        @endif
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
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Auto-calculate rejected quantity
                const qtyReceived = document.querySelector('input[name="quantity_received"]');
                const qtyAccepted = document.querySelector('input[name="quantity_accepted"]');
                const qtyRejected = document.querySelector('input[name="quantity_rejected"]');

                function calculateRejected() {
                    const received = parseFloat(qtyReceived.value) || 0;
                    const accepted = parseFloat(qtyAccepted.value) || 0;
                    qtyRejected.value = Math.max(0, received - accepted);
                }

                if (qtyReceived && qtyAccepted && qtyRejected) {
                    qtyReceived.addEventListener('change', calculateRejected);
                    qtyAccepted.addEventListener('change', calculateRejected);
                }

                // Handle PO item selection
                const poItemSelect = document.querySelector('select[name="purchase_order_item_id"]');
                if (poItemSelect) {
                    poItemSelect.addEventListener('change', function() {
                        const selected = this.options[this.selectedIndex];
                        if (selected.value) {
                            const remaining = selected.dataset.remaining;
                            const unit = selected.dataset.unit;
                            qtyReceived.value = remaining;
                            qtyAccepted.value = remaining;
                            qtyRejected.value = 0;
                        }
                    });
                }

                // Handle product selection for variations
                const productSelect = document.getElementById('product_id');
                const variationContainer = document.getElementById('variation-container');
                const variationSelect = document.getElementById('product_variation_id');

                if (productSelect && variationContainer) {
                    productSelect.addEventListener('change', async function() {
                        const selectedOption = productSelect.querySelector(`option[value="${this.value}"]`);
                        const hasVariations = selectedOption?.dataset?.hasVariations === 'true';
                        if (hasVariations && this.value) {
                            variationContainer.style.display = 'block';
                            // Fetch variations via API
                            try {
                                const response = await fetch(
                                    `/api/products/${this.value}/variations`);
                                const variations = await response.json();
                                variationSelect.innerHTML =
                                '<option value="">— Select Variation —</option>';
                                variations.forEach(v => {
                                    variationSelect.innerHTML +=
                                        `<option value="${v.id}">${v.name}</option>`;
                                });
                            } catch (e) {
                                console.error('Failed to load variations:', e);
                            }
                        } else {
                            variationContainer.style.display = 'none';
                            variationSelect.value = '';
                        }
                    });
                }
            });
        </script>
    @endpush
@endsection
