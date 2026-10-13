<?php

namespace App\Console\Commands;

use App\Support\DatabaseSnapshot;
use Illuminate\Console\Command;
use Throwable;

/**
 * Replaces a test database with a snapshot, to roll back a rehearsed
 * migration or import.
 *
 * Restoring empties the target first, so it's only allowed into a test
 * database: the connection and the database must both end in "_testing", the
 * environment must be local or testing, and --yes must be given. Every other
 * database is treated as live (CLAUDE.md); restoring one is for the user to do
 * by hand. The target is snapshotted first.
 */
class RestoreDatabaseSnapshotCommand extends Command
{
    protected $signature = 'db:restore-snapshot
                            {file : The snapshot (.sql.gz) to restore}
                            {--database= : Connection to restore into (must end in _testing)}
                            {--yes : Confirm that the target database is emptied and replaced}';

    protected $description = 'Restore a snapshot into a _testing database (never a live one)';

    public function handle(DatabaseSnapshot $snapshots): int
    {
        $connection = (string) $this->option('database');
        $database = (string) config("database.connections.{$connection}.database");

        if ($refusal = $this->refusal($connection, $database)) {
            $this->error($refusal);

            return self::FAILURE;
        }

        try {
            $snapshots->header($this->argument('file'));

            $backup = storage_path('app/snapshots/'.$database.'-before-restore-'.now()->format('Ymd-His').'.sql.gz');
            $snapshots->dump($connection, $backup);
            $this->line("Target snapshotted first: {$backup}");

            $snapshots->restore($connection, $this->argument('file'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored {$this->argument('file')} into {$database} ({$connection}); row counts match the snapshot.");

        return self::SUCCESS;
    }

    private function refusal(string $connection, string $database): ?string
    {
        return match (true) {
            ! app()->environment(['local', 'testing']) => 'Refusing: restoring is only allowed in the local or testing environment.',
            $connection === '' => 'Refusing: name the target with --database=<connection ending in _testing>.',
            config("database.connections.{$connection}") === null => "Refusing: there is no '{$connection}' connection.",
            ! str_ends_with($connection, '_testing') || ! str_ends_with($database, '_testing') => "Refusing: '{$connection}' ({$database}) isn't a test database. Only connections and databases ending in _testing may be restored into; every other database is treated as live.",
            ! $this->option('yes') => "Refusing: restoring empties {$database} first. Re-run with --yes to confirm.",
            default => null,
        };
    }
}
