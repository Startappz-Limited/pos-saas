<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * Adjustment lines in any unit (stage 11, step 4): a line names its unit, its quantity is entered
 * in that unit and moves its base quantity (crates of 24 bottles move 24 each); a fraction is taken
 * only where the unit allows decimals.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Africa/Nairobi'));
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->clerk = assignToNewShops(User::factory()->create());
    $this->clerk->assignRole(sharedRole('inventory-clerk'));
    $this->manager = assignToNewShops(User::factory()->create());
    $this->manager->assignRole(sharedRole('manager'));
    $this->bottles = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'piece']);
    app(StockLedger::class)->receive($this->shop->id, $this->bottles->defaultVariation, '100.000', 5.0, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $this->crate = ProductUnit::factory()->create(['variation_id' => $this->bottles->defaultVariation->id, 'name' => 'Crate of 24', 'factor' => '24.000', 'precision' => 0]);
    $this->quantity = fn (): string => app(StockLedger::class)->exactQuantity($this->shop->id, $this->bottles->defaultVariation->id);
    $this->adjust = function (User $user, array $line): TestResponse {
        return $this->actingAs($user)->post(route('stock-adjustments.store'), [
            'shop_id' => $this->shop->id, 'reason' => 'damaged', 'items' => [['product_id' => $this->bottles->id, ...$line]],
        ]);
    };
});

it('moves a crate line by 24 base units per crate, and keeps the figure as entered', function () {
    ($this->adjust)($this->clerk, ['quantity_change' => -2, 'unit_id' => $this->crate->uuid, 'cost_layer_id' => null])->assertSessionHasNoErrors();

    $item = StockAdjustment::query()->latest('id')->firstOrFail()->items->first();
    expect((float) $item->quantity_change)->toBe(-48.0)
        ->and((float) $item->unit_quantity)->toBe(-2.0)
        ->and($item->unit_id)->toBe($this->crate->id);

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $item->stockAdjustment))->assertRedirect();
    expect(($this->quantity)())->toBe('52.000');
});

it('takes a fraction of a unit that allows decimals', function () {
    $kg = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'kg']);
    app(StockLedger::class)->receive($this->shop->id, $kg->defaultVariation, '10.000', 5.0, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $kg->defaultVariation->baseUnit()->update(['precision' => 3]);

    $this->actingAs($this->clerk)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => 'damaged', 'items' => [['product_id' => $kg->id, 'quantity_change' => -0.25]],
    ]);
    $item = StockAdjustment::query()->latest('id')->firstOrFail()->items->first();
    expect((float) $item->quantity_change)->toBe(-0.25)
        ->and(app(StockLedger::class)->exactQuantity($this->shop->id, $kg->defaultVariation->id))->toBe('10.000');
});

it('refuses a fraction of a whole unit, in base units or in a unit', function () {
    ($this->adjust)($this->clerk, ['quantity_change' => -1.5])->assertSessionHasErrors('items.0.quantity_change');
    ($this->adjust)($this->clerk, ['quantity_change' => -1.5, 'unit_id' => $this->crate->uuid])->assertSessionHasErrors('items.0.quantity_change');
    expect(StockAdjustment::query()->count())->toBe(0);
});

it('refuses a unit that is not one of the product\'s', function () {
    $other = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $foreign = ProductUnit::factory()->create(['variation_id' => $other->defaultVariation->id, 'name' => 'Box', 'factor' => '6', 'precision' => 0]);

    ($this->adjust)($this->clerk, ['quantity_change' => -1, 'unit_id' => $foreign->uuid])->assertSessionHasErrors('items.0.unit_id');
    expect(StockAdjustment::query()->count())->toBe(0);
});

it('offers a product\'s units on the adjustment form', function () {
    $this->actingAs($this->clerk)->get(route('stock-adjustments.create'))->assertOk()->assertSee('Crate of 24');
});
