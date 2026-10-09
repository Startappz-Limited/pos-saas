# 2. Cash Register (Open / Close)

A cash register must be open before sales can be made. Each shop can have one active (open) register per day.

---

## Get Register Status

`GET /api/cash-registers/status`

Returns the current register state for the user's shop.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "has_active_register": true,
    "register": {
      "id": 5,
      "uuid": "a1b2c3d4-...",
      "register_number": "REG-AB12CD",
      "register_date": "2026-03-18",
      "opening_balance": "500.00",
      "expense_opening_balance": "200.00",
      "total_sales": "2340.00",
      "total_cash_sales": "1200.00",
      "total_card_sales": "890.00",
      "total_credit_sales": "250.00",
      "total_mobile_money_sales": "0.00",
      "total_bank_transfer_sales": "0.00",
      "total_cheque_sales": "0.00",
      "transaction_count": 15,
      "status": "open",
      "opened_at": "2026-03-18T08:00:00.000000Z",
      "opening_notes": null,
      "user": {
        "id": 1,
        "name": "John Doe"
      }
    }
  }
}
```

When no register is open:

```json
{
  "success": true,
  "data": {
    "has_active_register": false,
    "register": null
  }
}
```

---

## List Registers

`GET /api/cash-registers`

Returns paginated register history for the user's shop.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `date` | date | Filter by specific date (YYYY-MM-DD) |
| `status` | string | Filter by `open` or `closed` |
| `page` | int | Page number |
| `per_page` | int | Items per page (default: 20) |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "uuid": "a1b2c3d4-...",
      "register_number": "REG-AB12CD",
      "register_date": "2026-03-18",
      "opening_balance": "500.00",
      "expense_opening_balance": "200.00",
      "expense_balance_used": "150.00",
      "closing_balance": "1700.00",
      "expected_balance": "1700.00",
      "variance": "0.00",
      "total_sales": "2340.00",
      "total_cash_sales": "1200.00",
      "total_card_sales": "890.00",
      "total_credit_sales": "250.00",
      "total_mobile_money_sales": "0.00",
      "total_bank_transfer_sales": "0.00",
      "total_cheque_sales": "0.00",
      "transaction_count": 15,
      "status": "closed",
      "opened_at": "2026-03-18T08:00:00.000000Z",
      "closed_at": "2026-03-18T18:00:00.000000Z",
      "user": { "id": 1, "name": "John Doe" },
      "closed_by": { "id": 1, "name": "John Doe" }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 45
  }
}
```

---

## Open Register

`POST /api/cash-registers/open`

Opens a new cash register for today. Fails if a register is already open.

### Request Body

```json
{
  "opening_balance": 500.00,
  "expense_opening_balance": 200.00,
  "opening_notes": "Starting balance counted and verified"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `opening_balance` | required, numeric, min:0 |
| `expense_opening_balance` | nullable, numeric, min:0 |
| `opening_notes` | nullable, string |

### Success Response `201`

```json
{
  "success": true,
  "message": "Register opened successfully.",
  "data": {
    "id": 6,
    "uuid": "e5f6g7h8-...",
    "register_number": "REG-XY34ZW",
    "register_date": "2026-03-18",
    "opening_balance": "500.00",
    "expense_opening_balance": "200.00",
    "expense_balance_used": "0.00",
    "status": "open",
    "opened_at": "2026-03-18T08:00:00.000000Z"
  }
}
```

### Error Response `422`

```json
{
  "success": false,
  "message": "A register is already open for today."
}
```

---

## Show Register Details

`GET /api/cash-registers/{uuid}`

Returns full register details including linked sales.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 5,
    "uuid": "a1b2c3d4-...",
    "register_number": "REG-AB12CD",
    "register_date": "2026-03-18",
    "opening_balance": "500.00",
    "expense_opening_balance": "200.00",
    "expense_balance_used": "150.00",
    "closing_balance": "1700.00",
    "expected_balance": "1700.00",
    "variance": "0.00",
    "total_sales": "2340.00",
    "total_cash_sales": "1200.00",
    "total_card_sales": "890.00",
    "total_credit_sales": "250.00",
    "total_mobile_money_sales": "0.00",
    "total_bank_transfer_sales": "0.00",
    "total_cheque_sales": "0.00",
    "transaction_count": 15,
    "status": "closed",
    "opened_at": "2026-03-18T08:00:00.000000Z",
    "closed_at": "2026-03-18T18:00:00.000000Z",
    "opening_notes": null,
    "closing_notes": "All counted, no variance",
    "user": { "id": 1, "name": "John Doe" },
    "closed_by": { "id": 1, "name": "John Doe" },
    "sales": [
      {
        "uuid": "s1a2l3e4-...",
        "invoice_number": "INV-ABCD1234",
        "total_amount": "150.00",
        "payment_method": "cash",
        "status": "completed",
        "customer": { "id": 2, "name": "Jane Smith" },
        "created_at": "2026-03-18T09:15:00.000000Z"
      }
    ]
  }
}
```

---

## Close Register

`POST /api/cash-registers/{uuid}/close`

Closes the register. The server calculates expected balance and variance.

### Request Body

```json
{
  "closing_balance": 1700.00,
  "closing_notes": "All counted, no variance"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `closing_balance` | required, numeric, min:0 |
| `closing_notes` | nullable, string |

### Success Response `200`

```json
{
  "success": true,
  "message": "Register closed successfully.",
  "data": {
    "uuid": "a1b2c3d4-...",
    "opening_balance": "500.00",
    "expense_opening_balance": "200.00",
    "expense_balance_used": "150.00",
    "closing_balance": "1700.00",
    "expected_balance": "1700.00",
    "variance": "0.00",
    "total_sales": "2340.00",
    "total_cash_sales": "1200.00",
    "total_card_sales": "890.00",
    "total_credit_sales": "250.00",
    "total_mobile_money_sales": "0.00",
    "total_bank_transfer_sales": "0.00",
    "total_cheque_sales": "0.00",
    "transaction_count": 15,
    "status": "closed",
    "closed_at": "2026-03-18T18:00:00.000000Z"
  }
}
```

### Error Response `422`

```json
{
  "success": false,
  "message": "This register is already closed."
}
```

---

## Flutter Implementation Notes

- On app launch (after auth), call `GET /api/cash-registers/status` to check register state.
- If no register is open, prompt the user to open one before allowing sales.
- Show the register summary (totals, transaction count) on the home screen.
- When closing, display expected balance and let the user input actual counted cash.
- Show the variance (difference) prominently — highlight in red if negative.
- The register auto-closes previous day's open registers when a new one is opened.
