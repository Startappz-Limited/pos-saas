<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Credit Statement {{ $creditAccount->customer?->code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; line-height: 1.45; }
        .container { padding: 30px 36px; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header-left, .header-right { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { text-align: right; }
        .shop-name { font-size: 18px; font-weight: bold; color: #222; margin-bottom: 4px; }
        .muted { color: #666; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 18px; color: #222; }
        .info { display: table; width: 100%; margin-bottom: 20px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; }
        .label { display: inline-block; width: 110px; font-weight: bold; color: #555; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary td { width: 25%; border: 1px solid #ddd; padding: 9px; }
        .summary .value { display: block; font-size: 14px; font-weight: bold; color: #222; margin-top: 3px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #333; color: #fff; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .table td { padding: 8px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        .text-right { text-align: right; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; color: #777; font-size: 10px; }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="shop-name">{{ $creditAccount->shop?->name }}</div>
                <div class="muted">
                    @if ($creditAccount->shop?->address){{ $creditAccount->shop->address }}<br>@endif
                    @if ($creditAccount->shop?->phone){{ $creditAccount->shop->phone }}<br>@endif
                    @if ($creditAccount->shop?->email){{ $creditAccount->shop->email }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="shop-name">CREDIT STATEMENT</div>
                <div class="muted">Generated {{ now()->format('M d, Y H:i') }}</div>
            </div>
        </div>

        <div class="title">{{ $creditAccount->customer?->name }}</div>

        <div class="info">
            <div class="info-col">
                <div><span class="label">Customer Code</span>{{ $creditAccount->customer?->code ?? '—' }}</div>
                <div><span class="label">Phone</span>{{ $creditAccount->customer?->phone ?? '—' }}</div>
                <div><span class="label">Email</span>{{ $creditAccount->customer?->email ?? '—' }}</div>
            </div>
            <div class="info-col">
                <div><span class="label">Period</span>{{ $periodLabel }}</div>
                <div><span class="label">Type</span>{{ $typeLabel }}</div>
                <div><span class="label">Credit Limit</span>{{ format_currency($creditAccount->credit_limit) }}</div>
            </div>
        </div>

        <table class="summary">
            <tr>
                <td><span class="muted">Opening Balance</span><span class="value">{{ format_currency($openingBalance) }}</span></td>
                <td><span class="muted">Purchases / Debits</span><span class="value">{{ format_currency($totalDebits) }}</span></td>
                <td><span class="muted">Payments / Credits</span><span class="value">{{ format_currency($totalCredits) }}</span></td>
                <td><span class="muted">Closing Balance</span><span class="value">{{ format_currency($closingBalance) }}</span></td>
            </tr>
        </table>

        <table class="table">
            <thead>
                <tr><th>Date</th><th>Number</th><th>Type</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th><th>Due</th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at->format('M d, Y') }}</td>
                        <td>{{ $transaction->transaction_number }}</td>
                        <td>{{ $transaction->type->label() }}</td>
                        <td>{{ $transaction->description ?? '—' }}</td>
                        <td class="text-right">{{ $transaction->debit > 0 ? format_currency($transaction->debit) : '—' }}</td>
                        <td class="text-right">{{ $transaction->credit > 0 ? format_currency($transaction->credit) : '—' }}</td>
                        <td class="text-right">{{ format_currency($transaction->balance_after) }}</td>
                        <td>{{ $transaction->due_date?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align: center; padding: 22px; color: #777;">No transactions found for this statement.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">This statement was generated from {{ config('app.name') }}.</div>
    </div>
</body>

</html>