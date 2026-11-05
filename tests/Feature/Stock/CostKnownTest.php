<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Lines whose cost isn't fully known (stage 10, step 6): `cost_known` is false
 * when any unit came from a batch with no cost (or no estimate); settling
 * below-zero units against such a batch keeps the estimate; entering the cost
 * and re-costing clears it; a badge shows on the sale and in the profit report.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->owner->shops()->attach($this->shop);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => null, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->sell = function (int $quantity): SaleItem {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'completed_at' => now(), 'total_amount' => 100 * $quantity, 'tax_amount' => 0]);
        $line = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => $quantity, 'unit_price' => 100, 'line_total' => 100 * $quantity, 'taxable_amount' => 100 * $quantity, 'status' => 'completed',
        ]);
        $this->ledger->sellItem($line, $this->variation);
        $this->ledger->recostSale($sale->id);

        return $line->fresh();
    };
});

it('marks a line sold from a batch with no cost, and not one sold from a costed batch', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 5, null, StockMovementType::INTAKE);
    $unknown = ($this->sell)(2);

    $this->ledger->receive($this->shop->id, $this->variation, 5, 30, StockMovementType::INTAKE, ['received_at' => now()->addMinute()]);
    $this->travel(2)->minutes();
    $mixed = ($this->sell)(3); // 3 from the uncosted batch's remainder
    $known = ($this->sell)(5); // from the costed batch

    expect($unknown->cost_known)->toBeFalse()
        ->and($mixed->cost_known)->toBeFalse()
        ->and($known->cost_known)->toBeTrue()
        ->and(Sale::query()->costIncomplete()->orderBy('id')->pluck('id')->all())->toBe([$unknown->sale_id, $mixed->sale_id]);
});

it('keeps the provisional estimate when below-zero units settle against a batch with no cost, and marks the line', function () {
    $this->variation->update(['cost_price' => 40]); // the estimate
    $line = ($this->sell)(3);
    expect($line->cost_known)->toBeTrue()->and((float) $line->total_cost)->toBe(120.0);

    $this->ledger->receive($this->shop->id, $this->variation, 5, null, StockMovementType::INTAKE);

    $allocation = DB::table('cost_allocations')->where('sale_item_id', $line->id)->sole();
    expect((bool) $allocation->is_provisional)->toBeFalse()
        ->and((float) $allocation->unit_cost)->toBe(40.0)
        ->and((float) $line->fresh()->total_cost)->toBe(120.0)
        ->and($line->fresh()->cost_known)->toBeFalse();
});

it('clears the mark once the cost is entered and the sale re-costed', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 5, null, StockMovementType::INTAKE);
    $line = ($this->sell)(2);
    $this->product->update(['cost_price' => 35]);
    $this->variation->update(['cost_price' => 35]);

    $this->artisan('sales:recompute-profit', ['--business' => testBusiness()->id, '--apply' => true])->assertSuccessful();

    expect($line->fresh()->cost_known)->toBeTrue()
        ->and((float) $line->fresh()->total_cost)->toBe(70.0)
        ->and((float) DB::table('cost_layers')->where('variation_id', $this->variation->id)->value('unit_cost'))->toBe(35.0);
});

it('shows a "cost incomplete" badge on the sale and in the profit report', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 5, null, StockMovementType::INTAKE);
    $line = ($this->sell)(2);
    $sale = $line->sale;

    $this->actingAs($this->owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Cost incomplete')->assertSee(route('products.purchase-costs.index'), false);
    $this->actingAs($this->owner)->get(route('sales.profitReport', ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()]))
        ->assertOk()->assertSee($sale->invoice_number)->assertSee('Cost incomplete');
});
