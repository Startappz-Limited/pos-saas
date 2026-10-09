# Module 12: Credit Sales Management
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Comprehensive credit sales management system enabling customer credit accounts, credit limit enforcement, outstanding balance tracking, payment collection workflows, and credit reporting. Integrates with sales and payments modules for complete accounts receivable management.

**Priority:** P0 (Critical)  
**Dependencies:** Modules 10, 11  
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

### Credit Accounts Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            // Credit limits
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);
            
            // Status
            $table->string('status'); // CreditAccountStatus enum
            $table->boolean('is_suspended')->default(false);
            $table->string('suspension_reason')->nullable();
            
            // Payment terms
            $table->integer('payment_terms_days')->default(30);
            $table->integer('grace_period_days')->default(7);
            
            // Timestamps
            $table->timestamp('limit_updated_at')->nullable();
            $table->foreignId('limit_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('uuid');
            $table->unique(['customer_id', 'shop_id']);
            $table->index(['shop_id', 'status']);
            $table->index('current_balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
```

### Credit Transactions Table (Ledger)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            // Transaction details
            $table->string('transaction_number')->unique();
            $table->string('type'); // CreditTransactionType enum
            $table->string('reference_type')->nullable(); // Sale, Payment, Adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Amounts
            $table->decimal('debit', 15, 2)->default(0); // Increases balance (purchases)
            $table->decimal('credit', 15, 2)->default(0); // Decreases balance (payments)
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            
            // Due date for credit sales
            $table->date('due_date')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->integer('days_overdue')->default(0);
            
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('uuid');
            $table->index('transaction_number');
            $table->index(['credit_account_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['due_date', 'is_overdue']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
```

### Credit Limit Requests Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_limit_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            
            // Request details
            $table->string('request_number')->unique();
            $table->decimal('current_limit', 15, 2);
            $table->decimal('requested_limit', 15, 2);
            $table->text('justification');
            
            // Status
            $table->string('status'); // CreditRequestStatus enum
            
            // Approval workflow
            $table->decimal('approved_limit', 15, 2)->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('uuid');
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_limit_requests');
    }
};
```

### Overdue Notices Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overdue_notices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            
            $table->string('notice_number')->unique();
            $table->string('type'); // OverdueNoticeType enum (reminder, warning, final)
            $table->integer('notice_level')->default(1);
            
            $table->decimal('overdue_amount', 15, 2);
            $table->integer('days_overdue');
            
            $table->string('delivery_method'); // email, sms, both
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            
            $table->text('message')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('uuid');
            $table->index(['credit_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overdue_notices');
    }
};
```

---

## 3. Enums

### CreditAccountStatus Enum

**File:** `app/Enums/CreditAccountStatus.php`

```php
<?php

namespace App\Enums;

enum CreditAccountStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::CLOSED => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'danger',
            self::CLOSED => 'secondary',
        };
    }

    public function canMakePurchases(): bool
    {
        return $this === self::ACTIVE;
    }
}
```

### CreditTransactionType Enum

**File:** `app/Enums/CreditTransactionType.php`

```php
<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case PURCHASE = 'purchase';
    case PAYMENT = 'payment';
    case ADJUSTMENT_CREDIT = 'adjustment_credit';
    case ADJUSTMENT_DEBIT = 'adjustment_debit';
    case REFUND = 'refund';
    case WRITE_OFF = 'write_off';
    case OPENING_BALANCE = 'opening_balance';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::PAYMENT => 'Payment',
            self::ADJUSTMENT_CREDIT => 'Credit Adjustment',
            self::ADJUSTMENT_DEBIT => 'Debit Adjustment',
            self::REFUND => 'Refund',
            self::WRITE_OFF => 'Write Off',
            self::OPENING_BALANCE => 'Opening Balance',
        };
    }

    public function isDebit(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::ADJUSTMENT_DEBIT,
            self::OPENING_BALANCE,
        ]);
    }

    public function isCredit(): bool
    {
        return in_array($this, [
            self::PAYMENT,
            self::ADJUSTMENT_CREDIT,
            self::REFUND,
            self::WRITE_OFF,
        ]);
    }
}
```

### CreditRequestStatus Enum

**File:** `app/Enums/CreditRequestStatus.php`

```php
<?php

namespace App\Enums;

enum CreditRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PARTIALLY_APPROVED = 'partially_approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::PARTIALLY_APPROVED => 'Partially Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::PARTIALLY_APPROVED => 'info',
            self::REJECTED => 'danger',
            self::CANCELLED => 'secondary',
        };
    }
}
```

### OverdueNoticeType Enum

**File:** `app/Enums/OverdueNoticeType.php`

```php
<?php

namespace App\Enums;

enum OverdueNoticeType: string
{
    case REMINDER = 'reminder';
    case WARNING = 'warning';
    case FINAL_NOTICE = 'final_notice';
    case SUSPENSION = 'suspension';

    public function label(): string
    {
        return match ($this) {
            self::REMINDER => 'Payment Reminder',
            self::WARNING => 'Overdue Warning',
            self::FINAL_NOTICE => 'Final Notice',
            self::SUSPENSION => 'Account Suspension',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::REMINDER => 1,
            self::WARNING => 2,
            self::FINAL_NOTICE => 3,
            self::SUSPENSION => 4,
        };
    }
}
```

---

## 4. Models

### CreditAccount Model

**File:** `app/Models/CreditAccount.php`

```php
<?php

namespace App\Models;

use App\Enums\CreditAccountStatus;
use App\Enums\CreditTransactionType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CreditAccount extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'uuid',
        'customer_id',
        'shop_id',
        'credit_limit',
        'current_balance',
        'available_credit',
        'status',
        'is_suspended',
        'suspension_reason',
        'payment_terms_days',
        'grace_period_days',
        'limit_updated_at',
        'limit_updated_by',
        'last_purchase_at',
        'last_payment_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditAccountStatus::class,
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'available_credit' => 'decimal:2',
            'is_suspended' => 'boolean',
            'limit_updated_at' => 'datetime',
            'last_purchase_at' => 'datetime',
            'last_payment_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreditAccount $account) {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
            if (empty($account->status)) {
                $account->status = CreditAccountStatus::PENDING;
            }
            $account->available_credit = $account->credit_limit - $account->current_balance;
            $account->created_by = auth()->id();
        });

        static::updating(function (CreditAccount $account) {
            $account->available_credit = max(0, $account->credit_limit - $account->current_balance);
            $account->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function limitRequests(): HasMany
    {
        return $this->hasMany(CreditLimitRequest::class);
    }

    public function overdueNotices(): HasMany
    {
        return $this->hasMany(OverdueNotice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function limitUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'limit_updated_by');
    }

    // Methods

    public function canMakePurchase(float $amount): bool
    {
        if (!$this->status->canMakePurchases()) {
            return false;
        }

        if ($this->is_suspended) {
            return false;
        }

        return $this->available_credit >= $amount;
    }

    public function recordPurchase(Sale $sale): CreditTransaction
    {
        $transaction = $this->transactions()->create([
            'customer_id' => $this->customer_id,
            'shop_id' => $this->shop_id,
            'type' => CreditTransactionType::PURCHASE,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'debit' => $sale->total_amount,
            'credit' => 0,
            'balance_before' => $this->current_balance,
            'balance_after' => $this->current_balance + $sale->total_amount,
            'due_date' => now()->addDays($this->payment_terms_days),
            'description' => "Credit purchase - Invoice #{$sale->invoice_number}",
        ]);

        $this->current_balance += $sale->total_amount;
        $this->last_purchase_at = now();
        $this->save();

        return $transaction;
    }

    public function recordPayment(float $amount, ?int $referenceId = null, ?string $description = null): CreditTransaction
    {
        $transaction = $this->transactions()->create([
            'customer_id' => $this->customer_id,
            'shop_id' => $this->shop_id,
            'type' => CreditTransactionType::PAYMENT,
            'reference_type' => $referenceId ? \App\Models\CreditPayment::class : null,
            'reference_id' => $referenceId,
            'debit' => 0,
            'credit' => $amount,
            'balance_before' => $this->current_balance,
            'balance_after' => max(0, $this->current_balance - $amount),
            'description' => $description ?? 'Credit payment received',
        ]);

        $this->current_balance = max(0, $this->current_balance - $amount);
        $this->last_payment_at = now();
        $this->save();

        return $transaction;
    }

    public function adjustBalance(float $amount, bool $isCredit, string $reason): CreditTransaction
    {
        $type = $isCredit
            ? CreditTransactionType::ADJUSTMENT_CREDIT
            : CreditTransactionType::ADJUSTMENT_DEBIT;

        $newBalance = $isCredit
            ? max(0, $this->current_balance - abs($amount))
            : $this->current_balance + abs($amount);

        $transaction = $this->transactions()->create([
            'customer_id' => $this->customer_id,
            'shop_id' => $this->shop_id,
            'type' => $type,
            'debit' => $isCredit ? 0 : abs($amount),
            'credit' => $isCredit ? abs($amount) : 0,
            'balance_before' => $this->current_balance,
            'balance_after' => $newBalance,
            'description' => $reason,
        ]);

        $this->current_balance = $newBalance;
        $this->save();

        return $transaction;
    }

    public function updateCreditLimit(float $newLimit, ?string $reason = null): void
    {
        $this->credit_limit = $newLimit;
        $this->limit_updated_at = now();
        $this->limit_updated_by = auth()->id();
        $this->save();
    }

    public function suspend(string $reason): void
    {
        $this->is_suspended = true;
        $this->suspension_reason = $reason;
        $this->status = CreditAccountStatus::SUSPENDED;
        $this->save();
    }

    public function unsuspend(): void
    {
        $this->is_suspended = false;
        $this->suspension_reason = null;
        $this->status = CreditAccountStatus::ACTIVE;
        $this->save();
    }

    public function activate(): void
    {
        $this->status = CreditAccountStatus::ACTIVE;
        $this->save();
    }

    public function getOverdueTransactions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->transactions()
            ->where('type', CreditTransactionType::PURCHASE)
            ->where('due_date', '<', now())
            ->where('balance_after', '>', 0)
            ->orderBy('due_date')
            ->get();
    }

    public function getOverdueAmount(): float
    {
        return $this->transactions()
            ->where('type', CreditTransactionType::PURCHASE)
            ->where('due_date', '<', now())
            ->sum('debit') - $this->transactions()
            ->where('type', CreditTransactionType::PAYMENT)
            ->sum('credit');
    }

    public function getUtilizationPercentage(): float
    {
        if ($this->credit_limit <= 0) {
            return 0;
        }

        return round(($this->current_balance / $this->credit_limit) * 100, 2);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', CreditAccountStatus::ACTIVE);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('current_balance', '>', 0);
    }

    public function scopeOverLimit($query)
    {
        return $query->whereColumn('current_balance', '>', 'credit_limit');
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeHighUtilization($query, float $threshold = 80)
    {
        return $query->whereRaw('(current_balance / NULLIF(credit_limit, 0)) * 100 >= ?', [$threshold]);
    }
}
```

### CreditTransaction Model

**File:** `app/Models/CreditTransaction.php`

```php
<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class CreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'credit_account_id',
        'customer_id',
        'shop_id',
        'transaction_number',
        'type',
        'reference_type',
        'reference_id',
        'debit',
        'credit',
        'balance_before',
        'balance_after',
        'due_date',
        'is_overdue',
        'days_overdue',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'due_date' => 'date',
            'is_overdue' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreditTransaction $transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = (string) Str::uuid();
            }
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = self::generateTransactionNumber();
            }
            $transaction->created_by = auth()->id();
        });

        static::updating(function (CreditTransaction $transaction) {
            $transaction->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateTransactionNumber(): string
    {
        $prefix = 'CTX';
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())->count() + 1;
        
        return "{$prefix}-{$date}-" . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Methods

    public function updateOverdueStatus(): void
    {
        if ($this->due_date && $this->due_date->isPast() && $this->type === CreditTransactionType::PURCHASE) {
            $this->is_overdue = true;
            $this->days_overdue = $this->due_date->diffInDays(now());
            $this->save();
        }
    }

    public function getAmountAttribute(): float
    {
        return $this->type->isDebit() ? $this->debit : $this->credit;
    }

    // Scopes

    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('credit_account_id', $accountId);
    }
}
```

### CreditLimitRequest Model

**File:** `app/Models/CreditLimitRequest.php`

```php
<?php

namespace App\Models;

use App\Enums\CreditRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditLimitRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'credit_account_id',
        'customer_id',
        'shop_id',
        'request_number',
        'current_limit',
        'requested_limit',
        'justification',
        'status',
        'approved_limit',
        'approval_notes',
        'reviewed_at',
        'reviewed_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditRequestStatus::class,
            'current_limit' => 'decimal:2',
            'requested_limit' => 'decimal:2',
            'approved_limit' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreditLimitRequest $request) {
            if (empty($request->uuid)) {
                $request->uuid = (string) Str::uuid();
            }
            if (empty($request->request_number)) {
                $request->request_number = self::generateRequestNumber();
            }
            if (empty($request->status)) {
                $request->status = CreditRequestStatus::PENDING;
            }
            $request->created_by = auth()->id();
        });

        static::updating(function (CreditLimitRequest $request) {
            $request->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateRequestNumber(): string
    {
        $prefix = 'CLR';
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())->count() + 1;
        
        return "{$prefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Methods

    public function approve(float $approvedLimit, ?string $notes = null): void
    {
        $this->status = $approvedLimit >= $this->requested_limit
            ? CreditRequestStatus::APPROVED
            : CreditRequestStatus::PARTIALLY_APPROVED;
        $this->approved_limit = $approvedLimit;
        $this->approval_notes = $notes;
        $this->reviewed_at = now();
        $this->reviewed_by = auth()->id();
        $this->save();

        // Update credit account
        $this->creditAccount->updateCreditLimit($approvedLimit);
    }

    public function reject(string $reason): void
    {
        $this->status = CreditRequestStatus::REJECTED;
        $this->approval_notes = $reason;
        $this->reviewed_at = now();
        $this->reviewed_by = auth()->id();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = CreditRequestStatus::CANCELLED;
        $this->save();
    }

    public function getIncreaseAmountAttribute(): float
    {
        return $this->requested_limit - $this->current_limit;
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', CreditRequestStatus::PENDING);
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }
}
```

### OverdueNotice Model

**File:** `app/Models/OverdueNotice.php`

```php
<?php

namespace App\Models;

use App\Enums\OverdueNoticeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OverdueNotice extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'credit_account_id',
        'customer_id',
        'notice_number',
        'type',
        'notice_level',
        'overdue_amount',
        'days_overdue',
        'delivery_method',
        'is_sent',
        'sent_at',
        'is_acknowledged',
        'acknowledged_at',
        'message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OverdueNoticeType::class,
            'overdue_amount' => 'decimal:2',
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'is_acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OverdueNotice $notice) {
            if (empty($notice->uuid)) {
                $notice->uuid = (string) Str::uuid();
            }
            if (empty($notice->notice_number)) {
                $notice->notice_number = self::generateNoticeNumber();
            }
            $notice->notice_level = $notice->type->level();
            $notice->created_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateNoticeNumber(): string
    {
        $prefix = 'ODN';
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())->count() + 1;
        
        return "{$prefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Methods

    public function markAsSent(): void
    {
        $this->is_sent = true;
        $this->sent_at = now();
        $this->save();
    }

    public function markAsAcknowledged(): void
    {
        $this->is_acknowledged = true;
        $this->acknowledged_at = now();
        $this->save();
    }
}
```

---

## 5. Actions

### CreateCreditAccountAction

**File:** `app/Actions/Credit/CreateCreditAccountAction.php`

```php
<?php

namespace App\Actions\Credit;

use App\Enums\CreditAccountStatus;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class CreateCreditAccountAction
{
    public function execute(
        Customer $customer,
        Shop $shop,
        float $creditLimit = 0,
        int $paymentTermsDays = 30,
        int $gracePeriodDays = 7
    ): CreditAccount {
        return DB::transaction(function () use ($customer, $shop, $creditLimit, $paymentTermsDays, $gracePeriodDays) {
            return CreditAccount::create([
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'credit_limit' => $creditLimit,
                'current_balance' => 0,
                'available_credit' => $creditLimit,
                'status' => $creditLimit > 0 ? CreditAccountStatus::ACTIVE : CreditAccountStatus::PENDING,
                'payment_terms_days' => $paymentTermsDays,
                'grace_period_days' => $gracePeriodDays,
            ]);
        });
    }
}
```

### ProcessCreditSaleAction

**File:** `app/Actions/Credit/ProcessCreditSaleAction.php`

```php
<?php

namespace App\Actions\Credit;

use App\Enums\SaleType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class ProcessCreditSaleAction
{
    public function execute(CreditAccount $account, Sale $sale): CreditTransaction
    {
        return DB::transaction(function () use ($account, $sale) {
            if (!$account->canMakePurchase($sale->total_amount)) {
                throw new \Exception('Insufficient credit available.');
            }

            // Mark sale as credit
            $sale->sale_type = SaleType::CREDIT;
            $sale->save();

            // Record the purchase in credit account
            return $account->recordPurchase($sale);
        });
    }
}
```

### ProcessCreditPaymentAction

**File:** `app/Actions/Credit/ProcessCreditPaymentAction.php`

```php
<?php

namespace App\Actions\Credit;

use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;

class ProcessCreditPaymentAction
{
    public function execute(CreditAccount $account, CreditPayment $payment): CreditTransaction
    {
        return DB::transaction(function () use ($account, $payment) {
            return $account->recordPayment(
                $payment->amount,
                $payment->id,
                "Payment received - {$payment->payment_number}"
            );
        });
    }
}
```

### AdjustCreditBalanceAction

**File:** `app/Actions/Credit/AdjustCreditBalanceAction.php`

```php
<?php

namespace App\Actions\Credit;

use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;

class AdjustCreditBalanceAction
{
    public function execute(
        CreditAccount $account,
        float $amount,
        bool $isCredit,
        string $reason
    ): CreditTransaction {
        return DB::transaction(function () use ($account, $amount, $isCredit, $reason) {
            return $account->adjustBalance($amount, $isCredit, $reason);
        });
    }
}
```

### UpdateCreditLimitAction

**File:** `app/Actions/Credit/UpdateCreditLimitAction.php`

```php
<?php

namespace App\Actions\Credit;

use App\Models\CreditAccount;
use Illuminate\Support\Facades\DB;

class UpdateCreditLimitAction
{
    public function execute(CreditAccount $account, float $newLimit, ?string $reason = null): CreditAccount
    {
        return DB::transaction(function () use ($account, $newLimit, $reason) {
            $account->updateCreditLimit($newLimit, $reason);
            return $account->refresh();
        });
    }
}
```

### SendOverdueNoticeAction

**File:** `app/Actions/Credit/SendOverdueNoticeAction.php`

```php
<?php

namespace App\Actions\Credit;

use App\Enums\OverdueNoticeType;
use App\Models\CreditAccount;
use App\Models\OverdueNotice;
use App\Notifications\OverduePaymentNotification;
use Illuminate\Support\Facades\DB;

class SendOverdueNoticeAction
{
    public function execute(
        CreditAccount $account,
        OverdueNoticeType $type,
        string $deliveryMethod = 'email'
    ): OverdueNotice {
        return DB::transaction(function () use ($account, $type, $deliveryMethod) {
            $overdueAmount = $account->getOverdueAmount();
            $daysOverdue = $account->getOverdueTransactions()->max(function ($tx) {
                return $tx->due_date ? $tx->due_date->diffInDays(now()) : 0;
            }) ?? 0;

            $notice = OverdueNotice::create([
                'credit_account_id' => $account->id,
                'customer_id' => $account->customer_id,
                'type' => $type,
                'overdue_amount' => $overdueAmount,
                'days_overdue' => $daysOverdue,
                'delivery_method' => $deliveryMethod,
                'message' => $this->generateMessage($account, $type, $overdueAmount),
            ]);

            // Send notification
            $account->customer->notify(new OverduePaymentNotification($notice));

            $notice->markAsSent();

            // Auto-suspend if final notice
            if ($type === OverdueNoticeType::SUSPENSION) {
                $account->suspend('Automatic suspension due to overdue payment');
            }

            return $notice;
        });
    }

    private function generateMessage(CreditAccount $account, OverdueNoticeType $type, float $amount): string
    {
        $customer = $account->customer->name;
        $formattedAmount = number_format($amount, 2);

        return match ($type) {
            OverdueNoticeType::REMINDER => "Dear {$customer}, this is a friendly reminder that you have an outstanding balance of {$formattedAmount}. Please make payment at your earliest convenience.",
            OverdueNoticeType::WARNING => "Dear {$customer}, your account is overdue with a balance of {$formattedAmount}. Please settle this amount to avoid account suspension.",
            OverdueNoticeType::FINAL_NOTICE => "Dear {$customer}, this is your final notice. Your overdue balance of {$formattedAmount} must be paid immediately to prevent account suspension.",
            OverdueNoticeType::SUSPENSION => "Dear {$customer}, your credit account has been suspended due to an overdue balance of {$formattedAmount}. Please contact us to resolve this matter.",
        };
    }
}
```

---

## 6. Services

### CreditService

**File:** `app/Services/CreditService.php`

```php
<?php

namespace App\Services;

use App\Actions\Credit\AdjustCreditBalanceAction;
use App\Actions\Credit\CreateCreditAccountAction;
use App\Actions\Credit\ProcessCreditPaymentAction;
use App\Actions\Credit\ProcessCreditSaleAction;
use App\Actions\Credit\SendOverdueNoticeAction;
use App\Actions\Credit\UpdateCreditLimitAction;
use App\Enums\CreditAccountStatus;
use App\Enums\OverdueNoticeType;
use App\Models\CreditAccount;
use App\Models\CreditLimitRequest;
use App\Models\CreditPayment;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CreditService
{
    public function __construct(
        private CreateCreditAccountAction $createAccount,
        private ProcessCreditSaleAction $processSale,
        private ProcessCreditPaymentAction $processPayment,
        private AdjustCreditBalanceAction $adjustBalance,
        private UpdateCreditLimitAction $updateLimit,
        private SendOverdueNoticeAction $sendNotice
    ) {}

    /**
     * Create credit account for customer
     */
    public function createAccount(
        Customer $customer,
        Shop $shop,
        float $creditLimit = 0,
        int $paymentTermsDays = 30
    ): CreditAccount {
        // Check if account already exists
        $existing = CreditAccount::where('customer_id', $customer->id)
            ->where('shop_id', $shop->id)
            ->first();

        if ($existing) {
            throw new \Exception('Credit account already exists for this customer.');
        }

        return $this->createAccount->execute($customer, $shop, $creditLimit, $paymentTermsDays);
    }

    /**
     * Get or create credit account
     */
    public function getOrCreateAccount(Customer $customer, Shop $shop): CreditAccount
    {
        return CreditAccount::firstOrCreate(
            ['customer_id' => $customer->id, 'shop_id' => $shop->id],
            [
                'credit_limit' => 0,
                'current_balance' => 0,
                'status' => CreditAccountStatus::PENDING,
                'payment_terms_days' => 30,
                'grace_period_days' => 7,
            ]
        );
    }

    /**
     * Process a credit sale
     */
    public function processCreditSale(Customer $customer, Shop $shop, Sale $sale): CreditTransaction
    {
        $account = $this->getOrCreateAccount($customer, $shop);

        if (!$account->status->canMakePurchases()) {
            throw new \Exception('Credit account is not active.');
        }

        if (!$account->canMakePurchase($sale->total_amount)) {
            throw new \Exception(
                "Insufficient credit. Available: {$account->available_credit}, Required: {$sale->total_amount}"
            );
        }

        return $this->processSale->execute($account, $sale);
    }

    /**
     * Process credit payment
     */
    public function processCreditPayment(CreditPayment $payment): CreditTransaction
    {
        $account = CreditAccount::where('customer_id', $payment->customer_id)
            ->where('shop_id', $payment->shop_id)
            ->firstOrFail();

        return $this->processPayment->execute($account, $payment);
    }

    /**
     * Update credit limit
     */
    public function updateCreditLimit(CreditAccount $account, float $newLimit, ?string $reason = null): CreditAccount
    {
        if ($newLimit < 0) {
            throw new \InvalidArgumentException('Credit limit cannot be negative.');
        }

        return $this->updateLimit->execute($account, $newLimit, $reason);
    }

    /**
     * Request credit limit increase
     */
    public function requestLimitIncrease(
        CreditAccount $account,
        float $requestedLimit,
        string $justification
    ): CreditLimitRequest {
        if ($requestedLimit <= $account->credit_limit) {
            throw new \InvalidArgumentException('Requested limit must be higher than current limit.');
        }

        return CreditLimitRequest::create([
            'credit_account_id' => $account->id,
            'customer_id' => $account->customer_id,
            'shop_id' => $account->shop_id,
            'current_limit' => $account->credit_limit,
            'requested_limit' => $requestedLimit,
            'justification' => $justification,
        ]);
    }

    /**
     * Approve limit request
     */
    public function approveLimitRequest(
        CreditLimitRequest $request,
        float $approvedLimit,
        ?string $notes = null
    ): CreditLimitRequest {
        $request->approve($approvedLimit, $notes);
        return $request->refresh();
    }

    /**
     * Reject limit request
     */
    public function rejectLimitRequest(CreditLimitRequest $request, string $reason): CreditLimitRequest
    {
        $request->reject($reason);
        return $request->refresh();
    }

    /**
     * Adjust balance (credit or debit)
     */
    public function adjustBalance(
        CreditAccount $account,
        float $amount,
        bool $isCredit,
        string $reason
    ): CreditTransaction {
        return $this->adjustBalance->execute($account, $amount, $isCredit, $reason);
    }

    /**
     * Suspend account
     */
    public function suspendAccount(CreditAccount $account, string $reason): CreditAccount
    {
        $account->suspend($reason);
        return $account->refresh();
    }

    /**
     * Reactivate account
     */
    public function reactivateAccount(CreditAccount $account): CreditAccount
    {
        $account->unsuspend();
        return $account->refresh();
    }

    /**
     * Get overdue accounts
     */
    public function getOverdueAccounts(?Shop $shop = null): Collection
    {
        $query = CreditAccount::with(['customer', 'shop'])
            ->active()
            ->withBalance()
            ->whereHas('transactions', function ($q) {
                $q->where('due_date', '<', now())
                  ->where('type', 'purchase');
            });

        if ($shop) {
            $query->forShop($shop->id);
        }

        return $query->get();
    }

    /**
     * Process overdue notices
     */
    public function processOverdueNotices(): array
    {
        $overdueAccounts = $this->getOverdueAccounts();
        $notices = [];

        foreach ($overdueAccounts as $account) {
            $daysOverdue = $account->getOverdueTransactions()
                ->max(fn ($tx) => $tx->due_date?->diffInDays(now()) ?? 0);

            $noticeType = match (true) {
                $daysOverdue >= 60 => OverdueNoticeType::SUSPENSION,
                $daysOverdue >= 45 => OverdueNoticeType::FINAL_NOTICE,
                $daysOverdue >= 30 => OverdueNoticeType::WARNING,
                $daysOverdue >= 7 => OverdueNoticeType::REMINDER,
                default => null,
            };

            if ($noticeType) {
                // Check if notice of this type was already sent recently
                $recentNotice = $account->overdueNotices()
                    ->where('type', $noticeType)
                    ->where('created_at', '>', now()->subDays(7))
                    ->exists();

                if (!$recentNotice) {
                    $notices[] = $this->sendNotice->execute($account, $noticeType);
                }
            }
        }

        return $notices;
    }

    /**
     * Get account statement
     */
    public function getAccountStatement(
        CreditAccount $account,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(3);
        $endDate = $endDate ?? now();

        $transactions = $account->transactions()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        $openingBalance = $account->transactions()
            ->where('created_at', '<', $startDate)
            ->sum('debit') - $account->transactions()
            ->where('created_at', '<', $startDate)
            ->sum('credit');

        return [
            'account' => $account->load(['customer', 'shop']),
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'total_debits' => $transactions->sum('debit'),
            'total_credits' => $transactions->sum('credit'),
            'closing_balance' => $account->current_balance,
        ];
    }

    /**
     * Get credit summary for shop
     */
    public function getCreditSummary(?Shop $shop = null): array
    {
        $query = CreditAccount::query();
        
        if ($shop) {
            $query->forShop($shop->id);
        }

        return [
            'total_accounts' => (clone $query)->count(),
            'active_accounts' => (clone $query)->active()->count(),
            'suspended_accounts' => (clone $query)->where('status', CreditAccountStatus::SUSPENDED)->count(),
            'total_credit_extended' => (clone $query)->sum('credit_limit'),
            'total_outstanding' => (clone $query)->sum('current_balance'),
            'total_available' => (clone $query)->sum('available_credit'),
            'accounts_at_limit' => (clone $query)->whereColumn('current_balance', '>=', 'credit_limit')->count(),
            'overdue_accounts' => $this->getOverdueAccounts($shop)->count(),
            'total_overdue_amount' => $this->getOverdueAccounts($shop)->sum(fn ($a) => $a->getOverdueAmount()),
        ];
    }

    /**
     * Get customers approaching credit limit
     */
    public function getHighUtilizationAccounts(?Shop $shop = null, float $threshold = 80): Collection
    {
        return CreditAccount::with(['customer'])
            ->active()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->highUtilization($threshold)
            ->orderByDesc('current_balance')
            ->get();
    }

    /**
     * Get aging report
     */
    public function getAgingReport(?Shop $shop = null): array
    {
        $accounts = CreditAccount::with(['customer', 'transactions'])
            ->active()
            ->withBalance()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->get();

        $aging = [
            'current' => 0,
            '1_30_days' => 0,
            '31_60_days' => 0,
            '61_90_days' => 0,
            'over_90_days' => 0,
        ];

        foreach ($accounts as $account) {
            $overdueTransactions = $account->getOverdueTransactions();

            foreach ($overdueTransactions as $tx) {
                $daysOverdue = $tx->due_date->diffInDays(now());
                $amount = $tx->debit;

                match (true) {
                    $daysOverdue <= 0 => $aging['current'] += $amount,
                    $daysOverdue <= 30 => $aging['1_30_days'] += $amount,
                    $daysOverdue <= 60 => $aging['31_60_days'] += $amount,
                    $daysOverdue <= 90 => $aging['61_90_days'] += $amount,
                    default => $aging['over_90_days'] += $amount,
                };
            }
        }

        return $aging;
    }
}
```

---

## 7. Controllers

### CreditAccountController

**File:** `app/Http/Controllers/CreditAccountController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCreditAccountRequest;
use App\Http\Requests\UpdateCreditLimitRequest;
use App\Http\Requests\AdjustCreditBalanceRequest;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditAccountController extends Controller
{
    public function __construct(
        private CreditService $creditService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditAccount::class);

        $shopId = $request->get('shop_id');
        $status = $request->get('status');
        $hasBalance = $request->boolean('has_balance');

        $accounts = CreditAccount::with(['customer', 'shop'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($hasBalance, fn ($q) => $q->withBalance())
            ->orderByDesc('current_balance')
            ->paginate(30);

        $shops = Shop::active()->get();
        $summary = $this->creditService->getCreditSummary(
            $shopId ? Shop::find($shopId) : null
        );

        return view('credit.accounts.index', compact('accounts', 'shops', 'summary'));
    }

    public function create(Customer $customer): View
    {
        $this->authorize('create', CreditAccount::class);

        $customer->load('shop');

        return view('credit.accounts.create', compact('customer'));
    }

    public function store(CreateCreditAccountRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $account = $this->creditService->createAccount(
                $customer,
                $customer->shop,
                $request->credit_limit,
                $request->payment_terms_days ?? 30
            );

            return redirect()->route('credit.accounts.show', $account->uuid)
                ->with('success', 'Credit account created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(CreditAccount $creditAccount): View
    {
        $this->authorize('view', $creditAccount);

        $creditAccount->load(['customer', 'shop', 'transactions' => fn ($q) => $q->latest()->limit(20)]);
        $overdueAmount = $creditAccount->getOverdueAmount();

        return view('credit.accounts.show', compact('creditAccount', 'overdueAmount'));
    }

    public function updateLimit(UpdateCreditLimitRequest $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('updateLimit', $creditAccount);

        try {
            $this->creditService->updateCreditLimit(
                $creditAccount,
                $request->credit_limit,
                $request->reason
            );

            return back()->with('success', 'Credit limit updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function adjustBalance(AdjustCreditBalanceRequest $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('adjust', $creditAccount);

        try {
            $this->creditService->adjustBalance(
                $creditAccount,
                $request->amount,
                $request->adjustment_type === 'credit',
                $request->reason
            );

            return back()->with('success', 'Balance adjusted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function suspend(Request $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('suspend', $creditAccount);

        $request->validate(['reason' => 'required|string|max:500']);

        $this->creditService->suspendAccount($creditAccount, $request->reason);

        return back()->with('success', 'Account suspended.');
    }

    public function reactivate(CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('reactivate', $creditAccount);

        $this->creditService->reactivateAccount($creditAccount);

        return back()->with('success', 'Account reactivated.');
    }

    public function statement(Request $request, CreditAccount $creditAccount): View
    {
        $this->authorize('viewStatement', $creditAccount);

        $startDate = $request->get('start_date')
            ? \Carbon\Carbon::parse($request->get('start_date'))
            : now()->subMonths(3);
        $endDate = $request->get('end_date')
            ? \Carbon\Carbon::parse($request->get('end_date'))
            : now();

        $statement = $this->creditService->getAccountStatement($creditAccount, $startDate, $endDate);

        return view('credit.accounts.statement', compact('statement'));
    }

    public function overdue(Request $request): View
    {
        $this->authorize('viewOverdue', CreditAccount::class);

        $shopId = $request->get('shop_id');
        $shop = $shopId ? Shop::find($shopId) : null;

        $overdueAccounts = $this->creditService->getOverdueAccounts($shop);
        $agingReport = $this->creditService->getAgingReport($shop);
        $shops = Shop::active()->get();

        return view('credit.accounts.overdue', compact('overdueAccounts', 'agingReport', 'shops'));
    }
}
```

### CreditLimitRequestController

**File:** `app/Http/Controllers/CreditLimitRequestController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditLimitRequest;
use App\Http\Requests\ReviewCreditLimitRequest;
use App\Models\CreditAccount;
use App\Models\CreditLimitRequest;
use App\Models\Shop;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditLimitRequestController extends Controller
{
    public function __construct(
        private CreditService $creditService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditLimitRequest::class);

        $shopId = $request->get('shop_id');
        $status = $request->get('status');

        $requests = CreditLimitRequest::with(['customer', 'shop', 'creditAccount', 'reviewedBy'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30);

        $shops = Shop::active()->get();
        $pendingCount = CreditLimitRequest::pending()->count();

        return view('credit.requests.index', compact('requests', 'shops', 'pendingCount'));
    }

    public function create(CreditAccount $creditAccount): View
    {
        $this->authorize('create', CreditLimitRequest::class);

        $creditAccount->load(['customer', 'shop']);

        return view('credit.requests.create', compact('creditAccount'));
    }

    public function store(StoreCreditLimitRequest $request, CreditAccount $creditAccount): RedirectResponse
    {
        try {
            $creditRequest = $this->creditService->requestLimitIncrease(
                $creditAccount,
                $request->requested_limit,
                $request->justification
            );

            return redirect()->route('credit.requests.show', $creditRequest->uuid)
                ->with('success', 'Credit limit request submitted for approval.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(CreditLimitRequest $creditLimitRequest): View
    {
        $this->authorize('view', $creditLimitRequest);

        $creditLimitRequest->load(['creditAccount', 'customer', 'shop', 'reviewedBy', 'createdBy']);

        return view('credit.requests.show', compact('creditLimitRequest'));
    }

    public function approve(ReviewCreditLimitRequest $request, CreditLimitRequest $creditLimitRequest): RedirectResponse
    {
        $this->authorize('approve', $creditLimitRequest);

        try {
            $this->creditService->approveLimitRequest(
                $creditLimitRequest,
                $request->approved_limit,
                $request->notes
            );

            return redirect()->route('credit.requests.index')
                ->with('success', 'Credit limit request approved.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, CreditLimitRequest $creditLimitRequest): RedirectResponse
    {
        $this->authorize('reject', $creditLimitRequest);

        $request->validate(['reason' => 'required|string|max:500']);

        $this->creditService->rejectLimitRequest($creditLimitRequest, $request->reason);

        return redirect()->route('credit.requests.index')
            ->with('success', 'Credit limit request rejected.');
    }
}
```

---

## 8. Form Requests

### CreateCreditAccountRequest

**File:** `app/Http/Requests/CreateCreditAccountRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCreditAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\CreditAccount::class);
    }

    public function rules(): array
    {
        return [
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ];
    }
}
```

### UpdateCreditLimitRequest

**File:** `app/Http/Requests/UpdateCreditLimitRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateLimit', $this->route('creditAccount'));
    }

    public function rules(): array
    {
        return [
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

### AdjustCreditBalanceRequest

**File:** `app/Http/Requests/AdjustCreditBalanceRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustCreditBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjust', $this->route('creditAccount'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'adjustment_type' => ['required', Rule::in(['credit', 'debit'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for the adjustment.',
            'reason.min' => 'Reason must be at least 10 characters.',
        ];
    }
}
```

### StoreCreditLimitRequest

**File:** `app/Http/Requests/StoreCreditLimitRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\CreditLimitRequest::class);
    }

    public function rules(): array
    {
        $account = $this->route('creditAccount');

        return [
            'requested_limit' => [
                'required',
                'numeric',
                'min:' . ($account->credit_limit + 1),
            ],
            'justification' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_limit.min' => 'Requested limit must be higher than current limit.',
            'justification.min' => 'Please provide a detailed justification (at least 20 characters).',
        ];
    }
}
```

### ReviewCreditLimitRequest

**File:** `app/Http/Requests/ReviewCreditLimitRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('creditLimitRequest'));
    }

    public function rules(): array
    {
        return [
            'approved_limit' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\CreditAccountController;
use App\Http\Controllers\CreditLimitRequestController;

Route::middleware(['auth'])->prefix('credit')->name('credit.')->group(function () {
    // Credit Accounts
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [CreditAccountController::class, 'index'])
            ->name('index')
            ->middleware('permission:credit.view');
        
        Route::get('/overdue', [CreditAccountController::class, 'overdue'])
            ->name('overdue')
            ->middleware('permission:credit.overdue.view');
        
        Route::get('/customer/{customer}', [CreditAccountController::class, 'create'])
            ->name('create')
            ->middleware('permission:credit.create');
        
        Route::post('/customer/{customer}', [CreditAccountController::class, 'store'])
            ->name('store')
            ->middleware('permission:credit.create');
        
        Route::get('/{creditAccount}', [CreditAccountController::class, 'show'])
            ->name('show')
            ->middleware('permission:credit.view');
        
        Route::get('/{creditAccount}/statement', [CreditAccountController::class, 'statement'])
            ->name('statement')
            ->middleware('permission:credit.statement.view');
        
        Route::patch('/{creditAccount}/limit', [CreditAccountController::class, 'updateLimit'])
            ->name('updateLimit')
            ->middleware('permission:credit.limit.update');
        
        Route::post('/{creditAccount}/adjust', [CreditAccountController::class, 'adjustBalance'])
            ->name('adjust')
            ->middleware('permission:credit.adjust');
        
        Route::post('/{creditAccount}/suspend', [CreditAccountController::class, 'suspend'])
            ->name('suspend')
            ->middleware('permission:credit.suspend');
        
        Route::post('/{creditAccount}/reactivate', [CreditAccountController::class, 'reactivate'])
            ->name('reactivate')
            ->middleware('permission:credit.reactivate');
    });

    // Credit Limit Requests
    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('/', [CreditLimitRequestController::class, 'index'])
            ->name('index')
            ->middleware('permission:credit.requests.view');
        
        Route::get('/account/{creditAccount}', [CreditLimitRequestController::class, 'create'])
            ->name('create')
            ->middleware('permission:credit.requests.create');
        
        Route::post('/account/{creditAccount}', [CreditLimitRequestController::class, 'store'])
            ->name('store')
            ->middleware('permission:credit.requests.create');
        
        Route::get('/{creditLimitRequest}', [CreditLimitRequestController::class, 'show'])
            ->name('show')
            ->middleware('permission:credit.requests.view');
        
        Route::post('/{creditLimitRequest}/approve', [CreditLimitRequestController::class, 'approve'])
            ->name('approve')
            ->middleware('permission:credit.requests.approve');
        
        Route::post('/{creditLimitRequest}/reject', [CreditLimitRequestController::class, 'reject'])
            ->name('reject')
            ->middleware('permission:credit.requests.reject');
    });
});
```

---

## 10. Permissions

```php
// Credit account permissions
'credit.view',
'credit.create',
'credit.limit.update',
'credit.adjust',
'credit.suspend',
'credit.reactivate',
'credit.statement.view',
'credit.overdue.view',
'credit.reports',

// Credit limit request permissions
'credit.requests.view',
'credit.requests.create',
'credit.requests.approve',
'credit.requests.reject',
```

---

## 11. Scheduled Commands

### UpdateOverdueStatusCommand

**File:** `app/Console/Commands/UpdateOverdueStatusCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use Illuminate\Console\Command;

class UpdateOverdueStatusCommand extends Command
{
    protected $signature = 'credit:update-overdue';
    protected $description = 'Update overdue status for credit transactions';

    public function handle(): int
    {
        $updated = CreditTransaction::where('type', CreditTransactionType::PURCHASE)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('is_overdue', false)
            ->get()
            ->each(fn ($tx) => $tx->updateOverdueStatus());

        $this->info("Updated {$updated->count()} transactions to overdue status.");

        return Command::SUCCESS;
    }
}
```

### ProcessOverdueNoticesCommand

**File:** `app/Console/Commands/ProcessOverdueNoticesCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\CreditService;
use Illuminate\Console\Command;

class ProcessOverdueNoticesCommand extends Command
{
    protected $signature = 'credit:process-overdue-notices';
    protected $description = 'Send overdue payment notices to customers';

    public function handle(CreditService $creditService): int
    {
        $notices = $creditService->processOverdueNotices();

        $this->info("Sent " . count($notices) . " overdue notices.");

        return Command::SUCCESS;
    }
}
```

### Schedule in bootstrap/app.php

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('credit:update-overdue')->daily();
    $schedule->command('credit:process-overdue-notices')->dailyAt('09:00');
})
```

---

## 12. UI Template Reference

| View | Template Source |
|------|-----------------|
| Credit Accounts List | `design/src/customers-list.php` |
| Account Details | `design/src/customer-detail.php` |
| Account Statement | `design/src/invoice.php` |
| Overdue Report | `design/src/reports-sales.php` |
| Aging Report | `design/src/analytics.php` |
| Limit Requests | `design/src/pending-orders.php` |

---

## 13. Tests

**File:** `tests/Feature/CreditSalesTest.php`

```php
<?php

use App\Enums\CreditAccountStatus;
use App\Enums\CreditRequestStatus;
use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditLimitRequest;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\CreditService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('credit account can be created for customer', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);

    $service = app(CreditService::class);
    $account = $service->createAccount($customer, $shop, 5000, 30);

    expect($account)
        ->credit_limit->toBe(5000.00)
        ->current_balance->toBe(0.00)
        ->available_credit->toBe(5000.00)
        ->status->toBe(CreditAccountStatus::ACTIVE);
});

test('credit account cannot be duplicated for same customer and shop', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);

    $service = app(CreditService::class);
    $service->createAccount($customer, $shop, 5000);

    expect(fn () => $service->createAccount($customer, $shop, 10000))
        ->toThrow(\Exception::class);
});

test('credit sale reduces available credit', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $account = CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
        'current_balance' => 0,
        'status' => CreditAccountStatus::ACTIVE,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'total_amount' => 1500,
    ]);

    $service = app(CreditService::class);
    $transaction = $service->processCreditSale($customer, $shop, $sale);

    $account->refresh();
    expect((float) $account->current_balance)->toBe(1500.00);
    expect((float) $account->available_credit)->toBe(3500.00);
    expect($transaction->type)->toBe(CreditTransactionType::PURCHASE);
});

test('credit sale fails when exceeding limit', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 1000,
        'current_balance' => 0,
        'status' => CreditAccountStatus::ACTIVE,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'total_amount' => 1500,
    ]);

    $service = app(CreditService::class);

    expect(fn () => $service->processCreditSale($customer, $shop, $sale))
        ->toThrow(\Exception::class);
});

test('credit payment reduces balance', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $account = CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
        'current_balance' => 2000,
    ]);

    $account->recordPayment(500, null, 'Test payment');

    expect((float) $account->fresh()->current_balance)->toBe(1500.00);
});

test('credit transactions create audit trail', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $account = CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
        'current_balance' => 0,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'total_amount' => 1000,
    ]);

    $account->recordPurchase($sale);
    $account->recordPayment(400);

    expect($account->transactions)->toHaveCount(2);
    expect($account->transactions->first()->type)->toBe(CreditTransactionType::PURCHASE);
    expect($account->transactions->last()->type)->toBe(CreditTransactionType::PAYMENT);
});

test('credit limit can be updated', function () {
    $shop = Shop::factory()->create();
    $account = CreditAccount::factory()->create([
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
    ]);

    $service = app(CreditService::class);
    $service->updateCreditLimit($account, 10000, 'Good payment history');

    expect((float) $account->fresh()->credit_limit)->toBe(10000.00);
});

test('credit limit request can be submitted', function () {
    $account = CreditAccount::factory()->create(['credit_limit' => 5000]);

    $service = app(CreditService::class);
    $request = $service->requestLimitIncrease($account, 10000, 'Need higher limit for bulk orders');

    expect($request)
        ->current_limit->toBe(5000.00)
        ->requested_limit->toBe(10000.00)
        ->status->toBe(CreditRequestStatus::PENDING);
});

test('approved limit request updates account', function () {
    $account = CreditAccount::factory()->create(['credit_limit' => 5000]);
    $request = CreditLimitRequest::factory()->create([
        'credit_account_id' => $account->id,
        'current_limit' => 5000,
        'requested_limit' => 10000,
        'status' => CreditRequestStatus::PENDING,
    ]);

    $service = app(CreditService::class);
    $service->approveLimitRequest($request, 8000, 'Approved partial increase');

    expect($request->fresh()->status)->toBe(CreditRequestStatus::PARTIALLY_APPROVED);
    expect((float) $account->fresh()->credit_limit)->toBe(8000.00);
});

test('account can be suspended and reactivated', function () {
    $account = CreditAccount::factory()->create(['status' => CreditAccountStatus::ACTIVE]);

    $service = app(CreditService::class);
    $service->suspendAccount($account, 'Overdue payment');

    expect($account->fresh())
        ->status->toBe(CreditAccountStatus::SUSPENDED)
        ->is_suspended->toBeTrue();

    $service->reactivateAccount($account);

    expect($account->fresh())
        ->status->toBe(CreditAccountStatus::ACTIVE)
        ->is_suspended->toBeFalse();
});

test('suspended account cannot make purchases', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    CreditAccount::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
        'status' => CreditAccountStatus::SUSPENDED,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'total_amount' => 1000,
    ]);

    $service = app(CreditService::class);

    expect(fn () => $service->processCreditSale($customer, $shop, $sale))
        ->toThrow(\Exception::class);
});

test('balance adjustment creates transaction', function () {
    $account = CreditAccount::factory()->create([
        'current_balance' => 2000,
    ]);

    $service = app(CreditService::class);
    $transaction = $service->adjustBalance($account, 500, true, 'Goodwill credit');

    expect($transaction->type)->toBe(CreditTransactionType::ADJUSTMENT_CREDIT);
    expect((float) $account->fresh()->current_balance)->toBe(1500.00);
});

test('utilization percentage is calculated correctly', function () {
    $account = CreditAccount::factory()->create([
        'credit_limit' => 10000,
        'current_balance' => 7500,
    ]);

    expect($account->getUtilizationPercentage())->toBe(75.00);
});
```

---

## 14. Commands to Execute

```bash
# Step 1: Create Enums
mkdir -p app/Enums
# Create CreditAccountStatus, CreditTransactionType, CreditRequestStatus, OverdueNoticeType

# Step 2: Create Models with migrations
php artisan make:model CreditAccount -mfs --no-interaction
php artisan make:model CreditTransaction -mf --no-interaction
php artisan make:model CreditLimitRequest -mf --no-interaction
php artisan make:model OverdueNotice -m --no-interaction

# Step 3: Create Actions
mkdir -p app/Actions/Credit
# Create all credit actions

# Step 4: Create Service
php artisan make:class Services/CreditService --no-interaction

# Step 5: Create Controllers
php artisan make:controller CreditAccountController --no-interaction
php artisan make:controller CreditLimitRequestController --no-interaction

# Step 6: Create Form Requests
php artisan make:request CreateCreditAccountRequest --no-interaction
php artisan make:request UpdateCreditLimitRequest --no-interaction
php artisan make:request AdjustCreditBalanceRequest --no-interaction
php artisan make:request StoreCreditLimitRequest --no-interaction
php artisan make:request ReviewCreditLimitRequest --no-interaction

# Step 7: Create Policies
php artisan make:policy CreditAccountPolicy --model=CreditAccount --no-interaction
php artisan make:policy CreditLimitRequestPolicy --model=CreditLimitRequest --no-interaction

# Step 8: Create Commands
php artisan make:command UpdateOverdueStatusCommand --no-interaction
php artisan make:command ProcessOverdueNoticesCommand --no-interaction

# Step 9: Create Notification
php artisan make:notification OverduePaymentNotification --no-interaction

# Step 10: Run migrations
php artisan migrate

# Step 11: Create tests
php artisan make:test CreditSalesTest --pest --no-interaction

# Step 12: Run tests
php artisan test --compact --filter=CreditSales

# Step 13: Format code
vendor/bin/pint --dirty
```

---

## 15. Verification Checklist

Before proceeding to Module 13, verify:

- [ ] All tables have `uuid` column with `getRouteKeyName()` on models
- [ ] All tables have audit columns (`created_by`, `updated_by`)
- [ ] Credit accounts link customers to shops with unique constraint
- [ ] Credit limit enforcement works correctly
- [ ] Credit transactions create complete audit trail
- [ ] Payment reduces balance and updates available credit
- [ ] Credit limit requests follow approval workflow
- [ ] Account suspension blocks new purchases
- [ ] Overdue tracking works with due dates
- [ ] Overdue notices are generated and sent
- [ ] Statement generation includes all transactions
- [ ] Aging report categorizes correctly
- [ ] All permissions follow `{module}.{action}` format
- [ ] Scheduled commands registered
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 16. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 13: Expense Categories](./13-expense-categories.md)**
