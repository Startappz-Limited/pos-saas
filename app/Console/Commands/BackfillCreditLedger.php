<?php

namespace App\Console\Commands;

use App\Actions\RecordCreditSale;
use App\Actions\SettleCreditSalePayment;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild the credit ledger from credit sales that predate it being wired up.
 *
 * Credit sales were recorded only on `sales` until the till started posting to
 * `credit_transactions`, so historic debt is missing from every credit account.
 * This replays those sales — and any payments already collected against them —
 * through the same actions the till uses, which are idempotent, so the command
 * is safe to run repeatedly and safe to re-run after a partial failure.
 */
class BackfillCreditLedger extends Command
{
    protected $signature = 'credit:backfill
        {--shop= : Restrict to one shop id}
        {--dry-run : Report what would be posted without writing}';

    protected $description = 'Post historic credit sales and their payments to the credit ledger';

    public function handle(RecordCreditSale $recordCreditSale, SettleCreditSalePayment $settlePayment): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $shopId = $this->option('shop') ? (int) $this->option('shop') : null;

        $query = Sale::query()
            ->where('payment_method', 'credit')
            ->whereNotNull('customer_id')
            ->where('status', '!=', 'voided')
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId))
            ->with(['customer', 'shop', 'payments'])
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No credit sales to backfill.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry run] ' : '')."Backfilling {$total} credit sale(s).");

        $bar = $this->output->createProgressBar($total);
        $purchases = 0;
        $payments = 0;
        $skipped = 0;

        $query->chunkById(100, function (Collection $sales) use (
            $recordCreditSale, $settlePayment, $dryRun, $bar, &$purchases, &$payments, &$skipped
        ): void {
            foreach ($sales as $sale) {
                $bar->advance();

                if (! $sale->customer || ! $sale->shop) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $purchases++;
                    $payments += $sale->payments->count();

                    continue;
                }

                DB::transaction(function () use ($sale, $recordCreditSale, $settlePayment, &$purchases, &$payments): void {
                    // Post the sale's ORIGINAL total, then replay its payments;
                    // both actions are idempotent, so only genuinely new rows
                    // are counted.
                    // grantCredit: false — reconciling a past sale must not
                    // re-grant credit to a customer an admin has since revoked
                    // it from.
                    $purchase = $recordCreditSale->handle(
                        $sale,
                        (float) $sale->total_amount,
                        grantCredit: false,
                    );

                    if ($purchase?->wasRecentlyCreated) {
                        $purchases++;
                    }

                    foreach ($sale->payments as $payment) {
                        if ($settlePayment->handle($sale, $payment)?->wasRecentlyCreated) {
                            $payments++;
                        }
                    }
                });
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Result', 'Count'], [
            [$dryRun ? 'Purchases that would post' : 'Purchases posted', $purchases],
            [$dryRun ? 'Payments that would post' : 'Payments posted', $payments],
            ['Skipped (missing customer or shop)', $skipped],
        ]);

        if ($dryRun) {
            $this->comment('Dry run — nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
