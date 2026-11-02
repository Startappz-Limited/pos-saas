## Hotfix: authorize the flat API's writes

Most flat API writes checked only that the caller could reach the shop, never a permission. Any member with a
token could, for example, void a sale, delete a shop, approve an expense or edit products. Each write now checks the
same policy as its web counterpart. The few with no web counterpart need the nearest permission. `/api/v1` already
checked every write and is unchanged.

Also in this PR:
- `Api\ShopController::destroy` goes through `DeleteShopAction`, so staff are detached and the M-Pesa till
  registration is cleared, as on the web.
- Five pages that failed or went blank on a soft-deleted shop now show "Name (deleted)".
- **Customer permissions for the roles that use the app** (decided 2 November 2026; review finding 1). Two add-only,
  audited migrations give each shared template and every business's copy of it what it needs, so nobody loses
  customer work the flat API used to allow (see "The role migrations"):
  - cashier: `customers.create`;
  - manager: `customers.create`, `customers.update`, `customers.activate` and `customers.deactivate` (not delete);
  - accountant: `customers.create` and `customers.update`;
  - inventory clerk: nothing.

  `RoleSeeder` lists them for new installs.
- **Collecting a payment** (`SalePolicy::collectPayment`) also allows `credit-sales.collect` or `payments.create`,
  with shop access, so an accountant collects (review finding 2).
- **Completing a sale** has its own rule, `SalePolicy::complete` (review finding 3), used on the API and the web:
  `sales.create`, shop access, and the sale still pending, with no 24-hour limit. Editing a sale keeps
  `SalePolicy::update`'s 24-hour rule.
- **Converting an order that's already converted** answers 422 "This order cannot be converted to a sale." on the API,
  and returns to the order with that message on the web, not 403 (review finding 4). `EcommerceOrderPolicy::convert`
  checks permission only. The conversion also locks the order row and re-checks it inside its transaction, so two
  conversions at once can't both succeed.
- **The web's till helpers need `sales.create`**, as the API's do (review finding 5): opening and closing a register,
  and adding a sale source or delivery company from the sale screen.
- **Converting an online order checks its cash session.** A chosen `register_id` must be an open session of the
  order's own shop, as the abandoned-cart conversion already required. Before, any session the user could see was
  accepted, so a sale's totals and invoice series could land on another shop's till. The check runs before the
  website is changed. The sale endpoints that update a session's totals no longer fail with a 500 when the session
  is hidden from the person (`$sale->register?->`). The session is checked again inside the transaction under a row
  lock (review finding 7), since it may close between the first check and the sale.
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
| 1 | `POST /api/cash-registers/open` | permission | `sales.create` (the web now checks the same) |
| 2 | `POST /api/cash-registers/{cashRegister}/close` | permission | `sales.create` (the web now checks the same) |
| 3 | `POST /api/categories` | `CategoryPolicy::create` | `categories.create` |
| 4 | `PUT /api/categories/{category}` | `CategoryPolicy::update` | `categories.update` |
| 5 | `DELETE /api/categories/{category}` | `CategoryPolicy::delete` | `categories.delete`, and the category has no products |
| 6 | `POST /api/customers` | `StoreCustomerRequest` → `CustomerPolicy::create` | `customers.create` (cashiers, managers and accountants hold it) |
| 7 | `PUT /api/customers/{customer}` | `UpdateCustomerRequest` → `CustomerPolicy::update` | `customers.update` (managers and accountants hold it) |
| 8 | `DELETE /api/customers/{customer}` | `CustomerPolicy::delete` | `customers.delete` |
| 9 | `POST /api/customers/{customer}/activate` | `CustomerPolicy::activate` | `customers.activate` (managers hold it) |
| 10 | `POST /api/customers/{customer}/deactivate` | `CustomerPolicy::deactivate` | `customers.deactivate` (managers hold it) |
| 11 | `POST /api/delivery-companies` | permission | `sales.create` (quick add from the sale screen; the web now checks the same) |
| 12 | `PUT /api/delivery-companies/{delivery_company}` | permission | `settings.update` (no web screen) |
| 13 | `DELETE /api/delivery-companies/{delivery_company}` | permission | `settings.update` |
| 14 | `POST /api/delivery-companies/{deliveryCompany}/toggle-active` | permission | `settings.update` |
| 15 | `POST /api/ecommerce-orders/refresh` | `EcommerceOrderPolicy::refresh` | `ecommerce-orders.view` |
| 16 | `POST /api/ecommerce-orders/{order}/convert` | `ConvertOrderToSaleRequest` → `EcommerceOrderPolicy::convert` | `ecommerce-orders.convert` (or `edit-all`), within the shop; an order already converted is a 422, not a refusal |
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
| 42 | `POST /api/sale-sources` | permission | `sales.create` (quick add from the sale screen; the web now checks the same) |
| 43 | `POST /api/sales` | `StoreSaleRequest` → `SalePolicy::create` | `sales.create` |
| 44 | `PUT /api/sales/{sale}` | `SalePolicy::update` | `sales.update` within the shop and 24 hours of the sale (or `sales.edit-all`) |
| 45 | `DELETE /api/sales/{sale}` | `SalePolicy::delete` | `sales.delete` (or `delete-all`), within the shop |
| 46 | `POST /api/sales/{sale}/complete` | `SalePolicy::complete` | `sales.create`, within the shop, the sale pending; no 24-hour limit |
| 47 | `POST /api/sales/{sale}/void` | `SalePolicy::void` | `sales.void` (or `edit-all`), within the shop, not already voided |
| 48 | `POST /api/sales/{sale}/collect-payment` | `SalePolicy::collectPayment` | `sales.update`, `sales.edit-all`, `credit-sales.collect` or `payments.create`, within the shop |
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

**Not in this PR** (decided 2 November 2026; planned on `docs/saas-stage-plans`):
- `/api/v1/sync/push` checks no permission per item (customer, sale, sale payment, cash session). A member who can
  sign in on a paired till pushes them without `customers.create` or `sales.create`. A follow-up stage adds the checks,
  and keeps a refused item and flags it for the owner rather than dropping it.
- `ViewerShop` gives an owner with no assigned shop the business's first shop for new records. Within the business,
  as before; a follow-up lets them choose.

### Refusals

Every refusal is 403 with `{"message": "This action is unauthorized."}`: Laravel's shape, as the flat API's
existing policy refusals already were. The server's body stays as it is. Handling 403 is the app's job; the app
checklist says how. The app keys off the status code: per the contract skill, its `ErrorInterceptor` has no 403 case,
so today it shows a generic error. `tests/Unit/ApiForbiddenMessageTest` fails if an API controller raises a 403
without a message again.

### The role migrations

Two migrations, both through `App\Actions\Platform\GrantTemplatePermissions`:
- `2026_11_02_000001_grant_cashiers_customers_create.php`: cashier, `customers.create`;
- `2026_11_02_000002_grant_managers_accountants_customers.php`: manager, `customers.create`, `customers.update`,
  `customers.activate`, `customers.deactivate`; accountant, `customers.create`, `customers.update`.

Each grants a shared template and every business's copy of it (`roles.template_id`):
- **It only adds.** It inserts the missing grants and changes no other, so a permission a business took off its copy
  stays off.
- **A second run changes nothing**, and writes no audit entry.
- **Every role that gains a permission gets an audit entry** (review finding 1): `permissions_attached` with the
  permissions and the reason, as a grant on the role screens is recorded, with no person as the actor ("System") and
  the role's business.
- **It doesn't wipe customisations.** The console's "Apply to all copies" would have overwritten every copy, and
  `RoleSeeder` can't reach these old permissions on an existing database (stage 10, gap 11).
- **On UAT** (checked 2 November 2026): 12 cashier, manager and accountant roles of each kind (the template and 11
  copies), none holding any `customers.*` permission.

`tests/Feature/Roles/CustomerGrantsRolloutTest` covers the grants, idempotence, a removed permission staying removed,
the audit entries and the API rights; `CashierCustomersCreateTest` covers the cashier one.

### What changes for the app: intended refusals by role

All intended (decided 2 November 2026), matching the web.

**Cashier**, refused:
- voiding and deleting a sale (`sales.void`, `sales.delete`);
- every product, category, supplier and shop write;
- customer update, activate, deactivate and delete (a cashier may only **add** a customer);
- every expense write;
- an online order's status (`ecommerce-orders.update`);
- purchase-order approvals (approve, mark ordered, cancel, delete);
- delivery company edits (update, delete, toggle); quick add still works;
- editing a sale more than 24 hours old (completing it isn't limited).

Still allowed for a cashier:
- opening and closing a register;
- adding a customer, sale source or delivery company;
- creating and completing a sale (complete at any age while pending), and editing one within 24 hours;
- collecting a payment;
- converting an online order, and its notes and reminders.

**Manager:** keeps customer create, update, activate and deactivate; customer delete is refused.
**Accountant:** keeps customer create and update and collecting a payment; activate, deactivate and delete refused.
**Inventory clerk:** no customer writes.

### Checks

Run on the final commit, 2 November 2026:
- `vendor/bin/pint --dirty`: pass.
- `php artisan test --compact` (SQLite): 2,195 passed, 60 skipped, 4 todos.
- `vendor/bin/pest --configuration=phpunit.mysql.local.xml --compact` (MySQL 8): 2,205 passed, 50 skipped, 4 todos.
  (`LiveLikeDatasetTest` can fail when its two dataset builds fall either side of a day boundary for one purchase
  order's due date: `PurchaseOrderFactory` uses `fake()->dateTimeBetween($orderDate, '+90 days')`, whose end moves
  with the clock. Not from this change; it passed in this run. A follow-up fixes the factory's range.)
- UAT, read-only: no sale is on another shop's cash session (0 of 1,503); 11 cashier copies, all matching the
  template, none holding `customers.create` yet.

New tests:
- `FlatApiWriteAuthorizationTest` (review finding 6), for each of 53 writes:
  - refused to a member with no permission, with the message, the record's row (or, for a create, the table)
    unchanged;
  - refused the same way to a member with only an unrelated permission;
  - let through (not 401, 403 or 404) for a member with exactly the intended permission.

  Plus: the cashier's till work still allowed, a cashier can't void, and the API's shop delete behaves like the web's.
  The positive cases show the check asks for that permission; whether a minimal payload then succeeds is not
  asserted. The 54th write (resolving an online order's reminder) needs an alert and isn't in the data set.
- `DeletedShopNamesTest`: the five pages.
- `TenantIsolation/NoBusinessResolvedTest`: a bound `/api/v1` token of a suspended or closing business sees no
  business's data.
- `Roles/CashierCustomersCreateTest`: the migration and the cashier's customer rights.
- `Roles/CustomerGrantsRolloutTest`: both role migrations, their audit entries, and managers' and accountants' API
  rights.
- `SaleCollectAndCompleteTest`: an accountant collects; a member with none of the collecting permissions can't; a
  cashier completes a two-day-old pending sale on the API and the web but can't edit it; a non-pending sale, or a
  member without `sales.create`, can't be completed.
- `ConvertOrderTwiceTest`: the second conversion of one order is a 422 (API) or the message (web), with one sale.
- `WebTillHelpersTest`: the web's register and quick-add actions need `sales.create`.
- `SaleRegisterShopTest`: a conversion refused onto another shop's session, before the website is changed; a
  cashier completes a sale without a 500.
- `Unit/ApiForbiddenMessageTest`: no 403 without a message in the API controllers.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
