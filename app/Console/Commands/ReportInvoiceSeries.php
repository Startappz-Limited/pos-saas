<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\Sale;
use Illuminate\Console\Command;

/**
 * Read-only (stage 10, step 5d): how many invoice numbers each shop has in each
 * series, with the first and last, so the rollout can check that the
 * migrations changed no existing number and that new web sales take the web
 * series (`INV-{shop}-W-{year}-…`) while tills keep theirs. Deleted sales count:
 * their numbers stay taken.
 */
class ReportInvoiceSeries extends Command
{
    use RunsForBusiness;

    protected $signature = 'invoices:report-series';

    protected $description = 'Count invoice numbers per shop and series (read-only)';

    public function handleForBusiness(): int
    {
        $series = [];
        Sale::query()->withTrashed()->with('shop:id,name,code')->select(['id', 'shop_id', 'invoice_number'])->orderBy('id')
            ->lazyById()->each(function (Sale $sale) use (&$series): void {
                $number = (string) $sale->invoice_number;
                $key = preg_match('/^(.*)-(\d+)$/', $number, $match) ? $match[1] : '(other)';
                $row = &$series[$sale->shop?->name.'|'.$key];
                $row ??= ['shop' => (string) $sale->shop?->name, 'series' => $key, 'count' => 0, 'first' => $number, 'last' => $number];
                $row['count']++;
                $row['first'] = strcmp($number, $row['first']) < 0 ? $number : $row['first'];
                $row['last'] = strcmp($number, $row['last']) > 0 ? $number : $row['last'];
            });

        ksort($series);
        $this->table(['Shop', 'Series', 'Numbers', 'First', 'Last'], array_values($series));
        $this->info(array_sum(array_column($series, 'count')).' number(s). Nothing was changed.');

        return self::SUCCESS;
    }
}
