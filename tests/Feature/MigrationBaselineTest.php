<?php

use App\Support\SchemaSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * The consolidated migrations (stage 1a, decision 4.7).
 *
 * tests/Fixtures/legacy-schema/{sqlite,mysql}.json hold the schema main's
 * 104 migrations built (main at 1aea0e3). The legacy import checks its source
 * against them, and until the SaaS schema lands the consolidated migrations
 * must build exactly the same.
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

it('builds the same schema as the migrations it replaced', function () {
    $legacy = json_decode(file_get_contents(legacySchemaFixture()), true);

    $differences = SchemaSnapshot::differences($legacy, SchemaSnapshot::of());

    expect($differences)->toBe([], "Schema differs from main's:\n".implode("\n", $differences));
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
        return Artisan::call('migrate', ['--database' => 'guard_testing', '--force' => true]);
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
