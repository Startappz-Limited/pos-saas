<?php

namespace App\Console\Commands;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rolls permissions out (stage 10, step 0): creates the missing permissions,
 * gives each role template its listed ones and each business's copy its
 * template's, minus what was taken off on purpose (`role_revoked_permissions`),
 * then clears the permission cache. Adds only; a second run changes nothing.
 *
 * Deploy order: `php artisan migrate`, then this, never the reverse: the
 * migration that created `role_revoked_permissions` recorded every business's
 * removals before any sync could give them back.
 */
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
        {--database= : The database connection to sync (the default connection otherwise)}';

    protected $description = 'Create missing permissions and roll them out to every role template and business copy';

    public function handle(): int
    {
        $options = array_filter(['--force' => true, '--database' => $this->option('database')], fn ($value) => $value !== null);

        foreach ([PermissionSeeder::class, RoleSeeder::class] as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, ...$options]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Permissions are in sync.');

        return self::SUCCESS;
    }
}
