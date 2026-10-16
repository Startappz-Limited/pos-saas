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

- [ ] `tests/Feature/TenantIsolation/*`, built on the two-business fixture from Phase 1a.
- [ ] **Resource matrix:** web, flat API, `/api/v1` and sync; index, show, update and delete; foreign ids
      rejected on create.
- [x] **Canary crawl:** every parameter-free GET route, run as A, must not contain B's marker strings.
      *Step 1: `TenantIsolation/CanaryCrawlTest`, as A's owner, a one-shop cashier and the member of both, and as B's owner; proved by mutation (33 leaks with `BusinessScope` off).*
- [ ] **Switching:** after switching to A, nothing from B is shown or accepted.
- [ ] **Tokens:** a v1 token for A is refused for B; a legacy token acts only on the default business.
- [ ] **Sync:** a pull for A never returns B's rows; a push with B's ids is rejected.
- [ ] **Documents and background work:** reports, CSV, PDFs, jobs, webhooks (including M-Pesa) and
      scheduled sweeps stay within one business.
- [ ] **Files:** another business's file returns 404 or 403.
- [ ] **Uniqueness:** both businesses can use the same codes.
- [ ] The suite runs in the MySQL CI job as well as on SQLite.
- [ ] **CI:** the isolation suite becomes a required check that blocks merges.

`/api/v1`, sync and M-Pesa don't exist until Phase 6; write those parts of the matrix so they're filled
in when those endpoints are built.

## Schema

None.

## Acceptance criteria

- [ ] The isolation suite is green and required in CI.

## Risks

- Routes with parameters that the crawl skips; the resource matrix covers those.

## Definition of done

- [ ] Isolation suite green on SQLite and MySQL 8, and required in CI.
- [ ] Pint, the full suite and the flat API contract tests pass.
- [ ] From now on, every new model, route or endpoint adds its row to the resource matrix.
