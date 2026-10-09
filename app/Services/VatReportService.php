<?php

namespace App\Services;

use App\Enums\TaxClass;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates output VAT for a filing period.
 *
 * Kenyan VAT returns are filed monthly, by the 20th of the following month, and
 * report turnover split by tax treatment: standard rated, zero rated and exempt
 * are three different lines on the return, even though the last two both charge
 * nothing. Customer returns reduce the output VAT for the period they fall in,
 * so they are reported here as credit notes.
 *
 * This covers OUTPUT tax only — VAT charged on sales. Input tax on purchases is
 * not tracked: purchase orders record a `tax_amount` but nothing captures the
 * supplier's VAT registration or the tax class of what was bought, so claiming
 * input VAT from this system would be guesswork. The figures here are a
 * reconciliation aid for whoever files the return, not the return itself.
 */
class VatReportService
{
    public function __construct(private readonly TaxService $tax) {}

    /**
     * @return array{
     *     bands: array<int, array<string, mixed>>,
     *     credit_notes: array<int, array<string, mixed>>,
     *     totals: array<string, float|int>,
     *     unclassified: array<string, float|int>,
     * }
     */
    public function summary(
        ?Shop $shop,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $bands = $this->outputTax($shop, $from, $to);
        $creditNotes = $this->creditNoteTax($shop, $from, $to);

        $outputTax = array_sum(array_column($bands, 'tax_amount'));
        $creditedTax = array_sum(array_column($creditNotes, 'tax_amount'));

        $taxable = 0.0;
        $exempt = 0.0;

        foreach ($bands as $band) {
            if ($band['class'] instanceof TaxClass && $band['class']->isTaxableSupply()) {
                $taxable += $band['taxable_amount'];
            } else {
                $exempt += $band['taxable_amount'];
            }
        }

        return [
            'bands' => $bands,
            'credit_notes' => $creditNotes,
            'totals' => [
                'taxable_supplies' => round($taxable, 2),
                'exempt_supplies' => round($exempt, 2),
                'output_tax' => round($outputTax, 2),
                'credited_tax' => round($creditedTax, 2),
                'net_output_tax' => round($outputTax - $creditedTax, 2),
            ],
            'unclassified' => $this->unclassified($shop, $from, $to),
        ];
    }

    /**
     * Output VAT grouped by tax class and rate, aggregated in SQL.
     *
     * Grouped on the line's own shop, not the sale's: a sale may span shops and
     * each line records the shop that owns the product, which is the attribution
     * the rest of the reporting uses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function outputTax(?Shop $shop, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->saleItemQuery($shop, $from, $to)
            ->whereNotNull('sale_items.tax_class')
            ->groupBy('sale_items.tax_class', 'sale_items.tax_rate')
            ->selectRaw('sale_items.tax_class as tax_class')
            ->selectRaw('sale_items.tax_rate as tax_rate')
            ->selectRaw('COUNT(DISTINCT sales.id) as invoices')
            ->selectRaw('COALESCE(SUM(sale_items.taxable_amount), 0) as taxable_amount')
            ->selectRaw('COALESCE(SUM(sale_items.tax_amount), 0) as tax_amount')
            ->orderByDesc('sale_items.tax_rate')
            ->get();

        return $rows->map(function ($row): array {
            $class = TaxClass::tryFrom((string) $row->tax_class);

            return [
                'class' => $class,
                'label' => $class?->label() ?? (string) $row->tax_class,
                'kra_code' => $class?->kraCode() ?? '—',
                'rate' => round((float) $row->tax_rate, 2),
                'invoices' => (int) $row->invoices,
                'taxable_amount' => round((float) $row->taxable_amount, 2),
                'tax_amount' => round((float) $row->tax_amount, 2),
            ];
        })->all();
    }

    /**
     * Sales made before the VAT engine was switched on for the shop.
     *
     * These carry no tax class, so they cannot be placed on a band. Surfacing
     * the figure stops the report reading as complete when it is not.
     *
     * @return array{invoices: int, amount: float}
     */
    private function unclassified(?Shop $shop, CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $this->saleItemQuery($shop, $from, $to)
            ->whereNull('sale_items.tax_class')
            ->selectRaw('COUNT(DISTINCT sales.id) as invoices')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as amount')
            ->first();

        return [
            'invoices' => (int) ($row->invoices ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
        ];
    }

    /**
     * VAT to be credited back on goods the customer returned in the period.
     *
     * The returned line carries no tax of its own, so the rate is taken from the
     * original sale line and applied to what is actually being credited.
     *
     * @return array<int, array<string, mixed>>
     */
    private function creditNoteTax(?Shop $shop, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->join('sale_items', 'sale_items.id', '=', 'return_items.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('returns.status', 'completed')
            ->whereBetween('returns.updated_at', [$from, $to])
            ->whereNotNull('sale_items.tax_class')
            ->when($shop, fn ($query) => $query->where('returns.shop_id', $shop->id))
            ->groupBy('sale_items.tax_class', 'sale_items.tax_rate', 'sales.tax_inclusive')
            ->selectRaw('sale_items.tax_class as tax_class')
            ->selectRaw('sale_items.tax_rate as tax_rate')
            ->selectRaw('sales.tax_inclusive as tax_inclusive')
            ->selectRaw('COALESCE(SUM(return_items.total_price), 0) as credited_gross')
            ->get();

        $bands = [];

        foreach ($rows as $row) {
            $class = TaxClass::tryFrom((string) $row->tax_class);
            $rate = (float) $row->tax_rate;
            $gross = (float) $row->credited_gross;

            // Mirrors TaxService: inclusive prices contain the VAT, exclusive
            // prices had it added on top.
            $tax = $rate <= 0
                ? 0.0
                : ($row->tax_inclusive
                    ? round($gross * $rate / (100 + $rate), 2)
                    : round($gross * $rate / 100, 2));

            $key = $row->tax_class.'|'.$rate;

            $bands[$key] ??= [
                'class' => $class,
                'label' => $class?->label() ?? (string) $row->tax_class,
                'rate' => round($rate, 2),
                'credited_amount' => 0.0,
                'tax_amount' => 0.0,
            ];

            $bands[$key]['credited_amount'] = round($bands[$key]['credited_amount'] + $gross, 2);
            $bands[$key]['tax_amount'] = round($bands[$key]['tax_amount'] + $tax, 2);
        }

        return array_values($bands);
    }

    /**
     * Completed, non-voided sale lines in the period.
     */
    private function saleItemQuery(?Shop $shop, CarbonInterface $from, CarbonInterface $to)
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            // Ranged rather than whereDate() so the ['shop_id','completed_at']
            // index stays usable.
            ->whereBetween('sales.completed_at', [$from, $to])
            ->when($shop, fn ($query) => $query->where('sale_items.shop_id', $shop->id));
    }
}
