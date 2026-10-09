---
name: laravel-audit
description: Audit-logging standards for this POS codebase — the isolated audit database connection, mandatory audit columns, immutable append-only records, event/observer-driven capture (never controller calls), enum-backed status, sensitive-data masking, failure handling that must not break business flow, retention, and read access control. Use when adding audit logging to an operation, building out the AuditLog/AuditService/AuditLogController scaffolding, or changing anything that records who did what to money, stock, users, roles, or permissions.
---

# Audit Logging (POS)

Source: [0.5 audit_log_guide.md](../../../.ai/general/0.5%20audit_log_guide.md). Audit logging is
🔴 mandatory for new features, database changes, and API changes
([ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md)).

> Audit data is immutable, isolated, UUID-traceable, captured automatically, and an audit failure must
> never break the business flow.

## Deployment — the step that is easy to miss

The audit connection has its **own migration repository**, so `php artisan migrate` does **not** create
`audit_logs`. Every environment must run it explicitly:

```bash
touch database/audit.sqlite          # gitignored; provision per environment
php artisan migrate --database=audit --path=database/migrations/audit
```

Never put an audit migration in `database/migrations/` directly: it would be recorded in the shared
(main-database) repository, so a second environment would see it as already applied and silently never
create the table — and because audit writes fail soft, nothing would surface but log lines.

## Status: implemented

This was empty scaffolding and is now built:

| Piece | State |
|---|---|
| `audit` connection (SQLite, own migration repository) | ✅ |
| [database/migrations/audit/](../../../database/migrations/audit/) | ✅ polymorphic `auditable_*`, `event`, `status` |
| [app/Models/AuditLog.php](../../../app/Models/AuditLog.php) | ✅ immutable — `save()`/`delete()` throw |
| [app/Models/Concerns/Auditable.php](../../../app/Models/Concerns/Auditable.php) | ✅ capture on 12 models |
| `AuditableEvent` + [AuditLogger](../../../app/Listeners/AuditLogger.php) | ✅ writes, sanitises, never rethrows |
| [AuditAuthorizationChanges](../../../app/Listeners/AuditAuthorizationChanges.php) | ✅ role/permission grants |
| [AuditService](../../../app/Services/AuditService.php) + `AuditLogController` | ✅ read/filter/export, gated |
| `audit-logs.*` routes + views | ✅ working |
| `audit.view` / `audit.export` / `audit.full-access` | ✅ seeded |

Regression-tested in [AuditLogTest](../../../tests/Feature/AuditLogTest.php) and
[AuditCoverageTest](../../../tests/Feature/AuditCoverageTest.php).

## Isolated connection

Audit writes go to the `audit` connection, never the main database. The driver here is SQLite, not the
MySQL the guide shows — keep SQLite (it's what's configured) but be aware of the tradeoff: SQLite
serializes writers, so audit writes must stay off the hot path and must never share a transaction with
main-DB work.

```php
// Migration
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('audit')->create('audit_logs', function (Blueprint $table) { /* ... */ });
    }

    public function down(): void
    {
        Schema::connection('audit')->dropIfExists('audit_logs');
    }
};
```

```php
class AuditLog extends Model
{
    protected $connection = 'audit';
    public $timestamps = false;   // only created_at
}
```

Add `AUDIT_DB_DATABASE` to `.env.example` when you wire this up, and make sure the SQLite file is
created and gitignored (production must provision and back it up — audit records are meant to be
durable and immutable).

## Mandatory columns

The shipped schema uses **polymorphic** naming, not entity/action:

| Column | Notes |
|---|---|
| `uuid` | Audit record id (unique) |
| `auditable_type` / `auditable_id` / `auditable_uuid` | The affected record; display the uuid |
| `event` | `AuditEvent` enum — `created`, `updated`, `deleted`, `force_deleted`, … |
| `status` | `AuditStatus` enum — `success`, `failed`, `pending` |
| `old_values` / `new_values` | JSON, sanitised |
| `user_id` / `user_name` / `user_email` | Who did it |
| `ip_address`, `user_agent`, `url`, `method` | Request context |
| `tags` | JSON labels, e.g. `["authorization"]` |
| `shop_id` | Shop context for filtering (integer — no FK across connections) |
| `created_at` | Timestamp |

Indexed on `uuid`, `user_id`, `[auditable_type, auditable_id]`, `auditable_uuid`, `event`, `created_at`,
`shop_id`, and `[auditable_type, created_at]`.

## Immutability

Audit rows are append-only. Block updates at the model level:

```php
public function save(array $options = []): bool
{
    if ($this->exists) {
        throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
    }

    return parent::save($options);
}
```

No `update()`, no soft-delete-then-rewrite, no hard deletes without an approved retention process.

## Currently wired

12 models carry `use Auditable;` (Sale, Product, User, CashRegister, Expense, StockAdjustment, Shop,
Role, CreditAccount, CreditTransaction, SaleReturn, Refund) — adding another is one line.

**Role and permission grants are pivot writes**, so no model event fires. They are captured by
`AuditAuthorizationChanges`, which listens to Spatie's `RoleAttached`/`RoleDetached`/
`PermissionAttached`/`PermissionDetached`. This requires `config('permission.events_enabled') === true` —
it is enabled; do not turn it off without removing the audit expectation.

## What must be audited

Mandatory: create / update / delete / status change, **role & permission changes**, and
authentication events. In POS terms that means at minimum: sales and returns/refunds, payments and
credit-account transactions, stock movements and adjustments, cash-register open/close, expense
approvals, user account changes, and role/permission grants.

Read access needs auditing only for high-risk modules or PII access.

## Capture via events/observers — never from controllers

```
Model event → domain event (AuditableEvent) → listener (AuditLogger) → audit DB
```

Use an observer per audited model (this repo already registers observers in `AppServiceProvider`,
e.g. `Product::observe(ProductObserver::class)`) dispatching a single `AuditableEvent`, with one
listener doing the write. `$model->getOriginal()` / `$model->getChanges()` give you old/new values.

Forbidden: writing audit rows inside controllers, scattering manual audit calls through services, or
coupling the audit write to the main DB transaction.

## Failure handling

The listener catches everything, logs it, and **never rethrows** — a broken audit sink must not fail a
sale:

```php
try {
    AuditLog::create([...]);
} catch (\Throwable $e) {
    Log::critical('Audit logging failed', [
        'auditable_uuid' => $event->auditableUuid,
        'event' => $event->event->value,
        'error' => $e->getMessage(),
    ]);
}
```

Because writes are best-effort, prefer dispatching the audit write to the queue for high-volume paths
(sales), and alert on persistent failures.

## Sensitive data

Strip `password`, `password_confirmation`, `api_token`, `api_secret`, `remember_token`, and any
integration tokens outright. Mask PII (phone, tax id, card data) to a last-4 form. Never store an
unmasked secret in `old_values`/`new_values` — the audit DB is not a safe place for plaintext.

## Read access & retention

- Reads go through a query service that checks a permission first
  (`abort_unless(auth()->user()->can('audit.view'), 403)`), and the `audit-logs.*` routes need that
  permission seeded plus a policy — see [laravel-authorization](../laravel-authorization/SKILL.md).
- The application treats the audit DB as read-only apart from the audit writer.
- Minimum retention 12 months; archive rather than delete; no hard deletes without approval.

## Forbidden

- ❌ Audit rows in the main database
- ❌ Editing or deleting audit records
- ❌ Logging integer ids instead of uuids
- ❌ Missing actor / IP / user-agent context
- ❌ Audit writes inside the main DB transaction
- ❌ Audit failures that propagate and break the request
- ❌ Unmasked secrets or PII in audit payloads
- ❌ Audit calls written directly in controllers
