@extends('layouts.pos')

@section('content')
    <div class="pos-wrapper">
        <!-- Left Sidebar - Cart -->
        <div class="pos-sidebar">
            <!-- Header -->
            <div class="pos-header">
                <a href="{{ route('dashboard') }}" class="text-white text-decoration-none d-flex align-items-center">
                    <iconify-icon icon="solar:arrow-left-linear" class="me-2"></iconify-icon>
                </a>
                <span class="logo">{{ config('app.name') }} POS</span>
                <button type="button" class="btn btn-sm btn-outline-light ms-auto" id="btn-fullscreen"
                    title="Toggle Fullscreen">
                    <iconify-icon icon="solar:full-screen-bold" id="fullscreen-icon"></iconify-icon>
                </button>
            </div>

            <!-- Customer Selection -->
            <div class="pos-customer-select">
                <select class="form-select form-select-sm" id="customer_id">
                    <option value="" data-customer-type="retail">Walk-in Customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" data-customer-type="{{ $customer->customer_type }}">
                            {{ $customer->name }} - {{ $customer->phone }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Cart Items -->
            <div class="pos-cart" id="cart-container">
                <div class="pos-cart-empty" id="cart-empty">
                    <iconify-icon icon="solar:cart-large-2-bold-duotone"></iconify-icon>
                    <p>Cart is empty</p>
                    <small>Tap products to add them</small>
                </div>
                <div id="cart-items"></div>
            </div>

            <!-- Totals -->
            <div class="pos-totals">
                <div class="total-row">
                    <span>Items</span>
                    <span id="cart-items-count">0</span>
                </div>
                <div class="total-row grand-total">
                    <span>Subtotal</span>
                    <span id="cart-subtotal">{{ currency_symbol() }}0.00</span>
                </div>
            </div>

            <!-- Payment Actions -->
            <div class="pos-actions">
                <button type="button" class="btn btn-outline-secondary" id="btn-all-payments" disabled>
                    All Payments
                </button>
                <button type="button" class="btn btn-success" id="btn-cash" disabled>
                    Cash
                </button>
            </div>

            <!-- Footer Actions -->
            <div class="pos-footer-actions">
                <div class="pos-footer-btn" id="btn-customer">
                    <iconify-icon icon="solar:user-bold-duotone"></iconify-icon>
                    Customer
                </div>
                <div class="pos-footer-btn" id="btn-notes">
                    <iconify-icon icon="solar:document-text-bold-duotone"></iconify-icon>
                    Notes
                </div>
                <div class="pos-footer-btn" id="btn-more">
                    <iconify-icon icon="solar:menu-dots-bold"></iconify-icon>
                    More
                </div>
            </div>
        </div>

        <!-- Main Content - Products -->
        <div class="pos-main">
            <!-- Search Bar -->
            <div class="pos-search">
                <input type="text" id="product-search" placeholder="Search by item, name, serial #, UPC">
                <button type="button" class="btn btn-light btn-sm">
                    <iconify-icon icon="solar:minimalistic-magnifer-bold-duotone"></iconify-icon>
                </button>
            </div>

            <!-- Category Tabs -->
            <div class="pos-categories" id="category-tabs">
                <button type="button" class="pos-category-btn active" data-category="all">All Items</button>
                @foreach ($categories as $category)
                    <button type="button" class="pos-category-btn" data-category="{{ $category->id }}">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            <!-- Product Grid -->
            <div class="pos-products">
                <div class="pos-product-grid" id="product-grid">
                    @foreach ($products as $product)
                        <div class="pos-product-card {{ $product->stock_quantity <= 0 ? 'out-of-stock' : '' }}"
                            data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}"
                            data-product-sku="{{ $product->sku }}" data-product-price="{{ $product->selling_price }}"
                            data-product-wholesale-price="{{ $product->wholesale_price }}"
                            data-product-stock="{{ $product->stock_quantity }}"
                            data-product-category="{{ $product->category_id }}"
                            data-product-has-variations="{{ $product->has_variations ? 'true' : 'false' }}"
                            data-product-variations='@json($product->variations)'
                            style="{{ $product->stock_quantity <= 0 ? 'display: none;' : '' }}">
                            <div class="product-image">
                                @if ($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                                @else
                                    <iconify-icon icon="solar:box-bold-duotone" class="no-image"></iconify-icon>
                                @endif
                            </div>
                            <div class="product-info">
                                <div class="product-name">{{ $product->name }}</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="product-price">{{ format_currency($product->selling_price) }}</span>
                                    @if ($product->stock_quantity <= 0)
                                        <span class="product-stock text-danger">Out of stock</span>
                                    @else
                                        <span class="product-stock">{{ $product->stock_quantity }} in stock</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Variation Selection Modal -->
    <div class="modal fade" id="variationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Variation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="variation-options"></div>
            </div>
        </div>
    </div>

    <!-- Walk-in Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Walk-in Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="walk-in-name" placeholder="Enter customer name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="walk-in-phone" placeholder="Enter phone number">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Email <span class="text-muted">(Optional)</span></label>
                        <input type="email" class="form-control" id="walk-in-email" placeholder="Enter email address">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-customer">Save Customer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sale Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="sale-notes" rows="4" placeholder="Add notes for this sale..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Save Notes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- More Options Modal -->
    <div class="modal fade" id="moreModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">More Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('sales.index') }}" class="list-group-item list-group-item-action">
                            <iconify-icon icon="solar:list-bold-duotone" class="me-2"></iconify-icon>
                            View All Sales
                        </a>
                        <a href="{{ route('sales.create') }}" class="list-group-item list-group-item-action">
                            <iconify-icon icon="solar:document-add-bold-duotone" class="me-2"></iconify-icon>
                            Classic Sale Form
                        </a>
                        <a href="{{ route('cash-registers.index') }}" class="list-group-item list-group-item-action">
                            <iconify-icon icon="solar:cash-out-bold-duotone" class="me-2"></iconify-icon>
                            Cash Registers
                        </a>
                        <button type="button" class="list-group-item list-group-item-action" id="btn-clear-cart"
                            data-bs-dismiss="modal">
                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone"
                                class="me-2 text-danger"></iconify-icon>
                            Clear Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Source Modal -->
    <div class="modal fade" id="saleSourceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Sale Source</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new-source-name"
                            placeholder="e.g., Instagram, WhatsApp">
                        <div class="invalid-feedback" id="source-name-error"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="new-source-description" rows="2" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-sale-source">
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        Save Source
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Company Modal -->
    <div class="modal fade" id="deliveryCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Delivery Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new-company-name"
                            placeholder="e.g., FedEx, Local Rider">
                        <div class="invalid-feedback" id="company-name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="new-company-phone"
                            placeholder="e.g., +1234567890">
                        <div class="invalid-feedback" id="company-phone-error"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="new-company-contact"
                            placeholder="Optional contact name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-delivery-company">
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        Save Company
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="payment-form" action="{{ route('sales.store') }}" method="POST">
                        @csrf
                        @honeypot
                        <input type="hidden" name="customer_id" id="form-customer-id">
                        <input type="hidden" name="items" id="form-items">
                        <input type="hidden" name="notes" id="form-notes">
                        <input type="hidden" name="walk_in_customer_name" id="form-walk-in-name">
                        <input type="hidden" name="walk_in_customer_phone" id="form-walk-in-phone">
                        <input type="hidden" name="walk_in_customer_email" id="form-walk-in-email">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Sale Source <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select" name="source_id" id="source-select" required>
                                        <option value="">Select source...</option>
                                        @foreach ($saleSources as $source)
                                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" id="btn-add-source">
                                        <iconify-icon icon="solar:add-circle-line-duotone"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" id="payment-method" required>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="credit">Credit (Customer Account)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Delivery Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="delivery_location"
                                    id="delivery-location" placeholder="Enter delivery location" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Delivery Company (Rider)</label>
                                <div class="input-group">
                                    <select class="form-select" name="delivery_company_id" id="delivery-company-select">
                                        <option value="">Select rider...</option>
                                        @foreach ($deliveryCompanies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" id="btn-add-company">
                                        <iconify-icon icon="solar:add-circle-line-duotone"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Discount, Tax & Expenses Section -->
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Discount</label>
                                        <div class="input-group input-group-sm">
                                            <select class="form-select" id="modal-discount-type"
                                                style="max-width: 90px;">
                                                <option value="amount">{{ currency_symbol() }}</option>
                                                <option value="percentage">%</option>
                                            </select>
                                            <input type="number" class="form-control" id="modal-discount-value"
                                                name="discount_amount" min="0" step="0.01" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1"
                                            for="modal-tax-value">{{ config('tax.label', 'VAT') }}</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                            {{-- Once the shop is VAT registered the server derives the tax
                                                 from the lines and ignores anything posted here, so the box
                                                 is shown read-only rather than inviting a figure that will
                                                 be discarded. --}}
                                            <input type="number" class="form-control" id="modal-tax-value"
                                                name="tax_amount" min="0" step="0.01" value="0"
                                                @readonly($vatRegistered ?? false)>
                                        </div>
                                        @if ($vatRegistered ?? false)
                                            <div class="form-text small">{{ __('Calculated automatically.') }}</div>
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Delivery Fee</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                            <input type="number" class="form-control" id="modal-delivery-fee"
                                                name="delivery_fee" min="0" step="0.01" value="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-2 mt-1">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Packaging Fee</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                            <input type="number" class="form-control" id="modal-packaging-fee"
                                                name="packaging_fee" min="0" step="0.01" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Other Expenses</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                            <input type="number" class="form-control" id="modal-other-expenses"
                                                name="other_expenses" min="0" step="0.01" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Expense Notes</label>
                                        <input type="text" class="form-control form-control-sm"
                                            id="modal-expense-notes" name="expense_notes" placeholder="e.g., Express">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_cod" id="is-cod"
                                    value="1">
                                <label class="form-check-label" for="is-cod">
                                    <strong>COD (Cash On Delivery)</strong>
                                    <small class="d-block text-muted">Sale will remain pending until payment is
                                        collected</small>
                                </label>
                            </div>
                        </div>

                        <div class="bg-primary bg-opacity-10 p-3 rounded mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <strong id="modal-subtotal">{{ currency_symbol() }}0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Discount:</span>
                                <strong id="modal-discount-display"
                                    class="text-danger">-{{ currency_symbol() }}0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax:</span>
                                <strong id="modal-tax-display">{{ currency_symbol() }}0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Fees & Expenses:</span>
                                <strong id="modal-expenses-display">{{ currency_symbol() }}0.00</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fs-5">Total:</span>
                                <strong class="fs-5 text-primary" id="modal-total">{{ currency_symbol() }}0.00</strong>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" id="btn-complete-sale">
                                <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                                <iconify-icon icon="solar:check-circle-bold-duotone" class="me-2"></iconify-icon>
                                Complete Sale
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="mb-4">
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="text-success"
                            style="font-size: 80px;"></iconify-icon>
                    </div>
                    <h4 class="mb-2">Sale Completed!</h4>
                    <p class="text-muted mb-1" id="success-invoice">Invoice: #INV-XXXXXXXX</p>
                    <p class="fs-4 fw-bold text-primary mb-4" id="success-total">{{ currency_symbol() }}0.00</p>

                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-outline-primary btn-lg" id="btn-print-receipt">
                            <iconify-icon icon="solar:printer-bold-duotone" class="me-2"></iconify-icon>
                            Print Receipt
                        </button>
                        <button type="button" class="btn btn-success btn-lg" id="btn-new-sale">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="me-2"></iconify-icon>
                            New Sale
                        </button>
                    </div>

                    <div class="mt-3">
                        <a href="#" class="text-muted text-decoration-none" id="link-view-sale">
                            <small>View Sale Details →</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Cart state
        let cart = [];
        let currentCustomerType = 'retail';
        let saleNotes = '';
        let walkInCustomer = {
            name: '',
            phone: '',
            email: ''
        };

        // Currency symbol
        const currencySymbol = '{{ currency_symbol() }}';

        // DOM Elements
        const cartContainer = document.getElementById('cart-items');
        const cartEmpty = document.getElementById('cart-empty');
        const productGrid = document.getElementById('product-grid');
        const categoryTabs = document.getElementById('category-tabs');
        const searchInput = document.getElementById('product-search');

        // Modals
        const variationModal = new bootstrap.Modal(document.getElementById('variationModal'));
        const customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
        const notesModal = new bootstrap.Modal(document.getElementById('notesModal'));
        const moreModal = new bootstrap.Modal(document.getElementById('moreModal'));
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        const saleSourceModal = new bootstrap.Modal(document.getElementById('saleSourceModal'));
        const deliveryCompanyModal = new bootstrap.Modal(document.getElementById('deliveryCompanyModal'));
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));

        // Last sale data for receipt printing
        let lastSaleUuid = null;

        // Customer selection change
        document.getElementById('customer_id').addEventListener('change', function(e) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            currentCustomerType = selectedOption.dataset.customerType || 'retail';
            // Update all cart item prices based on customer type
            cart.forEach(item => {
                if (!item.variationId) {
                    item.price = currentCustomerType === 'wholesale' && item.wholesalePrice ?
                        item.wholesalePrice :
                        item.retailPrice;
                }
            });
            renderCart();
        });

        // Category filtering
        categoryTabs.addEventListener('click', function(e) {
            if (e.target.classList.contains('pos-category-btn')) {
                // Update active state
                categoryTabs.querySelectorAll('.pos-category-btn').forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');

                const categoryId = e.target.dataset.category;
                filterProducts(categoryId, searchInput.value);
            }
        });

        // Product search
        searchInput.addEventListener('input', function(e) {
            const activeCategory = categoryTabs.querySelector('.pos-category-btn.active').dataset.category;
            filterProducts(activeCategory, e.target.value);
        });

        // Filter products by category and search - out of stock only shown when searching
        function filterProducts(categoryId, searchTerm) {
            const cards = productGrid.querySelectorAll('.pos-product-card');
            const searchLower = searchTerm.toLowerCase();
            const isSearching = searchTerm.length > 0;

            cards.forEach(card => {
                const productCategory = card.dataset.productCategory;
                const productName = card.dataset.productName.toLowerCase();
                const productSku = card.dataset.productSku.toLowerCase();
                const isOutOfStock = card.classList.contains('out-of-stock');

                const matchesCategory = categoryId === 'all' || productCategory === categoryId;
                const matchesSearch = !searchTerm || productName.includes(searchLower) || productSku.includes(
                    searchLower);

                // Show out-of-stock items only when searching
                const shouldShow = matchesCategory && matchesSearch && (!isOutOfStock || isSearching);

                card.style.display = shouldShow ? 'block' : 'none';
            });
        }

        // Product click - Add to cart
        productGrid.addEventListener('click', function(e) {
            const card = e.target.closest('.pos-product-card');
            if (!card) return;

            // Allow clicking out-of-stock items (they're only visible when searched)
            const isOutOfStock = card.classList.contains('out-of-stock');
            if (isOutOfStock) {
                alert('This product is out of stock');
                return;
            }

            const productId = card.dataset.productId;
            const hasVariations = card.dataset.productHasVariations === 'true';

            if (hasVariations) {
                showVariationModal(card);
            } else {
                addToCart({
                    id: productId,
                    name: card.dataset.productName,
                    price: currentCustomerType === 'wholesale' ?
                        parseFloat(card.dataset.productWholesalePrice) || parseFloat(card.dataset
                            .productPrice) : parseFloat(card.dataset.productPrice),
                    retailPrice: parseFloat(card.dataset.productPrice),
                    wholesalePrice: parseFloat(card.dataset.productWholesalePrice),
                    quantity: 1,
                    variationId: null,
                    variationName: null
                });
            }
        });

        // Show variation modal
        function showVariationModal(card) {
            const variations = JSON.parse(card.dataset.productVariations);
            const productName = card.dataset.productName;
            const productId = card.dataset.productId;

            const optionsHtml = variations.map(v => `
            <button type="button" class="btn btn-outline-primary m-1 variation-option"
                data-product-id="${productId}"
                data-product-name="${productName}"
                data-variation-id="${v.id}"
                data-variation-name="${v.name}"
                data-variation-price="${v.selling_price}">
                ${v.name} - ${currencySymbol}${parseFloat(v.selling_price).toFixed(2)}
            </button>
        `).join('');

            document.getElementById('variation-options').innerHTML = `
            <p class="mb-3"><strong>${productName}</strong></p>
            <div class="d-flex flex-wrap">${optionsHtml}</div>
        `;

            variationModal.show();
        }

        // Variation selection
        document.getElementById('variation-options').addEventListener('click', function(e) {
            if (e.target.classList.contains('variation-option')) {
                const btn = e.target;
                addToCart({
                    id: btn.dataset.productId,
                    name: btn.dataset.productName,
                    price: parseFloat(btn.dataset.variationPrice),
                    retailPrice: parseFloat(btn.dataset.variationPrice),
                    wholesalePrice: parseFloat(btn.dataset.variationPrice),
                    quantity: 1,
                    variationId: btn.dataset.variationId,
                    variationName: btn.dataset.variationName
                });
                variationModal.hide();
            }
        });

        // Add to cart
        function addToCart(item) {
            const existingIndex = cart.findIndex(i =>
                i.id === item.id && i.variationId === item.variationId
            );

            if (existingIndex > -1) {
                cart[existingIndex].quantity++;
            } else {
                cart.push(item);
            }

            renderCart();
        }

        // Render cart
        function renderCart() {
            if (cart.length === 0) {
                cartEmpty.style.display = 'flex';
                cartContainer.innerHTML = '';
                updateTotals();
                updatePaymentButtons(false);
                return;
            }

            cartEmpty.style.display = 'none';
            updatePaymentButtons(true);

            cartContainer.innerHTML = cart.map((item, index) => `
            <div class="pos-cart-item" data-index="${index}">
                <div class="item-name">${item.name}</div>
                ${item.variationName ? `<div class="item-variant">${item.variationName}</div>` : ''}
                <div class="item-details">
                    <div class="item-qty-controls">
                        <button type="button" class="qty-btn qty-minus" data-index="${index}">−</button>
                        <span class="qty-value">${item.quantity}</span>
                        <button type="button" class="qty-btn qty-plus" data-index="${index}">+</button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="item-price">${currencySymbol}${(item.price * item.quantity).toFixed(2)}</span>
                        <span class="item-remove" data-index="${index}">
                            <iconify-icon icon="solar:trash-bin-minimalistic-linear"></iconify-icon>
                        </span>
                    </div>
                </div>
            </div>
        `).join('');

            updateTotals();
        }

        // Cart item actions
        cartContainer.addEventListener('click', function(e) {
            const index = parseInt(e.target.closest('[data-index]')?.dataset.index);
            if (isNaN(index)) return;

            if (e.target.classList.contains('qty-minus') || e.target.closest('.qty-minus')) {
                if (cart[index].quantity > 1) {
                    cart[index].quantity--;
                } else {
                    cart.splice(index, 1);
                }
                renderCart();
            } else if (e.target.classList.contains('qty-plus') || e.target.closest('.qty-plus')) {
                cart[index].quantity++;
                renderCart();
            } else if (e.target.closest('.item-remove')) {
                cart.splice(index, 1);
                renderCart();
            }
        });

        // Update totals (simplified - just cart subtotal and item count)
        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);

            document.getElementById('cart-subtotal').textContent = currencySymbol + subtotal.toFixed(2);
            document.getElementById('cart-items-count').textContent = itemCount;
        }

        // Update payment buttons
        function updatePaymentButtons(enabled) {
            document.getElementById('btn-all-payments').disabled = !enabled;
            document.getElementById('btn-cash').disabled = !enabled;
        }

        // Customer modal
        document.getElementById('btn-customer').addEventListener('click', function() {
            document.getElementById('walk-in-name').value = walkInCustomer.name;
            document.getElementById('walk-in-phone').value = walkInCustomer.phone;
            document.getElementById('walk-in-email').value = walkInCustomer.email;
            customerModal.show();
        });

        document.getElementById('save-customer').addEventListener('click', function() {
            walkInCustomer.name = document.getElementById('walk-in-name').value.trim();
            walkInCustomer.phone = document.getElementById('walk-in-phone').value.trim();
            walkInCustomer.email = document.getElementById('walk-in-email').value.trim();
            customerModal.hide();
        });

        // Notes modal
        document.getElementById('btn-notes').addEventListener('click', function() {
            document.getElementById('sale-notes').value = saleNotes;
            notesModal.show();
        });

        document.getElementById('sale-notes').addEventListener('input', function(e) {
            saleNotes = e.target.value;
        });

        // More modal
        document.getElementById('btn-more').addEventListener('click', function() {
            moreModal.show();
        });

        // Clear cart
        document.getElementById('btn-clear-cart').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                renderCart();
            }
        });

        // Calculate modal totals (called when values change in payment modal)
        function calculateModalTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            // Get discount
            const discountType = document.getElementById('modal-discount-type').value;
            let discountValue = parseFloat(document.getElementById('modal-discount-value').value) || 0;
            let discountAmount = discountType === 'percentage' ? (subtotal * discountValue) / 100 : discountValue;
            discountAmount = Math.min(discountAmount, subtotal);

            // Get fees
            const taxAmount = parseFloat(document.getElementById('modal-tax-value').value) || 0;
            const deliveryFee = parseFloat(document.getElementById('modal-delivery-fee').value) || 0;
            const packagingFee = parseFloat(document.getElementById('modal-packaging-fee').value) || 0;
            const otherExpenses = parseFloat(document.getElementById('modal-other-expenses').value) || 0;
            const totalExpenses = deliveryFee + packagingFee + otherExpenses;

            const total = Math.max(0, subtotal - discountAmount + taxAmount + totalExpenses);

            // Update display
            document.getElementById('modal-subtotal').textContent = currencySymbol + subtotal.toFixed(2);
            document.getElementById('modal-discount-display').textContent = '-' + currencySymbol + discountAmount.toFixed(
                2);
            document.getElementById('modal-tax-display').textContent = currencySymbol + taxAmount.toFixed(2);
            document.getElementById('modal-expenses-display').textContent = currencySymbol + totalExpenses.toFixed(2);
            document.getElementById('modal-total').textContent = currencySymbol + total.toFixed(2);

            // Update hidden discount field with calculated amount (for percentage)
            if (discountType === 'percentage') {
                document.getElementById('modal-discount-value').dataset.calculatedAmount = discountAmount;
            }
        }

        // Add event listeners for real-time calculation in payment modal
        ['modal-discount-type', 'modal-discount-value', 'modal-tax-value', 'modal-delivery-fee', 'modal-packaging-fee',
            'modal-other-expenses'
        ].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateModalTotals);
            document.getElementById(id).addEventListener('change', calculateModalTotals);
        });

        // Payment - Open modal
        function openPaymentModal(paymentMethod = 'cash') {
            if (cart.length === 0) return;

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            // Reset form values
            document.getElementById('form-customer-id').value = document.getElementById('customer_id').value;
            document.getElementById('form-notes').value = saleNotes;
            document.getElementById('form-walk-in-name').value = walkInCustomer.name;
            document.getElementById('form-walk-in-phone').value = walkInCustomer.phone;
            document.getElementById('form-walk-in-email').value = walkInCustomer.email;
            document.getElementById('payment-method').value = paymentMethod;

            // Reset fee/discount inputs
            document.getElementById('modal-discount-type').value = 'amount';
            document.getElementById('modal-discount-value').value = 0;
            document.getElementById('modal-tax-value').value = 0;
            document.getElementById('modal-delivery-fee').value = 0;
            document.getElementById('modal-packaging-fee').value = 0;
            document.getElementById('modal-other-expenses').value = 0;
            document.getElementById('modal-expense-notes').value = '';

            // Prepare items data
            const itemsData = cart.map((item, index) => ({
                product_id: item.id,
                variation_id: item.variationId || '',
                quantity: item.quantity,
                price: item.price
            }));
            document.getElementById('form-items').value = JSON.stringify(itemsData);

            // Calculate initial totals
            calculateModalTotals();

            paymentModal.show();
        }

        document.getElementById('btn-cash').addEventListener('click', () => openPaymentModal('cash'));
        document.getElementById('btn-all-payments').addEventListener('click', () => openPaymentModal('cash'));

        // Add sale source button
        document.getElementById('btn-add-source').addEventListener('click', function() {
            document.getElementById('new-source-name').value = '';
            document.getElementById('new-source-description').value = '';
            document.getElementById('new-source-name').classList.remove('is-invalid');
            saleSourceModal.show();
        });

        // Save sale source
        document.getElementById('save-sale-source').addEventListener('click', async function() {
            const btn = this;
            const spinner = btn.querySelector('.spinner-border');
            const name = document.getElementById('new-source-name').value.trim();
            const description = document.getElementById('new-source-description').value.trim();

            if (!name) {
                document.getElementById('new-source-name').classList.add('is-invalid');
                document.getElementById('source-name-error').textContent = 'Name is required.';
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
                    const select = document.getElementById('source-select');
                    const option = new Option(data.data.name, data.data.id);
                    select.add(option);
                    select.value = data.data.id;
                    saleSourceModal.hide();
                } else {
                    document.getElementById('new-source-name').classList.add('is-invalid');
                    document.getElementById('source-name-error').textContent = data.message ||
                        'Failed to create source.';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        });

        // Add delivery company button
        document.getElementById('btn-add-company').addEventListener('click', function() {
            document.getElementById('new-company-name').value = '';
            document.getElementById('new-company-phone').value = '';
            document.getElementById('new-company-contact').value = '';
            document.getElementById('new-company-name').classList.remove('is-invalid');
            document.getElementById('new-company-phone').classList.remove('is-invalid');
            deliveryCompanyModal.show();
        });

        // Save delivery company
        document.getElementById('save-delivery-company').addEventListener('click', async function() {
            const btn = this;
            const spinner = btn.querySelector('.spinner-border');
            const name = document.getElementById('new-company-name').value.trim();
            const phone = document.getElementById('new-company-phone').value.trim();
            const contactPerson = document.getElementById('new-company-contact').value.trim();

            let hasError = false;
            if (!name) {
                document.getElementById('new-company-name').classList.add('is-invalid');
                document.getElementById('company-name-error').textContent = 'Company name is required.';
                hasError = true;
            }
            if (!phone) {
                document.getElementById('new-company-phone').classList.add('is-invalid');
                document.getElementById('company-phone-error').textContent = 'Phone is required.';
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
                    const select = document.getElementById('delivery-company-select');
                    const option = new Option(data.data.name, data.data.id);
                    select.add(option);
                    select.value = data.data.id;
                    deliveryCompanyModal.hide();
                } else {
                    alert(data.message || 'Failed to create delivery company.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        });

        // Form submission - use AJAX and show success modal with print option
        document.getElementById('payment-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('btn-complete-sale');
            const spinner = btn.querySelector('.spinner-border');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const itemsJson = document.getElementById('form-items').value;
            const items = JSON.parse(itemsJson);

            // Build form data
            const formData = new FormData(this);

            // Calculate discount amount if percentage type is selected
            const discountType = document.getElementById('modal-discount-type').value;
            if (discountType === 'percentage') {
                const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const discountPct = parseFloat(document.getElementById('modal-discount-value').value) || 0;
                const discountAmount = Math.min((subtotal * discountPct) / 100, subtotal);
                formData.set('discount_amount', discountAmount);
            }

            // Remove the JSON items field and add proper array items
            formData.delete('items');
            items.forEach((item, index) => {
                Object.keys(item).forEach(key => {
                    formData.append(`items[${index}][${key}]`, item[key]);
                });
            });

            try {
                const response = await fetch('{{ route('sales.store') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    lastSaleUuid = data.data.uuid;

                    // Show success modal
                    document.getElementById('success-invoice').textContent = 'Invoice: #' + data.data
                        .invoice_number;
                    document.getElementById('success-total').textContent = currencySymbol + parseFloat(data.data
                        .total_amount).toFixed(2);
                    document.getElementById('link-view-sale').href = data.data.show_url;

                    paymentModal.hide();
                    successModal.show();
                } else {
                    alert(data.message || 'Failed to complete sale. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        });

        // Print receipt button
        document.getElementById('btn-print-receipt').addEventListener('click', function() {
            if (lastSaleUuid) {
                const receiptUrl = `/sales/${lastSaleUuid}/receipt?print=1`;
                window.open(receiptUrl, '_blank', 'width=400,height=600');
            }
        });

        // New sale button - reset everything
        document.getElementById('btn-new-sale').addEventListener('click', function() {
            // Reset cart
            cart = [];
            saleNotes = '';
            walkInCustomer = {
                name: '',
                phone: '',
                email: ''
            };
            lastSaleUuid = null;

            // Reset form
            document.getElementById('customer_id').value = '';
            document.getElementById('source-select').value = '';
            document.getElementById('delivery-location').value = '';
            document.getElementById('delivery-company-select').value = '';
            document.getElementById('is-cod').checked = false;
            document.getElementById('payment-method').value = 'cash';

            // Reset modal inputs
            document.getElementById('modal-discount-type').value = 'amount';
            document.getElementById('modal-discount-value').value = 0;
            document.getElementById('modal-tax-value').value = 0;
            document.getElementById('modal-delivery-fee').value = 0;
            document.getElementById('modal-packaging-fee').value = 0;
            document.getElementById('modal-other-expenses').value = 0;
            document.getElementById('modal-expense-notes').value = '';
            document.getElementById('sale-notes').value = '';

            // Update UI
            renderCart();
            successModal.hide();
        });

        // Initialize - hide out of stock items on page load
        filterProducts('all', '');

        // Fullscreen toggle
        const fullscreenBtn = document.getElementById('btn-fullscreen');
        const fullscreenIcon = document.getElementById('fullscreen-icon');

        function updateFullscreenIcon() {
            fullscreenIcon.setAttribute('icon',
                document.fullscreenElement ? 'solar:quit-full-screen-bold' : 'solar:full-screen-bold'
            );
        }

        fullscreenBtn.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen();
            }
        });

        document.addEventListener('fullscreenchange', updateFullscreenIcon);

        // Auto-enter fullscreen on page load
        document.documentElement.requestFullscreen().catch(() => {});
    </script>
@endpush
