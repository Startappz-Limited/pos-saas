# Phase 0: Development Baseline

> **Source:** saas-plan.md section 5 (Phase 0), section 1 (Test coverage and environment, Existing
> deployment), section 4.7, section 8 (Open questions 1 and 4)
> **Branch:** `saas/phase-0-baseline` · **Depends on:** None

---

## Goal

A known, reproducible starting point for development, **set up to work as though the system is live and
data is present**.

## Before you start

| Open question | Status |
|---------------|--------|
| **1. Is there live data?** | **Answered 12 October 2026:** no live data yet, but **development works as though the system is live and data is present.** Every database except the test database is treated as holding live data, and every schema change must carry existing data across. |
| **4. MySQL or MariaDB in production?** | **Answered 12 October 2026: MySQL.** Local development and the second CI job use MySQL 8. |

### What "work as though live" means for every stage

- **No destructive commands** against any database other than the test database: no `migrate:fresh`,
  `migrate:reset`, `db:wipe`, `TRUNCATE` or dropping tables with rows.
- **Take a snapshot before any migration** on the local database, and keep a way to restore it.
- **Every schema change carries existing data across.** For the clean-baseline rewrite (decision 4.7),
  that means a **one-off import** from the current schema into the new one, as the first business, which
  the plan already describes for the case where data exists. Phase 1a builds it.
- **Development runs against a realistic dataset**, not an empty database.

## Relevant current state

- **Versions:** PHP 8.4 runtime; Laravel 12, with `composer.lock` pinning 12.56.0 while `vendor/` has
  12.69.3.
- **Tests:** `php artisan test --compact`. Pest uses `RefreshDatabase` on SQLite `:memory:` with the sync
  queue and array mail and cache drivers. **Nothing runs on MySQL or MariaDB**, so `lockForUpdate` does
  nothing in tests.
- **Baseline on `main`:** 1,065 passed, 9 failed (89 test files, about 1,074 tests):
  - 8 WhatsApp notification tests depend on the local `.env` WhatsApp settings;
  - 1 HTTPS test fails because `APP_URL` is `https`.
- **Database:** the local database carries migrations from another branch and doesn't match `main`. The
  `.env` uses `DB_CONNECTION=mariadb`; production will be **MySQL**, so the new local database is MySQL 8.
- **CI:** none today (`.github/` holds only agent instructions).
- **Flat API:** 118 routes (`{success, data}`) used by the existing Flutter app; `mobile:contract-diff`
  compares its `api_endpoints.dart` with the routes.
- **Live-data rule:** `CLAUDE.md` says the `.env` database (`ecohub_pos`) is on a remote shared server and
  should be treated as live production data, and a project safety hook blocks wiping it. **Both stay.**
  This stage extends the rule to every development database.
- **Signs of existing data handling:** a migration (`add_shop_id_to_products_table`) back-fills existing
  rows, and commands exist to repair imported purchase costs and back-fill the shop pivot and credit
  ledger. The new work follows the same care.

## Work

### Live-data rules
- [x] **Answer Open question 1:** no live data yet; develop as though live (12 October 2026).
- [ ] **Keep** the live-data rule in `CLAUDE.md` and extend it: every database except the test database is
      treated as live; no destructive commands against it; snapshot before any migration; schema changes
      must carry existing data across.
- [ ] Keep the project's safety hook that blocks wiping a live database.

### Environment
- [ ] `composer install` to match the lock file.
- [ ] **A new local MySQL 8 database**, created by you and pointed to from `.env`
      (`DB_CONNECTION=mysql`). Leave the current database untouched; the safety hook blocks wiping it.
- [ ] Point local mail at a development catcher (log driver or Mailpit), so invites and codes never reach
      real inboxes.

### Live-like dataset
- [ ] A **live-like dataset** on the current (`main`) schema, so the new local database looks like a
      system in use. Built by a local-only, reproducible seeder (fixed random seed) that refuses to run
      outside `local` and `testing`. It covers, at realistic volume:
  - several shops, with staff assigned to some shops and not others;
  - products with and without variations, stock on products and variations, and some `shop_product` rows;
  - stock intakes, adjustments and movements; purchase orders and returns;
  - sales through all four sale paths, with items, payments and refunds;
  - customers in several shops, credit accounts, credit transactions and payments;
  - expenses, suppliers, categories, attributes and the other shared lists;
  - e-commerce orders and abandoned carts;
  - **soft-deleted rows** in the soft-deleting models, and duplicate-looking codes across shops.
- [ ] **Snapshot and restore** for the local database (a script around `mysqldump` and `mysql`), so a
      migration or import can be rehearsed and rolled back.

### Tests and contracts
- [ ] Make the 8 WhatsApp tests independent of `.env`; fix or isolate the HTTPS test.
- [ ] Record the baseline.
- [ ] Record the flat API's current contract (routes and response shapes) as fixtures for the
      compatibility tests, taken against the live-like dataset so responses have real content.

### CI
- [ ] A CI pipeline that runs Pint, the full suite and the flat API contract tests on every push.
- [ ] A second CI job that runs the suite on **MySQL 8** (Open question 4), so raw SQL, row locks and
      unique keys are tested on the engine that runs them.
- [ ] A third CI job, the **migration rehearsal**: load the live-like dataset on the `main` schema, then
      run the branch's migrations and import, and check that no data is lost. In Phase 0 there's nothing
      to migrate, so it only proves the dataset loads; it becomes the gate for Phase 1a.

## Schema

None.

## Dependencies

| Need | Package or service |
|------|--------------------|
| Mail | A **development** mail catcher (log driver or Mailpit) |
| Snapshots | `mysqldump` and `mysql` (MySQL 8 client tools) |

## Acceptance criteria

- [ ] The suite is green on the live-like local database and in all three CI jobs.
- [ ] The live-like dataset can be rebuilt from scratch with one command and produces the same data each
      time.
- [ ] A snapshot can be taken and restored, leaving the database identical.
- [ ] The flat API contract fixtures are recorded.
- [ ] `CLAUDE.md` states the extended live-data rule, and the safety hook is still in place.
- [x] Open question 1 is answered (no live data yet; develop as though live).

## Definition of done

- [ ] Pint and the full suite pass on SQLite and MySQL 8.
- [ ] The baseline and the "work as though live" rule are recorded in saas-plan.md.
