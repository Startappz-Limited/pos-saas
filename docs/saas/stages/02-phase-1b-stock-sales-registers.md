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
- [ ] The **four sale paths**: both `SaleController`s, `ConvertEcommerceOrderToSale` and
      `ConvertAbandonedCartToSale`.
- [ ] Every writer: `CompleteStockIntakeAction`, `StockAdjustmentService`, `RecordStockMovement`,
      purchase returns, sale returns that restock, and product create and update.
- [ ] Every reader: `CheckLowStockLevels`, `CreateInventorySnapshot`, `InventoryService`,
      `ProductService`, `DashboardController`.
- [ ] A `StockMovement` for every sale, written by **one shared stock service** rather than inline in
      each path.

### Costing: adopt FIFO (Open question 5)

**Step 1: analyse the earlier FIFO plan before writing code.** Present the findings and wait for
approval.
- [ ] Read how FIFO was meant to work:
  - **`docs/implementation/20-fifo-costing.md`**, the earlier FIFO implementation plan (Module 20 of the
    original master plan, which depends on Module 08, Stock Intake (Batches)). This is the main source;
  - the Module 08 document in `docs/implementation/` and the original master plan there;
  - the models and migrations for `cost_layers`, `cost_allocations` and `sale_item_stock_allocations`;
  - the git history of those files (`git log -S cost_layers`, `git log -- <file>`).
- [ ] Write down: what creates a cost layer (stock intake batches?), how a sale consumes layers, how
      returns, refunds and adjustments put stock back, and what each of the three tables holds.
- [ ] List where `20-fifo-costing.md` and the current code differ (tables, columns, what was never
      built).
- [ ] Map it onto the SaaS design: layers **per shop and per variation** (`shop_id`, `variation_id`,
      `business_id`), written by the shared stock service.
- [ ] Settle the cases the earlier plan may not cover:
  - a sale that takes stock below zero (offline sales are always accepted, 4.5): which cost it uses and
    how it's corrected when stock arrives;
  - sale returns and purchase returns;
  - stock adjustments up and down;
  - the cost of opening stock from the CSV import (Phase 3).
- [ ] Record the agreed design in saas-plan.md before implementing it.

**Step 2: implement.**
- [ ] Stock intake creates cost layers per shop and variation.
- [ ] Every sale path allocates cost from the layers (oldest first), through the shared stock service.
- [ ] Returns, refunds and adjustments create or restore layers as agreed in step 1.
- [ ] Profit and `RecomputeSaleProfit` use the FIFO cost of each sale item.

### Registers
- [ ] `Register` model.
- [ ] `CashRegister` becomes register sessions tied to a register.
- [ ] A **default register per shop** for the legacy API (4.8).

### Invoice numbers
- [ ] `InvoiceNumberService` moves to per-register series, keeping its existing row lock.
- [ ] Numbers unique per business.

### Flat API compatibility
- [ ] Product and variation stock computed from per-shop stock (4.8).

## Pending decisions (Proposed, review)

- **Which shop's stock the legacy API returns (4.8).** Proposed: use the shop in the request when it
  names one, and otherwise the total across the shops the user can access in the default business.
- **Default register for the legacy API (4.8).** Proposed: the existing app knows nothing about
  registers, so each shop gets a default register, and legacy cash-register and sale calls land on it.

## Acceptance criteria

- [ ] Each of the four sale paths decrements the shop's stock for the right product or variation and
      writes a movement.
- [ ] Low-stock alerts, snapshots and the dashboard read per-shop stock.
- [ ] Each sale item's cost comes from FIFO layers in its shop, oldest first; profit uses it.
- [ ] The cases settled in the FIFO analysis (negative stock, returns, adjustments) have tests.
- [ ] A cash session opens and closes on a register.
- [ ] Two registers in one shop number invoices in separate series without collisions, **tested
      concurrently on MySQL 8**.
- [ ] A legacy cash-register and sale call lands on the shop's default register.
- [ ] The flat API contract tests pass; the full suite is green on both databases.

## Risks (Phase 1, leakage and drift)

- **A stock writer or reader left on the old columns, so two figures drift apart again.**
- Cost layers drifting from stock (layer quantities not matching per-shop stock).
- The flat API changing shape by accident.

## Definition of done

- [ ] Acceptance criteria met, with tests.
- [ ] Pint, the full suite (SQLite and MySQL 8) and the flat API contract tests pass.
- [ ] Hotspot checklist items for stock, sale paths, costing and registers ticked in saas-plan.md
      section 2.
