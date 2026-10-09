# 3. Sales (POS)

The core POS functionality — creating sales, viewing sale history, collecting payments, and managing sale lifecycle.

---

## Lookup Data (Required Before Creating a Sale)

Before presenting the POS screen, fetch these lookup datasets:

### Get Customers

`GET /api/customers`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by name, phone, email |
| `status` | string | Filter: `active` |
| `per_page` | int | Items per page |

### Response Item Shape

```json
{
  "id": 1,
  "uuid": "...",
  "name": "Jane Smith",
  "phone": "+1234567890",
  "email": "jane@example.com",
  "customer_type": "retail",
  "allow_credit": true,
  "credit_limit": "5000.00",
  "credit_balance": "1200.00",
  "status": "active"
}
```

---

### Get Products

`GET /api/products`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by name, SKU, barcode |
| `status` | string | Filter: `active` |
| `category_id` | int | Filter by category |
| `shop_id` | int | Filter by shop |
| `per_page` | int | Items per page |

### Response Item Shape

```json
{
  "id": 1,
  "uuid": "...",
  "name": "Protein Powder 1kg",
  "sku": "PP-001",
  "barcode": "1234567890123",
  "category": { "id": 1, "name": "Supplements" },
  "cost_price": "25.00",
  "selling_price": "45.00",
  "wholesale_price": "35.00",
  "stock_quantity": 50,
  "has_variations": true,
  "image": "/storage/products/protein.jpg",
  "status": "active",
  "variations": [
    {
      "id": 1,
      "uuid": "...",
      "name": "Chocolate",
      "sku": "PP-001-CHOC",
      "barcode": "1234567890124",
      "cost_price": "25.00",
      "selling_price": "45.00",
      "wholesale_price": "35.00",
      "stock_quantity": 20,
      "attributes": { "flavor": "Chocolate" },
      "status": "active"
    },
    {
      "id": 2,
      "uuid": "...",
      "name": "Vanilla",
      "sku": "PP-001-VAN",
      "barcode": "1234567890125",
      "cost_price": "25.00",
      "selling_price": "45.00",
      "wholesale_price": "35.00",
      "stock_quantity": 30,
      "attributes": { "flavor": "Vanilla" },
      "status": "active"
    }
  ]
}
```

---

### Get Sale Sources

`GET /api/sale-sources`

```json
{
  "data": [
    { "id": 1, "name": "Walk-in", "icon": "bi-person", "is_active": true },
    { "id": 2, "name": "Phone Order", "icon": "bi-telephone", "is_active": true },
    { "id": 3, "name": "Website", "icon": "bi-globe", "is_active": true }
  ]
}
```

---

### Get Delivery Companies

`GET /api/delivery-companies`

```json
{
  "data": [
    {
      "id": 1,
      "uuid": "...",
      "name": "Express Delivery",
      "contact_person": "Ali",
      "phone": "+1234567890",
      "is_active": true
    }
  ]
}
```

---

### Get Categories

`GET /api/categories`

```json
{
  "data": [
    { "id": 1, "name": "Supplements", "parent_id": null, "status": "active" },
    { "id": 2, "name": "Equipment", "parent_id": null, "status": "active" }
  ]
}
```

---

## Create Sale

`POST /api/sales`

Creates a new sale within the currently open register.

### Request Body

```json
{
  "customer_id": 1,
  "source_id": 1,
  "delivery_location": "123 Main Street, City",
  "walk_in_customer_name": null,
  "walk_in_customer_email": null,
  "walk_in_customer_phone": null,
  "delivery_company_id": null,
  "items": [
    {
      "product_id": 1,
      "variation_id": 2,
      "quantity": 2,
      "price": 45.00
    },
    {
      "product_id": 5,
      "variation_id": null,
      "quantity": 1,
      "price": 120.00
    }
  ],
  "discount_amount": 10.00,
  "tax_amount": 5.00,
  "delivery_fee": 15.00,
  "packaging_fee": 0,
  "other_expenses": 0,
  "expense_notes": null,
  "payment_method": "cash",
  "is_cod": false,
  "notes": "Customer requested gift wrapping"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `customer_id` | nullable, exists:customers,id |
| `source_id` | required, exists:sale_sources,id |
| `delivery_location` | required, string, max:1000 |
| `walk_in_customer_name` | required_without:customer_id, nullable, string, max:255 |
| `walk_in_customer_email` | nullable, email, max:255 |
| `walk_in_customer_phone` | required_without:customer_id, nullable, string, max:20 |
| `delivery_company_id` | nullable, exists:delivery_companies,id |
| `items` | required, array, min:1 |
| `items.*.product_id` | required, exists:products,id |
| `items.*.variation_id` | nullable, exists:product_variations,id (required if product `has_variations`) |
| `items.*.quantity` | required, integer, min:1, max:10000 |
| `items.*.price` | required, numeric, min:0, max:1000000 |
| `discount_amount` | nullable, numeric, min:0 |
| `tax_amount` | nullable, numeric, min:0 |
| `delivery_fee` | nullable, numeric, min:0 |
| `packaging_fee` | nullable, numeric, min:0 |
| `other_expenses` | nullable, numeric, min:0 |
| `expense_notes` | nullable, string, max:500 |
| `payment_method` | required, in: `cash`, `card`, `bank_transfer`, `mobile_money`, `credit` |
| `is_cod` | nullable, boolean |
| `notes` | nullable, string, max:1000 |

### Success Response `201`

```json
{
  "success": true,
  "message": "Sale created successfully.",
  "data": {
    "uuid": "s1a2l3e4-...",
    "invoice_number": "INV-ABCD1234",
    "subtotal": "210.00",
    "discount_amount": "10.00",
    "tax_amount": "5.00",
    "delivery_fee": "15.00",
    "packaging_fee": "0.00",
    "other_expenses": "0.00",
    "total_amount": "220.00",
    "total_cost": "75.00",
    "total_profit": "145.00",
    "paid_amount": "220.00",
    "balance_due": "0.00",
    "payment_status": "paid",
    "payment_method": "cash",
    "is_cod": false,
    "status": "completed",
    "completed_at": "2026-03-18T10:30:00.000000Z",
    "items": [
      {
        "uuid": "...",
        "product_id": 1,
        "variation_id": 2,
        "quantity": 2,
        "unit_price": "45.00",
        "line_total": "90.00",
        "product": { "name": "Protein Powder 1kg" },
        "variation": { "name": "Vanilla" }
      }
    ],
    "customer": { "id": 1, "name": "Jane Smith" },
    "source": { "id": 1, "name": "Walk-in" }
  }
}
```

### Payment Method Behavior

| Method | Status | Payment Status | Paid Amount |
|--------|--------|---------------|-------------|
| `cash` | completed | paid | total_amount |
| `card` | completed | paid | total_amount |
| `bank_transfer` | completed | paid | total_amount |
| `mobile_money` | completed | paid | total_amount |
| `credit` | completed | unpaid | 0 |
| any + `is_cod=true` | pending | unpaid | 0 |

---

## List Sales

`GET /api/sales`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by invoice number or customer name |
| `status` | string | `draft`, `pending`, `completed`, `voided`, `refunded` |
| `payment_status` | string | `unpaid`, `partial`, `paid`, `refunded` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

### Response

Standard paginated list of sale objects (same structure as create response).

---

## Show Sale

`GET /api/sales/{uuid}`

Returns full sale details with items, payments, and related data.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "uuid": "s1a2l3e4-...",
    "invoice_number": "INV-ABCD1234",
    "subtotal": "210.00",
    "discount_amount": "10.00",
    "tax_amount": "5.00",
    "delivery_fee": "15.00",
    "total_amount": "220.00",
    "paid_amount": "220.00",
    "balance_due": "0.00",
    "payment_status": "paid",
    "payment_method": "cash",
    "is_cod": false,
    "status": "completed",
    "completed_at": "2026-03-18T10:30:00.000000Z",
    "customer": {
      "id": 1,
      "name": "Jane Smith",
      "phone": "+1234567890"
    },
    "source": { "id": 1, "name": "Walk-in" },
    "delivery_company": null,
    "items": [
      {
        "uuid": "...",
        "product": { "id": 1, "name": "Protein Powder 1kg", "sku": "PP-001" },
        "variation": { "id": 2, "name": "Vanilla" },
        "quantity": 2,
        "unit_price": "45.00",
        "line_total": "90.00"
      }
    ],
    "payments": [
      {
        "uuid": "...",
        "payment_number": "PAY-XY1234AB",
        "amount": "220.00",
        "payment_method": "cash",
        "reference": null,
        "paid_at": "2026-03-18T10:30:00.000000Z",
        "receiver": { "name": "John Doe" }
      }
    ],
    "created_by": { "name": "John Doe" },
    "created_at": "2026-03-18T10:30:00.000000Z"
  }
}
```

---

## Complete Sale

`POST /api/sales/{uuid}/complete`

Marks a pending sale as completed.

### Success Response `200`

```json
{
  "success": true,
  "message": "Sale completed successfully."
}
```

---

## Void Sale

`POST /api/sales/{uuid}/void`

Voids a sale. Rate limited to 5 requests per minute.

### Request Body

```json
{
  "void_reason": "Customer changed mind"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `void_reason` | required, string, max:255 |

---

## Collect Payment (COD / Partial)

`POST /api/sales/{uuid}/collect-payment`

Collects payment for COD or partially paid sales. Supports split payments.

### Request Body

```json
{
  "payments": [
    {
      "amount": 150.00,
      "payment_method": "cash",
      "reference": null,
      "notes": null
    },
    {
      "amount": 70.00,
      "payment_method": "card",
      "reference": "TXN-789456",
      "notes": "Visa ending 4242"
    }
  ]
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `payments` | required, array, min:1 |
| `payments.*.amount` | required, numeric, min:0.01 |
| `payments.*.payment_method` | required, in: `cash`, `card`, `bank_transfer`, `mobile_money`, `cheque` |
| `payments.*.reference` | nullable, string, max:255 |
| `payments.*.notes` | nullable, string, max:500 |

### Success Response `200`

```json
{
  "success": true,
  "message": "Payment collected successfully.",
  "data": {
    "paid_amount": "220.00",
    "balance_due": "0.00",
    "payment_status": "paid",
    "status": "completed"
  }
}
```

---

## Get Sale Payments

`GET /api/sales/{uuid}/payments`

Returns all payments for a specific sale.

```json
{
  "data": [
    {
      "uuid": "...",
      "payment_number": "PAY-XY1234AB",
      "amount": "150.00",
      "payment_method": "cash",
      "reference": null,
      "notes": null,
      "paid_at": "2026-03-18T14:00:00.000000Z",
      "receiver": { "name": "John Doe" }
    }
  ]
}
```

---

## Create Sale Source (Inline)

`POST /api/sale-sources`

Creates a new sale source on-the-fly from the POS screen.

### Request Body

```json
{
  "name": "Instagram",
  "description": "Orders from Instagram DMs"
}
```

### Success Response `201`

```json
{
  "success": true,
  "message": "Sale source created successfully.",
  "data": { "id": 4, "name": "Instagram" }
}
```

---

## Create Delivery Company (Inline)

`POST /api/delivery-companies`

Creates a new delivery company on-the-fly.

### Request Body

```json
{
  "name": "Quick Courier",
  "phone": "+1234567890",
  "contact_person": "Ahmed"
}
```

### Success Response `201`

```json
{
  "success": true,
  "message": "Delivery company created successfully.",
  "data": { "id": 3, "name": "Quick Courier", "phone": "+1234567890" }
}
```

---

## Flutter Implementation Notes

- Cache products, customers, sale sources, and delivery companies on first load. Refresh on pull-to-refresh.
- For products with `has_variations: true`, the user must select a variation before adding to cart.
- Use barcode scanning to look up products by `barcode` or `sku` field.
- Calculate `subtotal`, `total_amount` client-side for display, but the server recalculates on submission.
- For `credit` payment: customer must have `allow_credit: true` and sufficient credit limit.
- After creating a sale, update the register's running totals displayed on screen.
- The `is_cod` flag creates a pending sale that requires `collect-payment` later.
- Support split payments: allow multiple payment methods in one collection.
