<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Roles per decision 4.4 (stage 3).
 *
 * - The platform operator is a flag on the user, `users.is_platform_operator`:
 *   global and never a business member. (With Spatie teams every role is held
 *   inside a business, so a global role can't be a role assignment.)
 * - The business owner is the shared `admin` role, held in the business. The
 *   `super-admin` template, which owners held until now, is renamed to it, so
 *   every owner's assignment and the role's permissions carry across as they
 *   are.
 * - Each business gets its own copy of every other role template (manager,
 *   cashier, ...), with the template's permissions, and its members' template
 *   assignments move to the copies, so a business can change its roles without
 *   touching another's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_operator')->default(false)->after('status');
        });

        DB::transaction(function (): void {
            $superAdmin = DB::table('roles')->whereNull('business_id')->where('name', 'super-admin')->first();
            $admin = DB::table('roles')->whereNull('business_id')->where('name', 'admin')->first();

            if ($superAdmin !== null && $admin === null) {
                DB::table('roles')->where('id', $superAdmin->id)->update(['name' => 'admin', 'updated_at' => now()]);
            } elseif ($superAdmin !== null) {
                // Both exist: move the owners onto admin, keeping any they hold already.
                foreach (DB::table('model_has_roles')->where('role_id', $superAdmin->id)->get() as $row) {
                    DB::table('model_has_roles')->insertOrIgnore([...((array) $row), 'role_id' => $admin->id]);
                }
                DB::table('model_has_roles')->where('role_id', $superAdmin->id)->delete();
            }

            $templates = DB::table('roles')->whereNull('business_id')->where('name', '!=', 'admin')->get();

            foreach (DB::table('businesses')->pluck('id') as $businessId) {
                foreach ($templates as $template) {
                    $copyId = DB::table('roles')->where('business_id', $businessId)->where('name', $template->name)
                        ->where('guard_name', $template->guard_name)->value('id');

                    if ($copyId === null) {
                        $copyId = DB::table('roles')->insertGetId([
                            'business_id' => $businessId,
                            'uuid' => (string) Str::uuid(),
                            'name' => $template->name,
                            'guard_name' => $template->guard_name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $permissions = DB::table('role_has_permissions')->where('role_id', $template->id)->pluck('permission_id');
                        DB::table('role_has_permissions')->insert($permissions->map(fn ($id) => ['permission_id' => $id, 'role_id' => $copyId])->all());
                    }

                    foreach (DB::table('model_has_roles')->where('role_id', $template->id)->where('business_id', $businessId)->get() as $row) {
                        DB::table('model_has_roles')->insertOrIgnore([...((array) $row), 'role_id' => $copyId]);
                    }
                    DB::table('model_has_roles')->where('role_id', $template->id)->where('business_id', $businessId)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Assignments move back to the templates; the copies stay (they may
            // carry changes a business made).
            $templates = DB::table('roles')->whereNull('business_id')->where('name', '!=', 'admin')->pluck('id', 'name');
            foreach (DB::table('roles')->whereNotNull('business_id')->whereIn('name', $templates->keys())->get() as $copy) {
                foreach (DB::table('model_has_roles')->where('role_id', $copy->id)->get() as $row) {
                    DB::table('model_has_roles')->insertOrIgnore([...((array) $row), 'role_id' => $templates[$copy->name]]);
                }
                DB::table('model_has_roles')->where('role_id', $copy->id)->delete();
            }

            if (! DB::table('roles')->whereNull('business_id')->where('name', 'super-admin')->exists()) {
                DB::table('roles')->whereNull('business_id')->where('name', 'admin')->update(['name' => 'super-admin', 'updated_at' => now()]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_operator');
        });
    }
};
