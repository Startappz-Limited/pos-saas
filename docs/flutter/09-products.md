# 9. Manage Products

View, create, update, and manage product catalog from the mobile app.

---

## List Products

`GET /api/products`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by name, SKU, barcode |
| `status` | string | `active`, `inactive`, `out_of_stock`, `discontinued` |
| `category_id` | int | Filter by category |
| `shop_id` | int | Filter by shop |
| `per_page` | int | Items per page (default: 15) |
| `page` | int | Page number |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "p1r2o3d4-...",
      "name": "Protein Powder 1kg",
      "slug": "protein-powder-1kg",
      "sku": "PP-001",
      "barcode": "1234567890123",
      "description": "High-quality whey protein powder",
      "category": { "id": 1, "name": "Supplements" },
      "supplier": { "id": 1, "name": "Wholesale Supplements Ltd" },
      "cost_price": "25.00",
      "selling_price": "45.00",
      "wholesale_price": "35.00",
      "stock_quantity": 50,
      "reorder_level": 10,
      "track_stock": true,
      "has_variations": true,
      "image": "/storage/products/protein.jpg",
      "unit": "piece",
      "status": "active",
      "variations_count": 3
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 68
  }
}
```

---

## Show Product

`GET /api/products/{uuid}`

Returns full product details with variations and shop pricing.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "p1r2o3d4-...",
    "name": "Protein Powder 1kg",
    "slug": "protein-powder-1kg",
    "description": "High-quality whey protein powder",
    "sku": "PP-001",
    "barcode": "1234567890123",
    "category": { "id": 1, "name": "Supplements" },
    "supplier": { "id": 1, "name": "Wholesale Supplements Ltd" },
    "cost_price": "25.00",
    "selling_price": "45.00",
    "wholesale_price": "35.00",
    "stock_quantity": 50,
    "reorder_level": 10,
    "track_stock": true,
    "has_variations": true,
    "image": "/storage/products/protein.jpg",
    "images": ["/storage/products/protein-1.jpg", "/storage/products/protein-2.jpg"],
    "unit": "piece",
    "status": "active",
    "notes": "Best seller",
    "variations": [
      {
        "id": 1,
        "uuid": "v1a2r3i4-...",
        "name": "Chocolate",
        "sku": "PP-001-CHOC",
        "barcode": "1234567890124",
        "attributes": { "flavor": "Chocolate" },
        "cost_price": "25.00",
        "selling_price": "45.00",
        "wholesale_price": "35.00",
        "stock_quantity": 20,
        "image": "/storage/products/protein-choc.jpg",
        "status": "active"
      },
      {
        "id": 2,
        "uuid": "v5a6r7i8-...",
        "name": "Vanilla",
        "sku": "PP-001-VAN",
        "barcode": "1234567890125",
        "attributes": { "flavor": "Vanilla" },
        "cost_price": "25.00",
        "selling_price": "45.00",
        "wholesale_price": "35.00",
        "stock_quantity": 30,
        "image": null,
        "status": "active"
      }
    ],
    "shops": [
      {
        "id": 1,
        "name": "Main Store",
        "pivot": {
          "stock_quantity": 50,
          "reorder_level": 10,
          "cost_price": "25.00",
          "selling_price": "45.00",
          "wholesale_price": "35.00",
          "is_active": true
        }
      }
    ],
    "created_by": { "name": "Admin" },
    "updated_by": { "name": "Admin" },
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-03-15T10:00:00.000000Z"
  }
}
```

---

## Create Product

`POST /api/products`

### Request Body

```json
{
  "name": "Resistance Bands Set",
  "sku": "RB-001",
  "barcode": "9876543210123",
  "description": "Set of 5 resistance bands with varying resistance levels",
  "category_id": 2,
  "supplier_id": 1,
  "cost_price": 15.00,
  "selling_price": 35.00,
  "wholesale_price": 25.00,
  "stock_quantity": 100,
  "reorder_level": 20,
  "track_stock": true,
  "has_variations": false,
  "unit": "set",
  "status": "active",
  "notes": null,
  "variations": []
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `sku` | required, string, max:100, unique |
| `barcode` | nullable, string, max:100 |
| `description` | nullable, string |
| `category_id` | required, exists:categories,id |
| `supplier_id` | nullable, exists:suppliers,id |
| `cost_price` | required, numeric, min:0 |
| `selling_price` | required, numeric, min:0 |
| `wholesale_price` | nullable, numeric, min:0 |
| `stock_quantity` | required, integer, min:0 |
| `reorder_level` | nullable, integer, min:0 |
| `track_stock` | boolean |
| `has_variations` | boolean |
| `unit` | nullable, string, max:50 |
| `status` | required, in: `active`, `inactive`, `out_of_stock`, `discontinued` |
| `notes` | nullable, string |
| `variations` | array (if `has_variations` is true) |
| `variations.*.name` | required, string |
| `variations.*.sku` | required, string, unique |
| `variations.*.barcode` | nullable, string |
| `variations.*.cost_price` | required, numeric, min:0 |
| `variations.*.selling_price` | required, numeric, min:0 |
| `variations.*.wholesale_price` | nullable, numeric, min:0 |
| `variations.*.stock_quantity` | required, integer, min:0 |
| `variations.*.attributes` | nullable, array |

### Success Response `201`

```json
{
  "success": true,
  "message": "Product created successfully.",
  "data": {
    "uuid": "n1e2w3p4-...",
    "name": "Resistance Bands Set",
    "sku": "RB-001"
  }
}
```

---

## Update Product

`PUT /api/products/{uuid}`

### Request Body

Same fields as create. All fields optional.

### Success Response `200`

```json
{
  "success": true,
  "message": "Product updated successfully."
}
```

---

## Activate Product

`POST /api/products/{uuid}/activate`

### Success Response `200`

```json
{
  "success": true,
  "message": "Product activated successfully."
}
```

---

## Deactivate Product

`POST /api/products/{uuid}/deactivate`

### Success Response `200`

```json
{
  "success": true,
  "message": "Product deactivated successfully."
}
```

---

## Delete Product

`DELETE /api/products/{uuid}`

Soft-deletes the product.

### Success Response `200`

```json
{
  "success": true,
  "message": "Product deleted successfully."
}
```

---

## Low Stock Products

`GET /api/products/low-stock`

Returns products where `stock_quantity <= reorder_level`.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `per_page` | int | Items per page |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "uuid": "...",
      "name": "Protein Powder 1kg",
      "sku": "PP-001",
      "stock_quantity": 8,
      "reorder_level": 10,
      "selling_price": "45.00",
      "status": "active"
    }
  ]
}
```

---

## Get Categories

`GET /api/categories`

Returns categories for the product filter/selector.

```json
{
  "data": [
    {
      "id": 1,
      "name": "Supplements",
      "parent_id": null,
      "children_count": 3,
      "status": "active"
    },
    {
      "id": 2,
      "name": "Equipment",
      "parent_id": null,
      "children_count": 5,
      "status": "active"
    }
  ]
}
```

---

## Product Statuses

| Status | Color | Description |
|--------|-------|-------------|
| `active` | Green | Available for sale |
| `inactive` | Gray | Hidden from POS |
| `out_of_stock` | Yellow/Orange | No stock available |
| `discontinued` | Red | No longer sold |

---

## Flutter Implementation Notes

- Products list should support search, category filter, and status filter.
- Use barcode scanning to quickly look up products (search by `barcode` or `sku`).
- For products with variations, display variations as a sub-list or expandable section.
- Show stock level indicators (green=good, yellow=low, red=out).
- Low stock products should be accessible from a dedicated badge/tab.
- Product images: use `{base_url}{image_path}` to construct the full image URL.
- When creating products with variations, allow dynamically adding variation rows.
- The `shops` array shows per-shop pricing — useful for multi-location setups.
