# POS SaaS Conversion Plan (Development)

> Input: [pos-market-research.md](pos-market-research.md). Market findings are referenced, not repeated.
> Describes the `main` branch as of 12 October 2026. Other branches are out of scope.
> Approved 12 October 2026. Decisions record the answers given while the plan was reviewed.
> **Revised 12 October 2026: scoped to development.** This plan covers the code, schema and tests needed
> to turn the application into a multi-tenant POS SaaS. Deployment, provisioning, hosting and operations
> are listed in section 9 and will be planned separately. Code that those activities rely on (the empty
> database guard, the operator command, seeders, tenant file paths, data export) stays in scope.

## Development principles

- **The POS is the product.** Registers, cash sessions, sales, stock, receipts, till payments and offline
  trading get the feature work. The other modules (e-commerce sync, campaigns and social, ads, WhatsApp,
  AI) get the tenant scoping, per-tenant credentials and metering needed to be safe in a SaaS, and no new
  features in this plan.
- **Isolation before features.** No feature phase starts until the isolation suite (Phase 2) is green.
- **The flat `/api` never breaks.** Every phase keeps the legacy contract tests passing.
- **Every phase ends green:** Pint, the full suite, the isolation suite and both API contract test sets.

## 1. Current State

### Stack, structure, running

- **Versions:**
  - PHP 8.4 runtime (`^8.2` required).
  - **Laravel 12**: `composer.lock` pins 12.56.0, but `vendor/` has 12.69.3. Run `composer install`
    before work starts.
  - Sanctum 4.3, **Spatie laravel-permission 6.25 with `teams => false`**, Pest 4.5, dompdf 3.1,
    spatie/laravel-honeypot.
  - A private `startappz/wa-gateway-laravel` package (Composer VCS repository).
- **UI:** Blade with a static compiled Bootstrap 5 theme in `public/assets/`. Vite, Tailwind and Alpine
  are leftover Breeze scaffolding.
- **API:** a Sanctum JSON API with **118 routes** (flat, unversioned, `{success, data}`), used by the
  Flutter app. **494 app routes** in total.
- **Layout:** `app/Actions` (about 88), `app/Services` (eight empty stubs; sale and payment logic lives in
  the controllers), `app/Jobs` (10), `app/Policies` (32), `app/Models` (71).
- **Running it:** `composer run dev` (server, named-queue listener, `pail`, Vite). Local host
  `https://fitness-center.test`. The audit trail is a separate SQLite connection with its own migrations.
- **Tests:** `php artisan test --compact`. Pest uses `RefreshDatabase` globally on SQLite `:memory:`, with
  the sync queue and the array mail and cache drivers.

### Data model

- **About 84 tables:** 71 domain, 5 Spatie, 8 framework, plus `audit_logs` on the audit connection.
  **104 migrations.**
- **Top of the hierarchy is `shops`.** There's no tenant table.
- **Owned by a shop (`shop_id`, 42 tables):** sales and items, payments, customers, credit, expenses, cash
  registers, stock records, purchasing, returns and refunds, e-commerce, campaigns and social, WhatsApp,
  invoice sequences, reports and alerts, `shop_product`, `shop_user`.
- **Products are shared across shops:**
  - `products.shop_id` is nullable.
  - `shop_product` holds per-shop stock, reorder level and price overrides.
  - **Stock is kept in two places:** the sale path decrements `products.stock_quantity`, and writes no
    `StockMovement`.
- **Owned by no one:** suppliers, categories, attributes, expense categories, delivery companies, sale
  sources, pricing rules. Users are global.
- **`cash_registers` is a cash session** (one per cashier per shop per day: float, expected and closing
  balances, variance). There's no till or device entity.
- **Unique constraints across the whole system:**
  - **Typed by users:** shop code, SKU, barcode, slugs (products, categories, attributes, expense
    categories), supplier, customer and expense category codes, sale source name.
  - **Generated with random suffixes:** expense, purchase order, intake, adjustment, return and refund
    numbers, payment numbers, campaign and register numbers.
  - **Invoices:** `sales.invoice_number` comes from `InvoiceNumberService` (a per-shop sequence embedding
    the shop code), so its global uniqueness relies on shop codes being unique system-wide.
  - **Other:** `users.email`, Sanctum tokens, Baileys session keys.

### Authentication and authorization

- **Web:** Breeze sessions with email and password. **Registration creates a user with no role and no
  shop.** Profile self-delete (`DELETE /profile`) hard-deletes the user.
- **API:** `POST /api/login` with email, password and `device_name` returns a Sanctum token. No business
  selection, PIN or device pairing.
- **Roles:** global Spatie roles: `super-admin`, `manager`, `cashier`, `inventory-clerk`, `accountant`,
  `customer`. 185 permissions across 34 resources (`{resource}.{action}`). `Gate::before` lets
  `super-admin` do everything; it acts as the shop owner today.
- **Shop restriction is opt-in and fails open:**
  - `User::hasShopRestrictions()` is false for a user with **no** `shop_user` rows, and `canAccessShop()`
    then returns true for **every** shop.
  - Checked by hand in 35 files (some controllers, models, policies and `AuditService`); **no global
    scopes**.
  - Index screens for products, suppliers, categories, customers (web), users, shops and reports don't
    restrict by shop; `?shop_id=` is only an optional filter.
  - `ReportService` treats an empty `allowed_shop_ids` as "all shops".

### POS domains

| Domain | Where it lives | Notes |
|---|---|---|
| Products and inventory | `Product`, `ProductVariation`, `shop_product`, `StockIntake`, `StockAdjustment`, `StockMovement`, `InventorySnapshot`, `LowStockAlert` | Two stock sources; no movement written on sale |
| Sales | Both `SaleController`s (inline transactions), `CalculateSaleTotals`, `TaxService` | Server-side VAT for shops with `vat_registered`; no idempotency key |
| Receipts and invoices | `sales/receipt.blade.php` (browser print), `pdf/sale-invoice.blade.php`, signed invoice URLs | Show `config('app.name')` |
| Payments | `sale_payments`, `payments`, `PaymentMethod` (cash, card, bank transfer, cheque, mobile money, credit, other) | Recorded only; no provider integration |
| Customers and credit | `Customer` (per shop), `CreditAccount`, `CreditTransaction`, `CreditPayment` | Codes unique system-wide |
| Cash sessions | `CashRegister` | Float, expected, closing, variance |
| Stores | `Shop` (`settings` JSON, `vat_registered`, timezone) | |
| Registers/terminals | **None** | |

### Background work, cache, files

- **Jobs:**
  - 10 jobs on `default`, `ecommerce`, `whatsapp-notifications`, `order-notifications` and `baileys`.
  - They carry model ids; none carries a tenant.
  - The scheduler runs the campaign post sweep every 5 minutes and `baileys:prune-media` daily.
  - 9 console commands (backfills and repairs) work over all rows.
- **Cache:** `database` store. Only `SMSservice` (global keys `sms_settings` and `sms_query_parameters`,
  kept for a year) and Baileys media usage (per shop) use it.
- **Files:** all on the `public` disk with flat paths (`products/`, `profile-photos/`, Baileys media per
  session, downloaded product images). No tenant prefix, and publicly reachable.

### Raw SQL, reports, exports

- `DB::table`/`select`/`raw` in 11 files: `ReportService` and `DashboardController` (11 each),
  `ExpenseController`, `InvoiceNumberService`, `SaleController`, `Api\ReportController`,
  `VatReportService`, the e-commerce order controllers and two commands. **145 `*Raw()` clauses** overall.
- **Exports:** report and audit CSV; PDFs for credit statements, customer purchase history, sale and order
  invoices, and the reports dashboard.

### Hard-coded or platform-wide values

- **Branding:** `config('app.name')` on receipts and all PDFs. WooCommerce webhooks named
  `"Fitness Center - {topic}"`. The Meta WhatsApp template has a fixed
  `https://pos.startappz.co.ke/orders/` prefix. Avatars from ui-avatars.com.
- **Locale:** currency (`config('app.currency_*')`, `'KES'` fallbacks in campaigns, AI and the order
  importers), app timezone, phone country code `254`.
- **Tax and numbering:** `config/tax.php` is Kenya-only (per-shop `vat_registered`); invoice prefix, reset
  rule and padding are global config.
- **Payments:** the M-Pesa till `577 42 79` is hard-coded in `NotifyCustomerOfEcommerceOrder`.

### External integrations

| Integration | Today | Tenant fit |
|---|---|---|
| WooCommerce / Shopify | Per shop, encrypted in `shops.settings`; webhooks at `/api/webhooks/{platform}/{shop:uuid}` (HMAC) | Good |
| WhatsApp Cloud API | **One platform number from `.env`** | Must become per tenant |
| WhatsApp Baileys (Chatway Gateway) | One gateway token; sessions per shop; one inbound webhook | Good |
| SMS (`app/Http/Startappz/Services/SMSservice`) | **Broken:** `SmsChannel::isConfigured()` reads `env()`; `SMSservice` uses `App\Models\Setting`, which doesn't exist; global cache keys | Must be fixed and made per tenant |
| Meta / Google Ads, social posting | Platform OAuth app; per-shop encrypted tokens | Good |
| AI (qwen, OpenAI, Claude, Gemini) | **Platform API keys**; provider per shop | Needs metering |
| Email | One platform mailer | Fine; tenant branding missing |
| M-Pesa (Daraja), KRA eTIMS, printers | **None** | |

### Test coverage and environment

- **89 test files** (76 feature, 13 unit), about **1,074 tests**.
- **Baseline on `main`: 1,065 passed, 9 failed:**
  - 8 WhatsApp notification tests depend on the local `.env` WhatsApp settings.
  - 1 HTTPS test fails because `APP_URL` is `https`.
- Shop-scoping tests exist for the calendar, dashboard, expense API and credit. There are **no tenant
  isolation tests**.
- The local database carries migrations from another branch and doesn't match `main`; it's replaced by a
  fresh database in Phase 0.

## 2. Single-Tenant Hotspots

**Schema**
- [ ] A tenant table (`businesses`) and **memberships** (`business_user`), so one login can belong to
      several businesses, with a default business per user.
- [ ] `business_id` on business-wide lists, `shops`, `products`, and every table whose keys must be
      unique per tenant.
- [ ] Per-tenant unique keys (shop code, SKU, barcode, slugs, supplier, customer and category codes,
      sale source name).
- [ ] Invoice numbers stay unique system-wide even though shop codes repeat across businesses.
- [ ] **One stock source: the per-shop pivot** (`shop_product`); `products.stock_quantity` stops being a
      stock figure.
- [ ] Registers (devices) separate from cash sessions.
- [ ] Sync support: `client_uuid`, `updated_at` indexes and a deletions log for every synced table;
      last-sync time per register.

**Queries**
- [ ] Global scopes on every model with `shop_id` (about 42) and every business-wide model (7 lists,
      products, shops).
- [ ] Default-deny shop access (no assignment means no access).
- [ ] Viewer-aware validation instead of `exists:table,id`.
- [ ] Route model binding by uuid on unscoped models.
- [ ] Raw SQL in 11 files and "empty list means all" filters (`ReportService`).
- [ ] CSV and PDF exports.
- [ ] `AuditService` reads (audit rows gain `business_id`).

**Auth and roles**
- [ ] Platform operator (`super-admin`, outside every business) versus business owner (`admin`).
- [ ] Roles per business: default role templates plus custom roles (Spatie teams on `business_id`).
- [ ] Business picker at sign-in, a switcher, and a default business.
- [ ] New API tokens bound to one business; legacy API tokens act on the default business.
- [ ] Signup creates a business and its owner.
- [ ] Account status enforced on web and API.
- [ ] Hard self-delete replaced by deactivation and owner-controlled deletion.

**Background work**
- [ ] Jobs carry the business explicitly (scopes don't apply without a signed-in user).
- [ ] Scheduler sweeps and commands run per business and skip suspended or read-only businesses.

**Cache, files, config**
- [ ] Every cache key includes the business (`sms_settings` and `sms_query_parameters` are global today).
- [ ] Private disk with `{business_uuid}/…` paths, served through authorized routes.
- [ ] Business and shop settings replace `config('app.name')`, currency, timezone, country code, invoice
      prefix, the hard-coded till, the WooCommerce webhook name and the template URL.

**Integrations and API**
- [ ] Per-tenant SMS (fixed) and WhatsApp Cloud credentials, encrypted.
- [ ] Per-shop M-Pesa Till or Paybill number, used for STK Push and payment webhooks through the private
      M-Pesa package.
- [ ] AI usage metered against a prepaid balance per business.
- [ ] The current flat `/api` keeps working unchanged on the new schema; a new `/api/v1` covers every
      feature the Flutter app needs.
- [ ] Tests independent of `.env`, plus a two-business fixture.

## 3. Final Feature Gap Analysis

### Corrections to the research file

| Research said | Codebase shows |
|---|---|
| Cash registers per user per shop; no devices | Correct: `cash_registers` is a cash session. Session-based cash control is cheaper to build than implied. |
| Multi-store inventory is a strength (implied) | **Partly wrong.** Per-shop stock exists in the schema but isn't the figure sales update. **MVP fix:** the per-shop pivot becomes the only stock source, which supports several shop owners. |
| Browser-printable receipt | Correct, and it shows the platform name: a branding leak. |
| Integrations are a strength | Mostly. **SMS is broken** and **WhatsApp Cloud uses one platform number.** Both become per-tenant accounts (MVP). |
| "Breeze self-delete only" | It's a **hard delete** that fails or cascades on business records. |
| eTIMS is an MVP blocker | **Moved to post-launch** (timeline still early); a `FiscalProvider` seam is built in Phase 6. |
| Offline: server idempotency for MVP, app later | **Expanded:** offline trading in each of the user's businesses, synced when back online, is MVP (Phase 6). The owner is warned when a register hasn't synced for 24 hours. |
| Missing from the research | Shared lists with no owner, fail-open report filters, global unique codes, environment-dependent tests, **switching between businesses for one login**, **prepaid AI metering**. |

### MVP development, by phase

| Gap | Phase |
|---|---|
| Fresh local database, `.env`-independent tests, development mail catcher, flat API contract fixtures, CI running the suite | 0 |
| SaaS schema rebuild (empty database), tenant layer, default-deny, per-tenant keys, per-shop stock source, registers and register sessions, per-register invoice series, private tenant-prefixed file storage, per-business cache keys, legacy API compatibility, empty-database migration guard, platform-essential seeders | 1 |
| Tenant isolation test suite, enforced in CI | 2 |
| Platform and owner roles, default and custom roles per business, memberships with picker, switcher and default business, signup, invites, account status, data export and closure, onboarding, CSV import, business and shop settings (including the shop's Till or Paybill number), tenant branding and currency on receipts and PDFs, per-tenant SMS and WhatsApp credentials | 3 |
| Plans per shop (KES 1,500 / 3,000 / 5,000), trial, limits and feature gates, subscription and AI top-up collection through the private M-Pesa package (manual until go-live), read-only plus export when lapsed, prepaid AI credit (top-ups from KES 500) | 4 |
| Platform console, including manual payment recording before go-live; `platform:create-operator` command | 5 |
| **`/api/v1` for Flutter:** business-bound tokens, register pairing, offline sync (pull and push) with a 24-hour sync warning, idempotent sales and payments, M-Pesa STK at the till with payment webhooks, structured receipts for ESC/POS, SMS fix, `FiscalProvider` seam, rate limits per business | 6 (after Phase 2; alongside 4–5) |

### Deferred (after MVP)

- KRA eTIMS, through the `FiscalProvider` seam.
- Platform invoices and platform VAT details.
- Branded subdomains.
- Register PIN sign-in.
- Stock transfers between shops.
- Barcode label printing.
- Suspended or parked sales.
- Loyalty, gift cards and coupons.
- Blind cash count and variance approval.
- Card billing and M-Pesa Ratiba auto-renewal.
- KRA integrator certification.
- Restaurant features (out of scope).
- New features in the non-POS modules (e-commerce, campaigns and social, ads, AI).

## 4. Architecture Decisions

### 4.1 Tenancy model

| Option | Fit for this codebase |
|---|---|
| **A. Shared database, `business_id`, global scopes** | One schema and one migration run; suits many small tenants. The risk is a missed scope, especially in raw SQL (11 files, 145 `*Raw` clauses). Platform reporting is easy. |
| B. Schema per tenant | On MySQL a schema is a database, so this is the same as C. |
| C. Database per tenant | Strongest isolation and simple per-tenant restore. But migrations × N, connection switching in every job and in the global webhook entry points (WhatsApp gateway, M-Pesa), platform reporting through fan-out queries, and per-database cost too high for micro-tenants. |

**Recommendation: A.**
- `business_id` on business-wide tables, `shops`, `products` and any table with per-tenant keys.
- Shop-owned tables are scoped through the viewer's allowed shop ids.
- A reflection test fails if a model with `shop_id` or `business_id` lacks its scope.

**Decision: CONFIRMED (A, shared database)**

### 4.2 Tenant hierarchy

| Option | Notes |
|---|---|
| A. Business → Shop → Staff | No place for tills, device-bound sync or per-till data. |
| **B. Business → Shop → Register (device) → Staff, with cash sessions on a register** | Matches every product in the research; holds device pairing, the sync cursor and last-sync time, and later eTIMS device data. |
| C. Organisation → Business → Shop | Not needed; multi-business membership (4.4) covers people with several businesses. |

**Recommendation: B.**
- Products belong to the **business**; stock and prices per shop on `shop_product`, which is the **only**
  stock source.
- `cash_registers` becomes register sessions that reference a register.

**Decision: CONFIRMED (B, per-shop pivot as the only stock source)**

### 4.3 Tenant resolution

| Option | Notes |
|---|---|
| **A. The signed-in user's current business** | Stored in the session on the web and bound to the token on `/api/v1`; re-checked against membership on every request. No route changes. |
| B. Subdomain per tenant | Branded login pages; wildcard DNS and TLS, cookie domains, a tenant host in the app. |
| C. Path prefix (`/t/{slug}/…`) | Changes all 494 routes for no benefit. |
| D. Device registration | Complements A: a paired register implies business, shop and register. |

**Recommendation: A plus D.**
- Webhooks keep resolving by shop uuid, WhatsApp by session key, and M-Pesa payment webhooks by the shop's
  Till or Paybill number and the request id.
- Jobs and commands set the business explicitly.
- Legacy flat-API tokens resolve to the user's **default business**.

**Decision: CONFIRMED (A + D; branded subdomains after launch)**

### 4.4 Accounts model

| Option | Notes |
|---|---|
| A. A login belongs to exactly one business | Simplest, but doesn't allow one person to run two businesses. |
| **B. One login (unique email) with memberships in one or more businesses** | Picker at sign-in only when there's more than one business and no usable default, a switcher in the top bar, a **default business** per user, and a role per membership (Spatie teams on `business_id`). |
| C. A separate account per business | Breaks the single login. |

**Recommendation: B.**
- **Roles:**
  - `super-admin` is the platform operator: global, never a business member, and the only user
    `Gate::before` lets do everything.
  - `admin` is a business owner (several owners allowed; the founding owner is protected).
  - Each business gets default roles (manager, cashier, accountant, inventory clerk) plus its own
    **custom roles**.
- **Shop access:** owners see all their shops; others see their assigned shops; **no assignment means no
  access**.
- **Invites:** an invite to an existing email adds a membership to that login.
- **Which business a sign-in opens:**
  - **One business:** that business is selected automatically, with no picker on the web or the API.
  - **Several businesses with a default:** sign-in goes straight into the default business; the switcher
    in the top bar (web) or a switch request (API) changes it.
  - **Several businesses, no usable default** (none set, or the membership is no longer active): the
    picker is shown, and the chosen business becomes the default.
  - A membership that's inactive, or a business that's suspended or closing, is never selected
    automatically.
- **API:**
  - `/api/v1` sign-in follows the same rules: with one business, or a usable default, it returns a token
    **for that business** straight away, along with the list of the user's businesses. Otherwise it
    returns only the list, and the app requests a token for the chosen business.
  - Tokens are always bound to one business (`personal_access_tokens.business_id`); switching issues
    another token.
  - Legacy `/api/login` tokens act on the default business, which the user can change on the web.
- **Leak risks:**
  - The memoised shop list must be keyed by business.
  - The session's business is re-checked against membership on every request.
  - A token can never be used for another business.

**Decision: CONFIRMED (B, memberships with picker, switcher and default business; one business, or a
usable default, signs in without the picker)**

### 4.5 Offline support and sync

| Option | Notes |
|---|---|
| A. Online only | Rejected; doesn't suit Kenyan connectivity (research section 4). |
| B. Offline sale capture only | Not enough: the app must keep trading in each of the user's businesses. |
| **C. Offline trading per business with two-way sync** | The app keeps a separate local store for each business the user belongs to, each with its own token and register. **Pull:** catalogue, prices, per-shop stock, customers, staff and settings changed since a cursor, including deletions. **Push:** an outbox of sales, payments, new customers and cash-session events, each with a `client_uuid`; idempotent, applied in order, in batches, with a result per item. |

**Recommendation: C**, limited for MVP:
- **Editable offline:** sales, sale payments, customers, cash sessions.
- **Read-only offline:** catalogue and stock.
- **Conflict rules:** the server wins for master data; offline sales are always accepted and stock may go
  negative (flagged); prices are taken from the sale as recorded; original timestamps are kept.
- **Sync at least once a day:** each register records its last sync time. After 24 hours without a sync
  (configurable), the **shop owner is warned** (dashboard banner and a notification naming the register
  and its last sync time) and the API includes a `sync_overdue` flag. Nothing is blocked.
- **Server needs:** `client_uuid` (unique per business) on pushed records, `updated_at` indexes, a
  deletions log, pull and push endpoints.
- **Offline invoice numbers, M-Pesa payments made while the till is offline, duplicate customers and
  sales made before a lapse** follow the rules in 4.9.

**Decision: CONFIRMED (C, with the MVP scope and the 24-hour warning above)**

### 4.6 Billing

- **Pricing:** per shop, three tiers at **KES 1,500, KES 3,000 and KES 5,000 per shop per month**.
  - Stored as plan data and editable without a deploy.
  - **Generic for now:** the tiers are named Tier 1, Tier 2 and Tier 3, with placeholder limits (staff,
    products, registers, WhatsApp sessions, features) that the business sets later.
  - New businesses start on a trial (placeholder length, also plan data).
- **Collection:** through the **private M-Pesa package** (a Daraja integration: STK Push plus payment
  webhooks).
  - Subscriptions and AI top-ups are paid to the platform's number.
  - **Platform go-live is expected around 12 November 2026.** Until then, payments are recorded by hand
    in the platform console, and the automated path is built behind a switch that's turned on at go-live.
- **Lapsed subscription:** **read-only plus export for 30 business days** (4.9).
  - Sign-in and viewing work.
  - Every write is refused on web, API and sync push, with `subscription_inactive`, except sales made
    before the lapse (4.9).
  - Export stays available.
- **Downgrades:** the business must delete the shops above the new plan's quota; until it does, every
  shop is read-only (4.9).
- **AI:** **prepaid and metered.**
  - Each call is recorded (business, shop, provider, model, tokens, cost) against a credit balance per
    business.
  - Usage is **blocked at zero** until the business tops up. The owner is notified, and blocked work is
    retried after the top-up (4.9).
  - **Top-ups from KES 500.**
  - **The credit unit is generic for now:** a configurable conversion (how many credits a KES top-up buys
    and how many credits a call costs), so the real unit can be set later without code changes.
- **Platform invoices and platform VAT** are out of scope for now (post-launch). Phase 4 keeps
  subscriptions, payment records and M-Pesa receipt numbers.
- **Gating:** one `hasFeature()`/`limit()` check used by middleware, policies, the UI, jobs and the sync
  API.

**Decision: CONFIRMED (per shop, three generic tiers, private M-Pesa package, read-only plus export,
prepaid AI from KES 500 with a generic credit unit)**

### 4.7 Database restructure

There's no data in any environment, so this is a rebuild rather than a migration.

| Option | Notes |
|---|---|
| **A. Rewrite the migrations into a clean SaaS baseline** | Consolidated `create_*` migrations with `business_id`, per-tenant keys, registers, memberships, sync columns and the per-shop stock source; drops legacy columns and fixes nullable owners. Valid only because every environment is empty; the history stays in git. |
| B. Keep the 104 migrations and add SaaS migrations on top | Built for existing data that doesn't exist; leaves legacy columns, double stock and backfill code behind. |
| C. `schema:dump --prune` as a baseline, then additive migrations | A middle path that still carries the legacy structure. |

**Recommendation: A.**
- A guard refuses to migrate a database that isn't empty unless explicitly forced.
- Seeders provide only platform essentials: permissions, default role templates and plans.
- The first platform operator is created by a console command, not seeded.
- Tenants start empty.

**Decision: CONFIRMED (empty database; rewrite approach A)**

### 4.8 API strategy

| Option | Notes |
|---|---|
| A. Extend the flat `/api` only | Matches the current project rule, but the new SaaS needs (business-bound tokens, sync) would be bolted onto a contract the existing app relies on. |
| **B. Keep the flat `/api` unchanged and add `/api/v1` alongside it** | No versioned API exists today, so the new one is `v1`. `{success, message, data, meta, code}` responses, an error-code registry (`business_closing`, `subscription_inactive`, `plan_upgrade_required`, `shop_not_linked`, `account_inactive`, `sync_overdue`…), business-bound tokens, sync endpoints, idempotency keys, an OpenAPI document and contract tests. |
| C. GraphQL | No benefit for this team. |

**Recommendation: B.**
- **The current flat API must not break.** It keeps its routes, response shapes and `POST /api/login` on
  the new schema, through a compatibility layer:
  - legacy tokens act on the user's default business;
  - responses that include `products.stock_quantity` compute it from per-shop stock;
  - `mobile:contract-diff` and contract tests guard it.
- The CLAUDE.md rule against versioning the existing API stays.
- With no Flutter timeline yet, `/api/v1`, its OpenAPI document and contract tests are the hand-off for
  whenever the app is built.

**Decision: CONFIRMED (B: flat `/api` unchanged, new `/api/v1`)**

### 4.9 Edge-case rules

Decided while the plan was reviewed.

| Case | Rule |
|---|---|
| **Invoice numbers on an offline till** | Each register has its own invoice series, so a till can number sales while offline without colliding with another till: `INV-{shop code}-{register code}-{year}-{sequence}`. The app assigns the next number in its series; the server checks on sync that each number is unique and the series has no gaps or repeats, and flags any that do. Numbers stay unique across the platform through the generated per-shop code. |
| **Sales made before a lapse, synced after it** | Accepted. A pushed sale is accepted when its recorded time is before the moment the subscription lapsed; sales recorded after it are refused with `subscription_inactive` and stay on the device. A recorded time that's implausible (in the future, or before the register's previous sync) is accepted but flagged for review. |
| **M-Pesa payments while the till is offline** | The STK Push is sent through the server and the private M-Pesa package. The payment confirmation reaches the server by webhook even if the till has lost its connection by then. The till marks the payment **pending** and receives the result on its next sync. **A till with no connection at all** can't send an STK Push: the customer pays the shop's Till or Paybill directly, the cashier enters the M-Pesa code, and the payment stays pending until the till syncs. The server then matches the code against the payment notifications received for the shop's number; a code with no match stays pending and is flagged for review. |
| **Duplicate customers created offline** | Not merged automatically. A pushed customer whose phone number (normalized) matches an existing customer of the same business is saved and **flagged for review**; the owner or a manager merges or keeps both from a review screen. |
| **Downgrading to fewer shops** | The business must delete the shops above the new plan's quota. Until the shop count is within the quota, **every shop is read-only** (sign-in, viewing, export and deleting shops still work). |
| **Moving a shop to another business** | Not allowed. A shop leaves a business only by being deleted (its data goes through the export and erasure rules). |
| **Handing over a business** | The owner can hand ownership to an existing **co-owner** only, confirmed by a one-time code emailed to the owner (the same mechanism as the closure confirmation). The previous owner stays a co-owner unless they leave. |
| **Two businesses in two browser tabs** | Not supported. A session has **one active business**; switching changes it for every tab. A page opened before a switch carries the business it was loaded for, and a form submitted from it is refused with a "this business was switched; reload the page" message rather than saved into the new business. |
| **AI credit runs out during work** | The owner is notified. Work that was blocked (for example a scheduled campaign post) is kept **waiting for credit** and retried automatically after a top-up. |
| **How long a lapsed business stays read-only** | **30 business days** from the lapse. Then the business is **closed through the business-closure flow** (Phase 3): a final export is emailed to the owner, and the data is deleted when the closure grace period ends. Paying before the deletion reopens the business. The owner is warned before the 30 business days run out. |
| **Currency** | Set **per business**, never hard-coded. Every amount, receipt, PDF, report and AI prompt uses the business's currency; `config('app.currency_*')` and the `'KES'` fallbacks go. |

**Decision: CONFIRMED (rules above)**

## 5. Phased Implementation Plan

Every phase also has to keep the **flat `/api` working unchanged**, add the matching `/api/v1` endpoints
for whatever it changes (4.8), keep the isolation suite green (from Phase 2), and run Pint and the full
suite.

### Dependencies

**Already installed and sufficient:** `laravel/framework` 12, `laravel/sanctum` 4 (business-bound tokens),
`spatie/laravel-permission` 6.25 (per-business roles through teams, currently switched off),
`barryvdh/laravel-dompdf`, `spatie/laravel-honeypot`, `pestphp/pest` 4 (architecture tests can enforce
that every model with `shop_id` uses its scope). PHP extensions available: `zip`, `gd`, `imagick`, `intl`,
`bcmath`, `redis`, `pcntl`, `sodium`. Redis runs locally (queues, cache and locks for invoice series and
sync).

| Need | Package or service | Phase |
|---|---|---|
| M-Pesa STK Push and payment webhooks | The **private M-Pesa package** (Composer VCS repository with token access, like `wa-gateway-laravel`) | 4, 6 |
| OpenAPI docs for `/api/v1` | **`dedoc/scramble`** (confirmed) | 6 |
| Tenant SMS | **The current SMS service** (`app/Http/Startappz/Services/SMSservice`), fixed and made per tenant; no new package | 6 |
| Mail | A **development** mail catcher (log driver or Mailpit) | 0 |
| Optional | `larastan/larastan` (catch raw queries that skip scoping), `league/csv` (CSV import) | 1, 3 |

No tenancy package is needed; scoping is built in-house (4.1).

### Phase 0: Development baseline

- **Goal:** a known, reproducible starting point for development.
- **Work:**
  - `composer install` to match the lock file.
  - **A new, empty local database**, created by you and pointed to from `.env`. The project's safety
    hook blocks wiping the current one, which carries another branch's migrations.
  - Make the 8 WhatsApp tests independent of `.env`; fix or isolate the HTTPS test.
  - Point local mail at a development catcher (log driver or Mailpit), so invites and codes never reach
    real inboxes.
  - Record the baseline.
  - Record the flat API's current contract (routes and response shapes) as fixtures for the
    compatibility tests.
  - A CI pipeline that runs Pint, the full suite and the flat API contract tests on every push.
- **Schema:** none.
- **Acceptance:** the suite is green on a fresh database and in CI; the flat API contract fixtures are
  recorded.

### Phase 1: SaaS schema and automatic tenant scoping

- **Goal:** a clean multi-tenant POS schema in which every query is automatically limited to one
  business, with no shop access unless granted, while the flat API keeps working.
- **Files and modules:**
  - **Migrations:** rewritten (decision 4.7), with the empty-database guard.
  - **Seeders:** platform essentials only (permissions, role templates, plans).
  - **New:** a `Business` model, `BusinessMembership`, `Register`, `TenantContext` (current business from
    the session or token; set explicitly for jobs and commands), the `BelongsToBusiness` and
    `BelongsToShop` traits, `BusinessScope` and `ShopAccessScope`, a viewer-aware `exists` rule, a
    per-tenant unique rule.
  - **Models:** every model with `shop_id` (42) and the 7 list models; `User` (memberships, current and
    default business, default-deny shops); `Product` and `Shop`.
  - **POS core:** both sale controllers and the stock services (per-shop pivot stock plus a
    `StockMovement` per sale); `CashRegister` becomes register sessions tied to a `Register`;
    `InvoiceNumberService` moves to per-register series with Redis locks.
  - **Raw SQL:** `ReportService`, `DashboardController`, `VatReportService`, `ExpenseController`,
    `Api\ReportController`, the e-commerce order controllers, `InvoiceNumberService`.
  - **Also:** CSV and PDF exports, `AuditService`, the 10 jobs, the scheduler, the 9 commands
    (`--business`), cache keys.
  - **Files:** a private disk with `{business_uuid}/…` paths, served through authorized routes, with a
    per-tenant quota; product images, profile photos and Baileys media moved onto it.
  - **Flat API compatibility:** default-business resolution for legacy tokens; computed product stock in
    responses.
- **Schema:**
  - `businesses` (uuid, name, settings, status).
  - `business_user` (status, invited by, joined at); `users.default_business_id`.
  - Spatie `teams => true` with `team_foreign_key = business_id` (roles per business).
  - `business_id` on shops, products and the 7 lists.
  - `registers` (shop, name, code, device id, paired at, last synced at); `cash_registers` becomes
    register sessions with a register reference.
  - `client_uuid` plus a unique `(business_id, client_uuid)` on sales, sale payments, customers and
    register sessions.
  - `sync_deletions` (business, entity, id, deleted at).
  - Per-tenant unique keys; invoice numbers kept unique system-wide through a generated per-shop code.
  - **Invoice series per register** (`registers.code`, a per-register sequence) so tills can number sales
    offline (4.9).
  - `businesses.currency` (and timezone); amounts never assume KES (4.9).
  - Review flags: `customers.needs_review` (possible duplicate) and a flag for sales with an implausible
    recorded time.
  - `products.stock_quantity` removed as a stored figure; stock lives only on `shop_product`.
- **Risks (leakage):**
  - A missed raw query.
  - Code that runs with no signed-in user (jobs, webhooks, console), where scopes don't apply.
  - `withoutGlobalScopes()` misuse.
  - Route binding or validation accepting another business's ids.
  - The memoised shop list not keyed by business.
  - Signed invoice URLs.
  - Spatie's cache holding team-scoped role queries.
  - Files reachable through the old public paths.
  - The flat API changing shape by accident.
- **Acceptance:**
  - A reflection test: every model with `shop_id` or `business_id` has its scope.
  - Scope unit tests: owner, staff, no assignment, switching business.
  - Raw SQL paths and reports tested with two businesses.
  - A sale decrements the shop's pivot stock and writes a movement.
  - A cash session opens and closes on a register; two registers in one shop number invoices in separate
    series without collisions.
  - The migration guard refuses a non-empty database unless forced.
  - Uploaded files land under the business prefix and aren't publicly reachable.
  - The flat API contract tests (Phase 0 fixtures) pass.
  - The full suite green, with existing tests adapted to the business fixture.

### Phase 2: Tenant isolation test suite

- **Goal:** proof that business A never sees or changes business B's data, through any channel.
- **Files:** `tests/Feature/TenantIsolation/*`; a two-business fixture with a user who belongs to
  **both**.
- **Tests:**
  - **Resource matrix:** web, flat API, `/api/v1` and sync; index, show, update and delete; foreign ids
    rejected on create.
  - **Canary crawl:** every parameter-free GET route, run as A, must not contain B's marker strings.
  - **Switching:** after switching to A, nothing from B is shown or accepted.
  - **Tokens:** a v1 token for A is refused for B; a legacy token acts only on the default business.
  - **Sync:** a pull for A never returns B's rows; a push with B's ids is rejected.
  - **Documents and background work:** reports, CSV, PDFs, jobs, webhooks (including M-Pesa) and
    scheduled sweeps stay within one business.
  - **Files:** another business's file returns 404 or 403.
  - **Uniqueness:** both businesses can use the same codes.
- **CI:** the isolation suite becomes a required check that blocks merges.
- **Schema:** none.
- **Risks:** routes with parameters that the crawl skips; the resource matrix covers those.
- **Acceptance:** the isolation suite is green and required in CI.

### Phase 3: Accounts, roles, onboarding, business settings

- **Goal:** self-service signup, memberships with switching, correct roles, and tenant-owned settings,
  branding and integration credentials.
- **Files:**
  - **Roles:** `RoleSeeder` and `PermissionSeeder` (global `super-admin` and `admin`; role templates
    copied per business; custom roles); `AppServiceProvider` (`Gate::before` only for `super-admin`).
  - **Sign-in:**
    - `RegisteredUserController` (creates a business and its owner; honeypot).
    - The business picker, switcher and default-business setting (web).
    - `/api/v1` sign-in, list businesses, issue a token per business, switch, sign out.
  - **People:** invites; `UserPolicy` (owner protection; only owners delete); account status middleware;
    deactivation instead of self-delete; data export and business closure (final export emailed to the
    owner, deletion when the closure grace period ends; also triggered automatically after 30 business
    days of lapse, 4.9).
  - **Ownership handover** to an existing co-owner, confirmed by a code emailed to the owner (4.9).
  - **One active business per session:** pages and forms carry the business they were loaded for, and a
    submission for a business that's no longer active is refused with a "reload the page" message (4.9).
  - **Shop deletion** as the only way a shop leaves a business (no transfer).
  - **Onboarding:** the onboarding checklist (business, first shop, first register, products, first sale)
    and CSV product import.
  - **Settings:** business and shop name, logo, receipt header and footer, currency, timezone, invoice
    prefix, and the **shop's M-Pesa Till or Paybill number**. These replace `config('app.name')` and the
    hard-coded till in `NotifyCustomerOfEcommerceOrder`.
  - **Encrypted per-tenant credentials** for SMS and WhatsApp Cloud.
- **Schema:**
  - Settings on businesses and shops, including `shops.mpesa_shortcode` and `shops.mpesa_shortcode_type`
    (till or paybill).
  - `business_integrations` (business, optional shop, type, encrypted credentials, status).
  - Invites, exports and closure tables.
  - Foreign keys from business records to `users` set to `nullOnDelete`.
- **Risks:**
  - Privilege escalation through role assignment or invites across businesses.
  - The founding owner locked out.
  - A session or token used after the membership is removed.
  - Credentials leaking into logs.
- **Acceptance:**
  - Signup creates a business and its owner.
  - A user with one business signs in straight to it, on the web and `/api/v1`, with no picker.
  - A user in two businesses with a default signs in straight to the default; without a usable default
    they see the picker. They can switch, and only ever see the active business.
  - Custom roles apply only in their business.
  - A removed member loses web and API access immediately.
  - Ownership passes to a co-owner only with the emailed code; a non-co-owner can't receive it.
  - A form loaded for business A and submitted after switching to B is refused and nothing is saved in B.
  - Amounts, receipts and PDFs use the business's currency.
  - Receipts and PDFs show the tenant's branding and the shop's own Till or Paybill.
  - SMS and WhatsApp send through the tenant's own credentials (faked in tests).
  - `/api/v1` endpoints exist for every flow above; the flat API contract tests still pass.

### Phase 4: Billing, entitlements, metering

- **Goal:** each business is on a plan per shop, limits are enforced, collection goes through the private
  M-Pesa package, and AI runs on prepaid credit.
- **Files:**
  - **Billing module:** `Plan`, `Subscription`, `PlatformPayment`, `AiCreditBalance`, `AiUsageEvent`.
  - The entitlements service and a `Feature` enum.
  - A `PaymentCollector` adapter over the **private M-Pesa package** (STK Push and payment webhooks), with
    a go-live switch.
  - **Read-only middleware:** refuses writes on web, both APIs and sync push; allows export. It applies
    for 30 business days after a lapse, and whenever a business has more shops than its plan allows
    (deleting shops stays allowed so the business can get back within its quota).
  - A business-day calendar for the 30-day count.
  - **`AiManager`:** records usage, deducts credit, and refuses calls at zero balance; the owner is
    notified, and blocked jobs wait for credit and are re-dispatched after a top-up.
  - The billing page, the AI top-up flow (minimum KES 500), and reminder notifications.
- **Schema:**
  - `plans`: Tier 1, Tier 2 and Tier 3 at KES 1,500, 3,000 and 5,000 per shop per month; placeholder
    limits, features and trial length as data.
  - The AI credit conversion (credits per KES, credits per call) as configuration.
  - `subscriptions`: shop count, price at signup, status, period end, trial end.
  - `platform_payments`: purpose (subscription or AI top-up), M-Pesa receipt number, raw webhook data,
    recorded by (for manual entries).
  - `ai_credit_balances`, `ai_usage_events`.
- **Risks:**
  - Spoofed webhooks (verify, make idempotent).
  - A payment applied to the wrong business (match on the request and account reference, never on the
    phone number).
  - Read-only mode missing a write path, such as the sync push or a legacy API endpoint.
  - Metering failures letting AI usage through unrecorded.
- **Acceptance:**
  - A trial starts at signup.
  - Adding a shop or register beyond the plan is refused on web and both APIs.
  - A manual payment, and later a webhook payment, extends the subscription exactly once.
  - A lapsed business can read and export, but every write path returns `subscription_inactive`, for 30
    business days; the owner is warned before the end, and on the 31st business day the closure flow
    starts. A payment before deletion reopens the business.
  - After a downgrade with too many shops, every shop is read-only until enough shops are deleted.
  - An AI call deducts credit; at zero balance AI calls are refused until a top-up of at least KES 500,
    the owner is notified, and the blocked work runs after the top-up.

### Phase 5: Platform admin console

- **Goal:** manage businesses on the platform without database access.
- **Files:** a `platform.*` route group gated to `super-admin`:
  - businesses (plan, shops, registers, members, usage, status, AI balance);
  - suspend and reactivate; change plan; extend trial;
  - **record manual payments and AI top-ups** (needed until go-live);
  - audited, time-limited impersonation with a banner;
  - **`platform:create-operator`**, a console command that creates a platform operator.
- **Schema:** a suspension status on businesses; an impersonation log.
- **Risks:** the console as a way round isolation, so every action is audited.
- **Acceptance:** only platform operators reach `/platform`; impersonation is logged and shown;
  suspension blocks web, both APIs and sync; manual payments are attributed to the operator who entered
  them; the operator command creates a user outside every business.

### Phase 6: `/api/v1` for Flutter, offline sync, till payments

- **Goal:** a complete, documented API that a Flutter POS can trade on, online or offline, in any of the
  user's businesses.
- **Starts:** after Phase 2; can run alongside Phases 4 and 5.
- **Work:**
  - `/api/v1` with an OpenAPI document and the error-code registry; the same features as the web.
  - **Register pairing:** a device is paired to a shop's register and receives a business-bound token.
  - **Sync:** `GET /api/v1/sync/pull?since=` and `POST /api/v1/sync/push` (batched, idempotent by
    `client_uuid`, applied in order, results per item).
  - Last-sync tracking; the **24-hour owner warning** and the `sync_overdue` flag.
  - **M-Pesa STK Push at the till** through the private package, using the shop's own Till or Paybill
    number. The payment webhook marks the right sale paid, matched by shop number and request id. A till
    that has gone offline shows the payment as pending and receives the result on its next sync (4.9).
  - **Offline rules (4.9):** per-register invoice series checked on sync; sales recorded before a lapse
    accepted after it; possible duplicate customers flagged, with a review screen to merge or keep them.
  - Structured receipt JSON for ESC/POS printing.
  - **SMS fix, keeping the current SMS service:** replace the missing `App\Models\Setting` with the
    tenant's SMS settings (from `business_integrations`), read configuration through `config()` instead of
    `env()`, and key any cache by business.
  - **Offline M-Pesa entry:** a cashier on a till with no connection enters the M-Pesa code; on sync it's
    matched to the payment notification for the shop's number, or flagged for review (4.9).
  - The `FiscalProvider` seam (a null provider until eTIMS).
  - Rate limits per business on both APIs.
  - `mobile:contract-diff` extended to cover `/api/v1`.
- **Schema:** `payment_requests` (STK requests and webhooks per sale); the Phase 1 sync columns used.
- **Risks:**
  - Duplicate sales on retried pushes.
  - Pushes landing in the wrong business (checked against the token's business).
  - Pulls leaking deleted or foreign rows.
  - Webhooks matched to the wrong sale.
  - Clock skew on offline timestamps.
- **Acceptance:**
  - A paired register's token works only for its business and shop.
  - Pushing the same batch twice creates the records once.
  - A pull for business A returns only A's changes since the cursor, including deletions.
  - A user in two businesses syncs both independently.
  - A register that hasn't synced for 24 hours triggers the owner warning and the API flag, without
    blocking sales.
  - An STK payment marks the right sale paid exactly once, including when the till syncs only afterwards.
  - Two tills number sales offline without collisions; a repeated or missing number in a series is
    flagged on sync.
  - A sale recorded before the lapse is accepted after it; one recorded after is refused and kept on the
    device.
  - A customer pushed with an existing phone number is saved and flagged, not merged.
  - An M-Pesa code entered on an offline till is matched to the shop's payment notification on sync; an
    unmatched or reused code stays pending and is flagged.
  - The OpenAPI document matches the routes (contract test).
  - SMS sends through the tenant's provider.
  - The flat API contract tests still pass.

## 6. Working Through the Phases

- **One branch per phase** off `main`, merged only when the phase's acceptance criteria pass in CI.
- **One Claude Code session per phase.** Start large phases (1, 3, 4, 6) in plan mode with: "Read
  docs/saas-plan.md and plan Phase N only." Run `/clear` between phases; this file carries the continuity.
- **Commit after each completed step** inside a phase, with tests for that step in the same commit.
- **Keep this file current.** When implementation diverges from the plan, update the relevant section
  before moving on, and mark the hotspot checklist (section 2) as items are done.

## 7. Assumptions

1. Other branches are out of scope; this plan starts from `main` alone.
2. **Every environment starts with an empty database**, with no seeded data. The current company becomes
   the first business through signup and CSV import.
3. The target market is Kenya first (KES, VAT on sales, M-Pesa); eTIMS is post-launch.
4. Users have a unique email across the platform, may belong to several businesses with a role in each,
   and have a default business.
5. Products belong to the business; per-shop stock and prices on `shop_product` are the only stock source.
6. Tenants supply their own SMS and WhatsApp Cloud credentials. Shops supply only their M-Pesa Till or
   Paybill number; the private M-Pesa package handles STK Push and payment webhooks for it.
7. The private M-Pesa package can be installed like `wa-gateway-laravel` (a Composer VCS repository with
   token access) and supports this Laravel version. Not checked; it isn't in this repository yet.
8. AI runs on platform keys and is paid for by tenants through prepaid credit; Baileys stays a platform
   gateway with sessions per shop.
9. Files are stored on a private local disk with tenant-prefixed paths.
10. The current flat `/api` must keep working unchanged; the Flutter app will move to `/api/v1` when it's
    built, with no timeline yet.
11. Platform go-live for M-Pesa collection is around 12 November 2026; payments are recorded by hand until
    then.
12. "Business days" are Monday to Friday, excluding Kenyan public holidays, which are kept in a
    configurable calendar.
13. The till has a connection to the server at the moment it sends an STK Push; only the confirmation can
    arrive after the till goes offline (4.9).
14. Each business has one currency for all its shops.
15. The target database is **MySQL 8 or later** with **Redis**; tests run on SQLite in memory.
16. Tenant SMS keeps using the current SMS service's provider integration, with credentials per tenant.
17. Mail uses a development catcher during development.

## 8. Open Questions

None block development.

**Deferred:**
- Real tier names, limits and trial length (generic placeholders until then).
- The real AI credit unit (configurable conversion until then).
- Platform VAT details and platform invoices.

## 9. Out of Scope: Deployment and Operations

Moved out of this plan and to be planned separately once development is under way:

- Provisioning staging and production on the new schema, the rehearsal, smoke tests, rollback and the
  runbook.
- Hosting: the single-server setup, Redis and Horizon on the servers, worker and scheduler supervision,
  provisioning and backing up the audit database.
- Backups (`spatie/laravel-backup`), the per-tenant restore procedure and the restore drill.
- Error monitoring tagged by business (service not yet chosen).
- The tenant data-processing agreement and secret rotation.
- The production PHP version and the production mail provider.
