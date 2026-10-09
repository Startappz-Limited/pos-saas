<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;

/**
 * The "Net Profit" tile used to compute revenue - expenses, reporting the whole
 * cost of goods as profit. On live data that turned a 12,750 month into a
 * 611,544 "gain". These pin the deduction in both query branches.
 */
test('the dashboard net profit tile deducts cost of goods', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$shop->id]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'status' => 'completed',
        'total_amount' => 1000,
        'total_cost' => 600,
        'total_profit' => 400,
    ]);

    Expense::factory()->create([
        'shop_id' => $shop->id,
        'category_id' => ExpenseCategory::factory()->create()->id,
        'amount' => 150,
        'expense_date' => now(),
    ]);

    $statistics = $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->viewData('statistics');

    expect((float) $statistics['total_sales'])->toBe(1000.0)
        ->and((float) $statistics['total_cost'])->toBe(600.0)
        ->and((float) $statistics['gross_profit'])->toBe(400.0)
        ->and((float) $statistics['total_expenses'])->toBe(150.0)
        ->and((float) $statistics['profit'])->toBe(250.0);
});

test('the shop-filtered dashboard net profit tile deducts cost of goods', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$shop->id]);

    $product = Product::factory()->create();
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'status' => 'completed',
        'total_amount' => 1000,
        'total_cost' => 600,
        'total_profit' => 400,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 200,
        'unit_cost' => 120,
        'line_total' => 600,
    ]);

    Expense::factory()->create([
        'shop_id' => $shop->id,
        'category_id' => ExpenseCategory::factory()->create()->id,
        'amount' => 100,
        'expense_date' => now(),
    ]);

    $statistics = $this->actingAs($user)->get(route('dashboard', ['shop_id' => $shop->id]))
        ->assertSuccessful()
        ->viewData('statistics');

    // 600 of line_total against 3 x 120 of cost, less 100 of expenses.
    expect((float) $statistics['total_sales'])->toBe(600.0)
        ->and((float) $statistics['total_cost'])->toBe(360.0)
        ->and((float) $statistics['gross_profit'])->toBe(240.0)
        ->and((float) $statistics['profit'])->toBe(140.0);
});

test('a trading loss is reported as a loss rather than as revenue', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$shop->id]);

    // The live shape of the imported-cost problem: cost above selling price.
    Sale::factory()->create([
        'shop_id' => $shop->id,
        'status' => 'completed',
        'total_amount' => 1000,
        'total_cost' => 1400,
        'total_profit' => -400,
    ]);

    $statistics = $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->viewData('statistics');

    expect((float) $statistics['profit'])->toBeLessThan(0.0)
        ->and((float) $statistics['profit'])->toBe(-400.0);
});
