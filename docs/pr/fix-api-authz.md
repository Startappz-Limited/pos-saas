## Hotfix: authorize the flat API's writes

Most flat API writes checked only that the caller could reach the shop, never a permission. Any member with a
token could, for example, void a sale, delete a shop, approve an expense or edit products. Each write now checks the
same policy as its web counterpart. The few with no web counterpart need the nearest permission. `/api/v1` already
checked every write and is unchanged.

Also in this PR:
- `Api\ShopController::destroy` goes through `DeleteShopAction`, so staff are detached and the M-Pesa till
  registration is cleared, as on the web.
- Five pages that failed or went blank on a soft-deleted shop now show "Name (deleted)".
- **Cashiers may add a customer** (decided 2 November 2026). A one-off migration grants `customers.create` to the
  shared cashier template and to every business's copy of it; `RoleSeeder` lists it for new installs.
- **Converting an online order checks its cash session.** A chosen `register_id` must be an open session of the
  order's own shop, as the abandoned-cart conversion already required. Before, any session the user could see was
  accepted, so a sale's totals and invoice series could land on another shop's till. The check runs before the
  website is changed. The sale endpoints that update a session's totals no longer fail with a 500 when the session
  is hidden from the person (`$sale->register?->`).
- **A member with no shop can't add a customer, on the web or the API.** `customers.shop_id` is required.
  - **Before, the API** wrote a null shop and failed with a 500.
  - **Before, the web** fell back to shop id 1. The customer's business is read from its shop, so this could put the
    customer in **another business**.
  - **Now both** answer a validation error, "You haven't been given a shop yet. Ask the owner." (422 on the API,
    `errors.shop`). UAT has no customer in the wrong business (checked 2 November 2026).
  - **Why not attach the customer to the business instead:** that needs `shop_id` to become nullable, a schema change
    to a column with a cascading foreign key, which is out of scope for a hotfix. A customer stays in a shop.
  - Covered by `CustomerNeedsShopTest`.
- **Hard-coded tenant fallbacks** (swept 2 November 2026: `?? 1`, `shop_id`/`business_id` = 1, `Shop::first()` and
  `Business::first()` outside seeders and tests, unscoped `first()`). Fixed:
  - `CustomerController::store` (web): `?? 1` put a customer on shop 1, possibly another business's (above).
  - `CheckLowStockLevels`: `'created_by' => $userId ?? 1` credited a check with no user to user 1, who belongs to
    business 1. It now records no creator (the column is nullable). Latent: today's only caller passes the user, and
    UAT has no alert from user 1.
  - Writes with no shop for the person, which wrote a null shop or shop 0 and failed with a 500:
    - `ExpenseController::store` (web), a null `expenses.shop_id`;
    - `CashRegisterController::store` (web) and `Api\CashRegisterController::open`, shop 0 through
      `CashRegister::openRegister()`.

    Each now calls `App\Support\Tenancy\ViewerShop::require()` and refuses with "You haven't been given a shop yet.
    Ask the owner." (422 / back to the form). `openRegister()` itself refuses a null shop.
  - `CashRegisterController::store` (web) also read the optional `opening_notes` unconditionally (a 500 when left
    out).

  Left as they are, and safe: about 15 read paths that use `auth()->user()->shop_id ?? Shop::first()?->id` (reports,
  register lists, customer and expense lists, low-stock checks). `Shop::first()` runs through `ShopAccessScope`, so it
  never reaches another business. It is null for someone with no shop, and those reads then filter on a null shop and
  return nothing (or, in `LowStockAlertController`, say so). The remaining `?? 1` hits are page numbers, quantities and
  month counts. The unscoped `first()` calls are the listed scope bypasses (invite and export tokens, a variation's own
  product). Covered by `NoShopWritesTest` and `CustomerNeedsShopTest`.
- **Every 403 has a message.** The 24 `abort(403)` and `abort_unless(…, 403)` calls in the API controllers (flat and
  `/api/v1`) used to send `{"message": ""}`; they now say "This action is unauthorized.", like `authorize()`.

### Endpoints and what they now need

The "(within the shop)" rules also require access to the record's shop. A `*.full-access` permission passes every
check of its policy (`sales`, `expenses`, `expense-categories`, `purchase_orders`, `shops`). `*.edit-all` and
`*.delete-all` pass where noted.

| # | Endpoint | Check | Permission now needed |
|---|---|---|---|
| 1 | `POST /api/cash-registers/open` | permission | `sales.create` (no web check) |
| 2 | `POST /api/cash-registers/{cashRegister}/close` | permission | `sales.create` (no web check) |
| 3 | `POST /api/categories` | `CategoryPolicy::create` | `categories.create` |
| 4 | `PUT /api/categories/{category}` | `CategoryPolicy::update` | `categories.update` |
| 5 | `DELETE /api/categories/{category}` | `CategoryPolicy::delete` | `categories.delete`, and the category has no products |
| 6 | `POST /api/customers` | `StoreCustomerRequest` → `CustomerPolicy::create` | `customers.create` (cashiers now hold it, see below) |
| 7 | `PUT /api/customers/{customer}` | `UpdateCustomerRequest` → `CustomerPolicy::update` | `customers.update` |
| 8 | `DELETE /api/customers/{customer}` | `CustomerPolicy::delete` | `customers.delete` |
| 9 | `POST /api/customers/{customer}/activate` | `CustomerPolicy::activate` | `customers.activate` |
| 10 | `POST /api/customers/{customer}/deactivate` | `CustomerPolicy::deactivate` | `customers.deactivate` |
| 11 | `POST /api/delivery-companies` | permission | `sales.create` (quick add from the sale screen; no web check) |
| 12 | `PUT /api/delivery-companies/{delivery_company}` | permission | `settings.update` (no web screen) |
| 13 | `DELETE /api/delivery-companies/{delivery_company}` | permission | `settings.update` |
| 14 | `POST /api/delivery-companies/{deliveryCompany}/toggle-active` | permission | `settings.update` |
| 15 | `POST /api/ecommerce-orders/refresh` | `EcommerceOrderPolicy::refresh` | `ecommerce-orders.view` |
| 16 | `POST /api/ecommerce-orders/{order}/convert` | `ConvertOrderToSaleRequest` → `EcommerceOrderPolicy::convert` | `ecommerce-orders.convert` (or `edit-all`), within the shop, and the order can be converted |
| 17 | `POST /api/ecommerce-orders/{order}/notes` | `EcommerceOrderPolicy::view` | `ecommerce-orders.view`, within the shop |
| 18 | `POST /api/ecommerce-orders/{order}/reminders` | `EcommerceOrderPolicy::view` | `ecommerce-orders.view`, within the shop |
| 19 | `POST /api/ecommerce-orders/{order}/reminders/{alert}/resolve` | `EcommerceOrderPolicy::view` | `ecommerce-orders.view`, within the shop |
| 20 | `POST /api/ecommerce-orders/{order}/update-status` | `UpdateOrderStatusRequest` → `EcommerceOrderPolicy::updateStatus` | `ecommerce-orders.update` (or `edit-all`), within the shop |
| 21 | `POST /api/expense-categories` | `ExpenseCategoryPolicy::create` | `expense-categories.create` |
| 22 | `PUT /api/expense-categories/{expense_category}` | `ExpenseCategoryPolicy::update` | `expense-categories.update` |
| 23 | `DELETE /api/expense-categories/{expense_category}` | `ExpenseCategoryPolicy::delete` | `expense-categories.delete` |
| 24 | `POST /api/expenses` | `ExpensePolicy::create` | `expenses.create` |
| 25 | `PUT /api/expenses/{expense}` | `ExpensePolicy::update` | `expenses.update` (or `edit-all`), within the shop |
| 26 | `DELETE /api/expenses/{expense}` | `ExpensePolicy::delete` | `expenses.delete` (or `delete-all`), within the shop |
| 27 | `POST /api/expenses/{expense}/submit` | `ExpensePolicy::update` | as 25 |
| 28 | `POST /api/expenses/{expense}/approve` | `ExpensePolicy::approve` | `expenses.approve`, within the shop |
| 29 | `POST /api/expenses/{expense}/reject` | `ExpensePolicy::approve` | as 28 |
| 30 | `POST /api/expenses/{expense}/mark-paid` | `ExpensePolicy::update` | as 25 |
| 31 | `POST /api/expenses/{expense}/settle-from-register` | `ExpensePolicy::update` | as 25 |
| 32 | `POST /api/expenses/{expense}/cancel` | `ExpensePolicy::update` | as 25 (no web counterpart; update is the nearest) |
| 33 | `POST /api/products` | `ProductPolicy::create` | `products.create` |
| 34 | `PUT /api/products/{product}` | `ProductPolicy::update` | `products.update` |
| 35 | `DELETE /api/products/{product}` | `ProductPolicy::delete` | `products.delete` |
| 36 | `POST /api/products/{product}/activate` | `ProductPolicy::update` | `products.update` |
| 37 | `POST /api/products/{product}/deactivate` | `ProductPolicy::update` | `products.update` |
| 38 | `DELETE /api/purchase-orders/{purchase_order}` | `PurchaseOrderPolicy::delete` | `purchase_orders.delete`, draft only |
| 39 | `POST /api/purchase-orders/{purchaseOrder}/approve` | `PurchaseOrderPolicy::approve` | `purchase_orders.approve`, when it can be approved |
| 40 | `POST /api/purchase-orders/{purchaseOrder}/mark-ordered` | `PurchaseOrderPolicy::markAsOrdered` | `purchase_orders.update`, approved only |
| 41 | `POST /api/purchase-orders/{purchaseOrder}/cancel` | `PurchaseOrderPolicy::cancel` | `purchase_orders.update`, when it can be cancelled |
| 42 | `POST /api/sale-sources` | permission | `sales.create` (quick add from the sale screen; no web check) |
| 43 | `POST /api/sales` | `StoreSaleRequest` → `SalePolicy::create` | `sales.create` |
| 44 | `PUT /api/sales/{sale}` | `SalePolicy::update` | `sales.update` within the shop and 24 hours of the sale (or `sales.edit-all`) |
| 45 | `DELETE /api/sales/{sale}` | `SalePolicy::delete` | `sales.delete` (or `delete-all`), within the shop |
| 46 | `POST /api/sales/{sale}/complete` | `SalePolicy::update` | as 44 |
| 47 | `POST /api/sales/{sale}/void` | `SalePolicy::void` | `sales.void` (or `edit-all`), within the shop, not already voided |
| 48 | `POST /api/sales/{sale}/collect-payment` | `SalePolicy::collectPayment` | `sales.update` (or `edit-all`), within the shop |
| 49 | `POST /api/shops` | `ShopPolicy::create` | `shops.create` |
| 50 | `PUT /api/shops/{shop}` | `ShopPolicy::update` | `shops.update`, within the shop |
| 51 | `DELETE /api/shops/{shop}` | `ShopPolicy::delete` | `shops.delete`, within the shop; goes through `DeleteShopAction` |
| 52 | `POST /api/suppliers` | `SupplierPolicy::create` | `suppliers.create` |
| 53 | `PUT /api/suppliers/{supplier}` | `SupplierPolicy::update` | `suppliers.update` |
| 54 | `DELETE /api/suppliers/{supplier}` | `SupplierPolicy::delete` | `suppliers.delete` |

The five FormRequests in the table (rows 6, 7, 16, 20, 43) authorize before they validate. A caller without the
permission gets 403 whatever they send, not a 422 that would reveal the rules.

**Already checked before this PR, unchanged:**
- abandoned carts (7 writes);
- users (3);
- `customers/{customer}/send-statement`;
- `purchase-orders` store and update (through their FormRequests);
- every `/api/v1` write: owners only, `settings.update`, a policy, or a register token.

**Need no permission:** sign-in, sign-out and sign-up; your own till PIN; switching to a business you belong to;
the three webhooks, which are signature-verified.

### Refusals

Every refusal is 403 with `{"message": "This action is unauthorized."}`: Laravel's shape, as the flat API's
existing policy refusals already were. The server's body stays as it is. Handling 403 is the app's job; the app
checklist says how. The app keys off the status code: per the contract skill, its `ErrorInterceptor` has no 403 case,
so today it shows a generic error. `tests/Unit/ApiForbiddenMessageTest` fails if an API controller raises a 403
without a message again.

### The cashier migration

`database/migrations/2026_11_02_000001_grant_cashiers_customers_create.php` adds `customers.create` to the shared
cashier template and to every business's copy of it (`roles.template_id`).
- **It only adds.** It inserts with `insertOrIgnore` and removes nothing, so a business that took a permission away
  from its copy keeps it away.
- **A second run changes nothing.**
- **It doesn't wipe customisations.** The console's "Apply to all copies" would have overwritten every copy, and
  `RoleSeeder` can't reach this old permission on an existing database (stage 10, gap 11).
- **On UAT** (checked 2 November 2026): 11 cashier copies, all identical to the template, none holding the permission.

Cashiers get **only** `customers.create`. `tests/Feature/Roles/CashierCustomersCreateTest` covers the template,
the copies, idempotence, and an API create that succeeds while a customer edit is still refused.

### What changes for the app: intended cashier refusals

For a cashier, these writes now return 403. **All are intended (decided 2 November 2026)**, matching the web:

- voiding and deleting a sale (`sales.void`, `sales.delete`);
- every product write: create, update, delete, activate, deactivate;
- every category, supplier and shop write;
- customer update, activate, deactivate and delete (cashiers may only **add** a customer);
- every expense write: create, update, delete, submit, cancel, approve, reject, mark paid, settle from the register;
- an online order's status (`ecommerce-orders.update`);
- purchase-order approvals: approve, mark ordered, cancel, delete;
- delivery company edits: update, delete, toggle. Quick add still works.

Still allowed for a cashier:
- opening and closing a register;
- adding a sale source, delivery company or customer;
- creating, updating and completing a sale (update and complete within 24 hours of the sale);
- collecting a payment;
- converting an online order, and its notes and reminders.

### Checks

Run on the final commit, 2 November 2026:
- `vendor/bin/pint --dirty`: pass.
- `php artisan test --compact` (SQLite): 2,076 passed, 60 skipped, 4 todos.
- `vendor/bin/pest --configuration=phpunit.mysql.local.xml --compact` (MySQL 8): 2,085 passed, 1 failed, 50 skipped,
  4 todos. The failure is `LiveLikeDatasetTest` ("builds the same dataset twice"): a purchase order's
  `payment_due_date` differed by a day between two builds. It isn't from this change. `PurchaseOrderFactory` uses
  `fake()->dateTimeBetween($orderDate, '+90 days')`, whose end moves with the clock, so the same seeded value can fall
  on the next day. The test file rerun alone on MySQL passes (13 of 13). A follow-up fixes the factory's range.
- UAT, read-only: no sale is on another shop's cash session (0 of 1,503); 11 cashier copies, all matching the
  template, none holding `customers.create` yet.

New tests:
- `FlatApiWriteAuthorizationTest`: 53 writes refused with the message and no row changed; the cashier's till work
  still allowed; a cashier can't void; the API's shop delete behaves like the web's.
- `DeletedShopNamesTest`: the five pages.
- `TenantIsolation/NoBusinessResolvedTest`: a bound `/api/v1` token of a suspended or closing business sees no
  business's data.
- `Roles/CashierCustomersCreateTest`: the migration and the cashier's customer rights.
- `SaleRegisterShopTest`: a conversion refused onto another shop's session, before the website is changed; a
  cashier completes a sale without a 500.
- `Unit/ApiForbiddenMessageTest`: no 403 without a message in the API controllers.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
