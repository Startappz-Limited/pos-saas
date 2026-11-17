# Flutter POS Mobile App — API Documentation

This documentation covers all API endpoints required to build the Flutter POS mobile application for the Fitness Center platform.

## Overview

The Flutter app provides a mobile POS (Point of Sale) interface that connects to any hosted instance of this platform. Users provide a **base URL**, **email**, and **password** to authenticate.

## Authentication

All API endpoints use **Laravel Sanctum** token-based authentication.

- **Login**: `POST {base_url}/api/login` — returns a Bearer token
- **Logout**: `POST {base_url}/api/logout`
- All subsequent requests must include: `Authorization: Bearer {token}`

## Base Headers

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

## App Features & Documentation

| # | Feature | Documentation |
|---|---------|--------------|
| 1 | Authentication | [01-authentication.md](01-authentication.md) |
| 2 | Cash Register (Open/Close) | [02-cash-register.md](02-cash-register.md) |
| 3 | Make Sale (POS) | [03-sales.md](03-sales.md) |
| 4 | Website Orders (View & Convert) | [04-ecommerce-orders.md](04-ecommerce-orders.md) |
| 5 | Calendar | [05-calendar.md](05-calendar.md) |
| 6 | Reports (Daily) | [06-reports.md](06-reports.md) |
| 7 | Shipping Companies | [07-delivery-companies.md](07-delivery-companies.md) |
| 8 | Request Stock (Purchase Orders) | [08-purchase-orders.md](08-purchase-orders.md) |
| 9 | Manage Products | [09-products.md](09-products.md) |
| 12 | Expenses | [12-expenses.md](12-expenses.md) |
| 14 | Customers | [14-customers.md](14-customers.md) |

## Helpers & References

| # | Topic | Documentation |
|---|-------|--------------|
| 10 | Enums Reference | [10-enums.md](10-enums.md) |
| 11 | Authorization Helper | [11-authorization.md](11-authorization.md) |

## API Route Prefix

All endpoints are prefixed with `/api/` and versioned under the base URL provided by the user.

```
{base_url}/api/{endpoint}
```

## Error Response Format

All error responses follow a consistent structure:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message here."]
  }
}
```

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (deleted) |
| 401 | Unauthenticated |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

## Implementation Notes

- All monetary values are stored as `decimal(10,2)` — send and receive as numbers, not strings.
- UUIDs are used as public-facing identifiers for most resources. Internal `id` fields are integers.
- Pagination follows Laravel's standard: `{ data: [], links: {}, meta: { current_page, last_page, per_page, total } }`.
- Timestamps are in ISO 8601 format (`2026-03-18T10:30:00.000000Z`).

## Quantities and units (stage 11)

- **Convention.** A quantity the app reads as a number stays a JSON number: the flat API's `quantity`,
  `stock_quantity`, `reorder_level` and `quantity_before/change/after`, and the `/api/v1` receipt's line
  `quantity`. A whole value is an integer (`2`); only a fractional one is a decimal (`2.5`), and that happens only
  when a business sells by a unit with decimals.
- **New decimal fields are strings with the unit's decimals** (`"2.500"`): `base_quantity`, `quantity_exact`,
  `base_reorder_level`, `sellable_quantity`, `expired_quantity`, a unit's `factor`.
- **Pull** (`/api/v1/sync/pull`):
  - `units`: `uuid`, `variation_uuid` (null for a simple product's hidden variation), `product_uuid`, `name`,
    `factor`, `precision`, `barcode`, `selling_price`, `wholesale_price`, `is_base`, `can_sell`, `can_buy`,
    `sort_order`, `deleted_at`, `updated_at`. A deleted unit comes as an update with `deleted_at` set and
    `can_sell`/`can_buy` false, never in `deletions`: past receipts still name it.
  - `stock`: `quantity` and `reorder_level` are whole numbers, rounded down; `base_quantity`, `base_reorder_level`,
    `sellable_quantity`, `expired_quantity` (strings) and `next_expiry` (date or null).
  - `variations`: `track_batches`. `settings.stock.consumption`: `fifo` or `fefo`.
- **Push sale lines** take `unit_id` (a unit uuid, optional; the base unit when missing) and `base_quantity` (a
  string, optional). The till's `base_quantity` is kept; if it disagrees with quantity x factor the server records a
  `unit_factor_mismatch` issue. A unit the server doesn't have holds the sale as `unknown_unit` (disposition `hold`)
  until someone discards it with `outbox_item_discarded`.
- **Flat API:** sale lines carry `unit` (the unit record) and `base_quantity`; products carry `units`; the product
  search also matches a unit's own barcode, exactly.
- **Not sent:** a waiting product barcode (`unassigned_barcodes`) appears on no pull or flat response until an owner
  assigns it.
