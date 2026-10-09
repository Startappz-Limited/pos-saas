---
name: laravel-standards
description: Index and hard safety rules for the project's .ai/general engineering standards (security, database, API, routing, roles, audit, architecture, performance, testing, docs) covering ALL Laravel/PHP work in this repo, with pointers to the focused skill for each area. Use whenever creating or modifying controllers, models, migrations, routes, services, jobs, mail, Blade views, API endpoints, forms, or tests — and before opening a PR.
---

# Laravel Engineering Standards (POS)

This skill is the enforced standard for **all** PHP/Laravel work in this repository. The
authoritative detail lives in [.ai/general/](../../../.ai/general/) — this file is the actionable
summary. When this skill and a quick habit disagree, **this skill wins**.

When instructions conflict, apply this precedence order:

1. Hard safety rules (never overridden).
2. Backward-compatibility contracts.
3. `.ai/general` standards, with the Exception Process as the only override path.
4. This skill summary.
5. Quick habits.

Apply these standards to all new code and to the specific legacy code you modify, subject to the precedence order above.

> Scope note: [CLAUDE.md](../../../CLAUDE.md) documents the patterns this codebase actually shipped with
> (integer primary keys + a `uuid` route-key column, Spatie permission, flat unversioned API, simple
> `{success,data}` JSON responses). The team's stated direction is to hold genuinely new subsystems to
> the `.ai/general` standard going forward. Where a change touches existing modules, **match the sibling
> code** and bring only the specific methods or blocks being modified up to standard. Do not refactor
> unrelated methods or classes in the same file unless explicitly requested. The ONLY hard exceptions are the
> backward-compatibility contracts below (renaming them breaks mobile apps / add-ons) and the
> database-safety rules — both are non-negotiable.

---

## 📚 Focused skills — load the one that matches your task

This file is the index and the hard rules. Each area has a dedicated skill with the actual detail,
grounded in what this codebase does:

| Area | Skill | Load when |
|---|---|---|
| **Business rules — sales, stock, COGS, returns, credit, registers, pricing, attribution** | [pos-domain-rules](../pos-domain-rules/SKILL.md) | **before touching any of them** — also maps which named services/models are empty stubs |
| Security, OWASP, encryption, rate limiting | [laravel-security](../laravel-security/SKILL.md) | any route, form, endpoint, upload, or credential/PII handling |
| Migrations, models, indexes, enums | [laravel-database](../laravel-database/SKILL.md) | schema or Eloquent model work |
| JSON API, response shape, web↔API sync | [laravel-api](../laravel-api/SKILL.md) | anything under `Controllers/Api/`, `routes/api.php`, `Http/Resources/` |
| Route binding, names, middleware | [laravel-routing](../laravel-routing/SKILL.md) | adding/changing routes or middleware |
| Roles, permissions, policies, shop access | [laravel-authorization](../laravel-authorization/SKILL.md) | authorizing an action, writing a policy, seeding permissions |
| Audit logging | [laravel-audit](../laravel-audit/SKILL.md) | recording who changed money/stock/users/roles |
| E-commerce sync, webhooks, WhatsApp, AI/social drivers | [laravel-integrations](../laravel-integrations/SKILL.md) | any code calling an external API, or a webhook/sync job |
| Where code lives, clean code, layering | [laravel-architecture](../laravel-architecture/SKILL.md) | creating any new class, splitting fat classes |
| N+1, indexes, caching, chunking, queues | [laravel-performance](../laravel-performance/SKILL.md) | list/report queries, exports, observers, anything slow |
| Pest, DB safety, coverage per change type | [laravel-testing](../laravel-testing/SKILL.md) | writing or running tests |
| PHPDoc, `.env.example`, docs/ | [laravel-documentation](../laravel-documentation/SKILL.md) | new config key, endpoint, module, or enum |
| Bootstrap wiring, BS5 JS API | [bootstrap-ui](../bootstrap-ui/SKILL.md) | any Blade/UI markup |
| Design system, enum-driven badges, a11y | [ui-design-standards](../ui-design-standards/SKILL.md) | any Blade screen, badge, table, form, modal |
| Pre-merge gate, which rules apply | [pr-checklist](../pr-checklist/SKILL.md) | before finishing a change or opening a PR |

---

## 🔴 Hard safety rules — NEVER violate (from .ai/general/strict.md)

- **NEVER** run `php artisan migrate:fresh`, `migrate --fresh`, or anything that drops/truncates tables or wipes data — local OR production.
- **NEVER** run `migrate --force` against production without explicit reviewed approval.
- Run `db:seed` **only when explicitly requested**, and only the **specific seeder class** asked for (`--class=...`), never the full `DatabaseSeeder` blindly.
- Always assume a real database has real data: back up before any schema change.
- **Do not run tests that truncate/wipe/drop the database** — terminate them before they begin. Tests that need persistence must use an isolated test database or non-destructive transactions; otherwise use self-contained unit tests that do not mutate shared data.

## 🔒 Security — mandatory, no exceptions ([1.0 security_best_practices_guide.md](../../../.ai/general/1.0%20security_best_practices_guide.md), [owasp-top-10-laravel-implementation-guide.md](../../../.ai/general/owasp-top-10-laravel-implementation-guide.md))

- Auth rules: authenticate every protected route/endpoint, authorize every resource action (policy/permission check), and use `@csrf` on every state-changing web form.
- Form bot protection: for Blade forms use `@honeypot`; for Livewire components use the Livewire-compatible honeypot approach per the security guide; for API endpoints consumed by non-browser clients, honeypot is not applicable — apply rate limiting and bot-detection headers instead.
- Input/data rules: **never** `$request->all()`; validate and use `$request->validated()` (FormRequest) for every store/update; protect every model with `$fillable`.
- Query/output rules: Eloquent / parameter binding only, no raw SQL with interpolated input; Blade escaping `{{ }}` and avoid `{!! !!}` with user content.
- Operational rules: rate-limit all API endpoints and login/signup; secrets only in env vars; never log passwords/tokens/PII; sanitize logs; set security headers; `APP_DEBUG=false` in production.
- Encrypt at rest using `Crypt` any field containing PII (name, email, phone, address), payment data, credentials, or tokens. Fields used for querying/indexing should use blind-index patterns instead of direct encryption.

## 🗄️ Database & models ([0.3 database_and_data_design_guide.md](../../../.ai/general/0.3%20database_and_data_design_guide.md))

- Anonymous migration classes; **both `up()` and `down()`**.
- **UUID primary keys** and UUID foreign keys for new tables/models (`HasUuids`).
- When adding columns to an existing legacy table that uses integer primary keys, use integer foreign keys to match the existing schema. Only apply UUID foreign keys when the referenced table itself uses UUID primary keys. Do not mix UUID and integer foreign key types in the same relationship.
- Modern `casts()` method (not `$casts` property); enum casting for status/type fields.
- Indexes on foreign keys and frequently-queried columns; `created_at`/`updated_at` on every table.
- Prevent N+1: eager-load with `with()`, use `withCount()`/`withExists()`, chunk large sets.

## 🌐 API ([0.7 api_guide.md](../../../.ai/general/0.7%20api_guide.md), [1.1 web_api_sync_guide.md](../../../.ai/general/1.1%20web_api_sync_guide.md))

- Versioned routes `/api/v1/...`, RESTful verbs, Sanctum auth, rate limiting, pagination on lists.
- Standard JSON envelope: `{ success, message, data, meta }`; correct HTTP status codes.
- FormRequest validation; UUIDs in URLs, not integer IDs. Document new endpoints.

## 🛣️ Routing & 🏗️ structure ([0.8 routing_guide.md](../../../.ai/general/0.8%20routing_guide.md), [0.1 folder_structure_guide.md](../../../.ai/general/0.1%20folder_structure_guide.md))

- Routes registered/grouped by area; dot-notation names (`admin.users.index`); explicit middleware stacks.
- Folderize by area: `Controllers/{Admin,Api,Web}/`, `Services/<Domain>/`, `Jobs/<Purpose>/`, `Mail/<Domain>/`, flat `Models/`, resource-nested views.

## 🔐 Roles & 📝 audit ([0.4 roles_and_permissions_guide.md](../../../.ai/general/0.4%20roles_and_permissions_guide.md), [0.5 audit_log_guide.md](../../../.ai/general/0.5%20audit_log_guide.md))

- Authorization via policies + permissions; permission naming `{resource}.{action}`.
- Audit all state-changing operations performed by or on user accounts, payment records, and admin-privileged routes (POST/PUT/PATCH/DELETE), with user context (ID, IP, UA); immutable, sanitized audit records. Read-only admin actions (GET) do not require audit logging unless they access sensitive PII.

## 🧹 Coding standards ([0.9 coding_standards_guide.md](../../../.ai/general/0.9%20coding_standards_guide.md))

- PSR-12; type hints on all params/returns; PHPDoc on public methods.
- Methods must contain fewer than 20 lines of executable code (excluding blank lines, comments, PHPDoc, and opening/closing braces), with single responsibility; comments explain **WHY**, not WHAT.
- No `dd`/`dump`/debug code; no commented-out code; no hardcoded values; DRY.
- Business logic in Actions (single-purpose) / Services (orchestration) / Jobs (async) — not fat controllers.

## 🧪 Testing ([safe-testing-guide.md](../../../.ai/general/safe-testing-guide.md))

- New features: feature + unit tests incl. edge & negative cases, when they can run without wiping/truncating shared data. Bug fixes: a regression test.
- Tests must NOT wipe/truncate the database (see hard safety rules).

## 📄 Documentation ([0.6 documentation_guide.md](../../../.ai/general/0.6%20documentation_guide.md))

- PHPDoc blocks, update `.env.example` for new config, document new APIs, add changelog entries.

---

## ⚠️ Backward-compatibility contracts — do NOT "fix" (see [CLAUDE.md](../../../CLAUDE.md))

These are public contracts the Flutter mobile app and existing modules depend on. Standardize genuinely
new subsystems around the target standard, but do not retrofit/break these:

- **Flat, unversioned API** route paths and names (`/api/products`, `api.products.index`) already consumed
  by the mobile client — do not move them under `/api/v1` or rename them.
- **API response shape** `{"success": true, "data": ...}` (list endpoints nest the Laravel paginator in
  `data`) — do not rewrap into a different envelope on existing endpoints.
- **Integer primary keys + a separate `uuid` column** with `getRouteKeyName()` returning `uuid` — do not
  convert existing tables to UUID primary keys or change their route binding.

If a mandatory standard genuinely cannot be met, follow the **Exception Process** in
[ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md) (document reason, alternative, get approval) rather than silently skipping it.

If you identify a standards conflict mid-task that requires the Exception Process, stop generation and clearly state the conflict in this format: `CONFLICT: [standard] cannot be met because [reason]. Proposed alternative: [alternative].` Do not proceed until the user acknowledges. Do not silently apply an alternative.

## ✅ Before finishing any change

Work through [pr-checklist](../pr-checklist/SKILL.md) — it maps your change type to the standards that
are 🔴 mandatory for it and runs the gate. At minimum:

```bash
vendor/bin/pint
php artisan test --filter='<what you touched>'   # see laravel-testing before running the full suite
```

Consult [ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md) to see which standards are 🔴 mandatory for your specific change type. If ENFORCEMENT_MATRIX.md is not available in context, treat all standards in this document as mandatory (🔴) for any change type and apply the Exception Process only for the backward-compatibility contracts listed above.
