<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Roles the code refers to by name. They stay global (business_id NULL)
     * and are shared by every business; all other roles belong to one.
     *
     * @var array<int, string>
     */
    private array $systemRoles = ['super-admin', 'admin'];

    /**
     * Turn on Spatie "teams", with businesses as the teams: each business gets
     * its own roles, so an admin editing a role only changes it for their
     * business. Equivalent of the package's add_teams_fields migration, plus
     * moving the existing data into the existing businesses.
     *
     * On a fresh install the permission tables migration already created the
     * team columns (config('permission.teams') is now true), so the schema part
     * only runs on databases created before this change.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'business_id')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unsignedBigInteger('business_id')->nullable()->after('id');
                $table->index('business_id', 'roles_team_foreign_key_index');
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique(['business_id', 'name', 'guard_name']);
            });

            // 0 = "no business" (a super-admin): the column is part of the
            // assignment and must not be null
            foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
                Schema::table($pivot, function (Blueprint $table) use ($pivot): void {
                    $table->unsignedBigInteger('business_id')->default(0);
                    $table->index('business_id', "{$pivot}_team_foreign_key_index");
                });
            }
        }

        $this->moveExistingRolesIntoBusinesses();
    }

    public function down(): void
    {
        // One-way: once roles are per business there is no single global
        // role to fold them back into.
    }

    /**
     * Each assignment takes its user's business. Non-system roles go to the
     * first business; every other business that uses one gets its own copy
     * (same name and permissions) and its users are moved onto the copy.
     */
    private function moveExistingRolesIntoBusinesses(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
            DB::table($pivot)
                ->where('model_type', 'App\\Models\\User')
                ->update(['business_id' => DB::raw('COALESCE((SELECT users.business_id FROM users WHERE users.id = '.$pivot.'.model_id), 0)')]);
        }

        $firstBusinessId = DB::table('businesses')->orderBy('id')->value('id');

        if ($firstBusinessId === null) {
            return;
        }

        $roles = DB::table('roles')
            ->whereNull('business_id')
            ->whereNotIn('name', $this->systemRoles)
            ->get();

        foreach ($roles as $role) {
            DB::table('roles')->where('id', $role->id)->update(['business_id' => $firstBusinessId]);

            $otherBusinessIds = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->whereNotIn('business_id', [0, $firstBusinessId])
                ->distinct()
                ->pluck('business_id');

            foreach ($otherBusinessIds as $businessId) {
                $copyId = DB::table('roles')->insertGetId([
                    'business_id' => $businessId,
                    'uuid' => (string) Str::uuid(),
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $permissionIds = DB::table('role_has_permissions')->where('role_id', $role->id)->pluck('permission_id');

                DB::table('role_has_permissions')->insert(
                    $permissionIds->map(fn ($permissionId): array => ['role_id' => $copyId, 'permission_id' => $permissionId])->all()
                );

                DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('business_id', $businessId)
                    ->update(['role_id' => $copyId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
