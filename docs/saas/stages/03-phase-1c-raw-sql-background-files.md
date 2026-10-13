# Phase 1c: Raw SQL, Background Work, Files

> **Source:** saas-plan.md section 5 (Phase 1c, Phase 1 risks), section 1 (Background work, cache, files;
> Raw SQL, reports, exports), section 2 (Queries, Background work, Cache, files, config)
> **Branch:** `saas/phase-1c-raw-sql-background-files` · **Depends on:** Phase 1b

---

## Goal

Close every path that bypasses the model scopes: raw SQL, exports, audit, jobs, the scheduler and
commands, cache keys, signed links, the e-commerce stock push and file storage.

## Before you start

No open question blocks this stage.

## Relevant current state

### Raw SQL (34 files, 145 `*Raw()` clauses)
- **Heavy users:** `ReportService` and `DashboardController` (11 each), `ExpenseController`,
  `InvoiceNumberService`, `SaleController`, `Api\ReportController`, `VatReportService`, the e-commerce
  order controllers.
- **Also:** the `Customer`, `Supplier`, `CreditAccount` and `AbandonedCart` models; `CustomerController`,
  `PaymentController`, `CampaignController`, `AlertController`, `LowStockAlertController`,
  `AttributeController`; the `CreditAccount`, `Return`, `PurchaseReturn`, `Pricing`, `Supplier`,
  `Category`, `User`, `Audit` and `AbandonedCartSync` services; `ProductObserver`;
  `HandleGatewayWebhooks`; `StorePurchaseReturnRequest`; the `BackfillShopProductPivot` and
  `AssignProductShopFromSales` commands.
- One raw query uses `DATE_FORMAT` behind a driver check.
- `ReportService` treats an empty `allowed_shop_ids` as "all shops".

### Exports and audit
- Report and audit CSV; PDFs for credit statements, customer purchase history, sale and order invoices,
  and the reports dashboard.
- The audit trail is a separate SQLite connection with its own migrations.

### Background work
- 10 jobs on `default`, `ecommerce`, `whatsapp-notifications`, `order-notifications` and `baileys`; they
  carry model ids, none carries a tenant.
- The scheduler runs the campaign post sweep every 5 minutes and `baileys:prune-media` daily.
- 9 console commands (backfills and repairs) work over all rows.
- `ProductObserver` pushes stock to Woo/Shopify on `Product::updated` only.

### Cache and files
- **Cache:** `database` store; `SMSservice` uses global keys `sms_settings` and `sms_query_parameters`
  (kept for a year); Baileys media usage is per shop.
- **Files:** all on the `public` disk with flat paths (`products/`, `profile-photos/`, Baileys media per
  session, downloaded product images); no tenant prefix, publicly reachable.

### Signed links
- Invoice links use `signedRoute` and **never expire**.

## Decisions that apply

- **4.1:** shared database with scopes; raw SQL is the main risk of a missed scope. *Confirmed.*
- **4.3:** jobs and commands set the business explicitly. *Confirmed.*

## Work

### Raw SQL
- [ ] **All 34 files** listed above filter by business and allowed shops, including the models,
      controllers, services, observer, listener and form request outside the report code.
- [ ] "Empty list means all" filters removed (`ReportService`).

### Exports and audit
- [ ] CSV and PDF exports scoped.
- [ ] `AuditService`: audit rows gain `business_id`; reads scoped.

### Background work
- [ ] The 10 jobs carry the business.
- [ ] The scheduler runs per business and skips suspended or read-only businesses.
- [ ] The 9 commands take `--business`.

### Cache
- [ ] Every cache key includes the business.

### E-commerce stock push
- [ ] Triggered by per-shop stock changes, since `ProductObserver` only watches `Product::updated`.
- [ ] Each shop's store gets that shop's stock.

### Signed links
- [ ] Invoice, statement and order-invoice links become temporary (`temporarySignedRoute`).

### Files
- [ ] A private disk with `{business_uuid}/…` paths, served through authorized routes.
- [ ] A per-tenant quota.
- [ ] Product images, profile photos and Baileys media moved onto it.

## Schema

None beyond what 1a wrote (audit rows gain `business_id` on the audit connection).

## Acceptance criteria

- [ ] Raw SQL paths and reports tested with two businesses, **on MySQL 8**.
- [ ] Jobs and commands run for one named business only.
- [ ] A stock change in shop A updates shop A's online store and no other.
- [ ] An expired signed link is refused.
- [ ] Uploaded files land under the business prefix and aren't publicly reachable.
- [ ] The flat API contract tests pass; the full suite is green on both databases.

## Risks (Phase 1, leakage)

- **A missed raw query (34 files, not 11).**
- Code that runs with no signed-in user (jobs, webhooks, console), where scopes don't apply.
- Signed links that never expire.
- Files reachable through the old public paths.

## Definition of done

- [ ] Acceptance criteria met, with tests.
- [ ] Pint, the full suite (SQLite and MySQL 8) and the flat API contract tests pass.
- [ ] Hotspot checklist items for queries, background work, cache and files ticked in saas-plan.md
      section 2.
