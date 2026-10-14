# Phase 1a: Schema, Tenant Layer, Default-Deny

> **Source:** saas-plan.md section 5 (Phase 1, Phase 1a, Phase 1 schema, Phase 1 risks), sections 4.1–4.4,
> 4.7, 4.8, section 2 (Schema, Queries, Auth and roles)
> **Branch:** `saas/phase-1a-schema-tenant-layer` · **Depends on:** Phase 0

---

## Goal

Phase 1 as a whole: a clean multi-tenant POS schema in which every query is automatically limited to one
business, with no shop access unless granted, while the flat API keeps working.

Phase 1a: the whole new schema, the tenant layer, default-deny shop access, per-tenant keys, legacy token
resolution, and **the two-business test fixture built first**, so isolation is tested while scoping is
written rather than afterwards.

## Before you start

Phase 1 doesn't start until these are answered:

| Open question | Why it blocks 1a |
|---------------|------------------|
| **1. Is there live data?** | **Answered 12 October 2026:** no live data yet, but **development works as though the system is live and data is present.** Every database except the test database is treated as holding live data, and every schema change must carry existing data across. |
| **3. Do customers belong to a shop or to the business?** | **Answered 12 October 2026: the business.** Customers get `business_id`, are scoped by business rather than by shop, and their codes are unique per business. |
| **5. FIFO costing tables: drop or adopt?** | Blocks 1b, but the schema for 1b is written in 1a, so settle it first if possible. |

## Relevant current state

- **About 84 tables:** 71 domain, 5 Spatie, 8 framework, plus `audit_logs` on the audit connection.
  **104 migrations.**
- **Top of the hierarchy is `shops`.** There's no tenant table.
- **42 tables owned by a shop** (`shop_id`).
- **Owned by no one:** suppliers, categories, attributes, expense categories, delivery companies, sale
  sources, pricing rules. Users are global.
- **Products are shared across shops;** `products.shop_id` is nullable.
- **Unique across the whole system, typed by users:** shop code, SKU and barcode (on products **and**
  variations), slugs (products, categories, attributes, expense categories), supplier, customer and
  expense category codes, sale source name.
- **Soft deletes:** 19 models use `SoftDeletes`.
- **Auth:** global Spatie roles (`super-admin`, `manager`, `cashier`, `inventory-clerk`, `accountant`,
  `customer`); 185 permissions across 34 resources; `Gate::before` lets `super-admin` do everything; 12
  more places call `hasRole('super-admin')` directly. Spatie runs with `teams => false`.
- **Shop restriction is opt-in and fails open:** a user with no `shop_user` rows can access every shop;
  checked by hand in 35 files; no global scopes.
- **API:** `POST /api/login` returns a Sanctum token with no business selection.
- **Foreign keys:** 9 tables cascade on delete from `users`.

## Decisions that apply

- **4.1 Tenancy model:** shared database, `business_id`, global scopes. A reflection test fails if a
  model with `shop_id` or `business_id` lacks its scope. *Confirmed.*
- **4.2 Hierarchy:** Business → Shop → Register → Staff; products belong to the business; per-shop stock
  is the only stock source. *Confirmed.*
- **4.3 Tenant resolution:** the signed-in user's current business (session on the web, token on
  `/api/v1`), re-checked against membership on every request; jobs and commands set the business
  explicitly; legacy flat-API tokens resolve to the user's **default business**. *Confirmed.*
- **4.4 Accounts model:** one login with memberships in one or more businesses, a default business, a
  role per membership (Spatie teams on `business_id`); no assignment means no shop access. *Confirmed.*
- **4.7 Database restructure:** rewrite the migrations into a clean SaaS baseline; a guard refuses to
  migrate a non-empty database unless forced; seeders provide only platform essentials. *Confirmed;
  re-check done: there is no live data, so no import is needed.*
- **4.8 API strategy:** the flat `/api` stays unchanged; legacy tokens act on the user's default
  business. *Confirmed.*

## Work

### Migrations and seeders
- [x] **Migrations rewritten** (decision 4.7), with the empty-database guard. The schema for 1b and 1c
      (stock, registers, sync columns) is written here too, because the migrations are rewritten once.
      See "Phase 1 schema" below.
      *Steps 1–2:* 84 consolidated `create_*` migrations, proved identical to `main`'s schema (step 1), then the SaaS schema written into them; the guard is `0001_01_01_000000_guard_empty_database` (`DB_BASELINE_FORCE`). Every legacy column is kept, so the import carries every value (`MigrationBaselineTest`). Still to come with their steps: Spatie team columns, one credit account per customer.
- [ ] **Seeders:** platform essentials only (permissions, role templates, plans).

### Tenant layer
- [x] `Business` model and `BusinessMembership`.
      *Stage 1a step 2.* `app/Models/Business.php`, `BusinessMembership.php`; a new business gets its default sale source (Walk-in).
- [x] `TenantContext`: current business from the session or token; set explicitly for jobs and commands.
      *Stage 1a step 2.* `app/Support/Tenancy/TenantContext.php` (`set`, `run`, `bypass`), set by `ResolveTenant` after authentication; `BelongsToBusiness` refuses a record with no business (`MissingTenantContext`). `TenantContextTest`.
- [x] `BelongsToBusiness` and `BelongsToShop` traits; `BusinessScope` and `ShopAccessScope`.
      *Step 3.* `app/Models/Concerns/BelongsToShop.php`, `app/Models/Scopes/{BusinessScope,ShopAccessScope}.php`. With no business set: strict mode (local, testing) throws; a signed-in user gets no rows; code with no user runs unscoped and is logged until 1c. Shop access is a fresh subquery per query. Unauthenticated routes (webhooks, signed links) bind across businesses and then act for the record's business (`ResolvesTenantFromRouteBinding`); the WhatsApp gateway listener does the same by session key.
- [x] A viewer-aware `exists` rule (replaces `exists:table,id`).
      *Step 6:* rather than a new rule in 123 places, `exists:` itself is viewer-aware: `app/Support/Tenancy/TenantPresenceVerifier.php` (bound as Laravel's validation presence verifier) checks tenant tables through the models' tenant scopes, child tables through their visible parent, and `users` as members of the business. Soft-deleted rows still count, as before. It covers every existing and future `exists:` rule. `ViewerAwareExistsTest` (fails without the verifier).
- [x] A per-tenant unique rule that **ignores soft-deleted rows**.
      *Step 5:* `app/Rules/UniqueForTenant.php` replaces the 24 `unique:` rules on tenant tables (shop code, product slug/SKU/barcode, category name/slug, attribute name/slug, supplier code, sale source name). The credit-account rule changes with one account per customer.
- [x] Route model binding by uuid on unscoped models.
      *Step 7:* the unscoped models bound in routes (`User`, `Role`, `AuditLog`) already bound by uuid; the one model bound by its sequential id, `BaileysChat` (`{chat}`), gains a `uuid` and binds by it (inbox links, redirect and stream URL too). A test in `TenantScopeReflectionTest` fails if any route binds a model by a sequential id.

### Test fixture
- [x] **The two-business test fixture**, with a user who belongs to both.
      *Step 3.* `tests/Support/TwoBusinesses.php`: A and B, each with two shops (the same codes), an owner, a cashier assigned to one shop, a customer, product, supplier and sale; plus a member of both.
- [ ] Existing tests adapted to it.

### Models
- [x] Every model with `shop_id` (about 42) and the 7 list models get their scope.
      *Step 3.* 31 shop-owned models get `BelongsToShop`; alerts, alert rules, campaigns and saved reports (optional shop) also get `business_id` and `BelongsToBusiness`; `Shop` is limited to accessible shops on its own id. Exceptions, with reasons, in the reflection test: `SaleItem` (its `shop_id` is the product's shop; reached through the sale), `BusinessMembership`, `AuditLog` (1c).
- [x] `Customer` uses `BelongsToBusiness` (business-wide), not `BelongsToShop` (Open question 3).
      *Step 3.* Scoped by business only.
- [x] `User`: memberships, current and default business, **default-deny** shops (no assignment means no
      access).
      *Steps 2 and 4.* Memberships and default business (step 2); shop access is default-deny: an owner sees every shop of the business, other members only their assigned shops, no assignment means none (`TenantContext::accessibleShopsQuery`, `User::canAccessShop`). "Owner" is the `super-admin` role until Phase 3 splits platform operator and business owner.
- [x] `Product` and `Shop`.
      *Step 3.* Both scoped by business; `Shop` also by shop access.

### Roles
- [ ] Spatie `teams => true` with `team_foreign_key = business_id`.
- [ ] Review the 12 direct `hasRole('super-admin')` checks alongside the switch to teams.

### Flat API compatibility
- [ ] Default-business resolution for legacy tokens.

## Phase 1 schema (written in 1a)

- [x] `businesses` (uuid, name, settings, status).
      *Stage 1a step 2.* Also `currency` and `timezone`.
- [x] `business_user` (status, invited by, joined at); `users.default_business_id`.
      *Stage 1a step 2.*
- [ ] Spatie `teams => true` with `team_foreign_key = business_id` (roles per business).
- [x] `business_id` on shops, products and the 7 lists.
      *Stage 1a step 2.* Also on `product_variations` (per-business SKU and barcode), `sales`, `sale_payments` and `cash_registers` (for their `client_uuid` keys).
- [x] `business_id` on `customers`; customers become business-wide (Open question 3), with customer codes
      unique per business; `customers.shop_id` kept.
      *Stage 1a step 2.* Unique `(business_id, code)` among rows not soft-deleted.
- [x] Credit accounts: **one per customer** (unique on the customer), replacing one per shop.
      *Step 8:* `credit_accounts` unique on `customer_id`, with `business_id`; the account is business-scoped (it serves every shop) and keeps `shop_id` as the shop it was opened in. `CreditAccountService` finds and opens accounts by customer; `StoreCreditAccountRequest` checks one per customer.
- [ ] **A default variation for every product**; per-shop stock, reorder level and price and cost
      overrides keyed on `(shop_id, variation_id)` (4.2).
- [x] FIFO costing tables (`cost_layers`, `cost_allocations`, `sale_item_stock_allocations`) **kept** in
      the rewritten schema (Open question 5); their final columns follow the Phase 1b analysis, so leave
      room to adjust them there.
      *Stage 1a step 2.* Unchanged.
- [x] `registers` (shop, name, code, device id, paired at, last synced at, last invoice number);
      `cash_registers` becomes register sessions with a register reference.
      *Stage 1a step 2.* `cash_registers.register_id` (nullable until 1b moves the code).
- [x] `client_uuid` plus a unique `(business_id, client_uuid)` on sales, sale payments, customers and
      register sessions.
      *Stage 1a step 2.*
- [x] `sync_deletions` (business, entity, id, deleted at), written for soft and hard deletes.
      *Stage 1a step 2.* Writing it on deletes comes with sync (Phase 6).
- [x] Per-tenant unique keys, including SKU and barcode on variations; `sales` unique on
      `(business_id, invoice_number)`.
      *Stage 1a step 2.* Keys on soft-deleting tables include a generated `alive` column (NULL once deleted), so a deleted code can be reused.
- [x] **Invoice series per register** (`registers.code`, a per-register sequence) so tills can number
      sales offline (4.9).
      *Stage 1a step 2.* `registers.code` and `registers.last_invoice_number`; numbering moves in 1b.
- [x] `businesses.currency` and `businesses.timezone`, plus an optional shop timezone (the `shops` table
      has none today); amounts never assume KES (4.9).
      *Stage 1a step 2.*
- [x] Review flags: `customers.needs_review` (possible duplicate) and a flag for sales with an implausible
      recorded time.
      *Stage 1a step 2.* `sales.needs_review`.
- [x] **Per-shop stock keyed by variation** (4.2); `products.stock_quantity` and
      `product_variations.stock_quantity` removed as stored figures.
      *Stage 1a step 2.* `shop_variation` (unique `(shop_id, variation_id)`). **Departure (decided 14 October 2026):** the legacy stock columns stay until stage 1b moves their writers and readers, then 1b drops them with a migration that carries the data across.
- [x] Foreign keys from cash sessions and other business records to `users` no longer cascade on delete
      (9 tables cascade today).
      *Stage 1a step 2.* `cash_registers.user_id` and `expense_approval_history.user_id` now `nullOnDelete`. The other 7 cascades are on rows owned by the user (shop assignments, notification preferences and logs, saved reports, widgets, exports, alert recipients) and stay.

## Decisions made 12 October 2026 (were pending)

- **Per-shop stock for variants (4.2):** **every product has at least one variation**, with a default
  variation for a simple product. Per-shop stock, reorder level and price and cost overrides are keyed on
  `(shop_id, variation_id)`. SKU and barcode on variations become unique per business.
- **`customers.shop_id`:** **kept**. Customers are still scoped by business, so every shop of the business
  sees them.
- **Credit accounts:** **one per customer** (today `CreditAccount` is per shop).

## Dependencies

| Need | Package |
|------|---------|
| Optional | `larastan/larastan` (catch raw queries that skip scoping) |

No tenancy package is needed; scoping is built in-house (4.1).

## Acceptance criteria

- [x] A reflection test: every model with `shop_id` or `business_id` has its scope.
      *Step 3.* `tests/Feature/Tenancy/TenantScopeReflectionTest.php` (removing a scope from `Expense` fails it).
- [x] Scope unit tests: owner, staff, no assignment, switching business.
      *Steps 3–4:* `TenantScopeTest` (owner, staff, no assignment, a member of two businesses switching, web session switch).
- [x] Per-tenant unique keys: both businesses can use the same codes, and a soft-deleted code can be
      reused.
      *Step 5:* `PerTenantUniqueKeysTest` on both engines: same codes in two businesses; duplicates refused within one (shop, product, variation, customer, supplier, sale source, invoice number); soft-deleted codes reused (shop, product, customer, supplier, category); the rule and the supplier form agree.
- [x] The migration guard refuses a non-empty database unless forced.
      *Step 1:* `MigrationBaselineTest` (refuses, empty, already built, forced); CI checks it refuses `main`'s dataset.
- [x] A customer created in one shop is visible in every shop of the same business and in no other
      business; customer codes are unique per business.
      *Steps 3, 5 and 8:* `BusinessCustomersTest` (a branch cashier sees a customer created in the main shop; another business gets 404; a duplicate code in one business is refused), `PerTenantUniqueKeysTest`.
- [x] A customer has at most one credit account.
      *Step 8:* `BusinessCustomersTest`: credit sales in two shops share one account; a second account is refused by the service and by the database.
- [ ] Creating a product creates its default variation; per-shop stock rows are keyed on shop and
      variation.
- [ ] The flat API contract tests pass; the full suite is green on SQLite and MySQL 8.

## Risks (Phase 1, leakage)

- Code that runs with no signed-in user (jobs, webhooks, console), where scopes don't apply.
- `withoutGlobalScopes()` misuse.
- Route binding or validation accepting another business's ids.
- The memoised shop list not keyed by business.
- Spatie's cache holding team-scoped role queries.
- The flat API changing shape by accident.

## Definition of done

- [ ] Acceptance criteria met, with tests.
- [ ] Pint, the full suite (SQLite and MySQL 8) and the flat API contract tests pass.
- [ ] Hotspot checklist items for schema, tenant layer and default-deny ticked in saas-plan.md section 2.
