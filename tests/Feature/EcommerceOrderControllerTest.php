<?php

use App\Enums\EcommerceOrderStatus;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    Permission::create(['name' => 'ecommerce-orders.view']);
    $this->user->givePermissionTo('ecommerce-orders.view');
});

test('index page loads with optimized queries', function () {
    $shop = Shop::factory()->create();

    $orders = EcommerceOrder::factory()
        ->count(5)
        ->for($shop)
        ->pending()
        ->create();

    // Create items for the first order to verify withCount works
    EcommerceOrderItem::create([
        'ecommerce_order_id' => $orders->first()->id,
        'name' => 'Test Product',
        'quantity' => 2,
        'unit_price' => 100,
        'subtotal' => 200,
        'total' => 200,
        'tax_total' => 0,
        'platform_product_id' => 'prod_1',
    ]);

    $response = $this->get(route('ecommerce-orders.index'));

    $response->assertSuccessful();
    $response->assertViewHas('orders');
    $response->assertViewHas('shops');
    $response->assertViewHas('statistics');
});

test('index page returns correct statistics in a single query', function () {
    $shop = Shop::factory()->create();

    EcommerceOrder::factory()->count(3)->for($shop)->pending()->create();
    EcommerceOrder::factory()->count(2)->for($shop)->processing()->create();
    EcommerceOrder::factory()->count(1)->for($shop)->completed()->create();

    $response = $this->get(route('ecommerce-orders.index'));

    $response->assertSuccessful();

    $statistics = $response->viewData('statistics');

    expect($statistics['total'])->toBe(6)
        ->and($statistics['pending'])->toBe(3)
        ->and($statistics['processing'])->toBe(2)
        ->and($statistics['completed'])->toBe(1);
});

test('index page eager loads items count to prevent N+1', function () {
    $shop = Shop::factory()->create();

    $order = EcommerceOrder::factory()->for($shop)->pending()->create();

    EcommerceOrderItem::create([
        'ecommerce_order_id' => $order->id,
        'name' => 'Item A',
        'quantity' => 1,
        'unit_price' => 50,
        'subtotal' => 50,
        'total' => 50,
        'tax_total' => 0,
        'platform_product_id' => 'prod_a',
    ]);

    EcommerceOrderItem::create([
        'ecommerce_order_id' => $order->id,
        'name' => 'Item B',
        'quantity' => 3,
        'unit_price' => 30,
        'subtotal' => 90,
        'total' => 90,
        'tax_total' => 0,
        'platform_product_id' => 'prod_b',
    ]);

    $response = $this->get(route('ecommerce-orders.index'));

    $response->assertSuccessful();

    $loadedOrder = $response->viewData('orders')->first();
    expect($loadedOrder->items_count)->toBe(2);
});

test('index page only shows shops with orders', function () {
    $shopWithOrders = Shop::factory()->create(['name' => 'Shop With Orders']);
    $shopWithoutOrders = Shop::factory()->create(['name' => 'Empty Shop']);

    EcommerceOrder::factory()->for($shopWithOrders)->create();

    $response = $this->get(route('ecommerce-orders.index'));

    $response->assertSuccessful();

    $shops = $response->viewData('shops');
    expect($shops)->toHaveCount(1)
        ->and($shops->first()->name)->toBe('Shop With Orders');
});

test('index page filters by status', function () {
    $shop = Shop::factory()->create();

    EcommerceOrder::factory()->for($shop)->pending()->create();
    EcommerceOrder::factory()->for($shop)->processing()->create();

    $response = $this->get(route('ecommerce-orders.index', ['status' => 'pending']));

    $response->assertSuccessful();

    $orders = $response->viewData('orders');
    expect($orders)->toHaveCount(1)
        ->and($orders->first()->status)->toBe(EcommerceOrderStatus::Pending);
});

test('index page filters by shop', function () {
    $shop1 = Shop::factory()->create();
    $shop2 = Shop::factory()->create();

    EcommerceOrder::factory()->for($shop1)->create();
    EcommerceOrder::factory()->for($shop2)->create();

    $response = $this->get(route('ecommerce-orders.index', ['shop_id' => $shop1->id]));

    $response->assertSuccessful();

    $orders = $response->viewData('orders');
    expect($orders)->toHaveCount(1)
        ->and($orders->first()->shop_id)->toBe($shop1->id);
});

test('index page only includes assigned shop orders for allocated users', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $this->user->shops()->sync([$shop->id]);

    EcommerceOrder::factory()->for($shop)->pending()->create();
    EcommerceOrder::factory()->for($otherShop)->processing()->create();

    $response = $this->get(route('ecommerce-orders.index'));

    $response->assertSuccessful();

    $orders = $response->viewData('orders');
    $statistics = $response->viewData('statistics');

    expect($orders)->toHaveCount(1)
        ->and($orders->first()->shop_id)->toBe($shop->id)
        ->and((int) $statistics['total'])->toBe(1)
        ->and((int) $statistics['pending'])->toBe(1)
        ->and((int) $statistics['processing'])->toBe(0);
});

test('allocated user cannot view orders from another shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $this->user->shops()->sync([$shop->id]);
    $order = EcommerceOrder::factory()->for($otherShop)->create();

    $this->get(route('ecommerce-orders.show', $order))->assertForbidden();
});

test('allocated user cannot refresh orders for another shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $this->user->shops()->sync([$shop->id]);

    $this->post(route('ecommerce-orders.refresh'), ['shop_id' => $otherShop->id])
        ->assertForbidden();
});

test('index page searches by order number', function () {
    $shop = Shop::factory()->create();

    EcommerceOrder::factory()->for($shop)->create(['order_number' => '#10001']);
    EcommerceOrder::factory()->for($shop)->create(['order_number' => '#20002']);

    $response = $this->get(route('ecommerce-orders.index', ['search' => '10001']));

    $response->assertSuccessful();

    $orders = $response->viewData('orders');
    expect($orders)->toHaveCount(1)
        ->and($orders->first()->order_number)->toBe('#10001');
});
