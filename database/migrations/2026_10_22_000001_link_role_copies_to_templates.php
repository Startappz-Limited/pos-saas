<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Links each business's copy of a role template to the template (Phase 5,
 * A2): `roles.template_id`, so a copy finds its template without matching
 * names, and can be reset to it.
 *
 * Carries data across:
 * - every existing copy (a business's role named like a shared template
 *   other than `admin`) gets its template's id;
 * - a business that deleted a copy before copies were protected sees the
 *   shared template in its place and may have given it to members. Its copy
 *   is restored (with the template's current permissions) and those members'
 *   grants move onto it, as the stage 3 migration did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'template_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->foreignId('template_id')->nullable()->after('business_id')->constrained('roles')->nullOnDelete();
            });
        }

        DB::transaction(function (): void {
            $templates = DB::table('roles')->whereNull('business_id')->where('name', '!=', 'admin')->get();

            foreach (DB::table('businesses')->pluck('id') as $businessId) {
                foreach ($templates as $template) {
                    $copyId = DB::table('roles')->where('business_id', $businessId)->where('name', $template->name)
                        ->where('guard_name', $template->guard_name)->value('id');

                    if ($copyId === null) {
                        $copyId = DB::table('roles')->insertGetId([
                            'business_id' => $businessId,
                            'template_id' => $template->id,
                            'uuid' => (string) Str::uuid(),
                            'name' => $template->name,
                            'guard_name' => $template->guard_name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('role_has_permissions')->insert(
                            DB::table('role_has_permissions')->where('role_id', $template->id)->pluck('permission_id')
                                ->map(fn ($id): array => ['permission_id' => $id, 'role_id' => $copyId])->all()
                        );
                    } else {
                        DB::table('roles')->where('id', $copyId)->whereNull('template_id')->update(['template_id' => $template->id]);
                    }

                    foreach (DB::table('model_has_roles')->where('role_id', $template->id)->where('business_id', $businessId)->get() as $row) {
                        DB::table('model_has_roles')->insertOrIgnore([...((array) $row), 'role_id' => $copyId]);
                    }
                    DB::table('model_has_roles')->where('role_id', $template->id)->where('business_id', $businessId)->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Restored copies stay: members' grants are on them now.
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
