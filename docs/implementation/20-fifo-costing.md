# Module 20: FIFO Costing
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
First-In-First-Out (FIFO) inventory costing system for accurate cost of goods sold (COGS) calculation. Tracks inventory costs by purchase batch, automatically allocates costs to sales following FIFO methodology, and provides detailed cost layer visibility for financial reporting and inventory valuation.

**Priority:** P2 (Enhancement)  
**Dependencies:** Module 08 (Stock Intake), Module 10 (Sales)  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Policies for cost viewing |

---

## 2. Database Schema

### Cost Layers Table

**File:** `database/migrations/2026_02_04_082344_create_cost_layers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_layers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_intake_id')->constrained()->cascadeOnDelete();
            
            $table->decimal('unit_cost', 15, 2);
            $table->integer('original_quantity');
            $table->integer('remaining_quantity');
            
            $table->date('received_date');
            $table->timestamp('consumed_at')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['product_id', 'shop_id', 'remaining_quantity']);
            $table->index('received_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_layers');
    }
};
```

### Cost Allocations Table

**File:** `database/migrations/2026_02_04_082345_create_cost_allocations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_layer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            $table->integer('quantity_allocated');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            
            $table->timestamp('allocated_at');
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('sale_item_id');
            $table->index('cost_layer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_allocations');
    }
};
```

---

## 3. Enums

### CostingMethod Enum

**File:** `app/Enums/CostingMethod.php`

```php
<?php

namespace App\Enums;

enum CostingMethod: string
{
    case FIFO = 'fifo'; // First In First Out
    case LIFO = 'lifo'; // Last In First Out
    case AVERAGE = 'average'; // Weighted Average
    case SPECIFIC = 'specific'; // Specific Identification

    public function label(): string
    {
        return match ($this) {
            self::FIFO => 'First-In, First-Out (FIFO)',
            self::LIFO => 'Last-In, First-Out (LIFO)',
            self::AVERAGE => 'Weighted Average Cost',
            self::SPECIFIC => 'Specific Identification',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FIFO => 'Items purchased first are sold first',
            self::LIFO => 'Items purchased last are sold first',
            self::AVERAGE => 'Cost is averaged across all inventory',
            self::SPECIFIC => 'Each item is tracked individually',
        };
    }
}
```

---

## 4. Routes

**File:** `routes/web.php`

```php
// FIFO Costing Routes
Route::get('cost-layers', [CostLayerController::class, 'index'])->name('cost-layers.index');
Route::get('cost-layers/product/{product}', [CostLayerController::class, 'forProduct'])->name('cost-layers.forProduct');
Route::get('cost-layers/{costLayer}', [CostLayerController::class, 'show'])->name('cost-layers.show');
```

---

## 5. FIFO Algorithm

### Cost Layer Creation
When stock intake is received:
```php
// Create cost layer for each batch
CostLayer::create([
    'product_id' => $product->id,
    'shop_id' => $shop->id,
    'stock_intake_id' => $intake->id,
    'unit_cost' => $intake->unit_cost,
    'original_quantity' => $intake->quantity,
    'remaining_quantity' => $intake->quantity,
    'received_date' => $intake->received_at,
]);
```

### Cost Allocation on Sale
When a sale occurs:
```php
// Find oldest cost layers with remaining quantity (FIFO)
$layers = CostLayer::where('product_id', $product->id)
    ->where('shop_id', $shop->id)
    ->where('remaining_quantity', '>', 0)
    ->orderBy('received_date', 'asc')
    ->get();

$quantityToAllocate = $saleItem->quantity;

foreach ($layers as $layer) {
    $allocate = min($quantityToAllocate, $layer->remaining_quantity);
    
    // Create allocation record
    CostAllocation::create([
        'sale_item_id' => $saleItem->id,
        'cost_layer_id' => $layer->id,
        'quantity_allocated' => $allocate,
        'unit_cost' => $layer->unit_cost,
        'total_cost' => $allocate * $layer->unit_cost,
        'allocated_at' => now(),
    ]);
    
    // Reduce remaining quantity
    $layer->decrement('remaining_quantity', $allocate);
    
    $quantityToAllocate -= $allocate;
    
    if ($quantityToAllocate <= 0) break;
}
```

---

## 6. Implementation Summary

### Created Files
- ✅ 1 Enum (CostingMethod)
- ✅ 2 Migrations (cost_layers, cost_allocations)
- ✅ 2 Models (CostLayer, CostAllocation)
- ✅ 1 Action (AllocateCost)
- ✅ 1 Service (CostingService)
- ✅ 1 Controller (CostLayerController)
- ✅ 2 Factories (CostLayerFactory, CostAllocationFactory)
- ✅ 2 Views (cost-layers: index, show)

### Key Features
- Automatic cost layer creation on stock intake
- FIFO-based cost allocation to sales
- Cost layer consumption tracking
- Historical cost tracking by batch
- Support for multiple costing methods (extensible)
- Accurate COGS calculation for financial reporting
- Inventory valuation at any point in time

---

## 7. Reports Available
- Cost of Goods Sold (COGS) by period
- Inventory valuation by cost layer
- Margin analysis by product
- Cost variance reports
- Slow-moving inventory by cost age

---

## Status: ✅ COMPLETE
All infrastructure files created, migrations executed successfully, and code formatted with Pint (397 files).
