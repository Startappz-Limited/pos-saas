<?php

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\TwoBusinesses;

/*
 * Stock adjustments, fixed (stage 10, step 1g): a line moves the variation it
 * names by a signed change agreeing with the reason; its creator applies it on
 * saving unless it needs a second person (loss, theft, damage, expiry, an
 * unknown cost, or a cost value at or above the business's threshold), and the
 * creator never approves their own; approving applies the stock in one step;
 * numbers are a gapless series per shop.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-03 10:00:00'));
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => null, 'stock_quantity' => 0]);
    $this->default = $this->product->defaultVariation;
    $this->member = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role === 'owner' ? ownerRole() : $role);
        $user->shops()->attach($this->shop);

        return $user;
    };
    $this->clerk = ($this->member)('inventory-clerk');
    $this->manager = ($this->member)('manager');
    $this->stock = fn (ProductVariation $variation, int $quantity, ?float $cost, string $at = '-1 day') => $this->ledger->receive(
        $this->shop->id, $variation, $quantity, $cost, StockMovementType::INTAKE, ['received_at' => now()->modify($at)],
    );
    $this->adjust = function (User $user, string $reason, array $lines) {
        $this->actingAs($user)->post(route('stock-adjustments.store'), [
            'shop_id' => $this->shop->id,
            'reason' => $reason,
            'items' => array_map(fn (array $line): array => ['product_id' => $line[0]->product_id, 'variation_id' => $line[0]->is_default ? null : $line[0]->id, 'quantity_change' => $line[1]], $lines),
        ]);

        return StockAdjustment::query()->latest('id')->first();
    };
    $this->quantity = fn (ProductVariation $variation): int => $this->ledger->quantity($this->shop->id, $variation->id);
});

function shirt(): array
{
    $product = Product::factory()->create(['shop_id' => test()->shop->id, 'has_variations' => true, 'cost_price' => null, 'stock_quantity' => 0]);

    return [
        $product,
        ProductVariation::factory()->create(['product_id' => $product->id, 'name' => 'Red', 'is_default' => false, 'cost_price' => null, 'stock_quantity' => 0]),
        ProductVariation::factory()->create(['product_id' => $product->id, 'name' => 'Blue', 'is_default' => false, 'cost_price' => null, 'stock_quantity' => 0]),
    ];
}

it('adjusts the variation it names, not the default one', function () {
    [$product, $red] = shirt();
    ($this->stock)($red, 5, 10);

    $adjustment = ($this->adjust)($this->manager, 'found', [[$red, 2]]);

    expect($adjustment->status)->toBe('completed')
        ->and($adjustment->items->first()->variation_id)->toBe($red->id)
        ->and(($this->quantity)($red))->toBe(7)
        ->and(($this->quantity)($product->defaultVariation()->first()))->toBe(0);
});

it('requires a variation for a product with variations', function () {
    [$product] = shirt();

    $this->actingAs($this->clerk)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => 'found', 'items' => [['product_id' => $product->id, 'quantity_change' => 1]],
    ])->assertSessionHasErrors('items.0.variation_id');
});

it('writes the before and after figures when it is approved, not when it is drafted', function () {
    ($this->stock)($this->default, 5, 10);
    $adjustment = ($this->adjust)($this->clerk, 'damaged', [[$this->default, -2]]);
    expect($adjustment->status)->toBe('pending')
        ->and($adjustment->items->first()->quantity_before)->toBeNull();

    ($this->stock)($this->default, 3, 10);
    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    $line = $adjustment->items()->first();
    expect([$line->quantity_before, $line->quantity_after])->toBe([8, 6])
        ->and(($this->quantity)($this->default))->toBe(6);
});

it('refuses a change against its reason\'s direction', function (string $reason, int $change) {
    ($this->stock)($this->default, 5, 10);

    $this->actingAs($this->clerk)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => $reason, 'items' => [['product_id' => $this->product->id, 'quantity_change' => $change]],
    ])->assertSessionHasErrors('items.0.quantity_change');

    expect(StockAdjustment::count())->toBe(0);
})->with([
    'an increase for lost stock' => ['lost', 2],
    'a decrease for found stock' => ['found', -2],
    'no change' => ['recount', 0],
]);

it('raises one line and lowers another in one recount, which waits because it lowers stock', function () {
    [, $red, $blue] = shirt();
    ($this->stock)($red, 5, 10);
    ($this->stock)($blue, 5, 10);

    $adjustment = ($this->adjust)($this->manager, 'recount', [[$red, 1], [$blue, -1]]);
    expect($adjustment->status)->toBe('pending')
        ->and($adjustment->type->value)->toBe('mixed');

    $this->actingAs(($this->member)('owner'))->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();
    expect([($this->quantity)($red), ($this->quantity)($blue)])->toBe([6, 4]);
});

it('applies a recount that only raises stock on saving', function () {
    ($this->stock)($this->default, 5, 10);

    expect(($this->adjust)($this->manager, 'recount', [[$this->default, 2]])->status)->toBe('completed')
        ->and(($this->quantity)($this->default))->toBe(7);
});

it('applies the stock once on approval and can\'t be approved twice', function () {
    ($this->stock)($this->default, 5, 10);
    $adjustment = ($this->adjust)($this->clerk, 'lost', [[$this->default, -2]]);

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();
    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertForbidden();

    expect($adjustment->fresh()->status)->toBe('completed')
        ->and($adjustment->fresh()->approved_by)->toBe($this->manager->id)
        ->and(($this->quantity)($this->default))->toBe(3)
        ->and(DB::table('stock_movements')->where('variation_id', $this->default->id)->where('movement_type', StockMovementType::ADJUSTMENT_REMOVE->value)->count())->toBe(1);
});

it('lets a creator holding the approve permission apply found stock on saving, and holds a clerk\'s', function () {
    ($this->stock)($this->default, 5, 10);

    $applied = ($this->adjust)($this->manager, 'found', [[$this->default, 3]]);
    expect($applied->status)->toBe('completed')
        ->and($applied->approved_by)->toBe($this->manager->id)
        ->and($applied->auto_applied)->toBeTrue()
        ->and((float) $applied->cost_value)->toBe(30.0)
        ->and(($this->quantity)($this->default))->toBe(8);

    // The clerk holds create, not approve: theirs always waits.
    expect(($this->adjust)($this->clerk, 'found', [[$this->default, 1]])->status)->toBe('pending')
        ->and(($this->quantity)($this->default))->toBe(8);
});

it('holds damaged stock for a second person: not its creator, even the owner, but a manager', function () {
    $owner = ($this->member)('owner');
    ($this->stock)($this->default, 5, 10);

    $adjustment = ($this->adjust)($owner, 'damaged', [[$this->default, -1]]);
    expect($adjustment->status)->toBe('pending');

    $this->actingAs($owner)->post(route('stock-adjustments.approve', $adjustment))->assertForbidden();
    expect(($this->quantity)($this->default))->toBe(5);

    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();
    expect(($this->quantity)($this->default))->toBe(4);
});

it('holds an adjustment worth the threshold or more, and applies one below it', function (int $units, string $status) {
    ($this->stock)($this->default, 100, 100);

    expect(($this->adjust)($this->manager, 'other', [[$this->default, -$units]])->status)->toBe($status);
})->with([
    'exactly KES 5,000' => [50, 'pending'],
    'above it' => [51, 'pending'],
    'below it' => [49, 'completed'],
]);

it('values a recount by the units it moves both ways, so +100 and −100 above the threshold waits', function () {
    [, $red, $blue] = shirt();
    ($this->stock)($red, 10, 30);
    ($this->stock)($blue, 100, 30);

    $adjustment = ($this->adjust)($this->clerk, 'recount', [[$red, 100], [$blue, -100]]);

    expect($adjustment->status)->toBe('pending')
        ->and([($this->quantity)($red), ($this->quantity)($blue)])->toBe([10, 100]);
});

it('receives found stock at the shop\'s latest batch cost', function () {
    ($this->stock)($this->default, 2, 10, '-3 days');
    ($this->stock)($this->default, 2, 12, '-1 day');

    $adjustment = ($this->adjust)($this->manager, 'found', [[$this->default, 2]]);

    expect($adjustment->status)->toBe('completed')
        ->and((float) DB::table('cost_layers')->where('source_type', 'adjustment')->where('source_id', $adjustment->id)->value('unit_cost'))->toBe(12.0);
});

it('holds found stock with no batch to cost it for a second person, then receives it at an unknown cost', function () {
    $this->default->update(['cost_price' => 99]);

    $adjustment = ($this->adjust)($this->clerk, 'found', [[$this->default, 2]]);
    expect($adjustment->status)->toBe('pending');

    $this->actingAs($this->manager)->get(route('stock-adjustments.show', $adjustment))->assertOk()->assertSee('Cost unknown');
    $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    $layer = DB::table('cost_layers')->where('source_type', 'adjustment')->where('source_id', $adjustment->id)->first();
    expect($layer->unit_cost)->toBeNull()
        ->and((int) $layer->remaining_quantity)->toBe(2);
});

it('lets only the owner change the threshold, and audits the change', function () {
    migrateAuditDatabase();
    $owner = ($this->member)('owner');
    $business = testBusiness();
    $settingsEditor = User::factory()->create();
    $settingsEditor->givePermissionTo('settings.update');
    $settingsEditor->shops()->attach($this->shop);

    foreach ([$this->clerk, $settingsEditor] as $refused) {
        $this->actingAs($refused)->put(route('business.settings.update'), [
            'name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone, 'adjustment_approval_threshold' => 1000,
        ])->assertForbidden();
    }
    // Saving the page's other settings, the threshold unchanged, still works for them.
    $this->actingAs($settingsEditor)->put(route('business.settings.update'), [
        'name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(data_get($business->fresh()->settings, 'stock.adjustment_approval_threshold'))->toBeNull();

    $this->actingAs($owner)->put(route('business.settings.update'), [
        'name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone, 'adjustment_approval_threshold' => 1000,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(data_get($business->fresh()->settings, 'stock.adjustment_approval_threshold'))->toEqual(1000);
    $audit = AuditLog::query()->where('auditable_type', Business::class)->where('auditable_id', $business->id)->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->old_values)->toEqual(['stock.adjustment_approval_threshold' => 5000])
        ->and($audit->new_values)->toEqual(['stock.adjustment_approval_threshold' => 1000])
        ->and($audit->user_id)->toBe($owner->id);

    // Now worth KES 1,000 or more waits.
    ($this->stock)($this->default, 20, 100);
    expect(($this->adjust)($this->manager, 'other', [[$this->default, -10]])->status)->toBe('pending');
});

it('refuses "returned to supplier" on a new adjustment', function () {
    ($this->stock)($this->default, 5, 10);

    $this->actingAs($this->clerk)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => 'returned_to_supplier', 'items' => [['product_id' => $this->product->id, 'quantity_change' => -1]],
    ])->assertSessionHasErrors('reason');

    $this->actingAs($this->clerk)->get(route('stock-adjustments.create'))->assertOk()->assertDontSee('Returned to Supplier');
});

it('lists adjustments approved but never applied, and every one with a reason, without changing them', function () {
    ($this->stock)($this->default, 5, 10);
    $make = fn (string $reason, string $status, int $change) => tap(StockAdjustment::factory()->create([
        'shop_id' => $this->shop->id, 'type' => $change > 0 ? 'increase' : 'decrease', 'reason' => $reason, 'status' => $status, 'created_by' => $this->clerk->id,
    ]), fn (StockAdjustment $adjustment) => $adjustment->items()->create([
        'product_id' => $this->product->id, 'variation_id' => $this->default->id, 'shop_id' => $this->shop->id, 'quantity_change' => $change,
    ]));
    $unapplied = $make('damaged', 'approved', -2);
    $returned = $make('returned_to_supplier', 'completed', -1);
    $applied = $make('found', 'completed', 1);

    $this->artisan('stock-adjustments:list-unapplied', ['--business' => testBusiness()->id])
        ->expectsOutputToContain($unapplied->adjustment_number)
        ->doesntExpectOutputToContain($returned->adjustment_number)
        ->doesntExpectOutputToContain($applied->adjustment_number)
        ->assertSuccessful();

    $this->artisan('stock-adjustments:list-unapplied', ['--business' => testBusiness()->id, '--reason' => 'returned_to_supplier'])
        ->expectsOutputToContain($returned->adjustment_number)
        ->doesntExpectOutputToContain($unapplied->adjustment_number)
        ->assertSuccessful();

    expect($unapplied->fresh()->status)->toBe('approved')
        ->and(($this->quantity)($this->default))->toBe(5);
});

it('numbers adjustments in a gapless series per shop, and two businesses\' don\'t collide', function () {
    $other = Shop::factory()->create(['code' => 'MSA']);
    $made = fn (Shop $shop) => StockAdjustment::factory()->create(['shop_id' => $shop->id, 'type' => 'increase', 'reason' => 'found', 'status' => 'pending', 'created_by' => $this->clerk->id])->adjustment_number;

    expect([$made($this->shop), $made($this->shop), $made($other)])
        ->toBe(['ADJ-NRB-2026-000001', 'ADJ-NRB-2026-000002', 'ADJ-MSA-2026-000001']);

    $two = TwoBusinesses::create();
    $tenant = app(TenantContext::class);
    $numbers = [
        $tenant->run($two->a->business, fn () => $made($two->a->shops[0])),
        $tenant->run($two->b->business, fn () => $made($two->b->shops[0])),
    ];
    expect($numbers)->toBe(['ADJ-MAIN-2026-000001', 'ADJ-MAIN-2026-000001']);
});

it('answers 404 for another business\'s adjustment', function () {
    $two = TwoBusinesses::create();
    $adjustment = app(TenantContext::class)->run($two->b->business, fn () => StockAdjustment::factory()->create([
        'shop_id' => $two->b->shops[0]->id, 'type' => 'increase', 'reason' => 'found', 'created_by' => $two->b->owner->id, 'status' => 'pending',
    ]));
    app(TenantContext::class)->forget();

    $this->actingAs($two->a->owner)->get("/stock-adjustments/{$adjustment->getRouteKey()}")->assertNotFound();
    $this->actingAs($two->a->owner)->post("/stock-adjustments/{$adjustment->getRouteKey()}/approve")->assertNotFound();
});
