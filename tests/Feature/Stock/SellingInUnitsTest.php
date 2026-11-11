<?php

use App\Actions\Sale\VoidSale;
use App\Enums\ReturnReason;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * Selling in units (stage 11, step 3): a sale line takes the unit it was
 * sold in, moving base_quantity through the ledger.
 */

beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');

    $this->soda = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'bottle', 'selling_price' => 50, 'stock_quantity' => 0]);
    $this->crate = $this->soda->defaultVariation->units()->create([
        'business_id' => $this->soda->business_id, 'name' => 'crate', 'factor' => 24, 'precision' => 0,
        'selling_price' => 1100, 'is_base' => false,
    ]);
    $this->ledger->receive($this->shop->id, $this->soda->defaultVariation, 100, 20, StockMovementType::INTAKE);

    $this->fertiliser = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg', 'selling_price' => 100, 'stock_quantity' => 0]);
    $this->ledger->receive($this->shop->id, $this->fertiliser->defaultVariation, 50, 60, StockMovementType::INTAKE);
});

function salesStoreUnitPayload(Product $product, string $unitUuid, float $quantity, float $price): array
{
    return [
        'shop_id' => test()->shop->id,
        'source_id' => test()->source,
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => $product->id, 'unit_id' => $unitUuid, 'quantity' => $quantity, 'price' => $price],
        ],
    ];
}

it('sells 3 crates by unit, taking 72 bottles and charging 3 x the crate price', function () {
    $this->actingAs($this->owner)
        ->post(route('sales.store'), salesStoreUnitPayload($this->soda, $this->crate->uuid, 3, 1100))
        ->assertSessionHasNoErrors();

    $sale = Sale::latest('id')->firstOrFail();
    $item = $sale->items->sole();

    expect((string) $item->quantity)->toBe('3.000')
        ->and((string) $item->base_quantity)->toBe('72.000')
        ->and($item->unit_id)->toBe($this->crate->id)
        ->and((float) $sale->subtotal)->toBe(3300.0)
        ->and($this->ledger->quantity($this->shop->id, $this->soda->defaultVariation->id))->toBe(28);
});

it('sells 2.5 kg of fertiliser through the API', function () {
    Sanctum::actingAs($this->owner, ['*']);
    $baseUnit = $this->fertiliser->defaultVariation->baseUnit;

    $uuid = $this->postJson('/api/sales', salesStoreUnitPayload($this->fertiliser, $baseUnit->uuid, 2.5, 100))
        ->assertCreated()->json('data.uuid');

    $sale = Sale::where('uuid', $uuid)->sole();
    $item = $sale->items->sole();

    expect((string) $item->quantity)->toBe('2.500')
        ->and((string) $item->base_quantity)->toBe('2.500')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->fertiliser->defaultVariation->id))->toBe('47.500');
});

it('refuses a fraction of a whole-unit (crate) online', function () {
    $this->actingAs($this->owner)
        ->post(route('sales.store'), salesStoreUnitPayload($this->soda, $this->crate->uuid, 1.5, 1100))
        ->assertSessionHasErrors('items.0.quantity');
});

it('voiding a sale of 2 crates gives back 48 bottles', function () {
    $this->actingAs($this->owner)
        ->post(route('sales.store'), salesStoreUnitPayload($this->soda, $this->crate->uuid, 2, 1100))
        ->assertSessionHasNoErrors();
    $sale = Sale::latest('id')->firstOrFail();
    expect($this->ledger->quantity($this->shop->id, $this->soda->defaultVariation->id))->toBe(52);

    app(VoidSale::class)->execute($sale, 'customer changed mind', $this->owner);

    expect($this->ledger->quantity($this->shop->id, $this->soda->defaultVariation->id))->toBe(100);
});

it('returns and restocks a crate, in base units, at the per-base-unit cost', function () {
    $this->actingAs($this->owner)
        ->post(route('sales.store'), salesStoreUnitPayload($this->soda, $this->crate->uuid, 2, 1100))
        ->assertSessionHasNoErrors();
    $sale = Sale::latest('id')->firstOrFail();
    $saleItem = $sale->items->sole();

    $returns = app(ReturnService::class);
    $saleReturn = $returns->createReturn([
        'sale_id' => $sale->id,
        'reason' => ReturnReason::CUSTOMER_CHANGED_MIND->value,
        'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
    ], $this->owner->id);

    $returnItem = $saleReturn->items->sole();
    expect($returnItem->unit_id)->toBe($this->crate->id)
        ->and((string) $returnItem->base_quantity)->toBe('24.000');

    $returns->approveReturn($saleReturn, $this->owner->id);
    $returns->receiveReturn($saleReturn->fresh());

    // 2 crates (48 bottles) sold, 1 crate (24 bottles) returned: net 24 taken.
    expect($this->ledger->quantity($this->shop->id, $this->soda->defaultVariation->id))->toBe(76);

    $layer = DB::table('cost_layers')->where('source_type', 'sale_return')->sole();
    expect((float) $layer->unit_cost)->toBe(20.0);
});
