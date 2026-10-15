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
| 1. Raw SQL and the "empty means all" filter | _this commit_ | `RawQueryIsolationTest` (10, two businesses), `RawQueryScopeTest` (2) | 1,250 passed, 59 skipped | 1,260 passed, 49 skipped | **Reviewed all 34 files.** Raw clauses on Eloquent queries keep the model's tenant scope (the models, `CustomerController`, `PaymentController`, `CampaignController`, `AlertController`, `LowStockAlertController`, `AttributeController`, `ExpenseController`, most services, `ProductObserver`, the Baileys listener; `StorePurchaseReturnRequest` reads child rows by validated ids). The gap is the query builder (`DB::table`), which no scope reaches: `TenantContext::restrictToShops()`/`restrictToBusiness()` now limit such queries to the viewer's shops in the current business, with the model scopes' missing-business guard. Applied to every listing and aggregate read: the four `applyShopFilter` helpers (sales, e-commerce orders web and API, dashboard; an owner used to get **no filter at all**, so totals counted every business), the dashboard's selected-shop queries, the flat API reports, `VatReportService`, `ReportService`, low-stock checks, inventory snapshots, `products:assign-shop-from-sales`. **"Empty means all" removed:** `ReportController` always passes the viewer's accessible shops and `ReportService` always applies them (an empty list is no shops; a sale with no lines counts by its own shop). **Users** carry no tenant scope (one login, several businesses), and every user listing showed every business's users: a `User::inCurrentBusiness()` scope now limits `UserService` (list, search, statistics, by role), the shop manager and staff pickers and the abandoned-cart staff list; `search()` also had its `orWhere` escaping `active()`. Internal reads and writes by id (stock ledger, invoice series, default register) stay as they are. `RawQueryScopeTest` lists every file using the query builder with its count and reason, so a new raw query fails until it is listed. On the old code 6 of the 10 isolation tests fail (dashboard, reports, sales statistics, VAT, e-commerce statistics, users). Test adapted (1): the attribution test names the report's shops |
