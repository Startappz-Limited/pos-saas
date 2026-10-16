<?php

use App\Actions\ConvertAbandonedCartToSale;
use App\Actions\ConvertEcommerceOrderToSale;
use App\Enums\StockMovementType;
use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Each of the four sale paths takes the stock from the shop, for the right
 * product or variation, costs it from FIFO layers and writes a movement
 * (stage 1b acceptance).
 */
beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'MAIN']);
    $this->otherShop = Shop::factory()->create(['code' => 'OTHER']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');

    // A simple product: 10 at 20. A product with a variation: 5 at 30.
    $this->simple = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 100]);
    $this->ledger->receive($this->shop->id, $this->simple->defaultVariation, 10, 20, StockMovementType::INTAKE);

    $this->variable = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => true, 'selling_price' => 150]);
    $this->variation = ProductVariation::factory()->create(['product_id' => $this->variable->id, 'stock_quantity' => 0, 'selling_price' => 150]);
    $this->ledger->receive($this->shop->id, $this->variation, 5, 30, StockMovementType::INTAKE);

    // The other shop holds stock of the same product, which no sale here touches.
    $this->ledger->receive($this->otherShop->id, $this->simple->defaultVariation, 7, 25, StockMovementType::INTAKE);
});

function salePayload(): array
{
    return [
        'shop_id' => test()->shop->id,
        'source_id' => test()->source,
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => test()->simple->id, 'quantity' => 2, 'price' => 100],
            ['product_id' => test()->variable->id, 'variation_id' => test()->variation->id, 'quantity' => 1, 'price' => 150],
        ],
    ];
}

function expectStockTaken(Sale $sale, int $simpleTaken, int $variationTaken): void
{
    $ledger = app(StockLedger::class);

    expect($ledger->quantity(test()->shop->id, test()->simple->defaultVariation->id))->toBe(10 - $simpleTaken)
        ->and($ledger->quantity(test()->shop->id, test()->variation->id))->toBe(5 - $variationTaken)
        ->and($ledger->quantity(test()->otherShop->id, test()->simple->defaultVariation->id))->toBe(7)
        ->and(StockMovement::where('movement_type', StockMovementType::SALE)->where('reference_uuid', $sale->uuid)->count())
        ->toBe(($simpleTaken > 0 ? 1 : 0) + ($variationTaken > 0 ? 1 : 0));
}

it('takes stock through the API sale', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $uuid = $this->postJson('/api/sales', salePayload())->assertCreated()->json('data.uuid');
    $sale = Sale::where('uuid', $uuid)->sole();

    expectStockTaken($sale, 2, 1);
    // FIFO cost: 2 at 20 and 1 at 30.
    expect((float) $sale->total_cost)->toBe(70.0)
        ->and($sale->items->sortBy('id')->map(fn ($item) => (float) $item->unit_cost)->values()->all())->toBe([20.0, 30.0]);
});

it('takes stock through the web POS sale', function () {
    $this->actingAs($this->owner)->post(route('sales.store'), salePayload())->assertSessionHasNoErrors();
    $sale = Sale::latest('id')->firstOrFail();

    expectStockTaken($sale, 2, 1);
    expect((float) $sale->total_cost)->toBe(70.0);
});

it('takes stock when an online order becomes a sale', function () {
    // The website isn't reached: its order-items update succeeds.
    app()->instance(WooCommerceOrderSyncService::class, new class extends WooCommerceOrderSyncService
    {
        public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
        {
            return ['success' => true];
        }
    });
    $order = EcommerceOrder::factory()->woocommerce()->processing()->create(['shop_id' => $this->shop->id]);
    EcommerceOrderItem::factory()->create(['ecommerce_order_id' => $order->id, 'product_id' => $this->simple->id, 'quantity' => 3, 'unit_price' => 100, 'subtotal' => 300, 'total' => 300]);
    $this->actingAs($this->owner);

    $sale = app(ConvertEcommerceOrderToSale::class)->execute($order, [
        'source_id' => $this->source,
        'delivery_location' => 'Nairobi',
        'payment_method' => 'mobile_money',
        'items' => [['product_id' => $this->simple->id, 'quantity' => 3, 'unit_price' => 100]],
    ], $this->owner);

    expectStockTaken($sale, 3, 0);
    expect((float) $sale->total_cost)->toBe(60.0);
});

it('takes stock when an abandoned cart becomes a sale', function () {
    $cart = AbandonedCart::factory()->create(['shop_id' => $this->shop->id, 'status' => 'abandoned']);
    AbandonedCartItem::factory()->create([
        'abandoned_cart_id' => $cart->id, 'product_id' => $this->variable->id, 'variation_id' => $this->variation->id,
        'quantity' => 2, 'unit_price' => 150, 'line_total' => 300,
    ]);
    $this->actingAs($this->owner);

    $sale = app(ConvertAbandonedCartToSale::class)->execute($cart, ['source_id' => $this->source, 'payment_method' => 'mobile_money'], $this->owner);

    expectStockTaken($sale, 0, 2);
    expect((float) $sale->total_cost)->toBe(60.0);
});

it('accepts a sale beyond the shop\'s stock, costing the shortfall provisionally', function () {
    Sanctum::actingAs($this->owner, ['*']);
    $payload = salePayload();
    $payload['items'] = [['product_id' => $this->simple->id, 'quantity' => 12, 'price' => 100]];

    $this->postJson('/api/sales', $payload)->assertCreated();

    expect($this->ledger->quantity($this->shop->id, $this->simple->defaultVariation->id))->toBe(-2)
        ->and(DB::table('cost_allocations')->where('is_provisional', true)->sum('quantity_allocated'))->toEqual(2);
});
