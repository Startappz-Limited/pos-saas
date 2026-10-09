# Module 16: Alerts & Notifications
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Comprehensive alert and notification system for real-time business event notifications including low stock alerts, overdue payments, budget warnings, sales milestones, and system events. Supports multiple channels (in-app, email, SMS) with user preferences.

**Priority:** P1 (High)  
**Dependencies:** All previous modules  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Alert triggers logged |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Alerts Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Alert details
            $table->string('title');
            $table->text('message');
            $table->string('type'); // AlertType enum
            $table->string('severity'); // AlertSeverity enum
            $table->string('category'); // AlertCategory enum
            
            // Reference
            $table->nullableMorphs('alertable'); // Product, Sale, Expense, etc.
            
            // Status
            $table->boolean('is_read')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            
            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Metadata
            $table->json('data')->nullable();
            $table->json('actions')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('uuid');
            $table->index(['shop_id', 'type', 'is_resolved']);
            $table->index(['type', 'severity']);
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
```

### Alert Recipients Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_recipients', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['alert_id', 'user_id']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_recipients');
    }
};
```

### Alert Rules Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // AlertType enum
            $table->string('category'); // AlertCategory enum
            
            // Conditions
            $table->json('conditions');
            $table->string('operator')->default('AND'); // AND, OR
            
            // Thresholds
            $table->decimal('threshold_value', 15, 2)->nullable();
            $table->string('threshold_unit')->nullable();
            
            // Actions
            $table->json('channels'); // ['email', 'sms', 'in_app']
            $table->json('recipient_roles')->nullable();
            $table->json('recipient_users')->nullable();
            
            // Schedule
            $table->string('frequency')->nullable(); // immediate, hourly, daily, weekly
            $table->time('send_time')->nullable();
            $table->json('send_days')->nullable(); // [1,2,3,4,5] for weekdays
            
            $table->boolean('is_active')->default(true);
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['shop_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
```

### Notification Preferences Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Channel preferences
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            
            // Category preferences (JSON for flexibility)
            $table->json('category_preferences')->nullable();
            
            // Quiet hours
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            
            // Digest settings
            $table->boolean('daily_digest_enabled')->default(false);
            $table->time('digest_time')->nullable();
            
            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

### Notification Log Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('channel'); // NotificationChannel enum
            $table->string('type');
            $table->string('status'); // pending, sent, failed, delivered
            
            $table->string('recipient'); // email address, phone number
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['user_id', 'channel']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
```

---

## 3. Enums

### AlertType Enum

**File:** `app/Enums/AlertType.php`

```php
<?php

namespace App\Enums;

enum AlertType: string
{
    // Inventory
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    case STOCK_EXPIRING = 'stock_expiring';
    case REORDER_POINT = 'reorder_point';
    
    // Sales
    case SALE_COMPLETED = 'sale_completed';
    case LARGE_SALE = 'large_sale';
    case REFUND_PROCESSED = 'refund_processed';
    case SALES_TARGET_MET = 'sales_target_met';
    
    // Payments
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_OVERDUE = 'payment_overdue';
    case CREDIT_LIMIT_WARNING = 'credit_limit_warning';
    case CREDIT_LIMIT_EXCEEDED = 'credit_limit_exceeded';
    
    // Expenses
    case EXPENSE_PENDING = 'expense_pending';
    case EXPENSE_APPROVED = 'expense_approved';
    case BUDGET_WARNING = 'budget_warning';
    case BUDGET_EXCEEDED = 'budget_exceeded';
    
    // System
    case USER_LOGIN = 'user_login';
    case PASSWORD_CHANGED = 'password_changed';
    case SYSTEM_ERROR = 'system_error';
    case BACKUP_COMPLETED = 'backup_completed';

    public function label(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'Low Stock Alert',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::STOCK_EXPIRING => 'Stock Expiring Soon',
            self::REORDER_POINT => 'Reorder Point Reached',
            self::SALE_COMPLETED => 'Sale Completed',
            self::LARGE_SALE => 'Large Sale Alert',
            self::REFUND_PROCESSED => 'Refund Processed',
            self::SALES_TARGET_MET => 'Sales Target Met',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::PAYMENT_OVERDUE => 'Payment Overdue',
            self::CREDIT_LIMIT_WARNING => 'Credit Limit Warning',
            self::CREDIT_LIMIT_EXCEEDED => 'Credit Limit Exceeded',
            self::EXPENSE_PENDING => 'Expense Pending Approval',
            self::EXPENSE_APPROVED => 'Expense Approved',
            self::BUDGET_WARNING => 'Budget Warning',
            self::BUDGET_EXCEEDED => 'Budget Exceeded',
            self::USER_LOGIN => 'User Login',
            self::PASSWORD_CHANGED => 'Password Changed',
            self::SYSTEM_ERROR => 'System Error',
            self::BACKUP_COMPLETED => 'Backup Completed',
        };
    }

    public function defaultSeverity(): AlertSeverity
    {
        return match ($this) {
            self::OUT_OF_STOCK, 
            self::CREDIT_LIMIT_EXCEEDED, 
            self::BUDGET_EXCEEDED, 
            self::SYSTEM_ERROR => AlertSeverity::HIGH,
            
            self::LOW_STOCK, 
            self::STOCK_EXPIRING, 
            self::PAYMENT_OVERDUE, 
            self::CREDIT_LIMIT_WARNING, 
            self::BUDGET_WARNING => AlertSeverity::MEDIUM,
            
            default => AlertSeverity::LOW,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LOW_STOCK, self::OUT_OF_STOCK, self::REORDER_POINT => 'solar:box-bold',
            self::STOCK_EXPIRING => 'solar:calendar-bold',
            self::SALE_COMPLETED, self::LARGE_SALE => 'solar:cart-large-bold',
            self::REFUND_PROCESSED => 'solar:undo-left-bold',
            self::SALES_TARGET_MET => 'solar:cup-star-bold',
            self::PAYMENT_RECEIVED => 'solar:wallet-money-bold',
            self::PAYMENT_OVERDUE => 'solar:clock-circle-bold',
            self::CREDIT_LIMIT_WARNING, self::CREDIT_LIMIT_EXCEEDED => 'solar:card-bold',
            self::EXPENSE_PENDING, self::EXPENSE_APPROVED => 'solar:bill-list-bold',
            self::BUDGET_WARNING, self::BUDGET_EXCEEDED => 'solar:chart-bold',
            self::USER_LOGIN, self::PASSWORD_CHANGED => 'solar:user-bold',
            self::SYSTEM_ERROR => 'solar:danger-triangle-bold',
            self::BACKUP_COMPLETED => 'solar:cloud-download-bold',
        };
    }
}
```

### AlertSeverity Enum

**File:** `app/Enums/AlertSeverity.php`

```php
<?php

namespace App\Enums;

enum AlertSeverity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'info',
            self::MEDIUM => 'warning',
            self::HIGH => 'danger',
            self::CRITICAL => 'dark',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }
}
```

### AlertCategory Enum

**File:** `app/Enums/AlertCategory.php`

```php
<?php

namespace App\Enums;

enum AlertCategory: string
{
    case INVENTORY = 'inventory';
    case SALES = 'sales';
    case PAYMENTS = 'payments';
    case EXPENSES = 'expenses';
    case CUSTOMERS = 'customers';
    case SYSTEM = 'system';
    case SECURITY = 'security';

    public function label(): string
    {
        return match ($this) {
            self::INVENTORY => 'Inventory',
            self::SALES => 'Sales',
            self::PAYMENTS => 'Payments',
            self::EXPENSES => 'Expenses',
            self::CUSTOMERS => 'Customers',
            self::SYSTEM => 'System',
            self::SECURITY => 'Security',
        };
    }

    public function types(): array
    {
        return match ($this) {
            self::INVENTORY => [
                AlertType::LOW_STOCK,
                AlertType::OUT_OF_STOCK,
                AlertType::STOCK_EXPIRING,
                AlertType::REORDER_POINT,
            ],
            self::SALES => [
                AlertType::SALE_COMPLETED,
                AlertType::LARGE_SALE,
                AlertType::REFUND_PROCESSED,
                AlertType::SALES_TARGET_MET,
            ],
            self::PAYMENTS => [
                AlertType::PAYMENT_RECEIVED,
                AlertType::PAYMENT_OVERDUE,
                AlertType::CREDIT_LIMIT_WARNING,
                AlertType::CREDIT_LIMIT_EXCEEDED,
            ],
            self::EXPENSES => [
                AlertType::EXPENSE_PENDING,
                AlertType::EXPENSE_APPROVED,
                AlertType::BUDGET_WARNING,
                AlertType::BUDGET_EXCEEDED,
            ],
            self::SYSTEM => [
                AlertType::SYSTEM_ERROR,
                AlertType::BACKUP_COMPLETED,
            ],
            self::SECURITY => [
                AlertType::USER_LOGIN,
                AlertType::PASSWORD_CHANGED,
            ],
            self::CUSTOMERS => [],
        };
    }
}
```

### NotificationChannel Enum

**File:** `app/Enums/NotificationChannel.php`

```php
<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case IN_APP = 'in_app';
    case PUSH = 'push';
    case SLACK = 'slack';
    case WEBHOOK = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::IN_APP => 'In-App',
            self::PUSH => 'Push Notification',
            self::SLACK => 'Slack',
            self::WEBHOOK => 'Webhook',
        };
    }
}
```

---

## 4. Models

### Alert Model

**File:** `app/Models/Alert.php`

```php
<?php

namespace App\Models;

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Alert extends Model
{
    protected $fillable = [
        'uuid',
        'shop_id',
        'title',
        'message',
        'type',
        'severity',
        'category',
        'alertable_type',
        'alertable_id',
        'is_read',
        'is_resolved',
        'read_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'data',
        'actions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'category' => AlertCategory::class,
            'is_read' => 'boolean',
            'is_resolved' => 'boolean',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'data' => 'array',
            'actions' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $alert) {
            if (empty($alert->uuid)) {
                $alert->uuid = (string) Str::uuid();
            }
            if (empty($alert->severity)) {
                $alert->severity = $alert->type->defaultSeverity();
            }
            $alert->created_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AlertRecipient::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Methods

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public function resolve(?string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'resolution_notes' => $notes,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getActionUrl(): ?string
    {
        if (!$this->alertable) {
            return null;
        }

        return match (true) {
            $this->alertable instanceof Product => route('products.show', $this->alertable->uuid),
            $this->alertable instanceof Sale => route('sales.show', $this->alertable->uuid),
            $this->alertable instanceof Expense => route('expenses.show', $this->alertable->uuid),
            default => null,
        };
    }

    // Scopes

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeType($query, AlertType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCategory($query, AlertCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSeverity($query, AlertSeverity $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
```

### AlertRecipient Model

**File:** `app/Models/AlertRecipient.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRecipient extends Model
{
    protected $fillable = [
        'alert_id',
        'user_id',
        'is_read',
        'read_at',
        'is_dismissed',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'is_dismissed' => 'boolean',
            'dismissed_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public function dismiss(): void
    {
        $this->update([
            'is_dismissed' => true,
            'dismissed_at' => now(),
        ]);
    }
}
```

### AlertRule Model

**File:** `app/Models/AlertRule.php`

```php
<?php

namespace App\Models;

use App\Enums\AlertCategory;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AlertRule extends Model
{
    protected $fillable = [
        'uuid',
        'shop_id',
        'name',
        'description',
        'type',
        'category',
        'conditions',
        'operator',
        'threshold_value',
        'threshold_unit',
        'channels',
        'recipient_roles',
        'recipient_users',
        'frequency',
        'send_time',
        'send_days',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'category' => AlertCategory::class,
            'conditions' => 'array',
            'channels' => 'array',
            'recipient_roles' => 'array',
            'recipient_users' => 'array',
            'send_days' => 'array',
            'send_time' => 'datetime:H:i',
            'threshold_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $rule) {
            if (empty($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }
            $rule->created_by = auth()->id();
        });

        static::updating(function (self $rule) {
            $rule->updated_by = auth()->id();
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

    public function getRecipients(): \Illuminate\Support\Collection
    {
        $users = collect();

        // Add users by role
        if ($this->recipient_roles) {
            $roleUsers = User::role($this->recipient_roles)->get();
            $users = $users->merge($roleUsers);
        }

        // Add specific users
        if ($this->recipient_users) {
            $specificUsers = User::whereIn('id', $this->recipient_users)->get();
            $users = $users->merge($specificUsers);
        }

        return $users->unique('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, AlertType $type)
    {
        return $query->where('type', $type);
    }
}
```

### NotificationPreference Model

**File:** `app/Models/NotificationPreference.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'email_enabled',
        'sms_enabled',
        'in_app_enabled',
        'push_enabled',
        'category_preferences',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'daily_digest_enabled',
        'digest_time',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'category_preferences' => 'array',
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_start' => 'datetime:H:i',
            'quiet_hours_end' => 'datetime:H:i',
            'daily_digest_enabled' => 'boolean',
            'digest_time' => 'datetime:H:i',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isChannelEnabled(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->email_enabled,
            'sms' => $this->sms_enabled,
            'in_app' => $this->in_app_enabled,
            'push' => $this->push_enabled,
            default => false,
        };
    }

    public function isCategoryEnabled(string $category): bool
    {
        if (!$this->category_preferences) {
            return true; // Default to enabled
        }

        return $this->category_preferences[$category] ?? true;
    }

    public function isQuietHours(): bool
    {
        if (!$this->quiet_hours_enabled) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->quiet_hours_start?->format('H:i');
        $end = $this->quiet_hours_end?->format('H:i');

        if (!$start || !$end) {
            return false;
        }

        if ($start < $end) {
            return $now >= $start && $now <= $end;
        } else {
            // Overnight quiet hours (e.g., 22:00 to 07:00)
            return $now >= $start || $now <= $end;
        }
    }
}
```

---

## 5. Actions

### CreateAlertAction

**File:** `app/Actions/Alerts/CreateAlertAction.php`

```php
<?php

namespace App\Actions\Alerts;

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateAlertAction
{
    public function execute(
        AlertType $type,
        string $title,
        string $message,
        AlertCategory $category,
        ?Shop $shop = null,
        ?Model $alertable = null,
        ?AlertSeverity $severity = null,
        array $data = [],
        ?Collection $recipients = null
    ): Alert {
        return DB::transaction(function () use ($type, $title, $message, $category, $shop, $alertable, $severity, $data, $recipients) {
            $alert = Alert::create([
                'shop_id' => $shop?->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'category' => $category,
                'severity' => $severity ?? $type->defaultSeverity(),
                'alertable_type' => $alertable ? get_class($alertable) : null,
                'alertable_id' => $alertable?->id,
                'data' => $data,
            ]);

            // Add recipients
            if ($recipients && $recipients->isNotEmpty()) {
                foreach ($recipients as $user) {
                    $alert->recipients()->create([
                        'user_id' => $user->id,
                    ]);
                }
            }

            return $alert;
        });
    }
}
```

### SendAlertNotificationsAction

**File:** `app/Actions/Alerts/SendAlertNotificationsAction.php`

```php
<?php

namespace App\Actions\Alerts;

use App\Enums\NotificationChannel;
use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Support\Facades\Notification;

class SendAlertNotificationsAction
{
    public function execute(Alert $alert): void
    {
        $recipients = $alert->recipients()->with('user.notificationPreference')->get();

        foreach ($recipients as $recipient) {
            $user = $recipient->user;
            $preferences = $user->notificationPreference;

            // Skip if in quiet hours
            if ($preferences?->isQuietHours()) {
                continue;
            }

            // Skip if category is disabled
            if ($preferences && !$preferences->isCategoryEnabled($alert->category->value)) {
                continue;
            }

            $this->sendViaChannels($alert, $user, $preferences);
        }

        $alert->update(['sent_at' => now()]);
    }

    private function sendViaChannels(Alert $alert, User $user, $preferences): void
    {
        $channels = [];

        // In-app is always sent
        $channels[] = 'database';

        if (!$preferences || $preferences->email_enabled) {
            $channels[] = 'mail';
        }

        if ($preferences?->sms_enabled && $user->phone) {
            // Add SMS channel if configured
        }

        Notification::send($user, new AlertNotification($alert, $channels));
    }
}
```

### TriggerLowStockAlertAction

**File:** `app/Actions/Alerts/TriggerLowStockAlertAction.php`

```php
<?php

namespace App\Actions\Alerts;

use App\Enums\AlertCategory;
use App\Enums\AlertType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TriggerLowStockAlertAction
{
    public function __construct(private CreateAlertAction $createAlert) {}

    public function execute(?Shop $shop = null): array
    {
        $alerts = [];

        $lowStockItems = ShopInventory::with(['product', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->where('quantity', '>', 0)
            ->get();

        foreach ($lowStockItems as $inventory) {
            // Check if alert already exists for this product/shop
            $existingAlert = Alert::where('type', AlertType::LOW_STOCK)
                ->where('alertable_type', Product::class)
                ->where('alertable_id', $inventory->product_id)
                ->where('shop_id', $inventory->shop_id)
                ->unresolved()
                ->first();

            if ($existingAlert) {
                continue;
            }

            $recipients = $this->getRecipients($inventory->shop);

            $alerts[] = $this->createAlert->execute(
                AlertType::LOW_STOCK,
                "Low Stock: {$inventory->product->name}",
                "Stock for {$inventory->product->name} is running low. Current quantity: {$inventory->quantity}, Reorder point: {$inventory->reorder_point}",
                AlertCategory::INVENTORY,
                $inventory->shop,
                $inventory->product,
                null,
                [
                    'current_quantity' => $inventory->quantity,
                    'reorder_point' => $inventory->reorder_point,
                    'sku' => $inventory->product->sku,
                ],
                $recipients
            );
        }

        return $alerts;
    }

    private function getRecipients(Shop $shop): \Illuminate\Support\Collection
    {
        return User::permission('inventory.view')
            ->whereHas('shops', fn ($q) => $q->where('shops.id', $shop->id))
            ->get();
    }
}
```

---

## 6. Services

### AlertService

**File:** `app/Services/AlertService.php`

```php
<?php

namespace App\Services;

use App\Actions\Alerts\CreateAlertAction;
use App\Actions\Alerts\SendAlertNotificationsAction;
use App\Actions\Alerts\TriggerLowStockAlertAction;
use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AlertService
{
    public function __construct(
        private CreateAlertAction $createAlert,
        private SendAlertNotificationsAction $sendNotifications
    ) {}

    /**
     * Create and send alert
     */
    public function trigger(
        AlertType $type,
        string $title,
        string $message,
        AlertCategory $category,
        ?Shop $shop = null,
        ?Model $alertable = null,
        ?AlertSeverity $severity = null,
        array $data = [],
        ?Collection $recipients = null
    ): Alert {
        $alert = $this->createAlert->execute(
            $type, $title, $message, $category,
            $shop, $alertable, $severity, $data, $recipients
        );

        $this->sendNotifications->execute($alert);

        return $alert;
    }

    /**
     * Get alerts for user
     */
    public function getForUser(User $user, array $filters = []): Collection
    {
        $query = Alert::query()
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id))
            ->with(['shop', 'alertable'])
            ->active();

        if (isset($filters['category'])) {
            $query->category(AlertCategory::from($filters['category']));
        }

        if (isset($filters['severity'])) {
            $query->severity(AlertSeverity::from($filters['severity']));
        }

        if (isset($filters['unread']) && $filters['unread']) {
            $query->whereHas('recipients', fn ($q) => 
                $q->where('user_id', $user->id)->where('is_read', false)
            );
        }

        return $query->latest()->limit($filters['limit'] ?? 50)->get();
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(User $user): int
    {
        return Alert::whereHas('recipients', fn ($q) => 
            $q->where('user_id', $user->id)
              ->where('is_read', false)
              ->where('is_dismissed', false)
        )->active()->count();
    }

    /**
     * Mark alert as read for user
     */
    public function markAsRead(Alert $alert, User $user): void
    {
        $recipient = $alert->recipients()->where('user_id', $user->id)->first();
        if ($recipient) {
            $recipient->markAsRead();
        }
    }

    /**
     * Mark all as read for user
     */
    public function markAllAsRead(User $user): int
    {
        return \App\Models\AlertRecipient::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Dismiss alert for user
     */
    public function dismiss(Alert $alert, User $user): void
    {
        $recipient = $alert->recipients()->where('user_id', $user->id)->first();
        if ($recipient) {
            $recipient->dismiss();
        }
    }

    /**
     * Resolve alert
     */
    public function resolve(Alert $alert, ?string $notes = null): Alert
    {
        $alert->resolve($notes);
        return $alert->refresh();
    }

    /**
     * Get alert summary
     */
    public function getSummary(?Shop $shop = null): array
    {
        $query = Alert::query()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->unresolved()
            ->active();

        return [
            'total' => (clone $query)->count(),
            'critical' => (clone $query)->severity(AlertSeverity::CRITICAL)->count(),
            'high' => (clone $query)->severity(AlertSeverity::HIGH)->count(),
            'medium' => (clone $query)->severity(AlertSeverity::MEDIUM)->count(),
            'low' => (clone $query)->severity(AlertSeverity::LOW)->count(),
            'by_category' => $this->getCountByCategory($shop),
            'recent' => (clone $query)->recent(24)->count(),
        ];
    }

    /**
     * Get count by category
     */
    private function getCountByCategory(?Shop $shop = null): array
    {
        return Alert::query()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->unresolved()
            ->active()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
    }

    /**
     * Get alert history
     */
    public function getHistory(?Shop $shop = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->subMonth();
        $endDate = $endDate ?? now();

        return Alert::query()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['shop', 'alertable', 'resolvedBy'])
            ->latest()
            ->get();
    }

    /**
     * Process low stock alerts
     */
    public function processLowStockAlerts(?Shop $shop = null): array
    {
        $action = app(TriggerLowStockAlertAction::class);
        return $action->execute($shop);
    }

    /**
     * Process overdue payment alerts
     */
    public function processOverduePaymentAlerts(?Shop $shop = null): array
    {
        $alerts = [];

        $overdueSales = \App\Models\Sale::with(['customer', 'shop'])
            ->when($shop, fn ($q) => $q->where('shop_id', $shop->id))
            ->where('payment_status', 'partial')
            ->whereHas('payments', fn ($q) => $q->where('due_date', '<', now()))
            ->get();

        foreach ($overdueSales as $sale) {
            $existingAlert = Alert::where('type', AlertType::PAYMENT_OVERDUE)
                ->where('alertable_type', \App\Models\Sale::class)
                ->where('alertable_id', $sale->id)
                ->unresolved()
                ->first();

            if ($existingAlert) {
                continue;
            }

            $alerts[] = $this->trigger(
                AlertType::PAYMENT_OVERDUE,
                "Overdue Payment: {$sale->sale_number}",
                "Payment for sale {$sale->sale_number} is overdue. Outstanding: " . number_format($sale->balance, 2),
                AlertCategory::PAYMENTS,
                $sale->shop,
                $sale,
                AlertSeverity::HIGH,
                ['balance' => $sale->balance]
            );
        }

        return $alerts;
    }

    /**
     * Process budget alerts
     */
    public function processBudgetAlerts(?Shop $shop = null): array
    {
        $alerts = [];

        $categories = \App\Models\ExpenseCategory::with('shopSettings')
            ->whereNotNull('monthly_budget')
            ->get();

        foreach ($categories as $category) {
            $thisMonth = \App\Models\Expense::approved()
                ->forCategory($category->id)
                ->thisMonth()
                ->sum('amount');

            $budget = $category->monthly_budget;
            $utilization = $budget > 0 ? ($thisMonth / $budget) * 100 : 0;

            if ($utilization >= 100) {
                $type = AlertType::BUDGET_EXCEEDED;
                $title = "Budget Exceeded: {$category->name}";
                $severity = AlertSeverity::HIGH;
            } elseif ($utilization >= 80) {
                $type = AlertType::BUDGET_WARNING;
                $title = "Budget Warning: {$category->name}";
                $severity = AlertSeverity::MEDIUM;
            } else {
                continue;
            }

            $existingAlert = Alert::where('type', $type)
                ->where('alertable_type', \App\Models\ExpenseCategory::class)
                ->where('alertable_id', $category->id)
                ->whereMonth('created_at', now()->month)
                ->first();

            if ($existingAlert) {
                continue;
            }

            $alerts[] = $this->trigger(
                $type,
                $title,
                "Budget for {$category->name} is at {$utilization}%. Spent: " . number_format($thisMonth, 2) . " / " . number_format($budget, 2),
                AlertCategory::EXPENSES,
                null,
                $category,
                $severity,
                [
                    'budget' => $budget,
                    'spent' => $thisMonth,
                    'utilization' => $utilization,
                ]
            );
        }

        return $alerts;
    }
}
```

---

## 7. Controllers

### AlertController

**File:** `app/Http/Controllers/AlertController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\Shop;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function __construct(private AlertService $alertService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Alert::class);

        $shopId = $request->get('shop_id');
        $category = $request->get('category');
        $severity = $request->get('severity');
        $status = $request->get('status');

        $alerts = Alert::with(['shop', 'alertable'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($category, fn ($q) => $q->category(AlertCategory::from($category)))
            ->when($severity, fn ($q) => $q->severity(AlertSeverity::from($severity)))
            ->when($status === 'resolved', fn ($q) => $q->where('is_resolved', true))
            ->when($status === 'unresolved', fn ($q) => $q->unresolved())
            ->latest()
            ->paginate(30);

        $shops = Shop::active()->get();
        $summary = $this->alertService->getSummary(
            $shopId ? Shop::find($shopId) : null
        );

        return view('alerts.index', compact('alerts', 'shops', 'summary'));
    }

    public function show(Alert $alert): View
    {
        $this->authorize('view', $alert);
        $alert->load(['shop', 'alertable', 'recipients.user', 'resolvedBy']);

        // Mark as read for current user
        $this->alertService->markAsRead($alert, auth()->user());

        return view('alerts.show', compact('alert'));
    }

    public function markAsRead(Alert $alert): JsonResponse
    {
        $this->alertService->markAsRead($alert, auth()->user());
        return response()->json(['success' => true]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $count = $this->alertService->markAllAsRead(auth()->user());
        return response()->json(['success' => true, 'count' => $count]);
    }

    public function dismiss(Alert $alert): JsonResponse
    {
        $this->alertService->dismiss($alert, auth()->user());
        return response()->json(['success' => true]);
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('resolve', $alert);

        $this->alertService->resolve($alert, $request->notes);

        return back()->with('success', 'Alert resolved.');
    }

    public function unreadCount(): JsonResponse
    {
        $count = $this->alertService->getUnreadCount(auth()->user());
        return response()->json(['count' => $count]);
    }

    public function recent(): JsonResponse
    {
        $alerts = $this->alertService->getForUser(auth()->user(), [
            'limit' => 10,
            'unread' => true,
        ]);

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }
}
```

### NotificationPreferenceController

**File:** `app/Http/Controllers/NotificationPreferenceController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function edit(): View
    {
        $preferences = auth()->user()->notificationPreference 
            ?? new NotificationPreference(['user_id' => auth()->id()]);

        return view('settings.notifications', compact('preferences'));
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $preferences = NotificationPreference::updateOrCreate(
            ['user_id' => auth()->id()],
            $request->validated()
        );

        return back()->with('success', 'Notification preferences updated.');
    }
}
```

---

## 8. Form Requests

### UpdateNotificationPreferencesRequest

**File:** `app/Http/Requests/UpdateNotificationPreferencesRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => ['boolean'],
            'sms_enabled' => ['boolean'],
            'in_app_enabled' => ['boolean'],
            'push_enabled' => ['boolean'],
            'category_preferences' => ['nullable', 'array'],
            'category_preferences.*' => ['boolean'],
            'quiet_hours_enabled' => ['boolean'],
            'quiet_hours_start' => ['nullable', 'required_if:quiet_hours_enabled,true', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'required_if:quiet_hours_enabled,true', 'date_format:H:i'],
            'daily_digest_enabled' => ['boolean'],
            'digest_time' => ['nullable', 'required_if:daily_digest_enabled,true', 'date_format:H:i'],
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\AlertController;
use App\Http\Controllers\NotificationPreferenceController;

Route::middleware(['auth'])->group(function () {
    // Alerts
    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/', [AlertController::class, 'index'])
            ->name('index')
            ->middleware('permission:alerts.view');
        
        Route::get('/unread-count', [AlertController::class, 'unreadCount'])
            ->name('unread-count');
        
        Route::get('/recent', [AlertController::class, 'recent'])
            ->name('recent');
        
        Route::post('/mark-all-read', [AlertController::class, 'markAllAsRead'])
            ->name('mark-all-read');
        
        Route::get('/{alert}', [AlertController::class, 'show'])
            ->name('show')
            ->middleware('permission:alerts.view');
        
        Route::post('/{alert}/read', [AlertController::class, 'markAsRead'])
            ->name('read');
        
        Route::post('/{alert}/dismiss', [AlertController::class, 'dismiss'])
            ->name('dismiss');
        
        Route::post('/{alert}/resolve', [AlertController::class, 'resolve'])
            ->name('resolve')
            ->middleware('permission:alerts.resolve');
    });

    // Notification Preferences
    Route::get('/settings/notifications', [NotificationPreferenceController::class, 'edit'])
        ->name('settings.notifications');
    Route::put('/settings/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('settings.notifications.update');
});
```

---

## 10. Permissions

```php
'alerts.view',
'alerts.resolve',
'alerts.manage-rules',
'notifications.manage',
```

---

## 11. Scheduled Commands

**File:** `app/Console/Commands/ProcessAlertsCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class ProcessAlertsCommand extends Command
{
    protected $signature = 'alerts:process {--type=all : Type of alerts to process (low-stock, overdue, budget, all)}';
    protected $description = 'Process and trigger automated alerts';

    public function handle(AlertService $service): int
    {
        $type = $this->option('type');
        $total = 0;

        if ($type === 'all' || $type === 'low-stock') {
            $alerts = $service->processLowStockAlerts();
            $this->info('Low stock alerts: ' . count($alerts));
            $total += count($alerts);
        }

        if ($type === 'all' || $type === 'overdue') {
            $alerts = $service->processOverduePaymentAlerts();
            $this->info('Overdue payment alerts: ' . count($alerts));
            $total += count($alerts);
        }

        if ($type === 'all' || $type === 'budget') {
            $alerts = $service->processBudgetAlerts();
            $this->info('Budget alerts: ' . count($alerts));
            $total += count($alerts);
        }

        $this->info("Total alerts created: {$total}");
        return Command::SUCCESS;
    }
}
```

---

## 12. Notifications

### AlertNotification

**File:** `app/Notifications/AlertNotification.php`

```php
<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Alert $alert,
        private array $channels = ['database', 'mail']
    ) {}

    public function via($notifiable): array
    {
        return $this->channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[' . $this->alert->severity->label() . '] ' . $this->alert->title)
            ->line($this->alert->message);

        if ($url = $this->alert->getActionUrl()) {
            $mail->action('View Details', $url);
        }

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'alert_uuid' => $this->alert->uuid,
            'type' => $this->alert->type->value,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'severity' => $this->alert->severity->value,
            'category' => $this->alert->category->value,
            'action_url' => $this->alert->getActionUrl(),
            'data' => $this->alert->data,
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
```

---

## 13. Tests

**File:** `tests/Feature/AlertsTest.php`

```php
<?php

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\AlertService;

test('alert can be created', function () {
    $shop = Shop::factory()->create();
    
    $service = app(AlertService::class);
    $alert = $service->trigger(
        AlertType::LOW_STOCK,
        'Low Stock Alert',
        'Product ABC is running low',
        AlertCategory::INVENTORY,
        $shop
    );

    expect($alert)
        ->type->toBe(AlertType::LOW_STOCK)
        ->category->toBe(AlertCategory::INVENTORY);
});

test('alert has correct default severity', function () {
    $alert = Alert::factory()->create([
        'type' => AlertType::OUT_OF_STOCK,
    ]);

    expect($alert->severity)->toBe(AlertSeverity::HIGH);
});

test('alert can be marked as read', function () {
    $user = User::factory()->create();
    $alert = Alert::factory()->create();
    $alert->recipients()->create(['user_id' => $user->id]);

    $service = app(AlertService::class);
    $service->markAsRead($alert, $user);

    expect($alert->recipients()->first()->is_read)->toBeTrue();
});

test('alert can be resolved', function () {
    $user = User::factory()->create();
    $alert = Alert::factory()->create(['is_resolved' => false]);

    actingAs($user);
    
    $service = app(AlertService::class);
    $service->resolve($alert, 'Issue fixed');

    expect($alert->fresh())
        ->is_resolved->toBeTrue()
        ->resolution_notes->toBe('Issue fixed');
});

test('unread count is accurate', function () {
    $user = User::factory()->create();
    
    $alerts = Alert::factory()->count(5)->create();
    foreach ($alerts as $alert) {
        $alert->recipients()->create(['user_id' => $user->id]);
    }

    $service = app(AlertService::class);
    expect($service->getUnreadCount($user))->toBe(5);
});

test('low stock alerts are triggered', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->create();
    
    // Create low stock inventory
    \App\Models\ShopInventory::factory()->create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'reorder_point' => 10,
    ]);

    $service = app(AlertService::class);
    $alerts = $service->processLowStockAlerts($shop);

    expect($alerts)->not->toBeEmpty();
});
```

---

## 14. Verification Checklist

- [ ] All tables have `uuid` column with `getRouteKeyName()`
- [ ] Alert types cover all business events
- [ ] Notification preferences respected
- [ ] Quiet hours working
- [ ] Mark as read/dismiss works
- [ ] Alert resolution tracked
- [ ] Low stock alerts triggered
- [ ] Overdue payment alerts triggered
- [ ] Budget alerts triggered
- [ ] Email notifications sent
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass

---

## 15. Next Steps

→ **[Module 17: Reports & Dashboard](./17-reports-dashboard.md)**
