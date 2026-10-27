# Stage: Stocktakes

> **Source:** legacy design review (module 21, "physical stock counts and reconciliation") and the fourth round of
> decisions; written out in full in the sixth round, 27 October 2026.
> **Branch:** `feat/stocktakes`, from `main` once stage 12 is merged · **Depends on:**
> [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) (stock in units and batches, transfers in
> transit), [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) (units, decimal quantities,
> batches, expiry), [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md) (movements that
> name their batches, `stock_movements.layers`), [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md)
> (adjustment approval and its threshold) · **Next:** [23-stocktakes-in-the-app.md](./23-stocktakes-in-the-app.md)
> (counting from the Flutter app, an outline) · **Traceability:** [TRACEABILITY-stock.md](./TRACEABILITY-stock.md)

---

## Revisions

**Created 27 October 2026 (fourth round)** as an outline: stocktakes were a follow-up of the combined stock stage, then
"Points to confirm" 1 of stage 12, decided as a stage of their own after stage 12.

**Sixth round (27 October 2026):** written out in full. Decided: **a count never stops sales** (each line is compared
with the stock at the moment it was counted, adjusted for movements since; shops wanting a quiet count count after
closing); **counting is on the web, usable on a tablet**, in this stage; counting in the Flutter app is
[stage 23](./23-stocktakes-in-the-app.md). Both former points to confirm are answered. Fifth round's housekeeping
item (the module 21 note) is step 7.

---

## Goal

A shop counts its stock as a document: all of it, or a part. The counter records what is on the shelf, in the units it
is in, by batch where batches are tracked, without seeing what the system expects. Selling goes on while they count,
and sales made in between aren't mistaken for losses. A second person approves the differences, which are applied as
one adjustment at their cost. Afterwards, stock in the system is what was counted.

## Before you start

Stages 10, 11, 11c and 12 must be merged. Nothing remains open (see "Points to confirm").

## Decisions that apply

- **Stocktakes (decided 27 October 2026, fourth round):** full and cycle counts are documents, per shop; counting in
  units and by batch for products that track batches; blind counts (the counter doesn't see the expected quantity);
  sales during a count don't count as losses; variance approval by a second person, using stage 10's threshold; the
  "recount" adjustment reason is retired.
- **A count never stops sales (decided 27 October 2026, sixth round).** Each line is compared with the stock at the
  moment it was counted, adjusted for movements since. A shop that wants a quiet count counts after closing; the
  screen says so.
- **Counting is on the web, usable on a tablet (decided 27 October 2026, sixth round).** Counting in the Flutter app,
  with `/api/v1` endpoints and offline counts, is [stage 23](./23-stocktakes-in-the-app.md).
- **Adjustments record changes; stocktakes record counts** (decided 27 October 2026, stage 10).
- **Approval:** a second person holding `stocktakes.approve`, never the person who started or counted, when the
  stocktake's variance value is above `stock.adjustment_approval_threshold` (stage 10) or any line is a loss of a
  class that always needs a second person (none by default); otherwise the manager who submits applies it.
- **Every stock change goes through `StockLedger`**; differences are applied as one stock adjustment.
- **Configuration over code:** cycle-count scopes and the threshold are data the owner sets.
- **Live-data rule:** any change to existing data is a command with a dry run and `--apply`, rehearsed first.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026, and stages 10 to 12's plans.
- **No stocktake exists.** Counts are made as stock adjustments with the reason "recount" (`AdjustmentReason::RECOUNT`;
  1 on UAT), entered as a difference, with no allowance for sales made while counting.
- **Stage 10** (step 1g) makes adjustments name a variation, record their figures when stock moves, and need a second
  person above a threshold; "recount" stays a signed change until this stage.
- **Stage 11** gives units on lines, decimal quantities, batch numbers and expiry, and the factor lock.
- **Stage 11c** makes every movement that consumes, receives or releases record its batches (`layers`), so the stock of
  a variation, or of one batch, at any moment can be worked out from the current figures and the movements since.
- **Stage 12** puts stock in transit between shops (in neither shop).

---

## Work

Every part keeps to the tenancy rules: `stocktakes` and their lines are shop-owned (`BelongsToShop`); every new route
gets its `ResourceMatrixTest` row; a manager reaches only their shops.

### 1. The stocktake document
- [ ] `stocktakes`: `uuid`, business, shop, `number` (`STK-{shop}-{year}-{n}`, a gapless series per shop and year,
      assigned on starting), `scope` (`full` or `cycle`), `scope_filter` (JSON, for a cycle count: categories,
      products, or "not counted for N days"), `status` (`draft` → `counting` → `submitted` → `approved`, or
      `cancelled`), `blind` (always true in this stage), `started_by`/`started_at`, `submitted_by`/`submitted_at`,
      `approved_by`/`approved_at`, `stock_adjustment_id` (the adjustment it applied), notes, timestamps. `Auditable`.
- [ ] `stocktake_items` (the variations in scope, one row each): `variation_id`, `tracks_batches`, `status`
      (`to_count`, `counted`, `recount`).
- [ ] `stocktake_counts` (what was counted, any number per item): `stocktake_item_id`, `cost_layer_id` (the batch, when
      the variation tracks batches; NULL otherwise), `unit_id`, `quantity` (in that unit), `base_quantity`,
      `counted_by`, `counted_at`, `superseded_by` (a recount replaces a count). "2 cartons + 5 pieces" is two counts.
      The factor lock (`ProductUnit::USED_ON`) covers `stocktake_counts`.
- [ ] Starting a full count lists every tracked, active variation of the shop; a cycle count lists those its filter
      selects, or "50 items not counted in 30 days" (`stocktake_items` of past stocktakes say when each was last
      counted).
- [ ] Permissions `stocktakes.view`, `.count`, `.manage` (start, send back, submit, cancel), `.approve`, through
      `permissions:sync`: owner and manager templates all four; inventory clerk view and count.
- [ ] Tests: a full count lists every tracked variation of the shop and no other shop's; a cycle count of "not counted
      in 30 days" skips one counted last week; numbers are gapless per shop; another business's stocktake is a 404.

### 2. Counting on the web, on a tablet
- [ ] A count screen built for a tablet and a scanner: scan a unit's barcode (the unit is picked) or search; enter the
      quantity in that unit; for a variation that tracks batches, pick the batch (or enter its lot number and expiry,
      matched to a batch); several counters at once, each count stamped with who and when.
- [ ] **Blind:** counters never see the expected quantity, on screen, in the page's data or in exports, until the
      stocktake is submitted; a test pins it on the rendered page and its JSON.
- [ ] **Sales go on:** the screen says "Selling continues while you count. For a quiet count, count after closing."
- [ ] Tests: a counter enters 2 cartons and 5 pieces for a soda variation (53 bottles); a scanned crate barcode picks
      the crate unit; the expected quantity is in neither the page nor its data for a counter.

### 3. Expected at the moment counted
- [ ] For each count, `expected_at_count` = the ledger's quantity of that variation (and batch, when tracked) at the
      shop at `counted_at`: the current quantity minus the variation's movements after `counted_at` (for a batch:
      its current remaining, adjusted by the allocations and releases after `counted_at`, from stage 11c's `layers`).
- [ ] The item's **variance** = Σ its live counts' `base_quantity` − the expected quantity at its latest count. Sales,
      receipts, transfers and returns after a line was counted don't distort it, because they are on both sides.
- [ ] Stock in transit (stage 12) is in neither shop and isn't counted; batches already expired are counted like any
      other (the variance is real stock) and are flagged for a write-off.
- [ ] Tests: a variation counted at 47 while two are sold a minute later shows no variance; one counted at 45 with 47
      expected at that moment shows −2; a batch counted after a void returned units to it accounts for them.

### 4. Review and approval
- [ ] After counting, someone holding `stocktakes.manage` sees each item's counted, expected and variance quantities
      and value at cost (the cost the ledger would use; unknown-cost stock shown apart), may send items back to be
      counted again (`recount`), and submits.
- [ ] **Approval** (decided): when the stocktake's variance value (Σ |variance| × cost) is above
      `stock.adjustment_approval_threshold`, it waits for someone holding `stocktakes.approve` who is **neither the
      person who started it nor anyone who counted it**; otherwise the submitter's submission applies it. The policy
      refuses the excluded people, the owner included.
- [ ] Tests: a stocktake below the threshold applies on submit; one above waits, and its starter and its counters can't
      approve it while another manager can; sending an item back reopens it for counting.

### 5. Applying
- [ ] Approving creates one stock adjustment (reason `stocktake`, linked both ways) with a line per non-zero variance,
      in base units, naming the batch where tracked, applied through `StockLedger` in the same transaction: a
      shortfall consumes (from the counted batch, else the business's order), a surplus is received at the latest batch
      cost of that variation at the shop, or at an unknown cost when there is none (stage 11's rule: never silently at
      the fallback). Audited.
- [ ] After applying, the variation's stock equals what was counted plus the movements since each count.
- [ ] Tests: approving a stocktake of three variations leaves each at counted + movements since; a surplus of a variation
      with no batch cost is received at an unknown cost; approving twice is refused.

### 6. Retiring "recount" and reports
- [ ] `AdjustmentReason::RECOUNT` is no longer offered on new adjustments; the adjustment form says "For a count, use a
      stocktake." Existing adjustments keep it as history (1 on UAT).
- [ ] **Reports:** stocktake history per shop; variance by category and product over time; shrinkage value per month
      (variances from stocktakes plus loss and theft adjustments).
- [ ] Tests: "recount" is refused on a new adjustment and still shown on an old one; the shrinkage report adds a
      stocktake's loss and a theft adjustment in the same month.

### 7. Housekeeping
- [ ] **Note on `docs/implementation/21-stock-adjustments.md`** (added by stage 10): its last sentence becomes "Counts
      are stocktakes (stage 13), and the "recount" reason is retired." when this stage ships.
- [ ] Domain rules (`pos-domain-rules`) and CLAUDE.md: stocktakes, blind counts, expected at the moment counted.

## Contract

No sync or flat API change in this stage: counting is on the web. Stock changes from an applied stocktake reach tills
through the pull's `stock` section, as every ledger change does. Counting from the app is stage 23.

## Schema

| Change | Data carried across |
|---|---|
| `stocktakes`, `stocktake_items`, `stocktake_counts` (new) | None |
| Stocktake number series (per shop and year, like `invoice_sequences`) | None |
| Permissions `stocktakes.view`, `.count`, `.manage`, `.approve` | Through `permissions:sync` |
| `AdjustmentReason::STOCKTAKE` (new); `RECOUNT` no longer offered | Existing adjustments keep their reason |
| `ProductUnit::USED_ON` gains `stocktake_counts` | None |

## Acceptance criteria

- [ ] A shop counts all or part of its stock as a numbered document, in any unit and by batch where tracked, on a
      tablet, while selling continues.
- [ ] Counters never see expected quantities.
- [ ] Each line's variance is against the stock at the moment it was counted; sales in between aren't losses.
- [ ] Variances above the threshold need a second person who neither started nor counted; approving applies one
      adjustment through the ledger, at cost, and stock equals what was counted plus movements since.
- [ ] "Recount" is retired for new adjustments.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass.

## Risks

- **"Expected at count" depends on movements being complete.** Mitigation: every stock change goes through the ledger
  (stage 1b) and records its batches (stage 11c); the ledger's tests check it.
- **A count spread over days** sees more movements between counts. Mitigation: each count has its own moment; the
  review shows the time of each.
- **Counting a batch wrongly** moves stock between batches. Mitigation: the batch is picked from the shop's batches of
  that variation, or matched by lot number and expiry; unmatched lots are refused.
- **Approval slows a small shop** where the owner counts alone. Mitigation: below the threshold the submitter applies
  it; the owner can give `stocktakes.approve` to a trusted employee.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`; run the migrations (additive).
2. `permissions:sync` (stocktake permissions to the owner, manager and clerk copies).
3. On the rehearsal, count one shop of business 1 (a cycle count of ten items), submit, approve, and compare stock.
4. Check the server against the app's recorded requests (no change expected).
5. The user runs the migrations and `permissions:sync` on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing remains open. The two points of the outline are decided (a count never stops sales; counting on the web in
this stage).

## Follow-ups (not this stage)

- Counting in the Flutter app: [stage 23](./23-stocktakes-in-the-app.md).
