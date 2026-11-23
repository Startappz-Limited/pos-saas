<?php

use App\Actions\CheckLowStockLevels;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductService;
use App\Services\Stock\LowStockQuery;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

/*
 * Stage 11c, step 1: one definition of low stock. LowStockReportTest covers the report itself; these pin the rules the
 * definition is built on (expired stock does not count, the shop's reorder level beats the product's, another shop's
 * stock and untracked products never appear) and that the report, the product list and the alerts agree for one shop.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->other = Shop::factory()->create(['code' => 'WES']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());

    $this->product = function (Shop $shop, string $name, int $reorderLevel, bool $tracked = true): Product {
        return Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => $name,
            'stock_quantity' => 0,
            'has_variations' => false,
            'track_stock' => $tracked,
            'reorder_level' => $reorderLevel,
        ]);
    };

    $this->lowIds = fn (Shop $shop): array => app(LowStockQuery::class)->forShop($shop)->pluck('product_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
});

it('does not count expired stock: 50 tablets of which 40 expired are low against a reorder level of 20', function () {
    $tablets = ($this->product)($this->shop, 'Tablets', 20);
    $this->ledger->receive($this->shop->id, $tablets->defaultVariation, 40, 5.0, StockMovementType::INTAKE, ['batch_number' => 'OLD', 'expiry_date' => now()->subDays(3)->toDateString()]);
    $this->ledger->receive($this->shop->id, $tablets->defaultVariation, 10, 5.0, StockMovementType::INTAKE, ['batch_number' => 'NEW', 'expiry_date' => now()->addMonths(6)->toDateString()]);

    $row = app(LowStockQuery::class)->forShop($this->shop)->firstWhere('product_id', $tablets->id);

    expect($row)->not->toBeNull()
        ->and($row['sellable'])->toBe('10.000')
        ->and($row['threshold'])->toBe('20.000')
        ->and($row['shortfall'])->toBe('10.000');
});

it('uses the shop\'s reorder level before the product\'s, in base units', function () {
    $tea = ($this->product)($this->shop, 'Tea', 10);
    $this->ledger->receive($this->shop->id, $tea->defaultVariation, 5, 5.0, StockMovementType::INTAKE);

    expect(($this->lowIds)($this->shop))->toContain($tea->id);

    // The shop's own level, 3, is below the 5 on the shelf: the product's level of 10 no longer applies here.
    DB::table('shop_variation')
        ->where('shop_id', $this->shop->id)
        ->where('variation_id', $tea->defaultVariation->id)
        ->update(['reorder_level' => 3]);

    expect(($this->lowIds)($this->shop))->not->toContain($tea->id);
});

it('never counts another shop\'s stock, and never lists an untracked product', function () {
    $biscuits = ($this->product)($this->other, 'Biscuits', 10);
    $this->ledger->receive($this->other->id, $biscuits->defaultVariation, 2, 5.0, StockMovementType::INTAKE);
    $gum = ($this->product)($this->shop, 'Gum', 10, tracked: false);

    expect(app(LowStockQuery::class)->forShop($this->other)->pluck('product_id')->all())->toContain($biscuits->id)
        ->and(($this->lowIds)($this->shop))->not->toContain($biscuits->id)
        ->and(($this->lowIds)($this->shop))->not->toContain($gum->id)
        ->and(($this->lowIds)($this->other))->not->toContain($gum->id);
});

it('gives the same list in the report, the product list and the alerts for one shop', function () {
    $this->actingAs($this->owner);

    $tea = ($this->product)($this->shop, 'Tea', 10);
    $this->ledger->receive($this->shop->id, $tea->defaultVariation, 5, 5.0, StockMovementType::INTAKE);
    $rice = ($this->product)($this->shop, 'Rice', 10);
    $this->ledger->receive($this->shop->id, $rice->defaultVariation, 50, 5.0, StockMovementType::INTAKE);

    $definition = ($this->lowIds)($this->shop);

    $productList = app(ProductService::class)->getLowStockProducts(100, $this->shop->id)
        ->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

    $alerts = app(CheckLowStockLevels::class)->execute($this->shop->id, $this->owner->id)
        ->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

    $this->get(route('stock.low-stock', ['shop_id' => $this->shop->id]))
        ->assertOk()
        ->assertSee('Tea')
        ->assertDontSee('Rice');

    expect($definition)->toBe([$tea->id])
        ->and($productList)->toBe($definition)
        ->and($alerts)->toBe($definition);
});
