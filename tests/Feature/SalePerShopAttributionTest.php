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
    // Works in both shops (shop access is default-deny).
    $this->user = assignToNewShops(User::factory()->create());
    $this->actingAs($this->user);

    Permission::findOrCreate('sales.create');
    $this->user->givePermissionTo('sales.create');
});

// Stage 10, step 1a (decided 27 October 2026): a sale takes stock from the shop that sells it, so
// every line is the selling shop's, whichever shop each product was first stocked at.
test('a sale mixing products first stocked at two shops attributes every line to the selling shop', function () {
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
        'shop_id' => $this->shopA->id,
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

    // Every line, and the sale, belong to the selling shop.
    expect($sale->shop_id)->toBe($this->shopA->id)
        ->and(SaleItem::where('product_id', $productA->id)->firstOrFail()->shop_id)->toBe($this->shopA->id)
        ->and(SaleItem::where('product_id', $productB->id)->firstOrFail()->shop_id)->toBe($this->shopA->id);
});

test('shop performance report counts a sale\'s revenue under the shop that sold it', function () {
    $source = SaleSource::create(['name' => 'Counter', 'is_active' => true, 'sort_order' => 1]);

    $productA = Product::factory()->create([
        'shop_id' => $this->shopA->id, 'stock_quantity' => 10, 'selling_price' => 100, 'cost_price' => 40,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $this->shopB->id, 'stock_quantity' => 10, 'selling_price' => 200, 'cost_price' => 80,
    ]);
    $customer = Customer::factory()->create(['shop_id' => $this->shopA->id, 'customer_type' => 'retail']);

    $this->post(route('sales.store'), [
        'shop_id' => $this->shopB->id,
        'customer_id' => $customer->id,
        'source_id' => $source->id,
        'delivery_location' => 'Front counter',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'price' => 100], // first stocked at A
            ['product_id' => $productB->id, 'quantity' => 2, 'price' => 200], // first stocked at B
        ],
    ])->assertRedirect();

    $report = app(ReportService::class)->getDashboardData([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        // The shops the report covers are always named (stage 1c).
        'allowed_shop_ids' => [$this->shopA->id, $this->shopB->id],
    ]);

    $byShop = collect($report['shop_performance'])->keyBy('shop_id');

    // Sold at B: all 500 is B's, none A's.
    expect($byShop->has($this->shopA->id))->toBeFalse()
        ->and((float) $byShop[$this->shopB->id]['revenue'])->toBe(500.0)
        ->and((int) $byShop[$this->shopB->id]['transactions'])->toBe(1);
});
