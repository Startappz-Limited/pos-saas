<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\VatReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Output-VAT reconciliation for a filing period.
 *
 * Kenyan VAT returns are monthly and due by the 20th of the following month, so
 * the default period is the month just ended rather than the current one.
 */
class VatReportController extends Controller
{
    public function __construct(private readonly VatReportService $vatReports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('reports.view'), 403);

        [$shop, $from, $to, $shops] = $this->resolvePeriod($request);

        return view('reports.vat', [
            'summary' => $this->vatReports->summary($shop, $from, $to),
            'shop' => $shop,
            'shops' => $shops,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('reports.export'), 403);

        [$shop, $from, $to] = $this->resolvePeriod($request);

        $summary = $this->vatReports->summary($shop, $from, $to);
        $filename = sprintf('vat-return-%s-to-%s.csv', $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($summary, $shop, $from, $to): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['VAT summary']);
            fputcsv($handle, ['Shop', $shop?->name ?? 'All shops']);
            fputcsv($handle, ['PIN', $shop?->tax_pin ?? '']);
            fputcsv($handle, ['Period', $from->toDateString().' to '.$to->toDateString()]);
            fputcsv($handle, []);

            fputcsv($handle, ['Supplies', 'KRA code', 'Rate %', 'Invoices', 'Taxable amount', 'Output VAT']);

            foreach ($summary['bands'] as $band) {
                fputcsv($handle, [
                    $band['label'],
                    $band['kra_code'],
                    $band['rate'],
                    $band['invoices'],
                    number_format($band['taxable_amount'], 2, '.', ''),
                    number_format($band['tax_amount'], 2, '.', ''),
                ]);
            }

            if ($summary['credit_notes'] !== []) {
                fputcsv($handle, []);
                fputcsv($handle, ['Credit notes (customer returns)', '', 'Rate %', '', 'Credited amount', 'VAT credited']);

                foreach ($summary['credit_notes'] as $band) {
                    fputcsv($handle, [
                        $band['label'],
                        '',
                        $band['rate'],
                        '',
                        number_format($band['credited_amount'], 2, '.', ''),
                        number_format($band['tax_amount'], 2, '.', ''),
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Taxable supplies', number_format($summary['totals']['taxable_supplies'], 2, '.', '')]);
            fputcsv($handle, ['Exempt supplies', number_format($summary['totals']['exempt_supplies'], 2, '.', '')]);
            fputcsv($handle, ['Output VAT', number_format($summary['totals']['output_tax'], 2, '.', '')]);
            fputcsv($handle, ['Less VAT on credit notes', number_format($summary['totals']['credited_tax'], 2, '.', '')]);
            fputcsv($handle, ['Net output VAT', number_format($summary['totals']['net_output_tax'], 2, '.', '')]);

            if ($summary['unclassified']['invoices'] > 0) {
                fputcsv($handle, []);
                fputcsv($handle, [
                    'Sales with no tax class (excluded)',
                    $summary['unclassified']['invoices'],
                    number_format($summary['unclassified']['amount'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: ?Shop, 1: Carbon, 2: Carbon, 3: Collection}
     */
    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $user = $request->user();
        $fullAccess = $user->can('reports.full-access');

        $shops = $fullAccess
            ? Shop::query()->select('id', 'name')->orderBy('name')->get()
            : $user->shops()->select('shops.id', 'shops.name')->orderBy('shops.name')->get();

        $shopId = $validated['shop_id'] ?? null;

        if ($shopId !== null && ! $fullAccess && ! $shops->contains('id', (int) $shopId)) {
            abort(403);
        }

        // A VAT return covers a calendar month and is filed after it closes, so
        // the month just ended is the period a user almost always wants.
        $from = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->subMonthNoOverflow()->startOfMonth();

        $to = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : (isset($validated['start_date'])
                ? Carbon::parse($validated['start_date'])->endOfMonth()
                : now()->subMonthNoOverflow()->endOfMonth());

        return [$shopId ? Shop::find($shopId) : null, $from, $to, $shops];
    }
}
