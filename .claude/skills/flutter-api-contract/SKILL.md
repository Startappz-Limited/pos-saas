---
name: flutter-api-contract
description: The live API contract between this Laravel POS backend and the shipped Flutter mobile app at ../Flutter/pos — response envelope, pagination nesting, the string-money rule, auth flow, endpoint registry, and the specific ways a backend change silently breaks the app. Use when adding or changing any /api route, controller, FormRequest, API Resource, enum value, or model field that the mobile app reads, and when asked to keep the two projects in sync or to generate endpoints for a mobile feature.
---

# Flutter ↔ Laravel API Contract (POS)

The mobile client is a **separately deployed Flutter app** — not a page in this repo. Its local checkout:

```
/Users/startappzlimited/Documents/Development/Flutter/pos     # set MOBILE_APP_PATH in .env
```

Human-readable per-feature docs live in [docs/flutter/](../../../docs/flutter/) (14 files +
`postman-collection.json`). This skill is the **behavioural** contract those docs don't capture: the
ways a routine backend edit breaks a client you cannot redeploy.

> **The governing asymmetry.** You can change this API in a second; the app takes an app-store release
> to catch up. Every change here is therefore backward-compatible-by-default, and additive changes are
> strongly preferred over renames.

## Check drift mechanically — don't eyeball it

```bash
php artisan mobile:contract-diff                     # uses MOBILE_APP_PATH
php artisan mobile:contract-diff --app-path=/path/to/flutter/pos
php artisan mobile:contract-diff --fail-on-drift     # non-zero exit for CI
```

It parses the app's endpoint registry (`lib/core/constants/api_endpoints.dart`) and diffs it against
`Route::getRoutes()`, reporting both directions: paths the app calls that don't exist (these 404 in
production) and API surface the app never consumes. Run it after **any** change to `routes/api.php`.

⚠️ **It only checks paths, not payloads.** Field renames, type changes and enum additions pass this
check and still break the app — see "How a backend change breaks the app" below.

## Response envelope — as actually shipped

Minimal, and *not* the `{success, message, data, meta}` envelope `.ai/general` asks for. Match what
ships; see the "Two-standard reality" section of [CLAUDE.md](../../../CLAUDE.md).

**Single resource** — [Api/SaleController.php](../../../app/Http/Controllers/Api/SaleController.php):

```php
return response()->json(['success' => true, 'data' => $sale]);
```

**Collection — the paginator is nested, producing a double `data`:**

```php
$sales = $query->paginate($request->input('per_page', 20));

return response()->json(['success' => true, 'data' => $sales]);
```

```jsonc
{
  "success": true,
  "data": {                    // ← the paginator
    "current_page": 1,
    "data": [ /* rows */ ],    // ← the rows
    "last_page": 3,
    "per_page": 20,
    "total": 47
  }
}
```

The app absorbs both shapes through `ResponseHelper.extractList()`, which walks `data` then `data.data`.
**Consequence: it discards pagination meta entirely** — the app cannot see `last_page` or `total` from a
list call and paginates by incrementing `page` until a short page comes back. Don't "fix" a list
endpoint by flattening it to a bare array; both shapes work, and changing which one you emit is churn
the client gains nothing from.

**Errors** are Laravel's defaults, which the app's `ErrorInterceptor` maps to typed exceptions:

```jsonc
{ "message": "The given data was invalid.", "errors": { "field": ["…"] } }
```

| Status | App behaviour |
|---|---|
| 401 | `UnauthorizedException` → **clears stored credentials and force-navigates to `/login`** |
| 404 | `NotFoundException` |
| 422 | `ValidationException`, surfaces `errors.<field>[0]` to the user |
| other / 5xx | `ServerException` |
| timeout / no route to host | `NetworkException` |

A 401 from any endpoint logs the cashier out mid-shift. Never use 401 for authorization failures —
that's 403.

## Money is a string. Do not "fix" this.

Laravel's `decimal:2` casts return **strings**, and every money field in the app's models is typed
`String`, parsed as `json['total_amount']?.toString() ?? '0.00'`. Formatting and arithmetic on the
client are built on that.

⚠️ [docs/flutter/README.md](../../../docs/flutter/README.md) claims money is sent "as numbers, not
strings." **That line is wrong** — the app is right. Casting money to float to satisfy the doc would
change `"1500.00"` to `1500` and silently alter rendering.

Ints stay ints: `quantity`, `stock_quantity`, `*_id`.

## Identifiers

Public identifiers are **UUIDs**; integer `id` is internal. Web routes bind explicitly (`{sale:uuid}`),
API routes use implicit `{sale}` which resolves by uuid **only** for the models defining
`getRouteKeyName()` — 37 of 67. Adding an API route with a bare `{model}` on one of the other 30 binds
by integer id, and the app will send a uuid and get a 404.

**Before adding any `/api` route with a model parameter, check that model for `getRouteKeyName()`.**

One deliberate exception: `/users/{user}` is keyed by **integer id** — the app's registry declares
`userDetail(int id)`. Leave it.

## Auth flow

`POST /login` → Bearer token; `POST /logout`; `GET /user` for the current profile. The base URL is
**entered by the user at runtime** (`ApiClient.setBaseUrl()` appends `/api`), so one app binary talks to
any hosted instance. Nothing may assume a fixed host.

`/login` and `/register` sit behind `throttle:auth` (5/min, keyed by IP *and* submitted email); the whole
api group carries `throttle:api`. A 429 reaches the app as a generic `ServerException` — it has no
retry-after handling, so don't tighten those limits without a client change.

Permissions are delivered on the user payload and cached client-side in `authorization_helper.dart`.
**Renaming a permission string silently disables the UI it gates** — the helper reads a missing
permission as "not allowed", so buttons vanish rather than erroring.

## Most payloads are raw models, not Resources

Only [CashRegisterController](../../../app/Http/Controllers/Api/CashRegisterController.php) uses API
Resources. `SaleResource`, `ProductResource`, `CustomerResource`, `CategoryResource`, `ShopResource`,
`SupplierResource` and `InventoryResource` exist but are **not wired into their controllers** — those
endpoints return models and paginators directly.

The practical consequence is the sharpest edge in this contract:

> **The API payload is whatever `$model->toArray()` produces.** A new migration column appears in the
> mobile payload immediately, with no code change and no review. A `$hidden` entry or a renamed column
> removes a field the app may be reading.

So: when you add a column to a table the app reads, check whether the app needs it (usually harmless —
its models ignore unknown keys). When you **rename or remove** one, grep the app first (below). If you
adopt a Resource for an endpoint that didn't have one, it must emit **at least** every key
`$model->toArray()` emitted before, or fields silently become `null` → the app's `?? default`.

`CashRegisterController` also nests inconsistently — sometimes `data.register`, sometimes bare `data`.
That inconsistency is why `ResponseHelper` is defensive. Don't replicate it in new endpoints; return
`data` directly.

## How a backend change breaks the app — ranked by how quietly it fails

The app parses every field defensively (`json['x']?.toString() ?? '0.00'`, `json['status'] ?? 'pending'`).
Nothing throws. That makes these failures **silent**, which is why they're ranked this way:

| Change | What the app does | Loudness |
|---|---|---|
| Rename a response field | Substitutes the `??` default — `0.00` totals, `pending` status | 🔴 silent, corrupt data on screen |
| Add an enum case | Not in the app's `lib/core/enums/*`; its `fromValue(…, orElse: …)` returns a **real but wrong** case — a new sale status renders as "Draft", in Draft's colour | 🔴 silent, *plausibly* wrong |
| Rename a permission string | Gated buttons disappear | 🔴 silent |
| Tighten validation on an existing field | 422 the app can't satisfy; user sees a field error they can't fix | 🟠 visible but unfixable in-app |
| Add a **required** request field | Every submit 422s | 🟠 loud, feature dead |
| Rename/remove a route | 404 → `NotFoundException` | 🟢 caught by `mobile:contract-diff` |
| Change money to a number type | Formatting/rounding shifts | 🟠 subtle |
| Return 401 for an authz failure | **Logs the user out** | 🔴 destructive |

### Safe-change rules

- **Add, don't rename.** Need a better field name? Emit both, migrate the app, remove the old one a
  release later.
- **New request fields are optional**, or defaulted server-side.
- **New enum cases require a paired app change** — the app ships a closed enum set per domain
  (`sale_status`, `sale_type`, `payment_status`, `payment_method`, `ecommerce_order_status`,
  `purchase_order_status`, `product_status`, `customer_type`, `user_status`, `alert_type`,
  `alert_severity`). Each resolves with `firstWhere(…, orElse: …)`, so a backend-only case doesn't
  blank out — it **displays as some other real status**, which is worse.
  Keep [docs/flutter/10-enums.md](../../../docs/flutter/10-enums.md) authoritative for both sides.
- **Widen, never narrow, validation** on fields the app already sends.
- **403 for authorization, never 401.**

## Grep the app before changing a name

From the Laravel project root:

```bash
APP=${MOBILE_APP_PATH:-/Users/startappzlimited/Documents/Development/Flutter/pos}

grep -rn "total_amount"  "$APP/lib"                    # a response field
grep -rn "'/sales"       "$APP/lib/core/constants"     # a route path
grep -rn "sales.create"  "$APP/lib"                    # a permission string
grep -rn "class SaleModel" -A60 "$APP/lib/features/sales/models/sale_model.dart"
```

If a field appears in `$APP/lib`, it is load-bearing. There is no compiler to tell you otherwise.

## The app's architecture, so generated endpoints fit it

Per feature under `lib/features/<feature>/`, and a new endpoint is consumed through all four layers:

| Layer | Role |
|---|---|
| `services/<x>_service.dart` | One method per endpoint; raw `dio` call, returns `Response` |
| `actions/<x>_action.dart` | Unwraps the envelope via `ResponseHelper.extractList` / `response.data['data']`, maps to models, applies `CacheService` |
| `models/<x>_model.dart` | `fromJson` with a `??` default per field |
| `controllers/`, `views/`, `bindings/` | GetX state, UI, DI |

Features present: `auth`, `calendar`, `cash_register`, `customers`, `delivery_companies`,
`ecommerce_orders`, `expenses`, `home`, `products`, `purchase_orders`, `reports`, `sales`, `users`.

Endpoints are declared **only** in `lib/core/constants/api_endpoints.dart`, as either
`static const String x = '/path';` or `static String x(String uuid) => '/path/$uuid/action';`. That file
is what `mobile:contract-diff` parses — an endpoint hardcoded elsewhere escapes the check.

`CacheService` caches reference lists client-side (`sale_sources`, `customers`, `products`, `categories`,
`delivery_companies`). Changing the shape of one of those is not picked up until the cache expires.

## API surface the app does not consume

Currently 13 registered paths are unused by the app, including all four `/inventory/*` endpoints,
`POST /register`, `expenses/{expense}/settle-from-register`, `customers/{customer}/activate` and
`/deactivate`, and several `{param}` detail routes. Two readings, both worth checking before building:

- The app has no UI for it yet → this is the list to build a mobile feature from.
- It's dead API surface → candidate for removal, but confirm no other client uses it first.

`mobile:contract-diff` prints this list on every run.

## Checklist — changing anything the app touches

1. `php artisan mobile:contract-diff` **before and after**.
2. Renaming a field, route, permission or enum value? Grep `$APP/lib` first (commands above).
3. New enum case → add it to `lib/core/enums/` **and** `docs/flutter/10-enums.md`.
4. New request field → optional, or server-defaulted.
5. Money stays a string; ints stay ints.
6. Authorization failure → 403, never 401.
7. Model parameter on a new API route → confirm `getRouteKeyName()` exists on that model.
8. Update the matching `docs/flutter/NN-*.md` and `postman-collection.json`.
9. Note the required app-side change explicitly in your summary — the two repos deploy separately, so
   an unstated client change is a change that doesn't happen.

## Related

- [laravel-api](../laravel-api/SKILL.md) — response shape, versioning, pagination conventions
- [laravel-routing](../laravel-routing/SKILL.md) — route binding and `getRouteKeyName()`
- [laravel-authorization](../laravel-authorization/SKILL.md) — permission naming
- [pos-domain-rules](../pos-domain-rules/SKILL.md) — sale/stock/register semantics the app mirrors
- Agent: `pos-mobile-bridge` — carries a change across both repos
