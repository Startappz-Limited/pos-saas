# Module 06: Pricing Management
## Stock Taking & Sales Management System

### Module Overview
Implement product pricing with support for retail and wholesale prices. Prices can have effective dates for scheduled price changes and maintain a full pricing history.

**Priority:** P1 (High)  
**Dependencies:** Module 05 (Products & Variations)  
**Estimated Time:** 1-2 days

---

## 1. Database Schema

### Product Pricing Table

```sql
Schema::create('product_pricing', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('variation_id')->nullable()->constrained('product_variations')->cascadeOnDelete();
    $table->decimal('retail_price', 12, 2);
    $table->decimal('wholesale_price', 12, 2)->nullable();
    $table->integer('wholesale_min_quantity')->default(1); // Minimum qty for wholesale price
    $table->decimal('compare_at_price', 12, 2)->nullable(); // Original price for showing discounts
    $table->date('effective_date');
    $table->date('end_date')->nullable(); // For promotional pricing
    $table->enum('status', ['active', 'scheduled', 'expired'])->default('active');
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['product_id', 'variation_id', 'status']);
    $table->index('effective_date');
});
```

### Fields Specification

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint | PK, auto | Primary key |
| product_id | bigint | FK, required | Associated product |
| variation_id | bigint | FK, nullable | Specific variation (null = all) |
| retail_price | decimal(12,2) | required | Standard selling price |
| wholesale_price | decimal(12,2) | nullable | Bulk purchase price |
| wholesale_min_quantity | integer | default: 1 | Min qty for wholesale |
| compare_at_price | decimal(12,2) | nullable | Original/compare price |
| effective_date | date | required | When price takes effect |
| end_date | date | nullable | When price expires |
| status | enum | default: active | Pricing status |
| created_by | bigint | FK, nullable | User who set price |
| notes | text | nullable | Pricing notes |

---

## 2. Models & Relationships

### ProductPricing Model

**File:** `app/Models/ProductPricing.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPricing extends Model
{
    use HasFactory;

    protected $table = 'product_pricing';

    protected $fillable = [
        'product_id',
        'variation_id',
        'retail_price',
        'wholesale_price',
        'wholesale_min_quantity',
        'compare_at_price',
        'effective_date',
        'end_date',
        'status',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'effective_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ProductPricing $pricing) {
            // Set status based on effective date
            if ($pricing->effective_date > now()->toDateString()) {
                $pricing->status = 'scheduled';
            }
        });

        static::saved(function (ProductPricing $pricing) {
            // Deactivate other active pricing for same product/variation
            if ($pricing->status === 'active') {
                ProductPricing::where('product_id', $pricing->product_id)
                    ->where('variation_id', $pricing->variation_id)
                    ->where('id', '!=', $pricing->id)
                    ->where('status', 'active')
                    ->update(['status' => 'expired']);
            }
        });
    }

    /**
     * Product this pricing belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variation this pricing applies to
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    /**
     * User who created this pricing
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if pricing is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if pricing is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if pricing is expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->end_date && $this->end_date < now()->toDateString());
    }

    /**
     * Get the applicable price based on quantity
     */
    public function getPriceForQuantity(int $quantity): float
    {
        if ($this->wholesale_price && $quantity >= $this->wholesale_min_quantity) {
            return (float) $this->wholesale_price;
        }

        return (float) $this->retail_price;
    }

    /**
     * Calculate discount percentage
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->retail_price) {
            return null;
        }

        return round((($this->compare_at_price - $this->retail_price) / $this->compare_at_price) * 100, 1);
    }

    /**
     * Get formatted retail price
     */
    public function getFormattedRetailPriceAttribute(): string
    {
        return number_format($this->retail_price, 2);
    }

    /**
     * Get formatted wholesale price
     */
    public function getFormattedWholesalePriceAttribute(): ?string
    {
        return $this->wholesale_price
            ? number_format($this->wholesale_price, 2)
            : null;
    }

    /**
     * Scope for active pricing
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Scope for scheduled pricing
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->where('effective_date', '>', now()->toDateString());
    }

    /**
     * Scope for specific product
     */
    public function scopeForProduct($query, int $productId, ?int $variationId = null)
    {
        return $query->where('product_id', $productId)
            ->when($variationId, fn ($q) => $q->where('variation_id', $variationId));
    }
}
```

---

## 3. Price Service

### PricingService

**File:** `app/Services/PricingService.php`

```php
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPricing;
use App\Models\ProductVariation;
use Carbon\Carbon;

class PricingService
{
    /**
     * Get current pricing for a product/variation
     */
    public function getCurrentPricing(Product $product, ?ProductVariation $variation = null): ?ProductPricing
    {
        return ProductPricing::forProduct($product->id, $variation?->id)
            ->active()
            ->first();
    }

    /**
     * Set pricing for a product/variation
     */
    public function setPricing(
        Product $product,
        float $retailPrice,
        ?float $wholesalePrice = null,
        ?ProductVariation $variation = null,
        ?Carbon $effectiveDate = null,
        ?Carbon $endDate = null,
        ?int $userId = null,
        ?string $notes = null
    ): ProductPricing {
        $effectiveDate = $effectiveDate ?? now();
        
        $status = $effectiveDate->greaterThan(now()) ? 'scheduled' : 'active';

        return ProductPricing::create([
            'product_id' => $product->id,
            'variation_id' => $variation?->id,
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
            'effective_date' => $effectiveDate->toDateString(),
            'end_date' => $endDate?->toDateString(),
            'status' => $status,
            'created_by' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Get pricing for sale calculation
     */
    public function getPriceForSale(
        Product $product,
        ?ProductVariation $variation,
        int $quantity,
        bool $isWholesale = false
    ): float {
        $pricing = $this->getCurrentPricing($product, $variation);

        if (!$pricing) {
            return 0;
        }

        if ($isWholesale && $pricing->wholesale_price) {
            return (float) $pricing->wholesale_price;
        }

        return $pricing->getPriceForQuantity($quantity);
    }

    /**
     * Activate scheduled pricing
     */
    public function activateScheduledPricing(): int
    {
        $count = 0;
        
        $scheduled = ProductPricing::where('status', 'scheduled')
            ->where('effective_date', '<=', now()->toDateString())
            ->get();

        foreach ($scheduled as $pricing) {
            $pricing->update(['status' => 'active']);
            $count++;
        }

        return $count;
    }

    /**
     * Expire ended pricing
     */
    public function expireEndedPricing(): int
    {
        return ProductPricing::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }

    /**
     * Get pricing history for a product
     */
    public function getPricingHistory(Product $product, ?ProductVariation $variation = null): \Illuminate\Database\Eloquent\Collection
    {
        return ProductPricing::forProduct($product->id, $variation?->id)
            ->with('creator')
            ->orderByDesc('effective_date')
            ->get();
    }

    /**
     * Bulk update pricing
     */
    public function bulkUpdatePricing(array $pricingData, int $userId): array
    {
        $results = ['success' => 0, 'failed' => 0];

        foreach ($pricingData as $data) {
            try {
                $product = Product::findOrFail($data['product_id']);
                $variation = isset($data['variation_id'])
                    ? ProductVariation::find($data['variation_id'])
                    : null;

                $this->setPricing(
                    $product,
                    $data['retail_price'],
                    $data['wholesale_price'] ?? null,
                    $variation,
                    isset($data['effective_date']) ? Carbon::parse($data['effective_date']) : null,
                    null,
                    $userId
                );

                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }

        return $results;
    }
}
```

---

## 4. Controllers & Routes

### PricingController

**File:** `app/Http/Controllers/PricingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePricingRequest;
use App\Http\Requests\BulkPricingRequest;
use App\Models\Product;
use App\Models\ProductPricing;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    public function index(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $status = $request->get('status');

        $pricing = ProductPricing::with(['product.shop', 'variation', 'creator'])
            ->when($shopId, function ($q) use ($shopId) {
                $q->whereHas('product', fn ($pq) => $pq->where('shop_id', $shopId));
            })
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('effective_date')
            ->paginate(20);

        return view('pricing.index', compact('pricing', 'shopId', 'status'));
    }

    public function create(Product $product): View
    {
        $product->load('variations');
        $currentPricing = $this->pricingService->getCurrentPricing($product);

        return view('pricing.create', compact('product', 'currentPricing'));
    }

    public function store(StorePricingRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $variation = $data['variation_id']
            ? $product->variations()->find($data['variation_id'])
            : null;

        $this->pricingService->setPricing(
            $product,
            $data['retail_price'],
            $data['wholesale_price'] ?? null,
            $variation,
            isset($data['effective_date']) ? \Carbon\Carbon::parse($data['effective_date']) : null,
            isset($data['end_date']) ? \Carbon\Carbon::parse($data['end_date']) : null,
            auth()->id(),
            $data['notes'] ?? null
        );

        return redirect()->route('products.show', $product)
            ->with('success', 'Pricing updated successfully.');
    }

    public function history(Product $product): View
    {
        $history = $this->pricingService->getPricingHistory($product);
        $product->load('variations');

        return view('pricing.history', compact('product', 'history'));
    }

    public function bulkEdit(): View
    {
        $products = Product::with(['shop', 'variations', 'currentPricing'])
            ->active()
            ->get();

        return view('pricing.bulk-edit', compact('products'));
    }

    public function bulkUpdate(BulkPricingRequest $request): RedirectResponse
    {
        $results = $this->pricingService->bulkUpdatePricing(
            $request->validated()['pricing'],
            auth()->id()
        );

        return redirect()->route('pricing.index')
            ->with('success', "{$results['success']} prices updated. {$results['failed']} failed.");
    }
}
```

### Routes

**File:** `routes/web.php` (add to authenticated routes)

```php
use App\Http\Controllers\PricingController;

Route::middleware(['auth'])->group(function () {
    // ... existing routes

    // Pricing Management
    Route::get('pricing', [PricingController::class, 'index'])
        ->name('pricing.index')
        ->middleware('permission:products.view');
    
    Route::get('products/{product}/pricing/create', [PricingController::class, 'create'])
        ->name('products.pricing.create')
        ->middleware('permission:products.edit');
    
    Route::post('products/{product}/pricing', [PricingController::class, 'store'])
        ->name('products.pricing.store')
        ->middleware('permission:products.edit');
    
    Route::get('products/{product}/pricing/history', [PricingController::class, 'history'])
        ->name('products.pricing.history')
        ->middleware('permission:products.view');
    
    Route::get('pricing/bulk-edit', [PricingController::class, 'bulkEdit'])
        ->name('pricing.bulk-edit')
        ->middleware('permission:products.edit');
    
    Route::post('pricing/bulk-update', [PricingController::class, 'bulkUpdate'])
        ->name('pricing.bulk-update')
        ->middleware('permission:products.edit');
});
```

---

## 5. Form Requests

### StorePricingRequest

**File:** `app/Http/Requests/StorePricingRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('products.edit');
    }

    public function rules(): array
    {
        return [
            'variation_id' => ['nullable', 'exists:product_variations,id'],
            'retail_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'lt:retail_price'],
            'wholesale_min_quantity' => ['nullable', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'gt:retail_price'],
            'effective_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:effective_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'retail_price.required' => 'Please enter a retail price.',
            'wholesale_price.lt' => 'Wholesale price must be less than retail price.',
            'compare_at_price.gt' => 'Compare at price must be greater than retail price.',
            'end_date.after' => 'End date must be after effective date.',
        ];
    }
}
```

---

## 6. Scheduled Task

### Activate Scheduled Pricing Command

**File:** `app/Console/Commands/ActivateScheduledPricing.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\PricingService;
use Illuminate\Console\Command;

class ActivateScheduledPricing extends Command
{
    protected $signature = 'pricing:activate-scheduled';

    protected $description = 'Activate scheduled pricing that has reached effective date';

    public function handle(PricingService $pricingService): int
    {
        $activated = $pricingService->activateScheduledPricing();
        $expired = $pricingService->expireEndedPricing();

        $this->info("Activated: {$activated} pricing entries.");
        $this->info("Expired: {$expired} pricing entries.");

        return Command::SUCCESS;
    }
}
```

Register in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('pricing:activate-scheduled')->daily();
```

---

## 7. Tests (Pest)

**File:** `tests/Feature/PricingManagementTest.php`

```php
<?php

use App\Models\Product;
use App\Models\ProductPricing;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\PricingService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('pricing can be set for a product', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post(route('products.pricing.store', $product), [
        'retail_price' => 100.00,
        'wholesale_price' => 80.00,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('product_pricing', [
        'product_id' => $product->id,
        'retail_price' => 100.00,
    ]);
});

test('wholesale price must be less than retail price', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post(route('products.pricing.store', $product), [
        'retail_price' => 100.00,
        'wholesale_price' => 120.00, // Invalid: higher than retail
    ]);

    $response->assertSessionHasErrors('wholesale_price');
});

test('scheduled pricing is activated on effective date', function () {
    $product = Product::factory()->create();
    
    ProductPricing::factory()->create([
        'product_id' => $product->id,
        'status' => 'scheduled',
        'effective_date' => now()->subDay(),
    ]);

    $service = app(PricingService::class);
    $count = $service->activateScheduledPricing();

    expect($count)->toBe(1);
});

test('pricing history can be retrieved', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $product = Product::factory()->create();
    ProductPricing::factory()->count(5)->create(['product_id' => $product->id]);

    $response = $this->actingAs($user)->get(route('products.pricing.history', $product));

    $response->assertStatus(200);
});
```

**File:** `tests/Unit/PricingTest.php`

```php
<?php

use App\Models\Product;
use App\Models\ProductPricing;
use App\Services\PricingService;

test('pricing service returns correct price for quantity', function () {
    $product = Product::factory()->create();
    ProductPricing::factory()->create([
        'product_id' => $product->id,
        'retail_price' => 100,
        'wholesale_price' => 80,
        'wholesale_min_quantity' => 10,
        'status' => 'active',
        'effective_date' => now()->subDay(),
    ]);

    $service = app(PricingService::class);

    expect($service->getPriceForSale($product, null, 5, false))->toBe(100.0);
    expect($service->getPriceForSale($product, null, 15, false))->toBe(80.0);
});

test('discount percentage is calculated correctly', function () {
    $pricing = ProductPricing::factory()->create([
        'retail_price' => 80,
        'compare_at_price' => 100,
    ]);

    expect($pricing->discount_percentage)->toBe(20.0);
});

test('new active pricing deactivates previous pricing', function () {
    $product = Product::factory()->create();
    
    $oldPricing = ProductPricing::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'effective_date' => now()->subWeek(),
    ]);

    ProductPricing::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'effective_date' => now(),
    ]);

    expect($oldPricing->refresh()->status)->toBe('expired');
});
```

---

## 8. Commands to Execute

```bash
# Step 1: Create Model with migration, factory
php artisan make:model ProductPricing -mf --no-interaction

# Step 2: Create Service
php artisan make:class Services/PricingService --no-interaction

# Step 3: Create Controller
php artisan make:controller PricingController --no-interaction

# Step 4: Create Form Requests
php artisan make:request StorePricingRequest --no-interaction
php artisan make:request BulkPricingRequest --no-interaction

# Step 5: Create Command
php artisan make:command ActivateScheduledPricing --no-interaction

# Step 6: Run migrations
php artisan migrate

# Step 7: Create tests
php artisan make:test PricingManagementTest --pest --no-interaction
php artisan make:test Unit/PricingTest --pest --unit --no-interaction

# Step 8: Run tests
php artisan test --compact --filter=Pricing

# Step 9: Format code
vendor/bin/pint --dirty
```

---

## 9. Verification Checklist

Before proceeding to Module 07, verify:

- [ ] Product pricing table created successfully
- [ ] ProductPricing model with all methods
- [ ] PricingService works correctly
- [ ] Pricing can be set for products
- [ ] Pricing can be set per variation
- [ ] Wholesale pricing logic works
- [ ] Scheduled pricing activates correctly
- [ ] Pricing history is maintained
- [ ] Bulk pricing update works
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 10. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 07: Suppliers](./07-suppliers.md)**
