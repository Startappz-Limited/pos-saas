<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>POS - {{ config('app.name') }}</title>

    <!-- Vendor css (Bootstrap & Vendors) -->
    <link href="{{ theme_asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons css (Iconify & Boxicons) -->
    <link href="{{ theme_asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css (Theme Styles) -->
    <link href="{{ theme_asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Theme Config js (Require in all Page) -->
    <script src="{{ theme_asset('assets/js/config.js') }}"></script>

    <style>
        /* Fullscreen POS Styles */
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        .pos-wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .pos-sidebar {
            width: 380px;
            min-width: 380px;
            background: #fff;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e5e7eb;
            height: 100vh;
        }

        .pos-header {
            padding: 0.75rem 1rem;
            background: #3b82f6;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .pos-header .logo {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .pos-customer-select {
            padding: 0.5rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .pos-cart {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .pos-cart-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .pos-cart-item:hover {
            background: #f8fafc;
        }

        .pos-cart-item .item-name {
            font-weight: 500;
            color: #1e293b;
        }

        .pos-cart-item .item-variant {
            font-size: 0.8rem;
            color: #64748b;
        }

        .pos-cart-item .item-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pos-cart-item .item-qty-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pos-cart-item .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
        }

        .pos-cart-item .qty-btn:hover {
            background: #f1f5f9;
        }

        .pos-cart-item .qty-value {
            min-width: 30px;
            text-align: center;
            font-weight: 500;
        }

        .pos-cart-item .item-price {
            font-weight: 600;
            color: #1e293b;
        }

        .pos-cart-item .item-remove {
            color: #ef4444;
            cursor: pointer;
            padding: 0.25rem;
        }

        .pos-totals {
            padding: 1rem;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .pos-totals .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
        }

        .pos-totals .total-row.grand-total {
            font-size: 1.25rem;
            font-weight: 700;
            border-top: 2px solid #e5e7eb;
            padding-top: 0.75rem;
            margin-top: 0.5rem;
        }

        .pos-actions {
            padding: 0.75rem 1rem;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .pos-actions .btn {
            flex: 1;
            padding: 0.75rem;
            font-weight: 600;
        }

        .pos-footer-actions {
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            display: flex;
            justify-content: space-around;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .pos-footer-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.5rem;
            color: #64748b;
            text-decoration: none;
            font-size: 0.75rem;
            cursor: pointer;
        }

        .pos-footer-btn:hover {
            color: #3b82f6;
        }

        .pos-footer-btn iconify-icon {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        /* Main content - Product Grid */
        .pos-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f1f5f9;
            overflow: hidden;
        }

        .pos-search {
            padding: 0.75rem 1rem;
            background: #3b82f6;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .pos-search input {
            flex: 1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .pos-categories {
            padding: 0.75rem 1rem;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            flex-shrink: 0;
        }

        .pos-categories::-webkit-scrollbar {
            height: 4px;
        }

        .pos-category-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #fff;
            white-space: nowrap;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.15s ease;
        }

        .pos-category-btn:hover {
            background: #f1f5f9;
        }

        .pos-category-btn.active {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }

        .pos-products {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .pos-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.75rem;
        }

        .pos-product-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: 1px solid #e5e7eb;
        }

        .pos-product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .pos-product-card .product-image {
            aspect-ratio: 1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .pos-product-card .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pos-product-card .product-image .no-image {
            color: #cbd5e1;
            font-size: 2.5rem;
        }

        .pos-product-card .product-info {
            padding: 0.5rem;
        }

        .pos-product-card .product-name {
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }

        .pos-product-card .product-price {
            font-size: 0.9rem;
            font-weight: 700;
            color: #3b82f6;
        }

        .pos-product-card .product-stock {
            font-size: 0.7rem;
            color: #64748b;
        }

        .pos-product-card.out-of-stock {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Empty cart state */
        .pos-cart-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            padding: 2rem;
        }

        .pos-cart-empty iconify-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .pos-sidebar {
                width: 320px;
                min-width: 320px;
            }
        }

        @media (max-width: 767.98px) {
            .pos-wrapper {
                flex-direction: column-reverse;
            }

            .pos-sidebar {
                width: 100%;
                min-width: 100%;
                height: 45vh;
                border-right: none;
                border-top: 1px solid #e5e7eb;
            }

            .pos-main {
                height: 55vh;
            }

            .pos-product-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>

    @stack('styles')
</head>

<body>
    @yield('content')

    <!-- Vendor Javascript (Require in all Page) -->
    <script src="{{ theme_asset('assets/js/vendor.js') }}"></script>

    <!-- App Javascript (Require in all Page) -->
    <script src="{{ theme_asset('assets/js/app.js') }}"></script>

    @stack('scripts')
</body>

</html>
