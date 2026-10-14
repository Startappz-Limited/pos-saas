<?php

namespace App\Console\Commands;

use App\Support\ConnectionGuard;
use Database\Seeders\LiveLikeDatasetSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Builds the live-like dataset (stage 0) into an empty test database: the
 * main schema of this branch, then LiveLikeDatasetSeeder. The same seed and
 * scale give the same rows on every run.
 *
 * Only ever into an empty database whose connection and name end in
 * "_testing": every other database is treated as live (CLAUDE.md), and the
 * development database (UAT) holds real data.
 */
class BuildDatasetCommand extends Command
{
    protected $signature = 'dataset:build
                            {--database= : Connection to build into (must end in _testing and be empty)}
                            {--scale=1.0 : Fraction of the medium volume (1.0 = 5 shops, 400 products, 3,000 sales)}';

    protected $description = 'Build the reproducible live-like dataset into an empty _testing database';

    public function handle(): int
    {
        $connection = (string) $this->option('database');
        $database = (string) config("database.connections.{$connection}.database");
        $scale = (float) $this->option('scale');

        if ($refusal = $this->refusal($connection, $database, $scale)) {
            $this->error($refusal);

            return self::FAILURE;
        }

        // A medium build keeps thousands of faked jobs and notifications in
        // memory; the CLI default (128M) isn't enough.
        ini_set('memory_limit', '1G');

        $this->isolate($connection);

        try {
            // Migrations insert some rows (default sale sources); freeze the
            // clock first so their timestamps are the same on every build.
            Carbon::setTestNow(Carbon::parse(LiveLikeDatasetSeeder::STARTS));
            Artisan::call('migrate', ['--database' => $connection, '--no-interaction' => true]);

            $seeder = app(LiveLikeDatasetSeeder::class);
            $seeder->scale = $scale;
            $seeder->setContainer(app())->setCommand($this);
            $seeder->__invoke();
        } catch (Throwable $e) {
            $this->error('Build failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Built the live-like dataset (scale {$scale}) into {$database} ({$connection}); peak memory ".round(memory_get_peak_usage(true) / 1048576).' MB.');
        $this->table(['table', 'rows'], collect(LiveLikeDatasetSeeder::SUMMARY_TABLES)
            ->map(fn (string $table): array => [$table, DB::table($table)->count()])
            ->all());

        return self::SUCCESS;
    }

    private function refusal(string $connection, string $database, float $scale): ?string
    {
        return match (true) {
            ! app()->environment(['local', 'testing']) => 'Refusing: the dataset is only built in the local or testing environment.',
            $connection === '' => 'Refusing: name the target with --database=<connection ending in _testing>.',
            config("database.connections.{$connection}") === null => "Refusing: there is no '{$connection}' connection.",
            ! str_ends_with($connection, '_testing') || ! str_ends_with($database, '_testing') => "Refusing: '{$connection}' ({$database}) isn't a test database. The dataset is only built into a connection and database ending in _testing.",
            $scale <= 0 || $scale > 5 => 'Refusing: --scale must be greater than 0 and at most 5.',
            Schema::connection($connection)->getTableListing($database, false) !== [] => "Refusing: {$database} isn't empty. The dataset is only built into an empty database; create a fresh one.",
            default => null,
        };
    }

    /**
     * The build uses the target as the default connection, and keeps
     * everything else it touches in memory: the audit trail, cache, session
     * and permission cache. Any query on another connection is blocked.
     * (Queues, mail, notifications and HTTP are faked by the seeder.)
     */
    private function isolate(string $connection): void
    {
        config([
            'database.default' => $connection,
            'database.connections.audit.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'permission.cache.store' => 'array',
        ]);
        DB::setDefaultConnection($connection);
        DB::purge('audit');

        // Spatie's registrar picked its cache store at boot (the .env
        // database's cache table); move it to memory.
        app(PermissionRegistrar::class)->initializeCache();

        // Anything still bound to another connection fails instead of
        // reaching it (the .env database is treated as live).
        ConnectionGuard::only([$connection, 'audit']);

        Artisan::call('migrate', [
            '--database' => 'audit',
            '--path' => 'database/migrations/audit',
            '--no-interaction' => true,
        ]);
    }
}
