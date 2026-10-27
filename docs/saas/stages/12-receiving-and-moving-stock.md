# Stage: Receiving and Moving Stock

> **Source:** stock intake and products review, plan review and Phase 7 alignment of 27 October 2026; decisions and
> split of the combined stage, 27 October 2026.
> **Branch:** `feat/stock-receiving-transfers`, from `main` once stages 11 and 11c are merged · **Depends on:**
> [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) (units, decimal quantities, batches,
> `track_batches`), [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md) (the Low stock
> page and `LowStockQuery`, step 6), [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md)
> (`release()`, `permissions:sync`, `cost_known`, clerks without costs) · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-12)
> · **Next:** [13-stocktakes.md](./13-stocktakes.md) (depends on this stage)
> · **Related:** [14-returns-in-reports.md](./14-returns-in-reports.md)

---

## Revisions

**Split from the combined stage (27 October 2026).** "Stock Intake, Products and FIFO Integrity" became three stages
(see [stage 10](./10-stock-correctness-and-voids.md)). This one receives and moves stock, on the units, decimal
quantities and batches of [stage 11](./11-units-quantities-and-batches.md).

From the combined stage:
- **1c** (goods received notes) is here, built on units: each line is entered in a unit the variation is bought in,
  with its base quantity; costs are entered per that unit or per base unit. `packs_received` / `units_per_pack` are
  gone.
- **Purchase-order lines in units** replace `packs_ordered` / `units_per_pack`.
- **1d** (transfers) is here, built on units, and uses stage 10's `release()` unchanged to cancel a sent transfer.
- **The last purchase cost** (from 1b) is here, with receiving.
- **The recall report** (decision of 27 October 2026) is new.
- **From 1e:** `docs/purchase-costs.md`, the old `StockIntake` accessors and duplicate relations.

**Stage 11's second round (27 October 2026):** GRN, purchase-order and transfer lines join the unit factor lock
(`ProductUnit::USED_ON`); GRN expiries can be entered as a month (stored as its last day); receiving, transfers and the
recall report judge expiry with stage 11's `Expiry` helper; existing purchase-order lines get their unit by a command
(`purchase-orders:copy-to-units`) rather than in a migration.

**Sixth round (27 October 2026):** stage 11 was split into 11, 11b and 11c; this stage depends on 11 and 11c (its
low-stock step builds on 11c's page and query). It doesn't need 11b. Rollout checks the server against the app's
recorded requests; there is no paired app change here.

**Fifth round (27 October 2026):** the changes outside `docs/saas/stages/` made during the review (notes on old
documents, comments in old migration copies, corrections to the app's documents, lines in the plan) were reverted:
planning rounds change only this folder. Each is now a planned item in the stage whose work makes it true, with its
exact text (step 7).

**Fourth round (27 October 2026):**
- **Decided (was "Points to confirm" 1):** stocktakes are [stage 13](./13-stocktakes.md), after this one.
- **Fast and slow movers** (the pre-SaaS stock-taking guidelines, section 12) join step 5 beside stock age.
- **The app's purchase-order document** (`docs/flutter/08-purchase-orders.md`) is updated with units (step 1).
- **Follow-ups:** `/api/v1` GRN and transfer endpoints, and supplier product codes, are nice-to-haves; they stay
  follow-ups. Traceability: [TRACEABILITY-stock.md](./TRACEABILITY-stock.md).
- Nothing remains open.

**Legacy design review (27 October 2026):** the pre-SaaS modules 08 (stock intake) and 20 (FIFO costing) promised a
per-batch view (sold, damaged, returned, profit) and a slow-moving stock report that were never built; both are added
to step 5 (batch history, stock age). The supplier's lot number and the GRN number are stated as different things
(step 2). One point to confirm (stocktakes).

**Third round (27 October 2026):**
- **Low stock gains On order, In transit and To order** on stage 11c's Stock → Low stock page, and "Create purchase
  order" pre-fills To order in the variation's default purchase unit, rounded up (step 6).
- **No migration waits on a command:** `purchase_order_items.unit_id` stays nullable in the database (no NOT NULL
  migration after `purchase-orders:copy-to-units`); new lines must name a unit, by validation (step 1).

---

## Goal

Stock arrives, moves and is traced in the right shop at the right cost. A delivery is received as one goods received
note, in the units on the supplier's invoice (cartons, bags, boxes), with the supplier's own costs, its freight and
duty, and the batch numbers and expiry dates on the packs; clerks count it and managers confirm the costs. Stock
moves between shops as a document, batch by batch, at the cost it carries. Every receipt keeps the product's last
purchase cost current. For any batch number, the business can say which sales, customers and shops took it.

## Before you start

Stages 10, 11 and 11c must be merged. Nothing remains open (see "Points to confirm").

## Decisions that apply

- **Context (27 October 2026):** no customers yet; new businesses include pharmacies, agro-vets and wholesalers.
  The UAT businesses are kept; every change to their data is a command with a dry run and `--apply`, or a migration
  that carries every row across with counts, rehearsed first.
- **Stock intake (decided 27 October 2026):**
  - **Existing stock intakes stay as read-only history.** New deliveries are received as goods received notes
    (GRNs); nothing existing is rewritten.
  - **Receiving updates the product's cost price** to the last purchase cost (landed, ex VAT, per base unit).
  - **Clerks count, managers confirm costs.** A clerk records what arrived; it is in stock and saleable at once, at
    a provisional cost. A manager then confirms the costs from the supplier's invoice, and the server re-costs those
    batches, including sales already made from them. Clerks hold no cost permission (stage 10, step 1f).
  - **Cost order:** the shop's batches first; `shop_variation.cost_price` as the fallback (stage 11).
  - **Transfers between shops** are documents that carry each unit's batch cost.
- **Units and quantities (decided 27 October 2026, stage 11):** GRN, purchase-order and transfer lines record a
  `unit_id`, a quantity in that unit and the `base_quantity` the ledger moves; quantities are `decimal(15,3)`,
  validated by the unit's precision; unit costs per base unit are `decimal(15,4)`, money totals in cents.
- **Batches (decided 27 October 2026, stage 11):** batches carry a batch number and expiry date; a variation that
  tracks batches can't be received without both; expired batches are never sold.
- **The recall report (decided 27 October 2026):** for a batch number, the sales, customers and shops that took it,
  from `cost_allocations`.
- **Stage 1b:** every stock change goes through `StockLedger`.
- **Profit is net of VAT** ([docs/vat.md](../../vat.md)).

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), and stages 10 and 11's plans, 27 October 2026.

1. **One stock intake is one product.** `stock_intakes` has a single `product_id`, so a 30-line delivery is 30
   intakes, with no supplier invoice number to tie them together.
2. **An intake with no purchase order takes the last known cost** (`CompleteStockIntakeAction`: `fallbackCost()`),
   not the price paid. Most small shops receive without a purchase order, so cost of goods is wrong from the first
   delivery.
3. **Intake quantities are decimals cast to integers** on completion (`quantity_accepted` decimal(15,2)), so 2.5 is
   received as 2, silently.
4. **Intake numbers are random** (`'SI-'.Str::random(8)`, `StockIntake.php:76`): not a sequence, and not per
   business.
5. **Nothing records freight, duty or the supplier's invoice**, so delivery costs never reach cost of goods.
6. **Rejected quantity goes nowhere**: no return to the supplier, no record of the loss.
7. **There are no shop-to-shop transfers.** Moving stock is a write-off in one shop and an adjustment in the other,
   at the fallback cost rather than the cost of the stock that moved.
8. **Nothing keeps the last purchase cost.** The variation's `cost_price` is set by hand (Purchase Costs screen) or
   by the online-store importers; receiving doesn't touch it.
9. **Purchase-order lines have quantities but no unit** (`purchase_order_items.quantity_ordered` and
   `quantity_received`, `decimal(15,3)` after stage 11), so an order "for 10" doesn't say 10 of what.
10. **The manager template holds `stock-intake.approve`; the clerk's doesn't.** GRNs reuse `stock-intake.create`
    (count) and `stock-intake.approve` (confirm costs).
11. **`StockIntake::acceptanceRate()` / `rejectionRate()` return null** on the pages that show them, and the model
    has duplicate relations (`receivedByUser`, `completedByUser` beside `receiver`, `completer`).
12. **The pre-SaaS design** (module 08, reviewed 27 October 2026) made each delivery of one product a batch with its
    own generated `batch_number` (`BTH-{shop}-{date}-{n}`, counted per day, so two at once could collide), its own
    shipping and other costs, and stored counters (`sold_quantity`, `damaged_quantity`, `returned_quantity`) and a
    `status` that drift from what actually happened. It had no document tying a delivery's lines together, and used
    one field both as our reference and as the supplier's lot number. Its per-batch profit and expiring-stock views
    were never built.
13. **Ready to reuse:** `StockLedger::consume()` and `receive()` take decimal base quantities and carry batch numbers
    and expiry dates (stage 11); `release()` releases any consumer and takes the movement type from its caller
    (stage 10); allocations name their consumer polymorphically, so a transfer line needs no ledger change;
    `InvoiceNumberService` shows how a gapless per-shop series is kept.

**How other POS systems do it** (review of 27 October 2026): receiving as a document with many lines against one
location, received in full or in part against a purchase order, with shipping and duty folded into cost (Square,
Lightspeed); transfers between locations as documents that go draft, in transit, received, with partial receipt and
rejects (Shopify POS); a purchase unit converted to stock units at receipt (Odoo).

---

## Work

Every part of this stage keeps to the tenancy rules: business-owned records use `BelongsToBusiness`, shop-owned ones
`BelongsToShop`; numbers are per business and shop; `exists:` rules are business-aware; every new route gets its row
in `TenantIsolation/ResourceMatrixTest`; a manager reaches only the shops they work in.

### 1. Purchase-order lines in units
- [ ] `purchase_order_items.unit_id` (a unit the variation `can_buy`; the variation's default purchase unit when
      none is chosen), `base_quantity_ordered` and `base_quantity_received` (`decimal(15,3)`). `quantity_ordered`
      and `quantity_received` are in the line's unit; `unit_cost` is per the line's unit, as the supplier quotes it.
- [ ] Existing lines get the base unit and `base_… = quantity_…`, by a command in the pattern of stage 11's
      `catalogue:copy-to-units` (`purchase-orders:copy-to-units --business=<id>`, dry run, `--apply`, counts; on UAT
      no line has a fraction). `unit_id` stays nullable in the database, so no migration waits on the command; the
      purchase-order form request requires a unit on every new line, and a line still without one is read as the
      base unit until the command has run.
- [ ] **The factor lock covers purchase orders:** `purchase_order_items` joins `ProductUnit::USED_ON` (stage 11,
      step 2), so a unit on an order can't change its factor.
- [ ] The purchase-order form picks the unit per line and shows the base quantity ("10 Carton = 240 Bottle").
- [ ] Receiving against an order compares base quantities, so 10 cartons of 24 are fully received by 240 bottles,
      whatever unit the GRN line uses.
- [ ] Tests: an order of 10 cartons of 24 is fully received by 240 bottles counted as bottles, or by 10 cartons;
      existing lines read as base units after the migration.
- [ ] `docs/flutter/08-purchase-orders.md` is corrected in step 7.

### 2. Goods received notes (GRNs): clerks count, managers confirm costs
- [ ] **Schema:**
  - `stock_receipts`: `uuid`, business, shop (`BelongsToShop`), supplier (optional), purchase order (optional),
    `number` (`GRN-{shop code}-{year}-{n}`, a gapless series per shop and year, like invoice numbers; **null while
    `draft`, assigned on counting**, so a cancelled draft leaves no gap), supplier invoice number and date, receipt
    date, `status` (`draft`, `counted`, `costed`, `cancelled`), extra costs (`freight`, `duty`, `other`, ex VAT),
    `allocate_by` (`value` or `quantity`), notes, `counted_by`/`counted_at`, `costed_by`/`costed_at`,
    `created_by`, soft deletes.
  - `stock_receipt_lines`: receipt, variation (the product through it), `unit_id` (a `can_buy` unit; the
    variation's default purchase unit pre-filled), purchase-order line (optional), `quantity_received`,
    `quantity_rejected`, `quantity_accepted` (in the line's unit, `decimal(15,3)`, with a check constraint
    `quantity_accepted + quantity_rejected = quantity_received` where the database supports it),
    `base_quantity_accepted` (accepted × the unit's factor, computed), `rejection_reason`, `cost_basis` (`unit`: per
    the line's unit, as most invoices price it; or `base`: per base unit), `entered_cost` (as entered, ex VAT),
    `base_unit_cost` (per base unit, `decimal(15,4)`: `entered_cost ÷ factor` for `unit`, else `entered_cost`; null
    until costed), `vat_rate`, `landed_base_unit_cost` (`decimal(15,4)`, after the extra costs are shared),
    `line_total` (`decimal(15,2)`, the invoice line), `batch_number`, `expiry_date`, `cost_layer_id`.
    **`batch_number` is the supplier's lot number** as printed on the pack (what a recall names), never generated by
    us; our own reference for a delivery is the GRN number, and for a batch its GRN line. A line without a lot number
    has none (allowed unless the variation tracks batches).
  - `cost_layers.cost_pending` boolean (default false), `stock_receipt_line_id` and `source_layer_id` (nullable).
- [ ] **Validation:** quantities by the line's unit's precision (`QuantityForUnit`, stage 11): 2.5 cartons are refused,
      2.5 kg accepted. Received = accepted + rejected is checked in the form request too. When the variation
      `track_batches`, every accepted line needs a batch number and expiry date. The expiry is entered as a full date
      or as a month ("10/2026", stored as the month's last day, stage 11). A batch already expired by
      `App\Support\Stock\Expiry` (stage 11) at the receipt is refused unless the whole line is rejected.
- [ ] **The factor lock covers GRN lines:** `stock_receipt_lines` joins `ProductUnit::USED_ON`.
- [ ] **Counting (the clerk; `stock-intake.create`):** add lines by scanning a barcode (a unit's barcode picks that
      unit) or searching, or pull the remaining lines of a purchase order; enter quantities received and rejected in
      the line's unit. **Count** (`draft` → `counted`), in one transaction that is safe to submit twice (row lock and
      status check):
  - the GRN number is assigned;
  - each accepted line goes into the shop through `StockLedger::receive()` (`base_quantity_accepted`, with the
    line's batch number and expiry) at a **provisional cost** per base unit: the purchase-order line's cost ÷ its
    unit's factor if there is one, else the last purchase cost, else `fallbackCost()`; if none exists, the batch's
    cost is unknown (NULL) and sales from it are `cost_known = false` (stage 10) until costs are confirmed. Its batch
    is marked `cost_pending`;
  - purchase-order lines and status advance, in base quantities (partial receipts allowed; receiving more than
    ordered needs a second confirmation);
  - rejected units become a draft purchase return to the supplier, with the reason, in base units. **That return is
    marked `rejected_at_receipt`: shipping it doesn't take stock**, because the units never entered stock. It
    records the loss or credit only.

  The clerk never sees or enters costs: the cost fields and totals show only to `stock-intake.approve`.
- [ ] **Confirming costs (the manager; `stock-intake.approve`):** enter each line's cost as the supplier's invoice
      gives it, per the line's unit or per base unit (the screen shows both: "KES 2,400 a Carton = KES 100 a
      Bottle"), and the freight, duty and other costs. The screen shares the extra costs by line value (or by base
      quantity) into `landed_base_unit_cost`, and shows the receipt total against the supplier's invoice total, so a
      mismatch is visible before confirming. **Confirm** (`counted` → `costed`):
  - a new `StockLedger::recostLayer($layer, $baseUnitCost)` sets each batch's cost and re-costs **everything taken
    from it**: sale lines (`applyItemCost`, `recostSale`), supplier-return lines, adjustments, and transfer lines,
    whose receiving-shop batches (step 3, `source_layer_id`) are re-costed in turn, with their own sales. It clears
    `cost_pending` and recomputes `cost_known` on the lines it touched;
  - the last purchase cost is updated (step 4);
  - the GRN keeps a list of the sales it re-costed, with the profit before and after;
  - audited (both new models are `Auditable`).
- [ ] **Cancelling:** a `draft` can be cancelled; a `counted` receipt is cancelled only by reversing it (a write-off
      of what is left of its batches, with a reason), by an owner or manager. A cancelled counted receipt keeps its
      number.
- [ ] **Products that don't track stock** can't be put on a GRN line (stage 10, step 1a).
- [ ] **Old intakes** (`stock_intakes`) become read-only history: their pages stay; creating and completing intakes
      is removed from the menus and refused by the controller (410, "Receive deliveries as goods received notes").
      Their batches keep `stock_intake_id`.
- [ ] **Screens:** GRN list (filter by shop, status, supplier, "costs to confirm"); count screen (scanner first,
      large quantity inputs with the unit beside them, usable on a tablet); confirm-costs screen; a dashboard tile
      for managers with receipts waiting for costs, oldest first, with their age. The "cost incomplete" badge (stage
      10) links to the GRN waiting for costs when the batch came from one.
- [ ] **API:** the flat API has no intake routes and gains none. `/api/v1` receipt endpoints are a follow-up.
- [ ] Tests:
  - 12 cartons of 24 received at KES 2,400 a carton add 288 bottles at KES 100 each; at KES 1,000 a carton, each
    bottle costs 41.6667 and the 288 bottles still total exactly KES 12,000;
  - 3 cartons of 24 are received as 72 bottles; 4 bags of fertiliser as 200 kg, and 12.5 kg loose as 12.5 kg;
  - a 3-line delivery counted as one GRN puts all three in stock at provisional cost, saleable at once;
  - a clerk sees no costs and can't confirm; a manager confirms, and the batches and any sales already made from
    them are re-costed;
  - confirming re-costs a transfer taken from the batch and the sales made from it in the receiving shop;
  - a line with no PO cost, no last purchase cost and no fallback is counted at an unknown cost, and its sales are
    marked `cost_known = false` until confirmed (the badge links to the GRN);
  - freight shared by value and by quantity; the invoice-total check;
  - a partial receipt against a purchase order; over-receipt needs confirmation;
  - 2.5 cartons are refused; received ≠ accepted + rejected is refused;
  - Panadol boxes (a variation that tracks batches) can't be counted without a batch number and expiry; the batch
    number and expiry reach the cost batch; an expiry entered as "10/2026" is stored as 31 October 2026; a batch
    already expired at the receipt is refused unless rejected;
  - a unit used on a GRN line, a purchase-order line or a transfer line can't have its factor changed;
  - rejected units make a draft purchase return, and shipping it leaves stock unchanged;
  - counting twice is refused and stock moves once;
  - GRN numbers are gapless per shop and year, a cancelled draft takes no number, and two businesses' numbers
    don't collide;
  - old intakes can be read but not created;
  - isolation: another business's receipt is a 404, and its supplier, product, unit or purchase order ids are
    refused.

### 3. Transfers between shops
- [ ] **Schema:** `stock_transfers` (`uuid`, business, `from_shop_id`, `to_shop_id`, `number`
      `TRF-{from shop code}-{year}-{n}` assigned on sending, `status` `draft` → `sent` → `received` or
      `received_partly`, or `cancelled`; `sent_by`/`sent_at`, `received_by`/`received_at`, notes) and
      `stock_transfer_lines` (variation, `unit_id`, `quantity_sent` in that unit, `base_quantity_sent`,
      `base_quantity_received`, weighted `base_unit_cost` as sent (for display), discrepancy reason).
- [ ] **Permissions:** `stock-transfers.view`, `.create`, `.send`, `.receive`: the owner and the manager template;
      the inventory clerk gets view, create and receive. They reach existing businesses through `permissions:sync`
      (stage 10, step 0).
- [ ] **Sending** (someone who reaches the sending shop): `StockLedger::consume()` takes `base_quantity_sent` out of
      the sending shop's batches in the business's order (FIFO or FEFO, stage 11), skipping batches expired by the
      `Expiry` helper at the sending shop, as a sale does, with one allocation per batch (the transfer line is the consumer). The units are in transit: in neither
      shop. Sending more than the shop holds in unexpired stock is refused (a transfer never goes below zero).
- [ ] **Receiving** (someone who reaches the receiving shop): one `StockLedger::receive()` per source allocation, at
      that batch's cost, carrying its batch number and expiry (`source_type = transfer`, `source_layer_id` set), so
      cost and expiry arrive exact and the batch can be re-costed when the source is (step 2). The factor lock covers
      `stock_transfer_lines` (`ProductUnit::USED_ON`). A short delivery
      records the difference with a reason, goes on the till sync review (a new `transfer_discrepancy` issue), and
      the missing units are written off at their cost.
- [ ] **Cancelling:** a `draft` is cancelled outright. A `sent` transfer that hasn't been received is cancelled by
      `StockLedger::release()` on its lines (stage 10, step 3, unchanged), with the new movement type
      `TRANSFER_CANCEL`, which puts the units back into the sending shop's batches; by someone who reaches the
      sending shop, with a reason.
- [ ] **In-transit stock is valued and reported:** an "In transit" figure (units and cost) on the stock value
      report and the transfers list, so the business's total stock value doesn't drop while goods are on the road.
- [ ] A shop can't transfer to itself or to another business's shop (`exists:` is business-aware; the policy checks
      both shops). The receiving shop's `shop_variation` row is created if missing (stage 10, step 1a). Products that
      don't track stock can't be transferred.
- [ ] Tests: cost travels with the stock, batch by batch (3 at 60 and 2 at 70 arrive as two batches, not one at 64);
      batch number and expiry travel; a transfer of 2 crates moves 48 bottles; FEFO picks the earlier expiry and
      expired batches stay behind; partial receipt and discrepancy; in-transit stock counts in neither shop and shows
      in the in-transit figure; cancelling a sent transfer returns the units to the sending shop's original batches
      (`release()` on a transfer line, with the invariant holding); a manager of only one of the shops can act only on
      their side; isolation rows.

### 4. The last purchase cost
- [ ] Every confirmed receipt sets the variation's `cost_price` (per base unit) to the line's landed base unit
      cost, ex VAT, **unless a receipt with a later receipt date has already set it** (confirming an old GRN late
      doesn't overwrite a newer cost; `product_variations.last_cost_receipt_line_id` tells). It also sets
      `shop_variation.cost_price` for the receiving shop, so the shop's fallback stays current. Audited. The product
      page shows it as "Last purchase cost", with the date and the GRN, to those who may see costs.
- [ ] Tests: a confirmed receipt updates the last purchase cost and the shop's fallback cost; confirming an older
      GRN afterwards leaves both alone.

### 5. The recall report, batch history and stock age
- [ ] **For a batch number** (and optionally a product), per business: every batch with that number, including the
      copies transfers made (`source_layer_id`), and from their allocations:
  - the sales that took it: date, shop, invoice number, customer (name and phone where known), quantity in the unit
    sold and in base units;
  - transfers, supplier returns and write-offs that took it;
  - what is left in each shop, and in transit, and whether it is expired (by the `Expiry` helper, in each shop's
    timezone).
- [ ] Voided sales are listed apart (their units came back, stage 10); returns of the batch's units are shown against
      their sale.
- [ ] A screen under Stock (`stock.view`; customer phone numbers only for those who may see customers) and a CSV
      export. A manager sees only the shops they work in.
- [ ] **Batch history** (what module 08's batch page promised, from allocations rather than stored counters): for one
      batch, its GRN line (supplier, invoice, landed cost, lot number, expiry), then what became of every unit: sold
      (with the revenue and the margin on those sales), transferred (and to which shop's batch), returned to the
      supplier, written off (by reason), voided and returned by customers, and what is left. The figures always add up
      to what was received. Reached from the GRN, the recall report and the product's stock page.
- [ ] **Fast and slow movers** (the stock-taking guidelines, section 12): per shop and variation, base units sold per
      day over the last 30 and 90 days, and days of cover (sellable stock ÷ the 30-day rate); sorted fastest first or
      slowest first, with items that sold nothing in 90 days listed as "Not moving". `stock.view`. Step 6's low-stock
      page shows days of cover beside To order.
- [ ] **Stock age** (module 20's slow-moving report): per shop, the quantity and cost value of the batches still
      held, in age bands by receipt date (0–30, 31–90, 91–180, over 180 days), per product and in total, with the
      oldest receipt date. `stock.view`; costs only for those who may see them.
- [ ] Tests: a batch received at shop A, partly transferred to B and sold in both lists every sale with its customer;
      a voided sale is shown apart; another business's batch with the same number never appears; a manager of shop B
      only sees B's sales; a batch's history adds up to what was received after sales, a transfer, a write-off and a
      void; stock age puts a batch received 100 days ago in the 91–180 band, and a clerk sees quantities without
      values; the 30-day rate and days of cover are right for a variation sold 60 units in 30 days with 40 in stock
      (2 a day, 20 days); one with no sales in 90 days is "Not moving".

### 6. Low stock: what is coming, and what to order
- [ ] Stage 11c's **Stock → Low stock** page (`LowStockQuery`) gains, per variation, for the shop:
  - **On order:** open purchase-order lines for that shop not yet received (`base_quantity_ordered −
    base_quantity_received`, on orders that aren't cancelled or closed), in base units;
  - **In transit:** sent transfers to that shop not yet received (`base_quantity_sent`, status `sent`), in base units;
  - **To order:** shortfall − on order − in transit, never below zero.

  Both figures come from `LowStockQuery` itself (one query, joined), so the page, the dashboard tile and the alerts
  agree. A row whose To order is zero stays listed (it is still low), marked "Covered".
- [ ] **"Create purchase order"** for the selected rows pre-fills each line with the To order quantity in the
      variation's default purchase unit (`is_default_purchase`), **rounded up to whole units of it**: 30 pieces short
      with a carton of 24 is 2 cartons. A variation with no purchase unit but its base unit orders in base units,
      rounded up to the base unit's precision.
- [ ] Tests:
  - an open purchase order reduces To order, and a received or cancelled one doesn't;
  - a sent transfer counts as in transit until received, then as stock;
  - pre-fill rounds 30 pieces up to 2 cartons of 24;
  - To order never goes below zero.

### 7. Housekeeping
- [ ] Domain rules (`pos-domain-rules`) and CLAUDE.md: GRNs, transfers, the last purchase cost, the recall report.
- [ ] `docs/purchase-costs.md`: receiving now sets the purchase cost; the Purchase Costs screen is for corrections.
- [ ] **`docs/flutter/08-purchase-orders.md`** (the behaviour step 1 changes). Exact correction:
  - "Create Purchase Order" validation table gains `items.*.unit_id` ("nullable, exists:product_units,id in the
    business, a unit the variation can be bought in; the default purchase unit when missing");
    `items.*.quantity_ordered` becomes "required, numeric, more than 0, up to the unit's decimals" (was
    `min:0.01`); `items.*.unit_cost` is "per the line's unit";
  - the request example's lines gain `"unit_id"`;
  - "Show Purchase Order": lines gain `unit` (`id`, `name`, `factor` as a string), `base_quantity_ordered` and
    `base_quantity_received` (strings, three decimals); `quantity_ordered` stays a JSON number (an integer when
    whole).
- [ ] **Notes on the old documents this stage replaces**, under each title, once the stage ships:
  - `docs/implementation/08-stock-intake.md` (why: the pre-SaaS batch design; deliveries are now GRNs):
    > **Superseded (stage 12).** Never built as written. Stock is held per shop and variation in `shop_variation`
    > with FIFO or FEFO cost layers (stage 1b, `StockLedger`), and deliveries are goods received notes (stage 12).
    > No `stock_batches` table; batch state is derived, not stored; `batch_number` is the supplier's lot number;
    > selling below zero is allowed; expiry, FEFO and units are in stage 11. Every finding and where it went:
    > [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).
  - `docs/system/stock_taking_system_guidelines.md`, for its stock sections (2, 4, 5, 6, 8, 12 and 16) only (why:
    its per-intake batch design is what stages 11 and 12 replace):
    > **Partly superseded, for the stock sections only.** Stock isn't held in per-intake batches with stored costs:
    > it is per shop and variation, with FIFO or FEFO cost layers (stage 1b), received through goods received notes
    > (stage 12), in units with batches and expiry (stage 11). Every finding and where it went:
    > [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).
- [ ] **`docs/saas/pos-saas-plan.md`, "Deferred (after MVP)"** (the plan wins over stage files, so it is kept true):
      the line "Stock transfers between shops." becomes "~~Stock transfers between shops.~~ Built in stage 12
      ([12-receiving-and-moving-stock.md](stages/12-receiving-and-moving-stock.md))." when transfers ship.
- [ ] `StockIntake` (read-only history): replace `acceptanceRate()` / `rejectionRate()` with real accessors
      (`Attribute::make(get: …)`), since the old pages show them; remove the duplicate relations
      (`receivedByUser`, `completedByUser`) after moving their callers to `receiver` and `completer`.

## Contract

No change to the sync contract or the flat API. Stock received, transferred or written off reaches tills through the
pull's `stock` section, as every ledger change does. `/api/v1` endpoints for GRNs and transfers are a follow-up.

## Schema

| Change | Data carried across |
|---|---|
| `purchase_order_items.unit_id`, `base_quantity_ordered`, `base_quantity_received` | `purchase-orders:copy-to-units`: the base unit, base quantities equal to the quantities |
| `stock_receipts`, `stock_receipt_lines` (new) | None; old `stock_intakes` stay as history |
| `cost_layers.cost_pending` boolean, default false; `stock_receipt_line_id`, `source_layer_id` nullable | Existing batches are not pending and have none |
| `purchase_returns.rejected_at_receipt` boolean, default false | Existing returns take stock as before |
| `stock_transfers`, `stock_transfer_lines` (new) | None |
| GRN and transfer number series (per shop and year, like `invoice_sequences`) | None |
| `product_variations.last_cost_receipt_line_id` (nullable) | None |
| Permissions `stock-transfers.view`, `.create`, `.send`, `.receive` | Granted through `permissions:sync` |
| `StockMovementType::TRANSFER_CANCEL`; `sync_issues` type `transfer_discrepancy` | None |

## Acceptance criteria

- [ ] A 3-line delivery is one GRN: counted by a clerk it is in stock at once at provisional cost; confirmed by a
      manager its batches, and anything already taken from them (sales, transfers and the sales made from those),
      are re-costed to the invoice's landed costs.
- [ ] Lines are entered in the units on the invoice: 12 cartons of 24 at KES 1,000 a carton are received as 288
      bottles costing exactly KES 12,000 in total; 3 cartons of 24 as 72 bottles; a fraction of a carton is refused,
      a fraction of a kilogram accepted.
- [ ] A clerk never sees costs and can't confirm them; a manager can.
- [ ] Freight and duty are shared into the landed cost.
- [ ] A variation that tracks batches can't be received without a batch number and expiry; both reach the batch.
- [ ] Rejected units make a purchase return that doesn't take stock.
- [ ] Confirming a GRN sets the last purchase cost and the shop's fallback cost; an older GRN confirmed later doesn't
      overwrite them.
- [ ] Purchase orders are in units and received in base quantities.
- [ ] Old stock intakes can be read and not created or completed.
- [ ] A transfer carries each batch's cost, batch number and expiry from one shop to the other; a short delivery is
      recorded and written off; a cancelled sent transfer returns its units to the sending shop through `release()`;
      in-transit stock is valued.
- [ ] GRN and transfer numbers are gapless per shop and year.
- [ ] The recall report lists, for a batch number, every sale, customer and shop that took it, within the business.
- [ ] A batch's history accounts for every unit received; stock age shows what is held by age band.
- [ ] Stock → Low stock shows On order, In transit and To order per variation, and "Create purchase order" pre-fills
      To order in the default purchase unit, rounded up.
- [ ] Another business's receipts, transfers and batches are a 404 or never listed, and its ids are refused
      (isolation matrix).
- [ ] Pint, the full SQLite and MySQL suites, the isolation suite and both contract test sets pass.

## Risks

- **Re-costing on confirmation changes profit on sales already made**, including sales in another shop through a
  transfer, and possibly in a month already reported. Intended: they were costed provisionally. Mitigation: the GRN
  lists the sales it re-costed with profit before and after, and the managers' tile shows GRNs waiting for costs by
  age, so costs are confirmed while the month is open.
- **Old intakes are no longer created.** Anyone used to the intake screen is sent to GRNs with a message; the menu
  item moves.
- **Customer details in the recall report.** It lists names and phone numbers. Mitigation: only for those who may
  see customers, within their shops; the export is audited.

## Rollout (UAT)

1. Take a snapshot of UAT (`db:snapshot`).
2. Restore it into `rehearsal_testing` and run the migrations there. Then
   `purchase-orders:copy-to-units --business=<id>` for every business (dry run, then `--apply`), and check every
   purchase-order line has the base unit and base quantities equal to its quantities.
3. `permissions:sync` on the rehearsal: check a manager copy has `stock-transfers.*`, a clerk copy view, create and
   receive, and no cost permission.
4. Check the old intake pages still open on the rehearsal, and creating one is refused.
5. Check the server against the app's recorded requests (the purchase-order calls in the flat API contract fixtures
   succeed with lines that name no unit).
6. The user runs the migrations, `purchase-orders:copy-to-units` and `permissions:sync` on UAT. No app release is
   paired.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push
checks (Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing remains open. The decisions of 27 October 2026 cover receiving in units, costs per unit or base unit, batches,
the recall report and, in the fourth round, stocktakes as stage 13.

## Follow-ups (not this stage)

- `/api/v1` endpoints for GRNs and transfers, so a till or a phone can count deliveries.
- Supplier product codes and supplier prices per variation and unit (`supplier_products`), to pre-fill GRNs.
- Stocktakes are [stage 13](./13-stocktakes.md).
