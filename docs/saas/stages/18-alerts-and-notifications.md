# Stage: Alerts and Notifications

> **Source:** the review of findings outside the stock scope, 27 October 2026 (sixth round): alert types defined but
> never raised; notification preferences never read.
> **Branch:** `feat/alert-engine`, from `main` once stage 11c is merged · **Depends on:**
> [11c-low-stock-alerts-and-snapshots.md](./11c-low-stock-alerts-and-snapshots.md) (self-clearing system alerts,
> `source = system`, expiring-stock alerts), [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md)
> (`permissions:sync`), Phase 1c (background work per business), Phase 3 (messaging credentials per business)
> · **Next:** [19-expenses.md](./19-expenses.md) (budget alerts use this engine) · **Traceability:**
> [TRACEABILITY-alerts.md](./TRACEABILITY-alerts.md)

---

## Revisions

**Created 27 October 2026 (sixth round).** One alert engine that every alert type uses, built on stage 11c's
self-clearing, `source = system` alerts; alert rules are data the owner sets; notification preferences are read.

---

## Goal

When something needs attention (stock low or expiring, a customer overdue or near their credit limit, a budget
running out, an unusually large sale, a till not syncing), the right people are told once, on the channels they chose,
outside their quiet hours, and the alert clears itself when the cause is gone. Which alerts a business wants, their
thresholds and who receives them are settings, not code.

## Before you start

Stage 11c must be merged. One point is open (see "Points to confirm").

## Decisions that apply

- **One engine for every type (decided 27 October 2026, sixth round),** built on 11c's pattern: one open alert per
  cause (a dedupe key), raised and cleared by the same evaluation, `source = system`, no user.
- **Configuration over code (decided 27 October 2026, sixth round):** alert rules (type, on/off, thresholds,
  recipients, channels, severity) are per-business data; business types (stage 11) only suggest defaults.
- **Background work runs for one business** (Phase 1c): sweeps use `TenantContext::eachBusiness()`; notifications are
  queued on named queues.
- **Customer messages check the shop's switches** (CLAUDE.md); staff alerts are a separate channel set, governed by
  each person's preferences.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.
- **Two alert stores:** `alerts` (general: `type`, `severity`, `category`, `alertable`, read and resolved flags,
  `scheduled_at`, `sent_at`, `data`, `actions`; business-owned, shop optional) and `low_stock_alerts` (stage 11c makes
  them clear themselves).
- **`AlertType`** has about 30 cases. **Raised in code:** order and cart reminders and notes (calendar), order status
  changes, low stock (separate table). **Never raised:** `payment_overdue`, `credit_limit_warning`,
  `credit_limit_exceeded`, `budget_warning`, `budget_exceeded`, `stock_expiring` (stage 11c raises it), `large_sale`,
  `sales_target_met`, `refund_processed`, `expense_pending`, `expense_approved`, `password_changed`, `user_login`,
  `system_error`, `backup_completed`.
- **Causes the code can already compute:** overdue credit (`CreditAccountService` lists accounts with debits past
  their due date); a credit sale over the limit (`RecordCreditSale::exceedsLimit()`, flagged on the push); budgets per
  expense category (`monthly_budget`, `yearly_budget`); a till overdue to sync (`TillSyncOverdue`, Phase 7, its own
  notification).
- **`notification_preferences`** (per user: email, SMS, in-app, push; per-category preferences; quiet hours; daily
  digest) has a model and **is read by nothing**.
- **Staff notifications** today: `TillSyncOverdue`, `SubscriptionReminder`, `AiCreditExhausted`, `BusinessExportReady`,
  each sent directly.

---

## Work

### 1. Alert rules (data)
- [ ] `alert_rules` (`uuid`, business, `type` (an `AlertType`), `is_enabled`, `parameters` (JSON, per type: e.g.
      `days_overdue`, `credit_warning_percent`, `budget_warning_percent`, `large_sale_amount`), `severity`,
      `recipient_roles` (role names), `recipient_users` (optional), `channels` (`in_app`, `email`, `sms`,
      `whatsapp`), `shop_id` (nullable: every shop)). `BelongsToBusiness`, `Auditable`.
- [ ] An "Alerts" settings page (`settings.update`) lists every type with a plain description, its parameters and
      recipients. **Defaults** come from the business type (stage 11): e.g. stock expiring on for pharmacies and
      agro-vets; `alerts:apply-defaults --business=<id>` (dry run; `--apply`) gives existing businesses the defaults,
      never overwriting a rule the owner changed.
- [ ] Tests: a rule's parameters are validated per type; another business's rules are a 404; defaults applied twice
      change nothing the second time.

### 2. The engine
- [ ] `App\Services\Alerts\AlertEngine`: `raise(type, shop, subject, dedupeKey, data)` creates or refreshes the one open
      alert for that key (`alerts.dedupe_key`, unique among open alerts per business); `clear(dedupeKey, reason)`
      resolves it ("Cleared: :reason", no user); every system alert has `source = system`, `created_by` NULL.
- [ ] **Evaluators**, one per type, each answering "which causes hold now?" for a business: low stock (stage 11c's
      query), stock expiring (11c), payment overdue (credit accounts past due by `days_overdue`), credit limit warning
      (balance above `credit_warning_percent` of the limit) and exceeded, budget warning and exceeded (spend this month
      or year against the category's budget, stage 19), large sale (a completed sale above `large_sale_amount`), till
      sync overdue (Phase 7's rule), refund processed (stage 14, informational, cleared when read). The engine raises
      the causes that hold and clears open alerts whose cause no longer does.
- [ ] **When evaluators run:** event-driven where a cause appears at one moment (a sale completed, a refund processed,
      a payment recorded), queued per business; a sweep `alerts:evaluate` every 15 minutes for the rest
      (`eachBusiness`, `withoutOverlapping`, `onOneServer`).
- [ ] **Low stock moves onto the engine:** the open rows of `low_stock_alerts` are copied into `alerts`
      (`alerts:copy-low-stock --business=<id>`, dry run, `--apply`, counts); the low-stock screens read `alerts`; the
      old table is dropped in a later release, after a verify step (stage 11's two-release pattern).
- [ ] `TillSyncOverdue` is raised through the engine (its rule replaces the hard-coded 24 hours with a parameter).
- [ ] Types with no cause to compute stay unraised and hidden from the settings page: `sales_target_met` (no targets
      exist), `backup_completed` (operations, out of scope), `system_error` (logs), `user_login` and `password_changed`
      (stage 16's audit).
- [ ] Tests: each evaluator raises its alert once and clears it when the cause goes; a disabled rule raises nothing; a
      shop-scoped rule only for its shop; a system alert names no user and never one of another business; the copy of
      low-stock alerts is idempotent.

### 3. Notifications that respect preferences
- [ ] `AlertNotification` (queued, `alerts` queue added to the documented worker list) is sent to the rule's recipients
      who can reach the alert's shop, on the channels the rule allows **and** the person's
      `notification_preferences` allow (channel switches and per-category preferences).
- [ ] **Quiet hours** (the person's, read in the shop's timezone): non-critical alerts are held until they end;
      critical ones are sent.
- [ ] **Daily digest:** a person who chose it gets one message at their digest time listing the day's alerts instead of
      one each.
- [ ] A preferences page under the person's profile edits `notification_preferences`.
- [ ] SMS and WhatsApp to staff go through the business's own messaging credentials (Phase 3); WhatsApp to staff uses
      Baileys, since Cloud API free-form text fails outside a 24-hour window (CLAUDE.md).
- [ ] Tests: a person with email off gets in-app only; an alert in quiet hours is held and sent after; a critical alert
      isn't held; a digest person gets one message for three alerts; nobody receives an alert for a shop they can't
      reach.

## Contract

No sync change. The flat API's alert endpoints (if the app reads them) keep their shape; new alert types are new
`type` values, which the app may not know: an app check
([app-release-checklist.md](./app-release-checklist.md#stage-18)).

## Schema

| Change | Data carried across |
|---|---|
| `alert_rules` (new) | `alerts:apply-defaults` gives each business its type's defaults |
| `alerts.dedupe_key` (nullable), `alerts.source` (`system`, `user`) | Existing alerts: no key, `user` |
| `low_stock_alerts` copied into `alerts`, then dropped in a later release | `alerts:copy-low-stock`, verified before the drop |
| Scheduled `alerts:evaluate` (every 15 minutes), digest sender (every 15 minutes) | None |

## Acceptance criteria

- [ ] Every alert type with a computable cause is raised by the engine when its rule is on, once per cause, and clears
      itself; the rest are hidden.
- [ ] Rules (on/off, thresholds, recipients, channels) are per-business data with type defaults.
- [ ] Notifications respect each person's channels, categories, quiet hours and digest.
- [ ] Low stock and till-sync alerts run on the engine.
- [ ] The isolation suite and both full suites pass.

## Risks

- **Noise:** alerts that fire too often get ignored. Mitigation: one open alert per cause; thresholds per business;
  digests.
- **Messaging cost:** SMS and WhatsApp to staff cost money. Mitigation: in-app and email by default; SMS and WhatsApp
  only when a rule and the person both choose them (point 1).
- **The scheduler and queue worker must run** (CLAUDE.md: unproven in production). Mitigation: in-app alerts appear on
  the next evaluation; the deployment check lists the `alerts` queue.

## Rollout (UAT)

1. Snapshot; rehearsal migrations; `alerts:apply-defaults` (dry run, then `--apply`); `alerts:copy-low-stock` (dry run:
   none open on UAT).
2. Run `alerts:evaluate` once by hand; check overdue credit and large-sale alerts against UAT data.
3. The user runs the migrations and both commands on UAT; the worker's queue list gains `alerts`.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body.

## Points to confirm

1. **Paid channels for staff alerts:** may a business send staff alerts by SMS or WhatsApp at its own messaging cost,
   and is that metered by the plan (Phase 4)?

## Follow-ups (not this stage)

- Sales targets (`sales_target_met`) if businesses want targets.
- Push notifications to the app, when the app supports them.
