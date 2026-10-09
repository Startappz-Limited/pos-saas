# 10. Enums Reference

All enum values used across the Flutter POS API.

---

## SaleStatus

| Value | Label | Color |
|-------|-------|-------|
| `draft` | Draft | gray |
| `pending` | Pending | yellow |
| `completed` | Completed | green |
| `voided` | Voided | red |
| `refunded` | Refunded | blue |

---

## SaleType

| Value | Label | Price Field Used |
|-------|-------|-----------------|
| `retail` | Retail | `selling_price` |
| `wholesale` | Wholesale | `wholesale_price` |

---

## PaymentMethod

| Value | Label |
|-------|-------|
| `cash` | Cash |
| `card` | Card |
| `bank_transfer` | Bank Transfer |
| `cheque` | Cheque |
| `mobile_money` | Mobile Money |
| `credit` | Credit/On Account |
| `other` | Other |

---

## PaymentStatus

| Value | Label | Color |
|-------|-------|-------|
| `unpaid` | Unpaid | red |
| `partial` | Partially Paid | yellow |
| `paid` | Paid | green |
| `refunded` | Refunded | blue |

---

## ProductStatus

| Value | Label | Color |
|-------|-------|-------|
| `active` | Active | green |
| `inactive` | Inactive | gray |
| `out_of_stock` | Out of Stock | yellow |
| `discontinued` | Discontinued | red |

---

## EcommerceOrderStatus

| Value | Label | Color | Can Convert | Can Change Status |
|-------|-------|-------|-------------|-------------------|
| `pending` | Pending | yellow | Yes | Yes |
| `processing` | Processing | blue | Yes | Yes |
| `on-hold` | On Hold | gray | Yes | Yes |
| `completed` | Completed | green | Yes | Yes |
| `cancelled` | Cancelled | red | No | No |
| `refunded` | Refunded | dark | No | No |
| `failed` | Failed | red | No | No |

---

## PurchaseOrderStatus

| Value | Label | Can Edit | Can Approve |
|-------|-------|----------|-------------|
| `draft` | Draft | Yes | Yes |
| `pending` | Pending Approval | Yes | Yes |
| `approved` | Approved | No | No |
| `ordered` | Ordered | No | No |
| `partially_received` | Partially Received | No | No |
| `received` | Received | No | No |
| `cancelled` | Cancelled | No | No |

---

## UserStatus

| Value | Label |
|-------|-------|
| `active` | Active |
| `inactive` | Inactive |
| `suspended` | Suspended |

---

## ShopStatus

| Value | Label |
|-------|-------|
| `active` | Active |
| `inactive` | Inactive |
| `suspended` | Suspended |

---

## CategoryStatus

| Value | Label |
|-------|-------|
| `active` | Active |
| `inactive` | Inactive |

---

## CustomerType

| Value | Label |
|-------|-------|
| `retail` | Retail |
| `wholesale` | Wholesale |

---

## AlertType (Calendar Events)

| Value | Label | Description |
|-------|-------|-------------|
| `order_reminder` | Order Reminder | Scheduled follow-up for an order |
| `order_note` | Order Note | Note added to an order |
| `order_status_changed` | Order Status Changed | Automatic status change event |

---

## AlertSeverity

| Value | Label |
|-------|-------|
| `low` | Low |
| `medium` | Medium |
| `high` | High |
| `critical` | Critical |

---

## ReportType (Reference)

### Sales Reports
- `sales_summary` — Sales Summary
- `sales_by_product` — Sales by Product
- `sales_by_category` — Sales by Category
- `sales_by_customer` — Sales by Customer
- `sales_by_staff` — Sales by Staff
- `sales_trend` — Sales Trend

### Financial Reports
- `profit_loss` — Profit & Loss
- `expense_summary` — Expense Summary
- `cash_flow` — Cash Flow

### Inventory Reports
- `inventory_valuation` — Inventory Valuation
- `stock_movement` — Stock Movement
- `low_stock` — Low Stock
