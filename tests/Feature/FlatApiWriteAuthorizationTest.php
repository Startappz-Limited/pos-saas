<?php

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
 * (hotfix fix/api-authz, 29 October 2026). Before it, store, update, delete
 * and the other mutations below checked only that the person could reach the
 * shop, so any member with a token could, for example, void a sale or delete
 * a shop. Every write here is refused to a member of the shop who holds no
 * permission, and leaves the database as it was.
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
        'sale' => Sale::factory()->create(['shop_id' => $this->shop->id]),
        'supplier' => Supplier::factory()->create(),
        'order' => EcommerceOrder::factory()->create(['shop_id' => $this->shop->id]),
        'register' => CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->member->id]),
    ];
});

/**
 * Counts of every table the writes below could touch.
 *
 * @return array<string, int>
 */
function writeTableCounts(): array
{
    $tables = ['shops', 'categories', 'customers', 'delivery_companies', 'expense_categories', 'expenses', 'products',
        'purchase_orders', 'sales', 'sale_sources', 'suppliers', 'ecommerce_orders', 'cash_registers'];

    return collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
}

it('refuses every unchecked flat API write to a member with no permission', function (string $method, Closure $uri) {
    Sanctum::actingAs($this->member, ['*']);
    $before = writeTableCounts();

    $response = $this->json($method, $uri($this->records, $this->shop), ['name' => 'Changed'])->assertForbidden();

    // The app keys off the status; the message is what it can show (`abort_unless` without one sends "").
    expect($response->json('message'))->toBe('This action is unauthorized.');

    expect(writeTableCounts())->toBe($before);
})->with([
    'open register' => ['POST', fn (): string => '/api/cash-registers/open'],
    'close register' => ['POST', fn (array $r): string => "/api/cash-registers/{$r['register']->getRouteKey()}/close"],
    'create category' => ['POST', fn (): string => '/api/categories'],
    'update category' => ['PUT', fn (array $r): string => "/api/categories/{$r['category']->getRouteKey()}"],
    'delete category' => ['DELETE', fn (array $r): string => "/api/categories/{$r['category']->getRouteKey()}"],
    'create customer' => ['POST', fn (): string => '/api/customers'],
    'update customer' => ['PUT', fn (array $r): string => "/api/customers/{$r['customer']->getRouteKey()}"],
    'delete customer' => ['DELETE', fn (array $r): string => "/api/customers/{$r['customer']->getRouteKey()}"],
    'activate customer' => ['POST', fn (array $r): string => "/api/customers/{$r['customer']->getRouteKey()}/activate"],
    'deactivate customer' => ['POST', fn (array $r): string => "/api/customers/{$r['customer']->getRouteKey()}/deactivate"],
    'create delivery company' => ['POST', fn (): string => '/api/delivery-companies'],
    'update delivery company' => ['PUT', fn (array $r): string => "/api/delivery-companies/{$r['delivery']->getRouteKey()}"],
    'delete delivery company' => ['DELETE', fn (array $r): string => "/api/delivery-companies/{$r['delivery']->getRouteKey()}"],
    'toggle delivery company' => ['POST', fn (array $r): string => "/api/delivery-companies/{$r['delivery']->getRouteKey()}/toggle-active"],
    'refresh online orders' => ['POST', fn (): string => '/api/ecommerce-orders/refresh'],
    'convert online order' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$r['order']->getRouteKey()}/convert"],
    'note on online order' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$r['order']->getRouteKey()}/notes"],
    'reminder on online order' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$r['order']->getRouteKey()}/reminders"],
    'online order status' => ['POST', fn (array $r): string => "/api/ecommerce-orders/{$r['order']->getRouteKey()}/update-status"],
    'create expense category' => ['POST', fn (): string => '/api/expense-categories'],
    'update expense category' => ['PUT', fn (array $r): string => "/api/expense-categories/{$r['expenseCategory']->getRouteKey()}"],
    'delete expense category' => ['DELETE', fn (array $r): string => "/api/expense-categories/{$r['expenseCategory']->getRouteKey()}"],
    'create expense' => ['POST', fn (): string => '/api/expenses'],
    'update expense' => ['PUT', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}"],
    'delete expense' => ['DELETE', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}"],
    'submit expense' => ['POST', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}/submit"],
    'approve expense' => ['POST', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}/approve"],
    'reject expense' => ['POST', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}/reject"],
    'pay expense' => ['POST', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}/mark-paid"],
    'settle expense' => ['POST', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}/settle-from-register"],
    'cancel expense' => ['POST', fn (array $r): string => "/api/expenses/{$r['expense']->getRouteKey()}/cancel"],
    'create product' => ['POST', fn (): string => '/api/products'],
    'update product' => ['PUT', fn (array $r): string => "/api/products/{$r['product']->getRouteKey()}"],
    'delete product' => ['DELETE', fn (array $r): string => "/api/products/{$r['product']->getRouteKey()}"],
    'activate product' => ['POST', fn (array $r): string => "/api/products/{$r['product']->getRouteKey()}/activate"],
    'deactivate product' => ['POST', fn (array $r): string => "/api/products/{$r['product']->getRouteKey()}/deactivate"],
    'delete purchase order' => ['DELETE', fn (array $r): string => "/api/purchase-orders/{$r['purchaseOrder']->getRouteKey()}"],
    'approve purchase order' => ['POST', fn (array $r): string => "/api/purchase-orders/{$r['purchaseOrder']->getRouteKey()}/approve"],
    'order purchase order' => ['POST', fn (array $r): string => "/api/purchase-orders/{$r['purchaseOrder']->getRouteKey()}/mark-ordered"],
    'cancel purchase order' => ['POST', fn (array $r): string => "/api/purchase-orders/{$r['purchaseOrder']->getRouteKey()}/cancel"],
    'create sale source' => ['POST', fn (): string => '/api/sale-sources'],
    'create sale' => ['POST', fn (): string => '/api/sales'],
    'update sale' => ['PUT', fn (array $r): string => "/api/sales/{$r['sale']->getRouteKey()}"],
    'delete sale' => ['DELETE', fn (array $r): string => "/api/sales/{$r['sale']->getRouteKey()}"],
    'complete sale' => ['POST', fn (array $r): string => "/api/sales/{$r['sale']->getRouteKey()}/complete"],
    'void sale' => ['POST', fn (array $r): string => "/api/sales/{$r['sale']->getRouteKey()}/void"],
    'collect payment' => ['POST', fn (array $r): string => "/api/sales/{$r['sale']->getRouteKey()}/collect-payment"],
    'create shop' => ['POST', fn (): string => '/api/shops'],
    'update shop' => ['PUT', fn (array $r, Shop $shop): string => "/api/shops/{$shop->getRouteKey()}"],
    'delete shop' => ['DELETE', fn (array $r, Shop $shop): string => "/api/shops/{$shop->getRouteKey()}"],
    'create supplier' => ['POST', fn (): string => '/api/suppliers'],
    'update supplier' => ['PUT', fn (array $r): string => "/api/suppliers/{$r['supplier']->getRouteKey()}"],
    'delete supplier' => ['DELETE', fn (array $r): string => "/api/suppliers/{$r['supplier']->getRouteKey()}"],
]);

it('still lets a cashier do the till work the app does: open a register and add a sale source or delivery company', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($this->shop);
    Sanctum::actingAs($cashier, ['*']);

    // The records above opened a session for today; the cashier opens their own after it closes.
    CashRegister::query()->update(['status' => 'closed', 'closed_at' => now()]);

    $this->postJson('/api/cash-registers/open', ['opening_balance' => 500])->assertCreated();
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
