# Safe Testing Guide — Avoiding Database Wipes

## The Problem

This project uses **Pest** with a global `RefreshDatabase` trait applied to ALL Feature tests in `tests/Pest.php`:

```php
// tests/Pest.php (line 14-16)
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```

Additionally, `phpunit.xml` has the SQLite in-memory database **commented out**, meaning tests run against the **real MySQL database**:

```xml
<!-- phpunit.xml -->
<!-- <env name="DB_CONNECTION" value="sqlite"/> -->
<!-- <env name="DB_DATABASE" value=":memory:"/> -->
```

**This combination is dangerous**: every Feature test will wipe all tables and re-run migrations on your actual development database. This can destroy days of work.

---

## What `RefreshDatabase` Actually Does

1. **First test in the suite**: Runs `php artisan migrate:fresh` — this **drops ALL tables** and re-creates them from scratch
2. **Each subsequent test**: Wraps in a database transaction and rolls back after each test
3. **Net effect**: Your entire database is wiped clean at the start of the test run

---

## Safe Testing Strategies

### Strategy 1: Use Unit Tests (No Database)

Place tests in `tests/Unit/`. Unit tests do NOT get `RefreshDatabase` by default.

**For tests that need Laravel's app context** (config, helpers, etc.) but NOT the database, add `uses(Tests\TestCase::class)` at the top of the file:

```php
<?php
// tests/Unit/MyTest.php

use App\DataObjects\SomePayload;

// Boot the Laravel app (config(), storage_path(), etc.)
// but WITHOUT RefreshDatabase — DB is untouched
uses(Tests\TestCase::class);

it('builds a payload correctly', function () {
    expect(config('app.name'))->not->toBeEmpty();
    // ... test logic that doesn't need database
});
```

**When to use**: Testing payload builders, data transformations, config values, utility functions, API response formatting.

### Strategy 2: Dry-Run Test Scripts

Create standalone PHP scripts (like `test-aims-motor-submission.php`) that:
- Bootstrap Laravel with `require __DIR__.'/vendor/autoload.php'` and `$app = require_once __DIR__.'/bootstrap/app.php'`
- Accept a `--dry-run` flag to preview payloads without making API calls
- Read from the database but never write destructively

```bash
# Preview payload structure without any API calls or DB changes
php test-aims-motor-submission.php --dry-run

# Submit to real API (still doesn't wipe DB)
php test-aims-motor-submission.php 123 CA132
```

**When to use**: Testing end-to-end flows with real data, API payload validation, integration verification.

### Strategy 3: Use `--filter` to Run Specific Tests

Never run `php artisan test` without a filter when Feature tests exist:

```bash
# Safe — runs only Unit tests
php artisan test --testsuite=Unit

# Safe — runs only one specific file
php artisan test tests/Unit/AimsMotorIntegrationTest.php

# Safe — runs only matching test names
php artisan test --filter='builds motor payload'

# DANGEROUS — runs ALL tests including Feature (will wipe DB)
php artisan test
```

---

## How to Make `RefreshDatabase` NOT Global in Pest

### Option A: Remove Global RefreshDatabase (Recommended)

Change `tests/Pest.php` so Feature tests get the Laravel app but NOT `RefreshDatabase`:

```php
// tests/Pest.php — SAFE version

// Feature tests: Laravel app, NO automatic database wipe
pest()->extend(Tests\TestCase::class)
    ->in('Feature');

// If you create a subfolder for DB-safe tests that truly need RefreshDatabase:
// pest()->extend(Tests\TestCase::class)
//     ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
//     ->in('Feature/Database');
```

Then, only add `RefreshDatabase` to specific test files that genuinely need it:

```php
<?php
// tests/Feature/UserRegistrationTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Only THIS file gets RefreshDatabase
uses(RefreshDatabase::class);

it('registers a new user', function () {
    $response = $this->postJson('/api/register', [...]);
    $response->assertSuccessful();
});
```

### Option B: Use a Subfolder Approach

Keep the global `RefreshDatabase` but organize tests into safe/unsafe folders:

```php
// tests/Pest.php

// Feature tests WITHOUT RefreshDatabase (safe)
pest()->extend(Tests\TestCase::class)
    ->in('Feature');

// Only tests in Feature/Database get RefreshDatabase
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature/Database');
```

### Option C: Use SQLite In-Memory for Feature Tests

Uncomment the SQLite lines in `phpunit.xml` so `RefreshDatabase` wipes a throwaway in-memory database instead of your real one:

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Caveat**: Some MySQL-specific features (JSON columns, certain indexes, triggers) may not work with SQLite. Test carefully before switching.

### Option D: Use `.env.testing`

Create a `.env.testing` file that points to a separate test database:

```env
# .env.testing
DB_CONNECTION=mysql
DB_DATABASE=aar_online_test
DB_USERNAME=root
DB_PASSWORD=
```

This way `RefreshDatabase` wipes the test database, not your development database.

---

## Quick Reference: Where to Put Your Tests

| Test Type | Location | Has Laravel App? | Wipes DB? | Use For |
|-----------|----------|-----------------|-----------|---------|
| Pure Unit | `tests/Unit/` (no `uses`) | No | No | Pure PHP logic, no Laravel helpers |
| Unit + App | `tests/Unit/` with `uses(TestCase)` | Yes | No | Payload builders, config, helpers |
| Feature (safe) | `tests/Feature/` (after fixing Pest.php) | Yes | No | HTTP tests, API tests |
| Feature (DB) | `tests/Feature/` with `uses(RefreshDatabase)` | Yes | **YES** | Tests that need empty DB state |
| Dry-run scripts | Project root | Yes | No | Manual integration testing |

---

## Current Project Setup Summary

| Item | Value | Risk |
|------|-------|------|
| `tests/Pest.php` | `RefreshDatabase` global on Feature | **HIGH** — any Feature test wipes DB |
| `phpunit.xml` DB | SQLite commented out | Tests use real MySQL |
| Safe test location | `tests/Unit/` with `uses(TestCase)` | No DB risk |
| Test scripts | `test-aims-*.php --dry-run` | No DB risk |

---

## Rules of Thumb

1. **Never run `php artisan test` without `--testsuite=Unit` or `--filter`** until `RefreshDatabase` is fixed
2. **New integration tests go in `tests/Unit/`** with `uses(Tests\TestCase::class)` — NOT in `tests/Feature/`
3. **Always use `--dry-run`** first when testing API submissions with test scripts
4. **Before changing Pest.php**, make sure the team is aligned — it affects all developers
5. **Back up your database** before running any test suite that includes Feature tests
