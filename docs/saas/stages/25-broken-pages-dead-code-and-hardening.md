# Stage: Broken Pages, Dead Code and Hardening

> **Source:** the review of `main` at `0e288c4` (after stage 10a), 17 November 2026: every route checked against its
> controller method and view, the empty classes counted, and the security and robustness markers counted.
> **Branch:** `fix/broken-pages-dead-code`, from `main` · **Depends on:** nothing; can ship at any time (steps 1 and 2
> touch no stage's work; step 3 leaves to each owning stage what that stage will fill)
> · **Can run alongside:** every stage · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#review-of-main-17-november-2026) · **Traceability:**
> [TRACEABILITY-main-review.md](./TRACEABILITY-main-review.md)

---

## Revisions

**Created 17 November 2026** from the review of `main`. It takes the findings no other stage owns. Findings that
belong to a planned stage were added to that stage instead (11c, 18, 19, 20, 24 and the app checklist); the
traceability file lists every finding and where it went.

---

## Goal

No page or endpoint a signed-in person can reach fails with a 500 because its method or view was never written. No
class in `app/` is an empty shell that looks like the real thing. The remaining items of the security baseline and the
robustness basics are done, and the isolation suite has no placeholder tests left.

## Before you start

Nothing blocks steps 1, 4, 5, 6 and 7. Step 2 needs point 1 (whether the app calls the flat endpoints) and step 3
needs point 2 (remove or keep the empty classes other stages will fill).

## Decisions that apply

- **The flat `/api` never breaks** (README, rules for every stage): a recorded route is removed only when the app
  doesn't call it; otherwise it is made to work with the recorded shape.
- **Nothing pretends** (stage 20's recommendation): a route that can't do what it says is removed, not left failing.
- **The POS is the product** (plan, development principles): e-commerce, social and campaign code gets only what makes
  it safe, so their broken or empty parts are removed, not built.
- **Each empty class belongs to the stage that owns its domain** when one does (stages 18, 19, 20); this stage removes
  only those no stage will fill.
- **Every change keeps `CanaryCrawlTest`'s `BROKEN_ROUTES` honest:** a route fixed or removed here leaves that list,
  and the stage ends with the list empty except entries owned by a named later stage.

## Relevant current state

Checked against `main` at `0e288c4`, 17 November 2026 (read-only; `route:list` cross-checked against the code).

**Web routes whose controller method doesn't exist (always a 500):**

| Route | Action | Owner |
|---|---|---|
| `POST stock-movements` | `StockMovementController@store` | this stage, step 1 |
| `GET stock-movements/additions/list`, `deductions/list`, `stats/report` | `StockMovementController@additions`, `deductions`, `stats` | this stage, step 1 |
| `GET social-accounts/{social_account}` | `SocialAccountController@show` | this stage, step 1 |
| `POST inventory-snapshots/generate`, `GET inventory-snapshots/report/valuation` | `InventorySnapshotController@generate`, `valuationReport` | stage 11c, step 3 |
| `POST dashboard/widgets`, `POST dashboard/widgets/reorder`, `PUT`/`DELETE dashboard/widgets/{widget}`, `GET dashboard/widgets/{widget}/data` | `DashboardController@addWidget`, `reorderWidgets`, `updateWidget`, `removeWidget`, `widgetData` | stage 20, steps 1 and 6 |

**Web routes whose view doesn't exist (a 500 when opened):**

| Route | Name | View | Linked from |
|---|---|---|---|
| `sales/pending/list` | `sales.pending` | `sales.pending` | nothing found |
| `sales/completed/list` | `sales.completed` | `sales.completed` | nothing found |
| `sales/report/daily` | `sales.dailyReport` | `sales.daily-report` | nothing (stage 10, step 6 left it) |
| `customers/credit-alerts/list` | `customers.creditAlerts` | `customers.credit-alerts` | nothing found; stage 18 builds credit alerts |
| `shops/{shop}/users` | `shops.users` | `shops.users` | **`shops/show.blade.php`, twice** (lines 334 and 475) |

**Flat API routes whose method doesn't exist:**

| Route | Action | Owner |
|---|---|---|
| `GET api/inventory`, `api/inventory/shop/{shop}`, `api/inventory/product/{product}` | `Api\InventoryController@index`, `byShop`, `byProduct` (the class is empty) | this stage, step 2 |
| `GET api/inventory/low-stock` | `Api\InventoryController@lowStock` | stage 11c, step 1 |
| `POST api/register` | `Api\AuthController@register` | this stage, step 2 |

**Empty classes** (a constructor and nothing else; nothing in `app/`, `routes/` or `tests/` refers to any of them):
- 35 actions directly under `app/Actions/`: `AddSaleItem`, `AllocateCost`, `ApproveCreditLimitRequest`,
  `ApproveExpense`, `ApproveReturn`, `ApproveStockAdjustment`, `CompleteSale`, `CreateAlert`, `CreateCreditAccount`,
  `CreateCustomer`, `CreateExpense`, `CreateExpenseCategory`, `CreateSale`, `CreateStockAdjustment`, `ExportReport`,
  `GenerateOverdueNotice`, `GenerateReport`, `LogAuditEvent`, `MarkExpenseAsPaid`, `ProcessPayment`, `ProcessRefund`,
  `ProcessReturn`, `RecordCampaignConversion`, `RecordCampaignExpense`, `RecordCreditTransaction`, `RejectExpense`,
  `RequestCreditLimitIncrease`, `ResolveAlert`, `ScheduleReport`, `SendNotification`, `SubmitExpenseForApproval`,
  `UpdateCampaignMetrics`, `UpdateExpenseCategory`, `VerifyPayment`, `VoidSale`. Several share a name with the real
  code elsewhere (`App\Actions\VoidSale` is empty; the one that voids is `App\Actions\Sale\VoidSale`), which misleads.
- 8 services: `SaleService`, `PaymentService`, `CustomerService`, `ExpenseService`, `ExpenseCategoryService`,
  `NotificationService`, `DashboardService`, `CostingService`.
- 15 models: `AlertRecipient`, `CampaignConversion`, `CampaignExpense`, `CampaignMetric`, `CreditLimitRequest` (one
  reference), `CreditPayment`, `DashboardWidget`, `ExpenseApprovalHistory`, `ExpenseReceipt`, `NotificationLog`,
  `NotificationPreference`, `OverdueNotice`, `ReportExport`, `SaleItemStockAllocation`, `ShopPaymentMethod`.
- `Api\InventoryController` (an empty class with four routes).

**Security baseline** (CLAUDE.md's pending list, re-checked): the security-headers middleware, `Password::defaults()`
and forced HTTPS in production now exist. Still open:
- **Honeypot:** 58 `@honeypot` against 297 `<form` tags in `resources/views` (243 carry `@csrf`).
- **`$request->all()`:** `ProductSyncController` (lines 30 and 85, into `Validator::make`) and `WebhookController`
  (lines 92, 100 and 153, the whole payload handed to the queued jobs).

**Robustness:**
- **Jobs without `failed()`** (7 of 13): `FetchEcommerceOrdersJob`, `ProcessAbandonedCartWebhookJob`,
  `ProcessOrderWebhookJob`, `PublishCampaignPostJob`, `PushShopStockJob`, `RetryBlockedAiWork`, `SyncOrderStatusJob`.
- **HTTP calls without a timeout:** `WhatsAppService` and `SMSservice` set none; `ShopifyProductSyncService` (6 calls,
  1 `timeout(`) and `WooCommerceProductSyncService` (4 calls, 1) need reading to see whether the calls share a client.
- **Sale transactions don't retry on a deadlock** (PROGRESS.md, stage 1b follow-up): `DB::transaction` with one
  attempt; `StockLedger` locks one `shop_variation` row per line in line order, so two sales of the same items in a
  different order can deadlock and one fails.

**Isolation suite:** `tests/Feature/TenantIsolation/Phase6IsolationTest.php` holds four `->todo()` placeholders:
`/api/v1` resources (index, show, update, delete), the sync pull, the sync push carrying another business's ids, and
the M-Pesa payment webhook. Phase 7 built those surfaces; the placeholders were never replaced (the suites report
"4 todos").

**Documents outside this folder that no longer match the code** (corrected by step 7, when this stage ships):
CLAUDE.md says the API is flat and unversioned (34 `/api/v1` routes exist), that the security headers,
`Password::defaults()` and forced HTTPS are missing (all three exist), counts 88 actions and 109 API routes (147 and
158), and says in one place that only the `qwen` AI driver works and in another that all four do. `.env.example` lacks
`POSTMARK_API_KEY`, `RESEND_API_KEY`, the `WHATSAPP_*`, `META_*` and `GOOGLE_ADS_*` keys and
`TENANT_FILES_QUOTA_BYTES`.

---

## Work

### 1. Web routes that fail
- [ ] **`shops.users`:** build the view (the shop's members with their roles, from `User::inCurrentBusiness()` and the
      shop's assignments; `@can('update', $shop)`, as the staff card on the shop page since stage 10a), or remove the
      route and point the shop page's two links at People filtered by shop. Recommended: the second, if People already
      filters by shop; check first.
- [ ] **`sales.pending` and `sales.completed`:** redirect to `sales.index` with the status filter (the list already
      filters by status); the two controller methods go.
- [ ] **`sales.dailyReport`:** removed with its method (nothing links to it; stage 20's sales summary definition
      replaces it). `payments.dailyReport` is a different, working route and stays.
- [ ] **`customers.creditAlerts`:** removed with its method; stage 18 raises credit alerts and lists them on the
      alerts page.
- [ ] **`stock-movements`:** `store` removed (every stock change goes through `StockLedger`; a movement is never
      written by hand); `additions` and `deductions` redirect to `stock-movements.index` with the direction filter, or
      are removed if the index has none; `stats` removed (stock reports are stage 20 definitions).
- [ ] **`social-accounts.show`:** the route is excluded from the resource (`->except('show')`).
- [ ] `CanaryCrawlTest::BROKEN_ROUTES` and `ResourceMatrixTest` lose each entry fixed or removed; the entries left name
      their owning stage (11c, 20).
- [ ] Tests: each redirect lands on a page that renders; each removed route is a 404; a new test fails when any
      registered route names a controller method that doesn't exist or a controller renders a view that doesn't exist
      (the check this review ran by hand).

### 2. Flat API routes that fail (pending point 1)
- [ ] **`GET api/inventory`, `api/inventory/shop/{shop}`, `api/inventory/product/{product}`:** if the app calls them,
      built on `withStockOnHand()` and the shop access scope with the shape `docs/flutter/` documents; if it doesn't,
      removed with `Api\InventoryController`'s remaining methods left to stage 11c (`lowStock`). Either way the
      contract fixture's "known broken" entries are re-recorded on purpose.
- [ ] **`POST api/register`:** removed. Sign-up is the web flow of Phase 6 (business, trial, verification); a flat
      endpoint creating a user outside it would leave the user in no business.
- [ ] **`GET api/expenses/{expense}`** (PROGRESS.md, stage 0: 403 even for the owner): re-checked first, since stage 1a
      changed shop access; fixed here if still refused, else its contract entry re-recorded.
- [ ] `php artisan mobile:contract-diff` once the app is on the machine; the flat API contract test passes.

### 3. Empty classes (pending point 2)
- [ ] **Removed here** (no stage fills them): the actions `AddSaleItem`, `AllocateCost`, `ApproveCreditLimitRequest`,
      `ApproveReturn`, `ApproveStockAdjustment`, `CompleteSale`, `CreateCreditAccount`, `CreateCustomer`, `CreateSale`,
      `CreateStockAdjustment`, `LogAuditEvent`, `ProcessPayment`, `ProcessRefund`, `ProcessReturn`,
      `RecordCampaignConversion`, `RecordCampaignExpense`, `RecordCreditTransaction`, `RequestCreditLimitIncrease`,
      `UpdateCampaignMetrics`, `VerifyPayment`, `VoidSale`; the services `SaleService`, `PaymentService`,
      `CustomerService`, `DashboardService`, `CostingService`; the models `CampaignConversion`, `CampaignExpense`,
      `CampaignMetric`, `CreditPayment`, `SaleItemStockAllocation`, `ShopPaymentMethod`, and `CreditLimitRequest` once
      its one reference is read.
- [ ] **Left to their stage:** expenses (`CreateExpense`, `ApproveExpense`, `RejectExpense`, `MarkExpenseAsPaid`,
      `SubmitExpenseForApproval`, `CreateExpenseCategory`, `UpdateExpenseCategory`, `ExpenseService`,
      `ExpenseCategoryService`, `ExpenseReceipt`, `ExpenseApprovalHistory`): stage 19. Alerts and notifications
      (`CreateAlert`, `ResolveAlert`, `SendNotification`, `GenerateOverdueNotice`, `NotificationService`,
      `AlertRecipient`, `NotificationLog`, `NotificationPreference`, `OverdueNotice`): stage 18. Reports
      (`GenerateReport`, `ExportReport`, `ScheduleReport`, `ReportExport`, `DashboardWidget`): stage 20.
- [ ] **Tables stay.** Removing a model drops no table: the tables of removed models are listed with their row counts
      on UAT (read-only) in the PR, and dropping them is a later decision with its own migration.
- [ ] The unused `CostingMethod` enum goes with `CostingService` if nothing reads it (FIFO through `StockLedger` is the
      only costing; CLAUDE.md).
- [ ] Tests: `TenantScopeReflectionTest` and the suites pass with the classes gone; a new unit test fails when a class
      under `app/Actions` or `app/Services` has no public method besides its constructor.

### 4. Security baseline: what is left
- [ ] **Honeypot on every web form that changes something:** each `<form method="POST">` in `resources/views` carries
      `@honeypot` next to `@csrf`, except forms posted by signed-in JavaScript that can't carry it (listed with the
      reason in the test). A view test counts forms and fails on a POST form without both.
- [ ] **`ProductSyncController`:** the two inline validators become FormRequests; the controller reads
      `$request->validated()`.
- [ ] **`WebhookController`:** the payload handed to `ProcessOrderWebhookJob` and `ProcessAbandonedCartWebhookJob` is
      the verified raw body decoded once (`json_decode($request->getContent(), true)`), not `$request->all()`, so
      query-string parameters can't join a signed payload.
- [ ] Tests: a webhook with an extra query parameter queues a job whose payload doesn't contain it; the sync endpoints
      refuse an unlisted field.

### 5. Robustness
- [ ] **`failed()` on the seven jobs:** each logs with the business and shop, and where a person is waiting records it
      where they will see it (`ProcessOrderWebhookJob`, `FetchEcommerceOrdersJob`, `SyncOrderStatusJob`,
      `PushShopStockJob`: a `SyncIssue`; `PublishCampaignPostJob`: the post marked failed with the reason).
- [ ] **A timeout on every outbound call:** `WhatsAppService` and `SMSservice` get `timeout()` and
      `connectTimeout()` from config; the two product sync services' calls are read and given one where missing. A unit
      test scans `app/` for `Http::` chains without a timeout (allowed exceptions listed with reasons).
- [ ] **Sales retry on a deadlock:** `RecordSale` and the web POS's sale transaction run with
      `DB::transaction(…, attempts: 3)`, after moving anything that mustn't run twice (job dispatches, notifications)
      to after the commit; `StockLedger` locks a sale's `shop_variation` rows in id order, whatever the line order, so
      two sales of the same items can't deadlock on each other. Invoice numbers stay gapless (the number is claimed
      inside the transaction, so a retried attempt claims again after the rollback).
- [ ] Tests: on MySQL, two sales of the same two items in opposite orders, run concurrently, both succeed
      (the pattern of `InvoiceNumberConcurrencyTest`); a failed job leaves its record. The concurrency harness is made
      cheaper and the other concurrent cases (last unit, double void, double approval) are written in
      [stage 26](./26-test-suite-speed-edge-cases-and-journeys.md), step 2a; this stage's case is the one it starts from.

### 6. The isolation suite's placeholders
- [ ] `Phase6IsolationTest`'s four `->todo()` tests are written against what Phase 7 built: business B's records are
      refused on every `/api/v1` resource; a pull for A never returns B's rows; a push for A carrying B's ids is
      refused; an M-Pesa payment stays within the business whose account it names. Where `ApiV1/TwoBusinessSyncTest`
      already proves one, the placeholder is replaced by a pointer in the matrix and removed.
- [ ] The suites report no todos.

### 7. Documents (when the stage ships)
- [ ] **CLAUDE.md**, corrected to the code: the API has two surfaces (flat `/api`, unchanged, and `/api/v1` with the
      full envelope); the "Security baseline items still pending" line keeps only what step 4 leaves; the action,
      route and stub counts and lists; one statement on the AI drivers (the "only `qwen` and `null`" warning removed);
      the stub list shortened to what stages 18 to 20 still own.
- [ ] **`.env.example`:** `POSTMARK_API_KEY`, `RESEND_API_KEY`, `WHATSAPP_API_URL`, `WHATSAPP_API_TOKEN`,
      `WHATSAPP_PHONE_NUMBER_ID`, `META_APP_ID`, `META_APP_SECRET`, `META_GRAPH_VERSION`, `META_REDIRECT_URI`, the
      `GOOGLE_ADS_*` keys `config/services.php` reads and `TENANT_FILES_QUOTA_BYTES`, each empty with a one-line
      comment; a test fails when `config/` reads an `env()` key `.env.example` doesn't list.
- [ ] `pos-domain-rules` loses the stubs removed in step 3.

## Contract

- **Flat API:** `POST api/register` goes (point 1 confirms the app doesn't call it); the three `api/inventory` routes
  are built or go by the same point; everything else is unchanged. The contract fixtures are re-recorded on purpose for
  those entries only.
- **`/api/v1`:** unchanged.
- **Webhooks:** the same signature check; only what is passed to the job changes.

## Schema

None. No table is dropped (step 3).

## Acceptance criteria

- [ ] No registered route names a controller method that doesn't exist, and no controller renders a view that doesn't
      exist, except entries owned by stages 11c and 20, listed by name in the test.
- [ ] The shop page has no link to a page that fails.
- [ ] No class under `app/Actions` or `app/Services` is empty, except those stages 18, 19 and 20 fill, listed by name.
- [ ] Every POST form carries `@csrf` and `@honeypot` (exceptions listed with reasons).
- [ ] No controller passes `$request->all()` on.
- [ ] Every job has `failed()`; every outbound HTTP call has a timeout.
- [ ] Two concurrent sales of the same items in opposite line orders both succeed on MySQL.
- [ ] The isolation suite has no todos.
- [ ] The flat API contract test, the isolation suite and both full suites pass.

## Risks

- **The app calls a route removed here.** Mitigation: point 1 is answered from the app's code before step 2; until
  then step 2 isn't started.
- **A removed class is resolved by a string** (a config value, a morph map). Mitigation: a search for each class's
  short name across `app/`, `config/`, `database/` and `resources/` before removal; the full suites.
- **Retrying a sale transaction repeats a side effect.** Mitigation: dispatches and notifications move after the
  commit first, with a test that a retried sale notifies once.
- **Honeypot on a form a script posts** breaks the script. Mitigation: each such form is found by the view test and
  either gets the fields or is listed as an exception.

## Rollout (UAT)

1. No migrations. Snapshot before deploy as always.
2. Read-only row counts on UAT for the tables of the models removed in step 3; recorded in the PR.
3. Open the shop page, the sales list by status and the stock movements list on the rehearsal; none fails.
4. Check the server against the app's recorded requests (the flat API contract test on MySQL).

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

1. **Does the app call `GET api/inventory`, `api/inventory/shop/{shop}`, `api/inventory/product/{product}` or
   `POST api/register`?** They have answered 500 since before stage 0, so an app that called them would already be
   failing there. Recommended: remove all four unless the app's code shows a call; answered by reading the app
   ([app-release-checklist.md](./app-release-checklist.md#review-of-main-17-november-2026)).
2. **Remove the empty classes stages 18, 19 and 20 will fill, or leave them?** Recommended: leave those (listed in
   step 3) and remove the rest now; the owning stage fills or removes its own.
3. **Per-variation stock pushes to the online stores** (PROGRESS.md, stage 1c follow-up: the stores get a product's
   default variation only). Not planned here, by the plan's principle that e-commerce gets only what makes it safe.
   Confirm it stays a follow-up.

## Follow-ups (not this stage)

- Per-variation pushes to WooCommerce and Shopify (need the platforms' variant ids); point 3.
- Dropping the tables of removed models, once their UAT and production row counts are known to be zero.
- The 123 files on `main` that predate Pint (PROGRESS.md, stage 0 follow-up): a purely mechanical change on its own
  branch.
- `whereDate()` on indexed columns (31 uses in 14 files): the report queries are stage 20, step 3; the rest are
  rewritten as ranges when their file is next touched.
