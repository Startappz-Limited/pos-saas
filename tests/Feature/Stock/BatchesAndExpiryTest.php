<?php

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Category;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\SyncIssue;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * FEFO, batches and expiry (stage 11, step 5): consume() orders by the
 * business's own consumption setting and never takes an expired batch for a
 * sale; business types set that setting's default, and a product's own
 * track_batches, for what's created afterwards.
 */

beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');
});

function layer(Product $product, Shop $shop, string $batch, string $receivedAt, ?string $expiry, int $quantity = 10, float $cost = 10.0): CostLayer
{
    app(StockLedger::class)->receive($shop->id, $product->defaultVariation, $quantity, $cost, StockMovementType::INTAKE);
    $layer = CostLayer::query()->where('shop_id', $shop->id)->where('variation_id', $product->defaultVariation->id)
        ->where('batch_number', null)->latest('id')->first();
    $layer->update(['batch_number' => $batch, 'received_at' => $receivedAt, 'expiry_date' => $expiry]);

    return $layer->fresh();
}

it('consumes FIFO (oldest received) by default, and FEFO (soonest to expire) when the business is set to it', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 50]);
    $old = layer($product, $this->shop, 'OLD', '2026-01-01', '2026-12-31');
    $newer = layer($product, $this->shop, 'NEWER', '2026-06-01', '2026-02-28');

    // FIFO (default): the older-received layer (OLD) is taken first, regardless of expiry.
    $this->ledger->consume($this->shop->id, $product->defaultVariation, 5, StockMovementType::SALE, $product, []);
    expect((float) DB::table('cost_layers')->where('id', $old->id)->value('remaining_quantity'))->toBe(5.0)
        ->and((float) DB::table('cost_layers')->where('id', $newer->id)->value('remaining_quantity'))->toBe(10.0);

    // Switch the business to FEFO: now the soonest-to-expire layer (NEWER) goes first, though OLD
    // was received first.
    Business::query()->whereKey(testBusiness()->id)->update(['settings' => json_encode(['stock' => ['consumption' => 'fefo']])]);
    $this->ledger->consume($this->shop->id, $product->defaultVariation, 5, StockMovementType::SALE, $product, []);
    expect((float) DB::table('cost_layers')->where('id', $old->id)->value('remaining_quantity'))->toBe(5.0)
        ->and((float) DB::table('cost_layers')->where('id', $newer->id)->value('remaining_quantity'))->toBe(5.0);
});

it('refuses a sale that only expired stock could meet, leaving the expired batch untouched', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 50]);
    $this->travelTo(Carbon::parse('2026-11-02 08:00:00', 'Africa/Nairobi'));
    layer($product, $this->shop, 'EXPIRED', '2026-01-01', '2026-10-31', quantity: 10);

    // Expired stock is not sellable, so the sale is refused rather than completed on provisional stock.
    $this->actingAs($this->owner)->postJson(route('sales.store'), [
        'shop_id' => $this->shop->id, 'source_id' => $this->source, 'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In', 'walk_in_customer_phone' => '0700000000', 'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'price' => 50]],
    ])->assertStatus(422);

    expect((float) DB::table('cost_layers')->where('batch_number', 'EXPIRED')->value('remaining_quantity'))->toBe(10.0)
        ->and(DB::table('cost_allocations')->where('is_provisional', true)->count())->toBe(0)
        ->and(Sale::query()->count())->toBe(0);
});

it('expired_stock self-resolves once the shop\'s stock for that variation returns to zero or above', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 50]);
    $this->travelTo(Carbon::parse('2026-11-02 08:00:00', 'Africa/Nairobi'));
    layer($product, $this->shop, 'EXPIRED', '2026-01-01', '2026-10-31', quantity: 10);

    $this->actingAs($this->owner)->postJson(route('sales.store'), [
        'shop_id' => $this->shop->id, 'source_id' => $this->source, 'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In', 'walk_in_customer_phone' => '0700000000', 'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'price' => 50]],
        'oversell_reason' => 'Expired stock only',
    ])->assertOk();

    $issue = SyncIssue::query()->where('type', SyncIssue::EXPIRED_STOCK)->sole();
    expect($issue->resolved_at)->toBeNull();

    $this->ledger->receive($this->shop->id, $product->defaultVariation, 5, 10.0, StockMovementType::INTAKE);
    expect($issue->fresh()->resolved_at)->not->toBeNull();
});

it('a business type sets its consumption and track_batches defaults; businesses:set-type gives UAT-like businesses general_retail and changes no setting', function () {
    expect(BusinessType::GeneralRetail->consumption())->toBe('fifo')
        ->and(BusinessType::GeneralRetail->tracksBatchesByDefault())->toBeFalse()
        ->and(BusinessType::Pharmacy->consumption())->toBe('fefo')
        ->and(BusinessType::Pharmacy->tracksBatchesByDefault())->toBeTrue()
        ->and(BusinessType::AgroVet->consumption())->toBe('fefo');

    $business = testBusiness();
    expect($business->type)->toBeNull()
        ->and($business->stockConsumption())->toBe('fifo')
        ->and($business->tracksBatchesByDefault())->toBeFalse();

    $this->artisan('businesses:set-type', ['--type' => 'general_retail', '--business' => $business->id, '--apply' => true])
        ->assertSuccessful();

    expect($business->fresh()->type)->toBe(BusinessType::GeneralRetail)
        ->and($business->fresh()->settings)->toBeNull();

    // A second run changes nothing (already has a type).
    $this->artisan('businesses:set-type', ['--type' => 'pharmacy', '--business' => $business->id, '--apply' => true])
        ->assertSuccessful();
    expect($business->fresh()->type)->toBe(BusinessType::GeneralRetail);
});

it('a new product takes the business type\'s track_batches default, which the form can change', function () {
    testBusiness()->forceFill(['type' => BusinessType::Pharmacy])->save();

    $this->actingAs($this->owner)->post(route('products.store'), [
        'name' => 'Amoxicillin', 'shop_id' => $this->shop->id, 'category_id' => Category::factory()->create()->id,
        'selling_price' => 100,
    ])->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Amoxicillin')->firstOrFail();
    expect($product->defaultVariation->track_batches)->toBeTrue();

    $this->actingAs($this->owner)->post(route('products.store'), [
        'name' => 'Bandages', 'shop_id' => $this->shop->id, 'category_id' => Category::factory()->create()->id,
        'selling_price' => 50, 'track_batches' => '0',
    ])->assertSessionHasNoErrors();
    $other = Product::query()->where('name', 'Bandages')->firstOrFail();
    expect($other->defaultVariation->track_batches)->toBeFalse();
});
