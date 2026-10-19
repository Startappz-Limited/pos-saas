# Phase 3: Accounts, Roles, Onboarding, Business Settings

> **Source:** saas-plan.md section 5 (Phase 3), sections 4.3, 4.4, 4.9, section 1 (Authentication and
> authorization, Hard-coded values, External integrations), section 2 (Auth and roles, Integrations)
> **Branch:** `saas/phase-3-accounts-settings` · **Depends on:** Phase 2 (isolation suite green)

---

## Goal

Self-service signup, memberships with switching, correct roles, and tenant-owned settings, branding and
integration credentials.

## Before you start

| Open question | Why it matters here |
|---------------|---------------------|
| **2. How does the private M-Pesa package reach a shop's own Till or Paybill?** | **Decided 12 October 2026:** the package acts as an API; each shop has its own account details, and payments are confirmed by querying the API and comparing against what it returns. So each shop supplies its Till or Paybill **and its own M-Pesa account details** in its settings. |

## Relevant current state

- **Web:** Breeze sessions; **registration creates a user with no role and no shop**; `DELETE /profile`
  hard-deletes the user.
- **Roles:** global; `Gate::before` lets `super-admin` do everything and it acts as the shop owner today.
- **Hard-coded values:** `config('app.name')` on receipts and all PDFs; WooCommerce webhooks named
  `"Fitness Center - {topic}"`; the Meta WhatsApp template has a fixed
  `https://pos.startappz.co.ke/orders/` prefix; currency from `config('app.currency_*')` with `'KES'`
  fallbacks in campaigns, AI and the order importers; app timezone; phone country code `254`; invoice
  prefix, reset rule and padding are global config; the M-Pesa till `577 42 79` is hard-coded in
  `NotifyCustomerOfEcommerceOrder`.
- **Integrations:** WhatsApp Cloud uses **one platform number from `.env`**; SMS is broken
  (`SmsChannel::isConfigured()` reads `env()`; `SMSservice` uses `App\Models\Setting`, which doesn't
  exist; global cache keys).
- **Soft deletes:** 19 models. **Audit:** a separate SQLite database.

## Decisions that apply

- **4.4 Accounts model** (confirmed):
  - `super-admin` is the platform operator: global, never a business member, the only user
    `Gate::before` lets do everything.
  - `admin` is a business owner (several owners allowed; the founding owner is protected).
  - Each business gets default roles (manager, cashier, accountant, inventory clerk) plus custom roles.
  - Owners see all their shops; others see assigned shops; no assignment means no access.
  - An invite to an existing email adds a membership to that login.
  - **Sign-in:** one business → selected automatically; several with a usable default → straight into
    the default; several with no usable default → picker, and the choice becomes the default. An inactive
    membership or a suspended or closing business is never selected automatically.
  - **API:** `/api/v1` sign-in follows the same rules and returns a token bound to one business, plus the
    list of the user's businesses; switching issues another token.
- **4.9 Edge-case rules** (confirmed):
  - **Handing over a business:** only to an existing co-owner, confirmed by a one-time code emailed to
    the owner (the same mechanism as closure confirmation); the previous owner stays a co-owner unless
    they leave.
  - **Two businesses in two browser tabs:** not supported; one active business per session; a form from
    a page loaded before a switch is refused with "this business was switched; reload the page".
  - **Moving a shop to another business:** not allowed; a shop leaves only by being deleted.
  - **Lapsed business:** closed through the business-closure flow after 30 business days (the trigger is
    built in Phase 4; the closure flow is built here).
  - **Currency:** per business, never hard-coded; every amount, receipt, PDF, report and AI prompt uses
    it.

## Work

### Roles
- [x] `RoleSeeder` and `PermissionSeeder`: global `super-admin` and `admin`; role templates copied per
      business; custom roles. *(As built: the platform operator is the `users.is_platform_operator` flag,
      not a role; see PROGRESS, stage 3.)*
- [x] `AppServiceProvider`: `Gate::before` only for `super-admin`. *(Only for the platform operator.)*

### Sign-in
- [x] `RegisteredUserController` creates a business and its owner; honeypot. **The trial starts only
      after the email is verified**, so throwaway signups can't open free trials.
- [x] The business picker, switcher and default-business setting (web).
- [x] `/api/v1` sign-in, list businesses, issue a token per business, switch, sign out.

### People
- [x] Invites.
- [x] `UserPolicy`: owner protection; only owners delete.
- [x] Account status middleware (web and API).
- [ ] Deactivation instead of self-delete.
- [ ] **Data export and business closure:** final export emailed to the owner; deletion when the closure
      grace period ends; also triggered automatically after 30 business days of lapse (4.9). Deletion uses
      `forceDelete` on the 19 soft-deleting models and also covers the business's rows in the **SQLite
      audit database** and its files on the private disk.
- [ ] **Ownership handover** to an existing co-owner, confirmed by a code emailed to the owner.
- [ ] **One active business per session:** pages and forms carry the business they were loaded for; a
      submission for a business that's no longer active is refused with a "reload the page" message.
- [ ] **Shop deletion** as the only way a shop leaves a business.

### Onboarding
- [ ] The onboarding checklist: business, first shop, first register, products, first sale.
- [ ] CSV product import.

### Settings
- [ ] Business and shop name, logo, receipt header and footer, currency, timezone, invoice prefix, and the
      **shop's M-Pesa Till or Paybill number**. These replace `config('app.name')` and the hard-coded till
      in `NotifyCustomerOfEcommerceOrder`.

### Integrations
- [ ] **Encrypted per-tenant credentials** for SMS and WhatsApp Cloud.
- [ ] **Each shop's own M-Pesa account details**, encrypted (Open question 2), checked with a test query to
      the M-Pesa API when saved.

### Notifications
- [ ] Notifications carry the business they belong to, and their links switch to it.

## Schema

- [ ] Settings on businesses and shops, including `shops.mpesa_shortcode` and
      `shops.mpesa_shortcode_type` (till or paybill).
- [ ] `business_integrations` (business, optional shop, type, encrypted credentials, status).
- [ ] Invites, exports and closure tables.
- [ ] Foreign keys from business records to `users` set to `nullOnDelete`.

## Pending decisions (Proposed, review)

- **Shortcode ownership (4.3).** Proposed: a shortcode can belong to only one shop on the platform, and
  it's accepted only after the shop proves it controls it. With Open question 2 answered, the proof is a
  successful query to the M-Pesa API with the shop's own account details. Affects the shop settings
  validation here.

## Dependencies

| Need | Package |
|------|---------|
| Optional | `league/csv` (CSV import) |

## Acceptance criteria

- [x] Signup creates a business and its owner; the trial starts only once the email is verified.
- [ ] Closing a business leaves none of its rows in the main or audit database, and none of its files.
- [x] A user with one business signs in straight to it, on the web and `/api/v1`, with no picker.
- [x] A user in two businesses with a default signs in straight to the default; without a usable default
      they see the picker. They can switch, and only ever see the active business.
- [ ] Custom roles apply only in their business.
- [ ] A removed member loses web and API access immediately.
- [ ] Ownership passes to a co-owner only with the emailed code; a non-co-owner can't receive it.
- [ ] A form loaded for business A and submitted after switching to B is refused and nothing is saved in
      B.
- [ ] Amounts, receipts and PDFs use the business's currency.
- [ ] Receipts and PDFs show the tenant's branding and the shop's own Till or Paybill.
- [ ] A shop's M-Pesa account details are stored encrypted and accepted only after a successful test
      query to the M-Pesa API (faked in tests).
- [ ] SMS and WhatsApp send through the tenant's own credentials (faked in tests).
- [ ] `/api/v1` endpoints exist for every flow above; the flat API contract tests still pass.

## Risks

- Privilege escalation through role assignment or invites across businesses.
- The founding owner locked out.
- A session or token used after the membership is removed.
- Credentials leaking into logs.

## Definition of done

- [ ] Acceptance criteria met, with tests.
- [ ] New models, routes and endpoints added to the isolation resource matrix; isolation suite green.
- [ ] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.
- [ ] Hotspot checklist items for auth, roles, settings and integrations ticked in saas-plan.md section 2.
