<?php

use App\Enums\CreditAccountStatus;
use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function actingAsCreditUser(array $extraPermissions = []): array
{
    $shop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->attach($shop);

    $permissions = array_values(array_unique(array_merge(
        ['credit-sales.view', 'credit-sales.create', 'credit-sales.update', 'credit-sales.collect', 'credit-sales.write-off'],
        $extraPermissions,
    )));

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $user->givePermissionTo($permissions);
    actingAs($user);

    return [$shop, $user];
}

test('authorized user can view credit accounts', function () {
    actingAsCreditUser();

    get(route('credit-accounts.index'))->assertOk();
    get(route('credit-accounts.create'))->assertOk();
    get(route('credit-accounts.overdue'))->assertOk();
    get(route('credit-accounts.agingReport'))->assertOk();
    get(route('credit-transactions.index'))->assertOk();
    get(route('credit-transactions.overdue'))->assertOk();
});

test('credit account can be created for a wholesale customer who is allowed credit', function () {
    [$shop] = actingAsCreditUser();
    $customer = Customer::factory()->withCredit(10000)->create(['shop_id' => $shop->id]);

    $response = post(route('credit-accounts.store'), [
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 10000,
        'payment_terms_days' => 21,
        'grace_period_days' => 5,
        'status' => CreditAccountStatus::ACTIVE->value,
    ]);

    $account = CreditAccount::first();

    $response->assertRedirect(route('credit-accounts.show', $account));
    expect($account)
        ->not->toBeNull()
        ->and((float) $account->credit_limit)->toBe(10000.0)
        ->and((float) $account->available_credit)->toBe(10000.0)
        ->and($account->status)->toBe(CreditAccountStatus::ACTIVE);

    expect($customer->fresh())
        ->allow_credit->toBeTrue()
        ->and((float) $customer->fresh()->credit_limit)->toBe(10000.0)
        ->and((float) $customer->fresh()->credit_balance)->toBe(0.0);

    get(route('credit-accounts.show', $account))->assertOk();
    get(route('credit-accounts.edit', $account))->assertOk();
    get(route('credit-accounts.transactions', $account))->assertOk();
});

test('credit account create page lists only customers who are allowed credit', function () {
    [$shop] = actingAsCreditUser();
    $allowedCustomer = Customer::factory()->withCredit(10000)->create(['shop_id' => $shop->id]);
    $blockedCustomer = Customer::factory()->wholesale()->create([
        'shop_id' => $shop->id,
        'allow_credit' => false,
        'credit_limit' => 0,
    ]);

    $response = get(route('credit-accounts.create'))->assertOk();
    $customerIds = $response->viewData('customers')->pluck('id')->all();

    expect($customerIds)
        ->toContain($allowedCustomer->id)
        ->not->toContain($blockedCustomer->id);
});

test('wholesale customers not allowed credit cannot receive credit accounts', function () {
    [$shop] = actingAsCreditUser();
    $customer = Customer::factory()->wholesale()->create([
        'shop_id' => $shop->id,
        'allow_credit' => false,
        'credit_limit' => 0,
    ]);

    post(route('credit-accounts.store'), [
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 10000,
        'payment_terms_days' => 21,
        'grace_period_days' => 5,
        'status' => CreditAccountStatus::ACTIVE->value,
    ])->assertSessionHasErrors('customer_id');

    expect(CreditAccount::count())->toBe(0);
});

test('retail customers cannot receive credit accounts', function () {
    [$shop] = actingAsCreditUser();
    $customer = Customer::factory()->create([
        'shop_id' => $shop->id,
        'customer_type' => 'retail',
        'allow_credit' => true,
        'credit_limit' => 10000,
    ]);

    post(route('credit-accounts.store'), [
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 10000,
        'payment_terms_days' => 21,
        'grace_period_days' => 5,
        'status' => CreditAccountStatus::ACTIVE->value,
    ])->assertSessionHasErrors('customer_id');

    expect(CreditAccount::count())->toBe(0)
        ->and($customer->fresh()->allow_credit)->toBeTrue()
        ->and((float) $customer->fresh()->credit_limit)->toBe(10000.0);
});

test('retail customers cannot be created with credit enabled', function () {
    actingAsCreditUser(['customers.create']);

    post(route('customers.store'), [
        'name' => 'Retail Credit Customer',
        'email' => 'retail-credit@example.com',
        'phone' => '555-0101',
        'address' => 'Market Street',
        'customer_type' => 'retail',
        'allow_credit' => '1',
        'credit_limit' => 10000,
        'status' => 'active',
    ])->assertSessionHasErrors('allow_credit');

    expect(Customer::where('email', 'retail-credit@example.com')->exists())->toBeFalse();
});

test('retail customers cannot be updated to enable credit', function () {
    [$shop] = actingAsCreditUser(['customers.update']);
    $customer = Customer::factory()->create([
        'shop_id' => $shop->id,
        'customer_type' => 'retail',
        'allow_credit' => false,
        'credit_limit' => 0,
    ]);

    put(route('customers.update', $customer), [
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'address' => $customer->address,
        'customer_type' => 'retail',
        'allow_credit' => '1',
        'credit_limit' => 10000,
        'status' => 'active',
    ])->assertSessionHasErrors('allow_credit');

    expect($customer->fresh())
        ->allow_credit->toBeFalse()
        ->and((float) $customer->fresh()->credit_limit)->toBe(0.0);
});

test('credit transactions update account and customer balances', function () {
    [$shop] = actingAsCreditUser();
    $customer = Customer::factory()->withCredit(10000)->create(['shop_id' => $shop->id]);
    $account = CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 10000,
        'current_balance' => 0,
        'available_credit' => 10000,
        'status' => CreditAccountStatus::ACTIVE,
    ]);

    post(route('credit-transactions.store'), [
        'credit_account_id' => $account->id,
        'type' => CreditTransactionType::PURCHASE->value,
        'amount' => 2500,
        'due_date' => now()->addDays(30)->toDateString(),
        'description' => 'Invoice sale',
    ])->assertRedirect(route('credit-accounts.show', $account));

    $purchase = CreditTransaction::first();

    expect($purchase)
        ->type->toBe(CreditTransactionType::PURCHASE)
        ->and((float) $purchase->debit)->toBe(2500.0)
        ->and((float) $purchase->balance_before)->toBe(0.0)
        ->and((float) $purchase->balance_after)->toBe(2500.0);

    get(route('credit-transactions.show', $purchase))->assertOk();

    expect($account->fresh())
        ->and((float) $account->fresh()->current_balance)->toBe(2500.0)
        ->and((float) $account->fresh()->available_credit)->toBe(7500.0);
    expect((float) $customer->fresh()->credit_balance)->toBe(2500.0);

    post(route('credit-transactions.store'), [
        'credit_account_id' => $account->id,
        'type' => CreditTransactionType::PAYMENT->value,
        'amount' => 900,
        'description' => 'Cash payment',
    ])->assertRedirect(route('credit-accounts.show', $account));

    $payment = CreditTransaction::latest('id')->first();

    expect($payment)
        ->type->toBe(CreditTransactionType::PAYMENT)
        ->and((float) $payment->credit)->toBe(900.0)
        ->and((float) $payment->balance_before)->toBe(2500.0)
        ->and((float) $payment->balance_after)->toBe(1600.0);

    expect((float) $account->fresh()->current_balance)->toBe(1600.0)
        ->and((float) $account->fresh()->available_credit)->toBe(8400.0)
        ->and((float) $customer->fresh()->credit_balance)->toBe(1600.0);
});

test('credit account statement can be filtered and downloaded as pdf', function () {
    [$shop] = actingAsCreditUser();
    $customer = Customer::factory()->withCredit(10000)->create(['shop_id' => $shop->id]);
    $account = CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 10000,
        'current_balance' => 3700,
        'available_credit' => 6300,
        'status' => CreditAccountStatus::ACTIVE,
    ]);

    CreditTransaction::factory()->forAccount($account)->purchase(3000)->create([
        'description' => 'Opening purchase',
        'balance_before' => 0,
        'balance_after' => 3000,
        'created_at' => now()->subDays(10),
    ]);
    CreditTransaction::factory()->forAccount($account)->payment(500)->create([
        'description' => 'Part payment',
        'balance_before' => 3000,
        'balance_after' => 2500,
        'created_at' => now()->subDays(4),
    ]);
    $filteredPurchase = CreditTransaction::factory()->forAccount($account)->purchase(1200)->create([
        'description' => 'Filtered purchase',
        'balance_before' => 2500,
        'balance_after' => 3700,
        'created_at' => now()->subDay(),
    ]);

    $filters = [
        'date_from' => now()->subDays(2)->toDateString(),
        'date_to' => today()->toDateString(),
        'type' => CreditTransactionType::PURCHASE->value,
    ];

    $response = get(route('credit-accounts.statement', ['creditAccount' => $account, ...$filters]))->assertOk();

    expect($response->viewData('transactions'))
        ->toHaveCount(1)
        ->first()->id->toBe($filteredPurchase->id)
        ->and($response->viewData('openingBalance'))->toBe(2500.0)
        ->and($response->viewData('closingBalance'))->toBe(3700.0)
        ->and($response->viewData('totalDebits'))->toBe(1200.0)
        ->and($response->viewData('totalCredits'))->toBe(0.0);

    get(route('credit-accounts.statement.pdf', ['creditAccount' => $account, ...$filters]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('credit account management actions update status and limits', function () {
    [$shop] = actingAsCreditUser();
    $customer = Customer::factory()->withCredit(5000)->create(['shop_id' => $shop->id]);
    $account = CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
        'current_balance' => 0,
        'available_credit' => 5000,
        'status' => CreditAccountStatus::ACTIVE,
    ]);

    post(route('credit-accounts.adjustLimit', $account), [
        'credit_limit' => 12000,
        'reason' => 'Approved seasonal increase',
    ])->assertRedirect();

    expect((float) $account->fresh()->credit_limit)->toBe(12000.0)
        ->and((float) $customer->fresh()->credit_limit)->toBe(12000.0);

    post(route('credit-accounts.suspend', $account), [
        'reason' => 'Customer requested hold',
    ])->assertRedirect();

    expect($account->fresh())
        ->status->toBe(CreditAccountStatus::SUSPENDED)
        ->and($account->fresh()->is_suspended)->toBeTrue();

    post(route('credit-accounts.reactivate', $account))->assertRedirect();

    expect($account->fresh())
        ->status->toBe(CreditAccountStatus::ACTIVE)
        ->and($account->fresh()->is_suspended)->toBeFalse();

    put(route('credit-accounts.update', $account), [
        'credit_limit' => 9000,
        'payment_terms_days' => 45,
        'grace_period_days' => 10,
        'status' => CreditAccountStatus::ACTIVE->value,
    ])->assertRedirect(route('credit-accounts.show', $account));

    expect($account->fresh())
        ->payment_terms_days->toBe(45)
        ->and($account->fresh()->grace_period_days)->toBe(10)
        ->and((float) $account->fresh()->credit_limit)->toBe(9000.0);

    delete(route('credit-accounts.destroy', $account))->assertRedirect(route('credit-accounts.index'));
    assertSoftDeleted('credit_accounts', ['id' => $account->id]);
});
