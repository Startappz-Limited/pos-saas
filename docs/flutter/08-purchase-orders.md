# 8. Request Stock (Purchase Orders)

Create and manage purchase orders to request stock from suppliers.

---

## Lookup Data

### Get Suppliers

`GET /api/suppliers`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by name |
| `status` | string | Filter: `active` |

### Response Item Shape

```json
{
  "id": 1,
  "uuid": "...",
  "name": "Wholesale Supplements Ltd",
  "contact_person": "Sarah",
  "phone": "+1234567890",
  "email": "orders@wholesale.com",
  "status": "active"
}
```

### Get Shops

`GET /api/shops`

```json
{
  "data": [
    { "id": 1, "uuid": "...", "name": "Main Store", "status": "active" }
  ]
}
```

---

## List Purchase Orders

`GET /api/purchase-orders`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by order number |
| `status` | string | See [PurchaseOrderStatus enum](10-enums.md) |
| `supplier_id` | int | Filter by supplier |
| `shop_id` | int | Filter by shop |
| `page` | int | Page number |
| `per_page` | int | Items per page (default: 15) |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "uuid": "p1o2r3d4-...",
      "order_number": "PO-ABCD1234",
      "status": "pending",
      "order_date": "2026-03-18",
      "expected_delivery_date": "2026-03-25",
      "subtotal": "2500.00",
      "tax_amount": "125.00",
      "shipping_cost": "50.00",
      "discount_amount": "0.00",
      "total_amount": "2675.00",
      "supplier": { "id": 1, "name": "Wholesale Supplements Ltd" },
      "shop": { "id": 1, "name": "Main Store" },
      "items_count": 5,
      "created_at": "2026-03-18T09:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 8
  }
}
```

---

## Show Purchase Order

`GET /api/purchase-orders/{uuid}`

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "uuid": "p1o2r3d4-...",
    "order_number": "PO-ABCD1234",
    "status": "pending",
    "order_date": "2026-03-18",
    "expected_delivery_date": "2026-03-25",
    "subtotal": "2500.00",
    "tax_amount": "125.00",
    "shipping_cost": "50.00",
    "discount_amount": "0.00",
    "total_amount": "2675.00",
    "currency": "USD",
    "payment_terms": "Net 30",
    "payment_due_date": "2026-04-17",
    "payment_status": "unpaid",
    "notes": "Urgent order for restocking",
    "supplier": {
      "id": 1,
      "name": "Wholesale Supplements Ltd",
      "phone": "+1234567890",
      "email": "orders@wholesale.com"
    },
    "shop": { "id": 1, "name": "Main Store" },
    "items": [
      {
        "id": 1,
        "product": { "id": 1, "name": "Protein Powder 1kg", "sku": "PP-001" },
        "product_variation": null,
        "quantity_ordered": 50,
        "unit_cost": "25.00",
        "tax_rate": "5.00",
        "discount_percent": "0.00",
        "line_total": "1312.50",
        "notes": null
      },
      {
        "id": 2,
        "product": { "id": 5, "name": "Yoga Mat", "sku": "YM-001" },
        "product_variation": null,
        "quantity_ordered": 20,
        "unit_cost": "40.00",
        "tax_rate": "5.00",
        "discount_percent": "0.00",
        "line_total": "840.00",
        "notes": "Blue color preferred"
      }
    ],
    "created_by": { "name": "John Doe" },
    "approved_by": null,
    "approved_at": null,
    "created_at": "2026-03-18T09:00:00.000000Z"
  }
}
```

---

## Create Purchase Order

`POST /api/purchase-orders`

### Request Body

```json
{
  "supplier_id": 1,
  "shop_id": 1,
  "order_date": "2026-03-18",
  "expected_delivery_date": "2026-03-25",
  "payment_terms": "Net 30",
  "payment_due_date": "2026-04-17",
  "shipping_cost": 50.00,
  "discount_amount": 0,
  "tax_amount": 125.00,
  "notes": "Urgent order for restocking",
  "items": [
    {
      "product_id": 1,
      "product_variation_id": null,
      "quantity_ordered": 50,
      "unit_cost": 25.00,
      "tax_rate": 5.00,
      "discount_percent": 0,
      "notes": null
    },
    {
      "product_id": 5,
      "product_variation_id": null,
      "quantity_ordered": 20,
      "unit_cost": 40.00,
      "tax_rate": 5.00,
      "discount_percent": 0,
      "notes": "Blue color preferred"
    }
  ]
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `supplier_id` | required, exists:suppliers,id |
| `shop_id` | required, exists:shops,id |
| `order_date` | required, date |
| `expected_delivery_date` | nullable, date, after:order_date |
| `payment_terms` | nullable, string, max:255 |
| `payment_due_date` | nullable, date |
| `shipping_cost` | nullable, numeric, min:0 |
| `discount_amount` | nullable, numeric, min:0 |
| `tax_amount` | nullable, numeric, min:0 |
| `notes` | nullable, string |
| `terms_and_conditions` | nullable, string |
| `items` | required, array, min:1 |
| `items.*.product_id` | required, exists:products,id |
| `items.*.product_variation_id` | nullable, exists:product_variations,id |
| `items.*.quantity_ordered` | required, numeric, min:0.01 |
| `items.*.unit_cost` | required, numeric, min:0 |
| `items.*.tax_rate` | nullable, numeric, min:0, max:100 |
| `items.*.discount_percent` | nullable, numeric, min:0, max:100 |
| `items.*.notes` | nullable, string |

### Success Response `201`

```json
{
  "success": true,
  "message": "Purchase order created successfully.",
  "data": {
    "uuid": "p1o2r3d4-...",
    "order_number": "PO-ABCD1234"
  }
}
```

---

## Update Purchase Order

`PUT /api/purchase-orders/{uuid}`

Only editable when status is `draft` or `pending`.

### Request Body

Same structure as create.

---

## Approve Purchase Order

`POST /api/purchase-orders/{uuid}/approve`

Marks the PO as approved. Requires appropriate permissions.

### Success Response `200`

```json
{
  "success": true,
  "message": "Purchase order approved successfully."
}
```

---

## Mark as Ordered

`POST /api/purchase-orders/{uuid}/mark-ordered`

Marks the PO as ordered (sent to supplier).

### Success Response `200`

```json
{
  "success": true,
  "message": "Purchase order marked as ordered."
}
```

---

## Cancel Purchase Order

`POST /api/purchase-orders/{uuid}/cancel`

### Success Response `200`

```json
{
  "success": true,
  "message": "Purchase order cancelled."
}
```

---

## Purchase Order Statuses

| Status | Description | Can Edit | Can Approve |
|--------|-------------|----------|-------------|
| `draft` | Just created | Yes | Yes |
| `pending` | Awaiting approval | Yes | Yes |
| `approved` | Approved, ready to order | No | No |
| `ordered` | Sent to supplier | No | No |
| `partially_received` | Some items received | No | No |
| `received` | All items received | No | No |
| `cancelled` | Cancelled | No | No |

---

## Flutter Implementation Notes

- The stock request flow: Create PO → Submit → Approve → Mark Ordered.
- Show current stock levels for each product in the item picker to inform ordering decisions.
- Allow selecting products from a searchable list with current stock and cost price.
- Calculate line totals and order total client-side for preview.
- Status badges should be color-coded (draft=gray, pending=yellow, approved=blue, ordered=green, cancelled=red).
- Only show edit/delete options for `draft` and `pending` orders.
