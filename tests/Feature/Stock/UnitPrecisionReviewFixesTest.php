<?php

use App\Enums\ReturnReason;
use App\Enums\StockIntakeStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockIntake;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\Stock\StockLedger;
use App\Support\Stock\BaseQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Regressions from the precision review of the stock-units branch: a converted quantity must fit the base
 * unit's precision; editing an intake keeps creation's quantity rules; partial returns add up to the line's
 * base quantity; fractional stock reads as a fraction; the web sale form offers units; a new sale refuses a
 * disabled or deleted unit; and a unit on a pending adjustment keeps its factor.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->ledger = app(StockLedger::class);
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');

    // Tablets are whole; a "half" pack has a factor of 0.5.
    $this->tablets = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'tablet', 'selling_price' => 10, 'stock_quantity' => 0, 'has_variations' => false]);
    $this->tablets->defaultVariation->baseUnit()->update(['precision' => 0]);
    $this->half = ProductUnit::factory()->create(['variation_id' => $this->tablets->defaultVariation->id, 'name' => 'Half', 'factor' => '0.500', 'precision' => 0, 'can_sell' => true]);
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 100, 2, StockMovementType::INTAKE);

    $this->salePayload = fn (Product $product, ?string $unitUuid, float $quantity, float $price = 10): array => [
        'shop_id' => $this->shop->id,
        'source_id' => $this->source,
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [array_filter(['product_id' => $product->id, 'unit_id' => $unitUuid, 'quantity' => $quantity, 'price' => $price], fn ($value) => $value !== null)],
    ];
});

it('converts to base units only at the base unit precision', function () {
    expect(BaseQuantity::of('1', '0.5', 0))->toBeNull()
        ->and(BaseQuantity::of('2', '0.5', 0))->toBe('1.000')
        ->and(BaseQuantity::of('1', '0.5', 3))->toBe('0.500')
        ->and(BaseQuantity::of('1', '0.25', 1))->toBeNull()
        ->and(BaseQuantity::of('-3', '0.5', 1))->toBe('-1.500');
});

it('refuses selling one half-tablet pack of a whole-tablet product, and sells two', function () {
    $this->actingAs($this->owner)->post(route('sales.store'), ($this->salePayload)($this->tablets, $this->half->uuid, 1))
        ->assertSessionHasErrors('items.0.quantity');
    expect(Sale::query()->count())->toBe(0);

    $this->actingAs($this->owner)->post(route('sales.store'), ($this->salePayload)($this->tablets, $this->half->uuid, 2))
        ->assertSessionHasNoErrors();
    expect((string) SaleItem::query()->sole()->base_quantity)->toBe('1.000')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->tablets->defaultVariation->id))->toBe('99.000');
});

it('refuses an adjustment of half a whole tablet', function () {
    $this->actingAs($this->owner)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => 'damaged',
        'items' => [['product_id' => $this->tablets->id, 'quantity_change' => -1, 'unit_id' => $this->half->uuid]],
    ])->assertSessionHasErrors('items.0.quantity_change');

    expect(StockAdjustment::query()->count())->toBe(0);
});

it('applies the create rules when an intake is edited, and rechecks them on completion', function () {
    $intake = StockIntake::factory()->pending()->create([
        'purchase_order_id' => null, 'purchase_order_item_id' => null,
        'shop_id' => $this->shop->id, 'product_id' => $this->tablets->id,
        'quantity_received' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0,
    ]);

    // A fraction of a whole tablet is refused on edit, as it is on create.
    $this->actingAs($this->owner)->put(route('stock-intakes.update', $intake), ['quantity_accepted' => 2.5])
        ->assertSessionHasErrors('quantity_accepted');
    // So is accepting more than was received, judged against the stored received figure.
    $this->actingAs($this->owner)->put(route('stock-intakes.update', $intake), ['quantity_accepted' => 6])
        ->assertSessionHasErrors('quantity_accepted');
    expect((string) $intake->fresh()->quantity_accepted)->toBe('5.000');

    // The smallest figure creation accepts (0.001) is accepted on edit too, for a weight product.
    $kg = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg', 'stock_quantity' => 0, 'has_variations' => false]);
    $kg->defaultVariation->baseUnit()->update(['precision' => 3]);
    $weighed = StockIntake::factory()->pending()->create([
        'purchase_order_id' => null, 'purchase_order_item_id' => null,
        'shop_id' => $this->shop->id, 'product_id' => $kg->id,
        'quantity_received' => 1, 'quantity_accepted' => 1, 'quantity_rejected' => 0,
    ]);
    $this->actingAs($this->owner)->put(route('stock-intakes.update', $weighed), ['quantity_received' => 0.001, 'quantity_accepted' => 0.001])
        ->assertSessionHasNoErrors();

    // An intake already holding a fraction (saved before these checks) is refused at completion.
    DB::table('stock_intakes')->where('id', $intake->id)->update(['quantity_received' => 2.5, 'quantity_accepted' => 2.5]);
    expect(fn () => $this->actingAs($this->owner)->withoutExceptionHandling()->post(route('stock-intakes.complete', $intake)))
        ->toThrow(BusinessRuleException::class);
    expect($intake->fresh()->status)->toBe(StockIntakeStatus::PENDING)
        ->and($this->ledger->exactQuantity($this->shop->id, $this->tablets->defaultVariation->id))->toBe('100.000');
});

it('restocks repeated partial returns up to exactly the line base quantity', function () {
    $kg = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg', 'stock_quantity' => 0, 'has_variations' => false]);
    $kg->defaultVariation->baseUnit()->update(['precision' => 3]);
    // A synced line of three units whose recorded base quantity is 1.000.
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'customer_id' => null]);
    $saleItem = SaleItem::create([
        'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $kg->id,
        'variation_id' => $kg->defaultVariation->id, 'unit_id' => null,
        'quantity' => '3.000', 'base_quantity' => '1.000', 'unit_price' => 10, 'line_total' => 30,
        'unit_cost' => '5.0000', 'total_cost' => 5, 'status' => 'completed',
    ]);

    $bases = collect(range(1, 3))->map(fn () => (string) app(ReturnService::class)->createReturn([
        'sale_id' => $sale->id, 'reason' => ReturnReason::CUSTOMER_CHANGED_MIND->value,
        'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
    ], $this->owner->id)->items->sole()->base_quantity);

    expect($bases->all())->toBe(['0.333', '0.333', '0.334']);
});

it('refuses a partial return that would put back nothing', function () {
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'customer_id' => null]);
    // 1000 units sold as 0.001 base: one unit returned is 0.000001, which the ledger can't hold.
    $saleItem = SaleItem::create([
        'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->tablets->id,
        'variation_id' => $this->tablets->defaultVariation->id, 'unit_id' => null,
        'quantity' => '1000.000', 'base_quantity' => '0.001', 'unit_price' => 1, 'line_total' => 1000,
        'unit_cost' => '5.0000', 'total_cost' => 0.01, 'status' => 'completed',
    ]);

    expect(fn () => app(ReturnService::class)->createReturn([
        'sale_id' => $sale->id, 'reason' => ReturnReason::CUSTOMER_CHANGED_MIND->value,
        'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
    ], $this->owner->id))->toThrow(BusinessRuleException::class);
});

it('reads fractional stock as a fraction, not zero', function () {
    $kg = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg', 'stock_quantity' => 0, 'has_variations' => false]);
    $kg->defaultVariation->baseUnit()->update(['precision' => 3]);
    $this->ledger->receive($this->shop->id, $kg->defaultVariation, '0.750', 100, StockMovementType::INTAKE);
    $this->actingAs($this->owner);

    expect($kg->fresh()->stock_quantity)->toBe('0.750')
        ->and(Product::query()->withStockOnHand()->findOrFail($kg->id)->stock_quantity)->toBe('0.750')
        ->and($kg->fresh()->stockFigure())->toBe('0.75')
        ->and($this->tablets->fresh()->stockFigure())->toBe('100');

    // The flat API keeps a JSON number: a decimal for a fraction, an integer when whole.
    Sanctum::actingAs($this->owner, ['*']);
    expect($this->getJson("/api/products/{$kg->getRouteKey()}")->assertOk()->json('data.stock_quantity'))->toBe(0.75)
        ->and($this->getJson("/api/products/{$this->tablets->getRouteKey()}")->assertOk()->json('data.stock_quantity'))->toBe(100);
});

it('offers each product its sellable units on the web sale form', function () {
    $stopped = ProductUnit::factory()->create(['variation_id' => $this->tablets->defaultVariation->id, 'name' => 'Old strip', 'factor' => '10.000', 'precision' => 0, 'can_sell' => false]);

    $response = $this->actingAs($this->owner)->get(route('sales.create', ['shop_id' => $this->shop->id]))->assertOk();

    $response->assertSee('items[0][unit_id]', false)
        ->assertSee($this->half->uuid, false)
        ->assertDontSee($stopped->uuid, false)
        ->assertSee('data-default-variation="'.$this->tablets->defaultVariation->id.'"', false);
});

it('refuses a new sale in a disabled or deleted unit, but records a till push in one', function () {
    $strip = ProductUnit::factory()->create(['variation_id' => $this->tablets->defaultVariation->id, 'name' => 'Strip', 'factor' => '10.000', 'precision' => 0, 'can_sell' => true]);
    Sanctum::actingAs($this->owner, ['*']);

    $strip->update(['can_sell' => false]);
    $this->postJson('/api/sales', ($this->salePayload)($this->tablets, $strip->uuid, 1, 100))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.unit_id');

    $strip->update(['can_sell' => true]);
    $strip->delete();
    $this->postJson('/api/sales', ($this->salePayload)($this->tablets, $strip->uuid, 1, 100))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.unit_id');

    expect(Sale::query()->count())->toBe(0);
});

it('locks the factor of a unit named on a pending adjustment', function () {
    $crate = ProductUnit::factory()->create(['variation_id' => $this->tablets->defaultVariation->id, 'name' => 'Box of 24', 'factor' => '24.000', 'precision' => 0]);

    $this->actingAs($this->owner)->post(route('stock-adjustments.store'), [
        'shop_id' => $this->shop->id, 'reason' => 'found',
        'items' => [['product_id' => $this->tablets->id, 'quantity_change' => 1, 'unit_id' => $crate->uuid]],
    ])->assertSessionHasNoErrors();

    expect(StockAdjustment::query()->sole()->status)->toBe('pending')
        ->and(fn () => $crate->fresh()->update(['factor' => '12.000']))->toThrow(BusinessRuleException::class)
        ->and((string) $crate->fresh()->factor)->toBe('24.000');
});
