# Module 05: Products & Variations
## Stock Taking & Sales Management System

### Module Overview
Implement product management with support for product variations (size, color, etc.). Each product can have multiple variations, each with its own SKU for inventory tracking.

**Priority:** P1 (High)  
**Dependencies:** Module 03, Module 04  
**Estimated Time:** 2-3 days

---

## 1. Database Schema

### Products Table

```sql
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
    $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->string('brand')->nullable();
    $table->string('unit')->default('pcs'); // pcs, kg, liters, etc.
    $table->boolean('has_variations')->default(false);
    $table->boolean('track_stock')->default(true);
    $table->integer('low_stock_threshold')->default(10);
    $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
    $table->json('images')->nullable(); // Multiple product images
    $table->json('meta')->nullable(); // Additional metadata
    $table->timestamps();
    $table->softDeletes();
    
    $table->unique(['shop_id', 'slug']);
    $table->index(['shop_id', 'category_id']);
    $table->index('status');
});
```

### Product Variations Table

```sql
Schema::create('product_variations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('sku')->unique();
    $table->string('name'); // e.g., "Red - Large"
    $table->json('attributes'); // e.g., {"color": "Red", "size": "Large"}
    $table->string('barcode')->nullable()->unique();
    $table->string('image')->nullable();
    $table->decimal('weight', 10, 2)->nullable();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('sku');
    $table->index('barcode');
});
```

### Attribute Types Table (for variation options)

```sql
Schema::create('attribute_types', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('name'); // e.g., Color, Size
    $table->string('slug');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    
    $table->unique(['shop_id', 'slug']);
});
```

### Attribute Values Table

```sql
Schema::create('attribute_values', function (Blueprint $table) {
    $table->id();
    $table->foreignId('attribute_type_id')->constrained()->cascadeOnDelete();
    $table->string('value'); // e.g., Red, Blue, XL
    $table->string('label')->nullable(); // Display label
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    
    $table->unique(['attribute_type_id', 'value']);
});
```

---

## 2. Models & Relationships

### Product Model

**File:** `app/Models/Product.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'category_id',
        'name',
        'slug',
        'description',
        'brand',
        'unit',
        'has_variations',
        'track_stock',
        'low_stock_threshold',
        'status',
        'images',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'has_variations' => 'boolean',
            'track_stock' => 'boolean',
            'images' => 'array',
            'meta' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Shop this product belongs to
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Category this product belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Product variations
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * Active variations only
     */
    public function activeVariations(): HasMany
    {
        return $this->variations()->where('status', 'active');
    }

    /**
     * Current pricing (to be added in Module 06)
     */
    public function currentPricing(): HasOne
    {
        return $this->hasOne(ProductPricing::class)
            ->where('status', 'active')
            ->latest('effective_date');
    }

    /**
     * All pricing history (to be added in Module 06)
     */
    public function pricingHistory(): HasMany
    {
        return $this->hasMany(ProductPricing::class)->orderByDesc('effective_date');
    }

    /**
     * Stock batches for this product (to be added in Module 08)
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Check if product is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get primary image
     */
    public function getPrimaryImageAttribute(): ?string
    {
        $images = $this->images ?? [];
        return !empty($images) ? $images[0] : null;
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImageUrlAttribute(): string
    {
        return $this->primary_image
            ? asset('storage/' . $this->primary_image)
            : asset('assets/images/products/default.png');
    }

    /**
     * Get total stock across all variations
     */
    public function getTotalStockAttribute(): int
    {
        if (!$this->track_stock) {
            return PHP_INT_MAX;
        }

        return $this->stockBatches()
            ->where('remaining_quantity', '>', 0)
            ->sum('remaining_quantity');
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->track_stock && $this->total_stock <= $this->low_stock_threshold;
    }

    /**
     * Get or create default variation for non-variation products
     */
    public function getDefaultVariation(): ProductVariation
    {
        if (!$this->has_variations) {
            return $this->variations()->firstOrCreate(
                ['product_id' => $this->id],
                [
                    'sku' => $this->generateSku(),
                    'name' => $this->name,
                    'attributes' => [],
                    'status' => 'active',
                ]
            );
        }

        return $this->variations()->first();
    }

    /**
     * Generate SKU
     */
    public function generateSku(array $attributes = []): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $this->name), 0, 3));
        $shopCode = strtoupper(substr($this->shop->slug, 0, 2));
        $random = strtoupper(Str::random(4));
        
        if (!empty($attributes)) {
            $attrCode = strtoupper(substr(implode('', array_values($attributes)), 0, 4));
            return "{$shopCode}-{$prefix}-{$attrCode}-{$random}";
        }
        
        return "{$shopCode}-{$prefix}-{$random}";
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for specific shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope for low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereHas('stockBatches', function ($q) {
                $q->selectRaw('product_id, SUM(remaining_quantity) as total')
                    ->groupBy('product_id');
            });
    }

    /**
     * Search scope
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhereHas('variations', function ($vq) use ($term) {
                    $vq->where('sku', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%");
                });
        });
    }
}
```

### ProductVariation Model

**File:** `app/Models/ProductVariation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'attributes',
        'barcode',
        'image',
        'weight',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'weight' => 'decimal:2',
        ];
    }

    /**
     * Product this variation belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Current pricing (to be added in Module 06)
     */
    public function currentPricing(): HasOne
    {
        return $this->hasOne(ProductPricing::class)
            ->where('status', 'active')
            ->latest('effective_date');
    }

    /**
     * Stock batches for this variation (to be added in Module 08)
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Check if variation is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get image URL (fallback to product image)
     */
    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }

        return $this->product->primary_image_url;
    }

    /**
     * Get full name with product
     */
    public function getFullNameAttribute(): string
    {
        if ($this->product->has_variations) {
            return "{$this->product->name} - {$this->name}";
        }
        
        return $this->product->name;
    }

    /**
     * Get attribute display string
     */
    public function getAttributeDisplayAttribute(): string
    {
        if (empty($this->attributes)) {
            return '';
        }

        return collect($this->attributes)
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(', ');
    }

    /**
     * Get current stock
     */
    public function getCurrentStockAttribute(): int
    {
        return $this->stockBatches()
            ->where('remaining_quantity', '>', 0)
            ->sum('remaining_quantity');
    }

    /**
     * Scope for active variations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope by SKU or barcode
     */
    public function scopeFindByCode($query, string $code)
    {
        return $query->where('sku', $code)->orWhere('barcode', $code);
    }
}
```

### AttributeType Model

**File:** `app/Models/AttributeType.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttributeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AttributeType $type) {
            if (empty($type->slug)) {
                $type->slug = Str::slug($type->name);
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }
}
```

### AttributeValue Model

**File:** `app/Models/AttributeValue.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_type_id',
        'value',
        'label',
        'sort_order',
    ];

    public function attributeType(): BelongsTo
    {
        return $this->belongsTo(AttributeType::class);
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?? $this->value;
    }
}
```

---

## 3. Controllers & Routes

### ProductController

**File:** `app/Http/Controllers/ProductController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $categoryId = $request->get('category_id');
        $search = $request->get('search');
        $status = $request->get('status');

        $products = Product::with(['shop', 'category', 'variations'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->search($search))
            ->latest()
            ->paginate(15);

        $shops = Shop::active()->get();
        $categories = Category::active()->ordered()->get();

        return view('products.index', compact('products', 'shops', 'categories', 'shopId', 'categoryId', 'search', 'status'));
    }

    public function create(): View
    {
        $shops = Shop::active()->get();
        $categories = Category::active()->ordered()->get();

        return view('products.create', compact('shops', 'categories'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Handle images
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('products', 'public');
            }
            $data['images'] = $images;
        }

        $product = Product::create($data);

        // Create default variation if no variations
        if (!$product->has_variations) {
            $product->getDefaultVariation();
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $product->load(['shop', 'category', 'variations', 'currentPricing']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $shops = Shop::active()->get();
        $categories = Category::active()->ordered()->get();

        return view('products.edit', compact('product', 'shops', 'categories'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        // Handle images
        if ($request->hasFile('images')) {
            // Delete old images
            foreach ($product->images ?? [] as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
            
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('products', 'public');
            }
            $data['images'] = $images;
        }

        $product->update($data);

        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Check for stock
        if ($product->stockBatches()->where('remaining_quantity', '>', 0)->exists()) {
            return back()->with('error', 'Cannot delete product with existing stock.');
        }

        // Delete images
        foreach ($product->images ?? [] as $image) {
            Storage::disk('public')->delete($image);
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
```

### ProductVariationController

**File:** `app/Http/Controllers/ProductVariationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVariationRequest;
use App\Http\Requests\UpdateVariationRequest;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductVariationController extends Controller
{
    public function create(Product $product): View
    {
        return view('products.variations.create', compact('product'));
    }

    public function store(StoreVariationRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $data['product_id'] = $product->id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products/variations', 'public');
        }

        // Auto-generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = $product->generateSku($data['attributes'] ?? []);
        }

        // Generate name from attributes if not provided
        if (empty($data['name']) && !empty($data['attributes'])) {
            $data['name'] = implode(' - ', $data['attributes']);
        }

        ProductVariation::create($data);

        // Mark product as having variations
        if (!$product->has_variations) {
            $product->update(['has_variations' => true]);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Variation added successfully.');
    }

    public function edit(Product $product, ProductVariation $variation): View
    {
        return view('products.variations.edit', compact('product', 'variation'));
    }

    public function update(UpdateVariationRequest $request, Product $product, ProductVariation $variation): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products/variations', 'public');
        }

        $variation->update($data);

        return redirect()->route('products.show', $product)
            ->with('success', 'Variation updated successfully.');
    }

    public function destroy(Product $product, ProductVariation $variation): RedirectResponse
    {
        // Check for stock
        if ($variation->stockBatches()->where('remaining_quantity', '>', 0)->exists()) {
            return back()->with('error', 'Cannot delete variation with existing stock.');
        }

        $variation->delete();

        return redirect()->route('products.show', $product)
            ->with('success', 'Variation deleted successfully.');
    }
}
```

### Routes

**File:** `routes/web.php` (add to authenticated routes)

```php
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariationController;

Route::middleware(['auth'])->group(function () {
    // ... existing routes

    // Products Management
    Route::resource('products', ProductController::class)->middleware('permission:products.view');
    
    // Product Variations
    Route::resource('products.variations', ProductVariationController::class)
        ->except(['index', 'show'])
        ->middleware('permission:products.edit');
});
```

---

## 4. Form Requests

### StoreProductRequest

**File:** `app/Http/Requests/StoreProductRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('products.create');
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'exists:shops,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'has_variations' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,discontinued'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_id.required' => 'Please select a shop.',
            'name.required' => 'Please enter a product name.',
            'images.max' => 'You can upload a maximum of 5 images.',
        ];
    }
}
```

### StoreVariationRequest

**File:** `app/Http/Requests/StoreVariationRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('products.edit');
    }

    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:100', 'unique:product_variations,sku'],
            'name' => ['nullable', 'string', 'max:255'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:product_variations,barcode'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
```

---

## 5. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Product List | `design/src/product-list.php` |
| Product Grid | `design/src/product-grid.php` |
| Add Product | `design/src/product-add.php` |
| Edit Product | `design/src/product-edit.php` |
| Product Details | `design/src/product-details.php` |
| Attributes List | `design/src/attributes-list.php` |
| Add Attribute | `design/src/attributes-add.php` |

### View Structure

```
resources/views/
├── products/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   └── variations/
│       ├── create.blade.php
│       └── edit.blade.php
└── components/
    ├── product-card.blade.php
    └── variation-table.blade.php
```

---

## 6. Factory & Seeder

### ProductFactory

**File:** `database/factories/ProductFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);
        
        return [
            'shop_id' => Shop::factory(),
            'category_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'brand' => fake()->company(),
            'unit' => fake()->randomElement(['pcs', 'kg', 'liters', 'meters']),
            'has_variations' => false,
            'track_stock' => true,
            'low_stock_threshold' => fake()->numberBetween(5, 20),
            'status' => 'active',
        ];
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function withVariations(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_variations' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
```

### ProductVariationFactory

**File:** `database/factories/ProductVariationFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductVariationFactory extends Factory
{
    public function definition(): array
    {
        $color = fake()->colorName();
        $size = fake()->randomElement(['S', 'M', 'L', 'XL']);
        
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(Str::random(10)),
            'name' => "{$color} - {$size}",
            'attributes' => [
                'color' => $color,
                'size' => $size,
            ],
            'barcode' => fake()->ean13(),
            'weight' => fake()->randomFloat(2, 0.1, 10),
            'status' => 'active',
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
```

---

## 7. Tests (Pest)

**File:** `tests/Feature/ProductManagementTest.php`

```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('products list page can be rendered', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get(route('products.index'));

    $response->assertStatus(200);
});

test('authorized user can create a product', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->post(route('products.store'), [
        'shop_id' => $shop->id,
        'name' => 'Test Product',
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', ['name' => 'Test Product']);
});

test('product creates default variation when not has_variations', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $shop = Shop::factory()->create();

    $this->actingAs($user)->post(route('products.store'), [
        'shop_id' => $shop->id,
        'name' => 'Simple Product',
        'has_variations' => false,
    ]);

    $product = Product::where('name', 'Simple Product')->first();
    expect($product->variations()->count())->toBe(1);
});

test('products can be filtered by shop', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop1 = Shop::factory()->create();
    $shop2 = Shop::factory()->create();
    
    Product::factory()->count(3)->forShop($shop1)->create();
    Product::factory()->count(2)->forShop($shop2)->create();

    $response = $this->actingAs($user)->get(route('products.index', ['shop_id' => $shop1->id]));

    $response->assertStatus(200);
});

test('products can be searched', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop = Shop::factory()->create();
    Product::factory()->forShop($shop)->create(['name' => 'Unique Product Name']);
    Product::factory()->count(5)->forShop($shop)->create();

    $response = $this->actingAs($user)->get(route('products.index', ['search' => 'Unique']));

    $response->assertStatus(200);
});

test('variation can be added to product', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $product = Product::factory()->create(['has_variations' => true]);

    $response = $this->actingAs($user)->post(route('products.variations.store', $product), [
        'sku' => 'TEST-SKU-001',
        'name' => 'Red - Large',
        'attributes' => ['color' => 'Red', 'size' => 'Large'],
    ]);

    $response->assertRedirect(route('products.show', $product));
    $this->assertDatabaseHas('product_variations', ['sku' => 'TEST-SKU-001']);
});
```

**File:** `tests/Unit/ProductTest.php`

```php
<?php

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;

test('product generates slug automatically', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->forShop($shop)->create(['name' => 'My Test Product']);

    expect($product->slug)->toBe('my-test-product');
});

test('product can generate SKU', function () {
    $shop = Shop::factory()->create(['slug' => 'test-shop']);
    $product = Product::factory()->forShop($shop)->create(['name' => 'Test Product']);

    $sku = $product->generateSku();

    expect($sku)->toStartWith('TE-TES-');
});

test('product generates SKU with attributes', function () {
    $shop = Shop::factory()->create(['slug' => 'test-shop']);
    $product = Product::factory()->forShop($shop)->create(['name' => 'Test Product']);

    $sku = $product->generateSku(['color' => 'Red', 'size' => 'L']);

    expect($sku)->toContain('REDL');
});

test('default variation is created for simple products', function () {
    $product = Product::factory()->create(['has_variations' => false]);
    
    $variation = $product->getDefaultVariation();

    expect($variation)->toBeInstanceOf(ProductVariation::class);
    expect($product->variations()->count())->toBe(1);
});
```

---

## 8. Commands to Execute

```bash
# Step 1: Create Models with migrations, factories
php artisan make:model Product -mfs --no-interaction
php artisan make:model ProductVariation -mfs --no-interaction
php artisan make:model AttributeType -mf --no-interaction
php artisan make:model AttributeValue -mf --no-interaction

# Step 2: Create Controllers
php artisan make:controller ProductController --resource --no-interaction
php artisan make:controller ProductVariationController --no-interaction

# Step 3: Create Form Requests
php artisan make:request StoreProductRequest --no-interaction
php artisan make:request UpdateProductRequest --no-interaction
php artisan make:request StoreVariationRequest --no-interaction
php artisan make:request UpdateVariationRequest --no-interaction

# Step 4: Run migrations
php artisan migrate

# Step 5: Create tests
php artisan make:test ProductManagementTest --pest --no-interaction
php artisan make:test Unit/ProductTest --pest --unit --no-interaction

# Step 6: Run tests
php artisan test --compact --filter=Product

# Step 7: Format code
vendor/bin/pint --dirty
```

---

## 9. Verification Checklist

Before proceeding to Module 06, verify:

- [ ] Products table migration runs without errors
- [ ] Product variations table created successfully
- [ ] Attribute tables created successfully
- [ ] Product model with all relationships
- [ ] ProductVariation model with all methods
- [ ] SKU generation works correctly
- [ ] Default variation created for simple products
- [ ] Products can be filtered and searched
- [ ] Variations can be added/edited/deleted
- [ ] Product images upload works
- [ ] All tests pass (`php artisan test --compact --filter=Product`)
- [ ] Code formatted with Pint

---

## 10. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 06: Pricing Management](./06-pricing-management.md)**
