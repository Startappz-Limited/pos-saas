# Module 17: Reports & Dashboard
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Comprehensive reporting and dashboard system with real-time analytics, customizable widgets, exportable reports, and visual charts for sales, inventory, expenses, and business performance across all shops.

**Priority:** P1 (High)  
**Dependencies:** All previous modules  
**Estimated Time:** 3 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Saved Reports Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // ReportType enum
            
            // Filters & Parameters
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('group_by')->nullable();
            $table->string('sort_by')->nullable();
            $table->string('sort_direction')->default('desc');
            
            // Scheduling
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency')->nullable();
            $table->json('schedule_recipients')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            
            $table->boolean('is_public')->default(false);
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
```

### Dashboard Widgets Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('title');
            $table->string('widget_type'); // WidgetType enum
            $table->string('size')->default('medium'); // small, medium, large, full
            $table->integer('position')->default(0);
            $table->integer('row')->default(0);
            $table->integer('column')->default(0);
            
            // Configuration
            $table->json('config')->nullable();
            $table->json('filters')->nullable();
            
            $table->boolean('is_visible')->default(true);
            
            $table->timestamps();
            
            $table->index(['user_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
```

### Report Exports Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_report_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name');
            $table->string('type'); // ReportType enum
            $table->string('format'); // ExportFormat enum
            $table->string('status'); // pending, processing, completed, failed
            
            $table->json('filters')->nullable();
            
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('row_count')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
```

---

## 3. Enums

### ReportType Enum

**File:** `app/Enums/ReportType.php`

```php
<?php

namespace App\Enums;

enum ReportType: string
{
    // Sales Reports
    case SALES_SUMMARY = 'sales_summary';
    case SALES_BY_PRODUCT = 'sales_by_product';
    case SALES_BY_CATEGORY = 'sales_by_category';
    case SALES_BY_CUSTOMER = 'sales_by_customer';
    case SALES_BY_STAFF = 'sales_by_staff';
    case SALES_TREND = 'sales_trend';
    
    // Inventory Reports
    case INVENTORY_VALUATION = 'inventory_valuation';
    case STOCK_MOVEMENT = 'stock_movement';
    case LOW_STOCK = 'low_stock';
    case EXPIRING_STOCK = 'expiring_stock';
    case DEAD_STOCK = 'dead_stock';
    
    // Financial Reports
    case PROFIT_LOSS = 'profit_loss';
    case EXPENSE_SUMMARY = 'expense_summary';
    case EXPENSE_BY_CATEGORY = 'expense_by_category';
    case CASH_FLOW = 'cash_flow';
    case TAX_SUMMARY = 'tax_summary';
    
    // Payment Reports
    case PAYMENTS_RECEIVED = 'payments_received';
    case OUTSTANDING_PAYMENTS = 'outstanding_payments';
    case CREDIT_AGING = 'credit_aging';
    
    // Supplier Reports
    case SUPPLIER_PURCHASES = 'supplier_purchases';
    case SUPPLIER_PAYMENTS = 'supplier_payments';

    public function label(): string
    {
        return match ($this) {
            self::SALES_SUMMARY => 'Sales Summary',
            self::SALES_BY_PRODUCT => 'Sales by Product',
            self::SALES_BY_CATEGORY => 'Sales by Category',
            self::SALES_BY_CUSTOMER => 'Sales by Customer',
            self::SALES_BY_STAFF => 'Sales by Staff',
            self::SALES_TREND => 'Sales Trend',
            self::INVENTORY_VALUATION => 'Inventory Valuation',
            self::STOCK_MOVEMENT => 'Stock Movement',
            self::LOW_STOCK => 'Low Stock Report',
            self::EXPIRING_STOCK => 'Expiring Stock',
            self::DEAD_STOCK => 'Dead Stock',
            self::PROFIT_LOSS => 'Profit & Loss',
            self::EXPENSE_SUMMARY => 'Expense Summary',
            self::EXPENSE_BY_CATEGORY => 'Expenses by Category',
            self::CASH_FLOW => 'Cash Flow',
            self::TAX_SUMMARY => 'Tax Summary',
            self::PAYMENTS_RECEIVED => 'Payments Received',
            self::OUTSTANDING_PAYMENTS => 'Outstanding Payments',
            self::CREDIT_AGING => 'Credit Aging Report',
            self::SUPPLIER_PURCHASES => 'Supplier Purchases',
            self::SUPPLIER_PAYMENTS => 'Supplier Payments',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SALES_SUMMARY, self::SALES_BY_PRODUCT, self::SALES_BY_CATEGORY,
            self::SALES_BY_CUSTOMER, self::SALES_BY_STAFF, self::SALES_TREND => 'Sales',
            
            self::INVENTORY_VALUATION, self::STOCK_MOVEMENT, self::LOW_STOCK,
            self::EXPIRING_STOCK, self::DEAD_STOCK => 'Inventory',
            
            self::PROFIT_LOSS, self::EXPENSE_SUMMARY, self::EXPENSE_BY_CATEGORY,
            self::CASH_FLOW, self::TAX_SUMMARY => 'Financial',
            
            self::PAYMENTS_RECEIVED, self::OUTSTANDING_PAYMENTS, self::CREDIT_AGING => 'Payments',
            
            self::SUPPLIER_PURCHASES, self::SUPPLIER_PAYMENTS => 'Suppliers',
        };
    }

    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $type) {
            $category = $type->category();
            $grouped[$category][] = $type;
        }
        return $grouped;
    }
}
```

### WidgetType Enum

**File:** `app/Enums/WidgetType.php`

```php
<?php

namespace App\Enums;

enum WidgetType: string
{
    // Summary Cards
    case TOTAL_SALES = 'total_sales';
    case TOTAL_REVENUE = 'total_revenue';
    case TOTAL_ORDERS = 'total_orders';
    case AVERAGE_ORDER = 'average_order';
    case TOTAL_CUSTOMERS = 'total_customers';
    case TOTAL_PRODUCTS = 'total_products';
    case LOW_STOCK_COUNT = 'low_stock_count';
    case PENDING_ORDERS = 'pending_orders';
    
    // Charts
    case SALES_CHART = 'sales_chart';
    case REVENUE_CHART = 'revenue_chart';
    case TOP_PRODUCTS_CHART = 'top_products_chart';
    case TOP_CATEGORIES_CHART = 'top_categories_chart';
    case EXPENSE_CHART = 'expense_chart';
    case PROFIT_CHART = 'profit_chart';
    
    // Tables/Lists
    case RECENT_SALES = 'recent_sales';
    case LOW_STOCK_LIST = 'low_stock_list';
    case RECENT_ALERTS = 'recent_alerts';
    case TOP_CUSTOMERS = 'top_customers';
    case PENDING_APPROVALS = 'pending_approvals';

    public function label(): string
    {
        return match ($this) {
            self::TOTAL_SALES => 'Total Sales',
            self::TOTAL_REVENUE => 'Total Revenue',
            self::TOTAL_ORDERS => 'Total Orders',
            self::AVERAGE_ORDER => 'Average Order Value',
            self::TOTAL_CUSTOMERS => 'Total Customers',
            self::TOTAL_PRODUCTS => 'Total Products',
            self::LOW_STOCK_COUNT => 'Low Stock Items',
            self::PENDING_ORDERS => 'Pending Orders',
            self::SALES_CHART => 'Sales Chart',
            self::REVENUE_CHART => 'Revenue Chart',
            self::TOP_PRODUCTS_CHART => 'Top Products',
            self::TOP_CATEGORIES_CHART => 'Top Categories',
            self::EXPENSE_CHART => 'Expenses Chart',
            self::PROFIT_CHART => 'Profit Chart',
            self::RECENT_SALES => 'Recent Sales',
            self::LOW_STOCK_LIST => 'Low Stock Items',
            self::RECENT_ALERTS => 'Recent Alerts',
            self::TOP_CUSTOMERS => 'Top Customers',
            self::PENDING_APPROVALS => 'Pending Approvals',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::TOTAL_SALES, self::TOTAL_REVENUE, self::TOTAL_ORDERS,
            self::AVERAGE_ORDER, self::TOTAL_CUSTOMERS, self::TOTAL_PRODUCTS,
            self::LOW_STOCK_COUNT, self::PENDING_ORDERS => 'card',
            
            self::SALES_CHART, self::REVENUE_CHART, self::TOP_PRODUCTS_CHART,
            self::TOP_CATEGORIES_CHART, self::EXPENSE_CHART, self::PROFIT_CHART => 'chart',
            
            self::RECENT_SALES, self::LOW_STOCK_LIST, self::RECENT_ALERTS,
            self::TOP_CUSTOMERS, self::PENDING_APPROVALS => 'list',
        };
    }

    public function defaultSize(): string
    {
        return match ($this->category()) {
            'card' => 'small',
            'chart' => 'large',
            'list' => 'medium',
        };
    }
}
```

### ExportFormat Enum

**File:** `app/Enums/ExportFormat.php`

```php
<?php

namespace App\Enums;

enum ExportFormat: string
{
    case PDF = 'pdf';
    case EXCEL = 'excel';
    case CSV = 'csv';
    case JSON = 'json';

    public function label(): string
    {
        return match ($this) {
            self::PDF => 'PDF Document',
            self::EXCEL => 'Excel Spreadsheet',
            self::CSV => 'CSV File',
            self::JSON => 'JSON Data',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::PDF => 'pdf',
            self::EXCEL => 'xlsx',
            self::CSV => 'csv',
            self::JSON => 'json',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::PDF => 'application/pdf',
            self::EXCEL => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CSV => 'text/csv',
            self::JSON => 'application/json',
        };
    }
}
```

---

## 4. Models

### SavedReport Model

**File:** `app/Models/SavedReport.php`

```php
<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SavedReport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'shop_id',
        'name',
        'description',
        'type',
        'filters',
        'columns',
        'group_by',
        'sort_by',
        'sort_direction',
        'is_scheduled',
        'schedule_frequency',
        'schedule_recipients',
        'last_run_at',
        'next_run_at',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'filters' => 'array',
            'columns' => 'array',
            'schedule_recipients' => 'array',
            'is_scheduled' => 'boolean',
            'is_public' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $report) {
            if (empty($report->uuid)) {
                $report->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true);
        });
    }

    public function scopeScheduled($query)
    {
        return $query->where('is_scheduled', true);
    }
}
```

### DashboardWidget Model

**File:** `app/Models/DashboardWidget.php`

```php
<?php

namespace App\Models;

use App\Enums\WidgetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DashboardWidget extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'widget_type',
        'size',
        'position',
        'row',
        'column',
        'config',
        'filters',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'widget_type' => WidgetType::class,
            'config' => 'array',
            'filters' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $widget) {
            if (empty($widget->uuid)) {
                $widget->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('row')->orderBy('column')->orderBy('position');
    }
}
```

### ReportExport Model

**File:** `app/Models/ReportExport.php`

```php
<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportExport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'saved_report_id',
        'name',
        'type',
        'format',
        'status',
        'filters',
        'file_path',
        'file_size',
        'row_count',
        'started_at',
        'completed_at',
        'error_message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'format' => ExportFormat::class,
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $export) {
            if (empty($export->uuid)) {
                $export->uuid = (string) Str::uuid();
            }
            $export->expires_at = now()->addDays(7);
        });

        static::deleting(function (self $export) {
            if ($export->file_path) {
                Storage::disk('reports')->delete($export->file_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path || $this->status !== 'completed') {
            return null;
        }
        return Storage::disk('reports')->url($this->file_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $filePath, int $fileSize, int $rowCount): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'row_count' => $rowCount,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
```

---

## 5. Services

### DashboardService

**File:** `app/Services/DashboardService.php`

```php
<?php

namespace App\Services;

use App\Enums\WidgetType;
use App\Models\DashboardWidget;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Get widgets for user
     */
    public function getWidgets(User $user): Collection
    {
        return DashboardWidget::where('user_id', $user->id)
            ->visible()
            ->ordered()
            ->get();
    }

    /**
     * Get widget data
     */
    public function getWidgetData(DashboardWidget $widget, ?Shop $shop = null): array
    {
        return match ($widget->widget_type) {
            WidgetType::TOTAL_SALES => $this->getTotalSales($shop, $widget->filters),
            WidgetType::TOTAL_REVENUE => $this->getTotalRevenue($shop, $widget->filters),
            WidgetType::TOTAL_ORDERS => $this->getTotalOrders($shop, $widget->filters),
            WidgetType::AVERAGE_ORDER => $this->getAverageOrder($shop, $widget->filters),
            WidgetType::TOTAL_CUSTOMERS => $this->getTotalCustomers($shop),
            WidgetType::TOTAL_PRODUCTS => $this->getTotalProducts($shop),
            WidgetType::LOW_STOCK_COUNT => $this->getLowStockCount($shop),
            WidgetType::PENDING_ORDERS => $this->getPendingOrders($shop),
            WidgetType::SALES_CHART => $this->getSalesChart($shop, $widget->config),
            WidgetType::REVENUE_CHART => $this->getRevenueChart($shop, $widget->config),
            WidgetType::TOP_PRODUCTS_CHART => $this->getTopProductsChart($shop, $widget->config),
            WidgetType::TOP_CATEGORIES_CHART => $this->getTopCategoriesChart($shop, $widget->config),
            WidgetType::EXPENSE_CHART => $this->getExpenseChart($shop, $widget->config),
            WidgetType::PROFIT_CHART => $this->getProfitChart($shop, $widget->config),
            WidgetType::RECENT_SALES => $this->getRecentSales($shop, $widget->config),
            WidgetType::LOW_STOCK_LIST => $this->getLowStockList($shop, $widget->config),
            WidgetType::RECENT_ALERTS => $this->getRecentAlerts($shop, $widget->config),
            WidgetType::TOP_CUSTOMERS => $this->getTopCustomersList($shop, $widget->config),
            WidgetType::PENDING_APPROVALS => $this->getPendingApprovalsList($shop),
        };
    }

    private function getTotalSales(?Shop $shop, ?array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        $dates = $this->getPeriodDates($period);

        $total = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$dates['start'], $dates['end']])
            ->sum('total_amount');

        $previousTotal = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$dates['prev_start'], $dates['prev_end']])
            ->sum('total_amount');

        $change = $previousTotal > 0 ? (($total - $previousTotal) / $previousTotal) * 100 : 0;

        return [
            'value' => $total,
            'formatted' => number_format($total, 2),
            'change' => round($change, 1),
            'period' => $period,
        ];
    }

    private function getTotalRevenue(?Shop $shop, ?array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        $dates = $this->getPeriodDates($period);

        $revenue = \App\Models\Payment::query()
            ->whereHas('sale', fn ($q) => $q->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id)))
            ->whereBetween('payment_date', [$dates['start'], $dates['end']])
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'value' => $revenue,
            'formatted' => number_format($revenue, 2),
            'period' => $period,
        ];
    }

    private function getTotalOrders(?Shop $shop, ?array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        $dates = $this->getPeriodDates($period);

        $count = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$dates['start'], $dates['end']])
            ->count();

        return [
            'value' => $count,
            'formatted' => number_format($count),
            'period' => $period,
        ];
    }

    private function getAverageOrder(?Shop $shop, ?array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        $dates = $this->getPeriodDates($period);

        $average = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$dates['start'], $dates['end']])
            ->avg('total_amount') ?? 0;

        return [
            'value' => $average,
            'formatted' => number_format($average, 2),
            'period' => $period,
        ];
    }

    private function getTotalCustomers(?Shop $shop): array
    {
        $count = \App\Models\User::role('customer')
            ->when($shop, fn ($q) => $q->whereHas('shops', fn ($q2) => $q2->where('shops.id', $shop->id)))
            ->count();

        return [
            'value' => $count,
            'formatted' => number_format($count),
        ];
    }

    private function getTotalProducts(?Shop $shop): array
    {
        $count = \App\Models\Product::active()
            ->when($shop, fn ($q) => $q->whereHas('shopInventories', fn ($q2) => $q2->where('shop_id', $shop->id)))
            ->count();

        return [
            'value' => $count,
            'formatted' => number_format($count),
        ];
    }

    private function getLowStockCount(?Shop $shop): array
    {
        $count = \App\Models\ShopInventory::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->count();

        return [
            'value' => $count,
            'formatted' => number_format($count),
            'severity' => $count > 10 ? 'danger' : ($count > 5 ? 'warning' : 'success'),
        ];
    }

    private function getPendingOrders(?Shop $shop): array
    {
        $count = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->where('status', 'pending')
            ->count();

        return [
            'value' => $count,
            'formatted' => number_format($count),
        ];
    }

    private function getSalesChart(?Shop $shop, ?array $config): array
    {
        $days = $config['days'] ?? 30;
        $data = collect();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $total = \App\Models\Sale::query()
                ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
                ->whereDate('sale_date', $date)
                ->sum('total_amount');

            $data->push([
                'date' => $date->format('M d'),
                'value' => $total,
            ]);
        }

        return [
            'labels' => $data->pluck('date'),
            'data' => $data->pluck('value'),
            'type' => 'line',
        ];
    }

    private function getRevenueChart(?Shop $shop, ?array $config): array
    {
        $months = $config['months'] ?? 12;
        $data = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = \App\Models\Payment::query()
                ->whereHas('sale', fn ($q) => $q->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id)))
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->where('status', 'completed')
                ->sum('amount');

            $data->push([
                'month' => $date->format('M Y'),
                'value' => $revenue,
            ]);
        }

        return [
            'labels' => $data->pluck('month'),
            'data' => $data->pluck('value'),
            'type' => 'bar',
        ];
    }

    private function getTopProductsChart(?Shop $shop, ?array $config): array
    {
        $limit = $config['limit'] ?? 10;

        $products = \App\Models\SaleItem::query()
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('sale', fn ($q) => $q->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id))
                ->whereMonth('sale_date', now()->month))
            ->groupBy('product_id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->with('product')
            ->get();

        return [
            'labels' => $products->map(fn ($p) => $p->product->name),
            'data' => $products->pluck('total_sales'),
            'type' => 'bar',
        ];
    }

    private function getTopCategoriesChart(?Shop $shop, ?array $config): array
    {
        $limit = $config['limit'] ?? 8;

        $categories = \App\Models\SaleItem::query()
            ->selectRaw('category_id, SUM(total_price) as total_sales')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereHas('sale', fn ($q) => $q->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id))
                ->whereMonth('sale_date', now()->month))
            ->groupBy('category_id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get();

        $categoryNames = \App\Models\Category::whereIn('id', $categories->pluck('category_id'))
            ->pluck('name', 'id');

        return [
            'labels' => $categories->map(fn ($c) => $categoryNames[$c->category_id] ?? 'Unknown'),
            'data' => $categories->pluck('total_sales'),
            'type' => 'doughnut',
        ];
    }

    private function getExpenseChart(?Shop $shop, ?array $config): array
    {
        $months = $config['months'] ?? 6;
        $data = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $expenses = \App\Models\Expense::approved()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->whereMonth('expense_date', $date->month)
                ->whereYear('expense_date', $date->year)
                ->sum('amount');

            $data->push([
                'month' => $date->format('M Y'),
                'value' => $expenses,
            ]);
        }

        return [
            'labels' => $data->pluck('month'),
            'data' => $data->pluck('value'),
            'type' => 'bar',
        ];
    }

    private function getProfitChart(?Shop $shop, ?array $config): array
    {
        $months = $config['months'] ?? 6;
        $data = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            
            $revenue = \App\Models\Sale::query()
                ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
                ->whereMonth('sale_date', $date->month)
                ->whereYear('sale_date', $date->year)
                ->sum('total_amount');

            $expenses = \App\Models\Expense::approved()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->whereMonth('expense_date', $date->month)
                ->whereYear('expense_date', $date->year)
                ->sum('amount');

            $data->push([
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => $revenue - $expenses,
            ]);
        }

        return [
            'labels' => $data->pluck('month'),
            'datasets' => [
                ['label' => 'Revenue', 'data' => $data->pluck('revenue')],
                ['label' => 'Expenses', 'data' => $data->pluck('expenses')],
                ['label' => 'Profit', 'data' => $data->pluck('profit')],
            ],
            'type' => 'bar',
        ];
    }

    private function getRecentSales(?Shop $shop, ?array $config): array
    {
        $limit = $config['limit'] ?? 10;

        $sales = \App\Models\Sale::with(['customer', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->latest('sale_date')
            ->limit($limit)
            ->get();

        return ['items' => $sales];
    }

    private function getLowStockList(?Shop $shop, ?array $config): array
    {
        $limit = $config['limit'] ?? 10;

        $items = \App\Models\ShopInventory::with(['product', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->orderBy('quantity')
            ->limit($limit)
            ->get();

        return ['items' => $items];
    }

    private function getRecentAlerts(?Shop $shop, ?array $config): array
    {
        $limit = $config['limit'] ?? 5;

        $alerts = \App\Models\Alert::with(['shop'])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->unresolved()
            ->active()
            ->latest()
            ->limit($limit)
            ->get();

        return ['items' => $alerts];
    }

    private function getTopCustomersList(?Shop $shop, ?array $config): array
    {
        $limit = $config['limit'] ?? 10;

        $customers = \App\Models\Sale::query()
            ->selectRaw('customer_id, COUNT(*) as order_count, SUM(total_amount) as total_spent')
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereNotNull('customer_id')
            ->whereMonth('sale_date', now()->month)
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->with('customer')
            ->get();

        return ['items' => $customers];
    }

    private function getPendingApprovalsList(?Shop $shop): array
    {
        $expenses = \App\Models\Expense::with(['category', 'createdBy'])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->pending()
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        return ['items' => $expenses];
    }

    private function getPeriodDates(string $period): array
    {
        return match ($period) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
                'prev_start' => now()->subDay()->startOfDay(),
                'prev_end' => now()->subDay()->endOfDay(),
            ],
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
                'prev_start' => now()->subWeek()->startOfWeek(),
                'prev_end' => now()->subWeek()->endOfWeek(),
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
                'prev_start' => now()->subMonth()->startOfMonth(),
                'prev_end' => now()->subMonth()->endOfMonth(),
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
                'prev_start' => now()->subYear()->startOfYear(),
                'prev_end' => now()->subYear()->endOfYear(),
            ],
            default => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
                'prev_start' => now()->subMonth()->startOfMonth(),
                'prev_end' => now()->subMonth()->endOfMonth(),
            ],
        };
    }

    /**
     * Create default widgets for new user
     */
    public function createDefaultWidgets(User $user): void
    {
        $defaults = [
            ['widget_type' => WidgetType::TOTAL_SALES, 'size' => 'small', 'row' => 0, 'column' => 0],
            ['widget_type' => WidgetType::TOTAL_REVENUE, 'size' => 'small', 'row' => 0, 'column' => 1],
            ['widget_type' => WidgetType::TOTAL_ORDERS, 'size' => 'small', 'row' => 0, 'column' => 2],
            ['widget_type' => WidgetType::LOW_STOCK_COUNT, 'size' => 'small', 'row' => 0, 'column' => 3],
            ['widget_type' => WidgetType::SALES_CHART, 'size' => 'large', 'row' => 1, 'column' => 0],
            ['widget_type' => WidgetType::TOP_PRODUCTS_CHART, 'size' => 'medium', 'row' => 1, 'column' => 2],
            ['widget_type' => WidgetType::RECENT_SALES, 'size' => 'medium', 'row' => 2, 'column' => 0],
            ['widget_type' => WidgetType::RECENT_ALERTS, 'size' => 'medium', 'row' => 2, 'column' => 1],
        ];

        foreach ($defaults as $index => $widget) {
            DashboardWidget::create([
                'user_id' => $user->id,
                'title' => $widget['widget_type']->label(),
                'widget_type' => $widget['widget_type'],
                'size' => $widget['size'],
                'position' => $index,
                'row' => $widget['row'],
                'column' => $widget['column'],
            ]);
        }
    }
}
```

### ReportService

**File:** `app/Services/ReportService.php`

```php
<?php

namespace App\Services;

use App\Enums\ExportFormat;
use App\Enums\ReportType;
use App\Models\ReportExport;
use App\Models\SavedReport;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Generate report data
     */
    public function generate(ReportType $type, array $filters = [], ?Shop $shop = null): array
    {
        return match ($type) {
            ReportType::SALES_SUMMARY => $this->salesSummary($filters, $shop),
            ReportType::SALES_BY_PRODUCT => $this->salesByProduct($filters, $shop),
            ReportType::SALES_BY_CATEGORY => $this->salesByCategory($filters, $shop),
            ReportType::SALES_BY_CUSTOMER => $this->salesByCustomer($filters, $shop),
            ReportType::SALES_BY_STAFF => $this->salesByStaff($filters, $shop),
            ReportType::SALES_TREND => $this->salesTrend($filters, $shop),
            ReportType::INVENTORY_VALUATION => $this->inventoryValuation($filters, $shop),
            ReportType::STOCK_MOVEMENT => $this->stockMovement($filters, $shop),
            ReportType::LOW_STOCK => $this->lowStock($filters, $shop),
            ReportType::PROFIT_LOSS => $this->profitLoss($filters, $shop),
            ReportType::EXPENSE_SUMMARY => $this->expenseSummary($filters, $shop),
            ReportType::EXPENSE_BY_CATEGORY => $this->expenseByCategory($filters, $shop),
            ReportType::PAYMENTS_RECEIVED => $this->paymentsReceived($filters, $shop),
            ReportType::OUTSTANDING_PAYMENTS => $this->outstandingPayments($filters, $shop),
            ReportType::CREDIT_AGING => $this->creditAging($filters, $shop),
            default => ['error' => 'Report type not implemented'],
        };
    }

    private function salesSummary(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $sales = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->get();

        return [
            'title' => 'Sales Summary',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'summary' => [
                'total_sales' => $sales->sum('total_amount'),
                'total_orders' => $sales->count(),
                'average_order' => $sales->avg('total_amount') ?? 0,
                'total_items_sold' => $sales->sum(fn ($s) => $s->items->sum('quantity')),
            ],
            'by_status' => $sales->groupBy('status')->map->count(),
            'by_payment_status' => $sales->groupBy('payment_status')->map->count(),
        ];
    }

    private function salesByProduct(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $data = \App\Models\SaleItem::query()
            ->selectRaw('product_id, SUM(quantity) as qty_sold, SUM(total_price) as revenue')
            ->whereHas('sale', fn ($q) => $q
                ->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id))
                ->whereBetween('sale_date', [$startDate, $endDate]))
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->with('product')
            ->get();

        return [
            'title' => 'Sales by Product',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Product', 'SKU', 'Quantity Sold', 'Revenue'],
            'data' => $data->map(fn ($item) => [
                'product' => $item->product->name,
                'sku' => $item->product->sku,
                'quantity' => $item->qty_sold,
                'revenue' => $item->revenue,
            ]),
            'totals' => [
                'quantity' => $data->sum('qty_sold'),
                'revenue' => $data->sum('revenue'),
            ],
        ];
    }

    private function salesByCategory(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $data = \App\Models\SaleItem::query()
            ->selectRaw('products.category_id, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.total_price) as revenue')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereHas('sale', fn ($q) => $q
                ->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id))
                ->whereBetween('sale_date', [$startDate, $endDate]))
            ->groupBy('products.category_id')
            ->orderByDesc('revenue')
            ->get();

        $categories = \App\Models\Category::whereIn('id', $data->pluck('category_id'))->pluck('name', 'id');

        return [
            'title' => 'Sales by Category',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Category', 'Items Sold', 'Revenue', '% of Total'],
            'data' => $data->map(fn ($item) => [
                'category' => $categories[$item->category_id] ?? 'Unknown',
                'quantity' => $item->qty_sold,
                'revenue' => $item->revenue,
                'percentage' => $data->sum('revenue') > 0 
                    ? round(($item->revenue / $data->sum('revenue')) * 100, 1) 
                    : 0,
            ]),
            'totals' => [
                'quantity' => $data->sum('qty_sold'),
                'revenue' => $data->sum('revenue'),
            ],
        ];
    }

    private function salesByCustomer(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $data = \App\Models\Sale::query()
            ->selectRaw('customer_id, COUNT(*) as order_count, SUM(total_amount) as total_spent')
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->with('customer')
            ->get();

        return [
            'title' => 'Sales by Customer',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Customer', 'Orders', 'Total Spent', 'Average Order'],
            'data' => $data->map(fn ($item) => [
                'customer' => $item->customer?->name ?? 'Unknown',
                'orders' => $item->order_count,
                'total' => $item->total_spent,
                'average' => $item->order_count > 0 ? $item->total_spent / $item->order_count : 0,
            ]),
        ];
    }

    private function salesByStaff(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $data = \App\Models\Sale::query()
            ->selectRaw('created_by, COUNT(*) as order_count, SUM(total_amount) as total_sales')
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->groupBy('created_by')
            ->orderByDesc('total_sales')
            ->get();

        $users = User::whereIn('id', $data->pluck('created_by'))->pluck('name', 'id');

        return [
            'title' => 'Sales by Staff',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Staff Member', 'Orders', 'Total Sales', 'Average Sale'],
            'data' => $data->map(fn ($item) => [
                'staff' => $users[$item->created_by] ?? 'Unknown',
                'orders' => $item->order_count,
                'total' => $item->total_sales,
                'average' => $item->order_count > 0 ? $item->total_sales / $item->order_count : 0,
            ]),
        ];
    }

    private function salesTrend(array $filters, ?Shop $shop): array
    {
        $period = $filters['period'] ?? 'daily';
        $startDate = Carbon::parse($filters['start_date'] ?? now()->subDays(30));
        $endDate = Carbon::parse($filters['end_date'] ?? now());

        $data = collect();

        if ($period === 'daily') {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $total = \App\Models\Sale::query()
                    ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
                    ->whereDate('sale_date', $current)
                    ->sum('total_amount');

                $data->push([
                    'date' => $current->format('Y-m-d'),
                    'label' => $current->format('M d'),
                    'total' => $total,
                ]);

                $current->addDay();
            }
        } else {
            $current = $startDate->copy()->startOfMonth();
            while ($current <= $endDate) {
                $total = \App\Models\Sale::query()
                    ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
                    ->whereMonth('sale_date', $current->month)
                    ->whereYear('sale_date', $current->year)
                    ->sum('total_amount');

                $data->push([
                    'date' => $current->format('Y-m'),
                    'label' => $current->format('M Y'),
                    'total' => $total,
                ]);

                $current->addMonth();
            }
        }

        return [
            'title' => 'Sales Trend',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'data' => $data,
            'chart_data' => [
                'labels' => $data->pluck('label'),
                'values' => $data->pluck('total'),
            ],
        ];
    }

    private function inventoryValuation(array $filters, ?Shop $shop): array
    {
        $data = \App\Models\ShopInventory::with(['product', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->get()
            ->map(fn ($inv) => [
                'product' => $inv->product->name,
                'sku' => $inv->product->sku,
                'shop' => $inv->shop->name,
                'quantity' => $inv->quantity,
                'unit_cost' => $inv->product->cost_price ?? 0,
                'total_value' => $inv->quantity * ($inv->product->cost_price ?? 0),
            ]);

        return [
            'title' => 'Inventory Valuation',
            'as_of' => now()->format('M d, Y H:i'),
            'columns' => ['Product', 'SKU', 'Shop', 'Quantity', 'Unit Cost', 'Total Value'],
            'data' => $data,
            'totals' => [
                'quantity' => $data->sum('quantity'),
                'value' => $data->sum('total_value'),
            ],
        ];
    }

    private function stockMovement(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());
        $productId = $filters['product_id'] ?? null;

        $movements = \App\Models\InventoryMovement::with(['product', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'title' => 'Stock Movement Report',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Date', 'Product', 'Type', 'Quantity', 'Before', 'After', 'Reference'],
            'data' => $movements->map(fn ($m) => [
                'date' => $m->created_at->format('Y-m-d H:i'),
                'product' => $m->product->name,
                'type' => $m->movement_type,
                'quantity' => $m->quantity,
                'before' => $m->quantity_before,
                'after' => $m->quantity_after,
                'reference' => $m->reference,
            ]),
        ];
    }

    private function lowStock(array $filters, ?Shop $shop): array
    {
        $data = \App\Models\ShopInventory::with(['product', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->orderBy('quantity')
            ->get();

        return [
            'title' => 'Low Stock Report',
            'as_of' => now()->format('M d, Y H:i'),
            'columns' => ['Product', 'SKU', 'Shop', 'Current Stock', 'Reorder Point', 'Shortage'],
            'data' => $data->map(fn ($inv) => [
                'product' => $inv->product->name,
                'sku' => $inv->product->sku,
                'shop' => $inv->shop->name,
                'quantity' => $inv->quantity,
                'reorder_point' => $inv->reorder_point,
                'shortage' => max(0, $inv->reorder_point - $inv->quantity),
            ]),
            'total_items' => $data->count(),
        ];
    }

    private function profitLoss(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        // Revenue
        $revenue = \App\Models\Sale::query()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Cost of Goods Sold
        $cogs = \App\Models\SaleItem::query()
            ->whereHas('sale', fn ($q) => $q
                ->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id))
                ->whereBetween('sale_date', [$startDate, $endDate]))
            ->get()
            ->sum(fn ($item) => $item->quantity * ($item->product->cost_price ?? 0));

        // Expenses
        $expenses = \App\Models\Expense::approved()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses;

        return [
            'title' => 'Profit & Loss Statement',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0,
            'operating_expenses' => $expenses,
            'net_profit' => $netProfit,
            'net_margin' => $revenue > 0 ? ($netProfit / $revenue) * 100 : 0,
        ];
    }

    private function expenseSummary(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $expenses = \App\Models\Expense::with(['category'])
            ->approved()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        return [
            'title' => 'Expense Summary',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'total_expenses' => $expenses->sum('amount'),
            'expense_count' => $expenses->count(),
            'by_status' => $expenses->groupBy('status')->map->sum('amount'),
            'data' => $expenses->map(fn ($e) => [
                'date' => $e->expense_date->format('Y-m-d'),
                'category' => $e->category->name,
                'description' => $e->title,
                'amount' => $e->amount,
            ]),
        ];
    }

    private function expenseByCategory(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $data = \App\Models\Expense::query()
            ->selectRaw('category_id, COUNT(*) as expense_count, SUM(amount) as total')
            ->approved()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $categories = \App\Models\ExpenseCategory::whereIn('id', $data->pluck('category_id'))
            ->pluck('name', 'id');

        $totalExpenses = $data->sum('total');

        return [
            'title' => 'Expenses by Category',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Category', 'Count', 'Total', '% of Total'],
            'data' => $data->map(fn ($item) => [
                'category' => $categories[$item->category_id] ?? 'Unknown',
                'count' => $item->expense_count,
                'total' => $item->total,
                'percentage' => $totalExpenses > 0 ? round(($item->total / $totalExpenses) * 100, 1) : 0,
            ]),
            'totals' => [
                'count' => $data->sum('expense_count'),
                'total' => $totalExpenses,
            ],
        ];
    }

    private function paymentsReceived(array $filters, ?Shop $shop): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth());

        $payments = \App\Models\Payment::with(['sale.customer'])
            ->whereHas('sale', fn ($q) => $q->when($shop, fn ($q2) => $q2->where('shop_id', $shop->id)))
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->orderBy('payment_date', 'desc')
            ->get();

        return [
            'title' => 'Payments Received',
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'columns' => ['Date', 'Sale #', 'Customer', 'Method', 'Amount'],
            'data' => $payments->map(fn ($p) => [
                'date' => $p->payment_date->format('Y-m-d'),
                'sale_number' => $p->sale->sale_number,
                'customer' => $p->sale->customer?->name ?? 'Walk-in',
                'method' => $p->payment_method->label(),
                'amount' => $p->amount,
            ]),
            'totals' => [
                'total' => $payments->sum('amount'),
                'by_method' => $payments->groupBy('payment_method')->map->sum('amount'),
            ],
        ];
    }

    private function outstandingPayments(array $filters, ?Shop $shop): array
    {
        $sales = \App\Models\Sale::with(['customer'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->where('payment_status', '!=', 'paid')
            ->orderBy('sale_date')
            ->get();

        return [
            'title' => 'Outstanding Payments',
            'as_of' => now()->format('M d, Y'),
            'columns' => ['Sale #', 'Date', 'Customer', 'Total', 'Paid', 'Outstanding'],
            'data' => $sales->map(fn ($s) => [
                'sale_number' => $s->sale_number,
                'date' => $s->sale_date->format('Y-m-d'),
                'customer' => $s->customer?->name ?? 'Walk-in',
                'total' => $s->total_amount,
                'paid' => $s->paid_amount,
                'outstanding' => $s->balance,
            ]),
            'totals' => [
                'total_outstanding' => $sales->sum('balance'),
            ],
        ];
    }

    private function creditAging(array $filters, ?Shop $shop): array
    {
        $accounts = \App\Models\CreditAccount::with(['customer'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->where('current_balance', '>', 0)
            ->get();

        // Group by aging buckets
        $buckets = [
            'current' => 0,
            '1-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            'over_90' => 0,
        ];

        foreach ($accounts as $account) {
            $lastPayment = $account->transactions()
                ->where('type', 'payment')
                ->latest()
                ->first();

            $days = $lastPayment 
                ? $lastPayment->created_at->diffInDays(now()) 
                : $account->created_at->diffInDays(now());

            match (true) {
                $days <= 0 => $buckets['current'] += $account->current_balance,
                $days <= 30 => $buckets['1-30'] += $account->current_balance,
                $days <= 60 => $buckets['31-60'] += $account->current_balance,
                $days <= 90 => $buckets['61-90'] += $account->current_balance,
                default => $buckets['over_90'] += $account->current_balance,
            };
        }

        return [
            'title' => 'Credit Aging Report',
            'as_of' => now()->format('M d, Y'),
            'buckets' => [
                ['label' => 'Current', 'amount' => $buckets['current']],
                ['label' => '1-30 Days', 'amount' => $buckets['1-30']],
                ['label' => '31-60 Days', 'amount' => $buckets['31-60']],
                ['label' => '61-90 Days', 'amount' => $buckets['61-90']],
                ['label' => 'Over 90 Days', 'amount' => $buckets['over_90']],
            ],
            'total_outstanding' => array_sum($buckets),
            'accounts' => $accounts->count(),
        ];
    }

    /**
     * Save report configuration
     */
    public function saveReport(User $user, array $data): SavedReport
    {
        return SavedReport::create([
            'user_id' => $user->id,
            ...$data,
        ]);
    }

    /**
     * Export report
     */
    public function export(ReportType $type, ExportFormat $format, array $filters, User $user): ReportExport
    {
        return ReportExport::create([
            'user_id' => $user->id,
            'name' => $type->label() . ' - ' . now()->format('Y-m-d'),
            'type' => $type,
            'format' => $format,
            'status' => 'pending',
            'filters' => $filters,
        ]);
    }
}
```

---

## 6. Controllers

### DashboardController

**File:** `app/Http/Controllers/DashboardController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\WidgetType;
use App\Models\DashboardWidget;
use App\Models\Shop;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $shopId = $request->get('shop_id');
        $shop = $shopId ? Shop::find($shopId) : null;

        $widgets = $this->dashboardService->getWidgets($user);

        // Ensure default widgets exist
        if ($widgets->isEmpty()) {
            $this->dashboardService->createDefaultWidgets($user);
            $widgets = $this->dashboardService->getWidgets($user);
        }

        $widgetData = [];
        foreach ($widgets as $widget) {
            $widgetData[$widget->id] = $this->dashboardService->getWidgetData($widget, $shop);
        }

        $shops = Shop::active()->get();

        return view('dashboard', compact('widgets', 'widgetData', 'shops', 'shop'));
    }

    public function widgetData(DashboardWidget $widget, Request $request): JsonResponse
    {
        $shop = $request->shop_id ? Shop::find($request->shop_id) : null;
        $data = $this->dashboardService->getWidgetData($widget, $shop);

        return response()->json($data);
    }

    public function updateWidgetPosition(Request $request): JsonResponse
    {
        $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|exists:dashboard_widgets,id',
            'widgets.*.row' => 'required|integer|min:0',
            'widgets.*.column' => 'required|integer|min:0',
        ]);

        foreach ($request->widgets as $widgetData) {
            DashboardWidget::where('id', $widgetData['id'])
                ->where('user_id', auth()->id())
                ->update([
                    'row' => $widgetData['row'],
                    'column' => $widgetData['column'],
                ]);
        }

        return response()->json(['success' => true]);
    }

    public function toggleWidget(DashboardWidget $widget): JsonResponse
    {
        $this->authorize('update', $widget);

        $widget->update(['is_visible' => !$widget->is_visible]);

        return response()->json(['success' => true, 'is_visible' => $widget->is_visible]);
    }

    public function addWidget(Request $request): JsonResponse
    {
        $request->validate([
            'widget_type' => 'required|string',
            'title' => 'nullable|string|max:100',
        ]);

        $type = WidgetType::from($request->widget_type);

        $widget = DashboardWidget::create([
            'user_id' => auth()->id(),
            'title' => $request->title ?? $type->label(),
            'widget_type' => $type,
            'size' => $type->defaultSize(),
        ]);

        return response()->json(['success' => true, 'widget' => $widget]);
    }

    public function removeWidget(DashboardWidget $widget): JsonResponse
    {
        $this->authorize('delete', $widget);
        $widget->delete();

        return response()->json(['success' => true]);
    }
}
```

### ReportController

**File:** `app/Http/Controllers/ReportController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ExportFormat;
use App\Enums\ReportType;
use App\Http\Requests\GenerateReportRequest;
use App\Http\Requests\SaveReportRequest;
use App\Models\ReportExport;
use App\Models\SavedReport;
use App\Models\Shop;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function index(): View
    {
        $this->authorize('viewAny', SavedReport::class);

        $reportTypes = ReportType::byCategory();
        $savedReports = SavedReport::forUser(auth()->id())
            ->latest()
            ->limit(10)
            ->get();
        $recentExports = ReportExport::where('user_id', auth()->id())
            ->latest()
            ->limit(5)
            ->get();

        return view('reports.index', compact('reportTypes', 'savedReports', 'recentExports'));
    }

    public function generate(GenerateReportRequest $request): View
    {
        $type = ReportType::from($request->type);
        $shop = $request->shop_id ? Shop::find($request->shop_id) : null;

        $reportData = $this->reportService->generate($type, $request->validated(), $shop);

        $shops = Shop::active()->get();

        return view('reports.view', compact('type', 'reportData', 'shops'));
    }

    public function saved(): View
    {
        $reports = SavedReport::forUser(auth()->id())
            ->with(['shop', 'user'])
            ->latest()
            ->paginate(20);

        return view('reports.saved', compact('reports'));
    }

    public function save(SaveReportRequest $request): RedirectResponse
    {
        $report = $this->reportService->saveReport(auth()->user(), $request->validated());

        return redirect()->route('reports.saved')
            ->with('success', 'Report saved successfully.');
    }

    public function show(SavedReport $report): View
    {
        $this->authorize('view', $report);

        $shop = $report->shop;
        $reportData = $this->reportService->generate($report->type, $report->filters ?? [], $shop);

        return view('reports.view', [
            'type' => $report->type,
            'reportData' => $reportData,
            'savedReport' => $report,
        ]);
    }

    public function export(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => 'required|string',
            'format' => 'required|string',
            'filters' => 'nullable|array',
        ]);

        $export = $this->reportService->export(
            ReportType::from($request->type),
            ExportFormat::from($request->format),
            $request->filters ?? [],
            auth()->user()
        );

        // Dispatch export job
        \App\Jobs\ProcessReportExport::dispatch($export);

        return back()->with('success', 'Export started. You will be notified when it is ready.');
    }

    public function exports(): View
    {
        $exports = ReportExport::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('reports.exports', compact('exports'));
    }

    public function download(ReportExport $export)
    {
        $this->authorize('download', $export);

        if ($export->status !== 'completed' || !$export->file_path) {
            return back()->with('error', 'Export is not ready for download.');
        }

        if ($export->isExpired()) {
            return back()->with('error', 'Export has expired.');
        }

        return \Storage::disk('reports')->download(
            $export->file_path,
            $export->name . '.' . $export->format->extension()
        );
    }
}
```

---

## 7. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/widget/{widget}/data', [DashboardController::class, 'widgetData'])->name('dashboard.widget-data');
    Route::post('/dashboard/widgets/positions', [DashboardController::class, 'updateWidgetPosition'])->name('dashboard.update-positions');
    Route::post('/dashboard/widgets/{widget}/toggle', [DashboardController::class, 'toggleWidget'])->name('dashboard.toggle-widget');
    Route::post('/dashboard/widgets', [DashboardController::class, 'addWidget'])->name('dashboard.add-widget');
    Route::delete('/dashboard/widgets/{widget}', [DashboardController::class, 'removeWidget'])->name('dashboard.remove-widget');

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('/generate', [ReportController::class, 'generate'])->name('generate');
        Route::get('/saved', [ReportController::class, 'saved'])->name('saved');
        Route::post('/save', [ReportController::class, 'save'])->name('save');
        Route::get('/saved/{report}', [ReportController::class, 'show'])->name('show');
        Route::post('/export', [ReportController::class, 'export'])->name('export');
        Route::get('/exports', [ReportController::class, 'exports'])->name('exports');
        Route::get('/exports/{export}/download', [ReportController::class, 'download'])->name('download');
    });
});
```

---

## 8. Permissions

```php
'reports.view',
'reports.create',
'reports.export',
'reports.schedule',
'dashboard.customize',
```

---

## 9. Tests

**File:** `tests/Feature/ReportsTest.php`

```php
<?php

use App\Enums\ReportType;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReportService;

test('sales summary report generates correctly', function () {
    $shop = Shop::factory()->create();
    Sale::factory()->count(5)->create([
        'shop_id' => $shop->id,
        'total_amount' => 1000,
    ]);

    $service = app(ReportService::class);
    $report = $service->generate(ReportType::SALES_SUMMARY, [
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ], $shop);

    expect($report)
        ->toHaveKey('summary')
        ->and($report['summary']['total_sales'])->toBe(5000.0);
});

test('low stock report shows items below reorder point', function () {
    $shop = Shop::factory()->create();
    
    \App\Models\ShopInventory::factory()->create([
        'shop_id' => $shop->id,
        'quantity' => 5,
        'reorder_point' => 10,
    ]);

    $service = app(ReportService::class);
    $report = $service->generate(ReportType::LOW_STOCK, [], $shop);

    expect($report['data'])->not->toBeEmpty();
});

test('profit loss calculates correctly', function () {
    $shop = Shop::factory()->create();
    
    Sale::factory()->create([
        'shop_id' => $shop->id,
        'total_amount' => 10000,
    ]);

    \App\Models\Expense::factory()->approved()->create([
        'shop_id' => $shop->id,
        'amount' => 3000,
    ]);

    $service = app(ReportService::class);
    $report = $service->generate(ReportType::PROFIT_LOSS, [
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ], $shop);

    expect($report['revenue'])->toBe(10000.0);
    expect($report['operating_expenses'])->toBe(3000.0);
});
```

---

## 10. Verification Checklist

- [ ] All tables have `uuid` column with `getRouteKeyName()`
- [ ] Dashboard widgets customizable
- [ ] Widget data loads correctly
- [ ] All report types generate data
- [ ] Report filtering works
- [ ] Report export queued
- [ ] Saved reports work
- [ ] PDF/Excel/CSV exports work
- [ ] Charts render correctly
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass

---

## 11. Next Steps

→ **[Module 18: Audit Logs](./18-audit-logs.md)**
