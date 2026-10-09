---
name: laravel-api
description: API standards for this POS codebase's Sanctum-protected JSON API consumed by the Flutter app — endpoint naming, UUID-based route binding, FormRequest validation, the project's {success,data} response shape, pagination, filtering, HTTP status codes, rate limiting, idempotency, API Resources, and the mandatory rule that web and API controllers stay in sync. Use when adding or changing anything under app/Http/Controllers/Api/, routes/api.php, app/Http/Resources/, or any JSON endpoint or webhook response.
---

# API Standards (POS)

Sources: [0.7 api_guide.md](../../../.ai/general/0.7%20api_guide.md),
[1.1 web_api_sync_guide.md](../../../.ai/general/1.1%20web_api_sync_guide.md).
API changes are 🔴 mandatory-compliance work in [ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md).

Canonical example to copy: [app/Http/Controllers/Api/SaleController.php](../../../app/Http/Controllers/Api/SaleController.php).

## ⚠️ Backward-compatibility contracts — do NOT "fix" these

The Flutter client ([docs/flutter/](../../../docs/flutter/)) consumes this API. [0.7](../../../.ai/general/0.7%20api_guide.md)
asks for `/api/v1/...` and a `{success, message, data, meta}` envelope; **the shipped API is flat and
minimal**. Match the shipped contract:

| Aspect | What shipped (keep) | What the doc asks for |
|---|---|---|
| Path | `/api/products`, `/api/sales` — **no version prefix** | `/api/v1/...` |
| Route names | `api.sales.index`, `api.sales.store` | `api.v1.sales.index` |
| Success body | `['success' => true, 'data' => $payload]` | `{success, message, data, meta}` |
| List body | the Laravel paginator nested inside `data` | flattened `data` + `meta` + `links` |

Do not move existing endpoints under `/api/v1`, rename their route names, or rewrap their responses.
New endpoints go alongside the existing flat ones and use the same shape.

```php
// The shape to write — matches every existing Api controller
return response()->json([
    'success' => true,
    'data' => $sales,   // paginator for lists, model/array for single resources
]);
```

Include a `'message'` key where the client shows feedback (create/update/delete confirmations), and
use `'success' => false` with a `message` for errors. Don't invent a third shape.

## Endpoint design

- Nouns, plural, kebab-case: `/sales`, `/purchase-orders`, `/sale-sources`. Never verbs in paths
  (`/getSales` ❌).
- `Route::apiResource(...)` with explicit `->names([...])` prefixed `api.` — that's the existing
  convention in [routes/api.php](../../../routes/api.php).
- Sub-actions on a resource: `POST /sales/{sale}/complete`, `POST /sales/{sale}/void`.
- Correct verbs: `GET` read, `POST` create, `PUT` full update, `PATCH` partial, `DELETE` remove. Never
  a `GET` that changes state.

## Identifiers — UUID in URLs

Never expose or accept an integer `id` in a route parameter. Implicit binding (`{sale}`) already
resolves by `uuid` for the 37 models that define `getRouteKeyName(): string { return 'uuid'; }` — that
is what makes the flat `{sale}` parameters safe.

**Before adding a route for a model, confirm the model defines `getRouteKeyName()`.** If it doesn't,
either add it (matching siblings) or bind explicitly with `{model:uuid}`. A bare `{model}` on a model
without a route key binds by integer `id` and reopens the enumeration hole.

## Validation & authorization

- A FormRequest per store/update; controller uses `$request->validated()`. Never `$request->all()`.
- Share the FormRequest with the web controller where the rules are the same (`StoreSaleRequest` is
  used by both).
- Authorize every action — policy (`$this->authorize(...)`) or `permission:` middleware. Validation
  failures → `422`; unauthenticated → `401`; forbidden → `403`.
- Shop scoping is authorization here: check shop access before querying, as
  `Api\SaleController::index()` does with `$user->canAccessShop($shopId)` + the `visibleTo($user)`
  scope. Never return rows from a shop the token's user can't access.

## Lists

- Always `paginate()` — accept `per_page` with a sane default (existing default: 20).
- Filters via explicit query params (`status`, `payment_status`, `date`, `search`), each applied with
  `$request->filled(...)`/`when(...)`. Whitelist any user-supplied sort column.
- Eager-load what the response serializes (`with(['customer', 'source', 'createdBy'])`) — N+1 is
  🔴 mandatory to prevent, see [laravel-performance](../laravel-performance/SKILL.md).

## API Resources

[app/Http/Resources/](../../../app/Http/Resources/) holds `SaleResource`, `ProductResource`, etc. Use
them when shaping a payload for the client, and keep the exposed fields UUID-based — never leak
internal `id`s the client shouldn't have. When you add a field to a Resource, check whether the web
view and the Flutter client both need it.

## Rate limiting

🔴 Mandatory — and now implemented. `bootstrap/app.php` appends **`throttle:api` to the whole api
middleware group**, so every endpoint (including any new one) is limited by default. Named limiters are
registered in `AppServiceProvider::registerRateLimiters()` with values in
[config/ratelimit.php](../../../config/ratelimit.php):

| Limiter | Default/min | Keyed by | Applied to |
|---|---|---|---|
| `api` | 120 | user, else IP | the whole api group (baseline) |
| `auth` | 5 | IP **and** submitted email | `api.login`, `api.register` |
| `writes` | 30 | user | sale store / complete / collect-payment |
| `destructive` | 10 | user | sale void / destroy |
| `webhooks` | 300 | shop (else IP) | the three inbound webhook routes |

Rules when adding endpoints:
- The baseline covers you; add a tighter named limiter for money-affecting or irreversible actions.
- ⚠️ **Never pass an associative array to `->middleware()` on a resource route** —
  `['store' => 'throttle:writes', 'destroy' => 'throttle:destructive']` does **not** map per action, it
  applies *every* entry to *every* action (this silently capped ordinary reads at the destructive limit).
  Declare those actions as explicit routes instead, as `routes/api.php` now does for sales.
- Tune limits via `RATE_LIMIT_*` env vars, never by editing the numbers in code.

## Idempotency

For `POST`s that create money-affecting records (sales, payments, refunds), support an
`Idempotency-Key` header where the client can send one: cache the response against the key and return
the cached result on replay. 🟡 Recommended, but strongly preferred for sale/payment creation.

## Transactions

Multi-step writes wrap in `DB::transaction` / `DB::beginTransaction` + commit/rollback — the existing
API controllers hold this logic inline. Match the sibling, and keep the "pre-fetch by `whereIn` then
`keyBy`" pattern instead of querying per item.

## 🔁 Web ↔ API controller sync (mandatory)

Per [1.1 web_api_sync_guide.md](../../../.ai/general/1.1%20web_api_sync_guide.md): **when you change a
web controller, mirror it in its `Api\` counterpart — and vice versa.** Paired controllers include
`Sale`, `Product`, `Category`, `Customer`, `Shop`, `Supplier`, `CashRegister`, `PurchaseOrder`,
`EcommerceOrder`, `DeliveryCompany`, `Calendar`, `Report`, `SaleSource`, `Inventory`.

Mirror: new endpoints, business-logic/query/filter changes, new eager loads, status-transition rules,
new FormRequest rules, and **bug fixes**. The only legitimate differences are response format
(`view()`/`redirect()` vs `response()->json()`), auth (session vs Sanctum token), and error
presentation (flash vs JSON).

If you deliberately don't mirror a change, say so explicitly in your summary.

## Status codes

`200` ok · `201` created · `204` deleted-no-body · `400` malformed · `401` unauthenticated ·
`403` forbidden · `404` not found · `422` validation · `429` throttled · `500` server error.

## Before finishing

- `php artisan route:list --path=api` — confirm naming, binding, and middleware.
- Confirm the counterpart controller is in sync.
- Confirm the response shape matches the existing `{success, data}` contract.
- `vendor/bin/pint` + relevant tests.

## Forbidden

- ❌ Versioning or renaming existing endpoints; new envelope shapes
- ❌ Integer `id`s in URLs or payload identifiers
- ❌ `$request->all()`, inline validation
- ❌ Unpaginated list endpoints
- ❌ Un-throttled endpoints
- ❌ Inline external HTTP calls (dispatch a job)
- ❌ Silent failures / undocumented endpoints
- ❌ Changing a web controller and leaving its `Api\` twin behind
