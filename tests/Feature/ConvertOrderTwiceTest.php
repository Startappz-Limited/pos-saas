<?php

use App\Enums\StockMovementType;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * Converting an online order that has already been converted is a state, not a
 * permission (hotfix fix/api-authz, 2 November 2026): EcommerceOrderPolicy::convert
 * checks permission only, and the second conversion answers 422 "This order cannot
 * be converted to a sale." on the API (the web returns to the order with the same
 * message), never a permission refusal.
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

    $this->shop = Shop::factory()->create();
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->shops()->attach($this->shop);

    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 100]);
    app(StockLedger::class)->receive($this->shop->id, $this->product->defaultVariation, 10, 20, StockMovementType::INTAKE);
    $this->order = EcommerceOrder::factory()->woocommerce()->processing()->create(['shop_id' => $this->shop->id]);
    EcommerceOrderItem::factory()->create(['ecommerce_order_id' => $this->order->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'total' => 100]);
    $this->payload = [
        'source_id' => DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id'),
        'delivery_location' => 'Nairobi',
        'payment_method' => 'cash',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]],
    ];
});

it('answers 422 to the second conversion of the same order on the API, and makes one sale', function () {
    Sanctum::actingAs($this->cashier, ['*']);

    $this->postJson("/api/ecommerce-orders/{$this->order->getRouteKey()}/convert", $this->payload)->assertCreated();
    $this->postJson("/api/ecommerce-orders/{$this->order->getRouteKey()}/convert", $this->payload)
        ->assertUnprocessable()->assertJsonPath('message', 'This order cannot be converted to a sale.');

    expect(Sale::query()->where('shop_id', $this->shop->id)->count())->toBe(1)
        ->and($this->order->fresh()->sale_id)->toBe(Sale::query()->where('shop_id', $this->shop->id)->value('id'));
});

it('returns to the order with the same message on the web\'s second conversion', function () {
    $this->actingAs($this->cashier);

    $this->post(route('ecommerce-orders.storeConversion', $this->order), $this->payload)->assertRedirect();
    $this->from(route('ecommerce-orders.show', $this->order))
        ->post(route('ecommerce-orders.storeConversion', $this->order), $this->payload)
        ->assertRedirect(route('ecommerce-orders.show', $this->order))
        ->assertSessionHas('error', 'This order cannot be converted to a sale.');

    expect(Sale::query()->where('shop_id', $this->shop->id)->count())->toBe(1)
        ->and($this->order->fresh()->sale_id)->toBe(Sale::query()->where('shop_id', $this->shop->id)->value('id'));
});
