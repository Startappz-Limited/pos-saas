<?php

use App\Enums\ApiErrorCode;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SyncIssue;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * Sales pushed with units (stage 11, step 8): a line names its unit by uuid; a unit the server
 * never had holds the sale (`unknown_unit`); the till's base quantity is kept, and a figure that
 * disagrees with quantity x factor is recorded; an older app's integer push still records in base
 * units.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    // Dispositions reach apps at or above this version (stage 10, step 5c part 2).
    config(['sync.min_version.dispositions' => '2.0.0']);
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->shop = $this->a->shops[0];
    $this->product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 120]));
    $this->tenant->run($this->a->business, fn () => app(StockLedger::class)->receive($this->shop->id, $this->product->defaultVariation, '500.000', 5.0, StockMovementType::INTAKE));
    $this->register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->shop->id));
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();
});

function unitPush(string $token, array $items)
{
    Till::freshRequest();
    test()->withHeader('X-App-Version', '2.0.0');

    return test()->withToken($token)->postJson('/api/v1/sync/push', ['items' => $items]);
}

function unitSale(array $line): array
{
    return [
        'client_uuid' => (string) Str::uuid(), 'type' => 'sale', 'recorded_at' => '2026-10-26 10:00:00',
        'data' => [
            'source_id' => DB::table('sale_sources')->where('business_id', test()->a->business->id)->value('id'),
            'delivery_location' => 'In store', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000',
            'payment_method' => 'cash', 'items' => [$line],
        ],
    ];
}

function crateOf(Product $product, string $name = 'Carton of 24', string $factor = '24.000', int $precision = 0): ProductUnit
{
    return test()->tenant->run(test()->a->business, fn () => ProductUnit::factory()->create([
        'variation_id' => $product->defaultVariation->id, 'name' => $name, 'factor' => $factor, 'precision' => $precision,
    ]));
}

it('records a crate sold by its own unit as 24 base units per crate, at the price sent', function () {
    $crate = crateOf($this->product);
    $result = unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 3, 'price' => 2800, 'unit_id' => $crate->uuid])])
        ->assertOk()->json('data.results.0');

    expect($result['status'])->toBe('applied');
    $item = SaleItem::query()->withoutGlobalScopes()->where('sale_id', Sale::query()->withoutGlobalScopes()->latest('id')->value('id'))->first();
    expect((string) $item->quantity)->toStartWith('3')
        ->and((string) $item->base_quantity)->toStartWith('72')
        ->and((float) $item->unit_price)->toBe(2800.0);
});

it('keeps the till\'s base quantity and records the difference when it disagrees with quantity x factor', function () {
    $crate = crateOf($this->product);
    $line = ['product_id' => $this->product->id, 'quantity' => 3, 'price' => 2800, 'unit_id' => $crate->uuid, 'base_quantity' => 70];
    $result = unitPush($this->token, [unitSale($line)])->assertOk()->json('data.results.0');

    expect($result['status'])->toBe('applied')
        ->and($result['result']['flags'] ?? [])->toContain('unit_factor_mismatch');

    $sale = Sale::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
    expect((string) SaleItem::query()->withoutGlobalScopes()->where('sale_id', $sale->id)->value('base_quantity'))->toStartWith('70');

    $issue = $this->tenant->run($this->a->business, fn () => SyncIssue::query()->where('type', SyncIssue::UNIT_FACTOR_MISMATCH)->firstOrFail());
    expect($issue->details['till_base_quantity'])->toBe('70.000')
        ->and($issue->details['server_base_quantity'])->toBe('72.000');
});

it('holds a sale whose unit the server never had, with an unknown_unit issue, and records nothing', function () {
    $salesBefore = Sale::query()->withoutGlobalScopes()->count();
    $line = ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 2800, 'unit_id' => (string) Str::uuid()];

    unitPush($this->token, [unitSale($line)])
        ->assertJsonPath('data.results.0.status', 'refused')
        ->assertJsonPath('data.results.0.code', ApiErrorCode::UnknownUnit->value)
        ->assertJsonPath('data.results.0.disposition', 'hold');

    expect(Sale::query()->withoutGlobalScopes()->count())->toBe($salesBefore)
        ->and($this->tenant->run($this->a->business, fn () => SyncIssue::query()->where('type', SyncIssue::UNKNOWN_UNIT)->count()))->toBe(1);
});

it('holds a unit that belongs to another variation, even of the same product', function () {
    $other = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $elsewhere = crateOf($other, 'Carton of 12', '12.000');

    unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100, 'unit_id' => $elsewhere->uuid])])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::UnknownUnit->value);
});

it('holds a unit of another business, as an isolation check', function () {
    $two = TwoBusinesses::create();
    $theirs = $this->tenant->run($two->b->business, fn () => ProductUnit::factory()->create([
        'variation_id' => $two->b->product->defaultVariation->id, 'name' => 'Theirs', 'factor' => '6.000', 'precision' => 0,
    ]));

    unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100, 'unit_id' => $theirs->uuid])])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::UnknownUnit->value);
});

it('still applies a sale in a unit deleted since the till last pulled, as the sale was made', function () {
    $crate = crateOf($this->product);
    $this->tenant->run($this->a->business, fn () => $crate->delete());

    unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 2, 'price' => 2800, 'unit_id' => $crate->uuid])])
        ->assertJsonPath('data.results.0.status', 'applied');
});

it('still records an older app\'s integer push, with no unit, in base units', function () {
    unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 2, 'price' => 120])])
        ->assertJsonPath('data.results.0.status', 'applied');

    $sale = Sale::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
    expect((string) SaleItem::query()->withoutGlobalScopes()->where('sale_id', $sale->id)->value('base_quantity'))->toStartWith('2');
});

it('ends a held sale with an unknown unit when someone discards it, and the till learns it on its next push', function () {
    $sale = unitSale(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 2800, 'unit_id' => (string) Str::uuid()]);

    unitPush($this->token, [$sale])->assertJsonPath('data.results.0.code', ApiErrorCode::UnknownUnit->value);

    $discard = [
        'client_uuid' => (string) Str::uuid(), 'type' => 'outbox_item_discarded', 'recorded_at' => '2026-10-26 10:05:00',
        'data' => [
            'item_type' => 'sale', 'client_uuid' => $sale['client_uuid'], 'reason' => 'Unit not in the catalogue',
            'discarded_by' => $this->a->owner->uuid, 'item' => ['note' => 'raw'],
        ],
    ];
    unitPush($this->token, [$discard])->assertJsonPath('data.results.0.status', 'applied');

    // The discarded sale answers `discarded` when the till pushes it again, so it clears it.
    unitPush($this->token, [$sale])->assertJsonPath('data.results.0.status', 'discarded');
    expect($this->tenant->run($this->a->business, fn () => SyncIssue::query()->where('type', SyncIssue::OUTBOX_ITEM_DISCARDED)->count()))->toBe(1);
});

it('refuses a till base quantity that is negative or not a number, and stores nothing', function () {
    $crate = crateOf($this->product);
    $salesBefore = Sale::query()->withoutGlobalScopes()->count();

    unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 2800, 'unit_id' => $crate->uuid, 'base_quantity' => -24])])
        ->assertJsonPath('data.results.0.status', 'refused')
        ->assertJsonPath('data.results.0.code', ApiErrorCode::ValidationFailed->value);

    unitPush($this->token, [unitSale(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 2800, 'unit_id' => $crate->uuid, 'base_quantity' => 'lots'])])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::ValidationFailed->value);

    expect(Sale::query()->withoutGlobalScopes()->count())->toBe($salesBefore);
});
