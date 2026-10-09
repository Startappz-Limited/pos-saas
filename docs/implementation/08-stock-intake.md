# Module 08: Stock Intake (Batches)
## Stock Taking & Sales Management System

### Module Overview
Implement the core stock intake system where each delivery from a supplier creates a unique stock batch. This enables accurate cost tracking, FIFO costing, and profit calculation at the batch level.

**Priority:** P0 (Critical)  
**Dependencies:** Module 05, Module 07  
**Estimated Time:** 2-3 days

---

## 1. Database Schema

### Stock Batches Table

```sql
Schema::create('stock_batches', function (Blueprint $table) {
    $table->uuid('id')->primary(); // Unique Stock ID (UUID)
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('variation_id')->nullable()->constrained('product_variations')->cascadeOnDelete();
    $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
    
    // Quantities
    $table->integer('purchase_quantity');
    $table->integer('remaining_quantity');
    $table->integer('sold_quantity')->default(0);
    $table->integer('damaged_quantity')->default(0);
    $table->integer('returned_quantity')->default(0);
    
    // Costs
    $table->decimal('purchase_price', 12, 2); // Unit purchase price
    $table->decimal('shipping_cost', 12, 2)->default(0);
    $table->decimal('other_costs', 12, 2)->default(0);
    $table->decimal('total_cost', 12, 2); // Total batch cost
    $table->decimal('cost_per_unit', 12, 2); // Calculated cost per unit
    
    // Dates & References
    $table->string('batch_number')->unique();
    $table->string('supplier_invoice')->nullable();
    $table->date('date_received');
    $table->date('expiry_date')->nullable();
    
    // Audit
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->enum('status', ['active', 'depleted', 'expired', 'voided'])->default('active');
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['product_id', 'variation_id', 'status']);
    $table->index(['shop_id', 'status']);
    $table->index('date_received');
    $table->index('batch_number');
});
```

### Stock Adjustments Table

```sql
Schema::create('stock_adjustments', function (Blueprint $table) {
    $table->id();
    $table->uuid('stock_batch_id');
    $table->enum('type', ['damage', 'loss', 'correction', 'return_to_supplier', 'transfer']);
    $table->integer('quantity');
    $table->string('reason');
    $table->text('notes')->nullable();
    $table->foreignId('adjusted_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
    
    $table->foreign('stock_batch_id')->references('id')->on('stock_batches')->cascadeOnDelete();
    $table->index('stock_batch_id');
});
```

### Fields Specification

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | uuid | PK | Unique stock batch ID |
| product_id | bigint | FK, required | Product reference |
| variation_id | bigint | FK, nullable | Specific variation |
| shop_id | bigint | FK, required | Shop reference |
| supplier_id | bigint | FK, nullable | Supplier reference |
| purchase_quantity | integer | required | Qty received |
| remaining_quantity | integer | required | Current available qty |
| sold_quantity | integer | default: 0 | Qty sold |
| purchase_price | decimal(12,2) | required | Unit purchase price |
| shipping_cost | decimal(12,2) | default: 0 | Shipping costs |
| other_costs | decimal(12,2) | default: 0 | Additional costs |
| total_cost | decimal(12,2) | calculated | Total batch cost |
| cost_per_unit | decimal(12,2) | calculated | Cost per unit |
| batch_number | string | unique | Human-readable batch # |
| date_received | date | required | Receipt date |
| expiry_date | date | nullable | Product expiry |

---

## 2. Models & Relationships

### StockBatch Model

**File:** `app/Models/StockBatch.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockBatch extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'variation_id',
        'shop_id',
        'supplier_id',
        'purchase_quantity',
        'remaining_quantity',
        'sold_quantity',
        'damaged_quantity',
        'returned_quantity',
        'purchase_price',
        'shipping_cost',
        'other_costs',
        'total_cost',
        'cost_per_unit',
        'batch_number',
        'supplier_invoice',
        'date_received',
        'expiry_date',
        'created_by',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'cost_per_unit' => 'decimal:2',
            'date_received' => 'date',
            'expiry_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StockBatch $batch) {
            // Generate batch number if not set
            if (empty($batch->batch_number)) {
                $batch->batch_number = self::generateBatchNumber($batch->shop_id);
            }

            // Calculate costs
            $batch->calculateCosts();

            // Set remaining quantity to purchase quantity
            if (is_null($batch->remaining_quantity)) {
                $batch->remaining_quantity = $batch->purchase_quantity;
            }
        });

        static::updating(function (StockBatch $batch) {
            // Recalculate costs if relevant fields changed
            if ($batch->isDirty(['purchase_price', 'shipping_cost', 'other_costs', 'purchase_quantity'])) {
                $batch->calculateCosts();
            }

            // Update status if depleted
            if ($batch->remaining_quantity <= 0 && $batch->status === 'active') {
                $batch->status = 'depleted';
            }
        });
    }

    /**
     * Generate unique batch number
     */
    public static function generateBatchNumber(int $shopId): string
    {
        $prefix = 'BTH';
        $shopPrefix = str_pad($shopId, 2, '0', STR_PAD_LEFT);
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())->count() + 1;
        
        return "{$prefix}-{$shopPrefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate total cost and cost per unit
     */
    public function calculateCosts(): void
    {
        $this->total_cost = ($this->purchase_price * $this->purchase_quantity)
            + $this->shipping_cost
            + $this->other_costs;

        $this->cost_per_unit = $this->purchase_quantity > 0
            ? $this->total_cost / $this->purchase_quantity
            : 0;
    }

    /**
     * Product this batch belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variation this batch is for
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    /**
     * Shop this batch belongs to
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Supplier of this batch
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * User who created this batch
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Adjustments made to this batch
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * Sale items from this batch (to be added in Module 10)
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Deduct stock from batch
     */
    public function deductStock(int $quantity): bool
    {
        if ($quantity > $this->remaining_quantity) {
            return false;
        }

        $this->remaining_quantity -= $quantity;
        $this->sold_quantity += $quantity;

        if ($this->remaining_quantity <= 0) {
            $this->status = 'depleted';
        }

        return $this->save();
    }

    /**
     * Restore stock to batch
     */
    public function restoreStock(int $quantity): bool
    {
        $this->remaining_quantity += $quantity;
        $this->sold_quantity -= $quantity;

        if ($this->remaining_quantity > 0 && $this->status === 'depleted') {
            $this->status = 'active';
        }

        return $this->save();
    }

    /**
     * Check if batch is available for sale
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->remaining_quantity > 0;
    }

    /**
     * Check if batch is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if batch is expiring soon (within 30 days)
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date && $this->expiry_date->isBetween(now(), now()->addDays($days));
    }

    /**
     * Get profit from this batch
     */
    public function getProfitAttribute(): float
    {
        $revenue = $this->saleItems()->sum(\DB::raw('quantity * selling_price'));
        $cost = $this->sold_quantity * $this->cost_per_unit;
        
        return $revenue - $cost;
    }

    /**
     * Get profit margin percentage
     */
    public function getProfitMarginAttribute(): float
    {
        $revenue = $this->saleItems()->sum(\DB::raw('quantity * selling_price'));
        
        if ($revenue <= 0) {
            return 0;
        }

        return ($this->profit / $revenue) * 100;
    }

    /**
     * Scope for active batches with stock
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->where('remaining_quantity', '>', 0);
    }

    /**
     * Scope for FIFO ordering (oldest first)
     */
    public function scopeFifo($query)
    {
        return $query->orderBy('date_received')->orderBy('created_at');
    }

    /**
     * Scope for specific product/variation
     */
    public function scopeForProduct($query, int $productId, ?int $variationId = null)
    {
        return $query->where('product_id', $productId)
            ->when($variationId, fn ($q) => $q->where('variation_id', $variationId));
    }

    /**
     * Scope for specific shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope for expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope for expired
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }
}
```

### StockAdjustment Model

**File:** `app/Models/StockAdjustment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_batch_id',
        'type',
        'quantity',
        'reason',
        'notes',
        'adjusted_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (StockAdjustment $adjustment) {
            $batch = $adjustment->stockBatch;

            switch ($adjustment->type) {
                case 'damage':
                    $batch->remaining_quantity -= $adjustment->quantity;
                    $batch->damaged_quantity += $adjustment->quantity;
                    break;
                case 'loss':
                case 'return_to_supplier':
                    $batch->remaining_quantity -= $adjustment->quantity;
                    $batch->returned_quantity += $adjustment->quantity;
                    break;
                case 'correction':
                    // Can be positive or negative
                    $batch->remaining_quantity += $adjustment->quantity;
                    break;
            }

            if ($batch->remaining_quantity <= 0) {
                $batch->status = 'depleted';
            }

            $batch->save();
        });
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
```

---

## 3. Stock Service

### StockService

**File:** `app/Services/StockService.php`

```php
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Create a new stock batch
     */
    public function createStockBatch(array $data): StockBatch
    {
        return DB::transaction(function () use ($data) {
            return StockBatch::create($data);
        });
    }

    /**
     * Get available stock for a product/variation using FIFO
     */
    public function getAvailableStock(
        Product $product,
        ?ProductVariation $variation = null,
        ?Shop $shop = null
    ): Collection {
        return StockBatch::forProduct($product->id, $variation?->id)
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->available()
            ->fifo()
            ->get();
    }

    /**
     * Get total available quantity
     */
    public function getTotalAvailableQuantity(
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
     * Allocate stock for a sale (FIFO)
     */
    public function allocateStock(
        Product $product,
        int $quantity,
        ?ProductVariation $variation = null,
        ?Shop $shop = null
    ): array {
        $allocations = [];
        $remainingToAllocate = $quantity;

        $batches = $this->getAvailableStock($product, $variation, $shop);

        foreach ($batches as $batch) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $allocateFromBatch = min($batch->remaining_quantity, $remainingToAllocate);

            $allocations[] = [
                'batch' => $batch,
                'quantity' => $allocateFromBatch,
                'cost_per_unit' => $batch->cost_per_unit,
            ];

            $remainingToAllocate -= $allocateFromBatch;
        }

        if ($remainingToAllocate > 0) {
            throw new \Exception("Insufficient stock. Short by {$remainingToAllocate} units.");
        }

        return $allocations;
    }

    /**
     * Deduct stock using FIFO
     */
    public function deductStock(
        Product $product,
        int $quantity,
        ?ProductVariation $variation = null,
        ?Shop $shop = null
    ): array {
        return DB::transaction(function () use ($product, $quantity, $variation, $shop) {
            $allocations = $this->allocateStock($product, $quantity, $variation, $shop);

            foreach ($allocations as $allocation) {
                $allocation['batch']->deductStock($allocation['quantity']);
            }

            return $allocations;
        });
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(?Shop $shop = null): Collection
    {
        return Product::with(['shop', 'variations'])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->where('track_stock', true)
            ->active()
            ->get()
            ->filter(function ($product) {
                return $product->isLowStock();
            });
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
     * Get stock value
     */
    public function getStockValue(?Shop $shop = null): float
    {
        return StockBatch::when($shop, fn ($q) => $q->forShop($shop->id))
            ->available()
            ->sum(DB::raw('remaining_quantity * cost_per_unit'));
    }

    /**
     * Get stock movement summary
     */
    public function getStockMovementSummary(
        ?Shop $shop = null,
        ?\Carbon\Carbon $startDate = null,
        ?\Carbon\Carbon $endDate = null
    ): array {
        $query = StockBatch::when($shop, fn ($q) => $q->forShop($shop->id));

        if ($startDate && $endDate) {
            $query->whereBetween('date_received', [$startDate, $endDate]);
        }

        return [
            'total_received' => (clone $query)->sum('purchase_quantity'),
            'total_cost' => (clone $query)->sum('total_cost'),
            'total_sold' => (clone $query)->sum('sold_quantity'),
            'total_damaged' => (clone $query)->sum('damaged_quantity'),
            'current_stock' => (clone $query)->sum('remaining_quantity'),
        ];
    }
}
```

---

## 4. Controllers & Routes

### StockBatchController

**File:** `app/Http/Controllers/StockBatchController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockBatchRequest;
use App\Http\Requests\UpdateStockBatchRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockBatchController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    public function index(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $productId = $request->get('product_id');
        $status = $request->get('status');

        $batches = StockBatch::with(['product', 'variation', 'shop', 'supplier', 'creator'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('date_received')
            ->paginate(20);

        $shops = Shop::active()->get();
        $products = Product::active()->get();

        return view('stock.index', compact('batches', 'shops', 'products', 'shopId', 'productId', 'status'));
    }

    public function create(): View
    {
        $shops = Shop::active()->get();
        $products = Product::with('variations')->active()->get();
        $suppliers = Supplier::active()->get();

        return view('stock.create', compact('shops', 'products', 'suppliers'));
    }

    public function store(StoreStockBatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $batch = $this->stockService->createStockBatch($data);

        return redirect()->route('stock.show', $batch)
            ->with('success', 'Stock batch created successfully. Batch #' . $batch->batch_number);
    }

    public function show(StockBatch $stock): View
    {
        $stock->load(['product', 'variation', 'shop', 'supplier', 'creator', 'adjustments.adjustedBy']);

        return view('stock.show', compact('stock'));
    }

    public function edit(StockBatch $stock): View
    {
        $shops = Shop::active()->get();
        $products = Product::with('variations')->active()->get();
        $suppliers = Supplier::active()->get();

        return view('stock.edit', compact('stock', 'shops', 'products', 'suppliers'));
    }

    public function update(UpdateStockBatchRequest $request, StockBatch $stock): RedirectResponse
    {
        // Only allow updating certain fields
        $stock->update($request->validated());

        return redirect()->route('stock.show', $stock)
            ->with('success', 'Stock batch updated successfully.');
    }

    public function destroy(StockBatch $stock): RedirectResponse
    {
        if ($stock->sold_quantity > 0) {
            return back()->with('error', 'Cannot delete stock batch with sales.');
        }

        $stock->update(['status' => 'voided']);
        $stock->delete();

        return redirect()->route('stock.index')
            ->with('success', 'Stock batch voided successfully.');
    }
}
```

### StockAdjustmentController

**File:** `app/Http/Controllers/StockAdjustmentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function create(StockBatch $stock): View
    {
        return view('stock.adjustments.create', compact('stock'));
    }

    public function store(StoreStockAdjustmentRequest $request, StockBatch $stock): RedirectResponse
    {
        $data = $request->validated();
        $data['stock_batch_id'] = $stock->id;
        $data['adjusted_by'] = auth()->id();

        StockAdjustment::create($data);

        return redirect()->route('stock.show', $stock)
            ->with('success', 'Stock adjustment recorded successfully.');
    }
}
```

### Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\StockBatchController;
use App\Http\Controllers\StockAdjustmentController;

Route::middleware(['auth'])->group(function () {
    // Stock Management
    Route::resource('stock', StockBatchController::class)->middleware('permission:stock.view');
    
    Route::get('stock/{stock}/adjustments/create', [StockAdjustmentController::class, 'create'])
        ->name('stock.adjustments.create')
        ->middleware('permission:stock.adjust');
    
    Route::post('stock/{stock}/adjustments', [StockAdjustmentController::class, 'store'])
        ->name('stock.adjustments.store')
        ->middleware('permission:stock.adjust');
});
```

---

## 5. Form Requests

### StoreStockBatchRequest

**File:** `app/Http/Requests/StoreStockBatchRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('stock.create');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'variation_id' => ['nullable', 'exists:product_variations,id'],
            'shop_id' => ['required', 'exists:shops,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_quantity' => ['required', 'integer', 'min:1'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'supplier_invoice' => ['nullable', 'string', 'max:100'],
            'date_received' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:date_received'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Please select a product.',
            'shop_id.required' => 'Please select a shop.',
            'purchase_quantity.required' => 'Please enter the quantity received.',
            'purchase_quantity.min' => 'Quantity must be at least 1.',
            'purchase_price.required' => 'Please enter the purchase price.',
            'date_received.required' => 'Please enter the date received.',
            'expiry_date.after' => 'Expiry date must be after the received date.',
        ];
    }
}
```

---

## 6. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Stock List | `design/src/apps-ecommerce-inventory.php` |
| Add Stock | `design/src/purchase-order.php` |
| Stock Details | `design/src/inventory-received-orders.php` |

---

## 7. Tests (Pest)

**File:** `tests/Feature/StockBatchManagementTest.php`

```php
<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('stock batch can be created', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();

    $response = $this->actingAs($user)->post(route('stock.store'), [
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'purchase_quantity' => 100,
        'purchase_price' => 50.00,
        'date_received' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('stock_batches', [
        'product_id' => $product->id,
        'purchase_quantity' => 100,
    ]);
});

test('stock batch number is auto-generated', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    $batch = StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
    ]);

    expect($batch->batch_number)->toStartWith('BTH-');
});

test('cost per unit is calculated correctly', function () {
    $batch = StockBatch::factory()->create([
        'purchase_quantity' => 100,
        'purchase_price' => 50.00,
        'shipping_cost' => 100.00,
        'other_costs' => 50.00,
    ]);

    // Total cost = (100 * 50) + 100 + 50 = 5150
    // Cost per unit = 5150 / 100 = 51.50
    expect((float) $batch->cost_per_unit)->toBe(51.50);
});

test('stock can be deducted using FIFO', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    // Create two batches
    $batch1 = StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'purchase_quantity' => 50,
        'remaining_quantity' => 50,
        'date_received' => now()->subDays(2),
    ]);
    
    $batch2 = StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'purchase_quantity' => 50,
        'remaining_quantity' => 50,
        'date_received' => now()->subDay(),
    ]);

    $service = app(StockService::class);
    $allocations = $service->deductStock($product, 60, null, $shop);

    expect($allocations)->toHaveCount(2);
    expect($batch1->refresh()->remaining_quantity)->toBe(0);
    expect($batch2->refresh()->remaining_quantity)->toBe(40);
});

test('insufficient stock throws exception', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create();
    
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'remaining_quantity' => 10,
    ]);

    $service = app(StockService::class);

    expect(fn () => $service->deductStock($product, 20, null, $shop))
        ->toThrow(\Exception::class);
});
```

---

## 8. Commands to Execute

```bash
# Step 1: Create Models with migrations, factories
php artisan make:model StockBatch -mfs --no-interaction
php artisan make:model StockAdjustment -mf --no-interaction

# Step 2: Create Service
php artisan make:class Services/StockService --no-interaction

# Step 3: Create Controllers
php artisan make:controller StockBatchController --resource --no-interaction
php artisan make:controller StockAdjustmentController --no-interaction

# Step 4: Create Form Requests
php artisan make:request StoreStockBatchRequest --no-interaction
php artisan make:request UpdateStockBatchRequest --no-interaction
php artisan make:request StoreStockAdjustmentRequest --no-interaction

# Step 5: Run migrations
php artisan migrate

# Step 6: Create tests
php artisan make:test StockBatchManagementTest --pest --no-interaction
php artisan make:test Unit/StockBatchTest --pest --unit --no-interaction

# Step 7: Run tests
php artisan test --compact --filter=Stock

# Step 8: Format code
vendor/bin/pint --dirty
```

---

## 9. Verification Checklist

Before proceeding to Module 09, verify:

- [ ] Stock batches table with UUID primary key
- [ ] Stock adjustments table created
- [ ] Batch number auto-generation works
- [ ] Cost calculations are correct
- [ ] FIFO stock allocation works
- [ ] Stock deduction updates quantities
- [ ] Expiry date tracking works
- [ ] Stock adjustments update batch quantities
- [ ] StockService methods work correctly
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 10. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 09: Inventory Tracking](./09-inventory-tracking.md)**
