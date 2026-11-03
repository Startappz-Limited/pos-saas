{{--
    The adjustment form, shared by create and edit (stage 10, step 1g). Each line names a
    product, its variation when it has some, and a signed change: + adds stock, − removes it,
    agreeing with the reason. Expects $products (ids and names only), $adjustmentReasons and,
    on edit, $stockAdjustment.
--}}
@php
    $editing = isset($stockAdjustment);
    $lines = old('items', $editing
        ? $stockAdjustment->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'variation_id' => $item->variation?->is_default ? null : $item->variation_id,
            'quantity_change' => $item->quantity_change,
            'item_notes' => $item->item_notes,
        ])->values()->all()
        : []);
    $directions = collect($adjustmentReasons)->mapWithKeys(fn ($reason) => [$reason->value => $reason->direction()]);
    $text = [
        'product' => __('— Select Product —'),
        'variation' => __('— Select Variation —'),
        'none' => __('No variations'),
        'notes' => __('Optional notes'),
        'remove' => __('Remove line'),
        'productLabel' => __('Product'),
        'variationLabel' => __('Variation'),
        'changeLabel' => __('Change (+/−)'),
        'notesLabel' => __('Notes'),
        'increase' => __('This reason adds stock: enter positive numbers.'),
        'decrease' => __('This reason removes stock: enter negative numbers.'),
        'either' => __('Enter + for more stock and − for less.'),
        'recount' => __('Enter the difference you found, not the counted figure. Sales made while you counted are not allowed for.'),
    ];
@endphp

<div class="row">
    <div class="col-xl-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ __('Adjustment Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        @if ($editing)
                            <label class="form-label" for="adjustment-shop">{{ __('Shop') }}</label>
                            <input type="text" id="adjustment-shop" class="form-control" value="{{ $stockAdjustment->shop?->displayName() ?? '—' }}" disabled>
                            <small class="text-muted">{{ __('Shop cannot be changed after creation') }}</small>
                        @else
                            <label class="form-label" for="adjustment-shop">{{ __('Shop') }} <span class="text-danger">*</span></label>
                            <select name="shop_id" id="adjustment-shop" class="form-select @error('shop_id') is-invalid @enderror" required>
                                <option value="">{{ __('— Select Shop —') }}</option>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }}</option>
                                @endforeach
                            </select>
                            @error('shop_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="adjustment-reason">{{ __('Reason') }} <span class="text-danger">*</span></label>
                        <select name="reason" id="adjustment-reason" class="form-select @error('reason') is-invalid @enderror" required>
                            <option value="">{{ __('— Select Reason —') }}</option>
                            @foreach ($adjustmentReasons as $reason)
                                <option value="{{ $reason->value }}" @selected(old('reason', $editing ? $stockAdjustment->reason?->value : null) === $reason->value)>
                                    {{ __($reason->label()) }}
                                </option>
                            @endforeach
                        </select>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1" id="reason-hint" aria-live="polite"></small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="adjustment-notes">{{ __('Notes') }}</label>
                    <textarea name="notes" id="adjustment-notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                        placeholder="{{ __('Optional notes about this adjustment') }}">{{ old('notes', $editing ? $stockAdjustment->notes : null) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <p class="text-muted small mb-0">
                    {{ __('Sending stock back to a supplier?') }}
                    @can('create', App\Models\PurchaseReturn::class)
                        <a href="{{ route('purchase-returns.create') }}">{{ __('Raise a purchase return') }}</a>{{ __(', which keeps the credit the supplier owes you.') }}
                    @else
                        {{ __('That is a purchase return, which keeps the credit the supplier owes you.') }}
                    @endcan
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('Adjustment Items') }}</h5>
                <button type="button" class="btn btn-sm btn-primary" id="add-item-btn">
                    <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1" aria-hidden="true"></iconify-icon>
                    {{ __('Add Item') }}
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    {{ __('Enter the change, not the count: +3 adds three, −3 removes three.') }}
                </p>

                @if ($errors->has('items') || $errors->has('items.*'))
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach (array_merge($errors->get('items'), ...array_values($errors->get('items.*'))) as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="items-table">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Variation') }}</th>
                                <th>{{ __('Change (+/−)') }}</th>
                                <th>{{ __('Notes') }}</th>
                                <th><span class="visually-hidden">{{ __('Remove') }}</span></th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody"></tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3 d-none" id="no-items-alert">
                    <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle me-2" aria-hidden="true"></iconify-icon>
                    {{ __('Click "Add Item" to add products to this adjustment.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ __('Summary') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">{{ __('Total Items') }}</span>
                    <span class="fw-medium" id="total-items">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">{{ __('Units added') }}</span>
                    <span class="fw-medium text-success" id="total-added">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">{{ __('Units removed') }}</span>
                    <span class="fw-medium text-danger" id="total-removed">0</span>
                </div>
                <p class="text-muted small mb-0">
                    {{ __('Loss, theft, damage, expiry, an unknown cost or a value of :threshold or more waits for someone else to approve it. Anything else is applied when you save.', ['threshold' => number_format(App\Support\Stock\AdjustmentApproval::threshold(app(App\Support\Tenancy\TenantContext::class)->business()), 2)]) }}
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="align-middle me-1" aria-hidden="true"></iconify-icon>
                        {{ $editing ? __('Update Adjustment') : __('Save Adjustment') }}
                    </button>
                    <a href="{{ $editing ? route('stock-adjustments.show', $stockAdjustment) : route('stock-adjustments.index') }}" class="btn btn-light">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const products = @json($products);
    const lines = @json(array_values($lines));
    const directions = @json($directions);
    const text = @json($text);
    let index = 0;

    const tbody = document.getElementById('items-tbody');
    const reason = document.getElementById('adjustment-reason');
    const hint = document.getElementById('reason-hint');

    function option(value, label, selected) {
        const element = document.createElement('option');
        element.value = value;
        element.textContent = label;
        element.selected = selected;
        return element;
    }

    function summarise() {
        let added = 0;
        let removed = 0;
        tbody.querySelectorAll('.qty-input').forEach(input => {
            const value = parseInt(input.value, 10) || 0;
            value > 0 ? added += value : removed -= value;
        });
        document.getElementById('total-items').textContent = tbody.rows.length;
        document.getElementById('total-added').textContent = '+' + added;
        document.getElementById('total-removed').textContent = '−' + removed;
        document.getElementById('no-items-alert').classList.toggle('d-none', tbody.rows.length > 0);
    }

    function describeReason() {
        const direction = directions[reason.value];
        hint.textContent = reason.value === 'recount' ? text.recount : (direction ? text[direction] : '');
        tbody.querySelectorAll('.qty-input').forEach(input => {
            input.min = direction === 'increase' ? 1 : -1000000;
            input.max = direction === 'decrease' ? -1 : 1000000;
        });
    }

    function fillVariations(select, productId, selectedId) {
        const product = products.find(p => String(p.id) === String(productId));
        const variations = product ? product.variations : [];
        select.replaceChildren();
        if (variations.length === 0) {
            select.append(option('', text.none, true));
            select.disabled = true;
            select.required = false;
            return;
        }
        select.disabled = false;
        select.required = true;
        select.append(option('', text.variation, !selectedId));
        variations.forEach(v => select.append(option(v.id, v.name, String(v.id) === String(selectedId))));
    }

    function addLine(line = {}) {
        const i = index++;
        const row = tbody.insertRow();

        const product = document.createElement('select');
        product.name = `items[${i}][product_id]`;
        product.className = 'form-select form-select-sm';
        product.required = true;
        product.setAttribute('aria-label', text.productLabel);
        product.append(option('', text.product, !line.product_id));
        products.forEach(p => product.append(option(p.id, p.name, String(p.id) === String(line.product_id))));

        const variation = document.createElement('select');
        variation.name = `items[${i}][variation_id]`;
        variation.className = 'form-select form-select-sm';
        variation.setAttribute('aria-label', text.variationLabel);
        fillVariations(variation, line.product_id, line.variation_id);
        product.addEventListener('change', () => fillVariations(variation, product.value, null));

        const quantity = document.createElement('input');
        quantity.type = 'number';
        quantity.step = 1;
        quantity.name = `items[${i}][quantity_change]`;
        quantity.className = 'form-control form-control-sm qty-input';
        quantity.required = true;
        quantity.value = line.quantity_change ?? '';
        quantity.placeholder = '+3 / −3';
        quantity.setAttribute('aria-label', text.changeLabel);
        quantity.addEventListener('input', summarise);

        const notes = document.createElement('input');
        notes.type = 'text';
        notes.name = `items[${i}][item_notes]`;
        notes.className = 'form-control form-control-sm';
        notes.placeholder = text.notes;
        notes.value = line.item_notes ?? '';
        notes.setAttribute('aria-label', text.notesLabel);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-soft-danger';
        remove.setAttribute('aria-label', text.remove);
        remove.innerHTML = '<iconify-icon icon="solar:trash-bin-trash-bold-duotone" class="align-middle" aria-hidden="true"></iconify-icon>';
        remove.addEventListener('click', () => { row.remove(); summarise(); });

        [product, variation, quantity, notes, remove].forEach(element => row.insertCell().append(element));
        describeReason();
        summarise();
    }

    document.getElementById('add-item-btn').addEventListener('click', () => addLine());
    reason.addEventListener('change', describeReason);

    (lines.length ? lines : [{}]).forEach(line => addLine(line));
    describeReason();
});
</script>
@endpush
