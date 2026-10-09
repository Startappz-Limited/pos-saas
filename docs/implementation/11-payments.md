# Module 11: Payments
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Complete payment processing system supporting multiple payment methods, partial payments, payment history, and integration with credit sales. Enables tracking of all financial transactions.

**Priority:** P0 (Critical)  
**Dependencies:** Module 10  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns, Enum status |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Auditable trait on Payment model |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Payments Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            
            // Payment details
            $table->string('payment_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // PaymentMethod enum
            $table->string('status'); // PaymentStatus enum
            
            // Method-specific details
            $table->string('reference_number')->nullable(); // Check #, transaction ID, etc.
            $table->string('bank_name')->nullable();
            $table->string('card_last_four')->nullable();
            $table->string('mobile_provider')->nullable(); // M-Pesa, etc.
            $table->string('mobile_number')->nullable();
            
            // Balance tracking
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            
            // Timestamps
            $table->timestamp('payment_date');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('uuid');
            $table->index('payment_number');
            $table->index(['sale_id', 'status']);
            $table->index(['shop_id', 'payment_date']);
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

### Payment Methods Table (Configurable per Shop)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method'); // PaymentMethod enum
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable(); // Method-specific configuration
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['shop_id', 'payment_method']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_payment_methods');
    }
};
```

### Credit Payments Table (For tracking credit repayments)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('payment_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->string('status');
            
            $table->string('reference_number')->nullable();
            $table->decimal('credit_balance_before', 15, 2);
            $table->decimal('credit_balance_after', 15, 2);
            
            $table->timestamp('payment_date');
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('uuid');
            $table->index(['customer_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
    }
};
```

---

## 3. Enums

### PaymentMethod Enum

**File:** `app/Enums/PaymentMethod.php`

```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';
    case MOBILE_MONEY = 'mobile_money';
    case CREDIT = 'credit';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CARD => 'Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CHEQUE => 'Cheque',
            self::MOBILE_MONEY => 'Mobile Money',
            self::CREDIT => 'Credit/On Account',
            self::OTHER => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CASH => 'ri-money-dollar-circle-line',
            self::CARD => 'ri-bank-card-line',
            self::BANK_TRANSFER => 'ri-bank-line',
            self::CHEQUE => 'ri-file-text-line',
            self::MOBILE_MONEY => 'ri-smartphone-line',
            self::CREDIT => 'ri-wallet-line',
            self::OTHER => 'ri-more-line',
        };
    }

    public function requiresReference(): bool
    {
        return in_array($this, [
            self::CARD,
            self::BANK_TRANSFER,
            self::CHEQUE,
            self::MOBILE_MONEY,
        ]);
    }

    public function requiresVerification(): bool
    {
        return in_array($this, [
            self::CHEQUE,
            self::BANK_TRANSFER,
        ]);
    }
}
```

### TransactionStatus Enum

**File:** `app/Enums/TransactionStatus.php`

```php
<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case VERIFIED = 'verified';
    case FAILED = 'failed';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::VERIFIED => 'Verified',
            self::FAILED => 'Failed',
            self::VOIDED => 'Voided',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::VERIFIED => 'primary',
            self::FAILED => 'danger',
            self::VOIDED => 'secondary',
            self::REFUNDED => 'info',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::COMPLETED, self::VERIFIED]);
    }
}
```

---

## 4. Models

### Payment Model

**File:** `app/Models/Payment.php`

```php
<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'uuid',
        'sale_id',
        'shop_id',
        'customer_id',
        'payment_number',
        'amount',
        'payment_method',
        'status',
        'reference_number',
        'bank_name',
        'card_last_four',
        'mobile_provider',
        'mobile_number',
        'balance_before',
        'balance_after',
        'payment_date',
        'verified_at',
        'verified_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'payment_date' => 'datetime',
            'verified_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->payment_number)) {
                $payment->payment_number = self::generatePaymentNumber($payment->shop_id);
            }
            if (empty($payment->status)) {
                $payment->status = $payment->payment_method->requiresVerification()
                    ? TransactionStatus::PENDING
                    : TransactionStatus::COMPLETED;
            }
            if (empty($payment->payment_date)) {
                $payment->payment_date = now();
            }
            $payment->created_by = auth()->id();
        });

        static::updating(function (Payment $payment) {
            $payment->updated_by = auth()->id();
        });

        static::created(function (Payment $payment) {
            if ($payment->status->isSuccessful()) {
                $payment->updateSalePayment();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generatePaymentNumber(int $shopId): string
    {
        $prefix = 'PAY';
        $shopPrefix = str_pad($shopId, 2, '0', STR_PAD_LEFT);
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())
            ->where('shop_id', $shopId)
            ->count() + 1;
        
        return "{$prefix}-{$shopPrefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // Methods

    public function updateSalePayment(): void
    {
        $sale = $this->sale;
        $sale->paid_amount = $sale->payments()
            ->whereIn('status', [TransactionStatus::COMPLETED, TransactionStatus::VERIFIED])
            ->sum('amount');
        $sale->recalculateTotals();
        $sale->save();

        // Update customer credit if payment reduces balance
        if ($sale->customer && $sale->isCreditSale()) {
            $sale->customer->reduceCredit($this->amount);
        }
    }

    public function verify(): bool
    {
        $this->status = TransactionStatus::VERIFIED;
        $this->verified_at = now();
        $this->verified_by = auth()->id();
        $saved = $this->save();

        if ($saved) {
            $this->updateSalePayment();
        }

        return $saved;
    }

    public function void(string $reason): bool
    {
        $previousStatus = $this->status;
        
        $this->status = TransactionStatus::VOIDED;
        $this->voided_at = now();
        $this->voided_by = auth()->id();
        $this->void_reason = $reason;
        $saved = $this->save();

        // Revert sale payment if was previously successful
        if ($saved && $previousStatus->isSuccessful()) {
            $this->updateSalePayment();
            
            // Restore customer credit
            if ($this->customer) {
                $this->customer->addToCredit($this->amount);
            }
        }

        return $saved;
    }

    public function getFormattedMethodAttribute(): string
    {
        $method = $this->payment_method->label();
        
        if ($this->reference_number) {
            $method .= " (Ref: {$this->reference_number})";
        }
        
        return $method;
    }

    // Scopes

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForSale($query, int $saleId)
    {
        return $query->where('sale_id', $saleId);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [TransactionStatus::COMPLETED, TransactionStatus::VERIFIED]);
    }

    public function scopePending($query)
    {
        return $query->where('status', TransactionStatus::PENDING);
    }

    public function scopeByMethod($query, PaymentMethod $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', now());
    }
}
```

### CreditPayment Model

**File:** `app/Models/CreditPayment.php`

```php
<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CreditPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'shop_id',
        'sale_id',
        'payment_number',
        'amount',
        'payment_method',
        'status',
        'reference_number',
        'credit_balance_before',
        'credit_balance_after',
        'payment_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'credit_balance_before' => 'decimal:2',
            'credit_balance_after' => 'decimal:2',
            'payment_date' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreditPayment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->payment_number)) {
                $payment->payment_number = self::generatePaymentNumber();
            }
            if (empty($payment->status)) {
                $payment->status = TransactionStatus::COMPLETED;
            }
            if (empty($payment->payment_date)) {
                $payment->payment_date = now();
            }
            $payment->created_by = auth()->id();
        });

        static::updating(function (CreditPayment $payment) {
            $payment->updated_by = auth()->id();
        });

        static::created(function (CreditPayment $payment) {
            if ($payment->status->isSuccessful()) {
                $payment->customer->reduceCredit($payment->amount);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generatePaymentNumber(): string
    {
        $prefix = 'CPAY';
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', now())->count() + 1;
        
        return "{$prefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [TransactionStatus::COMPLETED, TransactionStatus::VERIFIED]);
    }
}
```

### ShopPaymentMethod Model

**File:** `app/Models/ShopPaymentMethod.php`

```php
<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShopPaymentMethod extends Model
{
    protected $fillable = [
        'uuid',
        'shop_id',
        'payment_method',
        'is_enabled',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'is_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $method) {
            if (empty($method->uuid)) {
                $method->uuid = (string) Str::uuid();
            }
            $method->created_by = auth()->id();
        });

        static::updating(function (self $method) {
            $method->updated_by = auth()->id();
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

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
```

---

## 5. Actions

### RecordPaymentAction

**File:** `app/Actions/Payments/RecordPaymentAction.php`

```php
<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class RecordPaymentAction
{
    public function execute(
        Sale $sale,
        float $amount,
        PaymentMethod $method,
        ?string $referenceNumber = null,
        ?array $methodDetails = [],
        ?string $notes = null
    ): Payment {
        return DB::transaction(function () use ($sale, $amount, $method, $referenceNumber, $methodDetails, $notes) {
            $balanceBefore = $sale->balance_due;

            $payment = Payment::create([
                'sale_id' => $sale->id,
                'shop_id' => $sale->shop_id,
                'customer_id' => $sale->customer_id,
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $referenceNumber,
                'bank_name' => $methodDetails['bank_name'] ?? null,
                'card_last_four' => $methodDetails['card_last_four'] ?? null,
                'mobile_provider' => $methodDetails['mobile_provider'] ?? null,
                'mobile_number' => $methodDetails['mobile_number'] ?? null,
                'balance_before' => $balanceBefore,
                'balance_after' => max(0, $balanceBefore - $amount),
                'notes' => $notes,
            ]);

            return $payment;
        });
    }
}
```

### RecordCreditPaymentAction

**File:** `app/Actions/Payments/RecordCreditPaymentAction.php`

```php
<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class RecordCreditPaymentAction
{
    public function execute(
        Customer $customer,
        Shop $shop,
        float $amount,
        PaymentMethod $method,
        ?Sale $sale = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): CreditPayment {
        return DB::transaction(function () use ($customer, $shop, $amount, $method, $sale, $referenceNumber, $notes) {
            $balanceBefore = $customer->credit_balance;

            $payment = CreditPayment::create([
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'sale_id' => $sale?->id,
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $referenceNumber,
                'credit_balance_before' => $balanceBefore,
                'credit_balance_after' => max(0, $balanceBefore - $amount),
                'notes' => $notes,
            ]);

            return $payment;
        });
    }
}
```

### VoidPaymentAction

**File:** `app/Actions/Payments/VoidPaymentAction.php`

```php
<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class VoidPaymentAction
{
    public function execute(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment->void($reason);
            return $payment->refresh();
        });
    }
}
```

---

## 6. Services

### PaymentService

**File:** `app/Services/PaymentService.php`

```php
<?php

namespace App\Services;

use App\Actions\Payments\RecordCreditPaymentAction;
use App\Actions\Payments\RecordPaymentAction;
use App\Actions\Payments\VoidPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopPaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentService
{
    public function __construct(
        private RecordPaymentAction $recordPayment,
        private RecordCreditPaymentAction $recordCreditPayment,
        private VoidPaymentAction $voidPayment
    ) {}

    /**
     * Record payment for a sale
     */
    public function recordPayment(
        Sale $sale,
        float $amount,
        PaymentMethod $method,
        ?string $referenceNumber = null,
        ?array $methodDetails = [],
        ?string $notes = null
    ): Payment {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($amount > $sale->balance_due) {
            throw new \InvalidArgumentException('Payment amount exceeds balance due.');
        }

        return $this->recordPayment->execute(
            $sale,
            $amount,
            $method,
            $referenceNumber,
            $methodDetails,
            $notes
        );
    }

    /**
     * Record full payment for a sale
     */
    public function recordFullPayment(
        Sale $sale,
        PaymentMethod $method,
        ?string $referenceNumber = null,
        ?array $methodDetails = []
    ): Payment {
        return $this->recordPayment(
            $sale,
            $sale->balance_due,
            $method,
            $referenceNumber,
            $methodDetails
        );
    }

    /**
     * Record split payment (multiple methods)
     */
    public function recordSplitPayment(Sale $sale, array $payments): array
    {
        $recordedPayments = [];

        foreach ($payments as $paymentData) {
            $recordedPayments[] = $this->recordPayment(
                $sale->refresh(),
                $paymentData['amount'],
                PaymentMethod::from($paymentData['method']),
                $paymentData['reference'] ?? null,
                $paymentData['details'] ?? []
            );
        }

        return $recordedPayments;
    }

    /**
     * Record credit payment from customer
     */
    public function recordCreditPayment(
        Customer $customer,
        Shop $shop,
        float $amount,
        PaymentMethod $method,
        ?Sale $sale = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): CreditPayment {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($amount > $customer->credit_balance) {
            throw new \InvalidArgumentException('Payment amount exceeds credit balance.');
        }

        return $this->recordCreditPayment->execute(
            $customer,
            $shop,
            $amount,
            $method,
            $sale,
            $referenceNumber,
            $notes
        );
    }

    /**
     * Verify a pending payment
     */
    public function verifyPayment(Payment $payment): Payment
    {
        if ($payment->status !== TransactionStatus::PENDING) {
            throw new \Exception('Only pending payments can be verified.');
        }

        $payment->verify();
        return $payment->refresh();
    }

    /**
     * Void a payment
     */
    public function voidPayment(Payment $payment, string $reason): Payment
    {
        if ($payment->status === TransactionStatus::VOIDED) {
            throw new \Exception('Payment is already voided.');
        }

        return $this->voidPayment->execute($payment, $reason);
    }

    /**
     * Get enabled payment methods for a shop
     */
    public function getEnabledMethods(Shop $shop): Collection
    {
        return ShopPaymentMethod::where('shop_id', $shop->id)
            ->enabled()
            ->get()
            ->pluck('payment_method');
    }

    /**
     * Get payment summary for a period
     */
    public function getPaymentSummary(
        ?Shop $shop = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $query = Payment::successful()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->when($startDate && $endDate, fn ($q) => $q->betweenDates($startDate, $endDate));

        $byMethod = (clone $query)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->payment_method->value => [
                    'count' => $item->count,
                    'total' => $item->total,
                ]
            ]);

        return [
            'total_payments' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('amount'),
            'by_method' => $byMethod,
            'pending_count' => Payment::pending()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->count(),
            'pending_amount' => Payment::pending()
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->sum('amount'),
        ];
    }

    /**
     * Get daily cash collection
     */
    public function getDailyCashCollection(?Shop $shop = null): float
    {
        return Payment::successful()
            ->byMethod(PaymentMethod::CASH)
            ->today()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->sum('amount');
    }

    /**
     * Get pending verifications
     */
    public function getPendingVerifications(?Shop $shop = null): Collection
    {
        return Payment::with(['sale', 'customer', 'createdBy'])
            ->pending()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get customer payment history
     */
    public function getCustomerPaymentHistory(Customer $customer): Collection
    {
        $salePayments = Payment::with(['sale'])
            ->where('customer_id', $customer->id)
            ->successful()
            ->get()
            ->map(fn ($p) => [
                'type' => 'sale_payment',
                'uuid' => $p->uuid,
                'amount' => $p->amount,
                'method' => $p->payment_method,
                'reference' => $p->sale->invoice_number,
                'date' => $p->payment_date,
            ]);

        $creditPayments = CreditPayment::where('customer_id', $customer->id)
            ->successful()
            ->get()
            ->map(fn ($p) => [
                'type' => 'credit_payment',
                'uuid' => $p->uuid,
                'amount' => $p->amount,
                'method' => $p->payment_method,
                'reference' => $p->payment_number,
                'date' => $p->payment_date,
            ]);

        return $salePayments->concat($creditPayments)
            ->sortByDesc('date')
            ->values();
    }
}
```

---

## 7. Controllers

### PaymentController

**File:** `app/Http/Controllers/PaymentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);

        $shopId = $request->get('shop_id');
        $method = $request->get('payment_method');
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $payments = Payment::with(['sale', 'customer', 'createdBy'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($method, fn ($q) => $q->where('payment_method', $method))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo))
            ->latest('payment_date')
            ->paginate(30);

        $shops = Shop::active()->get();
        $paymentMethods = PaymentMethod::cases();

        $summary = $this->paymentService->getPaymentSummary(
            $shopId ? Shop::find($shopId) : null,
            $dateFrom ? \Carbon\Carbon::parse($dateFrom) : now()->startOfMonth(),
            $dateTo ? \Carbon\Carbon::parse($dateTo) : now()
        );

        return view('payments.index', compact('payments', 'shops', 'paymentMethods', 'summary'));
    }

    public function create(Sale $sale): View
    {
        $this->authorize('create', Payment::class);

        if ($sale->balance_due <= 0) {
            return redirect()->route('sales.show', $sale->uuid)
                ->with('info', 'This sale is already fully paid.');
        }

        $sale->load(['customer', 'shop']);
        $paymentMethods = $this->paymentService->getEnabledMethods($sale->shop);

        return view('payments.create', compact('sale', 'paymentMethods'));
    }

    public function store(RecordPaymentRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $payment = $this->paymentService->recordPayment(
                $sale,
                $request->amount,
                PaymentMethod::from($request->payment_method),
                $request->reference_number,
                [
                    'bank_name' => $request->bank_name,
                    'card_last_four' => $request->card_last_four,
                    'mobile_provider' => $request->mobile_provider,
                    'mobile_number' => $request->mobile_number,
                ],
                $request->notes
            );

            $message = $sale->refresh()->balance_due <= 0
                ? 'Payment recorded. Sale is now fully paid.'
                : 'Payment recorded. Remaining balance: ' . number_format($sale->balance_due, 2);

            return redirect()->route('sales.show', $sale->uuid)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load(['sale.items', 'customer', 'shop', 'createdBy', 'verifiedBy']);

        return view('payments.show', compact('payment'));
    }

    public function verify(Payment $payment): RedirectResponse
    {
        $this->authorize('verify', $payment);

        try {
            $this->paymentService->verifyPayment($payment);
            return back()->with('success', 'Payment verified successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(VoidPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('void', $payment);

        try {
            $this->paymentService->voidPayment($payment, $request->reason);
            return back()->with('success', 'Payment voided successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pending(Request $request): View
    {
        $this->authorize('verify', Payment::class);

        $shopId = $request->get('shop_id');
        $shop = $shopId ? Shop::find($shopId) : null;

        $payments = $this->paymentService->getPendingVerifications($shop);
        $shops = Shop::active()->get();

        return view('payments.pending', compact('payments', 'shops'));
    }

    public function receipt(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load(['sale.items.product', 'customer', 'shop', 'createdBy']);

        return view('payments.receipt', compact('payment'));
    }
}
```

### CreditPaymentController

**File:** `app/Http/Controllers/CreditPaymentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\RecordCreditPaymentRequest;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditPaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditPayment::class);

        $customerId = $request->get('customer_id');

        $payments = CreditPayment::with(['customer', 'shop', 'sale', 'createdBy'])
            ->when($customerId, fn ($q) => $q->forCustomer($customerId))
            ->latest('payment_date')
            ->paginate(30);

        $customers = Customer::withOutstandingBalance()->get();

        return view('credit-payments.index', compact('payments', 'customers'));
    }

    public function create(Customer $customer): View
    {
        $this->authorize('create', CreditPayment::class);

        if ($customer->credit_balance <= 0) {
            return redirect()->route('customers.show', $customer->uuid)
                ->with('info', 'This customer has no outstanding balance.');
        }

        $customer->load('shop');
        $paymentMethods = $this->paymentService->getEnabledMethods($customer->shop);
        $unpaidSales = $customer->sales()->unpaid()->get();

        return view('credit-payments.create', compact('customer', 'paymentMethods', 'unpaidSales'));
    }

    public function store(RecordCreditPaymentRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $payment = $this->paymentService->recordCreditPayment(
                $customer,
                $customer->shop,
                $request->amount,
                PaymentMethod::from($request->payment_method),
                $request->sale_id ? \App\Models\Sale::find($request->sale_id) : null,
                $request->reference_number,
                $request->notes
            );

            $message = $customer->refresh()->credit_balance <= 0
                ? 'Payment recorded. Customer credit is now cleared.'
                : 'Payment recorded. Remaining balance: ' . number_format($customer->credit_balance, 2);

            return redirect()->route('customers.show', $customer->uuid)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(CreditPayment $creditPayment): View
    {
        $this->authorize('view', $creditPayment);

        $creditPayment->load(['customer', 'shop', 'sale', 'createdBy']);

        return view('credit-payments.show', compact('creditPayment'));
    }
}
```

---

## 8. Form Requests

### RecordPaymentRequest

**File:** `app/Http/Requests/RecordPaymentRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Payment::class);
    }

    public function rules(): array
    {
        $sale = $this->route('sale');
        $method = PaymentMethod::tryFrom($this->payment_method);

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $sale->balance_due],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_number' => [
                $method?->requiresReference() ? 'required' : 'nullable',
                'string',
                'max:100',
            ],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'card_last_four' => ['nullable', 'string', 'size:4'],
            'mobile_provider' => ['nullable', 'string', 'max:50'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter the payment amount.',
            'amount.max' => 'Payment amount cannot exceed the balance due.',
            'payment_method.required' => 'Please select a payment method.',
            'reference_number.required' => 'Reference number is required for this payment method.',
        ];
    }
}
```

### RecordCreditPaymentRequest

**File:** `app/Http/Requests/RecordCreditPaymentRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordCreditPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\CreditPayment::class);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $customer->credit_balance],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'sale_id' => ['nullable', 'exists:sales,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

### VoidPaymentRequest

**File:** `app/Http/Requests/VoidPaymentRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('payment'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for voiding this payment.',
            'reason.min' => 'Reason must be at least 10 characters.',
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CreditPaymentController;

Route::middleware(['auth'])->group(function () {
    // Payments
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->name('index')
            ->middleware('permission:payments.view');
        
        Route::get('/pending', [PaymentController::class, 'pending'])
            ->name('pending')
            ->middleware('permission:payments.verify');
        
        Route::get('/sale/{sale}', [PaymentController::class, 'create'])
            ->name('create')
            ->middleware('permission:payments.create');
        
        Route::post('/sale/{sale}', [PaymentController::class, 'store'])
            ->name('store')
            ->middleware('permission:payments.create');
        
        Route::get('/{payment}', [PaymentController::class, 'show'])
            ->name('show')
            ->middleware('permission:payments.view');
        
        Route::get('/{payment}/receipt', [PaymentController::class, 'receipt'])
            ->name('receipt')
            ->middleware('permission:payments.view');
        
        Route::post('/{payment}/verify', [PaymentController::class, 'verify'])
            ->name('verify')
            ->middleware('permission:payments.verify');
        
        Route::post('/{payment}/void', [PaymentController::class, 'void'])
            ->name('void')
            ->middleware('permission:payments.void');
    });

    // Credit Payments
    Route::prefix('credit-payments')->name('credit-payments.')->group(function () {
        Route::get('/', [CreditPaymentController::class, 'index'])
            ->name('index')
            ->middleware('permission:payments.credit.view');
        
        Route::get('/customer/{customer}', [CreditPaymentController::class, 'create'])
            ->name('create')
            ->middleware('permission:payments.credit.create');
        
        Route::post('/customer/{customer}', [CreditPaymentController::class, 'store'])
            ->name('store')
            ->middleware('permission:payments.credit.create');
        
        Route::get('/{creditPayment}', [CreditPaymentController::class, 'show'])
            ->name('show')
            ->middleware('permission:payments.credit.view');
    });
});
```

---

## 10. Permissions

```php
// Payment permissions
'payments.view',
'payments.create',
'payments.verify',
'payments.void',
'payments.reports',

// Credit payment permissions
'payments.credit.view',
'payments.credit.create',
```

---

## 11. UI Template Reference

| View | Template Source |
|------|-----------------|
| Payments List | `design/src/transactions-list.php` |
| Record Payment | `design/src/checkout.php` |
| Payment Receipt | `design/src/invoice.php` |
| Pending Verifications | `design/src/pending-orders.php` |

---

## 12. Tests

**File:** `tests/Feature/PaymentsTest.php`

```php
<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\PaymentService;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('payment can be recorded for a sale', function () {
    $user = User::factory()->create();
    $user->assignRole('sales');
    
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $response = $this->actingAs($user)->post(route('payments.store', $sale->uuid), [
        'amount' => 100,
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payments', [
        'sale_id' => $sale->id,
        'amount' => 100,
        'payment_method' => 'cash',
    ]);
});

test('payment number is auto-generated', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->create(['shop_id' => $shop->id]);
    
    $payment = Payment::factory()->create([
        'sale_id' => $sale->id,
        'shop_id' => $shop->id,
    ]);

    expect($payment->payment_number)->toStartWith('PAY-');
});

test('sale balance is updated after payment', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'paid_amount' => 0,
        'balance_due' => 100,
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $service = app(PaymentService::class);
    $service->recordPayment($sale, 60, PaymentMethod::CASH);

    $sale->refresh();
    expect((float) $sale->paid_amount)->toBe(60.00);
    expect((float) $sale->balance_due)->toBe(40.00);
    expect($sale->payment_status)->toBe(PaymentStatus::PARTIAL);
});

test('sale is marked as paid when fully paid', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $service = app(PaymentService::class);
    $service->recordFullPayment($sale, PaymentMethod::CASH);

    $sale->refresh();
    expect($sale->payment_status)->toBe(PaymentStatus::PAID);
    expect((float) $sale->balance_due)->toBe(0.00);
});

test('partial payment updates balance correctly', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 200,
        'balance_due' => 200,
    ]);

    $service = app(PaymentService::class);
    $service->recordPayment($sale, 50, PaymentMethod::CASH);
    $service->recordPayment($sale->refresh(), 100, PaymentMethod::CARD, 'TXN123');

    $sale->refresh();
    expect((float) $sale->paid_amount)->toBe(150.00);
    expect((float) $sale->balance_due)->toBe(50.00);
    expect($sale->payments)->toHaveCount(2);
});

test('cheque payment requires verification', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $service = app(PaymentService::class);
    $payment = $service->recordPayment($sale, 100, PaymentMethod::CHEQUE, 'CHQ001');

    expect($payment->status)->toBe(TransactionStatus::PENDING);
    
    // Sale balance should not change until verified
    $sale->refresh();
    expect((float) $sale->balance_due)->toBe(100.00);
});

test('payment verification updates sale', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $payment = Payment::factory()->create([
        'sale_id' => $sale->id,
        'shop_id' => $shop->id,
        'amount' => 100,
        'status' => TransactionStatus::PENDING,
    ]);

    $service = app(PaymentService::class);
    $service->verifyPayment($payment);

    expect($payment->refresh()->status)->toBe(TransactionStatus::VERIFIED);
    expect((float) $sale->refresh()->balance_due)->toBe(0.00);
});

test('voiding payment restores sale balance', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'paid_amount' => 100,
        'balance_due' => 0,
        'payment_status' => PaymentStatus::PAID,
    ]);

    $payment = Payment::factory()->create([
        'sale_id' => $sale->id,
        'shop_id' => $shop->id,
        'amount' => 100,
        'status' => TransactionStatus::COMPLETED,
    ]);

    $service = app(PaymentService::class);
    $service->voidPayment($payment, 'Test void');

    expect($payment->refresh()->status)->toBe(TransactionStatus::VOIDED);
    expect((float) $sale->refresh()->balance_due)->toBe(100.00);
});

test('credit payment reduces customer balance', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create([
        'shop_id' => $shop->id,
        'credit_balance' => 500,
    ]);

    $service = app(PaymentService::class);
    $service->recordCreditPayment($customer, $shop, 200, PaymentMethod::CASH);

    expect((float) $customer->refresh()->credit_balance)->toBe(300.00);
});

test('payment amount cannot exceed balance due', function () {
    $shop = Shop::factory()->create();
    $sale = Sale::factory()->completed()->create([
        'shop_id' => $shop->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $service = app(PaymentService::class);

    expect(fn () => $service->recordPayment($sale, 150, PaymentMethod::CASH))
        ->toThrow(\InvalidArgumentException::class);
});
```

---

## 13. Commands to Execute

```bash
# Step 1: Create Enums
# Create PaymentMethod, TransactionStatus enums

# Step 2: Create Models with migrations
php artisan make:model Payment -mfs --no-interaction
php artisan make:model CreditPayment -mf --no-interaction
php artisan make:model ShopPaymentMethod -m --no-interaction

# Step 3: Create Actions
mkdir -p app/Actions/Payments
# Create RecordPaymentAction, RecordCreditPaymentAction, VoidPaymentAction

# Step 4: Create Service
php artisan make:class Services/PaymentService --no-interaction

# Step 5: Create Controllers
php artisan make:controller PaymentController --no-interaction
php artisan make:controller CreditPaymentController --no-interaction

# Step 6: Create Form Requests
php artisan make:request RecordPaymentRequest --no-interaction
php artisan make:request RecordCreditPaymentRequest --no-interaction
php artisan make:request VoidPaymentRequest --no-interaction

# Step 7: Create Policies
php artisan make:policy PaymentPolicy --model=Payment --no-interaction
php artisan make:policy CreditPaymentPolicy --model=CreditPayment --no-interaction

# Step 8: Run migrations
php artisan migrate

# Step 9: Create tests
php artisan make:test PaymentsTest --pest --no-interaction

# Step 10: Run tests
php artisan test --compact --filter=Payments

# Step 11: Format code
vendor/bin/pint --dirty
```

---

## 14. Verification Checklist

Before proceeding to Module 12, verify:

- [ ] All tables have `uuid` column with `getRouteKeyName()` on models
- [ ] All tables have audit columns (`created_by`, `updated_by`)
- [ ] Payment number auto-generates uniquely
- [ ] Multiple payment methods supported
- [ ] Partial payments update sale balance correctly
- [ ] Full payment marks sale as PAID
- [ ] Pending payments (cheque/transfer) require verification
- [ ] Payment verification updates sale
- [ ] Voiding payment restores sale balance
- [ ] Credit payments reduce customer balance
- [ ] Split payments work correctly
- [ ] Payment amount validation prevents overpayment
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass
- [ ] Code formatted with Pint

---

## 15. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 12: Credit Sales Management](./12-credit-sales.md)**
