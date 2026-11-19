<?php

use App\Actions\Sale\RecordSale;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Sale;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * Quantities on the `/api/v1` surfaces (stage 11, step 8): the receipt's line quantity is a
 * JSON number that keeps a fraction, with the unit's name and exact figures beside it; the pull
 * sends stock as whole numbers (rounded down) and exact strings, with its sellable and expired
 * figures and the next expiry.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00', 'Africa/Nairobi'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->shop = $this->a->shops[0];
    $this->register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->shop->id));
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->seconds();
    $GLOBALS['till_token'] = $this->token;
});

/**
 * A completed sale of $quantity of $product at the till's shop, made through the real path.
 */
function saleOf(string $quantity, Product $product, TwoBusinesses $fixture, TenantContext $tenant, int $shopId)
{
    $source = DB::table('sale_sources')->where('business_id', $fixture->a->business->id)->value('id');
    $tenant->run($fixture->a->business, fn () => app(StockLedger::class)->receive($shopId, $product->defaultVariation, '20.000', 5.0, StockMovementType::INTAKE));

    return $tenant->run($fixture->a->business, fn () => app(RecordSale::class)->execute([
        'shop_id' => $shopId, 'source_id' => $source, 'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000', 'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'price' => 150]],
    ])->sale);
}

function receiptOf(Sale $sale)
{
    Till::freshRequest();

    return test()->withToken($GLOBALS['till_token'])->getJson("/api/v1/sales/{$sale->uuid}/receipt");
}

it('sends a fractional receipt line as a number, with its unit and exact figures beside it', function () {
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    // Sold by weight: a whole-piece base unit would refuse 2.5 (BaseQuantity, base precision).
    $this->tenant->run($this->a->business, fn () => $product->defaultVariation->baseUnit()->update(['precision' => 3]));
    $kg = $this->tenant->run($this->a->business, fn () => ProductUnit::factory()->create([
        'variation_id' => $product->defaultVariation->id, 'name' => 'Bag of rice (kg)', 'factor' => '1.000', 'precision' => 3,
    ]));
    $sale = saleOf('2.5', $product, $this->fixture, $this->tenant, $this->shop->id);
    DB::table('sale_items')->where('sale_id', $sale->id)->update(['unit_id' => $kg->id]);

    $response = receiptOf($sale)->assertOk();

    $item = collect($response->json('data.lines'))->first();
    expect($item['quantity'])->toBe(2.5)
        ->and($item['unit_name'])->toBe('Bag of rice (kg)')
        ->and($item['quantity_exact'])->toBe('2.500')
        ->and($item['base_quantity'])->toBe('2.500');
});

it('sends a whole receipt line as an integer, as before', function () {
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $response = receiptOf(saleOf('2', $product, $this->fixture, $this->tenant, $this->shop->id))->assertOk();

    $item = collect($response->json('data.lines'))->first();
    expect($item['quantity'])->toBeInt()
        ->and($item['quantity_exact'])->toBeString();
});

it('pulls stock as whole numbers and exact strings, with the sellable, expired and next-expiry figures', function () {
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $ledger = app(StockLedger::class);
    $this->tenant->run($this->a->business, function () use ($ledger, $product): void {
        $ledger->receive($this->shop->id, $product->defaultVariation, '10.500', 5.0, StockMovementType::INTAKE, ['batch_number' => 'OLD', 'expiry_date' => '2026-10-01']);
        $ledger->receive($this->shop->id, $product->defaultVariation, '4.000', 5.0, StockMovementType::INTAKE, ['batch_number' => 'NEW', 'expiry_date' => '2027-03-31']);
        $ledger->receive($this->shop->id, $product->defaultVariation, '2.250', 5.0, StockMovementType::INTAKE, ['batch_number' => 'NONE']);
    });

    $this->travel(5)->seconds();
    Till::freshRequest();
    $stock = collect(test()->withToken($this->token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk()->json('data.stock'))
        ->firstWhere('product_uuid', $product->uuid);

    expect($stock['quantity'])->toBe(16)
        ->and($stock['base_quantity'])->toBe('16.750')
        ->and($stock['sellable_quantity'])->toBe('6.250')
        ->and($stock['expired_quantity'])->toBe('10.500')
        ->and($stock['next_expiry'])->toBe('2027-03-31');
});

it('sends a deleted unit as an update flagged deleted, never as a deletion', function () {
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $crate = $this->tenant->run($this->a->business, fn () => ProductUnit::factory()->create([
        'variation_id' => $product->defaultVariation->id, 'name' => 'Carton of 24', 'factor' => '24.000', 'precision' => 0,
    ]));
    $this->tenant->run($this->a->business, fn () => $crate->delete());

    $this->travel(5)->seconds();
    Till::freshRequest();
    $body = test()->withToken($this->token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk()->json('data');

    $sent = collect($body['units'])->firstWhere('uuid', $crate->uuid);
    expect($sent)->not->toBeNull()
        ->and($sent['deleted_at'])->not->toBeNull()
        ->and($sent['can_sell'])->toBeFalse()
        ->and($sent['can_buy'])->toBeFalse()
        ->and($sent['factor'])->toBe('24.000')
        ->and(collect($body['deletions'] ?? [])->pluck('uuid')->all())->not->toContain($crate->uuid);
});

it('tells the till how the business takes stock from its batches', function () {
    Till::freshRequest();
    $settings = test()->withToken($this->token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk()->json('data.settings');

    expect($settings['stock']['consumption'])->toBeIn(['fifo', 'fefo']);
});

it('names a unit deleted since the sale on the receipt, and sends every unit again to a cursor that has none', function () {
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $crate = $this->tenant->run($this->a->business, fn () => ProductUnit::factory()->create([
        'variation_id' => $product->defaultVariation->id, 'name' => 'Case of 6', 'factor' => '6.000', 'precision' => 0,
    ]));
    $sale = saleOf('1', $product, $this->fixture, $this->tenant, $this->shop->id);
    DB::table('sale_items')->where('sale_id', $sale->id)->update(['unit_id' => $crate->id]);
    $this->tenant->run($this->a->business, fn () => $crate->delete());

    $item = collect(receiptOf($sale)->assertOk()->json('data.lines'))->first();
    expect($item['unit_name'])->toBe('Case of 6');

    // A first pull sends every unit; the cursor it returns sends none again until one changes.
    $this->travel(5)->seconds();
    Till::freshRequest();
    $first = test()->withToken($GLOBALS['till_token'])->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk();
    $cursor = $first->json('meta.cursor');
    expect(collect($first->json('data.units'))->pluck('uuid'))->toContain($crate->uuid);

    Till::freshRequest();
    $again = test()->withToken($GLOBALS['till_token'])->getJson('/api/v1/sync/pull?'.http_build_query(['since' => $cursor]))->assertOk();
    expect(collect($again->json('data.units'))->pluck('uuid'))->not->toContain($crate->uuid);
});

it('pulls a stock row again after the shop\'s midnight, when one of its batches has expired', function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00:00', 'Africa/Nairobi'));
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $this->tenant->run($this->a->business, fn () => app(StockLedger::class)->receive($this->shop->id, $product->defaultVariation, '6.000', 5.0, StockMovementType::INTAKE, ['batch_number' => 'DUE', 'expiry_date' => '2026-11-16']));
    $this->tenant->run($this->a->business, fn () => $this->shop->forceFill(['expiry_touched_at' => Carbon::parse('2026-11-16 12:00:00', 'Africa/Nairobi')])->saveQuietly());

    // Before the touch, the row is as it was: still sellable, nothing expired, nothing new to pull.
    $this->travelTo(Carbon::parse('2026-11-16 15:00:00', 'Africa/Nairobi'));
    Till::freshRequest();
    $before = test()->withToken($this->token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk();
    $cursor = $before->json('meta.cursor');

    // The shop's midnight passes; the touch runs (as the scheduler's every-15-minute run does).
    $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Africa/Nairobi'));
    // Run the command the scheduler runs (every 15 minutes), not the action behind it.
    Artisan::call('stock:touch-expiring');
    $this->travel(5)->seconds();

    Till::freshRequest();
    $after = test()->withToken($this->token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => $cursor]))->assertOk();
    $stock = collect($after->json('data.stock'))->firstWhere('product_uuid', $product->uuid);

    expect($stock)->not->toBeNull()
        ->and($stock['sellable_quantity'])->toBe('0.000')
        ->and($stock['expired_quantity'])->toBe('6.000');
});

it('sends every quantity as the encoding says: numbers where the contract has numbers, exact strings elsewhere', function () {
    $product = $this->tenant->run($this->a->business, fn () => Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]));
    $this->tenant->run($this->a->business, fn () => app(StockLedger::class)->receive($this->shop->id, $product->defaultVariation, '3.250', 5.0, StockMovementType::INTAKE));
    // Sold by weight: a whole-piece base unit would refuse 1.25 (BaseQuantity, base precision).
    $this->tenant->run($this->a->business, fn () => $product->defaultVariation->baseUnit()->update(['precision' => 3]));
    $kg = $this->tenant->run($this->a->business, fn () => ProductUnit::factory()->create([
        'variation_id' => $product->defaultVariation->id, 'name' => 'Kilogram', 'factor' => '1.000', 'precision' => 3,
    ]));
    $sale = saleOf('1.250', $product, $this->fixture, $this->tenant, $this->shop->id);
    DB::table('sale_items')->where('sale_id', $sale->id)->update(['unit_id' => $kg->id]);
    $this->travel(5)->seconds();

    Till::freshRequest();
    $pull = test()->withToken($this->token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk()->json('data');
    $receipt = receiptOf($sale)->assertOk()->json('data');

    // Quantities the contract has as numbers: never a string, whatever the value.
    $stockRow = collect($pull['stock'])->firstWhere('product_uuid', $product->uuid);
    // A threshold the shop never set is null, not a zero: null stays null.
    expect($stockRow['quantity'])->toBeInt()
        ->and(is_int($stockRow['reorder_level']) || $stockRow['reorder_level'] === null)->toBeTrue()
        ->and(collect($receipt['lines'])->pluck('quantity')->every(fn ($q) => is_int($q) || is_float($q)))->toBeTrue();

    // Exact figures, always the three-decimal string the contract names.
    expect($stockRow['base_quantity'])->toMatch('/^\d+\.\d{3}$/')
        ->and($stockRow['sellable_quantity'])->toMatch('/^\d+\.\d{3}$/')
        ->and($stockRow['expired_quantity'])->toMatch('/^\d+\.\d{3}$/')
        ->and(collect($receipt['lines'])->first()['base_quantity'])->toMatch('/^\d+\.\d{3}$/')
        ->and(collect($receipt['lines'])->first()['quantity_exact'])->toBe('1.250');
});
