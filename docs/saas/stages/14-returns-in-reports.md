# Stage: Returns in Sales, Cost and Profit Reports

> **Source:** returns review of 26 October 2026 (quality returns not reflected in sales or costing).
> **Branch:** `fix/returns-in-reports` · **Depends on:** Phase 1b (FIFO cost batches), the VAT engine
> ([docs/vat.md](../../vat.md)), [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md)
> (`cost_known`, `cash_payouts`, `refund_due`), [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md)
> (return lines in units, decimal quantities), [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md)
> (purchase returns that don't take stock, step 3a) · **Related:** [13-stocktakes.md](./13-stocktakes.md)

**Revisions (27 October 2026):** renumbered from 11 to 13 when the stock stage was split into stages 10, 11 and 12,
then to 14 when stocktakes became stage 13 (fourth round).

**Fourth round (27 October 2026):** goods a customer returns that go back to the supplier raise a draft purchase
return (new step 3a), because "returned to supplier" is a purchase return (decided 27 October 2026, stage 10); this
was a follow-up, now treated as needed. Depends on stage 12 for it. Traceability:
[TRACEABILITY-stock.md](./TRACEABILITY-stock.md).

Return lines carry a unit, a quantity in it and a base quantity from stage 11, so quantities are capped and costed in
base units (steps 1 and 2).

---

## Goal

When goods come back, the reports show it. A completed return takes its refunded value off sales, in the period
it completes. What happens to its cost depends on whether the goods can be sold again:
- **Restocked goods:** their cost comes off cost of goods sold, because the goods are back in stock.
- **Goods returned for poor quality** (defective, damaged, expired, not as described): their cost becomes a
  visible **write-off** instead of disappearing.

Every report computes these figures the same way, and the original sale and its invoice are never edited.

## Before you start

No open question blocks this stage; the points were decided on 26 October 2026 (below).

## Decisions that apply

- **Returns in reports (decided 26 October 2026):**
  - **A return counts on the date its refund is created** (when it becomes `completed`), as the VAT report does.
  - **"Other" and "not as described" are written off**, like defective, damaged, quality issue and expired.
  - **A cash refund needs an open register** in the shop.
  - **Goods returned to the supplier count as written off until the supplier credits them.**
  - **The app report gains new fields; `total_profit` keeps its meaning** (gross, as sold).

- **Profit is net of VAT** (`total_profit = total_amount − tax_amount − total_cost`, [docs/vat.md](../../vat.md)).
- **Invoices are a gapless series** (`InvoiceNumberService`): a sale and its figures stay as issued. Corrections
  are separate records.
- **Restockable returns go back into stock at the cost they were sold at** (stage 1b, agreed design).
- **The flat API is a live contract** for the Flutter app: responses gain fields, never change existing ones.
- **Live-data rule:** existing returns are filled in by a command with a dry run and `--apply`.

## Relevant current state

**How a return moves through the system** (`ReturnService`): `pending` → `approved` → `received` → `inspected` →
`completed`. It becomes `completed` when a refund is created; processing the refund only marks the `Refund`
completed.

**The return reason decides `is_restockable`:**
- **Restockable:** customer changed mind, wrong item, duplicate order, no longer needed. These go back into stock
  on receipt (`StockLedger::receive` at the line's `unit_cost`).
- **Not restockable:** defective, damaged, quality issue, expired, not as described, other. They never go back
  into stock. No movement and no loss is recorded.

**Problems:**
1. **No report subtracts returns.** These all add up only `completed` sales, so a refunded sale still counts in
   full:
   - the dashboard (`DashboardController`);
   - reports and exports (`ReportService`);
   - the daily and profit reports in `SaleController`;
   - the API's daily sales and top products (`Api\ReportController`);
   - the register totals (`CashRegister::updateSalesTotals`).
   The **VAT report** is the only exception: `VatReportService::creditNoteTax`.
2. **The return value ignores discounts.** A line is valued at `unit_price × quantity`, ignoring the line's
   `discount_amount` and any sale-level discount, so a refund can exceed what the customer paid.
3. **Returns can exceed what was sold.** `StoreReturnRequest` checks only `quantity ≥ 1`. The same line can be
   returned again and again, or for more units than were sold.
4. **The VAT credit ignores the restocking fee.** `creditNoteTax` credits VAT on the full returned value, even
   when a restocking fee was kept.
5. **Written-off goods leave no trace.** There is no stock movement and no loss figure.
6. **Refunds touch neither the register nor credit:**
   - a cash refund isn't taken off the register's expected cash (`calculateExpectedBalance()` is opening balance +
     cash sales);
   - a refund on a credit sale, or as store credit, doesn't change the customer's credit account.
7. **`return_items.is_restocked` and `restocked_at` are never set.**
8. **Returns are web only.** There are no API routes, so the Flutter app reads only the report figures.

**Example** (the target): sold 10 at 100, costing 60 each. 2 are returned defective and refunded 200.

| | Today | Defective (written off) | If restockable |
|---|---|---|---|
| Sales (before VAT) | 1,000 | 800 | 800 |
| Cost of goods sold | 600 | 480 | 480 |
| Returns written off | — | 120 | — |
| Profit | 400 | **200** | 320 |

---

## Work

### 1. Value returns at what the customer paid, and cap the quantity
- [ ] `ReturnService` values a returned line at its **paid** price per unit:
      `(line_total − line share of sale-level discount) / quantity sold`, times the units returned. This replaces
      `unit_price × quantity`.
- [ ] `StoreReturnRequest` / `UpdateReturnRequest` refuse a quantity above what is still returnable: sold, minus
      units on other returns that aren't rejected. The error goes under the line: "Only :n left to return on this
      line."
- [ ] **Units (stage 11):** a returned line is entered in the unit it was sold in or the base unit, and the cap
      compares base quantities (`base_quantity`), so 5 bottles can come back from a crate sold. The paid price per
      unit above is per base unit for this purpose: the line's paid value ÷ its `base_quantity`, times the base units
      returned.
- [ ] Tests:
  - a discounted line refunds the discounted price;
  - a sale-level discount is shared across lines;
  - returning more than sold is refused;
  - a second return of the same line is limited to what remains;
  - a rejected return frees its units.

### 2. Record each return line's figures when the return completes
- [ ] Migration on `return_items`:
  - `refund_net` decimal(15,2): refunded value before VAT;
  - `refund_tax` decimal(15,2);
  - `cost_amount` decimal(15,2), nullable (NULL when the line's cost isn't known; see stage 10 `cost_known`);
  - `disposition` string: `restocked`, `written_off` or `to_supplier`.
- [ ] Migration on `returns`: `completed_at` timestamp, nullable. This is the date a return counts in reports.
- [ ] Enum `App\Enums\ReturnDisposition` with `label()` and `badgeClass()`.
- [ ] When the refund is created (`createRefund`, the moment the return becomes `completed`):
  - **Restocking fee:** the fee is shared across lines in proportion to their value. A line's refunded gross is
    its value minus its fee share.
  - **`refund_net` and `refund_tax`:** split from the refunded gross at the original line's `tax_rate` and its
    sale's `tax_inclusive`, the same formula `VatReportService` uses (moved into `TaxService`, so it lives in one
    place).
  - **`cost_amount`:** the sale line's cost per base unit (`total_cost ÷ base_quantity`) × the base units returned,
    rounded once to cents.
  - **`disposition`:** `restocked` when `is_restockable`; `to_supplier` when `return_to_supplier`; `written_off`
    otherwise.
  - **`completed_at`:** set to now.
- [ ] `receiveReturn()` sets `is_restocked` and `restocked_at` on the lines it restocks.
- [ ] Tests:
  - figures on an inclusive and an exclusive sale;
  - a zero-rated line;
  - the fee shared across two lines;
  - disposition for each reason;
  - `completed_at` set once.

### 3. Record write-offs
- [ ] When a `written_off` line completes, record a `StockMovementType::RETURN_WRITE_OFF` movement for the units,
      with quantity before = quantity after (stock doesn't change, because the goods left at the sale), at the line's
      cost, referencing the return. The loss is then visible in the product's stock history.
- [ ] The movement goes through `StockLedger` (new `recordWriteOff()`, which writes only the movement), so
      the rule "only the ledger writes stock movements" holds.
- [ ] Tests: a defective return writes one movement at cost and leaves stock unchanged; a restocked return writes
      none of this kind.

### 3a. Goods for the supplier become a purchase return
- [ ] When a `to_supplier` line completes, a draft purchase return to the product's supplier (chosen on the return
      when the product has none) is raised with the line's base quantity and cost, marked `from_customer_return`: like
      stage 12's `rejected_at_receipt`, **shipping it doesn't take stock**, because the goods never went back into
      stock. It records the credit owed.
- [ ] When the supplier's credit is recorded on that purchase return, the write-off is reversed for the credited
      amount (a `RETURN_WRITE_OFF` reversal movement at cost, through `StockLedger::recordWriteOff()`), so the loss
      shown is only what the supplier didn't credit; `SalesFigures` reads it in the period the credit is recorded.
- [ ] Tests: a `to_supplier` line raises one draft purchase return that leaves stock unchanged when shipped; a full
      credit reverses the write-off, a partial one part of it; a second completion raises nothing more.

### 4. One place for net figures: `SalesFigures`
- [ ] New `App\Services\Reports\SalesFigures`. Given a period, an optional shop (the line's shop, as the dashboard
      uses) and the viewer's allowed shops, it returns:
  - `gross_sales`: completed sales, before VAT;
  - `returns`: Σ `refund_net` of returns completed in the period;
  - `net_sales` = gross − returns;
  - `cost_of_sales`: cost of completed sales, minus Σ `cost_amount` of **every** returned line in the period
    (restocked or not), so goods the customers kept are all that remain;
  - `written_off`: Σ `cost_amount` of `written_off` and `to_supplier` lines in the period;
  - `net_cost` = cost of sales + written off (= sale cost − cost of restocked lines);
  - `net_profit` = net sales − cost of sales − written off. In the example: 800 − 480 − 120 = 200 (defective)
    or 800 − 480 − 0 = 320 (restockable). The written-off cost is already in the sales' cost, so it must not be
    added on top of it.
  - `returns_total`: the refunded amount including VAT, to compare with VAT-inclusive sales totals;
  - `cash_refunds`: cash refunds processed in the period;
  - `returns_count` and `cost_incomplete` (any line with cost unknown).
- [ ] Raw queries restricted with `TenantContext::restrictToShops()`. The file is listed in `RawQueryScopeTest`
      with its reason.
- [ ] Indexes for the period queries: `returns (shop_id, status, completed_at)`.
- [ ] Unit tests: the example above, on SQLite and MySQL; a return completed in a later period reduces that
      period, not the sale's; shop filtering; another business's returns never counted.

### 5. Every report reads `SalesFigures`
- [ ] **Web dashboard** (`DashboardController`): sales, cost and profit tiles become net figures, with a "Returns"
      figure beside them. The comparison with the previous period uses the same figures.
- [ ] **`ReportService`** (reports and exports: CSV, PDF): add Returns, Net sales, Written off and Net profit
      columns or lines. Gross figures stay as labelled.
- [ ] **`SaleController` daily and profit reports:** the same lines.
- [ ] **Top products** (web and API): units and revenue net of that product's returns in the period.
- [ ] **VAT report:** `creditNoteTax` reads `refund_tax` (the fee-adjusted figure), by `completed_at` rather than
      `updated_at`.
- [ ] **API `GET api/reports/daily-sales`:** keep every existing field as it is (`total_sales`, `total_cost` and
      `total_profit` stay gross), and **add** to `statistics`, money as strings:
  - `returns_count`;
  - `returns_total` (including VAT, like `total_sales`);
  - `net_sales` (`total_sales − returns_total`);
  - `net_cost` (`total_cost` − cost of restocked lines);
  - `written_off`;
  - `net_profit`;
  - `cash_refunds`.

  Changing `total_profit` to net was rejected:
  - it would disagree with `total_sales`, `total_cost` and the per-sale `total_profit` in `sales[]`;
  - it could go negative where the app has never seen it;
  - the contract test checks shape, not meaning, so it would pass silently. Record the new fields for the Flutter app (run
      `php artisan mobile:contract-diff`), and add them to `/api/v1` if that report exists there.
- [ ] Tests: each report shows the example's figures. The flat API contract test passes with the added fields.

### 6. Refunds reach the register and the credit account
- [ ] **Cash refunds:** when processed, a cash refund is a `cash_payout` (stage 10, step 4) in the shop's open
      register session, naming the refund, and is taken off `calculateExpectedBalance()` with the session's other
      payouts. With no open session, it's refused: "Open the register to pay a cash refund." (decided).
- [ ] **Refunds owed from stage 10:** a `refund_due` issue (from a void or a late M-Pesa payment) is settled by
      processing a refund for it; the refund names the issue, and the issue is resolved with it.
- [ ] **Credit sales and store credit:** when processed, a refund with method `store_credit`, or `original_payment`
      on a credit sale, posts an offsetting credit transaction (`RecordCreditTransaction`, as
      `SettleCreditSalePayment::reverse` does for voids). This lowers what the customer owes, or holds the amount
      as credit.
- [ ] Card, bank transfer and `original_payment` on non-cash, non-credit sales change neither.
- [ ] Tests: a cash refund is a payout and lowers the expected cash; refused without a session; a refund for a
      `refund_due` issue resolves it; a credit-sale refund lowers the
      balance; a card refund changes neither.

### 7. Fill in returns completed before this stage
- [ ] `php artisan returns:backfill-figures --business=<id>` (dry run by default; `--apply`). For each completed
      return, it fills `refund_net`, `refund_tax`, `cost_amount`, `disposition` and `completed_at` (from
      `updated_at`), using the same code as step 2. Write-off movements are recorded with the original date.
- [ ] It never changes `total_amount` or `refund_amount` on old returns, even if the new discount rule would have
      priced them differently. The refund was paid as it was; the command lists any difference.
- [ ] Tests: old returns filled once; a second `--apply` changes nothing; differences listed.

### 8. Screens
- [ ] On the return page, each line shows its disposition badge (`ReturnDisposition::badgeClass()`), its refunded
      value and its cost.
- [ ] On the sale page, a "Returns" panel lists returns with their refunded totals, and the sale's net figure.
- [ ] Reports show a "cost incomplete" note when any line in the period has no known cost (stage 10).

## Schema

| Change | Data carried across |
|---|---|
| `return_items.refund_net`, `refund_tax`, `cost_amount`, `disposition` | Filled by `returns:backfill-figures` for completed returns |
| `returns.completed_at` | Backfilled from `updated_at` for completed returns |
| None for cash refunds: they are `cash_payouts` (stage 10) | None |
| Index `returns (shop_id, status, completed_at)` | None needed |
| `purchase_returns.from_customer_return` boolean, default false; `return_item_id` (nullable) | Existing purchase returns take stock as before |

## Acceptance criteria

**Values**
- [ ] A return is valued at what the customer paid, discounts included. Returns can't exceed what's left to return.

**Reports**
- [ ] The example (10 sold at 100, cost 60, 2 defective returned) shows these on the dashboard, in reports and
      exports, and in the daily and profit reports:
  - net sales 800;
  - cost of sales 480;
  - written off 120;
  - net profit 200.
- [ ] The same example with a restockable reason shows net profit 320 and no write-off.
- [ ] A return completed in November reduces November's figures, not the sale's month.
- [ ] The VAT credit on a return with a restocking fee is on the refunded amount only.
- [ ] The API daily sales report keeps its existing fields unchanged and adds `returns_count`, `returns_total`,
      `net_sales`, `net_cost`, `written_off`, `net_profit` and `cash_refunds`. For the example these are 1, 200, 800,
      600, 120, 200 and the cash refunded.

**Stock and money**
- [ ] A defective return records a write-off movement at cost, with stock unchanged.
- [ ] Restocked lines are marked `is_restocked`.
- [ ] A cash refund lowers the register's expected cash.
- [ ] A credit-sale refund lowers the customer's balance.

**Data**
- [ ] Completed returns from before this stage are filled in once, with a dry run first. Old refund amounts are
      unchanged.

**Isolation and checks**
- [ ] Business A's returns never appear in business B's figures (isolation test for `SalesFigures`).
- [ ] Pint, the full SQLite and MySQL suites, the isolation suite and both contract tests pass.

## Risks

- **Report figures change for every business on deploy.** Today's gross figures are overstated wherever returns
  exist. Mitigation: labels say "Net"; gross stays visible; the release note explains it.
- **The Flutter app shows gross figures until it reads the new fields.** Mitigation: fields are additive, so
  nothing breaks. Record the paired change.
- **Requiring an open register for cash refunds blocks cash refunds at shops that don't use registers** (decided).
  Mitigation: the message tells the cashier to open the register, and card, transfer and store-credit refunds
  still work.
- **Valuing at the paid price changes refunds for discounted sales.** This is correct, but cashiers may notice.
  The return form shows the paid price per unit.

## Rollout (UAT)

1. `php artisan db:snapshot`; restore into `rehearsal_testing`; `migrate --database=rehearsal_testing`.
2. `returns:backfill-figures` on the rehearsal: dry run, check the listed figures, `--apply`. Compare the report
   figures before and after for one period.
3. The user runs `php artisan migrate` and the backfill on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated, and
[docs/vat.md](../../vat.md) and pos-domain-rules note the net figures. The pre-push checks (Pint, SQLite, MySQL)
pass, and their results are in the PR body.

## Points to confirm

All decided on 26 and 27 October 2026 (see "Decisions that apply"). Nothing remains open for this stage.

## Follow-ups (not this stage)

- Returns API (`/api/v1`) for the Flutter app.
- Let inspection change a line's disposition (e.g. a "quality issue" item found resaleable).
