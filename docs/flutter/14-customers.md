# 14. Customers

Manage customers including creation, editing, credit settings, and activation status.

---

## List Customers

`GET /api/customers`

Returns paginated customers for the authenticated user's shop.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by name, email, phone, or code |
| `status` | string | Filter by status: `active`, `inactive` |
| `customer_type` | string | Filter by type: `retail`, `wholesale` |
| `per_page` | int | Items per page (default: 20) |
| `page` | int | Page number |

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "uuid": "a1b2c3d4-...",
        "code": "CUS-AB12CD",
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+254712345678",
        "address": "123 Main Street, Nairobi",
        "customer_type": "retail",
        "allow_credit": false,
        "credit_limit": "0.00",
        "credit_balance": "0.00",
        "status": "active",
        "notes": null,
        "shop_id": 1,
        "created_by": 1,
        "updated_by": null,
        "created_at": "2026-03-20T10:00:00.000000Z",
        "updated_at": "2026-03-20T10:00:00.000000Z",
        "deleted_at": null
      }
    ],
    "last_page": 3,
    "per_page": 20,
    "total": 45
  }
}
```

---

## Create Customer

`POST /api/customers`

### Request Body

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+254712345678",
  "address": "123 Main Street, Nairobi",
  "customer_type": "retail",
  "allow_credit": false,
  "credit_limit": 0,
  "notes": "VIP customer"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `name` | **required**, string, max 255 |
| `email` | nullable, valid email, max 255 |
| `phone` | nullable, string, max 50 |
| `address` | nullable, string |
| `customer_type` | **required**, must be `retail` or `wholesale` |
| `allow_credit` | boolean (default: false) |
| `credit_limit` | nullable, numeric, min 0 |
| `status` | **required**, must be `active` or `inactive` (default: `active`) |
| `notes` | nullable, string |

> **Note:** `code`, `uuid`, `shop_id`, and `created_by` are set automatically.

### Success Response `201`

```json
{
  "success": true,
  "message": "Customer created successfully.",
  "data": {
    "id": 5,
    "uuid": "e5f6g7h8-...",
    "code": "CUS-XY34ZW",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+254712345678",
    "address": "123 Main Street, Nairobi",
    "customer_type": "retail",
    "allow_credit": false,
    "credit_limit": "0.00",
    "credit_balance": "0.00",
    "status": "active",
    "notes": "VIP customer",
    "shop_id": 1,
    "created_by": 1,
    "created_at": "2026-03-23T10:00:00.000000Z"
  }
}
```

### Error Response `422`

```json
{
  "message": "The customer name is required.",
  "errors": {
    "name": ["The customer name is required."]
  }
}
```

---

## Show Customer

`GET /api/customers/{uuid}`

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 5,
    "uuid": "e5f6g7h8-...",
    "code": "CUS-XY34ZW",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+254712345678",
    "address": "123 Main Street, Nairobi",
    "customer_type": "retail",
    "allow_credit": false,
    "credit_limit": "0.00",
    "credit_balance": "0.00",
    "status": "active",
    "notes": "VIP customer",
    "shop_id": 1,
    "created_by": 1,
    "updated_by": null,
    "created_at": "2026-03-23T10:00:00.000000Z",
    "updated_at": "2026-03-23T10:00:00.000000Z"
  }
}
```

---

## Update Customer

`PUT /api/customers/{uuid}`

Partial updates are supported — only send fields you want to change.

### Request Body

```json
{
  "name": "John Updated",
  "phone": "+254799999999",
  "allow_credit": true,
  "credit_limit": 50000
}
```

### Success Response `200`

```json
{
  "success": true,
  "message": "Customer updated successfully.",
  "data": { }
}
```

---

## Delete Customer

`DELETE /api/customers/{uuid}`

Soft deletes the customer (can be restored).

### Success Response `200`

```json
{
  "success": true,
  "message": "Customer deleted successfully."
}
```

---

## Activate Customer

`POST /api/customers/{uuid}/activate`

Sets the customer's status to `active`.

### Success Response `200`

```json
{
  "success": true,
  "message": "Customer activated successfully.",
  "data": { }
}
```

---

## Deactivate Customer

`POST /api/customers/{uuid}/deactivate`

Sets the customer's status to `inactive`.

### Success Response `200`

```json
{
  "success": true,
  "message": "Customer deactivated successfully.",
  "data": { }
}
```

---

## Required Permissions

| Action | Permission |
|--------|-----------|
| List / View | `customers.view` |
| Create | `customers.create` |
| Update | `customers.update` |
| Delete | `customers.delete` |
| Activate | `customers.activate` |
| Deactivate | `customers.deactivate` |
| Manage Credit | `customers.manage-credit` |

> Users with `super-admin` role bypass all permission checks.

---

## Flutter Implementation Notes

- Customer `uuid` is used in all URL paths (not numeric `id`).
- The customer `code` (e.g. `CUS-AB12CD`) is auto-generated — do not send it in create/update requests.
- When `allow_credit` is `false`, `credit_limit` is automatically set to `0`.
- Use the `search` query param for a unified search across name, email, phone, and code.
- Use `activate`/`deactivate` endpoints instead of updating `status` directly.
