<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReportService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->shopA = Shop::factory()->create(['name' => 'Shop A']);
    $this->shopB = Shop::factory()->create(['name' => 'Shop B']);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    Permission::findOrCreate('sales.create');
    $this->user->givePermissionTo('sales.create');
});

test('a sale mixing products from two shops attributes each line to its product owning shop', function () {
    $source = SaleSource::create(['name' => 'Counter', 'is_active' => true, 'sort_order' => 1]);

    $productA = Product::factory()->create([
        'shop_id' => $this->shopA->id,
        'stock_quantity' => 10,
        'selling_price' => 100,
        'cost_price' => 40,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $this->shopB->id,
        'stock_quantity' => 10,
        'selling_price' => 200,
        'cost_price' => 80,
    ]);

    $customer = Customer::factory()->create([
        'shop_id' => $this->shopA->id,
        'customer_type' => 'retail',
    ]);

    $this->post(route('sales.store'), [
        'customer_id' => $customer->id,
        'source_id' => $source->id,
        'delivery_location' => 'Front counter',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'price' => 100],
            ['product_id' => $productB->id, 'quantity' => 2, 'price' => 200],
        ],
    ])->assertRedirect();

    $sale = Sale::firstOrFail();

    // Each line is attributed to the shop that owns its product.
    $itemA = SaleItem::where('product_id', $productA->id)->firstOrFail();
    $itemB = SaleItem::where('product_id', $productB->id)->firstOrFail();

    expect($itemA->shop_id)->toBe($this->shopA->id)
        ->and($itemB->shop_id)->toBe($this->shopB->id)
        // The sale itself is stamped with the cashier's resolved (default/selected)
        // shop; per-shop attribution lives on the line items above.
        ->and([$this->shopA->id, $this->shopB->id])->toContain($sale->shop_id);
});

test('shop performance report splits revenue per shop at the line-item level', function () {
    $source = SaleSource::create(['name' => 'Counter', 'is_active' => true, 'sort_order' => 1]);

    $productA = Product::factory()->create([
        'shop_id' => $this->shopA->id, 'stock_quantity' => 10, 'selling_price' => 100, 'cost_price' => 40,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $this->shopB->id, 'stock_quantity' => 10, 'selling_price' => 200, 'cost_price' => 80,
    ]);
    $customer = Customer::factory()->create(['shop_id' => $this->shopA->id, 'customer_type' => 'retail']);

    $this->post(route('sales.store'), [
        'customer_id' => $customer->id,
        'source_id' => $source->id,
        'delivery_location' => 'Front counter',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'price' => 100], // shop A: 100
            ['product_id' => $productB->id, 'quantity' => 2, 'price' => 200], // shop B: 400
        ],
    ])->assertRedirect();

    $report = app(ReportService::class)->getDashboardData([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    $byShop = collect($report['shop_performance'])->keyBy('shop_id');

    expect((float) $byShop[$this->shopA->id]['revenue'])->toBe(100.0)
        ->and((float) $byShop[$this->shopB->id]['revenue'])->toBe(400.0)
        ->and((int) $byShop[$this->shopA->id]['transactions'])->toBe(1)
        ->and((int) $byShop[$this->shopB->id]['transactions'])->toBe(1);
});
