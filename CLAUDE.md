# CLAUDE.md — POS (Point of Sale, Inventory & Retail Management)

> Authoritative, codebase-derived guide for Claude Code. Where this file and the aspirational
> standards in [.ai/general/](.ai/general/) disagree, this file describes **what the code actually
> does today**; `.ai/general` + the [laravel-standards](.claude/skills/laravel-standards/SKILL.md)
> skill describe the **target standard the team wants new code to move toward**. Read the
> "Two-standard reality" section before generating code — it is the single most important thing here.

## What this project is

A multi-shop retail Point-of-Sale and back-office system: products & variations, inventory/stock
movements with cost layers (COGS), sales & returns, customer credit accounts, suppliers & purchase
orders, expenses, cash registers, reporting, plus marketing (Meta/Google Ads, social posting) and
two-way e-commerce sync (WooCommerce & Shopify) and WhatsApp messaging (Meta Cloud API + unofficial
Baileys through Chatway Gateway). Server-rendered Blade admin UI + a Sanctum-protected JSON API
(consumed by a Flutter app — see [docs/flutter/](docs/flutter/)).

## Stack

- **PHP 8.2+** (Boost reports the runtime as 8.4), **Laravel 12** (streamlined Laravel 11+ structure).
- **Database: MySQL/MariaDB.** ⚠️ The current `.env` uses `DB_CONNECTION=mysql` and points **`DB_HOST` at a
  remote shared server** — not localhost. Treat the development database as live production data: never run
  `migrate:fresh`/`migrate:refresh`/`migrate:reset`/`db:wipe`, and never `db:seed` without `--class=`.
  A [PreToolUse hook](.claude/hooks/block-destructive-db.sh) now blocks these mechanically.
  A separate `audit` **SQLite** connection ([config/database.php](config/database.php),
  `database/audit.sqlite`) holds the audit trail. It has its **own migration repository**, so plain
  `migrate` does not create it — see the audit deploy command under Commands.
- **Auth/authz:** Laravel Sanctum (API tokens) + Spatie laravel-permission (roles/permissions) + policies.
- **Frontend:** Blade + a **static compiled Bootstrap 5 admin theme**. The real admin/POS/guest layouts
  ([resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php), `pos.blade.php`,
  `guest.blade.php`) load `public/assets/{css,js}/{vendor,app,icons}.min.*` via `{{ asset(...) }}` — **not**
  `@vite`. Views use the **Bootstrap 5 JS API** (`new bootstrap.Modal(...)`). Theme source lives in
  `public/assets/scss/`. See the [bootstrap-ui](.claude/skills/bootstrap-ui/SKILL.md) skill.
  ⚠️ The Vite/npm scaffolding (`resources/css/app.css` Tailwind directives, npm `bootstrap`, Alpine in
  `resources/js/app.js`) is **leftover Breeze boilerplate** used only by `welcome`, `auth`, and the demo
  layout — it is **not** how the production UI is styled. Do not build admin UI against Tailwind/Vite.
- **PDF:** barryvdh/laravel-dompdf (invoices, statements, reports → [resources/views/pdf/](resources/views/pdf/)).
- **Queues/cache/session:** database driver by default (`QUEUE_CONNECTION=database`).
- **Spam protection:** spatie/laravel-honeypot.
- **AI:** custom multi-driver `AiManager` ([app/Services/Ai/](app/Services/Ai/)) — drivers: `qwen`
  (default), `openai`, others; per-shop override via `Shop.settings.ai.provider`.
- **Tooling:** Pest 4 + PHPUnit 12, Laravel Pint, Boost (MCP), Pail, Sail, Breeze, Debugbar (dev).
- **WhatsApp (Baileys):** runs in **Chatway Gateway**, a separate service reached through the private
  `startappz/wa-gateway-laravel` package (Composer `vcs` repository; installing it needs a GitHub token with
  read access). [baileys-bridge/](baileys-bridge/) is the old in-repo Node bridge, kept only for
  `bin/export-to-gateway.js` (moves linked sessions across) until the cutover is done.

## Two-standard reality — READ BEFORE CODING

The shipped code and the written standards diverge. Do **not** blindly "fix" working code to match
the docs, and do **not** assume the docs describe reality. Concretely:

| Topic | What the code ACTUALLY does | What `.ai/general` / skills ASK for |
|---|---|---|
| Primary keys | `$table->id()` (auto-increment int) **plus** a separate `$table->uuid('uuid')->unique()` column | UUID primary keys (`HasUuids`) |
| Route binding | Web routes mostly bind explicitly (`{sale:uuid}`); API routes use implicit `{sale}`, which resolves by **uuid** for the 37 of 67 models that define `getRouteKeyName()`. A bare `{model}` on the other 30 binds by integer id — check the model before writing one | UUIDs in all URLs |
| API versioning | **Flat, unversioned** (`/api/products`, route names `api.products.index`) | `/api/v1/...` |
| API response shape | Minimal: `response()->json(['success' => true, 'data' => $paginator])` | Full envelope `{success, message, data, meta}` |
| Model casts | Mostly the `$casts` **property** (e.g. `Sale`); a few models use the `casts()` method (e.g. `Shop`). Match the file you're in | `casts()` method |
| Foreign keys | `foreignId(...)->constrained()` (integer FKs) | UUID FKs |
| Business logic | Service layer exists, but API controllers also hold inline `DB::transaction` logic | Thin controllers; logic in Actions/Services/Jobs |

**Rule of thumb:** match the conventions of the **sibling files you are editing**. When adding code to
existing modules, follow existing code (int PK + uuid column, flat API, `{success,data}`). The
`laravel-standards` skill is the team's stated direction for genuinely new subsystems — invoke it, but
if applying it would break consistency with the surrounding module, surface the conflict rather than
silently mixing patterns (see the skill's `CONFLICT:` protocol).

## Architecture patterns (as actually used)

- **Service layer** — services in [app/Services/](app/Services/), real ones including `ReportService`,
  `ReturnService`, `CreditAccountService`, `PricingService`, `StockAdjustmentService`, `ShopService`.
  Sub-namespaced for integrations: `Services/Integration/` (Shopify/WooCommerce), `Services/Ai/`,
  `Services/Social/`, `Services/Ads/`.
  ✅ `TaxService` (VAT engine), `VatReportService` (output-VAT summary) and `InvoiceNumberService`
  (gapless invoice sequences) are real — see [docs/vat.md](docs/vat.md).
  🔴 **Eight services are still EMPTY STUBS** — `SaleService`, `PaymentService`, `CustomerService`,
  `ExpenseService`, `ExpenseCategoryService`, `NotificationService`, `DashboardService`, `CostingService`.
  Sale/payment/expense logic lives **inline in the controllers**. Roughly 18 models are stubs too.
  Grep the real call path before trusting any class name; full list in
  [pos-domain-rules](.claude/skills/pos-domain-rules/SKILL.md).
  ✅ Previously-dead controllers are now implemented: `AlertController` (8 routes),
  `LowStockAlertController` (was 3 of 8 methods), `PaymentController` (reads `sale_payments`),
  `AuditLogController` + `AuditService`. `CostLayerController` and its 3 routes were **removed** —
  `cost_layers`/`cost_allocations` are unused scaffolding.
- **Actions** — 88 single-purpose operations in [app/Actions/](app/Actions/) (flat, plus domain subfolders
  such as `Product/`, `Shop/`, `PurchaseOrder/`, `Campaign/`, `Social/`, `Baileys/`).
- **FormRequests** — almost every store/update has a FormRequest in [app/Http/Requests/](app/Http/Requests/);
  controllers call `$request->validated()`, never `$request->all()`.
- **Policies** — a policy exists for nearly every model ([app/Policies/](app/Policies/)); authorize resource actions.
- **Jobs** — async work in [app/Jobs/](app/Jobs/): e-commerce order/product sync, webhook processing,
  campaign post publishing.
- **Observers** — model lifecycle hooks in [app/Observers/](app/Observers/).
- **Notifications** — [app/Notifications/](app/Notifications/) with custom WhatsApp channel + message classes.
- **Enums** — status/type values in [app/Enums/](app/Enums/).
- **Audit** — ✅ implemented on the isolated `audit` connection. Capture is via the
  `App\Models\Concerns\Auditable` trait (one line per model) → `AuditableEvent` → `AuditLogger` listener,
  which **swallows failures** so a broken audit sink can never fail a sale. Rows are immutable (`save()`
  and `delete()` throw), secrets are stripped and PII masked, and reads go through `AuditService` gated on
  `audit.view`/`audit.export`. Schema is `database/migrations/audit/` — polymorphic `auditable_type`/
  `auditable_id`/`auditable_uuid` plus `event`, `status`, `user_*`, `shop_id`.
  **Audited models:** Sale, Product, User, CashRegister, Expense, StockAdjustment, Shop, Role,
  CreditAccount, CreditTransaction, SaleReturn, Refund (add `use Auditable;` for more).
  **Authorization grants** (role/permission attach+detach) are audited via Spatie events —
  `config('permission.events_enabled')` is now `true` and `AuditAuthorizationChanges` listens; those are
  pivot writes, so no model event fires.
  See the [laravel-audit](.claude/skills/laravel-audit/SKILL.md) skill.
- **Models are flat** in [app/Models/](app/Models/); soft deletes + `created_by`/`updated_by` tracking are common.
- **Businesses and shop scoping (global scopes)** — a `Business` is the account a shop owner (`admin`)
  runs; it owns `shops`, `users` and the shared lists (`suppliers`, `categories`, `attributes`,
  `pricing_rules`, `expense_categories`, `delivery_companies`, `sale_sources`), which carry `business_id`.
  Two global scopes enforce isolation for whoever is signed in: models with `use BelongsToAccessibleShop`
  (every model with a `shop_id`) only return rows of `User::accessibleShopIds()` — all of the business's
  shops for an `admin`, the `shop_user` shops for staff — and models with `use BelongsToBusiness` only
  return the user's business (and stamp new rows with it). `Shop` is scoped on `id`. A **super-admin is
  never scoped**, and neither is anything with **no signed-in user** (queued jobs, webhooks, signed invoice
  links, console). `hasShopRestrictions()` is now `! isSuperAdmin()` — "no shop assigned" no longer means
  "every shop". Raw `DB::table()` queries bypass the scopes, so pass the allowed shop ids explicitly (see
  `ReportService`'s `allowed_shop_ids`, `VatReportService::summary()`). Validate referenced ids with
  `new ExistsForViewer(Model::class)`, not `exists:table,id` (which accepts another business's ids), and
  natural keys of the shared lists with `UniqueInBusiness::for($table, $column)`.
- **Roles are per business** (Spatie teams with `team_foreign_key = business_id`): `super-admin` and
  `admin` are global and hold every permission (new permissions are granted to them automatically);
  manager/cashier/... are copied into each business from `App\Support\DefaultRoles`, so an admin
  editing a role only affects their business. `{resource}.full-access` grants a resource's
  permissions but **not** a way around policy rules (`HandlesFullAccess`); only super-admin bypasses.
  See the laravel-authorization skill before touching roles.
- **Account status and deletion.** Inactive/suspended users cannot sign in and are signed out on their
  next request (`active` middleware, `EnsureUserIsActive`; API → `403 account_inactive`); changing a
  user's status away from active revokes their sessions and tokens (`User::booted`). **Only the business
  owner deletes accounts** (`UserPolicy`); co-admins/managers deactivate or suspend, users deactivate
  themselves from the profile page (there is no self-delete), and a super-admin can never delete a user.
  `UserService::delete` erases the person's data: photo, sessions, tokens, reset tokens, and their name/
  email/IP in the audit trail (`AuditErasure`, the one sanctioned write to audit rows).
  ⚠️ **A new foreign key from a business record to `users` must be `nullOnDelete()`** (and nullable), so
  deleting a person keeps the record; only data that belongs to the person (preferences, widgets) may
  cascade. Views/resources must cope with a missing user (`?->name ?? __('Deleted user')`).
- **Staff must be linked to a shop.** The `shop.linked` middleware (`EnsureUserIsLinkedToShop`, on the
  `auth` web group and the `auth:sanctum` API group) sends a non-admin with no shop to the `no-shop` page
  ("contact your administrator"), or returns `403 {"success":false,"code":"shop_not_linked"}` on the API
  (`/api/user` and `/api/logout` stay reachable). Admins are never blocked: one with no shop sees empty
  lists, and gets a business created on first request (`User::ensureBusiness()`). A new business is seeded
  with the default sale sources (`SaleSource::defaults()`), because a sale requires one.

## Coding standards to follow

- PHP: curly braces always; constructor property promotion; explicit param + return types; PHPDoc over
  inline comments; TitleCase enum cases.
- Eloquent over raw SQL; eager-load (`with`, `withCount`) to avoid N+1; `$fillable` on every model.
- Blade: escape with `{{ }}`, avoid `{!! !!}` on user content; `@csrf` + `@honeypot` on web forms; wrap
  user-facing strings in `__()`.
- Bootstrap 5 markup + the **BS5 JS API** (`new bootstrap.Modal(...)`), never BS4 jQuery plugin calls
  (`$('#x').modal()`) — see the [bootstrap-ui](.claude/skills/bootstrap-ui/SKILL.md) skill.
- Config via `config()`, never `env()` outside `config/`.
- Run `vendor/bin/pint` before finishing PHP changes.

## Key integrations

- **WooCommerce & Shopify** (two-way): product + order sync services in `Services/Integration/`; inbound
  webhooks at `POST /api/webhooks/woocommerce/{shop:uuid}` and `/shopify/{shop:uuid}` (HMAC-verified),
  handled by `WebhookController` → queued `ProcessOrderWebhookJob` / sync jobs. `ProductEcommerceSync`
  model maps local products to platform product IDs.
- **WhatsApp** — two paths: (1) Meta Cloud API (`config('services.whatsapp')`, `WhatsAppService`);
  (2) **Baileys** via Chatway Gateway — outbound through the package's `WaGateway` client
  (`WA_GATEWAY_URL` / `WA_GATEWAY_TOKEN`, [config/wa-gateway.php](config/wa-gateway.php)); inbound at
  `POST /wa-gateway/webhook`, which the package verifies (`WA_GATEWAY_WEBHOOK_SECRET`) and turns into
  events handled by [HandleGatewayWebhooks](app/Listeners/Baileys/HandleGatewayWebhooks.php). Controllers
  under `Controllers/Baileys/`, jobs on the `baileys` queue. Gateway delivery is at least once and
  **unordered**: dedupe on `wa_message_id`, and let session events and receipts only move forward.
  ⚠️ The Cloud API path can only send **free-form text inside a 24-hour customer-initiated window**
  (Meta error `131047` otherwise), so for a walk-in customer it effectively always fails and falls back
  to SMS. Anything that must reach a cold contact goes over **Baileys**, which has no such window.
- **Sale invoice delivery** — a completed sale sends the customer their invoice **PDF** over WhatsApp.
  `SendSaleNotifications` (called by BOTH `SaleController`s — web and API) fans out to the Cloud API
  text receipt and queues `SendSaleInvoiceViaBaileysJob` → `NotifyCustomerOfSale`, which renders
  `GenerateSaleInvoicePdf` and sends it as a `document` with the receipt as caption. The PDF bytes go
  to the gateway **inline**, so it never has to reach back into Laravel; the signed
  `sales.invoice-pdf` URL is still recorded on the message for re-download. Falls back to plain
  text if dompdf fails. `paymentReceived()` re-sends a PAID invoice when a balance is cleared.
- **Per-shop sale-message switches** — three per shop, stored in `Shop.settings['sale_notifications']`
  and toggled from the Baileys sessions screen (`baileys.automation.saleNotifications`):
  `all` (master), `invoice_pdf` (Baileys document), `text_receipt` (Cloud API text — also gates
  `CreditBalanceNotification` and `PaymentReceivedNotification`). Read them via
  `Shop::notifiesOnSale()` / `notifiesInvoiceOnSale()` / `notifiesReceiptOnSale()` — those return the
  **effective** value (master AND channel); `saleNotificationEnabled($channel)` returns the raw
  per-channel position, which is what the UI renders so the master switch is non-destructive.
  Defaults preserve pre-switch behaviour: everything on, with `invoice_pdf` still honouring
  `BAILEYS_NOTIFY_ON_SALE`. **Any new sale-triggered customer message must check the relevant switch.**
- **Meta Ads + Google Ads** (`Services/Ads/`) and **Social posting** (`Services/Social/`, driver-based);
  OAuth app creds in `config/services.php`, per-shop tokens stored **encrypted** on `social_accounts`.
- **AI** — call through `AiManager` / the AI manager binding, not a hardcoded provider.
- **Mail** — Postmark/Resend/SES configured; default mailer `log` in dev.

## Security rules

- Authenticate every protected route (`auth`, `auth:sanctum`); authorize via policy/permission
  (`{resource}.{action}` naming) on every resource action.
- Validate with FormRequests + `$request->validated()`; never `$request->all()`.
- `@csrf` + `@honeypot` on web forms; webhooks verified by HMAC signature (not session auth).
- Public document links (invoices) use **signed URLs** (`->middleware('signed')`).
- Encrypt PII / tokens at rest (`Crypt`) — already done for social account tokens; follow that pattern.
- Secrets only in `.env`/config; never log tokens/passwords/PII; `APP_DEBUG=false` in production.

## What NOT to do

- Don't build admin UI against **Tailwind** or `@vite` — the production layouts use the **static compiled
  Bootstrap theme** in `public/assets/` via `asset()`. The `resources/css/app.css` Tailwind directives and
  npm `bootstrap`/Vite are unused Breeze leftovers. Use Bootstrap 5 markup + the BS5 JS API.
- Don't convert existing integer-PK tables to UUID PKs, or version the existing flat API, or rewrite
  existing `{success,data}` responses into a different envelope — that breaks the Flutter client and
  existing modules. Match siblings.
- Don't `$request->all()`, don't write raw interpolated SQL, don't `env()` outside config.
- Don't run destructive DB commands — see Commands below. Never `migrate:fresh`/`migrate --fresh`.
- Don't bypass shop scoping on shop-owned queries.

## Testing conventions

- **Pest 4** ([tests/Feature](tests/), [tests/Unit](tests/)); most tests are feature tests.
- Create: `php artisan make:test --pest {name}` (`--unit` for unit). Run: `php artisan test --compact`
  or `--filter=Name`.
- Tests must **not** wipe/truncate the real DB. Use an isolated test DB / transactions; never
  `RefreshDatabase` against shared data. See [.ai/general/safe-testing-guide.md](.ai/general/safe-testing-guide.md).
- Do not delete tests without approval.

## Commands Claude should know

```bash
composer run dev          # serve + queue:listen + pail logs + vite, all at once
php artisan serve         # app server only
npm run dev / npm run build  # Vite — only affects Breeze leftover pages (welcome/auth/demo); the
                             # admin/POS UI is the static public/assets/ theme and needs no build step
# ⚠️ Named queues: a bare `queue:work`/`queue:listen` drains ONLY `default`, so every WhatsApp
# notification, invoice and sync job silently piles up in the `jobs` table. ALWAYS name them:
php artisan queue:work --queue=default,whatsapp-notifications,order-notifications,baileys,ecommerce --tries=3
# Diagnose a "notifications never arrive" report with:  select queue, count(*) from jobs group by queue;
php artisan migrate       # apply migrations (NEVER migrate:fresh / --fresh — destroys data)
# ⚠️ The audit trail lives on a SEPARATE connection with its OWN migration
# repository, so plain `migrate` does NOT create it. Every environment must run:
php artisan migrate --database=audit --path=database/migrations/audit
# (the SQLite file is gitignored — `touch database/audit.sqlite` first if missing)
php artisan pail          # tail logs
vendor/bin/pint           # format PHP (run before finishing)
php artisan test --compact            # run tests
php artisan route:list --path=api     # inspect API routes
# WhatsApp (Baileys) runs in Chatway Gateway, not in this repo; Composer needs a GitHub token to
# install its client package: COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}' composer install
# Project-specific commands:
php artisan ecommerce:sync-products   # SyncEcommerceProducts
# (BackfillShopProductPivot exists in app/Console/Commands)
# Mobile client contract — run after ANY change to routes/api.php:
php artisan mobile:contract-diff              # needs MOBILE_APP_PATH in .env
php artisan mobile:contract-diff --fail-on-drift   # non-zero exit for CI
```

Prefer Laravel Boost MCP tools (`search-docs`, `database-query`, `database-schema`, `list-artisan-commands`,
`get-absolute-url`, `browser-logs`) over manual equivalents. The app is served by Laravel Herd at a
`*.test` host — use `get-absolute-url` for URLs.

## Files Claude should read before editing

1. [app/Http/Controllers/Api/SaleController.php](app/Http/Controllers/Api/SaleController.php) — canonical
   API controller (response shape, shop scoping, inline `DB` transaction style).
2. [app/Models/Sale.php](app/Models/Sale.php) — model conventions (int PK + `uuid`, `getRouteKeyName`,
   `$casts` property, `$fillable`, typed relationships).
3. [routes/api.php](routes/api.php) & [routes/web.php](routes/web.php) — flat API naming + web binding by uuid.
4. [.claude/skills/laravel-standards/SKILL.md](.claude/skills/laravel-standards/SKILL.md) — index of the
   focused skills + hard safety rules.
5. The matching **sibling** service/request/policy for whatever domain you're touching.

## Skills & agents (load the focused one for your task)

[.claude/skills/](.claude/skills/) holds 17 skills derived from `.ai/general` plus the codebase itself:
`laravel-standards` (index), `-security`, `-database`, `-api`, `-routing`, `-authorization`, `-audit`,
`-architecture`, `-performance`, `-testing`, `-documentation`, `-integrations`, `bootstrap-ui`,
`ui-design-standards`, `pr-checklist`, `pos-domain-rules`, `flutter-api-contract`. Prefer the specific
skill over reasoning from generic Laravel habit — each one records where this codebase diverges from the
written standards.

[.claude/agents/](.claude/agents/) holds 11 specialist subagents (`pos-security-auditor`,
`pos-standards-reviewer`, `pos-performance-analyst` — read-only; plus `pos-api-developer`,
`pos-database-architect`, `pos-integration-engineer`, `pos-test-engineer`, `pos-ui-builder`,
`pos-audit-engineer`, `pos-docs-writer`, `pos-mobile-bridge`).

## The Flutter client is a live contract

The JSON API is consumed by a **separately deployed Flutter app** at
`~/Documents/Development/Flutter/pos` (set `MOBILE_APP_PATH` in `.env`). It parses every field
defensively, so a renamed field or a new enum case fails **silently** — a `0.00` total on a till receipt,
not an exception. Load [flutter-api-contract](.claude/skills/flutter-api-contract/SKILL.md) before
touching `/api`, and run `php artisan mobile:contract-diff` after. The app has its own mirrored skill
(`pos-backend-contract`) and agent (`pos-flutter-feature-builder`) in `.claude/` on that side.

⚠️ [.github/](.github/) contains a **separate, older** Copilot guidance set (`agents/`, `instructions/`,
`skills/`, `prompts/`) that contradicts the above in places — it still teaches `/api/v1` versioning and
per-policy super-admin bypasses. Treat `.claude/` as authoritative for Claude Code.

## Things to always check before generating code

- **Match the sibling module's pattern**, not the aspirational docs (see "Two-standard reality").
- **Shop scope**: a new model with a `shop_id` gets `use BelongsToAccessibleShop;`; a new business-wide
  list gets a `business_id` column + `use BelongsToBusiness;` (and `[business_id, key]` unique indexes).
  Raw `DB::table()` queries need the allowed shop ids applied by hand.
- **New model** → does it need a `uuid` column + `getRouteKeyName()`, soft deletes, `created_by`/`updated_by`,
  a FormRequest, a Policy, and a factory? Check siblings.
- **Tests**: factories put everything in one default business (`BusinessFactory::defaultId()`). A user
  that should see all of it is `User::factory()->owner()`; a "logged in but lacks the permission" user is
  `staffUser()` (from `tests/Pest.php`) — a bare factory user has no shop and is redirected to `no-shop`.
- **VAT / tax**: never trust a client-supplied `tax_amount`. VAT is computed server-side by
  `TaxService` via the shared `CalculateSaleTotals` action, gated per shop on `shops.vat_registered`
  (default **false**, which preserves the legacy passthrough). Products carry a nullable `tax_class`
  (`App\Enums\TaxClass` — Kenyan 16% standard / 8% reduced / zero-rated / exempt / non-VAT, with KRA
  category codes). `sales.tax_inclusive` records which formula produced `total_amount`, because
  inclusive and exclusive pricing do not add up the same way. **Profit is net of VAT**:
  `total_profit = total_amount - tax_amount - total_cost`. Read
  [docs/vat.md](docs/vat.md) before touching tax, sale totals or invoice numbering.
- **Invoice numbers** come from `InvoiceNumberService` (gapless per-shop sequence in
  `invoice_sequences`), never `Str::random()`.
- **Money/stock**: amounts are `decimal(10,2)`; stock quantities are integers.
  ⚠️ **Correction to a long-standing claim in this file:** stock changes do **not** all flow through
  `InventoryService`/`StockMovement`. The sale path calls `$product->decrement('stock_quantity', …)`
  directly and writes **no** `StockMovement` row; customer returns don't restock at all. Only intake,
  adjustments, and purchase returns use `RecordStockMovement`. COGS is the product's **current
  `cost_price` snapshotted onto `sale_items.unit_cost`** — there is no FIFO, and `cost_layers`,
  `cost_allocations`, `CostingService` and the `CostingMethod` enum are unused scaffolding.
  **Read [pos-domain-rules](.claude/skills/pos-domain-rules/SKILL.md) before touching stock, cost,
  returns, credit, registers, or per-shop attribution.**
- **AI calls** go through `AiManager` (respect per-shop provider); **e-commerce/WhatsApp** go through their
  services + queued jobs, never inline HTTP in a controller. ⚠️ Only the `qwen` and `null` AI drivers are
  actually implemented — `openai`/`claude`/`gemini` are configured and offered in the UI but fall back to
  `NullDriver`, producing silently empty output.
- Run `vendor/bin/pint` and the relevant tests before declaring done. (A PostToolUse hook already runs Pint
  on each edited PHP file.)

## ⚠ Gaps Found

Reported from evidence in the repo — not invented:

- **`.env.example` is still missing some keys that the code reads.** `config/services.php` references env
  vars absent from `.env.example`: `POSTMARK_API_KEY`, `RESEND_API_KEY`,
  `SLACK_BOT_USER_OAUTH_TOKEN`/`SLACK_BOT_USER_DEFAULT_CHANNEL`, `WHATSAPP_API_URL`/`WHATSAPP_API_TOKEN`/
  `WHATSAPP_PHONE_NUMBER_ID`, `META_APP_ID`/`META_APP_SECRET`/`META_GRAPH_VERSION`/`META_REDIRECT_URI`,
  and `GOOGLE_ADS_*`. Add these to `.env.example`.
  ✅ The `config/ai.php` keys (`AI_DRIVER`, `QWEN_*`, `OPENAI_*`, `ANTHROPIC_*`, `GEMINI_*`) and
  `AUDIT_DB_DATABASE` **are now present**; Baileys keys always were.
- ✅ **FIXED — all four AI providers now have drivers.** `AiProvider` offers qwen/openai/claude/gemini in
  the campaign form, but `AiManager::build()` only constructed `qwen`, and `forShop()` caught the resulting
  `InvalidArgumentException` into `NullDriver` — so picking claude/openai/gemini silently produced
  deterministic template text that read like bland model output. `ClaudeDriver` (Anthropic Messages API,
  `output_config.format` JSON schema, explicit `stop_reason: refusal` handling), `OpenAiDriver`
  (chat-completions) and `GeminiDriver` (`generateContent`) are implemented and registered; the fallback
  now logs. Covered by [AiDriverTest](tests/Feature/AiDriverTest.php).
  ⚠️ `config/ai.php` pins `claude-opus-5`; the Gemini default (`gemini-2.0-flash`) is **unverified** —
  check Google's current model list, since a retired id degrades to the template stub.
- ✅ **FIXED — the WooCommerce webhook used to fail open.** It wrapped HMAC verification in
  `if ($webhookSecret)`, so a shop with no configured secret accepted any unauthenticated POST and created
  orders from it. All three webhook handlers now fail closed, locked in by
  [tests/Feature/WebhookSignatureVerificationTest.php](tests/Feature/WebhookSignatureVerificationTest.php).
  ⚠️ **Operational precondition:** each shop with a WooCommerce integration must now have an encrypted
  `webhook_secret` in `settings.integrations.woocommerce`, or its webhooks are rejected with 400.
- ✅ **FIXED — API rate limiting.** `bootstrap/app.php` now appends `throttle:api` to the api middleware
  group (so new endpoints are covered by default), with named `auth`/`writes`/`destructive`/`webhooks`
  limiters registered in `AppServiceProvider` and limits in [config/ratelimit.php](config/ratelimit.php).
  All 109 api routes are covered; pinned by tests/Feature/ApiRateLimitTest.php.
- ✅ **FIXED — the dead controller routes.** `alerts.*`, `low-stock-alerts.*`, `payments.*` and
  `audit-logs.*` are implemented; `cost-layers.*` was removed as unused scaffolding.
- ✅ **FIXED — the "order-dependent" test failure was never order-dependent.**
  `SaleNotificationsTest > it sends CreditBalanceNotification for credit sales` was a **50/50 coin flip**:
  `CustomerFactory` sets `'customer_type' => fake()->randomElement(['retail', 'wholesale'])`, and
  `StoreSaleRequest` rejects credit sales for retail customers ("Credit sales are only available for
  wholesale customers"), so the POST failed validation and dispatched no notification. Running other
  files first merely reshuffled the faker sequence. The test now forces `customer_type => 'wholesale'`.
  ⚠️ **Same trap elsewhere:** any test that exercises credit must force `customer_type`, or use the
  `CustomerFactory::withCredit()` / `wholesale()` states — never the bare factory.
- ⚠️ **The suite needs `memory_limit=512M`** (now set in `phpunit.xml`). At the old 128M it died mid-run,
  which is why a complete result was never seen.
- ✅ **FIXED — the e-commerce importers wrote a SELLING price into `cost_price`, producing negative profit.**
  `WooCommerceProductSyncService` mapped `cost_price = regular_price` (the pre-discount selling price;
  Woo core has **no** cost-of-goods field) and `ShopifyProductSyncService` mapped
  `cost_price = compare_at_price ?? price` (the struck-through "was" price). Both sit at or above what the
  customer pays, so **60 of 78 live products had `cost_price > selling_price`** and **19 of 25 sales
  reported negative profit** (−17,055 net). Both services now leave the cost **NULL** unless a real one is
  available — a Woo cost-of-goods meta key (`_wc_cog_cost` et al.) or Shopify's `inventory_item.cost` — and
  **never overwrite a cost that already exists locally** on re-sync. `BackfillShopProductPivot` no longer
  derives cost from platform prices, and `syncToPlatform` no longer publishes the purchase cost to Shopify
  as `compare_at_price`. Covered by [PurchaseCostTest](tests/Feature/PurchaseCostTest.php).
  - `products.cost_price` / `product_variations.cost_price` are now **nullable**: NULL means "cost unknown",
    which is distinct from a genuine zero. `Product::boot` no longer coerces blank to 0, and the
    `CreateProductAction`/`UpdateProductAction` defaults are NULL. Use `Product::hasPurchaseCost()` and the
    `missingPurchaseCost()` / `costAboveSellingPrice()` scopes rather than testing the column directly.
  - ⚠️ **`sale_items.unit_cost` is NOT NULL**, so every sale path coalesces the cost: both `SaleController`s
    use `(… ->cost_price) ?? 0`. **Any new code reading `cost_price` for arithmetic must do the same** or a
    sale of an uncosted product throws.
  - **Rollout order matters**: `php artisan migrate` → `php artisan db:seed --class=PermissionSeeder` +
    `--class=RoleSeeder` (adds/grants `products.set-cost`) → `php artisan products:repair-purchase-cost`
    (dry run) → `--apply` → enter real costs at **Products → Purchase Costs**
    (`products.purchase-costs.index`, gated by `ProductPolicy::setCost`) →
    `php artisan sales:recompute-profit` (dry run) → `--apply`. `sales:recompute-profit` **skips** any sale
    still containing an uncosted product rather than zeroing it, so it is safe to run early and repeatedly;
    `--zero-unknown` overrides that. **Full command reference: [docs/purchase-costs.md](docs/purchase-costs.md).**
  - ⚠️ Between clearing the bad costs and entering the real ones, sales record **zero COGS**, so profit is
    *overstated* rather than negative. `sales:recompute-profit` corrects those retroactively.
- ✅ **FIXED — VAT was never calculated and profit counted it as margin.** `tax_amount` was a
  client-supplied number the server stored verbatim (`StoreSaleRequest`: `numeric|min:0|max:100000`),
  and `total_profit` was `total_amount - total_cost` where `total_amount` already contained the tax —
  so the VAT owed to KRA, plus the recharged delivery/packaging/other fees, were all booked as
  profit. There is now a server-side VAT engine (`TaxService` + `CalculateSaleTotals`, shared by both
  `SaleController`s), per-product tax classes, per-line tax snapshots, a VAT summary report at
  `reports.vat`, and `total_profit = total_amount - tax_amount - total_cost`.
  `sales:recompute-profit` carried the same bug and was fixed with it, so it doubles as the
  retroactive repair. Covered by [SaleVatTest](tests/Feature/SaleVatTest.php),
  [TaxServiceTest](tests/Feature/TaxServiceTest.php) and [VatReportTest](tests/Feature/VatReportTest.php).
  ⚠️ The engine stays **off** until a shop is flagged `vat_registered`, and switching a shop to
  **exclusive** pricing needs a paired Flutter change — the app computes the displayed total locally.
- ✅ **FIXED — invoice numbers were random.** `'INV-'.Str::random(8)` on a `unique` column: not a
  serial number, and a collision would fail a sale at the till. Now a gapless per-shop sequence
  (`invoice_sequences` + `InvoiceNumberService`), e.g. `INV-NRB-2026-000123`.
- ✅ **FIXED — `UpdateShopAction` replaced `settings` wholesale**, so saving one settings panel would
  drop every other section (sale-notification switches, integrations). It now merges into the stored
  blob before `processIntegrations` prunes disabled integrations.
- **Customer phone numbers now have a canonical form.** `customers.phone` keeps whatever the cashier
  typed; `customers.phone_normalized` holds the MSISDN (`254712345678`), maintained on write and
  queryable via `Customer::wherePhone()`. Backfill historic rows with
  `php artisan customers:normalize-phones --apply`. Use `App\Support\PhoneNumber` for any new
  number handling.
- 🔴 **Customer returns never restock inventory**, and sales write no `StockMovement` row — so
  `stock_movements` is not a complete ledger and cannot be used to reconcile stock. See
  [pos-domain-rules](.claude/skills/pos-domain-rules/SKILL.md).
- **`RecordStockMovement` has a lost-update race**: it reads `getCurrentQuantity()` then writes an absolute
  value, so concurrent intakes/adjustments on one product can clobber each other. (The sale path's
  `decrement()` is atomic and safe.) Needs `lockForUpdate()` or an atomic delta.
- **Security baseline items still pending** (per `.ai/general/SECURITY_ENFORCEMENT.md`): no security-headers
  middleware, `Password::defaults()` not applied, HTTPS not forced in production, honeypot missing from most
  forms.
- **`.env.example` is missing some keys the code reads** — the remaining `config/services.php` ones; see
  the list above.
- **Performance basics unused**: no `Cache::` usage in `app/` beyond one SMS service, and no
  `chunk()`/`chunkById()`/`lazy()`/`cursor()` anywhere — exports and reports materialise everything.
  Several report queries use `whereDate()` on indexed columns, defeating the `['shop_id','created_at']`
  indexes. 5 `Http::` calls in `Services/Integration/` have no timeout; only 2 of 6 jobs implement `failed()`.
- 🔴 **The queue worker must name its queues, and nothing in the repo proves production does.** All
  customer messaging is queued on **named** queues (`whatsapp-notifications`, `baileys`,
  `order-notifications`, `ecommerce`); a default worker drains none of them. `composer run dev` now
  passes the full `--queue=` list, but production deployment is undocumented — if WhatsApp "stopped
  working", check the worker's queue list *first*, then `select queue, count(*) from jobs group by queue;`
- **No CI/CD or container config** — `.github/` holds AI guidance, not workflows; no `Dockerfile` or
  `docker-compose.yml`. Deployment is undocumented, and nothing establishes that the **queue worker** and
  **`schedule:run`** are running in production — without them, every sync job and the campaign sweep silently
  never execute.
- **Dead frontend scaffolding.** `resources/css/app.css` holds Tailwind directives and `package.json`
  carries an npm `bootstrap` dep, but the production UI is the static `public/assets/` theme. Consider
  removing the unused Breeze/Tailwind scaffolding to avoid confusion, or wiring it intentionally.
- **`.ai/general` has stale content.** `safe-testing-guide.md` claims the sqlite lines in `phpunit.xml` are
  commented out (they are not — the suite correctly targets `:memory:`) and references another project's
  database and scripts. `0.2 ui_design_guide.md` points at a `designs/theme/` directory that doesn't exist.
  `README.md` still lists Tailwind + Laravel 11/PHP 8.1.
- **`.env` is gitignored and untracked** (verified) — but it holds live remote-database credentials, so treat
  it as a secret to rotate if it has ever been shared.
- **Audit DB is SQLite** (`database/audit.sqlite`) — acceptable for dev, but it must be provisioned and
  backed up in production, since audit records are meant to be immutable and durable.
