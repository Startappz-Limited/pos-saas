# Module 02: Roles & Permissions
## Stock Taking & Sales Management System

### Module Overview
Implement a role-based access control (RBAC) system to manage user permissions across the platform. This module uses Laravel's built-in Gate and Policy system with a custom roles/permissions structure.

**Priority:** P0 (Critical)  
**Dependencies:** Module 01 (Authentication & Users)  
**Estimated Time:** 2-3 days

---

## 1. Database Schema

### Roles Table

```sql
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->boolean('is_system')->default(false); // Prevent deletion of system roles
    $table->timestamps();
});
```

### Permissions Table

```sql
Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('group'); // e.g., 'users', 'shops', 'products'
    $table->text('description')->nullable();
    $table->timestamps();
});
```

### Role-Permission Pivot Table

```sql
Schema::create('role_permission', function (Blueprint $table) {
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
    $table->primary(['role_id', 'permission_id']);
});
```

### User-Role Pivot Table

```sql
Schema::create('role_user', function (Blueprint $table) {
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->primary(['role_id', 'user_id']);
});
```

### Default Roles

| Role | Slug | Description | System |
|------|------|-------------|--------|
| Super Admin | super-admin | Full system access | Yes |
| Manager | manager | Shop management access | Yes |
| Sales | sales | Sales and POS access | Yes |
| Accountant | accountant | Financial reporting access | Yes |
| Auditor | auditor | Read-only audit access | Yes |

### Default Permission Groups

| Group | Permissions |
|-------|-------------|
| users | view, create, edit, delete |
| roles | view, create, edit, delete, assign |
| shops | view, create, edit, delete |
| products | view, create, edit, delete |
| stock | view, create, edit, delete, adjust |
| sales | view, create, edit, delete, void |
| payments | view, create, edit, refund |
| credits | view, create, edit, delete, collect |
| expenses | view, create, edit, delete, approve |
| reports | view, export |
| settings | view, edit |

---

## 2. Models & Relationships

### Role Model

**File:** `app/Models/Role.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Permissions belonging to this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }

    /**
     * Grant permission to role
     */
    public function givePermission(Permission|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }
        
        $this->permissions()->syncWithoutDetaching($permission);
    }

    /**
     * Revoke permission from role
     */
    public function revokePermission(Permission|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->first();
        }
        
        if ($permission) {
            $this->permissions()->detach($permission);
        }
    }
}
```

### Permission Model

**File:** `app/Models/Permission.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'group',
        'description',
    ];

    /**
     * Roles with this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
```

### User Model Updates

**File:** `app/Models/User.php` (add to existing)

```php
// Add these imports
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Add these relationships and methods
/**
 * Roles assigned to this user
 */
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'role_user');
}

/**
 * Get all permissions through roles
 */
public function permissions(): array
{
    return $this->roles()
        ->with('permissions')
        ->get()
        ->pluck('permissions')
        ->flatten()
        ->pluck('slug')
        ->unique()
        ->toArray();
}

/**
 * Check if user has a specific role
 */
public function hasRole(string $roleSlug): bool
{
    return $this->roles()->where('slug', $roleSlug)->exists();
}

/**
 * Check if user has any of the given roles
 */
public function hasAnyRole(array $roleSlugs): bool
{
    return $this->roles()->whereIn('slug', $roleSlugs)->exists();
}

/**
 * Check if user has a specific permission
 */
public function hasPermission(string $permissionSlug): bool
{
    // Super admin has all permissions
    if ($this->hasRole('super-admin')) {
        return true;
    }
    
    return in_array($permissionSlug, $this->permissions());
}

/**
 * Check if user has any of the given permissions
 */
public function hasAnyPermission(array $permissionSlugs): bool
{
    if ($this->hasRole('super-admin')) {
        return true;
    }
    
    return count(array_intersect($permissionSlugs, $this->permissions())) > 0;
}

/**
 * Assign role to user
 */
public function assignRole(Role|string $role): void
{
    if (is_string($role)) {
        $role = Role::where('slug', $role)->firstOrFail();
    }
    
    $this->roles()->syncWithoutDetaching($role);
}

/**
 * Remove role from user
 */
public function removeRole(Role|string $role): void
{
    if (is_string($role)) {
        $role = Role::where('slug', $role)->first();
    }
    
    if ($role) {
        $this->roles()->detach($role);
    }
}

/**
 * Check if user is super admin
 */
public function isSuperAdmin(): bool
{
    return $this->hasRole('super-admin');
}
```

---

## 3. Controllers & Routes

### RoleController

**File:** `app/Http/Controllers/RoleController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])->paginate(10);
        
        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::all()->groupBy('group');
        
        return view('roles.create', compact('permissions'));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create($request->validated());
        
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }
        
        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role): View
    {
        $role->load(['permissions', 'users']);
        
        return view('roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        $permissions = Permission::all()->groupBy('group');
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        
        return view('roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());
        
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }
        
        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }
        
        $role->delete();
        
        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
```

### Routes

**File:** `routes/web.php` (add to authenticated routes)

```php
use App\Http\Controllers\RoleController;

Route::middleware('auth')->group(function () {
    // ... existing routes
    
    // Roles Management
    Route::resource('roles', RoleController::class);
    
    // Assign roles to users (add to user management routes later)
});
```

---

## 4. Form Requests (Validation)

### StoreRoleRequest

**File:** `app/Http/Requests/StoreRoleRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('roles.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->name),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a role name.',
            'name.unique' => 'This role name is already in use.',
            'permissions.*.exists' => 'One or more selected permissions are invalid.',
        ];
    }
}
```

### UpdateRoleRequest

**File:** `app/Http/Requests/UpdateRoleRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('roles.edit');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($this->role),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->name),
        ]);
    }
}
```

---

## 5. Middleware

### CheckPermission Middleware

**File:** `app/Http/Middleware/CheckPermission.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
```

### Register Middleware

**File:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'permission' => \App\Http\Middleware\CheckPermission::class,
    ]);
})
```

### Usage in Routes

```php
// Single permission
Route::get('/roles', [RoleController::class, 'index'])
    ->middleware('permission:roles.view');

// Multiple permissions (OR)
Route::get('/roles/create', [RoleController::class, 'create'])
    ->middleware('permission:roles.create,roles.edit');
```

---

## 6. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Roles List | `design/src/role-list.php` |
| Add Role | `design/src/role-add.php` |
| Edit Role | `design/src/role-edit.php` |
| Permissions | `design/src/pages-permissions.php` |

### View Structure

```
resources/views/
├── roles/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
└── components/
    └── permission-checkbox.blade.php
```

---

## 7. Seeders

### PermissionSeeder

**File:** `database/seeders/PermissionSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'group' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users'],
            
            // Roles
            ['name' => 'View Roles', 'slug' => 'roles.view', 'group' => 'roles'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'group' => 'roles'],
            ['name' => 'Edit Roles', 'slug' => 'roles.edit', 'group' => 'roles'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'group' => 'roles'],
            ['name' => 'Assign Roles', 'slug' => 'roles.assign', 'group' => 'roles'],
            
            // Shops
            ['name' => 'View Shops', 'slug' => 'shops.view', 'group' => 'shops'],
            ['name' => 'Create Shops', 'slug' => 'shops.create', 'group' => 'shops'],
            ['name' => 'Edit Shops', 'slug' => 'shops.edit', 'group' => 'shops'],
            ['name' => 'Delete Shops', 'slug' => 'shops.delete', 'group' => 'shops'],
            
            // Products
            ['name' => 'View Products', 'slug' => 'products.view', 'group' => 'products'],
            ['name' => 'Create Products', 'slug' => 'products.create', 'group' => 'products'],
            ['name' => 'Edit Products', 'slug' => 'products.edit', 'group' => 'products'],
            ['name' => 'Delete Products', 'slug' => 'products.delete', 'group' => 'products'],
            
            // Stock
            ['name' => 'View Stock', 'slug' => 'stock.view', 'group' => 'stock'],
            ['name' => 'Create Stock', 'slug' => 'stock.create', 'group' => 'stock'],
            ['name' => 'Edit Stock', 'slug' => 'stock.edit', 'group' => 'stock'],
            ['name' => 'Delete Stock', 'slug' => 'stock.delete', 'group' => 'stock'],
            ['name' => 'Adjust Stock', 'slug' => 'stock.adjust', 'group' => 'stock'],
            
            // Sales
            ['name' => 'View Sales', 'slug' => 'sales.view', 'group' => 'sales'],
            ['name' => 'Create Sales', 'slug' => 'sales.create', 'group' => 'sales'],
            ['name' => 'Edit Sales', 'slug' => 'sales.edit', 'group' => 'sales'],
            ['name' => 'Delete Sales', 'slug' => 'sales.delete', 'group' => 'sales'],
            ['name' => 'Void Sales', 'slug' => 'sales.void', 'group' => 'sales'],
            
            // Payments
            ['name' => 'View Payments', 'slug' => 'payments.view', 'group' => 'payments'],
            ['name' => 'Create Payments', 'slug' => 'payments.create', 'group' => 'payments'],
            ['name' => 'Edit Payments', 'slug' => 'payments.edit', 'group' => 'payments'],
            ['name' => 'Refund Payments', 'slug' => 'payments.refund', 'group' => 'payments'],
            
            // Credits
            ['name' => 'View Credits', 'slug' => 'credits.view', 'group' => 'credits'],
            ['name' => 'Create Credits', 'slug' => 'credits.create', 'group' => 'credits'],
            ['name' => 'Edit Credits', 'slug' => 'credits.edit', 'group' => 'credits'],
            ['name' => 'Delete Credits', 'slug' => 'credits.delete', 'group' => 'credits'],
            ['name' => 'Collect Credits', 'slug' => 'credits.collect', 'group' => 'credits'],
            
            // Expenses
            ['name' => 'View Expenses', 'slug' => 'expenses.view', 'group' => 'expenses'],
            ['name' => 'Create Expenses', 'slug' => 'expenses.create', 'group' => 'expenses'],
            ['name' => 'Edit Expenses', 'slug' => 'expenses.edit', 'group' => 'expenses'],
            ['name' => 'Delete Expenses', 'slug' => 'expenses.delete', 'group' => 'expenses'],
            ['name' => 'Approve Expenses', 'slug' => 'expenses.approve', 'group' => 'expenses'],
            
            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'group' => 'reports'],
            
            // Settings
            ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'settings'],
            ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'group' => 'settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}
```

### RoleSeeder

**File:** `database/seeders/RoleSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin - All permissions
        $superAdmin = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'is_system' => true,
            ]
        );
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // Manager
        $manager = Role::firstOrCreate(
            ['slug' => 'manager'],
            [
                'name' => 'Manager',
                'description' => 'Shop management with limited administrative access',
                'is_system' => true,
            ]
        );
        $managerPermissions = Permission::whereIn('group', [
            'shops', 'products', 'stock', 'sales', 'payments', 'credits', 'expenses', 'reports'
        ])->pluck('id');
        $manager->permissions()->sync($managerPermissions);

        // Sales
        $sales = Role::firstOrCreate(
            ['slug' => 'sales'],
            [
                'name' => 'Sales',
                'description' => 'Sales and POS operations',
                'is_system' => true,
            ]
        );
        $salesPermissions = Permission::whereIn('slug', [
            'products.view', 'stock.view',
            'sales.view', 'sales.create', 'sales.edit',
            'payments.view', 'payments.create',
            'credits.view', 'credits.create', 'credits.collect',
        ])->pluck('id');
        $sales->permissions()->sync($salesPermissions);

        // Accountant
        $accountant = Role::firstOrCreate(
            ['slug' => 'accountant'],
            [
                'name' => 'Accountant',
                'description' => 'Financial and expense management',
                'is_system' => true,
            ]
        );
        $accountantPermissions = Permission::whereIn('group', [
            'payments', 'credits', 'expenses', 'reports'
        ])->pluck('id');
        $accountant->permissions()->sync($accountantPermissions);

        // Auditor
        $auditor = Role::firstOrCreate(
            ['slug' => 'auditor'],
            [
                'name' => 'Auditor',
                'description' => 'Read-only access for auditing purposes',
                'is_system' => true,
            ]
        );
        $auditorPermissions = Permission::where('slug', 'like', '%.view')
            ->orWhere('slug', 'reports.export')
            ->pluck('id');
        $auditor->permissions()->sync($auditorPermissions);
    }
}
```

---

## 8. Tests (Pest)

**File:** `tests/Feature/RoleManagementTest.php`

```php
<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('super admin can view roles list', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get(route('roles.index'));

    $response->assertStatus(200);
    $response->assertViewHas('roles');
});

test('super admin can create a new role', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->post(route('roles.store'), [
        'name' => 'Custom Role',
        'description' => 'A custom test role',
        'permissions' => Permission::take(3)->pluck('id')->toArray(),
    ]);

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseHas('roles', ['slug' => 'custom-role']);
});

test('user without permission cannot access roles', function () {
    $user = User::factory()->create();
    $user->assignRole('sales');

    $response = $this->actingAs($user)->get(route('roles.index'));

    $response->assertStatus(403);
});

test('system roles cannot be deleted', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $systemRole = Role::where('is_system', true)->first();

    $response = $this->actingAs($user)->delete(route('roles.destroy', $systemRole));

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
});

test('user can be assigned a role', function () {
    $user = User::factory()->create();
    $role = Role::where('slug', 'manager')->first();

    $user->assignRole($role);

    expect($user->hasRole('manager'))->toBeTrue();
});

test('user permissions are inherited from roles', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    expect($user->hasPermission('products.view'))->toBeTrue();
    expect($user->hasPermission('settings.edit'))->toBeFalse();
});

test('super admin has all permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    expect($user->hasPermission('settings.edit'))->toBeTrue();
    expect($user->hasPermission('any.permission'))->toBeTrue();
});

test('role permissions can be updated', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    $role = Role::factory()->create();
    $permissions = Permission::take(5)->pluck('id')->toArray();

    $response = $this->actingAs($user)->put(route('roles.update', $role), [
        'name' => $role->name,
        'permissions' => $permissions,
    ]);

    $response->assertRedirect(route('roles.index'));
    expect($role->refresh()->permissions->count())->toBe(5);
});
```

**File:** `tests/Unit/UserRoleTest.php`

```php
<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('user can have multiple roles', function () {
    $user = User::factory()->create();
    
    $user->assignRole('manager');
    $user->assignRole('accountant');

    expect($user->roles->count())->toBe(2);
    expect($user->hasRole('manager'))->toBeTrue();
    expect($user->hasRole('accountant'))->toBeTrue();
});

test('user role can be removed', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');
    
    $user->removeRole('manager');

    expect($user->hasRole('manager'))->toBeFalse();
});

test('role can grant permission', function () {
    $role = Role::factory()->create();
    $permission = Permission::first();
    
    $role->givePermission($permission);

    expect($role->hasPermission($permission->slug))->toBeTrue();
});

test('role can revoke permission', function () {
    $role = Role::factory()->create();
    $permission = Permission::first();
    
    $role->givePermission($permission);
    $role->revokePermission($permission);

    expect($role->hasPermission($permission->slug))->toBeFalse();
});
```

---

## 9. Commands to Execute

Execute these commands in order:

```bash
# Step 1: Create Models with migrations, factories, seeders
php artisan make:model Role -mfs --no-interaction
php artisan make:model Permission -mfs --no-interaction

# Step 2: Create pivot table migrations
php artisan make:migration create_role_permission_table --no-interaction
php artisan make:migration create_role_user_table --no-interaction

# Step 3: Create Controller
php artisan make:controller RoleController --resource --no-interaction

# Step 4: Create Form Requests
php artisan make:request StoreRoleRequest --no-interaction
php artisan make:request UpdateRoleRequest --no-interaction

# Step 5: Create Middleware
php artisan make:middleware CheckPermission --no-interaction

# Step 6: Run migrations
php artisan migrate

# Step 7: Run seeders
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder

# Step 8: Create tests
php artisan make:test RoleManagementTest --pest --no-interaction
php artisan make:test Unit/UserRoleTest --pest --unit --no-interaction

# Step 9: Run tests
php artisan test --compact --filter=Role

# Step 10: Format code
vendor/bin/pint --dirty
```

---

## 10. Verification Checklist

Before proceeding to Module 03, verify:

- [ ] Roles table migration runs without errors
- [ ] Permissions table migration runs without errors
- [ ] Pivot tables created successfully
- [ ] Role model with all relationships and methods
- [ ] Permission model with relationships
- [ ] User model updated with role methods
- [ ] Permission seeder populates all permissions
- [ ] Role seeder creates default roles with correct permissions
- [ ] RoleController CRUD operations work
- [ ] CheckPermission middleware protects routes
- [ ] Super admin has access to all permissions
- [ ] Non-authorized users get 403 error
- [ ] All tests pass (`php artisan test --compact --filter=Role`)
- [ ] Code formatted with Pint

---

## 11. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 03: Shops Management](./03-shops-management.md)**
