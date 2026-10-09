---
name: laravel-routing
description: Routing standards for this POS codebase — UUID route-model binding, dot-notation route names, explicit middleware stacks (auth, permission, throttle, signed), route grouping and prefixes, keeping routes thin and cache-safe, and web vs API separation. Use when adding, renaming, grouping, or reviewing any route in routes/web.php, routes/api.php, or routes/auth.php, or when wiring middleware or authorization onto an endpoint.
---

# Routing Standards (POS)

Source: [0.8 routing_guide.md](../../../.ai/general/0.8%20routing_guide.md). Routing is 🔴 mandatory for
new features, refactors, and API changes ([ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md)).

> Routes are contracts, not conveniences. Renaming or re-pathing an existing route breaks Blade
> `route()` calls, redirects, and the Flutter client.

## Where routes live here

Flat files, not the folderized `routes/admin/*.php` layout the guide sketches — match what exists:

- [routes/web.php](../../../routes/web.php) — admin/POS UI, one big `Route::middleware('auth')->group()`
- [routes/api.php](../../../routes/api.php) — Sanctum JSON API + public webhook routes
- [routes/auth.php](../../../routes/auth.php) — Breeze auth flows
- `routes/console.php` — scheduled/console

Add new routes to the existing group that already carries the right middleware rather than creating a
new file or a parallel group.

## UUID route binding (mandatory)

**Never expose an integer `id` in a URL.** Two correct forms, both already in use:

```php
// Explicit — the dominant style in routes/web.php
Route::get('sales/{sale:uuid}', [SaleController::class, 'show'])->name('sales.show');

// Implicit — safe only because the model defines getRouteKeyName() => 'uuid'
Route::post('sales/{sale}/complete', [SaleController::class, 'complete'])->name('api.sales.complete');
```

37 of the 67 models define `getRouteKeyName()`. **Before writing `{model}` without `:uuid`, confirm
that model defines it** — otherwise the binding silently falls back to the integer primary key. When in
doubt, write `{model:uuid}` explicitly; it's self-documenting and cannot regress.

Forbidden: `{id}`, `{userId}`, `{numeric}`, or any hand-rolled `findOrFail($id)` on a request value.

## Naming

- Dot notation, resource-first: `sales.index`, `sales.show`, `cash-registers.close`.
- API routes are prefixed `api.` (`api.sales.index`) via explicit `->names([...])` on `apiResource`.
- Grouped areas use `->name('prefix.')` — e.g. the Baileys group uses `->prefix('baileys')->name('baileys.')`.
- **Every route has a name** (192 of them do). URLs are kebab-case.
- Renaming an existing route name is a breaking change — grep for it first:
  `grep -rn "route('sales.show'" resources/ app/`.

## Middleware — declare it explicitly

Stack order and intent, applied at the group level where possible:

- `auth` (web) / `auth:sanctum` (API) on everything not deliberately public.
- `verified` where email verification is required (the dashboard route uses it).
- `permission:{resource}.{action}` or a policy call in the controller — see
  [laravel-authorization](../laravel-authorization/SKILL.md).
- `throttle:X,1` on state-changing and abuse-prone endpoints. Existing precedent to match:
  `sales.store` → `throttle:20,1`; `cash-registers.store` → `throttle:10,1`;
  `sales.void` and `cash-registers.close` → `throttle:5,1`. **`routes/api.php` has no throttling at
  all — add it when you touch an endpoint** (see [laravel-api](../laravel-api/SKILL.md)).
- `signed` on public document links (invoice PDFs) — already used in `web.php`.
- Webhook routes are intentionally outside `auth` and must verify HMAC signatures instead.

## Keep routes thin and cache-safe

- Routes map 1:1 to a controller action. No business logic, authorization checks, or data
  transformation in a route definition.
- **Controller-array syntax only** (`[Controller::class, 'method']`) — closures break
  `php artisan route:cache`. Two legacy closures exist (`/` and `/dashboard`); don't add more, and
  don't put logic in them.
- Group by shared prefix + middleware + name instead of repeating them per route.

## Web vs API separation

| | Web | API |
|---|---|---|
| File | `routes/web.php` | `routes/api.php` |
| Auth | session + CSRF | Sanctum token |
| Response | `view()` / `redirect()` | `response()->json()` |
| Versioning | none | none (flat — see [laravel-api](../laravel-api/SKILL.md)) |

Never mix the two in one file. When you add a web endpoint, check whether its `Api\` counterpart needs
the same route (the web↔API sync rule is mandatory).

## Errors

Unmatched route or unresolvable UUID → `404` automatically. Authorization → `403`. Authentication →
`401` (API) / redirect (web). Validation → `422`. Don't hand-roll these.

## Before finishing

```bash
php artisan route:list --path=api          # verify names, bindings, middleware
grep -n "Route::.*{[a-z]" routes/web.php | grep -v ':uuid'   # audit non-explicit bindings
```

## Forbidden

- ❌ Integer-ID route parameters
- ❌ `{model}` on a model with no `getRouteKeyName()`
- ❌ Closures for business routes (breaks route caching)
- ❌ Unnamed routes
- ❌ Logic or authorization decisions inside a route definition
- ❌ Missing middleware stack (auth / permission / throttle)
- ❌ Mixing web and API routes in one file
- ❌ Renaming or re-pathing a shipped route without checking every `route()` reference
