<?php

use App\Actions\Catalogue\CopyCatalogueToUnits;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopUnitPrice;
use App\Models\UnassignedBarcode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * Release 1 of stage 11, step 7: the copy lists every difference and writes nothing on a dry run;
 * --apply copies once; a second run changes nothing; a barcode clash stops the copy; a product's
 * barcode on a product with real variations waits in unassigned_barcodes; verify fails until the
 * copy has run.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->simple = legacy(Product::factory()->create([
        'shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false,
        'barcode' => 'SIMPLE-1', 'unit' => 'piece', 'selling_price' => 100, 'cost_price' => 50,
    ]));
});

/**
 * Puts a product in the state before step 2: its variation has no base unit yet. Creating a
 * product now makes its base unit (ProductVariation::createBaseUnit), so a legacy row is simulated.
 */
function legacy(Product $product): Product
{
    DB::table('product_units')->where('variation_id', $product->defaultVariation->id)->delete();

    return $product;
}

function copyCommand(bool $apply = false): int
{
    return Artisan::call('catalogue:copy-to-units', array_filter([
        '--business' => testBusiness()->id, '--apply' => $apply ?: null,
    ]));
}

function baseUnitOf(Product $product): ?ProductUnit
{
    return ProductUnit::query()->withTrashed()->where('variation_id', $product->defaultVariation->id)->where('is_base', true)->first();
}

it('lists the plan and writes nothing on a dry run', function () {
    copyCommand(false);

    expect(ProductUnit::query()->withTrashed()->count())->toBe(0)
        ->and(ShopUnitPrice::query()->count())->toBe(0);
});

it('copies a simple product\'s base unit with its barcode, prices and cost, on --apply', function () {
    copyCommand(true);

    $base = baseUnitOf($this->simple);
    expect($base)->not->toBeNull()
        ->and($base->name)->toBe('piece')
        ->and($base->precision)->toBe(0)
        ->and($base->barcode)->toBe('SIMPLE-1')
        ->and($base->is_base)->toBeTrue()
        ->and((float) $base->selling_price)->toBe(100.0);
});

it('makes a second --apply change nothing', function () {
    copyCommand(true);
    $units = ProductUnit::query()->withTrashed()->count();
    $prices = ShopUnitPrice::query()->count();

    copyCommand(true);

    expect(ProductUnit::query()->withTrashed()->count())->toBe($units)
        ->and(ShopUnitPrice::query()->count())->toBe($prices);
});

it('gives a weight a three-decimal base unit, and lists a unit name it doesn\'t know', function () {
    $kg = legacy(Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'barcode' => 'KG-1', 'unit' => 'kg']));
    $bag = legacy(Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'barcode' => 'BAG-1', 'unit' => 'sack']));

    $report = app(CopyCatalogueToUnits::class)->run(false);

    expect(app(CopyCatalogueToUnits::class)->precisionFor('kg'))->toBe(3)
        ->and(app(CopyCatalogueToUnits::class)->precisionFor('piece'))->toBe(0)
        ->and(collect($report['precision_unlisted'])->implode(' '))->toContain($bag->name)
        ->and(collect($report['precision_unlisted'])->implode(' '))->not->toContain($kg->name);

    copyCommand(true);
    expect(baseUnitOf($kg)->precision)->toBe(3);
});

it('moves a product barcode on a product with real variations to the waiting list, not onto its hidden default variation', function () {
    $tee = legacy(Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => true, 'barcode' => 'TEE-ALL', 'unit' => 'piece']));

    copyCommand(true);

    expect(UnassignedBarcode::query()->where('product_id', $tee->id)->value('barcode'))->toBe('TEE-ALL')
        ->and(baseUnitOf($tee)->barcode)->toBeNull();
});

it('stops the copy on a barcode clash, writing nothing, and lists the clash', function () {
    // Another variation already holds the simple product's barcode.
    $other = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false]);
    ProductUnit::factory()->create(['variation_id' => $other->defaultVariation->id, 'name' => 'Pack', 'factor' => '6.000', 'precision' => 0, 'barcode' => 'SIMPLE-1']);

    $report = app(CopyCatalogueToUnits::class)->run(true);

    expect($report['clashes'])->not->toBeEmpty()
        ->and($report['applied'])->toBeFalse()
        ->and(baseUnitOf($this->simple))->toBeNull();
});

it('keeps a variation\'s price when the product\'s differs, and lists it', function () {
    $this->simple->defaultVariation->update(['selling_price' => 120]);

    $report = app(CopyCatalogueToUnits::class)->run(false);

    expect(collect($report['differences'])->implode(' '))->toContain($this->simple->name);
});

it('fails verify until the copy has run, and passes after it', function () {
    expect(Artisan::call('catalogue:verify-units', ['--business' => testBusiness()->id]))->not->toBe(0);

    copyCommand(true);

    expect(Artisan::call('catalogue:verify-units', ['--business' => testBusiness()->id]))->toBe(0);
});

it('warns about a waiting barcode without failing verify', function () {
    legacy(Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => true, 'barcode' => 'WAIT-1']));
    copyCommand(true);

    expect(Artisan::call('catalogue:verify-units', ['--business' => testBusiness()->id]))->toBe(0);
});
