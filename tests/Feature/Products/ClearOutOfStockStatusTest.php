<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\TwoBusinesses;

/*
 * Stage 11, step 6: the out_of_stock product status is retired. The command moves such rows to
 * inactive (hidden from online stores, as before), dry run by default, idempotent, and scoped to
 * one business.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
});

it('counts without writing on a dry run', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'status' => ProductStatus::OUT_OF_STOCK]);

    Artisan::call('products:clear-out-of-stock-status', ['--business' => testBusiness()->id]);

    expect($product->fresh()->status)->toBe(ProductStatus::OUT_OF_STOCK);
});

it('moves a by-hand out_of_stock row to inactive on --apply, and a second --apply changes nothing', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'status' => ProductStatus::OUT_OF_STOCK]);
    $active = Product::factory()->create(['shop_id' => $this->shop->id, 'status' => ProductStatus::ACTIVE]);

    Artisan::call('products:clear-out-of-stock-status', ['--business' => testBusiness()->id, '--apply' => true]);
    expect($product->fresh()->status)->toBe(ProductStatus::INACTIVE)
        ->and($active->fresh()->status)->toBe(ProductStatus::ACTIVE);

    Artisan::call('products:clear-out-of-stock-status', ['--business' => testBusiness()->id, '--apply' => true]);
    expect(Product::query()->withoutGlobalScopes()->where('status', ProductStatus::OUT_OF_STOCK->value)->count())->toBe(0);
});

it('never touches another business\'s products', function () {
    $two = TwoBusinesses::create();
    $theirs = app(TenantContext::class)->run($two->b->business, fn () => Product::factory()->create([
        'shop_id' => $two->b->shops[0]->id, 'status' => ProductStatus::OUT_OF_STOCK,
    ]));

    Artisan::call('products:clear-out-of-stock-status', ['--business' => testBusiness()->id, '--apply' => true]);

    expect(Product::query()->withoutGlobalScopes()->whereKey($theirs->id)->value('status'))->toBe(ProductStatus::OUT_OF_STOCK);
});
