<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The migration that carries legacy stock into shop_variation and drops the
 * legacy columns (stage 1b, 2026_10_15_000002).
 */
const STOCK_MOVE_MIGRATION = '2026_10_15_000002_move_stock_into_shop_variation_and_drop_legacy_columns.php';

beforeEach(function () {
    config(['database.connections.move_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    DB::purge('move_testing');
    $default = DB::getDefaultConnection();

    try {
        // Every migration before the stock move.
        $paths = collect(glob(database_path('migrations/*.php')))
            ->filter(fn (string $file): bool => basename($file) < STOCK_MOVE_MIGRATION)
            ->values()->all();
        Artisan::call('migrate', ['--database' => 'move_testing', '--path' => $paths, '--realpath' => true, '--force' => true]);
    } finally {
        DB::setDefaultConnection($default);
    }

    $this->db = DB::connection('move_testing');
});

function moveRow(string $table, array $row): int
{
    $db = DB::connection('move_testing');
    $columns = Schema::connection('move_testing')->getColumnListing($table);
    $row += array_filter([
        'uuid' => in_array('uuid', $columns, true) ? (string) Str::uuid() : null,
        'created_at' => in_array('created_at', $columns, true) ? now() : null,
        'updated_at' => in_array('updated_at', $columns, true) ? now() : null,
    ]);
    $db->table($table)->insert($row);

    return (int) ($row['id'] ?? $db->getPdo()->lastInsertId());
}

function runStockMove(): void
{
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('move_testing');

    try {
        (require database_path('migrations/'.STOCK_MOVE_MIGRATION))->up();
    } finally {
        DB::setDefaultConnection($default);
    }
}

it('carries every legacy figure into its product\'s own shop, with opening layers, then drops the columns', function () {
    moveRow('businesses', ['id' => 1, 'name' => 'Legacy']);
    moveRow('shops', ['id' => 10, 'business_id' => 1, 'name' => 'Main', 'code' => 'MAIN']);
    moveRow('shops', ['id' => 11, 'business_id' => 1, 'name' => 'Branch', 'code' => 'BRANCH']);
    moveRow('categories', ['id' => 1, 'business_id' => 1, 'name' => 'Cat', 'slug' => 'cat']);
    $product = fn (int $id, ?int $shop, int $stock, ?float $cost) => moveRow('products', [
        'id' => $id, 'business_id' => 1, 'name' => "P{$id}", 'slug' => "p{$id}", 'sku' => "P{$id}", 'category_id' => 1,
        'shop_id' => $shop, 'stock_quantity' => $stock, 'cost_price' => $cost,
    ]);
    $variation = fn (int $id, int $product, bool $default, int $stock, ?float $cost) => moveRow('product_variations', [
        'id' => $id, 'business_id' => 1, 'product_id' => $product, 'name' => "V{$id}", 'sku' => "V{$id}",
        'is_default' => $default, 'stock_quantity' => $stock, 'cost_price' => $cost,
    ]);

    // P1: own shop, 10 at 20. P2: no shop of its own, listed in the branch, 6.
    // P3: a variation holding 5, and the product itself below zero (-2).
    $product(1, 10, 10, 20);
    $variation(101, 1, true, 0, 20);
    $product(2, null, 6, 8);
    $variation(102, 2, true, 0, null);
    moveRow('shop_product', ['shop_id' => 11, 'product_id' => 2, 'stock_quantity' => 99]);
    $product(3, 10, -2, 12);
    $variation(103, 3, true, 0, 12);
    $variation(104, 3, false, 5, 30);

    runStockMove();

    $stock = $this->db->table('shop_variation')->orderBy('variation_id')->get()
        ->map(fn ($row) => [(int) $row->shop_id, (int) $row->variation_id, (int) $row->stock_quantity])->all();
    $layers = $this->db->table('cost_layers')->orderBy('variation_id')->get()
        ->map(fn ($layer) => [(int) $layer->variation_id, (float) $layer->unit_cost, (int) $layer->remaining_quantity])->all();
    $shortfall = $this->db->table('cost_allocations')->sole();

    expect($stock)->toBe([[10, 101, 10], [11, 102, 6], [10, 103, -2], [10, 104, 5]])
        // A default variation without a cost takes its product's.
        ->and($layers)->toBe([[101, 20.0, 10], [102, 8.0, 6], [104, 30.0, 5]])
        ->and([(int) $shortfall->variation_id, (int) $shortfall->quantity_allocated, (bool) $shortfall->is_provisional])->toBe([103, 2, true])
        ->and(Schema::connection('move_testing')->hasColumn('products', 'stock_quantity'))->toBeFalse()
        ->and(Schema::connection('move_testing')->hasColumn('product_variations', 'stock_quantity'))->toBeFalse()
        ->and(Schema::connection('move_testing')->hasColumn('shop_product', 'stock_quantity'))->toBeFalse();
});

it('gives a product that predates default variations one before moving its stock', function () {
    moveRow('businesses', ['id' => 1, 'name' => 'Legacy']);
    moveRow('shops', ['id' => 10, 'business_id' => 1, 'name' => 'Main', 'code' => 'MAIN']);
    moveRow('categories', ['id' => 1, 'business_id' => 1, 'name' => 'Cat', 'slug' => 'cat']);
    moveRow('products', ['id' => 1, 'business_id' => 1, 'name' => 'Old', 'slug' => 'old', 'sku' => 'OLD', 'category_id' => 1, 'shop_id' => 10, 'stock_quantity' => 4, 'cost_price' => 5]);

    runStockMove();

    $default = $this->db->table('product_variations')->where('product_id', 1)->sole();
    expect((bool) $default->is_default)->toBeTrue()
        ->and((int) $this->db->table('shop_variation')->where('variation_id', $default->id)->value('stock_quantity'))->toBe(4);
});
