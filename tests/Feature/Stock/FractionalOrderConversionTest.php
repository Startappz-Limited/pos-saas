<?php

use App\Enums\StockMovementType;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * Review follow-up (stage 11): converting an online order checks each quantity against the matched
 * product's base unit and carries it exactly through the totals, the sale line and the stock it takes.
 * WooCommerce and Shopify hold whole quantities only, and converting pushes the lines back to the
 * website, so a fraction is refused there even for a weight. A variation line takes its variation's stock.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    // The website isn't reached: its order-items update succeeds.
    app()->instance(WooCommerceOrderSyncService::class, new class extends WooCommerceOrderSyncService
    {
        public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
        {
            return ['success' => true];
        }
    });

    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());

    $this->rice = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg', 'stock_quantity' => 0, 'selling_price' => 200, 'has_variations' => false]);
    $this->rice->defaultVariation->baseUnit()->update(['precision' => 3]);
    $this->ledger->receive($this->shop->id, $this->rice->defaultVariation, 10, 80, StockMovementType::INTAKE);

    $this->tablets = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'tablet', 'stock_quantity' => 0, 'selling_price' => 10, 'has_variations' => false]);
    $this->tablets->defaultVariation->baseUnit()->update(['precision' => 0]);
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 10, 2, StockMovementType::INTAKE);

    $this->order = EcommerceOrder::factory()->woocommerce()->processing()->create(['shop_id' => $this->shop->id, 'discount_total' => 0, 'tax_total' => 0, 'shipping_total' => 0]);
    EcommerceOrderItem::factory()->create(['ecommerce_order_id' => $this->order->id, 'product_id' => $this->rice->id, 'quantity' => 1, 'unit_price' => 200, 'subtotal' => 200, 'total' => 200]);

    $this->convert = fn (array $items) => $this->postJson("/api/ecommerce-orders/{$this->order->getRouteKey()}/convert", [
        'source_id' => DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id'),
        'delivery_location' => 'Nairobi',
        'payment_method' => 'cash',
        'delivery_fee' => 0,
        'items' => $items,
    ]);
});

it('refuses 1.5 kg on a website order, since the website holds whole quantities', function () {
    Sanctum::actingAs($this->owner, ['*']);

    ($this->convert)([['product_id' => $this->rice->id, 'quantity' => 1.5, 'unit_price' => 200]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity' => 'The website only takes whole quantities']);

    expect(Sale::query()->where('shop_id', $this->shop->id)->count())->toBe(0)
        ->and($this->ledger->exactQuantity($this->shop->id, $this->rice->defaultVariation->id))->toBe('10.000');
});

it('converts whole kilograms exactly: totals, sale line and stock', function () {
    Sanctum::actingAs($this->owner, ['*']);

    ($this->convert)([['product_id' => $this->rice->id, 'quantity' => 2, 'unit_price' => 200]])->assertCreated();

    $sale = Sale::query()->where('shop_id', $this->shop->id)->sole();
    $line = $sale->items->sole();

    expect((float) $sale->subtotal)->toBe(400.0)
        ->and((float) $sale->total_cost)->toBe(160.0)
        ->and((string) $line->quantity)->toBe('2.000')
        ->and((string) $line->base_quantity)->toBe('2.000')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->rice->defaultVariation->id))->toBe('8.000');
});

it('takes a variation line\'s stock from that variation', function () {
    Sanctum::actingAs($this->owner, ['*']);
    $shirt = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 500, 'has_variations' => true]);
    $large = ProductVariation::factory()->create(['product_id' => $shirt->id, 'stock_quantity' => 0, 'selling_price' => 500]);
    $this->ledger->receive($this->shop->id, $large, 5, 300, StockMovementType::INTAKE);

    ($this->convert)([['product_id' => $shirt->id, 'variation_id' => $large->id, 'quantity' => 2, 'unit_price' => 500]])->assertCreated();

    $line = Sale::query()->where('shop_id', $this->shop->id)->sole()->items->sole();
    expect($this->ledger->exactQuantity($this->shop->id, $large->id))->toBe('3.000')
        ->and($line->variation_id)->toBe($large->id)
        ->and((float) $line->total_cost)->toBe(600.0);
});

it('refuses a fraction of a whole-unit product, and converts nothing', function () {
    Sanctum::actingAs($this->owner, ['*']);

    ($this->convert)([['product_id' => $this->tablets->id, 'quantity' => 1.5, 'unit_price' => 10]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
    // More decimals than the ledger holds are refused for a weight too.
    ($this->convert)([['product_id' => $this->rice->id, 'quantity' => 1.0005, 'unit_price' => 200]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');

    expect(Sale::query()->where('shop_id', $this->shop->id)->count())->toBe(0)
        ->and($this->ledger->exactQuantity($this->shop->id, $this->tablets->defaultVariation->id))->toBe('10.000');
});

it('still converts whole quantities of a whole-unit product', function () {
    Sanctum::actingAs($this->owner, ['*']);

    ($this->convert)([['product_id' => $this->tablets->id, 'quantity' => 3, 'unit_price' => 10]])->assertCreated();

    expect((string) Sale::query()->where('shop_id', $this->shop->id)->sole()->items->sole()->base_quantity)->toBe('3.000')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->tablets->defaultVariation->id))->toBe('7.000');
});
