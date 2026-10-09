# Module 07: Suppliers
## Stock Taking & Sales Management System

### Module Overview
Implement supplier management for tracking vendors who provide stock to the shops. Suppliers are linked to stock batches to maintain full traceability of product sources.

**Priority:** P1 (High)  
**Dependencies:** Module 03 (Shops Management)  
**Estimated Time:** 1 day

---

## 1. Database Schema

### Suppliers Table

```sql
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');
    $table->string('code')->unique()->nullable(); // Supplier code
    $table->string('contact_person')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->string('alt_phone')->nullable();
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->nullable();
    $table->string('tax_id')->nullable(); // VAT/Tax registration
    $table->text('payment_terms')->nullable();
    $table->integer('lead_time_days')->nullable(); // Average delivery days
    $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
    $table->json('meta')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['shop_id', 'status']);
    $table->index('name');
});
```

### Fields Specification

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint | PK, auto | Primary key |
| shop_id | bigint | FK, nullable | Associated shop (null = all shops) |
| name | string(255) | required | Supplier name |
| code | string(50) | unique, nullable | Supplier code |
| contact_person | string(255) | nullable | Primary contact |
| email | string(255) | nullable | Contact email |
| phone | string(20) | nullable | Primary phone |
| alt_phone | string(20) | nullable | Alternative phone |
| address | text | nullable | Physical address |
| city | string(100) | nullable | City |
| country | string(100) | nullable | Country |
| tax_id | string(50) | nullable | VAT/Tax ID |
| payment_terms | text | nullable | Payment terms |
| lead_time_days | integer | nullable | Avg delivery days |
| status | enum | default: active | Supplier status |
| meta | json | nullable | Additional data |
| notes | text | nullable | Internal notes |

---

## 2. Models & Relationships

### Supplier Model

**File:** `app/Models/Supplier.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'alt_phone',
        'address',
        'city',
        'country',
        'tax_id',
        'payment_terms',
        'lead_time_days',
        'status',
        'meta',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Supplier $supplier) {
            if (empty($supplier->code)) {
                $supplier->code = self::generateCode();
            }
        });
    }

    /**
     * Generate unique supplier code
     */
    public static function generateCode(): string
    {
        $prefix = 'SUP';
        $number = self::withTrashed()->count() + 1;
        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Shop this supplier is associated with
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Stock batches from this supplier (to be added in Module 08)
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Check if supplier is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if supplier is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->country])
            ->filter()
            ->implode(', ');
    }

    /**
     * Get total value of supplies
     */
    public function getTotalSupplyValueAttribute(): float
    {
        return $this->stockBatches()->sum('total_cost');
    }

    /**
     * Get last supply date
     */
    public function getLastSupplyDateAttribute(): ?\Carbon\Carbon
    {
        return $this->stockBatches()->latest('date_received')->value('date_received');
    }

    /**
     * Scope for active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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
     * Search scope
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('contact_person', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
```

---

## 3. Controllers & Routes

### SupplierController

**File:** `app/Http/Controllers/SupplierController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $shopId = $request->get('shop_id');
        $status = $request->get('status');
        $search = $request->get('search');

        $suppliers = Supplier::with('shop')
            ->withCount('stockBatches')
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->search($search))
            ->latest()
            ->paginate(15);

        $shops = Shop::active()->get();

        return view('suppliers.index', compact('suppliers', 'shops', 'shopId', 'status', 'search'));
    }

    public function create(): View
    {
        $shops = Shop::active()->get();

        return view('suppliers.create', compact('shops'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['shop', 'stockBatches' => function ($q) {
            $q->latest('date_received')->limit(10);
        }]);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        $shops = Shop::active()->get();

        return view('suppliers.edit', compact('supplier', 'shops'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->stockBatches()->exists()) {
            return back()->with('error', 'Cannot delete supplier with existing stock records.');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    /**
     * Get suppliers for dropdown (AJAX)
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $term = $request->get('term', '');
        $shopId = $request->get('shop_id');

        $suppliers = Supplier::active()
            ->forShop($shopId)
            ->search($term)
            ->limit(10)
            ->get(['id', 'name', 'code']);

        return response()->json($suppliers);
    }
}
```

### Routes

**File:** `routes/web.php` (add to authenticated routes)

```php
use App\Http\Controllers\SupplierController;

Route::middleware(['auth'])->group(function () {
    // ... existing routes

    // Suppliers Management
    Route::resource('suppliers', SupplierController::class)->middleware('permission:stock.view');
    Route::get('api/suppliers/search', [SupplierController::class, 'search'])
        ->name('suppliers.search')
        ->middleware('permission:stock.view');
});
```

---

## 4. Form Requests

### StoreSupplierRequest

**File:** `app/Http/Requests/StoreSupplierRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('stock.create');
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['nullable', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:suppliers,code'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'alt_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['nullable', 'in:active,inactive,blocked'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a supplier name.',
            'code.unique' => 'This supplier code is already in use.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
```

### UpdateSupplierRequest

**File:** `app/Http/Requests/UpdateSupplierRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('stock.edit');
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['nullable', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')->ignore($this->supplier),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'alt_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['required', 'in:active,inactive,blocked'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

---

## 5. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Supplier List | `design/src/seller-list.php` (adapted) |
| Add Supplier | `design/src/seller-add.php` (adapted) |
| Supplier Details | `design/src/seller-details.php` (adapted) |

### View Structure

```
resources/views/
├── suppliers/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
```

---

## 6. Factory & Seeder

### SupplierFactory

**File:** `database/factories/SupplierFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => null,
            'name' => fake()->company(),
            'code' => 'SUP' . fake()->unique()->numerify('#####'),
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'tax_id' => fake()->numerify('TAX-########'),
            'payment_terms' => fake()->randomElement(['Net 30', 'Net 60', 'COD', 'Prepaid']),
            'lead_time_days' => fake()->numberBetween(1, 30),
            'status' => 'active',
        ];
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
        ]);
    }
}
```

### SupplierSeeder

**File:** `database/seeders/SupplierSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        // Create some default global suppliers
        $suppliers = [
            [
                'name' => 'Main Distributor Co.',
                'contact_person' => 'John Smith',
                'email' => 'orders@maindist.com',
                'phone' => '+254 700 000 001',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 7,
            ],
            [
                'name' => 'Quick Supplies Ltd',
                'contact_person' => 'Jane Doe',
                'email' => 'sales@quicksupplies.com',
                'phone' => '+254 700 000 002',
                'payment_terms' => 'COD',
                'lead_time_days' => 3,
            ],
            [
                'name' => 'Wholesale World',
                'contact_person' => 'Mike Johnson',
                'email' => 'wholesale@world.com',
                'phone' => '+254 700 000 003',
                'payment_terms' => 'Net 60',
                'lead_time_days' => 14,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
```

---

## 7. Tests (Pest)

**File:** `tests/Feature/SupplierManagementTest.php`

```php
<?php

use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('suppliers list page can be rendered', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get(route('suppliers.index'));

    $response->assertStatus(200);
});

test('authorized user can create a supplier', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->post(route('suppliers.store'), [
        'name' => 'Test Supplier',
        'email' => 'test@supplier.com',
        'phone' => '+254 700 000 000',
    ]);

    $response->assertRedirect(route('suppliers.index'));
    $this->assertDatabaseHas('suppliers', ['name' => 'Test Supplier']);
});

test('supplier code is auto-generated', function () {
    $supplier = Supplier::factory()->create(['code' => null]);

    expect($supplier->code)->toStartWith('SUP');
});

test('supplier can be searched', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    Supplier::factory()->create(['name' => 'Unique Supplier Name']);
    Supplier::factory()->count(5)->create();

    $response = $this->actingAs($user)->get(route('suppliers.index', ['search' => 'Unique']));

    $response->assertStatus(200);
});

test('supplier with stock batches cannot be deleted', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $supplier = Supplier::factory()->create();
    // Note: Stock batch relationship will be tested in Module 08

    $response = $this->actingAs($user)->delete(route('suppliers.destroy', $supplier));

    // Without stock batches, should succeed
    $response->assertRedirect(route('suppliers.index'));
});

test('suppliers can be filtered by shop', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $shop = Shop::factory()->create();
    Supplier::factory()->count(3)->forShop($shop)->create();
    Supplier::factory()->count(2)->create(); // Global suppliers

    $response = $this->actingAs($user)->get(route('suppliers.index', ['shop_id' => $shop->id]));

    $response->assertStatus(200);
});
```

**File:** `tests/Unit/SupplierTest.php`

```php
<?php

use App\Models\Supplier;

test('supplier full address is formatted correctly', function () {
    $supplier = Supplier::factory()->create([
        'address' => '123 Main St',
        'city' => 'Nairobi',
        'country' => 'Kenya',
    ]);

    expect($supplier->full_address)->toBe('123 Main St, Nairobi, Kenya');
});

test('supplier status checks work correctly', function () {
    $active = Supplier::factory()->create(['status' => 'active']);
    $blocked = Supplier::factory()->blocked()->create();

    expect($active->isActive())->toBeTrue();
    expect($active->isBlocked())->toBeFalse();
    expect($blocked->isBlocked())->toBeTrue();
});

test('supplier code generation is unique', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    expect($supplier1->code)->not->toBe($supplier2->code);
});
```

---

## 8. Commands to Execute

```bash
# Step 1: Create Model with migration, factory, seeder
php artisan make:model Supplier -mfs --no-interaction

# Step 2: Create Controller
php artisan make:controller SupplierController --resource --no-interaction

# Step 3: Create Form Requests
php artisan make:request StoreSupplierRequest --no-interaction
php artisan make:request UpdateSupplierRequest --no-interaction

# Step 4: Run migrations
php artisan migrate

# Step 5: Run seeder
php artisan db:seed --class=SupplierSeeder

# Step 6: Create tests
php artisan make:test SupplierManagementTest --pest --no-interaction
php artisan make:test Unit/SupplierTest --pest --unit --no-interaction

# Step 7: Run tests
php artisan test --compact --filter=Supplier

# Step 8: Format code
vendor/bin/pint --dirty
```

---

## 9. Verification Checklist

Before proceeding to Module 08, verify:

- [ ] Suppliers table migration runs without errors
- [ ] Supplier model with all relationships and methods
- [ ] Supplier code auto-generation works
- [ ] SupplierController CRUD operations work
- [ ] Supplier seeder creates sample data
- [ ] Suppliers can be filtered by shop
- [ ] Supplier search works correctly
- [ ] Full address formatting works
- [ ] Status checks work correctly
- [ ] All tests pass (`php artisan test --compact --filter=Supplier`)
- [ ] Code formatted with Pint

---

## 10. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 08: Stock Intake](./08-stock-intake.md)**
