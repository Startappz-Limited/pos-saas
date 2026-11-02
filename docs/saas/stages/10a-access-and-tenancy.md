# Stage: Access and Tenancy

> **Source:** the seventh round of stage 10's review, 28 October 2026 (the users list's buttons, an owner's scope,
> members who couldn't see shops); moved into its own stage in the eighth round, 28 October 2026.
> **Branch:** `fix/access-tenancy`, from `main` · **Depends on:** no stage (ninth round of stage 10, 28 October 2026:
> this stage grants and revokes no permission) · **Can ship** at any time, before or after stage 10 · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-10a) · **Already merged:** stages 3, 4 and 6
> ([05](./05-phase-3-accounts-roles-settings.md), [06](./06-phase-4-billing-entitlements.md),
> [08](./08-phase-6-signup-and-user-invites.md)), whose rules this stage builds on · **Shipped ahead in a hotfix:**
> `fix/api-authz` (`c099c16`, 29 October 2026): the flat API's write authorization and the deleted-shop names (see
> "Hotfix" under Revisions)

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

---

## Goal

Everyone sees the actions they may take, and only those. Being the owner of one business gives nothing in another.
Every member can see the shops they work in, whatever their role; on the Free Trial that is the business's default
shop, and on any other tier the shops the owner chose on the invite.

## Before you start

Nothing must be merged first: this stage grants and revokes no permission (checked 28 October 2026), so it doesn't
need stage 10's step 0. Everything is decided; a split of the stage is proposed for decision (see "Points to
confirm"), and the revisions' decisions are in "Decisions that apply".

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
    ([stage 4](./06-phase-4-billing-entitlements.md), "Decisions that apply"). A business on a tier's trial can switch
    back to the Free Trial (checked 28 October 2026: `BillingController::plan` and `ChangeTrialPlan`); when it does,
    **every member is given the one remaining shop, which is the default** (decided 28 October 2026; "every member",
    second revision; one shop, third revision), so "on the Free Trial every member has the default shop" always
    holds.
  - **The switch back needs exactly one shop, and it active** (decided 29 October 2026, third revision; replaces the
    open proposal of a writable default shop). Moving to the Free Trial is refused:
    - while the business has more than one shop: "Delete shops until one remains before returning to the Free
      Trial. A deleted shop's sales, stock and payment history are kept." Deactivated shops count toward the shop
      limit (checked 29 October 2026: `PlanLimits::count(Limit::Shops)` counts every shop not soft-deleted, whatever
      its status), so deactivating doesn't help and the message says "delete". Deleting a shop loses no history (see
      "Deleting a shop" in "Relevant current state", fourth revision);
    - while its one shop is inactive, or it has none: "Activate a shop before moving to the Free Trial." (second
      revision).

    So a business never lands on the Free Trial over quota and no shop is read-only on return. Phase 4's read-only
    rule is unchanged: a business with more shops than it pays for has every shop read-only ([stage 6](./08-phase-6-signup-and-user-invites.md), §2.6
    "Changing plan during a trial"; checked 29 October 2026: `Entitlements::readOnlyReason()` returns `over_quota` for
    the whole business). The switch makes the remaining shop the default when it isn't already (audited).
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
    - **Businesses with no subscription** (checked 29 October 2026): sign-up always creates one (`RegisterBusiness`
      calls `StartTrial` before creating the first shop); the platform console's `ChangeBusinessPlan` creates one;
      `LegacyImport` creates none. UAT: 1 of 11 businesses has none (business 7, `pending_verification`, created 23
      October 2026; third revision, checked 29 October 2026: it has no primary owner, no member and no shop, so nobody
      can verify it and `StartTrialOnEmailVerified` will never run for it). `ChangeBusinessPlan` on a business with no
      subscription creates a `trialing` one on the chosen plan, `shop_count` 1 whatever the business's shops, ending
      `trial_days` after the change (14 for the Free Trial).
    - **How business 7 was made** (fourth revision, checked 29 October 2026, read-only on UAT): by the stage 3 sign-up,
      the only code that ever created a `pending_verification` business (`RegisterBusiness` from `f8820d9` until
      `4ecebbb`). It has that sign-up's five role copies and one `admin` grant (`model_has_roles`, user 56), but user
      56 no longer exists: deleting a user row cascades its `business_user` row and nulls `primary_owner_id`
      (`nullOnDelete`), while `model_has_roles` has no foreign key and keeps the grant. No app path deletes a single
      user row (only `PurgeBusiness`, which deletes the business too), so the user was deleted by hand. It isn't from
      `LegacyImport`, which inserts its business as `active`. It has no subscription because the stage 3 sign-up made
      none until verification. Business 6 is gone too (its id is unused). **Production** starts from the clean
      baseline and the one-off import; it can hold such rows only if a stage 3 sign-up ran there or a user row is
      deleted by hand, and the imported business itself has no subscription (`LegacyImport` creates none).
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
    - **`LegacyImport`** is the one-off import of pre-SaaS data into a freshly migrated database (CLAUDE.md,
      `saas:import-legacy`; checked 29 October 2026), run before go-live and not after.
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

### 1. The users list shows the actions people may take (gap 1)
- [ ] `resources/views/components/ui-action-buttons.blade.php` asks the model's policy instead of a permission named
      after the route: `@can($viewPermission ?? 'view', $model)`, `@can($editPermission ?? 'update', $model)`,
      `@can($deletePermission ?? 'delete', $model)`. A caller can still pass an ability. The component's docblock says
      so.
- [ ] The users list is the only caller (checked 28 October 2026); a search confirms none is added before this ships.
- [ ] **Delete removes the member from this business only** (decided; [stage 3](./05-phase-3-accounts-roles-settings.md)
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
- [ ] **`/api/v1` refuses a bound token whose business can't be entered** (fourth revision): `EnsureBusinessToken`
      (or a middleware beside it on the `/api/v1` group) refuses, on every call except `POST /api/v1/auth/sign-out`
      (so the app can still sign out), a token bound to a business when `ResolveTenant` set none:
  - **the business is suspended:** 403 `business_suspended` ("This business is suspended. Nobody can work in it until
    it is reactivated."), the flat API's code and message (`EnsureActiveBusiness`; fifth revision);
  - **otherwise** (closing, pending verification, or the person's membership is inactive or gone): 403
    `no_active_business` ("You are not a member of any active business.").

  A removed member's tokens are deleted on removal; one issued between checks is refused too. Both answers use
  `ApiV1::error()`, so the body is `/api/v1`'s `{success: false, message, code}`.

  It already makes the membership inactive, removes their roles and shop assignments here and deletes their
  `/api/v1` tokens bound here. A deactivated member's membership is `inactive` with `removed_at` null. Tables touched:
  `business_user`, `model_has_roles`, `shop_user`,
  `personal_access_tokens`, `business_invites`, `till_pins`, `users`, and the audit database. The User row and the
  person's memberships, roles and shops in other businesses stay. A person left with no business keeps the account
  and can accept new invites (an invite to an existing email adds a membership, stage 3).
- [ ] **Members removed before this stage:** `php artisan members:clean-removed --business=<id>|--all` (dry run;
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
- [ ] **A removed member can't be reactivated by hand** (fourth revision): while `removed_at` is set,
      `UserService::changeStatus` and `UserService::update` refuse any change of the member's status, so the web's
      Activate, Deactivate and Suspend and a user update with `status` (web and flat API) are refused with "Re-invite
      them instead." (422 on the flat API, `{success: false, message}`). The users list doesn't offer Activate on a
      removed member. Only an accepted re-invite brings them back, clearing `removed_at`, with the invite's role and
      shops.
- [ ] **`--all`** (second revision): `businesses:set-default-shop`, `users:grant-default-shop`,
      `users:list-without-shop` and `members:clean-removed` take `--all` instead of `--business`, running for every
      active business in turn (`TenantContext::eachBusiness()`), with one report per business. `businesses:set-default-shop
      --all` is the exception (third revision): it runs for **every** business, whatever its status (active, lapsed,
      pending verification, suspended, closing), so a business reopened or verified later already has its default
      shop; it runs each inside `TenantContext::run()`. `members:clean-removed --all` likewise runs for **every**
      business, whatever its status (fourth revision), so a member removed from a suspended or closing business is
      cleaned too.
- [ ] **Who may edit whom** (decided): `UserPolicy::update` also refuses a non-owner editing someone whose role holds a
      permission the editor doesn't hold; `UpdateUserRequest` and `UserService::update` refuse a change to one's own
      role (web and flat API), "You can't change your own role."; the owner rules stay as they are.
- [ ] Tests (`UiButtonLinkTest` or a new `UsersListActionsTest`):
  - an owner sees View and Edit on every member's row, and Delete on members they may delete (not themselves, not the
    primary owner);
  - a manager with `users.view` and `users.update` sees View and Edit on cashiers' rows, and View only on the owner's
    row and on another manager's row whose role holds more permissions; no Delete (only owners delete);
  - a manager can't raise a cashier to manager or owner; nobody changes their own role, the owner included;
  - a person in businesses A and B, deleted from A, keeps B's role and shops and can still sign in; their A-bound
    tokens stop working, and an unbound token with A as default moves to B; their till PIN and pending invites for A
    are gone; A's audit records the removal;
  - the owner can't delete themselves; nobody but an owner deletes anyone, and the primary owner can't be deleted;
  - a person removed from their only business signs in and sees "No active business" (web) and 403
    `no_active_business` (flat API and `/api/v1`);
  - `members:clean-removed` on a removed member deletes the till PIN, revokes an invite sent before the removal,
    keeps a re-invite sent after it, moves the default business and deletes unbound tokens when nothing usable is
    left, audits each change as "System", leaves a deactivated member untouched, and changes nothing on a second run;
  - a removal sets `removed_at`; deactivating a member doesn't; a re-invite accepted after a removal clears it;
  - `members:clean-removed` uses `removed_at` for rows that have it, and a re-invite sent after `removed_at`
    survives even when the row's `updated_at` changed since; a row from before this stage (inactive, no role, no
    `removed_at`) is listed separately in the dry run, and `--apply` sets its `removed_at` and cleans it; a
    deactivated member with a role gets no `removed_at`;
  - the web's Activate and a user update with `status` (web and flat API) on a removed member are refused with
    "Re-invite them instead." and change nothing; on a deactivated member they work; accepting a re-invite makes the
    removed member active and clears `removed_at`;
  - a founding owner who has just signed up, unverified, reaches their business (it is `active` and usable); after
    24 hours unverified they get `email_verification_required` (web redirect; 403 on both APIs), not
    `no_active_business`; verifying restores access; a `pending_verification` business from the stage 3 sign-up opens
    when its owner verifies;
  - `/api/v1` with a token bound to a business that is then suspended answers 403 `business_suspended` on every
    endpoint (sync included) except sign-out; closing, or one the person was removed from, answers 403
    `no_active_business`; sign-out still works in each case;
  - a lapsed (read-only) business is still usable: its member signs in to it, read-only; a suspended or closing one
    isn't, and the member sees "No active business" when it was their only one;
  - an automatic default-business move (a removal, the cleanup) is audited with its reason;
  - a cashier with no user permissions sees no buttons, and the list itself is refused if they lack `users.view`;
  - each button's link opens (`linkButtonHref`), and Edit saves a member's details.

### 2. An owner's rights stop at their own business (gap 2)
- [ ] **Roles never answer for another business:** a `User` remembers which business its `roles` and `permissions`
      relations were loaded for (the Spatie team id at load time); `hasRole`, `can` and `isBusinessOwner` reload them
      when the current business differs. `TenantContext::set()` keeps clearing the signed-in user's, as today.
- [ ] **Owner checks always name the current business:** every `isBusinessOwner()` call and every `admin` role check
      runs only with a business set (it returns false otherwise, as today); a search for `hasRole('admin')`,
      `role(CopyRoleTemplates::OWNER_ROLE)` and `isBusinessOwner` lists each place in the PR, with the business it
      runs in.
- [ ] **The flat API's unbound tokens** keep acting in the default business, and changing one's default business
      (the business switcher) is audited, so what a token can do is traceable. `/api/v1` tokens are bound to one
      business already (Phase 7).
- [ ] **Isolation tests** (`TenantIsolation/OwnerScopeTest`), with a person who owns business A and is a cashier in B,
      and a person who owns only A:
  - in B, the A-owner-and-B-cashier has a cashier's rights only: can't edit users, change roles, void, see costs,
    change settings or reach the platform console; their own user page in B offers no Edit for others;
  - the A-only owner gets a 404 for every B resource (users, roles, shops, sales, settings), on the web, the flat API
    and `/api/v1`;
  - a `User` loaded while A was set, asked again after switching to B (as a job looping with `eachBusiness()` does),
    answers with B's roles;
  - a queued job dispatched in A, run while B is the worker's last business, acts with A's roles (Phase 1c restores
    the business);
  - a command run for business B acts as the system, with no person's rights; no command takes its rights from a
    user's role (checked 28 October 2026: `RunsForBusiness` only sets the business, and no command or job signs a user
    in); a `RunsForBusiness` command runs with no signed-in user;
  - an unbound flat API token of the person above acts as owner when their default business is A and as cashier when
    it is B; the switch is audited;
  - an operator impersonating A's owner acts only in A.

### 3. Shops: viewing follows access, and the default shop on the Free Trial (gap 3)
- [ ] **Viewing follows access** (decided). `ShopPolicy::viewAny` allows any member of the business; `view` allows
      anyone who can reach the shop (`canAccessShop`). The list stays filtered by `ShopAccessScope`, so each person sees
      only their shops. Managing (create, update, delete, activate, staff assignments) keeps its permissions. On the
      shop's page, integrations, M-Pesa details and the staff list show only to someone who may update the shop.
      `shops.view` stays as a permission for roles a business wants to show more of, but no longer gates seeing one's
      own shops. The web and the flat API then agree (the API's list stays as it is; `show` gains the same `view`
      check).
- [ ] **The shop delete confirmation** says "Its sales, stock and payment history are kept; the shop can't be
      restored." (fourth revision; the flat API's authorization and the 5 pages' shop names shipped in hotfix
      `fix/api-authz`). Other pages that read a shop through a relation still show nothing for a deleted one (they
      guard with `?->` or `??`); naming it there is left to the page that needs it.
- [ ] **The default shop:** `businesses.default_shop_id` (nullable foreign key), set to the first shop at sign-up
      (`RegisterBusiness`). The owner can change it on the business settings page (`settings.update`, audited). **While
      the business is on the Free Trial**, the default shop can't be deleted or deactivated until another is chosen;
      on other tiers it is used for nothing and may be (decided). A return to the Free Trial needs exactly one shop, and
      it active (third revision; see "Decisions"); that shop becomes the default when it isn't already (audited;
      "System" when the switch is made by billing code with no one signed in). `ChangeTrialPlan` and
      `BillingController::plan` refuse otherwise, before changing anything. Existing businesses:
      `php artisan businesses:set-default-shop --business=<id>|--all` (dry run; `--apply`) sets the oldest active shop
      (`--all`: every business, whatever its status).
- [ ] **The owner grants shop access on the invite** (decided). The invite form lists the business's shops; the person
      accepting chooses nothing, and receives the invite's shops (as today).
- [ ] **On the Free Trial, every member has the default shop** (decided; `Business::onFreeTrial()`, see "Decisions"):
  - the invite form shows the default shop ticked and fixed ("On the Free Trial, everyone works in the main shop");
    the Free Trial allows one shop, so there is nothing else to choose;
  - accepting an invite while the business is on the Free Trial always assigns the default shop, whatever the invite
    holds;
  - every other way a member joins gives it while on the Free Trial (checked 28 October 2026: `User::joinBusiness()`
    is called by `AcceptBusinessInvite`, by `UserService::create` for `POST /api/users`, and by `RegisterBusiness` for
    the founding owner; the one-off `LegacyImport` also creates memberships). A hand-over passes the business to an
    existing co-owner and a role change adds nobody, so neither is a way to join;
  - returning to the Free Trial from a tier's trial gives every member the one remaining shop, the default (decided;
    second and third revisions);
  - `LegacyImport` assigns no shops and checks no limit; it runs only before go-live, and
    `businesses:set-default-shop` and `users:grant-default-shop` cover what it imported (second revision);
  - once the business moves to another tier, assignments made on the Free Trial stay until the owner changes them on
    the People or shop staff page.
- [ ] **Every other tier, its trial included** (decided): the owner sets on the invite which shop(s) the member has
      access to; at least one is required for every role but the owner (the form refuses an empty choice: "Choose
      the shops this person will work in."). When the business has one shop, it is pre-ticked on the invite form.
- [ ] **Businesses with no subscription** (second revision): `php artisan businesses:list-unsubscribed` (read-only)
      lists them; a platform operator gives each a plan with the console's "Change plan" (`ChangeBusinessPlan`) before
      this stage's commands run, so every business is metered and its Free Trial is known. The operator chooses the
      plan for the business's shops: the Free Trial (`free-trial`) for one shop or none, otherwise a tier.
      `ChangeBusinessPlan` gives a new subscription the business's current shop count (the shops `Limit::Shops`
      counts, at least 1) instead of always 1, so an imported business with several shops isn't over quota from the
      start (fourth revision). Either way it **starts a trial** (`trialing`, ending the plan's `trial_days` after the
      change). After go-live, only `LegacyImport` could make one, and it isn't run again.
  - **Who pays for the extra shops** (fifth revision, checked 2 November 2026): the business. It pays
    `price_at_signup × shop_count` a month (`Subscription::monthlyTotal()`) from its first payment after the trial,
    and nothing during the trial; no shop is free. The owner can lower the count on the billing page once shops are
    deleted (`BillingController::shops`), as today.
  - **The Free Trial is always one shop:** `ChangeBusinessPlan` refuses the Free Trial for a business with more than
    one shop ("This business has :count shops; the Free Trial allows one. Choose a tier.") and, on the Free Trial,
    sets `shop_count` to 1, as `StartTrial` and `ChangeTrialPlan` do. Moving a business that already has a
    subscription to a tier keeps its `shop_count`, as today.
  - *Proposed to move* (fifth revision; not moved): this item belongs to the platform console's billing, so to
    [stage 21](./21-plans-billing-trial-banner.md), step 3 ("Editable plans in the console"); see "Points to
    confirm".
- [ ] **Orphaned businesses are closed, not given a plan** (fourth revision): `php artisan businesses:close-orphaned`
      (dry run; `--apply`) lists every business with no active member and no shop, whatever its status, and closes
      each with `CloseBusiness::start()` (no requester, a new reason `orphaned` beside `owner` and `lapsed`), audited
      as "System". `EraseClosedBusinesses` erases it when the grace period ends, as for any closure. UAT (checked 2
      November 2026): businesses **4** (no member, no shop, a `trialing` subscription, so `list-unsubscribed` misses
      it) and **7** (no member, no shop, `pending_verification`, no subscription). `businesses:list-unsubscribed`
      marks such businesses "orphaned: close" rather than "needs a plan". The command also (fifth revision):
  - **lists, and never closes, businesses with shops but no active owner** (no active member holding `admin` in
    them): their history may matter, so a platform operator decides (hand them to someone through support, or close
    them by hand). UAT: businesses **3** ("Pos Biz", 1 shop, 1 sale, no member) and **5** ("Hub", 1 shop, no sale,
    no member), both `trialing`;
  - **removes role grants of users that no longer exist** (`model_has_roles` and `model_has_permissions` rows of
    `model_type` User whose `model_id` has no `users` row; neither table has a foreign key to `users`), in every
    business, audited as "System" with the role and business. UAT: 5 grants, users 51, 52, 53 and 54 (businesses
    3, 4 and 5) and 56 (business 7); no direct permission grants. All five users were deleted by hand, taking their
    memberships with them (`business_user` cascades).
- [ ] **Existing members without a shop:**
  - `php artisan users:list-without-shop --business=<id>` (read-only) lists every non-owner member with no shop;
  - `php artisan users:grant-default-shop --business=<id>` (dry run; `--apply`) gives each member of a business on the
    **Free Trial** its default shop, as the Free Trial rule says, and only lists members of businesses on any other
    tier, for their owner to assign; UAT: business 12's manager (user 63) gets the default shop; the four accountants
    of businesses 1 and 2 (a tier's trial, five shops each) are listed for their owners;
  - the People page shows "No shop yet" beside such a member, with a link to their edit form, where shops are
    assigned; the link is shown to someone who may update that member (`UserPolicy::update`: `users.update`, and
    allowed to manage them). The shop's staff page (`shops.update` and access to the shop) is the other way (checked
    28 October 2026: `ShopController::updateUsers`).
- [ ] Tests:
  - a cashier, an inventory clerk and an accountant each list and open only their assigned shops, on the web and the
    flat API; another shop of the business is a 404 to them; another business's shop is a 404;
  - a member with no shop sees "You haven't been given a shop yet. Ask the owner." instead of an empty list;
  - a cashier can't edit a shop or see its integrations and M-Pesa details; a manager with `shops.update` can;
  - on the Free Trial, an invite with no shop gives the accepting person the default shop; the person picks nothing;
    the form can't untick it;
  - on a tier's trial and on a paid tier, an invite with no shop is refused; the member gets exactly the shops the
    owner chose; a one-shop business ticks its shop;
  - after moving from the Free Trial to a tier, the owner removes the default shop from a member, and the member no
    longer sees it;
  - on the Free Trial the default shop can't be deleted or deactivated until another is chosen; on a tier it can;
    changing it is audited; a business returning to the Free Trial with its default deleted and one active shop left
    gets that shop as default, and every member has it;
  - moving to the Free Trial with no active shop is refused, and nothing changes;
  - moving to the Free Trial with two shops is refused with "Delete shops until one remains…", and nothing changes;
    deactivating one of them doesn't lift the refusal (a deactivated shop counts); deleting it does, and the switch
    then succeeds with the remaining shop as the default and the business not read-only; the deleted shop's sales,
    stock movements and M-Pesa payment requests are still there;
  - `businesses:close-orphaned` closes a business with no active member and no shop (reason `orphaned`, audited as
    "System"), leaves one with a member or a shop alone, and changes nothing on a second run; it lists a business
    with a shop but no active owner and doesn't close it; it removes a role grant of a deleted user and keeps every
    other grant;
  - `ChangeBusinessPlan` on a business with no subscription and three shops gives it a subscription for three shops;
    it refuses the Free Trial for that business, and gives a one-shop business the Free Trial with `shop_count` 1;
  - a default shop set by billing code with no one signed in is audited as "System";
  - the "No shop yet" link shows to a member who may update that person and opens their edit form; it isn't shown
    to someone who may not;
  - `businesses:list-unsubscribed` lists a business with no subscription and changes nothing;
  - `users:grant-default-shop --all` and `users:list-without-shop --all` run for every active business;
    `businesses:set-default-shop --all` and `members:clean-removed --all` also cover a suspended, a closing and a
    pending-verification business;
  - `Business::onFreeTrial()` is true on the `free-trial` plan and false on a tier's trial;
  - `users:grant-default-shop` gives Free Trial businesses' members their default shop, changes nothing on a second
    `--apply`, and only lists members of businesses on other tiers.

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
| `businesses.default_shop_id` (nullable foreign key) | `businesses:set-default-shop` sets the oldest active shop for existing businesses; the guard on deleting it applies on the Free Trial only |
| `business_user.removed_at` (nullable timestamp; third revision), set by `RemoveFromBusiness`, cleared only by `AcceptBusinessInvite` | Rows from before this stage stay null until `members:clean-removed --apply` sets it, from `updated_at`, on memberships that are `inactive` with no role (listed separately in the dry run first); it then clears what those removals left (till PINs, invites from before `removed_at`, default business, unbound tokens), each change audited |

No other column (`business_closures.reason` is a string, so `orphaned` needs no migration): roles' business is already Spatie's team (`model_has_roles.business_id`).

## Acceptance criteria

**Shops (step 3)**
- [ ] Every member lists and opens the shops they can reach, whatever their role, on the web and the API; managing a
      shop still needs its permissions.
- [ ] On the Free Trial every member has the default shop without choosing anything; on every other tier, its trial
      included, the owner sets each member's shops on the invite, at least one.
- [ ] UAT's members without a shop are listed; those of Free Trial businesses get the default shop.
- [ ] Changing the default shop is audited; on the Free Trial it can't be deleted or deactivated until another is
      chosen.
- [ ] The Free Trial is known by `Business::onFreeTrial()` (the plan's `is_trial_plan`); a lapse is read-only; a
      return to the Free Trial gives every member the one remaining shop, the default; it is refused unless the
      business has exactly one shop, and it active, so it never lands over quota.
- [ ] Deleting a shop keeps its history, and the confirmation says so (the shop names on old records and the flat
      API's authorization shipped in hotfix `fix/api-authz`).
- [ ] Every business has a subscription before this stage's commands run, except orphaned ones, which are closed.
- [ ] "No shop yet" shows beside a member without a shop, with a link to their edit form, only to someone who may
      update that member.

**Users list and owner scope (steps 1 and 2)**
- [ ] Owners and members with the right permissions see View, Edit and Delete on the users list as their policy
      allows; nobody else does.
- [ ] Being owner of one business gives no rights in another, on every surface; a `User` loaded for one business never
      answers with its roles in another; the isolation tests in step 2 pass.
- [ ] Every change of a default business is audited: the switcher, a removal and the cleanup ("System" when no one is
      signed in).
- [ ] A removal sets `business_user.removed_at`; deactivating doesn't.
- [ ] `members:clean-removed` leaves each removed member as a new removal would (till PIN, invites from before
      `removed_at`, default business, unbound tokens), keeps re-invites sent since, ignores deactivated members,
      lists rows from before this stage separately and sets their `removed_at`, audits each change, and changes
      nothing on a second run.
- [ ] A person with no usable business who signs in sees "No active business"; a lapsed business stays usable; a
      new owner works in their business before verifying.
- [ ] `/api/v1` answers 403 `business_suspended` on every call but sign-out once the token's business is suspended,
      and `no_active_business` once it is closing or the person can't enter it.
- [ ] `businesses:close-orphaned` closes businesses with no active member and no shop, lists those with shops but no
      active owner, and removes role grants of deleted users.
- [ ] A removed member can be brought back only by a re-invite.
- [ ] Deleting a member removes only their membership of that business: their roles, shops, tokens, till PIN and
      pending invites there; their other businesses and their login stay; the removal is audited.
- [ ] A non-owner can't edit or change the role of a stronger member, nor give a stronger role; nobody changes their
      own role; owners are edited only by owners, and the primary owner only by themselves.
- [ ] The isolation suite, the flat API contract test and both full suites pass.

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
- **Businesses with no subscription aren't metered** (no shop limit, no Free Trial). Accepted only until the rollout
  gives each a plan or closes it (UAT: business 7, orphaned, closed); at go-live the imported business is checked the
  same way (see "Go-live"); afterwards only `LegacyImport` could make one, and it isn't run again.
- **Closing an orphaned business erases it** after the grace period. Mitigation: only a business with no active
  member and no shop; the dry run lists each; the closure's export is built first, as for any closure.
- **Deleting a shop can't be undone.** No history is lost: sales, stock, payments, M-Pesa payment requests and audit
  rows stay, and the pages that failed on a deleted shop name it since hotfix `fix/api-authz`. Mitigation: the refusal
  and the delete confirmation say so.
  Alternative, if deleting proves too final: let a deactivated shop stop counting toward `Limit::Shops`, which is a
  Phase 4 change (its own stage item), not this stage's.
- **`/api/v1` now refuses a suspended or closing business's tokens on every call**, where some endpoints answered 200
  before. Mitigation: the app checklist's `business_suspended` and `no_active_business` checks; sign-out still works.
- **Removing grants of deleted users** touches every business. Mitigation: only rows whose user has no `users` row,
  which nothing can read (role checks start from a user); the dry run lists each; audited.
- **Businesses with shops but no owner** (UAT 3 and 5) stay as they are: nobody can sign in to them, and their
  trials lapse to read-only and then to closure by the sweep (`SweepSubscriptions`). Mitigation: the command lists
  them for a decision first; the sweep's closure builds the export, as for any closure.
- **A business on a tier's trial with several shops can't return to the Free Trial** until it deletes all but one
  (deactivating isn't enough, since deactivated shops count). Mitigation: the refusal says so and that history is
  kept, and nothing changes; the business can instead stay on its tier and pay. Phase 4's read-only rule is
  unchanged.
- **Reloading roles when the business differs** adds a query where a stale model was reused. Mitigation: only when the
  team id differs from the one the relations were loaded with; the common path (one business per request) is
  unchanged.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`; run the migrations (additive).
2. `businesses:list-unsubscribed` (read-only: business 7 expected, marked orphaned); `businesses:close-orphaned`
   (dry run: closes 4 and 7, lists 3 and 5 as having shops but no owner, removes 5 grants of deleted users; then
   `--apply`), which starts the closures; a platform operator decides about 3 and 5, and gives any other listed
   business a plan in the console ("Change plan"); check that `list-unsubscribed` then lists none.
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

*Proposed to move* (fifth revision; not moved): no stage owns the import and go-live (checked 2 November 2026: none of
`00`–`23` does; the plan's go-live is around 12 November 2026, [pos-saas-plan.md](../pos-saas-plan.md)). Proposed: a
new stage, "Go-live", holding this section, the M-Pesa collection switch and the import; see "Points to confirm".

After the one-off `LegacyImport` into the freshly migrated production database, and before anyone signs in:
1. `businesses:list-unsubscribed` against production (read-only). The imported business is expected (it has no
   subscription); any business marked orphaned is closed with `businesses:close-orphaned` (dry run, then `--apply`).
2. A platform operator gives the imported business a plan for its shops in the console ("Change plan"), and the
   command is run again until it lists none.
3. `businesses:set-default-shop --all`, `users:grant-default-shop --all` and `members:clean-removed --all`, each as a
   dry run, then `--apply`.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

Nothing is open. The decisions of 28 October 2026 stand, including the first revision's: the code's owner rule kept,
no editing a stronger member, nobody changes their own role; members kept their shops on a return to the Free Trial
(superseded by the third revision's one-shop rule); the default shop matters only on the Free Trial. The second revision's (29 October 2026): on a return to the Free
Trial every member gets the default shop; "usable" is defined; audits with no user are "System"; businesses with no
subscription are given a plan at rollout. The third revision's (29 October 2026): a return to the Free Trial needs
exactly one shop, and it active (Phase 4's read-only rule unchanged); removals are dated by `removed_at`; a lapsed
business is usable. The fourth revision's (29 October 2026): orphaned businesses (business 7) are closed; deleting a
shop keeps its history; a removed member comes back only by a re-invite. The fifth revision's (2 November 2026):
`/api/v1` answers `business_suspended` for a suspended business and `no_active_business` otherwise; the business
pays for every shop after the trial, and the Free Trial is one shop; `close-orphaned` lists ownerless businesses with
shops and removes deleted users' grants.

**Proposed for decision (fifth revision): split this stage.** It has grown from three gaps to about twenty work items
across four subjects. Nothing is moved until you decide.

| Part | Stays in 10a, or moves | Why |
|---|---|---|
| Step 1: the users list's buttons; who may edit whom | **stays** | access |
| Step 1: removing a member (`RemoveFromBusiness`, `removed_at`, `members:clean-removed`, no reactivation, the default business moves) | **stays** | access and membership |
| Step 1: `/api/v1` refusals (`business_suspended`, `no_active_business`) and what a person with no usable business sees | **stays** | access |
| Step 2: an owner's rights stop at their business | **stays** | tenancy |
| Step 3: viewing shops follows access; the owner grants shops on the invite; "No shop yet" | **stays** | access |
| Step 3: the default shop, every member has it on the Free Trial, `set-default-shop`, `grant-default-shop`, `list-without-shop` | **stays** | it is how members see shops on the Free Trial |
| The return to the Free Trial (one shop, and it active) and `Business::onFreeTrial()` | **moves to stage 21** (plans and billing), which then depends on 10a's default shop | billing rule |
| `ChangeBusinessPlan`'s shop count; the Free Trial refused for several shops | **moves to stage 21**, step 3 (the console's billing) | billing |
| `businesses:list-unsubscribed`, `businesses:close-orphaned`, deleted users' grants, the "Go-live" section | **moves to a new stage, "Go-live"** (with the import and the M-Pesa collection switch) | data hygiene before go-live |
| The shop delete confirmation text | **stays** (one line) | small |

With the split, 10a keeps steps 1 to 3 without billing, and the "Go-live" stage runs once, before 12 November 2026.
The app checklist's 10a section stays with 10a; none of its checks belongs to a part that moves.

## Follow-ups (not this stage)

- None.
