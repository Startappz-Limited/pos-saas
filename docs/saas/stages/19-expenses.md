# Stage: Expenses

> **Source:** the review of findings outside the stock scope, 27 October 2026 (sixth round): recurring expenses never
> generated, approval thresholds stored but unused, no receipt attachments.
> **Branch:** `feat/expenses`, from `main` once stage 18 is merged · **Depends on:**
> [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md) (the second-person approval pattern,
> `permissions:sync`), [18-alerts-and-notifications.md](./18-alerts-and-notifications.md) (budget alerts), Phase 1c
> (files per business, `TenantFiles`) · **Traceability:** [TRACEABILITY-expenses.md](./TRACEABILITY-expenses.md)

---

## Revisions

**Created 27 October 2026 (sixth round).** Approval reuses stage 10's second-person pattern; thresholds, recurrence
and receipt rules are data.

---

## Goal

An expense is recorded once, with its receipt, and approved by someone else when it is large or its category says so.
Rent and other recurring costs appear on their own, on time. Spending is compared with each category's budget, and the
owner hears when a budget runs low.

## Before you start

Stages 10 and 18 must be merged. One point is open (see "Points to confirm").

## Decisions that apply

- **Second-person approval (decided 27 October 2026, sixth round), as stage 10's adjustments:** an expense needs
  someone **other than its creator** holding `expenses.approve` when its category requires approval or its amount is
  above the category's `approval_threshold`, else the business's `expenses.approval_threshold` (default KES 10,000);
  otherwise it is approved on submission by a creator who holds `expenses.approve`, or waits for anyone who does.
- **Configuration over code:** thresholds per category and business, recurrence, and whether a receipt is required are
  data.
- **Files go through `TenantFiles`** (Phase 1c): private, per business, within the quota.
- **Budget alerts** are stage 18 rules (`budget_warning`, `budget_exceeded`).

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.
- **Workflow:** `ExpenseStatus` draft → pending (`submit`) → approved or rejected → paid (`markPaid`, or
  `settleFromRegister` from the open cash session), or cancelled. All in `ExpenseController` (inline); `ExpenseService`
  and `ExpenseCategoryService` are empty stubs (CLAUDE.md).
- **Approval:** `ExpensePolicy::approve` checks `expenses.approve` and shop access only; **the creator can approve
  their own**; every expense goes through submit and approve whatever its size.
- **Thresholds unused:** `expense_categories.requires_approval` and `approval_threshold` are validated and stored, and
  read by nothing.
- **History unused:** `expense_approval_history` and its model exist; nothing writes them.
- **Recurring:** `expenses.is_recurring`, `recurrence_frequency`, `recurrence_end_date`, `parent_expense_id` exist and a
  "Recurring" list shows them; **nothing generates the next occurrence**.
- **Receipts:** no column, table or upload.
- **Numbers:** `'EXP-'.Str::random(8)`, unique across all businesses.
- **Budgets:** `expense_categories.monthly_budget`, `yearly_budget`; no comparison or alert.
- **The flat API** has expense endpoints (`Api\ExpenseController`, `docs/flutter/12-expenses.md`).

---

## Work

### 1. Logic out of the controller
- [ ] `ExpenseService` (filled): create, update, submit, approve, reject, mark paid, settle from the register, each in
      a transaction, used by the web and flat API controllers alike; every status change writes
      `expense_approval_history` (from, to, who, comment).

### 2. Approval as stage 10's pattern
- [ ] `ExpenseApproval::needsSecondPerson($expense)`: the category requires approval, or the amount is above the
      category's threshold, else the business's (`businesses.settings['expenses']['approval_threshold']`, default
      10,000, set by `settings.update`, audited).
- [ ] Submitting an expense that doesn't need a second person, by a creator holding `expenses.approve`, approves it at
      once; otherwise it waits. The policy refuses the creator, whatever they hold, the owner included.
- [ ] Tests: a 2,000 expense by a manager is approved on submission; a 20,000 one waits and its creator can't approve
      it while another manager can; a category requiring approval waits whatever the amount; the threshold change is
      audited; history rows are written for each step.

### 3. Recurring expenses
- [ ] An expense marked recurring is a **template** (`is_recurring`, frequency `weekly`, `monthly`, `quarterly`,
      `yearly`, an anchor day, an end date). `expenses:generate-recurring`, every 15 minutes, per business and shop in
      the shop's timezone, creates each due occurrence as a **draft** (or `pending` when the template says
      "submit automatically"), `parent_expense_id` set, once per period (unique on parent and period), catching up any
      period missed since `last_generated_at`.
- [ ] Tests: a monthly rent template creates one draft each month on its anchor day; two runs in a row create one; a
      missed month is created on the next run; nothing after the end date.

### 4. Receipts
- [ ] `expense_attachments` (expense, file path on the `tenant` disk under the business's folder, mime, size,
      `uploaded_by`); images and PDFs up to 10 MB; served only by the signed-in `tenant-files.show` route; counted in
      the business's file quota.
- [ ] `expense_categories.requires_receipt` (boolean): submitting such an expense without an attachment is refused.
- [ ] Flat API: `POST /api/expenses/{expense}/attachments` (addition), with its `ResourceMatrixTest` row.
- [ ] Tests: a receipt uploads and is served only to members of the business; a category requiring a receipt refuses
      submission without one; another business's attachment is a 404.

### 5. Numbers and budgets
- [ ] **Numbers:** `EXP-{shop}-{year}-{n}`, a gapless series per shop and year; existing numbers kept; unique per
      business.
- [ ] **Budgets:** an expense summary per category and month (spent, budget, remaining, percent); stage 18's
      `budget_warning` (above the rule's percent, default 80%) and `budget_exceeded` rules read it.
- [ ] Tests: numbers are gapless per shop; two businesses' don't collide; a category at 85% of its monthly budget
      raises a warning alert that clears next month.

## Contract

The flat API's expense responses keep their shape; `attachments` is added to an expense's response and the upload
endpoint is new; a refusal for a missing receipt is a 422. App checks:
[app-release-checklist.md](./app-release-checklist.md#stage-19).

## Schema

| Change | Data carried across |
|---|---|
| `expense_attachments` (new) | None |
| `expense_categories.requires_receipt` (boolean, default false) | False for existing categories |
| `expenses.last_generated_at` (on templates), unique `(parent_expense_id, period)` | None |
| `businesses.settings['expenses']['approval_threshold']` (no column; default 10,000) | Missing means the default |
| `expenses.expense_number`: unique per business; new numbers from a series per shop and year | Existing numbers kept |

## Acceptance criteria

- [ ] Expenses above a threshold, or in a category requiring it, need a second person; the creator never approves their
      own; history is written.
- [ ] Recurring expenses are generated on time, once, catching up after a miss.
- [ ] Receipts attach, privately, and a category can require one.
- [ ] Spend is compared with budgets and alerts fire through stage 18.
- [ ] The flat API contract test, the isolation suite and both full suites pass.

## Risks

- **Small shops with one person** can't get a second approver above the threshold. Mitigation: the threshold is per
  business and category; the owner can give `expenses.approve` to a trusted employee.
- **Existing pending expenses** may now need a second person. Mitigation: they keep their status; the rule applies when
  they are approved.

## Rollout (UAT)

1. Snapshot; rehearsal migrations; `permissions:sync`.
2. On the rehearsal, submit expenses below and above the threshold; mark one recurring and run the generator.
3. Check the server against the app's recorded expense requests.
4. The user runs the migrations on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body.

## Points to confirm

1. **The default approval threshold** for expenses: KES 10,000 (proposed), or the same as stock adjustments
   (KES 5,000)?

## Follow-ups (not this stage)

- Linking an expense to a sale (delivery or packaging cost), from the pre-SaaS guidelines, if wanted.
