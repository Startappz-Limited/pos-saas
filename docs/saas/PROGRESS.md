# SaaS Conversion Progress

Tracks each stage of [pos-saas-plan.md](pos-saas-plan.md), one row per committed step.
Stage files: [stages/](stages/README.md).

## Stage 0 (Phase 0): Development baseline

Branch: `saas/phase-0-baseline` (from `main` at `237bb7f`). Plan approved 13 October 2026.

### Starting result

Full suite on SQLite, 13 October 2026, before any stage work: **1,065 passed, 9 failed** (2,885
assertions). The same after `composer install` (step 1).

Known baseline failures (to be fixed in step 2):

| Test file | Test |
|---|---|
| `tests/Feature/SaleNotificationsTest.php` | SaleController WhatsApp notifications → sends SaleCompletedNotification (2 tests) |
| `tests/Feature/SaleNotificationsTest.php` | SaleController WhatsApp notifications → sends CreditBalanceNotification |
| `tests/Feature/SaleNotificationsTest.php` | SaleController WhatsApp notifications → sends PaymentReceivedNotification |
| `tests/Feature/Baileys/SaleNotificationSwitchesTest.php` | the switches actually gate delivery → sends only the text receipt |
| `tests/Feature/Baileys/SaleNotificationSwitchesTest.php` | the switches actually gate delivery → sends both when everything is on |
| `tests/Feature/CreditLedgerTest.php` | a credit sale debits the credit account → tells the customer |
| `tests/Feature/WhatsAppIntegrationTest.php` | Customer WhatsApp integration → can receive WhatsApp notifications |
| `tests/Feature/SecurityBaselineTest.php` | does not force https outside production by default |

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| 1. Composer sync, PROGRESS.md | `f005222` | none | 1,065 passed, 9 failed (baseline) | not set up yet (step 4) | `composer install` downgraded 99 packages to the lock (Laravel 12.69.3 → 12.56.0, Pest 4.7.8 → 4.5.0); results unchanged |
| 2. Fix the 9 baseline failures | `100447a` | `TestEnvironmentTest` (3 tests) | **1,077 passed, 0 failed** | not set up yet (step 4) | Tests only; no production code changed. The 9 tests also pass in three random orders (seeds 1, 42, 1337) |
| 3. Live-data rule and safety hook | `c72bde4` | `SafetyHookTest` (23 cases) | 1,077 passed, 23 skipped | not set up yet (step 4) | The hook now also blocks rolling back migrations, `TRUNCATE` without `TABLE`, `DELETE FROM` without `WHERE` and `DROP SCHEMA`, and allows commands that name only a `_testing` database. The 23 cases **skip locally** (lerd runs PHP in a container without `jq`); I verified them on the host shell instead (23/23). They run in CI, where `jq` is installed |
| 4. Databases, test guard, mail | `dc43f0b` | `TestEnvironmentTest` guard cases (+8), `SafetyHookTest` (+4) | 1,085 passed, 27 skipped | **1,081 passed, 4 failed, 27 skipped** (first run; the 4 are pre-existing bugs, fixed in steps 4b and 4c) | Development DB is **UAT** (see below). New `mysql_testing` connection reading `DB_TEST_*`; local `pos_saas_testing` on lerd MySQL 8.4.11; `phpunit.mysql.xml`; `TestCase` refuses any database not ending in `_testing`; hook treats UAT and production hosts and names as live; Mailpit verified (test message caught); `.env.example` updated |
| 4b. Social account credentials column; hook hardened | `8f44b46` | `SocialAccountCredentialsTest` (4); `SafetyHookTest` rewritten (41 cases, in `tests/Fixtures/safety-hook-cases.php`) | 1,088 passed, 42 skipped | 1,088 passed, **1 failed** (low stock, step 4c), 41 skipped | Migration `2026_10_13_111431`: `social_accounts.credentials` JSON → TEXT; `down()` refuses while encrypted values exist. Rehearsed on `pos_saas_rehearsal_testing`: 2 rows (NULL and JSON credentials) identical before and after. New `rehearsal_testing` connection. Hook now blocks every artisan command that changes a database unless `--database=…_testing`. **See "UAT incident" below** |
| 4c. Low-stock check for variations | `5b5c2c0` | `CheckLowStockLevelsTest` (5) | 1,093 passed, 42 skipped | **1,094 passed, 0 failed**, 41 skipped | `CheckLowStockLevels` compares a variation's stock with its **product's** `reorder_level` (approved) and records that as the threshold. The 5 new tests fail on MySQL against the old code and pass with the fix. MySQL suite fully green for the first time |
| 5. Snapshot and restore | `1bc830a` | `DatabaseSnapshotTest` (12); hook cases +4 (45) | 1,102 passed, 49 skipped | **1,106 passed, 0 failed**, 45 skipped | `db:snapshot` (read-only, any MySQL/MariaDB connection) and `db:restore-snapshot` (only into a connection and database ending in `_testing`, local/testing env, `--yes`, snapshots the target first, checks row counts afterwards). Round trip verified with `CHECKSUM TABLE`; awkward values (quotes, semicolons, newlines, the statement marker, Unicode, NULL, empty) survive. **Real run:** UAT snapshot, 85 tables / 1,290 rows (UAT unchanged before and after), restored into `rehearsal_testing` (MariaDB 11.4 → MySQL 8.4) with every row count matching |
| 6. Live-like dataset; connection guard | `1a0e3b1` | `LiveLikeDatasetTest` (13), `ConnectionGuardTest` (4) | 1,116 passed, 52 skipped | 1,123 passed, 0 failed, 45 skipped (7 min 22 s) | `dataset:build --database=…_testing [--scale=]` builds into an **empty** `_testing` database only. `LiveLikeDatasetSeeder`: seeded Faker/`mt_rand`/UUIDs/random strings, frozen clock, fixed password hash, faked mail/notifications/jobs/HTTP. Sales through the **real paths** (web and API `store()`, both converters); credit payments through `collectPayment()`; adjustments through `StockAdjustmentService`. Medium build (scale 1): 3,000 sales / 7,289 lines / 400 products / 503 customers, **5 min 21 s, 145 MB**. Two separate builds give identical data in every table (encrypted columns compared decrypted). `ConnectionGuard` blocks every query not on the target connection, in `dataset:build` and `db:restore-snapshot` |
| 7. Flat API contract fixtures | `6f30c63` | `FlatApiContractTest` (2 checks + 1 recorder) | 1,116 passed, 54 skipped | **1,125 passed, 0 failed**, 45 skipped (7 min 44 s) | `tests/Fixtures/api-contract/routes.json` (all 118 `/api` routes: method, URI, name, middleware) and `responses.json` (48 calls against the live-like dataset: 46 GET routes, sign-in, creating a sale; each with status and JSON shape). Fails when a recorded route is removed or changed, or a working endpoint changes status or loses or retypes a key; additions are allowed. A deliberately altered fixture made the check fail as expected. **6 endpoints are already broken on `main`** (see "Known broken flat API endpoints") |
| 8. CI workflow | _this commit_ | none (workflow) | 1,116 passed, 54 skipped | 1,125 passed, 0 failed, 45 skipped | `.github/workflows/ci.yml`: `sqlite` (Pint + suite), `mysql` (MySQL 8.4 service; suite incl. contract and dataset tests), `migration-rehearsal` (dataset built with the base branch's code once it has `dataset:build`, then this branch's migrations, then a per-table row-count check). Each job stops with a clear message if the `COMPOSER_AUTH` secret is missing. **Not yet run on GitHub** (no runner locally, `act` not installed): verified the YAML parses, and ran the rehearsal's commands locally (85 tables, 1,741 rows, nothing lost) and its comparison against a doctored count (fails, naming the table) |

### Differences from the stage file

- The local database is MySQL 8.4.11 on `lerd-mysql` (`DB_CONNECTION=mysql`, database `fitness_center`),
  not MariaDB / remote `ecohub_pos`. `fitness_center` is treated as the read-only legacy database.
- Local mail already goes to Mailpit (`lerd-mailpit:1025`); only verification and documentation remain.
- **The 8 notification failures** (diagnosed in step 2): `Notification::fake()` doesn't record a
  notification whose `via()` returns no channels, and the WhatsApp notifications return none when
  WhatsApp isn't configured. These 8 tests go through the sale or notification path **without**
  configuring WhatsApp, so they only passed where `.env` held WhatsApp credentials. The stage file's
  explanation was right; my planning note saying otherwise was wrong. Fix: the tests call the new
  `configureWhatsApp()` helper (`tests/Pest.php`), and `phpunit.xml` forces the WhatsApp values blank.
- **The HTTPS failure:** `APP_URL` came from `.env` (`https://…`). `phpunit.xml` now forces
  `APP_URL=http://localhost`.
- The README links `pos-saas-project.md`; the plan file is `pos-saas-plan.md`.
- **Development database is UAT, not a new local database** (decided 13 October 2026). UAT is `fitness`
  on a shared MariaDB 11.4.4 server (64.227.44.46) holding real-looking data (13 shops, 25 users,
  79 products, 25 sales, 4 customers); every migration on `main` has already run there, so nothing is
  pending. It's treated as live and **not written in stage 0**. The two production connections in
  `.env` are commented out.
- **Tests and the synthetic dataset run on lerd's local MySQL 8.4.11** (`pos_saas_testing`). The UAT
  server hosts about 20 other applications' databases that its account can drop tables in, so tests
  never run there. CI's second job uses MySQL 8, as decided.
- **Engine note:** UAT and both production connections are **MariaDB**, while the stage file says
  production is MySQL and CI tests MySQL 8 (kept as decided). Worth confirming before launch.
- **MySQL 8 exposed two pre-existing application bugs** (they also affect UAT's MariaDB):
  1. `social_accounts.credentials` is a JSON column but holds an encrypted string, so saving a social
     account fails (3 tests). UAT has 0 social accounts. Fixed in step 4b (column becomes TEXT; a schema
     change the stage file didn't plan, approved 13 October 2026).
  2. `CheckLowStockLevels` compares variation stock with `reorder_level`, which variations don't have
     (1 test). Fixed in step 4c: a variation uses its product's reorder level (approved).

- **Snapshots are written in PHP, not with `mysqldump`** (the plan said `mysqldump`). lerd's PHP container
  ships MariaDB 11.8 clients while CI and the local test server run MySQL 8; the same PDO code works
  against both engines, and against UAT. `app/Support/DatabaseSnapshot.php`.
- **A baseline snapshot of UAT exists locally:** `storage/app/snapshots/fitness-20261013-120843.sql.gz`
  (85 tables, 1,290 rows; taken after the step 4b incident, before anything else touches UAT). The
  `rehearsal_testing` scratch database now holds a copy of UAT's data for rehearsals. Both contain real
  data; both are local and git-ignored.

- **The development database is now `pos-saas`** on 46.101.89.24 (MariaDB 11.4, 87 tables, no business
  data), replacing UAT `fitness` (decided 13 October 2026). Treated as live; the hook already does.
- **The dataset isn't built into the development database** (the plan said "the dev DB is built once"):
  the development database is live, and the dataset only goes into empty `_testing` databases
  (`dataset_testing` locally, a MySQL service in CI). Running the MySQL suite empties `dataset_testing`.
- **Step 6 factories:** only `SaleSource`, `DeliveryCompany` and `EcommerceOrderItem` got factories.
  `ShopPaymentMethod` and `ExpenseApprovalHistory` are empty stub models, so the dataset inserts their
  rows directly; `SalePayment` rows come from the real payment path. `PurchaseOrderItemFactory` always
  creates a product of its own, so the dataset creates purchase order lines directly.
- **Near-miss in step 6 (no confirmed change):** Spatie's permission cache is bound at boot to the
  `.env` database's `cache` table, so the first dataset builds sent their "forget cached permissions"
  calls there (the 73 s permission seeding). A comparison that seemed to show UAT cache rows deleted
  turned out to have read `pos-saas` (the `.env` had already been switched; the remaining row's expiry
  differs from UAT's), so there's no evidence UAT changed. Fixed: the build moves the permission cache to
  memory, and `ConnectionGuard` blocks every query not on the target connection.

- **Contract recording is a test group, not an `api:contract-record` command** (the plan said a command):
  the test framework already signs in through Sanctum and seeds the dataset. Re-record on purpose with
  `php artisan test --configuration=phpunit.mysql.xml --group=api-contract-record` (the group is excluded
  from normal runs).
- **The contract is recorded and checked on MySQL only** (the plan put it in the SQLite CI job). On
  SQLite, raw report queries return numbers where MySQL returns strings, so shapes differ between
  engines; production is MySQL-family. CI runs it in the MySQL job.
- **Shapes treat `null` as compatible with any type, and record numbers as `number`** (JSON has one
  number type). Whether a field is empty, or a total is whole, depends on the data, not the contract.

### Known broken flat API endpoints (pre-existing on `main`, found in step 7)

Recorded in the contract as broken; fixing them later won't fail the contract test.

| Endpoint | Problem |
|---|---|
| `GET api/inventory`, `GET api/inventory/low-stock`, `GET api/inventory/shop/{shop}`, `GET api/inventory/product/{product}` | Routed to `Api\InventoryController` methods (`index`, `lowStock`, `byShop`, `byProduct`) that don't exist: always 500 |
| `GET api/users/{user}` | 500: `Call to undefined relationship [shop] on model [App\Models\User]` |
| `GET api/expenses/{expense}` | 403 "Unauthorized access to this expense." even for the owner (super-admin) |

### UAT incident (13 October 2026, step 4b)

While rehearsing the step 4b migration I ran `migrate` followed by `migrate:rollback --step=1` with
shell-exported `DB_CONNECTION`/`DB_TEST_DATABASE` meant to point at the scratch database. lerd runs PHP in
a container, so the exports never reached PHP, and **both commands ran against UAT (`fitness`)**. My own
target check printed `mysql → fitness`, and I didn't stop the chain on it. The safety hook allowed it
because it trusted the `_testing` name in the shell prefix.

- **Effect on UAT:** the migration changed `social_accounts.credentials` to TEXT and recorded itself;
  the rollback changed it back and removed the record, within about a second. Verified read-only straight
  afterwards: the column definition is identical to before (`longtext … utf8mb4_bin … CHECK
  (json_valid(credentials))`), `social_accounts` still has 0 rows, and `migrations` has the same 106 rows
  and last entry. The only trace is that the `migrations` table's auto-increment moved on by one. No
  data was lost. No snapshot was taken first (the snapshot tool doesn't exist until step 5), which broke
  the live-data rule.
- **Fixes in this step:** the hook now blocks every artisan command that changes a database unless it
  passes `--database=<connection ending in _testing>` (shell prefixes no longer count), including
  `Artisan::call()` in tinker; rehearsals use the dedicated `rehearsal_testing` connection and check the
  target before the first write; CLAUDE.md states that shell prefixes don't reach lerd's PHP. The command
  that caused it is a test case that must be denied.
- The user confirmed afterwards: UAT holds data; no destructive commands on it.

### Follow-ups

- **`SmsChannel::isConfigured()` reads `env()` directly** (`app/Notifications/Channels/SmsChannel.php:66`),
  so tests can only switch SMS on with `putenv()`, and `phpunit.xml` can't pin it without breaking
  `NewEcommerceOrderNotificationTest`. Fixed with the SMS service in stage 6 (plan Phase 6, "SMS fix").
- **The hook matches text anywhere in a command**, including commit messages and heredocs, so a message
  that quotes a blocked command is refused. Workaround: pass long text through a file (`git commit -F`).
  Not worth weakening the hook for.
- **`CheckLowStockLevels` isn't limited to the shop it raises alerts for:** it checks every product
  and variation in the database and files each alert under the given shop. Per-shop stock and scoping
  belong to stages 1b (stock per shop and variation) and 1c.
- **Fix or remove the 6 known broken flat API endpoints** (table above). Check first whether the Flutter
  app calls them; it fails silently on errors.
- **Dataset realism:** cash registers are opened per cashier per day by the real sale path and never
  closed (1,827 open at scale 1); a later stage could close each day's registers.
- **Within one PHP process a second dataset build differs** (static state held over), so the
  determinism test runs each build as its own process, as `dataset:build` is used.
- **UAT schema drift:** UAT's `sale_sources` and `delivery_companies` have `business_id` columns that no
  migration on `main` creates. Relevant to any import from UAT (stage 1a).
- **No local audit database for UAT development:** `database/audit.sqlite` doesn't exist, so audit
  logging from a local session against UAT fails silently (the logger swallows errors). Create it when
  local writes to UAT start (`touch database/audit.sqlite` and the audit migration).
