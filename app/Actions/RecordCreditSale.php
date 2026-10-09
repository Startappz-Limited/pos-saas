<?php

namespace App\Actions;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Sale;
use App\Services\CreditAccountService;
use App\Services\CreditTransactionService;

/**
 * Post a credit sale as a debit on the customer's credit account.
 *
 * Credit sales used to be recorded only as `unpaid` rows on `sales`; the credit
 * ledger was never touched, so a wholesaler's balance stayed at zero however
 * much they owed. This is the missing link, and it is called from inside the
 * sale's own transaction so a sale and its debt commit together or not at all.
 *
 * @see SettleCreditSalePayment for the matching payment side.
 */
class RecordCreditSale
{
    public function __construct(
        private readonly CreditAccountService $creditAccounts,
        private readonly CreditTransactionService $creditTransactions,
    ) {}

    /**
     * @param  float|null  $amount  Overrides the amount to debit. Used by the
     *                              backfill, which must post each historic
     *                              sale's ORIGINAL total and then replay its
     *                              payments — reading the already-reduced
     *                              `balance_due` there would under-record the
     *                              debt and then over-credit the settlement.
     * @param  bool  $grantCredit  See CreditAccountService::resolveAccount().
     *                             False for the historic backfill.
     * @return CreditTransaction|null The posted debit, or null when the sale is
     *                                not a credit sale and nothing was owed.
     */
    public function handle(Sale $sale, ?float $amount = null, bool $grantCredit = true): ?CreditTransaction
    {
        if ($sale->payment_method !== 'credit' || ! $sale->customer_id) {
            return null;
        }

        // Re-running the action (a retried request, the backfill command) must
        // not double-charge the customer.
        $existing = CreditTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('type', CreditTransactionType::PURCHASE)
            ->first();

        if ($existing) {
            return $existing;
        }

        $sale->loadMissing(['customer', 'shop']);

        if (! $sale->customer || ! $sale->shop) {
            return null;
        }

        $amount ??= (float) $sale->balance_due;

        if ($amount <= 0) {
            return null;
        }

        $account = $this->creditAccounts->resolveAccount($sale->customer, $sale->shop, $grantCredit);

        return $this->creditTransactions->recordTransaction(
            $account,
            CreditTransactionType::PURCHASE,
            $amount,
            [
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'due_date' => ($sale->completed_at ?? now())
                    ->copy()
                    ->addDays($account->payment_terms_days)
                    ->toDateString(),
                'description' => "Credit sale {$sale->invoice_number}",
            ],
            enforceLimit: $sale->shop->enforcesCreditLimit(),
        );
    }

    /**
     * Whether this sale pushes the customer past their credit limit.
     *
     * Read before the debit is posted so the till can warn the cashier without
     * blocking the sale. Returns false when no limit is set.
     */
    public function exceedsLimit(Sale $sale): bool
    {
        if ($sale->payment_method !== 'credit' || ! $sale->customer_id) {
            return false;
        }

        $sale->loadMissing(['customer', 'shop']);

        if (! $sale->customer || ! $sale->shop) {
            return false;
        }

        $account = $this->creditAccounts->resolveAccount($sale->customer, $sale->shop);

        if ((float) $account->credit_limit <= 0) {
            return false;
        }

        return (float) $account->available_credit < (float) $sale->balance_due;
    }
}
