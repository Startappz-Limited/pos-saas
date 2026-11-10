## Stage 10a: access and tenancy

Stage file: [docs/saas/stages/10a-access-and-tenancy.md](../saas/stages/10a-access-and-tenancy.md).
Every acceptance criterion there is ticked with its evidence, except one that reads live UAT data (see "Before
merge").

Before this stage, apart from the flat API write-authorization and deleted-shop-naming carve-outs that shipped
ahead in hotfix `fix/api-authz`:
- the users list's action buttons checked permissions that don't exist for `users` (`users.show`/`edit`/`destroy`),
  so only a platform operator ever saw View, Edit or Delete;
- removing a member only deactivated their membership and cleared their roles/shops/tokens — their till PIN,
  pending invites and default business were untouched, and the removal itself left no audit trail;
- a `User` instance that outlived a business switch (a job's `eachBusiness()` loop, an operator script) kept
  answering with the business it was first loaded under;
- `shops.view` gated seeing one's own shop, so cashiers, clerks and accountants were refused the shops page even
  for shops they were assigned to;
- there was no default shop, so nothing could implement the Free Trial's "everyone works in the main shop" rule;
- a token bound to a business that became inaccessible (suspended, closing, or the person removed) reached
  endpoints `ResolveTenant` had already emptied, rather than being refused outright.

This PR fixes each of these, step by step as the stage file plans it (order agreed with the user: step 2, then
step 1, then step 3, since step 2's fix underpins auditing the automatic moves step 1 needed).

### Changes, by step

**Step 2: an owner's rights stop at their own business.**
- `App\Models\User` tracks the Spatie team id its `roles`/`permissions` relations were last loaded for, and
  reloads them in `hasRole()`, `can()` and `isBusinessOwner()` when the current business differs.
  `TenantContext::set()` still clears only the signed-in user's cache, as before — this closes the gap for every
  *other* `User` instance. Since Spatie's own `Gate::before` callback and `hasPermissionViaRole()` both route
  through `hasRole()`, one guard covers policies, Blade `@can` and direct role/permission checks alike.
- New `App\Support\Tenancy\DefaultBusinessAudit::record()` audits every change of a person's default business;
  wired into `BusinessSwitchController::switch()` (the automatic fallback) and `::makeDefault()` here, and into
  the removal and cleanup command in step 1.
- `OwnerScopeTest`: the caching fix itself, an owner-in-A/cashier-in-B scenario, an owner-of-A-only scenario, a
  queued job's business surviving past the worker's next business (a real `database`-queue round trip), a
  command acting with no signed-in user, and the audited switch.

**Step 1: the users list shows actions by policy, and removing a member cleans up fully.**
- `ui-action-buttons.blade.php` now asks the model's own policy ability (`view`/`update`/`delete`) instead of a
  permission named after the route.
- `RemoveFromBusiness` sets `business_user.removed_at`, deletes the till PIN, revokes pending invites (`<=` the
  removal time), moves a default business pointing here (via `DefaultBusinessAudit`, reason `member_removed`),
  deletes unbound tokens once nothing usable is left, and audits the removal itself (tag `member_removed`).
  `AcceptBusinessInvite` clears `removed_at` on an accepted re-invite — the only way back.
- `UserService::setBusinessStatus()` refuses an actual status change on a removed member with
  `BusinessRuleException::because('Re-invite them instead.', 'member_removed')`; re-submitting the same status
  is a no-op, not a refusal.
- New `members:clean-removed --business=<id>|--all` (dry run; `--apply`): backfills `removed_at` for memberships
  removed before this stage (inactive, no role — listed separately first), then settles every removed row the
  same way a fresh removal would; idempotent.
- `App\Console\Concerns\RunsForBusiness` gained generic `--all` support (`supportsAllBusinesses()`,
  `allBusinessesEveryStatus()`); the 14 existing callers that don't opt in are unaffected.
- `EnsureBusinessToken` now refuses an `/api/v1` token whose business `ResolveTenant` couldn't resolve
  (`business_suspended`/`no_active_business`), except sign-out, listing reachable businesses and switching to one
  (see "Decisions narrowed" below). Needed adding to `bootstrap/app.php`'s `middlewarePriority`, ahead of
  `SubstituteBindings` — otherwise a `{sale:uuid}`-bound route 404'd under the now-unset `TenantContext` before
  this check ran.
- `UserService::update()` refuses changing one's own role, the owner included.
- Tests: `UsersListActionsTest`, `MemberRemovalTest`, `CleanRemovedMembersTest`, plus additions to
  `NoBusinessResolvedTest`, and one `PeopleTest` assertion updated (see "Decisions narrowed").

**Step 3: shops follow access, and the default shop on the Free Trial.**
- `ShopPolicy::viewAny`/`view` allow any business member who can reach the shop; `shops.view` is no longer a
  gate. `Api\ShopController::show` gained the matching `view` check it never had.
  `shops/show.blade.php`'s integrations and staff-list cards are now wrapped in `@can('update', $shop)` — they
  used to render for anyone who could open the page, only their action buttons were gated.
- `businesses.default_shop_id` (see "Migrations" for why it carries no DB foreign key),
  `Business::defaultShop()`/`onFreeTrial()` (answers for the business named, not the ambient `TenantContext`, so
  a command iterating several businesses gets the right answer for each). `RegisterBusiness` sets it to the
  first shop at signup. `DeleteShopAction`/`ShopService::deactivate()` refuse touching the Free Trial's sole
  default shop. New `businesses:set-default-shop --business=<id>|--all` (dry run; `--apply`), on the `--all`
  support from step 1. The business settings page gained a default-shop field, audited via new
  `App\Support\Business\DefaultShopAudit`.
- `CreateUserAction` and `AcceptBusinessInvite` force the Free Trial's default shop onto every new member
  regardless of what was asked for. `StoreBusinessInviteRequest` requires `shop_ids` (min 1) once the business
  has a real metered tier subscription, except for an owner invite (see "Decisions narrowed" for why "metered
  tier", not merely "not the Free Trial").
- New `users:list-without-shop` and `users:grant-default-shop --business=<id>|--all` (dry run; `--apply`).
- New `User::hasAnyShop()` reads `shop_user` directly, joined to `shops` and restricted to the current business
  — `shops()`/`assignedShopIds()` are filtered by `ShopAccessScope` to the *viewer's* own shops, which would
  misreport someone else's row. Used for the People page's new "No shop yet" note (linked to the member's edit
  form, shown only to someone who may update them) and the shops index's "ask the owner" empty state.
- Tests: `ShopAccessAndDefaultShopTest` (13 tests), one `ShopTest` assertion updated, three `InviteFlowTest`
  cases given `shop_ids` now that their business genuinely subscribes to a tier.

### Decisions narrowed with the user while building

The stage's written decisions turned out to need narrowing in three places once they met already-shipped, tested
behaviour. Each is recorded in the stage file's "Points to confirm":

1. **`/api/v1`'s refusal also exempts listing and switching businesses.** Refusing literally every call but
   sign-out left a token whose business became inaccessible with no way to discover or move to another it still
   holds — breaking the existing, tested recovery flow in `ApiV1SignInTest`. `EnsureBusinessToken::EXEMPT_ROUTES`
   now also covers `businesses.index` and `businesses.switch`; the switch's own destination check (404 for a
   business the token holder can't enter) is unaffected.
2. **"A non-owner can't edit someone whose role holds a permission they don't hold" is not built** as a blanket
   check on `UserPolicy::update`. It broke `PeopleTest`'s existing design: a narrowly-permissioned people manager
   (`users.*` only) is meant to freely edit a cashier, even though the real seeded cashier role holds many
   unrelated operational permissions (`sales.create`, `products.view`, …) the manager doesn't. `GrantableRole`
   (pre-existing, unchanged) already covers the part that matters — not being able to *grant* a stronger role.
3. **"Every other tier" means a real metered tier subscription, not merely "not the Free Trial."** A business
   with no subscription at all isn't "on a tier"; `InviteFlowTest`'s fixture businesses have none, and rely on
   inviting with no shop. `shop_ids` is only required once `Entitlements::isMetered()` is true and the business
   isn't on the Free Trial.

### Migrations

Both additive; neither drops or reshapes existing data:

| Migration | What it does |
|---|---|
| `2026_11_10_000001_add_removed_at_to_business_user` | `business_user.removed_at`, nullable; null for every existing row until `members:clean-removed --apply` backfills it |
| `2026_11_10_000002_add_default_shop_id_to_businesses` | `businesses.default_shop_id`, nullable integer, indexed — **not** a DB foreign key. `shops.business_id` and `shops.manager_id`/`users.default_business_id` already close a cycle back to `businesses`; a real constraint here would extend it to three tables, which SQLite's schema builder refuses to rebuild ("Foreign keys form a cycle" — hit while building the test schema, not a MySQL problem, but the test suite runs on both). A shop is always soft-deleted, never hard, so the column is never left dangling in practice. `businesses:set-default-shop` backfills existing businesses with their oldest active shop |

### Rollout (UAT)

Not run as part of this PR — needs a fresh UAT snapshot (see "Before merge"). As planned in the stage file:

1. Snapshot; restore into `rehearsal_testing`; run both migrations there.
2. The Go-live stage's rollout runs first (closes empty businesses, confirms test-data businesses, removes
   deleted users' grants), so this stage's commands see only live businesses.
3. `businesses:set-default-shop --all` (dry run, then `--apply`); `users:list-without-shop --all`;
   `users:grant-default-shop --all` (dry run, then `--apply`).
4. `members:clean-removed --all` (dry run — review the backfill list — then `--apply`).
5. Spot-check as an owner: the users list's buttons; as a member of two businesses: rights in each; as a cashier:
   the shops page lists only their shops.
6. User runs the migrations and commands on UAT after a fresh snapshot.

### Test results

(Run on the branch at the head of this PR.)

| Check | Result |
|---|---|
| `vendor/bin/pint --test --diff=origin/main` | pass |
| `php artisan test --compact` (SQLite) | 2,468 passed, 62 skipped, 4 todos (8,871 assertions) |
| `vendor/bin/pest --configuration=phpunit.mysql.local.xml --compact` (MySQL 8) | 2,480 passed, 50 skipped, 4 todos (8,966 assertions) |

New test files: `OwnerScopeTest`, `UsersListActionsTest`, `MemberRemovalTest`, `CleanRemovedMembersTest`,
`ShopAccessAndDefaultShopTest`. Changed: `NoBusinessResolvedTest` (+2 tests), `PeopleTest` (1 assertion),
`ShopTest` (1 assertion), `InviteFlowTest` (3 cases given `shop_ids`). `ResourceMatrixTest`, `ApiV1ContractTest`
and `FlatApiContractTest` pass unchanged — no route or contract shape changed.

### Before merge

- **UAT rehearsal** (rollout steps 1–4). Not run: needs a fresh UAT snapshot, and the Go-live stage's own rollout
  (closing empty businesses, confirming test data) runs first. The one open acceptance criterion depends on it:
  UAT's members without a shop being correctly listed/granted against real data.
- **`php artisan mobile:contract-diff`** not run: the Flutter app isn't on this machine (`MOBILE_APP_PATH`). No
  route, field or enum the app reads changed; `app-release-checklist.md#stage-10a` is updated with the new
  businesses.index/switch recovery path the app should use before forcing a sign-out.
- **Not built, by decision** (see "Decisions narrowed" above): a blanket "can't edit a stronger member" check.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
