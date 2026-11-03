<?php

use App\Actions\ConvertAbandonedCartToSale;
use App\Actions\ConvertEcommerceOrderToSale;
use App\Enums\StockMovementType;
use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\SyncIssue;
use App\Models\User;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\ReportService;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Till;

/*
 * A sale takes stock from the shop that sells it (stage 10, step 1a), never from
 * the shop its product was first stocked at (`products.shop_id`). Before, every
 * line took its product's "owning" shop's stock, and the flat API also filed the
 * whole sale under its first product's shop.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shopA = Shop::factory()->create(['code' => 'AAA']);
    $this->shopB = Shop::factory()->create(['code' => 'BBB']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');

    // First stocked at A: 10 at 20 there, 6 at 25 at B.
    $this->product = Product::factory()->create(['shop_id' => $this->shopA->id, 'stock_quantity' => 0, 'selling_price' => 100]);
    $this->variation = $this->product->defaultVariation;
    $this->ledger->receive($this->shopA->id, $this->variation, 10, 20, StockMovementType::INTAKE);
    $this->ledger->receive($this->shopB->id, $this->variation, 6, 25, StockMovementType::INTAKE);
});

function sellingShopPayload(int $shopId, int $quantity = 2): array
{
    return [
        'shop_id' => $shopId,
        'source_id' => test()->source,
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => test()->product->id, 'quantity' => $quantity, 'price' => 100]],
    ];
}

/**
 * B's stock went down by $taken from its own batch (cost 25); A's is untouched; the sale and its lines are B's.
 */
function expectSoldAtB(Sale $sale, int $taken): void
{
    $ledger = app(StockLedger::class);
    $variationId = test()->variation->id;

    expect($ledger->quantity(test()->shopB->id, $variationId))->toBe(6 - $taken)
        ->and($ledger->quantity(test()->shopA->id, $variationId))->toBe(10)
        ->and($sale->shop_id)->toBe(test()->shopB->id)
        ->and($sale->items()->pluck('shop_id')->unique()->values()->all())->toBe([test()->shopB->id])
        ->and(StockMovement::query()->where('reference_uuid', $sale->uuid)->where('movement_type', StockMovementType::SALE)->pluck('shop_id')->unique()->values()->all())
        ->toBe([test()->shopB->id])
        ->and((float) $sale->fresh()->total_cost)->toBe(25.0 * $taken);
}

it('sells from the selling shop on the web POS', function () {
    $this->actingAs($this->owner)->post(route('sales.store'), sellingShopPayload($this->shopB->id))->assertSessionHasNoErrors();

    expectSoldAtB(Sale::latest('id')->firstOrFail(), 2);
});

it('sells from the shop the flat API names, and files the sale under it', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $uuid = $this->postJson('/api/sales', sellingShopPayload($this->shopB->id))->assertCreated()->json('data.uuid');

    expectSoldAtB(Sale::where('uuid', $uuid)->sole(), 2);
});

it('files a till\'s pushed sale under the till\'s shop and takes that shop\'s stock', function () {
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $register = Register::defaultFor($this->shopB->id);
    $tenant = app(TenantContext::class);
    $business = $tenant->business();
    $tenant->forget();
    $token = Till::token($this->owner, $register);
    $this->travel(1)->hours();

    Till::freshRequest();
    $payload = sellingShopPayload($this->shopB->id);
    unset($payload['shop_id']);
    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_uuid' => (string) Str::uuid(), 'type' => 'sale', 'recorded_at' => now()->toDateTimeString(), 'data' => $payload,
    ]]])->assertOk()->assertJsonPath('data.results.0.status', 'applied');

    $tenant->set($business);
    expectSoldAtB(Sale::latest('id')->firstOrFail(), 2);
});

it('sells an abandoned cart from the cart\'s shop', function () {
    $this->actingAs($this->owner);
    $cart = AbandonedCart::factory()->create(['shop_id' => $this->shopB->id, 'status' => 'abandoned']);
    AbandonedCartItem::factory()->create(['abandoned_cart_id' => $cart->id, 'product_id' => $this->product->id, 'variation_id' => null, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);

    $sale = app(ConvertAbandonedCartToSale::class)->execute($cart, [
        'source_id' => $this->source, 'delivery_location' => 'Nairobi', 'payment_method' => 'cash',
    ], $this->owner);

    expectSoldAtB($sale, 2);
});

it('sells an online order from the order\'s shop (already right; pinned)', function () {
    app()->instance(WooCommerceOrderSyncService::class, new class extends WooCommerceOrderSyncService
    {
        public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
        {
            return ['success' => true];
        }
    });
    $this->actingAs($this->owner);
    $order = EcommerceOrder::factory()->woocommerce()->processing()->create(['shop_id' => $this->shopB->id]);
    EcommerceOrderItem::factory()->create(['ecommerce_order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'subtotal' => 200, 'total' => 200]);

    $sale = app(ConvertEcommerceOrderToSale::class)->execute($order, [
        'source_id' => $this->source, 'delivery_location' => 'Nairobi', 'payment_method' => 'cash',
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100]],
    ], $this->owner);

    expectSoldAtB($sale, 2);
});

it('sells at a shop with no stock row for the product: the row is made, stock goes below zero, and the owner sees it', function () {
    $shopC = Shop::factory()->create(['code' => 'CCC']);
    expect(DB::table('shop_variation')->where('shop_id', $shopC->id)->where('variation_id', $this->variation->id)->exists())->toBeFalse();

    $this->actingAs($this->owner)->post(route('sales.store'), sellingShopPayload($shopC->id, 3))->assertSessionHasNoErrors();
    $sale = Sale::latest('id')->firstOrFail();

    expect($this->ledger->quantity($shopC->id, $this->variation->id))->toBe(-3)
        ->and($this->ledger->quantity($this->shopA->id, $this->variation->id))->toBe(10)
        ->and(SyncIssue::query()->where('type', SyncIssue::NEGATIVE_STOCK)->where('key', 'sale:'.$sale->uuid)->value('shop_id'))->toBe($shopC->id);

    $this->get(route('sync-issues.index'))->assertOk()->assertSee($sale->invoice_number);
});

it('records a negative_stock issue for a flat API sale below zero', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $uuid = $this->postJson('/api/sales', sellingShopPayload($this->shopB->id, 8))->assertCreated()->json('data.uuid');

    expect($this->ledger->quantity($this->shopB->id, $this->variation->id))->toBe(-2)
        ->and(SyncIssue::query()->where('type', SyncIssue::NEGATIVE_STOCK)->where('key', 'sale:'.$uuid)->exists())->toBeTrue();
});

it('moves no stock for a product that doesn\'t track stock', function () {
    $service = Product::factory()->create(['shop_id' => $this->shopA->id, 'track_stock' => false, 'cost_price' => 15, 'selling_price' => 50]);
    $payload = sellingShopPayload($this->shopB->id);
    $payload['items'] = [['product_id' => $service->id, 'quantity' => 3, 'price' => 50]];

    $this->actingAs($this->owner)->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
    $sale = Sale::latest('id')->firstOrFail();

    expect(StockMovement::query()->where('reference_uuid', $sale->uuid)->exists())->toBeFalse()
        ->and(DB::table('cost_allocations')->where('product_id', $service->id)->exists())->toBeFalse()
        ->and($this->ledger->quantity($this->shopB->id, $service->defaultVariation->id))->toBe(0)
        ->and((float) $sale->items()->value('total_cost'))->toBe(45.0);
});

it('counts a cross-shop sale under the selling shop in the sales-by-shop report', function () {
    $this->actingAs($this->owner)->post(route('sales.store'), sellingShopPayload($this->shopB->id))->assertSessionHasNoErrors();

    $byShop = collect(app(ReportService::class)->getDashboardData([
        'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
        'allowed_shop_ids' => [$this->shopA->id, $this->shopB->id],
    ])['shop_performance'])->keyBy('shop_id');

    expect($byShop->has($this->shopB->id))->toBeTrue()
        ->and($byShop[$this->shopB->id]['revenue'])->toBe(200.0)
        ->and($byShop->has($this->shopA->id))->toBeFalse();
});
