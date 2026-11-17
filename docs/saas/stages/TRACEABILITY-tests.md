# Traceability: Test Suite Review, 17 November 2026

Findings from the review of the tests: both full suites run with per-test timings on `feat/stock-units-batches`
(SQLite 26 min, MySQL 47 min, both at once), the test titles tallied by theme, and the code they exercise read for what
they miss. Coverage findings are leads from titles and code, not measured coverage.

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | MySQL run | Six tests take 17.7 of 47 minutes (dataset builds, invoice concurrency, one shop-action test) | Stage 26, steps 1a, 1d, 1f | planned |
| 2 | Both runs | 880 tests re-seed 218 permissions and the role templates each time: 20 of SQLite's 26 minutes | Stage 26, step 1b | planned |
| 3 | MySQL run | Ordinary tests average 1.08 s on MySQL against 0.21 s on SQLite; cause not measured | Stage 26, step 1c | planned |
| 4 | MySQL run | `CreateShopActionTest` takes 96 s in one case | Stage 26, step 1d (timeouts: stage 25, step 5) | planned |
| 5 | `composer.json` | No paratest; one process runs 2,588 tests | Stage 26, step 1e | pending decision (stage 26, point 4) |
| 6 | Titles, code | `lockForUpdate` in 16 places; three concurrent tests, all invoice numbers | Stage 26, step 2a; the deadlock case is stage 25, step 5's test | planned |
| 7 | `phpunit.xml` | `QUEUE_CONNECTION=sync`: retries, backoff, named queues and `failed()` can't fail a test | Stage 26, step 2b | planned |
| 8 | Titles | No timeout, 5xx or 429 from any store, Meta, SMS or AI service | Stage 26, step 2c | planned |
| 9 | Titles | Five tests on timezones and boundaries; no shop-vs-business timezone case; no report across a shop's midnight | Stage 26, step 2d | planned |
| 10 | `StoreSaleRequest`, titles | Change due, split tender, exact credit limit, the request's maximums untested | Stage 26, step 2e | planned (after stage 11) |
| 11 | Titles | Nothing above a few hundred rows; no query-count or memory assertions | Stage 26, step 2f; the pull's order test: stage 17, step 2 | planned |
| 12 | `{!! !!}` in four views, titles | User text isn't checked for escaping across pages and PDFs | Stage 26, step 2g | planned |
| 13 | `ResourceMatrixTest`, `SidebarTest` | Tenancy and the menu are tested; who can open each route is not recorded | Stage 26, step 2h | planned |
| 14 | `SafetyHookTest` | Skips inside lerd (no `jq`): 49 cases never run in the usual environment | Stage 26, step 2i | pending decision (stage 26, point 3) |
| 15 | `Phase6IsolationTest`, `CanaryCrawlTest` | Four todos; known-broken routes listed as exceptions | Stage 25, steps 1 and 6 (this stage checks they are empty) | planned |
| 16 | Whole suite | No chained journey (signup to erasure; a till from offline sale to settled) | Stage 26, step 3a (J1 to J6) | planned |
| 17 | `resources/views/sales/create.blade.php` | No browser test of the POS's JavaScript totals or the BS5 modals | Stage 26, step 3b | pending decision (stage 26, point 2) |
| 18 | Every stage | `mobile:contract-diff` never run | Stage 26, step 3c; the app checklist's first item | planned (app not on this machine) |
| 19 | Journeys, `LiveLikeDatasetTest` | Nothing checks the books balance (stock against movements, balances, series, sessions) | Stage 26, step 4 (`Invariants`) | planned |
| 20 | PROGRESS.md (stage 0, stage 10, `SupplierTest`, `ShopFactory`, `CustomerFactory`) | Random factory data has made tests flaky four times | Stage 26, step 4 (seeded Faker, deterministic defaults) | planned |
| 21 | PROGRESS.md, Phase 7 | `BusinessErasureTest` failed once, order-dependent | Stage 17, step 4; stage 26, step 4 extends random-order runs to the suite | planned |
| 22 | `tests/` | Three non-Pest runner scripts | Stage 26, step 4 | pending decision (stage 26, point 5) |
| 23 | Review | Classes in the money and stock core with no test, and weak assertions where there are tests | Stage 26, step 4 (coverage, mutation trial) | planned; mutation pending decision (point 6) |
| 24 | CLAUDE.md | "Before pushing" and the testing notes describe the old runs | Stage 26, step 5 | planned |
| 25 | `feat/stock-units-batches` | 4 SQLite and 5 MySQL failures on the working tree | Stage 11 (not investigated here) | open |
