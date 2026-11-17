# Traceability: Review of main, 17 November 2026

Findings from the review of `main` at `0e288c4` (after stage 10a), 17 November 2026. The suites weren't re-run for
the review; the last recorded result is stage 10a's (SQLite 2,468 passed, MySQL 8 2,480 passed, 0 failed).

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | `route:list` against the code | `stock-movements` `store`, `additions`, `deductions`, `stats`: methods don't exist | Stage 25, step 1 | planned |
| 2 | `route:list` against the code | `social-accounts.show`: method doesn't exist | Stage 25, step 1 | planned |
| 3 | `route:list` against the code | `inventory-snapshots` `generate`, `report/valuation`: methods don't exist | Stage 11c, step 3 | planned |
| 4 | `route:list` against the code | Five `dashboard/widgets*` routes: methods don't exist | Stage 20, step 1 (step 6 if built) | planned |
| 5 | Controllers against `resources/views` | `sales.pending`, `sales.completed`, `sales.daily-report`: views don't exist | Stage 25, step 1 | planned |
| 6 | Controllers against `resources/views` | `customers.credit-alerts`: view doesn't exist | Removed: stage 25, step 1; credit alerts: stage 18, step 3 | planned |
| 7 | Controllers against `resources/views`; `shops/show.blade.php` | `shops.users`: view doesn't exist, linked twice from the shop page | Stage 25, step 1 | planned |
| 8 | `route:list` against the code | `GET api/inventory`, `/shop/{shop}`, `/product/{product}`: `Api\InventoryController` is empty | Stage 25, step 2 | pending decision (stage 25, point 1) |
| 9 | `route:list` against the code | `GET api/inventory/low-stock`: method doesn't exist | Stage 11c, step 1 (already planned) | planned |
| 10 | `route:list` against the code | `POST api/register`: `Api\AuthController::register` doesn't exist | Stage 25, step 2 | pending decision (stage 25, point 1) |
| 11 | PROGRESS.md, stage 0 | `GET api/expenses/{expense}` answered 403 for the owner; not re-checked since | Stage 25, step 2 | planned |
| 12 | `app/Actions` | 35 empty action classes, none referred to | Removed: stage 25, step 3; expenses: stage 19, step 1; alerts: stage 18, step 3; reports: stage 20, step 1 | pending decision (stage 25, point 2) |
| 13 | `app/Services` | 8 empty services | Stage 25, step 3; `ExpenseService`, `ExpenseCategoryService`: stage 19; `NotificationService`: stage 18 | planned |
| 14 | `app/Models` | 15 empty models | Stage 25, step 3; stages 18, 19 and 20 for theirs | planned |
| 15 | `AppServiceProvider` | `MpesaGateway` bound to `NullMpesaGateway` | Stage 17, step 3 (already planned); stated at go-live: stage 24, "Go-live (production)" item 5 | pending decision (stage 17, point 1) |
| 16 | `AppServiceProvider` | `FiscalProvider` bound to `NullFiscalProvider` | Stage 22 (already planned); stage 24, item 5 | pending decision (stage 22, points 1 to 3) |
| 17 | `AppServiceProvider`, `config/billing.php` | `PaymentCollector` bound to `NullPaymentCollector`; payments recorded by hand | Stage 24, point 3 | pending decision |
| 18 | Stage 24 | Nothing built; its go-live date (about 12 November 2026) has passed | Stage 24, point 1 | pending decision |
| 19 | CLAUDE.md, `routes/console.php` | Nothing proves the scheduler and a queue worker with the named queues run in production | Stage 24, "Go-live (production)" item 4, point 2 | planned (checks only: deployment is outside the plan) |
| 20 | PROGRESS.md, every stage | `mobile:contract-diff` never run | [app-release-checklist.md](./app-release-checklist.md#review-of-main-17-november-2026) | planned (app not on this machine) |
| 21 | PROGRESS.md, stages 4 and 5 | The app doesn't handle 402 billing codes; owner role now `admin`; profile photo paths private | App checklist, same section (`business_suspended` is already under stage 10a) | planned |
| 22 | `config/sync.php` | `SYNC_MIN_VERSION_DISPOSITIONS` unset | App checklist, same section | planned |
| 23 | PROGRESS.md, Phase 7 "Open" | One till per shop | Stage 17, step 1 (already planned) | planned |
| 24 | PROGRESS.md, stage 1b follow-up | Sale transactions don't retry on a deadlock; rows locked in line order | Stage 25, step 5 | planned |
| 25 | PROGRESS.md, stage 1c follow-up | Online stores get the default variation only | Stage 25, point 3 and follow-ups | no work planned (e-commerce is outside the product's scope) |
| 26 | PROGRESS.md, stage 10 acceptance | 16 UAT adjustments approved and never applied | Stage 10 (open acceptance criterion; needs the rehearsal) | open |
| 27 | PROGRESS.md, stage 10a acceptance | UAT's members-without-a-shop counts unverified | Stage 10a (open acceptance criterion; needs the rehearsal) | open |
| 28 | `Phase6IsolationTest` | Four `->todo()` isolation tests | Stage 25, step 6 | planned |
| 29 | `resources/views` | 58 `@honeypot` for 297 forms | Stage 25, step 4 | planned |
| 30 | `ProductSyncController`, `WebhookController` | `$request->all()` | Stage 25, step 4 | planned |
| 31 | `app/Jobs` | 7 of 13 jobs have no `failed()` | Stage 25, step 5 | planned |
| 32 | `WhatsAppService`, `SMSservice`, the product sync services | Outbound HTTP calls without a timeout | Stage 25, step 5 | planned |
| 33 | `app/` | `whereDate()` on indexed columns, 31 uses in 14 files | Reports: stage 20, step 3 (already planned); the rest: stage 25, follow-ups | planned |
| 34 | CLAUDE.md | Says the API is unversioned, lists three security items as missing that exist, stale counts, contradicts itself on AI drivers | Stage 25, step 7 | planned |
| 35 | `.env.example` | Lacks keys `config/services.php` reads, and `TENANT_FILES_QUOTA_BYTES` | Stage 25, step 7 | planned |
| 36 | `CanaryCrawlTest::BROKEN_ROUTES` | Known broken routes are listed as exceptions, so the suite passes around them | Emptied as each is fixed: stages 25, 11c and 20 | planned |
