<?php

use App\Http\Controllers\AbandonedCartController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Baileys\BroadcastController;
use App\Http\Controllers\Baileys\InboxController;
use App\Http\Controllers\Baileys\SessionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditAccountController;
use App\Http\Controllers\CreditTransactionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EcommerceOrderController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventorySnapshotController;
use App\Http\Controllers\LowStockAlertController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPurchaseCostController;
use App\Http\Controllers\ProductSyncController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockIntakeController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VatReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // redirect to ogin
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Public signed routes (no auth required)
Route::get('orders/{order:uuid}/invoice', [EcommerceOrderController::class, 'invoicePdf'])
    ->name('orders.invoice-pdf')
    ->middleware('signed');

Route::get('sales/{sale:uuid}/invoice', [SaleController::class, 'invoicePdf'])
    ->name('sales.invoice-pdf')
    ->middleware('signed');

// Re-download link for the statement a customer receives over WhatsApp. Renders
// live, so it always shows what is owed now rather than at the time of sending.
Route::get('credit-accounts/{creditAccount:uuid}/statement-pdf', [CreditAccountController::class, 'outstandingStatementPdf'])
    ->name('credit-accounts.statement-pdf')
    ->middleware('signed');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User Management Routes
    Route::resource('users', UserController::class);
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');

    // Role Management Routes
    Route::resource('roles', RoleController::class);
    Route::get('roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.updatePermissions');

    // Shop Management Routes
    Route::post('shops/test-integration', [ShopController::class, 'testIntegration'])->name('shops.testIntegration');
    Route::resource('shops', ShopController::class);
    Route::post('shops/{shop}/activate', [ShopController::class, 'activate'])->name('shops.activate');
    Route::post('shops/{shop}/deactivate', [ShopController::class, 'deactivate'])->name('shops.deactivate');
    Route::post('shops/{shop}/suspend', [ShopController::class, 'suspend'])->name('shops.suspend');
    Route::get('shops/{shop}/users', [ShopController::class, 'users'])->name('shops.users');
    Route::put('shops/{shop}/users', [ShopController::class, 'updateUsers'])->name('shops.updateUsers');

    // Category Management Routes
    Route::resource('categories', CategoryController::class);
    Route::get('categories/tree/view', [CategoryController::class, 'tree'])->name('categories.tree');
    Route::post('categories/{category}/activate', [CategoryController::class, 'activate'])->name('categories.activate');
    Route::post('categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');

    // Attribute Management Routes
    Route::resource('attributes', AttributeController::class);
    Route::post('attributes/{attribute}/activate', [AttributeController::class, 'activate'])->name('attributes.activate');
    Route::post('attributes/{attribute}/deactivate', [AttributeController::class, 'deactivate'])->name('attributes.deactivate');

    // Purchase Cost Routes — registered before the products resource so the
    // literal path is not swallowed by the products/{product} show route.
    // Authorized by ProductPolicy::setCost in the controller, matching how
    // every other route in this file gates access.
    Route::get('products/purchase-costs', [ProductPurchaseCostController::class, 'index'])
        ->name('products.purchase-costs.index');
    Route::put('products/purchase-costs', [ProductPurchaseCostController::class, 'update'])
        ->name('products.purchase-costs.update');

    // Product Management Routes
    Route::resource('products', ProductController::class);
    Route::get('products/low-stock/list', [ProductController::class, 'lowStock'])->name('products.lowStock');
    Route::post('products/{product}/activate', [ProductController::class, 'activate'])->name('products.activate');
    Route::post('products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');

    // Product E-commerce Sync Routes
    Route::post('products/sync/initiate', [ProductSyncController::class, 'initiate'])->name('products.sync.initiate');
    Route::post('products/{product}/sync', [ProductSyncController::class, 'syncSingle'])->name('products.sync.single');

    // Pricing Management Routes
    Route::resource('pricing-rules', PricingRuleController::class);
    Route::get('pricing-rules/expired/list', [PricingRuleController::class, 'expired'])->name('pricing-rules.expired');
    Route::get('pricing-rules/upcoming/list', [PricingRuleController::class, 'upcoming'])->name('pricing-rules.upcoming');
    Route::post('pricing-rules/{pricingRule}/activate', [PricingRuleController::class, 'activate'])->name('pricing-rules.activate');
    Route::post('pricing-rules/{pricingRule}/deactivate', [PricingRuleController::class, 'deactivate'])->name('pricing-rules.deactivate');

    // Supplier Management Routes
    Route::resource('suppliers', SupplierController::class);
    Route::get('suppliers/top/list', [SupplierController::class, 'topSuppliers'])->name('suppliers.top');
    Route::get('suppliers/credit-alerts/list', [SupplierController::class, 'creditLimitAlerts'])->name('suppliers.creditAlerts');
    Route::post('suppliers/{supplier}/activate', [SupplierController::class, 'activate'])->name('suppliers.activate');
    Route::post('suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])->name('suppliers.deactivate');
    Route::post('suppliers/{supplier}/suspend', [SupplierController::class, 'suspend'])->name('suppliers.suspend');
    Route::post('suppliers/{supplier}/blacklist', [SupplierController::class, 'blacklist'])->name('suppliers.blacklist');

    // Purchase Order Management Routes
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::get('purchase-orders/pending/list', [PurchaseOrderController::class, 'pending'])->name('purchase-orders.pending');
    Route::get('purchase-orders/overdue/list', [PurchaseOrderController::class, 'overdue'])->name('purchase-orders.overdue');
    Route::post('purchase-orders/{purchase_order}/submit-for-approval', [PurchaseOrderController::class, 'submitForApproval'])->name('purchase-orders.submitForApproval');
    Route::post('purchase-orders/{purchase_order}/submit-approve-and-mark-ordered', [PurchaseOrderController::class, 'submitApproveAndMarkAsOrdered'])->name('purchase-orders.submitApproveAndMarkAsOrdered');
    Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('purchase-orders/{purchase_order}/approve-and-mark-ordered', [PurchaseOrderController::class, 'approveAndMarkAsOrdered'])->name('purchase-orders.approveAndMarkAsOrdered');
    Route::post('purchase-orders/{purchase_order}/mark-ordered', [PurchaseOrderController::class, 'markAsOrdered'])->name('purchase-orders.markAsOrdered');
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

    // Purchase Return Management Routes
    Route::resource('purchase-returns', PurchaseReturnController::class);
    Route::post('purchase-returns/{purchase_return}/approve', [PurchaseReturnController::class, 'approve'])->name('purchase-returns.approve');
    Route::post('purchase-returns/{purchase_return}/approve-and-ship', [PurchaseReturnController::class, 'approveAndShip'])->name('purchase-returns.approveAndShip');
    Route::post('purchase-returns/{purchase_return}/ship', [PurchaseReturnController::class, 'ship'])->name('purchase-returns.ship');
    Route::post('purchase-returns/{purchase_return}/complete', [PurchaseReturnController::class, 'complete'])->name('purchase-returns.complete');
    Route::post('purchase-returns/{purchase_return}/cancel', [PurchaseReturnController::class, 'cancel'])->name('purchase-returns.cancel');

    // Stock Intake Management Routes
    Route::resource('stock-intakes', StockIntakeController::class);
    Route::get('stock-intakes/pending/list', [StockIntakeController::class, 'pending'])->name('stock-intakes.pending');
    Route::get('stock-intakes/quality-issues/list', [StockIntakeController::class, 'qualityIssues'])->name('stock-intakes.qualityIssues');
    Route::post('stock-intakes/{stock_intake}/complete', [StockIntakeController::class, 'complete'])->name('stock-intakes.complete');
    Route::post('stock-intakes/{stock_intake}/cancel', [StockIntakeController::class, 'cancel'])->name('stock-intakes.cancel');

    // Inventory Tracking Routes
    Route::resource('inventory-snapshots', InventorySnapshotController::class)->only(['index', 'show']);
    Route::post('inventory-snapshots/generate', [InventorySnapshotController::class, 'generate'])->name('inventory-snapshots.generate');
    Route::get('inventory-snapshots/report/valuation', [InventorySnapshotController::class, 'valuationReport'])->name('inventory-snapshots.valuationReport');

    // Stock Movement Routes
    Route::resource('stock-movements', StockMovementController::class)->only(['index', 'show', 'store']);
    Route::get('stock-movements/additions/list', [StockMovementController::class, 'additions'])->name('stock-movements.additions');
    Route::get('stock-movements/deductions/list', [StockMovementController::class, 'deductions'])->name('stock-movements.deductions');
    Route::get('stock-movements/stats/report', [StockMovementController::class, 'stats'])->name('stock-movements.stats');

    // Low Stock Alert Routes
    Route::resource('low-stock-alerts', LowStockAlertController::class)->only(['index', 'show']);
    Route::post('low-stock-alerts/check', [LowStockAlertController::class, 'checkLevels'])->name('low-stock-alerts.check');
    Route::post('low-stock-alerts/{low_stock_alert}/acknowledge', [LowStockAlertController::class, 'acknowledge'])->name('low-stock-alerts.acknowledge');
    Route::post('low-stock-alerts/{low_stock_alert}/resolve', [LowStockAlertController::class, 'resolve'])->name('low-stock-alerts.resolve');
    Route::post('low-stock-alerts/{low_stock_alert}/ignore', [LowStockAlertController::class, 'ignore'])->name('low-stock-alerts.ignore');
    Route::get('low-stock-alerts/pending/list', [LowStockAlertController::class, 'pending'])->name('low-stock-alerts.pending');
    Route::get('low-stock-alerts/active/list', [LowStockAlertController::class, 'active'])->name('low-stock-alerts.active');

    // Customer Management Routes
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');
    Route::post('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])->name('customers.deactivate');
    Route::get('customers/{customer}/purchases', [CustomerController::class, 'purchases'])->name('customers.purchases');
    Route::get('customers/{customer}/purchases/pdf', [CustomerController::class, 'purchasesPdf'])->name('customers.purchases.pdf');
    Route::get('customers/credit-alerts/list', [CustomerController::class, 'creditAlerts'])->name('customers.creditAlerts');

    // Cash Register Routes (UUID-secured)
    Route::get('cash-registers', [CashRegisterController::class, 'index'])->name('cash-registers.index');
    Route::get('cash-registers/open', [CashRegisterController::class, 'open'])->name('cash-registers.open');
    Route::post('cash-registers', [CashRegisterController::class, 'store'])->name('cash-registers.store')->middleware('throttle:10,1');
    Route::get('cash-registers/{cashRegister:uuid}', [CashRegisterController::class, 'show'])->name('cash-registers.show');
    Route::get('cash-registers/{cashRegister:uuid}/close', [CashRegisterController::class, 'closeForm'])->name('cash-registers.close-form');
    Route::post('cash-registers/{cashRegister:uuid}/close', [CashRegisterController::class, 'close'])->name('cash-registers.close')->middleware('throttle:5,1');

    // Sales & Transactions Routes (UUID-secured, rate-limited)
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::get('pos', [SaleController::class, 'pos'])->name('pos.index');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store')->middleware('throttle:20,1');
    Route::post('sales/sale-sources', [SaleController::class, 'storeSaleSource'])->name('sales.storeSaleSource');

    // E-Commerce Orders Routes
    Route::get('ecommerce-orders', [EcommerceOrderController::class, 'index'])->name('ecommerce-orders.index');
    Route::post('ecommerce-orders/refresh', [EcommerceOrderController::class, 'refresh'])->name('ecommerce-orders.refresh');
    Route::get('ecommerce-orders/{order:uuid}', [EcommerceOrderController::class, 'show'])->name('ecommerce-orders.show');
    Route::post('ecommerce-orders/{order:uuid}/status', [EcommerceOrderController::class, 'updateStatus'])->name('ecommerce-orders.updateStatus');
    Route::get('ecommerce-orders/{order:uuid}/convert', [EcommerceOrderController::class, 'convertToSale'])->name('ecommerce-orders.convert');
    Route::post('ecommerce-orders/{order:uuid}/convert', [EcommerceOrderController::class, 'storeConversion'])->name('ecommerce-orders.storeConversion');
    Route::post('ecommerce-orders/{order:uuid}/notes', [EcommerceOrderController::class, 'storeNote'])->name('ecommerce-orders.storeNote');
    Route::post('ecommerce-orders/{order:uuid}/reminders', [EcommerceOrderController::class, 'storeReminder'])->name('ecommerce-orders.storeReminder');
    Route::post('ecommerce-orders/{order:uuid}/reminders/{alert}/resolve', [EcommerceOrderController::class, 'resolveReminder'])->name('ecommerce-orders.resolveReminder');

    // Abandoned Carts (website carts from the Abandoned Cart Recovery plugin)
    Route::get('abandoned-carts', [AbandonedCartController::class, 'index'])->name('abandoned-carts.index');
    Route::get('abandoned-carts/{abandonedCart:uuid}', [AbandonedCartController::class, 'show'])->name('abandoned-carts.show');
    Route::post('abandoned-carts/{abandonedCart:uuid}', [AbandonedCartController::class, 'update'])->name('abandoned-carts.update');
    Route::post('abandoned-carts/{abandonedCart:uuid}/notes', [AbandonedCartController::class, 'storeNote'])->name('abandoned-carts.storeNote');
    Route::post('abandoned-carts/{abandonedCart:uuid}/reminders', [AbandonedCartController::class, 'storeReminder'])->name('abandoned-carts.storeReminder');
    Route::post('abandoned-carts/{abandonedCart:uuid}/reminders/{alert:uuid}/resolve', [AbandonedCartController::class, 'resolveReminder'])->name('abandoned-carts.resolveReminder');
    Route::post('abandoned-carts/{abandonedCart:uuid}/whatsapp', [AbandonedCartController::class, 'sendWhatsApp'])->name('abandoned-carts.whatsapp')->middleware('throttle:20,1');
    Route::post('abandoned-carts/{abandonedCart:uuid}/convert', [AbandonedCartController::class, 'convert'])->name('abandoned-carts.convert')->middleware('throttle:20,1');
    Route::post('abandoned-carts/{abandonedCart:uuid}/opt-out', [AbandonedCartController::class, 'optOut'])->name('abandoned-carts.optOut');
    Route::post('sales/delivery-companies', [SaleController::class, 'storeDeliveryCompany'])->name('sales.storeDeliveryCompany');
    Route::get('sales/{sale:uuid}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('sales/{sale:uuid}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::get('sales/{sale:uuid}/edit', [SaleController::class, 'edit'])->name('sales.edit');
    Route::put('sales/{sale:uuid}', [SaleController::class, 'update'])->name('sales.update');
    Route::delete('sales/{sale:uuid}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::get('sales/pending/list', [SaleController::class, 'pending'])->name('sales.pending');
    Route::get('sales/completed/list', [SaleController::class, 'completed'])->name('sales.completed');
    Route::post('sales/{sale:uuid}/complete', [SaleController::class, 'complete'])->name('sales.complete');
    Route::post('sales/{sale:uuid}/void', [SaleController::class, 'void'])->name('sales.void')->middleware('throttle:5,1');
    Route::post('sales/{sale:uuid}/collect-payment', [SaleController::class, 'collectPayment'])->name('sales.collectPayment');
    Route::get('sales/{sale:uuid}/payments', [SaleController::class, 'getPayments'])->name('sales.getPayments');
    Route::get('sales/report/daily', [SaleController::class, 'dailyReport'])->name('sales.dailyReport');
    Route::get('sales/report/profit', [SaleController::class, 'profitReport'])->name('sales.profitReport');

    // Payment Routes — read/report only, over `sale_payments` (the table the sale
    // flow actually writes). The old `payments` table is unused scaffolding; its
    // store/pending/verify/void routes were removed because sale_payments has no
    // status/verification columns and payments are created via the sale flow
    // (sales.collectPayment), not standalone. See .claude/skills/pos-domain-rules.
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/report/daily', [PaymentController::class, 'dailyReport'])->name('payments.dailyReport');
    Route::get('payments/report/methods', [PaymentController::class, 'methodsReport'])->name('payments.methodsReport');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    // Credit Account Routes
    Route::resource('credit-accounts', CreditAccountController::class);
    Route::get('credit-accounts/{creditAccount}/transactions', [CreditAccountController::class, 'transactions'])->name('credit-accounts.transactions');
    Route::get('credit-accounts/{creditAccount}/statement', [CreditAccountController::class, 'statement'])->name('credit-accounts.statement');
    Route::get('credit-accounts/{creditAccount}/statement/pdf', [CreditAccountController::class, 'statementPdf'])->name('credit-accounts.statement.pdf');
    Route::post('credit-accounts/{creditAccount}/send-statement', [CreditAccountController::class, 'sendStatement'])->name('credit-accounts.sendStatement');
    Route::post('credit-accounts/{creditAccount}/suspend', [CreditAccountController::class, 'suspend'])->name('credit-accounts.suspend');
    Route::post('credit-accounts/{creditAccount}/reactivate', [CreditAccountController::class, 'reactivate'])->name('credit-accounts.reactivate');
    Route::post('credit-accounts/{creditAccount}/adjust-limit', [CreditAccountController::class, 'adjustLimit'])->name('credit-accounts.adjustLimit');
    Route::get('credit-accounts/overdue/list', [CreditAccountController::class, 'overdue'])->name('credit-accounts.overdue');
    Route::get('credit-accounts/report/aging', [CreditAccountController::class, 'agingReport'])->name('credit-accounts.agingReport');

    // Credit Transaction Routes
    Route::resource('credit-transactions', CreditTransactionController::class)->only(['index', 'show', 'store']);
    Route::get('credit-transactions/overdue/list', [CreditTransactionController::class, 'overdue'])->name('credit-transactions.overdue');
    Route::get('credit-transactions/customer/{customer}', [CreditTransactionController::class, 'byCustomer'])->name('credit-transactions.byCustomer');

    // Expense Category Routes
    Route::resource('expense-categories', ExpenseCategoryController::class);
    Route::get('expense-categories/{expenseCategory}/children', [ExpenseCategoryController::class, 'children'])->name('expense-categories.children');
    Route::get('expense-categories/{expenseCategory}/hierarchy', [ExpenseCategoryController::class, 'hierarchy'])->name('expense-categories.hierarchy');
    Route::post('expense-categories/{expenseCategory}/configure-shop', [ExpenseCategoryController::class, 'configureShop'])->name('expense-categories.configureShop');
    Route::get('expense-categories/report/budget', [ExpenseCategoryController::class, 'budgetReport'])->name('expense-categories.budgetReport');

    // Expense Routes
    Route::resource('expenses', ExpenseController::class);
    Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit'])->name('expenses.submit');
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::post('expenses/{expense}/mark-paid', [ExpenseController::class, 'markPaid'])->name('expenses.markPaid');
    Route::post('expenses/{expense}/settle-from-register', [ExpenseController::class, 'settleFromRegister'])->name('expenses.settleFromRegister');
    Route::get('expenses/pending/list', [ExpenseController::class, 'pending'])->name('expenses.pending');
    Route::get('expenses/recurring/list', [ExpenseController::class, 'recurring'])->name('expenses.recurring');
    Route::get('expenses/report/summary', [ExpenseController::class, 'summary'])->name('expenses.summary');
    Route::get('expenses/report/by-category', [ExpenseController::class, 'byCategory'])->name('expenses.byCategory');

    // Campaign Routes
    Route::resource('campaigns', CampaignController::class);
    Route::post('campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
    Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('campaigns/{campaign}/complete', [CampaignController::class, 'complete'])->name('campaigns.complete');
    Route::get('campaigns/{campaign}/metrics', [CampaignController::class, 'metrics'])->name('campaigns.metrics');
    Route::get('campaigns/{campaign}/roi', [CampaignController::class, 'roi'])->name('campaigns.roi');
    Route::get('campaigns/active/list', [CampaignController::class, 'active'])->name('campaigns.active');
    Route::get('campaigns/report/performance', [CampaignController::class, 'performance'])->name('campaigns.performance');
    Route::get('campaigns/report/by-channel', [CampaignController::class, 'byChannel'])->name('campaigns.byChannel');
    Route::post('campaigns/{campaign}/generate-content', [CampaignController::class, 'generateContent'])->name('campaigns.generateContent');
    Route::post('campaigns/{campaign}/posts', [CampaignController::class, 'storePost'])->name('campaigns.posts.store');
    Route::post('campaigns/{campaign}/posts/{post}/publish', [CampaignController::class, 'publishPost'])->name('campaigns.posts.publish');

    // Social Accounts (Facebook, Instagram, Meta Ads, Google Ads)
    Route::resource('social-accounts', SocialAccountController::class);

    // Baileys (unofficial WhatsApp via Chatway Gateway)
    // NOTE: kept entirely separate from the official WhatsApp Cloud API channel.
    Route::prefix('baileys')->name('baileys.')->group(function () {
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/create', [SessionController::class, 'create'])->name('sessions.create');
        Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session:uuid}', [SessionController::class, 'show'])->name('sessions.show');
        Route::delete('sessions/{session:uuid}', [SessionController::class, 'destroy'])->name('sessions.destroy');

        // Per-shop switches for the automatic sale messages (master + per channel).
        // Covers the official Cloud API receipt too, not just Baileys — it lives
        // here because this is the screen the switches are presented on.
        Route::post('automation/{shop}/sale-notifications', [SessionController::class, 'toggleSaleNotifications'])
            ->name('automation.saleNotifications');

        Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
        Route::post('inbox/start/{session:uuid}', [InboxController::class, 'startChat'])->name('inbox.start');
        Route::post('inbox/{chat}/map', [InboxController::class, 'mapChat'])->name('inbox.map');
        Route::post('inbox/{chat}/send', [InboxController::class, 'send'])->name('inbox.send');
        Route::post('inbox/{chat}/send-media', [InboxController::class, 'sendMedia'])->name('inbox.sendMedia');
        Route::get('inbox/{chat}/stream', [InboxController::class, 'stream'])->name('inbox.stream');

        Route::get('broadcast', [BroadcastController::class, 'show'])->name('broadcast.show');
        Route::post('broadcast/{session:uuid}/group', [BroadcastController::class, 'postToGroup'])->name('broadcast.group');
        Route::post('broadcast/{session:uuid}/status', [BroadcastController::class, 'updateStatus'])->name('broadcast.status');
    });

    // Calendar Routes
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');

    // Alert & Notification Routes
    Route::resource('alerts', AlertController::class)->only(['index', 'show', 'store']);
    Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    Route::post('alerts/{alert}/mark-read', [AlertController::class, 'markRead'])->name('alerts.markRead');
    Route::post('alerts/mark-all-read', [AlertController::class, 'markAllRead'])->name('alerts.markAllRead');
    Route::get('alerts/unread/count', [AlertController::class, 'unreadCount'])->name('alerts.unreadCount');
    Route::get('alerts/unresolved/list', [AlertController::class, 'unresolved'])->name('alerts.unresolved');

    // Dashboard Routes
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/widgets', [DashboardController::class, 'addWidget'])->name('dashboard.addWidget');
    Route::put('dashboard/widgets/{widget}', [DashboardController::class, 'updateWidget'])->name('dashboard.updateWidget');
    Route::delete('dashboard/widgets/{widget}', [DashboardController::class, 'removeWidget'])->name('dashboard.removeWidget');
    Route::post('dashboard/widgets/reorder', [DashboardController::class, 'reorderWidgets'])->name('dashboard.reorderWidgets');
    Route::get('dashboard/widgets/{widget}/data', [DashboardController::class, 'widgetData'])->name('dashboard.widgetData');

    // Report Routes
    // Declared before the resource so "reports/vat" is not swallowed by
    // reports/{report}.
    Route::get('reports/vat', [VatReportController::class, 'index'])->name('reports.vat');
    Route::get('reports/vat/export', [VatReportController::class, 'export'])->name('reports.vat.export');
    Route::resource('reports', ReportController::class);
    Route::post('reports/{report}/run', [ReportController::class, 'run'])->name('reports.run');
    Route::post('reports/{report}/schedule', [ReportController::class, 'schedule'])->name('reports.schedule');
    Route::post('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/types/list', [ReportController::class, 'types'])->name('reports.types');
    Route::get('reports/exports/list', [ReportController::class, 'exports'])->name('reports.exports');
    Route::get('reports/exports/{export}/download', [ReportController::class, 'download'])->name('reports.download');

    // Audit Log Routes
    Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);
    Route::get('audit-logs/model/{type}/{id}', [AuditLogController::class, 'forModel'])->name('audit-logs.forModel');
    Route::get('audit-logs/user/{user}', [AuditLogController::class, 'forUser'])->name('audit-logs.forUser');
    Route::post('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

    // Returns & Refunds Routes
    Route::resource('returns', ReturnController::class);
    Route::post('returns/{return}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('returns/{return}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
    Route::post('returns/{return}/receive', [ReturnController::class, 'receive'])->name('returns.receive');
    Route::post('returns/{return}/inspect', [ReturnController::class, 'inspect'])->name('returns.inspect');
    Route::get('returns/sale/{sale}', [ReturnController::class, 'forSale'])->name('returns.forSale');

    Route::resource('refunds', RefundController::class)->only(['index', 'show', 'store']);
    Route::post('refunds/{refund}/process', [RefundController::class, 'process'])->name('refunds.process');

    // NOTE: FIFO costing routes were removed — `cost_layers`/`cost_allocations` are
    // unused scaffolding (nothing writes them) and CostLayerController was empty, so
    // all three routes threw at runtime. COGS is the product's current cost_price
    // snapshotted onto sale_items at sale time. See .claude/skills/pos-domain-rules.

    // Stock Adjustment Routes
    Route::resource('stock-adjustments', StockAdjustmentController::class);
    Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
    Route::post('stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');
    Route::post('stock-adjustments/{stockAdjustment}/complete', [StockAdjustmentController::class, 'complete'])->name('stock-adjustments.complete');
});

require __DIR__.'/auth.php';
