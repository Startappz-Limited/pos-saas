<?php

use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\User;
use App\Services\CreditAccountService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * Customers belong to the business (Open question 3) and have one credit
 * account, serving every shop (stage 1a acceptance).
 */
beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
});

it('shows a customer created in one shop in every shop of the business, and in no other business', function () {
    $a = $this->fixture->a;
    // A cashier who works only in the branch, not the shop that created the customer.
    $branchCashier = User::factory()->memberOf($a->business)->create();
    $branchCashier->shops()->attach($a->shops[1]);

    Sanctum::actingAs($branchCashier, ['*']);
    app(TenantContext::class)->forget();
    $this->getJson("/api/customers/{$a->customer->uuid}")->assertOk();

    Sanctum::actingAs($this->fixture->b->owner, ['*']);
    app(TenantContext::class)->forget();
    $this->getJson("/api/customers/{$a->customer->uuid}")->assertNotFound();
});

it('keeps customer codes unique per business', function () {
    app(TenantContext::class)->set($this->fixture->a->business);

    // Both fixture businesses use CUST-1; a second one in A is refused.
    expect(fn () => Customer::factory()->forShop($this->fixture->a->shops[1])->create(['code' => 'CUST-1']))
        ->toThrow(QueryException::class);
});

describe('credit accounts', function () {
    beforeEach(function () {
        app(TenantContext::class)->set($this->fixture->a->business);
        $this->customer = $this->fixture->a->customer;
        $this->customer->update(['customer_type' => 'wholesale', 'allow_credit' => true, 'credit_limit' => 1000]);
        $this->service = app(CreditAccountService::class);
    });

    it('opens one account per customer, whichever shop sells on credit', function () {
        $first = $this->service->resolveAccount($this->customer, $this->fixture->a->shops[0]);
        $second = $this->service->resolveAccount($this->customer, $this->fixture->a->shops[1]);

        expect($second->id)->toBe($first->id)
            ->and(CreditAccount::where('customer_id', $this->customer->id)->count())->toBe(1)
            ->and($first->shop_id)->toBe($this->fixture->a->shops[0]->id);
    });

    it('refuses a second account for the same customer', function () {
        $this->service->resolveAccount($this->customer, $this->fixture->a->shops[0]);

        expect(fn () => $this->service->createAccount($this->customer, $this->fixture->a->shops[1], [
            'credit_limit' => 500, 'status' => 'active', 'payment_terms_days' => 30, 'grace_period_days' => 7,
        ]))->toThrow(ValidationException::class);

        expect(fn () => CreditAccount::create([
            'customer_id' => $this->customer->id,
            'shop_id' => $this->fixture->a->shops[1]->id,
            'credit_limit' => 500,
            'current_balance' => 0,
            'status' => 'active',
            'payment_terms_days' => 30,
            'grace_period_days' => 7,
        ]))->toThrow(QueryException::class);
    });
});
