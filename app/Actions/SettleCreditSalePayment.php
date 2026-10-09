<?php

namespace App\Actions;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\CreditAccountService;
use App\Services\CreditTransactionService;

/**
 * Credit a customer's credit account when they settle a credit sale.
 *
 * This is the half that made wholesalers "pay but never settle": collecting a
 * payment updated `sales.paid_amount` and stopped there, so the credit account
 * carried the debt forever. One credit transaction is posted per `sale_payments`
 * row, which keeps the ledger reconcilable against the payments table and makes
 * re-running the action harmless.
 *
 * @see RecordCreditSale for the matching purchase side.
 */
class SettleCreditSalePayment
{
    public function __construct(
        private readonly CreditAccountService $creditAccounts,
        private readonly CreditTransactionService $creditTransactions,
    ) {}

    /**
     * @return CreditTransaction|null The posted credit, or null when the sale
     *                                was not sold on credit.
     */
    public function handle(Sale $sale, SalePayment $payment): ?CreditTransaction
    {
        if ($sale->payment_method !== 'credit' || ! $sale->customer_id) {
            return null;
        }

        $existing = CreditTransaction::where('reference_type', SalePayment::class)
            ->where('reference_id', $payment->id)
            ->where('type', CreditTransactionType::PAYMENT)
            ->first();

        if ($existing) {
            return $existing;
        }

        $sale->loadMissing(['customer', 'shop']);

        if (! $sale->customer || ! $sale->shop) {
            return null;
        }

        $amount = (float) $payment->amount;

        if ($amount <= 0) {
            return null;
        }

        $account = $this->creditAccounts->resolveAccount($sale->customer, $sale->shop, grantCredit: false);

        return $this->creditTransactions->recordTransaction(
            $account,
            CreditTransactionType::PAYMENT,
            $amount,
            [
                'reference_type' => SalePayment::class,
                'reference_id' => $payment->id,
                'description' => "Payment {$payment->payment_number} against {$sale->invoice_number}",
            ],
            // A payment reduces debt; nothing about a limit may stand in its way.
            enforceLimit: false,
        );
    }

    /**
     * Reverse a credit sale's outstanding debit when the sale is voided.
     *
     * Posts an offsetting adjustment rather than deleting the purchase, so the
     * ledger stays append-only and the void remains visible on the statement.
     */
    public function reverse(Sale $sale, string $reason): ?CreditTransaction
    {
        if ($sale->payment_method !== 'credit' || ! $sale->customer_id) {
            return null;
        }

        $purchase = CreditTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('type', CreditTransactionType::PURCHASE)
            ->first();

        if (! $purchase) {
            return null;
        }

        $alreadyReversed = CreditTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('type', CreditTransactionType::ADJUSTMENT_CREDIT)
            ->exists();

        if ($alreadyReversed) {
            return null;
        }

        $sale->loadMissing(['customer', 'shop']);

        if (! $sale->customer || ! $sale->shop) {
            return null;
        }

        $account = $this->creditAccounts->resolveAccount($sale->customer, $sale->shop, grantCredit: false);

        return $this->creditTransactions->recordTransaction(
            $account,
            CreditTransactionType::ADJUSTMENT_CREDIT,
            (float) $purchase->debit,
            [
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'description' => "Reversal of voided credit sale {$sale->invoice_number}",
                'notes' => $reason,
            ],
            enforceLimit: false,
        );
    }
}
