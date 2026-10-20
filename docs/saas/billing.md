# Billing, entitlements and AI metering (Phase 4)

How a business pays, what its plan allows, what happens when it stops paying, and how AI is charged.
Decisions: saas-plan 4.6 and 4.9, assumption 12. Stage file: `stages/06-phase-4-billing-entitlements.md`.

## Plans and subscriptions

- `plans` is platform data, editable without a deploy: Tier 1, 2 and 3 at **KES 1,500, 3,000 and 5,000 per
  shop per month**, with placeholder limits (`staff`, `products`, `registers` per shop, `whatsapp_sessions`;
  `null` = unlimited), features (`App\Enums\Feature`) and trial length.
- `subscriptions` has one row per business: the plan, **the shops it pays for** (`shop_count`), the price
  locked at signup, status (`trialing`, `active`, `cancelled`), `trial_ends_at` and `current_period_end`.
- **The trial** starts when the owner verifies their email (`StartTrial`): the top tier, one shop. The
  migration gave every existing business a trial too, so none was locked out.
- **Standing is judged from the dates**, not from a status a sweep must have updated: past the trial or
  period end, the business is lapsed from that moment.
- A business **with no subscription is unmetered** (the platform's own, or not yet verified).

## One check

`App\Services\Billing\Entitlements`: `hasFeature()`, `limit()`, `assertCanAdd()`, `isReadOnly()`,
`readOnlyReason()`. Used by:

| Where | How |
|---|---|
| Creating things | `creating` listeners on Shop, Register, Product, BaileysSession, BusinessMembership (`PlanLimits`): web, both APIs, imports and sync all hit the same check. Refused with `PlanLimitExceeded` (402 `plan_limit_reached`) |
| Routes | `->middleware('feature:marketing')` (`RequireFeature`, 402 `feature_unavailable`); applied to campaigns, social accounts and WhatsApp |
| Writes | `EnforceSubscription` (read-only mode, below) |
| Background work | `Business::runsBackgroundWork()` is false while read-only |
| AI | `MeteredAiDriver` needs the `ai` feature |

## Read-only mode

A business is read-only when its subscription has lapsed, **or** it has more shops than it pays for.
Every write on the web and both APIs is refused with **`subscription_inactive`** (402; `reason` is
`subscription_inactive` or `shops_over_quota`). Still allowed (`config('billing.read_only_allowed')`):
signing in and out, switching business, viewing, exporting, paying, closing the business, deleting shops,
and the payment callback. The sync push (Phase 6) must join this list when it exists.

**Lapse:** read-only for **30 business days** (Monday to Friday, minus `config('billing.holidays')`).
`billing:sweep` (daily 03:00) reminds owners 3 days before an end, warns them 5 business days before the
grace period runs out, and on the 31st business day starts the closure flow (`reason: lapsed`: final
export, then erasure after the closure's own grace period). **Paying before erasure reopens the business.**

**Downgrade:** lowering the shops paid for below the shops the business has makes every shop read-only
until enough are deleted (deleting shops stays allowed).

## Payments

- `platform_payments` (purpose `subscription` or `ai_topup`; method `manual` or `mpesa`).
- **By hand until go-live** (about 12 November 2026): `php artisan billing:record-payment {business} {amount}
  [--purpose=ai_topup] [--receipt=] [--notes=]`. The platform console (Phase 5) will offer the same.
- **Automated, behind the switch** `BILLING_MPESA_ENABLED`: `PaymentCollector` over the private M-Pesa package
  (STK push with the platform's own details; the payment's uuid is the account reference). The bound adapter
  is `NullPaymentCollector` until the package is plugged in (Assumption 7: check it supports this Laravel).
- **Callbacks are never believed.** `POST /api/webhooks/platform-payments` only names a checkout request;
  `ConfirmPlatformPayment` asks the API, and accepts only a paid answer for at least the amount, carrying our
  reference. The M-Pesa receipt is unique, so one receipt can't pay twice.
- **Applied exactly once** (`ApplyPlatformPayment`: row lock + `applied_at`). A subscription payment buys
  whole months at the locked price for the shops paid for, counted from the end of what is already paid.

## AI credit

Prepaid, per business. `ai.credits`: `per_kes` (10), `per_call` (20), `per_1k_tokens` (10); minimum top-up
KES 500 (`billing.ai_min_topup`).

- `MeteredAiDriver` wraps every real provider for a metered business: reserve `per_call` with a single
  conditional `UPDATE ... WHERE balance >= x` (so concurrent calls can't go below zero), call, settle the real
  cost (tokens, else the per-call price), release on failure, record an `ai_usage_events` row (business,
  shop, provider, model, tokens, credits).
- **At zero** the provider is never reached: the request is saved (`ai_blocked_work`), the owners are told
  once per empty spell, and the caller gets 402 `ai_credit_exhausted`. A top-up releases the waiting work
  (`RetryBlockedAiWork`) and tells the requester.

## Pages and endpoints

Billing page `/billing` (owners); `/api/v1/billing*` (`docs/flutter/17-api-v1-billing.md`).
