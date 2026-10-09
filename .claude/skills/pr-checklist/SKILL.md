---
name: pr-checklist
description: Pre-merge quality gate for this POS codebase — works out which standards are mandatory for the change type at hand (feature, bug fix, refactor, hotfix, config, database, API, docs), runs the checks, and routes to the specific standards skill for each area. Use before opening a pull request, before declaring a change complete, when asked to review a diff or branch against project standards, or when unsure which rules apply to a change.
---

# Pre-Merge Checklist (POS)

Sources: [PR_CHECKLIST.md](../../../.ai/general/PR_CHECKLIST.md),
[ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md).

**There is no CI in this repo** (`.github/` holds AI guidance, not workflows) — nothing runs these
checks but you. Treat that as a reason to be stricter, not looser.

## Step 1 — classify the change, then load the right skills

| Change type | Mandatory (🔴) areas | Load |
|---|---|---|
| New feature | structure, coding, DB, models, roles, audit, API, routing, security, UI, testing, docs, N+1 | most of the below |
| Bug fix | coding, API, security, testing (+ regression test), N+1 | security, testing, performance |
| Refactor | structure, coding, models, API, routing, security, testing, memory/N+1 | architecture, performance, testing |
| Hotfix | API, security, code quality | security, api |
| Config change | security, docs, `.env.example` | security, documentation |
| Database change | DB design, models, audit, indexes, security, testing, docs | database, audit, performance, testing |
| API change | everything API-adjacent, incl. web↔API sync | api, security, performance, testing, documentation |
| Docs only | documentation | documentation |

Skills: [laravel-standards](../laravel-standards/SKILL.md) (index + hard rules) ·
[laravel-security](../laravel-security/SKILL.md) · [laravel-database](../laravel-database/SKILL.md) ·
[laravel-api](../laravel-api/SKILL.md) · [laravel-routing](../laravel-routing/SKILL.md) ·
[laravel-authorization](../laravel-authorization/SKILL.md) · [laravel-audit](../laravel-audit/SKILL.md) ·
[laravel-architecture](../laravel-architecture/SKILL.md) ·
[laravel-performance](../laravel-performance/SKILL.md) · [laravel-testing](../laravel-testing/SKILL.md) ·
[laravel-documentation](../laravel-documentation/SKILL.md) ·
[laravel-integrations](../laravel-integrations/SKILL.md) · [bootstrap-ui](../bootstrap-ui/SKILL.md) ·
[ui-design-standards](../ui-design-standards/SKILL.md)

## Step 2 — always-mandatory checks (every change, no exceptions)

**Security**
- [ ] Protected routes authenticated (`auth` / `auth:sanctum`)
- [ ] Every resource action authorized (policy or `permission:`), **including the shop-access check**
- [ ] FormRequest + `$request->validated()`; no `$request->all()`
- [ ] `$fillable` present on touched models
- [ ] `@csrf` + `@honeypot` on new web forms
- [ ] `{{ }}` escaping; no `{!! !!}` on user content
- [ ] `throttle:` on new state-changing / API endpoints
- [ ] No secrets in code; no `env()` outside `config/`; no PII/tokens logged

**Code quality**
- [ ] Methods under ~20 executable lines, single responsibility
- [ ] Types on params/returns; PHPDoc on new public methods
- [ ] No `dd()`/`dump()`/`var_dump()`, no commented-out code, no hardcoded values
- [ ] Enums instead of magic status strings
- [ ] Logic in Action/Service/Job, not the controller

**Performance (N+1 is 🔴 even for bug fixes)**
- [ ] Relationships eager-loaded for anything a loop or view touches
- [ ] `withCount()`/`withExists()`/`withSum()` instead of loading rows to aggregate
- [ ] Lists paginated; bulk work chunked
- [ ] New filter/sort columns indexed; no `whereDate()` on an indexed column
- [ ] Heavy/remote work queued

**Data safety**
- [ ] No destructive DB command was run (`migrate:fresh` etc. — forbidden)
- [ ] New migration has a working `down()`
- [ ] `uuid` + `getRouteKeyName()` on new models; integer `id` never exposed

## Step 3 — conditional checks

**If you added or changed a route** → named (dot notation), UUID-bound, explicit middleware stack;
`php artisan route:list` verified.

**If you changed a controller** → its web↔API counterpart is updated too, or you explicitly say why not
([1.1 web_api_sync_guide.md](../../../.ai/general/1.1%20web_api_sync_guide.md)).

**If you changed the API** → response shape still `{success, data}`; path still flat/unversioned;
`docs/flutter/` updated.

**If you added a permission or role** → seeded idempotently in `PermissionSeeder`/`RoleSeeder`, granted to
the right roles, permission cache reset, policy updated.

**If it's a state-changing operation on money, stock, users, or roles** → audit logging considered
([laravel-audit](../laravel-audit/SKILL.md)).

**If you touched an integration, webhook, or sync job** → signature verified over the raw body and
**fails closed**; the ingestion path dedupes on the platform id; `Http::` calls have timeouts; the job has
`tries`/`backoff`/`failed()`; credentials stay encrypted and unlogged; failure can't roll back a sale
([laravel-integrations](../laravel-integrations/SKILL.md)).

**If you added a config key** → `.env.example` updated with a placeholder.

**If you touched UI** → Bootstrap 5 markup + BS5 JS API, semantic status badge driven by the enum,
strings wrapped in `__()`, labels/aria present, no inline styles or hex colors.

## Step 4 — run the gate

```bash
vendor/bin/pint                                  # required before finishing
php artisan test --filter='<what you touched>'   # or the specific file
php artisan route:list --path=api                # if routes changed
```

Fast greps that catch the most common violations:

```bash
grep -rn '\$request->all()' app/Http/Controllers
grep -rn 'dd(\|dump(' app/
grep -n "Route::.*{[a-z]" routes/web.php | grep -v ':uuid'
```

Do **not** run the full test suite without first confirming the sqlite `:memory:` pins are still in
`phpunit.xml` — see [laravel-testing](../laravel-testing/SKILL.md).

## Step 5 — report honestly

- State what you ran and what it said. If tests failed, show the output.
- Name anything you deliberately skipped and why.
- If a 🔴 standard genuinely can't be met, stop and state:
  `CONFLICT: [standard] cannot be met because [reason]. Proposed alternative: [alternative].`
  Then follow the Exception Process in
  [ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md) — never silently skip.

## Git hygiene

Atomic commits with clear messages; no unrelated changes; no `.env`, IDE configs, or lock-file churn you
didn't intend. Commit or push **only when the user asks**, and never straight to `main` without asking.
