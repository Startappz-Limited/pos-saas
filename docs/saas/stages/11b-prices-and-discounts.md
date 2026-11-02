# Stage: Prices and Discounts

> **Source:** split from stage 11 on 27 October 2026 (sixth round). Its decisions were taken in the second to fourth
> rounds of 27 October 2026.
> **Branch:** `feat/prices-and-discounts`, from `main` once stage 11 is merged · **Depends on:**
> [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) (`product_units`, `shop_unit_prices` and
> its copy, `UnitPrice`, units on sale lines, the encoding helpers), [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md)
> (`permissions:sync`, refusal classes, `X-App-Version`, the till sync review)
> · **Runs in either order with:** [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md)
> · **App checks:** [app-release-checklist.md](./app-release-checklist.md#stage-11b) · **Traceability:**
> [TRACEABILITY-stock.md](./TRACEABILITY-stock.md)

---

## Revisions

**Split from stage 11 (27 October 2026, sixth round).** Stage 11 carried units, prices, low stock and snapshots over
two releases; it was split so each can be built and reviewed on its own. This stage takes everything about what a
line should cost and who may charge less: `PriceResolver::quote()`, quantity breaks, the server's price check,
`sales.override-price`, discount limits, offline approval by till PIN, and the pricing-rule `customer_id` fix. Stage 11
keeps the table `shop_unit_prices` and its copy (its release 2 drops the columns it replaces) and the simple unit price
order (`UnitPrice`); this stage builds on both. Where each moved part came from is listed in stage 11's Revisions.

---

## Goal

The server knows what every line should cost, and checks it. A unit's price comes from the shop's price for that unit,
else the unit's own, else the base unit's × factor; quantity breaks count base units across all the sale's lines of a
variation and never raise a price. A line charged below its quote, or a sale discounted beyond the person's limit,
needs someone allowed to override prices: online it is refused without them; offline it is recorded as charged,
because it happened, and reviewed.

## Before you start

Stage 11 must be merged (release 1 at least: `product_units`, `shop_unit_prices` filled, `UnitPrice`). Nothing remains
open (see "Points to confirm"). This stage is one release, additive.

## Decisions that apply

- **Quantity breaks count in base units (decided 27 October 2026):**
  - a rule's thresholds are in base units, summed across all lines of the variation in the sale: "24 or more" means
    24 bottles whether rung up as 1 crate, 24 bottles, or 1 crate plus 3 bottles on separate lines;
  - a rule's price is per base unit; a line in another unit is offered `rule price × factor`;
  - a unit with its own price is never overridden upward: the line's price is the lower of the unit's own price (after
    any shop override) and the rule's price × factor;
  - a rule may name a unit ("boxes only, 5 or more at X"); without one it applies to the variation in any unit.
- **The server checks prices (decided 27 October 2026, third round):** every sale the server records (web POS,
  `/api/v1`, the flat API, the push) is quoted line by line with `PriceResolver::quote()` at the sale's completion
  time. Online, a line below the expected price is refused (`price_below_expected`) unless the person holds
  `sales.override-price`; a higher price is allowed. Offline, the sale is recorded as charged and a lower price goes
  on the till sync review (`price_mismatch`). A difference within one cent per line is not a mismatch.
- **Sale-level discounts are limited and checked (decided 27 October 2026, fourth round):** a per-role limit (a
  percentage of the sale, set by the owner on the role screen; templates: cashier 0%, manager and owner no limit); a
  discount above the person's limit needs approval by someone holding `sales.override-price`
  (`discount_approved_by`); the price check compares the total after discount with the expected total. Online, an
  unapproved discount above the limit is refused (`discount_above_limit`); offline, recorded and flagged
  `price_mismatch`.
- **Offline approval uses the till PIN (decided 27 October 2026, fourth round):** the approver enters their till PIN
  on the same till, which checks it and their `sales.override-price` from the staff list; the push carries who approved
  and when; the server re-checks the permission at that time (it can't re-check the PIN; the audit row is the record).
  Approval PIN attempts count towards Phase 7's lockout.
- **Accepted as they are (decided 27 October 2026, third round):** old apps see no shop prices for non-base units
  (they can't sell them); pricing-rule thresholds stay whole base units.
- **Prices are configuration:** price lists, quantity breaks and discount limits are data the owner sets (rules,
  shop prices, the role screen); nothing is a code path per business type.
- **A fiscal number is never changed** (stage 10): a price check never renumbers or rewrites a recorded sale.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026, and stage 11's plan.

**Pricing rules** (`pricing_rules`):
- scoped to a **product** (`product_id`), not a variation, with `min_quantity` / `max_quantity` (integers), a price, a
  percentage or amount discount, dates, a priority and an optional customer;
- **evaluated nowhere on the server:** `PricingService::getBestPriceForProduct()` has no caller. The rules reach tills
  in the pull (`pricing_rules`), and the app applies them;
- `customer_id` is validated against `users` (`exists:users,id`) and its foreign key points at `users`, so a
  customer-specific rule names a staff member, not a customer;
- `calculateFinalPrice()` uses float arithmetic;
- **UAT:** 10 rules, none with a customer or a quantity threshold.

**The server doesn't price sales.** The web POS, the flat API and the push send each line's price, and `RecordSale`
records it (`unit_price => $item['price']`).

**Charging a different price:** no permission covers it. `pricing.view`, `.update`, `.bulk-update` and `.full-access`
manage the price list; `sales.edit-all` edits other people's sales; the till has no override. Sale lines carry no
discount of their own; the sale-level `discount_amount` needs no permission (`StoreSaleRequest` checks only that it
doesn't exceed the subtotal). **UAT:** 205 of 1,503 sales carry a discount.

**From stage 11:** `product_units` (with `selling_price`, `wholesale_price`), `shop_unit_prices` (filled by
`catalogue:copy-to-units`), `UnitPrice::for()` (shop price → unit's own → base × factor), units on sale lines, and the
encoding helpers.

---

## Work

Every part keeps to the tenancy rules: `BelongsToBusiness` / `BelongsToShop`, business-aware `exists:`, a
`ResourceMatrixTest` row for every new route.

### 1. `PriceResolver::quote()`
- [ ] **`App\Support\Pricing\PriceResolver`** prices a whole cart, because quantity breaks sum across lines:
      `quote(Shop $shop, ?Customer $customer, array $lines, ?CarbonInterface $at = null)` (each line a unit and a
      quantity) returns, per line, the price per unit and how it was found:
  1. **the unit's price:** `UnitPrice::for()` (stage 11): `shop_unit_prices` for that shop and unit, else the unit's own
     price (`wholesale_price` for a wholesale customer when set), else the base unit's price × factor;
  2. **the best rule** that applies at `$at` (step 2), as `rule price per base unit × factor`;
  3. **the lower of the two.** A rule never raises a price.
- [ ] Used by the web POS (it asks for a quote when the cart changes, `POST /pos/quote`, tenant-scoped, with its
      `ResourceMatrixTest` row), the flat API's product responses (single-unit prices), the sync pull, the store sync
      (base unit) and the pricing-rule preview. `PricingService::getBestPriceForProduct()` (no caller) is removed.
- [ ] **Shop prices per unit on screen:** the shop's product page edits `shop_unit_prices` for every unit (stage 11
      edits the base unit's only).
- [ ] Tests:
  - the shop's price for the crate wins over the crate's own price, which wins over 24 × the bottle;
  - a strip and a box of the same medicine are priced by their own price, else 10 × and 100 × the tablet;
  - `POST /pos/quote` answers for the shop's units only; another business's unit is a 404.

### 2. Quantity breaks in base units
- [ ] `pricing_rules.variation_id` (nullable: one variation of the rule's product; NULL: every variation of it) and
      `unit_id` (nullable: the rule applies only to lines in that unit; NULL: any unit).
- [ ] A rule's `min_quantity` / `max_quantity` are whole base units (they stay integers: no existing rule has one on
      UAT, and existing thresholds were already base units), compared with the sum of `base_quantity` over the sale's
      lines of that variation (of that unit only, for a unit-scoped rule).
- [ ] A rule's `price` is per base unit; `calculateFinalPrice()` works per base unit with the money helper, not floats.
- [ ] **Customer-specific rules name a customer:** `pricing_rules.customer_id` is re-pointed at `customers`
      (validation `exists:customers,id`, foreign key moved). `php artisan pricing-rules:check-customers
      --business=<id>` (read-only) lists any rule whose `customer_id` isn't a customer of the business; the re-point
      migration runs the same check and refuses if one exists, as a safety net only (none on UAT).
- [ ] Tests:
  - 1 crate, 24 bottles, and 1 crate + 3 bottles on separate lines reach the same "24 or more" tier;
  - a crate's own price of 2,280 beats a rule giving 2,400 (100 a bottle × 24); a rule giving 2,200 for 24 lowers the
    crate to 2,200; a shop price for the crate is the crate's own price in this comparison;
  - a rule scoped to boxes doesn't apply to strips; a rule scoped to one variation doesn't apply to another;
  - a customer-specific rule applies to that customer only; the check command lists a rule naming a user.

### 3. The server checks every price it records
- [ ] On every sale it records (the web POS `SaleController::store`, `RecordSale` for the flat API and the push, and
      `/api/v1`), the server quotes each line with `quote()` for the sale's shop, customer, units and quantities, at
      the sale's completion time (the push item's `recorded_at` offline, so dated rules apply as they did at the till).
      Online-store orders and abandoned carts are priced by the store and aren't checked.
- [ ] **Tolerance:** a line is below the expected price only when its charged total (unit price × quantity, rounded to
      cents) is more than KES 0.01 below its expected total (expected unit price × quantity, rounded to cents once).
      One cent per line, whatever the quantity.
- [ ] **Permission:** `sales.override-price` (new; the owner and the manager template, through `permissions:sync`).
      Nothing that exists fits (see "Relevant current state").
- [ ] **Each line records** `expected_unit_price` (per the unit sold, `decimal(12,2)`, as `unit_price`) and
      `price_overridden_by` (nullable user): who allowed a lower price. A line may name the person who authorised it
      (e.g. a manager at the till); it defaults to the person making the sale, and whoever it names must hold the
      permission.
- [ ] **Online** (web POS, `/api/v1`, flat API): a line below the expected price without the permission is refused,
      giving the expected price: the web POS shows it under the line ("The price for :item is below :expected. Ask
      someone who can override prices."); `/api/v1` answers 422 `price_below_expected` with each line's
      `expected_unit_price`; the flat API answers 422 `{success: false, message}` (its shape; no code). With the
      permission the line is accepted and `price_overridden_by` recorded. A line above the expected price is accepted
      and recorded, with its `expected_unit_price`.
- [ ] **Offline** (push): the sale is recorded as charged. Each line below the expected price records a
      `price_mismatch` issue on the till sync review: the line, the expected and charged prices, the person, the till,
      and whether the person named held `sales.override-price` on the server. Without it, the issue is open, even if
      the app let them. With it, the issue is recorded already resolved ("authorised override").
- [ ] The web POS takes its prices from `POST /pos/quote`, so its normal path never trips the check.
- [ ] Tests: a web POS line under the rule price is refused without `sales.override-price` and accepted with it, and
      `price_overridden_by` is recorded; an offline push under the expected price is recorded as charged and on the
      review as `price_mismatch` (open without the permission, resolved with it); a price above the expected one is
      accepted; a line one cent under on its total is not flagged, two cents is; a crate line is checked against the
      crate's own price, and a quantity break across lines is applied before comparing; a dated rule is judged at the
      sale's completion time; the flat API refusal keeps `{success: false, message}`; `/api/v1` answers
      `price_below_expected` with the expected prices.

### 4. Sale-level discounts are limited and checked
- [ ] `roles.max_discount_percent` (`decimal(5,2)`, nullable: no limit), set by the owner on the role screen
      (audited). Templates: cashier 0, manager and owner (`admin`) NULL; the inventory clerk and accountant 0. A
      person's limit is the highest of their roles' in the business. Business copies take their template's through
      `permissions:sync`; a limit the owner changed is kept.
- [ ] A discount above the person's limit needs someone holding `sales.override-price` to approve it; the sale records
      `discount_approved_by`.
- [ ] **The price check includes the discount:** the sale's charged total after its discount is compared with the
      expected total (Σ quoted lines), one cent per line of tolerance. A discount within the person's limit, or
      approved, is an allowed difference; any other shortfall is a mismatch.
- [ ] **Online:** a discount above the limit without approval is refused with `discount_above_limit` (`/api/v1`; the
      flat API's `{success: false, message}`; under the field on the web), giving the limit.
- [ ] **Offline:** recorded and flagged `price_mismatch` (with `kind = discount`).
- [ ] Tests: a discount within the cashier's limit is accepted; above it, refused online (`discount_above_limit`) and
      flagged offline; an approved discount is recorded with `discount_approved_by`; a discount taking the total below
      the expected one without being allowed is a mismatch; the owner changes a role's limit and it is audited.

### 5. Offline approval by the approver's till PIN
- [ ] When a cashier charges below the expected price, or discounts above their limit, the till asks for an approver,
      who enters their till PIN on the same till. The till checks the PIN (Phase 7's offline PIN check) and that the
      approver holds `sales.override-price` in its staff list.
- [ ] The push line carries `price_overridden_by`, the sale `discount_approved_by`, each with `override_approved_at`.
      The server re-checks that the approver held the permission at that time; if not, the sale is recorded and flagged
      `price_mismatch`. It can't re-verify the PIN, so the audit row (approver, time, till) is the record.
- [ ] Wrong approval PINs count towards Phase 7's PIN lockout (4 tries, 15 minutes doubling); the lockout is pushed
      as today's sign-in events are.
- [ ] Tests: a push line under the expected price with `price_overridden_by` naming someone who held
      `sales.override-price` at `override_approved_at` is recorded without an open issue; naming someone who didn't is
      recorded and flagged `price_mismatch`; an approval from an app older than this stage is absent, so the lower
      price is flagged.

### 6. The sync contract
- [ ] **Pull:**
  - a new entity `unit_prices`: the till's shop's `shop_unit_prices` (`unit_uuid`, `selling_price`,
    `wholesale_price`, `updated_at`); an old cursor's first pull sends them all;
  - `pricing_rules` gain `variation_uuid` and `unit_uuid` (null when the rule has none) and a customer's uuid from
    `customers`; `min_quantity` / `max_quantity` stay integers, in base units;
  - the staff list already carries each person's permissions (Phase 7), so `sales.override-price` reaches the till.
- [ ] **Push:** sale lines may carry `price_overridden_by` (a staff uuid) and `override_approved_at`; the sale may carry
      `discount_approved_by` and `override_approved_at`.
- [ ] **`/api/v1`:** sale creation answers `price_below_expected` and `discount_above_limit` (422) with the expected
      prices or the limit.
- [ ] Tests: a pull carries unit prices and the rules' new fields; the encoding follows stage 11's convention (prices
      are money strings as today); the flat API and v1 contract tests pass.

### 7. Housekeeping
- [ ] Domain rules (`pos-domain-rules`) and CLAUDE.md: `PriceResolver`, quantity breaks in base units, the price check
      and its tolerance, discount limits, `sales.override-price`.
- [ ] `docs/flutter/`: the `unit_prices` entity, the rules' new fields, the approval fields and the two refusal codes.
- [ ] **Note on `docs/implementation/06-pricing-management.md`**, under its title, once the stage ships (why: the
      pre-SaaS pricing design was never built as written):
      > **Superseded (stage 11b).** Never built as written (no `product_pricing` table). Prices live on units (stage
      > 11) with per-shop prices, and pricing rules with dates and quantity breaks, all resolved by `PriceResolver`;
      > the CSV import updates prices in bulk; `compare_at_price` is dropped. Every finding and where it went:
      > [TRACEABILITY-stock.md](../saas/stages/TRACEABILITY-stock.md).

## Contract (paired app changes)

The paired app release isn't scheduled; the server stays backward compatible with the app in shops. What the paired
app adds, without a date:
- stores `unit_prices` and prices a cart as `PriceResolver` does: the unit's price (shop price, own price, base ×
  factor), then quantity-break rules in base units summed across the variation's lines, then the lower of the two;
- checks prices and discounts as the server will: a price below the quote, or a discount above the person's role
  limit, needs an approver holding `sales.override-price` (from the staff list), who enters their till PIN on the
  same till; the line carries `price_overridden_by`, the sale `discount_approved_by`, each with
  `override_approved_at`; wrong approval PINs count towards the PIN lockout; online, on 422 `price_below_expected`
  or `discount_above_limit`, shows the expected price or the limit.

**Older apps:** they see no shop prices for units other than the base unit (decided: harmless, they can't sell them);
they apply quantity breaks per line only and can't name who overrode a price, so a lower price from them is recorded
and reviewed as `price_mismatch`. No refusal from this stage reaches an older app on the push: both codes are online
only. **Minimum app version** for the approval fields: the stage 11b app release (stage 10's `X-App-Version`). The app
checks are in [app-release-checklist.md](./app-release-checklist.md#stage-11b).

## Schema

| Change | Data carried across |
|---|---|
| `pricing_rules.variation_id`, `unit_id` (nullable) | NULL: every variation, any unit (today's meaning) |
| `pricing_rules.customer_id` re-pointed from `users` to `customers` | `pricing-rules:check-customers` lists any that isn't a customer (none on UAT); the migration re-checks as a safety net |
| `sale_items.expected_unit_price` (nullable), `price_overridden_by` (nullable user), `override_approved_at` (nullable) | None; past lines weren't checked |
| `sales.discount_approved_by`, `sales.override_approved_at` (nullable) | None |
| `roles.max_discount_percent` (`decimal(5,2)`, nullable) | Templates: cashier, clerk, accountant 0; manager and owner NULL; copies through `permissions:sync` |
| Permission `sales.override-price` | Granted to the owner and the manager template through `permissions:sync` |
| `ApiErrorCode` cases `price_below_expected`, `discount_above_limit` (online only) | None (an enum) |
| `sync_issues` type `price_mismatch` (with `kind`: `line` or `discount`) | None (a string column) |

**The sync contract changes:** the pull gains the entity `unit_prices` and the rule fields `variation_uuid` and
`unit_uuid`; push lines gain `price_overridden_by` and `override_approved_at`, sales `discount_approved_by`. All are
additions.

## Acceptance criteria

- [ ] `PriceResolver` prices every unit as decided; quantity breaks count base units across lines, never raise a price,
      and respect a rule's unit and variation.
- [ ] Every sale the server records is checked against `quote()`: online, a lower price needs `sales.override-price`
      and the override is recorded; offline, it is recorded and reviewed; one cent per line is tolerated.
- [ ] Discounts above a role's limit need approval; the price check covers the total after discount; offline approvals
      are by the approver's till PIN and re-checked by the server.
- [ ] Customer-specific rules name customers.
- [ ] The app's recorded requests still succeed against the new server; the flat API and v1 contract tests pass.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass.

## Risks

- **A 0% cashier discount limit refuses discounts cashiers give today.** UAT: 205 of 1,503 sales carry a discount.
  Mitigation: the release note says so; the owner raises the cashier role's limit, or a manager approves; offline,
  nothing is refused, only flagged.
- **The price check refuses sales at the web POS and online** where it didn't before. Mitigation: the web POS prices
  from the quote, so only a typed lower price trips it; managers hold the override; offline sales are never refused
  for price.
- **Offline approvals rest on the till's PIN check**, which the server can't repeat. Mitigation: the server re-checks
  the approver's permission at that time and audits every approval; wrong PINs count towards the lockout.
- **Rules can no longer raise a price.** No UAT rule does; a business that relied on it would see the lower price.
- **A discount within the person's limit is an allowed difference,** so the limits the owner sets decide how far a sale
  can fall below the quote.

## Rollout (UAT)

1. Take a snapshot of UAT (`db:snapshot`); restore it into `rehearsal_testing`.
2. `pricing-rules:check-customers --business=<id>` for every business (read-only; none expected).
3. Deploy and run the migrations on the rehearsal (additive; the re-point's check passes).
4. `permissions:sync` (adds `sales.override-price` to the owner and manager copies and the templates' discount limits
   to their copies; check a manager copy has the permission and a cashier copy a 0% limit).
5. Check the server against the app's recorded requests (pushes without approval fields, flat API sales): they
   succeed, and a recorded push below the quote is recorded and flagged.
6. The user runs steps 2 to 4 on UAT after a fresh snapshot, and tells owners about the cashier discount limit.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing remains open. The decisions of 27 October 2026 stand.

## Follow-ups (not this stage)

- `/api/v1` endpoints to manage shop prices from the app.
- Fractional quantity-break thresholds (2.5 kg or more), if a business needs them.
