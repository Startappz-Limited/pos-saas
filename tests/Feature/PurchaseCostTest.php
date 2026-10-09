<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use App\Services\Integration\ImageDownloadService;
use App\Services\Integration\ShopifyProductSyncService;
use App\Services\Integration\WooCommerceProductSyncService;
use Database\Factories\BusinessFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Regression cover for negative profit caused by a purchase cost that was never
 * a purchase cost.
 *
 * The WooCommerce importer wrote `regular_price` — the pre-discount SELLING
 * price — into `cost_price`, and Shopify's wrote `compare_at_price`. Both sit at
 * or above what the customer actually pays, so 60 of 78 live products were
 * priced to lose money and 19 of 25 sales reported negative profit.
 */
beforeEach(function () {
    foreach (['products.view', 'products.update', 'products.set-cost'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->shop = Shop::factory()->create();
});

function costUser(array $permissions = ['products.view', 'products.set-cost']): User
{
    $user = User::factory()->create();
    $user->shops()->sync([Shop::first()?->id ?? Shop::factory()->create()->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

describe('importer cost mapping', function () {
    it('does not write the WooCommerce regular price into cost price', function () {
        $service = new WooCommerceProductSyncService(app(ImageDownloadService::class));

        $service->syncSingleProduct($this->shop, [
            'id' => 501,
            'sku' => 'WC-TEST-001',
            'name' => 'Discounted Watch',
            'description' => 'A watch',
            'regular_price' => '2400',
            'price' => '1999',
            'stock_quantity' => 5,
            'manage_stock' => true,
            'status' => 'publish',
            'categories' => [],
        ]);

        $product = Product::where('sku', 'WC-TEST-001')->firstOrFail();

        expect($product->cost_price)->toBeNull()
            ->and((float) $product->selling_price)->toBe(1999.0)
            ->and($product->hasPurchaseCost())->toBeFalse();
    });

    it('reads a real cost from a WooCommerce cost-of-goods meta field', function () {
        $service = new WooCommerceProductSyncService(app(ImageDownloadService::class));

        $service->syncSingleProduct($this->shop, [
            'id' => 502,
            'sku' => 'WC-TEST-002',
            'name' => 'Costed Watch',
            'description' => '',
            'regular_price' => '2400',
            'price' => '1999',
            'stock_quantity' => 5,
            'manage_stock' => true,
            'status' => 'publish',
            'categories' => [],
            'meta_data' => [
                ['key' => '_wc_cog_cost', 'value' => '1200.50'],
            ],
        ]);

        $product = Product::where('sku', 'WC-TEST-002')->firstOrFail();

        expect((float) $product->cost_price)->toBe(1200.50);
    });

    it('never overwrites a purchase cost that already exists locally', function () {
        $product = Product::factory()->create([
            'sku' => 'WC-TEST-003',
            'cost_price' => 900,
            'selling_price' => 1999,
        ]);

        $service = new WooCommerceProductSyncService(app(ImageDownloadService::class));

        $service->syncSingleProduct($this->shop, [
            'id' => 503,
            'sku' => 'WC-TEST-003',
            'name' => 'Renamed Watch',
            'description' => '',
            'regular_price' => '2400',
            'price' => '1799',
            'stock_quantity' => 5,
            'manage_stock' => true,
            'status' => 'publish',
            'categories' => [],
        ]);

        $product->refresh();

        expect((float) $product->cost_price)->toBe(900.0)
            ->and((float) $product->selling_price)->toBe(1799.0);
    });

    it('does not treat the Shopify compare-at price as a cost', function () {
        $service = new ShopifyProductSyncService(app(ImageDownloadService::class));

        $service->syncSingleVariant(
            $this->shop,
            [
                'id' => 900,
                'title' => 'Shopify Tee',
                'body_html' => '',
                'status' => 'active',
                'variants' => [['id' => 901]],
                'product_type' => 'Apparel',
            ],
            [
                'id' => 901,
                'sku' => 'SH-TEST-001',
                'price' => '1500',
                'compare_at_price' => '2500',
                'inventory_quantity' => 3,
                'inventory_management' => 'shopify',
            ],
        );

        $product = Product::where('sku', 'SH-TEST-001')->firstOrFail();

        expect($product->cost_price)->toBeNull()
            ->and((float) $product->selling_price)->toBe(1500.0);
    });

    it('uses the Shopify inventory item cost when the payload carries one', function () {
        $service = new ShopifyProductSyncService(app(ImageDownloadService::class));

        $service->syncSingleVariant(
            $this->shop,
            [
                'id' => 910,
                'title' => 'Costed Tee',
                'body_html' => '',
                'status' => 'active',
                'variants' => [['id' => 911]],
                'product_type' => 'Apparel',
            ],
            [
                'id' => 911,
                'sku' => 'SH-TEST-002',
                'price' => '1500',
                'compare_at_price' => '2500',
                'inventory_quantity' => 3,
                'inventory_item' => ['cost' => '640.00'],
            ],
        );

        expect((float) Product::where('sku', 'SH-TEST-002')->firstOrFail()->cost_price)->toBe(640.0);
    });
});

describe('purchase cost screen', function () {
    it('is closed to users without products.set-cost', function () {
        $user = costUser(['products.view']);

        $this->actingAs($user)
            ->get(route('products.purchase-costs.index'))
            ->assertForbidden();
    });

    it('lists products whose cost is unset or above the selling price', function () {
        $missing = Product::factory()->withoutPurchaseCost()->create(['name' => 'Needs A Cost']);
        $inverted = Product::factory()->costAboveSellingPrice()->create(['name' => 'Sells At A Loss']);
        $healthy = Product::factory()->create([
            'name' => 'Perfectly Fine',
            'cost_price' => 100,
            'selling_price' => 250,
        ]);

        $this->actingAs(costUser())
            ->get(route('products.purchase-costs.index'))
            ->assertOk()
            ->assertSee($missing->name)
            ->assertSee($inverted->name)
            ->assertDontSee($healthy->name);
    });

    it('saves keyed costs and leaves blank fields untouched', function () {
        $filled = Product::factory()->withoutPurchaseCost()->create();
        $blank = Product::factory()->withoutPurchaseCost()->create();

        $this->actingAs(costUser())
            ->put(route('products.purchase-costs.update'), [
                'costs' => [
                    'product' => [
                        $filled->id => '742.25',
                        $blank->id => '',
                    ],
                ],
            ])
            ->assertRedirect();

        expect((float) $filled->fresh()->cost_price)->toBe(742.25)
            ->and($blank->fresh()->cost_price)->toBeNull();
    });

    it('rejects a negative purchase cost', function () {
        $product = Product::factory()->withoutPurchaseCost()->create();

        $this->actingAs(costUser())
            ->put(route('products.purchase-costs.update'), [
                'costs' => ['product' => [$product->id => '-5']],
            ])
            ->assertSessionHasErrors("costs.product.{$product->id}");

        expect($product->fresh()->cost_price)->toBeNull();
    });

    it('refuses the write for a user without products.set-cost', function () {
        $product = Product::factory()->withoutPurchaseCost()->create();

        $this->actingAs(costUser(['products.view']))
            ->put(route('products.purchase-costs.update'), [
                'costs' => ['product' => [$product->id => '100']],
            ])
            ->assertForbidden();

        expect($product->fresh()->cost_price)->toBeNull();
    });
});

describe('products:repair-purchase-cost', function () {
    it('reports without writing unless --apply is given', function () {
        $product = Product::factory()->costAboveSellingPrice()->create();

        $this->artisan('products:repair-purchase-cost')
            ->assertSuccessful();

        expect((float) $product->fresh()->cost_price)->toBe(2400.0);
    });

    it('clears costs at or above the selling price and leaves healthy ones alone', function () {
        $inverted = Product::factory()->costAboveSellingPrice()->create();
        $zeroMargin = Product::factory()->create(['cost_price' => 500, 'selling_price' => 500]);
        $healthy = Product::factory()->create(['cost_price' => 200, 'selling_price' => 500]);

        $this->artisan('products:repair-purchase-cost --apply')
            ->assertSuccessful();

        expect($inverted->fresh()->cost_price)->toBeNull()
            ->and($zeroMargin->fresh()->cost_price)->toBeNull()
            ->and((float) $healthy->fresh()->cost_price)->toBe(200.0);
    });

    it('keeps zero-margin costs when --strict is given', function () {
        $zeroMargin = Product::factory()->create(['cost_price' => 500, 'selling_price' => 500]);

        $this->artisan('products:repair-purchase-cost --apply --strict')
            ->assertSuccessful();

        expect((float) $zeroMargin->fresh()->cost_price)->toBe(500.0);
    });
});

describe('sales:recompute-profit', function () {
    it('rewrites a negative profit once the real cost is known', function () {
        $product = Product::factory()->create(['cost_price' => 1200, 'selling_price' => 1999]);
        $sale = saleWithItem($product, quantity: 2, unitPrice: 1999, bakedUnitCost: 2400);

        expect((float) $sale->total_profit)->toBeLessThan(0);

        $this->artisan('sales:recompute-profit --apply')->assertSuccessful();

        $sale->refresh();
        $item = $sale->items()->first();

        // 2 x 1999 sold, 2 x 1200 cost => 1598 profit, not -802.
        expect((float) $item->unit_cost)->toBe(1200.0)
            ->and((float) $item->total_cost)->toBe(2400.0)
            ->and((float) $item->profit)->toBe(1598.0)
            ->and((float) $sale->total_cost)->toBe(2400.0)
            ->and((float) $sale->total_profit)->toBe(1598.0);
    });

    it('changes nothing without --apply', function () {
        $product = Product::factory()->create(['cost_price' => 1200, 'selling_price' => 1999]);
        $sale = saleWithItem($product, quantity: 2, unitPrice: 1999, bakedUnitCost: 2400);

        $this->artisan('sales:recompute-profit')->assertSuccessful();

        expect((float) $sale->fresh()->total_profit)->toBe(-802.0);
    });

    it('skips a sale whose product still has no purchase cost', function () {
        $product = Product::factory()->withoutPurchaseCost()->create(['selling_price' => 1999]);
        $sale = saleWithItem($product, quantity: 1, unitPrice: 1999, bakedUnitCost: 2400);

        $this->artisan('sales:recompute-profit --apply')->assertSuccessful();

        // Left exactly as it was rather than silently costed at zero.
        expect((float) $sale->fresh()->total_profit)->toBe(-401.0)
            ->and((float) $sale->items()->first()->unit_cost)->toBe(2400.0);
    });

    it('costs unknown products at zero only when explicitly asked', function () {
        $product = Product::factory()->withoutPurchaseCost()->create(['selling_price' => 1999]);
        $sale = saleWithItem($product, quantity: 1, unitPrice: 1999, bakedUnitCost: 2400);

        $this->artisan('sales:recompute-profit --apply --zero-unknown')->assertSuccessful();

        expect((float) $sale->fresh()->total_profit)->toBe(1999.0)
            ->and((float) $sale->items()->first()->unit_cost)->toBe(0.0);
    });
});

/**
 * Builds a completed sale carrying an already-baked (wrong) unit cost, which is
 * how the live data got into trouble.
 */
function saleWithItem(Product $product, int $quantity, float $unitPrice, float $bakedUnitCost): Sale
{
    $shop = Shop::first() ?? Shop::factory()->create();
    $lineTotal = $quantity * $unitPrice;
    $totalCost = $quantity * $bakedUnitCost;

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'subtotal' => $lineTotal,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'delivery_fee' => 0,
        'packaging_fee' => 0,
        'other_expenses' => 0,
        'total_amount' => $lineTotal,
        'total_cost' => $totalCost,
        'total_profit' => $lineTotal - $totalCost,
    ]);

    SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
        'unit_cost' => $bakedUnitCost,
        'total_cost' => $totalCost,
        'profit' => $lineTotal - $totalCost,
        'profit_margin' => (($lineTotal - $totalCost) / $lineTotal) * 100,
        'status' => 'completed',
    ]);

    return $sale->fresh();
}

it('still completes a sale when the purchase cost is unknown', function () {
    // sale_items.unit_cost is NOT NULL, so a NULL cost_price must be coalesced
    // rather than passed through — otherwise clearing the bad costs would take
    // the till down for every affected product.
    Permission::findOrCreate('sales.create');
    $user = User::factory()->create();
    $user->shops()->sync([$this->shop->id]);
    $user->givePermissionTo('sales.create');

    $source = SaleSource::create(['business_id' => BusinessFactory::defaultId(), 'name' => 'Counter', 'is_active' => true, 'sort_order' => 1]);
    $product = Product::factory()->withoutPurchaseCost()->create([
        'shop_id' => $this->shop->id,
        'stock_quantity' => 10,
        'selling_price' => 1999,
    ]);
    $customer = Customer::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_type' => 'retail',
    ]);

    $this->actingAs($user)
        ->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'source_id' => $source->id,
            'delivery_location' => 'Front counter',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 1999],
            ],
        ])
        ->assertRedirect();

    $item = SaleItem::firstOrFail();

    expect((float) $item->unit_cost)->toBe(0.0)
        ->and((float) $item->total_cost)->toBe(0.0)
        ->and((float) Sale::firstOrFail()->total_profit)->toBe(3998.0);
});

it('keeps a product cost null instead of coercing it to zero', function () {
    $product = Product::create([
        'name' => 'No Cost Yet',
        'sku' => 'NC-001',
        'category_id' => Category::factory()->create()->id,
        'selling_price' => 500,
        'stock_quantity' => 1,
    ]);

    expect($product->fresh()->cost_price)->toBeNull()
        ->and($product->hasPurchaseCost())->toBeFalse();
});
