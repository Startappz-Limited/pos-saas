# 12. Expenses

Manage business expenses including tracking, approval workflows, and payment status.

---

## List Expenses

`GET /api/expenses`

Returns paginated expenses for the user's shop.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | Filter by status: `draft`, `pending`, `approved`, `rejected`, `paid`, `cancelled` |
| `category_id` | int | Filter by category ID |
| `start_date` | date | Filter from date (YYYY-MM-DD) |
| `end_date` | date | Filter to date (YYYY-MM-DD) |
| `date` | date | Filter by specific date (YYYY-MM-DD) |
| `is_paid` | bool | Filter by payment status |
| `search` | string | Search in title, expense number, description |
| `per_page` | int | Items per page (default: 20) |
| `page` | int | Page number |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "a1b2c3d4-...",
      "expense_number": "EXP-ABCD1234",
      "title": "Office Supplies",
      "description": "Printer paper and ink cartridges",
      "amount": "2500.00",
      "currency": "KES",
      "expense_date": "2026-03-15",
      "due_date": "2026-03-30",
      "status": "approved",
      "is_paid": false,
      "payment_method": null,
      "reference_number": null,
      "is_tax_deductible": true,
      "tax_amount": "400.00",
      "is_recurring": false,
      "notes": null,
      "category": {
        "id": 3,
        "name": "Office Supplies",
        "color": "#3498db",
        "icon": "bi-file-earmark"
      },
      "vendor": {
        "id": 5,
        "name": "ABC Suppliers Ltd"
      },
      "creator": {
        "id": 1,
        "name": "John Doe"
      },
      "approver": {
        "id": 2,
        "name": "Jane Manager"
      },
      "created_at": "2026-03-15T10:00:00.000000Z"
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

## Create Expense

`POST /api/expenses`

Create a new expense (starts in `draft` status).

### Request Body

```json
{
  "category_id": 3,
  "vendor_id": 5,
  "title": "Office Supplies",
  "description": "Printer paper and ink cartridges",
  "amount": 2500.00,
  "currency": "KES",
  "expense_date": "2026-03-15",
  "due_date": "2026-03-30",
  "payment_method": "cash",
  "reference_number": "INV-12345",
  "is_tax_deductible": true,
  "tax_amount": 400.00,
  "is_recurring": false,
  "recurrence_frequency": null,
  "recurrence_end_date": null,
  "settle_from_register": false,
  "notes": "Monthly office supplies order"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `category_id` | required, must exist |
| `vendor_id` | optional, must exist if provided |
| `title` | required, max 255 chars |
| `description` | optional, max 1000 chars |
| `amount` | required, numeric, min 0.01 |
| `currency` | optional, 3 chars (default: KES) |
| `expense_date` | required, valid date |
| `due_date` | optional, must be after expense_date |
| `payment_method` | optional: `cash`, `card`, `bank_transfer`, `mobile_money`, `cheque` |
| `reference_number` | optional, max 255 chars |
| `is_tax_deductible` | optional, boolean |
| `tax_amount` | optional, numeric, min 0 |
| `is_recurring` | optional, boolean |
| `recurrence_frequency` | optional: `daily`, `weekly`, `monthly`, `quarterly`, `yearly` |
| `recurrence_end_date` | optional, must be after expense_date |
| `settle_from_register` | optional, boolean — settle from active register's expense balance |
| `notes` | optional, max 1000 chars |

### Success Response `201`

```json
{
  "success": true,
  "message": "Expense created successfully.",
  "data": {
    "id": 1,
    "uuid": "a1b2c3d4-...",
    "expense_number": "EXP-ABCD1234",
    "title": "Office Supplies",
    "status": "draft",
    "amount": "2500.00",
    "category": { ... },
    "vendor": { ... }
  }
}
```

---

## Show Expense

`GET /api/expenses/{uuid}`

Returns full expense details.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "a1b2c3d4-...",
    "expense_number": "EXP-ABCD1234",
    "title": "Office Supplies",
    "description": "Printer paper and ink cartridges",
    "amount": "2500.00",
    "currency": "KES",
    "expense_date": "2026-03-15",
    "due_date": "2026-03-30",
    "paid_date": null,
    "status": "approved",
    "rejection_reason": null,
    "is_paid": false,
    "payment_method": null,
    "reference_number": null,
    "is_tax_deductible": true,
    "tax_amount": "400.00",
    "is_recurring": false,
    "recurrence_frequency": null,
    "recurrence_end_date": null,
    "notes": null,
    "submitted_at": "2026-03-15T10:30:00.000000Z",
    "approved_at": "2026-03-15T11:00:00.000000Z",
    "category": {
      "id": 3,
      "uuid": "...",
      "name": "Office Supplies",
      "type": "supplies",
      "color": "#3498db",
      "icon": "bi-file-earmark",
      "requires_approval": true,
      "approval_threshold": "1000.00"
    },
    "vendor": {
      "id": 5,
      "name": "ABC Suppliers Ltd",
      "email": "sales@abc.co.ke",
      "phone": "+254700123456"
    },
    "creator": {
      "id": 1,
      "name": "John Doe"
    },
    "approver": {
      "id": 2,
      "name": "Jane Manager"
    },
    "parent_expense": null,
    "child_expenses": [],
    "created_at": "2026-03-15T10:00:00.000000Z",
    "updated_at": "2026-03-15T11:00:00.000000Z"
  }
}
```

---

## Update Expense

`PUT /api/expenses/{uuid}`

Update an expense. Only `draft` or `rejected` expenses can be edited.

### Request Body

```json
{
  "title": "Updated Office Supplies",
  "amount": 2800.00,
  "notes": "Added extra items"
}
```

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense updated successfully.",
  "data": { ... }
}
```

### Error Response `422`

```json
{
  "success": false,
  "message": "This expense cannot be edited in its current status."
}
```

---

## Delete Expense

`DELETE /api/expenses/{uuid}`

Delete an expense. Only `draft` or `rejected` expenses can be deleted.

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense deleted successfully."
}
```

---

## Submit Expense for Approval

`POST /api/expenses/{uuid}/submit`

Submit a draft expense for approval.

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense submitted for approval.",
  "data": {
    "uuid": "a1b2c3d4-...",
    "status": "pending",
    "submitted_at": "2026-03-15T10:30:00.000000Z"
  }
}
```

### Error Response `422`

```json
{
  "success": false,
  "message": "Only draft expenses can be submitted."
}
```

---

## Approve Expense

`POST /api/expenses/{uuid}/approve`

Approve a pending expense. Requires `expenses.approve` permission.

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense approved successfully.",
  "data": {
    "uuid": "a1b2c3d4-...",
    "status": "approved",
    "approved_at": "2026-03-15T11:00:00.000000Z",
    "approver": {
      "id": 2,
      "name": "Jane Manager"
    }
  }
}
```

---

## Reject Expense

`POST /api/expenses/{uuid}/reject`

Reject a pending expense. Requires `expenses.approve` permission.

### Request Body

```json
{
  "reason": "Amount exceeds budget. Please reduce or split into multiple expenses."
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `reason` | required, max 500 chars |

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense rejected.",
  "data": {
    "uuid": "a1b2c3d4-...",
    "status": "rejected",
    "rejection_reason": "Amount exceeds budget. Please reduce or split into multiple expenses."
  }
}
```

---

## Mark Expense as Paid

`POST /api/expenses/{uuid}/mark-paid`

Mark an approved expense as paid.

### Request Body

```json
{
  "payment_method": "bank_transfer",
  "reference_number": "TXN-789456"
}
```

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense marked as paid.",
  "data": {
    "uuid": "a1b2c3d4-...",
    "status": "paid",
    "is_paid": true,
    "paid_date": "2026-03-20",
    "payment_method": "bank_transfer",
    "reference_number": "TXN-789456"
  }
}
```

---

## Cancel Expense

`POST /api/expenses/{uuid}/cancel`

Cancel an expense. Cannot cancel paid or already cancelled expenses.

### Success Response `200`

```json
{
  "success": true,
  "message": "Expense cancelled.",
  "data": {
    "uuid": "a1b2c3d4-...",
    "status": "cancelled"
  }
}
```

---

## Expense Summary

`GET /api/expenses/summary`

Returns expense summary statistics for a date range.

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `start_date` | date | First of month | Start date |
| `end_date` | date | End of month | End date |

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2026-03-01",
      "end_date": "2026-03-31"
    },
    "totals": {
      "total_expenses": "125000.00",
      "paid": "85000.00",
      "unpaid": "40000.00",
      "pending_approval_count": 5
    },
    "by_category": [
      {
        "category": {
          "id": 1,
          "name": "Utilities",
          "color": "#e74c3c",
          "icon": "bi-lightning"
        },
        "total": "35000.00"
      },
      {
        "category": {
          "id": 3,
          "name": "Office Supplies",
          "color": "#3498db",
          "icon": "bi-file-earmark"
        },
        "total": "15000.00"
      }
    ],
    "by_status": [
      { "status": "paid", "count": 12, "total": "85000.00" },
      { "status": "approved", "count": 3, "total": "25000.00" },
      { "status": "pending", "count": 5, "total": "15000.00" }
    ]
  }
}
```

---

## List Expense Categories

`GET /api/expense-categories`

Returns all active expense categories.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `type` | string | Filter by type (see Expense Category Types) |
| `root_only` | bool | Return only root categories (no parent) |
| `search` | string | Search in name or code |

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uuid": "c1a2t3e4-...",
      "name": "Utilities",
      "slug": "utilities",
      "code": "CAT-UTIL01",
      "description": "Electricity, water, internet bills",
      "type": "utilities",
      "is_operational": true,
      "is_tax_deductible": true,
      "monthly_budget": "50000.00",
      "yearly_budget": "600000.00",
      "requires_approval": true,
      "approval_threshold": "10000.00",
      "icon": "bi-lightning",
      "color": "#e74c3c",
      "sort_order": 1,
      "status": "active",
      "parent": null,
      "children": []
    },
    {
      "id": 2,
      "uuid": "c2a3t4e5-...",
      "name": "Rent",
      "slug": "rent",
      "code": "CAT-RENT01",
      "type": "rent",
      "monthly_budget": "80000.00",
      "requires_approval": false,
      "icon": "bi-building",
      "color": "#9b59b6",
      "parent": null,
      "children": []
    }
  ]
}
```

---

## Show Expense Category

`GET /api/expense-categories/{uuid}`

Returns category details with budget usage information.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "c1a2t3e4-...",
    "name": "Utilities",
    "monthly_budget": "50000.00",
    "yearly_budget": "600000.00",
    "monthly_usage_percent": 65.5,
    "yearly_usage_percent": 45.2,
    "parent": null,
    "children": [
      {
        "id": 10,
        "name": "Electricity",
        "code": "CAT-ELEC01"
      }
    ]
  }
}
```

---

## Expense Status Flow

```
DRAFT → (submit) → PENDING → (approve) → APPROVED → (mark-paid) → PAID
                           ↘ (reject) → REJECTED → (edit & resubmit) → PENDING
                                                                      
Any non-PAID status → (cancel) → CANCELLED
```

### Status Descriptions

| Status | Description | Can Edit | Can Delete |
|--------|-------------|----------|------------|
| `draft` | Initial state, not yet submitted | ✅ | ✅ |
| `pending` | Awaiting approval | ❌ | ❌ |
| `approved` | Approved, awaiting payment | ❌ | ❌ |
| `rejected` | Rejected by approver | ✅ | ✅ |
| `paid` | Paid and completed | ❌ | ❌ |
| `cancelled` | Cancelled | ❌ | ❌ |

---

## Expense Category Types

| Type | Label |
|------|-------|
| `operational` | Operational |
| `administrative` | Administrative |
| `marketing` | Marketing & Advertising |
| `payroll` | Payroll & Benefits |
| `utilities` | Utilities |
| `rent` | Rent & Lease |
| `supplies` | Supplies |
| `maintenance` | Maintenance & Repairs |
| `transport` | Transport & Logistics |
| `insurance` | Insurance |
| `taxes` | Taxes & Licenses |
| `miscellaneous` | Miscellaneous |

---

## Payment Methods

| Value | Label |
|-------|-------|
| `cash` | Cash |
| `card` | Card |
| `bank_transfer` | Bank Transfer |
| `mobile_money` | Mobile Money (M-Pesa, etc.) |
| `cheque` | Cheque |

---

## Flutter Implementation Notes

- Show expense summary dashboard with totals and category breakdown
- Color-code expenses by status (draft=grey, pending=yellow, approved=green, rejected=red, paid=blue, cancelled=dark)
- Display category icon and color in expense list items
- Show approval workflow actions based on status and user permissions
- When marking paid, prompt for payment method and reference number
- For recurring expenses, display recurrence badge and frequency
- Show budget usage progress bars for categories with budgets
- When `settle_from_register` is true, the expense is automatically marked as `paid` with `payment_method: cash`, deducted from the active register's expense balance
- If there is no active register or insufficient expense balance, a `422` error is returned
- Enable expense filtering by date range, status, and category
- Display rejection reason prominently when status is rejected
- Use `expenses.approve` permission to show approve/reject buttons
