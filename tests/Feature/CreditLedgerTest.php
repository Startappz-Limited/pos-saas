<?php

use App\Actions\RecordCreditSale;
use App\Actions\SettleCreditSalePayment;
use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\CreditBalanceNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * Credit sales used to touch only the `sales` table: the credit account was
 * never debited and a payment never credited it, so a wholesaler's balance sat
 * at zero however much they owed and however much they settled. These tests pin
 * both halves of that loop.
 */
beforeEach(function () {
    Notification::fake();

    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    // SaleSource has no factory.
    $this->saleSource = SaleSource::firstOrCreate(
        ['name' => 'Walk-in'],
        ['is_active' => true, 'sort_order' => 1],
    );

    // customer_type must be forced — CustomerFactory randomises it, and credit
    // is wholesale-only.
    $this->customer = Customer::factory()->forShop($this->shop)->create([
        'customer_type' => 'wholesale',
        'allow_credit' => true,
        'credit_limit' => 10000,
        'credit_balance' => 0,
        'phone' => '+254712345678',
        'created_by' => $this->user->id,
    ]);

    Permission::findOrCreate('sales.full-access');
    Permission::findOrCreate('credit-sales.full-access');
    $this->user->givePermissionTo(['sales.full-access', 'credit-sales.full-access']);

    $this->actingAs($this->user);
});

function creditSalePayload(array $overrides = []): array
{
    return array_merge([
        'customer_id' => test()->customer->id,
        'source_id' => test()->saleSource->id,
        'delivery_location' => 'Nairobi CBD',
        'payment_method' => 'credit',
        'items' => [[
            'product_id' => Product::factory()->create([
                'shop_id' => test()->shop->id,
                'stock_quantity' => 50,
                'cost_price' => 40,
            ])->id,
            'quantity' => 2,
            'price' => 500.00,
        ]],
    ], $overrides);
}

describe('a credit sale debits the credit account', function () {
    it('posts a PURCHASE debit and raises the balance', function () {
        $this->post(route('sales.store'), creditSalePayload())->assertRedirect();

        $sale = Sale::where('payment_method', 'credit')->firstOrFail();
        $account = CreditAccount::where('customer_id', $this->customer->id)->firstOrFail();
        $transaction = CreditTransaction::where('reference_id', $sale->id)->firstOrFail();

        expect($transaction->type)->toBe(CreditTransactionType::PURCHASE)
            ->and((float) $transaction->debit)->toBe(1000.0)
            ->and((float) $transaction->balance_before)->toBe(0.0)
            ->and((float) $transaction->balance_after)->toBe(1000.0)
            ->and((float) $account->current_balance)->toBe(1000.0)
            ->and((float) $account->available_credit)->toBe(9000.0);
    });

    it('auto-creates a credit account for a wholesaler who has none', function () {
        expect(CreditAccount::count())->toBe(0);

        $this->post(route('sales.store'), creditSalePayload())->assertRedirect();

        $account = CreditAccount::where('customer_id', $this->customer->id)->first();

        expect($account)->not->toBeNull()
            ->and($account->shop_id)->toBe($this->shop->id)
            ->and($account->payment_terms_days)->toBe(config('credit.default_payment_terms_days'));
    });

    it('mirrors the balance onto the customer, which is what the app reads', function () {
        $this->post(route('sales.store'), creditSalePayload())->assertRedirect();

        expect((float) $this->customer->fresh()->credit_balance)->toBe(1000.0);
    });

    it('dates the debt by the account payment terms', function () {
        $this->post(route('sales.store'), creditSalePayload())->assertRedirect();

        $transaction = CreditTransaction::where('type', CreditTransactionType::PURCHASE)->firstOrFail();
        $account = CreditAccount::firstOrFail();

        expect($transaction->due_date->toDateString())
            ->toBe(now()->addDays($account->payment_terms_days)->toDateString());
    });

    it('tells the customer their real balance, not the pre-sale one', function () {
        Notification::fake();

        $this->post(route('sales.store'), creditSalePayload())->assertRedirect();

        Notification::assertSentTo(
            $this->customer,
            CreditBalanceNotification::class,
            function ($notification, $channels, $notifiable): bool {
                return (float) $notifiable->credit_balance === 1000.0;
            },
        );
    });

    it('leaves cash sales out of the ledger entirely', function () {
        $this->post(route('sales.store'), creditSalePayload(['payment_method' => 'cash']))
            ->assertRedirect();

        expect(CreditTransaction::count())->toBe(0)
            ->and(CreditAccount::count())->toBe(0);
    });

    it('records the sale even when it exceeds the credit limit, and warns', function () {
        $this->customer->update(['credit_limit' => 100]);

        $response = $this->postJson(route('sales.store'), creditSalePayload());

        $response->assertOk()->assertJsonPath('data.over_credit_limit', true);

        expect((float) CreditAccount::firstOrFail()->current_balance)->toBe(1000.0);
    });
});

describe('collecting a payment settles the credit account', function () {
    beforeEach(function () {
        $this->post(route('sales.store'), creditSalePayload());
        $this->sale = Sale::where('payment_method', 'credit')->firstOrFail();
        $this->account = CreditAccount::firstOrFail();
    });

    it('posts a PAYMENT credit and lowers the balance', function () {
        $this->postJson(route('sales.collectPayment', $this->sale), [
            'payments' => [['amount' => 400.00, 'payment_method' => 'cash']],
        ])->assertOk();

        $payment = CreditTransaction::where('type', CreditTransactionType::PAYMENT)->firstOrFail();

        expect((float) $payment->credit)->toBe(400.0)
            ->and((float) $payment->balance_after)->toBe(600.0)
            ->and((float) $this->account->fresh()->current_balance)->toBe(600.0);
    });

    it('clears the account when the balance is settled in full', function () {
        $this->postJson(route('sales.collectPayment', $this->sale), [
            'payments' => [['amount' => 1000.00, 'payment_method' => 'cash']],
        ])->assertOk();

        expect((float) $this->account->fresh()->current_balance)->toBe(0.0)
            ->and((float) $this->account->fresh()->available_credit)->toBe(10000.0)
            ->and((float) $this->customer->fresh()->credit_balance)->toBe(0.0)
            ->and($this->account->fresh()->last_payment_at)->not->toBeNull();
    });

    it('settles across several partial payments', function () {
        foreach ([300, 300, 400] as $amount) {
            $this->postJson(route('sales.collectPayment', $this->sale->fresh()), [
                'payments' => [['amount' => $amount, 'payment_method' => 'cash']],
            ])->assertOk();
        }

        expect(CreditTransaction::where('type', CreditTransactionType::PAYMENT)->count())->toBe(3)
            ->and((float) $this->account->fresh()->current_balance)->toBe(0.0);
    });

    it('still settles after credit is revoked, without re-granting it', function () {
        // Revoking credit must never strand a debt that cannot be paid off —
        // nor should paying that debt off quietly restore the customer's credit.
        $this->customer->update(['allow_credit' => false]);

        $this->postJson(route('sales.collectPayment', $this->sale), [
            'payments' => [['amount' => 1000.00, 'payment_method' => 'cash']],
        ])->assertOk();

        expect((float) $this->account->fresh()->current_balance)->toBe(0.0)
            ->and($this->customer->fresh()->allow_credit)->toBeFalse();
    });

    it('releases the debt when the credit sale is voided', function () {
        $this->post(route('sales.void', $this->sale), ['void_reason' => 'Keyed in error']);

        expect((float) $this->account->fresh()->current_balance)->toBe(0.0)
            ->and(CreditTransaction::where('type', CreditTransactionType::ADJUSTMENT_CREDIT)->count())->toBe(1);
    });
});

describe('idempotency', function () {
    it('does not double-charge when the sale is replayed', function () {
        $this->post(route('sales.store'), creditSalePayload());
        $sale = Sale::where('payment_method', 'credit')->firstOrFail();

        app(RecordCreditSale::class)->handle($sale);
        app(RecordCreditSale::class)->handle($sale);

        expect(CreditTransaction::where('type', CreditTransactionType::PURCHASE)->count())->toBe(1)
            ->and((float) CreditAccount::firstOrFail()->current_balance)->toBe(1000.0);
    });

    it('does not double-credit when a payment is replayed', function () {
        $this->post(route('sales.store'), creditSalePayload());
        $sale = Sale::where('payment_method', 'credit')->firstOrFail();

        $this->postJson(route('sales.collectPayment', $sale), [
            'payments' => [['amount' => 500.00, 'payment_method' => 'cash']],
        ])->assertOk();

        $payment = SalePayment::firstOrFail();
        app(SettleCreditSalePayment::class)->handle($sale->fresh(), $payment);

        expect(CreditTransaction::where('type', CreditTransactionType::PAYMENT)->count())->toBe(1)
            ->and((float) CreditAccount::firstOrFail()->current_balance)->toBe(500.0);
    });

    it('does not reverse a voided sale twice', function () {
        $this->post(route('sales.store'), creditSalePayload());
        $sale = Sale::where('payment_method', 'credit')->firstOrFail();

        app(SettleCreditSalePayment::class)->reverse($sale, 'first');
        app(SettleCreditSalePayment::class)->reverse($sale->fresh(), 'second');

        expect(CreditTransaction::where('type', CreditTransactionType::ADJUSTMENT_CREDIT)->count())->toBe(1)
            ->and((float) CreditAccount::firstOrFail()->current_balance)->toBe(0.0);
    });
});

describe('the API sale path behaves identically to the web one', function () {
    it('debits the account for a credit sale created over the API', function () {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'stock_quantity' => 50,
            'cost_price' => 40,
        ]);

        $this->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'source_id' => $this->saleSource->id,
            'delivery_location' => 'Nairobi CBD',
            'payment_method' => 'credit',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 500.00]],
        ])->assertCreated();

        expect((float) CreditAccount::firstOrFail()->current_balance)->toBe(1000.0);
    });

    it('settles over the API collect-payment endpoint', function () {
        $this->post(route('sales.store'), creditSalePayload());
        $sale = Sale::where('payment_method', 'credit')->firstOrFail();

        $this->postJson("/api/sales/{$sale->uuid}/collect-payment", [
            'payments' => [['amount' => 1000.00, 'payment_method' => 'cash']],
        ])->assertOk();

        expect((float) CreditAccount::firstOrFail()->current_balance)->toBe(0.0);
    });
});
