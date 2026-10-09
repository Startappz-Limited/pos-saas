# Module 04: Categories
## Stock Taking & Sales Management System

### Module Overview
Implement a hierarchical category system for organizing products. Categories support parent-child relationships for multi-level categorization (e.g., Clothing > Men > Shirts).

**Priority:** P1 (High)  
**Dependencies:** Module 03 (Shops Management)  
**Estimated Time:** 1 day

---

## 1. Database Schema

### Categories Table

```sql
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->string('image')->nullable();
    $table->integer('sort_order')->default(0);
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
    $table->softDeletes();
    
    $table->unique(['shop_id', 'slug']);
});
```

### Fields Specification

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint | PK, auto | Primary key |
| parent_id | bigint | FK, nullable | Parent category |
| shop_id | bigint | FK, nullable | Associated shop (null = global) |
| name | string(255) | required | Category name |
| slug | string(255) | unique per shop | URL-friendly identifier |
| description | text | nullable | Category description |
| image | string(255) | nullable | Category image path |
| sort_order | integer | default: 0 | Display order |
| status | enum | default: active | Category status |

---

## 2. Models & Relationships

### Category Model

**File:** `app/Models/Category.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'shop_id',
        'name',
        'slug',
        'description',
        'image',
        'sort_order',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Recursive children (all descendants)
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Shop this category belongs to
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Products in this category (to be added in Module 05)
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Check if category is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if category is a root category
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get full path (breadcrumb)
     */
    public function getPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }

    /**
     * Get all ancestors
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($ancestors, $parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get depth level
     */
    public function getDepthAttribute(): int
    {
        return count($this->getAncestors());
    }

    /**
     * Get image URL or default
     */
    public function getImageUrlAttribute(): string
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : asset('assets/images/categories/default.png');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for root categories
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for specific shop or global
     */
    public function scopeForShop($query, ?int $shopId = null)
    {
        return $query->where(function ($q) use ($shopId) {
            $q->whereNull('shop_id');
            if ($shopId) {
                $q->orWhere('shop_id', $shopId);
            }
        });
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
```

---

## 3. Controllers & Routes

### CategoryController

**File:** `app/Http/Controllers/CategoryController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $shopId = $request->get('shop_id');
        
        $categories = Category::with(['parent', 'children', 'shop'])
            ->forShop($shopId)
            ->root()
            ->ordered()
            ->paginate(15);

        $shops = Shop::active()->get();

        return view('categories.index', compact('categories', 'shops', 'shopId'));
    }

    public function create(): View
    {
        $shops = Shop::active()->get();
        $parentCategories = Category::root()->active()->ordered()->get();

        return view('categories.create', compact('shops', 'parentCategories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        Category::create($data);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(Category $category): View
    {
        $category->load(['parent', 'children', 'products']);

        return view('categories.show', compact('category'));
    }

    public function edit(Category $category): View
    {
        $shops = Shop::active()->get();
        
        // Exclude this category and its descendants from parent options
        $excludeIds = $this->getDescendantIds($category);
        $excludeIds[] = $category->id;
        
        $parentCategories = Category::whereNotIn('id', $excludeIds)
            ->active()
            ->ordered()
            ->get();

        return view('categories.edit', compact('category', 'shops', 'parentCategories'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Check for child categories
        if ($category->hasChildren()) {
            return back()->with('error', 'Cannot delete category with sub-categories.');
        }

        // Check for products
        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete category with products.');
        }

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    /**
     * Update category sort order (AJAX)
     */
    public function updateOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'exists:categories,id'],
            'categories.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->categories as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get all descendant IDs
     */
    private function getDescendantIds(Category $category): array
    {
        $ids = [];
        
        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        
        return $ids;
    }
}
```

### Routes

**File:** `routes/web.php` (add to authenticated routes)

```php
use App\Http\Controllers\CategoryController;

Route::middleware(['auth'])->group(function () {
    // ... existing routes

    // Categories Management
    Route::resource('categories', CategoryController::class)->middleware('permission:products.view');
    Route::post('categories/update-order', [CategoryController::class, 'updateOrder'])
        ->name('categories.update-order')
        ->middleware('permission:products.edit');
});
```

---

## 4. Form Requests (Validation)

### StoreCategoryRequest

**File:** `app/Http/Requests/StoreCategoryRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('products.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a category name.',
            'parent_id.exists' => 'The selected parent category is invalid.',
            'shop_id.exists' => 'The selected shop is invalid.',
        ];
    }
}
```

### UpdateCategoryRequest

**File:** `app/Http/Requests/UpdateCategoryRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('products.edit');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                // Prevent setting self as parent
                function ($attribute, $value, $fail) {
                    if ($value == $this->category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
```

---

## 5. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Category List | `design/src/category-list.php` |
| Add Category | `design/src/category-add.php` |
| Edit Category | `design/src/category-edit.php` |
| Category Details | `design/src/category-details.php` |

### View Structure

```
resources/views/
├── categories/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   └── partials/
│       └── category-tree.blade.php
└── components/
    └── category-select.blade.php
```

---

## 6. Factory & Seeder

### CategoryFactory

**File:** `database/factories/CategoryFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);
        
        return [
            'parent_id' => null,
            'shop_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => 'active',
        ];
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    public function withParent(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'shop_id' => $parent->shop_id,
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

### CategorySeeder

**File:** `database/seeders/CategorySeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Global Categories
        $globalCategories = [
            'Clothing' => ['Men', 'Women', 'Kids'],
            'Electronics' => ['Phones', 'Laptops', 'Accessories'],
            'Home & Kitchen' => ['Appliances', 'Furniture', 'Decor'],
            'Sports & Fitness' => ['Equipment', 'Apparel', 'Accessories'],
        ];

        foreach ($globalCategories as $parentName => $children) {
            $parent = Category::firstOrCreate(
                ['name' => $parentName, 'shop_id' => null],
                [
                    'description' => "Products in {$parentName} category",
                    'status' => 'active',
                ]
            );

            foreach ($children as $index => $childName) {
                Category::firstOrCreate(
                    ['name' => $childName, 'parent_id' => $parent->id],
                    [
                        'description' => "{$childName} products",
                        'sort_order' => $index,
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
```

---

## 7. Tests (Pest)

**File:** `tests/Feature/CategoryManagementTest.php`

```php
<?php

use App\Models\Category;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('categories list page can be rendered', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get(route('categories.index'));

    $response->assertStatus(200);
});

test('authorized user can create a category', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'New Category',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('categories', ['name' => 'New Category']);
});

test('category can have a parent category', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $parent = Category::factory()->create();

    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Child Category',
        'parent_id' => $parent->id,
        'status' => 'active',
    ]);

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('categories', [
        'name' => 'Child Category',
        'parent_id' => $parent->id,
    ]);
});

test('category cannot be its own parent', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->put(route('categories.update', $category), [
        'name' => $category->name,
        'parent_id' => $category->id,
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('parent_id');
});

test('category with children cannot be deleted', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $parent = Category::factory()->create();
    Category::factory()->withParent($parent)->create();

    $response = $this->actingAs($user)->delete(route('categories.destroy', $parent));

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('categories', ['id' => $parent->id]);
});

test('category can be filtered by shop', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop = Shop::factory()->create();
    Category::factory()->count(3)->forShop($shop)->create();
    Category::factory()->count(2)->create(); // Global categories

    $response = $this->actingAs($user)->get(route('categories.index', ['shop_id' => $shop->id]));

    $response->assertStatus(200);
});
```

**File:** `tests/Unit/CategoryTest.php`

```php
<?php

use App\Models\Category;

test('category path is generated correctly', function () {
    $grandparent = Category::factory()->create(['name' => 'Level 1']);
    $parent = Category::factory()->withParent($grandparent)->create(['name' => 'Level 2']);
    $child = Category::factory()->withParent($parent)->create(['name' => 'Level 3']);

    expect($child->path)->toBe('Level 1 > Level 2 > Level 3');
});

test('category depth is calculated correctly', function () {
    $root = Category::factory()->create();
    $level1 = Category::factory()->withParent($root)->create();
    $level2 = Category::factory()->withParent($level1)->create();

    expect($root->depth)->toBe(0);
    expect($level1->depth)->toBe(1);
    expect($level2->depth)->toBe(2);
});

test('category ancestors are retrieved correctly', function () {
    $grandparent = Category::factory()->create(['name' => 'Grandparent']);
    $parent = Category::factory()->withParent($grandparent)->create(['name' => 'Parent']);
    $child = Category::factory()->withParent($parent)->create(['name' => 'Child']);

    $ancestors = $child->getAncestors();

    expect(count($ancestors))->toBe(2);
    expect($ancestors[0]->name)->toBe('Grandparent');
    expect($ancestors[1]->name)->toBe('Parent');
});

test('category slug is generated automatically', function () {
    $category = Category::factory()->create(['name' => 'Test Category']);

    expect($category->slug)->toBe('test-category');
});

test('root scope returns only root categories', function () {
    Category::factory()->count(3)->create();
    $parent = Category::factory()->create();
    Category::factory()->count(2)->withParent($parent)->create();

    $rootCategories = Category::root()->get();

    expect($rootCategories->count())->toBe(4);
});
```

---

## 8. Commands to Execute

Execute these commands in order:

```bash
# Step 1: Create Model with migration, factory, seeder
php artisan make:model Category -mfs --no-interaction

# Step 2: Create Controller
php artisan make:controller CategoryController --resource --no-interaction

# Step 3: Create Form Requests
php artisan make:request StoreCategoryRequest --no-interaction
php artisan make:request UpdateCategoryRequest --no-interaction

# Step 4: Run migrations
php artisan migrate

# Step 5: Run seeder
php artisan db:seed --class=CategorySeeder

# Step 6: Create tests
php artisan make:test CategoryManagementTest --pest --no-interaction
php artisan make:test Unit/CategoryTest --pest --unit --no-interaction

# Step 7: Run tests
php artisan test --compact --filter=Category

# Step 8: Format code
vendor/bin/pint --dirty
```

---

## 9. Verification Checklist

Before proceeding to Module 05, verify:

- [ ] Categories table migration runs without errors
- [ ] Category model with all relationships and methods
- [ ] Parent-child relationships work correctly
- [ ] Category path (breadcrumb) generates correctly
- [ ] CategoryController CRUD operations work
- [ ] Category seeder creates sample data
- [ ] Categories can be filtered by shop
- [ ] Sort order functionality works
- [ ] Cannot delete categories with children
- [ ] Slug auto-generation works
- [ ] All tests pass (`php artisan test --compact --filter=Category`)
- [ ] Code formatted with Pint

---

## 10. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 05: Products & Variations](./05-products-variations.md)**
