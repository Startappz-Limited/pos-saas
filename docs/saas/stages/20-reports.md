# Stage: Reports

> **Source:** the review of findings outside the stock scope, 27 October 2026 (sixth round): the report run and
> schedule actions only say "queued", the exports list is always empty, and downloads are never ready.
> **Branch:** `feat/reports`, from `main` once stage 14 is merged · **Depends on:**
> [14-returns-in-reports.md](./14-returns-in-reports.md) (`SalesFigures`, the one place for net figures),
> [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md) and
> [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) (stock reports), and, for step 5 only,
> [18-alerts-and-notifications.md](./18-alerts-and-notifications.md) (delivery) · **Traceability:**
> [TRACEABILITY-reports.md](./TRACEABILITY-reports.md)

---

## Revisions

**Created 27 October 2026 (sixth round).** Recommends removing the stub actions now and building report definitions
with synchronous runs; queued generation and scheduled emails wait for confirmation ("Points to confirm" 1).

---

## Goal

Every report a business can open is real: it shows what it says, from one definition of each figure, and exports what
it shows. Nothing pretends to run, schedule or prepare a file that never comes. Which reports exist, and their default
filters and columns, are definitions (data), so adding a report doesn't mean a new controller.

## Before you start

Stage 14 must be merged (net figures). Point 1 must be answered before steps 4 and 5.

## Decisions that apply

- **Net figures come from `SalesFigures`** (stage 14); stock figures from 11c's and 12's queries; nothing recomputes them.
- **Configuration over code (decided 27 October 2026, sixth round):** report definitions are data.
- **The recommendation (for confirmation): remove the stub actions now; build queued generation when a report
  outgrows a synchronous request.** Reasons, from the code:
  - nothing in the UI reaches the stubs: the reports page links only to a working synchronous CSV/PDF export, and the
    run, schedule, exports, download, create, show, edit and delete actions are routes that answer with a flash
    message ("queued", "available soon", "not ready yet") and do nothing;
  - they report success that didn't happen, which is worse than no button;
  - `report_exports` and `dashboard_widgets` have no business or shop column and no tenant scope, so wiring them up
    quickly would risk showing one business's files to another;
  - scheduled emails need the queue worker and the scheduler, which nothing proves run in production (CLAUDE.md);
  - report definitions have to exist before anything can be queued or scheduled.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.
- **`ReportController`:** `index` renders the dashboard report (from `ReportService::getDashboardData()`); `export`
  streams it as CSV or PDF synchronously (`reports.export`, `reports.full-access`); `create`, `store`, `show`, `edit`,
  `update`, `destroy` redirect with "will be available soon"; `run` and `schedule` redirect with "queued" and do
  nothing; `exports` returns an empty list; `download` says "not ready yet"; `types` returns five hard-coded names.
- **Routes** for all of them exist (`Route::resource('reports')` plus `run`, `schedule`, `exports`, `download`); the
  views link only to `reports.export` and `reports.vat`.
- **Tables:** `saved_reports` (business-owned, shop optional: name, type, filters, columns, grouping, sort, schedule
  fields) with a model, request and policy, used by nothing; `report_exports` (user, name, type, format, status, file,
  expiry; **no business or shop column**, no scope); `dashboard_widgets` (no tenant column, no scope), used by nothing.
- **`ReportType`** has 21 cases (sales, profit, stock, credit aging, expenses, tax, staff…), none implemented as a
  report; `VatReportService` is a real report (`reports.vat`).
- **A working pattern for queued files:** `BuildBusinessExport` (Phase 4) builds a business's archive on the queue,
  stores it under the business's folder and notifies the owner (`BusinessExportReady`).

---

## Work

### 1. Remove what pretends (pending point 1; recommended)
- [ ] Remove the routes and controller methods `create`, `store`, `show`, `edit`, `update`, `destroy`, `run`,
      `schedule`, `exports`, `download`, and the unused views `reports/create`, `show`, `edit`. Keep `index`, `export`,
      `types` (until step 2 replaces it) and the VAT report.
- [ ] `report_exports` and `dashboard_widgets` stay unused until steps 4 and 6 give them tenant columns (no data on UAT
      to carry: checked by a read-only count before the PR).
- [ ] Tests: the removed routes are 404s; the dashboard export still downloads; the route list test is updated.

### 2. Report definitions as data
- [ ] `report_definitions` (platform-provided, versioned with the code: `key`, `name`, `category`, the query class,
      allowed filters, default columns, the permission needed) seeded for the reports that exist: sales summary, sales by
      product, by category, by shop, by staff, by customer, profit and loss (net, stage 14), VAT (existing), low stock
      (11c), stock value and stock age (11c, 12), expiring stock (11), stock movement, credit aging, payments received,
      expenses by category; each maps one `ReportType`.
- [ ] **Saved reports** (`saved_reports`, existing table): a business saves a definition with its filters, columns and
      shop; listed on the reports page; `SavedReportPolicy` applies.
- [ ] Tests: each definition runs for a business and shows only its data; a manager sees only their shops; a saved
      report reopens with its filters.

### 3. Running and exporting, synchronously
- [ ] A report runs in the request, paginated on screen, with a row cap for exports (`config('reports.sync_row_cap')`,
      default 20,000): CSV streams with `lazy()`/chunks; PDF only under a smaller cap.
- [ ] Above the cap, the export button says "Too large to download now: narrow the dates or shops" until step 4 exists.
- [ ] Tests: an export of a saved report matches the screen; a report above the cap refuses with the message; no query
      uses `whereDate()` on an indexed column (CLAUDE.md's performance note).

### 4. Queued generation (only if point 1 says build)
- [ ] `report_exports` gains `business_id` and `shop_id` and `BelongsToBusiness`; `GenerateReportExport` (queued, the
      business's context restored) writes the file under the business's folder (`TenantFiles`, private), sets status and
      expiry (7 days), and notifies the requester; a downloads list shows the business's exports; download through the
      signed tenant-files route.
- [ ] Tests: an export runs on the queue and downloads; another business's export is a 404; an expired file is gone.

### 5. Scheduled reports (only if point 1 says build)
- [ ] A saved report can be scheduled (daily, weekly, monthly, in the business's timezone) to recipients chosen from the
      business's members; `reports:send-scheduled` (every 15 minutes, catch-up from `last_run_at`) queues step 4's
      export and emails the link through stage 18's notifications, respecting preferences.
- [ ] Tests: a weekly report is sent once a week, caught up after a missed run; only members receive it.

### 6. Dashboard widgets (optional within the stage)
- [ ] `dashboard_widgets` gain `business_id` and a scope; a member picks and orders tiles from the report definitions;
      the default dashboard stays as today.

## Contract

The flat API's report endpoints (`Api\ReportController`) keep their shape; nothing in this stage changes them. No sync
change.

## Schema

| Change | Data carried across |
|---|---|
| `report_definitions` (new, seeded) | None |
| `saved_reports` used as is | None (no rows on UAT expected; checked read-only) |
| `report_exports.business_id`, `shop_id` (step 4 only) | None |
| `dashboard_widgets.business_id` (step 6 only) | None |

## Acceptance criteria

- [ ] No action reports success it didn't achieve; the stubs are gone (if confirmed).
- [ ] Each listed report runs from its definition, shows only the business's data and exports what it shows.
- [ ] Net figures come only from `SalesFigures`.
- [ ] If built: queued exports and scheduled reports work per business, never across businesses.
- [ ] The isolation suite and both full suites pass.

## Risks

- **Removing buttons people expect.** Mitigation: nothing in the UI links to them today.
- **Large synchronous exports** time out. Mitigation: the row cap and its message; step 4 when needed.

## Rollout (UAT)

1. Snapshot; read-only counts of `saved_reports`, `report_exports` and `dashboard_widgets` rows; rehearsal migrations.
2. Run each definition for business 1 on the rehearsal; compare totals with the dashboard and the VAT report.
3. The user runs the migrations on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body.

## Points to confirm

1. **Build queued generation and scheduled emails now, or remove the stubs and build them later?** Recommended:
   remove now (step 1), build definitions and synchronous runs (steps 2 and 3), and build steps 4 and 5 when a report
   outgrows the row cap or a business asks for scheduled delivery.

## Follow-ups (not this stage)

- Report definitions a business writes itself (custom columns over a safe query builder).
