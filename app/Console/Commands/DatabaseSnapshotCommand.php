<?php

namespace App\Console\Commands;

use App\Support\DatabaseSnapshot;
use Illuminate\Console\Command;
use Throwable;

/**
 * Takes a snapshot of a database before a migration or import (CLAUDE.md,
 * live-data rule). Read-only, so it's safe against the development database
 * (UAT). Snapshots go to storage/app/snapshots/, which git ignores: they can
 * hold real data.
 */
class DatabaseSnapshotCommand extends Command
{
    protected $signature = 'db:snapshot
                            {--database= : Connection to snapshot (defaults to the .env database)}
                            {--path= : Where to write it (defaults to storage/app/snapshots/DATABASE-TIME.sql.gz)}';

    protected $description = 'Write a restorable snapshot of a MySQL/MariaDB database (read-only)';

    public function handle(DatabaseSnapshot $snapshots): int
    {
        $connection = $this->option('database') ?: config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $path = $this->option('path')
            ?: storage_path('app/snapshots/'.$database.'-'.now()->format('Ymd-His').'.sql.gz');

        try {
            $counts = $snapshots->dump($connection, $path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Snapshot of {$database} ({$connection}): ".count($counts).' tables, '.array_sum($counts).' rows.');
        $this->line($path);

        return self::SUCCESS;
    }
}
