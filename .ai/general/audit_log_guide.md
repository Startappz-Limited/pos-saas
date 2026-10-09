# Audit Log Guide

## 5.1 Audit Philosophy

Auditing in this application is non-optional and non-negotiable.

**Core principles:**

- Audit data is immutable
- Audit storage is isolated
- Everything is traceable by UUID
- Auditing is automatic, not manual
- Audit failure must not break business flow

## 5.2 Separate Audit Database (Mandatory)

### Rationale

- Prevents audit tampering
- Improves performance isolation
- Supports compliance and forensics
- Enables long-term retention

### 5.2.1 Database Connection Configuration

Audit logs use a dedicated database connection.

**In `config/database.php`:**

```php
'connections' => [
    
    // ... other connections
    
    'audit' => [
        'driver' => 'mysql',
        'host' => env('AUDIT_DB_HOST', '127.0.0.1'),
        'port' => env('AUDIT_DB_PORT', '3306'),
        'database' => env('AUDIT_DB_DATABASE', 'audit'),
        'username' => env('AUDIT_DB_USERNAME', 'root'),
        'password' => env('AUDIT_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

**In `.env`:**

```env
AUDIT_DB_HOST=127.0.0.1
AUDIT_DB_PORT=3306
AUDIT_DB_DATABASE=audit_db
AUDIT_DB_USERNAME=audit_user
AUDIT_DB_PASSWORD=secure_password
```

## 5.3 Mandatory Audit Columns

Every audited record must include:

| Column | Type | Description |
|--------|------|-------------|
| `uuid` | UUID | Audit record identifier |
| `entity_uuid` | UUID | Affected entity UUID |
| `entity_type` | STRING | Model or domain name |
| `action` | STRING | `create`, `update`, `delete`, `status_change` |
| `old_values` | JSON | Before state |
| `new_values` | JSON | After state |
| `status` | STRING | Outcome state |
| `actor_uuid` | UUID | Who performed action |
| `ip_address` | STRING | Request IP |
| `user_agent` | STRING | Client info |
| `created_at` | TIMESTAMP | Audit timestamp |

## 5.4 Audit Table Schema

### Migration Example

```php
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
            $table->uuid('entity_uuid');
            $table->string('entity_type');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('status');
            $table->uuid('actor_uuid')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');
            
            // Indexes for performance
            $table->index('entity_uuid');
            $table->index('entity_type');
            $table->index('action');
            $table->index('actor_uuid');
            $table->index('created_at');
        });
    }
    
    public function down(): void
    {
        Schema::connection('audit')->dropIfExists('audit_logs');
    }
};
```

### Audit Model

```php
namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditLog extends Model
{
    use HasUuids;
    
    protected $connection = 'audit';
    
    public $timestamps = false; // Only created_at is used
    
    protected $fillable = [
        'uuid',
        'entity_uuid',
        'entity_type',
        'action',
        'old_values',
        'new_values',
        'status',
        'actor_uuid',
        'ip_address',
        'user_agent',
        'created_at',
    ];
    
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
    
    /**
     * Prevent updates to audit logs
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \Exception('Audit logs are immutable and cannot be updated.');
        }
        
        return parent::save($options);
    }
}
```

## 5.5 What Must Be Audited

### Mandatory

- ✅ Create
- ✅ Update
- ✅ Delete
- ✅ Status changes
- ✅ Role & permission changes
- ✅ Authentication events

### Optional

- Read access (high-risk modules only)

## 5.6 Audit Trigger Strategy

### Event-Based Auditing (Preferred)

**Flow:**

```
Model Event
   ↓
Domain Event
   ↓
Audit Listener
   ↓
Audit DB
```

### Implementation Example

**1. Create Audit Event:**

```php
namespace App\Events\Audit;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditableEvent
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public string $entityUuid,
        public string $entityType,
        public string $action,
        public ?array $oldValues,
        public ?array $newValues,
        public string $status,
        public ?string $actorUuid = null,
    ) {}
}
```

**2. Create Audit Listener:**

```php
namespace App\Listeners\Audit;

use App\Events\Audit\AuditableEvent;
use App\Models\Audit\AuditLog;
use Illuminate\Support\Str;

class AuditLogger
{
    public function handle(AuditableEvent $event): void
    {
        try {
            AuditLog::create([
                'uuid' => Str::uuid(),
                'entity_uuid' => $event->entityUuid,
                'entity_type' => $event->entityType,
                'action' => $event->action,
                'old_values' => $event->oldValues,
                'new_values' => $event->newValues,
                'status' => $event->status,
                'actor_uuid' => $event->actorUuid ?? auth()->user()?->uuid,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log failure but don't throw
            \Log::error('Audit log failed', [
                'entity_uuid' => $event->entityUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

**3. Register Event Listener:**

In `app/Providers/EventServiceProvider.php`:

```php
use App\Events\Audit\AuditableEvent;
use App\Listeners\Audit\AuditLogger;

protected $listen = [
    AuditableEvent::class => [
        AuditLogger::class,
    ],
];
```

**4. Use in Model Observer:**

```php
namespace App\Observers;

use App\Models\User\User;
use App\Events\Audit\AuditableEvent;

class UserObserver
{
    public function created(User $user): void
    {
        AuditableEvent::dispatch(
            entityUuid: $user->uuid,
            entityType: User::class,
            action: 'create',
            oldValues: null,
            newValues: $user->toArray(),
            status: 'success',
        );
    }
    
    public function updated(User $user): void
    {
        AuditableEvent::dispatch(
            entityUuid: $user->uuid,
            entityType: User::class,
            action: 'update',
            oldValues: $user->getOriginal(),
            newValues: $user->getChanges(),
            status: 'success',
        );
    }
    
    public function deleted(User $user): void
    {
        AuditableEvent::dispatch(
            entityUuid: $user->uuid,
            entityType: User::class,
            action: 'delete',
            oldValues: $user->toArray(),
            newValues: null,
            status: 'success',
        );
    }
}
```

### Forbidden

- ❌ Writing audit logs inside controllers
- ❌ Manual audit calls scattered in code
- ❌ Transaction coupling between main DB and audit DB

## 5.7 UUID & Status Enforcement

### Rules

- All audit entries use `uuid`
- `status` must be Enum-backed

### Enum Definition

```php
namespace App\Enums;

enum AuditStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';
}
```

### Usage

```php
use App\Enums\AuditStatus;

AuditLog::create([
    // ... other fields
    'status' => AuditStatus::SUCCESS->value,
]);
```

## 5.8 Failure Handling

### Rule

Audit failures must:

- Be logged internally
- Never block main transaction
- Trigger alerts if persistent

### Implementation

```php
class AuditLogger
{
    public function handle(AuditableEvent $event): void
    {
        try {
            AuditLog::create([/* ... */]);
        } catch (\Exception $e) {
            // Log the failure
            \Log::critical('Audit logging failed', [
                'entity_uuid' => $event->entityUuid,
                'entity_type' => $event->entityType,
                'action' => $event->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Optionally: Send alert to monitoring system
            // \Sentry::captureException($e);
            
            // DO NOT throw - allow main transaction to proceed
        }
    }
}
```

## 5.9 Data Sensitivity Rules

### Security Guidelines

- ❌ No passwords
- ❌ No secrets
- ✅ Mask PII where required
- ❌ Redact tokens

### Example Implementation

```php
class AuditLogger
{
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'api_token',
        'api_secret',
        'remember_token',
    ];
    
    protected array $piiFields = [
        'ssn',
        'tax_id',
        'credit_card',
    ];
    
    protected function sanitizeData(?array $data): ?array
    {
        if (!$data) {
            return null;
        }
        
        foreach ($this->sensitiveFields as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }
        
        foreach ($this->piiFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->maskValue($data[$field]);
            }
        }
        
        return $data;
    }
    
    protected function maskValue(string $value): string
    {
        return '***' . substr($value, -4);
    }
}
```

## 5.10 Access Control

### Rules

- Audit DB is read-only to application
- Write-only via audit service
- Access restricted to authorized roles

### Implementation

**Read-Only User (Database Level):**

```sql
-- Create read-only user for application queries
CREATE USER 'audit_readonly'@'%' IDENTIFIED BY 'secure_password';
GRANT SELECT ON audit_db.* TO 'audit_readonly'@'%';
FLUSH PRIVILEGES;
```

**Service Layer Access:**

```php
namespace App\Services\Audit;

use App\Models\Audit\AuditLog;
use Illuminate\Support\Collection;

class AuditQueryService
{
    /**
     * Query audit logs (read-only)
     */
    public function getEntityAudit(string $entityUuid): Collection
    {
        // User must have audit.view permission
        abort_unless(auth()->user()->can('audit.view'), 403);
        
        return AuditLog::where('entity_uuid', $entityUuid)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get actor's audit trail
     */
    public function getActorAudit(string $actorUuid): Collection
    {
        abort_unless(auth()->user()->can('audit.view'), 403);
        
        return AuditLog::where('actor_uuid', $actorUuid)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

## 5.11 Retention Policy

### Requirements

- **Minimum retention:** 12 months
- **Archival strategy required**
- **No hard deletes without approval**

### Implementation Strategy

```php
namespace App\Console\Commands;

use App\Models\Audit\AuditLog;
use Illuminate\Console\Command;

class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive';
    
    protected $description = 'Archive audit logs older than 12 months';
    
    public function handle(): void
    {
        $cutoffDate = now()->subMonths(12);
        
        // Export to archive storage (S3, etc.)
        $oldLogs = AuditLog::where('created_at', '<', $cutoffDate)->get();
        
        if ($oldLogs->isNotEmpty()) {
            // Export logic here
            $this->exportToArchive($oldLogs);
            
            $this->info("Archived {$oldLogs->count()} audit logs");
        }
        
        // DO NOT delete without approval process
    }
    
    protected function exportToArchive($logs): void
    {
        // Implementation for archiving to external storage
    }
}
```

## 5.12 Forbidden Audit Anti-Patterns

- ❌ Sharing main DB for audits
- ❌ Editing audit logs
- ❌ Logging numeric IDs (use UUIDs)
- ❌ Skipping actor details
- ❌ Coupling audit writes with main transactions
- ❌ Storing sensitive data unmasked
- ❌ Allowing application write access to audit DB

---

## Section 5 Status

- **Status:** COMPLETE
- **Enforcement Level:** Mandatory
- **Refers To:** Section 0 (Core Engineering Standards)