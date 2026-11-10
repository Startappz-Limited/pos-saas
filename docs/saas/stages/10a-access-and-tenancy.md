# Stage: Access and Tenancy

> **Source:** the seventh round of stage 10's review, 28 October 2026 (the users list's buttons, an owner's scope,
> members who couldn't see shops); moved into its own stage in the eighth round, 28 October 2026.
> **Branch:** `fix/access-tenancy`, from `main` · **Depends on:** no stage (ninth round of stage 10, 28 October 2026:
> this stage grants and revokes no permission) · **Can ship** at any time, before or after stage 10 · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-10a) · **Already merged:** stages 3, 4 and 6
> ([05](./05-phase-3-accounts-roles-settings.md), [06](./06-phase-4-billing-entitlements.md),
> [08](./08-phase-6-signup-and-user-invites.md)), whose rules this stage builds on · **Shipped ahead in a hotfix:**
> `fix/api-authz` (`c099c16`, 29 October 2026): the flat API's write authorization and the deleted-shop names (see
> "Hotfix" under Revisions) · **Split (2 November 2026):** the return to the Free Trial and `ChangeBusinessPlan` moved to
> [stage 21](./21-plans-billing-trial-banner.md), step 3; the business hygiene commands and the go-live steps moved to
> [24-go-live.md](./24-go-live.md) (see "Points to confirm")

---

## Revisions

Stage 10's seventh-round note, kept as written (its gap and step numbers are stage 10's):

**Seventh round (28 October 2026):**
- **Found: the users list shows no View, Edit or Delete buttons to owners** (gap 20, new step 1h). The shared
  `<x-ui-action-buttons>` component checks invented permissions (`users.show`, `users.edit`, `users.destroy`) instead
  of the policy, so only platform operators (through `Gate::before`) see them.
- **Decided: an owner's rights stop at their own business** (gap 21, new step 1i): pinned by tests on every surface,
  with the one weak spot found fixed (a `User` loaded for one business answering with its roles in another).
- **Found: members can't see shops** (gap 22, new step 1j): only the manager template holds `shops.view`, and members
  invited without a shop see none. **Decided:** viewing a shop follows access; the owner grants shop access on the
  invite; on the Free Trial every member has the business's default shop; on any other tier (its trial included) the
  owner chooses each member's shops on the invite.

**Created 28 October 2026 (eighth round)** from stage 10's steps 1h, 1i and 1j, moved without change apart from
numbering: stage 10's gaps 20, 21 and 22 are gaps 1, 2 and 3 here, and its steps 1h, 1i and 1j are steps 1, 2 and 3.
They are about who may see and do what, not about stock, and need only stage 10's step 0, so they can ship first.
*(Superseded: since stage 10's ninth round this stage depends on no stage.)*

**Ninth round of stage 10 (28 October 2026):** this stage depends on no stage (decided). Checked 28 October 2026: none
of its steps grants or revokes a permission (step 3 changes `ShopPolicy`, not role grants), so it needs neither
`role_revoked_permissions` nor `permissions:sync` from stage 10's step 0.

**First revision (28 October 2026):** this stage's own review (code facts checked 28 October 2026):
- Gap 3: shop creation is limited by plan (one shop on the Free Trial, enforced on every `Shop::create`); only viewing
  shops isn't gated.
- The "Created" line on step 0 marked superseded.
- Step 1: deleting a member removes their membership of this business only, completing `RemoveFromBusiness` (invites,
  till PIN, default business, audit); "primary owner" defined (`businesses.primary_owner_id`); links to stage 3.
- Step 1: who may edit whom (decided): the code's owner rule kept; a non-owner can't edit a stronger member; nobody
  changes their own role; tests updated.
- The Free Trial is identified by the plan's `is_trial_plan` through `Business::onFreeTrial()`; a lapse is read-only,
  never a return to the Free Trial; a return from a tier's trial keeps members' shops (decided). *(Superseded: since
  the third revision a return needs exactly one shop, so there are no other shops to keep.)*
- The default shop matters only on the Free Trial (decided): its delete and deactivate guard applies only there.
- Step 2: the command test reworded (commands act as the system).
- Step 3: the ways a member joins listed; "hand-over" and "role change" removed; the one-shop wording; the "No shop
  yet" link's permission.
- Risks: unbound flat API tokens follow the default business; one line added to the app checklist.
- Contract: "A product," removed.
- Acceptance: criteria for the default shop, the switcher's audit, deleting a member, the edit rules and the Free
  Trial.
- "Before you start", "Points to confirm" and Rollout updated.

**Second revision (29 October 2026):** (code facts checked 29 October 2026)
- A return to the Free Trial gives the default shop to every member, keeping their other shops; the stage 6 rule it
  relies on was corrected (§2.6: over quota, every shop is read-only, none writable); a writable default shop is an
  open proposal. *(Superseded by the third revision: a return needs exactly one shop, so no other shops are kept and
  no shop is read-only on return.)*
- The switch back to the Free Trial is refused while the business has no active shop.
- "Usable" business defined (`User::usableBusinesses()`) and used for the new default business and for deleting
  unbound tokens.
- `LegacyImport` is the one-off pre-go-live import; this stage's commands cover its output.
- Audits with no signed-in user record the actor as System; tests added.
- What a person with no usable business sees on sign-in, with a test.
- Businesses with no subscription: listed and closed before this stage's commands (1 on UAT).
- `members:clean-removed`: removed members told apart from deactivated ones; only invites from before the removal
  revoked; unbound tokens deleted when nothing usable is left; every change audited; tests.
- Every automatic default-business move is audited; the token risk's mitigation updated.
- `--all` on the four commands; Rollout updated.
- Acceptance criteria for `members:clean-removed` and the "No shop yet" link; a risk for the cleanup's invite
  revocation; Revisions put in date order; the header names the merged stages.

**Third revision (29 October 2026):** (code facts checked 29 October 2026)
- The open proposal decided (option A): a return to the Free Trial is refused while the business has more than one
  shop ("Delete shops until one remains before returning to the Free Trial."); deactivated shops count toward
  `Limit::Shops` (`PlanLimits::count()` excludes only soft-deleted ones), so the message says "delete"; Phase 4's
  read-only rule is unchanged; the proposal removed from "Points to confirm"; tests added.
- `business_user.removed_at` (nullable), set by `RemoveFromBusiness`; `members:clean-removed` uses it, applies the
  no-role rule once to rows from before this stage (listed separately in the dry run; `--apply` sets their
  `removed_at`); "invites created before the removal" uses `removed_at`; Schema, tests, Risks and Rollout updated.
- "Active business" defined: `businesses.status = 'active'`; a lapsed (read-only) business is still usable; only a
  suspended, closing or pending-verification one isn't; a test added.
- App checklist: the app handles 403 `no_active_business` on every flat API and `/api/v1` call.
- Business 7 is given the Free Trial at rollout, which starts a 14-day trial; it has no owner, member or shop.
  *(Superseded by the fourth revision: it is closed instead.)*
- `businesses:set-default-shop --all` covers every business, not only active ones.

**Fourth revision (29 October 2026):** (code facts checked 29 October 2026)
- Business 7 is closed at rollout, not given a plan: it came from the stage 3 sign-up (status
  `pending_verification`, its five role copies, an `admin` grant), not `LegacyImport` (which inserts `active`), and
  its owner (user 56) was later deleted outside the app, taking the membership with it. New
  `businesses:close-orphaned`; production can hold such rows only through a pre-Phase-6 sign-up or a deleted user, and
  `LegacyImport`'s business has no subscription, so `businesses:list-unsubscribed` runs against production at go-live
  (new "Go-live" section). `ChangeBusinessPlan` gives a new subscription the business's shop count, not 1.
- Shop deletion is soft and keeps sales, stock, M-Pesa payment requests and audit rows; it clears the shop's staff
  and M-Pesa till registration. The refusal says the history is kept; Risks name what a deleted shop hides (its name
  through `belongsTo`, 5 unguarded views) and that it can't be restored; history relations include deleted shops.
  Found: the flat API's shop create, update and delete check no permission; fixed in step 3. *(Superseded: both
  shipped in hotfix `fix/api-authz`, which authorized every flat API write and named deleted shops on the 5 pages.)*
- Pending verification: sign-up has created an `active` business since Phase 6 (2), with 24 hours to verify; only
  rows from the stage 3 sign-up are `pending_verification`; what an unverified owner reaches is stated, with a test.
- `/api/v1` with a bound token of a suspended or closing business: today no business is resolved and no middleware
  refuses the call; now 403 `no_active_business` on every call but sign-out; the app checklist updated. *(Fifth
  revision: `business_suspended` for a suspended business.)*
- Reactivating a removed member: the web's Activate and a user update with `status` (web and flat API) could make a
  removed member active with no role; refused while `removed_at` is set ("Re-invite them instead."); only accepting a
  re-invite (`AcceptBusinessInvite`) clears it (corrected from `User::joinBusiness()`); tests.
- Text made obsolete by the one-shop return rule removed (members keeping other shops; shops read-only on return);
  earlier revisions marked superseded.
- `members:clean-removed --all` covers every business, whatever its status.

**Hotfix `fix/api-authz` (29 October 2026, `c099c16`, from `main`):** two of the fourth revision's items shipped
ahead of this stage and are removed from its work:
- **The flat API's writes are authorized.** The sweep found that not only the shop endpoints but most flat API writes
  checked only shop access: products, customers, categories, suppliers, expenses and expense categories, purchase-order
  actions, sales (store, update, delete, complete, void, collect payment), online orders, delivery companies, sale
  sources and cash registers. Each now checks the web's policy; the till helpers with no web check (opening and
  closing a register, adding a sale source or delivery company) need `sales.create`, and managing delivery companies
  needs `settings.update`. Five FormRequests authorize before validating, so a refusal is 403 whatever the input.
  `Api\ShopController::destroy` goes through `DeleteShopAction`. A refusal is Laravel's `{message}` with 403, like the
  flat API's other policy refusals (not `{success: false, message}` as written before). `/api/v1` writes already had
  their checks (owners only, `settings.update`, policies, or a register token).
- **Deleted shops are named on old records.** The 5 pages (`products/show`, `orders/show`, `abandoned-carts/show`,
  `stock-adjustments/edit`, `purchase-orders/index`; the last guarded already and showed "—") load the shop with
  `withTrashed()` and show "Name (deleted)" (`Shop::displayName()`).
- Checked with it: a signed-in request with no business set reaches no business's rows (the scopes add `1 = 0`); a
  bound `/api/v1` token of a suspended or closing business sees none, pinned by
  `TenantIsolation/NoBusinessResolvedTest`. The `no_active_business` answer for those tokens stays this stage's work.

**Fifth revision (2 November 2026):** (code facts and UAT counts checked 2 November 2026, read-only)
- `/api/v1` with a bound token whose business can't be entered: **403 `business_suspended`** when the business is
  suspended (as the flat API answers), **403 `no_active_business`** when it is closing or otherwise unusable (decided;
  the fourth revision's open point closed). The app checklist updated.
- `ChangeBusinessPlan`'s shop count stated: the business pays `price_at_signup × shop_count` a month from its first
  payment after the trial (`Subscription::monthlyTotal()`), nothing during it; the Free Trial is always one shop, and
  the console refuses it for a business with more than one. Proposed to move, with the "Go-live" section: no import or
  go-live stage exists (checked), so `ChangeBusinessPlan` to stage 21 and Go-live to a new stage; nothing moved yet.
- `businesses:close-orphaned` also **lists** (never closes) businesses with shops but no active owner (UAT: 3 and 5),
  and removes role grants of users that no longer exist (UAT: 5 grants, users 51 to 54 and 56). UAT's orphaned
  businesses are 4 and 7, not 7 alone (business 4 has a subscription, so `list-unsubscribed` misses it).
- A split of this stage proposed for decision ("Points to confirm"); nothing moved.

**Split (2 November 2026, decided):** the return to the Free Trial and `ChangeBusinessPlan` moved to [stage 21](./21-plans-billing-trial-banner.md),
step 3; businesses with no subscription, `businesses:close-orphaned` (businesses 3 and 5 now closed too, by
decision; revised the same day: an operator confirms them as UAT test data, and only empty businesses are closed
automatically), removing deleted users' grants, `LegacyImport` and the Go-live section moved to [the Go-live stage](./24-go-live.md). Each
place they were now links to where they went; the table is in "Points to confirm".

---

## Goal

Everyone sees the actions they may take, and only those. Being the owner of one business gives nothing in another.
Every member can see the shops they work in, whatever their role; on the Free Trial that is the business's default
shop, and on any other tier the shops the owner chose on the invite.

## Before you start

Nothing must be merged first: this stage grants and revokes no permission (checked 28 October 2026), so it doesn't
need stage 10's step 0. Everything is decided, and the revisions' decisions are in "Decisions that apply". Since the
split (2 November 2026) the billing and go-live parts are in stage 21 and the Go-live stage (see "Points to confirm").

## Decisions that apply

- **An owner's rights stop at their own business (decided 28 October 2026, seventh round).** Being the owner of one
  business gives nothing in another: not in a business where the person is a member with another role, not in one
  where they aren't a member, and not through a token, a queued job, a command or a cached role. Only a platform
  operator acts across businesses.
- **Shop access (decided 28 October 2026, seventh round):**
  - **Viewing follows access:** any member may list and open the shops they can reach (the owner every shop, others
    their assigned shops), whatever their role; managing a shop still needs its permissions.
  - **The owner grants shop access on the invite;** the person accepting chooses nothing.
  - **On the Free Trial, every member has the business's default shop**, automatically (the Free Trial allows one
    shop). Once the business moves to another tier, the owner can change a member's shops.
  - **On every other tier, its trial included, the owner sets on the invite which shop(s) the member has access to**;
    at least one is required (confirmed 28 October 2026).
  - **How the Free Trial is known (first revision, 28 October 2026):** a business is on the Free Trial when its
    subscription's plan has `is_trial_plan` (checked 28 October 2026: only the `free-trial` plan has it; a tier's
    trial is a `trialing` subscription on a tier plan, whose flag is false; the code tests the flag in `StartTrial`,
    `ChangeTrialPlan`, `BillingController` and `ApplyPlatformPayment`). One helper, `Business::onFreeTrial()`, is used
    everywhere this stage asks.
  - **Leaving and returning:** a lapse is read-only, never a fall back to the Free Trial
    ([stage 4](./06-phase-4-billing-entitlements.md), "Decisions that apply"). A return to the Free Trial from a tier's
    trial, and its rule (exactly one shop, and it active; every member then has that shop, the default), **moved to
    [stage 21](./21-plans-billing-trial-banner.md), step 3** (split, 2 November 2026). It relies on this stage's default shop, so "on the Free
    Trial every member has the default shop" still holds.
  - **The default shop matters only on the Free Trial** (decided 28 October 2026): on other tiers it is kept but
    used for nothing, and may be deleted or deactivated.
- **Members and owners (first revision, 28 October 2026):** removal, owner protection ("only owners delete") and
  hand-over are decided in [stage 3](./05-phase-3-accounts-roles-settings.md) (People; 4.9). **The primary owner** is
  the business's founding owner, `businesses.primary_owner_id` (checked 28 October 2026: set by `RegisterBusiness`,
  moved by `HandOverBusiness`); a business can have other owners (co-owners), who hold `admin` too.
- **A usable business** (second revision, 29 October 2026) is one where the person has an active membership and the
  business itself is active (checked 29 October 2026: `User::usableBusinesses()`, which `User::businessToOpen()` and
  `EnsureActiveBusiness` use). This stage uses the word only in that sense.
  - **"Active business"** (third revision, checked 29 October 2026) means `businesses.status = 'active'`
    (`Business::STATUS_ACTIVE`). A lapse doesn't change that status: it is read on the subscription
    (`Entitlements::isLapsed()`, `readOnlyReason()` = `lapsed`), so **a lapsed, read-only business is still usable**;
    its members sign in and see it read-only. A business stops being usable only when its status changes:
    `suspended` (the platform console, `SuspendBusiness`), `closing` (an owner's closure, or the sweep when a lapse's
    grace period ends, `SweepSubscriptions`) or `pending_verification`.
  - **Pending verification doesn't block onboarding** (fourth revision, checked 29 October 2026). Since Phase 6 (2)
    (`4ecebbb`) sign-up creates the business `active`, with its trial and first shop (`RegisterBusiness`), and gives
    the owner 24 hours (`users.verification_deadline_at`) to verify their email. Until then the owner uses everything;
    after it, `EnforceEmailVerification` refuses everything but verifying, resending the link, changing the email and
    signing out, on the web (a redirect to the verification page) and both APIs (403 `email_verification_required`,
    not `no_active_business`); verifying restores access at once. Only businesses from the stage 3 sign-up (before
    `4ecebbb`) can still be `pending_verification`; verifying their owner opens them (`StartTrialOnEmailVerified`).
    UAT has one, business 7, which has no owner left.
- **Audits with no signed-in user** (second revision, 29 October 2026): a change made by a command, a queued job or
  billing code records no person; its audit row's user columns are empty and every audit view shows the actor as
  "System" (checked 29 October 2026: `Auditable::dispatchAuditEvent()` takes `Auth::user()`, and the audit-log views
  print `user_name ?? 'System'`).
- **Who may edit whom (decided 28 October 2026):**
  - owners are edited only by owners, and the primary owner only by themselves (the code's rule, kept);
  - a non-owner can't give a role that holds a permission they don't hold (the code's rule, `GrantableRole`), and
    can't edit, or change the role of, someone whose role holds a permission they don't hold;
  - nobody changes their own role.
- **Tenancy rules** (Phase 1a to 3): every business-owned and shop-owned record carries its scope; shop access is
  default-deny; user route binding resolves only members of the current business; only a platform operator passes
  `Gate::before` (checked 28 October 2026).

## Relevant current state

Checked against `main` after Phase 7 (`832f684`) and on UAT, 28 October 2026 (read-only).

1. **The users list shows no row buttons to owners** (checked on UAT, 28 October 2026, as the owner of business 8).
    `users/index.blade.php` uses `<x-ui-action-buttons :model="$user" route="users" />`, which, with no permission
    passed, checks `@can("users.show")`, `@can("users.edit")` and `@can("users.destroy")`. None of those permissions
    exists (the real ones are `users.view`, `users.update`, `users.delete`), so every check fails and the actions
    column is empty for everyone but a platform operator, whom `Gate::before` lets through. `UserPolicy` itself allows
    the owner to edit (both business 8 members), and the user's own page shows "Edit" through `@can('update', $user)`,
    so the server isn't the problem. The users list is the component's only caller. `UiButtonLinkTest` checks only
    "Add User" on that page, which is why nothing caught it.
2. **How owner rights are scoped today** (checked 28 October 2026):
    - the owner is whoever holds the shared `admin` template **in the current business** (Spatie teams on
      `business_id`; `model_has_roles.business_id`); `User::isBusinessOwner()` asks `hasRole('admin')` only when a
      business is set; on UAT every owner holds the template in their own business only;
    - `Gate::before` already lets only platform operators through (not owners), as stage 10's step 0 requires;
    - `TenantContext::set()` moves Spatie's team and clears the loaded `roles` and `permissions` of **the signed-in
      user only**: any other `User` model loaded while one business was set (in a job, a command looping over
      businesses with `eachBusiness()`, a listener) keeps that business's roles when asked in the next. That is the
      weak spot;
    - user route binding resolves only members of the current business; tenant scopes keep data per business;
    - an unbound flat API token acts in the user's default business, so a person who is owner in one business and
      cashier in another acts with whichever role their default business gives them;
    - nothing tests that an owner of A has no extra rights in B.
3. **Members can't see shops** (checked on UAT, 28 October 2026). The free trial itself isn't the cause: nothing about
    viewing shops is gated by plan, trial or read-only mode (creating them is, below). Two things are:
    - **`shops.view` is held by the manager template only.** `ShopPolicy::viewAny` needs it, and `view` needs it plus
      access to the shop, so cashiers, inventory clerks and accountants are refused the shops page even for their own
      shops (business 9's accountant, user 58, assigned shop 16: refused). Every business's copies match their
      templates; this isn't the rollout bug (stage 10, gap 11).
    - **Shop access is default-deny, and an invite's shops are optional** (`StoreBusinessInviteRequest`: `shop_ids`
      nullable; `AcceptBusinessInvite` assigns only the invite's shops). A member invited without a shop sees none:
      business 12 (free trial, one shop), manager user 63, has `shops.view` and an empty list. On UAT 5 of 49 non-owner
      members have no shop (businesses 1 and 2: four accountants, five shops each; business 12: one manager).
    - **The web and the flat API disagree:** `Api\ShopController::index` checks no permission and lists the person's
      reachable shops (`ShopAccessScope`), so a cashier sees their shops in the app and is refused on the web.
    - **No "default shop" exists.** Sign-up creates the business's first shop (`RegisterBusiness`); nothing records it
      as the default. UAT: businesses 9 and 12 are on the Free Trial (plan `free-trial`); businesses 1, 2 and 8 are on
      a tier's trial (plans 3 and 2), which follows the tier's rules.
    - **Creating shops is limited by plan** (checked 28 October 2026). `AppServiceProvider::registerPlanLimits()`
      checks every `Shop::create` (`Shop::creating`, `PlanLimits::assertCanAdd(Limit::Shops)`) against the
      subscription's `shop_count` (`Entitlements::limit()`), which `StartTrial` and `ChangeTrialPlan` set to 1 on the
      Free Trial; `BillingController::shops` refuses to raise it there. A refusal is `PlanLimitExceeded`
      (`plan_limit_reached` on JSON, a message with "Upgrade" on the web). It covers the web (`CreateShopAction`), the
      flat API (`Api\ShopController::store`) and sign-up (`RegisterBusiness`); the one-off `LegacyImport` copies
      `shops` rows directly and isn't checked; a business with no subscription isn't metered.
    - **Businesses with no subscription, orphaned businesses and `LegacyImport`:** moved to [the Go-live stage](./24-go-live.md)
      (split, 2 November 2026), with how business 7 was made.
    - **Deleting a shop** (fourth revision, checked 29 October 2026): soft (`Shop` uses `SoftDeletes`; no code
      force-deletes a shop, and there is no restore). The web (`DeleteShopAction`) detaches its staff (`shop_user`)
      and clears its M-Pesa till registration (`mpesa_shortcode`, `mpesa_shortcode_type`, `mpesa_verified_at`) so
      another shop can register it; foreign-key cascades fire only on a hard delete, so its sales, sale items,
      payments, stock rows and movements, cost layers, registers, M-Pesa payment requests and audit rows (a separate
      database) all stay. The owner still reaches them (`TenantContext::accessibleShopsQuery()` reads `shops` without
      a `deleted_at` filter); a deleted shop no longer counts toward `Limit::Shops`. What it hides: `belongsTo(Shop)`
      relations (`Sale::shop()`, `StockMovement::shop()`, `CashRegister::shop()` and others) skip deleted shops, so
      a deleted shop's name is missing on its old records unless the page loads it with `withTrashed()`. UAT: 2
      deleted shops, with no sales. *(Hotfix `fix/api-authz`: the 4 pages that failed on a deleted shop, and the one
      that showed "—", now show "Name (deleted)"; the API's delete goes through `DeleteShopAction`.)*
    - **The flat API's writes** checked only shop access (fourth revision, checked 29 October 2026). *(Fixed by hotfix
      `fix/api-authz`, `c099c16`: every flat API write checks the web's policy or a permission.)*
    - **Deactivated and removed members look alike** (checked 29 October 2026): deactivating sets the membership to
      `inactive` (`UserService::changeStatus`, `UserStatus::INACTIVE`), as removing does; only a removed member has no
      role left in the business (`RemoveFromBusiness` calls `syncRoles([])`). `business_user` has no removal date; its
      `updated_at` is set when the status changes, and by any later change to the row, so it isn't a reliable removal
      date. This stage adds `business_user.removed_at` (step 1).
    - **Commands take one business** (checked 29 October 2026): `RunsForBusiness` has `--business` only, no `--all`;
      `TenantContext::eachBusiness()` runs a body for every active business.
    - **Removing a member** (checked 28 October 2026): `UserController::destroy` and `Api\UserController::destroy`
      check `UserPolicy::delete` (not oneself, by an owner, not the primary owner, not the last owner) and call
      `RemoveFromBusiness`, which sets the membership (`business_user`) inactive, removes the person's roles and shop
      assignments in this business and deletes their `/api/v1` tokens bound to it. The User row stays. It leaves their
      till PIN for the business (`till_pins`, one per business and person), pending invites to their email, and a
      `default_business_id` pointing here (`User::businessToOpen()` then falls back only when exactly one other
      business is usable); the removal itself isn't audited (`BusinessMembership` isn't `Auditable`; only the role
      change is, through `AuditAuthorizationChanges`).
    - **Editing members** (checked 28 October 2026): `UserPolicy::update` lets anyone with `users.update` edit
      themselves, and others when `mayManage()` allows (owners only by owners, the primary owner only by
      themselves); `GrantableRole` lets a non-owner give only a role whose permissions they all hold. Nothing stops a
      non-owner editing a stronger non-owner, or anyone changing their own role (`UserService::update` only keeps the
      last and the primary owner as owners).
    - **A person with no usable business** (checked 29 October 2026): `EnsureActiveBusiness` shows the web page
      `businesses.none` (403, "No active business: Your account is not a member of any active business. Ask a business
      owner to invite you, or sign out and create your own business.") and answers the APIs with 403
      `no_active_business`; an owner of a closing business gets the business picker instead.
    - **`/api/v1` after a suspension or closure** (fourth revision, checked 29 October 2026): a bound token's business
      is used only while the person may enter it (`ResolveTenant`, `User::canEnterBusiness()`: an active membership in
      an active business); otherwise no business is set. `/api/v1` routes carry `EnsureBusinessToken`, not the
      `business` middleware, so nothing refuses the call: permission-checked endpoints answer a generic 403 (stage 5's
      suspension test asserts only the status), `GET /api/v1/businesses` answers 200, and other endpoints run with no
      business (locally `MissingTenantContext`; in production a signed-in user's tenant queries return nothing).
      `SuspendBusiness` and `CloseBusiness` delete no tokens. The flat API answers 403 `business_suspended` for a
      suspended business and `no_active_business` otherwise.
    - **Reactivating a member** (fourth revision, checked 29 October 2026): user route binding resolves any member of
      the business, active or not (`User::scopeInCurrentBusiness()`), so a removed member can be opened. Two surfaces
      set the membership back to active: the web's Activate (`UserController::activate`, `UserService::changeStatus`)
      and a user update with `status` (`UpdateUserRequest` requires it; web and flat API, `UserService::update`). A
      removed member made active that way has no role and no shop. Accepting a re-invite also reactivates
      (`AcceptBusinessInvite`); `User::joinBusiness()` doesn't (`firstOrCreate`).

---

## Work

### 1. The users list shows the actions people may take (gap 1) — done, `fix/access-tenancy`
- [x] `resources/views/components/ui-action-buttons.blade.php` asks the model's policy instead of a permission named
      after the route: `@can($viewPermission ?? 'view', $model)`, `@can($editPermission ?? 'update', $model)`,
      `@can($deletePermission ?? 'delete', $model)`. A caller can still pass an ability. The component's docblock says
      so.
- [x] The users list is the only caller (re-checked 10 November 2026: still the only real caller; the other match is
      the component's own usage example inside its doc comment).
- [x] **Delete removes the member from this business only** (decided; [stage 3](./05-phase-3-accounts-roles-settings.md)
      decided removal). `RemoveFromBusiness`, used by the web and the flat API, also:
  - sets `business_user.removed_at` to now (third revision; nullable, new); accepting a re-invite
    (`AcceptBusinessInvite`, which makes the membership active again) clears it, and nothing else does (fourth
    revision; corrected from `User::joinBusiness()`, which doesn't reactivate);
  - revokes pending invites to the person's email for this business (`business_invites.revoked_at`);
  - deletes their till PIN for this business (`till_pins`);
  - when their `default_business_id` is this business, moves it to their earliest-joined other usable business, or
    sets it to null when they have none; with no usable business left, their unbound flat API tokens are deleted, and
    otherwise those tokens act in the new default; the move is audited (`default_business_changed`, with the reason
    `member_removed`), as every automatic move is;
  - records the removal in the audit trail (`member_removed`: who removed whom, from which business; "System" when
    no one is signed in).

  A person left with no usable business who signs in sees the "No active business" page (web) or 403
  `no_active_business` (APIs), as today.
- [x] **`/api/v1` refuses a bound token whose business can't be entered** (fourth revision): `EnsureBusinessToken`
      (or a middleware beside it on the `/api/v1` group) refuses, on every call except `POST /api/v1/auth/sign-out`
      (so the app can still sign out), a token bound to a business when `ResolveTenant` set none:
  - **the business is suspended:** 403 `business_suspended` ("This business is suspended. Nobody can work in it until
    it is reactivated."), the flat API's code and message (`EnsureActiveBusiness`; fifth revision);
  - **otherwise** (closing, pending verification, or the person's membership is inactive or gone): 403
    `no_active_business` ("You are not a member of any active business.").

  A removed member's tokens are deleted on removal; one issued between checks is refused too. Both answers use
  `ApiV1::error()`, so the body is `/api/v1`'s `{success: false, message, code}`.
  **Implementation note:** the refusal had to be added to `middlewarePriority` in `bootstrap/app.php`, right after
  `ResolveTenant`, not left unprioritised — otherwise Laravel's `SubstituteBindings` (route-model binding, e.g.
  `{sale:uuid}`) ran first under the now-unset `TenantContext`, 404ing before this check got a chance to answer
  `business_suspended`/`no_active_business`.
  **Conflict found and resolved with the user (10 November 2026):** "every call but sign-out", applied literally,
  broke an existing, tested recovery flow (`tests/Feature/Tenancy/ApiV1SignInTest.php`, "cuts a token off…"): a
  token whose business becomes inaccessible (membership suspended or removed, the business itself untouched) must
  still be able to call `GET /api/v1/businesses` (to see what else it can reach) and
  `POST /api/v1/businesses/{uuid}/switch` (to move there) — otherwise it could never recover without a fresh
  sign-in. **Decided:** the carve-out also covers `businesses.index` and `businesses.switch`
  (`EnsureBusinessToken::EXEMPT_ROUTES`); the switch's own destination check (404 for a business the token holder
  can't enter) is unaffected and still runs.

  It already makes the membership inactive, removes their roles and shop assignments here and deletes their
  `/api/v1` tokens bound here. A deactivated member's membership is `inactive` with `removed_at` null. Tables touched:
  `business_user`, `model_has_roles`, `shop_user`,
  `personal_access_tokens`, `business_invites`, `till_pins`, `users`, and the audit database. The User row and the
  person's memberships, roles and shops in other businesses stay. A person left with no business keeps the account
  and can accept new invites (an invite to an existing email adds a membership, stage 3).
- [x] **Members removed before this stage:** `php artisan members:clean-removed --business=<id>|--all` (dry run;
      `--apply`) brings every removed membership to the state a new removal leaves. A membership is removed when its
      `removed_at` is set (third revision). Rows from before this stage have none, so for them only, and once, it
      uses the second revision's rule: `inactive` and the person holds no role in the business (a deactivated member
      keeps their role and is left alone). The dry run lists those rows **separately**, as "removed before this stage
      (no role)", for review; `--apply` sets their `removed_at` to the membership's `updated_at`, the best date there
      is, and from then on they are handled like any other. For each removed membership, it:
  - deletes the till PIN for the business;
  - revokes pending invites to the person for the business **created before the removal** (`removed_at`), so a
    re-invite sent since survives;
  - moves a `default_business_id` that points at the business to their earliest-joined usable business, or null;
  - deletes their unbound flat API tokens when no usable business is left;
  - audits each change, with the actor "System".

  A second `--apply` changes nothing, and the no-role rule then finds no row without `removed_at`.
- [x] **A removed member can't be reactivated by hand** (fourth revision): while `removed_at` is set,
      `UserService::changeStatus` and `UserService::update` (via the shared `setBusinessStatus()`) refuse any actual
      change of the member's status, so the web's Activate, Deactivate and Suspend and a user update with `status`
      (web and flat API) are refused with "Re-invite them instead." — a `BusinessRuleException`, so it's a plain
      `back()->with('error', …)` flash on the web and `{success: false, message, code: 'member_removed'}` at 422 on
      the flat API, matching how this codebase's other non-field refusals already render (`Controller::failed()`'s
      doc comment), not a field-level `ValidationException`. Re-submitting the *same* status (e.g. Deactivate on an
      already-inactive removed member) is a no-op, not a refusal. **The users list already offers no Activate
      button anywhere** (checked 10 November 2026: no view references `route('users.activate', …)`; it's dead,
      reachable only by URL) — nothing to hide, so nothing was added there. Only an accepted re-invite brings them
      back, clearing `removed_at`, with the invite's role and shops (`AcceptBusinessInvite`).
- [x] **`--all`** (second revision): `members:clean-removed` takes `--all` (this step); `businesses:set-default-shop`,
      `users:grant-default-shop` and `users:list-without-shop` will take it when step 3 builds them. The mechanics are
      now generic on `App\Console\Concerns\RunsForBusiness` (`supportsAllBusinesses()`, `allBusinessesEveryStatus()`),
      so every existing `RunsForBusiness` command that doesn't opt in is unaffected (checked: 14 such commands,
      `--business`-only, unchanged). `--all` running for every **active** business in turn uses
      `TenantContext::eachBusiness()`, with a header line between businesses; `allBusinessesEveryStatus()` instead
      walks every business, whatever its status — `members:clean-removed --all` opts into that (fourth revision: a
      member removed from a suspended or closing business is cleaned too); `businesses:set-default-shop --all` will
      when step 3 builds it.
- [x] **Who may edit whom** (decided, then narrowed with the user, 10 November 2026): `UserService::update` (both
      web and flat API funnel through it) refuses a change to one's own role, "You can't change your own role.";
      the owner rules stay as they are (unchanged: only owners manage owners, the primary owner only themselves).
      **"A non-owner can't edit someone whose role holds a permission they don't hold" was tried in
      `UserPolicy::update` as a blanket permission-set comparison (`notStrongerThan()`) and dropped**: it broke
      `tests/Feature/Tenancy/PeopleTest.php`'s existing, deliberately-designed behavior — a narrowly-permissioned
      people manager (`users.*` only) is meant to freely edit a cashier, even though the real seeded `cashier`
      role holds many unrelated operational permissions (`sales.create`, `products.view`, …) the manager doesn't.
      `GrantableRole` (pre-existing, unchanged) already refuses assigning someone a role whose permissions the
      giver doesn't fully hold, which is what actually stops a weak admin from strengthening anyone's role; there
      is no additional protection for editing a stronger person's *other* fields beyond the existing owner rule.
      The stage's own test bullet below ("View only on … another manager's row whose role holds more
      permissions") is accordingly **not built** — see "Points to confirm".
- [x] Tests — `tests/Feature/UsersListActionsTest.php` (7 tests), `tests/Feature/MemberRemovalTest.php` (6 tests),
      `tests/Feature/CleanRemovedMembersTest.php` (4 tests), plus 2 new/changed tests in
      `tests/Feature/TenantIsolation/NoBusinessResolvedTest.php`, and one assertion fixed in
      `tests/Feature/Tenancy/PeopleTest.php` (see below). All passing:
  - an owner sees View and Edit on every member's row, and Delete on members they may delete (not themselves);
  - a manager with `users.view`/`users.update` sees View and Edit on a cashier's row **and on a peer manager's
    row holding more permissions** (the "who may edit whom" narrowing above), View only on the owner's row, and
    no Delete anywhere;
  - a manager can't raise a cashier to the owner's role (`GrantableRole`, pre-existing); nobody — the owner
    included — changes their own role, but the owner saves their own other details unchanged;
  - a person in businesses A and B, deleted from A: B's role and shop stay and they still sign in to B; their
    A-bound token is deleted, an unbound token with A as default moves to B (audited: old/new business, reason
    `member_removed`, actor); their A till PIN and a same-second pending invite are gone; A's audit trail records
    `member_removed`; with no usable business left, unbound tokens are deleted too;
  - the owner can't delete themselves; a manager can't delete anyone; `UserPolicy::delete` (unchanged) still allows
    only an owner, never the last one;
  - `/api/v1`: a token bound to a suspended business answers `403 business_suspended` on every endpoint checked
    (including `sales/{uuid}/receipt`, which needed the middleware-priority fix above to not 404 first) except
    sign-out, listing reachable businesses and switching to one (the exempt-routes conflict above), which still
    work; closing answers `403 no_active_business`; a removed member's token is deleted
    outright (`401`), and one somehow issued afterward is refused the same way (`403 no_active_business`), sign-out
    still working;
  - `members:clean-removed`: a legacy row (inactive, no role, no `removed_at`) is listed separately on a dry run,
    unchanged until `--apply`, which backfills `removed_at` from `updated_at` and settles it (till PIN gone, an
    invite from before the removal revoked, default business moved); a re-invite sent *after* the (backfilled)
    `removed_at` survives; a deactivated member who still holds a role is left alone, with no `removed_at`; a
    second `--apply` changes nothing; `--all` reaches a suspended business's removed member too.

  **Not separately tested here** (pre-existing, unaffected by this step, already covered elsewhere): the
  pending-verification/email-verification window (`EnsureActiveBusiness`, untouched) and a lapsed business staying
  usable (`Business::STATUS_ACTIVE` vs. `Entitlements::isLapsed()`, untouched) — both predate this stage and this
  step changes neither code path nor its existing tests.

  **One pre-existing test's assertion changed, not its behaviour:**
  `PeopleTest > it removes a person from this business only…` asserted the *session key* was set to B after
  removal — true only because `default_business_id` used to stay unmoved (old behaviour). Since this step moves
  `default_business_id` itself to B, `ResolveTenant` has no reason to also write the session key (the fallback
  already lands on B, now the default). The test now asserts `default_business_id` moved and that opening
  `/dashboard` resolves to B (`TenantContext::id()`), which is the fact that actually matters.

### 2. An owner's rights stop at their own business (gap 2) — done, `fix/access-tenancy`
- [x] **Roles never answer for another business:** `App\Models\User` now tracks the Spatie team id its `roles`/
      `permissions` relations were last loaded for (`$rolesLoadedForTeam`); `hasRole()`, `can()` and
      `isBusinessOwner()` call `refreshRolesForCurrentBusiness()` first, which unsets both relations when the
      current business differs, so the next access reloads under the right team. `hasRole()` can no longer delegate
      to `parent::` (that method lives in the `HasRoles` trait, not a base class), so the trait's original is kept
      under the alias `spatieHasRole`. `TenantContext::set()` is unchanged — it still clears the signed-in user's
      relations directly, now a harmless subset of the general guard. Since `hasPermissionViaRole()` and
      `isBusinessOwner()` both call `hasRole()`, and Spatie's own `Gate::before` callback calls
      `hasPermissionTo()` → `hasPermissionViaRole()` for every permission-string `can()` check, this one guard
      covers policies, Blade `@can`, and direct `hasRole`/`can`/`isBusinessOwner` calls alike.
- [x] **Owner checks always name the current business:** every `isBusinessOwner()` call already ran only with a
      business set (unchanged); the search for `hasRole('admin')`, `role(CopyRoleTemplates::OWNER_ROLE)` and
      `isBusinessOwner()` turned up 37 call sites outside `User.php` itself, all either a scoped DB query
      (`User::query()->role(...)`, no caching risk) or `$user->isBusinessOwner()` (now covered by the guard above).
      None bypass `TenantContext`. `GrantableRole` and `RolePolicy` call `getAllPermissions()` directly rather than
      through the three guarded methods — not fixed here, since in every call site that's always the signed-in
      actor (whose relations `TenantContext::set()` already clears), not a reused `User` instance; flagged in case
      a future caller passes a different instance.
- [x] **The flat API's unbound tokens** keep acting in the default business (unchanged, Phase 7). Changing the
      default business through the switcher is now audited: `App\Support\Tenancy\DefaultBusinessAudit::record()`
      (tags `['tenancy', 'default_business_changed']`, `auditable_type` = `User::class`) is called from both
      `BusinessSwitchController::switch()` (the automatic fallback when the stored default can no longer be
      entered) and `::makeDefault()` (the deliberate "make default" action); a no-op switch (already the default)
      audits nothing. The removal and cleanup command's automatic moves call the same helper in step 1.
- [x] **Isolation tests** (`tests/Feature/TenantIsolation/OwnerScopeTest.php`, 6 tests, 27 assertions, all passing):
  - a `User` loaded while A was set, asked again after switching to B, answers with B's roles (and back again) —
    the direct regression test for the caching fix;
  - an owner of A, cashier in B, has only a cashier's rights in B (`users.update`, `users.delete`,
    `settings.update`, `products.view-cost`, `sales.void` all false; their own edit form for another B member is
    `403`);
  - an owner of A gets a `404` for a B resource addressed directly by uuid (a non-member's session choice of B is
    ignored by `ResolveTenant`, so the dashboard still resolves to A and B's customer 404s);
  - a `RunsForBusiness`-style `TenantContext::run()` call sees no signed-in user (`Auth::user()` is null inside
    it) — pinning "no command takes its rights from a user's role";
  - a queued job dispatched while A is set, picked up by the worker once B is the last business set, still sees
    A as its business and the person as A's owner (exercises Laravel's real `Context::dehydrate()`/`hydrate()`
    queue payload mechanism, Phase 1c, via an actual `database`-queue round trip, not a mock);
  - the switcher's `makeDefault` action audits the change (old/new business, reason `switcher`, actor), and the
    person acts as owner with A as default and cashier with B as default.
  - **Not duplicated here:** "an operator impersonating A's owner acts only in A" is already pinned by
    `tests/Feature/Platform/ImpersonationTest.php` ("gives the operator exactly the user's permissions while
    impersonating"), which impersonation is scoped to one business by construction.

### 3. Shops: viewing follows access, and the default shop on the Free Trial (gap 3) — done, `fix/access-tenancy`
- [x] **Viewing follows access** (decided). `ShopPolicy::viewAny` allows any member of the business; `view` allows
      anyone who can reach the shop (`canAccessShop`). The list stays filtered by `ShopAccessScope`, so each person sees
      only their shops. Managing (create, update, delete, activate, staff assignments) keeps its permissions. On the
      shop's page, integrations and the staff list (this codebase has no separate M-Pesa section on `shops/show`; its
      fields live on `shops/edit`, already behind `update`) show only to someone who may update the shop. `shops.view`
      stays as a permission for roles a business wants to show more of, but no longer gates seeing one's own shops.
      The web and the flat API then agree: the API's list stays as it is; `Api\ShopController::show` gained the same
      `view` check (it had none before).
- [x] **The shop delete confirmation** says "Delete this shop? Its sales, stock and payment history are kept; the
      shop can't be restored." (`shops/index.blade.php`'s JS confirm; the flat API's authorization and the 5 pages'
      shop names shipped in hotfix `fix/api-authz`).
- [x] **The default shop:** `businesses.default_shop_id` (nullable integer, indexed, migrated — no DB foreign key:
      see "Schema"), set to the first shop at
      sign-up (`RegisterBusiness`). The owner can change it on the business settings page (new field, `settings.update`,
      audited via `App\Support\Business\DefaultShopAudit`). **While the business is on the Free Trial**, the default
      shop can't be deleted or deactivated until another is chosen (`DeleteShopAction`, `ShopService::deactivate()`,
      `BusinessRuleException`); on other tiers it is unguarded. The return to the Free Trial is stage 21's, unbuilt
      here. Existing businesses: `php artisan businesses:set-default-shop --business=<id>|--all` (dry run; `--apply`)
      sets the oldest active shop (`--all`: every business, whatever its status, via the generic `RunsForBusiness`
      support built in step 1).
- [x] **The owner grants shop access on the invite** (decided, unchanged). The invite form lists the business's shops;
      the person accepting chooses nothing, and receives the invite's shops.
- [x] **On the Free Trial, every member has the default shop** (decided; `Business::onFreeTrial()`, see "Decisions"):
  - the invite form shows the default shop ticked and fixed ("On the Free Trial, everyone works in the main shop");
    the Free Trial allows one shop, so there is nothing else to choose;
  - accepting an invite while the business is on the Free Trial always assigns the default shop, whatever the invite
    holds;
  - every other way a member joins gives it while on the Free Trial (checked 28 October 2026: `User::joinBusiness()`
    is called by `AcceptBusinessInvite`, by `UserService::create` for `POST /api/users`, and by `RegisterBusiness` for
    the founding owner; the one-off `LegacyImport` also creates memberships). A hand-over passes the business to an
    existing co-owner and a role change adds nobody, so neither is a way to join;
  - returning to the Free Trial from a tier's trial gives every member the one remaining shop, the default: moved to
    [stage 21](./21-plans-billing-trial-banner.md), step 3;
  - `LegacyImport` assigns no shops and checks no limit; it runs only before go-live, and
    `businesses:set-default-shop` and `users:grant-default-shop` cover what it imported (second revision);
  - once the business moves to another tier, assignments made on the Free Trial stay until the owner changes them on
    the People or shop staff page.
- [x] **Every other tier, its trial included** (decided, narrowed with the user — see "Points to confirm"): the
      owner sets on the invite which shop(s) the member has access to; at least one is required for every role but
      the owner, **once the business has a real metered tier subscription** (`StoreBusinessInviteRequest`:
      `shop_ids` `required|array|min:1` when `Entitlements::isMetered()` and not the Free Trial, and the role isn't
      the owner's; "Choose the shops this person will work in."). A business with no subscription at all is
      unmetered, not "on a tier," and stays optional, as it always has. When the business has one shop, it is
      pre-ticked on the invite form.
- *Moved (split, 2 November 2026):* `businesses:list-unsubscribed`, `businesses:close-orphaned` and removing deleted
  users' grants to [the Go-live stage](./24-go-live.md); `ChangeBusinessPlan`'s shop count and the Free Trial's one shop to [stage 21](./21-plans-billing-trial-banner.md), step 3.
- [x] **Existing members without a shop:**
  - `php artisan users:list-without-shop --business=<id>|--all` (read-only) lists every non-owner member with no shop;
  - `php artisan users:grant-default-shop --business=<id>|--all` (dry run; `--apply`) gives each member of a business
    on the **Free Trial** its default shop, as the Free Trial rule says, and only lists members of businesses on any
    other tier, for their owner to assign;
  - the People page shows "No shop yet" beside such a member, with a link to their edit form, where shops are
    assigned; the link is shown to someone who may update that member (`UserPolicy::update`, unchanged). The
    scope-independent `User::hasAnyShop()` reads the `shop_user` pivot directly, since `shops()`/`assignedShopIds()`
    are filtered by `ShopAccessScope` to the *viewer's* own shops, which would misreport someone else's row. The
    shop's staff page (`shops.update` and access to the shop) is the other way, unchanged.
  - **UAT counts not re-checked here**: the rollout counts in this section (business 12, businesses 1/2) are from
    28 October 2026 and belong to this stage's "Rollout" section, run at actual rollout, not at build time.
- [x] Tests (`tests/Feature/Tenancy/ShopAccessAndDefaultShopTest.php`, 13 tests, 46 assertions, all passing; plus one
      pre-existing `ShopTest` assertion updated to the new "any member" rule):
  - a cashier lists and opens only their assigned shop, on the web and the flat API; another shop 404s (web) /
    `403`s (flat API, since `Api\ShopController::show` is now authorized); another business's shop 404s;
  - a member with no shop sees "You haven't been given a shop yet." instead of an empty list;
  - a cashier can't edit a shop or see its integrations or staff list; a manager with `shops.update` can;
  - on the Free Trial, an invite accepted gets the default shop whatever `shop_ids` the invite held (the Free
    Trial's one-shop cap means there is nothing else to request in practice, enforced server-side regardless);
  - on a tier, an invite with no shop is refused ("Choose the shops this person will work in."); one with a shop
    gets exactly that shop; an owner invite needs none, on the Free Trial or not;
  - on the Free Trial the sole default shop can't be deleted or deactivated; on a tier it can, and changing the
    default shop is audited (tag `default_shop_id`);
  - a default shop set by `businesses:set-default-shop` with no one signed in is audited with a null `user_id`
    (shown everywhere as "System");
  - the "No shop yet" link shows to a member who may update that person, not to one who may not;
  - `Business::onFreeTrial()` is true on the `free-trial` plan, false on a tier's trial;
  - `users:grant-default-shop` gives a Free Trial business's shopless member the default shop, is idempotent on a
    second `--apply`, and only lists a tier business's shopless member for its owner.

  **Not separately tested here** (not part of this step's new work, or moved elsewhere): "after moving from the
  Free Trial to a tier, the owner removes the default shop from a member" is ordinary shop-staff management
  (`ShopController::updateUsers`), unchanged by this stage; `--all`'s active-vs-every-status split was already
  pinned generically in step 1's `CleanRemovedMembersTest`/`RunsForBusiness`, not repeated per command here.

## Contract

The flat API's shop list is unchanged (it already lists the person's reachable shops); its shop `show` gains the same
`view` check as the web (its writes are authorized since hotfix `fix/api-authz`). A shop refusal is `{message}` with 403,
like the flat API's other policy refusals; reactivating a removed member is 422 "Re-invite them instead.".
`/api/v1` answers 403 `business_suspended` (suspended) or `no_active_business` (otherwise) on every call but sign-out
when the token's business can't be entered. App checks:
[app-release-checklist.md](./app-release-checklist.md#stage-10a).

## Schema

| Change | Data carried across |
|---|---|
| `businesses.default_shop_id` (nullable integer, indexed — **not** a DB foreign key: `shops.business_id` and `shops.manager_id`/`users.default_business_id` already close a cycle back to `businesses`, which a real constraint here would extend to three tables; SQLite's schema builder refuses to rebuild a cyclic set ("Foreign keys form a cycle"), hit while building the test schema. A deleted shop is always soft-deleted, never hard, so the column is never left dangling.) | `businesses:set-default-shop` sets the oldest active shop for existing businesses; the guard on deleting it applies on the Free Trial only |
| `business_user.removed_at` (nullable timestamp; third revision), set by `RemoveFromBusiness`, cleared only by `AcceptBusinessInvite` | Rows from before this stage stay null until `members:clean-removed --apply` sets it, from `updated_at`, on memberships that are `inactive` with no role (listed separately in the dry run first); it then clears what those removals left (till PINs, invites from before `removed_at`, default business, unbound tokens), each change audited |

No other column (`business_closures.reason` is a string, so `orphaned` needs no migration): roles' business is already Spatie's team (`model_has_roles.business_id`).

**Governance registries updated (found by the full suite, not the stage text):** `Business::onFreeTrial()`
(`Subscription::withoutGlobalScopes()`, since it must answer for a business the caller names, not the ambient
`TenantContext`) and `User::hasAnyShop()` (a raw `DB::table('shop_user')` join, restricted to the current business
with `TenantContext::restrictToBusiness()`, not the viewer's own shops) each needed an entry in
`tests/Unit/TenantScopeBypassTest.php` / `tests/Unit/RawQueryScopeTest.php` respectively — both fail any new,
unlisted bypass or raw query on purpose.

## Acceptance criteria

**Shops (step 3)**
- [x] Every member lists and opens the shops they can reach, whatever their role, on the web and the API; managing a
      shop still needs its permissions. Evidence: `ShopAccessAndDefaultShopTest` ("lets a cashier view only their
      own assigned shop…", "refuses a cashier editing a shop…").
- [x] On the Free Trial every member has the default shop without choosing anything; on every other tier, its trial
      included, the owner sets each member's shops on the invite, at least one — **once the business has a real
      metered tier subscription** (narrowed with the user; an unmetered business stays optional — see "Points to
      confirm"). Evidence: `ShopAccessAndDefaultShopTest` ("gives the default shop to an invite…", "refuses a
      non-Free-Trial invite with no shop…", "needs no shop for an owner invite…").
- [ ] **UAT's members without a shop are listed; those of Free Trial businesses get the default shop** — not
      checked here: this reads UAT data (`--business=<id>` against the real database), which is a Rollout-time
      step (see "Rollout"), not something this build pass can verify. The commands themselves
      (`users:list-without-shop`, `users:grant-default-shop`) are built and tested against synthetic data.
- [x] Changing the default shop is audited; on the Free Trial it can't be deleted or deactivated until another is
      chosen. Evidence: `ShopAccessAndDefaultShopTest` ("can't delete or deactivate the sole default shop…", "can
      delete or deactivate the default shop on a tier, and changing the default shop is audited…").
- [x] The Free Trial is known by `Business::onFreeTrial()` (the plan's `is_trial_plan`); a lapse is read-only
      (unchanged by this stage). (The return to the Free Trial is accepted in
      [stage 21](./21-plans-billing-trial-banner.md).) Evidence: `ShopAccessAndDefaultShopTest`
      ("Business::onFreeTrial() is true on the free-trial plan and false on a tier's trial").
- [x] Deleting a shop keeps its history, and the confirmation says so (the shop names on old records and the flat
      API's authorization shipped in hotfix `fix/api-authz`; the confirmation text itself updated this stage).
      Evidence: `resources/views/shops/index.blade.php`'s `confirmDelete()`.
- [x] "No shop yet" shows beside a member without a shop, with a link to their edit form, only to someone who may
      update that member. Evidence: `ShopAccessAndDefaultShopTest` ("shows the \"No shop yet\" link only to
      someone who may update that member").

**Users list and owner scope (steps 1 and 2)**
- [x] Owners and members with the right permissions see View, Edit and Delete on the users list as their policy
      allows; nobody else does. Evidence: `UsersListActionsTest` (8 tests).
- [x] Being owner of one business gives no rights in another, on every surface; a `User` loaded for one business never
      answers with its roles in another; the isolation tests in step 2 pass. Evidence: `OwnerScopeTest` (6 tests).
- [x] Every change of a default business is audited: the switcher, a removal and the cleanup ("System" when no one is
      signed in). Evidence: `OwnerScopeTest` ("audits a switch…"), `MemberRemovalTest` ("removes a member from one
      business only…"), `CleanRemovedMembersTest` ("lists a legacy removal… then backfills and settles it").
- [x] A removal sets `business_user.removed_at`; deactivating doesn't. Evidence: `MemberRemovalTest`,
      `CleanRemovedMembersTest` ("keeps a re-invite sent after the removal, and leaves a deactivated member with a
      role untouched").
- [x] `members:clean-removed` leaves each removed member as a new removal would (till PIN, invites from before
      `removed_at`, default business, unbound tokens), keeps re-invites sent since, ignores deactivated members,
      lists rows from before this stage separately and sets their `removed_at`, audits each change, and changes
      nothing on a second run. Evidence: `CleanRemovedMembersTest` (4 tests).
- [ ] **A person with no usable business who signs in sees "No active business"; a lapsed business stays usable; a
      new owner works in their business before verifying** — not separately tested this stage: this is pre-existing,
      unchanged `EnsureActiveBusiness`/`Entitlements::isLapsed()` behaviour (see step 1's "Not separately tested
      here" note); already covered by tests that predate this stage and were not touched.
- [x] `/api/v1` answers 403 `business_suspended` on every call but sign-out once the token's business is suspended,
      and `no_active_business` once it is closing or the person can't enter it — **and also on listing/switching
      businesses**, narrowed with the user so a stuck token can still recover (see "Points to confirm"). Evidence:
      `NoBusinessResolvedTest` (3 tests).
- [x] A removed member can be brought back only by a re-invite. Evidence: `MemberRemovalTest` ("is brought back
      only by accepting a re-invite…").
- [x] Deleting a member removes only their membership of that business: their roles, shops, tokens, till PIN and
      pending invites there; their other businesses and their login stay; the removal is audited. Evidence:
      `MemberRemovalTest` ("removes a member from one business only…").
- [x] **A non-owner can't edit or change the role of a stronger member, nor give a stronger role**; nobody changes
      their own role; owners are edited only by owners, and the primary owner only by themselves — **the "can't
      edit a stronger member" half is not built**, dropped with the user (see "Points to confirm"): it broke the
      existing, tested design where a narrowly-permissioned people manager may edit a cashier regardless of the
      cashier's operational permissions. "Can't give a stronger role" (`GrantableRole`, pre-existing) and "nobody
      changes their own role" (`UserService::update`, this stage) are both built and tested
      (`UsersListActionsTest`).
- [x] The isolation suite, the flat API contract test and both full suites pass. Evidence: see "Definition of
      done" below — `tests/Feature/TenantIsolation`, `ApiV1ContractTest`, full SQLite and MySQL runs all green.

## Risks

- **Viewing shops by access opens the shops page to every role.** Mitigation: each person sees only their own
  shops; integrations, M-Pesa details and staff lists stay behind `shops.update`.
- **Granting the default shop to existing members of Free Trial businesses** changes what business 12's manager sees.
  Mitigation: it is the decided Free Trial rule; the dry run lists each member first. Members of other tiers are only
  listed, for their owners.
- **An unbound flat API token follows the user's default business**, so switching business on the web changes what
  that person's app can do. Mitigation: every change of the default business is audited (the switcher, a removal,
  the cleanup); the app should re-read permissions after a refusal or
  on resume (an app check, [app-release-checklist.md](./app-release-checklist.md#stage-10a)); `/api/v1` tokens are
  bound to one business and unaffected.
- **`members:clean-removed` revokes invites** a removed person still holds for the business. Mitigation: only
  invites created before `removed_at`; a re-invite sent since survives; the dry run lists each one; deactivated
  members, who keep their role, are left alone.
- **Rows from before this stage get `removed_at` by the no-role rule**, which could catch a deactivated member whose
  role was taken away by hand, and dates the removal from `updated_at`, which may be later than it was. Mitigation:
  the rule runs once; the dry run lists those rows separately for review before `--apply`; a later date only keeps
  fewer invites revoked, never more.
- **Deleting a shop can't be undone.** No history is lost: sales, stock, payments, M-Pesa payment requests and audit
  rows stay, and the pages that failed on a deleted shop name it since hotfix `fix/api-authz`. Mitigation: the refusal
  and the delete confirmation say so.
  Alternative, if deleting proves too final: let a deactivated shop stop counting toward `Limit::Shops`, which is a
  Phase 4 change (its own stage item), not this stage's.
- **`/api/v1` now refuses a suspended or closing business's tokens on every call**, where some endpoints answered 200
  before. Mitigation: the app checklist's `business_suspended` and `no_active_business` checks; sign-out still works.
- **Reloading roles when the business differs** adds a query where a stale model was reused. Mitigation: only when the
  team id differs from the one the relations were loaded with; the common path (one business per request) is
  unchanged.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`; run the migrations (additive).
2. [the Go-live stage](./24-go-live.md)'s UAT rollout runs first (it closes the empty businesses 4 and 7, has an operator confirm 3 and 5 as
   test data, and removes the deleted users' grants), so this stage's commands see only live businesses.
3. `businesses:set-default-shop --all` (every business, whatever its status; dry run, then `--apply`); `users:list-without-shop --all` (read-only: 5
   expected); `users:grant-default-shop --all` (dry run: business 12's manager gets the default shop, businesses 1 and
   2's four accountants listed; then `--apply`).
4. `members:clean-removed --all` (every business, whatever its status; dry run, then `--apply`): the dry run lists memberships from before this stage
   (inactive, no role, no `removed_at`) separately; review them, then `--apply` sets their `removed_at` and, for every
   removed member, deletes their till PIN, revokes pending invites from before the removal, moves a default business
   that points at a business they left and deletes unbound tokens when nothing usable is left.
5. Check, as an owner of one rehearsal business: the users list's buttons; as a member of two businesses: rights in
   each; as a cashier: the shops page lists only their shops.
6. Check the server against the app's recorded requests (the flat API's shop and user calls).
7. The user runs the migrations and the commands on UAT after a fresh snapshot.

## Go-live (production)

Moved to [the Go-live stage](./24-go-live.md) (split, 2 November 2026). This stage's commands (`businesses:set-default-shop --all`,
`users:grant-default-shop --all`, `members:clean-removed --all`) are its step 3 there.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Three points surfaced while building steps 1 to 3 (`fix/access-tenancy`) and were decided with the user on
10 November 2026:
- **The `/api/v1` refusal's "every call but sign-out"** also exempts `businesses.index` and `businesses.switch` —
  literally refusing every other call left a token whose business became inaccessible with no way to discover or
  move to another it still holds, breaking the existing, tested recovery flow in
  `tests/Feature/Tenancy/ApiV1SignInTest.php`. See step 1's `/api/v1` bullet.
- **"A non-owner can't edit someone whose role holds a permission they don't hold"** is **not built** as a
  blanket check on `UserPolicy::update`: it broke the existing, tested design where a narrowly-permissioned
  people manager may edit a cashier regardless of the cashier's own (often larger, operational) permission set.
  `GrantableRole` already covers the part of this that matters — not being able to *grant* a stronger role. See
  step 1's "Who may edit whom" bullet.
- **"Every other tier, its trial included" needs `shop_ids`** only once the business has a real metered tier
  subscription (`Entitlements::isMetered()` and not the Free Trial) — **not** merely "not on the Free Trial" as
  first read. A business with no subscription at all isn't "on a tier," and `tests/Feature/Signup/InviteFlowTest.php`
  already relies on inviting with no shop for such a business; three of its cases that genuinely do subscribe the
  business to `tier-1` now pass `shop_ids` instead. See step 3's invite bullets.

Nothing else is open. The decisions of 28 October 2026 stand, including the first revision's: the code's owner rule kept,
no editing a stronger member, nobody changes their own role; members kept their shops on a return to the Free Trial
(superseded by the third revision's one-shop rule); the default shop matters only on the Free Trial. The second revision's (29 October 2026): on a return to the Free
Trial every member gets the default shop; "usable" is defined; audits with no user are "System"; businesses with no
subscription are given a plan at rollout. The third revision's (29 October 2026): a return to the Free Trial needs
exactly one shop, and it active (Phase 4's read-only rule unchanged); removals are dated by `removed_at`; a lapsed
business is usable. The fourth revision's (29 October 2026): orphaned businesses (business 7) are closed; deleting a
shop keeps its history; a removed member comes back only by a re-invite. The fifth revision's (2 November 2026):
`/api/v1` answers `business_suspended` for a suspended business and `no_active_business` otherwise; the business
pays for every shop after the trial, and the Free Trial is one shop; `close-orphaned` lists ownerless businesses with
shops and removes deleted users' grants (since 2 November 2026 in [the Go-live stage](./24-go-live.md): only truly
empty businesses are closed automatically; those with data are decided by a platform operator).

**Split (decided 2 November 2026).** What stayed and where the rest went:

| Part | Where it is |
|---|---|
| Step 1: the users list's buttons; who may edit whom; removing a member (`removed_at`, `members:clean-removed`, no reactivation); `/api/v1` refusals; signing in with no usable business | **this stage**, step 1 |
| Step 2: an owner's rights stop at their business | **this stage**, step 2 |
| Step 3: viewing shops; the owner grants shops on the invite; the default shop and the Free Trial's members (`set-default-shop`, `grant-default-shop`, `list-without-shop`); "No shop yet"; `Business::onFreeTrial()`; the shop delete confirmation | **this stage**, step 3 |
| The return to the Free Trial (exactly one shop, and it active; every member gets it) | [stage 21](./21-plans-billing-trial-banner.md), step 3 |
| `ChangeBusinessPlan`'s shop count, who pays for extra shops, the Free Trial's one shop | [stage 21](./21-plans-billing-trial-banner.md), step 3 |
| `businesses:list-unsubscribed`, `businesses:close-orphaned`, removing deleted users' grants, businesses with no subscription, how business 7 was made, `LegacyImport`, the Go-live section | [the Go-live stage](./24-go-live.md) |

`Business::onFreeTrial()` stays here, because this stage's step 3 needs it; stage 21 uses it. The app checklist's 10a
section stays with 10a.

## Follow-ups (not this stage)

- None.
