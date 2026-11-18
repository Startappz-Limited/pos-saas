<?php

use App\Enums\ReturnReason;
use App\Enums\StockMovementType;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Str;

/*
 * Regressions from the review of the stock-units branch: a unit factor that stores as zero, a return
 * restocking more than its sale took out, the adjustment form's batch controls, the movement screens'
 * fractions and unit names, and return quantities that respect the sold unit's precision.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->ledger = app(StockLedger::class);
});

/**
 * A simple product on the test shop, with its default (base) variation.
 */
function simpleProduct(Shop $shop, string $unit, int $precision): Product
{
    $product = Product::factory()->create(['shop_id' => $shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => $unit]);
    $product->defaultVariation->baseUnit()->update(['precision' => $precision]);

    return $product->fresh();
}

/**
 * A completed sale of one line in the given unit: `quantity` in that unit, `baseQuantity` in base units.
 */
function soldLine(Shop $shop, Product $product, ?ProductUnit $unit, string $quantity, string $baseQuantity, string $unitCost = '5.0000'): SaleItem
{
    $sale = Sale::factory()->create(['shop_id' => $shop->id, 'status' => 'completed', 'customer_id' => null]);

    return SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'variation_id' => $product->defaultVariation->id,
        'unit_id' => $unit?->id,
        'quantity' => $quantity,
        'base_quantity' => $baseQuantity,
        'unit_price' => 100,
        'line_total' => 100,
        'unit_cost' => $unitCost,
        'total_cost' => bcmul($baseQuantity, $unitCost, 2),
        'status' => 'completed',
    ]);
}

it('refuses a pack factor that would store as zero, and accepts the smallest it can hold', function () {
    $product = simpleProduct($this->shop, 'piece', 0);

    $store = fn (string $factor, int $precision = 3) => $this->actingAs($this->owner)->post(route('products.units.store', $product), [
        'variation' => $product->defaultVariation->uuid,
        'name' => 'Sachet',
        'factor' => $factor,
        'precision' => $precision,
    ]);

    $store('0.0001')->assertSessionHasErrors('factor');
    $store('0.0015')->assertSessionHasErrors('factor');
    $store('0.001')->assertSessionHasNoErrors();

    expect(ProductUnit::query()->where('name', 'Sachet')->value('factor'))->toBe('0.001');
});

it('restocks a synced line in the base units it sold, not at the current factor', function () {
    $product = simpleProduct($this->shop, 'piece', 0);
    $crate = ProductUnit::factory()->create(['variation_id' => $product->defaultVariation->id, 'name' => 'Crate of 24', 'factor' => '24.000', 'precision' => 0]);

    // The till synced one crate as 20 bottles, against a factor of 24: the sale took 20 out.
    $saleItem = soldLine($this->shop, $product, $crate, '1.000', '20.000');

    $return = app(ReturnService::class)->createReturn([
        'sale_id' => $saleItem->sale_id,
        'reason' => ReturnReason::WRONG_ITEM->value,
        'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
    ], $this->owner->id);

    expect($return->items->first()->base_quantity)->toBe('20.000');

    app(ReturnService::class)->approveReturn($return, $this->owner->id);
    app(ReturnService::class)->receiveReturn($return);

    expect($this->ledger->exactQuantity($this->shop->id, $product->defaultVariation->id))->toBe('20.000');
});

it('refuses a fraction of a whole-unit line on return, and takes one of a weight line', function () {
    $tablets = simpleProduct($this->shop, 'tablet', 0);
    $flour = simpleProduct($this->shop, 'kg', 3);
    $tabletLine = soldLine($this->shop, $tablets, null, '2.000', '2.000');
    $flourLine = soldLine($this->shop, $flour, null, '2.000', '2.000');

    $post = fn (SaleItem $line, string $quantity) => $this->actingAs($this->owner)->post(route('returns.store'), [
        'sale_id' => $line->sale_id,
        'reason' => ReturnReason::WRONG_ITEM->value,
        'restocking_fee' => 0,
        'items' => [['sale_item_id' => $line->id, 'quantity' => $quantity, 'condition' => 'new']],
    ]);

    $post($tabletLine, '0.5')->assertSessionHasErrors('items.0.quantity');
    $post($flourLine, '0.5')->assertSessionHasNoErrors();

    expect(SaleReturn::query()->whereHas('items', fn ($q) => $q->where('sale_item_id', $tabletLine->id))->count())->toBe(0);
});

it('shows a fractional movement in its base unit, named and at its precision', function () {
    $flour = simpleProduct($this->shop, 'kg', 3);

    StockMovement::factory()->create([
        'shop_id' => $this->shop->id,
        'product_id' => $flour->id,
        'variation_id' => $flour->defaultVariation->id,
        'movement_type' => StockMovementType::SALE,
        'quantity' => '0.250',
    ]);

    $this->actingAs($this->owner)->get(route('stock-movements.index'))
        ->assertOk()
        ->assertSee('-0.250 kg', false)
        ->assertDontSee('-0<', false);
});

it('offers a batch-tracked product\'s open batches for its shop on the adjustment form', function () {
    $product = simpleProduct($this->shop, 'piece', 0);
    $product->defaultVariation->update(['track_batches' => true]);

    $this->ledger->receive($this->shop->id, $product->defaultVariation, 10, 5.0, StockMovementType::INTAKE);
    CostLayer::query()->where('variation_id', $product->defaultVariation->id)->update([
        'batch_number' => 'BATCH-7',
        'expiry_date' => '2027-01-01',
    ]);

    $this->actingAs($this->owner)->get(route('stock-adjustments.create'))
        ->assertOk()
        ->assertSee('Batch BATCH-7, expires 2027-01-01, 10 left', false);
});
