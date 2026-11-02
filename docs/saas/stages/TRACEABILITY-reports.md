# Traceability: Reports

Findings on reports outside the stock scope, from the review of 27 October 2026 (sixth round), checked against `main`
after Phase 7 (`832f684`). **Status:** *planned* (work in a stage that isn't done), *pending decision* (planned, waiting
on a point to confirm), *dropped* (with the reason).

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | `ReportController::run`, `schedule` | Answer "queued" and do nothing | Removed: stage 20, step 1 (recommended); or built: steps 4 and 5 | pending decision (stage 20, point 1) |
| 2 | `ReportController::exports` | Always an empty list | Removed: stage 20, step 1; or built: step 4 | pending decision |
| 3 | `ReportController::download` | Always "not ready yet" | Removed: stage 20, step 1; or built: step 4 | pending decision |
| 4 | `ReportController::create`, `store`, `show`, `edit`, `update`, `destroy` | Redirect with "available soon" | Removed: stage 20, step 1; saved reports built: step 2 | planned |
| 5 | `report_exports`, `dashboard_widgets` | No business or shop column, no tenant scope | Given tenant columns before use: stage 20, steps 4 and 6 | planned |
| 6 | `ReportType` (21 cases) | None implemented as a report | Report definitions: stage 20, step 2 | planned |
| 7 | `ReportController::types` | Five hard-coded names | Replaced by definitions: stage 20, step 2 | planned |
| 8 | Module 17, "Saved reports work" | `saved_reports` unused | Stage 20, step 2 | planned |
| 9 | Module 17, "Dashboard widgets customizable" | `dashboard_widgets` unused | Stage 20, step 6 | planned |
| 10 | Guidelines §12 "Sales trends by time, day, month"; fast movers | Not built | Sales trend definition: stage 20, step 2; fast and slow movers: stage 12, step 5 | planned |
| 11 | Module 20 §7 "COGS by period" | Not built | Profit and loss from `SalesFigures`: stages 14 and 20 | planned |
| 12 | Synchronous exports | No row cap; materialise everything (CLAUDE.md) | Row cap and chunked CSV: stage 20, step 3 | planned |
