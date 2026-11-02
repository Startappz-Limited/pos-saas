# Stage: Go-live

> **Source:** [stage 10a](./10a-access-and-tenancy.md)'s second to fifth revisions (29 October to 2 November 2026),
> moved here by the decided split of 2 November 2026; the closure rule decided 2 November 2026.
> **Branch:** `chore/go-live`, from `main` · **Depends on:** Phase 5 (the platform console: its business page and "Change
> plan", `ChangeBusinessPlan`), stage 3's closure (`CloseBusiness`, `BuildBusinessExport`, `businesses:erase-closed`);
> before go-live with an imported business of several shops, [stage 21](./21-plans-billing-trial-banner.md)'s
> `ChangeBusinessPlan` shop count (step 3) · **Runs:** step 3 ships first; then once on UAT, and once on production
> before anyone signs in (go-live is around 12 November 2026, [pos-saas-plan.md](../pos-saas-plan.md)) · **Before:**
> [stage 10a](./10a-access-and-tenancy.md)'s commands, which then see only live businesses

---

## Revisions

**Created 2 November 2026** from stage 10a, by the decided split: businesses with no subscription
(`businesses:list-unsubscribed`), orphaned businesses (`businesses:close-orphaned`), removing role grants of deleted
users, how business 7 was made, `LegacyImport`, and 10a's "Go-live" section. The wording and code facts are 10a's
(checked 29 October and 2 November 2026).

**Revised 2 November 2026** (code facts and UAT counts checked 2 November 2026, read-only):
- **A business is never erased before its owner has its data** (decided): erasure waits for the closure export to be
  emailed to the owners, and counts 30 days from that email, not from the closure request. A failed send blocks
  erasure, is retried, and is listed for a platform operator. New step 3; it changes stage 3's closure (see
  [stage 3](./05-phase-3-accounts-roles-settings.md)'s note) and Phase 4's lapse closure.
- **Only truly empty businesses are closed automatically** (decided; reverts the first version's "any business with no
  active member is closed"): no active member, no shop and no sale. A business with data but no active member is
  listed; a platform operator either invites an owner or downloads its export and confirms the closure.
- **Businesses 3 and 5 are UAT test data** (decided, a one-off, not a rule): a platform operator confirms their
  closure without an export, and they are erased on the normal schedule.
- The open point about erasing businesses 3 and 5 is closed.

**Revised again 2 November 2026** (code facts checked 2 November 2026):
- **Downloading a business's export as an operator needs its own platform permission**, `platform.business-data.export`,
  granted to named operators and audited; being an operator isn't enough.
- **The closure screens** say "Erased on or after <date>" once the export has been emailed, and "30 days after your
  export is emailed" before.
- **After the fifth failed send, every platform operator is notified** by email, as well as the closure being listed.
- [pos-saas-plan.md](../pos-saas-plan.md) decision 4.9 updated to match (a closure and erasure row; the lapse row and
  Phase 3's description).

---

## Goal

Before go-live, and on UAT before stage 10a's commands, every business is either one somebody works in, with a
subscription, or closed. No role grant names a user who no longer exists. No business is erased before its owner,
or a platform operator for a business with no owner, has its data.

## Before you start

Step 3 (the closure rule) ships before steps 1 and 2 are run anywhere, since they close businesses. Production with an
imported business of several shops also needs stage 21's `ChangeBusinessPlan` shop count. Nothing is open.

## Decisions that apply

- **Every business has a subscription, or is closed** (decided 29 October 2026, 10a's second revision). A business
  with no subscription isn't metered: no shop limit, no Free Trial.
- **A business is never erased before its owner has its data** (decided 2 November 2026). When a business is closed,
  its closure export is built and emailed to its owners: the primary owner and every co-owner (the code already sends
  to every `admin` holder of the business). When the email went is recorded and audited. `businesses:erase-closed`
  erases a closed business only when **all** of these hold:
  1. the closure export has been emailed to an owner, or a platform operator has confirmed the closure (decision
     below);
  2. at least **30 days** have passed since that email or that confirmation (not since the closure request);
  3. the business is still closed: nobody has reopened it or cancelled the closure meanwhile.

  If the export can't be sent (no owner with an email, a mail failure), the business is **not** erased: the send is
  retried, and after repeated failures the closure is listed for a platform operator. Reopening within the 30 days
  cancels the erasure; closing again later starts a new export and a new 30 days. This holds for every closure: an
  owner's, a lapse's (Phase 4) and an orphan's.
- **Only truly empty orphaned businesses are closed automatically** (decided 2 November 2026): no active member, no
  shop (soft-deleted included) and no sale. There is nothing to hand over, so the platform operator who runs the
  command confirms the closure, and erasure follows 30 days later.
- **A business with data but no active member is never closed automatically** (decided 2 November 2026). It is listed;
  a platform operator decides each one in the console, audited:
  - **invite an owner**: the person accepts an owner's invite, and from then on the business is theirs; if it is closed
    later, they get the export as above;
  - or **download its export and confirm the closure**: the operator downloads the business's export, then confirms;
    erasure follows 30 days after the confirmation.
- **UAT's businesses 3 and 5 are test data** (decided 2 November 2026, a one-off for UAT test data, not a rule): a
  platform operator confirms their closure without downloading an export ("UAT test data, no export needed, decision
  2 Nov 2026"), and they are erased 30 days later on the normal schedule.
- **`LegacyImport` is the one-off import** of pre-SaaS data into a freshly migrated database (CLAUDE.md,
  `saas:import-legacy`; checked 29 October 2026), run before go-live and not after. Its business has no subscription,
  so it is given one here.
- **Audits with no signed-in user** record the actor as "System" (10a, "Decisions that apply"); a command run by a
  platform operator records that operator where this stage says so.

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

  | Business | Members | Shops (deleted included) | Sales | Subscription | Here |
  |---|---|---|---|---|---|
  | 3 "Pos Biz" | none | 1 | 1 | `trialing` | has data: listed; closed by an operator's confirmation, no export (UAT test data, decided) |
  | 4 "Biz Connect" | none | none | none | `trialing` (so `list-unsubscribed` misses it) | empty: closed by the command |
  | 5 "Hub" | none | 1 | none | `trialing` | has data: listed; closed by an operator's confirmation, no export (UAT test data, decided) |
  | 7 "POS Biz" | none | none | none | none, `pending_verification` | empty: closed by the command |

- **Role grants of deleted users** (checked 2 November 2026): 5 `model_has_roles` rows name users that no longer
  exist: users 51 and 52 (business 3), 53 (business 4), 54 (business 5) and 56 (business 7). No
  `model_has_permissions` row does. Neither table has a foreign key to `users`.
- **How closure works today** (checked 2 November 2026):
  - `CloseBusiness::start()` sets the business `closing`, creates a `business_closures` row with `delete_after` =
    **now + `tenancy.closure_grace_days` (30)**, counted from the request, then creates a `BusinessExport` (purpose
    `closure`) and dispatches `BuildBusinessExport`. Callers: an owner (web, after an emailed code; `/api/v1`
    `business/closure`), Phase 4's lapse sweep (`SweepSubscriptions`, reason `lapsed`, no requester).
  - `BuildBusinessExport` (queued, 2 tries) builds the file, marks the export `ready`, then sends
    `BusinessExportReady` by mail to **every user holding `admin` in the business** (so the primary owner and the
    co-owners) in the same job, synchronously (the notification isn't queued). Its link is signed for 7 days, and
    downloading needs signing in as an owner of the business (`BusinessExport::findAcrossBusinesses()`, listed in
    `TenantScopeBypassTest`). **Nothing records whether or when the email went, and nothing audits it.** A mail
    failure throws, so the job retries and then `failed()` marks the export `failed` though its file was built. With
    no owner, `Notification::send()` sends to nobody and the export is still `ready`.
  - `CloseBusiness::cancel()`: an owner reopens during the grace period (`BusinessDataController::cancelClosure`),
    or a payment does for a lapse (`ApplyPlatformPayment`); `cancelled_at` is set and the business is `active` again.
    Closing again creates a new closure row and a new export.
  - **`businesses:erase-closed`** runs daily at 02:30 (`routes/console.php`). It takes open closures
    (`cancelled_at` and `erased_at` null) whose `delete_after` has passed, and `CloseBusiness::erase()` purges the
    business if it is still `closing` (`PurgeBusiness`: its rows, logins owned only by it, its files and audit rows).
    **It never looks at the export**: a business is erased 30 days after the request whether or not its owners ever
    received their data.
  - UAT has had no closure yet (`business_closures` empty, no closure export).

## Work

### 1. Businesses with no subscription
- [ ] `php artisan businesses:list-unsubscribed` (read-only) lists every business with no subscription, whatever its
      status, and marks each with no active member "orphaned" (with whether it has data) rather than "needs a plan".
      A platform operator gives each other one a plan with the console's "Change plan" (`ChangeBusinessPlan`) before
      10a's commands run: the Free Trial (`free-trial`) for one shop or none, otherwise a tier for its shops (stage
      21's shop count). Either way it **starts a trial**. After go-live, only `LegacyImport` could make one, and it
      isn't run again.

### 2. Orphaned businesses, and grants of deleted users
- [ ] `php artisan businesses:close-orphaned --operator=<email>` (dry run; `--apply`), whatever each business's
      status. `--operator` names the platform operator running it (`User::isPlatformOperator()`, `users.is_platform_operator`;
      refused otherwise); `--apply` without it is refused.
  - **closes every truly empty business**: no active member, no shop (soft-deleted included) and no sale. Closed with
    `CloseBusiness::start()` (no requester, a new reason `orphaned` beside `owner` and `lapsed`;
    `business_closures.reason` is a string), and **confirmed** by the operator named (step 3's `confirmed_by`,
    `confirmed_at`): there is no owner and nothing to hand over, so erasure follows 30 days after. Audited
    (`PlatformAudit::record('business.orphan_closed', …)`, with the operator in its context: a command has no
    signed-in user);
  - **lists, and never closes, every business with data but no active member** (a shop or a sale), with its shops,
    sales count and subscription, for an operator to decide in the console (below);
  - **lists, and never closes, businesses with members but no active owner** (no active member holding `admin`);
  - **removes role grants of users that no longer exist** (`model_has_roles` and `model_has_permissions` rows of
    `model_type` User whose `model_id` has no `users` row), in every business, each audited with the operator, the
    role and the business.

  A second `--apply` changes nothing.
- [ ] **The console's decision for a listed business** (its business page, `platform.businesses.show`; operators only,
      each action audited through `PlatformAudit` with the operator):
  - **"Invite an owner"**: sends an owner's invite (the `admin` role) to an email (`SendBusinessInvite`); once accepted
    the business has an owner and leaves the list. Audited `business.owner_invited`.
  - **"Download export"**: builds the business's export (`BusinessExport`, purpose `closure`) and lets the operator
    download it (a new console route; added to `TenantScopeBypassTest` with its reason). It needs the platform
    permission **`platform.business-data.export`** (below), not only being an operator. Audited
    `business.export_downloaded` (the operator, the business, the export, its size).
  - **"Confirm closure"**: offered once the operator has downloaded the export (or, for UAT's 3 and 5 only, with the
    recorded reason "UAT test data, no export needed, decision 2 Nov 2026"); closes the business (reason `orphaned`)
    with `confirmed_by` and `confirmed_at` set, so erasure follows 30 days after the confirmation. Audited
    `business.closure_confirmed`, with whether the export was downloaded.
- [ ] **The platform permission `platform.business-data.export`** (decided 2 November 2026): a business's data is
      handed to an operator only by someone given this right on purpose.
  - It isn't a Spatie permission: a Spatie grant always names a business (checked 2 November 2026:
    `model_has_permissions.business_id` is NOT NULL and part of its primary key), and an operator belongs to none.
    It is a row in a new table `platform_grants` (`user_id`, `permission`, `granted_by`, timestamps; unique on the
    pair), granted to no one at first.
  - It is checked with `User::hasPlatformPermission('platform.business-data.export')`, **never** `can()`:
    `Gate::before` lets a platform operator through every `can()` check (checked 2 November 2026: Phase 5, decision
    4.4; `is_platform_operator` is the only operator flag today), so `can()` would always say yes.
  - Granted and revoked by `php artisan platform:grant-permission <operator email> platform.business-data.export`
    (and `--revoke`), only to a platform operator (`User::isPlatformOperator()`), each audited through
    `PlatformAudit` (`platform.permission_granted` / `_revoked`, with who ran the command in its context).
  - The console hides "Download export" from an operator without it, and the route answers 404 to them, as console
    routes do.
- [ ] Tests:
  - `businesses:list-unsubscribed` lists a business with no subscription and changes nothing; marks an orphaned one;
  - `businesses:close-orphaned --apply` closes a business with no active member, no shop and no sale, confirmed by
    the named operator and audited with them; refuses `--apply` without an operator, or with someone who isn't one;
  - it never closes a business with no active member but a shop (a deleted one included) or a sale, and lists it;
    lists a business with members but no active owner without closing it; leaves a business with an active owner
    alone;
  - it removes a role grant of a deleted user and keeps every other grant; a second run changes nothing;
  - in the console, an operator invites an owner to a listed business, and once accepted it leaves the list;
  - "Confirm closure" is refused until the export is downloaded (apart from the recorded UAT reason); confirming
    closes the business with `confirmed_at`, audited; a non-operator gets 404 on every one of these (as every console
    route answers, `EnsurePlatformOperator`);
  - an operator without `platform.business-data.export` gets 404 on the download and doesn't see the button; one
    with it downloads, audited; `platform:grant-permission` grants and revokes it only for an operator, audited.

### 3. A business is never erased before its owner has its data (all closures)
- [ ] **Schema** (additive): `business_closures` gains `export_id` (nullable, the closure's `business_exports` row),
      `export_sent_at` (nullable timestamp: when the closure export was emailed; "`closure_export_sent_at`" in the
      decision), `export_attempts` (unsigned int, default 0), `export_error` (nullable text, the last failure),
      `confirmed_by` (nullable, a platform operator), `confirmed_at` (nullable) and `undelivered_notified_at`
      (nullable: when operators were emailed about the fifth failure). `delete_after` becomes nullable:
      it is set to the email's or the confirmation's time + 30 days, and stays null until then.
- [ ] **`CloseBusiness::start()`** no longer sets `delete_after`; it records the closure's `export_id`.
- [ ] **What the closure screens say** (decided 2 November 2026), on the web (the closing business's page and the
      confirmation after closing) and in `/api/v1`'s `business/closure` message:
  - before the export has been emailed: "Your data will be erased 30 days after your export is emailed.";
  - once it has: "Erased on or after <date>." (`delete_after`, in the business's timezone), with the date the export
    was emailed;
  - after an operator's confirmation (a business with no owner): "Erased on or after <date>.";
  - the confirmation email that the closure started says the same as the screen at that moment, and the export email
    says "Your data will be erased on or after <date>.".
- [ ] **Sending is separate from building.** For a closure export, `BuildBusinessExport` builds the file and then
      dispatches a new queued `SendClosureExport` job, so a mail failure no longer marks a built export `failed`.
      `SendClosureExport`:
  - sends `BusinessExportReady` to every owner of the business (the primary owner and co-owners: every `admin`
    holder, as today) who has an email; with none, it records the failure "No owner to send it to";
  - on success sets `export_sent_at` and `delete_after` (= `export_sent_at` + `tenancy.closure_grace_days`, 30) and
    audits `business.closure_export_sent` (who it went to, "System" as the actor);
  - on failure increments `export_attempts` and records `export_error`; it is retried by `businesses:erase-closed`'s
    first pass (below), at most once a day.
- [ ] **`businesses:erase-closed`** (daily, 02:30), in two passes:
  1. **retry**: every open closure with no `export_sent_at` and no `confirmed_at`, whose business is still `closing`,
     gets `SendClosureExport` again while `export_attempts` is under 5 (a missing or failed export is rebuilt first);
  2. **erase**: only open closures (`cancelled_at` and `erased_at` null) whose business is still `closing`, with
     `export_sent_at` or `confirmed_at` set, and `delete_after` (30 days after that) passed. `CloseBusiness::erase()`
     checks the same before purging.

  A closure with 5 failed attempts is never erased. It is listed for platform operators, and **every platform operator
  is emailed** (decided 2 November 2026): a new `ClosureExportUndelivered` notification (mail, queued) with the
  business, the closure's age, the attempts and the last error, sent once when the fifth attempt fails and audited
  (`business.closure_export_undelivered`, "System").
- [ ] **The console lists closures waiting for their export** (`export_sent_at` and `confirmed_at` null, 5 or more
      attempts, or no owner), with the error, on a "Closures" page (operators only). An operator can retry the send,
      invite an owner, or download the export and confirm the closure (as in step 2), each audited.
- [ ] **Reopening and closing again:** `CloseBusiness::cancel()` (an owner, or a payment for a lapse) cancels the
      erasure, as today (the closure is no longer open). Closing again creates a new closure, a new export and, once
      emailed, a new 30 days.
- [ ] **Existing closures at deploy** (none on UAT, checked 2 November 2026; production starts with none): a migration
      leaves an open closure with no `export_sent_at` unerasable until the retry pass sends its export, so the 30 days
      start from that email.
- **Stage 3's closure description and decision 4.9 in the plan** match this: stage 3 has a note pointing here, and
  [pos-saas-plan.md](../pos-saas-plan.md) 4.9 was updated on 2 November 2026 (a "Closing and erasing a business"
  row, the lapse row, and Phase 3's description).
- [ ] Tests (`BusinessClosureTest` and a new `ClosureExportTest`):
  - an owner's closure builds the export and emails it to the primary owner and a co-owner; `export_sent_at` is set,
    `delete_after` is 30 days after it, and the send is audited;
  - `businesses:erase-closed` doesn't erase a closure whose export hasn't been emailed, however old the request;
  - it doesn't erase 29 days after the email, and does at 30;
  - a mail failure leaves the export `ready`, records the error and an attempt, and the business isn't erased; the
    next day's pass retries and succeeds, and the 30 days start then;
  - a business with no owner records "No owner to send it to"; after 5 attempts it is listed for operators, every
    operator is emailed once (not again on later passes), the notification is audited, and the business is never
    erased; an operator's confirmation then lets it be erased 30 days later;
  - reopening within the 30 days cancels the erasure; closing again later sends a new export and starts a new 30 days;
  - a lapse's closure (`SweepSubscriptions`) follows the same rule, and a payment that reopens it cancels the erasure;
  - the closure screen says "30 days after your export is emailed" before the email and "Erased on or after <date>"
    after it (the date 30 days after the email), on the web and in `/api/v1`'s message.

## Contract

`/api/v1` `business/closure`'s message says "30 days after your export is emailed" (the export can't have gone yet
when the closure starts); its fields are unchanged.

## Schema

| Change | Data carried across |
|---|---|
| `business_closures`: `export_id`, `export_sent_at`, `export_attempts`, `export_error`, `confirmed_by`, `confirmed_at`; `delete_after` nullable | None on UAT (no closure yet). Any open closure without `export_sent_at` waits for its export to be sent before its 30 days start |
| `business_closures.reason` gains the value `orphaned` (a string) | None |
| `business_closures.undelivered_notified_at` (nullable): when operators were told of the fifth failed send | None |
| `platform_grants` (new: `user_id`, `permission`, `granted_by`, timestamps; unique on `user_id` and `permission`) | Empty: `platform.business-data.export` is granted to no one at first |

## Acceptance criteria

- [ ] No business is erased before its closure export has been emailed to an owner, or a platform operator has
      confirmed its closure, and 30 days have passed since; a reopened business is never erased.
- [ ] A failed send is retried, recorded and, after 5 attempts, listed for operators and emailed to every operator;
      it never leads to erasure.
- [ ] The closure screens say "30 days after your export is emailed" before the email, and "Erased on or after <date>"
      after it.
- [ ] Only an operator granted `platform.business-data.export` can download a business's export, and each grant and
      download is audited.
- [ ] Every business has a subscription before 10a's commands run, except those closed here.
- [ ] `businesses:close-orphaned` closes only truly empty businesses (UAT: 4 and 7), lists those with data but no
      active member (UAT: 3 and 5) and those with members but no active owner, and removes role grants of deleted
      users (UAT: 5); every action names the operator.
- [ ] An operator's invite, export download and closure confirmation are audited; UAT's 3 and 5 are confirmed as test
      data without an export.
- [ ] On production, the imported business has a plan for its shops before anyone signs in.

## Risks

- **Businesses with no subscription aren't metered** (no shop limit, no Free Trial). Accepted only until this stage
  gives each a plan or closes it; afterwards only `LegacyImport` could make one, and it isn't run again.
- **A closure whose export never sends is never erased.** Its data stays, and it holds the business's rows and files
  indefinitely. Mitigation: retries, then the operators' list, where they can invite an owner or confirm the closure.
- **Erasure now comes later** (30 days after the email, not the request), so data stays a little longer than the
  closure screens used to promise. Mitigation: the screens and emails say when instead of a date.
- **An operator downloads a business's data** (the new download). Mitigation: only operators granted
  `platform.business-data.export` by name, every grant and download audited, offered only for a business with no active
  member or a closure waiting for its export.
- **Checking the permission with `can()` by mistake** would let every operator through (`Gate::before`). Mitigation:
  `User::hasPlatformPermission()`, and a test with an operator who lacks it.
- **Closing UAT's 3 and 5 erases them** 30 days after the operator's confirmation, with no export. Accepted: UAT test
  data (decided 2 November 2026); recorded as a one-off, not a rule.
- **Removing grants of deleted users** touches every business. Mitigation: only rows whose user has no `users` row,
  which nothing can read (role checks start from a user); the dry run lists each; audited with the operator.

## Rollout (UAT)

1. Step 3 is merged and its migration run (snapshot first; rehearse on `rehearsal_testing`).
2. `businesses:list-unsubscribed` (read-only: business 7 expected, marked orphaned, empty).
3. `businesses:close-orphaned --operator=<operator email>` (dry run: closes 4 and 7 (empty), lists 3 and 5 (data, no
   member), removes 5 grants of deleted users; then `--apply`).
4. In the console, an operator confirms the closure of 3 and 5 with the recorded reason "UAT test data, no export
   needed, decision 2 Nov 2026"; they are erased 30 days later.
5. Check that `list-unsubscribed` then lists none.
6. The user runs the same on UAT after a fresh snapshot, then 10a's rollout.

## Go-live (production)

After the one-off `LegacyImport` into the freshly migrated production database, and before anyone signs in:
1. `businesses:list-unsubscribed` against production (read-only). The imported business is expected (it has no
   subscription). An orphaned empty business is closed with `businesses:close-orphaned --operator=<email>` (dry run,
   then `--apply`); one with data is decided in the console (invite an owner, or download and confirm).
2. A platform operator gives the imported business a plan for its shops in the console ("Change plan"); with several
   shops this needs stage 21's `ChangeBusinessPlan` shop count. The command is run again until it lists none.
3. Stage 10a's `businesses:set-default-shop --all`, `users:grant-default-shop --all` and `members:clean-removed --all`,
   each as a dry run, then `--apply`.

## Definition of done

Every acceptance criterion is ticked with evidence; the UAT rollout's dry runs are in the PR body. The stage file and
PROGRESS.md are updated. The pre-push checks (Pint, SQLite, MySQL) pass.

## Points to confirm

Nothing is open. Closed 2 November 2026: erasing businesses 3 and 5 (UAT test data: confirmed without an export,
erased on the normal schedule); the closure rule (erasure only after the export is emailed, or an operator confirms,
plus 30 days); orphaned businesses with data are listed, not closed; the operator download's permission; the closure
screens' wording; operators emailed after the fifth failed send.

## Follow-ups (not this stage)

- The M-Pesa collection switch at go-live (`BILLING_MPESA_ENABLED`) and the import rehearsal could join this stage;
  not decided.
