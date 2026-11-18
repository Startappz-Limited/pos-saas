<?php

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Rules\QuantityForUnit;
use App\Services\Stock\StockLedger;
use App\Support\Stock\BaseQuantity;
use App\Support\Stock\SaleLineUnit;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * Review follow-up (stage 11): a batch-named write-off takes only from its batch, and refuses a
 * shortfall rather than spilling into other batches; a unit quantity that would move no whole base
 * unit is refused; a quantity in scientific or padded notation is a validation error, not a crash.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Africa/Nairobi'));
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->clerk = assignToNewShops(User::factory()->create());
    $this->clerk->assignRole(sharedRole('inventory-clerk'));
    $this->manager = assignToNewShops(User::factory()->create());
    $this->manager->assignRole(sharedRole('manager'));
    $this->ledger = app(StockLedger::class);

    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'piece']);
    $this->variation = $this->product->defaultVariation;
    $this->variation->update(['track_batches' => true]);
    $this->variation->baseUnit()->update(['precision' => 0]);

    // Two batches received on different days: "OLD" (expired) and "NEW" (healthy).
    $this->batch = function (string $number, string $receivedAt, ?string $expiry, int $quantity) {
        $this->ledger->receive($this->shop->id, $this->variation, $quantity, 5.0, StockMovementType::INTAKE, ['received_at' => $receivedAt]);
        $layer = CostLayer::query()->where('variation_id', $this->variation->id)->latest('id')->firstOrFail();
        $layer->update(['batch_number' => $number, 'expiry_date' => $expiry]);

        return $layer->fresh();
    };
    $this->old = ($this->batch)('OLD', now()->subDays(30)->toDateTimeString(), '2026-11-01', 2);
    $this->new = ($this->batch)('NEW', now()->subDays(10)->toDateTimeString(), '2027-06-01', 10);
    // A third healthy batch, so the stock as a whole could cover a write-off that spills over.
    ($this->batch)('MID', now()->subDays(20)->toDateTimeString(), '2027-01-01', 10);

    $this->writeOff = fn (User $user, array $line): TestResponse => $this->actingAs($user)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => 'damaged', 'items' => [['product_id' => $this->product->id, ...$line]],
    ]);
});

it('takes a batch-named write-off only from that batch, and refuses a shortfall instead of spilling over', function () {
    // A write-off of all 10 of NEW is pending while NEW still holds 10.
    ($this->writeOff)($this->clerk, ['quantity_change' => -10, 'cost_layer_id' => $this->new->id])->assertSessionHasNoErrors();
    $adjustment = StockAdjustment::query()->latest('id')->firstOrFail();

    // Before it is approved, a sale takes 8 of NEW's 10.
    $this->ledger->consume($this->shop->id, $this->variation, 8, StockMovementType::SALE, $this->variation, [], $this->new->id);

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    // Refused: NEW has 2 left, and OLD and MID (the other batches, 14 in all) are not touched.
    expect($adjustment->fresh()->status)->not->toBe('completed')
        ->and(CostLayer::query()->where('batch_number', 'NEW')->value('remaining_quantity'))->toBe('2.000')
        ->and(CostLayer::query()->where('batch_number', 'OLD')->value('remaining_quantity'))->toBe('2.000')
        ->and(CostLayer::query()->where('batch_number', 'MID')->value('remaining_quantity'))->toBe('10.000')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('14.000');
});

it('lets a second write-off of a batch fail once the first has used it, leaving other batches alone', function () {
    ($this->writeOff)($this->clerk, ['quantity_change' => -2, 'cost_layer_id' => $this->old->id])->assertSessionHasNoErrors();
    ($this->writeOff)($this->clerk, ['quantity_change' => -2, 'cost_layer_id' => $this->old->id])->assertSessionHasNoErrors();
    [$first, $second] = StockAdjustment::query()->orderBy('id')->get()->all();

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $first))->assertRedirect();
    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $second))->assertRedirect();

    expect($first->fresh()->status)->toBe('completed')
        ->and($second->fresh()->status)->not->toBe('completed')
        ->and(CostLayer::query()->where('batch_number', 'OLD')->value('remaining_quantity'))->toBe('0.000')
        ->and(CostLayer::query()->where('batch_number', 'NEW')->value('remaining_quantity'))->toBe('10.000');
});

it('refuses a named batch that is too small for the write-off, or belongs to another product', function () {
    ($this->writeOff)($this->clerk, ['quantity_change' => -5, 'cost_layer_id' => $this->old->id])
        ->assertSessionHasErrors('items.0.cost_layer_id');

    $other = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'piece']);
    $other->defaultVariation->update(['track_batches' => true]);
    $this->ledger->receive($this->shop->id, $other->defaultVariation, 4, 5.0, StockMovementType::INTAKE);
    $foreign = CostLayer::query()->where('variation_id', $other->defaultVariation->id)->firstOrFail();

    ($this->writeOff)($this->clerk, ['quantity_change' => -1, 'cost_layer_id' => $foreign->id])
        ->assertSessionHasErrors('items.0.cost_layer_id');
    expect(StockAdjustment::query()->count())->toBe(0);
});

it('refuses a unit quantity that would move no whole base unit, and moves one that does', function () {
    $sachet = ProductUnit::factory()->create(['variation_id' => $this->variation->id, 'name' => 'Milli', 'factor' => '0.001', 'precision' => 3]);

    ($this->writeOff)($this->clerk, ['quantity_change' => -0.001, 'unit_id' => $sachet->uuid])
        ->assertSessionHasErrors('items.0.quantity_change');

    expect(fn () => SaleLineUnit::resolve($this->variation, ['quantity' => '0.001', 'unit_id' => $sachet->uuid]))
        ->toThrow(BusinessRuleException::class);
    expect(SaleLineUnit::resolve($this->variation, ['quantity' => '1000', 'unit_id' => $sachet->uuid])['base_quantity'])->toBe('1.000');
});

it('converts exactly, or refuses, at the ledger\'s scale', function () {
    expect(BaseQuantity::of('0.001', '0.001'))->toBeNull()
        ->and(BaseQuantity::of('0.5', '0.001'))->toBeNull()
        ->and(BaseQuantity::of('1', '0.001'))->toBe('0.001')
        ->and(BaseQuantity::of('2.5', '24.000'))->toBe('60.000')
        ->and(BaseQuantity::of('-1', '24.000'))->toBe('-24.000')
        ->and(BaseQuantity::of('0', '24.000'))->toBeNull();
});

it('refuses scientific and padded numeric notation as a validation message, without a crash', function () {
    $messages = function (string $value): array {
        $collected = [];
        (new QuantityForUnit(3))->validate('quantity', $value, function (string $message) use (&$collected): void {
            $collected[] = $message;
        });

        return $collected;
    };

    expect($messages('1e0'))->toBe(['Enter a number.'])
        ->and($messages(' 1'))->toBe(['Enter a number.'])
        ->and($messages('1 '))->toBe(['Enter a number.'])
        ->and($messages('1.5'))->toBe([])
        ->and($messages('+2'))->toBe([]);

    ($this->writeOff)($this->clerk, ['quantity_change' => '1e0'])->assertSessionHasErrors('items.0.quantity_change');
    ($this->writeOff)($this->clerk, ['quantity_change' => ' 2'])->assertSessionHasErrors('items.0.quantity_change');
    expect(StockAdjustment::query()->count())->toBe(0);
});

it('compares a batch in base units: a crate of 24 needs 24 bottles, not one', function () {
    $crate = ProductUnit::factory()->create(['variation_id' => $this->variation->id, 'name' => 'Crate of 24', 'factor' => '24.000', 'precision' => 0]);

    // NEW holds 10 bottles: one crate is 24 bottles, so the line is refused at submission.
    ($this->writeOff)($this->clerk, ['quantity_change' => -1, 'unit_id' => $crate->uuid, 'cost_layer_id' => $this->new->id])
        ->assertSessionHasErrors('items.0.cost_layer_id');
    expect(StockAdjustment::query()->count())->toBe(0);

    // A batch of 24 bottles covers it, and approval takes exactly those 24.
    ($this->batch)('BIG', now()->subDays(5)->toDateTimeString(), '2028-01-01', 24);
    $big = CostLayer::query()->where('batch_number', 'BIG')->firstOrFail();
    ($this->writeOff)($this->clerk, ['quantity_change' => -1, 'unit_id' => $crate->uuid, 'cost_layer_id' => $big->id])->assertSessionHasNoErrors();

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', StockAdjustment::query()->latest('id')->firstOrFail()))->assertRedirect();
    expect($big->fresh()->remaining_quantity)->toBe('0.000');
});

it('compares a batch in base units for a factor below one: half-bottle units take half a bottle each', function () {
    $half = ProductUnit::factory()->create(['variation_id' => $this->variation->id, 'name' => 'Half bottle', 'factor' => '0.500', 'precision' => 0]);

    // OLD holds 2 bottles: four half-bottles are 2 bottles, which it covers; five are 2.5, which it does not.
    ($this->writeOff)($this->clerk, ['quantity_change' => -4, 'unit_id' => $half->uuid, 'cost_layer_id' => $this->old->id])->assertSessionHasNoErrors();
    ($this->writeOff)($this->clerk, ['quantity_change' => -5, 'unit_id' => $half->uuid, 'cost_layer_id' => $this->old->id])
        ->assertSessionHasErrors('items.0.cost_layer_id');

    expect(StockAdjustment::query()->count())->toBe(1);
});
