<?php

use App\Support\SchemaSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The consolidated migrations (stage 1a, decision 4.7).
 *
 * database/legacy/schema-{sqlite,mysql}.json hold the schema main's
 * 104 migrations built (main at 1aea0e3). Step 1 proved the consolidated
 * migrations built exactly that schema; the SaaS baseline adds to it and must
 * keep every legacy column (until stage 1b moves stock), so the legacy import
 * can carry every value across. The legacy import (saas:import-legacy) checks its source against them.
 *
 * They were recorded once, from main's migrations on each engine (stage 1a
 * step 1), and must not be re-recorded: the migrations now build the SaaS
 * schema.
 */
const LEGACY_SCHEMA_DIR = __DIR__.'/../../database/legacy';

function legacySchemaFixture(): string
{
    return LEGACY_SCHEMA_DIR.'/schema-'.DB::getDriverName().'.json';
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

/**
 * Legacy tables no legacy code ever wrote, rebuilt for the SaaS design: the
 * FIFO costing tables (stage 1b). They hold no data to carry.
 */
const REBUILT_LEGACY_TABLES = ['cost_layers', 'cost_allocations'];

it('keeps every legacy table and column, so the import can carry all data across', function () {
    $legacy = json_decode(file_get_contents(legacySchemaFixture()), true);
    $current = SchemaSnapshot::of();
    $lost = [];

    foreach ($legacy as $table => $definition) {
        if (in_array($table, REBUILT_LEGACY_TABLES, true)) {
            continue;
        }

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
