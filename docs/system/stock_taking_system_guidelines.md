# Stock Taking & Sales Management System
## Laravel-Based Multi-Shop Platform

### 1. Overview
This system is a centralized stock, sales, and expense management platform supporting multiple shops under one roof (e.g. Kids Store, Fitness Store, Kitchen Shop). Each stock intake is tracked individually with a unique stock ID, enabling accurate cost tracking, profit calculation, and advanced reporting.

---

### 2. Core Design Principles
- Every stock intake is unique
- Costs are tracked per stock batch
- Sales captured at item level
- Full audit trail
- Multi-shop, single platform

---

### 3. Shops Management
Each shop operates independently under one platform.

**Fields**
- shop_id
- name
- location
- status

---

### 4. Products & Variations
**Products**
- product_id
- name
- category
- shop_id
- description
- status

**Variations**
- variation_id
- product_id
- attributes (JSON)
- sku

---

### 5. Stock Intake (Unique Stock ID)
Each delivery from a supplier creates a new stock batch.

**Stock Batch Fields**
- stock_id (UUID)
- product_id
- variation_id
- shop_id
- supplier
- purchase_quantity
- remaining_quantity
- purchase_price
- shipping_cost
- other_costs
- total_cost
- cost_per_unit
- date_received
- created_by

---

### 6. Pricing
- Retail Price
- Wholesale Price

**Fields**
- product_id
- variation_id
- retail_price
- wholesale_price
- effective_date
- status

---

### 7. Sales Management
Each sale is captured per item.

**Sale Fields**
- sale_id
- date_time
- shop_id
- user_id
- source_of_sale
- customer_type
- customer_name
- customer_phone
- customer_email
- delivery_location

---

### 8. Sale Line Items
- sale_id
- stock_id
- product_id
- variation_id
- quantity
- selling_price
- price_type
- subtotal

---

### 9. Payments
- payment_mode
- payment_status
- commission_percentage
- commission_cap
- commission_amount
- net_received

---

### 10. Credit Sales
- credit_id
- sale_id
- customer
- amount_owed
- amount_paid
- balance
- due_date
- status

---

### 11. Expenses
**Categories**
- Transport
- Packaging
- Shipping
- Delivery
- Advertising
- Other

**Fields**
- expense_id
- shop_id
- category
- description
- amount
- advertising_platform
- expense_date
- related_sale_id

---

### 12. Inventory Alerts & Analytics
- Low stock alerts
- Fast / slow moving items
- Sales trends by time, day, month

---

### 13. Reports
- Inventory reports
- Sales reports
- Expense reports
- Financial summaries
- Advertising ROI

---

### 14. Audit & Documentation
- Stock intake notes
- Sales receipts
- Credit notes
- Expense vouchers
- Full audit logs

---

### 15. Multi-User Roles
- Super Admin
- Manager
- Sales
- Accountant
- Auditor

---

### 16. Recommended Enhancements
- Returns & refunds
- FIFO costing
- Stock adjustments
- Backups
- API-ready design
