<?php

use App\Support\SchemaSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The consolidated migrations (stage 1a, decision 4.7).
 *
 * tests/Fixtures/legacy-schema/{sqlite,mysql}.json hold the schema main's
 * 104 migrations built (main at 1aea0e3). Step 1 proved the consolidated
 * migrations built exactly that schema; the SaaS baseline adds to it and must
 * keep every legacy column (until stage 1b moves stock), so the legacy import
 * can carry every value across. The import checks its source against them.
 *
 * The fixtures were recorded with main's migrations, once per engine:
 *   php artisan test --group=legacy-schema-record
 *   vendor/bin/pest --configuration=phpunit.mysql.xml --group=legacy-schema-record
 */
const LEGACY_SCHEMA_DIR = __DIR__.'/../Fixtures/legacy-schema';

function legacySchemaFixture(): string
{
    return LEGACY_SCHEMA_DIR.'/'.DB::getDriverName().'.json';
}

/**
 * Legacy columns the SaaS baseline changes on purpose, with the reason.
 */
const INTENDED_COLUMN_CHANGES = [
    // Business records no longer disappear with the user who made them; the
    // reference is cleared instead (Phase 1 schema).
    'cash_registers.user_id' => 'nullable',
    'expense_approval_history.user_id' => 'nullable',
];

it('keeps every legacy table and column, so the import can carry all data across', function () {
    $legacy = json_decode(file_get_contents(legacySchemaFixture()), true);
    $current = SchemaSnapshot::of();
    $lost = [];

    foreach ($legacy as $table => $definition) {
        foreach ($definition['columns'] as $column => $was) {
            $now = $current[$table]['columns'][$column] ?? null;

            if ($now === null) {
                $lost[] = "{$table}.{$column}: removed";
            } elseif ($now['type'] !== $was['type']) {
                $lost[] = "{$table}.{$column}: type {$was['type']} became {$now['type']}";
            } elseif ($now['nullable'] !== $was['nullable'] && (INTENDED_COLUMN_CHANGES["{$table}.{$column}"] ?? null) !== 'nullable') {
                $lost[] = "{$table}.{$column}: nullable changed";
            }
        }
    }

    expect($lost)->toBe([], "Legacy columns changed:\n".implode("\n", $lost));
});

describe('the empty-database guard', function () {
    beforeEach(function () {
        // A scratch database of its own, so the guard sees exactly what each
        // test puts there.
        config(['database.connections.guard_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge('guard_testing');
    });

    function migrateGuardDatabase(): int
    {
        // The migrator doesn't switch the default connection back when a
        // migration throws; left on guard_testing, the test's own transaction
        // would never be rolled back and would lock the test database.
        $default = DB::getDefaultConnection();

        try {
            return Artisan::call('migrate', ['--database' => 'guard_testing', '--force' => true]);
        } finally {
            DB::setDefaultConnection($default);
        }
    }

    it('builds the baseline on an empty database', function () {
        expect(migrateGuardDatabase())->toBe(0)
            ->and(Schema::connection('guard_testing')->hasTable('sales'))->toBeTrue();
    });

    it('refuses a database that already has tables', function () {
        Schema::connection('guard_testing')->create('sales', fn ($table) => $table->id());

        expect(fn () => migrateGuardDatabase())->toThrow(RuntimeException::class, 'Refusing to build the SaaS baseline');
        expect(Schema::connection('guard_testing')->hasTable('shops'))->toBeFalse();
    });

    it('lets later migrations run once the baseline is in place', function () {
        migrateGuardDatabase();

        expect(migrateGuardDatabase())->toBe(0)
            ->and(Artisan::output())->toContain('Nothing to migrate');
    });

    it('builds over existing tables only when forced', function () {
        Schema::connection('guard_testing')->create('legacy_notes', fn ($table) => $table->id());
        config(['database.baseline_force' => true]);

        expect(migrateGuardDatabase())->toBe(0)
            ->and(Schema::connection('guard_testing')->hasTable('sales'))->toBeTrue();
    });
});

it('records the legacy schema', function () {
    @mkdir(LEGACY_SCHEMA_DIR, 0755, true);
    file_put_contents(legacySchemaFixture(), json_encode(SchemaSnapshot::of(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    expect(file_exists(legacySchemaFixture()))->toBeTrue();
})->group('legacy-schema-record');
