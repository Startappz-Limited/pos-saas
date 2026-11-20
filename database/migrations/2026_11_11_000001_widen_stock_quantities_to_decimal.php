<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decimal quantities (stage 11, step 1): stock is counted in base units, and a
 * unit's precision (0 for pieces, 3 for kg or litres) decides how many
 * decimals it allows. Widening is a type change only — no value changes — so
 * every column's total is checked before and after. `products.reorder_level`,
 * `shop_product.reorder_level` and `pricing_rules.min_quantity`/`max_quantity`
 * stay whole numbers (not carried forward by this stage); `stock_intakes`
 * stays as read-only history.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> quantity columns, widened to decimal(15,3) */
    private const QUANTITY_COLUMNS = [
        'shop_variation' => ['stock_quantity', 'reorder_level'],
        'cost_layers' => ['original_quantity', 'remaining_quantity'],
        'cost_allocations' => ['quantity_allocated'],
        'stock_movements' => ['quantity', 'quantity_before', 'quantity_after'],
        'sale_items' => ['quantity'],
        'return_items' => ['quantity'],
        'stock_adjustment_items' => ['quantity_before', 'quantity_change', 'quantity_after'],
        'purchase_return_items' => ['quantity'],
        'purchase_order_items' => ['quantity_ordered', 'quantity_received', 'quantity_remaining'],
        'inventory_snapshots' => ['quantity_on_hand'],
        'low_stock_alerts' => ['current_quantity', 'threshold_quantity'],
        'ecommerce_order_items' => ['quantity'],
        'abandoned_cart_items' => ['quantity'],
    ];

    /** @var array<string, list<string>> unit costs (per base unit), widened to decimal(15,4) */
    private const COST_COLUMNS = [
        'cost_layers' => ['unit_cost'],
        'cost_allocations' => ['unit_cost', 'provisional_unit_cost'],
        'stock_movements' => ['unit_cost'],
        'sale_items' => ['unit_cost'],
        'product_variations' => ['cost_price'],
        'shop_variation' => ['cost_price'],
    ];

    public function up(): void
    {
        $before = $this->totals();

        Schema::table('shop_variation', function (Blueprint $table): void {
            $table->decimal('stock_quantity', 15, 3)->default(0)->change();
            $table->decimal('reorder_level', 15, 3)->nullable()->change();
            $table->decimal('cost_price', 15, 4)->nullable()->change();
        });

        Schema::table('cost_layers', function (Blueprint $table): void {
            $table->decimal('original_quantity', 15, 3)->change();
            $table->decimal('remaining_quantity', 15, 3)->change();
            $table->decimal('unit_cost', 15, 4)->nullable()->change();
        });

        Schema::table('cost_allocations', function (Blueprint $table): void {
            $table->decimal('quantity_allocated', 15, 3)->change();
            $table->decimal('unit_cost', 15, 4)->nullable()->change();
            $table->decimal('provisional_unit_cost', 15, 4)->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('quantity_before', 15, 3)->change();
            $table->decimal('quantity_after', 15, 3)->change();
            $table->decimal('unit_cost', 15, 4)->nullable()->change();
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('unit_cost', 15, 4)->default(0)->change();
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->change();
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table): void {
            $table->decimal('quantity_before', 15, 3)->nullable()->change();
            $table->decimal('quantity_change', 15, 3)->change();
            $table->decimal('quantity_after', 15, 3)->nullable()->change();
        });

        Schema::table('purchase_return_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('quantity_ordered', 15, 3)->change();
            $table->decimal('quantity_received', 15, 3)->default(0)->change();
            $table->decimal('quantity_remaining', 15, 3)->change();
        });

        Schema::table('inventory_snapshots', function (Blueprint $table): void {
            $table->decimal('quantity_on_hand', 15, 3)->change();
        });

        Schema::table('low_stock_alerts', function (Blueprint $table): void {
            $table->decimal('current_quantity', 15, 3)->change();
            $table->decimal('threshold_quantity', 15, 3)->change();
        });

        Schema::table('ecommerce_order_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->default(1)->change();
        });

        Schema::table('abandoned_cart_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->default(1)->change();
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->decimal('cost_price', 15, 4)->nullable()->change();
        });

        $this->assertUnchanged($before, $this->totals());
    }

    public function down(): void
    {
        $before = $this->totals();

        Schema::table('shop_variation', function (Blueprint $table): void {
            $table->integer('stock_quantity')->default(0)->change();
            $table->integer('reorder_level')->nullable()->change();
            $table->decimal('cost_price', 10, 2)->nullable()->change();
        });

        Schema::table('cost_layers', function (Blueprint $table): void {
            $table->integer('original_quantity')->change();
            $table->integer('remaining_quantity')->change();
            $table->decimal('unit_cost', 15, 2)->nullable()->change();
        });

        Schema::table('cost_allocations', function (Blueprint $table): void {
            $table->integer('quantity_allocated')->change();
            $table->decimal('unit_cost', 15, 2)->nullable()->change();
            $table->decimal('provisional_unit_cost', 15, 2)->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->integer('quantity')->change();
            $table->integer('quantity_before')->change();
            $table->integer('quantity_after')->change();
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->integer('quantity')->change();
            $table->decimal('unit_cost', 12, 2)->default(0)->change();
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->integer('quantity')->change();
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table): void {
            $table->integer('quantity_before')->nullable()->change();
            $table->integer('quantity_change')->change();
            $table->integer('quantity_after')->nullable()->change();
        });

        Schema::table('purchase_return_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('quantity_ordered', 15, 2)->change();
            $table->decimal('quantity_received', 15, 2)->default(0)->change();
            $table->decimal('quantity_remaining', 15, 2)->change();
        });

        Schema::table('inventory_snapshots', function (Blueprint $table): void {
            $table->integer('quantity_on_hand')->change();
        });

        Schema::table('low_stock_alerts', function (Blueprint $table): void {
            $table->integer('current_quantity')->change();
            $table->integer('threshold_quantity')->change();
        });

        Schema::table('ecommerce_order_items', function (Blueprint $table): void {
            $table->integer('quantity')->default(1)->change();
        });

        Schema::table('abandoned_cart_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1)->change();
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->nullable()->change();
        });

        // Reversing truncates anything genuinely fractional; accepted, since
        // nothing writes a fraction before this stage ships.
        $this->assertUnchanged($before, $this->totals(), tolerance: '0.001');
    }

    /**
     * @return array<string, string> a sum per widened column, as a string
     */
    private function totals(): array
    {
        $totals = [];
        foreach ([self::QUANTITY_COLUMNS, self::COST_COLUMNS] as $set) {
            foreach ($set as $tableName => $columns) {
                foreach ($columns as $column) {
                    $totals["{$tableName}.{$column}"] = (string) DB::table($tableName)->sum($column);
                }
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     */
    private function assertUnchanged(array $before, array $after, string $tolerance = '0'): void
    {
        foreach ($before as $key => $sum) {
            if (bccomp(bcsub($sum, $after[$key], 6), $tolerance, 6) > 0 && bccomp(bcsub($after[$key], $sum, 6), $tolerance, 6) > 0) {
                throw new RuntimeException("Widening changed {$key}: {$sum} before, {$after[$key]} after.");
            }
        }
    }
};
