<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }

        .container {
            padding: 30px 40px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .shop-name {
            font-size: 18px;
            font-weight: bold;
            color: #222;
            margin-bottom: 4px;
        }

        .shop-details {
            font-size: 11px;
            color: #666;
        }

        /* Invoice Title */
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #222;
            margin-bottom: 20px;
        }

        /* Info Section */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }

        .info-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 130px;
        }

        .info-value {
            color: #333;
        }

        .customer-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .customer-details {
            font-size: 11px;
            color: #666;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead th {
            background: #333;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }

        .items-table thead th:last-child {
            text-align: right;
        }

        .items-table thead th:nth-child(2) {
            text-align: center;
        }

        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .items-table tbody td:last-child {
            text-align: right;
        }

        .items-table tbody td:nth-child(2) {
            text-align: center;
        }

        /* Totals */
        .totals {
            width: 100%;
        }

        .totals-row {
            display: table;
            width: 100%;
        }

        .totals-label {
            display: table-cell;
            width: 70%;
            text-align: right;
            padding: 5px 12px;
            font-weight: bold;
            color: #555;
        }

        .totals-value {
            display: table-cell;
            width: 30%;
            text-align: right;
            padding: 5px 12px;
        }

        .totals-total .totals-label,
        .totals-total .totals-value {
            font-size: 14px;
            font-weight: bold;
            color: #222;
            border-top: 2px solid #333;
            padding-top: 8px;
        }

        /* Shipping Note */
        .shipping-note {
            font-size: 10px;
            color: #999;
            text-align: right;
            padding-right: 12px;
            margin-top: 2px;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
    </style>
</head>

<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                <div class="shop-name">{{ $shop->name }}</div>
                <div class="shop-details">
                    @if ($shop->address)
                        {{ $shop->address }}<br>
                    @endif
                    @if ($shop->city){{ $shop->city }}@if ($shop->state)
                            , {{ $shop->state }}
                        @endif
                        <br>
                    @endif
                    @if ($shop->country)
                        {{ $shop->country }}<br>
                    @endif
                    @if ($shop->phone)
                        {{ $shop->phone }}<br>
                    @endif
                    @if ($shop->email)
                        {{ $shop->email }}
                    @endif
                </div>
            </div>
            <div class="header-right">
                <div class="shop-name">INVOICE</div>
            </div>
        </div>

        {{-- Customer & Order Info --}}
        <div class="info-section">
            <div class="info-left">
                <div class="customer-name">{{ $order->customer_name }}</div>
                <div class="customer-details">
                    @if ($order->shipping_address)
                        @php $addr = $order->shipping_address; @endphp
                        @if (!empty($addr['address_1'] ?? ($addr['address1'] ?? ($addr['street'] ?? ''))))
                            {{ $addr['address_1'] ?? ($addr['address1'] ?? $addr['street']) }}<br>
                        @endif
                        @if (!empty($addr['city'] ?? ''))
                            {{ $addr['city'] }}
                            @if (!empty($addr['state'] ?? ($addr['province'] ?? '')))
                                , {{ $addr['state'] ?? $addr['province'] }}
                            @endif
                            <br>
                        @endif
                        @if (!empty($addr['country'] ?? ''))
                            {{ $addr['country'] }}<br>
                        @endif
                    @endif
                    @if ($order->customer_email)
                        {{ $order->customer_email }}<br>
                    @endif
                    @if ($order->customer_phone)
                        {{ $order->customer_phone }}
                    @endif
                </div>
            </div>
            <div class="info-right">
                <div>
                    <span class="info-label">Invoice Number:</span>
                    <span class="info-value">{{ $order->order_number }}</span>
                </div>
                <div>
                    <span class="info-label">Order Date:</span>
                    <span
                        class="info-value">{{ ($order->platform_created_at ?? $order->created_at)->format('F j, Y') }}</span>
                </div>
                @if ($order->payment_method)
                    <div>
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value">{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ $order->currency }} {{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal</div>
                <div class="totals-value">{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Shipping</div>
                <div class="totals-value">{{ $order->currency }} {{ number_format($order->shipping_total ?? 0, 2) }}
                </div>
            </div>
            @if ($order->discount_total > 0)
                <div class="totals-row">
                    <div class="totals-label">Discount</div>
                    <div class="totals-value">-{{ $order->currency }} {{ number_format($order->discount_total, 2) }}
                    </div>
                </div>
            @endif
            @if ($order->tax_total > 0)
                <div class="totals-row">
                    <div class="totals-label">Tax</div>
                    <div class="totals-value">{{ $order->currency }} {{ number_format($order->tax_total, 2) }}</div>
                </div>
            @endif
            <div class="totals-row totals-total">
                <div class="totals-label">Total</div>
                <div class="totals-value">{{ $order->currency }} {{ number_format($order->total, 2) }}</div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            {{ $shop->name }}
        </div>
    </div>
</body>

</html>
