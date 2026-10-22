<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * A role can be switched off (Phase 5, A3): `roles.is_active`. An inactive
 * role grants nothing, while the people holding it keep the assignment.
 *
 * Additive: every existing role is active (the column's default), so nobody's
 * access changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'is_active')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('guard_name');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
