## Stage 10: stock correctness and voids

Stage file: [docs/saas/stages/10-stock-correctness-and-voids.md](../saas/stages/10-stock-correctness-and-voids.md).
Every acceptance criterion there is ticked with its evidence, except one that needs the UAT rehearsal (see "Before
merge").

Before this stage:
- a void left the stock sold and the money taken;
- a sale took stock from the product's shop, not the shop that sold it;
- adjustments ignored the variation and their sign;
- a completed sale could be hard-deleted;
- a till and the web could issue the same invoice number;
- a permission granted to a role template after a business was created never reached that business.

This PR fixes each of these, step by step as the stage file plans it.

### Changes, by step

**Step 0: permissions reach every business.**
- `permissions:sync` (PermissionSeeder then RoleSeeder) gives every business's copy of a template the template's
  grants, minus the removals recorded in `role_revoked_permissions`.
- `RevokeTemplatePermissions` records a removal. The backfill runs inside the migration that creates the table, so a
  sync after the deploy can't hand back a permission a business took away.
- `Gate::before` lets only platform operators through, never a business owner.

**Step 1a: sell from the selling shop.** Every sale path (web POS, flat API, till push, abandoned cart, online order)
takes stock from the sale's shop. A sale below zero records a `negative_stock` issue.

**Step 1f: clerks don't see or set costs.**
- The clerk roles lose `products.view-cost` and `products.set-cost`.
- The flat API sends `cost_price: null` without `products.view-cost`. Product create, edit and delete need the
  product permissions.

**Step 1g and its follow-up: stock adjustments.**
- An adjustment moves the variation it names and records signed changes.
- Its numbers come from a gapless per-shop series (`ADJ-{shop}-{year}-{n}`).
- Its value is the cost of what it moved: an increase is costed at the latest batch cost, else marked unknown.
- These adjustments wait for a second person holding `stock-adjustments.approve`, through the shared approval rule
  (`App\Support\Approvals`): loss, theft, damage, expiry, quality issue, giveaway, a recount that lowers stock, and
  any over the value threshold. A sole owner may approve their own, with a note.
- `stock-adjustments:list-unapplied` lists adjustments approved before this stage but never applied. Nothing applies
  them by command.

**Step 2: who can void, and for how long.**
- Only `sales.void` voids. Managers lose it; the owner keeps it.
- The void window is per business: `session` by default, else `end_of_day`, `minutes` or `none`, in shop-local time,
  never past the shop's midnight. Only the owner changes it.
- Past the window, a void answers 422 `void_window_closed`.
- Someone without `sales.void` requests a void (`sale_void_requests`). Requests expire after 48 hours, and approvers
  are notified and reminded hourly (`approvals:remind`).

**Step 3: `StockLedger::release()`.** Gives a consumer's units back to the batches they came from, including
provisional below-zero units (`SALE_VOID`, `SALE_DELETE`, `CostLayer::SOURCE_RELEASE`).

**Step 4: voiding gives stock and money back** (`VoidSale`, `VoidRefunds`; decided 4 November 2026).
- Cash comes out of the voider's own open session, else it becomes a refund owed. It never comes out of someone
  else's session.
- Card, M-Pesa, bank transfer and cheque are always owed (`refunds_due`). Store credit is never refunded.
- People holding `sales.void` or `refunds.approve` pay refunds owed (Refunds owed screen).
- A manager records petty cash and drawer payouts (`cash_payouts`, `cash-registers.payout`). The session's expected
  cash subtracts payouts.
- A sale with a return that isn't rejected can't be voided.

**Step 5: deleting a sale.**
- Completed sales can't be deleted (422 `sale_completed`). A pending sale with payments must be voided instead (422
  `sale_has_payments`).
- A draft or pending sale with no payments is soft-deleted, and its stock is returned first.
- 17 raw `sales` queries now add `whereNull('sales.deleted_at')`. The sale page still opens a deleted sale, with a
  "Deleted" banner.

**Step 5a: a Void button on the web sale page.**
- The button is labelled and asks for confirmation.
- When a void isn't possible (past the window, or a return exists), the page says why.
- Someone without `sales.void` sees "Request void" instead.

**Step 5b: online orders cancelled before dispatch void automatically** (decided 4 November 2026).
- Store money is marked "refunded in store". POS payments become refunds owed.
- A paid order with no refund, a partial refund, or a cancellation after dispatch is flagged for review, and owners
  are notified. None of these is voided.

**Step 5c: voids from the till** (decided 4 November 2026).
- `POST /api/v1/sales/{uuid}/void` voids online. Offline, the push item `sale_void` does it; a void pushed before its
  sale is `pending`.
- The till's session at the void time pays the cash. When that session is already closed, it is corrected with an
  audit note and owners are flagged.
- Every push result carries a `disposition` (`retry`, `drop` or `hold`). Apps below `SYNC_MIN_VERSION_DISPOSITIONS`
  get Phase 7's codes (`LegacyRefusals`).
- Held items are accepted or discarded on the server (sync issues), or re-rung as a new web sale. The till discards
  its own items with `outbox_item_discarded`.
- The pull gains `sale_voids`, `resolved_items` and `stock.pending_removal`. The receipt gains `voidable` and
  `voidable_until`.
- Registers record `outbox_count` and `X-App-Version`. Re-pairing a till with unsynced items needs confirmation (422
  `till_has_unsynced_items`).
- A late or duplicate M-Pesa payment for a voided or already-paid sale becomes a refund owed.

**Step 5d: invoice series.**
- Server-numbered sales take the shop's web series, `INV-{shop}-W-{year}-{n}`: web POS, flat API, online orders and
  abandoned carts. Tills keep their register's series.
- Register codes must be `R` plus digits; `W` is reserved.
- The year follows the shop's local date.
- The web series uses the same locked sequence row as the till series, so it is gapless under concurrent saves (MySQL
  concurrency test).
- `invoices:report-series` lists the series in use (read-only).
- Nothing in the codebase parses an invoice number.

**Step 6: lines whose cost isn't fully known.**
- `sale_items.cost_known` is false when any unit came from a batch with no cost. It is backfilled from existing
  allocations.
- The sale page and the profit report show a "Cost incomplete" badge.
- The profit report's view was missing, so it was built here.

**Step 7: repairs for sales voided and deleted before this stage.**
- `sales:restock-voided` gives an old void's stock back once (dry run, then `--apply`) and records no money.
- `stock:orphaned-movements` lists sale movements whose sale was hard-deleted.

**Step 8: housekeeping.** CLAUDE.md (deploy order, background work, stage 10 rules, invoice series, audited models),
the `pos-domain-rules` skill, the Flutter product docs, and PROGRESS.md.

### Migrations

All are additive, and each carries existing data across:

| Migration | What it does |
|---|---|
| `2026_11_03_000001_create_role_revoked_permissions_table` | Removal records; **runs the rollout backfill** (Phase 5 and 7 permissions granted, other absences recorded) |
| `2026_11_03_000002_revoke_clerk_cost_permissions` | Clerk copies lose `products.view-cost` and `products.set-cost` (recorded) |
| `2026_11_03_000003_stock_adjustments_by_variation` | `variation_id`, signed changes, numbers, value |
| `2026_11_03_000004_revoke_manager_sales_void` | Manager copies lose `sales.void` (recorded) |
| `2026_11_03_000005_create_notifications_table` | Database notifications (approvals) |
| `2026_11_03_000006_stock_adjustment_approvals` | Approval columns; figures captured at approval |
| `2026_11_03_000007_grant_manager_adjustment_approval` | Managers get `stock-adjustments.approve` |
| `2026_11_03_000008_create_sale_void_requests_table` | Void requests |
| `2026_11_04_000001_create_cash_payouts_and_refunds_due` | `cash_payouts`, `refunds_due` |
| `2026_11_04_000002_sale_voids_at_tills` | `sales.voided_register_id`, `void_recorded_at` |
| `2026_11_04_000003_register_outbox_and_app_version` | Registers' `outbox_count`, `app_version` and their report times |
| `2026_11_04_000004_held_items_and_sale_flags` | `sync_push_receipts.outcome`, `sales.sync_flags` |
| `2026_11_04_000005_sale_items_cost_known` | `sale_items.cost_known`, backfilled (counts printed) |

Step 5's soft deletes need no migration: `sales.deleted_at` is already in the baseline; only the model now uses it.

### Rollout

1. `php artisan db:snapshot` (UAT).
2. Restore it into `rehearsal_testing`, and run the migrations there with `--database=rehearsal_testing`. Check:
   - the backfill's counts, with none of this stage's new permissions recorded as removed;
   - the revocation counts (manager copies losing `sales.void`, clerk copies losing both cost permissions);
   - the `cost_known` backfill counts.
3. `roles:backfill-rollout --business=<id>` on the rehearsal (dry run): the report, and a re-run that changes nothing.
4. **Then** `permissions:sync` on the rehearsal. Check:
   - a manager copy has `cash-registers.payout`, `sync-issues.accept` and `sync-issues.discard`, and not `sales.void`;
   - a clerk copy has no cost permission;
   - a permission a business removed before this stage stays removed.
5. `sales:restock-voided --business=<id>` (dry run, then `--apply`). Compare stock per variation before and after.
6. Run the read-only checks:
   - `stock-adjustments:list-unapplied --business=<id>`: 16 expected;
   - the same with `--reason=returned_to_supplier`: none expected;
   - `invoices:report-series --business=<id>`: 1,503 numbers in `R1` series and one other, unchanged.
7. On UAT, the user runs, in this order: the migrations, then `permissions:sync` (**never the reverse**), then the
   repair, then `stock-adjustments:list-unapplied` for each business. The owner applies or rejects each listed
   adjustment on its page.
8. **Background work.** Both must be running, or nobody is reminded, void requests never expire, and no approval or
   refund-owed notification is delivered:
   - `schedule:run` every minute (it runs the hourly `approvals:remind`, which also expires void requests after 48
     hours);
   - a queue worker whose `--queue=` list includes `default`, which is where approval and refund-owed notifications are
     queued, e.g.
     `php artisan queue:work --queue=default,whatsapp-notifications,order-notifications,baileys,ecommerce --tries=3`.
9. **Server before app.** Every sync change is an addition, so the app in shops keeps working. Leave
   `SYNC_MIN_VERSION_DISPOSITIONS` empty until the paired app ships, then set it to that version.

### App checklist (the paired Flutter release)

From [app-release-checklist.md#stage-10](../saas/stages/app-release-checklist.md#stage-10). The app release isn't
scheduled.

**Sync and outbox**
- Sends `X-App-Version` on every push, pull and pairing request.
- Follows `disposition`. Handles `pending` and the `*_not_synced` codes.
- Clears items in `resolved_items`, and items answered `applied` or `discarded`.
- Pushes `outbox_item_discarded` for a discarded item and each item queued behind it.
- Offers discarding a held sale or payment only to someone holding `sales.void`. Never offers "accept" at the till.
- Sends `outbox_count`. Never deletes a local store with unsynced items. Asks before signing out or switching business
  with unsynced items. Confirms on 422 `till_has_unsynced_items` at pairing.
- Shows the sync review's outcome for a held sale.

**Voids**
- Shows Void when `voidable` is true and the person holds `sales.void`. Calls the online void, queues `sale_void`
  offline, and reads `sale_voids`.
- Handles `void_window_closed`, `sale_has_returns`, `sale_already_voided` and 403.
- Shows the `money` a void answers.
- Warns, without blocking, "N units pending removal".
- Void requests: someone without `sales.void` requests; approvers list, approve and reject.

**Sales, products and adjustments**
- Hides Delete on completed sales and handles its 422 codes. A deleted sale is a 404.
- Shows `cost_price: null` without breaking. Hides cost fields and product editing by permission.
- Handles 403 on product writes. Creates products only online, and can't sell an unknown barcode offline.
- Stock adjustments, if the app makes them: no "Returned to supplier", signed changes and `variation_id`, and expects
  them to wait for approval.

**Invoice numbers**
- **Never assumes the invoice number's format:** treats it as an opaque string.
- Numbers offline sales only in its own register's series.

### Test results

(Run on the branch at the head of this PR.)

| Check | Result |
|---|---|
| `vendor/bin/pint --test --diff=origin/main` | pass |
| `php artisan test --compact` (SQLite) | 2,419 passed, 62 skipped, 4 todos (8,644 assertions), 2,169 s |
| `vendor/bin/pest --configuration=phpunit.mysql.local.xml --compact` (MySQL 8) | 2,430 passed, 1 failed, 50 skipped, 4 todos (8,738 assertions), 3,378 s. The failure was `LiveLikeDatasetTest`'s build-twice check: `StockIntakeFactory`'s Faker `intake_date` read the real clock, so two builds straddled midnight. A pre-existing flake, fixed by pinning the date in `LiveLikeDatasetSeeder`; `LiveLikeDatasetTest` on MySQL then passed 13 of 13 |

New test files:
- `PermissionRolloutTest`
- `ClerkCostsTest`
- `SellingShopTest`
- `StockAdjustmentsTest`
- `AdjustmentApprovalsTest`
- `VoidPermissionAndWindowTest`
- `VoidRequestsTest`
- `StockReleaseTest`
- `VoidStockAndMoneyTest`
- `DeleteSaleTest`
- `VoidButtonTest`
- `StoreCancellationTest`
- `TillVoidTest`
- `ApiV1VoidContractTest`
- `SyncDispositionsTest`
- `HeldItemsTest`
- `InvoiceSeriesTest`
- `CostKnownTest`
- `RepairCommandsTest`

`ResourceMatrixTest` has rows for every new web, flat API and `/api/v1` route. `FlatApiContractTest`,
`ApiV1ContractTest` and `LiveLikeDatasetTest` pass unchanged.

### Before merge

- **UAT rehearsal** (rollout steps 1–6). Not run: the rehearsal needs a fresh UAT snapshot, and existing records were
  not checked during the work. The one open acceptance criterion depends on it: the 16 approved-but-unapplied
  adjustments being listed.
- **`php artisan mobile:contract-diff`** not run: the Flutter app isn't on this machine (`MOBILE_APP_PATH`). The
  server changes are additions, and the contract tests pass unchanged.
- **Open for stage 14:** a shop can have two open sessions, and collected payments aren't tied to a session. Stage 10
  pays from the voider's own session; stage 14 decides its own rule.
- **Known, out of scope:** the daily sales report has no view (pre-existing).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
