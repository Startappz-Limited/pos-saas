# Stage: Signup Hardening, Trials, Email Verification Grace and Unified Invites

> **Source:** signup and invites review of 21 October 2026; builds on Phase 3 (signup, email verification,
> roles per business) and Phase 4 as built (plans, subscriptions, entitlements, read-only middleware).
> **Branch:** `saas/signup-trials-invites` · **Depends on:** Phase 4, Phase 5 Part A (roles per business)

---

## Goal

Only real accounts with strong passwords get in; every new business starts a 14-day trial from signup, either
a Free Trial or a trial of the tier it picks; a new owner can use the system straight away and must verify
their email within 24 hours; and adding a user is one invite flow where the invitee sets their own password.

## Before you start

No open question blocks this stage.

## Decisions that apply

- **4.6 Billing (Phase 4, confirmed):** per-shop pricing (Tier 1/2/3 at KES 1,500, 3,000 and 5,000 per shop per
  month) and limits as plan data; one `hasFeature()`/`limit()` check everywhere; lapse = read-only plus export
  for 30 business days, writes refused with `subscription_inactive`; manual payments in the platform console
  until M-Pesa go-live (around 12 November 2026).
- **Signup and invites review (21 October 2026):**
  - **Trial length is 14 days, counted from signup** (not from email verification).
  - **Every signup starts on a trial:** either the **Free Trial** (1 shop, 3 users) or a **14-day trial of the
    tier picked** (that tier's limits). Paying continues the subscription after the trial.
  - **Roles on a trial work as on a paid plan:** the person who signs up is the owner (`admin`, never the platform
    `super-admin`) and can add users with any role they may give, `admin` included.
  - **Email verification:** the system is usable unverified for 24 hours after signup; after that, access is
    blocked until the email is verified. **Changing the owner's email restarts the 24-hour window.**
  - **Email rules:** generic domains (`test.com`, `example.com` and similar) and placeholder addresses are
    blocked; role-style addresses (`info@`, `sales@`) are allowed.
  - **Passwords:** common and easily guessed passwords (`Password123` and similar) are refused.
  - **Create user and invite user are one action:** email and role required, name optional; the invitee sets
    their password (and name if missing). **Invites expire after 72 hours.**
- **Changing plan during a trial (22 October 2026):**
  - Choosing a tier during a trial changes the plan and limits at once and keeps the trial's end date.
  - Paying during a trial starts the paid period on the trial's end date; the remaining trial days are kept.
  - Moving to a smaller plan follows the Phase 4 downgrade rule.

## Relevant current state

- **Billing (Phase 4):** `Plan`, `Subscription`, the entitlements service (`hasFeature()`/`limit()`), the
  `Feature` enum and the read-only middleware exist. Plan data has placeholder limits (staff, products,
  registers, WhatsApp sessions, features) and a placeholder trial length. New businesses start on a trial.
- **Signup and verification (Phase 3):** the trial starts **after** email verification. This stage changes
  that: the trial starts at signup and verification gets a 24-hour grace period.
- **Roles (Phase 5 Part A):** `admin` is the owner role; `GrantableRole` decides which roles a person may give;
  inactive roles can't be given.
- **Users:** creating a user (admin sets the password) and inviting a user are separate screens and endpoints.

---

## Work

### 1. Signup validation

#### 1.1 Email
- [x] Syntactically valid email (RFC 5322-compatible validator).
- [x] **Blocked local parts** (case-insensitive, after stripping `+tags` and, for Gmail, dots): `test`,
      `testing`, `tester`, `test1`–`test999`, `demo`, `example`, `sample`, `user`, `asdf`, `qwerty`, `abc`,
      `abc123`, `aaa`, `xxx`, `fake`, `noreply`, `no-reply`, `null`, `foo`, `bar`, `foobar`.
- [x] **Blocked domains:** `example.com`, `example.org`, `example.net`, `test.com`, `test.org`, `test.net`,
      `domain.com`, `email.com`, `*.test`, `*.example`, `*.invalid`, `*.localhost`, plus a disposable-provider
      list (e.g. `disposable-email-domains`) stored as data so it can be updated without a deploy.
- [x] **Role-style addresses allowed:** `info@`, `sales@`, `support@`, `accounts@` and similar on a real domain.
- [x] Domain must accept mail (MX or A lookup, cached); on DNS timeout allow and log.
- [x] Unique among owners (normalised, lowercase).
- [x] Generic error text that doesn't reveal the blocklist: "Please use a real email address you have access to."

#### 1.2 Password
- [x] 10–128 characters.
- [x] Not in a common-passwords list (top 10k–100k, case-insensitive).
- [x] Not breached: Have I Been Pwned range API (k-anonymity); on failure fall back to the local list.
- [x] Doesn't contain the email local part, the person's name or the business name.
- [x] No trivial patterns: repeated characters, sequences (`12345678`, `qwertyui`), common word plus digits or
      year (`Password123`, `Welcome2024`, `Admin@123`).
- [x] Strength meter on the form (zxcvbn, minimum score 3); the server check is authoritative. (written, not yet checked in a browser)

#### 1.3 Shared
- [x] Email and password rules as reusable validation rules, used by signup, invite acceptance (section 4),
      password reset and owner email change.
- [x] Signup rate-limited per IP and per email (e.g. 5 per 15 minutes).

### 2. Plans and trials at signup

#### 2.1 Signup flow
- [x] Signup opens on a **plan picker**: the Free Trial card and the three tiers, read from plan data. Each tier
      card shows its price per shop per month and its limits (shops, staff, registers, WhatsApp sessions,
      features).
      **Changed 23 October 2026 (`fix/signup-page-ui`, decided by the product owner):** the cards are no longer
      shown. The Free Trial is chosen by default; the plans (name and price) are folded behind "Change", which
      opens by itself when a pricing page preselects a tier (`?plan=tier-2`). The form keeps four fields (name,
      business, email, password with a show/hide toggle; no password confirmation) and a Terms checkbox, required
      by the server (`agree_terms`); its links come from `TERMS_URL` and `PRIVACY_URL`.
- [x] Picking a tier asks for the number of shops (the subscription's shop count) and shows the monthly total
      the business will pay after the trial.
- [x] Every card says "14-day trial, no payment now".
- [x] On submit, one transaction creates the business, the owner (role `admin`), the first shop, the role
      template copies (`CopyRoleTemplates`) and the subscription in its trial state with
      `trial_ends_at = signup + 14 days`.

#### 2.2 Free Trial
- [x] A **Free Trial plan record** (plan data, not code): 1 shop, 3 staff (the owner counts as one), and trial
      limits for registers, products and WhatsApp sessions; 14 days.
- [x] The person who signs up is the owner (`admin`). They can add users with any role, `admin` included,
      subject to `GrantableRole` (only an owner gives `admin`). No role is locked on the Free Trial.
- [x] One Free Trial per owner email and per business; a second signup with the same email is refused.

#### 2.3 Tier trials
- [x] A tier trial uses the picked tier's plan and limits, with its shop count, for 14 days.
- [x] Roles work as on the Free Trial and on paid plans (role templates, `GrantableRole`).

#### 2.4 Limits
- [x] All limits go through the existing `limit()`; no new checks.
- [x] **Pending invites count toward the staff limit:** `limit()` counts active staff plus pending (not expired,
      not revoked) invites.
- [x] Shop and register limits as built in Phase 4.

#### 2.5 Trial end
- [x] Trial end is a **lapse**: the existing read-only middleware applies (read-only plus export for 30 business
      days, `subscription_inactive`, then the closure flow). No new status.
- [x] Reminders before the trial ends (e.g. 3 days and 1 day before) through the Phase 4 reminder notifications.
- [x] Continuing after the trial:
  - **Tier trial:** pay for the tier and shop count.
  - **Free Trial:** pick a tier and shop count, then pay.
  - Until go-live, payment is recorded by hand in the platform console; after go-live, the M-Pesa path.
- [x] Users and their roles carry over unchanged when a trial becomes a paid plan.

#### 2.6 Changing plan during a trial
- [x] **Choosing a tier without paying** (Free Trial → a tier, or one tier → another): the trial's plan, limits
      and shop count change immediately; `trial_ends_at` stays the same. The 14 days never restart.
- [x] **Paying during a trial:** the payment is accepted and recorded; the trial ends and the paid period
      starts on the original `trial_ends_at`, so the remaining trial days are kept. The first paid period runs
      from `trial_ends_at` to `trial_ends_at` + one billing period.
- [x] **Moving to a smaller plan during a trial** (fewer shops or lower limits than the business already uses):
      the Phase 4 downgrade rule applies; every shop is read-only until the business is within the new quota
      (sign-in, viewing, export and deleting shops still work). The plan picker warns before confirming.
- [x] **Staff over the new limit:** existing staff stay; new users and invites are refused by `limit()` until
      the business is within the limit.
- [x] Payments during a trial, manual (platform console) or M-Pesa after go-live, extend the subscription
      exactly once, as in Phase 4.
- [x] The billing page shows the current plan, shop count, trial end date and, after an early payment, the date
      the paid period starts.
- [x] Every plan change during a trial is recorded in the audit log (who, from, to, when).

### 3. Email verification with a 24-hour grace period

#### 3.1 Behaviour
- [x] **Change from Phase 3:** the trial no longer waits for verification; the owner is signed in at signup and
      a verification email is sent.
- [x] For 24 hours the system is fully usable unverified, with a banner: "Verify your email — X hours left" and
      **Resend**.
- [x] After 24 hours unverified, everything is blocked except verify, resend, change email and sign out. The
      block covers **web, the flat API, `/api/v1` and sync push** (sync push is Phase 7; it will sit behind the same middleware), returning `403` with
      `email_verification_required`.
- [x] Verifying restores access immediately; nothing is lost.
- [x] The trial clock keeps running during the grace period and while blocked.

#### 3.2 Email change
- [x] Changing the owner's email clears `email_verified_at`, sets `verification_deadline_at = now + 24 hours`,
      sends a verification email to the new address, and notifies the old address of the change.
- [x] The same grace-period behaviour then applies.

#### 3.3 Implementation
- [x] `users.verification_deadline_at` (nullable); set at signup and on email change.
- [x] Middleware: unverified and past `verification_deadline_at` → block, alongside the Phase 4 read-only
      middleware.
- [x] Verification links single-use and signed, valid 48 hours; resending invalidates older links.
- [x] Resend rate-limited (1 a minute, 5 an hour). Optional reminder at about 20 hours (not built).
- [x] Invited users are verified on accepting their invite.

### 4. Unified "Add user" (invite)

#### 4.1 Form
| Field | Required | Notes |
|---|---|---|
| Email | Yes | Email rules from 1.1 |
| Role | Yes | Roles the inviter may give (`GrantableRole`; only an owner gives `admin`); inactive roles hidden |
| Name | No | If blank, the invitee sets it on accepting |
| Shop(s) | If applicable | Shops the user can access |

No password is set by the inviter.

#### 4.2 Flow
> **As built:** the existing `business_invites` table is extended (optional `name`, 72-hour expiry) instead of a
> new `user_invitations` table with a placeholder user. An invited person has no `users` row until they accept
> (so `users.status = invited` and nullable `name`/`password` aren't needed), and an invite for an email that
> already has a login adds a membership to it, as in Phase 3.
- [x] Submitting checks `limit()` (staff, including pending invites), then creates the user with
      `status = invited` and an invitation, and emails a single-use link.
- [x] The **accept page** shows the business name and role; name pre-filled if given (editable), **required** if
      not; password and confirmation using the 1.2 rules.
- [x] Accepting saves name and password, sets `status = active` and `email_verified_at = now`, and signs the user
      in.

#### 4.3 Managing invites
- [x] Users list shows invited users as **Pending**, with **Resend**, **Revoke** and **Change role**.
- [x] Links expire after **72 hours**; an expired link shows "Ask your admin to send a new invite."
- [x] Resending invalidates the previous link; revoking frees the staff slot.
- [x] An email already belonging to a member of the business is refused.

#### 4.4 Billing and roles rules
- [x] Sending, resending and accepting invites are refused while the business is read-only (lapsed or over shop
      quota) with `subscription_inactive`.
- [x] Accepting is refused if the role was switched off after the invite was sent; the invitee is told to ask
      for a new invite.

#### 4.5 Removing the old path
> **As built:** the web path is gone (`users.create` redirects to Add user; there is no `users.store`; the
> view and `StoreUserRequest` are deleted). The flat API's `POST /api/users` (admin sets a password, the
> Flutter contract) is **still there**, awaiting a check of the app (not on this machine) and an agreed
> replacement; so this item stays open.
- [ ] Remove the "Create user" screen and endpoint where the admin sets a password; every entry point opens
      "Add user". Check the Flutter app for any use of the old endpoint.

---

## Schema

- [x] Free Trial plan record (data): 1 shop, 3 staff, trial limits, 14 days. Trial length set to 14 days on the
      tier plans.
- [x] `users.verification_deadline_at` nullable; existing unverified business owners get `now + 24 hours` on deploy (staff added by an admin were never asked to verify, so they get none).
- [x] ~~`users.status` invited; nullable `users.name`/`password`~~: not needed, see 4.2 "As built".
- [x] Invitations: `business_invites` extended with `name`; pending ones cut back to 72 hours (migration `2026_10_23_000005`).
- [x] Disposable-domain list as data (`disposable_email_domains`, loaded by the migration and `signup:load-disposable-domains`).
- [ ] Every migration additive or carrying data across; rehearsed on `import_testing` with counts before and
      after; snapshot before UAT.

## Acceptance criteria

**Validation**
- [x] `test@gmail.com`, `demo@example.com`, `anything@test.com` and a disposable-provider address are refused;
      `john+shop@gmail.com` and `info@realshop.co.ke` are accepted.
- [x] `Password123`, `Welcome2024` and `qwerty123456` are refused on signup, invite acceptance and password reset;
      a long random passphrase is accepted.

**Trials**
- [x] Every signup starts a trial ending 14 days after signup, whether or not the email is verified.
- [x] A Free Trial business can't add a second shop or a 4th staff member, pending invites included, on web and
      both APIs.
- [x] The person who signs up is `admin` (never `super-admin`) and can add users with any role, `admin`
      included; a non-owner can't give `admin`.
- [x] A tier trial has that tier's limits and its chosen shop count.
- [x] The same email can't start a second Free Trial.
- [x] At trial end the business is read-only with `subscription_inactive`; a payment (manual, or M-Pesa after
      go-live) reopens it exactly once.

**Changing plan during a trial**
- [x] A Free Trial business that picks Tier 2 with 3 shops on day 5 gets Tier 2 limits and 3 shops at once;
      `trial_ends_at` is unchanged.
- [x] Switching tiers during a trial, any number of times, never moves `trial_ends_at`.
- [x] A payment on day 5 of a trial starts the paid period on the original `trial_ends_at`; the business keeps
      its trial days and the payment is applied exactly once.
- [x] Moving to a plan with fewer shops than the business has makes every shop read-only until enough shops are
      deleted; the picker warns before confirming.
- [x] Moving to a plan with a lower staff limit keeps existing staff and refuses new users and invites until
      within the limit.
- [x] Each plan change during a trial is in the audit log.

**Verification**
- [x] A new owner can use the system immediately.
- [x] At 23h59m everything works; at 24h01m unverified, web, both APIs and sync push return
      `email_verification_required`, except verify, resend, change email and sign out.
- [x] Changing the owner's email starts a new 24-hour window and notifies the old address.

**Invites**
- [x] A user can be added with only email and role.
- [x] The invitee sets a password (and a name if none was given) on one page and is verified on accepting.
- [x] Expired, revoked or used links can't be reused; resend invalidates the old link.
- [x] An inactive role can't be offered or accepted.
- [x] Invites are refused while the business is read-only.
- [ ] The old create-with-password path no longer exists.

**Contract and checks**
- [ ] Flat API and `/api/v1` contract tests pass; any changed responses (signup, user creation) are documented
      for the Flutter app.
- [x] Invite and accept routes added to the isolation resource matrix: business A can't see, resend, revoke or
      accept business B's invites.

## Risks

- **Enforcement gaps:** the verification block missing a write path (sync push, a legacy endpoint), as with
  read-only mode in Phase 4. Mitigation: reuse the same middleware coverage and route list.
- **Trial abuse:** repeated Free Trials with new emails. Mitigation: email rules, one trial per email; review
  usage once live.
- **Plan changes during a trial:** an early payment applied twice, or applied from the payment date instead of
  the trial end, would shorten or double the paid period. Mitigation: one code path sets the paid period start
  to `trial_ends_at`, payments are idempotent, and tests cover early, on-time and late payments.
- **Email deliverability:** a lost verification email locks the owner out at 24 hours. Mitigation: resend,
  change email, and a reminder before the deadline.
- **Flutter:** sign-in may now return `email_verification_required`; the app must handle it. Check whether the
  app uses the old create-user endpoint.

## Rollout (UAT)

1. Snapshot: `php artisan db:snapshot`.
2. `php artisan migrate` (verification deadline, user status, invitations, nullable name and password).
3. Seed or update plan data: the Free Trial plan and the 14-day trial length on the tiers.
4. Load the disposable-domain list.

## Definition of done

- [ ] Acceptance criteria met, with tests (including time-travel tests for the 24-hour window and the 14-day
      trial).
- [ ] New routes added to the isolation resource matrix; isolation suite green.
- [ ] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.

## Points to confirm

None.
