<?php

use App\Actions\ConvertEcommerceOrderToSale;
use App\Exceptions\BusinessRuleException;
use App\Models\CashRegister;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Integration\WooCommerceOrderSyncService;
use Laravel\Sanctum\Sanctum;

/*
 * A sale's cash session is always one of its own shop's (hotfix fix/api-authz,
 * 2 November 2026). Converting an online order used to accept any session the
 * person could see, so the sale's totals and invoice series could land on
 * another shop's till, and completing it later failed with a 500 for a cashier
 * of the sale's shop (`$sale->register` was hidden from them by the shop scope).
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->otherShop = Shop::factory()->create();
});

it('refuses to convert an online order onto another shop\'s cash session', function () {
    // The website must not be reached: the refusal comes first.
    $this->mock(WooCommerceOrderSyncService::class)->shouldNotReceive('syncOrderItems');
    $owner = User::factory()->create();
    $owner->assignRole('admin');
    $this->actingAs($owner);
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $order = EcommerceOrder::factory()->woocommerce()->processing()->create(['shop_id' => $this->shop->id]);
    EcommerceOrderItem::factory()->create(['ecommerce_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'total' => 100]);
    $elsewhere = CashRegister::factory()->create(['shop_id' => $this->otherShop->id, 'user_id' => $owner->id, 'status' => 'open']);
    $sales = Sale::count();

    expect(fn () => app(ConvertEcommerceOrderToSale::class)->execute($order, [
        'register_id' => $elsewhere->id,
        'delivery_location' => 'Nairobi',
        'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ], $owner))->toThrow(BusinessRuleException::class, 'The selected register is not an open register of this shop.');

    expect(Sale::count())->toBe($sales);
});

it('lets a cashier complete a sale and collect its payment without an error', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($this->shop);
    Sanctum::actingAs($cashier, ['*']);

    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending', 'payment_status' => 'unpaid']);
    expect(CashRegister::find($sale->register_id)?->shop_id)->toBe($this->shop->id);

    $this->postJson("/api/sales/{$sale->getRouteKey()}/complete")->assertOk();
});

it('completes a sale whose session the viewer can\'t see without failing', function () {
    // Older data: a session in another shop. The sale completes; that till's totals are left as they are.
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($this->shop);
    Sanctum::actingAs($cashier, ['*']);

    $sale = Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'status' => 'pending',
        'register_id' => CashRegister::factory()->create(['shop_id' => $this->otherShop->id])->id,
    ]);

    $this->postJson("/api/sales/{$sale->getRouteKey()}/complete")->assertOk();
    expect($sale->fresh()->status)->toBe('completed');
});
