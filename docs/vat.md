# VAT (Kenya)

## Overview

Until now the till stored whatever `tax_amount` the client posted. That is not a tax calculation —
it is a text box. `StoreSaleRequest` accepted `numeric|min:0|max:100000` and both `SaleController`s
wrote it straight to `sales.tax_amount`, so the VAT on an invoice was whatever the cashier or the
mobile app happened to type, and nothing recorded *which* supply it related to.

VAT is now derived from the sale lines by [`TaxService`](../app/Services/TaxService.php).

**The engine is off unless a shop is explicitly flagged VAT registered.** A business below the
registration threshold may not charge VAT, so `shops.vat_registered` defaults to `false` and every
existing shop keeps the previous behaviour byte for byte until someone turns it on.

## Tax classes

Kenyan VAT has more than one "no tax" answer, and they are not interchangeable — input VAT is
reclaimable on zero-rated supplies but not on exempt ones, so the VAT return reports them on
separate lines. [`TaxClass`](../app/Enums/TaxClass.php) mirrors the KRA/eTIMS tax categories:

| Case | Value | Rate | KRA code | Typical use |
|---|---|---|---|---|
| `Exempt` | `exempt` | 0% | A | Unprocessed foodstuffs, financial services |
| `Standard` | `standard` | 16% | B | Most retail goods — the default |
| `ZeroRated` | `zero_rated` | 0% | C | Exports |
| `NonVat` | `non_vat` | 0% | D | Outside the scope of VAT |
| `Reduced` | `reduced` | 8% | E | Petroleum products |

A product's `tax_class` may be left `NULL`, which means "inherit": the shop's default, then
`config('tax.default_class')`. Leave it blank unless the product genuinely differs, so a rate change
does not have to be applied product by product.

The KRA codes are stored deliberately. Nothing transmits to eTIMS yet, but when it does the code is
already on the line and does not have to be back-derived.

## Inclusive vs exclusive pricing

Both conventions are common in Kenya and the arithmetic differs, so `sales.tax_inclusive` records
which one produced a given total. That flag is what lets a sale still add up years later.

```
inclusive  (retail)     total = subtotal - discount + fees        VAT sits INSIDE subtotal
                        tax   = amount × r / (100 + r)

exclusive  (wholesale)  total = subtotal - discount + tax + fees  VAT added on top
                        tax   = amount × r / 100
```

Kenyan retail quotes VAT-inclusive shelf prices — the customer pays the ticket price — so
`prices_include_tax` defaults to `true`. Set it per shop in `Shop.settings['tax']` or globally with
`TAX_PRICES_INCLUDE_TAX`.

> ⚠️ Switching a shop to **exclusive** pricing changes what customers are billed: 16% is added to
> every standard-rated line. Do that deliberately, and see the Flutter caveat below.

## Discounts

VAT is chargeable on the discounted consideration, not on the list price. A sale-level discount is
apportioned across the lines pro-rata by value *before* the tax is worked out, with the rounding
residue pushed onto the last line so the shares always sum exactly to the discount.

```
1,160 inclusive, less a 160 discount → customer pays 1,000
                                     → VAT 137.93, net 862.07
```

## What gets stored

**`sales`**

| Column | Meaning |
|---|---|
| `tax_amount` | Total VAT on the sale |
| `tax_inclusive` | Which formula produced `total_amount` |
| `taxable_amount` | Turnover net of VAT |
| `tax_breakdown` | Per-class totals (JSON), for the invoice and the VAT return |
| `customer_tax_pin` | Buyer PIN, snapshotted so a reissue keeps the original |

**`sale_items`** — `tax_class`, `tax_rate`, `tax_amount`, `taxable_amount`, all snapshotted. Changing
a product's tax class later must never rewrite history.

**`shops`** — `tax_pin`, `vat_registered`. **`customers`** — `tax_pin`.

## Profit

`total_profit` used to be `total_amount - total_cost`, and `total_amount` contains the tax, so
**every taxed sale booked the VAT owed to KRA as margin.** It is now:

```php
total_profit = total_amount - tax_amount - total_cost
```

One formula covers both conventions. Under inclusive pricing the tax is already inside
`total_amount`; under exclusive pricing it was added to it. Either way it comes out. Delivery,
packaging and other charges *are* recharged to the customer, so they stay in.

Per line, profit is measured against `taxable_amount` — net of both VAT and the line's share of any
sale-level discount.

To correct historical sales, run
[`sales:recompute-profit`](../app/Console/Commands/RecomputeSaleProfit.php); it carried the same bug
and has been fixed alongside.

## Invoice numbering

Sales were numbered `'INV-'.Str::random(8)`. That is not a serial number: a tax invoice must come
from a gapless per-shop sequence, and a random string on a `unique` column eventually collides and
fails a sale at the till.

Numbers now come from `invoice_sequences` via
[`InvoiceNumberService`](../app/Services/InvoiceNumberService.php), which takes the row with
`lockForUpdate()` inside the sale's own transaction so concurrent registers queue instead of both
claiming the same number.

```
INV-NRB-2026-000123
 │   │    │     └── zero-padded counter (INVOICE_PAD)
 │   │    └──────── reset period (INVOICE_RESET=yearly)
 │   └───────────── shop code (INVOICE_INCLUDE_SHOP_CODE)
 └───────────────── INVOICE_PREFIX
```

Existing `INV-XXXXXXXX` numbers are left alone.

## The VAT summary report

**Reports → VAT Summary** (`reports.vat`, gated on `reports.view`; CSV export on `reports.export`).

Output VAT grouped by tax class and rate for a filing period, defaulting to the month just ended —
Kenyan VAT returns are monthly and due by the 20th of the following month. Customer returns appear
as credit notes, with the rate taken from the original sale line.

> This covers **output** VAT only. Input VAT on purchases is not tracked: purchase orders record a
> `tax_amount` but nothing captures the supplier's VAT registration or the tax class of what was
> bought, so claiming input VAT from this system would be guesswork. Treat the figures as a
> reconciliation aid for whoever files the return, not as the return itself.

Sales recorded before VAT was enabled carry no tax class and cannot be placed on a band. The report
counts them separately and says so, rather than reading as complete when it is not.

## Turning it on for a shop

1. `php artisan migrate` — adds the tax columns and `invoice_sequences` (additive only).
2. **Shops → edit → Tax & Compliance**: enter the KRA PIN, set the default tax class, choose whether
   prices include VAT, then tick **This shop is registered for VAT**.
3. Set `tax_class` on any product that is not standard-rated (**Products → edit → Tax Class**).
   Everything else inherits the shop default.
4. Make one test sale and check the receipt: it should be headed **TAX INVOICE** and show the VAT.
5. `php artisan sales:recompute-profit` (dry run), then `--apply`, to correct historical profit.

## ⚠️ Flutter client caveat

`sale_create_controller.dart` computes the total the customer sees locally:

```dart
double get total => subtotal - discount + tax + deliveryFee;
```

- **Inclusive pricing** (the default): no VAT is added to the total, so the app's figure matches the
  server's. Nothing breaks.
- **Exclusive pricing**: the server adds 16% that the app did not show. The app would display a
  total lower than the sale actually recorded.

Before switching any shop to exclusive pricing, the app needs a paired change to read the total back
from the create response rather than trusting its local sum. The response already carries
`tax_amount`, `tax_inclusive`, `taxable_amount` and `tax_breakdown`.

## Commands

```bash
php artisan sales:recompute-profit            # dry run — corrects historical profit
php artisan sales:recompute-profit --apply
php artisan customers:normalize-phones        # dry run — backfills the canonical MSISDN
php artisan customers:normalize-phones --apply --show-duplicates
```

## Configuration

[`config/tax.php`](../config/tax.php) and [`config/invoicing.php`](../config/invoicing.php); env keys
are listed in `.env.example`. Per-shop overrides live in `Shop.settings['tax']`:
`prices_include_tax`, `default_class`, `rates`.
