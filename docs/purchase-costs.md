# Purchase Costs (COGS) Documentation

## Overview

Purchase cost is what the shop **paid a supplier** for a unit. It is the basis for COGS: at the
moment of sale the product's current `cost_price` is snapshotted onto `sale_items.unit_cost`, and
profit is derived from it. Get the cost wrong and every report built on it is wrong — permanently,
because the snapshot is never revisited.

A purchase cost is only trustworthy when it comes from a supplier. There are exactly two sources:

1. **Inventory intake** — a purchase order captures `unit_cost` per line.
2. **Manual entry** — a user holding `products.set-cost` keys it in on the Purchase Costs screen.

Products that arrive from WooCommerce or Shopify have **neither**, so their cost is deliberately left
`NULL` ("not established yet") rather than guessed.

## Why cost is nullable

`products.cost_price` and `product_variations.cost_price` allow `NULL`. `NULL` means *unknown*; `0`
would mean *this genuinely costs nothing to buy*, which is never true here. Keeping the two distinct
is what lets the Purchase Costs screen and the commands below find the gaps.

Check it in code with the helpers, not the raw column:

```php
$product->hasPurchaseCost();          // false for NULL and for 0
$product->hasCostAboveSellingPrice(); // the "sells at a loss" fingerprint

Product::missingPurchaseCost()->get();
Product::costAboveSellingPrice()->get();
```

> ⚠️ `sale_items.unit_cost` is **NOT NULL**. Every read of `cost_price` used in arithmetic must
> coalesce: `($variation ? $variation->cost_price : $product->cost_price) ?? 0`. Both `SaleController`s
> already do. Omitting it throws on any sale of an uncosted product.

## The bug this replaced

The e-commerce importers wrote a **selling** price into the cost column:

| Importer | Old mapping | Why it broke |
|---|---|---|
| WooCommerce | `cost_price = regular_price` | Woo core has no cost-of-goods field. `regular_price` is the pre-discount selling price, so any discounted product had `cost > price`. |
| Shopify | `cost_price = compare_at_price ?? price` | `compare_at_price` is the struck-through "was" price, always ≥ what the customer pays. |

Result on live data: 60 of 78 products priced to lose money, and 19 of 25 sales reporting negative
profit (−17,055 net).

Both importers now leave the cost `NULL` unless a **real** one is available — a WooCommerce
cost-of-goods meta key (`_wc_cog_cost`, `_alg_wc_cog_cost`, `_cost_of_goods`,
`_wc_cog_cost_purchase`) or Shopify's `inventory_item.cost` — and they **never overwrite a cost that
already exists locally** on re-sync.

## Commands

Both commands are **dry-run by default**. Neither writes anything until you pass `--apply`, and both
print exactly what they would change first.

### `php artisan products:repair-purchase-cost`

Clears purchase costs that were never purchase costs — the values the importers derived from a
selling price. It resets them to `NULL` so they resurface for manual entry, rather than leaving a
figure that guarantees a negative margin.

```bash
php artisan products:repair-purchase-cost            # dry run: list what would be cleared
php artisan products:repair-purchase-cost --apply    # clear them
```

| Option | Effect |
|---|---|
| `--apply` | Write the changes. Without it nothing is touched. |
| `--strict` | Only clear costs **strictly above** the selling price, keeping exact zero-margin ones (`cost == price`). Default also clears those, since they are the same importer artifact on an undiscounted product. |
| `--shop=<id>` | Restrict to products attached to one shop. |
| `--force` | Skip the confirmation prompt (only prompts in production). |

Products are saved one at a time so the `Auditable` trait records the previous value of every cost it
clears. Take a snapshot first anyway if you want a trivially restorable copy:

```bash
php artisan tinker --execute="file_put_contents('costs-backup.json', DB::table('products')->whereColumn('cost_price','>=','selling_price')->get(['id','sku','cost_price'])->toJson());"
```

### `php artisan sales:recompute-profit`

Re-derives `unit_cost`, `total_cost`, `profit` and `profit_margin` on existing `sale_items`, then
`total_cost` and `total_profit` on their `sales`, using each product's **current** purchase cost. This
is what corrects historic reports after you enter the real costs — the sale-time snapshot does not
update itself.

```bash
php artisan sales:recompute-profit           # dry run: show the before/after totals
php artisan sales:recompute-profit --apply   # write the corrected figures
```

| Option | Effect |
|---|---|
| `--apply` | Write the changes. Without it nothing is touched. |
| `--sale=<uuid>` | Recompute a single sale. |
| `--from=YYYY-MM-DD` / `--to=YYYY-MM-DD` | Restrict to a date range. |
| `--zero-unknown` | Treat a still-unknown cost as `0` instead of skipping the sale. Overstates profit — use only when you accept that. |

**It skips any sale still containing a product with no purchase cost**, rather than costing it at
zero. That makes it safe to run early and repeatedly: work through the Purchase Costs screen, re-run,
and the skipped count falls to zero. The summary table reports how many were skipped and the total
profit before and after.

Profit is measured the same way the sale path measures it — against the full sale total including
fees, tax and discount (`total_profit = total_amount - total_cost`), not just the sum of line profits.

## Full recovery sequence

Order matters. Steps 1–2 are prerequisites; the importers error against the old schema.

```bash
# 1. Allow "cost unknown" in the schema
php artisan migrate

# 2. Register the products.set-cost permission and grant it to
#    super-admin / manager / inventory-clerk (both seeders are additive —
#    they use firstOrCreate and givePermissionTo, never revoke or sync)
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder

# 3. Clear the importer-derived costs
php artisan products:repair-purchase-cost
php artisan products:repair-purchase-cost --apply

# 4. Enter the real costs in the UI:
#    Products → Purchase Costs   (route: products.purchase-costs.index)

# 5. Correct the historic sales
php artisan sales:recompute-profit
php artisan sales:recompute-profit --apply
```

> Permissions and roles live only on the **default `mysql` connection**. The separate `audit` SQLite
> connection holds `audit_logs` and nothing else, so there is no second permission table to seed.
> If you run more than one deployment, every environment needs steps 1–2 of its own.

### Between step 3 and step 5

Cleared costs mean affected sales record **zero COGS**, so profit is *overstated* rather than
negative. That is the intended trade-off of not inventing a cost, and step 5 corrects it
retroactively. Work through the Purchase Costs screen promptly.

## The Purchase Costs screen

**Products → Purchase Costs**, gated by `ProductPolicy::setCost` (`products.set-cost`; held by
super-admin, manager and inventory-clerk).

- Lists products whose cost is unset or above their selling price, filterable by search, category and
  problem type.
- Shows the most recent purchase-order unit cost as a suggestion for products that *did* come through
  inventory intake.
- Blank fields are left unchanged, so you can fill the list in over several passes.
- Each save goes through `SetProductPurchaseCostsAction`, one model at a time, so the audit trail
  records who set which cost.

Products with variations are costed per variation, since that is where their `cost_price` lives.

## Related

- Tests: [tests/Feature/PurchaseCostTest.php](../tests/Feature/PurchaseCostTest.php)
- Business rules: [.claude/skills/pos-domain-rules/SKILL.md](../.claude/skills/pos-domain-rules/SKILL.md)
- Roles and permissions: [ROLES_AND_PERMISSIONS.md](ROLES_AND_PERMISSIONS.md)
