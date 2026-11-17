# Stage: Test Suite: Speed, Edge Cases and Journeys

> **Source:** the review of the tests, 17 November 2026: both full suites run with per-test timings, and the test titles
> and the code they exercise read for edge cases and end-to-end flows.
> **Branch:** `chore/test-suite`, from `main` · **Depends on:** nothing; step 1 can start now. Steps 2e and 3 (journey 1)
> are written after stage 11 merges, since quantities become decimal and units arrive there.
> **Can run alongside:** every stage · **Traceability:** [TRACEABILITY-tests.md](./TRACEABILITY-tests.md)

---

## Revisions

**Created 17 November 2026** from the test review. It covers why the MySQL run takes 35 to 47 minutes, the edge cases
the tests don't reach, the end-to-end flows nobody chains together, and enhancements to how the tests are built. Test
items that belong to another stage's code stayed in that stage (stage 17, step 4 for flaky tests; stage 25, steps 5 and
6 for the deadlock and the isolation placeholders), with a pointer here.

---

## Goal

The suite is fast enough to run before every push without batching. It reaches the failures that happen in a shop
(two people at once, a slow or failing store, a shop's midnight, a customer overpaying, a browser total that differs
from the server's) and not only the ones that happen in a test. Whole journeys run from signup to erasure and from a
paired till to a settled sale, and each ends by checking that the books balance.

## Before you start

The suites don't pass on `feat/stock-units-batches` today (4 failures on SQLite and 5 on MySQL, 17 November 2026:
`MigrationBaselineTest`, `SalePerShopAttributionTest` twice, `OnboardingTest`, and on MySQL `FlatApiContractTest`).
They belong to stage 11 and aren't investigated here. Step 1's baseline is taken on a green `main` (stage 10a's
recorded result: SQLite 2,468 passed, MySQL 2,480 passed, 0 failed). Six points are open (see "Points to confirm");
point 2 blocks step 3 only, point 4 blocks step 1e only.

## Decisions that apply

- **Tests never touch a database that isn't a `_testing` one** (CLAUDE.md, the live-data rule). Anything this stage adds,
  including per-worker databases, keeps `TestCase::assertSafeTestDatabase()` and the safety hook honest, with cases for
  both.
- **No test is deleted without approval** (CLAUDE.md). Moving a test to another group, or replacing a placeholder, is
  not deleting; removing a legacy runner script is, and waits for point 5.
- **Every full run still happens before a push** ("Before pushing", CLAUDE.md; no CI). This stage shortens it and keeps
  the default run for each step small; it does not make a push skip anything.
- **A test that needs MySQL says so** (`->skip(fn () => DB::getDriverName() !== 'mysql', …)`, as today).
- **Journeys and browser tests are groups** (`journey`, `browser`, `slow`), so each can be run, or left out, by name.

## Relevant current state

Measured 17 November 2026 on `feat/stock-units-batches`, both full suites running at once on 8 cores and 14 GB (so times
are inflated; PROGRESS.md recorded about 35 minutes for MySQL on `main`). `junit-sqlite.xml` and `junit-mysql.xml` are
in the scratchpad of that session; the numbers are recomputed in step 1.

| | SQLite | MySQL 8 |
|---|---|---|
| Tests | 2,588 (62 skipped, 4 todo) | 2,588 (50 skipped, 4 todo) |
| Wall time | 26 min | 47 min |
| Median / mean test | 0.24 s / 0.60 s | 0.28 s / 1.09 s |
| Fastest 90% of tests | 17.0 min | 18.8 min |
| Six tests over 30 s | none | 17.7 min together |

**Where the time goes (MySQL):**
- `LiveLikeDatasetTest`: 9.1 min in 13 tests; "builds the same data every time" 330 s and "covers what a system in use
  holds" 213 s, each building the dataset in its own process.
- `InvoiceNumberConcurrencyTest`: 7.1 min in 3 tests (114, 133 and 178 s), MySQL only.
- `Unit/Actions/Shop/CreateShopActionTest`: one test, "creates shop without integration config", 96 s. Cause unknown.
- **Role seeding:** 82 files call `seedRolesAndPermissions()` (`PermissionSeeder::insertMissing()`, 218 permissions, then
  `RoleSeeder`: six templates, every business copy) before every test: 880 tests. On SQLite these average 1.37 s
  against 0.21 s for tests that don't, 20 of the 26 minutes. On MySQL the tests that don't seed also average 1.08 s,
  about five times SQLite; why isn't measured.
- **Per test, always:** `RefreshDatabase`'s transaction, a `Business::factory()` ("Test Business"), and a delete of the
  `audit_logs` table (`tests/Pest.php`).
- **No parallelism:** `pestphp/pest` is installed without paratest; one process runs everything.
- Heavy single tests also include `CanaryCrawlTest` (5 tests, 9 s each) and `Navigation/SidebarTest` (up to 7.5 s).

**Coverage, from the test titles and the code (a lead, not a proof):**

| Area | What exists | What's missing |
|---|---|---|
| Concurrency | 3 process-based tests, all invoice numbers | `lockForUpdate` is used in 16 places (stock ledger, voids, approvals, credit, pairing, platform payments, sync); none has a concurrent test |
| Queues | `QUEUE_CONNECTION=sync`; 8 titles mention a job failing | retries, backoff, `failed()`, which queue a job lands on |
| External failures | `Http::fake` in 23 files | no timeout, 5xx or 429 from a store, Meta, WhatsApp, SMS or an AI driver |
| Time | 5 titles (void window, invoice series, expiry) | reports and cash sessions across a shop's midnight; a shop whose timezone differs from the business's |
| Payments | `collectPayment()` and credit ledger tests | a customer paying more than the total (change), split tender, an exact credit limit |
| Money | 76 VAT titles, 27 on rounding | many lines under inclusive and exclusive VAT with discounts and fees; the request's maximums (quantity 10,000, price 1,000,000, fees 10,000) |
| Scale | none above a few hundred rows | lists, exports and pulls with thousands |
| Hostile input | 13 titles on long or Unicode input | user text rendered unescaped (`{!! !!}` is used 4 times, for the receipt header and footer); markup in names |
| Who can open what | `ResourceMatrixTest` (tenancy), `SidebarTest` (menu) | a role-by-route table, so a change to who can open a page shows as a diff |
| Browser | none | the POS screen (1,056 lines, inline JavaScript), BS5 modals, expired-form (419) handling |
| Journeys | strong tests per module; `LiveLikeDatasetSeeder` sells through the real paths | no test chains signup to erasure, or a paired till from offline sale to settled; nothing checks the books balance at the end |
| Skips | 50 to 62 by database | `SafetyHookTest` always skips in lerd (no `jq`), so the hook's 49 cases never run in the usual environment |

**Test hygiene seen on the way:** randomness in factories has already produced flaky tests three times (stage 0's
notification failures, `SupplierTest`'s 0.90 ratio, `ShopFactory` codes, and `CustomerFactory`'s random customer type
that made a credit test a coin flip, CLAUDE.md); three non-Pest runner scripts sit in `tests/`
(`integration-test-script.php`, `run-integration-tests.php`, `run-product-sync-tests.php`).

---

## Work

### 1. Speed
- [ ] **Measure first, and keep the measuring.** A script `tests/Support/junit-summary.php` reads a JUnit file (Pest's
      `--log-junit`) and prints the totals, the slowest 25 files and tests, and the time over a threshold; the pre-push
      section of CLAUDE.md names it. The baseline of the green `main` goes in the PR body.
- [ ] **1a. A `slow` group** for the tests over 30 s: the three concurrency tests, `LiveLikeDatasetTest`'s two builds and
      `CreateShopActionTest`'s case until 1d explains it. `phpunit.xml` and both MySQL files exclude the group by default
      (as `api-contract-record` is); the pre-push commands run it once, on MySQL, with `--group=slow`. A step's targeted
      run never includes it.
- [ ] **1b. Seed once per run, not per test.** The permissions and the six role templates are seeded once, right after
      the schema is built and outside any test's transaction; `seedRolesAndPermissions()` and `ownerRole()` then find
      them and return at once. Tests whose subject is the seeding itself (`PermissionRolloutTest`, `NewPermissionsTest`,
      `RoleTemplatesTest`, `CustomerGrantsRolloutTest`, `ClerkCostsTest`, the role console tests) opt out with a helper
      `freshRoleSeeding()` that empties the role tables inside their transaction. The files that fail when the seeding
      is shared are found by running the suite once (expected: tests counting roles or permissions); each is fixed or
      opts out, not worked around.
- [ ] **1c. Find out why an ordinary MySQL test costs 1.08 s.** Log the queries of 20 sampled plain tests
      (`DB::listen`, once per file, not committed), then reduce what shows: the "Test Business" and its subscription and
      default shop built once per file where the tests only read them, the per-test `audit_logs` delete replaced by a
      truncate-free check (`audit_logs` only when the test used the audit connection), Spatie's permission cache rebuilt
      once per test rather than per call. The target is stated by the measurement, not assumed.
- [ ] **1d. `CreateShopActionTest`'s 96 s** is explained (a real outbound call waiting for a timeout is the first thing
      to rule out; stage 25, step 5 adds the timeouts) and fixed, so the case leaves the `slow` group.
- [ ] **1e. Parallel runs** (pending point 4). `pestphp/pest`'s parallel mode (paratest) with one database per worker
      (`pos_saas_testing_1` … `_8`, created once by a script, never by the tests). Changes it needs:
  - `TestCase::assertSafeTestDatabase()` accepts a name ending `_testing` or `_testing_<n>`; the safety hook and its
    cases (`tests/Fixtures/safety-hook-cases.php`) get the same rule, with a case that still refuses `pos_saas_demo`;
  - the tests that share a named database or a file (`dataset_testing`, `import_testing`, `rehearsal_testing`, the
    audit file, the files disk) join a `serial` group run in a second pass after the parallel one;
  - `phpunit.mysql.local.xml` carries the port override as today; CLAUDE.md's "Before pushing" commands are rewritten
    (step 6).
- [ ] **1f. The dataset tests do less.** "Builds the same data every time" runs at the smallest scale that still
      touches every table (the test already lists what it covers); the two builds it needs are reused by "covers what a
      system in use holds" instead of a third build; the contract tests keep their own scale.
- [ ] Tests: `TestCaseGuardTest` (new or extended) covers the per-worker names; `SafetyHookTest` gains its cases.

### 2. Edge cases
Each item names the test to write and the failure it would catch. MySQL-only tests say so; the rest run on both.

#### 2a. Concurrency (MySQL)
- [ ] **A cheaper harness first.** The three existing tests take 114 to 178 s; the cost (a full `artisan` boot per worker
      is the likely one) is measured and the harness changed so a concurrent case costs seconds: workers started once
      from one bootstrap script, N sales each, results read from the database.
- [ ] Cases, each with several workers released together:
  - two sales of the last unit: one succeeds, the other is a negative-stock sale as decided in stage 11 (or refused),
    never both costed from the same layer, and `shop_variation` never goes below what the ledger says;
  - two sales of the same two items in opposite line order both succeed (the deadlock gap, stage 25, step 5, whose test
    this is);
  - two people void the same sale: stock released once, one refund owed;
  - two approvers approve the same adjustment, void request or refund owed: the first wins, the second is refused,
    stock moves once;
  - two credit sales that together pass the credit limit: one is refused; the balance equals the transactions;
  - the same push item delivered twice at once: one sale, the second `applied`/`already_applied`;
  - two devices pair one till at once: one pairs, the other gets the unsynced-items or already-paired answer;
  - a platform payment applied twice at once: the subscription extends once.

#### 2b. Queues
- [ ] A test lists every queue name a job or notification in `app/` uses and fails when one isn't in the documented
      worker list (`composer.json`'s `dev` script and CLAUDE.md), the mistake that silently stops WhatsApp.
- [ ] A group `queue-database` runs a few critical flows with `QUEUE_CONNECTION=database` and a worker
      (`queue:work --stop-when-empty --queue=<the list>`): a completed sale delivers its invoice message, an online
      order webhook creates its sale, a stock push goes out. Nothing sits in `jobs` afterwards.
- [ ] Each job's `$tries`, `$backoff` and `failed()` are asserted (the seven jobs without `failed()` get it in
      stage 25, step 5; this checks it, and that `failed()` records what a person will see).
- [ ] A job dispatched inside a business carries it to the worker (`RestoresBusiness`), including a job dispatched
      inside `TenantContext::run()`.

#### 2c. External failures
- [ ] For WooCommerce, Shopify, Meta/WhatsApp, the SMS service, the AI drivers and the social drivers, `Http::fake`
      answers with a connection timeout, a 500, a 429 with `Retry-After` and malformed JSON. Asserted per caller:
      a sale still completes; the failure becomes a `SyncIssue`, a failed message or a fallback as the code intends;
      no secret is logged; the next attempt works after the store recovers.
- [ ] A WooCommerce or Shopify webhook with a wrong signature, a replayed one, an old one and an oversized body
      (the three handlers already fail closed; this pins replay and size).

#### 2d. Time
- [ ] A shop in `Pacific/Auckland` and a business in `Africa/Nairobi`: with the clock set by `travelTo`, a sale at the
      shop's 00:30 on 1 January takes the new year's series; the day's sales report, the cash session's day, the void
      window `end_of_day` and the nightly snapshot all use the shop's date, not the server's or the business's.
- [ ] A cash session open across the shop's midnight: reports count its sales on the day they happened.
- [ ] Exact boundaries: a void request valid 48 hours (at 47:59:59 and 48:00:01), an approval reminder at 24 hours, a
      trial ending on its last second, a verification grace of 24 hours, a subscription lapsing on its end date, a
      void window of 1 minute.
- [ ] No test depends on the real clock: a scan fails on `now()` or `Carbon::now()` in a test file without a frozen
      clock (the intake factory's date was one cause of a failure, stage 10).

#### 2e. Money (written after stage 11)
- [ ] **Property tests.** A Pest dataset of 200 seeded random carts (the seed printed on failure) per pricing mode
      (inclusive and exclusive VAT, VAT-registered and not): lines with decimal quantities and units, a line discount,
      a sale discount, delivery, packaging and other fees. For each, the identities hold: lines + tax + fees − discount
      = total; `total_profit = total − tax − total_cost`; payments + credit = total; the VAT summary equals the lines;
      the invoice PDF, the receipt and the API response show the same total.
- [ ] **The request's limits:** quantity 0.001 and 10,000, price 0 and 1,000,000, fees 10,000, notes 1,000 and 1,001
      characters, a line count of 1 and of 200: each accepted or refused as the rule says, and none a 500.
- [ ] **Paying:** more than the total (change due is shown and never booked as income), exactly the total, a second
      payment on a part-paid sale that overpays the balance, a payment on a voided or returned sale, a credit sale at
      exactly the limit and one cent over, a cash payment after the register closed.
- [ ] **Returns:** a return of part of a line twice, a return after a void (refused), a return costing the original
      layer's cost after a price change, a return in a different unit than the sale.

#### 2f. Scale
- [ ] A dataset of 2,500 sales, products and customers (built once per run, in the `slow` group's database or a fixture)
      for: every paginated list returns the right page counts and no N+1 (a query-count assertion per list, such as
      `DB::getQueryLog()` bounded by a constant); the sync pull pages 20,000 products in a stable order (stage 17, step 2
      adds the indexes; this is its "same order before and after" test); a CSV export streams under a memory ceiling
      (`memory_get_peak_usage()` delta), so stage 20's row cap has something to prove.

#### 2g. Hostile input
- [ ] **A markup canary** in `tests/Support/Canaries.php`: a product, customer, supplier, shop, note and receipt header
      each carry `"><script>window.__x=1</script>` and an emoji and 255-character name; `CanaryCrawlTest` asserts no page
      or PDF contains the raw script, and none fails. (The four `{!! !!}` uses are checked, since they sit on user text.)
- [ ] Values that look like SQL, a leading `=` (a spreadsheet formula) in a name that reaches a CSV export (prefixed
      with `'`), a null byte, and a name that is only spaces.

#### 2h. Who can open what
- [ ] **A permission matrix recorded like the contract**: `tests/Fixtures/permission-matrix.json` holds, for each
      template role (owner, manager, cashier, inventory-clerk, accountant, customer) and each web and `/api/v1` route
      that is not public, the status the role gets (200/302 or 403). A test replays it against a seeded business and
      fails on any difference, so a change to who can open a page is a visible diff in a PR, re-recorded on purpose
      (a `--group=permission-matrix-record`, as the contract fixtures are).

#### 2i. Skips and placeholders
- [ ] `SafetyHookTest`'s 49 cases run in the usual environment (point 3: `jq` in the lerd container, or the pre-push
      script runs them on the host and prints that it did).
- [ ] The `->todo()` isolation tests are stage 25, step 6; `CanaryCrawlTest::BROKEN_ROUTES` is stage 25, step 1. This
      stage only checks, at the end, that both are empty.

### 3. End to end

#### 3a. Journeys (HTTP level, group `journey`)
Each journey is one test that chains the real controllers and jobs, with the clock frozen and `Http` faked, and ends
with `Invariants::assertBooksBalance($business)` (step 4). They run in the default MySQL run only if each is under
about 10 seconds; otherwise they join `slow`.
- [ ] **J1, a business from signup to its first report:** sign up, verify the email, start the trial, add a shop, add
      products with units, receive stock, sell on the web POS (cash, then credit to a wholesale customer), collect a
      part payment later, return part of one sale, void another, close the register, check the profit report equals the
      ledger's cost and the VAT report equals the lines, export the data.
- [ ] **J2, a till offline:** pair a register, sell three times offline with the register's series, push the items,
      push them again (nothing doubles), pull on a second till, void one from the till, a held item accepted on the sync
      review, a till payment by M-Pesa confirmed by the fake gateway.
- [ ] **J3, an online order:** a WooCommerce webhook creates an order, it converts to a sale, stock goes down and the
      store is pushed, the order is cancelled in the store, the sale voids itself, stock comes back, no refund is owed
      for store money.
- [ ] **J4, the subscription's life:** trial, lapse into read-only (reads and export still open), a recorded payment
      restores writing, a later lapse closes the business, the export is emailed, erasure happens 30 days after the
      email and touches nothing of another business, its files or its audit rows.
- [ ] **J5, one person, two businesses:** switch on the web, an API token bound to each, each till syncs only its own,
      removal from one leaves the other intact, a removed member can only come back by invite.
- [ ] **J6, an approval:** a clerk files an adjustment above the threshold, the approvers (and no one else) are
      notified, the first approves, stock moves once, the audit trail has both rows, the clerk is told, the reminder
      doesn't fire for a settled request.

#### 3b. The browser (group `browser`, pending point 2)
A small set, because each test is slow and the PHP tests already cover the server.
- [ ] The POS: add two items, change a quantity, apply a discount, pick a payment method: the total on screen equals the
      receipt's and the database's, under inclusive and exclusive VAT.
- [ ] An out-of-stock item asks for the oversell reason (stage 11) and records it.
- [ ] Credit for a retail customer is refused in the page with the server's message.
- [ ] The Void sale confirmation (a Bootstrap 5 modal): needs a reason, names the items, posts once on a double click.
- [ ] An expired form (419) returns to the form with what was typed, passwords excepted.
- [ ] Each role's sidebar shows only what it can open (the server-side `SidebarTest` stays; this is the one check on the
      rendered page).
- [ ] They run as `--group=browser`, outside the default and the pre-push runs until point 2 is answered.

#### 3c. The app
- [ ] `php artisan mobile:contract-diff --fail-on-drift` joins the pre-push list when the app is on the machine
      ([app-release-checklist.md](./app-release-checklist.md), first item); until then the pre-push output says it was
      not run.

### 4. Making the tests better
- [ ] **An invariants helper** `tests/Support/Invariants.php`, `assertBooksBalance($business)`: for every shop and
      variation, `shop_variation.quantity` equals the sum of its movements; unconsumed cost layers add up to positive
      stock; every sale's total equals its payments, credit and refunds owed; each credit account's balance equals its
      transactions; invoice numbers are unique and gapless per series; a cash session's expected balance equals its
      opening, payments, payouts and expenses; no row references another business's row. The journeys call it, and so
      does `LiveLikeDatasetTest` on the 3,000-sale dataset it already builds (the one place that sees many sales).
- [ ] **A seeded Faker per test.** A `beforeEach` seeds Faker from `crc32` of the test's name plus `TEST_SEED` (default
      fixed); a failure prints the seed, so a random failure reproduces. The factories' random choices that tests depend
      on (`CustomerFactory::customer_type`) become deterministic defaults with named states, as CLAUDE.md already
      advises.
- [ ] **A random-order run for the whole suite**, not only `BusinessErasureTest` (stage 17, step 4): the script runs
      `--order-by=random` with a printed seed, recorded in the PR when it fails; run weekly by hand.
- [ ] **Coverage of the money and stock core**, to find classes no test reaches: a run with a coverage driver (PCOV or
      Xdebug in lerd's PHP) over `app/Services/Stock`, `app/Services/Billing`, `app/Actions/Sale`, `TaxService`,
      `InvoiceNumberService` and `app/Support/Sales`; classes below an agreed share are listed in the PR, not gated.
- [ ] **A trial of mutation testing** (Pest's `--mutate` with `covers()`) on `StockLedger`, `TaxService`,
      `InvoiceNumberService` and `CalculateSaleTotals`: the surviving mutants show which assertions are missing. The
      score is recorded; nothing is gated (point 6).
- [ ] **Scenario builders** in `tests/Support` (`aShopWithStock()`, `aCashier()`, `aWholesaleCustomer()`) replace the
      repeated `beforeEach` setup in the largest files, so a change to what a shop needs is made once.
- [ ] **The legacy runner scripts** (`tests/integration-test-script.php`, `run-integration-tests.php`,
      `run-product-sync-tests.php`) are read; each is ported to Pest or removed, with approval (point 5).
- [ ] **A time budget in the output.** The pre-push commands print the junit summary, so a test that grows past 10 s
      shows up where it is written.

### 5. Housekeeping
- [ ] **CLAUDE.md's "Before pushing"** is rewritten for the new commands (parallel run, the `slow`, `journey` and
      `serial` groups, the summary script) and its note that the suite takes minutes is corrected.
- [ ] **`pos-domain-rules`** and the `laravel-testing` skill gain: seed once, the groups, the invariants helper, the
      seeded Faker, and "a test that needs a clock freezes it".
- [ ] **`phpunit.xml` and both MySQL files** carry the group exclusions identically; `TestEnvironmentTest` (stage 0)
      asserts they do.

## Contract

None. Only tests, test support and the test configuration change; no route, response or migration changes. The
`permission-matrix.json` fixture is new and recorded the way the contract fixtures are.

## Schema

None. Per-worker test databases (step 1e) are created by a script on the local MySQL server only.

## Acceptance criteria

- [ ] The full MySQL run is at most the target of point 1, and the default run (without `slow`, `journey` and
      `browser`) at most the other, both measured on the same machine as the baseline and in the PR body.
- [ ] No test takes more than 30 s outside `slow`; the summary script prints the slowest 25.
- [ ] `seedRolesAndPermissions()` runs once per process; every test that depends on shared seeding is known by name.
- [ ] The concurrency cases of step 2a pass on MySQL 20 runs in a row.
- [ ] Every outbound call in step 2c has a failure test; every job has its `failed()` asserted.
- [ ] The time cases (2d) pass with a shop and a business in different timezones.
- [ ] The property tests pass for 200 seeded carts in each pricing mode.
- [ ] `assertBooksBalance` passes after every journey and after the live-like dataset's build.
- [ ] The permission matrix is recorded and replayed; a deliberate permission change makes it fail.
- [ ] `SafetyHookTest`'s cases run in the usual environment, or the pre-push script says plainly that it ran them on
      the host.
- [ ] A failing random test prints its seed and reproduces with it.
- [ ] `BROKEN_ROUTES` and the isolation todos are empty (stage 25).
- [ ] Pint and both full suites pass.

## Risks

- **Seeding once hides a test's dependence on a clean database.** Mitigation: step 1b names every file that fails when
  it is shared; the seeding tests opt out; a random-order run (step 4) catches the rest.
- **Parallel runs share something nobody noticed** (a file, a cache key, a fixed port). Mitigation: the `serial` group
  and a comparison of results with the single-process run before the old way is dropped.
- **Per-worker databases weaken the live-data guard.** Mitigation: the guard accepts only the two suffixed patterns,
  and a test fails on any other name.
- **Concurrency tests are flaky by nature.** Mitigation: each case asserts an invariant that holds under any
  interleaving (not a particular order), and runs 20 times in a row before it is accepted.
- **Browser tests are slow and break on markup changes.** Mitigation: eight tests at most, selected by role and label,
  outside the default run.
- **Property tests find real bugs on day one.** That is the purpose; each finding is its own fix, with its own stage
  or hotfix, not folded into this one.

## Rollout

None to UAT: nothing here runs against `pos-saas`. The per-worker databases are created on the local MySQL server only.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL, with the new groups) pass, and their results and times are in the PR body.

## Points to confirm

1. **The time targets.** Proposed: the default MySQL run under 10 minutes and the full pre-push run (both suites, every
   group but `browser`) under 25. Are those right, or is a different budget wanted?
2. **The browser tool.** Pest 4's browser plugin needs Playwright and Node in lerd's PHP environment; Dusk needs Chrome.
   Which can run on this machine? Recommended: Pest's plugin, if Playwright installs here; otherwise leave step 3b
   unscheduled and keep the server-side checks.
3. **`jq` in lerd's container** so `SafetyHookTest` runs. Can it be added, or should the pre-push script run the hook's
   cases on the host and say so?
4. **Per-worker databases on the local MySQL server.** Eight more databases named `pos_saas_testing_<n>` (created by a
   script, dropped by none). Acceptable on the lerd MySQL, and is a user with rights to create them available?
5. **The three legacy runner scripts** in `tests/`: port them to Pest, or remove them? Removing needs your approval.
6. **Is mutation testing worth trying?** It needs a coverage driver in lerd's PHP and is slow. Recommended: try it once on
   the four named classes; keep it only if it finds missing assertions.

## Follow-ups (not this stage)

- A CI job, if CI returns: the groups and the parallel run are what it would run (`CLAUDE.md`: no CI today).
- Visual-regression screenshots for the receipt and invoice PDFs.
- A nightly run on a restored UAT snapshot of the read-only checks (`Invariants`), as an operator command
  (`books:check --business`) rather than a test.
