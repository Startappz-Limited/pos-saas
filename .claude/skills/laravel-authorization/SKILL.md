---
name: laravel-authorization
description: Roles, permissions, and policy enforcement for this POS codebase using Spatie laravel-permission — permission naming ({resource}.{action} plus the -all / full-access variants used here), the seeded kebab-case role set, writing and registering policies, shop-access checks, Blade @can directives, the super-admin Gate::before bypass, and permission cache invalidation. Use when adding a model or controller action that needs authorization, writing or editing a policy, adding permissions or roles, seeding them, or gating UI elements.
---

# Roles, Permissions & Policies (POS)

Source: [0.4 roles_and_permissions_guide.md](../../../.ai/general/0.4%20roles_and_permissions_guide.md).
Package: **spatie/laravel-permission ^6.24**. Authorization is 🔴 mandatory on every resource action
([ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md)).

> Permissions grant access; roles just group them. Authorization is enforced server-side — the UI may
> reflect permissions but never enforces them.

## How this codebase actually enforces authorization

**Policies are the enforcement point, not route middleware.** There are 31 policies in
[app/Policies/](../../../app/Policies/) and `routes/web.php` uses **zero** `permission:` middleware —
26 controllers call `$this->authorize(...)`. Follow that: put the permission check inside the policy,
call the policy from the controller.

```php
// Controller — authorize, then delegate
public function show(Sale $sale): View
{
    $this->authorize('view', $sale);

    return view('sales.show', compact('sale'));
}
```

```php
// Policy — permission check + shop isolation (the pattern in SalePolicy)
public function view(User $user, Sale $sale): bool
{
    if ($user->can('sales.full-access')) {
        return true;
    }

    if ($user->can('sales.view-all') && $user->canAccessShop($sale->shop_id)) {
        return true;
    }

    return $user->can('sales.view') && $user->canAccessShop($sale->shop_id);
}
```

Two things every policy method must do:
1. Check a **permission** (`$user->can('sales.update')`) — never `hasRole()` for feature gating.
2. Check **shop access** (`$user->canAccessShop($model->shop_id)`) for any shop-owned model. A policy
   that only checks the permission lets a manager from shop A read shop B's data.

`AppServiceProvider::boot()` registers `Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null)`
— super-admin bypasses everything, so don't add per-policy super-admin branches for that purpose, and
remember the bypass means your shop check only protects non-super-admin roles.

## Permission naming

Format `{resource}.{action}`, resource plural, kebab-case actions. The seeded vocabulary in
[PermissionSeeder.php](../../../database/seeders/PermissionSeeder.php) — reuse these action names
instead of inventing synonyms:

- CRUD: `.view`, `.create`, `.update`, `.delete`
- Scope escalations: `.view-all`, `.edit-all`, `.delete-all` (act across shops, still gated by
  `canAccessShop`), `.full-access` (bypass for that resource)
- Lifecycle: `.activate`, `.deactivate`, `.suspend`
- Domain-specific: e.g. `roles.assign-permissions`

## Role naming — match the seeder, not the guide

⚠️ [0.4](../../../.ai/general/0.4%20roles_and_permissions_guide.md) §4.4 asks for Title Case role names
("Super Admin"). **The seeded roles are lowercase kebab-case**: `super-admin`, `manager`, `cashier`,
`inventory-clerk`, `accountant`, `customer`. Use these exact strings — `hasRole('Super Admin')` would
silently never match. Don't rename them.

## Adding permissions or roles

- **Seeder-driven only** — no manual DB inserts, no runtime `Permission::create()` in app code.
- Add to [PermissionSeeder.php](../../../database/seeders/PermissionSeeder.php) /
  [RoleSeeder.php](../../../database/seeders/RoleSeeder.php); keep them **idempotent**
  (`firstOrCreate`), so re-running is safe.
- Both seeders call `app()[PermissionRegistrar::class]->forgetCachedPermissions()` first — keep that.
- Run **only** the specific seeder, and only when asked:
  `php artisan db:seed --class=PermissionSeeder`. Never the full `DatabaseSeeder` blindly.
- Grant the new permission to the roles that need it in `RoleSeeder`, then reset the cache
  (`php artisan permission:cache-reset`) — Spatie caches permissions, so a new permission appears not
  to work until the cache clears.

`App\Models\Role` extends `Spatie\Permission\Models\Role`, adds a `uuid` (generated in `creating`) and
`getRouteKeyName() => 'uuid'`. If you add a custom Permission model, follow the same shape.

## Registering a new policy

`AppServiceProvider::boot()` registers policies **explicitly** via `Gate::policy(Model::class, ModelPolicy::class)`.
Auto-discovery works for conventionally named pairs, but this project registers them by hand — add your
new policy there so it can't silently not apply.

Standard methods: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, plus
domain actions (`void`, `close`, `approve`). Keep business constraints in the policy where they belong
— e.g. `SalePolicy::update()` also limits edits to a time window.

## Blade

```blade
@can('create', App\Models\Sale::class)
    <a href="{{ route('sales.create') }}" class="btn btn-primary">{{ __('New Sale') }}</a>
@endcan

@can('update', $sale)
    <a href="{{ route('sales.edit', $sale->uuid) }}" class="btn btn-secondary">{{ __('Edit') }}</a>
@endcan
```

- Prefer model-based `@can` so the policy's shop check runs, over a bare permission name.
- UI gating is cosmetic — the controller must still authorize.
- Disabled actions should explain the missing permission rather than vanish, where that's clearer.

## API authorization

Every API endpoint authorizes too (`403` on failure, with no permission-name leakage in the message).
Shop access is checked before querying — see `Api\SaleController::index()` (`canAccessShop`) and the
`visibleTo($user)` scope. See [laravel-api](../laravel-api/SKILL.md).

## Audit

Role creation, permission changes, role assignment, and revocations must be audited — see
[laravel-audit](../laravel-audit/SKILL.md).

## Forbidden

- ❌ `hasRole()` for feature gating (use permissions; roles only for system-area bootstrap)
- ❌ Hardcoded Title Case role names that don't match the seeder
- ❌ Authorization logic inline in a controller instead of a policy
- ❌ A policy that checks the permission but not `canAccessShop()` on shop-owned data
- ❌ Permissions created outside seeders / non-idempotent seeders
- ❌ Forgetting the permission cache reset after changes
- ❌ UI-only authorization
