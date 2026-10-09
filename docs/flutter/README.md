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
