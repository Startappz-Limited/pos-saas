<?php

use App\Enums\ExpenseCategoryType;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    Permission::create(['name' => 'reports.view']);
    Permission::create(['name' => 'reports.full-access']);
    Permission::create(['name' => 'reports.export']);
});

test('reports index requires permission', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    actingAs($user);

    get(route('reports.index'))
        ->assertForbidden();
});

test('reports index returns shop filtered profitability with wholesale metrics', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $shopA = Shop::factory()->create(['name' => 'Central']);
    $shopB = Shop::factory()->create(['name' => 'West']);

    $user->givePermissionTo(['reports.view', 'reports.full-access']);
    actingAs($user);

    $wholesaleCustomer = Customer::create([
        'uuid' => fake()->uuid(),
        'shop_id' => $shopA->id,
        'code' => 'CUS-WH-001',
        'name' => 'Wholesale Buyer',
        'customer_type' => 'wholesale',
        'status' => 'active',
    ]);

    $retailCustomer = Customer::create([
        'uuid' => fake()->uuid(),
        'shop_id' => $shopA->id,
        'code' => 'CUS-RT-001',
        'name' => 'Retail Buyer',
        'customer_type' => 'retail',
        'status' => 'active',
    ]);

    $category = ExpenseCategory::create([
        'name' => 'Operations',
        'type' => ExpenseCategoryType::OPERATIONAL,
        'created_by' => $user->id,
    ]);

    $sale1 = Sale::factory()->create([
        'shop_id' => $shopA->id,
        'customer_id' => $wholesaleCustomer->id,
        'sale_type' => 'wholesale',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 1000,
        'total_cost' => 700,
        'total_profit' => 300,
        'created_at' => now()->subDays(2),
    ]);

    // Reports attribute revenue per line via sale_items.shop_id, so each sale needs
    // a line owned by the shop being filtered on.
    SaleItem::factory()
        ->forShop($shopA)
        ->withTotals(1000, 700)
        ->create(['sale_id' => $sale1->id]);

    $sale2 = Sale::factory()->create([
        'shop_id' => $shopA->id,
        'customer_id' => $retailCustomer->id,
        'sale_type' => 'regular',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 500,
        'total_cost' => 350,
        'total_profit' => 150,
        'created_at' => now()->subDay(),
    ]);

    SaleItem::factory()
        ->forShop($shopA)
        ->withTotals(500, 350)
        ->create(['sale_id' => $sale2->id]);

    $sale3 = Sale::factory()->create([
        'shop_id' => $shopB->id,
        'sale_type' => 'regular',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 400,
        'total_cost' => 300,
        'total_profit' => 100,
        'created_at' => now()->subDay(),
    ]);

    SaleItem::factory()
        ->forShop($shopB)
        ->withTotals(400, 300)
        ->create(['sale_id' => $sale3->id]);

    Expense::create([
        'shop_id' => $shopA->id,
        'category_id' => $category->id,
        'title' => 'Rent',
        'amount' => 120,
        'expense_date' => now()->toDateString(),
        'status' => 'paid',
        'is_paid' => true,
        'created_by' => $user->id,
    ]);

    Expense::create([
        'shop_id' => $shopB->id,
        'category_id' => $category->id,
        'title' => 'Utilities',
        'amount' => 500,
        'expense_date' => now()->toDateString(),
        'status' => 'paid',
        'is_paid' => true,
        'created_by' => $user->id,
    ]);

    expect((float) Sale::query()->where('shop_id', $shopA->id)->sum('total_amount'))->toBe(1500.0);
    expect((float) Expense::query()->where('shop_id', $shopA->id)->sum('amount'))->toBe(120.0);
    expect(Expense::query()->where('shop_id', $shopA->id)->first()?->getRawOriginal('status'))->toBe('paid');

    $response = get(route('reports.index', [
        'start_date' => now()->subDays(7)->toDateString(),
        'end_date' => now()->toDateString(),
        'shop_id' => $shopA->id,
        'sale_status' => 'completed',
        'expense_status' => 'paid',
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');

    expect($reportData['summary']['total_revenue'])->toBe(1500.0)
        ->and($reportData['summary']['gross_profit'])->toBe(450.0)
        ->and($reportData['summary']['expense_total'])->toBe(120.0)
        ->and($reportData['summary']['net_profit'])->toBe(330.0)
        ->and($reportData['wholesale']['revenue'])->toBe(1000.0)
        ->and($reportData['wholesale']['gross_profit'])->toBe(300.0)
        ->and($reportData['retail']['revenue'])->toBe(500.0);
});

test('reports index filters by customer segment wholesale', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $shop = Shop::factory()->create();

    $user->givePermissionTo(['reports.view', 'reports.full-access']);
    actingAs($user);

    $wholesaleCustomer = Customer::create([
        'uuid' => fake()->uuid(),
        'shop_id' => $shop->id,
        'code' => 'CUS-WH-100',
        'name' => 'Segment Wholesale',
        'customer_type' => 'wholesale',
        'status' => 'active',
    ]);

    $retailCustomer = Customer::create([
        'uuid' => fake()->uuid(),
        'shop_id' => $shop->id,
        'code' => 'CUS-RT-200',
        'name' => 'Segment Retail',
        'customer_type' => 'retail',
        'status' => 'active',
    ]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $wholesaleCustomer->id,
        'sale_type' => 'regular',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 300,
        'total_cost' => 220,
        'total_profit' => 80,
        'created_at' => now()->subDay(),
    ]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $retailCustomer->id,
        'sale_type' => 'regular',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 700,
        'total_cost' => 500,
        'total_profit' => 200,
        'created_at' => now()->subDay(),
    ]);

    expect((float) Sale::query()->sum('total_amount'))->toBe(1000.0);

    $response = get(route('reports.index', [
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->toDateString(),
        'customer_segment' => 'wholesale',
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');

    expect($reportData['summary']['total_revenue'])->toBe(300.0)
        ->and($reportData['summary']['total_transactions'])->toBe(1)
        ->and($reportData['wholesale']['transactions'])->toBe(1);
});

test('reports export csv downloads with current filters', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $shop = Shop::factory()->create();

    $user->givePermissionTo(['reports.view', 'reports.export', 'reports.full-access']);
    actingAs($user);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 220,
        'total_cost' => 120,
        'total_profit' => 100,
        'created_at' => now()->subDay(),
    ]);

    $response = post(route('reports.export', 'dashboard'), [
        'format' => 'csv',
        'start_date' => now()->subDays(7)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('text/csv')
        ->and((string) $response->headers->get('content-disposition'))->toContain('.csv')
        ->and($response->streamedContent())->toContain('Detailed Reports Export')
        ->and($response->streamedContent())->toContain('Summary');
});

test('reports export pdf downloads with current filters', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $user->givePermissionTo(['reports.view', 'reports.export', 'reports.full-access']);
    actingAs($user);

    $response = post(route('reports.export', 'dashboard'), [
        'format' => 'pdf',
        'start_date' => now()->subDays(7)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('application/pdf')
        ->and((string) $response->headers->get('content-disposition'))->toContain('.pdf');
});
