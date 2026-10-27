# Plans shown consistently, editable plans, and the trial banner (plan)

**Status:** planned, not started. Decisions confirmed 26 October 2026.

## Decisions

- **Price:** a subscriber keeps the price locked at signup until their next renewal; at renewal they move to the
  plan's current price. Billing shows both when they differ.
- **Banner:** owners only.
- **Visibility:** tenants see a plan only while it is active. Platform operators create and edit plans, choosing
  which features and limits each tier carries.

## Findings

- Signup (`resources/views/auth/register.blade.php`) reads price, limits and features from `plans`. Billing
  (`resources/views/billing/show.blade.php`) reads the same table but shows `price_at_signup`, never the plan's
  limits and features, and offers tiers in a bare dropdown.
- Plans are seeded by two migrations (`2026_10_21_000001`, `2026_10_23_000002`); no console screen edits them.
- "14 days" is hard-coded in the signup view although `plans.trial_days` exists.
- No trial banner exists; the trial end appears only on the billing page and in reminder emails.

## Phase 1: one definition, shown identically

1. Extract the signup plan card to `partials/plan-card.blade.php` (takes a `Plan`); use it on signup and on
   billing's "Change plan" (replacing the dropdown). Billing also shows the current plan's limits and features,
   with usage against each limit.
2. Add `Plan::priceLine()` / `trialLine()`; replace the hard-coded "14 days" with `trial_days`. Signup, billing,
   the console and `/api/v1/billing` all use them.
3. **Locked price until renewal:** keep `price_at_signup` (rename later if wanted) as the price in force. Billing
   shows "KES X per shop (locked), KES Y from your next renewal" when the plan's price differs. When
   `ApplyPlatformPayment` starts a new period, set the subscription's price to the plan's current price. A
   payment for months already running at the locked price still buys them at that price.
4. Test: for every active plan, signup and billing show the same name, price, limits and features; an edited price
   leaves a running subscription unchanged and applies at the next period.

## Phase 2: editable plans (platform console)

1. "Plans" section in the console, gated by a new `plans.manage` permission (platform operators only): create,
   edit (name, key on create only, price, trial days, limits as number fields with blank = unlimited, features as
   checkboxes from `Feature`, order) and activate or deactivate.
2. Deactivating hides a plan from signup and billing; existing subscribers keep it. A new plan appears to tenants
   only once activated.
3. `Plan` becomes `Auditable` (before and after values). Validation: unique key, price >= 0, limits integers.
4. Move seed data into `PlanSeeder` (`updateOrCreate` by key) so placeholders can be reset with
   `db:seed --class=PlanSeeder`. Existing migrations stay.
5. Document in `docs/saas/billing.md`. New `Feature` or `Limit` enum cases need code, so the console lists only
   existing ones.
6. Tests: operator can create, edit, activate and deactivate; a tenant never sees an inactive plan on signup or
   billing; non-operators get 403; isolation matrix row.

## Phase 3: trial banner (owners only)

1. `partials/trial-banner.blade.php`, included beside the other banners in `layouts/app.blade.php` and
   `pos.blade.php`. Shown to the business owner while the subscription is trialing and `trial_ends_at` is in the
   future.
2. Text: "Your free trial ends in 9 days 4 hrs" (Free Trial) or "Your Tier 2 trial ends in ..." with a "Choose a
   plan" button to `/billing`. Neutral above 7 days, warning from 7, danger from 2.
3. Rendered on the server, updated by a small script from `data-ends-at` (every minute; every second in the last
   hour); at zero it reloads into the read-only notice. Dismissible for the session until the last 2 days.
4. `/api/v1/billing` gains `trial.ends_at` and `trial.seconds_left` (additive) for the Flutter till.
5. Tests (with `travelTo`): shown for a trialing owner, not for a paid owner or a manager; the three urgency
   levels; a plan change leaves the end date alone.

## Order

Phase 1, then Phase 3, then Phase 2.

## Definition of done

Pint, targeted tests, then the "Before pushing" suites; `mobile:contract-diff` after the v1 change.
