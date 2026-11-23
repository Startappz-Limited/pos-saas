<?php

use App\Actions\CreateInventorySnapshot;
use App\Actions\Stock\TakeNightlySnapshots;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\CostLayer;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Stage 11c, step 4: a past day's stock and value are rebuilt exactly from the ledger. The clock runs in UTC here, as
 * production does: a stored time is compared with the UTC instant a day ends at.
 */

/** The instant of a local time at the shop (Nairobi), in UTC for the test clock. */
function nairobi(string $local): Carbon
{
    return Carbon::parse($local, 'Africa/Nairobi')->utc();
}

/** A completed sale line of $shop's variation, to consume and give back through the ledger. */
function rebuildSaleItem(Shop $shop, Product $product): SaleItem
{
    $sale = Sale::factory()->create(['shop_id' => $shop->id, 'status' => 'completed', 'customer_id' => null]);

    return SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'variation_id' => $product->defaultVariation->id,
        'quantity' => 1,
        'base_quantity' => 1,
        'unit_price' => 10,
        'line_total' => 10,
        'unit_cost' => 5,
        'total_cost' => 5,
        'status' => 'completed',
    ]);
}

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(nairobi('2026-11-01 08:00'));
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB', 'timezone' => 'Africa/Nairobi']);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'track_stock' => true]);
    $this->variation = $this->product->defaultVariation;
    $this->ledger->receive($this->shop->id, $this->variation, 10, 5.0, StockMovementType::INTAKE);
    $this->snapshots = app(CreateInventorySnapshot::class);
});

/** The snapshot of the variation as it stood at $at, rebuilt from the ledger, and the one taken then (on time). */
function rebuildOf(Shop $shop, ProductVariation $variation, Carbon $at, string $kind): InventorySnapshot
{
    app(CreateInventorySnapshot::class)->execute($shop->id, $at->copy()->subDay()->setTimezone('Africa/Nairobi')->toDateString(), null, $kind, InventorySnapshot::SOURCE_REBUILT, $at);

    return InventorySnapshot::query()->where('variation_id', $variation->id)->where('kind', $kind)->sole();
}

it('records the batches each change touched on its movement', function () {
    $item = rebuildSaleItem($this->shop, $this->product);
    $this->travelTo(nairobi('2026-11-01 09:00'));
    $this->ledger->sellItem($item, $this->variation);

    $intake = StockMovement::query()->where('movement_type', StockMovementType::INTAKE)->sole();
    $sale = StockMovement::query()->where('movement_type', StockMovementType::SALE)->sole();

    expect($intake->layers)->toHaveCount(1)
        ->and(Str::before((string) $intake->layers[0]['quantity'], '.'))->toBe('10')
        ->and((float) $sale->layers[0]['quantity'])->toBe(-1.0)
        ->and($sale->layers[0]['layer_id'])->toBe(CostLayer::query()->where('variation_id', $this->variation->id)->oldest('id')->value('id'));
});

it('rebuilds a day exactly after sales, a receipt, a void and a supplier return, as the on-time figure was', function () {
    // The end of 2 November (Nairobi): the on-time figure, taken before anything moves after it.
    $endOfDay = nairobi('2026-11-03 00:00');
    $this->travelTo($endOfDay->copy()->addMinutes(5));
    $this->snapshots->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_DAILY, InventorySnapshot::SOURCE_ON_TIME, $endOfDay);
    $onTime = InventorySnapshot::query()->where('kind', InventorySnapshot::KIND_DAILY)->sole();

    // Then the day's stock keeps moving: a sale, a receipt (a batch of its own, after midnight), a void of the sale and a
    // supplier return.
    $sold = rebuildSaleItem($this->shop, $this->product);
    $this->travelTo(nairobi('2026-11-03 09:00'));
    $this->ledger->sellItem($sold, $this->variation);
    $this->travelTo(nairobi('2026-11-03 10:00'));
    $this->ledger->receive($this->shop->id, $this->variation, 5, 7.0, StockMovementType::INTAKE);
    $this->travelTo(nairobi('2026-11-03 11:00'));
    $this->ledger->release($sold, StockMovementType::SALE_VOID);
    $this->travelTo(nairobi('2026-11-03 12:00'));
    $this->ledger->consume($this->shop->id, $this->variation, 2, StockMovementType::SUPPLIER_RETURN, rebuildSaleItem($this->shop, $this->product));

    $rebuilt = rebuildOf($this->shop, $this->variation, $endOfDay, InventorySnapshot::KIND_MANUAL);

    expect((string) $rebuilt->quantity_on_hand)->toBe((string) $onTime->quantity_on_hand)
        ->and((string) $rebuilt->quantity_on_hand)->toBe('10.000')
        ->and((float) $rebuilt->total_value)->toBe((float) $onTime->total_value)
        ->and((float) $rebuilt->total_value)->toBe(50.0)
        ->and((string) $rebuilt->unknown_cost_quantity)->toBe((string) $onTime->unknown_cost_quantity)
        ->and((string) $rebuilt->shortfall_quantity)->toBe('0.000');

    // The rebuilt day is not the current one: the stock has moved on to 13 and 75.
    expect($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('13.000');
});

it('rebuilds a sale made before midnight but voided after it, as it stood at midnight', function () {
    $this->travelTo(nairobi('2026-11-02 20:00'));
    $sold = rebuildSaleItem($this->shop, $this->product);
    $this->ledger->sellItem($sold, $this->variation);

    $endOfDay = nairobi('2026-11-03 00:00');
    $this->travelTo($endOfDay->copy()->addMinutes(5));
    $this->snapshots->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_DAILY, InventorySnapshot::SOURCE_ON_TIME, $endOfDay);
    $onTime = InventorySnapshot::query()->where('kind', InventorySnapshot::KIND_DAILY)->sole();

    $this->travelTo(nairobi('2026-11-03 09:00'));
    $this->ledger->release($sold, StockMovementType::SALE_VOID);

    $rebuilt = rebuildOf($this->shop, $this->variation, $endOfDay, InventorySnapshot::KIND_MANUAL);

    expect((string) $onTime->quantity_on_hand)->toBe('9.000')
        ->and((string) $rebuilt->quantity_on_hand)->toBe('9.000')
        ->and((float) $rebuilt->total_value)->toBe((float) $onTime->total_value)
        ->and((float) $rebuilt->total_value)->toBe(45.0);
});

it('leaves a batch received after midnight out of a day\'s value', function () {
    $endOfDay = nairobi('2026-11-03 00:00');
    $this->travelTo(nairobi('2026-11-03 10:00'));
    $this->ledger->receive($this->shop->id, $this->variation, 20, 100.0, StockMovementType::INTAKE);

    $rebuilt = rebuildOf($this->shop, $this->variation, $endOfDay, InventorySnapshot::KIND_MANUAL);

    expect((string) $rebuilt->quantity_on_hand)->toBe('10.000')
        ->and((float) $rebuilt->total_value)->toBe(50.0);
});

it('rebuilds a day around a quarantine release, which moves units between batches with no stock change', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 4, 9.0, StockMovementType::RETURN, ['quarantine_status' => 'unverified']);
    $held = CostLayer::query()->whereNotNull('quarantine_status')->sole();

    $endOfDay = nairobi('2026-11-03 00:00');
    $this->travelTo($endOfDay->copy()->addMinutes(5));
    $this->snapshots->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_DAILY, InventorySnapshot::SOURCE_ON_TIME, $endOfDay);
    $onTime = InventorySnapshot::query()->where('kind', InventorySnapshot::KIND_DAILY)->sole();

    $this->travelTo(nairobi('2026-11-03 09:00'));
    $this->ledger->addQuarantineRelease($held, '4.000', ['notes' => 'released']);

    $move = StockMovement::query()->where('movement_type', StockMovementType::QUARANTINE_RELEASE)->sole();
    expect((string) $move->quantity)->toBe('0.000')
        ->and($move->layers)->toHaveCount(2)
        ->and($move->layers[0]['role'])->toBe('quarantine_source')
        ->and((float) $move->layers[0]['quantity'])->toBe(-4.0);

    $rebuilt = rebuildOf($this->shop, $this->variation, $endOfDay, InventorySnapshot::KIND_MANUAL);

    expect((string) $rebuilt->quantity_on_hand)->toBe((string) $onTime->quantity_on_hand)
        ->and((float) $rebuilt->total_value)->toBe((float) $onTime->total_value)
        ->and((float) $rebuilt->total_value)->toBe(86.0);
});

it('takes the current figures, marked late, when the ledger cannot rebuild a day exactly', function () {
    $endOfDay = nairobi('2026-11-03 00:00');
    $this->travelTo(nairobi('2026-11-03 09:00'));
    // A change the ledger never recorded: the batch loses two units with no movement.
    DB::table('cost_layers')->where('variation_id', $this->variation->id)->update(['remaining_quantity' => 8]);

    $this->snapshots->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_MANUAL, InventorySnapshot::SOURCE_REBUILT, $endOfDay);
    $row = InventorySnapshot::query()->where('kind', InventorySnapshot::KIND_MANUAL)->sole();

    // The current stock figure stands in (the batch's own figure is the one the ledger can't reconcile).
    expect($row->source)->toBe(InventorySnapshot::SOURCE_LATE)
        ->and((string) $row->quantity_on_hand)->toBe('10.000');
});

it('takes a missed day from the ledger, and the day that ended on time, when the scheduler skipped two midnights', function () {
    // The last run was at 1 November's midnight, so the days since (1 and 2 November) are due.
    $this->travelTo(nairobi('2026-11-01 00:10'));
    $this->shop->forceFill(['snapshot_taken_at' => nairobi('2026-11-01 00:10')])->saveQuietly();

    // Sales on 1 and 2 November; the scheduler is down until 3 November.
    $this->travelTo(nairobi('2026-11-01 15:00'));
    $this->ledger->sellItem(rebuildSaleItem($this->shop, $this->product), $this->variation);
    $this->travelTo(nairobi('2026-11-02 15:00'));
    $this->ledger->sellItem(rebuildSaleItem($this->shop, $this->product), $this->variation);
    $this->ledger->sellItem(rebuildSaleItem($this->shop, $this->product), $this->variation);

    $this->travelTo(nairobi('2026-11-03 00:10'));
    app(TakeNightlySnapshots::class)->execute();

    $first = InventorySnapshot::query()->whereDate('snapshot_date', '2026-11-01')->sole();
    $second = InventorySnapshot::query()->whereDate('snapshot_date', '2026-11-02')->sole();

    expect($first->source)->toBe(InventorySnapshot::SOURCE_REBUILT)
        ->and((string) $first->quantity_on_hand)->toBe('9.000')
        ->and($second->source)->toBe(InventorySnapshot::SOURCE_ON_TIME)
        ->and((string) $second->quantity_on_hand)->toBe('7.000');
});

it('marks a missed day before the ledger began late, and rebuilds the day after it', function () {
    $this->travelTo(nairobi('2026-11-01 00:10'));
    $this->shop->forceFill(['snapshot_taken_at' => nairobi('2026-11-01 00:10')])->saveQuietly();
    $this->travelTo(nairobi('2026-11-01 15:00'));
    $this->ledger->sellItem(rebuildSaleItem($this->shop, $this->product), $this->variation);

    // The ledger began on 2 November, so 1 November's end (the start of 2 November) is before it.
    $business = Business::query()->find(testBusiness()->id);
    $business->forceFill(['ledger_started_at' => nairobi('2026-11-02 12:00')])->saveQuietly();

    $this->travelTo(nairobi('2026-11-03 00:10'));
    app(TakeNightlySnapshots::class)->execute();

    expect(InventorySnapshot::query()->whereDate('snapshot_date', '2026-11-01')->sole()->source)->toBe(InventorySnapshot::SOURCE_LATE)
        ->and(InventorySnapshot::query()->whereDate('snapshot_date', '2026-11-02')->sole()->source)->toBe(InventorySnapshot::SOURCE_ON_TIME);
});

it('records the ledger start from the first batch, dry run first, and keeps a start already recorded', function () {
    $business = Business::query()->find(testBusiness()->id);
    expect($business->ledger_started_at)->toBeNull();

    $this->artisan('stock:ledger-start')->assertSuccessful();
    expect(Business::query()->find($business->id)->ledger_started_at)->toBeNull();

    $this->artisan('stock:ledger-start', ['--apply' => true])->assertSuccessful();
    $recorded = Business::query()->find($business->id)->getRawOriginal('ledger_started_at');
    expect($recorded)->not->toBeNull();

    $this->travelTo(nairobi('2026-11-09 08:00'));
    $this->artisan('stock:ledger-start', ['--apply' => true])->assertSuccessful();
    expect(Business::query()->find($business->id)->getRawOriginal('ledger_started_at'))->toBe($recorded);
});
