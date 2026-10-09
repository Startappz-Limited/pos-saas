# Module 03: Shops Management
## Stock Taking & Sales Management System

### Module Overview
Implement multi-shop management allowing the platform to support multiple independent shops (e.g., Kids Store, Fitness Store, Kitchen Shop) under one system. Each shop operates independently with its own products, stock, and sales.

**Priority:** P0 (Critical)  
**Dependencies:** Module 01, Module 02  
**Estimated Time:** 1-2 days

---

## 1. Database Schema

### Shops Table

```sql
Schema::create('shops', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('location')->nullable();
    $table->string('address')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->string('logo')->nullable();
    $table->text('description')->nullable();
    $table->enum('status', ['active', 'inactive', 'closed'])->default('active');
    $table->json('settings')->nullable(); // Store shop-specific settings
    $table->timestamps();
    $table->softDeletes();
});
```

### Shop-User Pivot Table

```sql
Schema::create('shop_user', function (Blueprint $table) {
    $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_primary')->default(false); // User's primary shop
    $table->timestamps();
    $table->primary(['shop_id', 'user_id']);
});
```

### Fields Specification

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint | PK, auto | Primary key |
| name | string(255) | required | Shop name |
| slug | string(255) | unique | URL-friendly identifier |
| location | string(255) | nullable | General location/area |
| address | text | nullable | Full address |
| phone | string(20) | nullable | Contact phone |
| email | string(255) | nullable | Contact email |
| logo | string(255) | nullable | Logo image path |
| description | text | nullable | Shop description |
| status | enum | default: active | Shop status |
| settings | json | nullable | Custom settings |
| created_at | timestamp | auto | Record creation |
| updated_at | timestamp | auto | Record update |
| deleted_at | timestamp | nullable | Soft delete |

---

## 2. Models & Relationships

### Shop Model

**File:** `app/Models/Shop.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'location',
        'address',
        'phone',
        'email',
        'logo',
        'description',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Shop $shop) {
            if (empty($shop->slug)) {
                $shop->slug = Str::slug($shop->name);
            }
        });
    }

    /**
     * Users assigned to this shop
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Products in this shop (to be added in Module 05)
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Stock batches in this shop (to be added in Module 08)
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Sales in this shop (to be added in Module 10)
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Expenses for this shop (to be added in Module 14)
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Check if shop is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get logo URL or default
     */
    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/' . $this->logo)
            : asset('assets/images/shops/default-logo.png');
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a specific setting value
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Scope for active shops
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
```

### User Model Updates

**File:** `app/Models/User.php` (add to existing)

```php
// Add relationship
/**
 * Shops this user is assigned to
 */
public function shops(): BelongsToMany
{
    return $this->belongsToMany(Shop::class, 'shop_user')
        ->withPivot('is_primary')
        ->withTimestamps();
}

/**
 * Get user's primary shop
 */
public function primaryShop(): ?Shop
{
    return $this->shops()->wherePivot('is_primary', true)->first();
}

/**
 * Check if user belongs to a specific shop
 */
public function belongsToShop(Shop|int $shop): bool
{
    $shopId = $shop instanceof Shop ? $shop->id : $shop;
    return $this->shops()->where('shops.id', $shopId)->exists();
}

/**
 * Assign user to shop
 */
public function assignToShop(Shop|int $shop, bool $isPrimary = false): void
{
    $shopId = $shop instanceof Shop ? $shop->id : $shop;
    
    // If this is primary, remove primary from other shops
    if ($isPrimary) {
        $this->shops()->updateExistingPivot(
            $this->shops->pluck('id')->toArray(),
            ['is_primary' => false]
        );
    }
    
    $this->shops()->syncWithoutDetaching([
        $shopId => ['is_primary' => $isPrimary]
    ]);
}

/**
 * Remove user from shop
 */
public function removeFromShop(Shop|int $shop): void
{
    $shopId = $shop instanceof Shop ? $shop->id : $shop;
    $this->shops()->detach($shopId);
}
```

### Implemented Shop Access Semantics

- Users are allocated to zero or more shops through the `shop_user` pivot.
- A user with one or more allocated shops is restricted to records for those shops.
- A user with no allocated shops is treated as unrestricted and can view or act across all shops, subject to their permissions.
- Dashboard, sales, website orders, shop listings, and related model policies apply this shop visibility rule before returning records or allowing actions.
- User create/edit forms accept multiple allocated shops; leaving the allocation empty intentionally grants all-shop access.

---

## 3. Controllers & Routes

### ShopController

**File:** `app/Http/Controllers/ShopController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        $shops = Shop::withCount(['users', 'products'])
            ->latest()
            ->paginate(10);

        return view('shops.index', compact('shops'));
    }

    public function create(): View
    {
        return view('shops.create');
    }

    public function store(StoreShopRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('shops/logos', 'public');
        }

        $shop = Shop::create($data);

        return redirect()->route('shops.index')
            ->with('success', 'Shop created successfully.');
    }

    public function show(Shop $shop): View
    {
        $shop->load(['users']);

        return view('shops.show', compact('shop'));
    }

    public function edit(Shop $shop): View
    {
        return view('shops.edit', compact('shop'));
    }

    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($shop->logo) {
                Storage::disk('public')->delete($shop->logo);
            }
            $data['logo'] = $request->file('logo')->store('shops/logos', 'public');
        }

        $shop->update($data);

        return redirect()->route('shops.index')
            ->with('success', 'Shop updated successfully.');
    }

    public function destroy(Shop $shop): RedirectResponse
    {
        // Check if shop has related data
        if ($shop->products()->exists() || $shop->sales()->exists()) {
            return back()->with('error', 'Cannot delete shop with existing products or sales.');
        }

        if ($shop->logo) {
            Storage::disk('public')->delete($shop->logo);
        }

        $shop->delete();

        return redirect()->route('shops.index')
            ->with('success', 'Shop deleted successfully.');
    }

    /**
     * Assign users to shop
     */
    public function assignUsers(Request $request, Shop $shop): RedirectResponse
    {
        $request->validate([
            'users' => ['required', 'array'],
            'users.*' => ['exists:users,id'],
        ]);

        $shop->users()->sync($request->users);

        return back()->with('success', 'Users assigned successfully.');
    }
}
```

### Routes

**File:** `routes/web.php` (add to authenticated routes)

```php
use App\Http\Controllers\ShopController;

Route::middleware(['auth'])->group(function () {
    // ... existing routes

    // Shops Management
    Route::resource('shops', ShopController::class)->middleware('permission:shops.view');
    Route::post('shops/{shop}/assign-users', [ShopController::class, 'assignUsers'])
        ->name('shops.assign-users')
        ->middleware('permission:shops.edit');
});
```

---

## 4. Form Requests (Validation)

### StoreShopRequest

**File:** `app/Http/Requests/StoreShopRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('shops.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:active,inactive,closed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a shop name.',
            'email.email' => 'Please enter a valid email address.',
            'logo.image' => 'The logo must be an image file.',
            'logo.max' => 'The logo must not exceed 2MB.',
        ];
    }
}
```

### UpdateShopRequest

**File:** `app/Http/Requests/UpdateShopRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('shops.edit');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive,closed'],
        ];
    }
}
```

---

## 5. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Shop List | `design/src/seller-list.php` |
| Add Shop | `design/src/seller-add.php` |
| Edit Shop | `design/src/seller-edit.php` |
| Shop Details | `design/src/seller-details.php` |

### View Structure

```
resources/views/
├── shops/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
└── components/
    └── shop-card.blade.php
```

---

## 6. Factory & Seeder

### ShopFactory

**File:** `database/factories/ShopFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShopFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'location' => fake()->city(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'description' => fake()->paragraph(),
            'status' => 'active',
            'settings' => [
                'currency' => 'KES',
                'tax_rate' => 16,
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }
}
```

### ShopSeeder

**File:** `database/seeders/ShopSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        // Create default shops
        $shops = [
            [
                'name' => 'Kids Store',
                'location' => 'Main Mall',
                'description' => 'Children\'s clothing and accessories',
            ],
            [
                'name' => 'Fitness Store',
                'location' => 'Sports Complex',
                'description' => 'Fitness equipment and sportswear',
            ],
            [
                'name' => 'Kitchen Shop',
                'location' => 'Town Center',
                'description' => 'Kitchen appliances and utensils',
            ],
        ];

        foreach ($shops as $shopData) {
            Shop::firstOrCreate(
                ['name' => $shopData['name']],
                $shopData
            );
        }

        // Assign super admin to all shops
        $superAdmin = User::whereHas('roles', function ($query) {
            $query->where('slug', 'super-admin');
        })->first();

        if ($superAdmin) {
            $allShops = Shop::pluck('id')->toArray();
            foreach ($allShops as $index => $shopId) {
                $superAdmin->assignToShop($shopId, $index === 0);
            }
        }
    }
}
```

---

## 7. Tests (Pest)

**File:** `tests/Feature/ShopManagementTest.php`

```php
<?php

use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('shops list page can be rendered', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get(route('shops.index'));

    $response->assertStatus(200);
});

test('authorized user can create a shop', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'New Test Shop',
        'location' => 'Test Location',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('shops.index'));
    $this->assertDatabaseHas('shops', ['name' => 'New Test Shop']);
});

test('unauthorized user cannot create a shop', function () {
    $user = User::factory()->create();
    $user->assignRole('sales');

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
    ]);

    $response->assertStatus(403);
});

test('shop can be updated', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->put(route('shops.update', $shop), [
        'name' => 'Updated Shop Name',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('shops.index'));
    expect($shop->refresh()->name)->toBe('Updated Shop Name');
});

test('shop without dependencies can be deleted', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->delete(route('shops.destroy', $shop));

    $response->assertRedirect(route('shops.index'));
    $this->assertSoftDeleted('shops', ['id' => $shop->id]);
});

test('users can be assigned to shop', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    
    $shop = Shop::factory()->create();
    $users = User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->post(route('shops.assign-users', $shop), [
        'users' => $users->pluck('id')->toArray(),
    ]);

    $response->assertSessionHas('success');
    expect($shop->users->count())->toBe(3);
});

test('shop slug is generated automatically', function () {
    $shop = Shop::factory()->create(['name' => 'My Awesome Shop']);

    expect($shop->slug)->toBe('my-awesome-shop');
});

test('active scope returns only active shops', function () {
    Shop::factory()->count(3)->create(['status' => 'active']);
    Shop::factory()->count(2)->create(['status' => 'inactive']);

    $activeShops = Shop::active()->get();

    expect($activeShops->count())->toBe(3);
});
```

**File:** `tests/Unit/ShopTest.php`

```php
<?php

use App\Models\Shop;
use App\Models\User;

test('shop has settings accessor', function () {
    $shop = Shop::factory()->create([
        'settings' => ['currency' => 'USD', 'tax_rate' => 10],
    ]);

    expect($shop->getSetting('currency'))->toBe('USD');
    expect($shop->getSetting('tax_rate'))->toBe(10);
    expect($shop->getSetting('unknown', 'default'))->toBe('default');
});

test('shop can update single setting', function () {
    $shop = Shop::factory()->create([
        'settings' => ['currency' => 'USD'],
    ]);

    $shop->setSetting('currency', 'KES');

    expect($shop->refresh()->getSetting('currency'))->toBe('KES');
});

test('shop is active check works', function () {
    $activeShop = Shop::factory()->create(['status' => 'active']);
    $inactiveShop = Shop::factory()->create(['status' => 'inactive']);

    expect($activeShop->isActive())->toBeTrue();
    expect($inactiveShop->isActive())->toBeFalse();
});
```

**File:** `tests/Unit/UserShopTest.php`

```php
<?php

use App\Models\Shop;
use App\Models\User;

test('user can be assigned to multiple shops', function () {
    $user = User::factory()->create();
    $shops = Shop::factory()->count(3)->create();

    foreach ($shops as $shop) {
        $user->assignToShop($shop);
    }

    expect($user->shops->count())->toBe(3);
});

test('user can have a primary shop', function () {
    $user = User::factory()->create();
    $shop1 = Shop::factory()->create();
    $shop2 = Shop::factory()->create();

    $user->assignToShop($shop1);
    $user->assignToShop($shop2, isPrimary: true);

    expect($user->primaryShop()->id)->toBe($shop2->id);
});

test('user belongs to shop check works', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();

    $user->assignToShop($shop);

    expect($user->belongsToShop($shop))->toBeTrue();
    expect($user->belongsToShop($otherShop))->toBeFalse();
});

test('user can be removed from shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create();

    $user->assignToShop($shop);
    $user->removeFromShop($shop);

    expect($user->belongsToShop($shop))->toBeFalse();
});
```

---

## 8. Commands to Execute

Execute these commands in order:

```bash
# Step 1: Create Model with migration, factory, seeder
php artisan make:model Shop -mfs --no-interaction

# Step 2: Create pivot table migration
php artisan make:migration create_shop_user_table --no-interaction

# Step 3: Create Controller
php artisan make:controller ShopController --resource --no-interaction

# Step 4: Create Form Requests
php artisan make:request StoreShopRequest --no-interaction
php artisan make:request UpdateShopRequest --no-interaction

# Step 5: Create storage symlink (if not exists)
php artisan storage:link

# Step 6: Run migrations
php artisan migrate

# Step 7: Run seeders
php artisan db:seed --class=ShopSeeder

# Step 8: Create tests
php artisan make:test ShopManagementTest --pest --no-interaction
php artisan make:test Unit/ShopTest --pest --unit --no-interaction
php artisan make:test Unit/UserShopTest --pest --unit --no-interaction

# Step 9: Run tests
php artisan test --compact --filter=Shop

# Step 10: Format code
vendor/bin/pint --dirty
```

---

## 9. Verification Checklist

Before proceeding to Module 04, verify:

- [ ] Shops table migration runs without errors
- [ ] Shop-User pivot table created successfully
- [ ] Shop model with all relationships and methods
- [ ] User model updated with shop relationships
- [ ] ShopController CRUD operations work
- [ ] Shop seeder creates default shops
- [ ] Logo upload and storage works
- [ ] Users can be assigned to shops
- [ ] Shop settings (JSON) work correctly
- [ ] Active scope filters correctly
- [ ] Soft delete works
- [ ] All tests pass (`php artisan test --compact --filter=Shop`)
- [ ] Code formatted with Pint

---

## 10. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 04: Categories](./04-categories.md)**
