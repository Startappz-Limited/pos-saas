# Phase 4: Billing, Entitlements, Metering

> **Source:** saas-plan.md section 5 (Phase 4), sections 4.6, 4.9, section 1 (External integrations),
> section 8 (Open question 2)
> **Branch:** `saas/phase-4-billing` · **Depends on:** Phase 3

---

## Goal

Each business is on a plan per shop, limits are enforced, collection goes through the private M-Pesa
package, and AI runs on prepaid credit.

## Before you start

| Open question | Why it matters here |
|---------------|---------------------|
| **2. How does the private M-Pesa package work?** | **Decided 12 October 2026:** the package acts as an API; each shop has its own account details, and payments are confirmed by querying the API and comparing against what it returns. Subscriptions and AI top-ups use the **platform's** own account details. Check that the package supports this Laravel version (Assumption 7). |

## Relevant current state

- **AI:** platform API keys (qwen, OpenAI, Claude, Gemini) with a provider per shop; `AiManager` exists
  and only picks a driver per shop. No metering.
- **M-Pesa (Daraja):** no integration today.
- **Payments:** recorded only; no provider integration.

## Decisions that apply

- **4.6 Billing** (confirmed):
  - **Pricing:** per shop, Tier 1, Tier 2 and Tier 3 at **KES 1,500, 3,000 and 5,000 per shop per
    month**, stored as plan data and editable without a deploy; placeholder limits (staff, products,
    registers, WhatsApp sessions, features); new businesses start on a trial (placeholder length, plan
    data).
  - **Collection:** the private M-Pesa package (STK Push plus payment webhooks) to the platform's number.
    **Platform go-live is expected around 12 November 2026**; until then payments are recorded by hand in
    the platform console, and the automated path is built behind a switch turned on at go-live.
  - **Lapsed subscription:** read-only plus export for 30 business days; every write refused on web, API
    and sync push with `subscription_inactive`, except sales made before the lapse; export stays
    available.
  - **Downgrades:** the business must delete shops above the new quota; until then every shop is
    read-only.
  - **AI:** prepaid and metered; each call recorded (business, shop, provider, model, tokens, cost)
    against a credit balance per business; blocked at zero until a top-up; owner notified; blocked work
    retried after the top-up; top-ups from KES 500; the credit unit is a configurable conversion.
  - **Platform invoices and platform VAT** are post-launch; this stage keeps subscriptions, payment
    records and M-Pesa receipt numbers.
  - **Gating:** one `hasFeature()`/`limit()` check used by middleware, policies, the UI, jobs and the
    sync API.
- **4.9 Edge-case rules** (confirmed):
  - **Downgrading to fewer shops:** every shop read-only until within quota (sign-in, viewing, export and
    deleting shops still work).
  - **AI credit runs out during work:** owner notified; blocked work kept waiting for credit and retried
    automatically after a top-up.
  - **How long a lapsed business stays read-only:** 30 business days from the lapse; then closed through
    the business-closure flow (built in Phase 3); paying before deletion reopens it; the owner is warned
    before the 30 business days run out.
- **Assumption 12:** business days are Monday to Friday, excluding Kenyan public holidays, kept in a
  configurable calendar.

## Work

- [x] **Billing module:** `Plan`, `Subscription`, `PlatformPayment`, `AiCreditBalance`, `AiUsageEvent`.
- [x] The entitlements service and a `Feature` enum.
- [x] A `PaymentCollector` adapter over the **private M-Pesa package** (an API: STK Push with the
      platform's account details, and payments confirmed by querying it),
      with a go-live switch.
- [x] **Read-only middleware:** refuses writes on web, both APIs and sync push; allows export. Applies for
      30 business days after a lapse, and whenever a business has more shops than its plan allows
      (deleting shops stays allowed).
- [x] A business-day calendar for the 30-day count.
- [x] **`AiManager`:** reserves the estimated cost atomically before a call, settles the actual cost
      afterwards, records usage, and refuses calls at zero balance; the owner is notified, and blocked
      jobs wait for credit and are re-dispatched after a top-up.
- [x] The billing page, the AI top-up flow (minimum KES 500), and reminder notifications.

## Schema

- [x] `plans`: Tier 1, Tier 2 and Tier 3 at KES 1,500, 3,000 and 5,000 per shop per month; placeholder
      limits, features and trial length as data.
- [x] The AI credit conversion (credits per KES, credits per call) as configuration.
- [x] `subscriptions`: shop count, price at signup, status, period end, trial end.
- [x] `platform_payments`: purpose (subscription or AI top-up), M-Pesa receipt number, raw webhook data,
      recorded by (for manual entries).
- [x] `ai_credit_balances`, `ai_usage_events`.

## Decisions taken

- **Atomic AI credit reservation (4.6).** Built as proposed: each call reserves its estimated cost with
  one conditional update (`balance >= estimate`) before it runs, and settles or releases the difference
  afterwards (`App\Services\Billing\AiCredits`), so concurrent calls can't take the balance below zero.

## Dependencies

| Need | Package |
|------|---------|
| M-Pesa STK Push and payment confirmation | The **private M-Pesa package** (Composer VCS repository with token access, like `wa-gateway-laravel`); it acts as an API queried with the account details |

## Acceptance criteria

- [x] A trial starts at signup (after email verification, Phase 3).
- [x] Adding a shop or register beyond the plan is refused on web and both APIs.
- [x] A manual payment, and later a payment confirmed through the M-Pesa API, extends the subscription
      exactly once.
- [x] A lapsed business can read and export, but every write path returns `subscription_inactive`, for 30
      business days; the owner is warned before the end, and on the 31st business day the closure flow
      starts. A payment before deletion reopens the business.
- [x] After a downgrade with too many shops, every shop is read-only until enough shops are deleted.
- [x] An AI call deducts credit; at zero balance AI calls are refused until a top-up of at least KES 500,
      the owner is notified, and the blocked work runs after the top-up.
- [x] Concurrent AI calls can't take the balance below zero.

## Risks

- Spoofed callbacks (confirm every payment by querying the M-Pesa API; make it idempotent).
- A payment applied to the wrong business (match on the request and account reference, never on the
  phone number).
- Read-only mode missing a write path, such as the sync push or a legacy API endpoint.
- Metering failures letting AI usage through unrecorded.

## Definition of done

- [x] Acceptance criteria met, with tests.
- [x] New models, routes and endpoints added to the isolation resource matrix; isolation suite green.
- [x] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.
- [x] Hotspot checklist items for billing and AI metering ticked in saas-plan.md section 2.
