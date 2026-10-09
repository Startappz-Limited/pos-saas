# Guidelines Compliance Document
## Stock Taking & Sales Management System

This document ensures all implementation modules follow the standards defined in `.ai/general/` folder.

---

## 1. Critical Standards Summary

Based on the `.ai/general` guidelines, all modules MUST follow these standards:

### 1.1 Database Design (0.3 guide)

**Dual ID System:**
```php
$table->id();                           // Internal use (joins, indexes)
$table->uuid('uuid')->unique();         // External use (URLs, APIs)
```

**Mandatory Base Columns (ALL tables):**
```php
$table->uuid('uuid')->unique();
$table->string('status')->default('active');  // Use Enum
$table->foreignId('created_by')->nullable()->constrained('users');
$table->foreignId('updated_by')->nullable()->constrained('users');
$table->timestamps();
```

**Status Fields:** Must use PHP 8.1+ native Enums with Laravel casting.

### 1.2 Models (0.3 & 0.9 guides)

**Required Model Configuration:**
```php
// Use UUID for route binding (NEVER expose integer IDs)
public function getRouteKeyName(): string
{
    return 'uuid';
}

// Auto-generate UUID and set audit fields
protected static function boot(): void
{
    parent::boot();
    
    static::creating(function ($model) {
        if (empty($model->uuid)) {
            $model->uuid = (string) \Illuminate\Support\Str::uuid();
        }
        $model->created_by = auth()->id();
    });
    
    static::updating(function ($model) {
        $model->updated_by = auth()->id();
    });
}

// Cast status to Enum
protected function casts(): array
{
    return [
        'status' => RecordStatus::class,
    ];
}
```

### 1.3 Roles & Permissions (0.4 guide)

**Package:** Use `spatie/laravel-permission` v6.0+

**Permission Naming:** `{module}.{action}` format
- `users.create`, `users.view`, `users.update`, `users.delete`
- `inventory.view`, `inventory.alerts.acknowledge`

**Authorization:** Use Policies, not inline controller checks.

### 1.4 Routing (0.8 guide)

**UUID-Only Route Binding:**
```php
// ✅ CORRECT - Uses UUID
Route::get('/users/{user:uuid}', [UserController::class, 'show']);

// Or set getRouteKeyName() on model and use:
Route::get('/users/{user}', [UserController::class, 'show']);

// ❌ WRONG - Exposes integer ID
Route::get('/users/{user}', ...);  // Without UUID binding
```

### 1.5 Audit Logging (0.5 guide)

**Separate Database:** Audit logs go to dedicated `audit` database connection.

**Automatic Logging:** Use Auditable trait on models.

### 1.6 Folder Structure (0.1 guide)

```
app/
├── Actions/           # Single-purpose action classes
├── Enums/             # PHP 8.1+ native enums
├── Http/Controllers/  # Controllers by domain
├── Models/            # Eloquent models
├── Services/          # Complex business logic
├── Jobs/              # Queued jobs by domain
└── Policies/          # Authorization policies
```

### 1.7 Security (1.0 guide)

- Form Request classes for ALL validation
- Use `$request->validated()` only
- Rate limiting on sensitive endpoints
- Authorization via Policies

---

## 2. Module Compliance Status

### Updates Required for Existing Modules

| Module | UUID Column | Audit Columns | Enum Status | Route UUID | Spatie Permissions |
|--------|-------------|---------------|-------------|------------|-------------------|
| 01: Auth | ⚠️ Add | ⚠️ Add | ⚠️ Add | ⚠️ Update | ⚠️ Migrate |
| 02: Roles | ⚠️ Add | ⚠️ Add | N/A | ⚠️ Update | ⚠️ Migrate |
| 03: Shops | ⚠️ Add | ⚠️ Add | ⚠️ Add | ⚠️ Update | ✅ |
| 04: Categories | ⚠️ Add | ⚠️ Add | ⚠️ Add | ⚠️ Update | ✅ |
| 05: Products | ⚠️ Add | ⚠️ Add | ⚠️ Add | ⚠️ Update | ✅ |
| 06: Pricing | ⚠️ Add | ⚠️ Add | ⚠️ Add | ⚠️ Update | ✅ |
| 07: Suppliers | ⚠️ Add | ⚠️ Add | ⚠️ Add | ⚠️ Update | ✅ |
| 08: Stock Intake | ⚠️ Add uuid | ⚠️ Add updated_by | ⚠️ Add | ⚠️ Update | ✅ |
| 09: Inventory | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 3. Standard Migration Template

Use this template for ALL new migrations:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table) {
            // Primary key (internal)
            $table->id();
            
            // UUID (external - for URLs/APIs)
            $table->uuid('uuid')->unique();
            
            // Domain fields
            // ...
            
            // Status (use Enum)
            $table->string('status')->default('active');
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes(); // Optional, if justified
            
            // Indexes
            $table->index('uuid');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
};
```

---

## 4. Standard Model Template

Use this template for ALL new models:

```php
<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExampleModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        // ... other fields
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = RecordStatus::ACTIVE;
            }
            $model->created_by = auth()->id();
        });

        static::updating(function (self $model) {
            $model->updated_by = auth()->id();
        });
    }

    /**
     * Route key for UUID binding (per 0.8 guide)
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope for active records
     */
    public function scopeActive($query)
    {
        return $query->where('status', RecordStatus::ACTIVE);
    }
}
```

---

## 5. Standard Enum Template

```php
<?php

namespace App\Enums;

enum RecordStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'warning',
            self::ARCHIVED => 'secondary',
        };
    }
}
```

---

## 6. Standard Controller Template

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExampleRequest;
use App\Http\Requests\UpdateExampleRequest;
use App\Models\Example;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExampleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Example::class);
        
        $examples = Example::active()->latest()->paginate(20);
        
        return view('examples.index', compact('examples'));
    }

    public function show(Example $example): View
    {
        $this->authorize('view', $example);
        
        return view('examples.show', compact('example'));
    }

    public function store(StoreExampleRequest $request): RedirectResponse
    {
        // Form Request handles authorization and validation
        $example = Example::create($request->validated());
        
        return redirect()->route('examples.show', $example->uuid)
            ->with('success', 'Example created successfully.');
    }

    public function update(UpdateExampleRequest $request, Example $example): RedirectResponse
    {
        $example->update($request->validated());
        
        return redirect()->route('examples.show', $example->uuid)
            ->with('success', 'Example updated successfully.');
    }

    public function destroy(Example $example): RedirectResponse
    {
        $this->authorize('delete', $example);
        
        $example->delete();
        
        return redirect()->route('examples.index')
            ->with('success', 'Example deleted successfully.');
    }
}
```

---

## 7. Standard Route Template

```php
use App\Http\Controllers\ExampleController;

Route::middleware(['auth'])->group(function () {
    // Resource routes with UUID binding via getRouteKeyName()
    Route::resource('examples', ExampleController::class)
        ->middleware('permission:examples.view');
    
    // Or explicit permission per action
    Route::prefix('examples')->name('examples.')->group(function () {
        Route::get('/', [ExampleController::class, 'index'])
            ->middleware('permission:examples.view');
        
        Route::get('/{example}', [ExampleController::class, 'show'])
            ->middleware('permission:examples.view');
        
        Route::post('/', [ExampleController::class, 'store'])
            ->middleware('permission:examples.create');
        
        Route::put('/{example}', [ExampleController::class, 'update'])
            ->middleware('permission:examples.update');
        
        Route::delete('/{example}', [ExampleController::class, 'destroy'])
            ->middleware('permission:examples.delete');
    });
});
```

---

## 8. Spatie Permission Integration

### Installation
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### Extend Tables with UUID
```php
// Migration to add UUID to Spatie tables
Schema::table('roles', function (Blueprint $table) {
    $table->uuid('uuid')->unique()->after('id');
    $table->string('status')->default('active')->after('guard_name');
});

Schema::table('permissions', function (Blueprint $table) {
    $table->uuid('uuid')->unique()->after('id');
    $table->string('module')->nullable()->after('name');
    $table->string('status')->default('active')->after('guard_name');
});
```

### User Model Trait
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

---

## 9. Audit Logging Setup

### Database Connection
```php
// config/database.php
'audit' => [
    'driver' => 'mysql',
    'host' => env('AUDIT_DB_HOST', '127.0.0.1'),
    'database' => env('AUDIT_DB_DATABASE', 'audit_db'),
    // ...
],
```

### Auditable Trait
```php
namespace App\Traits;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            self::logAudit($model, 'create');
        });

        static::updated(function ($model) {
            self::logAudit($model, 'update', $model->getOriginal());
        });

        static::deleted(function ($model) {
            self::logAudit($model, 'delete');
        });
    }

    private static function logAudit($model, string $action, array $oldValues = []): void
    {
        \App\Models\Audit\AuditLog::create([
            'entity_uuid' => $model->uuid,
            'entity_type' => get_class($model),
            'action' => $action,
            'old_values' => $action === 'update' ? $oldValues : null,
            'new_values' => $model->toArray(),
            'status' => 'completed',
            'actor_uuid' => auth()->user()?->uuid,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

---

## 10. Checklist for All Future Modules

Before implementing any module, ensure:

- [ ] Migration has `uuid` column
- [ ] Migration has `status` column with Enum default
- [ ] Migration has `created_by` and `updated_by` columns
- [ ] Model implements `getRouteKeyName()` returning 'uuid'
- [ ] Model auto-generates UUID in `boot()` method
- [ ] Model sets audit columns in `boot()` method
- [ ] Model casts status to Enum
- [ ] Controller uses Form Requests for validation
- [ ] Controller uses Policies for authorization
- [ ] Routes use UUID binding (via model's getRouteKeyName)
- [ ] Permissions follow `{module}.{action}` naming
- [ ] Tests verify authorization and validation
- [ ] Code formatted with Pint
