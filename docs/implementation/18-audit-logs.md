# Module 18: Audit Logs
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder, specifically guideline **0.5 Audit Logging** which mandates a separate audit database for immutable logs.

### Module Overview
Comprehensive audit logging system using a separate `audit` database connection for immutable records. Tracks all create, update, delete operations across the system with full history, user attribution, and IP tracking.

**Priority:** P1 (High - Security Requirement)  
**Dependencies:** All modules  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.5 Audit Logging** | Separate audit database, immutable storage |
| **0.3 Database Design** | Dual ID (`id` + `uuid`), timestamps |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Policies, audit viewer access control |

---

## 2. Database Configuration

### Add Audit Database Connection

**File:** `config/database.php`

```php
'connections' => [
    // ... existing connections ...

    'audit' => [
        'driver' => 'mysql',
        'host' => env('AUDIT_DB_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('AUDIT_DB_PORT', env('DB_PORT', '3306')),
        'database' => env('AUDIT_DB_DATABASE', 'audit_logs'),
        'username' => env('AUDIT_DB_USERNAME', env('DB_USERNAME', 'root')),
        'password' => env('AUDIT_DB_PASSWORD', env('DB_PASSWORD', '')),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],
],
```

### Environment Variables

**File:** `.env.example`

```env
# Audit Database (Separate for immutability)
AUDIT_DB_HOST=127.0.0.1
AUDIT_DB_PORT=3306
AUDIT_DB_DATABASE=audit_logs
AUDIT_DB_USERNAME=audit_user
AUDIT_DB_PASSWORD=secure_password
```

---

## 3. Database Schema

### Audit Logs Table

**File:** `database/migrations/audit/0001_01_01_000000_create_audit_logs_table.php`

> **Note:** This migration runs on the `audit` connection.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'audit';

    public function up(): void
    {
        Schema::connection('audit')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Who performed the action
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            
            // What was affected
            $table->string('auditable_type'); // Model class
            $table->unsignedBigInteger('auditable_id');
            $table->uuid('auditable_uuid')->nullable();
            
            // The action
            $table->string('event'); // created, updated, deleted, restored, etc.
            
            // The changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            
            // Context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('tags')->nullable();
            
            // Shop context (if applicable)
            $table->unsignedBigInteger('shop_id')->nullable();
            
            $table->timestamp('created_at');

            // Indexes for efficient querying
            $table->index('uuid');
            $table->index('user_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('auditable_uuid');
            $table->index('event');
            $table->index('created_at');
            $table->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::connection('audit')->dropIfExists('audit_logs');
    }
};
```

### Audit Sessions Table

**File:** `database/migrations/audit/0001_01_01_000001_create_audit_sessions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'audit';

    public function up(): void
    {
        Schema::connection('audit')->create('audit_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            
            $table->string('event'); // login, logout, failed_login, password_reset, etc.
            
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('location')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            
            $table->boolean('successful')->default(true);
            $table->text('failure_reason')->nullable();
            
            $table->timestamp('created_at');

            $table->index('uuid');
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('audit')->dropIfExists('audit_sessions');
    }
};
```

### Audit Exports Table

**File:** `database/migrations/audit/0001_01_01_000002_create_audit_exports_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'audit';

    public function up(): void
    {
        Schema::connection('audit')->create('audit_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->unsignedBigInteger('user_id');
            
            $table->string('name');
            $table->string('format'); // csv, json
            $table->string('status'); // pending, processing, completed, failed
            
            $table->json('filters')->nullable();
            
            $table->string('file_path')->nullable();
            $table->integer('record_count')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('audit')->dropIfExists('audit_exports');
    }
};
```

---

## 4. Enums

### AuditEvent Enum

**File:** `app/Enums/AuditEvent.php`

```php
<?php

namespace App\Enums;

enum AuditEvent: string
{
    // Model events
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    case FORCE_DELETED = 'force_deleted';
    
    // Authentication events
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case FAILED_LOGIN = 'failed_login';
    case PASSWORD_RESET = 'password_reset';
    case PASSWORD_CHANGED = 'password_changed';
    case TWO_FACTOR_ENABLED = 'two_factor_enabled';
    case TWO_FACTOR_DISABLED = 'two_factor_disabled';
    
    // Access events
    case VIEWED = 'viewed';
    case EXPORTED = 'exported';
    case PRINTED = 'printed';
    
    // Status changes
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUBMITTED = 'submitted';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::FORCE_DELETED => 'Permanently Deleted',
            self::LOGIN => 'Logged In',
            self::LOGOUT => 'Logged Out',
            self::FAILED_LOGIN => 'Failed Login',
            self::PASSWORD_RESET => 'Password Reset',
            self::PASSWORD_CHANGED => 'Password Changed',
            self::TWO_FACTOR_ENABLED => '2FA Enabled',
            self::TWO_FACTOR_DISABLED => '2FA Disabled',
            self::VIEWED => 'Viewed',
            self::EXPORTED => 'Exported',
            self::PRINTED => 'Printed',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SUBMITTED => 'Submitted',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'mdi-plus-circle',
            self::UPDATED => 'mdi-pencil',
            self::DELETED => 'mdi-delete',
            self::RESTORED => 'mdi-restore',
            self::FORCE_DELETED => 'mdi-delete-forever',
            self::LOGIN => 'mdi-login',
            self::LOGOUT => 'mdi-logout',
            self::FAILED_LOGIN => 'mdi-login-variant',
            self::PASSWORD_RESET, self::PASSWORD_CHANGED => 'mdi-key',
            self::TWO_FACTOR_ENABLED, self::TWO_FACTOR_DISABLED => 'mdi-shield',
            self::VIEWED => 'mdi-eye',
            self::EXPORTED => 'mdi-download',
            self::PRINTED => 'mdi-printer',
            self::APPROVED => 'mdi-check-circle',
            self::REJECTED => 'mdi-close-circle',
            self::SUBMITTED => 'mdi-send',
            self::CANCELLED => 'mdi-cancel',
            self::COMPLETED => 'mdi-check-all',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'success',
            self::UPDATED => 'info',
            self::DELETED, self::FORCE_DELETED => 'danger',
            self::RESTORED => 'warning',
            self::LOGIN, self::APPROVED, self::COMPLETED => 'success',
            self::LOGOUT => 'secondary',
            self::FAILED_LOGIN, self::REJECTED => 'danger',
            self::PASSWORD_RESET, self::PASSWORD_CHANGED => 'warning',
            default => 'primary',
        };
    }

    public function isModelEvent(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::UPDATED,
            self::DELETED,
            self::RESTORED,
            self::FORCE_DELETED,
        ]);
    }

    public function isAuthEvent(): bool
    {
        return in_array($this, [
            self::LOGIN,
            self::LOGOUT,
            self::FAILED_LOGIN,
            self::PASSWORD_RESET,
            self::PASSWORD_CHANGED,
            self::TWO_FACTOR_ENABLED,
            self::TWO_FACTOR_DISABLED,
        ]);
    }
}
```

---

## 5. Models

### AuditLog Model

**File:** `app/Models/AuditLog.php`

```php
<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    /**
     * Use the audit database connection
     */
    protected $connection = 'audit';

    protected $table = 'audit_logs';

    /**
     * Disable auto-timestamps - we only use created_at
     */
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_name',
        'user_email',
        'auditable_type',
        'auditable_id',
        'auditable_uuid',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'tags',
        'shop_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'tags' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $log) {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });

        // Prevent updates to audit logs (immutability)
        static::updating(function () {
            return false;
        });

        // Prevent deletion of audit logs (immutability)
        static::deleting(function () {
            return false;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the auditable model
     */
    public function auditable()
    {
        // Return null if model was deleted
        return $this->morphTo('auditable')->withTrashed();
    }

    /**
     * Get changed attributes with old and new values
     */
    public function getChangesAttribute(): array
    {
        $changes = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $changes[$key] = [
                'old' => $old[$key] ?? null,
                'new' => $new[$key] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * Get a human-readable model name
     */
    public function getModelNameAttribute(): string
    {
        return class_basename($this->auditable_type);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by model type
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('auditable_type', $modelClass);
    }

    /**
     * Scope: Filter by specific model instance
     */
    public function scopeForInstance($query, Model $model)
    {
        return $query->where('auditable_type', get_class($model))
                     ->where('auditable_id', $model->getKey());
    }

    /**
     * Scope: Filter by event type
     */
    public function scopeForEvent($query, AuditEvent|string $event)
    {
        $value = $event instanceof AuditEvent ? $event->value : $event;
        return $query->where('event', $value);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope: Filter by shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope: Only model events
     */
    public function scopeModelEvents($query)
    {
        return $query->whereIn('event', [
            AuditEvent::CREATED->value,
            AuditEvent::UPDATED->value,
            AuditEvent::DELETED->value,
            AuditEvent::RESTORED->value,
            AuditEvent::FORCE_DELETED->value,
        ]);
    }
}
```

### AuditSession Model

**File:** `app/Models/AuditSession.php`

```php
<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditSession extends Model
{
    protected $connection = 'audit';

    protected $table = 'audit_sessions';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_name',
        'event',
        'ip_address',
        'user_agent',
        'location',
        'device_type',
        'browser',
        'successful',
        'failure_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
            if (empty($session->created_at)) {
                $session->created_at = now();
            }
        });

        // Immutability
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('successful', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('successful', false);
    }
}
```

### AuditExport Model

**File:** `app/Models/AuditExport.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuditExport extends Model
{
    protected $connection = 'audit';

    protected $table = 'audit_exports';

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'format',
        'status',
        'filters',
        'file_path',
        'record_count',
        'started_at',
        'completed_at',
        'error_message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $export) {
            if (empty($export->uuid)) {
                $export->uuid = (string) Str::uuid();
            }
            $export->expires_at = now()->addDays(7);
        });

        static::deleting(function (self $export) {
            if ($export->file_path) {
                Storage::disk('audit-exports')->delete($export->file_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $filePath, int $recordCount): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'record_count' => $recordCount,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
```

---

## 6. Auditable Trait

**File:** `app/Traits/Auditable.php`

```php
<?php

namespace App\Traits;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Boot the trait
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit(AuditEvent::CREATED, [], $model->getAuditableAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getAuditableChanges();
            if (!empty($changes['old']) || !empty($changes['new'])) {
                $model->logAudit(AuditEvent::UPDATED, $changes['old'], $changes['new']);
            }
        });

        static::deleted(function (Model $model) {
            $event = $model->isForceDeleting() 
                ? AuditEvent::FORCE_DELETED 
                : AuditEvent::DELETED;
            $model->logAudit($event, $model->getAuditableAttributes(), []);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->logAudit(AuditEvent::RESTORED, [], $model->getAuditableAttributes());
            });
        }
    }

    /**
     * Create an audit log entry
     */
    public function logAudit(AuditEvent $event, array $oldValues = [], array $newValues = []): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'auditable_uuid' => $this->uuid ?? null,
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'tags' => $this->getAuditTags(),
            'shop_id' => $this->getAuditShopId(),
        ]);
    }

    /**
     * Get attributes to audit
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        $hidden = $this->getAuditHidden();
        
        return collect($attributes)
            ->except($hidden)
            ->toArray();
    }

    /**
     * Get changes for audit
     */
    protected function getAuditableChanges(): array
    {
        $changes = $this->getChanges();
        $hidden = $this->getAuditHidden();
        $original = $this->getOriginal();

        $old = [];
        $new = [];

        foreach ($changes as $key => $value) {
            if (in_array($key, $hidden)) {
                continue;
            }
            if ($key === 'updated_at') {
                continue;
            }
            $old[$key] = $original[$key] ?? null;
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Attributes to exclude from audit
     */
    protected function getAuditHidden(): array
    {
        return property_exists($this, 'auditHidden') 
            ? $this->auditHidden 
            : ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    /**
     * Get tags for the audit log
     */
    protected function getAuditTags(): ?array
    {
        return property_exists($this, 'auditTags') ? $this->auditTags : null;
    }

    /**
     * Get shop ID for multi-shop context
     */
    protected function getAuditShopId(): ?int
    {
        if (property_exists($this, 'shop_id')) {
            return $this->shop_id;
        }
        if (method_exists($this, 'shop')) {
            return $this->shop?->id;
        }
        return null;
    }

    /**
     * Get all audit logs for this model
     */
    public function auditLogs()
    {
        return AuditLog::forInstance($this)->latest('created_at');
    }

    /**
     * Log custom audit event
     */
    public function logCustomAudit(AuditEvent $event, array $metadata = []): AuditLog
    {
        return $this->logAudit($event, [], $metadata);
    }
}
```

---

## 7. Services

### AuditService

**File:** `app/Services/AuditService.php`

```php
<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditExport;
use App\Models\AuditLog;
use App\Models\AuditSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Search audit logs with filters
     */
    public function search(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = AuditLog::query()->latest('created_at');

        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (!empty($filters['model'])) {
            $query->forModel($filters['model']);
        }

        if (!empty($filters['event'])) {
            $query->forEvent($filters['event']);
        }

        if (!empty($filters['shop_id'])) {
            $query->forShop($filters['shop_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('auditable_uuid', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get audit history for a specific model
     */
    public function getModelHistory($model): Collection
    {
        return AuditLog::forInstance($model)
            ->latest('created_at')
            ->get();
    }

    /**
     * Get user activity log
     */
    public function getUserActivity(User $user, int $limit = 50): Collection
    {
        return AuditLog::byUser($user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Log authentication session
     */
    public function logSession(
        User $user,
        AuditEvent $event,
        bool $successful = true,
        ?string $failureReason = null
    ): AuditSession {
        $userAgent = Request::userAgent();
        
        return AuditSession::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'event' => $event,
            'ip_address' => Request::ip(),
            'user_agent' => $userAgent,
            'location' => $this->getLocationFromIp(Request::ip()),
            'device_type' => $this->getDeviceType($userAgent),
            'browser' => $this->getBrowser($userAgent),
            'successful' => $successful,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * Get session history for user
     */
    public function getSessionHistory(User $user, int $limit = 20): Collection
    {
        return AuditSession::forUser($user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activity statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = AuditLog::query();

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (!empty($filters['shop_id'])) {
            $query->forShop($filters['shop_id']);
        }

        return [
            'total_logs' => (clone $query)->count(),
            'by_event' => (clone $query)
                ->selectRaw('event, COUNT(*) as count')
                ->groupBy('event')
                ->pluck('count', 'event')
                ->toArray(),
            'by_model' => (clone $query)
                ->selectRaw('auditable_type, COUNT(*) as count')
                ->groupBy('auditable_type')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'auditable_type')
                ->mapWithKeys(fn ($count, $type) => [class_basename($type) => $count])
                ->toArray(),
            'by_user' => (clone $query)
                ->selectRaw('user_id, user_name, COUNT(*) as count')
                ->groupBy('user_id', 'user_name')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'user_id' => $row->user_id,
                    'user_name' => $row->user_name,
                    'count' => $row->count,
                ])
                ->toArray(),
            'timeline' => $this->getTimeline($filters),
        ];
    }

    /**
     * Get activity timeline
     */
    protected function getTimeline(array $filters): array
    {
        $days = 30;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = AuditLog::whereDate('created_at', $date)
                ->when(!empty($filters['shop_id']), fn ($q) => $q->forShop($filters['shop_id']))
                ->count();

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('M d'),
                'count' => $count,
            ];
        }

        return $data;
    }

    /**
     * Export audit logs
     */
    public function createExport(User $user, array $filters, string $format = 'csv'): AuditExport
    {
        return AuditExport::create([
            'user_id' => $user->id,
            'name' => 'Audit Export - ' . now()->format('Y-m-d H:i'),
            'format' => $format,
            'status' => 'pending',
            'filters' => $filters,
        ]);
    }

    /**
     * Clean old audit logs (retention policy)
     */
    public function cleanOldLogs(int $retentionDays = 365): int
    {
        // Note: Consider archiving instead of deleting for compliance
        $cutoffDate = now()->subDays($retentionDays);

        // Actually, per immutability requirement, we should NOT delete
        // Instead, archive to cold storage
        return 0;
    }

    /**
     * Get location from IP (simplified)
     */
    protected function getLocationFromIp(?string $ip): ?string
    {
        // Implement with GeoIP service if needed
        return null;
    }

    /**
     * Parse device type from user agent
     */
    protected function getDeviceType(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (preg_match('/mobile/i', $userAgent)) {
            return 'Mobile';
        }
        if (preg_match('/tablet/i', $userAgent)) {
            return 'Tablet';
        }
        return 'Desktop';
    }

    /**
     * Parse browser from user agent
     */
    protected function getBrowser(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (preg_match('/Chrome/i', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/Firefox/i', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/Safari/i', $userAgent)) {
            return 'Safari';
        }
        if (preg_match('/Edge/i', $userAgent)) {
            return 'Edge';
        }

        return 'Other';
    }
}
```

---

## 8. Event Listeners for Authentication

### AuthenticationLogger

**File:** `app/Listeners/LogAuthentication.php`

```php
<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

class LogAuthentication
{
    public function __construct(private AuditService $auditService) {}

    public function handleLogin(Login $event): void
    {
        $this->auditService->logSession($event->user, AuditEvent::LOGIN);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            $this->auditService->logSession($event->user, AuditEvent::LOGOUT);
        }
    }

    public function handleFailed(Failed $event): void
    {
        if ($event->user) {
            $this->auditService->logSession(
                $event->user,
                AuditEvent::FAILED_LOGIN,
                false,
                'Invalid credentials'
            );
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->auditService->logSession($event->user, AuditEvent::PASSWORD_RESET);
    }
}
```

**Register in EventServiceProvider or `bootstrap/app.php`:**

```php
use App\Listeners\LogAuthentication;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

// In bootstrap/app.php
->withEvents([
    Login::class => LogAuthentication::class . '@handleLogin',
    Logout::class => LogAuthentication::class . '@handleLogout',
    Failed::class => LogAuthentication::class . '@handleFailed',
    PasswordReset::class => LogAuthentication::class . '@handlePasswordReset',
])
```

---

## 9. Controllers

### AuditLogController

**File:** `app/Http/Controllers/AuditLogController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Models\AuditExport;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->only([
            'user_id', 'model', 'event', 'shop_id', 
            'start_date', 'end_date', 'search'
        ]);

        $logs = $this->auditService->search($filters);

        $users = User::select('id', 'name')->orderBy('name')->get();
        $shops = Shop::select('id', 'name')->orderBy('name')->get();
        $events = AuditEvent::cases();

        $models = AuditLog::distinct()
            ->pluck('auditable_type')
            ->map(fn ($type) => [
                'value' => $type,
                'label' => class_basename($type),
            ]);

        return view('audit.index', compact('logs', 'users', 'shops', 'events', 'models', 'filters'));
    }

    public function show(AuditLog $log): View
    {
        $this->authorize('view', $log);

        return view('audit.show', compact('log'));
    }

    public function statistics(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->only(['start_date', 'end_date', 'shop_id']);
        $statistics = $this->auditService->getStatistics($filters);

        if ($request->wantsJson()) {
            return response()->json($statistics);
        }

        $shops = Shop::select('id', 'name')->orderBy('name')->get();

        return view('audit.statistics', compact('statistics', 'shops', 'filters'));
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $modelClass = $request->model_type;
        
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Invalid model'], 400);
        }

        $model = $modelClass::find($request->model_id);
        
        if (!$model) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        $history = $this->auditService->getModelHistory($model);

        return response()->json($history);
    }

    public function userActivity(User $user): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $activity = $this->auditService->getUserActivity($user);
        $sessions = $this->auditService->getSessionHistory($user);

        return view('audit.user-activity', compact('user', 'activity', 'sessions'));
    }

    public function export(Request $request)
    {
        $this->authorize('export', AuditLog::class);

        $request->validate([
            'format' => 'required|in:csv,json',
            'filters' => 'nullable|array',
        ]);

        $export = $this->auditService->createExport(
            auth()->user(),
            $request->filters ?? [],
            $request->format
        );

        // Dispatch export job
        \App\Jobs\ProcessAuditExport::dispatch($export);

        return back()->with('success', 'Export started. You will be notified when ready.');
    }

    public function exports(): View
    {
        $exports = AuditExport::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('audit.exports', compact('exports'));
    }

    public function download(AuditExport $export)
    {
        $this->authorize('download', $export);

        if ($export->status !== 'completed' || !$export->file_path) {
            return back()->with('error', 'Export not ready.');
        }

        return \Storage::disk('audit-exports')->download(
            $export->file_path,
            $export->name . '.' . $export->format
        );
    }
}
```

---

## 10. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\AuditLogController;

Route::middleware(['auth'])->group(function () {
    // Audit Logs
    Route::prefix('audit')->name('audit.')->middleware('permission:audit.view')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/statistics', [AuditLogController::class, 'statistics'])->name('statistics');
        Route::get('/user/{user}', [AuditLogController::class, 'userActivity'])->name('user-activity');
        Route::get('/history', [AuditLogController::class, 'history'])->name('history');
        Route::get('/{log}', [AuditLogController::class, 'show'])->name('show');
        
        Route::middleware('permission:audit.export')->group(function () {
            Route::post('/export', [AuditLogController::class, 'export'])->name('export');
            Route::get('/exports', [AuditLogController::class, 'exports'])->name('exports');
            Route::get('/exports/{export}/download', [AuditLogController::class, 'download'])->name('download');
        });
    });
});
```

---

## 11. Permissions

```php
'audit.view',
'audit.export',
'audit.manage', // For retention policies
```

---

## 12. Apply Auditable Trait to Models

Add the `Auditable` trait to all models that need audit logging:

```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;

    // Optionally define attributes to hide from audit
    protected array $auditHidden = ['some_secret_field'];

    // Optionally define tags for categorization
    protected array $auditTags = ['inventory', 'product'];
}
```

**Apply to these models:**
- User
- Shop
- Product
- ProductVariation
- Category
- Supplier
- StockIntake
- Sale
- Payment
- CreditAccount
- Expense
- Campaign
- Alert
- PriceHistory

---

## 13. Artisan Commands

### Audit Migration Command

**File:** `app/Console/Commands/AuditMigrate.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AuditMigrate extends Command
{
    protected $signature = 'audit:migrate {--fresh : Drop all tables and re-run migrations}';

    protected $description = 'Run migrations for the audit database';

    public function handle(): int
    {
        $options = [
            '--database' => 'audit',
            '--path' => 'database/migrations/audit',
        ];

        if ($this->option('fresh')) {
            Artisan::call('migrate:fresh', $options);
        } else {
            Artisan::call('migrate', $options);
        }

        $this->info('Audit database migrations completed.');

        return Command::SUCCESS;
    }
}
```

---

## 14. Tests

**File:** `tests/Feature/AuditLogTest.php`

```php
<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Traits\Auditable;

test('model creation is audited', function () {
    // Ensure Product uses Auditable trait
    expect(in_array(Auditable::class, class_uses_recursive(Product::class)))->toBeTrue();

    $user = User::factory()->create();
    actingAs($user);

    $product = Product::factory()->create(['name' => 'Test Product']);

    $log = AuditLog::forInstance($product)->first();

    expect($log)
        ->not->toBeNull()
        ->and($log->event)->toBe(AuditEvent::CREATED)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->new_values['name'])->toBe('Test Product');
});

test('model update is audited with changes', function () {
    $user = User::factory()->create();
    actingAs($user);

    $product = Product::factory()->create(['name' => 'Old Name']);
    $product->update(['name' => 'New Name']);

    $log = AuditLog::forInstance($product)
        ->forEvent(AuditEvent::UPDATED)
        ->first();

    expect($log)
        ->not->toBeNull()
        ->and($log->old_values['name'])->toBe('Old Name')
        ->and($log->new_values['name'])->toBe('New Name');
});

test('model deletion is audited', function () {
    $user = User::factory()->create();
    actingAs($user);

    $product = Product::factory()->create();
    $productId = $product->id;
    $product->delete();

    $log = AuditLog::where('auditable_type', Product::class)
        ->where('auditable_id', $productId)
        ->forEvent(AuditEvent::DELETED)
        ->first();

    expect($log)->not->toBeNull();
});

test('audit logs are immutable', function () {
    $log = AuditLog::factory()->create();
    $originalEvent = $log->event;

    // Attempt to update
    $log->event = AuditEvent::DELETED;
    $result = $log->save();

    // Refresh and check
    $log->refresh();
    expect($log->event)->toBe($originalEvent);
});

test('audit logs cannot be deleted', function () {
    $log = AuditLog::factory()->create();
    $logId = $log->id;

    $log->delete();

    // Should still exist
    expect(AuditLog::find($logId))->not->toBeNull();
});

test('sensitive attributes are hidden from audit', function () {
    $user = User::factory()->create(['password' => 'secret']);

    $log = AuditLog::forInstance($user)
        ->forEvent(AuditEvent::CREATED)
        ->first();

    expect($log->new_values)->not->toHaveKey('password');
});

test('audit search filters work correctly', function () {
    $user = User::factory()->create();
    Product::factory()->count(5)->create();

    $service = app(\App\Services\AuditService::class);

    $results = $service->search([
        'model' => Product::class,
        'event' => AuditEvent::CREATED->value,
    ]);

    expect($results->count())->toBe(5);
});
```

---

## 15. Factory

**File:** `database/factories/AuditLogFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'auditable_uuid' => $product->uuid,
            'event' => $this->faker->randomElement(AuditEvent::cases()),
            'old_values' => null,
            'new_values' => ['name' => $this->faker->word],
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'url' => $this->faker->url,
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
        ];
    }
}
```

---

## 16. Verification Checklist

- [ ] Separate `audit` database connection configured
- [ ] Audit migrations run on audit connection
- [ ] `Auditable` trait applied to all relevant models
- [ ] Create, update, delete events logged
- [ ] Sensitive fields excluded from logs
- [ ] Audit logs are immutable (no update/delete)
- [ ] User and IP tracked on all logs
- [ ] Authentication events logged
- [ ] Audit viewer with filters works
- [ ] Audit export works
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass

---

## 17. Implementation Complete

This module completes the Stock Taking & Sales Management System implementation. All 19 modules (00-18) are now documented:

| Module | Name | Status |
|--------|------|--------|
| 00 | Master Plan | ✅ |
| 00 | Guidelines Compliance | ✅ |
| 01 | Authentication & Users | ✅ |
| 02 | Roles & Permissions | ✅ |
| 03 | Shops Management | ✅ |
| 04 | Categories | ✅ |
| 05 | Products & Variations | ✅ |
| 06 | Pricing Management | ✅ |
| 07 | Suppliers | ✅ |
| 08 | Stock Intake | ✅ |
| 09 | Inventory Tracking | ✅ |
| 10 | Sales Transactions | ✅ |
| 11 | Payments | ✅ |
| 12 | Credit Sales | ✅ |
| 13 | Expense Categories | ✅ |
| 14 | Expenses | ✅ |
| 15 | Advertising ROI | ✅ |
| 16 | Alerts & Notifications | ✅ |
| 17 | Reports & Dashboard | ✅ |
| 18 | Audit Logs | ✅ |
