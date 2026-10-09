# Module 09: Inventory Tracking
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Real-time inventory tracking with low stock alerts, stock valuation, and movement history. This module aggregates data from stock batches to provide inventory insights.

**Priority:** P0 (Critical)  
**Dependencies:** Module 05, Module 08  
**Estimated Time:** 2-3 days

---

## 1. Guidelines Compliance

This module adheres to the following `.ai/general` standards:

| Guideline | Compliance |
|-----------|------------|
| **0.1 Folder Structure** | Controllers in `Http/Controllers/`, Services in `Services/`, Models in `Models/` |
| **0.3 Database Design** | Dual ID system (`id` + `uuid`), mandatory base columns |
| **0.4 Roles & Permissions** | Spatie Laravel Permission package |
| **0.5 Audit Logging** | Auditable trait with separate database |
| **0.8 Routing** | UUID-only route model binding |
| **0.9 Coding Standards** | Actions for single-purpose, Services for complex logic |
| **1.0 Security** | Form Request validation, authorization policies |

---

## 2. Database Schema

### Inventory Snapshots Table (Daily/Periodic Snapshots)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            
            $table->integer('quantity_on_hand');
            $table->decimal('total_value', 15, 2); // Cost value
            $table->decimal('retail_value', 15, 2); // Retail price value
            $table->date('snapshot_date');
            
            // Audit columns (per 0.3 guide)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['shop_id', 'product_id', 'variation_id', 'snapshot_date'], 'inventory_snapshot_unique');
            $table->index(['shop_id', 'snapshot_date']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_snapshots');
    }
};
```

### Stock Movement Log Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->uuid('stock_batch_id')->nullable();
            
            // Movement details
            $table->string('movement_type'); // Uses StockMovementType enum
            $table->integer('quantity');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            
            // Reference
            $table->string('reference_type')->nullable(); // 'sale', 'adjustment', 'transfer', etc.
            $table->string('reference_uuid')->nullable();
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->foreign('stock_batch_id')->references('id')->on('stock_batches')->nullOnDelete();
            $table->index(['product_id', 'variation_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
```

### Low Stock Alerts Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('low_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            
            $table->integer('current_quantity');
            $table->integer('threshold_quantity');
            $table->string('status'); // Uses AlertStatus enum
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['shop_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_stock_alerts');
    }
};
```

---

## 3. Enums (Per 0.3 Guide - PHP 8.1+ Native Enums)

### StockMovementType Enum

**File:** `app/Enums/StockMovementType.php`

```php
<?php

namespace App\Enums;

enum StockMovementType: string
{
    case INTAKE = 'intake';
    case SALE = 'sale';
    case RETURN = 'return';
    case ADJUSTMENT_ADD = 'adjustment_add';
    case ADJUSTMENT_REMOVE = 'adjustment_remove';
    case DAMAGE = 'damage';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::INTAKE => 'Stock Intake',
            self::SALE => 'Sale',
            self::RETURN => 'Customer Return',
            self::ADJUSTMENT_ADD => 'Adjustment (Add)',
            self::ADJUSTMENT_REMOVE => 'Adjustment (Remove)',
            self::DAMAGE => 'Damaged Stock',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::EXPIRED => 'Expired',
        };
    }

    public function isAddition(): bool
    {
        return in_array($this, [
            self::INTAKE,
            self::RETURN,
            self::ADJUSTMENT_ADD,
            self::TRANSFER_IN,
        ]);
    }

    public function isDeduction(): bool
    {
        return in_array($this, [
            self::SALE,
            self::ADJUSTMENT_REMOVE,
            self::DAMAGE,
            self::TRANSFER_OUT,
            self::EXPIRED,
        ]);
    }
}
```

### AlertStatus Enum

**File:** `app/Enums/AlertStatus.php`

```php
<?php

namespace App\Enums;

enum AlertStatus: string
{
    case PENDING = 'pending';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';
    case IGNORED = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::RESOLVED => 'Resolved',
            self::IGNORED => 'Ignored',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'danger',
            self::ACKNOWLEDGED => 'warning',
            self::RESOLVED => 'success',
            self::IGNORED => 'secondary',
        };
    }
}
```

---

## 4. Models (Per 0.3 & 0.9 Guides)

### StockMovement Model

**File:** `app/Models/StockMovement.php`

```php
<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'product_id',
        'variation_id',
        'stock_batch_id',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'reference_type',
        'reference_uuid',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'unit_cost' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StockMovement $movement) {
            if (empty($movement->uuid)) {
                $movement->uuid = (string) \Illuminate\Support\Str::uuid();
            }
            $movement->created_by = auth()->id();
        });

        static::updating(function (StockMovement $movement) {
            $movement->updated_by = auth()->id();
        });
    }

    /**
     * Get the route key for the model (UUID for URLs per 0.8 guide)
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for specific shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope for specific product
     */
    public function scopeForProduct($query, int $productId, ?int $variationId = null)
    {
        return $query->where('product_id', $productId)
            ->when($variationId, fn ($q) => $q->where('variation_id', $variationId));
    }

    /**
     * Scope for date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
```

### LowStockAlert Model

**File:** `app/Models/LowStockAlert.php`

```php
<?php

namespace App\Models;

use App\Enums\AlertStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LowStockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'product_id',
        'variation_id',
        'current_quantity',
        'threshold_quantity',
        'status',
        'acknowledged_at',
        'acknowledged_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AlertStatus::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (LowStockAlert $alert) {
            if (empty($alert->uuid)) {
                $alert->uuid = (string) \Illuminate\Support\Str::uuid();
            }
            $alert->status = AlertStatus::PENDING;
            $alert->created_by = auth()->id();
        });

        static::updating(function (LowStockAlert $alert) {
            $alert->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Acknowledge the alert
     */
    public function acknowledge(): bool
    {
        $this->status = AlertStatus::ACKNOWLEDGED;
        $this->acknowledged_at = now();
        $this->acknowledged_by = auth()->id();
        
        return $this->save();
    }

    /**
     * Resolve the alert
     */
    public function resolve(): bool
    {
        $this->status = AlertStatus::RESOLVED;
        return $this->save();
    }

    /**
     * Scope for pending alerts
     */
    public function scopePending($query)
    {
        return $query->where('status', AlertStatus::PENDING);
    }

    /**
     * Scope for shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }
}
```

### InventorySnapshot Model

**File:** `app/Models/InventorySnapshot.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'product_id',
        'variation_id',
        'quantity_on_hand',
        'total_value',
        'retail_value',
        'snapshot_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'retail_value' => 'decimal:2',
            'snapshot_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (InventorySnapshot $snapshot) {
            if (empty($snapshot->uuid)) {
                $snapshot->uuid = (string) \Illuminate\Support\Str::uuid();
            }
            $snapshot->created_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }
}
```

---

## 5. Actions (Per 0.9 Guide - Single Purpose)

### RecordStockMovementAction

**File:** `app/Actions/Inventory/RecordStockMovementAction.php`

```php
<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;

class RecordStockMovementAction
{
    /**
     * Record a stock movement
     */
    public function execute(
        Shop $shop,
        Product $product,
        StockMovementType $type,
        int $quantity,
        ?ProductVariation $variation = null,
        ?StockBatch $batch = null,
        ?string $referenceType = null,
        ?string $referenceUuid = null,
        ?string $notes = null
    ): StockMovement {
        $quantityBefore = $this->getCurrentQuantity($shop, $product, $variation);
        
        $quantityAfter = $type->isAddition()
            ? $quantityBefore + $quantity
            : $quantityBefore - $quantity;

        return StockMovement::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'variation_id' => $variation?->id,
            'stock_batch_id' => $batch?->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'unit_cost' => $batch?->cost_per_unit,
            'reference_type' => $referenceType,
            'reference_uuid' => $referenceUuid,
            'notes' => $notes,
        ]);
    }

    private function getCurrentQuantity(Shop $shop, Product $product, ?ProductVariation $variation): int
    {
        return StockBatch::forShop($shop->id)
            ->forProduct($product->id, $variation?->id)
            ->available()
            ->sum('remaining_quantity');
    }
}
```

### CheckLowStockAction

**File:** `app/Actions/Inventory/CheckLowStockAction.php`

```php
<?php

namespace App\Actions\Inventory;

use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\StockBatch;

class CheckLowStockAction
{
    /**
     * Check if product is low on stock and create alert if needed
     */
    public function execute(
        Shop $shop,
        Product $product,
        ?ProductVariation $variation = null
    ): ?LowStockAlert {
        if (!$product->track_stock) {
            return null;
        }

        $currentQuantity = StockBatch::forShop($shop->id)
            ->forProduct($product->id, $variation?->id)
            ->available()
            ->sum('remaining_quantity');

        $threshold = $variation?->low_stock_threshold 
            ?? $product->low_stock_threshold 
            ?? 10;

        // Check if already has pending alert
        $existingAlert = LowStockAlert::forShop($shop->id)
            ->where('product_id', $product->id)
            ->when($variation, fn ($q) => $q->where('variation_id', $variation->id))
            ->pending()
            ->first();

        if ($currentQuantity <= $threshold) {
            if ($existingAlert) {
                $existingAlert->update(['current_quantity' => $currentQuantity]);
                return $existingAlert;
            }

            return LowStockAlert::create([
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'variation_id' => $variation?->id,
                'current_quantity' => $currentQuantity,
                'threshold_quantity' => $threshold,
            ]);
        }

        // Resolve existing alert if stock is now sufficient
        if ($existingAlert && $currentQuantity > $threshold) {
            $existingAlert->resolve();
        }

        return null;
    }
}
```

### CreateInventorySnapshotAction

**File:** `app/Actions/Inventory/CreateInventorySnapshotAction.php`

```php
<?php

namespace App\Actions\Inventory;

use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateInventorySnapshotAction
{
    /**
     * Create inventory snapshots for all products in a shop
     */
    public function execute(Shop $shop, ?Carbon $date = null): int
    {
        $date = $date ?? now();
        $count = 0;

        // Get all products with stock in this shop
        $products = Product::whereHas('stockBatches', function ($q) use ($shop) {
            $q->forShop($shop->id)->available();
        })->with(['variations', 'pricing'])->get();

        DB::transaction(function () use ($products, $shop, $date, &$count) {
            foreach ($products as $product) {
                if ($product->has_variations) {
                    foreach ($product->variations as $variation) {
                        $this->createSnapshot($shop, $product, $variation, $date);
                        $count++;
                    }
                } else {
                    $this->createSnapshot($shop, $product, null, $date);
                    $count++;
                }
            }
        });

        return $count;
    }

    private function createSnapshot(
        Shop $shop,
        Product $product,
        $variation,
        Carbon $date
    ): InventorySnapshot {
        $quantity = StockBatch::forShop($shop->id)
            ->forProduct($product->id, $variation?->id)
            ->available()
            ->sum('remaining_quantity');

        $costValue = StockBatch::forShop($shop->id)
            ->forProduct($product->id, $variation?->id)
            ->available()
            ->sum(DB::raw('remaining_quantity * cost_per_unit'));

        $retailPrice = $product->pricing()
            ->where('shop_id', $shop->id)
            ->active()
            ->first()
            ?->retail_price ?? 0;

        return InventorySnapshot::updateOrCreate(
            [
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'variation_id' => $variation?->id,
                'snapshot_date' => $date->toDateString(),
            ],
            [
                'quantity_on_hand' => $quantity,
                'total_value' => $costValue,
                'retail_value' => $quantity * $retailPrice,
            ]
        );
    }
}
```

---

## 6. Services (Per 0.9 Guide - Complex Business Logic)

### InventoryService

**File:** `app/Services/InventoryService.php`

```php
<?php

namespace App\Services;

use App\Actions\Inventory\CheckLowStockAction;
use App\Actions\Inventory\CreateInventorySnapshotAction;
use App\Actions\Inventory\RecordStockMovementAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private RecordStockMovementAction $recordMovement,
        private CheckLowStockAction $checkLowStock,
        private CreateInventorySnapshotAction $createSnapshot
    ) {}

    /**
     * Get current stock level for a product
     */
    public function getStockLevel(
        Product $product,
        ?ProductVariation $variation = null,
        ?Shop $shop = null
    ): int {
        return StockBatch::forProduct($product->id, $variation?->id)
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->available()
            ->sum('remaining_quantity');
    }

    /**
     * Get stock valuation
     */
    public function getStockValuation(?Shop $shop = null): array
    {
        $query = StockBatch::available()
            ->when($shop, fn ($q) => $q->forShop($shop->id));

        return [
            'total_quantity' => (clone $query)->sum('remaining_quantity'),
            'cost_value' => (clone $query)->sum(DB::raw('remaining_quantity * cost_per_unit')),
            'batch_count' => (clone $query)->count(),
        ];
    }

    /**
     * Get stock movement history
     */
    public function getMovementHistory(
        ?Shop $shop = null,
        ?Product $product = null,
        ?int $limit = 50
    ): Collection {
        return StockMovement::with(['product', 'variation', 'shop', 'createdBy'])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->when($product, fn ($q) => $q->forProduct($product->id))
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Record stock intake and check low stock
     */
    public function recordIntake(
        StockBatch $batch,
        Shop $shop,
        Product $product,
        ?ProductVariation $variation = null
    ): StockMovement {
        $movement = $this->recordMovement->execute(
            shop: $shop,
            product: $product,
            type: StockMovementType::INTAKE,
            quantity: $batch->purchase_quantity,
            variation: $variation,
            batch: $batch,
            referenceType: 'stock_batch',
            referenceUuid: $batch->id
        );

        // Check if this resolves any low stock alerts
        $this->checkLowStock->execute($shop, $product, $variation);

        return $movement;
    }

    /**
     * Record stock sale
     */
    public function recordSale(
        Shop $shop,
        Product $product,
        int $quantity,
        ?ProductVariation $variation = null,
        ?StockBatch $batch = null,
        ?string $saleUuid = null
    ): StockMovement {
        $movement = $this->recordMovement->execute(
            shop: $shop,
            product: $product,
            type: StockMovementType::SALE,
            quantity: $quantity,
            variation: $variation,
            batch: $batch,
            referenceType: 'sale',
            referenceUuid: $saleUuid
        );

        // Check low stock after sale
        $this->checkLowStock->execute($shop, $product, $variation);

        return $movement;
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(?Shop $shop = null): Collection
    {
        return Product::with(['variations', 'shop'])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->where('track_stock', true)
            ->active()
            ->get()
            ->filter(function ($product) use ($shop) {
                $quantity = $this->getStockLevel($product, null, $shop);
                $threshold = $product->low_stock_threshold ?? 10;
                return $quantity <= $threshold;
            })
            ->values();
    }

    /**
     * Get expiring stock
     */
    public function getExpiringStock(?Shop $shop = null, int $days = 30): Collection
    {
        return StockBatch::with(['product', 'variation', 'shop'])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->available()
            ->expiringSoon($days)
            ->orderBy('expiry_date')
            ->get();
    }

    /**
     * Create daily inventory snapshot
     */
    public function createDailySnapshot(Shop $shop): int
    {
        return $this->createSnapshot->execute($shop);
    }
}
```

---

## 7. Controllers (Per 0.8 & 0.9 Guides)

### InventoryController

**File:** `app/Http/Controllers/InventoryController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\LowStockAlert;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * Display inventory dashboard
     */
    public function index(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $shop = $shopId ? Shop::find($shopId) : null;

        $valuation = $this->inventoryService->getStockValuation($shop);
        $lowStockProducts = $this->inventoryService->getLowStockProducts($shop);
        $expiringStock = $this->inventoryService->getExpiringStock($shop, 30);
        $recentMovements = $this->inventoryService->getMovementHistory($shop, null, 20);

        $shops = Shop::active()->get();

        return view('inventory.index', compact(
            'valuation',
            'lowStockProducts',
            'expiringStock',
            'recentMovements',
            'shops',
            'shop'
        ));
    }

    /**
     * Display stock movements
     */
    public function movements(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $productId = $request->get('product_id');

        $movements = StockMovement::with(['product', 'variation', 'shop', 'createdBy'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->latest()
            ->paginate(50);

        $shops = Shop::active()->get();

        return view('inventory.movements', compact('movements', 'shops'));
    }

    /**
     * Display low stock alerts
     */
    public function alerts(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $status = $request->get('status', 'pending');

        $alerts = LowStockAlert::with(['product', 'variation', 'shop', 'acknowledgedBy'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30);

        $shops = Shop::active()->get();

        return view('inventory.alerts', compact('alerts', 'shops', 'status'));
    }

    /**
     * Acknowledge a low stock alert
     */
    public function acknowledgeAlert(LowStockAlert $alert)
    {
        $this->authorize('acknowledge', $alert);
        
        $alert->acknowledge();

        return back()->with('success', 'Alert acknowledged successfully.');
    }
}
```

---

## 8. Routes (Per 0.8 Guide - UUID Binding)

**File:** `routes/web.php`

```php
use App\Http\Controllers\InventoryController;

Route::middleware(['auth'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])
        ->name('index')
        ->middleware('permission:inventory.view');
    
    Route::get('/movements', [InventoryController::class, 'movements'])
        ->name('movements')
        ->middleware('permission:inventory.view');
    
    Route::get('/alerts', [InventoryController::class, 'alerts'])
        ->name('alerts')
        ->middleware('permission:inventory.view');
    
    // Note: Uses UUID via getRouteKeyName() on model
    Route::post('/alerts/{alert}/acknowledge', [InventoryController::class, 'acknowledgeAlert'])
        ->name('alerts.acknowledge')
        ->middleware('permission:inventory.alerts.acknowledge');
});
```

---

## 9. Scheduled Jobs (Daily Stock Check)

### CheckLowStockJob

**File:** `app/Jobs/Inventory/CheckLowStockJob.php`

```php
<?php

namespace App\Jobs\Inventory;

use App\Actions\Inventory\CheckLowStockAction;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckLowStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Shop $shop
    ) {}

    public function handle(CheckLowStockAction $action): void
    {
        $products = Product::forShop($this->shop->id)
            ->where('track_stock', true)
            ->active()
            ->with('variations')
            ->get();

        foreach ($products as $product) {
            if ($product->has_variations) {
                foreach ($product->variations as $variation) {
                    $action->execute($this->shop, $product, $variation);
                }
            } else {
                $action->execute($this->shop, $product);
            }
        }
    }
}
```

### Schedule Registration

**File:** `routes/console.php`

```php
use App\Jobs\Inventory\CheckLowStockJob;
use App\Models\Shop;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Shop::active()->each(function ($shop) {
        CheckLowStockJob::dispatch($shop);
    });
})->dailyAt('06:00')->name('check-low-stock');
```

---

## 10. Permissions (Per 0.4 Guide)

### Add to Permission Seeder

```php
// Inventory permissions
'inventory.view',
'inventory.movements.view',
'inventory.alerts.view',
'inventory.alerts.acknowledge',
'inventory.snapshots.create',
```

---

## 11. Tests (Pest)

**File:** `tests/Feature/InventoryTrackingTest.php`

```php
<?php

use App\Actions\Inventory\CheckLowStockAction;
use App\Actions\Inventory\RecordStockMovementAction;
use App\Enums\AlertStatus;
use App\Enums\StockMovementType;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('stock movement is recorded correctly', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create(['track_stock' => true]);
    $batch = StockBatch::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'remaining_quantity' => 100,
    ]);

    $action = app(RecordStockMovementAction::class);
    $movement = $action->execute(
        shop: $shop,
        product: $product,
        type: StockMovementType::INTAKE,
        quantity: 100,
        batch: $batch
    );

    expect($movement)->toBeInstanceOf(StockMovement::class);
    expect($movement->movement_type)->toBe(StockMovementType::INTAKE);
    expect($movement->quantity)->toBe(100);
});

test('low stock alert is created when stock falls below threshold', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create([
        'track_stock' => true,
        'low_stock_threshold' => 20,
    ]);
    
    StockBatch::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'remaining_quantity' => 10,
    ]);

    $action = app(CheckLowStockAction::class);
    $alert = $action->execute($shop, $product);

    expect($alert)->toBeInstanceOf(LowStockAlert::class);
    expect($alert->status)->toBe(AlertStatus::PENDING);
    expect($alert->current_quantity)->toBe(10);
});

test('low stock alert is resolved when stock is replenished', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create([
        'track_stock' => true,
        'low_stock_threshold' => 20,
    ]);
    
    // Create low stock alert
    $alert = LowStockAlert::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'status' => AlertStatus::PENDING,
    ]);
    
    // Add stock above threshold
    StockBatch::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'remaining_quantity' => 50,
    ]);

    $action = app(CheckLowStockAction::class);
    $action->execute($shop, $product);

    expect($alert->refresh()->status)->toBe(AlertStatus::RESOLVED);
});

test('inventory service calculates stock valuation correctly', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    StockBatch::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'remaining_quantity' => 50,
        'cost_per_unit' => 10.00,
    ]);
    
    StockBatch::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'remaining_quantity' => 30,
        'cost_per_unit' => 12.00,
    ]);

    $service = app(InventoryService::class);
    $valuation = $service->getStockValuation($shop);

    expect($valuation['total_quantity'])->toBe(80);
    expect((float) $valuation['cost_value'])->toBe(860.00); // (50*10) + (30*12)
});

test('user can view inventory dashboard with permission', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $response = $this->actingAs($user)->get(route('inventory.index'));

    $response->assertOk();
});

test('alert can be acknowledged by authorized user', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');
    
    $alert = LowStockAlert::factory()->create(['status' => AlertStatus::PENDING]);

    $this->actingAs($user)
        ->post(route('inventory.alerts.acknowledge', $alert->uuid))
        ->assertRedirect();

    expect($alert->refresh()->status)->toBe(AlertStatus::ACKNOWLEDGED);
    expect($alert->acknowledged_by)->toBe($user->id);
});
```

---

## 12. Commands to Execute

```bash
# Step 1: Create Enums
mkdir -p app/Enums
# Create StockMovementType.php and AlertStatus.php manually

# Step 2: Create Models with migrations
php artisan make:model StockMovement -mf --no-interaction
php artisan make:model LowStockAlert -mf --no-interaction
php artisan make:model InventorySnapshot -mf --no-interaction

# Step 3: Create Actions directory and classes
mkdir -p app/Actions/Inventory
# Create action classes

# Step 4: Create Service
php artisan make:class Services/InventoryService --no-interaction

# Step 5: Create Controller
php artisan make:controller InventoryController --no-interaction

# Step 6: Create Job
php artisan make:job Inventory/CheckLowStockJob --no-interaction

# Step 7: Run migrations
php artisan migrate

# Step 8: Create tests
php artisan make:test InventoryTrackingTest --pest --no-interaction

# Step 9: Run tests
php artisan test --compact --filter=Inventory

# Step 10: Format code
vendor/bin/pint --dirty
```

---

## 13. Verification Checklist

Before proceeding to Module 10, verify:

- [ ] All tables have `uuid` column (per 0.3 guide)
- [ ] All tables have audit columns (`created_by`, `updated_by`)
- [ ] Models use `getRouteKeyName()` returning 'uuid' (per 0.8 guide)
- [ ] Enums used for status fields (per 0.3 guide)
- [ ] Actions created for single-purpose operations (per 0.9 guide)
- [ ] Service orchestrates multiple actions (per 0.9 guide)
- [ ] Routes use UUID binding (per 0.8 guide)
- [ ] Permissions follow `{module}.{action}` format (per 0.4 guide)
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 14. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 10: Sales & Transactions](./10-sales-transactions.md)**
