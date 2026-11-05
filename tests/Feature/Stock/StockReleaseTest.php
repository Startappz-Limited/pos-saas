<?php

use App\Enums\StockMovementType;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Till;

/*
 * StockLedger::release() (stage 10, step 3): one method gives back everything
 * a consumer took, into the batches it came from; provisional units come back
 * with no batch; waiting below-zero sales settle against what is given back.
 */

beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => 40, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->line = function (int $quantity, float $price = 100): SaleItem {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'total_amount' => $price * $quantity, 'tax_amount' => 0]);

        return SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $price * $quantity, 'taxable_amount' => $price * $quantity, 'status' => 'completed',
        ]);
    };
    $this->receive = fn (int $quantity, ?float $cost, string $at = '-1 day') => $this->ledger->receive(
        $this->shop->id, $this->variation, $quantity, $cost, StockMovementType::INTAKE, ['received_at' => now()->modify($at)],
    );
    $this->layers = fn (): array => DB::table('cost_layers')->where('variation_id', $this->variation->id)->orderBy('id')
        ->get(['source_type', 'unit_cost', 'remaining_quantity'])
        ->map(fn ($layer) => [$layer->source_type, $layer->unit_cost === null ? null : (float) $layer->unit_cost, (int) $layer->remaining_quantity])->all();
    $this->holds = function (): void {
        $stock = $this->ledger->quantity($this->shop->id, $this->variation->id);
        $remaining = (int) DB::table('cost_layers')->where('shop_id', $this->shop->id)->where('variation_id', $this->variation->id)->sum('remaining_quantity');
        $provisional = (int) DB::table('cost_allocations')->where('shop_id', $this->shop->id)->where('variation_id', $this->variation->id)->where('is_provisional', true)->sum('quantity_allocated');
        expect($remaining)->toBe(max($stock, 0))->and($provisional)->toBe(max(-$stock, 0));
    };
});

it('puts a line taken from two batches back into both', function () {
    ($this->receive)(3, 10, '-3 days');
    ($this->receive)(5, 20, '-1 day');
    $line = ($this->line)(6);
    $this->ledger->sellItem($line, $this->variation);
    expect(($this->layers)())->toBe([['intake', 10.0, 0], ['intake', 20.0, 2]]);

    $change = $this->ledger->release($line, StockMovementType::SALE_VOID);

    expect(($this->layers)())->toBe([['intake', 10.0, 3], ['intake', 20.0, 5]])
        ->and($change->quantity)->toBe(6)
        ->and($change->movement->movement_type)->toBe(StockMovementType::SALE_VOID)
        ->and((float) $change->movement->unit_cost)->toBe(round((3 * 10 + 3 * 20) / 6, 2))
        ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(8)
        ->and(DB::table('cost_allocations')->where('sale_item_id', $line->id)->count())->toBe(0);
    ($this->holds)();
});

it('releases a provisional line with no batch', function () {
    $line = ($this->line)(4);
    $this->ledger->sellItem($line, $this->variation);
    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(-4);

    $this->ledger->release($line, StockMovementType::SALE_VOID);

    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(0)
        ->and(($this->layers)())->toBe([]);
    ($this->holds)();
});

it('releases a line that was part batch, part provisional', function () {
    ($this->receive)(2, 10);
    $line = ($this->line)(5);
    $this->ledger->sellItem($line, $this->variation);

    $this->ledger->release($line, StockMovementType::SALE_DELETE);

    expect(($this->layers)())->toBe([['intake', 10.0, 2]])
        ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(2);
    ($this->holds)();
});

it('releases an adjustment line and a supplier-return line through the same method', function () {
    ($this->receive)(10, 10);
    $user = User::factory()->create();
    $adjustment = StockAdjustment::factory()->create(['shop_id' => $this->shop->id, 'type' => 'decrease', 'reason' => 'damaged', 'status' => 'completed', 'created_by' => $user->id]);
    $adjustmentLine = StockAdjustmentItem::create(['stock_adjustment_id' => $adjustment->id, 'product_id' => $this->product->id, 'variation_id' => $this->variation->id, 'shop_id' => $this->shop->id, 'quantity_change' => -3]);
    $this->ledger->consume($this->shop->id, $this->variation, 3, StockMovementType::ADJUSTMENT_REMOVE, $adjustmentLine);

    $return = PurchaseReturn::factory()->create(['shop_id' => $this->shop->id, 'supplier_id' => Supplier::factory()->create()->id, 'requested_by' => $user->id]);
    $returnLine = PurchaseReturnItem::factory()->create(['purchase_return_id' => $return->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 10, 'line_total' => 20]);
    $this->ledger->consume($this->shop->id, $this->variation, 2, StockMovementType::SUPPLIER_RETURN, $returnLine);
    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(5);

    $this->ledger->release($adjustmentLine, StockMovementType::SALE_VOID);
    $this->ledger->release($returnLine, StockMovementType::SALE_VOID);

    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(10)
        ->and(($this->layers)())->toBe([['intake', 10.0, 10]]);
    ($this->holds)();
});

it('changes nothing when released twice', function () {
    ($this->receive)(5, 10);
    $line = ($this->line)(3);
    $this->ledger->sellItem($line, $this->variation);
    $this->ledger->release($line, StockMovementType::SALE_VOID);
    $movements = DB::table('stock_movements')->count();

    expect($this->ledger->release($line, StockMovementType::SALE_VOID))->toBeNull()
        ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(5)
        ->and(DB::table('stock_movements')->count())->toBe($movements);
    ($this->holds)();
});

it('gives back into a new release batch, at the units\' cost, when their batch is gone', function () {
    ($this->receive)(5, 10);
    $line = ($this->line)(2);
    $this->ledger->sellItem($line, $this->variation);
    DB::table('cost_allocations')->where('sale_item_id', $line->id)->update(['cost_layer_id' => null, 'is_provisional' => false]);
    DB::table('cost_layers')->where('variation_id', $this->variation->id)->update(['remaining_quantity' => 3]);

    $this->ledger->release($line, StockMovementType::SALE_VOID);

    expect(($this->layers)())->toBe([['intake', 10.0, 3], [CostLayer::SOURCE_RELEASE, 10.0, 2]]);
    ($this->holds)();
});

it('settles a below-zero sale against the units a void gives back, and re-costs it', function () {
    ($this->receive)(5, 10);
    $first = ($this->line)(5);
    $this->ledger->sellItem($first, $this->variation);
    $second = ($this->line)(2);
    $this->ledger->sellItem($second, $this->variation);
    expect(DB::table('cost_allocations')->where('sale_item_id', $second->id)->value('is_provisional'))->toBeTruthy();

    $this->ledger->release($first, StockMovementType::SALE_VOID);

    $allocation = DB::table('cost_allocations')->where('sale_item_id', $second->id)->sole();
    expect((bool) $allocation->is_provisional)->toBeFalse()
        ->and((float) $allocation->unit_cost)->toBe(10.0)
        ->and((float) DB::table('sale_items')->where('id', $second->id)->value('total_cost'))->toBe(20.0)
        ->and((float) DB::table('sales')->where('id', $second->sale_id)->value('total_cost'))->toBe(20.0)
        ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(3)
        ->and(($this->layers)())->toBe([['intake', 10.0, 3]]);
    ($this->holds)();
});

it('moves the stock row on, so a till\'s next pull carries the restored stock', function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-04 09:00:00'));
    ($this->receive)(5, 10);
    $line = ($this->line)(3);
    $this->ledger->sellItem($line, $this->variation);

    $owner = User::factory()->create();
    $owner->assignRole(ownerRole());
    $register = Register::defaultFor($this->shop->id);
    app(TenantContext::class)->forget();
    $token = Till::token($owner, $register);
    $this->travel(2)->seconds();
    Till::freshRequest();
    $cursor = $this->withToken($token)->getJson('/api/v1/sync/pull')->assertOk()->json('meta.cursor');

    $this->travel(5)->seconds();
    app(TenantContext::class)->run(testBusiness(), fn () => $this->ledger->release($line, StockMovementType::SALE_VOID));
    $this->travel(2)->seconds();
    Till::freshRequest();
    $stock = collect($this->withToken($token)->getJson('/api/v1/sync/pull?since='.urlencode($cursor))->assertOk()->json('data.stock'));

    expect($stock->firstWhere('product_uuid', $this->product->uuid)['quantity'] ?? null)->toBe(5);
});
