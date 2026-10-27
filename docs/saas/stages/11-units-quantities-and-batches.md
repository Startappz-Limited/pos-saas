# Stage: Units, Quantities and Batches

> **Source:** stock intake and products review, plan review and Phase 7 alignment of 27 October 2026; decisions and
> split of the combined stage, 27 October 2026; refinements of 27 October 2026 (second round).
> **Branch:** `feat/stock-units-batches`, from `main` once stage 10 is merged · **Depends on:**
> [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md) (the selling-shop rule, `release()`,
> `cost_known`, `permissions:sync`, the `sale_void_discarded` pattern), Phase 7 (the sync pull and push)
> · **Next:** [11b-prices-and-discounts.md](./11b-prices-and-discounts.md) and
> [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md) (either order), then
> [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) · **Related:**
> [14-returns-in-reports.md](./14-returns-in-reports.md) (return lines carry units from this stage),
> [15-regulated-products.md](./15-regulated-products.md) (uses business types and batches) · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-11)

---

## Revisions

**Split from the combined stage (27 October 2026).** "Stock Intake, Products and FIFO Integrity" became three stages
(see [stage 10](./10-stock-correctness-and-voids.md)). This one is the foundation for receiving and moving stock:
units of measure, decimal quantities, FEFO and batches, and the end of the duplicated product columns. It changes the
sync contract, so it is its own release, server first.

From the combined stage:
- **1b** (the product catalogue) is here, rewritten for units: the shop-aware stock helpers, the cost order, per-shop
  reorder levels, opening stock and the architecture test. `purchase_unit` / `units_per_pack` are replaced by
  `product_units`. The selling-price order is now `PriceResolver`, per unit. The last purchase cost moved to stage 12,
  with receiving.
- **1e**: SKU uniqueness and money arithmetic in `Product`.
- **"Points to confirm" 1 to 4 and 6** of the combined stage are decided (decimal stock, FEFO, `OUT_OF_STOCK`, the
  legacy columns, selling by the pack).

**Second round (27 October 2026):**
- **Contract convention** stated once: new decimal fields are strings; existing numeric fields keep their type; whole
  quantities are encoded as integers on the flat API (step 8, "Contract").
- **Expiry** defined: expired from the start of the day after `expiry_date`, in the shop's timezone; month-only
  expiries stored as the month's last day; one `Expiry` helper; a daily touch per shop at local midnight, touching
  only the rows that changed (step 5).
- **Fractions pushed offline:** the new till refuses them locally (Contract).
- **Unknown units on the push:** refused, reviewed (`unknown_unit`), and ended by a manager's discard
  (`sale_discarded`); the lookup is business-scoped and includes soft-deleted units (steps 2 and 8).
- **A unit's factor is locked once used**, and push lines carry the till's `base_quantity`, which wins on a mismatch
  (`unit_factor_mismatch`) (steps 2, 3 and 8).
- **Old apps and barcodes:** a variation's existing barcode moves unchanged to its base unit (step 7).
- **Decided: one home for each fact** (was "Points to confirm" 1). The duplicated columns are dropped in this stage,
  by copy, verify and drop (step 7). The two earlier commands (`products:move-shop-costs`,
  `products:reconcile-default-variation`) become `catalogue:copy-to-units` and `catalogue:verify-units`.
- **Decided: quantity breaks count in base units** (was "Points to confirm" 2) (step 3).
- **Found in the code:** pricing rules are evaluated nowhere on the server today, and they are scoped to a product,
  not a variation; their `customer_id` points at `users`, not `customers` (step 3).

**Sixth round (27 October 2026): split into 11, 11b and 11c.** This stage keeps units, quantities and batches;
prices and discounts went to [stage 11b](./11b-prices-and-discounts.md), low stock, alerts and snapshots to
[stage 11c](./11c-low-stock-alerts-and-snapshots.md). Where each step went:
- Step 1 (decimal quantities), step 2 (units), step 5 (FEFO, batches, expiry, business types), step 6
  (`OUT_OF_STOCK`), step 7 (one home, the two releases) and step 9 (housekeeping) stay, less the parts below.
- Step 2's `shop_unit_prices`: the table and its copy stay here (release 2 drops the columns it replaces), with the
  simple price order as `UnitPrice`; editing every unit's shop price, and `PriceResolver`, are 11b.
- Step 3: selling in units stays; `PriceResolver::quote()`, quantity breaks, the price check, discount limits and
  offline approval went to 11b (its steps 1 to 5), with their tests.
- Step 4: the stock helpers, the architecture test, the cost order and opening stock stay; per-shop reorder levels and
  `isLowStockAt()` went to 11c (step 1).
- Steps 4a (low stock) and 4b (snapshots) went to 11c (steps 1 to 4); the snapshot catch-up became exact (decided).
- Step 7: `pricing-rules:check-customers`, the pricing-rule columns and the `customer_id` re-point went to 11b.
- Step 8: the pull's `unit_prices` entity, the rules' new fields and the push's approval fields went to 11b (step 6).
- Step 9: the note on module 06 went to 11b; the note on module 09 and the low-stock correction of
  `docs/flutter/09-products.md` went to 11c.
- Decisions, current state, Contract, Schema, Acceptance criteria, Risks and Rollout moved with their steps; nothing
  is in two stages.
- **App checks** moved to [app-release-checklist.md](./app-release-checklist.md#stage-11); rollout checks the server
  against the app's recorded requests.
- Stage 15 is now [Regulated products](./15-regulated-products.md); the pharmacy business type's preset is there.

**Fifth round (27 October 2026):** the changes outside `docs/saas/stages/` made during the review (notes on old
documents, comments in old migration copies, corrections to the app's documents, lines in the plan) were reverted:
planning rounds change only this folder. Each is now a planned item in the stage whose work makes it true, with its
exact text (step 9).

**Fourth round (27 October 2026):**
- **Decided (were "Points to confirm" 1 to 5):** business types set defaults only, from a table of six (step 5);
  sale-level discounts are limited per role and checked with the price (step 3); adjustment lines take any unit
  (step 3); FIFO and FEFO only, with `CostingMethod`, `CostingService` and `sale_item_stock_allocations` removed (step
  9); snapshots every night per shop, kept 13 months, month-ends for good (step 4b).
- **Decided:** offline price and discount approval by the approver's till PIN (step 3, "Contract"); unassigned barcodes
  aren't sent at all until assigned (step 7); system-run alerts carry `source = system` and no user (step 4a); an
  increase without a cost is received at an unknown cost, never silently at the fallback (step 5); the minimum app
  version for this stage's features is recorded ("Contract", with stage 10's `X-App-Version`).
- **Follow-ups:** prescription-only items and the controlled-drugs register are needed by pharmacies and get
  [stage 15](./15-regulated-products.md); weighted-average cost stays a follow-up, built only if a customer needs it.
  Traceability: [TRACEABILITY-stock.md](./TRACEABILITY-stock.md).
- Nothing remains open.

**Legacy design review (27 October 2026):** the pre-SaaS modules 08, 09, 20 and 21 (`docs/implementation/`) were
reviewed against the code and these stages. Added here: low-stock alerts resolve themselves and record no false user
(step 4a); adjustments can name the batch they take, and an increase can carry a cost (step 5); inventory snapshots
in base units, valued through the new homes (step 4b). Three new points to confirm (counting in units, nightly
snapshots, costing methods). Stage 10 fixes today's adjustment bugs (its step 1g).

**Third round (27 October 2026):**
- **Decided: the server checks prices** (was "Points to confirm" 1): every sale it records is quoted by
  `PriceResolver`; a lower price needs `sales.override-price` (new) online, and is reviewed (`price_mismatch`)
  offline (step 3).
- **Two releases, no migration designed to fail:** release 1 adds and copies; release 2 drops, its checks only a
  safety net (step 7, Rollout).
- **`/api/v1` receipt `quantity` stays a number**, with `quantity_exact` and `base_quantity` added (step 8).
- **Encoding is explicit per API:** `App\Casts\Quantity` is storage only; `Encode::flat()`, `pullWhole()` and
  `exact()` encode (steps 1 and 8).
- **`stock:touch-expiring` catches up** from a last-run time per shop (`shops.expiry_touched_at`) (step 5).
- **Decided: product-level barcodes on products with variations** (was "Points to confirm" 3) wait in
  `unassigned_barcodes` until the owner assigns or drops each on the product page (step 7).
- **One discard for every stuck outbox item** (stage 10's `outbox_item_discarded`) replaces `sale_discarded`;
  `unknown_unit` is held for it (step 8).
- **Deleted units never go in `deletions`:** they are pulled as updates with `deleted_at` (steps 2 and 8).
- **Accepted:** old apps see no shop prices for non-base units; pricing-rule thresholds stay whole base units.
- **Low stock has one definition** (`LowStockQuery`) and one page, Stock → Low stock (step 4a).

---

## Goal

A product is bought, held and sold in the units a shop actually uses. Panadol is held in tablets and sold by the
tablet, the strip of 10 or the box of 100; fertiliser is held in kilograms, sold by the kilogram to three decimals
or by the 50 kg bag; soda is held in bottles and sold by the bottle or the crate of 24. Scanning a crate's barcode
sells one crate at the crate price and takes 24 bottles from stock.

Quantities are decimal wherever stock is counted, with each unit saying how many decimals it allows. Batches carry
a batch number and an expiry date; a business that sells medicine or agro-chemicals sells the batch that expires
first, and nobody sells an expired batch.

Every stock question about a product is asked of a shop, in base units. Each fact (a price, a barcode, a stock
level, a cost) has one home.

## Before you start

Stage 10 must be merged: this stage changes the ledger's quantities, which `release()` (stage 10) also moves.

Nothing remains open (see "Points to confirm"). Stage 10's `X-App-Version` (step 5c there) must be in place: this
stage's contract relies on it.

This stage ships as **two server releases** (step 7): release 1 adds the new homes and copies into them; release 2
drops the old columns.

## Decisions that apply

- **Context (27 October 2026):** no customers yet; new businesses include pharmacies, agro-vets and wholesalers (who
  sell whole cartons). The UAT businesses are kept: every change to their data is a command with a dry run and
  `--apply`, rehearsed on a restored snapshot first.

- **Decimal stock (decided 27 October 2026):** stock and quantities are decimal throughout (`shop_variation`,
  `cost_layers`, `cost_allocations`, sale lines, return lines, adjustment lines, GRN, purchase-order and transfer
  lines), as `decimal(15,3)`. Each unit of measure has a `precision` (0 for pieces, 3 for kg or litres); validation
  reads it, and a fraction is refused only for a unit with precision 0. Unit costs per base unit are
  `decimal(15,4)`; money totals stay in cents, rounded once at the total. Existing integer data widens with no
  change in value.

- **FEFO and batches (decided 27 October 2026):**
  - A business setting `stock.consumption`: `fifo` (default) or `fefo`. Setting up a business as a pharmacy or
    agro-vet defaults it to `fefo`.
  - With `fefo`, `consume()` takes batches by earliest expiry, then oldest received.
  - **Expired batches are never consumed by a sale**, in either mode. If only expired stock remains, the shortfall
    goes below zero (provisional) as today, the sale is flagged, and the till warns. Expired stock leaves through a
    write-off.
  - `cost_layers.batch_number` and `expiry_date`. Per-variation `track_batches`: when true, receiving requires a
    batch number and expiry date.
  - The recall report is in [stage 12](./12-receiving-and-moving-stock.md).

- **`ProductStatus::OUT_OF_STOCK` is removed (decided 27 October 2026).** Out of stock is derived per shop. Rows that
  people set by hand map to `inactive`, rows that code set automatically to `active`; the mapping is a command with
  a dry run, and the enum case is removed after it runs.

- **Legacy columns are dropped in this stage (decided 27 October 2026):** `products.stock_quantity`;
  `shop_product.cost_price` (copied to `shop_variation.cost_price` where empty); the product's own price and cost
  columns (where a UAT product differs from its default variation, the variation's value wins; listed before
  applying). The flat API keeps its `stock_quantity` field, computed from `shop_variation`.

- **One home for each fact (decided 27 October 2026).** The duplicated columns are dropped in this stage, in the same
  sequence that creates the base units, so there is never a period with two writable copies of a price:

  | Fact | Single home | Dropped from |
  |---|---|---|
  | Selling and wholesale price | `product_units` | `product_variations`, `products` |
  | Per-shop price override | `shop_unit_prices` (`shop_id`, `unit_id`, `selling_price`, `wholesale_price`; unique on shop and unit) | `shop_product` and `shop_variation` price columns |
  | Barcode | `product_units` (unique per business) | `product_variations`, `products` |
  | Stock, reorder level, fallback cost | `shop_variation` | `products`, `shop_product` |
  | Last purchase cost (per base unit) | `product_variations.cost_price` | `products.cost_price` |
  | Listed in a shop, active there | `shop_product` (`is_active` only) | n/a |

  - Where copies disagree on UAT, the value that has been charged wins: the variation's price over the product's;
    for shop overrides, `shop_variation`'s (the one the till pull has sent since Phase 7) over `shop_product`'s. The
    command lists every difference before applying.
  - The flat API and the pull keep emitting the old field names, computed from these homes, so neither contract
    changes. The contract tests pin that.
  - The price order, here as `UnitPrice`: `shop_unit_prices` for that shop and unit, then the unit's price, then the
    base unit's price × factor. Stage 11b's `PriceResolver` adds quantity breaks and the price check on top.

- **Two releases for the column moves (decided 27 October 2026, third round):** release 1 adds the new homes, with
  code that reads and writes only them, and the copy and verify commands; release 2 drops the old columns. No
  migration is designed to fail.
- **Encoding is per API (decided 27 October 2026, third round):** quantities are stored and computed as scale-3
  strings; each API encodes them with a named helper (`flat()`, `pullWhole()`, `exact()`). Existing numeric fields,
  `/api/v1` receipt `quantity` included, keep their type; new decimal fields are strings.
- **Product-level barcodes on products with real variations (decided 27 October 2026, third round)** aren't moved onto
  the hidden default variation: they wait, listed, until the owner assigns each to a variation's unit or drops it. A
  product without real variations keeps today's behaviour (its barcode goes to its default variation's base unit).
- **Deleted units stay on the till (decided 27 October 2026, third round):** pulled as updates with `deleted_at`,
  never in `deletions`.

- **Prices, discounts, low stock and snapshots** are decided in stages [11b](./11b-prices-and-discounts.md) and
  [11c](./11c-low-stock-alerts-and-snapshots.md) (split 27 October 2026, sixth round).
- **Configuration over code:** business types, units, precision and the consumption setting are data the owner sets;
  regulated-product controls are presets ([stage 15](./15-regulated-products.md)), not code paths per type.
- **Business types set defaults, never restrictions (decided 27 October 2026, fourth round).** Chosen at setup,
  changeable later; changing it changes only the defaults for what is created afterwards, never existing products or
  settings:

  | Type | Consumption | `track_batches` on new products | Unit presets |
  |---|---|---|---|
  | General retail / minimart (`general_retail`) | FIFO | off | piece; pack |
  | Supermarket (`supermarket`) | FIFO | off | piece; crate or carton; kg |
  | Wholesale (`wholesale`) | FIFO | off | piece; carton; bale |
  | Pharmacy (`pharmacy`) | FEFO | on | tablet; strip; box; bottle |
  | Agro-vet (`agro_vet`) | FEFO | on | kg; bag; litre; can; piece |
  | Hardware (`hardware`) | FIFO | off | piece; kg; metre; box |

  Existing UAT businesses become `general_retail`, with their settings unchanged.
- **Unassigned barcodes aren't sent (decided 27 October 2026, fourth round):** until assigned, a waiting barcode is in
  neither the pull nor the flat API, so an old app scanning it finds nothing.
- **Adjustments in any unit (decided 27 October 2026, fourth round):** adjustment lines (and stocktake lines, stage
  13) take any unit of the variation, stored with `unit_id`, `quantity` and `base_quantity`; "2 cartons + 5 pieces" is
  two lines. The factor lock covers them.
- **FIFO and FEFO only (decided 27 October 2026, fourth round):** `CostingMethod` (with LIFO, which IAS 2 doesn't
  allow, and the unused average), `CostingService` and `sale_item_stock_allocations` are removed in this stage.
  Weighted average is a follow-up, built only if a customer needs it.
- **No silent fallback cost on increases (decided 27 October 2026, fourth round):** an adjustment increase without a
  cost entered is received at an unknown cost, and its sales are `cost_known = false` (stage 10, step 6).

- **Units of measure (decided 27 October 2026):** `product_units` replaces `purchase_unit` / `units_per_pack`:
  - every variation has exactly one base unit (factor 1), carrying the variation's prices and barcode;
  - stock is held in the base unit; a sale line records `unit_id`, `quantity` in that unit and `base_quantity`
    (quantity × factor), which is what the ledger moves; the same applies to return, GRN, purchase-order and
    transfer lines;
  - scanning a unit's barcode adds that unit (a carton's barcode sells one carton at the carton price);
  - quantity-break pricing rules apply on top, in stage 11b;
  - stock screens show quantities in the largest sensible unit as well as the base ("12 cartons + 5 pieces");
  - selling by the pack is part of this stage.

- **Cost order (decided 27 October 2026, kept):** the shop's batches come first; `shop_variation.cost_price` stays,
  as a fallback only, behind the latest batch cost; then the variation's `cost_price` (the last purchase cost).
- **Stage 1b (15 October 2026):** every stock change goes through `StockLedger`; selling below zero is allowed and
  re-costed when stock arrives.
- **The flat API is a live contract** for the Flutter app: responses gain fields, never change existing ones.
- **Profit is net of VAT** ([docs/vat.md](../../vat.md)).

## Relevant current state

Checked against `main` after Phase 7 (`832f684`) and stage 10's plan, 27 October 2026.

**Quantities are integers almost everywhere:**
- `integer`: `shop_variation.stock_quantity`, `cost_layers.original_quantity` / `remaining_quantity`,
  `cost_allocations.quantity_allocated`, `stock_movements.quantity` / `quantity_before` / `quantity_after`,
  `sale_items.quantity`, `return_items.quantity`, `stock_adjustment_items.quantity_before` / `quantity_change` /
  `quantity_after`, `purchase_return_items.quantity` (unsigned), `products.reorder_level`,
  `shop_product.reorder_level`, `shop_variation.reorder_level`, `pricing_rules.min_quantity` / `max_quantity`.
- `decimal(15,2)`: `purchase_order_items.quantity_ordered` / `quantity_received` / `quantity_remaining`, and the
  old `stock_intakes` quantities (read-only history from stage 12).
- `StockLedger` takes `int $quantity` throughout; `Product`'s pivot stock query casts the sum `as signed`.
- **Validation:** `StoreSaleRequest` has `items.*.quantity` `integer|min:1|max:10000`. The sync push validates sale
  items with the same rules (`SaleHandler`), so the flat API and offline sales share them.
- **Unit costs** are `decimal(15,2)` on `cost_layers` and `cost_allocations` (`unit_cost`, `provisional_unit_cost`),
  and `decimal(12,2)` on `sale_items.unit_cost` and `stock_movements.unit_cost`.
- **JSON today:** integer columns encode as JSON integers. Laravel's `decimal:3` cast would encode `"2.000"` (a
  string), and a float `2.0` encodes as `2.0`; either would break the app's integer parsing. Step 8 handles it.

**Units are a label.** `products.unit` is a free-text string (default "piece"), shown and sent to tills, never used in
arithmetic. UAT (read-only, 27 October 2026) uses four values: `piece` (60 products), `liter` (56), `kg` (43) and
`meter` (59). Their stock is whole numbers.

**Every fact has several homes today** (the columns decision 7 drops):
- prices on the product, the variation, `shop_product` and `shop_variation`; costs on the product, the variation,
  `shop_product` and `shop_variation`; barcodes on the product and the variation; reorder levels on the product,
  `shop_product` and `shop_variation`;
- **nothing picks a shop override when selling:** the server doesn't price sales (the web POS, the flat API and the
  push send each line's price, and `RecordSale` records it: `unit_price => $item['price']`); the sync pull sends the
  `shop_variation` overrides in its `stock` section; `InventoryService` and inventory snapshots value stock at the
  `shop_variation` price; the product page shows the `shop_product` price;
- **UAT:** no `shop_variation` or `shop_product` price override, no `shop_product.cost_price`, no `shop_product`
  reorder level other than the default (10); 2 products (business 2 and business 3) whose price or cost differs from
  their default variation's; 33 products with variations carry a product-level barcode, which their hidden default
  variation would inherit; no default variation's barcode differs from its product's.

**Barcodes** are unique per business on products (`business_id, barcode, alive`) and on variations
(`business_id, barcode`), separately. On UAT, giving each variation a base unit with its barcode (or, for a default
variation, its product's) makes no clash; 204 variations have no barcode. The till looks barcodes up in its own
catalogue from the pull (`products.barcode`, `variations.barcode`).

**The legacy stock column is already gone.** Phase 1b's migration `2026_10_15_000002` moved `products.stock_quantity`
and `product_variations.stock_quantity` into `shop_variation`, checked the totals, and dropped both columns. What
remains: `Product::$fillable` still lists `stock_quantity`; `stock_quantity` on products and variations is a computed
attribute (`HasShopStock`) that is also settable; the flat API's `POST /api/products` requires `stock_quantity`.

**Products model:**
1. **`Product`'s stock helpers ignore the shop.** `isOutOfStock()`, `isLowStock()`, `canBeSold()` and
   `getTotalStockValueAttribute()` read the computed `stock_quantity` (a total across the viewer's shops). At shop
   A's till, `canBeSold()` can say yes when shop A has none and shop B has 50. Stock value multiplies that figure by
   the product's `cost_price`, not the cost of the batches held.
2. **`createDefaultVariation()` copies the product's prices, tax class and status once**, at creation; editing the
   product later leaves its default variation stale, and the default variation is what sells.
3. **Costs have no stated order.** `fallbackCost()` reads the batches, then `shop_variation.cost_price`, then the
   variation's and the product's.
4. **Reorder levels:** `CheckLowStockLevels` uses `coalesce(shop_variation.reorder_level, products.reorder_level)`;
   `Product::isLowStock()` and `scopeLowStock()` don't.
5. **Opening stock typed on the product form is received at the fallback cost** (`HasShopStock` → `setLevel()`).
   The CSV import receives a new product's opening stock at the file's cost; its update path uses `setLevel()`.
6. **SKUs** are unique per business already. `generateSku()` checks, then inserts. `Product::boot()` assigns a uuid
   that `HasUuids` assigns already.
7. **Money in `Product`:** profit and margin use float arithmetic on decimal strings.

**`ProductStatus::OUT_OF_STOCK`:** nothing sets it automatically (`ProductService::markOutOfStock()` has no caller);
people can choose it on the product form and the flat API. Readers: `scopeOutOfStock()`,
`ProductService::getOutOfStockProducts()` and the products page's tile, `ProductFactory::outOfStock()`. The store sync
publishes only `active` products, so `inactive` hides a product as `out_of_stock` does. No UAT product has it. The
Flutter app isn't on this machine (`MOBILE_APP_PATH` points at a missing folder).

**How the APIs encode today:** the flat API returns models and paginators directly (`response()->json(['data' =>
$products])`), not API resources, so a model's JSON is what the app gets. The pull builds its arrays by hand
(`SyncPull::entity()`); the `/api/v1` receipt casts `(int) $item->quantity` (`SaleReceipt`).

**Pricing rules, discounts, low stock and snapshots** today: see stages 11b and 11c.

**From the pre-SaaS design** (modules 08, 09, 20 and 21, reviewed 27 October 2026):
- **Batch state was stored, not derived.** Module 08's `stock_batches` kept `status` (`active`, `depleted`,
  `expired`), `sold_quantity`, `damaged_quantity` and `returned_quantity` beside `remaining_quantity`, and refused a
  sale that ran out (`Insufficient stock`). Stage 1b replaced it with cost layers and allocations; this stage keeps
  that: expiry is worked out by the `Expiry` helper, never stored, and what a batch went to comes from its
  allocations.
- **`CostingMethod`** (module 20) offers FIFO, LIFO, weighted average and specific identification; nothing reads it.
  The business's consumption setting (FIFO or FEFO, step 5) is the only method. LIFO isn't allowed under IFRS
  (IAS 2). Decided: FIFO and FEFO only (step 9).
- **Adjustments** (module 21) take stock oldest batch first, never a named batch (a damaged carton from one
  delivery), and add stock at the fallback cost with no way to give its cost. Stage 10 (step 1g) fixes their
  bugs.

**Time and scheduling:** `shops.timezone` (nullable, "overrides the business timezone") and `businesses.timezone`
(default `Africa/Nairobi`) exist; stage 10's `VoidWindow` reads them in that order. The scheduler (`routes/console.php`)
runs fixed-time UTC jobs and an hourly `sync:warn-overdue`; nothing runs per shop timezone yet.

**FEFO and batches today:** `consume()` orders batches by `received_at`, then `id`, after an optional
`$preferredLayerId`. Batches have no batch number or expiry. No business records what kind of business it is.

**Phase 7 and stage 10 to reuse:** push results `applied`, `duplicate`, `refused`, `failed` (and `pending`, stage
10); `sync_issues` and the till sync review; the `sale_void_discarded` item (stage 10, step 5c) as the pattern for a
manager's discard; `RecordsSyncDeletion` for pulled deletions; `SyncCursor::position()` returns null for an entity a
cursor doesn't know, so a new entity is pulled from the start.

---

## Work

Every part of this stage keeps to the tenancy rules: business-owned records use `BelongsToBusiness`, shop-owned ones
`BelongsToShop`; `exists:` rules are business-aware; every new route gets its row in
`TenantIsolation/ResourceMatrixTest`; a manager reaches only the shops they work in.

### 1. Decimal quantities
- [ ] **Migrations widen every stock quantity to `decimal(15,3)`**, values unchanged:
      `shop_variation.stock_quantity` and `reorder_level`; `cost_layers.original_quantity` and `remaining_quantity`;
      `cost_allocations.quantity_allocated`; `stock_movements.quantity`, `quantity_before`, `quantity_after`;
      `sale_items.quantity`; `return_items.quantity`; `stock_adjustment_items.quantity_before`, `quantity_change`,
      `quantity_after`; `purchase_return_items.quantity`; `purchase_order_items.quantity_ordered`,
      `quantity_received`, `quantity_remaining` (from `decimal(15,2)`). Any other stock quantity a search finds
      (inventory snapshots, low-stock alerts, online-order and abandoned-cart lines) is widened too and listed in the
      PR. `stock_intakes` stays as it is (read-only history). `pricing_rules.min_quantity` / `max_quantity` stay
      whole base units (step 3).
- [ ] **Unit costs widen to `decimal(15,4)`:** `cost_layers.unit_cost`, `cost_allocations.unit_cost` and
      `provisional_unit_cost`, `sale_items.unit_cost`, `stock_movements.unit_cost`, `product_variations.cost_price`
      and `shop_variation.cost_price` (costs per base unit). Totals stay `decimal(…,2)`, rounded once at the total. A
      carton of 24 at KES 1,000 costs 41.6667 a piece, and 24 pieces still cost exactly KES 1,000.
- [ ] **No floats for quantities.** `StockLedger` takes and returns quantities as decimal strings at scale 3, and its
      arithmetic and comparisons use `bcmath` (`bcadd`, `bcsub`, `bcmul`, `bccomp`). `StockChange` carries strings.
      The same applies to `HasShopStock`, `CheckLowStockLevels`, inventory snapshots and every report that sums
      quantities in PHP. Raw queries that cast stock `as signed` cast to `decimal(15,3)`.
- [ ] **One cast for quantities, for storage only:** `App\Casts\Quantity` reads a widened column as a scale-3 string
      for storage and `bcmath` arithmetic. A model's `toArray()` / JSON gives that string too (`"2.000"`), so nothing
      becomes a number by accident; encoding for an API is step 8's job. Every widened quantity column on a model
      uses it.
- [ ] **Validation reads the unit's precision.** `App\Rules\QuantityForUnit` checks a quantity against the unit it is
      entered in: more than 0, at most the precision's decimals. A fraction for a precision-0 unit is refused
      ("Panadol tablets are sold whole: enter a whole number of tablets, or sell by the strip."). Every form request
      and push handler with a quantity uses it; `integer` rules on quantities go.
- [ ] Tests:
  - the widening migrations leave every value unchanged (row counts and sums per table, on SQLite and MySQL);
  - 2.5 kg sold from 50 kg leaves 47.5, and 0.1 + 0.2 kg sold leaves exactly 0.3 kg less (no float drift);
  - the ledger invariant holds with decimal quantities, for receive, consume, release and settling below zero;
  - a fraction of a tablet is refused online; 1.250 kg is accepted, 1.2505 kg is refused.

### 2. Units of measure (`product_units`)
- [ ] **Schema:** `product_units`: `uuid`, `business_id` (`BelongsToBusiness`), `variation_id`, `name`, `factor`
      (`decimal(15,3)`, base units in one of this unit, more than 0), `precision` (0 to 3), `barcode` (nullable,
      unique per business among live units), `selling_price`, `wholesale_price` (nullable; `decimal(10,2)`, per this
      unit), `is_base`, `can_sell`, `can_buy`, `is_default_purchase`, `sort_order`, `created_by`/`updated_by`,
      timestamps, `deleted_at` (soft deletes). `Auditable`. **Not** `RecordsSyncDeletion`: a deleted unit stays on
      the till (step 8). Deleting a unit soft-deletes it and turns `can_sell` and `can_buy` off, which moves
      `updated_at` so the pull sends it.
  - **Exactly one base unit per variation:** factor 1, `can_sell` true, its precision is the stock's precision. Held
    by a stored generated column (`base_key`: 1 for the base, NULL otherwise) with a unique index on
    `(variation_id, base_key)`, and by the actions, which refuse to delete, unbase or re-factor a base unit.
  - Unit names are unique per variation. At most one `is_default_purchase` per variation.
  - A non-base unit's factor must give a base quantity the base unit's precision can hold (a bag of 50 kg: fine; a
    pack of 2.5 tablets: refused).
- [ ] **Shop prices per unit:** `shop_unit_prices` (`shop_id`, `unit_id`, `selling_price`, `wholesale_price`;
      `BelongsToShop`; unique on shop and unit). The only place a shop overrides a price. **This stage owns the table
      and its copy** (step 7), because release 2 drops the columns it replaces; the shop's existing price screen writes
      it for the base unit. Stage 11b adds editing every unit's shop price, and `PriceResolver`.
- [ ] **`App\Support\Pricing\UnitPrice::for(Shop $shop, ProductUnit $unit, ?Customer $customer = null)`:** the unit's
      price by the order in "Decisions that apply" (shop price, the unit's own, base × factor; `wholesale_price` for a
      wholesale customer when set). It backs the APIs' computed price fields (step 7) and the web POS until stage 11b's
      `quote()` replaces it there.
- [ ] **A unit's factor is locked once used.** `ProductUnit::isUsed()` is true once the unit appears on any line
      (`sale_items`, `return_items` here; stage 12 adds GRN, purchase-order and transfer lines to the same list,
      `ProductUnit::USED_ON`). Adjustment lines take units too (step 3), so `stock_adjustment_items` is on the list.
      Once used:
  - a model guard (`updating`) refuses a `factor` change with `UnitInUse`, so the API, imports and tinker can't
    change it either;
  - the product form shows the factor read-only, with "To change the pack size, add a new unit and stop selling this
    one.";
  - to change a pack size, a new unit is created and the old one's `can_sell` / `can_buy` turned off. Its past lines
    keep their meaning.
- [ ] **Finding a unit:** every lookup by id or uuid (forms, the flat API, the push) goes through
      `ProductUnit::resolveFor(ProductVariation $variation, string $uuid)`: within the current business (the tenant
      scope stays on), **including soft-deleted units** (`withTrashed()`), and only a unit of that variation. A unit of
      another business, or of another variation, is not found.
- [ ] **The product form edits units.** A "Units" table on the product (or on each variation): name, factor
      ("1 Strip = 10 Tablet"), precision, barcode, selling and wholesale price, can sell, can buy, default for
      purchases. A product without variations edits its default variation's units. The base unit's row is always
      first and can't be removed. The shop's price for the base unit is edited on the shop's product page (every unit's
      in stage 11b).
- [ ] **CSV import:** the base unit's name and precision (`unit`, `unit_precision`), and one optional extra unit per
      row (`pack_unit`, `pack_factor`, `pack_barcode`, `pack_price`). More units are added on the form.
- [ ] **Showing quantities:** `App\Support\Stock\UnitBreakdown` writes a base quantity in the largest sensible unit
      plus the remainder: the largest sellable or buyable unit with a whole-number factor not above the quantity,
      then base units ("12 Crate + 5 Bottle (293 Bottle)"; "3 Bag + 12.5 kg (162.5 kg)"). Stock screens, low-stock
      alerts and the stock value report use it.
- [ ] Tests (the three examples):
  - Panadol: tablet base (precision 0), strip ×10, box ×100; 2 strips and 1 box are 120 tablets;
  - fertiliser: kg base (precision 3), bag ×50; a bag is 50 kg, and 2.5 kg is a valid quantity;
  - soda: bottle base, crate ×24, with its own barcode; 293 bottles show as "12 Crate + 5 Bottle";
  - a variation can't lose its base unit or have two; a unit barcode clashing with another unit in the business is
    refused, and one in another business isn't;
  - editing the factor of a used unit is refused on the form and through the model; an unused unit's factor can be
    changed;
  - `resolveFor()` finds a soft-deleted unit of the variation, and not another business's or another variation's.

### 3. Selling in units
- [ ] **Sale lines in units:** `sale_items.unit_id` (foreign key to `product_units`), `quantity` (in that unit),
      `base_quantity` (`decimal(15,3)`). Online, the server computes `base_quantity = quantity × factor`; from the
      push, it takes the till's figure (step 8). `unit_price` and `unit_cost` are per the unit sold, so `line_total`
      and `total_cost` keep their meaning. The ledger moves `base_quantity`: `sellItem()` consumes it, and
      `release()` (stage 10) gives it back. Existing lines: `unit_id` set to the base unit and
      `base_quantity = quantity`, by `catalogue:copy-to-units` (step 7).
- [ ] **Every sale path takes a unit:** the web POS, `RecordSale` (flat API and push), online orders and abandoned
      carts (always the base unit: online stores sell base units). A line with no unit is the base unit.
- [ ] **Scanning a unit's barcode adds that unit.** Barcode lookup (the web POS, the flat API's product search, the
      till's local catalogue from the pull) looks in `product_units`: a crate's barcode adds one crate at the crate
      price; a bottle's adds one bottle.
- [ ] **Fractions:** online (web, flat API, `/api/v1`), a fraction of a precision-0 unit is refused (step 1). From the
      push, it is applied as recorded (the goods have left the shop) and flagged on the till sync review
      (`fractional_quantity`), as negative stock is.
- [ ] **Return lines in units:** `return_items.unit_id` and `base_quantity`; a returned line takes the unit it was
      sold in, or the base unit; restocking moves `base_quantity`. Existing lines get the base unit (step 7). Stage 14
      caps returns in base units.
- [ ] **Adjustment lines in any unit** (decided): `stock_adjustment_items.unit_id`, `quantity` (in that unit) and
      `base_quantity`, as other lines; the ledger moves `base_quantity`. "2 cartons + 5 pieces" is entered as two
      quantities (two lines of the same variation). Existing lines get the base unit and `base_quantity = quantity`
      (by `catalogue:copy-to-units`). The factor lock covers them.
- [ ] **Receipts and invoices** show the unit with the quantity ("2 Strip", "2.500 kg", "1 Crate"): the web receipt,
      the invoice PDF and the `/api/v1` receipt JSON.
- [ ] **Reports** sum `base_quantity` for quantities and show them in base units; money is unchanged.
- [ ] Tests:
  - 3 crates of 24 sold by scanning the crate barcode take 72 bottles and charge 3 × the crate price;
  - 2.5 kg of fertiliser sold from a 50 kg bag's stock leaves 47.5 kg; one bag sold takes 50 kg at the bag price;
  - a strip and a box of the same medicine are stocked correctly (10 and 100 tablets), and `UnitPrice` gives each
    its own price, else 10 × and 100 × the tablet;
  - a fraction of a tablet is refused online, and applied and flagged on the push;
  - voiding a sale of 2 crates gives back 48 bottles;
  - an adjustment of 2 cartons + 5 pieces (two lines) moves 53 pieces; a used carton on an adjustment line can't
    change its factor.

### 4. Stock questions asked of a shop (from the combined stage's 1b)
- [ ] **Shop-aware stock helpers**, in base units, replacing `Product::isOutOfStock()`, `canBeSold()` and
      `getTotalStockValueAttribute()` (and the same on `ProductVariation`; `isLowStock()` is stage 11c's):
  - `availableAt(Shop $shop, ?ProductVariation $variation = null)`;
  - `canBeSoldAt(Shop $shop, ProductVariation $variation)`: the status allows selling, and either stock isn't
    tracked or the shop has sellable (unexpired) stock. Selling below zero stays allowed on the till;
  - `stockValueAt(?Shop $shop = null)`: Σ `remaining_quantity × unit_cost` over the batches, with batches of
    unknown cost counted separately and flagged, and expired batches shown apart.
  - Callers are found with a search for each old method and moved; the old methods are removed.
- [ ] **`stock_quantity` on products is read-only.** The setter in `HasShopStock` goes, and `stock_quantity` leaves
      `Product::$fillable`. The flat API's `stock_quantity` keeps its name and shape, computed from `shop_variation`.
      `POST /api/products` still accepts `stock_quantity`, received as opening stock (below).
- [ ] **An architecture test** fails on any write to `stock_quantity` outside `StockLedger`, and on any reference to
      a dropped column (step 7).
- [ ] **Cost order**, stated in `StockLedger::fallbackCost()`'s docblock and the domain rules: the shop's latest
      batch with a cost, then `shop_variation.cost_price` (the fallback), then `product_variations.cost_price` (the
      last purchase cost). All per base unit.
- [ ] **Opening stock** typed on the product form (create and edit) and sent to `POST /api/products` is received at
      the cost entered (`StockLedger::receive`, `source_type = opening`), with a batch number and expiry date when
      the variation tracks batches (step 5). The CSV import's update path receives an increase at the file's cost
      too. With no cost entered, the batch's cost is unknown and its sales are marked (`cost_known`, stage 10).
- [ ] Tests:
  - `canBeSoldAt()` is false at shop A with no stock and true at shop B with 50, for the same product;
  - stock value is the sum over batches, with unknown-cost and expired batches apart;
  - a sale at a shop with no batch uses the shop's fallback cost, then the variation's;
  - opening stock is a batch at the entered cost.

### 5. FEFO, batches and expiry
- [ ] **What kind of business** (decided): `businesses.type` (`general_retail`, `supermarket`, `wholesale`,
      `pharmacy`, `agro_vet`, `hardware`), chosen at sign-up and changeable on the business settings page by
      `settings.update` (audited). `App\Enums\BusinessType` holds the table in "Decisions that apply": each type's
      consumption, `track_batches` default for new products, and unit presets (offered as the base and extra units on
      the product form, never required). The type restricts nothing. Changing it changes only what is created
      afterwards; the settings page says so ("Existing products and settings stay as they are.").
- [ ] **Existing businesses:** `php artisan businesses:set-type --type=general_retail --business=<id>` (dry run;
      `--apply`; only businesses with no type) gives the UAT businesses `general_retail`, leaving their settings as
      they are.
- [ ] **The consumption setting:** `businesses.settings['stock']['consumption']`, `fifo` (default; also when
      missing) or `fefo`. A new business takes its type's; changing the type later doesn't change it. On the business
      settings page under "Stock", changed by `settings.update`, audited.
- [ ] **New products take the type's `track_batches` default** (pharmacy and agro-vet: on); the product form can
      change it.
- [ ] **Batches carry a number and an expiry:** `cost_layers.batch_number` (nullable string) and `expiry_date`
      (nullable date), indexed with `(shop_id, variation_id, expiry_date)`. `product_variations.track_batches`
      (boolean, default false): when true, anything that receives stock (opening stock here; GRNs and transfers in
      stage 12; an adjustment that adds stock) requires both.
- [ ] **When a batch is expired:** from the start of the day after its `expiry_date`, in the shop's timezone
      (`shops.timezone`, else `businesses.timezone`, default Africa/Nairobi, as `VoidWindow` reads them). A batch with
      `expiry_date` 31 October is sellable until 23:59:59 on 31 October, Nairobi time, and expired from 00:00 on 1
      November.
- [ ] **One helper decides it:** `App\Support\Stock\Expiry` (`isExpired($expiryDate, Shop $shop, $at)`,
      `firstSellableDate(Shop $shop, $at)`, `localToday(Shop $shop)`, `localMidnight(Shop $shop, $date)`). `consume()` (as a SQL condition:
      `expiry_date IS NULL OR expiry_date >= firstSellableDate`), the pull's `sellable_quantity`, `expired_quantity`
      and `next_expiry`, the daily touch, the expiring-stock list and stage 12's recall report all use it, so they
      never disagree.
- [ ] **Month-only expiries:** "EXP 10/2026", common on medicines, is entered as a month; the opening-stock and
      adjustment forms (and stage 12's GRN form) accept `MM/YYYY` or a full date, and store a month as its last day
      (31 October 2026).
- [ ] **`consume()` order:** `fifo`: `received_at`, then `id`; `fefo`: `expiry_date` (batches without one last), then
      `received_at`, then `id`; `$preferredLayerId` still comes first.
- [ ] **Expired batches are never sold.** For a sale line, `consume()` skips expired batches, in both modes. If only
      expired stock remains, the shortfall goes below zero (provisional, `fallbackCost()`). The sale is flagged: an
      `expired_stock` issue on the till sync review (the sale, the line, the units short), and the web POS warns when
      the item is added. Other consumers (write-offs, supplier returns) can take expired batches.
- [ ] **The expiry touch:** `stock:touch-expiring`, scheduled every 15 minutes (`withoutOverlapping`,
      `onOneServer`). Each shop keeps the time it was last done, `shops.expiry_touched_at` (nullable). For each
      active business (`TenantContext::eachBusiness()`) and each of its shops, a run:
  - touches `updated_at` on every `shop_variation` row holding a batch with stock left that **has expired since that
    shop's last run**, by the `Expiry` helper (`Expiry::datesExpiredBetween($shop, $from, $now)`: the expiry dates
    whose expiry moment, the shop's midnight after them, falls after `$from` and at or before `$now`);
  - then sets `expiry_touched_at = $now`, in the same transaction.

  So a missed midnight (the scheduler down, a slow run) is caught up by the next run, and two runs in a row touch
  nothing new. A shop that has never run (NULL) touches every row with an expired batch once. Tills then pull only
  those rows. The online-store stock push for those rows is dispatched too, so stores stop offering expired stock
  (they are sent sellable stock); stage 11c hooks its low-stock and expiring-stock alerts onto this run.
- [ ] **Adjustments and batches:** an adjustment line that removes stock may name the batch it takes
      (`stock_adjustment_items.cost_layer_id`, passed to `consume()` as `$preferredLayerId`): a damaged carton from one
      delivery comes out of that delivery's batch. For a variation that tracks batches, a removal for damaged,
      expired or quality issue must name it. A line that adds stock carries a batch number and expiry when the
      variation tracks batches, and a cost per base unit entered by someone holding `products.set-cost`. **Without a
      cost entered, the increase is received at an unknown cost** (the batch's `unit_cost` NULL), and sales from it are
      `cost_known = false` (stage 10, step 6) until someone sets it; never at the fallback cost silently. The line
      shows "Cost unknown".
- [ ] **Writing off expired stock:** a "Write off expired" action on the shop's stock screen creates a stock
      adjustment, reason "expired", that consumes the expired batches (`$preferredLayerId` per batch), at their cost.
- [ ] **Expiring stock list:** per shop, batches expiring within N days (30 by default) and those already expired,
      with quantity (in the largest sensible unit), batch number and value.
- [ ] Tests:
  - a batch with `expiry_date` 31 October is sellable at 23:59 on 31 October and expired at 00:00 on 1 November,
    Nairobi time; the same moment expressed in UTC gives the same answer; a shop with its own timezone uses it;
  - a month-only expiry "10/2026" is stored as 31 October 2026;
  - FEFO takes the batch that expires first even when it was received later; FIFO takes the oldest received;
  - an expired batch is skipped, the sale goes below zero and is flagged (`expired_stock`), on web and push;
  - the touch touches only rows with a batch that expired since the shop's last run; with the scheduler skipping
    midnight, the next run still touches the row; two runs in a row touch it once; a shop in another timezone is
    touched at its own midnight, not Nairobi's; a shop never run before touches its expired rows once;
  - a write-off of expired stock takes the expired batches and leaves the others;
  - a damaged-stock adjustment naming a batch takes that batch, not the oldest; for a variation that tracks batches,
    one without a batch is refused;
  - a variation that tracks batches can't receive opening stock without a batch number and expiry;
  - each business type sets its consumption, `track_batches` default and unit presets on what is created after;
    changing a type later leaves existing products and settings alone; a pharmacy created at sign-up uses FEFO;
    `businesses:set-type` gives UAT-like businesses `general_retail` and changes no setting;
  - an increase without a cost makes a batch of unknown cost and its sales are `cost_known = false`; with a cost from
    a holder of `products.set-cost`, a batch at that cost.

### 6. Remove `ProductStatus::OUT_OF_STOCK`
- [ ] **Before removing it:** search what sets and reads it in the app, the store sync and the Flutter app (with
      `MOBILE_APP_PATH` set to a checkout), and record it in the PR. Found on 27 October 2026: nothing sets it
      automatically; people can set it by hand; the store sync hides every non-active product; no UAT row has it.
- [ ] **`php artisan products:clear-out-of-stock-status --business=<id>`** (dry run by default; `--apply` writes;
      counts per business). Rows map to **`inactive`**, which hides them from online stores as the old status did (a
      test pins that the store sync pushes an `inactive` product as draft). If the search finds code that set it
      automatically, those rows map to `active` instead, and the command says which.
- [ ] Once the command has run everywhere, the enum case is removed: the product form and the flat API refuse
      `out_of_stock` (422), `scopeOutOfStock()` becomes `scopeOutOfStockAt(Shop $shop)`,
      `ProductService::getOutOfStockProducts()` and the products page's tile count per shop, `markOutOfStock()` goes,
      and `ProductFactory::outOfStock()` makes a product with no stock at its shop.
- [ ] Tests: the command maps a by-hand row to `inactive` and a second `--apply` changes nothing; an `inactive`
      product isn't published; the out-of-stock tile counts products with no stock at the shop.

### 7. One home for each fact: copy, verify, drop
- [ ] **Two server releases**, so there is never a period with two writable copies, and no migration is designed to
      fail:
  - **Release 1:** the additive migrations (steps 1 to 5's tables and columns: `product_units`, `shop_unit_prices`,
    `unassigned_barcodes`, the unit columns on lines, the widened columns, `shops.expiry_touched_at`), and code that reads and writes **only the new homes**: nothing writes a column that
    release 2 drops (the architecture test, step 4). After deploying, in maintenance mode, the commands below run for
    every business, then `permissions:sync`, then maintenance mode ends.
  - **Release 2:** the drop migrations. Each still runs the
    `catalogue:verify-units` check across all businesses first and refuses with "Run catalogue:copy-to-units and
    catalogue:verify-units for every business first" if it fails: a safety net only, which the normal path never
    trips.
  - **A fresh database** (CI, SQLite tests, a new install) runs both releases' migrations in one go without stopping:
    with no rows, every check passes.
- [ ] **The commands, in release 1:**
  1. **`php artisan catalogue:copy-to-units --business=<id>`** (dry run by default; `--apply` writes; idempotent):
     - a base unit for every variation: name from the product's `unit`; precision from that name (`piece`, `pcs`,
       `each`, `unit` → 0; `kg`, `g`, `liter`, `litre`, `l`, `ml`, `meter`, `metre`, `m` → 3; anything else → 0,
       listed); **the variation's existing barcode, unchanged**, so an old app scanning it still sells one base
       unit; for the default variation of a product **without** real variations, the product's barcode when the
       variation has none (today's behaviour); the variation's `selling_price` and `wholesale_price`;
       `is_base`, `can_sell`, `can_buy`, `is_default_purchase` true;
     - shop price overrides to `shop_unit_prices` for the base unit: `shop_variation`'s, else `shop_product`'s for
       the default variation;
     - `shop_product.cost_price` to `shop_variation.cost_price` where empty; `shop_product.reorder_level` to
       `shop_variation.reorder_level` where empty and different from the product's;
     - the product's `cost_price` to its default variation's where that is empty;
     - existing `sale_items` and `return_items`: `unit_id` = the base unit, `base_quantity = quantity`;
     - **a product-level barcode on a product with real variations is not moved** onto its hidden default variation:
       it goes to `unassigned_barcodes` (`business_id`, `product_id`, `barcode`, timestamps; `BelongsToBusiness`;
       unique per business), and the report lists it with the product and its variations;
     - **it lists every difference before applying**: a product whose price or cost differs from its default
       variation's (the variation's is kept; the product's is recorded in the audit log); a `shop_product` override
       that differs from the `shop_variation` one (the `shop_variation` one is kept); the unassigned barcodes; the
       precision given to each unit name; a barcode clash (which stops it).
     UAT, 27 October 2026: 2 products differ; 33 products with variations carry a product barcode (to
     `unassigned_barcodes`); no overrides, no clashes.
  2. **`php artisan catalogue:verify-units --business=<id>`** (read-only): every variation has one base unit; every
     line has a unit and a base quantity; every value release 2 drops equals its new home, or is a difference the
     copy listed and resolved; a product barcode is either on a unit or in `unassigned_barcodes`. It prints the
     counts per table, lists the unassigned barcodes as **warnings**, and exits non-zero only on a gap.
  3. `products:clear-out-of-stock-status` (step 6) and `businesses:set-type` (step 5) run in the same window. Stage 11b
     owns `pricing-rules:check-customers`; stage 11c owns `stock:ledger-start`. **This stage owns
     `catalogue:copy-to-units`, including the copy into `shop_unit_prices`.**
- [ ] **Release 2's drops:** `products.selling_price`, `wholesale_price`, `cost_price`, `barcode`;
      `product_variations.selling_price`, `wholesale_price`, `barcode`; `shop_product.selling_price`,
      `wholesale_price`, `cost_price`, `reorder_level`; `shop_variation.selling_price`, `wholesale_price`.
- [ ] **Assigning the waiting barcodes:** the product's edit page shows a "Barcode to assign" panel for each of its
      `unassigned_barcodes`: pick one of the product's variations' units to give it to, or drop it (with a reason);
      both audited. The products list has a "Barcodes to assign" filter. A screen, not a command, because the owner
      does it, and the product page is where unit barcodes are edited. **Until assigned, it isn't sent** (decided): the
      pull and the flat API give the product no `barcode` from it, so an old app scanning it finds nothing rather than
      selling the default variation. The panel's warning says "Scanning this barcode won't work until you assign it." The products list shows the same warning.
- [ ] **`products.stock_quantity`** was dropped in Phase 1b; only the setter and `$fillable` entry remain (step 4).
- [ ] **The old field names stay on the APIs, computed from the new homes:** product and variation `selling_price`,
      `wholesale_price` and `barcode` from the default (or that) variation's base unit; product `cost_price` from the
      default variation (gated as in stage 10, step 1f); the pull's `stock.selling_price` and `wholesale_price` from
      `shop_unit_prices` for the base unit; `stock.reorder_level` from `shop_variation`. Every reader in the app
      moves to the homes, found with a search and listed in the PR (the Purchase Costs screen, `hasPurchaseCost()`
      and its scopes, `sales:recompute-profit`, `products:repair-purchase-cost`, the online-store importers,
      `InventoryService`, inventory snapshots, the product page).
- [ ] Tests:
  - the copy's dry run lists the differences and changes nothing; `--apply` copies; a second `--apply` changes
    nothing;
  - after the copy, each UAT-like fixture variation's base unit has its old barcode, and an old-style push of a line
    scanned by that barcode (variation, no unit) is recorded as one base unit;
  - a product with real variations and a product barcode: the barcode goes to `unassigned_barcodes`, not the hidden
    default variation; verify warns and still exits zero; assigning it puts it on the chosen unit, and dropping it
    removes it, both audited; a product without variations keeps its barcode on its base unit; an unassigned barcode
    is in neither the pull nor the flat API, and appears on its unit once assigned;
  - verify exits non-zero while a variation has no base unit or a value differs, and zero after the copy;
  - each drop migration refuses if verification fails (the safety net) and passes after the copy; on an empty
    database both releases' migrations run in one go;
  - the flat API and v1 contract tests pass with the computed fields.

### 8. The sync contract and the flat API
- [ ] **The convention** (see "Contract"): new decimal fields are strings; existing numeric fields keep their type;
      whole values are encoded as integers.
- [ ] **Encoding is explicit, per API:** `App\Support\Quantities\Encode`:
  - `flat($q)`: a JSON number, an `int` when whole, a float otherwise;
  - `pullWhole($q)`: a whole number, rounded down (`stock.quantity`, `stock.reorder_level`);
  - `exact($q, int $precision)`: a string with the unit's decimals (new fields, `/api/v1` `quantity_exact`,
    `base_quantity`).

  The pull (`SyncPull::entity()`) and the `/api/v1` receipt (`SaleReceipt`) build their arrays by hand, so each
  quantity there calls a helper. **The flat API returns models, not resources**, so it gets one explicit step: a
  middleware on the flat API group, `EncodeFlatQuantities` (beside Phase 7's `ShapeApiV1Response`), applies `flat()`
  to the quantity keys listed in `Encode::FLAT_KEYS` (`quantity`, `stock_quantity`, `reorder_level`,
  `quantity_ordered`, `quantity_received`, `quantity_remaining`, `quantity_before`, `quantity_change`,
  `quantity_after`), and only where the value is a scale-3 decimal string from the cast. New `base_quantity` fields on
  the flat API are encoded with `exact()` where they are added.
- [ ] **Pull:**
  - a new entity `units`: `uuid`, `variation_uuid` (null for a hidden default variation, as in `stock`),
    `product_uuid`, `name`, `factor` (string, "24.000"), `precision`, `barcode`, `selling_price`, `wholesale_price`,
    `is_base`, `can_sell`, `can_buy`, `sort_order`, `deleted_at`, `updated_at`. **Deleted units are sent as updates**
    (soft-deleted units included in the query, `deleted_at` set, `can_sell` and `can_buy` false), and **never listed
    in `deletions`**, so past sales and receipts on the till still show them and no variation key is needed there. An
    old cursor's first pull sends every unit;
  - `stock` rows keep `quantity` and `reorder_level` as **whole numbers, rounded down** (an old app shows slightly
    less than exists, never more), and gain `base_quantity` and `base_reorder_level` (strings, three decimals),
    `sellable_quantity` and `expired_quantity` (strings) and `next_expiry` (the earliest unexpired expiry date, or
    null);
  - products and variations keep `selling_price`, `wholesale_price`, `barcode` and `unit`, computed (step 7);
    variations gain `track_batches`;
  - settings gain `stock.consumption`;
  - no cost field anywhere (stage 10, step 1f).
- [ ] **Push sale lines** gain `unit` (a unit uuid, optional) and `base_quantity` (a string, optional), and take
      `quantity` as a number or a decimal string:
  - **backward compatible:** a line without a unit is the base unit; an integer quantity is valid; without
    `base_quantity` the server computes it;
  - **the till's base quantity wins:** when a line carries `base_quantity` and it differs from `quantity × factor`
    (the unit was edited between the till's last pull and the push), the server records the till's figure (what the
    customer saw and the stock that left) and a `unit_factor_mismatch` issue on the review (the till, the sale, the
    unit, both figures);
  - **a unit is found by `ProductUnit::resolveFor()`** (step 2): business-scoped, soft-deleted units included, so a
    unit deleted since still resolves and the sale is applied;
  - **an unknown unit ends on the review, then with a discard:** units are created only on the server, so a uuid the
    server never had (or another business's, or another variation's) will never resolve. The item is `refused` with
    `unknown_unit`, disposition `hold` (stage 10's classes), and the server records an `unknown_unit` issue with the
    raw line (the till, the sale's `client_uuid`, the unit uuid, the quantity, the person). The till keeps the sale in
    its outbox, marked, until a manager discards it with stage 10's `outbox_item_discarded` (`item_type = sale`),
    which is reviewed and audited. `unknown_unit` is one more reason that leads there; this stage adds no discard
    item of its own.
- [ ] **Flat API:** `POST /api/sales` items accept `unit_id` (optional; base unit when missing) and decimal
      quantities by the unit's precision. Every quantity field in responses (`quantity` on sale and return lines,
      `stock_quantity`, `reorder_level`) stays a JSON number: an `int` when whole (cast before encoding, since
      `json_encode(2.0)` gives `2.0`), a decimal only for a genuinely fractional value, which exists only once a
      business uses a unit with precision above 0. Responses gain `unit` (`id`, `name`, `factor`) and `base_quantity`
      (string); products gain `units`. The product search finds unit barcodes.
- [ ] **`/api/v1`** receipts: lines keep `quantity` a JSON number (`Encode::flat()`: an `int` when whole), and gain
      `unit_name`, `quantity_exact` (`exact()`, the unit's decimals) and `base_quantity` (`exact()`, three decimals).
      All additions: the v1 contract fixtures stay unchanged.
- [ ] Tests:
  - a `decimal(15,3)` value of `2.000` is encoded as `2` in the flat API, in the pull's `stock.quantity` and in the
    `/api/v1` receipt's `quantity`; `2.500` is `2.5` in the flat API and the receipt's `quantity` (with
    `quantity_exact` `"2.500"` for a kg line), and `2` in `stock.quantity` with `base_quantity` `"2.500"`;
  - a model's own JSON gives the scale-3 string (`"2.000"`), so only the helpers turn quantities into numbers;
  - **every emitted quantity goes through a helper:** a test runs every pull entity, the `/api/v1` receipt and every
    recorded flat API endpoint against a fixture with fractional quantities, and fails on any quantity key that is a
    raw `"2.500"` string where a number is expected, or a number where a string is expected; and every widened column
    name (step 1) is either in `Encode::FLAT_KEYS` or listed as not on the flat API;
  - an older app's push (integer quantities, no unit, no `base_quantity`) still records the sale in base units;
  - a push with a crate unit takes 24 bottles per crate and records the price as sent;
  - a push whose `base_quantity` disagrees with the server's calculation records the till's figure and a
    `unit_factor_mismatch` issue;
  - an unknown unit is refused (`hold`) and on the review as `unknown_unit`; another business's unit uuid is refused
    the same way (an isolation test); a soft-deleted unit still resolves and the sale is applied; the held sale is
    ended by an `outbox_item_discarded`, which is audited and on the review;
  - a pull carries units, and stock with both `quantity` (whole) and `base_quantity`; an old cursor gets every unit on
    its next pull;
  - a unit deleted on the server arrives on the till as an update (`deleted_at` set, `can_sell` and `can_buy` false),
    never in `deletions`, and a past receipt using it still renders its name;
  - an expired batch shows in `expired_quantity`, and its row is pulled again after the shop's midnight;
  - the flat API contract test and the v1 contract test pass.

### 9. Housekeeping
- [ ] **SKUs:** `generateSku()` retries on a duplicate-key error rather than relying on its check-then-insert.
      Remove the redundant uuid assignment in `Product::boot()`.
- [ ] **Money in `Product`:** profit and margin (now from the default variation) use the same money helper as sales,
      not float arithmetic on decimal strings.
- [ ] **FIFO and FEFO only** (decided): `App\Enums\CostingMethod` and `App\Services\CostingService` (an empty stub)
      are deleted; `sale_item_stock_allocations` is dropped in release 2 (its migration refuses if the table has rows:
      0 on UAT), with its model. A search finds no reader of any of them. Weighted average is a follow-up.
- [ ] Domain rules (`pos-domain-rules`) and CLAUDE.md: units and base quantities, decimal quantities, precision and
      the `Quantity` cast, the `bcmath` rule, one home for each fact, `UnitPrice`, the cost order, expiry and FEFO,
      the factor lock. CLAUDE.md's "stock quantities are integers" line changes.
- [ ] `docs/flutter/`: the contract convention, the new pull entities and fields, the push line fields, the
      discard item, the compatibility rules.
- [ ] **`docs/flutter/09-products.md`** (the behaviour steps 6 and 8 change; the low-stock section is stage 11c's).
      Exact correction:
  - "Product Statuses": the `out_of_stock` row is removed, with "Out of stock is per shop: read `stock_quantity`.";
  - "Create Product" validation: `status` becomes "nullable, in: `active`, `inactive`, `discontinued`";
    `stock_quantity` becomes "nullable, numeric, min:0, up to the base unit's decimals; received as opening stock at
    the cost given";
  - "Show Product": responses gain `units` (`id`, `name`, `factor` as a string, `precision`, `barcode`,
    `selling_price`, `wholesale_price`, `is_base`, `can_sell`); quantities stay JSON numbers (an integer when whole).
- [ ] **`docs/flutter/10-enums.md`, "ProductStatus"** (step 6): the `out_of_stock` row is removed, and a line added:
      "Out of stock is worked out per shop from stock; it is no longer a status."
- [ ] **Notes on the old documents this stage replaces**, under each title, once the stage ships (why: they are the
      pre-SaaS design, and readers should land on what was built):
  - `docs/implementation/05-products-variations.md`:
    > **Superseded (stage 11).** Built differently: the catalogue belongs to the business, not a shop; every product
    > has a hidden default variation; per-shop stock is in `shop_variation`; attributes are one `attributes` table
    > (module 29); variations are edited in the product form; units, decimal quantities, barcodes on units and
    > per-shop reorder levels are in stages 11 and 11c. Every finding and where it went: [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).
  - `docs/implementation/20-fifo-costing.md`:
    > **Superseded (stage 11).** The "Status: COMPLETE" below was not accurate: `CostingService` was an empty stub,
    > `CostLayerController` was removed, and nothing read `CostingMethod`. FIFO costing was built in stage 1b
    > (`StockLedger`); FIFO and FEFO only, with `CostingMethod`, `CostingService` and `sale_item_stock_allocations`
    > removed, and four-decimal unit costs are in stage 11; nightly snapshots for past valuation in stage 11c; batch
    > history and stock age in stage 12. Every finding and where it went: [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).
- [ ] **Comments in the three old migration copies in `docs/migration/`**, after `<?php` (why: they look like live
      migrations, and two describe tables or columns this stage removes):
  - `2026_02_03_175344_create_product_variations_table.php`: "// Superseded: the clean baseline (SaaS stage 1a)
    replaced this copy; variations' prices and barcodes move to product_units in stage 11. See
    docs/saas/stages/TRACEABILITY-stock.md.";
  - `2026_02_03_193008_create_sale_item_stock_allocations_table.php`: "// Superseded: never used; cost_allocations
    (SaaS stage 1b) records what each line took. The table is dropped in stage 11. See
    docs/saas/stages/TRACEABILITY-stock.md.";
  - `2026_02_04_190456_add_wholesale_price_to_products_table.php`: "// Superseded: the product's price columns are
    dropped in stage 11; prices live on product_units. See docs/saas/stages/TRACEABILITY-stock.md."
- [ ] **`docs/saas/pos-saas-plan.md`, Phase 1b** (the plan wins over stage files, so it is kept true): the line
      "`sale_item_stock_allocations` stays, unused." becomes "`sale_item_stock_allocations` stays, unused (dropped in
      stage 11, decided 27 October 2026)." when release 2 drops the table.

## Contract (paired app changes)

**The convention**, which every field in this stage follows:
- **New decimal fields are strings:** `base_quantity`, `base_reorder_level`, `sellable_quantity`,
  `expired_quantity`, `factor`, and the push's `base_quantity`.
- **Existing numeric fields keep their type.** The pull's `stock.quantity` and `stock.reorder_level` stay whole
  numbers, rounded down (the safe direction: an old app shows slightly less than exists, never more).
  `pricing_rules.min_quantity` / `max_quantity` stay integers.
- **The flat API's quantities stay JSON numbers**, and so does the `/api/v1` receipt's `quantity`. Whole values are
  cast to `int` before encoding, because `json_encode(2.0)` produces `2.0`, which a strict integer parser can reject.
  Only genuinely fractional lines produce decimals, and those exist only once a business uses a unit with precision
  above 0. The exact value is in the new string fields (`quantity_exact`, `base_quantity`).
- **Encoding is explicit:** `Encode::flat()`, `pullWhole()` and `exact()`; a model's own JSON is the scale-3 string.

**The paired app release isn't scheduled.** The server stays backward compatible with the app in shops (additions
only, old fields kept, Phase 7's codes for older versions). What the paired app adds, without a date:
- stores `units`, and prices a unit by `UnitPrice`'s order (stage 11b adds the cart quote);
- looks barcodes up in its units, adding that unit (a crate's barcode adds a crate);
- sends `unit`, the quantity in that unit and its own `base_quantity` on every sale line;
- refuses a fraction of a precision-0 unit at the till, so a `fractional_quantity` flag comes only from an older app,
  or from a unit whose precision changed on the server since the till last pulled;
- on `unknown_unit` (disposition `hold`), keeps the sale marked in its outbox and lets a manager (holding
  `sales.void`) discard it with a reason, pushing stage 10's `outbox_item_discarded`;
- keeps deleted units (`deleted_at` set) for showing past sales and receipts, finds them on lookup as
  `ProductUnit::resolveFor()` does, and never offers them for sale or purchase;
- never shows an unassigned barcode (it isn't sent);
- shows stock from `base_quantity` in the largest sensible unit, and warns when `sellable_quantity` is short (expired
  stock) or an item's `next_expiry` is near;
- prints the unit on receipts.

What the app's release must check is in [app-release-checklist.md](./app-release-checklist.md#stage-11).

**Compatibility rules the server keeps** (pinned by tests): a push line without `unit` is the base unit; an integer
`quantity` is valid; without `base_quantity` the server computes it; the pull's whole-number fields stay whole;
product and variation `selling_price`, `wholesale_price`, `barcode` and `unit` stay, computed from the base unit;
each variation's existing barcode is its base unit's; the flat API's quantities stay numbers.

**Minimum app version** (stage 10's `X-App-Version`, set in `config/sync.php` when the paired app ships): units and
`base_quantity` are read from apps at or above the stage 11 release; from older apps, lines are the base unit. No stage
11 refusal code reaches an older app on the push: `unknown_unit` needs a unit, which an older app never sends.

**What an older app can't do:** sell by any unit but the base unit (a crate's barcode is a new value, not in its
catalogue, so it says "not found" rather than selling a crate as one bottle); see fractional stock exactly; warn about
expired stock. The server refuses no offline sale for these reasons.

## Schema

| Change | Data carried across |
|---|---|
| Stock quantities widened to `decimal(15,3)` (the tables in step 1) | Values unchanged; row counts and sums checked |
| Unit costs widened to `decimal(15,4)` (`cost_layers`, `cost_allocations`, `sale_items.unit_cost`, `stock_movements.unit_cost`, `product_variations.cost_price`, `shop_variation.cost_price`) | Values unchanged (two decimals become four) |
| `product_units` (new), with `deleted_at` | `catalogue:copy-to-units`: one base unit per variation, with its barcode and prices |
| `shop_unit_prices` (new) | `catalogue:copy-to-units`: `shop_variation`, else `shop_product`, overrides (none on UAT) |
| `sale_items.unit_id`, `base_quantity`; `return_items.unit_id`, `base_quantity` | `catalogue:copy-to-units`: the base unit, `base_quantity = quantity` |
| `unassigned_barcodes` (new) | `catalogue:copy-to-units`: product barcodes of products with real variations (33 on UAT) |
| `shops.expiry_touched_at` (nullable timestamp) | None; NULL touches a shop's expired rows once |
| Dropped (release 2): `products.selling_price`, `wholesale_price`, `cost_price`, `barcode`; `product_variations.selling_price`, `wholesale_price`, `barcode`; `shop_product.selling_price`, `wholesale_price`, `cost_price`, `reorder_level`; `shop_variation.selling_price`, `wholesale_price` | Copied by `catalogue:copy-to-units`, checked by `catalogue:verify-units`; each drop refuses until the check passes |
| `cost_layers.batch_number`, `expiry_date` (nullable) | Existing batches have none |
| `product_variations.track_batches` boolean, default false | Off for every existing variation |
| `businesses.settings['stock']['consumption']` (no column): `fifo` or `fefo` | Missing means `fifo` |
| `ProductStatus::OUT_OF_STOCK` removed | `products:clear-out-of-stock-status` maps rows to `inactive` first (none on UAT) |
| `stock_adjustment_items.cost_layer_id` (nullable), `unit_cost` (nullable, `decimal(15,4)`) | None; past adjustments named no batch |
| `businesses.type` (`general_retail`, `supermarket`, `wholesale`, `pharmacy`, `agro_vet`, `hardware`) | `businesses:set-type` gives existing businesses `general_retail`; settings unchanged |
| `stock_adjustment_items.unit_id`, `quantity` `decimal(15,3)`, `base_quantity` | `catalogue:copy-to-units`: the base unit, `base_quantity = quantity` |
| Dropped: `sale_item_stock_allocations`; deleted: `CostingMethod`, `CostingService` | Table empty on UAT; the drop refuses otherwise |
| `sync_issues` types `fractional_quantity`, `expired_stock`, `unknown_unit`, `unit_factor_mismatch` | None (a string column); discards use stage 10's `outbox_item_discarded` |
| `ApiErrorCode` case `unknown_unit` (push, `hold`) | None (an enum) |
| Scheduled `stock:touch-expiring`, every 15 minutes | None beyond `shops.expiry_touched_at` |

**The sync contract changes (step 8):** the pull gains the entity `units`, the stock fields `base_quantity`,
`base_reorder_level`, `sellable_quantity`, `expired_quantity` and `next_expiry`, the variation field `track_batches`
and the setting `stock.consumption`; push sale lines gain `unit`, `base_quantity` and decimal `quantity`; deleted units
arrive as updates;
the `/api/v1` receipt gains `unit_name`, `quantity_exact` and `base_quantity`. All are additions (see "Contract").

## Acceptance criteria

**Quantities and units**
- [ ] Every stock quantity is `decimal(15,3)` and every unit cost per base unit `decimal(15,4)`; existing values are
      unchanged; the ledger uses no floats.
- [ ] Every variation has exactly one base unit; Panadol (tablet, strip ×10, box ×100), fertiliser (kg to three
      decimals, bag ×50) and soda (bottle, crate ×24) work as described in "Goal".
- [ ] 3 crates of 24 sold by scanning the crate barcode take 72 bottles and charge the crate price; 2.5 kg of
      fertiliser is sold from a 50 kg bag's stock; a fraction of a tablet is refused online and flagged offline.
- [ ] A used unit's factor can't be changed, by any route.
- [ ] Stock screens show the largest sensible unit and the base ("12 Crate + 5 Bottle").

**One home**
- [ ] Each price, barcode, stock level and cost has one home; the dropped columns' values are all carried across;
      the API fields are computed (`UnitPrice`).

**Business and adjustments**
- [ ] Business types set defaults only; existing UAT businesses are `general_retail` with their settings unchanged.
- [ ] Adjustment lines take any unit; an increase without a cost is at an unknown cost.
- [ ] `CostingMethod`, `CostingService` and `sale_item_stock_allocations` are gone.
- [ ] An adjustment can take a named batch, and an increase can carry a cost from someone allowed to set costs.

**Batches**
- [ ] A batch is expired from the shop's midnight after its expiry date, the same everywhere (sales, pull, reports);
      month-only expiries are the month's last day.
- [ ] With FEFO, the earlier expiry is taken even when received later; a pharmacy or agro-vet starts with FEFO.
- [ ] An expired batch is never sold: the sale goes below zero and is flagged, and the till learns of the expiry on
      its next pull after the shop's midnight, through only the rows that changed.

**Catalogue**
- [ ] "In stock" is answered per shop, in base units; stock value is the cost of the batches held, with unknown-cost
      and expired stock shown apart. Opening stock is a batch at the cost entered.
- [ ] `OUT_OF_STOCK` is gone; former rows are `inactive` and hidden from online stores.

**Contract and checks**
- [ ] The contract convention holds: `2.000` is `2`, `2.500` is `2.5` on the flat API and the v1 receipt and `2`
      with `"2.500"` in the pull; every emitted quantity goes through a named helper.
- [ ] A missed midnight is caught up by the next expiry touch; two runs touch once.
- [ ] Product barcodes of products with real variations wait in `unassigned_barcodes` until the owner assigns or
      drops them; nothing is lost when release 2 drops `products.barcode`.
- [ ] Deleted units reach the till as updates and past receipts still show them.
- [ ] An older app's integer push still records sales in base units, and its barcode scans still sell one base unit.
- [ ] A push with an unknown unit is held and reviewed, and ends with an audited `outbox_item_discarded`; a till's base quantity
      wins over a changed factor, and the mismatch is reviewed.
- [ ] The flat API and v1 contract tests pass; no flat API field changes type; the app's recorded requests still
      succeed against both releases.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass; new routes appear in
      `ResourceMatrixTest`.

## Risks

- **Decimal quantities touch the ledger's busiest tables and every caller.** Mitigation: the widening is a type change
  with no value change, rehearsed on the restored snapshot; the ledger's tests run on SQLite and MySQL; `bcmath`
  everywhere.
- **SQLite and MySQL treat decimals differently** (SQLite returns numbers, MySQL strings). Mitigation: the `Quantity`
  cast normalises on read; both suites run.
- **Dropping columns is one-way.** Mitigation: copy and verify run in release 1, the drops in release 2; each drop
  re-checks as a safety net; a snapshot is taken before each release.
- **The window between the releases:** the old columns are still there but unread and unwritten. Anything that still
  wrote them (a raw query the search missed, a queue worker running release 0's code, the legacy importer) would
  write values release 2 then drops. Mitigation: the architecture test forbids references to them; queue workers are
  restarted on deploy; release 2's safety net re-runs verification, which catches a value that differs from its home;
  the window is kept short (days, not weeks).
- **The precision guessed from a unit name may be wrong** for a UAT product. Mitigation: the copy lists what it gave
  each name; a precision is a form edit, and stock values don't change.
- **Old apps see whole-unit stock** for fractionally stocked items. Accepted until the paired release, whose date
  isn't set.
- **A till's base quantity is trusted over the server's** when they differ. Bounded by the factor lock (a used unit
  can't change), so a mismatch means a unit edited before its first sale reached the server; each one is reviewed.
- **The expiry touch runs on the scheduler.** If `schedule:run` isn't running (CLAUDE.md: nothing proves production
  runs it), tills learn of expiries late, though sales on the server never take expired stock; the next run catches
  up from `expiry_touched_at`.

## Rollout (UAT)

**Release 1** (additive migrations; code that reads and writes only the new homes):
1. Take a snapshot of UAT (`db:snapshot`). Restore it into `rehearsal_testing`.
2. Deploy release 1 to the rehearsal and run the migrations (all additive; none stops).
3. Maintenance mode on. For every business, in this order (all owned by this stage):
   1. `businesses:set-type --type=general_retail` (dry run: 10 businesses), then `--apply`;
   2. `products:clear-out-of-stock-status` (dry run: none), then `--apply`;
   3. `catalogue:copy-to-units` (dry run: check the list: 2 products differ, 33 unassigned barcodes, the precision per
      unit name, no clash; it also fills `shop_unit_prices`: none on UAT), then `--apply`;
   4. `catalogue:verify-units` (must exit zero; the 33 barcodes are warnings).
4. `permissions:sync` (nothing new from this stage; check nothing changes). Maintenance mode off.
5. **The expiry touch:** run `stock:touch-expiring` once by hand (it touches nothing: no batch has an expiry yet, and
   it sets `expiry_touched_at` on every shop); check `schedule:list` shows it every 15 minutes. It decides per shop
   timezone itself, so the scheduler entry needs no timezone.
6. **Check the server against the app's recorded requests** on the rehearsal: the Phase 7 and stage 10 sync tests run
   unchanged, and the recorded pushes and pulls (integer quantities, no units, variation barcodes) and the flat API
   contract fixtures succeed. The app's own checks wait for its release
   ([app-release-checklist.md](./app-release-checklist.md#stage-11)).
7. The user runs steps 2 to 5 on UAT, after a fresh snapshot.

**Release 2** (the drops), once release 1 has run cleanly on UAT:
8. Take a snapshot. On the rehearsal (restored from it), run `catalogue:verify-units` for every business, then deploy
   release 2 and run the migrations: the drops (with `sale_item_stock_allocations`, empty) apply (their checks pass).
   Check every UAT variation's barcode is on its base unit, and the 33 product barcodes are still in
   `unassigned_barcodes` or assigned. Check the recorded requests again.
9. The user runs `catalogue:verify-units` and then the migrations on UAT.

Stages 11b and 11c follow, each with its own rollout; 11b's `pricing-rules:check-customers` and 11c's
`stock:ledger-start` are theirs.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push
checks (Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing remains open. The decisions of 27 October 2026 stand, including the second round (one home for each fact), the
third (product barcodes on products with variations) and the fourth (business types, counting in units, costing
methods). The pricing and snapshot decisions are in stages 11b and 11c.

## Follow-ups (not this stage)

- Prescription-only items, controlled drugs and restricted pesticides: a stage of their own,
  [15-regulated-products.md](./15-regulated-products.md).
- Weighted-average cost, built only if a customer needs it (decided 27 October 2026).
- `/api/v1` endpoints to manage units from the app.
- More units per row in the CSV import.
