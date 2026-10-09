<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditTransactionService
{
    public function getTransactions(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return CreditTransaction::with(['creditAccount.customer', 'customer', 'shop'])
            ->when($filters['credit_account_id'] ?? null, fn ($query, int $accountId) => $query->forAccount($accountId))
            ->when($filters['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['shop_id'] ?? null, fn ($query, int $shopId) => $query->where('shop_id', $shopId))
            ->when($filters['visible_to'] ?? null, fn ($query, User $user) => $query->visibleTo($user))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['overdue'] ?? false, fn ($query) => $query->overdue())
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Post one movement to a credit account and update its balance atomically.
     *
     * @param  bool  $enforceLimit  Whether a PURCHASE that exceeds the available
     *                              credit is rejected. The manual entry form
     *                              enforces it; the point of sale does not by
     *                              default, because a sale that has already been
     *                              rung up must land in the ledger regardless —
     *                              an unrecorded debt is worse than an
     *                              over-limit one. See config/credit.php.
     */
    public function recordTransaction(CreditAccount $creditAccount, CreditTransactionType $type, float $amount, array $data = [], bool $enforceLimit = true): CreditTransaction
    {
        return DB::transaction(function () use ($creditAccount, $type, $amount, $data, $enforceLimit): CreditTransaction {
            $creditAccount = CreditAccount::whereKey($creditAccount->id)->lockForUpdate()->firstOrFail();
            $isDebit = $type->isDebit();
            $customer = $creditAccount->customer;

            if ($isDebit && $enforceLimit && ($customer?->customer_type !== 'wholesale' || ! $customer->allow_credit)) {
                throw ValidationException::withMessages([
                    'amount' => 'Credit purchases are only available for wholesale customers who are allowed credit.',
                ]);
            }

            if ($enforceLimit && $type === CreditTransactionType::PURCHASE && ! $creditAccount->canMakePurchase($amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'The purchase exceeds the available credit for this account.',
                ]);
            }

            $balanceBefore = (float) $creditAccount->current_balance;
            $balanceAfter = $isDebit ? $balanceBefore + $amount : max(0, $balanceBefore - $amount);

            $transaction = CreditTransaction::create([
                'credit_account_id' => $creditAccount->id,
                'customer_id' => $creditAccount->customer_id,
                'shop_id' => $creditAccount->shop_id,
                'type' => $type,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'debit' => $isDebit ? $amount : 0,
                'credit' => $isDebit ? 0 : $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? $type->label(),
                'notes' => $data['notes'] ?? null,
            ]);

            $updates = ['current_balance' => $balanceAfter];

            if ($type === CreditTransactionType::PURCHASE) {
                $updates['last_purchase_at'] = now();
            }

            if ($type === CreditTransactionType::PAYMENT) {
                $updates['last_payment_at'] = now();
            }

            $creditAccount->update($updates);
            app(CreditAccountService::class)->syncCustomerCredit($creditAccount->fresh());

            // load(), not fresh(): fresh() returns a new instance and so drops
            // `wasRecentlyCreated`, which callers use to tell a newly posted
            // movement from an idempotent re-run.
            return $transaction->load(['creditAccount', 'customer', 'shop']);
        });
    }
}
