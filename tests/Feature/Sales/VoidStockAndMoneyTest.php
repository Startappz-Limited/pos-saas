<?php

use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Jobs\PushShopStockJob;
use App\Models\AuditLog;
use App\Models\CashPayout;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\RefundDue;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleVoidRequest;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Voiding gives stock back and hands the money back (stage 10, step 4;
 * decided 4 November 2026): lines are released into their batches; cash the
 * sale's open session counted leaves it by recalculation; other cash is paid
 * out of the voider's own open session, else owed; card, M-Pesa, bank and
 * cheque payments are owed; credit is never refunded. Refunds owed are paid
 * later from the payer's own session (cash) or recorded (anything else).
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->member = function (string|Role $role): User {
        $user = User::factory()->create();
        $user->assignRole($role === 'owner' ? ownerRole() : $role);
        $user->shops()->attach($this->shop);

        return $user;
    };
    $this->owner = ($this->member)('owner');
    $this->manager = ($this->member)('manager');   // refunds.approve and cash-registers.payout, no sales.void
    $this->cashier = ($this->member)('cashier');
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => 40, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->ledger->receive($this->shop->id, $this->variation, 10, 40, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $this->session = fn (User $user, string $status = 'open') => CashRegister::factory()->create([
        'shop_id' => $this->shop->id, 'user_id' => $user->id, 'status' => $status, 'opening_balance' => 1000,
        'closed_at' => $status === 'closed' ? now() : null,
    ]);
    $this->till = ($this->session)($this->cashier);
    $this->sell = function (array $attributes = [], int $quantity = 3) {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id, 'register_id' => $this->till->id, 'status' => 'completed', 'completed_at' => now(),
            'payment_method' => 'cash', 'total_amount' => 300, 'paid_amount' => 300, 'tax_amount' => 0, ...$attributes,
        ]);
        $line = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => $quantity, 'unit_price' => 100, 'line_total' => 100 * $quantity, 'taxable_amount' => 100 * $quantity, 'status' => 'completed',
        ]);
        $this->ledger->sellItem($line, $this->variation);
        $sale->register?->updateSalesTotals();

        return $sale->fresh();
    };
    $this->pay = fn (Sale $sale, string $method, float $amount, ?string $reference = null) => SalePayment::create([
        'business_id' => $sale->business_id ?? testBusiness()->id, 'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id,
        'payment_number' => 'PAY-'.Str::random(6), 'amount' => $amount, 'payment_method' => $method, 'reference' => $reference, 'paid_at' => now(),
    ]);
    $this->void = function (User $user, Sale $sale) {
        Sanctum::actingAs($user, ['*']);

        return $this->postJson("/api/sales/{$sale->getRouteKey()}/void", ['void_reason' => 'Keyed in error']);
    };
    $this->stock = fn (): int => $this->ledger->quantity($this->shop->id, $this->variation->id);
    // A sale can be voided after its session closes only under a window other than `session` (step 2).
    $this->closeTill = function (): void {
        testBusiness()->update(['settings' => array_replace_recursive(testBusiness()->settings ?? [], ['sales' => ['void_window' => 'end_of_day']])]);
        $this->till->update(['status' => 'closed', 'closed_at' => now(), 'closing_balance' => 1300, 'expected_balance' => 1300, 'variance' => 0]);
    };
});

describe('stock', function () {
    it('gives the stock back into its batches on the flat API and the web', function () {
        $api = ($this->sell)();
        $web = ($this->sell)();
        $cost = (float) $api->total_cost;
        expect(($this->stock)())->toBe(4);

        ($this->void)($this->owner, $api)->assertOk()->assertJsonPath('message', 'Sale voided. 3 items are back in stock.');
        $this->actingAs($this->owner)->post(route('sales.void', $web), ['void_reason' => 'Duplicate'])->assertSessionHas('success');

        expect(($this->stock)())->toBe(10)
            ->and((int) DB::table('cost_layers')->where('variation_id', $this->variation->id)->sum('remaining_quantity'))->toBe(10)
            ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::SALE_VOID->value)->count())->toBe(2)
            ->and((float) $api->fresh()->total_cost)->toBe($cost); // cost and profit stay as sold
    });

    it('never moves a sale voided before this stage when stock arrives', function () {
        $sale = ($this->sell)([], 12); // 2 below zero, provisional
        $sale->update(['status' => 'voided']);   // as a void before this stage left it: allocations kept
        $before = (float) DB::table('sale_items')->where('sale_id', $sale->id)->value('total_cost');

        $this->ledger->receive($this->shop->id, $this->variation, 5, 55, StockMovementType::INTAKE);

        expect((bool) DB::table('cost_allocations')->where('is_provisional', true)->exists())->toBeTrue()
            ->and((float) DB::table('sale_items')->where('sale_id', $sale->id)->value('total_cost'))->toBe($before);
    });

    it('pushes the restored stock to the shop\'s online stores, and audits the void', function () {
        migrateAuditDatabase();
        $this->shop->update(['settings' => ['integrations' => ['woocommerce' => ['enabled' => true, 'store_url' => 'https://store.test', 'consumer_key' => Crypt::encrypt('ck'), 'consumer_secret' => Crypt::encrypt('cs')]]]]);
        $sale = ($this->sell)();
        Queue::fake();

        ($this->void)($this->owner, $sale)->assertOk();

        Queue::assertPushed(PushShopStockJob::class, fn (PushShopStockJob $job) => $job->shopId === $this->shop->id);
        expect(AuditLog::query()->where('auditable_type', Sale::class)->where('auditable_id', $sale->id)->get()
            ->contains(fn (AuditLog $row) => ($row->new_values['status'] ?? null) === 'voided'))->toBeTrue();
    });

    it('refuses a void while a return isn\'t rejected (422), and allows it once the only return is rejected', function () {
        $sale = ($this->sell)();
        $return = SaleReturn::factory()->create(['sale_id' => $sale->id, 'shop_id' => $this->shop->id, 'status' => ReturnStatus::PENDING]);

        ($this->void)($this->owner, $sale)->assertUnprocessable()
            ->assertJsonPath('code', 'sale_has_returns')
            ->assertJsonPath('message', 'This sale has returns. Process the return instead.');

        $return->update(['status' => ReturnStatus::REJECTED]);
        ($this->void)($this->owner, $sale)->assertOk();
    });
});

describe('cash', function () {
    it('takes a cash sale out of its own open session by recalculating it, with no payout or refund', function () {
        $sale = ($this->sell)();
        expect((float) $this->till->fresh()->calculateExpectedBalance())->toBe(1300.0);

        ($this->void)($this->owner, $sale)->assertOk();

        expect((float) $this->till->fresh()->calculateExpectedBalance())->toBe(1000.0)
            ->and(CashPayout::count())->toBe(0)
            ->and(RefundDue::count())->toBe(0);
    });

    it('leaves a closed session as it was, and pays the cash out of the voider\'s own open session', function () {
        $sale = ($this->sell)();
        ($this->closeTill)();
        $own = ($this->session)($this->owner);

        ($this->void)($this->owner, $sale)->assertOk()->assertJsonPath('money.paid_out.0.amount', '300.00');

        $till = $this->till->fresh();
        expect([(float) $till->total_cash_sales, (float) $till->expected_balance, (float) $till->variance])->toBe([300.0, 1300.0, 0.0])
            ->and((float) $own->fresh()->calculateExpectedBalance())->toBe(700.0)
            ->and(RefundDue::count())->toBe(0);
    });

    it('owes the cash when the voider has no open session of their own, never taking it from someone else\'s', function () {
        $sale = ($this->sell)();
        ($this->closeTill)();
        $otherOpen = ($this->session)($this->cashier);

        ($this->void)($this->owner, $sale)->assertOk()->assertJsonPath('money.refunds_due.0.method', 'cash');

        $refund = RefundDue::sole();
        expect((float) $refund->amount)->toBe(300.0)
            ->and(CashPayout::count())->toBe(0)
            ->and((float) $otherOpen->fresh()->calculateExpectedBalance())->toBe(1000.0)
            ->and($this->manager->notifications()->where('type', ApprovalRequested::class)->sole()->data['kind'])->toBe('refund_due')
            ->and($this->owner->notifications()->where('type', ApprovalRequested::class)->count())->toBe(0);
    });

    it('lets someone with refunds.approve pay a cash refund owed out of their own open session, later', function () {
        $sale = ($this->sell)();
        ($this->closeTill)();
        ($this->void)($this->owner, $sale)->assertOk();
        $refund = RefundDue::sole();

        $this->actingAs($this->cashier)->post(route('refunds-due.pay', $refund), ['resolution' => 'paid'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $refund), ['resolution' => 'paid'])->assertSessionHas('error', 'Open your register to pay a cash refund.');

        $managerSession = ($this->session)($this->manager);
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $refund), ['resolution' => 'paid'])->assertSessionHas('success');

        expect($refund->fresh()->status)->toBe('paid')
            ->and($refund->fresh()->resolved_by)->toBe($this->manager->id)
            ->and((float) $managerSession->fresh()->calculateExpectedBalance())->toBe(700.0)
            ->and($this->manager->notifications()->where('type', ApprovalRequested::class)->sole()->data['resolved'])->toBe('paid');
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $refund), ['resolution' => 'paid'])->assertForbidden();
    });

    it('owes the cash of an approved void request, even when the approver has a session open', function () {
        $sale = ($this->sell)();
        ($this->closeTill)();
        // A void requested while the session was open, approved after it closed.
        $request = SaleVoidRequest::factory()->create(['sale_id' => $sale->id, 'shop_id' => $this->shop->id, 'requested_by' => $this->cashier->id, 'requested_at' => now()->subMinutes(5)]);
        $this->till->update(['closed_at' => now()->addMinute()]);
        ($this->session)($this->owner);

        $this->actingAs($this->owner)->post(route('void-requests.approve', $request))->assertSessionHas('success');

        expect($sale->fresh()->status)->toBe('voided')
            ->and(CashPayout::count())->toBe(0)
            ->and(RefundDue::sole()->method)->toBe('cash');
    });
});

describe('other methods', function () {
    it('owes card, M-Pesa, bank transfer and cheque payments with their method and reference', function () {
        $sale = ($this->sell)(['status' => 'pending', 'completed_at' => null, 'payment_method' => 'mobile_money', 'paid_amount' => 0]);
        foreach ([['card', 50, 'CARD-1'], ['mobile_money', 60, 'QWE123'], ['bank_transfer', 70, 'BT-9'], ['cheque', 80, 'CHQ-4']] as [$method, $amount, $reference]) {
            ($this->pay)($sale, $method, $amount, $reference);
        }
        $sale->update(['paid_amount' => 260]);

        ($this->void)($this->owner, $sale->fresh())->assertOk();

        expect(RefundDue::orderBy('id')->get()->map(fn (RefundDue $r) => [$r->method, (float) $r->amount, $r->reference])->all())->toBe([
            ['card', 50.0, 'CARD-1'], ['mobile_money', 60.0, 'QWE123'], ['bank_transfer', 70.0, 'BT-9'], ['cheque', 80.0, 'CHQ-4'],
        ]);

        // M-Pesa is refunded outside the system and recorded with its reference; a cheque may come back instead.
        $mpesa = RefundDue::where('method', 'mobile_money')->sole();
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $mpesa), ['resolution' => 'paid'])->assertSessionHas('error');
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $mpesa), ['resolution' => 'paid', 'reference' => 'REV-77'])->assertSessionHas('success');
        $cheque = RefundDue::where('method', 'cheque')->sole();
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $cheque), ['resolution' => 'cheque_returned'])->assertSessionHas('success');
        $card = RefundDue::where('method', 'card')->sole();
        $this->actingAs($this->manager)->post(route('refunds-due.pay', $card), ['resolution' => 'cheque_returned'])->assertSessionHas('error');

        expect($mpesa->fresh()->resolved_reference)->toBe('REV-77')
            ->and([$cheque->fresh()->status, $cheque->fresh()->resolution])->toBe(['settled', 'cheque_returned']);
        $this->actingAs($this->manager)->get(route('refunds-due.index'))->assertOk()->assertSee('CARD-1')->assertSee('BT-9');
    });

    it('refunds payments on a voided credit sale by their method, and never the credit itself', function () {
        $sale = ($this->sell)(['payment_method' => 'credit', 'paid_amount' => 0]);
        ($this->pay)($sale, 'mobile_money', 100, 'MP-1');
        $sale->update(['paid_amount' => 100]);

        ($this->void)($this->owner, $sale->fresh())->assertOk();

        expect(RefundDue::sole()->only(['method', 'reference']))->toBe(['method' => 'mobile_money', 'reference' => 'MP-1'])
            ->and((float) RefundDue::sole()->amount)->toBe(100.0);
    });

    it('voids a part-paid pending sale days later: stock back, its cash out of the voider\'s session, its M-Pesa owed', function () {
        $sale = ($this->sell)(['status' => 'pending', 'completed_at' => null, 'payment_method' => 'cash', 'paid_amount' => 0]);
        ($this->pay)($sale, 'cash', 100);
        ($this->pay)($sale, 'mobile_money', 50, 'MP-2');
        $sale->update(['paid_amount' => 150]);
        $this->travel(3)->days();
        $own = ($this->session)($this->owner);

        ($this->void)($this->manager, $sale->fresh())->assertForbidden();
        ($this->void)($this->owner, $sale->fresh())->assertOk();

        expect(($this->stock)())->toBe(10)
            ->and((float) CashPayout::sole()->amount)->toBe(100.0)
            ->and(CashPayout::sole()->cash_register_id)->toBe($own->id)
            ->and(RefundDue::sole()->only(['method', 'reference']))->toBe(['method' => 'mobile_money', 'reference' => 'MP-2']);
    });
});

it('lets a manager record petty cash out of an open session, with a reason; a cashier can\'t', function () {
    $this->actingAs($this->cashier)->post(route('cash-registers.payouts.store', $this->till), ['amount' => 50, 'reason' => 'Milk'])->assertForbidden();
    $this->actingAs($this->manager)->post(route('cash-registers.payouts.store', $this->till), ['amount' => 50])->assertSessionHasErrors('reason');
    $this->actingAs($this->manager)->post(route('cash-registers.payouts.store', $this->till), ['amount' => 50, 'reason' => 'Milk'])->assertSessionHas('success');

    expect((float) $this->till->fresh()->calculateExpectedBalance())->toBe(950.0);
    $this->actingAs($this->manager)->get(route('cash-registers.show', $this->till))->assertOk()->assertSee('Milk');
});

it('puts a void of 5 units taken from two batches (3 at 60, 2 at 70) back where they came from, and the next sale is costed at 60 again', function () {
    // A fresh variation, so the beforeEach batch doesn't take part.
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $variation = $product->defaultVariation;
    $this->ledger->receive($this->shop->id, $variation, 3, 60, StockMovementType::INTAKE, ['received_at' => now()->subDays(2)]);
    $this->ledger->receive($this->shop->id, $variation, 2, 70, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $line = function (int $quantity) use ($product, $variation): SaleItem {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->till->id, 'status' => 'completed', 'completed_at' => now(), 'payment_method' => 'card', 'total_amount' => 100 * $quantity, 'paid_amount' => 0, 'tax_amount' => 0]);
        $item = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $product->id, 'shop_id' => $this->shop->id,
            'quantity' => $quantity, 'unit_price' => 100, 'line_total' => 100 * $quantity, 'taxable_amount' => 100 * $quantity, 'status' => 'completed',
        ]);
        $this->ledger->sellItem($item, $variation);

        return $item->fresh();
    };
    $sold = $line(5);
    expect($this->ledger->quantity($this->shop->id, $variation->id))->toBe(0);

    ($this->void)($this->owner, $sold->sale)->assertOk();

    $layers = DB::table('cost_layers')->where('variation_id', $variation->id)->orderBy('received_at')->pluck('remaining_quantity')->map(fn ($n) => (int) $n)->all();
    expect($layers)->toBe([3, 2])
        ->and($this->ledger->quantity($this->shop->id, $variation->id))->toBe(5)
        ->and((float) $line(1)->unit_cost)->toBe(60.0);
});

it('refuses a second void, and stock moves only once', function () {
    $sale = ($this->sell)();

    ($this->void)($this->owner, $sale)->assertOk();
    ($this->void)($this->owner, $sale->fresh())->assertForbidden();
    $this->actingAs($this->owner)->post(route('sales.void', $sale), ['void_reason' => 'Again'])->assertForbidden();

    expect(($this->stock)())->toBe(10)
        ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::SALE_VOID->value)->count())->toBe(1);
});
