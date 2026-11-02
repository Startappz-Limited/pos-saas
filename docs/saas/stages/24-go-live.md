# Stage: Go-live

> **Source:** [stage 10a](./10a-access-and-tenancy.md)'s second to fifth revisions (29 October to 2 November 2026),
> moved here by the decided split of 2 November 2026.
> **Branch:** `chore/go-live`, from `main` · **Depends on:** Phase 5 (the platform console's "Change plan",
> `ChangeBusinessPlan`), stage 3's closure (`CloseBusiness`, `businesses:erase-closed`); before go-live with an imported
> business of several shops, [stage 21](./21-plans-billing-trial-banner.md)'s `ChangeBusinessPlan` shop count (step 3)
> · **Runs:** once on UAT, then once on production before anyone signs in (go-live is around 12 November 2026,
> [pos-saas-plan.md](../pos-saas-plan.md)) · **Before:** [stage 10a](./10a-access-and-tenancy.md)'s commands, which
> then see only live businesses

---

## Revisions

**Created 2 November 2026** from stage 10a, by the decided split: businesses with no subscription
(`businesses:list-unsubscribed`), orphaned businesses (`businesses:close-orphaned`), removing role grants of deleted
users, how business 7 was made, `LegacyImport`, and 10a's "Go-live" section. The wording and code facts are 10a's
(checked 29 October and 2 November 2026), apart from one decision made the same day:
- **Businesses 3 and 5 are closed** like 4 and 7 (decided 2 November 2026), where 10a's fifth revision only listed
  them. A business with no active member is closed whether or not it has shops; the dry run lists those with shops
  separately, as "no members, has shops, closed by decision 2 Nov 2026".

---

## Goal

Before go-live, and on UAT before stage 10a's commands, every business is either one somebody works in, with a
subscription, or closed. No role grant names a user who no longer exists.

## Before you start

Nothing must be merged first except for production with an imported business of several shops, which needs stage 21's
`ChangeBusinessPlan` shop count. One point is open: closing businesses 3 and 5 erases their data after 30 days (see
"Points to confirm").

## Decisions that apply

- **Every business has a subscription, or is closed** (decided 29 October 2026, 10a's second revision). A business
  with no subscription isn't metered: no shop limit, no Free Trial.
- **Orphaned businesses are closed, not given a plan** (decided 29 October 2026, 10a's fourth revision), through the
  normal closure: `CloseBusiness::start()` with no requester, the reason `orphaned`, audited as "System".
- **A business with no active member is closed even when it has shops** (decided 2 November 2026, for UAT's
  businesses 3 and 5). A business with members but no active owner is only listed, for a platform operator to decide.
- **`LegacyImport` is the one-off import** of pre-SaaS data into a freshly migrated database (CLAUDE.md,
  `saas:import-legacy`; checked 29 October 2026), run before go-live and not after. Its business has no subscription,
  so it is given one here.
- **Audits with no signed-in user** record the actor as "System" (10a, "Decisions that apply").

## Relevant current state

Checked 29 October and 2 November 2026 (read-only on UAT).

- **Businesses with no subscription** (checked 29 October 2026): sign-up always creates one (`RegisterBusiness` calls
  `StartTrial` before creating the first shop); the platform console's `ChangeBusinessPlan` creates one;
  `LegacyImport` creates none. UAT: 1 of 11 businesses has none (business 7, `pending_verification`, created 23
  October 2026; it has no primary owner, no member and no shop, so nobody can verify it and
  `StartTrialOnEmailVerified` will never run for it). `ChangeBusinessPlan` on a business with no subscription creates
  a `trialing` one on the chosen plan, `shop_count` 1 whatever the business's shops (stage 21 changes that), ending
  `trial_days` after the change (14 for the Free Trial).
- **How business 7 was made** (checked 29 October 2026): by the stage 3 sign-up, the only code that ever created a
  `pending_verification` business (`RegisterBusiness` from `f8820d9` until `4ecebbb`). It has that sign-up's five
  role copies and one `admin` grant (`model_has_roles`, user 56), but user 56 no longer exists: deleting a user row
  cascades its `business_user` row and nulls `primary_owner_id` (`nullOnDelete`), while `model_has_roles` has no
  foreign key and keeps the grant. No app path deletes a single user row (only `PurgeBusiness`, which deletes the
  business too), so the user was deleted by hand. It isn't from `LegacyImport`, which inserts its business as
  `active`. Business 6 is gone too. **Production** starts from the clean baseline and the one-off import; it can hold
  such rows only if a stage 3 sign-up ran there or a user row is deleted by hand, and the imported business itself has
  no subscription.
- **UAT's businesses with no active member** (checked 2 November 2026):

  | Business | Members | Shops | Sales | Subscription | Here |
  |---|---|---|---|---|---|
  | 3 "Pos Biz" | none | 1 | 1 | `trialing` | closed (decided 2 November 2026) |
  | 4 "Biz Connect" | none | none | none | `trialing` (so `list-unsubscribed` misses it) | closed |
  | 5 "Hub" | none | 1 | none | `trialing` | closed (decided 2 November 2026) |
  | 7 "POS Biz" | none | none | none | none, `pending_verification` | closed |

- **Role grants of deleted users** (checked 2 November 2026): 5 `model_has_roles` rows name users that no longer
  exist: users 51 and 52 (business 3), 53 (business 4), 54 (business 5) and 56 (business 7). No
  `model_has_permissions` row does. Neither table has a foreign key to `users`.
- **What closing does** (checked 2 November 2026): `CloseBusiness::start()` sets the business `closing`, records a
  `business_closures` row with `delete_after` = now + `tenancy.closure_grace_days` (30), and builds an export for the
  owners. **`businesses:erase-closed`** runs daily at 02:30 (`routes/console.php`) and erases every business whose
  grace period is over (`PurgeBusiness`: its rows, logins owned only by it, its files and audit rows). A closing
  business can be reopened by an owner within the grace period. A business with no owner has nobody to reopen it or
  receive its export.

## Work

### 1. Businesses with no subscription
- [ ] `php artisan businesses:list-unsubscribed` (read-only) lists every business with no subscription, whatever its
      status, and marks each with no active member "orphaned: close" rather than "needs a plan". A platform operator
      gives each other one a plan with the console's "Change plan" (`ChangeBusinessPlan`) before 10a's commands run:
      the Free Trial (`free-trial`) for one shop or none, otherwise a tier for its shops (stage 21's shop count).
      Either way it **starts a trial**. After go-live, only `LegacyImport` could make one, and it isn't run again.

### 2. Orphaned businesses, and grants of deleted users
- [ ] `php artisan businesses:close-orphaned` (dry run; `--apply`), whatever each business's status:
  - **closes every business with no active member**, with `CloseBusiness::start()` (no requester, a new reason
    `orphaned` beside `owner` and `lapsed`; `business_closures.reason` is a string, so no migration), audited as
    "System". The dry run lists them in two groups: "no members, no shops" and **"no members, has shops, closed by
    decision 2 Nov 2026"**, each with its shops, sales count and subscription;
  - **lists, and never closes, businesses with members but no active owner** (no active member holding `admin`), for
    a platform operator to decide;
  - **removes role grants of users that no longer exist** (`model_has_roles` and `model_has_permissions` rows of
    `model_type` User whose `model_id` has no `users` row), in every business, each audited as "System" with the role
    and business.

  A second `--apply` changes nothing. `businesses:erase-closed` erases a closed business when its grace period ends,
  as for any closure (see "Points to confirm" for 3 and 5).
- [ ] Tests:
  - `businesses:list-unsubscribed` lists a business with no subscription and changes nothing; marks one with no
    active member "orphaned: close";
  - `businesses:close-orphaned` closes a business with no active member and no shop, and one with no active member
    and a shop (listed in the "has shops" group), with the reason `orphaned`, audited as "System";
  - it leaves a business with an active member alone, and lists one with members but no active owner without closing
    it;
  - it removes a role grant of a deleted user and keeps every other grant;
  - a second run changes nothing.

## Contract

None: commands and the console only.

## Schema

| Change | Data carried across |
|---|---|
| None (`business_closures.reason` is a string; `orphaned` is a new value) | The commands close businesses and delete grants of deleted users, each audited |

## Acceptance criteria

- [ ] Every business has a subscription before 10a's commands run, except those closed here.
- [ ] `businesses:close-orphaned` closes every business with no active member (UAT: 3, 4, 5 and 7), lists those with
      members but no active owner, and removes role grants of deleted users (UAT: 5).
- [ ] On production, the imported business has a plan for its shops before anyone signs in.

## Risks

- **Businesses with no subscription aren't metered** (no shop limit, no Free Trial). Accepted only until this stage
  gives each a plan or closes it; afterwards only `LegacyImport` could make one, and it isn't run again.
- **Closing erases after the grace period.** The closure keeps the data for 30 days (`closure_grace_days`), then
  `businesses:erase-closed` erases it. For 4 and 7 nothing is lost (no shop, no sale). **Business 3 has a shop and a
  sale, and 5 a shop**; with no owner, nobody receives the export or can reopen them. Mitigation: the dry run lists
  them separately; see "Points to confirm".
- **Removing grants of deleted users** touches every business. Mitigation: only rows whose user has no `users` row,
  which nothing can read (role checks start from a user); the dry run lists each; audited.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`.
2. `businesses:list-unsubscribed` (read-only: business 7 expected, marked orphaned).
3. `businesses:close-orphaned` (dry run: closes 4 and 7 ("no members, no shops") and 3 and 5 ("no members, has shops,
   closed by decision 2 Nov 2026"), lists no business with members but no owner, removes 5 grants of deleted users;
   then `--apply`).
4. Check that `list-unsubscribed` then lists none.
5. The user runs the same on UAT after a fresh snapshot, then 10a's rollout.

## Go-live (production)

After the one-off `LegacyImport` into the freshly migrated production database, and before anyone signs in:
1. `businesses:list-unsubscribed` against production (read-only). The imported business is expected (it has no
   subscription); any business marked orphaned is closed with `businesses:close-orphaned` (dry run, then `--apply`).
2. A platform operator gives the imported business a plan for its shops in the console ("Change plan"); with several
   shops this needs stage 21's `ChangeBusinessPlan` shop count. The command is run again until it lists none.
3. Stage 10a's `businesses:set-default-shop --all`, `users:grant-default-shop --all` and `members:clean-removed --all`,
   each as a dry run, then `--apply`.

## Definition of done

Every acceptance criterion is ticked with evidence; the UAT rollout's dry runs are in the PR body. The stage file and
PROGRESS.md are updated. The pre-push checks (Pint, SQLite, MySQL) pass.

## Points to confirm

1. **Businesses 3 and 5 are erased 30 days after they are closed.** The decision (2 November 2026) says closing keeps
   their data; it does only for the grace period (`closure_grace_days`, 30), after which the scheduled
   `businesses:erase-closed` erases them, business 3's sale included. Nobody owns them, so nobody receives the export
   or can reopen them, and a closure export can be downloaded only by an owner of the business (checked 2 November
   2026: `BusinessExport`'s download, listed in `TenantScopeBypassTest`). Proposed, either: the erasure of a business
   closed as `orphaned` with shops waits for a platform operator's confirmation in the console; or the console lets an
   operator download its closure export first. Until decided, the command closes them as decided and the risk stands.

## Follow-ups (not this stage)

- The M-Pesa collection switch at go-live (`BILLING_MPESA_ENABLED`) and the import rehearsal could join this stage;
  not decided.
