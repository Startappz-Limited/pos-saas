# Traceability: Plans and Billing

Findings from `docs/saas/plans-and-trial-banner.md` and the code, reviewed 27 October 2026 (sixth round).

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | Plan, Phase 1 step 1 | No shared plan-card partial; billing uses a `<select>` | Stage 21, step 1 | planned |
| 2 | Plan, Phase 1 step 2 | "14 days" hard-coded on sign-up | `trial_days`: stage 21, step 1 | planned |
| 3 | Plan, Phase 1 step 3 | Locked price until renewal not applied at renewal | Stage 21, step 1 | planned |
| 4 | Plan, Phase 2 | No console screen to edit plans; plans seeded by migrations | Stage 21, step 3 | planned |
| 5 | Plan, Phase 3 | No trial banner; `/api/v1/billing` has no trial fields | Stage 21, step 2 | planned |
| 6 | Sign-up review, 26 October 2026 | Tiers and prices not agreed | Stage 21, point 1 | pending decision |
| 7 | Plan, "Phase 2 step 5" | `docs/saas/billing.md` to document editable plans | Planned item in stage 21, step 3 | planned |
