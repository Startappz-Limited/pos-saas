# FIFO costing: analysis for stage 1b

Stage 1b, "Costing: adopt FIFO", step 1. Open question 5 was answered on 12 October 2026: adopt the
FIFO costing tables, after analysing the earlier plan. This document is that analysis and the proposed
design, **approved on 15 October 2026** (decisions in section 4) and recorded in `pos-saas-plan.md`.

Sources read:
- `docs/implementation/20-fifo-costing.md` (Module 20);
- `docs/implementation/08-stock-intake.md` (Module 08, Stock Intake (Batches));
- the migrations and models for `cost_layers`, `cost_allocations` and `sale_item_stock_allocations`;
- every stock writer: the four sale paths, `CompleteStockIntakeAction`, `RecordStockMovement`,
  `StockAdjustmentService`, `MarkPurchaseReturnShippedAction`, `ReturnService`;
- the git history of the costing tables.

## 1. What the earlier plan intended

**Module 08** planned a `stock_batches` table: one row per supplier delivery, with
- purchase price, shipping and other costs;
- a computed unit and total cost;
- `remaining_quantity`, `damaged_quantity` and `returned_quantity`.

Sales were to draw from batches oldest first. Adjustments were to change a batch's quantities directly.

**Module 20** then planned FIFO cost layers on top of stock intake:

| Table | Holds |
|---|---|
| `cost_layers` | One layer per stock intake: product, shop, intake, `unit_cost`, `original_quantity`, `remaining_quantity`, `received_date`, `consumed_at` |
| `cost_allocations` | Which layers a sale item consumed: sale item, layer, product, shop, quantity, unit and total cost, `allocated_at` |

The algorithm:
- **Intake** creates a layer.
- **A sale** takes the layers of that product and shop that still have stock, oldest `received_date`
  first. For each layer it records an allocation and decrements the layer's `remaining_quantity`, until
  the sale's quantity is covered.

Module 10 adds `sale_item_stock_allocations`: sale item, `stock_batch_id` (a uuid), quantity, unit and
total cost. It's the batch-based version of the same idea.

**Not covered by the earlier plan:**
- returns, refunds and adjustments;
- a sale larger than the layers hold;
- variations;
- layers that don't come from an intake (opening stock).

## 2. What was actually built

**Nothing of the algorithm.** All three tables came in with the initial commit, and no code ever wrote
to them:
- `CostLayer`, `CostAllocation` and `SaleItemStockAllocation` are empty models;
- `CostingService` is an empty class;
- the `CostingMethod` enum is unused.

**Where the plan and the code differ:**

| Planned | Actual |
|---|---|
| `stock_batches` (Module 08) | **Never created.** `stock_intakes` took its role, with `batch_number` and `expiry_date`. `stock_movements.stock_batch_id` and `sale_item_stock_allocations.stock_batch_id` are uuids pointing at nothing |
| A layer's cost comes from the intake (`$intake->unit_cost`) | **`stock_intakes` has no cost column.** The only purchase cost is `purchase_order_items.unit_cost` (when the intake came from a purchase order), else the product or variation `cost_price` (nullable: NULL means unknown) |
| Layers per product and shop | Products have variations, and per-shop stock is now keyed on `(shop_id, variation_id)` (decision 4.2). The layer tables have no `variation_id` |
| `cost_layers.stock_intake_id` required | Layers must also come from adjustments, sale returns and opening stock |
| `cost_allocations.sale_item_id` required | Damage, loss and purchase returns also consume stock |
| Sale cost from allocations | `sale_items.unit_cost` is the product's **current** `cost_price`, copied at sale time (`?? 0`) |

**How stock moves today**, which the stock service replaces:
- **The four sale paths** decrement `products.stock_quantity` or `product_variations.stock_quantity`
  directly. They write no `StockMovement`.
- **Completing a stock intake** increments the **product's** stock, even when the intake is for a
  variation. It writes no movement.
- **Adjustments** and purchase returns go through `RecordStockMovement`, which:
  - reads the current figure, then writes an absolute value (a lost-update race);
  - updates the product or variation, never a shop.
- **Customer returns** record whether an item can be restocked, but never restock it.

## 3. Proposed design (SaaS)

### Tables

1a is merged, so changes are **additive migrations**. The costing tables are empty in every database,
so nothing needs carrying across.

- **`cost_layers`** (adopted). Add:
  - `business_id`;
  - `variation_id` (required);
  - `source_type` and `source_id` (what created the layer);
  - `received_at` (a timestamp; layers are ordered by it, then by id).

  `stock_intake_id` becomes nullable. `unit_cost` becomes nullable: NULL means "cost unknown", following
  the `cost_price` rule.
- **`cost_allocations`** (adopted). Add:
  - `business_id` and `variation_id`;
  - `consumer_type` and `consumer_id` (a sale item, an adjustment item or a purchase-return item);
    `sale_item_id` becomes nullable and stays set for sales;
  - `cost_layer_id` nullable, for a **provisional** allocation (see negative stock);
  - `is_provisional`.
- **`sale_item_stock_allocations`**: it duplicates `cost_allocations`, keyed on a batch uuid that never
  existed. **Kept as it is, unused** (decision 3).

### One shared stock service

`App\Services\Stock\StockLedger` is the only code that changes stock. Every change happens in one
transaction:
- it locks the `shop_variation` row (`lockForUpdate`);
- it changes the quantity by a delta, never an absolute value (this fixes the lost-update race);
- it writes the `StockMovement`;
- it creates or consumes cost layers.

| Event | Stock | Cost layers |
|---|---|---|
| Stock intake completed | + accepted quantity, in the intake's shop, for its variation (the default one for a simple product) | New layer at the purchase-order line's unit cost, else the variation's cost, else unknown |
| Sale (all four paths) | − quantity | Consume oldest first. `sale_items.unit_cost` is the weighted cost of what was consumed; profit uses it |
| Adjustment up (found, correction) | + quantity | New layer at the latest layer's cost, else the variation's cost |
| Adjustment down (damage, loss, expiry) | − quantity | Consume oldest first, like a sale (the adjustment item is the consumer) |
| Purchase return shipped to supplier | − quantity | Consume the returned intake's layer first, then oldest first |
| Sale return, restockable | + quantity | New layer **at the cost the item was sold at** (see decision 2) |
| Sale return, not restockable | none | none: the goods are written off; the sale's cost stays as it was |
| Opening stock: the 1b data move and Phase 3's CSV import | the figure given | One "opening" layer per shop and variation, at the variation's cost |

### Negative stock (offline sales are always accepted, 4.5)

When the layers can't cover a sale:
- what they hold is allocated as usual;
- the **shortfall is allocated provisionally**, with no layer, at the latest known cost for that shop
  and variation (its last layer, else the variation's cost, else unknown);
- stock goes negative, and is flagged on the movement.

**When stock next arrives** (proposed; see decision 1):
- the new layer first covers the outstanding provisional allocations, oldest first;
- each covered allocation is re-costed at the layer's cost;
- the sale item's cost and profit, and the sale's totals, are updated;
- a movement records the correction.

### Profit

- `sale_items.unit_cost` and `total_cost` come from the item's allocations.
- `RecomputeSaleProfit` recomputes from the allocations. It keeps today's rules: an unknown cost counts
  as zero, and sales still holding an uncosted item are skipped unless `--zero-unknown` is given.
- The per-shop `cost_price` override on `shop_variation` is the fallback cost for that shop, ahead of the
  variation's own cost.

### Data carried across

One migration (and an artisan command for re-running) does all of the following:
- sets each `shop_variation.stock_quantity` from the legacy figures, using the same rule as the legacy
  import: a product's stock goes to its own shop;
- creates an opening layer for every positive figure;
- checks that the totals match.

After that, the legacy `products.stock_quantity` and `product_variations.stock_quantity` columns, and
`shop_product`'s stock column, are no longer written. Whether they're dropped in 1b or later is
decision 4.

### Flat API (4.8)

Responses that include `stock_quantity` keep the key, computed from per-shop stock:
- the shop named in the request, when it names one;
- otherwise the total across the shops the user can access in the current business.

## 4. Decisions (agreed 15 October 2026)

1. **Negative stock is re-costed on arrival.** The next layer first covers outstanding provisional
   allocations, oldest first; those sale items' cost and profit, and their sales' totals, are updated,
   and a movement records the correction.
2. **A restockable sale return becomes a new layer at the cost the item was sold at.**
3. **`sale_item_stock_allocations` is kept** (empty, unused); all costing goes through
   `cost_allocations`.
4. **The legacy stock columns are dropped in 1b**, after a migration moves every figure into
   `shop_variation` (with opening layers) and checks the totals.
