# 7. Delivery Companies (Shipping)

Manage delivery/shipping companies used in sales.

---

## List Delivery Companies

`GET /api/delivery-companies`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by name, phone, contact person |
| `is_active` | boolean | Filter by active status |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "d1e2l3i4-...",
      "name": "Express Delivery",
      "contact_person": "Ali Ahmed",
      "phone": "+971501234567",
      "email": "ali@express.com",
      "address": "Warehouse 5, Industrial Area",
      "is_active": true,
      "created_at": "2026-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

## Show Delivery Company

`GET /api/delivery-companies/{uuid}`

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "d1e2l3i4-...",
    "name": "Express Delivery",
    "contact_person": "Ali Ahmed",
    "phone": "+971501234567",
    "email": "ali@express.com",
    "address": "Warehouse 5, Industrial Area",
    "is_active": true,
    "total_sales_count": 48
  }
}
```

---

## Create Delivery Company

`POST /api/delivery-companies`

### Request Body

```json
{
  "name": "Quick Courier",
  "contact_person": "Mohammed",
  "phone": "+971509876543",
  "email": "info@quickcourier.com",
  "address": "Unit 12, Logistics Park"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `contact_person` | nullable, string, max:255 |
| `phone` | required, string, max:20 |
| `email` | nullable, email, max:255 |
| `address` | nullable, string, max:500 |

### Success Response `201`

```json
{
  "success": true,
  "message": "Delivery company created successfully.",
  "data": {
    "id": 3,
    "uuid": "n1e2w3c4-...",
    "name": "Quick Courier",
    "phone": "+971509876543"
  }
}
```

---

## Update Delivery Company

`PUT /api/delivery-companies/{uuid}`

### Request Body

Same fields as create. All fields are optional for update.

### Success Response `200`

```json
{
  "success": true,
  "message": "Delivery company updated successfully."
}
```

---

## Toggle Active Status

`POST /api/delivery-companies/{uuid}/toggle-active`

Activates or deactivates a delivery company.

### Success Response `200`

```json
{
  "success": true,
  "message": "Delivery company deactivated.",
  "data": { "is_active": false }
}
```

---

## Delete Delivery Company

`DELETE /api/delivery-companies/{uuid}`

Only allowed if the company has no associated sales.

### Success Response `200`

```json
{
  "success": true,
  "message": "Delivery company deleted."
}
```

### Error Response `422`

```json
{
  "success": false,
  "message": "Cannot delete delivery company with existing sales."
}
```

---

## Flutter Implementation Notes

- Show as a simple list with add/edit/toggle functionality.
- Active companies appear in the sale creation delivery dropdown.
- Allow inline creation from the sale screen (quick add).
- Show a confirmation dialog before deactivating a company.
