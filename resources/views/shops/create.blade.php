@extends('layouts.app')

@section('title', 'Create Shop')

@section('content')
    <form action="{{ route('shops.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:shop-2-bold-duotone"
                                class="align-middle text-primary me-2"></iconify-icon>
                            Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Shop Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="code" class="form-label">Shop Code</label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror"
                                    id="code" name="code" value="{{ old('code') }}" placeholder="e.g., SHOP-001"
                                    >
                                <div class="form-text">
                                    Enter a unique shop code using uppercase letters, numbers, and hyphens.
                                    Leave this field empty to have one generated automatically.
                                </div>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:map-point-bold-duotone"
                                class="align-middle text-success me-2"></iconify-icon>
                            Location Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="address" class="form-label">Street Address</label>
                                <input type="text" class="form-control @error('address') is-invalid @enderror"
                                    id="address" name="address" value="{{ old('address') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control @error('city') is-invalid @enderror"
                                    id="city" name="city" value="{{ old('city') }}">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control @error('state') is-invalid @enderror"
                                    id="state" name="state" value="{{ old('state') }}">
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control @error('postal_code') is-invalid @enderror"
                                    id="postal_code" name="postal_code" value="{{ old('postal_code') }}">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control @error('country') is-invalid @enderror"
                                    id="country" name="country" value="{{ old('country', 'Kenya') }}" required>
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Coordinates (Optional)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control @error('latitude') is-invalid @enderror"
                                        name="latitude" placeholder="Latitude" value="{{ old('latitude') }}">
                                    <input type="text" class="form-control @error('longitude') is-invalid @enderror"
                                        name="longitude" placeholder="Longitude" value="{{ old('longitude') }}">
                                </div>
                                <div class="form-text">GPS coordinates for map integration</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:phone-bold-duotone"
                                class="align-middle text-info me-2"></iconify-icon>
                            Contact Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                    id="phone" name="phone" value="{{ old('phone') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" value="{{ old('email') }}">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                @include('shops.partials.tax-settings', ['shop' => null])

                <!-- E-Commerce Integration -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:cart-large-2-bold-duotone"
                                class="align-middle text-danger me-2"></iconify-icon>
                            E-Commerce Integration
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="integrationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="woocommerce-tab" data-bs-toggle="tab"
                                    data-bs-target="#woocommerce" type="button" role="tab">
                                    <iconify-icon icon="logos:woocommerce-icon" class="align-middle me-1"></iconify-icon>
                                    WooCommerce
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="shopify-tab" data-bs-toggle="tab" data-bs-target="#shopify"
                                    type="button" role="tab">
                                    <iconify-icon icon="logos:shopify" class="align-middle me-1"></iconify-icon>
                                    Shopify
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3" id="integrationTabsContent">
                            <!-- WooCommerce Tab -->
                            <div class="tab-pane fade show active" id="woocommerce" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="woocommerce_enabled" name="integrations[woocommerce][enabled]"
                                                value="1"
                                                {{ old('integrations.woocommerce.enabled') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="woocommerce_enabled">
                                                Enable WooCommerce Integration
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="woocommerce_store_url" class="form-label">Store URL</label>
                                        <input type="url"
                                            class="form-control @error('integrations.woocommerce.store_url') is-invalid @enderror"
                                            id="woocommerce_store_url" name="integrations[woocommerce][store_url]"
                                            value="{{ old('integrations.woocommerce.store_url') }}"
                                            placeholder="https://your-store.com">
                                        @error('integrations.woocommerce.store_url')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="woocommerce_consumer_key" class="form-label">Consumer Key</label>
                                        <input type="text"
                                            class="form-control @error('integrations.woocommerce.consumer_key') is-invalid @enderror"
                                            id="woocommerce_consumer_key" name="integrations[woocommerce][consumer_key]"
                                            value="{{ old('integrations.woocommerce.consumer_key') }}"
                                            placeholder="ck_xxxxxxxxxxxxxxxxxxxxx">
                                        @error('integrations.woocommerce.consumer_key')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="woocommerce_consumer_secret" class="form-label">Consumer
                                            Secret</label>
                                        <input type="password"
                                            class="form-control @error('integrations.woocommerce.consumer_secret') is-invalid @enderror"
                                            id="woocommerce_consumer_secret"
                                            name="integrations[woocommerce][consumer_secret]"
                                            value="{{ old('integrations.woocommerce.consumer_secret') }}"
                                            placeholder="cs_xxxxxxxxxxxxxxxxxxxxx">
                                        @error('integrations.woocommerce.consumer_secret')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="test_woocommerce_connection">
                                            <iconify-icon icon="solar:refresh-circle-bold-duotone"
                                                class="align-middle me-1"></iconify-icon>
                                            Test Connection
                                        </button>
                                        <div id="woocommerce_status" class="mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shopify Tab -->
                            <div class="tab-pane fade" id="shopify" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="shopify_enabled" name="integrations[shopify][enabled]" value="1"
                                                {{ old('integrations.shopify.enabled') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="shopify_enabled">
                                                Enable Shopify Integration
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="shopify_shop_domain" class="form-label">Shop Domain</label>
                                        <input type="text"
                                            class="form-control @error('integrations.shopify.shop_domain') is-invalid @enderror"
                                            id="shopify_shop_domain" name="integrations[shopify][shop_domain]"
                                            value="{{ old('integrations.shopify.shop_domain') }}"
                                            placeholder="your-store.myshopify.com">
                                        @error('integrations.shopify.shop_domain')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="shopify_access_token" class="form-label">Access Token</label>
                                        <input type="password"
                                            class="form-control @error('integrations.shopify.access_token') is-invalid @enderror"
                                            id="shopify_access_token" name="integrations[shopify][access_token]"
                                            value="{{ old('integrations.shopify.access_token') }}"
                                            placeholder="shpat_xxxxxxxxxxxxxxxxxxxxx">
                                        @error('integrations.shopify.access_token')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="test_shopify_connection">
                                            <iconify-icon icon="solar:refresh-circle-bold-duotone"
                                                class="align-middle me-1"></iconify-icon>
                                            Test Connection
                                        </button>
                                        <div id="shopify_status" class="mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                            <small>Connect your e-commerce platforms to sync products and orders. Data synchronization
                                will be available in a future update.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Status & Manager -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:settings-bold-duotone"
                                class="align-middle text-warning me-2"></iconify-icon>
                            Shop Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status"
                                name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('status', 'active') == $value ? 'selected' : '' }}>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="manager_id" class="form-label">Shop Manager</label>
                            <select class="form-select @error('manager_id') is-invalid @enderror" id="manager_id"
                                name="manager_id">
                                <option value="">Select Manager (Optional)</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}"
                                        {{ old('manager_id') == $manager->id ? 'selected' : '' }}>
                                        {{ $manager->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Assign a manager to oversee this shop</div>
                            @error('manager_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <iconify-icon icon="solar:check-circle-line-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Create Shop
                            </button>
                            <a href="{{ route('shops.index') }}" class="btn btn-soft-secondary">
                                <iconify-icon icon="solar:close-circle-line-duotone"
                                    class="align-middle me-1"></iconify-icon>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card border-primary border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <iconify-icon icon="solar:info-circle-bold-duotone"
                                class="text-primary fs-20 me-2"></iconify-icon>
                            <h6 class="mb-0">Quick Tips</h6>
                        </div>
                        <ul class="mb-0 ps-3">
                            <li class="mb-1"><small>Shop code must be unique</small></li>
                            <li class="mb-1"><small>Set status to "Active" to enable operations</small></li>
                            <li class="mb-1"><small>Manager assignment can be changed later</small></li>
                            <li class="mb-1"><small>Coordinates help with location services</small></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Test WooCommerce Connection
            document.getElementById('test_woocommerce_connection').addEventListener('click', function() {
                testConnection('woocommerce', {
                    store_url: document.getElementById('woocommerce_store_url').value,
                    consumer_key: document.getElementById('woocommerce_consumer_key').value,
                    consumer_secret: document.getElementById('woocommerce_consumer_secret').value
                });
            });

            // Test Shopify Connection
            document.getElementById('test_shopify_connection').addEventListener('click', function() {
                testConnection('shopify', {
                    shop_domain: document.getElementById('shopify_shop_domain').value,
                    access_token: document.getElementById('shopify_access_token').value
                });
            });

            function testConnection(platform, credentials) {
                const button = document.getElementById(`test_${platform}_connection`);
                const statusDiv = document.getElementById(`${platform}_status`);

                // Show loading state
                button.disabled = true;
                button.innerHTML =
                    '<iconify-icon icon="svg-spinners:ring-resize" class="align-middle me-1"></iconify-icon> Testing...';
                statusDiv.style.display = 'none';

                // Send AJAX request
                fetch('{{ route('shops.testIntegration') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            platform: platform,
                            credentials: credentials
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Reset button
                        button.disabled = false;
                        button.innerHTML =
                            '<iconify-icon icon="solar:refresh-circle-bold-duotone" class="align-middle me-1"></iconify-icon> Test Connection';

                        // Show status
                        statusDiv.style.display = 'block';
                        if (data.connected) {
                            statusDiv.className = 'alert alert-success py-2 mb-0 mt-2';
                            statusDiv.innerHTML =
                                '<iconify-icon icon="solar:check-circle-bold" class="align-middle me-1"></iconify-icon> ' +
                                data.message;
                            if (data.details) {
                                statusDiv.innerHTML += '<div class="mt-1"><small>';
                                if (data.details.store_name) statusDiv.innerHTML +=
                                    `Store: ${data.details.store_name}<br>`;
                                if (data.details.currency) statusDiv.innerHTML +=
                                    `Currency: ${data.details.currency}`;
                                if (data.details.email) statusDiv.innerHTML += `Email: ${data.details.email}`;
                                statusDiv.innerHTML += '</small></div>';
                            }
                        } else {
                            statusDiv.className = 'alert alert-danger py-2 mb-0 mt-2';
                            statusDiv.innerHTML =
                                '<iconify-icon icon="solar:close-circle-bold" class="align-middle me-1"></iconify-icon> ' +
                                data.message;
                        }
                    })
                    .catch(error => {
                        // Reset button
                        button.disabled = false;
                        button.innerHTML =
                            '<iconify-icon icon="solar:refresh-circle-bold-duotone" class="align-middle me-1"></iconify-icon> Test Connection';

                        // Show error
                        statusDiv.style.display = 'block';
                        statusDiv.className = 'alert alert-danger py-2 mb-0 mt-2';
                        statusDiv.innerHTML =
                            '<iconify-icon icon="solar:close-circle-bold" class="align-middle me-1"></iconify-icon> Connection test failed. Please try again.';
                    });
            }
        });
    </script>
@endpush
