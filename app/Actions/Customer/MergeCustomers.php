<?php

namespace App\Actions\Customer;

use App\Exceptions\BusinessRuleException;
use App\Models\CreditAccount;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate customer into the one kept (Phase 7, decision 4.9: a
 * till's possible duplicate, merged by an owner or manager, never
 * automatically). Everything the duplicate has (sales, returns, refunds,
 * payments, messages, price rules) moves to the kept customer; the duplicate
 * is soft-deleted, which tells every till to drop it, and points at the kept
 * one, so a till that still names it reaches the right customer.
 *
 * A duplicate with a credit account is not merged: its ledger would have to
 * be combined, which is an accounting decision, not a tidy-up.
 */
class MergeCustomers
{
    /** Tables whose rows name a customer, moved to the kept one. */
    private const TABLES = [
        'sales', 'returns', 'refunds', 'payments', 'abandoned_carts', 'pricing_rules', 'campaign_conversions',
        'overdue_notices', 'whatsapp_messages', 'baileys_contacts', 'baileys_messages', 'credit_limit_requests',
        'credit_payments', 'credit_transactions',
    ];

    /**
     * Both customers are the current business's (bound through its scopes).
     *
     * @throws BusinessRuleException
     */
    public function execute(Customer $duplicate, Customer $keep): void
    {
        if ($duplicate->is($keep)) {
            throw new BusinessRuleException(__('A customer can\'t be merged into itself.'));
        }

        if (CreditAccount::query()->where('customer_id', $duplicate->id)->exists()) {
            throw new BusinessRuleException(__(':name has a credit account, so they can\'t be merged. Keep both, or settle and close the account first.', ['name' => $duplicate->name]));
        }

        DB::transaction(function () use ($duplicate, $keep): void {
            foreach (self::TABLES as $table) {
                DB::table($table)->where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]);
            }

            $duplicate->forceFill(['merged_into_id' => $keep->id])->save();
            $duplicate->delete();
        });
    }
}
