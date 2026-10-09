---
name: laravel-testing
description: Testing standards and database-safety rules for this POS codebase — Pest 4 conventions, where Feature vs Unit tests go, the global RefreshDatabase trait and which database it actually targets, safe ways to run a subset of the suite, factories, mocking external integrations, and what coverage is required per change type. Use when writing or running tests, adding a factory, reproducing a bug, or before declaring any change done.
---

# Testing (POS)

Sources: [safe-testing-guide.md](../../../.ai/general/safe-testing-guide.md), testing rows in
[ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md) and
[PR_CHECKLIST.md](../../../.ai/general/PR_CHECKLIST.md).

Stack: **Pest 4** + PHPUnit 12, ~60 test files, **54 factories** in
[database/factories/](../../../database/factories/).

## 🔴 The database-safety rule (never overridden)

**Never run a test that drops, truncates, or wipes a real database — terminate it before it starts.**
`migrate:fresh`/`migrate --fresh` are forbidden outright. This matters more here than in a typical
project because the dev `.env` points `DB_HOST` at a **remote shared server**, not localhost.

## What the config actually does today

[tests/Pest.php](../../../tests/Pest.php) applies `RefreshDatabase` **globally to every Feature test**:

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```

`RefreshDatabase` runs `migrate:fresh` on the first test — it drops every table. The thing that keeps
that safe is [phpunit.xml](../../../phpunit.xml), which **does** pin the test connection to a throwaway
in-memory database:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

⚠️ **[safe-testing-guide.md](../../../.ai/general/safe-testing-guide.md) is out of date on this point** —
it says these lines are commented out. They are not, and the same file's project-specific claims (an
`aar_online_test` database, `test-aims-*.php` scripts) belong to a different project. Its *strategies*
are still sound; its status table is stale.

Because the entire safety of the Feature suite rests on those two lines, treat them as load-bearing:

- **Never remove, comment out, or override them**, and don't add a `.env.testing` that points at a real
  MySQL/MariaDB database — that would aim `migrate:fresh` at real data.
- If you change anything about the test environment, re-verify the target connection **before** running
  Feature tests.
- phpunit.xml also pins `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`,
  `MAIL_MAILER=array` — keep those; they're what make tests deterministic and side-effect-free.

## Running tests

```bash
php artisan test --compact                      # full suite
php artisan test --filter='creates a sale'      # by name
php artisan test tests/Feature/SaleControllerTest.php   # one file
php artisan test --testsuite=Unit               # unit only, no DB
```

Prefer a `--filter` or a single file while iterating — it's faster and keeps the blast radius small.
Before running the full suite the first time in a session, confirm the sqlite pins are still in
`phpunit.xml`.

## Where tests go

| Kind | Location | Gets `RefreshDatabase` | Use for |
|---|---|---|---|
| Feature | `tests/Feature/` | **Yes** (global, in-memory sqlite) | HTTP/route/controller/policy flows, API endpoints |
| Unit | `tests/Unit/` | No | Services, actions, enums, helpers, payload builders, pure logic |

`tests/Unit/` mirrors app structure (`Unit/Services/`, `Unit/Actions/`, `Unit/Models/`, `Unit/Http/`,
`Unit/Support/`). A Unit test needing app context but no DB adds `uses(Tests\TestCase::class);` at the
top of the file. Prefer a Unit test whenever the logic doesn't genuinely need HTTP or the database.

`tests/TestCase.php` is bare — put shared setup in the test file or a Pest helper rather than bloating
it.

Note: `tests/integration-test-script.php`, `run-integration-tests.php`, and
`run-product-sync-tests.php` are standalone scripts, not part of the suite. They run against whatever
`.env` says — i.e. the **remote** database. Read them before running them, and never run one that
writes.

## Creating tests

```bash
php artisan make:test --pest SaleReturnTest          # Feature
php artisan make:test --pest --unit PricingRuleTest  # Unit
```

Pest style, matching existing files:

```php
it('rejects a sale item quantity above the cap', function () {
    $user = User::factory()->create(['shop_id' => Shop::factory()->create()->id]);

    $this->actingAs($user)
        ->postJson(route('api.sales.store'), ['items' => [['quantity' => 99999]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.quantity');
});
```

- Use factories, never hand-built fixtures or reliance on existing rows. Add a factory for any new
  model (54 already exist — follow their shape).
- Arrange / act / assert; specific assertions (`assertJsonValidationErrors`, `assertDatabaseHas`,
  `assertForbidden`), not just `assertSuccessful()`.
- Names describe behaviour: `it('blocks a cashier from voiding another shop's sale')`.
- **Mock external integrations** — `Http::fake()` for WooCommerce/Shopify/Meta/WhatsApp/AI,
  `Queue::fake()`/`Bus::fake()` for dispatch assertions, `Notification::fake()`, `Mail::fake()`. A test
  must never hit a live API.

## Required coverage

Per [ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md):

- **New feature**: unit + feature tests, including **edge cases and negative cases** (🔴), plus security
  tests (🔴) — unauthorized access, cross-shop access, validation rejection.
- **Bug fix**: a regression test that fails before the fix (🔴 feature test).
- **API change**: unit + feature + integration + edge + negative + security (all 🔴).
- **Refactor**: existing tests must still pass; add tests for newly extracted units.

For this codebase specifically, the security tests worth writing every time: a user from shop A cannot
read/modify shop B's records; a role without the permission gets 403; the endpoint rejects
over-cap/negative amounts.

## Rules

- **Do not delete or skip existing tests** without approval. 60 test files exist; if one breaks,
  understand why.
- Report results honestly — if tests fail, show the output; if you skipped the suite, say so.
- Run `vendor/bin/pint` before finishing, and the tests covering what you touched.

## Forbidden

- ❌ Anything that wipes/truncates/drops a real database
- ❌ Removing or overriding the sqlite `:memory:` pins in `phpunit.xml`
- ❌ Adding a `.env.testing` that points at a real database
- ❌ Adding `RefreshDatabase` to a test that runs against a real connection
- ❌ Tests that call live external APIs
- ❌ Tests depending on pre-existing rows instead of factories
- ❌ Deleting tests to make a suite pass
