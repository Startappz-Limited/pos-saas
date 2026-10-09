# Module 10: Sales & Transactions
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Complete sales transaction system with FIFO stock deduction, profit tracking per batch, partial payments, and credit sales support. This is the core revenue-generating module.

**Priority:** P0 (Critical)  
**Dependencies:** Module 05, Module 08, Module 09  
**Estimated Time:** 3-4 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns, Enum status |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Auditable trait on Sale model |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Sales Table (Invoice/Receipt Header)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            
            // Sale identifiers
            $table->string('invoice_number')->unique();
            $table->string('sale_type'); // SaleType enum: retail, wholesale
            
            // Amounts
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('discount_type')->nullable(); // DiscountType enum
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Cost & Profit (calculated from line items)
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            
            // Payment tracking
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('payment_status'); // PaymentStatus enum
            
            // Status
            $table->string('status'); // SaleStatus enum
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('uuid');
            $table->index('invoice_number');
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'created_at']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
```

### Sale Items Table (Line Items)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            
            // Quantity & Pricing
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); // Selling price
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2); // (quantity * unit_price) - discount
            
            // Cost tracking (from stock batches)
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('profit_margin', 8, 2)->default(0); // Percentage
            
            // Status
            $table->string('status')->default('pending'); // SaleItemStatus enum
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
```

### Sale Item Stock Allocations (Links to Stock Batches)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->uuid('stock_batch_id');
            
            $table->integer('quantity'); // Quantity from this batch
            $table->decimal('unit_cost', 12, 2); // Cost from this batch
            $table->decimal('total_cost', 12, 2); // quantity * unit_cost
            
            $table->timestamps();
            
            $table->foreign('stock_batch_id')
                ->references('id')
                ->on('stock_batches')
                ->cascadeOnDelete();
            
            $table->index('uuid');
            $table->index('stock_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_stock_allocations');
    }
};
```

### Customers Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            $table->string('code')->unique(); // CUST00001
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_type')->default('retail'); // CustomerType enum
            
            // Credit settings
            $table->boolean('allow_credit')->default(false);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('credit_balance', 15, 2)->default(0); // Current outstanding
            
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('uuid');
            $table->index('code');
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

---

## 3. Enums

### SaleType Enum

**File:** `app/Enums/SaleType.php`

```php
<?php

namespace App\Enums;

enum SaleType: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';

    public function label(): string
    {
        return match ($this) {
            self::RETAIL => 'Retail',
            self::WHOLESALE => 'Wholesale',
        };
    }

    public function priceField(): string
    {
        return match ($this) {
            self::RETAIL => 'retail_price',
            self::WHOLESALE => 'wholesale_price',
        };
    }
}
```

### SaleStatus Enum

**File:** `app/Enums/SaleStatus.php`

```php
<?php

namespace App\Enums;

enum SaleStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::VOIDED => 'Voided',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::VOIDED => 'danger',
            self::REFUNDED => 'info',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    public function canVoid(): bool
    {
        return in_array($this, [self::PENDING, self::COMPLETED]);
    }
}
```

### PaymentStatus Enum

**File:** `app/Enums/PaymentStatus.php`

```php
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Paid',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
            self::REFUNDED => 'info',
        };
    }
}
```

### CustomerType Enum

**File:** `app/Enums/CustomerType.php`

```php
<?php

namespace App\Enums;

enum CustomerType: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';
    case VIP = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::RETAIL => 'Retail Customer',
            self::WHOLESALE => 'Wholesale Customer',
            self::VIP => 'VIP Customer',
        };
    }

    public function defaultSaleType(): SaleType
    {
        return match ($this) {
            self::RETAIL, self::VIP => SaleType::RETAIL,
            self::WHOLESALE => SaleType::WHOLESALE,
        };
    }
}
```

---

## 4. Models

### Sale Model

**File:** `app/Models/Sale.php`

```php
<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'uuid',
        'shop_id',
        'customer_id',
        'invoice_number',
        'sale_type',
        'subtotal',
        'discount_amount',
        'discount_type',
        'tax_amount',
        'total_amount',
        'total_cost',
        'total_profit',
        'paid_amount',
        'balance_due',
        'payment_status',
        'status',
        'notes',
        'completed_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_type' => SaleType::class,
            'status' => SaleStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Sale $sale) {
            if (empty($sale->uuid)) {
                $sale->uuid = (string) Str::uuid();
            }
            if (empty($sale->invoice_number)) {
                $sale->invoice_number = self::generateInvoiceNumber($sale->shop_id);
            }
            if (empty($sale->status)) {
                $sale->status = SaleStatus::DRAFT;
            }
            if (empty($sale->payment_status)) {
                $sale->payment_status = PaymentStatus::UNPAID;
            }
            $sale->created_by = auth()->id();
        });

        static::updating(function (Sale $sale) {
            $sale->updated_by = auth()->id();
        });
    }

    /**
     * Route key for UUID binding
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber(int $shopId): string
    {
        $prefix = 'INV';
        $shopPrefix = str_pad($shopId, 2, '0', STR_PAD_LEFT);
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())
            ->where('shop_id', $shopId)
            ->count() + 1;
        
        return "{$prefix}-{$shopPrefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // Methods

    /**
     * Recalculate totals from line items
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items->sum('line_total');
        $this->total_cost = $this->items->sum('total_cost');
        $this->total_amount = $this->subtotal - $this->discount_amount + $this->tax_amount;
        $this->total_profit = $this->subtotal - $this->total_cost - $this->discount_amount;
        $this->balance_due = $this->total_amount - $this->paid_amount;
        
        $this->updatePaymentStatus();
    }

    /**
     * Update payment status based on amounts
     */
    public function updatePaymentStatus(): void
    {
        if ($this->paid_amount <= 0) {
            $this->payment_status = PaymentStatus::UNPAID;
        } elseif ($this->paid_amount >= $this->total_amount) {
            $this->payment_status = PaymentStatus::PAID;
            $this->balance_due = 0;
        } else {
            $this->payment_status = PaymentStatus::PARTIAL;
        }
    }

    /**
     * Complete the sale
     */
    public function complete(): bool
    {
        $this->status = SaleStatus::COMPLETED;
        $this->completed_at = now();
        
        return $this->save();
    }

    /**
     * Void the sale
     */
    public function void(string $reason): bool
    {
        $this->status = SaleStatus::VOIDED;
        $this->voided_at = now();
        $this->voided_by = auth()->id();
        $this->void_reason = $reason;
        
        return $this->save();
    }

    /**
     * Check if sale is editable
     */
    public function isEditable(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if sale is a credit sale
     */
    public function isCreditSale(): bool
    {
        return $this->status === SaleStatus::COMPLETED 
            && $this->payment_status !== PaymentStatus::PAID;
    }

    /**
     * Get profit margin percentage
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->subtotal <= 0) {
            return 0;
        }
        
        return ($this->total_profit / $this->subtotal) * 100;
    }

    // Scopes

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SaleStatus::COMPLETED);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now());
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('payment_status', [PaymentStatus::UNPAID, PaymentStatus::PARTIAL]);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
```

### SaleItem Model

**File:** `app/Models/SaleItem.php`

```php
<?php

namespace App\Models;

use App\Enums\SaleItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_id',
        'product_id',
        'variation_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
        'unit_cost',
        'total_cost',
        'profit',
        'profit_margin',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'profit' => 'decimal:2',
            'profit_margin' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SaleItem $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
            $item->created_by = auth()->id();
            $item->calculateTotals();
        });

        static::updating(function (SaleItem $item) {
            $item->updated_by = auth()->id();
            $item->calculateTotals();
        });

        static::saved(function (SaleItem $item) {
            $item->sale->recalculateTotals();
            $item->sale->save();
        });

        static::deleted(function (SaleItem $item) {
            $item->sale->recalculateTotals();
            $item->sale->save();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Calculate line totals and profit
     */
    public function calculateTotals(): void
    {
        $this->line_total = ($this->quantity * $this->unit_price) - $this->discount_amount;
        $this->total_cost = $this->quantity * $this->unit_cost;
        $this->profit = $this->line_total - $this->total_cost;
        
        $this->profit_margin = $this->line_total > 0
            ? ($this->profit / $this->line_total) * 100
            : 0;
    }

    // Relationships

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function stockAllocations(): HasMany
    {
        return $this->hasMany(SaleItemStockAllocation::class);
    }
}
```

### SaleItemStockAllocation Model

**File:** `app/Models/SaleItemStockAllocation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SaleItemStockAllocation extends Model
{
    protected $fillable = [
        'uuid',
        'sale_item_id',
        'stock_batch_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $allocation) {
            if (empty($allocation->uuid)) {
                $allocation->uuid = (string) Str::uuid();
            }
            $allocation->total_cost = $allocation->quantity * $allocation->unit_cost;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
```

### Customer Model

**File:** `app/Models/Customer.php`

```php
<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'customer_type',
        'allow_credit',
        'credit_limit',
        'credit_balance',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'customer_type' => CustomerType::class,
            'status' => RecordStatus::class,
            'allow_credit' => 'boolean',
            'credit_limit' => 'decimal:2',
            'credit_balance' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Customer $customer) {
            if (empty($customer->uuid)) {
                $customer->uuid = (string) Str::uuid();
            }
            if (empty($customer->code)) {
                $customer->code = self::generateCode();
            }
            if (empty($customer->status)) {
                $customer->status = RecordStatus::ACTIVE;
            }
            $customer->created_by = auth()->id();
        });

        static::updating(function (Customer $customer) {
            $customer->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateCode(): string
    {
        $lastCustomer = self::orderBy('id', 'desc')->first();
        $nextId = $lastCustomer ? $lastCustomer->id + 1 : 1;
        
        return 'CUST' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // Methods

    public function canPurchaseOnCredit(float $amount): bool
    {
        if (!$this->allow_credit) {
            return false;
        }
        
        return ($this->credit_balance + $amount) <= $this->credit_limit;
    }

    public function availableCredit(): float
    {
        return max(0, $this->credit_limit - $this->credit_balance);
    }

    public function addToCredit(float $amount): bool
    {
        $this->credit_balance += $amount;
        return $this->save();
    }

    public function reduceCredit(float $amount): bool
    {
        $this->credit_balance = max(0, $this->credit_balance - $amount);
        return $this->save();
    }

    // Scopes

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', RecordStatus::ACTIVE);
    }

    public function scopeWithCredit($query)
    {
        return $query->where('allow_credit', true);
    }

    public function scopeWithOutstandingBalance($query)
    {
        return $query->where('credit_balance', '>', 0);
    }
}
```

---

## 5. Actions

### CreateSaleAction

**File:** `app/Actions/Sales/CreateSaleAction.php`

```php
<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;

class CreateSaleAction
{
    public function execute(
        Shop $shop,
        SaleType $saleType,
        ?Customer $customer = null
    ): Sale {
        return Sale::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer?->id,
            'sale_type' => $saleType,
            'status' => SaleStatus::DRAFT,
        ]);
    }
}
```

### AddSaleItemAction

**File:** `app/Actions/Sales/AddSaleItemAction.php`

```php
<?php

namespace App\Actions\Sales;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\StockService;

class AddSaleItemAction
{
    public function __construct(
        private StockService $stockService
    ) {}

    public function execute(
        Sale $sale,
        Product $product,
        int $quantity,
        float $unitPrice,
        ?ProductVariation $variation = null,
        float $discountAmount = 0
    ): SaleItem {
        // Get the weighted average cost from available stock
        $avgCost = $this->stockService->getWeightedAverageCost(
            $product,
            $variation,
            $sale->shop
        );

        return SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'variation_id' => $variation?->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'unit_cost' => $avgCost,
        ]);
    }
}
```

### CompleteSaleAction

**File:** `app/Actions/Sales/CompleteSaleAction.php`

```php
<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemStockAllocation;
use App\Services\InventoryService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class CompleteSaleAction
{
    public function __construct(
        private StockService $stockService,
        private InventoryService $inventoryService
    ) {}

    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            // Allocate stock for each item using FIFO
            foreach ($sale->items as $item) {
                $this->allocateStockForItem($sale, $item);
            }

            // Mark sale as completed
            $sale->complete();

            return $sale->refresh();
        });
    }

    private function allocateStockForItem(Sale $sale, SaleItem $item): void
    {
        $allocations = $this->stockService->deductStock(
            $item->product,
            $item->quantity,
            $item->variation,
            $sale->shop
        );

        $totalCost = 0;

        foreach ($allocations as $allocation) {
            SaleItemStockAllocation::create([
                'sale_item_id' => $item->id,
                'stock_batch_id' => $allocation['batch']->id,
                'quantity' => $allocation['quantity'],
                'unit_cost' => $allocation['cost_per_unit'],
            ]);

            $totalCost += $allocation['quantity'] * $allocation['cost_per_unit'];

            // Record stock movement
            $this->inventoryService->recordSale(
                $sale->shop,
                $item->product,
                $allocation['quantity'],
                $item->variation,
                $allocation['batch'],
                $sale->uuid
            );
        }

        // Update item with actual cost from allocated batches
        $item->update([
            'unit_cost' => $item->quantity > 0 ? $totalCost / $item->quantity : 0,
            'total_cost' => $totalCost,
            'status' => 'completed',
        ]);
    }
}
```

### VoidSaleAction

**File:** `app/Actions/Sales/VoidSaleAction.php`

```php
<?php

namespace App\Actions\Sales;

use App\Enums\StockMovementType;
use App\Models\Sale;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class VoidSaleAction
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function execute(Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            // Restore stock from allocations
            foreach ($sale->items as $item) {
                foreach ($item->stockAllocations as $allocation) {
                    $allocation->stockBatch->restoreStock($allocation->quantity);

                    // Record stock return movement
                    $this->inventoryService->recordMovement(
                        shop: $sale->shop,
                        product: $item->product,
                        type: StockMovementType::RETURN,
                        quantity: $allocation->quantity,
                        variation: $item->variation,
                        batch: $allocation->stockBatch,
                        referenceType: 'sale_void',
                        referenceUuid: $sale->uuid
                    );
                }
            }

            // Handle customer credit if applicable
            if ($sale->customer && $sale->balance_due > 0) {
                $sale->customer->reduceCredit($sale->balance_due);
            }

            // Void the sale
            $sale->void($reason);

            return $sale->refresh();
        });
    }
}
```

---

## 6. Services

### SalesService

**File:** `app/Services/SalesService.php`

```php
<?php

namespace App\Services;

use App\Actions\Sales\AddSaleItemAction;
use App\Actions\Sales\CompleteSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Actions\Sales\VoidSaleAction;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function __construct(
        private CreateSaleAction $createSale,
        private AddSaleItemAction $addItem,
        private CompleteSaleAction $completeSale,
        private VoidSaleAction $voidSale,
        private StockService $stockService
    ) {}

    /**
     * Create a new sale
     */
    public function createSale(
        Shop $shop,
        SaleType $saleType,
        ?Customer $customer = null
    ): Sale {
        return $this->createSale->execute($shop, $saleType, $customer);
    }

    /**
     * Add item to sale with stock validation
     */
    public function addItem(
        Sale $sale,
        Product $product,
        int $quantity,
        ?ProductVariation $variation = null,
        ?float $customPrice = null,
        float $discount = 0
    ): array {
        // Check stock availability
        $available = $this->stockService->getTotalAvailableQuantity(
            $product,
            $variation,
            $sale->shop
        );

        if ($quantity > $available) {
            return [
                'success' => false,
                'error' => "Insufficient stock. Only {$available} available.",
            ];
        }

        // Get price based on sale type
        $unitPrice = $customPrice ?? $this->getProductPrice($product, $sale->sale_type, $sale->shop);

        $item = $this->addItem->execute(
            $sale,
            $product,
            $quantity,
            $unitPrice,
            $variation,
            $discount
        );

        return [
            'success' => true,
            'item' => $item,
        ];
    }

    /**
     * Get product price based on sale type
     */
    public function getProductPrice(Product $product, SaleType $saleType, Shop $shop): float
    {
        $pricing = $product->pricing()
            ->where('shop_id', $shop->id)
            ->active()
            ->first();

        if (!$pricing) {
            return 0;
        }

        return $saleType === SaleType::WHOLESALE
            ? $pricing->wholesale_price
            : $pricing->retail_price;
    }

    /**
     * Apply discount to sale
     */
    public function applyDiscount(Sale $sale, float $amount, string $type = 'fixed'): Sale
    {
        $sale->discount_amount = $type === 'percentage'
            ? ($sale->subtotal * $amount / 100)
            : $amount;
        
        $sale->discount_type = $type;
        $sale->recalculateTotals();
        $sale->save();

        return $sale;
    }

    /**
     * Complete sale and deduct stock
     */
    public function completeSale(Sale $sale): Sale
    {
        if ($sale->items->isEmpty()) {
            throw new \Exception('Cannot complete sale with no items.');
        }

        return $this->completeSale->execute($sale);
    }

    /**
     * Process credit sale
     */
    public function processCreditSale(Sale $sale): Sale
    {
        if (!$sale->customer) {
            throw new \Exception('Credit sale requires a customer.');
        }

        if (!$sale->customer->canPurchaseOnCredit($sale->balance_due)) {
            throw new \Exception('Customer has insufficient credit limit.');
        }

        $sale = $this->completeSale($sale);

        // Add to customer credit balance
        $sale->customer->addToCredit($sale->balance_due);

        return $sale;
    }

    /**
     * Void a sale and restore stock
     */
    public function voidSale(Sale $sale, string $reason): Sale
    {
        if (!$sale->status->canVoid()) {
            throw new \Exception('This sale cannot be voided.');
        }

        return $this->voidSale->execute($sale, $reason);
    }

    /**
     * Get sales summary for period
     */
    public function getSalesSummary(
        ?Shop $shop = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $query = Sale::completed()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->when($startDate && $endDate, fn ($q) => $q->betweenDates($startDate, $endDate));

        return [
            'total_sales' => (clone $query)->count(),
            'total_revenue' => (clone $query)->sum('total_amount'),
            'total_cost' => (clone $query)->sum('total_cost'),
            'total_profit' => (clone $query)->sum('total_profit'),
            'average_sale' => (clone $query)->avg('total_amount'),
            'total_items' => (clone $query)->withCount('items')->get()->sum('items_count'),
        ];
    }

    /**
     * Get unpaid/credit sales
     */
    public function getCreditSales(?Shop $shop = null): Collection
    {
        return Sale::with(['customer', 'items'])
            ->completed()
            ->unpaid()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts(
        ?Shop $shop = null,
        int $limit = 10,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', SaleStatus::COMPLETED->value)
            ->when($shop, fn ($q) => $q->where('sales.shop_id', $shop->id))
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('sales.created_at', [$startDate, $endDate]))
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.line_total) as total_revenue'),
                DB::raw('SUM(sale_items.profit) as total_profit')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }
}
```

---

## 7. Controllers

### SaleController

**File:** `app/Http/Controllers/SaleController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\SaleType;
use App\Http\Requests\AddSaleItemRequest;
use App\Http\Requests\ApplySaleDiscountRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\Shop;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        private SalesService $salesService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $shopId = $request->get('shop_id');
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $sales = Sale::with(['shop', 'customer', 'createdBy'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(20);

        $shops = Shop::active()->get();
        $summary = $this->salesService->getSalesSummary(
            $shopId ? Shop::find($shopId) : null,
            $dateFrom ? \Carbon\Carbon::parse($dateFrom) : now()->startOfMonth(),
            $dateTo ? \Carbon\Carbon::parse($dateTo) : now()
        );

        return view('sales.index', compact('sales', 'shops', 'summary'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Sale::class);

        $shop = Shop::findOrFail($request->get('shop_id', auth()->user()->primaryShop()?->id));
        $customers = Customer::forShop($shop->id)->active()->get();
        $products = Product::forShop($shop->id)->active()->with(['variations', 'pricing'])->get();

        return view('sales.create', compact('shop', 'customers', 'products'));
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $shop = Shop::findOrFail($request->shop_id);
        $customer = $request->customer_id ? Customer::find($request->customer_id) : null;
        $saleType = SaleType::from($request->sale_type);

        $sale = $this->salesService->createSale($shop, $saleType, $customer);

        return redirect()->route('sales.edit', $sale->uuid)
            ->with('success', 'Sale created. Add items to continue.');
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['shop', 'customer', 'items.product', 'items.variation', 'items.stockAllocations.stockBatch', 'payments', 'createdBy']);

        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale): View
    {
        $this->authorize('update', $sale);

        if (!$sale->isEditable()) {
            return redirect()->route('sales.show', $sale->uuid)
                ->with('error', 'This sale cannot be edited.');
        }

        $sale->load(['items.product', 'items.variation', 'customer']);
        $products = Product::forShop($sale->shop_id)->active()->with(['variations', 'pricing'])->get();
        $customers = Customer::forShop($sale->shop_id)->active()->get();

        return view('sales.edit', compact('sale', 'products', 'customers'));
    }

    public function addItem(AddSaleItemRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $product = Product::findOrFail($request->product_id);
        $variation = $request->variation_id ? ProductVariation::find($request->variation_id) : null;

        $result = $this->salesService->addItem(
            $sale,
            $product,
            $request->quantity,
            $variation,
            $request->custom_price,
            $request->discount ?? 0
        );

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Item added to sale.');
    }

    public function removeItem(Sale $sale, $itemUuid): RedirectResponse
    {
        $this->authorize('update', $sale);

        $item = $sale->items()->where('uuid', $itemUuid)->firstOrFail();
        $item->delete();

        return back()->with('success', 'Item removed from sale.');
    }

    public function applyDiscount(ApplySaleDiscountRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $this->salesService->applyDiscount(
            $sale,
            $request->discount_amount,
            $request->discount_type
        );

        return back()->with('success', 'Discount applied.');
    }

    public function complete(Sale $sale): RedirectResponse
    {
        $this->authorize('complete', $sale);

        try {
            $this->salesService->completeSale($sale);
            return redirect()->route('sales.show', $sale->uuid)
                ->with('success', 'Sale completed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('void', $sale);

        try {
            $this->salesService->voidSale($sale, $request->reason);
            return redirect()->route('sales.show', $sale->uuid)
                ->with('success', 'Sale voided successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receipt(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['shop', 'customer', 'items.product', 'items.variation', 'createdBy']);

        return view('sales.receipt', compact('sale'));
    }
}
```

---

## 8. Form Requests

### StoreSaleRequest

**File:** `app/Http/Requests/StoreSaleRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\SaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sale::class);
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'exists:shops,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'sale_type' => ['required', Rule::enum(SaleType::class)],
        ];
    }
}
```

### AddSaleItemRequest

**File:** `app/Http/Requests/AddSaleItemRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddSaleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('sale'));
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'variation_id' => ['nullable', 'exists:product_variations,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Please select a product.',
            'quantity.required' => 'Please enter quantity.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\SaleController;
use App\Http\Controllers\CustomerController;

Route::middleware(['auth'])->group(function () {
    // Customers
    Route::resource('customers', CustomerController::class);
    
    // Sales
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])
            ->name('index')
            ->middleware('permission:sales.view');
        
        Route::get('/create', [SaleController::class, 'create'])
            ->name('create')
            ->middleware('permission:sales.create');
        
        Route::post('/', [SaleController::class, 'store'])
            ->name('store')
            ->middleware('permission:sales.create');
        
        Route::get('/{sale}', [SaleController::class, 'show'])
            ->name('show')
            ->middleware('permission:sales.view');
        
        Route::get('/{sale}/edit', [SaleController::class, 'edit'])
            ->name('edit')
            ->middleware('permission:sales.update');
        
        Route::get('/{sale}/receipt', [SaleController::class, 'receipt'])
            ->name('receipt')
            ->middleware('permission:sales.view');
        
        // Sale items
        Route::post('/{sale}/items', [SaleController::class, 'addItem'])
            ->name('items.add')
            ->middleware('permission:sales.update');
        
        Route::delete('/{sale}/items/{item}', [SaleController::class, 'removeItem'])
            ->name('items.remove')
            ->middleware('permission:sales.update');
        
        // Actions
        Route::post('/{sale}/discount', [SaleController::class, 'applyDiscount'])
            ->name('discount')
            ->middleware('permission:sales.update');
        
        Route::post('/{sale}/complete', [SaleController::class, 'complete'])
            ->name('complete')
            ->middleware('permission:sales.complete');
        
        Route::post('/{sale}/void', [SaleController::class, 'void'])
            ->name('void')
            ->middleware('permission:sales.void');
    });
});
```

---

## 10. Permissions

```php
// Sales permissions
'sales.view',
'sales.create',
'sales.update',
'sales.complete',
'sales.void',
'sales.reports',

// Customer permissions
'customers.view',
'customers.create',
'customers.update',
'customers.delete',
'customers.credit.manage',
```

---

## 11. UI Template Reference

| View | Template Source |
|------|-----------------|
| Sales List | `design/src/apps-ecommerce-orders-list.php` |
| New Sale (POS) | `design/src/pos-*.php` |
| Sale Details | `design/src/apps-ecommerce-order-detail.php` |
| Receipt/Invoice | `design/src/invoice.php` |
| Customers List | `design/src/apps-ecommerce-customer-list.php` |

---

## 12. Tests

**File:** `tests/Feature/SalesTest.php`

```php
<?php

use App\Actions\Sales\CompleteSaleAction;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPricing;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\SalesService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('sale can be created', function () {
    $user = User::factory()->create();
    $user->assignRole('sales');
    
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->post(route('sales.store'), [
        'shop_id' => $shop->id,
        'sale_type' => 'retail',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sales', [
        'shop_id' => $shop->id,
        'sale_type' => 'retail',
        'status' => 'draft',
    ]);
});

test('invoice number is auto-generated', function () {
    $shop = Shop::factory()->create();
    
    $sale = Sale::factory()->create(['shop_id' => $shop->id]);

    expect($sale->invoice_number)->toStartWith('INV-');
});

test('item can be added to sale', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    ProductPricing::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'retail_price' => 100,
    ]);
    
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 50,
        'cost_per_unit' => 60,
    ]);
    
    $sale = Sale::factory()->create(['shop_id' => $shop->id]);

    $service = app(SalesService::class);
    $result = $service->addItem($sale, $product, 5);

    expect($result['success'])->toBeTrue();
    expect($sale->refresh()->items)->toHaveCount(1);
    expect($sale->items->first()->quantity)->toBe(5);
});

test('sale totals are calculated correctly', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->create(['shop_id' => $shop->id]);
    
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'quantity' => 2,
        'unit_price' => 100,
        'unit_cost' => 60,
    ]);
    
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'quantity' => 3,
        'unit_price' => 50,
        'unit_cost' => 30,
    ]);

    $sale->refresh();

    expect((float) $sale->subtotal)->toBe(350.00); // (2*100) + (3*50)
    expect((float) $sale->total_cost)->toBe(210.00); // (2*60) + (3*30)
    expect((float) $sale->total_profit)->toBe(140.00); // 350 - 210
});

test('sale completion deducts stock using FIFO', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    $batch1 = StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 30,
        'cost_per_unit' => 50,
        'date_received' => now()->subDays(2),
    ]);
    
    $batch2 = StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 50,
        'cost_per_unit' => 55,
        'date_received' => now()->subDay(),
    ]);
    
    $sale = Sale::factory()->create(['shop_id' => $shop->id]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 40,
        'unit_price' => 100,
    ]);

    $action = app(CompleteSaleAction::class);
    $action->execute($sale);

    expect($batch1->refresh()->remaining_quantity)->toBe(0);
    expect($batch2->refresh()->remaining_quantity)->toBe(40);
    expect($sale->refresh()->status)->toBe(SaleStatus::COMPLETED);
});

test('insufficient stock prevents item addition', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 10,
    ]);
    
    $sale = Sale::factory()->create(['shop_id' => $shop->id]);

    $service = app(SalesService::class);
    $result = $service->addItem($sale, $product, 20);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Insufficient stock');
});

test('voiding sale restores stock', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    $batch = StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 100,
        'cost_per_unit' => 50,
    ]);
    
    $sale = Sale::factory()->completed()->create(['shop_id' => $shop->id]);
    $item = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);
    
    // Simulate stock allocation
    $batch->update(['remaining_quantity' => 80, 'sold_quantity' => 20]);
    \App\Models\SaleItemStockAllocation::create([
        'sale_item_id' => $item->id,
        'stock_batch_id' => $batch->id,
        'quantity' => 20,
        'unit_cost' => 50,
    ]);

    $service = app(SalesService::class);
    $service->voidSale($sale, 'Test void');

    expect($batch->refresh()->remaining_quantity)->toBe(100);
    expect($sale->refresh()->status)->toBe(SaleStatus::VOIDED);
});

test('credit sale updates customer balance', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create([
        'shop_id' => $shop->id,
        'allow_credit' => true,
        'credit_limit' => 1000,
        'credit_balance' => 0,
    ]);
    
    $product = Product::factory()->forShop($shop)->create();
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 100,
    ]);
    
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'total_amount' => 500,
        'balance_due' => 500,
    ]);
    
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
    ]);

    $service = app(SalesService::class);
    $service->processCreditSale($sale);

    expect($customer->refresh()->credit_balance)->toBe(500.00);
});
```

---

## 13. Commands to Execute

```bash
# Step 1: Create Enums
mkdir -p app/Enums
# Create SaleType, SaleStatus, PaymentStatus, CustomerType enums

# Step 2: Create Models with migrations
php artisan make:model Sale -mfs --no-interaction
php artisan make:model SaleItem -mf --no-interaction
php artisan make:model SaleItemStockAllocation -m --no-interaction
php artisan make:model Customer -mfs --no-interaction

# Step 3: Create Actions
mkdir -p app/Actions/Sales
# Create CreateSaleAction, AddSaleItemAction, CompleteSaleAction, VoidSaleAction

# Step 4: Create Service
php artisan make:class Services/SalesService --no-interaction

# Step 5: Create Controllers
php artisan make:controller SaleController --no-interaction
php artisan make:controller CustomerController --resource --no-interaction

# Step 6: Create Form Requests
php artisan make:request StoreSaleRequest --no-interaction
php artisan make:request AddSaleItemRequest --no-interaction
php artisan make:request ApplySaleDiscountRequest --no-interaction
php artisan make:request VoidSaleRequest --no-interaction

# Step 7: Create Policy
php artisan make:policy SalePolicy --model=Sale --no-interaction

# Step 8: Run migrations
php artisan migrate

# Step 9: Create tests
php artisan make:test SalesTest --pest --no-interaction

# Step 10: Run tests
php artisan test --compact --filter=Sales

# Step 11: Format code
vendor/bin/pint --dirty
```

---

## 14. Verification Checklist

Before proceeding to Module 11, verify:

- [ ] All tables have `uuid` column with `getRouteKeyName()` on models
- [ ] All tables have audit columns (`created_by`, `updated_by`)
- [ ] Enums used for all status fields
- [ ] Invoice number auto-generates uniquely
- [ ] Items can be added/removed from draft sales
- [ ] Sale totals recalculate automatically
- [ ] FIFO stock allocation works correctly
- [ ] Stock deduction creates allocations linking to batches
- [ ] Profit calculated per item and sale
- [ ] Voiding restores stock to original batches
- [ ] Credit sales update customer balance
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 15. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 11: Payments](./11-payments.md)**
