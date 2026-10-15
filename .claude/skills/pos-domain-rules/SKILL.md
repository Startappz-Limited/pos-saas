---
name: pos-domain-rules
description: The business rules of this POS system as the code actually implements them — sale and payment lifecycles, how stock is really mutated, how COGS is captured, per-line shop attribution, the return/refund unwind, customer credit accounts, cash-register reconciliation, and pricing resolution. Also maps which named services and models are empty stubs. Use before changing anything touching sales, stock, cost, returns, refunds, credit, registers, expenses, pricing, or reports — and whenever you need to know where a business rule actually lives.
---

# POS Domain Rules (as implemented)

This is the knowledge that is hardest to recover from the code and most expensive to get wrong. Every
claim here was verified against the source; where the code contradicts `CLAUDE.md` or `.ai/general`,
**the code is documented and the contradiction is called out.**

Read this before touching money, stock, or cost. Pair it with
[laravel-database](../laravel-database/SKILL.md) for schema work and
[laravel-performance](../laravel-performance/SKILL.md) for report queries.

## 🔴 First: many named classes are empty stubs

Do **not** assume a class does what its name suggests. `CLAUDE.md` and the architecture docs name
services that contain no code. Verified empty (`class X { }`):

**Services (9):** `SaleService`, `PaymentService`, `CustomerService`, `ExpenseService`,
`ExpenseCategoryService`, `NotificationService`, `DashboardService`, `CostingService`, `AuditService`.

**Models (~20):** `CostLayer`, `CostAllocation`, `SaleItemStockAllocation`, `Payment`, `CreditPayment`,
`CreditLimitRequest`, `OverdueNotice`, `AuditLog`, `AlertRule`, `AlertRecipient`, `NotificationLog`,
`NotificationPreference`, `ExpenseReceipt`, `ExpenseApprovalHistory`, `CampaignMetric`,
`CampaignExpense`, `CampaignConversion`, `DashboardWidget`, `SavedReport`, `ReportExport`,
`ShopPaymentMethod`.

**Controllers (4) — with 26 registered routes that throw at runtime:** `AlertController` (13 routes),
`PaymentController` (6), `AuditLogController` (4), `CostLayerController` (3).

Consequences for your work:
- **Sale creation logic lives inline in the controllers**, not in `SaleService`. The real implementations
  are [SaleController](../../../app/Http/Controllers/SaleController.php) (878 lines) and its API twin
  [Api/SaleController](../../../app/Http/Controllers/Api/SaleController.php), which must stay in sync.
- Before "following the existing service pattern" for sales/payments/expenses, check whether the service
  has a body. Grep the real call path instead of trusting the name.
- Don't fill in a stub mid-task as a side effect. If logic belongs in a service, say so and get agreement
  — quietly moving a sale path is a large behavioural change.

## Sale lifecycle

`SaleStatus`: `draft` → `pending` → `completed` → (`voided` | `refunded`).
`PaymentStatus`: `unpaid` → `partial` → `paid` → `refunded`. Both are separate axes — a `completed` sale
can be `unpaid` (that is what credit sales are).

`SaleType` and `CustomerType` drive wholesale vs retail behaviour, including which price applies and how
reports segment revenue (`ReportService` computes wholesale and retail summaries from the same filtered
base query).

Guardrails already encoded in policy, not schema: `SalePolicy::update()` restricts edits to a time window
after creation, and deletion is admin-only. Preserve those when adding sale mutations.

## How stock is mutated (since SaaS stage 1b)

Stock lives **per shop and per variation** in `shop_variation` (`(shop_id, variation_id)`); every product
has a hidden default variation. The old `products.stock_quantity`, `product_variations.stock_quantity`
and `shop_product.stock_quantity` columns are **gone**: `stock_quantity` on Product and ProductVariation is
a computed attribute (`HasShopStock`) — the shop the request names, else the viewer's accessible shops —
and setting it moves the product's own shop to that figure through the ledger.

**One service changes stock: `App\Services\Stock\StockLedger`.** It locks the `shop_variation` row,
changes it by a delta, writes a `StockMovement`, and creates or consumes FIFO cost layers.

| Operation | Ledger call |
|---|---|
| Sale (all four paths) | `sellItem($saleItem, $variation)` per line, then `recostSale($saleId)` |
| Customer return, restockable items | `receive()` at the sold `unit_cost` (`ReturnService::receiveReturn`) |
| Stock intake | `receive()` at the PO line cost, else `fallbackCost()` |
| Stock adjustment | `StockAdjustmentService` → ledger; refuses to go negative |
| Purchase return to supplier | `consume()` preferring the intake's layer |
| E-commerce sync, product edits | `setLevel()` |

Never write a second stock-mutation path, and never update `shop_variation.stock_quantity` directly. Sales
made **before** stage 1b have no `StockMovement` rows, so the ledger is complete only from the cut-over.

## How COGS is captured — FIFO per shop and variation

- Intake, restocked returns, upward adjustments and opening stock create **cost layers**
  (`cost_layers`); consumption takes the oldest layer first and records `cost_allocations`.
- A sale line's cost is the sum of its allocations; `sale_items.unit_cost` is that over the quantity, and
  `total_profit = total_amount − tax_amount − total_cost`.
- **Below zero** (offline sales are always accepted): the shortfall is allocated **provisionally** at
  `fallbackCost()` and re-costed when stock arrives, which updates the sale's cost and profit.
- A layer with unknown cost (NULL) costs 0 until a purchase cost is entered; `sales:recompute-profit`
  then fills the allocation and the rest of that layer. See [docs/purchase-costs.md](../../../docs/purchase-costs.md).
- `CostingService`, the `CostingMethod` enum and `sale_item_stock_allocations` remain **unused**.
- Design and settled cases: [docs/saas/fifo-analysis.md](../../../docs/saas/fifo-analysis.md).

## 🔴 Per-line shop attribution

A single sale can contain products belonging to different shops. Attribution is therefore **per line, not
per sale**: `sale_items.shop_id` is set from the **product's owning shop**, and revenue/profit reports
aggregate from items (`ReportService::getSalesByShopFromItems()`), not from `sales.shop_id`.

This is recent, deliberate work (the `shop_id` column on `sale_items`, the `AssignProductShopFromSales`
and `BackfillShopProductPivot` commands) and it is locked in by
[tests/Feature/SalePerShopAttributionTest.php](../../../tests/Feature/SalePerShopAttributionTest.php).

Rules:
- When you add a sale line anywhere, set `sale_items.shop_id` from the product's shop — not from the
  sale header, and not from the acting user's shop.
- When you write a per-shop revenue or profit report, aggregate **from `sale_items`**. Grouping `sales`
  by `shop_id` gives the wrong answer for mixed-shop sales, and will quietly disagree with the existing
  dashboard.
- `sales.shop_id` still means "where the transaction was rung up" — both columns are meaningful, so don't
  collapse them.

## Returns and refunds

`ReturnStatus`: `pending` → `approved` | `rejected` → `received` → `inspected` → `completed`.
A `Refund` is a **separate record** with its own `pending` → processed step
(`ReturnService::createRefund()` then `processRefund()`), so a return being approved is not the same as
money having moved.

Money rules, from `ReturnService`:
- `refund_amount = max(0, total_amount − restocking_fee)` — the floor at zero is deliberate; a restocking
  fee larger than the line total must never produce a negative refund.
- `createRefund()` throws unless the return is in a refund-eligible status; `processRefund()` throws
  unless the refund is still pending. Keep both guards.
- Stock is **not** restored (see above).

Purchase returns (to suppliers) are a different flow with their own enums
(`PurchaseReturnStatus`, `PurchaseReturnReason`) and they *do* record stock movements.

## Customer credit accounts

`CreditAccount` denormalises three money columns: `credit_limit`, `current_balance`, `available_credit`,
all `decimal(10,2)`. `available_credit` is **derived** — recalculated via
`$creditAccount->calculateAvailableCredit()` in the model's save path. Never set it by hand; change
`credit_limit` or `current_balance` and let it recompute, or the three fall out of sync.

`CreditTransactionType` is the full vocabulary of balance movements: `purchase`, `payment`,
`adjustment_credit`, `adjustment_debit`, `refund`, `write_off`, `opening_balance`. Every balance change
should be expressible as one of these — if you find yourself needing a new kind of movement, add an enum
case rather than an untyped adjustment.

`CreditAccountService` handles limits, suspension/reactivation, aging (`getAgingReport()`), overdue
detection, and `syncCustomerCredit()`. Note `CreditPayment`, `CreditLimitRequest`, and `OverdueNotice`
models are **stubs**, so features implied by those tables aren't wired.

A credit sale is a `completed` sale with `payment_status` of `unpaid`/`partial` — the two axes again.

## Cash register reconciliation

Despite its name, `CashRegister` (`cash_registers`) is a **cash session**. Since SaaS stage 1b every
session runs on a `Register` (a till; `cash_registers.register_id`), and every shop has a default register
`R1` created with the shop. `openRegister()`/`getActiveRegister()` take a register and default to `R1`,
so the legacy calls land there. Each register numbers its invoices in its own series
(`InvoiceNumberService::next($shop, $session->register_id)`).

`CashRegister` carries `opening_balance`, `closing_balance`, `expected_balance`, `variance`, plus
`closing_notes`, and has both `sales()` and `expenses()` relations. Expenses can be **paid out of the
drawer**, which is why `getRemainingExpenseBalance()` exists and why reconciliation is not simply
`opening + sales`.

Rules:
- `variance = closing_balance − expected_balance`. A non-zero variance is a business signal, never
  something to silently correct.
- Expenses settled from the register must be included when deriving `expected_balance` — the existing
  `ExpenseSettleFromRegisterTest` and `CashRegisterExpenseOpeningBalanceTest` encode this; read them
  before changing register maths.
- Close operations are throttled (`throttle:5,1`) and policy-gated. A register should not be closable
  twice.

## Pricing resolution

`PricingService::getBestPriceForProduct()` is the single entry point for "what does this cost right now".
Pricing rules are time-bounded (active / upcoming / expired scopes) and typed by `PricingType` and
`DiscountType`. Wholesale vs retail follows `CustomerType`/`SaleType`.

Always resolve price through that method rather than reading `selling_price` directly, or time-bounded
promotions and wholesale tiers silently stop applying.

## Money and quantity invariants

- Money is `decimal(10,2)` everywhere. Never store money in a float column, and be careful comparing
  decimals returned as strings.
- Stock quantities are **integers**. No fractional stock.
- Multi-step writes (sale + items + payments + register entries) belong in **one** `DB::transaction`.
  The existing controllers do this inline with `DB::beginTransaction()` / commit / rollback — match the
  sibling.
- Derived totals (`total_cost`, `total_profit`, `profit_margin`, `available_credit`) are persisted. If you
  change an input, recompute the derived value in the same transaction.

## When you touch any of this

1. Grep the real call path before trusting a class name — the stub list above is long.
2. Ask which axis you're changing: sale status, payment status, stock, cost, or attribution. They move
   independently.
3. Mirror the change into the web ↔ API twin (mandatory — see [laravel-api](../laravel-api/SKILL.md)).
4. Write the test that pins the invariant, not just the happy path: resulting stock, resulting totals,
   resulting per-shop attribution. See [laravel-testing](../laravel-testing/SKILL.md).
5. If a fix would change historical numbers (restocking returns, real FIFO costing, re-attributing past
   sales), stop and flag it — that's a data-migration decision, not a code change.
