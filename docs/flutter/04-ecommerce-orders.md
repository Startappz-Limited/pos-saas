# 4. E-commerce Orders (View & Convert)

View orders synced from WooCommerce/Shopify and convert them into local POS sales.

---

## List Orders

`GET /api/ecommerce-orders`

Returns paginated list of e-commerce orders.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by order number, customer name, or email |
| `shop_id` | int | Filter by shop |
| `platform` | string | `woocommerce` or `shopify` |
| `status` | string | See [EcommerceOrderStatus enum](10-enums.md) |
| `conversion` | string | `converted` or `unconverted` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "uuid": "o1r2d3e4-...",
      "order_number": "#1042",
      "platform": "woocommerce",
      "status": "processing",
      "payment_method": "cod",
      "payment_status": "pending",
      "currency": "USD",
      "subtotal": "150.00",
      "discount_total": "10.00",
      "shipping_total": "15.00",
      "tax_total": "5.00",
      "total": "160.00",
      "customer_name": "Ahmed Ali",
      "customer_email": "ahmed@example.com",
      "customer_phone": "+1234567890",
      "shipping_address": {
        "address_1": "123 Main St",
        "city": "Dubai",
        "state": "Dubai",
        "country": "AE"
      },
      "is_converted": false,
      "is_cod": true,
      "can_be_converted": true,
      "platform_created_at": "2026-03-17T14:00:00.000000Z",
      "items_count": 3,
      "shop": { "id": 1, "name": "Main Store" }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 20,
    "total": 35
  }
}
```

---

## Show Order Details

`GET /api/ecommerce-orders/{uuid}`

Returns full order details including items and notes.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "uuid": "o1r2d3e4-...",
    "order_number": "#1042",
    "platform": "woocommerce",
    "status": "processing",
    "payment_method": "cod",
    "payment_status": "pending",
    "currency": "USD",
    "subtotal": "150.00",
    "discount_total": "10.00",
    "shipping_total": "15.00",
    "tax_total": "5.00",
    "total": "160.00",
    "customer_name": "Ahmed Ali",
    "customer_email": "ahmed@example.com",
    "customer_phone": "+1234567890",
    "billing_address": {
      "first_name": "Ahmed",
      "last_name": "Ali",
      "address_1": "123 Main St",
      "city": "Dubai",
      "state": "Dubai",
      "postcode": "00000",
      "country": "AE"
    },
    "shipping_address": {
      "first_name": "Ahmed",
      "last_name": "Ali",
      "address_1": "123 Main St",
      "city": "Dubai",
      "state": "Dubai",
      "postcode": "00000",
      "country": "AE"
    },
    "notes": "Please deliver before 5pm",
    "is_converted": false,
    "is_cod": true,
    "can_be_converted": true,
    "sale": null,
    "converted_by": null,
    "converted_at": null,
    "platform_created_at": "2026-03-17T14:00:00.000000Z",
    "last_synced_at": "2026-03-18T08:00:00.000000Z",
    "items": [
      {
        "id": 1,
        "name": "Protein Powder 1kg - Chocolate",
        "sku": "PP-001-CHOC",
        "quantity": 2,
        "unit_price": "45.00",
        "subtotal": "90.00",
        "total": "90.00",
        "product_id": 1,
        "has_matched_product": true
      },
      {
        "id": 2,
        "name": "Yoga Mat",
        "sku": "YM-001",
        "quantity": 1,
        "unit_price": "60.00",
        "subtotal": "60.00",
        "total": "60.00",
        "product_id": 5,
        "has_matched_product": true
      }
    ],
    "order_notes": [
      {
        "uuid": "...",
        "title": "Customer called",
        "message": "Asked about delivery time",
        "creator": { "name": "John Doe" },
        "created_at": "2026-03-18T09:00:00.000000Z"
      }
    ],
    "reminders": [
      {
        "uuid": "...",
        "title": "Follow up delivery",
        "message": "Call customer to confirm address",
        "scheduled_at": "2026-03-19T10:00:00.000000Z",
        "is_resolved": false,
        "creator": { "name": "John Doe" }
      }
    ]
  }
}
```

---

## Refresh Orders from Platform

`POST /api/ecommerce-orders/refresh`

Triggers a background job to fetch latest orders from the e-commerce platform.

### Request Body

```json
{
  "shop_id": 1
}
```

### Success Response `200`

```json
{
  "success": true,
  "message": "Order refresh has been queued. New orders will appear shortly."
}
```

---

## Update Order Status

`POST /api/ecommerce-orders/{uuid}/status`

Updates order status and syncs back to the e-commerce platform.

### Request Body

```json
{
  "status": "completed",
  "note": "Delivered and payment collected"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `status` | required, in: `pending`, `processing`, `on-hold`, `completed`, `cancelled`, `refunded`, `failed` |
| `note` | nullable, string, max:500 |

### Success Response `200`

```json
{
  "success": true,
  "message": "Order status updated and synced to platform."
}
```

### Warning Response `200` (sync failed)

```json
{
  "success": true,
  "message": "Order status updated locally, but platform sync failed: Connection timed out.",
  "warning": true
}
```

---

## Convert Order to Sale

`POST /api/ecommerce-orders/{uuid}/convert`

Converts an e-commerce order into a local POS sale. Only works for unconverted orders with eligible statuses.

### Request Body

```json
{
  "register_id": 5,
  "source_id": 3,
  "customer_id": null,
  "payment_method": "cash",
  "delivery_company_id": 1,
  "delivery_location": "123 Main St, Dubai",
  "delivery_fee": 15.00,
  "notes": "Converted from website order #1042",
  "items": [
    {
      "product_id": 1,
      "variation_id": 2,
      "quantity": 2,
      "unit_price": 45.00
    },
    {
      "product_id": 5,
      "variation_id": null,
      "quantity": 1,
      "unit_price": 60.00
    }
  ]
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `register_id` | required, exists:cash_registers,id |
| `source_id` | required, exists:sale_sources,id |
| `customer_id` | nullable, exists:customers,id |
| `payment_method` | nullable, string, max:50 |
| `delivery_company_id` | nullable, exists:delivery_companies,id |
| `delivery_location` | nullable, string, max:1000 |
| `delivery_fee` | nullable, numeric, min:0 |
| `notes` | nullable, string, max:2000 |
| `items` | required, array, min:1 |
| `items.*.product_id` | required, exists:products,id |
| `items.*.variation_id` | nullable, exists:product_variations,id |
| `items.*.quantity` | required, integer, min:1 |
| `items.*.unit_price` | required, numeric, min:0 |

### Success Response `201`

```json
{
  "success": true,
  "message": "Order #1042 has been converted to sale INV-ABCD1234.",
  "data": {
    "sale_uuid": "s1a2l3e4-...",
    "invoice_number": "INV-ABCD1234"
  }
}
```

### Error Response `422`

```json
{
  "success": false,
  "message": "This order cannot be converted to a sale."
}
```

---

## Add Note to Order

`POST /api/ecommerce-orders/{uuid}/notes`

### Request Body

```json
{
  "message": "Customer called to change delivery address"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `message` | required, string, max:2000 |

---

## Add Reminder to Order

`POST /api/ecommerce-orders/{uuid}/reminders`

### Request Body

```json
{
  "title": "Follow up delivery",
  "message": "Call customer to confirm address",
  "scheduled_at": "2026-03-19T10:00:00.000000Z"
}
```

---

## Resolve Reminder

`POST /api/ecommerce-orders/{uuid}/reminders/{alert_uuid}/resolve`

Marks a reminder as resolved.

---

## Flutter Implementation Notes

- Show unconverted orders prominently (badge count on the orders tab).
- Color-code orders by status (pending=yellow, processing=blue, completed=green, etc.).
- COD orders (`is_cod: true`) should be highlighted — these need payment collection after delivery.
- The conversion flow:
  1. User taps "Convert to Sale" on an order.
  2. App pre-fills items from the order.
  3. User selects/confirms delivery company, payment method.
  4. App sends `POST /api/ecommerce-orders/{uuid}/convert`.
- After conversion, the order's `is_converted` becomes `true` and links to the sale.
- Failed platform sync should show a warning toast but not block the user.
- Use pull-to-refresh or a refresh button to trigger `POST /api/ecommerce-orders/refresh`.
