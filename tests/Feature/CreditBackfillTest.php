<?php

use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    Http::preventStrayRequests();

    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);

    $this->customer = Customer::factory()->forShop($this->shop)->create([
        'customer_type' => 'wholesale',
        'allow_credit' => true,
        'credit_limit' => 10000,
        'credit_balance' => 0,
        'phone' => '+254712345678',
    ]);

    // A historic credit sale, written the way the old code wrote them: unpaid on
    // `sales`, with nothing at all in the credit ledger.
    $this->sale = Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $this->customer->id,
        'payment_method' => 'credit',
        'payment_status' => 'partial',
        'status' => 'completed',
        'total_amount' => 1000.00,
        'paid_amount' => 400.00,
        'balance_due' => 600.00,
        'completed_at' => now()->subDays(10),
    ]);

    SalePayment::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $this->sale->id,
        'payment_number' => 'PAY-HISTORIC',
        'amount' => 400.00,
        'payment_method' => 'cash',
        'paid_at' => now()->subDays(5),
    ]);
});

it('never messages a customer — it is a reconciliation, not a re-run of the sale', function () {
    // The whole point: replaying months of historic sales must not fire months
    // of WhatsApp invoices and payment receipts at customers.
    $this->artisan('credit:backfill')->assertSuccessful();

    Notification::assertNothingSent();
    Queue::assertNothingPushed();
});

it('rebuilds the account from the sale and its payments', function () {
    $this->artisan('credit:backfill')->assertSuccessful();

    $account = CreditAccount::where('customer_id', $this->customer->id)->firstOrFail();

    // 1,000 charged, 400 already collected → 600 still outstanding.
    expect((float) $account->current_balance)->toBe(600.0)
        ->and((float) $this->customer->fresh()->credit_balance)->toBe(600.0)
        ->and(CreditTransaction::count())->toBe(2);
});

it('does not re-grant credit an admin has revoked', function () {
    // Reconciling history must not undo a deliberate decision to cut a
    // defaulting wholesaler off.
    $this->customer->update(['allow_credit' => false]);

    $this->artisan('credit:backfill')->assertSuccessful();

    expect($this->customer->fresh()->allow_credit)->toBeFalse()
        // ...but the debt is still recorded.
        ->and((float) $this->customer->fresh()->credit_balance)->toBe(600.0);
});

it('is safe to run twice', function () {
    $this->artisan('credit:backfill')->assertSuccessful();
    $this->artisan('credit:backfill')->assertSuccessful();

    expect(CreditTransaction::count())->toBe(2)
        ->and((float) CreditAccount::firstOrFail()->current_balance)->toBe(600.0);
});

it('writes nothing on a dry run', function () {
    $this->artisan('credit:backfill --dry-run')->assertSuccessful();

    expect(CreditTransaction::count())->toBe(0)
        ->and(CreditAccount::count())->toBe(0);
});

it('skips voided sales', function () {
    $this->sale->update(['status' => 'voided']);

    $this->artisan('credit:backfill')->assertSuccessful();

    expect(CreditTransaction::count())->toBe(0);
});
