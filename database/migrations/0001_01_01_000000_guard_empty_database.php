<?php

use App\Support\SchemaSnapshot;
use Illuminate\Database\Migrations\Migration;

/**
 * The migrations are a clean SaaS baseline (decision 4.7), not a history
 * that can be applied over an older schema. This first migration refuses to
 * build it on a database that already has tables: data in an existing
 * database is carried across with the legacy import into a new, empty one.
 *
 * Once it has run, the migrator records it and never runs it again, so later
 * migrations apply normally. `DB_BASELINE_FORCE=true` overrides it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = SchemaSnapshot::tables($this->getConnection());

        if ($tables === [] || config('database.baseline_force')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to build the SaaS baseline: the database already has %d tables (%s). '
            .'Migrate an empty database and carry the data across with the legacy import, '
            .'or set DB_BASELINE_FORCE=true to build over these tables.',
            count($tables),
            implode(', ', array_slice($tables, 0, 5)).(count($tables) > 5 ? ', …' : ''),
        ));
    }

    public function down(): void
    {
        //
    }
};
