# Stage: Sign-in Security and Audit

> **Source:** the review of findings outside the stock scope, 27 October 2026 (sixth round): logins and failed logins
> aren't audited.
> **Branch:** `feat/sign-in-audit`, from `main` once stage 10 is merged · **Depends on:**
> [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md) (`permissions:sync`), Phase 3 (the audit
> trail per business), Phase 5 (the platform console, impersonation), Phase 7 (till PINs and their lockout)
> · **Can run alongside:** stages 11 to 14 · **Traceability:** [TRACEABILITY-security.md](./TRACEABILITY-security.md)

---

## Revisions

**Created 27 October 2026 (sixth round).** Planned in full from the finding that sign-ins aren't audited, with lockout
visibility and what a platform operator sees versus a business owner.

---

## Goal

Every sign-in attempt, on the web, the flat API, `/api/v1` and the till, is on record, and the right people can see
it: an owner sees attempts on their own staff and can clear a lockout; a platform operator sees everything, including
attempts on emails that match no one. Lockouts are visible while they last, not just felt.

## Before you start

Stage 10 must be merged (`permissions:sync`). Two points are open (see "Points to confirm").

## Decisions that apply

- **The audit trail is per business** (Phase 1c): every row carries its business; another business's row is a 404,
  even for an owner. Rows are immutable; failures to write are swallowed so they never break a sign-in (CLAUDE.md).
- **Personal data is masked in the audit** (Phase 3): emails and IPs of unknown sign-ins are stored masked or hashed.
- **What each sees (decided 27 October 2026, sixth round):** an owner (and whoever holds `audit.view`) sees sign-in
  events for members of their business; a platform operator sees every event, including those that belong to no
  business (unknown emails, operator sign-ins).
- **Thresholds are configuration:** lockout attempts and decay are platform settings (step 4), not code per business.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.
- **Web sign-in** (`App\Http\Requests\Auth\LoginRequest`): 5 tries per email and IP (`RateLimiter`), then
  `event(new Lockout)`; nothing listens to `Login`, `Logout`, `Failed`, `Lockout` or `PasswordReset`.
- **Flat API sign-in** (`POST /api/login`) and `/api/v1` sign-in: throttled by the `auth` limiter
  (`config/ratelimit.php`, 5 a minute); no event recorded.
- **Till PIN sign-ins** are pushed (`till_sign_in`) and audited by `TillSignInHandler`; PIN lockout after 4 tries,
  15 minutes doubling, a manager unlocks (Phase 7).
- **The audit trail:** `audit_logs` on the `audit` connection, `business_id` nullable (rows written before stage 1c, or
  with no business); `AuditService` reads only the current business; impersonation is tagged
  (`impersonated_by:{operator}`).
- **The platform console** has no screen for the platform's own audit rows (stage 5 follow-up, "decided at planning").

---

## Work

### 1. Sign-in events recorded
- [ ] Listeners on `Login`, `Logout`, `Failed`, `Lockout`, `PasswordReset`, `Verified` (Laravel's auth events), and the
      same events raised by the flat API's and `/api/v1`'s sign-in controllers, write audit rows through the existing
      `AuditLogger` path (failures swallowed): `event` (`signed_in`, `signed_out`, `sign_in_failed`, `locked_out`,
      `password_reset`, `email_verified`), `channel` (`web`, `api`, `api_v1`, `till`), the user when known, the IP and
      user agent (masked for unknown users), the time.
- [ ] **Which business a row belongs to:**
  - a successful sign-in: the business the session or token resolves to (`ResolveTenant`); a platform operator's sign-in
    has none;
  - a failed sign-in or lockout for an email that matches a user: one row per business the user is a member of, so
    each owner sees it;
  - an email that matches no one: one row with no business (platform only), the email hashed.
- [ ] Till PIN events keep Phase 7's rows; they gain `channel = till` for the same views.
- [ ] Tests: each event writes a row with its channel; a failed sign-in for a member of businesses A and B writes one
      row in each; an unknown email writes one platform row with the email hashed; an owner of A never sees B's rows;
      a broken audit connection doesn't stop a sign-in.

### 2. Lockouts visible and clearable
- [ ] **"Sign-in security"** (People → Sign-in security; `users.view` and `audit.view`): recent sign-ins and failures
      for the business's members, and **current lockouts**: web (the `LoginRequest` limiter key for the member's
      email), API (the `auth` limiter) and till PIN (Phase 7's `locked_at`), each with when it ends.
- [ ] **Clear a lockout** (`users.unlock`, new: owner and manager templates): clears the limiter keys for that member
      (all channels) or unlocks the till PIN; audited. A member of another business can't be unlocked.
- [ ] Tests: a member locked out on the web shows on the page with its end time; clearing it lets them sign in;
      clearing is audited; a manager without `users.unlock` can't; another business's member is a 404.

### 3. The platform operator's view
- [ ] A console screen "Audit" (`platform.audit.view`, operators only): every row, any business or none, filterable by
      business, event, channel, user and time; sign-in failures grouped by IP and by email hash over a window, to spot
      a spray; impersonation starts and stops.
- [ ] Owners never see platform rows, or rows tagged with an operator's impersonation beyond the "an operator acted"
      marker they already see (Phase 5).
- [ ] Tests: an operator sees a platform row and two businesses' rows; an owner sees only theirs; the spray view groups
      twenty failures from one IP across three businesses.

### 4. Settings and retention
- [ ] Lockout thresholds (web tries, decay; API `auth` limit) become platform settings read by `LoginRequest` and the
      limiters, editable in the console, audited; defaults stay as today (5 tries; 5 a minute).
- [ ] Retention of sign-in rows: see "Points to confirm" 2; until decided, they are kept like every audit row.

## Contract

The flat API and `/api/v1` sign-in responses are unchanged (a lockout stays `429`/`too_many_requests`); the only
change is that attempts are recorded. No app change.

## Schema

| Change | Data carried across |
|---|---|
| `audit_logs.channel` (nullable string) | Existing rows NULL; till sign-in rows written from now on carry `till` |
| Permissions `users.unlock`, `platform.audit.view` | Through `permissions:sync` (`platform.audit.view` to operators only) |
| Platform settings `auth.web_max_attempts`, `auth.web_decay_seconds`, `auth.api_per_minute` | Defaults equal today's values |

## Acceptance criteria

- [ ] Every sign-in, sign-out, failure, lockout, reset and verification is recorded with its channel, on every surface.
- [ ] An owner sees their members' events and current lockouts and can clear a lockout, audited; never another
      business's.
- [ ] A platform operator sees every event, including unknown emails, and a spray view.
- [ ] Sign-in never fails because the audit can't be written.
- [ ] The isolation suite, the contract tests and both full suites pass.

## Risks

- **Audit volume:** a credential spray writes many rows. Mitigation: unknown-email failures go to one platform row each,
  hashed; the limiter caps the rate.
- **Privacy:** failed sign-ins hold emails and IPs. Mitigation: masked for unknown users; owners see only their members.

## Rollout (UAT)

1. Take a snapshot; run the migrations on the rehearsal, including the audit connection
   (`migrate --database=audit --path=database/migrations/audit` on the rehearsal's audit file).
2. `permissions:sync`; check an owner copy has `users.unlock`.
3. Sign in, fail and lock out on the rehearsal on each surface; check the page and the console.
4. Check the server against the app's recorded sign-in requests (unchanged responses).
5. The user runs the migrations (both connections) and `permissions:sync` on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body.

## Points to confirm

1. **Alerts on suspicious sign-ins.** Should an owner be alerted (stage 18's engine) on, say, five failures on one
   member in an hour, or a sign-in from a new country? Recommended: yes, as an alert rule, off by default.
2. **Retention of sign-in rows.** Audit rows are immutable and kept; sign-in rows grow fastest. Keep them like other
   audit rows, or prune sign-in rows after 12 months with a platform command?

## Follow-ups (not this stage)

- Two-factor sign-in for owners and operators.
