<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\LowStockQuery;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * Stage 11c, step 1: Stock → Low stock is the one low-stock report. It reads LowStockQuery (sellable stock,
 * base units, the shop's reorder level first), lists the shortfall largest first, and turns selected rows into
 * a draft purchase order whose quantities are recomputed on the server.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->other = Shop::factory()->create(['code' => 'WES']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->supplier = Supplier::factory()->create();

    // Rice (kg, weighed): 1.5 kg sellable against a reorder level of 4 kg, so a shortfall of 2.5.
    $this->rice = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'kg', 'stock_quantity' => 0, 'has_variations' => false, 'track_stock' => true, 'reorder_level' => 4]);
    $this->rice->defaultVariation->baseUnit()->update(['precision' => 3]);
    $this->ledger->receive($this->shop->id, $this->rice->defaultVariation, '1.500', 80, StockMovementType::INTAKE);

    // Tea, fine: 50 against 10.
    $this->tea = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'track_stock' => true, 'reorder_level' => 10]);
    $this->ledger->receive($this->shop->id, $this->tea->defaultVariation, 50, 5, StockMovementType::INTAKE);

    // Biscuits at the other shop only, low there.
    $this->biscuits = Product::factory()->create(['shop_id' => $this->other->id, 'stock_quantity' => 0, 'has_variations' => false, 'track_stock' => true, 'reorder_level' => 10]);
    $this->ledger->receive($this->other->id, $this->biscuits->defaultVariation, 2, 5, StockMovementType::INTAKE);
});

it('lists the low items of the chosen shop, largest shortfall first, in base units', function () {
    $this->actingAs($this->owner)->get(route('stock.low-stock', ['shop_id' => $this->shop->id]))
        ->assertOk()
        ->assertSee($this->rice->name)
        ->assertSee('2.5')
        ->assertDontSee($this->tea->name)
        ->assertDontSee($this->biscuits->name);
});

it('shows the shop\'s own reorder level, not the product\'s, when it has one', function () {
    DB::table('shop_variation')->where('shop_id', $this->shop->id)->where('variation_id', $this->tea->defaultVariation->id)->update(['reorder_level' => 60]);

    $rows = app(LowStockQuery::class)->forShop($this->shop);

    expect($rows->pluck('product_id')->all())->toContain($this->tea->id)
        ->and($rows->firstWhere('product_id', $this->tea->id)['threshold'])->toBe('60.000');
});

it('never shows a shop the viewer cannot access', function () {
    $this->actingAs($this->owner)->get(route('stock.low-stock', ['shop_id' => $this->other->id]))->assertOk()->assertSee($this->biscuits->name)->assertDontSee($this->rice->name);
    $this->actingAs($this->owner)->get(route('stock.low-stock', ['shop_id' => $this->shop->id]))->assertOk()->assertDontSee($this->biscuits->name);

    // A manager with no shop assigned sees no shop's stock (default-deny), though the owner sees every shop.
    $stranger = User::factory()->create();
    $stranger->assignRole(sharedRole('manager'));
    $this->actingAs($stranger)->get(route('stock.low-stock', ['shop_id' => $this->shop->id]))->assertOk()->assertDontSee($this->rice->name);
});

it('refuses the page to someone without stock.view', function () {
    $clerk = User::factory()->create();

    $this->actingAs($clerk)->get(route('stock.low-stock'))->assertForbidden();
});

it('makes a draft purchase order for the chosen rows, with the shortfall as the quantity', function () {
    $this->actingAs($this->owner)->post(route('stock.low-stock.purchase-order'), [
        'shop_id' => $this->shop->id,
        'supplier_id' => $this->supplier->id,
        'lines' => ["{$this->rice->id}:{$this->rice->defaultVariation->id}"],
    ])->assertSessionHasNoErrors();

    $order = PurchaseOrder::query()->where('shop_id', $this->shop->id)->sole();
    $item = $order->items->sole();

    expect($order->status)->toBe(PurchaseOrderStatus::DRAFT)
        ->and($order->supplier_id)->toBe($this->supplier->id)
        ->and($item->product_id)->toBe($this->rice->id)
        ->and((string) $item->quantity_ordered)->toBe('2.500');
});

it('recomputes the quantity on the server, ignoring any figure the form sends', function () {
    $key = "{$this->rice->id}:{$this->rice->defaultVariation->id}";

    $this->actingAs($this->owner)->post(route('stock.low-stock.purchase-order'), [
        'shop_id' => $this->shop->id,
        'supplier_id' => $this->supplier->id,
        'lines' => [$key],
        'quantity' => 999,
    ]);

    expect((string) PurchaseOrder::query()->sole()->items->sole()->quantity_ordered)->toBe('2.500');
});

it('skips a line that is no longer low, and refuses an order with no low line left', function () {
    $this->actingAs($this->owner)->post(route('stock.low-stock.purchase-order'), [
        'shop_id' => $this->shop->id,
        'supplier_id' => $this->supplier->id,
        'lines' => ["{$this->tea->id}:{$this->tea->defaultVariation->id}"],
    ])->assertSessionHasErrors('lines');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('does not order a line of another shop\'s stock', function () {
    $this->actingAs($this->owner)->post(route('stock.low-stock.purchase-order'), [
        'shop_id' => $this->other->id,
        'supplier_id' => $this->supplier->id,
        'lines' => ["{$this->rice->id}:{$this->rice->defaultVariation->id}"],
    ])->assertSessionHasErrors('lines');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('serves the recorded inventory low-stock route in the same shape as the product list', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $inventory = $this->getJson('/api/inventory/low-stock')->assertOk()->json();
    $products = $this->getJson('/api/products/low-stock')->assertOk()->json();

    // Same rows and totals; only the pagination links name their own path.
    expect($inventory['data']['data'])->toBe($products['data']['data'])
        ->and($inventory['data']['total'])->toBe($products['data']['total'])
        ->and(collect($inventory['data']['data'])->pluck('id')->all())->toContain($this->rice->id)
        ->and(collect($inventory['data']['data'])->pluck('id')->all())->not->toContain($this->tea->id);
});

it('redirects the old product list to the Stock report', function () {
    $this->actingAs($this->owner)->get(route('products.lowStock', ['shop_id' => $this->shop->id]))
        ->assertRedirect(route('stock.low-stock', ['shop_id' => $this->shop->id]));
});
