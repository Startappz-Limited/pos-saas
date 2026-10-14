<?php

use App\Support\DatabaseSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * db:snapshot and db:restore-snapshot (stage 0, step 5). The round trip runs
 * on the rehearsal_testing scratch database, never the test database itself:
 * a restore drops tables, which would break RefreshDatabase's transaction.
 */
function snapshotPath(string $name): string
{
    return storage_path('app/snapshots/tests/'.$name.'-'.uniqid().'.sql.gz');
}

afterEach(function () {
    foreach (glob(storage_path('app/snapshots/tests/*.sql.gz')) ?: [] as $file) {
        unlink($file);
    }
});

describe('round trip on MySQL', function () {
    beforeEach(function () {
        // Pest runs this before a describe-level skip, so skip here first.
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL: runs with phpunit.mysql.xml.');
        }

        // Probe tables of its own, so the test works whatever the scratch
        // database holds (it may contain a restored copy of UAT).
        $this->db = DB::connection('rehearsal_testing');
        $schema = Schema::connection('rehearsal_testing');
        foreach (['snapshot_probe_values', 'snapshot_probe_flags', 'snapshot_extra'] as $table) {
            $schema->dropIfExists($table);
        }
        $schema->create('snapshot_probe_values', function ($table) {
            $table->string('key')->primary();
            $table->mediumText('value')->nullable();
            $table->integer('position');
        });
        $schema->create('snapshot_probe_flags', function ($table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active');
            $table->json('settings')->nullable();
        });

        $this->db->table('snapshot_probe_values')->insert([
            ['key' => 'tricky', 'value' => "semi;colon\nnew line -- comment 'quote' \"double\" back\\slash ñ 🙂", 'position' => 1],
            ['key' => 'marker', 'value' => "a\n".DatabaseSnapshot::STATEMENT_END."\nb", 'position' => 2],
            ['key' => 'empty', 'value' => '', 'position' => 3],
            ['key' => 'null', 'value' => null, 'position' => 4],
        ]);
        $this->db->table('snapshot_probe_flags')->insert([
            ['name' => 'on', 'is_active' => true, 'settings' => json_encode(['a' => 1])],
            ['name' => 'off', 'is_active' => false, 'settings' => null],
        ]);
    });

    it('restores a database exactly as it was snapshotted', function () {
        $snapshots = app(DatabaseSnapshot::class);
        $before = $snapshots->checksums('rehearsal_testing');
        $path = snapshotPath('round-trip');

        $this->artisan('db:snapshot', ['--database' => 'rehearsal_testing', '--path' => $path])->assertSuccessful();

        // Change it every way a rehearsal might: delete, add, edit, add a table.
        $this->db->table('snapshot_probe_values')->where('key', 'empty')->delete();
        $this->db->table('snapshot_probe_values')->insert(['key' => 'new', 'value' => 'x', 'position' => 9]);
        $this->db->table('snapshot_probe_flags')->where('name', 'on')->update(['is_active' => false]);
        Schema::connection('rehearsal_testing')->create('snapshot_extra', fn ($table) => $table->id());
        expect($snapshots->checksums('rehearsal_testing'))->not->toBe($before);

        $this->artisan('db:restore-snapshot', ['file' => $path, '--database' => 'rehearsal_testing', '--yes' => true])
            ->assertSuccessful();

        expect($snapshots->checksums('rehearsal_testing'))->toBe($before)
            ->and(Schema::connection('rehearsal_testing')->hasTable('snapshot_extra'))->toBeFalse()
            ->and($this->db->table('snapshot_probe_values')->where('key', 'tricky')->value('value'))
            ->toBe("semi;colon\nnew line -- comment 'quote' \"double\" back\\slash ñ 🙂")
            ->and($this->db->table('snapshot_probe_values')->where('key', 'null')->value('value'))->toBeNull()
            ->and($this->db->table('snapshot_probe_values')->where('key', 'empty')->value('value'))->toBe('')
            ->and((bool) $this->db->table('snapshot_probe_flags')->where('name', 'on')->value('is_active'))->toBeTrue();
    });

    it('records the row count of every table in the snapshot header', function () {
        $path = snapshotPath('header');

        app(DatabaseSnapshot::class)->dump('rehearsal_testing', $path);

        $header = app(DatabaseSnapshot::class)->header($path);
        expect($header['database'])->toBe('pos_saas_rehearsal_testing')
            ->and($header['tables']['snapshot_probe_values'])->toBe(4)
            ->and($header['tables']['snapshot_probe_flags'])->toBe(2);
    });

    it('snapshots the target before restoring into it', function () {
        $path = snapshotPath('source');
        app(DatabaseSnapshot::class)->dump('rehearsal_testing', $path);
        $backups = glob(storage_path('app/snapshots/pos_saas_rehearsal_testing-before-restore-*.sql.gz')) ?: [];

        $this->artisan('db:restore-snapshot', ['file' => $path, '--database' => 'rehearsal_testing', '--yes' => true])
            ->assertSuccessful();

        $new = array_diff(glob(storage_path('app/snapshots/pos_saas_rehearsal_testing-before-restore-*.sql.gz')) ?: [], $backups);
        expect($new)->toHaveCount(1);
        array_map('unlink', $new);
    });
});

describe('refusals', function () {
    it('refuses to restore without naming a target', function () {
        $this->artisan('db:restore-snapshot', ['file' => 'x.sql.gz', '--yes' => true])
            ->expectsOutputToContain('name the target with --database=')
            ->assertFailed();
    });

    it('refuses to restore into a database that isn\'t a test database', function (string $connection, string $database) {
        config(["database.connections.{$connection}" => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $database, 'username' => 'nobody', 'password' => '',
        ]]);

        $this->artisan('db:restore-snapshot', ['file' => 'x.sql.gz', '--database' => $connection, '--yes' => true])
            ->expectsOutputToContain("isn't a test database")
            ->assertFailed();
    })->with([
        'the development connection' => ['mysql', 'fitness'],
        'a _testing connection pointing at a live database' => ['sneaky_testing', 'fitness'],
        'a live connection pointing at a test database' => ['sneaky', 'pos_saas_testing'],
    ]);

    it('refuses to restore without --yes', function () {
        $this->artisan('db:restore-snapshot', ['file' => 'x.sql.gz', '--database' => 'rehearsal_testing'])
            ->expectsOutputToContain('Re-run with --yes')
            ->assertFailed();
    });

    it('refuses to restore outside local and testing', function () {
        app()['env'] = 'production';

        $this->artisan('db:restore-snapshot', ['file' => 'x.sql.gz', '--database' => 'rehearsal_testing', '--yes' => true])
            ->expectsOutputToContain('only allowed in the local or testing environment')
            ->assertFailed();
    });

    it('refuses an unknown connection', function () {
        $this->artisan('db:restore-snapshot', ['file' => 'x.sql.gz', '--database' => 'nothing_testing', '--yes' => true])
            ->expectsOutputToContain("there is no 'nothing_testing' connection")
            ->assertFailed();
    });

    it('fails on a missing snapshot without touching the target', function () {
        $this->artisan('db:restore-snapshot', ['file' => '/no/such/file.sql.gz', '--database' => 'rehearsal_testing', '--yes' => true])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    });

    it('refuses to snapshot a non-MySQL database', function () {
        $this->artisan('db:snapshot', ['--database' => 'sqlite', '--path' => snapshotPath('sqlite')])
            ->expectsOutputToContain('MySQL and MariaDB only')
            ->assertFailed();
    });
});
