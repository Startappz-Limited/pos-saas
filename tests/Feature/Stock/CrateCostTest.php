<?php

use App\Actions\Sale\RecordSale;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

/*
 * A sale in a unit with a factor costs its base quantity at the base cost (stage 11, step 3): two
 * crates of 24 bottles at 10 each cost 480 (240 a crate), not 20.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'selling_price' => 120, 'cost_price' => 10]);
    app(StockLedger::class)->receive($this->shop->id, $this->product->defaultVariation, '100.000', 10.0, StockMovementType::INTAKE);
    $this->crate = ProductUnit::factory()->create([
        'variation_id' => $this->product->defaultVariation->id, 'name' => 'Crate of 24', 'factor' => '24.000', 'precision' => 0,
    ]);
});

it('costs a crate line at its base quantity, and the sale\'s total cost and profit follow from it', function () {
    $sale = app(RecordSale::class)->execute([
        'shop_id' => $this->shop->id,
        'source_id' => DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id'),
        'delivery_location' => 'Counter', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 2800, 'unit_id' => $this->crate->uuid]],
    ])->sale;

    $sale->refresh();
    $line = $sale->items()->first();

    expect((float) $line->base_quantity)->toBe(48.0)
        ->and((float) $sale->total_cost)->toBe(480.0)
        ->and((float) $sale->total_profit)->toBe(5600.0 - 480.0)
        ->and((float) $line->unit_cost)->toBe(240.0);
});
