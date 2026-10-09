# Module 19: Returns & Refunds
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Comprehensive returns and refunds management system enabling return request processing, approval workflows, item inspection, restocking procedures, and refund processing. Integrates with sales and inventory modules for complete reverse logistics management.

**Priority:** P2 (Enhancement)  
**Dependencies:** Modules 10 (Sales), 09 (Inventory)  
**Estimated Time:** 2 days

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

### Returns Table

**File:** `database/migrations/2026_02_04_081057_create_returns_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            $table->string('return_number')->unique();
            $table->string('status'); // ReturnStatus enum
            $table->string('reason'); // ReturnReason enum
            $table->text('notes')->nullable();
            
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('restocking_fee', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            
            // Workflow
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users');
            $table->text('inspection_notes')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('return_number');
            $table->index(['sale_id', 'status']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
```

### Return Items Table

**File:** `database/migrations/2026_02_04_081058_create_return_items_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_intake_id')->nullable()->constrained('stock_intakes');
            
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            
            $table->string('condition')->nullable(); // new, opened, damaged, defective
            $table->text('condition_notes')->nullable();
            $table->boolean('is_restockable')->default(false);
            $table->boolean('is_restocked')->default(false);
            $table->timestamp('restocked_at')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
```

### Refunds Table

**File:** `database/migrations/2026_02_04_081059_create_refunds_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            $table->string('refund_number')->unique();
            $table->string('method'); // RefundMethod enum
            $table->decimal('amount', 15, 2);
            
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('notes')->nullable();
            
            $table->string('transaction_id')->nullable();
            $table->string('reference_number')->nullable();
            
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('refund_number');
            $table->index(['return_id', 'status']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
```

---

## 3. Enums

### ReturnReason Enum

**File:** `app/Enums/ReturnReason.php`

```php
<?php

namespace App\Enums;

enum ReturnReason: string
{
    case DEFECTIVE = 'defective';
    case DAMAGED = 'damaged';
    case WRONG_ITEM = 'wrong_item';
    case EXPIRED = 'expired';
    case CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case QUALITY_ISSUE = 'quality_issue';
    case DUPLICATE_ORDER = 'duplicate_order';
    case NO_LONGER_NEEDED = 'no_longer_needed';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DEFECTIVE => 'Defective Product',
            self::DAMAGED => 'Damaged During Shipping',
            self::WRONG_ITEM => 'Wrong Item Received',
            self::EXPIRED => 'Expired Product',
            self::CUSTOMER_CHANGED_MIND => 'Customer Changed Mind',
            self::NOT_AS_DESCRIBED => 'Not As Described',
            self::QUALITY_ISSUE => 'Quality Issue',
            self::DUPLICATE_ORDER => 'Duplicate Order',
            self::NO_LONGER_NEEDED => 'No Longer Needed',
            self::OTHER => 'Other',
        };
    }

    public function requiresInspection(): bool
    {
        return in_array($this, [
            self::DEFECTIVE,
            self::DAMAGED,
            self::QUALITY_ISSUE,
            self::EXPIRED,
        ]);
    }

    public function isRestockable(): bool
    {
        return in_array($this, [
            self::CUSTOMER_CHANGED_MIND,
            self::WRONG_ITEM,
            self::DUPLICATE_ORDER,
            self::NO_LONGER_NEEDED,
        ]);
    }
}
```

### ReturnStatus Enum

**File:** `app/Enums/ReturnStatus.php`

```php
<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECEIVED = 'received';
    case INSPECTED = 'inspected';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::RECEIVED => 'Items Received',
            self::INSPECTED => 'Inspected',
            self::COMPLETED => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::RECEIVED => 'primary',
            self::INSPECTED => 'secondary',
            self::COMPLETED => 'success',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::PENDING;
    }

    public function canRefund(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::INSPECTED,
            self::COMPLETED,
        ]);
    }
}
```

### RefundMethod Enum

**File:** `app/Enums/RefundMethod.php`

```php
<?php

namespace App\Enums;

enum RefundMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case STORE_CREDIT = 'store_credit';
    case ORIGINAL_PAYMENT = 'original_payment';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash Refund',
            self::CARD => 'Card Refund',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::STORE_CREDIT => 'Store Credit',
            self::ORIGINAL_PAYMENT => 'Original Payment Method',
        };
    }

    public function requiresApproval(): bool
    {
        return in_array($this, [
            self::CASH,
            self::BANK_TRANSFER,
        ]);
    }
}
```

---

## 4. Routes

**File:** `routes/web.php`

```php
// Returns & Refunds Routes
Route::resource('returns', ReturnController::class);
Route::post('returns/{return}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
Route::post('returns/{return}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
Route::post('returns/{return}/receive', [ReturnController::class, 'receive'])->name('returns.receive');
Route::post('returns/{return}/inspect', [ReturnController::class, 'inspect'])->name('returns.inspect');
Route::get('returns/sale/{sale}', [ReturnController::class, 'forSale'])->name('returns.forSale');

Route::resource('refunds', RefundController::class)->only(['index', 'show', 'store']);
Route::post('refunds/{refund}/process', [RefundController::class, 'process'])->name('refunds.process');
```

---

## 5. Implementation Summary

### Created Files
- ✅ 3 Enums (ReturnReason, ReturnStatus, RefundMethod)
- ✅ 3 Migrations (returns, return_items, refunds)
- ✅ 3 Models (SaleReturn, ReturnItem, Refund)
- ✅ 3 Actions (ProcessReturn, ApproveReturn, ProcessRefund)
- ✅ 1 Service (ReturnService)
- ✅ 2 Controllers (ReturnController, RefundController)
- ✅ 2 Requests (StoreReturnRequest, ProcessRefundRequest)
- ✅ 1 Policy (ReturnPolicy)
- ✅ 3 Factories (ReturnFactory, ReturnItemFactory, RefundFactory)
- ✅ 6 Views (returns: index, show, create, edit; refunds: index, show)

### Key Features
- Return request creation and approval workflow
- Multiple return reasons with automatic restockability detection
- Item-level condition tracking and inspection
- Restocking fee calculation
- Multiple refund methods support
- Refund processing with transaction tracking
- Complete audit trail for all operations

---

## Status: ✅ COMPLETE
All infrastructure files created, migrations executed successfully, and code formatted with Pint (387 files).
