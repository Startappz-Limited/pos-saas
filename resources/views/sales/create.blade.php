@extends('layouts.app')

@section('title', 'Create Sale')

@section('content')

    <div class="mb-3">
        <a href="{{ route('sales.index') }}" class="btn btn-soft-secondary">
            <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon> Back to Sales
        </a>
    </div>

    <form action="{{ route('sales.store') }}" method="POST">
        @csrf
        @honeypot

        <div class="row">
            <div class="col-12 col-lg-8 order-2 order-lg-1">
                <!-- Customer & Delivery Information -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer & Delivery Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Customer Selection -->
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select class="form-select @error('customer_id') is-invalid @enderror" id="customer_id"
                                    name="customer_id">
                                    <option value="" data-customer-type="retail">Walk-in Customer</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}"
                                            data-customer-type="{{ $customer->customer_type }}"
                                            {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }} - {{ $customer->phone }}
                                            ({{ ucfirst($customer->customer_type) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Sale Source -->
                            <div class="col-md-6">
                                <label for="source_id" class="form-label">Sale Source <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select @error('source_id') is-invalid @enderror" id="source_id"
                                        name="source_id" required>
                                        <option value="">Select source...</option>
                                        @foreach ($saleSources as $source)
                                            <option value="{{ $source->id }}"
                                                {{ old('source_id') == $source->id ? 'selected' : '' }}>
                                                {{ $source->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-soft-primary" onclick="openSaleSourceModal()">
                                        <iconify-icon icon="solar:add-circle-line-duotone"></iconify-icon>
                                    </button>
                                </div>
                                @error('source_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Walk-in Customer Details (shown when no customer selected) -->
                            <div class="col-12" id="walk-in-customer-section" style="display: none;">
                                <div class="border rounded p-3 bg-light">
                                    <h6 class="mb-3 text-muted">
                                        <iconify-icon icon="solar:user-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Walk-in Customer Details
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="walk_in_customer_name" class="form-label">Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text"
                                                class="form-control @error('walk_in_customer_name') is-invalid @enderror"
                                                id="walk_in_customer_name" name="walk_in_customer_name"
                                                value="{{ old('walk_in_customer_name') }}"
                                                placeholder="Enter customer name">
                                            @error('walk_in_customer_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="walk_in_customer_phone" class="form-label">Phone <span
                                                    class="text-danger">*</span></label>
                                            <input type="tel"
                                                class="form-control @error('walk_in_customer_phone') is-invalid @enderror"
                                                id="walk_in_customer_phone" name="walk_in_customer_phone"
                                                value="{{ old('walk_in_customer_phone') }}"
                                                placeholder="Enter phone number">
                                            @error('walk_in_customer_phone')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="walk_in_customer_email" class="form-label">Email <span
                                                    class="text-muted">(Optional)</span></label>
                                            <input type="email"
                                                class="form-control @error('walk_in_customer_email') is-invalid @enderror"
                                                id="walk_in_customer_email" name="walk_in_customer_email"
                                                value="{{ old('walk_in_customer_email') }}"
                                                placeholder="Enter email address">
                                            @error('walk_in_customer_email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Location -->
                            <div class="col-md-6">
                                <label for="delivery_location" class="form-label">Delivery Location <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control @error('delivery_location') is-invalid @enderror" id="delivery_location"
                                    name="delivery_location" rows="2" placeholder="Enter delivery address or pickup location..." required>{{ old('delivery_location') }}</textarea>
                                @error('delivery_location')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Delivery Company -->
                            <div class="col-md-6">
                                <label for="delivery_company_id" class="form-label">Delivery Company / Rider</label>
                                <div class="input-group">
                                    <select class="form-select @error('delivery_company_id') is-invalid @enderror"
                                        id="delivery_company_id" name="delivery_company_id">
                                        <option value="">Select delivery company...</option>
                                        @foreach ($deliveryCompanies as $company)
                                            <option value="{{ $company->id }}"
                                                {{ old('delivery_company_id') == $company->id ? 'selected' : '' }}>
                                                {{ $company->name }} - {{ $company->phone }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-soft-primary"
                                        onclick="openDeliveryCompanyModal()">
                                        <iconify-icon icon="solar:add-circle-line-duotone"></iconify-icon>
                                    </button>
                                </div>
                                @error('delivery_company_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sale Items -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sale Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="items-table">
                                <thead class="d-none d-md-table-header-group">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-nowrap" style="min-width: 120px;">Variation</th>
                                        <th class="text-nowrap" style="min-width: 100px;">Quantity</th>
                                        <th class="text-nowrap" style="min-width: 120px;">Price</th>
                                        <th class="text-nowrap" style="min-width: 120px;">Subtotal</th>
                                        <th style="width: 70px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="items-tbody">
                                    <tr class="item-row">
                                        <td data-label="Product">
                                            <div class="product-search-wrapper position-relative">
                                                <input type="text" class="form-control product-search"
                                                    placeholder="Search product by name or SKU..." autocomplete="off"
                                                    style="color: #000 !important; background-color: #fff !important;">
                                                <select name="items[0][product_id]"
                                                    class="form-select product-select d-none" required>
                                                    <option value="">Select Product</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}" data-wholesale-price="{{ $product->wholesale_price }}" data-has-variations="{{ $product->has_variations ? 'true' : 'false' }}" data-variations="{{ $product->variations->toJson() }}">{{ $product->name }} ({{ $product->sku }})</option>
                                                    @endforeach
                                                </select>
                                                <div class="product-results position-absolute bg-white border rounded shadow-sm d-none"
                                                    style="z-index: 1000; max-height: 300px; overflow-y: auto; width: 100%;">
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Variation">
                                            <select name="items[0][variation_id]" class="form-select variation-select"
                                                disabled>
                                                <option value="">No variation</option>
                                            </select>
                                        </td>
                                        <td data-label="Quantity">
                                            <input type="number" name="items[0][quantity]"
                                                class="form-control item-quantity" value="1" min="1"
                                                required>
                                        </td>
                                        <td data-label="Price">
                                            <input type="number" name="items[0][price]" class="form-control item-price"
                                                step="0.01" min="0" required>
                                        </td>
                                        <td data-label="Subtotal">
                                            <input type="text" class="form-control item-subtotal" readonly>
                                        </td>
                                        <td data-label="Action" class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger remove-item">
                                                <iconify-icon icon="solar:trash-bin-minimalistic-linear"></iconify-icon>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="add-item">
                            <iconify-icon icon="solar:add-circle-line-duotone" class="align-middle me-1"></iconify-icon>
                            Add
                            Item
                        </button>
                    </div>
                </div>

                <!-- Additional Expenses -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title mb-3">
                            <iconify-icon icon="solar:wallet-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Additional Expenses
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="delivery_fee" class="form-label">Delivery Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" name="delivery_fee" id="delivery_fee"
                                        class="form-control expense-input" value="0" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="packaging_fee" class="form-label">Packaging Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" name="packaging_fee" id="packaging_fee"
                                        class="form-control expense-input" value="0" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="other_expenses" class="form-label">Other Expenses</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" name="other_expenses" id="other_expenses"
                                        class="form-control expense-input" value="0" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="expense_notes" class="form-label">Expense Notes</label>
                                <input type="text" name="expense_notes" id="expense_notes" class="form-control"
                                    placeholder="e.g., Express delivery">
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <iconify-icon icon="solar:info-circle-bold" class="align-middle me-1"></iconify-icon>
                            <small>These expenses will be added to the total amount payable by the customer.</small>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Additional Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4 order-1 order-lg-2 mb-3 mb-lg-0">
                <!-- Sale Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sale Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">Subtotal:</td>
                                        <td class="text-end fw-semibold" id="summary-subtotal">{{ currency_symbol() }}0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">
                                            <label for="discount_amount">Discount:</label>
                                        </td>
                                        <td class="text-end">
                                            <input type="number" name="discount_amount" id="discount_amount"
                                                class="form-control form-control-sm text-end" value="0"
                                                step="0.01" min="0">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">
                                            <label for="tax_amount">{{ config('tax.label', 'VAT') }}:</label>
                                            @if ($vatRegistered ?? false)
                                                <div class="form-text small mb-0">
                                                    {{ __('Calculated automatically.') }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{-- Read-only for a VAT-registered shop: the server derives the
                                                 tax from the lines and discards anything posted here. --}}
                                            <input type="number" name="tax_amount" id="tax_amount"
                                                class="form-control form-control-sm text-end" value="0"
                                                step="0.01" min="0" @readonly($vatRegistered ?? false)>
                                        </td>
                                    </tr>
                                    <tr id="expense-row" style="display: none;">
                                        <td class="text-muted">
                                            <small>Additional Expenses:</small>
                                        </td>
                                        <td class="text-end">
                                            <small class="text-muted" id="summary-expenses">{{ currency_symbol() }}0.00</small>
                                        </td>
                                    </tr>
                                    <tr class="border-top">
                                        <th class="fs-16">Total Payable:</th>
                                        <th class="text-end fs-16 text-primary" id="summary-total">{{ currency_symbol() }}0.00</th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Payment Method</h6>
                        <select name="payment_method" id="payment_method"
                            class="form-select @error('payment_method') is-invalid @enderror" required>
                            <option value="cash" {{ old('payment_method', 'cash') == 'cash' ? 'selected' : '' }}>Cash
                            </option>
                            <option value="card" {{ old('payment_method') == 'card' ? 'selected' : '' }}>Card</option>
                            <option value="bank_transfer"
                                {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="mobile_money" {{ old('payment_method') == 'mobile_money' ? 'selected' : '' }}>
                                Mobile Money</option>
                            <option value="credit" {{ old('payment_method') == 'credit' ? 'selected' : '' }}>Credit
                                (Customer Account)</option>
                        </select>
                        @error('payment_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="is_cod" id="is_cod"
                                value="1" {{ old('is_cod') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_cod">
                                <strong>COD (Cash On Delivery)</strong>
                                <small class="d-block text-muted">Sale will remain pending until payment is
                                    collected</small>
                            </label>
                        </div>
                        @error('is_cod')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Actions -->
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                    class="align-middle me-1"></iconify-icon> Create Sale
                            </button>
                            <a href="{{ route('sales.index') }}" class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Sale Source Modal -->
    <x-ui-modal id="saleSourceModal" title="Add New Sale Source" size="md">
        <x-slot:body>
            <form id="saleSourceForm">
                <div class="mb-3">
                    <label for="source_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="source_name" name="name"
                        placeholder="e.g., Instagram, WhatsApp, Walk-in" required>
                    <div class="invalid-feedback" id="source_name_error"></div>
                </div>
                <div class="mb-0">
                    <label for="source_description" class="form-label">Description</label>
                    <textarea class="form-control" id="source_description" name="description" rows="2"
                        placeholder="Optional description for this source"></textarea>
                </div>
            </form>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveSaleSourceBtn">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Save Source
            </button>
        </x-slot:footer>
    </x-ui-modal>

    <!-- Delivery Company Modal -->
    <x-ui-modal id="deliveryCompanyModal" title="Add New Delivery Company" size="md">
        <x-slot:body>
            <form id="deliveryCompanyForm">
                <div class="mb-3">
                    <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="company_name" name="name"
                        placeholder="e.g., FedEx, Local Rider" required>
                    <div class="invalid-feedback" id="company_name_error"></div>
                </div>
                <div class="mb-3">
                    <label for="company_phone" class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="company_phone" name="phone"
                        placeholder="e.g., +1234567890" required>
                    <div class="invalid-feedback" id="company_phone_error"></div>
                </div>
                <div class="mb-0">
                    <label for="company_contact_person" class="form-label">Contact Person</label>
                    <input type="text" class="form-control" id="company_contact_person" name="contact_person"
                        placeholder="Optional contact person name">
                </div>
            </form>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveDeliveryCompanyBtn">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Save Company
            </button>
        </x-slot:footer>
    </x-ui-modal>

    @push('styles')
        <style>
            .product-search-wrapper {
                position: relative;
            }

            .product-search {
                color: #000 !important;
                background-color: #fff !important;
                opacity: 1 !important;
                -webkit-text-fill-color: #000 !important;
                text-overflow: ellipsis;
                overflow: hidden;
                white-space: nowrap;
            }

            .product-search:focus {
                color: #000 !important;
                background-color: #fff !important;
            }

            .product-search::placeholder {
                color: #6c757d !important;
                opacity: 0.6;
            }

            .product-results {
                top: 100%;
                left: 0;
                margin-top: 2px;
            }

            /* Mobile responsive table styles */
            @media (max-width: 767.98px) {
                #items-table tbody tr {
                    display: block;
                    margin-bottom: 1rem;
                    border: 1px solid #dee2e6;
                    border-radius: 0.5rem;
                    padding: 0.75rem;
                    background: #fff;
                }

                #items-table tbody td {
                    display: block;
                    width: 100% !important;
                    border: none;
                    padding: 0.5rem 0;
                    text-align: left !important;
                }

                #items-table tbody td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    display: block;
                    margin-bottom: 0.25rem;
                    color: #495057;
                    font-size: 0.875rem;
                }

                #items-table tbody td[data-label="Action"] {
                    text-align: right !important;
                    padding-top: 0.75rem;
                    border-top: 1px solid #dee2e6;
                    margin-top: 0.5rem;
                }

                #items-table tbody td[data-label="Action"]::before {
                    display: none;
                }

                /* Hide table borders on mobile */
                #items-table,
                #items-table tbody {
                    border: none;
                }

                /* Ensure inputs are full width on mobile */
                #items-table .form-control,
                #items-table .form-select {
                    width: 100%;
                }

                /* Stack quantity, price and subtotal in a row on mobile */
                .item-row {
                    position: relative;
                }

                /* Make the product results dropdown work better on mobile */
                .product-results {
                    position: fixed !important;
                    left: 1rem !important;
                    right: 1rem !important;
                    width: calc(100% - 2rem) !important;
                    max-height: 50vh !important;
                }
            }

            /* Tablet responsive adjustments */
            @media (min-width: 768px) and (max-width: 991.98px) {

                #items-table th,
                #items-table td {
                    padding: 0.5rem;
                    font-size: 0.875rem;
                }

                #items-table .form-control,
                #items-table .form-select {
                    padding: 0.375rem 0.5rem;
                    font-size: 0.875rem;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            let itemIndex = 1;
            let currentCustomerType = 'retail'; // Default to retail pricing

            // Listen for customer type changes
            document.getElementById('customer_id').addEventListener('change', function(e) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                currentCustomerType = selectedOption.dataset.customerType || 'retail';

                // Update all existing product prices
                updateAllProductPrices();
            });

            // Update prices for all products in the cart based on customer type
            function updateAllProductPrices() {
                document.querySelectorAll('.item-row').forEach(function(row) {
                    const productSelect = row.querySelector('.product-select');
                    const variationSelect = row.querySelector('.variation-select');

                    // If a variation is selected, don't update (variation price takes precedence)
                    if (variationSelect && variationSelect.value) {
                        return;
                    }

                    // Update product price based on customer type
                    if (productSelect.value) {
                        const selectedOption = productSelect.options[productSelect.selectedIndex];
                        row.querySelector('.item-price').value = getPrice(selectedOption).toFixed(2);
                        updateRowSubtotal(row);
                    }
                });
            }

            // Get price based on customer type. A wholesale price of NULL (never
            // set) or 0 is not a real price — fall back to retail rather than
            // ringing the customer up at nothing.
            function getPrice(element) {
                const retail = parseFloat(element.dataset.price) || 0;

                if (currentCustomerType !== 'wholesale') {
                    return retail;
                }

                return parseFloat(element.dataset.wholesalePrice) || retail;
            }

            // Product search functionality
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('product-search')) {
                    const searchTerm = e.target.value.toLowerCase();
                    const row = e.target.closest('.item-row');
                    const resultsDiv = row.querySelector('.product-results');
                    const productSelect = row.querySelector('.product-select');

                    if (searchTerm.length < 2) {
                        resultsDiv.classList.add('d-none');
                        return;
                    }

                    const options = Array.from(productSelect.options);
                    const matches = options.filter(option => {
                        if (!option.value) return false;
                        return option.textContent.toLowerCase().includes(searchTerm);
                    });

                    if (matches.length > 0) {
                        resultsDiv.innerHTML = matches.map(option => {
                            const displayPrice = getPrice(option);
                            const productText = option.textContent.trim();
                            return `
                        <div class="product-result-item p-2 border-bottom" style="cursor: pointer;" 
                             data-value="${option.value}"
                             data-price="${option.dataset.price}"
                             data-wholesale-price="${option.dataset.wholesalePrice}"
                             data-has-variations="${option.dataset.hasVariations}"
                             data-variations='${option.dataset.variations}'
                             data-text="${productText}">
                            <div class="fw-medium">${productText}</div>
                            <small class="text-muted">$${displayPrice.toFixed(2)}</small>
                        </div>
                    `;
                        }).join('');
                        resultsDiv.classList.remove('d-none');
                    } else {
                        resultsDiv.innerHTML = '<div class="p-2 text-muted">No products found</div>';
                        resultsDiv.classList.remove('d-none');
                    }
                }

                if (e.target.classList.contains('item-quantity') || e.target.classList.contains('item-price')) {
                    updateRowSubtotal(e.target.closest('.item-row'));
                }
                if (e.target.id === 'discount_amount' || e.target.id === 'tax_amount') {
                    updateSummary();
                }
            });

            // Select product from search results
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('product-result-item') || e.target.closest('.product-result-item')) {
                    const item = e.target.classList.contains('product-result-item') ? e.target : e.target.closest(
                        '.product-result-item');
                    const row = item.closest('.item-row');
                    const productSelect = row.querySelector('.product-select');
                    const variationSelect = row.querySelector('.variation-select');
                    const searchInput = row.querySelector('.product-search');
                    const resultsDiv = row.querySelector('.product-results');

                    // Set the product
                    productSelect.value = item.dataset.value;
                    const productText = (item.dataset.text || item.textContent).trim();
                    resultsDiv.classList.add('d-none');

                    // Set the value with explicit styling
                    searchInput.value = productText;
                    searchInput.setAttribute('value', productText);
                    searchInput.style.color = '#000';
                    searchInput.style.backgroundColor = '#fff';
                    searchInput.blur();

                    // Handle variations
                    if (item.dataset.hasVariations === 'true') {
                        const variations = JSON.parse(item.dataset.variations);
                        variationSelect.innerHTML = '<option value="">Select variation</option>';
                        variations.forEach(variation => {
                            const option = new Option(
                                `${variation.name} - $${parseFloat(variation.selling_price).toFixed(2)}`,
                                variation.id
                            );
                            option.dataset.price = variation.selling_price;
                            variationSelect.add(option);
                        });
                        variationSelect.disabled = false;
                        variationSelect.required = true;
                    } else {
                        variationSelect.innerHTML = '<option value="">No variation</option>';
                        variationSelect.disabled = true;
                        variationSelect.required = false;

                        // Use customer-type-appropriate price
                        row.querySelector('.item-price').value = getPrice(item).toFixed(2);
                        updateRowSubtotal(row);
                    }
                }

                if (e.target.closest('.remove-item')) {
                    const rows = document.querySelectorAll('.item-row');
                    if (rows.length > 1) {
                        e.target.closest('.item-row').remove();
                        updateSummary();
                    }
                }
            });

            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.classList.contains('product-search') && !e.target.classList.contains(
                        'product-result-item')) {
                    document.querySelectorAll('.product-results').forEach(div => div.classList.add('d-none'));
                }
            });

            // Update price when variation is selected
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('variation-select')) {
                    const row = e.target.closest('.item-row');
                    const price = e.target.options[e.target.selectedIndex].dataset.price;
                    if (price) {
                        row.querySelector('.item-price').value = price;
                        updateRowSubtotal(row);
                    }
                }
            });

            // Add new item row
            document.getElementById('add-item').addEventListener('click', function() {
                const tbody = document.getElementById('items-tbody');
                const newRow = document.querySelector('.item-row').cloneNode(true);

                // Update name attributes and clear values
                newRow.querySelectorAll('input, select').forEach(function(input) {
                    const name = input.getAttribute('name');
                    if (name) {
                        input.setAttribute('name', name.replace('[0]', `[${itemIndex}]`));
                    }
                    if (input.classList.contains('item-quantity')) {
                        input.value = 1;
                    } else if (input.classList.contains('product-search')) {
                        input.value = '';
                        input.style.color = '#000';
                        input.style.backgroundColor = '#fff';
                    } else if (input.classList.contains('variation-select')) {
                        input.innerHTML = '<option value="">No variation</option>';
                        input.disabled = true;
                        input.required = false;
                    } else if (!input.classList.contains('item-subtotal')) {
                        input.value = '';
                    }
                });

                // Hide results div
                newRow.querySelector('.product-results').classList.add('d-none');

                tbody.appendChild(newRow);
                itemIndex++;
                updateSummary();
            });

            // Calculate row subtotal
            function updateRowSubtotal(row) {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const subtotal = quantity * price;
                row.querySelector('.item-subtotal').value = '{{ currency_symbol() }}' + subtotal.toFixed(2);
                updateSummary();
            }

            // Update sale summary
            function updateSummary() {
                let subtotal = 0;
                document.querySelectorAll('.item-row').forEach(function(row) {
                    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                    const price = parseFloat(row.querySelector('.item-price').value) || 0;
                    subtotal += quantity * price;
                });

                const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
                const tax = parseFloat(document.getElementById('tax_amount').value) || 0;

                // Calculate total expenses
                const deliveryFee = parseFloat(document.getElementById('delivery_fee').value) || 0;
                const packagingFee = parseFloat(document.getElementById('packaging_fee').value) || 0;
                const otherExpenses = parseFloat(document.getElementById('other_expenses').value) || 0;
                const totalExpenses = deliveryFee + packagingFee + otherExpenses;

                // Show/hide expense row
                const expenseRow = document.getElementById('expense-row');
                if (totalExpenses > 0) {
                    expenseRow.style.display = 'table-row';
                    document.getElementById('summary-expenses').textContent = '{{ currency_symbol() }}' + totalExpenses.toFixed(2);
                } else {
                    expenseRow.style.display = 'none';
                }

                const total = subtotal - discount + tax + totalExpenses;

                document.getElementById('summary-subtotal').textContent = '{{ currency_symbol() }}' + subtotal.toFixed(2);
                document.getElementById('summary-total').textContent = '{{ currency_symbol() }}' + total.toFixed(2);
            }

            // Listen for expense changes
            document.querySelectorAll('.expense-input').forEach(function(input) {
                input.addEventListener('input', updateSummary);
            });

            // Handle walk-in customer section visibility
            const customerSelect = document.getElementById('customer_id');
            const walkInSection = document.getElementById('walk-in-customer-section');
            const walkInNameInput = document.getElementById('walk_in_customer_name');
            const walkInPhoneInput = document.getElementById('walk_in_customer_phone');

            function toggleWalkInSection() {
                const isWalkIn = !customerSelect.value || customerSelect.value === '';

                if (isWalkIn) {
                    walkInSection.style.display = 'block';
                    walkInNameInput.required = true;
                    walkInPhoneInput.required = true;
                } else {
                    walkInSection.style.display = 'none';
                    walkInNameInput.required = false;
                    walkInPhoneInput.required = false;
                    // Clear walk-in fields when customer is selected
                    walkInNameInput.value = '';
                    walkInPhoneInput.value = '';
                    document.getElementById('walk_in_customer_email').value = '';
                }
            }

            // Initialize on page load
            toggleWalkInSection();

            // Listen for customer changes
            customerSelect.addEventListener('change', toggleWalkInSection);

            // Modal instances
            const saleSourceModal = new bootstrap.Modal(document.getElementById('saleSourceModal'));
            const deliveryCompanyModal = new bootstrap.Modal(document.getElementById('deliveryCompanyModal'));

            // Open Sale Source Modal
            function openSaleSourceModal() {
                document.getElementById('saleSourceForm').reset();
                clearValidationErrors('saleSourceForm');
                saleSourceModal.show();
            }

            // Open Delivery Company Modal
            function openDeliveryCompanyModal() {
                document.getElementById('deliveryCompanyForm').reset();
                clearValidationErrors('deliveryCompanyForm');
                deliveryCompanyModal.show();
            }

            // Clear validation errors
            function clearValidationErrors(formId) {
                const form = document.getElementById(formId);
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
            }

            // Show validation error
            function showValidationError(inputId, errorId, message) {
                const input = document.getElementById(inputId);
                const error = document.getElementById(errorId);
                input.classList.add('is-invalid');
                error.textContent = message;
            }

            // Save Sale Source
            document.getElementById('saveSaleSourceBtn').addEventListener('click', async function() {
                const btn = this;
                const spinner = btn.querySelector('.spinner-border');
                const name = document.getElementById('source_name').value.trim();
                const description = document.getElementById('source_description').value.trim();

                clearValidationErrors('saleSourceForm');

                if (!name) {
                    showValidationError('source_name', 'source_name_error', 'Name is required.');
                    return;
                }

                btn.disabled = true;
                spinner.classList.remove('d-none');

                try {
                    const response = await fetch('{{ route('sales.storeSaleSource') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            name,
                            description
                        })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Add new option to select
                        const select = document.getElementById('source_id');
                        const option = new Option(data.data.name, data.data.id);
                        select.add(option);
                        select.value = data.data.id;

                        saleSourceModal.hide();

                        // Show success toast/alert
                        showToast('success', data.message);
                    } else {
                        if (data.errors) {
                            Object.keys(data.errors).forEach(field => {
                                if (field === 'name') {
                                    showValidationError('source_name', 'source_name_error', data.errors[
                                        field][0]);
                                }
                            });
                        } else {
                            showToast('error', data.message || 'Failed to create sale source.');
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                } finally {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                }
            });

            // Save Delivery Company
            document.getElementById('saveDeliveryCompanyBtn').addEventListener('click', async function() {
                const btn = this;
                const spinner = btn.querySelector('.spinner-border');
                const name = document.getElementById('company_name').value.trim();
                const phone = document.getElementById('company_phone').value.trim();
                const contactPerson = document.getElementById('company_contact_person').value.trim();

                clearValidationErrors('deliveryCompanyForm');

                let hasError = false;
                if (!name) {
                    showValidationError('company_name', 'company_name_error', 'Company name is required.');
                    hasError = true;
                }
                if (!phone) {
                    showValidationError('company_phone', 'company_phone_error', 'Phone is required.');
                    hasError = true;
                }
                if (hasError) return;

                btn.disabled = true;
                spinner.classList.remove('d-none');

                try {
                    const response = await fetch('{{ route('sales.storeDeliveryCompany') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            name,
                            phone,
                            contact_person: contactPerson
                        })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Add new option to select
                        const select = document.getElementById('delivery_company_id');
                        const option = new Option(`${data.data.name} - ${data.data.phone}`, data.data.id);
                        select.add(option);
                        select.value = data.data.id;

                        deliveryCompanyModal.hide();

                        // Show success toast/alert
                        showToast('success', data.message);
                    } else {
                        if (data.errors) {
                            Object.keys(data.errors).forEach(field => {
                                if (field === 'name') {
                                    showValidationError('company_name', 'company_name_error', data.errors[
                                        field][0]);
                                }
                                if (field === 'phone') {
                                    showValidationError('company_phone', 'company_phone_error', data.errors[
                                        field][0]);
                                }
                            });
                        } else {
                            showToast('error', data.message || 'Failed to create delivery company.');
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                } finally {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                }
            });

            // Simple toast notification function
            function showToast(type, message) {
                // Check if toastr is available
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                } else {
                    // Fallback to simple alert style notification
                    const alertDiv = document.createElement('div');
                    alertDiv.className =
                        `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
                    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                    alertDiv.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    setTimeout(() => alertDiv.remove(), 5000);
                }
            }
        </script>
    @endpush
@endsection
