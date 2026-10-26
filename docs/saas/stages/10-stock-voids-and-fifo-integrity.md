# Stage: Stock and FIFO Integrity for Voided and Deleted Sales

> **Source:** FIFO review of 26 October 2026 (the stock ledger and its callers, after Phase 1b).
> **Branch:** `fix/stock-voids-fifo` · **Depends on:** Phase 1b (the `StockLedger` and FIFO cost batches)
> · **Related:** [11-returns-in-reports.md](./11-returns-in-reports.md) (returns in sales, cost and profit)

---

## Goal

Every sale that leaves the books gives its stock back. A voided sale returns its units to the cost batches they
came from. Its cost records are released, and it can no longer be re-costed. A sale that took stock can't be deleted
in a way that loses that stock. Lines whose cost isn't fully known are marked as such, so reports can say so
instead of quietly counting them as free. Voiding and deleting through the API need the same permission as on
the web.

## Before you start

No open question blocks this stage; the points were decided on 26 October 2026 (below).

## Decisions that apply

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
- `sellItem()` is called by all four sale paths, then `recostSale()`. The four are web, API, online-store orders
  and abandoned carts.
- **Stock is taken when the sale is created, whatever its status.** A pending or cash-on-delivery sale holds its
  stock too.

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
6. **There are no shop-to-shop transfers.** Moving stock is a write-off in one shop and an adjustment in the
   other, at the fallback cost rather than the cost of the stock that moved. This is out of scope here; see
   Follow-ups.

---

## Work

### 1. Who can void and delete, and for how long
- [ ] **API checks:** `Api\SaleController::void` calls `$this->authorize('void', $sale)`; `::destroy` calls
      `$this->authorize('delete', $sale)`, so web and API apply the same policy. Refusals stay
      `{success: false, message}` with 403 (flat API shape).
- [ ] **Only `sales.void` allows voiding:** remove the `sales.edit-all` branch from `SalePolicy::void`.
- [ ] **The owner only, by default:**
  - `RoleSeeder` no longer gives `sales.void` to the manager template. The owner (`admin`) holds every
    permission, so it keeps it.
  - A migration revokes `sales.void` from the shared manager template and every business's copy of it:
    - counts before and after;
    - each change audited;
    - Spatie cache flushed.
  - Owners can give it back to any role from the role screens; the template's "Reset to default" no longer adds
    it.
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
  - A small `App\Support\Sales\VoidWindow` works out, for a sale, whether it can still be voided and until when.
    The policy, the sale page and the API all use it, so they always agree.
  - It's on the business settings page, under "Sales", with the choices "Until the end of the day (default)", "A
    number of minutes" (with the number) and "No time limit". **Anyone with `settings.update` can change it**
    (decided), as with the page's other settings. Each change is audited with the old and new values.
  - `SalePolicy::void` refuses once the window has closed. Messages:
    - "This sale can only be voided on the day it was made. Use a return instead." (end of day);
    - "This sale can no longer be voided (limit: :minutes minutes). Use a return instead." (minutes).
  - **The owner is held to the limit too** (decided). Past the window, a return keeps the books and the register
    right.
  - The check uses the server's clock, read in the shop's timezone. For offline sales synced later, the window runs
    from the sale's own completion time. An offline sale synced the next day is already past the end of its day,
    so it can only be returned.
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
  - a member with `settings.update` changes the limit, and one without it can't;
  - another business's setting doesn't apply;
  - another business's sale is still 404;
  - the migration revokes from copies and leaves custom roles alone.
- [ ] Check the Flutter app's void/delete buttons against the permissions it reads (`authorization_helper.dart`).
      Record any paired change under "Contract". The app should hide Void past the window, and treat the 403 as
      a message.

### 2. `StockLedger::release()`: give a consumer's stock back
- [ ] New public method: `release(Model $consumer, StockMovementType $type, array $context = []): StockChange`.
      It undoes everything one consumer (a sale line, adjustment line or supplier-return line) took, in one
      transaction:
  1. Lock the `shop_variation` row (as `lockStock()` does), then the consumer's allocations.
  2. **Settled allocations** (with a `cost_layer_id`): add `quantity_allocated` back to that batch's
     `remaining_quantity` and clear its `consumed_at`. Units go back **into the batch they came from**, so FIFO
     order and cost stay exact.
  3. If that batch no longer exists (product deleted), create a new batch at the allocation's `unit_cost`, with
     `source_type = release`.
  4. **Provisional allocations** (no batch): delete them. The stock comes back but no batch does, which keeps the
     invariant.
  5. Raise `shop_variation.stock_quantity` by the total released and write one `StockMovement` of `$type`
     (`SALE_VOID`, new), at the weighted cost released.
  6. Delete the released allocations. Their history is kept in the movement and the audit log.
  7. Push stock to the shop's online stores after commit, as `changeStock()` does.
- [ ] `StockMovementType::SALE_VOID` (an addition). `CostLayer::SOURCE_RELEASE`.
- [ ] Unit tests on SQLite and MySQL:
  - a line taken from two batches goes back to both, in the right quantities;
  - a provisional line releases stock with no batch;
  - a mixed line (part batch, part provisional);
  - the invariant holds after each case;
  - releasing twice changes nothing the second time.

### 3. Voiding gives stock back
- [ ] Web and API `void` call `release()` for every line, inside the existing transaction, before the credit
      reversal.
- [ ] Line and sale cost and profit are left as they were sold. The sale is excluded from reports by its status.
      The figures stay for the audit trail and the voided-sales list.
- [ ] `settleProvisional()` skips allocations whose sale is voided. This is needed as a second guard for sales
      voided before this stage, because their allocations still exist.
- [ ] **A sale with a return that isn't rejected can't be voided** (decided): 422 with "This sale has returns.
      Process the return instead."
- [ ] The register's totals are recalculated after a void, on web as well as API (web doesn't do this today).
- [ ] Tests:
  - void restores stock and batches, on web and API;
  - a voided sale no longer moves when stock arrives;
  - void refused while a return exists;
  - the stores push is dispatched;
  - an audit row records the void.

### 4. Deleting a sale
- [ ] **A completed sale can't be deleted; it can only be voided** (decided). Web and API return 422
      with "Completed sales can't be deleted. Void the sale instead."
- [ ] A sale that isn't completed (draft or pending) can still be deleted. Before deleting, its lines are released
      as in step 2, since stock is taken at creation for every status.
- [ ] Remove or hide the "Delete" action for completed sales in the sales views. The server check is the guard.
- [ ] Tests:
  - deleting a completed sale is refused and changes nothing;
  - deleting a pending sale returns its stock, and then the sale is gone;
  - both on web and API.

### 4a. A Void button on the web sale page
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

### 4b. Void automatically when an online order is cancelled before dispatch
- [ ] When the WooCommerce or Shopify order sync (webhook or pull) moves an order to `cancelled`, and the order
      has a converted sale that isn't voided, the sale is voided with `release()` and the credit reversal, as in
      step 3.
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

### 5. Mark lines whose cost isn't fully known
- [ ] Migration: `sale_items.cost_known` boolean, default true. It records whether every unit was costed from a
      batch with a cost, or from a known estimate.
- [ ] `sellItem()` sets it from `StockChange::$costKnown`. `settleProvisional()` and `release()` recompute it from
      the line's remaining allocations.
- [ ] When settling against a batch with no cost, **keep the provisional estimate** as the allocation's cost and
      mark the line `cost_known = false`, rather than replacing the estimate with NULL (decided).
- [ ] `Sale::scopeCostIncomplete()` (sales with any line not known). Show a "cost incomplete" badge on the sale
      page and in the profit report. Link to Products → Purchase Costs.
- [ ] Backfill in the migration: `cost_known = false` for lines with an allocation whose `unit_cost` is NULL.
      Count the lines before and after.
- [ ] Tests: a line from a cost-unknown batch is marked; entering the cost and re-costing clears it (with
      `sales:recompute-profit`); the badge shows.

### 6. Repair sales voided and deleted before this stage
- [ ] `php artisan sales:restock-voided --business=<id>` (`RunsForBusiness`). For every voided sale whose lines
      still have allocations, it runs `release()` (dry run by default; `--apply` writes). It reports per sale: the
      lines, the units, the shops.
- [ ] `php artisan stock:orphaned-movements --business=<id>` (read-only) lists `SALE` movements whose sale no
      longer exists, so the business can correct stock by hand for sales deleted before this stage. Their
      allocations are gone, so nothing can be released automatically.
- [ ] Tests on fixtures: an old voided sale is restored once (a second `--apply` changes nothing). The orphaned
      list finds a deleted sale's movements.

### 7. Housekeeping
- [ ] Note in [pos-domain-rules](../../../.claude/skills/pos-domain-rules/SKILL.md) and CLAUDE.md:
  - voids release stock;
  - only `sales.void` voids, within the business's time limit;
  - completed sales can't be deleted;
  - `cost_known`.
- [ ] Leave `CostingService`, `CostingMethod` and `sale_item_stock_allocations` in place. Their removal is a
      separate clean-up, listed under Follow-ups.

## Schema

| Change | Data carried across |
|---|---|
| `sale_items.cost_known` boolean, default true | Backfilled false where an allocation has no cost |
| `sales.void` revoked from the manager template and its copies (data migration) | Counts before and after; custom roles untouched |
| `businesses.settings['sales']['void_window']` (no column): `end_of_day`, minutes (1–1,440) or `none` | Missing means the default, `end_of_day` |

`release()` writes existing tables.

## Acceptance criteria

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

**Permissions and time limit**
- [ ] Void and delete through the API need `sales.void` / `sales.delete`, as on the web.
- [ ] By default only the owner can void. A role the owner gives `sales.void` can void. `sales.edit-all` alone
      can't.
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
- [ ] The flat API contract test passes. A void or delete refusal is a new 403/422, which the Flutter app already
      handles as an error message. Any change is recorded for the app.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass. New routes or commands appear in
      `ResourceMatrixTest` where they apply.

## Risks

- **Concurrency:** a void racing a sale of the same variation. Mitigation: `release()` locks the `shop_variation`
  row first, in the same order as `consume()`, so they can't deadlock.
- **Repairing old voids changes stock figures shops have been working with.** Mitigation: a dry run listing every
  change, run on a UAT snapshot in `rehearsal_testing` first, applied per business by the owner.
- **Managers lose voiding in every business on deploy.** Owners must give `sales.void` back to roles that need
  it. Mitigation: the release note says so, and the migration lists, per business, the roles it changed.
- **Sales made just before midnight have little time to be voided.** Mitigation: the next day they go through a
  return, and a business can choose a number of minutes instead (up to 24 hours) or no limit.
- **Timezones:** counting the day in UTC would close Nairobi sales at 03:00 local time. Mitigation: the window is
  always worked out in the shop's timezone, and tested across midnight.
- **The Flutter app may show "Delete" on completed sales.** The server refuses, so the app shows an error. Record
  the paired change.

## Rollout (UAT)

1. `php artisan db:snapshot`.
2. `php artisan migrate --database=rehearsal_testing` on the restored snapshot. Check the `cost_known` backfill
   counts.
3. `sales:restock-voided --business=<id>` on the rehearsal (dry run, then `--apply`). Compare stock per variation
   before and after.
4. The user runs `php artisan migrate` and the repair on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push
checks (Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

All decided on 26 October 2026 (see "Decisions that apply"). Nothing remains open for this stage.

## Follow-ups (not this stage)

- Shop-to-shop transfers that carry the batch cost (consume in one shop, receive the same allocations' costs in
  the other).
- Remove `CostingService`, `CostingMethod` and `sale_item_stock_allocations`.
- Use the `sales.deleted_at` column (soft deletes) or drop it. `VatReportService` filters on it today, though it's
  never set.
