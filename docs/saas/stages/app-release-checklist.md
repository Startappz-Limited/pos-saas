# App Release Checklist

Created 27 October 2026 (sixth round); stage 10a's section added and stage 10's discard item updated in the eighth round (28 October 2026), and again in the
ninth and tenth (28 October 2026) and in stage 10a's third and fourth revisions (29 October 2026), its fifth revision and the hotfix
fix/api-authz (2 November 2026). The Flutter app's next release isn't scheduled, and the app isn't on this
machine (`MOBILE_APP_PATH` points nowhere). The server stays backward compatible with the app in shops through every
stage (additions only, old fields kept, Phase 7's codes for apps without a new enough `X-App-Version`), and each
stage's rollout checks **the server** against the app's recorded requests. Everything below checks **the app**, and is
done when the app is on this machine and its release is planned. Nothing here assumes how the app behaves today: each
item is a question to answer by reading or running the app.

How to use it: for each stage the release includes, read the app's code for each item, run it against a rehearsal
server, tick it, and note the app version. `php artisan mobile:contract-diff` runs first.

## Stage 10
Stage file: [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md).
- [ ] Sends `X-App-Version` on every push, pull and pairing request.
- [ ] Follows `disposition` on push results: `retry` resends, `drop` removes and shows why, `hold` keeps the item marked.
- [ ] Handles status `pending` and the codes `customer_not_synced`, `sale_not_synced`, `cash_session_not_synced`.
- [ ] Handles the void flow: shows Void when `voidable` is true and the person holds `sales.void`; calls
      `POST /api/v1/sales/{uuid}/void` online; queues `sale_void` offline; reads `sale_voids` from the pull.
- [ ] Handles the void refusals: `void_window_closed`, `sale_has_returns`, `sale_already_voided`, 403.
- [ ] Clears items listed in the pull's `resolved_items`, and items answered `applied` or `discarded`.
- [ ] Lets a manager holding `sync-issues.discard` (from the staff list) discard a held or stuck item with a reason
      (`outbox_item_discarded`), with the items queued behind it; offers discarding a held sale or a payment only to
      someone holding `sales.void`; never offers "accept" at the till.
- [ ] Sends `outbox_count`; never deletes a local store with unsynced items; asks before signing out or switching
      business with unsynced items; handles 422 `till_has_unsynced_items` at pairing with a confirmation.
- [ ] Shows `cost_price: null` without breaking product screens; hides cost fields by permission.
- [ ] Handles 403 on product create, edit and delete; creates products only online; can't sell an unknown barcode
      offline.
- [ ] Hides Delete on completed sales; handles the 422 if shown.
- [ ] Treats `invoice_number` as an opaque string (server-numbered sales now use the web series `INV-{shop}-W-…`).
- [ ] Numbers offline sales only in its own register's series, from `last_invoice_number` at pairing.
- [ ] Shows the till sync review's outcome for a held sale (accepted with its own number, or discarded).

## Stage 10a
Stage file: [10a-access-and-tenancy.md](./10a-access-and-tenancy.md).
- [ ] Shows the shops list to every member for the shops they can reach, and handles a 403 on shop or user editing.
- [ ] Re-reads the person's permissions after a refusal and on resume, since an unbound flat API token follows the
      user's default business (planned, stage 10a first revision).
- [ ] Handles 403 `no_active_business` on every flat API and `/api/v1` call: it shows that the person isn't a member of
      any active business, instead of an empty or zero screen (checked 29
      October 2026: the flat API's `EnsureActiveBusiness` returns it on every authenticated route, and `/api/v1`
      sign-in returns it when the person has no usable business; planned, stage 10a third revision). From stage 10a's
      fourth revision `/api/v1` also refuses every call but sign-out once the token's business can't be entered, sync
      included, where some calls answered 200 or a generic 403 before: **403 `business_suspended`** when the business
      is suspended (as the flat API does), **403 `no_active_business`** otherwise (fifth revision, 2 November 2026).
      The app says which ("This business is suspended…" or "You are not a member of any active business."), then
      signs out of `/api/v1` (sign-out still works).

## Hotfix fix/api-authz
Branch `fix/api-authz` (`c099c16`, `ef9f74d`, 29 October and 2 November 2026), not a stage: the flat API's writes now
check the web's permissions. Its PR body lists all 54 endpoints with the permission each needs.
- [ ] **A cashier's calls that are now refused** (403, probed 2 November 2026 against the seeded cashier role) are
      handled, not shown as a server error. The app hides or explains the action instead:
      - intended: void and delete a sale (`sales.void`, `sales.delete`); create, edit, delete, activate and deactivate
        products; every write on categories, suppliers, shops and expense categories; approve, mark ordered, cancel
        and delete purchase orders; approve, reject and mark paid expenses; edit, delete and toggle delivery
        companies (quick add still works); delete, activate and deactivate customers;
      - **decision pending** (possible regressions): adding a customer (`POST /api/customers`, needs
        `customers.create`), editing one (`customers.update`), recording, editing, submitting and cancelling an
        expense (`expenses.create`, `expenses.update`), and moving an online order's status (`ecommerce-orders.update`).
        If the cashier template gains any of these, the check changes with it.

      Still allowed for a cashier: opening and closing a register, adding a sale source or delivery company,
      creating, updating and completing a sale (update and complete within 24 hours of the sale), collecting a
      payment, converting an online order, and its notes and reminders.
- [ ] **The 403 body** is `{"message": "This action is unauthorized."}` (Laravel's shape, no `success`) on every
      refusal the hotfix added; the older shop-access refusals still send `{"message": ""}`. The app keys off the
      status code (its `ErrorInterceptor` has no 403 case, so a 403 falls through to `ServerException`, checked 2
      November 2026 against the contract skill): it maps 403 to a "You don't have permission to do that." message,
      and uses `message` when it isn't empty.

## Stage 11
Stage file: [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md).
- [ ] Stores the `units` entity, including deleted units (`deleted_at`), and finds them on lookup for past receipts.
- [ ] Looks barcodes up in units, adding that unit; never shows an unassigned barcode.
- [ ] Sends `unit`, the quantity in that unit (decimal) and `base_quantity` on every sale line.
- [ ] Refuses a fraction of a precision-0 unit at the till.
- [ ] Parses the pull's new decimal strings (`base_quantity`, `base_reorder_level`, `sellable_quantity`,
      `expired_quantity`, `factor`) and still reads `quantity` and `reorder_level` as whole numbers.
- [ ] Parses flat API quantities that are numbers, integer when whole, decimal when fractional.
- [ ] Warns when `sellable_quantity` is short or `next_expiry` is near.
- [ ] Holds a sale refused `unknown_unit` and lets a manager discard it.
- [ ] Prints the unit on receipts (`unit_name`, `quantity_exact`).
- [ ] Reads `track_batches` and `stock.consumption` without breaking.

## Stage 11b
Stage file: [11b-prices-and-discounts.md](./11b-prices-and-discounts.md).
- [ ] Stores `unit_prices` and prices a cart as `PriceResolver` does (shop price, unit price, base × factor, then
      quantity breaks across lines in base units, the lower of the two).
- [ ] Reads the pricing rules' `variation_uuid`, `unit_uuid` and customer uuid.
- [ ] Asks for an approver's till PIN for a price below the quote or a discount above the person's role limit; checks
      `sales.override-price` from the staff list; counts wrong PINs towards the lockout.
- [ ] Sends `price_overridden_by`, `discount_approved_by` and `override_approved_at`.
- [ ] Handles 422 `price_below_expected` and `discount_above_limit` online, showing the expected price or the limit.

## Stage 11c
Stage file: [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md).
- [ ] Works low stock out from `sellable_quantity` against `base_reorder_level`, as the server does.
- [ ] Shows the flat API's low-stock lists under their new definition without breaking.

## Stage 12
Stage file: [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md).
- [ ] Purchase orders: sends `unit_id` (or none, for the default purchase unit) and reads `unit`,
      `base_quantity_ordered`, `base_quantity_received`.

## Stage 15
Stage file: [15-regulated-products.md](./15-regulated-products.md).
- [ ] Stores `control_classes` and `record_templates` (by version) and each variation's `control_class_uuid`.
- [ ] Refuses lines of a class not sellable offline; asks for the supervisor's till PIN where required.
- [ ] Captures records from templates, with `template_version`, and sends them with the line.
- [ ] Handles `controlled_product_permission`, `supervisor_required` and `regulated_record_required`.

## Stage 17
Stage file: [17-platform-tills-sync-mpesa.md](./17-platform-tills-sync-mpesa.md).
- [ ] Copes with more than one register in `/api/v1/registers` and lets a manager choose which till to pair.

## Stage 18
Stage file: [18-alerts-and-notifications.md](./18-alerts-and-notifications.md).
- [ ] Shows alerts of types it doesn't know without breaking (if it reads alerts at all).

## Stage 19
Stage file: [19-expenses.md](./19-expenses.md).
- [ ] Reads `attachments` on an expense; uploads receipts; handles the 422 for a missing required receipt.

## Stage 21
Stage file: [21-plans-billing-trial-banner.md](./21-plans-billing-trial-banner.md).
- [ ] Reads `trial.ends_at` and `trial.seconds_left` from `/api/v1/billing`.

## Stage 22
Stage file: [22-etims.md](./22-etims.md).
- [ ] Prints the receipt's `fiscal` block (control code, QR) once signed; prints unsigned receipts as today.

## Stage 23
Stage file: [23-stocktakes-in-the-app.md](./23-stocktakes-in-the-app.md).
- [ ] Counting in the app: written when that stage is planned in full.

## Counts

| Stage | Checks |
|---|---|
| 10 | 14 |
| 10a | 3 |
| Hotfix fix/api-authz | 2 |
| 11 | 10 |
| 11b | 5 |
| 11c | 2 |
| 12 | 1 |
| 15 | 4 |
| 17 | 1 |
| 18 | 1 |
| 19 | 1 |
| 21 | 1 |
| 22 | 1 |
| 23 | 1 (placeholder) |
| **Total** | **47** |
