<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Role;
use App\Support\DefaultRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the roles.
     *
     * super-admin and admin are global system roles shared by every business,
     * and both hold every permission (new permissions are granted to them as
     * they are created; see AppServiceProvider). The other roles are per
     * business: each business gets its own copy from DefaultRoles, which a new
     * business also receives when it is created. Safe to re-run: it only adds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([Role::SUPER_ADMIN, Role::ADMIN] as $name) {
            Role::global($name)->givePermissionTo(Permission::all());
        }

        Business::query()->each(fn (Business $business) => DefaultRoles::seedFor($business));

        $this->command?->info('✅ Seeded the system roles and the default roles of '.Business::count().' business(es).');
    }
}
