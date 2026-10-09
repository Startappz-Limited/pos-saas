---
description: Database migration development with schema changes and data integrity. Use when creating migrations, modifying tables, adding columns, creating indexes, or when user mentions migration, schema, database change, or table structure."
applyTo: "**/migrations/**"
---

# Migration Guidelines

## Critical Rules

**When modifying existing columns, you MUST include ALL attributes:**

```php
// ❌ Wrong - other attributes will be lost
Schema::table('products', function (Blueprint $table) {
    $table->decimal('price', 10, 2)->nullable()->change();
});

// ✅ Correct - preserves all attributes
Schema::table('products', function (Blueprint $table) {
    $table->decimal('price', 10, 2)->unsigned()->nullable()->after('name')->change();
});
```

## Migration Structure

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->unsigned();
            $table->integer('quantity')->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['shop_id', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

## Column Types for Common Data

- **Money**: `$table->decimal('amount', 10, 2)->unsigned()`
- **Foreign Keys**: `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`
- **Status/Enum**: `$table->string('status')` with validation in model
- **Quantity**: `$table->integer('quantity')->unsigned()->default(0)`
- **Flags**: `$table->boolean('is_active')->default(true)`

## Indexes

Add indexes for:

- Foreign keys (usually automatic)
- Columns used in WHERE clauses
- Columns used in ORDER BY
- Composite indexes for common query patterns

```php
$table->index(['shop_id', 'created_at']);
$table->unique(['email', 'shop_id']);
```

## Multi-Tenancy Pattern

All shop-scoped tables MUST have:

```php
$table->foreignId('shop_id')->constrained()->cascadeOnDelete();
$table->index('shop_id'); // Auto-added with foreign key
```

## Data Migrations

For data changes, create separate migrations:

```php
public function up(): void
{
    DB::table('users')
        ->whereNull('shop_id')
        ->update(['shop_id' => 1]);
}
```

## Testing Migrations

Run migrations in test:

```php
php artisan migrate:fresh --seed
php artisan migrate:rollback
php artisan migrate
```
