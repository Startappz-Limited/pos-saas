# Module 13: Expense Categories
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Hierarchical expense category system enabling organized expense tracking, budgeting, and financial reporting. Supports multi-level categorization with parent-child relationships, budget limits per category, and expense type classification.

**Priority:** P1 (High)  
**Dependencies:** Module 03 (Shops)  
**Estimated Time:** 1 day

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns, Enum status |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Auditable trait on models |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Expense Categories Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Hierarchy
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->integer('depth')->default(0);
            $table->string('path')->nullable(); // Materialized path for fast hierarchy queries
            
            // Basic info
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique(); // Short code like EXP-OPS-001
            $table->text('description')->nullable();
            
            // Classification
            $table->string('type'); // ExpenseCategoryType enum
            $table->boolean('is_operational')->default(true);
            $table->boolean('is_tax_deductible')->default(false);
            
            // Budget settings
            $table->decimal('monthly_budget', 15, 2)->nullable();
            $table->decimal('yearly_budget', 15, 2)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->decimal('approval_threshold', 15, 2)->nullable(); // Require approval above this amount
            
            // Display
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            
            // Status
            $table->string('status'); // CommonStatus enum
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('uuid');
            $table->index('slug');
            $table->index('code');
            $table->index('parent_id');
            $table->index('type');
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
```

### Shop Expense Category Settings Table (Per-shop overrides)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_expense_category_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            
            // Shop-specific overrides
            $table->boolean('is_enabled')->default(true);
            $table->decimal('monthly_budget', 15, 2)->nullable();
            $table->decimal('yearly_budget', 15, 2)->nullable();
            $table->boolean('requires_approval')->nullable();
            $table->decimal('approval_threshold', 15, 2)->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->unique(['shop_id', 'expense_category_id']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_expense_category_settings');
    }
};
```

---

## 3. Enums

### ExpenseCategoryType Enum

**File:** `app/Enums/ExpenseCategoryType.php`

```php
<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case OPERATIONAL = 'operational';
    case ADMINISTRATIVE = 'administrative';
    case MARKETING = 'marketing';
    case PAYROLL = 'payroll';
    case UTILITIES = 'utilities';
    case RENT = 'rent';
    case SUPPLIES = 'supplies';
    case MAINTENANCE = 'maintenance';
    case TRANSPORT = 'transport';
    case INSURANCE = 'insurance';
    case TAXES = 'taxes';
    case MISCELLANEOUS = 'miscellaneous';

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'Operational',
            self::ADMINISTRATIVE => 'Administrative',
            self::MARKETING => 'Marketing & Advertising',
            self::PAYROLL => 'Payroll & Benefits',
            self::UTILITIES => 'Utilities',
            self::RENT => 'Rent & Lease',
            self::SUPPLIES => 'Supplies',
            self::MAINTENANCE => 'Maintenance & Repairs',
            self::TRANSPORT => 'Transport & Logistics',
            self::INSURANCE => 'Insurance',
            self::TAXES => 'Taxes & Licenses',
            self::MISCELLANEOUS => 'Miscellaneous',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'ri-settings-3-line',
            self::ADMINISTRATIVE => 'ri-briefcase-line',
            self::MARKETING => 'ri-megaphone-line',
            self::PAYROLL => 'ri-team-line',
            self::UTILITIES => 'ri-lightbulb-line',
            self::RENT => 'ri-building-line',
            self::SUPPLIES => 'ri-archive-line',
            self::MAINTENANCE => 'ri-tools-line',
            self::TRANSPORT => 'ri-truck-line',
            self::INSURANCE => 'ri-shield-check-line',
            self::TAXES => 'ri-government-line',
            self::MISCELLANEOUS => 'ri-more-2-line',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'primary',
            self::ADMINISTRATIVE => 'secondary',
            self::MARKETING => 'info',
            self::PAYROLL => 'warning',
            self::UTILITIES => 'success',
            self::RENT => 'dark',
            self::SUPPLIES => 'light',
            self::MAINTENANCE => 'danger',
            self::TRANSPORT => 'primary',
            self::INSURANCE => 'secondary',
            self::TAXES => 'warning',
            self::MISCELLANEOUS => 'dark',
        };
    }

    public function isTaxDeductible(): bool
    {
        return in_array($this, [
            self::OPERATIONAL,
            self::PAYROLL,
            self::UTILITIES,
            self::RENT,
            self::SUPPLIES,
            self::MAINTENANCE,
            self::TRANSPORT,
            self::INSURANCE,
        ]);
    }
}
```

---

## 4. Models

### ExpenseCategory Model

**File:** `app/Models/ExpenseCategory.php`

```php
<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Enums\ExpenseCategoryType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'uuid',
        'parent_id',
        'depth',
        'path',
        'name',
        'slug',
        'code',
        'description',
        'type',
        'is_operational',
        'is_tax_deductible',
        'monthly_budget',
        'yearly_budget',
        'requires_approval',
        'approval_threshold',
        'icon',
        'color',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExpenseCategoryType::class,
            'status' => CommonStatus::class,
            'is_operational' => 'boolean',
            'is_tax_deductible' => 'boolean',
            'requires_approval' => 'boolean',
            'monthly_budget' => 'decimal:2',
            'yearly_budget' => 'decimal:2',
            'approval_threshold' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ExpenseCategory $category) {
            if (empty($category->uuid)) {
                $category->uuid = (string) Str::uuid();
            }
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
            if (empty($category->code)) {
                $category->code = self::generateCode($category);
            }
            if (empty($category->status)) {
                $category->status = CommonStatus::ACTIVE;
            }
            
            // Set depth and path based on parent
            if ($category->parent_id) {
                $parent = self::find($category->parent_id);
                $category->depth = $parent ? $parent->depth + 1 : 0;
                $category->path = $parent 
                    ? trim($parent->path . '/' . $parent->id, '/')
                    : null;
            }
            
            $category->created_by = auth()->id();
        });

        static::updating(function (ExpenseCategory $category) {
            $category->updated_by = auth()->id();
        });

        static::deleting(function (ExpenseCategory $category) {
            // Move children to parent or root
            $category->children()->update(['parent_id' => $category->parent_id]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateCode(ExpenseCategory $category): string
    {
        $typePrefix = match ($category->type) {
            ExpenseCategoryType::OPERATIONAL => 'OPS',
            ExpenseCategoryType::ADMINISTRATIVE => 'ADM',
            ExpenseCategoryType::MARKETING => 'MKT',
            ExpenseCategoryType::PAYROLL => 'PAY',
            ExpenseCategoryType::UTILITIES => 'UTL',
            ExpenseCategoryType::RENT => 'RNT',
            ExpenseCategoryType::SUPPLIES => 'SUP',
            ExpenseCategoryType::MAINTENANCE => 'MNT',
            ExpenseCategoryType::TRANSPORT => 'TRN',
            ExpenseCategoryType::INSURANCE => 'INS',
            ExpenseCategoryType::TAXES => 'TAX',
            ExpenseCategoryType::MISCELLANEOUS => 'MSC',
            default => 'EXP',
        };

        $sequence = self::where('type', $category->type)->count() + 1;
        
        return "EXP-{$typePrefix}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function shopSettings(): HasMany
    {
        return $this->hasMany(ShopExpenseCategorySetting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Methods

    public function getAncestors(): \Illuminate\Database\Eloquent\Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors->reverse()->values();
    }

    public function getAllDescendantIds(): array
    {
        $ids = [];
        
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    public function getBreadcrumb(): string
    {
        $ancestors = $this->getAncestors();
        $names = $ancestors->pluck('name')->push($this->name);
        
        return $names->implode(' > ');
    }

    public function getEffectiveMonthlyBudget(?Shop $shop = null): ?float
    {
        if ($shop) {
            $setting = $this->shopSettings()->where('shop_id', $shop->id)->first();
            if ($setting && $setting->monthly_budget !== null) {
                return $setting->monthly_budget;
            }
        }

        return $this->monthly_budget;
    }

    public function getEffectiveApprovalThreshold(?Shop $shop = null): ?float
    {
        if ($shop) {
            $setting = $this->shopSettings()->where('shop_id', $shop->id)->first();
            if ($setting && $setting->approval_threshold !== null) {
                return $setting->approval_threshold;
            }
        }

        return $this->approval_threshold;
    }

    public function requiresApprovalFor(float $amount, ?Shop $shop = null): bool
    {
        if ($shop) {
            $setting = $this->shopSettings()->where('shop_id', $shop->id)->first();
            if ($setting && $setting->requires_approval !== null) {
                if (!$setting->requires_approval) {
                    return false;
                }
                $threshold = $setting->approval_threshold ?? $this->approval_threshold;
                return $threshold !== null && $amount >= $threshold;
            }
        }

        if (!$this->requires_approval) {
            return false;
        }

        return $this->approval_threshold !== null && $amount >= $this->approval_threshold;
    }

    public function isEnabledForShop(Shop $shop): bool
    {
        $setting = $this->shopSettings()->where('shop_id', $shop->id)->first();
        return $setting ? $setting->is_enabled : true;
    }

    public function getMonthlySpend(?Shop $shop = null, ?\Carbon\Carbon $month = null): float
    {
        $month = $month ?? now();

        return $this->expenses()
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereYear('expense_date', $month->year)
            ->whereMonth('expense_date', $month->month)
            ->approved()
            ->sum('amount');
    }

    public function getBudgetUtilization(?Shop $shop = null, ?\Carbon\Carbon $month = null): ?float
    {
        $budget = $this->getEffectiveMonthlyBudget($shop);
        
        if (!$budget || $budget <= 0) {
            return null;
        }

        $spend = $this->getMonthlySpend($shop, $month);
        
        return round(($spend / $budget) * 100, 2);
    }

    public function isOverBudget(?Shop $shop = null, ?\Carbon\Carbon $month = null): bool
    {
        $utilization = $this->getBudgetUtilization($shop, $month);
        return $utilization !== null && $utilization > 100;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', CommonStatus::ACTIVE);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType($query, ExpenseCategoryType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeTaxDeductible($query)
    {
        return $query->where('is_tax_deductible', true);
    }

    public function scopeRequiringApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    public function scopeWithBudget($query)
    {
        return $query->whereNotNull('monthly_budget');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeEnabledForShop($query, Shop $shop)
    {
        return $query->whereDoesntHave('shopSettings', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id)->where('is_enabled', false);
        });
    }
}
```

### ShopExpenseCategorySetting Model

**File:** `app/Models/ShopExpenseCategorySetting.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShopExpenseCategorySetting extends Model
{
    protected $fillable = [
        'uuid',
        'shop_id',
        'expense_category_id',
        'is_enabled',
        'monthly_budget',
        'yearly_budget',
        'requires_approval',
        'approval_threshold',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'requires_approval' => 'boolean',
            'monthly_budget' => 'decimal:2',
            'yearly_budget' => 'decimal:2',
            'approval_threshold' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $setting) {
            if (empty($setting->uuid)) {
                $setting->uuid = (string) Str::uuid();
            }
            $setting->created_by = auth()->id();
        });

        static::updating(function (self $setting) {
            $setting->updated_by = auth()->id();
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

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
```

---

## 5. Actions

### CreateExpenseCategoryAction

**File:** `app/Actions/ExpenseCategories/CreateExpenseCategoryAction.php`

```php
<?php

namespace App\Actions\ExpenseCategories;

use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

class CreateExpenseCategoryAction
{
    public function execute(
        string $name,
        ExpenseCategoryType $type,
        ?int $parentId = null,
        ?string $description = null,
        ?float $monthlyBudget = null,
        bool $requiresApproval = false,
        ?float $approvalThreshold = null,
        ?string $icon = null,
        ?string $color = null
    ): ExpenseCategory {
        return DB::transaction(function () use (
            $name, $type, $parentId, $description, $monthlyBudget,
            $requiresApproval, $approvalThreshold, $icon, $color
        ) {
            $sortOrder = ExpenseCategory::where('parent_id', $parentId)->max('sort_order') + 1;

            return ExpenseCategory::create([
                'name' => $name,
                'parent_id' => $parentId,
                'type' => $type,
                'description' => $description,
                'monthly_budget' => $monthlyBudget,
                'yearly_budget' => $monthlyBudget ? $monthlyBudget * 12 : null,
                'requires_approval' => $requiresApproval,
                'approval_threshold' => $approvalThreshold,
                'is_tax_deductible' => $type->isTaxDeductible(),
                'icon' => $icon ?? $type->icon(),
                'color' => $color ?? $type->color(),
                'sort_order' => $sortOrder,
            ]);
        });
    }
}
```

### UpdateExpenseCategoryAction

**File:** `app/Actions/ExpenseCategories/UpdateExpenseCategoryAction.php`

```php
<?php

namespace App\Actions\ExpenseCategories;

use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateExpenseCategoryAction
{
    public function execute(
        ExpenseCategory $category,
        array $data
    ): ExpenseCategory {
        return DB::transaction(function () use ($category, $data) {
            // Update slug if name changed
            if (isset($data['name']) && $data['name'] !== $category->name) {
                $data['slug'] = Str::slug($data['name']);
            }

            // Update yearly budget if monthly changes
            if (isset($data['monthly_budget'])) {
                $data['yearly_budget'] = $data['monthly_budget'] * 12;
            }

            // Update tax deductible based on type
            if (isset($data['type'])) {
                $type = $data['type'] instanceof ExpenseCategoryType 
                    ? $data['type'] 
                    : ExpenseCategoryType::from($data['type']);
                $data['is_tax_deductible'] = $type->isTaxDeductible();
            }

            $category->update($data);

            return $category->refresh();
        });
    }
}
```

### ReorderExpenseCategoriesAction

**File:** `app/Actions/ExpenseCategories/ReorderExpenseCategoriesAction.php`

```php
<?php

namespace App\Actions\ExpenseCategories;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

class ReorderExpenseCategoriesAction
{
    /**
     * @param array<int, int> $order [category_id => sort_order]
     */
    public function execute(array $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order as $categoryId => $sortOrder) {
                ExpenseCategory::where('id', $categoryId)->update(['sort_order' => $sortOrder]);
            }
        });
    }
}
```

### ConfigureShopCategoryAction

**File:** `app/Actions/ExpenseCategories/ConfigureShopCategoryAction.php`

```php
<?php

namespace App\Actions\ExpenseCategories;

use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\ShopExpenseCategorySetting;
use Illuminate\Support\Facades\DB;

class ConfigureShopCategoryAction
{
    public function execute(
        Shop $shop,
        ExpenseCategory $category,
        array $settings
    ): ShopExpenseCategorySetting {
        return DB::transaction(function () use ($shop, $category, $settings) {
            return ShopExpenseCategorySetting::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'expense_category_id' => $category->id,
                ],
                $settings
            );
        });
    }
}
```

---

## 6. Services

### ExpenseCategoryService

**File:** `app/Services/ExpenseCategoryService.php`

```php
<?php

namespace App\Services;

use App\Actions\ExpenseCategories\ConfigureShopCategoryAction;
use App\Actions\ExpenseCategories\CreateExpenseCategoryAction;
use App\Actions\ExpenseCategories\ReorderExpenseCategoriesAction;
use App\Actions\ExpenseCategories\UpdateExpenseCategoryAction;
use App\Enums\CommonStatus;
use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\ShopExpenseCategorySetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExpenseCategoryService
{
    public function __construct(
        private CreateExpenseCategoryAction $createCategory,
        private UpdateExpenseCategoryAction $updateCategory,
        private ReorderExpenseCategoriesAction $reorderCategories,
        private ConfigureShopCategoryAction $configureShopCategory
    ) {}

    /**
     * Create a new expense category
     */
    public function create(
        string $name,
        ExpenseCategoryType $type,
        ?int $parentId = null,
        ?string $description = null,
        ?float $monthlyBudget = null,
        bool $requiresApproval = false,
        ?float $approvalThreshold = null
    ): ExpenseCategory {
        return $this->createCategory->execute(
            $name,
            $type,
            $parentId,
            $description,
            $monthlyBudget,
            $requiresApproval,
            $approvalThreshold
        );
    }

    /**
     * Update expense category
     */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        return $this->updateCategory->execute($category, $data);
    }

    /**
     * Reorder categories
     */
    public function reorder(array $order): void
    {
        $this->reorderCategories->execute($order);
    }

    /**
     * Configure category for specific shop
     */
    public function configureForShop(
        Shop $shop,
        ExpenseCategory $category,
        array $settings
    ): ShopExpenseCategorySetting {
        return $this->configureShopCategory->execute($shop, $category, $settings);
    }

    /**
     * Enable/disable category for shop
     */
    public function toggleForShop(Shop $shop, ExpenseCategory $category, bool $enabled): void
    {
        $this->configureForShop($shop, $category, ['is_enabled' => $enabled]);
    }

    /**
     * Get category tree (hierarchical)
     */
    public function getCategoryTree(?Shop $shop = null): Collection
    {
        $query = ExpenseCategory::with('allChildren')
            ->root()
            ->active()
            ->ordered();

        if ($shop) {
            $query->enabledForShop($shop);
        }

        return $query->get();
    }

    /**
     * Get flat list of categories
     */
    public function getFlatList(?ExpenseCategoryType $type = null, ?Shop $shop = null): Collection
    {
        $query = ExpenseCategory::active()
            ->ordered()
            ->when($type, fn ($q) => $q->ofType($type))
            ->when($shop, fn ($q) => $q->enabledForShop($shop));

        return $query->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'full_name' => $category->getBreadcrumb(),
                'type' => $category->type,
                'depth' => $category->depth,
                'parent_id' => $category->parent_id,
            ];
        });
    }

    /**
     * Get categories for dropdown
     */
    public function getForDropdown(?Shop $shop = null): Collection
    {
        return ExpenseCategory::active()
            ->ordered()
            ->when($shop, fn ($q) => $q->enabledForShop($shop))
            ->get()
            ->map(fn ($c) => [
                'value' => $c->id,
                'label' => str_repeat('— ', $c->depth) . $c->name,
                'uuid' => $c->uuid,
            ]);
    }

    /**
     * Get categories by type
     */
    public function getByType(ExpenseCategoryType $type): Collection
    {
        return ExpenseCategory::active()
            ->ofType($type)
            ->ordered()
            ->get();
    }

    /**
     * Get budget summary
     */
    public function getBudgetSummary(?Shop $shop = null, ?Carbon $month = null): array
    {
        $month = $month ?? now();
        $categories = ExpenseCategory::active()
            ->withBudget()
            ->when($shop, fn ($q) => $q->enabledForShop($shop))
            ->get();

        $summary = [
            'total_budget' => 0,
            'total_spent' => 0,
            'categories' => [],
            'over_budget' => [],
            'near_budget' => [],
        ];

        foreach ($categories as $category) {
            $budget = $category->getEffectiveMonthlyBudget($shop);
            $spent = $category->getMonthlySpend($shop, $month);
            $utilization = $budget > 0 ? round(($spent / $budget) * 100, 2) : 0;

            $summary['total_budget'] += $budget;
            $summary['total_spent'] += $spent;

            $categoryData = [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'budget' => $budget,
                'spent' => $spent,
                'remaining' => $budget - $spent,
                'utilization' => $utilization,
            ];

            $summary['categories'][] = $categoryData;

            if ($utilization > 100) {
                $summary['over_budget'][] = $categoryData;
            } elseif ($utilization >= 80) {
                $summary['near_budget'][] = $categoryData;
            }
        }

        $summary['total_remaining'] = $summary['total_budget'] - $summary['total_spent'];
        $summary['total_utilization'] = $summary['total_budget'] > 0
            ? round(($summary['total_spent'] / $summary['total_budget']) * 100, 2)
            : 0;

        return $summary;
    }

    /**
     * Get spending by category type
     */
    public function getSpendingByType(?Shop $shop = null, ?Carbon $month = null): Collection
    {
        $month = $month ?? now();

        return collect(ExpenseCategoryType::cases())->map(function ($type) use ($shop, $month) {
            $categories = ExpenseCategory::active()
                ->ofType($type)
                ->when($shop, fn ($q) => $q->enabledForShop($shop))
                ->get();

            $totalSpent = $categories->sum(fn ($c) => $c->getMonthlySpend($shop, $month));
            $totalBudget = $categories->sum(fn ($c) => $c->getEffectiveMonthlyBudget($shop) ?? 0);

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
                'color' => $type->color(),
                'spent' => $totalSpent,
                'budget' => $totalBudget,
                'category_count' => $categories->count(),
            ];
        })->filter(fn ($item) => $item['spent'] > 0 || $item['budget'] > 0);
    }

    /**
     * Seed default categories
     */
    public function seedDefaults(): void
    {
        $defaults = [
            [
                'name' => 'Utilities',
                'type' => ExpenseCategoryType::UTILITIES,
                'children' => [
                    ['name' => 'Electricity', 'type' => ExpenseCategoryType::UTILITIES],
                    ['name' => 'Water', 'type' => ExpenseCategoryType::UTILITIES],
                    ['name' => 'Internet', 'type' => ExpenseCategoryType::UTILITIES],
                    ['name' => 'Phone', 'type' => ExpenseCategoryType::UTILITIES],
                ],
            ],
            [
                'name' => 'Rent & Lease',
                'type' => ExpenseCategoryType::RENT,
                'children' => [
                    ['name' => 'Shop Rent', 'type' => ExpenseCategoryType::RENT],
                    ['name' => 'Warehouse Rent', 'type' => ExpenseCategoryType::RENT],
                    ['name' => 'Equipment Lease', 'type' => ExpenseCategoryType::RENT],
                ],
            ],
            [
                'name' => 'Payroll',
                'type' => ExpenseCategoryType::PAYROLL,
                'requires_approval' => true,
                'approval_threshold' => 10000,
                'children' => [
                    ['name' => 'Salaries', 'type' => ExpenseCategoryType::PAYROLL],
                    ['name' => 'Wages', 'type' => ExpenseCategoryType::PAYROLL],
                    ['name' => 'Bonuses', 'type' => ExpenseCategoryType::PAYROLL],
                    ['name' => 'Benefits', 'type' => ExpenseCategoryType::PAYROLL],
                ],
            ],
            [
                'name' => 'Marketing',
                'type' => ExpenseCategoryType::MARKETING,
                'children' => [
                    ['name' => 'Digital Advertising', 'type' => ExpenseCategoryType::MARKETING],
                    ['name' => 'Print Advertising', 'type' => ExpenseCategoryType::MARKETING],
                    ['name' => 'Promotions', 'type' => ExpenseCategoryType::MARKETING],
                    ['name' => 'Signage', 'type' => ExpenseCategoryType::MARKETING],
                ],
            ],
            [
                'name' => 'Supplies',
                'type' => ExpenseCategoryType::SUPPLIES,
                'children' => [
                    ['name' => 'Office Supplies', 'type' => ExpenseCategoryType::SUPPLIES],
                    ['name' => 'Packaging', 'type' => ExpenseCategoryType::SUPPLIES],
                    ['name' => 'Cleaning Supplies', 'type' => ExpenseCategoryType::SUPPLIES],
                ],
            ],
            [
                'name' => 'Maintenance',
                'type' => ExpenseCategoryType::MAINTENANCE,
                'children' => [
                    ['name' => 'Equipment Repairs', 'type' => ExpenseCategoryType::MAINTENANCE],
                    ['name' => 'Building Maintenance', 'type' => ExpenseCategoryType::MAINTENANCE],
                    ['name' => 'Vehicle Maintenance', 'type' => ExpenseCategoryType::MAINTENANCE],
                ],
            ],
            [
                'name' => 'Transport',
                'type' => ExpenseCategoryType::TRANSPORT,
                'children' => [
                    ['name' => 'Fuel', 'type' => ExpenseCategoryType::TRANSPORT],
                    ['name' => 'Delivery Costs', 'type' => ExpenseCategoryType::TRANSPORT],
                    ['name' => 'Staff Transport', 'type' => ExpenseCategoryType::TRANSPORT],
                ],
            ],
            [
                'name' => 'Insurance',
                'type' => ExpenseCategoryType::INSURANCE,
                'children' => [
                    ['name' => 'Property Insurance', 'type' => ExpenseCategoryType::INSURANCE],
                    ['name' => 'Vehicle Insurance', 'type' => ExpenseCategoryType::INSURANCE],
                    ['name' => 'Liability Insurance', 'type' => ExpenseCategoryType::INSURANCE],
                ],
            ],
            [
                'name' => 'Taxes & Licenses',
                'type' => ExpenseCategoryType::TAXES,
                'children' => [
                    ['name' => 'Business License', 'type' => ExpenseCategoryType::TAXES],
                    ['name' => 'Property Tax', 'type' => ExpenseCategoryType::TAXES],
                    ['name' => 'Permits', 'type' => ExpenseCategoryType::TAXES],
                ],
            ],
            [
                'name' => 'Miscellaneous',
                'type' => ExpenseCategoryType::MISCELLANEOUS,
                'children' => [
                    ['name' => 'Bank Charges', 'type' => ExpenseCategoryType::MISCELLANEOUS],
                    ['name' => 'Professional Fees', 'type' => ExpenseCategoryType::MISCELLANEOUS],
                    ['name' => 'Other Expenses', 'type' => ExpenseCategoryType::MISCELLANEOUS],
                ],
            ],
        ];

        foreach ($defaults as $parentData) {
            $children = $parentData['children'] ?? [];
            unset($parentData['children']);

            $parent = $this->create(
                $parentData['name'],
                $parentData['type'],
                null,
                null,
                null,
                $parentData['requires_approval'] ?? false,
                $parentData['approval_threshold'] ?? null
            );

            foreach ($children as $childData) {
                $this->create(
                    $childData['name'],
                    $childData['type'],
                    $parent->id
                );
            }
        }
    }
}
```

---

## 7. Controllers

### ExpenseCategoryController

**File:** `app/Http/Controllers/ExpenseCategoryController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\CommonStatus;
use App\Enums\ExpenseCategoryType;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Http\Requests\ConfigureShopCategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private ExpenseCategoryService $categoryService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $type = $request->get('type');
        $search = $request->get('search');

        $categories = ExpenseCategory::with(['parent', 'children'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->ordered()
            ->paginate(50);

        $categoryTypes = ExpenseCategoryType::cases();
        $budgetSummary = $this->categoryService->getBudgetSummary();

        return view('expense-categories.index', compact('categories', 'categoryTypes', 'budgetSummary'));
    }

    public function tree(): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $tree = $this->categoryService->getCategoryTree();
        $categoryTypes = ExpenseCategoryType::cases();

        return view('expense-categories.tree', compact('tree', 'categoryTypes'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ExpenseCategory::class);

        $parentId = $request->get('parent_id');
        $parent = $parentId ? ExpenseCategory::find($parentId) : null;
        $categories = $this->categoryService->getForDropdown();
        $categoryTypes = ExpenseCategoryType::cases();

        return view('expense-categories.create', compact('parent', 'categories', 'categoryTypes'));
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $category = $this->categoryService->create(
            $request->name,
            ExpenseCategoryType::from($request->type),
            $request->parent_id,
            $request->description,
            $request->monthly_budget,
            $request->boolean('requires_approval'),
            $request->approval_threshold
        );

        return redirect()->route('expense-categories.show', $category->uuid)
            ->with('success', 'Expense category created successfully.');
    }

    public function show(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('view', $expenseCategory);

        $expenseCategory->load(['parent', 'children', 'createdBy', 'updatedBy']);
        $ancestors = $expenseCategory->getAncestors();
        $budgetUtilization = $expenseCategory->getBudgetUtilization();

        return view('expense-categories.show', compact('expenseCategory', 'ancestors', 'budgetUtilization'));
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('update', $expenseCategory);

        $categories = $this->categoryService->getForDropdown()
            ->filter(fn ($c) => $c['value'] !== $expenseCategory->id);
        $categoryTypes = ExpenseCategoryType::cases();

        return view('expense-categories.edit', compact('expenseCategory', 'categories', 'categoryTypes'));
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->categoryService->update($expenseCategory, $request->validated());

        return redirect()->route('expense-categories.show', $expenseCategory->uuid)
            ->with('success', 'Expense category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        // Check for existing expenses
        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', 'Cannot delete category with existing expenses.');
        }

        $expenseCategory->delete();

        return redirect()->route('expense-categories.index')
            ->with('success', 'Expense category deleted successfully.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('reorder', ExpenseCategory::class);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:expense_categories,id',
        ]);

        $order = [];
        foreach ($request->order as $index => $categoryId) {
            $order[$categoryId] = $index;
        }

        $this->categoryService->reorder($order);

        return back()->with('success', 'Categories reordered successfully.');
    }

    public function shopSettings(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('configureShop', $expenseCategory);

        $shops = Shop::active()->with(['expenseCategorySettings' => function ($q) use ($expenseCategory) {
            $q->where('expense_category_id', $expenseCategory->id);
        }])->get();

        return view('expense-categories.shop-settings', compact('expenseCategory', 'shops'));
    }

    public function updateShopSettings(
        ConfigureShopCategoryRequest $request,
        ExpenseCategory $expenseCategory,
        Shop $shop
    ): RedirectResponse {
        $this->authorize('configureShop', $expenseCategory);

        $this->categoryService->configureForShop($shop, $expenseCategory, $request->validated());

        return back()->with('success', 'Shop settings updated successfully.');
    }

    public function budgetReport(Request $request): View
    {
        $this->authorize('viewBudget', ExpenseCategory::class);

        $shopId = $request->get('shop_id');
        $month = $request->get('month') ? \Carbon\Carbon::parse($request->get('month')) : now();
        $shop = $shopId ? Shop::find($shopId) : null;

        $budgetSummary = $this->categoryService->getBudgetSummary($shop, $month);
        $spendingByType = $this->categoryService->getSpendingByType($shop, $month);
        $shops = Shop::active()->get();

        return view('expense-categories.budget-report', compact('budgetSummary', 'spendingByType', 'shops', 'month'));
    }
}
```

---

## 8. Form Requests

### StoreExpenseCategoryRequest

**File:** `app/Http/Requests/StoreExpenseCategoryRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ExpenseCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(ExpenseCategoryType::class)],
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a category name.',
            'type.required' => 'Please select a category type.',
        ];
    }
}
```

### UpdateExpenseCategoryRequest

**File:** `app/Http/Requests/UpdateExpenseCategoryRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\CommonStatus;
use App\Enums\ExpenseCategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('expenseCategory'));
    }

    public function rules(): array
    {
        $category = $this->route('expenseCategory');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'type' => ['sometimes', 'required', Rule::enum(ExpenseCategoryType::class)],
            'parent_id' => [
                'nullable',
                'exists:expense_categories,id',
                Rule::notIn([$category->id, ...$category->getAllDescendantIds()]),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::enum(CommonStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'A category cannot be its own parent or a child of its descendants.',
        ];
    }
}
```

### ConfigureShopCategoryRequest

**File:** `app/Http/Requests/ConfigureShopCategoryRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureShopCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configureShop', $this->route('expenseCategory'));
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['boolean'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'yearly_budget' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\ExpenseCategoryController;

Route::middleware(['auth'])->prefix('expense-categories')->name('expense-categories.')->group(function () {
    Route::get('/', [ExpenseCategoryController::class, 'index'])
        ->name('index')
        ->middleware('permission:expense-categories.view');
    
    Route::get('/tree', [ExpenseCategoryController::class, 'tree'])
        ->name('tree')
        ->middleware('permission:expense-categories.view');
    
    Route::get('/budget-report', [ExpenseCategoryController::class, 'budgetReport'])
        ->name('budget-report')
        ->middleware('permission:expense-categories.budget.view');
    
    Route::get('/create', [ExpenseCategoryController::class, 'create'])
        ->name('create')
        ->middleware('permission:expense-categories.create');
    
    Route::post('/', [ExpenseCategoryController::class, 'store'])
        ->name('store')
        ->middleware('permission:expense-categories.create');
    
    Route::post('/reorder', [ExpenseCategoryController::class, 'reorder'])
        ->name('reorder')
        ->middleware('permission:expense-categories.reorder');
    
    Route::get('/{expenseCategory}', [ExpenseCategoryController::class, 'show'])
        ->name('show')
        ->middleware('permission:expense-categories.view');
    
    Route::get('/{expenseCategory}/edit', [ExpenseCategoryController::class, 'edit'])
        ->name('edit')
        ->middleware('permission:expense-categories.update');
    
    Route::put('/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
        ->name('update')
        ->middleware('permission:expense-categories.update');
    
    Route::delete('/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:expense-categories.delete');
    
    Route::get('/{expenseCategory}/shop-settings', [ExpenseCategoryController::class, 'shopSettings'])
        ->name('shop-settings')
        ->middleware('permission:expense-categories.configure-shop');
    
    Route::put('/{expenseCategory}/shop/{shop}', [ExpenseCategoryController::class, 'updateShopSettings'])
        ->name('update-shop-settings')
        ->middleware('permission:expense-categories.configure-shop');
});
```

---

## 10. Permissions

```php
// Expense category permissions
'expense-categories.view',
'expense-categories.create',
'expense-categories.update',
'expense-categories.delete',
'expense-categories.reorder',
'expense-categories.configure-shop',
'expense-categories.budget.view',
```

---

## 11. Seeders

### ExpenseCategorySeeder

**File:** `database/seeders/ExpenseCategorySeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Services\ExpenseCategoryService;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ExpenseCategoryService::class);
        $service->seedDefaults();
    }
}
```

---

## 12. UI Template Reference

| View | Template Source |
|------|-----------------|
| Categories List | `design/src/category-list.php` |
| Category Tree | `design/src/category-list.php` |
| Create/Edit Form | `design/src/category-add.php` |
| Category Details | `design/src/category-details.php` |
| Budget Report | `design/src/reports-sales.php` |

---

## 13. Tests

**File:** `tests/Feature/ExpenseCategoriesTest.php`

```php
<?php

use App\Enums\CommonStatus;
use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\ShopExpenseCategorySetting;
use App\Models\User;
use App\Services\ExpenseCategoryService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('expense category can be created', function () {
    $service = app(ExpenseCategoryService::class);
    
    $category = $service->create(
        'Office Supplies',
        ExpenseCategoryType::SUPPLIES,
        null,
        'General office supplies',
        5000
    );

    expect($category)
        ->name->toBe('Office Supplies')
        ->type->toBe(ExpenseCategoryType::SUPPLIES)
        ->monthly_budget->toBe(5000.00)
        ->status->toBe(CommonStatus::ACTIVE);
});

test('expense category code is auto-generated', function () {
    $category = ExpenseCategory::factory()->create([
        'type' => ExpenseCategoryType::UTILITIES,
    ]);

    expect($category->code)->toStartWith('EXP-UTL-');
});

test('expense category slug is auto-generated', function () {
    $category = ExpenseCategory::factory()->create([
        'name' => 'Office Supplies & Equipment',
    ]);

    expect($category->slug)->toBe('office-supplies-equipment');
});

test('child category inherits depth from parent', function () {
    $parent = ExpenseCategory::factory()->create();
    
    $child = ExpenseCategory::factory()->create([
        'parent_id' => $parent->id,
    ]);

    expect($child->depth)->toBe(1);
    
    $grandchild = ExpenseCategory::factory()->create([
        'parent_id' => $child->id,
    ]);

    expect($grandchild->depth)->toBe(2);
});

test('category breadcrumb shows full path', function () {
    $parent = ExpenseCategory::factory()->create(['name' => 'Utilities']);
    $child = ExpenseCategory::factory()->create([
        'name' => 'Electricity',
        'parent_id' => $parent->id,
    ]);

    expect($child->getBreadcrumb())->toBe('Utilities > Electricity');
});

test('category can get all descendant ids', function () {
    $parent = ExpenseCategory::factory()->create();
    $child1 = ExpenseCategory::factory()->create(['parent_id' => $parent->id]);
    $child2 = ExpenseCategory::factory()->create(['parent_id' => $parent->id]);
    $grandchild = ExpenseCategory::factory()->create(['parent_id' => $child1->id]);

    $descendantIds = $parent->getAllDescendantIds();

    expect($descendantIds)
        ->toContain($child1->id)
        ->toContain($child2->id)
        ->toContain($grandchild->id)
        ->toHaveCount(3);
});

test('shop-specific budget overrides category budget', function () {
    $shop = Shop::factory()->create();
    $category = ExpenseCategory::factory()->create(['monthly_budget' => 5000]);

    ShopExpenseCategorySetting::create([
        'shop_id' => $shop->id,
        'expense_category_id' => $category->id,
        'monthly_budget' => 10000,
    ]);

    expect($category->getEffectiveMonthlyBudget($shop))->toBe(10000.00);
    expect($category->getEffectiveMonthlyBudget())->toBe(5000.00);
});

test('category can be disabled for specific shop', function () {
    $shop = Shop::factory()->create();
    $category = ExpenseCategory::factory()->create();

    expect($category->isEnabledForShop($shop))->toBeTrue();

    ShopExpenseCategorySetting::create([
        'shop_id' => $shop->id,
        'expense_category_id' => $category->id,
        'is_enabled' => false,
    ]);

    expect($category->isEnabledForShop($shop))->toBeFalse();
});

test('approval required for expenses above threshold', function () {
    $category = ExpenseCategory::factory()->create([
        'requires_approval' => true,
        'approval_threshold' => 1000,
    ]);

    expect($category->requiresApprovalFor(500))->toBeFalse();
    expect($category->requiresApprovalFor(1000))->toBeTrue();
    expect($category->requiresApprovalFor(2000))->toBeTrue();
});

test('category tree can be retrieved', function () {
    $parent = ExpenseCategory::factory()->create(['parent_id' => null]);
    ExpenseCategory::factory()->count(3)->create(['parent_id' => $parent->id]);

    $service = app(ExpenseCategoryService::class);
    $tree = $service->getCategoryTree();

    expect($tree)->not->toBeEmpty();
    expect($tree->first()->children)->toHaveCount(3);
});

test('flat list shows categories with depth indicators', function () {
    $parent = ExpenseCategory::factory()->create(['parent_id' => null, 'name' => 'Parent']);
    ExpenseCategory::factory()->create(['parent_id' => $parent->id, 'name' => 'Child']);

    $service = app(ExpenseCategoryService::class);
    $list = $service->getFlatList();

    $child = $list->firstWhere('name', 'Child');
    expect($child['depth'])->toBe(1);
    expect($child['full_name'])->toBe('Parent > Child');
});

test('categories can be reordered', function () {
    $cat1 = ExpenseCategory::factory()->create(['sort_order' => 0]);
    $cat2 = ExpenseCategory::factory()->create(['sort_order' => 1]);
    $cat3 = ExpenseCategory::factory()->create(['sort_order' => 2]);

    $service = app(ExpenseCategoryService::class);
    $service->reorder([
        $cat3->id => 0,
        $cat1->id => 1,
        $cat2->id => 2,
    ]);

    expect($cat3->fresh()->sort_order)->toBe(0);
    expect($cat1->fresh()->sort_order)->toBe(1);
    expect($cat2->fresh()->sort_order)->toBe(2);
});

test('deleting parent moves children to grandparent', function () {
    $grandparent = ExpenseCategory::factory()->create();
    $parent = ExpenseCategory::factory()->create(['parent_id' => $grandparent->id]);
    $child = ExpenseCategory::factory()->create(['parent_id' => $parent->id]);

    $parent->delete();

    expect($child->fresh()->parent_id)->toBe($grandparent->id);
});

test('tax deductible is set based on category type', function () {
    $utilities = ExpenseCategory::factory()->create([
        'type' => ExpenseCategoryType::UTILITIES,
    ]);
    
    $miscellaneous = ExpenseCategory::factory()->create([
        'type' => ExpenseCategoryType::MISCELLANEOUS,
    ]);

    expect($utilities->is_tax_deductible)->toBeTrue();
    expect($miscellaneous->is_tax_deductible)->toBeFalse();
});

test('budget summary calculates correctly', function () {
    $category = ExpenseCategory::factory()->create([
        'monthly_budget' => 10000,
    ]);

    // Create some expenses (mock or factory)
    // This would require Module 14 (Expenses) to be implemented

    $service = app(ExpenseCategoryService::class);
    $summary = $service->getBudgetSummary();

    expect($summary['total_budget'])->toBe(10000.00);
});
```

---

## 14. Commands to Execute

```bash
# Step 1: Create Enum
mkdir -p app/Enums
# Create ExpenseCategoryType enum

# Step 2: Create Models with migrations
php artisan make:model ExpenseCategory -mfs --no-interaction
php artisan make:model ShopExpenseCategorySetting -m --no-interaction

# Step 3: Create Actions
mkdir -p app/Actions/ExpenseCategories
# Create all expense category actions

# Step 4: Create Service
php artisan make:class Services/ExpenseCategoryService --no-interaction

# Step 5: Create Controller
php artisan make:controller ExpenseCategoryController --no-interaction

# Step 6: Create Form Requests
php artisan make:request StoreExpenseCategoryRequest --no-interaction
php artisan make:request UpdateExpenseCategoryRequest --no-interaction
php artisan make:request ConfigureShopCategoryRequest --no-interaction

# Step 7: Create Policy
php artisan make:policy ExpenseCategoryPolicy --model=ExpenseCategory --no-interaction

# Step 8: Create Factory
php artisan make:factory ExpenseCategoryFactory --no-interaction

# Step 9: Create Seeder
php artisan make:seeder ExpenseCategorySeeder --no-interaction

# Step 10: Run migrations
php artisan migrate

# Step 11: Run seeder
php artisan db:seed --class=ExpenseCategorySeeder

# Step 12: Create tests
php artisan make:test ExpenseCategoriesTest --pest --no-interaction

# Step 13: Run tests
php artisan test --compact --filter=ExpenseCategories

# Step 14: Format code
vendor/bin/pint --dirty
```

---

## 15. Verification Checklist

Before proceeding to Module 14, verify:

- [ ] All tables have `uuid` column with `getRouteKeyName()` on models
- [ ] All tables have audit columns (`created_by`, `updated_by`)
- [ ] Category code auto-generates uniquely
- [ ] Hierarchical relationships work (parent/child)
- [ ] Depth and path calculated correctly
- [ ] Breadcrumb shows full hierarchy path
- [ ] Category types have correct icons and colors
- [ ] Tax deductible flag set based on type
- [ ] Budget settings work at category level
- [ ] Shop-specific overrides work
- [ ] Category can be disabled per shop
- [ ] Approval threshold triggers correctly
- [ ] Category tree retrieval works
- [ ] Reordering updates sort_order
- [ ] Deleting parent moves children correctly
- [ ] Default categories seeded properly
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 16. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 14: Expenses](./14-expenses.md)**
