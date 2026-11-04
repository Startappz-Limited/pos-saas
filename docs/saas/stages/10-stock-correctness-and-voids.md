# Stage: Stock Correctness and Voids

> **Source:** FIFO review of 26 October 2026 (the stock ledger and its callers, after Phase 1b); stock intake and
> products review of 27 October 2026; plan review, Phase 7 alignment and its review, 27 October 2026; split of the
> combined stage, 27 October 2026.
> **Status:** final plan, 28 October 2026.
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

**Implementation decisions (3 November 2026).** Made while building steps 1g and 2 on `fix/stock-voids-fifo`; they
replace the earlier text where they differ, and are listed under "Decisions that apply" (3 November 2026): approval by
permission with notifications (shared by adjustments and voids), stricter adjustment rules, owner-only limits, void
requests, 422 for a closed window, the shop's timezone on its form, and a temporary refusal for pending sales with
payments until step 4.

**Split from the combined stage (27 October 2026).** "Stock Intake, Products and FIFO Integrity" became three
stages, because it mixed bug fixes in code that exists today with two new foundations (units and decimal
quantities, then receiving and transfers on top of them), and the decisions of 27 October changed the foundations:
- **This stage (10)** fixes what is wrong today, in whole units as now: the permission rollout (step 0), selling from
  the selling shop (1a), clerks and costs (1f), stock adjustments (1g), who may void and for how long (2),
  `release()` (3), voids, payouts and deletes (4, 5, 5a, 5b), till voids and held items (5c), invoice series (5d),
  `cost_known` (6), repairs (7) and housekeeping (8). Access and tenancy (users list, owner scope, shop access) are
  [stage 10a](./10a-access-and-tenancy.md) (eighth round), which depends on no stage (ninth round).
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

**Tenth round (28 October 2026):** final fixes (code facts checked 28 October 2026):
- A pending sale has no void window until it is completed; until then a holder of `sales.void` can void it at any
  time, and its payments are handled as in step 4 (Decisions, steps 2 and 5, Acceptance, Risks).
- `/api/v1` sale and receipt responses carry `voidable` (decided) beside `voidable_until`, which is null for a pending
  sale (steps 5b and 5c, Contract, Acceptance, Rollout; the app checklist).
- A re-rung sale takes the till's `client_uuid`, so items queued behind it apply to it; a re-push of the original
  answers `discarded` (step 5c, Contract, Acceptance).
- Stage 11's discard of a sale with an unknown unit needs `sales.void`, as here (decided; a note in stage 11).
- "Points to confirm" says one point is open and lists the ninth-round exception and the tenth round's decisions.
- Eighth-round `sync-issues.discard` line marked partly superseded.
- Step 4 test reworded to the session step 4 chooses.
- Header: status line.

**Ninth round (28 October 2026):** a second review of the whole stage, cross-checked against stages 10a to 23
(code facts checked 28 October 2026):
- Risks: a taken invoice number is discard-only (or re-rung), as step 5c and the Contract say.
- Third-round line on discards marked superseded.
- Acceptance: the duplicate payout criterion under "Voids from the till" removed; Repair names sales hard-deleted
  before this stage.
- Stage 10a depends on no stage (decided): it grants and revokes no permission, so it doesn't need step 0.
- Adjustment threshold value: Σ |quantity| × unit cost over the lines, so a mixed recount can't net to zero; a line of
  unknown cost needs a second person; stage 11 values by base quantity (step 1g).
- A found increase is costed at the latest batch cost at the shop, else unknown, as stage 13 does for surpluses
  (decided; step 1g). Today's code uses `fallbackCost()`.
- Which session pays a void's cash: the voiding till's open session; on the web, the one the person chooses; none open
  means a `refund_due` (step 4).
- `refund_due` can be marked refunded on the review until stage 14 replaces it (step 5c).
- A pending sale with payments can't be deleted; it can be voided, its window running from creation (decided; step 5).
- `sales.delete` stays with the owner only, as today (decided; step 2).
- Soft deletes: what must still see a deleted sale, and the raw queries that must leave it out (step 5).
- Discarding a held `sale` or `sale_payment` item needs `sales.void` (decided; step 5c, Contract).
- `release()` settles waiting below-zero sales against the batches it refills (step 3).
- Voids of eTIMS-signed invoices: linked to stage 22 (step 4, Decisions).
- The void window gains `session`, the new default, capped at the end of the day (decided; step 2).
- `invoice_number_taken`: "Discard and record as a new web sale" (step 5c).
- Web and flat API sales below zero record `negative_stock`; blocking such sales is a follow-up (decided; step 1a).
- Notes added to stages 10a, 11, 13 and 14, `TRACEABILITY-stock.md` and the app checklist (listed in "Points to
  confirm"); one point left open for stage 14.

**Eighth round (28 October 2026):** a review of the whole stage (code facts checked 28 October 2026):
- Acceptance: a held item is accepted with the till's own invoice number, or discarded (no "new number").
- `invoice_number_taken` is discard-only (decided): a taken number can never be kept, and is never renumbered (step 5c).
- A number in the web series is never accepted from a till (steps 5c and 5d).
- Deploy order: the backfill runs inside the migration that creates `role_revoked_permissions`, before any new-style
  sync, and skips this stage's new permissions; no deploy script exists, so the order is stated in Rollout (step 0).
- Contract "Push results": a held item waits until a manager accepts it on the server or discards it.
- `sync-issues.discard` (new, decided) replaces `sales.void` for discards from the till and the review screen.
  *(Partly superseded in the ninth round: a `sale` or `sale_payment` item needs `sales.void`.)*
- A `sale_void` recorded outside any lease is `outside_trading_lease`, class hold (step 5c).
- Deleting a pending sale: `Sale` uses soft deletes (decided), `release()` writes `SALE_DELETE`, the orphan report
  covers only sales hard-deleted before this stage (steps 3, 5, 7).
- Rollout step 4 checks a manager copy holds `sync-issues.accept` and `sync-issues.discard`.
- "A return that isn't rejected" is the wording wherever a return blocks a void.
- The adjustment threshold: at or above it needs a second person.
- Schema: the adjustment series key in `invoice_sequences`, the adjustment-number index per shop, `quantity_change`
  needs no column change, `sync_push_receipts.outcome` (new; receipts have no outcome column today).
- Step 5b: an auto-void also applies to a pending cash-on-delivery sale; `voidable_until` governs people's voids only.
- Goal, "Before you start", the decisions intro, "Points to confirm" and the split note cover every step and the 28
  October decisions.
- Superseded earlier lines marked (the price check is stage 11b's; no renumbering remains).
- Acceptance criteria cover every step, including 5d, payouts, `sync-issues.discard`, the deploy order, soft
  deletes, the web-series rule and voids outside a lease.
- Steps 1h, 1i and 1j moved to [stage 10a](./10a-access-and-tenancy.md) (decided).

**Seventh round (28 October 2026):** found the users-list buttons bug, decided that an owner's rights stop at their own
business and that shop access follows the invite (default shop on the Free Trial). That work moved to
[stage 10a](./10a-access-and-tenancy.md) in the eighth round.

**Sixth round (27 October 2026):**
- **Decided: a fiscal number, once printed or signed, is never changed by the server.** Accepting a held sale keeps
  the till's invoice number; the earlier plan to keep the till's number in a separate column and print a renumbering
  note was removed (step 5c).
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
  `sales.void`) replaces `sale_void_discarded`; its issue type replaces `void_discarded` (step 5c). *(Superseded:
  since the eighth round, by someone holding `sync-issues.discard`; since the ninth, `sales.void` again for `sale` and
  `sale_payment` items.)*
- **Every push refusal has one class** (retry, drop or hold), carried in a new `disposition` field on every result
  that isn't applied. Three Phase 7 refusals that meant "push the other item first" get their own codes
  (`customer_not_synced`, `sale_not_synced`, `cash_session_not_synced`), so that no code has two classes (step 5c,
  "Contract").
- **A refused sale no longer drops its void:** sales are held, so a queued void waits behind its sale and is
  discarded with it.
- **Follow-ups:** the server's price check is stage 11's (it needs `PriceResolver`). *(Superseded: since the sixth
  round it is stage 11b's.)*

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
took away. Clerks count stock; they don't see or set costs. Stock adjustments move the variation they name, with a
second person for losses and large values. (Steps 0, 1f and 1g.)

Nothing a till holds is lost or silently changed: every refusal has a class, a held item is accepted or discarded on
the review, a fiscal number is never changed, and tills and the web never share an invoice series. (Steps 5c and 5d.)

## Before you start

The decisions of 26, 27 and 28 October 2026 are below. One point is left for stage 14's review; nothing else is open
(see "Points to confirm").

Start from `main` after Phase 7 (`832f684`): steps 1a, 4, 5c and 5d change code Phase 7 added (`RecordSale`, the
sync push and pull, `TillPayments`, `InvoiceNumberService`). The "current state" lines below were checked against that
code on 27 October, and the eighth round's facts on 28 October 2026. Step 0 comes first: every later step that adds or
revokes a permission relies on it. [Stage 10a](./10a-access-and-tenancy.md) depends on no stage and can ship at any time (it grants and revokes no
permission, checked 28 October 2026).

## Decisions that apply

Decisions of 26, 27 and 28 October 2026, for steps 0 to 8. Each step says which apply; later rounds' decisions replace
earlier ones where they differ (the Revisions list says which). The decisions of 3 November 2026 (below, first) replace
any earlier text they contradict.

- **Decisions of 4 November 2026 (step 4, money on a void):**
  - **Approving a void and paying money out are separate.** Cash the sale's own open session counted leaves it by
    recalculation (the sale no longer counts). Any other cash is paid out at once **only from the voider's own open
    session in the shop**; otherwise (an approved void request, a flat API or web void by someone with no open session
    of their own, no session open) it becomes a **cash refund owed**. Cash is never taken from someone else's session
    automatically. Same rule on the web, the flat API and void-request approval. This replaces step 4's "a web void asks
    the person to choose among the shop's open sessions".
  - **Card, M-Pesa, bank transfer and cheque payments** each become a refund owed with their method and reference. A
    cheque's refund can be settled as "cheque returned" (an uncleared cheque) as well as paid.
  - **A voided credit sale:** payments made on it are refunded by their method under these rules; the credit reversal
    clears what the customer still owed. "Credit" is not money received and never creates a refund.
  - **Refunds owed** (`refunds_due`): a list per business and shop for owners and payers (Sales → Refunds owed);
    marking one paid or settled records the method, reference and, for cash, the payer's own open session, from which
    it is paid out then (refused with none: "Open your register to pay a cash refund."); audited. M-Pesa refunds are
    made outside the system and only recorded (a reference is required). Payers are those holding `sales.void` or
    **`refunds.approve`** (the existing refund-approval permission: owner and manager templates; the cashier's
    `sales.refund` is not enough), with access to the shop. They are notified when one is created, under the shared
    notification rule. Step 5c's `refund_due` sources (`late_payment`, `duplicate_payment`) use the same table.
  - **Noted, not fixed here:** payments collected after the sale (credit collections, M-Pesa at the till, deposits)
    aren't tied to a session (`sale_payments` has no session column), and a session's expected cash counts only
    completed cash sales by their total, so cash collected later never appears in any drawer's expected cash.
    Proposed fix: stage 14 (returns and refunds in reports), which already reworks session cash for refunds:
    `sale_payments.cash_register_id` set from the collector's open session, and `calculateExpectedBalance()` counting
    a session's cash payments rather than its cash sales' totals, with a backfill for open sessions only.
- **Decisions of 3 November 2026 (during implementation, steps 1g and 2):**
  - **Approval by permission, with notifications (shared by stock adjustments and void requests):**
    - approvers are the active members of the business who hold the approve permission there
      (`stock-adjustments.approve`, or `sales.void` for voids) and may work in the request's shop; never a role name;
      the owner can give the permission to anyone;
    - nobody approves their own request, except an owner when no other member is eligible: they approve with a
      required note, audited and flagged "self-approved" (a filter on the adjustments list); non-owners never do;
    - when a request is made, every eligible approver but its creator is notified (in the app under the topbar's
      bell, and by email; queued, sent once the request is saved; no push: the backend has none); the first approval
      or rejection settles it and the others' notifications are marked resolved; approving and rejecting are audited;
      rejecting needs a reason; the creator is told the outcome;
    - a request still pending after 24 hours gets one reminder to its approvers and is listed on the owner's
      dashboard (`approvals:remind`, hourly);
    - a pending request whose creator leaves stays pending and listed, never applies by itself, and the owner can
      settle it.
  - **Stock adjustments (step 1g):**
    - `stock-adjustments.create` and `stock-adjustments.approve` are separate: a creator holding approve applies on
      saving where the rules allow; a creator without it always waits. Owner and manager templates hold approve
      (add-only rollout through `GrantTemplatePermissions`, audited);
    - always wait, whatever the value: lost, stolen, damaged, expired, **quality issue, promotional giveaway**, and a
      **recount with any line lowering stock**. Apply on saving (known cost, under the threshold): found, a recount
      that only raises stock, and "other";
    - the threshold stays "at or above waits" (exactly KES 5,000 waits); the value is gross (Σ |quantity| × cost, never
      netted), and **cumulative**: one person's auto-applied adjustments at one shop on one shop-local day count
      together;
    - **changing the threshold is owner-only** (not `settings.update` alone), audited;
    - **cancel replaces delete**: a pending adjustment is cancelled (status `cancelled`) and keeps its number, so the
      series has no gaps; an applied, rejected or cancelled adjustment can't be edited or cancelled, full access
      included;
    - a pending decrease doesn't hold stock: the till keeps selling it, and **the till shows a non-blocking warning
      "N units pending removal"**, built where the till's stock display is touched (step 5c); stock is never held.
  - **Voids (step 2):**
    - `sales.void` stays off the manager template; the revocation keeps it on a manager copy whose owner gave it on
      purpose, as the audit trail shows (an attach by a person who isn't a platform operator);
    - **changing the void time limit (including "no limit") is owner-only**, audited;
    - **a void refused because the window has passed answers 422** `void_window_closed` with the time-limit message
      (a browser form goes back with the message); 403 stays for no permission. Web and flat API;
    - **void requests:** someone with `sales.create` and without `sales.void` asks, with a reason, inside the window;
      eligible approvers (holding `sales.void`, with access to the shop, not the requester) are notified under the
      shared rule and approve or reject on the web or the app; approval voids the sale, with the window checked at
      the request's time, so an approval up to **48 hours** after a timely request still voids it; after that the
      request expires and the requester is told. Holders of `sales.void` void directly;
    - **the shop's timezone** is on the shop form and flat API (optional IANA zone; else the business's, else
      Africa/Nairobi);
    - **temporary, until step 4 ships the refund rule:** a pending sale with payments can't be voided, nor a void of it
      requested: 422 "This sale has payments. It can't be voided until refunds are supported." (`sale_has_payments`).
      Step 4 removes the refusal and its tests.

- **Context (27 October 2026):** there are no customers yet; the system is being set up for new businesses,
  including pharmacies, agro-vets and wholesalers. The existing businesses on UAT are kept, so every change to
  existing data is a command with a dry run and `--apply`, rehearsed on a restored snapshot in `rehearsal_testing`
  first. Repairs whose only purpose was protecting customer history are dropped; repairs that keep the UAT
  businesses consistent stay.

- **Voids and deletes (decided 26 October 2026):**
  - **Only the owner can void by default.** `sales.void` is held by the owner role only; the owner can give it to
    other roles in their business. `sales.edit-all` no longer allows voiding.
  - **Completed sales can't be deleted, only voided.**
  - **A sale with a return that isn't rejected can't be voided.**
  - **Voiding has a time limit: by default, while the sale's register session is open, and never past the end of
    the day the sale was made** (midnight in the shop's timezone, so always within 24 hours). Each business can
    instead choose the end of the day, a number of minutes (up to 24 hours) or no limit at all. **Anyone with `settings.update` can change it**, and **it applies to the owner too.**
    (Changed on 26 October 2026 from 15 minutes to the end of the day; changed 28 October 2026, ninth round, to the
    register session: a sale with no session uses the end of the day, step 2.)
  - **A pending sale with payments can't be deleted, only voided** (decided 28 October 2026, ninth round). **A
    pending sale has no void window: its window starts when it is completed; until then, someone holding
    `sales.void` (the owner by default) can void it at any time** (decided 28 October 2026, tenth round).
  - **Only the owner deletes by default**, as today (`sales.delete`; decided 28 October 2026, ninth round).
  - **Which session pays a void's cash (decided 28 October 2026, ninth round):** a till's void, that till's open
    session; a web void, the open session the person chooses; none open, a `refund_due` (step 4).
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
  holds (a sale, a void, a payment, and so on), by someone holding `sync-issues.discard` (decided 28 October 2026;
  owner and manager templates), reviewed and audited. **A `sale` or `sale_payment` item needs `sales.void`** (decided 28
  October 2026, ninth round): discarding it removes goods and money the till recorded, a void without the void
  rules. Every push
  refusal has exactly one class: transient (retry), final and dropped, or final and kept for a person (hold).

- **Held items can be accepted (decided 27 October 2026, fourth round).** The till sync review gives a held push
  item two resolutions: **accept** (a manager holding `sync-issues.accept` applies it on the server, always with the
  till's own invoice number; a sale outside the lease is applied at the till's recorded time and flagged; a validation
  fault is applied as the table in step 5c says; `invoice_number_taken` and a web-series number from a till are never
  accepted, though a taken number's sale can be re-rung as a new web sale, step 5c) or **discard** (`outbox_item_discarded`, only for items that shouldn't exist). Either way it is audited, and the till learns of it on its next sync.
- **A fiscal number is never changed by the server (decided 27 October 2026, sixth round).** An invoice number, once
  printed on a receipt or signed (by eTIMS, [stage 22](./22-etims.md)), stays as issued. A correction is a credit
  note and a new sale, never a renumbering. Once eTIMS is on, a void of a signed invoice issues a credit note; see
  [stage 22](./22-etims.md), step 3.
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
  - needs a **second person** holding `stock-adjustments.approve`: loss, theft, damage, expiry, any adjustment
    whose cost value is **at or above** the threshold, and any line of unknown cost. **The creator can never approve
    their own;**
  - the cost value is Σ |quantity| × unit cost over the lines (decided 28 October 2026, ninth round), so a recount of
    +100 and −100 doesn't net to zero;
  - a found increase is costed at the latest batch cost of that variation at the shop, else at an unknown cost (decided
    28 October 2026, ninth round, as [stage 13](./13-stocktakes.md), step 5, does for surpluses;
    [stage 11](./11-units-quantities-and-batches.md), step 5, changes it to the cost entered, else unknown);
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

**Ninth round (checked 28 October 2026).** Gaps 20 to 22 moved to [stage 10a](./10a-access-and-tenancy.md).

23. **A found increase is booked at a cost nobody entered.** `StockAdjustmentService` receives an increase at
    `StockLedger::fallbackCost()`: the latest batch cost at the shop, else `shop_variation.cost_price`, else the
    variation's or product's `cost_price`, else NULL.
24. **Cash sessions are per register and day.** `CashRegister::getActiveRegister()` finds one open session per
    register for today's `register_date`; the web uses `R1` and a paired till its own register, so a shop can have two
    open sessions. `sales.register_id` (nullable) is the session a sale was made in.
25. **A part-paid cash-on-delivery sale stays `pending`** with payments: `RecordSalePayment` completes a sale only when
    it is paid in full.
26. **Only the owner can delete sales:** `SalePolicy::delete` needs `sales.delete` or `sales.delete-all`, and no
    template but `admin` (which holds every permission) has either.
27. **Raw queries on `sales` don't filter `deleted_at`** (`DB::table('sales')` and `join('sales')` in `SaleController`,
    `DashboardController`, `Api\ReportController`, `ReportService`, `BusinessDirectory`, `AssignProductShopFromSales`);
    only `VatReportService` does. `StockMovement::reference()` and the `sale()` relations of `SaleItem`, `SalePayment`
    and `SaleReturn` would hide a soft-deleted sale.
28. **Below-zero sales are recorded only from the push:** `SaleHandler` records a `negative_stock` issue; web and flat
    API sales below zero record nothing.
29. **`sync_issues` already has `resolved_at`, `resolved_by` and `resolution`** (`checked`, `kept`, `merged` today), so
    a `refund_due` can be closed without a new column.


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
- [ ] **One-time backfill, inside the migration** that creates `role_revoked_permissions` (decided 28 October 2026):
      it runs in that migration's transaction, so absences are recorded before any new-style `RoleSeeder` can run
      (`permissions:sync` runs after migrations). `php artisan roles:backfill-rollout` (dry run by default; `--apply`
      writes; `RunsForBusiness`) runs the same logic for reports and re-runs; it is idempotent. Kept: the UAT businesses
      lack the Phase 5 and 7 permissions. For each business copy and each permission its template holds that the copy
      lacks:
  - if the permission is one of those added in Phase 5 or 7 (`attributes.*`, `stock.view`, `registers.*`,
    `sync-issues.*`), grant it: these are the rollout's misses;
  - otherwise, record it in `role_revoked_permissions`: before this stage, an absence can't be told apart from a
    deliberate removal, so it is kept as one.
  - **this stage's new permissions are skipped** (`cash-registers.payout`, `sync-issues.accept`,
    `sync-issues.discard`): never recorded as revoked, so the first `permissions:sync` grants them to the manager
    copies;
  - The report lists, per business and role, what was granted and what was recorded as revoked; the migration prints
    the counts, and the command prints the full report.
- [ ] **`php artisan permissions:sync`**: runs `PermissionSeeder`, then `RoleSeeder`, then clears the Spatie cache.
      **No deploy script exists** (checked 28 October 2026: `composer.json`'s `setup` runs `migrate --force` for local
      set-up; nothing orders `permissions:sync`), so the deploy order is stated in Rollout: migrations first (the
      backfill runs inside them), then `permissions:sync`; step 8 documents it. Nothing runs `PermissionSeeder` on its
      own any more. Stages 11 and 12 add their permissions through it.
- [ ] **No `Gate::before` for the owner.** The owner holds every permission through the `admin` role, and a
      `Gate::before` returning `true` would skip `SalePolicy::void`'s time limit, which applies to the owner too
      (step 2). Already true: `Gate::before` lets only platform operators through (checked 28 October 2026); a test
      keeps it so.
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
  - `permissions:sync` run twice changes nothing the second time;
  - **the real order:** a business copy that lost a permission by hand before this stage, migrated (the backfill
    runs inside the migration) and then synced with `permissions:sync`, still lacks it; and the manager copy gains
    `cash-registers.payout`, `sync-issues.accept` and `sync-issues.discard`.

### 1a. Sell from the selling shop (a bug fix)
- [ ] A sale line's shop is the **selling shop**: the till's shop for a register-bound token, the request's
      validated `shop_id` on the flat API and the web POS, the order's shop for online orders. Never the product's
      `shop_id`. In `RecordSale` (flat API and sync push), `SaleController::store` and
      `ConvertAbandonedCartToSale`. `ConvertEcommerceOrderToSale` is already right (its lines take the sale's shop);
      a test pins it.
- [ ] The sale's own shop is the selling shop too, not its first product's owning shop.
- [ ] A product not stocked at the selling shop still sells: the shop's stock goes below zero, costed
      provisionally and flagged (`negative_stock`), as offline sales are.
- [ ] **Selling below zero is shown** (ninth round, gap 28): a web or flat API sale that takes a variation below zero
      records the `negative_stock` issue, as the push already does, so it is on the till sync review for holders of
      `sync-issues.view` (the owner and the manager template) and flagged on the sale page. Blocking such sales is a
      follow-up (decided 28 October 2026).
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
      nothing; the sales-by-shop report counts a cross-shop sale under the selling shop; a web sale below zero records
      a `negative_stock` issue and the owner sees it on the till sync review.

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
> **Revised 3 November 2026** (see "Decisions that apply"): approval by permission with notifications, stricter
> reasons, a cumulative and owner-only threshold, and cancel instead of delete. Done; see PROGRESS.
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
      is lost, stolen, damaged or expired, when any line's cost is unknown, or when the adjustment's cost value is
      **at or above** `businesses.settings['stock']['adjustment_approval_threshold']` (default KES 5,000; set on
      the business settings page under "Stock" by `settings.update`, audited).
  - **The cost value** (decided 28 October 2026, ninth round) is Σ over the lines of |`quantity_change`| × the line's
    unit cost: for a decrease, the cost `consume()` would use (its batches oldest first, `fallbackCost()` for a
    shortfall); for an increase, the cost it is received at (below). A recount of +100 and −100 counts 200 units, not
    0. Stage 11 values lines by `base_quantity` ([stage 11](./11-units-quantities-and-batches.md), step 3), and
    [stage 13](./13-stocktakes.md) values a stocktake the same way (Σ |variance| × cost, step 4).
  - When it is false, the creator (holding `stock-adjustments.create`) applies it on saving.
  - When it is true, it waits for someone **other than the creator** holding `stock-adjustments.approve`. The policy
    refuses the creator, whatever they hold, the owner included.
- [ ] **The cost of an increase** (decided 28 October 2026, ninth round; gap 23): a line that adds stock (found,
      or a recount that finds more) is received at the latest batch cost of that variation at the shop, else at an
      unknown cost (the batch's `unit_cost` NULL, so its sales are `cost_known = false`, step 6), never at a
      `cost_price` nobody entered for this stock. This is stage 13's rule for stocktake surpluses
      ([stage 13](./13-stocktakes.md), step 5); [stage 11](./11-units-quantities-and-batches.md), step 5, replaces it
      with the cost entered by someone holding `products.set-cost`, else unknown. The line shows the cost it will use,
      or "Cost unknown".
- [ ] **One step to apply:** approving applies the stock in the same transaction; "complete" as a separate step goes.
- [ ] **Adjustments approved but never applied** (16 on UAT): `php artisan stock-adjustments:list-unapplied
      --business=<id>` (read-only) lists them with their lines and the stock now; `--reason=<reason>` lists every
      adjustment with that reason whatever its status (for "returned to supplier", so the owner can raise purchase
      returns for any credit still owed). The owner applies or rejects each from its page, through the one-step
      approval above; nothing is applied automatically, since the stock may have been corrected another way since.
- [ ] **Numbers:** `ADJ-{shop code}-{year}-{n}`, a gapless series per shop and year, kept in `invoice_sequences`
      under the series key `adj:{year}` for the shop (the table keys a series by `shop_id` and a 32-character
      `series`, checked 28 October 2026), assigned when the adjustment is saved; existing adjustments keep their
      numbers. `stock_adjustments` has no `business_id` column (checked 28 October 2026) and shop codes are unique in a
      business, so the global unique index on `adjustment_number` becomes `(shop_id, adjustment_number)`.
- [ ] **Columns:** `stock_adjustment_items.quantity_change` is already a signed integer (checked 28 October 2026), so
      signed changes need only the validation (`min:1` today) to change; `stock_adjustments.type` stays and is filled
      from the lines (increase, decrease or mixed).
- [ ] Tests: a variation of a product with variations is adjusted, and the default variation isn't; the before and
      after figures are those at approval; "increase, lost" is refused; one recount raises one line and lowers
      another; approving applies the stock once and can't be done twice; a found-stock increase is applied by its
      creator; a damaged-stock decrease waits, and its creator (even the owner) can't approve it while a manager can;
      an "other" decrease of exactly KES 5,000 waits, one above waits and one below is applied; a recount of +100 and
      −100 whose value (Σ |quantity| × cost) is above the threshold waits; a found increase is received at the shop's
      latest batch cost, and one for a variation with no batch at an unknown cost, which waits for a second person;
      the threshold is changed by
      `settings.update` and audited; "returned to supplier" is refused on a new adjustment; `--reason` lists old
      ones;
      numbers are gapless per shop and two businesses' don't collide; another business's adjustment is a 404.

### 2. Who can void and delete, and for how long
> **Revised 3 November 2026** (see "Decisions that apply"): void requests, 422 for a closed window, an owner-only
> limit, deliberate grants kept, the shop's timezone on its form, and a temporary refusal for pending sales with
> payments until step 4. Done; see PROGRESS.
- [ ] **API checks:** `Api\SaleController::void` calls `$this->authorize('void', $sale)`; `::destroy` calls
      `$this->authorize('delete', $sale)`, so web and API apply the same policy. Refusals stay
      `{success: false, message}` with 403 (flat API shape).
- [ ] **Only `sales.void` allows voiding:** remove the `sales.edit-all` branch from `SalePolicy::void`.
- [ ] **Deleting stays with the owner** (decided 28 October 2026, ninth round; gap 26): `sales.delete` and
      `sales.delete-all` stay off every template but the owner's, as today; owners can give them to a role. Every
      delete is audited (`Sale` is `Auditable`).
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
  - A business setting `sales.void_window`, stored in `businesses.settings`. It takes one of four values:
    - `session` (the default, also used when the setting is missing; decided 28 October 2026, ninth round): a sale
      can be voided while the cash session it was made in (`sales.register_id`, gap 24) is open, and never past
      23:59:59 on the day it was completed, so a session left open overnight doesn't keep voids open; a sale with no
      session (an online order) uses `end_of_day`;
    - `end_of_day`: a sale can be voided until 23:59:59 on the day it was completed;
    - a whole number of minutes from 1 to 1,440 (24 hours), counted from completion;
    - `none`: no limit.
  - **"The day" is the shop's local day:** the sale's completion time is read in the shop's timezone (`shops.timezone`,
    else `businesses.timezone`, default Africa/Nairobi), never the server's UTC. So a sale at 22:00 in Nairobi can be
    voided until midnight Nairobi time, not until midnight UTC.
  - A sale made just before midnight has only minutes left. That's intended: the day's figures close at midnight.
  - A small `App\Support\Sales\VoidWindow` works out, for a sale, until when it can be voided (`until($sale)`) and
    whether it can be voided at a given moment (`allows($sale, CarbonInterface $at)`). **A sale that isn't completed
    has no window** (decided 28 October 2026, tenth round): `until($sale)` returns null and `allows($sale, $at)` is
    true at any moment; the window starts at its completion. The policy, the sale page,
    `/api/v1` and the push all use it, so they always agree.
  - It's on the business settings page, under "Sales", with the choices "Until the register session closes
    (default)", "Until the end of the day", "A number of minutes" (with the number) and "No time limit". **Anyone with `settings.update` can change it**
    (decided), as with the page's other settings. Each change is audited with the old and new values.
  - `SalePolicy::void` refuses once the window has closed. Messages:
    - "This sale's register session has closed, so it can't be voided. Use a return instead." (session);
    - "This sale can only be voided on the day it was made. Use a return instead." (end of day);
    - "This sale can no longer be voided (limit: :minutes minutes). Use a return instead." (minutes).
  - **The owner is held to the limit too** (decided). Past the window, a return keeps the books and the register
    right. (Hence no `Gate::before` for the owner, step 0.)
  - **Which moment is checked:** an online void (web or `/api/v1`) is checked at the current time; an offline void
    (the push item `sale_void`, step 5c) at the item's `recorded_at`, which must lie inside a lease the till was
    given. The window always runs from the sale's own completion time (a pending sale has no window until it is
    completed), read in the shop's timezone. So a sale and its void both recorded offline on Monday and pushed on Tuesday
    are accepted, while a void recorded on Tuesday for Monday's sale is refused. With `session`, an offline void is
    allowed when the sale's session was still open at `recorded_at` (not closed, or closed later than it).
- [ ] **Tests:**
  - a manager with no grant gets 403 on web and API;
  - a role the owner gave `sales.void` succeeds;
  - `sales.edit-all` alone is refused;
  - by default (`session`), a sale can be voided while its session is open and is refused once the session closes,
    even the same day; a session left open past midnight stops voids at midnight; a sale with no session uses the end
    of the day; a missing setting means `session`; an offline void recorded before its sale's session closed is
    allowed, and one recorded after it is refused;
  - with `end_of_day`, a sale made at 10:00 can be voided at 23:59 the same day, and is refused at 00:00 the next day;
  - a pending sale can be voided at any moment, days after its creation; once it is completed, the `session` window
    applies from its completion; a manager without `sales.void` can't void a pending sale;
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
> **Done 4 November 2026** (`StockReleaseTest`). `release()` returns null when there is nothing to release, so a
> second release changes nothing and writes no movement. Skipping voided sales when settling is step 4.
- [x] New public method: `release(Model $consumer, StockMovementType $type, array $context = []): StockChange`.
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
     `consume()`), and write one `StockMovement` of `$type` (`SALE_VOID` or `SALE_DELETE`, new), at the weighted cost
     released. The
     caller passes the type, so stage 12 adds `TRANSFER_CANCEL` without touching `release()`.
  6. Delete the released allocations. Their history is kept in the movement and the audit log.
  7. **Settle waiting below-zero sales** (ninth round): for each batch it gave units back to, oldest first, call
     `settleProvisional()` (private, which `receive()` calls for its new batch; checked 28 October 2026), so the shop
     variation's outstanding provisional allocations take those units at the batch's cost and their sales are
     re-costed. Voided sales are skipped (step 4). Stage 12's transfer cancel gets this unchanged, and stage 11c's
     batch details on movements record it.
  8. Push stock to the shop's online stores after commit, as `changeStock()` does.
- [x] `StockMovementType::SALE_VOID` and `SALE_DELETE` (additions), both listed in `isAddition()` (the enum lists
      every type in `isAddition()` or `isDeduction()`, checked 28 October 2026). `CostLayer::SOURCE_RELEASE`.
- [x] Unit tests on SQLite and MySQL:
  - a line taken from two batches goes back to both, in the right quantities;
  - a provisional line releases stock with no batch;
  - a mixed line (part batch, part provisional);
  - an adjustment line and a supplier-return line release through the same method (the transfer-line test is in
    stage 12);
  - the invariant holds after each case;
  - releasing twice changes nothing the second time;
  - a sale of 5 takes the only batch; a second sale of 2 goes below zero provisionally; voiding the first sale settles
    the 2 at the batch's cost, re-costs the second sale, and the invariant holds;
  - a released line's `shop_variation.updated_at` moves on, and a till's next pull carries the restored stock.

### 4. Voiding gives stock back
> **Done 4 November 2026** under the decisions of 4 November (see "Decisions that apply"): no session choice on the
> web; the voider's own session or a refund owed. The temporary refusal of paid pending sales is removed.
> `VoidStockAndMoneyTest`.
- [x] Web and API `void` call `release()` for every line, inside the existing transaction, before the credit
      reversal.
- [x] Line and sale cost and profit are left as they were sold. The sale is excluded from reports by its status.
      The figures stay for the audit trail and the voided-sales list.
- [x] `settleProvisional()` skips allocations whose sale is voided. This is needed as a second guard for sales
      voided before this stage, because their allocations still exist.
- [x] **A sale with a return that isn't rejected can't be voided** (decided): 422 with "This sale has returns.
      Process the return instead."
- [x] **Cash on a void:**
  - **The sale's session is open:** its totals are recalculated (`updateSalesTotals()`), on web as well as API (web
    doesn't do this today), so the cash leaves its expected cash.
  - **The sale's session is closed:** it was counted and reconciled, so it is left as it is (no recalculation; its
    stored `expected_balance` and `variance` and its totals stay). The cash handed back comes out of today's drawer:
    a cash payout is recorded in an open session (`cash_payouts`: session, amount, reason, the sale, who; the session
    names its register, so the payout needs no register column), and `calculateExpectedBalance()` subtracts that
    session's payouts.
  - **Which open session** (decided 28 October 2026, ninth round; gap 24): a void from a till (`/api/v1` or the push)
    pays out of that till's open session (`voided_register_id`); a void on the web asks the person to choose among the
    shop's open sessions, each named by its register, preselected when there is one. With none open (for a till, none
    open on that till), a `refund_due` issue is recorded instead (`source = void`, `method = cash`, step 5c). With the
    default `session` window (step 2) a sale's own session is open while it can be voided, so this applies to the other
    windows and to sales with no session.
  - Card and M-Pesa payments are never paid out of a drawer: they are a `refund_due` (step 5c).
- [x] **eTIMS:** nothing fiscal is produced in this stage. Once eTIMS is on, a void of a signed invoice issues a credit
      note; see [stage 22](./22-etims.md), step 3.
- [x] **Payouts on the session page:** a session lists its payouts (amount, reason, the sale if any, who). Someone
      holding `cash-registers.payout` (new: the owner and the manager template, through step 0's rollout) can record
      a payout by hand for petty cash, with a reason required; it lowers the open session's expected cash and is
      audited. Stage 14's cash refunds are payouts too.
- [x] Tests:
  - void restores stock and batches, on web and API;
  - a voided sale no longer moves when stock arrives;
  - void refused while a return exists;
  - a cash sale voided while its session is open lowers that session's expected cash;
  - a cash sale voided after its session closed leaves that session's totals, expected balance and variance as they
    were, and records a payout in the open session step 4 chooses (the voiding till's, or the one chosen on the web),
    whose expected cash drops by it;
  - the same with no open session records a `refund_due` (`source = void`, `method = cash`);
  - in a shop with two open sessions (`R1` and a till's `R2`), a web void of a sale whose session closed asks which
    session pays, and only the chosen one's expected cash drops; a till's void pays out of the till's session; a till
    whose own session is closed records a `refund_due` even while `R1` is open;
  - a manager records a petty-cash payout and the expected cash drops by it; a cashier without
    `cash-registers.payout` can't; a payout needs a reason;
  - the stores push is dispatched;
  - an audit row records the void.

### 5. Deleting a sale
> **Done 4 November 2026** (`DeleteSaleTest`; the part-paid pending void is in `VoidStockAndMoneyTest`). Only draft and
> pending sales can be deleted (the literal reading: a voided or refunded sale is refused, "Only draft and pending
> sales can be deleted."); refusals carry `code` (`sale_completed`, `sale_not_deletable`, `sale_has_payments`). No sales
> view had a Delete action, so there was nothing to hide. Who deleted a sale comes from the audit trail (no column).
- [x] **A completed sale can't be deleted; it can only be voided** (decided). Web and API return 422
      with "Completed sales can't be deleted. Void the sale instead."
- [x] A sale that isn't completed (draft or pending, such as a cash-on-delivery sale) and has no payments can still
      be deleted, by the owner (step 2). Before deleting, its lines are released as in step 3 with `SALE_DELETE`, since
      stock is taken at creation for every status.
- [x] **A pending sale with payments can't be deleted** (decided 28 October 2026, ninth round; gap 25): 422 "This sale
      has payments. Void it instead." While it is pending it has no void window (step 2): someone holding `sales.void`
      (the owner by default) can void it at any time. Voiding it releases its stock, and its payments are handled as
      in step 4: cash is paid out of the open session (the voiding till's own, or on the web the one the person
      chooses), M-Pesa and card are a `refund_due`, and cash with no session open is a `refund_due`. The automatic
      void of online orders (step 5b) is unchanged.
- [x] **Soft deletes** (decided 28 October 2026): `Sale` uses `SoftDeletes` on the existing `sales.deleted_at` (nothing
      sets it today; `VatReportService` already filters on it, checked 28 October 2026). A deleted sale keeps its row,
      its lines and its invoice number, which stays taken and is never issued again; it leaves every list, report and
      the pull; its movements always point at a row.
- [x] **What still sees a deleted sale** (checked 28 October 2026, gap 27):
  - `StockMovement::reference()` and the `sale()` relations of `SaleItem`, `SalePayment` and `SaleReturn` use
    `withTrashed()`, so a movement, line, payment or return always resolves its sale;
  - `StockLedger`'s reads by id (`DB::table('sales')`) already see it, so a release and a re-cost still work;
  - the audit trail reads only the audit database (`auditable_uuid`), so nothing changes there;
  - `VatReportService` already leaves it out; `invoices:report-series` (step 5d) counts its number, which stays taken
    (the unique key includes deleted rows);
  - the raw queries in `SaleController`, `DashboardController`, `Api\ReportController`, `ReportService`,
    `BusinessDirectory` and `AssignProductShopFromSales` gain `whereNull('sales.deleted_at')`, listed in the PR;
  - `sales.show` binds `withTrashed()` for someone holding `sales.view-all`, with a "Deleted" banner (who, when);
    anyone else gets a 404.
- [x] Remove or hide the "Delete" action for completed sales in the sales views. The server check is the guard.
- [x] Tests:
  - deleting a completed sale is refused and changes nothing;
  - deleting a pending sale returns its stock with a `SALE_DELETE` movement, and the sale is soft-deleted: gone from
    every list and report, its row, lines and invoice number kept, and the number never issued again;
  - a deleted sale's movements are never listed by `stock:orphaned-movements`, and `$movement->reference` still
    resolves the sale;
  - a deleted sale leaves the dashboard, the sales list's statistics and the flat API reports; its page opens with a
    "Deleted" banner for a holder of `sales.view-all` and is a 404 for others;
  - a pending sale with a payment can't be deleted (422) and can be voided while it is pending;
  - a part-paid pending sale (cash and M-Pesa) voided three days after its creation releases its stock, pays its cash
    out of an open session and records a `refund_due` for its M-Pesa part; a manager without `sales.void` can't void
    it; once it is completed, the `session` window applies;
  - a manager without `sales.delete` can't delete a pending sale; the owner can;
  - both on web and API.

### 5a. A Void button on the web sale page
- [ ] On `sales.show`, a **"Void sale"** button (`btn-outline-danger`, with an icon and its text, never icon-only).
      It is shown only when `@can('void', $sale)` passes: the permission, the time limit, no return that isn't
      rejected, and not
      already voided.
- [ ] **When the button isn't available**, the page says why in one line instead:
  - "This sale's register session has closed, so it can't be voided. Use a return instead." (session), "This sale can
    only be voided on the day it was made. Use a return instead." (end of day), or "Voiding closed
    :time ago (limit :minutes minutes). Use a return instead." (minutes);
  - "This sale has a return, so it can't be voided." (for a return that isn't rejected); or
  - nothing, for people without the permission.
- [ ] **While the window is open**, a muted line under the button says until when: "Can be voided until the register
      session closes (11:59 pm today at the latest)." (session), "Can be voided until 11:59 pm today." (end of day), or "Can be voided for another :minutes minutes." (minutes). Nothing is shown with no
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
  - **Which session pays the cash**, when the sale's cash must come out of another session (step 4): a required
    choice of the shop's open sessions, each named by its register.
  - **Buttons:** "Cancel" and "Void sale" (`btn-danger`). The form has `@csrf` and `@honeypot`, and posts to
    `sales.void`.
- [ ] After voiding, the page shows a success message ("Sale voided. :count items are back in stock."), and a
      "Voided" badge with who voided it, when and why.
- [ ] **Accessibility:** the modal has a labelled title, focus moves to the reason field, Escape cancels, and the
      first error gets focus.
- [ ] **Tests:**
  - the button shows for a holder within the window;
  - it is hidden past the window, with the explanation shown;
  - it is hidden for a sale with a return that isn't rejected, and for someone without the permission; it shows for
    a sale whose only return was rejected;
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
- [ ] **Pending sales too:** a cash-on-delivery order becomes a `pending` sale that holds stock (checked 28 October
      2026, `ConvertEcommerceOrderToSale`), so a cancelled COD order voids its pending sale the same way, releasing its
      stock. `voidable` and `voidable_until` (step 5c) govern voids by people at the till and on the web; this
      automatic void doesn't read them.
  - `voided_by` is NULL.
  - `void_reason` is "Order cancelled in :store (order :number)."
  - The audit row records it as automatic.
- [ ] **It is not voided automatically** when the order was already dispatched, or when the sale has a return that
      isn't rejected.
      Instead the sale is flagged ("The online order was cancelled after dispatch. Check whether the goods came
      back, and process a return.") and the shop's staff are notified in the app's alerts.
- [ ] **Done once:** a repeated or late webhook for the same cancellation changes nothing (the sale is already
      voided). It runs for the order's business, in the queued job, with the tenant context set.
- [ ] **Tests:**
  - a cancelled `processing` WooCommerce order voids its sale and restores stock; a cancelled cash-on-delivery order
    voids its pending sale and restores its stock;
  - a cancelled `completed` order doesn't void, and flags the sale instead;
  - a Shopify order cancelled unfulfilled voids, and one cancelled fulfilled doesn't;
  - a sale with a return that isn't rejected isn't voided;
  - the webhook twice gives one void;
  - the void doesn't push the stock change back to the store that cancelled it (the store sync's own
    `syncInProgress` guard).

### 5c. Voids from the till: `/api/v1` and offline
- [ ] **Online:** `POST /api/v1/sales/{uuid}/void` (`reason` required), through `SalePolicy::void` (permission,
      `VoidWindow` checked at the current time, no return that isn't rejected, not already voided) and the same void
      action as the web
      (step 4). Refusals carry codes, added to `ApiErrorCode`: `void_window_closed`, `sale_has_returns`,
      `sale_already_voided`, and 403 `forbidden` without `sales.void`. A register-bound token reaches only its shop's
      sales. The sale records the till (`voided_register_id`).
- [ ] **Offline:** a new push item `sale_void` with its own `client_uuid` (`sale` uuid or `sale_client_uuid`,
      `reason`, `voided_by`). The till checks the person's `sales.void` from the staff list; the server checks it
      again. The time limit is judged by `VoidWindow` at the item's `recorded_at`, which must lie inside a lease the
      till was given. **A `sale_void` recorded outside any lease** is refused `outside_trading_lease`, class **hold**
      (decided 28 October 2026): a void is a person's deliberate act and may mean money handed back, so it is kept for
      a manager rather than dropped. Accepting it applies the void only if `VoidWindow` allows it at the server's
      current time and the sale has no return that isn't rejected; otherwise it can only be discarded. Released and audited as an online void, with the till (`voided_register_id`) and the recorded
      time (`void_recorded_at`; `voided_at` stays the server's time).
- [ ] **Order and a sale not yet on the server:** push items are applied in the order sent, so a void after its
      sale in the same push finds it. A `sale_void` whose sale is unknown (later in the same push, or not pushed
      yet) gets a new result status, `pending` ("not yet applied"). Nothing is kept, no receipt is written, and the
      till sends it again with its next push. It is not refused.
- [ ] **Once only, and races:**
  - the push's once-per-`client_uuid` rule applies: a void pushed twice is applied once and releases stock once;
  - a void for a sale already voided elsewhere (the web, another till, an online cancellation) is `applied` as a
    no-op, with `result.already_voided = true`, so the till clears it;
  - a void for a sale that has had a return since (one that isn't rejected) is `refused` (`sale_has_returns`) and goes on the till sync review
    as a new `void_refused` issue (the till, the person, the recorded time and the reason), so a manager sees the
    void that didn't happen.
- [ ] **Pending removals at the till** (decided 3 November 2026): the pull's `stock` carries, per variation, the units
      that pending stock-adjustment decreases would remove (`pending_removal`), and the till shows a non-blocking
      warning "N units pending removal" when it sells into them. Stock is never held.
- [ ] **Telling the tills:** the pull gains `sale_voids`, the shop's sales voided since the cursor (`uuid`,
      `client_uuid`, `voided_at`, `void_recorded_at`, `reason`), so every till of the shop marks them, wherever the
      void was made. The restored stock reaches the tills through the pull's `stock` section (step 3).
- [ ] **When voiding is possible** (decided 28 October 2026, tenth round): `/api/v1` sale and receipt responses carry
      `voidable` (true or false) and `voidable_until`:
  - `voidable` is true when the sale can be voided now: completed and inside its window, or pending with payments
    (no window, step 2); false when it has a return that isn't rejected, is voided, is past its window, or is pending
    with no payments (which is deleted instead);
  - `voidable_until` is the end of the window when there is one, and null otherwise (a pending sale, a window of
    `none`, or a sale that can't be voided);
  - both are about time and the sale's state only, so they are the same for everyone; the app combines `voidable`
    with the signed-in person's `sales.void` from the staff list to show or hide its Void button. Neither exists in
    the code yet (checked 28 October 2026: `SaleReceipt` carries no void field), so no Phase 7 app reads them.
- [ ] **One refund issue type, `refund_due`** (built in step 4 as the `refunds_due` table and the Refunds owed list,
      not a sync issue; 5c adds the till sources and links the review to it), on the till sync review, carrying a `source` (`void` or
      `late_payment`), the `method`, the amount and the payment reference:
  - `source = void`: voiding a sale with M-Pesa or card payments records one per payment, until stage 14 brings
    refunds; so does a cash void with no open session to pay out of (step 4);
  - `source = late_payment`: `TillPayments::apply()` refuses a voided sale, so a confirmed M-Pesa payment for it
    (an STK request still pending at the void, or a code matched later) is left unapplied and recorded as one.
  - **Closing it, until stage 14** (decided 28 October 2026, ninth round; gap 29): a "Mark refunded" action on the
    review records the method, reference, amount, who and when, in the issue's details, and resolves it
    (`resolution = refunded`, `resolved_at`, `resolved_by`); an open issue has no resolution. It needs
    `cash-registers.payout` for cash, and `sync-issues.accept` for any other method. For cash it also records a
    payout in an open session the person chooses (step 4); with none open it is refused ("Open the register to pay a
    cash refund."). Audited; done once (the issue is checked under a lock). [Stage 14](./14-returns-in-reports.md),
    step 6, replaces it: a refund processed for the issue resolves it.
- [ ] **A till that would lose unsynced work** (a reinstall, a lost device, signing out, re-pairing):
  - **The server knows what a till holds:** every push and pull sends `outbox_count` (unsynced items on the till);
    the server keeps it on the register (`outbox_count`, `outbox_reported_at`), shows it in the register list, and
    adds it to the overdue-sync warning ("Till R2 hasn't synced for 26 hours and holds 41 unsynced items").
  - **Re-pairing a till that holds unsynced work** is refused unless confirmed: with the old device's last
    `outbox_count` above 0, pairing answers 422 `till_has_unsynced_items` (the count and when it was reported)
    until sent again with `confirm = 1`. The confirmed re-pair is audited with the count, so the lost items are on
    record.
  - **A void whose sale is held** (refused and kept for a person, see "Every refusal has one class") waits behind it
    as `pending`. If someone holding `sales.void` discards the sale, its queued void and payments are discarded with
    it, each with its own `outbox_item_discarded`.
  - **A void whose sale is gone** (the till lost it) is discarded the same way.
- [ ] **One discard for every stuck outbox item:** a new push item `outbox_item_discarded` (`item_type`: `sale`,
      `sale_void`, `sale_payment`, `customer`, `cash_session`, `till_sign_in`, `mpesa_code`, and any type a later
      stage adds; the discarded item's `client_uuid`; `reason`; `discarded_by`, who must hold `sync-issues.discard`).
      It is
      applied once per its own `client_uuid`, records an `outbox_item_discarded` issue on the till sync review (the
      till, the item type and uuid, the raw item as the till held it, the reason, the person) and an audit row, so
      nothing a till abandons goes unrecorded. A discard from someone without the permission its item type needs is
      refused (`forbidden`, drop). **`sync-issues.discard`** (new, decided 28 October 2026; the owner and the manager template,
      through step 0's rollout) is separate from `sales.void`, which step 2 makes owner-only, so a manager can clear a
      stuck till item without being able to void sales. **Sales and payments are the exception** (decided 28 October
      2026, ninth round): discarding a `sale` or `sale_payment` item removes goods and money the till recorded, a void
      without the void rules, so it needs `sales.void` (the owner by default); a held sale's queued voids and payments
      are discarded with it under the same rule. A manager can still accept a held sale, or re-ring one whose number
      was taken (below). A discard of an item the server has already applied is applied as a no-op
      (`result.already_applied`), so the till clears it.
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
    - `invoice_number_taken` is **discard-only** (decided 28 October 2026). What it detects (checked 28 October
      2026): `SaleHandler` catches the unique key `sales(business_id, invoice_number)`; `InvoiceNumberService::adopt()`
      only checks the number is in the till's register's series. Numbers carry the shop and register codes, and
      re-pairing a different device always issues a new register code that is never reused (`PairRegister::nextCode`),
      so after step 5d a taken number means the same register issued it twice: a till bug or tampering. The number
      can't be kept (the key) and is never renumbered (the fiscal rule). The review shows the whole sale and offers
      two resolutions:
      - **"Discard and record as a new web sale"** (decided 28 October 2026, ninth round), for a sale that happened:
        in one transaction it discards the item and creates the sale from the raw item in the shop's web series
        (`nextWeb()`), with its lines, stock, payments, customer, cashier and recorded time as the till recorded them;
        the new sale's `sync_flags` holds `rerung` (the till's invoice number and client uuid) and the issue names the
        new sale, so each links to the other; audited; needs `sync-issues.accept`, since it applies a sale. **The new
        sale takes the till's `client_uuid`** (decided 28 October 2026, tenth round): it is free, because the
        original was never applied (checked 28 October 2026: `sales.client_uuid` is unique per business). The
        discarded item's receipt is `discarded`, while the sale exists under that uuid. A later push of the original
        sale item is answered from its receipt and creates nothing (checked 28 October 2026: `SyncPush` looks up
        `sync_push_receipts` by `client_uuid` before any handler runs, and the sales key would refuse a second sale
        anyway). Items the till queued behind it (`sale_payment`, `sale_void`) find the new sale by its
        `sale_client_uuid` (checked 28 October 2026: `PaymentHandler` finds a sale that way) and apply normally, a void
        under the void rules. **The
        customer's receipt number won't match the books:** the sale page and the invoice show "Re-rung from till
        receipt :number" so the receipt can be found;
      - **Discard**, for a sale that never happened (`sales.void`, as every sale discard);
    - `invalid_invoice_number` (a number not in the till's series): accept with the till's number when it is unique
      in the business, flagged `accepted_invalid_series`; otherwise discard. **A number in the web series
      (`INV-{shop}-W-…`) is never accepted from a till** (decided 28 October 2026): discard only, because
      `nextWeb()` would later issue it again;
    - `outside_trading_lease`: applied at the till's `recorded_at`, flagged (`accepted_outside_lease`);
    - `validation_failed`: resolved as the table below says, by the rule that failed; the screen offers exactly
      that.
    - `sale_already_paid` is no longer held (below).
  - **Discard** (`outbox_item_discarded`), with a reason, for items that shouldn't exist: `unknown_unit`,
    `unknown_item_type`, `invoice_number_taken`, a web-series number, or a manager's judgement. A discard can come
    from the till (as above) or from the review screen, by someone holding the permission its item type needs
    (`sales.void` for a sale or a payment, `sync-issues.discard` otherwise).
  - The item's outcome is stored by its `client_uuid` in `sync_push_receipts`, which today holds `business_id`,
    `register_id`, `client_uuid`, `type`, `result` and `applied_at` and no outcome (checked 28 October 2026): it gains
    `outcome` (`applied`, `accepted`, `discarded`; existing rows `applied`),
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
  - `voidable` and `voidable_until` are the same for a cashier and the owner; a sale with a return that isn't
    rejected is `voidable: false`; a pending sale with payments is `voidable: true` with `voidable_until` null;
  - a confirmed M-Pesa payment for a voided sale doesn't apply and is a `refund_due` with `source = late_payment`;
  - voiding a sale paid by M-Pesa is a `refund_due` with `source = void`, the method and the reference;
  - another business's sale is a 404;
  - `outbox_count` is stored from a push and a pull, shown in the register list, and named in the overdue warning;
  - re-pairing a till that last reported unsynced items is refused without `confirm`, and audited with the count
    when confirmed; a till that reported 0 re-pairs without asking;
  - an `outbox_item_discarded` for a held sale, for a void and for a payment is each audited and on the review; a
    discard of a void from a person without `sync-issues.discard` is refused with `drop`, and a manager holding it
    succeeds; a discard of a held sale or a payment by a manager without `sales.void` is refused with `drop`, and the
    owner's succeeds, taking the sale's queued items with it; one for an item already applied is a no-op;
  - every refusal carries the `disposition` the "Contract" table gives its code; a payment pushed before its sale is
    `pending` with `sale_not_synced` and `retry`, and is applied when pushed again after the sale;
  - an accepted held sale keeps the till's invoice number; the till's next push of it answers `applied`, so the till
    clears it; the next pull lists it in `resolved_items`; it can't be accepted twice;
  - an `invoice_number_taken` sale can't be accepted; discarding it records the whole sale in the issue and the
    audit; it is never renumbered;
  - "Discard and record as a new web sale" creates one sale in the web series with the till's lines, stock, payments
    and cashier, flagged `rerung` with the till's number, linked from the issue, audited, once; a manager without
    `sync-issues.accept` can't;
  - a payment queued behind a re-rung sale applies to the new sale; a void queued behind it voids the new sale under
    the void rules; the original sale item pushed again answers `discarded` and creates nothing;
  - a `refund_due` marked refunded is resolved once and audited; a cash one records a payout in the chosen session
    and is refused with no open session; a cashier without `cash-registers.payout` can't mark a cash one, and someone
    without `sync-issues.accept` can't mark an M-Pesa one;
  - a till's sale numbered in the web series (`INV-{shop}-W-…`) is refused and can't be accepted, only discarded;
  - a `sale_void` recorded outside any lease is held (`outside_trading_lease`); accepted, it voids the sale when the
    window allows it now and there is no return that isn't rejected; otherwise only discard is offered;
  - **one rule from each group:** a sale whose customer doesn't exist is corrected by picking a customer, then
    accepted; a sale with a 12,000 delivery fee is accepted as recorded and flagged `large_amount`; a sale with a
    discount above its subtotal can only be discarded;
  - an accepted `outside_trading_lease` sale is applied at its recorded time and flagged;
  - a discard from the review screen is audited, and can't be done twice; a discard by someone without the
    permission its item type needs, or an accept by someone without `sync-issues.accept`, is refused;
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
      series (`invalid_invoice_number`), and such a sale is never accepted later (discard only, step 5c). Whether the app treats the number as an opaque string is an app check
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
- [ ] `php artisan stock:orphaned-movements --business=<id>` (read-only) lists `SALE` movements whose sale row no
      longer exists, so the business can correct stock by hand for sales hard-deleted before this stage. Sales
      deleted after it are soft-deleted (step 5), so their rows remain and they are never listed. Their
      allocations are gone, so nothing can be released automatically.
- [ ] **Old voids keep their money as it was:** `sales:restock-voided` gives back stock only. It records no payouts
      and no `refund_due` issues: those sales' sessions were counted at the time. The report says so.
- [ ] `sales:report-cross-shop-lines` is **not** built (removed 27 October 2026; step 1a).
- [ ] Tests on fixtures: an old voided sale is restored once (a second `--apply` changes nothing), with no payout or
      issue recorded. The orphaned list finds a deleted sale's movements.

### 8. Housekeeping
- [ ] **The deploy order is written down** (a planned item, made when the stage ships): no deploy script exists
      (checked 28 October 2026), so CLAUDE.md's "Commands" section gains "Deploy: `php artisan migrate`, then
      `php artisan permissions:sync`, never the reverse", with the reason (the role backfill runs inside the migrations
      and must record removals before any sync).
- [ ] Note in [pos-domain-rules](../../../.claude/skills/pos-domain-rules/SKILL.md) and CLAUDE.md:
  - the selling-shop rule;
  - voids release stock;
  - only `sales.void` voids, within the business's time limit (by default, while the sale's register session is
    open);
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
- **Void at the till:** a Void button shown when `voidable` is true and the signed-in person holds
  `sales.void` (from the staff list); online it calls `POST /api/v1/sales/{uuid}/void`, offline it queues a
  `sale_void` push item with its own `client_uuid` and `recorded_at`.
- **Push results:** follow `disposition` (below): `retry` sends the item again with the next push; `drop` removes it
  and shows why; `hold` keeps it, marked, until a manager accepts it on the server or discards it. `applied` with `already_voided` (or
  `already_applied` for a discard) clears the item.
- **Pull:** mark the sales listed in `sale_voids` as voided; restored stock arrives in `stock` as usual.
- **Refusal codes** on the online void: `void_window_closed`, `sale_has_returns`, `sale_already_voided`, 403.
- **Delete:** a completed sale can't be deleted (422); the app hides Delete for completed sales (step 5).
- **Unsynced work:** send `outbox_count` with every push and pull; never delete a business's local store while it
  holds unsynced items; warn, and require a confirmation, before signing out or switching business with unsynced
  items.
- **Held items:** a held sale keeps its queued void and payments behind it. A manager can discard a held or stuck
  item on the till (holding `sync-issues.discard`, with a reason; a held sale or a payment needs `sales.void`, the
  owner by default): the app pushes `outbox_item_discarded` for it and for each
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
| `forbidden` | `sale_void` (without `sales.void`), `till_sign_in` (`unlocked_by` may not unlock), `outbox_item_discarded` (without `sync-issues.discard`, or without `sales.void` for a `sale` or `sale_payment` item) | `refused` | drop | The action wasn't allowed; nothing to keep |
| `outside_trading_lease` | `sale`, `sale_void` | `refused` | hold | Phase 7 for sales; a void outside a lease is held too (step 5c); accepted only within the void window at the server's time |
| `invalid_invoice_number` | `sale` | `refused` | hold | Phase 7; accepted with the till's number when unique in the business, except a web-series number, which is discard only |
| `invoice_number_taken` | `sale` | `refused` | hold | Phase 7; recorded as a repeat. Discard only (decided 28 October 2026): the number can't be kept and is never renumbered; a sale that happened can be re-rung on the server as a new web sale (it keeps the till's `client_uuid`, so items queued behind it apply) |
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
| `sale_void`, `outbox_item_discarded`, `sale_voids`, `resolved_items`, `voidable`, `voidable_until` | The stage 10 app release |
| Units, `base_quantity` (stage 11); price and discount approvals (stage 11b) | The stage 11 and 11b app release |

## Schema

| Change | Data carried across |
|---|---|
| `role_revoked_permissions` (new) | Filled inside its own migration by the backfill (absences outside Phase 5 and 7 kept as revocations; this stage's new permissions skipped), then by the revoking migrations; `roles:backfill-rollout` re-runs and reports |
| `stock_adjustment_items.variation_id` (foreign key) | Backfilled with the product's default variation (what the code moved); counts before and after |
| `stock_adjustments.adjustment_number`: unique index becomes `(shop_id, adjustment_number)`; new numbers from the series key `adj:{year}` per shop in `invoice_sequences` | Existing numbers kept |
| `stock_adjustment_items.quantity_change`: no column change (already a signed integer); `stock_adjustments.type` filled from the lines | None |
| `sale_items.cost_known` boolean, default true | Backfilled false where an allocation has no cost |
| `sales.void` revoked from the manager template and its copies (data migration) | Counts before and after; custom roles untouched; revocations recorded |
| `products.set-cost` and `products.view-cost` revoked from the inventory-clerk template and its copies (data migration) | Counts before and after; custom roles untouched; revocations recorded |
| `businesses.settings['sales']['void_window']` (no column): `session`, `end_of_day`, minutes (1–1,440) or `none` | Missing means the default, `session` (changed 28 October 2026 from `end_of_day`; no business has the setting yet) |
| `sales.voided_register_id` (nullable, foreign key to `registers`), `sales.void_recorded_at` (nullable: when the till recorded the void; `voided_at` stays the server's time) | None; past voids have neither |
| `cash_payouts` (new): session (which names its register), shop, business, amount, reason, sale (nullable), `sync_issue_id` (nullable, for a `refund_due` marked refunded), `paid_by`, timestamps | None |
| `sync_issues.resolution` gains `refunded` (existing column); a `refund_due`'s `details` gain the refund's method, reference and amount | None |
| `sales.sync_flags` gains `rerung` (the till's invoice number and client uuid) on a sale re-rung from an `invoice_number_taken` item | None |
| `registers.outbox_count` (unsigned int, nullable), `registers.outbox_reported_at` (nullable) | None; filled by the next push or pull |
| Permission `cash-registers.payout` | Granted to the owner and the manager template through step 0's rollout |
| `StockMovementType::SALE_VOID`, `SALE_DELETE` (both in `isAddition()`), `CostLayer::SOURCE_RELEASE` | None (constants) |
| `Sale` uses `SoftDeletes` on the existing `sales.deleted_at` | None set today; deleted sales from now on keep their rows and numbers |
| Permission `sync-issues.discard` | Granted to the owner and the manager template through step 0's rollout |
| `sync_push_receipts.outcome` (`applied`, `accepted`, `discarded`) | Existing rows `applied` |
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
`voidable` and `voidable_until`. All are additions: an older app ignores what it doesn't know (see "Contract").

`release()` writes existing tables, through `StockLedger::changeStock()`. Quantities stay whole units in this stage;
stage 11 makes them decimal.

## Acceptance criteria

**Permissions (steps 0 and 1f)**
- [ ] A business created before a permission was granted to its template gets it on the next `permissions:sync`.
- [ ] A permission a business or the platform console took off a role stays off after `permissions:sync`.
- [ ] Existing businesses have the Phase 5 and 7 permissions after the backfill; other absences are kept.
- [ ] The backfill runs inside the migration that creates `role_revoked_permissions`, before `permissions:sync`; a
      permission a business removed before this stage stays removed after a deploy in that order; this stage's new
      permissions are never recorded as revoked.
- [ ] `Gate::before` lets only platform operators through.
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
- [ ] A web or flat API sale below zero records a `negative_stock` issue the owner and manager see on the till sync
      review.

**Stock adjustments (step 1g)**
- [ ] An adjustment moves the variation it names; its figures are those at approval; the reason decides the
      direction; approving applies it; numbers are a series per shop.
- [ ] The 16 approved-but-unapplied UAT adjustments are listed for the owner, not applied automatically.
- [ ] Loss, theft, damage, expiry and adjustments at or above the threshold need a second person; the creator never
      approves their own; "returned to supplier" is no longer an adjustment.
- [ ] Adjustment numbers are a gapless series per shop and year, unique per shop.
- [ ] An adjustment's value is Σ |quantity| × unit cost, so a +100/−100 recount above the threshold waits; a line of
      unknown cost waits for a second person; a found increase is costed at the shop's latest batch cost, else unknown.

**Products from the app (step 1f)**
- [ ] Products are created from the app only online by holders of `products.create`; fields the API doesn't take are
      refused, not dropped.

**Voids (step 4)**
- [ ] Voiding a sale of 5 units taken from two batches (3 at 60, 2 at 70) puts 3 back in the first batch and 2 in
      the second. Stock rises by 5, and the next sale is costed at 60 again.
- [ ] Voiding a sale sold below zero raises stock and removes its provisional allocation. Stock arriving later
      doesn't re-cost the voided sale.
- [ ] Voiding twice is refused, and stock moves only once.
- [ ] A sale with a return that isn't rejected can't be voided; one whose only return was rejected can.
- [ ] The invariant holds after every case, on SQLite and MySQL.

**Release (step 3)**
- [ ] `release()` returns a consumer's units to the batches they came from, once, and writes `SALE_VOID` or
      `SALE_DELETE`; it then settles waiting below-zero sales against those batches, and the invariant holds.

**Payouts (step 4)**
- [ ] A manager holding `cash-registers.payout` records a petty-cash payout with a reason, and it lowers the session's
      expected cash; a cashier without it can't.
- [ ] A void's cash comes out of the voiding till's open session, or on the web the open session the person chooses;
      with none open it is a `refund_due`.

**Deletes (step 5)**
- [ ] A completed sale can't be deleted on web or API.
- [ ] Deleting a pending sale returns its stock first (`SALE_DELETE`) and soft-deletes it; its invoice number stays
      taken; `stock:orphaned-movements` never lists it.
- [ ] A pending sale with payments can't be deleted, and can be voided at any time while pending; only the owner
      deletes by default.
- [ ] A deleted sale still resolves from its movements, lines, payments and returns, and leaves every list, report and
      dashboard.

**Web Void button (step 5a)**
- [ ] The sale page shows a clearly labelled "Void sale" button within the window. Its confirmation explains what
      voiding does and when to use a return instead, and needs a reason.
- [ ] Past the window, or with a return that isn't rejected, the page explains why the button isn't there.

**Online cancellations (step 5b)**
- [ ] An online order cancelled before dispatch voids its sale once and restores stock, a pending cash-on-delivery
      sale included. One cancelled after dispatch flags the sale for a return instead.

**Voids from the till (step 5c)**
- [ ] A till voids a sale through `/api/v1`, and offline through the push, under the same permission, time limit
      and rules as the web; an offline void is judged by its `recorded_at`, and refused if the person may not void.
- [ ] A void pushed before its sale is `pending` and applied once the sale arrives; a void pushed twice releases
      stock once; a void of a sale voided elsewhere is a no-op; a void of a sale returned since is refused and on
      the review.
- [ ] Every till of the shop learns of a void, wherever it was made, and gets the restored stock, on its next pull.
- [ ] `voidable` and `voidable_until` are the same for everyone; `voidable` is false when the sale can't be voided,
      and a pending sale with payments is voidable with no `voidable_until`.
- [ ] A confirmed M-Pesa payment never brings a voided sale back; it is a `refund_due` (`source = late_payment`).
- [ ] Voiding a sale paid by M-Pesa or card is a `refund_due` (`source = void`) with the method and reference.
- [ ] A void after the sale's cash session closed leaves that session as counted, and pays the cash out of the
      voiding till's open session, or on the web the open session the person chooses (or records a `refund_due` when
      none is open).
- [ ] The server knows how many unsynced items each till holds; re-pairing a till that holds some needs a
      confirmation and is audited; any held or stuck outbox item can be discarded on the till, and that is reviewed
      and audited.
- [ ] Every push refusal carries a `disposition`, and each code has exactly one class.
- [ ] A held item is accepted with the till's own invoice number, or discarded, on the review, once, audited, and the
      till clears it; a duplicate payment becomes a `refund_due` and is dropped.
- [ ] `invoice_number_taken` and a web-series number from a till can only be discarded.
- [ ] A void recorded outside any lease is held, accepted only within the void window at the server's time.
- [ ] Discarding a `sale` or `sale_payment` item needs `sales.void`; other discards need `sync-issues.discard` (owner
      and manager).
- [ ] An `invoice_number_taken` sale that happened is re-rung as a new web sale, linked to the till's number, audited.
- [ ] A re-rung sale keeps the till's `client_uuid`: items queued behind it apply to it, and a re-push of the original
      answers `discarded` and creates nothing.
- [ ] A `refund_due` is marked refunded once, audited, with a payout for cash, until stage 14 replaces it.
- [ ] Each validation rule on a pushed sale is resolved as its group in step 5c says.
- [ ] Every request carries `X-App-Version`; an older app gets Phase 7's codes and shapes (the replay test).

**Permissions and time limit (step 2)**
- [ ] Void and delete through the API need `sales.void` / `sales.delete`, as on the web.
- [ ] By default only the owner can void. A role the owner gives `sales.void` can void. `sales.edit-all` alone
      can't. `permissions:sync` doesn't give it back to managers.
- [ ] By default a sale can be voided while its register session is open, never past the end of the day it was made
      in the shop's timezone (the end of the day for a sale with no session), for the owner too. A business's choice of
      the end of the day, its own number of minutes (up to 24 hours), or "No time limit", applies to that business
      only. Anyone with `settings.update` can change it. A pending sale has no window until it is completed.

**Costs (step 6)**
- [ ] A line costed from a batch with no cost is marked `cost_known = false` and flagged on the sale page and the
      profit report.
- [ ] Settling below-zero sales against a batch with no cost keeps the estimate.

**Repair (step 7)**
- [ ] `sales:restock-voided` restores old voided sales once, with a dry run first.
- [ ] `stock:orphaned-movements` lists movements of sales hard-deleted before this stage.

**Invoice series, contract and checks (step 5d and the contract)**
- [ ] The flat API contract test passes. A void, delete or product refusal is a new 403/422; how the app shows it is
      an app check (the checklist).
- [ ] Tills and the web never share an invoice series (step 5d): two tills' same sequence numbers don't collide; a
      web sale and a till sale can't collide; existing numbers are untouched.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass. New routes or commands appear in
      `ResourceMatrixTest` where they apply, `POST /api/v1/sales/{uuid}/void` included.
- [ ] `ApiV1VoidContractTest` pins the online void's success and refusals (`void_window_closed`, `sale_has_returns`,
      `sale_already_voided`, 403); the v1 contract test passes with its fixtures unchanged.
- [ ] The app's recorded requests (pushes and pulls without step 5c, and the flat API calls in the contract
      fixtures) still succeed against the new server.

**Housekeeping (step 8)**
- [ ] The domain rules and CLAUDE.md say what this stage changed, and step 8's planned document items (the app's
      products document, the module 21 note, the deploy order) are done when the stage ships.

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
  discarded, or re-rung as new web sales whose numbers differ from the customer's receipt. Mitigation: after step 5d
  no server path draws on a till's series, so a repeat means a bug or tampering, which the review shows; a re-rung
  sale shows the till's number on its page and invoice.
- **Server-numbered sales change number form** (`W` instead of `R1`). Mitigation: the form is a string; old numbers
  stay; whether the app relies on the form is an app check.
- **The backfill inside a migration** writes data in a schema change. Mitigation: it only inserts revocation rows
  (and grants the Phase 5 and 7 misses), prints its counts, is rehearsed on the restored snapshot first, and the
  command re-runs it idempotently; without it, the first `permissions:sync` would give copies every permission they
  had deliberately lost.
- **Soft-deleted sales still hold their invoice numbers**, so a number freed by a delete is never reused. Intended:
  numbers are fiscal and never re-issued.
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
- **Cash paid out of a different session.** A void after its session closed takes the cash from an open drawer, so
  that session's expected cash includes a payout for a sale it didn't make. With the default `session` window this
  can't happen for a sale made in a session; it remains for the other windows and for sales with no session. The
  payout names the sale, the closed session's report stays as it was counted, and on the web the person chooses the
  drawer the cash really leaves.
- **Sales made just before midnight have little time to be voided.** Mitigation: the next day they go through a
  return, and a business can choose a number of minutes instead (up to 24 hours) or no limit.
- **The `session` default ends voids when a cashier closes the register**, even minutes after a sale. Intended: a
  counted drawer isn't reopened; later corrections are returns. A business can choose another window. A session left
  open overnight stops voids at midnight.
- **An owner can void a sale that has been pending for a long time** (a part-paid cash-on-delivery or layaway sale),
  since a pending sale has no window. Mitigation: it is audited, needs `sales.void` (the owner by default), and its
  payments become a payout or a `refund_due`, so the money is accounted for.
- **Only the owner can discard a held sale or payment.** A stuck sale waits for the owner. Mitigation: a manager can
  accept it, or re-ring a taken-number sale; the owner can give `sales.void` to a trusted role.
- **A void now re-costs other sales** (those that went below zero and settle against the returned units), so their
  profit changes when a different sale is voided. Intended: it is what `receive()` does when stock arrives.
- **A `refund_due` marked refunded before stage 14** must not be refunded again. Mitigation: it is resolved, and stage
  14 offers only open issues (its planned note).
- **A raw query missed by the soft-delete change** would count deleted sales. Mitigation: the list in step 5, the PR
  lists each query, and a test checks the dashboard and reports.
- **A found increase costed at the latest batch cost** may differ from what the stock really cost. Mitigation: it is a
  real cost of that variation at that shop, never a `cost_price` guess; with no batch the cost is unknown and the line
  needs a second person; stage 11 lets someone holding `products.set-cost` enter the cost.
- **Recording `negative_stock` for web sales** adds issues to the review in shops that sell below zero often.
  Mitigation: one issue per sale, resolved as "checked"; the blocking setting is a follow-up.
- **Timezones:** counting the day in UTC would close Nairobi sales at 03:00 local time. Mitigation: the window is
  always worked out in the shop's timezone, and tested across midnight.
- **The app may offer "Delete" on completed sales.** The server refuses with 422; the app's handling is an app check.

## Rollout (UAT)

1. Take a snapshot of UAT (`db:snapshot`).
2. Restore it into `rehearsal_testing` and run the migrations there. **The backfill runs inside the migration that
   creates `role_revoked_permissions`**, before anything syncs roles. Check its counts (the Phase 5 and 7 permissions
   granted, other absences recorded, none of this stage's new permissions recorded), the `cost_known` backfill counts
   and the revocation counts (manager copies losing `sales.void`, clerk copies losing both cost permissions).
3. `roles:backfill-rollout --business=<id>` on the rehearsal (dry run): the report, and confirmation that a re-run
   changes nothing.
4. **Then** `permissions:sync` on the rehearsal. Check a manager copy has `cash-registers.payout`,
   `sync-issues.accept` and `sync-issues.discard` and not `sales.void`; a clerk copy has no cost permission; and a
   permission a business had removed before this stage is still removed.
5. `sales:restock-voided --business=<id>` on the rehearsal (dry run, then `--apply`). Compare stock per variation
   before and after. (None to restock on 27 October 2026.)
6. `stock-adjustments:list-unapplied --business=<id>` on the rehearsal (read-only): 16 expected; with
   `--reason=returned_to_supplier`: none expected. `invoices:report-series --business=<id>` (read-only): 1,503 numbers
   in `R1` series and one other, unchanged after the migrations.
7. The user runs, on UAT, the migrations (the backfill runs inside them), then `permissions:sync`, then the repair,
   then
   `stock-adjustments:list-unapplied` for each business. **The owner applies or rejects each listed adjustment on its
   page**, through the one-step approval (a second person where step 1g requires one). Nothing is applied by a
   command.
8. **Background work (step 1g follow-up and step 2):** the scheduler must run `approvals:remind` hourly (it is in
   `routes/console.php`; `schedule:run` must run every minute), and a queue worker must be running for approval
   notifications (queued on `default`; the worker's `--queue=` list must include it). Without them nobody is
   reminded, void requests never expire, and no approval email or in-app notification is delivered.
9. **Server before app:** deploy the server first. Every sync change is an addition, so the app in shops ignores
   `sale_voids`, `voidable` and `voidable_until`, and never sends `sale_void`.
10. Check the server, on the rehearsal, against the app's recorded requests: the Phase 7 sync tests run unchanged;
   the recorded pushes and pulls, and the flat API contract fixtures, succeed; the replay test (no `X-App-Version`)
   gets Phase 7's codes. How the app itself behaves is checked in its own release
   ([app-release-checklist.md](./app-release-checklist.md#stage-10)), whose date isn't set.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push
checks (Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

One point is open, for stage 14 (below); everything else is decided. The decisions of 26, 27 and 28 October 2026
stand (see "Decisions that apply"), including the
fourth round's answers on products from the app, adjustment approval and returns to the supplier, and the eighth
round's: `invoice_number_taken` is discard-only, discards need `sync-issues.discard` (except a sale or payment, which
needs `sales.void`, ninth round), pending-sale deletes are soft
deletes, and access and tenancy are stage 10a. The ninth round's (28 October 2026): 10a depends on no stage; a found
increase is costed at the latest batch cost, else unknown; a pending sale with payments is voided, not deleted;
`sales.delete` stays with the owner; discarding a sale or payment needs `sales.void`; the void window's default is
`session`; stocktakes compare "at or above" the threshold too; blocking sales without stock is a follow-up. The tenth
round's (28 October 2026): a pending sale has no void window until it is completed, and a holder of `sales.void` can
void it at any time; `/api/v1` carries `voidable` beside `voidable_until`; a re-rung sale keeps the till's
`client_uuid`; stage 11's discard of a sale with an unknown unit needs `sales.void`.

Notes added to other stages in the ninth round (planned, one line each): [stage 10a](./10a-access-and-tenancy.md)
(no dependency), [stage 11](./11-units-quantities-and-batches.md) (the threshold by base quantity),
[stage 13](./13-stocktakes.md) ("at or above"), [stage 14](./14-returns-in-reports.md) (a `refund_due` already marked
refunded isn't offered), [TRACEABILITY-stock.md](./TRACEABILITY-stock.md) (row 16 reopened as a follow-up) and the
[app checklist](./app-release-checklist.md#stage-10) (discarding a sale or payment).

**Open, for stage 14's review (decided 28 October 2026 to leave stage 14 as it is):** stage 14's cash refunds are a
payout "in the shop's open register session" ([stage 14](./14-returns-in-reports.md), step 6), but a shop can have two
open sessions today (gap 24). Stage 10 chooses as in step 4; stage 14 decides its own rule when it is reviewed.

## Follow-ups (not this stage)

- **The server checks prices** in [stage 11b](./11b-prices-and-discounts.md): it needs
  `PriceResolver`, which this stage doesn't have. Until then the server records the price a till or the web POS
  sends.
- ~~Use the `sales.deleted_at` column (soft deletes) or drop it.~~ Decided 28 October 2026: soft deletes (step 5).
- **Block sales without stock** (decided 28 October 2026, ninth round, as a follow-up): a per-business setting, off by
  default, for pharmacies and agro-vets, refusing an online sale (web, flat API, `/api/v1` online) that would take a
  variation below zero. An offline till can't know other tills' sales, so the push still records them, flagged. It
  reopens [TRACEABILITY-stock.md](./TRACEABILITY-stock.md) row 16 as an optional setting; stage 11's
  `canBeSoldAt()` is where it would hook. Until then, step 1a shows every below-zero sale on the review.
