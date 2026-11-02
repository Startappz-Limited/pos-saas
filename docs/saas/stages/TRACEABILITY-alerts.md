# Traceability: Alerts and Notifications

Findings from the review of 27 October 2026 (sixth round), checked against `main` after Phase 7 (`832f684`).
**Status:** *planned*, *dropped* (with the reason).

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | `AlertType::PAYMENT_OVERDUE` | Never raised | Evaluator: stage 18, step 2 | planned |
| 2 | `AlertType::CREDIT_LIMIT_WARNING`, `CREDIT_LIMIT_EXCEEDED` | Never raised | Evaluators: stage 18, step 2 | planned |
| 3 | `AlertType::BUDGET_WARNING`, `BUDGET_EXCEEDED` | Never raised | Evaluators: stage 18, step 2, with stage 19's budget summary | planned |
| 4 | `AlertType::STOCK_EXPIRING` | Never raised | Stage 11c, step 2; moved onto the engine in stage 18 | planned |
| 5 | `AlertType::LARGE_SALE` | Never raised | Evaluator: stage 18, step 2 | planned |
| 6 | `AlertType::REFUND_PROCESSED` | Never raised | Evaluator: stage 18, step 2 (after stage 14) | planned |
| 7 | `AlertType::SALES_TARGET_MET` | Never raised; no targets exist | Hidden; targets a follow-up of stage 18 | dropped: nothing to measure until targets exist |
| 8 | `AlertType::BACKUP_COMPLETED`, `SYSTEM_ERROR` | Never raised | Hidden | dropped: operations and logs, out of scope (plan section 9) |
| 9 | `AlertType::USER_LOGIN`, `PASSWORD_CHANGED` | Never raised | Sign-in events are audited: stage 16; suspicious sign-in alerts: stage 16, point 1 | planned |
| 10 | `notification_preferences` | Read by nothing | Read by `AlertNotification`: stage 18, step 3 | planned |
| 11 | Quiet hours, daily digest (module 16) | Not built | Stage 18, step 3 | planned |
| 12 | Module 16, email notifications for alerts | Not built | Stage 18, step 3 | planned |
| 13 | `low_stock_alerts` beside `alerts` | Two alert stores | Low stock moved onto the engine, old table dropped later: stage 18, step 2 | planned |
| 14 | `TillSyncOverdue` | Hard-coded 24 hours, its own notification | Raised through the engine, threshold a rule parameter: stage 18, step 2 | planned |
| 15 | Alert types and thresholds | Code, not data | `alert_rules` per business: stage 18, step 1 | planned |
