# POS SaaS Conversion Plan (Development)

> Input: [pos-market-research.md](pos-market-research.md). Market findings are referenced, not repeated.
> Describes the `main` branch as of 12 October 2026. Other branches are out of scope.
> Approved 12 October 2026. Decisions record the answers given while the plan was reviewed.
> **Revised 12 October 2026: scoped to development.** This plan covers the code, schema and tests needed
> to turn the application into a multi-tenant POS SaaS. Deployment, provisioning, hosting and operations
> are listed in section 9 and will be planned separately. Code that those activities rely on (the empty
> database guard, the operator command, seeders, tenant file paths, data export) stays in scope.
> **Corrected 12 October 2026 after a code review against `main` (`37231c1`).** Facts in sections 1–3 were
> re-checked against the code. Changes the review proposes but nobody has decided yet are marked
> **Proposed (review)**. Section 8 lists the questions this raised; four of them now block a phase.
> Section 10 summarises what changed.

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
  existing Flutter app (`docs/flutter/`; `mobile:contract-diff` compares its `api_endpoints.dart` with the
  routes). **505 app routes** in total.
- **Layout:**
  - `app/Actions` (100), `app/Jobs` (10), `app/Policies` (32), `app/Models` (69).
  - `app/Services` has about 57 classes. Eight of them are empty stubs (`SaleService`, `PaymentService`,
    `CustomerService`, `ExpenseService`, `ExpenseCategoryService`, `DashboardService`, `CostingService`,
    `NotificationService`). Sale and payment logic lives in the controllers.
- **Running it:** `composer run dev` (server, named-queue listener, `pail`, Vite). Local host
  `https://fitness-center.test`. The audit trail is a separate SQLite connection with its own migrations.
- **Drivers:** the `.env` uses `DB_CONNECTION=mariadb`. Cache, queue and session use the `database` driver.
  Redis is configured, but nothing uses it.
- **Tests:** `php artisan test --compact`. Pest uses `RefreshDatabase` globally on SQLite `:memory:`, with
  the sync queue and the array mail and cache drivers. **Nothing runs on MySQL or MariaDB**, so row locks
  (`lockForUpdate`) do nothing in tests. One raw query uses `DATE_FORMAT` behind a driver check.

### Data model

- **About 84 tables:** 71 domain, 5 Spatie, 8 framework, plus `audit_logs` on the audit connection.
  **104 migrations.**
- **Top of the hierarchy is `shops`.** There's no tenant table.
- **Owned by a shop (`shop_id`, 42 tables):** sales and items, payments, customers, credit, expenses, cash
  registers, stock records, purchasing, returns and refunds, e-commerce, campaigns and social, WhatsApp,
  invoice sequences, reports and alerts, `shop_product`, `shop_user`.
- **Products are shared across shops:**
  - `products.shop_id` is nullable.
  - `shop_product` has columns for per-shop stock, reorder level and cost, selling and wholesale price
    overrides.
- **Stock lives on the product and the variation, not per shop.**
  - Every POS write goes to `products.stock_quantity` or `product_variations.stock_quantity`: intake,
    adjustments, purchase returns, `RecordStockMovement`, and product create and update.
  - `shop_product.stock_quantity` is written only by the Woo/Shopify product sync and
    `BackfillShopProductPivot`. The per-shop pivot is effectively unused for stock.
  - **Four code paths make a sale**, and each one decrements the product or variation directly without
    writing a `StockMovement`: the web `SaleController`, `Api\SaleController`,
    `ConvertEcommerceOrderToSale` and `ConvertAbandonedCartToSale`. Adjustments do write movements, through
    `RecordStockMovement`.
  - Readers of `stock_quantity` include `CheckLowStockLevels`, `CreateInventorySnapshot`,
    `InventoryService`, `ProductService`, `DashboardController`, `ProductObserver` (which pushes stock to
    Woo/Shopify on `Product::updated`) and both product sync services.
- **Variants:** `product_variations` keeps its own `stock_quantity`, and `shop_product` has no
  `variation_id`, so **variant stock can't be held per shop** in today's schema.
- **FIFO costing tables are unused.** `cost_layers`, `cost_allocations` and `sale_item_stock_allocations`
  have models, but no code uses them. Profit (`RecomputeSaleProfit`) uses the product's cost price and
  ignores the `shop_product.cost_price` override.
- **Soft deletes:** 19 models use `SoftDeletes`.
- **Owned by no one:** suppliers, categories, attributes, expense categories, delivery companies, sale
  sources, pricing rules. Users are global.
- **`cash_registers` is a cash session** (one per cashier per shop per day: float, expected and closing
  balances, variance). There's no till or device entity.
- **Unique constraints across the whole system:**
  - **Typed by users:** shop code, SKU and barcode (on products **and** variations), slugs (products,
    categories, attributes, expense categories), supplier, customer and expense category codes, sale
    source name.
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
  `super-admin` do everything; it acts as the shop owner today. 12 more places call
  `hasRole('super-admin')` directly.
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
| Products and inventory | `Product`, `ProductVariation`, `shop_product`, `StockIntake`, `StockAdjustment`, `StockMovement`, `InventorySnapshot`, `LowStockAlert`; unused `CostLayer`, `CostAllocation`, `SaleItemStockAllocation` | Stock on the product or variation; the per-shop pivot is unused for stock; no movement written on sale |
| Sales | Both `SaleController`s (inline transactions), `ConvertEcommerceOrderToSale`, `ConvertAbandonedCartToSale`, `CalculateSaleTotals`, `TaxService` | Four sale paths; server-side VAT for shops with `vat_registered`; no idempotency key |
| Receipts and invoices | `sales/receipt.blade.php` (browser print), `pdf/sale-invoice.blade.php`, signed invoice URLs | Show `config('app.name')`; signed URLs use `signedRoute` and **never expire** |
| Payments | `sale_payments`, `payments`, `PaymentMethod` (cash, card, bank transfer, cheque, mobile money, credit, other) | Recorded only; no provider integration |
| Customers and credit | `Customer` (per shop), `CreditAccount`, `CreditTransaction`, `CreditPayment` | Codes unique system-wide |
| Cash sessions | `CashRegister` | Float, expected, closing, variance |
| Stores | `Shop` (`settings` JSON, `vat_registered`, `tax_pin`) | No timezone column; only two files use a timezone at all |
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

- **Raw SQL appears in 34 files** (`DB::table`/`select`/`raw`/`statement` and **145 `*Raw()` clauses**):
  - **Heavy users:** `ReportService` and `DashboardController` (11 each), `ExpenseController`,
    `InvoiceNumberService`, `SaleController`, `Api\ReportController`, `VatReportService`, the e-commerce
    order controllers.
  - **Also:** the `Customer`, `Supplier`, `CreditAccount` and `AbandonedCart` models;
    `CustomerController`, `PaymentController`, `CampaignController`, `AlertController`,
    `LowStockAlertController`, `AttributeController`; the `CreditAccount`, `Return`, `PurchaseReturn`,
    `Pricing`, `Supplier`, `Category`, `User`, `Audit` and `AbandonedCartSync` services;
    `ProductObserver`; `HandleGatewayWebhooks`; `StorePurchaseReturnRequest`; the
    `BackfillShopProductPivot` and `AssignProductShopFromSales` commands.
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

**After Phase 0 (13 October 2026; details in [PROGRESS.md](PROGRESS.md)):**
- **Baseline: green on both engines.** SQLite 1,116 passed; MySQL 8 1,125 passed (both with skips: the
  safety-hook cases need `jq`, and the MySQL-only tests skip on SQLite). The 9 failures were environment
  dependencies (WhatsApp settings and `APP_URL` read from `.env`), now pinned in `phpunit.xml`.
- **MySQL found two production bugs SQLite hid**, both fixed: social account credentials stored as an
  encrypted string in a JSON column, and low-stock checks for variations querying a column variations
  don't have.
- **Databases:** development is `pos-saas` (MariaDB 11.4 on the production server, no business data,
  treated as live); tests and the live-like dataset use local MySQL 8 databases ending in `_testing`
  (`mysql_testing`, `rehearsal_testing`, `dataset_testing` connections, `DB_TEST_*` keys).
- **"Work as though live" rule:** every database except a `_testing` one is treated as live; no
  destructive commands; snapshot before any migration or import (`db:snapshot`); schema changes carry
  data across. Enforced by the safety hook, a `_testing`-only guard in `tests/TestCase.php`, and
  `ConnectionGuard` in `dataset:build` and `db:restore-snapshot`. Recorded in CLAUDE.md.
- **Six flat API endpoints are already broken on `main`** (recorded in the contract fixtures).

### Existing deployment (unconfirmed)

The review found signs that a live system may hold real data. **This contradicts Assumption 2 and must be
answered before Phase 1** (Open question 1):
- `CLAUDE.md` says the `.env` database (`ecohub_pos`) is on a **remote shared server** and should be
  treated as **live production data**.
- The code names a production URL (`https://pos.startappz.co.ke/orders/`) and a client ("Fitness Center"
  on the WooCommerce webhooks).
- A migration (`add_shop_id_to_products_table`) back-fills existing rows. Commands exist to repair
  "imported" purchase costs and to back-fill the shop pivot and the credit ledger.

## 2. Single-Tenant Hotspots

**Schema**
- [x] A tenant table (`businesses`) and **memberships** (`business_user`), so one login can belong to
      several businesses, with a default business per user.
      **Done (stage 1a).**
- [x] `business_id` on business-wide lists, `shops`, `products`, and every table whose keys must be
      unique per tenant.
      **Done (stage 1a)**, also on alerts, campaigns, saved reports and credit accounts.
- [x] Per-tenant unique keys (shop code, SKU, barcode, slugs, supplier, customer and category codes,
      sale source name).
      **Done (stage 1a):** unique per business in the schema and `UniqueForTenant`.
- [x] Invoice numbers unique **per business** (`(business_id, invoice_number)`), not system-wide.
      **Done (stage 1a)** in the schema; numbering moves to per-register series in 1b.
- [ ] **One stock source, per shop and per sellable item, variants included** (4.2);
      `products.stock_quantity` and `product_variations.stock_quantity` stop being stock figures.
      **Schema done (stage 1a):** `shop_variation` keyed on `(shop_id, variation_id)`, a default variation per product. The legacy columns stay until 1b moves the code (decided 14 October 2026).
- [ ] All four sale paths and every stock writer and reader moved to that source; a `StockMovement` per
      sale.
- [ ] The unused FIFO costing tables dropped or adopted (Open question 5); profit uses the per-shop cost.
- [x] Per-tenant unique keys that allow a soft-deleted code to be reused (19 soft-deleting models).
      **Done (stage 1a):** a generated `alive` column in each key.
- [ ] Registers (devices) separate from cash sessions.
      **Schema done (stage 1a):** `registers`, `cash_registers.register_id`; model and code in 1b.
- [ ] Sync support: `client_uuid`, `updated_at` indexes and a deletions log for every synced table;
      last-sync time per register.
      **Partly done (stage 1a):** `client_uuid` keys and `sync_deletions`; `updated_at` indexes and the writes come with sync (Phase 6).

**Queries**
- [x] Global scopes on every model with `shop_id` (about 42) and every business-wide model (7 lists,
      products, shops).
      **Done (stage 1a):** `BusinessScope`, `ShopAccessScope`; a reflection test enforces it.
- [x] Default-deny shop access (no assignment means no access).
      **Done (stage 1a).**
- [x] Viewer-aware validation instead of `exists:table,id`.
      **Done (stage 1a):** every `exists:` rule, through `TenantPresenceVerifier`.
- [x] Route model binding by uuid on unscoped models.
      **Done (stage 1a):** a test checks every route binds by uuid.
- [ ] Raw SQL in 34 files (section 1) and "empty list means all" filters (`ReportService`).
- [ ] CSV and PDF exports.
- [ ] `AuditService` reads (audit rows gain `business_id`); the SQLite audit database included in export
      and closure erasure.
- [ ] Signed invoice and statement links made temporary (`temporarySignedRoute`).

**Auth and roles**
- [ ] Platform operator (`super-admin`, outside every business) versus business owner (`admin`).
- [ ] Roles per business: default role templates plus custom roles (Spatie teams on `business_id`).
      **Partly done (stage 1a):** Spatie teams on `business_id`; the seeded roles are templates shared by every business. Copies per business and custom roles: Phase 3.
- [ ] Business picker at sign-in, a switcher, and a default business.
- [ ] New API tokens bound to one business; legacy API tokens act on the default business.
      **Half done (stage 1a):** legacy tokens act on the default business; business-bound tokens come with `/api/v1` (Phase 6).
- [ ] Signup creates a business and its owner, after the email is verified (no trial before that).
- [ ] Account status enforced on web and API.
- [ ] Hard self-delete replaced by deactivation and owner-controlled deletion.

**Background work**
- [ ] Jobs carry the business explicitly (scopes don't apply without a signed-in user).
- [ ] Scheduler sweeps and commands run per business and skip suspended or read-only businesses.
- [ ] E-commerce stock push triggered by per-shop stock changes (`ProductObserver` only watches
      `Product::updated` today, so it would stop firing).
- [ ] Notifications carry the business they belong to; their links switch to it.

**Cache, files, config**
- [ ] Every cache key includes the business (`sms_settings` and `sms_query_parameters` are global today).
- [ ] Private disk with `{business_uuid}/…` paths, served through authorized routes.
- [ ] Business and shop settings replace `config('app.name')`, currency, timezone, country code, invoice
      prefix, the hard-coded till, the WooCommerce webhook name and the template URL.

**Integrations and API**
- [ ] Per-tenant SMS (fixed) and WhatsApp Cloud credentials, encrypted.
- [ ] Per-shop M-Pesa Till or Paybill, used for STK Push and payment webhooks through the private M-Pesa
      package. The number alone isn't enough: Daraja needs that shortcode's own credentials and go-live,
      unless the package is an aggregator (Open question 2). Shortcodes unique across the platform.
- [ ] AI usage metered against a prepaid balance per business.
- [ ] The current flat `/api` keeps working unchanged on the new schema; a new `/api/v1` covers every
      feature the Flutter app needs.
- [ ] Tests independent of `.env`, plus a two-business fixture. **Half done (Phase 0):** tests no longer
      depend on `.env` (WhatsApp, `APP_URL`; SMS still reads `env()` directly, fixed in Phase 6); the
      two-business fixture comes with Phase 2.
      **Two-business fixture done (stage 1a):** `tests/Support/TwoBusinesses.php`.
- [ ] A CI job on the target database (MySQL or MariaDB) for raw SQL, locks and the isolation suite.
- [ ] Legacy flat-API clients with no register or shop context get a defined default (4.8).

## 3. Final Feature Gap Analysis

### Corrections to the research file

| Research said | Codebase shows |
|---|---|
| Cash registers per user per shop; no devices | Correct: `cash_registers` is a cash session. Session-based cash control is cheaper to build than implied. |
| Multi-store inventory is a strength (implied) | **Wrong.** Stock is held on the product and the variation, not per shop. The per-shop pivot is written only by the e-commerce sync, and it can't hold variant stock (no `variation_id`). **MVP fix:** one per-shop stock source that covers variants (4.2), with all four sale paths and every stock writer and reader moved to it. |
| Browser-printable receipt | Correct, and it shows the platform name: a branding leak. |
| Integrations are a strength | Mostly. **SMS is broken** and **WhatsApp Cloud uses one platform number.** Both become per-tenant accounts (MVP). |
| "Breeze self-delete only" | It's a **hard delete** that fails or cascades on business records. |
| eTIMS is an MVP blocker | **Moved to post-launch** (timeline still early); a `FiscalProvider` seam is built in Phase 6. |
| Offline: server idempotency for MVP, app later | **Expanded:** offline trading in each of the user's businesses, synced when back online, is MVP (Phase 6). The owner is warned when a register hasn't synced for 24 hours. |
| Missing from the research | Shared lists with no owner, fail-open report filters, global unique codes, environment-dependent tests, **switching between businesses for one login**, **prepaid AI metering**, unused FIFO costing tables, signed links that never expire, an existing Flutter app on the flat API. |

### MVP development, by phase

| Gap | Phase |
|---|---|
| Answer Open question 1 (live data); fresh local database, `.env`-independent tests, development mail catcher, flat API contract fixtures, CI running the suite on SQLite **and** the target database | 0 |
| **1a:** SaaS schema rebuild, the two-business test fixture, tenant layer, default-deny, per-tenant keys, legacy token resolution, empty-database migration guard, platform-essential seeders | 1a |
| **1b:** per-shop stock source covering variants, all four sale paths, stock writers and readers, costing decision, registers and register sessions, per-register invoice series, legacy default register | 1b |
| **1c:** raw SQL in 34 files, exports, audit, jobs, scheduler and commands, e-commerce stock push, private tenant-prefixed file storage, per-business cache keys, temporary signed links | 1c |
| Tenant isolation test suite, enforced in CI | 2 |
| Platform and owner roles, default and custom roles per business, memberships with picker, switcher and default business, signup, invites, account status, data export and closure, onboarding, CSV import, business and shop settings (including the shop's Till or Paybill number), tenant branding and currency on receipts and PDFs, per-tenant SMS and WhatsApp credentials | 3 |
| Plans per shop (KES 1,500 / 3,000 / 5,000), trial, limits and feature gates, subscription and AI top-up collection through the private M-Pesa package (manual until go-live), read-only plus export when lapsed, prepaid AI credit (top-ups from KES 500) | 4 |
| Platform console, including manual payment recording before go-live; `platform:create-operator` command | 5 |
| **`/api/v1` for Flutter:** business-bound tokens, register pairing, offline sync (pull and push) with a 24-hour sync warning and an offline trading lease, idempotent sales and payments, M-Pesa STK at the till with payment webhooks, structured receipts for ESC/POS, SMS fix, `FiscalProvider` seam, rate limits per business | 6 (after Phase 2; alongside 4–5) |

### Deferred (after MVP)

- KRA eTIMS, through the `FiscalProvider` seam. **Risk:** VAT-registered businesses in Kenya generally
  have to issue eTIMS invoices, so until it ships they may not be able to use the product for their sales
  (Open question 6).
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
| **A. Shared database, `business_id`, global scopes** | One schema and one migration run; suits many small tenants. The risk is a missed scope, especially in raw SQL (34 files, 145 `*Raw` clauses). Platform reporting is easy. |
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

**Proposed (review): how the per-shop stock source covers variants.** Today `shop_product` can't hold
variant stock. Recommended: **every product has at least one variation**, with a default variation for a
simple product, and per-shop stock, reorder level and price and cost overrides are keyed on
`(shop_id, variation_id)`. This puts every sellable item in one table and avoids a nullable `variation_id`
in a unique key, which MySQL doesn't enforce. The alternative is a `shop_product` row keyed on
`(shop_id, product_id, variation_id)` with a generated column for the unique key. Either way, SKU and
barcode on variations become unique per business.

### 4.3 Tenant resolution

| Option | Notes |
|---|---|
| **A. The signed-in user's current business** | Stored in the session on the web and bound to the token on `/api/v1`; re-checked against membership on every request. No route changes. |
| B. Subdomain per tenant | Branded login pages; wildcard DNS and TLS, cookie domains, a tenant host in the app. |
| C. Path prefix (`/t/{slug}/…`) | Changes all 494 routes for no benefit. |
| D. Device registration | Complements A: a paired register implies business, shop and register. |

**Recommendation: A plus D.**
- Webhooks keep resolving by shop uuid, WhatsApp by session key, and M-Pesa payment webhooks by the shop's
  Till or Paybill number and the request id. **Proposed (review):** a shortcode can belong to only one
  shop on the platform, and it's accepted only after the shop proves it controls it (its Daraja
  credentials work, or the aggregator confirms it).
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
- **Proposed (review): offline trading lease.** The device's clock can be changed, so its recorded time
  can't decide whether a sale counts. Each sync returns a server-signed **"trade until"** time (the
  subscription's paid-up date, capped at a few days ahead). The app stops taking sales after it, and the
  server accepts a pushed sale only if it falls within a lease it issued.
- **Proposed (review): reinstalls.** The app's invoice counter is lost when it's reinstalled. Pairing a
  register returns the last invoice number the server has seen for it, and re-pairing a device after a
  reinstall issues a **new register code**, so numbers can't repeat.
- **Soft deletes:** a soft-deleted record goes into the deletions log like a hard delete.
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
  - **Proposed (review):** each call **reserves** its estimated cost atomically before it runs and
    settles the actual cost afterwards, so concurrent calls can't take the balance below zero.
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

> **Re-check required (review).** The code suggests a live system may exist (section 1, "Existing
> deployment"). If `pos.startappz.co.ke` or `ecohub_pos` holds data anyone relies on, option A still
> works for the code, but it needs a **one-off import** of that data into the new schema as the first
> business (or a decision to retire that system). Phase 1 doesn't start until Open question 1 is answered.

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

**As built (stage 1a, 14 October 2026):** the rewrite stands, and because development works as though live
(Phase 0), data crosses by a **one-off import** rather than being given up:
- the 104 migrations became one `create_*` migration per table, first proved to build `main`'s exact schema,
  then given the SaaS schema; every legacy column is kept, so the import can carry every value;
- the empty-database guard is the first migration (`DB_BASELINE_FORCE` overrides);
- `saas:import-legacy` copies a pre-SaaS database into a freshly migrated baseline as one business, reading the
  source only, and verifies counts, money totals, credit balances, stock and references. Rehearsed on `main`'s
  live-like dataset and on a copy of the old development database; CI repeats it on every push;
- **departure:** `products.stock_quantity`, `product_variations.stock_quantity`, `shop_product` and the
  `cash_registers` name are **kept until Phase 1b**, which moves the code off them and drops them with a migration
  that carries the data across (decided 14 October 2026).

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
  - responses that include `products.stock_quantity` (and variation stock) compute it from per-shop
    stock. **Proposed (review):** use the shop in the request when it names one, and otherwise the total
    across the shops the user can access in the default business;
  - **Proposed (review):** the existing app knows nothing about registers, so each shop gets a
    **default register**. Legacy cash-register and sale calls land on it;
  - `mobile:contract-diff` and contract tests guard it.
- The CLAUDE.md rule against versioning the existing API stays.
- With no Flutter timeline yet, `/api/v1`, its OpenAPI document and contract tests are the hand-off for
  whenever the app is built.

**Decision: CONFIRMED (B: flat `/api` unchanged, new `/api/v1`)**

### 4.9 Edge-case rules

Decided while the plan was reviewed.

| Case | Rule |
|---|---|
| **Invoice numbers on an offline till** | Each register has its own invoice series, so a till can number sales while offline without colliding with another till: `INV-{shop code}-{register code}-{year}-{sequence}`. The app assigns the next number in its series; the server checks on sync that each number is unique and the series has no gaps or repeats, and flags any that do. **Corrected (review):** numbers are unique **per business** (`(business_id, invoice_number)`). The shop code is the user's own code, unique within the business, and there's no second, generated code. A reinstalled device gets a new register code (4.5). |
| **Sales made before a lapse, synced after it** | Accepted. A pushed sale is accepted when its recorded time is before the moment the subscription lapsed; sales recorded after it are refused with `subscription_inactive` and stay on the device. A recorded time that's implausible (in the future, or before the register's previous sync) is accepted but flagged for review. **Corrected (review):** the device clock alone can't decide this. A sale also has to fall within an offline trading lease the server issued (4.5). |
| **M-Pesa payments while the till is offline** | The STK Push is sent through the server and the private M-Pesa package. The payment confirmation reaches the server by webhook even if the till has lost its connection by then. The till marks the payment **pending** and receives the result on its next sync. **A till with no connection at all** can't send an STK Push: the customer pays the shop's Till or Paybill directly, the cashier enters the M-Pesa code, and the payment stays pending until the till syncs. The server then matches the code against the payment notifications received for the shop's number; a code with no match stays pending and is flagged for review. **Corrected (review):** both STK Push and these payment notifications (C2B confirmations) need the shortcode's own Daraja credentials and callback registration, or an aggregator that holds them (Open question 2). Buy Goods tills carry no account reference, so matching is by M-Pesa code and amount. |
| **Duplicate customers created offline** | Not merged automatically. A pushed customer whose phone number (normalized) matches an existing customer of the same business is saved and **flagged for review**; the owner or a manager merges or keeps both from a review screen. **Note (review):** customers belong to a **shop** today (`customers.shop_id`, with credit accounts per shop). Whether they become business-wide decides whether this check runs per shop or per business (Open question 3). |
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
`bcmath`, `redis`, `pcntl`, `sodium`.

**Corrected (review): Redis isn't needed for this plan.** The app runs cache, queue and session on the
`database` driver, and nothing uses Redis. Invoice series keep the row lock (`lockForUpdate`) that
`InvoiceNumberService` already uses; offline numbers are assigned on the device anyway. Moving to Redis is
a hosting decision (section 9).

| Need | Package or service | Phase |
|---|---|---|
| M-Pesa STK Push and payment webhooks | The **private M-Pesa package** (Composer VCS repository with token access, like `wa-gateway-laravel`). **Check first:** whether it pushes to a merchant's shortcode with that shortcode's own credentials or as an aggregator (Open question 2) | 4, 6 |
| OpenAPI docs for `/api/v1` | **`dedoc/scramble`** (confirmed) | 6 |
| Tenant SMS | **The current SMS service** (`app/Http/Startappz/Services/SMSservice`), fixed and made per tenant; no new package | 6 |
| Mail | A **development** mail catcher (log driver or Mailpit) | 0 |
| Optional | `larastan/larastan` (catch raw queries that skip scoping), `league/csv` (CSV import) | 1, 3 |

No tenancy package is needed; scoping is built in-house (4.1).

### Phase 0: Development baseline

- **Goal:** a known, reproducible starting point for development.
- **Work:**
  - **Answer Open question 1** (is there live data?). If there is, plan the one-off import (4.7).
  - `composer install` to match the lock file.
  - ~~A new, empty local database, pointed to from `.env`.~~ **Changed 13 October 2026:** development
    uses the **UAT** database (treated as live); tests and the synthetic dataset use a local MySQL 8
    database whose name ends in `_testing` (`mysql_testing` connection, `DB_TEST_*` keys). See the
    stage 0 file.
  - Make the 8 WhatsApp tests independent of `.env`; fix or isolate the HTTPS test.
  - Point local mail at a development catcher (log driver or Mailpit), so invites and codes never reach
    real inboxes.
  - Record the baseline.
  - Record the flat API's current contract (routes and response shapes) as fixtures for the
    compatibility tests.
  - A CI pipeline that runs Pint, the full suite and the flat API contract tests on every push. The repo
    has no CI today (`.github/` holds only agent instructions).
  - A second CI job that runs the suite on the target database (MySQL or MariaDB, Open question 4), so
    raw SQL, row locks and unique keys are tested on the engine that runs them.
- **Schema:** none.
- **Acceptance:** the suite is green on a fresh database and in both CI jobs; the flat API contract
  fixtures are recorded; Open question 1 is answered.

### Phase 1: SaaS schema and automatic tenant scoping

**Corrected (review): split into 1a, 1b and 1c.** Phase 1 as first written covered:
- rewriting about 79 tables;
- scopes on about 50 models;
- raw SQL in 34 files and four sale paths;
- the stock redesign, registers, file storage, jobs, commands and the legacy API layer.

All of it would have landed in one branch while about 1,074 tests stayed green. Each sub-phase now gets its
own branch and ends green. The two-business fixture is built **first**, in 1a, so isolation is tested
while scoping is written rather than afterwards. Phase 1 doesn't start until Open questions 1, 3 and 5 are
answered.

- **Goal (all of Phase 1):** a clean multi-tenant POS schema in which every query is automatically limited
  to one business, with no shop access unless granted, while the flat API keeps working.

#### Phase 1a: Schema, tenant layer, default-deny

- **Files and modules:**
  - **Migrations:** rewritten (decision 4.7), with the empty-database guard. The schema for 1b and 1c
    (stock, registers, sync columns) is written here too, because the migrations are rewritten once.
  - **Seeders:** platform essentials only (permissions, role templates, plans).
  - **New:** a `Business` model, `BusinessMembership`, `TenantContext` (current business from the session
    or token; set explicitly for jobs and commands), the `BelongsToBusiness` and `BelongsToShop` traits,
    `BusinessScope` and `ShopAccessScope`, a viewer-aware `exists` rule, a per-tenant unique rule that
    ignores soft-deleted rows.
  - **The two-business test fixture** (with a user who belongs to both), and the existing tests adapted
    to it.
  - **Models:** every model with `shop_id` (about 42) and the 7 list models; `User` (memberships, current
    and default business, default-deny shops); `Product` and `Shop`.
  - **Roles:** the 12 direct `hasRole('super-admin')` checks reviewed alongside the switch to teams.
  - **Flat API compatibility:** default-business resolution for legacy tokens.
- **Acceptance:**
  - A reflection test: every model with `shop_id` or `business_id` has its scope.
  - Scope unit tests: owner, staff, no assignment, switching business.
  - Per-tenant unique keys: both businesses can use the same codes, and a soft-deleted code can be reused.
  - The migration guard refuses a non-empty database unless forced.
  - The flat API contract tests pass; the full suite is green on SQLite and the target database.

**Phase 1a as built (14 October 2026; details in [PROGRESS.md](PROGRESS.md)):**
- **Tenant layer:** `TenantContext` (set by `ResolveTenant`; jobs and commands set it explicitly), `BelongsToBusiness`
  and `BelongsToShop` with `BusinessScope` and `ShopAccessScope` on every tenant model (a reflection test enforces
  it). With no business set, local and testing throw; production gives a signed-in user no rows, and code with no
  user runs unscoped with a warning until 1c.
- **Default-deny:** the owner sees every shop; others only their assigned shops. A record outside the user's shops
  is now **404, not 403**.
- **Validation:** every `exists:` rule is viewer-aware through a tenant presence verifier (no rule rewritten);
  per-business uniqueness through `UniqueForTenant`.
- **Roles:** Spatie teams on `business_id`; the seeded roles are templates shared by every business. The platform
  operator / business owner split stays in **Phase 3**: until then the owner is `super-admin` in the current
  business, through one method (`User::isBusinessOwner`).
- **Customers** are business-wide; **one credit account per customer**, business-scoped (it serves every shop).
- **Products** get a hidden default variation and per-shop rows in `shop_variation`.
- **Seeders:** permissions and role templates; plans are seeded in Phase 4 with their table.
- **Import:** see 4.7.

#### Phase 1b: Stock, sales, registers, invoice numbers

- **Files and modules:**
  - **Stock source:** per shop and per sellable item, variants included (4.2). Moved onto it:
    - the **four sale paths**: both `SaleController`s, `ConvertEcommerceOrderToSale` and
      `ConvertAbandonedCartToSale`;
    - every writer: `CompleteStockIntakeAction`, `StockAdjustmentService`, `RecordStockMovement`,
      purchase returns, sale returns that restock, and product create and update;
    - every reader: `CheckLowStockLevels`, `CreateInventorySnapshot`, `InventoryService`,
      `ProductService`, `DashboardController`.
  - A `StockMovement` for every sale, written by one shared stock service rather than inline in each path.
  - **Costing:** the unused FIFO tables are dropped or adopted (Open question 5); profit and
    `RecomputeSaleProfit` use the per-shop cost.
  - **Registers:** `Register`; `CashRegister` becomes register sessions tied to a register; a **default
    register per shop** for the legacy API (4.8).
  - **Invoice numbers:** `InvoiceNumberService` moves to per-register series, keeping its existing row
    lock; numbers unique per business.
  - **Flat API compatibility:** product and variation stock computed from per-shop stock (4.8).
- **Acceptance:**
  - Each of the four sale paths decrements the shop's stock for the right product or variation and writes
    a movement.
  - Low-stock alerts, snapshots and the dashboard read per-shop stock.
  - A cash session opens and closes on a register; two registers in one shop number invoices in separate
    series without collisions, tested concurrently on the target database.
  - A legacy cash-register and sale call lands on the shop's default register.
  - The flat API contract tests pass; the full suite is green on both databases.

**FIFO costing design (agreed 15 October 2026; analysis in [fifo-analysis.md](fifo-analysis.md)):**
- `cost_layers` and `cost_allocations` are adopted, per shop and per variation, with `business_id`, the
  layer's source and the allocation's consumer (sale item, adjustment item, purchase-return item).
  `sale_item_stock_allocations` stays, unused.
- One shared stock service changes stock: it locks the `shop_variation` row, changes it by a delta, writes
  the `StockMovement`, and creates or consumes layers, oldest first.
- Layers come from completed intakes (at the purchase-order line's cost, else the variation's), upward
  adjustments, restockable sale returns (at the cost the item was sold at) and opening stock. Sales,
  downward adjustments and purchase returns consume them.
- Sales below zero are accepted; the shortfall is costed provisionally and re-costed when stock arrives,
  updating those sales' cost and profit.
- `sale_items.unit_cost` comes from the allocations; profit and `RecomputeSaleProfit` use it.
- The legacy stock columns are dropped in 1b, after the data move.

#### Phase 1c: Raw SQL, background work, files

- **Files and modules:**
  - **Raw SQL in all 34 files** listed in section 1, including the models, controllers, services,
    observer, listener and form request outside the report code.
  - **Also:** CSV and PDF exports; `AuditService` (audit rows gain `business_id`); the 10 jobs; the
    scheduler; the 9 commands (`--business`); cache keys.
  - **E-commerce stock push:** triggered by per-shop stock changes, since `ProductObserver` only watches
    `Product::updated`; each shop's store gets that shop's stock.
  - **Signed links:** invoice, statement and order-invoice links become temporary
    (`temporarySignedRoute`).
  - **Files:** a private disk with `{business_uuid}/…` paths, served through authorized routes, with a
    per-tenant quota; product images, profile photos and Baileys media moved onto it.
- **Acceptance:**
  - Raw SQL paths and reports tested with two businesses, on the target database.
  - Jobs and commands run for one named business only.
  - A stock change in shop A updates shop A's online store and no other.
  - An expired signed link is refused.
  - Uploaded files land under the business prefix and aren't publicly reachable.
  - The flat API contract tests pass; the full suite is green on both databases.

#### Phase 1 schema (written in 1a)

- `businesses` (uuid, name, settings, status).
- `business_user` (status, invited by, joined at); `users.default_business_id`.
- Spatie `teams => true` with `team_foreign_key = business_id` (roles per business).
- `business_id` on shops, products and the 7 lists.
- `registers` (shop, name, code, device id, paired at, last synced at, last invoice number);
  `cash_registers` becomes register sessions with a register reference.
- `client_uuid` plus a unique `(business_id, client_uuid)` on sales, sale payments, customers and register
  sessions.
- `sync_deletions` (business, entity, id, deleted at), written for soft and hard deletes.
- Per-tenant unique keys, including SKU and barcode on variations; `sales` unique on
  `(business_id, invoice_number)`.
- **Invoice series per register** (`registers.code`, a per-register sequence) so tills can number sales
  offline (4.9).
- `businesses.currency` and `businesses.timezone`, plus an optional shop timezone (the `shops` table has
  none today); amounts never assume KES (4.9).
- Review flags: `customers.needs_review` (possible duplicate) and a flag for sales with an implausible
  recorded time.
- **Per-shop stock keyed by variation** (4.2); `products.stock_quantity` and
  `product_variations.stock_quantity` removed as stored figures.
- Foreign keys from cash sessions and other business records to `users` no longer cascade on delete
  (9 tables cascade today).

#### Phase 1 risks (leakage)

- A missed raw query (34 files, not 11).
- Code that runs with no signed-in user (jobs, webhooks, console), where scopes don't apply.
- `withoutGlobalScopes()` misuse.
- Route binding or validation accepting another business's ids.
- The memoised shop list not keyed by business.
- Signed links that never expire.
- Spatie's cache holding team-scoped role queries.
- Files reachable through the old public paths.
- The flat API changing shape by accident.
- A stock writer or reader left on the old columns, so two figures drift apart again.

### Phase 2: Tenant isolation test suite

- **Goal:** proof that business A never sees or changes business B's data, through any channel.
- **Files:** `tests/Feature/TenantIsolation/*`, built on the two-business fixture from Phase 1a (a user
  who belongs to **both**). The suite runs in the target-database CI job as well as on SQLite.
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
    - `RegisteredUserController` (creates a business and its owner; honeypot). The trial starts only
      after the email is verified, so throwaway signups can't open free trials.
    - The business picker, switcher and default-business setting (web).
    - `/api/v1` sign-in, list businesses, issue a token per business, switch, sign out.
  - **People:** invites; `UserPolicy` (owner protection; only owners delete); account status middleware;
    deactivation instead of self-delete; data export and business closure (final export emailed to the
    owner, deletion when the closure grace period ends; also triggered automatically after 30 business
    days of lapse, 4.9). Deletion uses `forceDelete` on the 19 soft-deleting models and also covers the
    business's rows in the SQLite audit database and its files on the private disk.
  - **Ownership handover** to an existing co-owner, confirmed by a code emailed to the owner (4.9).
  - **One active business per session:** pages and forms carry the business they were loaded for, and a
    submission for a business that's no longer active is refused with a "reload the page" message (4.9).
  - **Shop deletion** as the only way a shop leaves a business (no transfer).
  - **Onboarding:** the onboarding checklist (business, first shop, first register, products, first sale)
    and CSV product import.
  - **Settings:** business and shop name, logo, receipt header and footer, currency, timezone, invoice
    prefix, and the **shop's M-Pesa Till or Paybill number**. These replace `config('app.name')` and the
    hard-coded till in `NotifyCustomerOfEcommerceOrder`.
  - **Encrypted per-tenant credentials** for SMS and WhatsApp Cloud, and per-shop Daraja credentials if
    Open question 2 settles on that model.
  - **Notifications** carry the business they belong to, and their links switch to it.
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
  - Signup creates a business and its owner; the trial starts only once the email is verified.
  - Closing a business leaves none of its rows in the main or audit database, and none of its files.
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
  - **`AiManager`** (it exists today and only picks a driver per shop): reserves the estimated cost
    atomically before a call, settles the actual cost afterwards, records usage, and refuses calls at zero
    balance; the owner is notified, and blocked jobs wait for credit and are re-dispatched after a top-up.
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
  - Concurrent AI calls can't take the balance below zero.

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
  - **Register pairing:** a device is paired to a shop's register and receives a business-bound token
    and the last invoice number the server has for it. Re-pairing after a reinstall issues a new register
    code (4.5).
  - **Offline trading lease:** every sync returns a server-signed "trade until" time; the app stops
    taking sales after it, and the server accepts only sales inside a lease it issued (4.5).
  - **Sync:** `GET /api/v1/sync/pull?since=` and `POST /api/v1/sync/push` (batched, idempotent by
    `client_uuid`, applied in order, results per item).
  - Last-sync tracking; the **24-hour owner warning** and the `sync_overdue` flag.
  - **M-Pesa STK Push at the till** through the private package, using the shop's own Till or Paybill.
    The payment webhook marks the right sale paid, matched by shop number and request id. A till that has
    gone offline shows the payment as pending and receives the result on its next sync (4.9). **Depends on
    Open question 2:** STK Push and C2B confirmations need that shortcode's own credentials and callback
    registration (with Safaricom go-live per shop), or an aggregator that holds them.
  - **Offline rules (4.9):** per-register invoice series checked on sync; sales recorded before a lapse
    and inside an issued lease accepted after it; possible duplicate customers flagged, with a review
    screen to merge or keep them.
  - Structured receipt JSON for ESC/POS printing.
  - **SMS fix, keeping the current SMS service:** replace the missing `App\Models\Setting` with the
    tenant's SMS settings (from `business_integrations`), read configuration through `config()` instead of
    `env()`, and key any cache by business.
  - **Offline M-Pesa entry:** a cashier on a till with no connection enters the M-Pesa code; on sync it's
    matched to the payment notification for the shop's number by code and amount, or flagged for review
    (4.9). Buy Goods tills carry no account reference.
  - The `FiscalProvider` seam (a null provider until eTIMS).
  - Rate limits per business on both APIs.
  - `mobile:contract-diff` extended to cover `/api/v1`.
- **Schema:** `payment_requests` (STK requests and webhooks per sale); the Phase 1 sync columns used.
- **Risks:**
  - Duplicate sales on retried pushes.
  - Pushes landing in the wrong business (checked against the token's business).
  - Pulls leaking deleted or foreign rows.
  - Webhooks matched to the wrong sale.
  - Clock skew on offline timestamps, or a device clock set back on purpose to trade after a lapse
    (the lease covers this).
  - A reinstalled app reusing invoice numbers (re-pairing covers this).
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
    device. A sale outside any issued lease is refused even if its recorded time is before the lapse.
  - A device re-paired after a reinstall gets a new register code, and its numbers don't repeat.
  - A customer pushed with an existing phone number is saved and flagged, not merged.
  - An M-Pesa code entered on an offline till is matched to the shop's payment notification on sync; an
    unmatched or reused code stays pending and is flagged.
  - The OpenAPI document matches the routes (contract test).
  - SMS sends through the tenant's provider.
  - The flat API contract tests still pass.

## 6. Working Through the Phases

- **One branch per phase** off `main`, merged only when the phase's acceptance criteria pass in CI.
- **One Claude Code session per phase.** Start large phases (1a, 1b, 1c, 3, 4, 6) in plan mode with:
  "Read docs/saas-plan.md and plan Phase N only." Run `/clear` between phases; this file carries the continuity.
- **Commit after each completed step** inside a phase, with tests for that step in the same commit.
- **Keep this file current.** When implementation diverges from the plan, update the relevant section
  before moving on, and mark the hotspot checklist (section 2) as items are done.

## 7. Assumptions

1. Other branches are out of scope; this plan starts from `main` alone.
2. **Every environment starts with an empty database**, with no seeded data. The current company becomes
   the first business through signup and CSV import. **Unconfirmed (review):** the code suggests a live
   system with data may exist (section 1, "Existing deployment"; Open question 1).
3. The target market is Kenya first (KES, VAT on sales, M-Pesa); eTIMS is post-launch.
4. Users have a unique email across the platform, may belong to several businesses with a role in each,
   and have a default business.
5. Products belong to the business; per-shop stock and prices, keyed down to the variation (4.2), are the
   only stock source.
6. Tenants supply their own SMS and WhatsApp Cloud credentials. **Corrected (review):** for STK Push and
   payment notifications to reach a shop's own Till or Paybill, the number alone isn't enough. Either the
   shop supplies Daraja credentials for it (with Safaricom go-live per shop), or the private M-Pesa
   package works as an aggregator that already holds them (Open question 2).
7. The private M-Pesa package can be installed like `wa-gateway-laravel` (a Composer VCS repository with
   token access) and supports this Laravel version. Not checked; it isn't in this repository yet. Also
   unchecked: which of the two models in Assumption 6 it supports.
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
15. The target database is **MySQL 8 or later** (the `.env` uses MariaDB; Open question 4). Redis isn't
    required by this plan (section 5). Tests run on SQLite in memory **and** on the target database in
    CI.
16. Tenant SMS keeps using the current SMS service's provider integration, with credentials per tenant.
17. Mail uses a development catcher during development.
18. Customers stay per shop, as today, unless Open question 3 decides otherwise.

## 8. Open Questions

Raised by the review on 12 October 2026. Questions 1–5 block the phase named.

1. **Is there live data?** Does `pos.startappz.co.ke` or the `ecohub_pos` database hold data that anyone
   (the "Fitness Center" client, for example) still relies on? If so, plan a one-off import into the new
   schema, or retire that system. *Blocks Phase 1.*
2. **How does the private M-Pesa package reach a shop's own Till or Paybill?** Per-shop Daraja
   credentials (with Safaricom go-live for each shop), or an aggregator arrangement? This decides what a
   shop has to supply at onboarding. *Blocks the M-Pesa parts of Phases 3, 4 and 6.*
3. **Do customers belong to a shop or to the business?** Today they're per shop, with credit accounts per
   shop. This decides the duplicate-customer check, the sync pull and the customer unique keys.
   *Blocks Phase 1a.*
4. **MySQL or MariaDB in production?** CI runs on the same engine. *Blocks the Phase 0 CI job.*
5. **Do the unused FIFO costing tables go, or does costing adopt them?** (`cost_layers`,
   `cost_allocations`, `sale_item_stock_allocations`). *Blocks Phase 1b.*
6. **eTIMS deferral.** Is launching without eTIMS acceptable, given that VAT-registered businesses
   generally need to issue eTIMS invoices? If not, launch only for businesses that aren't VAT-registered,
   or bring the `FiscalProvider` work forward.
7. **Timeline.** The plan has no effort estimates and no launch date. 12 November 2026 is the platform's
   M-Pesa collection go-live, which is a month away. Set a launch date and size the phases against it.

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
## 10. Review Corrections (12 October 2026)

A code review against `main` (`37231c1`) changed the plan as follows.

**Facts corrected (section 1):**
- Stock lives on the product and the variation; the per-shop pivot is effectively unused for stock and
  has no `variation_id`.
- Four code paths make a sale, not two.
- Raw SQL appears in 34 files, not 11.
- Redis isn't used; invoice numbering already uses a row lock.
- `shops` has no timezone column.
- Services, models, actions and routes recounted.
- The `.env` uses MariaDB.
- Signed links never expire.
- An existing Flutter app uses the flat API.
- Signs of a live deployment that contradict the "no data anywhere" assumption.

**Gaps added:**
- Per-shop stock for variants.
- The unused FIFO costing tables.
- Every stock writer and reader, and the e-commerce stock push.
- Soft deletes and per-tenant unique keys.
- The SQLite audit database in export and erasure.
- A CI job on the target database.
- Legacy-API defaults: a default register per shop, and which shop's stock legacy calls return.
- Customers per shop versus per business.
- Email verification before a trial.
- Notifications with business context.
- Atomic AI credit reservation.
- Temporary signed links.

**Rules corrected:**
- Invoice numbers unique per business, with no second generated shop code.
- An offline trading lease instead of trusting the device clock after a lapse.
- Re-pairing issues a new register code after a reinstall.
- Per-shop M-Pesa needs the shortcode's own credentials or an aggregator.
- Shortcodes unique across the platform and verified.

**Structure:**
- Phase 1 split into 1a, 1b and 1c.
- The two-business fixture moved into 1a.
- Phase 0 now answers the live-data question and adds the target-database CI job.
- Open questions 1–5 now block named phases.

The version before this review is saved next to this file as `saas-plan.original.md`.
