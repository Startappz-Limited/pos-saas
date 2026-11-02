<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\ShopStatus;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryCompany;
use App\Models\EcommerceOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * The flat API's writes check the same policy or permission as the web
 * (hotfix fix/api-authz, 29 October and 2 November 2026). Before it, store,
 * update, delete and the other mutations below checked only that the person
 * could reach the shop, so any member with a token could, for example, void a
 * sale or delete a shop.
 *
 * For every write, three cases, each by a member of the shop:
 * - with no permission: refused, and the record's row (or, for a create, the
 *   table) is exactly as it was;
 * - with an unrelated permission (`reports.view`): refused the same way, so
 *   the endpoint checks a specific permission, not just "has any";
 * - with exactly the intended permission: let through (not 401, 403 or 404).
 *   That proves the check asks for that permission and no other; whether the
 *   minimal payload then succeeds or fails validation is the endpoint's own
 *   business.
 */

beforeEach(function () {
    seedRolesAndPermissions();

    $this->shop = Shop::factory()->create();
    $this->member = User::factory()->create();
    $this->member->shops()->attach($this->shop);

    $this->records = [
        'category' => Category::factory()->create(),
        'customer' => Customer::factory()->forShop($this->shop)->create(),
        'delivery' => DeliveryCompany::factory()->create(),
        'expenseCategory' => $expenseCategory = ExpenseCategory::factory()->create(),
        'expense' => Expense::factory()->create(['shop_id' => $this->shop->id, 'category_id' => $expenseCategory->id]),
        'product' => Product::factory()->create(['shop_id' => $this->shop->id]),
        'purchaseOrder' => PurchaseOrder::factory()->create(['shop_id' => $this->shop->id]),
        'sale' => Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending']),
        'supplier' => Supplier::factory()->create(),
        'order' => EcommerceOrder::factory()->create(['shop_id' => $this->shop->id]),
        'register' => CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->member->id]),
    ];
    $this->records['shop'] = $this->shop;
});

/**
 * The tables and records the endpoints touch.
 */
const WRITE_TARGETS = [
    'category' => 'categories', 'customer' => 'customers', 'delivery' => 'delivery_companies',
    'expenseCategory' => 'expense_categories', 'expense' => 'expenses', 'product' => 'products',
    'purchaseOrder' => 'purchase_orders', 'sale' => 'sales', 'supplier' => 'suppliers', 'order' => 'ecommerce_orders',
    'register' => 'cash_registers', 'shop' => 'shops',
];

/**
 * The record's row as stored, or the table's row count for a create.
 */
function writeTargetState(string $target, array $records): mixed
{
    $table = WRITE_TARGETS[$target];

    return isset($records[$target]) && $records[$target]->exists
        ? (array) DB::table($table)->where('id', $records[$target]->id)->first()
        : DB::table($table)->count();
}

/**
 * Every write: [method, uri, the intended permissions, what it touches, whether it creates, a payload].
 *
 * @return array<string, array{0: string, 1: Closure, 2: list<string>, 3: string, 4: bool, 5: array<string, mixed>}>
 */
function flatApiWrites(): array
{
    $id = fn (string $key): Closure => fn (array $r): string => (string) $r[$key]->getRouteKey();

    return [
        'open register' => ['POST', fn (): string => '/api/cash-registers/open', ['sales.create'], 'register', true, ['opening_balance' => 100]],
        'close register' => ['POST', fn (array $r): string => "/api/cash-registers/{$id('register')($r)}/close", ['sales.create'], 'register', false, ['closing_balance' => 100]],
        'create category' => ['POST', fn (): string => '/api/categories', ['categories.create'], 'category', true, ['name' => 'Changed']],
        'update category' => ['PUT', fn (array $r): string => "/api/categories/{$id('category')($r)}", ['categories.update'], 'category', false, ['name' => 'Changed']],
        'delete category' => ['DELETE', fn (array $r): string => "/api/categories/{$id('category')($r)}", ['categories.delete'], 'category', false, []],
        'create customer' => ['POST', fn (): string => '/api/customers', ['customers.create'], 'customer', true, ['name' => 'Changed', 'phone' => '0712345678', 'customer_type' => 'retail']],
        'update customer' => ['PUT', fn (array $r): string => "/api/customers/{$id('customer')($r)}", ['customers.update'], 'customer', false, ['name' => 'Changed']],
        'delete customer' => ['DELETE', fn (array $r): string => "/api/customers/{$id('customer')($r)}", ['customers.delete'], 'customer', false, []],
        'activate customer' => ['POST', fn (array $r): string => "/api/customers/{$id('customer')($r)}/activate", ['customers.activate'], 'customer', false, []],
        'deactivate customer' => ['POST', fn (array $r): string => "/api/customers/{$id('customer')($r)}/deactivate", ['customers.deactivate'], 'customer', false, []],
        'create delivery company' => ['POST', fn (): string => '/api/delivery-companies', ['sales.create'], 'delivery', true, ['name' => 'Changed', 'phone' => '0712345678']],
        'update delivery company' => ['PUT', fn (array $r): string => "/api/delivery-companies/{$id('delivery')($r)}", ['settings.update'], 'delivery', false, ['name' => 'Changed', 'phone' => '0712345678']],
        'delete delivery company' => ['DELETE', fn (array $r): string => "/api/delivery-companies/{$id('delivery')($r)}", ['settings.update'], 'delivery', false, []],
        'toggle delivery company' => ['POST', fn (array $r): string => "/api/delivery-companies/{$id('delivery')($r)}/toggle-active", ['settings.update'], 'delivery', false, []],
        'refresh online orders' => ['POST', fn (): string => '/api/ecommerce-orders/refresh', ['ecommerce-orders.view'], 'order', true, []],
        'convert online order' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$id('order')($r)}/convert", ['ecommerce-orders.convert'], 'order', false, ['name' => 'Changed']],
        'note on online order' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$id('order')($r)}/notes", ['ecommerce-orders.view'], 'order', false, ['note' => 'Changed']],
        'reminder on online order' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$id('order')($r)}/reminders", ['ecommerce-orders.view'], 'order', false, ['name' => 'Changed']],
        'online order status' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$id('order')($r)}/update-status", ['ecommerce-orders.update'], 'order', false, ['name' => 'Changed']],
        'create expense category' => ['POST', fn (): string => '/api/expense-categories', ['expense-categories.create'], 'expenseCategory', true, ['name' => 'Changed']],
        'update expense category' => ['PUT', fn (array $r): string => "/api/expense-categories/{$id('expenseCategory')($r)}", ['expense-categories.update'], 'expenseCategory', false, ['name' => 'Changed']],
        'delete expense category' => ['DELETE', fn (array $r): string => "/api/expense-categories/{$id('expenseCategory')($r)}", ['expense-categories.delete'], 'expenseCategory', false, []],
        'create expense' => ['POST', fn (): string => '/api/expenses', ['expenses.create'], 'expense', true, ['title' => 'Changed']],
        'update expense' => ['PUT', fn (array $r): string => "/api/expenses/{$id('expense')($r)}", ['expenses.update'], 'expense', false, ['title' => 'Changed']],
        'delete expense' => ['DELETE', fn (array $r): string => "/api/expenses/{$id('expense')($r)}", ['expenses.delete'], 'expense', false, []],
        'submit expense' => ['POST', fn (array $r): string => "/api/expenses/{$id('expense')($r)}/submit", ['expenses.update'], 'expense', false, []],
        'approve expense' => ['POST', fn (array $r): string => "/api/expenses/{$id('expense')($r)}/approve", ['expenses.approve'], 'expense', false, []],
        'reject expense' => ['POST', fn (array $r): string => "/api/expenses/{$id('expense')($r)}/reject", ['expenses.approve'], 'expense', false, ['reason' => 'Changed']],
        'pay expense' => ['POST', fn (array $r): string => "/api/expenses/{$id('expense')($r)}/mark-paid", ['expenses.update'], 'expense', false, []],
        'settle expense' => ['POST', fn (array $r): string => "/api/expenses/{$id('expense')($r)}/settle-from-register", ['expenses.update'], 'expense', false, []],
        'cancel expense' => ['POST', fn (array $r): string => "/api/expenses/{$id('expense')($r)}/cancel", ['expenses.update'], 'expense', false, []],
        'create product' => ['POST', fn (): string => '/api/products', ['products.create'], 'product', true, ['name' => 'Changed']],
        'update product' => ['PUT', fn (array $r): string => "/api/products/{$id('product')($r)}", ['products.update'], 'product', false, ['name' => 'Changed']],
        'delete product' => ['DELETE', fn (array $r): string => "/api/products/{$id('product')($r)}", ['products.delete'], 'product', false, []],
        'activate product' => ['POST', fn (array $r): string => "/api/products/{$id('product')($r)}/activate", ['products.update'], 'product', false, []],
        'deactivate product' => ['POST', fn (array $r): string => "/api/products/{$id('product')($r)}/deactivate", ['products.update'], 'product', false, []],
        'delete purchase order' => ['DELETE', fn (array $r): string => "/api/purchase-orders/{$id('purchaseOrder')($r)}", ['purchase_orders.delete'], 'purchaseOrder', false, []],
        'approve purchase order' => ['POST', fn (array $r): string => "/api/purchase-orders/{$id('purchaseOrder')($r)}/approve", ['purchase_orders.approve'], 'purchaseOrder', false, []],
        'order purchase order' => ['POST', fn (array $r): string => "/api/purchase-orders/{$id('purchaseOrder')($r)}/mark-ordered", ['purchase_orders.update'], 'purchaseOrder', false, []],
        'cancel purchase order' => ['POST', fn (array $r): string => "/api/purchase-orders/{$id('purchaseOrder')($r)}/cancel", ['purchase_orders.update'], 'purchaseOrder', false, []],
        'create sale source' => ['POST', fn (): string => '/api/sale-sources', ['sales.create'], 'sale', true, ['name' => 'Changed']],
        'create sale' => ['POST', fn (): string => '/api/sales', ['sales.create'], 'sale', true, ['notes' => 'Changed']],
        'update sale' => ['PUT', fn (array $r): string => "/api/sales/{$id('sale')($r)}", ['sales.update'], 'sale', false, ['notes' => 'Changed']],
        'delete sale' => ['DELETE', fn (array $r): string => "/api/sales/{$id('sale')($r)}", ['sales.delete'], 'sale', false, []],
        'complete sale' => ['POST', fn (array $r): string => "/api/sales/{$id('sale')($r)}/complete", ['sales.create'], 'sale', false, []],
        'void sale' => ['POST', fn (array $r): string => "/api/sales/{$id('sale')($r)}/void", ['sales.void'], 'sale', false, ['reason' => 'Changed']],
        'collect payment' => ['POST', fn (array $r): string => "/api/sales/{$id('sale')($r)}/collect-payment", ['payments.create'], 'sale', false, ['amount' => 1]],
        'create shop' => ['POST', fn (): string => '/api/shops', ['shops.create'], 'shop', true, ['name' => 'Changed']],
        'update shop' => ['PUT', fn (array $r): string => "/api/shops/{$id('shop')($r)}", ['shops.update'], 'shop', false, ['name' => 'Changed']],
        'delete shop' => ['DELETE', fn (array $r): string => "/api/shops/{$id('shop')($r)}", ['shops.delete'], 'shop', false, []],
        'create supplier' => ['POST', fn (): string => '/api/suppliers', ['suppliers.create'], 'supplier', true, ['name' => 'Changed']],
        'update supplier' => ['PUT', fn (array $r): string => "/api/suppliers/{$id('supplier')($r)}", ['suppliers.update'], 'supplier', false, ['name' => 'Changed']],
        'delete supplier' => ['DELETE', fn (array $r): string => "/api/suppliers/{$id('supplier')($r)}", ['suppliers.delete'], 'supplier', false, []],
    ];
}

/**
 * The record states a policy needs before it can allow the action (a purchase order
 * can only be approved while pending, and so on).
 */
function prepareWriteTarget(string $name, array $records): void
{
    // Pest names a data set `data set "approve purchase order"`.
    match (true) {
        str_contains($name, '"approve purchase order"') => $records['purchaseOrder']->update(['status' => PurchaseOrderStatus::PENDING]),
        str_contains($name, '"order purchase order"') => $records['purchaseOrder']->update(['status' => PurchaseOrderStatus::APPROVED]),
        default => null,
    };
}

it('refuses a write to a member with no permission, and leaves the record as it was', function (string $method, Closure $uri, array $permissions, string $target, bool $creates) {
    Sanctum::actingAs($this->member, ['*']);
    $before = writeTargetState($target, $creates ? [] : $this->records);

    $this->json($method, $uri($this->records), ['name' => 'Changed', 'title' => 'Changed', 'notes' => 'Changed'])
        ->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');

    expect(writeTargetState($target, $creates ? [] : $this->records))->toBe($before);
})->with(flatApiWrites());

it('refuses a write to a member with only an unrelated permission, and leaves the record as it was', function (string $method, Closure $uri, array $permissions, string $target, bool $creates) {
    $this->member->givePermissionTo('reports.view');
    Sanctum::actingAs($this->member, ['*']);
    $before = writeTargetState($target, $creates ? [] : $this->records);

    $this->json($method, $uri($this->records), ['name' => 'Changed', 'title' => 'Changed', 'notes' => 'Changed'])->assertForbidden();

    expect(writeTargetState($target, $creates ? [] : $this->records))->toBe($before);
})->with(flatApiWrites());

it('lets a write through for a member with exactly its intended permission', function (string $method, Closure $uri, array $permissions, string $target, bool $creates, array $payload) {
    $this->member->givePermissionTo($permissions);
    Sanctum::actingAs($this->member, ['*']);
    prepareWriteTarget($this->dataName(), $this->records);

    $status = $this->json($method, $uri($this->records), $payload)->getStatusCode();

    expect($status)->not->toBeIn([401, 403, 404], "{$method} answered {$status} with ".implode(', ', $permissions));
})->with(flatApiWrites());

it('still lets a cashier do the till work the app does: open a register and add a customer, sale source or delivery company', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($this->shop);
    Sanctum::actingAs($cashier, ['*']);

    // The records above opened a session for today; the cashier opens their own after it closes.
    CashRegister::query()->update(['status' => 'closed', 'closed_at' => now()]);

    $this->postJson('/api/cash-registers/open', ['opening_balance' => 500])->assertCreated();
    $this->postJson('/api/customers', ['name' => 'Wanjiru Kamau', 'phone' => '0712345678', 'customer_type' => 'retail'])->assertCreated();
    $this->postJson('/api/sale-sources', ['name' => 'Roadside stall'])->assertSuccessful();
    $this->postJson('/api/delivery-companies', ['name' => 'Boda Express', 'phone' => '0712345678'])->assertSuccessful();
});

it('refuses a cashier voiding a sale through the API, as the web does', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($this->shop);
    Sanctum::actingAs($cashier, ['*']);

    $this->postJson("/api/sales/{$this->records['sale']->getRouteKey()}/void", ['reason' => 'Mistake'])->assertForbidden();

    expect($this->records['sale']->fresh()->status)->not->toBe('voided');
});

it('lets a member with shops.create create a shop through the API, and refuses update and delete without access', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo(['shops.view', 'shops.create', 'shops.update', 'shops.delete']);
    $manager->shops()->attach($this->shop);
    $other = Shop::factory()->create();
    Sanctum::actingAs($manager, ['*']);

    $this->postJson('/api/shops', ['name' => 'Second Shop'])->assertCreated();
    $this->putJson("/api/shops/{$this->shop->getRouteKey()}", ['name' => 'Renamed'])->assertOk();
    expect($this->shop->fresh()->name)->toBe('Renamed');

    // A shop outside their access doesn't bind for them at all.
    $this->deleteJson("/api/shops/{$other->getRouteKey()}")->assertNotFound();
    expect(DB::table('shops')->where('id', $other->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('deletes a shop through the API as the web does: staff detached and the M-Pesa till registration cleared', function () {
    $owner = User::factory()->create();
    $owner->assignRole('admin');
    $shop = Shop::factory()->create([
        'status' => ShopStatus::INACTIVE,
        'mpesa_shortcode' => '174379',
        'mpesa_shortcode_type' => 'till',
        'mpesa_verified_at' => now(),
    ]);
    $this->member->shops()->attach($shop);
    Sanctum::actingAs($owner, ['*']);

    $this->deleteJson("/api/shops/{$shop->getRouteKey()}")->assertOk();

    $deleted = Shop::withTrashed()->find($shop->id);
    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->mpesa_shortcode)->toBeNull()
        ->and($deleted->mpesa_shortcode_type)->toBeNull()
        ->and($deleted->mpesa_verified_at)->toBeNull()
        ->and(DB::table('shop_user')->where('shop_id', $shop->id)->exists())->toBeFalse();
});
