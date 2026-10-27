# POS SaaS Conversion: Implementation Stages

One file per implementation stage of [pos-saas-plan.md](../pos-saas-plan.md). Each file pulls together what that
stage needs from the plan: goal, blocking questions, relevant current state, decisions, work, schema,
acceptance criteria, risks and the review proposals still awaiting a decision.

The plan stays the source of truth. When a stage file and the plan disagree, the plan wins; update both.

## Stages

| Stage | File | Branch | Depends on | Blocked by / open |
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
| 10. Stock correctness and voids | [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md) | `fix/stock-voids-fifo` | Phase 7 (merged) | None |
| 11. Units, quantities and batches | [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) | `feat/stock-units-batches` | 10 | None |
| 11b. Prices and discounts | [11b-prices-and-discounts.md](./11b-prices-and-discounts.md) | `feat/prices-and-discounts` | 11 (either order with 11c) | None |
| 11c. Low stock, alerts and snapshots | [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md) | `feat/low-stock-and-snapshots` | 11 (either order with 11b) | None |
| 12. Receiving and moving stock (GRNs, transfers, recall) | [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) | `feat/stock-receiving-transfers` | 11, 11c | None |
| 13. Stocktakes | [13-stocktakes.md](./13-stocktakes.md) | `feat/stocktakes` | 12 (and 10, 11, 11c) | None |
| 14. Returns in sales, cost and profit reports | [14-returns-in-reports.md](./14-returns-in-reports.md) | `fix/returns-in-reports` | Phase 1b, the VAT engine, 10, 11, 12 | None |
| 15. Regulated products | [15-regulated-products.md](./15-regulated-products.md) | `feat/regulated-products` | 11, 12 | **Unscheduled** until a regulated customer is in view; one point (Kenyan legal fields) |
| 16. Sign-in security and audit | [16-security-and-audit.md](./16-security-and-audit.md) | `feat/sign-in-audit` | 10 | Two points to confirm |
| 17. Platform: tills, sync indexes, M-Pesa package, test stability | [17-platform-tills-sync-mpesa.md](./17-platform-tills-sync-mpesa.md) | `feat/platform-tills-sync` | 10 | Step 3 waits on the M-Pesa package |
| 18. Alerts and notifications | [18-alerts-and-notifications.md](./18-alerts-and-notifications.md) | `feat/alert-engine` | 11c, 10 | One point to confirm |
| 19. Expenses | [19-expenses.md](./19-expenses.md) | `feat/expenses` | 10, 18 | One point to confirm |
| 20. Reports | [20-reports.md](./20-reports.md) | `feat/reports` | 14, 11c, 12 (18 for scheduled delivery) | Point 1: build or remove the stubs |
| 21. Plans, billing page and trial banner | [21-plans-billing-trial-banner.md](./21-plans-billing-trial-banner.md) | `feat/plans-trial-banner` | Phases 4 to 7 (can run any time) | Tiers not agreed (point 1) |
| 22. KRA eTIMS | [22-etims.md](./22-etims.md) | `feat/etims` | 10, 14, 11, 17 | Points 1 to 3 (integration type, certification, who must use it) |
| 23. Stocktakes in the app (outline) | [23-stocktakes-in-the-app.md](./23-stocktakes-in-the-app.md) | `feat/stocktakes-app` | 13, a scheduled app release | Two points to confirm |

**Proposed order (27 October 2026, sixth round).** The stock stages come first: 10, 11, then 11b and 11c (either
order), 12, 13, 14. Then 16 (sign-in security; small, and can run alongside the stock stages), 17 (platform; its tills
step needs stage 10's invoice series), 18 (the alert engine, on 11c's alerts), 19 (expenses, on 18), 20 (reports, on 14),
22 (eTIMS, when its points are answered). 21 (plans and the trial banner) can run at any time. 15 is unscheduled until
a regulated customer is in view, and 23 waits for the app's next release.

**The app.** The Flutter app's next release isn't scheduled. Every stage keeps the server backward compatible with the
app in shops and checks the server against the app's recorded requests; the app-side checks for each stage are in
[app-release-checklist.md](./app-release-checklist.md).

**Traceability.** Every finding from documents outside this folder, and where it went:
[stock, products, inventory and costing](./TRACEABILITY-stock.md), [reports](./TRACEABILITY-reports.md),
[alerts](./TRACEABILITY-alerts.md), [expenses](./TRACEABILITY-expenses.md), [security](./TRACEABILITY-security.md),
[billing](./TRACEABILITY-billing.md), [platform and eTIMS](./TRACEABILITY-platform.md).

**History.** Stages 10 to 15 came from the FIFO and returns reviews of 26 October 2026, the stock intake and products
review and the review of the pre-SaaS stock documents of 27 October 2026. On 27 October the combined stock stage was
split into 10, 11 and 12; stocktakes became 13 and the returns stage 14; in the sixth round stage 11 was split again
into 11, 11b and 11c (by suffix, so no other stage was renumbered), stage 15 became Regulated products, and stages 16
to 23 were added for the findings outside the stock scope and Phase 7's open items.

**Planning rounds change only this folder** (decided 27 October 2026): changes to code, migrations or other documents
are planned as items in the stage whose work makes them true; PROGRESS.md is edited only when the user asks.

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
