# Copilot Instructions — POS (Point of Sale, Inventory & Retail Management)

> Derived from the actual codebase. Generic Laravel Boost guidelines live in [AGENTS.md](../AGENTS.md);
> this file is the project-specific layer. When the written standards in [.ai/general/](../.ai/general/)
> disagree with the shipped code, follow the **sibling files you are editing** — see "Two-standard reality".

## What this project is

A multi-shop retail Point-of-Sale and back-office system: products & variations, inventory/stock
movements with cost layers (COGS), sales & returns, customer credit accounts, suppliers & purchase
orders, expenses, cash registers, reporting, marketing (Meta/Google Ads, social posting), two-way
e-commerce sync (WooCommerce & Shopify), and WhatsApp messaging (Meta Cloud API + an unofficial
Baileys Node bridge). Server-rendered Blade admin UI plus a Sanctum-protected JSON API consumed by a
Flutter mobile app.

## Stack

- PHP 8.2+ (runtime 8.4), Laravel 12 (Laravel 11+ streamlined structure).
- MariaDB/MySQL primary DB; a separate `audit` SQLite connection for audit logs.
- Sanctum (API tokens) + Spatie laravel-permission (roles/permissions) + policies.
- Blade + a **static compiled Bootstrap 5 admin theme** in `public/assets/` loaded via `asset()` (NOT
  `@vite`); views use the BS5 JS API. **Bootstrap, not Tailwind.** (The `resources/css/app.css` Tailwind
  + npm bootstrap/Vite/Alpine are unused Breeze leftovers, only on welcome/auth/demo pages.)
- dompdf (PDFs), spatie/laravel-honeypot, custom multi-driver AI manager (qwen default, openai…).
- Pest 4 / PHPUnit 12, Pint, Laravel Boost (MCP). Standalone Node service in `baileys-bridge/`.

## Deployment

No CI/CD, Dockerfile, or workflow files exist in the repo. Locally served by Laravel Herd at a `*.test`
host (or `php artisan serve` / `composer run dev`). Production target is undocumented — do not assume a
platform. Branch strategy: work off `main`.

## Architecture patterns (as actually used)

- **Service layer** per domain in `app/Services/` (`SaleService`, `InventoryService`, `CostingService`,
  `CreditAccountService`, `PricingService`, …), sub-namespaced for integrations (`Services/Integration/`
  Shopify/WooCommerce, `Services/Ai/`, `Services/Social/`, `Services/Ads/`).
- **Actions** for single-purpose ops (`app/Actions/`); **FormRequests** for all validation
  (`$request->validated()`, never `$request->all()`); **Policies** for nearly every model.
- **Jobs** for async work (e-commerce sync, webhook processing, campaign publishing); **Observers**,
  **Enums**, **Notifications** (custom WhatsApp channel).
- Flat `app/Models/`; soft deletes + `created_by`/`updated_by` common. Audit records → separate `audit` DB.
- **Multi-shop scoping**: most queries filter by `auth()->user()->shop_id ?? Shop::first()?->id`.

## Coding standards to follow

- Curly braces always; constructor property promotion; explicit param + return types; PHPDoc over inline
  comments; TitleCase enum cases. Eloquent over raw SQL; eager-load to avoid N+1; `$fillable` on models.
- Blade: `{{ }}` escaping (avoid `{!! !!}` on user content); `@csrf` + `@honeypot` on web forms; `__()` for
  user-facing strings. Bootstrap 5 markup + BS5 JS API (`new bootstrap.Modal(...)`), never BS4 jQuery calls.
- `config()` not `env()` outside `config/`. Run `vendor/bin/pint` before finishing PHP changes.

## Two-standard reality (match siblings, not the docs)

The shipped code differs from the aspirational `.ai/general` standards. **Follow existing sibling code:**

| Topic | Code actually does | `.ai/general` asks for |
|---|---|---|
| Primary keys | int `id()` + separate `uuid` column | UUID PKs |
| Route binding | web binds by `uuid` (`getRouteKeyName`); API often by int id | UUIDs everywhere |
| API | flat/unversioned (`/api/products`, `api.products.index`) | `/api/v1/...` |
| API response | `['success' => true, 'data' => $paginator]` | `{success, message, data, meta}` |
| Casts | `$casts` property | `casts()` method |

The `laravel-standards` skill is the team's target for genuinely new subsystems; for edits to existing
modules, stay consistent with surrounding code.

## Key integrations

- **WooCommerce/Shopify** two-way sync (`Services/Integration/`); HMAC-verified webhooks
  `POST /api/webhooks/{woocommerce,shopify}/{shop:uuid}` → queued jobs; `ProductEcommerceSync` maps IDs.
- **WhatsApp**: Meta Cloud API (`config('services.whatsapp')`, `WhatsAppService`) AND Baileys bridge
  (`BAILEYS_BRIDGE_URL`, inbound `POST /api/webhooks/baileys`, `Controllers/Baileys/`, `baileys` queue).
- **Meta/Google Ads** (`Services/Ads/`) + **social posting** (`Services/Social/`); per-shop tokens stored
  **encrypted** on `social_accounts`. **AI** via `AiManager` (per-shop provider override).
- Mail via Postmark/Resend/SES (default `log` in dev).

## Security rules

- Authenticate every protected route (`auth` / `auth:sanctum`); authorize via policy/permission
  (`{resource}.{action}`). Validate with FormRequests + `validated()`. `@csrf` + `@honeypot` on web forms.
- Webhooks verified by HMAC; public document links use **signed URLs** (`->middleware('signed')`).
- Encrypt PII/tokens at rest (`Crypt`, as done for social tokens). Secrets only in env; never log secrets;
  `APP_DEBUG=false` in production.
- **Gap:** `bootstrap/app.php` registers no global middleware and no explicit API rate limiting — add
  throttling to API + auth endpoints if not applied per-route.

## What NOT to do

- Don't build admin UI with Tailwind or `@vite` — the production layouts use the static compiled
  Bootstrap theme in `public/assets/`. Use Bootstrap 5 markup + the BS5 JS API.
- Don't migrate existing int-PK tables to UUID PKs, version the existing flat API, or change the
  `{success,data}` response shape — it breaks the Flutter client and module consistency.
- Don't `$request->all()`, raw interpolated SQL, or `env()` outside config. Don't bypass shop scoping.
- Don't run destructive DB commands (`migrate:fresh`, `--fresh`, truncate/drop) — local or production.

## Testing conventions

- Pest 4, mostly feature tests in `tests/Feature/` (unit in `tests/Unit/`). Create with
  `php artisan make:test --pest {name}`; run `php artisan test --compact` / `--filter=Name`.
- Tests must NOT wipe/truncate the real DB — use an isolated test DB / transactions, not `RefreshDatabase`
  on shared data (see `.ai/general/safe-testing-guide.md`). Don't delete tests without approval.
</content>
