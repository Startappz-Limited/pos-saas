@extends('layouts.app')

@section('title', 'Create Return')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('returns.index') }}">Returns</a></li>
                        <li class="breadcrumb-item active">Create New</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Create Return Request</h4>
            </div>
        </div>

        <form action="{{ route('returns.store') }}" method="POST" id="return-form">
            @csrf
            <div class="row">
                <div class="col-xl-8">
                    <!-- Return Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Return Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="sale-search" class="form-label">Find Sale</label>
                                    <div class="input-group">
                                        <input type="search" id="sale-search" class="form-control"
                                            name="sale_search"
                                            value="{{ $saleSearch }}"
                                            placeholder="Search by invoice, customer name, or phone">
                                        <button type="button" id="sale-search-button" class="btn btn-outline-secondary">
                                            Search
                                        </button>
                                        @if ($saleSearch !== '')
                                            <a href="{{ route('returns.create', array_filter(['sale_id' => request('sale_id')])) }}"
                                                class="btn btn-light">Clear</a>
                                        @endif
                                    </div>
                                    <small class="text-muted">Search for a sale first, then choose it below.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sale <span class="text-danger">*</span></label>
                                    <select name="sale_id" id="sale-select"
                                        class="form-select @error('sale_id') is-invalid @enderror" data-choices
                                        data-choices-search-true required>
                                        <option value="">— Select Sale —</option>
                                        @foreach ($sales as $s)
                                            @php
                                                $customerName = $s->customer?->name ?? $s->walk_in_customer_name ?? 'Walk-in';
                                                $customerPhone = $s->customer?->phone ?? $s->walk_in_customer_phone;
                                            @endphp
                                            <option value="{{ $s->id }}"
                                                {{ old('sale_id') == $s->id || ($sale && $sale->id == $s->id) ? 'selected' : '' }}>
                                                {{ $s->invoice_number ?? '#' . $s->id }} —
                                                {{ $customerName }}{{ $customerPhone ? ' (' . $customerPhone . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('sale_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                                    <select name="reason" class="form-select @error('reason') is-invalid @enderror"
                                        required>
                                        <option value="">— Select Reason —</option>
                                        @foreach ($returnReasons as $reason)
                                            <option value="{{ $reason->value }}"
                                                {{ old('reason') == $reason->value ? 'selected' : '' }}>
                                                {{ $reason->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Restocking Fee</label>
                                    <input type="number" name="restocking_fee" step="0.01" min="0"
                                        class="form-control @error('restocking_fee') is-invalid @enderror"
                                        value="{{ old('restocking_fee', 0) }}" placeholder="0.00">
                                    @error('restocking_fee')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                        placeholder="Optional notes about this return">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Items -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Return Items</h5>
                        </div>
                        <div class="card-body">
                            @error('items')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            @if ($sale && $sale->items->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>
                                                    <input type="checkbox" id="select-all" class="form-check-input">
                                                </th>
                                                <th>Product</th>
                                                <th class="text-center">Sold Qty</th>
                                                <th style="width: 120px;">Return Qty</th>
                                                <th style="width: 140px;">Condition</th>
                                                <th style="width: 200px;">Notes</th>
                                                <th style="width: 180px;">Supplier Return</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sale->items as $index => $item)
                                                @php
                                                    $isSelected = old("items.{$index}.sale_item_id") == $item->id;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input item-checkbox"
                                                            data-index="{{ $index }}" @checked($isSelected)>
                                                    </td>
                                                    <td>{{ $item->product?->name ?? 'Unknown' }}</td>
                                                    <td class="text-center">{{ $item->quantity }}</td>
                                                    <td>
                                                        <input type="hidden"
                                                            name="items[{{ $index }}][sale_item_id]"
                                                            value="{{ $item->id }}" @disabled(!$isSelected)>
                                                        <input type="number" name="items[{{ $index }}][quantity]"
                                                            class="form-control form-control-sm" min="1"
                                                            max="{{ $item->quantity }}"
                                                            value="{{ old("items.{$index}.quantity", 1) }}"
                                                            @disabled(!$isSelected)>
                                                    </td>
                                                    <td>
                                                        <select name="items[{{ $index }}][condition]"
                                                            class="form-select form-select-sm" @disabled(!$isSelected)>
                                                            <option value="">—</option>
                                                            <option value="new" @selected(old("items.{$index}.condition") === 'new')>New</option>
                                                            <option value="opened" @selected(old("items.{$index}.condition") === 'opened')>Opened
                                                            </option>
                                                            <option value="damaged" @selected(old("items.{$index}.condition") === 'damaged')>Damaged
                                                            </option>
                                                            <option value="defective" @selected(old("items.{$index}.condition") === 'defective')>Defective
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                            name="items[{{ $index }}][condition_notes]"
                                                            class="form-control form-control-sm" placeholder="Notes"
                                                            value="{{ old("items.{$index}.condition_notes") }}"
                                                            @disabled(!$isSelected)>
                                                    </td>
                                                    <td>
                                                        <div class="form-check mb-2">
                                                            <input type="checkbox"
                                                                name="items[{{ $index }}][return_to_supplier]"
                                                                value="1" class="form-check-input"
                                                                @checked(old("items.{$index}.return_to_supplier")) @disabled(!$isSelected)>
                                                            <label class="form-check-label small">Mark for supplier</label>
                                                        </div>
                                                        <input type="text"
                                                            name="items[{{ $index }}][return_to_supplier_notes]"
                                                            class="form-control form-control-sm"
                                                            placeholder="Supplier note"
                                                            value="{{ old("items.{$index}.return_to_supplier_notes") }}"
                                                            @disabled(!$isSelected)>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @elseif($sale)
                                <div class="alert alert-warning mb-0">
                                    <iconify-icon icon="solar:info-circle-bold-duotone"
                                        class="align-middle me-2"></iconify-icon>
                                    This sale does not have any items available for return.
                                </div>
                            @else
                                <div class="alert alert-info mb-0">
                                    <iconify-icon icon="solar:info-circle-bold-duotone"
                                        class="align-middle me-2"></iconify-icon>
                                    Select a sale above to see available items for return.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Submit Return Request
                                </button>
                                <a href="{{ route('returns.index') }}" class="btn btn-light">
                                    Cancel
                                </a>
                            </div>
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
            const saleSearchInput = document.getElementById('sale-search');
            const saleSearchButton = document.getElementById('sale-search-button');
            const saleSelect = document.getElementById('sale-select');
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.item-checkbox');

            if (saleSelect) {
                saleSelect.addEventListener('change', function() {
                    const url = new URL(window.location.href);

                    if (this.value) {
                        url.searchParams.set('sale_id', this.value);
                    } else {
                        url.searchParams.delete('sale_id');
                    }

                    window.location.href = url.toString();
                });
            }

            if (saleSearchInput && saleSearchButton) {
                const applySaleSearch = function() {
                    const url = new URL(window.location.href);
                    const searchValue = saleSearchInput.value.trim();

                    if (searchValue) {
                        url.searchParams.set('sale_search', searchValue);
                    } else {
                        url.searchParams.delete('sale_search');
                    }

                    window.location.href = url.toString();
                };

                saleSearchButton.addEventListener('click', applySaleSearch);
                saleSearchInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applySaleSearch();
                    }
                });
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                        toggleItemInputs(cb);
                    });
                });

                checkboxes.forEach(cb => {
                    cb.addEventListener('change', function() {
                        toggleItemInputs(this);
                    });
                });
            }

            function toggleItemInputs(checkbox) {
                const row = checkbox.closest('tr');
                const inputs = row.querySelectorAll('input[name], select[name], textarea[name]');
                inputs.forEach(input => {
                    input.disabled = !checkbox.checked;
                });
            }
        });
    </script>
@endpush
