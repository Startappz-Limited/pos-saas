<?php

namespace App\Services;

use App\Enums\CreditAccountStatus;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditAccountService
{
    public function getAccounts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CreditAccount::with(['customer', 'shop'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('customer', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['shop_id'] ?? null, fn ($query, int $shopId) => $query->forShop($shopId))
            ->when($filters['visible_to'] ?? null, fn ($query, User $user) => $query->visibleTo($user))
            ->when($filters['has_balance'] ?? false, fn ($query) => $query->withBalance())
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createAccount(Customer $customer, Shop $shop, array $data): CreditAccount
    {
        return DB::transaction(function () use ($customer, $shop, $data): CreditAccount {
            $this->ensureCreditAllowedCustomer($customer);

            if (CreditAccount::where('customer_id', $customer->id)->where('shop_id', $shop->id)->exists()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'This customer already has a credit account for the selected shop.',
                ]);
            }

            $creditAccount = CreditAccount::create([
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'credit_limit' => $data['credit_limit'],
                'current_balance' => 0,
                'available_credit' => $data['credit_limit'],
                'status' => CreditAccountStatus::from($data['status']),
                'payment_terms_days' => $data['payment_terms_days'],
                'grace_period_days' => $data['grace_period_days'],
            ]);

            $this->syncCustomerCredit($creditAccount);

            return $creditAccount->fresh(['customer', 'shop']);
        });
    }

    /**
     * Find the customer's credit account for a shop, creating it if absent.
     *
     * Called from the till when a credit sale is rung up. A wholesale customer
     * who has never been set up must not block the sale, and the resulting debt
     * must not go unrecorded — so the account is created with the shop's default
     * terms and an admin can adjust the limit afterwards.
     *
     * @param  bool  $grantCredit  Whether creating the account also marks the
     *                             customer as allowed credit. True at the till,
     *                             where a credit sale is itself the grant. False
     *                             for the historic backfill, which reconciles
     *                             past sales and must not re-grant credit an
     *                             admin has since revoked.
     */
    public function resolveAccount(Customer $customer, Shop $shop, bool $grantCredit = true): CreditAccount
    {
        // withTrashed: `credit_accounts` is uniquely indexed on (customer_id,
        // shop_id) irrespective of deleted_at, so a soft-deleted account must be
        // restored rather than re-created.
        $existing = CreditAccount::withTrashed()
            ->where('customer_id', $customer->id)
            ->where('shop_id', $shop->id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $defaults = $shop->creditDefaults();

        // A limit already set on the customer record is an explicit decision by
        // an admin and outranks the shop default — otherwise setting a limit on
        // the customer form would be silently discarded the first time they buy
        // on credit.
        $creditLimit = (float) $customer->credit_limit > 0
            ? (float) $customer->credit_limit
            : $defaults['credit_limit'];

        try {
            $creditAccount = CreditAccount::create([
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'credit_limit' => $creditLimit,
                'current_balance' => 0,
                'available_credit' => $creditLimit,
                'status' => CreditAccountStatus::ACTIVE,
                'payment_terms_days' => $defaults['payment_terms_days'],
                'grace_period_days' => $defaults['grace_period_days'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two tills rang up this customer's first credit sale at once.
            return CreditAccount::withTrashed()
                ->where('customer_id', $customer->id)
                ->where('shop_id', $shop->id)
                ->firstOrFail();
        }

        if ($grantCredit && ! $customer->allow_credit) {
            $customer->update(['allow_credit' => true]);
        }

        $this->syncCustomerCredit($creditAccount);

        return $creditAccount;
    }

    public function updateAccount(CreditAccount $creditAccount, array $data): CreditAccount
    {
        return DB::transaction(function () use ($creditAccount, $data): CreditAccount {
            $this->ensureCreditAllowedCustomer($creditAccount->customer);

            $creditAccount->update([
                'credit_limit' => $data['credit_limit'],
                'status' => CreditAccountStatus::from($data['status']),
                'payment_terms_days' => $data['payment_terms_days'],
                'grace_period_days' => $data['grace_period_days'],
                'limit_updated_at' => now(),
                'limit_updated_by' => Auth::id(),
            ]);

            $this->syncCustomerCredit($creditAccount->fresh());

            return $creditAccount->fresh(['customer', 'shop']);
        });
    }

    public function updateCreditLimit(CreditAccount $creditAccount, float $creditLimit): CreditAccount
    {
        return DB::transaction(function () use ($creditAccount, $creditLimit): CreditAccount {
            $this->ensureCreditAllowedCustomer($creditAccount->customer);

            $creditAccount->update([
                'credit_limit' => $creditLimit,
                'limit_updated_at' => now(),
                'limit_updated_by' => Auth::id(),
            ]);

            $this->syncCustomerCredit($creditAccount->fresh());

            return $creditAccount->fresh(['customer', 'shop']);
        });
    }

    public function suspendAccount(CreditAccount $creditAccount, string $reason): CreditAccount
    {
        $creditAccount->suspend($reason);

        return $creditAccount->fresh(['customer', 'shop']);
    }

    public function reactivateAccount(CreditAccount $creditAccount): CreditAccount
    {
        $creditAccount->reactivate();

        return $creditAccount->fresh(['customer', 'shop']);
    }

    public function deleteAccount(CreditAccount $creditAccount): bool
    {
        if ((float) $creditAccount->current_balance > 0) {
            throw ValidationException::withMessages([
                'credit_account' => 'Credit accounts with an outstanding balance cannot be deleted.',
            ]);
        }

        return (bool) $creditAccount->delete();
    }

    public function getStatistics(?int $shopId = null): array
    {
        $stats = CreditAccount::query()
            ->when($shopId, fn ($query) => $query->forShop($shopId))
            ->selectRaw("\n                COUNT(*) as total,\n                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,\n                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,\n                SUM(CASE WHEN current_balance > 0 THEN 1 ELSE 0 END) as with_balance,\n                COALESCE(SUM(credit_limit), 0) as total_credit_limit,\n                COALESCE(SUM(current_balance), 0) as total_balance,\n                COALESCE(SUM(available_credit), 0) as total_available\n            ")
            ->first();

        return [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'suspended' => (int) $stats->suspended,
            'with_balance' => (int) $stats->with_balance,
            'total_credit_limit' => (float) $stats->total_credit_limit,
            'total_balance' => (float) $stats->total_balance,
            'total_available' => (float) $stats->total_available,
        ];
    }

    public function getOverdueAccounts(?int $shopId = null): Collection
    {
        return CreditAccount::with(['customer', 'shop'])
            ->active()
            ->withBalance()
            ->whereHas('transactions', function ($query): void {
                // `due_date` is a DATE column, so compare it directly — whereDate()
                // wraps it in a function and defeats the (due_date, is_overdue) index.
                $query->where('debit', '>', 0)
                    ->where('due_date', '<', today());
            })
            ->when($shopId, fn ($query) => $query->forShop($shopId))
            ->get();
    }

    /**
     * Outstanding debt bucketed by how long it has been overdue.
     *
     * Covers every account carrying a balance, not just the overdue ones —
     * otherwise the `current` bucket omits accounts that are entirely up to
     * date, which is the majority of a healthy book.
     *
     * @return array<string, float>
     */
    public function getAgingReport(?int $shopId = null): array
    {
        $aging = [
            'current' => 0.0,
            '1_30_days' => 0.0,
            '31_60_days' => 0.0,
            '61_90_days' => 0.0,
            'over_90_days' => 0.0,
        ];

        CreditAccount::query()
            ->withBalance()
            ->when($shopId, fn ($query) => $query->forShop($shopId))
            ->with('transactions')
            ->chunkById(200, function (Collection $accounts) use (&$aging): void {
                foreach ($accounts as $account) {
                    foreach ($this->bucketOutstanding($account) as $bucket => $amount) {
                        $aging[$bucket] += $amount;
                    }
                }
            });

        return array_map(fn (float $amount): float => round($amount, 2), $aging);
    }

    /**
     * Age one account's *unpaid* debt, applying payments oldest-invoice-first.
     *
     * Ageing gross debits — as this once did — reports debt that has already
     * been settled, so the report grows without bound as a customer trades.
     *
     * @return array<string, float>
     */
    private function bucketOutstanding(CreditAccount $account): array
    {
        $buckets = [
            'current' => 0.0,
            '1_30_days' => 0.0,
            '31_60_days' => 0.0,
            '61_90_days' => 0.0,
            'over_90_days' => 0.0,
        ];

        $unapplied = (float) $account->transactions->sum('credit');

        $debits = $account->transactions
            ->filter(fn ($transaction): bool => (float) $transaction->debit > 0)
            ->sortBy(fn ($transaction) => $transaction->due_date ?? $transaction->created_at);

        foreach ($debits as $transaction) {
            $amount = (float) $transaction->debit;
            $settled = min($unapplied, $amount);
            $unapplied -= $settled;
            $outstanding = $amount - $settled;

            if ($outstanding <= 0) {
                continue;
            }

            $dueDate = $transaction->due_date;
            $daysOverdue = $dueDate && $dueDate->isPast() ? (int) $dueDate->diffInDays(today()) : 0;

            match (true) {
                $daysOverdue <= 0 => $buckets['current'] += $outstanding,
                $daysOverdue <= 30 => $buckets['1_30_days'] += $outstanding,
                $daysOverdue <= 60 => $buckets['31_60_days'] += $outstanding,
                $daysOverdue <= 90 => $buckets['61_90_days'] += $outstanding,
                default => $buckets['over_90_days'] += $outstanding,
            };
        }

        return $buckets;
    }

    /**
     * Mirror the account's limit and balance onto the customer record.
     *
     * Deliberately unvalidated: this is a projection, not a gate. Requiring
     * `allow_credit` here used to make settling a debt impossible the moment an
     * admin revoked credit, which left the customer owing money they could not
     * be recorded as having paid. Eligibility is enforced when an account is
     * created or updated, and at the till.
     *
     * It deliberately does NOT touch `allow_credit`: this runs on every payment,
     * so writing the flag here would silently re-grant credit to a defaulting
     * customer an admin had just revoked it from. Granting is a decision, and
     * belongs to whoever makes it — see resolveAccount().
     */
    public function syncCustomerCredit(CreditAccount $creditAccount): void
    {
        $creditAccount->customer()->update([
            'credit_limit' => $creditAccount->credit_limit,
            'credit_balance' => $creditAccount->current_balance,
        ]);
    }

    private function ensureCreditAllowedCustomer(Customer $customer): void
    {
        if ($customer->customer_type !== 'wholesale') {
            throw ValidationException::withMessages([
                'customer_id' => 'Credit is only available for wholesale customers.',
            ]);
        }

        if (! $customer->allow_credit) {
            throw ValidationException::withMessages([
                'customer_id' => 'Credit accounts can only be linked to customers who are allowed credit.',
            ]);
        }
    }
}
