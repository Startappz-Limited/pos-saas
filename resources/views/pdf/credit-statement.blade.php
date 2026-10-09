<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ __('Statement of Account') }} — {{ $customer?->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; line-height: 1.45; }
        .container { padding: 30px 36px; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header-left, .header-right { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { text-align: right; }
        .shop-name { font-size: 18px; font-weight: bold; color: #222; margin-bottom: 4px; }
        .muted { color: #666; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 4px; color: #222; }
        .subtitle { color: #666; margin-bottom: 18px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary td { width: 33.33%; border: 1px solid #ddd; padding: 9px; }
        .summary .value { display: block; font-size: 14px; font-weight: bold; color: #222; margin-top: 3px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #333; color: #fff; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .table td { padding: 8px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        .table tfoot td { border-top: 2px solid #333; border-bottom: none; font-weight: bold; font-size: 13px; padding-top: 10px; }
        .text-right { text-align: right; }
        .overdue { color: #b02a37; font-weight: bold; }
        .settled { padding: 24px; text-align: center; border: 1px solid #ddd; color: #555; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; color: #777; font-size: 10px; }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="shop-name">{{ $shop?->name }}</div>
                <div class="muted">
                    @if ($shop?->address){{ $shop->address }}<br>@endif
                    @if ($shop?->phone){{ $shop->phone }}<br>@endif
                    @if ($shop?->email){{ $shop->email }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="shop-name">{{ __('STATEMENT OF ACCOUNT') }}</div>
                <div class="muted">{{ __('As at') }} {{ $asAt->format('d M Y') }}</div>
            </div>
        </div>

        <div class="title">{{ $customer?->name }}</div>
        <div class="subtitle">
            @if ($customer?->code){{ $customer->code }} &middot; @endif
            {{ $customer?->phone }}
        </div>

        <table class="summary">
            <tr>
                <td>
                    {{ __('Credit Limit') }}
                    <span class="value">{{ number_format((float) $creditAccount->credit_limit, 2) }}</span>
                </td>
                <td>
                    {{ __('Total Due') }}
                    <span class="value">{{ number_format($totalOwed, 2) }}</span>
                </td>
                <td>
                    {{ __('Available Credit') }}
                    <span class="value">{{ number_format((float) $creditAccount->available_credit, 2) }}</span>
                </td>
            </tr>
        </table>

        @if ($sales->isEmpty())
            <div class="settled">{{ __('This account has no outstanding invoices. Thank you.') }}</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th class="text-right">{{ __('Age') }}</th>
                        <th class="text-right">{{ __('Total') }}</th>
                        <th class="text-right">{{ __('Paid') }}</th>
                        <th class="text-right">{{ __('Due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $sale)
                        @php
                            $saleDate = $sale->completed_at ?? $sale->created_at;
                            $ageDays = $saleDate ? (int) $saleDate->diffInDays($asAt) : null;
                        @endphp
                        <tr>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $saleDate?->format('d M Y') ?? '—' }}</td>
                            <td class="text-right {{ $ageDays !== null && $ageDays > 30 ? 'overdue' : '' }}">
                                {{ $ageDays !== null ? $ageDays . ' ' . __('days') : '—' }}
                            </td>
                            <td class="text-right">{{ number_format((float) $sale->total_amount, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $sale->paid_amount, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $sale->balance_due, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right">{{ __('TOTAL DUE') }}</td>
                        <td class="text-right">{{ number_format($totalOwed, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        <div class="footer">
            {{ __('Generated') }} {{ $asAt->format('d M Y H:i') }}
            @if ($shop?->name) &middot; {{ $shop->name }} @endif
        </div>
    </div>
</body>

</html>
