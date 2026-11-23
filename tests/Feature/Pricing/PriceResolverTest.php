<?php

use App\Enums\PricingType;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\ShopUnitPrice;
use App\Support\Pricing\PriceResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Stage 11b, steps 1 and 2: PriceResolver prices every line. A unit's price comes from the shop, then the unit, then
 * the base unit times the factor; a quantity-break rule counts base units across the sale's lines of a variation and
 * never raises a price; a rule may name a unit, a variation or a customer.
 */

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->resolver = app(PriceResolver::class);

    // A bottle at 100, a crate of 24 at its own 2,280.
    $this->soda = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'bottle', 'selling_price' => 100, 'stock_quantity' => 0, 'has_variations' => false]);
    $this->bottle = $this->soda->defaultVariation->baseUnit;
    $this->crate = $this->soda->defaultVariation->units()->create([
        'business_id' => $this->soda->business_id, 'name' => 'crate', 'factor' => 24, 'precision' => 0,
        'selling_price' => 2280, 'is_base' => false,
    ]);
});

/** A line of $unit in $quantity units, as the resolver takes it. */
function resolverLine(Product $product, ProductVariation $variation, $unit, string $quantity): array
{
    return [
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'unit' => $unit,
        'base_quantity' => bcmul($quantity, (string) $unit->factor, 3),
    ];
}

it('prices a unit from the shop, then its own price, then the base unit times the factor', function () {
    $crate = $this->crate->fresh();
    $variation = $this->soda->defaultVariation;

    // 24 x the bottle: no crate price of its own.
    $crate->update(['selling_price' => null]);
    expect($this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $crate, '1')])[0])
        ->toMatchArray(['unit_price' => '2400.00', 'source' => 'base', 'rule_id' => null]);

    // The crate's own price, which wins over 24 x the bottle.
    $crate->update(['selling_price' => 2280]);
    expect($this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $crate, '1')])[0])
        ->toMatchArray(['unit_price' => '2280.00', 'source' => 'unit']);

    // The shop's price for the crate wins over both.
    ShopUnitPrice::create(['shop_id' => $this->shop->id, 'unit_id' => $crate->id, 'selling_price' => 2200]);
    expect($this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $crate, '1')])[0])
        ->toMatchArray(['unit_price' => '2200.00', 'source' => 'shop']);
});

it('counts a quantity break in base units across all the lines of the variation', function () {
    $variation = $this->soda->defaultVariation;
    PricingRule::create([
        'name' => '24 or more', 'type' => PricingType::BULK, 'product_id' => $this->soda->id, 'price' => 90,
        'min_quantity' => 24, 'is_active' => true, 'priority' => 0,
    ]);

    // 1 crate alone is 24 bottles: the break applies, 90 a bottle x 24 = 2,160, below the crate's own 2,280.
    $one = $this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $this->crate->fresh(), '1')]);
    expect($one[0])->toMatchArray(['unit_price' => '2160.00', 'source' => 'rule']);

    // 24 bottles in one line: the same tier.
    $bottles = $this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $this->bottle, '24')]);
    expect($bottles[0]['unit_price'])->toBe('90.00');

    // 1 crate and 3 bottles on separate lines: 27 bottles in all, so both lines take the break.
    $mixed = $this->resolver->quote($this->shop, null, [
        resolverLine($this->soda, $variation, $this->crate->fresh(), '1'),
        resolverLine($this->soda, $variation, $this->bottle, '3'),
    ]);
    expect($mixed[0]['unit_price'])->toBe('2160.00')
        ->and($mixed[1]['unit_price'])->toBe('90.00');

    // 23 bottles: no break.
    $below = $this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $this->bottle, '23')]);
    expect($below[0]['unit_price'])->toBe('100.00')
        ->and($below[0]['rule_id'])->toBeNull();
});

it('never raises a price: a rule dearer than the unit leaves it alone', function () {
    $variation = $this->soda->defaultVariation;
    PricingRule::create([
        'name' => 'dear', 'type' => PricingType::BULK, 'product_id' => $this->soda->id, 'price' => 200,
        'min_quantity' => 1, 'is_active' => true, 'priority' => 0,
    ]);

    $quote = $this->resolver->quote($this->shop, null, [resolverLine($this->soda, $variation, $this->crate->fresh(), '1')]);

    expect($quote[0])->toMatchArray(['unit_price' => '2280.00', 'source' => 'unit', 'rule_id' => null]);
});

it('applies a rule scoped to a unit only to lines in that unit', function () {
    $variation = $this->soda->defaultVariation;
    PricingRule::create([
        'name' => 'boxes only', 'type' => PricingType::BULK, 'product_id' => $this->soda->id, 'unit_id' => $this->crate->id,
        'price' => 80, 'min_quantity' => 1, 'is_active' => true, 'priority' => 0,
    ]);

    $quote = $this->resolver->quote($this->shop, null, [
        resolverLine($this->soda, $variation, $this->crate->fresh(), '1'),
        resolverLine($this->soda, $variation, $this->bottle, '1'),
    ]);

    expect($quote[0]['unit_price'])->toBe('1920.00') // 80 x 24, below 2,280
        ->and($quote[1]['unit_price'])->toBe('100.00');
});

it('applies a rule scoped to one variation only to that variation', function () {
    $other = ProductVariation::factory()->create(['product_id' => $this->soda->id, 'selling_price' => 100]);
    $first = $this->soda->defaultVariation;
    PricingRule::create([
        'name' => 'first only', 'type' => PricingType::BULK, 'product_id' => $this->soda->id, 'variation_id' => $first->id,
        'price' => 70, 'min_quantity' => 1, 'is_active' => true, 'priority' => 0,
    ]);

    $quote = $this->resolver->quote($this->shop, null, [
        resolverLine($this->soda, $first, $first->baseUnit, '1'),
        resolverLine($this->soda, $other, $other->baseUnit, '1'),
    ]);

    expect($quote[0]['unit_price'])->toBe('70.00')
        ->and($quote[1]['unit_price'])->toBe('100.00');
});

it('applies a customer-specific rule to that customer only', function () {
    $variation = $this->soda->defaultVariation;
    $customer = Customer::factory()->create(['customer_type' => 'retail']);
    $stranger = Customer::factory()->create(['customer_type' => 'retail']);
    PricingRule::create([
        'name' => 'for one', 'type' => PricingType::CUSTOMER_SPECIFIC, 'product_id' => $this->soda->id, 'customer_id' => $customer->id,
        'price' => 85, 'is_active' => true, 'priority' => 0,
    ]);

    $line = [resolverLine($this->soda, $variation, $this->bottle, '1')];

    expect($this->resolver->quote($this->shop, $customer, $line)[0]['unit_price'])->toBe('85.00')
        ->and($this->resolver->quote($this->shop, $stranger, $line)[0]['unit_price'])->toBe('100.00')
        ->and($this->resolver->quote($this->shop, null, $line)[0]['unit_price'])->toBe('100.00');
});

it('applies a dated rule only inside its dates, judged at the sale\'s time', function () {
    $variation = $this->soda->defaultVariation;
    PricingRule::create([
        'name' => 'weekend', 'type' => PricingType::TIME_BASED, 'product_id' => $this->soda->id, 'price' => 75,
        'start_date' => now()->addDay(), 'end_date' => now()->addDays(3), 'is_active' => true, 'priority' => 0,
    ]);

    $line = [resolverLine($this->soda, $variation, $this->bottle, '1')];

    expect($this->resolver->quote($this->shop, null, $line)[0]['unit_price'])->toBe('100.00')
        ->and($this->resolver->quote($this->shop, null, $line, now()->addDays(2))[0]['unit_price'])->toBe('75.00')
        ->and($this->resolver->quote($this->shop, null, $line, now()->addDays(5))[0]['unit_price'])->toBe('100.00');
});

it('refuses a customer-specific rule that names a staff user, once the re-point has run', function () {
    expect(fn () => PricingRule::create([
        'name' => 'names a staff member', 'type' => PricingType::CUSTOMER_SPECIFIC, 'product_id' => $this->soda->id,
        'customer_id' => 999999, 'price' => 85, 'is_active' => true, 'priority' => 0,
    ]))->toThrow(QueryException::class);
});

it('lists a rule that names someone who is not a customer of this business, and changes nothing', function () {
    // A customer of another business satisfies the foreign key, yet is not a customer of this one: the check lists it.
    $elsewhere = Customer::factory()->create(['business_id' => Business::factory()->create()->id]);
    PricingRule::create([
        'name' => 'names a stranger', 'type' => PricingType::CUSTOMER_SPECIFIC, 'product_id' => $this->soda->id,
        'customer_id' => $elsewhere->id, 'price' => 85, 'is_active' => true, 'priority' => 0,
    ]);

    $this->artisan('pricing-rules:check-customers')
        ->expectsOutputToContain('names a stranger')
        ->assertExitCode(1);

    expect(DB::table('pricing_rules')->where('customer_id', $elsewhere->id)->exists())->toBeTrue();
});
