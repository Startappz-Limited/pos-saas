<?php

use App\Enums\StockMovementType;
use App\Models\CashPayout;
use App\Models\Product;
use App\Models\RefundDue;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/*
 * Repairs for sales voided and deleted before stage 10 (step 7): an old void's
 * stock is given back once, with no money recorded; sale movements whose sale
 * was hard-deleted are listed for correcting by hand.
 */

beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->ledger->receive($this->shop->id, $this->variation, 10, 40, StockMovementType::INTAKE);
    $this->sell = function (int $quantity): Sale {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'completed_at' => now(), 'payment_method' => 'cash', 'paid_amount' => 300]);
        $line = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => $quantity, 'unit_price' => 100, 'line_total' => 100 * $quantity, 'taxable_amount' => 100 * $quantity, 'status' => 'completed',
        ]);
        $this->ledger->sellItem($line, $this->variation);

        return $sale;
    };
    $this->stock = fn (): int => $this->ledger->quantity($this->shop->id, $this->variation->id);
});

it('gives an old void\'s stock back once, after a dry run, recording no money', function () {
    $sale = ($this->sell)(3);
    $sale->update(['status' => 'voided']); // voided before voids released stock: allocations kept
    expect(($this->stock)())->toBe(7);

    $this->artisan('sales:restock-voided', ['--business' => testBusiness()->id])->expectsOutputToContain('Dry run')->assertSuccessful();
    expect(($this->stock)())->toBe(7);

    $this->artisan('sales:restock-voided', ['--business' => testBusiness()->id, '--apply' => true])->assertSuccessful();
    $this->artisan('sales:restock-voided', ['--business' => testBusiness()->id, '--apply' => true])->expectsOutputToContain('No voided sale still holds stock.')->assertSuccessful();

    expect(($this->stock)())->toBe(10)
        ->and(CashPayout::count())->toBe(0)
        ->and(RefundDue::count())->toBe(0);
});

it('lists the sale movements of a hard-deleted sale, and never a soft-deleted one\'s', function () {
    $gone = ($this->sell)(2);
    $kept = ($this->sell)(1);
    $gone->items()->delete();
    $gone->forceDelete();
    $kept->delete();

    Artisan::call('stock:orphaned-movements', ['--business' => testBusiness()->id]);

    expect(Artisan::output())->toContain($gone->uuid)->not->toContain($kept->uuid)->toContain('1 movement(s)');
});
