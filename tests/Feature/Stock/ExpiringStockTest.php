<?php

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\StockMovementType;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\TwoBusinesses;

/*
 * Expiring stock and "Write off expired" (stage 11, step 5): a per-shop list of expired batches
 * and those expiring soon; the write-off files an "expired" adjustment that takes each expired
 * batch out of that batch and, like every loss, waits for a second person.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-03 10:00:00', 'Africa/Nairobi'));
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->member = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role === 'owner' ? ownerRole() : $role);
        $user->shops()->attach($this->shop);

        return $user;
    };
    $this->owner = ($this->member)('owner');
    $this->clerk = ($this->member)('inventory-clerk');
    $this->manager = ($this->member)('manager');
    $this->batch = function (string $name, ?string $expiry, int|string $quantity = 10, ?Shop $shop = null, ?Product $product = null): CostLayer {
        $shop ??= $this->shop;
        $product ??= Product::factory()->create(['shop_id' => $shop->id, 'stock_quantity' => 0, 'name' => $name]);
        $this->ledger->receive($shop->id, $product->defaultVariation, $quantity, 5.0, StockMovementType::INTAKE, ['batch_number' => $name, 'expiry_date' => $expiry]);

        return CostLayer::query()->where('shop_id', $shop->id)->where('variation_id', $product->defaultVariation->id)->latest('id')->firstOrFail();
    };
});

it('lists a shop\'s expired batches and those expiring soon, and nothing else', function () {
    ($this->batch)('GONE', '2026-10-31');
    ($this->batch)('SOON', '2026-11-20');
    ($this->batch)('LATER', '2027-03-01');
    ($this->batch)('NOEXPIRY', null);
    $otherShop = Shop::factory()->create();
    ($this->batch)('ELSEWHERE', '2026-10-01', 10, $otherShop);

    $page = $this->actingAs($this->owner)->get(route('stock.expiring', ['shop_id' => $this->shop->id]))->assertOk();

    $page->assertSeeInOrder(['GONE', 'SOON'])->assertDontSee('LATER')->assertDontSee('NOEXPIRY')->assertDontSee('ELSEWHERE');
});

it('widens or narrows the window by the days asked for', function () {
    ($this->batch)('IN-TEN', '2026-11-13');
    ($this->batch)('IN-FORTY', '2026-12-13');

    $this->actingAs($this->owner)->get(route('stock.expiring', ['shop_id' => $this->shop->id, 'days' => 30]))
        ->assertSee('IN-TEN')->assertDontSee('IN-FORTY');
    $this->actingAs($this->owner)->get(route('stock.expiring', ['shop_id' => $this->shop->id, 'days' => 60]))
        ->assertSee('IN-TEN')->assertSee('IN-FORTY');
});

it('shows a batch\'s value only to someone who may see costs, and "Cost unknown" for a batch without one', function () {
    $known = ($this->batch)('KNOWN', '2026-10-30', 10);
    $unknown = ($this->batch)('UNKNOWN', '2026-10-30', 4);
    CostLayer::query()->whereKey($unknown->id)->update(['unit_cost' => null]);

    // The clerk template holds no cost permission (stage 10, step 1f).
    $this->actingAs($this->clerk)->get(route('stock.expiring', ['shop_id' => $this->shop->id]))
        ->assertOk()->assertSee('KNOWN')->assertDontSee('Value')->assertDontSee('Cost unknown');

    $this->actingAs($this->owner)->get(route('stock.expiring', ['shop_id' => $this->shop->id]))
        ->assertOk()->assertSee('Value')->assertSee('50.00')->assertSee('Cost unknown');
});

it('is closed to someone who may not see stock', function () {
    $stranger = User::factory()->create();
    $stranger->shops()->attach($this->shop);

    $this->actingAs($stranger)->get(route('stock.expiring'))->assertForbidden();
});

it('files an expired adjustment taking each expired batch from that batch, for a second person to approve', function () {
    $expiredA = ($this->batch)('A-EXP', '2026-10-31', 6);
    $expiredB = ($this->batch)('B-EXP', '2026-10-20', 3);
    $fresh = ($this->batch)('FRESH', '2027-05-01', 8);
    // A product holding an expired batch and a fresh one: only the expired one goes.
    $mixed = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'name' => 'MIXED']);
    $mixedOld = ($this->batch)('M-OLD', '2026-09-01', 2, null, $mixed);
    $mixedNew = ($this->batch)('M-NEW', '2027-09-01', 7, null, $mixed);

    $this->actingAs($this->clerk)->post(route('stock.expiring.write-off'), ['shop_id' => $this->shop->id])->assertRedirect();

    $adjustment = StockAdjustment::query()->latest('id')->firstOrFail();
    expect($adjustment->reason)->toBe(AdjustmentReason::EXPIRED)
        ->and($adjustment->type)->toBe(AdjustmentType::DECREASE)
        ->and($adjustment->status)->toBe('pending')
        ->and($adjustment->items)->toHaveCount(3)
        ->and($adjustment->items->pluck('cost_layer_id')->sort()->values()->all())->toBe(collect([$expiredA->id, $expiredB->id, $mixedOld->id])->sort()->values()->all());

    // Nothing moved until a second person approves.
    expect((float) CostLayer::query()->find($expiredA->id)->remaining_quantity)->toBe(6.0);

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    expect((float) CostLayer::query()->find($expiredA->id)->remaining_quantity)->toBe(0.0)
        ->and((float) CostLayer::query()->find($expiredB->id)->remaining_quantity)->toBe(0.0)
        ->and((float) CostLayer::query()->find($mixedOld->id)->remaining_quantity)->toBe(0.0)
        ->and((float) CostLayer::query()->find($mixedNew->id)->remaining_quantity)->toBe(7.0)
        ->and((float) CostLayer::query()->find($fresh->id)->remaining_quantity)->toBe(8.0)
        // At their own cost: the three batches were received at 5.
        ->and((float) DB::table('stock_movements')->where('movement_type', StockMovementType::ADJUSTMENT_REMOVE->value)->value('unit_cost'))->toBe(5.0);
});

it('writes off nothing when the shop holds no expired stock, and says so', function () {
    ($this->batch)('FRESH', '2027-05-01');

    $this->actingAs($this->manager)->post(route('stock.expiring.write-off'), ['shop_id' => $this->shop->id])
        ->assertRedirect()->assertSessionHas('error');

    expect(StockAdjustment::query()->count())->toBe(0);
});

it('leaves a fraction of a unit for adjustments that take any unit, and says so on the page', function () {
    $kg = ($this->batch)('KG', '2026-10-31', '2.500');

    $this->actingAs($this->manager)->post(route('stock.expiring.write-off'), ['shop_id' => $this->shop->id]);
    $adjustment = StockAdjustment::query()->latest('id')->firstOrFail();
    expect((float) $adjustment->items->first()->quantity_change)->toBe(-2.0);

    $this->actingAs($this->owner)->get(route('stock.expiring', ['shop_id' => $this->shop->id]))
        ->assertSee('hold a fraction of a unit');
});

it('refuses a write-off by someone who may not file adjustments', function () {
    ($this->batch)('GONE', '2026-10-31');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('stock.view');
    $viewer->shops()->attach($this->shop);

    $this->actingAs($viewer)->post(route('stock.expiring.write-off'), ['shop_id' => $this->shop->id])->assertForbidden();
});

it('refuses another business\'s shop, and shows another business nothing of this one', function () {
    $two = TwoBusinesses::create();
    ($this->batch)('MINE', '2026-10-31');

    $this->actingAs($two->a->owner)->post(route('stock.expiring.write-off'), ['shop_id' => $this->shop->id])
        ->assertSessionHasErrors('shop_id');

    $this->actingAs($two->a->owner)->get(route('stock.expiring', ['shop_id' => $this->shop->id]))
        ->assertOk()->assertDontSee('MINE');

    expect(StockAdjustment::query()->withoutGlobalScopes()->count())->toBe(0);
});
