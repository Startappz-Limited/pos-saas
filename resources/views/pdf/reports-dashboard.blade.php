<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Detailed Reports Export</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .container { padding: 24px 28px; }
        .header { margin-bottom: 16px; }
        .title { font-size: 18px; font-weight: bold; color: #222; margin-bottom: 4px; }
        .muted { color: #666; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 8px; color: #222; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .summary td { border: 1px solid #ddd; padding: 8px; width: 25%; }
        .label { color: #666; display: block; }
        .value { font-size: 12px; font-weight: bold; color: #222; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .table th { background: #2d3748; color: #fff; text-align: left; padding: 6px; font-size: 9px; }
        .table td { border-bottom: 1px solid #e5e5e5; padding: 6px; }
        .text-right { text-align: right; }
        .footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #ddd; text-align: center; font-size: 9px; color: #777; }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="title">Detailed Reports Export</div>
            <div class="muted">Period: {{ $reportData['filters']['start_date'] }} to {{ $reportData['filters']['end_date'] }}</div>
            <div class="muted">Generated: {{ $generatedAt->format('M d, Y H:i') }}</div>
        </div>

        <div class="section-title">Summary</div>
        <table class="summary">
            <tr>
                <td><span class="label">Revenue</span><span class="value">{{ format_currency($reportData['summary']['total_revenue']) }}</span></td>
                <td><span class="label">Gross Profit</span><span class="value">{{ format_currency($reportData['summary']['gross_profit']) }}</span></td>
                <td><span class="label">Expenses</span><span class="value">{{ format_currency($reportData['summary']['expense_total']) }}</span></td>
                <td><span class="label">Net Profit</span><span class="value">{{ format_currency($reportData['summary']['net_profit']) }}</span></td>
            </tr>
        </table>

        <div class="section-title">Shop Performance</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Shop</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Gross Profit</th>
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Net Profit</th>
                    <th class="text-right">Net Margin %</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['shop_performance'] as $row)
                    <tr>
                        <td>{{ $row['shop_name'] }}</td>
                        <td class="text-right">{{ number_format($row['revenue'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['gross_profit'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['expense_total'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['net_profit'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['net_margin'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title">Top Products By Margin</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Gross Profit</th>
                    <th class="text-right">Margin %</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['top_products_by_margin'] as $row)
                    <tr>
                        <td>{{ $row->product_name }}</td>
                        <td class="text-right">{{ number_format($row->quantity_sold, 2) }}</td>
                        <td class="text-right">{{ number_format($row->revenue, 2) }}</td>
                        <td class="text-right">{{ number_format($row->gross_profit, 2) }}</td>
                        <td class="text-right">{{ number_format($row->margin, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">Generated from {{ config('app.name') }}</div>
    </div>
</body>

</html>
