# Traceability: Stock, Products, Inventory and Costing

**Sixth round (27 October 2026):** stage 11 was split into 11 (units), 11b (prices and discounts) and 11c (low stock,
alerts and snapshots); stage 15 became Regulated products; stocktakes (13) were written out in full; counting in the app
is stage 23. Rows below point at the new stages. Other areas have their own tables: reports, alerts, expenses,
security, billing and platform (`TRACEABILITY-*.md`).

Every finding from the documents outside `docs/saas/stages` on stock, products, inventory and FIFO costing, with
where it now lives. Reviewed against the code on `main` (after Phase 7, `832f684`) on 27 October 2026. Scope: the
product catalogue (products, variations, prices, barcodes, units), stock levels and movements, receiving and purchase
orders as they affect stock, adjustments, transfers and stocktakes, batches and expiry, low stock and reorder levels,
snapshots and valuation, cost layers and cost of goods. Other subjects are out of scope for this table.

**Status:** *planned* (work in a stage that isn't done), *done* (built in a finished stage), *no work* (built
differently on purpose; nothing to do), *dropped* (decided not to build, with the reason). Notes on old documents,
comments in old migration copies and corrections to other documents are planned items in the stage that makes them
true; planning rounds change only `docs/saas/stages/`.

## Documents reviewed

| Document | In scope | Result | Note or correction |
|---|---|---|---|
| `docs/implementation/05-products-variations.md` | Whole | Built differently (business-wide catalogue, hidden default variation, `attributes` table) | Planned: stage 11, step 9 |
| `docs/implementation/06-pricing-management.md` | Whole | Not built as written; replaced by pricing rules, stage 11's units and stage 11b's `PriceResolver` | Planned: stage 11b, step 7 |
| `docs/implementation/08-stock-intake.md` | Whole | Not built as written; cost layers (stage 1b), GRNs (stage 12) | Planned: stage 12, step 7 |
| `docs/implementation/09-inventory-tracking.md` | Whole | Partly built | Planned: stage 11c, step 5 |
| `docs/implementation/20-fifo-costing.md` | Whole | Built differently in stage 1b; "COMPLETE" claim inaccurate | Planned: stage 11, step 9 |
| `docs/implementation/21-stock-adjustments.md` | Whole | Built, with bugs (stage 10, step 1g) | Planned: stage 10, step 8 (updated in stage 13, step 6) |
| `docs/implementation/29-module-05-completion.md` | Whole (attributes) | Accurate: its table, model, controller and routes exist | No |
| `docs/system/stock_taking_system_guidelines.md` | Sections 2, 4, 5, 6, 8, 12, 16 | Stock sections built differently or planned | Planned (stock sections): stage 12, step 7 |
| `docs/saas/fifo-analysis.md` | Whole | Accurate: the agreed design, built in stage 1b | No |
| `docs/purchase-costs.md` | Whole | Accurate today; stages 11 and 12 update it | No |
| `docs/saas/pos-saas-plan.md` | Stock sections (1b, "Deferred") | Two lines out of date | Lines planned: stage 11, step 9; stage 12, step 7 |
| `docs/flutter/09-products.md` | Whole | Inaccurate on `POST /api/products` | No; correction planned: stage 10, step 8, stage 11, step 9, and stage 11c, step 5 (low stock) |
| `docs/flutter/08-purchase-orders.md` | Whole | Accurate today | No; correction planned: stage 12, step 7 |
| `docs/flutter/10-enums.md` | "ProductStatus" section only | Accurate today | No; correction planned: stage 11, step 9 |
| `docs/migration/2026_02_03_175344_create_product_variations_table.php` | Whole | Superseded by the clean baseline (stage 1a) | Comment planned: stage 11, step 9 |
| `docs/migration/2026_02_03_193008_create_sale_item_stock_allocations_table.php` | Whole | Table unused; dropped in stage 11 | Comment planned: stage 11, step 9 |
| `docs/migration/2026_02_04_190456_add_wholesale_price_to_products_table.php` | Whole | Column dropped in stage 11 (prices on units) | Comment planned: stage 11, step 9 |
| `docs/implementation/10-sales-transactions.md` | "Sale Item Stock Allocations" and FIFO deduction and voiding only | Built differently (stage 1b allocations); voids in stage 10 | No (out of scope document) |
| `docs/implementation/19-returns-refunds.md` | Restocking fields only | Restock built in stage 1b; `is_restocked` in stage 14 | No (out of scope document) |
| `docs/implementation/product-variations-status.md` | "Product Creation UI" section only | Built: the product form takes variations | No (out of scope document) |

## Findings

| # | Source (document, section) | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | 05, §1 Products table | Products belonged to a shop (`shop_id`) | Business-wide catalogue (stage 1a); `products.shop_id` means "first stocked at": stage 10, step 1a | planned |
| 2 | 05, §1 | `unit` was a label (`pcs`, `kg`) with no arithmetic | Units of measure: stage 11, step 2 | planned |
| 3 | 05, §1 | `low_stock_threshold` per product only | Per-shop reorder level: stage 11c, step 1 | planned |
| 4 | 05, §1 | SKU and barcode unique across all businesses | Unique per business (stage 1a); barcodes on units: stage 11, step 2 | done / planned |
| 5 | 05, §1 Attribute types and values | Two tables never built | `attributes` table (Phase 5 / module 29) | no work |
| 6 | 05, §3 ProductVariationController | Variations edited on their own screen | Edited in the product form (exists) | no work |
| 7 | 06, §1 `product_pricing` | Prices with effective dates and statuses never built | Pricing rules with start and end dates; `PriceResolver` quotes at the sale's time: stage 11b, steps 1 and 3 | planned |
| 8 | 06, §1 | Price history | `ProductUnit` and `shop_unit_prices` audited: stage 11, step 2; per-unit shop prices edited: stage 11b, step 1 | planned |
| 9 | 06, §1 `wholesale_min_quantity` | Wholesale by quantity | Quantity breaks in base units: stage 11b, step 2 | planned |
| 10 | 06, §1 `compare_at_price` | A struck-through "was" price | Dropped: an online-store display concern, outside the POS (plan principle); never used for cost | dropped |
| 11 | 06, §4 bulk update | Bulk price update never built | The CSV import's update mode changes prices (exists); per unit: stage 11, step 2 | no work / planned |
| 12 | 06, §6 ActivateScheduledPricing | Scheduled price activation | Rules take effect by date; no job needed | no work |
| 13 | 08, §1 `stock_batches` | One batch per product per delivery, no document | GRNs with many lines: stage 12, step 2 | planned |
| 14 | 08, §2 `generateBatchNumber()` | Batch numbers counted per day (could clash); one field for our reference and the supplier's lot | GRN number vs supplier lot number: stage 12, step 2 | planned |
| 15 | 08, §1 status and counters | `status`, `sold_quantity`, `damaged_quantity`, `returned_quantity` stored and drifting | Derived from allocations: stage 1b; batch history: stage 12, step 5 | done / planned |
| 16 | 08, §3 `allocateStock()` | A sale refused when stock runs out | Dropped: selling below zero is decided (stage 1b) | dropped |
| 17 | 08, §1 shipping and other costs | Delivery costs per batch | Landed costs on the GRN: stage 12, step 2 | planned |
| 18 | 08, §2 `getProfitAttribute()` | Per-batch profit never built | Batch history: stage 12, step 5 | planned |
| 19 | 08, §3 `getExpiringStock()`; 09 | Expiring-stock view never built | Expiry, FEFO, expiring list: stage 11, step 5 | planned |
| 20 | 08, §2 `isExpired()` | Expiry by the server's clock | `Expiry` helper in the shop's timezone: stage 11, step 5 | planned |
| 21 | 08, §2 `cost_per_unit` decimal(12,2) | Unit cost rounded to cents | Four decimals: stage 11, step 1 | planned |
| 22 | 09, §2 `stock_movements` | Movements written outside a lock | `StockLedger`: stage 1b | done |
| 23 | 09, §9 CheckLowStockJob | Daily low-stock check never scheduled | Runs from stage 11's expiry touch: stage 11c, step 2 | planned |
| 24 | 09, §5 CheckLowStockAction | Alerts never resolve; system runs credited to user 1 | Self-resolving, `source = system`: stage 11c, step 2 | planned |
| 25 | 09, §2 `inventory_snapshots` | Taken by hand only; integer; retail value from a column being dropped; NULL variation in a unique key | Nightly, decimal, valued through `UnitPrice`, exact catch-up: stage 11c, steps 3 and 4 | planned |
| 26 | 09, §10 permissions | `inventory.*` permissions never added | Dropped: the `stock.*` and `stock-adjustments.*` permissions are used (Phase 5) | dropped |
| 27 | 09 and code | Low stock worked out in six places, three ways | One `LowStockQuery`: stage 11c, step 1; On order, In transit, To order: stage 12, step 6 | planned |
| 28 | 20, §3 `CostingMethod` | LIFO, average and specific identification offered, unused | Removed: stage 11, step 9 (LIFO not allowed under IAS 2; average a follow-up if a customer needs it) | planned |
| 29 | 20, §6 `CostingService` | An empty stub | Removed: stage 11, step 9 | planned |
| 30 | 20, §2; 10, Sale Item Stock Allocations | `sale_item_stock_allocations` unused | Dropped table: stage 11, step 9 | planned |
| 31 | 20, §2 `cost_layers` | Layers per product, from intakes only | Per shop and variation, from any source: stage 1b | done |
| 32 | 20, §6 "valuation at any point in time" | Never built | Nightly snapshots, month-ends kept, rebuilt exactly when missed: stage 11c, steps 3 and 4 | planned |
| 33 | 20, §7 reports | COGS by period, cost variance, slow-moving by cost age never built | Slow movers and stock age: stage 12, step 5; cost variance: GRN re-costing list, stage 12, step 2; COGS by period: stage 14 (`SalesFigures`) | planned |
| 34 | 20, Status | "COMPLETE" claimed; `CostLayerController` removed | Note on the document: stage 11, step 9 | planned |
| 35 | 21, §2 items | Adjustments only move the default variation | Stage 10, step 1g | planned |
| 36 | 21, §5 | Before and after figures recorded at draft time | Stage 10, step 1g | planned |
| 37 | 21, §3 | Type and reason independent ("increase, lost" accepted) | The reason decides the direction: stage 10, step 1g | planned |
| 38 | 21, §5 approval | Every adjustment needs approval then a separate complete; `requiresApproval()` never called; self-approval possible | Second person for loss, theft, damage, expiry or above the threshold; never the creator; one step: stage 10, step 1g | planned |
| 39 | 21 and code | 16 UAT adjustments approved but never applied | `stock-adjustments:list-unapplied`, owner decides: stage 10, step 1g and Rollout | planned |
| 40 | 21, §5 numbers | Random numbers, unique across all businesses | Series per shop and year: stage 10, step 1g | planned |
| 41 | 21, §3 reasons | "Returned to supplier" loses the supplier's credit | Removed for purchase returns: stage 10, step 1g; customer goods for the supplier: stage 14, step 3a | planned |
| 42 | 21, §7 use cases | Physical counts done as adjustments | Stocktakes: stage 13; "recount" retired there | planned |
| 43 | 21 and code | Always the oldest batch; increases at the fallback cost | Named batch; cost entered or unknown: stage 11, step 5 | planned |
| 44 | 21 and code | Adjustment lines in base units only | Any unit: stage 11, step 3 | planned |
| 45 | Guidelines §5 | Each intake a unique stock batch with its costs | GRN lines and batches: stage 12, step 2 | planned |
| 46 | Guidelines §6 | Retail and wholesale prices with effective dates | Units' prices (stage 11, step 2) and pricing rules: stage 11b, steps 1 and 2 | planned |
| 47 | Guidelines §8 | Sale lines name the stock batch | Allocations per line (stage 1b); recall report: stage 12, step 5 | done / planned |
| 48 | Guidelines §12 | Low stock alerts | Stage 11, step 4a | planned |
| 49 | Guidelines §12 | Fast and slow moving items | Stage 12, step 5 | planned |
| 50 | Guidelines §16 | FIFO costing and stock adjustments | Stage 1b; stage 10, step 1g | done / planned |
| 51 | fifo-analysis, "not covered" | Opening stock as layers | Stage 1b; opening stock at the cost entered: stage 11, step 4 | done / planned |
| 52 | purchase-costs.md | Receiving doesn't set the purchase cost | Last purchase cost on receipt: stage 12, step 4; the document updated: stage 12, step 7 | planned |
| 53 | purchase-costs.md commands | Commands read `products.cost_price`, which stage 11 drops | Readers moved: stage 11, step 7 | planned |
| 54 | flutter/09-products, Create Product | Documents `variations`, `has_variations`, `status`, required `sku` and `category_id`; the API drops the first three silently | 422 instead of dropping, `status` accepted: stage 10, step 1f; document corrected: stage 10, step 8 | planned |
| 55 | flutter/09-products, Low Stock | `stock_quantity <= reorder_level` | One definition (sellable, per shop): stage 11c, step 1; document: stage 11c, step 5 | planned |
| 56 | flutter/10-enums, ProductStatus | `out_of_stock` listed | Removed: stage 11, step 6; document: stage 11, step 9 | planned |
| 57 | flutter/08-purchase-orders | Lines without a unit | Units on purchase-order lines: stage 12, step 1; document corrected: stage 12, step 7 | planned |
| 58 | pos-saas-plan, Deferred | "Stock transfers between shops" deferred | Transfers: stage 12, step 3; plan line: stage 12, step 7 | planned |
| 59 | pos-saas-plan, Phase 1b | `sale_item_stock_allocations` "stays, unused" | Dropped: stage 11, step 9; plan line: stage 11, step 9 | planned |
| 60 | pos-saas-plan, Deferred | Barcode label printing | Dropped for now: deferred after MVP by the plan | dropped |
| 61 | 19, restocking | `is_restocked`, `restocked_at` never set | Stage 14, step 2 | planned |
| 62 | 10, voiding | Voiding restores stock to the original batches | Stage 10, steps 3 and 4 | planned |

## Follow-ups in stages 10 to 15 on these subjects (11b and 11c included)

| Follow-up | Stage | Needed? | Where |
|---|---|---|---|
| Remove `CostingService`, `CostingMethod`, `sale_item_stock_allocations` | 10 | Yes (decided) | Stage 11, step 9 |
| Prescription-only items and a controlled-drugs register | 11 | Yes: pharmacies are a target business type, and the law expects these records | Stage 15, Regulated products (a configurable framework; unscheduled until a regulated customer is in view) |
| Weighted-average cost | 11 | No: only if a customer needs it (decided) | Follow-up in stage 11 |
| `/api/v1` endpoints for units and shop prices | 11, 11b | No: managed on the web | Follow-ups in stages 11 and 11b |
| More units per row in the CSV import | 11 | No | Follow-up in stage 11 |
| Fractional quantity-break thresholds | 11b | No (decided: whole base units) | Follow-up in stage 11b |
| Stocktakes | 12 | Yes (decided) | Stage 13 |
| `/api/v1` endpoints for GRNs and transfers | 12 | No: GRNs and transfers on the web | Follow-up in stage 12 |
| Supplier product codes and prices (`supplier_products`) | 12 | No | Follow-up in stage 12 |
| Link customer-return goods for the supplier to purchase returns | 14 | Yes (follows the "returned to supplier" decision) | Stage 14, step 3a |
| Counting stocktakes in the app | 13 | Yes, when the app's release is scheduled (decided 27 October 2026) | Stage 23 (outline) |
| Every alert type on one engine | 11c | Yes | Stage 18 |
