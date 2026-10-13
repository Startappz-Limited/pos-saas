<?php

use App\Actions\CheckLowStockLevels;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;

/**
 * Variations have no reorder level of their own: a variation is low when its
 * stock is at or below its product's reorder level. The variation check used
 * a reorder_level column that product_variations doesn't have, which failed on
 * MySQL and MariaDB (stage 0, step 4c).
 */
beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
});

function productWithVariation(int $reorderLevel, int $variationStock, bool $trackStock = true): ProductVariation
{
    $product = Product::factory()->create([
        'has_variations' => true,
        'track_stock' => $trackStock,
        'reorder_level' => $reorderLevel,
        'stock_quantity' => 100,
    ]);

    return ProductVariation::factory()->create([
        'product_id' => $product->id,
        'stock_quantity' => $variationStock,
    ]);
}

function checkLevels(): void
{
    app(CheckLowStockLevels::class)->execute(test()->shop->id, test()->user->id);
}

it('raises an alert for a variation at or below its product\'s reorder level', function (int $stock) {
    $variation = productWithVariation(reorderLevel: 10, variationStock: $stock);

    checkLevels();

    $alert = LowStockAlert::where('variation_id', $variation->id)->sole();
    expect($alert->product_id)->toBe($variation->product_id)
        ->and($alert->shop_id)->toBe($this->shop->id)
        ->and((int) $alert->current_quantity)->toBe($stock)
        ->and((int) $alert->threshold_quantity)->toBe(10);
})->with(['below' => 3, 'exactly at' => 10]);

it('raises no alert for a variation above its product\'s reorder level', function () {
    $variation = productWithVariation(reorderLevel: 10, variationStock: 11);

    checkLevels();

    expect(LowStockAlert::where('variation_id', $variation->id)->exists())->toBeFalse();
});

it('ignores variations of products that don\'t track stock', function () {
    $variation = productWithVariation(reorderLevel: 10, variationStock: 0, trackStock: false);

    checkLevels();

    expect(LowStockAlert::where('variation_id', $variation->id)->exists())->toBeFalse();
});

it('still checks simple products against their own reorder level', function () {
    $low = Product::factory()->create(['has_variations' => false, 'track_stock' => true, 'reorder_level' => 5, 'stock_quantity' => 2]);
    $fine = Product::factory()->create(['has_variations' => false, 'track_stock' => true, 'reorder_level' => 5, 'stock_quantity' => 50]);

    checkLevels();

    expect(LowStockAlert::where('product_id', $low->id)->whereNull('variation_id')->exists())->toBeTrue()
        ->and(LowStockAlert::where('product_id', $fine->id)->exists())->toBeFalse();
});
