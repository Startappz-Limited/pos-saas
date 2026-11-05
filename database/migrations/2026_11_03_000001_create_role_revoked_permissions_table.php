<?php

use App\Support\Authorization\RolloutBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions taken off a role on purpose (stage 10, step 0), so the rollout
 * (`RoleSeeder`) gives a role its template's permissions minus these instead of
 * guessing from timestamps, which missed businesses.
 *
 * The backfill runs here, in this migration's transaction, so every absence a
 * business made by hand is recorded before any new-style `RoleSeeder` (through
 * `permissions:sync`, after the migrations) can run. See RolloutBackfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_revoked_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
        });

        DB::transaction(function (): void {
            $report = app(RolloutBackfill::class)->run(null, apply: true);
            $granted = array_sum(array_map(fn (array $row): int => count($row['granted']), $report));
            $revoked = array_sum(array_map(fn (array $row): int => count($row['revoked']), $report));

            if (! app()->runningUnitTests()) {
                echo "  Role rollout backfill: {$granted} permission(s) granted, {$revoked} recorded as revoked, across ".count($report)." role copies.\n";
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_revoked_permissions');
    }
};
