<?php

use App\Actions\CreateInventorySnapshot;
use App\Actions\Stock\PruneDailySnapshots;
use App\Actions\Stock\TakeNightlySnapshots;
use App\Enums\StockMovementType;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Stage 11c, step 3: inventory snapshots. One per shop, variation and day, of a kind (daily, month_end, manual).
 * The nightly run takes the day that ended once a shop's own midnight has passed; retention deletes old dailies
 * and keeps month-end and manual ones.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-03 10:00:00', 'Africa/Nairobi'));
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB', 'timezone' => 'Africa/Nairobi']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());

    // Two variations: a simple product's default one, and a product with a variation of its own.
    $this->soap = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'track_stock' => true, 'selling_price' => 50]);
    $this->ledger->receive($this->shop->id, $this->soap->defaultVariation, 10, 5.0, StockMovementType::INTAKE);
    $this->ledger->receive($this->shop->id, $this->soap->defaultVariation, 4, null, StockMovementType::INTAKE); // a batch of unknown cost

    $this->shirt = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => true, 'track_stock' => true]);
    $this->large = $this->shirt->variations()->create(['name' => 'Large', 'sku' => 'SH-L', 'selling_price' => 300, 'cost_price' => 120, 'stock_quantity' => 0, 'is_default' => false]);
    $this->ledger->receive($this->shop->id, $this->large, 3, 120.0, StockMovementType::INTAKE);
});

it('values a day\'s stock: FIFO cost, unknown cost apart, and retail at the base unit\'s price', function () {
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_DAILY, InventorySnapshot::SOURCE_ON_TIME);

    $soap = InventorySnapshot::query()->where('variation_id', $this->soap->defaultVariation->id)->sole();

    expect((string) $soap->quantity_on_hand)->toBe('14.000')
        ->and((float) $soap->total_value)->toBe(50.0)
        ->and((string) $soap->unknown_cost_quantity)->toBe('4.000')
        ->and((string) $soap->shortfall_quantity)->toBe('0.000')
        ->and((float) $soap->retail_value)->toBe(700.0)
        ->and($soap->kind)->toBe(InventorySnapshot::KIND_DAILY)
        ->and($soap->source)->toBe(InventorySnapshot::SOURCE_ON_TIME)
        ->and($soap->snapshot_date->toDateString())->toBe('2026-11-02');
});

it('names every snapshot\'s variation, so a shirt\'s variation has its own row', function () {
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02');

    $rows = InventorySnapshot::query()->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn (InventorySnapshot $s): bool => $s->variation_id !== null))->toBeTrue()
        ->and($rows->firstWhere('variation_id', $this->large->id)->quantity_on_hand)->toBe('3.000');
});

it('keeps one row per variation when a day is taken twice', function () {
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02');
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02');

    expect(InventorySnapshot::query()->whereDate('snapshot_date', '2026-11-02')->count())->toBe(2);
});

it('carries a shortfall below zero at no value', function () {
    // A figure below zero (a sale that took more than was held) is recorded as a shortfall, worth nothing.
    DB::table('shop_variation')->where('shop_id', $this->shop->id)->where('variation_id', $this->large->id)->update(['stock_quantity' => -2]);

    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02');
    $row = InventorySnapshot::query()->where('variation_id', $this->large->id)->sole();

    expect((string) $row->quantity_on_hand)->toBe('-2.000')
        ->and((string) $row->shortfall_quantity)->toBe('2.000')
        ->and((float) $row->retail_value)->toBe(0.0);
});

it('takes the nightly snapshot of the day that ended, once the shop\'s own midnight has passed', function () {
    // 00:10 in Nairobi on 3 November: the 2nd has ended.
    $this->travelTo(Carbon::parse('2026-11-03 00:10:00', 'Africa/Nairobi'));
    app(TakeNightlySnapshots::class)->execute();

    expect(InventorySnapshot::query()->pluck('snapshot_date')->map->toDateString()->unique()->all())->toBe(['2026-11-02'])
        ->and($this->shop->fresh()->snapshot_taken_at)->not->toBeNull();

    // Another run the same day takes nothing.
    $this->travelTo(Carbon::parse('2026-11-03 00:25:00', 'Africa/Nairobi'));
    expect(app(TakeNightlySnapshots::class)->execute())->toBe(0);
});

it('snapshots a shop in another timezone at its own midnight', function () {
    $la = Shop::factory()->create(['code' => 'LAX', 'timezone' => 'America/Los_Angeles']);
    $this->ledger->receive($la->id, $this->soap->defaultVariation, 2, 5.0, StockMovementType::INTAKE);
    // Snapshotted at its own midnight on 2 November (Los Angeles), so the next one is due at its midnight on the 3rd.
    $la->forceFill(['snapshot_taken_at' => Carbon::parse('2026-11-02 00:30:00', 'America/Los_Angeles')->utc()])->saveQuietly();

    // 00:10 Nairobi on 3 November is 13:10 on the 2nd in Los Angeles: its day hasn't ended.
    $this->travelTo(Carbon::parse('2026-11-03 00:10:00', 'Africa/Nairobi'));
    app(TakeNightlySnapshots::class)->execute();
    expect(InventorySnapshot::query()->where('shop_id', $la->id)->exists())->toBeFalse();

    // 01:00 in Los Angeles on the 3rd: the 2nd has ended there.
    $this->travelTo(Carbon::parse('2026-11-03 01:00:00', 'America/Los_Angeles'));
    app(TakeNightlySnapshots::class)->execute();
    expect(InventorySnapshot::query()->where('shop_id', $la->id)->first()->snapshot_date->toDateString())->toBe('2026-11-02');
});

it('takes the last day of a month as a month-end snapshot', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:10:00', 'Africa/Nairobi'));
    app(TakeNightlySnapshots::class)->execute();

    expect(InventorySnapshot::query()->whereDate('snapshot_date', '2026-11-30')->pluck('kind')->unique()->all())->toBe([InventorySnapshot::KIND_MONTH_END]);
});

it('deletes a daily snapshot older than 13 months, and keeps month-end and manual ones', function () {
    $old = Carbon::parse('2025-09-01');
    app(CreateInventorySnapshot::class)->execute($this->shop->id, $old->toDateString(), null, InventorySnapshot::KIND_DAILY);
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2025-09-30', null, InventorySnapshot::KIND_MONTH_END);
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2025-09-15', null, InventorySnapshot::KIND_MANUAL);
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-10-01', null, InventorySnapshot::KIND_DAILY);

    // 3 November 2026 less 13 months is 3 October 2025: anything dated before that goes.
    app(PruneDailySnapshots::class)->execute(Carbon::parse('2026-11-03'));

    // Two variations per day: each snapshot is kept or pruned for both.
    $kept = InventorySnapshot::query()->get()->map(fn (InventorySnapshot $s) => $s->snapshot_date->toDateString().':'.$s->kind)->unique()->sort()->values()->all();
    expect($kept)->toBe(['2025-09-15:manual', '2025-09-30:month_end', '2026-10-01:daily'])
        ->and(InventorySnapshot::query()->whereDate('snapshot_date', '2025-09-01')->exists())->toBeFalse();
});

it('takes a manual snapshot from the screen, and refuses a shop the user cannot reach', function () {
    $this->actingAs($this->owner)->post(route('inventory-snapshots.generate'), ['shop_id' => $this->shop->id])->assertRedirect();

    expect(InventorySnapshot::query()->where('kind', InventorySnapshot::KIND_MANUAL)->count())->toBe(2);

    $other = Shop::factory()->create(['code' => 'WES']);
    $clerk = User::factory()->create();
    $clerk->assignRole(sharedRole('manager'));
    $this->actingAs($clerk)->post(route('inventory-snapshots.generate'), ['shop_id' => $other->id])->assertNotFound();
});

it('reports one snapshot per variation for a day, preferring the nightly figure over a manual re-take', function () {
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_DAILY);
    app(CreateInventorySnapshot::class)->execute($this->shop->id, '2026-11-02', null, InventorySnapshot::KIND_MANUAL);

    $this->actingAs($this->owner)->get(route('inventory-snapshots.valuationReport', ['date' => '2026-11-02']))
        ->assertOk()
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 1
            && $rows->first()['variations'] === 2
            && $rows->first()['total_value'] === 50.0 + 360.0);
});
