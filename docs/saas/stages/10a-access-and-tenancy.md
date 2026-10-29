# Stage: Access and Tenancy

> **Source:** the seventh round of stage 10's review, 28 October 2026 (the users list's buttons, an owner's scope,
> members who couldn't see shops); moved into its own stage in the eighth round, 28 October 2026.
> **Branch:** `fix/access-tenancy`, from `main` · **Depends on:** no stage (ninth round of stage 10, 28 October 2026:
> this stage grants and revokes no permission) · **Can ship** at any time, before or after stage 10 · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-10a) · **Already merged:** stages 3, 4 and 6
> ([05](./05-phase-3-accounts-roles-settings.md), [06](./06-phase-4-billing-entitlements.md),
> [08](./08-phase-6-signup-and-user-invites.md)), whose rules this stage builds on

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
  never a return to the Free Trial; a return from a tier's trial keeps members' shops (decided).
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
  open proposal.
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

---

## Goal

Everyone sees the actions they may take, and only those. Being the owner of one business gives nothing in another.
Every member can see the shops they work in, whatever their role; on the Free Trial that is the business's default
shop, and on any other tier the shops the owner chose on the invite.

## Before you start

Nothing must be merged first: this stage grants and revokes no permission (checked 28 October 2026), so it doesn't
need stage 10's step 0. One proposal is open (a writable default shop while over quota; see "Points to confirm");
everything else is decided, and the revisions' decisions are in "Decisions that apply".

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
    **every member keeps their shops and is given the default shop too** (decided 28 October 2026; "every member",
    second revision, 29 October 2026), so "on the Free Trial every member has the default shop" always holds. While
    the business has more shops than the Free Trial's one, **every shop is read-only**, not only the extra ones
    ([stage 6](./08-phase-6-signup-and-user-invites.md), §2.6 "Changing plan during a trial"; checked 29 October 2026:
    `Entitlements::readOnlyReason()` returns `over_quota` for the whole business, and no shop is kept writable), until
    shops are deleted down to one. The default shop is therefore not writable on its own; see "Points to confirm".
  - **The switch back needs an active shop** (second revision, 29 October 2026): moving to the Free Trial is refused
    while the business has no active shop ("Activate a shop before moving to the Free Trial."). When the default shop
    is gone or inactive, the oldest active shop becomes the default as part of the switch, audited.
  - **The default shop matters only on the Free Trial** (decided 28 October 2026): on other tiers it is kept but
    used for nothing, and may be deleted or deactivated.
- **Members and owners (first revision, 28 October 2026):** removal, owner protection ("only owners delete") and
  hand-over are decided in [stage 3](./05-phase-3-accounts-roles-settings.md) (People; 4.9). **The primary owner** is
  the business's founding owner, `businesses.primary_owner_id` (checked 28 October 2026: set by `RegisterBusiness`,
  moved by `HandOverBusiness`); a business can have other owners (co-owners), who hold `admin` too.
- **A usable business** (second revision, 29 October 2026) is one where the person has an active membership and the
  business itself is active (checked 29 October 2026: `User::usableBusinesses()`, which `User::businessToOpen()` and
  `EnsureActiveBusiness` use). This stage uses the word only in that sense.
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
      October 2026).
    - **`LegacyImport`** is the one-off import of pre-SaaS data into a freshly migrated database (CLAUDE.md,
      `saas:import-legacy`; checked 29 October 2026), run before go-live and not after.
    - **Deactivated and removed members look alike** (checked 29 October 2026): deactivating sets the membership to
      `inactive` (`UserService::changeStatus`, `UserStatus::INACTIVE`), as removing does; only a removed member has no
      role left in the business (`RemoveFromBusiness` calls `syncRoles([])`). `business_user` has no removal date; its
      `updated_at` is set when the status changes.
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

  It already makes the membership inactive, removes their roles and shop assignments here and deletes their
  `/api/v1` tokens bound here. Tables touched: `business_user`, `model_has_roles`, `shop_user`,
  `personal_access_tokens`, `business_invites`, `till_pins`, `users`, and the audit database. The User row and the
  person's memberships, roles and shops in other businesses stay. A person left with no business keeps the account
  and can accept new invites (an invite to an existing email adds a membership, stage 3).
- [ ] **Members removed before this stage:** `php artisan members:clean-removed --business=<id>|--all` (dry run;
      `--apply`) brings every removed membership to the state a new removal leaves. A membership counts as removed
      when it is `inactive` and the person holds no role in the business; a deactivated member keeps their role and
      is left alone. For each, it:
  - deletes the till PIN for the business;
  - revokes pending invites to the person for the business **created before the membership became inactive** (its
    `updated_at`), so a re-invite sent since survives;
  - moves a `default_business_id` that points at the business to their earliest-joined usable business, or null;
  - deletes their unbound flat API tokens when no usable business is left;
  - audits each change, with the actor "System".

  A second `--apply` changes nothing.
- [ ] **`--all`** (second revision): `businesses:set-default-shop`, `users:grant-default-shop`,
      `users:list-without-shop` and `members:clean-removed` take `--all` instead of `--business`, running for every
      active business in turn (`TenantContext::eachBusiness()`), with one report per business.
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
- [ ] **The default shop:** `businesses.default_shop_id` (nullable foreign key), set to the first shop at sign-up
      (`RegisterBusiness`). The owner can change it on the business settings page (`settings.update`, audited). **While
      the business is on the Free Trial**, the default shop can't be deleted or deactivated until another is chosen;
      on other tiers it is used for nothing and may be (decided). When a business returns to the Free Trial and its
      default shop is gone or inactive, its oldest active shop becomes the default (audited; "System" when the switch
      is made by billing code with no one signed in). The switch is refused while the business has no active shop. Existing businesses:
      `php artisan businesses:set-default-shop --business=<id>` (dry run; `--apply`) sets the oldest active shop.
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
  - returning to the Free Trial from a tier's trial keeps every member's shops and gives the default shop to every
    member (decided; second revision);
  - `LegacyImport` assigns no shops and checks no limit; it runs only before go-live, and
    `businesses:set-default-shop` and `users:grant-default-shop` cover what it imported (second revision);
  - once the business moves to another tier, assignments made on the Free Trial stay until the owner changes them on
    the People or shop staff page.
- [ ] **Every other tier, its trial included** (decided): the owner sets on the invite which shop(s) the member has
      access to; at least one is required for every role but the owner (the form refuses an empty choice: "Choose
      the shops this person will work in."). When the business has one shop, it is pre-ticked on the invite form.
- [ ] **Businesses with no subscription** (second revision): `php artisan businesses:list-unsubscribed` (read-only)
      lists them; a platform operator gives each a plan with the console's "Change plan" (`ChangeBusinessPlan`) before
      this stage's commands run, so every business is metered and its Free Trial is known. After that, only
      `LegacyImport` could make one, and it isn't run after go-live.
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
    changing it is audited; a business returning to the Free Trial with its default deleted gets its oldest active
    shop as default, and every member keeps their shops and has the default shop;
  - moving to the Free Trial with no active shop is refused, and nothing changes;
  - a default shop set by billing code with no one signed in is audited as "System";
  - the "No shop yet" link shows to a member who may update that person and opens their edit form; it isn't shown
    to someone who may not;
  - `businesses:list-unsubscribed` lists a business with no subscription and changes nothing;
  - each command's `--all` runs for every active business;
  - `Business::onFreeTrial()` is true on the `free-trial` plan and false on a tier's trial;
  - `users:grant-default-shop` gives Free Trial businesses' members their default shop, changes nothing on a second
    `--apply`, and only lists members of businesses on other tiers.

## Contract

The flat API's shop list is unchanged (it already lists the person's reachable shops); its shop `show` gains the same
`view` check as the web. A user or shop refusal stays `{success: false, message}` with 403. App checks:
[app-release-checklist.md](./app-release-checklist.md#stage-10a).

## Schema

| Change | Data carried across |
|---|---|
| `businesses.default_shop_id` (nullable foreign key) | `businesses:set-default-shop` sets the oldest active shop for existing businesses; the guard on deleting it applies on the Free Trial only |
| None for removing members: `RemoveFromBusiness` writes existing tables | `members:clean-removed` clears what earlier removals left (till PINs, invites from before the removal, default business, unbound tokens), each change audited |

No other column: roles' business is already Spatie's team (`model_has_roles.business_id`).

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
      return to the Free Trial keeps members' shops and gives the default shop to every member; it is refused while no
      shop is active.
- [ ] Every business has a subscription before this stage's commands run.
- [ ] "No shop yet" shows beside a member without a shop, with a link to their edit form, only to someone who may
      update that member.

**Users list and owner scope (steps 1 and 2)**
- [ ] Owners and members with the right permissions see View, Edit and Delete on the users list as their policy
      allows; nobody else does.
- [ ] Being owner of one business gives no rights in another, on every surface; a `User` loaded for one business never
      answers with its roles in another; the isolation tests in step 2 pass.
- [ ] Every change of a default business is audited: the switcher, a removal and the cleanup ("System" when no one is
      signed in).
- [ ] `members:clean-removed` leaves each removed member as a new removal would (till PIN, earlier invites, default
      business, unbound tokens), keeps re-invites sent since, ignores deactivated members, audits each change, and
      changes nothing on a second run.
- [ ] A person with no usable business who signs in sees "No active business".
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
  invites created before the membership became inactive; a re-invite sent since survives; the dry run lists each
  one; deactivated members, who keep their role, are left alone.
- **Businesses with no subscription aren't metered** (no shop limit, no Free Trial). Accepted only until the rollout
  gives each a plan (1 on UAT); afterwards only `LegacyImport` could make one, and it runs only before go-live.
- **Over quota on a return to the Free Trial, every shop is read-only**, the default shop included, until extra shops
  are deleted (stage 6's rule). The plan picker warns before confirming (stage 6); keeping the default shop writable
  is an open proposal.
- **Reloading roles when the business differs** adds a query where a stale model was reused. Mitigation: only when the
  team id differs from the one the relations were loaded with; the common path (one business per request) is
  unchanged.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`; run the migrations (additive).
2. `businesses:list-unsubscribed` (read-only: business 7 expected); a platform operator gives each listed business a
   plan in the console.
3. `businesses:set-default-shop --all` (dry run, then `--apply`); `users:list-without-shop --all` (read-only: 5
   expected); `users:grant-default-shop --all` (dry run: business 12's manager gets the default shop, businesses 1 and
   2's four accountants listed; then `--apply`).
4. `members:clean-removed --all` (dry run, then `--apply`): for members removed before this stage, deletes
   their till PIN, revokes pending invites to them and moves a default business that points at a business they left.
5. Check, as an owner of one rehearsal business: the users list's buttons; as a member of two businesses: rights in
   each; as a cashier: the shops page lists only their shops.
6. Check the server against the app's recorded requests (the flat API's shop and user calls).
7. The user runs the migrations and the commands on UAT after a fresh snapshot.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

One proposal is open (below); everything else is decided. The decisions of 28 October 2026 stand, including the first revision's: the code's owner rule
kept, no editing a stronger member, nobody changes their own role; members keep their shops on a return to the Free
Trial; the default shop matters only on the Free Trial. The second revision's (29 October 2026): on a return to the
Free Trial every member gets the default shop; the switch needs an active shop; "usable" is defined; audits with no
user are "System"; businesses with no subscription are given a plan at rollout.

**Open (proposal, second revision):** stage 6's rule makes every shop read-only while a business on the Free Trial has
more than one shop (§2.6; `Entitlements::readOnlyReason()`). Proposed: keep the default shop writable in that state, so
the business can keep selling while it deletes the extra shops. That changes Phase 4's read-only middleware, so it
needs a decision and its own stage item; until then the rule stands as stage 6 states it.

## Follow-ups (not this stage)

- None.
