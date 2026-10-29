<?php

use App\Models\AbandonedCart;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\User;

/*
 * Deleting a shop is a soft delete that keeps its history (hotfix
 * fix/api-authz, 29 October 2026). The pages below used to read
 * `->shop->name` through a relation that skips deleted shops, so they failed
 * (or showed nothing) once the shop was deleted. They now load the shop with
 * `withTrashed()` and name it, marked "(deleted)".
 */

beforeEach(function () {
    ownerRole();
    $this->owner = User::factory()->create();
    $this->owner->assignRole('admin');
    $this->actingAs($this->owner);

    $this->shop = Shop::factory()->create(['name' => 'Westlands']);
    $this->keep = Shop::factory()->create();
});

it('shows a deleted shop by name, marked deleted, on its old records', function (Closure $page) {
    $url = $page($this->shop);
    $this->shop->delete();

    $this->get($url)->assertOk()->assertSee('Westlands (deleted)');
})->with([
    'online order' => [fn (Shop $shop): string => route('ecommerce-orders.show', EcommerceOrder::factory()->create(['shop_id' => $shop->id]))],
    'abandoned cart' => [fn (Shop $shop): string => route('abandoned-carts.show', AbandonedCart::factory()->create(['shop_id' => $shop->id]))],
    'stock adjustment' => [fn (Shop $shop): string => route('stock-adjustments.edit', StockAdjustment::factory()->create(['shop_id' => $shop->id, 'status' => 'pending', 'type' => 'increase', 'reason' => 'recount', 'created_by' => test()->owner->id]))],
    'purchase orders list' => [function (Shop $shop): string {
        PurchaseOrder::factory()->create(['shop_id' => $shop->id]);

        return route('purchase-orders.index');
    }],
    'product sync records' => [function (Shop $shop): string {
        $product = Product::factory()->create(['shop_id' => test()->keep->id]);
        ProductEcommerceSync::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'platform' => 'woocommerce',
            'platform_product_id' => '123',
            'sync_status' => 'synced',
            'sync_direction' => 'from_platform',
        ]);

        return route('products.show', $product);
    }],
]);

it('names a shop that isn\'t deleted as it is, with no marker', function () {
    expect($this->shop->displayName())->toBe('Westlands');

    $this->shop->delete();

    expect(Shop::withTrashed()->find($this->shop->id)->displayName())->toBe('Westlands (deleted)');
});
