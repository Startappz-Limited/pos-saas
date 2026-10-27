# Stage: Stock Correctness and Voids

> **Source:** FIFO review of 26 October 2026 (the stock ledger and its callers, after Phase 1b); stock intake and
> products review of 27 October 2026; plan review, Phase 7 alignment and its review, 27 October 2026; split of the
> combined stage, 27 October 2026.
> **Branch:** `fix/stock-voids-fifo`, from `main` (Phase 7 merged, `832f684`) · **Depends on:** Phase 1b (the
> `StockLedger` and FIFO cost batches), Phase 7 (`RecordSale`, sync push and pull, register-bound tokens, till
> PINs, the till sync review, `payment_requests`)
> · **Next:** [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) (depends on this stage),
> then [11b-prices-and-discounts.md](./11b-prices-and-discounts.md) and
> [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md), then
> [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) · App checks:
> [app-release-checklist.md](./app-release-checklist.md#stage-10)
> · **Related:** [14-returns-in-reports.md](./14-returns-in-reports.md) (returns in sales, cost and profit; uses
> `cost_known`, `cash_payouts` and `refund_due` from this stage)

---

## Revisions

**Split from the combined stage (27 October 2026).** "Stock Intake, Products and FIFO Integrity" became three
stages, because it mixed bug fixes in code that exists today with two new foundations (units and decimal
quantities, then receiving and transfers on top of them), and the decisions of 27 October changed the foundations:
- **This stage (10)** fixes what is wrong today, in whole units as now: the permission rollout, selling from the
  selling shop, clerks and costs, voids, deletes, till voids and `cost_known`.
- **[Stage 11](./11-units-quantities-and-batches.md)** brings units of measure, decimal quantities, FEFO and
  batches, and drops the legacy columns.
- **[Stage 12](./12-receiving-and-moving-stock.md)** brings goods received notes, purchase orders in units,
  transfers and the recall report.

Step numbers are kept from the combined stage (0, 1a, 1f, 2 to 8), so references to them elsewhere still hold.
Steps 1b to 1e moved to stages 11 and 12.

Changed by the decisions of 27 October 2026:
- **Step 1f:** clerks lose `products.view-cost` as well as `products.set-cost`; the flat API's product endpoints
  are authorised and hide costs from those without `products.view-cost`; the sync pull is pinned to carry no
  cost field.
- **Step 7:** `sales:report-cross-shop-lines` is removed (no business needs to reconcile past cross-shop sales).
  `sales:restock-voided` and `stock:orphaned-movements` stay for the UAT businesses.
- **Step 3:** `release()` works on any consumer; transfer lines (stage 12) use it unchanged.
- **Points to confirm:** the six from the combined stage are decided or moved; one new point (the flat API's
  product endpoints).

**Sixth round (27 October 2026):**
- **Decided: a fiscal number, once printed or signed, is never changed by the server.** Accepting a held sale keeps
  the till's invoice number; `till_invoice_number` and the "Renumbered from …" reprint are gone (step 5c).
- **Decided: tills and the web never share an invoice series** (new step 5d). Found in the code: every sale path
  takes its number from its cash session's register, so web POS, flat API and online sales draw on `R1`, the series a
  paired till numbers offline; that is how a till's number gets taken. Web and online sales move to a per-shop web
  series (`INV-{shop}-W-{year}-{n}`); tills keep their register's.
- **Which validation errors can be corrected** is a table (step 5c), from the rules a pushed sale passes through.
- **App checks move to the app's own release:** rollout checks the server against the app's recorded requests; the
  app-side checks are in [app-release-checklist.md](./app-release-checklist.md#stage-10). No app behaviour is
  assumed.
- Stage 11 was split into 11 (units), 11b (prices and discounts) and 11c (low stock, alerts and snapshots); the price
  check is 11b's.

**Fifth round (27 October 2026):** the changes outside `docs/saas/stages/` made during the review (notes on old
documents, comments in old migration copies, corrections to the app's documents, lines in the plan) were reverted:
planning rounds change only this folder. Each is now a planned item in the stage whose work makes it true, with its
exact text (step 8).

**Fourth round (27 October 2026):**
- **Decided:** held push items can be **accepted** on the till sync review (`sync-issues.accept`, new), not only
  discarded (step 5c); a payment pushed for a sale already paid is a `refund_due` (`source = duplicate_payment`) and
  dropped; every push and pull sends `X-App-Version`, and older apps get Phase 7's codes back (step 5c, "Contract").
- **Decided (were "Points to confirm" 1 to 3):** products are created from the app only online, by someone holding
  `products.create` (step 1f); who approves adjustments, with a business threshold, and never the creator (step 1g);
  "returned to supplier" leaves the adjustment reasons for purchase returns (step 1g). "Recount" stays a signed
  change until stocktakes ([stage 13](./13-stocktakes.md)) replace it.
- **Found:** the flat API's product create drops `variations`, `has_variations` and `status`, which
  `docs/flutter/09-products.md` documents (gap 18, step 1f).
- `CostingService`, `CostingMethod` and `sale_item_stock_allocations` are removed in stage 11 (decided), no longer a
  follow-up here. Traceability: [TRACEABILITY-stock.md](./TRACEABILITY-stock.md).
- Nothing remains open.

**Legacy design review (27 October 2026):** the pre-SaaS modules 08 (stock intake), 09 (inventory tracking), 20 (FIFO
costing) and 21 (stock adjustments) in `docs/implementation/` were reviewed against the code. Stock adjustments, built
from module 21, have bugs in code that exists today, so they are fixed here (step 1g, gap 17); the design
improvements went to stages 11 and 12. Two new points to confirm (adjustment approval, returns to the supplier as
adjustments).

**Third round (27 October 2026):**
- **One discard for every stuck outbox item:** `outbox_item_discarded` (any item type, by someone holding
  `sales.void`) replaces `sale_void_discarded`; its issue type replaces `void_discarded` (step 5c).
- **Every push refusal has one class** (retry, drop or hold), carried in a new `disposition` field on every result
  that isn't applied. Three Phase 7 refusals that meant "push the other item first" get their own codes
  (`customer_not_synced`, `sale_not_synced`, `cash_session_not_synced`), so that no code has two classes (step 5c,
  "Contract").
- **A refused sale no longer drops its void:** sales are held, so a queued void waits behind its sale and is
  discarded with it.
- **Follow-ups:** the server's price check is stage 11's (it needs `PriceResolver`).

The combined stage's revisions of 27 October 2026 (plan review, Phase 7 alignment and its review) were never
committed on their own; git history holds the combined file only as of `61245e3`
(`docs/saas/stages/10-stock-voids-and-fifo-integrity.md`). Everything in them is carried into stages 10 to 12. Those
that apply here:
step 0; the stock row created before it is locked; products that don't track stock; `VoidWindow` checked at the
current time or at the push item's `recorded_at`; `release()` through `changeStock()`; cash on a void and
`cash_payouts`; step 5c (pending voids, once only, `void_refused`, `voidable_until`, `refund_due`, `outbox_count`,
re-pairing, and the discard item, now `outbox_item_discarded`); the Contract section.

---

## Goal

Every sale takes stock from the shop that sells it, and every sale that leaves the books gives its stock back. A
voided sale returns its units to the cost batches they came from. Its cost records are released, and it can no
longer be re-costed. A sale that took stock can't be deleted in a way that loses that stock. Voiding works the same
from the web, the app and an offline till, under one permission and one time limit. Lines whose cost isn't fully
known are marked as such, so reports can say so instead of quietly counting them as free.

Every business gets the permissions its roles are meant to have, whenever it was created, and keeps the ones it
took away. Clerks count stock; they don't see or set costs. (Steps 0 and 1f.)

## Before you start

The decisions of 26 and 27 October 2026 are below. Nothing remains open (see "Points to confirm").

Start from `main` after Phase 7 (`832f684`): steps 1a, 4 and 5c change code Phase 7 added (`RecordSale`, the sync
push and pull, `TillPayments`). The "current state" lines below were checked against that code on 27 October.

## Decisions that apply

- **Context (27 October 2026):** there are no customers yet; the system is being set up for new businesses,
  including pharmacies, agro-vets and wholesalers. The existing businesses on UAT are kept, so every change to
  existing data is a command with a dry run and `--apply`, rehearsed on a restored snapshot in `rehearsal_testing`
  first. Repairs whose only purpose was protecting customer history are dropped; repairs that keep the UAT
  businesses consistent stay.

- **Voids and deletes (decided 26 October 2026):**
  - **Only the owner can void by default.** `sales.void` is held by the owner role only; the owner can give it to
    other roles in their business. `sales.edit-all` no longer allows voiding.
  - **Completed sales can't be deleted, only voided.**
  - **A sale with a return can't be voided.**
  - **Voiding has a time limit: by default, until the end of the day the sale was made** (midnight in the shop's
    timezone, so always within 24 hours). Each business can instead set a number of minutes (up to 24 hours) or
    no limit at all. **Anyone with `settings.update` can change it**, and **it applies to the owner too.**
    (Changed on 26 October 2026 from 15 minutes.)
  - **The web sale page gets a Void button**, clearly labelled, with instructions on what voiding does.
  - **A sale is voided automatically when its online order is cancelled before dispatch.**
  - **Below-zero sales settled against stock with no cost keep their estimated cost** instead of dropping to zero.
  - **No partial voids:** if only some items are wrong, void the whole sale and ring it up again, or use a return.

- **Selling shop (decided 27 October 2026):** a sale takes stock from the selling shop, never from the shop a
  product was first added in.

- **Clerks don't see or set costs (decided 27 October 2026):** `products.set-cost` **and** `products.view-cost`
  are revoked from the inventory-clerk template and its copies, recorded in `role_revoked_permissions`. The cashier
  template doesn't hold `products.view-cost` (checked: it holds `products.view` only). The sync pull sends no cost
  fields to a till user without `products.view-cost`.

- **Stuck outbox items (decided 27 October 2026):** one push item, `outbox_item_discarded`, discards any item a till
  holds (a sale, a void, a payment, and so on), by someone holding `sales.void`, reviewed and audited. Every push
  refusal has exactly one class: transient (retry), final and dropped, or final and kept for a person (hold).

- **Held items can be accepted (decided 27 October 2026, fourth round).** The till sync review gives a held push
  item two resolutions: **accept** (a manager holding `sync-issues.accept` applies it on the server, always with the
  till's own invoice number; a sale outside the lease is applied at the till's recorded time and flagged; a validation
  fault is applied as the table in step 5c says) or **discard** (`outbox_item_discarded`, only for items that
  shouldn't exist). Either way it is audited, and the till learns of it on its next sync.
- **A fiscal number is never changed by the server (decided 27 October 2026, sixth round).** An invoice number, once
  printed on a receipt or signed (by eTIMS, [stage 22](./22-etims.md)), stays as issued. A correction is a credit
  note and a new sale, never a renumbering.
- **Tills and the web never share a series (decided 27 October 2026, sixth round).** Each till numbers from its own
  register's series, unique per register; web, flat API and online sales take a per-shop web series a till can't
  produce. The app in shops keeps working unchanged (step 5d).
- **A payment for a sale already paid is a refund owed (decided 27 October 2026, fourth round):** `refund_due` with
  `source = duplicate_payment`; the item is dropped so the till clears it.
- **Old apps get the codes they know (decided 27 October 2026, fourth round):** every push and pull sends the app's
  version; for apps older than this stage's, the server translates new refusal codes back to Phase 7's and leaves
  out `disposition`.
- **Products from the app (decided 27 October 2026, fourth round):** the app creates products only online, and only
  for someone holding `products.create` (the cashier template doesn't). No offline product creation: an offline till
  that scans an unknown item can't sell it.
- **Who approves stock adjustments (decided 27 October 2026, fourth round):**
  - applied by its creator (holding `stock-adjustments.create`): increases, and decreases for reasons other than
    loss, theft, damage or expiry, whose cost value is below the business's threshold;
  - needs a **second person** holding `stock-adjustments.approve`: loss, theft, damage, expiry, and any adjustment
    whose cost value is above the threshold. **The creator can never approve their own;**
  - the threshold is `stock.adjustment_approval_threshold` (default KES 5,000), changed by `settings.update`,
    audited; approving applies the stock in the same step.
- **"Returned to supplier" is a purchase return (decided 27 October 2026, fourth round)**, not an adjustment reason.
- **Recounts move to stocktakes (decided 27 October 2026, fourth round):** an adjustment records a change ("3
  damaged"); a count ("we have 47") is a stocktake ([stage 13](./13-stocktakes.md)). "Recount" stays a signed change
  until stocktakes exist, then leaves the reasons.

- **From Phase 7 (till first, 26 October 2026):** tills work through `/api/v1` with register-bound tokens and
  sync offline (pull and push, once per client uuid, inside a trading lease); people sign in on an offline till with
  a till PIN, and the till checks their permissions from the staff list; what a push turns up goes on the till sync
  review. So a void is also a till action, online and offline (step 5c).

- **Stage 1b (agreed 15 October 2026, [fifo-analysis.md](../fifo-analysis.md)):**
  - Every stock change goes through `StockLedger`.
  - Stock is held per shop and variation in `shop_variation`, changed by a delta under a row lock.
  - Cost batches (`cost_layers`) are used oldest first.
  - What each sale line used is recorded in `cost_allocations`.
  - Selling below zero is allowed: the shortfall is costed provisionally and re-costed when stock arrives.
- **Profit is net of VAT** ([docs/vat.md](../../vat.md)).
- **Live-data rule (CLAUDE.md):** every repair of existing data is a command with a dry run and `--apply`, run on
  `_testing` first.

## Relevant current state

**How FIFO works today:**
- `receive()` creates a batch. That batch first settles provisional (below-zero) allocations, oldest first, and
  re-costs those sales.
- `consume()` uses batches oldest first, one allocation each. A shortfall gets a provisional allocation at
  `fallbackCost()`.
- `sellItem()` is called by all four sale paths, then `recostSale()`. The four are the web POS
  (`SaleController::store`), `RecordSale` (the flat API and the sync push, since Phase 7), online-store orders and
  abandoned carts.
- **Stock is taken when the sale is created, whatever its status.** A pending or cash-on-delivery sale holds its
  stock too.
- Allocations name their consumer polymorphically (`consumer_type`, `consumer_id`; `StockLedger::consumerKey()`
  maps sale, adjustment and supplier-return lines, and any other model to its snake-case name), so one method can
  release what any consumer took.

**The invariant the ledger keeps** for a shop and variation:

```
shop_variation.stock_quantity
  = Σ cost_layers.remaining_quantity
  − Σ cost_allocations.quantity_allocated (where is_provisional)
```

**The gaps:**
1. **Voiding gives nothing back.** `SaleController::void` (web and API) sets `status = voided` and reverses the
   credit; nothing else happens:
   - stock stays reduced, and the batches stay used;
   - the sale's allocations stay in place;
   - a provisional allocation on a voided line is still re-costed later by `settleProvisional()`.
2. **Deleting loses stock.** `Sale` has no `SoftDeletes` (the `sales.deleted_at` column exists but is never set), so
   `$sale->delete()` is a hard delete:
   - it cascades to `sale_items` and then to `cost_allocations`;
   - the batches stay used and the stock stays reduced;
   - the `StockMovement` rows point at a sale that no longer exists.
3. **The API checks only shop access for void and delete.** Web uses `SalePolicy::void` and `::delete`
   (`sales.void`, `sales.delete`). `Api\SaleController::void` and `::destroy` check only `canAccessShop()`, so any
   member assigned to the shop can void or delete through the app.
4. **Unknown costs count as zero, silently:**
   - a line that used a batch with no cost (`unit_cost` NULL) adds 0 for those units, and nothing marks the line;
   - `settleProvisional()` against a batch with no cost replaces the provisional estimate with NULL, which then
     sums as 0.
5. **Unused leftovers:** `CostingService`, the `CostingMethod` enum and `sale_item_stock_allocations` (CLAUDE.md
   already lists them).
6. **A sale takes stock from the product's "owning" shop, not the selling shop.** Each line's shop is
   `$product->shop_id ?? $shopId` in `RecordSale.php:170`, `SaleController.php:318` and
   `ConvertAbandonedCartToSale.php:141`. The flat API also files the whole sale under its first product's owning shop
   when no till is involved (`RecordSale.php:59`; a till's push already uses the till's shop). Online-store orders
   are right already: their lines carry no shop, so `sellItem()` uses the sale's.
7. **The `shop_variation` rows are made only when a product is created** (`syncShopVariationRows()` in the
   `created` hook). Listing a product in a new shop, or adding a variation, doesn't always make them, so a sale at
   that shop has no row to lock.
8. **The inventory-clerk template holds `products.set-cost` and `products.view-cost`**, so neither permission can
   separate counting from costing. The cashier template holds `products.view` only.
9. **The flat API's product endpoints check no permission** (checked 27 October 2026). `Api\ProductController`
   has no `authorize()` call and its routes only `auth:sanctum` and `business`, so any member, a cashier included,
   can create, edit and delete products and set `cost_price` through `/api/products`. Every product response
   carries `cost_price`.
10. **The sync pull carries no cost field today** (`SyncPull::entity()`: products, variations and stock carry
    prices, not costs). The pull is per till (a register-bound token), not per person, so a field can't be sent
    to some of the till's users and not others.
11. **The permission rollout misses businesses.** `RoleSeeder::grant()` and `grantNewPermissionsToBusinessCopies()`
    decide what is new by comparing `permissions.created_at` with the role's `updated_at` or the copy's
    `created_at`. A permission created before a business's copies, but granted to the template after, never
    reaches them. Existing businesses lack `attributes.*`, `stock.view`, `registers.*` and `sync-issues.*` for this
    reason. `PermissionSeeder::names()` also lists `registers.view` twice.

**After Phase 7:**

12. **Tills can't void.** There is no `/api/v1` void and no push item for one, so a till (online or offline) can only
    ask someone at the web screen, which has no Void button either (step 5a).
13. **Tills aren't told about voids made elsewhere.** The pull carries no sales, so a till keeps showing a sale as
    paid after it was voided on the web or by another till.
14. **A confirmed M-Pesa payment can bring a voided sale back.** `TillPayments::apply()` checks only that the sale
    isn't paid; a voided sale is unpaid, so the payment is recorded and `RecordSalePayment` sets the sale `paid` and
    `completed` again.
15. **Voiding a paid sale returns no money and records none.** Cash drops out of the session's expected cash
    (only completed sales count), but nothing says the customer is owed it back; an M-Pesa or card payment has no
    trace at all until refunds exist ([stage 14](./14-returns-in-reports.md)).
16. **Repair commands and the review screen exist to reuse:** `sync_issues` and its screen (Phase 7) take new issue
    types; `InvoiceNumberService` shows how a gapless per-shop series is kept.

**Legacy design review (27 October 2026):**

17. **Stock adjustments** (module 21, checked 27 October 2026):
    - **They only ever move the default variation.** `stock_adjustment_items` has `product_id` and no `variation_id`,
      and `StockAdjustmentService` adjusts `$product->defaultVariation()`, so a product with variations can't be
      adjusted: its stock goes to the hidden default variation.
    - **The figures are recorded at draft time.** `addItem()` stores `quantity_before` and `quantity_after` when the
      line is added; completion (days later) moves stock by `quantity_change` but leaves the stale figures.
    - **The direction isn't tied to the reason.** Each adjustment has one `type` (`increase` / `decrease`) and one
      `reason`, validated separately, so "increase, lost" is accepted, and a recount that finds some items over and
      some under needs two documents.
    - **Approve and complete are two steps, and both need `stock-adjustments.approve`.** `requiresApproval()`
      (lost, stolen) is never called: every adjustment waits for approval, then waits again to be completed; nothing
      stops the creator approving their own.
    - **Numbers are random** (`'ADJ-'.Str::random(8)`), unique across all businesses rather than a series per shop.
    - **UAT (read-only, 27 October 2026):** 21 adjustments: **16 approved but never completed** (their stock never
      moved), 1 completed, 4 pending; one "increase, lost"; one approved by its creator; none on a product with
      variations.

**UAT (read-only counts, 27 October 2026):** ten businesses, three with products (97, 97 and 12); no voided sales
yet. So `sales:restock-voided` (step 7) has nothing to do on UAT today; it stays for voids made before this stage
is deployed.


**Fourth round (27 October 2026):**

18. **The flat API's product create doesn't take what the app's contract document says** (checked 27 October 2026).
    `docs/flutter/09-products.md` documents `variations`, `has_variations` and `status` on `POST /api/products`, and
    `sku` and `category_id` as required; `Api\ProductController::store` validates none of the first three (they are
    silently dropped, so a product sent with variations is created simple) and takes `sku` and `category_id` as
    optional.
19. **No app version reaches the server.** Phase 7's push and pull send no version, and nothing stores one, so the
    server can't tell an old app from a new one.

---

## Work

### 0. Permissions reach every business (first: this stage adds and revokes permissions)

- [ ] **Removals are recorded, not inferred.** New table `role_revoked_permissions` (`role_id`, `permission_id`,
      `revoked_by` nullable, timestamps; primary key on the pair; both foreign keys cascade on delete).
  - The business role screens and the platform console's template editor write a row when a permission is taken
    off a role, and delete it when the permission is given back. "Reset to default" clears a copy's rows.
  - Every data migration that revokes a permission (`sales.void` in step 2; `products.set-cost` and
    `products.view-cost` in step 1f) writes the rows too.
- [ ] **`RoleSeeder` without timestamps:**
  - `grant()`: a template gets its listed permissions minus its revoked ones. The `created_at > updated_at` filter
    goes.
  - `grantNewPermissionsToBusinessCopies()` becomes `syncBusinessCopies()`: each copy gets its template's
    permissions minus the copy's revoked ones. The `created_at > copy.created_at` filter goes.
- [ ] **One-time backfill**, `php artisan roles:backfill-rollout` (dry run by default; `--apply` writes;
      `RunsForBusiness`). Kept: the UAT businesses lack the Phase 5 and 7 permissions. For each business copy and
      each permission its template holds that the copy lacks:
  - if the permission is one of those added in Phase 5 or 7 (`attributes.*`, `stock.view`, `registers.*`,
    `sync-issues.*`), grant it: these are the rollout's misses;
  - otherwise, record it in `role_revoked_permissions`: before this stage, an absence can't be told apart from a
    deliberate removal, so it is kept as one.
  - The report lists, per business and role, what was granted and what was recorded as revoked.
- [ ] **`php artisan permissions:sync`**: runs `PermissionSeeder`, then `RoleSeeder`, then clears the Spatie cache.
      The deploy script runs it after migrations; nothing runs `PermissionSeeder` on its own any more. Stages 11
      and 12 add their permissions through it.
- [ ] **No `Gate::before` for the owner.** The owner holds every permission through the `admin` role, and a
      `Gate::before` returning `true` would skip `SalePolicy::void`'s time limit, which applies to the owner too
      (step 2).
- [ ] Remove the duplicate `registers.view` from `PermissionSeeder::names()` (a plain insert in
      `seedPermissions()` fails on the unique index).
- [ ] **Tests** (`PermissionRolloutTest`):
  - the race: a permission is created, then a business, then the template is granted it and `RoleSeeder` runs; the
    business's copy has it;
  - a permission a business took off its copy stays off after `RoleSeeder` runs again;
  - a permission the platform console took off a template stays off;
  - "Reset to default" gives back what the template holds;
  - the backfill grants the Phase 5 and 7 permissions, records other absences as revoked, and a second `--apply`
    changes nothing;
  - `permissions:sync` run twice changes nothing the second time.

### 1a. Sell from the selling shop (a bug fix)
- [ ] A sale line's shop is the **selling shop**: the till's shop for a register-bound token, the request's
      validated `shop_id` on the flat API and the web POS, the order's shop for online orders. Never the product's
      `shop_id`. In `RecordSale` (flat API and sync push), `SaleController::store` and
      `ConvertAbandonedCartToSale`. `ConvertEcommerceOrderToSale` is already right (its lines take the sale's shop);
      a test pins it.
- [ ] The sale's own shop is the selling shop too, not its first product's owning shop.
- [ ] A product not stocked at the selling shop still sells: the shop's stock goes below zero, costed
      provisionally and flagged (`negative_stock`), as offline sales are.
- [ ] **The stock row exists before it is locked.** `StockLedger::lockStock()` inserts the `shop_variation` row
      (`insertOrIgnore`, quantity 0) before taking the row lock, for every caller: sales, receipts, adjustments,
      releases (and, from stage 12, GRNs and transfers). Listing a product in a shop and adding a variation also
      call `syncShopVariationRows()` (in the actions that do them, not only in `Product::created`).
- [ ] **Products that don't track stock never move stock.** `sellItem()` skips the ledger for `track_stock = false`
      (no movement, no allocation, `cost_known` from the variation's cost). Stage 12's GRN and transfer lines
      refuse them.
- [ ] `products.shop_id` means "first stocked at" from now on. Nothing reads it as ownership; the product form's
      label says "First stocked at".
- [ ] **Reports:** find every report and query that groups or filters sales by `products.shop_id` (sales by shop,
      profit by shop, dashboard tiles, exports) and change it to `sales.shop_id` / `sale_items.shop_id`. List them
      in the PR.
- [ ] Past cross-shop sale lines are left as they are: no business needs them reconciled (decided 27 October 2026).
- [ ] **Flat API contract:** the response is unchanged; only which shop's stock moves changes. `FlatApiContractTest`
      and `LiveLikeDatasetTest` pass on MySQL.
- [ ] Tests: a sale at shop B of a product first added at A takes B's stock and B's batches, on web, flat API, sync
      push and both converters; a till's sale is filed under the till's shop; A's stock is unchanged; a sale at a
      shop with no `shop_variation` row creates it and goes below zero; a product that doesn't track stock moves
      nothing; the sales-by-shop report counts a cross-shop sale under the selling shop.

### 1f. Clerks don't see or set costs
- [ ] `RoleSeeder` no longer gives `products.set-cost` or `products.view-cost` to the inventory-clerk template. It
      keeps `products.view` and its stock permissions.
- [ ] A migration revokes both from the clerk template and every business's copy of it, writing
      `role_revoked_permissions` rows (step 0) so the rollout doesn't give them back. Counts before and after; each
      change audited; Spatie cache flushed. Custom roles a business made itself keep what the owner gave them.
- [ ] **The cashier template stays without `products.view-cost`**; a test pins that neither the cashier nor the
      clerk template holds a cost permission.
- [ ] **The flat API's product endpoints are authorised** (gap 9) through `ProductPolicy`, as the web is: `index`
      and `show` need `products.view`, `store` `products.create`, `update` `products.update`, `destroy`
      `products.delete`, `activate` and `deactivate` `products.update`. Refusals stay `{success: false, message}`
      with 403.
- [ ] **Costs on the flat API follow the permissions:**
  - responses carry `cost_price` (and any other cost field on the product, its variations and its shop rows) only
    for someone holding `products.view-cost` or `products.set-cost`; for anyone else the key is kept and its
    value is `null`, so the app's parsing doesn't change (it already treats a missing cost as unknown);
  - writing `cost_price` needs `products.set-cost`: without it, `store` takes the product with no cost (NULL,
    "cost unknown") and `update` refuses a `cost_price` change with 403 ("You can't set purchase costs.").
- [ ] **Products are created from the app only online** (decided): `POST /api/products` needs `products.create`,
      which the cashier template doesn't hold. Offline product creation isn't supported: client-made products would
      bring barcode and SKU clashes into the sync, so the push has no product item, and an offline till that scans an
      unknown item can't sell it.
- [ ] **No silently dropped fields** (gap 18): `POST /api/products` with `variations` or `has_variations = true`
      answers 422 ("Create products with variations on the web.") rather than creating a simple product; `status` is
      accepted (`Rule::enum`), as on the web. The app's document is corrected in step 8.
- [ ] **The sync pull carries no cost field** (gap 10). A test pins it: no key in the pull's products,
      variations, stock or staff sections names a cost. The pull goes to a till, which every member at the shop
      uses, so a cost field would reach people without `products.view-cost`; if a later stage needs costs on a
      till, it is a separate endpoint called by a signed-in person who holds the permission.
- [ ] Tests: a clerk can count stock and adjust it, and can't open the Purchase Costs screen, see a cost on the
      product pages, or set a cost on the product form; a role the owner gave `products.set-cost` can; a cashier
      gets 403 creating a product through `/api/products`; a member without `products.view-cost` gets
      `cost_price: null` from `/api/products/{product}`, and one with it gets the cost; the flat API contract test
      passes (the key is kept); a product sent with `variations` is refused with 422 and nothing is created; a member
      holding `products.create` creates a simple product with a `status`.

### 1g. Stock adjustments: fix what is wrong today (legacy design review)
- [ ] **By variation:** `stock_adjustment_items.variation_id` (foreign key; required for a product with variations,
      the default variation for one without). The form and `StoreStockAdjustmentRequest` (used by the controller,
      which validates inline today) pick a variation; the service adjusts that one. Existing items get the default
      variation, which is what the code moved (a migration backfill with counts: 21 on UAT, none on a product with
      variations).
- [ ] **Figures at completion:** a draft line shows the current stock live; `quantity_before` and `quantity_after`
      are written when stock moves, from the ledger's `StockChange`, not when the line is added.
- [ ] **The reason decides the direction:** `AdjustmentReason::direction()`: `decrease` for damaged, expired, lost,
      stolen, quality issue, promotional giveaway; `increase` for found; `either` for recount and other. A line's
      `quantity_change` is signed (`+` adds, `−` removes) and must agree with its reason's direction; the adjustment's
      `type` becomes derived (kept for display). So one adjustment can raise some lines and lower others.
- [ ] **"Returned to supplier" leaves the reasons** (decided): new adjustments can't use it; the form points to
      purchase returns, which keep the credit the supplier owes. Existing adjustments with it stay as history (none
      on UAT).
- [ ] **"Recount" stays, as a signed change, until stocktakes exist** (decided): the person enters the difference,
      not the counted figure, and sales made while they counted aren't allowed for. The form says so ("For a full
      count, use a stocktake" once [stage 13](./13-stocktakes.md) ships, which removes the reason). 1 on UAT.
- [ ] **Who approves** (decided): `AdjustmentApproval::needsSecondPerson($adjustment)` is true when any line's reason
      is lost, stolen, damaged or expired, or when the adjustment's cost value (Σ base quantity × the cost the ledger
      would use) is above `businesses.settings['stock']['adjustment_approval_threshold']` (default KES 5,000; set on
      the business settings page under "Stock" by `settings.update`, audited).
  - When it is false, the creator (holding `stock-adjustments.create`) applies it on saving.
  - When it is true, it waits for someone **other than the creator** holding `stock-adjustments.approve`. The policy
    refuses the creator, whatever they hold, the owner included.
- [ ] **One step to apply:** approving applies the stock in the same transaction; "complete" as a separate step goes.
- [ ] **Adjustments approved but never applied** (16 on UAT): `php artisan stock-adjustments:list-unapplied
      --business=<id>` (read-only) lists them with their lines and the stock now; `--reason=<reason>` lists every
      adjustment with that reason whatever its status (for "returned to supplier", so the owner can raise purchase
      returns for any credit still owed). The owner applies or rejects each from its page, through the one-step
      approval above; nothing is applied automatically, since the stock may have been corrected another way since.
- [ ] **Numbers:** `ADJ-{shop code}-{year}-{n}`, a gapless series per shop and year (as invoice numbers), assigned
      when the adjustment is saved; existing adjustments keep their numbers; the unique index becomes per business.
- [ ] Tests: a variation of a product with variations is adjusted, and the default variation isn't; the before and
      after figures are those at approval; "increase, lost" is refused; one recount raises one line and lowers
      another; approving applies the stock once and can't be done twice; a found-stock increase is applied by its
      creator; a damaged-stock decrease waits, and its creator (even the owner) can't approve it while a manager can;
      an "other" decrease above KES 5,000 waits and one below is applied; the threshold is changed by
      `settings.update` and audited; "returned to supplier" is refused on a new adjustment; `--reason` lists old
      ones;
      numbers are gapless per shop and two businesses' don't collide; another business's adjustment is a 404.

### 2. Who can void and delete, and for how long
- [ ] **API checks:** `Api\SaleController::void` calls `$this->authorize('void', $sale)`; `::destroy` calls
      `$this->authorize('delete', $sale)`, so web and API apply the same policy. Refusals stay
      `{success: false, message}` with 403 (flat API shape).
- [ ] **Only `sales.void` allows voiding:** remove the `sales.edit-all` branch from `SalePolicy::void`.
- [ ] **The owner only, by default:**
  - `RoleSeeder` no longer gives `sales.void` to the manager template. The owner (`admin`) holds every
    permission, so it keeps it.
  - A migration revokes `sales.void` from the shared manager template and every business's copy of it:
    - counts before and after;
    - each change audited, and recorded in `role_revoked_permissions` (step 0);
    - Spatie cache flushed.
  - Owners can give it back to any role from the role screens (which deletes the revocation row); the template's
    "Reset to default" no longer adds it.
  - Custom roles a business made itself keep whatever the owner gave them.
- [ ] **Time limit:**
  - A business setting `sales.void_window`, stored in `businesses.settings`. It takes one of three values:
    - `end_of_day` (the default, also used when the setting is missing): a sale can be voided until 23:59:59 on
      the day it was completed;
    - a whole number of minutes from 1 to 1,440 (24 hours), counted from completion;
    - `none`: no limit.
  - **"The day" is the shop's local day:** the sale's completion time is read in the shop's timezone (`shops.timezone`,
    else `businesses.timezone`, default Africa/Nairobi), never the server's UTC. So a sale at 22:00 in Nairobi can be
    voided until midnight Nairobi time, not until midnight UTC.
  - A sale made just before midnight has only minutes left. That's intended: the day's figures close at midnight.
  - A small `App\Support\Sales\VoidWindow` works out, for a sale, until when it can be voided (`until($sale)`) and
    whether it can be voided at a given moment (`allows($sale, CarbonInterface $at)`). The policy, the sale page,
    `/api/v1` and the push all use it, so they always agree.
  - It's on the business settings page, under "Sales", with the choices "Until the end of the day (default)", "A
    number of minutes" (with the number) and "No time limit". **Anyone with `settings.update` can change it**
    (decided), as with the page's other settings. Each change is audited with the old and new values.
  - `SalePolicy::void` refuses once the window has closed. Messages:
    - "This sale can only be voided on the day it was made. Use a return instead." (end of day);
    - "This sale can no longer be voided (limit: :minutes minutes). Use a return instead." (minutes).
  - **The owner is held to the limit too** (decided). Past the window, a return keeps the books and the register
    right. (Hence no `Gate::before` for the owner, step 0.)
  - **Which moment is checked:** an online void (web or `/api/v1`) is checked at the current time; an offline void
    (the push item `sale_void`, step 5c) at the item's `recorded_at`, which must lie inside a lease the till was
    given. The window always runs from the sale's own completion time, read in the shop's timezone. So a sale and its
    void both recorded offline on Monday and pushed on Tuesday are accepted, while a void recorded on Tuesday for
    Monday's sale is refused.
- [ ] **Tests:**
  - a manager with no grant gets 403 on web and API;
  - a role the owner gave `sales.void` succeeds;
  - `sales.edit-all` alone is refused;
  - by default, a sale made at 10:00 can be voided at 23:59 the same day, and is refused at 00:00 the next day;
  - a sale made at 23:50 is refused at 00:01;
  - the day is the shop's: for a Nairobi shop, a sale at 22:00 local time (19:00 UTC) is refused at 00:30 local time,
    even though it is still the same day in UTC;
  - a shop with its own timezone uses that timezone;
  - a business set to 60 minutes allows a void at 30 minutes and refuses one at 61;
  - a number above 1,440 is refused by the settings form;
  - "No time limit" allows a week-old sale;
  - the owner is refused the next day;
  - `VoidWindow::allows()` with `$at` the current time (online) and with a push item's `recorded_at` (offline): a
    Monday void of a Monday sale pushed on Tuesday is allowed; a Tuesday void of it is not;
  - a member with `settings.update` changes the limit, and one without it can't;
  - another business's setting doesn't apply;
  - another business's sale is still 404;
  - the migration revokes from copies and leaves custom roles alone;
  - after the migration, `permissions:sync` doesn't give `sales.void` back to the manager copies.
- [ ] Check the Flutter app's void/delete buttons against the permissions it reads (`authorization_helper.dart`).
      Record any paired change under "Contract". The app should hide Void past the window, and treat the 403 as
      a message.

### 3. `StockLedger::release()`: give a consumer's stock back
- [ ] New public method: `release(Model $consumer, StockMovementType $type, array $context = []): StockChange`.
      It undoes everything one consumer took, whatever the consumer is: it finds the allocations through
      `consumerKey()`, so a sale line, adjustment line or supplier-return line today, and stage 12's transfer
      lines, are released by the same code with no change to this method. In one transaction:
  1. Lock the `shop_variation` row (as `lockStock()` does), then the consumer's allocations.
  2. **Settled allocations** (with a `cost_layer_id`): add `quantity_allocated` back to that batch's
     `remaining_quantity` and clear its `consumed_at`. Units go back **into the batch they came from**, so FIFO
     order and cost stay exact.
  3. If that batch no longer exists (product deleted), create a new batch at the allocation's `unit_cost`, with
     `source_type = release`.
  4. **Provisional allocations** (no batch): delete them. The stock comes back but no batch does, which keeps the
     invariant.
  5. Raise `shop_variation.stock_quantity` by the total released, through `changeStock()` (which sets
     `shop_variation.updated_at`, the column the sync pull pages stock by, as it does for `receive()` and
     `consume()`), and write one `StockMovement` of `$type` (`SALE_VOID`, new), at the weighted cost released. The
     caller passes the type, so stage 12 adds `TRANSFER_CANCEL` without touching `release()`.
  6. Delete the released allocations. Their history is kept in the movement and the audit log.
  7. Push stock to the shop's online stores after commit, as `changeStock()` does.
- [ ] `StockMovementType::SALE_VOID` (addition). `CostLayer::SOURCE_RELEASE`.
- [ ] Unit tests on SQLite and MySQL:
  - a line taken from two batches goes back to both, in the right quantities;
  - a provisional line releases stock with no batch;
  - a mixed line (part batch, part provisional);
  - an adjustment line and a supplier-return line release through the same method (the transfer-line test is in
    stage 12);
  - the invariant holds after each case;
  - releasing twice changes nothing the second time;
  - a released line's `shop_variation.updated_at` moves on, and a till's next pull carries the restored stock.

### 4. Voiding gives stock back
- [ ] Web and API `void` call `release()` for every line, inside the existing transaction, before the credit
      reversal.
- [ ] Line and sale cost and profit are left as they were sold. The sale is excluded from reports by its status.
      The figures stay for the audit trail and the voided-sales list.
- [ ] `settleProvisional()` skips allocations whose sale is voided. This is needed as a second guard for sales
      voided before this stage, because their allocations still exist.
- [ ] **A sale with a return that isn't rejected can't be voided** (decided): 422 with "This sale has returns.
      Process the return instead."
- [ ] **Cash on a void:**
  - **The sale's session is open:** its totals are recalculated (`updateSalesTotals()`), on web as well as API (web
    doesn't do this today), so the cash leaves its expected cash.
  - **The sale's session is closed:** it was counted and reconciled, so it is left as it is (no recalculation; its
    stored `expected_balance` and `variance` and its totals stay). The cash handed back comes out of today's drawer:
    a cash payout is recorded in the shop's current open session (`cash_payouts`: session, amount, reason, the sale,
    who), and `calculateExpectedBalance()` subtracts that session's payouts. With no open session in the shop, a
    `refund_due` issue is recorded instead (`source = void`, `method = cash`, step 5c).
  - Card and M-Pesa payments are never paid out of a drawer: they are a `refund_due` (step 5c).
- [ ] **Payouts on the session page:** a session lists its payouts (amount, reason, the sale if any, who). Someone
      holding `cash-registers.payout` (new: the owner and the manager template, through step 0's rollout) can record
      a payout by hand for petty cash, with a reason required; it lowers the open session's expected cash and is
      audited. Stage 14's cash refunds are payouts too.
- [ ] Tests:
  - void restores stock and batches, on web and API;
  - a voided sale no longer moves when stock arrives;
  - void refused while a return exists;
  - a cash sale voided while its session is open lowers that session's expected cash;
  - a cash sale voided after its session closed leaves that session's totals, expected balance and variance as they
    were, and records a payout in the shop's open session, whose expected cash drops by it;
  - the same with no open session records a `refund_due` (`source = void`, `method = cash`);
  - a manager records a petty-cash payout and the expected cash drops by it; a cashier without
    `cash-registers.payout` can't; a payout needs a reason;
  - the stores push is dispatched;
  - an audit row records the void.

### 5. Deleting a sale
- [ ] **A completed sale can't be deleted; it can only be voided** (decided). Web and API return 422
      with "Completed sales can't be deleted. Void the sale instead."
- [ ] A sale that isn't completed (draft or pending) can still be deleted. Before deleting, its lines are released
      as in step 3, since stock is taken at creation for every status.
- [ ] Remove or hide the "Delete" action for completed sales in the sales views. The server check is the guard.
- [ ] Tests:
  - deleting a completed sale is refused and changes nothing;
  - deleting a pending sale returns its stock, and then the sale is gone;
  - both on web and API.

### 5a. A Void button on the web sale page
- [ ] On `sales.show`, a **"Void sale"** button (`btn-outline-danger`, with an icon and its text, never icon-only).
      It is shown only when `@can('void', $sale)` passes: the permission, the time limit, no return, and not
      already voided.
- [ ] **When the button isn't available**, the page says why in one line instead:
  - "This sale can only be voided on the day it was made. Use a return instead." (end of day), or "Voiding closed
    :time ago (limit :minutes minutes). Use a return instead." (minutes);
  - "This sale has a return, so it can't be voided."; or
  - nothing, for people without the permission.
- [ ] **While the window is open**, a muted line under the button says until when: "Can be voided until 11:59 pm
      today." (end of day), or "Can be voided for another :minutes minutes." (minutes). Nothing is shown with no
      limit.
- [ ] **Clicking opens a confirmation window** (BS5 modal, the danger style for destructive actions). It contains:
  - **Title:** "Void this sale?"
  - **What happens**, as a short list:
    - "The sale is cancelled and no longer counts in sales, cost or profit reports.";
    - "All :count items go back into stock in :shop.";
    - "If it was a credit sale, the customer no longer owes for it.";
    - "The invoice number :invoice stays in the records, marked Voided.";
    - "This can't be undone."
  - **When to use it instead of a return:** "Use Void when the sale was a mistake (wrong item, price or quantity,
    payment that didn't go through, a duplicate) and the goods haven't left the shop. If the customer took the goods
    and brought them back, use a return instead."
  - **A required reason** (`void_reason`), with a `<label>` and server-side errors shown under it.
  - **Buttons:** "Cancel" and "Void sale" (`btn-danger`). The form has `@csrf` and `@honeypot`, and posts to
    `sales.void`.
- [ ] After voiding, the page shows a success message ("Sale voided. :count items are back in stock."), and a
      "Voided" badge with who voided it, when and why.
- [ ] **Accessibility:** the modal has a labelled title, focus moves to the reason field, Escape cancels, and the
      first error gets focus.
- [ ] **Tests:**
  - the button shows for a holder within the window;
  - it is hidden past the window, with the explanation shown;
  - it is hidden for a sale with a return, and for someone without the permission;
  - the modal text names the item count, shop and invoice;
  - posting without a reason fails with the error under the field;
  - a successful void shows the badge.

### 5b. Void automatically when an online order is cancelled before dispatch
- [ ] When the WooCommerce or Shopify order sync (webhook or pull) moves an order to `cancelled`, and the order
      has a converted sale that isn't voided, the sale is voided with `release()` and the credit reversal, as in
      step 4.
- [ ] **"Before dispatch"** means the store hadn't fulfilled the order when it was cancelled:
  - WooCommerce: the order's previous status was `pending`, `processing` or `on-hold`, not `completed`.
  - Shopify: `fulfillment_status` was not `fulfilled` or `partial`.
- [ ] **It's a system action:** it doesn't need `sales.void` and isn't held to the time limit, because the decision
      was taken in the store.
  - `voided_by` is NULL.
  - `void_reason` is "Order cancelled in :store (order :number)."
  - The audit row records it as automatic.
- [ ] **It is not voided automatically** when the order was already dispatched, or when the sale has a return.
      Instead the sale is flagged ("The online order was cancelled after dispatch. Check whether the goods came
      back, and process a return.") and the shop's staff are notified in the app's alerts.
- [ ] **Done once:** a repeated or late webhook for the same cancellation changes nothing (the sale is already
      voided). It runs for the order's business, in the queued job, with the tenant context set.
- [ ] **Tests:**
  - a cancelled `processing` WooCommerce order voids its sale and restores stock;
  - a cancelled `completed` order doesn't void, and flags the sale instead;
  - a Shopify order cancelled unfulfilled voids, and one cancelled fulfilled doesn't;
  - a sale with a return isn't voided;
  - the webhook twice gives one void;
  - the void doesn't push the stock change back to the store that cancelled it (the store sync's own
    `syncInProgress` guard).

### 5c. Voids from the till: `/api/v1` and offline
- [ ] **Online:** `POST /api/v1/sales/{uuid}/void` (`reason` required), through `SalePolicy::void` (permission,
      `VoidWindow` checked at the current time, no return, not already voided) and the same void action as the web
      (step 4). Refusals carry codes, added to `ApiErrorCode`: `void_window_closed`, `sale_has_returns`,
      `sale_already_voided`, and 403 `forbidden` without `sales.void`. A register-bound token reaches only its shop's
      sales. The sale records the till (`voided_register_id`).
- [ ] **Offline:** a new push item `sale_void` with its own `client_uuid` (`sale` uuid or `sale_client_uuid`,
      `reason`, `voided_by`). The till checks the person's `sales.void` from the staff list; the server checks it
      again. The time limit is judged by `VoidWindow` at the item's `recorded_at`, which must lie inside a lease the
      till was given. Released and audited as an online void, with the till (`voided_register_id`) and the recorded
      time (`void_recorded_at`; `voided_at` stays the server's time).
- [ ] **Order and a sale not yet on the server:** push items are applied in the order sent, so a void after its
      sale in the same push finds it. A `sale_void` whose sale is unknown (later in the same push, or not pushed
      yet) gets a new result status, `pending` ("not yet applied"). Nothing is kept, no receipt is written, and the
      till sends it again with its next push. It is not refused.
- [ ] **Once only, and races:**
  - the push's once-per-`client_uuid` rule applies: a void pushed twice is applied once and releases stock once;
  - a void for a sale already voided elsewhere (the web, another till, an online cancellation) is `applied` as a
    no-op, with `result.already_voided = true`, so the till clears it;
  - a void for a sale that has had a return since is `refused` (`sale_has_returns`) and goes on the till sync review
    as a new `void_refused` issue (the till, the person, the recorded time and the reason), so a manager sees the
    void that didn't happen.
- [ ] **Telling the tills:** the pull gains `sale_voids`, the shop's sales voided since the cursor (`uuid`,
      `client_uuid`, `voided_at`, `void_recorded_at`, `reason`), so every till of the shop marks them, wherever the
      void was made. The restored stock reaches the tills through the pull's `stock` section (step 3).
- [ ] **When voiding is possible:** `/api/v1` sale and receipt responses carry `voidable_until`: the end of the
      business's window for this sale, or null when the sale can't be voided (it has a return, is voided, or is not
      completed). It is about time and the sale's state only, so it is the same for everyone; the app combines it
      with the signed-in person's `sales.void` from the staff list to show or hide its Void button.
- [ ] **One refund issue type, `refund_due`**, on the till sync review, carrying a `source` (`void` or
      `late_payment`), the `method`, the amount and the payment reference:
  - `source = void`: voiding a sale with M-Pesa or card payments records one per payment, until stage 14 brings
    refunds; so does a cash void with no open session to pay out of (step 4);
  - `source = late_payment`: `TillPayments::apply()` refuses a voided sale, so a confirmed M-Pesa payment for it
    (an STK request still pending at the void, or a code matched later) is left unapplied and recorded as one.
- [ ] **A till that would lose unsynced work** (a reinstall, a lost device, signing out, re-pairing):
  - **The server knows what a till holds:** every push and pull sends `outbox_count` (unsynced items on the till);
    the server keeps it on the register (`outbox_count`, `outbox_reported_at`), shows it in the register list, and
    adds it to the overdue-sync warning ("Till R2 hasn't synced for 26 hours and holds 41 unsynced items").
  - **Re-pairing a till that holds unsynced work** is refused unless confirmed: with the old device's last
    `outbox_count` above 0, pairing answers 422 `till_has_unsynced_items` (the count and when it was reported)
    until sent again with `confirm = 1`. The confirmed re-pair is audited with the count, so the lost items are on
    record.
  - **A void whose sale is held** (refused and kept for a person, see "Every refusal has one class") waits behind it
    as `pending`. If a manager discards the sale, its queued void and payments are discarded with it, each with its
    own `outbox_item_discarded`.
  - **A void whose sale is gone** (the till lost it) is discarded the same way.
- [ ] **One discard for every stuck outbox item:** a new push item `outbox_item_discarded` (`item_type`: `sale`,
      `sale_void`, `sale_payment`, `customer`, `cash_session`, `till_sign_in`, `mpesa_code`, and any type a later
      stage adds; the discarded item's `client_uuid`; `reason`; `discarded_by`, who must hold `sales.void`). It is
      applied once per its own `client_uuid`, records an `outbox_item_discarded` issue on the till sync review (the
      till, the item type and uuid, the raw item as the till held it, the reason, the person) and an audit row, so
      nothing a till abandons goes unrecorded. A discard from someone without `sales.void` is refused (`forbidden`,
      drop). A discard of an item the server has already applied is applied as a no-op (`result.already_applied`), so
      the till clears it.
- [ ] **Every refusal has one class.** Each push result that isn't `applied` or `duplicate` gains `disposition`:
  - `retry` (transient): the till keeps the item and sends it again with the next push;
  - `drop` (final): the till removes the item and shows why;
  - `hold` (final, kept for a person): the till keeps the item, marked, until a manager **accepts** it on the server
    or **discards** it with `outbox_item_discarded`.

  Every code has exactly one class (the table in "Contract"). Phase 7's `validation_failed` for a customer not yet
  pushed, and `not_found` for a payment or cash-session event pushed before its sale or opening, are "push the other
  item first", not a fault: they become `customer_not_synced`, `sale_not_synced` and `cash_session_not_synced`
  (`ApiErrorCode`), status `pending`, disposition `retry`. `disposition` is an addition; the `status` of every
  other Phase 7 refusal is unchanged.
- [ ] **Accepting a held item** (decided): every held refusal records a `held_item` issue on the till sync review
      with the raw item, its code and message. Its resolutions:
  - **Accept** (`sync-issues.accept`, new: the owner and the manager template, through step 0's rollout): the server
    applies the item as the till sent it, through the same handler, with the fix the code needs:
    - **the till's invoice number is always kept** (decided): nothing is renumbered, so the printed receipt, the
      server and, later, eTIMS agree;
    - `invoice_number_taken`: after step 5d it can come only from a bug or a tampered till (a till's series is its
      own). It stays a hold. Its resolutions are "accept with the till's number", allowed only when that number is
      unique within the till's register's series (so it can't be the number already taken), or discard. Never
      renumbered;
    - `invalid_invoice_number` (a number not in the till's series): accept with the till's number when it is unique
      in the business, flagged `accepted_invalid_series`; otherwise discard;
    - `outside_trading_lease`: applied at the till's `recorded_at`, flagged (`accepted_outside_lease`);
    - `validation_failed`: resolved as the table below says, by the rule that failed; the screen offers exactly
      that.
    - `sale_already_paid` is no longer held (below).
  - **Discard** (`outbox_item_discarded`), with a reason, for items that shouldn't exist: `unknown_unit`,
    `unknown_item_type`, or a manager's judgement. A discard can come from the till (as above) or from the review
    screen, by someone holding `sales.void`.
  - The item's outcome is stored by its `client_uuid` (in `sync_push_receipts`, as an applied or discarded receipt),
    so the till's next push of it answers `applied` (with the server's result) or `discarded`, and the till clears it.
    The next pull also lists it in a new `resolved_items` section (`client_uuid`, `outcome`, `result`), so a till that
    no longer pushes it still clears it.
  - Audited either way; neither can be done twice (the issue's status and the receipt are checked under a lock).
- [ ] **Which validation errors can be corrected** (decided). A pushed sale passes through Phase 7's `SaleHandler`
      (lease, the customer by client uuid), `StoreSaleRequest` (its rules, the credit-customer check, the discount
      check, the variation check), `PushPeople` (the cashier) and `RecordSale`, checked 27 October 2026. Each rule is
      in exactly one group; the review screen offers exactly what the group allows:

  | Rule (where) | Group | On the review screen |
  |---|---|---|
  | `customer_id` exists (`StoreSaleRequest`) | Correctable | Pick an existing customer, or make it a walk-in sale |
  | Customer by `customer_client_uuid` never synced (`SaleHandler`; `customer_not_synced` while it may still come) | Correctable | Pick an existing customer |
  | `walk_in_customer_name` / `walk_in_customer_phone` required without a customer | Correctable | Enter them, or pick a customer |
  | Credit sale needs a customer; credit only for wholesale customers (`validateCreditCustomer`) | Correctable | Pick a wholesale customer |
  | `payment_method` in `cash, card, bank_transfer, mobile_money, credit` | Correctable | Pick a valid method |
  | `source_id` required, exists | Correctable | Pick a sale source |
  | `delivery_location` required | Correctable | Enter it ("In shop" offered) |
  | `delivery_company_id` exists | Correctable | Pick one, or clear it |
  | `customer_tax_pin` is a valid KRA PIN (`KraPin`) | Correctable | Correct it, or clear it |
  | `items.*.variation_id` required for a product with variations, and belongs to the product | Correctable | Pick the product's variation |
  | The cashier can work at this till (`PushPeople`) | Correctable | Pick a staff member of the shop |
  | `items.*.quantity` above 10,000; `items.*.price` above 1,000,000 | Accept as recorded, flagged | Accept: applied as sent, flagged `large_line` |
  | `delivery_fee`, `packaging_fee`, `other_expenses` above 10,000; `tax_amount` above 100,000 | Accept as recorded, flagged | Accept: applied as sent, flagged `large_amount` (for a VAT-registered shop `tax_amount` is ignored anyway) |
  | `walk_in_customer_email` not an email | Accept as recorded, flagged | Accept: applied with the email left out, flagged `bad_email` |
  | Text over its length (`delivery_location` 1,000, `notes` 1,000, `expense_notes` 500, walk-in name 255, phone 20) | Accept as recorded, flagged | Accept: applied with the text cut to the column, flagged `text_shortened` |
  | `items` missing or empty | Not acceptable | Discard only |
  | `items.*.product_id` missing or not a product of the business | Not acceptable | Discard only (products are made only on the server) |
  | `items.*.quantity` below 1; `items.*.price` negative; `discount_amount` negative | Not acceptable | Discard only |
  | `discount_amount` above the lines' subtotal (`validateDiscount`) | Not acceptable | Discard only (the receipt showed a negative total; the sale is rung up again on the web) |
  | `shop_id` | Not applied to till sales | A till's sale is always its shop's |

  Stage 11 adds `fractional_quantity` (accepted on the push, flagged) and `unknown_unit` (discard only); stage 11b's
  price checks never refuse a pushed sale.
- [ ] **The flags** are recorded on the sale (`sales.sync_flags`, JSON) and on the issue, and shown on the sale page.
- [ ] **A payment pushed for a sale already paid** (decided): `PaymentHandler` records a `refund_due` with
      `source = duplicate_payment` (amount, method, reference, the sale) and answers `refused` with
      `sale_already_paid`, disposition **drop**, so the till clears it. The money is on the review as owed back.
- [ ] **The app's version** (decided): every push, pull and pairing request sends `X-App-Version` (semantic version).
      Phase 7 sends none. The server stores the latest per register (`registers.app_version`,
      `app_version_reported_at`) and shows it in the register list. A request without the header is treated as an app
      before this stage.
- [ ] **Old apps get the codes they know** (decided): `App\Support\Sync\LegacyRefusals` rewrites a push result for an
      app older than this stage's minimum version (`config('sync.min_version.dispositions')`): `customer_not_synced`
      → `validation_failed`, `sale_not_synced` and `cash_session_not_synced` → `not_found`, status `pending` →
      `refused`, and no `disposition`. A `sale_void` or `outbox_item_discarded` can't come from such an app. The
      minimum version for each feature is in "Contract".
- [ ] **Housekeeping:** the route gets its rows in `TenantIsolation/ResourceMatrixTest` (another business's sale is a
      404; a till of another shop is a 404). The v1 contract test records only sign-in and parameter-free GETs, so
      a new `ApiV1VoidContractTest` pins the shape and status of each refusal (`void_window_closed`,
      `sale_has_returns`, `sale_already_voided`, 403) and of a successful void.
- [ ] Tests:
  - an online and an offline void release stock as the web's does;
  - a sale and its void both recorded offline on Monday and pushed on Tuesday are accepted; a void recorded on
    Tuesday for Monday's sale is refused `void_window_closed`;
  - a person without `sales.void` is refused on push even if the till let them;
  - a void pushed before its sale is `pending`, and applied when the push is repeated after the sale arrives;
  - a void pushed twice releases stock once;
  - a void for a sale already voided on the web is applied as a no-op and the till clears it;
  - a void for a sale returned since is refused and on the review as `void_refused`;
  - another till of the shop pulls the void and the restored stock;
  - `voidable_until` is the same for a cashier and the owner, and null for a sale with a return;
  - a confirmed M-Pesa payment for a voided sale doesn't apply and is a `refund_due` with `source = late_payment`;
  - voiding a sale paid by M-Pesa is a `refund_due` with `source = void`, the method and the reference;
  - another business's sale is a 404;
  - `outbox_count` is stored from a push and a pull, shown in the register list, and named in the overdue warning;
  - re-pairing a till that last reported unsynced items is refused without `confirm`, and audited with the count
    when confirmed; a till that reported 0 re-pairs without asking;
  - an `outbox_item_discarded` for a held sale, for a void and for a payment is each audited and on the review; one
    from a person without `sales.void` is refused with `drop`; one for an item already applied is a no-op;
  - every refusal carries the `disposition` the "Contract" table gives its code; a payment pushed before its sale is
    `pending` with `sale_not_synced` and `retry`, and is applied when pushed again after the sale;
  - an accepted held sale keeps the till's invoice number; an `invoice_number_taken` sale can be accepted only when
    its number is unique in its register's series, and is never renumbered; the till's next push of it answers
    `applied`, so the till clears it; the next pull lists it in `resolved_items`; it can't be accepted twice;
  - **one rule from each group:** a sale whose customer doesn't exist is corrected by picking a customer, then
    accepted; a sale with a 12,000 delivery fee is accepted as recorded and flagged `large_amount`; a sale with a
    discount above its subtotal can only be discarded;
  - an accepted `outside_trading_lease` sale is applied at its recorded time and flagged;
  - a discard from the review screen is audited, and can't be done twice; an accept by someone without
    `sync-issues.accept` is refused;
  - an M-Pesa payment pushed twice for one sale applies once and records one `refund_due` (`source =
    duplicate_payment`), and the second answers `drop`;
  - `X-App-Version` is stored on the register; **a contract test replays a push as an app before this stage** (no
    header) and gets Phase 7's codes and shapes (`validation_failed`, `not_found`, `refused`, no `disposition`).

### 5d. Invoice series: tills and the web never share one
- [ ] **What Phase 7 does today** (checked 27 October 2026): `InvoiceNumberService` keeps one series per register
      and year (`invoice_sequences`, series key `r{register}:{year}`), formatted `INV-{shop}-{register}-{year}-{n}`;
      `next()` claims the next number under a row lock; `adopt()` takes a till's offline number when it is in that
      register's series, moves the series on past it and records gaps. **Every sale path numbers from its cash
      session's register:** the web POS (`SaleController::store`), `RecordSale` (flat API and push), online orders and
      abandoned carts. Each shop has one register (`R1`), so web sales and a paired till's offline sales draw on the
      same series, and a web sale can take a number the till has already printed offline.
- [ ] **Tills keep their register's series, unchanged in form** (`INV-{shop}-{register}-{year}-{n}`, e.g.
      `INV-NRB-R2-2026-000145`): a till numbers only from its own register, so two tills issuing the same sequence
      number differ by register code. The year stays in the number (today's form, which the app in shops already
      produces).
- [ ] **Web and online sales take a per-shop web series:** `INV-{shop}-W-{year}-{n}` (series key `w{shop}:{year}`),
      claimed by `InvoiceNumberService::nextWeb($shop)`, used by the web POS, the flat API (`RecordSale` without a till
      context), online orders and abandoned carts. The cash session still belongs to its register; only the number's
      series changes. **`W` can never be a register code** (register codes are validated `R` + digits; `W` is
      reserved), so a web number and a till number can't collide.
- [ ] **Old apps keep working, without clashes:** the app in shops sells through the flat API, which the server
      numbers, so its new sales take web numbers; a till app that numbers offline (Phase 7's contract) numbers in its
      register's series, which no server path draws on any more. No app change is needed; the series a register
      issues is told to the till at pairing as today (`last_invoice_number`). `adopt()` refuses a number in the web
      series (`invalid_invoice_number`). Whether the app treats the number as an opaque string is an app check
      ([app-release-checklist.md](./app-release-checklist.md#stage-10)).
- [ ] **Existing sales keep their numbers.** Only new numbers use the web series. UAT (read-only, 27 October 2026):
      1,503 sales, all in their shop's `R1` series but one (not `INV-…`); one register per shop; no paired till.
      `invoices:report-series --business=<id>` (read-only) counts numbers per shop and series before and after.
- [ ] Tests: two tills of one shop issue sequence number 145 and both are recorded (`…-R1-…-000145`,
      `…-R2-…-000145`); an accepted held sale keeps its number; a web sale and a till sale made at the same moment
      can't collide (`W` vs `R1`); a till can't adopt a web-series number; an old-app sale through the flat API and a
      till sale in the register series can't collide; existing numbers are untouched.

### 6. Mark lines whose cost isn't fully known
- [ ] Migration: `sale_items.cost_known` boolean, default true. It records whether every unit was costed from a
      batch with a cost, or from a known estimate.
- [ ] `sellItem()` sets it from `StockChange::$costKnown`. `settleProvisional()` and `release()` recompute it from
      the line's remaining allocations (stage 12's `recostLayer()` does too).
- [ ] When settling against a batch with no cost, **keep the provisional estimate** as the allocation's cost and
      mark the line `cost_known = false`, rather than replacing the estimate with NULL (decided).
- [ ] `Sale::scopeCostIncomplete()` (sales with any line not known). Show a "cost incomplete" badge on the sale
      page and in the profit report, linking to Products → Purchase Costs (stage 12 links to the GRN waiting for
      costs when the batch came from one).
- [ ] Backfill in the migration: `cost_known = false` for lines with an allocation whose `unit_cost` is NULL.
      Count the lines before and after.
- [ ] Tests: a line from a cost-unknown batch is marked; entering the cost and re-costing clears it (with
      `sales:recompute-profit`); the badge shows.

### 7. Repair sales voided and deleted before this stage
- [ ] `php artisan sales:restock-voided --business=<id>` (`RunsForBusiness`), kept for the UAT businesses' voided
      sales (none on 27 October 2026; any voided before this stage is deployed). For every voided sale whose lines
      still have allocations, it runs `release()` (dry run by default; `--apply` writes). It reports per sale: the
      lines, the units, the shops.
- [ ] `php artisan stock:orphaned-movements --business=<id>` (read-only) lists `SALE` movements whose sale no
      longer exists, so the business can correct stock by hand for sales deleted before this stage. Their
      allocations are gone, so nothing can be released automatically.
- [ ] **Old voids keep their money as it was:** `sales:restock-voided` gives back stock only. It records no payouts
      and no `refund_due` issues: those sales' sessions were counted at the time. The report says so.
- [ ] `sales:report-cross-shop-lines` is **not** built (removed 27 October 2026; step 1a).
- [ ] Tests on fixtures: an old voided sale is restored once (a second `--apply` changes nothing), with no payout or
      issue recorded. The orphaned list finds a deleted sale's movements.

### 8. Housekeeping
- [ ] Note in [pos-domain-rules](../../../.claude/skills/pos-domain-rules/SKILL.md) and CLAUDE.md:
  - the selling-shop rule;
  - voids release stock;
  - only `sales.void` voids, within the business's time limit;
  - completed sales can't be deleted;
  - `cost_known`;
  - clerks hold no cost permission, and the flat API hides costs without `products.view-cost`;
  - new permissions go in `PermissionSeeder` and the role grants, and get a rollout test; removals from roles are
    recorded in `role_revoked_permissions`; `permissions:sync` runs on deploy.
- [ ] `CostingService`, `CostingMethod` and `sale_item_stock_allocations` are removed in stage 11 (decided 27 October
      2026), not here.
- [ ] **`docs/flutter/09-products.md`, "Create Product" and "Update Product"** (the behaviour step 1f changes). Exact
      correction:
  - validation table: `sku` becomes "nullable, string, max:100, unique in the business"; `category_id` becomes
    "nullable, exists:categories,id"; `cost_price` becomes "nullable, numeric, min:0; saved only for someone holding
    `products.set-cost`, otherwise the product is created with an unknown cost"; `status` becomes "nullable, in:
    `active`, `inactive`, `out_of_stock`, `discontinued`" (stage 11 removes `out_of_stock`); the rows `has_variations`,
    `variations`, `variations.*.name`, `.sku`, `.barcode`, `.cost_price`, `.selling_price`, `.wholesale_price`,
    `.stock_quantity` and `.attributes` are removed;
  - the request example loses `has_variations` and `variations`;
  - a paragraph is added under the table: "Products with variations are created on the web. Sending `variations`, or
    `has_variations: true`, answers 422 and creates nothing. Creating, editing and deleting a product need
    `products.create`, `products.update` and `products.delete` (403 otherwise); the app creates products only
    online.";
  - "Show Product" and "List Products": "`cost_price` is `null` for someone without `products.view-cost` or
    `products.set-cost`; the key is always present."
- [ ] **Note on `docs/implementation/21-stock-adjustments.md`**, under its title, once step 1g ships (why: the module
      is the pre-SaaS design, and its "requires approval" rule and "complete" step no longer describe the code):
      > **Superseded (stage 10, step 1g).** Built, with bugs fixed in stage 10: adjustments only moved the default
      > variation, recorded their figures at draft time, let the type and reason disagree, needed approval for every
      > reason (the `requiresApproval()` below is never called) and then a separate "complete", and used random
      > numbers. Approval now needs a second person for loss, theft, damage, expiry or above a threshold; "returned to
      > supplier" is a purchase return. Batches, costs and units on adjustments are in stage 11; counts are stocktakes,
      > stage 13. Every finding and where it went: [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).
- [ ] **PROGRESS.md:** when this stage starts, its section is added, with a "Planned" table listing stages 11 to 15
      (file, branch, depends on, status), as the definition of done requires.

## Contract (paired app changes)

The Flutter app is a separate release. This stage's server changes are additions, so the app that is in shops keeps
working; the paired app version adds:
- **Void at the till:** a Void button shown when `voidable_until` is in the future and the signed-in person holds
  `sales.void` (from the staff list); online it calls `POST /api/v1/sales/{uuid}/void`, offline it queues a
  `sale_void` push item with its own `client_uuid` and `recorded_at`.
- **Push results:** follow `disposition` (below): `retry` sends the item again with the next push; `drop` removes it
  and shows why; `hold` keeps it, marked, until a manager discards it. `applied` with `already_voided` (or
  `already_applied` for a discard) clears the item.
- **Pull:** mark the sales listed in `sale_voids` as voided; restored stock arrives in `stock` as usual.
- **Refusal codes** on the online void: `void_window_closed`, `sale_has_returns`, `sale_already_voided`, 403.
- **Delete:** a completed sale can't be deleted (422); the app hides Delete for completed sales (step 5).
- **Unsynced work:** send `outbox_count` with every push and pull; never delete a business's local store while it
  holds unsynced items; warn, and require a confirmation, before signing out or switching business with unsynced
  items.
- **Held items:** a held sale keeps its queued void and payments behind it. A manager can discard a held or stuck
  item on the till (holding `sales.void`, with a reason): the app pushes `outbox_item_discarded` for it and for each
  item queued behind it. An item accepted or discarded on the server comes back `applied` or `discarded` on its next
  push, and in the pull's `resolved_items`; the app clears it either way.
- **Version:** send `X-App-Version` on every push, pull and pairing request.
- **Products:** create products only online, for someone holding `products.create`; never offer product creation
  offline; an unknown barcode offline can't be sold.
- **Re-pairing:** on 422 `till_has_unsynced_items`, show the count and ask the manager to confirm.
- **Products on the flat API:** `cost_price` is `null` for people without `products.view-cost`; creating, editing
  and deleting products need the product permissions (403 otherwise); a cost change without `products.set-cost`
  is a 403. The app should hide cost fields and product editing by the permissions it reads.
- The flat API keeps its shape; its void and delete now need the permission (step 2).

**Every push refusal and its class** (the codes in Phase 7's `SyncPush` and handlers, checked 27 October 2026, and
those this stage, 11 and 11b add). The app follows `disposition`; an app before this stage reads `status` and
`code` as it does today.

| Code | Item types | Status | Class | Notes |
|---|---|---|---|---|
| (none) | `sale_void` whose sale is unknown | `pending` | retry | Stage 10 |
| `customer_not_synced` (was `validation_failed`) | `sale` | `pending` | retry | Push the customer first |
| `sale_not_synced` (was `not_found`) | `sale_payment` | `pending` | retry | Push the sale first |
| `cash_session_not_synced` (was `not_found`) | `cash_session` | `pending` | retry | Push the opening first |
| `subscription_inactive` | every item recorded after a lapse | `refused` | retry | Phase 7's message already says "keep this on the till and sync again once it is [paid]"; it clears when the business pays, so it isn't held for a person |
| `server_error` | any | `failed` | retry | Phase 7 |
| `void_window_closed` | `sale_void` | `refused` | drop | Shown to the person; stage 10 |
| `sale_has_returns` | `sale_void` | `refused` | drop | Also a `void_refused` issue; stage 10 |
| `forbidden` | `sale_void`, `till_sign_in` (`unlocked_by` may not unlock), `outbox_item_discarded` | `refused` | drop | The action wasn't allowed; nothing to keep |
| `outside_trading_lease` | `sale` | `refused` | hold | Phase 7 |
| `invalid_invoice_number` | `sale` | `refused` | hold | Phase 7 |
| `invoice_number_taken` | `sale` | `refused` | hold | Phase 7; recorded as a repeat. After step 5d only a bug or a tampered till; accepted only with a number unique in its register's series, else discarded; never renumbered |
| `validation_failed` | any (other faults, including a person who can't work at this till) | `refused` | hold | Phase 7 |
| `sale_already_paid` | `sale_payment` | `refused` | drop | Recorded as a `refund_due` (`source = duplicate_payment`); nothing for the till to keep |
| `unknown_item_type` | any | `refused` | hold | An app newer than the server; held, not resent in a loop |
| `unknown_unit` | `sale` | `refused` | hold | Stage 11 |

`price_below_expected` and `discount_above_limit` (stage 11b) are online refusals only: from the push, the sale is
recorded as charged and reviewed. Held items are resolved by **accept** or **discard** (step 5c).

**For an app older than this stage** (no `X-App-Version`, or below the minimum), `LegacyRefusals` sends Phase 7's
codes: `customer_not_synced` → `validation_failed`, `sale_not_synced` and `cash_session_not_synced` → `not_found`,
status `refused`, no `disposition`.

**Invoice numbers:** a till numbers in its register's series, as today; server-numbered sales (web, flat API, online)
take the shop's web series (step 5d). Nothing renumbers a printed number.

**The paired app version is not scheduled.** The server stays backward compatible with the app in shops: additions
only, old fields kept, Phase 7's codes for older versions. What the app's release must check is in
[app-release-checklist.md](./app-release-checklist.md#stage-10); nothing here assumes how the app behaves.

**Minimum app version for each feature** (set in `config/sync.php` when the paired app is released; the server
checks it, never the app's own claim of a feature):

| Feature | Minimum app version |
|---|---|
| `disposition`, the `*_not_synced` codes, `pending` on payments | The stage 10 app release |
| `sale_void`, `outbox_item_discarded`, `sale_voids`, `resolved_items`, `voidable_until` | The stage 10 app release |
| Units, `base_quantity` (stage 11); price and discount approvals (stage 11b) | The stage 11 and 11b app release |

## Schema

| Change | Data carried across |
|---|---|
| `role_revoked_permissions` (new) | Filled by `roles:backfill-rollout` (absences outside Phase 5 and 7 kept as revocations) and by the revoking migrations |
| `stock_adjustment_items.variation_id` (foreign key) | Backfilled with the product's default variation (what the code moved); counts before and after |
| `stock_adjustments.adjustment_number`: unique per business; new numbers from a series per shop and year | Existing numbers kept |
| `sale_items.cost_known` boolean, default true | Backfilled false where an allocation has no cost |
| `sales.void` revoked from the manager template and its copies (data migration) | Counts before and after; custom roles untouched; revocations recorded |
| `products.set-cost` and `products.view-cost` revoked from the inventory-clerk template and its copies (data migration) | Counts before and after; custom roles untouched; revocations recorded |
| `businesses.settings['sales']['void_window']` (no column): `end_of_day`, minutes (1–1,440) or `none` | Missing means the default, `end_of_day` |
| `sales.voided_register_id` (nullable, foreign key to `registers`), `sales.void_recorded_at` (nullable: when the till recorded the void; `voided_at` stays the server's time) | None; past voids have neither |
| `cash_payouts` (new): session, shop, business, amount, reason, sale (nullable), `paid_by`, timestamps | None |
| `registers.outbox_count` (unsigned int, nullable), `registers.outbox_reported_at` (nullable) | None; filled by the next push or pull |
| Permission `cash-registers.payout` | Granted to the owner and the manager template through step 0's rollout |
| `StockMovementType::SALE_VOID`, `CostLayer::SOURCE_RELEASE` | None (constants) |
| `ApiErrorCode` cases: `void_window_closed`, `sale_has_returns`, `sale_already_voided`, `till_has_unsynced_items` | None (an enum) |
| `sync_issues` types: `refund_due` (with `source`, `method`, amount, reference), `void_refused`, `outbox_item_discarded` | None (a string column) |
| `ApiErrorCode` cases: `customer_not_synced`, `sale_not_synced`, `cash_session_not_synced` | None (an enum); they replace three uses of `validation_failed` / `not_found` on the push |
| `registers.app_version`, `registers.app_version_reported_at` (nullable) | None; filled by the next push or pull |
| Permission `sync-issues.accept` | Granted to the owner and the manager template through step 0's rollout |
| `sync_issues` type `held_item`; `refund_due` source `duplicate_payment`; `sales.sync_flags` (JSON, nullable) | None |
| Invoice series: web series `w{shop}:{year}` in `invoice_sequences`; register code `W` reserved | Existing sales keep their numbers; `invoices:report-series` counts per shop and series |
| `businesses.settings['stock']['adjustment_approval_threshold']` (no column; default 5,000) | Missing means the default |
| `AdjustmentReason::RETURNED_TO_SUPPLIER` no longer offered for new adjustments | Existing rows kept as history (none on UAT) |

**The sync contract changes (step 5c):** the push gains the items `sale_void` and `outbox_item_discarded`, the
result status `pending` and the result field `disposition`; held items can be accepted, and the pull gains
`resolved_items`; requests carry `X-App-Version`, and older apps get Phase 7's codes; three "push the other item first" refusals get their own
codes; push and pull requests gain `outbox_count`; pairing gains `confirm` and the refusal
`till_has_unsynced_items`; the pull gains the section `sale_voids`; `/api/v1` sale and receipt responses gain
`voidable_until`. All are additions: an older app ignores what it doesn't know (see "Contract").

`release()` writes existing tables, through `StockLedger::changeStock()`. Quantities stay whole units in this stage;
stage 11 makes them decimal.

## Acceptance criteria

**Permissions (steps 0 and 1f)**
- [ ] A business created before a permission was granted to its template gets it on the next `permissions:sync`.
- [ ] A permission a business or the platform console took off a role stays off after `permissions:sync`.
- [ ] Existing businesses have the Phase 5 and 7 permissions after the backfill; other absences are kept.
- [ ] The deploy runs `permissions:sync` after migrations.
- [ ] A clerk never sees costs and can't set them, on the web or the flat API; the cashier and clerk templates hold
      no cost permission; a role the owner gives a cost permission can.
- [ ] The flat API's product endpoints need the product permissions; `cost_price` is `null` without
      `products.view-cost`, with the key kept.
- [ ] The sync pull carries no cost field.

**Selling shop (step 1a)**
- [ ] A sale at shop B takes shop B's stock and batches, whichever shop the product was first added in, on every
      sale path; a till's sale is filed under the till's shop. The flat API's responses are unchanged.
- [ ] Selling at a shop with no `shop_variation` row works and creates it.
- [ ] A product that doesn't track stock moves no stock.
- [ ] Reports count sales under the shop that made them.

**Stock adjustments (step 1g)**
- [ ] An adjustment moves the variation it names; its figures are those at approval; the reason decides the
      direction; approving applies it; numbers are a series per shop.
- [ ] The 16 approved-but-unapplied UAT adjustments are listed for the owner, not applied automatically.
- [ ] Loss, theft, damage, expiry and adjustments above the threshold need a second person; the creator never
      approves their own; "returned to supplier" is no longer an adjustment.
- [ ] Products are created from the app only online by holders of `products.create`; fields the API doesn't take are
      refused, not dropped.

**Voids**
- [ ] Voiding a sale of 5 units taken from two batches (3 at 60, 2 at 70) puts 3 back in the first batch and 2 in
      the second. Stock rises by 5, and the next sale is costed at 60 again.
- [ ] Voiding a sale sold below zero raises stock and removes its provisional allocation. Stock arriving later
      doesn't re-cost the voided sale.
- [ ] Voiding twice is refused, and stock moves only once.
- [ ] A sale with a return can't be voided.
- [ ] The invariant holds after every case, on SQLite and MySQL.

**Deletes**
- [ ] A completed sale can't be deleted on web or API.
- [ ] Deleting a pending sale returns its stock first.

**Web Void button**
- [ ] The sale page shows a clearly labelled "Void sale" button within the window. Its confirmation explains what
      voiding does and when to use a return instead, and needs a reason.
- [ ] Past the window, or with a return, the page explains why the button isn't there.

**Online cancellations**
- [ ] An online order cancelled before dispatch voids its sale once and restores stock. One cancelled after
      dispatch flags the sale for a return instead.

**Voids from the till (step 5c)**
- [ ] A till voids a sale through `/api/v1`, and offline through the push, under the same permission, time limit
      and rules as the web; an offline void is judged by its `recorded_at`, and refused if the person may not void.
- [ ] A void pushed before its sale is `pending` and applied once the sale arrives; a void pushed twice releases
      stock once; a void of a sale voided elsewhere is a no-op; a void of a sale returned since is refused and on
      the review.
- [ ] Every till of the shop learns of a void, wherever it was made, and gets the restored stock, on its next pull.
- [ ] `voidable_until` is the same for everyone and null when the sale can't be voided.
- [ ] A confirmed M-Pesa payment never brings a voided sale back; it is a `refund_due` (`source = late_payment`).
- [ ] Voiding a sale paid by M-Pesa or card is a `refund_due` (`source = void`) with the method and reference.
- [ ] A void after the sale's cash session closed leaves that session as counted, and pays the cash out of the
      shop's open session (or records a `refund_due` when none is open).
- [ ] The server knows how many unsynced items each till holds; re-pairing a till that holds some needs a
      confirmation and is audited; any held or stuck outbox item can be discarded on the till, and that is reviewed
      and audited.
- [ ] Every push refusal carries a `disposition`, and each code has exactly one class.
- [ ] A held item can be accepted (with a new invoice number where needed) or discarded on the review, once, audited,
      and the till clears it; a duplicate payment becomes a `refund_due` and is dropped.
- [ ] Every request carries `X-App-Version`; an older app gets Phase 7's codes and shapes (the replay test).
- [ ] A manager records a petty-cash payout with a reason; it lowers the session's expected cash.

**Permissions and time limit**
- [ ] Void and delete through the API need `sales.void` / `sales.delete`, as on the web.
- [ ] By default only the owner can void. A role the owner gives `sales.void` can void. `sales.edit-all` alone
      can't. `permissions:sync` doesn't give it back to managers.
- [ ] By default a sale can be voided until the end of the day it was made, in the shop's timezone, and not after,
      for the owner too. A business's own number of minutes (up to 24 hours), or "No time limit", applies to that
      business only. Anyone with `settings.update` can change it.

**Costs**
- [ ] A line costed from a batch with no cost is marked `cost_known = false` and flagged on the sale page and the
      profit report.
- [ ] Settling below-zero sales against a batch with no cost keeps the estimate.

**Repair**
- [ ] `sales:restock-voided` restores old voided sales once, with a dry run first.
- [ ] `stock:orphaned-movements` lists movements of deleted sales.

**Contract and checks**
- [ ] The flat API contract test passes. A void, delete or product refusal is a new 403/422; how the app shows it is
      an app check (the checklist).
- [ ] Tills and the web never share an invoice series; an accepted held sale keeps its number; existing numbers are
      untouched.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass. New routes or commands appear in
      `ResourceMatrixTest` where they apply, `POST /api/v1/sales/{uuid}/void` included.
- [ ] `ApiV1VoidContractTest` pins the online void's success and refusals (`void_window_closed`, `sale_has_returns`,
      `sale_already_voided`, 403); the v1 contract test passes with its fixtures unchanged.
- [ ] The app's recorded requests (pushes and pulls without step 5c, and the flat API calls in the contract
      fixtures) still succeed against the new server.

## Risks

- **The selling-shop fix changes whose stock moves.** Past sales stay as they were. Stock counts per shop are right
  from the deploy on.
- **The rollout backfill can't tell old removals from misses.** Outside the Phase 5 and 7 permissions, it treats
  every absence as deliberate. A role that missed an older permission by the same bug stays without it; the
  backfill's report lists those absences so the owner can give them back.
- **Authorising the flat API's product endpoints refuses what the app may do today** (a cashier adding a product at
  the till). Decided: cashiers don't create products. The refusal is a 403 the app shows as a message; the release
  note says so.
- **A taken invoice number can't be renumbered**, so a till bug that reuses numbers leaves sales that can only be
  accepted when unique or discarded. Mitigation: after step 5d no server path draws on a till's series, so a repeat
  means a bug or tampering, which the review shows.
- **Server-numbered sales change number form** (`W` instead of `R1`). Mitigation: the form is a string; old numbers
  stay; whether the app relies on the form is an app check.
- **An app can claim any version.** The version only selects which codes the server sends; it grants nothing.
- **Second-person approval slows small shops** where the owner works alone. Mitigation: the threshold is per business;
  only loss, theft, damage and expiry always need a second person, and a business can give
  `stock-adjustments.approve` to a trusted employee.
- **Concurrency:** a void racing a sale of the same variation. Mitigation: `release()` locks the `shop_variation`
  row first, in the same order as `consume()`, so they can't deadlock.
- **Repairing old voids changes stock figures shops have been working with.** Mitigation: a dry run listing every
  change, run on a UAT snapshot in `rehearsal_testing` first, applied per business by the owner.
- **Managers lose voiding in every business on deploy, and clerks lose costs.** Owners must give `sales.void` (or a
  cost permission) back to roles that need it. Mitigation: the release note says so, and the migrations list, per
  business, the roles they changed.
- **Offline voids judged by the till's clock.** A till's recorded time decides whether a void is inside the
  window. The lease bounds how far the clock can drift, and an implausible time is flagged as it is for sales.
- **A void waiting for a sale that never arrives.** A `pending` void is sent again with every push until its sale
  arrives. If its sale is held or gone from the till, a manager discards both (`outbox_item_discarded`), which is
  reviewed and audited.
- **New codes for three Phase 7 refusals.** An app before this stage sees `customer_not_synced`, `sale_not_synced` or
  `cash_session_not_synced` with status `pending` where it saw `validation_failed` / `not_found` with `refused`.
  Mitigation: `LegacyRefusals` sends Phase 7's codes to an app without `X-App-Version` (the replay test); the new
  app's handling is an app check.
- **Unsynced work lost by a reinstall or a lost device.** The server can't recover it, but it knows the till held
  it (`outbox_count`), warns before a re-pair strands it, and records the count when a re-pair is confirmed.
- **Cash paid out of a different session.** A void after its session closed takes the cash from today's drawer, so
  today's expected cash includes a payout for a sale of another day. The payout names the sale, and the closed
  session's report stays as it was counted.
- **Sales made just before midnight have little time to be voided.** Mitigation: the next day they go through a
  return, and a business can choose a number of minutes instead (up to 24 hours) or no limit.
- **Timezones:** counting the day in UTC would close Nairobi sales at 03:00 local time. Mitigation: the window is
  always worked out in the shop's timezone, and tested across midnight.
- **The app may offer "Delete" on completed sales.** The server refuses with 422; the app's handling is an app check.

## Rollout (UAT)

1. Take a snapshot of UAT (`db:snapshot`).
2. Restore it into `rehearsal_testing` and run the migrations there. Check the `cost_known` backfill counts and the
   revocation counts (manager copies losing `sales.void`, clerk copies losing both cost permissions).
3. `roles:backfill-rollout --business=<id>` on the rehearsal (dry run, then `--apply`). Check the report: the
   Phase 5 and 7 permissions granted, other absences recorded.
4. `permissions:sync` on the rehearsal. Check a manager copy has `cash-registers.payout` and not `sales.void`, and a
   clerk copy has no cost permission.
5. `sales:restock-voided --business=<id>` on the rehearsal (dry run, then `--apply`). Compare stock per variation
   before and after. (None to restock on 27 October 2026.)
6. `stock-adjustments:list-unapplied --business=<id>` on the rehearsal (read-only): 16 expected; with
   `--reason=returned_to_supplier`: none expected. `invoices:report-series --business=<id>` (read-only): 1,503 numbers
   in `R1` series and one other, unchanged after the migrations.
7. The user runs the migrations, the backfill, `permissions:sync` and the repair on UAT, then
   `stock-adjustments:list-unapplied` for each business. **The owner applies or rejects each listed adjustment on its
   page**, through the one-step approval (a second person where step 1g requires one). Nothing is applied by a
   command.
8. **Server before app:** deploy the server first. Every sync change is an addition, so the app in shops ignores
   `sale_voids` and `voidable_until`, and never sends `sale_void`.
9. Check the server, on the rehearsal, against the app's recorded requests: the Phase 7 sync tests run unchanged;
   the recorded pushes and pulls, and the flat API contract fixtures, succeed; the replay test (no `X-App-Version`)
   gets Phase 7's codes. How the app itself behaves is checked in its own release
   ([app-release-checklist.md](./app-release-checklist.md#stage-10)), whose date isn't set.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push
checks (Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing remains open. The decisions of 26 and 27 October 2026 stand (see "Decisions that apply"), including the fourth
round's answers on products from the app, adjustment approval and returns to the supplier.

## Follow-ups (not this stage)

- **The server checks prices** in [stage 11b](./11b-prices-and-discounts.md): it needs
  `PriceResolver`, which this stage doesn't have. Until then the server records the price a till or the web POS
  sends.
- Use the `sales.deleted_at` column (soft deletes) or drop it. `VatReportService` filters on it today, though it's
  never set.
