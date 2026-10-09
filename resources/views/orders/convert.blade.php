@extends('layouts.app')

@section('title', 'Convert Order #' . $order->order_number . ' to Sale')

@section('content')

    <form method="POST" action="{{ route('ecommerce-orders.storeConversion', $order) }}">
        @csrf
        <div class="row">
            <!-- Left Column - Conversion Form -->
            <div class="col-12 col-lg-8 order-2 order-lg-1">
                <!-- Order Summary -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:cart-large-4-bold-duotone"
                                class="align-middle me-1 text-primary"></iconify-icon>
                            Order #{{ $order->order_number }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <p class="mb-1">
                                    <span class="fw-medium">Platform:</span>
                                    @if ($order->platform === 'woocommerce')
                                        <span class="badge bg-purple-subtle text-purple">WooCommerce</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">Shopify</span>
                                    @endif
                                </p>
                                <p class="mb-1"><span class="fw-medium">Customer:</span>
                                    {{ $order->customer_name ?? 'N/A' }}</p>
                                <p class="mb-0"><span class="fw-medium">Email:</span>
                                    {{ $order->customer_email ?? 'N/A' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <p class="mb-1">
                                    <span class="fw-medium">Status:</span>
                                    <span
                                        class="badge bg-{{ $order->status->color() }}-subtle text-{{ $order->status->color() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </p>
                                <p class="mb-1"><span class="fw-medium">Payment:</span>
                                    {{ $order->payment_method ?? 'N/A' }}
                                    @if ($order->is_cod)
                                        <span class="badge bg-warning-subtle text-warning">COD</span>
                                    @endif
                                </p>
                                <p class="mb-0"><span class="fw-medium">Total:</span>
                                    <span class="fw-semibold text-primary">{{ $order->currency }}
                                        {{ number_format($order->total, 2) }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sale Settings -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:settings-bold-duotone"
                                class="align-middle me-1 text-primary"></iconify-icon>
                            Sale Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="register_id" class="form-label">Cash Register <span
                                        class="text-danger">*</span></label>
                                @if ($activeRegister)
                                    <input type="hidden" name="register_id" value="{{ $activeRegister->id }}">
                                    <input type="text" class="form-control" disabled
                                        value="{{ $activeRegister->register_name ?? 'Register #' . $activeRegister->id }} ({{ $activeRegister->register_date->format('M d, Y') }})">
                                @else
                                    <div class="alert alert-warning mb-0">
                                        <iconify-icon icon="solar:danger-triangle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        No active register. A register will be auto-opened on conversion.
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sale Source</label>
                                <input type="hidden" name="source_id" value="{{ $websiteSource->id }}">
                                <input type="text" class="form-control" disabled value="{{ $websiteSource->name }}">
                                @error('source_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label">Customer (Optional)</label>
                                <select name="customer_id" id="customer_id"
                                    class="form-select @error('customer_id') is-invalid @enderror">
                                    <option value="">Walk-in Customer ({{ $order->customer_name ?? 'Unknown' }})
                                    </option>
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method</label>
                                @if ($order->is_cod)
                                    <input type="hidden" name="payment_method" value="cash">
                                    <input type="text" class="form-control" disabled value="Cash on Delivery">
                                    <small class="text-muted mt-1 d-block">
                                        <iconify-icon icon="solar:info-circle-bold"
                                            class="align-middle me-1"></iconify-icon>
                                        COD order — sale will be created as unpaid/pending
                                    </small>
                                @else
                                    <input type="hidden" name="payment_method"
                                        value="{{ $order->payment_method ?? 'online' }}">
                                    <input type="text" class="form-control" disabled
                                        value="{{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'Online')) }} (Paid)">
                                    <small class="text-muted mt-1 d-block">
                                        <iconify-icon icon="solar:check-circle-bold"
                                            class="align-middle me-1 text-success"></iconify-icon>
                                        Already paid online — sale will be marked as completed
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Information -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:delivery-bold-duotone"
                                class="align-middle me-1 text-primary"></iconify-icon>
                            Delivery Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="delivery_company_id" class="form-label">Delivery Company</label>
                                <select name="delivery_company_id" id="delivery_company_id"
                                    class="form-select @error('delivery_company_id') is-invalid @enderror">
                                    <option value="">None</option>
                                    @foreach ($deliveryCompanies as $company)
                                        <option value="{{ $company->id }}"
                                            {{ old('delivery_company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('delivery_company_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="delivery_fee" class="form-label">Delivery Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" name="delivery_fee" id="delivery_fee"
                                        class="form-control @error('delivery_fee') is-invalid @enderror"
                                        value="{{ old('delivery_fee', $order->shipping_total) }}" step="0.01"
                                        min="0">
                                </div>
                                @error('delivery_fee')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="delivery_location" class="form-label">Delivery Location</label>
                                @php
                                    $shippingAddr = $order->shipping_address;
                                    $defaultLocation = '';
                                    if ($shippingAddr) {
                                        $parts = array_filter([
                                            $shippingAddr['address_1'] ?? '',
                                            $shippingAddr['address_2'] ?? '',
                                            $shippingAddr['city'] ?? '',
                                            $shippingAddr['state'] ?? '',
                                            $shippingAddr['postcode'] ?? '',
                                            $shippingAddr['country'] ?? '',
                                        ]);
                                        $defaultLocation = implode(', ', $parts);
                                    }
                                @endphp
                                <textarea name="delivery_location" id="delivery_location"
                                    class="form-control @error('delivery_location') is-invalid @enderror" rows="2">{{ old('delivery_location', $defaultLocation) }}</textarea>
                                @error('delivery_location')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:box-bold-duotone"
                                class="align-middle me-1 text-primary"></iconify-icon>
                            Order Items
                        </h5>
                        <button type="button" class="btn btn-sm btn-primary" id="add-order-item">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            Add Item
                        </button>
                    </div>
                    <div class="card-body">
                        @error('items')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="table-responsive">
                            <table class="table table-nowrap align-middle mb-0" id="order-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="min-width: 320px;">Product</th>
                                        <th scope="col" style="min-width: 180px;">Variation</th>
                                        <th scope="col" class="text-center" style="width: 110px;">Qty</th>
                                        <th scope="col" class="text-end" style="width: 150px;">Price</th>
                                        <th scope="col" class="text-end" style="width: 150px;">Total</th>
                                        <th scope="col" class="text-center" style="width: 70px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="order-items-tbody">
                                    @foreach ($order->items as $index => $item)
                                        <tr class="order-item-row" data-index="{{ $index }}">
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][order_item_id]"
                                                    value="{{ $item->id }}">
                                                <div class="product-search-wrapper position-relative">
                                                    <input type="text" class="form-control product-search"
                                                        value="{{ $item->product?->name ? $item->product->name . ' (' . $item->product->sku . ')' : $item->name }}"
                                                        placeholder="Search product by name or SKU..." autocomplete="off">
                                                    <select name="items[{{ $index }}][product_id]"
                                                        class="form-select product-select d-none"
                                                        data-selected="{{ old("items.{$index}.product_id", $item->product_id) }}"></select>
                                                    <div class="product-results position-absolute bg-white border rounded shadow-sm d-none"
                                                        style="z-index: 1000; max-height: 300px; overflow-y: auto; width: 100%;">
                                                    </div>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    Original: {{ $item->name }}
                                                    @if ($item->sku)
                                                        &middot; SKU: {{ $item->sku }}
                                                    @endif
                                                </small>
                                                @error("items.{$index}.product_id")
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>
                                                <select name="items[{{ $index }}][variation_id]"
                                                    class="form-select variation-select"
                                                    data-selected="{{ old("items.{$index}.variation_id") }}"></select>
                                                @error("items.{$index}.variation_id")
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $index }}][quantity]"
                                                    class="form-control item-quantity text-center"
                                                    value="{{ old("items.{$index}.quantity", $item->quantity) }}"
                                                    min="1" step="1" required>
                                                @error("items.{$index}.quantity")
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <span class="input-group-text">{{ $order->currency }}</span>
                                                    <input type="number" name="items[{{ $index }}][unit_price]"
                                                        class="form-control item-price text-end"
                                                        value="{{ old("items.{$index}.unit_price", $item->unit_price) }}"
                                                        min="0" step="0.01" required>
                                                </div>
                                                @error("items.{$index}.unit_price")
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>
                                                <input type="text" class="form-control item-total text-end" readonly>
                                            </td>
                                            <td class="text-center">
                                                <button type="button"
                                                    class="btn btn-sm btn-soft-danger remove-order-item">
                                                    <iconify-icon
                                                        icon="solar:trash-bin-minimalistic-linear"></iconify-icon>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <template id="order-item-row-template">
                    <tr class="order-item-row" data-index="INDEX">
                        <td>
                            <div class="product-search-wrapper position-relative">
                                <input type="text" class="form-control product-search"
                                    placeholder="Search product by name or SKU..." autocomplete="off">
                                <select name="items[INDEX][product_id]"
                                    class="form-select product-select d-none"></select>
                                <div class="product-results position-absolute bg-white border rounded shadow-sm d-none"
                                    style="z-index: 1000; max-height: 300px; overflow-y: auto; width: 100%;"></div>
                            </div>
                        </td>
                        <td>
                            <select name="items[INDEX][variation_id]" class="form-select variation-select"></select>
                        </td>
                        <td>
                            <input type="number" name="items[INDEX][quantity]"
                                class="form-control item-quantity text-center" value="1" min="1"
                                step="1" required>
                        </td>
                        <td>
                            <div class="input-group">
                                <span class="input-group-text">{{ $order->currency }}</span>
                                <input type="number" name="items[INDEX][unit_price]"
                                    class="form-control item-price text-end" min="0" step="0.01" required>
                            </div>
                        </td>
                        <td>
                            <input type="text" class="form-control item-total text-end" readonly>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger remove-order-item">
                                <iconify-icon icon="solar:trash-bin-minimalistic-linear"></iconify-icon>
                            </button>
                        </td>
                    </tr>
                </template>
            </div>

            <!-- Right Column - Summary -->
            <div class="col-12 col-lg-4 order-1 order-lg-2">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Order Total</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">Subtotal:</td>
                                        <td class="text-end">
                                            <span id="summary-subtotal">{{ $order->currency }}
                                                {{ number_format($order->subtotal, 2) }}</span>
                                        </td>
                                    </tr>
                                    @if ($order->discount_total > 0)
                                        <tr>
                                            <td class="text-muted">Discount:</td>
                                            <td class="text-end text-danger">-{{ $order->currency }}
                                                {{ number_format($order->discount_total, 2) }}</td>
                                        </tr>
                                    @endif
                                    @if ($order->shipping_total > 0)
                                        <tr id="summary-shipping-row">
                                            <td class="text-muted">Shipping:</td>
                                            <td class="text-end">
                                                <span id="summary-shipping">{{ $order->currency }}
                                                    {{ number_format($order->shipping_total, 2) }}</span>
                                            </td>
                                        </tr>
                                    @else
                                        <tr id="summary-shipping-row" class="d-none">
                                            <td class="text-muted">Shipping:</td>
                                            <td class="text-end"><span id="summary-shipping">{{ $order->currency }}
                                                    0.00</span></td>
                                        </tr>
                                    @endif
                                    @if ($order->tax_total > 0)
                                        <tr>
                                            <td class="text-muted">Tax:</td>
                                            <td class="text-end">{{ $order->currency }}
                                                {{ number_format($order->tax_total, 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr class="border-top">
                                        <th class="fs-16">Total:</th>
                                        <th class="text-end fs-16 text-primary">
                                            <span id="summary-total">{{ $order->currency }}
                                                {{ number_format($order->total, 2) }}</span>
                                        </th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <iconify-icon icon="solar:cart-plus-bold-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                @if ($order->is_cod)
                                    Convert to Sale (COD - Unpaid)
                                @else
                                    Convert to Sale (Paid)
                                @endif
                            </button>
                            <a href="{{ route('ecommerce-orders.show', $order) }}" class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                Back to Order
                            </a>
                        </div>

                        {{-- Order Actions (status changes synced to platform) --}}
                        @if ($order->status->canChangeStatus() && count($order->status->allowedTransitions()) > 0)
                            <hr class="my-3">
                            <p class="text-muted small mb-2">
                                <iconify-icon icon="solar:info-circle-bold" class="align-middle me-1"></iconify-icon>
                                Change order status (syncs to website)
                            </p>
                            <div class="d-grid gap-2">
                                @foreach ($order->status->allowedTransitions() as $transition)
                                    @if ($transition !== \App\Enums\EcommerceOrderStatus::Completed)
                                        <button type="button" class="btn {{ $transition->buttonClass() }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#statusModal-{{ $transition->value }}">
                                            <iconify-icon icon="{{ $transition->icon() }}"
                                                class="align-middle me-1"></iconify-icon>
                                            {{ $transition->label() }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="button" class="btn btn-soft-warning" data-bs-toggle="modal"
                        data-bs-target="#addReminderModal">
                        <iconify-icon icon="solar:alarm-bold-duotone" class="align-middle me-1"></iconify-icon>
                        Set Reminder
                    </button>
                </div>

                @if ($order->notes)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Notes</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-0">{{ $order->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>

    <!-- Add Reminder Modal -->
    <div class="modal fade" id="addReminderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('ecommerce-orders.storeReminder', $order) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:alarm-bold-duotone"
                                class="align-middle me-1 text-warning"></iconify-icon>
                            Set Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="reminder-title" class="form-label">Title <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="title" id="reminder-title" class="form-control"
                                placeholder="e.g. Follow up on delivery" required>
                        </div>
                        <div class="mb-3">
                            <label for="reminder-message" class="form-label">Details</label>
                            <textarea name="message" id="reminder-message" class="form-control" rows="3"
                                placeholder="Additional details about this reminder..."></textarea>
                        </div>
                        <div class="mb-0">
                            <label for="reminder-scheduled-at" class="form-label">Remind At <span
                                    class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" id="reminder-scheduled-at"
                                class="form-control" required min="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">
                            <iconify-icon icon="solar:alarm-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Set Reminder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Status Change Modals (outside the convert form) --}}
    @if ($order->status->canChangeStatus())
        @foreach ($order->status->allowedTransitions() as $transition)
            @if ($transition !== \App\Enums\EcommerceOrderStatus::Completed)
                <div class="modal fade" id="statusModal-{{ $transition->value }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('ecommerce-orders.updateStatus', $order) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $transition->value }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <iconify-icon icon="{{ $transition->icon() }}"
                                            class="align-middle me-1 text-{{ $transition->color() }}"></iconify-icon>
                                        {{ $transition->label() }} Order
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted">
                                        Change order <strong>#{{ $order->order_number }}</strong> status from
                                        <span
                                            class="badge bg-{{ $order->status->color() }}-subtle text-{{ $order->status->color() }}">{{ $order->status->label() }}</span>
                                        to
                                        <span
                                            class="badge bg-{{ $transition->color() }}-subtle text-{{ $transition->color() }}">{{ $transition->label() }}</span>.
                                        This will also update the status on the website.
                                    </p>
                                    <div class="mb-3">
                                        <label for="note-{{ $transition->value }}" class="form-label">Note
                                            (Optional)
                                        </label>
                                        <textarea name="note" id="note-{{ $transition->value }}" class="form-control" rows="3"
                                            placeholder="Add a reason or note for this status change..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn {{ $transition->buttonClass() }}">
                                        <iconify-icon icon="{{ $transition->icon() }}"
                                            class="align-middle me-1"></iconify-icon>
                                        {{ $transition->label() }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif

@endsection

@php
    $convertProducts = $products
        ->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->selling_price,
                'has_variations' => (bool) $product->has_variations,
                'variations' => $product->variations
                    ->map(function ($variation) {
                        return [
                            'id' => $variation->id,
                            'name' => $variation->name,
                            'price' => (float) $variation->selling_price,
                            'sku' => $variation->sku,
                        ];
                    })
                    ->values(),
            ];
        })
        ->values();
@endphp

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const products = @json($convertProducts);
            const currency = @json($order->currency);
            const discountTotal = Number(@json((float) $order->discount_total));
            const taxTotal = Number(@json((float) $order->tax_total));
            const tbody = document.getElementById('order-items-tbody');
            const template = document.getElementById('order-item-row-template');
            const deliveryFeeInput = document.getElementById('delivery_fee');
            let itemIndex = document.querySelectorAll('.order-item-row').length;

            function money(amount) {
                return `${currency} ${Number(amount || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })}`;
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function productLabel(product) {
                return product.sku ? `${product.name} (${product.sku})` : product.name;
            }

            function findProduct(productId) {
                return products.find((product) => String(product.id) === String(productId));
            }

            function populateProductSelect(row) {
                const select = row.querySelector('.product-select');
                const selected = select.dataset.selected || select.value || '';

                select.innerHTML = '<option value="">Select Product</option>' + products.map((product) => {
                    return `<option value="${product.id}">${escapeHtml(productLabel(product))}</option>`;
                }).join('');

                select.value = selected;

                if (selected) {
                    const product = findProduct(selected);
                    if (product) {
                        applyProductToRow(row, product, false);
                    }
                } else {
                    updateVariationSelect(row, null);
                }
            }

            function updateVariationSelect(row, product, selectedVariationId = null) {
                const variationSelect = row.querySelector('.variation-select');
                const selected = selectedVariationId || variationSelect.dataset.selected || variationSelect.value ||
                    '';

                variationSelect.innerHTML = '';

                if (!product || !product.has_variations) {
                    variationSelect.add(new Option('No variation', ''));
                    variationSelect.disabled = true;
                    variationSelect.required = false;
                    variationSelect.value = '';
                    return;
                }

                variationSelect.add(new Option('Select variation', ''));
                product.variations.forEach((variation) => {
                    const option = new Option(
                        `${variation.name}${variation.sku ? ` (${variation.sku})` : ''} - ${money(variation.price)}`,
                        variation.id
                    );
                    option.dataset.price = variation.price;
                    variationSelect.add(option);
                });
                variationSelect.disabled = false;
                variationSelect.required = true;
                variationSelect.value = selected;

                const selectedVariation = product.variations.find((variation) => String(variation.id) === String(
                    selected));
                if (selectedVariation && !row.querySelector('.item-price').value) {
                    row.querySelector('.item-price').value = selectedVariation.price;
                }
            }

            function applyProductToRow(row, product, updatePrice = true) {
                row.querySelector('.product-select').value = product.id;
                row.querySelector('.product-search').value = productLabel(product);
                updateVariationSelect(row, product);

                if (updatePrice && !product.has_variations) {
                    row.querySelector('.item-price').value = product.price;
                }

                updateRowTotal(row);
            }

            function updateRowTotal(row) {
                const quantity = Number(row.querySelector('.item-quantity').value || 0);
                const unitPrice = Number(row.querySelector('.item-price').value || 0);
                const total = quantity * unitPrice;
                const totalInput = row.querySelector('.item-total');

                totalInput.dataset.amount = total;
                totalInput.value = money(total);
                updateSummary();
            }

            function updateSummary() {
                const subtotal = Array.from(document.querySelectorAll('.item-total'))
                    .reduce((sum, input) => sum + Number(input.dataset.amount || 0), 0);
                const shipping = Number(deliveryFeeInput?.value || 0);
                const total = subtotal - discountTotal + taxTotal + shipping;
                const shippingRow = document.getElementById('summary-shipping-row');

                document.getElementById('summary-subtotal').textContent = money(subtotal);
                document.getElementById('summary-shipping').textContent = money(shipping);
                document.getElementById('summary-total').textContent = money(total);

                if (shippingRow) {
                    shippingRow.classList.toggle('d-none', shipping <= 0);
                }
            }

            function renderProductResults(row, searchTerm) {
                const results = row.querySelector('.product-results');
                const term = searchTerm.toLowerCase();

                if (term.length < 2) {
                    results.classList.add('d-none');
                    results.innerHTML = '';
                    return;
                }

                const matches = products.filter((product) => productLabel(product).toLowerCase().includes(term))
                    .slice(0,
                        20);

                if (matches.length === 0) {
                    results.innerHTML = '<div class="p-2 text-muted">No products found</div>';
                    results.classList.remove('d-none');
                    return;
                }

                results.innerHTML = matches.map((product) => `
                    <button type="button" class="dropdown-item product-result-item py-2" data-product-id="${product.id}">
                        <span class="fw-medium">${escapeHtml(productLabel(product))}</span>
                        <small class="text-muted d-block">${money(product.price)}</small>
                    </button>
                `).join('');
                results.classList.remove('d-none');
            }

            function addBlankRow() {
                const html = template.innerHTML.replaceAll('INDEX', itemIndex);
                tbody.insertAdjacentHTML('beforeend', html);
                const row = tbody.querySelector('.order-item-row:last-child');
                itemIndex++;
                populateProductSelect(row);
                updateRowTotal(row);
            }

            document.querySelectorAll('.order-item-row').forEach((row) => {
                populateProductSelect(row);
                updateRowTotal(row);
            });

            document.getElementById('add-order-item').addEventListener('click', addBlankRow);

            document.addEventListener('input', function(event) {
                const row = event.target.closest('.order-item-row');

                if (event.target.classList.contains('product-search') && row) {
                    row.querySelector('.product-select').value = '';
                    updateVariationSelect(row, null);
                    renderProductResults(row, event.target.value);
                    return;
                }

                if ((event.target.classList.contains('item-quantity') || event.target.classList.contains(
                        'item-price')) &&
                    row) {
                    updateRowTotal(row);
                    return;
                }

                if (event.target.id === 'delivery_fee') {
                    updateSummary();
                }
            });

            document.addEventListener('change', function(event) {
                if (!event.target.classList.contains('variation-select')) {
                    return;
                }

                const row = event.target.closest('.order-item-row');
                const selectedOption = event.target.options[event.target.selectedIndex];

                if (selectedOption?.dataset.price) {
                    row.querySelector('.item-price').value = selectedOption.dataset.price;
                    updateRowTotal(row);
                }
            });

            document.addEventListener('click', function(event) {
                const resultItem = event.target.closest('.product-result-item');
                if (resultItem) {
                    const row = resultItem.closest('.order-item-row');
                    const product = findProduct(resultItem.dataset.productId);

                    if (product) {
                        applyProductToRow(row, product);
                    }

                    row.querySelector('.product-results').classList.add('d-none');
                    return;
                }

                if (event.target.closest('.remove-order-item')) {
                    const rows = document.querySelectorAll('.order-item-row');

                    if (rows.length > 1) {
                        event.target.closest('.order-item-row').remove();
                        updateSummary();
                    }
                }

                if (!event.target.closest('.product-search-wrapper')) {
                    document.querySelectorAll('.product-results').forEach((results) => results.classList
                        .add('d-none'));
                }
            });
        });
    </script>
@endpush
