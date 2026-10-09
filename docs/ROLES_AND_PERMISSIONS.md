# Roles & Permissions — Implementation Guide

A replicable, end-to-end guide to how Role-Based Access Control (RBAC) is implemented in this
project. Follow this to reproduce the exact same authorization system in another **Laravel**
application.

---

## 1. Overview

The system is a **permission-driven RBAC** built on the
[`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission) package, layered with
Laravel's native **Policies** and **Gates**.

Core design principles:

- **Permissions are the unit of truth, not roles.** Code never checks "is this user a manager?".
  It checks "can this user do `sales.create`?". Roles are simply named bundles of permissions.
- **Roles are data, not code.** Roles and their permission sets are seeded into the database and
  can be edited at runtime through a UI — no deploy needed to change what a role can do.
- **Two enforcement layers:** Policies (model-level, used in controllers & Blade) and a
  super-admin **Gate bypass** (global override).
- **Permissions follow a `module.action` naming convention** (e.g. `products.create`,
  `sales.refund`, `users.full-access`).

```
User ──(model_has_roles)──> Role ──(role_has_permissions)──> Permission
   │                                                              ▲
   └──────────────(model_has_permissions, optional direct)───────┘
```

---

## 2. Dependencies & Configuration

### 2.1 Install the package

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

This publishes `config/permission.php` and a migration that creates five tables (see §3).

### 2.2 Key config (`config/permission.php`)

| Setting              | Value                                       | Notes                                    |
| -------------------- | ------------------------------------------- | ---------------------------------------- |
| Permission model     | `Spatie\Permission\Models\Permission::class`| Default                                  |
| Role model           | `App\Models\Role::class`                     | **Custom** — overridden to add a UUID    |
| Guard                | `web`                                        | Session-based auth                       |
| Cache expiry         | 24 hours                                      | Permissions are cached for performance   |
| Teams                | Disabled                                      | Single-tenant permission scope           |

> **Important:** because the Role model is customized, point `config/permission.php`'s
> `models.role` at `App\Models\Role::class`.

---

## 3. Database Schema

The Spatie migration creates these tables:

| Table                    | Purpose                                              |
| ------------------------ | ---------------------------------------------------- |
| `permissions`            | Every permission (`id`, `name`, `guard_name`)        |
| `roles`                  | Every role (`id`, `uuid`, `name`, `guard_name`)      |
| `role_has_permissions`   | Pivot: which permissions belong to which role        |
| `model_has_roles`        | Pivot: which roles are assigned to which user        |
| `model_has_permissions`  | Pivot: permissions granted directly to a user (rare) |

The `users` table itself only stores identity/profile fields plus a `status` column
(`active` / `inactive` / `suspended`) — **status is independent of permissions** (a user can hold a
role but be suspended and unable to log in).

---

## 4. Models

### 4.1 User model — `app/Models/User.php`

Add the `HasRoles` trait. This trait provides `assignRole()`, `syncRoles()`, `hasRole()`,
`can()`, `hasPermissionTo()`, etc.

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
    // ...
}
```

### 4.2 Custom Role model — `app/Models/Role.php`

The Spatie `Role` is extended only to add a UUID (used for route-model binding so role IDs aren't
exposed in URLs):

```php
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['uuid', 'name', 'guard_name'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $role) => $role->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
```

---

## 5. Defining Permissions (the catalog)

All permissions are declared in one seeder and grouped by module. Use the
**`module.action`** convention. Each module also has a `*.full-access` permission that policies
treat as a "bypass this module's checks" super-permission.

`database/seeders/PermissionSeeder.php`:

```php
$permissions = [
    // Users Management
    'users.view', 'users.create', 'users.update', 'users.delete',
    'users.activate', 'users.deactivate', 'users.suspend', 'users.full-access',

    // Roles & Permissions Management
    'roles.view', 'roles.create', 'roles.update', 'roles.delete',
    'roles.assign-permissions', 'roles.full-access', 'permissions.view',

    // Sales Management (note the *-all variants for cross-scope access)
    'sales.view', 'sales.view-all', 'sales.create', 'sales.update',
    'sales.edit-all', 'sales.delete', 'sales.delete-all',
    'sales.void', 'sales.refund', 'sales.restore', 'sales.full-access',

    // ... ~75+ permissions across products, inventory, payments,
    //     expenses, reports, settings, etc.
];

foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
}
```

**Naming patterns worth replicating:**

- `module.view` / `.create` / `.update` / `.delete` — standard CRUD.
- `module.view-all`, `module.edit-all`, `module.delete-all` — elevated, cross-scope variants
  (e.g. view *all* shops' sales vs. only your own).
- `module.<verb>` — domain actions (`sales.void`, `sales.refund`, `expenses.approve`,
  `purchase_orders.approve`).
- `module.full-access` — module-level override checked in policies' `before()` hook.

---

## 6. Defining Roles & Mapping Permissions

Roles are created and have permissions attached in `database/seeders/RoleSeeder.php`. The roles in
this system are:

| Role              | Description                                            |
| ----------------- | ----------------------------------------------------- |
| `super-admin`     | Complete system access (all permissions + Gate bypass)|
| `manager`         | Shops, inventory, sales, reports — incl. approvals     |
| `cashier`         | Sales, payments, returns, basic inventory             |
| `inventory-clerk` | Inventory, stock intake, products                      |
| `accountant`      | Expenses, payments, credit, financial reports          |
| `customer`        | View products + dashboard only                         |

```php
public function run(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions(); // clear cache first

    $roles = [
        ['name' => 'super-admin', 'description' => 'Has complete system access'],
        ['name' => 'manager',     'description' => 'Manages shops, inventory, sales, reports'],
        // ...
    ];

    foreach ($roles as $r) {
        Role::firstOrCreate(['name' => $r['name']], ['guard_name' => 'web']);
    }

    $this->assignSuperAdminPermissions();
    $this->assignManagerPermissions();
    // ... one method per role
}

// Super admin gets everything:
private function assignSuperAdminPermissions(): void
{
    Role::findByName('super-admin')->givePermissionTo(Permission::all());
}

// Each other role gets an explicit allow-list:
private function assignCashierPermissions(): void
{
    Role::findByName('cashier')->givePermissionTo([
        'products.view',
        'inventory.view', 'inventory.count',
        'sales.view', 'sales.create', 'sales.update', 'sales.refund',
        'returns.view', 'returns.create', 'returns.update',
        'payments.view', 'payments.create',
        'dashboard.view', 'reports.view', 'reports.sales',
    ]);
}
```

> **Convention:** each role gets its own `assign<Role>Permissions()` method with an explicit,
> readable allow-list. This makes the permission matrix self-documenting and easy to diff.

### Seeding order — `database/seeders/DatabaseSeeder.php`

Order matters: permissions must exist before roles reference them, and roles before users.

```php
$this->call([
    PermissionSeeder::class,  // 1. create permissions
    RoleSeeder::class,        // 2. create roles + attach permissions
    UserSeeder::class,        // 3. create users + assign roles
    ShopSeeder::class,
]);
```

---

## 7. Assigning Roles to Users

### Via seeder — `database/seeders/UserSeeder.php`

```php
$admin = User::create([...]);
$admin->assignRole('super-admin');

$cashier = User::create([...]);
$cashier->assignRole('cashier');
```

### Via application code — `app/Services/UserService.php`

User create/update flows use `syncRoles()` so the assigned set always matches the submitted form:

```php
public function create(array $data): User
{
    $user = $this->createUserAction->execute($data);
    if (!empty($data['roles'])) {
        $user->syncRoles($data['roles']); // replaces all roles with the given set
    }
    return $user;
}
```

`assignRole()` adds a role; `syncRoles([...])` replaces the full set; `removeRole()` detaches one.

---

## 8. Enforcement Layer 1 — The Super-Admin Gate

Registered in `app/Providers/AppServiceProvider.php`. `Gate::before` runs **before every
authorization check** in the app. Returning `true` grants; returning `null` falls through to the
normal policy.

```php
public function boot(): void
{
    // Super-admin bypasses ALL permission checks, everywhere.
    Gate::before(function ($user, $ability) {
        return $user->hasRole('super-admin') ? true : null;
    });

    // Register model policies
    Gate::policy(Role::class, RolePolicy::class);
    Gate::policy(Sale::class, SalePolicy::class);
    Gate::policy(Shop::class, ShopPolicy::class);
    // ... one line per policy
}
```

> Models in the same namespace as their policy (e.g. `User` → `UserPolicy`) are auto-discovered by
> Laravel and don't need an explicit `Gate::policy()` line. Models whose policy name doesn't follow
> the convention are registered explicitly here.

---

## 9. Enforcement Layer 2 — Policies

Every protected model has a Policy class in `app/Policies/`. Policies translate the
generic ability names (`viewAny`, `create`, `update`, `delete`) into permission checks via
`$user->can('permission.name')`.

`app/Policies/UserPolicy.php`:

```php
class UserPolicy
{
    // Module-level override: anyone with `users.full-access` skips every check below.
    public function before(User $user, string $ability): ?bool
    {
        return $user->can('users.full-access') ? true : null;
    }

    public function viewAny(User $user): bool   { return $user->can('users.view'); }
    public function view(User $user, User $m): bool   { return $user->can('users.view'); }
    public function create(User $user): bool    { return $user->can('users.create'); }
    public function update(User $user, User $m): bool { return $user->can('users.update'); }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;            // business rule: cannot delete yourself
        }
        return $user->can('users.delete');
    }
}
```

Policies are also where **business rules** live alongside permission checks, e.g. in
`RolePolicy::delete()`:

```php
public function delete(User $user, Role $role): bool
{
    if ($role->name === 'super-admin') {
        return false;                // never deletable, even with the permission
    }
    return $user->can('roles.delete');
}
```

**Two override tiers to note:**

1. `Gate::before` → `super-admin` role bypasses everything globally.
2. `Policy::before` → `<module>.full-access` permission bypasses that one module's checks.

---

## 10. Enforcement Layer 3 — Controllers, Routes & Views

### Controllers — `$this->authorize()`

Each controller action authorizes against the policy before doing work:

```php
public function index(): View
{
    $this->authorize('viewAny', User::class);   // → UserPolicy::viewAny()
    // ...
}

public function destroy(User $user): RedirectResponse
{
    $this->authorize('delete', $user);          // → UserPolicy::delete($user)
    // ...
}
```

A failed check throws `AuthorizationException` → HTTP 403.

### Routes — authentication middleware

Authorization is enforced at the controller/policy level; routes enforce **authentication** (and
rate limiting):

```php
Route::middleware('auth')->group(function () {
    Route::resource('users', UserController::class);
    Route::post('sales', [SaleController::class, 'store'])->middleware('throttle:20,1');
    // ...
});
```

> You can also gate routes directly with Spatie's middleware
> (`->middleware('permission:sales.create')` or `->middleware('role:manager')`) — this project
> prefers policy checks in controllers for finer-grained, testable control.

### Blade views — `@can`

The same policies drive UI rendering, so users only see actions they're allowed to perform:

```blade
@can('create', App\Models\StockAdjustment::class)
    <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary">New Adjustment</a>
@endcan

@can('approve', $adjustment)
    <button>Approve</button>
@endcan
```

Spatie also adds `@role('manager')` and `@haspermission('sales.refund')` directives if you need to
branch on roles/permissions directly.

---

## 11. Editing Roles at Runtime

Because roles & permissions are data, an admin UI manages them. `RoleController` uses
`syncPermissions()` to overwrite a role's permission set from submitted checkboxes:

```php
public function store(Request $request)
{
    $this->authorize('create', Role::class);

    $validated = $request->validate([
        'name'          => ['required', 'string', 'unique:roles,name', 'regex:/^[a-z0-9-]+$/'],
        'permissions'   => 'array',
        'permissions.*' => 'exists:permissions,id',
    ]);

    $role = Role::create(['name' => $validated['name']]);

    if (!empty($validated['permissions'])) {
        $role->syncPermissions(
            Permission::whereIn('id', $validated['permissions'])->get()
        );
    }
    // ...
}
```

> After changing roles/permissions programmatically, clear the cache:
> `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();`
> (or `php artisan permission:cache-reset`).

---

## 12. Request Authorization Flow (end to end)

```
HTTP request
   │
   ▼
[auth middleware]  ── not logged in ──> redirect to login
   │ authenticated
   ▼
Controller action → $this->authorize('update', $sale)
   │
   ▼
Gate::before  ── user is super-admin ──> ALLOW ✅
   │ not super-admin (returns null)
   ▼
SalePolicy::before  ── user has sales.full-access ──> ALLOW ✅
   │ no full-access (returns null)
   ▼
SalePolicy::update  →  $user->can('sales.edit-all' | 'sales.update') + business rules
   │
   ├── true  ──> ALLOW ✅ (action runs)
   └── false ──> AuthorizationException → 403 ❌
```

`$user->can(...)` resolves the permission by walking `model_has_roles → role_has_permissions`
(and `model_has_permissions` for any direct grants), all served from the 24-hour permission cache.

---

## 13. Replication Checklist

To stand this up in a new Laravel project:

1. `composer require spatie/laravel-permission`, publish config + migration, `php artisan migrate`.
2. Add `HasRoles` to the `User` model.
3. (Optional) Subclass the Spatie `Role` model to add a UUID; point `config/permission.php` at it.
4. Create `PermissionSeeder` — declare every permission using `module.action` naming, plus a
   `module.full-access` per module.
5. Create `RoleSeeder` — create roles, then one `assign<Role>Permissions()` allow-list per role;
   give `super-admin` all permissions.
6. Wire seeders in `DatabaseSeeder` in order: Permission → Role → User.
7. In `AppServiceProvider::boot()`, add the `Gate::before` super-admin bypass and register policies.
8. Generate a Policy per protected model (`php artisan make:policy XPolicy --model=X`); implement a
   `before()` full-access hook + per-ability `$user->can('module.action')` checks; embed business
   rules here.
9. Call `$this->authorize(...)` in every controller action; wrap routes in `auth` middleware.
10. Gate UI with `@can` in Blade.
11. Build a role-management UI using `syncPermissions()` for runtime editing; reset the permission
    cache after changes.

---

## 14. File Reference

| Concern                         | File                                                          |
| ------------------------------- | ------------------------------------------------------------ |
| Package config                  | `config/permission.php`                                       |
| User model (`HasRoles`)         | `app/Models/User.php`                                         |
| Custom Role model               | `app/Models/Role.php`                                         |
| Permission catalog              | `database/seeders/PermissionSeeder.php`                       |
| Role → permission mapping       | `database/seeders/RoleSeeder.php`                             |
| User → role assignment (seed)   | `database/seeders/UserSeeder.php`                             |
| User → role assignment (app)    | `app/Services/UserService.php`                                |
| Super-admin Gate + policy reg.  | `app/Providers/AppServiceProvider.php`                        |
| Policies                        | `app/Policies/*.php` (e.g. `UserPolicy.php`, `RolePolicy.php`)|
| Controller enforcement          | `app/Http/Controllers/*.php` (`$this->authorize(...)`)        |
| Runtime role editing            | `app/Http/Controllers/RoleController.php`                     |
| View-level checks               | `resources/views/**/*.blade.php` (`@can`)                     |
| Schema                          | `database/migrations/*_create_permission_tables.php`          |
