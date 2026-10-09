# Stock Taking & Sales Management System
## Master Implementation Plan

### Project Overview
A Laravel 12 multi-shop stock, sales, and expense management platform with Bootstrap 5 UI (Larkon template).

---

## Development Guidelines Reference

| Category | Guideline |
|----------|-----------|
| **Framework** | Laravel 12 (PHP 8.4) |
| **UI Framework** | Bootstrap 5 (Larkon Admin Template) |
| **Testing** | Pest v4 |
| **Code Style** | Laravel Pint |
| **UI Source** | `./design/src` |
| **UI Docs** | `./design/Documentation` |

### Key Development Rules
1. Use `php artisan make:` commands for file generation
2. Create Form Request classes for all validations
3. Create Factories & Seeders for every model
4. Run `vendor/bin/pint --dirty` before commits
5. Write Pest tests for each feature
6. Check sibling files for conventions before coding

---

## Implementation Phases

### Phase 1: Foundation (Weeks 1-2)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 01 | Authentication & Users | P0 | None |
| 02 | Roles & Permissions | P0 | Module 01 |
| 03 | Shops Management | P0 | Module 02 |

### Phase 2: Product Management (Weeks 3-4)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 04 | Categories | P1 | Module 03 |
| 05 | Products & Variations | P1 | Module 04 |
| 06 | Pricing Management | P1 | Module 05 |

### Phase 3: Stock Management (Weeks 5-6)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 07 | Suppliers | P1 | Module 03 |
| 08 | Stock Intake (Batches) | P0 | Module 05, 07 |
| 09 | Inventory Tracking | P1 | Module 08 |

### Phase 4: Sales Management (Weeks 7-8)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 10 | Sales & Line Items | P0 | Module 08 |
| 11 | Payments | P0 | Module 10 |
| 12 | Credit Sales | P1 | Module 10, 11 |

### Phase 5: Financial Management (Weeks 9-10)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 13 | Expense Categories | P1 | Module 03 |
| 14 | Expenses Tracking | P1 | Module 13 |
| 15 | Advertising & ROI | P2 | Module 14 |

### Phase 6: Analytics & Reporting (Weeks 11-12)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 16 | Inventory Alerts | P1 | Module 09 |
| 17 | Reports & Dashboards | P1 | All Modules |
| 18 | Audit Logs | P2 | All Modules |

### Phase 7: Enhancements (Weeks 13+)
| Module | Document | Priority | Dependencies |
|--------|----------|----------|--------------|
| 19 | Returns & Refunds | P2 | Module 10 |
| 20 | FIFO Costing | P2 | Module 08 |
| 21 | Stock Adjustments | P2 | Module 09 |
| 22 | API Layer | P3 | All Modules |

---

## Module Document Structure

Each implementation document follows this structure:

```
1. Module Overview
2. Database Schema
3. Models & Relationships
4. Controllers & Routes
5. Form Requests (Validation)
6. Views (UI Components)
7. Tests (Pest)
8. Commands to Execute
9. Verification Checklist
```

---

## UI Template Mapping

| System Feature | Larkon Template Reference |
|----------------|---------------------------|
| Dashboard | `index.php`, `dashboard-sales.php` |
| User Management | `customer-list.php`, `customer-add.php` |
| Roles & Permissions | `role-list.php`, `role-add.php`, `pages-permissions.php` |
| Shops | `seller-list.php`, `seller-add.php` |
| Categories | `category-list.php`, `category-add.php` |
| Products | `product-list.php`, `product-add.php`, `product-details.php` |
| Inventory | `apps-ecommerce-inventory.php`, `inventory-warehouse.php` |
| Sales/Orders | `orders-list.php`, `order-detail.php` |
| Invoices | `invoice-list.php`, `invoice-add.php`, `invoice-details.php` |
| Expenses | `purchase-list.php` |
| Charts/Reports | `charts-apex-*.php` |
| Forms | `forms-*.php` |
| Tables | `tables-basic.php`, `tables-gridjs.php` |
| Authentication | `auth-signin.php`, `auth-signup.php`, `auth-password.php` |

---

## File Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Migration | `{timestamp}_create_{table}_table.php` | `2026_02_03_000001_create_shops_table.php` |
| Model | `{Singular}.php` | `Shop.php` |
| Controller | `{Singular}Controller.php` | `ShopController.php` |
| Form Request | `{Action}{Model}Request.php` | `StoreShopRequest.php` |
| Factory | `{Model}Factory.php` | `ShopFactory.php` |
| Seeder | `{Model}Seeder.php` | `ShopSeeder.php` |
| Test | `{Feature}Test.php` | `ShopManagementTest.php` |
| View Folder | `{plural}` | `resources/views/shops/` |

---

## Quick Commands Reference

```bash
# Create Model with all related files
php artisan make:model Shop -mfsc --no-interaction

# Create Controller
php artisan make:controller ShopController --resource --no-interaction

# Create Form Requests
php artisan make:request StoreShopRequest --no-interaction
php artisan make:request UpdateShopRequest --no-interaction

# Create Pest Test
php artisan make:test ShopManagementTest --pest --no-interaction

# Run migrations
php artisan migrate

# Run tests
php artisan test --compact

# Code style
vendor/bin/pint --dirty
```

---

## Getting Started

1. Complete Module 01 (Authentication & Users) first
2. Each module must pass all tests before moving to the next
3. Run `vendor/bin/pint --dirty` after each module
4. Commit after each completed module

---

## Document Index

- [00 - Master Plan](./00-implementation-master-plan.md) ← You are here
- [01 - Authentication & Users](./01-authentication-users.md)
- [02 - Roles & Permissions](./02-roles-permissions.md)
- [03 - Shops Management](./03-shops-management.md)
- [04 - Categories](./04-categories.md)
- [05 - Products & Variations](./05-products-variations.md)
- [06 - Pricing Management](./06-pricing-management.md)
- [07 - Suppliers](./07-suppliers.md)
- [08 - Stock Intake](./08-stock-intake.md)
- [09 - Inventory Tracking](./09-inventory-tracking.md)
- [10 - Sales & Line Items](./10-sales-line-items.md)
- [11 - Payments](./11-payments.md)
- [12 - Credit Sales](./12-credit-sales.md)
- [13 - Expense Categories](./13-expense-categories.md)
- [14 - Expenses Tracking](./14-expenses-tracking.md)
- [15 - Advertising & ROI](./15-advertising-roi.md)
- [16 - Inventory Alerts](./16-inventory-alerts.md)
- [17 - Reports & Dashboards](./17-reports-dashboards.md)
- [18 - Audit Logs](./18-audit-logs.md)
