<?php

use App\Enums\AlertType;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Jobs\PushShopStockJob;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\RefundDue;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\Stock\StockLedger;
use App\Support\Sales\StoreCancellations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
 * An online order cancelled in its store (stage 10, step 5b; decided 4
 * November 2026): voided automatically before dispatch, never a POS refund
 * owed for money the store took (marked refunded in store, or flagged when
 * the store reports no refund); part refunds and dispatched orders are
 * flagged, not voided.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    migrateAuditDatabase();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->owner->shops()->attach($this->shop);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->ledger->receive($this->shop->id, $this->variation, 10, 40, StockMovementType::INTAKE);
    $this->convert = function (string $platform, array $platformData, string $status = 'processing') {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id, 'register_id' => null, 'status' => 'pending', 'completed_at' => null,
            'payment_method' => 'cash', 'is_cod' => true, 'total_amount' => 300, 'paid_amount' => 0, 'tax_amount' => 0,
        ]);
        $line = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => 3, 'unit_price' => 100, 'line_total' => 300, 'taxable_amount' => 300, 'status' => 'pending',
        ]);
        $this->ledger->sellItem($line, $this->variation);
        EcommerceOrder::factory()->create([
            'shop_id' => $this->shop->id, 'platform' => $platform, 'platform_order_id' => '101', 'order_number' => '#101',
            'status' => $status, 'payment_method' => 'cod', 'total' => 300, 'platform_data' => $platformData, 'sale_id' => $sale->id,
        ]);

        return $sale->fresh();
    };
    $this->woo = fn (array $order) => app(WooCommerceOrderSyncService::class)->upsertOrder($this->shop, [
        'id' => 101, 'number' => '101', 'total' => '300.00', 'line_items' => [], 'billing' => [], 'shipping' => [], ...$order,
    ]);
    $this->shopify = fn (array $order) => app(ShopifyOrderSyncService::class)->upsertOrder($this->shop, [
        'id' => 101, 'name' => '#101', 'order_number' => 101, 'total_price' => '300.00', 'currency' => 'KES', 'line_items' => [], ...$order,
    ]);
    $this->stock = fn (): int => $this->ledger->quantity($this->shop->id, $this->variation->id);
    $this->reviews = fn (Sale $sale) => Alert::query()->where('alertable_type', Sale::class)->where('alertable_id', $sale->id)->where('type', AlertType::SALE_REVIEW->value)->get();
    $this->ownerNotices = fn () => $this->owner->notifications()->where('type', ApprovalRequested::class)->get();
});

it('voids a cancelled processing WooCommerce order\'s cash-on-delivery sale: stock back, no person, audited as automatic', function () {
    $sale = ($this->convert)('woocommerce', ['status' => 'processing']);
    expect(($this->stock)())->toBe(7);

    ($this->woo)(['status' => 'cancelled']);

    $sale->refresh();
    expect($sale->status)->toBe('voided')
        ->and($sale->voided_by)->toBeNull()
        ->and($sale->void_reason)->toBe('Order cancelled in Woocommerce (order #101).')
        ->and(($this->stock)())->toBe(10)
        ->and(RefundDue::count())->toBe(0)
        ->and(AuditLog::query()->where('auditable_type', Sale::class)->where('auditable_id', $sale->id)->get()
            ->contains(fn (AuditLog $row) => in_array('automatic', $row->tags ?? [], true)))->toBeTrue();
});

it('marks money the store took and refunded "refunded in store", and never owes it again', function () {
    $sale = ($this->convert)('woocommerce', ['status' => 'processing', 'date_paid' => '2026-11-04T08:00:00']);

    ($this->woo)(['status' => 'cancelled', 'date_paid' => '2026-11-04T08:00:00', 'refunds' => [['id' => 55, 'total' => '-300.00']]]);

    $store = RefundDue::sole();
    expect($sale->fresh()->status)->toBe('voided')
        ->and([$store->status, $store->resolution, $store->method, $store->reference, (float) $store->amount])->toBe(['settled', 'refunded_in_store', 'store', '55', 300.0]);

    $this->actingAs($this->owner)->post(route('sales.refunds-due.store', $sale), ['amount' => 300, 'method' => 'bank_transfer'])
        ->assertSessionHas('error', 'The store already refunded this sale.');
    expect(RefundDue::count())->toBe(1);
});

it('voids a paid order the store reports no refund for, owes nothing, flags it and tells the owners; the owner can record a refund owed', function () {
    $sale = ($this->convert)('woocommerce', ['status' => 'on-hold', 'date_paid' => '2026-11-04T08:00:00', 'payment_method' => 'bacs']);

    ($this->woo)(['status' => 'cancelled', 'date_paid' => '2026-11-04T08:00:00', 'payment_method' => 'bacs']);

    expect($sale->fresh()->status)->toBe('voided')
        ->and(RefundDue::count())->toBe(0)
        ->and(($this->reviews)($sale)->sole()->message)->toBe('Order cancelled but no refund recorded in the store. Check whether the customer was refunded.')
        ->and(($this->ownerNotices)()->sole()->data['kind'])->toBe('sale_review');

    $this->actingAs($this->owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Needs review')->assertSee('Record a refund owed');
    $this->actingAs($this->owner)->post(route('sales.refunds-due.store', $sale), ['amount' => 300, 'method' => 'bank_transfer', 'reference' => 'BT-1'])->assertSessionHas('success');
    expect(RefundDue::sole()->only(['status', 'method', 'source']))->toBe(['status' => 'pending', 'method' => 'bank_transfer', 'source' => 'store_cancellation']);
});

it('flags a part refund or part cancellation for review and doesn\'t void, cancelled or not', function (string $status) {
    $sale = ($this->convert)('woocommerce', ['status' => 'processing', 'date_paid' => '2026-11-04T08:00:00']);

    ($this->woo)(['status' => $status, 'date_paid' => '2026-11-04T08:00:00', 'refunds' => [['id' => 56, 'total' => '-100.00']]]);

    expect($sale->fresh()->status)->toBe('pending')
        ->and(($this->stock)())->toBe(7)
        ->and(($this->reviews)($sale)->sole()->data['code'])->toBe(StoreCancellations::PARTIAL)
        ->and(($this->ownerNotices)())->toHaveCount(1);
})->with(['processing', 'cancelled']);

it('doesn\'t void an order cancelled after dispatch: it flags the sale and alerts the shop\'s staff', function () {
    $sale = ($this->convert)('woocommerce', ['status' => 'completed'], 'completed');

    ($this->woo)(['status' => 'cancelled']);

    expect($sale->fresh()->status)->toBe('pending')
        ->and(($this->reviews)($sale)->sole()->message)->toBe('The online order was cancelled after dispatch. Check whether the goods came back, and process a return.')
        ->and(($this->reviews)($sale)->sole()->shop_id)->toBe($this->shop->id)
        ->and(($this->ownerNotices)())->toHaveCount(0);
});

it('voids a Shopify order cancelled unfulfilled, and flags one cancelled fulfilled', function () {
    $sale = ($this->convert)('shopify', ['financial_status' => 'paid']);
    ($this->shopify)(['cancelled_at' => '2026-11-04T09:00:00', 'financial_status' => 'refunded', 'fulfillment_status' => null,
        'refunds' => [['id' => 77, 'transactions' => [['kind' => 'refund', 'status' => 'success', 'amount' => '300.00']]]]]);
    expect($sale->fresh()->status)->toBe('voided')
        ->and(RefundDue::sole()->resolution)->toBe('refunded_in_store');

    EcommerceOrder::query()->forceDelete();
    $fulfilled = ($this->convert)('shopify', ['financial_status' => 'paid', 'fulfillment_status' => 'fulfilled']);
    ($this->shopify)(['cancelled_at' => '2026-11-04T09:00:00', 'financial_status' => 'paid', 'fulfillment_status' => 'fulfilled']);
    expect($fulfilled->fresh()->status)->toBe('pending')
        ->and(($this->reviews)($fulfilled)->sole()->data['code'])->toBe(StoreCancellations::AFTER_DISPATCH);
});

it('doesn\'t void a sale with a return that isn\'t rejected', function () {
    $sale = ($this->convert)('woocommerce', ['status' => 'processing']);
    SaleReturn::factory()->create(['sale_id' => $sale->id, 'shop_id' => $this->shop->id, 'status' => ReturnStatus::PENDING]);

    ($this->woo)(['status' => 'cancelled']);

    expect($sale->fresh()->status)->toBe('pending')
        ->and(($this->reviews)($sale))->toHaveCount(1);
});

it('voids once when the webhook comes twice, and never pushes the stock back to the store that cancelled it', function () {
    $this->shop->update(['settings' => ['integrations' => ['woocommerce' => ['enabled' => true, 'store_url' => 'https://store.test', 'consumer_key' => Crypt::encrypt('ck'), 'consumer_secret' => Crypt::encrypt('cs')]]]]);
    $sale = ($this->convert)('woocommerce', ['status' => 'processing']);
    Queue::fake();

    ($this->woo)(['status' => 'cancelled']);
    ($this->woo)(['status' => 'cancelled']);

    expect(DB::table('stock_movements')->where('movement_type', StockMovementType::SALE_VOID->value)->count())->toBe(1)
        ->and(($this->stock)())->toBe(10);
    Queue::assertNotPushed(PushShopStockJob::class);
});

it('owes payments recorded in the POS itself, such as M-Pesa collected by hand on cash on delivery', function () {
    $sale = ($this->convert)('woocommerce', ['status' => 'processing']);
    SalePayment::create(['business_id' => testBusiness()->id, 'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'payment_number' => 'PAY-1', 'amount' => 100, 'payment_method' => 'mobile_money', 'reference' => 'MP-9', 'paid_at' => now()]);
    $sale->update(['paid_amount' => 100]);

    ($this->woo)(['status' => 'cancelled']);

    expect($sale->fresh()->status)->toBe('voided')
        ->and(RefundDue::sole()->only(['method', 'reference', 'status']))->toBe(['method' => 'mobile_money', 'reference' => 'MP-9', 'status' => 'pending']);
});
