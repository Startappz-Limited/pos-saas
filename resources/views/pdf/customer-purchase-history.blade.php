<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Customer Purchases {{ $customer->code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; line-height: 1.45; }
        .container { padding: 28px 32px; }
        .header { display: table; width: 100%; margin-bottom: 22px; }
        .header-left, .header-right { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { text-align: right; }
        .shop-name { font-size: 17px; font-weight: bold; color: #222; margin-bottom: 4px; }
        .muted { color: #666; }
        .title { font-size: 22px; font-weight: bold; margin-bottom: 16px; color: #222; }
        .info { display: table; width: 100%; margin-bottom: 18px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; }
        .label { display: inline-block; width: 110px; font-weight: bold; color: #555; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .summary td { width: 25%; border: 1px solid #ddd; padding: 8px; }
        .summary .value { display: block; font-size: 13px; font-weight: bold; color: #222; margin-top: 3px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #333; color: #fff; padding: 7px; text-align: left; font-size: 9px; text-transform: uppercase; }
        .table td { padding: 7px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        .text-right { text-align: right; }
        .footer { margin-top: 26px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; color: #777; font-size: 9px; }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="shop-name">{{ $customer->shop?->name ?? config('app.name') }}</div>
                <div class="muted">
                    @if ($customer->shop?->address){{ $customer->shop->address }}<br>@endif
                    @if ($customer->shop?->phone){{ $customer->shop->phone }}<br>@endif
                    @if ($customer->shop?->email){{ $customer->shop->email }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="shop-name">PURCHASE HISTORY</div>
                <div class="muted">Generated {{ now()->format('M d, Y H:i') }}</div>
            </div>
        </div>

        <div class="title">{{ $customer->name }}</div>

        <div class="info">
            <div class="info-col">
                <div><span class="label">Customer Code</span>{{ $customer->code ?? '—' }}</div>
                <div><span class="label">Phone</span>{{ $customer->phone ?? '—' }}</div>
                <div><span class="label">Email</span>{{ $customer->email ?? '—' }}</div>
            </div>
            <div class="info-col">
                <div><span class="label">Period</span>{{ $periodLabel }}</div>
                <div><span class="label">Sale Status</span>{{ $statusLabel }}</div>
                <div><span class="label">Payment Status</span>{{ $paymentStatusLabel }}</div>
            </div>
        </div>

        <table class="summary">
            <tr>
                <td><span class="muted">Purchases</span><span class="value">{{ $summary['count'] }}</span></td>
                <td><span class="muted">Completed</span><span class="value">{{ $summary['completed_count'] }}</span></td>
                <td><span class="muted">Total Amount</span><span class="value">{{ format_currency($summary['total_amount']) }}</span></td>
                <td><span class="muted">Balance Due</span><span class="value">{{ format_currency($summary['balance_due']) }}</span></td>
            </tr>
        </table>

        <table class="table">
            <thead>
                <tr><th>Date</th><th>Invoice</th><th>Source</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Sale</th><th>Payment</th></tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>{{ $purchase->created_at->format('M d, Y') }}</td>
                        <td>{{ $purchase->invoice_number }}</td>
                        <td>{{ $purchase->source?->name ?? 'Direct sale' }}</td>
                        <td class="text-right">{{ format_currency($purchase->total_amount) }}</td>
                        <td class="text-right">{{ format_currency($purchase->paid_amount) }}</td>
                        <td class="text-right">{{ format_currency($purchase->balance_due) }}</td>
                        <td>{{ ucfirst($purchase->status) }}</td>
                        <td>{{ ucfirst($purchase->payment_status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align: center; padding: 22px; color: #777;">No purchases found for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">This purchase history was generated from {{ config('app.name') }}.</div>
    </div>
</body>

</html>