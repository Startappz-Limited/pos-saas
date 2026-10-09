---
name: laravel-database
description: Database and Eloquent model standards for this POS codebase — migration structure and safety, identifier strategy (integer PK + uuid route key), mandatory base and audit columns, enum-backed status fields, foreign keys and indexes, soft deletes, naming conventions, casts, and fillable. Use when writing or reviewing a migration, adding a column or table, creating or editing an Eloquent model, defining relationships or scopes, or changing anything about schema or data integrity.
---

# Database & Model Standards (POS)

Source: [0.3 database_and_data_design_guide.md](../../../.ai/general/0.3%20database_and_data_design_guide.md).
Database changes are 🔴 mandatory-compliance work in
[ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md).

## 🔴 Hard safety rules — never violated

- **NEVER** `php artisan migrate:fresh`, `migrate --fresh`, or anything that drops/truncates tables —
  local **or** production. The dev `.env` points `DB_HOST` at a **remote shared server**, so "it's
  just local" is not true here.
- **NEVER** `migrate --force` against production without explicit reviewed approval.
- `db:seed` only when explicitly asked, and only the named class (`--class=...`) — never the full
  `DatabaseSeeder` blindly.
- Assume real data. Back up before any schema change.
- Never edit a shipped migration — add a new additive one.

## Identifier strategy — match what shipped

⚠️ This is the biggest doc-vs-code divergence. [0.3](../../../.ai/general/0.3%20database_and_data_design_guide.md)
§3.2 describes a dual system and the [laravel-standards](../laravel-standards/SKILL.md) skill states
UUID primary keys as the target. **The shipped tables use:**

```php
$table->id();                        // BIGINT auto-increment PK — internal joins/FKs
$table->uuid('uuid')->unique();      // external/public reference
$table->index('uuid');
```

with `getRouteKeyName(): string { return 'uuid'; }` on the model. Keep doing that when you add a table
to an existing module — 93 migrations and the Flutter client depend on it.

- Integer `id` for joins and foreign keys; `uuid` for URLs, APIs, and public references.
- **Never expose the integer `id`** in a URL, API payload, or public link (enumeration attack).
- Only use UUID primary keys / `HasUuids` for a genuinely new, self-contained subsystem — and never
  mix UUID and integer FK types in one relationship. If you think a new table warrants UUID PKs,
  surface the conflict (`CONFLICT:` protocol in [laravel-standards](../laravel-standards/SKILL.md))
  rather than mixing patterns.
- Do **not** convert an existing integer-PK table to UUID PKs.

## Mandatory columns

Every table:

| Column | Notes |
|---|---|
| `id` | Auto-increment PK |
| `uuid` | Unique + indexed; external reference |
| `status` | Enum-backed where the entity has domain state |
| `created_at` / `updated_at` | `$table->timestamps()` — mandatory |
| `created_by` / `updated_by` | `foreignId(...)->nullable()->constrained('users')` — common in this repo |
| `deleted_at` | Soft deletes where justified (recovery/regulatory need) |

## Migrations

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();

            $table->decimal('amount', 10, 2);
            $table->integer('quantity');
            $table->string('status')->default(StockTransferStatus::Pending->value);

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->index('uuid');
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
```

Rules:
- Anonymous class syntax; **both `up()` and `down()`** (`down()` is 🔴 mandatory).
- One table per migration; no conditional or environment-specific logic.
- `foreignId(...)->constrained()` with an explicit `onDelete` intent; nullable FKs need a reason.
- Money is `decimal(10, 2)`. Stock quantities are integers.
- No JSON columns for core domain data (`shops.settings`-style config is the justified exception —
  and note JSON predicates cannot use an index; see [laravel-performance](../laravel-performance/SKILL.md)).

## Indexes

- 🔴 Mandatory on foreign keys, `uuid`, and every column a query actually filters or sorts on.
- Composite index order must match query order — `['shop_id', 'created_at']`, as `sales` and
  `stock_movements` already do.
- Index for read paths, not write convenience.

## Models

Match sibling models (e.g. [app/Models/Sale.php](../../../app/Models/Sale.php)):

```php
class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['shop_id', 'product_id', 'quantity', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'status' => StockTransferStatus::class,
        'amount' => 'decimal:2',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
```

- `$fillable` on **every** model (mass-assignment protection — security rule).
- Enum-cast status/type fields; **no magic status strings** anywhere outside an enum in
  [app/Enums/](../../../app/Enums/). Enum cases are TitleCase.
- Casts: existing models use the **`$casts` property** — match them. (The docs ask for a `casts()`
  method; that's the target for new subsystems only.)
- Typed relationship return types; scopes for common filters.
- Generate the `uuid` the way siblings do (observer/booted hook or explicit assignment) — a new model
  that forgets it will break route binding.
- No business logic in models — that belongs in a service (see
  [laravel-architecture](../laravel-architecture/SKILL.md)).

## Stock and money integrity (project-specific)

- Never mutate `stock_quantity` ad hoc. Stock changes flow through `InventoryService` /
  `StockMovement`, and cost layers through `CostingService` (COGS depends on it).
- Multi-step writes (sale + items + stock movements + register entries) belong in one
  `DB::transaction`.
- Audit-relevant tables must stay audit-compatible: `uuid` present, status changes trackable,
  `created_by`/`updated_by` populated (see [laravel-audit](../laravel-audit/SKILL.md)).

## Naming

| Element | Convention |
|---|---|
| Table | plural snake_case |
| Column | snake_case |
| Pivot table | both names singular, alphabetical (`product_shop`) |
| Enum class | StudlyCase; cases TitleCase; values lowercase strings |

## Forbidden

- ❌ `migrate:fresh` / anything destructive
- ❌ Editing or renaming columns in a shipped migration
- ❌ Migration without `down()`
- ❌ Exposing integer `id` externally; missing `uuid`
- ❌ Magic status strings instead of enums
- ❌ Missing `$fillable`
- ❌ New FK or filtered column with no index
- ❌ Converting existing integer-PK tables to UUID PKs
- ❌ Raw SQL in migrations unless genuinely unavoidable
