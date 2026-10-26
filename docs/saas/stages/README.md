# POS SaaS Conversion: Implementation Stages

One file per implementation stage of [pos-saas-plan.md](../pos-saas-plan.md). Each file pulls together what that
stage needs from the plan: goal, blocking questions, relevant current state, decisions, work, schema,
acceptance criteria, risks and the review proposals still awaiting a decision.

The plan stays the source of truth. When a stage file and the plan disagree, the plan wins; update both.

## Stages

| Stage | File | Branch | Depends on | Blocked by |
|-------|------|--------|------------|------------|
| Phase 0 | [00-phase-0-development-baseline.md](./00-phase-0-development-baseline.md) | `saas/phase-0-baseline` | None | None |
| Phase 1a | [01-phase-1a-schema-tenant-layer.md](./01-phase-1a-schema-tenant-layer.md) | `saas/phase-1a-schema-tenant-layer` | Phase 0 | None |
| Phase 1b | [02-phase-1b-stock-sales-registers.md](./02-phase-1b-stock-sales-registers.md) | `saas/phase-1b-stock-sales-registers` | Phase 1a | None (starts with the FIFO analysis) |
| Phase 1c | [03-phase-1c-raw-sql-background-files.md](./03-phase-1c-raw-sql-background-files.md) | `saas/phase-1c-raw-sql-background-files` | Phase 1b | None |
| Phase 2 | [04-phase-2-tenant-isolation.md](./04-phase-2-tenant-isolation.md) | `saas/phase-2-isolation` | Phase 1c | None |
| Phase 3 | [05-phase-3-accounts-roles-settings.md](./05-phase-3-accounts-roles-settings.md) | `saas/phase-3-accounts-settings` | Phase 2 | None |
| Phase 4 | [06-phase-4-billing-entitlements.md](./06-phase-4-billing-entitlements.md) | `saas/phase-4-billing` | Phase 3 | None |
| Phase 5: Platform Console and Roles per Business | [07-phase-5-platform-console.md](./07-phase-5-platform-console.md) | `saas/phase-5-platform-console` | Phase 4, `fix/ui-button-links` | None |
| Phase 6: Signup, trials and invites | [08-phase-6-signup-and-user-invites.md](./08-phase-6-signup-and-user-invites.md) | `saas/signup-trials-invites` | Phase 4, Phase 5 Part A | None |
| Phase 7: `/api/v1`, sync, till payments | [09-phase-7-api-v1-sync-payments.md](./09-phase-7-api-v1-sync-payments.md) | `saas/phase-7-api-sync-payments` | Phase 2 (can run alongside 4–6) | None |
| Stock and FIFO integrity (voids, deletes) | [10-stock-voids-and-fifo-integrity.md](./10-stock-voids-and-fifo-integrity.md) | `fix/stock-voids-fifo` | Phase 1b | None |
| Returns in sales, cost and profit reports | [11-returns-in-reports.md](./11-returns-in-reports.md) | `fix/returns-in-reports` | Phase 1b, the VAT engine; best after stage 10 (`cost_known`) | None |

Stages 10 and 11 came from the FIFO and returns reviews of 26 October 2026; their decisions were taken the same day.

Phase 5 scope merged on 21 October 2026: roles per business (the roles review) and the platform console are
one stage on `saas/phase-5-platform-console`. The separate `fix/roles-per-business` branch is retired; its plan
is Part A of the Phase 5 stage file.

Answered on 12 October 2026:
- **Open question 1:** there is no live data, so the clean-baseline rewrite (4.7) stands with no import.
- **Open question 3:** customers belong to the business.
- **Open question 4:** MySQL in production; CI and local development use MySQL 8.
- **Open question 5:** adopt the FIFO costing tables, after analysing the earlier FIFO plan in
  `docs/implementation/20-fifo-costing.md` (first step of Phase 1b).
- **Open question 2:** the M-Pesa package acts as an API; each shop has its own account details, and
  payments are confirmed by querying the API.
- **Variant stock (4.2):** every product has a default variation; per-shop stock is keyed on
  `(shop_id, variation_id)`.
- **Customers:** `customers.shop_id` is kept; one credit account per customer.

Open question 6 (eTIMS deferral) and 7 (timeline) don't block a stage, but should be answered before
launch.

## Rules for every stage (pos-saas-plan.md "Development principles", section 5 and section 6)

- **One branch per stage** off `main`, merged only when the stage's acceptance criteria pass in CI.
- **One Claude Code session per stage.** Start large stages (1a, 1b, 1c, 3, 4, 6) in plan mode.
- **Commit after each completed step**, with tests for that step in the same commit.
- **Isolation before features.** No feature stage (3 onwards) starts until the isolation suite (Phase 2)
  is green.
- **The flat `/api` never breaks.** Every stage keeps the legacy contract tests passing.
- **Add the matching `/api/v1` endpoints** for whatever the stage changes (4.8).
- **Every stage ends green:** Pint, the full suite (on SQLite and MySQL 8), the isolation
  suite (from Phase 2) and both API contract test sets.
- **Keep the plan current.** When implementation diverges, update pos-saas-plan.md and the stage file, and
  tick the hotspot checklist (plan section 2) as items are done.

## Development principles (from the plan)

- **The POS is the product.** Registers, cash sessions, sales, stock, receipts, till payments and offline
  trading get the feature work. E-commerce sync, campaigns and social, ads, WhatsApp and AI get only the
  tenant scoping, per-tenant credentials and metering needed to be safe in a SaaS.
- **Out of scope:** deployment and operations (plan section 9).
