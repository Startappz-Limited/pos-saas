# Phase 1b: Stock, Sales, Registers, Invoice Numbers

> **Source:** saas-plan.md section 5 (Phase 1b, Phase 1 risks), section 1 (Data model, POS domains),
> sections 4.2, 4.5, 4.8, 4.9, section 3 (Corrections)
> **Branch:** `saas/phase-1b-stock-sales-registers` · **Depends on:** Phase 1a

---

## Goal

Move every stock writer, reader and sale path onto **one per-shop stock source that covers variants**,
add registers and register sessions, and move invoice numbering to per-register series. The schema for
this stage was written in 1a.

## Before you start

| Open question | Status |
|---------------|--------|
| **5. FIFO costing tables** | **Answered 12 October 2026: adopt them**, after analysing the earlier FIFO plan. That analysis is the first piece of work below. |

Variant stock is decided (4.2): every product has a default variation, and per-shop stock is keyed on
`(shop_id, variation_id)`.

## Relevant current state

- **Stock lives on the product and the variation, not per shop.** Every POS write goes to
  `products.stock_quantity` or `product_variations.stock_quantity`: intake, adjustments, purchase
  returns, `RecordStockMovement`, and product create and update.
- `shop_product.stock_quantity` is written only by the Woo/Shopify product sync and
  `BackfillShopProductPivot`. `shop_product` has no `variation_id`, so **variant stock can't be held per
  shop** in today's schema.
- **Four code paths make a sale**, each decrementing the product or variation directly without a
  `StockMovement`: the web `SaleController`, `Api\SaleController`, `ConvertEcommerceOrderToSale` and
  `ConvertAbandonedCartToSale`. Adjustments do write movements, through `RecordStockMovement`.
- **Readers of `stock_quantity`:** `CheckLowStockLevels`, `CreateInventorySnapshot`, `InventoryService`,
  `ProductService`, `DashboardController`, `ProductObserver` (pushes stock to Woo/Shopify on
  `Product::updated`) and both product sync services.
- **FIFO costing tables are unused.** Profit (`RecomputeSaleProfit`) uses the product's cost price and
  ignores the `shop_product.cost_price` override.
- **`cash_registers` is a cash session** (one per cashier per shop per day: float, expected and closing
  balances, variance). There's no till or device entity.
- **Invoices:** `sales.invoice_number` comes from `InvoiceNumberService` (a per-shop sequence embedding
  the shop code) and already uses a row lock (`lockForUpdate`).

## Decisions that apply

- **4.2:** products belong to the business; stock and prices per shop, the only stock source;
  `cash_registers` becomes register sessions that reference a register. *Confirmed.*
- **4.8:** the flat `/api` keeps working; responses that include product (and variation) stock compute it
  from per-shop stock. *Confirmed.*
- **4.9, invoice numbers on an offline till:** each register has its own series,
  `INV-{shop code}-{register code}-{year}-{sequence}`. **Corrected (review):** numbers are unique **per
  business** (`(business_id, invoice_number)`); the shop code is the user's own code, unique within the
  business; there's no second, generated code. *Confirmed.*
- **Dependencies (section 5):** Redis isn't needed. Invoice series keep the existing row lock.

## Work

### Stock source
Per shop and per sellable item, variants included (4.2). Move onto it:
- [x] The **four sale paths**: both `SaleController`s, `ConvertEcommerceOrderToSale` and
      `ConvertAbandonedCartToSale`.
      *Step 3: each path creates the line, then `StockLedger::sellItem()`, then `recostSale()`; `SalePathsStockTest` (5).*
- [x] Every writer: `CompleteStockIntakeAction`, `StockAdjustmentService`, `RecordStockMovement`,
      purchase returns, sale returns that restock, and product create and update.
      *Step 3: intake, adjustments, `RecordStockMovement`, purchase returns, restockable sale returns, product create/update (`HasShopStock` → `setLevel`) and the e-commerce syncs all go through `StockLedger`; `StockWritersAndReadersTest`.*
- [x] Every reader: `CheckLowStockLevels`, `CreateInventorySnapshot`, `InventoryService`,
      `ProductService`, `DashboardController`.
      *Step 3: per-shop through `shop_variation` and `StockQuery`; `StockWritersAndReadersTest`. Step 4 dropped the legacy columns, so no reader can fall back to them.*
- [x] A `StockMovement` for every sale, written by **one shared stock service** rather than inline in
      each path.
      *Step 2: `App\Services\Stock\StockLedger` (row lock + delta, a movement per change); `StockLedgerTest` (9).*

### Costing: adopt FIFO (Open question 5)

**Step 1: analyse the earlier FIFO plan before writing code.** Present the findings and wait for
approval.
- [x] Read how FIFO was meant to work:
  - **`docs/implementation/20-fifo-costing.md`**, the earlier FIFO implementation plan (Module 20 of the
    original master plan, which depends on Module 08, Stock Intake (Batches)). This is the main source;
  - the Module 08 document in `docs/implementation/` and the original master plan there;
  - the models and migrations for `cost_layers`, `cost_allocations` and `sale_item_stock_allocations`;
  - the git history of those files (`git log -S cost_layers`, `git log -- <file>`).
- [x] Write down: what creates a cost layer (stock intake batches?), how a sale consumes layers, how
      returns, refunds and adjustments put stock back, and what each of the three tables holds.
- [x] List where `20-fifo-costing.md` and the current code differ (tables, columns, what was never
      built).
- [x] Map it onto the SaaS design: layers **per shop and per variation** (`shop_id`, `variation_id`,
      `business_id`), written by the shared stock service.
- [x] Settle the cases the earlier plan may not cover:
  - a sale that takes stock below zero (offline sales are always accepted, 4.5): which cost it uses and
    how it's corrected when stock arrives;
  - sale returns and purchase returns;
  - stock adjustments up and down;
  - the cost of opening stock from the CSV import (Phase 3).
- [x] Record the agreed design in saas-plan.md before implementing it.
      *Step 1: `docs/saas/fifo-analysis.md`; decisions agreed 15 October 2026 (re-cost on arrival, restockable returns as a new layer at the sold cost, `sale_item_stock_allocations` kept unused, legacy stock columns dropped in 1b) and recorded in saas-plan.md.*

**Step 2: implement.**
- [x] Stock intake creates cost layers per shop and variation.
      *Step 3: `CompleteStockIntakeAction` → `receive()` at the PO line cost.*
- [x] Every sale path allocates cost from the layers (oldest first), through the shared stock service.
      *Steps 2–3: `sellItem()` consumes oldest first; below zero is provisional at `fallbackCost()`.*
- [x] Returns, refunds and adjustments create or restore layers as agreed in step 1.
      *Steps 2–3: restockable returns a new layer at the sold cost; adjustments up a layer, down consume; purchase returns prefer the intake's layer.*
- [x] Profit and `RecomputeSaleProfit` use the FIFO cost of each sale item.
      *Steps 3 and 5: `recostSale()`; `RecomputeSaleProfit` costs lines from allocations; `FifoProfitTest` (2).*

### Registers
- [x] `Register` model.
      *Step 6.*
- [x] `CashRegister` becomes register sessions tied to a register.
      *Step 6: `cash_registers.register_id`; migration `2026_10_15_000003` places every existing session on its shop's default register.*
- [x] A **default register per shop** for the legacy API (4.8).
      *Step 6: `R1` "Main till", created with the shop; `RegistersTest` (5).*

### Invoice numbers
- [x] `InvoiceNumberService` moves to per-register series, keeping its existing row lock.
      *Step 7: series `r{register}:{year}` under the same `lockForUpdate`; the row is created by an upsert before it is locked, since a locking read of a missing row deadlocked two registers (found by the concurrency test). Migration `2026_10_15_000004` carries each shop's series on at its default register.*
- [x] Numbers unique per business.
      *`(business_id, invoice_number)` (stage 1a); `INV-{shop}-{register}-{year}-{n}` with shop codes unique per business and register codes per shop.*

### Flat API compatibility
- [x] Product and variation stock computed from per-shop stock (4.8).
      *Step 3: `HasShopStock`; `shops[].pivot.stock_quantity` from `shop_variation`; the flat API contract test passes.*

## Pending decisions (Proposed, review)

- **Which shop's stock the legacy API returns (4.8).** Proposed: use the shop in the request when it
  names one, and otherwise the total across the shops the user can access in the default business.
  *Implemented as proposed (step 3, `StockQuery`).*
- **Default register for the legacy API (4.8).** Proposed: the existing app knows nothing about
  registers, so each shop gets a default register, and legacy cash-register and sale calls land on it.
  *Implemented as proposed (step 6).*

## Acceptance criteria

- [x] Each of the four sale paths decrements the shop's stock for the right product or variation and
      writes a movement.
      *`SalePathsStockTest`.*
- [x] Low-stock alerts, snapshots and the dashboard read per-shop stock.
      *`StockWritersAndReadersTest`.*
- [x] Each sale item's cost comes from FIFO layers in its shop, oldest first; profit uses it.
      *`StockLedgerTest`, `FifoProfitTest`.*
- [x] The cases settled in the FIFO analysis (negative stock, returns, adjustments) have tests.
      *`StockLedgerTest` (negative stock and re-costing, returns, adjustments, purchase returns), `StockMoveMigrationTest`.*
- [x] A cash session opens and closes on a register.
      *`RegistersTest`.*
- [x] Two registers in one shop number invoices in separate series without collisions, **tested
      concurrently on MySQL 8**.
      *`InvoiceNumberConcurrencyTest`: 6 processes (3 per register) claim 120 numbers together on MySQL 8; each series is 1–60 with no collision or gap. `InvoiceNumbersTest` for the series themselves.*
- [x] A legacy cash-register and sale call lands on the shop's default register.
      *`RegistersTest`, `InvoiceNumbersTest`.*
- [x] The flat API contract tests pass; the full suite is green on both databases.
      *SQLite 1,238 passed, 59 skipped; MySQL 8 1,248 passed, 49 skipped; `FlatApiContractTest` within the MySQL run.*

## Risks (Phase 1, leakage and drift)

- **A stock writer or reader left on the old columns, so two figures drift apart again.**
- Cost layers drifting from stock (layer quantities not matching per-shop stock).
- The flat API changing shape by accident.

## Definition of done

- [x] Acceptance criteria met, with tests.
- [x] Pint, the full suite (SQLite and MySQL 8) and the flat API contract tests pass.
      *SQLite 1,238 passed, 59 skipped; MySQL 8 1,248 passed, 49 skipped; Pint clean.*
- [x] Hotspot checklist items for stock, sale paths, costing and registers ticked in saas-plan.md
      section 2.
      *Stock source, sale paths, costing, registers and the legacy default ticked.*
