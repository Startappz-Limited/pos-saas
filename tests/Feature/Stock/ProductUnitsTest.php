<?php

use App\Actions\Product\ImportProductsFromCsv;
use App\Exceptions\BusinessRuleException;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopUnitPrice;
use App\Support\Pricing\UnitPrice;
use App\Support\Stock\UnitBreakdown;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

/*
 * Units of measure (stage 11, step 2): every variation has exactly one base
 * unit, created automatically; any number of pack units sit on top of it.
 */

beforeEach(function () {
    $this->shop = Shop::factory()->create();
});

it('creates a base unit for a new variation, named and sized from the product\'s unit', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'piece']);
    $variation = $product->defaultVariation;

    $base = $variation->units()->sole();
    expect($base->is_base)->toBeTrue()
        ->and($base->name)->toBe('piece')
        ->and($base->factor)->toBe('1.000')
        ->and($base->precision)->toBe(0)
        ->and($base->can_sell)->toBeTrue()
        ->and($base->is_default_purchase)->toBeTrue();
});

it('guesses precision from the unit name: whole for pieces, three decimals for kg and the like', function () {
    expect(ProductUnit::guessPrecision('piece'))->toBe(0)
        ->and(ProductUnit::guessPrecision('kg'))->toBe(3)
        ->and(ProductUnit::guessPrecision('liter'))->toBe(3)
        ->and(ProductUnit::guessPrecision('box'))->toBe(0)
        ->and(ProductUnit::guessPrecision(null))->toBe(0);
});

it('works Panadol: tablet base, strip of 10, box of 100', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'tablet']);
    $variation = $product->defaultVariation;
    $base = $variation->baseUnit;

    $strip = $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'strip', 'factor' => 10, 'precision' => 0, 'selling_price' => 50, 'is_base' => false]);
    $box = $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'box', 'factor' => 100, 'precision' => 0, 'selling_price' => 450, 'is_base' => false]);

    expect($base->precision)->toBe(0)
        ->and((float) $strip->factor * 2 + (float) $box->factor * 1)->toBe(120.0)
        ->and(UnitBreakdown::describe($variation, '120.000'))->toBe('1 box + 20 tablet (120 tablet)');
});

it('works fertiliser: kg base to three decimals, bag of 50 kg', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg']);
    $variation = $product->defaultVariation;
    $base = $variation->baseUnit;
    $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'bag', 'factor' => 50, 'precision' => 0, 'is_base' => false]);

    expect($base->precision)->toBe(3)
        ->and(UnitBreakdown::describe($variation, '2.500'))->toBe('2.5 kg')
        ->and(UnitBreakdown::describe($variation, '162.500'))->toBe('3 bag + 12.5 kg (162.5 kg)');
});

it('works soda: bottle base, crate of 24 with its own barcode', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'bottle']);
    $variation = $product->defaultVariation;
    $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'crate', 'factor' => 24, 'precision' => 0, 'barcode' => '6001000000099', 'is_base' => false]);

    expect(UnitBreakdown::describe($variation, '293.000'))->toBe('12 crate + 5 bottle (293 bottle)');
});

it('allows exactly one base unit per variation', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $variation = $product->defaultVariation;

    expect(fn () => $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'second-base', 'factor' => 1, 'is_base' => true]))
        ->toThrow(QueryException::class);
});

it('refuses to change the base unit\'s factor, or to unbase it', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $base = $product->defaultVariation->baseUnit;

    expect(fn () => $base->update(['factor' => 2]))->toThrow(BusinessRuleException::class)
        ->and(fn () => $base->update(['is_base' => false]))->toThrow(BusinessRuleException::class);
});

it('refuses to remove the base unit, but allows removing a pack unit', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $variation = $product->defaultVariation;
    $pack = $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'box', 'factor' => 10, 'is_base' => false]);

    expect(fn () => $variation->baseUnit->delete())->toThrow(BusinessRuleException::class);
    $pack->delete();
    expect($pack->fresh()->trashed())->toBeTrue();
});

it('allows an unused unit\'s factor to change', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $variation = $product->defaultVariation;
    $pack = $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'box', 'factor' => 10, 'is_base' => false]);

    $pack->update(['factor' => 12]);

    expect((string) $pack->fresh()->factor)->toBe('12.000');
});

it('frees a deleted product\'s barcode by soft-deleting its units, not the variation', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'barcode' => '5000000000001']);
    $unit = $product->defaultVariation->baseUnit;

    $product->delete();

    expect($unit->fresh()->trashed())->toBeTrue()
        ->and($product->defaultVariation()->exists())->toBeTrue();

    $second = Product::factory()->create(['shop_id' => $this->shop->id, 'barcode' => '5000000000001']);
    expect($second->exists)->toBeTrue();
});

it('refuses a unit barcode clashing with another unit in the business, but allows it in another business', function () {
    $productA = Product::factory()->create(['shop_id' => $this->shop->id]);
    $productB = Product::factory()->create(['shop_id' => $this->shop->id]);
    $productA->defaultVariation->units()->create(['business_id' => $productA->business_id, 'name' => 'box', 'factor' => 10, 'barcode' => '111', 'is_base' => false]);

    expect(fn () => $productB->defaultVariation->units()->create(['business_id' => $productB->business_id, 'name' => 'box', 'factor' => 10, 'barcode' => '111', 'is_base' => false]))
        ->toThrow(QueryException::class);

    $otherBusiness = app(TenantContext::class)->run(Business::factory()->create(), function () {
        $shop = Shop::factory()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        return $product->defaultVariation->units()->create(['business_id' => $product->business_id, 'name' => 'box', 'factor' => 10, 'barcode' => '111', 'is_base' => false]);
    });

    expect($otherBusiness->exists)->toBeTrue();
});

it('resolves a unit of its own variation, including soft-deleted, within the current business', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $variation = $product->defaultVariation;
    $pack = $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'box', 'factor' => 10, 'is_base' => false]);
    $pack->delete();

    $other = Product::factory()->create(['shop_id' => $this->shop->id])->defaultVariation;

    expect(ProductUnit::resolveFor($variation, $pack->uuid)?->id)->toBe($pack->id)
        ->and(ProductUnit::resolveFor($other, $pack->uuid))->toBeNull();
});

it('prices a unit: shop override, then its own, then the base times the factor', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'selling_price' => 100]);
    $variation = $product->defaultVariation;
    $base = $variation->baseUnit;
    $base->update(['selling_price' => 100]);
    $pack = $variation->units()->create(['business_id' => $variation->business_id, 'name' => 'box', 'factor' => 10, 'is_base' => false, 'selling_price' => null]);

    expect(UnitPrice::for($this->shop, $pack))->toBe(1000.0);

    $pack->update(['selling_price' => 900]);
    expect(UnitPrice::for($this->shop, $pack))->toBe(900.0);

    ShopUnitPrice::create(['shop_id' => $this->shop->id, 'unit_id' => $pack->id, 'selling_price' => 850]);
    expect(UnitPrice::for($this->shop, $pack))->toBe(850.0);
});

it('gives a wholesale customer the wholesale price where one is set', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'selling_price' => 100, 'wholesale_price' => 90]);
    $variation = $product->defaultVariation;
    $base = $variation->baseUnit;
    $base->update(['selling_price' => 100, 'wholesale_price' => 90]);
    $customer = Customer::factory()->create(['customer_type' => 'wholesale']);

    expect(UnitPrice::for($this->shop, $base, $customer))->toBe(90.0)
        ->and(UnitPrice::for($this->shop, $base))->toBe(100.0);
});

it('imports a pack unit and a precision override from the CSV columns', function () {
    $import = app(ImportProductsFromCsv::class);
    $csv = "name,selling_price,unit,unit_precision,pack_unit,pack_factor,pack_barcode,pack_price\nFertiliser,50,kg,2,bag,50,,2000\n";
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    $analysis = $import->analyse($path);
    expect($analysis['errors'])->toBe([]);
    $import->import($analysis, $this->shop, null);

    $product = Product::query()->where('name', 'Fertiliser')->firstOrFail();
    $variation = $product->defaultVariation;

    expect($variation->baseUnit->precision)->toBe(2)
        ->and($variation->units()->where('is_base', false)->sole())
        ->name->toBe('bag')
        ->and((string) $variation->units()->where('is_base', false)->sole()->factor)->toBe('50.000')
        ->and((float) $variation->units()->where('is_base', false)->sole()->selling_price)->toBe(2000.0);

    unlink($path);
});
