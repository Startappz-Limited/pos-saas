# Phase 2: Tenant Isolation Test Suite

> **Source:** saas-plan.md section 5 (Phase 2), "Development principles"
> **Branch:** `saas/phase-2-isolation` · **Depends on:** Phase 1c

---

## Goal

Proof that business A never sees or changes business B's data, through any channel. **No feature stage
(Phase 3 onwards) starts until this suite is green.**

## Before you start

No open question blocks this stage. The two-business fixture (a user who belongs to **both**) already
exists from Phase 1a.

## Relevant current state

- Shop-scoping tests exist for the calendar, dashboard, expense API and credit. There were **no tenant
  isolation tests** before Phase 1a.
- The suite runs on SQLite in memory, and since Phase 0 also on MySQL 8 in CI.

## Work

- [x] `tests/Feature/TenantIsolation/*`, built on the two-business fixture from Phase 1a.
      *Steps 1–2: the crawl, the matrix and `tests/Support/Canaries.php` on `TwoBusinesses`.*
- [x] **Resource matrix:** web, flat API, `/api/v1` and sync; index, show, update and delete; foreign ids
      rejected on create.
      *Step 2: `ResourceMatrixTest` (web and flat API; index is the crawl's). `/api/v1` and sync rows when Phase 6 builds them.*
- [x] **Canary crawl:** every parameter-free GET route, run as A, must not contain B's marker strings.
      *Step 1: `TenantIsolation/CanaryCrawlTest`, as A's owner, a one-shop cashier and the member of both, and as B's owner; proved by mutation (33 leaks with `BusinessScope` off).*
- [x] **Switching:** after switching to A, nothing from B is shown or accepted.
      *Step 3: `SwitchingAndTokensTest`.*
- [x] **Tokens:** a v1 token for A is refused for B; a legacy token acts only on the default business.
      *Step 3: a legacy token acts only on the default business; the `/api/v1` token row is a pending test until Phase 6 builds v1 tokens.*
- [x] **Sync:** a pull for A never returns B's rows; a push with B's ids is rejected.
      *Step 3: written as pending tests (`Phase6IsolationTest`), filled in when Phase 6 builds sync.*
- [x] **Documents and background work:** reports, CSV, PDFs, jobs, webhooks (including M-Pesa) and
      scheduled sweeps stay within one business.
      *Stage 1c's `RawQueryIsolationTest`, `AuditIsolationTest`, `BackgroundWorkTest` (now in the isolation suite) and step 3's `WebhookIsolationTest`; M-Pesa is a pending test until Phase 6.*
- [x] **Files:** another business's file returns 404 or 403.
      *`Tenancy/TenantFilesTest` (stage 1c): another business's file is a 404.*
- [x] **Uniqueness:** both businesses can use the same codes.
      *`Tenancy/PerTenantUniqueKeysTest` (stage 1a); both fixture businesses use the same shop, SKU, customer and supplier codes.*
- [x] The suite runs in the MySQL CI job as well as on SQLite.
      *The full MySQL job, and the `isolation` job on both databases (step 3).*
- [ ] **CI:** the isolation suite becomes a required check that blocks merges.
      *Step 3: the `isolation` job (checks "Tenant isolation (sqlite)" and "Tenant isolation (mysql)"). **To do (you):** mark both as required in the branch protection rules for `main`.*

`/api/v1`, sync and M-Pesa don't exist until Phase 6; write those parts of the matrix so they're filled
in when those endpoints are built.

## Schema

None.

## Acceptance criteria

- [ ] The isolation suite is green and required in CI.
      *Green: 199 tests on SQLite and MySQL 8. Required once branch protection marks the checks.*

## Risks

- Routes with parameters that the crawl skips; the resource matrix covers those.

## Definition of done

- [ ] Isolation suite green on SQLite and MySQL 8, and required in CI.
      *Green on both; required pending the branch protection setting.*
- [x] Pint, the full suite and the flat API contract tests pass.
      *SQLite 1,379 passed, 59 skipped; MySQL 8 1,389 passed, 49 skipped (contract test included); Pint clean.*
- [x] From now on, every new model, route or endpoint adds its row to the resource matrix.
      *Recorded in CLAUDE.md and the header of `ResourceMatrixTest`.*
