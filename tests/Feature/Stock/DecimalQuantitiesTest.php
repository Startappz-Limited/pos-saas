<?php

use App\Casts\Quantity;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Rules\QuantityForUnit;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Decimal quantities (stage 11, step 1): stock is held as scale-3 strings and
 * moved with bcmath, never a float, so repeated fractional changes never
 * drift away from the exact answer.
 */

beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => 40, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->line = function (float|string $quantity, float $price = 100): SaleItem {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'total_amount' => $price * (float) $quantity, 'tax_amount' => 0]);

        return SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $price * (float) $quantity,
            'taxable_amount' => $price * (float) $quantity, 'status' => 'completed',
        ]);
    };
});

it('moves a fractional quantity from stock, exactly', function () {
    $this->ledger->receive($this->shop->id, $this->variation, '50.000', 10, StockMovementType::INTAKE);

    $item = ($this->line)('2.500');
    $this->ledger->sellItem($item, $this->variation);

    expect($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('47.500');
});

it('never drifts across repeated fractional changes (0.1 + 0.2)', function () {
    $this->ledger->receive($this->shop->id, $this->variation, '50.000', 10, StockMovementType::INTAKE);

    $this->ledger->sellItem(($this->line)('0.100'), $this->variation);
    $this->ledger->sellItem(($this->line)('0.200'), $this->variation);

    expect($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('49.700');
});

it('receives, consumes and releases a fractional quantity, keeping layers exact', function () {
    $change = $this->ledger->receive($this->shop->id, $this->variation, '10.500', 4, StockMovementType::INTAKE);
    expect($change->quantity)->toBe('10.500');

    $item = ($this->line)('3.250');
    $sold = $this->ledger->sellItem($item, $this->variation);
    expect($sold->quantity)->toBe('3.250')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('7.250');

    $released = $this->ledger->release($item, StockMovementType::SALE_VOID);
    expect($released->quantity)->toBe('3.250')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('10.500')
        ->and(Quantity::normalize(DB::table('cost_layers')->where('variation_id', $this->variation->id)->sole()->remaining_quantity))->toBe('10.500');
});

it('settles a fractional shortfall sold below zero when stock arrives', function () {
    $item = ($this->line)('5.500');
    $this->ledger->sellItem($item, $this->variation); // below zero, at the product's fallback cost
    expect($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('-5.500');

    $this->ledger->receive($this->shop->id, $this->variation, '2.000', 25, StockMovementType::INTAKE);

    // 2.000 of the 5.500 settles at the arriving cost; 3.500 stays provisional.
    $settled = DB::table('cost_allocations')->where('sale_item_id', $item->id)->where('is_provisional', false)->sole();
    $stillProvisional = DB::table('cost_allocations')->where('sale_item_id', $item->id)->where('is_provisional', true)->sole();
    expect(Quantity::normalize($settled->quantity_allocated))->toBe('2.000')
        ->and(Quantity::normalize($stillProvisional->quantity_allocated))->toBe('3.500')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('-3.500');
});

it('refuses a fraction for a whole-unit (precision 0) quantity, and accepts one within precision', function () {
    $fails = function (int $precision, string $value): bool {
        $failed = false;
        (new QuantityForUnit($precision))->validate('quantity', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    };

    expect($fails(0, '2.5'))->toBeTrue()
        ->and($fails(0, '2'))->toBeFalse()
        ->and($fails(3, '1.250'))->toBeFalse()
        ->and($fails(3, '1.2505'))->toBeTrue()
        ->and($fails(3, '0'))->toBeTrue() // must be greater than 0
        ->and($fails(3, '-1'))->toBeTrue();
});

it('normalizes a quantity to a canonical scale-3 string regardless of the driver\'s native type', function () {
    expect(Quantity::normalize(2))->toBe('2.000')
        ->and(Quantity::normalize('2.5'))->toBe('2.500')
        ->and(Quantity::normalize(2.5))->toBe('2.500')
        // SQLite-style binary-float noise a few units past the 15th digit rounds away cleanly.
        ->and(Quantity::normalize(49.699999999999996))->toBe('49.700')
        ->and(Quantity::normalize(0, 4))->toBe('0.0000');
});
