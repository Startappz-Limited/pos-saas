<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $sale->invoice_number }}</title>
    <style>
        /* Thermal Receipt Styles - Optimized for 80mm (3.15") printers */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0;
            size: 80mm auto;
        }

        @media print {

            html,
            body {
                width: 80mm;
                margin: 0 auto;
            }

            .receipt {
                width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }

        .receipt {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            padding: 5mm;
            background: #fff;
        }

        .receipt-header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
            margin-bottom: 10px;
        }

        .shop-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .shop-info {
            font-size: 10px;
        }

        .receipt-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            padding: 5px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }

        .receipt-info {
            margin-bottom: 10px;
            font-size: 11px;
        }

        /* Two-column rows use table layout, not flexbox.
           Thermal printer drivers don't render flexbox reliably, which makes
           the right-hand column collapse/stack under the label when printed. */
        .receipt-info-row,
        .item-details,
        .total-row {
            display: table;
            width: 100%;
        }

        .receipt-info-row>span,
        .item-details>span,
        .total-row>span {
            display: table-cell;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        .receipt-info-row>span:last-child,
        .item-details>span:last-child,
        .total-row>span:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .receipt-items {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin-bottom: 10px;
        }

        .item-row {
            margin-bottom: 8px;
        }

        .item-name {
            font-weight: bold;
        }

        .item-variation {
            font-size: 10px;
            font-style: italic;
        }

        .item-details {
            font-size: 11px;
        }

        .receipt-totals {
            padding: 10px 0;
            border-bottom: 1px dashed #000;
        }

        .total-row {
            margin-bottom: 3px;
        }

        .total-row.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 8px;
            margin-top: 8px;
        }

        .payment-info {
            padding: 10px 0;
            border-bottom: 1px dashed #000;
            font-size: 11px;
        }

        .receipt-footer {
            text-align: center;
            padding-top: 15px;
            font-size: 10px;
        }

        .receipt-footer .thank-you {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .barcode {
            text-align: center;
            padding: 10px 0;
            font-size: 10px;
            letter-spacing: 2px;
        }

        .print-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px auto;
            width: 80mm;
        }

        .btn {
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
        }

        .btn-print {
            background: #28a745;
            color: #fff;
        }

        .btn-close {
            background: #6c757d;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="print-actions no-print">
        <button type="button" class="btn btn-print" onclick="window.print()">🖨️ Print Receipt</button>
        <button type="button" class="btn btn-close" onclick="window.close()">✕ Close</button>
    </div>

    <div class="receipt">
        <!-- Header -->
        <div class="receipt-header">
            <div class="shop-name">{{ $sale->shop->name ?? config('app.name') }}</div>
            @if ($sale->shop)
                <div class="shop-info">
                    @if ($sale->shop->address)
                        {{ $sale->shop->address }}<br>
                    @endif
                    @if ($sale->shop->phone)
                        Tel: {{ $sale->shop->phone }}<br>
                    @endif
                    @if ($sale->shop->email)
                        {{ $sale->shop->email }}
                    @endif
                    @if ($sale->shop->tax_pin)
                        <br>PIN: {{ $sale->shop->tax_pin }}
                    @endif
                </div>
            @endif
        </div>

        <!-- Receipt Title -->
        <div class="receipt-title">
            @if ($sale->is_cod)
                COD RECEIPT
            @elseif ($sale->shop?->vat_registered)
                {{-- A VAT-registered seller issues a tax invoice: it is the
                     document a registered buyer needs to claim input VAT. --}}
                TAX INVOICE
            @else
                SALES RECEIPT
            @endif
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info">
            <div class="receipt-info-row">
                <span>Invoice:</span>
                <span>{{ $sale->invoice_number }}</span>
            </div>
            <div class="receipt-info-row">
                <span>Date:</span>
                <span>{{ $sale->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="receipt-info-row">
                <span>Cashier:</span>
                <span>{{ $sale->createdBy->name ?? 'N/A' }}</span>
            </div>
            @if ($sale->customer)
                <div class="receipt-info-row">
                    <span>Customer:</span>
                    <span>{{ $sale->customer->name }}</span>
                </div>
            @elseif($sale->walk_in_customer_name)
                <div class="receipt-info-row">
                    <span>Customer:</span>
                    <span>{{ $sale->walk_in_customer_name }}</span>
                </div>
            @endif
            @if ($sale->delivery_location)
                <div class="receipt-info-row">
                    <span>Delivery:</span>
                    <span>{{ Str::limit($sale->delivery_location, 20) }}</span>
                </div>
            @endif
            @if ($sale->customer_tax_pin)
                <div class="receipt-info-row">
                    <span>Customer PIN:</span>
                    <span>{{ $sale->customer_tax_pin }}</span>
                </div>
            @endif
        </div>

        <!-- Items -->
        <div class="receipt-items">
            @foreach ($sale->items as $item)
                <div class="item-row">
                    <div class="item-name">{{ $item->product->name }}</div>
                    @if ($item->variation)
                        <div class="item-variation">({{ $item->variation->name }})</div>
                    @endif
                    <div class="item-details">
                        <span>{{ $item->quantity }} x {{ format_currency($item->unit_price) }}</span>
                        <span>{{ format_currency($item->line_total) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Totals -->
        <div class="receipt-totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span>{{ format_currency($sale->subtotal) }}</span>
            </div>
            @if ($sale->discount_amount > 0)
                <div class="total-row">
                    <span>Discount</span>
                    <span>-{{ format_currency($sale->discount_amount) }}</span>
                </div>
            @endif
            @php
                $taxLabel = config('tax.label', 'VAT');
                $bands = collect($sale->tax_breakdown ?? [])->filter(fn ($b) => (float) ($b['tax_amount'] ?? 0) > 0);
            @endphp
            {{-- Inclusive pricing means the tax is already inside the subtotal, so
                 the receipt reports it rather than adding it to the total. --}}
            @if ($sale->tax_amount > 0)
                @forelse ($bands as $band)
                    <div class="total-row">
                        <span>{{ $taxLabel }}
                            {{ rtrim(rtrim(number_format((float) ($band['rate'] ?? 0), 2), '0'), '.') }}%{{ $sale->tax_inclusive ? ' (incl)' : '' }}</span>
                        <span>{{ format_currency($band['tax_amount']) }}</span>
                    </div>
                @empty
                    <div class="total-row">
                        <span>{{ $taxLabel }}{{ $sale->tax_inclusive ? ' (incl)' : '' }}</span>
                        <span>{{ format_currency($sale->tax_amount) }}</span>
                    </div>
                @endforelse
            @endif
            @if ($sale->delivery_fee > 0)
                <div class="total-row">
                    <span>Delivery</span>
                    <span>{{ format_currency($sale->delivery_fee) }}</span>
                </div>
            @endif
            @if ($sale->packaging_fee > 0)
                <div class="total-row">
                    <span>Packaging</span>
                    <span>{{ format_currency($sale->packaging_fee) }}</span>
                </div>
            @endif
            @if ($sale->other_expenses > 0)
                <div class="total-row">
                    <span>Other</span>
                    <span>{{ format_currency($sale->other_expenses) }}</span>
                </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL</span>
                <span>{{ format_currency($sale->total_amount) }}</span>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="payment-info">
            <div class="total-row">
                <span>Payment Method:</span>
                <span>{{ ucwords(str_replace('_', ' ', $sale->payment_method)) }}</span>
            </div>
            @if ($sale->is_cod)
                <div class="total-row">
                    <span>Status:</span>
                    <span>CASH ON DELIVERY</span>
                </div>
            @else
                <div class="total-row">
                    <span>Paid:</span>
                    <span>{{ format_currency($sale->paid_amount) }}</span>
                </div>
                @if ($sale->balance_due > 0)
                    <div class="total-row">
                        <span>Balance Due:</span>
                        <span>{{ format_currency($sale->balance_due) }}</span>
                    </div>
                @endif
            @endif
        </div>

        <!-- Barcode -->
        <div class="barcode">
            *{{ $sale->invoice_number }}*
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="thank-you">Thank You!</div>
            <div>Please keep this receipt for your records</div>
            @if ($sale->source)
                <div style="margin-top: 5px;">Source: {{ $sale->source->name }}</div>
            @endif
            @if ($sale->notes)
                <div style="margin-top: 5px; font-style: italic;">Note: {{ $sale->notes }}</div>
            @endif
        </div>
    </div>

    <script>
        // Auto print when opened with ?print=1 parameter
        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>

</html>
