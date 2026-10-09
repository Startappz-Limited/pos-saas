<?php

use App\Http\Controllers\Api\AbandonedCartController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeliveryCompanyController;
use App\Http\Controllers\Api\EcommerceOrderController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SaleSourceController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook routes (public — verified via HMAC signature, throttled per shop)
Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function () {
    Route::post('/woocommerce/{shop:uuid}', [WebhookController::class, 'woocommerce'])->name('webhooks.woocommerce');
    Route::post('/shopify/{shop:uuid}', [WebhookController::class, 'shopify'])->name('webhooks.shopify');

    // Baileys (unofficial WhatsApp) events arrive from Chatway Gateway at
    // POST /wa-gateway/webhook, a route the wa-gateway-laravel package
    // registers with its own signature check and limiter; see
    // config/wa-gateway.php and App\Listeners\Baileys\HandleGatewayWebhooks.
});

// Public routes — tightly throttled: these are the brute-force surface.
// Named `api.*` so they don't collide with the web auth routes' `login`/`register`.
Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::post('/register', [AuthController::class, 'register'])->name('api.register');
});

// Protected routes
Route::middleware(['auth:sanctum', 'active', 'business.open', 'shop.linked'])->group(function () {
    // Still reachable when the user is not linked to a shop yet.
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // Shops
    Route::apiResource('shops', ShopController::class)->names([
        'index' => 'api.shops.index',
        'store' => 'api.shops.store',
        'show' => 'api.shops.show',
        'update' => 'api.shops.update',
        'destroy' => 'api.shops.destroy',
    ]);

    // Categories
    Route::apiResource('categories', CategoryController::class)->names([
        'index' => 'api.categories.index',
        'store' => 'api.categories.store',
        'show' => 'api.categories.show',
        'update' => 'api.categories.update',
        'destroy' => 'api.categories.destroy',
    ]);

    // Products
    Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('api.products.low-stock');
    Route::post('products/{product}/activate', [ProductController::class, 'activate'])->name('api.products.activate');
    Route::post('products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('api.products.deactivate');
    Route::apiResource('products', ProductController::class)->names([
        'index' => 'api.products.index',
        'store' => 'api.products.store',
        'show' => 'api.products.show',
        'update' => 'api.products.update',
        'destroy' => 'api.products.destroy',
    ]);

    // Customers
    // WhatsApp a wholesaler their outstanding-invoice statement. A write that
    // messages a customer, so it carries the tighter `writes` limiter.
    Route::post('customers/{customer}/send-statement', [CustomerController::class, 'sendStatement'])
        ->middleware('throttle:writes')->name('api.customers.send-statement');
    Route::post('customers/{customer}/activate', [CustomerController::class, 'activate'])->name('api.customers.activate');
    Route::post('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])->name('api.customers.deactivate');
    Route::apiResource('customers', CustomerController::class)->names([
        'index' => 'api.customers.index',
        'store' => 'api.customers.store',
        'show' => 'api.customers.show',
        'update' => 'api.customers.update',
        'destroy' => 'api.customers.destroy',
    ]);

    // Credit has no dedicated API surface by design: the app reads a
    // wholesaler's debt from `credit_balance` / `credit_limit` on the customer
    // payload, which the credit ledger now keeps current. Money is recorded
    // against a credit account through `sales/{sale}/collect-payment`. The full
    // statement, aging report and manual adjustments stay on the web back office.

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class)->names([
        'index' => 'api.suppliers.index',
        'store' => 'api.suppliers.store',
        'show' => 'api.suppliers.show',
        'update' => 'api.suppliers.update',
        'destroy' => 'api.suppliers.destroy',
    ]);

    // Sales — money-affecting writes and irreversible actions are throttled
    // tighter than the baseline `throttle:api`, mirroring the web routes.
    Route::post('sales/{sale}/complete', [SaleController::class, 'complete'])
        ->middleware('throttle:writes')->name('api.sales.complete');
    Route::post('sales/{sale}/collect-payment', [SaleController::class, 'collectPayment'])
        ->middleware('throttle:writes')->name('api.sales.collect-payment');
    Route::post('sales/{sale}/void', [SaleController::class, 'void'])
        ->middleware('throttle:destructive')->name('api.sales.void');
    Route::get('sales/{sale}/payments', [SaleController::class, 'payments'])->name('api.sales.payments');

    // store/destroy are declared separately so each gets its own limiter — passing
    // an associative array to ->middleware() on a resource does NOT map per action,
    // it applies every entry to every action.
    Route::post('sales', [SaleController::class, 'store'])
        ->middleware('throttle:writes')->name('api.sales.store');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])
        ->middleware('throttle:destructive')->name('api.sales.destroy');

    Route::apiResource('sales', SaleController::class)->only(['index', 'show', 'update'])->names([
        'index' => 'api.sales.index',
        'show' => 'api.sales.show',
        'update' => 'api.sales.update',
    ]);

    // Sale Sources
    Route::get('sale-sources', [SaleSourceController::class, 'index'])->name('api.sale-sources.index');
    Route::post('sale-sources', [SaleSourceController::class, 'store'])->name('api.sale-sources.store');

    // Cash Registers
    Route::get('cash-registers/status', [CashRegisterController::class, 'status'])->name('api.cash-registers.status');
    Route::post('cash-registers/open', [CashRegisterController::class, 'open'])->name('api.cash-registers.open');
    Route::get('cash-registers', [CashRegisterController::class, 'index'])->name('api.cash-registers.index');
    Route::get('cash-registers/{cashRegister}', [CashRegisterController::class, 'show'])->name('api.cash-registers.show');
    Route::post('cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close'])->name('api.cash-registers.close');

    // E-commerce Orders
    Route::post('ecommerce-orders/refresh', [EcommerceOrderController::class, 'refresh'])->name('api.ecommerce-orders.refresh');
    Route::post('ecommerce-orders/{order}/update-status', [EcommerceOrderController::class, 'updateStatus'])->name('api.ecommerce-orders.update-status');
    Route::post('ecommerce-orders/{order}/convert', [EcommerceOrderController::class, 'convert'])->name('api.ecommerce-orders.convert');
    Route::post('ecommerce-orders/{order}/notes', [EcommerceOrderController::class, 'storeNote'])->name('api.ecommerce-orders.store-note');
    Route::post('ecommerce-orders/{order}/reminders', [EcommerceOrderController::class, 'storeReminder'])->name('api.ecommerce-orders.store-reminder');
    Route::post('ecommerce-orders/{order}/reminders/{alert}/resolve', [EcommerceOrderController::class, 'resolveReminder'])->name('api.ecommerce-orders.resolve-reminder');
    Route::get('ecommerce-orders', [EcommerceOrderController::class, 'index'])->name('api.ecommerce-orders.index');
    Route::get('ecommerce-orders/{order}', [EcommerceOrderController::class, 'show'])->name('api.ecommerce-orders.show');

    // Abandoned Carts (website carts from the Abandoned Cart Recovery plugin)
    Route::get('abandoned-carts', [AbandonedCartController::class, 'index'])->name('api.abandoned-carts.index');
    Route::get('abandoned-carts/{abandonedCart}', [AbandonedCartController::class, 'show'])->name('api.abandoned-carts.show');
    Route::match(['put', 'patch'], 'abandoned-carts/{abandonedCart}', [AbandonedCartController::class, 'update'])->name('api.abandoned-carts.update');
    Route::post('abandoned-carts/{abandonedCart}/notes', [AbandonedCartController::class, 'storeNote'])->name('api.abandoned-carts.store-note');
    Route::post('abandoned-carts/{abandonedCart}/reminders', [AbandonedCartController::class, 'storeReminder'])->name('api.abandoned-carts.store-reminder');
    Route::post('abandoned-carts/{abandonedCart}/reminders/{alert}/resolve', [AbandonedCartController::class, 'resolveReminder'])->name('api.abandoned-carts.resolve-reminder');
    Route::post('abandoned-carts/{abandonedCart}/whatsapp', [AbandonedCartController::class, 'sendWhatsApp'])
        ->middleware('throttle:writes')->name('api.abandoned-carts.whatsapp');
    Route::post('abandoned-carts/{abandonedCart}/convert', [AbandonedCartController::class, 'convert'])
        ->middleware('throttle:writes')->name('api.abandoned-carts.convert');
    Route::post('abandoned-carts/{abandonedCart}/opt-out', [AbandonedCartController::class, 'optOut'])->name('api.abandoned-carts.opt-out');

    // Calendar
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('api.calendar.events');

    // Reports
    Route::get('reports/daily-sales', [ReportController::class, 'dailySales'])->name('api.reports.daily-sales');
    Route::get('reports/daily-register', [ReportController::class, 'dailyRegister'])->name('api.reports.daily-register');
    Route::get('reports/top-products', [ReportController::class, 'topProducts'])->name('api.reports.top-products');
    Route::get('reports/pending-payments', [ReportController::class, 'pendingPayments'])->name('api.reports.pending-payments');

    // Delivery Companies
    Route::post('delivery-companies/{deliveryCompany}/toggle-active', [DeliveryCompanyController::class, 'toggleActive'])->name('api.delivery-companies.toggle-active');
    Route::apiResource('delivery-companies', DeliveryCompanyController::class)->names([
        'index' => 'api.delivery-companies.index',
        'store' => 'api.delivery-companies.store',
        'show' => 'api.delivery-companies.show',
        'update' => 'api.delivery-companies.update',
        'destroy' => 'api.delivery-companies.destroy',
    ]);

    // Purchase Orders
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('api.purchase-orders.approve');
    Route::post('purchase-orders/{purchaseOrder}/mark-ordered', [PurchaseOrderController::class, 'markOrdered'])->name('api.purchase-orders.mark-ordered');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('api.purchase-orders.cancel');
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->names([
        'index' => 'api.purchase-orders.index',
        'store' => 'api.purchase-orders.store',
        'show' => 'api.purchase-orders.show',
        'update' => 'api.purchase-orders.update',
        'destroy' => 'api.purchase-orders.destroy',
    ]);

    // Inventory routes
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/shop/{shop}', [InventoryController::class, 'byShop']);
    Route::get('inventory/product/{product}', [InventoryController::class, 'byProduct']);
    Route::get('inventory/low-stock', [InventoryController::class, 'lowStock']);

    // Expense Categories
    Route::apiResource('expense-categories', ExpenseCategoryController::class)->names([
        'index' => 'api.expense-categories.index',
        'store' => 'api.expense-categories.store',
        'show' => 'api.expense-categories.show',
        'update' => 'api.expense-categories.update',
        'destroy' => 'api.expense-categories.destroy',
    ]);

    // Expenses
    Route::get('expenses/summary', [ExpenseController::class, 'summary'])->name('api.expenses.summary');
    Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit'])->name('api.expenses.submit');
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('api.expenses.approve');
    Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('api.expenses.reject');
    Route::post('expenses/{expense}/mark-paid', [ExpenseController::class, 'markPaid'])->name('api.expenses.mark-paid');
    Route::post('expenses/{expense}/settle-from-register', [ExpenseController::class, 'settleFromRegister'])->name('api.expenses.settle-from-register');
    Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('api.expenses.cancel');
    Route::apiResource('expenses', ExpenseController::class)->names([
        'index' => 'api.expenses.index',
        'store' => 'api.expenses.store',
        'show' => 'api.expenses.show',
        'update' => 'api.expenses.update',
        'destroy' => 'api.expenses.destroy',
    ]);

    // Users
    Route::get('users/roles', [UserController::class, 'roles'])->name('api.users.roles');
    Route::get('users/shops', [UserController::class, 'shops'])->name('api.users.shops');
    Route::get('users/statuses', [UserController::class, 'statuses'])->name('api.users.statuses');
    Route::apiResource('users', UserController::class)->names([
        'index' => 'api.users.index',
        'store' => 'api.users.store',
        'show' => 'api.users.show',
        'update' => 'api.users.update',
        'destroy' => 'api.users.destroy',
    ]);
});
