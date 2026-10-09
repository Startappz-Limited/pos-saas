# Module 21: Stock Adjustments
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Stock adjustment system for correcting inventory discrepancies, recording damaged/expired goods, physical count adjustments, and other inventory changes outside of normal sales and intake workflows. Includes approval workflow for theft/loss adjustments and complete audit trail.

**Priority:** P2 (Enhancement)  
**Dependencies:** Module 09 (Inventory Tracking)  
**Estimated Time:** 1.5 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns, Enum types |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Auditable trait on models |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies, Approval workflow |

---

## 2. Database Schema

### Stock Adjustments Table

**File:** `database/migrations/2026_02_04_082604_create_stock_adjustments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('adjustment_number')->unique();
            
            $table->string('type'); // AdjustmentType enum
            $table->string('reason'); // AdjustmentReason enum
            $table->text('notes')->nullable();
            
            $table->string('status')->default('pending'); // pending, approved, rejected, completed
            
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('adjustment_number');
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
```

### Stock Adjustment Items Table

**File:** `database/migrations/2026_02_04_082605_create_stock_adjustment_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            $table->integer('quantity_before');
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            
            $table->text('item_notes')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('stock_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
```

---

## 3. Enums

### AdjustmentType Enum

**File:** `app/Enums/AdjustmentType.php`

```php
<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case INCREASE = 'increase';
    case DECREASE = 'decrease';

    public function label(): string
    {
        return match ($this) {
            self::INCREASE => 'Stock Increase',
            self::DECREASE => 'Stock Decrease',
        };
    }

    public function multiplier(): int
    {
        return match ($this) {
            self::INCREASE => 1,
            self::DECREASE => -1,
        };
    }
}
```

### AdjustmentReason Enum

**File:** `app/Enums/AdjustmentReason.php`

```php
<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case DAMAGED = 'damaged';
    case EXPIRED = 'expired';
    case LOST = 'lost';
    case STOLEN = 'stolen';
    case FOUND = 'found';
    case RECOUNT = 'recount';
    case QUALITY_ISSUE = 'quality_issue';
    case RETURNED_TO_SUPPLIER = 'returned_to_supplier';
    case PROMOTIONAL_GIVEAWAY = 'promotional_giveaway';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DAMAGED => 'Damaged Goods',
            self::EXPIRED => 'Expired Products',
            self::LOST => 'Lost Inventory',
            self::STOLEN => 'Theft/Stolen',
            self::FOUND => 'Found During Count',
            self::RECOUNT => 'Physical Count Adjustment',
            self::QUALITY_ISSUE => 'Quality Issue',
            self::RETURNED_TO_SUPPLIER => 'Returned to Supplier',
            self::PROMOTIONAL_GIVEAWAY => 'Promotional Giveaway',
            self::OTHER => 'Other',
        };
    }

    public function isNegative(): bool
    {
        return in_array($this, [
            self::DAMAGED,
            self::EXPIRED,
            self::LOST,
            self::STOLEN,
            self::QUALITY_ISSUE,
            self::RETURNED_TO_SUPPLIER,
            self::PROMOTIONAL_GIVEAWAY,
        ]);
    }

    public function requiresApproval(): bool
    {
        return in_array($this, [
            self::LOST,
            self::STOLEN,
        ]);
    }
}
```

---

## 4. Routes

**File:** `routes/web.php`

```php
// Stock Adjustment Routes
Route::resource('stock-adjustments', StockAdjustmentController::class);
Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
Route::post('stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');
Route::post('stock-adjustments/{stockAdjustment}/complete', [StockAdjustmentController::class, 'complete'])->name('stock-adjustments.complete');
```

---

## 5. Adjustment Workflow

### 1. Create Adjustment
```php
$adjustment = StockAdjustment::create([
    'shop_id' => $shop->id,
    'adjustment_number' => 'ADJ-' . date('Ymd') . '-' . $sequence,
    'type' => AdjustmentType::DECREASE,
    'reason' => AdjustmentReason::DAMAGED,
    'notes' => 'Water damage in storage area',
    'status' => 'pending',
    'created_by' => auth()->id(),
]);

// Add items
StockAdjustmentItem::create([
    'stock_adjustment_id' => $adjustment->id,
    'product_id' => $product->id,
    'shop_id' => $shop->id,
    'quantity_before' => 100,
    'quantity_change' => -15,
    'quantity_after' => 85,
]);
```

### 2. Approval (if required)
```php
if ($adjustment->reason->requiresApproval()) {
    // Requires manager approval
    $adjustment->update([
        'approved_by' => auth()->id(),
        'approved_at' => now(),
        'status' => 'approved',
    ]);
}
```

### 3. Complete & Apply
```php
foreach ($adjustment->items as $item) {
    // Update inventory
    $inventory = Inventory::where('product_id', $item->product_id)
        ->where('shop_id', $item->shop_id)
        ->first();
    
    $inventory->update([
        'quantity' => $item->quantity_after,
    ]);
    
    // Create stock movement record
    StockMovement::create([
        'product_id' => $item->product_id,
        'shop_id' => $item->shop_id,
        'type' => 'adjustment',
        'quantity' => $item->quantity_change,
        'reference_type' => 'stock_adjustment',
        'reference_id' => $adjustment->id,
    ]);
}

$adjustment->update(['status' => 'completed']);
```

---

## 6. Implementation Summary

### Created Files
- ✅ 2 Enums (AdjustmentType, AdjustmentReason)
- ✅ 2 Migrations (stock_adjustments, stock_adjustment_items)
- ✅ 2 Models (StockAdjustment, StockAdjustmentItem)
- ✅ 2 Actions (CreateStockAdjustment, ApproveStockAdjustment)
- ✅ 1 Service (StockAdjustmentService)
- ✅ 1 Controller (StockAdjustmentController)
- ✅ 1 Request (StoreStockAdjustmentRequest)
- ✅ 1 Policy (StockAdjustmentPolicy)
- ✅ 2 Factories (StockAdjustmentFactory, StockAdjustmentItemFactory)
- ✅ 4 Views (stock-adjustments: index, show, create, edit)

### Key Features
- Increase/decrease adjustment types
- 10 predefined adjustment reasons
- Multi-item adjustments in single transaction
- Before/after quantity tracking
- Approval workflow for theft/loss
- Automatic inventory updates
- Complete audit trail via stock movements
- Adjustment number generation

---

## 7. Use Cases
- Physical stock counts and reconciliation
- Recording damaged or expired goods
- Theft or loss documentation
- Promotional giveaways tracking
- Supplier returns (without refund)
- Found inventory during counts
- Quality control removals

---

## Status: ✅ COMPLETE
All infrastructure files created, migrations executed successfully, and code formatted with Pint (411 files).
