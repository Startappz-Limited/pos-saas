# Module 14: Expenses
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Complete expense tracking system with receipt management, approval workflow, recurring expenses, vendor tracking, and integration with expense categories for budget monitoring and financial reporting.

**Priority:** P1 (High)  
**Dependencies:** Modules 03, 13  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns, Enum status |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Auditable trait on Expense model |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Expenses Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('suppliers')->nullOnDelete();
            
            // Expense details
            $table->string('expense_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('KES');
            
            // Payment details
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->date('paid_date')->nullable();
            
            // Dates
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            
            // Status & Approval
            $table->string('status'); // ExpenseStatus enum
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Recurring
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable(); // RecurrenceFrequency enum
            $table->date('recurrence_end_date')->nullable();
            $table->foreignId('parent_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            
            // Tax
            $table->boolean('is_tax_deductible')->default(false);
            $table->decimal('tax_amount', 15, 2)->nullable();
            
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('uuid');
            $table->index('expense_number');
            $table->index(['shop_id', 'expense_date']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'expense_date']);
            $table->index('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
```

### Expense Receipts Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->string('original_name');
            
            // Audit columns
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_receipts');
    }
};
```

### Expense Approval History Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_approval_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('action'); // submitted, approved, rejected, returned
            $table->string('from_status');
            $table->string('to_status');
            $table->text('comments')->nullable();
            
            $table->timestamps();
            
            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_approval_history');
    }
};
```

---

## 3. Enums

### ExpenseStatus Enum

**File:** `app/Enums/ExpenseStatus.php`

```php
<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PAID => 'primary',
            self::CANCELLED => 'dark',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED]);
    }

    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    public function canPay(): bool
    {
        return $this === self::APPROVED;
    }
}
```

### RecurrenceFrequency Enum

**File:** `app/Enums/RecurrenceFrequency.php`

```php
<?php

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::BIWEEKLY => 'Every 2 Weeks',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        };
    }

    public function nextDate(\Carbon\Carbon $from): \Carbon\Carbon
    {
        return match ($this) {
            self::DAILY => $from->addDay(),
            self::WEEKLY => $from->addWeek(),
            self::BIWEEKLY => $from->addWeeks(2),
            self::MONTHLY => $from->addMonth(),
            self::QUARTERLY => $from->addMonths(3),
            self::YEARLY => $from->addYear(),
        };
    }
}
```

---

## 4. Models

### Expense Model

**File:** `app/Models/Expense.php`

```php
<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Enums\RecurrenceFrequency;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'uuid',
        'shop_id',
        'category_id',
        'vendor_id',
        'expense_number',
        'title',
        'description',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'is_paid',
        'paid_date',
        'expense_date',
        'due_date',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'approved_by',
        'is_recurring',
        'recurrence_frequency',
        'recurrence_end_date',
        'parent_expense_id',
        'is_tax_deductible',
        'tax_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'recurrence_frequency' => RecurrenceFrequency::class,
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'is_paid' => 'boolean',
            'is_recurring' => 'boolean',
            'is_tax_deductible' => 'boolean',
            'expense_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'recurrence_end_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Expense $expense) {
            if (empty($expense->uuid)) {
                $expense->uuid = (string) Str::uuid();
            }
            if (empty($expense->expense_number)) {
                $expense->expense_number = self::generateExpenseNumber($expense->shop_id);
            }
            if (empty($expense->status)) {
                $expense->status = ExpenseStatus::DRAFT;
            }
            $expense->created_by = auth()->id();
        });

        static::updating(function (Expense $expense) {
            $expense->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateExpenseNumber(int $shopId): string
    {
        $prefix = 'EXP';
        $shopPrefix = str_pad($shopId, 2, '0', STR_PAD_LEFT);
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())
            ->where('shop_id', $shopId)
            ->count() + 1;
        
        return "{$prefix}-{$shopPrefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class);
    }

    public function approvalHistory(): HasMany
    {
        return $this->hasMany(ExpenseApprovalHistory::class);
    }

    public function parentExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    public function childExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Methods

    public function submit(): void
    {
        $this->status = ExpenseStatus::PENDING;
        $this->submitted_at = now();
        $this->save();

        $this->recordApprovalHistory('submitted', ExpenseStatus::DRAFT, ExpenseStatus::PENDING);
    }

    public function approve(?string $comments = null): void
    {
        $previousStatus = $this->status;
        $this->status = ExpenseStatus::APPROVED;
        $this->approved_at = now();
        $this->approved_by = auth()->id();
        $this->save();

        $this->recordApprovalHistory('approved', $previousStatus, ExpenseStatus::APPROVED, $comments);
    }

    public function reject(string $reason): void
    {
        $previousStatus = $this->status;
        $this->status = ExpenseStatus::REJECTED;
        $this->rejection_reason = $reason;
        $this->save();

        $this->recordApprovalHistory('rejected', $previousStatus, ExpenseStatus::REJECTED, $reason);
    }

    public function markAsPaid(PaymentMethod $method, ?string $reference = null): void
    {
        $this->status = ExpenseStatus::PAID;
        $this->is_paid = true;
        $this->paid_date = now();
        $this->payment_method = $method;
        $this->reference_number = $reference;
        $this->save();
    }

    public function cancel(): void
    {
        $previousStatus = $this->status;
        $this->status = ExpenseStatus::CANCELLED;
        $this->save();

        $this->recordApprovalHistory('cancelled', $previousStatus, ExpenseStatus::CANCELLED);
    }

    protected function recordApprovalHistory(string $action, ExpenseStatus $from, ExpenseStatus $to, ?string $comments = null): void
    {
        $this->approvalHistory()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'comments' => $comments,
        ]);
    }

    public function requiresApproval(): bool
    {
        return $this->category->requiresApprovalFor($this->amount, $this->shop);
    }

    public function getTotalWithTaxAttribute(): float
    {
        return $this->amount + ($this->tax_amount ?? 0);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && 
               !$this->is_paid && 
               $this->due_date->isPast() &&
               $this->status !== ExpenseStatus::CANCELLED;
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        return $this->due_date->diffInDays(now());
    }

    // Scopes

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeStatus($query, ExpenseStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', ExpenseStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID]);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false)
                     ->where('status', ExpenseStatus::APPROVED);
    }

    public function scopeOverdue($query)
    {
        return $query->unpaid()
                     ->whereNotNull('due_date')
                     ->where('due_date', '<', now());
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('expense_date', now()->month)
                     ->whereYear('expense_date', now()->year);
    }

    public function scopeTaxDeductible($query)
    {
        return $query->where('is_tax_deductible', true);
    }
}
```

### ExpenseReceipt Model

**File:** `app/Models/ExpenseReceipt.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseReceipt extends Model
{
    protected $fillable = [
        'uuid',
        'expense_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'original_name',
        'uploaded_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $receipt) {
            if (empty($receipt->uuid)) {
                $receipt->uuid = (string) Str::uuid();
            }
            $receipt->uploaded_by = auth()->id();
        });

        static::deleting(function (self $receipt) {
            Storage::disk('receipts')->delete($receipt->file_path);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('receipts')->url($this->file_path);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### ExpenseApprovalHistory Model

**File:** `app/Models/ExpenseApprovalHistory.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExpenseApprovalHistory extends Model
{
    protected $table = 'expense_approval_history';

    protected $fillable = [
        'uuid',
        'expense_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'comments',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $history) {
            if (empty($history->uuid)) {
                $history->uuid = (string) Str::uuid();
            }
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## 5. Actions

### CreateExpenseAction

**File:** `app/Actions/Expenses/CreateExpenseAction.php`

```php
<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class CreateExpenseAction
{
    public function execute(
        Shop $shop,
        ExpenseCategory $category,
        string $title,
        float $amount,
        \Carbon\Carbon $expenseDate,
        array $additionalData = []
    ): Expense {
        return DB::transaction(function () use ($shop, $category, $title, $amount, $expenseDate, $additionalData) {
            $expense = Expense::create([
                'shop_id' => $shop->id,
                'category_id' => $category->id,
                'title' => $title,
                'amount' => $amount,
                'expense_date' => $expenseDate,
                'is_tax_deductible' => $category->is_tax_deductible,
                ...$additionalData,
            ]);

            return $expense;
        });
    }
}
```

### SubmitExpenseAction

**File:** `app/Actions/Expenses/SubmitExpenseAction.php`

```php
<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Notifications\ExpenseSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubmitExpenseAction
{
    public function execute(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense) {
            $expense->submit();

            // Notify approvers
            $approvers = $expense->shop->users()
                ->permission('expenses.approve')
                ->get();

            Notification::send($approvers, new ExpenseSubmittedNotification($expense));

            return $expense->refresh();
        });
    }
}
```

### ApproveExpenseAction

**File:** `app/Actions/Expenses/ApproveExpenseAction.php`

```php
<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Notifications\ExpenseApprovedNotification;
use Illuminate\Support\Facades\DB;

class ApproveExpenseAction
{
    public function execute(Expense $expense, ?string $comments = null): Expense
    {
        return DB::transaction(function () use ($expense, $comments) {
            $expense->approve($comments);

            // Notify creator
            $expense->createdBy->notify(new ExpenseApprovedNotification($expense));

            return $expense->refresh();
        });
    }
}
```

### RejectExpenseAction

**File:** `app/Actions/Expenses/RejectExpenseAction.php`

```php
<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Notifications\ExpenseRejectedNotification;
use Illuminate\Support\Facades\DB;

class RejectExpenseAction
{
    public function execute(Expense $expense, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $reason) {
            $expense->reject($reason);

            // Notify creator
            $expense->createdBy->notify(new ExpenseRejectedNotification($expense, $reason));

            return $expense->refresh();
        });
    }
}
```

### ProcessRecurringExpensesAction

**File:** `app/Actions/Expenses/ProcessRecurringExpensesAction.php`

```php
<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ProcessRecurringExpensesAction
{
    public function execute(): array
    {
        $created = [];

        $recurringExpenses = Expense::recurring()
            ->approved()
            ->whereNull('recurrence_end_date')
            ->orWhere('recurrence_end_date', '>=', now())
            ->get();

        foreach ($recurringExpenses as $expense) {
            $nextDate = $expense->recurrence_frequency->nextDate($expense->expense_date);

            if ($nextDate->isToday() || $nextDate->isPast()) {
                $created[] = $this->createRecurringInstance($expense, $nextDate);
            }
        }

        return $created;
    }

    private function createRecurringInstance(Expense $parent, \Carbon\Carbon $date): Expense
    {
        return DB::transaction(function () use ($parent, $date) {
            return Expense::create([
                'shop_id' => $parent->shop_id,
                'category_id' => $parent->category_id,
                'vendor_id' => $parent->vendor_id,
                'title' => $parent->title,
                'description' => $parent->description,
                'amount' => $parent->amount,
                'expense_date' => $date,
                'is_recurring' => false,
                'parent_expense_id' => $parent->id,
                'is_tax_deductible' => $parent->is_tax_deductible,
                'status' => ExpenseStatus::DRAFT,
            ]);
        });
    }
}
```

---

## 6. Services

### ExpenseService

**File:** `app/Services/ExpenseService.php`

```php
<?php

namespace App\Services;

use App\Actions\Expenses\ApproveExpenseAction;
use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\RejectExpenseAction;
use App\Actions\Expenses\SubmitExpenseAction;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseReceipt;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function __construct(
        private CreateExpenseAction $createExpense,
        private SubmitExpenseAction $submitExpense,
        private ApproveExpenseAction $approveExpense,
        private RejectExpenseAction $rejectExpense
    ) {}

    /**
     * Create a new expense
     */
    public function create(
        Shop $shop,
        ExpenseCategory $category,
        string $title,
        float $amount,
        Carbon $expenseDate,
        array $additionalData = []
    ): Expense {
        return $this->createExpense->execute($shop, $category, $title, $amount, $expenseDate, $additionalData);
    }

    /**
     * Update expense
     */
    public function update(Expense $expense, array $data): Expense
    {
        if (!$expense->status->canEdit()) {
            throw new \Exception('Expense cannot be edited in current status.');
        }

        $expense->update($data);
        return $expense->refresh();
    }

    /**
     * Submit expense for approval
     */
    public function submit(Expense $expense): Expense
    {
        if ($expense->status !== ExpenseStatus::DRAFT && $expense->status !== ExpenseStatus::REJECTED) {
            throw new \Exception('Only draft or rejected expenses can be submitted.');
        }

        return $this->submitExpense->execute($expense);
    }

    /**
     * Approve expense
     */
    public function approve(Expense $expense, ?string $comments = null): Expense
    {
        if (!$expense->status->canApprove()) {
            throw new \Exception('Expense cannot be approved in current status.');
        }

        return $this->approveExpense->execute($expense, $comments);
    }

    /**
     * Reject expense
     */
    public function reject(Expense $expense, string $reason): Expense
    {
        if (!$expense->status->canApprove()) {
            throw new \Exception('Expense cannot be rejected in current status.');
        }

        return $this->rejectExpense->execute($expense, $reason);
    }

    /**
     * Mark expense as paid
     */
    public function markAsPaid(Expense $expense, PaymentMethod $method, ?string $reference = null): Expense
    {
        if (!$expense->status->canPay()) {
            throw new \Exception('Only approved expenses can be marked as paid.');
        }

        $expense->markAsPaid($method, $reference);
        return $expense->refresh();
    }

    /**
     * Upload receipt
     */
    public function uploadReceipt(Expense $expense, UploadedFile $file): ExpenseReceipt
    {
        $fileName = $expense->expense_number . '-' . time() . '.' . $file->extension();
        $path = $file->storeAs('expenses/' . $expense->shop_id, $fileName, 'receipts');

        return $expense->receipts()->create([
            'file_name' => $fileName,
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Delete receipt
     */
    public function deleteReceipt(ExpenseReceipt $receipt): void
    {
        $receipt->delete();
    }

    /**
     * Get expense summary
     */
    public function getSummary(?Shop $shop = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $query = Expense::approved()
            ->betweenDates($startDate, $endDate)
            ->when($shop, fn ($q) => $q->forShop($shop->id));

        $byCategory = (clone $query)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category')
            ->get()
            ->mapWithKeys(fn ($e) => [$e->category->name => $e->total]);

        return [
            'total_expenses' => (clone $query)->sum('amount'),
            'expense_count' => (clone $query)->count(),
            'average_expense' => (clone $query)->avg('amount') ?? 0,
            'by_category' => $byCategory,
            'pending_approval' => Expense::pending()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->count(),
            'pending_amount' => Expense::pending()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->sum('amount'),
            'unpaid_expenses' => Expense::unpaid()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->count(),
            'overdue_expenses' => Expense::overdue()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->count(),
        ];
    }

    /**
     * Get expenses by category for period
     */
    public function getByCategory(?Shop $shop = null, ?Carbon $month = null): Collection
    {
        $month = $month ?? now();

        return Expense::approved()
            ->whereMonth('expense_date', $month->month)
            ->whereYear('expense_date', $month->year)
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($expenses, $categoryId) {
                $category = $expenses->first()->category;
                return [
                    'category' => $category,
                    'total' => $expenses->sum('amount'),
                    'count' => $expenses->count(),
                    'expenses' => $expenses,
                ];
            });
    }

    /**
     * Get pending approvals for user
     */
    public function getPendingApprovals(?Shop $shop = null): Collection
    {
        return Expense::with(['category', 'shop', 'createdBy'])
            ->pending()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Get overdue expenses
     */
    public function getOverdueExpenses(?Shop $shop = null): Collection
    {
        return Expense::with(['category', 'shop', 'vendor'])
            ->overdue()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get tax deductible expenses for period
     */
    public function getTaxDeductible(?Shop $shop = null, ?int $year = null): Collection
    {
        $year = $year ?? now()->year;

        return Expense::with(['category'])
            ->taxDeductible()
            ->approved()
            ->whereYear('expense_date', $year)
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->orderBy('expense_date')
            ->get();
    }

    /**
     * Get expense trend
     */
    public function getExpenseTrend(?Shop $shop = null, int $months = 12): Collection
    {
        $data = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $total = Expense::approved()
                ->whereMonth('expense_date', $date->month)
                ->whereYear('expense_date', $date->year)
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->sum('amount');

            $data->push([
                'month' => $date->format('M Y'),
                'total' => $total,
            ]);
        }

        return $data;
    }
}
```

---

## 7. Controllers

### ExpenseController

**File:** `app/Http/Controllers/ExpenseController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Requests\UploadReceiptRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\ExpenseCategoryService;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseService $expenseService,
        private ExpenseCategoryService $categoryService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $shopId = $request->get('shop_id');
        $categoryId = $request->get('category_id');
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $expenses = Expense::with(['category', 'shop', 'vendor', 'createdBy'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($categoryId, fn ($q) => $q->forCategory($categoryId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->where('expense_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('expense_date', '<=', $dateTo))
            ->latest('expense_date')
            ->paginate(30);

        $shops = Shop::active()->get();
        $categories = $this->categoryService->getForDropdown();
        $summary = $this->expenseService->getSummary(
            $shopId ? Shop::find($shopId) : null
        );

        return view('expenses.index', compact('expenses', 'shops', 'categories', 'summary'));
    }

    public function create(): View
    {
        $this->authorize('create', Expense::class);

        $shops = Shop::active()->get();
        $categories = $this->categoryService->getForDropdown();
        $vendors = Supplier::active()->get();

        return view('expenses.create', compact('shops', 'categories', 'vendors'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $shop = Shop::findOrFail($request->shop_id);
        $category = ExpenseCategory::findOrFail($request->category_id);

        $expense = $this->expenseService->create(
            $shop,
            $category,
            $request->title,
            $request->amount,
            \Carbon\Carbon::parse($request->expense_date),
            $request->safe()->except(['shop_id', 'category_id', 'title', 'amount', 'expense_date', 'receipts'])
        );

        // Upload receipts if any
        if ($request->hasFile('receipts')) {
            foreach ($request->file('receipts') as $file) {
                $this->expenseService->uploadReceipt($expense, $file);
            }
        }

        return redirect()->route('expenses.show', $expense->uuid)
            ->with('success', 'Expense created successfully.');
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'shop', 'vendor', 'receipts', 'approvalHistory.user', 'createdBy', 'approvedBy']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        if (!$expense->status->canEdit()) {
            return redirect()->route('expenses.show', $expense->uuid)
                ->with('error', 'This expense cannot be edited.');
        }

        $shops = Shop::active()->get();
        $categories = $this->categoryService->getForDropdown();
        $vendors = Supplier::active()->get();

        return view('expenses.edit', compact('expense', 'shops', 'categories', 'vendors'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        try {
            $this->expenseService->update($expense, $request->validated());
            return redirect()->route('expenses.show', $expense->uuid)
                ->with('success', 'Expense updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        if (!$expense->status->canEdit()) {
            return back()->with('error', 'This expense cannot be deleted.');
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }

    public function submit(Expense $expense): RedirectResponse
    {
        $this->authorize('submit', $expense);

        try {
            $this->expenseService->submit($expense);
            return back()->with('success', 'Expense submitted for approval.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);

        try {
            $this->expenseService->approve($expense, $request->comments);
            return back()->with('success', 'Expense approved.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('reject', $expense);

        $request->validate(['reason' => 'required|string|min:10']);

        try {
            $this->expenseService->reject($expense, $request->reason);
            return back()->with('success', 'Expense rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pay(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('pay', $expense);

        $request->validate([
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string|max:100',
        ]);

        try {
            $this->expenseService->markAsPaid(
                $expense,
                PaymentMethod::from($request->payment_method),
                $request->reference_number
            );
            return back()->with('success', 'Expense marked as paid.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function uploadReceipt(UploadReceiptRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('uploadReceipt', $expense);

        $this->expenseService->uploadReceipt($expense, $request->file('receipt'));

        return back()->with('success', 'Receipt uploaded.');
    }

    public function deleteReceipt(Expense $expense, \App\Models\ExpenseReceipt $receipt): RedirectResponse
    {
        $this->authorize('deleteReceipt', $expense);

        $this->expenseService->deleteReceipt($receipt);

        return back()->with('success', 'Receipt deleted.');
    }

    public function pending(Request $request): View
    {
        $this->authorize('approve', Expense::class);

        $shopId = $request->get('shop_id');
        $shop = $shopId ? Shop::find($shopId) : null;

        $expenses = $this->expenseService->getPendingApprovals($shop);
        $shops = Shop::active()->get();

        return view('expenses.pending', compact('expenses', 'shops'));
    }
}
```

---

## 8. Form Requests

### StoreExpenseRequest

**File:** `app/Http/Requests/StoreExpenseRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Expense::class);
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'exists:shops,id'],
            'category_id' => ['required', 'exists:expense_categories,id'],
            'vendor_id' => ['nullable', 'exists:suppliers,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:expense_date'],
            'is_recurring' => ['boolean'],
            'recurrence_frequency' => ['required_if:is_recurring,true', 'nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_end_date' => ['nullable', 'date', 'after:expense_date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipts' => ['nullable', 'array'],
            'receipts.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\ExpenseController;

Route::middleware(['auth'])->prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])
        ->name('index')
        ->middleware('permission:expenses.view');
    
    Route::get('/pending', [ExpenseController::class, 'pending'])
        ->name('pending')
        ->middleware('permission:expenses.approve');
    
    Route::get('/create', [ExpenseController::class, 'create'])
        ->name('create')
        ->middleware('permission:expenses.create');
    
    Route::post('/', [ExpenseController::class, 'store'])
        ->name('store')
        ->middleware('permission:expenses.create');
    
    Route::get('/{expense}', [ExpenseController::class, 'show'])
        ->name('show')
        ->middleware('permission:expenses.view');
    
    Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])
        ->name('edit')
        ->middleware('permission:expenses.update');
    
    Route::put('/{expense}', [ExpenseController::class, 'update'])
        ->name('update')
        ->middleware('permission:expenses.update');
    
    Route::delete('/{expense}', [ExpenseController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:expenses.delete');
    
    Route::post('/{expense}/submit', [ExpenseController::class, 'submit'])
        ->name('submit')
        ->middleware('permission:expenses.submit');
    
    Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])
        ->name('approve')
        ->middleware('permission:expenses.approve');
    
    Route::post('/{expense}/reject', [ExpenseController::class, 'reject'])
        ->name('reject')
        ->middleware('permission:expenses.reject');
    
    Route::post('/{expense}/pay', [ExpenseController::class, 'pay'])
        ->name('pay')
        ->middleware('permission:expenses.pay');
    
    Route::post('/{expense}/receipt', [ExpenseController::class, 'uploadReceipt'])
        ->name('upload-receipt')
        ->middleware('permission:expenses.update');
    
    Route::delete('/{expense}/receipt/{receipt}', [ExpenseController::class, 'deleteReceipt'])
        ->name('delete-receipt')
        ->middleware('permission:expenses.update');
});
```

---

## 10. Permissions

```php
'expenses.view',
'expenses.create',
'expenses.update',
'expenses.delete',
'expenses.submit',
'expenses.approve',
'expenses.reject',
'expenses.pay',
'expenses.reports',
```

---

## 11. Scheduled Commands

**File:** `app/Console/Commands/ProcessRecurringExpensesCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Actions\Expenses\ProcessRecurringExpensesAction;
use Illuminate\Console\Command;

class ProcessRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:process-recurring';
    protected $description = 'Create instances of recurring expenses';

    public function handle(ProcessRecurringExpensesAction $action): int
    {
        $created = $action->execute();
        $this->info('Created ' . count($created) . ' recurring expense instances.');
        return Command::SUCCESS;
    }
}
```

---

## 12. Tests

**File:** `tests/Feature/ExpensesTest.php`

```php
<?php

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\ExpenseService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('expense can be created', function () {
    $shop = Shop::factory()->create();
    $category = ExpenseCategory::factory()->create();

    $service = app(ExpenseService::class);
    $expense = $service->create($shop, $category, 'Office Supplies', 500, now());

    expect($expense)
        ->title->toBe('Office Supplies')
        ->amount->toBe(500.00)
        ->status->toBe(ExpenseStatus::DRAFT);
});

test('expense number is auto-generated', function () {
    $expense = Expense::factory()->create();
    expect($expense->expense_number)->toStartWith('EXP-');
});

test('draft expense can be submitted for approval', function () {
    $expense = Expense::factory()->draft()->create();

    $service = app(ExpenseService::class);
    $service->submit($expense);

    expect($expense->fresh()->status)->toBe(ExpenseStatus::PENDING);
});

test('pending expense can be approved', function () {
    $expense = Expense::factory()->pending()->create();

    $service = app(ExpenseService::class);
    $service->approve($expense, 'Approved for office use');

    expect($expense->fresh())
        ->status->toBe(ExpenseStatus::APPROVED)
        ->approved_by->not->toBeNull();
});

test('pending expense can be rejected', function () {
    $expense = Expense::factory()->pending()->create();

    $service = app(ExpenseService::class);
    $service->reject($expense, 'Missing documentation');

    expect($expense->fresh())
        ->status->toBe(ExpenseStatus::REJECTED)
        ->rejection_reason->toBe('Missing documentation');
});

test('approved expense can be marked as paid', function () {
    $expense = Expense::factory()->approved()->create();

    $service = app(ExpenseService::class);
    $service->markAsPaid($expense, PaymentMethod::CASH);

    expect($expense->fresh())
        ->status->toBe(ExpenseStatus::PAID)
        ->is_paid->toBeTrue()
        ->payment_method->toBe(PaymentMethod::CASH);
});

test('approval history is tracked', function () {
    $expense = Expense::factory()->draft()->create();

    $service = app(ExpenseService::class);
    $service->submit($expense);

    expect($expense->approvalHistory)->toHaveCount(1);
    expect($expense->approvalHistory->first()->action)->toBe('submitted');
});
```

---

## 13. Verification Checklist

- [ ] All tables have `uuid` column with `getRouteKeyName()`
- [ ] All tables have audit columns
- [ ] Expense workflow: Draft → Pending → Approved → Paid
- [ ] Receipt upload and storage works
- [ ] Approval history tracked
- [ ] Recurring expenses processed
- [ ] Budget integration with categories
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass

---

## 14. Next Steps

→ **[Module 15: Advertising ROI](./15-advertising-roi.md)**
