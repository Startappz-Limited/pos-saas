# Phase 5: Platform Admin Console

> **Source:** saas-plan.md section 5 (Phase 5), sections 4.4 and 4.6
> **Branch:** `saas/phase-5-platform-console` · **Depends on:** Phase 4

---

## Goal

Manage businesses on the platform without database access.

## Before you start

No open question blocks this stage.

## Decisions that apply

- **4.4:** `super-admin` is the platform operator, global, never a business member, and the only user
  `Gate::before` lets do everything. *Confirmed.*
- **4.6:** until M-Pesa go-live (around 12 November 2026), payments are recorded by hand in the platform
  console. *Confirmed.*
- **4.7:** the first platform operator is created by a console command, not seeded. *Confirmed.*

## Work

A `platform.*` route group gated to `super-admin`:
- [ ] Businesses: plan, shops, registers, members, usage, status, AI balance.
- [ ] Suspend and reactivate; change plan; extend trial.
- [ ] **Record manual payments and AI top-ups** (needed until go-live).
- [ ] Audited, time-limited impersonation with a banner.
- [ ] **`platform:create-operator`**, a console command that creates a platform operator.

## Schema

- [ ] A suspension status on businesses.
- [ ] An impersonation log.

## Acceptance criteria

- [ ] Only platform operators reach `/platform`.
- [ ] Impersonation is logged and shown.
- [ ] Suspension blocks web, both APIs and sync.
- [ ] Manual payments are attributed to the operator who entered them.
- [ ] The operator command creates a user outside every business.

## Risks

- The console as a way round isolation, so **every action is audited**.

## Definition of done

- [ ] Acceptance criteria met, with tests.
- [ ] Isolation suite green (the console's cross-business reads are audited and covered by tests).
- [ ] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.
