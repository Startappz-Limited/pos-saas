# Traceability: Expenses

Findings from the review of 27 October 2026 (sixth round), checked against `main` after Phase 7 (`832f684`).
**Status:** *planned*, *dropped* (with the reason).

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | Module 14, "Recurring expenses processed" | Recurrence fields stored; nothing generates occurrences | `expenses:generate-recurring`: stage 19, step 3 | planned |
| 2 | Module 13, "Approval threshold triggers correctly" | `requires_approval`, `approval_threshold` stored, read by nothing | Second-person approval: stage 19, step 2 | planned |
| 3 | `ExpensePolicy::approve` | The creator can approve their own | Refused: stage 19, step 2 | planned |
| 4 | Module 14, "Receipt upload and storage works" | No receipt column, table or upload | `expense_attachments`: stage 19, step 4 | planned |
| 5 | `expense_approval_history` | Model and table, written by nothing | Written by `ExpenseService`: stage 19, step 1 | planned |
| 6 | `ExpenseService`, `ExpenseCategoryService` | Empty stubs; logic inline in the controller | Stage 19, step 1 | planned |
| 7 | `Expense::expense_number` | Random, unique across businesses | Series per shop and year: stage 19, step 5 | planned |
| 8 | Module 13, budgets | Budgets stored, never compared | Summary and alerts: stage 19, step 5, and stage 18 | planned |
| 9 | Module 13, shop-specific category overrides | Not built (categories are per business) | None | dropped: categories belong to the business (stage 1a); a shop needing its own uses a category |
| 10 | Guidelines §11, `related_sale_id` | Expenses can't be tied to a sale | Follow-up of stage 19 | dropped for now: not needed until asked |
