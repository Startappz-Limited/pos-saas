<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * The flat API and units (stage 11, step 8): a product lists its units, a sale's lines name
 * their unit with the base quantity as a string, and a unit's own barcode finds its product
 * exactly, so a crate's code is never taken for a bottle's.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Africa/Nairobi'));
    $this->shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->owner->shops()->attach($this->shop);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 120, 'barcode' => 'BOTTLE-1', 'name' => 'Soda 500ml']);
    $this->crate = ProductUnit::factory()->create([
        'variation_id' => $this->product->defaultVariation->id, 'name' => 'Crate of 24', 'factor' => '24.000', 'precision' => 0,
        'barcode' => 'CRATE-24', 'selling_price' => 2800,
    ]);
    app(StockLedger::class)->receive($this->shop->id, $this->product->defaultVariation, '500.000', 5.0, StockMovementType::INTAKE);
    Sanctum::actingAs($this->owner, ['*']);
});

it('finds a product by its unit\'s own barcode, exactly, and not by a fragment of it', function () {
    $found = $this->getJson('/api/products?search=CRATE-24')->assertOk()->json('data.data');
    expect(collect($found)->pluck('id'))->toContain($this->product->id);

    $partial = $this->getJson('/api/products?search=CRATE-2')->assertOk()->json('data.data');
    expect(collect($partial)->pluck('id'))->not->toContain($this->product->id)
        ->and(collect($this->getJson('/api/products?search=BOTTLE-1')->json('data.data'))->pluck('id'))->toContain($this->product->id);
});

it('lists a product\'s units, the crate included, and leaves a deleted unit off the list', function () {
    $units = collect($this->getJson("/api/products/{$this->product->getRouteKey()}")->assertOk()->json('data.units'));

    expect($units->pluck('name')->all())->toContain('Crate of 24');

    $this->crate->delete();
    $after = collect($this->getJson("/api/products/{$this->product->getRouteKey()}")->assertOk()->json('data.units'));
    expect($after->pluck('name')->all())->not->toContain('Crate of 24');
});

it('answers a sale\'s line with its unit and the base quantity as a three-decimal string', function () {
    $sale = Sale::factory()->create([
        'shop_id' => $this->shop->id, 'status' => 'completed', 'created_by' => $this->owner->id,
    ]);
    $line = $sale->items()->create([
        'uuid' => (string) Str::uuid(), 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
        'variation_id' => $this->product->defaultVariation->id, 'unit_id' => $this->crate->id,
        'quantity' => '2.000', 'base_quantity' => '48.000', 'unit_price' => 2800, 'line_total' => 5600,
    ]);

    $body = $this->getJson("/api/sales/{$sale->getRouteKey()}")->assertOk()->json('data.items.0');

    expect($body['unit']['name'])->toBe('Crate of 24')
        ->and($body['base_quantity'])->toBe('48.000')
        ->and($body['quantity'])->toBe(2)
        ->and($line->id)->not->toBeNull();
});

it('sends a fractional line as a number on the flat API, and its base quantity as the exact string', function () {
    $kg = ProductUnit::factory()->create([
        'variation_id' => $this->product->defaultVariation->id, 'name' => 'Kilogram', 'factor' => '1.000', 'precision' => 3,
    ]);
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'created_by' => $this->owner->id]);
    $sale->items()->create([
        'uuid' => (string) Str::uuid(), 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
        'variation_id' => $this->product->defaultVariation->id, 'unit_id' => $kg->id,
        'quantity' => '2.500', 'base_quantity' => '2.500', 'unit_price' => 300, 'line_total' => 750,
    ]);

    $line = $this->getJson("/api/sales/{$sale->getRouteKey()}")->assertOk()->json('data.items.0');

    expect($line['quantity'])->toBe(2.5)
        ->and($line['base_quantity'])->toBe('2.500')
        ->and($line['unit']['name'])->toBe('Kilogram');
});

it('does not send a unit\'s internal columns on the flat API', function () {
    $body = $this->getJson("/api/products/{$this->product->getRouteKey()}")->assertOk()->json('data.units.0');

    expect($body)->not->toHaveKey('business_id')
        ->and($body)->not->toHaveKey('created_by')
        ->and($body)->toHaveKey('name');
});
