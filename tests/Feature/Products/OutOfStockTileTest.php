<?php

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ProductService;
use App\Services\Stock\StockLedger;

/*
 * The out-of-stock tile counts products with no stock in the shops the viewer may see, not a
 * status (stage 11, step 6).
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
});

it('counts an active tracked product with no stock, and not one that has stock', function () {
    Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'track_stock' => true, 'status' => ProductStatus::ACTIVE]);
    $stocked = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'track_stock' => true, 'status' => ProductStatus::ACTIVE]);
    app(StockLedger::class)->receive($this->shop->id, $stocked->defaultVariation, '5.000', 5.0, StockMovementType::INTAKE);

    expect(app(ProductService::class)->getStatistics()['out_of_stock'])->toBe(1);
});

it('leaves an untracked product and an inactive one out of the count', function () {
    Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'track_stock' => false, 'status' => ProductStatus::ACTIVE]);
    Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'track_stock' => true, 'status' => ProductStatus::INACTIVE]);

    expect(app(ProductService::class)->getStatistics()['out_of_stock'])->toBe(0);
});
