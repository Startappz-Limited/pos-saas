# Stage: Plans, the Billing Page and the Trial Banner

> **Source:** [docs/saas/plans-and-trial-banner.md](../plans-and-trial-banner.md) (a plan marked "planned, not
> started", decisions confirmed 26 October 2026), given a stage in the sixth round, 27 October 2026.
> **Branch:** `feat/plans-trial-banner` · **Depends on:** Phase 4 (plans, subscriptions, entitlements, read-only mode),
> Phase 5 (the platform console), Phase 6 (sign-up with plan cards, PR #21), Phase 7 (`/api/v1/billing`, the trading
> lease, `subscription_inactive` on the push) · **Can run alongside:** any stage · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-21) · **Traceability:**
> [TRACEABILITY-billing.md](./TRACEABILITY-billing.md)

---

## Revisions

**Created 27 October 2026 (sixth round)** from `plans-and-trial-banner.md`, checked against the code and Phase 7's
subscription handling. Its three phases are this stage's steps, in its order (1, then 3, then 2). The tiers and prices
themselves aren't agreed (26 October 2026: "billing to be reviewed later"); this stage builds how plans are shown and
edited, not which plans exist.

---

## Goal

A plan looks the same wherever it appears: on sign-up, on the billing page, in the console and to the app. A
subscriber keeps the price they signed up at until their next renewal. Platform operators edit plans as data. An owner
on a trial always sees how long is left.

## Before you start

One point is open (see "Points to confirm"); it doesn't block the steps, which work with whatever plans exist.

## Decisions that apply

- **From the plan (confirmed 26 October 2026):** a subscriber keeps the price locked at sign-up until the next renewal,
  then moves to the plan's current price, and billing shows both when they differ; the trial banner is for owners
  only; tenants see a plan only while it is active; operators create and edit plans and choose each tier's features
  and limits.
- **Plans are data** (sixth round's flexibility rule): limits and features per plan are set by operators; a new
  `Feature` or `Limit` case still needs code, so the console lists only existing ones.
- **Phase 7 stays as it is:** while lapsed, the push accepts items recorded before the lapse and refuses later ones with
  `subscription_inactive` (retry, stage 10); the trading lease never runs past the paid-up date (trial or period end).
  The banner's end time is that same date.
- **Billing is reviewed later** (26 October 2026): tier names, prices and limits aren't decided here.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`) and PR #21, 27 October 2026.
- **Sign-up** (`resources/views/auth/register.blade.php`) shows plan cards in a window (PR #21), from `plans`; "14 days"
  is still hard-coded in three places although `plans.trial_days` exists. There is no shared plan-card partial.
- **Billing** (`resources/views/billing/show.blade.php`) shows `price_at_signup` and offers tiers in a `<select>`, not
  the cards; it doesn't show the plan's limits or features.
- **Plans** are seeded by migrations (`2026_10_21_000001`, `2026_10_23_000002`); no console screen edits them; `Plan`
  has `price_per_shop`, `limits`, `features`, `trial_days`, `is_active`, `is_trial_plan`, `sort_order`.
- **No trial banner** exists (partials: impersonation, till sync, verification).
- **`/api/v1/billing`** (`V1BillingController::show`) returns the plan, usage and payment state; it has no trial fields.
- `Subscription::price_at_signup` and `shop_count`; `ApplyPlatformPayment` starts a new period.

---

## Work

### 1. One plan card, shown identically (the plan's Phase 1)
- [ ] Extract the sign-up card to `partials/plan-card.blade.php` (takes a `Plan`); sign-up and billing's "Change plan"
      use it (the `<select>` goes). Billing also shows the current plan's limits and features, with usage against each
      limit (`PlanLimits`).
- [ ] `Plan::priceLine()` and `trialLine()`; the hard-coded "14 days" becomes `trial_days`. Sign-up, billing, the
      console and `/api/v1/billing` use them.
- [ ] **Price locked until renewal:** `price_at_signup` stays the price in force; billing shows "KES X per shop
      (locked), KES Y from your next renewal" when the plan's price differs; when `ApplyPlatformPayment` starts a new
      period, the subscription's price becomes the plan's current price. A payment for months already running at the
      locked price buys them at that price.
- [ ] Tests: for every active plan, sign-up and billing show the same name, price, limits and features; an edited price
      leaves a running subscription unchanged and applies at the next period; the trial length comes from
      `trial_days`.

### 2. The trial banner (the plan's Phase 3)
- [ ] `partials/trial-banner.blade.php`, beside the other banners in `layouts/app.blade.php` and `pos.blade.php`, shown
      to the business owner while the subscription is trialing and `trial_ends_at` is in the future.
- [ ] Text: "Your free trial ends in 9 days 4 hrs" (Free Trial) or "Your Tier 2 trial ends in …", with "Choose a plan"
      to `/billing`; neutral above 7 days, warning from 7, danger from 2.
- [ ] Rendered on the server, updated from `data-ends-at` (every minute; every second in the last hour); at zero it
      reloads into the read-only notice (Phase 4). Dismissible for the session until the last 2 days.
- [ ] `/api/v1/billing` gains `trial.ends_at` and `trial.seconds_left` (additions).
- [ ] Tests (with `travelTo`): shown for a trialing owner, not for a paid owner or a manager; the three urgency levels;
      a plan change leaves the end date alone; the banner's end equals the trading lease's cap.

### 3. Editable plans in the console (the plan's Phase 2)
- [ ] A "Plans" section (`plans.manage`, operators only): create, edit (name, key on create only, price, trial days,
      limits as numbers with blank = unlimited, features as checkboxes from `Feature`, order), activate and deactivate.
- [ ] Deactivating hides a plan from sign-up and billing; existing subscribers keep it. A new plan appears to tenants
      only once activated.
- [ ] `Plan` becomes `Auditable` (before and after); validation: unique key, price ≥ 0, limits whole numbers.
- [ ] Seed data moves into `PlanSeeder` (`updateOrCreate` by key), run through `permissions:sync`'s deploy sequence or
      on its own with `--class=PlanSeeder`; the existing migrations stay.
- [ ] `docs/saas/billing.md` documents it (a planned item: written when this step ships).
- [ ] Tests: an operator creates, edits, activates and deactivates; a tenant never sees an inactive plan on sign-up or
      billing; non-operators get 403; the isolation matrix row.

## Contract

`/api/v1/billing` gains `trial.ends_at` and `trial.seconds_left` (additions); nothing else changes. App checks:
[app-release-checklist.md](./app-release-checklist.md#stage-21).

## Schema

| Change | Data carried across |
|---|---|
| Permission `plans.manage` (operators) | Through `permissions:sync` |
| `Plan` audited | None |
| `PlanSeeder` | `updateOrCreate` by key: today's plans unchanged |

## Acceptance criteria

- [ ] Every surface shows a plan identically, from one partial and the plan's own fields.
- [ ] A locked price holds until renewal and then follows the plan.
- [ ] Owners on a trial see the banner, with the end matching the trading lease; managers don't.
- [ ] Operators edit plans as data, audited; tenants see only active plans.
- [ ] Phase 7's lapse handling is unchanged (its tests pass unmodified).
- [ ] The isolation suite and both full suites pass.

## Risks

- **Editing a live plan's price** surprises subscribers. Mitigation: the lock until renewal, and billing shows both
  prices.
- **Plans not agreed yet.** Mitigation: the stage builds the mechanism; the tiers are data an operator sets later.

## Rollout (UAT)

1. Snapshot; rehearsal migrations; `PlanSeeder` on the rehearsal (unchanged plans).
2. Check sign-up and billing for one trialing and one paid rehearsal business.
3. The user runs the migrations and the seeder on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body; `mobile:contract-diff` after the v1 change.

## Points to confirm

1. **The tiers themselves** (names, prices per shop, trial length, limits such as staff, products and tills per shop):
   to be agreed in the billing review the user deferred on 26 October 2026.

## Follow-ups (not this stage)

- Card billing and M-Pesa Ratiba auto-renewal (deferred by the plan).
