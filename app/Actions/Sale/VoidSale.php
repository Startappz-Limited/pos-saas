<?php

namespace App\Actions\Sale;

use App\Actions\SettleCreditSalePayment;
use App\Enums\StockMovementType;
use App\Models\CashPayout;
use App\Models\RefundDue;
use App\Models\Register;
use App\Models\Sale;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Sales\VoidRefunds;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Voids a sale (stage 10, steps 2 and 4): shared by the web and flat API void
 * and an approved void request. In one transaction it gives every line's
 * stock back (StockLedger::release), hands back the money the sale took
 * (VoidRefunds: cash out of the voider's own session, else a refund owed),
 * marks it voided and releases the debt a credit sale created; then its
 * session's totals are recalculated if the session is still open. Line and sale cost and profit stay as
 * sold: reports leave the sale out by its status. Who may void, and until
 * when, is the caller's check (SalePolicy::void, or the request's own window).
 */
class VoidSale
{
    public function __construct(
        private SettleCreditSalePayment $credit,
        private StockLedger $ledger,
        private VoidRefunds $refunds,
    ) {}

    /**
     * @param  ?User  $by  who voided it; null for an automatic void (step 5b)
     * @param  bool  $approvedRequest  voided by approving a request: cash is owed, never paid out at once
     * @param  ?Register  $till  voided at this till: its session at $recordedAt pays the cash (step 5c)
     * @return array{payouts: list<CashPayout>, refunds: list<RefundDue>, units: int}
     */
    public function execute(Sale $sale, string $reason, ?User $by, bool $approvedRequest = false, ?Register $till = null, ?CarbonInterface $recordedAt = null): array
    {
        $result = DB::transaction(function () use ($sale, $reason, $by, $approvedRequest, $till, $recordedAt): array {
            $units = 0;
            foreach ($sale->items()->orderBy('id')->get() as $item) {
                $units += $this->ledger->release($item, StockMovementType::SALE_VOID, ['reference' => $sale, 'user_id' => $by?->id, 'notes' => "Sale {$sale->invoice_number} voided."])?->quantity ?? 0;
            }

            // Before the status changes: what it took, and whether its session counted it.
            $money = $this->refunds->settle($sale, $by, $approvedRequest, $till, $recordedAt);

            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                // NULL for an automatic void (an online order cancelled in its store, step 5b).
                'voided_by' => $by?->id,
                'void_reason' => $reason,
                // At a till (stage 10, step 5c): which, and when the till recorded it.
                'voided_register_id' => $till?->id,
                'void_recorded_at' => $till !== null ? ($recordedAt ?? now()) : null,
            ]);

            // Release the debt a voided credit sale created, or the customer
            // keeps owing for a sale that no longer exists.
            $this->credit->reverse($sale, $reason);

            return [...$money, 'units' => $units];
        });

        // An open session drops the sale from its totals; a closed one was counted
        // and reconciled, so it stays as it was (its cash was paid out or is owed).
        if ($sale->register_id && $sale->register?->status === 'open') {
            $sale->register->updateSalesTotals();
        }

        return $result;
    }

    /**
     * The success message: the stock back, and what became of the money.
     *
     * @param  array{payouts: list<CashPayout>, refunds: list<RefundDue>, units: int}  $result
     */
    public static function summary(array $result): string
    {
        $lines = [trans_choice('{0} Sale voided.|{1} Sale voided. 1 item is back in stock.|[2,*] Sale voided. :count items are back in stock.', $result['units'], ['count' => $result['units']])];
        $paid = array_sum(array_map(fn ($payout): float => (float) $payout->amount, $result['payouts']));
        if ($paid > 0) {
            $lines[] = __('Hand :amount in cash back to the customer from your register.', ['amount' => number_format($paid, 2)]);
        }
        if ($result['refunds'] !== []) {
            $lines[] = trans_choice('{1} A refund is owed to the customer and has been listed for payment.|[2,*] :count refunds are owed to the customer and have been listed for payment.', count($result['refunds']), ['count' => count($result['refunds'])]);
        }

        return implode(' ', $lines);
    }

    /**
     * @param  array{payouts: list<CashPayout>, refunds: list<RefundDue>, units: int}  $result
     * @return array{paid_out: list<array{uuid: string, amount: string}>, refunds_due: list<array{uuid: string, method: string, amount: string, reference: ?string}>}
     */
    public static function money(array $result): array
    {
        return [
            'paid_out' => array_map(fn ($payout): array => ['uuid' => (string) $payout->uuid, 'amount' => number_format((float) $payout->amount, 2, '.', '')], $result['payouts']),
            'refunds_due' => array_map(fn ($refund): array => [
                'uuid' => (string) $refund->uuid, 'method' => $refund->method, 'amount' => number_format((float) $refund->amount, 2, '.', ''), 'reference' => $refund->reference,
            ], $result['refunds']),
        ];
    }
}
