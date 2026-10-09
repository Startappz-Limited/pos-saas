<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->invoice_number }}</title>
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
            width: 55%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 45%;
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

        .invoice-title {
            font-size: 22px;
            font-weight: bold;
            color: #222;
            margin-bottom: 8px;
        }

        /* Payment stamp */
        .stamp {
            display: inline-block;
            padding: 5px 14px;
            border: 2px solid;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stamp-paid {
            color: #1a7f37;
            border-color: #1a7f37;
        }

        .stamp-partial {
            color: #b26a00;
            border-color: #b26a00;
        }

        .stamp-unpaid {
            color: #b42318;
            border-color: #b42318;
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

        .section-heading {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            margin-bottom: 4px;
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

        .items-table thead th.num,
        .items-table tbody td.num {
            text-align: right;
        }

        .items-table thead th.center,
        .items-table tbody td.center {
            text-align: center;
        }

        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .variation {
            font-size: 10px;
            color: #888;
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

        .totals-due .totals-label,
        .totals-due .totals-value {
            font-size: 13px;
            font-weight: bold;
            color: #b42318;
        }

        /* Payments */
        .payments {
            margin-top: 30px;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .payments-table thead th {
            background: #f2f2f2;
            color: #555;
            padding: 7px 12px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }

        .payments-table thead th.num,
        .payments-table tbody td.num {
            text-align: right;
        }

        .payments-table tbody td {
            padding: 7px 12px;
            border-bottom: 1px solid #ececec;
        }

        /* Notes */
        .notes {
            margin-top: 25px;
            font-size: 11px;
            color: #666;
        }

        .notes-label {
            font-weight: bold;
            color: #555;
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
    @php
        $currency = currency_symbol();
        $issuedAt = $sale->completed_at ?? $sale->created_at;
        $customerName = $sale->customer?->name ?? ($sale->walk_in_customer_name ?: 'Walk-in Customer');
        $customerPhone = $sale->customer?->phone ?? $sale->walk_in_customer_phone;
        $customerEmail = $sale->customer?->email ?? $sale->walk_in_customer_email;
        $stamp = match ($sale->payment_status) {
            'paid' => ['label' => 'Paid', 'class' => 'stamp-paid'],
            'partial' => ['label' => 'Partially Paid', 'class' => 'stamp-partial'],
            default => ['label' => 'Unpaid', 'class' => 'stamp-unpaid'],
        };
    @endphp

    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                <div class="shop-name">{{ $shop?->name ?? config('app.name') }}</div>
                <div class="shop-details">
                    @if ($shop?->address)
                        {{ $shop->address }}<br>
                    @endif
                    @if ($shop?->city)
                        {{ $shop->city }}@if ($shop->state), {{ $shop->state }}@endif
                        <br>
                    @endif
                    @if ($shop?->country)
                        {{ $shop->country }}<br>
                    @endif
                    @if ($shop?->phone)
                        {{ $shop->phone }}<br>
                    @endif
                    @if ($shop?->email)
                        {{ $shop->email }}<br>
                    @endif
                    @if ($shop?->tax_pin)
                        <strong>{{ __('PIN') }}: {{ $shop->tax_pin }}</strong>
                    @endif
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">{{ $shop?->vat_registered ? __('TAX INVOICE') : __('INVOICE') }}</div>
                <div class="stamp {{ $stamp['class'] }}">{{ $stamp['label'] }}</div>
            </div>
        </div>

        {{-- Customer & Invoice Info --}}
        <div class="info-section">
            <div class="info-left">
                <div class="section-heading">Billed To</div>
                <div class="customer-name">{{ $customerName }}</div>
                <div class="customer-details">
                    @if ($customerPhone)
                        {{ $customerPhone }}<br>
                    @endif
                    @if ($customerEmail)
                        {{ $customerEmail }}<br>
                    @endif
                    @if ($sale->delivery_location)
                        {{ $sale->delivery_location }}<br>
                    @endif
                    @if ($sale->customer_tax_pin)
                        {{ __('PIN') }}: {{ $sale->customer_tax_pin }}
                    @endif
                </div>
            </div>
            <div class="info-right">
                <div>
                    <span class="info-label">Invoice Number:</span>
                    <span class="info-value">{{ $sale->invoice_number }}</span>
                </div>
                <div>
                    <span class="info-label">Date:</span>
                    <span class="info-value">{{ $issuedAt?->format('F j, Y') }}</span>
                </div>
                @if ($sale->payment_method)
                    <div>
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value">{{ ucwords(str_replace('_', ' ', $sale->payment_method)) }}</span>
                    </div>
                @endif
                @if ($sale->deliveryCompany)
                    <div>
                        <span class="info-label">Delivery:</span>
                        <span class="info-value">{{ $sale->deliveryCompany->name }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="center">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>
                            {{ $item->product?->name ?? 'Item' }}
                            @if ($item->variation?->name)
                                <div class="variation">{{ $item->variation->name }}</div>
                            @endif
                        </td>
                        <td class="center">{{ $item->quantity }}</td>
                        <td class="num">{{ $currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num">{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal</div>
                <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->subtotal, 2) }}</div>
            </div>
            @if ((float) $sale->discount_amount > 0)
                <div class="totals-row">
                    <div class="totals-label">Discount</div>
                    <div class="totals-value">-{{ $currency }}
                        {{ number_format((float) $sale->discount_amount, 2) }}</div>
                </div>
            @endif
            @php
                $taxLabel = config('tax.label', 'VAT');
                $breakdown = collect($sale->tax_breakdown ?? []);
            @endphp
            {{-- Under inclusive pricing the tax already sits inside the subtotal,
                 so it is reported rather than added, and the net is shown first. --}}
            @if ($sale->tax_inclusive && (float) $sale->tax_amount > 0)
                <div class="totals-row">
                    <div class="totals-label">{{ __('Taxable amount') }}</div>
                    <div class="totals-value">{{ $currency }}
                        {{ number_format((float) $sale->taxable_amount, 2) }}</div>
                </div>
                @foreach ($breakdown as $band)
                    @continue((float) ($band['tax_amount'] ?? 0) <= 0)
                    <div class="totals-row">
                        <div class="totals-label">{{ $taxLabel }}
                            @ {{ rtrim(rtrim(number_format((float) ($band['rate'] ?? 0), 2), '0'), '.') }}%
                            ({{ __('included') }})</div>
                        <div class="totals-value">{{ $currency }}
                            {{ number_format((float) $band['tax_amount'], 2) }}</div>
                    </div>
                @endforeach
                @if ($breakdown->isEmpty())
                    <div class="totals-row">
                        <div class="totals-label">{{ $taxLabel }} ({{ __('included') }})</div>
                        <div class="totals-value">{{ $currency }}
                            {{ number_format((float) $sale->tax_amount, 2) }}</div>
                    </div>
                @endif
            @elseif ((float) $sale->tax_amount > 0)
                @forelse ($breakdown as $band)
                    @continue((float) ($band['tax_amount'] ?? 0) <= 0)
                    <div class="totals-row">
                        <div class="totals-label">{{ $taxLabel }}
                            @ {{ rtrim(rtrim(number_format((float) ($band['rate'] ?? 0), 2), '0'), '.') }}%</div>
                        <div class="totals-value">{{ $currency }}
                            {{ number_format((float) $band['tax_amount'], 2) }}</div>
                    </div>
                @empty
                    <div class="totals-row">
                        <div class="totals-label">{{ $taxLabel }}</div>
                        <div class="totals-value">{{ $currency }}
                            {{ number_format((float) $sale->tax_amount, 2) }}</div>
                    </div>
                @endforelse
            @endif
            @if ((float) $sale->delivery_fee > 0)
                <div class="totals-row">
                    <div class="totals-label">Delivery Fee</div>
                    <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->delivery_fee, 2) }}</div>
                </div>
            @endif
            @if ((float) $sale->packaging_fee > 0)
                <div class="totals-row">
                    <div class="totals-label">Packaging Fee</div>
                    <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->packaging_fee, 2) }}
                    </div>
                </div>
            @endif
            @if ((float) $sale->other_expenses > 0)
                <div class="totals-row">
                    <div class="totals-label">Other Charges</div>
                    <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->other_expenses, 2) }}
                    </div>
                </div>
            @endif
            <div class="totals-row totals-total">
                <div class="totals-label">Total</div>
                <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->total_amount, 2) }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Amount Paid</div>
                <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->paid_amount, 2) }}</div>
            </div>
            @if ((float) $sale->balance_due > 0)
                <div class="totals-row totals-due">
                    <div class="totals-label">Balance Due</div>
                    <div class="totals-value">{{ $currency }} {{ number_format((float) $sale->balance_due, 2) }}</div>
                </div>
            @endif
        </div>

        {{-- Payments --}}
        @if ($sale->payments->isNotEmpty())
            <div class="payments">
                <div class="section-heading">Payments Received</div>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->payments as $payment)
                            <tr>
                                <td>{{ ($payment->paid_at ?? $payment->created_at)?->format('M j, Y') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference ?: '—' }}</td>
                                <td class="num">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Notes --}}
        @if ($sale->notes)
            <div class="notes">
                <span class="notes-label">Notes:</span> {{ $sale->notes }}
            </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            Thank you for your business — {{ $shop?->name ?? config('app.name') }}
        </div>
    </div>
</body>

</html>
