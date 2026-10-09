<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Services\CreditAccountService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);

    $this->customer = Customer::factory()->forShop($this->shop)->create([
        'customer_type' => 'wholesale',
        'allow_credit' => true,
        'credit_limit' => 10000,
    ]);

    $this->account = CreditAccount::create([
        'customer_id' => $this->customer->id,
        'shop_id' => $this->shop->id,
        'credit_limit' => 10000,
        'current_balance' => 0,
        'status' => 'active',
        'payment_terms_days' => 30,
        'grace_period_days' => 7,
    ]);

    Permission::findOrCreate('credit-sales.full-access');
    $this->user->givePermissionTo('credit-sales.full-access');
});

/**
 * Post a movement straight onto the ledger, bypassing the sale flow, so the
 * aging maths can be exercised against dates in the past.
 */
function ledgerRow(CreditAccount $account, float $debit, float $credit, ?string $dueDate, float $before, float $after): CreditTransaction
{
    return CreditTransaction::create([
        'credit_account_id' => $account->id,
        'customer_id' => $account->customer_id,
        'shop_id' => $account->shop_id,
        'type' => $debit > 0 ? CreditTransactionType::PURCHASE : CreditTransactionType::PAYMENT,
        'debit' => $debit,
        'credit' => $credit,
        'balance_before' => $before,
        'balance_after' => $after,
        'due_date' => $dueDate,
    ]);
}

describe('overdue and aging maths', function () {
    it('applies payments to the oldest invoice first', function () {
        // 1,000 overdue by 45 days, plus 500 not yet due, then 500 paid. FIFO
        // sends that payment to the overdue invoice, leaving 500 overdue —
        // and this must agree with the aging report's own allocation.
        ledgerRow($this->account, 1000, 0, now()->subDays(45)->toDateString(), 0, 1000);
        ledgerRow($this->account, 500, 0, now()->addDays(15)->toDateString(), 1000, 1500);
        ledgerRow($this->account, 0, 500, null, 1500, 1000);

        $this->account->update(['current_balance' => 1000]);

        $aging = app(CreditAccountService::class)->getAgingReport($this->shop->id);

        expect($this->account->fresh()->overdueAmount())->toBe(500.0)
            ->and($aging['31_60_days'])->toBe(500.0)
            ->and($aging['current'])->toBe(500.0);
    });

    it('reports nothing overdue once the old invoices are cleared', function () {
        ledgerRow($this->account, 1000, 0, now()->subDays(45)->toDateString(), 0, 1000);
        ledgerRow($this->account, 0, 1000, null, 1000, 0);

        expect($this->account->fresh()->overdueAmount())->toBe(0.0);
    });

    it('ages only unpaid debt, not gross purchases', function () {
        ledgerRow($this->account, 1000, 0, now()->subDays(100)->toDateString(), 0, 1000);
        ledgerRow($this->account, 800, 0, now()->subDays(45)->toDateString(), 1000, 1800);
        ledgerRow($this->account, 0, 1000, null, 1800, 800);

        $this->account->update(['current_balance' => 800]);

        $aging = app(CreditAccountService::class)->getAgingReport($this->shop->id);

        // The 100-day invoice is fully paid off; only the 45-day one remains.
        expect($aging['over_90_days'])->toBe(0.0)
            ->and($aging['31_60_days'])->toBe(800.0);
    });

    it('counts an account with only current debt in the current bucket', function () {
        // The old report only looked at accounts that already had something
        // overdue, so healthy accounts were missing from `current` entirely.
        ledgerRow($this->account, 1200, 0, now()->addDays(20)->toDateString(), 0, 1200);
        $this->account->update(['current_balance' => 1200]);

        $aging = app(CreditAccountService::class)->getAgingReport($this->shop->id);

        expect($aging['current'])->toBe(1200.0);
    });
});

describe('cross-shop isolation on the credit accounts screen', function () {
    beforeEach(function () {
        $this->otherShop = Shop::factory()->create();
        $otherCustomer = Customer::factory()->forShop($this->otherShop)->create([
            'customer_type' => 'wholesale',
            'allow_credit' => true,
        ]);

        $this->otherAccount = CreditAccount::create([
            'customer_id' => $otherCustomer->id,
            'shop_id' => $this->otherShop->id,
            'credit_limit' => 5000,
            'current_balance' => 2500,
            'status' => 'active',
            'payment_terms_days' => 30,
            'grace_period_days' => 7,
        ]);
    });

    it('hides another shop\'s credit accounts from a shop-assigned user', function () {
        $response = $this->actingAs($this->user)->get(route('credit-accounts.index'));

        $response->assertOk();

        expect($response->viewData('accounts')->pluck('id')->all())
            ->toBe([$this->account->id]);
    });

    it('rejects an explicit shop_id the user cannot access', function () {
        $this->actingAs($this->user)
            ->get(route('credit-accounts.index', ['shop_id' => $this->otherShop->id]))
            ->assertForbidden();
    });

    it('shows every shop of the business to its owner', function () {
        $admin = User::factory()->owner()->create();
        $admin->givePermissionTo('credit-sales.full-access');

        $response = $this->actingAs($admin)->get(route('credit-accounts.index'));

        expect($response->viewData('accounts')->count())->toBe(2);
    });
});
