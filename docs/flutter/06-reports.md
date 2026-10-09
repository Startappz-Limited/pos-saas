# 6. Reports (Daily)

Reports are capped per day — the mobile app focuses on daily sales and register summaries.

---

## Daily Sales Report

`GET /api/reports/daily-sales`

Returns sales summary for a specific date.

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `date` | date (YYYY-MM-DD) | today | Date to report on |

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "date": "2026-03-18",
    "statistics": {
      "total_sales": "4580.00",
      "total_cost": "2150.00",
      "total_profit": "2430.00",
      "total_transactions": 22,
      "profit_margin": "53.06"
    },
    "sales_by_payment_method": {
      "cash": { "count": 12, "total": "2100.00" },
      "card": { "count": 7, "total": "1580.00" },
      "mobile_money": { "count": 2, "total": "650.00" },
      "credit": { "count": 1, "total": "250.00" }
    },
    "sales": [
      {
        "uuid": "...",
        "invoice_number": "INV-ABCD1234",
        "total_amount": "220.00",
        "total_profit": "145.00",
        "payment_method": "cash",
        "payment_status": "paid",
        "status": "completed",
        "customer": { "name": "Jane Smith" },
        "completed_at": "2026-03-18T10:30:00.000000Z"
      }
    ]
  }
}
```

---

## Daily Register Report

`GET /api/reports/daily-register`

Returns register summary for a specific date.

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `date` | date (YYYY-MM-DD) | today | Date to report on |

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "date": "2026-03-18",
    "register": {
      "register_number": "REG-AB12CD",
      "opening_balance": "500.00",
      "closing_balance": "1700.00",
      "expected_balance": "1700.00",
      "variance": "0.00",
      "total_sales": "4580.00",
      "total_cash_sales": "2100.00",
      "total_card_sales": "1580.00",
      "total_credit_sales": "250.00",
      "total_mobile_money_sales": "650.00",
      "total_bank_transfer_sales": "0.00",
      "total_cheque_sales": "0.00",
      "transaction_count": 22,
      "status": "closed",
      "opened_at": "2026-03-18T08:00:00.000000Z",
      "closed_at": "2026-03-18T18:00:00.000000Z",
      "opened_by": "John Doe",
      "closed_by": "John Doe"
    }
  }
}
```

---

## Top Products Report (Daily)

`GET /api/reports/top-products`

Returns top-selling products for a specific date.

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `date` | date (YYYY-MM-DD) | today | Date to report on |
| `limit` | int | 10 | Number of products to return |

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "date": "2026-03-18",
    "products": [
      {
        "product_id": 1,
        "product_name": "Protein Powder 1kg",
        "sku": "PP-001",
        "total_quantity": 15,
        "total_revenue": "675.00",
        "total_profit": "300.00"
      },
      {
        "product_id": 5,
        "product_name": "Yoga Mat",
        "sku": "YM-001",
        "total_quantity": 8,
        "total_revenue": "960.00",
        "total_profit": "480.00"
      }
    ]
  }
}
```

---

## Pending Payments Report

`GET /api/reports/pending-payments`

Returns COD/credit sales with outstanding balances for the current day.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "total_pending": "1250.00",
    "count": 5,
    "sales": [
      {
        "uuid": "...",
        "invoice_number": "INV-WXYZ5678",
        "total_amount": "350.00",
        "paid_amount": "0.00",
        "balance_due": "350.00",
        "payment_status": "unpaid",
        "is_cod": true,
        "customer": { "name": "Ahmed Ali" },
        "created_at": "2026-03-18T11:00:00.000000Z"
      }
    ]
  }
}
```

---

## Flutter Implementation Notes

- Show the daily report as the default "Reports" tab content.
- Allow date navigation (previous/next day arrows) to view different dates.
- Display key metrics (total sales, profit, transaction count) as cards at the top.
- Show a pie chart or bar chart for sales by payment method.
- List top products in a ranked list.
- Show pending payments with a "Collect Payment" button linking to the sale detail.
- Auto-refresh report data when returning from creating a sale or collecting a payment.
