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
| 4. Databases, test guard, mail | `dc43f0b` | `TestEnvironmentTest` guard cases (+8), `SafetyHookTest` (+4) | 1,085 passed, 27 skipped | **1,081 passed, 4 failed, 27 skipped** (first run; the 4 are pre-existing bugs, fixed in steps 4b and 4c) | Development DB is **Development Database** (see below). New `mysql_testing` connection reading `DB_TEST_*`; local `pos_saas_testing` on lerd MySQL 8.4.11; `phpunit.mysql.xml`; `TestCase` refuses any database not ending in `_testing`; hook treats Development Database and production hosts and names as live; Mailpit verified (test message caught); `.env.example` updated |
| 4b. Social account credentials column; hook hardened | `8f44b46` | `SocialAccountCredentialsTest` (4); `SafetyHookTest` rewritten (41 cases, in `tests/Fixtures/safety-hook-cases.php`) | 1,088 passed, 42 skipped | 1,088 passed, **1 failed** (low stock, step 4c), 41 skipped | Migration `2026_10_13_111431`: `social_accounts.credentials` JSON → TEXT; `down()` refuses while encrypted values exist. Rehearsed on `pos_saas_rehearsal_testing`: 2 rows (NULL and JSON credentials) identical before and after. New `rehearsal_testing` connection. Hook now blocks every artisan command that changes a database unless `--database=…_testing`. **See "Development Database incident" below** |
| 4c. Low-stock check for variations | `5b5c2c0` | `CheckLowStockLevelsTest` (5) | 1,093 passed, 42 skipped | **1,094 passed, 0 failed**, 41 skipped | `CheckLowStockLevels` compares a variation's stock with its **product's** `reorder_level` (approved) and records that as the threshold. The 5 new tests fail on MySQL against the old code and pass with the fix. MySQL suite fully green for the first time |
| 5. Snapshot and restore | `1bc830a` | `DatabaseSnapshotTest` (12); hook cases +4 (45) | 1,102 passed, 49 skipped | **1,106 passed, 0 failed**, 45 skipped | `db:snapshot` (read-only, any MySQL/MariaDB connection) and `db:restore-snapshot` (only into a connection and database ending in `_testing`, local/testing env, `--yes`, snapshots the target first, checks row counts afterwards). Round trip verified with `CHECKSUM TABLE`; awkward values (quotes, semicolons, newlines, the statement marker, Unicode, NULL, empty) survive. **Real run:** Development Database snapshot, 85 tables / 1,290 rows (Development Database unchanged before and after), restored into `rehearsal_testing` (MariaDB 11.4 → MySQL 8.4) with every row count matching |
| 6. Live-like dataset; connection guard | `1a0e3b1` | `LiveLikeDatasetTest` (13), `ConnectionGuardTest` (4) | 1,116 passed, 52 skipped | 1,123 passed, 0 failed, 45 skipped (7 min 22 s) | `dataset:build --database=…_testing [--scale=]` builds into an **empty** `_testing` database only. `LiveLikeDatasetSeeder`: seeded Faker/`mt_rand`/UUIDs/random strings, frozen clock, fixed password hash, faked mail/notifications/jobs/HTTP. Sales through the **real paths** (web and API `store()`, both converters); credit payments through `collectPayment()`; adjustments through `StockAdjustmentService`. Medium build (scale 1): 3,000 sales / 7,289 lines / 400 products / 503 customers, **5 min 21 s, 145 MB**. Two separate builds give identical data in every table (encrypted columns compared decrypted). `ConnectionGuard` blocks every query not on the target connection, in `dataset:build` and `db:restore-snapshot` |
| 7. Flat API contract fixtures | `6f30c63` | `FlatApiContractTest` (2 checks + 1 recorder) | 1,116 passed, 54 skipped | **1,125 passed, 0 failed**, 45 skipped (7 min 44 s) | `tests/Fixtures/api-contract/routes.json` (all 118 `/api` routes: method, URI, name, middleware) and `responses.json` (48 calls against the live-like dataset: 46 GET routes, sign-in, creating a sale; each with status and JSON shape). Fails when a recorded route is removed or changed, or a working endpoint changes status or loses or retypes a key; additions are allowed. A deliberately altered fixture made the check fail as expected. **6 endpoints are already broken on `main`** (see "Known broken flat API endpoints") |
| 8. CI workflow | `ca97279` | none (workflow) | 1,116 passed, 54 skipped | 1,125 passed, 0 failed, 45 skipped | `.github/workflows/ci.yml`: `sqlite` (Pint + suite), `mysql` (MySQL 8.4 service; suite incl. contract and dataset tests), `migration-rehearsal` (dataset built with the base branch's code once it has `dataset:build`, then this branch's migrations, then a per-table row-count check). Each job stops with a clear message if the `COMPOSER_AUTH` secret is missing. **Not yet run on GitHub** (no runner locally, `act` not installed): verified the YAML parses, and ran the rehearsal's commands locally (85 tables, 1,741 rows, nothing lost) and its comparison against a doctored count (fails, naming the table) |
| 9. Docs; end of stage | _this commit_ | none | **1,116 passed, 0 failed**, 54 skipped | **1,125 passed, 0 failed**, 45 skipped (8 min) | Baseline, live-data rule and databases recorded in the plan (section 1, "After Phase 0"); stage file ticked with evidence; README links fixed. **CI fix:** the `sqlite` job now runs Pint only on files the branch changes (`--diff`): 123 files already on `main` predate Pint, so a full-repo check would fail every push |

### Differences from the stage file

- The local database is MySQL 8.4.11 on `46.101.89.24` (`DB_CONNECTION=mysql`, database `pos-saas`).
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
- **Development database is Development Database, not a new local database** (decided 13 October 2026). Development Database is `pos-saas`
  on a shared MariaDB 11.4.4 server (64.227.44.46) holding real-looking data (13 shops, 25 users,
  79 products, 25 sales, 4 customers); every migration on `main` has already run there, so nothing is
  pending. It's treated as live and **not written in stage 0**. The two production connections in
  `.env` are commented out.
- **Tests and the synthetic dataset run on lerd's local MySQL 8.4.11** (`pos_saas_testing`). The Development Database
  server hosts about 20 other applications' databases that its account can drop tables in, so tests
  never run there. CI's second job uses MySQL 8, as decided.
- **Engine note:** Development Database and both production connections are **MySQL**, while the stage file says
  production is MySQL and CI tests MySQL 8 (kept as decided). Worth confirming before launch.
- **MySQL 8 exposed two pre-existing application bugs** :
  1. `social_accounts.credentials` is a JSON column but holds an encrypted string, so saving a social
     account fails (3 tests). Development Database has 0 social accounts. Fixed in step 4b (column becomes TEXT; a schema
     change the stage file didn't plan, approved 13 October 2026).
  2. `CheckLowStockLevels` compares variation stock with `reorder_level`, which variations don't have
     (1 test). Fixed in step 4c: a variation uses its product's reorder level (approved).

- **Snapshots are written in PHP, not with `mysqldump`** (the plan said `mysqldump`). lerd's PHP container
  ships MariaDB 11.8 clients while CI and the local test server run MySQL 8; the same PDO code works
  against both engines, and against Development Database. `app/Support/DatabaseSnapshot.php`.
- **A baseline snapshot of Development Database exists locally:** `storage/app/snapshots/fitness-20261013-120843.sql.gz`
  (85 tables, 1,290 rows; taken after the step 4b incident, before anything else touches Development Database). The
  `rehearsal_testing` scratch database now holds a copy of Development Database's data for rehearsals. Both contain real
  data; both are local and git-ignored.

- **The development database is now `pos-saas`** on 46.101.89.24 (MariaDB 11.4, 87 tables, no business
  data), replacing Development Database `fitness` (decided 13 October 2026). Treated as live; the hook already does.
- **The dataset isn't built into the development database** (the plan said "the dev DB is built once"):
  the development database is live, and the dataset only goes into empty `_testing` databases
  (`dataset_testing` locally, a MySQL service in CI). Running the MySQL suite empties `dataset_testing`.
- **Step 6 factories:** only `SaleSource`, `DeliveryCompany` and `EcommerceOrderItem` got factories.
  `ShopPaymentMethod` and `ExpenseApprovalHistory` are empty stub models, so the dataset inserts their
  rows directly; `SalePayment` rows come from the real payment path. `PurchaseOrderItemFactory` always
  creates a product of its own, so the dataset creates purchase order lines directly.
- **Near-miss in step 6 (no confirmed change):** Spatie's permission cache is bound at boot to the
  `.env` database's `cache` table, so the first dataset builds sent their "forget cached permissions"
  calls there (the 73 s permission seeding). A comparison that seemed to show Development Database cache rows deleted
  turned out to have read `pos-saas` (the `.env` had already been switched; the remaining row's expiry
  differs from Development Database's), so there's no evidence Development Database changed. Fixed: the build moves the permission cache to
  memory, and `ConnectionGuard` blocks every query not on the target connection.

- **Contract recording is a test group, not an `api:contract-record` command** (the plan said a command):
  the test framework already signs in through Sanctum and seeds the dataset. Re-record on purpose with
  `vendor/bin/pest --configuration=phpunit.mysql.xml --group=api-contract-record` (the group is excluded
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

### Development Database incident (13 October 2026, step 4b)

While rehearsing the step 4b migration I ran `migrate` followed by `migrate:rollback --step=1` with
shell-exported `DB_CONNECTION`/`DB_TEST_DATABASE` meant to point at the scratch database. lerd runs PHP in
a container, so the exports never reached PHP, and **both commands ran against Development Database (`fitness`)**. My own
target check printed `mysql → fitness`, and I didn't stop the chain on it. The safety hook allowed it
because it trusted the `_testing` name in the shell prefix.

- **Effect on Development Database:** the migration changed `social_accounts.credentials` to TEXT and recorded itself;
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
- The user confirmed afterwards: Development Database holds data; no destructive commands on it.

### End of stage 0 (13 October 2026)

- **Checks on the whole branch:** SQLite 1,116 passed, 0 failed; MySQL 8 1,125 passed, 0 failed; Pint passes
  on every changed file (`pint --test --diff=main`).
- **Acceptance criteria:** all ticked with evidence in the stage file except **"green in all three CI
  jobs"**: CI hasn't run yet. It needs the `COMPOSER_AUTH` repository secret (not set yet), and its first
  run is the push of this branch.
- **PR checklist (`.ai/general/PR_CHECKLIST.md`):** no debug code, secrets or `env()` outside config in
  the diff; the one migration is anonymous with `up()` and `down()`; validation, CSRF and honeypot are
  untouched (no new forms or endpoints). Items that contradict CLAUDE.md's "two-standard reality" (UUID
  keys, `HasUuids`, `casts()` method) don't apply to this repo's existing modules.

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
- **123 files on `main` aren't Pint-formatted.** CI checks only changed files; formatting the rest is a
  separate, purely mechanical change best done on its own branch.
- **Fix or remove the 6 known broken flat API endpoints** (table above). Check first whether the Flutter
  app calls them; it fails silently on errors.
- **Dataset realism:** cash registers are opened per cashier per day by the real sale path and never
  closed (1,827 open at scale 1); a later stage could close each day's registers.
- **Within one PHP process a second dataset build differs** (static state held over), so the
  determinism test runs each build as its own process, as `dataset:build` is used.
- **Development Database schema drift:** Development Database's `sale_sources` and `delivery_companies` have `business_id` columns that no
  migration on `main` creates. Relevant to any import from Development Database (stage 1a).
- **No local audit database for Development Database development:** `database/audit.sqlite` doesn't exist, so audit
  logging from a local session against Development Database fails silently (the logger swallows errors). Create it when
  local writes to Development Database start (`touch database/audit.sqlite` and the audit migration).

## Stage 1a (Phase 1a): Schema, tenant layer, default-deny

Branch: `saas/phase-1a-schema-tenant-layer` (from `main` at `1aea0e3`, the merge of stage 0). Plan: the
stage file itself (approved 14 October 2026), worked in its own order.

### Starting result

`main` at `1aea0e3` (same tree as `bdd710e`), 14 October 2026: **SQLite 1,120 passed, 55 skipped;
MySQL 8 1,129 passed, 46 skipped**; Pint passes on changed files.

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| 1. Migrations consolidated; empty-database guard | `a4196bc` | `MigrationBaselineTest` (5) | 1,124 passed, 55 skipped | 1,133 passed, 46 skipped (11 min; contract test passes) | First part of "Migrations rewritten". The 104 migrations become 84 `create_*` migrations (one per table) plus the guard; they build **exactly** `main`'s schema on both engines, proved against `tests/Fixtures/legacy-schema/{sqlite,mysql}.json` (recorded from `main`'s migrations; a deliberately changed default fails the check). The guard (`0001_01_01_000000_guard_empty_database`) refuses a database that already has tables unless `DB_BASELINE_FORCE=true`. CI's rehearsal now checks that the baseline **refuses** `main`'s dataset and builds in an empty database; the real rehearsal (the legacy import) comes with the import. Removed one test, with approval: the rollback guard of stage 0's credentials migration, which no longer exists |
| 2. SaaS schema; businesses, memberships, tenant context | `e5859bc` | `TenantContextTest` (12); `MigrationBaselineTest` parity check replaced by "keeps every legacy column" | 1,136 passed, 55 skipped | 1,145 passed, 46 skipped (7 min) | Second part of "Migrations rewritten", plus `Business`, `BusinessMembership` and `TenantContext` from the tenant layer (the new NOT NULL `business_id` needs them). **Schema:** `businesses`, `business_user`, `users.default_business_id`; `business_id` on shops, products, variations, the 7 lists, customers, sales, sale payments, cash sessions; per-business unique keys (soft-deleted rows excluded through a generated `alive` column); `shop_variation` (per-shop stock per variation), `registers` + `cash_registers.register_id`, `client_uuid` keys, `sync_deletions`, review flags, currency/timezone, shop timezone; `is_default` on variations; two user FKs no longer cascade. Every legacy column kept (test). **Tenant core:** `ResolveTenant` (after auth: session business re-checked against membership, else the default business; API tokens use the default), `BelongsToBusiness` (business from the parent, else the context, else refused). Each new business gets a Walk-in sale source (replacing the migration's global one). **Seeders:** `DatabaseSeeder` = permissions + roles; the demo `UserSeeder`, `ShopSeeder`, `SaleSourcesSeeder` removed (decision 4.7); the dataset builds into its own business. **Tests:** each feature test runs in a "Test Business" (`tests/Pest.php`); factory users join it. **Found and fixed two transaction leaks** that hung the MySQL suite once tests wrote rows: the guard test (Laravel's migrator doesn't restore the default connection when a migration throws) and the in-process dataset build (it switches the default connection); both left the test's transaction open, locking the next fresh migration |
| 3. Tenant scopes; two-business fixture | `ca4cb5a` | `TenantScopeTest` (10), `TenantScopeReflectionTest` (2) | 1,148 passed, 55 skipped | 1,157 passed, 46 skipped | `BusinessScope` and `ShopAccessScope` on every model with `business_id` or `shop_id` (shop access still fails open for a member with no assignment; default-deny is the next step). `canAccessShop()` only accepts the current business's accessible shops. **Behaviour change, tests adapted (15):** a record in a shop the user can't access is now **404**, not 403 (it's outside their scope, so its existence isn't revealed): abandoned carts (11), sales (2), e-commerce orders (1); a credit statement for a customer whose credit account is in another shop is now 422 ("no credit account"), still never 401. **Found and fixed:** memoising each user's shops went stale when shops were created in the same request; access is now a subquery built per query; and unordered `Shop` queries no longer came back in id order on MySQL (the access filter changed the index used), which ~30 `Shop::first()` fallbacks rely on: shop queries without an order of their own are ordered by id |
| 4. Default-deny shop access | `d3dce4e` | `TenantScopeTest` +1 (no assignment), `UserTest` +1 (owner), `ExpenseApiShopScopeTest` +1 | 1,151 passed, 55 skipped | 1,160 passed, 46 skipped (7 min) | A member who isn't an owner sees only their assigned shops; none assigned means none (decision 4.4); no switch to turn it off. `canAccessShop(null)` ("every shop") is owner-only. `Api\ExpenseController::store` answers 422 instead of a 500 when the user has no shop. **Behaviour change:** a `*.full-access` permission alone no longer reaches every shop; being the owner does. **Tests adapted (22 files):** users that stood for staff working in the scenario's shops are now assigned to them (`assignToNewShops()` in `tests/Pest.php`, or an explicit `attach`); tests that asserted the old fail-open rule now assert default-deny, with the all-seeing user as the owner (`UserTest`, `DashboardShopAccessTest`, `CalendarShopScopingTest`, `CreditAgingAndScopingTest`, `ExpenseApiShopScopeTest`) |
| 5. Per-tenant unique rule | `fced59b` | `PerTenantUniqueKeysTest` (16) | 1,167 passed, 55 skipped | 1,176 passed, 46 skipped (7 min) | `UniqueForTenant` (unique within the current business, soft-deleted rows ignored; a plain query, so rows in shops the user can't see still count) replaces 24 `unique:` rules in 13 files. Users, roles and the credit-account rule are unchanged here |
| 6. Viewer-aware `exists` | `874b75c` | `ViewerAwareExistsTest` (9) | 1,176 passed, 55 skipped | 1,185 passed, 46 skipped (7.5 min) | `TenantPresenceVerifier` makes every `exists:` rule respect the tenant scopes (another business's ids, and shops the user can't access, fail validation), with no change to the 123 rules. **Behaviour change, tests adapted (2):** naming a shop the user can't access in a filter is now a validation error rather than a 403 (e-commerce order refresh, VAT report). The contract test picks its sale source from the dataset's business and sets that business explicitly (input only; fixtures unchanged). `LiveLikeDatasetTest` now names the rows and columns that differ when two builds don't match (the intermittent `users` difference, below) |
| 7. Route binding by uuid | `30348a1` | `TenantScopeReflectionTest` +1 (every route binds by uuid) | 1,177 passed, 55 skipped | 1,186 passed, 46 skipped (8 min) | `baileys_chats.uuid`; `{chat}` binds by uuid; the inbox's `?chat=` links, the redirect after starting a chat and the stream URL use it. **Fixed a flaky factory:** `ShopFactory` codes came from fake company names and could collide (seen once on MySQL); they now end in a random suffix |
| 8. One credit account per customer | `806d853` | `BusinessCustomersTest` (4) | 1,181 passed, 55 skipped | 1,190 passed, 46 skipped (11 min) | `credit_accounts` unique on `customer_id` and business-scoped: the one account serves every shop of the business, so it can't be hidden by the shop it was opened in (`shop_id` kept for that). `CreditAccountService::resolveAccount()` and `createAccount()` work by customer; the request rule checks one per customer; `CreditAccount::scopeVisibleTo()` no longer narrows by shop. Credit transactions keep their shop scope, like sales. **Behaviour change, tests adapted (2):** shop-assigned staff now see every credit account of their business (still none of another business); a statement for an account opened in a shop the user can't access is 403 again (the original assertion, restored) |
| 9. Default variation; per-shop rows | `2771af3` | `DefaultVariationTest` (5) | 1,186 passed, 55 skipped | 1,195 passed, 46 skipped (13 min) | Every new product gets a default variation (reserved SKU `DEFAULT-{product uuid}`, the product's prices and tax class), hidden by a global scope from variation lists, the API and `exists:` checks. Each variation gets a `shop_variation` row in every shop of its product (own shop and listed shops), created with the product, with each variation, and after shop assignments change (product create and update actions, Woo and Shopify sync, `BackfillShopProductPivot`). Rows start at zero; stage 1b moves stock onto them. No existing test changed. The MySQL suite now takes about 13 minutes (the dataset builds create the extra rows) |
| 10. Spatie teams: roles per business | `24d385b` | `RolesPerBusinessTest` (7) | 1,193 passed, 55 skipped | 1,202 passed, 46 skipped (13 min) | `teams => true`, `team_foreign_key = business_id`. Seeded roles are templates shared by every business (`business_id` NULL; `RoleSeeder` runs with no business set); a role created while a business is set belongs to that business, and roles screens show templates plus the business's own. Assignments and direct permissions are held per business. `TenantContext::set()` sets Spatie's team and clears loaded roles; `POST /api/login` sets the default business first, so it returns the roles held there. The 11 policy-level super-admin checks repeated `Gate::before` and are removed; `Gate::before` asks `isBusinessOwner()`. Tests: the fixture uses shared templates (`sharedRole()` in `tests/Pest.php`); no existing test changed |
| 11. One-off legacy import | `94021c5` | `LegacyImportTest` (8); hook cases +3 (49, checked on the host) | 1,201 passed, 58 skipped | 1,210 passed, 49 skipped (12 min) | `php artisan saas:import-legacy --from=<legacy connection> --database=<freshly migrated baseline> --business-name=…` (`app/Support/Legacy/LegacyImport.php`): the stage 0 commitment that data crosses into the rewritten schema. The source is only read (a guard refuses anything but reads; `ConnectionGuard` limits the command to the two connections); the target must be a freshly migrated baseline. Every legacy row is copied with its ids into one business; users become members (inactive ones inactive) with role grants held in the business; credit accounts merge to one per customer; every product gets a default variation and per-shop stock rows (a product's stock goes to its own shop; listed shops' e-commerce figures are noted, not used). A verification compares counts, money totals, credit balances per customer and stock, and finds orphaned references; any mismatch fails the command; a JSON report goes to `storage/app/imports/`. The legacy schema fixtures moved to `database/legacy/` (the command reads them) and their recorder was removed. **Rehearsed on MySQL:** `main`'s live-like dataset, built with `main`'s own code (worktree, scale 0.25), imported and verified with no mismatches (94 notes, all listed-shop stock figures); the copy of the old development database in `rehearsal_testing` (13 shops, 25 users, 79 products, 25 sales, 4 customers) imported and verified, no notes. New `import_testing` connection. **CI:** the rehearsal job now imports `main`'s dataset into the baseline as its gate, uploads the report, and checks the dataset is unchanged. The safety hook treats the import as a write: it needs a `_testing` target |

| 12. End of stage | _this commit_ | `TenantScopeBypassTest` (every place that lifts the tenant scopes, with its reason) | **1,202 passed, 0 failed**, 58 skipped | **1,211 passed, 0 failed**, 49 skipped (9.5 min) | CLAUDE.md tenancy rules and the migration/import commands; plan updated (4.7 as built, Phase 1a as built, hotspots); stage file ticked with evidence; UAT runbook below; `pint --test --diff=origin/main` passes. The 58 SQLite skips are the MySQL-only tests and the safety-hook cases (no `jq` in lerd's container; all 49 checked on the host) |

### Moving UAT onto the new schema (for you to run; nothing here has touched UAT)

The baseline refuses a database that already has tables, so UAT (`pos-saas`) can't be migrated in place.
Its data crosses by the import into a **new** database; `pos-saas` itself is only read and stays as it is.

1. Stop the queue worker and let `jobs` drain: queued jobs, sessions, cache and password-reset tokens are
   not carried across.
2. Snapshot UAT: `php artisan db:snapshot` (read-only; the file lands in `storage/app/snapshots/`).
3. Create an empty database on the UAT server (for example `pos-saas-v2`) and add a connection for it.
4. Migrate the baseline into it: `php artisan migrate --database=<new connection>`. This is also the first run of
   the baseline on **MariaDB 11.4** (CI and local tests use MySQL 8); if it fails, no data has moved.
5. Import: `php artisan saas:import-legacy --from=mysql --database=<new connection> --business-name="<name>"`.
   It reads `pos-saas` only and stops with a non-zero exit on any mismatch; read the report it writes.
6. Point `DB_DATABASE` at the new database.

**Worth knowing first:** UAT holds almost nothing to carry (1 sale source, no users, 111 migrations from
another branch whose extra `business_id` columns the import ignores and reports). With no users, nobody
can sign in after the import. A freshly migrated database seeded with `db:seed` (permissions and role
templates) and a first user may serve UAT better; the import is there for any database that does hold data.

### Differences from the stage file

- **"Migrations rewritten" lands in two commits:** first the same schema, consolidated and proved
  identical, then the SaaS schema written into those files. Each commit stays green; the second is
  reviewable as a schema diff against `main`. The second also brings `Business`, `BusinessMembership`
  and `TenantContext` forward from the tenant layer, because NOT NULL `business_id` needs them.
- **Generated numbers stay globally unique** (purchase order, intake, payment, return and similar
  numbers carry random suffixes); only the codes users type, and invoice numbers, become unique per
  business, as the stage file lists.
- **Seeded demo data removed** (`UserSeeder`, `ShopSeeder`, `SaleSourcesSeeder`): decision 4.7 seeds
  platform essentials only; plans are seeded in Phase 4 with their table.
- **Legacy stock columns are kept through 1a** (`products.stock_quantity`,
  `product_variations.stock_quantity`, `shop_product`, the `cash_registers` name), decided 14 October
  2026: the code that writes them moves in 1b, which then drops them with a migration that carries the
  data across.
- **The super-admin split stays in Phase 3**, as the plan places it. Stage 1a switches roles to per
  business (Spatie teams) and reviews the 12 direct checks; the owner is `super-admin` in the current
  business until then. (An earlier answer chose to split now; you then asked to follow the stages as set.)
- **`exists:` is viewer-aware without rewriting the 123 rules:** a tenant presence verifier makes Laravel's
  own `exists:` respect the scopes, which also covers rules written later.
- **A record outside the user's shops is 404, not 403**, and naming an inaccessible shop in a form is a
  validation error: the record is outside the user's scope, so its existence isn't revealed. The tests
  adapted in steps 3 and 6 assert the new statuses.
- **Credit accounts are business-scoped**, not shop-scoped: with one account per customer serving every
  shop, scoping it by the shop it was opened in would stop other shops selling on credit to that customer.
- **The one-off import is built in 1a** (stage 0's commitment), with its own CI rehearsal.

### Follow-ups

- **Jobs, commands and webhooks run without a business** in production until stage 1c makes each set
  one: scoped queries then run unscoped (logged); the two webhook jobs that look up managers by role find
  none. Covered in tests by the test business; tracked by 1c's "the 10 jobs carry the business".
- **The 30 `auth()->user()->shop_id ?? Shop::first()?->id` fallbacks** remain (now limited to accessible
  shops); 1c's raw SQL and controller work replaces them.
- **`shop_variation` stock starts at zero** for new products; 1b moves stock onto it.
- **`mobile:contract-diff` not run:** the Flutter app (`MOBILE_APP_PATH`) isn't on this machine. The flat
  API contract test passed at every step; worth running where the app is, especially since login's
  `roles` now come from the default business.
- **The MySQL suite takes about 12–13 minutes** (it was 7): the dataset builds now create default
  variations and per-shop rows.
- **Intermittent MySQL failure seen in stage 1a step 3:** `StockIntakeTest > authorized user can create
  stock intake` failed in one full run and two isolated runs straight after it (the redirect succeeded,
  the asserted row wasn't found), then passed in 11 further runs, including a full run with
  diagnostics. Not reproduced; watch for it.
- **Intermittent dataset determinism failure (stage 1a, steps 3 and 6):** in about one full MySQL run in
  three, `LiveLikeDatasetTest > builds the same data every time` reports that `users` differs between the
  two builds. Six back-to-back builds in isolation were identical, and so were the instrumented full
  runs. The test now prints the differing rows and columns; investigate from that output next time.

## Stage 1b (Phase 1b): Stock, sales, registers, invoice numbers

Branch: `saas/phase-1b-stock-sales-registers` (from `main` at `92409f6`, the merge of stage 1a). Plan:
the stage file itself.

### Starting result

`main` at `92409f6` (the same tree as `2e2fcad`, verified at the end of stage 1a): **SQLite 1,202
passed, 58 skipped; MySQL 8 1,211 passed, 49 skipped**.

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| 1. FIFO analysis and agreed design | `63c6cca` | none (documents) | unchanged | unchanged | `docs/saas/fifo-analysis.md`: the earlier plan (Modules 08, 20), what was built (nothing beyond empty tables and models), where they differ (no `stock_batches`; no cost on `stock_intakes`; no variations), the SaaS mapping, and the cases the earlier plan didn't cover. Decided 15 October 2026: negative stock re-costed on arrival; restockable returns become a layer at the sold cost; `sale_item_stock_allocations` kept, unused; legacy stock columns dropped in 1b. Recorded in `pos-saas-plan.md` |
| 2. Cost tables and the stock service | `67ecf30` | `StockLedgerTest` (9); `LegacyImportTest` +opening layers | 1,211 passed, 58 skipped | 1,220 passed, 49 skipped (11 min) | Migration `2026_10_15_000001`: `cost_layers` and `cost_allocations` rebuilt for the agreed design (per shop and variation, business, source and consumer, provisional allocations); they were empty everywhere, and the migration refuses to run otherwise. Models `CostLayer`, `CostAllocation`, `ShopVariation` (tenant-scoped). `App\Services\Stock\StockLedger`: `receive()` (a layer, which first settles units sold below zero and re-costs their sales), `consume()` (oldest layers first, a preferred layer first, a provisional shortfall below zero), `sellItem()`/`recostSale()` (a line's cost and profit from what it consumed, net of VAT), `fallbackCost()`. Each change locks the `shop_variation` row and moves it by a delta. No callers yet. The legacy import now reads stock from the source and gives every imported figure an opening layer (a negative one, a provisional shortfall), and checks layers add up to stock; the never-written costing tables aren't carried |
| 3. Every sale path, writer and reader on per-shop stock | `6344205` | `SalePathsStockTest` (5), `StockWritersAndReadersTest` (9) | 1,225 passed, 58 skipped | 1,234 passed, 49 skipped (8 min) | **Sale paths:** web and API `SaleController`, `ConvertEcommerceOrderToSale`, `ConvertAbandonedCartToSale` take each line's stock from its shop through `StockLedger::sellItem()` (a movement per line, FIFO cost; below zero accepted and costed provisionally), then `recostSale()` sets the sale's FIFO cost and profit (set on the sale in memory so the API's money fields stay numbers). **Writers:** intake completion (into the intake's shop, for its variation, layered at the purchase order line's cost; it used to add a variation's intake to the product), stock adjustments (into the adjustment's shop), purchase returns (the returned intake's layer first), `RecordStockMovement` (through the ledger: no more read-then-write race), restockable customer returns (**restocked on receipt for the first time**, at the sold cost), and the Woo/Shopify product sync (the store's figure is the syncing shop's stock, when the platform manages inventory; pushes send that shop's stock). **Readers:** low-stock alerts (per shop: the stage 0 follow-up), inventory snapshots (per shop, valued at FIFO cost), `InventoryService::getCurrentInventory`, `ProductService`, the dashboard. **Compatibility:** `stock_quantity` on products and variations is computed from per-shop stock (the request's shop, else the viewer's shops; `withStockOnHand()` loads it in one query) and still settable (it moves the product's own shop through the ledger), so forms, the flat API and factories keep working. Tests adapted (2): stock is per shop, so the intake test puts product and intake in one shop; the e-commerce pivot tests read the shop's stock rather than the pivot column |
| 4. Legacy stock moved, columns dropped | `bad94f3` | `StockMoveMigrationTest` (2); `StockWritersAndReadersTest` +1 (pivot stock) | 1,228 passed, 58 skipped | 1,237 passed, 49 skipped (8.5 min) | Migration `2026_10_15_000002`: every `products.stock_quantity` (through the default variation) and `product_variations.stock_quantity` figure goes to the product's own shop in `shop_variation`, with an opening FIFO layer at the variation's cost (a negative figure: a provisional shortfall); missing default variations are created; the totals are checked before `products.stock_quantity`, `product_variations.stock_quantity` and `shop_product.stock_quantity` are dropped. `down()` restores the columns as totals across shops. The flat API's `shops[].pivot.stock_quantity` is now each shop's stock of the product (a subquery on the relation; integer). The dataset's second-shop stock goes through the ledger. **Found on MySQL:** dropping the `shop_product (product_id, stock_quantity)` index failed because it also served the `product_id` foreign key; every test then re-ran the failing migration, which looked like a hang. The FK gets its own index first. Rehearsed again: the old development database copy imports and verifies on the new schema (some of its stock is below zero; it arrives as provisional shortfalls). The legacy schema test lists the three columns as moved, and the import test gives its source the legacy columns |
| 5. Profit from FIFO cost | `d61d62c` | `FifoProfitTest` (2) | 1,230 passed, 58 skipped | 1,239 passed, 49 skipped (8.5 min) | `sales:recompute-profit` costs a line from its FIFO allocations; a known layer cost is never replaced by today's price, and an allocation whose layer cost was unknown takes the purchase cost entered since, which is also written to the rest of that layer so later sales are costed. Lines with no allocations (sold before FIFO, or imported) use the product's current cost as before. `docs/purchase-costs.md` updated. **Local MySQL runs now use `phpunit.mysql.local.xml`** (untracked, in `.git/info/exclude`): `.env` was changed to the host-side port 3307 (kept, at your request), and inside lerd's container the server is on 3306; the copy only forces that port |
| 6. Registers; sessions on a register | `6864ed8` | `RegistersTest` (5) | 1,235 passed, 58 skipped | 1,244 passed, 49 skipped (8.5 min) | `Register` model (shop-scoped, uuid route key) and factory; every shop gets a default register (`R1`, "Main till") when created; migration `2026_10_15_000003` gives every existing shop its default register and places every existing cash session on it (refusing to finish if one can't be placed). A cash session (`CashRegister`) always runs on a register: `openRegister()`/`getActiveRegister()` take a register and default to the shop's default one, so legacy cash-register and sale calls land there; sessions on different registers of one shop run side by side. `cash_registers.register_id` stays nullable in the schema (its `nullOnDelete` key), but every session gets one |
| 7. Invoice numbers per register | _this commit_ | `InvoiceNumbersTest` (3), `InvoiceNumberConcurrencyTest` (1, MySQL only) | 1,238 passed, 59 skipped | 1,248 passed, 49 skipped (8.7 min) | `InvoiceNumberService::next($shop, $register)`: each register numbers in its own series (`r{register id}:{year}`), `INV-{shop}-{register}-{year}-{n}`, under the same row lock, and notes the number on `registers.last_invoice_number`; no register named means the shop's default. All four sale paths pass the register their cash session runs on. Migration `2026_10_15_000004` carries each shop's existing series on at its default register (`INV-NRB-2026-000123` is followed by `INV-NRB-R1-2026-000124`). **The concurrency test found a deadlock that was already on `main`:** a locking read of a series row that didn't exist yet took a gap lock, two registers then deadlocked inserting into the gap, MySQL rolled one sale's transaction back, and the old retry loop swallowed the error and carried on outside any transaction. The row is now created by an upsert before it is locked, and no database error is swallowed. The test runs 6 processes (3 per register) claiming 120 numbers together on a scratch database (`concurrency_testing`, created by the test and in CI); each series comes out 1–60 with no collision or gap (passed 4 runs in a row). Rehearsed migrations 000003 and 000004 on `import_testing` (13 shops → 13 default registers, 11 sessions all placed; the legacy data has no invoice series). Tests adapted (2 files): expected numbers carry the register code. `docs/vat.md` updated |

### Differences from the stage file

- **`sale_item_stock_allocations` is kept, unused** (decided 15 October 2026): `cost_allocations`
  records what each sale line consumed, so the third table has no job in the agreed design.
- **`cash_registers.register_id` stays nullable in the schema** (its key is `nullOnDelete`), but every
  session gets a register: the model defaults it, and migration `2026_10_15_000003` places every
  existing session and refuses to finish otherwise.
- **Each shop's existing invoice series carries on at its default register** (migration
  `2026_10_15_000004`) rather than restarting at 1, so a shop's count never goes backwards.
- **The invoice row lock is kept, but the series row is now created before it is locked.** The stage
  file asks to keep the lock; the concurrency test showed that the old way of creating a new series
  (a locking read of a missing row, then insert, swallowing the error) deadlocked two registers and lost
  the sale's transaction. This was already on `main`.
- **The concurrency test needs a scratch database** (`concurrency_testing`, default
  `pos_saas_concurrency_testing`): processes can't see rows inside the suite's per-test transaction.
  The test creates and migrates it; CI creates it with the other scratch databases.

### Moving UAT onto stage 1b (for you to run; nothing here has touched UAT)

After stage 1b merges, four additive migrations apply on top of the stage 1a baseline:
`2026_10_15_000001` (rebuilds the empty cost tables; refuses if they hold rows), `000002` (moves product
and variation stock into `shop_variation` with opening cost layers, then **drops** the three legacy stock
columns; `down()` restores them as totals), `000003` (default registers, sessions placed) and `000004`
(invoice series onto default registers).

1. Snapshot first: `php artisan db:snapshot`.
2. Rehearse on a copy: `php artisan db:restore-snapshot FILE --database=rehearsal_testing --yes`, then
   `php artisan migrate --database=rehearsal_testing`. Rehearsed here on `import_testing` (the legacy
   import): stock 804 across 10 layers with no mismatch, 13 default registers, all 11 sessions placed.
3. Then run `php artisan migrate` on UAT yourself.

### End of stage 1b (15 October 2026)

- **Checks on the whole branch:** SQLite 1,238 passed, 59 skipped; MySQL 8 1,248 passed, 49 skipped
  (the flat API contract test runs within it); Pint passes on every changed file
  (`pint --test --diff=origin/main`).
- **Acceptance criteria:** all ticked with evidence in the stage file; hotspots for the stock source,
  sale paths, costing, registers and the legacy default ticked in saas-plan.md section 2.
- **PR checklist (`.ai/general/PR_CHECKLIST.md`):** no debug code, secrets or `env()` outside config in
  the diff; the four migrations are anonymous with `up()` and `down()` and carry existing data across;
  no new routes, forms or endpoints, so validation, CSRF and honeypot are untouched; the flat API keeps
  its shape (contract test). Items that contradict CLAUDE.md's "two-standard reality" (UUID keys,
  `HasUuids`) don't apply to this repo's existing modules.
- **CI migration rehearsal fixed (`666da88`):** it was written for the stage 1a PR (pre-SaaS base: the
  guard must refuse the dataset, then the import). With 1a merged the dataset is built on the baseline,
  so `migrate` rightly applied only 1b's migrations and the job failed. It now runs in `additive` mode
  against a base with the baseline: this branch's migrations on the dataset, failing if any table loses
  rows. Run locally the same way (dataset built with `main` at scale 0.25): 5,412 rows before, 5,613
  after (191 opening cost layers, 6 registers, 4 migration rows), none lost.
- **CLAUDE.md and the `pos-domain-rules` skill** now describe the ledger, FIFO costing, registers and
  per-register invoice numbers, replacing the "no StockMovement for sales / no FIFO" corrections.

### Follow-ups

- **E-commerce stock push on per-shop stock changes:** `ProductObserver` pushes on `Product::updated`;
  sales and intake change `shop_variation` through the ledger and touch no product, so they push
  nothing. This is the 1c hotspot "E-commerce stock push triggered by per-shop stock changes".
- **Choosing a register:** the existing app can't name one, so every web and API sale lands on `R1`;
  picking a register (and pairing devices) comes with `/api/v1` and sync (Phase 6).
- **Sale transactions don't retry on a deadlock** (`DB::transaction` with one attempt, with sync jobs
  dispatched inside): a deadlock fails the sale cleanly. The known invoice deadlock is fixed; the stock
  ledger locks one `shop_variation` row per line, in line order, so two sales of the same items in
  different orders can still deadlock.
- **Scratch tables to remove:** `zz_probe_0`, `zz_probe_1`, `zz_probe_2` in
  `pos_saas_rehearsal_testing` (empty, created by mistake during step 4; the safety hook blocks dropping
  them from here).
- **`.env` `DB_TEST_PORT=3307`** (the host port, kept at your request) means artisan inside lerd's
  container can't reach MySQL through `.env`. The local MySQL suite runs with an untracked
  `phpunit.mysql.local.xml` that forces 3306; the rehearsal in step 7 ran through a scratchpad bootstrap
  that does the same.
- **`mobile:contract-diff` not run** (no Flutter app on this machine); no routes changed, and the flat
  API contract test passed at every step.

## Stage 1c (Phase 1c): Raw SQL, background work, files

Branch: `saas/phase-1c-raw-sql-background-files` (from `main` at `89e145d`, the merge of stage 1b). Plan:
the stage file itself.

### Starting result

`main` at `89e145d` (the same tree as `2bb2139`, verified at the end of stage 1b): **SQLite 1,238
passed, 59 skipped; MySQL 8 1,248 passed, 49 skipped**.

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| 1. Raw SQL and the "empty means all" filter | `91d0c27` | `RawQueryIsolationTest` (10, two businesses), `RawQueryScopeTest` (2) | 1,250 passed, 59 skipped | 1,260 passed, 49 skipped | **Reviewed all 34 files.** Raw clauses on Eloquent queries keep the model's tenant scope (the models, `CustomerController`, `PaymentController`, `CampaignController`, `AlertController`, `LowStockAlertController`, `AttributeController`, `ExpenseController`, most services, `ProductObserver`, the Baileys listener; `StorePurchaseReturnRequest` reads child rows by validated ids). The gap is the query builder (`DB::table`), which no scope reaches: `TenantContext::restrictToShops()`/`restrictToBusiness()` now limit such queries to the viewer's shops in the current business, with the model scopes' missing-business guard. Applied to every listing and aggregate read: the four `applyShopFilter` helpers (sales, e-commerce orders web and API, dashboard; an owner used to get **no filter at all**, so totals counted every business), the dashboard's selected-shop queries, the flat API reports, `VatReportService`, `ReportService`, low-stock checks, inventory snapshots, `products:assign-shop-from-sales`. **"Empty means all" removed:** `ReportController` always passes the viewer's accessible shops and `ReportService` always applies them (an empty list is no shops; a sale with no lines counts by its own shop). **Users** carry no tenant scope (one login, several businesses), and every user listing showed every business's users: a `User::inCurrentBusiness()` scope now limits `UserService` (list, search, statistics, by role), the shop manager and staff pickers and the abandoned-cart staff list; `search()` also had its `orWhere` escaping `active()`. Internal reads and writes by id (stock ledger, invoice series, default register) stay as they are. `RawQueryScopeTest` lists every file using the query builder with its count and reason, so a new raw query fails until it is listed. On the old code 6 of the 10 isolation tests fail (dashboard, reports, sales statistics, VAT, e-commerce statistics, users). Test adapted (1): the attribution test names the report's shops |
| 2. Exports and audit | `2e157d4` | `AuditIsolationTest` (4), `RawQueryIsolationTest` +3 (report and VAT CSV, customer PDF) | 1,257 passed, 59 skipped | 1,267 passed, 49 skipped | **Audit:** an additive audit migration (`database/migrations/audit/2026_10_16_000001`) gives `audit_logs` a nullable, indexed `business_id`; the `Auditable` trait records the record's own business, else the current one, and role and permission grants record the current business. `AuditService` reads only the current business (no business set: no rows) and, inside it, the shops the viewer may access (default-deny; it used to show everything to a user with no shop assignments). **Another business's audit row is a 404:** the row bound by uuid had no scope and business owners pass every policy, so `AuditLog` now binds only the current business's rows, and the policy checks the business too. Rows written before this have no business until `php artisan audit:backfill-business` (dry run; `--apply` writes) fills it from the row's shop, else its record's business, else the only business when there is one; rows still unresolved stay hidden. **Exports:** the report and VAT CSV use the services scoped in step 1, the audit CSV uses `AuditService`, and the PDFs (credit statements, purchase history, sale and order invoices) render records that come through scoped route binding; tests prove the CSVs carry only the viewer's business and another business's customer PDF is a 404. The saved-export list and download are stubs that return nothing |
| 3. Background work: jobs, scheduler, commands | `5672063` | `BackgroundWorkTest` (6) | 1,263 passed, 59 skipped | 1,272 passed, 49 skipped, 1 failed (the known intermittent `StockIntakeTest`, fixed in this commit) | **Jobs:** every queued job, listener, notification and mail carries the business it was dispatched for, through Laravel's hidden log context: `TenantContext::CONTEXT_KEY` is written into the payload when it's queued, and the worker acts for that business again when the job runs (it resets the tenant before reading the payload). Covers the 10 jobs and the 6 queued notifications with one mechanism. **Fail closed:** code with no signed-in user and no business used to run unscoped with a warning in production "until stage 1c"; it is now refused as in strict mode (`MissingTenantContext`), since jobs, commands and the scheduler all act for a business. **Scheduler:** both sweeps run once per business through `TenantContext::eachBusiness()`, skipping any business that isn't active (`Business::runsBackgroundWork()`; read-only joins in Phase 4); a failure in one business is reported and the rest still run. **Commands:** the 8 that work on tenant data (`products:assign-shop-from-sales`, `credit:backfill`, `products:backfill-shop-pivot`, `customers:normalize-phones`, `baileys:prune-media`, `sales:recompute-profit`, `products:repair-purchase-cost`, `ecommerce:sync-products`) take `--business=<id or uuid>` through `RunsForBusiness`; without it they use the only business, and refuse when there are several. The rest are platform tools (dataset, snapshots, legacy import, contract diff, audit backfill). **A cross-business deletion prevented:** run per business, `baileys:prune-media --orphans` would have taken every other business's session directories for orphans and deleted their media; orphans are now judged against every business's sessions, and other businesses' directories are left to their own run (the test fails on the old code). **Flaky test fixed:** `StockIntakeTest > authorized user can create stock intake` (intermittent since stage 1a) ordered a random 10–1000 units and received 50; below 50 that is a validation error, which also redirects, so the row was missing about 1 run in 25. It now orders 100 and asserts no validation errors |
| 4. Cache keys per business | `14d9a1d` | `CacheKeyScopeTest` (1), `BackgroundWorkTest` +1 | 1,265 passed, 59 skipped | 1,278 passed, 49 skipped (this run overlapped the start of step 5; step 5's full run re-verifies both) | `TenantContext::cacheKey()` prefixes the current business and refuses with none. The two global SMS keys (`sms_settings`, `sms_query_parameters`, kept a year) and the Baileys media-usage key now use it; `CacheKeyScopeTest` fails on any file that uses the cache without it. **Found:** `SMSservice` reads `App\Models\Setting`, which doesn't exist (no model, no `settings` table), so on a cache miss SMS sending fails: the SMS fallback can't be working today. Not fixed here; per-business SMS settings are Phase 6 |
| 5. E-commerce stock push per shop | `c47a438` | `ShopStockPushTest` (3) | 1,270 passed, 59 skipped (step 6's clean run) | 1,280 passed, 49 skipped | Every stock change goes through the ledger, so the ledger now queues `PushShopStockJob` for the shop whose stock changed, after the change commits, when that shop has a WooCommerce or Shopify store connected. The job sends that shop's own figure (`syncInventory`, which reads the shop's ledger quantity) to that shop's stores only, and is unique per shop and product while waiting, so a burst of sales sends the latest figure once. Not while a store's own figures are being written in (`ProductObserver::$syncInProgress`), so a store never gets its change echoed back. `ProductObserver` still pushes product details on `Product::updated`. Products with variations push their default variation only, as `syncInventory` always has (follow-up) |
| 6. Temporary signed links | `7d423eb` | `SaleInvoiceDeliveryTest` +2 | 1,270 passed, 59 skipped | 1,280 passed, 49 skipped (a clean run of steps 4–6 together) | Invoice, statement and order-invoice links (`sales.invoice-pdf`, `credit-accounts.statement-pdf`, `orders.invoice-pdf`) used `signedRoute` and never expired; they now come from `App\Support\DocumentLinks::signed()`, a `temporarySignedRoute` valid for `config('invoicing.document_link_days')` (`DOCUMENT_LINK_DAYS`, default **30**). An expired link gets the `signed` middleware's 403. The WhatsApp order template's button suffix now carries `expires=` before the signature. Links already sent stay valid (they carry no expiry); new ones expire. `.env.example` gains the key and its invoice-format comment is corrected to the per-register form. Test adapted (1): the order notification asserts the new suffix |
| 7. Business files: private disk, prefix, quota, move | `0e0d14f` | `TenantFilesTest` (6); media tests adapted (2 files) | 1,276 passed, 59 skipped | 1,286 passed, 49 skipped | `App\Support\Tenancy\TenantFiles` puts every file under `{business uuid}/` and enforces a per-business quota (`TENANT_FILES_QUOTA_BYTES`, default **2 GB**; `businesses.settings['files_quota_bytes']` overrides; usage cached per business). A new private `tenant` disk (`storage/app/tenant`, outside the web root) holds **WhatsApp media** (inbound and inbox uploads) and **profile photos**, served only through the signed-in `tenant-files.show` route (`/files/{path}`): another business's file, a path outside the folder (`..`), or WhatsApp media of a session in a shop the viewer can't access is a 404. **Product images stay public** under `{business uuid}/products/` (decided 15 October 2026): the shipped Flutter app builds `{base_url}{image}` without an auth header and the online stores fetch them. Over quota: an upload is a validation error; inbound WhatsApp media and downloaded platform images are skipped and logged. `baileys:prune-media` prunes both the private folder and the legacy public one (still guarded against other businesses' sessions). **Existing files:** `php artisan files:move-to-business --business=<id>` (dry run; `--apply` copies, checks each copy and repoints the record; `--delete-originals` afterwards removes originals whose copy matches). A profile photo belongs to the business it was uploaded in (a member of several sees it only there) |

### Differences from the stage file

- **Product images stay public** (decided 15 October 2026), under `{business uuid}/products/`: the shipped
  Flutter app builds `{base_url}{image}` without an auth header and the online stores fetch them. WhatsApp
  media and profile photos are private as the stage file says.
- **User listings are scoped too** (not in the stage file's list): users carry no tenant scope, and the
  users page, its search and statistics, and the manager and staff pickers showed every business's users.
- **8 commands take `--business`, not 9**: the 8 that work on tenant data. The others are platform tools
  (dataset, snapshots, legacy import, contract diff, audit backfill).
- **Code with no business is refused in production too.** Stage 1a let jobs and commands run unscoped,
  with a warning, until this stage; with jobs, the scheduler and commands acting for a business, the
  fallback became a refusal.
- **Audit:** the audit database's part in the business export and closure erasure, and **notifications:**
  their links switching to their business, come with the closure flow and the business switcher (Phase 3).
- **Two leaks found outside the listed work:** another business's audit row opened by uuid (owners pass
  every policy), and the Baileys orphan sweep, which run per business would have deleted every other
  business's media.

### Moving UAT onto stage 1c (for you to run; nothing here has touched UAT)

Stage 1c adds no migration to the main database. It adds one to the audit database, and moves files.

1. **Stop the queue workers and let `jobs` drain** (`select queue, count(*) from jobs group by queue;`):
   jobs queued before this stage carry no business and are refused.
2. Snapshot: `php artisan db:snapshot`, and copy `database/audit.sqlite` and `storage/app/public`.
3. Audit column: `php artisan migrate --database=audit --path=database/migrations/audit`, then
   `php artisan audit:backfill-business` (dry run) and `--apply`.
4. Files: `php artisan files:move-to-business --business=<id>` (dry run), then `--apply`. Check product
   images, profile photos and the WhatsApp inbox, then `--apply --delete-originals`.
5. Make sure `storage/app/tenant` is writable and included in backups.
6. Restart the workers (with the full `--queue=` list) and the scheduler.

### End of stage 1c (16 October 2026)

- **Checks on the whole branch:** SQLite 1,276 passed, 59 skipped; MySQL 8 1,286 passed, 49 skipped
  (the flat API contract test runs within it); Pint passes on every changed file
  (`pint --test --diff=origin/main`).
- **Acceptance criteria:** all ticked with evidence in the stage file; hotspots for queries, background
  work, cache and files ticked in saas-plan.md section 2 (audit export and notification links noted as
  partly done).
- **PR checklist (`.ai/general/PR_CHECKLIST.md`):** no debug code, secrets or `env()` outside config; the
  one migration (audit connection) is anonymous with `up()` and `down()` and additive; the new route
  (`tenant-files.show`) is authenticated and authorized by business, shop and path; validation goes through
  the existing FormRequests (quota errors are validation errors); the flat API keeps its shape (contract
  test). `.env.example` gains `DOCUMENT_LINK_DAYS`; `TENANT_FILES_QUOTA_BYTES` has a config default.
- **CLAUDE.md** describes `restrictToShops()`, `User::inCurrentBusiness()`, `cacheKey()`, `TenantFiles`,
  queued work carrying its business, `RunsForBusiness`, expiring document links and the new commands.

### After the stage: duplicate credit transaction numbers

The CI dataset build failed on a credit sale: `Duplicate entry 'CTX-260718-00001'`. A credit transaction
number was today's count plus one, and since stage 1a that count ran through the shop-access scope, so a
cashier who sees one shop issued a number another shop held (and two sales at once could read the same
count). Numbers are now the date plus a random suffix, like the other generated numbers;
`CreditTransactionNumberTest` fails on the old code. Checks: SQLite 1,277 passed, 59 skipped; MySQL 8
1,287 passed, 49 skipped. **The bug is on `main` too**, and the rehearsal job builds its dataset with
`main`'s code, so the fix is also on its own branch, `fix/credit-transaction-number` from `main`, to merge
first.

### Follow-ups

- **Missing views (already on `main`):** `sales.dailyReport` and `sales.profitReport` render
  `sales.daily-report` and `sales.profit-report`, which don't exist: both pages are a 500.
- **SMS can't be working (already on `main`):** `SMSservice` reads `App\Models\Setting`, which doesn't
  exist; per-business SMS settings are Phase 6.
- **Profile photos in the Flutter app:** a new photo's `profile_photo` is a private path, served only to a
  signed-in web session. If the app shows avatars from `{base_url}{profile_photo}`, they won't load;
  check with `mobile:contract-diff` where the app is (not on this machine).
- **Stores get a product's default variation only:** `syncInventory` has always pushed the default
  variation; per-variation pushes need the platforms' variant ids.
- **File columns with no upload code yet** (expense receipts, purchase order and intake attachments,
  category images, report exports): when they gain uploads, use `TenantFiles`.
- **`mobile:contract-diff` not run** (no Flutter app on this machine); no routes in `routes/api.php`
  changed, and the flat API contract test passed at every step.

## Stage 2 (Phase 2): Tenant isolation test suite

Branch: `saas/phase-2-isolation` (from `main` at `c79e357`, the merge of stage 1c and the credit transaction
number fix). Plan: the stage file itself.

### Starting result

`main` at `c79e357`: **SQLite 1,277 passed, 59 skipped; MySQL 8 1,287 passed, 49 skipped** (the stage 1c
branch with the credit transaction number fix, verified before the merge).

### Known before starting

- **Sign-up leaves the new user in no business:** every page after sign-in is refused (local) or empty
  (production). The fix, sign-up creating a business and its owner, is a Phase 3 item; decided 16 October
  2026 to finish Phase 2 first.

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| 1. Canary crawl | `7d80ffe` | `TenantIsolation/CanaryCrawlTest` (5), `tests/Support/Canaries.php` | 1,282 passed, 59 skipped | 1,292 passed, 49 skipped | Every parameter-free GET route behind sign-in (about 100 web, 27 flat API) is requested as business A's owner, a cashier limited to one shop, and the member of both businesses, and as business B's owner the other way round; no response may contain the other business's markers (names and record uuids, planted by `Canaries::plant()` across 13 kinds of record on top of the fixture's). A route that errors fails the crawl, except 11 already broken on `main` (missing controller methods and views), listed with their reasons, and one that fails on SQLite only (`MONTH()`). Proved by mutation: with `BusinessScope` disabled the crawl reports 33 leaks. **Found and fixed:** `/pricing-rules` was a 500 whenever a business had a pricing rule (`PricingService::getStatistics()` keyed an array by an enum), and `/suppliers/credit-alerts/list` was a 500 on MySQL every time (ordered by `credit_utilization`, a computed attribute; SQLite reads an unknown name as a string, so the SQLite suite never saw it) |
| 2. Resource matrix | `594efcd` | `TenantIsolation/ResourceMatrixTest` (91) | 1,373 passed, 59 skipped | 1,383 passed, 49 skipped | Business B's record of each resource (19 web, 14 flat API) is shown, updated and deleted as business A's owner, who passes every policy, so only the tenant boundary stands in the way: each must be a 404 and leave the record unchanged (87 checks), using each route's own method. Creating with B's shop, supplier, customer or product is rejected (4). `/api/v1` and sync rows come with Phase 6. **Leak found and fixed: users.** Users carry no tenant scope (one login, several businesses) and the policy checks only the permission, so another business's user could be viewed, changed and deleted by id, and the flat API's delete succeeded. `User` now binds only members of the current business. **Also fixed:** `GET /api/users/{user}` was always a 500 (it loaded a `shop` relation that doesn't exist; now `shops`). `social-accounts.show` has no controller method (already on `main`), listed in the matrix |
| 3. Switching, tokens, webhooks, CI | `ddfaaa7` | `SwitchingAndTokensTest` (4), `WebhookIsolationTest` (2), `Phase6IsolationTest` (5 to do) | 1,379 passed, 59 skipped | 1,389 passed, 49 skipped | **Switching:** the member of both businesses, after choosing A, sees only A and B's customer is a 404 to read or change; after switching back, only the other. A chosen business the user doesn't belong to, or a suspended membership, falls back to the default business. **Tokens:** a flat API token acts only on the user's default business, whatever the session chose (`/api/v1` tokens bound to one business come in Phase 6). **Webhooks:** each shop's webhook takes only its own secret; another business's secret, or a delivery replayed at another business's shop, is refused, and the order job is for the named shop. **Phase 6 rows** (`/api/v1`, sync, M-Pesa) are written as pending tests to fill in when those endpoints are built. **CI:** a new `isolation` job runs `tests/Feature/TenantIsolation` and `tests/Feature/Tenancy` on SQLite and MySQL 8 (199 tests); it becomes a required check once branch protection marks it so (for you to set). Already covered by the stage 1a and 1c tests in `tests/Feature/Tenancy`, now part of the isolation suite: reports, CSV and PDFs, audit, jobs and the scheduler (`RawQueryIsolationTest`, `AuditIsolationTest`, `BackgroundWorkTest`), files (`TenantFilesTest`), uniqueness (`PerTenantUniqueKeysTest`) |

### Differences from the stage file

- **The isolation suite is two folders:** `tests/Feature/TenantIsolation` (this stage) and
  `tests/Feature/Tenancy` (the isolation tests written in stages 1a and 1c: reports, exports, audit, jobs,
  the scheduler, files, uniqueness), rather than moving the earlier tests.
- **`/api/v1`, sync and M-Pesa rows are pending tests** (`Phase6IsolationTest`), as the stage file asks.
- **"Required in CI" waits for you:** the `isolation` job exists and is green; marking its two checks as
  required is a branch protection setting on GitHub.

### End of stage 2 (16 October 2026)

- **Checks on the whole branch:** SQLite 1,379 passed, 59 skipped; MySQL 8 1,389 passed, 49 skipped (the flat
  API contract test runs within it); the isolation suite 199 passed on both; Pint passes on every changed
  file (`pint --test --diff=origin/main`).
- **Leaks found and fixed:** another business's users could be viewed, changed and deleted by id (step 2).
  No other leak: the crawl and the matrix are green, and the crawl reports 33 leaks with `BusinessScope` off.
- **Bugs found and fixed on the way (already in production):** the pricing rules page (500 once a business
  had a rule), supplier credit alerts on MySQL (500 every time), `GET /api/users/{user}` (500 every time).
- **CLAUDE.md** records the rule: every new model, route or endpoint adds its row to the resource matrix.

### Follow-ups

- **Mark the isolation checks as required** (you): GitHub → Settings → Branches → the rule for `main` →
  "Require status checks to pass" → add "Tenant isolation (sqlite)" and "Tenant isolation (mysql)" (they appear
  once the job has run on a pull request).
- **Sign-up still leaves the new user in no business** (Phase 3's first item, decided 16 October 2026).
- **Routes already broken on `main`** (listed in `CanaryCrawlTest` and `ResourceMatrixTest`): 11 missing
  controller methods and views (the daily and profit sale reports, pending and completed sales lists, credit
  alerts, stock movement reports, inventory valuation, `/api/inventory`), `SocialAccountController::show`,
  and `/expenses/report/summary` on SQLite only (`MONTH()`). Not tenancy; worth a cleanup pass.
- **`mobile:contract-diff` not run** (no Flutter app here). `GET /api/users/{user}` now returns `shops` (it
  never answered before).
- **Flaky test fixed after the stage:** `SupplierTest > nearCreditLimit scope filters correctly` failed in CI
  (0 of 2 suppliers found). The factory drew the ratio once, from 0.90–0.99, for both suppliers; at exactly
  0.90 the balance rounded down (50,000.06 × 0.90 = 45,000.054 → 45,000.05), below the scope's 90%, about
  one run in 25. It now draws per row, at 91–99%, rounded up to the cent.

## Stage 3 (Phase 3): Accounts, roles, onboarding, business settings

Branch: `saas/phase-3-accounts-settings` (from `main` at `4d24c3e`, the merge of stage 2). Plan: the stage
file itself.

### Starting result

`main` at `4d24c3e`: **SQLite 1,379 passed, 59 skipped; MySQL 8 1,389 passed, 49 skipped** (stage 2's end
of stage, verified before the merge).

### Known before starting

- **Sign-up leaves the new user in no business** ("No business is set for this Shop" on the dashboard).
  Fixed by this stage's sign-in step.

### Decisions taken at the start (16 October 2026)

- **M-Pesa:** an interface now, with a fake for tests; the private package is plugged in later.
- **Till and Paybill ownership:** a shortcode belongs to one shop platform-wide, and is accepted only after
  a successful test query with the shop's own account details.

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| 1. Roles: platform operator, owners, per-business templates | `15b0563` | `Tenancy/RoleTemplatesTest` (5); `RolesPerBusinessTest` +1 (platform operator) | 1,385 passed, 59 skipped (4.6 min) | 1,395 passed, 49 skipped (19 min) | **Platform operator:** the `users.is_platform_operator` flag (never fillable), the only user `Gate::before` lets do everything. **Owner:** the shared `admin` role, held per business (`User::isBusinessOwner()`); the shared `super-admin` is renamed `admin`. **Templates:** manager, cashier, inventory clerk, accountant and customer are copied into each new business with their permissions (`CopyRoleTemplates`, from `Business::created`); role lists and lookups by name use the business's own copy, which hides the template; custom roles as before. `RoleSeeder` grants permissions added later to each business's copies (permissions created after the copy), so a rollout reaches existing businesses without restoring a permission a business removed. Migration `2026_10_16_000001`: adds the flag, renames `super-admin` to `admin` (or moves its holders if `admin` exists), copies the templates for every existing business and moves each member's grant onto their business's copy; `down()` reverses it. The legacy import does the same for the imported business. **Rehearsed** on `import_testing` (the old development database import): 2 owners on the shared `admin`; business 1's copies created; 3 managers and 1 cashier moved onto them; 6 grants before and after. **Tests faster:** `PermissionSeeder` now inserts its 203 permissions in one query (it made two per permission, about a second per test on MySQL); unchanged for existing rows, so re-running it on UAT is still safe. Tests that need an owner use `ownerRole()`; the resource matrix no longer seeds the role templates. Tests adapted: the owner role is `admin` (about 20 files) |
| 2. Sign-up creates a business and its owner | `f8820d9` | `Auth/RegistrationTest` +7 (9) | 1,392 passed, 59 skipped (3.6 min) | 1,402 passed, 49 skipped (10.5 min) | **Fixes the sign-up error** ("No business is set for this Shop"). Sign-up asks for a business name and creates the login, the business and an active owner membership (`admin` in that business, the user's default), in one transaction (`RegisterBusiness`); honeypot on the form, and `ProtectAgainstSpam` on the route (the package was installed but its middleware was applied nowhere, so the `@honeypot` fields in other forms still do nothing). The business starts `pending_verification`: every page sends the owner to verify their email; verifying opens it and sets `businesses.trial_started_at` (`StartTrialOnEmailVerified`), once. Migration `2026_10_16_000002` adds that column (additive; rehearsed on `import_testing`, the existing business stays active with no trial start). A signed-in user with no usable business gets a "No active business" page (403) instead of an error (`EnsureActiveBusiness`, the `business` middleware on the dashboard and every signed-in page); the business picker replaces it next. **Existing logins are not asked to verify:** `User` doesn't implement `MustVerifyEmail` (2 of the 25 users in the UAT copy have never verified, and would be locked out of the dashboard); the link is sent by the sign-up itself. The verify-email page restyled in the Bootstrap theme. **Test speed:** the role migration test no longer drops a column (on MySQL that committed the test's transaction and made the next test rebuild the database, about a minute; `6fc6b98`) |
| 3. Business picker, switcher, default business (web) | `d43d696` | `Tenancy/BusinessSwitchingTest` (9); `ResourceMatrixTest` +2 (opening or defaulting B as A's owner) | 1,403 passed, 59 skipped (3.5 min) | 1,413 passed, 49 skipped (10.8 min) | **Sign-in (4.4):** one usable business opens straight away (also when the default is no longer usable); several open the usable default; several with no usable default go to the picker (`/businesses`), and the choice becomes the default. An inactive membership or a suspended business is never opened (`ResolveTenant`, `User::usableBusinesses()`). **Picker:** each usable business with Open and Make default, current and default marked. **Switcher:** in the top bar for a user in more than one business; switching keeps a usable default. Opening or defaulting a business the user can't work in (another business, suspended, inactive membership) is a 404 (`BusinessSwitchController`). JSON requests with no business chosen get 409 "Choose a business". The web half of the sign-in acceptance criteria; `/api/v1` is the next item. Refusing a form loaded before a switch is its own item (People) |
| 4. `/api/v1` sign-in, businesses, switch, sign-out | `223a564` | `Tenancy/ApiV1SignInTest` (12); `Phase6IsolationTest`: the `/api/v1` token row filled in (4 still to do); `ResourceMatrixTest` +1 (switching a token to B) | 1,417 passed, 59 skipped (3.6 min) | 1,427 passed, 49 skipped (10.7 min) | `POST /api/v1/auth/sign-in` (throttled like `/login`) follows the web rules: an optional `business` uuid, else the usable default, else the only usable business, else no token and the list (`business_required`), and the named choice becomes the default when there was no usable one. `GET /api/v1/businesses`; `POST /api/v1/businesses/{uuid}/switch` issues a token for the other business and revokes the one used (404 for a business the user can't work in); `POST /api/v1/auth/sign-out`. **Tokens bound to a business:** migration `2026_10_16_000003` adds `personal_access_tokens.business_id` (nullable, cascades with the business; additive, rehearsed on `import_testing`); `ResolveTenant` uses it while the user can still work there, so a removed or suspended membership cuts the token off at once and it is never moved to another business. Flat API tokens (no business) still act on the default business, and are refused on `/api/v1` with 403, not 401, so the app isn't signed out (`EnsureBusinessToken`). Responses `{success, message, data}`. `docs/flutter/15-api-v1-sign-in.md`. The shipped app doesn't call these yet: no app change needed. `mobile:contract-diff` not run (no Flutter checkout here) |
| 5. Invites | `c21f255` | `Tenancy/InvitesTest` (11); `ResourceMatrixTest` +2 (resending or withdrawing B's invite); an invite canary per business in the crawl | 1,430 passed, 59 skipped (3.9 min) | 1,440 passed, 49 skipped (11.5 min) | Users → Invite (`users.create`): email, role, shops; the link expires in 7 days and only its token's hash is stored (`business_invites`, migration `2026_10_19_000001`, additive, rehearsed on `import_testing`); a second invite or a resend replaces the link; pending invites can be resent or withdrawn. **No escalation through invites:** only an owner invites an owner; anyone else only a role whose permissions they all hold; another business's role or shop is refused, and so is an active member's email. **Accepting** (`AcceptBusinessInvite`): a new email creates its login on the invite page (verified, since the link proved the email); an email with a login signs in and the invite adds a membership to **that** login (decision 4.4), with the role held in the invited business only (B's owner stays B's owner and joins A as invited); a former member is restored; another signed-in login is told the invite isn't theirs; a used, withdrawn or expired link is "no longer valid". The email is sent straight away, not queued (the plain token exists only in it). Two scope lifts listed in `TenantScopeBypassTest`: finding an invite by its token (opened signed out or in another business), and assigning its shops to a joiner who can't see any yet (the business scope still applies). Schema item "invites, exports and closure tables": invites done, the rest with closure |
| 6. `UserPolicy`: owner protection, only owners remove | `277fea0` | `Tenancy/PeopleTest` (7) | 1,437 passed, 59 skipped (3.8 min) | 1,447 passed, 49 skipped (11.4 min) | **Owner protection:** a non-owner can't change, suspend or remove an owner (`users.full-access` no longer bypasses this); only owners remove people, never themselves; the last owner can't be removed or demoted. **Removing = leaving this business** (`RemoveFromBusiness`): membership inactive, roles and shop assignments here detached, `/api/v1` tokens for it revoked; the login stays (other businesses, authorship); an invite restores it. **Roles given** (`GrantableRole`, shared by invites and Add/Edit User on the web and API): only an owner gives `admin`; others only roles within their own permissions. **Already on `main`, found and fixed:** the flat API's user endpoints had no authorization at all (any signed-in user, a cashier included, could create an owner, change any member's email or password, or delete users; now authorized like the web, 403 when refused); "delete user" hard-deleted the login (`User` has no soft deletes), cutting the person off from every business; Add User (web and API) created logins in no business since stage 1a (they now join the current one, role held there); `UserService::getActive()` listed users of every business; the user screens' Remove and Edit buttons checked permissions that don't exist (`users.destroy`, `users.edit`), now the policy. `DeleteUserAction` removed (unused). **Still open:** suspending or deactivating changes the login's status everywhere (next item, account status) |
| 7. Account status (web and API); status per business | `c1d9fa1` | `Tenancy/AccountStatusTest` (8); `LegacyImportTest`: imported accounts are active | 1,445 passed, 59 skipped (6 min) | 1,454 passed, 49 skipped, then the contract test after re-recording its routes (see notes) (18.6 min) | **Decided 19 October 2026: status is per business.** Suspending, deactivating or activating from a business's screens or the API's `status` field changes only the membership there (`UserService::setBusinessStatus`); the login and other businesses are unaffected. The API's `status` keeps its name and values and shows the status in the token's business (`User::presentBusinessStatus()`), so the Flutter app needs no change; the list filter and the Users page counts use it too; an unknown `status` is now 422 (it was a 500). **Enforcement** (`EnsureAccountActive`, on every web and API request): an account that isn't active (the platform operator's, Phase 5) is signed out on the web and refused on the API with 403; web sign-in now refuses it (it didn't check status). The flat API's data routes need a usable business (403 otherwise, was an error or empty lists); `/api/user` and `/api/logout` don't. **Data carried across:** migration `2026_10_19_000002` moves each inactive or suspended account's status onto its memberships and makes the account active (they were set by the business before there were several); `down()` puts it back on accounts with no active membership. Rehearsed on `import_testing`: accounts 20 active, 3 inactive, 2 suspended → 25 active; memberships 20 active, 5 inactive → 20 active, 3 inactive, 2 suspended. The legacy import does the same. `User` defaults `status` to active (a login just created reads as active before it's reloaded). **Contract fixture:** the flat API routes are re-recorded on MySQL for the `business` middleware only (112 data routes; the diff is that line alone); the recorded responses are unchanged and still pass |
| 8. Deactivation instead of self-delete | `cf88b69` | `Tenancy/SelfDeactivationTest` (2); `ProfileTest`'s deletion test restated for deactivation | 1,447 passed, 59 skipped (6 min) | 1,457 passed, 49 skipped (17.7 min) | `DELETE /profile` (password confirmed, as before) deactivates instead of hard-deleting the login (`DeactivateOwnAccount`): the account becomes inactive, signed out, every API token revoked; the login, its memberships and its records' authorship stay, for the platform operator to restore. The only owner of a business is refused ("make someone else an owner first"); an owner with a co-owner may go. The profile form is rebuilt in Bootstrap (the Breeze modal needed Alpine, which the Bootstrap layout doesn't load, so it never opened). One test changed in meaning, not removed: "user can delete their account" asserted the login was gone; it now asserts the login stays, inactive |
| 9. Data export and business closure | `10fd215` + fix | `Tenancy/BusinessClosureTest` (4), `BusinessErasureTest` (4); matrix row | not run | not run | Built by the earlier commit "Feature: Account deletion": owners export (ZIP of every row found by foreign keys, audit rows, files; secrets stripped), close with an emailed code, 30-day grace, cancel, daily `businesses:erase-closed` erasing main rows, the business's own logins, audit rows and both disks. **Reviewed and fixed here:** the canary export planted by `Canaries` made four tests' `BusinessExport::sole()` find two rows; the erasure now deletes files one by one before the folder (a folder delete that meets one stubborn file left everything else readable; on Windows it surfaced as an `Lstat` failure that failed the command) |
| 10. Ownership handover; one business per session; shops never move | `56a73d0` | `Tenancy/HandoverTest` (6), `FormBusinessTest` (5) | not run alone | not run | **Founding owner:** `businesses.primary_owner_id` (nullable, `nullOnDelete`; migration `2026_10_20_000001` fills the earliest active owner of each existing business; sign-up sets it). Other owners can't change, demote or remove that owner (`UserPolicy`, `UserService`); only they start a handover. **Handover:** a code emailed to them names an existing, active co-owner (checked again on confirming); the previous owner stays a co-owner. **One business per session:** `@csrf` now also writes `_business` (the uuid the page was loaded for) and `VerifyFormBusiness` refuses a state-changing request naming another business than the active one ("This business was switched; reload the page", 409 for JSON); scripts may send `X-Business`. A request that names no business is not judged (older pages, webhooks). **Shops:** `Shop` refuses to change its `business_id`; deleting is the only way out |
| 11. Shop M-Pesa Till/Paybill; `business_integrations` | `7349165` | `Tenancy/ShopMpesaTest` (8) | not run alone | not run | `MpesaGateway` interface with `NullMpesaGateway` bound until the private package is plugged in (so nothing is accepted unproven) and `FakeMpesaGateway` for tests. `SaveShopMpesaAccount`: the number must be digits, belongs to one shop on the platform (a query-builder check listed in `RawQueryScopeTest`), is accepted only after the test query with the shop's own details, and stores them encrypted in `business_integrations` (`credentials` is `encrypted:array`, hidden, never audited or exported). Blank fields keep the stored details for the same number. Deleting a shop frees its number. The e-commerce order message now names the shop's own Till or Paybill, and omits the payment line's number when none is set (the hard-coded `577 42 79` is gone). Migrations `2026_10_20_000002/3` (additive) |
| 12. Business settings on receipts, PDFs, amounts | `…` | `Tenancy/BusinessSettingsTest` (7) | not run alone | not run | Name, logo, receipt header and footer, currency, timezone and invoice prefix on Business settings (`settings.update`). `currency_code()`, `currency_symbol()`, `format_currency()` follow the active business (about 300 call sites untouched); `business_name()` replaces `config('app.name')` on every receipt and PDF; the invoice PDF carries the logo and header, and receipt and PDF the footer and the shop's Till/Paybill; new invoices use the business's prefix (numbers already issued keep theirs). **Still global:** `Supplier` and the AI and importer `'KES'` fallbacks, the phone country code and the Meta template's fixed URL prefix (the template is the tenant's own once it brings its WhatsApp Cloud account) |
| 13. Messaging credentials per business | `bea86a7` | `Tenancy/MessagingCredentialsTest` (6); `Pest.php` helpers `useWhatsAppAccount`/`useSmsAccount`; three existing test files moved onto them | 77 passed in the touched files | not run | `SMSservice` and `WhatsAppService` send with the active business's own saved account (`TenantMessagingCredentials`) and **never fall back to the platform's** when a business is set; only code acting for no business reads `.env` (`config('services.sms')`, new). The broken `SMSservice::send()` (used a `Setting` model that doesn't exist) is removed. Business settings → Messaging accounts saves them write-only (never shown again, blanks keep the value, `https` only) |
| 14. Notification links | `35b12b6` | `Tenancy/NotificationLinkTest` (4) | 118 passed in the touched files | not run | Notifications already carried their business (stage 1c). Their links now go through a signed `businesses.open` URL that switches the reader's session to that business first (404 if they can't work in it; the target must be a local path) |
| 15. Onboarding and CSV product import | `46ab8fc` | `Tenancy/OnboardingTest` (8) | not run alone | not run | A checklist on the owner's dashboard (business, shop, cash register, products, first sale) that ticks itself from the business's data and disappears when complete. CSV import (`products/import`): header row required, all-or-nothing with errors by line, products through the same action as the form, opening stock through `StockLedger`, up to 2,000 rows, template download. No new package (`fgetcsv`) |
| 16. `/api/v1` administration; isolation rows | `46ab8fc` | `Tenancy/ApiV1AdministrationTest` (10); `ResourceMatrixTest` +1 | not run alone | not run | Settings, onboarding, messaging accounts, a shop's M-Pesa, product import, export, handover and closure on `/api/v1` (`{success, message, data}`); cancelling a closure stays on the web, since a token for a closing business stops working. **Found by this test:** `primary_owner_id` made `businesses` and `users` a foreign-key cycle that broke the export and erasure ordering; the ordering now ignores that pointer |

### Differences from the stage file

- **Deferred: foreign keys from business records to `users` as `nullOnDelete`.** 100 foreign keys in 52
  migrations are plain (restrict), and some of their columns are NOT NULL. Rewriting them is a data-bearing
  migration that has to be rehearsed on MySQL 8; the local server here defaults to MyISAM (it cannot even
  build the baseline), so it was not attempted. Nothing in the app hard-deletes a login any more (removal,
  deactivation and closure), so the risk is low; do it on a proper MySQL 8 with a snapshot.
- **Deferred to Phase 4:** the trigger that closes a business after 30 business days of lapse (the closure
  flow itself is built: `CloseBusiness::start(…, reason: 'lapsed')`).
- **On Windows**, `TenantScopeBypassTest` and `RawQueryScopeTest` fail because they compare paths with
  backslashes against the forward-slash lists; they pass on Linux (CI). Not changed here.
- **The platform operator is a flag, not a global `super-admin` role.** Roles are per business (Spatie teams
  on `business_id`): a role grant always carries a business, so a role held outside every business can't
  be expressed. `users.is_platform_operator` is set only by hand or by the platform console (Phase 5).

## Stage 4 (Phase 4): Billing, entitlements, metering

Branch: `saas/phase-4-billing` (from `main` after the stage 3 merge, `560e179`). Plan: the stage file itself;
how it works: [billing.md](billing.md).

### Steps

| Step | Commit | Tests added | Notes |
|---|---|---|---|
| 1. Plans, subscriptions, entitlements, calendar, trial | `c560cbc` | `Billing/EntitlementsTest` (10) | Migration `2026_10_21_000001`: `plans` (seeded: 1,500 / 3,000 / 5,000 per shop, placeholder limits, features and 14-day trial as data), `subscriptions`, `platform_payments`, `ai_credit_balances`, `ai_usage_events`, `ai_blocked_work`; every existing business gets a trial on the top tier so none is locked out. `Entitlements` (`hasFeature`, `limit`, `assertCanAdd`, `readOnlyReason`), `Feature` and `Limit` enums, `BusinessDays` (Monday to Friday minus a configurable holiday list). The trial starts on email verification (`StartTrial`). A business with no subscription is unmetered |
| 2. Limits, read-only mode, sweep | `97e857b` | `Billing/ReadOnlyTest` (10) | `creating` listeners on Shop, Register (per shop), Product, BaileysSession and BusinessMembership (`PlanLimits`) refuse past the plan on every path. `EnforceSubscription` refuses writes on web and API with `subscription_inactive` (402) when lapsed or over the shops paid for; allowlist in `config('billing.read_only_allowed')` (sign-in/out, switching, export, paying, closing, deleting shops, the callback). `billing:sweep` (daily): renewal reminder, closure warning 5 business days before the 30-business-day grace ends, closure on the 31st. Read-only businesses do no background work. **Found by the tests:** these listeners run before the model's own, which fills `business_id`, so they fall back to the current business; and middleware ordering needed `appendToPriorityList(ResolveTenant::class, …)` or the check ran before the business was resolved |
| 3. Payments, AI credit, billing page, API | `516c467` | `Billing/PaymentsTest` (19), `Billing/AiCreditsTest` (11); matrix row; `ReadOnlyTest` +1 | `ApplyPlatformPayment` (row lock + `applied_at`: exactly once; months at the locked price; reopens a business closed for the lapse), `RecordManualPayment` and `billing:record-payment` (by hand until go-live), `PaymentCollector` (null until the private package is connected; fake for tests) with `ConfirmPlatformPayment` believing only the API's answer (paid, amount, our reference, unique receipt), `POST /api/webhooks/platform-payments` as a nudge only. `AiCredits` (single conditional UPDATEs, so concurrent calls can't overdraw), `MeteredAiDriver` wrapped by `AiManager` for metered businesses, blocked work saved and re-dispatched after a top-up, owner notified once per empty spell. `/billing` page and `/api/v1/billing*`; `feature:` route middleware on campaigns, social accounts and WhatsApp |
| 4. Close the stage | `90a992c` | `ApiV1ContractTest` (2 checks + 1 recorder, `tests/Fixtures/api-v1-contract/`); `ResourceMatrixTest` +1 (`/api/v1` billing); a billing canary per business; `AiCreditsTest` +1 | Branch `saas/phase-4-close` from `main` at `6106dfc`. **Isolation:** each business gets a canary payment (a marker M-Pesa receipt and its check link) shown on `/billing`, which the crawl visits; on `/api/v1`, A's token gets a 404 checking B's payment, A's billing summary carries A's credit balance only, and a cashier is refused. **`/api/v1` contract:** all 22 routes and the shapes of sign-in and the five parameter-free GETs, recorded with the two-business fixture; passes on SQLite and MySQL; removing a recorded key fails it. Re-record with `vendor/bin/pest --group=api-v1-contract-record` (excluded from normal runs). The shape helpers moved to `tests/Support/ApiShape.php` for both contract tests (no change in what the flat test checks). **Top-up minimum:** the web form and `/api/v1` validate `amount` against `billing.ai_min_topup`, so under KES 500 is a field error (422 on the API); the payment actions still refuse it too. **Docs:** the stage file's definition of done ticked and the AI credit decision recorded as built; plan section 2 hotspots ticked with evidence (Phase 3's, and a new billing item) |

### Differences from the stage file

- **Platform go-live is a switch, not a date in code:** `BILLING_MPESA_ENABLED` is off and `NullPaymentCollector` is bound; the adapter over the private package is written when the package is available (Assumption 7: check it supports this Laravel). Payments are recorded with `billing:record-payment` meanwhile.
- **The flat API now answers 402 on writes for a lapsed or over-quota business** (and `plan_limit_reached` / `feature_unavailable`). The shipped Flutter app does not know these codes; it shows its generic error. Add handling before billing is enforced on production businesses.
- **Concurrency of AI credit** rests on the single-statement conditional update; there is a semantic test, not a multi-process one (unlike the invoice-number test).
- **Not built (post-launch, per the stage file):** platform invoices and platform VAT; proration when raising the shops paid for mid-period (the higher price applies from the next payment).

### End of stage 4 (21 October 2026)

- **Checks on the whole branch** (`main` with stage 4 merged, plus the close-out): SQLite **1,571 passed, 0
  failed**, 59 skipped (6.3 min); MySQL 8 **1,581 passed, 0 failed**, 49 skipped (15.3 min), including the
  flat API and `/api/v1` contract tests and the isolation suite; Pint passes.
- **Acceptance criteria:** all ticked in the stage file with tests (`Billing/EntitlementsTest`,
  `ReadOnlyTest`, `PaymentsTest`, `AiCreditsTest`). Two parts can only be tested once sync exists
  (Phase 6): read-only on the sync push, and accepting sales made before the lapse.
- **Starting result** was not recorded when the stage began; the run above covers all of `main`.

### Follow-ups

- **Go-live (expected around 12 November 2026):** write the `PaymentCollector` adapter over the private
  M-Pesa package and check it supports this Laravel version (Assumption 7); then turn on
  `BILLING_MPESA_ENABLED`. Until then payments are recorded with `billing:record-payment`.
- **Flutter app:** handle 402 `subscription_inactive`, `plan_limit_reached` and `feature_unavailable`
  before billing is enforced on live businesses; today the app shows its generic error.
- **Concurrency of AI credit** has a logic test, not a multi-process one like the invoice numbers.
- **Plan section 2 still open:** business settings (phone country code, `KES` fallbacks, the Meta template
  URL) and shop M-Pesa STK Push and webhooks (Phase 6).

## Stage 5 (Phase 5): Platform console and roles per business

Branch: `saas/phase-5-platform-console` (from `main` at `3a2fe4f`). Plan: the stage file itself
([07-phase-5-platform-console.md](stages/07-phase-5-platform-console.md)). **Status: in progress.**

### Scope (merged 21 October 2026)

Roles per business (the roles review, previously planned for a separate `fix/roles-per-business` branch,
now retired) and the platform admin console are one stage: Part A (roles per business) first, since the
console relies on the policy rules it sets; then Part B (the console). Depends on Phase 4 and
`fix/ui-button-links`, both merged. The signup, trials and invites stage follows (Phase 6,
`saas/signup-trials-invites`).

### Decisions recorded

- **Impersonation (21 October 2026):** the operator acts strictly as the impersonated user. No
  `Gate::before`, no template edits and no `/platform` routes while impersonating; operator rights return
  only when impersonation ends, manually or at its time limit.
- **Template changes (21 October 2026):** a template edit reaches new businesses and "Reset to default"
  only. Existing copies change only through an explicit, confirmed, audited "Apply to all copies".
- **Roles review (22 October 2026):** copied templates can't be deleted or renamed and can be reset to
  default; `customer` stays a protected template; `attributes.view`, `attributes.create` and `stock.view`
  (with `attributes.update`, `attributes.delete`) become real permissions; a role can be switched off,
  after a SweetAlert confirmation naming how many people hold it.

### Starting result

`main` at `3a2fe4f` (22 October 2026): **SQLite 1,622 passed, 0 failed, 59 skipped** (6.8 min); **MySQL 8
1,633 passed, 0 failed, 49 skipped** (15.6 min), including the flat API and `/api/v1` contract tests and the
isolation suite. No known baseline failures.

### Steps

| Step | Commit | Tests added | Suite (SQLite) | Suite (MySQL 8) | Notes |
|---|---|---|---|---|---|
| A1. Isolation and escalation | `d75e049` | `Roles/RolePolicyTest` (16); `ResourceMatrixTest` +6 (B's role: show, edit, update, destroy, permissions, updatePermissions) | 1,644 passed, 59 skipped (6.8 min) | 1,655 passed, 49 skipped (15.9 min), contract tests included | **Policy:** a business changes only its own roles (another business's role is already a 404 through route binding, now pinned in the matrix); shared templates and the owner role (`admin`) are read-only from a business, with a message; `roles.full-access` counts as each `roles.*` permission instead of passing everything (its blanket `before()` is gone); a non-owner can't change a role they hold. **No escalation:** a non-owner gives only permissions they hold, and the role's permissions out of their reach are kept on save (`SyncRolePermissions`), so a partial view never strips them. The form requests check the policy first, so a refused role is 403 before its fields are judged. **Names** (`RoleName`): unique within the business, never a built-in role's name; create and edit share one rule (`StoreRoleRequest`/`UpdateRoleRequest`, which existed unused). **Holders** (`Role::holders()`): counts, the role page and the delete check count only the current business (Spatie's `users()` spans every business). **Bug found on `main` and fixed:** Spatie's permission cache is shared by every business but was loaded through the role scope, so it held only the shared roles and the roles of whichever business's request built it; other businesses' staff lost their own roles' permissions until the cache was cleared. `TenantAwarePermissionRegistrar` (bound by an extender, since the package binds the registrar after the app provider) loads it with the scopes lifted; listed in `TenantScopeBypassTest`; a regression test fails without it. **Tests adapted (not weakened), `RoleTest`:** refusing the owner role is now 403 instead of a redirect with an error (the role still asserted unchanged); the two tests giving permissions now have the editor hold them |
| A2. Template link, protection, reset | `a47e435` | `Roles/TemplateCopiesTest` (9); `ResourceMatrixTest` +1 (B's role: reset); `DatabaseSnapshotTest`: the probe table gains a generated column | 1,654 passed, 59 skipped (6.9 min) | 1,665 passed, 49 skipped (19.7 min) | **Migration** `2026_10_22_000001` (`roles.template_id`, foreign key to `roles`, `nullOnDelete`): links existing copies by name; restores a copy a business deleted earlier (with the template's current permissions) and moves any grants of the shared template in that business onto it; `down()` drops the column, restored copies stay. Applied, rolled back and re-applied on `mysql_testing`. **Snapshot** before: `storage/app/snapshots/pos_saas_demo_testing-20261022-110703.sql.gz` (UAT, 101 tables, 19,486 rows), then `pos_saas_demo_testing-20261022-111812.sql.gz` after the snapshot fix below. **Rehearsed** on that snapshot restored into `rehearsal_testing`: roles 30 → 31, business roles 24 → 25 (**business 3 had deleted its accountant copy**; restored), linked copies 25 of 25, grants 52 → 52 (none were on a shared template), role permissions 1,529 → 1,559 (the restored copy's 30). Not run on UAT. `CopyRoleTemplates` sets the link; `RoleSeeder` rolls new permissions out to copies by the link instead of the name. **Protection:** a copy can't be deleted (policy, 403 with a reason) or renamed (`RoleName`, 422); its permissions can change; custom roles keep edit, rename and delete. **Reset to default** (`POST roles/{role}/reset`, `ResetRoleToTemplate`): own copies only; confirmed in a SweetAlert dialog that sets a server-side `confirm` field (a checkbox in `<noscript>` without JavaScript; 422 without it); a non-owner only when they hold every permission of the template (no escalation); audited with the permissions before and after. The reusable dialog is `partials/swal-confirm-script` (A3 uses it too). The role pages read a non-existent `is_system` column; they now show the real built-in state. **Bug found and fixed:** `db:snapshot` dumped generated columns (`attributes.alive`), so restoring any snapshot into MySQL failed (3105); they are now left out (the round-trip test fails without the fix). Snapshots taken before this fix can't be restored into MySQL. `tests/Pest.php` gains `migrateAuditDatabase()` |
| A3. Active and inactive roles | `3b085fb` | `Roles/RoleActiveTest` (9); `ResourceMatrixTest` +1 (B's role: active) | 1,664 passed, 59 skipped (10.2 min) | 1,675 passed, 49 skipped (25.5 min) | **Migration** `2026_10_22_000002` (`roles.is_active`, default true; additive). Snapshot before: `storage/app/snapshots/pos_saas_demo_testing-20261022-115152.sql.gz`. Applied, rolled back and re-applied on `mysql_testing`; **rehearsed** on the restored UAT copy: 31 roles, 31 active after; grants 52 and role permissions 1,559 unchanged. Not run on UAT. **An inactive role grants nothing:** `User` overrides Spatie's `hasPermissionViaRole()` and `getPermissionsViaRoles()` (Spatie's cache keeps the flag), so one change covers the web, the flat API, `/api/v1`, and the permissions in sign-in and `/api/user`; role names in responses are unchanged; holders keep the assignment and get the permissions back when it is switched on. **Switching** (`PATCH roles/{role}/active`, `SetRoleActive`): the A1 policy (never the owner role or a template; a non-owner never a role they hold); switching off needs `confirm`, set by the SweetAlert dialog naming how many people in the business hold the role (checkbox in `<noscript>`; 422 without); switching on needs none; the edit form's checkbox writes the state with the same confirmation (a hidden `is_active=0` makes "unticked" explicit; absent leaves the state alone); audited (`Auditable`); permission cache cleared. **Not given to anyone new:** `GrantableRole` refuses an inactive role (invites, Add/Edit User, web and API) unless the person being edited already holds it; the forms offer active roles plus the ones the person holds, so saving never drops a role. Accepting an invite sent before the role was switched off still assigns it (it grants nothing). The role list shows the state with a filter; the role page has a switch button. **Found, not changed:** the flat API's product list (`GET /api/products`) checks no permission at all (any signed-in member lists products); the test uses the people list instead (follow-up) |
| A4. New permissions and `AttributePolicy` | `21be769` | `Roles/NewPermissionsTest` (6), `ViewPermissionNamesTest` (1) | 1,671 passed, 59 skipped (10.9 min) | 1,682 passed, 49 skipped (26.3 min) | **Permissions** (`PermissionSeeder`, 203 → 208): `attributes.view`, `.create`, `.update`, `.delete`, `stock.view`. **Grants** (`RoleSeeder`): the manager template gets all five, inventory-clerk `stock.view`, owners all through `admin`, copies through the existing newer-permission rollout; customer unchanged. **Seeder change:** an existing template now gets only the permissions created since it last changed (`updated_at`), the rule copies already follow, so re-running the seeder no longer undoes a template edit (B6 touches the template when editing it); a template the run creates gets its full list. **Rehearsed** on the restored UAT copy (`PermissionSeeder` then `RoleSeeder`): permissions 203 → 208; role permissions 1,559 → 1,600, exactly the 41 grants of the new permissions (admin 5, manager template 5, inventory-clerk template 1, each of the 5 businesses' manager copy 5 and inventory-clerk copy 1); nothing else re-added. Not run on UAT. **`AttributePolicy`** on every `AttributeController` action (none was authorized; activate and deactivate count as `update`); the attribute views already asked the policy, which didn't exist, so their buttons were hidden from everyone. There are no attribute API routes. **Bug found and fixed:** `Attribute` had no `$fillable`, so creating, editing and switching attributes failed (500). **Views:** the sidebar's Attributes and Stock entries show for the roles that hold them; `ViewPermissionNamesTest` fails on any `@can`/`@cannot`/`@canany` naming a permission that isn't seeded or a gate (it lists exactly the three sidebar entries without the new permissions); `@can('view-api-docs')` was in the unused component sidebar, linking to a route that doesn't exist: removed |
| A5. Business-facing screens | `7870d06` | `Roles/RoleScreensTest` (7); `RoleActiveTest`'s "not offered" check made real | 1,678 passed, 59 skipped (8.7 min) | 1,689 passed, 49 skipped (20.1 min) | **Display names** (`Role::displayName()`): Owner, Manager, Cashier, Inventory clerk, Accountant, Customer; custom roles readable (`night-shift` reads "Night shift"); on the role, people, shop and invite screens and in the role pickers (the user edit picker marks a switched-off role the person still holds). Stored names unchanged: `/api/user` still reports `admin`. **Picker:** grouped by area with readable labels (the full name in a tooltip); only the permissions the editor may give (all for an owner); `*.full-access` explained beside it ("Counts as every permission in this area"); a non-owner is told the list is limited, and the edit form says how many of the role's permissions are out of their reach (kept on save, A1). **No fictitious fields:** the display-name and description inputs had no column and saved nothing: removed; the name example ("Store Manager") broke the name rule: now `night-shift`. **`GET roles/{role}/permissions`** rendered a view that never existed (500): it opens the edit form. **No "Super Admin"** on any business screen (crawl of the role and people pages; the controller messages went in A1). **Test fix:** A3's "switched-off role not offered" check matched `>clerk<`, which the select component's whitespace made impossible to find, so it could never fail; it now checks the option value on the Add User and invite forms. **Part A complete:** stage file ticked, except the console side of the template criterion (B6) |
| B4. `platform:create-operator` | `e09bcc4` | `Platform/CreateOperatorTest` (6) | not run (see note) | not run (see note) | Creates a platform operator outside every business (decision 4.7; `CreatePlatformOperator` action plus command): `is_platform_operator` set with `forceFill` (never mass-assignable, tested), verified, active, no membership, no role, no default business; `Gate::before` lets it do everything. Password asked twice, hidden (12+ characters, mixed case, number, symbol), or `--generate-password` (24 characters, printed once). A member's email is refused (an operator is never a business member); an existing login with no membership is promoted only with `--promote`, keeping its password. Creation audited (`Auditable` on `User`, no business). The safety hook now treats the command as changing the `.env` database (the user runs it on UAT); its fixture gains the case, and all 50 cases were checked on the host (the test skips in lerd's container, which has no `jq`). **Checks:** the step's tests, the unit guards, the isolation suite and both contract tests (514 passed), Pint. **Full suites deferred** at your request (22 October 2026): they run once when Part B is complete |
| B5. Console audit and access | `a80e669` | `Platform/PlatformAccessTest` (8) | deferred | deferred | **Access:** `/platform` (`platform.*`, `auth` + `EnsurePlatformOperator`): a 404 for anyone but a platform operator, and for an operator while impersonating (session keys read by `Support\Platform\Impersonation`, set in B3); no `business` middleware (an operator belongs to no business); operators are sent to `/platform` at sign-in and from business pages (JSON: 403). The test walks every `platform.*` route in the route list with business B's records as parameters (owner of A, B's cashier, the two-business member: 404; guest: sign in), so each later console route is covered on arrival. Overview page in its own layout (`layouts/platform`). **Audit:** `PlatformAudit::record()` writes every console action, reads included, with the operator, the business it concerns (its owner sees it in their audit log) and the action, tagged `platform`; actions concerning no business are recorded against `platform`, id 0 (`audit_logs.auditable_id` is NOT NULL; the audit schema is left alone). `AuditLogger` adds `impersonated_by:<operator id>` to rows written while impersonating. **Checks:** step tests, unit guards, isolation, both contract tests, audit and auth tests (578 passed), Pint |

### Differences from the stage file

- **There is no role API** (flat or `/api/v1`) besides `GET api/users/roles`, a list; the role criteria saying
  "web and API" are met on the web, and the API list is checked for isolation (A1).
- **The platform operator is `users.is_platform_operator`**, not a `super-admin` role (as built in Phase 3).
- **The owner role `admin` is one shared row**, not copied: a console edit (B6) changes every business's owners
  at once, so "Apply to all copies" doesn't apply to it.

### Follow-ups

- **Snapshots taken before `a47e435`** include generated columns and fail to restore into MySQL; take a new
  one (`php artisan db:snapshot`) before relying on a restore.
- **`.env`'s `DB_TEST_PORT=3307`** is the host-side port; lerd's PHP reaches MySQL on 3306, so artisan commands
  with `--database=mysql_testing` (or `rehearsal_testing`) fail to connect from lerd. The phpunit MySQL config
  forces 3306; rehearsals here used a local, uncommitted runner that does the same.
- **The flat API's product endpoints don't authorize** (`Api\ProductController` has no policy or permission
  check; on `main`): any signed-in member of the business can list and read products, inactive role or not.
  Adding a check may affect the Flutter till (cashiers hold `products.view` in the template); decide with the
  app owner.
- **Flutter:** no checkout of the app on this machine (`MOBILE_APP_PATH` points nowhere here), so
  `authorization_helper.dart` was not searched for `super-admin` as the owner role. Since Phase 3 the owner's
  role is reported as `admin`; check the app before release.
