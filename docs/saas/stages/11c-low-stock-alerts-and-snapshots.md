# Stage: Low Stock, Alerts and Snapshots

> **Source:** split from stage 11 on 27 October 2026 (sixth round); legacy design review of modules 09 and 20, and the
> second to fourth rounds of 27 October 2026.
> **Branch:** `feat/low-stock-and-snapshots`, from `main` once stage 11 is merged · **Depends on:**
> [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) (decimal quantities, units and
> `UnitBreakdown`, the `Expiry` helper and the expiry touch, `UnitPrice`, the pull's stock fields)
> · **Runs in either order with:** [11b-prices-and-discounts.md](./11b-prices-and-discounts.md) · **Next:**
> [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) (adds On order and In transit to this stage's
> page) · **Later:** [18-alerts-and-notifications.md](./18-alerts-and-notifications.md) (one alert engine, built on
> this stage's self-clearing system alerts) · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-11c) · **Traceability:**
> [TRACEABILITY-stock.md](./TRACEABILITY-stock.md)

---

## Revisions

**Split from stage 11 (27 October 2026, sixth round).** This stage takes low stock (one query, one page, everything
else moved onto it), alerts that clear themselves with `source = system`, per-shop reorder levels, expiring-stock
alerts, and nightly inventory snapshots with retention. Decided in the sixth round: **a missed snapshot is always
rebuilt exactly from the ledger** (step 4); "current figures, marked late" only for dates before a business's ledger
began.

---

## Goal

"Is this low?" has one answer, per shop, in base units, on stock that can actually be sold. Alerts appear when it is
low or expiring, and clear themselves when it isn't. Every shop has a stock snapshot for every night, so the stock's
quantity and FIFO value on any past date can be read back, even when the scheduler missed a night.

## Before you start

Stage 11 must be merged. Nothing remains open (see "Points to confirm"). One release, additive.

## Decisions that apply

- **Low stock has one definition (decided 27 October 2026, third round):** per shop, in base units, sellable
  (unexpired) stock of tracked products against the shop's reorder level, else the product's; one query behind one
  page, the product filter, the dashboard tile and the alerts.
- **System runs name no user (decided 27 October 2026, fourth round):** alerts raised by the system have `created_by`
  NULL and `source = system`.
- **Nightly snapshots (decided 27 October 2026, fourth round):** one per shop at its local midnight, caught up from a
  last-run time; daily snapshots kept 13 months, month-end and manual ones kept indefinitely.
- **Snapshot catch-up is exact (decided 27 October 2026, sixth round):** a missed snapshot is always rebuilt from the
  ledger: quantity at the shop's local midnight = current `shop_variation` quantity minus the movements since; FIFO
  value = each batch's remaining quantity at midnight × its unit cost, with unknown-cost stock apart. "Current figures,
  marked late" is used only for dates before the business's ledger began.
- **Expiring stock alerts live here** (decided 27 October 2026, sixth round), on the same self-clearing pattern; stage
  18 later puts every alert type on one engine.
- **Thresholds are configuration:** reorder levels per shop and the expiring window per business are data the owner
  sets.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.

**Low stock is worked out in six places, three ways:**
1. `Product::scopeLowStock()`: `whereStock('<=', 'reorder_level')`, the viewer's shops summed against the product's
   level, shop-blind;
2. `ProductService::getLowStockProducts()` and its `low_stock` statistic (the products page's tile and its "Low
   stock" button), through that scope;
3. the web page `products.lowStock` (`/products/low-stock/list`, sidebar "Low stock") and `GET /api/products/low-stock`,
   both through `getLowStockProducts()`;
4. the dashboard's low-stock list (`DashboardController`): `whereStock` against `reorder_level`, filtered by
   `products.shop_id` (the owning-shop rule stage 10 removes);
5. `CheckLowStockLevels`: per shop, `coalesce(shop_variation.reorder_level, products.reorder_level)`, run only from the
   alerts screen's "Check levels" button, creating `LowStockAlert`s;
6. `InventoryService::getCurrentInventory()`'s `is_low_stock` (no caller).

`GET /api/inventory/low-stock` routes to `InventoryController::lowStock()`, which doesn't exist. `ReportType::LOW_STOCK`
and `WidgetType::LOW_STOCK_COUNT` / `LOW_STOCK_LIST` are enum cases with no implementation. The codebase's pattern for
a reusable stock query is `App\Services\Stock\StockQuery`.

**Reorder levels:** on the product, `shop_product` and `shop_variation`; `CheckLowStockLevels` reads
`coalesce(shop_variation.reorder_level, products.reorder_level)`; `Product::isLowStock()` doesn't. Stage 11 makes
`shop_variation` the only per-shop home.

**Alerts:** `CreateLowStockAlert` dedupes per shop and variation, but alerts **never resolve when stock recovers**, and
`CheckLowStockLevels` records `created_by ?? 1`, crediting system runs to user 1, who may belong to another business.
Module 09's daily check was never scheduled. A separate `alerts` table (general alerts with types, severity, category,
`alertable`) exists; `AlertType::STOCK_EXPIRING` is defined there and never raised. UAT: no open alert.

**Snapshots:** `inventory_snapshots`, `CreateInventorySnapshot` and `InventorySnapshotController` exist, taken by hand
only (none on UAT); the quantity is an integer, the variation can be NULL in the unique key, and the retail value reads
the `shop_variation` price stage 11 drops.

**The ledger's start, per business** (the first `cost_layers` row, read-only on UAT, 27 October 2026): businesses 1
and 2, 1 October 2025; business 3, 21 October 2026; business 8, 23 October 2026; the others have no stock yet.

**Movements:** `stock_movements` record each change (quantity, before, after, the reference, time); allocations
record what each consumer took from which batch (`cost_layer_id`, `quantity_allocated`, `allocated_at`). Stage 10's
`release()` deletes the released allocations and writes one movement for the consumer, so it doesn't say which batch
got how much back.

---

## Work

Every part keeps to the tenancy rules: `BelongsToBusiness` / `BelongsToShop`, business-aware `exists:`, a
`ResourceMatrixTest` row for every new route, a manager reaches only their shops.

### 1. Low stock: one definition
- [ ] **Per-shop reorder levels:** `shop_variation.reorder_level` is the shop's, in base units; low stock reads it,
      then the product's `reorder_level` (the default for new shops).
- [ ] **`App\Services\Stock\LowStockQuery`** (beside `StockQuery`): for one shop, in base units, the variations whose
      **sellable** stock (batches expired by stage 11's `Expiry` helper excluded) is at or below the shop's reorder
      level, else the product's; tracked products only; active in the shop (`shop_product.is_active`). Per variation:
      the sellable quantity, the reorder level, the shortfall (level − sellable, never below zero) and the sellable
      quantity in the largest sensible unit (`UnitBreakdown`). A manager's call is limited to their shops.
- [ ] `isLowStockAt(Shop $shop)` on `Product` and `ProductVariation` asks the query; `Product::scopeLowStock()` and
      `isLowStock()` are removed with stage 11's other shop-blind helpers.
- [ ] **Stock → Low stock**, the single report: per shop (a shop picker; a manager sees only their shops), the query's
      columns, sortable by shortfall; select rows and **"Create purchase order"**, which opens a draft purchase order
      for that shop with the shortfall per line (in base units here; stage 12 pre-fills the purchase unit and adds On
      order and In transit). `stock.view`. Its row in `ResourceMatrixTest`.
- [ ] **Everything else moves onto the query or goes:**
  - `Product::scopeLowStock()`: removed;
  - `ProductService::getLowStockProducts()` and the `low_stock` statistic: the query, for the request's shop;
  - `products.lowStock` (`/products/low-stock/list`): redirects to Stock → Low stock; the sidebar's "Low stock" link
    points there;
  - `GET /api/products/low-stock`: the query, for the request's shop (or the viewer's shops), same response shape (the
    contract test pins it);
  - `GET /api/inventory/low-stock` (a route to a missing method): served by the same code, so the recorded route works;
  - the dashboard's low-stock list: the query, for the selected shop;
  - `CheckLowStockLevels`: the query creates the alerts (step 2);
  - `InventoryService::getCurrentInventory()`'s `is_low_stock` (no caller): removed, or computed from the query if a
    caller is found;
  - `ReportType::LOW_STOCK` and the `WidgetType` low-stock cases: point at the new page and query.
  - The till works low stock out offline from the pull (stage 11's `sellable_quantity` against `base_reorder_level`),
    by the same rule.
- [ ] Tests:
  - the report, the product filter, the dashboard tile and the alerts give the same list for the same shop;
  - expired stock doesn't count: 50 tablets with 40 expired is low against a reorder level of 20;
  - another shop's stock doesn't count; an untracked product never appears;
  - the shop's reorder level beats the product's, in base units;
  - `/products/low-stock/list` redirects; `/api/products/low-stock` and `/api/inventory/low-stock` answer with the
    recorded shape;
  - a manager sees only their shops; another business's shop is a 404.

### 2. Alerts that clear themselves
- [ ] **Low stock:** `CheckLowStockLevels` runs from stage 11's expiry touch for each shop it touches, and after any
      stock change that crosses a reorder level (queued, per shop, coalesced), so alerts no longer wait for the "Check
      levels" button. An open alert (pending or acknowledged) whose variation is no longer on the list is marked
      `resolved` automatically ("Stock recovered", no user); it opens again if the variation drops back.
- [ ] **No false user:** a system run records `created_by` NULL and `source = system` (`low_stock_alerts.source`:
      `system` or `user`); `current_quantity` and `threshold_quantity` are decimal, in base units.
- [ ] **Expiring stock:** for each shop, a batch with stock left whose expiry falls within the business's window
      (`businesses.settings['stock']['expiring_alert_days']`, default 30) raises one `AlertType::STOCK_EXPIRING` alert
      in `alerts` (`source = system`, `alertable` the batch, a dedupe key `expiring:{shop}:{batch}`). It resolves
      itself when the batch has no stock left, and becomes an "expired stock" alert when the batch expires, until it
      is written off. Raised from the expiry touch.
- [ ] Tests: an alert resolves itself when the variation is no longer low, and opens again if it drops back; a system
      run records no user and `source = system`; a system alert in business B never names a user of business A; an
      expiring batch raises one alert, clears when sold out, and turns into an expired alert at the shop's midnight.

### 3. Inventory snapshots
- [ ] `inventory_snapshots` keep per shop, variation (always set: a simple product's default variation, so the unique
      key has no NULL that MySQL would let repeat) and date: the quantity (`decimal(15,3)`, base units), the cost value
      (Σ batches held, as `stockValueAt()`), the unknown-cost quantity, any shortfall below zero (no value), and the
      retail value (stage 11's `UnitPrice` for the base unit at that shop). `kind`: `daily`, `month_end` (the daily
      snapshot of a month's last day) or `manual`; `source`: `on_time`, `rebuilt` or `late`; unique on
      `(shop_id, variation_id, snapshot_date, kind)`.
- [ ] `CreateInventorySnapshot` uses them; the snapshot screen shows quantities with `UnitBreakdown` and marks
      rebuilt and late snapshots.
- [ ] **Every night, per shop:** `stock:snapshot`, scheduled every 15 minutes (`withoutOverlapping`, `onOneServer`),
      takes a shop's snapshot once its local midnight has passed since its last one (`shops.snapshot_taken_at`), dated
      the day that ended. Each missed day is taken on the next run (step 4).
- [ ] **Retention:** `stock:prune-snapshots` (daily) deletes `daily` snapshots older than 13 months; `month_end` and
      `manual` ones are kept indefinitely. Manual snapshots stay possible from the snapshot screen.
- [ ] Tests: a snapshot of a shop with two variations and a batch of unknown cost; taking it twice on one day keeps one
      row per variation; the retail value uses the shop's price for the base unit; a shop in another timezone is
      snapshotted at its own midnight; retention prunes a 14-month-old daily snapshot but keeps a month-end and a
      manual one.

### 4. Exact catch-up from the ledger
- [ ] **Movements name their batches:** from this stage, every `StockMovement` that consumes, receives or releases
      stock records `layers` (JSON: `layer_id`, signed quantity, and for a release the released allocation's
      `allocated_at`). `consume()`, `receive()` and stage 10's `release()` fill it; the ledger's tests check it.
- [ ] **Rebuilding a missed day** for a shop, at its local midnight `M` (from `Expiry::localMidnight`, stage 11):
  - **quantity** per variation = current `shop_variation.stock_quantity` − Σ (`quantity_after − quantity_before`) of
    the variation's movements at the shop after `M`;
  - **FIFO value** per variation = Σ over the batches received at or before `M` of (remaining at `M`) × `unit_cost`,
    where remaining at `M` = current `remaining_quantity` + Σ allocations from the batch made after `M` (still
    present) − Σ releases into the batch after `M` of allocations made before `M` (from `layers`); batches received
    after `M` are left out; batches of unknown cost are reported apart; stock below zero at `M` is the shortfall, at no
    value;
  - the costs are each batch's current `unit_cost` (re-costing since `M` is reflected; the snapshot says so);
  - written with `source = rebuilt`.
- [ ] **Before the ledger:** `businesses.ledger_started_at` (the first `cost_layers` row's time, filled by
      `stock:ledger-start --business=<id>`, dry run, `--apply`). A missed date before it can't be rebuilt: the
      snapshot takes the current figures with `source = late`.
- [ ] Tests:
  - a rebuilt snapshot equals one taken on time, given the same movements and no re-costing in between (quantity and
    value), with sales, a receipt, a void (release) and a supplier return after midnight;
  - a batch received after midnight is left out of the rebuilt value;
  - a snapshot for a date before the business's ledger began is marked `late`;
  - the scheduler skipping two midnights produces two rebuilt snapshots on the next run.

### 5. Housekeeping
- [ ] Domain rules (`pos-domain-rules`) and CLAUDE.md: one low-stock definition, self-clearing system alerts,
      snapshots and their catch-up.
- [ ] **`docs/flutter/09-products.md`, "Low Stock Products"** (the behaviour step 1 changes). Exact correction:
      "Returns the variations whose **sellable** stock at the request's shop (expired batches excluded) is at or below
      the shop's reorder level, else the product's; tracked products only." replaces "Returns products where
      `stock_quantity <= reorder_level`."
- [ ] **Note on `docs/implementation/09-inventory-tracking.md`**, under its title, once the stage ships (why: the
      pre-SaaS inventory-tracking design was only partly built):
      > **Superseded (stage 11c).** Partly built. `stock_movements` are written by `StockLedger` (stage 1b). One
      > low-stock definition, alerts that clear themselves, and nightly snapshots in base units, rebuilt exactly when
      > missed, are in stage 11c; On order and In transit in stage 12. The `inventory.*` permissions are dropped
      > (`stock.*` is used). Every finding and where it went: [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).

## Contract

No new sync fields: the till's low-stock view uses stage 11's `sellable_quantity` and `base_reorder_level`. The flat
API's two low-stock endpoints keep their response shape (pinned by the contract test); which variations they list
changes to the new definition. App checks: [app-release-checklist.md](./app-release-checklist.md#stage-11c).

## Schema

| Change | Data carried across |
|---|---|
| `low_stock_alerts.current_quantity`, `threshold_quantity` `decimal(15,3)`; `created_by` nullable; `source` (`system`, `user`) | Values unchanged; existing rows `system` (none open on UAT) |
| `inventory_snapshots.variation_id` always set; `quantity_on_hand` `decimal(15,3)`; `unknown_cost_quantity`, `shortfall_quantity`; `kind` (`daily`, `month_end`, `manual`); `source` (`on_time`, `rebuilt`, `late`) | None on UAT (no snapshots); any existing row gets its product's default variation, `kind = manual` |
| `shops.snapshot_taken_at` (nullable) | None |
| `businesses.ledger_started_at` (nullable) | `stock:ledger-start` fills it from the first cost layer (UAT: 1 October 2025 for businesses 1 and 2; 21 and 23 October 2026 for 3 and 8) |
| `stock_movements.layers` (JSON, nullable) | None; movements before this stage have none (rebuilds within them use allocations only, see Risks) |
| `businesses.settings['stock']['expiring_alert_days']` (no column; default 30) | Missing means 30 |
| Scheduled `stock:snapshot` (every 15 minutes), `stock:prune-snapshots` (daily) | None |

## Acceptance criteria

- [ ] One query answers low stock for the report, the product filter, the dashboard, the alerts and both API endpoints,
      per shop, on sellable stock of tracked products, against the shop's reorder level; the old page redirects.
- [ ] Alerts resolve themselves when stock recovers; system runs record no user (`source = system`); expiring batches
      raise one alert each, which clears itself.
- [ ] A snapshot is taken for every shop at its local midnight; a missed one is rebuilt exactly from the ledger, and
      only dates before the ledger began are marked late; daily ones are kept 13 months, month-end and manual ones for
      good.
- [ ] The app's recorded requests and the flat API contract tests still pass.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass.

## Risks

- **Releases before this stage left no per-batch record**, so a rebuild across a release made before `layers` existed
  can't add that batch's returned units back exactly. Mitigation: such releases are rare (voids, cancelled transfers);
  the rebuild flags the snapshot when the window contains a release without `layers`.
- **Rebuilt values use today's costs**, so a batch re-costed since shows its new cost. Mitigation: the snapshot says
  `rebuilt`; month-end snapshots are taken on time when the scheduler runs.
- **The snapshot job runs on the scheduler.** If `schedule:run` isn't running, snapshots are rebuilt on the next run;
  nothing is lost while the ledger holds the movements.
- **Low-stock alerts after every crossing** could be noisy. Mitigation: one open alert per shop and variation, raised
  and cleared by the same query.

## Rollout (UAT)

1. Take a snapshot of UAT (`db:snapshot`); restore it into `rehearsal_testing`; deploy and run the migrations
   (additive).
2. `stock:ledger-start --business=<id>` for every business (dry run: the dates above; then `--apply`).
3. Run `stock:snapshot` once by hand: every shop gets yesterday's snapshot and `snapshot_taken_at`; check
   `schedule:list` shows it every 15 minutes and `stock:prune-snapshots` daily.
4. Run `CheckLowStockLevels` for every shop and compare the page, the products filter, the dashboard and the two API
   endpoints for one shop: the same list.
5. Check the server against the app's recorded requests (the low-stock endpoints answer with the recorded shape).
6. The user runs steps 2 and 3 on UAT after a fresh snapshot.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing remains open. The decisions of 27 October 2026 stand.

## Follow-ups (not this stage)

- Every other alert type on one engine: [stage 18](./18-alerts-and-notifications.md).
