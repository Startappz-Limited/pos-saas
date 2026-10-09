@extends('layouts.app')

@section('title', 'Edit Stock Adjustment: ' . $stockAdjustment->adjustment_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('stock-adjustments.show', $stockAdjustment) }}">{{ $stockAdjustment->adjustment_number }}</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Edit Adjustment: {{ $stockAdjustment->adjustment_number }}</h4>
            </div>
        </div>

        <form action="{{ route('stock-adjustments.update', $stockAdjustment) }}" method="POST" id="stock-adjustment-form">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-xl-8">
                    <!-- Adjustment Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Adjustment Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Shop</label>
                                    <input type="text" class="form-control" value="{{ $stockAdjustment->shop->name }}" disabled>
                                    <small class="text-muted">Shop cannot be changed after creation</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adjustment Type <span class="text-danger">*</span></label>
                                    <select name="type" id="adjustment-type" class="form-select @error('type') is-invalid @enderror" required>
                                        @foreach ($adjustmentTypes as $type)
                                            <option value="{{ $type->value }}"
                                                {{ old('type', $stockAdjustment->type->value) == $type->value ? 'selected' : '' }}>
                                                {{ ucfirst($type->value) }} Stock
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                                    <select name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                                        <option value="">— Select Reason —</option>
                                        @foreach ($adjustmentReasons as $reason)
                                            <option value="{{ $reason->value }}"
                                                {{ old('reason', $stockAdjustment->reason->value) == $reason->value ? 'selected' : '' }}>
                                                {{ ucwords(str_replace('_', ' ', $reason->value)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                        placeholder="Optional notes about this adjustment">{{ old('notes', $stockAdjustment->notes) }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Adjustment Items -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Adjustment Items</h5>
                            <button type="button" class="btn btn-sm btn-primary" id="add-item-btn">
                                <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" id="items-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40%;">Product</th>
                                            <th style="width: 20%;">Quantity</th>
                                            <th style="width: 30%;">Notes</th>
                                            <th style="width: 10%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-tbody">
                                        <!-- Items will be added here dynamically -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info mt-3" id="no-items-alert" style="display: none;">
                                <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle me-2"></iconify-icon>
                                Click "Add Item" to add products to this adjustment.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <!-- Summary Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Total Items</span>
                                <span class="fw-medium" id="total-items">{{ $stockAdjustment->items->count() }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Total Quantity</span>
                                <span class="fw-medium" id="total-quantity">{{ $stockAdjustment->total_quantity_change }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Update Adjustment
                                </button>
                                <a href="{{ route('stock-adjustments.show', $stockAdjustment) }}" class="btn btn-light">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Delete -->
                    <div class="card mt-4 border-danger">
                        <div class="card-body">
                            <h6 class="text-danger">Danger Zone</h6>
                            <p class="text-muted small mb-3">Deleting this adjustment cannot be undone.</p>
                            <form action="{{ route('stock-adjustments.destroy', $stockAdjustment) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm w-100"
                                    onclick="return confirm('Are you sure you want to delete this adjustment?')">
                                    <iconify-icon icon="solar:trash-bin-trash-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Delete Adjustment
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const products = @json($products);
    const existingItems = @json($stockAdjustment->items);
    let itemIndex = 0;

    const itemsTbody = document.getElementById('items-tbody');
    const addItemBtn = document.getElementById('add-item-btn');
    const noItemsAlert = document.getElementById('no-items-alert');
    const totalItemsEl = document.getElementById('total-items');
    const totalQuantityEl = document.getElementById('total-quantity');

    function updateSummary() {
        const rows = itemsTbody.querySelectorAll('tr');
        let totalQty = 0;
        rows.forEach(row => {
            const qtyInput = row.querySelector('input[name$="[quantity_change]"]');
            if (qtyInput) {
                totalQty += parseInt(qtyInput.value) || 0;
            }
        });
        totalItemsEl.textContent = rows.length;
        totalQuantityEl.textContent = totalQty;
        noItemsAlert.style.display = rows.length > 0 ? 'none' : 'block';
    }

    function addItem(productId = '', quantity = 1, notes = '') {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select name="items[${itemIndex}][product_id]" class="form-select form-select-sm" required>
                    <option value="">— Select Product —</option>
                    ${products.map(p => `<option value="${p.id}" ${p.id == productId ? 'selected' : ''}>${p.name}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" name="items[${itemIndex}][quantity_change]" class="form-control form-control-sm qty-input"
                    min="1" value="${quantity}" required>
            </td>
            <td>
                <input type="text" name="items[${itemIndex}][item_notes]" class="form-control form-control-sm"
                    placeholder="Optional notes" value="${notes || ''}">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-soft-danger remove-item-btn">
                    <iconify-icon icon="solar:trash-bin-trash-bold-duotone" class="align-middle"></iconify-icon>
                </button>
            </td>
        `;
        itemsTbody.appendChild(row);
        itemIndex++;
        updateSummary();

        // Add event listener to new quantity input
        row.querySelector('.qty-input').addEventListener('input', updateSummary);

        // Add event listener to remove button
        row.querySelector('.remove-item-btn').addEventListener('click', function() {
            row.remove();
            updateSummary();
        });
    }

    addItemBtn.addEventListener('click', function() {
        addItem();
    });

    // Load existing items
    existingItems.forEach(item => {
        addItem(item.product_id, item.quantity_change, item.item_notes);
    });
});
</script>
@endpush